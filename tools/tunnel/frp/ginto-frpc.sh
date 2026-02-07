#!/bin/bash
#
# Ginto FRP Client - Easy tunnel to ginto.ai
#
# Usage:
#   ginto-frpc expose <subdomain> <local_port>  - Expose local port as subdomain.ginto.ai
#   ginto-frpc status                           - Check tunnel status
#   ginto-frpc stop                             - Stop tunnel
#   ginto-frpc install                          - Download frpc binary
#
# Environment variables:
#   GINTO_FRP_TOKEN   - Your authentication token (get from ginto.ai dashboard)
#   GINTO_FRP_SERVER  - Server address (default: ginto.ai)
#   GINTO_FRP_PORT    - Server port (default: 7000)
#

set -e

# Configuration
FRP_VERSION="${FRP_VERSION:-0.66.0}"
FRP_SERVER="${GINTO_FRP_SERVER:-ginto.ai}"
FRP_PORT="${GINTO_FRP_PORT:-7000}"
FRP_TOKEN="${GINTO_FRP_TOKEN:-}"
FRP_DIR="${HOME}/.ginto-frp"
FRP_BIN="${FRP_DIR}/frpc"
FRP_CONFIG="${FRP_DIR}/frpc.toml"
FRP_PID_FILE="${FRP_DIR}/frpc.pid"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

print_banner() {
    echo -e "${CYAN}"
    echo "╔═══════════════════════════════════════════╗"
    echo "║       Ginto FRP Tunnel Client             ║"
    echo "║       Expose local services easily        ║"
    echo "╚═══════════════════════════════════════════╝"
    echo -e "${NC}"
}

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[✓]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[!]${NC} $1"; }
log_error() { echo -e "${RED}[✗]${NC} $1"; }

detect_arch() {
    local arch
    arch=$(uname -m)
    case $arch in
        x86_64)  echo "linux_amd64" ;;
        aarch64) echo "linux_arm64" ;;
        armv7l)  echo "linux_arm" ;;
        armv6l)  echo "linux_arm" ;;
        i686)    echo "linux_386" ;;
        darwin)
            if [[ $(uname -m) == "arm64" ]]; then
                echo "darwin_arm64"
            else
                echo "darwin_amd64"
            fi
            ;;
        *)
            log_error "Unsupported architecture: $arch"
            exit 1
            ;;
    esac
}

install_frpc() {
    local arch
    arch=$(detect_arch)
    
    log_info "Installing frpc for ${arch}..."
    
    mkdir -p "$FRP_DIR"
    
    local url="https://github.com/fatedier/frp/releases/download/v${FRP_VERSION}/frp_${FRP_VERSION}_${arch}.tar.gz"
    local tmp_file="/tmp/frp_${FRP_VERSION}.tar.gz"
    
    log_info "Downloading from ${url}..."
    
    if command -v curl &> /dev/null; then
        curl -sSL "$url" -o "$tmp_file"
    elif command -v wget &> /dev/null; then
        wget -q "$url" -O "$tmp_file"
    else
        log_error "Neither curl nor wget found. Please install one of them."
        exit 1
    fi
    
    log_info "Extracting..."
    tar -xzf "$tmp_file" -C /tmp
    cp "/tmp/frp_${FRP_VERSION}_${arch}/frpc" "$FRP_BIN"
    chmod +x "$FRP_BIN"
    
    rm -rf "/tmp/frp_${FRP_VERSION}_${arch}" "$tmp_file"
    
    log_success "frpc installed to ${FRP_BIN}"
    log_info "Version: $(${FRP_BIN} --version 2>/dev/null || echo 'unknown')"
}

check_dependencies() {
    if [[ ! -x "$FRP_BIN" ]]; then
        log_warn "frpc not found. Installing..."
        install_frpc
    fi
}

