#!/usr/bin/env bash
# gintoai.sh - Ginto AI Installation Script
# This script installs all dependencies required to run Ginto AI
# Usage: ./bin/gintoai.sh install (will prompt for sudo when needed)

set -euo pipefail

# Make apt non-interactive
export DEBIAN_FRONTEND=noninteractive

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Checkpoint file for resume capability
CHECKPOINT_FILE="$PROJECT_DIR/.install_checkpoint"
CHECKPOINT_CONFIG="$PROJECT_DIR/.install_config"

# List of installation steps in order
INSTALL_STEPS=(
    "check_home_directory"
    "prompt_configuration"
    "check_sudo"
    "detect_os"
    "update_packages"
    "install_git"
    "install_utilities"
    "install_redis"
    "install_build_tools"
    "install_php"
    "install_mariadb"
    "install_caddy"
    "configure_caddy"
    "install_composer"
    "install_nodejs"
    "install_llamacpp"
    "configure_systemd_service"
    "setup_permissions"
    "install_dependencies"
    "setup_env"
    "start_services"
    "print_summary"
)

# Save checkpoint after completing a step
save_checkpoint() {
    local step="$1"
    echo "$step" > "$CHECKPOINT_FILE"
}

# Get last completed step (empty if none)
get_last_checkpoint() {
    if [ -f "$CHECKPOINT_FILE" ]; then
        cat "$CHECKPOINT_FILE"
    else
        echo ""
    fi
}

# Clear checkpoint (installation complete)
clear_checkpoint() {
    rm -f "$CHECKPOINT_FILE" "$CHECKPOINT_CONFIG"
}

# Save configuration to file for resume
save_config() {
    cat > "$CHECKPOINT_CONFIG" << EOF
CADDY_LIVE_MODE="$CADDY_LIVE_MODE"
CADDY_DOMAIN="$CADDY_DOMAIN"
CADDY_TLS_EMAIL="$CADDY_TLS_EMAIL"
OS="${OS:-}"
OS_VERSION="${OS_VERSION:-}"
DB_NAME="${DB_NAME:-ginto}"
DB_USER="${DB_USER:-ginto}"
DB_PASS="${DB_PASS:-}"
EOF
}

# Load configuration from file
load_config() {
    if [ -f "$CHECKPOINT_CONFIG" ]; then
        source "$CHECKPOINT_CONFIG"
        return 0
    fi
    return 1
}

# Check if step should run (based on checkpoint)
should_run_step() {
    local step="$1"
    local last_checkpoint="$2"
    
    # If no checkpoint, run all steps
    if [ -z "$last_checkpoint" ]; then
        return 0
    fi
    
    # Find index of last checkpoint and current step
    local last_idx=-1
    local step_idx=-1
    local i=0
    for s in "${INSTALL_STEPS[@]}"; do
        if [ "$s" == "$last_checkpoint" ]; then
            last_idx=$i
        fi
        if [ "$s" == "$step" ]; then
            step_idx=$i
        fi
        ((i++))
    done
    
    # Run step if it comes after the checkpoint
    if [ $step_idx -gt $last_idx ]; then
        return 0
    fi
    return 1
}

# Auto-detect the current user (the one running the script, not root)
if [ -n "${SUDO_USER:-}" ]; then
    INSTALL_USER="$SUDO_USER"
elif [ "$(whoami)" != "root" ]; then
    INSTALL_USER="$(whoami)"
else
    # Fallback: detect from project directory ownership
    INSTALL_USER="$(stat -c '%U' "$PROJECT_DIR" 2>/dev/null || echo "root")"
fi
INSTALL_USER_HOME=$(getent passwd "$INSTALL_USER" | cut -d: -f6)

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m'

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[OK]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }
log_step() { echo -e "\n${CYAN}==>${NC} $1"; }
log_prompt() { echo -e "${MAGENTA}[?]${NC} $1"; }

# Check that project is installed in home directory
check_home_directory() {
    log_step "Checking installation directory..."
    
    # Get the expected home directory path
    local expected_path="$INSTALL_USER_HOME/ginto"
    
    # Check if PROJECT_DIR is under the user's home directory
    if [[ "$PROJECT_DIR" != "$INSTALL_USER_HOME"* ]]; then
        log_error "Ginto MUST be installed in your home directory!"
        log_error "Current location: $PROJECT_DIR"
        log_error "Expected location: $expected_path"
        echo ""
        log_info "Please move the project to your home directory:"
        echo "  mv $PROJECT_DIR $expected_path"
        echo "  cd $expected_path"
        echo "  ./run.sh install"
        echo ""
        exit 1
    fi
    
    log_success "Project is correctly located in home directory: $PROJECT_DIR"
}

# Check sudo access
check_sudo() {
    if ! sudo -n true 2>/dev/null; then
        log_info "This script requires sudo access for system packages."
        sudo -v
    fi
}

# Detect OS
detect_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$ID
        OS_VERSION=$VERSION_ID
    else
        log_error "Cannot detect OS. /etc/os-release not found."
        exit 1
    fi
    log_info "Detected OS: $OS $OS_VERSION"
    log_info "Installing for user: $INSTALL_USER ($INSTALL_USER_HOME)"
    
    # Update config with OS info for resume capability
    save_config
}

# Update package lists
update_packages() {
    log_step "Updating package lists..."
    case $OS in
        ubuntu|debian)
            sudo apt-get update -qq
            ;;
        fedora|rhel|centos)
            sudo dnf check-update || true
            ;;
        *)
            log_warn "Unknown OS, skipping package update"
            ;;
    esac
    log_success "Package lists updated"
}

