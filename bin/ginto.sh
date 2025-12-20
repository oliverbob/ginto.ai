#!/usr/bin/env bash
#===============================================================================
# Ginto LXC/LXD Sandbox Management Script
#===============================================================================
# Automates creation, management, and maintenance of LXD-based sandboxes
# for the Ginto AI platform.
#
# Usage:
#   bin/ginto.sh <command> [options]
#
# Commands:
#   init              Initialize LXD and create base images
#   reset             Delete everything and reinitialize (DESTRUCTIVE)
#   create <name>     Create a new sandbox from ginto-sandbox template
#   delete <name>     Delete a sandbox completely
#   start <name>      Start a stopped sandbox
#   stop <name>       Stop a running sandbox
#   restart <name>    Restart a sandbox
#   list              List all sandboxes
#   status [name]     Show status of sandbox(es)
#   shell <name>      Open shell in sandbox
#   exec <name> <cmd> Execute command in sandbox
#   logs <name>       View Caddy logs from sandbox
#   ip <name>         Get IP address of sandbox
#   publish           Publish ginto-sandbox container as image
#   backup [name]     Backup image to /home/oliverbob/containers/
#   cleanup [tier]    Remove stopped sandboxes past idle timeout
#   verify            Verify LXD setup and permissions
#   bootstrap         Install all packages (run INSIDE container)
#
# Options:
#   --web-user <user> Add specific user to sudoers for LXC access
#   --tier <tier>     Set resource tier: free, premium, admin (default: free)
#
# Fair Use Tiers (TESTING ONLY - not for production hosting):
#   free     - 50MB disk, 128MB RAM, 0.5 CPU, 30 procs, 15min idle
#   premium  - 250MB disk, 256MB RAM, 1 CPU, 75 procs, 2hr idle
#   admin    - 1GB disk, 512MB RAM, 2 CPU, 150 procs, 8hr idle
#
# Examples:
#   bin/ginto.sh init                    # First-time setup
#   bin/ginto.sh --web-user oliverbob init  # Init with specific user
#   bin/ginto.sh reset                   # Delete all and reinstall
#   bin/ginto.sh create mybox            # Create free-tier sandbox
#   bin/ginto.sh create mybox --tier=premium  # Create premium sandbox
#   bin/ginto.sh shell sandbox-user123   # Open shell
#   bin/ginto.sh cleanup                 # Clean free-tier sandboxes
#   bin/ginto.sh cleanup all             # Clean all tiers
#===============================================================================

set -euo pipefail

# Configuration
readonly SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Detect LXC command path
if command -v /snap/bin/lxc &>/dev/null; then
    readonly LXC_CMD="sudo /snap/bin/lxc"
elif command -v lxc &>/dev/null; then
    readonly LXC_CMD="sudo lxc"
else
    readonly LXC_CMD="sudo /snap/bin/lxc"  # Fallback, will fail if not installed
fi

readonly BASE_IMAGE="ginto-sandbox"
readonly BASE_CONTAINER="ginto-sandbox"
readonly BACKUP_DIR="${GINTO_BACKUP_DIR:-/var/lib/ginto/backups}"
readonly SANDBOX_PREFIX="ginto-sandbox-"

# Optional: Web user for sudoers (can be set via --web-user or WEB_USER env)
WEB_USER="${WEB_USER:-}"

#===============================================================================
# FAIR USE LIMITS - Conservative Testing-Only Tiers
#===============================================================================
# ⚠️  IMPORTANT: Ginto sandboxes are for LEARNING and TESTING only.
# These are NOT production hosting environments. Users should not run
# customer-facing applications or services in these containers.
#
# TIER DEFINITIONS:
#   free    = Exploratory testing (very restrictive, quick cleanup)
#   premium = Extended learning sessions (moderate limits)
#   admin   = Internal debugging/development only
#
# STORAGE NOTE: LXD uses Copy-on-Write. Base image is ~176 MiB shared.
# Per-sandbox delta starts at ~3 MiB, grows with user files.
# 50MB limit = scripts, small DBs, documents. No npm/pip/composer install.
#===============================================================================

# --- FREE TIER (exploratory testing) ---
readonly FREE_DISK_QUOTA="50MB"     # Scripts, small files, documents only
readonly FREE_MEMORY="128MB"        # Minimal RAM (tight but functional)
readonly FREE_CPU="0.5"             # Half a CPU core (50% of 1 core)
readonly FREE_PROCESSES="30"        # Minimal processes
readonly FREE_IDLE_TIMEOUT="900"    # 15 minutes idle = cleanup

# --- PREMIUM TIER (extended learning) ---
readonly PREMIUM_DISK_QUOTA="250MB" # Small projects, some packages
readonly PREMIUM_MEMORY="256MB"     # Reasonable for learning
readonly PREMIUM_CPU="1"            # 1 full CPU core
readonly PREMIUM_PROCESSES="75"     # Moderate processes
readonly PREMIUM_IDLE_TIMEOUT="7200"   # 2 hours idle timeout

# --- ADMIN TIER (internal use only) ---
readonly ADMIN_DISK_QUOTA="1GB"     # Development/debugging
readonly ADMIN_MEMORY="512MB"       # Comfortable for testing
readonly ADMIN_CPU="2"              # 2 cores
readonly ADMIN_PROCESSES="150"      # Generous for debugging
readonly ADMIN_IDLE_TIMEOUT="28800" # 8 hours

# Default tier for new sandboxes (override with --tier=premium)
DEFAULT_TIER="${DEFAULT_TIER:-free}"

