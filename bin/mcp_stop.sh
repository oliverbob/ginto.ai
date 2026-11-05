#!/usr/bin/env bash
set -e
# Stop the GitHub MCP server started by `bin/mcp_start.sh`.
cd "$(dirname "$0")/.."
PID_FILE="/tmp/github-mcp.pid"
if [ -f "$PID_FILE" ]; then
  PID=$(cat "$PID_FILE")
  if ps -p "$PID" > /dev/null 2>&1; then
    kill "$PID" && echo "Stopped github-mcp (pid $PID)"
    # give it a moment and ensure it's gone
    sleep 1
    if ps -p "$PID" > /dev/null 2>&1; then
      echo "Process did not exit; sending SIGKILL"
      kill -9 "$PID" || true
    fi
  else
    echo "No process with pid $PID"
  fi
  rm -f "$PID_FILE"
else
  echo "No pid file ($PID_FILE) found; nothing to stop."
fi

# If there is a helper script under tools/, call its stop helper as well
if [ -x "$(pwd)/tools/github-mcp/mcp_stop.sh" ]; then
  bash "$(pwd)/tools/github-mcp/mcp_stop.sh" || true
fi

echo "mcp_stop complete"

# Stop the lightweight mcp_stub if it was started
STUB_PID_FILE=/tmp/mcp_stub.pid
if [ -f "$STUB_PID_FILE" ]; then
  STUB_PID=$(cat "$STUB_PID_FILE")
  if ps -p "$STUB_PID" > /dev/null 2>&1; then
    kill "$STUB_PID" && echo "Stopped mcp_stub (pid $STUB_PID)"
    sleep 1
    if ps -p "$STUB_PID" > /dev/null 2>&1; then
      echo "mcp_stub did not exit; sending SIGKILL"
      kill -9 "$STUB_PID" || true
    fi
  else
    echo "No process with pid $STUB_PID"
  fi
  rm -f "$STUB_PID_FILE" || true
fi

#!/usr/bin/env bash
set -e
# Stop the GitHub MCP server started by `scripts/mcp_start.sh`.
cd "$(dirname "$0")/.."
PID_FILE="/tmp/github-mcp.pid"
if [ -f "$PID_FILE" ]; then
  PID=$(cat "$PID_FILE")
  if ps -p "$PID" > /dev/null 2>&1; then
    kill "$PID" && echo "Stopped github-mcp (pid $PID)"
    # give it a moment and ensure it's gone
    sleep 1
    if ps -p "$PID" > /dev/null 2>&1; then
      echo "Process did not exit; sending SIGKILL"
      kill -9 "$PID" || true
    fi
  else
    echo "No process with pid $PID"
  fi
  rm -f "$PID_FILE"
else
  echo "No pid file ($PID_FILE) found; nothing to stop."
fi

# If there is a helper script under tools/, call its stop helper as well
if [ -x "$(pwd)/tools/github-mcp/mcp_stop.sh" ]; then
  bash "$(pwd)/tools/github-mcp/mcp_stop.sh" || true
fi
