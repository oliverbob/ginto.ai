#!/usr/bin/env bash
# run.sh - Main entry point for Ginto AI
# Usage: ./run.sh [command]
#   install  - Install all dependencies and set up the project
#   start    - Start the web server and services
#   stop     - Stop all services
#   status   - Show status of services
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
    help|--help|-h)
        show_help
        ;;
    *)
        log_error "Unknown command: $1"
        show_help
        exit 1
        ;;
esac
