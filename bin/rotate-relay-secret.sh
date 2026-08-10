#!/usr/bin/env bash
#
# Rotate the relay signing secret shared by ginto.ai and its callers.
#
# The secret lives in a file on two separate hosts, so it cannot change on both
# at the same instant. Rotating naively means every request in the gap is
# refused, and the gap lasts as long as it takes to edit the second file. This
# script uses the overlap RelayAuth supports instead, in three phases:
#
#   1. accept  — the new secret goes on ginto.ai as RELAY_JWT_SECRET and the old
#                one moves to RELAY_JWT_SECRET_PREVIOUS. Both are now honoured,
#                so callers still signing with the old secret keep working.
#   2. issue   — callers are updated to sign with the new secret.
#   3. retire  — RELAY_JWT_SECRET_PREVIOUS is cleared, so only the new secret
#                is honoured. Rotation is not finished until this runs; until it
#                does, two keys can mint a token.
#
# Every .env is backed up before it is touched, and each phase verifies with a
# live call before it reports success.
#
# Usage:
#   bin/rotate-relay-secret.sh accept            # phase 1, on ginto.ai
#   bin/rotate-relay-secret.sh issue <secret>    # phase 2, on each caller
#   bin/rotate-relay-secret.sh retire            # phase 3, on ginto.ai
#   bin/rotate-relay-secret.sh status            # what is configured right now
#
# For the common case where both checkouts are on this machine, `local` does all
# three against ~/repo/blockchain in one go.
#   bin/rotate-relay-secret.sh local

set -euo pipefail

GINTO_DIR=${GINTO_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}
GINTO_ENV="$GINTO_DIR/.env"
CALLER_DIR=${CALLER_DIR:-$HOME/repo/blockchain}
CALLER_ENV="$CALLER_DIR/.env"
VERIFY_URL=${VERIFY_URL:-http://127.0.0.1:8000}
VERIFY_USER=${VERIFY_USER:-}

die() { printf '\033[31merror:\033[0m %s\n' "$*" >&2; exit 1; }
note() { printf '  %s\n' "$*"; }
section() { printf '\n\033[1m%s\033[0m\n' "$*"; }

# set_env <file> <KEY> <value> — replace the key in place, or append it.
set_env() {
  local file=$1 key=$2 val=$3
  [ -f "$file" ] || die "no .env at $file"
  if grep -q "^${key}=" "$file"; then
    # The value is hex from `openssl rand`, so no escaping games are needed —
    # but go through a temp file so a failed write cannot truncate the .env.
    awk -v k="$key" -v v="$val" \
      'BEGIN{FS=OFS="="} $1==k {print k "=" v; next} {print}' "$file" > "$file.tmp"
    mv "$file.tmp" "$file"
  else
    printf '%s=%s\n' "$key" "$val" >> "$file"
  fi
}

get_env() { grep "^$2=" "$1" 2>/dev/null | head -n1 | cut -d= -f2- || true; }

backup() {
  local file=$1 stamp
  stamp=$(date +%Y%m%d-%H%M%S)
  cp "$file" "$file.bak-$stamp"
  note "backed up $(basename "$file") -> $(basename "$file").bak-$stamp"
}

fingerprint() {   # never print a secret; print something you can compare
  local v=$1
  [ -z "$v" ] && { echo "(unset)"; return; }
  printf '%s… (%d chars, sha256:%s)\n' "${v:0:4}" "${#v}" "$(printf '%s' "$v" | sha256sum | cut -c1-12)"
}

# A live call proves the two sides agree. Needs a username with an active
# subscription; without one the relay correctly answers 403 and we can only
# prove the signature was accepted, which is still the thing being rotated.
verify() {
  local secret=$1 user=${2:-$VERIFY_USER}
  [ -z "$user" ] && { note "no VERIFY_USER set — skipping the live check"; return 0; }

  local token code
  token=$(php -r '
    require getenv("CALLER_DIR") . "/vendor/autoload.php"; $n = time();
    echo Firebase\JWT\JWT::encode([
      "sub" => $argv[1], "aud" => "ginto-relay", "iss" => "rotate-script",
      "iat" => $n, "exp" => $n + 300, "jti" => bin2hex(random_bytes(16)),
    ], $argv[2], "HS256");' "$user" "$secret") || die "could not mint a test token"

  code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 \
         "$VERIFY_URL/api/v1/relay/session" -H "Authorization: Bearer $token") || true

  case "$code" in
    200) note "verified: relay served $user (HTTP 200)"; return 0 ;;
    403) note "verified: signature accepted; $user has no active subscription (HTTP 403)"; return 0 ;;
    401) return 1 ;;
    *)   note "relay answered HTTP $code — could not verify"; return 1 ;;
  esac
}

export CALLER_DIR

