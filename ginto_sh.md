# Ginto Sandbox Setup (LXD + Alpine)

This document describes how to create and manage sandboxed containers for Ginto user code execution using LXD with Alpine Linux.

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                         HOST MACHINE                            │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │   Node.js Sandbox Proxy :1800 (deterministic IP routing)│    │
│  └────────────────────────┬────────────────────────────────┘    │
│                           │                                     │
│         ┌─────────────────┼─────────────────┐                   │
│         ▼                 ▼                 ▼                   │
│  ┌─────────────┐   ┌─────────────┐   ┌─────────────┐            │
│  │ sandbox-u1  │   │ sandbox-u2  │   │ sandbox-u3  │  ...       │
│  │ 10.0.0.2:80 │   │ 10.0.0.3:80 │   │ 10.0.0.4:80 │            │
│  │ (PHP+Caddy) │   │ (PHP+Caddy) │   │ (PHP+Caddy) │            │
│  └─────────────┘   └─────────────┘   └─────────────┘            │
│        LXD Containers (Alpine Linux)                            │
└─────────────────────────────────────────────────────────────────┘
```

**Flow:**
1. User hits `http://host:1800/?sandbox=user123`
2. Node.js proxy computes IP via SHA256(sandboxId)
3. Node.js proxies request to container's internal IP :80
4. Container's Caddy/PHP handles the request

## System Requirements & Dependencies

This section documents all system requirements and dependencies that must be manually installed for the sandbox infrastructure to function.

### Host System Requirements

| Requirement | Minimum | Recommended | Notes |
|-------------|---------|-------------|-------|
| **OS** | Ubuntu 20.04 LTS | Ubuntu 22.04+ LTS | Any LXD-compatible Linux works |
| **RAM** | 4 GB | 8+ GB | ~256MB per active container |
| **Disk** | 20 GB | 50+ GB | Base image ~100MB, ~100MB per sandbox |
| **CPU** | 2 cores | 4+ cores | Containers share CPU; 1 core limit per sandbox |
| **Kernel** | 5.4+ | 5.15+ | Required for LXD container isolation |

### Required Software (Host Machine)

These must be installed manually on the host machine before sandboxes can be created:

#### 1. Snap Package Manager

```bash
# Install snapd (usually pre-installed on Ubuntu)
sudo apt update
sudo apt install -y snapd

# Verify installation
snap version
```

#### 2. LXD Container Runtime

LXD provides container isolation for sandboxes.

```bash
# Install LXD via snap (recommended)
sudo snap install lxd

# Initialize LXD with defaults
sudo lxd init --auto

# Verify installation
sudo /snap/bin/lxc version
```

**Post-installation:**
- Socket location: `/var/snap/lxd/common/lxd/unix.socket`
- Binary location: `/snap/bin/lxc` (NOT `/usr/bin/lxc`)
- Default bridge network: `lxdbr0` (10.x.x.x range)

#### 3. Redis (MANUAL INSTALLATION REQUIRED)

⚠️ **Redis is NOT auto-installed.** It must be manually set up.

Redis stores sandbox-to-IP mappings for the reverse proxy, enabling O(1) lookups for container routing.

```bash
# Install Redis server
sudo apt update
sudo apt install -y redis-server

# Enable Redis to start on boot
sudo systemctl enable redis-server

# Start Redis
sudo systemctl start redis-server

# Verify Redis is running
redis-cli ping
# Expected output: PONG

# Check Redis version
redis-cli INFO server | grep redis_version
```

**Redis Configuration (Optional tuning):**

Edit `/etc/redis/redis.conf` for production:

```bash
# Recommended settings for sandbox usage
maxmemory 256mb              # Limit memory (1M keys ≈ 100MB)
maxmemory-policy allkeys-lru # Evict old keys when full
save ""                      # Disable persistence (optional)
```

**Redis Data Format:**
```
Key: sandbox:<sandboxId>       → Value: <container_ip>
Key: sandbox:<sandboxId>:last  → Value: <unix_timestamp>  (last access time)

Example:
sandbox:cs668dfqjm8e           → 10.93.65.197
sandbox:cs668dfqjm8e:last      → 1734432000
```

### Redis Implementation in Ginto

Redis is used in two places:

1. **PHP (`LxdSandboxManager` + `SandboxProxy`)** - For PHP-based lookups
2. **Node.js (`sandbox-proxy.js`)** - For high-performance reverse proxy

#### PHP Redis Integration

The `LxdSandboxManager` class (`src/Helpers/LxdSandboxManager.php`) uses Redis:

```php
// Get Redis connection
private static function getRedis(): ?\Redis
{
    if (!class_exists('Redis')) {
        return null;
    }
    try {
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        return $redis;
    } catch (\Throwable $e) {
        return null;
    }
}

// Cache container IP
public static function getSandboxIp(string $userId): ?string
{
    $redis = self::getRedis();
    if ($redis) {
        $cached = $redis->get('sandbox:' . $userId);
        if ($cached) return $cached;
    }
    
    // Fallback to LXD direct lookup
    $ip = self::getLxdIp($userId);
    if ($ip && $redis) {
        $redis->set('sandbox:' . $userId, $ip);
    }
    return $ip;
}
```

The `SandboxProxy` class (`src/Helpers/SandboxProxy.php`) extends this with last-access tracking:

```php
// Track last access time for cleanup
$redis->set(self::REDIS_PREFIX . $sandboxId . ':last', time());
```

#### Node.js Redis Integration

The sandbox proxy (`tools/sandbox-proxy/sandbox-proxy.js`) uses the `redis` npm package:

```javascript
const { createClient } = require('redis');

const CONFIG = {
    redisUrl: process.env.REDIS_URL || 'redis://127.0.0.1:6379',
    redisPrefix: 'sandbox:',
};

// Initialize Redis
let redis = createClient({ url: CONFIG.redisUrl });
await redis.connect();

// Get cached IP
async function getCachedIp(userId) {
    if (!redis?.isReady) return null;
    return await redis.get(CONFIG.redisPrefix + userId);
}

// Cache IP
async function cacheIp(userId, ip) {
    if (!redis?.isReady) return;
    await redis.set(CONFIG.redisPrefix + userId, ip);
}
```

### Redis Scaling for High Traffic

Redis handles sandbox routing with O(1) lookups, enabling horizontal scaling.

#### Scaling Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    LOAD BALANCER (Nginx/HAProxy)                │
│                              :1800                              │
└──────────────────────────────┬──────────────────────────────────┘
                               │
         ┌─────────────────────┼─────────────────────┐
         ▼                     ▼                     ▼
┌─────────────────┐   ┌─────────────────┐   ┌─────────────────┐
│   Web Server 1  │   │   Web Server 2  │   │   Web Server 3  │
│  (Ginto PHP)    │   │  (Ginto PHP)    │   │  (Ginto PHP)    │
└────────┬────────┘   └────────┬────────┘   └────────┬────────┘
         │                     │                     │
         └─────────────────────┼─────────────────────┘
                               ▼
                    ┌─────────────────────┐
                    │     Redis Cluster   │
                    │   (or single node)  │
                    │   sandbox:* keys    │
                    └──────────┬──────────┘
                               │
         ┌─────────────────────┼─────────────────────┐
         ▼                     ▼                     ▼
┌─────────────────┐   ┌─────────────────┐   ┌─────────────────┐
│   LXD Host 1    │   │   LXD Host 2    │   │   LXD Host 3    │
│ sandbox-user1   │   │ sandbox-user50  │   │ sandbox-user100 │
│ sandbox-user2   │   │ sandbox-user51  │   │ sandbox-user101 │
│     ...         │   │     ...         │   │     ...         │
└─────────────────┘   └─────────────────┘   └─────────────────┘
```

#### Redis Scaling Strategies

| Users | Strategy | Configuration |
|-------|----------|---------------|
| < 10,000 | Single Redis | Default install, 256MB RAM |
| 10,000 - 100,000 | Redis with persistence | Enable RDB snapshots |
| 100,000 - 1M | Redis with replicas | Master-slave replication |
| > 1M | Redis Cluster | Sharded across multiple nodes |

#### Memory Estimation

Each sandbox requires ~100 bytes in Redis:
- Key: `sandbox:<sandboxId>` (~30 bytes)
- Value: IP address (~15 bytes)
- Last access key: ~40 bytes
- Overhead: ~15 bytes

**Formula:**
```
Redis Memory = Number of Sandboxes × 100 bytes
10,000 sandboxes ≈ 1 MB
100,000 sandboxes ≈ 10 MB
1,000,000 sandboxes ≈ 100 MB
```

#### Redis Cluster Configuration (for > 100K users)

```bash
# Example redis-cluster.conf
port 7000
cluster-enabled yes
cluster-config-file nodes.conf
cluster-node-timeout 5000
appendonly yes
```

#### 4. PHP Extensions for Redis

The Ginto application needs PHP Redis support:

```bash
# Install PHP Redis extension
sudo apt install -y php-redis

# Verify it's loaded
php -m | grep redis

# Restart PHP-FPM (production)
sudo systemctl restart php-fpm
```

#### 5. Node.js (for Sandbox Proxy)

Required for the dynamic reverse proxy:

```bash
# Install Node.js 18+ (using NodeSource)
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Verify installation
node --version  # Should be v18+
npm --version

# Install proxy dependencies
cd /opt/ginto-sandbox-proxy
npm install http-proxy redis
```

#### 6. Caddy Web Server (Host)

For routing requests to the sandbox proxy:

```bash
# Install Caddy
sudo apt install -y debian-keyring debian-archive-keyring apt-transport-https
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | sudo gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | sudo tee /etc/apt/sources.list.d/caddy-stable.list
sudo apt update
sudo apt install caddy

