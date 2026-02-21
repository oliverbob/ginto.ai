#!/bin/bash
# Manual SDCPU (FastSD CPU Image Generation) install script
# Extracted from bin/gintoai.sh install_sdcpu() and configure_sdcpu_service()
#
# Usage: sudo bash bin/install_sdcpu.sh [--cpu | --gpu[=cu118|cu121|cu124]]
#
#   --cpu          Force CPU-only PyTorch wheels (default if no GPU detected)
#   --gpu          Auto-detect CUDA version and use matching GPU wheels
#   --gpu=cu118    Force CUDA 11.8 wheels
#   --gpu=cu121    Force CUDA 12.1 wheels
#   --gpu=cu124    Force CUDA 12.4 wheels
#
# Assumes the project is already cloned and tools/sdcpu/ contains source files.

set -e

# --- Argument parsing ---------------------------------------------------
MODE="auto"   # auto | cpu | gpu
CUDA_TAG=""   # e.g. cu124

for arg in "$@"; do
    case "$arg" in
        --cpu)
            MODE="cpu" ;;
        --gpu)
            MODE="gpu" ;;
        --gpu=*)
            MODE="gpu"
            CUDA_TAG="${arg#--gpu=}" ;;
        *)
            echo "Unknown option: $arg"
            echo "Usage: $0 [--cpu | --gpu[=cu118|cu121|cu124]]"
            exit 1 ;;
    esac
done

# --- Resolve CUDA tag ---------------------------------------------------
detect_cuda_tag() {
    # Try nvidia-smi first
    if command -v nvidia-smi &>/dev/null; then
        local cuda_ver
        cuda_ver=$(nvidia-smi 2>/dev/null | grep -oP 'CUDA Version: \K[0-9]+\.[0-9]+')
        local major minor
        major=$(echo "$cuda_ver" | cut -d. -f1)
        minor=$(echo "$cuda_ver" | cut -d. -f2)
        if [[ "$major" -ge 12 && "$minor" -ge 4 ]]; then echo "cu124"
        elif [[ "$major" -ge 12 && "$minor" -ge 1 ]]; then echo "cu121"
        elif [[ "$major" -ge 11 && "$minor" -ge 8 ]]; then echo "cu118"
        else echo "cu118"   # oldest supported
        fi
    else
        echo ""
    fi
}

if [[ "$MODE" == "auto" ]]; then
    DETECTED=$(detect_cuda_tag)
    if [[ -n "$DETECTED" ]]; then
        MODE="gpu"
        CUDA_TAG="$DETECTED"
        echo "==> GPU detected — will use CUDA wheels ($CUDA_TAG)"
    else
        MODE="cpu"
        echo "==> No GPU detected — will use CPU-only wheels"
    fi
elif [[ "$MODE" == "gpu" && -z "$CUDA_TAG" ]]; then
    CUDA_TAG=$(detect_cuda_tag)
    if [[ -z "$CUDA_TAG" ]]; then
        echo "WARN: --gpu specified but could not detect CUDA version; defaulting to cu124"
        CUDA_TAG="cu124"
    fi
    echo "==> GPU mode — detected CUDA tag: $CUDA_TAG"
fi

if [[ "$MODE" == "cpu" ]]; then
    TORCH_INDEX="https://download.pytorch.org/whl/cpu"
    echo "==> PyTorch index: CPU-only ($TORCH_INDEX)"
else
    TORCH_INDEX="https://download.pytorch.org/whl/$CUDA_TAG"
    echo "==> PyTorch index: GPU/$CUDA_TAG ($TORCH_INDEX)"
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
SDCPU_DIR="$PROJECT_DIR/tools/sdcpu"
INSTALL_USER="${SUDO_USER:-$(whoami)}"
INSTALL_USER_HOME=$(eval echo "~$INSTALL_USER")

echo "==> SDCPU directory : $SDCPU_DIR"
echo "==> Running as user : $INSTALL_USER"

# --- Sanity check -------------------------------------------------------
if [ ! -f "$SDCPU_DIR/requirements.txt" ] || [ ! -f "$SDCPU_DIR/src/api_server.py" ]; then
    echo "ERROR: SDCPU source not found at $SDCPU_DIR"
    echo "       Make sure tools/sdcpu/ contains requirements.txt and src/api_server.py"
    exit 1
