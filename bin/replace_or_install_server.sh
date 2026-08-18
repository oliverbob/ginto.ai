#!/bin/bash
set -e

# ==========================================
# Silverqueen.pro Interactive Installer (Force Mode)
# ==========================================

# 1. Root Check
if [[ $EUID -ne 0 ]]; then
   echo "❌ Error: This script must be run as root."
   exit 1
fi

echo "🚀 Starting Ginto Environment Setup..."

# ==========================================
# 2. Force Clean Ports (Stop & Kill)
# ==========================================
echo "🧹 Cleaning up existing services..."

# Stop Caddy first to prevent self-killing logic issues later
systemctl stop caddy 2>/dev/null || true

# Stop and Mask common web servers
for service in apache2 httpd nginx; do
    if systemctl list-unit-files | grep -q "^$service"; then
        echo "   - Stopping and masking $service..."
        systemctl stop $service 2>/dev/null || true
        systemctl disable $service 2>/dev/null || true
        systemctl mask $service 2>/dev/null || true
    fi
done

echo "🔪 Force killing any lingering processes on Ports 80 and 443..."
# Install psmisc for fuser if missing (lightweight)
if ! command -v fuser &> /dev/null; then
    if [ -f /etc/debian_version ]; then apt-get update && apt-get install -y psmisc; fi
    if [ -f /etc/redhat-release ]; then dnf install -y psmisc; fi
fi

# Kill processes on 80/tcp and 443/tcp
fuser -k 80/tcp 2>/dev/null || true
fuser -k 443/tcp 2>/dev/null || true

echo "✅ Ports 80 and 443 are now free."

# ==========================================
# 3. Install Caddy (If missing)
# ==========================================
if ! command -v caddy &> /dev/null; then
    echo "📦 Installing Caddy v2..."
    if [ -f /etc/debian_version ]; then
        apt-get update -y
        apt-get install -y debian-keyring debian-archive-keyring apt-transport-https curl
        curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-archive-keyring.gpg
        curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/deb/debian.packages.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
        apt-get update -y
        apt-get install -y caddy
    elif [ -f /etc/redhat-release ]; then
        dnf install -y 'dnf-command(copr)'
        dnf copr enable -y @caddy/caddy
        dnf install -y caddy
    else
        echo "❌ Unsupported OS. Install Caddy manually."
        exit 1
    fi
else
    echo "✅ Caddy is already installed."
fi

# ==========================================
# 4. Interactive Configuration
# ==========================================
echo ""
echo "❓ Select Mode:"
echo "   1) DEV  (http://localhost on Port 80 - NO SSL)"
echo "   2) LIVE (https://silverqueen.pro - Auto SSL)"
read -p "   Enter choice [1 or 2]: " MODE_CHOICE

# Set defaults
TARGET_USER=${SUDO_USER:-$(whoami)}
DEFAULT_DIR="/home/$TARGET_USER/ginto"

echo ""
read -p "📂 Enter App Directory [Default: $DEFAULT_DIR]: " INSTALL_DIR
INSTALL_DIR=${INSTALL_DIR:-$DEFAULT_DIR}

read -p "👤 Enter User to run App [Default: $TARGET_USER]: " APP_USER
APP_USER=${APP_USER:-$TARGET_USER}

# ==========================================
# 5. Write Caddyfile
# ==========================================
echo "📝 Writing Caddyfile..."

# Backup existing
if [ -f /etc/caddy/Caddyfile ]; then
    cp /etc/caddy/Caddyfile "/etc/caddy/Caddyfile.bak.$(date +%s)"
fi

# Common Proxy Logic
BLOCK_CONTENT="    encode zstd gzip

    handle /stream/* {
        uri strip_prefix /stream
        reverse_proxy 127.0.0.1:31827
    }

    handle /ws_stt/* {
        uri strip_prefix /ws_stt
        reverse_proxy 127.0.0.1:9011
    }

    @root {
        path /
    }
    rewrite @root /chat
"

if [ "$MODE_CHOICE" == "1" ]; then
    # DEV CONFIG
    cat > /etc/caddy/Caddyfile <<EOF
{
    auto_https off
}

http://localhost {
$BLOCK_CONTENT
    reverse_proxy 127.0.0.1:8000
}
EOF
    echo "   - Mode: DEV (http://localhost on Port 80)"

else
    # LIVE CONFIG
    read -p "🌐 Enter Domain [Default: silverqueen.pro]: " DOMAIN_NAME
    DOMAIN_NAME=${DOMAIN_NAME:-silverqueen.pro}
    
    read -p "📧 Enter Email for TLS [Optional]: " TLS_EMAIL
    TLS_CONFIG=""
    if [ -n "$TLS_EMAIL" ]; then
        TLS_CONFIG="tls $TLS_EMAIL"
    fi

    cat > /etc/caddy/Caddyfile <<EOF
$DOMAIN_NAME {
    $TLS_CONFIG
$BLOCK_CONTENT
    reverse_proxy 127.0.0.1:8090
}
EOF
    echo "   - Mode: LIVE (HTTPS)"
fi

# ==========================================
# 6. Write Systemd Service
# ==========================================
echo "⚙️  Configuring ginto.service..."

SERVICE_FILE="/etc/systemd/system/ginto.service"
COMPOSER_BIN=$(command -v composer || echo "/usr/local/bin/composer")

mkdir -p "$INSTALL_DIR/logs"
chown -R "$APP_USER":"$APP_USER" "$INSTALL_DIR/logs"

cat > "$SERVICE_FILE" <<EOF
[Unit]
Description=Ginto PHP CMS Service
After=network.target caddy.service
Wants=caddy.service

[Service]
Type=simple
User=$APP_USER
WorkingDirectory=$INSTALL_DIR
ExecStart=$COMPOSER_BIN start --services
Restart=always
RestartSec=5
StandardOutput=append:$(dirname $INSTALL_DIR)/ginto_logs/logs.txt
StandardError=append:$(dirname $INSTALL_DIR)/ginto_logs/logs.txt
Environment=PATH=/usr/bin:/usr/local/bin
Environment=PHP_CLI_SERVER_WORKERS=4

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload

# ==========================================
# 7. Start Services (Clean Start)
# ==========================================
echo "🔥 Starting services..."

# Double check ports again before start
fuser -k 80/tcp 2>/dev/null || true
fuser -k 443/tcp 2>/dev/null || true

systemctl enable --now caddy
systemctl restart caddy

systemctl enable --now ginto
systemctl restart ginto

echo ""
echo "=========================================="
if [ "$MODE_CHOICE" == "1" ]; then
    echo "✅ DONE! Open: http://localhost"
    echo "   (Ports 80/443 were forced open)"
else
    echo "✅ DONE! Open: https://$DOMAIN_NAME"
fi
echo "=========================================="
echo "Status:"
systemctl status ginto.service --no-pager | head -n 3