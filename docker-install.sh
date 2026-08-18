#!/bin/bash
# Ginto AI Docker Installation Script
# One-step installation for Docker mode deployment
#
# Usage:
#   curl -fsSL https://raw.githubusercontent.com/oliverbob/silverqueen.pro/main/docker-install.sh | bash
#   OR
#   ./docker-install.sh
#
# Requirements:
#   - Docker 20.10+ with Docker Compose v2
#   - At least one LLM API key (Groq, OpenAI, Anthropic, etc.)

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Print functions
print_info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
print_success() { echo -e "${GREEN}✅ $1${NC}"; }
print_warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
print_error() { echo -e "${RED}❌ $1${NC}"; }
print_header() {
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}  $1${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

# ASCII Art Banner
print_banner() {
    echo ""
    echo -e "${GREEN}"
    cat << 'EOF'
   _____ _       _           _    ___ 
  / ____(_)     | |         / \  |_ _|
 | |  __ _ _ __ | |_ ___   / _ \  | | 
 | | |_ | | '_ \| __/ _ \ / ___ \ | | 
 | |__| | | | | | || (_) / /   \ \| | 
  \_____|_|_| |_|\__\___/_/     \_\___|
                                       
  🐳 Docker Installation Mode
EOF
    echo -e "${NC}"
}

# Check if Docker is installed
check_docker() {
    print_header "Checking Docker Installation"
    
    if ! command -v docker &> /dev/null; then
        print_error "Docker is not installed"
        echo ""
        echo "Please install Docker first:"
        echo "  curl -fsSL https://get.docker.com | sh"
        echo "  sudo usermod -aG docker \$USER"
        echo "  # Log out and back in"
        exit 1
    fi
    
    # Check Docker daemon
    if ! docker info &> /dev/null; then
        print_error "Docker daemon is not running"
        echo ""
        echo "Start Docker with:"
        echo "  sudo systemctl start docker"
        echo "  OR"
        echo "  sudo service docker start"
        exit 1
    fi
    
    # Check Docker Compose
    if ! docker compose version &> /dev/null; then
        print_warning "Docker Compose v2 not found, checking for docker-compose..."
        if ! command -v docker-compose &> /dev/null; then
            print_error "Docker Compose is not installed"
            echo ""
            echo "Install Docker Compose v2:"
            echo "  sudo apt install docker-compose-plugin"
            exit 1
        fi
        COMPOSE_CMD="docker-compose"
    else
        COMPOSE_CMD="docker compose"
    fi
    
    print_success "Docker is installed and running"
    docker --version
    $COMPOSE_CMD version
}

# Clone or update the repository
clone_repo() {
    print_header "Setting Up Repository"
    
    INSTALL_DIR="${GINTO_INSTALL_DIR:-$HOME/silverqueen.pro}"
    
    if [ -d "$INSTALL_DIR" ]; then
        print_info "Repository exists at $INSTALL_DIR"
        cd "$INSTALL_DIR"
        
        # Check if it's a git repository
        if [ -d ".git" ]; then
            print_info "Updating existing repository..."
            git pull --ff-only || print_warning "Could not update (changes may be present)"
        fi
    else
        print_info "Cloning repository to $INSTALL_DIR..."
        git clone https://github.com/oliverbob/silverqueen.pro.git "$INSTALL_DIR"
        cd "$INSTALL_DIR"
    fi
    
    print_success "Repository ready at $INSTALL_DIR"
}

# Create environment configuration
setup_env() {
    print_header "Configuring Environment"
    
    if [ -f ".env" ]; then
        print_info ".env file already exists"
        read -p "Do you want to reconfigure? (y/N): " RECONFIG
        if [[ ! "$RECONFIG" =~ ^[Yy]$ ]]; then
            print_info "Keeping existing configuration"
            return
        fi
    fi
    
    # Copy template
    cp docker/.env.example .env
    
    echo ""
    echo "Please configure your API keys (at least one required):"
    echo ""
    
    # Groq API Key
    read -p "Groq API Key (recommended, press Enter to skip): " GROQ_KEY
    if [ -n "$GROQ_KEY" ]; then
        sed -i "s/^GROQ_API_KEY=.*/GROQ_API_KEY=$GROQ_KEY/" .env
    fi
    
    # OpenAI API Key
    read -p "OpenAI API Key (press Enter to skip): " OPENAI_KEY
    if [ -n "$OPENAI_KEY" ]; then
        sed -i "s/^OPENAI_API_KEY=.*/OPENAI_API_KEY=$OPENAI_KEY/" .env
    fi
    
    # Anthropic API Key
    read -p "Anthropic API Key (press Enter to skip): " ANTHROPIC_KEY
    if [ -n "$ANTHROPIC_KEY" ]; then
        sed -i "s/^ANTHROPIC_API_KEY=.*/ANTHROPIC_API_KEY=$ANTHROPIC_KEY/" .env
    fi
    
    # Cerebras API Key
    read -p "Cerebras API Key (press Enter to skip): " CEREBRAS_KEY
    if [ -n "$CEREBRAS_KEY" ]; then
        sed -i "s/^CEREBRAS_API_KEY=.*/CEREBRAS_API_KEY=$CEREBRAS_KEY/" .env
    fi
    
    # Check if at least one key is set
    if [ -z "$GROQ_KEY" ] && [ -z "$OPENAI_KEY" ] && [ -z "$ANTHROPIC_KEY" ] && [ -z "$CEREBRAS_KEY" ]; then
        print_warning "No API keys configured!"
        echo "You can add them later in .env"
    fi
    
    echo ""
    
    # Database password
    read -p "Database password (press Enter for auto-generated): " DB_PASS
    if [ -z "$DB_PASS" ]; then
        DB_PASS=$(openssl rand -base64 16 | tr -d '=+/')
        print_info "Generated database password"
    fi
    sed -i "s/^DB_PASS=.*/DB_PASS=$DB_PASS/" .env
    
    # Root password
    DB_ROOT_PASS=$(openssl rand -base64 16 | tr -d '=+/')
    sed -i "s/^DB_ROOT_PASS=.*/DB_ROOT_PASS=$DB_ROOT_PASS/" .env
    
    # IP permutation key for sandbox security
    IP_KEY=$(openssl rand -base64 32 | tr -d '=+/')
    sed -i "s/^IP_PERMUTATION_KEY=.*/IP_PERMUTATION_KEY=$IP_KEY/" .env
    
    print_success "Environment configured"
}

# Set up environment variables for Docker Compose
setup_host_env() {
    # Export current user's UID and GID for file permissions
    export HOST_UID=$(id -u)
    export HOST_GID=$(id -g)
    
    # Export Docker group GID for socket access
    if [ -S /var/run/docker.sock ]; then
        export DOCKER_GID=$(stat -c '%g' /var/run/docker.sock)
    else
        export DOCKER_GID=999
    fi
}

# Build and start containers
start_services() {
    print_header "Building Docker Images"
    
    # Set up host environment variables
    setup_host_env
    
    print_info "Building images (this may take a few minutes)..."
    $COMPOSE_CMD build --parallel
    
    print_success "Images built successfully"
    
    print_header "Starting Services"
    
    print_info "Starting all services..."
    $COMPOSE_CMD up -d
    
    # Wait for services to be healthy
    print_info "Waiting for services to be ready..."
    sleep 10
    
    # Check service status
    $COMPOSE_CMD ps
    
    # Start SDCPU (FastSD CPU) if available
    if [ -d "tools/sdcpu/venv" ] && [ -f "tools/sdcpu/src/api_server.py" ]; then
        print_info "Starting SDCPU (FastSD CPU) image generation server..."
        SDCPU_PID_FILE="/tmp/ginto-sdcpu.pid"
        
        # Kill any existing process
        if [ -f "$SDCPU_PID_FILE" ]; then
            OLD_PID=$(cat "$SDCPU_PID_FILE")
            kill "$OLD_PID" 2>/dev/null || true
            rm -f "$SDCPU_PID_FILE"
        fi
        
        # Start SDCPU
        cd tools/sdcpu
        source venv/bin/activate
        nohup python src/api_server.py --port 8888 > /tmp/sdcpu.log 2>&1 &
        echo $! > "$SDCPU_PID_FILE"
        cd ../..
        print_success "SDCPU started (pid $(cat $SDCPU_PID_FILE))"
    fi
    
    print_success "Services started successfully"
}

# Build sandbox image
build_sandbox_image() {
    print_header "Building Sandbox Image"
    
    if [ -f "docker/sandbox/Dockerfile" ]; then
        print_info "Building ginto/sandbox:latest image..."
        docker build -t ginto/sandbox:latest -f docker/sandbox/Dockerfile docker/sandbox/
        print_success "Sandbox image built successfully"
    else
        print_warning "Sandbox Dockerfile not found, skipping sandbox image build"
    fi
}

# Create sandbox network
setup_sandbox_network() {
    print_header "Setting Up Sandbox Network"
    
    SUBNET="${DOCKER_SANDBOX_SUBNET:-172.30.0.0/16}"
    
    if docker network inspect ginto-sandbox &> /dev/null; then
        print_info "Sandbox network already exists"
    else
        print_info "Creating sandbox network (subnet: $SUBNET)..."
        docker network create \
            --driver bridge \
            --subnet="$SUBNET" \
            ginto-sandbox || true
        print_success "Sandbox network created"
    fi
}

# Create storage directory outside repo for session persistence
setup_storage() {
    print_header "Setting Up Storage Directory"
    
    STORAGE_DIR="$(dirname "$INSTALL_DIR")/storage"
    
    print_info "Creating storage directory at $STORAGE_DIR..."
    mkdir -p "$STORAGE_DIR/sessions"
    mkdir -p "$STORAGE_DIR/logs"
    mkdir -p "$STORAGE_DIR/cache"
    mkdir -p "$STORAGE_DIR/uploads"
    mkdir -p "$STORAGE_DIR/temp"
    mkdir -p "$STORAGE_DIR/backups"
    
    # Make directories writable by Docker's www-data (UID 33)
    # Using chmod 777 is safe here because these are user-specific directories
    chmod 755 "$STORAGE_DIR"
    chmod 777 "$STORAGE_DIR/sessions"
    chmod 777 "$STORAGE_DIR/logs"
    chmod 777 "$STORAGE_DIR/cache"
    chmod 777 "$STORAGE_DIR/uploads"
    chmod 777 "$STORAGE_DIR/temp"
    chmod 777 "$STORAGE_DIR/backups"
    
    print_success "Storage directory ready at $STORAGE_DIR"
}

# Print final instructions
print_final() {
    print_header "Installation Complete!"
    
    echo ""
    echo -e "${GREEN}🎉 Ginto AI is now running in Docker mode!${NC}"
    echo ""
    echo "Access the web interface at:"
    echo -e "  ${BLUE}http://localhost${NC}"
    echo ""
    echo "Useful commands:"
    echo "  $COMPOSE_CMD logs -f         # View logs"
    echo "  $COMPOSE_CMD ps              # Check service status"
    echo "  $COMPOSE_CMD down            # Stop services"
    echo "  $COMPOSE_CMD up -d           # Start services"
    echo "  $COMPOSE_CMD restart         # Restart services"
    echo ""
    echo "Configuration file:"
    echo "  $(pwd)/.env"
    echo ""
    echo "Documentation:"
    echo "  https://github.com/oliverbob/silverqueen.pro/blob/main/docker/README.md"
    echo ""
    
    if [ -f ".env" ]; then
        if ! grep -q "API_KEY=." .env; then
            print_warning "Remember to add at least one API key to .env!"
        fi
    fi
}

# Main installation flow
main() {
    print_banner
    
    check_docker
    clone_repo
    setup_env
    setup_storage
    build_sandbox_image
    setup_sandbox_network
    start_services
    print_final
}

# Run main function
main "$@"
