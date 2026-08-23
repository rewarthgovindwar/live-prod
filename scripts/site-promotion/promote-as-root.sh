#!/bin/bash
# Wrapper invoked via passwordless sudo from the web UI (runs as root).
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
exec "$SCRIPT_DIR/promote-pull.sh" "$@"
