# FRP on ginto.ai

There are **two independent frps servers** running on `149.28.145.52`. They are
not redundant copies of each other and they are not interchangeable. Changing
one to look like the other will break whatever depends on it.

## The two servers

| | Wildcard server | Dedicated server |
|---|---|---|
| Purpose | Public `*.ginto.ai` subdomain tunnels | Separate, purpose-built tunnelling |
| systemd unit | `ginto-frps.service` | `frps.service` |
| Binary | `/opt/frp/frps` | `/home/oliverbob/frp/frps` |
| Config | `/etc/frp/frps.toml` | `/home/oliverbob/frp/frps.toml` |
| Secrets | `/etc/frp/frps.env` (`FRP_AUTH_TOKEN`, `FRP_DASHBOARD_PWD`) | inline in its own config |
| Control port | `7000` | `7700` |
| HTTP vhost | `7080` | *none* |
| HTTPS vhost | `7443` | *none* |
| Dashboard | `127.0.0.1:7500` | *none* |
| Fronted by Caddy | yes | no |

The dedicated server on `7700` has **no vhost ports configured at all**, so it
cannot serve `http`/`https` subdomain proxies — it exists for its own use case
and must be left alone. Everything below concerns the wildcard server only.

## How a `*.ginto.ai` request is served

```
Browser  ──TLS──►  Caddy :443            (terminates TLS, on-demand cert)
                     │
                     │  plaintext HTTP, Host preserved
                     ▼
                   frps HTTP vhost :7080  (matches proxy by subdomain)
                     │
                     │  frpc control connection (TLS, token auth)
                     ▼
                   frpc on the user's machine
                     │
                     ▼
                   local app
```

Relevant pieces:

- `/etc/caddy/Caddyfile` — global `on_demand_tls { ask http://localhost:8000/api/tunnel/verify }`
- `/etc/caddy/sites-enabled/tunnels.caddy` — the `*.ginto.ai` block; forwards to `127.0.0.1:7080`
- `TunnelController::verifyTunnel()` — gates certificate issuance per subdomain
- `custom404Page = /var/www/frp/404.html` — the "Tunnel Not Found" page frps serves when no proxy matches

## The rule that matters for clients

**A proxy for `*.ginto.ai` must be `type = "http"`.**

Caddy terminates TLS at the edge and forwards plaintext to the frps **HTTP**
vhost on `:7080`. An `https`-type proxy is registered on the **HTTPS** vhost
`:7443`, which nothing routes to. The result is a tunnel that reports `online`
in the dashboard and in `/admin/hosting/tunnels`, while every request returns
the "Tunnel Not Found" 404 — because the two are looking at different vhosts.

Symptom checklist for that failure:

```bash
# on the server
curl -s -o /dev/null -w '%{http_code}\n' -H 'Host: sub.ginto.ai' http://127.0.0.1:7080/     # 404
curl -sk --resolve sub.ginto.ai:7443:127.0.0.1 -o /dev/null -w '%{http_code}\n' \
     https://sub.ginto.ai:7443/                                                             # 200/303
```

A 404 on `:7080` together with a real response on `:7443` means the client
registered the wrong proxy type.

### TLS-only local apps

`type = "http"` does not force the local hop to be plaintext. When the local
app only speaks TLS (for example the gntl web admin on `:2026`), the client
bridges it with frpc's `http2https` plugin:

```toml
[[proxies]]
name = "btc-https"
type = "http"                    # what frps needs
subdomain = "btc"
[proxies.plugin]
type = "http2https"
localAddr = "127.0.0.1:2026"     # local hop stays encrypted
hostHeaderRewrite = "127.0.0.1"
```

frps hands frpc plaintext (the edge already did the TLS work) and frpc re-wraps
it in TLS for the local app. Do not emit `localIP`/`localPort` next to a
`plugin` block — the plugin owns the local endpoint.

The gntl client implements this in `_frp_exposure_plan()` and
`render_frpc_config()`, keyed off `FRP_EDGE_TLS_HOSTS` (default `ginto.ai`) so
that pointing gntl at any other frps leaves its behaviour unchanged.

## Authorising a tunnel: `POST /api/tunnel/bind`

