# /transcribe integration and tests

This repository exposes a simple test UI at `/transcribe` which can record audio
client-side and POST it to the server for transcription.

What we added
- A client-side minimum-duration check (500ms) to avoid uploading extremely
  short clips that upstream/transcription providers reject.
- A small integration script `scripts/test_transcribe.sh` which POSTs a file to
  `/transcribe` using a fetched CSRF token and prints the JSON result.

How to test locally

1. Start the dev stack (will source your repo `.env` automatically):

```bash
composer start
```

2. Run the integration test for WAV or WEBM (script will fetch CSRF token):

```bash
bash scripts/test_transcribe.sh tools/groq-mcp/input/sample.wav
# or
bash scripts/test_transcribe.sh /tmp/some_sample.webm
```

3. Use the browser test page at http://127.0.0.1:8000/transcribe to record and
   try uploads. The client will now prevent too-short uploads and surface
   detailed CLI stderr messages in the debug area when the server reports an
   error (e.g. "audio too short").
