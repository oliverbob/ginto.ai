#!/bin/bash
#
# Sandbox Cleanup Script
# ======================
# This script runs periodically (via cron) to clean up inactive sandboxes:
#
# - VISITOR sandboxes (no user_id in DB): DELETE after 1 hour of inactivity
# - LOGGED-IN USER sandboxes: STOP (but keep) after 1 hour of inactivity
#
# Inactivity is determined by:
# 1. Container not running, OR
# 2. Container has been idle (no recent CPU activity) for 1+ hour
#
# Usage: Run via cron every 5-10 minutes:
#   */5 * * * * /path/to/ginto/bin/sandbox_cleanup.sh >> /var/log/sandbox_cleanup.log 2>&1
#

set -euo pipefail

# Configuration
INACTIVE_THRESHOLD_SECONDS=3600  # 1 hour
GINTO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CLIENTS_DIR="${GINTO_ROOT}/clients"
DB_NAME="${GINTO_DB_NAME:-ginto}"
LOG_PREFIX="[sandbox-cleanup]"

# Logging helpers
log_info() { echo "$(date '+%Y-%m-%d %H:%M:%S') ${LOG_PREFIX} INFO: $*"; }
log_warn() { echo "$(date '+%Y-%m-%d %H:%M:%S') ${LOG_PREFIX} WARN: $*"; }
log_error() { echo "$(date '+%Y-%m-%d %H:%M:%S') ${LOG_PREFIX} ERROR: $*" >&2; }

# Check if a sandbox belongs to a logged-in user (has user_id in DB)
# Returns 0 if user-owned, 1 if visitor
is_user_owned_sandbox() {
    local sandbox_id="$1"
    
    # Try to query the database
    local result
    result=$(mysql -N -s -D "${DB_NAME}" -e \
        "SELECT user_id FROM client_sandboxes WHERE sandbox_id = '${sandbox_id}' AND user_id IS NOT NULL LIMIT 1" 2>/dev/null || echo "")
    
    if [[ -n "$result" && "$result" != "NULL" ]]; then
        return 0  # User-owned
    else
        return 1  # Visitor (no user_id or not in DB)
    fi
}

# Get container last activity time (returns seconds since epoch, or 0 if unknown)
get_container_last_activity() {
    local container_name="$1"
    
    # Check if container is running
    local state
    state=$(sudo lxc info "$container_name" 2>/dev/null | grep -E "^Status:" | awk '{print $2}' || echo "")
    
    if [[ "$state" != "RUNNING" ]]; then
        # Container not running - check when it was last started/stopped
        # Use the directory modification time as a proxy
        local sandbox_id="${container_name#sandbox-}"
        local sandbox_dir="${CLIENTS_DIR}/${sandbox_id}"
        if [[ -d "$sandbox_dir" ]]; then
            stat -c %Y "$sandbox_dir" 2>/dev/null || echo "0"
        else
            echo "0"
        fi
        return
    fi
    
    # Container is running - check process activity
    # Use /proc uptime from inside container or check for recent processes
    local last_activity
    last_activity=$(sudo lxc exec "$container_name" -- sh -c \
        'stat -c %Y /tmp 2>/dev/null || date +%s' 2>/dev/null || echo "0")
    
    echo "$last_activity"
}

# Check if container has been inactive for threshold
is_inactive() {
    local container_name="$1"
    local now
    now=$(date +%s)
    
    local last_activity
    last_activity=$(get_container_last_activity "$container_name")
    
    if [[ "$last_activity" -eq 0 ]]; then
        # Unknown activity - assume inactive
        return 0
    fi
    
    local inactive_seconds=$((now - last_activity))
    
    if [[ $inactive_seconds -ge $INACTIVE_THRESHOLD_SECONDS ]]; then
        return 0  # Inactive
    else
        return 1  # Still active
    fi
}

# Stop a sandbox container
stop_sandbox() {
    local sandbox_id="$1"
    local container_name="sandbox-${sandbox_id}"
    
    log_info "Stopping sandbox container: ${container_name}"
    sudo lxc stop "$container_name" --force 2>/dev/null || true
}

