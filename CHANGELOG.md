# Changelog

All notable changes to Ginto will be documented in this file.

## [1.0.3] - 2026-01-06

### Added

- **Orphaned Image Detection** in LXC Manager
  - Images now show status indicators (red = orphaned/safe to delete, green = in use, amber = template)
  - Image detail view displays status badges and explanatory text
  - API returns `is_orphaned` and `in_use` flags for each image

- **One-Click Domain Assignment** for containers
  - New `/admin/hosting/domains/quick-assign` API endpoint
  - Styled modal replaces browser `prompt()` dialog
  - Async API call without page redirect
  - Auto-creates Caddy config, DNS zone, and A records in single operation

- **Container Owner Display** in LXC Manager
  - Added Owner column showing username and full name
  - Properly extracts sandbox ID from container names (`ginto-sandbox-{id}`)

### Fixed

- **Container Proxy 403 Error** - `/root` directory permissions set to 755 during sandbox creation
  - Caddy runs as `caddy` user and needs read access to web roots under `/root`
  - Applied fix to both `LxdSandboxManager.php` and `ginto.sh`

---

## [1.0.2] - 2026-01-05

### 🚀 Major Architecture Change

**Docker is now for sandboxes only** - The main application stack (PHP, MariaDB, Caddy, Redis) now runs directly on the host system. Docker is used exclusively for user sandbox containers.

This provides:
- Better performance (main app runs natively)
- Strong isolation (user code runs in Docker containers)
- Unified experience across LXC/LXD/bare-metal deployments
- `ginto.service` systemd unit works universally

### Added

- **Lightpanda Web Tools in Chat** - `web_fetch`, `web_search`, `web_extract_links` now available as agent tools
  - Real-time activity streaming with status indicators (Reading URL, Searching, etc.)
  - Model instructed to use Lightpanda instead of curl for URL fetching
  - Collapsible activity timeline showing fetch/search progress

- **Server Hosting Control Panel** (`/admin/hosting`)
  - Virtualmin/CyberPanel-style management UI for bare-metal deployments
  - Dashboard with system stats (CPU, memory, disk, uptime)
  - Service management (start/stop/restart Caddy, PHP-FPM, MariaDB, Redis)

- **DNS Zone Management** 
  - Full zone editor with PowerDNS integration
  - Support for all record types: A, AAAA, CNAME, MX, TXT, NS, SRV, CAA, SOA
  - SOA defaults configuration
  - Automatic serial incrementation

- **Database Management**
  - Create/delete databases via admin UI
  - User provisioning with proper privileges

- **Firewall Management**
  - UFW rule management GUI
  - fail2ban status integration

- **SSL/TLS Dashboard**
  - Let's Encrypt certificate monitoring via Caddy auto-provisioning

- **Backup Management**
  - Manual backup creation with tar.gz
  - Backup listing and deletion

### Changed

- **docker-compose.yml**: Now contains ONLY sandbox services
  - Removed: php, mariadb, caddy, redis containers
  - Added: sandbox-proxy, terminal-server, sandbox-manager

- **Installation Script** (`gintoai.sh`):
  - Docker mode now installs full stack on host + Docker for sandboxes
  - Updated prompts to clarify architecture
  - Added `build_sandbox_images()` and `start_sandbox_services()` functions

- **Image Generation**: Now uses SDCPU (FastSD CPU with OpenVINO)
  - ~1 second generation on CPU, no GPU required
  - Replaced experimental LightPanda + Raphael AI approach

### Fixed

- Installation mode prompts now accurately describe the architecture
- README documentation updated to reflect new architecture

---

## [1.0.2] - 2026-01-02

### Added

- **Panda Search**: AI-powered web search with LightPanda browser engine
  - Real-time web search during chat conversations
  - Collapsible activity timeline showing search queries and sources
  - Smart source deduplication and summarization
  - Integration with all LLM providers

- **Image Generation** *(Testing)*: AI image generation via LightPanda + Raphael AI
  - `/imagegen` endpoint with streaming SSE events
  - Uses LightPanda browser to scrape Raphael AI website
  - No API keys required - web scraping approach
  - Test interface at GET `/imagegen`

- **User Message Actions**: Interactive buttons for user prompts
  - **Copy**: Copy prompt text to clipboard
  - **Edit**: Edit prompt inline with Save & Send
  - **Regenerate**: Re-run the same prompt for alternative response
  - Buttons appear on hover below the message bubble

- **Response Version Navigation**: Navigate between regenerated responses
  - Version indicator (e.g., "2/3") in response footer
  - Previous/Next buttons to browse response alternatives
  - Versions persist with conversation history

### Changed

- **Conversation UI**: Improved messenger-style chat interface
  - User prompts displayed as right-aligned blue bubbles
  - Cleaner action button placement (outside bubble, no empty footer)
  - Edit-in-place with inline textarea and Cancel/Save buttons
  - Better visual hierarchy for multi-turn conversations

