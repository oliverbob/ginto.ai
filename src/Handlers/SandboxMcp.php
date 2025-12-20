<?php

declare(strict_types=1);

namespace App\Handlers;

use PhpMcp\Server\Attributes\McpTool;
use Ginto\Helpers\LxdSandboxManager;

/**
 * Sandbox File MCP Tools
 * 
 * Provides MCP tools for agents to interact with user sandbox containers.
 * All file operations are scoped to /home/ inside the LXD container.
 * 
 * The sandbox_id is obtained from the session context, ensuring users
 * can only access their own sandbox.
 * 
 * Security:
 * - All paths are sanitized to prevent traversal attacks
 * - Operations are confined to the user's LXD container
 * - Sandbox must exist and be running for operations to succeed
 */
final class SandboxMcp
{
    /**
     * Get the sandbox ID from session or provided parameter.
     * Falls back to session if not explicitly provided.
     */
    private static function getSandboxId(?string $sandboxId = null): ?string
    {
        if ($sandboxId && !empty(trim($sandboxId))) {
            return trim($sandboxId);
        }
        
        // Try to get from session
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        
        return $_SESSION['sandbox_id'] ?? null;
    }

    /**
     * Validate sandbox exists and is accessible
     */
    private static function validateSandbox(?string $sandboxId): array
    {
        if (empty($sandboxId)) {
            return ['valid' => false, 'error' => 'No sandbox ID available. Please create a sandbox first.'];
        }
        
        if (!LxdSandboxManager::sandboxExists($sandboxId)) {
            return ['valid' => false, 'error' => 'Sandbox does not exist. Please create a sandbox first.'];
        }
        
        // Ensure sandbox is running
        if (!LxdSandboxManager::sandboxRunning($sandboxId)) {
            LxdSandboxManager::ensureSandboxRunning($sandboxId);
            usleep(500000); // Wait 0.5s for container to start
            
            if (!LxdSandboxManager::sandboxRunning($sandboxId)) {
                return ['valid' => false, 'error' => 'Failed to start sandbox container.'];
            }
        }
        
        return ['valid' => true, 'error' => null];
    }

    // =========================================================================
    // FILE OPERATIONS
    // =========================================================================