# Delete a sandbox completely (container + directory + DB record)
delete_sandbox() {
    local sandbox_id="$1"
    local container_name="sandbox-${sandbox_id}"
    local sandbox_dir="${CLIENTS_DIR}/${sandbox_id}"
    
    log_info "Deleting visitor sandbox: ${sandbox_id}"
    
    # Stop and delete container
    if sudo lxc info "$container_name" &>/dev/null; then
        log_info "  Stopping container ${container_name}..."
        sudo lxc stop "$container_name" --force 2>/dev/null || true
        log_info "  Deleting container ${container_name}..."
        sudo lxc delete "$container_name" --force 2>/dev/null || true
    fi
    
    # Remove directory
    if [[ -d "$sandbox_dir" ]]; then
        log_info "  Removing directory ${sandbox_dir}..."
        rm -rf "$sandbox_dir"
    fi
    
    # Remove DB record
    mysql -D "${DB_NAME}" -e \
        "DELETE FROM client_sandboxes WHERE sandbox_id = '${sandbox_id}'" 2>/dev/null || true
    
    # Clear Redis cache for this sandbox
    redis-cli DEL "sandbox:${sandbox_id}" 2>/dev/null || true
    
    log_info "  Sandbox ${sandbox_id} deleted completely"
}

# Main cleanup logic
main() {
    log_info "Starting sandbox cleanup..."
    
    local stopped_count=0
    local deleted_count=0
    
    # Get all sandbox containers (named sandbox-*)
    local containers
    containers=$(sudo lxc list --format csv -c n 2>/dev/null | grep "^sandbox-" || echo "")
    
    for container_name in $containers; do
        # Skip base images
        if [[ "$container_name" == "ginto-base" || "$container_name" == "ginto-sandbox" ]]; then
            continue
        fi
        
        local sandbox_id="${container_name#sandbox-}"
        
        # Check if inactive
        if ! is_inactive "$container_name"; then
            # Still active - skip
            continue
        fi
        
        log_info "Sandbox ${sandbox_id} is inactive (>1 hour)"
        
        # Check if visitor or user-owned
        if is_user_owned_sandbox "$sandbox_id"; then
            # User-owned: just stop, don't delete
            log_info "  User-owned sandbox - stopping only"
            stop_sandbox "$sandbox_id"
            ((stopped_count++)) || true
        else
            # Visitor: delete completely
            log_info "  Visitor sandbox - deleting"
            delete_sandbox "$sandbox_id"
            ((deleted_count++)) || true
        fi
    done
    
    # Also clean up orphaned directories (no matching container)
    if [[ -d "$CLIENTS_DIR" ]]; then
        for dir in "${CLIENTS_DIR}"/*/; do
            [[ -d "$dir" ]] || continue
            
            local sandbox_id
            sandbox_id=$(basename "$dir")
            
            # Skip special files
            [[ "$sandbox_id" == "router.php" ]] && continue
            [[ "$sandbox_id" == ".gitignore" ]] && continue
            [[ -z "$sandbox_id" ]] && continue
            
            local container_name="sandbox-${sandbox_id}"
            
            # Check if container exists
            if ! sudo lxc info "$container_name" &>/dev/null; then
                # No container - check if directory is old enough to delete
                local dir_mtime
                dir_mtime=$(stat -c %Y "$dir" 2>/dev/null || echo "0")
                local now
                now=$(date +%s)
                local age=$((now - dir_mtime))
                
                if [[ $age -ge $INACTIVE_THRESHOLD_SECONDS ]]; then
                    # Check if visitor
                    if ! is_user_owned_sandbox "$sandbox_id"; then
                        log_info "Cleaning orphaned visitor directory: ${sandbox_id}"
                        rm -rf "$dir"
                        mysql -D "${DB_NAME}" -e \
                            "DELETE FROM client_sandboxes WHERE sandbox_id = '${sandbox_id}'" 2>/dev/null || true
                        ((deleted_count++)) || true
                    fi
                fi
            fi
        done
    fi
    
    log_info "Cleanup complete: ${stopped_count} user sandboxes stopped, ${deleted_count} visitor sandboxes deleted"
}

# Run main
main "$@"
