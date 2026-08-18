#!/bin/bash
#
# Cleanup expired Ginto Cloud subdomains
# Run via cron every minute: * * * * * /path/to/cleanup_cloud_domains.sh
#

set -e

CADDY_AVAILABLE="/etc/caddy/sites-available"
CADDY_ENABLED="/etc/caddy/sites-enabled"
RELOAD_NEEDED=0

# Find cloud subdomain configs (*.silverqueen.pro.caddy files with "Ginto Cloud" in them)
for config in "$CADDY_AVAILABLE"/*.silverqueen.pro.caddy; do
    [ -f "$config" ] || continue
    
    # Check if it's a cloud subdomain (has our marker comment)
    if grep -q "Ginto Cloud temporary subdomain" "$config" 2>/dev/null; then
        # Extract expiry timestamp from comment
        # Format: # Expires: 2025-01-08 15:30:00
        expiry_line=$(grep "# Expires:" "$config" 2>/dev/null | head -1)
        if [ -n "$expiry_line" ]; then
            expiry_date=$(echo "$expiry_line" | sed 's/.*# Expires: //')
            expiry_epoch=$(date -d "$expiry_date" +%s 2>/dev/null || echo 0)
            now_epoch=$(date +%s)
            
            if [ "$expiry_epoch" -gt 0 ] && [ "$now_epoch" -gt "$expiry_epoch" ]; then
                domain=$(basename "$config" .caddy)
                echo "Removing expired cloud domain: $domain"
                
                # Remove symlink and config
                rm -f "$CADDY_ENABLED/$(basename "$config")"
                rm -f "$config"
                RELOAD_NEEDED=1
            fi
        fi
    fi
done

# Reload Caddy if any domains were removed
if [ "$RELOAD_NEEDED" -eq 1 ]; then
    echo "Reloading Caddy..."
    sudo systemctl reload caddy
fi
