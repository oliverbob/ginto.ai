<?php
/**
 * Unified Sandbox Interface for Ginto
 * 
 * Provides a consistent API for sandbox operations regardless of backend (LXD or Docker).
 * Automatically selects the appropriate backend based on configuration and availability.
 * 
 * Usage:
 *   use Ginto\Helpers\UnifiedSandbox;
 *   
 *   // Create a sandbox
 *   $result = UnifiedSandbox::create($sandboxId);
 *   
 *   // Execute a command
 *   [$code, $stdout, $stderr] = UnifiedSandbox::exec($sandboxId, 'php -v');
 *   
 *   // Get sandbox IP
 *   $ip = UnifiedSandbox::getIp($sandboxId);
 */
namespace Ginto\Helpers;

class UnifiedSandbox
{
    // Backend types
    const BACKEND_LXD = 'lxd';
    const BACKEND_DOCKER = 'docker';
    const BACKEND_AUTO = 'auto';
    
    // Cached backend choice
    private static ?string $activeBackend = null;
    
    /**
     * Get the active sandbox backend
     */
    public static function getBackend(): string
    {
        if (self::$activeBackend !== null) {
            return self::$activeBackend;
        }
        
        $mode = getenv('SANDBOX_MODE') ?: ($_ENV['SANDBOX_MODE'] ?? $_SERVER['SANDBOX_MODE'] ?? 'auto');
        
        if ($mode === self::BACKEND_LXD) {
            self::$activeBackend = self::BACKEND_LXD;
            return self::$activeBackend;
        }
        
        if ($mode === self::BACKEND_DOCKER) {
            self::$activeBackend = self::BACKEND_DOCKER;
            return self::$activeBackend;
        }
        
        // Auto-detect
        self::$activeBackend = self::detectBackend();
        return self::$activeBackend;
    }
    
    /**
     * Detect the best available backend
     */
    private static function detectBackend(): string
    {
        // If we're inside Docker, prefer Docker sandboxes
        if (file_exists('/.dockerenv')) {
            $dockerCheck = DockerSandboxManager::checkDockerAvailability();
            if ($dockerCheck['available']) {
                return self::BACKEND_DOCKER;
            }
        }
        
        // Check LXD first (preferred for Linux)
        $lxdCheck = LxdSandboxManager::checkLxcAvailability();
        if ($lxdCheck['available']) {
            return self::BACKEND_LXD;
        }
        
        // Fall back to Docker
        $dockerCheck = DockerSandboxManager::checkDockerAvailability();
        if ($dockerCheck['available']) {
            return self::BACKEND_DOCKER;
        }
        
        // Default to LXD (will show appropriate errors)
        return self::BACKEND_LXD;
    }
    
