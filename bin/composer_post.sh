#!/usr/bin/env bash
set -euo pipefail
# bin/composer_post.sh
# Safe composer post-install/update hook. Only starts the Ratchet server if
# the environment variable GINTO_AUTO_START is set to "1". This prevents
# composer installations in CI or non-interactive shells from launching
# background services unexpectedly.

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$REPO_ROOT"

if [ "${GINTO_AUTO_START:-0}" != "1" ]; then
  echo "Skipping Ratchet auto-start (GINTO_AUTO_START not set to 1)"
  exit 0
fi

if ! command -v php >/dev/null 2>&1; then
  echo "php CLI not found in PATH; cannot start Ratchet"
  exit 0
fi

mkdir -p ../storage/logs
touch ../storage/logs/ratchet.log 2>/dev/null || true
chown $(id -u):$(id -g) ../storage || true

echo "Starting Ratchet WebSocket server in background (dev)"
nohup php -d display_errors=1 bin/start_rachet_stream.php > ../storage/logs/ratchet.log 2>&1 &
echo $! > /tmp/ginto-ratchet.pid
echo "Ratchet started (pid $(cat /tmp/ginto-ratchet.pid), log: ../storage/logs/ratchet.log)"

exit 0
