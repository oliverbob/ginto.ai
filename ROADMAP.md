# Ginto.AI Roadmap

## Installation Modes (User Preference System)

The `./install.sh` script (internally `gintoai.sh`) will support multiple installation modes tailored to different user needs. Each mode balances **speed**, **performance**, and **features** differently.

---

### 🚀 Mode 1: `lite` — Lightning Fast Installation

**Command:** `./install.sh lite`

**For:** Users who want to experience Ginto.AI immediately with zero wait time.

**Philosophy:** Get the UI running in seconds. Everything else loads lazily in the background.

| Aspect | Behavior |
|--------|----------|
| **Speed** | ⚡⚡⚡⚡⚡ Fastest (< 30 seconds) |
| **Initial Features** | Core chat UI only |
| **Background Tasks** | Dependencies, migrations, optimizations run while you explore |
| **Models** | Cloud-only (Groq/Cerebras) — no local model downloads |
| **Sandbox** | Deferred — enabled on first use |

**How it works:**
1. Copies essential files
2. Starts PHP server immediately
3. Opens browser to `/chat`
4. Background daemon handles: composer install, migrations, npm build, cache warmup
5. Progress shown via subtle toast notifications

---

### 📦 Mode 2: `install` — Standard Installation (Default)

**Command:** `./install.sh` or `./install.sh install`

**For:** Users who want a complete, tested setup before using the system.

**Philosophy:** Everything installed and verified before first use. Slightly slower but fully functional from the start.

| Aspect | Behavior |
|--------|----------|
| **Speed** | ⚡⚡⚡ Moderate (2-5 minutes) |
| **Initial Features** | Full feature set |
| **Verification** | All components tested before completion |
| **Models** | Cloud APIs configured, optional local model prompt |
| **Sandbox** | Docker/LXD setup included |

**Steps:**
1. System dependency check
2. Composer install
3. Database migrations
4. Environment configuration wizard
5. Optional: Download default local model
6. Sandbox container setup
7. Final verification tests

---

### 🔧 Mode 3: `expert` — Bare Metal / Full Control

**Command:** `./install.sh expert`

**For:** Developers, sysadmins, and power users who want full control over every component.

**Philosophy:** No assumptions. Ask everything. Maximum customization.

| Aspect | Behavior |
|--------|----------|
| **Speed** | ⚡ Slowest (varies by choices) |
| **Control** | Full — every component optional |
| **Prompts** | Interactive for each major decision |
| **Sandbox Backend** | Choose: Docker / LXD / Podman / None |
| **Database** | Choose: SQLite / MySQL / PostgreSQL |
| **Web Server** | Choose: Built-in PHP / Caddy / Nginx / Apache |

**Expert Options:**
- Custom installation paths
- Manual .env configuration
- Skip specific components
- Advanced networking setup
- Cluster/multi-node configuration
- Custom model paths and quantizations

---

### 🧪 Mode 4: `test` — CI/CD & Testing Mode

**Command:** `./install.sh test`

**For:** Automated testing, CI pipelines, and development verification.

**Philosophy:** Headless, deterministic, with comprehensive test suite execution.

| Aspect | Behavior |
|--------|----------|
| **Speed** | ⚡⚡⚡ Fast |
| **Output** | Machine-readable (JSON/TAP) |
| **Database** | SQLite in-memory or temp file |
| **Tests** | Full test suite runs automatically |
| **Exit Code** | 0 = pass, non-zero = fail |

**Includes:**
- Unit tests
- Integration tests
- API endpoint verification
- Migration verification
- Sandbox smoke tests

---

### 🖼️ Mode 5: `image` — Pre-built Container/VM Image

**Command:** `./install.sh image [docker|lxd|qcow2]`

**For:** Deployment from pre-built images — fastest for production.

**Philosophy:** Everything pre-compiled and optimized. Just configure and run.

| Aspect | Behavior |
|--------|----------|
| **Speed** | ⚡⚡⚡⚡ Fast (image pull only) |
| **Customization** | Environment variables only |
| **Updates** | Pull new image version |

**Image Types:**
- `docker` — Docker Hub image
- `lxd` — LXD/Incus image
- `qcow2` — VM disk image (KVM/Proxmox)

---

### 🌩️ Mode 6: `bare` — Bare Metal Server

**Command:** `./install.sh bare`

**For:** Production deployments on dedicated servers, VPS, or self-hosted hardware.

