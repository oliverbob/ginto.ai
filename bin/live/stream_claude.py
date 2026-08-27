#!/usr/bin/env python3
"""
A publisher for https://silverqueen.pro/stream/claude — the reference stream.

Python makes the pictures; ffmpeg encodes them and pushes RTMP. That split is
the point: it is the same shape as the Android broadcaster, where the camera
produces frames and MediaCodec encodes them, so the encoder settings that
matter here are the ones that matter there.

The setting that matters most is the keyframe interval. MediaMTX begins every
HLS segment on a keyframe, so an encoder that emits one IDR and then none
leaves the muxer running forever with nothing to close, no playlist on disk,
and a 404 at the playback URL for the whole broadcast. That is not a MediaMTX
fault and no server setting repairs it — the fix has to be at the encoder.
Here it is `-g 60` at 30 fps: one keyframe every two seconds, matching
hlsSegmentDuration.

No credentials anywhere. MediaMTX accepts the publish because the path is
open, and Caddy serves the segments to anyone. Publishing is a push to a URL;
watching is an HTTP GET.
"""

import argparse
import signal
import subprocess
import sys
import time
from datetime import datetime, timezone

import numpy as np

WIDTH, HEIGHT, FPS = 1280, 720, 30
GOP = FPS * 2  # one keyframe every two seconds — see the note above.


def encoder(url: str) -> subprocess.Popen:
    """ffmpeg reading raw frames on stdin and pushing H.264 + AAC over RTMP."""
    cmd = [
        "ffmpeg", "-hide_banner", "-loglevel", "warning",
        # Video: raw frames arriving on the pipe, in real time.
        "-f", "rawvideo", "-pix_fmt", "rgb24",
        "-s", f"{WIDTH}x{HEIGHT}", "-r", str(FPS), "-i", "pipe:0",
        # Audio: a quiet tone, so the stream has two tracks like a real one.
        "-f", "lavfi", "-i", "sine=frequency=440:sample_rate=44100",
        "-c:v", "libx264", "-preset", "veryfast", "-tune", "zerolatency",
        "-profile:v", "high", "-pix_fmt", "yuv420p",
        "-b:v", "2500k", "-maxrate", "2500k", "-bufsize", "5000k",
        # The three flags that decide whether this is watchable at all.
        "-g", str(GOP), "-keyint_min", str(GOP), "-sc_threshold", "0",
        "-c:a", "aac", "-b:a", "128k", "-ar", "44100", "-ac", "1",
        "-f", "flv", url,
    ]
    return subprocess.Popen(cmd, stdin=subprocess.PIPE)


def digits() -> dict:
    """A 3x5 dot font — enough to draw a clock without shipping a font file."""
    raw = {
        "0": "111101101101111", "1": "010010010010010", "2": "111001111100111",
        "3": "111001111001111", "4": "101101111001001", "5": "111100111001111",
        "6": "111100111101111", "7": "111001001001001", "8": "111101111101111",
        "9": "111101111001111", ":": "000010000010000", " ": "000000000000000",
        "-": "000000111000000", ".": "000000000000010",
    }
    return {c: np.array([int(b) for b in bits]).reshape(5, 3) for c, bits in raw.items()}


FONT = digits()


def draw(frame: np.ndarray, text: str, x: int, y: int, scale: int, colour) -> None:
    """Blit text into the frame, one scaled dot at a time."""
    for i, ch in enumerate(text):
        glyph = FONT.get(ch)
        if glyph is None:
            continue
        big = np.kron(glyph, np.ones((scale, scale), dtype=int))
        h, w = big.shape
        left = x + i * (w + scale)
        if left + w > frame.shape[1] or y + h > frame.shape[0]:
            break
        frame[y:y + h, left:left + w][big == 1] = colour


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--url", default="rtmp://127.0.0.1:1935/live/claude")
    ap.add_argument("--seconds", type=float, default=0.0, help="0 runs until stopped")
    args = ap.parse_args()

    ff = encoder(args.url)
    stop = {"now": False}
    signal.signal(signal.SIGTERM, lambda *_: stop.update(now=True))
    signal.signal(signal.SIGINT, lambda *_: stop.update(now=True))

    # A vertical gradient, computed once — the moving parts are drawn on top.
    base = np.zeros((HEIGHT, WIDTH, 3), dtype=np.uint8)
    base[:, :, 2] = np.linspace(40, 120, HEIGHT, dtype=np.uint8)[:, None]
    base[:, :, 0] = np.linspace(10, 40, HEIGHT, dtype=np.uint8)[:, None]

    started = time.time()
    frame_no = 0

    try:
        while not stop["now"]:
            if args.seconds and time.time() - started >= args.seconds:
                break

            frame = base.copy()

            # A sweeping bar: visible proof the picture is moving, not a still.
            bar = int((frame_no * 6) % WIDTH)
            frame[:, bar:bar + 12] = (0, 200, 255)

            # The wall clock, so a viewer can see how far behind live they are.
            clock = datetime.now(timezone.utc).strftime("%H:%M:%S")
            draw(frame, clock, 60, 80, 10, (255, 255, 255))

            # Frame counter and elapsed seconds.
            draw(frame, f"{frame_no}", 60, 260, 4, (180, 220, 255))
            draw(frame, f"{int(time.time() - started)}", 60, 340, 4, (180, 220, 255))

            try:
                ff.stdin.write(frame.tobytes())
            except (BrokenPipeError, ValueError):
                break

            frame_no += 1
            # Pace to the wall clock rather than sleeping a fixed interval, so
            # the stream does not drift away from real time as work varies.
            target = started + frame_no / FPS
            slack = target - time.time()
            if slack > 0:
                time.sleep(slack)
    finally:
        if ff.stdin:
            try:
                ff.stdin.close()
            except Exception:
                pass
        try:
            ff.wait(timeout=10)
        except subprocess.TimeoutExpired:
            ff.kill()

    print(f"published {frame_no} frames in {time.time() - started:.1f}s", file=sys.stderr)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
