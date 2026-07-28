#!/bin/bash
#
# Ginto Network Setup
# Configures network mode for LXD sandbox containers
#
# Network Modes:
#   bridge  - Default LXD bridge (lxdbr0), 10.0.0.0/8, 16.7M IPs
#   nat     - NAT mode with port isolation, 10.0.0.0/8, 16.7M IPs
#   macvlan - Macvlan on dummy interface, full 32-bit, 4.3B IPs
#   ipvlan  - IPVLAN on dummy interface, full 32-bit, 4.3B IPs (L3 mode)
#
# Usage: 
#   sudo ./setup_network.sh [mode|status|teardown]
#   sudo ./setup_network.sh bridge    # Switch to bridge mode
#   sudo ./setup_network.sh nat       # Switch to NAT mode
#   sudo ./setup_network.sh macvlan   # Switch to macvlan mode
#   sudo ./setup_network.sh ipvlan    # Switch to ipvlan mode
#   sudo ./setup_network.sh status    # Show current configuration
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
ENV_FILE="${PROJECT_ROOT}/.env"

DUMMY_IF="ginto-macvlan0"
MACVLAN_NET="ginto-macvlan"
SHIM_IF="ginto-shim"
SHIM_IP="0.0.0.1"  # Host's IP on the virtual network

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }
log_step() { echo -e "${BLUE}[STEP]${NC} $1"; }

check_root() {
    if [ "$EUID" -ne 0 ]; then
        log_error "Please run as root (sudo)"
        exit 1
    fi
}

# Update .env file with network mode
update_env() {
    local mode="$1"
    
    if [ ! -f "$ENV_FILE" ]; then
        echo "# Ginto Environment Configuration" > "$ENV_FILE"
    fi
    
    # Remove existing LXD_NETWORK_MODE line
    sed -i '/^LXD_NETWORK_MODE=/d' "$ENV_FILE"
    
    # Add new value
    echo "LXD_NETWORK_MODE=${mode}" >> "$ENV_FILE"
    log_info "Updated .env: LXD_NETWORK_MODE=${mode}"
}

# Get current network mode from .env
get_current_mode() {
    if [ -f "$ENV_FILE" ]; then
        grep -E '^LXD_NETWORK_MODE=' "$ENV_FILE" 2>/dev/null | cut -d= -f2 || echo "bridge"
    else
        echo "bridge"
    fi
}

setup_dummy() {
    log_step "Creating dummy interface ${DUMMY_IF}..."
    
    # Load dummy module if needed
    modprobe dummy 2>/dev/null || true
    
    # Check if already exists
    if ip link show ${DUMMY_IF} &>/dev/null; then
        log_warn "${DUMMY_IF} already exists"
    else
        ip link add ${DUMMY_IF} type dummy
        log_info "Created ${DUMMY_IF}"
    fi
    
    # Configure with /0 (full IPv4 range)
    ip link set ${DUMMY_IF} up
    
    # Add /0 address if not present
    if ! ip addr show ${DUMMY_IF} | grep -q "0.0.0.2/0"; then
        ip addr add 0.0.0.2/0 dev ${DUMMY_IF} 2>/dev/null || true
        log_info "Added 0.0.0.2/0 to ${DUMMY_IF}"
    fi
    
    log_info "Dummy interface ready: ${DUMMY_IF}"
}

setup_shim() {
    log_info "Creating shim interface ${SHIM_IF}..."
    
    # Check if already exists
    if ip link show ${SHIM_IF} &>/dev/null; then
        log_warn "${SHIM_IF} already exists"
    else
        # Create macvlan shim on the dummy interface
        ip link add ${SHIM_IF} link ${DUMMY_IF} type macvlan mode bridge
        log_info "Created ${SHIM_IF}"
    fi
    
    # Configure shim
    ip link set ${SHIM_IF} up
    
    # Add IP for host communication
    if ! ip addr show ${SHIM_IF} | grep -q "${SHIM_IP}/32"; then
        ip addr add ${SHIM_IP}/32 dev ${SHIM_IF} 2>/dev/null || true
        log_info "Added ${SHIM_IP}/32 to ${SHIM_IF}"
    fi
    
    log_info "Shim interface ready: ${SHIM_IF}"
}

setup_lxd_network() {
    log_info "Creating LXD macvlan network ${MACVLAN_NET}..."
    
    # Check if LXD network already exists
    if lxc network show ${MACVLAN_NET} &>/dev/null; then
        log_warn "LXD network ${MACVLAN_NET} already exists"
    else
        lxc network create ${MACVLAN_NET} --type=macvlan parent=${DUMMY_IF}
        log_info "Created LXD network ${MACVLAN_NET}"
    fi
}

