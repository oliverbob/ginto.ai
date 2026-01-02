#!/bin/sh
# Ginto AI Sandbox Container Entrypoint
# Initializes the sandbox environment for code execution

set -e

echo "🔒 Ginto AI Sandbox Starting..."

# Display environment info
echo "   Sandbox ID: ${SANDBOX_ID:-unknown}"
echo "   Sandbox IP: ${SANDBOX_IP:-auto}"

# Ensure proper ownership of sandbox home
chown -R sandbox:sandbox /home/sandbox 2>/dev/null || true

# Create project directory if it doesn't exist
mkdir -p /home/sandbox/project
chown sandbox:sandbox /home/sandbox/project

# Setup Python virtual environment activation
if [ -d "/home/sandbox/.venv" ]; then
    export PATH="/home/sandbox/.venv/bin:$PATH"
fi

# Initialize composer.json if project is empty
if [ ! -f "/home/sandbox/project/composer.json" ] && [ -z "$(ls -A /home/sandbox/project 2>/dev/null)" ]; then
    cat > /home/sandbox/project/composer.json << 'EOF'
{
    "name": "ginto/sandbox",
    "description": "Ginto AI Sandbox Project",
    "type": "project",
    "require": {},
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    }
}
EOF
    mkdir -p /home/sandbox/project/src
    chown -R sandbox:sandbox /home/sandbox/project
fi

# Create index.php for web preview
if [ ! -f "/home/sandbox/project/index.php" ]; then
    cat > /home/sandbox/project/index.php << 'EOF'
<?php
// Ginto AI Sandbox - Default index
echo "<!DOCTYPE html>\n";
echo "<html><head><title>Sandbox</title></head>\n";
echo "<body style='font-family: system-ui; padding: 2rem;'>\n";
echo "<h1>🔒 Ginto AI Sandbox</h1>\n";
echo "<p>Your sandbox is ready for development!</p>\n";
echo "<p>PHP Version: " . phpversion() . "</p>\n";
echo "</body></html>\n";
EOF
    chown sandbox:sandbox /home/sandbox/project/index.php
fi

echo "✅ Sandbox environment ready"
echo ""

# Execute the main command
exec "$@"
