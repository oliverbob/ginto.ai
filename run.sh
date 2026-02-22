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

    if ! command -v python3 >/dev/null 2>&1; then
        log_error "python3 not found. Install Python 3 first."
        exit 1
    fi

    local target_user="${SUDO_USER:-$USER}"
    local target_home
    target_home="$(getent passwd "$target_user" | cut -d: -f6)"
    if [ -z "$target_home" ] || [ ! -d "$target_home" ]; then
        log_error "Could not determine home directory for user: $target_user"
        exit 1
    fi

    local venv_path="$target_home/.env"
    local service_path="/etc/systemd/system/ginto-lt.service"

    log_info "Preparing Python virtual environment at: $venv_path"
    if [ ! -x "$venv_path/bin/python" ]; then
        sudo -u "$target_user" python3 -m venv "$venv_path"
    fi

    log_info "Installing/updating mitmproxy in virtual environment..."
    sudo -u "$target_user" "$venv_path/bin/pip" install --upgrade pip >/dev/null
    sudo -u "$target_user" "$venv_path/bin/pip" install --upgrade mitmproxy

    log_info "Creating systemd service: $service_path"
    cat > "$service_path" <<EOF
[Unit]
Description=Ginto Local Tunnel Relay (18080 -> 8888)
After=network.target

[Service]
Type=simple
User=$target_user
Group=$target_user
WorkingDirectory=$target_home
Environment=HOME=$target_home
ExecStart=$venv_path/bin/mitmproxy --mode reverse:http://127.0.0.1:8888 -p 18080
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
        log_info "Relay target should now be reachable at http://127.0.0.1:18080"
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
