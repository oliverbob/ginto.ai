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

# Create standard directory structure (matching LXD sandbox)
mkdir -p /home/sandbox/.config
mkdir -p /home/sandbox/.local
mkdir -p /home/sandbox/.pki
mkdir -p /home/sandbox/Desktop
mkdir -p /home/sandbox/Documents
mkdir -p /home/sandbox/Downloads
mkdir -p /home/sandbox/Music
mkdir -p /home/sandbox/Pictures
mkdir -p /home/sandbox/Videos
mkdir -p /home/sandbox/Websites
mkdir -p /home/sandbox/project

# Setup Python virtual environment activation
if [ -d "/home/sandbox/.venv" ]; then
    export PATH="/home/sandbox/.venv/bin:$PATH"
fi

# Create welcome index.php if it doesn't exist
if [ ! -f "/home/sandbox/index.php" ]; then
    cat > /home/sandbox/index.php << 'PHPEOF'
<?php
/**
 * Welcome to your Ginto Sandbox!
 *
 * This is your personal development environment.
 * Edit this file or create new ones to get started.
 */

$tools = [
    "PHP" => phpversion(),
    "Node.js" => trim(shell_exec("/usr/bin/node --version 2>/dev/null") ?: "N/A"),
    "npm" => trim(shell_exec("/usr/bin/npm --version 2>/dev/null") ?: "N/A"),
    "Python" => trim(shell_exec("/usr/bin/python3 --version 2>/dev/null") ?: "N/A"),
    "Git" => trim(shell_exec("/usr/bin/git --version 2>/dev/null") ?: "N/A"),
    "Composer" => trim(shell_exec("/usr/local/bin/composer --version 2>/dev/null | head -1") ?: "N/A"),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ginto Sandbox</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-indigo-900 via-purple-900 to-pink-800 min-h-screen">
    <div class="container mx-auto px-4 py-16">
        <div class="text-center mb-12">
            <h1 class="text-5xl font-bold mb-4">🚀 Welcome to Ginto Sandbox</h1>
            <p class="text-xl text-purple-200">Your personal development environment</p>
        </div>

        <div class="max-w-4xl mx-auto">
            <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-8 mb-8">
                <h2 class="text-2xl font-bold mb-6 text-white">Available Tools</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <?php foreach ($tools as $name => $version): ?>
                    <div class="bg-white/5 rounded-lg p-4">
                        <div class="font-semibold text-purple-300"><?= htmlspecialchars($name) ?></div>
                        <div class="text-sm text-gray-300"><?= htmlspecialchars($version) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
PHPEOF
    chown sandbox:sandbox /home/sandbox/index.php
fi

# Create README.md if it doesn't exist
if [ ! -f "/home/sandbox/README.md" ]; then
    cat > /home/sandbox/README.md << 'MDEOF'
# Ginto Sandbox

Welcome to your personal development sandbox!

## Getting Started

This is your isolated environment where you can:
- Write and execute code (PHP, Python, Node.js)
- Create and manage files
- Build projects safely

## Directory Structure

- `Documents/` - Store your documents
- `Downloads/` - Downloaded files
- `Websites/` - Web projects
- `project/` - Main project directory

## Available Tools

- PHP 8.3 with Composer
- Node.js 20 with npm
- Python 3.12 with pip
- Git for version control

Happy coding! 🚀
MDEOF
    chown sandbox:sandbox /home/sandbox/README.md
fi

# Ensure all files owned by sandbox user
chown -R sandbox:sandbox /home/sandbox

echo "✅ Sandbox environment ready"
echo ""

# Execute the main command
exec "$@"