    /**
     * Check sandbox system availability
     */
    public static function checkAvailability(): array
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            return DockerSandboxManager::checkDockerAvailability();
        }
        
        return LxdSandboxManager::checkLxcAvailability();
    }
    
    /**
     * Check if sandboxing is available
     */
    public static function isAvailable(): bool
    {
        $check = self::checkAvailability();
        return $check['available'] ?? false;
    }
    
    /**
     * Get container name for a sandbox ID
     */
    public static function containerName(string $sandboxId): string
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            return DockerSandboxManager::containerName($sandboxId);
        }
        
        return LxdSandboxManager::containerName($sandboxId);
    }
    
    /**
     * Get sandbox IP address
     * 
     * Queries the actual container IP from LXD/Docker (not deterministic).
     * Returns null if container not found or not running.
     */
    public static function getIp(string $sandboxId): ?string
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            // Docker uses deterministic IPs that are actually configured
            return DockerSandboxManager::sandboxToIp($sandboxId);
        }
        
        // LXD: query actual container IP (survives DHCP/mode changes)
        return LxdSandboxManager::getSandboxIp($sandboxId);
    }
    
    /**
     * Check if a sandbox exists
     */
    public static function exists(string $sandboxId): bool
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            return DockerSandboxManager::sandboxExists($sandboxId);
        }
        
        return LxdSandboxManager::sandboxExists($sandboxId);
    }
    
    /**
     * Check if a sandbox is running
     */
    public static function isRunning(string $sandboxId): bool
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            return DockerSandboxManager::sandboxRunning($sandboxId);
        }
        
        return LxdSandboxManager::sandboxExists($sandboxId); // LXD always runs if exists
    }
    
    /**
     * Create a new sandbox
     */
    public static function create(string $sandboxId, ?array $options = null): array
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            return DockerSandboxManager::createSandbox($sandboxId, $options);
        }
        
        // LXD create is typically done via ensureSandboxRunning
        try {
            LxdSandboxManager::ensureSandboxRunning($sandboxId);
            return [
                'success' => true,
                'message' => 'Sandbox created',
                'container_name' => LxdSandboxManager::containerName($sandboxId),
                'ip_address' => LxdSandboxManager::sandboxToIp($sandboxId),
                'backend' => 'lxd'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'backend' => 'lxd'
            ];
        }
    }
    
    /**
     * Create a new sandbox asynchronously (non-blocking)
     * 
     * Returns immediately with operation ID. Poll creationStatus() to check progress.
     */
    public static function createAsync(string $sandboxId, ?array $options = null): array
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            // Docker doesn't have async API, use sync
            return DockerSandboxManager::createSandbox($sandboxId, $options);
        }
        
        // LXD async create
        return LxdSandboxManager::createSandboxAsync($sandboxId);
    }
    
    /**
     * Get sandbox creation status (for polling async creation)
     */
    public static function creationStatus(string $sandboxId): array
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            // Docker: check if container exists and running
            $exists = DockerSandboxManager::sandboxExists($sandboxId);
            $running = $exists ? DockerSandboxManager::sandboxRunning($sandboxId) : false;
            
            return [
                'status' => $exists ? ($running ? 'ready' : 'stopped') : 'not_created',
                'ready' => $running,
                'ip' => $running ? DockerSandboxManager::getSandboxIp($sandboxId) : null,
                'sandboxId' => DockerSandboxManager::containerName($sandboxId),
                'error' => null,
            ];
        }
        
        // LXD: use the async status method
        return LxdSandboxManager::getCreationStatus($sandboxId);
    }
    
    /**
     * Start a sandbox
     */
    public static function start(string $sandboxId): array
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            return DockerSandboxManager::startSandbox($sandboxId);
        }
        
        // LXD start
        try {
            LxdSandboxManager::ensureSandboxRunning($sandboxId);
            return [
                'success' => true,
                'message' => 'Sandbox started',
                'backend' => 'lxd'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'backend' => 'lxd'
            ];
        }
    }
    
    /**
     * Ensure a sandbox is running (alias for start)
     */
    public static function ensureRunning(string $sandboxId): array
    {
        return self::start($sandboxId);
    }
    
    /**
     * Stop a sandbox
     */
    public static function stop(string $sandboxId): array
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            return DockerSandboxManager::stopSandbox($sandboxId);
        }
        
        // LXD stop
        $containerName = LxdSandboxManager::containerName($sandboxId);
        exec(LxdSandboxManager::LXC_CMD . ' stop ' . escapeshellarg($containerName) . ' --force 2>&1', $output, $code);
        
        return [
            'success' => ($code === 0),
            'message' => ($code === 0) ? 'Sandbox stopped' : implode("\n", $output),
            'backend' => 'lxd'
        ];
    }
    
    /**
     * Delete a sandbox
     */
    public static function delete(string $sandboxId): array
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            return DockerSandboxManager::deleteSandbox($sandboxId);
        }
        
        // LXD delete
        $containerName = LxdSandboxManager::containerName($sandboxId);
        exec(LxdSandboxManager::LXC_CMD . ' delete ' . escapeshellarg($containerName) . ' --force 2>&1', $output, $code);
        
        return [
            'success' => ($code === 0),
            'message' => ($code === 0) ? 'Sandbox deleted' : implode("\n", $output),
            'backend' => 'lxd'
        ];
    }
    
    /**
     * Execute a command in a sandbox
     * Returns: [exit_code, stdout, stderr]
     */
    public static function exec(string $sandboxId, string $command, string $cwd = '/home/sandbox', int $timeout = 30): array
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            return DockerSandboxManager::execInSandbox($sandboxId, $command, $cwd, $timeout);
        }
        
        return LxdSandboxManager::execInSandbox($sandboxId, $command, $cwd, $timeout);
    }
    
    /**
     * Read a file from the sandbox
     */
    public static function readFile(string $sandboxId, string $filePath): array
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            return DockerSandboxManager::readFile($sandboxId, $filePath);
        }
        
        // LXD read file
        [$code, $stdout, $stderr] = LxdSandboxManager::execInSandbox($sandboxId, 'cat ' . escapeshellarg($filePath));

        if ($code !== 0) {
            return [
                'success' => false,
                'error' => $stderr ?: 'Failed to read file'
            ];
        }

        // Detect binary data (NUL byte or non-UTF8) and return base64 when binary
        $isBinary = false;
        if (strpos($stdout, "\0") !== false) {
            $isBinary = true;
        } else {
            // Use mb_check_encoding if available to determine UTF-8 validity
            if (function_exists('mb_check_encoding')) {
                if (!mb_check_encoding($stdout, 'UTF-8')) {
                    $isBinary = true;
                }
            }
        }

        if ($isBinary) {
            return [
                'success' => true,
                'content' => base64_encode($stdout),
                'is_binary' => true
            ];
        }

        return [
            'success' => true,
            'content' => $stdout,
            'is_binary' => false
        ];
    }
    
    /**
     * Write a file to the sandbox
     */
    public static function writeFile(string $sandboxId, string $filePath, string $content): array
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            // Ensure parent directory exists inside Docker sandbox
            $dir = dirname($filePath);
            if ($dir !== '.' && $dir !== '/') {
                [$dcode, , $dstderr] = DockerSandboxManager::execInSandbox($sandboxId, 'mkdir -p ' . escapeshellarg($dir), '/home/sandbox', 10);
                if (($dcode ?? 1) !== 0) {
                    return [
                        'success' => false,
                        'error' => 'Failed to create directory: ' . ($dstderr ?? 'unknown error')
                    ];
                }
            }

            $result = DockerSandboxManager::writeFile($sandboxId, $filePath, $content);
            if (!empty($result['success'])) {
                $result['bytes'] = strlen($content);
            }
            return $result;
        }
        
        // LXD write file
        $dir = dirname($filePath);
        if ($dir !== '.' && $dir !== '/') {
            [$dcode, , $dstderr] = LxdSandboxManager::execInSandbox($sandboxId, 'mkdir -p ' . escapeshellarg($dir));
            if (($dcode ?? 1) !== 0) {
                return [
                    'success' => false,
                    'error' => 'Failed to create directory: ' . ($dstderr ?? 'unknown error')
                ];
            }
        }

        $encoded = base64_encode($content);
        $cmd = 'echo ' . escapeshellarg($encoded) . ' | base64 -d > ' . escapeshellarg($filePath);
        [$code, $stdout, $stderr] = LxdSandboxManager::execInSandbox($sandboxId, $cmd);

        if ($code !== 0) {
            return [
                'success' => false,
                'error' => $stderr ?: 'Failed to write file'
            ];
        }

        return [
            'success' => true,
            'message' => 'File written successfully',
            'path' => $filePath,
            'bytes' => strlen($content)
        ];
    }
    
    /**
     * Get the home directory path for sandboxes
     * Docker: /home/sandbox (user home)
     * LXD: /root (root user home directory)
     */
    public static function getHomePath(): string
    {
        $backend = self::getBackend();
        return ($backend === self::BACKEND_DOCKER) ? '/home/sandbox' : '/root';
    }

    /**
     * Detect which backend actually hosts a given sandbox ID.
     * Returns 'docker', 'lxd', or null if not found.
     * This performs an existence check against both managers to resolve
     * sandbox ownership regardless of the globally configured backend.
     */
    public static function detectBackendForSandbox(string $sandboxId): ?string
    {
        // Check Docker first
        try {
            if (class_exists('\\Ginto\\Helpers\\DockerSandboxManager') && DockerSandboxManager::sandboxExists($sandboxId)) {
                return self::BACKEND_DOCKER;
            }
        } catch (\Throwable $_) {}

        // Then check LXD
        try {
            if (class_exists('\\Ginto\\Helpers\\LxdSandboxManager') && LxdSandboxManager::sandboxExists($sandboxId)) {
                return self::BACKEND_LXD;
            }
        } catch (\Throwable $_) {}

        // Not found explicitly
        return null;
    }

    /**
     * Resolve a home path for a specific sandbox by detecting the
     * backend that actually contains the sandbox. Falls back to
     * the global backend if detection fails.
     */
    public static function getHomePathForSandbox(string $sandboxId): string
    {
        $detected = self::detectBackendForSandbox($sandboxId);
        if ($detected === self::BACKEND_DOCKER) {
            return '/home/sandbox';
        }
        if ($detected === self::BACKEND_LXD) {
            return '/root';
        }

        // Fallback to global home path
        return self::getHomePath();
    }
    
    /**
     * List files in a sandbox directory
     */
    public static function listFiles(string $sandboxId, ?string $path = null, int $maxDepth = 5): array
    {
        // Prefer per-sandbox detection: the sandbox may be hosted by Docker or LXD
        // regardless of the globally configured backend. Fall back to global
        // backend if detection fails.
        $detected = self::detectBackendForSandbox($sandboxId);
        $backend = $detected ?? self::getBackend();
        $homePath = self::getHomePath();
        $targetPath = $path ?? $homePath;

        if ($backend === self::BACKEND_DOCKER) {
            return DockerSandboxManager::listFiles($sandboxId, $targetPath, $maxDepth);
        }

        return LxdSandboxManager::listFiles($sandboxId, $targetPath, $maxDepth);
    }
    
    /**
     * List all sandboxes
     */
    public static function listSandboxes(): array
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            return DockerSandboxManager::listSandboxes();
        }
        
        // LXD list
        $prefix = LxdSandboxManager::CONTAINER_PREFIX;
        $cmd = LxdSandboxManager::LXC_CMD . ' list --format csv -c n,s 2>/dev/null';
        exec($cmd, $output, $code);
        
        if ($code !== 0) {
            return [];
        }
        
        $sandboxes = [];
        foreach ($output as $line) {
            $parts = explode(',', $line);
            if (count($parts) >= 2) {
                $name = trim($parts[0]);
                if (strpos($name, $prefix) === 0) {
                    $sandboxId = str_replace($prefix, '', $name);
                    $sandboxes[] = [
                        'sandbox_id' => $sandboxId,
                        'container_name' => $name,
                        'status' => trim($parts[1]),
                        'running' => (trim($parts[1]) === 'RUNNING')
                    ];
                }
            }
        }
        
        return $sandboxes;
    }
    
    /**
     * Get sandbox status
     */
    public static function getStatus(string $sandboxId): array
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            $status = DockerSandboxManager::getSandboxStatus($sandboxId);
            $status['backend'] = 'docker';
            return $status;
        }
        
        // LXD status
        $exists = LxdSandboxManager::sandboxExists($sandboxId);
        
        return [
            'exists' => $exists,
            'running' => $exists,
            'status' => $exists ? 'running' : 'not_found',
            'ip_address' => $exists ? LxdSandboxManager::sandboxToIp($sandboxId) : null,
            'backend' => 'lxd'
        ];
    }
    
    /**
     * Get info about the current backend
     */
    public static function getBackendInfo(): array
    {
        $backend = self::getBackend();
        
        return [
            'backend' => $backend,
            'available' => self::isAvailable(),
            'details' => self::checkAvailability()
        ];
    }
    
    /**
     * Delete sandbox completely (container + DB + Redis + directory)
     * Unified interface for complete sandbox cleanup
     * 
     * @param string $sandboxId The sandbox ID
     * @param \Medoo\Medoo|null $db Database connection (optional, will create if needed)
     * @return array ['success' => bool, 'deleted' => [...], 'errors' => [...], 'backend' => string]
     */
    public static function deleteCompletely(string $sandboxId, $db = null): array
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            // Docker sandbox cleanup
            $result = [
                'success' => true,
                'deleted' => [],
                'errors' => [],
                'backend' => 'docker'
            ];
            
            // 1. Delete Docker container
            $deleteResult = DockerSandboxManager::deleteSandbox($sandboxId);
            if ($deleteResult['success']) {
                $result['deleted'][] = 'container';
            } else {
                $result['errors'][] = $deleteResult['error'] ?? 'Failed to delete container';
                $result['success'] = false;
            }
            
            // 2. Delete database entry
            if ($db === null) {
                try {
                    if (class_exists('\\Ginto\\Core\\Database')) {
                        $db = \Ginto\Core\Database::getConnection();
                    }
                } catch (\Throwable $e) {}
            }
            
            if ($db) {
                try {
                    $db->delete('client_sandboxes', ['sandbox_id' => $sandboxId]);
                    $result['deleted'][] = 'database';
                } catch (\Throwable $e) {
                    $result['errors'][] = 'Failed to delete database entry: ' . $e->getMessage();
                }
            }
            
            // 3. Delete clients directory if exists
            $clientsDir = defined('ROOT_PATH') ? ROOT_PATH . '/clients/' . $sandboxId : null;
            if (!$clientsDir) {
                $clientsDir = dirname(dirname(__DIR__)) . '/clients/' . $sandboxId;
            }
            
            if (is_dir($clientsDir)) {
                self::deleteDirectoryRecursive($clientsDir);
                if (!is_dir($clientsDir)) {
                    $result['deleted'][] = 'directory';
                } else {
                    $result['errors'][] = 'Failed to delete directory: ' . $clientsDir;
                }
            } else {
                $result['deleted'][] = 'directory';
            }
            
            return $result;
        }
        
        // LXD sandbox cleanup - use existing method
        $result = LxdSandboxManager::deleteSandboxCompletely($sandboxId, $db);
        $result['backend'] = 'lxd';
        return $result;
    }
    
    /**
     * Recursively delete a directory
     */
    private static function deleteDirectoryRecursive(string $path): bool
    {
        if (!is_dir($path)) {
            return true;
        }
        
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $itemPath = $path . '/' . $item;
            if (is_dir($itemPath)) {
                self::deleteDirectoryRecursive($itemPath);
            } else {
                @unlink($itemPath);
            }
        }
        
        return @rmdir($path);
    }
    
    /**
     * Check if a path exists in the sandbox
     */
    public static function pathExists(string $sandboxId, string $path): bool
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            // Docker: use exec to check
            $result = DockerSandboxManager::execInSandbox($sandboxId, 'test -e ' . escapeshellarg($path) . ' && echo 1 || echo 0', '/home/sandbox', 5);
            return isset($result[1]) && trim($result[1]) === '1';
        }
        
        return LxdSandboxManager::pathExists($sandboxId, $path);
    }
    
    /**
     * Create a file or folder in the sandbox
     */
    public static function createItem(string $sandboxId, string $path, string $type = 'file'): array
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            if ($type === 'folder' || $type === 'directory') {
                $result = DockerSandboxManager::execInSandbox($sandboxId, 'mkdir -p ' . escapeshellarg($path), '/home/sandbox', 10);
                return [
                    'success' => ($result[0] ?? 1) === 0,
                    'error' => $result[2] ?? null
                ];
            } else {
                // Create parent directory first, then touch file
                $dir = dirname($path);
                if ($dir !== '.' && $dir !== '/') {
                    DockerSandboxManager::execInSandbox($sandboxId, 'mkdir -p ' . escapeshellarg($dir), '/home/sandbox', 10);
                }
                $result = DockerSandboxManager::execInSandbox($sandboxId, 'touch ' . escapeshellarg($path), '/home/sandbox', 10);
                return [
                    'success' => ($result[0] ?? 1) === 0,
                    'error' => $result[2] ?? null
                ];
            }
        }
        
        return LxdSandboxManager::createItem($sandboxId, $path, $type);
    }
    
    /**
     * Delete a file or folder in the sandbox
     */
    public static function deleteItem(string $sandboxId, string $path): array
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            $result = DockerSandboxManager::execInSandbox($sandboxId, 'rm -rf ' . escapeshellarg($path), '/home/sandbox', 10);
            return [
                'success' => ($result[0] ?? 1) === 0,
                'error' => $result[2] ?? null
            ];
        }
        
        return LxdSandboxManager::deleteItem($sandboxId, $path);
    }
    
    /**
     * Rename/move a file or folder in the sandbox
     */
    public static function renameItem(string $sandboxId, string $oldPath, string $newPath): array
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            // Ensure parent directory exists
            $dir = dirname($newPath);
            if ($dir !== '.' && $dir !== '/') {
                DockerSandboxManager::execInSandbox($sandboxId, 'mkdir -p ' . escapeshellarg($dir), '/home/sandbox', 10);
            }
            $result = DockerSandboxManager::execInSandbox($sandboxId, 'mv ' . escapeshellarg($oldPath) . ' ' . escapeshellarg($newPath), '/home/sandbox', 10);
            return [
                'success' => ($result[0] ?? 1) === 0,
                'error' => $result[2] ?? null
            ];
        }
        
        return LxdSandboxManager::renameItem($sandboxId, $oldPath, $newPath);
    }
    
    /**
     * Copy a file or folder in the sandbox
     */
    public static function copyItem(string $sandboxId, string $sourcePath, string $destPath): array
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            // Ensure parent directory exists
            $dir = dirname($destPath);
            if ($dir !== '.' && $dir !== '/') {
                DockerSandboxManager::execInSandbox($sandboxId, 'mkdir -p ' . escapeshellarg($dir), '/home/sandbox', 10);
            }
            $result = DockerSandboxManager::execInSandbox($sandboxId, 'cp -r ' . escapeshellarg($sourcePath) . ' ' . escapeshellarg($destPath), '/home/sandbox', 10);
            return [
                'success' => ($result[0] ?? 1) === 0,
                'error' => $result[2] ?? null
            ];
        }
        
        return LxdSandboxManager::copyItem($sandboxId, $sourcePath, $destPath);
    }

    /**
     * Job queue directory for async commands
     */
    public static function getJobDir(): string
    {
        // Prefer storing jobs in a sibling `storage/` directory located
        // next to the project directory (../storage). This keeps runtime
        // files outside the repository folder (e.g. ~/ginto.ai -> ~/storage).
        $projectRoot = defined('ROOT_PATH') ? ROOT_PATH : dirname(dirname(__DIR__));
        $storageRoot = dirname($projectRoot) . '/storage';
        $dir = $storageRoot . '/var/sandbox_jobs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Enqueue a command to be executed by an external worker.
     * Returns ['success'=>true,'job_id'=>string]
     */
    public static function enqueueJob(string $sandboxId, string $command, string $cwd = '/home/sandbox', int $timeout = 300, array $meta = []): array
    {
        try {
            $jobDir = self::getJobDir();
            $id = 'job_' . bin2hex(random_bytes(8));
            $file = $jobDir . '/' . $id . '.json';
            $payload = [
                'id' => $id,
                'sandbox_id' => $sandboxId,
                'command' => $command,
                'cwd' => $cwd,
                'timeout' => $timeout,
                'meta' => $meta,
                'status' => 'queued',
                'created_at' => time()
            ];
            file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return ['success' => true, 'job_id' => $id];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get job status/result for a queued job
     */
    public static function getJobStatus(string $jobId): array
    {
        $jobDir = self::getJobDir();
        $file = $jobDir . '/' . $jobId . '.json';
        if (!file_exists($file)) {
            return ['success' => false, 'error' => 'Job not found'];
        }
        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) {
            return ['success' => false, 'error' => 'Invalid job file'];
        }
        return array_merge(['success' => true], $data);
    }
}
