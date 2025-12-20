#!/usr/bin/env bash
#
# Setup script for Ginto Sandbox Proxy Infrastructure
#
# This installs:
#   1. Redis for user->IP mappings
#   2. Node.js and the sandbox proxy
#   3. Caddy configuration for reverse proxying to the Node.js proxy
#   4. Systemd service for the proxy
#
# Usage:
#   sudo ./setup_sandbox_proxy.sh
#
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"
PROXY_DIR="$REPO_ROOT/tools/sandbox-proxy"
INSTALL_DIR="/opt/ginto-sandbox-proxy"

echo "=== Ginto Sandbox Proxy Setup ==="
echo "Repo root: $REPO_ROOT"
echo "Proxy source: $PROXY_DIR"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "[!] Please run as root (sudo)"
    exit 1
fi

# 1. Install Redis
echo "[1/6] Installing Redis..."
if command -v redis-server &>/dev/null; then
    echo "     Redis already installed"
else
    apt-get update -qq
    apt-get install -y redis-server
fi

systemctl enable redis-server
systemctl start redis-server
echo "     Redis: $(redis-cli ping 2>/dev/null || echo 'not responding')"

# 2. Install Node.js (if not present)
echo "[2/6] Checking Node.js..."
if command -v node &>/dev/null; then
    NODE_VERSION=$(node -v)
    echo "     Node.js already installed: $NODE_VERSION"
else
    echo "     Installing Node.js 20.x..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
fi

# 3. Copy proxy to install directory
echo "[3/6] Installing sandbox proxy..."
mkdir -p "$INSTALL_DIR"
cp "$PROXY_DIR/package.json" "$INSTALL_DIR/"
cp "$PROXY_DIR/sandbox-proxy.js" "$INSTALL_DIR/"

cd "$INSTALL_DIR"
npm install --production
echo "     Installed to $INSTALL_DIR"

# 4. Create systemd service
echo "[4/6] Creating systemd service..."
cat > /etc/systemd/system/ginto-sandbox-proxy.service << 'EOF'
[Unit]
Description=Ginto Sandbox Proxy
After=network.target redis-server.service lxd.service
Wants=redis-server.service

[Service]
Type=simple
User=root
WorkingDirectory=/opt/ginto-sandbox-proxy
ExecStart=/usr/bin/node sandbox-proxy.js
Restart=always
RestartSec=5
Environment=NODE_ENV=production
Environment=PROXY_PORT=3000
Environment=AUTO_CREATE_SANDBOX=0

# Logging
StandardOutput=journal
StandardError=journal
SyslogIdentifier=ginto-sandbox-proxy

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable ginto-sandbox-proxy
systemctl restart ginto-sandbox-proxy
echo "     Service: ginto-sandbox-proxy"

# 5. Configure Caddy (if installed)
echo "[5/6] Configuring Caddy..."
CADDYFILE="/etc/caddy/Caddyfile"
if [ -f "$CADDYFILE" ]; then
    # Check if sandbox config already exists
    if grep -q "# Ginto Sandbox Proxy" "$CADDYFILE" 2>/dev/null; then
        echo "     Sandbox proxy config already in Caddyfile"
    else
        echo "" >> "$CADDYFILE"
        cat >> "$CADDYFILE" << 'EOF'

# Ginto Sandbox Proxy
:1800 {
    reverse_proxy 127.0.0.1:3000
}
EOF
        echo "     Added sandbox proxy config to Caddyfile"
        systemctl reload caddy 2>/dev/null || echo "     (Caddy reload skipped)"
    fi
else
    echo "     Caddy not installed, skipping. Add manually:"
    echo "     :1800 { reverse_proxy 127.0.0.1:3000 }"
fi

# 6. Verify installation
echo "[6/6] Verifying installation..."
sleep 2

# Check Redis
if redis-cli ping 2>/dev/null | grep -q PONG; then
    echo "     ✓ Redis: OK"
else
    echo "     ✗ Redis: FAILED"
fi

# Check Node.js proxy
if curl -s http://127.0.0.1:3000/health 2>/dev/null | grep -q '"status":"ok"'; then
    echo "     ✓ Proxy: OK (port 3000)"
else
    echo "     ✗ Proxy: Not responding yet (check: journalctl -u ginto-sandbox-proxy)"
fi

# Check LXD
if lxc list &>/dev/null; then
    echo "     ✓ LXD: OK"
else
    echo "     ✗ LXD: Not available (run setup_lxd_alpine.sh first)"
fi

echo ""
echo "=== Setup Complete ==="
echo ""
echo "Usage:"
echo "  1. Create a sandbox:  lxc launch ginto sandbox-user123"
echo "  2. Get container IP:  lxc list sandbox-user123 -c 4 --format csv"
echo "  3. Cache in Redis:    redis-cli SET sandbox:user123 10.x.x.x"
echo "  4. Access via proxy:  curl http://localhost:1800/?sandbox=user123"
echo ""
echo "Management:"
echo "  - Proxy logs:  journalctl -u ginto-sandbox-proxy -f"
echo "  - Restart:     systemctl restart ginto-sandbox-proxy"
echo "  - Status:      curl http://localhost:3000/status"
echo ""