    #[McpTool(
        name: 'sandbox_list_files',
        description: 'List files and directories in the user\'s sandbox. Returns a tree structure of files and folders. The sandbox home directory is /home/. Use this to explore what files exist in the sandbox before reading or modifying them.'
    )]
    public function listFiles(
        ?string $path = '',
        ?int $maxDepth = 5,
        ?string $sandbox_id = null
    ): array {
        $sandboxId = self::getSandboxId($sandbox_id);
        $validation = self::validateSandbox($sandboxId);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }
        
        $remotePath = '/home';
        if (!empty($path)) {
            $path = str_replace(['../', '..\\', '..'], '', $path);
            $remotePath = '/home/' . ltrim($path, '/');
        }
        
        $result = LxdSandboxManager::listFiles($sandboxId, $remotePath, $maxDepth ?? 5);
        
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'Failed to list files'];
        }
        
        return [
            'success' => true,
            'path' => $remotePath,
            'tree' => $result['tree'],
            'sandbox_id' => $sandboxId
        ];
    }

    #[McpTool(
        name: 'sandbox_read_file',
        description: 'Read the contents of a file from the user\'s sandbox. The path is relative to /home/ in the sandbox. For example, to read /home/project/index.php, pass "project/index.php" as the path.'
    )]
    public function readFile(
        string $path,
        ?string $sandbox_id = null
    ): array {
        $sandboxId = self::getSandboxId($sandbox_id);
        $validation = self::validateSandbox($sandboxId);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }
        
        if (empty($path)) {
            return ['success' => false, 'error' => 'File path is required'];
        }
        
        $result = LxdSandboxManager::readFile($sandboxId, $path);
        
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'Failed to read file'];
        }
        
        return [
            'success' => true,
            'path' => $path,
            'content' => $result['content'],
            'size' => strlen($result['content'] ?? ''),
            'sandbox_id' => $sandboxId
        ];
    }

    #[McpTool(
        name: 'sandbox_write_file',
        description: 'Write content to a file in the user\'s sandbox. Creates the file if it doesn\'t exist. Creates parent directories automatically. The path is relative to /home/ in the sandbox. For example, to write /home/project/index.php, pass "project/index.php" as the path.'
    )]
    public function writeFile(
        string $path,
        string $content,
        ?string $sandbox_id = null
    ): array {
        $sandboxId = self::getSandboxId($sandbox_id);
        $validation = self::validateSandbox($sandboxId);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }
        
        if (empty($path)) {
            return ['success' => false, 'error' => 'File path is required'];
        }
        
        $result = LxdSandboxManager::writeFile($sandboxId, $path, $content);
        
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'Failed to write file'];
        }
        
        // Build the download/view URL
        $cleanPath = ltrim($path, '/');
        $url = '/clients/' . $cleanPath;
        
        return [
            'success' => true,
            'path' => $path,
            'url' => $url,
            'bytes_written' => $result['bytes'] ?? strlen($content),
            'sandbox_id' => $sandboxId,
            'message' => "File created: $path\nView/Download: $url"
        ];
    }

    #[McpTool(
        name: 'sandbox_create_file',
        description: 'Create a new empty file or directory in the user\'s sandbox. Set type to "folder" to create a directory, or "file" (default) to create an empty file. Parent directories are created automatically.'
    )]
    public function createFile(
        string $path,
        ?string $type = 'file',
        ?string $sandbox_id = null
    ): array {
        $sandboxId = self::getSandboxId($sandbox_id);
        $validation = self::validateSandbox($sandboxId);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }
        
        if (empty($path)) {
            return ['success' => false, 'error' => 'Path is required'];
        }
        
        $itemType = ($type === 'folder' || $type === 'directory') ? 'folder' : 'file';
        $result = LxdSandboxManager::createItem($sandboxId, $path, $itemType);
        
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'Failed to create item'];
        }
        
        return [
            'success' => true,
            'path' => $path,
            'type' => $itemType,
            'sandbox_id' => $sandboxId,
            'message' => ucfirst($itemType) . " created successfully: $path"
        ];
    }

    #[McpTool(
        name: 'sandbox_delete_file',
        description: 'Delete a file or directory from the user\'s sandbox. For directories, this will recursively delete all contents. Use with caution. The path is relative to /home/ in the sandbox.'
    )]
    public function deleteFile(
        string $path,
        ?string $sandbox_id = null
    ): array {
        $sandboxId = self::getSandboxId($sandbox_id);
        $validation = self::validateSandbox($sandboxId);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }
        
        if (empty($path)) {
            return ['success' => false, 'error' => 'Path is required'];
        }
        
        // Extra safety: don't allow deleting root
        $cleanPath = trim($path, '/');
        if (empty($cleanPath) || $cleanPath === '.' || $cleanPath === 'home') {
            return ['success' => false, 'error' => 'Cannot delete root directory'];
        }
        
        $result = LxdSandboxManager::deleteItem($sandboxId, $path);
        
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'Failed to delete item'];
        }
        
        return [
            'success' => true,
            'path' => $path,
            'sandbox_id' => $sandboxId,
            'message' => "Deleted successfully: $path"
        ];
    }

    #[McpTool(
        name: 'sandbox_rename_file',
        description: 'Rename or move a file/directory in the user\'s sandbox. Both paths are relative to /home/ in the sandbox. Can be used to move files between directories.'
    )]
    public function renameFile(
        string $old_path,
        string $new_path,
        ?string $sandbox_id = null
    ): array {
        $sandboxId = self::getSandboxId($sandbox_id);
        $validation = self::validateSandbox($sandboxId);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }
        
        if (empty($old_path) || empty($new_path)) {
            return ['success' => false, 'error' => 'Both old_path and new_path are required'];
        }
        
        $result = LxdSandboxManager::renameItem($sandboxId, $old_path, $new_path);
        
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'Failed to rename item'];
        }
        
        return [
            'success' => true,
            'old_path' => $old_path,
            'new_path' => $new_path,
            'sandbox_id' => $sandboxId,
            'message' => "Renamed/moved successfully: $old_path -> $new_path"
        ];
    }

    #[McpTool(
        name: 'sandbox_copy_file',
        description: 'Copy a file or directory within the user\'s sandbox. Both paths are relative to /home/ in the sandbox. Directories are copied recursively.'
    )]
    public function copyFile(
        string $source_path,
        string $dest_path,
        ?string $sandbox_id = null
    ): array {
        $sandboxId = self::getSandboxId($sandbox_id);
        $validation = self::validateSandbox($sandboxId);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }
        
        if (empty($source_path) || empty($dest_path)) {
            return ['success' => false, 'error' => 'Both source_path and dest_path are required'];
        }
        
        $result = LxdSandboxManager::copyItem($sandboxId, $source_path, $dest_path);
        
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'Failed to copy item'];
        }
        
        return [
            'success' => true,
            'source_path' => $source_path,
            'dest_path' => $dest_path,
            'sandbox_id' => $sandboxId,
            'message' => "Copied successfully: $source_path -> $dest_path"
        ];
    }

    // =========================================================================
    // COMMAND EXECUTION
    // =========================================================================

    #[McpTool(
        name: 'sandbox_exec',
        description: 'Execute a shell command in the user\'s sandbox container. Commands run as a non-root user inside an isolated LXD container. The working directory defaults to /home. Common use cases: running npm/composer install, running scripts, checking installed packages. Returns stdout/stderr and exit code.'
    )]
    public function execCommand(
        string $command,
        ?string $cwd = '/home',
        ?int $timeout = 30,
        ?string $sandbox_id = null
    ): array {
        $sandboxId = self::getSandboxId($sandbox_id);
        $validation = self::validateSandbox($sandboxId);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }
        
        if (empty($command)) {
            return ['success' => false, 'error' => 'Command is required'];
        }
        
        // Limit timeout to reasonable bounds
        $timeout = max(1, min($timeout ?? 30, 120));
        
        // Ensure cwd is within /home
        $cwd = $cwd ?? '/home';
        if (strpos($cwd, '/home') !== 0) {
            $cwd = '/home/' . ltrim(str_replace(['../', '..\\', '..'], '', $cwd), '/');
        }
        
        [$exitCode, $stdout, $stderr] = LxdSandboxManager::execInSandbox($sandboxId, $command, $cwd, $timeout);
        
        return [
            'success' => $exitCode === 0,
            'exit_code' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'command' => $command,
            'cwd' => $cwd,
            'sandbox_id' => $sandboxId
        ];
    }

    // =========================================================================
    // MULTI-FILE OPERATIONS
    // =========================================================================

    #[McpTool(
        name: 'sandbox_compose_project',
        description: 'Create multiple files and directories at once in the user\'s sandbox. Ideal for scaffolding new projects or features. Pass an array of file definitions with path and content. Creates parent directories automatically. Paths are relative to /home/.'
    )]
    public function composeProject(
        array $files,
        ?string $description = null,
        ?string $sandbox_id = null
    ): array {
        $sandboxId = self::getSandboxId($sandbox_id);
        $validation = self::validateSandbox($sandboxId);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }
        
        $results = [
            'description' => $description ?? 'Multi-file composition',
            'created' => [],
            'failed' => [],
            'sandbox_id' => $sandboxId
        ];
        
        foreach ($files as $file) {
            $path = $file['path'] ?? null;
            $content = $file['content'] ?? '';
            $type = $file['type'] ?? 'file';
            
            if (!$path) {
                $results['failed'][] = ['error' => 'Missing path in file definition'];
                continue;
            }
            
            try {
                if ($type === 'folder' || $type === 'directory') {
                    $result = LxdSandboxManager::createItem($sandboxId, $path, 'folder');
                } else {
                    $result = LxdSandboxManager::writeFile($sandboxId, $path, $content);
                }
                
                if ($result['success']) {
                    $results['created'][] = [
                        'path' => $path,
                        'type' => $type,
                        'size' => strlen($content)
                    ];
                } else {
                    $results['failed'][] = [
                        'path' => $path,
                        'error' => $result['error'] ?? 'Unknown error'
                    ];
                }
            } catch (\Throwable $e) {
                $results['failed'][] = [
                    'path' => $path,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        $results['summary'] = sprintf(
            'Created %d items. %d failed.',
            count($results['created']),
            count($results['failed'])
        );
        $results['success'] = count($results['failed']) === 0;
        
        return $results;
    }

    // =========================================================================
    // SANDBOX STATUS
    // =========================================================================

    #[McpTool(
        name: 'sandbox_status',
        description: 'Get the status of the user\'s sandbox container. Returns whether the sandbox exists, is running, and its IP address. Useful for debugging sandbox issues.'
    )]
    public function getStatus(?string $sandbox_id = null): array
    {
        $sandboxId = self::getSandboxId($sandbox_id);
        
        if (empty($sandboxId)) {
            return [
                'success' => true,
                'exists' => false,
                'running' => false,
                'sandbox_id' => null,
                'message' => 'No sandbox assigned. Create a sandbox first.'
            ];
        }
        
        $exists = LxdSandboxManager::sandboxExists($sandboxId);
        $running = $exists ? LxdSandboxManager::sandboxRunning($sandboxId) : false;
        $ip = $running ? LxdSandboxManager::getSandboxIp($sandboxId) : null;
        
        return [
            'success' => true,
            'exists' => $exists,
            'running' => $running,
            'ip' => $ip,
            'sandbox_id' => $sandboxId,
            'container_name' => LxdSandboxManager::containerName($sandboxId),
            'message' => $running 
                ? 'Sandbox is running and ready.' 
                : ($exists ? 'Sandbox exists but is not running.' : 'Sandbox does not exist.')
        ];
    }

    #[McpTool(
        name: 'sandbox_file_exists',
        description: 'Check if a file or directory exists in the user\'s sandbox. The path is relative to /home/ in the sandbox.'
    )]
    public function fileExists(
        string $path,
        ?string $sandbox_id = null
    ): array {
        $sandboxId = self::getSandboxId($sandbox_id);
        $validation = self::validateSandbox($sandboxId);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }
        
        if (empty($path)) {
            return ['success' => false, 'error' => 'Path is required'];
        }
        
        $exists = LxdSandboxManager::pathExists($sandboxId, $path);
        
        return [
            'success' => true,
            'path' => $path,
            'exists' => $exists,
            'sandbox_id' => $sandboxId
        ];
    }

    // =========================================================================
    // PROJECT SCAFFOLDING
    // =========================================================================

    /**
     * Get available project templates
     */
    private static function getProjectTemplates(): array
    {
        return [
            'html' => [
                'name' => 'Static HTML Website',
                'description' => 'Basic HTML/CSS/JS website',
                'files' => [
                    ['path' => 'index.html', 'template' => 'html_index'],
                    ['path' => 'css/style.css', 'template' => 'html_css'],
                    ['path' => 'js/main.js', 'template' => 'html_js'],
                ],
            ],
            'php' => [
                'name' => 'PHP Website',
                'description' => 'PHP website with routing',
                'files' => [
                    ['path' => 'index.php', 'template' => 'php_index'],
                    ['path' => 'includes/header.php', 'template' => 'php_header'],
                    ['path' => 'includes/footer.php', 'template' => 'php_footer'],
                    ['path' => 'css/style.css', 'template' => 'html_css'],
                    ['path' => 'js/main.js', 'template' => 'html_js'],
                ],
            ],
            'react' => [
                'name' => 'React App',
                'description' => 'React application with Vite',
                'files' => [
                    ['path' => 'package.json', 'template' => 'react_package'],
                    ['path' => 'vite.config.js', 'template' => 'react_vite_config'],
                    ['path' => 'index.html', 'template' => 'react_index_html'],
                    ['path' => 'src/main.jsx', 'template' => 'react_main'],
                    ['path' => 'src/App.jsx', 'template' => 'react_app'],
                    ['path' => 'src/App.css', 'template' => 'react_app_css'],
                    ['path' => 'src/index.css', 'template' => 'react_index_css'],
                ],
                'post_commands' => ['npm install'],
            ],
            'vue' => [
                'name' => 'Vue App',
                'description' => 'Vue 3 application with Vite',
                'files' => [
                    ['path' => 'package.json', 'template' => 'vue_package'],
                    ['path' => 'vite.config.js', 'template' => 'vue_vite_config'],
                    ['path' => 'index.html', 'template' => 'vue_index_html'],
                    ['path' => 'src/main.js', 'template' => 'vue_main'],
                    ['path' => 'src/App.vue', 'template' => 'vue_app'],
                    ['path' => 'src/style.css', 'template' => 'vue_style_css'],
                ],
                'post_commands' => ['npm install'],
            ],
            'node' => [
                'name' => 'Node.js API',
                'description' => 'Express.js REST API',
                'files' => [
                    ['path' => 'package.json', 'template' => 'node_package'],
                    ['path' => 'index.js', 'template' => 'node_index'],
                    ['path' => 'routes/api.js', 'template' => 'node_routes'],
                    ['path' => '.env.example', 'template' => 'node_env'],
                ],
                'post_commands' => ['npm install'],
            ],
            'python' => [
                'name' => 'Python Flask API',
                'description' => 'Flask web application',
                'files' => [
                    ['path' => 'app.py', 'template' => 'python_app'],
                    ['path' => 'requirements.txt', 'template' => 'python_requirements'],
                    ['path' => 'templates/index.html', 'template' => 'python_template'],
                    ['path' => 'static/style.css', 'template' => 'html_css'],
                ],
                'post_commands' => ['pip install -r requirements.txt'],
            ],
            'tailwind' => [
                'name' => 'Tailwind CSS Website',
                'description' => 'Static site with Tailwind CSS',
                'files' => [
                    ['path' => 'package.json', 'template' => 'tailwind_package'],
                    ['path' => 'tailwind.config.js', 'template' => 'tailwind_config'],
                    ['path' => 'postcss.config.js', 'template' => 'tailwind_postcss'],
                    ['path' => 'src/input.css', 'template' => 'tailwind_input_css'],
                    ['path' => 'src/index.html', 'template' => 'tailwind_index'],
                ],
                'post_commands' => ['npm install'],
            ],
        ];
    }

    /**
     * Get template content by name
     */
    private static function getTemplateContent(string $template, string $projectName, string $description): string
    {
        $templates = [
            // HTML templates
            'html_index' => <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$projectName}</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <nav>
            <h1>{$projectName}</h1>
        </nav>
    </header>
    
    <main>
        <section class="hero">
            <h2>Welcome to {$projectName}</h2>
            <p>{$description}</p>
        </section>
    </main>
    
    <footer>
        <p>&copy; 2024 {$projectName}. All rights reserved.</p>
    </footer>
    
    <script src="js/main.js"></script>
</body>
</html>
HTML,
            'html_css' => <<<CSS
/* Reset and base styles */
*, *::before, *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
    line-height: 1.6;
    color: #333;
    background-color: #f5f5f5;
}