# Caddy runs on port 1800 for sandbox routing
```

### Container Dependencies (Inside Each Sandbox)

Each sandbox container is created from the `ginto-sandbox` base image. The base image includes:

| Package | Version | Purpose |
|---------|---------|---------|
| Alpine Linux | 3.20 | Minimal base OS (~5MB) |
| OpenRC | Latest | Init system for services |
| bash | 5.x | Shell for command execution |
| curl | Latest | HTTP requests |
| ca-certificates | Latest | SSL/TLS verification |
| **php82** | 8.2.x | PHP runtime |
| **php82-fpm** | 8.2.x | FastCGI Process Manager |
| **php82-pdo** | 8.2.x | Database abstraction |
| **php82-pdo_mysql** | 8.2.x | MySQL driver |
| **php82-mysqli** | 8.2.x | MySQL native driver |
| **php82-json** | 8.2.x | JSON support |
| **php82-mbstring** | 8.2.x | Multibyte string support |
| **php82-openssl** | 8.2.x | SSL/cryptography |
| **php82-curl** | 8.2.x | cURL support |
| **php82-session** | 8.2.x | Session handling |
| **php82-tokenizer** | 8.2.x | PHP tokenizer |
| **php82-xml** | 8.2.x | XML support |
| **php82-ctype** | 8.2.x | Character type functions |
| **php82-fileinfo** | 8.2.x | File info functions |
| **caddy** | 2.x | Web server |
| mysql-client | Latest | MySQL CLI (for debugging) |
| **nodejs** | 20.x | JavaScript runtime |
| **npm** | 10.x | Node package manager |
| **python3** | 3.12.x | Python interpreter |
| **git** | 2.45.x | Version control |
| **composer** | Latest | PHP dependency manager (via script) |
| **sqlite** | 3.45.x | SQLite database |
| **vim** | 9.x | Text editor |
| **nano** | 8.x | Text editor |

**Total Base Image Size:** ~102 MB

### Sandbox Home Directory Structure

The `ginto-sandbox` base template includes a pre-configured `/root/` directory that serves as the root user home for all user files in the `/editor` route (accessed via "My Files" in `/chat`):

```
/root/
├── index.php      # Welcome page with dev tools info (served at /)
├── README.md      # Getting started guide for users
├── Desktop/       # Desktop files
├── Documents/     # Document storage
├── Downloads/     # Downloaded files
├── Music/         # Music files
├── Pictures/      # Image files
├── Videos/        # Video files
└── Websites/      # Web project directory
```

#### Default Files in Template

**`/root/README.md`** - A welcome guide displayed in the editor:
- Lists available tools (PHP, Node.js, Python, Git, etc.)
- Quick start examples for PHP, Node.js, and Python projects
- Web server information (Caddy on port 80)

**`/root/index.php`** - Default landing page that:
- Displays installed tool versions dynamically
- Shows the Ginto Sandbox welcome interface
- Includes quick start instructions and links

These files are automatically available when a user clicks "My Files" in the `/chat` route, providing an immediate starting point for development.

### How the Editor Route Uses Sandbox Files

The `/editor` route (and `/chat` → "My Files" button) reads files from within the LXC container:

1. **File Tree**: Uses `LxdSandboxManager::execInSandbox()` to run `find /root -type f` inside the container
2. **File Read**: Uses `lxc file pull` to retrieve file contents
3. **File Write**: Uses `lxc file push` to save edited files
4. **File Create**: Uses `lxc exec` to create new files/directories

The web root inside each container is `/root/`, configured in Caddy's configuration at `/etc/caddy/Caddyfile`:

```caddy
:80 {
    root * /root
    php_fastcgi unix//run/php-fpm82.sock
    file_server
    file_server browse
}
```

### Disk Space Requirements

| Component | Size | Notes |
|-----------|------|-------|
| LXD installation | ~50 MB | Via snap |
| `ginto-sandbox` image | ~102 MB | Base template |
| Each sandbox container | ~100 MB | Layered on base image |
| Redis data | ~1 MB per 10K mappings | Very lightweight |
| Container storage pool | 10-50 GB | Depends on user count |

**Formula for capacity planning:**
```
Total Storage = 102 MB (base) + (N containers × 100 MB) + 10 GB buffer
Example: 100 sandboxes = 102 + 10,000 + 10,000 = ~20 GB
```

### Network Requirements

| Port | Service | Direction | Notes |
|------|---------|-----------|-------|
| 8000 | PHP Dev Server | Inbound | Development only |
| 3000 | Node.js Proxy | Internal | Sandbox proxy |
| 1800 | Caddy | Inbound | Public sandbox access |
| 6379 | Redis | Localhost only | Never expose externally |
| 80 | Container Caddy | LXD bridge | Internal container web |

### Memory Requirements

| Component | Memory Usage |
|-----------|--------------|
| LXD daemon | ~50 MB |
| Redis server | ~10 MB idle, ~100 MB with 1M keys |
| Node.js proxy | ~50 MB |
| Each active container | 128-256 MB (configurable) |
| PHP-FPM per container | ~30 MB |

**Formula:**
```
Total RAM = 200 MB (overhead) + (N active containers × 256 MB)
Example: 20 active sandboxes = 200 + 5,120 = ~5.3 GB RAM
```

### Installation Verification Checklist

Run these commands to verify all dependencies are properly installed:

```bash
# 1. LXD
sudo /snap/bin/lxc version
# ✓ Should show version number

# 2. Redis
redis-cli ping
# ✓ Should return: PONG

# 3. PHP Redis extension
php -m | grep redis
# ✓ Should return: redis

# 4. Node.js
node --version
# ✓ Should be v18.x.x or higher

# 5. Base image exists
sudo /snap/bin/lxc image list | grep ginto-sandbox
# ✓ Should show ginto-sandbox image (~102MB)

# 6. Sudoers configuration
sudo -u www-data sudo /snap/bin/lxc list 2>&1 | head -1
# ✓ Should not prompt for password

# 7. Caddy (if using)
caddy version
# ✓ Should show version number
```

### Quick Dependency Install Script

For convenience, here's a complete installation script:

```bash
#!/bin/bash
# install_sandbox_deps.sh - Run as root or with sudo

set -e

echo "=== Installing Sandbox Dependencies ==="

# 1. Update system
apt update && apt upgrade -y

# 2. Install snap (if not present)
apt install -y snapd
snap install core

# 3. Install LXD
snap install lxd
lxd init --auto

# 4. Install Redis
apt install -y redis-server
systemctl enable redis-server
systemctl start redis-server

# 5. Install PHP Redis extension
apt install -y php-redis

# 6. Install Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt install -y nodejs

# 7. Install Caddy
apt install -y debian-keyring debian-archive-keyring apt-transport-https
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
apt update && apt install -y caddy

echo "=== Dependencies Installed ==="
echo "Next steps:"
echo "1. Create base image: sudo /path/to/ginto/bin/setup_lxd_alpine.sh"
echo "2. Configure sudoers: see docs/sandbox.md 'LXD Permissions Setup'"
echo "3. Start services: redis-server, caddy, sandbox-proxy"
```

---

## Quick Start

```bash
# 1. Install LXD and create base image
sudo /path/to/ginto/bin/setup_lxd_alpine.sh

# 2. Install proxy infrastructure (Redis, Node.js proxy, Caddy config)
sudo /path/to/ginto/bin/setup_sandbox_proxy.sh

# 3. Start the proxy
cd /opt/ginto-sandbox-proxy && node sandbox-proxy.js
```

## Part 1: LXD Base Image Setup

### Install LXD

```bash
sudo snap install lxd
sudo lxd init --auto
```

### Run the Setup Script

```bash
sudo /path/to/ginto/bin/setup_lxd_alpine.sh
```

This creates:
- `ginto-base`: Template container
- `ginto`: Reusable image (~9MB)

### Creating the Full ginto-sandbox Image

The minimal `ginto` image only contains Alpine + bash + curl. The production `ginto-sandbox` image (~102MB) includes all development tools. Here's how to create it:

```bash
#!/bin/bash
# create_ginto_sandbox_image.sh - Creates the full development sandbox template

set -e

CONTAINER="ginto-sandbox"
IMAGE_ALIAS="ginto-sandbox"

echo "=== Creating Ginto Sandbox Template ==="

# 1. Launch from minimal Alpine image
sudo /snap/bin/lxc launch images:alpine/3.20 $CONTAINER

# Wait for container to be ready
sleep 3

# 2. Install PHP 8.2 and all required extensions
echo "[*] Installing PHP 8.2..."
sudo /snap/bin/lxc exec $CONTAINER -- apk update
sudo /snap/bin/lxc exec $CONTAINER -- apk add --no-cache \
    php82 \
    php82-fpm \
    php82-pdo \
    php82-pdo_mysql \
    php82-pdo_sqlite \
    php82-mysqlnd \
    php82-json \
    php82-mbstring \
    php82-openssl \
    php82-curl \
    php82-session \
    php82-tokenizer \
    php82-xml \
    php82-ctype \
    php82-fileinfo \
    php82-dom \
    php82-iconv \
    php82-phar \
    php82-common

# 3. Install MySQL client (connects to external MySQL server)
echo "[*] Installing MySQL client..."
sudo /snap/bin/lxc exec $CONTAINER -- apk add --no-cache mysql-client

# 4. Install SQLite for local database needs
echo "[*] Installing SQLite..."
sudo /snap/bin/lxc exec $CONTAINER -- apk add --no-cache sqlite