setup_persistence() {
    log_info "Setting up persistence..."
    
    # Create systemd service for persistence across reboots
    cat > /etc/systemd/system/ginto-macvlan.service << 'EOF'
[Unit]
Description=Ginto Macvlan Network Setup
After=network.target
Before=snap.lxd.daemon.service

[Service]
Type=oneshot
RemainAfterExit=yes
ExecStart=/bin/bash -c '\
    modprobe dummy 2>/dev/null || true; \
    ip link add ginto-macvlan0 type dummy 2>/dev/null || true; \
    ip link set ginto-macvlan0 up; \
    ip addr add 0.0.0.2/0 dev ginto-macvlan0 2>/dev/null || true; \
    ip link add ginto-shim link ginto-macvlan0 type macvlan mode bridge 2>/dev/null || true; \
    ip link set ginto-shim up; \
    ip addr add 0.0.0.1/32 dev ginto-shim 2>/dev/null || true'
ExecStop=/bin/bash -c '\
    ip link del ginto-shim 2>/dev/null || true; \
    ip link del ginto-macvlan0 2>/dev/null || true'

[Install]
WantedBy=multi-user.target
EOF
    
    systemctl daemon-reload
    systemctl enable ginto-macvlan.service
    log_info "Systemd service created and enabled"
}

add_route() {
    local ip=$1
    
    if [ -z "$ip" ]; then
        log_error "Usage: $0 add-route <ip>"
        exit 1
    fi
    
    # Add route through shim interface
    if ! ip route show | grep -q "$ip"; then
        ip route add ${ip}/32 dev ${SHIM_IF} 2>/dev/null || true
        log_info "Added route for ${ip} via ${SHIM_IF}"
    fi
}

remove_route() {
    local ip=$1
    
    if [ -z "$ip" ]; then
        log_error "Usage: $0 remove-route <ip>"
        exit 1
    fi
    
    ip route del ${ip}/32 dev ${SHIM_IF} 2>/dev/null || true
    log_info "Removed route for ${ip}"
}

status() {
    local current_mode=$(get_current_mode)
    
    echo "=== Ginto Network Status ==="
    echo ""
    echo "Current Mode: ${current_mode^^}"  # uppercase
    
    case "$current_mode" in
        macvlan)
            echo "IP Range: Full 32-bit (4.3 billion unique IPs)"
            echo "Layer: L2 (Ethernet)"
            echo "NAT: No (containers have unique public-like IPs)"
            ;;
        ipvlan)
            echo "IP Range: Full 32-bit (4.3 billion unique IPs)"
            echo "Layer: L3 (IP routing)"
            echo "NAT: No (containers have unique public-like IPs)"
            ;;
        nat)
            echo "IP Range: 10.0.0.0/8 (16.7 million unique IPs)"
            echo "NAT: Yes (outbound via host IP)"
            ;;
        bridge|*)
            echo "IP Range: 10.0.0.0/8 (16.7 million unique IPs)"
            echo "NAT: No (direct bridge routing)"
            ;;
    esac
    echo ""
    
    # Check NAT status on lxdbr0
    echo "LXD Bridge (lxdbr0):"
    if command -v lxc &>/dev/null && lxc network show lxdbr0 &>/dev/null; then
        local nat_enabled=$(lxc network get lxdbr0 ipv4.nat 2>/dev/null || echo "unknown")
        local ipv4_addr=$(lxc network get lxdbr0 ipv4.address 2>/dev/null || echo "unknown")
        echo "  IPv4 Address: ${ipv4_addr}"
        echo "  NAT Enabled: ${nat_enabled}"
    else
        echo "  NOT CONFIGURED"
    fi
    echo ""
    
    echo "Macvlan Infrastructure:"
    echo "  Dummy Interface (${DUMMY_IF}):"
    if ip link show ${DUMMY_IF} &>/dev/null; then
        ip addr show ${DUMMY_IF} | grep -E "inet|state" | sed 's/^/    /'
    else
        echo "    NOT CONFIGURED"
    fi
    
    echo "  Shim Interface (${SHIM_IF}):"
    if ip link show ${SHIM_IF} &>/dev/null; then
        ip addr show ${SHIM_IF} | grep -E "inet|state" | sed 's/^/    /'
    else
        echo "    NOT CONFIGURED"
    fi
    
    echo "  LXD Network (${MACVLAN_NET}):"
    if command -v lxc &>/dev/null; then
        lxc network show ${MACVLAN_NET} 2>/dev/null | head -5 | sed 's/^/    /' || echo "    NOT CONFIGURED"
    else
        echo "    LXC not available"
    fi
    echo ""
    
    echo "IPVLAN Infrastructure:"
    echo "  IPVLAN Shim (${IPVLAN_SHIM}):"
    if ip link show ${IPVLAN_SHIM} &>/dev/null; then
        ip addr show ${IPVLAN_SHIM} | grep -E "inet|state" | sed 's/^/    /'
    else
        echo "    NOT CONFIGURED"
    fi
    
    echo "  LXD Network (${IPVLAN_NET}):"
    if command -v lxc &>/dev/null; then
        lxc network show ${IPVLAN_NET} 2>/dev/null | head -5 | sed 's/^/    /' || echo "    NOT CONFIGURED"
    else
        echo "    LXC not available"
    fi
    echo ""
    
    echo "Routes via shim:"
    ip route show | grep -E "${SHIM_IF}|${IPVLAN_SHIM}" 2>/dev/null | head -20 || echo "  No routes"
}

