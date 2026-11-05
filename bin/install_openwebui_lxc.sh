#!/bin/bash
# OpenWebUI Installation Script for LXC/Alpine Sandboxes
# This script installs Docker and runs OpenWebUI container inside an LXC Alpine container
# Usage: install_openwebui_lxc.sh
#
# Run this script inside the Alpine container (not on the host)

set -e

echo "=========================================="
echo "OpenWebUI Installation for Alpine/LXC"
echo "(Docker-in-LXC)"
echo "=========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if we're running in Alpine
if ! grep -q "Alpine" /etc/os-release 2>/dev/null; then
    echo -e "${YELLOW}Warning: This script is designed for Alpine Linux${NC}"
fi

# Port for OpenWebUI
OWUI_PORT="${OWUI_PORT:-8088}"

echo "Installing OpenWebUI on port: ${OWUI_PORT}"
echo ""

# Step 1: Install Docker if not present
echo -e "${GREEN}[1/3] Checking Docker installation...${NC}"
if command -v docker &> /dev/null; then
    echo "Docker is already installed"
else
    echo "Installing Docker..."
    if command -v apk &> /dev/null; then
        # Alpine Linux
        apk update
        apk add --no-cache docker docker-cli-compose
        rc-update add docker boot 2>/dev/null || true
        service docker start 2>/dev/null || dockerd &
        sleep 3
    elif command -v apt-get &> /dev/null; then
        # Debian/Ubuntu
        apt-get update
        apt-get install -y docker.io
        systemctl enable docker 2>/dev/null || true
        systemctl start docker 2>/dev/null || dockerd &
        sleep 3
    else
        echo -e "${RED}Unsupported package manager. Please install Docker manually.${NC}"
        exit 1
    fi
fi

# Step 2: Ensure Docker is running
echo ""
echo -e "${GREEN}[2/3] Ensuring Docker daemon is running...${NC}"
if ! docker info &>/dev/null; then
    echo "Starting Docker daemon..."
    if command -v rc-service &> /dev/null; then
        rc-service docker start 2>/dev/null || dockerd &
    elif command -v systemctl &> /dev/null; then
        systemctl start docker 2>/dev/null || dockerd &
    else
        dockerd &
    fi
    sleep 5
fi

if ! docker info &>/dev/null; then
    echo -e "${RED}Failed to start Docker daemon${NC}"
    exit 1
fi

echo "Docker is running"

# Step 3: Run OpenWebUI container
echo ""
echo -e "${GREEN}[3/3] Starting OpenWebUI container...${NC}"

# Remove existing container if any
docker rm -f open-webui 2>/dev/null || true

# Run OpenWebUI
docker run -d \
    --name open-webui \
    --restart unless-stopped \
    -p ${OWUI_PORT}:8080 \
    -v open-webui:/app/backend/data \
    ghcr.io/open-webui/open-webui:main

echo ""
echo "=========================================="
echo -e "${GREEN}✅ OpenWebUI installed successfully!${NC}"
echo "=========================================="
echo ""
echo "OpenWebUI is running at:"
echo "  http://localhost:${OWUI_PORT}/"
echo ""
echo "To check status:"
echo "  docker ps -f name=open-webui"
echo ""
echo "To view logs:"
echo "  docker logs -f open-webui"
echo ""
echo "To stop:"
echo "  docker stop open-webui"
echo ""
echo "To start again:"
echo "  docker start open-webui"
echo ""
