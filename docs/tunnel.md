# Ginto Tunnel

A SirTunnel-inspired reverse tunnel service that allows users to expose their local OpenWebUI (or other services) to the internet via subdomains on ginto.ai.

## Tunnel Options

Ginto offers two tunnel implementations:

| Feature | FRP Tunnel (Recommended) | WebSocket Tunnel |
|---------|--------------------------|------------------|
| Performance | ⚡ High (native Go) | Medium |
| Protocol Support | HTTP/HTTPS/TCP/UDP | HTTP only |
| Setup | Download frpc binary | Uses websocat |
| Dashboard | Yes (built-in) | No |
| Load Balancing | Yes | No |
| P2P Mode | Yes | No |
| Documentation | [tools/tunnel/frp/README.md](../tools/tunnel/frp/README.md) | Below |

**For most users, we recommend the FRP tunnel** - see [FRP Tunnel Documentation](../tools/tunnel/frp/README.md).

## ImageGen Relay (`/tunnel`)

For the ImageGen-specific relay control plane (approve/revoke, server/client sync, and `vision.ginto.ai` behavior), see:

- [ImageGen Tunnel Relay Documentation](./tunnel-imagegen.md)

---

## WebSocket Tunnel (Legacy)

## Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                          GINTO TUNNEL                               │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  [Local Machine]                        [ginto.ai Server]           │
│  ┌──────────────┐                      ┌──────────────────────────┐ │
│  │ OpenWebUI    │                      │ Tunnel Server (ws:8765)  │ │
│  │ :8088        │◄───────────────────► │ ┌──────────────────────┐ │ │
│  └──────────────┘   WebSocket Tunnel   │ │ Caddy Reverse Proxy  │ │ │
│        │                               │ │ xyz.ginto.ai:443     │ │ │
│        │                               │ └──────────────────────┘ │ │
│  ┌──────────────┐                      └──────────────────────────┘ │
│  │ expose.sh    │                                  ▲                │
│  │ client       │──────────────────────────────────┘                │
│  └──────────────┘                                                   │
│                                                                     │
│  [Internet User]                                                    │
│  Browser: https://xyz.ginto.ai ──────────────────► Caddy ► Tunnel   │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

## Features

- **WebSocket-based**: Reliable bidirectional communication
- **JWT Authentication**: Secure token-based session management
- **Dynamic DNS**: Automatic subdomain creation via PowerDNS
- **Auto Caddy Config**: Reverse proxy configuration on-the-fly
- **Expiring Sessions**: 10-minute limit for unregistered users
- **Clean Shutdown**: Automatic cleanup of DNS and Caddy configs

## Components

### 1. Tunnel Server (`tools/tunnel/tunnel_server.php`)

The WebSocket server that manages tunnel connections:

- Listens on `ws://0.0.0.0:8765`
- Generates JWT tokens for authentication
- Creates DNS records for subdomains
- Configures Caddy reverse proxy
- Handles HTTP request/response proxying
- Cleans up expired tunnels

### 2. Client Script (`bin/expose.sh`)

Bash script to establish tunnel from local machine:

```bash
# Basic usage
./expose.sh myapp 8088

# Custom server
./expose.sh myapp 3000 custom-server.ginto.ai
```

### 3. API Controller (`src/Controllers/TunnelController.php`)

REST API endpoints for tunnel management:

- `POST /api/tunnel/request` - Request new tunnel subdomain
- `GET /api/tunnel/status` - Check tunnel status

### 4. UI Integration (`scripts-openwebui.php`)

"Expose to Web" button in Docker mode:

- Shows when OpenWebUI is running in Docker backend
- Opens modal for subdomain selection
- Displays connection instructions

## Installation

### Server Setup

1. Install dependencies:
```bash
composer require cboden/ratchet react/react firebase/php-jwt
```

2. Install systemd service:
```bash
sudo cp deploy/ginto-tunnel.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable ginto-tunnel
sudo systemctl start ginto-tunnel
```