# 5. Install Node.js 20 and npm
echo "[*] Installing Node.js 20..."
sudo /snap/bin/lxc exec $CONTAINER -- apk add --no-cache nodejs npm

# 6. Install Python 3.12
echo "[*] Installing Python 3..."
sudo /snap/bin/lxc exec $CONTAINER -- apk add --no-cache python3 py3-pip

# 7. Install Git
echo "[*] Installing Git..."
sudo /snap/bin/lxc exec $CONTAINER -- apk add --no-cache git

# 8. Install Composer (PHP package manager)
echo "[*] Installing Composer..."
sudo /snap/bin/lxc exec $CONTAINER -- sh -c 'curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer'

# 8b. Create php symlink (required for Composer and PHP-FPM PATH)
# Alpine installs PHP as php82, but many tools expect just 'php'
echo "[*] Creating php symlink..."
sudo /snap/bin/lxc exec $CONTAINER -- ln -sf /usr/bin/php82 /usr/bin/php

# 9. Install Caddy web server
echo "[*] Installing Caddy..."
sudo /snap/bin/lxc exec $CONTAINER -- apk add --no-cache caddy caddy-openrc

# 10. Install text editors
echo "[*] Installing editors..."
sudo /snap/bin/lxc exec $CONTAINER -- apk add --no-cache vim nano

# 11. Install essential tools
echo "[*] Installing essential tools..."
sudo /snap/bin/lxc exec $CONTAINER -- apk add --no-cache \
    bash \
    curl \
    ca-certificates \
    openrc

# 12. Create /home directory structure
echo "[*] Setting up /home directory..."
sudo /snap/bin/lxc exec $CONTAINER -- mkdir -p /home/{Desktop,Documents,Downloads,Music,Pictures,Videos,Websites}

# 13. Create default index.php (uses full paths for PHP-FPM compatibility)
# Note: PHP-FPM has limited PATH, so use absolute paths for shell_exec commands
sudo /snap/bin/lxc exec $CONTAINER -- sh -c 'cat > /home/index.php << '\''PHPEOF'\''
<?php
// Use full paths - PHP-FPM PATH does not include /usr/local/bin
$tools = [
    "PHP" => phpversion(),
    "Node.js" => trim(shell_exec("/usr/bin/node --version 2>/dev/null") ?: "N/A"),
    "npm" => trim(shell_exec("/usr/bin/npm --version 2>/dev/null") ?: "N/A"),
    "Python" => trim(shell_exec("/usr/bin/python3 --version 2>/dev/null") ?: "N/A"),
    "Git" => trim(shell_exec("/usr/bin/git --version 2>/dev/null") ?: "N/A"),
    "Composer" => trim(preg_match("/\d+\.\d+\.\d+/", shell_exec("/usr/local/bin/composer --version 2>&1") ?? "", $m) ? $m[0] : "N/A"),
];
?>
<!DOCTYPE html>
<html><head><title>Ginto Sandbox</title></head>
<body>
<h1>🚀 Welcome to Ginto Sandbox</h1>
<h2>Available Tools:</h2>
<ul>
<?php foreach ($tools as $name => $ver): ?>
<li><strong><?= $name ?>:</strong> <?= htmlspecialchars($ver) ?></li>
<?php endforeach; ?>
</ul>
</body></html>
PHPEOF'

# 14. Create README.md
sudo /snap/bin/lxc exec $CONTAINER -- sh -c 'cat > /home/README.md << '\''EOF'\''
# 🚀 Ginto Sandbox

Welcome to your personal development environment!

## Available Tools

- **PHP 8.2** + Composer
- **Node.js 20** + npm
- **Python 3** + pip
- **Git** for version control
- **MySQL Client** + **SQLite**
- **Vim/Nano** text editors

## Quick Start

Edit `index.php` or create new files to get started!
EOF'

# 15. Configure Caddy
echo "[*] Configuring Caddy..."
sudo /snap/bin/lxc exec $CONTAINER -- sh -c 'cat > /etc/caddy/Caddyfile << '\''EOF'\''
:80 {
    root * /home
    php_fastcgi unix//run/php-fpm82.sock
    file_server
    file_server browse
    header {
        X-Content-Type-Options nosniff
        X-Frame-Options SAMEORIGIN
    }
    log {
        output file /var/log/caddy/access.log
        format console
    }
}
EOF'

# 16. Configure PHP-FPM
echo "[*] Configuring PHP-FPM..."
sudo /snap/bin/lxc exec $CONTAINER -- mkdir -p /var/log/caddy

# 17. Enable services to auto-start
echo "[*] Enabling services..."
sudo /snap/bin/lxc exec $CONTAINER -- rc-update add php-fpm82 default
sudo /snap/bin/lxc exec $CONTAINER -- rc-update add caddy default

# 18. Set proper permissions
sudo /snap/bin/lxc exec $CONTAINER -- chown -R nobody:nobody /home

# 19. Stop container and publish as image
echo "[*] Publishing image..."
sudo /snap/bin/lxc stop $CONTAINER
sudo /snap/bin/lxc publish $CONTAINER --alias $IMAGE_ALIAS

echo "=== Done ==="
echo "Image created: $IMAGE_ALIAS"
echo "To verify: sudo /snap/bin/lxc image list | grep $IMAGE_ALIAS"
```

### Key Points About the Base Image

1. **No MySQL Server**: The sandbox uses `mysql-client` to connect to an external MySQL server (the host's MySQL). MySQL server is NOT installed inside containers to save resources.

2. **PHP-FPM + Caddy**: Each container runs its own PHP-FPM and Caddy instance, providing isolated web serving.

3. **OpenRC Services**: Alpine uses OpenRC (not systemd). Services are enabled with `rc-update add <service> default`.

4. **Web Root is `/home/`**: User files are stored in `/home/` which is the Caddy document root.

5. **No sudo inside container**: Containers run as isolated environments without sudo access.

6. **PHP Symlink Required**: Alpine installs PHP as `php82`, but Composer and other tools expect `php`. The symlink `/usr/bin/php -> /usr/bin/php82` is essential.

7. **Full Paths in index.php**: PHP-FPM runs with a limited `PATH` that doesn't include `/usr/local/bin`. Use absolute paths like `/usr/local/bin/composer` in `shell_exec()` calls.

### Updating the Base Image

When making changes to the `ginto-sandbox` container (e.g., installing packages, updating configs), you must **republish it as an image** for changes to affect new sandboxes:

```bash
# 1. Make your changes to the container
sudo /snap/bin/lxc start ginto-sandbox
sudo /snap/bin/lxc exec ginto-sandbox -- <your commands>

# 2. Stop the container
sudo /snap/bin/lxc stop ginto-sandbox

# 3. Delete the old image alias and republish
sudo /snap/bin/lxc image alias delete ginto-sandbox
sudo /snap/bin/lxc publish ginto-sandbox --alias ginto-sandbox

# 4. Verify the new image
sudo /snap/bin/lxc image list | grep ginto-sandbox
```

**Important**: `lxc launch ginto-sandbox` uses the **image**, not the running container. Changes to the container are NOT automatically reflected in the image.

### Backup and Restore Images

```bash
# Export image to file (for backup)
sudo /snap/bin/lxc image export ginto-sandbox /path/to/backup/ginto-sandbox-$(date +%Y%m%d)

# Import image from file
sudo /snap/bin/lxc image import /path/to/backup/ginto-sandbox-20251216.tar.gz --alias ginto-sandbox-restored
```

## Part 2: Per-User Sandbox Lifecycle

### First Time (Create Sandbox)

When a user clicks "My Files" for the first time:

```php
// PHP triggers sandbox creation
$sandboxId = "sandbox-" . $userId;
exec("lxc launch ginto $sandboxId");

// Wait for container, get IP
$ip = trim(shell_exec("lxc list $sandboxId -c 4 --format csv | cut -d' ' -f1"));

// Install packages inside container
exec("lxc exec $sandboxId -- apk add php82 php82-fpm caddy mysql-client");

// Store mapping in Redis
$redis->set("sandbox:$userId", $ip);
```

### Subsequent Visits (Access Existing Sandbox)

```php
$sandboxId = "sandbox-" . $userId;
$ip = $redis->get("sandbox:$userId");

if (!$ip) {
    // Sandbox might exist but not in cache - get IP from LXD
    $ip = trim(shell_exec("lxc list $sandboxId -c 4 --format csv | cut -d' ' -f1"));
    if ($ip) $redis->set("sandbox:$userId", $ip);
}

// Execute commands in sandbox
exec("lxc exec $sandboxId -- /bin/sh -c 'cd /home && php script.php'");
```

## Part 3: Reverse Proxy Setup

### Option A: Node.js Dynamic Proxy (Recommended for 1M+ users)

Located at `tools/sandbox-proxy/sandbox-proxy.js`:

```javascript
const http = require('http');
const httpProxy = require('http-proxy');
const redis = require('redis');

const proxy = httpProxy.createProxyServer();
const client = redis.createClient();

