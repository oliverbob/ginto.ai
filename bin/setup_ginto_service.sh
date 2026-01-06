#!/bin/bash
# Setup Ginto systemd service
# Run this script with sudo: sudo ./setup_ginto_service.sh

set -e

echo "Creating Ginto systemd service..."

cat > /etc/systemd/system/ginto.service << 'EOF'
[Unit]
Description=Ginto PHP CMS Service
After=network.target caddy.service
Wants=caddy.service

[Service]
Type=simple
User=oliverbob
WorkingDirectory=/home/oliverbob/ginto
ExecStart=/bin/bash -lc '/usr/local/bin/composer start --services'
Restart=always
RestartSec=5
StandardOutput=append:/home/oliverbob/storage/logs/ginto.log
StandardError=append:/home/oliverbob/storage/logs/ginto.log
Environment="PATH=/usr/local/bin:/usr/bin:/bin:/home/oliverbob/.local/bin"

[Install]
WantedBy=multi-user.target
EOF

echo "Reloading systemd daemon..."
systemctl daemon-reload

echo "Enabling ginto.service..."
systemctl enable ginto.service

echo "Restarting ginto.service..."
systemctl restart ginto.service

echo "Done! Checking status..."
systemctl status ginto.service --no-pager
