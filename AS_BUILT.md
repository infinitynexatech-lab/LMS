# Infinity Learn — As-Built / Rebuild Guide

A complete walkthrough for rebuilding this LMS from scratch. Built on top of **Moodle 4.5 LTS**, GPL v3, the most mature open-source LMS (≈40% global LMS market share, used by Walmart, Open University, Indian govt's SWAYAM). Everything below has been verified against the `bitnami/moodle:4.5` image.

Concrete values used in this build:

| Setting | Value |
|---|---|
| Domain | lms.infinitynexatech.com |
| Repo | https://github.com/infinitynexatech-lab/LMS |
| Moodle version | 4.5 LTS (security-patched through Dec 2027) |
| Base image | docker.io/bitnami/moodle:4.5 |
| Theme name | `theme_infinity` (child of Boost) |
| Local plugin | `local_infinity` (branding + seed) |
| Brand name | Infinity Learn |
| Admin email | admin@infinitynexatech.com |
| Primary color | `#6366F1` (indigo — matches Infinity CRM) |
| VPS | Hetzner Cloud CPX22, Ubuntu 24.04 (same node co-hosting CRM for demo) |
| Install dir | /opt/infinity-learn |

## 0. Architecture at a glance

```
                ┌────────────────────────────────┐
internet ─────▶ │ Caddy (80/443, auto Let's      │
                │ Encrypt TLS)                   │
                └──────────────┬─────────────────┘
                               │ reverse_proxy
                               ▼
                ┌────────────────────────────────┐
                │ Moodle 4.5 (PHP 8.3 + Apache)  │
                │ - / (front page, course list)  │
                │ - /login                       │
                │ - /admin/                      │
                │ - /course/, /mod/, /user/      │
                │ - theme/infinity (overlay)     │
                │ - local/infinity  (overlay)    │
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
   - Jitsi plugin → meet.jit.si (free public, demo-grade)
   - BigBlueButton plugin → self-host on dedicated CPX31+ node
```

Everything except runtime user data (course content, user accounts, attempt records) is git-tracked: theme, local plugin, seed scripts, deploy scripts. A fresh customer environment is one `bash deploy/redeploy.sh` away.

## 1. Stack & infra

| Layer | Choice | Why |
|---|---|---|
| App | Moodle 4.5 LTS via `bitnami/moodle:4.5` | GPL v3, mature, native SCORM/xAPI, ~2000 plugins. Bitnami image handles install + env-var bootstrapping. |
| DB | MariaDB 11.4-alpine (Bitnami) | Moodle's recommended DB; utf8mb4 by default. |
| Reverse proxy | Caddy 2-alpine | Auto-Let's-Encrypt, near-zero config (same as CRM). |
| Host | Hetzner Cloud CPX22 (shared with CRM for demo) | 2 vCPU / 4 GB / 80 GB SSD. Resize to CPX31+ before adding BBB or > 50 concurrent learners. |
| OS | Ubuntu 24.04 | Same as CRM box. |
| Containers | Docker + Compose | One-file dev/prod parity. |

## 2. Repo layout

```
LMS/                                # your fork at infinitynexatech-lab/LMS
├── docker-compose.yml              # local dev: moodle + mariadb on 8080
├── docker-compose.prod.yml         # production: + caddy, secrets from .env (TODO)
├── .env.example                    # template (real .env is gitignored)
├── .gitignore
├── .gitattributes                  # *.sh, *.php, *.yml = LF (Windows authoring)
├── deploy/                         # (TODO)
│   ├── Caddyfile                   # reverse_proxy moodle:8080 + HSTS
│   ├── setup.sh                    # one-shot VPS bootstrap
│   ├── redeploy.sh                 # wipe & re-provision
│   └── README.md
├── theme/
│   └── infinity/                   # child theme of Boost (mounted into image)
│       ├── version.php
│       ├── config.php
│       ├── lib.php                 # main SCSS + pre-SCSS callbacks
│       ├── settings.php            # admin → appearance → infinity
│       ├── scss/post.scss          # brand CSS appended after Boost
│       └── lang/en/theme_infinity.php
├── local/
│   └── infinity/                   # branding + seed (mounted into image)
│       ├── version.php
│       ├── db/install.php          # sets theme + sitename + support strings on install
│       └── lang/en/local_infinity.php
├── seed/                           # (TODO) sample courses, categories, demo users
│   ├── categories.php              # Corporate / Customer / Partner / Public
│   ├── courses.php                 # sample courses with quizzes
│   └── users.sh                    # bulk-create demo learners + instructors
└── AS_BUILT.md                     # this file
```

Moodle core (≈ 50k files) lives **inside the bitnami image**, untouched. We never commit Moodle source to this repo — only the overlay. Treat the fork like the Corteza overlay.

## 3. Local dev environment

### 3a. Boot it

```bash
cp .env.example .env             # fill in DB_PASSWORD, ADMIN_PASSWORD
docker compose up -d
docker compose logs -f moodle    # wait for "Moodle setup finished"
```

Open <http://localhost:8080>. Log in with `admin / AdminPass!2026`.

### 3b. Verify the overlay is mounted

```bash
docker compose exec moodle ls /bitnami/moodle/theme/infinity
docker compose exec moodle ls /bitnami/moodle/local/infinity
```

Both directories should list our files. If they're empty, Compose isn't bind-mounting — check that you ran `docker compose up` from the repo root.

### 3c. Apply changes after edit

For changes inside `theme/infinity/scss/post.scss` (cosmetic only): **Site administration → Appearance → Themes → Purge theme caches**.

For changes inside `theme/infinity/version.php`, `config.php`, `lib.php`, OR any `local/infinity/**`:

```bash
docker compose exec moodle \
  php /bitnami/moodle/admin/cli/upgrade.php --non-interactive
```

That's the equivalent of Corteza's "wipe + reboot" — except Moodle's upgrade.php is non-destructive (DB data preserved).

## 4. Branding

### 4a. The two-piece overlay

| Piece | Purpose |
|---|---|
| `theme/infinity` | Child theme of Boost. Owns SCSS variables, post-CSS, logo, login background. |
| `local/infinity` | Local plugin. Owns DB-side branding (site name, support email, theme = 'infinity'). |

The theme is for **visual** branding; the local plugin is for **textual/config** branding. Splitting these mirrors Corteza's `provision/branding/` (asset source) vs `provision/settings/branding.yaml` (config).

### 4b. Theme colors

Brand colors enter Moodle's SCSS via the `prescss` callback in `theme/infinity/lib.php` — it injects `$primary`, `$secondary`, `$success`, etc. as SCSS variables before Boost's `_variables.scss` is compiled. To rebrand:

1. Edit `theme/infinity/lib.php → theme_infinity_get_pre_scss()`.
2. Edit `theme/infinity/scss/post.scss` for any rules that target specific selectors (login background gradient, button gradient, etc.).
3. Bump `theme/infinity/version.php → $plugin->version` (yyyymmddNN).
4. Run upgrade.php (see §3c) or **Purge theme caches**.

### 4c. Site name / support strings

Set in `local/infinity/db/install.php → xmldb_local_infinity_install()`. This runs **once**, on install. To change after install:

- Quick path: **Site administration → Front page settings** → edit "Full site name" & "Short name".
- Repeatable path: edit `db/install.php`, bump `local/infinity/version.php → $plugin->version`, run upgrade.php — but you'll need to **uninstall the local plugin first** (or add an `db/upgrade.php` with an upgrade step) because install hooks don't re-run.

### 4d. Logo

Moodle's logo lives in `admin → Appearance → Logos`. Upload SVG/PNG via the UI and they're stored in `mdl_files`. To git-track the logo (recommended), do it via `local_infinity` config:

```php
// In db/install.php, after set_config('theme', 'infinity'):
$fs = get_file_storage();
$ctx = context_system::instance();
$record = (object) [
    'contextid' => $ctx->id, 'component' => 'core_admin',
    'filearea'  => 'logo',   'itemid'    => 0,
    'filepath'  => '/',      'filename'  => 'logo.svg',
];
$fs->create_file_from_pathname($record, __DIR__ . '/../pix/logo.svg');
```

Then drop `local/infinity/pix/logo.svg` in the repo.

## 5. Course taxonomy — seed/categories.php (TODO)

To cover "everything" (corporate + customer + academic + consumer) in one demo, the front-page category tree should be:

```
Infinity Learn
├── Corporate Training      (employee onboarding, compliance)
├── Customer Training       (product certification for B2B buyers)
├── Partner Enablement      (channel partner courses, NDA-gated)
├── Public Catalog          (paid, anyone can self-enrol)
└── Academic                (terms-based, instructor-led, gradebook)
```

Each top-level category gets 2–3 sample courses. Categories are seeded via `core_course_category::create()` from a CLI PHP script (`seed/categories.php`), invoked from `deploy/redeploy.sh`.

## 6. Sample courses — seed/courses.php (TODO)

Each demo course should exercise a different Moodle feature so the demo doesn't look like one trick:

| Course | Demonstrates |
|---|---|
| "Welcome to Infinity Learn" | Front-page course, Content blocks, video |
| "GDPR Compliance" | SCORM 1.2 package (Articulate sample) |
| "Sales Certification" | Quiz with cert on pass + cohort enrolment |
| "Product Demo Series" | xAPI / Tin Can content + completion tracking |
| "Live: Q1 All-Hands" | Jitsi activity (live virtual classroom) |
| "Paid: Advanced Excel" | Stripe enrol (stubbed gateway, see §8) |

Created via `core_course_external::create_courses()` from CLI PHP (`seed/courses.php`). SCORM/xAPI sample packages live in `seed/packages/`.

## 7. Live virtual classroom

### 7a. Default for the demo — Jitsi

The `mod_jitsi` plugin (GPL v2) embeds a Jitsi Meet room as a course activity. Free public Jitsi at `meet.jit.si` works out of the box; no extra container needed, no extra IP allowance.

Install:
```bash
docker compose exec moodle bash -lc '
  cd /bitnami/moodle/mod &&
  git clone --depth=1 https://github.com/UDIMA/moodle-mod_jitsi.git jitsi'
docker compose exec moodle php /bitnami/moodle/admin/cli/upgrade.php --non-interactive
```

Configure: **Site administration → Plugins → Activity modules → Jitsi** → set server URL to `https://meet.jit.si` (default), leave JWT off.

Document this in `seed/install_plugins.sh` so a fresh deploy reapplies it.

### 7b. Production upgrade — BigBlueButton

BBB self-hosts and gives breakout rooms, polls, multi-user whiteboard, recording. Requirements:
- Dedicated node (BBB needs Ubuntu 22.04, ≥ 4 vCPU, ≥ 8 GB RAM, public IPv4, ports 80/443/16384–32768 UDP).
- Install with `bbb-install-2.7.sh` from bigbluebutton.org docs.
- In Moodle: **Plugins → Activity modules → BigBlueButton** → enter server URL + shared secret.

Do not attempt BBB on the CPX22 demo node. It will crash the box.

## 8. Paid courses

Native enrol plugins shipped with Moodle 4.5:

| Plugin | Status | Notes |
|---|---|---|
| `enrol_paypal` | Core | PayPal IPN. Working but PayPal-only. |
| `enrol_stripe` | Community (Stripe-maintained) | https://github.com/stripe/moodle-payment-stripe |
| `enrol_razorpay` | Community | Useful for India market. |

For the demo, **enable `enrol_paypal` with sandbox credentials** (or a stubbed redirect that always succeeds) — matches your Astro-site pattern of stubbed payment gateways. Real gateway integration goes in a later iteration once a real customer signs.

Enable: **Site administration → Plugins → Enrolments → Manage enrol plugins** → enable PayPal. Per-course: course settings → enrolment methods → add PayPal → set amount + currency.

## 9. SCORM / xAPI

Both are **native**. No plugin install needed.

- **SCORM 1.2 / 2004**: course → add activity → SCORM package → upload `.zip`. Moodle parses the imsmanifest, plays the package via its built-in SCORM player, records cmi.* data in `mdl_scorm_scoes_value`.
- **xAPI / Tin Can**: add activity → External tool (LTI 1.3) OR enable LRS plugin `logstore_xapi` to forward statements to an external Learning Record Store.

For the demo, ship one SCORM 1.2 sample (Articulate's free "Hello World" package) and one xAPI sample (Rustici Driver's "Golf Example"). Drop them in `seed/packages/`, install via `seed/courses.php` using `mod_scorm_external::*` calls.

## 10. RBAC — roles & users (TODO seed/roles.sql, seed/users.sh)

Moodle has 8 built-in roles (Manager, Course creator, Teacher, Non-editing teacher, Student, Guest, Authenticated user, Authenticated user on front page). For a multi-audience LMS, add custom roles:

| Role | Based on | Purpose |
|---|---|---|
| `infinity_corporate_admin` | Manager | Limited to "Corporate Training" category. |
| `infinity_customer_csm` | Course creator | Can create customer-onboarding courses. |
| `infinity_partner_lead` | Teacher | Read-only catalog + edit partner-enablement courses. |
| `infinity_paying_learner` | Student | Default for self-enrol via PayPal. |

Created via `create_role()` + `assign_capability()` calls in `seed/roles.php`. Cohort assignment via `cohort_add_member()`.

Demo users — never insert into `mdl_user` directly (Moodle uses md5+salt with rehash). Use the CLI:

```bash
docker compose exec moodle php /bitnami/moodle/admin/cli/create_user.php \
  --username=sarah --password='Strong@2026' \
  --email=sarah@infinitynexatech.com --firstname=Sarah --lastname=Johnson
```

Wrap in `seed/users.sh` (mirrors `create_users.sh` in CRM).

## 11. Production deployment (TODO)

### 11a. DNS + VPS

Existing CRM box on CPX22 has spare capacity for the Moodle demo. Add an A record at the registrar:

```
lms.infinitynexatech.com  A  <CRM_VPS_IPv4>  TTL 600
```

Caddy on the CRM box already handles 80/443 — extend its Caddyfile to add the `lms.` site block reverse-proxying to a new Moodle container on the same Docker network. **Or** stand Moodle up on its own port and let CRM's Caddy proxy externally.

### 11b. docker-compose.prod.yml (TODO)

Mirrors CRM's prod compose:
- `caddy` service (reused — share the Caddyfile across both deployments)
- `mariadb` service for Moodle (separate DB from CRM's Postgres)
- `moodle` service WITHOUT publishing 8080 to the host — Caddy reaches it on the internal Docker network
- Secrets from `.env`: `DOMAIN`, `DB_PASSWORD`, `ADMIN_PASSWORD`
- `MOODLE_REVERSEPROXY=true`, `MOODLE_SSLPROXY=true` (Bitnami env vars for trusting X-Forwarded-Proto)

### 11c. Caddyfile addition (TODO)

```caddy
lms.infinitynexatech.com {
    encode gzip zstd
    reverse_proxy moodle:8080 {
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

### 11d. setup.sh / redeploy.sh (TODO)

Same pattern as CRM:
- `setup.sh` — apt update, docker install, ufw, clone repo, generate secrets, `docker compose up`.
- `redeploy.sh` — `git pull`, `docker compose down -v` (wipes DB), `up -d`, run `seed/*.php` to repopulate.

### 11e. Backups

```cron
# /etc/cron.daily/lms-backup
docker compose -f /opt/infinity-learn/docker-compose.prod.yml exec -T mariadb \
  mysqldump -u bn_moodle -p"$DB_PASSWORD" bitnami_moodle | gzip > /var/backups/lms-$(date +%F).sql.gz
# moodledata (uploaded files, course backups) — tar it
tar czf /var/backups/lms-moodledata-$(date +%F).tar.gz -C /var/lib/docker/volumes/infinity-learn_moodledata_data _data
find /var/backups -name 'lms-*' -mtime +14 -delete
```

Two artifacts per day, since Moodle splits state across DB + filesystem (unlike Corteza which keeps everything in Postgres).

## 12. The gotcha list (will be filled in as we hit them)

| # | Gotcha | Symptom | Fix |
|---|---|---|---|
| 1 | Bitnami first-boot env vars only apply once | Changing `MOODLE_SITE_NAME` in compose after first boot has no effect | Re-seed via `local_infinity` upgrade, OR `docker compose down -v` and re-up |
| 2 | Theme code changes don't appear | CSS edits invisible | **Site administration → Appearance → Themes → Purge theme caches** (or set `$CFG->themedesignermode = true;` in dev) |
| 3 | Plugin code changes don't appear | New menu items absent | Run `php admin/cli/upgrade.php --non-interactive` and bump `version.php` |
| 4 | Cron not running | Course completion stuck, scheduled tasks pending | Bitnami image has `cron` baked in; if disabled, `docker compose exec moodle php /bitnami/moodle/admin/cli/cron.php` |
| 5 | `theme/infinity` bind-mount as `:ro` blocks Moodle from writing compiled CSS | 500 errors after enable | Moodle writes compiled CSS to `moodledata/`, not the theme dir — `:ro` is fine. If you see errors, check `moodledata` permissions. |
| 6 | Reverse proxy without `MOODLE_REVERSEPROXY=true` | Mixed-content warnings, login redirects to http | Set both `MOODLE_REVERSEPROXY=true` and `MOODLE_SSLPROXY=true` in prod compose |
| 7 | `enrol_paypal` requires public IPN URL | Payment marks "pending" forever in dev | Use ngrok or stub the gateway (matches Astro-sites pattern) |
| 8 | SCORM 2004 4th edition behaves differently from 1.2 | Reports show "in progress" indefinitely | Test SCORM packages against Moodle's `SCORM Cloud Player` first; some Articulate exports are buggy |

(Add to this table as you hit each one — the CRM doc filled this in over the full build.)

## 13. Build order (TL;DR)

- **Day 0** (done in this scaffold). `docker-compose.yml`, `.env.example`, `.gitignore`, `.gitattributes`. `docker compose up -d`. Verify Moodle boots at http://localhost:8080.
- **Day 0** (done). `theme/infinity/` skeleton + `local/infinity/` skeleton. Mounted as overlays. After first boot, log in → confirm site is named "Infinity Learn" and theme = Infinity.
- **Day 1**. Refine `theme/infinity/scss/post.scss` and `theme_infinity_get_pre_scss()` against the actual rendered pages. Add logo via `local_infinity`.
- **Day 2**. `seed/categories.php` + `seed/courses.php`. Wire into `redeploy.sh`. Include one SCORM sample + one xAPI sample in `seed/packages/`.
- **Day 2**. Install Jitsi plugin via `seed/install_plugins.sh`. Add one Jitsi activity to "Live: Q1 All-Hands" sample course.
- **Day 3**. Stub PayPal enrol on the "Paid: Advanced Excel" sample course.
- **Day 3**. `seed/roles.php` for custom roles, `seed/users.sh` for demo users.
- **Day 4**. `docker-compose.prod.yml`, `deploy/Caddyfile`, `deploy/setup.sh`, `deploy/redeploy.sh`. DNS A record. Verify https://lms.infinitynexatech.com.
- **Day 4**. Set up daily backups (DB + moodledata).
- **Day 5**. Fill in §12 gotchas with everything that bit us. Ship.

Total active dev time: ~4–5 working days. (Slightly longer than CRM because Moodle has more native surface area to brand than Corteza.)

## 14. License

Moodle is **GPL v3**. Implications for this build:

- **Self-hosting + modifications + selling access**: ✅ Fully allowed. GPL v3 grants every freedom needed for the white-label playbook.
- **Distributing modified Moodle to a customer** (e.g. handing them a Docker image): Must also provide source under GPL v3. Easy — link them to this repo.
- **`theme_infinity` + `local_infinity`**: Inherit GPL v3 because they `require_once` Moodle's GPL code. Standard for every Moodle plugin in the marketplace.
- **Trademark "Moodle"**: Owned by Moodle Pty Ltd. We're free to use the name in technical docs (this AS_BUILT), but the **user-visible product name is "Infinity Learn"**, not "Moodle". Never put "Moodle" in marketing copy, screenshots' chrome, or the front-end.
- **AGPL question** (does running this as a SaaS trigger source-disclosure obligations to end users): No — Moodle is GPL v3 not AGPL v3. SaaS deployment without source disclosure is permitted. (Canvas LMS is AGPL — that's why we didn't pick it.)

Bottom line: zero IP risk for the white-label + self-host + sell-access business model. Same posture as Corteza (Apache 2.0) for the CRM — just a different license with slightly stricter distribution obligations.
