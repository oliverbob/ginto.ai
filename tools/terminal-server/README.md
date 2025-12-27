Ginto terminal WebSocket PTY server (development)

This small server exposes a WebSocket endpoint that spawns a PTY and forwards input/output.
It can run a host shell (`/bin/bash`) or execute a shell inside a systemd-nspawn machine (`machinectl shell <machine> /bin/sh`) for sandboxed sessions.

Setup

1. Install Node.js (>= 18) and npm.
2. Install dependencies:

```bash
cd tools/terminal-server
npm install
```

Run

```bash
# default listens on 0.0.0.0:8081 but remote connections are disabled by default
GINTO_TERMINAL_PORT=8081 node server.js
# or allow remote (less secure):
GINTO_TERMINAL_ALLOW_REMOTE=1 GINTO_TERMINAL_PORT=8081 node server.js
```

Client

The playground console will connect to `ws://HOST:PORT/?mode=sandbox&container=<id>` for sandbox shells or `?mode=os` for host shells (admins only). The server does not authenticate requests — run on a trusted development host or add an authentication proxy.

Security

- This server is intended for local development only. Do not expose it to untrusted networks without proper authentication.
- The server only allows remote connections if `GINTO_TERMINAL_ALLOW_REMOTE=1` is set.
