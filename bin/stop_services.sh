#!/usr/bin/env bash
set -e
cd "$(dirname "$0")/.."

echo "Stopping auxiliary services..."

# Stop clients server
CLIENTS_PID_FILE="/tmp/ginto-clients.pid"
if [ -f "$CLIENTS_PID_FILE" ]; then
  PID=$(cat "$CLIENTS_PID_FILE")
  if ps -p "$PID" > /dev/null 2>&1; then
    kill "$PID" && echo "Stopped clients server (pid $PID)"
    sleep 1
    if ps -p "$PID" > /dev/null 2>&1; then
      kill -9 "$PID" || true
    fi
  fi
  rm -f "$CLIENTS_PID_FILE" || true
else
  echo "No clients pid file; nothing to stop."
fi

# Stop Ratchet
RAT_PID_FILE="/tmp/ginto-ratchet.pid"
if [ -f "$RAT_PID_FILE" ]; then
  PID=$(cat "$RAT_PID_FILE")
  if ps -p "$PID" > /dev/null 2>&1; then
    kill "$PID" && echo "Stopped Ratchet (pid $PID)"
    sleep 1
    if ps -p "$PID" > /dev/null 2>&1; then
      kill -9 "$PID" || true
    fi
  fi
  rm -f "$RAT_PID_FILE" || true
else
  echo "No ratchet pid file; nothing to stop."
fi

# Stop MCP components (reuse existing script if present)
if [ -x "$(pwd)/bin/mcp_stop.sh" ]; then
  bash "$(pwd)/bin/mcp_stop.sh" || true
else
  echo "No bin/mcp_stop.sh found; ensure MCP processes are stopped manually if needed."
fi

# Stop Sandbox Proxy
SANDBOX_PROXY_PID_FILE="/tmp/ginto-sandbox-proxy.pid"
if [ -f "$SANDBOX_PROXY_PID_FILE" ]; then
  PID=$(cat "$SANDBOX_PROXY_PID_FILE")
  if ps -p "$PID" > /dev/null 2>&1; then
    kill "$PID" && echo "Stopped sandbox proxy (pid $PID)"
    sleep 1
    if ps -p "$PID" > /dev/null 2>&1; then
      kill -9 "$PID" || true
    fi
  fi
  rm -f "$SANDBOX_PROXY_PID_FILE" || true
else
  echo "No sandbox proxy pid file; nothing to stop."
fi

echo "stop_services complete"
