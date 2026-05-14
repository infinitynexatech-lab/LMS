#!/usr/bin/env bash
set -euo pipefail

# One-shot bootstrap for the Infinity Learn LMS on a fresh Ubuntu 24.04 VPS,
# OR on the existing Infinity CRM CPX22 box (co-hosted demo).
#
# Usage (as root):
#   INSTALL_DIR=/opt/infinity-learn \
#   DOMAIN_DEFAULT=lms.infinitynexatech.com \
#   bash deploy/setup.sh

INSTALL_DIR="${INSTALL_DIR:-/opt/infinity-learn}"
DOMAIN_DEFAULT="${DOMAIN_DEFAULT:-lms.infinitynexatech.com}"
REPO_URL="${REPO_URL:-https://github.com/infinitynexatech-lab/LMS.git}"
ADMIN_EMAIL_DEFAULT="${ADMIN_EMAIL_DEFAULT:-admin@infinitynexatech.com}"

apt-get update -qq
apt-get install -y -qq curl git openssl ca-certificates ufw

command -v docker >/dev/null || curl -fsSL https://get.docker.com | sh
systemctl enable --now docker

# Firewall — only touch UFW if this is the FIRST service on the box.
# If CRM already configured ufw, leave it alone.
if ! ufw status | grep -q "Status: active"; then
    ufw --force reset >/dev/null
    ufw default deny incoming
    ufw default allow outgoing
    ufw allow 22/tcp
    ufw allow 80/tcp
    ufw allow 443/tcp
    ufw --force enable
fi

# Clone or fast-forward.
[[ -d "$INSTALL_DIR/.git" ]] || git clone "$REPO_URL" "$INSTALL_DIR"
cd "$INSTALL_DIR"
git pull --ff-only

# Generate secrets on first run.
[[ -f .env ]] || cat > .env <<EOF
DOMAIN=$DOMAIN_DEFAULT
DB_PASSWORD=$(openssl rand -hex 24)
ADMIN_PASSWORD=$(openssl rand -hex 16)
ADMIN_EMAIL=$ADMIN_EMAIL_DEFAULT
EOF
chmod 600 .env

docker compose -f docker-compose.prod.yml pull --quiet
docker compose -f docker-compose.prod.yml up -d

echo
echo "==> Done. Moodle starting on 127.0.0.1:8090."
echo "==> Next steps:"
echo "    1. Append deploy/Caddyfile to your reverse-proxy Caddyfile, reload."
echo "    2. After ~2 minutes (first-boot install), run:  bash deploy/redeploy.sh"
echo "       (or run seed scripts individually — see seed/README.md)"
echo "    3. Admin password is in .env  (ADMIN_PASSWORD=…)."
