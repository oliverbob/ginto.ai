#!/usr/bin/env bash
# deploy/sandboxd-smoke.sh
# Basic smoke-test for the socket-activated sandboxd broker.
# Usage: sudo ./deploy/sandboxd-smoke.sh <sandboxId> <hostPath>

set -euo pipefail

SANDBOX=${1:-testsmoke}
HOSTPATH=${2:-/home/$(whoami)/ginto/clients/${SANDBOX}}
SOCKS=(/run/ginto-sandboxd.sock /run/sandboxd.sock /run/ginto/sandboxd.sock)
FOUND=""
for s in "${SOCKS[@]}"; do
  if [ -S "$s" ]; then
    FOUND=$s
    break
  fi
done

if [ -z "$FOUND" ]; then
  echo "No sandboxd socket found (looked at: ${SOCKS[*]}). Is sandboxd.socket enabled?" >&2
  exit 2
fi

MSG="{\"action\":\"create\",\"sandboxId\":\"${SANDBOX}\",\"hostPath\":\"${HOSTPATH}\"}"

# Use socat if available, otherwise try nc -U
if command -v socat >/dev/null 2>&1; then
  echo "Sending to $FOUND: $MSG"
  printf '%s
' "$MSG" | socat - UNIX-CONNECT:${FOUND}
elif command -v nc >/dev/null 2>&1; then
  printf '%s
' "$MSG" | nc -U ${FOUND}
else
  echo "Install socat or netcat (nc) to use this smoke test." >&2
  exit 2
fi

exit 0
