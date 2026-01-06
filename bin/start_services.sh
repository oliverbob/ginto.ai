#!/usr/bin/env bash
set -e
# Start auxiliary services (clients server, MCP servers, Ratchet, AI SDK)
cd "$(dirname "$0")/.."

echo "Starting auxiliary services..."

# === Ensure websockify is installed (required for noVNC) ===
if ! command -v websockify &>/dev/null; then
  echo "Installing websockify for noVNC support..."
  if command -v apt-get &>/dev/null; then
    sudo apt-get install -y python3-websockify 2>/dev/null || \
    pip3 install websockify --break-system-packages 2>/dev/null || \
    pip3 install websockify 2>/dev/null || true
  elif command -v apk &>/dev/null; then
    sudo apk add --no-cache py3-websockify 2>/dev/null || \
    pip3 install websockify 2>/dev/null || true
  fi
  if command -v websockify &>/dev/null; then
    echo "websockify installed successfully"
  else
    echo "Warning: Failed to install websockify - noVNC may not work"
  fi
fi

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
RAT_PORT=31827

# Always kill any process on the Ratchet port first (even if PID file is stale)
if command -v fuser &>/dev/null; then
  fuser -k ${RAT_PORT}/tcp 2>/dev/null || true
elif command -v lsof &>/dev/null; then
  lsof -ti:${RAT_PORT} | xargs -r kill -9 2>/dev/null || true
fi
sleep 0.5

# Check if already running via PID file
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

# === SDCPU (FastSD CPU Image Generation Server) ===
SDCPU_PID_FILE="/tmp/ginto-sdcpu.pid"
SDCPU_PORT=8888

# Check if already running
if [ -f "$SDCPU_PID_FILE" ]; then
  SDPID=$(cat "$SDCPU_PID_FILE")
  if ps -p "$SDPID" > /dev/null 2>&1; then
    echo "SDCPU server already running (PID: $SDPID)"
  else
    rm -f "$SDCPU_PID_FILE"
  fi
fi

# Start SDCPU if not running and venv exists
if [ ! -f "$SDCPU_PID_FILE" ]; then
  if [ -d "tools/sdcpu/venv" ] && [ -f "tools/sdcpu/src/api_server.py" ]; then
    echo "Starting SDCPU (FastSD CPU) server on port $SDCPU_PORT..."
    cd tools/sdcpu
    source venv/bin/activate
    nohup python src/api_server.py --port $SDCPU_PORT > /tmp/sdcpu.log 2>&1 &
    echo $! > "$SDCPU_PID_FILE"
    cd ../..
    echo "SDCPU started (pid $(cat $SDCPU_PID_FILE), log: /tmp/sdcpu.log)"
  else
    echo "SDCPU not installed (missing venv or api_server.py) - skipping"
  fi
fi

echo "start_services complete"