# Install PHP 8.x and required extensions
install_php() {
    log_step "Installing PHP 8.x and extensions..."
    
    # Check if PHP 8.x is already installed
    if command -v php &>/dev/null; then
        local php_version=$(php -v | head -1 | awk '{print $2}')
        if [[ "$php_version" =~ ^8\. ]]; then
            log_info "PHP already installed: $php_version"
            # Ensure redis extension is installed for existing PHP
            local php_major_minor=$(echo "$php_version" | cut -d. -f1,2)
            if ! php -m | grep -qi redis; then
                log_info "Installing missing php${php_major_minor}-redis..."
                sudo apt-get install -y "php${php_major_minor}-redis" 2>/dev/null || true
                sudo systemctl restart "php${php_major_minor}-fpm" 2>/dev/null || true
            fi
            return 0
        fi
    fi
    
    case $OS in
        ubuntu|debian)
            # Add PHP repository for latest PHP
            sudo apt-get install -y software-properties-common
            sudo add-apt-repository -y ppa:ondrej/php 2>/dev/null || true
            sudo apt-get update -qq
            
            # Detect latest STABLE PHP 8.x version with all required extensions
            # We check for opcache package to ensure it's a complete release
            local PHP_VERSION=""
            for ver in 8.4 8.3 8.2 8.1; do
                if apt-cache show "php${ver}-opcache" &>/dev/null; then
                    PHP_VERSION="$ver"
                    break
                fi
            done
            
            if [ -z "$PHP_VERSION" ]; then
                PHP_VERSION="8.3"  # Fallback
                log_warn "Could not detect stable PHP version, using $PHP_VERSION"
            else
                log_info "Detected latest stable PHP version: $PHP_VERSION"
            fi
            
            # Install PHP and extensions with detected version
            sudo apt-get install -y \
                "php${PHP_VERSION}" \
                "php${PHP_VERSION}-cli" \
                "php${PHP_VERSION}-fpm" \
                "php${PHP_VERSION}-mysql" \
                "php${PHP_VERSION}-sqlite3" \
                "php${PHP_VERSION}-curl" \
                "php${PHP_VERSION}-xml" \
                "php${PHP_VERSION}-mbstring" \
                "php${PHP_VERSION}-zip" \
                "php${PHP_VERSION}-gd" \
                "php${PHP_VERSION}-bcmath" \
                "php${PHP_VERSION}-intl" \
                "php${PHP_VERSION}-readline" \
                "php${PHP_VERSION}-opcache" \
                "php${PHP_VERSION}-redis"
            
            # Ensure this version is the default
            sudo update-alternatives --set php "/usr/bin/php${PHP_VERSION}" 2>/dev/null || true
            ;;
        fedora)
            sudo dnf install -y \
                php php-cli php-fpm php-mysqlnd php-pdo \
                php-curl php-xml php-mbstring php-zip php-gd \
                php-bcmath php-intl php-opcache php-redis
            ;;
        *)
            log_error "Unsupported OS for PHP installation: $OS"
            exit 1
            ;;
    esac
    
    log_success "PHP installed: $(php -v | head -1)"
}

# Install Composer
install_composer() {
    log_step "Installing Composer..."
    
    if command -v composer &>/dev/null; then
        log_info "Composer already installed: $(composer --version --no-interaction)"
        return
    fi
    
    cd /tmp
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
    sudo chmod +x /usr/local/bin/composer
    
    log_success "Composer installed: $(composer --version --no-interaction)"
}

# Install MariaDB
install_mariadb() {
    log_step "Installing MariaDB..."
    
    # Check if MariaDB is already installed and running
    if command -v mariadb &>/dev/null; then
        log_info "MariaDB already installed: $(mariadb --version 2>/dev/null | awk '{print $5}' | tr -d ',')"
        # Ensure it's running
        sudo systemctl enable mariadb 2>/dev/null || true
        sudo systemctl start mariadb 2>/dev/null || true
        
        # Skip database config if we detected existing installation
        if [[ "${SKIP_DB_USER_SETUP:-false}" == "true" ]]; then
            log_info "Database already configured, skipping user setup"
            return 0
        fi
        
        # Still configure database if needed
        log_step "Ensuring MariaDB database and user exist..."
        local escaped_pass=$(printf '%s' "$DB_PASS" | sed "s/'/''/g")
        sudo mariadb <<EOF
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${escaped_pass}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF
        log_success "Database '${DB_NAME}' configured with user '${DB_USER}'"
        return 0
    fi
    
    case $OS in
        ubuntu|debian)
            sudo apt-get install -y mariadb-server mariadb-client
            sudo systemctl enable mariadb
            sudo systemctl start mariadb
            ;;
        fedora)
            sudo dnf install -y mariadb-server mariadb
            sudo systemctl enable mariadb
            sudo systemctl start mariadb
            ;;
        *)
            log_warn "Skipping MariaDB installation for OS: $OS"
            return
            ;;
    esac
    
    log_success "MariaDB installed and running"
    
    # Create database and user
    log_step "Configuring MariaDB database and user..."
    
    # Escape special characters in password for SQL
    local escaped_pass=$(printf '%s' "$DB_PASS" | sed "s/'/''/g")
    
    # Create database and user using root access
    sudo mariadb <<EOF
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${escaped_pass}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF
    
    log_success "Database '${DB_NAME}' created with user '${DB_USER}'"
}

# Install Git
install_git() {
    log_step "Installing Git..."
    
    if command -v git &>/dev/null; then
        log_info "Git already installed: $(git --version)"
        return
    fi
    
    case $OS in
        ubuntu|debian)
            sudo apt-get install -y git
            ;;
        fedora)
            sudo dnf install -y git
            ;;
    esac
    
    log_success "Git installed: $(git --version)"
}

# Install additional utilities
install_utilities() {
    log_step "Installing utilities (curl, unzip, ffmpeg)..."
    
    # Check if all utilities are already installed
    local missing_utils=()
    command -v curl &>/dev/null || missing_utils+=("curl")
    command -v unzip &>/dev/null || missing_utils+=("unzip")
    command -v ffmpeg &>/dev/null || missing_utils+=("ffmpeg")
    command -v lsof &>/dev/null || missing_utils+=("lsof")
    
    if [ ${#missing_utils[@]} -eq 0 ]; then
        log_info "All utilities already installed (curl, unzip, ffmpeg, lsof)"
        return 0
    fi
    
    log_info "Installing missing utilities: ${missing_utils[*]}"
    
    case $OS in
        ubuntu|debian)
            sudo apt-get install -y curl unzip ffmpeg lsof iputils-ping net-tools
            ;;
        fedora)
            sudo dnf install -y curl unzip ffmpeg lsof iputils net-tools
            ;;
    esac
    
    log_success "Utilities installed"
}

