#!/bin/bash
# Setup Ginto systemd service
# Run this script with sudo: sudo ./setup_ginto_service.sh

set -e

# Detect ginto directory and user
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GINTO_DIR="$(dirname "$SCRIPT_DIR")"
GINTO_USER="$(stat -c '%U' "$GINTO_DIR")"
STORAGE_DIR="$(dirname "$GINTO_DIR")/storage"

echo "[INFO] Ginto directory: $GINTO_DIR"
echo "[INFO] Ginto user: $GINTO_USER"
echo "[INFO] Storage directory: $STORAGE_DIR"

# Ensure storage/logs exists
mkdir -p "$STORAGE_DIR/logs"
chown -R "$GINTO_USER:$GINTO_USER" "$STORAGE_DIR"

echo "[INFO] Creating Ginto systemd service..."

cat > /etc/systemd/system/ginto.service << EOF
[Unit]
Description=Ginto AI PHP Application
After=network.target mariadb.service caddy.service
Wants=caddy.service

[Service]
Type=simple
User=$GINTO_USER
Group=$GINTO_USER
WorkingDirectory=$GINTO_DIR
# Run ginto start directly (not via composer to avoid timeout issues)
ExecStart=/bin/bash -c "bash bin/ginto start --services && tail -n 200 -F /tmp/ginto-web.log"
ExecStop=/usr/bin/pkill -f "php.*ginto"
Restart=always
RestartSec=5
StandardOutput=append:$STORAGE_DIR/logs/ginto.log
StandardError=append:$STORAGE_DIR/logs/ginto-error.log
Environment=PATH=/usr/bin:/usr/local/bin:/home/$GINTO_USER/.local/bin
Environment=HOME=/home/$GINTO_USER

[Install]
WantedBy=multi-user.target
EOF

echo "[INFO] Reloading systemd daemon..."
systemctl daemon-reload

echo "[INFO] Enabling ginto.service..."
systemctl enable ginto.service

echo "[INFO] Restarting ginto.service..."
systemctl restart ginto.service

sleep 2

echo "[OK] Done! Checking status..."
systemctl status ginto.service --no-pager | head -20
