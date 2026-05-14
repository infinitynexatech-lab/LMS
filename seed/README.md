# seed/

Runs AFTER first boot of Moodle. Equivalent to the CRM's `seed/` block.

| File | Purpose |
|---|---|
| `install_plugins.sh` | Clone third-party plugins into the running container and run `upgrade.php`. (Jitsi is parked — see file for details.) |
| `categories.php` | Create top-level course categories: Corporate / Customer / Partner / Public / Academic. |
| `courses.php` | Create one sample course per audience (depends on `categories.php`). |
| `roles.php` | Create custom Infinity Learn roles cloned from Moodle archetypes. |
| `create_users.php` | Create 4 demo users via the Moodle API + assign each to its custom role at system context. Invoked by `users.sh`. |
| `users.sh` | Thin wrapper that copies + runs `create_users.php` inside the container. |
| `attach_scorm.php` | Attach `packages/gdpr-demo.zip` as a SCORM 1.2 activity inside the `gdpr` course. |
| `packages/gdpr-demo/` | SCORM 1.2 sample: `imsmanifest.xml` + `index.html` (a real 3-slide SCO with `cmi.core.lesson_status` reporting). |
| `packages/gdpr-demo.zip` | Pre-built zip of the above. Rebuild with: `Compress-Archive -Path seed/packages/gdpr-demo/* -DestinationPath seed/packages/gdpr-demo.zip -Force` |

## Run order (local dev)

```bash
# 0. Plugins (Jitsi parked for now — skip or install manually via admin UI)
# bash seed/install_plugins.sh

# 1. Categories → courses → roles
docker compose cp seed/categories.php moodle:/tmp/categories.php
docker compose cp seed/courses.php    moodle:/tmp/courses.php
docker compose cp seed/roles.php      moodle:/tmp/roles.php
docker compose exec -T moodle php /tmp/categories.php
docker compose exec -T moodle php /tmp/courses.php
docker compose exec -T moodle php /tmp/roles.php

# 2. Users (creates + assigns roles)
bash seed/users.sh

# 3. SCORM sample attached to the gdpr course
docker compose cp seed/packages/gdpr-demo.zip moodle:/tmp/gdpr-demo.zip
docker compose cp seed/attach_scorm.php       moodle:/tmp/attach_scorm.php
docker compose exec -T moodle php /tmp/attach_scorm.php
```

`deploy/redeploy.sh` runs all of these in order on the prod VPS.

## After running

Visit http://localhost:8081, log in as `admin / AdminPass!2026`, navigate to **Courses → Corporate Training → GDPR Compliance (SCORM)** — the SCORM activity will launch the 3-slide SCO and report completion back to Moodle.