teardown_macvlan() {
    log_step "Tearing down macvlan network..."
    
    # Remove LXD network
    if command -v lxc &>/dev/null && lxc network show ${MACVLAN_NET} &>/dev/null; then
        lxc network delete ${MACVLAN_NET} 2>/dev/null || log_warn "Could not delete LXD network"
    fi
    
    # Remove shim
    ip link del ${SHIM_IF} 2>/dev/null || true
    
    # Remove dummy
    ip link del ${DUMMY_IF} 2>/dev/null || true
    
    # Disable service
    systemctl disable ginto-macvlan.service 2>/dev/null || true
    rm -f /etc/systemd/system/ginto-macvlan.service
    systemctl daemon-reload
    
    log_info "Macvlan teardown complete"
}

setup_macvlan() {
    check_root
    setup_dummy
    setup_shim
    setup_lxd_network
    setup_persistence
    update_env "macvlan"
    
    echo ""
    log_info "=== Macvlan network setup complete ==="
    log_info "Dummy interface: ${DUMMY_IF} (0.0.0.0/0 - full IPv4 range)"
    log_info "Shim interface: ${SHIM_IF} (for host-to-container communication)"
    log_info "LXD network: ${MACVLAN_NET} (use with containers)"
    log_info "IP Range: 4.3 billion unique IPs (full 32-bit)"
    echo ""
    log_info "To add a route for a container IP:"
    log_info "  $0 add-route <container-ip>"
}

setup_bridge() {
    log_step "Setting up bridge network mode..."
    
    # Ensure lxdbr0 exists and NAT is disabled for pure bridge mode
    if command -v lxc &>/dev/null; then
        # Check if lxdbr0 exists
        if lxc network show lxdbr0 &>/dev/null; then
            log_info "lxdbr0 exists, configuring for bridge mode"
            lxc network set lxdbr0 ipv4.nat false 2>/dev/null || true
        fi
    fi
    
    update_env "bridge"
    
    echo ""
    log_info "=== Bridge network setup complete ==="
    log_info "Using default LXD bridge: lxdbr0"
    log_info "IP Range: 10.0.0.0/8 (16.7 million unique IPs)"
    log_info "NAT: Disabled (direct routing)"
    echo ""
}

setup_nat() {
    log_step "Setting up NAT network mode..."
    
    # NAT mode uses lxdbr0 with NAT enabled
    if command -v lxc &>/dev/null; then
        # Check if lxdbr0 exists
        if lxc network show lxdbr0 &>/dev/null; then
            log_info "lxdbr0 exists, enabling NAT"
            lxc network set lxdbr0 ipv4.nat true 2>/dev/null || true
        else
            log_info "Creating lxdbr0 with NAT enabled"
            lxc network create lxdbr0 ipv4.address=10.166.3.1/24 ipv4.nat=true 2>/dev/null || true
        fi
    fi
    
    update_env "nat"
    
    echo ""
    log_info "=== NAT network setup complete ==="
    log_info "Using LXD bridge: lxdbr0 with NAT"
    log_info "IP Range: 10.0.0.0/8 (16.7 million unique IPs)"
    log_info "NAT: Enabled (outbound traffic uses host IP)"
    log_info ""
    log_info "How NAT works:"
    log_info "  - Containers get private IPs (10.x.x.x)"
    log_info "  - Outbound traffic appears from host's public IP"
    log_info "  - Inbound routing handled by sandbox-proxy"
    echo ""
}

