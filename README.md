# Minimal PHP 8.2 REST API (Tasks CRUD)

This repository demonstrates a **production‑ready** yet lightweight PHP API built with:

* **PHP 8.2** – typed properties, strict types, and modern syntax.
* **PDO** – safe prepared statements, error handling, and a singleton connection.
* **FastRoute** – a fast, PSR‑7‑compatible router (no heavy framework required).
* **Composer autoload** – PSR‑4 class loading.
* **JSON** request/response bodies.
* **.htaccess** rewrite for Apache (works the same on Nginx with a simple location block).

---
## Table of Contents

- [Project Structure](#project-structure)
- [Setup](#setup)
- [API Endpoints](#api-endpoints)
- [AI SDK Integration](#ai-sdk-integration)
- [Sandbox Security](#sandbox-security-architecture)
- [Configuration](#configuration)
- [License](#license)

---
## Project structure
```
├─ composer.json
├─ README.md
├─ sql/
│   └─ create_tasks_table.sql
├─ public/
│   ├─ .htaccess
│   └─ index.php          ← front‑controller (router entry point)
└─ src/
    ├─ Database.php       ← PDO singleton
    └─ TaskController.php ← CRUD actions
```
---

## Logs

Application logs are located at:
```
../storage/logs/ginto.log
```
(One level up from the project directory, at `/home/<user>/storage/logs/ginto.log`)

---

## Setup
1. **Clone & install dependencies**
   ```bash
   git clone <repo‑url>
   cd php-rest-api
   composer install
   ```
2. **Create the database** (adjust credentials in `src/Database.php` or set env vars `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).
   ```bash
   mysql -u root -p < sql/create_tasks_table.sql
   ```
3. **Configure your web server**
   *Apache* – point the document root to the `public/` folder. The provided `.htaccess` rewrites all requests to `index.php`.
   *Nginx* – use a location block similar to:
   ```nginx
   location / {
       try_files $uri /index.php?$query_string;
   }
   fastcgi_pass   php-fpm;
   include        fastcgi_params;
   fastcgi_param  SCRIPT_FILENAME $document_root/index.php;
   ```
4. **Run locally with PHP’s built‑in server** (great for quick testing):
   ```bash
   php -S localhost:8000 -t public
   ```
   The API will be reachable at `http://localhost:8000`.
---

## API endpoints
| Method | URI | Description | Example response |
|--------|-----|-------------|------------------|
| `GET` | `/tasks` | List all tasks | `[{"id":1,"title":"Buy milk","completed":0}, …]` |
| `GET` | `/tasks/{id}` | Get a single task | `{ "id":1, "title":"Buy milk", "completed":0 }` |
| `POST` | `/tasks` | Create a task (JSON body) | `201 Created` → `{ "id":2, "title":"Read book", "completed":false }` |
| `PUT` | `/tasks/{id}` | Update title/completed flag | `{ "message":"Task updated" }` |
| `DELETE` | `/tasks/{id}` | Delete a task | `{ "message":"Task deleted" }` |
---

## Security & best practices notes
* **Prepared statements** prevent SQL injection.
* **Strict typing** (`declare(strict_types=1)`) catches type errors early.
* **Error handling** – the controller returns proper HTTP status codes (400, 404, 500, etc.).
* **Environment variables** – keep DB credentials out of source control (use `.env` with `vlucas/phpdotenv` in a real project).
* **CORS** – add appropriate headers if the API is consumed from a browser.
* **Rate limiting / authentication** – not covered here but easy to plug in (e.g., JWT middleware).
---

## Testing the API (cURL examples)
```bash
# List tasks
curl -s http://localhost:8000/tasks | jq

# Create a task
curl -s -X POST http://localhost:8000/tasks \
     -H "Content-Type: application/json" \
     -d '{"title":"Write docs","completed":false}' | jq

# Update a task (id=1)
curl -s -X PUT http://localhost:8000/tasks/1 \
     -H "Content-Type: application/json" \
     -d '{"completed":true}' | jq

# Delete a task (id=2)
curl -s -X DELETE http://localhost:8000/tasks/2 | jq
```
---

---

## AI SDK Integration

Ginto includes a modular integration with the [Vercel AI SDK](https://sdk.vercel.ai/) for structured tool calling and agent orchestration. This replaces custom tool-calling logic with a battle-tested, well-maintained library.

### Quick Start

```bash
# Install dependencies
cd public/assets/js/ai-sdk
npm install

# Build for production
npm run build
```

### Basic Usage

```javascript
import { GintoAgent } from './ai-sdk/index.js';

const agent = new GintoAgent();

await agent.stream({
  prompt: 'List my files and create a hello.html',
  onText: (text) => console.log(text),
  onToolCall: (name, args) => console.log('Executing:', name),
  onFinish: (response) => console.log('Done!'),
});
```

### Features

| Feature | Description |
|---------|-------------|
| **Automatic Tool Loop** | `maxSteps` handles multi-step execution automatically |
| **Type-Safe Tools** | Zod schemas for all tool parameters |
| **Streaming** | Native SSE streaming support |
| **Provider Agnostic** | Works with OpenRouter, OpenAI, Anthropic, etc. |
| **Bridge Mode** | Integrate with existing chat.js without rewriting |

### Available Sandbox Tools

| Tool | Description |
|------|-------------|
| `sandbox_list_files` | List files and directories |
| `sandbox_read_file` | Read file contents |
| `sandbox_write_file` | Create or update files |
| `sandbox_delete_file` | Delete files/folders |
| `sandbox_exec` | Execute shell commands |
| `sandbox_create_project` | Create from template |

### Documentation

- [Full Integration Guide](docs/ai-sdk-integration.md)
- [AI SDK README](public/assets/js/ai-sdk/README.md)

---

## Sandbox Security Architecture

Ginto uses LXD containers with **Proxmox-style security hardening** to safely allow nesting (Docker/LXC inside containers) while protecting the host.

### Current Security Implementation (v1.0)

| Feature | Status | Implementation |
|---------|--------|----------------|
| **Unprivileged Containers** | ✅ | `security.privileged=false` |
| **UID Namespace Isolation** | ✅ | `security.idmap.isolated=true` |
| **Nesting Enabled** | ✅ | `security.nesting=true` with interception |
| **Mount Syscall Interception** | ✅ | Whitelist: `ext4,tmpfs,proc,sysfs,cgroup,overlay` |
| **Device Node Interception** | ✅ | `security.syscalls.intercept.mknod=true` |
| **Extended Attr Interception** | ✅ | `security.syscalls.intercept.setxattr=true` |
| **BPF Interception** | ✅ | `security.syscalls.intercept.bpf=true` |
| **Resource Limits** | ✅ | 2 CPU, 1GB RAM, 200 processes |
| **Kernel Module Loading** | ✅ Blocked | `linux.kernel_modules=""` |
| **Command Filtering** | ✅ | `SandboxSecurity.php` blocks dangerous commands |

### Security Comparison: Ginto vs Proxmox

| Feature | Proxmox | Ginto | Status |
|---------|---------|-------|--------|
| User Namespace Mapping | UID 100000+ | `idmap.isolated=true` | ✅ Equivalent |
| Unprivileged Default | ✅ | ✅ | ✅ Same |
| Syscall Interception | AppArmor + seccomp | LXD intercept | ✅ Same result |
| Mount Filtering | AppArmor whitelist | `mount.allowed=...` | ✅ Same |
| **AppArmor Profile** | Custom strict | LXD default | ⚠️ **Gap** |
| **Seccomp Profile** | Custom restrictive | LXD default | ⚠️ **Gap** |
| **Network Egress Filtering** | veth + iptables | Just lxdbr0 | ⚠️ **Gap** |
| Cgroup v2 Delegation | Controlled | LXD handles | ✅ Same |

**Current Security Rating: 85/100** (Production ready)

### Future Security Enhancements (Community Contributions Welcome!)

#### 1. Custom AppArmor Profile (Medium Priority)
Add stricter AppArmor confinement matching Proxmox:
```bash
# Example: Add to ginto.sh apply_security_config()
$LXC_CMD config set "$name" raw.apparmor="
  deny mount options=(ro, remount) -> /,
  deny /sys/firmware/** rwklx,
  deny /sys/kernel/** rwklx,
  deny /proc/sys/** wklx,
"
```

#### 2. Custom Seccomp Profile (Low Priority)
The syscall interception mostly covers this, but a custom profile adds defense-in-depth:
```bash
# Create /etc/lxd/seccomp/ginto.json with restricted syscalls
$LXC_CMD config set "$name" raw.seccomp="..."
```

#### 3. Network Egress Filtering (Medium Priority)
Restrict outbound connections to prevent abuse:
```bash
# Add to ginto.sh or as separate network hardening script
sudo iptables -I FORWARD -i lxdbr0 -o eth0 -j DROP
sudo iptables -I FORWARD -i lxdbr0 -o eth0 -p tcp --dport 80 -j ACCEPT
sudo iptables -I FORWARD -i lxdbr0 -o eth0 -p tcp --dport 443 -j ACCEPT
sudo iptables -I FORWARD -i lxdbr0 -o eth0 -p udp --dport 53 -j ACCEPT
```

#### 4. Per-Container Network Namespaces (Low Priority)
Isolate container networks to prevent cross-container attacks.

### Security Files Reference

| File | Purpose |
|------|---------|
| `bin/ginto.sh` | Container creation with security config |
| `src/Helpers/SandboxSecurity.php` | Command filtering and rate limiting |
| `src/Helpers/LxdSandboxManager.php` | PHP sandbox management |
| `/etc/sudoers.d/ginto-lxd` | Restricted sudo access for web server |

### Reporting Security Issues

If you discover a security vulnerability, please email **security@ginto.ai** instead of opening a public issue.

---

## TODO

### Sandbox Infrastructure

- [ ] **Multi-Distro LXC Support** - Extend `ginto.sh` to support additional Linux distributions as sandbox base images:
  - **Debian 12 (Bookworm)** - Stable, widely used, similar to Ubuntu
  - **Fedora** - For users preferring RPM-based systems
  - **Arch Linux** - Rolling release, latest packages
  - **Rocky Linux / AlmaLinux** - RHEL-compatible for enterprise users
  - **openSUSE Leap** - Enterprise-grade stability
  - **Void Linux** - Lightweight alternative to Alpine with glibc

- [ ] **Container Runtime Alternatives** - Support for other container technologies:
  - **Podman** - Rootless containers, Docker-compatible
  - **systemd-nspawn** - Lightweight, built into systemd
  - **Incus** - LXD fork with community governance

### Features

- [ ] One-click web installer at `https://ginto.ai/install.sh`
- [ ] Automatic SSL certificate provisioning per sandbox
- [ ] Resource usage dashboard
- [ ] Sandbox templates (Laravel, Next.js, Django, etc.)

---

## Firewall (UFW) Configuration

If your server uses UFW, LXD bridge traffic must be allowed for containers to get IP addresses.

**The `ginto.sh init` command automatically configures UFW** if it detects UFW is active. However, if you need to configure it manually:

```bash
# Allow LXD bridge traffic (required for container networking)
sudo ufw allow in on lxdbr0
sudo ufw allow out on lxdbr0
sudo ufw route allow in on lxdbr0
sudo ufw route allow out on lxdbr0
```

**Why is this needed?**
- LXD creates a bridge network (`lxdbr0`) with a built-in DHCP server
- UFW's default configuration blocks traffic on unknown interfaces
- Without these rules, containers cannot get IPv4 addresses via DHCP

**Troubleshooting:**
- If containers show only IPv6 (no IPv4), UFW is likely blocking DHCP
- Run `sudo ufw status` to check if rules for `lxdbr0` exist
- The bridge uses an internal subnet (e.g., `10.x.x.0/24`) not exposed to the internet

---

## License
MIT – feel free to copy, modify, and use in your own projects.
