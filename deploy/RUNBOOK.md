# Production deploy runbook — lms.infinitynexatech.com

Step-by-step deployment to the existing **Infinity CRM CPX22** Hetzner box. Co-hosts alongside `crm.infinitynexatech.com`. Treat this as the canonical "from zero to live" sequence; rerun `deploy/redeploy.sh` after the first run to refresh seed data.

## Prerequisites

- Root SSH access to the CRM box
- DNS access at your registrar
- The CRM Caddy is already terminating TLS on :80 / :443
- About **15 minutes** of wall time (5 min build + 2 min install + everything else fast)

---

## 1. DNS — your registrar

Add an A record:

| Type | Name | Value | TTL |
|---|---|---|---|
| A | `lms` | `<CRM_VPS_IPv4>` | 600 |

Verify before continuing:

```bash
nslookup lms.infinitynexatech.com 8.8.8.8
# should return <CRM_VPS_IPv4>
```

(Wait until propagation completes — usually < 2 minutes.)

## 2. SSH to the box

```bash
ssh root@<CRM_VPS_IPv4>
```

## 3. Run the bootstrap

```bash
mkdir -p /opt/infinity-learn
cd /opt
git clone https://github.com/infinitynexatech-lab/LMS.git infinity-learn
cd /opt/infinity-learn
bash deploy/setup.sh
```

`setup.sh` will:
- `apt-get install` git/curl/openssl/ufw (idempotent)
- `curl get.docker.com` if docker isn't present (no-op on the CRM box)
- Skip UFW reconfiguration (CRM already configured it)
- Generate `/opt/infinity-learn/.env` with random DB & admin passwords
- `docker compose -f docker-compose.prod.yml build` (≈ 5 min — pulls php:8.3-apache, builds extensions, clones Moodle 4.5 LTS)
- `docker compose -f docker-compose.prod.yml up -d`

Watch the build output. When it returns, Moodle is starting and binding to `127.0.0.1:8090` on the host.

## 4. Wait for first-boot install (~2 min)

```bash
docker compose -f /opt/infinity-learn/docker-compose.prod.yml logs -f moodle
# Stop watching once you see "Installation completed successfully" and Apache "resuming normal operations"
```

Sanity check:

```bash
curl -sI http://localhost:8090
# HTTP/1.1 200 OK + Server: Apache/2.4.x (Debian)
```

## 5. Wire up Caddy

The CRM box's Caddy currently terminates `crm.infinitynexatech.com`. Add the LMS block.

