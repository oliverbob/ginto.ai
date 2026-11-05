#!/usr/bin/env bash
set -euo pipefail

# Simple integration test for /transcribe endpoint.
# Usage: ./bin/test_transcribe.sh <audio_file>

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FILE=${1:-""}

if [ -z "$FILE" ] || [ ! -f "$FILE" ]; then
  echo "Usage: $0 <audio_file>" >&2
  echo "  Provide a WAV or audio file to test transcription" >&2
  exit 1
fi

echo "Testing /transcribe with file: $FILE"

COOKIEJAR="/tmp/ginto_test_cookiejar"
rm -f "$COOKIEJAR"

CSRF_JSON=$(curl -sS -c "$COOKIEJAR" http://127.0.0.1:8000/dev/csrf)
CSRF_TOKEN=$(echo "$CSRF_JSON" | grep -o '"csrf_token"[^,]*' | awk -F '"' '{print $4}')

if [ -z "$CSRF_TOKEN" ]; then
  echo "Could not fetch CSRF token; is the server running?" >&2
  echo "Server output (last 50 lines):"; tail -n 50 /tmp/ginto-php.log || true
  exit 2
fi

echo "Using csrf: $CSRF_TOKEN"

curl -sS -b "$COOKIEJAR" -c "$COOKIEJAR" -X POST \
  -F "csrf_token=$CSRF_TOKEN" \
  -F "file=@${FILE}" \
  http://127.0.0.1:8000/transcribe | jq -C .

echo "done"