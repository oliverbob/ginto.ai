#!/usr/bin/env bash
# deploy/run_install_and_check.sh
# Helper wrapper: run this as root to install sandboxd artifacts and perform
# a one-shot create of a sandbox, then show logs and machine status.

set -euo pipefail

if [ "$#" -lt 3 ]; then
  echo "Usage: $0 <web_user> <sandboxId> <hostPath>"
  echo "Example: sudo $0 oliverbob 2nKOuHHSYDuL /home/oliverbob/ginto/clients/2nKOuHHSYDuL"
  exit 2
fi

WEB_USER="$1"
SANDBOX_ID="$2"
HOST_PATH="$3"

REPO_ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
LOG_DIR="/var/log/ginto"
mkdir -p "$LOG_DIR"

echo "Running installer: deploy/install_sandboxd.sh $WEB_USER --create-now $SANDBOX_ID $HOST_PATH"
cd "$REPO_ROOT"
./deploy/install_sandboxd.sh "$WEB_USER" --create-now "$SANDBOX_ID" "$HOST_PATH" || true

CANONICAL=$(echo "$SANDBOX_ID" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9._-]+/-/g' | sed -E 's/^-+|-+$//g')
if [ -z "$CANONICAL" ]; then
  CANONICAL=$(echo -n "$SANDBOX_ID" | md5sum | cut -c1-16)
fi
CANONICAL=${CANONICAL:0:63}

INSTALL_LOG="$(dirname "$REPO_ROOT")/storage/backups/install_${SANDBOX_ID}.log"
DAEMON_LOG="$LOG_DIR/sandboxd_${CANONICAL}.log"
INSTALL_LOG2="$LOG_DIR/install_${CANONICAL}.log"

echo
echo "==== Last lines from storage/backups install log (if present) ===="
if [ -f "$INSTALL_LOG" ]; then
  tail -n 200 "$INSTALL_LOG" || true
else
  echo "(no $INSTALL_LOG)"
fi

echo
echo "==== Last lines from /var/log/ginto (${DAEMON_LOG}) ===="
if [ -f "$DAEMON_LOG" ]; then
  tail -n 200 "$DAEMON_LOG" || true
else
  echo "(no $DAEMON_LOG)"
fi

echo
echo "==== Last lines from /var/log/ginto install log (${INSTALL_LOG2}) ===="
if [ -f "$INSTALL_LOG2" ]; then
  tail -n 200 "$INSTALL_LOG2" || true
else
  echo "(no $INSTALL_LOG2)"
fi

echo
echo "==== machinectl status for ginto-sandbox-${CANONICAL} ===="
if command -v machinectl >/dev/null 2>&1; then
  machinectl status "ginto-sandbox-${CANONICAL}" || true
  echo
  echo "==== machinectl list (ginto) ===="
  machinectl list | grep ginto-sandbox || true
else
  echo "machinectl not available on this host"
fi

echo
echo "Done. If you want me to analyse the outputs, paste the full output here."