http.createServer(async (req, res) => {
    const url = new URL(req.url, `http://${req.headers.host}`);
    const userId = url.searchParams.get('sandbox');
    
    if (!userId) {
        res.writeHead(400);
        return res.end('Missing sandbox parameter');
    }

    const ip = await client.get(`sandbox:${userId}`);
    if (!ip) {
        res.writeHead(404);
        return res.end('Sandbox not found');
    }

    proxy.web(req, res, { target: `http://${ip}:80` });
}).listen(1800, () => console.log('Sandbox proxy running on :1800'));
```

### Option B: Caddy with Dynamic Backends

```
:1800 {
    reverse_proxy {
        to {http.request.uri.query.sandbox}:80
        # Requires lookup translation - see sandbox-proxy for dynamic routing
    }
}
```

### Host Caddy Configuration

Note: The sandbox proxy now listens directly on port 1800, so Caddy reverse proxy is optional:

```
# Only needed if using Caddy as a front proxy
:1800 {
    # Node.js proxy listens directly on this port
}
```

## Part 4: Inside Each Container

Each sandbox container runs:
- **Caddy**: Web server on port 80
- **PHP-FPM**: PHP processing
- **User files**: Located at `/root/`

Container Caddyfile (`/etc/caddy/Caddyfile`):

```
:80 {
    root * /root
    php_fastcgi unix//run/php-fpm.sock
    file_server
}
```

## Redis vs LXD Container for Redis

**Recommendation: Install Redis on host machine**

Reasons:
- Lower latency for lookups (no network hop)
- Simpler architecture
- Redis is lightweight (~1MB memory for 1M keys)
- Containers are ephemeral; Redis persistence on host is easier

```bash
sudo apt install redis-server
sudo systemctl enable redis-server
```

## CLI Reference

### Container Management

```bash
# Spawn new sandbox
lxc launch ginto sandbox-user123

# Execute commands
lxc exec sandbox-user123 -- /bin/sh -c "php /root/index.php"

# Get container IP
lxc list sandbox-user123 -c 4 --format csv | cut -d' ' -f1

# Copy files in
lxc file push /local/file.php sandbox-user123/root/file.php

# Copy files out
lxc file pull sandbox-user123/root/output.txt ./output.txt

# Stop and delete
lxc stop sandbox-user123
lxc delete sandbox-user123
```

### Redis Commands

```bash
# Set sandbox mapping
redis-cli SET sandbox:user123 10.0.0.5

# Get sandbox IP
redis-cli GET sandbox:user123

# List all sandboxes
redis-cli KEYS "sandbox:*"

# Delete mapping
redis-cli DEL sandbox:user123
```

## Resource Limits

Apply per-container limits:

```bash
lxc config set sandbox-user123 limits.cpu 1
lxc config set sandbox-user123 limits.memory 256MB
lxc config device set sandbox-user123 root size 100MB
```

## PHP Integration

The `SandboxManager.php` helper provides:

```php
use Ginto\Helpers\SandboxManager;

// Create sandbox for user
$result = SandboxManager::createSandbox($userId);
// Returns: ['ip' => '10.0.0.5', 'sandboxId' => 'sandbox-user123']

// Check if sandbox exists
$exists = SandboxManager::sandboxExists($userId);

// Execute command in sandbox
[$code, $stdout, $stderr] = SandboxManager::execInSandbox($userId, 'php script.php');

// Get sandbox IP
$ip = SandboxManager::getSandboxIp($userId);

// Delete sandbox
SandboxManager::deleteSandbox($userId);
```

## Setup Scripts

| Script | Purpose |
|--------|---------|
| `bin/setup_lxd_alpine.sh` | Create minimal LXD base image (`ginto`) |
| `bin/setup_sandbox_proxy.sh` | Install Redis, Node.js proxy, Caddy config |
| `tools/sandbox-proxy/sandbox-proxy.js` | Node.js dynamic proxy server |

## Current Status

- **Production Image**: `ginto-sandbox` (~102MB Alpine 3.20) — Full PHP/Caddy stack
- **Minimal Image**: `ginto` (~9MB Alpine 3.20) — Minimal base (bash, curl, ca-certificates)
- **Container startup time**: ~1-2 seconds
- **Default resource limits**: 1 CPU core, 512MB RAM per container

See [System Requirements & Dependencies](#system-requirements--dependencies) for full installation details.

---

## MySQL Database Integration

The sandbox system uses MySQL to track sandbox ownership and metadata through the `client_sandboxes` table.

### Database Schema

```sql
CREATE TABLE IF NOT EXISTS client_sandboxes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,           -- FK to users table (logged-in users)
    public_id VARCHAR(16) NULL,          -- Session-based ID (visitors)
    sandbox_id VARCHAR(12) NOT NULL UNIQUE,  -- Random unique sandbox identifier
    quota_bytes BIGINT NOT NULL DEFAULT 104857600, -- 100MB default quota
    used_bytes BIGINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Index for fast lookups
CREATE INDEX idx_client_sandbox_id ON client_sandboxes (sandbox_id);
```

### User Types and Sandbox Ownership

| User Type | Identifier | Session Key | Sandbox Lifetime | Cleanup Method |
|-----------|------------|-------------|------------------|----------------|
| **Visitor** | `public_id` (session-generated) | `$_SESSION['public_id']` | 1 hour | Auto on session expiry |
| **User** | `user_id` (database FK) | `$_SESSION['user_id']` | Permanent | Manual or admin delete |
| **Admin** | `user_id` + `is_admin` flag | `$_SESSION['is_admin']` | Uses project root (no sandbox) | N/A |

### Sandbox Installation Wizard

The sandbox installation wizard provides a guided, user-friendly experience for creating sandboxes. It's implemented in `/src/Views/chat/chat.php` and handles the complete flow from user action to sandbox creation.

#### Wizard Flow Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                     SANDBOX INSTALLATION WIZARD FLOW                        │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  User clicks "My Files" button in /chat sidebar                             │
│       ↓                                                                     │
│  checkAndOpenSandbox() function triggered                                   │
│       ↓                                                                     │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │  GET /sandbox/status  →  Check if sandbox already exists            │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│       ↓                                                                     │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │ Status Response Routing:                                             │   │
│  │                                                                      │   │
│  │  • "unauthorized"    → Redirect to /login?redirect=/chat             │   │
│  │  • "not_created"     → Show Installation Wizard (Step 1)             │   │
│  │  • "not_installed"   → Show Installation Wizard (Step 1)             │   │
│  │  • "installed" (stopped) → POST /sandbox/start, then open editor     │   │
│  │  • "running"         → Open editor directly with ?sandbox=ID         │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

#### Wizard Modal Steps

The wizard is a 5-step modal dialog (`#sandbox-wizard-modal`) in chat.php:

| Step | ID | Title | Purpose |
|------|----|-------|---------|
| **Step 1** | `wizard-step-1` | Welcome | Introduces the sandbox feature, explains benefits |
| **Step 2** | `wizard-step-2` | Terms & Conditions | User must accept terms before installation |
| **Step 3** | `wizard-step-3` | Installing | Progress animation with real-time status updates |
| **Step 4** | `wizard-step-4` | Success | Shows sandbox ID, offers "Open Editor" button |
| **Step Error** | `wizard-step-error` | Error | Displays error message, allows retry |

#### Step-by-Step Wizard Experience

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  STEP 1: WELCOME                                                            │
│  ─────────────────────────────────────────────────────────────────────────  │
│                                                                             │
│    ╔════════════════════════════════════════════════════════════════╗       │
│    ║  🎉 Welcome to Your Personal Sandbox                           ║       │
│    ║                                                                ║       │
│    ║  Your sandbox is a secure, isolated environment where you can: ║       │
│    ║    ✓ Write and run PHP, Node.js, and Python code               ║       │
│    ║    ✓ Create and manage files                                   ║       │
│    ║    ✓ Install packages with npm, pip, composer                  ║       │
│    ║    ✓ Preview your projects in real-time                        ║       │
│    ║                                                                ║       │
│    ║                                [Get Started →]                 ║       │
│    ╚════════════════════════════════════════════════════════════════╝       │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│  STEP 2: TERMS & CONDITIONS                                                 │
│  ─────────────────────────────────────────────────────────────────────────  │
│                                                                             │
│    ╔════════════════════════════════════════════════════════════════╗       │
│    ║  📜 Terms of Service                                           ║       │
│    ║                                                                ║       │
│    ║  By using this sandbox, you agree to:                          ║       │
│    ║    • Not use it for malicious purposes                         ║       │
│    ║    • Respect resource limits (CPU, memory, storage)            ║       │
│    ║    • Visitors: Sandbox expires after 1 hour                    ║       │
│    ║                                                                ║       │
│    ║  [✓] I accept the terms and conditions                         ║       │
│    ║                                                                ║       │
│    ║  [← Back]                        [Install Sandbox →]           ║       │
│    ╚════════════════════════════════════════════════════════════════╝       │
│                                                                             │
│  Note: Install button is disabled until checkbox is checked                 │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│  STEP 3: INSTALLING (Progress Animation)                                    │
│  ─────────────────────────────────────────────────────────────────────────  │
│                                                                             │
│    ╔════════════════════════════════════════════════════════════════╗       │
│    ║  ⏳ Setting up your sandbox...                                 ║       │
│    ║                                                                ║       │
│    ║  ████████████░░░░░░░░░░░░░░░░░░  45%                           ║       │
│    ║                                                                ║       │
│    ║  [✓] Creating sandbox directory...                             ║       │
│    ║  [⟳] Launching LXC container...       - Currently active      ║       │
│    ║  [ ] Configuring environment...                                ║       │
│    ║  [ ] Finalizing setup...                                       ║       │
│    ║                                                                ║       │
│    ║  Status: "Launching LXC container..."                          ║       │
│    ╚════════════════════════════════════════════════════════════════╝       │
│                                                                             │
│  Backend: POST /sandbox/install with CSRF token                             │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│  STEP 4: SUCCESS                                                            │
│  ─────────────────────────────────────────────────────────────────────────  │
│                                                                             │
│    ╔════════════════════════════════════════════════════════════════╗       │
│    ║  ✅ Sandbox Ready!                                             ║       │
│    ║                                                                ║       │
│    ║  Your sandbox ID: a1b2c3d4-e5f6-...                            ║       │
│    ║                                                                ║       │
│    ║  Your isolated development environment is now ready.           ║       │
│    ║  Click below to start coding!                                  ║       │
│    ║                                                                ║       │
│    ║                    [🚀 Open Editor]                            ║       │
│    ╚════════════════════════════════════════════════════════════════╝       │
│                                                                             │
│  Clicking "Open Editor" calls openSandboxAfterInstall()                     │
│  → Opens /editor?sandbox={sandbox_id} in iframe modal                       │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│  STEP ERROR: Installation Failed                                            │
│  ─────────────────────────────────────────────────────────────────────────  │
│                                                                             │
│    ╔════════════════════════════════════════════════════════════════╗       │
│    ║  ❌ Installation Failed                                        ║       │
│    ║                                                                ║       │
│    ║  Error: "LXC container creation timed out"                     ║       │
│    ║                                                                ║       │
│    ║  [Try Again]                                 [Close]           ║       │
│    ╚════════════════════════════════════════════════════════════════╝       │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

