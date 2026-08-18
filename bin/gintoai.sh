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

# Installation mode: "native" (default) or "docker"
INSTALL_MODE="${GINTO_INSTALL_MODE:-native}"

# PowerDNS control flags (set via command line or env vars)
SKIP_POWERDNS="${GINTO_SKIP_POWERDNS:-false}"
FORCE_POWERDNS="${GINTO_FORCE_POWERDNS:-false}"

# Legacy install mode - enables /install v1 wizard instead of /chat → /live flow
LEGACY_INSTALL="${GINTO_LEGACY_INSTALL:-false}"

# Steps to skip entirely (space-separated tokens). Populated from --skip flags
# and/or the GINTO_SKIP_STEPS env var. A token is matched as a substring of the
# step name, so "sdcpu" skips both install_sdcpu and configure_sdcpu_service.
SKIP_STEPS="${GINTO_SKIP_STEPS:-}"

# Parse additional flags from command line
_expect_skip=false
for arg in "$@"; do
    if [[ "$_expect_skip" == "true" ]]; then
        SKIP_STEPS="$SKIP_STEPS $arg"
        _expect_skip=false
        continue
    fi
    case "$arg" in
        --skip-powerdns)
            SKIP_POWERDNS=true
            ;;
        --force-powerdns)
            FORCE_POWERDNS=true
            ;;
        --legacy)
            LEGACY_INSTALL=true
            ;;
        --skip=*)
            # --skip=sdcpu  or  --skip=sdcpu,powerdns
            SKIP_STEPS="$SKIP_STEPS ${arg#--skip=}"
            ;;
        --skip)
            # --skip sdcpu   (value is the next argument)
            _expect_skip=true
            ;;
    esac
done
# Normalize any comma-separated tokens into space-separated
SKIP_STEPS="${SKIP_STEPS//,/ }"

# Returns 0 (true) if a step should be skipped per --skip / GINTO_SKIP_STEPS.
# Match is a substring of the step name, so "sdcpu" skips install_sdcpu and
# configure_sdcpu_service; a full step name like "install_powerdns" also works.
is_step_skipped() {
    local step="$1" token
    for token in $SKIP_STEPS; do
        [ -z "$token" ] && continue
        if [[ "$step" == "$token" || "$step" == *"$token"* ]]; then
            return 0
        fi
    done
    return 1
}

# List of installation steps for NATIVE mode
INSTALL_STEPS_NATIVE=(
    "check_home_directory"
    "prompt_configuration"
    "check_sudo"
    "detect_os"
    "update_packages"
    "install_git"
    "install_utilities"
    "install_redis"
    "install_coturn"
    "install_build_tools"
    "install_php"
    "install_mariadb"
    "install_caddy"
    "configure_caddy"
    "install_composer"
    "install_nodejs"
    "install_llamacpp"
    "install_powerdns"
    "install_sdcpu"
    "configure_systemd_service"
    "configure_sdcpu_service"
    "setup_permissions"
    "configure_firewall"
    "install_dependencies"
    "setup_env"
    "import_geonames"
    "start_services"
    "print_summary"
)

# List of installation steps for DOCKER mode
# Main stack (PHP, MariaDB, Caddy, Redis) installed on host
# Docker is ONLY used for user sandboxes
INSTALL_STEPS_DOCKER=(
    "check_home_directory"
    "prompt_docker_configuration"
    "check_sudo"
    "detect_os"
    "update_packages"
    "install_git"
    "install_utilities"
    "install_redis"
    "install_coturn"
    "install_build_tools"
    "install_php"
    "install_mariadb"
    "install_caddy"
    "configure_caddy"
    "install_composer"
    "install_nodejs"
    "install_llamacpp"
    "install_powerdns"
    "install_docker_engine"
    "install_docker_compose"
    "install_sdcpu"
    "configure_systemd_service"
    "configure_sdcpu_service"
    "setup_permissions"
    "configure_firewall"
    "install_dependencies"
    "setup_env"
    "build_sandbox_images"
    "start_services"
    "start_sandbox_services"
    "print_docker_summary"
)

# Default to native steps (will be updated based on mode)
INSTALL_STEPS=("${INSTALL_STEPS_NATIVE[@]}")

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
INSTALL_MODE="${INSTALL_MODE:-native}"
CADDY_LIVE_MODE="${CADDY_LIVE_MODE:-false}"
CADDY_DOMAIN="${CADDY_DOMAIN:-}"
CADDY_TLS_EMAIL="${CADDY_TLS_EMAIL:-}"
TLS_EMAIL="${TLS_EMAIL:-}"
OS="${OS:-}"
OS_VERSION="${OS_VERSION:-}"
DB_NAME="${DB_NAME:-ginto}"
DB_USER="${DB_USER:-ginto}"
DB_PASS="${DB_PASS:-}"
DB_ROOT_PASS="${DB_ROOT_PASS:-}"
SANDBOX_MODE="${SANDBOX_MODE:-lxd}"
LLAMACPP_MODE="${LLAMACPP_MODE:-compile}"
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

# Get server's primary IP address
get_server_ip() {
    # Try multiple methods to get the IP
    local ip=""
    
    # Method 1: ip route (most reliable on Linux)
    ip=$(ip route get 1 2>/dev/null | awk '{print $7; exit}')
    
    # Method 2: hostname -I (fallback)
    if [ -z "$ip" ]; then
        ip=$(hostname -I 2>/dev/null | awk '{print $1}')
    fi
    
    # Method 3: ifconfig (older systems)
    if [ -z "$ip" ]; then
        ip=$(ifconfig 2>/dev/null | grep -Eo 'inet (addr:)?([0-9]*\.){3}[0-9]*' | grep -Eo '([0-9]*\.){3}[0-9]*' | grep -v '127.0.0.1' | head -1)
    fi
    
    echo "${ip:-unknown}"
}

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
# Required PHP extensions as "module_name:debian_package"
# (module_name is what `php -m` reports; package is the apt suffix on Debian/Ubuntu).
# This is the single source of truth used by both the fresh-install and
# already-installed paths so a required extension can never be silently skipped.
PHP_REQUIRED_EXTS=(
    "mysqli:mysql"
    "pdo_mysql:mysql"
    "redis:redis"
    "curl:curl"
    "mbstring:mbstring"
    "dom:xml"
    "zip:zip"
    "gd:gd"
    "bcmath:bcmath"
    "intl:intl"
    "sqlite3:sqlite3"
    "Zend OPcache:opcache"   # php -m reports opcache as "Zend OPcache", not "opcache"
)

# Ensure every required PHP extension is actually loaded. Installs any that are
# missing (Debian/Ubuntu apt naming), restarts php-fpm if anything changed, and
# warns loudly if a required extension is still absent afterwards.
ensure_php_extensions() {
    local php_version php_major_minor
    php_version=$(php -v 2>/dev/null | head -1 | awk '{print $2}')
    php_major_minor=$(echo "$php_version" | cut -d. -f1,2)

    # Repair any half-configured dpkg state before touching packages
    sudo dpkg --configure -a 2>/dev/null || true
    sudo apt-get install -f -y 2>/dev/null || true

    local needs_restart=false ext_spec ext_module ext_package
    local repo_ready=false

    for ext_spec in "${PHP_REQUIRED_EXTS[@]}"; do
        ext_module="${ext_spec%%:*}"
        ext_package="${ext_spec##*:}"

        if php -m | grep -qi "^${ext_module}$"; then
            continue
        fi

        case "$OS" in
            ubuntu|debian)
                if ! $repo_ready; then
                    if ! apt-cache show "php${php_major_minor}-opcache" &>/dev/null; then
                        log_info "Adding ppa:ondrej/php to resolve PHP ${php_major_minor} extensions..."
                        sudo apt-get install -y software-properties-common
                        sudo add-apt-repository -y ppa:ondrej/php 2>/dev/null || true
                        sudo apt-get update -qq
                    fi
                    repo_ready=true
                fi

                local pkg="php${php_major_minor}-${ext_package}"
                if dpkg -s "$pkg" &>/dev/null; then
                    # apt thinks it's installed but php -m disagrees — force reconfigure/reinstall
                    log_warn "$pkg reports installed but ${ext_module} not loaded — reinstalling..."
                    sudo apt-get install --reinstall -y "$pkg" || true
                else
                    log_info "Installing missing PHP extension: ${ext_module} (${pkg})..."
                    sudo apt-get install -y "$pkg" || log_warn "apt-get install failed for $pkg"
                fi

                # Explicitly enable across every SAPI in case the dpkg trigger didn't run
                sudo phpenmod -v "$php_major_minor" "$ext_module" 2>/dev/null || true
                needs_restart=true
                ;;
        esac
    done

    if [[ "$needs_restart" == "true" ]]; then
        sudo systemctl restart "php${php_major_minor}-fpm" 2>/dev/null || true
    fi

    # Final hard check, with real diagnostics this time instead of a blind warning
    local missing="" ext_module2
    for ext_spec in "${PHP_REQUIRED_EXTS[@]}"; do
        ext_module2="${ext_spec%%:*}"
        php -m | grep -qi "^${ext_module2}$" || missing+=" ${ext_module2}"
    done
    if [ -n "$missing" ]; then
        log_warn "PHP is still missing extension(s):${missing}"
        log_warn "Diagnostics:"
        log_warn "  php binary: $(command -v php) -> $(readlink -f "$(command -v php)" 2>/dev/null)"
        log_warn "  loaded ini: $(php --ini 2>/dev/null | grep 'Loaded Configuration')"
        log_warn "  conf.d dir: /etc/php/${php_major_minor}/cli/conf.d/"
        ls "/etc/php/${php_major_minor}/cli/conf.d/" 2>/dev/null | sed 's/^/    /' || true
        return 1
    fi
    return 0
}

install_php() {
    log_step "Installing PHP 8.x and extensions..."
    
    # Check if PHP 8.x is already installed
    if command -v php &>/dev/null; then
        local php_version=$(php -v | head -1 | awk '{print $2}')
        if [[ "$php_version" =~ ^8\. ]]; then
            log_info "PHP already installed: $php_version"
            # Guarantee all required extensions exist even if PHP was installed
            # by something other than this script (this is exactly the case that
            # left mysqli missing and broke composer). A non-zero return here must
            # NOT abort the installer under `set -e` — later steps (Caddy, MariaDB,
            # Composer) still need to run so the real failure (if any) surfaces at
            # the composer install step instead of silently killing everything.
            ensure_php_extensions || log_warn "Some PHP extensions still missing — continuing install; composer step will confirm if this is fatal"
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
            
            # Install PHP and extensions with detected version.
            # No `|| true` swallow here on purpose: if this apt-get genuinely fails
            # (bad package names, network issue), we want to know immediately rather
            # than limping forward with a half-installed PHP and blaming extensions later.
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

    # Verify (and backfill) required extensions after a fresh install too, so a
    # package that failed to install for any reason is caught before composer runs.
    # Same non-fatal guard as above — don't let this kill the rest of the installer.
    ensure_php_extensions || log_warn "Some PHP extensions still missing after fresh install — continuing; composer step will confirm if this is fatal"

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
    log_step "Installing utilities (curl, unzip, ffmpeg, websockify, websocat, build tools, OCR tools)..."
    
    # Ensure install user is in lxd group for PHP to access LXD API socket
    # PHP runs as the install user via composer start, not www-data
    if getent group lxd &>/dev/null; then
        sudo usermod -aG lxd "$INSTALL_USER" 2>/dev/null || true
        log_info "Added $INSTALL_USER to lxd group for LXD API access"
    fi
    
    # Check if all utilities are already installed
    local missing_utils=()
    command -v curl &>/dev/null || missing_utils+=("curl")
    command -v unzip &>/dev/null || missing_utils+=("unzip")
    command -v ffmpeg &>/dev/null || missing_utils+=("ffmpeg")
    command -v lsof &>/dev/null || missing_utils+=("lsof")
    command -v websockify &>/dev/null || missing_utils+=("websockify")
    command -v websocat &>/dev/null || missing_utils+=("websocat")
    command -v ifconfig &>/dev/null || missing_utils+=("net-tools")
    command -v ping &>/dev/null || missing_utils+=("iputils-ping")
    command -v cmake &>/dev/null || missing_utils+=("cmake")
    command -v g++ &>/dev/null || missing_utils+=("build-essential")
    command -v git &>/dev/null || missing_utils+=("git")
    # docker compose plugin — checked separately from `docker` itself since native
    # mode never installs the Docker engine; we only need the CLI + compose plugin
    # here so `docker compose` subcommands used elsewhere (sdcpu, tunnel, etc.) work.
    docker compose version &>/dev/null 2>&1 || missing_utils+=("docker-compose-plugin")
    # OCR/PDF tools for Vision-Centric RAG
    command -v pdftotext &>/dev/null || missing_utils+=("poppler-utils")
    command -v tesseract &>/dev/null || missing_utils+=("tesseract-ocr")
    command -v gs &>/dev/null || missing_utils+=("ghostscript")
    command -v convert &>/dev/null || missing_utils+=("imagemagick")
    command -v antiword &>/dev/null || missing_utils+=("antiword")
    command -v catdoc &>/dev/null || missing_utils+=("catdoc")

    # Ensure wkhtmltopdf is present; treat as a missing utility if absent so update flows install it
    if ! command -v wkhtmltopdf &>/dev/null; then
        missing_utils+=("wkhtmltopdf")
    fi
    
    if [ ${#missing_utils[@]} -eq 0 ]; then
        log_info "All utilities already installed (curl, unzip, ffmpeg, lsof, websockify, websocat, net-tools, cmake, g++, git, docker compose, OCR tools)"
        # Even if packages are already present, group membership can still be
        # missing (e.g. docker group created after this user last logged in,
        # or user added by other means). Check every run, not just on install.
        ensure_docker_group_membership
        return 0
    fi
    
    log_info "Installing missing utilities: ${missing_utils[*]}"

    # docker-compose-plugin (and docker-ce-cli it depends on for the `docker`
    # command it hooks into) live in Docker's own apt/dnf repo, not the distro's
    # default repos. Neither is ever added in native mode (that only happens
    # inside install_docker_engine, which is Docker-mode-only). Without adding
    # this repo first, the apt-get/dnf install below fails to resolve
    # docker-compose-plugin — and under `set -euo pipefail` that failure kills
    # the ENTIRE installer here, before Redis/Caddy/PHP/MariaDB ever run.
    ensure_docker_repo() {
        case $OS in
            ubuntu|debian)
                if [ ! -f /etc/apt/sources.list.d/docker.list ]; then
                    log_info "Adding Docker's apt repository (required for docker-compose-plugin)..."
                    sudo apt-get install -y ca-certificates curl gnupg
                    sudo install -m 0755 -d /etc/apt/keyrings
                    if [ ! -f /etc/apt/keyrings/docker.gpg ]; then
                        curl -fsSL "https://download.docker.com/linux/${OS}/gpg" | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
                        sudo chmod a+r /etc/apt/keyrings/docker.gpg
                    fi
                    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/${OS} $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
                        sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
                    sudo apt-get update -qq
                fi
                ;;
            fedora)
                if ! sudo dnf repolist 2>/dev/null | grep -qi docker-ce; then
                    log_info "Adding Docker's dnf repository (required for docker-compose-plugin)..."
                    sudo dnf install -y dnf-plugins-core
                    sudo dnf config-manager --add-repo https://download.docker.com/linux/fedora/docker-ce.repo
                fi
                ;;
        esac
    }
    ensure_docker_repo
    
    case $OS in
        ubuntu|debian)
            # Ensure universe repo is enabled for ffmpeg
            sudo add-apt-repository -y universe 2>/dev/null || true
            sudo apt-get update -qq
            sudo apt-get install -y curl unzip ffmpeg lsof iputils-ping net-tools \
                docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin \
                python3-websockify \
                python3-websocket cmake build-essential git pkg-config libcurl4-openssl-dev \
                poppler-utils tesseract-ocr tesseract-ocr-eng tesseract-ocr-fil tesseract-ocr-chi-sim \
                ghostscript imagemagick antiword catdoc wkhtmltopdf \
                pandoc fonts-noto fonts-dejavu-core \
                postfix opendkim opendkim-tools
            
            # Install websocat (not in apt, download binary)
            if ! command -v websocat &>/dev/null; then
                log_info "Installing websocat..."
                local WEBSOCAT_VERSION="1.13.0"
                local WEBSOCAT_URL="https://github.com/vi/websocat/releases/download/v${WEBSOCAT_VERSION}/websocat.x86_64-unknown-linux-musl"
                sudo curl -fsSL "$WEBSOCAT_URL" -o /usr/local/bin/websocat
                sudo chmod +x /usr/local/bin/websocat
            fi
            ;;
        fedora)
            sudo dnf install -y curl unzip ffmpeg lsof iputils net-tools \
                docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin \
                python3-websockify \
                python3-websocket cmake gcc-c++ git pkgconfig libcurl-devel \
                poppler-utils tesseract tesseract-langpack-eng tesseract-langpack-fil \
                ghostscript ImageMagick antiword catdoc \
                pandoc noto-fonts dejavu-sans-fonts
            
            # Install websocat
            if ! command -v websocat &>/dev/null; then
                log_info "Installing websocat..."
                local WEBSOCAT_VERSION="1.13.0"
                local WEBSOCAT_URL="https://github.com/vi/websocat/releases/download/v${WEBSOCAT_VERSION}/websocat.x86_64-unknown-linux-musl"
                sudo curl -fsSL "$WEBSOCAT_URL" -o /usr/local/bin/websocat
                sudo chmod +x /usr/local/bin/websocat
            fi
            ;;
    esac
    
    # Refresh bash command cache so newly installed commands are found
    hash -r

    # Ensure wkhtmltopdf CLI is available; try distro package installs
    if ! command -v wkhtmltopdf &>/dev/null; then
        log_info "Installing wkhtmltopdf and X virtual framebuffer (xvfb)..."
        if command -v apt-get &>/dev/null; then
            sudo apt-get update && sudo apt-get install -y wkhtmltopdf xvfb pandoc || true
        elif command -v dnf &>/dev/null; then
            sudo dnf install -y wkhtmltopdf xorg-x11-server-Xvfb pandoc || true
        elif command -v apk &>/dev/null; then
            sudo apk add --no-cache wkhtmltopdf xvfb-run pandoc || true
        else
            log_warn "Could not detect package manager to install wkhtmltopdf; please install it manually"
        fi
        hash -r || true
    fi

    # Fallback: if wkhtmltopdf still missing on Debian/Ubuntu, try installing prebuilt .deb
    if ! command -v wkhtmltopdf &>/dev/null; then
        if command -v apt-get &>/dev/null; then
            log_warn "wkhtmltopdf not found after package install — attempting .deb fallback"
            tmpdeb="/tmp/wkhtmltox.deb"
            # Try a known release (0.12.6) — best-effort fallback
            wget -q -O "$tmpdeb" "https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6-1/wkhtmltox_0.12.6-1.bionic_amd64.deb" || true
            if [ -f "$tmpdeb" ]; then
                sudo dpkg -i "$tmpdeb" || sudo apt-get -f install -y || true
                rm -f "$tmpdeb"
            else
                log_warn "Could not download wkhtmltopdf .deb fallback — please install wkhtmltopdf manually"
            fi
            hash -r || true
        fi
    fi

    # Final check: confirm docker compose actually works now, not just that
    # the package installed (mirrors the ensure_php_extensions pattern).
    if ! docker compose version &>/dev/null 2>&1; then
        log_warn "docker compose still not working after install — 'docker' commands elsewhere may fail"
        log_warn "Check: docker --version ; docker compose version"
    fi

    # Add INSTALL_USER to the docker group so they can run `docker`/`docker compose`
    # without sudo. The docker-ce-cli package alone does not create this group or
    # add anyone to it — that normally only happens via install_docker_engine
    # (Docker-mode-only), so native mode needs it done explicitly here too.
    ensure_docker_group_membership
    
    log_success "Utilities installed"
}

