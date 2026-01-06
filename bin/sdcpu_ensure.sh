#!/bin/bash
# Ensure SDCPU is running - can be called from cron or systemd timer
# Usage: bin/sdcpu_ensure.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
SDCPU_DIR="$PROJECT_DIR/tools/sdcpu"
SDCPU_PID_FILE="/tmp/ginto-sdcpu.pid"
SDCPU_LOG="/tmp/sdcpu.log"
SDCPU_PORT=8888

# Check if SDCPU is configured
if [ ! -d "$SDCPU_DIR/venv" ] || [ ! -f "$SDCPU_DIR/src/api_server.py" ]; then
    exit 0  # SDCPU not set up, skip silently
fi

# Check if already running via PID file
if [ -f "$SDCPU_PID_FILE" ]; then
    SDPID=$(cat "$SDCPU_PID_FILE")
    if ps -p "$SDPID" > /dev/null 2>&1; then
        # Also verify it's responding
        if curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$SDCPU_PORT/health" 2>/dev/null | grep -q "200"; then
            exit 0  # Already running and healthy
        fi
    fi
    rm -f "$SDCPU_PID_FILE"
fi

# Check if something else is using the port
if netstat -tuln 2>/dev/null | grep -q ":$SDCPU_PORT " || ss -tuln 2>/dev/null | grep -q ":$SDCPU_PORT "; then
    # Port in use - check if it's our server
    if curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$SDCPU_PORT/health" 2>/dev/null | grep -q "200"; then
        exit 0  # Another instance running and healthy
    fi
fi

# Start SDCPU
echo "$(date): Starting SDCPU server..." >> "$SDCPU_LOG"
cd "$SDCPU_DIR"
source venv/bin/activate
nohup python src/api_server.py --port $SDCPU_PORT >> "$SDCPU_LOG" 2>&1 &
echo $! > "$SDCPU_PID_FILE"
echo "$(date): SDCPU started with PID $(cat $SDCPU_PID_FILE)" >> "$SDCPU_LOG"
