#!/usr/bin/env bash
# ──────────────────────────────────────────────────────────────────────────────
# setup-live.sh — Provision the live-streaming stack on a server.
#
# What this installs:
#   1. MediaMTX (RTMP ingest → HLS segmentation) on port 1935 / 8888
#   2. Systemd unit for the mediamtx binary
#   3. Caddy route at silverqueen.pro/live/* → HLS proxy
#
# Prerequisites:
#   - Ubuntu, root SSH
#   - Caddy already running and serving silverqueen.pro on :443
#   - Caddyfile at /etc/caddy/Caddyfile
#
# Usage:
#   ssh root@<host> 'bash -s' < ~/ginto.ai/bin/setup-live.sh
# ──────────────────────────────────────────────────────────────────────────────
set -euo pipefail

MEDIA_VERSION="v1.11.1"
MEDIA_URL="https://github.com/bluenviron/mediamtx/releases/download/${MEDIA_VERSION}/mediamtx_${MEDIA_VERSION}_linux_amd64.tar.gz"
INSTALL_DIR="/opt/mediamtx"
CADDYFILE="/etc/caddy/Caddyfile"
RTMP_PORT=1935
HLS_PORT=8888

echo "==> Installing mediamtx ${MEDIA_VERSION} …"
mkdir -p "$INSTALL_DIR"
curl -sL "$MEDIA_URL" | tar xz -C "$INSTALL_DIR" --strip-components=0
chmod +x "$INSTALL_DIR/mediamtx"

# v1.11.1 ships a full default YAML. We download it, then patch only the
# settings we need — the binary rejects YAML keys it does not recognise
# when the file is hand-written (different from the built-in defaults).
echo "==> Fetching default mediamtx.yml …"
curl -sL "https://raw.githubusercontent.com/bluenviron/mediamtx/${MEDIA_VERSION}/mediamtx.yml" \
    -o "$INSTALL_DIR/mediamtx.yml"

# Patch the defaults: disable RTSP/SRT/WebRTC, enable HLS remux.
sed -i \
    -e "s|^rtsp: yes|rtsp: no|" \
    -e "s|^srt: yes|srt: no|" \
    -e "s|^webrtc: yes|webrtc: no|" \
    -e "s|^hlsAlwaysRemux: no|hlsAlwaysRemux: yes|" \
    -e "s|^hlsSegmentCount: 7|hlsSegmentCount: 3|" \
    "$INSTALL_DIR/mediamtx.yml"

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
systemctl enable --now mediamtx
sleep 1
if systemctl is-active mediamtx >/dev/null 2>&1; then
    echo "   mediamtx: running"
else
    echo "   mediamtx: FAILED — check journalctl -u mediamtx"; exit 1
fi

# ── Caddy: add /live/* route if not already present ──────────────────────────
if ! grep -q 'handle /live/\*' "$CADDYFILE" 2>/dev/null; then
    echo "==> Adding /live/* HLS proxy to Caddyfile …"

    # Insert right after the 'encode zstd gzip' line.
    sed -i "/encode zstd gzip/a\\
\\
    handle /live/* {\\
        uri strip_prefix /live\\
        reverse_proxy 127.0.0.1:${HLS_PORT} {\\
            flush_interval -1\\
            transport http {\\
                read_timeout 0\\
                write_timeout 0\\
            }\\
        }\\
    }" "$CADDYFILE"

    echo "   Reloading Caddy …"
    systemctl reload caddy 2>/dev/null || systemctl restart caddy
else
    echo "==> /live/* route already in Caddyfile — skipping."
fi

HOST_IP=$(hostname -I | awk '{print $1}')
echo ""
echo "═══════════════════════════════════════════════════════════════════"
echo "  Live streaming stack ready"
echo ""
echo "  RTMP ingest :  rtmp://${HOST_IP}:${RTMP_PORT}/live/<stream_key>"
echo "  HLS playback :  https://silverqueen.pro/live/<stream_key>/index.m3u8"
echo ""
echo "  Configure comchain .env:"
echo "    LIVE_INGEST_URL=rtmp://silverqueen.pro:${RTMP_PORT}/live"
echo "    LIVE_HLS_BASE=https://silverqueen.pro/live"
echo "═══════════════════════════════════════════════════════════════════"