**Find the CRM Caddyfile** (most likely paths):
- `/etc/caddy/Caddyfile` (if Caddy runs on the host via systemd)
- `/opt/infinity-crm/deploy/Caddyfile` (if Caddy runs inside CRM's docker-compose)

**Append** the block from `/opt/infinity-learn/deploy/Caddyfile` (the file in this repo). The block is:

```caddy
lms.infinitynexatech.com {
    encode gzip zstd
    reverse_proxy localhost:8090 {
        header_up X-Forwarded-Proto https
        header_up X-Real-IP {remote_host}
    }
    header {
        -Server
        Strict-Transport-Security "max-age=31536000; includeSubDomains"
        X-Content-Type-Options "nosniff"
        Referrer-Policy "strict-origin-when-cross-origin"
    }
}
```

> **If Caddy is in CRM's docker-compose**, swap `localhost:8090` → `host.docker.internal:8090` and add to CRM's caddy service:
> ```yaml
> extra_hosts:
>   - "host.docker.internal:host-gateway"
> ```

**Reload Caddy:**

```bash
# Host Caddy
systemctl reload caddy

# OR: Caddy in CRM compose
docker compose -f /opt/infinity-crm/docker-compose.prod.yml exec -T caddy \
  caddy reload --config /etc/caddy/Caddyfile
```

**Verify TLS** (Let's Encrypt cert issuance usually takes < 30s on first hit):

```bash
curl -sI https://lms.infinitynexatech.com
# HTTP/2 200 + strict-transport-security header
```

Open the URL in a browser. Confirm the login page renders with the indigo gradient. Log in with `admin` and the password in `/opt/infinity-learn/.env` (`ADMIN_PASSWORD=`).

## 6. Seed sample data

```bash
cd /opt/infinity-learn
bash deploy/redeploy.sh
```

> **Warning:** `redeploy.sh` runs `docker compose down -v` first — it **wipes the DB and all course content**. Only safe to run when there's no real user data. After first-time deploy, this is the right call to populate the demo.

What it does, in order:
1. `git pull --ff-only`
2. `down -v` (DB wipe), `build`, `up -d`
3. Waits for Moodle's first-boot install
4. Runs `seed/categories.php`, `seed/courses.php`, `seed/roles.php`
5. `seed/users.sh` — 4 demo users + role assignment
6. `seed/attach_scorm.php` — SCORM 1.2 sample attached to the GDPR course

When it finishes (≈ 3–4 min total), the demo is live with:
- 5 categories (Corporate / Customer / Partner / Public / Academic)
- 7 sample courses
- 4 demo users (`sarah`, `miguel`, `priya`, `alex` — all password `Strong@2026`)
- 4 custom roles assigned
- 1 working SCORM 1.2 activity

## 7. Verify the demo

| Check | URL |
|---|---|
| Front page renders with theme | https://lms.infinitynexatech.com |
| Login as `sarah` (Corporate Admin) | https://lms.infinitynexatech.com/login |
| GDPR course with SCORM activity | Courses → Corporate Training → GDPR Compliance |
| Click the SCORM activity | should launch the 3-slide SCO, "Mark complete" reports back to Moodle |

## 8. Daily backup cron

```bash
cat > /etc/cron.daily/lms-backup <<'EOF'
#!/usr/bin/env bash
set -eu
cd /opt/infinity-learn
set -a; source .env; set +a

mkdir -p /var/backups/lms
docker compose -f docker-compose.prod.yml exec -T mariadb \
  mariadb-dump -u moodle -p"$DB_PASSWORD" moodle | gzip > /var/backups/lms/db-$(date +%F).sql.gz

tar czf /var/backups/lms/moodledata-$(date +%F).tar.gz \
  -C /var/lib/docker/volumes/lms_moodledata _data

find /var/backups/lms -mtime +14 -delete
EOF
chmod +x /etc/cron.daily/lms-backup
```

## 9. Rollback / cleanup

```bash
# Stop + remove containers, KEEP volumes (preserves DB + uploads)
docker compose -f /opt/infinity-learn/docker-compose.prod.yml down

# FULL wipe (removes DB + uploads — destructive!)
docker compose -f /opt/infinity-learn/docker-compose.prod.yml down -v

# Remove the install dir entirely
rm -rf /opt/infinity-learn

# Remove Caddy lms.* block + reload
```

## What's deferred

- **Jitsi plugin auto-install** — repo URL needs verification (see `seed/install_plugins.sh`); install via admin UI for now: Site administration → Plugins → Install plugins → search "Jitsi".
- **Real Stripe / PayPal credentials** — the demo ships a stub PayPal enrol. Wire real keys once the first paying customer signs.
- **BigBlueButton** — needs a dedicated CPX31+ node (≥ 4 vCPU, ≥ 8 GB RAM, UDP 16384–32768). Out of scope for the shared-CPX22 demo.

## When you outgrow the shared CPX22

Migration is straightforward:

```bash
# On old box
docker compose -f /opt/infinity-learn/docker-compose.prod.yml exec -T mariadb \
  mariadb-dump -u moodle -p"$DB_PASSWORD" moodle > /tmp/lms.sql
tar czf /tmp/moodledata.tar.gz \
  -C /var/lib/docker/volumes/lms_moodledata _data
scp /tmp/{lms.sql,moodledata.tar.gz} root@<NEW_VPS>:/tmp/

# On new box: setup.sh, then before redeploy.sh, restore:
docker compose -f docker-compose.prod.yml exec -T mariadb \
  mariadb -u moodle -p"$DB_PASSWORD" moodle < /tmp/lms.sql
tar xzf /tmp/moodledata.tar.gz \
  -C /var/lib/docker/volumes/lms_moodledata _data
```

Update DNS A record to the new IP and reload Caddy on the new node.