/* Header */
header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1rem 2rem;
}

nav h1 {
    font-size: 1.5rem;
}

/* Main content */
main {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

.hero {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.hero h2 {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    color: #667eea;
}

/* Footer */
footer {
    text-align: center;
    padding: 2rem;
    background: #333;
    color: white;
    margin-top: 2rem;
}
CSS,
            'html_js' => <<<JS
// Main JavaScript file
document.addEventListener('DOMContentLoaded', function() {
    console.log('{$projectName} loaded successfully!');
    
    // Add your JavaScript code here
});
JS,
            // PHP templates
            'php_index' => <<<PHP
<?php
require_once 'includes/header.php';
?>

<main>
    <section class="hero">
        <h2>Welcome to <?= htmlspecialchars('{$projectName}') ?></h2>
        <p><?= htmlspecialchars('{$description}') ?></p>
    </section>
</main>

<?php require_once 'includes/footer.php'; ?>
PHP,
            'php_header' => <<<PHP
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \$pageTitle ?? '{$projectName}' ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <nav>
            <h1>{$projectName}</h1>
        </nav>
    </header>
PHP,
            'php_footer' => <<<PHP
    <footer>
        <p>&copy; <?= date('Y') ?> {$projectName}. All rights reserved.</p>
    </footer>
    
    <script src="js/main.js"></script>
</body>
</html>
PHP,
            // React templates
            'react_package' => <<<JSON
{
  "name": "{$projectName}",
  "private": true,
  "version": "0.1.0",
  "type": "module",
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "preview": "vite preview"
  },
  "dependencies": {
    "react": "^18.2.0",
    "react-dom": "^18.2.0"
  },
  "devDependencies": {
    "@vitejs/plugin-react": "^4.2.0",
    "vite": "^5.0.0"
  }
}
JSON,
            'react_vite_config' => <<<JS
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  server: {
    host: '0.0.0.0',
    port: 3000
  }
})
JS,
            'react_index_html' => <<<HTML
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{$projectName}</title>
  </head>
  <body>
    <div id="root"></div>
    <script type="module" src="/src/main.jsx"></script>
  </body>