case "${1:-}" in

  status)
    section "ginto.ai — $GINTO_ENV"
    note "RELAY_JWT_SECRET          $(fingerprint "$(get_env "$GINTO_ENV" RELAY_JWT_SECRET)")"
    note "RELAY_JWT_SECRET_PREVIOUS $(fingerprint "$(get_env "$GINTO_ENV" RELAY_JWT_SECRET_PREVIOUS)")"
    note "RELAY_ALLOWED_USERS       $(get_env "$GINTO_ENV" RELAY_ALLOWED_USERS || echo '(any member)')"
    if [ -f "$CALLER_ENV" ]; then
      section "caller — $CALLER_ENV"
      note "GINTO_RELAY_SECRET        $(fingerprint "$(get_env "$CALLER_ENV" GINTO_RELAY_SECRET)")"
      note "GINTO_RELAY_URL           $(get_env "$CALLER_ENV" GINTO_RELAY_URL)"
      section "match"
      if [ "$(get_env "$GINTO_ENV" RELAY_JWT_SECRET)" = "$(get_env "$CALLER_ENV" GINTO_RELAY_SECRET)" ]; then
        note "caller is signing with the CURRENT secret"
      elif [ -n "$(get_env "$GINTO_ENV" RELAY_JWT_SECRET_PREVIOUS)" ] \
        && [ "$(get_env "$GINTO_ENV" RELAY_JWT_SECRET_PREVIOUS)" = "$(get_env "$CALLER_ENV" GINTO_RELAY_SECRET)" ]; then
        note "caller is still on the PREVIOUS secret — run 'issue' then 'retire'"
      else
        note "caller's secret matches NEITHER — calls are being refused"
      fi
    fi
    ;;

  accept)
    section "Phase 1 — ginto.ai accepts a new secret alongside the old one"
    old=$(get_env "$GINTO_ENV" RELAY_JWT_SECRET)
    [ -z "$old" ] && die "RELAY_JWT_SECRET is not set; nothing to rotate from"
    new=$(openssl rand -hex 32)

    backup "$GINTO_ENV"
    set_env "$GINTO_ENV" RELAY_JWT_SECRET "$new"
    set_env "$GINTO_ENV" RELAY_JWT_SECRET_PREVIOUS "$old"
    note "new secret installed; old secret retained as RELAY_JWT_SECRET_PREVIOUS"

    if ! verify "$old"; then
      die "the OLD secret is no longer accepted — callers would break. Restore the .bak and investigate."
    fi
    verify "$new" || die "the NEW secret is not accepted. Restore the .bak and investigate."

    section "Next"
    note "Give this to every caller, then run: $0 retire"
    printf '\n  %s\n\n' "$new"
    ;;

  issue)
    secret=${2:-}
    [ -z "$secret" ] && die "usage: $0 issue <secret>"
    [ ${#secret} -lt 32 ] && die "that secret is ${#secret} chars; the minimum is 32"
    section "Phase 2 — caller signs with the new secret"
    backup "$CALLER_ENV"
    set_env "$CALLER_ENV" GINTO_RELAY_SECRET "$secret"
    note "updated GINTO_RELAY_SECRET in $CALLER_ENV"
    verify "$secret" || die "the relay refuses the new secret — is phase 1 deployed there?"
    ;;

  retire)
    section "Phase 3 — stop accepting the previous secret"
    prev=$(get_env "$GINTO_ENV" RELAY_JWT_SECRET_PREVIOUS)
    [ -z "$prev" ] && { note "RELAY_JWT_SECRET_PREVIOUS is already clear — nothing to retire"; exit 0; }

    cur=$(get_env "$GINTO_ENV" RELAY_JWT_SECRET)
    if [ -f "$CALLER_ENV" ] && [ "$(get_env "$CALLER_ENV" GINTO_RELAY_SECRET)" = "$prev" ]; then
      die "$CALLER_ENV is still on the previous secret. Run '$0 issue' there first, or its calls will start failing."
    fi

    backup "$GINTO_ENV"
    set_env "$GINTO_ENV" RELAY_JWT_SECRET_PREVIOUS ""
    note "previous secret retired; only the current secret is accepted now"
    verify "$cur" || die "the current secret is not accepted — restore the .bak"
    verify "$prev" && die "the previous secret is STILL accepted — retire did not take effect" || \
      note "confirmed: the old secret is now refused"
    ;;

  local)
    section "Rotating both sides on this machine"
    [ -f "$CALLER_ENV" ] || die "no caller .env at $CALLER_ENV (set CALLER_DIR)"
    out=$("$0" accept) || { echo "$out"; exit 1; }
    echo "$out"
    new=$(echo "$out" | tail -n2 | tr -d '[:space:]')
    "$0" issue "$new"
    "$0" retire
    section "Done"
    "$0" status
    ;;

  *)
    sed -n '2,30p' "${BASH_SOURCE[0]}" | sed 's/^# \?//'
    exit 1
    ;;
esac
