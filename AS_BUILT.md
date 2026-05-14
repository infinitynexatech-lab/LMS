# Infinity Learn — As-Built / Rebuild Guide

A complete walkthrough for rebuilding this LMS from scratch. Built on top of **Moodle 4.5 LTS** (GPL v3), the most mature open-source LMS (≈40 % global market share — Walmart, Open University, India's SWAYAM). Everything below has been verified against a custom Dockerfile that layers Moodle's `MOODLE_405_STABLE` git branch on top of the official `php:8.3-apache-bookworm` image.

Concrete values used in this build:

| Setting | Value |
|---|---|
| Domain | lms.infinitynexatech.com |
| Repo | https://github.com/infinitynexatech-lab/LMS |
| Moodle source | github.com/moodle/moodle @ `MOODLE_405_STABLE` (4.5 LTS, security-patched through Dec 2027) |
| Base image | `php:8.3-apache-bookworm` (official PHP image, Apache-2.0) |
| DB image | `mariadb:11.4` (official MariaDB, GPLv2) |
| Theme name | `theme_infinity` (child of Boost) |
| Local plugin | `local_infinity` (branding + seed) |
| Brand name | Infinity Learn |
| Admin email | admin@infinitynexatech.com |
| Primary color | `#6366F1` (indigo — matches Infinity CRM) |
| VPS | Hetzner Cloud CPX22, Ubuntu 24.04 (co-hosted with CRM for the demo) |
| Install dir | /opt/infinity-learn |

## 0. Architecture at a glance

```
                ┌────────────────────────────────┐
internet ─────▶ │ Caddy (80/443, auto Let's      │
                │ Encrypt TLS) — shared with CRM │
                └──────────────┬─────────────────┘
                               │ reverse_proxy to 127.0.0.1:8090
                               ▼
                ┌────────────────────────────────┐
                │ Apache + PHP 8.3 + Moodle 4.5  │
                │ (custom Dockerfile)            │
                │ - /            front page      │
                │ - /login       login page      │
                │ - /admin/      site admin      │
                │ - /course/, /mod/, /user/      │
                │ - theme/infinity (overlay)     │
                │ - local/infinity (overlay)     │
                └──────────────┬─────────────────┘
                               │
                               ▼
                ┌────────────────────────────────┐
                │ MariaDB 11.4                   │
                │ - mdl_course, mdl_course_*     │
                │ - mdl_user, mdl_role, mdl_*    │
                │ - mdl_config, mdl_config_plugins│
                │ - mdl_scorm, mdl_quiz, mdl_*   │
                └────────────────────────────────┘

  Optional live-classroom path (separate node when scaled):
   - Jitsi plugin → meet.jit.si  (free, demo-grade)
   - BigBlueButton plugin → self-host on dedicated CPX31+ node
```

Everything except runtime user data (course content, accounts, attempt records) is git-tracked. A fresh customer environment is one `bash deploy/redeploy.sh` away.

## 1. Stack & infra

| Layer | Choice | Why |
|---|---|---|
| App | Moodle 4.5 LTS source pinned to `MOODLE_405_STABLE` | GPL v3, mature, native SCORM/xAPI, ~2000 plugins. Pinned to LTS branch so we get security patches but no breaking changes. |
| Image build | Custom `Dockerfile` from `php:8.3-apache-bookworm` | No third-party Moodle image dependencies. (Bitnami removed `bitnami/moodle` from public Docker Hub in 2025 — see §12 #1.) |
| DB | `mariadb:11.4` (Docker Inc official) | Moodle's recommended DB; utf8mb4 by default. |
| Reverse proxy | Caddy 2-alpine | Auto-Let's-Encrypt, near-zero config (same Caddy as CRM). |
| Host | Hetzner Cloud CPX22 (shared with CRM for demo) | 2 vCPU / 4 GB / 80 GB SSD. Resize to CPX31+ before BBB or > 50 concurrent learners. |
| OS | Ubuntu 24.04 | Same as CRM box. |
| Containers | Docker + Compose | One-file dev/prod parity. |

## 2. Repo layout

```
LMS/                                # fork at infinitynexatech-lab/LMS
├── Dockerfile                      # php:8.3-apache + Moodle 4.5 source + extensions
├── docker/
│   ├── apache-moodle.conf          # Apache vhost (DocumentRoot, AllowOverride)
│   └── entrypoint.sh               # waits for DB, runs admin/cli/install.php on first boot
├── docker-compose.yml              # local dev: builds the image, MariaDB, port 8080
├── docker-compose.prod.yml         # production: same + restart unless-stopped, 127.0.0.1:8090
├── .env.example                    # template (real .env is gitignored)
├── .gitignore .gitattributes
├── deploy/
│   ├── Caddyfile                   # snippet to add to host Caddy or CRM's caddy
│   ├── setup.sh                    # one-shot VPS bootstrap
│   ├── redeploy.sh                 # wipe + re-provision + run seeds
│   └── README.md
├── theme/
│   └── infinity/                   # child theme of Boost (mounted into image)
│       ├── version.php config.php lib.php settings.php
│       ├── scss/post.scss          # brand CSS appended after Boost
│       └── lang/en/theme_infinity.php
├── local/
│   └── infinity/                   # branding + seed plugin (mounted into image)
│       ├── version.php
│       ├── db/install.php          # sets theme + sitename + logo on install
│       ├── pix/logo.svg            # source logo (installed into core_admin/logo filearea)
│       └── lang/en/local_infinity.php
├── seed/                           # ran after first boot OR via deploy/redeploy.sh
│   ├── README.md                   # run order
│   ├── install_plugins.sh          # Jitsi (and any other GitHub-hosted plugins)
│   ├── categories.php              # Corporate / Customer / Partner / Public / Academic
│   ├── courses.php                 # one sample course per audience
│   ├── roles.php                   # 4 custom Infinity roles
│   └── users.sh                    # 4 demo users via admin/cli/create_user.php
├── README.md
└── AS_BUILT.md                     # this file
```

Moodle's ~50k upstream files live inside the built image — never committed to this repo. The only Moodle-touching files we track are `theme/infinity/`, `local/infinity/`, `seed/*.php`, and the entrypoint. **Treat the fork like a config overlay.**

## 3. Local dev environment

### 3a. First boot

```bash
cp .env.example .env             # only DB_ROOT_PASSWORD / DB_PASSWORD needed in dev (optional)
docker compose build moodle      # ~5 min first time (apt + PHP extensions + git clone Moodle)
docker compose up -d
docker compose logs -f moodle    # wait for "==> DB reachable" then admin/cli/install.php output
```

Open <http://localhost:8081>. Log in with `admin / AdminPass!2026`.

### 3b. Verify the overlay is mounted

```bash
docker compose exec moodle ls /var/www/html/theme/infinity
docker compose exec moodle ls /var/www/html/local/infinity
```

Both directories should list our files. If they're empty, Compose isn't bind-mounting — re-run from the repo root.

### 3c. Apply changes after edit

| Change to… | Action |
|---|---|
| `theme/infinity/scss/post.scss` (cosmetic only) | **Site administration → Appearance → Themes → Purge caches** |
| `theme/infinity/{version,config,lib}.php` OR any `local/infinity/**` | Bump the affected `version.php`, then `docker compose exec moodle php /var/www/html/admin/cli/upgrade.php --non-interactive` |
| `Dockerfile`, `docker/*.conf`, `docker/entrypoint.sh` | `docker compose build moodle && docker compose up -d` |

Moodle's `upgrade.php` is non-destructive — DB data is preserved.

### 3d. Rebuild from scratch (wipe everything)

```bash
docker compose down -v
docker compose build moodle
docker compose up -d
```

Equivalent to Corteza's `down -v` → "re-provision."

## 4. Branding

### 4a. The two-piece overlay

| Piece | Purpose |
|---|---|
| `theme/infinity` | Child theme of Boost. Owns SCSS variables, post-CSS, login background. |
| `local/infinity` | Local plugin. Owns DB-side branding (site name, support email, theme = 'infinity', site logo). |

The theme covers **visual** branding; the local plugin covers **textual / config / asset** branding. Mirrors Corteza's `provision/branding/` (assets) vs `provision/settings/branding.yaml` (config).

### 4b. Theme colors

Brand colors enter Moodle's SCSS via the `prescss` callback in `theme/infinity/lib.php` — it injects `$primary`, `$secondary`, `$success`, etc. before Boost's `_variables.scss` is compiled. To rebrand:

1. Edit `theme/infinity/lib.php → theme_infinity_get_pre_scss()`.
2. Edit `theme/infinity/scss/post.scss` for any rules that target specific selectors (login gradient, button gradient, dashboard cards, etc.).
3. Bump `theme/infinity/version.php → $plugin->version` (yyyymmddNN).
4. Run upgrade.php (see §3c) or **Purge theme caches**.

### 4c. Site name / support strings

Set in `local/infinity/db/install.php → xmldb_local_infinity_install()`. Runs **once** on install. To change after install:

- Quick path: **Site administration → Front page settings**.
- Repeatable path: edit `db/install.php`, write a `db/upgrade.php` with an upgrade step, bump `local/infinity/version.php`. Don't try to re-run install hooks — they're one-shot by design.

### 4d. Logo

The repo ships `local/infinity/pix/logo.svg`. On first install, `local_infinity` copies it into Moodle's `core_admin / logo / 0 /` filearea via the Files API — equivalent to manually uploading it in **Site administration → Appearance → Logos**.

To replace the logo: edit `local/infinity/pix/logo.svg`, then either re-upload via the admin UI or write a `db/upgrade.php` step that calls the same `local_infinity_install_brand_file()` helper.

## 5. Course taxonomy — seed/categories.php

The front-page category tree covers all four audiences in one demo:

```
Infinity Learn
├── Corporate Training      (employee onboarding, compliance)
├── Customer Training       (product certification for B2B buyers)
├── Partner Enablement      (channel-partner courses, NDA-gated)
├── Public Catalog          (paid, anyone can self-enrol)
└── Academic                (term-based, instructor-led, gradebook)
```

Created via `core_course_category::create()` from `seed/categories.php`. Idempotent — re-running skips existing rows.

## 6. Sample courses — seed/courses.php

One course per audience exercises a different Moodle feature so the demo doesn't look like one trick:

| Course | Demonstrates |
|---|---|
| "Welcome to Infinity Learn" | Front-page course, Content blocks |
| "GDPR Compliance" | SCORM 1.2 package (placeholder until real package uploaded) |
| "Product Certification" | Quiz + cert-on-pass |
| "Channel Sales Playbook" | Cohort-only enrolment (NDA-gated) |
| "Advanced Excel (Paid Demo)" | PayPal enrol — stubbed gateway |
| "Live: Q1 All-Hands" | Jitsi activity (live virtual classroom) |
| "Intro to Statistics" | Term-based academic with gradebook |

Created via `create_course()` from `seed/courses.php`. Real SCORM/xAPI packages drop in `seed/packages/` and are attached in a follow-up iteration.

## 7. Live virtual classroom

### 7a. Default for the demo — Jitsi

The `mod_jitsi` plugin (GPL v2) embeds a Jitsi Meet room as a course activity. Free public `meet.jit.si` works out of the box; no extra container, no public-IP allowance.

Install (handled by `seed/install_plugins.sh`):
```bash
docker compose exec moodle bash -lc '
  cd /var/www/html/mod &&
  git clone --depth=1 https://github.com/UDIMA/moodle-mod_jitsi.git jitsi &&
  chown -R www-data:www-data jitsi'
docker compose exec moodle php /var/www/html/admin/cli/upgrade.php --non-interactive
```

Configure: **Site administration → Plugins → Activity modules → Jitsi** → set server URL to `https://meet.jit.si` (default), leave JWT off.

### 7b. Production upgrade — BigBlueButton

BBB self-hosts and gives breakout rooms, polls, multi-user whiteboard, recording. Requirements:
- Dedicated node (Ubuntu 22.04, ≥ 4 vCPU, ≥ 8 GB RAM, public IPv4, UDP 16384–32768).
- Install with `bbb-install-2.7.sh` from bigbluebutton.org docs.
- In Moodle: **Plugins → Activity modules → BigBlueButton** → server URL + shared secret.

**Do not** attempt BBB on the CPX22 demo node — it will crash the box.

## 8. Paid courses

Native enrol plugins shipped with Moodle 4.5:

| Plugin | Status | Notes |
|---|---|---|
| `enrol_paypal` | Core | PayPal IPN. Working but PayPal-only. |
| `enrol_stripepayment` | Community | https://github.com/dualcube/moodle-enrol_stripepayment |
| `enrol_razorpay` | Community | Useful for India market. |

For the demo, **enable `enrol_paypal` with sandbox credentials** — matches your Astro-site pattern of stubbed payment gateways. Real gateway integration goes in a later iteration once a real customer signs.

Enable: **Site administration → Plugins → Enrolments → Manage enrol plugins** → enable PayPal. Per-course: course settings → enrolment methods → add PayPal → set amount + currency.

## 9. SCORM / xAPI

Both are **native** — no plugin install needed.

- **SCORM 1.2 / 2004**: course → add activity → SCORM package → upload `.zip`. Moodle parses imsmanifest, plays the package via its built-in player, records `cmi.*` data in `mdl_scorm_scoes_value`.
- **xAPI / Tin Can**: enable LRS plugin `logstore_xapi` to forward statements to an external LRS, or add an LTI 1.3 activity for content that emits xAPI statements.

For the demo, ship one SCORM 1.2 sample (Articulate's free "Hello World") and one xAPI sample (Rustici Driver's "Golf Example") in `seed/packages/`, attached to the GDPR and Product Certification courses.

## 10. RBAC — roles & users

Moodle ships 8 built-in roles. We add 4 custom roles via `seed/roles.php`:

| Role shortname | Cloned from | Purpose |
|---|---|---|
| `infinity_corporate_admin` | manager | Limited to "Corporate Training" category. |
| `infinity_customer_csm` | coursecreator | Can create customer-onboarding courses. |
| `infinity_partner_lead` | editingteacher | Read-only catalog + edit partner-enablement courses. |
| `infinity_paying_learner` | student | Default for self-enrol via PayPal. |

Each new role inherits its archetype's capabilities (copied at create-time, then editable independently).

Demo users — never insert into `mdl_user` directly (Moodle uses salted password hashing with rehash). Use the CLI:

```bash
docker compose exec moodle php /var/www/html/admin/cli/create_user.php \
  --username=sarah --password='Strong@2026' \
  --email=sarah@infinitynexatech.com --firstname=Sarah --lastname=Johnson \
  --ignore-password-policy
```

`seed/users.sh` wraps this for 4 demo accounts. Role assignment happens via Cohorts (admin UI) or a `seed/assign_roles.php` follow-up (deferred).

## 11. Production deployment

### 11a. DNS + co-hosting

Existing CRM box on CPX22 has spare capacity for the Moodle demo. Add an A record at the registrar:

```
lms.infinitynexatech.com  A  <CRM_VPS_IPv4>  TTL 600
```

Caddy on the CRM box already handles 80/443 — extend its Caddyfile to add the `lms.` site block reverse-proxying to a new Moodle container. See `deploy/Caddyfile` for the two routing options (host-Caddy vs CRM-Caddy-in-Docker).

### 11b. docker-compose.prod.yml

Differences from dev compose:
- `restart: unless-stopped` on both services
- DB password from `${DB_PASSWORD}` (`.env`)
- Admin user/password from `${ADMIN_*}` (`.env`)
- `MOODLE_REVERSEPROXY: "true"` — Bitnami env-var name is reused by our entrypoint to inject `$CFG->reverseproxy = true; $CFG->sslproxy = true;` into config.php
- Moodle exposed on `127.0.0.1:8090` only (loopback) — Caddy proxies in

### 11c. Caddy snippet

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
    }
}
```

(If Caddy lives in CRM's compose, swap `localhost:8090` for `host.docker.internal:8090` and add `extra_hosts: ["host.docker.internal:host-gateway"]` to the caddy service.)

### 11d. setup.sh / redeploy.sh

Same pattern as CRM:
- `setup.sh` — apt update, docker install, conditional UFW (skips if CRM already configured it), clone repo, generate secrets, `docker compose up -d`.
- `redeploy.sh` — `git pull`, `down -v` (wipes DB), `build`, `up -d`, run all `seed/*` to repopulate.

### 11e. Backups

```cron
# /etc/cron.daily/lms-backup
docker compose -f /opt/infinity-learn/docker-compose.prod.yml exec -T mariadb \
  mariadb-dump -u moodle -p"$DB_PASSWORD" moodle | gzip > /var/backups/lms-$(date +%F).sql.gz
# moodledata (uploaded files, course backups, SCORM packages) — tar it
tar czf /var/backups/lms-moodledata-$(date +%F).tar.gz \
  -C /var/lib/docker/volumes/infinity-learn_moodledata _data
find /var/backups -name 'lms-*' -mtime +14 -delete
```

Two artifacts per day, since Moodle splits state across DB + filesystem (unlike Corteza which keeps everything in Postgres).

## 12. The gotcha list

| # | Gotcha | Symptom | Fix |
|---|---|---|---|
| 1 | **Bitnami removed bitnami/moodle and tagged mariadb tags from public Docker Hub in 2025** | `failed to resolve reference "docker.io/bitnami/mariadb:11.4": not found` during `docker compose up` | We pivoted to a custom Dockerfile from `php:8.3-apache` + the official `mariadb:11.4` image. Never reintroduce a `bitnami/*` pin without verifying it's still public. |
| 2 | Bitnami first-boot env vars only apply once | Changing `MOODLE_SITE_NAME` in compose after first boot has no effect | We don't use Bitnami any more, but our entrypoint has the same property. Re-seed via `local_infinity` upgrade, OR `docker compose down -v` and re-up. |
| 3 | Theme code changes don't appear | CSS edits invisible | **Site administration → Appearance → Themes → Purge theme caches** (or set `$CFG->themedesignermode = true;` in dev). |
| 4 | Plugin code changes don't appear | New menu items absent | Run `php /var/www/html/admin/cli/upgrade.php --non-interactive` and bump `version.php`. |
| 5 | Cron not running | Course completion stuck, scheduled tasks pending | Our image doesn't run cron by default — invoke manually for the demo: `docker compose exec moodle php /var/www/html/admin/cli/cron.php`. Add a real cron container in §11 for prod. |
| 6 | Reverse proxy without `$CFG->reverseproxy = true` | Mixed-content warnings, login redirects to http | The entrypoint injects this when `MOODLE_REVERSEPROXY=true`. Verify with `docker compose exec moodle grep -i reverseproxy /var/www/html/config.php`. |
| 7 | `enrol_paypal` requires public IPN URL | Payment marks "pending" forever in dev | Use ngrok or stub the gateway (matches Astro-sites pattern). |
| 8 | SCORM 2004 4th edition behaves differently from 1.2 | Reports show "in progress" indefinitely | Test SCORM packages against Moodle's `SCORM Cloud Player` first; some Articulate exports are buggy. |
| 9 | Moodle install fails with "moodledata not writable" | `admin/cli/install.php` exits non-zero | The entrypoint `chown`s `moodledata`, but bind-mounts from the host with different UID can override it. Use a named volume (we do) rather than a host bind for `moodledata`. |
| 10 | CRLF line endings on shell scripts | `bad interpreter` in container | `.gitattributes` declares `*.sh text eol=lf`. If still broken, `git config core.autocrlf input` on Windows. |
| 11 | `php:8.3-apache` WORKDIR is `/var/www/html` — `rm -rf` from inside it kills git | `fatal: Unable to read current working directory` during Dockerfile `git clone` | `WORKDIR /tmp` before the rm/clone, then `WORKDIR /var/www/html` after. Subtle: error happens in the NEXT command (git), not the rm itself. |
| 12 | Port 8080 already taken (Corteza dev) | `Bind for 0.0.0.0:8080 failed: port is already allocated` on `docker compose up` | LMS dev compose uses **8081** (Corteza uses 8080). Prod compose uses **127.0.0.1:8090** loopback. |
| 13 | Git Bash on Windows mangles Linux paths passed to `docker exec` | `until docker compose exec -T moodle test -f /var/www/html/config.php` loops forever even after the file exists; manual `ls` shows `C:/Program Files/Git/var/www/html/...` | Prefix the in-container path with `//` (e.g. `//var/www/html/config.php`) OR `export MSYS_NO_PATHCONV=1` before invoking docker exec from Git Bash. Doesn't affect PowerShell or WSL. |

(Add to this table as you hit each one — the CRM doc filled this in over the full build.)

## 13. Build order (TL;DR)

- **Day 0**. Write `Dockerfile`, `docker/entrypoint.sh`, `docker/apache-moodle.conf`, `docker-compose.yml`, `.env.example`, `.gitignore`, `.gitattributes`. `docker compose build moodle && docker compose up -d`. Verify Moodle boots at http://localhost:8081.
- **Day 0**. `theme/infinity/` skeleton + `local/infinity/` skeleton (mounted as overlays). After first boot, confirm site is named "Infinity Learn" and theme = Infinity.
- **Day 1**. Refine `theme/infinity/scss/post.scss` and `theme_infinity_get_pre_scss()` against rendered pages. Drop the SVG logo into `local/infinity/pix/`.
- **Day 2**. `seed/categories.php` + `seed/courses.php`. Wire into `redeploy.sh`. Drop one SCORM + one xAPI sample into `seed/packages/`.
- **Day 2**. Install Jitsi plugin via `seed/install_plugins.sh`. Add a Jitsi activity to "Live: Q1 All-Hands".
- **Day 3**. Stub PayPal enrol on "Advanced Excel". `seed/roles.php` for custom roles, `seed/users.sh` for demo users.
- **Day 4**. `docker-compose.prod.yml`, `deploy/Caddyfile`, `deploy/setup.sh`, `deploy/redeploy.sh`. DNS A record. Verify https://lms.infinitynexatech.com.
- **Day 4**. Daily backups (DB + moodledata).
- **Day 5**. Fill in §12 gotchas with everything that bit you. Ship.

Total active dev time: ~4–5 working days. (Slightly more than CRM because Moodle has a larger native surface area to brand than Corteza.)

## 14. License

Moodle is **GPL v3**. Implications for this build:

- **Self-hosting + modifications + selling access**: ✅ Fully allowed.
- **Distributing modified Moodle to a customer** (e.g. handing them a Docker image): must also provide source under GPL v3. Easy — link them to this repo.
- **`theme_infinity` + `local_infinity`**: inherit GPL v3 because they `require_once` Moodle's GPL code. Standard for every Moodle plugin in the marketplace.
- **Trademark "Moodle"**: owned by Moodle Pty Ltd. We're free to use the name in technical docs (this AS_BUILT), but the **user-visible product name is "Infinity Learn"**, not "Moodle". Never put "Moodle" in marketing copy, screenshots' chrome, or front-end strings.
- **AGPL question** (does running this as a SaaS trigger source-disclosure obligations to end users): No — Moodle is GPL v3, not AGPL v3. SaaS deployment without source disclosure is permitted. (Canvas LMS is AGPL — that's why we didn't pick it.)

Bottom line: zero IP risk for the white-label + self-host + sell-access business model. Same posture as Corteza (Apache 2.0) for the CRM — just a different open-source license.
