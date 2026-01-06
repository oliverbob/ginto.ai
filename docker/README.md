# 🐳 Ginto AI Docker Installation

This directory contains the Docker configuration for running Ginto AI in containers.

## Quick Start

```bash
# Clone the repository
git clone https://github.com/oliverbob/ginto.ai.git
cd ginto.ai

# Copy environment file and configure
cp docker/.env.example .env
nano .env  # Add your API keys

# Start all services
docker-compose up -d

# View logs
docker-compose logs -f

# Access the application
open http://localhost
```

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         Docker Network                          │
│                        (172.28.0.0/16)                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐          │
│  │   Caddy     │    │   PHP-FPM   │    │  WebSocket  │          │
│  │   :80/443   │───▶│    :9000    │    │    :8080    │          │
│  │             │    │  (Ginto AI) │    │  (Ratchet)  │          │
│  └─────────────┘    └─────────────┘    └─────────────┘          │
│         │                  │                  │                 │
│         │                  ▼                  │                 │
│         │           ┌─────────────┐           │                 │
│         │           │  MariaDB    │           │                 │
│         │           │    :3306    │───────────┘                 │
│         │           └─────────────┘                             │
│         │                  │                                    │
│         │                  ▼                                    │
│         │           ┌─────────────┐                             │
│         │           │    Redis    │                             │
│         │           │    :6379    │                             │
│         │           └─────────────┘                             │
│         │                  │                                    │
│         ▼                  ▼                                    │
│  ┌─────────────┐    ┌─────────────┐                             │
│  │Sandbox Proxy│    │Terminal Svr │                             │
│  │    :3000    │    │    :3001    │                             │
│  └─────────────┘    └─────────────┘                             │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

## Services

| Service | Port | Description |
|---------|------|-------------|
| `caddy` | 80, 443 | Web server with automatic HTTPS |
| `php` | 9000 | PHP-FPM application server |
| `websocket` | 8080 | Ratchet WebSocket for streaming |
| `mariadb` | 3306 | MariaDB database |
| `redis` | 6379 | Redis cache |
| `sandbox-proxy` | 3000 | Node.js reverse proxy for sandboxes |
| `terminal-server` | 3001 | WebSocket PTY terminal server |

## Environment Variables

Copy `docker/.env.example` to `.env` in the project root:

```bash
cp docker/.env.example .env
```

### Required Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `DB_NAME` | Database name | `ginto` |
| `DB_USER` | Database user | `ginto` |
| `DB_PASS` | Database password | `secret` |
| `DB_ROOT_PASS` | MariaDB root password | `rootsecret` |

### API Keys (at least one required)

| Variable | Description |
|----------|-------------|
| `GROQ_API_KEY` | Groq API key |
| `CEREBRAS_API_KEY` | Cerebras API key |
| `OPENAI_API_KEY` | OpenAI API key |
| `ANTHROPIC_API_KEY` | Anthropic API key |

### Optional Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `HTTP_PORT` | HTTP port | `80` |
| `HTTPS_PORT` | HTTPS port | `443` |
| `CADDY_DOMAIN` | Domain for HTTPS | `localhost` |
| `TLS_EMAIL` | Email for Let's Encrypt | (empty) |
| `APP_ENV` | Application environment | `production` |
| `APP_DEBUG` | Enable debug mode | `false` |

## Commands

### Start Services

```bash
# Start all services in background
docker-compose up -d

# Start with rebuild
docker-compose up -d --build

# Start specific service
docker-compose up -d php mariadb redis
```

### Stop Services

```bash
# Stop all services
docker-compose down

# Stop and remove volumes (deletes data!)
docker-compose down -v
```

### View Logs

```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f php
docker-compose logs -f caddy
```

### Execute Commands

```bash
# PHP shell
docker-compose exec php bash

# Composer install
docker-compose exec php composer install

# Database shell
docker-compose exec mariadb mysql -u ginto -psecret ginto

# Redis CLI
docker-compose exec redis redis-cli
```

### Health Checks

```bash
# Check container status
docker-compose ps

# Check health status
docker inspect --format='{{.State.Health.Status}}' ginto-php
```

## Production Deployment

### 1. Configure Domain and HTTPS

Edit `docker/caddy/Caddyfile` and uncomment the HTTPS section:

```caddyfile
{$CADDY_DOMAIN:yourdomain.com} {
    tls {$TLS_EMAIL:admin@yourdomain.com}
    # ... rest of config
}
```