3. Open firewall port:
```bash
sudo ufw allow 8765/tcp
```

### Client Setup

Just download and run expose.sh:

```bash
curl -O https://ginto.ai/bin/expose.sh
chmod +x expose.sh
./expose.sh myapp 8088
```

## Usage

### From the UI

1. Start OpenWebUI in Docker mode
2. Click "🌐 Expose to Web" button
3. Enter desired subdomain (e.g., `my-owui`)
4. Copy the terminal command shown
5. Run the command on your local machine
6. Access via `https://my-owui.ginto.ai`

### From Command Line

```bash
# Expose local port 8088 as myapp.ginto.ai
./expose.sh myapp 8088

# Output:
# [TUNNEL] Connecting to wss://ginto.ai:8765...
# [TUNNEL] ✓ Connected! Your service is live at:
# [TUNNEL] https://myapp.ginto.ai
# [TUNNEL] Press Ctrl+C to disconnect
```

## API Reference

### POST /api/tunnel/request

Request a new tunnel subdomain.

**Request:**
```json
{
  "subdomain": "my-openwebui"
}
```

**Response (success):**
```json
{
  "success": true,
  "url": "https://my-openwebui.ginto.ai",
  "subdomain": "my-openwebui",
  "token": "eyJ0eXAi...",
  "expires_in": 600,
  "ws_endpoint": "wss://ginto.ai:8765"
}
```

**Response (error):**
```json
{
  "success": false,
  "error": "Subdomain already in use"
}
```

### GET /api/tunnel/status

Check if user has an active tunnel.

**Response:**
```json
{
  "active": true,
  "subdomain": "my-openwebui",
  "url": "https://my-openwebui.ginto.ai",
  "created_at": 1735123456,
  "expires_at": 1735124056
}
```

## Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `TUNNEL_PORT` | 8765 | WebSocket server port |
| `TUNNEL_HOST` | 0.0.0.0 | WebSocket bind address |
| `TUNNEL_JWT_SECRET` | (random) | JWT signing secret |
| `TUNNEL_EXPIRY` | 600 | Session expiry (seconds) |
| `TUNNEL_DNS_TTL` | 60 | DNS record TTL |

### Caddy Template

Tunnel creates Caddy configs like:

```caddyfile
# /etc/caddy/sites-enabled/tunnel-myapp.ginto.ai
myapp.ginto.ai {
    tls {
        on_demand
    }
  reverse_proxy 127.0.0.1:8765 {
        header_up X-Tunnel-Subdomain myapp
    }
}
```

## Security

### Rate Limiting

- 5 tunnel requests per user per hour
- 1 concurrent tunnel per unregistered user
- 10 concurrent tunnels per registered user

### Subdomain Validation

- 3-32 characters
- Lowercase letters, numbers, hyphens
- Cannot start/end with hyphen
- Reserved words blocked (api, www, admin, etc.)

### Token Security

- JWT tokens signed with server secret
- Tokens include expiry timestamp
- Tokens bound to subdomain

## Troubleshooting

### "Connection refused"

Ensure tunnel server is running:
```bash
sudo systemctl status ginto-tunnel
```

### "Subdomain already in use"

Choose a different subdomain or wait for existing tunnel to expire.

### "Token expired"

Tunnel sessions last 10 minutes for unregistered users. Register at ginto.ai for persistent tunnels.

### "DNS not resolving"

Wait 1-2 minutes for DNS propagation, or check PowerDNS:
```bash
dig +short myapp.ginto.ai
```

## Limitations

- HTTP/HTTPS only (no raw TCP)
- Maximum payload: 16MB per request
- WebSocket connections not tunneled (HTTP only)
- IPv4 only for now

## Roadmap

- [ ] WebSocket passthrough
- [ ] TCP tunneling
- [ ] Custom domain support
- [ ] Connection metrics dashboard
- [ ] Geographic server selection
