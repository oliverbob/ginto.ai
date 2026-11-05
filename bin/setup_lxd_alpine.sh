#!/usr/bin/env bash
set -e

ALPINE_VERSION="3.20"
BASE_CONTAINER="ginto-base"
CUSTOM_ALIAS="ginto"

echo "=== LXD Alpine Base Image Setup ==="

# 1. Ensure LXD is initialized
if ! lxc info >/dev/null 2>&1; then
  echo "[*] Initializing LXD..."
  lxd init --auto
else
  echo "[*] LXD already initialized"
fi

# 2. Check if base container already exists
if lxc list --format csv -c n | grep -q "^${BASE_CONTAINER}$"; then
  echo "[*] Base container already exists: ${BASE_CONTAINER}"
else
  echo "[*] Launching Alpine ${ALPINE_VERSION} base container..."
  lxc launch images:alpine/${ALPINE_VERSION} ${BASE_CONTAINER}
fi

# 3. Wait for container to be ready
echo "[*] Waiting for container to be ready..."
lxc exec ${BASE_CONTAINER} -- sh -c 'echo Alpine ready' >/dev/null 2>&1

# 4. Install base packages
echo "[*] Installing base packages..."
lxc exec ${BASE_CONTAINER} -- sh -c "
  apk update &&
  apk add --no-cache bash curl ca-certificates
"

# 5. Stop container before publishing
echo "[*] Stopping base container..."
lxc stop ${BASE_CONTAINER} || true

# 6. Publish as reusable image
if lxc image list --format csv -c a | grep -q "^${CUSTOM_ALIAS}$"; then
  echo "[*] Image alias already exists: ${CUSTOM_ALIAS}"
else
  echo "[*] Publishing custom Alpine image..."
  lxc publish ${BASE_CONTAINER} --alias ${CUSTOM_ALIAS}
fi

# 7. Restart base container (optional)
lxc start ${BASE_CONTAINER} || true

echo "=== Done ==="
echo "You can now launch containers with:"
echo "  lxc launch ${CUSTOM_ALIAS} my-container"
