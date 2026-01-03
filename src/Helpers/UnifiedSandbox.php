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
     * Convert sandbox ID to IP address
     */
    public static function getIp(string $sandboxId): string
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            return DockerSandboxManager::sandboxToIp($sandboxId);
        }
        
        return LxdSandboxManager::sandboxToIp($sandboxId);
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
        
        // LXD create is typically done via ensureSandbox
        try {
            LxdSandboxManager::ensureSandbox($sandboxId);
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
            LxdSandboxManager::ensureSandbox($sandboxId);
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
        
        return [
            'success' => true,
            'content' => $stdout
        ];
    }
    
    /**
     * Write a file to the sandbox
     */
    public static function writeFile(string $sandboxId, string $filePath, string $content): array
    {
        $backend = self::getBackend();
        
        if ($backend === self::BACKEND_DOCKER) {
            return DockerSandboxManager::writeFile($sandboxId, $filePath, $content);
        }
        
        // LXD write file
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
            'path' => $filePath
        ];
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
}
