# ImageGen Tunnel Relay (`/tunnel`) — Approval, Sync, and Server/Client Flow

This document explains how Ginto’s ImageGen tunnel relay works, with emphasis on approving/disapproving the relay from the admin panel and how server/client state stays in sync.

Admin UI entry point:

- https://silverqueen.pro/admin/hosting/tunnels

## Why this exists

Image generation can run locally on CPU (`127.0.0.1:8888`) or through the public relay endpoint (`https://vision.silverqueen.pro`) when tunnel mode is enabled. The `/tunnel` path acts as a controlled gateway that can be explicitly approved or revoked.

## Routing and endpoints

### Public and API routes

- `/tunnel` and `/tunnel/{path}` are handled by `TunnelController@relayVision` and `TunnelController@relayVisionPath` in [src/Routes/web.php](src/Routes/web.php).
- Approval check endpoint: `/api/tunnel/relay/approval` → `TunnelController@relayApproval` in [src/Routes/web.php](src/Routes/web.php).
- Admin control plane routes are in [src/Routes/admin_controller_routes.php](src/Routes/admin_controller_routes.php):
  - `/admin/hosting/tunnels`
  - `/admin/hosting/tunnels/api`
  - `/admin/hosting/tunnels/relay/approve` (POST)
  - `/admin/hosting/tunnels/relay/revoke` (POST)

### Key controllers

- Relay proxy and approval enforcement: [src/Controllers/TunnelController.php](src/Controllers/TunnelController.php)
- Admin tunnel orchestration: [src/Controllers/HostingController.php](src/Controllers/HostingController.php)

## Approve / Disapprove flow (required operational control)

### 1) Approve relay

From the admin page, clicking **Approve** calls:

- `POST /admin/hosting/tunnels/relay/approve`

Server behavior in `HostingController::tunnelsRelayApprove()`:

1. Validates admin + CSRF.
2. Sanitizes subdomain (`vision` by default), validates approval duration (minutes or never-expire).
3. Starts a dedicated relay FRP client process (`provisionRelayProxy`) bound to `TUNNEL_RELAY_LOCAL_PORT`.
4. Ensures Caddy domain config for `vision.silverqueen.pro` (`provisionRelayDomainConfig`).
5. Ensures DNS zone/records (`autoCreateDnsForDomain`).
6. Writes approval into registry and removes blocklist entry.

### 2) Revoke relay (disapprove)

From the admin page, clicking **Revoke** calls:

- `POST /admin/hosting/tunnels/relay/revoke`

Server behavior in `HostingController::tunnelsRelayRevoke()`:

1. Validates admin + CSRF.
2. Removes `vision` from tunnel registry.
3. Adds `vision` to tunnel blocklist.
4. Stops relay FRP process (`stopRelayProxyProcess`).

Result: external `/tunnel` access is denied until approval is restored.

## Server/client sync model

### State files used for synchronization

- Registry: `/var/lib/ginto/tunnel-registry.json` (fallback `/tmp/ginto-tunnel-registry.json`)
- Blocklist: `/var/lib/ginto/tunnel-blocklist.json` (fallback `/tmp/ginto-tunnel-blocklist.json`)
- Relay approval checks telemetry: `/var/lib/ginto/tunnel-relay-checks.json`

### How sync works

1. Admin action updates registry/blocklist through `HostingController`.
2. Relay approval API (`/api/tunnel/relay/approval`) reads local registry/blocklist and returns `approved`, `blocked`, `expires_at`, and `remaining`.
3. `/tunnel` relay requests call `isVisionRelayApprovedRemote()` before proxying externally.
4. If approval endpoint is unavailable, logic falls back to local approval state to avoid hard failure in split environments.
5. Every approval check is recorded (IP, host, timestamp, count) for observability on the admin page.

This gives eventual consistency between control-plane actions and runtime relay behavior, without requiring a long-lived session channel.

## `/tunnel` request path behavior

Implemented in `TunnelController::proxyVisionRequest()`:

- Local requests (`localhost/127.0.0.1`) proxy directly to `127.0.0.1:TUNNEL_RELAY_LOCAL_PORT`.
- Non-local requests must pass relay approval checks.
- If approved, request is proxied to `https://vision.silverqueen.pro`.
- If not approved, request is rejected with HTTP 403 and an admin guidance message.

The relay preserves request method/body and forwards headers with `X-Forwarded-*` plus `X-Ginto-Tunnel-Relay`.

## ImageGen integration (highly customizable behavior)

Image generation routing chooses CPU/local or GPU/relay based on runtime settings:

- `IMAGEGEN_COMPUTE_MODE` (`auto|cpu|gpu`)
- `SDCPU_ACTIVE`
- `SDCPU_TUNNEL`

Code paths:

- [src/Handlers/ImageGenHandler.php](src/Handlers/ImageGenHandler.php) (`resolveSdcpuApiUrl()`)
- [src/Handlers/ChatStreamHandler.php](src/Handlers/ChatStreamHandler.php)

When relay mode is selected, requests target:

- `https://vision.silverqueen.pro/api/generate`
- `https://vision.silverqueen.pro/api/generate-stream`

The `/live` settings page exposes customization via env-backed controls:

- Profile (`IMAGEGEN_PROFILE`)
- Compute mode (`IMAGEGEN_COMPUTE_MODE`)
- Steps / guidance / width / height overrides
- Model ID override (`IMAGEGEN_MODEL_ID`)

These values are validated and persisted in [src/Controllers/LiveController.php](src/Controllers/LiveController.php), and rendered in [src/Views/live/settings.php](src/Views/live/settings.php).

## Serverless technology in action (control-plane pattern)

This relay is implemented with a serverless-style control plane:

- Approval is represented as lightweight state (JSON registry/blocklist) instead of a tightly coupled persistent tunnel session store.
- Runtime relay checks are stateless HTTP reads (`/api/tunnel/relay/approval`) before forwarding traffic.
- The admin console at `https://silverqueen.pro/admin/hosting/tunnels` acts as the control surface for provisioning/revocation while clients only need the public relay endpoint.

In short: control-plane decisions are centralized; data-plane proxying remains simple and stateless per request.

## Operations quick runbook

1. Open `https://silverqueen.pro/admin/hosting/tunnels`.
2. Verify relay section shows `vision.silverqueen.pro` and local relay port.
3. Click **Approve** and choose duration.
4. Confirm:
   - Relay status = Approved
   - FRP process running
   - Caddy config paths present
   - DNS zone created
5. Test ImageGen with compute mode `gpu` or `auto + SDCPU_TUNNEL=true`.
6. Click **Revoke** to immediately disable external relay access.

## Notes

- Approval is required for external relay traffic; local relay access can still be used for internal paths.
- Admin page telemetry (`last_check_at`, `last_check_ip`, count) helps verify whether clients are actively checking relay approval.