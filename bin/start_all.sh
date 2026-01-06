#!/usr/bin/env bash
set -e
# Start all local services needed for development (web server etc.)
# This script is safe to run from the repo root or from anywhere; it
# resolves the repo root relative to this script location.
cd "$(dirname "$0")/.."

# Load .env for local development if present
set -o allexport
[ -f .env ] && . .env
set +o allexport

WEB_PID_FILE="/tmp/ginto-web.pid"

# Start PHP built-in server on port 8000 if not already running
if [ -f "$WEB_PID_FILE" ]; then
  PID=$(cat "$WEB_PID_FILE")
  if ps -p "$PID" > /dev/null 2>&1; then
    echo "Web server already running (PID: $PID)"
  else
    rm -f "$WEB_PID_FILE"
  fi
fi

if [ ! -f "$WEB_PID_FILE" ]; then
  nohup php -S 0.0.0.0:8000 -t public > /tmp/ginto-web.log 2>&1 &
  echo $! > "$WEB_PID_FILE"
  echo "Started web server on :8000 (pid $(cat $WEB_PID_FILE))"
fi

# Start clients server (dev) on port 8080 serving the 'clients' folder
# Uses router.php for security filtering
CLIENTS_PID_FILE="/tmp/ginto-clients.pid"

if [ -f "$CLIENTS_PID_FILE" ]; then
  PID=$(cat "$CLIENTS_PID_FILE")
  if ps -p "$PID" > /dev/null 2>&1; then
    echo "Clients server already running (PID: $PID)"
  else
    rm -f "$CLIENTS_PID_FILE"
  fi
fi

if [ ! -f "$CLIENTS_PID_FILE" ]; then
  # clients/router.php is part of the core codebase - no directory creation needed
  # Use router.php for security filtering
  nohup php -S 0.0.0.0:8080 -t clients clients/router.php > /tmp/ginto-clients.log 2>&1 &
  echo $! > "$CLIENTS_PID_FILE"
  echo "Started clients server on :8080 with security router (pid $(cat $CLIENTS_PID_FILE))"
fi

# Sandbox creation on startup is disabled. Sandboxes should only be
# created when a user explicitly requests one from the Playground editor
# (the UI invokes the validated wrapper). This avoids creating machines
# automatically on developer machine start and prevents accidental
# sandbox installation for users who haven't requested it.
MACHINECTL_BIN="$(command -v machinectl || true)"
if [ -n "$MACHINECTL_BIN" ]; then
  echo "Skipping automatic sandbox creation on startup."
  echo "Create a sandbox from the Playground editor, or run as root:" 
  echo "  sudo /usr/local/sbin/ginto-create-wrapper.sh <sandboxId> $(pwd)/clients/<sandboxId>"
fi

echo "start_all complete"
