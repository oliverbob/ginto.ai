#!/usr/bin/env bash
set -e
# Start auxiliary services (clients server, MCP servers, Ratchet, AI SDK)
cd "$(dirname "$0")/.."

echo "Starting auxiliary services..."

# === AI SDK Build ===
# Build the AI SDK if needed (runs npm install + build)
if [ -x "$(pwd)/bin/build_ai_sdk.sh" ]; then
  bash "$(pwd)/bin/build_ai_sdk.sh" || echo "Warning: AI SDK build failed, continuing..."
fi

# Clients server (static clients folder)
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
  nohup php -S 0.0.0.0:8080 -t clients clients/router.php > /tmp/ginto-clients.log 2>&1 &
  echo $! > "$CLIENTS_PID_FILE"
  echo "Started clients server on :8080 (pid $(cat $CLIENTS_PID_FILE))"
fi

# Start MCP servers and mcp_stub (reuses existing script)
if [ -x "$(pwd)/bin/mcp_start.sh" ]; then
  bash "$(pwd)/bin/mcp_start.sh" || true
fi

# Ratchet: start in background and record pid
RAT_PID_FILE="/tmp/ginto-ratchet.pid"
if [ -f "$RAT_PID_FILE" ]; then
  RPID=$(cat "$RAT_PID_FILE")
  if ps -p "$RPID" > /dev/null 2>&1; then
    echo "Ratchet already running (PID: $RPID)"
  else
    rm -f "$RAT_PID_FILE"
  fi
fi

if [ ! -f "$RAT_PID_FILE" ]; then
  echo "Starting Ratchet WebSocket server (background)"
  mkdir -p ../storage/logs
  php -d display_errors=1 bin/start_rachet_stream.php > ../storage/logs/ratchet.log 2>&1 &
  echo $! > "$RAT_PID_FILE"
  echo "Ratchet started (pid $(cat $RAT_PID_FILE), log: ../storage/logs/ratchet.log)"
fi

# Sandbox Proxy: Start Node.js sandbox proxy on port 1800
SANDBOX_PROXY_PID_FILE="/tmp/ginto-sandbox-proxy.pid"
if [ -f "$SANDBOX_PROXY_PID_FILE" ]; then
  SPID=$(cat "$SANDBOX_PROXY_PID_FILE")
  if ps -p "$SPID" > /dev/null 2>&1; then
    echo "Sandbox proxy already running (PID: $SPID)"
  else
    rm -f "$SANDBOX_PROXY_PID_FILE"
  fi
fi

if [ ! -f "$SANDBOX_PROXY_PID_FILE" ]; then
  if [ -f "tools/sandbox-proxy/sandbox-proxy.js" ]; then
    echo "Starting Sandbox Proxy on port 1800..."
    cd tools/sandbox-proxy && npm install --silent 2>/dev/null || true
    cd ../..
    nohup env PROXY_PORT=1800 node tools/sandbox-proxy/sandbox-proxy.js > /tmp/sandbox-proxy.log 2>&1 &
    echo $! > "$SANDBOX_PROXY_PID_FILE"
    echo "Sandbox proxy started (pid $(cat $SANDBOX_PROXY_PID_FILE), log: /tmp/sandbox-proxy.log)"
  else
    echo "Sandbox proxy not found at tools/sandbox-proxy/sandbox-proxy.js"
  fi
fi

echo "start_services complete"
