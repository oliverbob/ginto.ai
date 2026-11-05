#!/bin/bash
# Start websockify for noVNC - designed to be run from PHP
# Usage: start_websockify.sh <port> <vnc_target> [web_root]

PORT="${1:-6080}"
TARGET="${2:-localhost:5900}"
WEB_ROOT="${3:-/home/test/silverqueen.pro/public/lib/novnc}"
LOG_FILE="/tmp/websockify-${PORT}.log"
PID_FILE="/tmp/websockify-${PORT}.pid"
TARGET_FILE="/tmp/websockify-${PORT}.target"

# Check if already running with this port AND correct target
if [ -f "$PID_FILE" ]; then
    OLD_PID=$(cat "$PID_FILE" 2>/dev/null)
    OLD_TARGET=$(cat "$TARGET_FILE" 2>/dev/null)
    if [ -n "$OLD_PID" ] && kill -0 "$OLD_PID" 2>/dev/null; then
        if [ "$OLD_TARGET" = "$TARGET" ]; then
            echo "Already running: PID $OLD_PID -> $TARGET"
            exit 0
        else
            # Target changed - kill old process
            echo "Target changed from $OLD_TARGET to $TARGET, restarting..."
            kill "$OLD_PID" 2>/dev/null
            sleep 0.5
        fi
    fi
    rm -f "$PID_FILE" "$TARGET_FILE"
fi

# Kill any stale websockify processes on this port
# Use pgrep + kill to avoid killing ourselves, and match websockify binary specifically
for pid in $(pgrep -f "python.*websockify.* ${PORT} " 2>/dev/null); do
    kill "$pid" 2>/dev/null
done
# Also kill by port using fuser if available (more reliable)
fuser -k ${PORT}/tcp 2>/dev/null || true
sleep 0.5

# Start websockify in background with setsid for full detachment
# --heartbeat 30: send ping every 30 seconds to detect stale connections
setsid websockify --web="$WEB_ROOT" --heartbeat 30 "$PORT" "$TARGET" > "$LOG_FILE" 2>&1 &
WS_PID=$!

# Save PID and target
echo "$WS_PID" > "$PID_FILE"
echo "$TARGET" > "$TARGET_FILE"

# Wait briefly and check port is listening
sleep 1
if ss -tlnp 2>/dev/null | grep -q ":${PORT}"; then
    echo "Started on port $PORT"
    exit 0
else
    echo "Failed to start on port $PORT"
    cat "$LOG_FILE" 2>/dev/null
    exit 1
fi