# Ensure INSTALL_USER is a member of the docker group, creating the group first
# if the docker-ce-cli package didn't already create it (some minimal installs
# skip group creation since no daemon is present to need it).
ensure_docker_group_membership() {
    if ! getent group docker &>/dev/null; then
        log_info "Creating docker group..."
        sudo groupadd docker
    fi

    if groups "$INSTALL_USER" 2>/dev/null | grep -qw docker; then
        log_info "$INSTALL_USER already in docker group"
        return 0
    fi

    sudo usermod -aG docker "$INSTALL_USER"
    log_success "Added $INSTALL_USER to docker group"
    log_warn "Group membership takes effect on next login/shell — run 'newgrp docker' or log out and back in to use docker without sudo in this session"
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
            log_warn "Redis installed but service may need manual start"
            return 1
        fi
    fi
}

# Install and configure coturn TURN server for WebRTC NAT traversal
# Required for audio/video calls to work through mobile networks and symmetric NAT
install_coturn() {
    log_step "Installing coturn TURN server..."
    
    local coturn_installed=false
    
    # Check if coturn is already installed and running
    if command -v turnserver &>/dev/null; then
        if systemctl is-active --quiet coturn 2>/dev/null; then
            log_info "coturn already installed and running"
            coturn_installed=true
        fi
    fi
    
    if ! $coturn_installed; then
        log_info "Installing coturn..."
        
        case $OS in
            ubuntu|debian)
                sudo apt-get install -y coturn
                ;;
            fedora)
                sudo dnf install -y coturn
                ;;
        esac
        
        # Get public IP for external-ip config
        local PUBLIC_IP
        PUBLIC_IP=$(curl -s ifconfig.me 2>/dev/null || curl -s icanhazip.com 2>/dev/null || echo "")
        
        # Determine realm from domain or use default
        local TURN_REALM="${CADDY_DOMAIN:-ginto.local}"
        
        # Configure coturn
        log_info "Configuring coturn with external IP: $PUBLIC_IP"
        
        sudo tee /etc/turnserver.conf > /dev/null << TURNEOF
# Coturn TURN server configuration for Ginto AI
listening-port=3478
tls-listening-port=5349

# External IP for NAT traversal
${PUBLIC_IP:+external-ip=$PUBLIC_IP}

# Realm (your domain)
realm=$TURN_REALM

# Use fingerprints
fingerprint

# Long-term credential mechanism
lt-cred-mech

# Static credentials for Ginto AI
user=ginto:ginto-turn-2026

# Disable old STUN compatibility for security
no-stun-backward-compatibility
no-rfc5780
response-origin-only-with-rfc5780

# Use syslog
syslog

# Min/max ports for relay
min-port=49152
max-port=65535
TURNEOF

        # Enable coturn in default config
        if [ -f /etc/default/coturn ]; then
            sudo sed -i 's/^#\?TURNSERVER_ENABLED=.*/TURNSERVER_ENABLED=1/' /etc/default/coturn
        else
            echo "TURNSERVER_ENABLED=1" | sudo tee /etc/default/coturn > /dev/null
        fi
        
        # Enable and start coturn
        sudo systemctl enable coturn
        sudo systemctl restart coturn
        
        if systemctl is-active --quiet coturn; then
            log_success "coturn installed and running on port 3478"
        else
            log_warn "coturn installed but service may need manual start"
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
    
    # Create symlinks in /usr/local/bin for IMMEDIATE system-wide access
    # This works without needing to source any profile or restart shell
    log_info "Creating system-wide symlinks in /usr/local/bin..."
    for binary in "$LLAMACPP_BIN"/llama-*; do
        if [ -f "$binary" ] && [ -x "$binary" ]; then
            local binname=$(basename "$binary")
            sudo ln -sf "$binary" "/usr/local/bin/$binname" 2>/dev/null || true
        fi
    done
    
    # Also symlink the main binaries individually for certainty
    if [ -f "$LLAMACPP_BIN/llama-server" ]; then
        sudo ln -sf "$LLAMACPP_BIN/llama-server" "/usr/local/bin/llama-server"
        log_info "Linked: llama-server -> /usr/local/bin/llama-server"
    fi
    if [ -f "$LLAMACPP_BIN/llama-cli" ]; then
        sudo ln -sf "$LLAMACPP_BIN/llama-cli" "/usr/local/bin/llama-cli"
        log_info "Linked: llama-cli -> /usr/local/bin/llama-cli"
    fi
    
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
    
    log_success "llama.cpp available system-wide (symlinked to /usr/local/bin)"
}

# Create default llama.cpp model start script with default models
create_llamacpp_start_script() {
    local SCRIPT_PATH="$PROJECT_DIR/bin/start_llamacpp_models.sh"
    local STOP_SCRIPT="$PROJECT_DIR/bin/stop_llamacpp_models.sh"
    
    # If script already exists in repo, just make it executable
    if [ -f "$SCRIPT_PATH" ]; then
        log_info "llama.cpp start script already exists, making executable..."
        chmod +x "$SCRIPT_PATH"
        chmod +x "$STOP_SCRIPT" 2>/dev/null || true
        log_success "bin/start_llamacpp_models.sh is ready"
        return 0
    fi
    
    log_info "Creating llama.cpp model start script..."
    
    cat > "$SCRIPT_PATH" << 'SCRIPT'
#!/usr/bin/env bash
# Ginto AI - llama.cpp Model Starter
# Starts llama-server instances for vision and reasoning models

set -e

# Default models (can be overridden via environment or .env file)
VISION_HF_MODEL="${VISION_HF_MODEL:-ggml-org/SmolVLM2-500M-Video-Instruct-GGUF}"
REASONING_HF_MODEL="${REASONING_HF_MODEL:-lm-kit/qwen-3-0.6b-instruct-gguf}"

# Load from .env if exists
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GINTO_DIR="$(dirname "$SCRIPT_DIR")"
if [ -f "$GINTO_DIR/.env" ]; then
    source "$GINTO_DIR/.env" 2>/dev/null || true
fi

echo "╔════════════════════════════════════════╗"
echo "║   Ginto AI - Starting LLM Models       ║"
echo "╚════════════════════════════════════════╝"
echo ""

# Check if llama-server is available
if ! command -v llama-server &>/dev/null; then
    echo "[ERROR] llama-server not found in PATH"
    echo "Please ensure llama.cpp is compiled and in PATH"
    exit 1
fi

# Start Vision Model (port 8033)
if [ -n "$VISION_HF_MODEL" ] && [ "$VISION_HF_MODEL" != "skip" ]; then
    echo "[INFO] Starting Vision Model: $VISION_HF_MODEL"
    echo "       Port: 8033"
    nohup llama-server -hf "$VISION_HF_MODEL" --jinja -c 0 --host 0.0.0.0 --port 8033 > /tmp/llama-vision.log 2>&1 &
    echo $! > /tmp/llama-vision.pid
    echo "[OK] Vision model started (PID: $!)"
    echo ""
fi

# Start Reasoning Model (port 8034)
if [ -n "$REASONING_HF_MODEL" ] && [ "$REASONING_HF_MODEL" != "skip" ]; then
    echo "[INFO] Starting Reasoning Model: $REASONING_HF_MODEL"
    echo "       Port: 8034"
    nohup llama-server -hf "$REASONING_HF_MODEL" -c 0 --host 0.0.0.0 --port 8034 > /tmp/llama-reasoning.log 2>&1 &
    echo $! > /tmp/llama-reasoning.pid
    echo "[OK] Reasoning model started (PID: $!)"
    echo ""
fi

echo "Models are starting in background. Check logs at:"
echo "  Vision:    /tmp/llama-vision.log"
echo "  Reasoning: /tmp/llama-reasoning.log"
echo ""
echo "To stop models: kill \$(cat /tmp/llama-*.pid 2>/dev/null)"
echo "To view logs:   tail -f /tmp/llama-*.log"
SCRIPT

    chmod +x "$SCRIPT_PATH"
    
    # Also create a stop script
    local STOP_SCRIPT="$PROJECT_DIR/bin/stop_llamacpp_models.sh"
    cat > "$STOP_SCRIPT" << 'STOPSCRIPT'
#!/usr/bin/env bash
# Ginto AI - Stop llama.cpp models

echo "Stopping llama.cpp models..."

for pidfile in /tmp/llama-*.pid; do
    if [ -f "$pidfile" ]; then
        pid=$(cat "$pidfile")
        if kill -0 "$pid" 2>/dev/null; then
            kill "$pid"
            echo "Stopped PID $pid"
        fi
        rm -f "$pidfile"
    fi
done

echo "Done."
STOPSCRIPT

    chmod +x "$STOP_SCRIPT"
    
    log_success "Created: bin/start_llamacpp_models.sh"
    log_success "Created: bin/stop_llamacpp_models.sh"
    log_info "Run './bin/start_llamacpp_models.sh' to start the default models"
}

