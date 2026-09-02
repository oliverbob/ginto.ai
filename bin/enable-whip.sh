#!/usr/bin/env bash
#
# WHIP ingest on silverqueen.pro.
#
#   ssh root@162.35.101.26
#   su - oliverbob && cd ginto.ai && git pull
#   sudo bash bin/enable-whip.sh
#
# WHY
#
# A conference confers but does not stream: only the twelve in the call hear
# it. The expensive way to fix that is a server that composites twelve faces
# into one picture, which needs headless Chrome and about four cores. The cheap
# way is to let the host's own page do the compositing — it is already decoding
# every one of those streams — and publish the result as a single WebRTC
# stream. That is what WHIP accepts.
#
# MediaMTX has spoken WHIP for years and it is running here already. It was
# switched off by one line. Everything downstream — the HLS muxer, the /stream
# path, the player, the publish hooks — is the same machinery Go Live has used
# all along, so a conference stream arrives as an ordinary broadcast.
#
# Idempotent: run it again and it changes nothing it has already changed.

set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; DIM='\033[2m'; NC='\033[0m'
say()  { echo -e "${GREEN}$*${NC}"; }
warn() { echo -e "${YELLOW}$*${NC}"; }
die()  { echo -e "${RED}$*${NC}" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "Run this with sudo — it edits /opt and /etc."

CONF=/opt/mediamtx/mediamtx.yml
CADDYFILE=/etc/caddy/Caddyfile
PUBLIC_IP="${PUBLIC_IP:-162.35.101.26}"

[ -f "$CONF" ] || die "No $CONF — is MediaMTX installed here?"

echo "=========================================="
echo " WHIP ingest — silverqueen.pro"
echo "=========================================="
echo

# ── 1. MediaMTX ──────────────────────────────────────────────────────────────
say "[1/4] MediaMTX"

if grep -qE '^webrtc:[[:space:]]*yes' "$CONF"; then
    echo -e "  ${DIM}webrtc already on${NC}"
else
    cp "$CONF" "$CONF.bak.$(date +%Y%m%d%H%M%S)"
    sed -i 's/^webrtc:[[:space:]]*no/webrtc: yes/' "$CONF"
    echo "  webrtc: no -> yes (previous config kept as .bak)"
fi

add_key() {
    grep -qE "^$1:" "$CONF" || {
        sed -i "/^webrtc:[[:space:]]*yes/a $1: $2" "$CONF"
        echo "  added $1: $2"
    }
}

# The HTTP side, where the WHIP offer is POSTed. Loopback would be tidier, but
# MediaMTX serves the WHIP endpoint and the WHEP player from the same listener
# and Caddy fronts it either way.
add_key webrtcAddress ':8889'

# One UDP port for media, for the same reason the SFU uses one: a range is a
# firewall rule per port, and there is no firewall here to keep tidy but there
# will be one day.
add_key webrtcLocalUDPAddress ':8189'

# What the server tells a browser to send media to. Without it MediaMTX offers
# the addresses it can see on its own interfaces, and on a VPS that is fine —
# but stating it removes the guess.
grep -qE '^webrtcAdditionalHosts:' "$CONF" || {
    sed -i "/^webrtc:[[:space:]]*yes/a webrtcAdditionalHosts: [$PUBLIC_IP]" "$CONF"
    echo "  added webrtcAdditionalHosts: [$PUBLIC_IP]"
}

systemctl restart mediamtx
sleep 2

systemctl is-active --quiet mediamtx || {
    journalctl -u mediamtx -n 20 --no-pager || true
    die "  MediaMTX did not come back. The previous config is beside it as .bak."
}

echo "  mediamtx restarted"
ss -lntu 2>/dev/null | grep -E ':(8889|8189|1935|8888)\b' | sed 's/^/    /' || true

# ── 2. Caddy ─────────────────────────────────────────────────────────────────
say "[2/4] Caddy"

if [ ! -f "$CADDYFILE" ]; then
    warn "  No $CADDYFILE — expose 127.0.0.1:8889 yourself."
else
    if grep -q 'handle /whip/\*' "$CADDYFILE"; then
        echo -e "  ${DIM}/whip/* already proxied${NC}"
    else
        cp "$CADDYFILE" "$CADDYFILE.bak.$(date +%Y%m%d%H%M%S)"

        # Inserted into the apex block, beside the HLS it feeds — and
        # deliberately NOT on a subdomain of its own. A new host needs a new
        # certificate, and on-demand issuance here is gated by the tunnel
        # verifier; the apex already has a working certificate and needs
        # nothing granted to it.
        python3 - "$CADDYFILE" <<'PY'
import sys, re
path = sys.argv[1]
src  = open(path).read()

anchor = "\thandle /messenger-ws/* {"
block = """\thandle /whip/* {
\t\t# WHIP: the browser POSTs an SDP offer here and MediaMTX answers.
\t\t# Written by ginto.ai/bin/enable-whip.sh.
\t\turi strip_prefix /whip
\t\treverse_proxy 127.0.0.1:8889 {
\t\t\t# An SDP exchange is small and immediate; buffering it adds
\t\t\t# latency to the one part of a stream that is a handshake.
\t\t\tflush_interval -1
\t\t}
\t\theader Access-Control-Allow-Origin *
\t\theader Access-Control-Allow-Headers *
\t\theader Access-Control-Allow-Methods "POST, PATCH, DELETE, OPTIONS"
\t}

"""
assert src.count(anchor) == 1, "could not find the messenger-ws anchor"
open(path, "w").write(src.replace(anchor, block + anchor, 1))
print("  inserted handle /whip/* into the apex block")
PY
    fi

    if caddy validate --config "$CADDYFILE" >/dev/null 2>&1; then
        chown -R caddy:caddy /var/log/caddy 2>/dev/null || true
        systemctl reload caddy
        echo "  Caddy reloaded"
    else
        caddy validate --config "$CADDYFILE" 2>&1 | tail -5 | sed 's/^/    /'
        die "  Caddy did not validate. Nothing is live; the previous file is beside it as .bak."
    fi
fi

# ── 3. does it answer ────────────────────────────────────────────────────────
say "[3/4] checking"

code=$(curl -s -o /dev/null -w '%{http_code}' -m 5 http://127.0.0.1:8889/ || true)
echo "  mediamtx webrtc listener: HTTP $code"

# ── 4. what to publish to ────────────────────────────────────────────────────
say "[4/4] endpoint"

echo
echo "  Publish (WHIP):  https://silverqueen.pro/whip/live/<key>/whip"
echo "  Watch (HLS):     https://silverqueen.pro/stream/<key>/index.m3u8"
echo
echo -e "${DIM}  The key is the credential, exactly as it is for RTMP: there is no"
echo -e "  publish authentication on this server, and an unguessable path is what"
echo -e "  stands in for one. Mint conference keys the way broadcasts already"
echo -e "  mint theirs, and never log them.${NC}"
echo
say " Done."
