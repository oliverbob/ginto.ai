#!/usr/bin/env bash
# deploy/install_sandboxd.sh
# Install and enable the socket-activated sandbox daemon. Optionally perform
# a one-shot create as root so no persistent sudoers entries are required.
# Usage (as root): ./install_sandboxd.sh [web_user] [--create-now <sandboxId> <hostPath>] [--install-sudoers]

set -euo pipefail

WEB_USER=${1:-${SUDO_USER:-www-data}}
shift || true
REPO_ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
SYSTEMD_DIR=/etc/systemd/system
SUDOERS_DIR=/etc/sudoers.d

CREATE_NOW=0
CREATE_ID=""
CREATE_PATH=""
INSTALL_SUDOERS=0
SOCKET_OWNER="${SUDO_USER:-}"

while [ "$#" -gt 0 ]; do
  case "$1" in
    --create-now)
      CREATE_NOW=1
      CREATE_ID="$2"; CREATE_PATH="$3"
      shift 3
      ;;
    --socket-owner)
      SOCKET_OWNER="$2"
      shift 2
      ;;
    --install-sudoers)
      INSTALL_SUDOERS=1
      shift
      ;;
    --help|-h)
      echo "Usage: $0 [web_user] [--create-now <sandboxId> <hostPath>] [--install-sudoers]"; exit 0
      ;;
    *)
      # already consumed web_user as first arg; ignore extras
      shift
      ;;
  esac
done

if [ "$(id -u)" -ne 0 ]; then
  echo "This script must be run as root. Use sudo or su -c." >&2
  exit 2
fi

echo "Installing ginto sandboxd artifacts from $REPO_ROOT/deploy"

# Ensure group exists
if ! getent group ginto >/dev/null; then
  echo "Creating group 'ginto'"
  groupadd --force ginto
else
  echo "Group 'ginto' already exists"
fi

# Add web user to group
if id "$WEB_USER" >/dev/null 2>&1; then
  echo "Adding user '$WEB_USER' to group 'ginto'"
  usermod -aG ginto "$WEB_USER"
else
  echo "Warning: user '$WEB_USER' does not exist on this system. Create or specify the correct webserver user." >&2
fi

# Install systemd unit files
for f in sandboxd.socket sandboxd.service; do
  src="$REPO_ROOT/deploy/$f"
  if [ -f "$src" ]; then
    dst="$SYSTEMD_DIR/$f"
    echo "Installing $f -> $dst"
    cp -f "$src" "$dst"
    # If a socket-owner was specified, modify the installed socket unit
    if [ "$f" = 'sandboxd.socket' ] && [ -n "$SOCKET_OWNER" ]; then
      if id "$SOCKET_OWNER" >/dev/null 2>&1; then
        echo "Adjusting socket unit to owner/group: $SOCKET_OWNER"
        # Ensure SocketUser/SocketGroup set to the chosen owner (SocketUser=root kept optional)
        sed -i "s/^SocketGroup=.*/SocketGroup=${SOCKET_OWNER}/" "$dst" || true
        sed -i "s/^SocketUser=.*/SocketUser=${SOCKET_OWNER}/" "$dst" || true
        # If SocketGroup/SocketUser not present, add them
        if ! grep -q '^SocketGroup=' "$dst" ; then
          sed -i "/^\[Socket\]/a SocketGroup=${SOCKET_OWNER}" "$dst"
        fi
        if ! grep -q '^SocketUser=' "$dst" ; then
          sed -i "/^\[Socket\]/a SocketUser=${SOCKET_OWNER}" "$dst"
        fi
      else
        echo "Warning: socket owner user '$SOCKET_OWNER' not found on system; leaving unit defaults" >&2
      fi
    fi
    chmod 0644 "$dst"
  else
    echo "Warning: $src not found in repo; skipping" >&2
  fi
done

SUDOERS_SRC="$REPO_ROOT/deploy/ginto-sudoers.example"
SUDOERS_DST="$SUDOERS_DIR/ginto-sandboxd"
if [ $INSTALL_SUDOERS -eq 1 ]; then
  if [ -f "$SUDOERS_SRC" ]; then
    if [ -f "$SUDOERS_DST" ]; then
      echo "Note: $SUDOERS_DST already exists. Backing up to ${SUDOERS_DST}.bak"
      cp -f "$SUDOERS_DST" "${SUDOERS_DST}.bak"
    fi
    echo "Installing sudoers snippet to $SUDOERS_DST"
    cp -f "$SUDOERS_SRC" "$SUDOERS_DST"
    chmod 0440 "$SUDOERS_DST"
    # Validate sudoers file
    if visudo -cf "$SUDOERS_DST"; then
      echo "Sudoers snippet looks valid"
    else
      echo "ERROR: installed sudoers snippet is invalid. Restoring backup if present." >&2
      if [ -f "${SUDOERS_DST}.bak" ]; then
        mv -f "${SUDOERS_DST}.bak" "$SUDOERS_DST"
      fi
      exit 3
    fi
  else
    echo "Warning: sudoers example $SUDOERS_SRC not found; skipping sudoers install" >&2
  fi
else
  echo "Not installing sudoers by default. Use --install-sudoers to enable persistent sudoers." 
fi

# Reload systemd and enable socket
echo "Reloading systemd daemon"
systemctl daemon-reload

echo "Enabling and starting sandboxd.socket (socket-activated daemon)"
systemctl daemon-reload
systemctl enable --now sandboxd.socket || true

# Show status
echo
systemctl status sandboxd.socket --no-pager || true

echo
if ss -l | grep -q sandboxd; then
  echo "Socket appears to be listening (check above)."
else
  echo "Socket may not be listening; check unit status above."
fi

echo
echo "Done. Restart your webserver (or ensure web user has a new session) so group membership takes effect for '$WEB_USER'."

if [ $CREATE_NOW -eq 1 ]; then
  echo "Performing one-shot create as root for sandboxId=$CREATE_ID"
  # Ensure helper scripts are installed to /usr/local/bin
  install -m 0755 "$REPO_ROOT/scripts/sandboxd.sh" /usr/local/bin/sandboxd.sh || true
  # Invoke helper directly as root (no sudo) to create sandbox
  /usr/local/bin/sandboxd.sh create "$CREATE_ID" "$CREATE_PATH"
  echo "One-shot create invoked. Check /var/log/ginto/${CREATE_ID}.log for details."
fi

echo "If you want a persistent sudoers entry, re-run with --install-sudoers."
echo "If you'd rather not install sudoers, nothing further is required; the socket approach avoids sudo." 
