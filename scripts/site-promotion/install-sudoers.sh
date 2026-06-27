#!/bin/bash
# Install passwordless sudo for site promotion apply (production web user only).
set -euo pipefail

if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
  echo "Run as root: sudo bash $0" >&2
  exit 1
fi

DEPLOY_USER="${SITE_PROMOTION_PROD_USER:-dnyan5592}"
APP_ROOT="${SITE_PROMOTION_PROD_PATH:-/home/dnyanda.vss.ac/public_html}"
WRAPPER="${APP_ROOT}/scripts/site-promotion/promote-as-root.sh"
SUDOERS_FILE="/etc/sudoers.d/site-promotion"

[[ -x "$WRAPPER" ]] || chmod 750 "$WRAPPER"
chown root:root "$WRAPPER"

cat > "$SUDOERS_FILE" <<EOF
# Site promotion — allow ${DEPLOY_USER} to apply staging updates from the web UI
Defaults:${DEPLOY_USER} !requiretty
${DEPLOY_USER} ALL=(root) NOPASSWD: ${WRAPPER} *
EOF

chmod 440 "$SUDOERS_FILE"
visudo -cf "$SUDOERS_FILE"

echo "Installed ${SUDOERS_FILE}"
echo "Test: sudo -u ${DEPLOY_USER} sudo -n ${WRAPPER} --preview"
