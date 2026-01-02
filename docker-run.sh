#!/bin/bash
# docker-run.sh - Ginto AI Docker Helper Script
# Simplifies common Docker operations for Ginto AI
#
# Usage:
#   ./docker-run.sh up          # Start all services
#   ./docker-run.sh down        # Stop all services
#   ./docker-run.sh logs        # View logs
#   ./docker-run.sh shell       # Open PHP shell
#   ./docker-run.sh status      # Check service status

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Print colored message
info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
success() { echo -e "${GREEN}✅ $1${NC}"; }
warn() { echo -e "${YELLOW}⚠️  $1${NC}"; }
error() { echo -e "${RED}❌ $1${NC}"; }

# Check if Docker is installed
check_docker() {
    if ! command -v docker &> /dev/null; then
        error "Docker is not installed. Please install Docker first."
        echo "  Visit: https://docs.docker.com/get-docker/"
        exit 1
    fi
    
    if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
        error "Docker Compose is not installed."
        exit 1
    fi
}

# Detect docker-compose command
compose_cmd() {
    if docker compose version &> /dev/null; then
        echo "docker compose"
    else
        echo "docker-compose"
    fi
}

# Check if .env exists
check_env() {
    if [ ! -f ".env" ]; then
        warn ".env file not found. Creating from template..."
        if [ -f "docker/.env.example" ]; then
            cp docker/.env.example .env
            success ".env file created. Please edit it with your API keys:"
            echo "  nano .env"
            echo ""
        else
            error "docker/.env.example not found!"
            exit 1
        fi
    fi
}

# Show usage
usage() {
    echo ""
    echo "🐳 Ginto AI Docker Helper"
    echo ""
    echo "Usage: $0 <command> [options]"
    echo ""
    echo "Commands:"
    echo "  up              Start all services"
    echo "  up --build      Rebuild and start all services"
    echo "  up --sandbox    Start with Docker sandbox support"
    echo "  down            Stop all services"
    echo "  down -v         Stop and remove volumes (WARNING: deletes data)"
    echo "  restart         Restart all services"
    echo "  logs            Follow all logs"
    echo "  logs <service>  Follow specific service logs (php, caddy, mariadb, etc.)"
    echo "  shell           Open bash shell in PHP container"
    echo "  db              Open MySQL shell"
    echo "  redis           Open Redis CLI"
    echo "  status          Show container status"
    echo "  health          Check service health"
    echo "  clean           Remove all containers, images, and volumes"
    echo "  install         First-time setup"
    echo ""
    echo "Examples:"
    echo "  $0 up                   # Start all services"
    echo "  $0 logs php             # Follow PHP logs"
    echo "  $0 shell                # Open PHP shell"
    echo "  $0 up --sandbox         # Start with Docker sandboxes"
    echo ""
}

# Start services
cmd_up() {
    check_env
    
    local compose=$(compose_cmd)
    local args=""
    local compose_files="-f docker-compose.yml"
    
    for arg in "$@"; do
        case "$arg" in
            --build)
                args="$args --build"
                ;;
            --sandbox)
                compose_files="$compose_files -f docker-compose.sandbox.yml"
                info "Enabling Docker sandbox support..."
                ;;
        esac
    done
    
    info "Starting Ginto AI services..."
    $compose $compose_files up -d $args
    
    # Start SDCPU (FastSD CPU) if available
    start_sdcpu
    
    echo ""
    success "Ginto AI is starting!"
    echo ""
    echo "  🌐 Web UI:    http://localhost"
    echo "  📊 Logs:      $0 logs"
    echo "  🔧 Shell:     $0 shell"
    echo ""
}

# Start SDCPU (FastSD CPU) image generation server
start_sdcpu() {
    if [ -d "tools/sdcpu/venv" ] && [ -f "tools/sdcpu/src/api_server.py" ]; then
        SDCPU_PID_FILE="/tmp/ginto-sdcpu.pid"
        
        # Check if already running
        if [ -f "$SDCPU_PID_FILE" ]; then
            SDPID=$(cat "$SDCPU_PID_FILE")
            if ps -p "$SDPID" > /dev/null 2>&1; then
                info "SDCPU already running (PID: $SDPID)"
                return 0
            else
                rm -f "$SDCPU_PID_FILE"
            fi
        fi
        
        info "Starting SDCPU (FastSD CPU) image generation server..."
        cd tools/sdcpu
        source venv/bin/activate
        nohup python src/api_server.py --port 8888 > /tmp/sdcpu.log 2>&1 &
        echo $! > "$SDCPU_PID_FILE"
        cd ../..
        success "SDCPU started (pid $(cat $SDCPU_PID_FILE))"
    fi
}