check_token() {
    if [[ -z "$FRP_TOKEN" ]]; then
        # Try to read from config file
        if [[ -f "${FRP_DIR}/token" ]]; then
            FRP_TOKEN=$(cat "${FRP_DIR}/token")
        fi
    fi
    
    if [[ -z "$FRP_TOKEN" ]]; then
        log_error "Authentication token not set!"
        echo ""
        echo "Get your token from https://ginto.ai/dashboard/tokens"
        echo ""
        echo "Then set it with one of:"
        echo "  export GINTO_FRP_TOKEN='your-token'"
        echo "  echo 'your-token' > ${FRP_DIR}/token"
        echo ""
        exit 1
    fi
}

validate_subdomain() {
    local subdomain="$1"
    if [[ ! "$subdomain" =~ ^[a-z0-9][a-z0-9-]{1,30}[a-z0-9]$ ]]; then
        log_error "Invalid subdomain format: $subdomain"
        echo "Use 3-32 lowercase letters, numbers, and hyphens."
        echo "Must start and end with a letter or number."
        exit 1
    fi
    
    # Check reserved
    local reserved=("www" "api" "admin" "mail" "ftp" "ssh" "tunnel" "app" "dev" "test" "staging")
    for r in "${reserved[@]}"; do
        if [[ "$subdomain" == "$r" ]]; then
            log_error "Subdomain '$subdomain' is reserved. Please choose another."
            exit 1
        fi
    done
}

check_local_port() {
    local port="$1"
    if ! (echo >/dev/tcp/127.0.0.1/"$port") 2>/dev/null; then
        log_warn "No service detected on port $port"
        echo "Make sure your service is running before exposing."
        read -p "Continue anyway? (y/n) " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
}

generate_config() {
    local subdomain="$1"
    local port="$2"
    local proxy_name="${subdomain}-$(date +%s)"
    
    cat > "$FRP_CONFIG" << EOF
# Ginto FRP Client Configuration
# Generated: $(date)

serverAddr = "${FRP_SERVER}"
serverPort = ${FRP_PORT}

auth.method = "token"
auth.token = "${FRP_TOKEN}"

transport.tls.enable = true
transport.tls.disableCustomTLSFirstByte = true
transport.poolCount = 3

log.to = "${FRP_DIR}/frpc.log"
log.level = "info"
log.maxDays = 3

[[proxies]]
name = "${proxy_name}"
type = "http"
localIP = "127.0.0.1"
localPort = ${port}
subdomain = "${subdomain}"
hostHeaderRewrite = "127.0.0.1"
EOF
    
    log_info "Configuration written to ${FRP_CONFIG}"
}

cmd_expose() {
    local subdomain="${1:-}"
    local port="${2:-8088}"
    
    if [[ -z "$subdomain" ]]; then
        log_error "Usage: ginto-frpc expose <subdomain> [local_port]"
        echo ""
        echo "Examples:"
        echo "  ginto-frpc expose myapp 8088     # Expose port 8088 as myapp.ginto.ai"
        echo "  ginto-frpc expose demo 3000      # Expose port 3000 as demo.ginto.ai"
        exit 1
    fi
    
    check_dependencies
    check_token
    validate_subdomain "$subdomain"
    
    # Check if already running
    if [[ -f "$FRP_PID_FILE" ]] && kill -0 "$(cat "$FRP_PID_FILE")" 2>/dev/null; then
        log_warn "Tunnel already running (PID: $(cat "$FRP_PID_FILE"))"
        log_info "Stop it first with: ginto-frpc stop"
        exit 1
    fi
    
    check_local_port "$port"
    generate_config "$subdomain" "$port"
    
    echo ""
    log_info "Starting tunnel..."
    
    # Run frpc in foreground (for interactive use)
    trap 'cmd_stop; exit 0' INT TERM
    
    "$FRP_BIN" -c "$FRP_CONFIG" &
    local pid=$!
    echo $pid > "$FRP_PID_FILE"
    
    # Wait a moment for connection
    sleep 2
    
    if kill -0 $pid 2>/dev/null; then
        echo ""
        log_success "Tunnel established!"
        echo ""
        echo -e "  ${CYAN}Public URL:${NC}  https://${subdomain}.${FRP_SERVER}/"
        echo -e "  ${CYAN}Local:${NC}       http://127.0.0.1:${port}/"
        echo ""
        echo "Press Ctrl+C to stop the tunnel"
        echo ""
        
        # Wait for frpc to exit
        wait $pid
    else
        log_error "Failed to establish tunnel. Check logs: cat ${FRP_DIR}/frpc.log"
        rm -f "$FRP_PID_FILE"
        exit 1
    fi
}

