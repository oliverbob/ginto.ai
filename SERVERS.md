# Ginto Speech-to-Text (STT) and Server Architecture

## Overview
Ginto supports speech-to-text (STT) via two main mechanisms:

Note: this repository includes an interactive installer `bin/replace_or_install_server.sh` that can configure Caddy and deploy a `ginto.service` unit. See `docs/SERVERS.md` → "Server replacement helper" for full usage, examples, and flags (`--local`, `--live`, `--force`, `--no-mask`, `--dry-run`, `--yes`, `--upstream-port`).

1. **PHP Endpoint (Default):**
   - Endpoint: `/audio/stt` (handled by PHP server, typically on port 8000)
   - Reads audio uploads and transcribes using either a cloud API (e.g., Groq) or a local Python wrapper.
   - Environment variables are loaded from `.env` using `vlucas/phpdotenv` in `public/index.php`.
   - No dependency on MCP server for basic STT.

2. **Python Realtime STT (Optional):**
   - File: `tools/groq-mcp/src/realtime_stt.py`
   - Used if `USE_PY_STT` is set in `.env`.
   - Provides advanced streaming and VAD-gated transcription.

## Server Ports
- **PHP Built-in Server:** Handles all main endpoints (e.g., `/audio/stt`, `/transcribe`, `/chat`). Usually runs on port 8000:  
  `php -S 0.0.0.0:8000 -t public`
- **MCP Server (Optional):** Used for tool calls and advanced LLM features. Runs on port 9010. Not required for basic STT or chat endpoints.

## Environment Variables
- All environment variables must be set in `.env` and are loaded into `$_ENV` by Dotenv in `public/index.php`.
- Example required variables:
  - `GROQ_API_KEY` (for cloud STT)
  - `USE_PY_STT` (set to `1` to use Python STT)

## File Locations
- PHP STT logic: `src/Routes/web.php`
- Python STT logic: `tools/groq-mcp/src/realtime_stt.py`

## Restarting
- After changing `.env`, restart the PHP server to reload environment variables.

---
