#!/bin/sh
# Ginto AI - One-line installer
# Usage: curl -fsSL https://raw.githubusercontent.com/oliverbob/ginto.ai/main/install.sh | sh

set -e

REPO_URL="https://github.com/oliverbob/ginto.ai.git"
INSTALL_DIR="$HOME/ginto.ai"

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

# Run the installer interactively with sudo
# Reconnect stdin to /dev/tty for interactive prompts when piped from curl
exec sudo ./run.sh install < /dev/tty
