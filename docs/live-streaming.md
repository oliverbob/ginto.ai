# Live streaming

Going live from the SilverQueen Android app, end to end. Three repos and three
hosts take part, and each one owns exactly one thing.

```
Android app (LiveStreamActivity, RootEncoder)
  │  camera + mic, H.264/AAC
  ▼
RTMP  rtmp://silverqueen.pro:1935/live/<stream_key>
  │
MediaMTX on interserver
  ├── writes HLS segments to /opt/mediamtx/hls/live/<stream_key>/
  ├── runOnReady   → POST comchain /api/v1/live/hook/publish    (broadcast → live)
  └── runOnNotReady→ POST comchain /api/v1/live/hook/unpublish  (broadcast → ended)
  │
Caddy  handle_path /stream/*  → file_server over that directory
  ▼
Viewers  https://silverqueen.pro/stream/<stream_key>/index.m3u8   (video.js in the chat page)
```

Since 2026-08-31 a broadcast is also **recorded**, uploaded to B2 and posted to
the feed once it ends. That half is documented in `~/repo/comchain/RECORDING.md`
— read it before changing the hooks, and note that the $5/month gate deciding
*who* gets a server recording is **not built yet**, so the pipeline currently
records everyone.

The media server, not the API, decides when a broadcast is really live. The row
goes `created` when someone taps Go live, `live` only when frames actually
arrive, and `ended` when the encoder disconnects — however it disconnected.
That is why a crashed phone cannot leave a ghost stream standing.

## Who owns what

| Repo | Owns |
|---|---|
| `~/AndroidStudioProjects/silverqueen-apk` | `LiveStreamActivity` — camera, encoder, RTMP push. `LiveStreamService` — the foreground service that makes holding the camera legal. `MainActivity.SilverQueenBridge.liveStart/liveStop/liveState` — the JS bridge. |
| `~/repo/comchain` (CT108) | `Api\LiveController` + `LiveBroadcast` — minting keys, the two webhooks, the viewer count, the went-live push. `app/views/chat/index.view.php` — the Go live sheet and the video.js watch player. |
| `~/repo/ginto.ai` (interserver) | `bin/setup-live.sh` — provisions MediaMTX, the hooks, the systemd unit and the Caddy route, and now the recorder. `bin/live/hls-repair.sh` — the watchdog for streams with no second keyframe. `bin/live/publish-recording.php` — uploads a finished recording to B2 and tells comchain. `src/Helpers/B2Helper::uploadFile()` — the streaming upload. `/admin/stream-notes` in `src/Routes/web.php` — the admin gate on `/stream/test/claude.md`. |

## Hosts

| Host | Address | Role |
|---|---|---|
| interserver | 162.35.101.26 (`silverqueen.pro`) | Caddy, MediaMTX, RTMP ingest, HLS on disk |
| CT108 | 192.168.1.108 | comchain app server |
| CT104 | 192.168.1.101 | silverqueen-api (wallet host, `sq.silverqueen.pro`) |

## Configuration

comchain's `.env` on CT108 — these three must agree with what `setup-live.sh`
put on interserver, or broadcasts mint keys that go nowhere:

```
LIVE_INGEST_URL=rtmp://silverqueen.pro:1935/live
LIVE_HLS_BASE=https://silverqueen.pro/stream
LIVE_HOOK_SECRET=<shared with /opt/mediamtx/hook-*.sh — not in this repo>
```

The hook secret and the JWT secret live in gitignored `.env` files and in the
hook scripts on interserver (mode 700). Read them there; they are deliberately
absent from every repo.

## Provisioning

```bash
LIVE_HOOK_SECRET=<secret> ssh root@162.35.101.26 \
  'LIVE_HOOK_SECRET=<secret> bash -s' < bin/setup-live.sh
```

The script refuses to run against a host whose mediamtx is already up, because
rewriting `mediamtx.yml` and restarting drops every broadcast on air. `FORCE=1`
overrides that, and should only be used when you know the box is idle.

## Two decisions worth not re-litigating

**HLS is served from disk, not proxied.** MediaMTX's own HLS server sets a
`cookieCheck` cookie marked `Secure; SameSite=None; Partitioned` while itself
speaking plain HTTP behind Caddy. No arrangement of `header_up`, query
rewriting or `handle_response` made segment delivery reliable through it. The
working shape is `hlsDirectory` plus Caddy `file_server`, and the debugging
that led there is in `/stream/test/claude.md` (admin-gated) and in
`HLS-DEBUG-LOG.md`.

**The hooks are `runOnReady`/`runOnNotReady`, and the publish hook sleeps.**
Under `runOnAvailable` MediaMTX kills the hook process after roughly eight
seconds and the stream dies with it. Under `runOnReady` the process is expected
to live as long as the publisher and gets SIGINT on disconnect — which is what
fires the unpublish hook. Hence `exec sleep 86400` as the last line of
`hook-publish.sh`. It is load-bearing, not leftover.

## Testing without a phone

```bash
ffmpeg -re -f lavfi -i testsrc=size=1280x720:rate=30 \
       -f lavfi -i sine=frequency=440 \
       -c:v libx264 -preset veryfast -c:a aac -f flv \
       rtmp://silverqueen.pro:1935/live/<stream_key>
```

`-stream_loop -1` does not work with lavfi inputs; give the source a `duration`
instead. `https://silverqueen.pro/stream/test` is a standing test page against
the `test` key.

## Checking on it

```bash
ssh root@162.35.101.26 'curl -s http://127.0.0.1:9997/v3/paths/list'   # what is on air
ssh root@162.35.101.26 'journalctl -u mediamtx -n 50'                  # why it is not
ls /opt/mediamtx/hls/live/<key>/                                       # segments landing?
```

An empty segment directory for a path the API reports as `ready` means the HLS
muxer is running but not writing — check that `hlsDirectory` survived the last
config edit.
