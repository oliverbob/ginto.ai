#!/usr/bin/env bash
# ──────────────────────────────────────────────────────────────────────────────
# setup-live.sh — Provision the live-streaming stack on a server.
#
# What this installs:
#   1. MediaMTX (RTMP ingest → HLS segments written to disk) on :1935 / :8888
#   2. runOnReady / runOnNotReady hooks that tell comchain a broadcast started
#      and ended, signed with the shared LIVE_HOOK_SECRET
#   3. A systemd unit for the mediamtx binary
#   4. A Caddy route at silverqueen.pro/stream/* serving those segments
#
# Prerequisites:
#   - Ubuntu, root SSH
#   - Caddy already running and serving silverqueen.pro on :443
#   - Caddyfile at /etc/caddy/Caddyfile
#
# Usage:
#   LIVE_HOOK_SECRET=<shared secret> \
#     ssh root@<host> 'LIVE_HOOK_SECRET=<shared secret> bash -s' < bin/setup-live.sh
#
#   Add FORCE=1 to re-provision a host that already has a stack installed.
#   Without it this script refuses to touch a running mediamtx, because
#   overwriting mediamtx.yml drops every broadcast currently on air.
# ──────────────────────────────────────────────────────────────────────────────
set -euo pipefail

MEDIA_VERSION="v1.20.1"
MEDIA_URL="https://github.com/bluenviron/mediamtx/releases/download/${MEDIA_VERSION}/mediamtx_${MEDIA_VERSION}_linux_amd64.tar.gz"
INSTALL_DIR="/opt/mediamtx"
HLS_DIR="${INSTALL_DIR}/hls"
CADDYFILE="/etc/caddy/Caddyfile"
RTMP_PORT=1935
HLS_PORT=8888
API_PORT=9997
COMCHAIN_HOOKS="https://comchain.silverqueen.pro/api/v1/live/hook"

: "${LIVE_HOOK_SECRET:?LIVE_HOOK_SECRET must be set — it is the same value comchain has in its .env}"
FORCE="${FORCE:-0}"

# ── Refuse to knock a live stack over by accident ────────────────────────────
if systemctl is-active mediamtx >/dev/null 2>&1 && [ "$FORCE" != "1" ]; then
    ONAIR=$(curl -s --max-time 3 "http://127.0.0.1:${API_PORT}/v3/paths/list" 2>/dev/null \
            | grep -o '"ready":true' | wc -l)
    echo "mediamtx is already running with ${ONAIR} path(s) on air."
    echo "Re-provisioning rewrites its config and restarts it, which ends every"
    echo "broadcast in progress. Re-run with FORCE=1 if that is what you want."
    exit 1
fi

echo "==> Installing mediamtx ${MEDIA_VERSION} …"
mkdir -p "$INSTALL_DIR" "$HLS_DIR"
curl -sL "$MEDIA_URL" | tar xz -C "$INSTALL_DIR"
chmod +x "$INSTALL_DIR/mediamtx"

# The shipped mediamtx.yml is a few hundred lines of defaults. We write only
# what we depart from — the binary fills in the rest, and a short file is one
# you can actually read when a stream misbehaves at 3am.
#
# hlsDirectory is the whole design: segments land on disk and Caddy serves them
# as files. Proxying MediaMTX's own HLS server instead means inheriting its
# cookie handshake, which is built for HTTPS while the server speaks HTTP, and
# no arrangement of Caddy headers makes that come out right.
echo "==> Writing mediamtx.yml …"
cat > "$INSTALL_DIR/mediamtx.yml" <<YAML
readTimeout: 30s
writeTimeout: 30s
logLevel: info
logDestinations: [stdout]
api: yes
apiAddress: :${API_PORT}
rtsp: yes
rtmp: yes
rtmpAddress: :${RTMP_PORT}
hls: yes
hlsAddress: :${HLS_PORT}
hlsVariant: mpegts
hlsSegmentCount: 3
hlsSegmentDuration: 1s
hlsAlwaysRemux: yes
hlsDirectory: ${HLS_DIR}
webrtc: no
srt: no
playback: no
metrics: no
pprof: no
pathDefaults:
  runOnReady: ${INSTALL_DIR}/hook-publish.sh
  runOnNotReady: ${INSTALL_DIR}/hook-unpublish.sh
  source: publisher
paths:
  all_others:
YAML

