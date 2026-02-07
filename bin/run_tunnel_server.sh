#!/bin/bash
# Ginto Tunnel Server Runner
# Starts the WebSocket-based reverse tunnel server
#
# Usage: ./run_tunnel_server.sh [start|stop|status|restart]

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
TUNNEL_SERVER="$PROJECT_ROOT/tools/tunnel/tunnel_server.php"
PID_FILE="/var/run/ginto-tunnel.pid"
LOG_FILE="/var/log/ginto-tunnel.log"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() { echo -e "${GREEN}[TUNNEL]${NC} $1"; }
warn() { echo -e "${YELLOW}[TUNNEL]${NC} $1"; }
error() { echo -e "${RED}[TUNNEL]${NC} $1" >&2; }

check_deps() {
    if ! command -v php &>/dev/null; then
        error "PHP not found"
        exit 1
    fi
    
    if [ ! -f "$TUNNEL_SERVER" ]; then
        error "Tunnel server not found: $TUNNEL_SERVER"
        exit 1
    fi
}

is_running() {
    if [ -f "$PID_FILE" ]; then
        local pid=$(cat "$PID_FILE")
        if ps -p "$pid" >/dev/null 2>&1; then
            return 0
        fi
        rm -f "$PID_FILE"
    fi
    return 1
}

start_server() {
    check_deps
    
    if is_running; then
        local pid=$(cat "$PID_FILE")
        warn "Tunnel server already running (PID: $pid)"
        exit 0
    fi
    
    log "Starting Ginto Tunnel Server..."
    
    # Create log file if it doesn't exist
    touch "$LOG_FILE" 2>/dev/null || LOG_FILE="/tmp/ginto-tunnel.log"
    
    # Start server in background
    cd "$PROJECT_ROOT"
    nohup php "$TUNNEL_SERVER" >> "$LOG_FILE" 2>&1 &
    local pid=$!
    
    echo $pid > "$PID_FILE" 2>/dev/null || echo $pid > "/tmp/ginto-tunnel.pid"
    
    sleep 1
    
    if is_running; then
        log "Tunnel server started (PID: $pid)"
        log "Listening on ws://0.0.0.0:8765"
        log "Log file: $LOG_FILE"
    else
        error "Failed to start tunnel server"
        cat "$LOG_FILE" | tail -20
        exit 1
    fi
}

stop_server() {
    if ! is_running; then
        warn "Tunnel server is not running"
        return
    fi
    
    local pid=$(cat "$PID_FILE")
    log "Stopping tunnel server (PID: $pid)..."
    
    kill "$pid" 2>/dev/null || true
    
    # Wait for graceful shutdown
    for i in {1..10}; do
        if ! ps -p "$pid" >/dev/null 2>&1; then
            break
        fi
        sleep 0.5
    done
    
    # Force kill if still running
    if ps -p "$pid" >/dev/null 2>&1; then
        kill -9 "$pid" 2>/dev/null || true
    fi
    
    rm -f "$PID_FILE"
    log "Tunnel server stopped"
}

status() {
    if is_running; then
        local pid=$(cat "$PID_FILE")
        log "Tunnel server is running (PID: $pid)"
        
        # Show some stats
        if command -v netstat &>/dev/null; then
            local conns=$(netstat -an 2>/dev/null | grep ':8765' | grep -c ESTABLISHED || echo 0)
            log "Active connections: $conns"
        fi
    else
        warn "Tunnel server is not running"
        exit 1
    fi
}

case "${1:-status}" in
    start)
        start_server
        ;;
    stop)
        stop_server
        ;;
    restart)
        stop_server
        sleep 1
        start_server
        ;;
    status)
        status
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status}"
        exit 1
        ;;
esac
