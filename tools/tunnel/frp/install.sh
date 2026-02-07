#!/bin/bash
#
# Ginto FRP Client Installer
# 
# Quick install: curl -sSL https://ginto.ai/frp/install.sh | bash
#
# This script:
# 1. Downloads the frpc binary
# 2. Installs the ginto-frpc helper script
# 3. Sets up the configuration directory
#

set -e

FRP_VERSION="${FRP_VERSION:-0.66.0}"
INSTALL_DIR="${HOME}/.ginto-frp"
BIN_DIR="${HOME}/.local/bin"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

echo -e "${CYAN}"
echo "╔═══════════════════════════════════════════╗"
echo "║     Ginto FRP Tunnel - Quick Install      ║"
echo "╚═══════════════════════════════════════════╝"
echo -e "${NC}"

# Detect architecture
detect_arch() {
    local os arch
    os=$(uname -s | tr '[:upper:]' '[:lower:]')
    arch=$(uname -m)
    
    case "$os" in
        linux) os="linux" ;;
        darwin) os="darwin" ;;
        mingw*|msys*|cygwin*) 
            echo -e "${RED}Windows detected. Please download manually from:${NC}"
            echo "https://github.com/fatedier/frp/releases"
            exit 1
            ;;
        *)
            echo -e "${RED}Unsupported OS: $os${NC}"
            exit 1
            ;;
    esac
    
    case "$arch" in
        x86_64|amd64) arch="amd64" ;;
        aarch64|arm64) arch="arm64" ;;
        armv7l) arch="arm" ;;
        i686|i386) arch="386" ;;
        *)
            echo -e "${RED}Unsupported architecture: $arch${NC}"
            exit 1
            ;;
    esac
    
    echo "${os}_${arch}"
}

ARCH=$(detect_arch)
echo -e "${BLUE}[INFO]${NC} Detected platform: ${ARCH}"

# Create directories
mkdir -p "$INSTALL_DIR"
mkdir -p "$BIN_DIR"

# Download frpc
FRP_URL="https://github.com/fatedier/frp/releases/download/v${FRP_VERSION}/frp_${FRP_VERSION}_${ARCH}.tar.gz"
TMP_FILE="/tmp/frp_${FRP_VERSION}.tar.gz"

echo -e "${BLUE}[INFO]${NC} Downloading frpc v${FRP_VERSION}..."

if command -v curl &> /dev/null; then
    curl -sSL "$FRP_URL" -o "$TMP_FILE"
elif command -v wget &> /dev/null; then
    wget -q "$FRP_URL" -O "$TMP_FILE"
else
    echo -e "${RED}[ERROR]${NC} Neither curl nor wget found. Please install one."
    exit 1
fi

echo -e "${BLUE}[INFO]${NC} Extracting..."
tar -xzf "$TMP_FILE" -C /tmp
cp "/tmp/frp_${FRP_VERSION}_${ARCH}/frpc" "$INSTALL_DIR/frpc"
chmod +x "$INSTALL_DIR/frpc"
rm -rf "/tmp/frp_${FRP_VERSION}_${ARCH}" "$TMP_FILE"

# Download ginto-frpc helper script
echo -e "${BLUE}[INFO]${NC} Installing ginto-frpc helper..."
HELPER_URL="https://ginto.ai/frp/ginto-frpc.sh"

if command -v curl &> /dev/null; then
    curl -sSL "$HELPER_URL" -o "$INSTALL_DIR/ginto-frpc"
elif command -v wget &> /dev/null; then
    wget -q "$HELPER_URL" -O "$INSTALL_DIR/ginto-frpc"
fi

chmod +x "$INSTALL_DIR/ginto-frpc"

# Create symlink
ln -sf "$INSTALL_DIR/ginto-frpc" "$BIN_DIR/ginto-frpc"

# Add to PATH if needed
if [[ ":$PATH:" != *":$BIN_DIR:"* ]]; then
    echo -e "${YELLOW}[WARN]${NC} ${BIN_DIR} is not in your PATH"
    
    # Detect shell and add to rc file
    SHELL_RC=""
    if [[ -n "$ZSH_VERSION" ]] || [[ "$SHELL" == *"zsh"* ]]; then
        SHELL_RC="$HOME/.zshrc"
    elif [[ -n "$BASH_VERSION" ]] || [[ "$SHELL" == *"bash"* ]]; then
        SHELL_RC="$HOME/.bashrc"
    fi
    
    if [[ -n "$SHELL_RC" ]]; then
        echo "export PATH=\"\$PATH:$BIN_DIR\"" >> "$SHELL_RC"
        echo -e "${BLUE}[INFO]${NC} Added $BIN_DIR to PATH in $SHELL_RC"
        echo -e "${YELLOW}[NOTE]${NC} Run 'source $SHELL_RC' or start a new terminal"
    fi
fi

echo ""
echo -e "${GREEN}╔═══════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║         Installation Complete!            ║${NC}"
echo -e "${GREEN}╚═══════════════════════════════════════════╝${NC}"
echo ""
echo "Installed:"
echo "  frpc:        $INSTALL_DIR/frpc"
echo "  ginto-frpc:  $INSTALL_DIR/ginto-frpc"
echo "  symlink:     $BIN_DIR/ginto-frpc"
echo ""
echo "Next steps:"
echo ""
echo "  1. Get your auth token from https://ginto.ai/dashboard/tokens"
echo ""
echo "  2. Set your token:"
echo "     export GINTO_FRP_TOKEN='your-token-here'"
echo ""
echo "  3. Expose your local service:"
echo "     ginto-frpc expose myapp 8088"
echo ""
echo "  4. Access at https://myapp.ginto.ai"
echo ""
echo "For help: ginto-frpc help"
echo ""
