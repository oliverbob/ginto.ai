# Ginto FRP Tunnel

A fast, reliable tunnel service using [frp](https://github.com/fatedier/frp) (Fast Reverse Proxy) to expose local services to the internet via subdomains on silverqueen.pro.

## Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                        GINTO FRP TUNNEL                              │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  [Local Machine]                        [silverqueen.pro Server]            │
│  ┌──────────────┐                      ┌──────────────────────────┐ │
│  │ OpenWebUI    │                      │ frps (Server)            │ │
│  │ :8088        │◄───────────────────►│ ┌──────────────────────┐ │ │
│  └──────────────┘   TCP Connection     │ │ vhostHTTPPort :8080  │ │ │
│        ▲                               │ │ vhostHTTPSPort :8443 │ │ │
│        │                               │ └──────────────────────┘ │ │
│  ┌──────────────┐                      │           ▲              │ │
│  │ frpc         │                      │           │              │ │
│  │ (client)     │──────────────────────┼───────────┘              │ │
│  └──────────────┘                      │                          │ │
│                                        │ ┌──────────────────────┐ │ │
│                                        │ │ Caddy Reverse Proxy  │ │ │
│                                        │ │ *.silverqueen.pro:443       │ │ │
│                                        │ └──────────────────────┘ │ │
│                                        └──────────────────────────┘ │
│                                                    ▲                │
│  [Internet User]                                   │                │
│  Browser: https://xyz.silverqueen.pro ────────────────────┘                │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

## Features

- **High Performance**: Native Go implementation, much faster than WebSocket tunnels
- **Multiple Protocols**: HTTP, HTTPS, TCP, UDP, STCP (secret TCP)
- **Custom Subdomains**: Use `subdomain = "myapp"` for myapp.silverqueen.pro
- **TLS Encryption**: Secure communication between client and server
- **Token Authentication**: Secure access with shared token
- **Connection Pooling**: Faster response times
- **Dashboard**: Web UI to monitor tunnels
- **Health Checks**: Automatic proxy removal on failure

## Quick Start

### Client Setup (One-liner)

```bash
# Download and run the client helper script
curl -sSL https://silverqueen.pro/frp/install.sh | bash

# Expose local port 8088 as myapp.silverqueen.pro
ginto-frp expose myapp 8088
```

### Manual Client Setup

1. Download frpc for your platform from [frp releases](https://github.com/fatedier/frp/releases)

2. Create `frpc.toml`:
```toml
serverAddr = "silverqueen.pro"
serverPort = 7000
auth.token = "your-auth-token"

[[proxies]]
name = "my-openwebui"
type = "http"
localPort = 8088
subdomain = "myapp"
```

3. Run the client:
```bash
./frpc -c ./frpc.toml
```

4. Access your service at `https://myapp.silverqueen.pro`

## Server Configuration

The frps server runs on silverqueen.pro with the following configuration:

- **Bind Port**: 7000 (client connections)
- **vHost HTTP Port**: 8080 (HTTP vhost routing)
- **vHost HTTPS Port**: 8443 (HTTPS vhost routing)
- **Dashboard**: 7500 (admin interface)
- **Subdomain Host**: silverqueen.pro

## Authentication

### Token Authentication (Default)
Set `auth.token` in both frps.toml and frpc.toml to the same value.

### Per-User Tokens
Users can get their personal auth token from their silverqueen.pro dashboard:
1. Log in at https://silverqueen.pro
2. Go to Settings → API Tokens
3. Generate a "Tunnel" token
4. Use this token in your frpc.toml

## Proxy Types

### HTTP Proxy (Most Common)
```toml
[[proxies]]
name = "web"
type = "http"
localPort = 8088
subdomain = "myapp"
# Optional: rewrite Host header
hostHeaderRewrite = "localhost"
```

### HTTPS Proxy
```toml
[[proxies]]
name = "secure-web"
type = "https"
localPort = 443
subdomain = "secure"
```

### TCP Proxy
```toml
[[proxies]]
name = "ssh"
type = "tcp"
localPort = 22
remotePort = 6000  # Access via silverqueen.pro:6000
```

### Secret TCP (Private Access)
```toml
[[proxies]]
name = "private-service"
type = "stcp"
secretKey = "my-secret-key"
localIP = "127.0.0.1"
localPort = 3306
```

## Advanced Features

### Load Balancing
Multiple clients can register the same subdomain for load balancing:

```toml
[[proxies]]
name = "web-1"
type = "http"
localPort = 8088
subdomain = "myapp"
loadBalancer.group = "web"
loadBalancer.groupKey = "shared-secret"
```

### Health Checks
```toml
[[proxies]]
name = "web"
type = "http"
localPort = 8088
subdomain = "myapp"
healthCheck.type = "http"
healthCheck.path = "/health"
healthCheck.intervalSeconds = 10
```

### Bandwidth Limiting
```toml
[[proxies]]
name = "limited"
type = "http"
localPort = 8088
subdomain = "myapp"
transport.bandwidthLimit = "10MB"
```

## Comparison with WebSocket Tunnel

| Feature | FRP Tunnel | WebSocket Tunnel |
|---------|------------|------------------|
| Performance | ⚡ High | Medium |
| Protocol Support | HTTP/HTTPS/TCP/UDP | HTTP only |
| Setup Complexity | Medium | Easy |
| Binary Size | ~10MB | N/A (uses system tools) |
| Connection Reliability | Excellent | Good |
| Built-in Dashboard | Yes | No |
| P2P Support | Yes (xtcp) | No |

## Troubleshooting

### "Login failed" or "auth failed"
- Ensure `auth.token` matches between client and server
- Check if your token is valid in your silverqueen.pro dashboard

### "Subdomain already used"
- Choose a different subdomain
- Subdomains are unique across all users

### "Connection refused"
- Ensure frps is running: `systemctl status ginto-frps`
- Check if port 7000 is open

### Checking Tunnel Status
```bash
# On client
./frpc status -c ./frpc.toml

# Or visit the dashboard (if enabled)
http://127.0.0.1:7400
```

## Files

- `frps.toml` - Server configuration
- `frpc.toml` - Client configuration template
- `install_frps.sh` - Server installation script
- `ginto-frpc.sh` - Client helper script
- `ginto-frps.service` - Systemd service file

## Security Notes

1. Always use token authentication
2. Consider using TLS for sensitive data
3. Use STCP for private services
4. The dashboard password should be strong
5. Allowed ports are restricted on the server

## Links

- [frp GitHub Repository](https://github.com/fatedier/frp)
- [frp Full Documentation](https://github.com/fatedier/frp/blob/dev/README.md)
- [Silverqueen.pro](https://silverqueen.pro)