# Install llama.cpp from source for local LLM inference
install_llamacpp() {
    # Check if user explicitly chose to skip
    if [[ "${LLAMACPP_MODE:-compile}" == "skip" ]]; then
        log_info "Skipping llama.cpp installation (user choice)"
        return 0
    fi
    
    log_step "Checking llama.cpp installation..."
    
    local LLAMACPP_DIR="$INSTALL_USER_HOME/llama.cpp"
    local LLAMACPP_BIN="$LLAMACPP_DIR/build/bin"
    
    # Check if already installed
    if [ -f "$LLAMACPP_BIN/llama-server" ] || [ -f "$LLAMACPP_BIN/llama-cli" ]; then
        log_info "llama.cpp already installed at $LLAMACPP_DIR"
        
        # Ensure PATH is configured
        configure_llamacpp_path
        
        # Ensure start script exists
        if [ ! -f "$PROJECT_DIR/bin/start_llamacpp_models.sh" ]; then
            create_llamacpp_start_script
        fi
        
        # Auto-start the models in Docker mode
        if [[ "${INSTALL_MODE:-}" == "docker" ]]; then
            log_step "Starting llama.cpp models..."
            if [ -f "$PROJECT_DIR/bin/start_llamacpp_models.sh" ]; then
                bash "$PROJECT_DIR/bin/start_llamacpp_models.sh" --restart
                log_success "Models are starting in background. Please wait 30-60 seconds for download."
            fi
            
            # Configure Ollama for Docker container access (if installed)
            configure_ollama_for_docker
        fi
        
        # Show available commands
        log_success "Available llama.cpp commands:"
        ls -1 "$LLAMACPP_BIN" 2>/dev/null | head -10 | while read cmd; do
            echo "  - $cmd"
        done
        return 0
    fi
    
    log_step "Installing llama.cpp from source..."
    
    # Ensure build dependencies are installed (cmake, g++, etc.)
    if ! command -v cmake &>/dev/null || ! command -v g++ &>/dev/null; then
        log_info "Installing build dependencies (cmake, build-essential)..."
        sudo apt-get update -qq
        sudo apt-get install -y cmake build-essential git pkg-config libcurl4-openssl-dev
        hash -r
    fi
    
    # Verify cmake is now available
    if ! command -v cmake &>/dev/null; then
        log_error "Failed to install cmake. Please install manually: sudo apt-get install cmake build-essential"
        cd "$PROJECT_DIR"
        return 1
    fi
    
    # Clone the repository; re-clone if CMakeLists.txt is missing (partial/corrupted clone)
    if [ -d "$LLAMACPP_DIR" ]; then
        if [ ! -f "$LLAMACPP_DIR/CMakeLists.txt" ]; then
            log_warn "llama.cpp directory exists but CMakeLists.txt is missing (corrupted clone) — re-cloning..."
            rm -rf "$LLAMACPP_DIR"
            git clone https://github.com/ggerganov/llama.cpp.git "$LLAMACPP_DIR"
        else
            log_info "llama.cpp directory exists, pulling latest..."
            git -C "$LLAMACPP_DIR" pull origin master || true
        fi
    else
        log_info "Cloning llama.cpp repository..."
        git clone https://github.com/ggerganov/llama.cpp.git "$LLAMACPP_DIR"
    fi

    # Guard: ensure CMakeLists.txt is present before attempting to build
    if [ ! -f "$LLAMACPP_DIR/CMakeLists.txt" ]; then
        log_error "CMakeLists.txt not found in $LLAMACPP_DIR after clone. Cannot build llama.cpp."
        cd "$PROJECT_DIR"
        return 1
    fi

    # If download-only mode, stop here
    if [[ "${LLAMACPP_MODE:-compile}" == "download" ]]; then
        log_success "llama.cpp downloaded to $LLAMACPP_DIR"
        log_info "To compile manually:"
        echo "  cmake -S \"$LLAMACPP_DIR\" -B \"$LLAMACPP_DIR/build\" -DCMAKE_BUILD_TYPE=Release -DLLAMA_CURL=ON -DLLAMA_NATIVE=ON -DLLAMA_BUILD_SERVER=ON"
        echo "  cmake --build \"$LLAMACPP_DIR/build\" --config Release -j\$(nproc)"
        cd "$PROJECT_DIR"
        return 0
    fi

    # Configure with CMake using explicit -S/-B to avoid relative-path issues
    log_info "Configuring llama.cpp build..."
    cmake -S "$LLAMACPP_DIR" -B "$LLAMACPP_DIR/build" \
        -DCMAKE_BUILD_TYPE=Release \
        -DLLAMA_CURL=ON \
        -DLLAMA_NATIVE=ON \
        -DLLAMA_BUILD_SERVER=ON

    # Build using all available cores
    local NPROC=$(nproc 2>/dev/null || echo 4)
    log_info "Building llama.cpp with $NPROC cores (this may take a few minutes)..."
    cmake --build "$LLAMACPP_DIR/build" --config Release -j"$NPROC"
    
    # Verify installation and configure PATH
    if [ -f "$LLAMACPP_BIN/llama-server" ] || [ -f "$LLAMACPP_BIN/llama-cli" ]; then
        log_success "llama.cpp built successfully!"
        
        # Configure PATH
        configure_llamacpp_path
        
        # Create default model start script
        create_llamacpp_start_script
        
        # Auto-start the models only in Docker mode (native mode lets web installer do it)
        if [[ "${INSTALL_MODE:-}" == "docker" ]]; then
            log_step "Starting llama.cpp models..."
            if [ -f "$PROJECT_DIR/bin/start_llamacpp_models.sh" ]; then
                bash "$PROJECT_DIR/bin/start_llamacpp_models.sh" --restart
                log_success "Models are starting in background. Please wait 30-60 seconds for download."
            fi
            
            # Configure Ollama for Docker container access (if installed)
            configure_ollama_for_docker
        else
            log_info "Run 'bin/start_llamacpp_models.sh' after web installer to start models"
        fi
        
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

# Install and configure PowerDNS authoritative server
# Used for hosting DNS zones via /admin/hosting/dns
# Skipped on local/development machines (use --force-powerdns to override)
install_powerdns() {
    log_step "Checking PowerDNS..."
    
    # Skip if --skip-powerdns flag was passed
    if [[ "$SKIP_POWERDNS" == "true" ]]; then
        log_info "Skipping PowerDNS (--skip-powerdns flag)"
        return 0
    fi
    
    # Auto-detect if this is a local machine (not a public server)
    # Skip PowerDNS on local machines unless --force-powerdns is set
    if [[ "$FORCE_POWERDNS" != "true" ]]; then
        # Check if running in LXC/container (not a bare-metal server)
        if [[ -f /run/host_configured ]] || grep -q "container=lxc" /proc/1/environ 2>/dev/null || [[ -f /.dockerenv ]]; then
            log_info "Skipping PowerDNS (detected container/LXC environment)"
            log_info "Use --force-powerdns to install anyway"
            return 0
        fi
        
        # Check if public IP differs from local IPs (indicates NAT/local network)
        local PUBLIC_IP
        PUBLIC_IP=$(curl -s -4 --connect-timeout 5 ifconfig.me 2>/dev/null || echo "")
        if [[ -n "$PUBLIC_IP" ]]; then
            # Check if public IP is assigned to any local interface
            if ! ip addr show 2>/dev/null | grep -q "$PUBLIC_IP"; then
                log_info "Skipping PowerDNS (public IP $PUBLIC_IP not bound to local interfaces)"
                log_info "This indicates NAT/local network - DNS server wouldn't be reachable"
                log_info "Use --force-powerdns to install anyway"
                return 0
            fi
        fi
    fi
    
    local pdns_installed=false
    
    # Check if PowerDNS is already installed and running
    if command -v pdns_server &>/dev/null; then
        if systemctl is-active --quiet pdns 2>/dev/null; then
            log_info "PowerDNS already installed and running"
            pdns_installed=true
        fi
    fi
    
    # Install PowerDNS if not present
    if ! $pdns_installed; then
        log_info "Installing PowerDNS authoritative server with MySQL backend..."
        
        # Stop pdns if it's trying to run with bad config (from previous failed install)
        sudo systemctl stop pdns 2>/dev/null || true
        
        case $OS in
            ubuntu|debian)
                # Prevent auto-start during install (config not ready yet)
                sudo mkdir -p /etc/powerdns
                echo "# Placeholder - will be configured after install" | sudo tee /etc/powerdns/pdns.conf > /dev/null
                sudo apt-get install -y pdns-server pdns-backend-mysql
                ;;
            fedora)
                sudo dnf install -y pdns pdns-backend-mysql
                ;;
        esac
        
        # Stop the service that may have started with incomplete config
        sudo systemctl stop pdns 2>/dev/null || true
        
        # Generate API key
        local PDNS_API_KEY
        PDNS_API_KEY=$(openssl rand -hex 32)
        
        # Get public IP for binding
        local PUBLIC_IP
        PUBLIC_IP=$(curl -s -4 ifconfig.me 2>/dev/null || echo "0.0.0.0")
        
        # Configure PowerDNS
        log_info "Configuring PowerDNS..."
        sudo tee /etc/powerdns/pdns.conf > /dev/null << EOF
# PowerDNS Authoritative Server Configuration
# Managed by Ginto AI - https://ginto.ai

# Backend: Use gmysql (MySQL/MariaDB)
launch=gmysql
gmysql-host=127.0.0.1
gmysql-port=3306
gmysql-dbname=${DB_NAME:-ginto}
gmysql-user=${DB_USER:-ginto}
gmysql-password=${DB_PASS:-}
gmysql-dnssec=no

# Network settings
local-address=$PUBLIC_IP
local-port=53

# API settings for web management
api=yes
api-key=$PDNS_API_KEY
webserver=yes
webserver-address=127.0.0.1
webserver-port=8081
webserver-allow-from=127.0.0.1

# Logging
loglevel=4
log-dns-queries=no
log-dns-details=no

# Security
allow-axfr-ips=127.0.0.1
disable-axfr=yes
version-string=anonymous

# Performance
cache-ttl=60
query-cache-ttl=20
negquery-cache-ttl=60

# SOA defaults
default-soa-content=ns1.ginto.ai admin.ginto.ai 0 10800 3600 604800 3600
EOF
        
        # Add PowerDNS API credentials to .env if not already present
        if [ -f "$PROJECT_DIR/.env" ]; then
            if ! grep -q "POWERDNS_API_KEY" "$PROJECT_DIR/.env"; then
                echo "" >> "$PROJECT_DIR/.env"
                echo "# PowerDNS API Configuration" >> "$PROJECT_DIR/.env"
                echo "POWERDNS_API_KEY=$PDNS_API_KEY" >> "$PROJECT_DIR/.env"
                echo "POWERDNS_API_URL=http://127.0.0.1:8081/api/v1" >> "$PROJECT_DIR/.env"
                log_info "Added PowerDNS API credentials to .env"
            fi
        fi
        
                # Load the PowerDNS MySQL schema if the domains table doesn't exist yet
        # (fresh DB, or a DB that predates PowerDNS being added). Without this,
        # pdns_server starts, connects fine, then crash-loops on its first query.
        local table_exists
        table_exists=$(mysql -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME:-ginto}' AND table_name='domains';" 2>/dev/null || echo 0)
        if [ "$table_exists" == "0" ]; then
            log_info "PowerDNS schema not found in database, loading it..."
            local schema_file
            schema_file=$(find /usr/share/doc -iname "schema.mysql.sql*" -path "*pdns*" 2>/dev/null | head -1)
            if [ -n "$schema_file" ]; then
                if [[ "$schema_file" == *.gz ]]; then
                    zcat "$schema_file" | sudo mysql "${DB_NAME:-ginto}"
                else
                    sudo mysql "${DB_NAME:-ginto}" < "$schema_file"
                fi
                log_success "PowerDNS schema loaded into ${DB_NAME:-ginto}"
            else
                log_warn "Could not locate PowerDNS schema file — pdns.service will fail to start until it's loaded manually"
                log_info "See: https://doc.powerdns.com/authoritative/backends/generic-mysql.html"
            fi
        else
            log_info "PowerDNS schema already present in database"
        fi

        # Enable and start PowerDNS
        sudo systemctl enable pdns
        sudo systemctl restart pdns
        
        # Verify PowerDNS is running
        sleep 2
        if systemctl is-active --quiet pdns 2>/dev/null; then
            log_success "PowerDNS installed and running on $PUBLIC_IP:53"
            log_info "API available at http://127.0.0.1:8081/api/v1"
        else
            log_warn "PowerDNS installed but service may need manual configuration"
            log_info "Check: sudo systemctl status pdns"
            return 1
        fi
    fi
}

# Install Ollama using official installer
# See: https://ollama.com/download/linux
install_ollama() {
    log_step "Installing Ollama..."
    
    # Use Ollama's official install script
    curl -fsSL https://ollama.com/install.sh | sh
    
    if command -v ollama &>/dev/null; then
        log_success "Ollama installed successfully: $(ollama --version 2>/dev/null || echo 'version unknown')"
        return 0
    else
        log_error "Ollama installation failed"
        return 1
    fi
}

# Configure Ollama to listen on all interfaces for Docker access
# Uses systemd drop-in override (Ollama-recommended approach)
# See: https://docs.ollama.com/linux
apply_ollama_docker_config() {
    local OVERRIDE_DIR="/etc/systemd/system/ollama.service.d"
    local OVERRIDE_FILE="$OVERRIDE_DIR/override.conf"
    
    # Check if systemd service exists
    if [ ! -f /etc/systemd/system/ollama.service ] && ! systemctl cat ollama &>/dev/null 2>&1; then
        log_warn "Ollama systemd service not found, cannot configure"
        return 1
    fi
    
    # Check if already configured
    if [ -f "$OVERRIDE_FILE" ] && grep -q "OLLAMA_HOST=0.0.0.0" "$OVERRIDE_FILE" 2>/dev/null; then
        log_info "Ollama already configured for Docker access"
        return 0
    fi
    
    # Create drop-in override directory
    sudo mkdir -p "$OVERRIDE_DIR"
    
    # Create override file to bind to all interfaces
    echo '[Service]
Environment="OLLAMA_HOST=0.0.0.0"' | sudo tee "$OVERRIDE_FILE" > /dev/null
    
    # Reload systemd and restart Ollama
    sudo systemctl daemon-reload
    sudo systemctl restart ollama
    
    log_success "Ollama configured to listen on all interfaces (0.0.0.0:11434)"
    return 0
}

# Setup Ollama for Docker mode - handles install prompts and configuration
# Called during Docker install to ensure Ollama is accessible from containers
configure_ollama_for_docker() {
    log_step "Ollama Configuration for Docker..."
    
    # Check if Ollama is already installed
    if command -v ollama &>/dev/null; then
        log_info "Ollama detected: $(ollama --version 2>/dev/null || echo 'installed')"
        
        # Auto-configure for Docker access
        apply_ollama_docker_config
        return 0
    fi
    
    # Ollama not installed - prompt user
    echo ""
    log_info "Ollama provides local LLM inference with easy model management."
    log_info "It's an alternative to llama.cpp with a simpler interface."
    echo ""
    echo "  1) Install Ollama    - Download and configure for Docker access"
    echo "  2) Skip              - Don't install Ollama"
    echo ""
    
    local ollama_choice=""
    
    # Check for non-interactive mode
    if [ -t 0 ] && [ -r /dev/tty ] && (echo -n "" > /dev/tty) 2>/dev/null; then
        log_prompt "Install Ollama? (1-2) [default: 2 - skip]:"
        read -r -p "> " ollama_choice < /dev/tty
    else
        # Non-interactive - skip by default
        log_info "Non-interactive mode, skipping Ollama installation"
        ollama_choice="2"
    fi
    
    case "${ollama_choice:-2}" in
        1|install|yes|y)
            install_ollama
            if [ $? -eq 0 ]; then
                apply_ollama_docker_config
            fi
            ;;
        *)
            log_info "Skipping Ollama installation"
            ;;
    esac
}

# Stop and mask web servers that would conflict with Caddy on ports 80/443
mask_competing_servers() {
    local competing=("nginx" "apache2" "apache" "httpd" "lighttpd")
    for svc in "${competing[@]}"; do
        if systemctl list-units --all --no-legend "${svc}.service" 2>/dev/null | grep -q "${svc}"; then
            log_info "Masking competing web server: ${svc}"
            sudo systemctl stop    "${svc}" 2>/dev/null || true
            sudo systemctl disable "${svc}" 2>/dev/null || true
            sudo systemctl mask    "${svc}" 2>/dev/null || true
        fi
    done
}