IPVLAN_NET="ginto-ipvlan"
IPVLAN_DUMMY="ginto-ipvlan0"

setup_ipvlan() {
    log_step "Setting up IPVLAN network mode..."
    check_root
    
    # IPVLAN mode reuses the macvlan shim (ginto-shim) for host routing
    # because nested LXD can't create new macvlan/ipvlan interfaces
    # But uses its own dummy interface for container networking
    
    # First ensure macvlan infrastructure exists (for the shim)
    setup_dummy
    setup_shim
    
    # Create dedicated dummy interface for ipvlan containers
    log_step "Creating dummy interface ${IPVLAN_DUMMY}..."
    if ip link show ${IPVLAN_DUMMY} &>/dev/null; then
        log_warn "${IPVLAN_DUMMY} already exists"
    else
        ip link add ${IPVLAN_DUMMY} type dummy
        log_info "Created ${IPVLAN_DUMMY}"
    fi
    ip link set ${IPVLAN_DUMMY} up
    log_info "Dummy interface ready: ${IPVLAN_DUMMY}"
    
    # Note: We reuse ginto-shim for routing (already created by setup_shim)
    log_info "Using existing shim interface ${SHIM_IF} for host routing"
    
    # Create LXD IPVLAN network
    log_step "Creating LXD IPVLAN network..."
    if command -v lxc &>/dev/null; then
        if lxc network show ${IPVLAN_NET} &>/dev/null; then
            log_warn "LXD network ${IPVLAN_NET} already exists"
        else
            lxc network create ${IPVLAN_NET} \
                --type=macvlan \
                parent=${IPVLAN_DUMMY} 2>/dev/null || {
                    log_warn "Failed to create ${IPVLAN_NET}, may need manual setup"
                }
            log_info "Created LXD network: ${IPVLAN_NET}"
        fi
    fi
    
    # Setup persistence
    setup_persistence "ipvlan"
    
    update_env "ipvlan"
    
    echo ""
    log_info "=== IPVLAN network setup complete ==="
    log_info "Mode: IPVLAN L3 (Layer 3)"
    log_info "IP Range: Full 32-bit (4.3 billion unique IPs)"
    log_info "Interface: ${IPVLAN_NET}"
    log_info ""
    log_info "IPVLAN L3 benefits:"
    log_info "  - Works in cloud environments with MAC restrictions"
    log_info "  - Lower overhead than macvlan"
    log_info "  - Better for nested virtualization"
    log_info "  - No broadcast traffic"
    echo ""
}

teardown_ipvlan() {
    log_step "Tearing down IPVLAN infrastructure..."
    
    # Remove LXD network
    if command -v lxc &>/dev/null && lxc network show ${IPVLAN_NET} &>/dev/null; then
        lxc network delete ${IPVLAN_NET} 2>/dev/null || log_warn "Could not delete LXD network"
    fi
    
    # Remove ipvlan shim
    ip link del ${IPVLAN_SHIM} 2>/dev/null || true
    
    log_info "IPVLAN infrastructure removed"
}

show_help() {
    echo "Ginto Network Setup"
    echo ""
    echo "Usage: $0 <command> [options]"
    echo ""
    echo "Commands:"
    echo "  bridge              Switch to bridge mode (16.7M IPs, direct routing)"
    echo "  nat                 Switch to NAT mode (16.7M IPs, host IP for outbound)"
    echo "  macvlan             Switch to macvlan mode (4.3B IPs, Layer 2)"
    echo "  ipvlan              Switch to ipvlan mode (4.3B IPs, Layer 3)"
    echo "  status              Show current network configuration"
    echo "  teardown            Remove macvlan/ipvlan infrastructure"
    echo "  add-route <ip>      Add host route to container IP"
    echo "  remove-route <ip>   Remove host route"
    echo ""
    echo "Current mode: $(get_current_mode)"
}

case "${1:-help}" in
    bridge)
        setup_bridge
        ;;
    nat)
        setup_nat
        ;;
    macvlan)
        setup_macvlan
        ;;
    ipvlan)
        setup_ipvlan
        ;;
    teardown|remove)
        check_root
        teardown_macvlan
        teardown_ipvlan 2>/dev/null || true
        update_env "bridge"
        ;;
    status)
        status
        ;;
    add-route)
        check_root
        add_route "$2"
        ;;
    remove-route)
        check_root
        remove_route "$2"
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
