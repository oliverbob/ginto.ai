#!/bin/bash
# Rescue a stream whose encoder never emits a second keyframe.
#
# MediaMTX starts every HLS segment on a keyframe. An encoder that sends one
# IDR and then none keeps the connection open and the path ready while no
# segment is ever closed and no playlist is ever written, so the broadcast is
# unwatchable for its whole duration. SilverQueen builds before 1.46.64 do
# exactly this: bitrate and fps were transposed in prepareVideo, so MediaCodec
# was told 2,500,000 fps and put its next keyframe millions of frames away.
#
# Re-encoding is the only repair — keyframes cannot be inserted into a stream
# that does not have them. Timing is everything: this must attach while the
# opening IDR is still in MediaMTX's buffer for new readers, which is why the
# publish hook starts it seconds after the stream arrives rather than after a
# viewer complains.
#
# For a healthy encoder this never runs: MediaMTX writes the playlist within a
# couple of seconds and the guard below sees it and exits.
set -u

MTX_PATH="$1"
DIR="/opt/mediamtx/hls/${MTX_PATH}"
PLAYLIST="$DIR/index.m3u8"
PIDFILE="/tmp/hls-repair-$(echo "$MTX_PATH" | tr / _).pid"

# Give the muxer a fair chance first.
for _ in $(seq 1 12); do
    if [ -s "$PLAYLIST" ]; then exit 0; fi
    sleep 0.5
done

mkdir -p "$DIR"
logger -t hls-repair "no playlist for ${MTX_PATH} after 6s; starting repair transcode"

# -flags2 +showall is what makes this possible at all. Without it the decoder
# waits for an IDR that is never coming and emits nothing, which is why the
# first version of this script attached to the stream and still produced no
# playlist. With it, frames come out from the first packet: visibly degraded
# until the picture settles, which is worth far more than a 404.
ffmpeg -hide_banner -loglevel warning \
    -fflags +genpts+discardcorrupt -err_detect ignore_err -flags2 +showall \
    -rtsp_transport tcp \
    -i "rtsp://127.0.0.1:8554/${MTX_PATH}" \
    -c:v libx264 -preset veryfast -tune zerolatency -profile:v high \
    -b:v 2000k -maxrate 2000k -bufsize 4000k \
    -g 60 -keyint_min 60 -sc_threshold 0 -pix_fmt yuv420p \
    -c:a aac -b:a 128k -ar 44100 \
    -f hls -hls_time 2 -hls_list_size 7 -hls_flags delete_segments+omit_endlist \
    -hls_segment_filename "$DIR/rep%d.ts" "$PLAYLIST" \
    >/dev/null 2>&1 &

echo $! > "$PIDFILE"