# Install Caddy web server
install_caddy() {
    log_step "Installing Caddy web server..."

    # Always silence competing servers before Caddy claims ports 80/443
    mask_competing_servers

    if command -v caddy &>/dev/null; then
        log_info "Caddy already installed: $(caddy version)"
        # Still ensure data directories exist with proper ownership
        sudo mkdir -p /var/lib/caddy/.local/share/caddy
        sudo mkdir -p /var/lib/caddy/.config/caddy
        sudo chown -R caddy:caddy /var/lib/caddy
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
    
    # Create Caddy data directories for TLS certificates
    sudo mkdir -p /var/lib/caddy/.local/share/caddy
    sudo mkdir -p /var/lib/caddy/.config/caddy
    sudo chown -R caddy:caddy /var/lib/caddy
    log_info "Created Caddy data directories"
    
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
    
    # Determine terminal server port based on install mode
    # Docker mode uses port 3001 (Docker terminal-server), native uses 31827 (PHP)
    local TERMINAL_PORT=31827
    if [[ "${INSTALL_MODE:-}" == "docker" ]]; then
        TERMINAL_PORT=3001
    fi
    
    if [[ "$CADDY_LIVE_MODE" == "yes" ]]; then
        # Live server mode
        log_info "Configuring Caddy for live server: $CADDY_DOMAIN (terminal port: $TERMINAL_PORT)"
        
        sudo tee /etc/caddy/Caddyfile > /dev/null << EOF
{
    on_demand_tls {
        ask http://127.0.0.1:8000/api/tunnel/verify
    }
}

import sites-enabled/*

$CADDY_DOMAIN {
    tls $CADDY_TLS_EMAIL

    encode zstd gzip

    handle /stream/* {
        uri strip_prefix /stream
        reverse_proxy 127.0.0.1:$TERMINAL_PORT {
            flush_interval -1
            transport http {
                read_timeout 0
                write_timeout 0
            }
            header_up Host localhost
        }
    }

    handle /messenger-ws/* {
        uri path_regexp .* /messenger
        reverse_proxy 127.0.0.1:$TERMINAL_PORT {
            flush_interval -1
            transport http {
                read_timeout 0
                write_timeout 0
            }
            header_up Host localhost
        }
    }

    handle /mall-notify-ws/* {
        uri path_regexp .* /mall-notify
        reverse_proxy 127.0.0.1:$TERMINAL_PORT {
            flush_interval -1
            transport http {
                read_timeout 0
                write_timeout 0
            }
            header_up Host localhost
        }
    }

    handle /terminal/* {
        uri strip_prefix /terminal
        reverse_proxy 127.0.0.1:$TERMINAL_PORT {
            flush_interval -1
            transport http {
                read_timeout 0
                write_timeout 0
            }
            header_up Host localhost
        }
    }

    handle /ws_stt/* {
        uri strip_prefix /ws_stt
        reverse_proxy 127.0.0.1:9011
    }

    # noVNC websocket proxy - extract port from path and proxy
    handle_path /vnc-ws/6170/* {
        reverse_proxy 127.0.0.1:6170
    }
    handle_path /vnc-ws/6171/* {
        reverse_proxy 127.0.0.1:6171
    }
    handle_path /vnc-ws/6172/* {
        reverse_proxy 127.0.0.1:6172
    }
    handle_path /vnc-ws/6173/* {
        reverse_proxy 127.0.0.1:6173
    }
    handle_path /vnc-ws/6174/* {
        reverse_proxy 127.0.0.1:6174
    }
    handle_path /vnc-ws/6175/* {
        reverse_proxy 127.0.0.1:6175
    }
    handle_path /vnc-ws/6176/* {
        reverse_proxy 127.0.0.1:6176
    }
    handle_path /vnc-ws/6177/* {
        reverse_proxy 127.0.0.1:6177
    }
    handle_path /vnc-ws/6178/* {
        reverse_proxy 127.0.0.1:6178
    }
    handle_path /vnc-ws/6179/* {
        reverse_proxy 127.0.0.1:6179
    }
    handle_path /vnc-ws/6180/* {
        reverse_proxy 127.0.0.1:6180
    }

    @root {
        path /
    }
    rewrite @root /chat

    # Serve static video files directly
    handle /public/assets/videos/* {
        root * /home/$INSTALL_USER/ginto.ai
        file_server
    }

    # WebSocket tunnel endpoint
    @tunnel_ws path /tunnel/ws
    handle @tunnel_ws {
        reverse_proxy 127.0.0.1:8765 {
            flush_interval -1
            transport http {
                read_timeout 0
                write_timeout 0
            }
        }
    }

    # Everything else goes to PHP app
    handle {
        reverse_proxy 127.0.0.1:8000
    }
}

# FRP Tunnel - proxy subdomains to frps vhost port
*.$CADDY_DOMAIN {
    tls {
        on_demand
    }
    reverse_proxy 127.0.0.1:7080 {
        header_up Host {host}
    }
}
EOF
    else
        # Local development mode
        log_info "Configuring Caddy for local development (port 80, terminal port: $TERMINAL_PORT)"
        
        sudo tee /etc/caddy/Caddyfile > /dev/null << EOF
{
    auto_https off
}

http://localhost, :80 {
    encode zstd gzip

    handle /stream/* {
        uri strip_prefix /stream
        reverse_proxy 127.0.0.1:$TERMINAL_PORT {
            header_up Host localhost
        }
    }

    handle /messenger-ws/* {
        uri path_regexp .* /messenger
        reverse_proxy 127.0.0.1:$TERMINAL_PORT {
            header_up Host localhost
        }
    }

    handle /mall-notify-ws/* {
        uri path_regexp .* /mall-notify
        reverse_proxy 127.0.0.1:$TERMINAL_PORT {
            header_up Host localhost
        }
    }

    handle /terminal/* {
        uri strip_prefix /terminal
        reverse_proxy 127.0.0.1:$TERMINAL_PORT {
            header_up Host localhost
        }
    }

    handle /ws_stt/* {
        uri strip_prefix /ws_stt
        reverse_proxy 127.0.0.1:9011
    }

    # noVNC websocket proxy for sandbox VNC connections
    handle_path /vnc-ws/6170/* {
        reverse_proxy 127.0.0.1:6170
    }
    handle_path /vnc-ws/6171/* {
        reverse_proxy 127.0.0.1:6171
    }
    handle_path /vnc-ws/6172/* {
        reverse_proxy 127.0.0.1:6172
    }
    handle_path /vnc-ws/6173/* {
        reverse_proxy 127.0.0.1:6173
    }
    handle_path /vnc-ws/6174/* {
        reverse_proxy 127.0.0.1:6174
    }
    handle_path /vnc-ws/6175/* {
        reverse_proxy 127.0.0.1:6175
    }
    handle_path /vnc-ws/6176/* {
        reverse_proxy 127.0.0.1:6176
    }
    handle_path /vnc-ws/6177/* {
        reverse_proxy 127.0.0.1:6177
    }
    handle_path /vnc-ws/6178/* {
        reverse_proxy 127.0.0.1:6178
    }
    handle_path /vnc-ws/6179/* {
        reverse_proxy 127.0.0.1:6179
    }
    handle_path /vnc-ws/6180/* {
        reverse_proxy 127.0.0.1:6180
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
    
    # Detect composer binary — check common install locations in order
    local COMPOSER_BIN=""
    local composer_candidates=(
        "$(command -v composer 2>/dev/null || true)"
        "/usr/local/bin/composer"
        "/usr/bin/composer"
        "$INSTALL_USER_HOME/.local/bin/composer"
        "$INSTALL_USER_HOME/.composer/vendor/bin/composer"
        "/home/$INSTALL_USER/.local/bin/composer"
    )
    for candidate in "${composer_candidates[@]}"; do
        if [ -n "$candidate" ] && [ -x "$candidate" ]; then
            COMPOSER_BIN="$candidate"
            break
        fi
    done

    if [ -z "$COMPOSER_BIN" ]; then
        log_error "Cannot locate composer binary. Please install Composer and re-run."
        return 1
    fi
    log_info "Using composer at: $COMPOSER_BIN"

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
ExecStart=/bin/bash -c 'cd $PROJECT_DIR && bash bin/start_web.sh && bash bin/start_services.sh'
ExecStop=/usr/bin/pkill -f "php.*ginto"
Restart=always
RestartSec=5
StandardOutput=append:$(dirname $PROJECT_DIR)/storage/logs/ginto.log
StandardError=append:$(dirname $PROJECT_DIR)/storage/logs/ginto-error.log
Environment=PATH=$(dirname "$COMPOSER_BIN"):/usr/bin:/usr/local/bin:/home/$INSTALL_USER/.local/bin
Environment=HOME=$INSTALL_USER_HOME
Environment=COMPOSER_HOME=$INSTALL_USER_HOME/.composer
Environment=PHP_CLI_SERVER_WORKERS=4

[Install]
WantedBy=multi-user.target
EOF
    
    # Reload systemd and enable service
    sudo systemctl daemon-reload
    sudo systemctl enable ginto.service
    
    log_success "Ginto service configured for user: $INSTALL_USER"
    log_info "Service will start automatically on boot"
}

# Install SDCPU (FastSD CPU Image Generation) - creates venv and installs dependencies
install_sdcpu() {
    log_step "Installing SDCPU (FastSD CPU Image Generation)..."
    
    SDCPU_DIR="$PROJECT_DIR/tools/sdcpu"
    
    # Check if SDCPU source exists
    if [ ! -f "$SDCPU_DIR/requirements.txt" ] || [ ! -f "$SDCPU_DIR/src/api_server.py" ]; then
        log_info "SDCPU source not found at $SDCPU_DIR, skipping"
        return 0
    fi
    
    # Skip only if the venv exists AND its core dependencies actually import.
    # A bare venv dir can be left behind by a failed pip install, so verify.
    if [ -d "$SDCPU_DIR/venv" ] && [ -f "$SDCPU_DIR/venv/bin/python" ]; then
        if "$SDCPU_DIR/venv/bin/python" -c "import diffusers, fastapi, PIL" &>/dev/null; then
            log_info "SDCPU already installed (venv verified)"
            return 0
        fi
        log_warn "SDCPU venv is incomplete (deps failed to import) - reinstalling..."
    fi
    
    # In update mode (SKIP_CONFIGURED), we handle SDCPU interactively in prompt_configuration
    # So skip the automatic installation here
    if [[ "${SKIP_CONFIGURED:-false}" == "true" ]]; then
        log_info "SDCPU installation handled separately in update mode"
        return 0
    fi
    
    # Install system dependencies for Pillow and other Python packages
    log_info "Installing system dependencies for SDCPU..."
    if command -v apt-get &>/dev/null; then
        # Get Python version (e.g., 3.12)
        local py_version
        py_version=$(python3 -c 'import sys; print(f"{sys.version_info.major}.{sys.version_info.minor}")')
        # Install version-specific venv package, pip, dev headers, and Pillow build dependencies
        sudo apt-get install -y "python${py_version}-venv" "python${py_version}-dev" \
            python3-venv python3-dev python3-pip \
            libjpeg-dev zlib1g-dev libpng-dev libfreetype6-dev liblcms2-dev \
            libopenjp2-7-dev libtiff-dev libwebp-dev 2>/dev/null || \
        sudo apt-get install -y python3-venv python3-dev python3-pip libjpeg-dev zlib1g-dev
    elif command -v dnf &>/dev/null; then
        sudo dnf install -y python3-virtualenv python3-pip python3-devel \
            libjpeg-devel zlib-devel libpng-devel freetype-devel lcms2-devel \
            openjpeg2-devel libtiff-devel libwebp-devel
    elif command -v apk &>/dev/null; then
        sudo apk add --no-cache python3 py3-pip py3-virtualenv python3-dev \
            jpeg-dev zlib-dev libpng-dev freetype-dev lcms2-dev \
            openjpeg-dev tiff-dev libwebp-dev
    fi
    
    # Verify venv is now available
    if ! python3 -m venv --help &>/dev/null; then
        log_error "Failed to install python3-venv - cannot set up SDCPU"
        return 1
    fi
    
    cd "$SDCPU_DIR"
    
    log_info "Creating Python virtual environment..."
    python3 -m venv venv
    
    log_info "Installing SDCPU dependencies (this may take a few minutes)..."
    source venv/bin/activate
    pip install --upgrade pip
    
    # Pin PyTorch to 2.8.0 for compatibility with optimum-intel (2.9.0 breaks it)
    # Use CPU-only wheels for smaller size and faster install
    log_info "Installing PyTorch 2.8.0 (CPU)..."
    pip install torch==2.8.0 torchvision==0.23.0 --index-url https://download.pytorch.org/whl/cpu
    
    # Now install the rest of requirements
    pip install -r requirements.txt
    deactivate
    
    # Fix ownership
    chown -R "$INSTALL_USER:$INSTALL_USER" "$SDCPU_DIR/venv"
    
    cd "$PROJECT_DIR"
    
    log_success "SDCPU installed successfully"
}

# Configure SDCPU (FastSD CPU Image Generation) systemd service
configure_sdcpu_service() {
    log_step "Configuring SDCPU systemd service..."
    
    SDCPU_DIR="$PROJECT_DIR/tools/sdcpu"
    
    # Check if SDCPU is installed (has venv and api_server.py)
    if [ ! -d "$SDCPU_DIR/venv" ] || [ ! -f "$SDCPU_DIR/src/api_server.py" ]; then
        log_info "SDCPU not installed, skipping service configuration"
        log_info "To install SDCPU: cd $SDCPU_DIR && python3 -m venv venv && source venv/bin/activate && pip install -r requirements.txt"
        return 0
    fi
    
    # Skip if service already exists and we're in skip mode
    if [[ "${SKIP_CONFIGURED:-false}" == "true" ]] && [ -f "/etc/systemd/system/sdcpu.service" ]; then
        log_info "SDCPU service already configured, skipping"
        return 0
    fi
    
    # Create the systemd service file
    sudo tee /etc/systemd/system/sdcpu.service > /dev/null << EOF
[Unit]
Description=FastSD CPU Image Generation Server
After=network.target

[Service]
Type=simple
User=$INSTALL_USER
Group=$INSTALL_USER
WorkingDirectory=$SDCPU_DIR
ExecStart=$SDCPU_DIR/venv/bin/python src/api_server.py --port 8888
Restart=always
RestartSec=5
Environment=HOME=$INSTALL_USER_HOME
Environment=PATH=$SDCPU_DIR/venv/bin:/usr/local/bin:/usr/bin:/bin

[Install]
WantedBy=multi-user.target
EOF
    
    # Reload systemd, enable and start the service
    sudo systemctl daemon-reload
    sudo systemctl enable sdcpu.service
    sudo systemctl restart sdcpu.service
    
    # Verify it started
    if sudo systemctl is-active --quiet sdcpu.service; then
        log_success "SDCPU service installed, enabled, and running"
    else
        log_warn "SDCPU service installed but failed to start - check /tmp/sdcpu.log"
    fi
}

# Configure Tunnel Server systemd service
configure_tunnel_service() {
    log_step "Configuring Tunnel Server systemd service..."
    
    TUNNEL_SCRIPT="$PROJECT_DIR/tools/tunnel/tunnel_server.php"
    
    # Check if tunnel server script exists
    if [ ! -f "$TUNNEL_SCRIPT" ]; then
        log_info "Tunnel server script not found at $TUNNEL_SCRIPT, skipping service configuration"
        return 0
    fi
    
    # Skip if service already exists and we're in skip mode
    if [[ "${SKIP_CONFIGURED:-false}" == "true" ]] && [ -f "/etc/systemd/system/ginto-tunnel.service" ]; then
        log_info "Tunnel service already configured, skipping"
        return 0
    fi
    
    # Create the systemd service file
    sudo tee /etc/systemd/system/ginto-tunnel.service > /dev/null << EOF
[Unit]
Description=Ginto Tunnel Server
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=$PROJECT_DIR
ExecStart=/usr/bin/php $TUNNEL_SCRIPT
Restart=always
RestartSec=5
Environment=TUNNEL_PORT=8765
StandardOutput=append:/var/log/ginto-tunnel.log
StandardError=append:/var/log/ginto-tunnel.log

[Install]
WantedBy=multi-user.target
EOF
    
    # Create log file if it doesn't exist
    sudo touch /var/log/ginto-tunnel.log
    sudo chmod 644 /var/log/ginto-tunnel.log
    
    # Reload systemd, enable and start the service
    sudo systemctl daemon-reload
    sudo systemctl enable ginto-tunnel.service
    sudo systemctl restart ginto-tunnel.service
    
    # Verify it started
    if sudo systemctl is-active --quiet ginto-tunnel.service; then
        log_success "Tunnel service installed, enabled, and running"
    else
        log_warn "Tunnel service installed but failed to start - check /var/log/ginto-tunnel.log"
    fi
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
    
    # Ensure www-data can traverse directories to reach bin/start_websockify.sh for noVNC
    # This sets execute permission on the path: /home/user -> /home/user/ginto.ai -> /home/user/ginto.ai/bin
    local home_dir
    home_dir=$(dirname "$PROJECT_DIR")
    chmod o+x "$home_dir" 2>/dev/null || true
    chmod o+x "$PROJECT_DIR" 2>/dev/null || true
    chmod o+x "$PROJECT_DIR/bin" 2>/dev/null || true
    chmod o+x "$PROJECT_DIR/public" 2>/dev/null || true
    chmod o+x "$PROJECT_DIR/public/lib" 2>/dev/null || true
    chmod o+x "$PROJECT_DIR/public/lib/novnc" 2>/dev/null || true
    [[ "$quiet" != "true" ]] && log_info "Set directory traverse permissions for websockify"
    
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
    
    # Ensure /var/www is owned by INSTALL_USER:www-data
    if [ -d "/var/www" ]; then
        local current_owner=$(stat -c '%U' /var/www 2>/dev/null)
        local current_group=$(stat -c '%G' /var/www 2>/dev/null)
        if [ "$current_owner" != "$INSTALL_USER" ] || [ "$current_group" != "www-data" ]; then
            log_info "Fixing /var/www ownership: $current_owner:$current_group -> $INSTALL_USER:www-data"
            sudo chown "$INSTALL_USER:www-data" /var/www
            sudo chmod 775 /var/www
            log_success "/var/www ownership fixed"
        else
            log_info "/var/www already owned by $INSTALL_USER:www-data"
        fi
    else
        log_info "Creating /var/www with correct ownership"
        sudo mkdir -p /var/www
        sudo chown "$INSTALL_USER:www-data" /var/www
        sudo chmod 775 /var/www
    fi
    
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

# Configure firewall rules for noVNC websockify ports
configure_firewall() {
    log_step "Configuring firewall..."
    
    # Check if ufw is installed and active
    if ! command -v ufw &>/dev/null; then
        log_info "UFW not installed, skipping firewall configuration"
        return 0
    fi
    
    # Check if ufw is active
    if ! sudo ufw status | grep -q "Status: active"; then
        log_info "UFW not active, skipping firewall configuration"
        return 0
    fi
    
    # Allow websockify port range 6170-6200 for noVNC connections
    # Each sandbox container gets assigned a unique port in this range
    if ! sudo ufw status | grep -q "6170:6200/tcp"; then
        sudo ufw allow 6170:6200/tcp comment "noVNC websockify ports"
        log_success "Opened ports 6170-6200 for noVNC websockify"
    else
        log_info "Websockify ports already allowed in firewall"
    fi
    
    # Allow DNS ports (53/tcp and 53/udp) for PowerDNS authoritative server
    if ! sudo ufw status | grep -q "53/tcp"; then
        sudo ufw allow 53/tcp comment "PowerDNS TCP"
        log_success "Opened port 53/tcp for PowerDNS"
    else
        log_info "DNS TCP port already allowed in firewall"
    fi
    
    if ! sudo ufw status | grep -q "53/udp"; then
        sudo ufw allow 53/udp comment "PowerDNS UDP"
        log_success "Opened port 53/udp for PowerDNS"
    else
        log_info "DNS UDP port already allowed in firewall"
    fi
    
    # Allow OpenWebUI port 8088 for embedded OpenWebUI access
    if ! sudo ufw status | grep -q "8088/tcp"; then
        sudo ufw allow 8088/tcp comment "OpenWebUI"
        log_success "Opened port 8088/tcp for OpenWebUI"
    else
        log_info "OpenWebUI port already allowed in firewall"
    fi
    
    # Allow coturn TURN server ports for WebRTC NAT traversal
    if ! sudo ufw status | grep -q "3478/tcp"; then
        sudo ufw allow 3478/tcp comment "TURN TCP"
        sudo ufw allow 3478/udp comment "TURN UDP"
        log_success "Opened port 3478 for TURN server"
    else
        log_info "TURN port 3478 already allowed in firewall"
    fi
    
    if ! sudo ufw status | grep -q "5349/tcp"; then
        sudo ufw allow 5349/tcp comment "TURN TLS"
        sudo ufw allow 5349/udp comment "TURN DTLS"
        log_success "Opened port 5349 for TURN TLS"
    else
        log_info "TURN TLS port already allowed in firewall"
    fi
    
    # Allow TURN relay ports (UDP high port range)
    if ! sudo ufw status | grep -q "49152:65535/udp"; then
        sudo ufw allow 49152:65535/udp comment "TURN relay ports"
        log_success "Opened ports 49152-65535/udp for TURN relay"
    else
        log_info "TURN relay ports already allowed in firewall"
    fi
}

# Install Composer dependencies
install_dependencies() {
    log_step "Installing Composer dependencies..."
    
    cd "$PROJECT_DIR"
    
    if [ -f composer.json ]; then
        # Ensure vendor directory has correct ownership (may have been created by Docker/root)
        if [ -d "$PROJECT_DIR/vendor" ]; then
            sudo chown -R "$INSTALL_USER:$INSTALL_USER" "$PROJECT_DIR/vendor"
        fi
        
        # Ensure project directory is writable by install user
        sudo chown "$INSTALL_USER:$INSTALL_USER" "$PROJECT_DIR"
        
        # Run composer as the install user, not root, so plugins can run
        sudo -u "$INSTALL_USER" composer install --no-interaction --prefer-dist
        log_success "Composer dependencies installed"
    else
        log_warn "composer.json not found, skipping"
    fi
    
    # Install phpMyAdmin nested dependencies
    local PMA_DIR="$PROJECT_DIR/vendor/phpmyadmin/phpmyadmin"
    if [ -d "$PMA_DIR" ] && [ -f "$PMA_DIR/composer.json" ]; then
        log_info "Installing phpMyAdmin dependencies..."
        cd "$PMA_DIR"
        sudo -u "$INSTALL_USER" composer install --no-interaction --prefer-dist
        cd "$PROJECT_DIR"
        log_success "phpMyAdmin dependencies installed"
    else
        log_info "phpMyAdmin not found in vendor, skipping nested install"
    fi
    
    # Install MCP tools Node.js dependencies
    if command -v npm &>/dev/null; then
        log_info "Installing MCP tools npm dependencies..."
        
        # Lightpanda MCP - headless browser for AI agents
        if [ -d "$PROJECT_DIR/tools/lightpanda-mcp" ] && [ -f "$PROJECT_DIR/tools/lightpanda-mcp/package.json" ]; then
            log_info "Installing Lightpanda MCP dependencies..."
            cd "$PROJECT_DIR/tools/lightpanda-mcp"
            # Install JS deps only. --ignore-scripts skips @lightpanda/browser's
            # postinstall, which downloads a large browser binary and can hang the
            # installer on slow links. We fetch that binary separately, below.
            sudo -u "$INSTALL_USER" npm install --no-audit --no-fund --ignore-scripts 2>/dev/null \
                || npm install --no-audit --no-fund --ignore-scripts
            # Download the Lightpanda browser binary best-effort, time-boxed so a
            # stalled download can never block the install. Non-fatal: the MCP tool
            # still installs and the binary retries on next run or first use.
            log_info "Downloading Lightpanda browser binary (best-effort, up to 3 min)..."
            if sudo -u "$INSTALL_USER" timeout 180 npx --yes @lightpanda/browser upgrade >/dev/null 2>&1; then
                log_success "Lightpanda browser binary ready"
            else
                log_warn "Lightpanda browser binary not downloaded (slow/unavailable) - skipping"
                log_info "Retry later: cd tools/lightpanda-mcp && npm run upgrade-browser"
            fi
            cd "$PROJECT_DIR"
            log_success "Lightpanda MCP installed"
        fi
        
        # Add other MCP tools here as needed
    else
        log_warn "npm not found, skipping MCP tools installation"
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
    
    # Function to update or add key=value in .env (only if value is non-empty)
    update_env_var() {
        local key="$1"
        local value="$2"
        local skip_if_empty="${3:-false}"
        
        # If value is empty and skip_if_empty is true, don't overwrite existing
        if [ -z "$value" ] && [ "$skip_if_empty" = "true" ]; then
            if grep -q "^${key}=" "$env_file" 2>/dev/null; then
                log_info "Preserving existing $key (new value empty)"
                return 0
            fi
        fi
        
        if grep -q "^${key}=" "$env_file" 2>/dev/null; then
            # Update existing key
            sed -i "s|^${key}=.*|${key}=${value}|" "$env_file"
        else
            # Add new key
            echo "${key}=${value}" >> "$env_file"
        fi
    }
    
    # Set database variables (preserve password if new value is empty)
    update_env_var "DB_TYPE" "mysql"
    update_env_var "DB_HOST" "localhost"
    update_env_var "DB_PORT" "3306"
    update_env_var "DB_NAME" "$DB_NAME"
    update_env_var "DB_USER" "$DB_USER"
    update_env_var "DB_PASS" "$DB_PASS" "true"
    
    # Set APP_URL based on Caddy mode
    if [[ "$CADDY_LIVE_MODE" == "yes" ]]; then
        update_env_var "CADDY_DOMAIN" "$CADDY_DOMAIN"
        update_env_var "APP_URL" "https://$CADDY_DOMAIN"
        # Default mail-from address for live installs (only set if not already present)
        if ! grep -q "^MAIL_FROM=" "$env_file" 2>/dev/null; then
            update_env_var "MAIL_FROM" "no-reply@$CADDY_DOMAIN"
        fi
    else
        update_env_var "CADDY_DOMAIN" "localhost"
        update_env_var "APP_URL" "http://localhost"
    fi
    
    # CRITICAL: Set proper ownership on .env so PHP/web installer can modify it
    # The file was created/modified as root, but ginto.service runs PHP as INSTALL_USER
    chown "$INSTALL_USER:$INSTALL_USER" "$env_file"
    chmod 664 "$env_file"
    log_info "Set .env ownership to $INSTALL_USER:$INSTALL_USER with mode 664"
    
    # Legacy install mode: create marker file for v1 /install wizard
    if [[ "$LEGACY_INSTALL" == "true" ]]; then
        touch "$PROJECT_DIR/.legacy-install"
        chown "$INSTALL_USER:$INSTALL_USER" "$PROJECT_DIR/.legacy-install"
        log_info "Legacy install mode enabled - /install wizard available"
    else
        # Remove legacy marker if it exists (ensure v2 flow)
        rm -f "$PROJECT_DIR/.legacy-install"
    fi
    
    log_success "Database configuration written to .env"
}

# Import GeoNames geographic data for zone search
import_geonames() {
    log_step "Importing geographic data (GeoNames)..."

    local geo_import="$PROJECT_DIR/bin/geo_import.php"
    if [ ! -f "$geo_import" ]; then
        log_warn "geo_import.php not found, skipping"
        return 0
    fi

    # Check if already imported
    local count
    count=$(sudo -u "$INSTALL_USER" php "$geo_import" --status 2>/dev/null | grep -c "PH" || true)
    if [ "$count" -gt 0 ]; then
        log_info "GeoNames PH data already imported, skipping"
        return 0
    fi

    # Read GEO_COUNTRIES from .env (default: PH)
    local countries="PH"
    if [ -f "$PROJECT_DIR/.env" ]; then
        local env_countries
        env_countries=$(grep -E "^GEO_COUNTRIES=" "$PROJECT_DIR/.env" 2>/dev/null | cut -d= -f2 | tr -d '"' | tr -d "'" || true)
        if [ -n "$env_countries" ]; then
            countries="$env_countries"
        fi
    fi

    log_info "Importing geographic data for: $countries"
    sudo -u "$INSTALL_USER" php "$geo_import" "$countries" 2>&1 | tail -20

    log_success "Geographic data import complete"
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
    echo "  - phpMyAdmin $([ -d "$PROJECT_DIR/vendor/phpmyadmin/phpmyadmin/vendor" ] && echo 'Installed' || echo 'N/A')"
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
    
    # Read domain from .env if available
    local env_domain=""
    local env_app_url=""
    if [ -f "$PROJECT_DIR/.env" ]; then
        env_domain=$(grep -E '^CADDY_DOMAIN=' "$PROJECT_DIR/.env" 2>/dev/null | cut -d'=' -f2- | tr -d '"' | tr -d "'" || echo "")
        env_app_url=$(grep -E '^APP_URL=' "$PROJECT_DIR/.env" 2>/dev/null | cut -d'=' -f2- | tr -d '"' | tr -d "'" || echo "")
    fi
    
    # Use configured domain or fall back to localhost
    local site_domain="${CADDY_DOMAIN:-$env_domain}"
    local app_url="${env_app_url:-}"
    
    local server_ip=$(get_server_ip)
    echo "Access your site:"
    
    # If domain is configured (live mode), show HTTPS URL
    if [ -n "$site_domain" ] && [ "$site_domain" != "localhost" ]; then
        echo "  - Website: https://$site_domain"
        if [ "$server_ip" != "unknown" ]; then
            echo "  - Server IP: $server_ip"
        fi
        if [ -d "$PROJECT_DIR/vendor/phpmyadmin/phpmyadmin/vendor" ]; then
            echo "  - phpMyAdmin: https://$site_domain/pma"
        fi
        echo "  - Live setup: https://$site_domain/live"
    elif [ -n "$app_url" ] && [[ "$app_url" == https://* ]]; then
        # Extract domain from APP_URL
        local url_domain=$(echo "$app_url" | sed 's|https://||' | sed 's|/.*||')
        echo "  - Website: $app_url"
        if [ "$server_ip" != "unknown" ]; then
            echo "  - Server IP: $server_ip"
        fi
        if [ -d "$PROJECT_DIR/vendor/phpmyadmin/phpmyadmin/vendor" ]; then
            echo "  - phpMyAdmin: https://$url_domain/pma"
        fi
        echo "  - Live setup: https://$url_domain/live"
    else
        # Local development mode
        echo "  - Local: http://localhost"
        if [ "$server_ip" != "unknown" ]; then
            echo "  - Network: http://$server_ip"
        fi
        echo "  - Direct: http://localhost:8000"
        if [ -d "$PROJECT_DIR/vendor/phpmyadmin/phpmyadmin/vendor" ]; then
            echo "  - phpMyAdmin: http://localhost/pma"
        fi
        echo "  - Local setup: http://localhost:8000/live"
    fi
    echo ""
    echo "Useful commands:"
    echo "  - Start:   sudo systemctl start ginto"
    echo "  - Stop:    sudo systemctl stop ginto"
    echo "  - Status:  sudo systemctl status ginto"
    echo "  - Logs:    tail -f $(dirname $PROJECT_DIR)/storage/logs/ginto.log"
    echo ""
}

#===============================================================================
# DOCKER MODE INSTALLATION FUNCTIONS
#===============================================================================

# Prompt for Docker-specific configuration
prompt_docker_configuration() {
    echo ""
    log_step "Docker Sandbox Mode Configuration"
    echo ""
    log_info "Ginto AI will install the main stack on this system:"
    log_info "  - PHP 8.3, MariaDB, Caddy, Redis (on host)"
    log_info "  - Docker ONLY for user sandboxes (isolated code execution)"
    echo ""
    log_info "This provides:"
    log_info "  - Better performance (main app runs natively)"
    log_info "  - Strong isolation (user code runs in Docker containers)"
    log_info "  - Easy management (systemd for app, Docker for sandboxes)"
    echo ""
    
    # Domain configuration
    echo ""
    echo "  1) Local     - localhost on port 80 (development)"
    echo "  2) Domain    - Configure HTTPS with your domain (production)"
    echo ""
    log_prompt "Choose server mode (1-2) [default: 1]:"
    read -r -p "> " domain_choice < /dev/tty
    
    if [[ "$domain_choice" == "2" ]]; then
        CADDY_LIVE_MODE="yes"
        echo ""
        log_prompt "Enter your domain name (e.g., example.com):"
        read -r -p "> " CADDY_DOMAIN < /dev/tty
        
        if [ -z "$CADDY_DOMAIN" ]; then
            log_warn "No domain entered, using localhost"
            CADDY_LIVE_MODE="no"
            CADDY_DOMAIN="localhost"
        fi
        
        log_prompt "Enter your email for TLS certificate:"
        read -r -p "> " TLS_EMAIL < /dev/tty
        TLS_EMAIL="${TLS_EMAIL:-admin@$CADDY_DOMAIN}"
    else
        CADDY_LIVE_MODE="no"
        CADDY_DOMAIN="localhost"
        TLS_EMAIL=""
    fi
    
    # Sandbox mode is always docker in this mode
    echo ""
    log_step "Sandbox Configuration"
    log_info "Sandboxes will run in Docker containers for isolation."
    SANDBOX_MODE="docker"
    log_success "Sandbox mode: docker (containers)"
    
    # Database configuration
    echo ""
    log_step "Database Configuration"
    log_info "MariaDB will be installed on this system."
    echo ""
    
    DB_NAME="${DB_NAME:-ginto}"
    DB_USER="${DB_USER:-ginto}"
    
    # Generate random password by default
    local DEFAULT_PASS=$(openssl rand -base64 16 2>/dev/null | tr -dc 'a-zA-Z0-9' | head -c 16)
    
    log_prompt "Enter database password (or press Enter for random):"
    read -r -s -p "> " DB_PASS < /dev/tty
    echo ""
    
    if [ -z "$DB_PASS" ]; then
        DB_PASS="$DEFAULT_PASS"
        log_info "Generated random password: $DB_PASS"
    fi
    
    DB_ROOT_PASS=$(openssl rand -base64 16 2>/dev/null | tr -dc 'a-zA-Z0-9' | head -c 16)
    
    save_config
    log_success "Configuration saved"
}

# Install Docker Engine
install_docker_engine() {
    log_step "Checking Docker installation..."
    
    if command -v docker &>/dev/null; then
        local docker_version=$(docker --version 2>/dev/null | awk '{print $3}' | tr -d ',')
        log_info "Docker already installed: $docker_version"
        
        # Ensure user is in docker group
        if ! groups "$INSTALL_USER" | grep -q docker; then
            sudo usermod -aG docker "$INSTALL_USER"
            log_info "Added $INSTALL_USER to docker group"
            log_warn "You may need to log out and back in for group changes to take effect"
        fi
        return 0
    fi
    
    log_info "Installing Docker Engine..."
    
    case $OS in
        ubuntu|debian)
            # Remove old versions
            sudo apt-get remove -y docker docker-engine docker.io containerd runc 2>/dev/null || true
            
            # Install prerequisites
            sudo apt-get install -y \
                ca-certificates \
                curl \
                gnupg \
                lsb-release
            
            # Add Docker's official GPG key
            sudo install -m 0755 -d /etc/apt/keyrings
            curl -fsSL https://download.docker.com/linux/$OS/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg 2>/dev/null || true
            sudo chmod a+r /etc/apt/keyrings/docker.gpg
            
            # Add Docker repository
            echo \
                "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/$OS \
                $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
                sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
            
            # Install Docker
            sudo apt-get update -qq
            sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
            ;;
        fedora)
            sudo dnf install -y dnf-plugins-core
            sudo dnf config-manager --add-repo https://download.docker.com/linux/fedora/docker-ce.repo
            sudo dnf install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
            ;;
        *)
            log_error "Unsupported OS for Docker installation: $OS"
            log_info "Please install Docker manually: https://docs.docker.com/engine/install/"
            exit 1
            ;;
    esac
    
    # Start and enable Docker
    sudo systemctl enable docker
    sudo systemctl start docker
    
    # Add user to docker group
    sudo usermod -aG docker "$INSTALL_USER"
    
    # Add www-data to docker group (for PHP/web sandbox creation)
    if id www-data &>/dev/null; then
        sudo usermod -aG docker www-data
        log_info "Added www-data to docker group for web sandbox access"
        # Restart PHP-FPM to pick up group change
        sudo systemctl restart php*-fpm 2>/dev/null || true
    fi
    
    log_success "Docker installed: $(docker --version)"
    log_warn "You may need to log out and back in for docker group membership"
}

# Install Docker Compose (standalone or plugin)
install_docker_compose() {
    log_step "Checking Docker Compose..."
    
    # Check for plugin version first (Docker Compose V2)
    if docker compose version &>/dev/null 2>&1; then
        log_info "Docker Compose (plugin) already available: $(docker compose version --short 2>/dev/null)"
        return 0
    fi
    
    # Check for standalone version
    if command -v docker-compose &>/dev/null; then
        log_info "Docker Compose (standalone) already installed: $(docker-compose --version)"
        return 0
    fi
    
    log_info "Installing Docker Compose plugin..."
    
    case $OS in
        ubuntu|debian)
            sudo apt-get install -y docker-compose-plugin
            ;;
        fedora)
            sudo dnf install -y docker-compose-plugin
            ;;
        *)
            # Fallback: install standalone binary
            local COMPOSE_VERSION=$(curl -s https://api.github.com/repos/docker/compose/releases/latest | grep '"tag_name"' | cut -d'"' -f4)
            COMPOSE_VERSION="${COMPOSE_VERSION:-v2.24.0}"
            sudo curl -L "https://github.com/docker/compose/releases/download/${COMPOSE_VERSION}/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
            sudo chmod +x /usr/local/bin/docker-compose
            ;;
    esac
    
    log_success "Docker Compose installed"
}

# Setup Docker environment file
setup_docker_env() {
    log_step "Setting up Docker environment..."
    
    local env_file="$PROJECT_DIR/.env"
    
    # Create .env from example if needed
    if [ ! -f "$env_file" ]; then
        if [ -f "$PROJECT_DIR/docker/.env.example" ]; then
            cp "$PROJECT_DIR/docker/.env.example" "$env_file"
        else
            touch "$env_file"
        fi
    fi
    
    # Helper to update env var
    update_env_var() {
        local key="$1"
        local value="$2"
        if grep -q "^${key}=" "$env_file" 2>/dev/null; then
            sed -i "s|^${key}=.*|${key}=${value}|" "$env_file"
        else
            echo "${key}=${value}" >> "$env_file"
        fi
    }
    
    # Core database settings
    update_env_var "DB_TYPE" "mysql"
    update_env_var "DB_HOST" "mariadb"
    update_env_var "DB_PORT" "3306"
    update_env_var "DB_NAME" "$DB_NAME"
    update_env_var "DB_USER" "$DB_USER"
    update_env_var "DB_PASS" "$DB_PASS"
    update_env_var "DB_ROOT_PASS" "$DB_ROOT_PASS"
    
    # Docker-specific settings
    update_env_var "REDIS_HOST" "redis"
    update_env_var "REDIS_PORT" "6379"
    update_env_var "SANDBOX_MODE" "$SANDBOX_MODE"
    update_env_var "INSTALL_MODE" "docker"
    update_env_var "DOCKER_MODE" "true"
    
    # Domain/TLS settings
    update_env_var "CADDY_DOMAIN" "$CADDY_DOMAIN"
    update_env_var "TLS_EMAIL" "$TLS_EMAIL"
    
    if [[ "$CADDY_LIVE_MODE" == "yes" ]]; then
        update_env_var "APP_URL" "https://$CADDY_DOMAIN"
        update_env_var "HTTP_PORT" "80"
        update_env_var "HTTPS_PORT" "443"
        # Default mail-from address for live installs
        if ! grep -q "^MAIL_FROM=" "$env_file" 2>/dev/null; then
            update_env_var "MAIL_FROM" "no-reply@$CADDY_DOMAIN"
        fi
    else
        update_env_var "APP_URL" "http://localhost"
        update_env_var "HTTP_PORT" "80"
        update_env_var "HTTPS_PORT" "443"
    fi
    
    update_env_var "APP_ENV" "production"
    update_env_var "APP_DEBUG" "false"
    
    # Set ownership
    chown "$INSTALL_USER:$INSTALL_USER" "$env_file"
    # Use 666 for Docker mode so PHP container (www-data) can read AND write
    chmod 666 "$env_file"
    
    # Make entire project writable for Docker's www-data user
    # The volume mount preserves host permissions, so we need world-writable
    chmod -R a+w "$PROJECT_DIR"
    
    log_success "Docker environment configured in .env"

    # Pre-create the external Docker network that docker-compose.yml declares as external
    if ! docker network inspect ginto-sandbox &>/dev/null 2>&1; then
        log_info "Creating Docker network: ginto-sandbox"
        docker network create ginto-sandbox
    fi
}

# Build Docker images
build_docker_images() {
    log_step "Building Docker images..."
    
    cd "$PROJECT_DIR"
    
    # Use docker compose (plugin) or docker-compose (standalone)
    local COMPOSE_CMD="docker compose"
    if ! docker compose version &>/dev/null 2>&1; then
        COMPOSE_CMD="docker-compose"
    fi
    
    # Build images - use cache to speed up rebuilds
    # Only rebuild layers that have changed
    log_info "Building containers (using cache if available)..."
    $COMPOSE_CMD build
    
    log_success "Docker images built successfully"
}

# Start Docker services
start_docker_services() {
    log_step "Starting Docker services..."
    
    cd "$PROJECT_DIR"
    
    # CRITICAL: Ensure project is writable by Docker's www-data user EVERY TIME
    # This must run before containers start, even on re-runs
    log_info "Setting project permissions for Docker..."
    chmod -R a+w "$PROJECT_DIR"
    chmod 666 "$PROJECT_DIR/.env" 2>/dev/null || true
    
    local COMPOSE_CMD="docker compose"
    if ! docker compose version &>/dev/null 2>&1; then
        COMPOSE_CMD="docker-compose"
    fi
    
    # Start infrastructure services first (mariadb, redis) and wait for them
    # Use --force-recreate to ensure config changes (like healthchecks) are applied
    log_info "Starting database and cache services..."
    $COMPOSE_CMD up -d --force-recreate mariadb redis || true
    
    # Give containers a moment to initialize
    sleep 3
    
    # Wait for mariadb to be healthy (up to 120 seconds)
    log_info "Waiting for MariaDB to be ready..."
    local max_attempts=60
    local attempt=0
    while [ $attempt -lt $max_attempts ]; do
        # Check container health status directly
        local health_status
        health_status=$(docker inspect --format='{{.State.Health.Status}}' ginto-mariadb 2>/dev/null) || health_status="starting"
        if [ "$health_status" == "healthy" ]; then
            echo ""  # New line after dots
            log_success "MariaDB is ready"
            break
        fi
        # Also check if container is running but no healthcheck yet
        if docker exec ginto-mariadb mariadb-admin ping -h localhost 2>/dev/null | grep -q "alive"; then
            echo ""  # New line after dots
            log_success "MariaDB is ready (ping successful)"
            break
        fi
        ((attempt++)) || true
        # Show progress
        printf "."
        sleep 2
    done
    
    if [ $attempt -eq $max_attempts ]; then
        echo ""  # New line after dots
        log_warn "MariaDB healthcheck timeout - continuing anyway..."
    fi
    
    # Now start remaining services with force-recreate
    log_info "Starting application services..."
    $COMPOSE_CMD up -d --force-recreate || true
    
    # Wait for all services to be ready
    log_info "Waiting for all services to initialize..."
    sleep 5
    
    # Verify all containers are running
    local not_running=$($COMPOSE_CMD ps --format '{{.Name}} {{.Status}}' 2>/dev/null | grep -v "Up" | grep -v "^$" || true)
    if [ -n "$not_running" ]; then
        log_warn "Some containers may not be running yet. Retrying..."
        $COMPOSE_CMD up -d
        sleep 5
    fi
    
    # Check service status
    $COMPOSE_CMD ps
    
    log_success "Docker services started"
}

# Print Docker mode summary (new architecture: host stack + Docker sandboxes)
print_docker_summary() {
    echo ""
    echo -e "${GREEN}============================================${NC}"
    echo -e "${GREEN}  Ginto AI Installation Complete!${NC}"
    echo -e "${GREEN}============================================${NC}"
    echo ""
    echo "Main stack (running on host):"
    echo "  - PHP-FPM:  $(php-fpm8.3 -v 2>/dev/null | head -1 || echo 'checking...')"
    echo "  - MariaDB:  $(mysql --version 2>/dev/null | cut -d' ' -f1-5 || echo 'checking...')"
    echo "  - Caddy:    $(caddy version 2>/dev/null | head -1 || echo 'checking...')"
    echo "  - Redis:    $(redis-server --version 2>/dev/null | cut -d' ' -f1-3 || echo 'checking...')"
    echo ""
    echo "Sandbox services (Docker containers):"
    
    cd "$PROJECT_DIR"
    local COMPOSE_CMD="docker compose"
    if ! docker compose version &>/dev/null 2>&1; then
        COMPOSE_CMD="docker-compose"
    fi
    $COMPOSE_CMD ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}" 2>/dev/null || $COMPOSE_CMD ps
    
    # Read domain from .env if not set
    local site_domain="${CADDY_DOMAIN:-}"
    local live_mode="${CADDY_LIVE_MODE:-}"
    if [ -z "$site_domain" ] && [ -f "$PROJECT_DIR/.env" ]; then
        site_domain=$(grep -E '^CADDY_DOMAIN=' "$PROJECT_DIR/.env" 2>/dev/null | cut -d'=' -f2- | tr -d '"' | tr -d "'" || echo "")
        local app_url=$(grep -E '^APP_URL=' "$PROJECT_DIR/.env" 2>/dev/null | cut -d'=' -f2- | tr -d '"' | tr -d "'" || echo "")
        if [[ "$app_url" == https://* ]]; then
            live_mode="yes"
        fi
    fi
    
    echo ""
    echo "Configuration:"
    echo "  - Mode: Docker Sandbox (host + containers)"
    echo "  - Domain: ${site_domain:-localhost}"
    echo "  - Database: $DB_NAME (user: $DB_USER)"
    echo "  - Sandbox Mode: docker"
    echo ""
    local server_ip=$(get_server_ip)
    echo "Access your site:"
    if [ -n "$site_domain" ] && [ "$site_domain" != "localhost" ]; then
        echo "  - Website: https://$site_domain"
        if [ "$server_ip" != "unknown" ]; then
            echo "  - Server IP: $server_ip"
        fi
    elif [[ "$live_mode" == "yes" ]]; then
        echo "  - HTTPS: https://$site_domain"
    else
        echo "  - HTTP: http://localhost"
        if [ "$server_ip" != "unknown" ]; then
            echo "  - Network: http://$server_ip"
        fi
    fi
    echo ""
    echo "System commands:"
    echo "  - Start app:     sudo systemctl start ginto"
    echo "  - Stop app:      sudo systemctl stop ginto"
    echo "  - App logs:      journalctl -u ginto -f"
    echo "  - App status:    sudo systemctl status ginto"
    echo ""
    echo "Sandbox commands:"
    echo "  - Start:   docker compose up -d"
    echo "  - Stop:    docker compose down"
    echo "  - Logs:    docker compose logs -f"
    echo "  - Status:  docker compose ps"
    echo ""
    echo "Database access:"
    echo "  - mysql -u $DB_USER -p $DB_NAME"
    echo ""
}

# Build sandbox images only (for new architecture)
build_sandbox_images() {
    log_step "Building Docker sandbox images..."
    
    cd "$PROJECT_DIR"
    
    # Use docker compose (plugin) or docker-compose (standalone)
    local COMPOSE_CMD="docker compose"
    if ! docker compose version &>/dev/null 2>&1; then
        COMPOSE_CMD="docker-compose"
    fi
    
    # Build sandbox-related images only
    log_info "Building sandbox containers..."
    $COMPOSE_CMD build sandbox-proxy terminal-server sandbox-manager 2>/dev/null || {
        log_info "Building all containers in docker-compose.yml..."
        $COMPOSE_CMD build
    }
    
    # Build the base sandbox image if Dockerfile exists
    if [ -f "$PROJECT_DIR/docker/sandbox/Dockerfile" ]; then
        log_info "Building base sandbox image..."
        docker build -t ginto-sandbox:latest -f docker/sandbox/Dockerfile docker/sandbox/ || true
    fi
    
    log_success "Sandbox images built successfully"
}

# Start sandbox services only (main stack runs on host)
start_sandbox_services() {
    log_step "Starting Docker sandbox services..."

    cd "$PROJECT_DIR"

    local COMPOSE_CMD="docker compose"
    if ! docker compose version &>/dev/null 2>&1; then
        COMPOSE_CMD="docker-compose"
    fi

    # Ensure the external Docker network exists before compose tries to attach to it
    if ! docker network inspect ginto-sandbox &>/dev/null 2>&1; then
        log_info "Creating Docker network: ginto-sandbox"
        docker network create ginto-sandbox
    fi

    # Stop and remove any existing containers to avoid port conflicts
    log_info "Cleaning up any existing sandbox containers..."
    $COMPOSE_CMD down --remove-orphans 2>/dev/null || true

    # Also remove any old containers that might be using our ports
    docker rm -f ginto-sandbox-proxy ginto-terminal-server ginto-sandbox-manager 2>/dev/null || true

    # Kill any non-Docker processes still holding sandbox ports
    for port in "${SANDBOX_PROXY_PORT:-3000}" "${TERMINAL_SERVER_PORT:-3001}"; do
        local pids
        pids=$(lsof -t -i TCP:"$port" -sTCP:LISTEN 2>/dev/null || true)
        if [ -n "$pids" ]; then
            log_info "Port $port still in use by PID(s) $pids — killing..."
            # shellcheck disable=SC2086
            kill -9 $pids 2>/dev/null || true
            sleep 1
        fi
    done

    # Start sandbox services
    log_info "Starting sandbox proxy and terminal server..."
    $COMPOSE_CMD up -d
    
    # Wait for services to be ready
    sleep 3
    
    # Check service status
    $COMPOSE_CMD ps
    
    log_success "Sandbox services started"
    echo ""
    echo "Sandbox services:"
    echo "  - Sandbox Proxy:     http://localhost:${SANDBOX_PROXY_PORT:-3000}"
    echo "  - Terminal Server:   ws://localhost:${TERMINAL_SERVER_PORT:-3001}"
    echo ""
}

#===============================================================================
# END DOCKER MODE FUNCTIONS
#===============================================================================

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

# Detect existing Docker installation
detect_existing_docker_installation() {
    # Check if docker-compose.yml exists and containers are present
    if [ -f "$PROJECT_DIR/docker-compose.yml" ] && [ -f "$PROJECT_DIR/.env" ]; then
        # Check if any ginto containers exist (running or stopped)
        if docker compose -f "$PROJECT_DIR/docker-compose.yml" ps -a 2>/dev/null | grep -q "ginto"; then
            return 0  # Docker installation exists
        fi
    fi
    return 1  # No Docker installation
}

# Ask all interactive questions upfront (Ollama-style)
prompt_configuration() {
    # Shared tty-availability check, computed once, reused by every prompt below
    # instead of only the first one. This was the actual bug: only reinstall_choice
    # checked for a usable /dev/tty; every prompt after it (domain, TLS email, DB
    # creds, llama.cpp) did a bare `read ... < /dev/tty` with no guard, so in a
    # non-interactive session those failed outright under `set -e` and killed the
    # script silently instead of falling back to a sane default or env var.
    local HAVE_TTY=false
    if [ -t 0 ] && [ -r /dev/tty ] && (echo -n "" > /dev/tty) 2>/dev/null; then
        HAVE_TTY=true
    fi

    # Small helper: prompt interactively if we have a tty, otherwise use the
    # given env var (if set) or fall back to a default. Centralizes the pattern
    # instead of repeating a tty check + read + fallback at every call site.
    prompt_or_env() {
        local prompt_text="$1" env_value="$2" default_value="$3" is_secret="${4:-false}"
        local result=""
        if [ -n "$env_value" ]; then
            echo "$result_from_env" >/dev/null 2>&1  # no-op, keeps shellcheck quiet
            printf '%s' "$env_value"
            return 0
        fi
        if $HAVE_TTY; then
            log_prompt "$prompt_text" >&2   # <-- was: log_prompt "$prompt_text"
            if [[ "$is_secret" == "true" ]]; then
                read -r -s -p "> " result < /dev/tty
                echo "" >&2
            else
                read -r -p "> " result < /dev/tty
            fi
            printf '%s' "${result:-$default_value}"
        else
            printf '%s' "$default_value"
        fi
    }

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

        local reinstall_choice="${GINTO_UPDATE_MODE:-}"
        if [ -z "$reinstall_choice" ]; then
            if $HAVE_TTY; then
                echo "  1) Update only     - Pull changes, run migrations (recommended)"
                echo "  2) Fresh install   - Remove all and reinstall from scratch"
                echo ""
                log_prompt "Choose option (1-2) [default: 1]:"
                read -r -p "> " reinstall_choice < /dev/tty
            else
                log_info "Non-interactive mode detected, defaulting to update..."
                log_info "To force a fresh install (needed to change domain/email) without a tty, run:"
                log_info "  GINTO_UPDATE_MODE=2 GINTO_DOMAIN=example.com GINTO_TLS_EMAIL=admin@example.com ./run.sh install"
                reinstall_choice="1"
            fi
        else
            log_info "Using GINTO_UPDATE_MODE=$reinstall_choice"
        fi

        if [[ "$reinstall_choice" == "2" ]]; then
            log_warn "Fresh install selected - clearing all checkpoints..."
            clear_checkpoint

            # Capture the OLD db name/user before we wipe .env, so we can drop
            # them from MariaDB below.
            local OLD_DB_NAME="" OLD_DB_USER=""
            if [ -f "$PROJECT_DIR/.env" ]; then
                OLD_DB_NAME=$(grep -E '^DB_NAME=' "$PROJECT_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'" || echo "")
                OLD_DB_USER=$(grep -E '^DB_USER=' "$PROJECT_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'" || echo "")
            fi

            # Actually wipe .env so setup_env() creates a clean one from
            # .env.example instead of patching a handful of keys inside the
            # old file (which left stale/unrelated config behind).
            if [ -f "$PROJECT_DIR/.env" ]; then
                local backup_env="$PROJECT_DIR/.env.bak.$(date +%Y%m%d_%H%M%S)"
                cp "$PROJECT_DIR/.env" "$backup_env"
                rm -f "$PROJECT_DIR/.env"
                log_info "Backed up old .env to $(basename "$backup_env") and removed it"
            fi
            rm -f "$PROJECT_DIR/.legacy-install"

            # Drop the old MariaDB database/user. CREATE USER IF NOT EXISTS
            # later on will silently no-op if this user already exists —
            # including skipping the password — which previously left .env
            # and MariaDB's actual credentials out of sync after every fresh
            # install. Dropping first guarantees the next CREATE USER actually
            # creates the user with the new password.
            if [ -n "$OLD_DB_NAME" ] && command -v mysql &>/dev/null; then
                log_warn "Dropping old MariaDB database '$OLD_DB_NAME' and user '${OLD_DB_USER:-ginto}'@'localhost'..."
                sudo mysql -e "DROP DATABASE IF EXISTS \`${OLD_DB_NAME}\`; DROP USER IF EXISTS '${OLD_DB_USER:-ginto}'@'localhost'; FLUSH PRIVILEGES;" 2>/dev/null \
                    && log_success "Old database and user dropped" \
                    || log_warn "Could not drop old database/user (may not exist yet, or MariaDB not running) — continuing"
            fi

            SKIP_CONFIGURED=false
            # Falls through to the full configuration flow below. clear_checkpoint
            # already removed CHECKPOINT_CONFIG so the load_config short-circuit
            # further down won't fire — but we don't rely on that alone; see the
            # explicit `return` removal note below.
        else
            log_info "Checking file permissions..."
            fix_permissions
            log_success "Permissions verified"
            log_info "Skipping most configuration prompts - will only update, reconfirm Caddy, and run migrations"

            if [ -f "$PROJECT_DIR/.env" ]; then
                DB_NAME=$(grep -E '^DB_NAME=' "$PROJECT_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'" || echo "ginto")
                DB_USER=$(grep -E '^DB_USER=' "$PROJECT_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'" || echo "ginto")
                DB_PASS=$(grep -E '^DB_PASS=' "$PROJECT_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")
                SANDBOX_MODE_ENV=$(grep -E '^SANDBOX_MODE=' "$PROJECT_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'" || echo "")
                [ -z "$DB_NAME" ] && DB_NAME="ginto"
                [ -z "$DB_USER" ] && DB_USER="ginto"
            else
                DB_NAME="ginto"; DB_USER="ginto"; DB_PASS=""; SANDBOX_MODE_ENV=""
            fi

            if ! command -v docker &>/dev/null; then
                echo ""
                log_step "Docker Installation"
                echo ""
                echo "  Docker is not installed. Docker is required for:"
                echo "    - Docker-based sandboxes (isolated code execution)"
                echo "    - Running user code in containers"
                echo ""

                local docker_choice="${GINTO_DOCKER_CHOICE:-}"
                if [ -z "$docker_choice" ]; then
                    if $HAVE_TTY; then
                        echo "  1) Install Docker now (recommended for production)"
                        echo "  2) Skip - I don't need Docker sandboxes"
                        echo ""
                        log_prompt "Choose option (1-2) [default: 1]:"
                        read -r -p "> " docker_choice < /dev/tty
                    else
                        log_info "Non-interactive mode - will attempt to install Docker..."
                        docker_choice="1"
                    fi
                fi

                if [[ "$docker_choice" != "2" ]]; then
                    if [ -z "${OS:-}" ]; then
                        if [ -f /etc/os-release ]; then
                            . /etc/os-release
                            OS=$ID
                            OS_VERSION=$VERSION_ID
                        fi
                    fi
                    install_docker_engine
                    install_docker_compose

                    if [ -f "$PROJECT_DIR/.env" ]; then
                        if ! grep -q "^SANDBOX_MODE=" "$PROJECT_DIR/.env"; then
                            echo "SANDBOX_MODE=docker" >> "$PROJECT_DIR/.env"
                            log_info "Added SANDBOX_MODE=docker to .env"
                        elif ! grep -q "^SANDBOX_MODE=docker" "$PROJECT_DIR/.env"; then
                            sed -i 's/^SANDBOX_MODE=.*/SANDBOX_MODE=docker/' "$PROJECT_DIR/.env"
                            log_info "Updated SANDBOX_MODE=docker in .env"
                        fi
                    fi

                    log_step "Checking sandbox image..."
                    if ! docker images | grep -q "ginto-sandbox"; then
                        log_info "Building sandbox image..."
                        build_sandbox_images 2>/dev/null || log_warn "Sandbox image build skipped (run manually if needed)"
                    else
                        log_success "Sandbox image already exists"
                    fi
                    log_success "Docker installation complete!"
                else
                    log_info "Skipping Docker installation."
                    log_info "To install Docker later: curl -fsSL https://get.docker.com | sh"
                fi
            else
                local docker_version=$(docker --version 2>/dev/null | awk '{print $3}' | tr -d ',')
                log_info "Docker already installed: $docker_version"

                if ! groups "$INSTALL_USER" | grep -q docker; then
                    log_info "Adding $INSTALL_USER to docker group..."
                    sudo usermod -aG docker "$INSTALL_USER"
                    log_warn "You may need to log out and back in for docker group membership to take effect"
                fi

                if id www-data &>/dev/null && ! groups www-data | grep -q docker; then
                    log_info "Adding www-data to docker group for web sandbox access..."
                    sudo usermod -aG docker www-data
                    sudo systemctl restart php*-fpm 2>/dev/null || true
                    log_success "www-data added to docker group"
                fi

                if [ -f "$PROJECT_DIR/.env" ]; then
                    if ! grep -q "^SANDBOX_MODE=" "$PROJECT_DIR/.env"; then
                        echo "SANDBOX_MODE=docker" >> "$PROJECT_DIR/.env"
                        log_info "Added SANDBOX_MODE=docker to .env"
                    fi
                fi
            fi

            SDCPU_DIR="$PROJECT_DIR/tools/sdcpu"
            if [ -f "$SDCPU_DIR/requirements.txt" ] && [ -f "$SDCPU_DIR/src/api_server.py" ]; then
                if [ ! -d "$SDCPU_DIR/venv" ] || [ ! -f "$SDCPU_DIR/venv/bin/python" ]; then
                    echo ""
                    log_step "SDCPU Installation (AI Image Generation)"
                    echo ""
                    echo "  SDCPU is an AI image generation tool that runs on CPU."
                    echo "  It requires ~2GB of disk space and takes a few minutes to install."
                    echo ""

                    local sdcpu_choice="${GINTO_SDCPU_CHOICE:-}"
                    if [ -z "$sdcpu_choice" ]; then
                        if $HAVE_TTY; then
                            echo "  1) Install SDCPU now"
                            echo "  2) Skip - I don't need AI image generation"
                            echo ""
                            log_prompt "Choose option (1-2) [default: 2]:"
                            read -r -p "> " sdcpu_choice < /dev/tty
                        else
                            log_info "Non-interactive mode - skipping SDCPU..."
                            sdcpu_choice="2"
                        fi
                    fi

                    if [[ "$sdcpu_choice" == "1" ]]; then
                        install_sdcpu
                        configure_sdcpu_service
                        log_success "SDCPU installation complete!"
                    else
                        log_info "Skipping SDCPU installation."
                        log_info "To install SDCPU later: Run './run.sh install' and choose option 1 for SDCPU"
                    fi
                else
                    log_info "SDCPU already installed (venv exists)"
                fi
            fi

            # --- Caddy domain/email: always offer to (re)configure, even in
            # update mode, instead of hardcoding it to "skip". Pre-filled with
            # whatever is currently in .env so hitting Enter just keeps it.
            echo ""
            log_step "Caddy Domain Configuration"
            local existing_domain="" existing_email="" existing_mode="1"
            if [ -f "$PROJECT_DIR/.env" ]; then
                existing_domain=$(grep -E '^CADDY_DOMAIN=' "$PROJECT_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'" || echo "")
                existing_email=$(grep -E '^TLS_EMAIL=' "$PROJECT_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'" || echo "")
                [ -z "$existing_email" ] && existing_email=$(grep -E '^MAIL_FROM=' "$PROJECT_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'" || echo "")
            fi
            [ -n "$existing_domain" ] && [ "$existing_domain" != "localhost" ] && existing_mode="2"

            local server_choice
            server_choice=$(prompt_or_env "Choose server mode (1-2) [default: ${existing_mode}] (current: ${existing_domain:-localhost}):
  1) Local     - localhost on port 80 (development)
  2) Live      - Configure domain with HTTPS (production)" "${GINTO_SERVER_MODE:-}" "$existing_mode")

            if [[ "$server_choice" == "2" ]]; then
                CADDY_LIVE_MODE="yes"
                CADDY_DOMAIN=$(prompt_or_env "Enter your domain name (current: ${existing_domain:-none}):" "${GINTO_DOMAIN:-}" "${existing_domain}")
                if [ -z "$CADDY_DOMAIN" ]; then
                    log_warn "No domain entered, keeping Caddy on localhost"
                    CADDY_LIVE_MODE="no"
                    CADDY_DOMAIN="localhost"
                    CADDY_TLS_EMAIL=""
                else
                    CADDY_TLS_EMAIL=$(prompt_or_env "Enter your email for TLS certificate (current: ${existing_email:-admin@$CADDY_DOMAIN}):" "${GINTO_TLS_EMAIL:-}" "${existing_email:-admin@$CADDY_DOMAIN}")
                fi
            else
                CADDY_LIVE_MODE="no"
                CADDY_DOMAIN="localhost"
                CADDY_TLS_EMAIL=""
            fi

            # SKIP_CONFIGURED still short-circuits everything else in update
            # mode (DB setup, systemd service, etc). FORCE_CADDY_RECONFIGURE
            # is a separate flag so configure_caddy()/setup_env() know to
            # actually apply the domain/email we just confirmed above instead
            # of skipping like they normally would when SKIP_CONFIGURED=true.
            SKIP_CONFIGURED=true
            SKIP_DB_USER_SETUP=true
            FORCE_CADDY_RECONFIGURE=true

            save_checkpoint "install_dependencies"
            # Explicit return — this is the ONLY branch that should skip the
            # DB prompts below. The fresh-install branch above falls through
            # intentionally; it must NOT hit this return.
            return 0
        fi
    fi

    # Only reachable via: no existing install, OR "fresh install" was chosen above.
    # Guard against a stale saved config short-circuiting a deliberate fresh
    # install: if the user explicitly chose "2) Fresh install", skip the resume
    # check entirely even if a config file somehow survived clear_checkpoint.
    if [ -z "${_GINTO_FORCE_FRESH_PROMPTS:-}" ] && load_config 2>/dev/null; then
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

    # Caddy mode — env vars let this run fully non-interactively:
    #   GINTO_SERVER_MODE=2 GINTO_DOMAIN=example.com GINTO_TLS_EMAIL=admin@example.com
    local server_choice
    server_choice=$(prompt_or_env "Choose server mode (1-2) [default: 1]:
  1) Local     - localhost on port 80 (development)
  2) Live      - Configure domain with HTTPS (production)" "${GINTO_SERVER_MODE:-}" "1")

    if [[ "$server_choice" == "2" ]]; then
        CADDY_LIVE_MODE="yes"
        CADDY_DOMAIN=$(prompt_or_env "Enter your domain name (e.g., example.com):" "${GINTO_DOMAIN:-}" "")
        if [ -z "$CADDY_DOMAIN" ]; then
            log_error "Domain name is required for live server mode (set GINTO_DOMAIN if running non-interactively)"
            exit 1
        fi
        CADDY_TLS_EMAIL=$(prompt_or_env "Enter your email for TLS certificate (e.g., admin@$CADDY_DOMAIN):" "${GINTO_TLS_EMAIL:-}" "admin@$CADDY_DOMAIN")
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

    DB_NAME=$(prompt_or_env "Enter database name (default: ginto):" "${GINTO_DB_NAME:-}" "ginto")
    DB_USER=$(prompt_or_env "Enter database username (default: ginto):" "${GINTO_DB_USER:-}" "ginto")

    if [[ "$DB_USER" == "root" ]]; then
        log_warn "Using 'root' is not recommended for application access."
        if $HAVE_TTY && [ -z "${GINTO_DB_USER:-}" ]; then
            echo "  1) Change username (recommended)"
            echo "  2) Keep 'root' anyway"
            echo ""
            log_prompt "Choose option (1-2) [default: 1]:"
            read -r -p "> " root_choice < /dev/tty
            if [[ "$root_choice" != "2" ]]; then
                log_prompt "Enter database username:"
                read -r -p "> " DB_USER < /dev/tty
                DB_USER="${DB_USER:-ginto}"
            fi
        else
            log_warn "Non-interactive/env-provided 'root' — keeping as-is. Set GINTO_DB_USER to override."
        fi
    fi

    DB_PASS="${GINTO_DB_PASS:-}"
    if [ -z "$DB_PASS" ]; then
        if $HAVE_TTY; then
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
        else
            log_error "No tty available and GINTO_DB_PASS not set — cannot prompt for a database password."
            log_error "Re-run with: GINTO_DB_PASS='yourpassword' ./run.sh install ..."
            exit 1
        fi
    fi

    log_success "Database configuration: $DB_NAME with user '$DB_USER'"

    # llama.cpp configuration
    echo ""
    log_step "llama.cpp Configuration"

    local LLAMACPP_BIN="$INSTALL_USER_HOME/llama.cpp/build/bin"
    if [ -f "$LLAMACPP_BIN/llama-server" ] || [ -f "$LLAMACPP_BIN/llama-cli" ]; then
        log_info "llama.cpp already installed at $INSTALL_USER_HOME/llama.cpp"
        LLAMACPP_MODE="skip"
        log_success "Skipping llama.cpp installation (already installed)"
    else
        log_info "llama.cpp provides local LLM inference for offline AI."
        local llamacpp_choice
        llamacpp_choice=$(prompt_or_env "Choose llama.cpp option (1-3) [default: 2]:
  1) Download only        - I'll compile it myself
  2) Download and compile - Maximum native support (may take time)
  3) Skip                 - Don't install llama.cpp" "${GINTO_LLAMACPP_MODE:-}" "2")

        case "${llamacpp_choice}" in
            1|download) LLAMACPP_MODE="download" ;;
            3|skip)     LLAMACPP_MODE="skip" ;;
            *)          LLAMACPP_MODE="compile" ;;
        esac
        log_success "Selected llama.cpp option: $LLAMACPP_MODE"
    fi

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
    # install_llamacpp included because it was added later and checkpoints may skip it
    # NOTE: install_sdcpu is handled interactively in update mode, not auto-run
    local ALWAYS_RUN_STEPS=("setup_permissions" "install_utilities" "install_llamacpp")
    
    for step in "${INSTALL_STEPS[@]}"; do
        # Honor --skip / GINTO_SKIP_STEPS (overrides always-run and resume)
        if is_step_skipped "$step"; then
            log_warn "Skipping (--skip requested): $step"
            continue
        fi
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