### 2. Set Environment Variables

```bash
# .env
CADDY_DOMAIN=yourdomain.com
TLS_EMAIL=admin@yourdomain.com
APP_ENV=production
APP_DEBUG=false
```

### 3. Use Secure Passwords

```bash
# Generate secure passwords
DB_PASS=$(openssl rand -base64 32)
DB_ROOT_PASS=$(openssl rand -base64 32)
```

### 4. Deploy

```bash
docker-compose up -d --build
```

## Sandbox Support (Optional)

Docker mode supports **Docker-based sandboxes** that provide the same functionality as LXD sandboxes but work on any platform with Docker.

### Building the Sandbox Image

Before using Docker sandboxes, build the base image:

```bash
docker build -t ginto/sandbox:latest -f docker/sandbox/Dockerfile docker/sandbox/
```

### Sandbox Configuration

The sandbox system is configured via environment variables in `.env`:

```env
# Sandbox mode: 'docker', 'lxd', or 'auto'
SANDBOX_MODE=docker

# Docker network subnet for sandboxes (deterministic IP allocation)
DOCKER_SANDBOX_SUBNET=172.30.0.0/16

# Secret key for IP permutation (change in production)
IP_PERMUTATION_KEY=your-secret-key-here
```

### How Docker Sandboxes Work

1. **Sibling Containers**: Sandboxes run as sibling containers to the main PHP container
2. **Docker Socket**: The PHP container mounts `/var/run/docker.sock` to manage sandbox containers
3. **Deterministic IPs**: Same Feistel permutation algorithm as LXD for collision-free IP allocation
4. **Isolated Network**: Sandboxes run on a separate Docker network (`ginto-sandbox`)

### Sandbox Container Features

Each sandbox container includes:
- **PHP 8.3** with common extensions
- **Node.js 20** with npm
- **Python 3.12** with pip
- **Composer** for PHP dependencies
- **Git** for version control
- **Caddy** for web preview (port 8080)
- **Supervisor** for process management

### Using the Unified Sandbox API

The `UnifiedSandbox` class provides a consistent API regardless of backend:

```php
use Ginto\Helpers\UnifiedSandbox;

// Check which backend is active
$info = UnifiedSandbox::getBackendInfo();
// ['backend' => 'docker', 'available' => true, ...]

// Create a sandbox
$result = UnifiedSandbox::create('user-123');

// Execute a command
[$code, $stdout, $stderr] = UnifiedSandbox::exec('user-123', 'php -v');

// Read a file
$file = UnifiedSandbox::readFile('user-123', '/home/sandbox/project/index.php');

// Write a file
UnifiedSandbox::writeFile('user-123', '/home/sandbox/test.php', '<?php echo "Hello";');

// Get sandbox IP
$ip = UnifiedSandbox::getIp('user-123');

// List all sandboxes
$sandboxes = UnifiedSandbox::listSandboxes();

// Delete a sandbox
UnifiedSandbox::delete('user-123');
```

### Alternative: Host LXD

If running on Linux with LXD installed on the host:

```yaml
# docker-compose.override.yml
services:
  php:
    environment:
      - SANDBOX_MODE=lxd
    volumes:
      - /var/snap/lxd/common/lxd/unix.socket:/var/snap/lxd/common/lxd/unix.socket
```

## Troubleshooting

### Container won't start

```bash
# Check logs
docker-compose logs php

# Rebuild without cache
docker-compose build --no-cache php
```

### Database connection issues

```bash
# Check if MariaDB is ready
docker-compose exec mariadb mysqladmin ping -h localhost

# Check PHP can connect
docker-compose exec php php -r "new PDO('mysql:host=mariadb', 'ginto', 'secret');"
```

### Permission issues

```bash
# Fix storage permissions
docker-compose exec php chown -R www-data:www-data /var/www/storage
```

### Clear all data and restart

```bash
docker-compose down -v
docker-compose up -d --build
```

## Development Mode

For development with hot-reload:

```bash
# Use development compose file
docker-compose -f docker-compose.yml -f docker-compose.dev.yml up -d
```

Create `docker-compose.dev.yml`:

```yaml
version: "3.8"
services:
  php:
    environment:
      - APP_ENV=development
      - APP_DEBUG=true
    volumes:
      - .:/var/www/html:cached
```

## License

MIT License - see [LICENSE](../LICENSE) for details.
