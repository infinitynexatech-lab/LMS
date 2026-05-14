#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   bash seed/install_plugins.sh          # local dev (docker-compose.yml)
#   bash seed/install_plugins.sh prod     # production (docker-compose.prod.yml)
MODE="${1:-dev}"
COMPOSE_FILE="docker-compose.yml"
[[ "$MODE" == "prod" ]] && COMPOSE_FILE="docker-compose.prod.yml"

in_moodle() { docker compose -f "$COMPOSE_FILE" exec -T moodle bash -lc "$1"; }

# TODO Jitsi: the previous UDIMA/moodle-mod_jitsi URL is dead. Install via
# Moodle's plugin directory UI for now (Site administration → Plugins →
# Install plugins → search "Jitsi"), or pin a verified GitHub mirror here.
#
# echo "==> Installing Jitsi activity plugin (mod_jitsi)…"
# in_moodle '
#     cd /var/www/html/mod
#     [ -d jitsi ] || git clone --depth=1 <VERIFIED_REPO_URL> jitsi
#     chown -R www-data:www-data jitsi
# '

# Other plugins to consider (uncomment + verify URL when ready):
#
#   Stripe enrol — paid courses:
#   in_moodle '[ -d /var/www/html/enrol/stripepayment ] || \
#     git clone --depth=1 https://github.com/dualcube/moodle-enrol_stripepayment.git \
#       /var/www/html/enrol/stripepayment'

echo "==> Running Moodle upgrade.php to register new plugins…"
in_moodle 'php /var/www/html/admin/cli/upgrade.php --non-interactive'

echo "==> Done."