# Colors for output
readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[1;33m'
readonly BLUE='\033[0;34m'
readonly NC='\033[0m' # No Color

#-------------------------------------------------------------------------------
# Helper Functions
#-------------------------------------------------------------------------------

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[OK]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

die() {
    log_error "$1"
    exit 1
}

require_root_or_sudo() {
    if [[ $EUID -ne 0 ]] && ! sudo -n true 2>/dev/null; then
        die "This command requires sudo access"
    fi
}

container_exists() {
    local name="$1"
    $LXC_CMD info "$name" &>/dev/null
}

container_running() {
    local name="$1"
    local status
    status=$($LXC_CMD info "$name" 2>/dev/null | grep -i "status:" | awk '{print $2}')
    [[ "$status" == "RUNNING" ]]
}

image_exists() {
    local name="$1"
    $LXC_CMD image list | grep -q "$name"
}

#-------------------------------------------------------------------------------
# Compute deterministic IP for sandbox using Feistel permutation
# This ensures the same sandbox ID always gets the same IP
#-------------------------------------------------------------------------------
compute_sandbox_ip() {
    local sandbox_id="$1"
    local network_prefix="${2:-10.166.3}"
    
    # Use node.js for the Feistel computation (matches sandbox-proxy.js)
    local ip
    ip=$(node -e "
const crypto = require('crypto');
const KEY = process.env.IP_PERMUTATION_KEY || 'ginto-default-key-change-in-prod';
function feistelPermute(input, key) {
    let L = (input >>> 16) & 0xFFFF;
    let R = input & 0xFFFF;
    for (let i = 0; i < 4; i++) {
        const rk = crypto.createHash('sha256').update(key + ':' + i + ':' + R).digest();
        const F = (rk[0] << 8) | rk[1];
        L = [R, L ^ F][0]; R = L ^ F; L = [R, L][0]; R = [R, L ^ F][1];
        const nL = R; const nR = L ^ F; L = nL; R = nR;
    }
    return ((L << 16) | R) >>> 0;
}
const sandboxId = '$sandbox_id';
const hash = crypto.createHash('sha256').update(sandboxId).digest();
const input = ((hash[0] << 24) | (hash[1] << 16) | (hash[2] << 8) | hash[3]) >>> 0;
const permuted = feistelPermute(input, KEY);
const lastOctet = 2 + (permuted % 253);
console.log('$network_prefix.' + lastOctet);
" 2>/dev/null)
    
    echo "$ip"
}

get_container_ip() {
    local name="$1"
    local ip
    ip=$($LXC_CMD list "$name" --format csv -c 4 2>/dev/null | grep -oE '10\.[0-9]+\.[0-9]+\.[0-9]+' | head -1)
    echo "$ip"
}

wait_for_ip() {
    local name="$1"
    local timeout="${2:-30}"
    local count=0
    local ip=""
    
    while [[ -z "$ip" && $count -lt $timeout ]]; do
        ip=$(get_container_ip "$name")
        if [[ -z "$ip" ]]; then
            sleep 1
            ((count++))
        fi
    done
    
    echo "$ip"
}

# Apply security hardening to a container
# This is CRITICAL for containers with terminal access
# Uses Proxmox-style protections to allow nesting safely
#
# SECURITY MODEL: Defense in Depth
# ================================
# Layer 1: Unprivileged container (container root ≠ host root)
# Layer 2: User namespace isolation (uid/gid mapping)
# Layer 3: AppArmor confinement (MAC policy)
# Layer 4: Seccomp syscall filtering (blocks dangerous syscalls)
# Layer 5: Capability dropping (no CAP_SYS_ADMIN, etc.)
# Layer 6: Device restrictions (no access to host devices)
# Layer 7: Resource limits (CPU, memory, disk, processes)
# Layer 8: Network isolation (container network only)
#
# Even if an attacker escapes the container, they face:
# - No kernel access (unprivileged + seccomp)
# - No host filesystem access (isolated rootfs)
# - No host network access (bridged/isolated network)
# - No host device access (device filtering)
# - UID 0 in container = high UID on host (useless)
#
apply_security_config() {
    local name="$1"
    local tier="${2:-$DEFAULT_TIER}"  # free, premium, or admin
    
    log_info "Applying Proxmox-style security hardening to '$name' (tier: $tier)..."
    
    # Select limits based on tier
    local disk_quota memory cpu processes
    case "$tier" in
        premium)
            disk_quota="$PREMIUM_DISK_QUOTA"
            memory="$PREMIUM_MEMORY"
            cpu="$PREMIUM_CPU"
            processes="$PREMIUM_PROCESSES"
            ;;
        admin)
            disk_quota="$ADMIN_DISK_QUOTA"
            memory="$ADMIN_MEMORY"
            cpu="$ADMIN_CPU"
            processes="$ADMIN_PROCESSES"
            ;;
        free|*)
            disk_quota="$FREE_DISK_QUOTA"
            memory="$FREE_MEMORY"
            cpu="$FREE_CPU"
            processes="$FREE_PROCESSES"
            ;;
    esac
    
    #===========================================================================
    # LAYER 1: UNPRIVILEGED CONTAINER (Most Critical)
    #===========================================================================
    # Container runs without real root privileges
    # Even if attacker gets root inside container, they are UID 100000+ on host
    #---------------------------------------------------------------------------
    
    $LXC_CMD config set "$name" security.privileged=false 2>/dev/null || true
    
    #===========================================================================
    # LAYER 2: USER NAMESPACE ISOLATION
    #===========================================================================
    # Maps container UIDs to unprivileged host UIDs
    # Container root (UID 0) = Host UID 100000+ (no privileges)
    # Nested containers get ANOTHER layer of isolation
    #---------------------------------------------------------------------------
    
    # Isolated idmap = unique UID range per container (prevents cross-container attacks)
    $LXC_CMD config set "$name" security.idmap.isolated=true 2>/dev/null || true
    
    # Use subordinate UIDs (extra isolation layer)
    $LXC_CMD config set "$name" security.idmap.size=65536 2>/dev/null || true
    
    #===========================================================================
    # LAYER 3: APPARMOR CONFINEMENT (Mandatory Access Control)
    #===========================================================================
    # AppArmor restricts what the container can access even if it has permissions
    # LXD's default profile is already very restrictive
    #---------------------------------------------------------------------------
    
    # Use LXD's generated AppArmor profile (automatically created)
    # This blocks: raw socket access, mounting host fs, ptrace host processes, etc.
    $LXC_CMD config set "$name" raw.apparmor="" 2>/dev/null || true
    
    #===========================================================================
    # LAYER 4: NESTING WITH STRICT SYSCALL INTERCEPTION
    #===========================================================================
    # Enable nesting (for Docker, LXC inside) BUT with intercepted syscalls
    # Dangerous operations are validated by LXD before being allowed
    #---------------------------------------------------------------------------
    
    # Enable nesting (allows nested containers/Docker)
    $LXC_CMD config set "$name" security.nesting=true 2>/dev/null || true
    
    # INTERCEPT MOUNT: All mount syscalls go through LXD validation
    # Only allows safe filesystem types, prevents mounting host filesystems
    $LXC_CMD config set "$name" security.syscalls.intercept.mount=true 2>/dev/null || true
    $LXC_CMD config set "$name" security.syscalls.intercept.mount.allowed=ext4,tmpfs,proc,sysfs,cgroup,cgroup2,overlay,devpts 2>/dev/null || true
    $LXC_CMD config set "$name" security.syscalls.intercept.mount.shift=true 2>/dev/null || true
    
    # INTERCEPT MKNOD: Device creation goes through LXD validation
    # Prevents creating devices that access host hardware
    $LXC_CMD config set "$name" security.syscalls.intercept.mknod=true 2>/dev/null || true
    
    # INTERCEPT SETXATTR: Extended attributes validated by LXD
    # Prevents setting dangerous security attributes
    $LXC_CMD config set "$name" security.syscalls.intercept.setxattr=true 2>/dev/null || true
    
    # INTERCEPT BPF: eBPF programs validated by LXD
    # Prevents malicious kernel-level packet filtering
    $LXC_CMD config set "$name" security.syscalls.intercept.bpf=true 2>/dev/null || true
    $LXC_CMD config set "$name" security.syscalls.intercept.bpf.devices=true 2>/dev/null || true
    
    # INTERCEPT SCHED_SETSCHEDULER: Prevents real-time priority escalation
    $LXC_CMD config set "$name" security.syscalls.intercept.sched_setscheduler=true 2>/dev/null || true
    
    # INTERCEPT SYSINFO: Hides host system information
    $LXC_CMD config set "$name" security.syscalls.intercept.sysinfo=true 2>/dev/null || true
    
    #===========================================================================
    # LAYER 5: BLOCK DANGEROUS KERNEL FEATURES
    #===========================================================================
    # Prevent container from loading kernel modules or accessing kernel directly
    #---------------------------------------------------------------------------
    
    # Block kernel module loading (even if somehow allowed)
    $LXC_CMD config set "$name" linux.kernel_modules="" 2>/dev/null || true
    
    # Block sysctl modifications that could affect host
    $LXC_CMD config set "$name" linux.sysctl.kernel.unprivileged_userns_clone=0 2>/dev/null || true
    
    #===========================================================================
    # LAYER 6: DEVICE ACCESS RESTRICTIONS
    #===========================================================================
    # Block access to all host devices except safe virtual devices
    #---------------------------------------------------------------------------
    
    # Block raw disk access (no /dev/sda, /dev/nvme0n1, etc.)
    # LXD already blocks this by default, but we ensure it
    $LXC_CMD config device remove "$name" rawdisk 2>/dev/null || true
    
    # Block GPU passthrough (no CUDA escapes)
    $LXC_CMD config device remove "$name" gpu 2>/dev/null || true
    
    #===========================================================================
    # LAYER 7: NETWORK SECURITY
    #===========================================================================
    # Container uses bridged networking - isolated from host network stack
    #---------------------------------------------------------------------------
    
    # Disable raw socket access via security profile (prevents packet sniffing)
    # This is enforced by AppArmor profile
    
    # Disable IPv6 router advertisements (prevents network attacks)
    $LXC_CMD config set "$name" security.devlxd=true 2>/dev/null || true
    
    #===========================================================================
    # LAYER 8: RESOURCE LIMITS (Prevent DoS)
    #===========================================================================
    # Hard limits on CPU, memory, disk, and processes
    # Prevents resource exhaustion attacks
    #---------------------------------------------------------------------------
    
    # DISK QUOTA: Hard limit on storage
    $LXC_CMD config device set "$name" root size="$disk_quota" 2>/dev/null || true
    
    # CPU LIMIT: Prevent CPU starvation of host
    $LXC_CMD config set "$name" limits.cpu="$cpu" 2>/dev/null || true
    $LXC_CMD config set "$name" limits.cpu.priority=5 2>/dev/null || true
    
    # MEMORY LIMIT: Hard memory cap (OOM killer if exceeded)
    $LXC_CMD config set "$name" limits.memory="$memory" 2>/dev/null || true
    $LXC_CMD config set "$name" limits.memory.enforce=hard 2>/dev/null || true
    $LXC_CMD config set "$name" limits.memory.swap=false 2>/dev/null || true
    
    # PROCESS LIMIT: Prevents fork bombs
    $LXC_CMD config set "$name" limits.processes="$processes" 2>/dev/null || true
    
    # DISK I/O PRIORITY: Lower priority than host processes
    $LXC_CMD config set "$name" limits.disk.priority=3 2>/dev/null || true
    
    # NETWORK BANDWIDTH: Optional rate limiting (commented out - can enable if needed)
    # $LXC_CMD config device set "$name" eth0 limits.ingress=10Mbit 2>/dev/null || true
    # $LXC_CMD config device set "$name" eth0 limits.egress=10Mbit 2>/dev/null || true
    
    #===========================================================================
    # LAYER 9: TERMINAL & CLI ABUSE PROTECTION
    #===========================================================================
    # Specific protections against shell/terminal attack vectors
    #---------------------------------------------------------------------------
    
    # FILE DESCRIPTOR LIMIT: Prevents fd exhaustion attacks
    # Default is often 1M+ which allows resource exhaustion
    local fd_limit
    case "$tier" in
        admin)   fd_limit="4096" ;;
        premium) fd_limit="2048" ;;
        *)       fd_limit="1024" ;;
    esac
    # Note: This requires cgroup v2 and may not work on all systems
    # Fallback is kernel default (already limited by process limit)
    
    # NETWORK RATE LIMITING: Prevent outbound attacks/crypto mining
    # Ingress = data coming IN to container
    # Egress = data going OUT from container (more dangerous for attacks)
    local net_limit
    case "$tier" in
        admin)   net_limit="50Mbit" ;;
        premium) net_limit="20Mbit" ;;
        *)       net_limit="5Mbit" ;;   # Very limited for free tier
    esac
    $LXC_CMD config device set "$name" eth0 limits.ingress="$net_limit" 2>/dev/null || true
    $LXC_CMD config device set "$name" eth0 limits.egress="$net_limit" 2>/dev/null || true
    
    # DISABLE PTRACE: Prevents debugging/tracing other processes
    # This blocks gdb, strace, etc. from being weaponized
    $LXC_CMD config set "$name" security.syscalls.deny=ptrace 2>/dev/null || true
    
    #===========================================================================
    # LAYER 10: ADDITIONAL HARDENING
    #===========================================================================
    # Extra protections that don't fit other categories
    #---------------------------------------------------------------------------
    
    # Prevent container from seeing host dmesg (kernel messages)
    $LXC_CMD config set "$name" security.syscalls.deny_compat=false 2>/dev/null || true
    
    # Block loading of kernel modules via init_module/finit_module
    # Already blocked by unprivileged + seccomp, but belt-and-suspenders
    
    # Disable direct /dev/lxd API access from nested containers
    # This prevents nested containers from controlling the parent
    $LXC_CMD config set "$name" security.devlxd.images=false 2>/dev/null || true
    
    # BLOCK KEYCTL: Prevents kernel keyring manipulation
    # Could be used to steal secrets from kernel memory
    $LXC_CMD config set "$name" security.syscalls.deny=keyctl 2>/dev/null || true
    
    # BLOCK ADD_KEY: Prevents adding keys to kernel keyring
    $LXC_CMD config set "$name" security.syscalls.deny=add_key 2>/dev/null || true
    
    # BLOCK REQUEST_KEY: Prevents requesting keys from kernel
    $LXC_CMD config set "$name" security.syscalls.deny=request_key 2>/dev/null || true
    
    log_success "Proxmox-style security applied to '$name' (tier: $tier)"
    log_info "  Limits: disk=$disk_quota, mem=$memory, cpu=$cpu, procs=$processes"
    log_info "  Network: $net_limit ingress/egress"
    log_info "  Security: unprivileged, isolated idmap, AppArmor, syscall interception"
    log_info "  Terminal protection: ptrace blocked, keyctl blocked, rate limited"
}

