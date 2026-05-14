#!/usr/bin/env bash
set -eu

CFG="${MOODLE_DOCROOT:-/var/www/html}/config.php"
DOCROOT="${MOODLE_DOCROOT:-/var/www/html}"
DATAROOT="${MOODLE_DATAROOT:-/var/www/moodledata}"

# Wait for DB to accept connections.
echo "==> Waiting for ${MOODLE_DATABASE_HOST}:${MOODLE_DATABASE_PORT:-3306}…"
until php -r "
try {
    new PDO(
        'mysql:host=${MOODLE_DATABASE_HOST};port=${MOODLE_DATABASE_PORT:-3306};dbname=${MOODLE_DATABASE_NAME}',
        '${MOODLE_DATABASE_USER}',
        '${MOODLE_DATABASE_PASSWORD}'
    );
    exit(0);
} catch (Throwable \$e) { exit(1); }
" >/dev/null 2>&1; do
    sleep 3
done
echo "==> DB reachable."

if [ ! -f "$CFG" ]; then
    echo "==> First boot — running admin/cli/install.php…"
    mkdir -p "$DATAROOT"
    chown -R www-data:www-data "$DATAROOT" "$DOCROOT"

    su -s /bin/bash www-data -c "php ${DOCROOT}/admin/cli/install.php \
        --lang=en \
        --wwwroot='${MOODLE_WWWROOT:-http://localhost:8080}' \
        --dataroot='${DATAROOT}' \
        --dbtype=mariadb \
        --dbhost='${MOODLE_DATABASE_HOST}' \
        --dbname='${MOODLE_DATABASE_NAME}' \
        --dbuser='${MOODLE_DATABASE_USER}' \
        --dbpass='${MOODLE_DATABASE_PASSWORD}' \
        --fullname='${MOODLE_SITE_NAME:-Infinity Learn}' \
        --shortname='${MOODLE_SITE_NAME:-Infinity Learn}' \
        --adminuser='${MOODLE_USERNAME:-admin}' \
        --adminpass='${MOODLE_PASSWORD:-AdminPass!2026}' \
        --adminemail='${MOODLE_EMAIL:-admin@local.dev}' \
        --non-interactive \
        --agree-license"

    # Reverse-proxy hints, appended to config.php.
    if [ "${MOODLE_REVERSEPROXY:-false}" = "true" ]; then
        sed -i "/require_once/i \$CFG->reverseproxy = true;" "$CFG"
        sed -i "/require_once/i \$CFG->sslproxy = true;" "$CFG"
    fi
fi

# Re-assert ownership in case mounted volumes shifted it.
chown -R www-data:www-data "$DOCROOT" "$DATAROOT" 2>/dev/null || true

exec "$@"
