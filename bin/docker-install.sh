#!/usr/bin/env bash
#===============================================================================
# Ginto AI Docker Quick Install Script
#===============================================================================
# Thin wrapper that runs gintoai.sh in Docker mode.
# For the full unified installer, use: ./run.sh install
#
# Usage:
#   ./bin/docker-install.sh              # Docker installation
#   GINTO_INSTALL_MODE=docker ./run.sh install  # Alternative
#===============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Set Docker mode and delegate to gintoai.sh
export GINTO_INSTALL_MODE=docker
exec "$SCRIPT_DIR/gintoai.sh" install