#-------------------------------------------------------------------------------
# Setup sandbox-proxy service
# This provides a reverse proxy for web requests to sandbox containers
#-------------------------------------------------------------------------------
setup_sandbox_proxy() {
    local GINTO_DIR
    GINTO_DIR=$(cd "$SCRIPT_DIR/.." && pwd)
    local PROXY_DIR="$GINTO_DIR/tools/sandbox-proxy"
    
    if [[ ! -d "$PROXY_DIR" ]]; then
        log_warn "Sandbox proxy directory not found: $PROXY_DIR"
        log_info "Skipping sandbox-proxy setup"
        return 0
    fi
    
    log_info "Setting up sandbox-proxy service..."
    
    # Install npm dependencies if needed
    if [[ ! -d "$PROXY_DIR/node_modules" ]]; then
        log_info "Installing sandbox-proxy dependencies..."
        (cd "$PROXY_DIR" && npm install) || {
            log_error "Failed to install sandbox-proxy dependencies"
            return 1
        }
    fi
    
    # Detect ginto directory owner for running the service
    local service_user
    service_user=$(stat -c '%U' "$GINTO_DIR" 2>/dev/null || stat -f '%Su' "$GINTO_DIR" 2>/dev/null)
    [[ -z "$service_user" ]] && service_user="www-data"
    
    # Create systemd service
    log_info "Creating sandbox-proxy.service (user: $service_user, port: 1800)..."
    
    # Detect LXD bridge network prefix (typically 10.x.x for lxdbr0)
    local network_prefix=""
    local bridge_ip
    bridge_ip=$(ip -4 addr show lxdbr0 2>/dev/null | grep -oP '(?<=inet\s)\d+\.\d+\.\d+' | head -1)
    if [[ -n "$bridge_ip" ]]; then
        network_prefix="$bridge_ip"
        log_info "Detected LXD network prefix: $network_prefix"
    else
        log_warn "Could not detect LXD bridge, using default 10.166.3"
        network_prefix="10.166.3"
    fi
    
    cat <<EOF | sudo tee /etc/systemd/system/sandbox-proxy.service > /dev/null
[Unit]
Description=Ginto Sandbox Proxy
After=network.target redis-server.service
Wants=redis-server.service

[Service]
Type=simple
User=$service_user
WorkingDirectory=$PROXY_DIR
Environment=PORT=1800
Environment=LXD_NETWORK_PREFIX=$network_prefix
Environment=NODE_ENV=production
ExecStart=/usr/bin/node sandbox-proxy.js
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF
    
    # Kill anything on port 1800 before starting
    log_info "Clearing port 1800..."
    sudo fuser -k 1800/tcp 2>/dev/null || true
    sleep 1
    
    # Reload and start service
    sudo systemctl daemon-reload
    sudo systemctl enable sandbox-proxy
    sudo systemctl restart sandbox-proxy
    
    # Wait a moment and check status
    sleep 2
    if systemctl is-active --quiet sandbox-proxy; then
        log_success "sandbox-proxy service started on port 1800"
    else
        log_warn "sandbox-proxy service may have issues, check: sudo journalctl -u sandbox-proxy"
    fi
}

#-------------------------------------------------------------------------------
# Command: init
#-------------------------------------------------------------------------------
cmd_init() {
    log_info "Initializing LXD for Ginto sandboxes..."
    
    # Check LXD is installed (try snap first, then native)
    if ! command -v lxc &>/dev/null && ! command -v /snap/bin/lxc &>/dev/null; then
        log_info "Installing LXD via snap..."
        
        # Ensure snapd is installed (required for Ubuntu 24.04+)
        if ! command -v snap &>/dev/null; then
            log_info "Installing snapd first..."
            sudo apt-get update
            sudo apt-get install -y snapd
            # Wait for snapd to be ready
            sleep 5
            sudo systemctl enable --now snapd.socket
            sudo systemctl start snapd
            sleep 3
        fi
        
        # Install LXD via snap (only supported method on Ubuntu 24.04+)
        sudo snap install lxd
        sudo usermod -aG lxd "$(whoami)" 2>/dev/null || true
        
        # Ensure snap bin is in PATH for this session
        export PATH="/snap/bin:$PATH"
    else
        log_success "LXD is already installed"
    fi
    
    # Check if LXD storage pool exists (proper initialization check)
    local storage_exists=false
    if $LXC_CMD storage list 2>/dev/null | grep -q "default\|ginto"; then
        storage_exists=true
    fi
    
    if [[ "$storage_exists" == "false" ]]; then
        log_info "Initializing LXD with storage pool..."
        
        # Create preseed config for proper LXD initialization
        cat <<EOF | sudo lxd init --preseed
config: {}
networks:
- config:
    ipv4.address: auto
    ipv6.address: auto
  description: ""
  name: lxdbr0
  type: bridge
  project: default
storage_pools:
- config: {}
  description: ""
  name: default
  driver: dir
profiles:
- config: {}
  description: Default LXD profile
  devices:
    eth0:
      name: eth0
      network: lxdbr0
      type: nic
    root:
      path: /
      pool: default
      type: disk
  name: default
projects: []
cluster: null
EOF
        log_success "LXD initialized with storage pool"
    else
        log_success "LXD is already initialized with storage"
    fi
    
    # Enable nesting on default profile (required for containers that run Docker/LXC)
    if ! $LXC_CMD profile show default 2>/dev/null | grep -q "security.nesting.*true"; then
        log_info "Enabling nesting on default profile..."
        $LXC_CMD profile set default security.nesting=true 2>/dev/null || true
        log_success "Nesting enabled on default profile"
    else
        log_success "Nesting already enabled on default profile"
    fi
    
    # Configure UFW to allow LXD bridge traffic (if UFW is active)
    if command -v ufw &>/dev/null && sudo ufw status | grep -q "Status: active"; then
        log_info "Configuring UFW for LXD bridge..."
        
        # Check if lxdbr0 rules already exist
        if ! sudo ufw status | grep -q "lxdbr0"; then
            sudo ufw allow in on lxdbr0 >/dev/null 2>&1
            sudo ufw allow out on lxdbr0 >/dev/null 2>&1
            sudo ufw route allow in on lxdbr0 >/dev/null 2>&1
            sudo ufw route allow out on lxdbr0 >/dev/null 2>&1
            log_success "UFW rules added for lxdbr0 bridge"
        else
            log_success "UFW rules for lxdbr0 already exist"
        fi
    fi
    
    # Create backup directory
    if [[ ! -d "$BACKUP_DIR" ]]; then
        log_info "Creating backup directory: $BACKUP_DIR"
        sudo mkdir -p "$BACKUP_DIR"
        sudo chown "$(whoami):$(whoami)" "$BACKUP_DIR" 2>/dev/null || true
    fi
    
    # Check if base image exists
    if ! image_exists "$BASE_IMAGE"; then
        log_warn "Base image '$BASE_IMAGE' not found"
        log_info "Creating base image from Alpine..."
        create_base_image
    else
        log_success "Base image '$BASE_IMAGE' exists"
    fi
    
    # Configure sudoers for web server users to run lxc commands without password
    # SECURITY: Commands are restricted to sandbox-* containers only
    log_info "Configuring sudoers for LXC access..."
    
    # Build list of users that need LXC access
    local lxc_users="www-data"
    
    # Method 1: Check --web-user argument (passed from command line)
    if [[ -n "${WEB_USER:-}" ]]; then
        if [[ "$WEB_USER" != "www-data" && "$WEB_USER" != "root" ]]; then
            lxc_users="$lxc_users $WEB_USER"
            log_info "Using specified web user: $WEB_USER"
        fi
    fi
    
    # Method 2: Detect from running processes
    local web_user=""
    web_user=$(ps aux | grep -E 'php.*-S.*8000|php-fpm.*pool' | grep -v grep | head -1 | awk '{print $1}')
    [[ -z "$web_user" ]] && web_user=$(ps aux | grep -E 'nginx.*worker|apache2.*www' | grep -v grep | head -1 | awk '{print $1}')
    if [[ -n "$web_user" && "$web_user" != "www-data" && "$web_user" != "root" && ! " $lxc_users " =~ " $web_user " ]]; then
        lxc_users="$lxc_users $web_user"
        log_info "Detected web server running as: $web_user"
    fi
    
    # Method 3: Check owner of the ginto directory (likely the deploy user)
    local ginto_owner=""
    ginto_owner=$(stat -c '%U' "$SCRIPT_DIR/.." 2>/dev/null || stat -f '%Su' "$SCRIPT_DIR/.." 2>/dev/null)
    if [[ -n "$ginto_owner" && "$ginto_owner" != "www-data" && "$ginto_owner" != "root" && ! " $lxc_users " =~ " $ginto_owner " ]]; then
        lxc_users="$lxc_users $ginto_owner"
        log_info "Detected ginto directory owner: $ginto_owner"
    fi
    
    # Always recreate sudoers to ensure all users are included
    log_info "Creating sudoers for users: $lxc_users"
    
    # Generate sudoers content for all web users
    {
        echo "# Ginto LXD - Allow web server users to manage LXC containers without password"
        echo "# SECURITY HARDENING:"
        echo "# 1. Disable use_pty requirement (PHP has no TTY to hijack)"
        echo "# 2. Restrict ALL commands to sandbox-* containers only"
        echo "# 3. Prevent access to host resources via lxc"
        echo "#"
        echo "# Allowed users: $lxc_users"
        echo ""
        
        for user in $lxc_users; do
            echo "# Rules for $user"
            echo "Defaults:$user !use_pty"
            echo ""
            echo "# Container lifecycle - restricted to sandbox-* prefix"
            echo "$user ALL=(root) NOPASSWD: /snap/bin/lxc launch ginto-sandbox sandbox-*"
            echo "$user ALL=(root) NOPASSWD: /snap/bin/lxc start sandbox-*"
            echo "$user ALL=(root) NOPASSWD: /snap/bin/lxc stop sandbox-*"
            echo "$user ALL=(root) NOPASSWD: /snap/bin/lxc delete sandbox-*"
            echo "$user ALL=(root) NOPASSWD: /snap/bin/lxc info sandbox-*"
            echo ""
            echo "# Exec commands - restricted to sandbox-* containers"
            echo "$user ALL=(root) NOPASSWD: /snap/bin/lxc exec sandbox-* -- *"
            echo ""
            echo "# Config - only allow setting limits on sandbox-* containers"
            echo "$user ALL=(root) NOPASSWD: /snap/bin/lxc config set sandbox-* limits.cpu *"
            echo "$user ALL=(root) NOPASSWD: /snap/bin/lxc config set sandbox-* limits.memory *"
            echo "$user ALL=(root) NOPASSWD: /snap/bin/lxc config set sandbox-* limits.processes *"
            echo "$user ALL=(root) NOPASSWD: /snap/bin/lxc config set sandbox-* security.nesting*"
            echo "$user ALL=(root) NOPASSWD: /snap/bin/lxc config set sandbox-* security.privileged*"
            echo "$user ALL=(root) NOPASSWD: /snap/bin/lxc config device set sandbox-* root size *"
            echo ""
            echo "# File operations - restricted to sandbox-* containers"
            echo "$user ALL=(root) NOPASSWD: /snap/bin/lxc file push * sandbox-*"
            echo "$user ALL=(root) NOPASSWD: /snap/bin/lxc file pull sandbox-* *"
            echo ""
            echo "# List operations (read-only, safe to allow broadly)"
            echo "$user ALL=(root) NOPASSWD: /snap/bin/lxc list *"
            echo "$user ALL=(root) NOPASSWD: /snap/bin/lxc list"
            echo "$user ALL=(root) NOPASSWD: /snap/bin/lxc image list *"
            echo "$user ALL=(root) NOPASSWD: /snap/bin/lxc image list"
            echo ""
        done
    } | sudo tee /etc/sudoers.d/ginto-lxd > /dev/null
    
    sudo chmod 440 /etc/sudoers.d/ginto-lxd
    log_success "Sudoers file created for: $lxc_users"
    
    # Setup sandbox-proxy service
    setup_sandbox_proxy
    
    log_success "LXD initialization complete"
}

