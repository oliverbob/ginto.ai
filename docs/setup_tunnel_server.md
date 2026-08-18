# Tunnel Server Setup

One-shot script to install and configure the FRP server (`frps`) on a VPS so
that gntl clients can expose local ports as `*.silverqueen.pro` subdomains.

## Quick start

```bash
# On the VPS, as root:
sudo ./bin/setup_tunnel_server.sh

# Or with explicit options:
sudo ./bin/setup_tunnel_server.sh --user oliverbob --domain silverqueen.pro
```

## What it does

| Step | Detail |
|------|--------|
| Installs `frps` binary | Downloads frp to `/opt/frp/frps`, symlinks to `/usr/local/bin` |
| Creates `/etc/frp/frps.toml` | Binds port 7000, HTTP vhost on 7080, dashboard on 7500 |
| Creates `/etc/frp/frps.env` | Generates random `FRP_AUTH_TOKEN` + `FRP_DASHBOARD_PWD` |
| Creates `ginto-frps.service` | Systemd unit running as the target user (default `oliverbob`) |
| Writes `sites-enabled/tunnels.caddy` | Caddy wildcard block: `*.silverqueen.pro → 127.0.0.1:7080` |
| Patches Caddyfile | Adds `on_demand_tls` + `import sites-enabled/*` if missing |
| Creates `/var/www/frp/404.html` | Custom "Tunnel Not Found" page |
| Starts + enables frps | Systemd daemon-reload, enable, restart |
| Reloads Caddy | Picks up the new sites-enabled config |

## Flags

| Flag | Default | Description |
|------|---------|-------------|
| `--user=<name>` | `oliverbob` | OS user that runs frps and owns config/logs |
| `--domain=<domain>` | `silverqueen.pro` | Base domain for subdomains |
| `--email=<email>` | `admin@<domain>` | TLS email for Caddy on-demand certs |
| `--frp-version=<ver>` | `0.67.0` | frp release to download |
| `--force` | off | Overwrite existing config, env, and 404 page |

## Idempotency

The script is safe to run multiple times:

- **Binary**: Skipped if already installed (unless `--force`)
- **Config/env**: Skipped if files already exist (unless `--force`)
- **Systemd service**: Always overwritten (safe)
- **Caddy config**: Always overwritten (safe)
- **Directories + permissions**: Always applied

## Running from gintoai.sh

The script is designed to be called standalone **or** from the `gintoai.sh`
installer. To integrate:

```bash
# Inside gintoai.sh, in the install_tunnel_server step:
"$SCRIPT_DIR/setup_tunnel_server.sh" \
    --user="$INSTALL_USER" \
    --domain="$CADDY_DOMAIN" \
    --email="$CADDY_TLS_EMAIL"
```

## Port reference

| Port | Protocol | Purpose |
|------|----------|---------|
| 7000 | TCP | frpc clients connect here |
| 7080 | HTTP | Caddy proxies `*.domain` here (HTTP vhost) |
| 7443 | HTTPS | HTTPS vhost (unused — Caddy terminates TLS) |
| 7500 | HTTP | Dashboard (localhost only) |

## File layout on the VPS

```
/etc/frp/
  frps.toml          # frps configuration
  frps.env           # FRP_AUTH_TOKEN + FRP_DASHBOARD_PWD (mode 600)

/opt/frp/
  frps               # server binary
  frpc               # client binary (for testing)

/etc/caddy/
  Caddyfile          # main config (with on_demand_tls + import)
  sites-enabled/
    tunnels.caddy    # *.domain wildcard block

/var/log/frp/
  frps.log           # frps log file

/var/www/frp/
  404.html           # custom "Tunnel Not Found" page

/etc/systemd/system/
  ginto-frps.service # systemd unit (User=oliverbob)
```

## Verifying it works

```bash
# 1. Check frps is running
systemctl status ginto-frps

# 2. Check ports
ss -tlnp | grep -E ':(7000|7080|7443|7500)\b'

# 3. Test the verify endpoint (used by Caddy on_demand_tls)
curl -s -w '\nHTTP %{http_code}\n' \
  'http://127.0.0.1:8000/api/tunnel/verify?domain=test.silverqueen.pro'

# 4. Test the bind endpoint (should return 401 for invalid key, not 503)
curl -s -w '\nHTTP %{http_code}\n' -X POST \
  http://127.0.0.1:8000/api/tunnel/bind \
  -H 'Content-Type: application/json' \
  -d '{"key":"invalid","local_port":2026,"client":"test"}'

# 5. Check dashboard (from VPS only)
set -a; . /etc/frp/frps.env; set +a
curl -s -u "admin:$FRP_DASHBOARD_PWD" http://127.0.0.1:7500/api/proxy/http | jq .
```

## Troubleshooting

**frps won't start**
```bash
journalctl -u ginto-frps -n 20
# Common: port already in use — kill the old process first
```

**Verify endpoint returns "Invalid domain format"**
```bash
# The PHP app regex must accept your base domain. Check TunnelController.php
# lines verifyTunnel() and tunnelAuthz() — both must match *.silverqueen.pro.
```

**Caddy returns 502 for subdomains**
```bash
# Check that sites-enabled/tunnels.caddy exists and forwards to 127.0.0.1:7080
cat /etc/caddy/sites-enabled/tunnels.caddy
caddy validate --config /etc/caddy/Caddyfile
systemctl reload caddy
```

**PHP app returns 503 "Tunnel server credentials unavailable"**
```bash
# The PHP process (running as the web user) cannot read /etc/frp/frps.env
ls -la /etc/frp/frps.env    # must be readable by the PHP user
# Re-run the script with --force to fix ownership
```