# Install Redis for agent communication and caching
# NOTE: Redis is NOT used for IP routing (that's deterministic via Feistel permutation)
#       Redis is used for: agent pub/sub, session state, message queues
install_redis() {
    log_step "Checking Redis..."
    
    local redis_installed=false
    
    # Check if Redis is already installed and running
    if command -v redis-server &>/dev/null; then
        if systemctl is-active --quiet redis-server 2>/dev/null || systemctl is-active --quiet redis 2>/dev/null; then
            log_info "Redis already installed and running ($(redis-server --version | head -1 | awk '{print $3}'))"
            redis_installed=true
        fi
    fi
    
    # Install Redis if not present
    if ! $redis_installed; then
        log_info "Installing Redis server..."
        
        case $OS in
            ubuntu|debian)
                sudo apt-get install -y redis-server
                sudo systemctl enable --now redis-server
                ;;
            fedora)
                sudo dnf install -y redis
                sudo systemctl enable --now redis
                ;;
        esac
        
        # Verify Redis is running
        if systemctl is-active --quiet redis-server 2>/dev/null || systemctl is-active --quiet redis 2>/dev/null; then
            log_success "Redis installed and running"
        else
            log_warning "Redis installed but service may need manual start"
            return 1
        fi
    fi
}

# Install build tools for compiling llama.cpp and other native tools
install_build_tools() {
    log_step "Installing build tools (for llama.cpp and native compilation)..."
    
    # Check if essential build tools are already installed
    local have_gcc=false
    local have_cmake=false
    command -v gcc &>/dev/null && have_gcc=true
    command -v cmake &>/dev/null && have_cmake=true
    
    if $have_gcc && $have_cmake; then
        log_info "Build tools already installed (gcc: $(gcc --version | head -1 | awk '{print $NF}'), cmake: $(cmake --version | head -1 | awk '{print $3}'))"
        return 0
    fi
    
    log_info "Installing build tools..."
    
    case $OS in
        ubuntu|debian)
            sudo apt-get install -y \
                build-essential \
                cmake \
                pkg-config \
                libcurl4-openssl-dev \
                libssl-dev \
                libblas-dev \
                liblapack-dev \
                libopenblas-dev \
                ccache
            ;;
        fedora)
            sudo dnf install -y \
                gcc gcc-c++ make \
                cmake \
                pkg-config \
                libcurl-devel \
                openssl-devel \
                blas-devel \
                lapack-devel \
                openblas-devel \
                ccache
            ;;
        *)
            log_warn "Build tools installation not configured for OS: $OS"
            log_warn "You may need to manually install: gcc, g++, cmake, make"
            return
            ;;
    esac
    
    log_success "Build tools installed"
}

# Install Node.js (optional, for frontend assets)
install_nodejs() {
    log_step "Installing Node.js (optional)..."
    
    if command -v node &>/dev/null; then
        log_info "Node.js already installed: $(node --version)"
        return
    fi
    
    case $OS in
        ubuntu|debian)
            # Auto-detect current Node.js LTS version from nodesource
            # Fetches the latest LTS major version dynamically
            local NODE_LTS_VERSION
            NODE_LTS_VERSION=$(curl -sL https://nodejs.org/dist/index.json | grep -oP '"version":"v\K[0-9]+' | head -1)
            if [ -z "$NODE_LTS_VERSION" ]; then
                NODE_LTS_VERSION="22"  # Fallback to known LTS
                log_warn "Could not detect latest Node.js LTS, using v$NODE_LTS_VERSION"
            else
                log_info "Detected latest Node.js version: v$NODE_LTS_VERSION"
            fi
            curl -fsSL "https://deb.nodesource.com/setup_${NODE_LTS_VERSION}.x" | sudo -E bash -
            sudo apt-get install -y nodejs
            ;;
        fedora)
            sudo dnf install -y nodejs npm
            ;;
    esac
    
    if command -v node &>/dev/null; then
        log_success "Node.js installed: $(node --version)"
    else
        log_warn "Node.js installation skipped"
    fi
}