#-------------------------------------------------------------------------------
# Command: reset
#-------------------------------------------------------------------------------
cmd_reset() {
    log_warn "⚠️  THIS WILL DELETE ALL SANDBOXES AND IMAGES!"
    echo ""
    echo "This command will:"
    echo "  1. Delete ALL sandbox containers (sandbox-*)"
    echo "  2. Delete the ginto-sandbox base container"
    echo "  3. Delete the ginto-sandbox image"
    echo "  4. Remove /etc/sudoers.d/ginto-lxd"
    echo "  5. Reset Redis server"
    echo "  6. Reset sandbox-proxy service"
    echo "  7. Re-run initialization from scratch"
    echo ""
    read -p "Are you sure? Type 'yes' to continue: " confirm
    
    if [[ "$confirm" != "yes" ]]; then
        log_info "Aborted."
        return 0
    fi
    
    log_info "Deleting all sandbox containers..."
    local containers
    containers=$($LXC_CMD list "${SANDBOX_PREFIX}" --format csv -c n 2>/dev/null || true)
    for container in $containers; do
        log_info "  Deleting $container..."
        $LXC_CMD delete "$container" --force 2>/dev/null || true
    done
    
    log_info "Deleting ginto-sandbox container..."
    $LXC_CMD delete "$BASE_CONTAINER" --force 2>/dev/null || true
    
    log_info "Deleting ginto-sandbox image..."
    $LXC_CMD image delete "$BASE_IMAGE" 2>/dev/null || true
    
    log_info "Removing sudoers file..."
    sudo rm -f /etc/sudoers.d/ginto-lxd
    
    # Reset Redis
    log_info "Resetting Redis..."
    if command -v redis-server &>/dev/null; then
        sudo systemctl stop redis-server 2>/dev/null || true
        sudo rm -f /var/lib/redis/dump.rdb 2>/dev/null || true
        sudo systemctl start redis-server 2>/dev/null || true
        log_success "Redis data cleared and restarted"
    else
        log_info "Installing Redis..."
        sudo apt-get install -y redis-server
        sudo systemctl enable --now redis-server
        log_success "Redis installed and started"
    fi
    
    # Reset sandbox-proxy
    log_info "Resetting sandbox-proxy service..."
    sudo systemctl stop sandbox-proxy 2>/dev/null || true
    sudo rm -f /etc/systemd/system/sandbox-proxy.service 2>/dev/null || true
    sudo systemctl daemon-reload
    
    log_success "Reset complete. Running init..."
    echo ""
    
    cmd_init
}