### Fixed

- **Copy Button**: Now functional on user message bubbles
- **Edit Flow**: Edit-in-place replaces previous "edit to composer" behavior

---

## [1.0.1] - 2025-12-26

### Fixed

- **Sandbox Preview**: Fixed 502 Bad Gateway error when previewing files in the editor
  - The `/sandbox-preview/` route was trying to connect to a non-existent Node.js proxy on port 1800
  - Now proxies directly to the LXD container's web server on port 80, matching `/clients/` route behavior
  - Editor preview (eye icon) now works correctly

- **Network Dashboard Routes**: Fixed 404 error when changing network modes
  - Route path mismatch: JavaScript called `/admin/network/api/network/set` but route was defined as `/network/api/set`
  - Updated `admin_controller_routes.php` to use consistent route path `/network/api/network/set`

### Changed

- **NetworkController**: Enhanced LXD container management
  - Added `apiNetworkSet()` method for network mode switching (bridge, nat, macvlan, ipvlan)
  - Added unified cleanup for visitor sandboxes
  - Improved container resource usage display
  - Added fast network info lookup (avoids slow exec calls on page load)

- **LxdSandboxManager**: Enhanced sandbox lifecycle management
  - Added `deleteSandboxCompletely()` for atomic cleanup (container + DB + Redis + directory)
  - Improved `getSandboxIp()` with direct LXD IP lookup for bridge/nat modes
  - Better container running checks and auto-start functionality

- **Network Dashboard UI**: Improved admin network management interface
  - Network mode selector with bridge/nat/macvlan/ipvlan options
  - Real-time container status display
  - Bulk operations for containers

### Added

- **4 Network Modes**: Flexible container networking with NAT, Bridge, MACVLAN, and IPVLAN support
  - **NAT**: Default mode, containers share host IP with port forwarding
  - **Bridge**: Containers get IPs on a virtual bridge network
  - **MACVLAN**: Containers appear as physical devices on the LAN with unique MAC addresses
  - **IPVLAN**: Containers share host MAC but get unique IPs on the LAN (nested LXD compatible)

- **bin/setup_network.sh**: Network mode configuration script for LXD
  - Supports bridge, nat, macvlan, and ipvlan modes
  - Automatically creates required network infrastructure (dummy interfaces, shim networks)
  - Updates `.env` with selected network mode

- **Confirmation Modal**: Reusable modal component for delete confirmations
  - Added `src/Views/admin/parts/confirm-modal.php`

### Removed

- Cleaned up deprecated LXC view files:
  - Removed `src/Views/admin/lxc.php` (replaced by `admin/network/network.php`)
  - Removed `src/Views/admin/lxcs/lxc.php` and `lxcs.php` (consolidated)

---

## [1.0.0] - 2025-12-21

### Changed

- **IP Routing**: Replaced Redis-backed IP lookups with bijective Feistel network permutation
  - ~100-500x faster routing (1μs vs 100-500μs per request)
  - Zero network I/O for IP resolution
  - No Redis dependency for routing (Redis still optional for agent communication)
  - Collision-free: mathematically guaranteed unique IPs per sandbox
  - See [docs/sandbox.md](docs/sandbox.md) for technical details

#### Performance Comparison: Bijective (Feistel) vs Redis Lookup

| Metric | Bijective (Feistel) | Redis Lookup |
|--------|---------------------|--------------|
| **Latency** | ~1 μs (microsecond) | ~100-500 μs |
| **Network I/O** | None (pure CPU) | 1 round-trip |
| **Failure modes** | None | Redis down, connection timeout, memory full |
| **Scalability** | Infinite (stateless) | Limited by Redis connections |
| **Memory** | 0 bytes stored | ~100 bytes per key |

**Why the difference:**

```
Bijective:
  sandboxId → SHA256 → 4 XORs → IP
  Total: ~50 CPU instructions, no syscalls

Redis:
  sandboxId → serialize → TCP send → Redis parse → 
  B-tree lookup → serialize → TCP receive → deserialize
  Total: 2 syscalls + network stack + Redis overhead
```

**Real-world impact:**

| Scenario | Bijective | Redis |
|----------|-----------|-------|
| 1,000 req/sec | 1ms total compute | 100-500ms total |
| Redis down | ✅ Still works | ❌ All routing fails |
| Cold start | Instant | Wait for Redis connection |

The trade-off: Redis is more *flexible* (you can change mappings dynamically), but the Feistel approach is deterministic — same input always gives same output, so there's nothing to "look up." The mapping is mathematical, not stored.

### Removed

- Redis is no longer required for sandbox IP routing
- Removed `ginto:sandbox:` Redis key prefix (replaced with `agent:` for optional agent features)
