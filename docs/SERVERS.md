```markdown

This document describes everyone about Ginto's servers: roles, default ports,
### Production recommendation — standard: Nginx in front of Apache (Apache on 8090)
- Do NOT expose PHP's built-in server directly on the Internet. Instead:
  - Use **nginx** as the public frontend on 80/443 and reverse-proxy requests to an **Apache** backend that listens on port **8090** (your standard server setup).
  - Configure PHP via `mod_php` or preferably `php-fpm` (fcgi) and have Apache serve the app on 127.0.0.1:8090. Nginx handles TLS termination and reverse proxying.
  - Example flow: Client -> nginx (80/443) -> nginx proxied to Apache (127.0.0.1:8090) -> php-fpm or mod_php
whether those ports need to be public in production, and example nginx
proxy configs. It also points out the files you run for Groq/MCP and lists the
cloud dependencies.
  # Proxy to Apache listening on 127.0.0.1:8090
  proxy_pass http://127.0.0.1:8090;
---

## 1. PHP Application Server
- **Purpose:** Handles the web UI, REST endpoints (e.g. `/audio/stt`, `/transcribe`, `/chat`).
- **Default (dev) port:** 8000 (built-in PHP server)
  - Command: `php -S 0.0.0.0:8000 -t public` (see `bin/start_web.sh`, `bin/start_all.sh`)
- **Entrypoint:** `public/index.php`
- **Config:** `.env` (via `vlucas/phpdotenv`), see `APP_URL`

### Production recommendation
- Do NOT expose PHP's built-in server directly on the Internet. Instead:
  - Use nginx (or Apache) as the public frontend on 80/443 and reverse-proxy requests to the app.
  - Prefer using `php-fpm` + unix socket (or php-fpm TCP) for production.

## 2. Clients preview server (dev only)
- **Purpose:** Serves all `clients/` sandboxes for preview during development.
- **Default (dev) port:** 8080
  - Command: `php -S 0.0.0.0:8080 -t clients clients/router.php` (see `bin/start_all.sh`)
- **Production:** typically not exposed publicly. Use the main app's `/clients/*` path
  proxied via nginx (if you want to serve these previews in production).

## 3. MCP Server (Model Context Protocol) — Optional
- **Purpose:** Advanced LLM tool call server and tool discovery/unification.
- **Default port:** 9010 (in `.env` as `MCP_SERVER_URL` or spawns by `bin/mcp_start.sh`)
- **Entrypoint:** `tools/groq-mcp/server.py` (python-based MCP)

### Production recommendation
- Keep MCP internal where possible — do not expose 9010 publicly unless you
  explicitly need remote tool discovery. Point `MCP_SERVER_URL` at an internal
  address (e.g. `http://127.0.0.1:9010`), or proxy via nginx if you require
  remote access.

## 4. WebSocket STT/TTS Proxy (Groq) — Optional
- **Purpose:** Handles streaming STT/TTS proxy to Groq's API and returns
  incremental results to the browser.
- **Default (dev) port:** 9011 (configurable via `GINTO_WS_PORT` or passed
  to `tools/groq-mcp/start_ws_stt.sh`)
- **Entrypoint / Runner:** `tools/groq-mcp/ws_stt_tts.py` (run via the helper
  `tools/groq-mcp/start_ws_stt.sh`)

### Production recommendation
- Proxy WebSocket connections via nginx on `80/443` to avoid exposing 9011
  directly. If the browser needs to access the WebSocket, configure nginx to
  upgrade the connection (see example nginx blocks below).
- **Purpose:** Provides realtime streaming endpoints for `/stream` and `/terminal` used by the UI.
- **Default port:** 31827 (see `bin/start_rachet_stream.php`, `bin/start_rachet_stream_fixed.php`)

### Composer install behavior note
- Composer no longer attempts to start the Ratchet server automatically during `composer install` or `composer update`.
  - The `post-install-cmd`/`post-update-cmd` now call `bin/composer_post.sh`, which only starts the Ratchet server when `GINTO_AUTO_START` is set to `1`. This prevents unintended background services during non-interactive installs (CI, deployment pipelines, etc.).
  - To enable auto-start during install, run `GINTO_AUTO_START=1 composer install`.
  - For interactive/dev workflows, prefer `bin/start_services.sh` to start all auxiliary services (clients, MCP, ratchet).

### Production recommendation
- As with other WebSocket servers, use nginx to proxy the WebSocket endpoint to
  `127.0.0.1:31827` and keep the internal high port closed to outside traffic.

## 6. Database (MySQL)
- **Default port:** 3306 (set via `.env` DB_PORT)
- **Production:** do not allow public access to the DB port; restrict to localhost
  or a private internal network. Use proper credentials, firewalls, and optional
  private networking.

## Port Summary Quick Table
| Service                | Default Port | Usage/Visibility                       |
|-----------------------:|:------------:|:--------------------------------------|
| HTTP(S) (nginx)        | 80 / 443     | Public (recommended front-end entry)  |
| Clients preview (dev)  | 8080         | Dev only; internal/proxied            |
| MCP Server (optional)  | 9010         | Internal; expose only if needed       |
| MySQL                  | 3306         | Internal only (do not expose)         |

## FAQ — Should I forward these ports?
- 80/443 — Yes, these are public and handled by nginx (recommended).
- 8000 — No, not directly. If you want the app publicly available, proxy
  through nginx (80/443) rather than opening 8000 directly.
- 8080 — No for production (dev preview server). Only open if you intentionally
  want to expose sandboxes for public access (not recommended).
- 9010 — Typically no; keep MCP internal. Open only if you must support remote MCP discovery.
- 9011 — Only if your client browser must connect directly; otherwise proxy with nginx.
- 31827 — Keep internal and use nginx for WebSockets unless there is a compelling reason to open it.

## Groq communication: what port & what to run
- Outbound communication to Groq uses HTTPS (`https://api.groq.com`) — direct
  outgoing traffic on TCP 443. The server side of Groq integrations are
  local services that speak to Groq (e.g. the Python `ws_stt_tts.py` or the
  `tools/groq-mcp/server.py`).
- To run Groq-facing components locally:
  - Start the Groq MCP python server (optional): `python tools/groq-mcp/server.py`
  - Start the WebSocket STT/TTS proxy: `tools/groq-mcp/start_ws_stt.sh 9011` (or pass a different port as arg/ENV)
  - Ensure `GROQ_API_KEY` is set in `.env` or the running environment
    (see `tools/groq-mcp/config.py` for helper tooling and `pyproject.toml`
    for Python dependencies).

## Example nginx configuration (HTTP + WebSocket proxy)
```
server {
  listen 80;
  server_name example.com;
  return 301 https://$host$request_uri;
}

server {
  listen 443 ssl;
  server_name example.com;
  ssl_certificate /etc/ssl/example.crt;
  ssl_certificate_key /etc/ssl/example.key;

  # Proxy the PHP app (dev fallback to built-in server)
  location / {
    proxy_pass http://127.0.0.1:8000;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
  }

  # Proxy Ratchet WebSockets
  location /stream/ {
      proxy_pass http://127.0.0.1:31827/;
      proxy_http_version 1.1;
      proxy_set_header Upgrade $http_upgrade;
      proxy_set_header Connection "Upgrade";
      proxy_set_header Host $host;
  }

  # Proxy Groq WS STT/TTS (if running)
  location /ws_stt/ {
      proxy_pass http://127.0.0.1:9010/;
      proxy_http_version 1.1;
      proxy_set_header Upgrade $http_upgrade;
      proxy_set_header Connection "Upgrade";
      proxy_set_header Host $host;
  }
}
```

## Dependencies for cloud/production
- PHP >= 7.4+ (8.x recommended)
  - Composer to install `composer.json` dependencies (`cboden/ratchet`, `php-mcp/*`, `guzzlehttp/guzzle`, `vlucas/phpdotenv`, etc.)
- MySQL (or compatible MySQL server)
- Python >= 3.11 for `tools/groq-mcp` components
  - Python packages are listed in `tools/groq-mcp/pyproject.toml` (notably: `mcp[cli]`, `fastapi`, `uvicorn`, `httpx`, `python-dotenv`, `pydantic`, `sounddevice`, `soundfile`).
- `ffmpeg` — audio transcoding used by the PHP transcribe endpoint
- Systemd (for `sandboxd` service and system socket units if used)

## Security notes and recommendations
- Never expose DB ports (3306) to the public internet. Restrict to local
  addresses or a private network.
- Prefer using nginx as your sole public entry point and proxy to internal
  service ports for HTTP and WebSocket traffic.
- For MCP and Groq-facing services, prefer internal-only and proxy via TLS
  when you must provide access.

---
References: search for ports and config in the repo (examples in `bin/`, `tools/groq-mcp/`, `bin/start_rachet_stream.php`, `.env`, and `composer.json`).
```

## Troubleshooting & useful start/stop commands
This section lists the scripts used to start/stop the main services (dev), where to find PID/log files, and handy commands to check status and connectivity.

### Helpful quick checks
- Verify services are listening on expected ports:
  ```bash
  sudo lsof -iTCP -sTCP:LISTEN -P -n
  ss -ltnp | grep -E "8000|8080|9010|9011|31827|3306"
  ```
- Check for a pid and whether the process is alive:
  ```bash
  [ -f /tmp/ginto-web.pid ] && echo "web pid: $(cat /tmp/ginto-web.pid)" && ps -p $(cat /tmp/ginto-web.pid)
  ```
- Tail logs to observe live errors:
  ```bash
  tail -f /tmp/ginto-web.log /tmp/ginto-clients.log ../storage/logs/ratchet.log tools/groq-mcp/logs/ginto-ws-stt.log 2>/dev/null
  ```

### Start / stop (dev environment)
- Start entire dev set (web + clients + auxiliary services):
  ```bash
  bin/start_all.sh
  ```
- Start web server only (built-in PHP server):
  ```bash
  bin/start_web.sh
  # or via composer
  composer serve
  ```
- Start auxiliary services (clients, ratchet, mcp stub):
  ```bash
  bin/start_services.sh
  ```
- Stop auxiliary services (clients, ratchet, mcp stub):
  ```bash
  bin/stop_services.sh
  ```
- Stop everything started by `start_all` (web):
  ```bash
  bin/stop_all.sh
  ```

### Specific service commands
- MCP (PHP stub) start/stop: (starts PHP stub on 9010)
  ```bash
  bin/mcp_start.sh
  bin/mcp_stop.sh
  ```
  
  Note: `bin/mcp_start.sh` will attempt to ensure `python3 >= 3.11` is
  available for running any Python-based MCP helpers (e.g. `tools/groq-mcp`).
  The script detects common package managers and will try to install Python
  and the `venv` package if missing (it requires `sudo` for installation).
  If you prefer not to have the script attempt installation, set
  `SKIP_PYTHON_INSTALL=1` in your environment before running it.
- Groq MCP Python server (optional) – start in its own venv:
  ```bash
  cd tools/groq-mcp
  ./start_ws_stt.sh 9011   # runs ws_stt_tts.py, sets GINTO_WS_PORT
  ./stop_ws_stt.sh         # stops ws stt service
  ```
  Logs: `tools/groq-mcp/logs/ginto-ws-stt.log`, PID: `tools/groq-mcp/ginto-ws-stt.pid`
- Ratchet WebSocket (stream/term) – start/stop (dev):
  ```bash
  php bin/start_rachet_stream.php &   # runs the Ratchet server on 31827
  # OR let bin/start_services.sh manage it
  bin/stop_services.sh                # stops the ratchet / clients services
  ```

### Troubleshooting checks to isolate issues
- If web UI is unreachable:
  - Check nginx is running (production): `sudo systemctl status nginx`
  - If you're using built-in server: check pid and logs (`/tmp/ginto-web.pid`, `/tmp/ginto-web.log`)
  - Check APP_URL in `.env` and environment variables are loaded
- If MCP discovery fails:
  - Confirm `MCP_SERVER_URL` is set in `.env` or your environment
  - Confirm `bin/mcp_start.sh` or the MCP process is running (check `/tmp/mcp_stub.pid`)
  - Confirm MCP server is reachable: `curl -s 'http://127.0.0.1:9010/mcp/discover'`
- If WebSocket transcribe or TTS isn't working:
  - Confirm `GROQ_API_KEY` is set
  - Confirm `tools/groq-mcp/ginto-ws-stt.pid` exists and `tools/groq-mcp/logs/ginto-ws-stt.log` shows startup
  - Test the WebSocket endpoint with `wscat` or `websocat`:
    ```bash
    npm i -g wscat
    wscat -c ws://127.0.0.1:9011/
    # or for TLS proxy via nginx: wss://example.com/ws_stt/
    ```
- If Ratchet streams are failing:
  - Check `../storage/logs/ratchet.log` for errors
  - Ensure Ratchet pid exists `/tmp/ginto-ratchet.pid` and process is alive
  - Test connecting to WebSocket route: `wscat -c ws://127.0.0.1:31827/stream` (path may vary)

### Quick network health checks
- Test the main app (HTTP): `curl -I http://127.0.0.1:8000/`
- Test the MCP endpoint: `curl -I http://127.0.0.1:9010/mcp/discover`
- Test the DB connects (if locally installed):
  ```bash
  mysql -h ${DB_HOST:-localhost} -P ${DB_PORT:-3306} -u ${DB_USER:-root} -p
  ```

## Caddy compatibility (Composer and WS/WSS)
Yes — Caddy (v2) is compatible with Composer and WebSockets (WS/WSS) when used as a reverse proxy.

- Composer: Composer is a PHP dependency manager and CLI tool — it runs locally
  to install dependencies into `vendor/` and doesn't depend on the web server. Caddy
  does not conflict with Composer; you can continue to use Composer to install
  vendors and manage runtime dependencies. For serving PHP code, you can either
  proxy to Apache (as above) or use `php-fpm` with Caddy's `php_fastcgi` directive.
- WebSockets (WS/WSS): Caddy v2's `reverse_proxy` fully supports WebSocket
  proxying and will handle the HTTP Upgrade handshake automatically. This means
  you can use Caddy for `wss://` endpoints with automatic TLS and reverse proxy
  to your backend WebSocket servers (e.g., Ratchet on 31827, WS STT/TTS on 9011).

### Example `Caddyfile` for Ginto (proxy to Apache 8090 + route websockets)
```
example.com {
  # Automatically obtain TLS certs for example.com
  reverse_proxy 127.0.0.1:8090

  # Route websockets to Ratchet stream
  handle_path /stream/* {
      reverse_proxy 127.0.0.1:31827
  }

  # Route Groq ws stt/tss to its backend
  handle_path /ws_stt/* {
      reverse_proxy 127.0.0.1:9011
  }
}
```

Notes and options:
- `php_fastcgi` — If you'd like to avoid Apache entirely, Caddy can pass PHP to `php-fpm` with `php_fastcgi`:
  ```
  example.com {
    php_fastcgi unix//run/php/php8.1-fpm.sock
  }
  ```

- `WS/WSS` Testing: use `wscat` to confirm proxy behavior:
  ```bash
  npm i -g wscat
  wscat -c wss://example.com/stream
  ```

- Security: Treat MCP endpoints as internal; do not expose them unless necessary. You can restrict routes with Caddy `remote_ip` matchers or access tokens.

If you'd like, I can add a `deploy/caddy/Caddyfile` with this sample and a small `README` for step-by-step instructions on deployment and testing.

## Server replacement helper
There's a helper script in `bin/replace_or_install_server.sh` which automates
installing/configuring Caddy for Ginto, optionally replacing existing Apache/
Nginx, and deploying a `ginto.service` systemd unit to run the app.

The updated script provides a clearer interactive experience and keeps
backwards-compatible non-interactive flags for automation. Key behavior:

- When run interactively, the script walks you through a short wizard:
  1) Select install type: `Local (dev)` vs `Live (deploy)`.
     - Local (dev): Caddy will be configured to serve HTTP only (port 80)
       with automatic HTTPS disabled (`{ auto_https off }` in the Caddyfile).
       Caddy will reverse proxy to the local app (default upstream port 8090)
       and a `localhost` site block is added for convenience.
     - Live (deploy): Caddy will be configured for HTTPS for the provided
       `DOMAIN` with optional `TLS_EMAIL` for ACME contact; site blocks will
       use `https://` and TLS will be enabled meaning Caddy will listen on 443.
  2) Optionally remove/mask existing web servers (apache2/httpd/nginx).
  3) Optionally free ports (80/443) interactively if another process is bound.
  4) Confirm a summary and write/validate the Caddyfile before reloading Caddy.
  5) Create and enable a `ginto.service` systemd unit and enable it.

Key features and safety checks:

- Dry-run support: `DRY_RUN=1` prints the steps and files it would change.
- Confirmations: the interactive flow shows a summary before applying changes.
- Caddy validation: the script validates the Caddyfile (via `caddy validate`) and
  restores a backup if the new configuration fails to validate.
- Minimal Caddyfile: if no Caddyfile exists on the system, the script writes a
  minimal dev Caddyfile so systemd Caddy won't fail on boot.
- Dev-mode safety: When in dev mode the script writes `{ auto_https off }` and
  the site blocks as `http://domain` and `localhost` (no TLS). It then reloads
  Caddy and verifies that it unbinds 443; if 443 is still bound the script offers
  corrective options (restart/stop/kill).
- Dev root-forwarding: In dev mode the script adds a Caddy rule to forward the
  root path `/` internally to the app's `/chat` endpoint on the configured
  `--upstream-port` (defaults to 8000). This ensures `http://localhost/` behaves
  consistently for local development and shows the Chat UI without requiring an
  external redirect.
- Start order: `ginto.service` is created to depend on `caddy.service` so the
  Ginto app starts after Caddy is up (`After=caddy.service` and `Wants=caddy.service`).
- Port verification: the script checks Caddy is actually serving on port 80
  when in dev mode before enabling `ginto.service`; if port 80 is unavailable
  it gives options to free it interactively using the `--force` flag.

Flags and options (examples):

- Interactive (default TTY):
  ```bash
  # Run the interactive installer (local/dev)
  sudo ./bin/replace_or_install_server.sh
  # Or choose live deploy with domain + TLS email
  sudo ./bin/replace_or_install_server.sh --mode deploy --domain example.com --tls-email admin@example.com
  ```

- Non-interactive examples (convenience flags):
  ```bash
  # Explicit local/dev non-interactive
  sudo ./bin/replace_or_install_server.sh --local --yes

  # Explicit live deploy with TLS (automated)
  sudo ./bin/replace_or_install_server.sh --live --domain example.com --tls-email admin@example.com --yes

  # Dry-run to preview changes
  DRY_RUN=1 sudo ./bin/replace_or_install_server.sh --local
  ```

- Short / advanced flags:
  - `--local` / `--live` — shortcuts for `--mode dev` / `--mode deploy`
  - `--force` — ask interactively to stop/kill processes bound to 80/443.
    - `--upstream-port` / `-p` — (optional) set the port Caddy reverse_proxies to on the local machine. Default: `8000` for `--local` / dev installations, `8090` for `--live` / deploy installs (if not explicitly set).
  - `--no-mask` — do not stop or mask existing Apache/Nginx services.
  - `--force-drop-443` — legacy flag kept for compatibility, equivalent to `--force` for 443.
  - `--user` / `-u` — set the non-root owner (for unit file and chown).
  - `--domain` / `-d` — domain to configure when `--live`.
  - `--tls-email` / `-e` — ACME contact email for TLS certs.
  - `--dry-run` — print operations instead of executing them.
  - `--yes` / `-y` — accept prompts (non-interactive).

Notes and recommendations:

- Use `DRY_RUN=1` and `--dry-run` to preview changes before applying.
- Use `--no-mask` if you want to keep existing Apache/Nginx running and
  avoid port conflicts; in this case Caddy may not be able to bind to 80/443.
- The script keeps the previous Caddyfile as a timestamped backup in
  `/etc/caddy/Caddyfile.bak.*` before overwriting — the helper also writes a
  commented header of the previous config at the start of the new file so
  admins can inspect the difference later.
- If domain/TLS cert provisioning is needed in `--live`, ensure DNS for the
  domain points to the server and port 443 is reachable from the internet.

Troubleshooting/caveats:

- If Caddy fails to start: inspect `systemctl status caddy` and
  `journalctl -xeu caddy.service` for details; the script validates and will
  revert to the previous Caddyfile on validation error when possible.
- If 80/443 ports are already used by another service, run with `--force` to
  interactively stop them or free the port. If you're automating, include
  `--yes` to accept prompts.

The script aims for a low-friction interactive flow for developer machines
(`--local`) while still supporting a safe, automated `--live` deploy flow for
production setup.

---
# Ginto Server Architecture

This document describes all major servers and services used in the Ginto project, their roles, ports, and how they interact.

---

## 1. PHP Application Server
- **Purpose:** Handles all main web endpoints, user authentication, chat, and speech-to-text (STT) via `/audio/stt` and `/transcribe`.
- **Default Port:** 8000 (built-in server: `php -S 0.0.0.0:8000 -t public`)
- **Entrypoint:** `public/index.php`
- **Environment:** Loads `.env` using `vlucas/phpdotenv` (all config in `$_ENV`).
- **Notes:**
  - No dependency on MCP server for basic chat or STT.
  - Restart after changing `.env`.

## 2. MCP Server (Model Context Protocol)
- **Purpose:** Handles advanced LLM tool calls, unified chat, and tool discovery.
- **Default Port:** 9010
- **Entrypoint:** `tools/groq-mcp/` (Python or Node.js, depending on implementation)
- **Notes:**
  - Only required for advanced tool-calling endpoints (e.g., `/mcp/chat`, `/mcp/call`).
  - Not required for basic chat or STT endpoints.

## 3. Python Realtime STT Server (Optional)
- **Purpose:** Provides advanced streaming speech-to-text (STT) with VAD and low-latency transcription.
- **Entrypoint:** `tools/groq-mcp/src/realtime_stt.py`
- **How to Enable:** Set `USE_PY_STT=1` in `.env`.
- **Notes:**
  - Used by PHP backend if enabled.
  - Requires Python 3 and dependencies (see `tools/groq-mcp/requirements.txt`).

## 4. WebSocket STT/TTS Proxy (Optional)
- **Purpose:** Local proxy for streaming STT and TTS (text-to-speech) via WebSocket.
- **Default Port:** 9011 (configurable)
- **Entrypoint:** `tools/groq-mcp/start_ws_stt.sh`
- **How to Start:**
  ```sh
  cd tools/groq-mcp
  ./start_ws_stt.sh 9011 &
  ```
- **Notes:**
  - Used for local dev/testing.
  - Started/stopped automatically by Composer scripts if present.

---

## Port Summary
| Service                | Default Port | Entrypoint/Script                        |
|------------------------|--------------|------------------------------------------|
| PHP App Server         | 8000         | public/index.php                         |
| MCP Server             | 9010         | tools/groq-mcp/                          |
| WebSocket STT/TTS      | 9011         | tools/groq-mcp/start_ws_stt.sh           |

---

## Environment Variables
- All servers use `.env` for configuration where possible.
- Always restart the relevant server after changing `.env`.

---

## See Also
- `README.md` (project overview)
- `docs/` for more architecture and usage guides
- `tools/groq-mcp/README.md` for MCP and STT/TTS details