# ── Hooks ────────────────────────────────────────────────────────────────────
# comchain's LiveController gates these on HMAC-SHA256 over "<action>|<key>",
# because an encoder carries no session and cannot be asked to hold a wallet.
#
# The `exec sleep` at the end of the publish hook is not idle padding. Under
# runOnReady MediaMTX expects the hook process to live as long as the
# publisher, and sends it SIGINT on disconnect — which is what triggers
# runOnNotReady. A hook that exits after its curl ends the broadcast the
# instant it begins.
echo "==> Writing hooks …"
cat > "$INSTALL_DIR/hook-publish.sh" <<HOOK
#!/bin/bash
# RTMP connecting is not the same as a playable stream. An encoder that sends
# one keyframe and no more keeps the connection open and the path "ready" while
# MediaMTX, which starts every segment on a keyframe, never closes one and
# never writes a playlist. Announcing on connect put a live badge on streams
# whose index.m3u8 was a 404 for their whole duration.
#
# So wait for the playlist. If it never appears, say nothing: the broadcast
# stays "created", the page keeps showing connecting, and nobody is invited to
# watch a stream that cannot play.
KEY="\${MTX_PATH#live/}"
PLAYLIST="${HLS_DIR}/\${MTX_PATH}/index.m3u8"

for _ in \$(seq 1 40); do
  [ -s "\$PLAYLIST" ] && break
  sleep 0.5
done

if [ -s "\$PLAYLIST" ]; then
  SIG=\$(printf "%s|%s" "publish" "\$KEY" | openssl dgst -sha256 -hmac "${LIVE_HOOK_SECRET}" -hex 2>/dev/null | awk '{print \$NF}')
  curl -s --max-time 5 -X POST "${COMCHAIN_HOOKS}/publish" \\
    -d "key=\$KEY" -d "sig=\$SIG" >/dev/null 2>&1
else
  logger -t mediamtx-hook "no HLS playlist for \$KEY after 20s; not announcing it live"
fi

# Stay alive regardless: MediaMTX SIGINTs this on disconnect, and that is what
# triggers runOnNotReady.
exec sleep 86400
HOOK

cat > "$INSTALL_DIR/hook-unpublish.sh" <<HOOK
#!/bin/bash
KEY="\${MTX_PATH#live/}"
SIG=\$(printf "%s|%s" "unpublish" "\$KEY" | openssl dgst -sha256 -hmac "${LIVE_HOOK_SECRET}" -hex 2>/dev/null | awk '{print \$NF}')
curl -s --max-time 5 -X POST "${COMCHAIN_HOOKS}/unpublish" \\
  -d "key=\$KEY" -d "sig=\$SIG" >/dev/null 2>&1 &
exit 0
HOOK

# 700, not 755: these scripts carry the shared hook secret in plain text, and
# only the user MediaMTX runs as has any business reading them.
chmod 700 "$INSTALL_DIR/hook-publish.sh" "$INSTALL_DIR/hook-unpublish.sh"

echo "==> Writing systemd unit …"
cat > /etc/systemd/system/mediamtx.service <<UNIT
[Unit]
Description=MediaMTX — RTMP ingest + HLS
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
ExecStart=${INSTALL_DIR}/mediamtx ${INSTALL_DIR}/mediamtx.yml
Restart=always
RestartSec=3
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target
UNIT

systemctl daemon-reload
systemctl enable mediamtx
systemctl restart mediamtx
sleep 2
if systemctl is-active mediamtx >/dev/null 2>&1; then
    echo "   mediamtx: running"
else
    echo "   mediamtx: FAILED — check journalctl -u mediamtx"; exit 1
fi

# ── Caddy: serve the segments as files ───────────────────────────────────────
if ! grep -q 'handle_path /stream/\*' "$CADDYFILE" 2>/dev/null; then
    echo "==> Adding /stream/* file_server route to the Caddyfile …"

    # handle_path strips the prefix, so /stream/<key>/index.m3u8 reads
    # <hls dir>/live/<key>/index.m3u8 — the same <key> the RTMP publisher used.
    sed -i "/encode zstd gzip/a\\
\\
    handle_path /stream/* {\\
        root * ${HLS_DIR}/live\\
        file_server\\
        header {\\
            Access-Control-Allow-Origin *\\
            Cache-Control \"no-cache\"\\
        }\\
    }" "$CADDYFILE"

    echo "   Reloading Caddy …"
    systemctl reload caddy 2>/dev/null || systemctl restart caddy
else
    echo "==> /stream/* route already in the Caddyfile — skipping."
fi

echo ""
echo "═══════════════════════════════════════════════════════════════════"
echo "  Live streaming stack ready"
echo ""
echo "  RTMP ingest  :  rtmp://silverqueen.pro:${RTMP_PORT}/live/<stream_key>"
echo "  HLS playback :  https://silverqueen.pro/stream/<stream_key>/index.m3u8"
echo ""
echo "  comchain .env must match:"
echo "    LIVE_INGEST_URL=rtmp://silverqueen.pro:${RTMP_PORT}/live"
echo "    LIVE_HLS_BASE=https://silverqueen.pro/stream"
echo "    LIVE_HOOK_SECRET=<the same secret passed to this script>"
echo "═══════════════════════════════════════════════════════════════════"
