<?php

declare(strict_types=1);

namespace App\Handlers;

use PhpMcp\Server\Attributes\McpTool;
use Ginto\Helpers\UnifiedSandbox;

/**
 * Sandbox File MCP Tools
 * 
 * Provides MCP tools for agents to interact with user sandbox containers.
 * All file operations are scoped to the home directory inside the container.
 * 
 * Supports both LXC (default) and Docker backends via UnifiedSandbox.
 * The backend is automatically detected based on server configuration.
 * 
 * The sandbox_id is obtained from the session context, ensuring users
 * can only access their own sandbox.
 * 
 * Security:
 * - All paths are sanitized to prevent traversal attacks
 * - Operations are confined to the user's container
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
        
        if (!UnifiedSandbox::exists($sandboxId)) {
            $backend = UnifiedSandbox::getBackend();
            $backendName = $backend === 'docker' ? 'Docker' : 'LXC';
            return ['valid' => false, 'error' => "Sandbox does not exist. Please create a sandbox first. (Backend: {$backendName})"];
        }
        
        // Ensure sandbox is running
        if (!UnifiedSandbox::isRunning($sandboxId)) {
            UnifiedSandbox::ensureRunning($sandboxId);
            usleep(500000); // Wait 0.5s for container to start
            
            if (!UnifiedSandbox::isRunning($sandboxId)) {
                return ['valid' => false, 'error' => 'Failed to start sandbox container.'];
            }
        }
        
        return ['valid' => true, 'error' => null];
    }

    /**
     * Normalize a path: expand ~, strip leading slashes, prevent traversal
     * In sandbox, all paths are relative to /root/ so ~ just means root
     */
    private static function normalizePath(string $path): string
    {
        // Expand ~ and ~/ to empty (root of /root/)
        $path = preg_replace('/^~\/?/', '', $path);
        
        // Strip leading slashes
        $path = ltrim($path, '/');
        
        // Prevent directory traversal
        $path = str_replace(['../', '..\\'], '', $path);
        
        // Remove any remaining .. segments
        $path = preg_replace('/\.\.+/', '', $path);
        
        return $path;
    }

    // =========================================================================
    // FILE OPERATIONS
    // =========================================================================

    #[McpTool(
        name: 'sandbox_list_files',
        description: 'List files and directories in the user\'s sandbox. Returns a tree structure of files and folders. Use this to explore what files exist in the sandbox before reading or modifying them.'
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
        
        $homePath = UnifiedSandbox::getHomePathForSandbox($sandboxId);
        $remotePath = $homePath;
        if (!empty($path)) {
            $path = self::normalizePath($path);
            if (!empty($path)) {
                $remotePath = $homePath . '/' . $path;
            }
        }
        
        $result = UnifiedSandbox::listFiles($sandboxId, $remotePath, $maxDepth ?? 5);
        
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
        description: 'Read the contents of a file from the user\'s sandbox. The path is relative to the home directory in the sandbox. For example, to read project/index.php, pass "project/index.php" as the path.'
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
        
        // Normalize path (expand ~, strip leading /, prevent traversal)
        $path = self::normalizePath($path);
        
        if (empty($path)) {
            return ['success' => false, 'error' => 'Invalid path'];
        }
        
        $result = UnifiedSandbox::readFile($sandboxId, $path);

        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'Failed to read file'];
        }

        $isBinary = $result['is_binary'] ?? false;
        $content = $result['content'] ?? '';

        return [
            'success' => true,
            'path' => $path,
            'content' => $content,
            'encoding' => $isBinary ? 'base64' : 'utf-8',
            'is_binary' => $isBinary,
            'size' => $isBinary ? (int) (strlen($content) * 3 / 4) : strlen($content),
            'sandbox_id' => $sandboxId
        ];
    }

    #[McpTool(
        name: 'sandbox_write_file',
        description: 'Write content to a file in the user\'s sandbox. Creates the file if it doesn\'t exist. Creates parent directories automatically. The path is relative to the home directory in the sandbox. For example, to write project/index.php, pass "project/index.php" as the path.'
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
        
        // Normalize path (expand ~, strip leading /, prevent traversal)
        $path = self::normalizePath($path);
        
        if (empty($path)) {
            return ['success' => false, 'error' => 'Invalid path'];
        }
            $homePath = UnifiedSandbox::getHomePathForSandbox($sandboxId);
        // Read original content before writing (for checkpoint/restore support)
        $originalContent = null;
        $isNewFile = false;
        $readResult = UnifiedSandbox::readFile($sandboxId, $path);
        if ($readResult['success']) {
            $originalContent = $readResult['content'];
        } else {
            $isNewFile = true;
        }
        
        $result = UnifiedSandbox::writeFile($sandboxId, $path, $content);
        
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'Failed to write file'];
        }
        
        // Build the download/view URL
        $cleanPath = ltrim($path, '/');
            $cleanPath = ltrim($path, '/');
            $url = '/clients/' . $sandboxId . '/' . $cleanPath;
        return [
            'success' => true,
            'path' => $path,
            'url' => $url,
            'bytes_written' => $result['bytes'] ?? strlen($content),
            'sandbox_id' => $sandboxId,
            'original_content' => $originalContent,
            'is_new_file' => $isNewFile,
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
        $result = UnifiedSandbox::createItem($sandboxId, $path, $itemType);
        
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
        name: 'sandbox_delete',
        description: 'Delete a file or folder from the user\'s sandbox. Use type="file" to delete ONLY files (skip folders), type="folder" to delete ONLY folders, or type="any" (default) for both. The path is relative to the home directory in the sandbox.'
    )]
    public function deleteFile(
        string $path,
        ?string $type = 'any',
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
        
        // Normalize path (expand ~, strip leading /, prevent traversal)
        $path = self::normalizePath($path);
        
        // Extra safety: don't allow deleting root
        $cleanPath = trim($path, '/');
        if (empty($cleanPath) || $cleanPath === '.' || $cleanPath === 'root') {
            return ['success' => false, 'error' => 'Cannot delete root directory'];
        }
        
        // Type filtering: check if path is file or folder before deleting
        $homePath = UnifiedSandbox::getHomePathForSandbox($sandboxId);
        if ($type === 'file' || $type === 'folder') {
            // Use exec - returns [exit_code, stdout, stderr]
            // We need to check if path is a directory
            $checkCmd = "test -d " . escapeshellarg("$homePath/$cleanPath") . " && echo folder || echo file";
            [$exitCode, $stdout, $stderr] = UnifiedSandbox::exec($sandboxId, $checkCmd, $homePath, 10);
            $actualType = trim($stdout ?: 'file');
            
            if ($type === 'file' && $actualType === 'folder') {
                return ['success' => false, 'error' => "Skipped: '$path' is a folder, not a file", 'skipped' => true];
            }
            if ($type === 'folder' && $actualType === 'file') {
                return ['success' => false, 'error' => "Skipped: '$path' is a file, not a folder", 'skipped' => true];
            }
        }
        
        $result = UnifiedSandbox::deleteItem($sandboxId, $homePath . '/' . $cleanPath);
        
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
        description: 'Rename or move a file/directory in the user\'s sandbox. Both paths are relative to /root/ in the sandbox. Can be used to move files between directories.'
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
        
        // Normalize paths
        $old_path = self::normalizePath($old_path);
        $new_path = self::normalizePath($new_path);
        
        if (empty($old_path) || empty($new_path)) {
            return ['success' => false, 'error' => 'Invalid path'];
        }
        
        $result = UnifiedSandbox::renameItem($sandboxId, $old_path, $new_path);
        
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
        description: 'Copy a file or directory within the user\'s sandbox. Both paths are relative to /root/ in the sandbox. Directories are copied recursively.'
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
        
        // Normalize paths
        $source_path = self::normalizePath($source_path);
        $dest_path = self::normalizePath($dest_path);
        
        if (empty($source_path) || empty($dest_path)) {
            return ['success' => false, 'error' => 'Invalid path'];
        }
        
        $result = UnifiedSandbox::copyItem($sandboxId, $source_path, $dest_path);
        
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
        description: 'Execute a shell command in the user\'s sandbox container. Commands run inside an isolated container. The working directory defaults to the home directory. Common use cases: running npm/composer install, running scripts, checking installed packages. Returns stdout/stderr and exit code.'
    )]
    public function execCommand(
        string $command,
        ?string $cwd = null,
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
        
        // Use per-sandbox home path for cwd, ensure it's within bounds
        $homePath = UnifiedSandbox::getHomePathForSandbox($sandboxId);
        $cwd = $cwd ?? $homePath;
        if (strpos($cwd, $homePath) !== 0 && strpos($cwd, '/') !== 0) {
            $cwd = $homePath . '/' . ltrim(str_replace(['../', '..\\', '..'], '', $cwd), '/');
        }
        
        [$exitCode, $stdout, $stderr] = UnifiedSandbox::exec($sandboxId, $command, $cwd, $timeout);
        
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
        description: 'Create multiple files and directories at once in the user\'s sandbox. Ideal for scaffolding new projects or features. Pass an array of file definitions with path and content. Creates parent directories automatically. Paths are relative to home directory..'
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
                    $result = UnifiedSandbox::createItem($sandboxId, $path, 'folder');
                } else {
                    $result = UnifiedSandbox::writeFile($sandboxId, $path, $content);
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
        
        $exists = UnifiedSandbox::sandboxExists($sandboxId);
        $running = $exists ? UnifiedSandbox::sandboxRunning($sandboxId) : false;
        $ip = $running ? UnifiedSandbox::getSandboxIp($sandboxId) : null;
        
        return [
            'success' => true,
            'exists' => $exists,
            'running' => $running,
            'ip' => $ip,
            'sandbox_id' => $sandboxId,
            'container_name' => UnifiedSandbox::containerName($sandboxId),
            'message' => $running 
                ? 'Sandbox is running and ready.' 
                : ($exists ? 'Sandbox exists but is not running.' : 'Sandbox does not exist.')
        ];
    }

    #[McpTool(
        name: 'sandbox_file_exists',
        description: 'Check if a file or directory exists in the user\'s sandbox. The path is relative to the home directory in the sandbox.'
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
        
        $exists = UnifiedSandbox::pathExists($sandboxId, $path);
        
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

        $templates = ProjectTemplates::getTemplates();
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
        $createDirResult = UnifiedSandbox::createItem($sandboxId, $projectPath, 'folder');
        if (!$createDirResult['success']) {
            return ['success' => false, 'error' => 'Failed to create project directory: ' . ($createDirResult['error'] ?? 'Unknown error')];
        }

        $createdFiles = [];
        $failedFiles = [];

        // Create all files from template
        foreach ($template['files'] as $file) {
            $filePath = $projectPath . '/' . $file['path'];
            $content = ProjectTemplates::getContent($file['template'], $projectName, $description);
            
            // Ensure parent directory exists
            $parentDir = dirname($filePath);
            if ($parentDir !== $projectPath && $parentDir !== '.') {
                UnifiedSandbox::createItem($sandboxId, $parentDir, 'folder');
            }
            
            $result = UnifiedSandbox::writeFile($sandboxId, $filePath, $content);
            
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
                $homePath = UnifiedSandbox::getHomePathForSandbox($sandboxId);
                $cwd = rtrim($homePath, '/') . '/' . $projectPath;
                [$exitCode, $stdout, $stderr] = UnifiedSandbox::exec($sandboxId, $cmd, $cwd, 120);
                $commandResults[] = [
                    'command' => $cmd,
                    'success' => $exitCode === 0,
                    'exit_code' => $exitCode,
                    'output' => $exitCode === 0 ? 'Completed successfully' : ($stderr ?: $stdout)
                ];
            }
        }

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
            'run_hint' => ProjectTemplates::getRunHint($projectType, $projectPath),
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
        $templates = ProjectTemplates::getTemplates();
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

    // =========================================================================
    // DOCUMENT CREATION
    // =========================================================================

    #[McpTool(
        name: 'sandbox_create_document',
        description: 'Create a real document (PDF, DOCX, ODT) in the user\'s sandbox using Pandoc. Write content in Markdown format - it will be converted to the target format. Supported formats: pdf (recommended for professional docs), docx (Word), odt (LibreOffice), html, md, txt, rtf. Default format is PDF.'
    )]
    public function createDocument(
        string $filename,
        string $content,
        string $format = 'pdf',
        ?string $title = null,
        ?string $folder = 'Documents',
        ?string $sandbox_id = null
    ): array {
        $sandboxId = self::getSandboxId($sandbox_id);
        $validation = self::validateSandbox($sandboxId);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }
        
        $formatConfig = DocumentFormats::getFormat($format);
        $format = strtolower($format);
        
        // Validate format
        if (!$formatConfig) {
            return [
                'success' => false,
                'error' => "Unknown format: {$format}. Available formats: " . implode(', ', DocumentFormats::getFormatKeys()),
                'available_formats' => DocumentFormats::getFormatKeys()
            ];
        }
        
        $title = $title ?? pathinfo($filename, PATHINFO_FILENAME);
        
        // Clean filename
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $basename = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $basename);
        $basename = str_replace(' ', '_', $basename);
        
        if (empty($basename)) {
            $basename = 'document_' . date('Y-m-d_His');
        }
        
        // Clean folder path
        $folder = trim($folder, '/');
        if (empty($folder)) {
            $folder = 'Documents';
        }
        
        // Create source markdown file path
        $sourcePath = $folder . '/' . $basename . '.md';
        $outputFilename = $basename . $formatConfig['extension'];
        $outputPath = $folder . '/' . $outputFilename;
        
        // Prepare markdown content with title
        $markdownContent = "---\ntitle: \"{$title}\"\n---\n\n" . $content;
        
        // Ensure folder exists and write source markdown file
        $writeResult = UnifiedSandbox::writeFile($sandboxId, $sourcePath, $markdownContent);
        
        if (!$writeResult['success']) {
            return ['success' => false, 'error' => $writeResult['error'] ?? 'Failed to write source file'];
        }

        // Resolve sandbox home and working directory for commands, ensure target folder exists
        $homePath = UnifiedSandbox::getHomePathForSandbox($sandboxId);
        $cwd = rtrim($homePath, '/') . '/' . $folder;
        UnifiedSandbox::createItem($sandboxId, $folder, 'folder');
        
        // If native format (md, txt, html), we're done or just need simple conversion
        if ($formatConfig['native'] ?? false) {
            if ($format === 'md') {
                // Already written as markdown
                $url = '/clients/' . $sandboxId . '/' . $sourcePath;
                return [
                    'success' => true,
                    'path' => $sourcePath,
                    'filename' => $basename . '.md',
                    'format' => $formatConfig['name'],
                    'format_key' => $format,
                    'mime_type' => $formatConfig['mime'],
                    'url' => $url,
                    'bytes_written' => $writeResult['bytes'] ?? strlen($markdownContent),
                    'sandbox_id' => $sandboxId,
                    'message' => "Document created: {$basename}.md ({$formatConfig['name']})",
                    'open_hint' => DocumentFormats::getOpenHint($format)
                ];
            } elseif ($format === 'html') {
                // Convert markdown to styled HTML
                $htmlContent = DocumentFormats::markdownToHtml($content, $title);
                $htmlWriteResult = UnifiedSandbox::writeFile($sandboxId, $outputPath, $htmlContent);
                
                if (!$htmlWriteResult['success']) {
                    return ['success' => false, 'error' => 'Failed to write HTML file'];
                }
                
                // Clean up source md file
                UnifiedSandbox::deleteItem($sandboxId, $sourcePath);
                
                $url = '/clients/' . $sandboxId . '/' . $outputPath;
                return [
                    'success' => true,
                    'path' => $outputPath,
                    'filename' => $outputFilename,
                    'format' => $formatConfig['name'],
                    'format_key' => $format,
                    'mime_type' => $formatConfig['mime'],
                    'url' => $url,
                    'bytes_written' => $htmlWriteResult['bytes'] ?? strlen($htmlContent),
                    'sandbox_id' => $sandboxId,
                    'message' => "Document created: {$outputFilename} ({$formatConfig['name']})",
                    'open_hint' => DocumentFormats::getOpenHint($format)
                ];
            } elseif ($format === 'txt') {
                // Write as plain text (strip markdown formatting)
                $txtContent = $content;
                $txtWriteResult = UnifiedSandbox::writeFile($sandboxId, $outputPath, $txtContent);
                
                if (!$txtWriteResult['success']) {
                    return ['success' => false, 'error' => 'Failed to write text file'];
                }
                
                // Clean up source md file
                UnifiedSandbox::deleteItem($sandboxId, $sourcePath);
                
                $url = '/clients/' . $sandboxId . '/' . $outputPath;
                return [
                    'success' => true,
                    'path' => $outputPath,
                    'filename' => $outputFilename,
                    'format' => $formatConfig['name'],
                    'format_key' => $format,
                    'mime_type' => $formatConfig['mime'],
                    'url' => $url,
                    'bytes_written' => $txtWriteResult['bytes'] ?? strlen($txtContent),
                    'sandbox_id' => $sandboxId,
                    'message' => "Document created: {$outputFilename} ({$formatConfig['name']})",
                    'open_hint' => DocumentFormats::getOpenHint($format)
                ];
            }
        }
        
        // For PDF, DOCX, ODT, RTF - use Pandoc to convert
        $pandocOutputFormat = match($format) {
            'pdf' => 'pdf',
            'docx' => 'docx',
            'odt' => 'odt',
            'rtf' => 'rtf',
            'pptx' => 'pptx',
            default => 'pdf'
        };
        
        // For PDF, use a two-step process: Markdown -> HTML -> PDF (via wkhtmltopdf)
        // This is more reliable than pandoc's PDF engine which requires pdflatex
        if ($format === 'pdf') {
            // First convert markdown to styled HTML
            $htmlContent = DocumentFormats::markdownToHtml($content, $title);
            // Inject a small script to set window.status when the page is fully loaded so wkhtmltopdf
            // can use `--window-status ready` instead of relying on long javascript delays.
            $htmlContent .= "\n<!-- window-status ready injection -->\n<script>window.addEventListener('load', function(){ window.status='ready'; });</script>\n";
            $htmlFilename = $basename . '.html';
            $htmlPath = $folder . '/' . $htmlFilename;
            
            $htmlWriteResult = UnifiedSandbox::writeFile($sandboxId, $htmlPath, $htmlContent);
            if (!$htmlWriteResult['success']) {
                return ['success' => false, 'error' => 'Failed to create intermediate HTML file'];
            }

            // FAST PATH: try host-level conversion (avoid sandbox/worker). Write files to
            // host `clients/{sandboxId}/{folder}` so native `wkhtmltopdf` can access them.
            $projectRoot = defined('ROOT_PATH') ? ROOT_PATH : dirname(dirname(__DIR__));
            $hostRoot = dirname($projectRoot) . '/storage';
            $hostClientsDir = rtrim($hostRoot, '/') . '/clients/' . $sandboxId . '/' . $folder;
            if (!is_dir($hostClientsDir)) {
                @mkdir($hostClientsDir, 0755, true);
            }

            $hostHtmlPath = $hostClientsDir . '/' . $htmlFilename;
            $hostOutputPath = $hostClientsDir . '/' . $outputFilename;

            // Write HTML to host clients dir
            @file_put_contents($hostHtmlPath, $htmlContent);

            // Candidate host commands (try lightweight ones first)
            $wkHostCandidates = [
                "wkhtmltopdf --quiet --window-status ready --enable-local-file-access " . escapeshellarg($hostHtmlPath) . ' ' . escapeshellarg($hostOutputPath),
                "xvfb-run -a wkhtmltopdf --quiet --window-status ready --enable-local-file-access " . escapeshellarg($hostHtmlPath) . ' ' . escapeshellarg($hostOutputPath),
                "/usr/bin/wkhtmltopdf --quiet --window-status ready --enable-local-file-access " . escapeshellarg($hostHtmlPath) . ' ' . escapeshellarg($hostOutputPath),
                "/usr/local/bin/wkhtmltopdf --quiet --window-status ready --enable-local-file-access " . escapeshellarg($hostHtmlPath) . ' ' . escapeshellarg($hostOutputPath),
            ];

            $converted = false; $exitCode = 1; $out = [];
            foreach ($wkHostCandidates as $cmd) {
                // run with timeout via shell `timeout` if available
                $full = (str_starts_with($cmd, 'timeout')) ? $cmd : "timeout 20s $cmd";
                exec($full . ' 2>&1', $out, $exitCode);
                if ($exitCode === 0 && file_exists($hostOutputPath)) {
                    $converted = true;
                    break;
                }
            }

            if ($converted) {
                // Clean up intermediate sandbox files
                UnifiedSandbox::deleteItem($sandboxId, $htmlPath);
                UnifiedSandbox::deleteItem($sandboxId, $sourcePath);
                // Read the generated host file and write it into the sandbox Documents folder
                $fileSize = filesize($hostOutputPath) ?: 0;
                $hostContent = @file_get_contents($hostOutputPath);
                if ($hostContent !== false) {
                    $writeBack = UnifiedSandbox::writeFile($sandboxId, $outputPath, $hostContent);
                    if (!($writeBack['success'] ?? false)) {
                        // proceed but include a warning in the response
                        $warning = 'Failed to write file back into sandbox: ' . ($writeBack['error'] ?? 'unknown');
                    }
                } else {
                    $warning = 'Failed to read host-generated file';
                }

                $url = '/clients/' . $sandboxId . '/' . $outputPath;
                $resp = [
                    'success' => true,
                    'path' => $outputPath,
                    'filename' => $outputFilename,
                    'format' => $formatConfig['name'],
                    'format_key' => $format,
                    'mime_type' => $formatConfig['mime'],
                    'url' => $url,
                    'bytes_written' => $fileSize,
                    'sandbox_id' => $sandboxId,
                    'message' => "Document created: {$outputFilename} ({$formatConfig['name']})",
                    'open_hint' => DocumentFormats::getOpenHint($format),
                    'converter' => 'wkhtmltopdf (host)'
                ];
                if (!empty($warning)) {
                    $resp['warning'] = $warning;
                }

                return $resp;
            }

            // Host conversion failed — fall back to enqueueing a background job inside sandbox
            $enqueueCmd = "timeout 30s /usr/bin/wkhtmltopdf --quiet --window-status ready --enable-local-file-access \"{$htmlFilename}\" \"{$outputFilename}\"";
            $enqueue = UnifiedSandbox::enqueueJob($sandboxId, $enqueueCmd, $cwd, 120, ['type' => 'wkhtmltopdf', 'output' => $outputPath]);
            if (!($enqueue['success'] ?? false)) {
                $htmlUrl = '/clients/' . $sandboxId . '/' . $htmlPath;
                return ['success' => true, 'path' => $htmlPath, 'filename' => $htmlFilename, 'format' => 'html', 'url' => $htmlUrl, 'bytes_written' => $htmlWriteResult['bytes'] ?? strlen($htmlContent), 'sandbox_id' => $sandboxId, 'message' => 'Created HTML but failed to enqueue PDF conversion'];
            }

            $htmlUrl = '/clients/' . $sandboxId . '/' . $htmlPath;
            $pdfUrl = '/clients/' . $sandboxId . '/' . $outputPath;
            return [
                'success' => true,
                'job_id' => $enqueue['job_id'],
                'message' => 'Conversion enqueued',
                'path' => $outputPath,
                'sandbox_id' => $sandboxId,
                'url_html' => $htmlUrl,
                'url' => $pdfUrl,
            ];
        } else {
            // For DOCX, ODT, RTF - try host-level Pandoc first (faster, no sandbox needed)
            $projectRoot = defined('ROOT_PATH') ? ROOT_PATH : dirname(dirname(__DIR__));
            $hostRoot = dirname($projectRoot) . '/storage';
            $hostClientsDir = rtrim($hostRoot, '/') . '/clients/' . $sandboxId . '/' . $folder;
            if (!is_dir($hostClientsDir)) {
                @mkdir($hostClientsDir, 0755, true);
            }

            $hostMdPath = $hostClientsDir . '/' . $basename . '.md';
            $hostOutputPath = $hostClientsDir . '/' . $outputFilename;
            @file_put_contents($hostMdPath, $markdownContent);

            // Try multiple pandoc binary locations to be more robust across hosts
            $pandocCmdBase = "-o " . escapeshellarg($hostOutputPath) . " --metadata title=" . escapeshellarg($title) . ' ' . escapeshellarg($hostMdPath);
            $pandocCandidates = [
                "pandoc $pandocCmdBase",
                "/usr/bin/pandoc $pandocCmdBase",
                "/usr/local/bin/pandoc $pandocCmdBase",
            ];

            $collected = [];
            $converted = false;
            foreach ($pandocCandidates as $candidate) {
                $out = [];
                $full = "timeout 60s " . $candidate;
                exec($full . ' 2>&1', $out, $exitCode);
                $collected[] = trim(implode("\n", $out));
                if (($exitCode ?? 1) === 0 && file_exists($hostOutputPath)) {
                    $converted = true;
                    break;
                }
            }

            if ($converted) {
                // Remove sandbox-side source if present
                UnifiedSandbox::deleteItem($sandboxId, $sourcePath);
                $fileSize = filesize($hostOutputPath) ?: 0;
                $hostContent = @file_get_contents($hostOutputPath);
                if ($hostContent !== false) {
                    $writeBack = UnifiedSandbox::writeFile($sandboxId, $outputPath, $hostContent);
                    if (!($writeBack['success'] ?? false)) {
                        $warning = 'Failed to write file back into sandbox: ' . ($writeBack['error'] ?? 'unknown');
                    }
                } else {
                    $warning = 'Failed to read host-generated file';
                }

                $url = '/clients/' . $sandboxId . '/' . $outputPath;
                $resp = [
                    'success' => true,
                    'path' => $outputPath,
                    'filename' => $outputFilename,
                    'format' => $formatConfig['name'],
                    'format_key' => $format,
                    'mime_type' => $formatConfig['mime'],
                    'url' => $url,
                    'bytes_written' => $fileSize,
                    'sandbox_id' => $sandboxId,
                    'message' => "Document created: {$outputFilename} ({$formatConfig['name']})",
                    'converter' => 'pandoc (host)'
                ];
                if (!empty($warning)) {
                    $resp['warning'] = $warning;
                }

                return $resp;
            }

            // Host pandoc failed — report collected output to help debugging.
            return [
                'success' => false,
                'error' => 'Host pandoc conversion failed. Ensure `pandoc` is installed on the host and supports the requested output format.',
                'details' => implode("\n---\n", array_filter($collected)),
                'hint' => 'Install pandoc on the host (e.g., apt-get install -y pandoc) or ensure PATH includes the pandoc binary.'
            ];
        }
        
        // Verify output file was created using execInSandbox
        $checkResult = UnifiedSandbox::exec($sandboxId, "test -f \"{$outputFilename}\" && echo 'exists'", $cwd, 10);
        $fileExists = (trim($checkResult[1] ?? '') === 'exists');
        
        if (!$fileExists) {
            return [
                'success' => false,
                'error' => 'Document conversion completed but output file not found',
                'source_file' => $sourcePath
            ];
        }
        
        // Get file size
        $statResult = UnifiedSandbox::exec($sandboxId, "stat -c %s \"{$outputFilename}\" 2>/dev/null || echo 0", $cwd, 10);
        $fileSize = intval(trim($statResult[1] ?? '0'));
        
        // Clean up source markdown file (keep only the final document)
        UnifiedSandbox::deleteItem($sandboxId, $sourcePath);
        
        // Build the download/view URL
        $url = '/clients/' . $sandboxId . '/' . $outputPath;
        
        return [
            'success' => true,
            'path' => $outputPath,
            'filename' => $outputFilename,
            'format' => $formatConfig['name'],
            'format_key' => $format,
            'mime_type' => $formatConfig['mime'],
            'url' => $url,
            'bytes_written' => $fileSize,
            'sandbox_id' => $sandboxId,
            'message' => "Document created: {$outputFilename} ({$formatConfig['name']})",
            'open_hint' => DocumentFormats::getOpenHint($format),
            'converter' => 'pandoc'
        ];
    }

    #[McpTool(
        name: 'sandbox_list_document_formats',
        description: 'List all available document formats that can be created with sandbox_create_document. Returns format key, name, file extension, and description for each format.'
    )]
    public function listDocumentFormats(): array
    {
        return [
            'success' => true,
            'formats' => DocumentFormats::getFormatList(),
            'usage' => 'Call sandbox_create_document with format set to one of the format keys above. Write content in Markdown - it will be converted automatically using Pandoc.',
            'tip' => 'For professional documents, use format="pdf" (default). For Word-editable documents, use format="docx".'
        ];
    }
}