# Stop SDCPU
stop_sdcpu() {
    SDCPU_PID_FILE="/tmp/ginto-sdcpu.pid"
    if [ -f "$SDCPU_PID_FILE" ]; then
        PID=$(cat "$SDCPU_PID_FILE")
        if ps -p "$PID" > /dev/null 2>&1; then
            kill "$PID" && info "Stopped SDCPU server (pid $PID)"
            sleep 1
            if ps -p "$PID" > /dev/null 2>&1; then
                kill -9 "$PID" 2>/dev/null || true
            fi
        fi
        rm -f "$SDCPU_PID_FILE"
    fi
}

# Stop services
cmd_down() {
    local compose=$(compose_cmd)
    
    # Stop SDCPU first
    stop_sdcpu
    
    info "Stopping Ginto AI services..."
    $compose down "$@"
    success "All services stopped."
}

# Restart services
cmd_restart() {
    local compose=$(compose_cmd)
    
    info "Restarting Ginto AI services..."
    $compose restart
    success "All services restarted."
}

# View logs
cmd_logs() {
    local compose=$(compose_cmd)
    
    if [ -n "$1" ]; then
        info "Following logs for $1..."
        $compose logs -f "$1"
    else
        info "Following all logs..."
        $compose logs -f
    fi
}

# Open PHP shell
cmd_shell() {
    local compose=$(compose_cmd)
    
    info "Opening PHP shell..."
    $compose exec php bash
}

# Open database shell
cmd_db() {
    local compose=$(compose_cmd)
    
    info "Opening MariaDB shell..."
    $compose exec mariadb mysql -u ginto -psecret ginto
}

# Open Redis CLI
cmd_redis() {
    local compose=$(compose_cmd)
    
    info "Opening Redis CLI..."
    $compose exec redis redis-cli
}

# Show status
cmd_status() {
    local compose=$(compose_cmd)
    
    info "Container status:"
    echo ""
    $compose ps
}

# Check health
cmd_health() {
    local compose=$(compose_cmd)
    
    info "Checking service health..."
    echo ""
    
    # Check each container
    for container in ginto-php ginto-caddy ginto-mariadb ginto-redis; do
        if docker inspect "$container" &> /dev/null; then
            local status=$(docker inspect --format='{{.State.Status}}' "$container" 2>/dev/null)
            local health=$(docker inspect --format='{{.State.Health.Status}}' "$container" 2>/dev/null || echo "no healthcheck")
            
            if [ "$status" == "running" ]; then
                if [ "$health" == "healthy" ] || [ "$health" == "no healthcheck" ]; then
                    success "$container: $status ($health)"
                else
                    warn "$container: $status ($health)"
                fi
            else
                error "$container: $status"
            fi
        else
            error "$container: not found"
        fi
    done
    
    echo ""
    
    # Check web access
    if curl -s -o /dev/null -w "%{http_code}" http://localhost/health | grep -q "200"; then
        success "Web server responding on http://localhost"
    else
        warn "Web server not responding (may still be starting)"
    fi
}

# Clean everything
cmd_clean() {
    local compose=$(compose_cmd)
    
    warn "This will remove ALL Ginto AI containers, images, and volumes!"
    read -p "Are you sure? (y/N) " -n 1 -r
    echo ""
    
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        info "Stopping and removing containers..."
        $compose down -v --rmi all 2>/dev/null || true
        
        info "Removing orphan volumes..."
        docker volume prune -f 2>/dev/null || true
        
        success "Cleanup complete."
    else
        info "Cleanup cancelled."
    fi
}

# First-time install
cmd_install() {
    check_docker
    check_env
    
    info "Running first-time setup..."
    
    local compose=$(compose_cmd)
    
    # Build images
    info "Building Docker images..."
    $compose build
    
    # Start services
    info "Starting services..."
    $compose up -d
    
    # Wait for services to be ready
    info "Waiting for services to start..."
    sleep 10
    
    # Check health
    cmd_health
    
    echo ""
    success "Installation complete!"
    echo ""
    echo "  🌐 Access Ginto AI at: http://localhost"
    echo ""
    echo "  Don't forget to add your API keys to .env:"
    echo "    nano .env"
    echo ""
}

# Main entry point
main() {
    check_docker
    
    case "${1:-}" in
        up)
            shift
            cmd_up "$@"
            ;;
        down)
            shift
            cmd_down "$@"
            ;;
        restart)
            cmd_restart
            ;;
        logs)
            shift
            cmd_logs "$@"
            ;;
        shell|bash|php)
            cmd_shell
            ;;
        db|mysql|mariadb)
            cmd_db
            ;;
        redis)
            cmd_redis
            ;;
        status|ps)
            cmd_status
            ;;
        health|check)
            cmd_health
            ;;
        clean|prune)
            cmd_clean
            ;;
        install|setup)
            cmd_install
            ;;
        help|--help|-h)
            usage
            ;;
        *)
            usage
            exit 1
            ;;
    esac
}

main "$@"