# Docker mode installation function
do_docker_install() {
    # CRITICAL: Set INSTALL_MODE at the very start so all functions know we're in Docker mode
    INSTALL_MODE="docker"
    
    echo ""
    echo -e "${CYAN}╔════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║   Ginto AI - Docker Installation       ║${NC}"
    echo -e "${CYAN}╚════════════════════════════════════════╝${NC}"
    echo ""
    
    log_info "Running as user: $INSTALL_USER"
    log_info "Installation mode: Docker"
    
    # Check for existing Docker installation
    if detect_existing_docker_installation; then
        log_step "Existing Docker installation detected"
        echo ""
        
        # Show container status
        log_info "Current container status:"
        docker compose -f "$PROJECT_DIR/docker-compose.yml" ps -a 2>/dev/null || true
        echo ""
        
        # Check for non-interactive mode (env var or no tty)
        local update_choice="${GINTO_UPDATE_MODE:-}"
        if [ -z "$update_choice" ]; then
            # Test if we can actually read from /dev/tty
            if [ -t 0 ] && [ -r /dev/tty ] && (echo -n "" > /dev/tty) 2>/dev/null; then
                # Interactive mode - prompt user
                echo "  1) Update only     - Pull latest images, restart services (recommended)"
                echo "  2) Fresh install   - Remove all containers/volumes and reinstall"
                echo ""
                log_prompt "Choose option (1-2) [default: 1]:"
                read -r -p "> " update_choice < /dev/tty
            else
                # Non-interactive mode - default to update
                log_info "Non-interactive mode detected, defaulting to update..."
                update_choice="1"
            fi
        else
            log_info "Using GINTO_UPDATE_MODE=$update_choice"
        fi
        
        case "${update_choice:-1}" in
            2)
                log_warn "Fresh install selected - removing existing Docker installation..."
                docker compose -f "$PROJECT_DIR/docker-compose.yml" down -v --remove-orphans 2>/dev/null || true
                docker compose -f "$PROJECT_DIR/docker-compose.yml" rm -f 2>/dev/null || true
                
                # Also clear checkpoint
                clear_checkpoint
                log_info "Proceeding with fresh Docker installation..."
                ;;
            *)
                log_info "Update mode - restarting services with latest changes..."
                
                # Prompt for llama.cpp exactly like native mode does
                local LLAMACPP_BIN="$INSTALL_USER_HOME/llama.cpp/build/bin"
                if [ -f "$LLAMACPP_BIN/llama-server" ] || [ -f "$LLAMACPP_BIN/llama-cli" ]; then
                    log_info "llama.cpp already installed at $INSTALL_USER_HOME/llama.cpp"
                    # Set to "installed" so install_llamacpp knows to configure PATH and start models
                    LLAMACPP_MODE="installed"
                else
                    echo ""
                    log_step "llama.cpp Configuration"
                    log_info "llama.cpp provides local LLM inference for offline AI."
                    echo ""
                    echo "  1) Download only        - I'll compile it myself"
                    echo "  2) Download and compile - Maximum native support (may take time)"
                    echo "  3) Skip                 - Don't install llama.cpp"
                    echo ""
                    
                    log_prompt "Choose llama.cpp option (1-3) [default: 2]:"
                    read -r -p "> " llamacpp_choice < /dev/tty
                    
                    case "${llamacpp_choice}" in
                        1|download)
                            LLAMACPP_MODE="download"
                            ;;
                        3|skip)
                            LLAMACPP_MODE="skip"
                            ;;
                        *)
                            LLAMACPP_MODE="compile"
                            ;;
                    esac
                    
                    log_success "Selected llama.cpp option: $LLAMACPP_MODE"
                fi
                
                # Run ALWAYS_RUN_STEPS (permissions, utilities, llama.cpp)
                local ALWAYS_RUN_STEPS=("setup_permissions" "install_utilities" "install_llamacpp")
                for step in "${ALWAYS_RUN_STEPS[@]}"; do
                    log_info "Running: $step"
                    $step
                done
                
                # SDCPU Interactive Installation Check
                SDCPU_DIR="$PROJECT_DIR/tools/sdcpu"
                if [ -f "$SDCPU_DIR/requirements.txt" ] && [ -f "$SDCPU_DIR/src/api_server.py" ]; then
                    # SDCPU source exists - check if already installed
                    if [ ! -d "$SDCPU_DIR/venv" ] || [ ! -f "$SDCPU_DIR/venv/bin/python" ]; then
                        echo ""
                        log_step "SDCPU Installation (AI Image Generation)"
                        echo ""
                        echo "  SDCPU is an AI image generation tool that runs on CPU."
                        echo "  It requires ~2GB of disk space and takes a few minutes to install."
                        echo ""
                        
                        local sdcpu_choice=""
                        if [ -t 0 ] && [ -r /dev/tty ] && (echo -n "" > /dev/tty) 2>/dev/null; then
                            echo "  1) Install SDCPU now"
                            echo "  2) Skip - I don't need AI image generation"
                            echo ""
                            log_prompt "Choose option (1-2) [default: 2]:"
                            read -r -p "> " sdcpu_choice < /dev/tty
                        else
                            log_info "Non-interactive mode - skipping SDCPU..."
                            sdcpu_choice="2"
                        fi
                        
                        if [[ "$sdcpu_choice" == "1" ]]; then
                            install_sdcpu
                            configure_sdcpu_service
                            log_success "SDCPU installation complete!"
                        else
                            log_info "Skipping SDCPU installation."
                            log_info "To install SDCPU later: Run './run.sh install' and choose option 1 for SDCPU"
                        fi
                    else
                        log_info "SDCPU already installed (venv exists)"
                    fi
                fi
                
                # Rebuild and restart Docker services
                docker compose -f "$PROJECT_DIR/docker-compose.yml" build
                docker compose -f "$PROJECT_DIR/docker-compose.yml" up -d --force-recreate
                
                log_success "Docker services updated successfully!"
                local server_ip=$(get_server_ip)
                log_info "Access the web installer at: http://localhost"
                if [ "$server_ip" != "unknown" ]; then
                    log_info "Alternatively access it at: http://$server_ip"
                fi
                return 0
                ;;
        esac
    fi
    
    # Set Docker installation steps
    INSTALL_STEPS=("${INSTALL_STEPS_DOCKER[@]}")
    
    # Check for resume
    local last_checkpoint=$(get_last_checkpoint)
    if [ -n "$last_checkpoint" ]; then
        echo ""
        log_warn "Previous installation was interrupted after: $last_checkpoint"
        log_info "Resuming from where we left off..."
        
        if load_config 2>/dev/null; then
            log_info "Loaded saved configuration"
        fi
        echo ""
    fi
    
    # Run Docker steps
    for step in "${INSTALL_STEPS[@]}"; do
        # Honor --skip / GINTO_SKIP_STEPS (overrides resume)
        if is_step_skipped "$step"; then
            log_warn "Skipping (--skip requested): $step"
            continue
        fi
        if should_run_step "$step" "$last_checkpoint"; then
            $step
            save_checkpoint "$step"
        else
            log_info "Skipping (already done): $step"
        fi
    done
    
    clear_checkpoint
    log_success "Docker installation completed successfully!"
}