**Philosophy:** Manual provisioning with production-grade security hardening.

| Aspect | Behavior |
|--------|----------|
| **Speed** | 🌩️ Thorough setup |
| **Security** | Production hardening, firewall rules, fail2ban |
| **SSL** | Auto-provisioned via Caddy/Let's Encrypt |
| **Services** | Systemd units for auto-restart |

**Includes:**
- UFW firewall configuration
- fail2ban setup
- Systemd service files
- Log rotation
- Automatic security updates
- Reverse proxy configuration
- SSL certificate provisioning

---

### • Mode 7: `cloud` — Cloud Instance (Premium)

**Command:** `./install.sh cloud`

**For:** Instant deployment on managed cloud infrastructure.

**Philosophy:** Zero configuration. Premium managed service.

| Aspect | Behavior |
|--------|----------|
| **Speed** | Instant |
| **Infrastructure** | Managed by Ginto.AI |
| **Maintenance** | Automatic updates, backups |
| **Support** | Priority support included |

**Deployment Options:**
- One-click from ginto.ai dashboard
- API provisioning for automation
- Custom subdomain: `yourname.ginto.ai`

---

## Lazy Loading Architecture

For `lite` mode, the system implements **progressive enhancement**:

```
┌─────────────────────────────────────────────────────────────┐
│  IMMEDIATE (< 30s)                                          │
│  ├── Core PHP files                                         │
│  ├── Minimal CSS/JS                                         │
│  ├── SQLite database (empty)                                │
│  └── Chat UI shell                                          │
├─────────────────────────────────────────────────────────────┤
│  BACKGROUND PHASE 1 (while user explores)                   │
│  ├── Composer dependencies                                  │
│  ├── Database migrations                                    │
│  └── Tailwind CSS compilation                               │
├─────────────────────────────────────────────────────────────┤
│  BACKGROUND PHASE 2 (on-demand)                             │
│  ├── Sandbox container (when first tool-call attempted)     │
│  ├── Local model download (when selected)                   │
│  └── MCP servers (when specific tools needed)               │
├─────────────────────────────────────────────────────────────┤
│  DEFERRED (user-initiated)                                  │
│  ├── Additional models                                      │
│  ├── Voice features (TTS/STT)                               │
│  └── Advanced plugins                                       │
└─────────────────────────────────────────────────────────────┘
```

---

## Guided Walkthrough System

### Cancelable Tooltip Walkthrough

After installation, users are greeted with an optional interactive walkthrough:

**Features:**
- 🎯 **Highlight bubbles** point to UI elements
- 💬 **Contextual tooltips** explain each feature
- ⏭️ **Skip button** always visible
- 🔄 **Resume later** — progress saved
- 🎮 **Simulated experience** — demo conversations show capabilities

**Walkthrough Steps:**
1. **Welcome** — Introduction to Ginto.AI
2. **Chat Input** — How to ask questions
3. **Model Selector** — Switching between AI providers
4. **Sidebar** — Navigation and history
5. **Sandbox** — Code execution explained
6. **Settings** — Customization options
7. **Completion** — Ready to use!

**Implementation:**
```javascript
// User can dismiss at any time
window.GINTO_WALKTHROUGH = {
  enabled: true,
  currentStep: 0,
  completed: false,
  dismiss: () => { /* save preference, hide all tooltips */ }
};
```

---

## Installation Flag Summary

| Flag | Speed | Features | Use Case |
|------|-------|----------|----------|
| `lite` | ⚡⚡⚡⚡⚡ | Minimal → Full | Quick demo, impatient users |
| `image` | ⚡⚡⚡⚡ | Full | Production deployment |
| `install` | ⚡⚡ | Full | Default, most users |
| `test` | ⚡⚡ | Test suite | CI/CD, verification |
| `expert` | ⚡ | Custom | Developers, self-hosting |
| `bare` | 🌩️ | Full + hardened | Bare metal servers |
| `cloud` | • | Full + managed | Cloud instance, instant premium |

---

## Future Enhancements

- [ ] `./install.sh upgrade` — In-place upgrades
- [ ] `./install.sh doctor` — Diagnose issues
- [ ] `./install.sh backup` — Full system backup
- [ ] `./install.sh restore` — Restore from backup
- [ ] Auto-detection of optimal mode based on system resources
- [ ] Web-based installer alternative (`/install` endpoint)

---

*Last updated: January 9, 2026*