# Configure llama.cpp PATH in user's shell profile
configure_llamacpp_path() {
    local LLAMACPP_DIR="$INSTALL_USER_HOME/llama.cpp"
    local LLAMACPP_BIN="$LLAMACPP_DIR/build/bin"
    local BASHRC="$INSTALL_USER_HOME/.bashrc"
    local PROFILE="$INSTALL_USER_HOME/.profile"
    local PATH_LINE="export PATH=\"$LLAMACPP_BIN:\$PATH\""
    local PATH_MARKER="# llama.cpp PATH"
    
    # Add to /etc/profile.d for system-wide access (works in all shells including web terminal)
    local PROFILE_D="/etc/profile.d/llamacpp.sh"
    if [ ! -f "$PROFILE_D" ]; then
        log_info "Adding llama.cpp to system-wide PATH (/etc/profile.d/)..."
        sudo tee "$PROFILE_D" > /dev/null << EOF
# llama.cpp PATH - added by Ginto installer
if [ -d "$LLAMACPP_BIN" ]; then
    export PATH="$LLAMACPP_BIN:\$PATH"
fi
if [ -d "$INSTALL_USER_HOME/.local/bin" ]; then
    export PATH="$INSTALL_USER_HOME/.local/bin:\$PATH"
fi
EOF
        sudo chmod +x "$PROFILE_D"
    else
        log_info "System-wide llama.cpp PATH already configured"
    fi
    
    # Remove any existing llama.cpp PATH entries from .bashrc to prevent duplicates
    if [ -f "$BASHRC" ]; then
        # Remove old entries (the marker line and the next 3 lines after it)
        sed -i "/$PATH_MARKER/,+3d" "$BASHRC" 2>/dev/null || true
        # Also remove any stray llama.cpp references
        sed -i '/llama\.cpp\/build\/bin/d' "$BASHRC" 2>/dev/null || true
    fi
    
    # Add fresh entry to .bashrc
    log_info "Adding llama.cpp to PATH in .bashrc..."
    cat >> "$BASHRC" << EOF

$PATH_MARKER
if [ -d "$LLAMACPP_BIN" ]; then
    $PATH_LINE
fi
EOF
    
    # Remove and re-add to .profile for login shells
    if [ -f "$PROFILE" ]; then
        sed -i "/$PATH_MARKER/,+3d" "$PROFILE" 2>/dev/null || true
        sed -i '/llama\.cpp\/build\/bin/d' "$PROFILE" 2>/dev/null || true
        cat >> "$PROFILE" << EOF

$PATH_MARKER
if [ -d "$LLAMACPP_BIN" ]; then
    $PATH_LINE
fi
EOF
    fi
    
    # Create ~/.local/bin and add symlinks for immediate access
    mkdir -p "$INSTALL_USER_HOME/.local/bin"
    if [ -f "$LLAMACPP_BIN/llama-server" ]; then
        ln -sf "$LLAMACPP_BIN/llama-server" "$INSTALL_USER_HOME/.local/bin/llama-server" 2>/dev/null || true
    fi
    if [ -f "$LLAMACPP_BIN/llama-cli" ]; then
        ln -sf "$LLAMACPP_BIN/llama-cli" "$INSTALL_USER_HOME/.local/bin/llama-cli" 2>/dev/null || true
    fi
    
    # Ensure ~/.local/bin is in PATH too (only add if not present)
    if ! grep -q "# Local bin PATH" "$BASHRC" 2>/dev/null; then
        cat >> "$BASHRC" << 'EOF'

# Local bin PATH
if [ -d "$HOME/.local/bin" ]; then
    export PATH="$HOME/.local/bin:$PATH"
fi
EOF
    fi
    
    log_success "llama.cpp PATH configured system-wide"
}

# Install llama.cpp from source for local LLM inference
install_llamacpp() {
    log_step "Checking llama.cpp installation..."
    
    local LLAMACPP_DIR="$INSTALL_USER_HOME/llama.cpp"
    local LLAMACPP_BIN="$LLAMACPP_DIR/build/bin"
    
    # Check if already installed
    if [ -f "$LLAMACPP_BIN/llama-server" ] || [ -f "$LLAMACPP_BIN/llama-cli" ]; then
        log_info "llama.cpp already installed at $LLAMACPP_DIR"
        
        # Just ensure PATH is configured
        configure_llamacpp_path
        
        # Show available commands
        log_success "Available llama.cpp commands:"
        ls -1 "$LLAMACPP_BIN" 2>/dev/null | head -10 | while read cmd; do
            echo "  - $cmd"
        done
        return 0
    fi
    
    log_step "Installing llama.cpp from source..."
    
    # Clone the repository
    if [ -d "$LLAMACPP_DIR" ]; then
        log_info "llama.cpp directory exists, pulling latest..."
        cd "$LLAMACPP_DIR"
        git pull origin master || true
    else
        log_info "Cloning llama.cpp repository..."
        git clone https://github.com/ggerganov/llama.cpp.git "$LLAMACPP_DIR"
        cd "$LLAMACPP_DIR"
    fi
    
    # Create build directory
    mkdir -p build
    cd build
    
    # Configure with CMake
    log_info "Configuring llama.cpp build..."
    cmake .. \
        -DCMAKE_BUILD_TYPE=Release \
        -DLLAMA_CURL=ON \
        -DLLAMA_NATIVE=ON \
        -DLLAMA_BUILD_SERVER=ON
    
    # Build using all available cores
    local NPROC=$(nproc 2>/dev/null || echo 4)
    log_info "Building llama.cpp with $NPROC cores (this may take a few minutes)..."
    cmake --build . --config Release -j"$NPROC"
    
    # Verify installation and configure PATH
    if [ -f "$LLAMACPP_BIN/llama-server" ] || [ -f "$LLAMACPP_BIN/llama-cli" ]; then
        log_success "llama.cpp built successfully!"
        
        # Configure PATH
        configure_llamacpp_path
        
        # Show available commands
        log_success "Available llama.cpp commands:"
        ls -1 "$LLAMACPP_BIN" 2>/dev/null | head -10 | while read cmd; do
            echo "  - $cmd"
        done
    else
        log_warn "llama.cpp build may have failed. Check $LLAMACPP_DIR/build/ for details."
    fi
    
    # Return to project directory
    cd "$PROJECT_DIR"
}

# Install Caddy web server
install_caddy() {
    log_step "Installing Caddy web server..."
    
    if command -v caddy &>/dev/null; then
        log_info "Caddy already installed: $(caddy version)"
        return
    fi
    
    case $OS in
        ubuntu|debian)
            sudo apt-get install -y debian-keyring debian-archive-keyring apt-transport-https
            curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | sudo gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg 2>/dev/null || true
            curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | sudo tee /etc/apt/sources.list.d/caddy-stable.list >/dev/null
            sudo apt-get update -qq
            sudo apt-get install -y caddy
            ;;
        fedora)
            sudo dnf install -y 'dnf-command(copr)'
            sudo dnf copr enable -y @caddy/caddy
            sudo dnf install -y caddy
            ;;
        *)
            log_warn "Manual Caddy installation required for OS: $OS"
            return
            ;;
    esac
    
    log_success "Caddy installed: $(caddy version)"
}