fi

if [ -d "$SDCPU_DIR/venv" ] && [ -f "$SDCPU_DIR/venv/bin/python" ]; then
    echo "INFO: SDCPU venv already exists at $SDCPU_DIR/venv — skipping Python install."
    echo "      Delete $SDCPU_DIR/venv and re-run to force a reinstall."
else
    # --- System dependencies ------------------------------------------------
    echo "==> Installing system dependencies..."
    if command -v apt-get &>/dev/null; then
        PY_VER=$(python3 -c 'import sys; print(f"{sys.version_info.major}.{sys.version_info.minor}")')
        sudo apt-get install -y \
            "python${PY_VER}-venv" "python${PY_VER}-dev" \
            python3-venv python3-dev python3-pip \
            libjpeg-dev zlib1g-dev libpng-dev libfreetype6-dev liblcms2-dev \
            libopenjp2-7-dev libtiff-dev libwebp-dev 2>/dev/null || \
        sudo apt-get install -y python3-venv python3-dev python3-pip libjpeg-dev zlib1g-dev
    elif command -v dnf &>/dev/null; then
        sudo dnf install -y python3-virtualenv python3-pip python3-devel \
            libjpeg-devel zlib-devel libpng-devel freetype-devel lcms2-devel \
            openjpeg2-devel libtiff-devel libwebp-devel
    elif command -v apk &>/dev/null; then
        sudo apk add --no-cache python3 py3-pip py3-virtualenv python3-dev \
            jpeg-dev zlib-dev libpng-dev freetype-dev lcms2-dev \
            openjpeg-dev tiff-dev libwebp-dev
    fi

    if ! python3 -m venv --help &>/dev/null; then
        echo "ERROR: python3-venv is not available after install attempt."
        exit 1
    fi

    # --- Python venv + dependencies -----------------------------------------
    cd "$SDCPU_DIR"

    echo "==> Creating Python virtual environment..."
    python3 -m venv venv

    echo "==> Activating venv and upgrading pip..."
    source venv/bin/activate
    pip install --upgrade pip

    # Pin PyTorch 2.8.0 — 2.9.0 breaks optimum-intel
    echo "==> Installing PyTorch 2.8.0 wheels from $TORCH_INDEX..."
    pip install torch==2.8.0 torchvision==0.23.0 \
        --index-url "$TORCH_INDEX"

    echo "==> Installing remaining requirements from requirements.txt..."
    pip install -r requirements.txt
    deactivate

    # Fix ownership so the target user owns the venv
    sudo chown -R "$INSTALL_USER:$INSTALL_USER" "$SDCPU_DIR/venv"

    cd "$PROJECT_DIR"
    echo "==> SDCPU Python environment installed successfully."
fi

# --- systemd service ----------------------------------------------------
echo "==> Writing /etc/systemd/system/sdcpu.service..."
sudo tee /etc/systemd/system/sdcpu.service > /dev/null << EOF
[Unit]
Description=FastSD CPU Image Generation Server
After=network.target

[Service]
Type=simple
User=$INSTALL_USER
Group=$INSTALL_USER
WorkingDirectory=$SDCPU_DIR
ExecStart=$SDCPU_DIR/venv/bin/python src/api_server.py --port 8888
Restart=always
RestartSec=5
Environment=HOME=$INSTALL_USER_HOME
Environment=PATH=$SDCPU_DIR/venv/bin:/usr/local/bin:/usr/bin:/bin

[Install]
WantedBy=multi-user.target
EOF

echo "==> Enabling and starting sdcpu.service..."
sudo systemctl daemon-reload
sudo systemctl enable sdcpu.service
sudo systemctl restart sdcpu.service

if sudo systemctl is-active --quiet sdcpu.service; then
    echo "==> sdcpu.service is running on port 8888."
else
    echo "WARN: sdcpu.service failed to start. Check logs:"
    echo "      sudo journalctl -u sdcpu.service -n 50"
    echo "      /tmp/sdcpu.log"
fi