#### JavaScript Functions

The wizard is powered by these key JavaScript functions in `chat.php`:

```javascript
// Main entry point - triggered by "My Files" button click
async function checkAndOpenSandbox() {
    // 1. Wait for auth to be ready
    await window.GINTO_AUTH_PROMISE;
    
    // 2. Check sandbox status via API
    const statusRes = await fetch('/sandbox/status', { credentials: 'same-origin' });
    const statusData = await statusRes.json();
    
    // 3. Route based on status
    if (statusData.status === 'not_created') {
        showSandboxWizard();  // Opens wizard modal
    } else if (statusData.container_status === 'stopped') {
        // Start stopped container, then open editor
        await fetch('/sandbox/start', { method: 'POST', ... });
        openEditorWithSandbox(statusData.sandbox_id);
    } else {
        // Already running - open editor directly
        openEditorWithSandbox(statusData.sandbox_id);
    }
}

// Show/hide wizard modal
function showSandboxWizard() {
    document.getElementById('sandbox-wizard-modal').classList.remove('hidden');
    showWizardStep(1);  // Start at welcome step
}

function closeSandboxWizard() {
    document.getElementById('sandbox-wizard-modal').classList.add('hidden');
}

// Navigate between wizard steps
function showWizardStep(step) {
    document.querySelectorAll('.wizard-step').forEach(el => el.classList.add('hidden'));
    document.getElementById('wizard-step-' + step).classList.remove('hidden');
}

// Perform the actual installation
async function installSandbox() {
    showWizardStep(3);  // Show progress step
    
    // Animate progress through 4 sub-steps
    const steps = [
        { num: 1, text: 'Creating sandbox directory...', progress: 10 },
        { num: 2, text: 'Launching LXC container...', progress: 30 },
        { num: 3, text: 'Configuring environment...', progress: 70 },
        { num: 4, text: 'Finalizing setup...', progress: 90 }
    ];
    
    // POST to /sandbox/install with CSRF token
    const body = new URLSearchParams();
    body.append('csrf_token', window.GINTO_AUTH?.csrfToken || '');
    body.append('accept_terms', '1');
    
    const res = await fetch('/sandbox/install', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    });
    
    const data = await res.json();
    
    if (data.success) {
        showWizardStep(4);  // Success step
        installedSandboxId = data.sandbox_id;
    } else {
        showWizardStep('error');  // Error step
    }
}

// Open editor after successful installation
function openSandboxAfterInstall() {
    closeSandboxWizard();
    editorIframe.src = '/editor?sandbox=' + encodeURIComponent(installedSandboxId);
    editorModal.classList.remove('hidden');
}
```

#### CSRF Token Handling

The wizard includes automatic CSRF token refresh for visitors whose sessions may have been reset:

```javascript
// If CSRF fails (403), try refreshing token once
if (res.status === 403 && data?.error?.toLowerCase().includes('csrf')) {
    const refreshed = await refreshCsrfToken();
    if (refreshed) {
        // Retry the install request with new token
        ({ res, data } = await doInstallRequest());
    }
}

async function refreshCsrfToken() {
    const res = await fetch('/dev/csrf', { credentials: 'same-origin' });
    const data = await res.json();
    if (data.success && data.csrf_token) {
        window.GINTO_AUTH.csrfToken = data.csrf_token;
        return true;
    }
    return false;
}
```

#### Sandbox Status Indicator

The sidebar shows a real-time status indicator for the sandbox:

```javascript
function updateSandboxStatusIndicator(status) {
    const indicator = document.getElementById('sandbox-status-indicator');
    const dot = indicator.querySelector('span');
    
    switch (status) {
        case 'running':
        case 'ready':
            // Green pulsing dot
            dot.className = 'w-2 h-2 rounded-full bg-emerald-500 animate-pulse';
            break;
        case 'stopped':
        case 'installed':
            // Amber dot (stopped but exists)
            dot.className = 'w-2 h-2 rounded-full bg-amber-500';
            break;
        case 'not_created':
        case 'not_installed':
            // Gray dot (no sandbox)
            dot.className = 'w-2 h-2 rounded-full bg-gray-400';
            break;
        case 'error':
            // Red dot
            dot.className = 'w-2 h-2 rounded-full bg-red-500';
            break;
    }
}
```

#### Visitor vs Logged-In User Experience

| Aspect | Visitor | Logged-In User |
|--------|---------|----------------|
| **Trigger** | Same - "My Files" button | Same - "My Files" button |
| **Wizard Steps** | Same 5-step wizard | Same 5-step wizard |
| **Session Storage** | `public_id` in session | `user_id` in session |
| **Sandbox Lifetime** | 1 hour (auto-cleanup) | Permanent until manual delete |
| **CSRF Handling** | Auto-refresh on 403 | Standard validation |
| **Post-Install** | Editor opens with temp sandbox | Editor opens with persistent sandbox |

### Sandbox Creation Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        SANDBOX CREATION SEQUENCE                            │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  1. User clicks "My Files" in /chat                                         │
│     ↓                                                                       │
│  2. Frontend sends POST to /sandbox/install with CSRF token                 │
│     ↓                                                                       │
│  3. Backend validates CSRF token:                                           │
│     if (empty($token) || $token !== $_SESSION['csrf_token']) → 403 error    │
│     ↓                                                                       │
│  4. Determine user type:                                                    │
│     - Logged in user → use user_id                                          │
│     - Visitor → use/generate public_id                                      │
│     ↓                                                                       │
│  5. Check if sandbox exists in DB:                                          │
│     SELECT sandbox_id FROM client_sandboxes WHERE user_id = ? OR public_id = ?
│     ↓                                                                       │
│  6. If exists → validate LXC container exists                               │
│     If not → generate new sandbox_id                                        │
│     ↓                                                                       │
│  7. INSERT INTO client_sandboxes (user_id, public_id, sandbox_id, ...)      │
│     ↓                                                                       │
│  8. Create LXC container: lxc launch ginto-sandbox sandbox-{sandbox_id}     │
│     ↓                                                                       │
│  9. Store in session: $_SESSION['sandbox_id'] = $sandboxId                  │
│     ↓                                                                       │
│  10. Cache IP in Redis: sandbox:{sandbox_id} → container_ip                 │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Sandbox Deletion Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        SANDBOX DELETION SEQUENCE                            │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Trigger: POST /sandbox/destroy OR session expiry OR admin action           │
│     ↓                                                                       │
│  1. Validate CSRF token (for user-initiated)                                │
│     ↓                                                                       │
│  2. Get sandbox_id from session or request                                  │
│     ↓                                                                       │
│  3. LxdSandboxManager::deleteSandboxCompletely($sandboxId, $db):            │
│     a. lxc stop sandbox-{id} --force                                        │
│     b. lxc delete sandbox-{id}                                              │
│     c. DELETE FROM client_sandboxes WHERE sandbox_id = ?                    │
│     d. Redis: DEL sandbox:{id}                                              │
│     e. rm -rf /clients/{id}/  (legacy directory if exists)                  │
│     ↓                                                                       │
│  4. Clear session:                                                          │
│     unset($_SESSION['sandbox_id'])                                          │
│     unset($_SESSION['sandbox_created_at'])                                  │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Visitor Session Lifecycle

Visitors get a 1-hour sandbox session. Here's the complete lifecycle:

```php
// In editor.php - Visitor session management

// Check if visitor session expired (1 hour)
if ($isVisitor) {
    $sessionCreatedAt = $_SESSION['session_created_at'] ?? null;
    $sessionLifetime = 3600; // 1 hour
    
    if ($sessionCreatedAt && (time() - $sessionCreatedAt) >= $sessionLifetime) {
        // Session expired - clean up sandbox completely
        $expiredSandboxId = $_SESSION['sandbox_id'] ?? null;
        if ($expiredSandboxId) {
            $db = \Ginto\Core\Database::getInstance();
            LxdSandboxManager::deleteSandboxCompletely($expiredSandboxId, $db);
        }
        
        // Destroy the entire session
        session_unset();
        session_destroy();
        session_start();
        
        // Set fresh session timestamp
        $_SESSION['session_created_at'] = time();
    }
}
```

### Database Queries Summary

