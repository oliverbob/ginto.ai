#!/usr/bin/env bash
set -euo pipefail

# Simple helper to run git commands from the repository root reliably.
# Usage: ./bin/git-helper.sh status

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd -P)"

if ! command -v git >/dev/null 2>&1; then
  echo "git: command not found" >&2
  exit 2
fi

if [ "$#" -eq 0 ]; then
  echo "Usage: $(basename "$0") <git-args...>" >&2
  exit 1
fi

git -C "$REPO_ROOT" "$@"