# Configure Caddy (uses variables set by prompt_configuration)
configure_caddy() {
    log_step "Configuring Caddy..."
    
    # Skip if existing installation detected
    if [[ "${SKIP_CONFIGURED:-false}" == "true" ]]; then
        log_info "Caddy already configured, skipping"
        # Just ensure it's running
        sudo systemctl enable caddy 2>/dev/null || true
        sudo systemctl restart caddy 2>/dev/null || true
        return 0
    fi
    
    if [[ "$CADDY_LIVE_MODE" == "yes" ]]; then
        # Live server mode
        log_info "Configuring Caddy for live server: $CADDY_DOMAIN"
        
        sudo tee /etc/caddy/Caddyfile > /dev/null << EOF
$CADDY_DOMAIN {
    tls $CADDY_TLS_EMAIL

    encode zstd gzip

    handle /stream/* {
        uri strip_prefix /stream
        reverse_proxy 127.0.0.1:31827 {
            header_up Host localhost
        }
    }

    handle /terminal/* {
        uri strip_prefix /terminal
        reverse_proxy 127.0.0.1:31827 {
            header_up Host localhost
        }
    }

    handle /ws_stt/* {
        uri strip_prefix /ws_stt
        reverse_proxy 127.0.0.1:9011
    }

    @root {
        path /
    }
    rewrite @root /chat

    # Everything else goes to PHP app
    handle {
        reverse_proxy 127.0.0.1:8000
    }
}
EOF
    else
        # Local development mode
        log_info "Configuring Caddy for local development (port 80)"
        
        sudo tee /etc/caddy/Caddyfile > /dev/null << 'EOF'
{
    auto_https off
}

http://localhost, :80 {
    encode zstd gzip

    handle /stream/* {
        uri strip_prefix /stream
        reverse_proxy 127.0.0.1:31827 {
            header_up Host localhost
        }
    }

    handle /terminal/* {
        uri strip_prefix /terminal
        reverse_proxy 127.0.0.1:31827 {
            header_up Host localhost
        }
    }

    handle /ws_stt/* {
        uri strip_prefix /ws_stt
        reverse_proxy 127.0.0.1:9011
    }

    @root {
        path /
    }
    rewrite @root /chat

    reverse_proxy 127.0.0.1:8000
}
EOF
    fi
    
    # Reload Caddy
    sudo systemctl enable caddy
    sudo systemctl restart caddy
    
    log_success "Caddy configured and running"
}

# Configure ginto.service for systemd
configure_systemd_service() {
    log_step "Configuring Ginto systemd service..."
    
    # Skip if service already exists and we're in skip mode
    if [[ "${SKIP_CONFIGURED:-false}" == "true" ]] && [ -f "/etc/systemd/system/ginto.service" ]; then
        log_info "Ginto service already configured, skipping"
        return 0
    fi
    
    # Storage directory is outside project dir
    STORAGE_DIR="$(dirname "$PROJECT_DIR")/storage"
    mkdir -p "$STORAGE_DIR/logs"
    
    # Create the systemd service file
    sudo tee /etc/systemd/system/ginto.service > /dev/null << EOF
[Unit]
Description=Ginto AI PHP Application
After=network.target mariadb.service caddy.service
Wants=caddy.service

[Service]
Type=simple
User=$INSTALL_USER
Group=$INSTALL_USER
WorkingDirectory=$PROJECT_DIR
ExecStart=/usr/local/bin/composer start --services
ExecStop=/usr/bin/pkill -f "php.*ginto"
Restart=always
RestartSec=5
StandardOutput=append:$(dirname $PROJECT_DIR)/storage/logs/ginto.log
StandardError=append:$(dirname $PROJECT_DIR)/storage/logs/ginto-error.log
Environment=PATH=/usr/bin:/usr/local/bin:/home/$INSTALL_USER/.local/bin
Environment=HOME=$INSTALL_USER_HOME
Environment=COMPOSER_HOME=$INSTALL_USER_HOME/.composer

[Install]
WantedBy=multi-user.target
EOF
    
    # Reload systemd and enable service
    sudo systemctl daemon-reload
    sudo systemctl enable ginto.service
    
    log_success "Ginto service configured for user: $INSTALL_USER"
    log_info "Service will start automatically on boot"
}

# Fix critical file permissions (can be run anytime, idempotent)
# This ensures .env and project files are owned by INSTALL_USER
fix_permissions() {
    local quiet="${1:-false}"
    
    # Fix project directory ownership
    if [ "$(stat -c '%U' "$PROJECT_DIR" 2>/dev/null)" != "$INSTALL_USER" ]; then
        chown "$INSTALL_USER:$INSTALL_USER" "$PROJECT_DIR" 2>/dev/null || true
        chmod 755 "$PROJECT_DIR" 2>/dev/null || true
        [[ "$quiet" != "true" ]] && log_info "Fixed project directory ownership"
    fi
    
    # Fix .env ownership - CRITICAL for web installer
    if [ -f "$PROJECT_DIR/.env" ]; then
        if [ "$(stat -c '%U' "$PROJECT_DIR/.env" 2>/dev/null)" != "$INSTALL_USER" ]; then
            chown "$INSTALL_USER:$INSTALL_USER" "$PROJECT_DIR/.env"
            chmod 664 "$PROJECT_DIR/.env"
            [[ "$quiet" != "true" ]] && log_info "Fixed .env ownership to $INSTALL_USER"
        fi
    fi
    
    # Make bin scripts executable
    chmod +x "$PROJECT_DIR"/bin/*.sh 2>/dev/null || true
    chmod +x "$PROJECT_DIR"/run.sh 2>/dev/null || true
}

# Setup project directory permissions
setup_permissions() {
    log_step "Setting up directory permissions..."
    
    # Create storage directory if it doesn't exist (outside project dir)
    STORAGE_DIR="$(dirname "$PROJECT_DIR")/storage"
    mkdir -p "$STORAGE_DIR"/{sessions,logs,cache,backups,temp,uploads}
    
    # Run the idempotent permission fixer
    fix_permissions
    
    # Set permissions on storage directory
    chown -R "$INSTALL_USER:$INSTALL_USER" "$STORAGE_DIR"
    chmod -R g+w "$STORAGE_DIR"
    
    log_success "Permissions configured"
}

# Install Composer dependencies
install_dependencies() {
    log_step "Installing Composer dependencies..."
    
    cd "$PROJECT_DIR"
    
    if [ -f composer.json ]; then
        # Run composer as the install user, not root, so plugins can run
        sudo -u "$INSTALL_USER" composer install --no-interaction --prefer-dist
        log_success "Composer dependencies installed"
    else
        log_warn "composer.json not found, skipping"
    fi
}

# Create .env file if not exists
setup_env() {
    log_step "Setting up environment..."
    
    local env_file="$PROJECT_DIR/.env"
    
    # Skip if existing installation detected
    if [[ "${SKIP_CONFIGURED:-false}" == "true" ]] && [ -f "$env_file" ]; then
        log_info ".env already configured, skipping"
        return 0
    fi
    
    if [ ! -f "$env_file" ]; then
        if [ -f "$PROJECT_DIR/.env.example" ]; then
            cp "$PROJECT_DIR/.env.example" "$env_file"
            log_info "Created .env from .env.example"
        else
            # Create minimal .env with database config
            touch "$env_file"
            log_info "Created new .env file"
        fi
    else
        log_info ".env already exists"
    fi
    
    # Update database configuration in .env
    log_info "Writing database configuration to .env..."
    
    # Function to update or add key=value in .env
    update_env_var() {
        local key="$1"
        local value="$2"
        if grep -q "^${key}=" "$env_file" 2>/dev/null; then
            # Update existing key
            sed -i "s|^${key}=.*|${key}=${value}|" "$env_file"
        else
            # Add new key
            echo "${key}=${value}" >> "$env_file"
        fi
    }
    
    # Set database variables
    update_env_var "DB_TYPE" "mysql"
    update_env_var "DB_HOST" "localhost"
    update_env_var "DB_PORT" "3306"
    update_env_var "DB_NAME" "$DB_NAME"
    update_env_var "DB_USER" "$DB_USER"
    update_env_var "DB_PASS" "$DB_PASS"
    
    # Set APP_URL based on Caddy mode
    if [[ "$CADDY_LIVE_MODE" == "yes" ]]; then
        update_env_var "APP_URL" "https://$CADDY_DOMAIN"
    else
        update_env_var "APP_URL" "http://localhost"
    fi
    
    # CRITICAL: Set proper ownership on .env so PHP/web installer can modify it
    # The file was created/modified as root, but ginto.service runs PHP as INSTALL_USER
    chown "$INSTALL_USER:$INSTALL_USER" "$env_file"
    chmod 664 "$env_file"
    log_info "Set .env ownership to $INSTALL_USER:$INSTALL_USER with mode 664"
    
    log_success "Database configuration written to .env"
}

# Start services
start_services() {
    log_step "Starting services..."
    
    # Start Ginto service
    sudo systemctl start ginto.service
    
    # Wait for services to be ready
    sleep 3
    
    # Check if running
    if sudo systemctl is-active --quiet ginto.service; then
        log_success "Ginto service is running"
    else
        log_warn "Ginto service may not have started. Check: sudo journalctl -u ginto.service"
    fi
    
    if sudo systemctl is-active --quiet caddy.service; then
        log_success "Caddy service is running"
    else
        log_warn "Caddy service may not have started. Check: sudo journalctl -u caddy.service"
    fi
}

# Print summary
print_summary() {
    echo ""
    echo -e "${GREEN}============================================${NC}"
    echo -e "${GREEN}  Ginto AI Installation Complete!${NC}"
    echo -e "${GREEN}============================================${NC}"
    echo ""
    echo "Installed components:"
    echo "  - PHP $(php -v 2>/dev/null | head -1 | awk '{print $2}' || echo 'N/A')"
    echo "  - Composer $(composer --version --no-interaction 2>/dev/null | awk '{print $3}' || echo 'N/A')"
    echo "  - MariaDB $(mariadb --version 2>/dev/null | awk '{print $5}' | tr -d ',' || echo 'N/A')"
    echo "  - Caddy $(caddy version 2>/dev/null | awk '{print $1}' || echo 'N/A')"
    echo "  - Git $(git --version 2>/dev/null | awk '{print $3}' || echo 'N/A')"
    echo "  - Node.js $(node --version 2>/dev/null || echo 'N/A')"
    echo "  - llama.cpp $([ -f "$INSTALL_USER_HOME/llama.cpp/build/bin/llama-server" ] && echo 'Installed' || echo 'N/A')"
    echo ""
    echo "Configuration:"
    echo "  - User: $INSTALL_USER"
    echo "  - Project: $PROJECT_DIR"
    echo "  - Storage: $(dirname "$PROJECT_DIR")/storage"
    echo ""
    echo "Database:"
    echo "  - Database: $DB_NAME"
    echo "  - User: $DB_USER"
    echo "  - Host: localhost:3306"
    echo ""
    echo "Services:"
    echo "  - ginto.service: $(sudo systemctl is-active ginto.service 2>/dev/null || echo 'unknown')"
    echo "  - caddy.service: $(sudo systemctl is-active caddy.service 2>/dev/null || echo 'unknown')"
    echo ""
    echo "Access your site:"
    echo "  - Local: http://localhost"
    echo "  - Direct: http://localhost:8000"
    echo ""
    echo "Useful commands:"
    echo "  - Start:   sudo systemctl start ginto"
    echo "  - Stop:    sudo systemctl stop ginto"
    echo "  - Status:  sudo systemctl status ginto"
    echo "  - Logs:    tail -f $(dirname $PROJECT_DIR)/storage/logs/ginto.log"
    echo ""
}

# Check if core components are already installed (skip prompts if so)
# Quick permission fix - always runs to ensure correct ownership
fix_permissions() {
    log_step "Checking file permissions..."
    
    # Fix project directory ownership
    chown "$INSTALL_USER:$INSTALL_USER" "$PROJECT_DIR" 2>/dev/null || true
    
    # Fix .env file ownership (critical for web installer)
    if [ -f "$PROJECT_DIR/.env" ]; then
        local current_owner=$(stat -c '%U' "$PROJECT_DIR/.env" 2>/dev/null)
        if [ "$current_owner" != "$INSTALL_USER" ]; then
            chown "$INSTALL_USER:$INSTALL_USER" "$PROJECT_DIR/.env"
            chmod 664 "$PROJECT_DIR/.env"
            log_info "Fixed .env ownership: was $current_owner, now $INSTALL_USER"
        else
            log_success ".env ownership is correct ($INSTALL_USER)"
        fi
    fi
    
    # Ensure storage directory exists and has correct permissions
    local STORAGE_DIR="$(dirname "$PROJECT_DIR")/storage"
    if [ -d "$STORAGE_DIR" ]; then
        chown -R "$INSTALL_USER:$INSTALL_USER" "$STORAGE_DIR" 2>/dev/null || true
    fi
    
    # Make bin scripts executable
    chmod +x "$PROJECT_DIR"/bin/*.sh 2>/dev/null || true
    chmod +x "$PROJECT_DIR"/run.sh 2>/dev/null || true
}

detect_existing_installation() {
    local has_php=false
    local has_mariadb=false
    local has_caddy=false
    local has_composer=false
    local has_env=false
    
    command -v php &>/dev/null && [[ $(php -v 2>/dev/null | head -1) =~ ^PHP\ 8 ]] && has_php=true
    command -v mariadb &>/dev/null && has_mariadb=true
    command -v caddy &>/dev/null && has_caddy=true
    command -v composer &>/dev/null && has_composer=true
    [ -f "$PROJECT_DIR/.env" ] && has_env=true
    
    if $has_php && $has_mariadb && $has_caddy && $has_composer && $has_env; then
        return 0  # Core is installed
    fi
    return 1  # Need full installation
}

# Ask all interactive questions upfront (Ollama-style)
prompt_configuration() {
    # First check if core installation already exists
    if detect_existing_installation; then
        log_step "Existing installation detected"
        log_success "Core components already installed:"
        echo "  - PHP: $(php -v 2>/dev/null | head -1 | awk '{print $2}')"
        echo "  - MariaDB: $(mariadb --version 2>/dev/null | awk '{print $5}' | tr -d ',')"
        echo "  - Caddy: $(caddy version 2>/dev/null | awk '{print $1}')"
        echo "  - Composer: $(composer --version --no-interaction 2>/dev/null | awk '{print $3}')"
        echo "  - .env: configured"
        echo ""
        
        # ALWAYS fix permissions on re-run (idempotent)
        log_info "Checking file permissions..."
        fix_permissions
        log_success "Permissions verified"
        
        log_info "Skipping configuration prompts - will only install missing components"
        
        # Read existing database credentials from .env
        if [ -f "$PROJECT_DIR/.env" ]; then
            DB_NAME=$(grep -E '^DB_NAME=' "$PROJECT_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'" || echo "ginto")
            DB_USER=$(grep -E '^DB_USER=' "$PROJECT_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'" || echo "ginto")
            DB_PASS=$(grep -E '^DB_PASS=' "$PROJECT_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")
            [ -z "$DB_NAME" ] && DB_NAME="ginto"
            [ -z "$DB_USER" ] && DB_USER="ginto"
        else
            DB_NAME="ginto"
            DB_USER="ginto"
            DB_PASS=""
        fi
        CADDY_LIVE_MODE="skip"
        CADDY_DOMAIN=""
        CADDY_TLS_EMAIL=""
        SKIP_CONFIGURED=true
        SKIP_DB_USER_SETUP=true  # Don't touch existing database user
        
        # Skip to final steps - only run dependencies, env check, and summary
        # This prevents re-running installation steps on existing systems
        save_checkpoint "install_dependencies"
        return 0
    fi
    
    # Check if we're resuming and config exists
    if load_config 2>/dev/null; then
        log_info "Loaded saved configuration from previous run"
        log_info "  Mode: ${CADDY_LIVE_MODE:-local}"
        if [[ "${CADDY_LIVE_MODE:-no}" == "yes" ]]; then
            log_info "  Domain: $CADDY_DOMAIN"
        fi
        log_info "  Database: ${DB_NAME:-ginto} (user: ${DB_USER:-ginto})"
        return 0
    fi
    
    SKIP_CONFIGURED=false
    echo ""
    log_step "Configuration"
    echo ""
    
    # Caddy mode
    log_prompt "Do you want to install this as a LIVE server with a domain? (yes/no)"
    log_info "Default: no (local development mode on port 80)"
    read -r -p "> " CADDY_LIVE_MODE < /dev/tty
    CADDY_LIVE_MODE="${CADDY_LIVE_MODE:-no}"
    
    if [[ "$CADDY_LIVE_MODE" =~ ^[Yy][Ee]?[Ss]?$ ]]; then
        CADDY_LIVE_MODE="yes"
        echo ""
        log_prompt "Enter your domain name (e.g., example.com):"
        read -r -p "> " CADDY_DOMAIN < /dev/tty
        
        if [ -z "$CADDY_DOMAIN" ]; then
            log_error "Domain name is required for live server mode"
            exit 1
        fi
        
        log_prompt "Enter your email for TLS certificate (e.g., admin@$CADDY_DOMAIN):"
        read -r -p "> " CADDY_TLS_EMAIL < /dev/tty
        CADDY_TLS_EMAIL="${CADDY_TLS_EMAIL:-admin@$CADDY_DOMAIN}"
    else
        CADDY_LIVE_MODE="no"
        CADDY_DOMAIN=""
        CADDY_TLS_EMAIL=""
    fi
    
    # Database configuration
    echo ""
    log_step "Database Configuration"
    echo ""
    log_info "The installer will create a MariaDB database and user for Ginto."
    log_info "Do NOT use 'root' - create a dedicated user for security."
    echo ""
    
    log_prompt "Enter database name (default: ginto):"
    read -r -p "> " DB_NAME < /dev/tty
    DB_NAME="${DB_NAME:-ginto}"
    
    log_prompt "Enter database username (default: ginto):"
    read -r -p "> " DB_USER < /dev/tty
    DB_USER="${DB_USER:-ginto}"
    
    # Validate username is not root
    if [[ "$DB_USER" == "root" ]]; then
        log_warn "Using 'root' is not recommended for application access."
        log_prompt "Are you sure you want to use root? (yes/no)"
        read -r -p "> " confirm_root < /dev/tty
        if [[ ! "$confirm_root" =~ ^[Yy][Ee]?[Ss]?$ ]]; then
            log_prompt "Enter database username:"
            read -r -p "> " DB_USER < /dev/tty
            DB_USER="${DB_USER:-ginto}"
        fi
    fi
    
    # Password prompt with confirmation
    while true; do
        log_prompt "Enter database password for '$DB_USER':"
        read -r -s -p "> " DB_PASS < /dev/tty
        echo ""
        
        if [ -z "$DB_PASS" ]; then
            log_error "Password cannot be empty. Please enter a password."
            continue
        fi
        
        log_prompt "Confirm database password:"
        read -r -s -p "> " DB_PASS_CONFIRM < /dev/tty
        echo ""
        
        if [ "$DB_PASS" != "$DB_PASS_CONFIRM" ]; then
            log_error "Passwords do not match. Please try again."
        else
            break
        fi
    done
    
    log_success "Database configuration: $DB_NAME with user '$DB_USER'"
    
    # Save config for potential resume
    save_config
    
    echo ""
    log_info "Configuration saved. Starting unattended installation..."
    echo ""
}

# Main installation function
do_install() {
    echo ""
    echo -e "${CYAN}╔════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║     Ginto AI - Installation Script     ║${NC}"
    echo -e "${CYAN}╚════════════════════════════════════════╝${NC}"
    echo ""
    
    log_info "Running as user: $INSTALL_USER"
    
    # Check for resume
    local last_checkpoint=$(get_last_checkpoint)
    if [ -n "$last_checkpoint" ]; then
        echo ""
        log_warn "Previous installation was interrupted after: $last_checkpoint"
        log_info "Resuming from where we left off..."
        
        # Load saved config when resuming
        if load_config 2>/dev/null; then
            log_info "Loaded saved configuration"
            log_info "  Mode: ${CADDY_LIVE_MODE:-local}"
            if [[ "${CADDY_LIVE_MODE:-no}" == "yes" ]]; then
                log_info "  Domain: $CADDY_DOMAIN"
            fi
            log_info "  Database: ${DB_NAME:-ginto} (user: ${DB_USER:-ginto})"
        fi
        echo ""
    fi
    
    # Run steps with checkpoint tracking
    local run_step
    # Steps that should always run (idempotent, critical for correct state)
    local ALWAYS_RUN_STEPS=("setup_permissions")
    
    for step in "${INSTALL_STEPS[@]}"; do
        # Check if step should always run
        local always_run=false
        for always_step in "${ALWAYS_RUN_STEPS[@]}"; do
            if [[ "$step" == "$always_step" ]]; then
                always_run=true
                break
            fi
        done
        
        if $always_run || should_run_step "$step" "$last_checkpoint"; then
            # Execute the step function
            $step
            # Save checkpoint after successful completion
            save_checkpoint "$step"
        else
            log_info "Skipping (already done): $step"
        fi
    done
    
    # Clear checkpoint on successful completion
    clear_checkpoint
    
    log_success "Installation completed successfully!"
}

# Command dispatcher
case "${1:-help}" in
    install)
        do_install
        ;;
    reset)
        # Clear checkpoints to start fresh
        clear_checkpoint
        log_success "Installation checkpoint cleared. Run 'install' to start fresh."
        ;;
    status)
        echo "Ginto Service Status:"
        sudo systemctl status ginto.service --no-pager || true
        echo ""
        echo "Caddy Service Status:"
        sudo systemctl status caddy.service --no-pager || true
        # Show checkpoint status
        if [ -f "$CHECKPOINT_FILE" ]; then
            echo ""
            echo "Installation Status:"
            echo "  Last completed step: $(cat "$CHECKPOINT_FILE")"
            echo "  Run 'install' to resume or 'reset' to start fresh"
        fi
        ;;
    start)
        sudo systemctl start ginto.service
        sudo systemctl start caddy.service
        echo "Services started"
        ;;
    stop)
        sudo systemctl stop ginto.service
        echo "Ginto service stopped"
        ;;
    restart)
        sudo systemctl restart ginto.service
        sudo systemctl restart caddy.service
        echo "Services restarted"
        ;;
    *)
        echo "Ginto AI Installation Script"
        echo ""
        echo "Usage: $0 <command>"
        echo ""
        echo "Commands:"
        echo "  install   - Install all dependencies and configure services"
        echo "              (auto-resumes if interrupted)"
        echo "  reset     - Clear installation checkpoint to start fresh"
        echo "  start     - Start Ginto and Caddy services"
        echo "  stop      - Stop Ginto service"
        echo "  restart   - Restart all services"
        echo "  status    - Show service status"
        echo ""
        echo "This will install:"
        echo "  - PHP 8.3 with required extensions"
        echo "  - Composer (PHP package manager)"
        echo "  - MariaDB (database server)"
        echo "  - Caddy (web server/reverse proxy)"
        echo "  - Git, curl, unzip, ffmpeg"
        echo "  - Node.js LTS (auto-detected latest)"
        echo "  - llama.cpp (local LLM inference server)"
        echo "  - Build tools (gcc, cmake, etc.)"
        echo ""
        echo "NOTE: Ginto must be installed in your home directory!"
        echo ""
        ;;
esac
