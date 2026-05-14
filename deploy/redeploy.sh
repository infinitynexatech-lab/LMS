#!/usr/bin/env bash
set -euo pipefail

# Wipe + re-provision Infinity Learn on the VPS. DESTROYS all course content
# and user data — only run on the demo box or before going live.
#
# Usage (as root):
#   bash /opt/infinity-learn/deploy/redeploy.sh

cd "${INSTALL_DIR:-/opt/infinity-learn}"
git pull --ff-only

set -a; source .env; set +a

docker compose -f docker-compose.prod.yml down -v
docker compose -f docker-compose.prod.yml build moodle
docker compose -f docker-compose.prod.yml up -d

echo "==> Waiting for Moodle install to finish (≈2 min)…"
until docker compose -f docker-compose.prod.yml exec -T moodle \
        test -f /var/www/html/config.php 2>/dev/null; do
    sleep 5
done
# Install runs after config.php is written — give it more time.
sleep 60

echo "==> Installing third-party plugins…"
bash seed/install_plugins.sh prod

echo "==> Seeding categories + courses + roles…"
docker compose -f docker-compose.prod.yml cp seed/categories.php moodle:/tmp/categories.php
docker compose -f docker-compose.prod.yml cp seed/courses.php    moodle:/tmp/courses.php
docker compose -f docker-compose.prod.yml cp seed/roles.php      moodle:/tmp/roles.php
docker compose -f docker-compose.prod.yml exec -T moodle php /tmp/categories.php
docker compose -f docker-compose.prod.yml exec -T moodle php /tmp/courses.php
docker compose -f docker-compose.prod.yml exec -T moodle php /tmp/roles.php

echo "==> Creating demo users…"
bash seed/users.sh prod

echo
echo "==> Redeploy complete. https://${DOMAIN}"
echo "==> Admin: admin / ${ADMIN_PASSWORD}"
