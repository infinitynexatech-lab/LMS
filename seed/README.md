# seed/

Runs AFTER first boot of Moodle. Equivalent to the CRM's `seed/` block.

| File | Purpose |
|---|---|
| install_plugins.sh | Clone third-party plugins (Jitsi) into the running container and run Moodle's upgrade.php. |
| categories.php | Create top-level course categories: Corporate / Customer / Partner / Public / Academic. |
| courses.php | Create one sample course per audience (depends on categories.php). |
| roles.php | Create custom Infinity Learn roles cloned from Moodle archetypes. |
| users.sh | Bulk-create demo learners + instructors via `admin/cli/create_user.php`. |

## Run order (local dev)

```bash
bash seed/install_plugins.sh

docker compose cp seed/categories.php moodle:/tmp/categories.php
docker compose cp seed/courses.php    moodle:/tmp/courses.php
docker compose cp seed/roles.php      moodle:/tmp/roles.php
docker compose exec -T moodle php /tmp/categories.php
docker compose exec -T moodle php /tmp/courses.php
docker compose exec -T moodle php /tmp/roles.php

bash seed/users.sh
```

`deploy/redeploy.sh` runs all five in order on the prod VPS.
