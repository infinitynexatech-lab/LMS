#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   bash seed/users.sh          # local dev
#   bash seed/users.sh prod     # production
MODE="${1:-dev}"
COMPOSE_FILE="docker-compose.yml"
[[ "$MODE" == "prod" ]] && COMPOSE_FILE="docker-compose.prod.yml"

# Moodle 4.5 doesn't ship admin/cli/create_user.php — we use a Moodle-API
# script instead that also assigns each user's custom role at system context.
docker compose -f "$COMPOSE_FILE" cp seed/create_users.php moodle:/tmp/create_users.php
docker compose -f "$COMPOSE_FILE" exec -T moodle php /tmp/create_users.php