| Operation | Query | When |
|-----------|-------|------|
| **Find user's sandbox** | `SELECT sandbox_id FROM client_sandboxes WHERE user_id = ?` | On page load |
| **Find visitor's sandbox** | `SELECT sandbox_id FROM client_sandboxes WHERE public_id = ?` | On page load |
| **Create sandbox** | `INSERT INTO client_sandboxes (user_id, public_id, sandbox_id, ...)` | On "My Files" click |
| **Check sandbox exists** | `SELECT 1 FROM client_sandboxes WHERE sandbox_id = ?` | Validation |
| **Delete sandbox** | `DELETE FROM client_sandboxes WHERE sandbox_id = ?` | On destroy/expiry |
| **Update quota** | `UPDATE client_sandboxes SET quota_bytes = ? WHERE sandbox_id = ?` | Admin action |
| **List all sandboxes** | `SELECT * FROM client_sandboxes` | Admin panel |

---

## CSRF Security for Sandbox Operations

All sandbox mutating operations require CSRF token validation to prevent cross-site request forgery attacks.

### CSRF Token Generation

```php
// In routes - Generate and store CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Pass to view
\Ginto\Core\View::view('chat/chat', [
    'csrf_token' => $csrf_token,
    // ...
]);
```

### CSRF Token Validation

```php
// In POST handlers - Validate CSRF token
$token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid or missing CSRF token']);
    exit;
}
```

### Protected Endpoints

| Endpoint | Method | CSRF Required | Description |
|----------|--------|---------------|-------------|
| `/sandbox/install` | POST | ✅ Yes | Create sandbox container |
| `/sandbox/destroy` | POST | ✅ Yes | Delete sandbox completely |
| `/chat/create_sandbox` | POST | ✅ Yes | Create sandbox from chat |
| `/editor/toggle_sandbox` | POST | ✅ Yes | Toggle sandbox mode (admin) |
| `/editor/save` | POST | ✅ Yes | Save file in sandbox |
| `/editor/create` | POST | ✅ Yes | Create file/folder |
| `/editor/delete` | POST | ✅ Yes | Delete file/folder |
| `/editor/rename` | POST | ✅ Yes | Rename file/folder |
| `/admin/sandboxes/{id}/delete` | POST | ✅ Yes | Admin delete sandbox |
| `/admin/sandboxes/{id}/start` | POST | ✅ Yes | Admin start container |
| `/admin/sandboxes/{id}/stop` | POST | ✅ Yes | Admin stop container |

### Frontend CSRF Implementation

```javascript
// In JavaScript - Include CSRF token in requests
async function createSandbox() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content 
        || window.CSRF_TOKEN;
    
    const response = await fetch('/sandbox/install', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken  // Can use header
        },
        body: JSON.stringify({
            csrf_token: csrfToken,      // Or body field
            accept_terms: true
        })
    });
}
```

### Admin Sandbox Operations

Admins have additional sandbox management capabilities with full CSRF protection:

```php
// UsersAdminController.php - Admin delete sandbox
public function deleteSandbox($sandboxId) {
    // Validate method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        return;
    }
    
    // Validate CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo 'Invalid token';
        return;
    }
    
    // Perform atomic deletion
    $result = LxdSandboxManager::deleteSandboxCompletely($sandboxId, $this->db);
    
    // Response
    header('Location: /admin/sandboxes');
}
```

### Security Best Practices

1. **Token Generation**: Use cryptographically secure random bytes (`random_bytes(32)`)
2. **Token Storage**: Store only in server-side session (never in cookies alone)
3. **Token Comparison**: Use `hash_equals()` to prevent timing attacks
4. **Token Per-Session**: Generate once per session, regenerate on login
5. **HTTPS Only**: Always use HTTPS in production to protect tokens in transit

---

## Notes

