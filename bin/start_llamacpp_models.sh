#!/usr/bin/env bash
# Ginto AI - llama.cpp Model Starter
# Starts llama-server instances for vision and reasoning models
# Use --restart to force restart even if already running

set -e

# Parse arguments
FORCE_RESTART=false
for arg in "$@"; do
    case $arg in
        --restart|--force|-r|-f)
            FORCE_RESTART=true
            ;;
    esac
done

# Default models (can be overridden via environment or .env file)
VISION_HF_MODEL="${VISION_HF_MODEL:-ggml-org/SmolVLM2-500M-Video-Instruct-GGUF}"
REASONING_HF_MODEL="${REASONING_HF_MODEL:-lm-kit/qwen-3-0.6b-instruct-gguf}"

# Ports
VISION_PORT=8033
REASONING_PORT=8034

# Load from .env if exists
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GINTO_DIR="$(dirname "$SCRIPT_DIR")"
if [ -f "$GINTO_DIR/.env" ]; then
    source "$GINTO_DIR/.env" 2>/dev/null || true
fi

echo "╔════════════════════════════════════════╗"
echo "║   Ginto AI - Starting LLM Models       ║"
echo "╚════════════════════════════════════════╝"
echo ""

# Function to kill process on port
kill_port() {
    local port=$1
    local pids=$(lsof -t -i :"$port" 2>/dev/null || true)
    if [ -n "$pids" ]; then
        echo "[INFO] Stopping existing process on port $port (PID: $pids)"
        kill $pids 2>/dev/null || true
        sleep 1
    fi
}

# Check if llama-server is available
if ! command -v llama-server &>/dev/null; then
    echo "[ERROR] llama-server not found in PATH"
    echo "Please ensure llama.cpp is compiled and in PATH"
    exit 1
fi

# Function to check if port is in use
port_in_use() {
    local port=$1
    if lsof -i :"$port" &>/dev/null || ss -tuln | grep -q ":$port "; then
        return 0  # Port is in use
    fi
    return 1  # Port is free
}

# Start Vision Model (port 8033)
if [ -n "$VISION_HF_MODEL" ] && [ "$VISION_HF_MODEL" != "skip" ]; then
    if $FORCE_RESTART; then
        kill_port $VISION_PORT
    fi
    if port_in_use $VISION_PORT; then
        echo "[OK] Vision Model already running on port $VISION_PORT"
    else
        echo "[INFO] Starting Vision Model: $VISION_HF_MODEL"
        echo "       Port: $VISION_PORT"
        nohup llama-server -hf "$VISION_HF_MODEL" --jinja -c 0 --host 0.0.0.0 --port $VISION_PORT > /tmp/llama-vision.log 2>&1 &
        echo $! > /tmp/llama-vision.pid
        echo "[OK] Vision model started (PID: $!)"
    fi
    echo ""
fi

# Start Reasoning Model (port 8034)
if [ -n "$REASONING_HF_MODEL" ] && [ "$REASONING_HF_MODEL" != "skip" ]; then
    if $FORCE_RESTART; then
        kill_port $REASONING_PORT
    fi
    if port_in_use $REASONING_PORT; then
        echo "[OK] Reasoning Model already running on port $REASONING_PORT"
    else
        echo "[INFO] Starting Reasoning Model: $REASONING_HF_MODEL"
        echo "       Port: $REASONING_PORT"
        nohup llama-server -hf "$REASONING_HF_MODEL" -c 0 --host 0.0.0.0 --port $REASONING_PORT > /tmp/llama-reasoning.log 2>&1 &
        echo $! > /tmp/llama-reasoning.pid
        echo "[OK] Reasoning model started (PID: $!)"
    fi
    echo ""
fi

echo "Models are starting in background. Check logs at:"
echo "  Vision:    /tmp/llama-vision.log"
echo "  Reasoning: /tmp/llama-reasoning.log"
echo ""
echo "To stop models: kill \$(cat /tmp/llama-*.pid 2>/dev/null)"
echo "To view logs:   tail -f /tmp/llama-*.log"
