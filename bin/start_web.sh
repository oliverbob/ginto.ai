#!/usr/bin/env bash
set -e
# Start only the web dev server and write pid/log files
cd "$(dirname "$0")/.."

WEB_PID_FILE="/tmp/ginto-web.pid"

if [ -f "$WEB_PID_FILE" ]; then
  PID=$(cat "$WEB_PID_FILE")
  if ps -p "$PID" > /dev/null 2>&1; then
    echo "Web server already running (PID: $PID)"
    exit 0
  else
    rm -f "$WEB_PID_FILE"
  fi
fi

nohup php -S 0.0.0.0:8000 -t public public/router.php > /tmp/ginto-web.log 2>&1 &
echo $! > "$WEB_PID_FILE"
echo "Started web server on :8000 with router (pid $(cat $WEB_PID_FILE))"

echo "start_web complete"
