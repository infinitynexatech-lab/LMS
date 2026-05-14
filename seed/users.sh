#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   bash seed/users.sh          # local dev
#   bash seed/users.sh prod     # production
MODE="${1:-dev}"
COMPOSE_FILE="docker-compose.yml"
[[ "$MODE" == "prod" ]] && COMPOSE_FILE="docker-compose.prod.yml"

# username : first : last : email : password
USERS=(
  "sarah:Sarah:Johnson:sarah.johnson@infinitynexatech.com:Strong@2026"
  "miguel:Miguel:Reyes:miguel.reyes@infinitynexatech.com:Strong@2026"
  "priya:Priya:Sharma:priya.sharma@infinitynexatech.com:Strong@2026"
  "alex:Alex:Chen:alex.chen@example.com:Strong@2026"
)

for u in "${USERS[@]}"; do
  IFS=":" read -r username first last email password <<< "$u"
  docker compose -f "$COMPOSE_FILE" exec -T moodle \
    php /var/www/html/admin/cli/create_user.php \
      --username="$username" \
      --password="$password" \
      --email="$email" \
      --firstname="$first" \
      --lastname="$last" \
      --ignore-password-policy 2>&1 \
    && echo "+ user $username created" \
    || echo "= user $username already exists"
done

# NOTE: admin/cli/create_user.php does not accept a --role flag. Custom-role
# assignment (e.g. assigning 'infinity_corporate_admin' to sarah) is handled
# by Cohorts in Moodle. Either:
#  (a) admin UI → Users → Cohorts → assign cohort to a role at system context, or
#  (b) write seed/assign_roles.php with role_assign() calls (deferred).
