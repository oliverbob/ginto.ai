Cleanup utilities for sandboxd and install logs

This folder contains a small cleanup helper to safely archive or remove
old install logs and daemon logs and optionally remove stopped sandbox
rootfs directories. The goal is to keep the editor UI from tailing
very old failure logs and to reclaim disk space.

Files
- cleanup_sandboxd.sh — main cleanup script (dry-run by default)

How it works
- storage/backups/install_*.log files older than the configured threshold are
  compressed (gzip) and moved to storage/backups/old/.
- daemon logs in /var/log/ginto/sandboxd_*.log older than the threshold
  are compressed and moved to /var/log/ginto/old/.
- Optionally (--remove-stopped) rootfs directories under /var/lib/machines
  named ginto-sandbox-* older than the threshold will be removed if they are
  not active (machinectl status returns non-zero).

Safety
- The script is a conservative dry-run by default. Pass --confirm to perform
  destructive actions.
- Always inspect the dry-run output carefully before running with --confirm.

Example
  # Show what would be removed for items older than 14 days
  sudo ./deploy/cleanup_sandboxd.sh --days 14 --dry-run

  # Actually remove and archive items > 30 days and also remove stopped machines
  sudo ./deploy/cleanup_sandboxd.sh --days 30 --confirm --remove-stopped

Scheduling
- Add a systemd timer or cron job to run this periodically (e.g. weekly).