</html>
HTML,
            'react_main' => <<<JSX
import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App.jsx'
import './index.css'

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
)
JSX,
            'react_app' => <<<JSX
import { useState } from 'react'
import './App.css'

function App() {
  const [count, setCount] = useState(0)

  return (
    <div className="app">
      <header className="app-header">
        <h1>{$projectName}</h1>
        <p>{$description}</p>
      </header>
      
      <main>
        <div className="card">
          <button onClick={() => setCount((count) => count + 1)}>
            Count is {count}
          </button>
          <p>Edit <code>src/App.jsx</code> and save to test HMR</p>
        </div>
      </main>
    </div>
  )
}

export default App
JSX,
            'react_app_css' => <<<CSS
.app {
  text-align: center;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.app-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 2rem;
  color: white;
}

.app-header h1 {
  margin: 0 0 0.5rem 0;
  font-size: 2.5rem;
}

main {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}

.card {
  padding: 2rem;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.card button {
  background: #667eea;
  color: white;
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: 4px;
  font-size: 1rem;
  cursor: pointer;
  transition: background 0.2s;
}

.card button:hover {
  background: #764ba2;
}
CSS,
            'react_index_css' => <<<CSS
:root {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
  line-height: 1.6;
  color: #333;
  background-color: #f5f5f5;
}

*, *::before, *::after {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  min-width: 320px;
  min-height: 100vh;
}
CSS,
            // Vue templates
            'vue_package' => <<<JSON
{
  "name": "{$projectName}",
  "private": true,
  "version": "0.1.0",
  "type": "module",
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "preview": "vite preview"
  },
  "dependencies": {
    "vue": "^3.4.0"
  },
  "devDependencies": {
    "@vitejs/plugin-vue": "^5.0.0",
    "vite": "^5.0.0"
  }
}
JSON,
            'vue_vite_config' => <<<JS
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  server: {
    host: '0.0.0.0',
    port: 3000
  }
})
JS,
            'vue_index_html' => <<<HTML
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{$projectName}</title>
  </head>
  <body>
    <div id="app"></div>
    <script type="module" src="/src/main.js"></script>
  </body>
