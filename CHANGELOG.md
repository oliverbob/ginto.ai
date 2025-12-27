# Changelog

All notable changes to Ginto will be documented in this file.

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
