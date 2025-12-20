#!/bin/bash
# Setup Caddy configuration for ginto.ai
# Run this script with sudo: sudo ./setup_caddy.sh

set -e

echo "Writing Caddy configuration..."

cat > /etc/caddy/Caddyfile << 'EOF'
ginto.ai {
    tls oliverbob.lagumen@gmail.com

    encode zstd gzip

    handle /stream/* {
        uri strip_prefix /stream
        reverse_proxy 127.0.0.1:31827
    }

    handle /ws_stt/* {
        uri strip_prefix /ws_stt
        reverse_proxy 127.0.0.1:9011
    }

    # Everything except stream/ws_stt goes to 8000
    handle {
        reverse_proxy 127.0.0.1:8000
    }
}
EOF

echo "Stopping Caddy..."
systemctl stop caddy

echo "Starting Caddy..."
systemctl start caddy

echo "Done! Checking status..."
systemctl status caddy --no-pager