</html>
HTML,
            'vue_main' => <<<JS
import { createApp } from 'vue'
import './style.css'
import App from './App.vue'

createApp(App).mount('#app')
JS,
            'vue_app' => <<<VUE
<script setup>
import { ref } from 'vue'

const count = ref(0)
</script>

<template>
  <div class="app">
    <header class="app-header">
      <h1>{$projectName}</h1>
      <p>{$description}</p>
    </header>
    
    <main>
      <div class="card">
        <button @click="count++">Count is {{ count }}</button>
        <p>Edit <code>src/App.vue</code> to test HMR</p>
      </div>
    </main>
  </div>
</template>

<style scoped>
.app {
  text-align: center;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.app-header {
  background: linear-gradient(135deg, #42b883 0%, #35495e 100%);
  padding: 2rem;
  color: white;
}

.app-header h1 {
  margin: 0 0 0.5rem 0;
  font-size: 2.5rem;
}

main {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}

.card {
  padding: 2rem;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.card button {
  background: #42b883;
  color: white;
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: 4px;
  font-size: 1rem;
  cursor: pointer;
  transition: background 0.2s;
}

.card button:hover {
  background: #35495e;
}
</style>
VUE,
            'vue_style_css' => <<<CSS
:root {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
  line-height: 1.6;
  color: #333;
  background-color: #f5f5f5;
}

*, *::before, *::after {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  min-width: 320px;
  min-height: 100vh;
}
CSS,
            // Node.js templates
            'node_package' => <<<JSON
{
  "name": "{$projectName}",
  "version": "1.0.0",
  "description": "{$description}",
  "main": "index.js",
  "scripts": {
    "start": "node index.js",
    "dev": "node --watch index.js"
  },
  "dependencies": {
    "express": "^4.18.2",
    "cors": "^2.8.5",
    "dotenv": "^16.3.1"
  }
}
JSON,
            'node_index' => <<<JS
const express = require('express');
const cors = require('cors');
require('dotenv').config();

const apiRoutes = require('./routes/api');

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(cors());
app.use(express.json());

// Routes
app.use('/api', apiRoutes);

// Health check
app.get('/', (req, res) => {
  res.json({ 
    name: '{$projectName}',
    status: 'running',
    message: '{$description}'
  });
});

// Start server
app.listen(PORT, '0.0.0.0', () => {
  console.log(`{$projectName} running on http://0.0.0.0:\${PORT}`);
});
JS,
            'node_routes' => <<<JS
const express = require('express');
const router = express.Router();

// GET /api/items
router.get('/items', (req, res) => {
  res.json([
    { id: 1, name: 'Item 1' },
    { id: 2, name: 'Item 2' },
    { id: 3, name: 'Item 3' }
  ]);
});

// GET /api/items/:id
router.get('/items/:id', (req, res) => {
  const { id } = req.params;
  res.json({ id: parseInt(id), name: `Item \${id}` });
});

// POST /api/items
router.post('/items', (req, res) => {
  const { name } = req.body;
  res.status(201).json({ id: Date.now(), name });
});

module.exports = router;
JS,
            'node_env' => <<<ENV
PORT=3000
NODE_ENV=development
ENV,
            // Python templates
            'python_app' => <<<PYTHON
from flask import Flask, render_template, jsonify

app = Flask(__name__)

@app.route('/')
def index():
    return render_template('index.html', 
                          project_name='{$projectName}',
                          description='{$description}')

@app.route('/api/health')
def health():
    return jsonify({
        'status': 'healthy',
        'name': '{$projectName}'
    })

@app.route('/api/items')
def get_items():
    return jsonify([
        {'id': 1, 'name': 'Item 1'},
        {'id': 2, 'name': 'Item 2'},
        {'id': 3, 'name': 'Item 3'}
    ])

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)
PYTHON,
            'python_requirements' => <<<TXT
flask>=2.0.0
python-dotenv>=1.0.0
TXT,
            'python_template' => <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ project_name }}</title>
    <link rel="stylesheet" href="{{ url_for('static', filename='style.css') }}">