Keys minted at `/account/keys` are the credential a tunnel client uses to
authorise itself. No session, no CSRF (the caller is an frpc host, not a
browser), and no admin step.

```bash
curl -X POST https://ginto.ai/api/tunnel/bind \
     -H 'Content-Type: application/json' \
     -d '{"key":"gtnl-...","local_port":2026,"client":"my-host"}'
```

Returns the connection parameters the client needs — `server_addr`,
`server_port`, `frp_token`, and `proxy_type` (always `http`, per the rule
above) — and records the subdomain in `/var/lib/ginto/tunnel-registry.json`
against its owner and the key's expiry, which is what `verifyTunnel()` consults
when issuing a certificate.

Verification, in `verifyTunnelAccessKey()`:

1. `sha256(token)` must match a `tunnel_access_keys` row — that column is what
   revoke and expiry act on
2. the row must not be revoked or expired
3. when `APP_KEY` is set, the HS256 signature must verify and the claims
   (`sd`, `sub`, `jti`) must describe the same grant as the row

Malformed, unknown, revoked and expired keys all return the same generic 401,
so the endpoint cannot be used to enumerate subdomains.

### The operator-key binding

A client publishes its key as the `ginto_key` proxy meta. On a second bind call
— made once the tunnel is online — the server reads the meta off the live proxy
via the frps dashboard and, only if it matches, sets `access_key_enabled` in the
registry. From then on `verifyTunnel()` requires the online proxy to present
that key before issuing a certificate.

This is deliberately verified rather than trusted: enabling the requirement for
a proxy that does not publish the meta would block certificate issuance for that
subdomain, so a bind would cause an outage. If the meta later disappears, the
next bind clears the flag rather than leaving a stale requirement behind.

The frps token is read from `FRP_AUTH_TOKEN` in the app environment, falling
back to `/etc/frp/frps.env`.

## Authorisation is enforced per request

`*.ginto.ai` runs every request through Caddy `forward_auth` to
`/api/tunnel/authz` before it reaches frps. A subdomain serves only while the
online proxy publishes an account key that is valid, so revoking or deleting a
key at `/account/keys` stops the tunnel on the next request - no restart and no
cooperation from the client.

```
Browser ──► Caddy ──► forward_auth /api/tunnel/authz ──► 204 ──► frps :7080
                              │
                              └── 403 "tunnel not authorised"
```

The subrequest must carry `Host: ginto.ai` and pass the real hostname in
`X-Tunnel-Host`. The app routes by `Host`, so a subdomain Host makes it relay
the check into that very tunnel, which answers from the tunnelled app instead
of the gate.

Exempt, because they are not frp tunnels: subdomains with their own file in
`sites-enabled`, `virtual_hosts` records, and `owui-*`.

Two consequences worth knowing:

- being online is not authorisation. The frps token is shared by every client,
  so a connected proxy only proves someone holds that token.
- this puts the PHP app on the request path for every tunnel hit. Decisions are
  cached for a few seconds, but if the app is down, tunnels stop serving.

## Inspecting live state

```bash
# which proxies are registered, and on which vhost
set -a; . /etc/frp/frps.env; set +a
curl -s -u "admin:$FRP_DASHBOARD_PWD" http://127.0.0.1:7500/api/proxy/http  | jq .
curl -s -u "admin:$FRP_DASHBOARD_PWD" http://127.0.0.1:7500/api/proxy/https | jq .

# both servers, so you can tell them apart at a glance
ps aux | grep '[f]rps'
ss -ltnp | grep -E ':7000|:7080|:7443|:7500|:7700'
```

`/api/proxy/http` listing a subdomain is what makes it reachable through Caddy.
A subdomain that only appears under `/api/proxy/https` is the failure above.

## Changing Caddy

Always back up before editing, and reload rather than restart:

```bash
cp /etc/caddy/Caddyfile /etc/caddy/Caddyfile.bak.$(date +%Y%m%d_%H%M%S)
cp -a /etc/caddy/sites-enabled /etc/caddy/sites-enabled.bak.$(date +%Y%m%d_%H%M%S)
caddy validate --config /etc/caddy/Caddyfile
systemctl reload caddy
```