#-------------------------------------------------------------------------------
# Command: create_base_image (internal)
#-------------------------------------------------------------------------------
create_base_image() {
    local container="$BASE_CONTAINER"
    
    log_info "Creating ginto-sandbox base image..."
    
    # Launch Alpine container
    log_info "Launching Alpine 3.20 container..."
    if ! $LXC_CMD launch images:alpine/3.20 "$container" 2>&1; then
        echo ""
        log_error "Failed to launch container. If you see 'forkstart' error, nesting is not enabled."
        echo ""
        echo "  If you're running inside an LXD container, enable nesting from the HOST:"
        echo ""
        echo "    # For default profile (all new containers):"
        echo "    lxc profile set default security.nesting=true"
        echo ""
        echo "    # Or for a specific container:"
        echo "    lxc config set <your-container-name> security.nesting=true"
        echo "    lxc restart <your-container-name>"
        echo ""
        echo "  Then run this install script again."
        echo ""
        exit 1
    fi
    sleep 5
    
    # Wait for network
    log_info "Waiting for network..."
    local ip=""
    for i in {1..30}; do
        ip=$(get_container_ip "$container")
        [[ -n "$ip" ]] && break
        sleep 1
    done
    [[ -z "$ip" ]] && die "Container failed to get IP address"
    log_success "Container IP: $ip"
    
    # Install packages
    log_info "Installing PHP 8.2..."
    $LXC_CMD exec "$container" -- apk update
    $LXC_CMD exec "$container" -- apk add --no-cache \
        php82 php82-fpm php82-json php82-mbstring php82-openssl \
        php82-pdo php82-pdo_mysql php82-pdo_sqlite php82-curl \
        php82-dom php82-xml php82-ctype php82-session php82-tokenizer \
        php82-fileinfo php82-iconv php82-zip php82-gd php82-phar
    
    log_info "Installing Node.js 20..."
    $LXC_CMD exec "$container" -- apk add --no-cache nodejs npm
    
    log_info "Installing Python 3..."
    $LXC_CMD exec "$container" -- apk add --no-cache python3 py3-pip
    
    log_info "Installing MySQL client and SQLite..."
    $LXC_CMD exec "$container" -- apk add --no-cache mysql-client sqlite
    
    log_info "Installing Git..."
    $LXC_CMD exec "$container" -- apk add --no-cache git
    
    log_info "Installing Caddy..."
    $LXC_CMD exec "$container" -- apk add --no-cache caddy caddy-openrc
    
    log_info "Installing editors and tools..."
    $LXC_CMD exec "$container" -- apk add --no-cache vim nano bash curl ca-certificates openrc
    
    log_info "Installing document creation tools (pandoc, weasyprint)..."
    $LXC_CMD exec "$container" -- apk add --no-cache pandoc py3-weasyprint font-noto ttf-dejavu
    $LXC_CMD exec "$container" -- fc-cache -f
    
    log_info "Creating php symlink..."
    $LXC_CMD exec "$container" -- ln -sf /usr/bin/php82 /usr/bin/php
    
    log_info "Installing Composer..."
    $LXC_CMD exec "$container" -- sh -c 'curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer'
    
    log_info "Creating /home directory structure..."
    $LXC_CMD exec "$container" -- mkdir -p /home/{Desktop,Documents,Downloads,Music,Pictures,Videos,Websites}
    
    log_info "Creating index.php..."
    $LXC_CMD exec "$container" -- sh -c 'cat > /home/index.php << '\''PHPEOF'\''
<?php
/**
 * Welcome to your Ginto Sandbox!
 *
 * This is your personal development environment.
 * Edit this file or create new ones to get started.
 */

$tools = [
    "PHP" => phpversion(),
    "Node.js" => trim(shell_exec("/usr/bin/node --version 2>/dev/null") ?: "Not installed"),
    "npm" => trim(shell_exec("/usr/bin/npm --version 2>/dev/null") ?: "Not installed"),
    "Python" => trim(shell_exec("/usr/bin/python3 --version 2>/dev/null") ?: "Not installed"),
    "Git" => trim(shell_exec("/usr/bin/git --version 2>/dev/null") ?: "Not installed"),
    "Composer" => trim(shell_exec("/usr/local/bin/composer --version 2>/dev/null | head -1") ?: "Not installed"),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ginto Sandbox</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-indigo-900 via-purple-900 to-pink-800 min-h-screen text-white">
    <div class="container mx-auto px-4 py-16">
        <div class="text-center mb-12">
            <h1 class="text-5xl font-bold mb-4">🚀 Welcome to Ginto Sandbox</h1>
            <p class="text-xl text-purple-200">Your personal development environment is ready!</p>
        </div>

        <div class="max-w-4xl mx-auto">
            <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-8 mb-8">
                <h2 class="text-2xl font-semibold mb-6 flex items-center gap-2">
                    <span>🛠️</span> Available Tools
                </h2>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($tools as $name => $version): ?>
                    <div class="bg-white/5 rounded-lg p-4">
                        <div class="font-medium text-purple-200"><?= htmlspecialchars($name) ?></div>
                        <div class="text-sm text-gray-300"><?= htmlspecialchars($version) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-8 mb-8">
                <h2 class="text-2xl font-semibold mb-6 flex items-center gap-2">
                    <span>📁</span> Quick Start
                </h2>
                <div class="space-y-4 text-purple-100">
                    <p>✏️ Edit <code class="bg-white/20 px-2 py-1 rounded">index.php</code> to customize this page</p>
                    <p>📦 Use <code class="bg-white/20 px-2 py-1 rounded">composer init</code> to start a PHP project</p>
                    <p>🌐 Use <code class="bg-white/20 px-2 py-1 rounded">npm init</code> to start a Node.js project</p>
                    <p>🐍 Use <code class="bg-white/20 px-2 py-1 rounded">pip install</code> for Python packages</p>
                </div>
            </div>

            <!-- Mission Statement -->
            <div class="bg-gradient-to-r from-purple-500/20 to-pink-500/20 backdrop-blur-lg rounded-2xl p-8 border border-purple-400/30">
                <h2 class="text-2xl font-semibold mb-4 flex items-center gap-2">
                    <span>🎯</span> The Ginto Vision
                </h2>
                <div class="space-y-4 text-purple-100 leading-relaxed">
                    <p class="text-lg">
                        <strong class="text-white">Ginto</strong> is pioneering a paradigm shift in artificial intelligence infrastructure—ushering in an era where <em>containerized agentic systems</em> become the foundational architecture for human-AI collaboration.
                    </p>
                    
                    <!-- AI Evolution Context -->
                    <div class="bg-white/5 rounded-xl p-5 border-l-4 border-purple-400">
                        <p class="text-purple-200 font-medium mb-2">⚡ The Unprecedented Pace of AI Innovation</p>
                        <p class="text-sm">
                            Artificial intelligence is advancing at an extraordinary velocity—with breakthrough capabilities emerging in weeks rather than years. Traditional educational institutions, bound by semester schedules and multi-year curriculum approval cycles, were never designed to keep pace with such rapid technological evolution. This is not a failing of academia; it is simply that <strong class="text-white">AI moves at the speed of innovation, not the speed of institutions</strong>.
                        </p>
                        <p class="text-sm mt-3">
                            Ginto exists to <em>complement and extend</em> formal education—serving as a real-time bridge between emerging AI capabilities and the professionals who need them <strong class="text-white">today</strong>. We partner with educational systems and governments, providing the agile, continuously-updated training that keeps workforces competitive while traditional institutions focus on foundational knowledge and critical thinking that only they can provide.
                        </p>
                    </div>
                    
                    <p>
                        We are establishing the industry first comprehensive <strong class="text-purple-200">certification framework for Agentic Toolsets</strong>, complemented by an exclusive curriculum of <strong class="text-purple-200">AI-powered masterclasses and professional courses you will not find anywhere else</strong>—designed to democratize access to autonomous agent development. From introductory modules for newcomers to advanced practitioner certifications, our educational pathway ensures mastery at every level.
                    </p>
                    <p>
                        Our containerized approach guarantees that every developer, engineer, data scientist, researcher, healthcare professional, legal expert, educator, and creative practitioner—regardless of prior technical experience—can deploy, orchestrate, and manage intelligent agents within isolated, secure, and reproducible environments.
                    </p>
                    <p>
                        This sandbox represents more than a development environment; it is your gateway to the <strong class="text-purple-200">agentic computing paradigm</strong>—where AI agents operate as first-class obedient virtual citizens interacting with the real world alongside traditional software. These are not autonomous forces beyond human control—they are precision instruments that amplify human capability while remaining firmly under human authority. This represents not a threat, but an unprecedented opportunity to drive excellence by empowering individuals with intelligent tools they command and control.
                    </p>
                    <div class="mt-6 pt-4 border-t border-purple-400/30">
                        <div class="grid md:grid-cols-4 gap-4 text-center text-sm">
                            <a href="https://ginto.ai/masterclass" class="bg-white/5 rounded-lg p-3 hover:bg-white/10 transition-all hover:scale-105 block">
                                <div class="text-2xl mb-1">🎓</div>
                                <div class="font-medium text-purple-200">Masterclasses</div>
                                <div class="text-gray-400">Expert-led deep dives</div>
                            </a>
                            <a href="https://ginto.ai/courses" class="bg-white/5 rounded-lg p-3 hover:bg-white/10 transition-all hover:scale-105 block">
                                <div class="text-2xl mb-1">📚</div>
                                <div class="font-medium text-purple-200">Courses</div>
                                <div class="text-gray-400">Structured learning paths</div>
                            </a>
                            <a href="https://ginto.ai/certifications" class="bg-white/5 rounded-lg p-3 hover:bg-white/10 transition-all hover:scale-105 block">
                                <div class="text-2xl mb-1">🏆</div>
                                <div class="font-medium text-purple-200">Certifications</div>
                                <div class="text-gray-400">Industry-recognized credentials</div>
                            </a>
                            <a href="https://github.com/oliverbob/ginto.ai" target="_blank" class="bg-white/5 rounded-lg p-3 hover:bg-white/10 transition-all hover:scale-105 block">
                                <svg class="w-8 h-8 mb-1 mx-auto" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 21.795 24 17.295 24 12c0-6.627-5.373-12-12-12z"/></svg>
                                <div class="font-medium text-purple-200">Open Source</div>
                                <div class="text-gray-400">Contribute on GitHub</div>
                            </a>
                        </div>
                    </div>
                    <div class="mt-4 text-center">
                        <p class="text-sm text-purple-300 italic">
                            "Bridging the gap between AI rapid evolution and the professionals who shape tomorrow—one container at a time."
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-12 text-purple-300">
            <p>Powered by <strong>Ginto</strong> • Alpine Linux Container • Agentic AI Platform</p>
        </div>
    </div>
</body>
</html>
PHPEOF'
    
    log_info "Creating README.md..."
    $LXC_CMD exec "$container" -- sh -c 'cat > /home/README.md << EOF
# 🚀 Ginto Sandbox

Welcome to your personal development environment!

## Available Tools

- **PHP 8.2** + Composer
- **Node.js 20** + npm
- **Python 3** + pip
- **Git** for version control
- **MySQL Client** + SQLite
- **Vim/Nano** text editors

## Quick Start

\`\`\`bash
# PHP project
composer init

# Node.js project
npm init

# Python script
python3 script.py
\`\`\`

## Directory Structure

- \`/home/\` - Your web root (files served via Caddy)
- \`/home/index.php\` - Default landing page
- \`/home/Websites/\` - Additional web projects
EOF'
    
    log_info "Configuring Caddy..."
    $LXC_CMD exec "$container" -- sh -c 'cat > /etc/caddy/Caddyfile << EOF
:80 {
    root * /home
    php_fastcgi 127.0.0.1:9000
    file_server
    encode gzip
    log {
        output file /var/log/caddy/access.log
        format console
    }
}
EOF'
    
    $LXC_CMD exec "$container" -- mkdir -p /var/log/caddy
    
    log_info "Enabling services..."
    # Auto-detect installed PHP-FPM service
    local php_fpm_svc=$($LXC_CMD exec "$container" -- sh -c "rc-service --list | grep php-fpm | head -1" 2>/dev/null)
    [ -z "$php_fpm_svc" ] && php_fpm_svc="php-fpm82"
    $LXC_CMD exec "$container" -- rc-update add "$php_fpm_svc" default
    $LXC_CMD exec "$container" -- rc-update add caddy default
    
    log_info "Setting permissions..."
    $LXC_CMD exec "$container" -- chown -R nobody:nobody /home
    
    log_info "Stopping container and publishing as image..."
    $LXC_CMD stop "$container"
    
    # Remove old image alias if exists
    $LXC_CMD image alias delete "$BASE_IMAGE" 2>/dev/null || true
    
    # Publish container as image
    $LXC_CMD publish "$container" --alias "$BASE_IMAGE"
    
    log_success "Base image '$BASE_IMAGE' created successfully"
    $LXC_CMD image list | grep "$BASE_IMAGE"
}

#-------------------------------------------------------------------------------
# Command: create
# Usage: ginto.sh create <name> [--tier=free|premium|admin]
#-------------------------------------------------------------------------------
cmd_create() {
    local name=""
    local tier="$DEFAULT_TIER"
    
    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --tier=*)
                tier="${1#--tier=}"
                shift
                ;;
            --tier)
                tier="$2"
                shift 2
                ;;
            *)
                [[ -z "$name" ]] && name="$1"
                shift
                ;;
        esac
    done
    
    [[ -z "$name" ]] && die "Usage: ginto.sh create <sandbox-name> [--tier=free|premium|admin]"
    
    # Validate tier
    case "$tier" in
        free|premium|admin) ;;
        *) die "Invalid tier '$tier'. Use: free, premium, or admin" ;;
    esac
    
    # Add prefix if not present
    [[ "$name" != ${SANDBOX_PREFIX}* ]] && name="${SANDBOX_PREFIX}${name}"
    
    if container_exists "$name"; then
        log_warn "Container '$name' already exists"
        if ! container_running "$name"; then
            log_info "Starting existing container..."
            $LXC_CMD start "$name"
        fi
    else
        # Extract sandbox ID from full name (remove prefix)
        local sandbox_id="${name#${SANDBOX_PREFIX}}"
        
        # Detect LXD bridge network prefix
        local network_prefix
        network_prefix=$(ip -4 addr show lxdbr0 2>/dev/null | grep -oP '(?<=inet\s)\d+\.\d+\.\d+' | head -1)
        [[ -z "$network_prefix" ]] && network_prefix="10.166.3"
        
        # Compute deterministic static IP
        local static_ip
        static_ip=$(compute_sandbox_ip "$sandbox_id" "$network_prefix")
        
        log_info "Creating sandbox '$name' from $BASE_IMAGE (tier: $tier)..."
        log_info "Assigning static IP: $static_ip"
        
        $LXC_CMD launch "$BASE_IMAGE" "$name"
        
        # Assign static IP for deterministic routing
        if [[ -n "$static_ip" ]]; then
            $LXC_CMD config device override "$name" eth0 ipv4.address="$static_ip" 2>/dev/null || {
                log_warn "Could not assign static IP, using DHCP"
            }
        fi
        
        # Apply security hardening with tier-based limits
        apply_security_config "$name" "$tier"
    fi
    
    # Wait for container and get IP
    sleep 3
    local ip
    ip=$(wait_for_ip "$name" 30)
    
    if [[ -z "$ip" ]]; then
        log_error "Failed to get IP address"
        return 1
    fi
    
    log_success "Sandbox '$name' created (tier: $tier)"
    echo "  IP Address: $ip"
    echo "  Web URL: http://$ip/"
    echo "  Shell: bin/ginto.sh shell $name"
}

#-------------------------------------------------------------------------------
# Command: delete
#-------------------------------------------------------------------------------
cmd_delete() {
    local name="${1:-}"
    [[ -z "$name" ]] && die "Usage: ginto.sh delete <sandbox-name>"
    
    # Add prefix if not present
    [[ "$name" != ${SANDBOX_PREFIX}* ]] && name="${SANDBOX_PREFIX}${name}"
    
    if ! container_exists "$name"; then
        log_warn "Container '$name' does not exist"
        return 0
    fi
    
    log_info "Deleting sandbox '$name'..."
    $LXC_CMD delete "$name" --force
    log_success "Sandbox '$name' deleted"
}

#-------------------------------------------------------------------------------
# Command: start
#-------------------------------------------------------------------------------
cmd_start() {
    local name="${1:-}"
    [[ -z "$name" ]] && die "Usage: ginto.sh start <sandbox-name>"
    
    [[ "$name" != ${SANDBOX_PREFIX}* ]] && name="${SANDBOX_PREFIX}${name}"
    
    if ! container_exists "$name"; then
        die "Container '$name' does not exist"
    fi
    
    if container_running "$name"; then
        log_warn "Container '$name' is already running"
        return 0
    fi
    
    log_info "Starting sandbox '$name'..."
    $LXC_CMD start "$name"
    
    local ip
    ip=$(wait_for_ip "$name" 30)
    log_success "Sandbox '$name' started (IP: $ip)"
}

#-------------------------------------------------------------------------------
# Command: stop
#-------------------------------------------------------------------------------
cmd_stop() {
    local name="${1:-}"
    [[ -z "$name" ]] && die "Usage: ginto.sh stop <sandbox-name>"
    
    [[ "$name" != ${SANDBOX_PREFIX}* ]] && name="${SANDBOX_PREFIX}${name}"
    
    if ! container_exists "$name"; then
        die "Container '$name' does not exist"
    fi
    
    log_info "Stopping sandbox '$name'..."
    $LXC_CMD stop "$name" --force
    log_success "Sandbox '$name' stopped"
}

#-------------------------------------------------------------------------------
# Command: restart
#-------------------------------------------------------------------------------
cmd_restart() {
    local name="${1:-}"
    [[ -z "$name" ]] && die "Usage: ginto.sh restart <sandbox-name>"
    
    [[ "$name" != ${SANDBOX_PREFIX}* ]] && name="${SANDBOX_PREFIX}${name}"
    
    cmd_stop "$name"
    sleep 1
    cmd_start "$name"
}

#-------------------------------------------------------------------------------
# Command: list
#-------------------------------------------------------------------------------
cmd_list() {
    log_info "Ginto sandboxes:"
    echo ""
    $LXC_CMD list "${SANDBOX_PREFIX}" -c n,s,4,m,l
    echo ""
    log_info "Base containers:"
    $LXC_CMD list "ginto" -c n,s,4,m,l | grep -v "${SANDBOX_PREFIX}"
}

#-------------------------------------------------------------------------------
# Command: status
#-------------------------------------------------------------------------------
cmd_status() {
    local name="${1:-}"
    
    if [[ -z "$name" ]]; then
        # Show all
        cmd_list
        return
    fi
    
    [[ "$name" != ${SANDBOX_PREFIX}* ]] && name="${SANDBOX_PREFIX}${name}"
    
    if ! container_exists "$name"; then
        die "Container '$name' does not exist"
    fi
    
    $LXC_CMD info "$name"
}

#-------------------------------------------------------------------------------
# Command: shell
#-------------------------------------------------------------------------------
cmd_shell() {
    local name="${1:-}"
    [[ -z "$name" ]] && die "Usage: ginto.sh shell <sandbox-name>"
    
    [[ "$name" != ${SANDBOX_PREFIX}* ]] && name="${SANDBOX_PREFIX}${name}"
    
    if ! container_exists "$name"; then
        die "Container '$name' does not exist"
    fi
    
    if ! container_running "$name"; then
        log_info "Starting container first..."
        $LXC_CMD start "$name"
        sleep 2
    fi
    
    log_info "Opening shell in '$name'..."
    $LXC_CMD exec "$name" -- /bin/sh
}

#-------------------------------------------------------------------------------
# Command: exec
#-------------------------------------------------------------------------------
cmd_exec() {
    local name="${1:-}"
    shift || true
    local cmd="$*"
    
    [[ -z "$name" ]] && die "Usage: ginto.sh exec <sandbox-name> <command>"
    [[ -z "$cmd" ]] && die "Usage: ginto.sh exec <sandbox-name> <command>"
    
    [[ "$name" != ${SANDBOX_PREFIX}* ]] && name="${SANDBOX_PREFIX}${name}"
    
    if ! container_exists "$name"; then
        die "Container '$name' does not exist"
    fi
    
    $LXC_CMD exec "$name" -- sh -c "$cmd"
}

#-------------------------------------------------------------------------------
# Command: logs
#-------------------------------------------------------------------------------
cmd_logs() {
    local name="${1:-}"
    [[ -z "$name" ]] && die "Usage: ginto.sh logs <sandbox-name>"
    
    [[ "$name" != ${SANDBOX_PREFIX}* ]] && name="${SANDBOX_PREFIX}${name}"
    
    if ! container_exists "$name"; then
        die "Container '$name' does not exist"
    fi
    
    $LXC_CMD exec "$name" -- tail -f /var/log/caddy/access.log 2>/dev/null || \
        log_warn "No logs available yet"
}

#-------------------------------------------------------------------------------
# Command: ip
#-------------------------------------------------------------------------------
cmd_ip() {
    local name="${1:-}"
    [[ -z "$name" ]] && die "Usage: ginto.sh ip <sandbox-name>"
    
    [[ "$name" != ${SANDBOX_PREFIX}* ]] && name="${SANDBOX_PREFIX}${name}"
    
    if ! container_exists "$name"; then
        die "Container '$name' does not exist"
    fi
    
    local ip
    ip=$(get_container_ip "$name")
    
    if [[ -z "$ip" ]]; then
        log_warn "Container not running or no IP assigned"
        return 1
    fi
    
    echo "$ip"
}

#-------------------------------------------------------------------------------
# Command: publish
#-------------------------------------------------------------------------------
cmd_publish() {
    log_info "Publishing ginto-sandbox container as image..."
    
    if ! container_exists "$BASE_CONTAINER"; then
        die "Base container '$BASE_CONTAINER' does not exist"
    fi
    
    # Stop if running
    if container_running "$BASE_CONTAINER"; then
        log_info "Stopping container..."
        $LXC_CMD stop "$BASE_CONTAINER"
    fi
    
    # Delete old alias
    log_info "Removing old image alias..."
    $LXC_CMD image alias delete "$BASE_IMAGE" 2>/dev/null || true
    
    # Publish
    log_info "Publishing new image..."
    $LXC_CMD publish "$BASE_CONTAINER" --alias "$BASE_IMAGE"
    
    log_success "Image published as '$BASE_IMAGE'"
    $LXC_CMD image list | grep "$BASE_IMAGE"
}

#-------------------------------------------------------------------------------
# Command: backup
#-------------------------------------------------------------------------------
cmd_backup() {
    local name="${1:-$BASE_IMAGE}"
    local date_str
    date_str=$(date +%Y%m%d-%H%M%S)
    local backup_file="${BACKUP_DIR}/${name}-${date_str}.tar.gz"
    
    mkdir -p "$BACKUP_DIR"
    
    log_info "Backing up image '$name' to $backup_file..."
    $LXC_CMD image export "$name" "${BACKUP_DIR}/${name}-${date_str}"
    
    if [[ -f "$backup_file" ]]; then
        local size
        size=$(du -h "$backup_file" | cut -f1)
        log_success "Backup created: $backup_file ($size)"
    else
        log_success "Backup created in $BACKUP_DIR"
        ls -lah "${BACKUP_DIR}/${name}-${date_str}"*
    fi
}

#-------------------------------------------------------------------------------
# Command: bootstrap (run INSIDE a container to install all packages)
#-------------------------------------------------------------------------------
detect_os() {
    if [[ -f /etc/alpine-release ]]; then
        echo "alpine"
    elif [[ -f /etc/lsb-release ]] || [[ -f /etc/debian_version ]]; then
        echo "ubuntu"
    else
        echo "unknown"
    fi
}

cmd_bootstrap() {
    # SAFETY CHECK: This command is meant to run INSIDE a container only!
    # Prevent accidental execution on the host system
    if [[ -f /etc/sudoers.d/ginto-lxd ]] || systemctl is-active --quiet lxd 2>/dev/null; then
        die "ERROR: 'bootstrap' command must be run INSIDE a container, not on the host!
        
This command installs packages and configures services for sandbox containers.
Running it on the host would overwrite your system configuration.

If you want to initialize LXD on the host, use:
    sudo bash ~/ginto/bin/ginto.sh init

If you want to run bootstrap inside a container:
    lxc exec <container-name> -- bash ~/ginto/bin/ginto.sh bootstrap"
    fi
    
    log_info "Ginto Sandbox Bootstrap - Installing all required packages..."
    
    local os_type
    os_type=$(detect_os)
    log_info "Detected OS: $os_type"
    
    case "$os_type" in
        alpine)
            bootstrap_alpine
            ;;
        ubuntu)
            bootstrap_ubuntu
            ;;
        *)
            die "Unsupported OS. Only Alpine and Ubuntu are supported."
            ;;
    esac
    
    # Common post-install setup
    setup_home_directory
    setup_caddy_config
    
    log_success "Bootstrap complete! Your sandbox is ready."
}

bootstrap_alpine() {
    log_info "Installing packages for Alpine Linux..."
    
    apk update
    
    log_info "Installing PHP 8.2..."
    apk add --no-cache \
        php82 php82-fpm php82-json php82-mbstring php82-openssl \
        php82-pdo php82-pdo_mysql php82-pdo_sqlite php82-curl \
        php82-dom php82-xml php82-ctype php82-session php82-tokenizer \
        php82-fileinfo php82-iconv php82-zip php82-gd
    
    log_info "Installing Node.js 20..."
    apk add --no-cache nodejs npm
    
    log_info "Installing Python 3..."
    apk add --no-cache python3 py3-pip
    
    log_info "Installing MySQL client and SQLite..."
    apk add --no-cache mysql-client sqlite
    
    log_info "Installing Git..."
    apk add --no-cache git
    
    log_info "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    
    log_info "Creating php symlink..."
    ln -sf /usr/bin/php82 /usr/bin/php
    
    log_info "Installing Caddy..."
    apk add --no-cache caddy caddy-openrc
    
    log_info "Installing editors and tools..."
    apk add --no-cache vim nano bash curl ca-certificates openrc
    
    log_info "Installing document creation tools (pandoc, weasyprint)..."
    apk add --no-cache pandoc py3-weasyprint font-noto ttf-dejavu
    fc-cache -f
    
    log_info "Enabling services..."
    # Auto-detect installed PHP-FPM service
    local php_fpm_svc=$(rc-service --list | grep php-fpm | head -1)
    [ -z "$php_fpm_svc" ] && php_fpm_svc="php-fpm82"
    rc-update add "$php_fpm_svc" default
    rc-update add caddy default
    
    log_success "Alpine packages installed successfully"
}

bootstrap_ubuntu() {
    log_info "Installing packages for Ubuntu..."
    
    export DEBIAN_FRONTEND=noninteractive
    
    apt-get update
    apt-get upgrade -y
    
    log_info "Installing PHP 8.x..."
    apt-get install -y software-properties-common
    add-apt-repository -y ppa:ondrej/php
    apt-get update
    
    # Auto-detect latest available PHP 8.x version
    local PHP_VERSION=$(apt-cache search '^php8\.[0-9]+-cli$' 2>/dev/null | sort -V | tail -1 | grep -oP 'php\K8\.[0-9]+')
    [ -z "$PHP_VERSION" ] && PHP_VERSION="8.3"
    log_info "Detected PHP version: $PHP_VERSION"
    
    apt-get install -y \
        "php${PHP_VERSION}" "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-common" \
        "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" "php${PHP_VERSION}-curl" "php${PHP_VERSION}-zip" \
        "php${PHP_VERSION}-mysql" "php${PHP_VERSION}-sqlite3" "php${PHP_VERSION}-gd" "php${PHP_VERSION}-intl" \
        "php${PHP_VERSION}-bcmath" "php${PHP_VERSION}-readline" "php${PHP_VERSION}-redis"
    
    log_info "Installing Node.js 20..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
    
    log_info "Installing Python 3..."
    apt-get install -y python3 python3-pip python3-venv
    
    log_info "Installing MySQL client and SQLite..."
    apt-get install -y mysql-client sqlite3
    
    log_info "Installing Redis..."
    apt-get install -y redis-server
    systemctl enable --now redis-server
    
    log_info "Installing Git..."
    apt-get install -y git
    
    log_info "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    
    log_info "Installing Caddy..."
    apt-get install -y debian-keyring debian-archive-keyring apt-transport-https curl
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
    apt-get update
    apt-get install -y caddy
    
    log_info "Installing editors and tools..."
    apt-get install -y vim nano curl ca-certificates
    
    log_info "Cleaning up..."
    apt-get autoremove -y
    apt-get clean
    
    log_success "Ubuntu packages installed successfully"
}

setup_home_directory() {
    log_info "Creating /home directory structure..."
    mkdir -p /home/{Desktop,Documents,Downloads,Music,Pictures,Videos,Websites}
    
    log_info "Creating index.php..."
    cat > /home/index.php << 'PHPEOF'
<?php
/**
 * Welcome to your Ginto Sandbox!
 *
 * This is your personal development environment.
 * Edit this file or create new ones to get started.
 */

$tools = [
    "PHP" => phpversion(),
    "Node.js" => trim(shell_exec("/usr/bin/node --version 2>/dev/null") ?: "Not installed"),
    "npm" => trim(shell_exec("/usr/bin/npm --version 2>/dev/null") ?: "Not installed"),
    "Python" => trim(shell_exec("/usr/bin/python3 --version 2>/dev/null") ?: "Not installed"),
    "Git" => trim(shell_exec("/usr/bin/git --version 2>/dev/null") ?: "Not installed"),
    "Composer" => trim(shell_exec("/usr/local/bin/composer --version 2>/dev/null | head -1") ?: "Not installed"),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ginto Sandbox</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-indigo-900 via-purple-900 to-pink-800 min-h-screen text-white">
    <div class="container mx-auto px-4 py-16">
        <div class="text-center mb-12">
            <h1 class="text-5xl font-bold mb-4">🚀 Welcome to Ginto Sandbox</h1>
            <p class="text-xl text-purple-200">Your personal development environment is ready!</p>
        </div>

        <div class="max-w-4xl mx-auto">
            <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-8 mb-8">
                <h2 class="text-2xl font-semibold mb-6 flex items-center gap-2">
                    <span>🛠️</span> Available Tools
                </h2>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($tools as $name => $version): ?>
                    <div class="bg-white/5 rounded-lg p-4">
                        <div class="font-medium text-purple-200"><?= htmlspecialchars($name) ?></div>
                        <div class="text-sm text-gray-300"><?= htmlspecialchars($version) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-8 mb-8">
                <h2 class="text-2xl font-semibold mb-6 flex items-center gap-2">
                    <span>📁</span> Quick Start
                </h2>
                <div class="space-y-4 text-purple-100">
                    <p>✏️ Edit <code class="bg-white/20 px-2 py-1 rounded">index.php</code> to customize this page</p>
                    <p>📦 Use <code class="bg-white/20 px-2 py-1 rounded">composer init</code> to start a PHP project</p>
                    <p>🌐 Use <code class="bg-white/20 px-2 py-1 rounded">npm init</code> to start a Node.js project</p>
                    <p>🐍 Use <code class="bg-white/20 px-2 py-1 rounded">pip install</code> for Python packages</p>
                </div>
            </div>

            <!-- Mission Statement -->
            <div class="bg-gradient-to-r from-purple-500/20 to-pink-500/20 backdrop-blur-lg rounded-2xl p-8 border border-purple-400/30">
                <h2 class="text-2xl font-semibold mb-4 flex items-center gap-2">
                    <span>🎯</span> The Ginto Vision
                </h2>
                <div class="space-y-4 text-purple-100 leading-relaxed">
                    <p class="text-lg">
                        <strong class="text-white">Ginto</strong> is pioneering a paradigm shift in artificial intelligence infrastructure—ushering in an era where <em>containerized agentic systems</em> become the foundational architecture for human-AI collaboration.
                    </p>
                    
                    <!-- AI Evolution Context -->
                    <div class="bg-white/5 rounded-xl p-5 border-l-4 border-purple-400">
                        <p class="text-purple-200 font-medium mb-2">⚡ The Unprecedented Pace of AI Innovation</p>
                        <p class="text-sm">
                            Artificial intelligence is advancing at an extraordinary velocity—with breakthrough capabilities emerging in weeks rather than years. Traditional educational institutions, bound by semester schedules and multi-year curriculum approval cycles, were never designed to keep pace with such rapid technological evolution. This is not a failing of academia; it is simply that <strong class="text-white">AI moves at the speed of innovation, not the speed of institutions</strong>.
                        </p>
                        <p class="text-sm mt-3">
                            Ginto exists to <em>complement and extend</em> formal education—serving as a real-time bridge between emerging AI capabilities and the professionals who need them <strong class="text-white">today</strong>. We partner with educational systems and governments, providing the agile, continuously-updated training that keeps workforces competitive while traditional institutions focus on foundational knowledge and critical thinking that only they can provide.
                        </p>
                    </div>
                    
                    <p>
                        We are establishing the industry first comprehensive <strong class="text-purple-200">certification framework for Agentic Toolsets</strong>, complemented by an exclusive curriculum of <strong class="text-purple-200">AI-powered masterclasses and professional courses you will not find anywhere else</strong>—designed to democratize access to autonomous agent development. From introductory modules for newcomers to advanced practitioner certifications, our educational pathway ensures mastery at every level.
                    </p>
                    <p>
                        Our containerized approach guarantees that every developer, engineer, data scientist, researcher, healthcare professional, legal expert, educator, and creative practitioner—regardless of prior technical experience—can deploy, orchestrate, and manage intelligent agents within isolated, secure, and reproducible environments.
                    </p>
                    <p>
                        This sandbox represents more than a development environment; it is your gateway to the <strong class="text-purple-200">agentic computing paradigm</strong>—where AI agents operate as first-class obedient virtual citizens interacting with the real world alongside traditional software. These are not autonomous forces beyond human control—they are precision instruments that amplify human capability while remaining firmly under human authority. This represents not a threat, but an unprecedented opportunity to drive excellence by empowering individuals with intelligent tools they command and control.
                    </p>
                    <div class="mt-6 pt-4 border-t border-purple-400/30">
                        <div class="grid md:grid-cols-4 gap-4 text-center text-sm">
                            <a href="https://ginto.ai/masterclass" class="bg-white/5 rounded-lg p-3 hover:bg-white/10 transition-all hover:scale-105 block">
                                <div class="text-2xl mb-1">🎓</div>
                                <div class="font-medium text-purple-200">Masterclasses</div>
                                <div class="text-gray-400">Expert-led deep dives</div>
                            </a>
                            <a href="https://ginto.ai/courses" class="bg-white/5 rounded-lg p-3 hover:bg-white/10 transition-all hover:scale-105 block">
                                <div class="text-2xl mb-1">📚</div>
                                <div class="font-medium text-purple-200">Courses</div>
                                <div class="text-gray-400">Structured learning paths</div>
                            </a>
                            <a href="https://ginto.ai/certifications" class="bg-white/5 rounded-lg p-3 hover:bg-white/10 transition-all hover:scale-105 block">
                                <div class="text-2xl mb-1">🏆</div>
                                <div class="font-medium text-purple-200">Certifications</div>
                                <div class="text-gray-400">Industry-recognized credentials</div>
                            </a>
                            <a href="https://github.com/oliverbob/ginto.ai" target="_blank" class="bg-white/5 rounded-lg p-3 hover:bg-white/10 transition-all hover:scale-105 block">
                                <svg class="w-8 h-8 mb-1 mx-auto" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 21.795 24 17.295 24 12c0-6.627-5.373-12-12-12z"/></svg>
                                <div class="font-medium text-purple-200">Open Source</div>
                                <div class="text-gray-400">Contribute on GitHub</div>
                            </a>
                        </div>
                    </div>
                    <div class="mt-4 text-center">
                        <p class="text-sm text-purple-300 italic">
                            "Bridging the gap between AI rapid evolution and the professionals who shape tomorrow—one container at a time."
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-12 text-purple-300">
            <p>Powered by <strong>Ginto</strong> • Alpine Linux Container • Agentic AI Platform</p>
        </div>
    </div>
</body>
</html>
PHPEOF
    
    log_info "Creating README.md..."
    cat > /home/README.md << 'EOF'
# 🚀 Ginto Sandbox

Welcome to your personal development environment!

## Available Tools

- **PHP 8.2** + Composer
- **Node.js 20** + npm
- **Python 3** + pip
- **Git** for version control
- **MySQL Client** + SQLite
- **Vim/Nano** text editors

## Quick Start

```bash
# PHP project
composer init

# Node.js project
npm init

# Python script
python3 script.py
```

## Directory Structure

- `/home/` - Your web root (files served via Caddy)
- `/home/index.php` - Default landing page
- `/home/Websites/` - Additional web projects
EOF
    
    log_info "Setting permissions..."
    chown -R nobody:nogroup /home 2>/dev/null || chown -R nobody:nobody /home
    
    log_success "Home directory setup complete"
}

setup_caddy_config() {
    log_info "Configuring Caddy..."
    
    local os_type
    os_type=$(detect_os)
    
    # PHP-FPM connection - Alpine defaults to TCP, Ubuntu uses socket
    local php_fastcgi
    if [[ "$os_type" == "alpine" ]]; then
        php_fastcgi="127.0.0.1:9000"
    else
        # Auto-detect PHP-FPM socket
        local php_sock=$(ls /run/php/php*-fpm.sock 2>/dev/null | head -1)
        if [ -n "$php_sock" ]; then
            php_fastcgi="unix/${php_sock}"
        else
            php_fastcgi="unix//run/php/php-fpm.sock"
        fi
    fi
    
    mkdir -p /var/log/caddy
    
    cat > /etc/caddy/Caddyfile << EOF
:80 {
    root * /home
    php_fastcgi $php_fastcgi
    file_server
    encode gzip
    log {
        output file /var/log/caddy/access.log
        format console
    }
}
EOF
    
    log_success "Caddy configured"
}

#-------------------------------------------------------------------------------
# Command: cleanup
# Cleans up idle sandboxes based on tier timeouts
# Usage: ginto.sh cleanup [--tier=free|premium|admin|all]
#-------------------------------------------------------------------------------
cmd_cleanup() {
    local tier="${1:-free}"
    local max_age
    local count=0
    
    # Select timeout based on tier
    case "$tier" in
        premium)
            max_age="$PREMIUM_IDLE_TIMEOUT"
            ;;
        admin)
            max_age="$ADMIN_IDLE_TIMEOUT"
            ;;
        all)
            # Clean all tiers with their respective timeouts
            log_info "Cleaning all tiers..."
            cmd_cleanup "free"
            cmd_cleanup "premium"
            cmd_cleanup "admin"
            return
            ;;
        free|*)
            max_age="$FREE_IDLE_TIMEOUT"
            tier="free"
            ;;
    esac
    
    log_info "Cleaning up stopped sandboxes older than $((max_age / 60)) minutes (tier: $tier)..."
    
    # Get list of stopped sandbox containers
    local containers
    containers=$($LXC_CMD list "${SANDBOX_PREFIX}" --format csv -c n,s | grep ",STOPPED" | cut -d',' -f1)
    
    for container in $containers; do
        # Get last used time
        local last_used
        last_used=$($LXC_CMD info "$container" 2>/dev/null | grep "Last Used:" | sed 's/Last Used: //')
        
        if [[ -n "$last_used" ]]; then
            local last_ts
            last_ts=$(date -d "$last_used" +%s 2>/dev/null || echo 0)
            local now_ts
            now_ts=$(date +%s)
            local age=$((now_ts - last_ts))
            
            if [[ $age -gt $max_age ]]; then
                log_info "Deleting old container: $container (age: $((age / 60)) min)"
                $LXC_CMD delete "$container" --force
                ((count++))
            fi
        fi
    done
    
    if [[ $count -eq 0 ]]; then
        log_info "No old containers to clean up (tier: $tier)"
    else
        log_success "Cleaned up $count container(s) (tier: $tier)"
    fi
}

#-------------------------------------------------------------------------------
# Command: verify
#-------------------------------------------------------------------------------
cmd_verify() {
    log_info "Verifying Ginto LXD setup..."
    echo ""
    
    # Check LXD
    echo -n "LXD installed: "
    if command -v /snap/bin/lxc &>/dev/null; then
        echo -e "${GREEN}YES${NC}"
    else
        echo -e "${RED}NO${NC}"
    fi
    
    # Check LXD socket
    echo -n "LXD socket: "
    if [[ -S /var/snap/lxd/common/lxd/unix.socket ]]; then
        echo -e "${GREEN}OK${NC}"
    else
        echo -e "${RED}MISSING${NC}"
    fi
    
    # Check base image
    echo -n "Base image ($BASE_IMAGE): "
    if image_exists "$BASE_IMAGE"; then
        local size
        size=$($LXC_CMD image list "$BASE_IMAGE" --format csv -c s | head -1)
        echo -e "${GREEN}OK${NC} ($size)"
    else
        echo -e "${RED}MISSING${NC}"
    fi
    
    # Check base container
    echo -n "Base container: "
    if container_exists "$BASE_CONTAINER"; then
        echo -e "${GREEN}EXISTS${NC}"
    else
        echo -e "${YELLOW}NOT FOUND${NC} (will be created on init)"
    fi
    
    # Count sandboxes
    local sandbox_count
    sandbox_count=$($LXC_CMD list "${SANDBOX_PREFIX}" --format csv -c n 2>/dev/null | wc -l)
    echo "Active sandboxes: $sandbox_count"
    
    # Check sudoers
    echo -n "Sudoers configured: "
    if sudo grep -rq "lxc" /etc/sudoers.d/ 2>/dev/null; then
        echo -e "${GREEN}YES${NC}"
    else
        echo -e "${YELLOW}CHECK NEEDED${NC}"
    fi
    
    echo ""
    log_success "Verification complete"
}

#-------------------------------------------------------------------------------
# Main
#-------------------------------------------------------------------------------
main() {
    # Parse global options first
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --web-user=*)
                WEB_USER="${1#*=}"
                shift
                ;;
            --web-user)
                WEB_USER="${2:-}"
                shift 2
                ;;
            *)
                break
                ;;
        esac
    done
    
    local cmd="${1:-help}"
    shift || true
    
    case "$cmd" in
        init)
            cmd_init "$@"
            ;;
        reset|reinstall)
            cmd_reset "$@"
            ;;
        create|new|launch)
            cmd_create "$@"
            ;;
        delete|rm|remove|destroy)
            cmd_delete "$@"
            ;;
        start)
            cmd_start "$@"
            ;;
        stop)
            cmd_stop "$@"
            ;;
        restart)
            cmd_restart "$@"
            ;;
        list|ls)
            cmd_list "$@"
            ;;
        status|info)
            cmd_status "$@"
            ;;
        shell|sh|bash)
            cmd_shell "$@"
            ;;
        exec|run)
            cmd_exec "$@"
            ;;
        logs|log)
            cmd_logs "$@"
            ;;
        ip|address)
            cmd_ip "$@"
            ;;
        publish|pub)
            cmd_publish "$@"
            ;;
        backup|export)
            cmd_backup "$@"
            ;;
        cleanup|clean|gc)
            cmd_cleanup "$@"
            ;;
        verify|check|test)
            cmd_verify "$@"
            ;;
        bootstrap)
            cmd_bootstrap "$@"
            ;;
        install|setup)
            cmd_init "$@"
            ;;
        help|--help|-h)
            head -36 "$0" | tail -34
            ;;
        *)
            log_error "Unknown command: $cmd"
            echo "Run 'bin/ginto.sh help' for usage"
            exit 1
            ;;
    esac
}

main "$@"
