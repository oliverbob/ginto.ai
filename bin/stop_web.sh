#!/usr/bin/env bash
set -e
cd "$(dirname "$0")/.."

WEB_PID_FILE="/tmp/ginto-web.pid"
if [ -f "$WEB_PID_FILE" ]; then
  PID=$(cat "$WEB_PID_FILE")
  if ps -p "$PID" > /dev/null 2>&1; then
    kill "$PID" && echo "Stopped web server (pid $PID)"
    sleep 1
    if ps -p "$PID" > /dev/null 2>&1; then
      echo "Process did not exit; sending SIGKILL"
      kill -9 "$PID" || true
    fi
  else
    echo "No process with pid $PID"
  fi
  rm -f "$WEB_PID_FILE"
else
  echo "No pid file ($WEB_PID_FILE) found; nothing to stop."
fi

echo "stop_web complete"