# Prompt for installation mode
prompt_install_mode() {
    echo ""
    echo -e "${CYAN}╔════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║     Ginto AI - Installation Wizard     ║${NC}"
    echo -e "${CYAN}╚════════════════════════════════════════╝${NC}"
    echo ""
    log_info "Choose your installation mode:"
    echo ""
    echo "  1) native  - Install directly on system (PHP, MariaDB, Caddy, etc.)"
    echo "               Sandboxes: LXD containers"
    echo "               Best for: Dedicated servers, VPS, production"
    echo ""
    echo "  2) docker  - Install stack on system + Docker sandboxes"
    echo "               Sandboxes: Docker containers"
    echo "               Best for: Systems without LXD, quick sandbox setup"
    echo ""
    log_prompt "Enter mode (1/native or 2/docker) [default: native]:"
    read -r -p "> " mode_choice < /dev/tty
    
    case "${mode_choice,,}" in
        2|docker)
            INSTALL_MODE="docker"
            ;;
        *)
            INSTALL_MODE="native"
            ;;
    esac
    
    log_success "Selected mode: $INSTALL_MODE"
}

install_novnc_service() {
    log_step "Installing/updating noVNC from GitHub and configuring systemd service..."

    check_sudo

    local NOVNC_PARENT_DIR="$INSTALL_USER_HOME/storage"
    local NOVNC_DIR="$NOVNC_PARENT_DIR/novnc"
    local NOVNC_REPO_URL="https://github.com/novnc/noVNC.git"
    local NOVNC_LISTEN_PORT="${NOVNC_LISTEN_PORT:-6080}"
    local NOVNC_VNC_TARGET="${NOVNC_VNC_TARGET:-localhost:5905}"

    # Ensure required tools exist
    if ! command -v git >/dev/null 2>&1; then
        log_info "Installing git..."
        case "$OS" in
            ubuntu|debian)
                sudo apt-get update -qq
                sudo apt-get install -y git
                ;;
            fedora|rhel|centos)
                sudo dnf install -y git
                ;;
            *)
                log_error "Unsupported OS for automatic git installation: $OS"
                return 1
                ;;
        esac
    fi

    mkdir -p "$NOVNC_PARENT_DIR"

    if [ -d "$NOVNC_DIR/.git" ]; then
        log_info "noVNC repository already exists at $NOVNC_DIR, pulling latest changes..."
        git -C "$NOVNC_DIR" fetch --all --tags --prune || true
        git -C "$NOVNC_DIR" pull --ff-only || true
    else
        if [ -d "$NOVNC_DIR" ] && [ ! -d "$NOVNC_DIR/.git" ]; then
            log_warn "Existing directory at $NOVNC_DIR is not a git repository; backing it up"
            mv "$NOVNC_DIR" "$NOVNC_DIR.backup.$(date +%Y%m%d_%H%M%S)"
        fi
        log_info "Cloning noVNC from GitHub into $NOVNC_DIR..."
        git clone "$NOVNC_REPO_URL" "$NOVNC_DIR"
    fi

    # Ensure proper ownership for runtime user
    sudo chown -R "$INSTALL_USER:$INSTALL_USER" "$NOVNC_DIR"

    # Ensure proxy script exists
    if [ ! -x "$NOVNC_DIR/utils/novnc_proxy" ]; then
        if [ -f "$NOVNC_DIR/utils/novnc_proxy" ]; then
            chmod +x "$NOVNC_DIR/utils/novnc_proxy"
        else
            log_error "novnc_proxy not found at $NOVNC_DIR/utils/novnc_proxy"
            return 1
        fi
    fi

    log_info "Writing /etc/systemd/system/novnc.service"
    sudo tee /etc/systemd/system/novnc.service > /dev/null << EOF