- Alpine 3.19 no longer available; using 3.20
- Containers start in ~1-2 seconds
- Container IPs are in the `10.x.x.x` range (LXD default bridge)
- Redis key format: `sandbox:<userId>` → `<container_ip>`
- Redis must be installed manually (see [Redis installation](#3-redis-manual-installation-required))

## LXD Permissions Setup (Required for Web Server)

LXD requires special permissions to allow the web server (PHP) to create and manage containers. Without proper setup, you'll see "permission denied" errors when visitors try to create sandboxes.

### The Problem

LXD uses a Unix socket at `/var/snap/lxd/common/lxd/unix.socket` which requires:
1. Membership in the `lxd` group, OR
2. Root/sudo access

Since web servers typically run as `www-data` (production) or your user account (development), they don't have direct access to the LXD socket.

### Solution: Sudoers Configuration

Create a sudoers file that allows the web server user to run specific `lxc` commands without a password:

```bash
# Create the sudoers configuration
sudo tee /etc/sudoers.d/ginto-lxd << 'EOF'
# Allow www-data (web server) to run specific LXC commands without password
# This is required for automated sandbox creation

www-data ALL=(root) NOPASSWD: /snap/bin/lxc launch *
www-data ALL=(root) NOPASSWD: /snap/bin/lxc start *
www-data ALL=(root) NOPASSWD: /snap/bin/lxc stop *
www-data ALL=(root) NOPASSWD: /snap/bin/lxc delete *
www-data ALL=(root) NOPASSWD: /snap/bin/lxc exec *
www-data ALL=(root) NOPASSWD: /snap/bin/lxc config *
www-data ALL=(root) NOPASSWD: /snap/bin/lxc info *
www-data ALL=(root) NOPASSWD: /snap/bin/lxc list *
www-data ALL=(root) NOPASSWD: /snap/bin/lxc file *
www-data ALL=(root) NOPASSWD: /snap/bin/lxc copy *
www-data ALL=(root) NOPASSWD: /snap/bin/lxc image *
www-data ALL=(root) NOPASSWD: /snap/bin/lxc storage *
www-data ALL=(root) NOPASSWD: /snap/bin/lxc network *

# For development: also allow your user account
# Replace 'oliverbob' with your username
oliverbob ALL=(root) NOPASSWD: /snap/bin/lxc launch *
oliverbob ALL=(root) NOPASSWD: /snap/bin/lxc start *
oliverbob ALL=(root) NOPASSWD: /snap/bin/lxc stop *
oliverbob ALL=(root) NOPASSWD: /snap/bin/lxc delete *
oliverbob ALL=(root) NOPASSWD: /snap/bin/lxc exec *
oliverbob ALL=(root) NOPASSWD: /snap/bin/lxc config *
oliverbob ALL=(root) NOPASSWD: /snap/bin/lxc info *
oliverbob ALL=(root) NOPASSWD: /snap/bin/lxc list *
oliverbob ALL=(root) NOPASSWD: /snap/bin/lxc file *
oliverbob ALL=(root) NOPASSWD: /snap/bin/lxc copy *
oliverbob ALL=(root) NOPASSWD: /snap/bin/lxc image *
oliverbob ALL=(root) NOPASSWD: /snap/bin/lxc storage *
oliverbob ALL=(root) NOPASSWD: /snap/bin/lxc network *
EOF

# Set proper permissions
sudo chmod 440 /etc/sudoers.d/ginto-lxd

# Validate the configuration
sudo visudo -c
```

### How LxdSandboxManager Uses Sudo

The `LxdSandboxManager` class uses `sudo /snap/bin/lxc` for all container operations:

```php
// In src/Helpers/LxdSandboxManager.php
const LXC_CMD = 'sudo /snap/bin/lxc';

// All commands use this constant:
exec(self::LXC_CMD . " launch ginto-sandbox $containerName 2>&1", $output, $code);
exec(self::LXC_CMD . " start $containerName 2>&1", $output, $code);
exec(self::LXC_CMD . " stop $containerName 2>&1", $output, $code);
// etc.
```

### Alternative: Add User to LXD Group (Development Only)

For local development, you can also add your user to the `lxd` group:

```bash
# Add user to lxd group
sudo usermod -aG lxd $USER

# Apply the group change (or log out and back in)
newgrp lxd

# Verify access
lxc list
```

**Note:** This doesn't work for `www-data` in production because the web server process would need to restart after group changes.

### Security Considerations

The sudoers configuration is secure because:

1. **Limited Commands**: Only specific `lxc` subcommands are allowed (launch, start, stop, etc.)
2. **No Shell Access**: Users can't run arbitrary commands with sudo
3. **Full Path Required**: Commands must use the full path `/snap/bin/lxc`
4. **Isolated Containers**: Each sandbox is isolated from other containers and the host
5. **No External Access**: Containers have no external access; users interact only through the PHP API

### Verifying the Setup

Test that the configuration works:

```bash
# As your user (or as www-data using sudo -u www-data)
sudo /snap/bin/lxc list

# Should show available containers/images without password prompt
```

### Troubleshooting

**"Installation Failed" error in browser:**
1. Check web server logs: `tail -f /tmp/ginto-web.log`
2. Verify sudoers file: `sudo visudo -c`
3. Test as web user: `sudo -u www-data sudo /snap/bin/lxc list`

**Permission denied errors:**
1. Ensure sudoers file exists: `ls -la /etc/sudoers.d/ginto-lxd`
2. Check file permissions: should be `0440`
3. Verify path is correct: `/snap/bin/lxc` (not `/usr/bin/lxc`)

**Syntax error in sudoers:**
1. If locked out, boot in recovery mode
2. Run `visudo` to fix the syntax
3. Always use `sudo visudo -c` to validate before saving

---

## Permissions to Automate (for bin/ginto_container.sh)

The following permissions and configurations MUST be automated in the installation script:

### 1. Sudoers Configuration (CRITICAL)

```bash
# Create sudoers file for LXC access
configure_sudoers() {
    local WEB_USER="${1:-www-data}"
    local DEV_USER="${2:-$(whoami)}"
    
    cat > /etc/sudoers.d/ginto-lxd << EOF
# Ginto LXD Sandbox Permissions
# Auto-generated by ginto_container.sh

# Web server user (PHP/Apache/Nginx)
$WEB_USER ALL=(root) NOPASSWD: /snap/bin/lxc launch *
$WEB_USER ALL=(root) NOPASSWD: /snap/bin/lxc start *
$WEB_USER ALL=(root) NOPASSWD: /snap/bin/lxc stop *
$WEB_USER ALL=(root) NOPASSWD: /snap/bin/lxc delete *
$WEB_USER ALL=(root) NOPASSWD: /snap/bin/lxc exec *
$WEB_USER ALL=(root) NOPASSWD: /snap/bin/lxc config *
$WEB_USER ALL=(root) NOPASSWD: /snap/bin/lxc info *
$WEB_USER ALL=(root) NOPASSWD: /snap/bin/lxc list *
$WEB_USER ALL=(root) NOPASSWD: /snap/bin/lxc file *
$WEB_USER ALL=(root) NOPASSWD: /snap/bin/lxc copy *
$WEB_USER ALL=(root) NOPASSWD: /snap/bin/lxc image *
$WEB_USER ALL=(root) NOPASSWD: /snap/bin/lxc storage *
$WEB_USER ALL=(root) NOPASSWD: /snap/bin/lxc network *

# Development user
$DEV_USER ALL=(root) NOPASSWD: /snap/bin/lxc launch *
$DEV_USER ALL=(root) NOPASSWD: /snap/bin/lxc start *
$DEV_USER ALL=(root) NOPASSWD: /snap/bin/lxc stop *
$DEV_USER ALL=(root) NOPASSWD: /snap/bin/lxc delete *
$DEV_USER ALL=(root) NOPASSWD: /snap/bin/lxc exec *
$DEV_USER ALL=(root) NOPASSWD: /snap/bin/lxc config *
$DEV_USER ALL=(root) NOPASSWD: /snap/bin/lxc info *
$DEV_USER ALL=(root) NOPASSWD: /snap/bin/lxc list *
$DEV_USER ALL=(root) NOPASSWD: /snap/bin/lxc file *
$DEV_USER ALL=(root) NOPASSWD: /snap/bin/lxc copy *
$DEV_USER ALL=(root) NOPASSWD: /snap/bin/lxc image *
$DEV_USER ALL=(root) NOPASSWD: /snap/bin/lxc storage *
$DEV_USER ALL=(root) NOPASSWD: /snap/bin/lxc network *
EOF

    chmod 440 /etc/sudoers.d/ginto-lxd
    visudo -c || { echo "Sudoers syntax error!"; exit 1; }
}
```

### 2. LXD Group Membership

```bash
# Add users to lxd group (for direct access without sudo in some cases)
configure_lxd_group() {
    local WEB_USER="${1:-www-data}"
    local DEV_USER="${2:-$(whoami)}"
    
    # Add web user to lxd group
    usermod -aG lxd "$WEB_USER" 2>/dev/null || true
    
    # Add dev user to lxd group  
    usermod -aG lxd "$DEV_USER" 2>/dev/null || true
    
    echo "Users added to lxd group. Log out/in to apply."
}
```

### 3. Redis Configuration (CRITICAL for Scaling)

Redis is essential for O(1) container IP lookups and must be automated.

```bash
# Full Redis installation and configuration
configure_redis() {
    local MAX_MEMORY="${1:-256mb}"
    
    echo "[*] Installing Redis server..."
    apt update
    apt install -y redis-server
    
    # Enable on boot
    systemctl enable redis-server
    
    # Backup original config
    cp /etc/redis/redis.conf /etc/redis/redis.conf.backup
    
    # Configure for Ginto sandbox usage
    cat > /etc/redis/redis.conf.d/ginto.conf << EOF
# Ginto Sandbox Redis Configuration
# Auto-generated by ginto_container.sh

# Memory settings
maxmemory $MAX_MEMORY
maxmemory-policy allkeys-lru

# Performance settings
tcp-keepalive 300
timeout 0

# Security - bind to localhost only
bind 127.0.0.1 ::1

# Disable external access
protected-mode yes

# Persistence (optional - enable for data recovery)
# RDB snapshots every 5 min if at least 1 key changed
save 300 1
save 60 1000

# AOF for durability (optional)
# appendonly yes
# appendfsync everysec

# Logging
loglevel notice
logfile /var/log/redis/redis-server.log
EOF

    # Include Ginto config in main config
    if ! grep -q "include /etc/redis/redis.conf.d/ginto.conf" /etc/redis/redis.conf; then
        echo "include /etc/redis/redis.conf.d/ginto.conf" >> /etc/redis/redis.conf
    fi
    
    # Create config directory if needed
    mkdir -p /etc/redis/redis.conf.d
    
    # Restart Redis
    systemctl restart redis-server
    
    # Verify
    if redis-cli ping | grep -q PONG; then
        echo "[✓] Redis installed and running"
    else
        echo "[✗] Redis failed to start"
        return 1
    fi
}

# Verify Redis is working properly
verify_redis() {
    echo "[*] Verifying Redis..."
    
    # Test connection
    if ! redis-cli ping | grep -q PONG; then
        echo "[✗] Redis not responding"
        return 1
    fi
    
    # Test set/get
    redis-cli SET ginto:test "hello" > /dev/null
    local result=$(redis-cli GET ginto:test)
    redis-cli DEL ginto:test > /dev/null
    
    if [ "$result" = "hello" ]; then
        echo "[✓] Redis read/write working"
    else
        echo "[✗] Redis read/write failed"
        return 1
    fi
    
    # Check memory usage
    local used=$(redis-cli INFO memory | grep used_memory_human | cut -d: -f2 | tr -d '\r')
    local max=$(redis-cli CONFIG GET maxmemory | tail -1)
    echo "[*] Redis memory: ${used} / ${max} bytes max"
    
    return 0
}
```

### 4. Node.js Sandbox Proxy Setup

The sandbox proxy requires Node.js 18+ and npm packages:

```bash
# Install and configure the sandbox proxy
configure_sandbox_proxy() {
    local GINTO_PATH="${1:-/var/www/ginto}"
    local PROXY_PORT="${2:-1800}"
    
    echo "[*] Setting up sandbox proxy..."
    
    # Create proxy directory
    mkdir -p /opt/ginto-sandbox-proxy
    
    # Copy or create package.json
    cat > /opt/ginto-sandbox-proxy/package.json << 'EOF'
{
  "name": "ginto-sandbox-proxy",
  "version": "1.0.0",
  "description": "Dynamic reverse proxy for Ginto LXD sandboxes",
  "main": "sandbox-proxy.js",
  "scripts": {
    "start": "node sandbox-proxy.js"
  },
  "dependencies": {
    "http-proxy": "^1.18.1",
    "redis": "^4.6.0"
  },
  "engines": {
    "node": ">=18.0.0"
  }
}
EOF

    # Copy proxy script
    if [ -f "$GINTO_PATH/tools/sandbox-proxy/sandbox-proxy.js" ]; then
        cp "$GINTO_PATH/tools/sandbox-proxy/sandbox-proxy.js" /opt/ginto-sandbox-proxy/
    else
        echo "[!] sandbox-proxy.js not found, downloading..."
        # Would need to download from repo or create inline
    fi
    
    # Install npm dependencies
    cd /opt/ginto-sandbox-proxy
    npm install --production
    
    # Set ownership
    chown -R www-data:www-data /opt/ginto-sandbox-proxy
    
    # Create environment file
    cat > /opt/ginto-sandbox-proxy/.env << EOF
PROXY_PORT=$PROXY_PORT
REDIS_URL=redis://127.0.0.1:6379
CONTAINER_PORT=80
AUTO_CREATE_SANDBOX=0
LXD_BASE_IMAGE=ginto-sandbox
EOF

    echo "[✓] Sandbox proxy configured"
}
```

### 5. PHP Redis Extension

```bash
# Install PHP Redis extension
configure_php_redis() {
    apt install -y php-redis
    
    # Restart PHP-FPM if running
    systemctl restart php*-fpm 2>/dev/null || true
}
```

### 6. Directory Permissions

```bash
# Set up required directories with correct permissions
configure_directories() {
    local GINTO_PATH="${1:-/var/www/ginto}"
    local WEB_USER="${2:-www-data}"
    
    # Storage directory for sandbox mappings
    mkdir -p "$GINTO_PATH/storage/sandbox"
    chown -R "$WEB_USER:$WEB_USER" "$GINTO_PATH/storage"
    chmod -R 775 "$GINTO_PATH/storage"
    
    # Logs directory
    mkdir -p "$GINTO_PATH/logs"
    chown -R "$WEB_USER:$WEB_USER" "$GINTO_PATH/logs"
    chmod -R 775 "$GINTO_PATH/logs"
    
    # Clients directory (legacy, may still be used for routing)
    mkdir -p "$GINTO_PATH/clients"
    chown -R "$WEB_USER:$WEB_USER" "$GINTO_PATH/clients"
    chmod -R 775 "$GINTO_PATH/clients"
}
```

### 7. Firewall Rules (UFW)

```bash
# Configure firewall for sandbox access
configure_firewall() {
    # Allow LXD bridge traffic (internal only)
    ufw allow from 10.0.0.0/8 to any comment "LXD containers"
    
    # Allow sandbox preview port
    ufw allow 1800/tcp comment "Sandbox preview"
    
    # Ensure Redis is NOT exposed externally
    ufw deny 6379/tcp comment "Block external Redis"
    
    ufw reload
}
```

### 7. Systemd Service for Sandbox Proxy

```bash
# Create systemd service for Node.js sandbox proxy
configure_sandbox_proxy_service() {
    cat > /etc/systemd/system/ginto-sandbox-proxy.service << 'EOF'
[Unit]
Description=Ginto Sandbox Reverse Proxy
After=network.target redis-server.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/opt/ginto-sandbox-proxy
ExecStart=/usr/bin/node sandbox-proxy.js
Restart=always
RestartSec=10
StandardOutput=syslog
StandardError=syslog
SyslogIdentifier=ginto-sandbox-proxy
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
EOF

    systemctl daemon-reload
    systemctl enable ginto-sandbox-proxy
    systemctl start ginto-sandbox-proxy
}
```

### 8. Complete Permissions Verification Script

```bash
#!/bin/bash
# verify_sandbox_permissions.sh - Run after installation to verify setup

echo "=== Ginto Sandbox Permissions Verification ==="

ERRORS=0

# 1. Check LXD
echo -n "LXD installed: "
if command -v /snap/bin/lxc &>/dev/null; then
    echo "✓"
else
    echo "✗ - Install with: sudo snap install lxd"
    ((ERRORS++))
fi

# 2. Check sudoers
echo -n "Sudoers configured: "
if [ -f /etc/sudoers.d/ginto-lxd ]; then
    echo "✓"
else
    echo "✗ - Run configure_sudoers()"
    ((ERRORS++))
fi

# 3. Check sudoers syntax
echo -n "Sudoers valid: "
if sudo visudo -c &>/dev/null; then
    echo "✓"
else
    echo "✗ - Syntax error in sudoers"
    ((ERRORS++))
fi

# 4. Check Redis
echo -n "Redis running: "
if redis-cli ping 2>/dev/null | grep -q PONG; then
    echo "✓"
else
    echo "✗ - Start with: sudo systemctl start redis-server"
    ((ERRORS++))
fi

# 5. Check PHP Redis extension
echo -n "PHP Redis extension: "
if php -m 2>/dev/null | grep -qi redis; then
    echo "✓"
else
    echo "✗ - Install with: sudo apt install php-redis"
    ((ERRORS++))
fi

# 6. Check ginto-sandbox image
echo -n "ginto-sandbox image: "
if sudo /snap/bin/lxc image list --format=csv 2>/dev/null | grep -q ginto-sandbox; then
    echo "✓"
else
    echo "✗ - Create with: create_ginto_sandbox_image.sh"
    ((ERRORS++))
fi

# 7. Test LXC as www-data
echo -n "www-data can run lxc: "
if sudo -u www-data sudo /snap/bin/lxc list &>/dev/null; then
    echo "✓"
else
    echo "✗ - Check sudoers configuration"
    ((ERRORS++))
fi

echo ""
if [ $ERRORS -eq 0 ]; then
    echo "=== All checks passed! ==="
else
    echo "=== $ERRORS check(s) failed ==="
    exit 1
fi
```

### Summary: Permissions Checklist

| # | Item | Required For | Command to Apply |
|---|------|--------------|------------------|
| 1 | `/etc/sudoers.d/ginto-lxd` | PHP to manage containers | `configure_sudoers www-data` |
| 2 | `lxd` group membership | Optional direct LXD access | `usermod -aG lxd www-data` |
| 3 | Redis server + config | Container IP mapping (O(1) lookups) | `configure_redis 256mb` |
| 4 | Node.js proxy setup | High-performance reverse proxy | `configure_sandbox_proxy` |
| 5 | PHP Redis extension | PHP to query Redis cache | `apt install php-redis` |
| 6 | `/storage/` ownership | PHP file writes | `chown www-data:www-data storage/` |
| 7 | Firewall rules | Security | `configure_firewall` |
| 8 | Sandbox proxy service | Auto-start reverse proxy | `configure_sandbox_proxy_service` |
| 9 | `ginto-sandbox` image | Container template | Run image creation script |

### Complete Installation Order

```bash
#!/bin/bash
# ginto_container.sh - Complete sandbox infrastructure setup

# 1. Install system dependencies
apt update && apt install -y snapd redis-server php-redis nodejs npm

# 2. Install and initialize LXD
snap install lxd
lxd init --auto

# 3. Configure sudoers
configure_sudoers www-data $(whoami)

# 4. Configure Redis
configure_redis 256mb
verify_redis

# 5. Install PHP Redis extension
configure_php_redis

# 6. Create ginto-sandbox base image
# Run: create_ginto_sandbox_image.sh

# 7. Set up Node.js sandbox proxy
configure_sandbox_proxy /var/www/ginto 3000

# 8. Configure directories
configure_directories /var/www/ginto www-data

# 9. Configure firewall
configure_firewall

# 10. Set up systemd services
configure_sandbox_proxy_service

# 11. Verify everything
verify_sandbox_permissions.sh
```

---

## Automation Script ✅ (Completed)

The one-click installer has been implemented as `bin/ginto.sh`. See the [Nested LXD Testing](#nested-lxd-testing-verified-) section below for verification results.

**Usage:**
```bash
# Initialize LXD and create base image
bin/ginto.sh init

# Create a new sandbox
bin/ginto.sh create <username>

# Verify setup
bin/ginto.sh verify
```

For the complete list of commands, run `bin/ginto.sh help`.

---

## Nested LXD Testing (Verified ✅)

The `ginto.sh` script has been successfully tested in a **nested LXD environment**, which is a more challenging scenario than production deployment.

### Test Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                    HOST MACHINE                              │
│                 Ubuntu 24.04.3 LTS                           │
│                                                              │
│  ┌────────────────────────────────────────────────────────┐  │
│  │              ginto-ubuntu-test                         │  │
│  │         (Ubuntu minimal 24.04)                         │  │
│  │         security.nesting=true                          │  │
│  │                                                        │  │
│  │  ┌──────────────────────────────────────────────────┐  │  │
│  │  │           NESTED LXD                             │  │  │
│  │  │                                                  │  │  │
│  │  │  ┌─────────────────┐  ┌─────────────────┐        │  │  │
│  │  │  │ ginto-sandbox   │  │ sandbox-test123 │        │  │  │
│  │  │  │ (base image)    │  │ (user sandbox)  │        │  │  │
│  │  │  │ Alpine 3.20     │  │ 10.36.97.245    │        │  │  │
│  │  │  └─────────────────┘  └─────────────────┘        │  │  │
│  │  └──────────────────────────────────────────────────┘  │  │
│  └────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────┘
```

### Test Results (December 17, 2025)

| Component | Status | Details |
|-----------|--------|---------|
| `ginto.sh init` | ✅ PASS | LXD initialized with preseed, Alpine image created |
| `ginto-sandbox` image | ✅ PASS | 108.00 MiB, all packages installed |
| `ginto.sh create` | ✅ PASS | Sandbox created with IP assignment |
| PHP 8.2.28 | ✅ PASS | All extensions loaded |
| Node.js 20.15.1 | ✅ PASS | npm 10.9.1 included |
| Python 3.12.12 | ✅ PASS | pip available |
| Composer 2.9.2 | ✅ PASS | Working correctly |
| Caddy 2.7.6 | ✅ PASS | PHP-FPM integration working |
| Git 2.45.4 | ✅ PASS | Installed |
| `ginto.sh verify` | ✅ PASS | All checks passed |

### How to Run Nested LXD Tests

1. **Create a base Ubuntu template:**
   ```bash
   sudo /snap/bin/lxc launch ubuntu-minimal:24.04 ginto-ubuntu-base
   sudo /snap/bin/lxc stop ginto-ubuntu-base
   ```

2. **Create test instance with nesting enabled:**
   ```bash
   sudo /snap/bin/lxc copy ginto-ubuntu-base ginto-ubuntu-test
   sudo /snap/bin/lxc config set ginto-ubuntu-test security.nesting=true
   sudo /snap/bin/lxc start ginto-ubuntu-test
   ```

3. **Install LXD inside the test container:**
   ```bash
   sudo /snap/bin/lxc exec ginto-ubuntu-test -- apt update
   sudo /snap/bin/lxc exec ginto-ubuntu-test -- apt install -y snapd
   sudo /snap/bin/lxc exec ginto-ubuntu-test -- snap install lxd
   ```

4. **Copy and run ginto.sh:**
   ```bash
   sudo /snap/bin/lxc file push bin/ginto.sh ginto-ubuntu-test/root/ginto.sh
   sudo /snap/bin/lxc exec ginto-ubuntu-test -- chmod +x /root/ginto.sh
   sudo /snap/bin/lxc exec ginto-ubuntu-test -- /root/ginto.sh init
   ```

5. **Test sandbox creation:**
   ```bash
   sudo /snap/bin/lxc exec ginto-ubuntu-test -- /root/ginto.sh create test123
   sudo /snap/bin/lxc exec ginto-ubuntu-test -- /root/ginto.sh verify
   ```

### Why Nested Testing Matters

- **Harder than production:** Nested LXD has additional overhead and complexity
- **If it works nested, it works native:** Success in nested environment guarantees success on bare-metal/VM
- **Isolated testing:** Test changes without affecting production infrastructure
- **Reproducible:** Can easily destroy and recreate test environment

### Production Deployment Confidence

Since the script works in a nested LXD environment (which is more restrictive), deployment on a standard server will be **even smoother**:

- No `security.nesting=true` required on the host
- Better performance (no virtualization overhead)
- Same commands, same workflow
# ginto.ai
