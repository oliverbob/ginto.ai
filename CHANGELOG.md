# Changelog

All notable changes to Ginto will be documented in this file.

## [Unreleased]

### Changed

- **IP Routing**: Replaced Redis-backed IP lookups with bijective Feistel network permutation
  - ~100-500x faster routing (1μs vs 100-500μs per request)
  - Zero network I/O for IP resolution
  - No Redis dependency for routing (Redis still optional for agent communication)
  - Collision-free: mathematically guaranteed unique IPs per sandbox
  - See [docs/sandbox.md](docs/sandbox.md) for technical details

### Removed

- Redis is no longer required for sandbox IP routing
- Removed `ginto:sandbox:` Redis key prefix (replaced with `agent:` for optional agent features)
