#!/usr/bin/env bash
# deploy/cleanup_sandboxd.sh
# Cleanup tool for ginto sandbox logs and stopped sandboxes.
# - Dry-run by default.
# - Archives removed files under storage/backups/old/ and /var/log/ginto/old/
# - Removes /var/lib/machines/ginto-sandbox-* rootfs only when container is not active
#
# Usage examples:
# 1) Dry-run: show what would be removed:
#    sudo ./deploy/cleanup_sandboxd.sh --days 7 --dry-run
# 2) Do the removals (be careful):
#    sudo ./deploy/cleanup_sandboxd.sh --days 30 --confirm
#
set -euo pipefail

REPO_ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
STORAGE_BACKUPS="$REPO_ROOT/storage/backups"
DAEMON_LOG_DIR="/var/log/ginto"
MACHINES_DIR="/var/lib/machines"
ARCHIVE_DIR_BACKUPS="$STORAGE_BACKUPS/old"
ARCHIVE_DIR_DAEMON="$DAEMON_LOG_DIR/old"

DAYS=${DAYS:-30}
DRY_RUN=1
CONFIRM=0
VERBOSE=1
KEEP_IF_ACTIVE=1

usage(){
  cat <<EOF
cleanup_sandboxd.sh — cleanup old install logs, daemon logs, and stopped machine roots

Usage: $0 [--days N] [--dry-run|--confirm] [--keep-active|--remove-stopped] [--verbose|--quiet]

--days N          Age threshold in days to consider 'old' (default: $DAYS)
--dry-run         (default) show actions without modifying files
--confirm         actually perform removals
--keep-active     keep sandbox roots if still active (default behaviour)
--remove-stopped  also remove stopped machine rootfs directories older than N days
--verbose         print progress (default)
--quiet           minimal output

Examples:
  # Dry-run of items older than 7 days
  sudo ./deploy/cleanup_sandboxd.sh --days 7 --dry-run

  # Actually remove and archive old logs and stopped machines older than 30 days
  sudo ./deploy/cleanup_sandboxd.sh --days 30 --confirm --remove-stopped
EOF
}

while [ "$#" -gt 0 ]; do
  case "$1" in
    --days) DAYS="$2"; shift 2;;
    --dry-run) DRY_RUN=1; shift;;
    --confirm) DRY_RUN=0; CONFIRM=1; shift;;
    --keep-active) KEEP_IF_ACTIVE=1; shift;;
    --remove-stopped) KEEP_IF_ACTIVE=0; shift;;
    --quiet) VERBOSE=0; shift;;
    --verbose) VERBOSE=1; shift;;
    --help|-h) usage; exit 0;;
    *) echo "Unknown arg: $1" >&2; usage; exit 2;;
  esac
done

if [ "$VERBOSE" -eq 1 ]; then
  echo "cleanup_sandboxd — days=$DAYS dry_run=$DRY_RUN keep_active=$KEEP_IF_ACTIVE"
fi

# Ensure directories exist
mkdir -p "$ARCHIVE_DIR_BACKUPS" "$ARCHIVE_DIR_DAEMON"

# Helper functions
do_mv_or_echo() {
  local src="$1" dst="$2"
  if [ "$DRY_RUN" -eq 1 ]; then
    echo "DRY-RUN: mv '$src' -> '$dst'"
  else
    mkdir -p "$(dirname "$dst")"
    mv -f "$src" "$dst"
    echo "Moved: $src -> $dst"
  fi
}

do_rm_or_echo() {
  local p="$1"
  if [ "$DRY_RUN" -eq 1 ]; then
    echo "DRY-RUN: rm -rf $p"
  else
    rm -rf -- "$p"
    echo "Removed: $p"
  fi
}

