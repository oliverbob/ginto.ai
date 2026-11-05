# Introducing OpenWebUI Integration

Ginto AI includes native [Open WebUI](https://github.com/open-webui/open-webui) integration with seamless iframe embedding.

## Overview

We're proud and excited to announce two breakthrough features that transform how you use Open WebUI:

1. **Seamless Iframe Embedding** - Open WebUI embedded directly in Ginto AI with same-origin subdomain architecture solving cross-origin storage issues
2. **Expose to Web** - One-click FRP tunneling to share your local OpenWebUI instance via public `*.silverqueen.pro` subdomains

Open WebUI is embedded as a first-class feature within Ginto AI, providing users with access to Open WebUI's interface directly from the Ginto AI dashboard without leaving the application.

### Key Features

- **One-click installation** - Open WebUI is automatically installed in the user's sandbox container
- **Same-origin subdomain** - Uses `oi.silverqueen.pro` to solve cross-origin storage issues
- **Universal iframe modal** - Minimize, maximize, fullscreen controls with tab management
- **Automatic SSL** - Caddy reverse proxy handles HTTPS and WebSocket connections
- **Shared authentication** - localStorage and JWT tokens work seamlessly

<p align="center">
  <img src="../public/assets/images/exposeweb.png" alt="Ginto AI Expose - Creating public tunnel for OpenWebUI" width="48%">
  <img src="../public/assets/images/exposedweb.png" alt="Ginto AI Exposed - Active tunnel with public URL" width="48%">
</p>

*Left: Creating a public tunnel to expose OpenWebUI to the internet via FRP. Right: Active tunnel with public subdomain URL for sharing.*

## Architecture

```
+-------------------------------------------------------------+
|                         silverqueen.pro                            |
|  +-------------------------------------------------------+  |
|  |                    Iframe Modal                       |  |
|  |  +-------------------------------------------------+  |  |
|  |  |               oi.silverqueen.pro                       |  |  |
|  |  |          (Open WebUI Instance)                  |  |  |
|  |  |                                                 |  |  |
|  |  |  +-------------------------------------------+  |  |  |
|  |  |  |      LXD/Docker Sandbox Container         |  |  |  |
|  |  |  |      - Open WebUI on port 8088            |  |  |  |
|  |  |  |      - Ollama connection                  |  |  |  |
|  |  |  +-------------------------------------------+  |  |  |
|  |  +-------------------------------------------------+  |  |
|  +-------------------------------------------------------+  |
+-------------------------------------------------------------+
```

## Same-Origin Solution

The key technical challenge with iframe embedding is browser security restrictions around third-party cookies and storage partitioning.

### The Problem

When embedding `external-domain.com` in an iframe on `silverqueen.pro`:
- Browser treats `external-domain.com` cookies/localStorage as third-party
- Modern browsers block or partition third-party storage
- Open WebUI's JWT authentication fails because tokens aren't accessible

### The Solution

By using a **subdomain of the parent domain**:

```
Parent:    silverqueen.pro
OpenWebUI: oi.silverqueen.pro  ← Same registrable domain
```

The browser treats `oi.silverqueen.pro` as same-site with `silverqueen.pro`, allowing:
- Shared localStorage
- Cookie access with `SameSite=Lax`
- Full JWT authentication functionality

## Installation Flow

When a user clicks "Open WebUI" in Ginto AI:

1. **Sandbox Check** - Verifies user has an active sandbox container
2. **Docker Installation** - Runs Open WebUI Docker container inside sandbox
   ```bash
   docker run -d --name open-webui \
     -p 8088:8080 \
     -v open-webui:/app/backend/data \
     ghcr.io/open-webui/open-webui:main
   ```
3. **DNS Registration** - Creates A record for `oi.silverqueen.pro` pointing to server
4. **Caddy Config** - Generates reverse proxy configuration:
   ```caddy
   oi.silverqueen.pro {
       reverse_proxy http://{sandbox_ip}:8088 {
           header_up Host {host}
           header_up X-Real-IP {remote_host}
           header_up X-Forwarded-For {remote_host}
           header_up X-Forwarded-Proto {scheme}
       }
       
       encode gzip
       
       @websockets {
           header Connection *Upgrade*
           header Upgrade websocket
       }
       reverse_proxy @websockets http://{sandbox_ip}:8088
   }
   ```
5. **Iframe Modal** - Opens Open WebUI in the universal iframe viewer

## API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/sandbox/openwebui/status` | GET | Check if Open WebUI is installed and running |
| `/api/sandbox/openwebui/install` | POST | Install Open WebUI in sandbox |
| `/api/sandbox/openwebui/start` | POST | Start Open WebUI container |
| `/api/sandbox/openwebui/stop` | POST | Stop Open WebUI container |
| `/api/sandbox/openwebui/register-domain` | POST | Register subdomain with Caddy |

### Status Response

```json
{
  "success": true,
  "installed": true,
  "running": true,
  "sandbox_exists": true,
  "sandbox_id": "abc123",
  "backend": "lxd",
  "url": "https://oi.silverqueen.pro/"
}
```

## Iframe Modal Features

The universal iframe modal provides:

- **Header Bar** - Title, URL, and control buttons
- **Refresh** - Reload the iframe content
- **Minimize** - Collapse to a small tab indicator
- **Maximize** - Expand to fill the viewport
- **Fullscreen** - Browser fullscreen mode
- **Open in New Tab** - Launch in separate browser tab
- **Expose to Web** - Create a public tunnel URL (see below)
- **Close** - Exit the modal

### Expose to Web (FRP Tunnel)

The "Expose to Web" feature allows you to share your Open WebUI instance with anyone via a public URL.

**How it works:**
1. Click the **Expose** button (globe icon) in the iframe modal toolbar
2. Choose a custom subdomain (3-32 chars, lowercase, alphanumeric + hyphens)
3. Click "Start Tunnel" to create a public URL like `myapp.silverqueen.pro`

**Features:**
- **Git-style subdomains** - Auto-generated 7-character hex subdomains (e.g., `4cc23ad.silverqueen.pro`)
- **Custom subdomains** - Choose your own subdomain name
- **10-minute expiry** - Free users get temporary tunnels; registered users get persistent access
- **FRP-powered** - Uses [fatedier/frp](https://github.com/fatedier/frp) for high-performance tunneling
- **Auto-SSL** - Caddy handles HTTPS certificates automatically via on-demand TLS

**Technical details:**
- Server: FRP server binds to port 7000, vhost HTTP on 7080
- Caddy wildcard: `*.silverqueen.pro` routes to FRP vhost port
- Client config stored in `~/.ginto-frp/frpc-{subdomain}.toml`
- Logs: `/tmp/frpc-{subdomain}.log`

See [CHANGELOG.md](../CHANGELOG.md) for version history and the FRP Tunnel feature (v1.0.6).

### Tab Management

Multiple iframe tabs can be open simultaneously:
- Minimized tabs stack vertically above the chat composer
- Circle indicators expand to pill shape on hover
- Click to restore, X to close
- State persists in localStorage

## Configuration

### Environment Variables

Open WebUI inside the sandbox can be configured via environment variables passed to the Docker container:

```bash
# Ollama connection (if using external Ollama)
OLLAMA_BASE_URL=http://host.docker.internal:11434

# Disable authentication (not recommended)
WEBUI_AUTH=false

# Custom data directory
DATA_DIR=/app/backend/data
```

### Caddy Configuration

The Caddy config is stored in `/etc/caddy/sites-available/oi.silverqueen.pro.caddy` for reference.

**Note:** The actual routing for `oi.silverqueen.pro` is handled in `/etc/caddy/sites-available/tunnels.caddy` as part of the wildcard `*.silverqueen.pro` configuration. The standalone config is kept in `sites-available` for backup/reference but is not enabled in `sites-enabled`.

## Troubleshooting

### Assets Not Loading

If images or assets don't load in the iframe:

1. **Check domain** - Ensure using same-origin subdomain (e.g., `oi.silverqueen.pro`)
2. **Clear cache** - Hard refresh with Ctrl+Shift+R
3. **Check Caddy** - Verify config is loaded: `systemctl status caddy`

### Authentication Issues

If login doesn't persist:

1. **Verify subdomain** - Must be subdomain of parent domain
2. **Check cookies** - Open DevTools → Application → Cookies
3. **localStorage** - Verify JWT token is stored

### Container Not Starting

```bash
# Check container status in sandbox
lxc exec {sandbox_name} -- docker ps -a

# View logs
lxc exec {sandbox_name} -- docker logs open-webui

# Restart container
lxc exec {sandbox_name} -- docker restart open-webui
```

## Related Documentation

- [Sandbox Environment](sandbox.md) - LXD/Docker container setup
- [LLM Providers](llm-providers.md) - Configuring AI backends
- [MCP Tools](mcp-tools.md) - Available agent tools
- [Tunnel Documentation](tunnel.md) - Detailed FRP tunnel setup and API
- [CHANGELOG](../CHANGELOG.md) - Version history and recent changes

## Changelog References

Key updates related to OpenWebUI integration:

- **v1.0.6** - FRP Tunnel "Expose to Web" feature with git-style subdomains
- **v1.0.5** - Iframe modal improvements, refresh button, same-origin domain switch to `oi.silverqueen.pro`

## Credits

Open WebUI is developed by the [Open WebUI team](https://github.com/open-webui/open-webui). Ginto AI's integration builds upon their excellent work to provide seamless embedding capabilities.
