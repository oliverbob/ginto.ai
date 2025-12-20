#!/usr/bin/env bash
set -e
# Start the GitHub MCP server from tools/github-mcp if present.
cd "$(dirname "$0")/.."
set -o allexport
[ -f .env ] && . .env
set +o allexport

# Optional flag to skip auto-installing python
SKIP_PYTHON_INSTALL=${SKIP_PYTHON_INSTALL:-0}

# Ensure Python 3.11+ exists when not skipped. This helper will attempt to
# install Python via the system package manager if it is missing. It makes a
# best-effort attempt and will not abort the entire script on failure.
ensure_python() {
  if [ "$SKIP_PYTHON_INSTALL" = "1" ]; then
    echo "SKIP_PYTHON_INSTALL=1; skipping python auto-install"
    return 0
  fi

  need_py="3.11"
  if command -v python3 >/dev/null 2>&1; then
    if check_python_version; then
      echo "python3 present and version is >= $need_py"
    else
      echo "python3 is present but < $need_py, attempting install or upgrade..."
      attempt_install_python || true
    fi
  else
    echo "python3 not found; attempting to install python3 (>= $need_py)..."
    attempt_install_python || true
  fi
}

attempt_install_python() {
  # Use sudo if available and not root
  SUDO=""
  if [ "$EUID" -ne 0 ] && command -v sudo >/dev/null 2>&1; then
    SUDO="sudo"
  fi

  if command -v apt-get >/dev/null 2>&1; then
    echo "Detected apt-get; attempting apt-get install python3.11 and venv"
    $SUDO apt-get update -y || true
    # Try to install python3.11 first, then fall back to python3 packages
    if $SUDO apt-get install -y python3.11 python3.11-venv python3.11-distutils >/dev/null 2>&1; then
      return 0
    fi
    $SUDO apt-get install -y python3 python3-venv python3-distutils >/dev/null 2>&1 || true
    return 0
  elif command -v dnf >/dev/null 2>&1; then
    echo "Detected dnf; attempting dnf install python3"
    $SUDO dnf install -y python3 python3-venv >/dev/null 2>&1 || true
    return 0
  elif command -v yum >/dev/null 2>&1; then
    echo "Detected yum; attempting yum install python3"
    $SUDO yum install -y python3 python3-venv >/dev/null 2>&1 || true
    return 0
  elif command -v pacman >/dev/null 2>&1; then
    echo "Detected pacman; attempting pacman to install python"
    $SUDO pacman -S --noconfirm python python-virtualenv >/dev/null 2>&1 || true
    return 0
  else
    echo "No supported package manager found; please install Python >= 3.11 manually"
    return 1
  fi
}

check_python_version() {
  # Return 0 if python3 exists and is >= 3.11
  if command -v python3 >/dev/null 2>&1; then
    python3 - <<'PY' >/dev/null 2>&1
import sys
v = sys.version_info
if not (v.major == 3 and v.minor >= 11):
    raise SystemExit(1)
PY
    return $?
  fi
  return 1
}

# Try to ensure Python is available before continuing.
if ! check_python_version; then
  ensure_python || echo "Warning: python >= 3.11 not available; groq-mcp python services will not be runnable until installed"
fi

# GitHub MCP removed - was unused

# Start a lightweight PHP stub MCP on 127.0.0.1:9010 so the UI discovery
# has a responsive endpoint. This is idempotent and will not start multiple
# copies if a pid file exists and the process is alive.
STUB_PID_FILE=/tmp/mcp_stub.pid
if [ -f "$STUB_PID_FILE" ]; then
  STUB_PID=$(cat "$STUB_PID_FILE")
  if ps -p "$STUB_PID" > /dev/null 2>&1; then
    echo "mcp_stub already running (pid $STUB_PID)"
  else
    echo "Removing stale mcp_stub pid file"
    rm -f "$STUB_PID_FILE"
  fi
fi

if [ ! -f "$STUB_PID_FILE" ]; then
  if [ -d "$(pwd)/tools/mcp_stub" ]; then
    nohup php -S 127.0.0.1:9010 -t "$(pwd)/tools/mcp_stub" > /tmp/mcp_stub.log 2>&1 &
    echo $! > "$STUB_PID_FILE"
    echo "Started mcp_stub (pid $(cat $STUB_PID_FILE))"
  else
    echo "tools/mcp_stub not present; skipping mcp_stub start"
  fi
fi