# 1) Cleanup storage/backups/install_*.log older than DAYS
if [ -d "$STORAGE_BACKUPS" ]; then
  if [ "$VERBOSE" -eq 1 ]; then echo "Scanning $STORAGE_BACKUPS for install_*.log older than $DAYS days..."; fi
  find "$STORAGE_BACKUPS" -maxdepth 1 -type f -name 'install_*.log' -mtime +$DAYS -print0 | while IFS= read -r -d '' f; do
    base=$(basename "$f")
    stamp=$(date -u +%Y%m%dT%H%M%SZ)
    dst="$ARCHIVE_DIR_BACKUPS/${base}.${stamp}.gz"
    if [ "$VERBOSE" -eq 1 ]; then echo "Found old install log: $f"; fi
    if [ "$DRY_RUN" -eq 1 ]; then
      echo "Would compress and archive to: $dst"
    else
      gzip -c "$f" > "${dst}" || true
      rm -f -- "$f"
      echo "Archived $f -> $dst"
    fi
  done
fi

# 2) Cleanup daemon logs older than DAYS (rotate/archive)
if [ -d "$DAEMON_LOG_DIR" ]; then
  if [ "$VERBOSE" -eq 1 ]; then echo "Scanning $DAEMON_LOG_DIR for sandboxd_*.log older than $DAYS days..."; fi
  find "$DAEMON_LOG_DIR" -maxdepth 1 -type f -name 'sandboxd_*.log' -mtime +$DAYS -print0 | while IFS= read -r -d '' f; do
    base=$(basename "$f")
    stamp=$(date -u +%Y%m%dT%H%M%SZ)
    dst="$ARCHIVE_DIR_DAEMON/${base}.${stamp}.gz"
    if [ "$VERBOSE" -eq 1 ]; then echo "Found old daemon log: $f"; fi
    if [ "$DRY_RUN" -eq 1 ]; then
      echo "Would archive daemon log to: $dst"
    else
      gzip -c "$f" > "${dst}" || true
      rm -f -- "$f"
      echo "Archived $f -> $dst"
    fi
  done
fi

# 3) Optionally remove stopped machine rootfs under /var/lib/machines/ginto-sandbox-* older than DAYS
if [ "$KEEP_IF_ACTIVE" -eq 0 ] && [ -d "$MACHINES_DIR" ]; then
  if [ "$VERBOSE" -eq 1 ]; then echo "Looking for stopped sandbox rootfs older than $DAYS days under $MACHINES_DIR..."; fi
  find "$MACHINES_DIR" -maxdepth 1 -type d -name 'ginto-sandbox-*' -mtime +$DAYS -print0 | while IFS= read -r -d '' d; do
    name=$(basename "$d")
    safe_name="${name##ginto-sandbox-}"
    # check machinectl status - if running skip
    if systemctl --version >/dev/null 2>&1 && command -v machinectl >/dev/null 2>&1; then
      if machinectl status "$name" >/dev/null 2>&1; then
        if [ "$VERBOSE" -eq 1 ]; then echo "Skipping active machine: $name"; fi
        continue
      fi
    fi
    if [ "$VERBOSE" -eq 1 ]; then echo "Removing rootfs: $d"; fi
    if [ "$DRY_RUN" -eq 1 ]; then
      echo "DRY-RUN: remove $d"
    else
      rm -rf -- "$d"
      echo "Removed rootfs: $d"
    fi
  done
fi

# 4) GC: remove zero-byte or empty install logs older than N days (safety)
if [ -d "$STORAGE_BACKUPS" ]; then
  find "$STORAGE_BACKUPS" -maxdepth 1 -type f -name 'install_*.log' -mtime +$DAYS -size 0 -print0 | while IFS= read -r -d '' f; do
    if [ "$VERBOSE" -eq 1 ]; then echo "Pruning empty install log: $f"; fi
    do_rm_or_echo "$f"
  done
fi

if [ "$VERBOSE" -eq 1 ]; then echo "Cleanup completed (dry_run=$DRY_RUN)."; fi

exit 0