cmd_status() {
    check_dependencies
    
    if [[ -f "$FRP_PID_FILE" ]] && kill -0 "$(cat "$FRP_PID_FILE")" 2>/dev/null; then
        log_success "Tunnel is running (PID: $(cat "$FRP_PID_FILE"))"
        
        if [[ -f "$FRP_CONFIG" ]]; then
            local subdomain
            subdomain=$(grep 'subdomain' "$FRP_CONFIG" | cut -d'"' -f2)
            echo "  URL: https://${subdomain}.${FRP_SERVER}/"
        fi
    else
        log_info "No tunnel running"
    fi
}

cmd_stop() {
    if [[ -f "$FRP_PID_FILE" ]]; then
        local pid
        pid=$(cat "$FRP_PID_FILE")
        if kill -0 "$pid" 2>/dev/null; then
            log_info "Stopping tunnel (PID: $pid)..."
            kill "$pid" 2>/dev/null || true
            rm -f "$FRP_PID_FILE"
            log_success "Tunnel stopped"
        else
            rm -f "$FRP_PID_FILE"
            log_info "Tunnel was not running"
        fi
    else
        log_info "No tunnel running"
    fi
}

cmd_logs() {
    local log_file="${FRP_DIR}/frpc.log"
    if [[ -f "$log_file" ]]; then
        tail -f "$log_file"
    else
        log_info "No logs found"
    fi
}

cmd_version() {
    check_dependencies
    "$FRP_BIN" --version
}

cmd_help() {
    print_banner
    echo "Usage: ginto-frpc <command> [options]"
    echo ""
    echo "Commands:"
    echo "  expose <subdomain> [port]  Expose local port as subdomain.ginto.ai"
    echo "  status                     Check tunnel status"
    echo "  stop                       Stop running tunnel"
    echo "  logs                       View tunnel logs"
    echo "  install                    Download/update frpc binary"
    echo "  version                    Show frpc version"
    echo "  help                       Show this help"
    echo ""
    echo "Examples:"
    echo "  ginto-frpc expose myapp 8088    # https://myapp.ginto.ai -> localhost:8088"
    echo "  ginto-frpc expose demo 3000     # https://demo.ginto.ai -> localhost:3000"
    echo ""
    echo "Environment Variables:"
    echo "  GINTO_FRP_TOKEN    Your authentication token"
    echo "  GINTO_FRP_SERVER   Server address (default: ginto.ai)"
    echo "  GINTO_FRP_PORT     Server port (default: 7000)"
    echo ""
    echo "Get your token at: https://ginto.ai/dashboard/tokens"
    echo ""
}

# Main
case "${1:-help}" in
    expose)
        print_banner
        cmd_expose "$2" "$3"
        ;;
    status)
        cmd_status
        ;;
    stop)
        cmd_stop
        ;;
    logs)
        cmd_logs
        ;;
    install)
        print_banner
        install_frpc
        ;;
    version)
        cmd_version
        ;;
    help|--help|-h)
        cmd_help
        ;;
    *)
        log_error "Unknown command: $1"
        echo "Run 'ginto-frpc help' for usage."
        exit 1
        ;;
esac
