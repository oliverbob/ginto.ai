#!/usr/bin/env bash
# run.sh - Main entry point for Ginto AI
# Usage: ./run.sh [command]
#   install  - Install all dependencies and set up the project
#   start    - Start the web server and services
#   stop     - Stop all services
#   status   - Show status of services
#   local-tunnel - Install/start persistent local relay on 127.0.0.1:18080 -> 127.0.0.1:8888
#   novnc-local - Install/update local noVNC from GitHub and configure systemd service
#   help     - Show this help message

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[OK]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

show_help() {
    echo "Ginto AI - Run Script"
    echo ""
    echo "Usage: ./run.sh [command]"
    echo ""
    echo "Commands:"
    echo "  install    Install all dependencies (requires sudo)"
    echo "  start      Start the web server and services"
    echo "  stop       Stop all running services"
    echo "  status     Show status of all services"
    echo "  local-tunnel Configure persistent local relay service (requires sudo)"
    echo "  novnc-local Install/update local noVNC and configure novnc.service"
    echo "  help       Show this help message"
    echo ""
    echo "Examples:"
    echo "  sudo ./run.sh install    # First-time setup"
    echo "  ./run.sh start           # Start the application"
}

cmd_install() {
    log_info "Running installation via bin/gintoai.sh..."
    bash "$SCRIPT_DIR/bin/gintoai.sh" install
}

cmd_start() {
    log_info "Starting Ginto AI..."
    if command -v composer &>/dev/null; then
        composer start
    else
        log_warn "Composer not found, using direct script..."
        bash bin/ginto start --services
    fi
}

cmd_stop() {
    log_info "Stopping Ginto AI..."
    if command -v composer &>/dev/null; then
        composer stop
    else
        bash bin/stop_all.sh
        bash bin/mcp_stop.sh
    fi
    log_success "All services stopped"
}

cmd_status() {
    log_info "Checking service status..."
    echo ""
    
    # Web server
    if pgrep -f "php.*:8000" > /dev/null 2>&1; then
        echo -e "Web Server:     ${GREEN}Running${NC}"
    else
        echo -e "Web Server:     ${RED}Stopped${NC}"
    fi
    
    # Ratchet WebSocket
    if [ -f /tmp/ratchet_stream.pid ] && ps -p "$(cat /tmp/ratchet_stream.pid 2>/dev/null)" > /dev/null 2>&1; then
        echo -e "Ratchet WS:     ${GREEN}Running${NC} (PID: $(cat /tmp/ratchet_stream.pid))"
    else
        echo -e "Ratchet WS:     ${RED}Stopped${NC}"
    fi
    
    # Clients server
    if [ -f /tmp/clients_server.pid ] && ps -p "$(cat /tmp/clients_server.pid 2>/dev/null)" > /dev/null 2>&1; then
        echo -e "Clients Server: ${GREEN}Running${NC} (PID: $(cat /tmp/clients_server.pid))"
    else
        echo -e "Clients Server: ${RED}Stopped${NC}"
    fi
    
    echo ""
}

cmd_novnc_local() {
    log_info "Installing/configuring local noVNC via bin/gintoai.sh..."
    bash "$SCRIPT_DIR/bin/gintoai.sh" novnc-local
}

cmd_local_tunnel() {
    if [ "${EUID:-$(id -u)}" -ne 0 ]; then
        log_error "local-tunnel requires root. Run: sudo ./run.sh local-tunnel"
        exit 1
    fi

    if ! command -v docker >/dev/null 2>&1; then
        log_error "docker not found. Install Docker first."
        exit 1
    fi

    local service_path="/etc/systemd/system/ginto-lt.service"

    log_info "Ensuring Docker service is enabled and running..."
    systemctl enable docker >/dev/null 2>&1 || true
    systemctl restart docker

    log_info "Creating systemd service: $service_path"
    cat > "$service_path" <<EOF
[Unit]
Description=Ginto Local Tunnel Relay (Docker, 127.0.0.1:18080 -> 127.0.0.1:8888)
After=network-online.target docker.service
Wants=network-online.target docker.service

[Service]
Type=simple
ExecStartPre=-/usr/bin/docker rm -f ginto-local-tunnel
ExecStartPre=/usr/bin/docker pull alpine/socat:latest
ExecStart=/usr/bin/docker run --rm --name ginto-local-tunnel --network host alpine/socat:latest -d -d TCP-LISTEN:18080,bind=127.0.0.1,fork,reuseaddr TCP:127.0.0.1:8888
ExecStop=/usr/bin/docker stop ginto-local-tunnel
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

    log_info "Reloading systemd and enabling ginto-lt.service..."
    systemctl daemon-reload
    systemctl enable ginto-lt.service
    systemctl restart ginto-lt.service

    if systemctl is-active --quiet ginto-lt.service; then
        log_success "ginto-lt.service is active"
        log_info "Relay is loopback-only at http://127.0.0.1:18080 (not exposed externally)"
    else
        log_warn "ginto-lt.service failed to start"
        log_info "Check logs: sudo journalctl -u ginto-lt.service -n 80 --no-pager"
        exit 1
    fi
}

# Main command dispatcher
case "${1:-help}" in
    install)
        cmd_install
        ;;
    start)
        cmd_start
        ;;
    stop)
        cmd_stop
        ;;
    status)
        cmd_status
        ;;
    novnc-local)
        cmd_novnc_local
        ;;
    local-tunnel)
        cmd_local_tunnel
        ;;
    help|--help|-h)
        show_help
        ;;
    *)
        log_error "Unknown command: $1"
        show_help
        exit 1
        ;;
esac