[Unit]
Description=noVNC Web VNC Proxy
After=network.target vncserver.service

[Service]
Type=simple
User=$INSTALL_USER
Group=$INSTALL_USER
WorkingDirectory=$NOVNC_DIR
ExecStart=$NOVNC_DIR/utils/novnc_proxy --listen $NOVNC_LISTEN_PORT --vnc $NOVNC_VNC_TARGET
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

    sudo systemctl daemon-reload
    sudo systemctl enable novnc.service
    sudo systemctl restart novnc.service

    if sudo systemctl is-active --quiet novnc.service; then
        log_success "noVNC service configured and running"
        log_info "Service: novnc.service"
        log_info "Repo path: $NOVNC_DIR"
        log_info "Listening on: 0.0.0.0:$NOVNC_LISTEN_PORT"
        log_info "VNC target: $NOVNC_VNC_TARGET"
    else
        log_error "novnc.service failed to start"
        sudo systemctl status novnc.service --no-pager || true
        return 1
    fi
}

# Command dispatcher
case "${1:-help}" in
    install)
        # Check if we already have a complete native installation
        if detect_existing_installation; then
            log_info "Existing native installation detected, checking for updates..."
            INSTALL_MODE="native"
        # Check if mode is pre-set via environment
        elif [[ "${GINTO_INSTALL_MODE:-}" == "docker" ]]; then
            INSTALL_MODE="docker"
        elif [[ "${GINTO_INSTALL_MODE:-}" == "native" ]]; then
            INSTALL_MODE="native"
        # Check if mode is saved from previous run
        elif load_config 2>/dev/null && [[ -n "${INSTALL_MODE:-}" ]]; then
            log_info "Resuming previous installation (mode: $INSTALL_MODE)"
        else
            prompt_install_mode
        fi
        
        # Save mode for resume
        save_config
        
        if [[ "$INSTALL_MODE" == "docker" ]]; then
            do_docker_install
        else
            do_install
        fi
        ;;
    install-docker|docker)
        INSTALL_MODE="docker"
        do_docker_install
        ;;
    install-native|native)
        INSTALL_MODE="native"
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
    novnc-local)
        # OS detection is needed for package handling in this command
        detect_os
        install_novnc_service
        ;;
    *)
        echo "Ginto AI Installation Script"
        echo ""
        echo "Usage: $0 <command>"
        echo ""
        echo "Installation Commands:"
        echo "  install        - Interactive install (prompts for native or docker)"
        echo "  install-native - Install directly on system (native mode)"
        echo "  install-docker - Install using Docker containers"
        echo ""
        echo "Service Commands (native mode):"
        echo "  start     - Start Ginto and Caddy services"
        echo "  stop      - Stop Ginto service"
        echo "  restart   - Restart all services"
        echo "  status    - Show service status"
        echo "  novnc-local - Install/update local noVNC from GitHub and configure novnc.service"
        echo ""
        echo "Other Commands:"
        echo "  reset     - Clear installation checkpoint to start fresh"
        echo ""
        echo "Environment Variables:"
        echo "  GINTO_INSTALL_MODE=docker|native  - Pre-select installation mode"
        echo "  GINTO_SKIP_POWERDNS=true          - Skip PowerDNS installation"
        echo "  GINTO_FORCE_POWERDNS=true         - Force PowerDNS even in containers"
        echo ""
        echo "Optional Flags:"
        echo "  --skip-powerdns   - Skip PowerDNS installation (for local dev)"
        echo "  --force-powerdns  - Force PowerDNS in containers/NAT environments"
        echo ""
        echo "Native mode installs:"
        echo "  - PHP 8.x with required extensions"
        echo "  - Composer (PHP package manager)"
        echo "  - MariaDB (database server)"
        echo "  - Caddy (web server/reverse proxy)"
        echo "  - Git, curl, unzip, ffmpeg"
        echo "  - Node.js LTS (auto-detected latest)"
        echo "  - llama.cpp (local LLM inference server)"
        echo "  - Build tools (gcc, cmake, etc.)"
        echo ""
        echo "Docker mode installs:"
        echo "  - Docker Engine and Docker Compose"
        echo "  - All services run in containers (PHP, MariaDB, Caddy, Redis)"
        echo "  - Optional Docker-based sandboxes"
        echo ""
        echo "NOTE: Ginto must be installed in your home directory!"
        echo ""
        ;;
esac
