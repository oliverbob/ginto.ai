#!/bin/bash
# PHP Entrypoint for Ginto AI Docker
# Handles composer install, migrations, and startup

set -e

echo "🐘 Ginto AI PHP Container Starting..."

# Wait for MariaDB to be ready
wait_for_db() {
    echo "⏳ Waiting for MariaDB to be ready..."
    local max_attempts=30
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if php -r "
            \$pdo = new PDO(
                'mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '3306'),
                getenv('DB_USER'),
                getenv('DB_PASS'),
                [PDO::ATTR_TIMEOUT => 5]
            );
        " 2>/dev/null; then
            echo "✅ MariaDB is ready!"
            return 0
        fi
        
        echo "   Attempt $attempt/$max_attempts - MariaDB not ready yet..."
        sleep 2
        attempt=$((attempt + 1))
    done
    
    echo "❌ MariaDB did not become ready in time"
    return 1
}

# Run composer install if vendor doesn't exist
run_composer() {
    if [ ! -d "/var/www/html/vendor" ] || [ ! -f "/var/www/html/vendor/autoload.php" ]; then
        echo "📦 Running composer install..."
        cd /var/www/html
        composer install --no-dev --optimize-autoloader --no-interaction
        echo "✅ Composer dependencies installed"
    else
        echo "✅ Composer dependencies already installed"
    fi
}

# Run database migrations
run_migrations() {
    echo "🔄 Checking database migrations..."
    cd /var/www/html
    
    # Check if database exists, create if not
    php -r "
        \$host = getenv('DB_HOST') ?: 'mariadb';
        \$port = getenv('DB_PORT') ?: '3306';
        \$user = getenv('DB_USER') ?: 'ginto';
        \$pass = getenv('DB_PASS') ?: 'secret';
        \$name = getenv('DB_NAME') ?: 'ginto';
        
        try {
            \$pdo = new PDO(\"mysql:host=\$host;port=\$port\", \$user, \$pass);
            \$pdo->exec(\"CREATE DATABASE IF NOT EXISTS \$name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci\");
            echo \"✅ Database '\$name' ready\\n\";
        } catch (PDOException \$e) {
            echo \"⚠️ Database check warning: \" . \$e->getMessage() . \"\\n\";
        }
    "
    
    # Run migrations if available
    if [ -d "/var/www/html/database/migrations" ]; then
        echo "🔄 Running migrations..."
        for migration in /var/www/html/database/migrations/*.sql; do
            if [ -f "$migration" ]; then
                echo "   Applying: $(basename $migration)"
                mysql -h"${DB_HOST:-mariadb}" -u"${DB_USER:-ginto}" -p"${DB_PASS:-secret}" "${DB_NAME:-ginto}" < "$migration" 2>/dev/null || true
            fi
        done
        echo "✅ Migrations complete"
    fi
}

# Create storage directories
setup_storage() {
    echo "📁 Setting up storage directories..."
    mkdir -p /var/www/storage/logs 2>/dev/null || true
    mkdir -p /var/www/storage/cache 2>/dev/null || true
    mkdir -p /var/www/storage/sessions 2>/dev/null || true
    
    # Try to fix ownership (may fail on bind mounts owned by host user - that's OK)
    chown -R www-data:www-data /var/www/storage 2>/dev/null || true
    
    # Try to fix permissions (may fail on bind mounts - that's OK if they're already writable)
    chmod -R 775 /var/www/storage 2>/dev/null || true
    chmod 1777 /var/www/storage/sessions 2>/dev/null || true
    
    # Explicitly fix session files permissions
    find /var/www/storage/sessions -name 'sess_*' -exec chmod 600 {} \; 2>/dev/null || true
    
    echo "✅ Storage directories ready"
}

# Setup Lightpanda browser for web search
setup_lightpanda() {
    echo "🐼 Setting up Lightpanda browser..."
    
    LIGHTPANDA_DIR="/root/.cache/lightpanda-node"
    LIGHTPANDA_BIN="$LIGHTPANDA_DIR/lightpanda"
    WWW_CACHE="/var/www/.cache/lightpanda-node"
    
    # Check if Lightpanda is already installed
    if [ -x "$LIGHTPANDA_BIN" ]; then
        echo "✅ Lightpanda already installed"
    else
        # Install Lightpanda browser
        if [ -d "/var/www/html/tools/lightpanda-mcp" ] && command -v node >/dev/null 2>&1; then
            echo "   Downloading Lightpanda browser..."
            cd /var/www/html/tools/lightpanda-mcp
            npx @lightpanda/browser upgrade 2>/dev/null || echo "   ⚠️ Lightpanda download failed (will retry on next restart)"
            cd /var/www/html
        else
            echo "   ⚠️ Skipping Lightpanda setup (node not found or lightpanda-mcp missing)"
        fi
    fi
    
    # Fix permissions so www-data can access the binary
    if [ -d "$LIGHTPANDA_DIR" ]; then
        chmod 755 /root 2>/dev/null || true
        chmod 755 /root/.cache 2>/dev/null || true
        chmod 755 "$LIGHTPANDA_DIR" 2>/dev/null || true
        chmod 755 "$LIGHTPANDA_BIN" 2>/dev/null || true
        
        # Create symlink for www-data user (Node.js uses homedir()/.cache)
        mkdir -p "$WWW_CACHE"
        ln -sf "$LIGHTPANDA_BIN" "$WWW_CACHE/lightpanda"
        chown -R www-data:www-data /var/www/.cache 2>/dev/null || true
        
        echo "✅ Lightpanda permissions set"
    fi
}

# Generate .env if not exists
setup_env() {
    if [ ! -f "/var/www/html/.env" ]; then
        echo "📝 Creating .env from environment variables..."
        cat > /var/www/html/.env << EOF
# Generated by Docker entrypoint
DB_HOST=${DB_HOST:-mariadb}
DB_PORT=${DB_PORT:-3306}
DB_NAME=${DB_NAME:-ginto}
DB_USER=${DB_USER:-ginto}
DB_PASS=${DB_PASS:-secret}

REDIS_HOST=${REDIS_HOST:-redis}
REDIS_PORT=${REDIS_PORT:-6379}

LLM_PROVIDER=${LLM_PROVIDER:-}
GROQ_API_KEY=${GROQ_API_KEY:-}
CEREBRAS_API_KEY=${CEREBRAS_API_KEY:-}
OPENAI_API_KEY=${OPENAI_API_KEY:-}
ANTHROPIC_API_KEY=${ANTHROPIC_API_KEY:-}

APP_ENV=${APP_ENV:-production}
APP_DEBUG=${APP_DEBUG:-false}
EOF
        echo "✅ .env file created"
    else
        echo "✅ .env file already exists"
    fi
}

# Main entrypoint logic
main() {
    setup_storage
    setup_env
    
    if [ "${SKIP_DB_WAIT:-false}" != "true" ]; then
        wait_for_db
    fi
    
    run_composer
    
    if [ "${RUN_MIGRATIONS:-true}" == "true" ]; then
        run_migrations
    fi
    
    setup_lightpanda
    
    echo ""
    echo "🚀 Ginto AI PHP Container Ready!"
    echo "   PHP Version: $(php -v | head -n 1)"
    echo ""
    
    # Execute the main command
    exec "$@"
}

main "$@"