</head>
<body>
    <header>
        <nav><h1>{{ project_name }}</h1></nav>
    </header>
    <main>
        <section class="hero">
            <h2>Welcome to {{ project_name }}</h2>
            <p>{{ description }}</p>
        </section>
    </main>
    <footer>
        <p>&copy; 2024 {{ project_name }}</p>
    </footer>
</body>
</html>
HTML,
            // Tailwind templates
            'tailwind_package' => <<<JSON
{
  "name": "{$projectName}",
  "version": "1.0.0",
  "scripts": {
    "build": "npx tailwindcss -i ./src/input.css -o ./dist/output.css",
    "watch": "npx tailwindcss -i ./src/input.css -o ./dist/output.css --watch"
  },
  "devDependencies": {
    "tailwindcss": "^3.4.0",
    "autoprefixer": "^10.4.16",
    "postcss": "^8.4.32"
  }
}
JSON,
            'tailwind_config' => <<<JS
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./src/**/*.{html,js}"],
  theme: {
    extend: {},
  },
  plugins: [],
}
JS,
            'tailwind_postcss' => <<<JS
module.exports = {
  plugins: {
    tailwindcss: {},
    autoprefixer: {},
  }
}
JS,
            'tailwind_input_css' => <<<CSS
@tailwind base;
@tailwind components;
@tailwind utilities;
CSS,
            'tailwind_index' => <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$projectName}</title>
    <link href="../dist/output.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white p-6">
        <nav class="max-w-6xl mx-auto">
            <h1 class="text-2xl font-bold">{$projectName}</h1>
        </nav>
    </header>
    
    <main class="max-w-6xl mx-auto p-8">
        <section class="bg-white rounded-lg shadow-lg p-8 text-center">
            <h2 class="text-4xl font-bold text-indigo-600 mb-4">Welcome to {$projectName}</h2>
            <p class="text-gray-600 text-lg">{$description}</p>
            <button class="mt-6 bg-indigo-500 hover:bg-indigo-600 text-white px-6 py-3 rounded-lg transition">
                Get Started
            </button>
        </section>
    </main>
    
    <footer class="bg-gray-800 text-white text-center p-6 mt-8">
        <p>&copy; 2024 {$projectName}</p>
    </footer>
