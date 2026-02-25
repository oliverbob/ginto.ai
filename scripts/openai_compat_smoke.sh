#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-https://az.ginto.ai}"
API_KEY="${OPENAI_API_KEY:-${API_KEY:-}}"
IMAGE_PATH="${IMAGE_PATH:-}"
MASK_PATH="${MASK_PATH:-}"
MODEL="${MODEL:-gpt-image-1}"

if [[ -z "${API_KEY}" ]]; then
  echo "[error] Set OPENAI_API_KEY (or API_KEY) before running." >&2
  exit 1
fi

AUTH_HEADER=("-H" "Authorization: Bearer ${API_KEY}")
JSON_HEADER=("-H" "Content-Type: application/json")
COMMON_CURL=("-sS" "--fail-with-body")

hr() {
  echo
  echo "================================================================================"
  echo "$1"
  echo "================================================================================"
}

pp() {
  if command -v jq >/dev/null 2>&1; then
    jq .
  else
    cat
  fi
}

post_json() {
  local path="$1"
  local payload="$2"
  curl "${COMMON_CURL[@]}" "${AUTH_HEADER[@]}" "${JSON_HEADER[@]}" \
    -X POST "${BASE_URL}${path}" \
    -d "${payload}"
}

hr "1) Chat Completions (non-stream)"
post_json "/v1/chat/completions" "$(cat <<JSON
{
  \"model\": \"${MODEL}\",
  \"stream\": false,
  \"messages\": [
    { \"role\": \"system\", \"content\": \"You are a helpful assistant.\" },
    { \"role\": \"user\", \"content\": \"Generate a red square image.\" }
  ]
}
JSON
)" | pp

hr "2) Chat Completions (stream=true, SSE)"
curl "${COMMON_CURL[@]}" "${AUTH_HEADER[@]}" "${JSON_HEADER[@]}" \
  -N -X POST "${BASE_URL}/v1/chat/completions" \
  -d "$(cat <<JSON
{
  \"model\": \"${MODEL}\",
  \"stream\": true,
  \"messages\": [
    { \"role\": \"user\", \"content\": \"Generate a red square image.\" }
  ]
}
JSON
)"

echo
hr "3) Images Generations (b64_json)"
post_json "/v1/images/generations" "$(cat <<JSON
{
  \"model\": \"${MODEL}\",
  \"prompt\": \"A tiny red square icon on white background\",
  \"n\": 1,
  \"size\": \"256x256\",
  \"response_format\": \"b64_json\"
}
JSON
)" | pp

hr "4) Images Generations (url)"
post_json "/v1/images/generations" "$(cat <<JSON
{
  \"model\": \"${MODEL}\",
  \"prompt\": \"A tiny red square icon on white background\",
  \"n\": 1,
  \"size\": \"256x256\",
  \"response_format\": \"url\"
}
JSON
)" | pp

hr "5) Images Edits (multipart)"
if [[ -z "${IMAGE_PATH}" ]]; then
  echo "[skip] IMAGE_PATH not set; skipping /v1/images/edits"
else
  if [[ ! -f "${IMAGE_PATH}" ]]; then
    echo "[error] IMAGE_PATH does not exist: ${IMAGE_PATH}" >&2
    exit 1
  fi

  if [[ -n "${MASK_PATH}" && ! -f "${MASK_PATH}" ]]; then
    echo "[error] MASK_PATH does not exist: ${MASK_PATH}" >&2
    exit 1
  fi

  edit_args=(
    -F "model=${MODEL}"
    -F "prompt=Add a glowing halo around the subject"
    -F "n=1"
    -F "size=256x256"
    -F "response_format=b64_json"
    -F "image=@${IMAGE_PATH}"
  )

  if [[ -n "${MASK_PATH}" ]]; then
    edit_args+=( -F "mask=@${MASK_PATH}" )
  fi

  curl "${COMMON_CURL[@]}" "${AUTH_HEADER[@]}" \
    -X POST "${BASE_URL}/v1/images/edits" \
    "${edit_args[@]}" | pp
fi

hr "6) Models list"
curl "${COMMON_CURL[@]}" "${AUTH_HEADER[@]}" \
  "${BASE_URL}/v1/models" | pp

hr "7) Error format check (invalid messages type)"
set +e
err_output="$(post_json "/v1/chat/completions" "$(cat <<JSON
{
  \"model\": \"${MODEL}\",
  \"messages\": \"invalid-type\"
}
JSON
)" 2>&1)"
err_code=$?
set -e

echo "HTTP command exit code: ${err_code}"
echo "${err_output}" | pp || echo "${err_output}"

echo
echo "Done."
