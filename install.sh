#!/bin/sh
# Ginto AI - One-line installer
# Usage: curl -fsSL https://raw.githubusercontent.com/oliverbob/silverqueen.pro/main/install.sh | sh

set -e

REPO_URL="https://github.com/oliverbob/silverqueen.pro.git"
INSTALL_DIR="$HOME/silverqueen.pro"

echo "🚀 Ginto AI Installer"
echo "====================="
echo ""

# Check for git
if ! command -v git >/dev/null 2>&1; then
    echo "❌ Git is not installed. Please install git first:"
    echo "   sudo apt install git"
    exit 1
fi

# Clone or update repo
if [ -d "$INSTALL_DIR" ]; then
    echo "📁 Found existing installation at $INSTALL_DIR"
    echo "   Pulling latest changes..."
    cd "$INSTALL_DIR"
    git pull
else
    echo "📥 Cloning Ginto AI to $INSTALL_DIR..."
    git clone "$REPO_URL" "$INSTALL_DIR"
    cd "$INSTALL_DIR"
fi

echo ""
echo "🔧 Starting installation..."
echo ""

# Check if sudo needs a password
if sudo -n true 2>/dev/null; then
    # Passwordless sudo available - just run
    exec sudo ./run.sh install
else
    # Need password - read from /dev/tty (works even when stdin is piped)
    echo "🔐 Sudo password required for installation."
    printf "Password: "
    stty -echo < /dev/tty 2>/dev/null || true
    read -r SUDO_PASS < /dev/tty
    stty echo < /dev/tty 2>/dev/null || true
    echo ""
    echo "$SUDO_PASS" | sudo -S ./run.sh install 2>&1 | grep -v "^\[sudo\] password"
fi