</body>
</html>
HTML,
        ];

        return $templates[$template] ?? "// Template not found: {$template}";
    }

    #[McpTool(
        name: 'sandbox_create_project',
        description: 'Create a new project from a template in the user\'s sandbox. Available project types: html (static website), php (PHP website), react (React + Vite), vue (Vue 3 + Vite), node (Express.js API), python (Flask API), tailwind (Tailwind CSS site). Automatically scaffolds all necessary files and optionally runs setup commands like npm install.'
    )]
    public function createProject(
        string $project_type,
        ?string $project_name = 'my-project',
        ?string $description = 'A new project',
        ?bool $run_install = true,
        ?string $sandbox_id = null
    ): array {
        $sandboxId = self::getSandboxId($sandbox_id);
        $validation = self::validateSandbox($sandboxId);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }

        $templates = self::getProjectTemplates();
        $projectType = strtolower(trim($project_type));
        
        if (!isset($templates[$projectType])) {
            return [
                'success' => false,
                'error' => "Unknown project type: {$project_type}",
                'available_types' => array_keys($templates),
                'hint' => 'Use one of the available project types listed above.'
            ];
        }

        $template = $templates[$projectType];
        $projectName = preg_replace('/[^a-zA-Z0-9_-]/', '-', $project_name ?? 'my-project');
        $projectPath = $projectName;
        $description = $description ?? 'A new project';

        // Create project directory
        $createDirResult = LxdSandboxManager::createItem($sandboxId, $projectPath, 'folder');
        if (!$createDirResult['success']) {
            return ['success' => false, 'error' => 'Failed to create project directory: ' . ($createDirResult['error'] ?? 'Unknown error')];
        }

        $createdFiles = [];
        $failedFiles = [];

        // Create all files from template
        foreach ($template['files'] as $file) {
            $filePath = $projectPath . '/' . $file['path'];
            $content = self::getTemplateContent($file['template'], $projectName, $description);
            
            // Ensure parent directory exists
            $parentDir = dirname($filePath);
            if ($parentDir !== $projectPath && $parentDir !== '.') {
                LxdSandboxManager::createItem($sandboxId, $parentDir, 'folder');
            }
            
            $result = LxdSandboxManager::writeFile($sandboxId, $filePath, $content);
            
            if ($result['success']) {
                $createdFiles[] = $filePath;
            } else {
                $failedFiles[] = ['path' => $filePath, 'error' => $result['error'] ?? 'Unknown error'];
            }
        }

        // Run post-install commands if requested
        $commandResults = [];
        if ($run_install && !empty($template['post_commands'])) {
            foreach ($template['post_commands'] as $cmd) {
                $cwd = '/home/' . $projectPath;
                [$exitCode, $stdout, $stderr] = LxdSandboxManager::execInSandbox($sandboxId, $cmd, $cwd, 120);
                $commandResults[] = [
                    'command' => $cmd,
                    'success' => $exitCode === 0,
                    'exit_code' => $exitCode,
                    'output' => $exitCode === 0 ? 'Completed successfully' : ($stderr ?: $stdout)
                ];
            }
        }

        // Determine run command hint
        $runHints = [
            'html' => 'Open index.html in a browser, or use a simple HTTP server',
            'php' => 'Run with: php -S 0.0.0.0:8000',
            'react' => 'Run with: cd ' . $projectPath . ' && npm run dev',
            'vue' => 'Run with: cd ' . $projectPath . ' && npm run dev',
            'node' => 'Run with: cd ' . $projectPath . ' && npm start',
            'python' => 'Run with: cd ' . $projectPath . ' && python app.py',
            'tailwind' => 'Build CSS: cd ' . $projectPath . ' && npm run build',
        ];

        return [
            'success' => count($failedFiles) === 0,
            'project_type' => $projectType,
            'project_name' => $projectName,
            'project_path' => $projectPath,
            'template_name' => $template['name'],
            'description' => $template['description'],
            'files_created' => $createdFiles,
            'files_failed' => $failedFiles,
            'install_commands' => $commandResults,
            'run_hint' => $runHints[$projectType] ?? 'Check project documentation',
            'sandbox_id' => $sandboxId,
            'message' => count($failedFiles) === 0 
                ? "Project '{$projectName}' created successfully with " . count($createdFiles) . " files!"
                : "Project created with some errors. " . count($createdFiles) . " files created, " . count($failedFiles) . " failed."
        ];
    }

    #[McpTool(
        name: 'sandbox_list_project_types',
        description: 'List all available project templates that can be created with sandbox_create_project. Returns the project type key, name, and description for each available template.'
    )]
    public function listProjectTypes(): array
    {
        $templates = self::getProjectTemplates();
        $types = [];
        
        foreach ($templates as $key => $template) {
            $types[] = [
                'type' => $key,
                'name' => $template['name'],
                'description' => $template['description'],
                'has_install' => !empty($template['post_commands'])
            ];
        }
        
        return [
            'success' => true,
            'project_types' => $types,
            'usage' => 'Call sandbox_create_project with project_type set to one of the types above'
        ];
    }
}
