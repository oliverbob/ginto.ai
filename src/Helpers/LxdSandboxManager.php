<?php
/**
 * LXD-based Sandbox Manager for Ginto
 * 
 * Manages user sandboxes using LXD containers (Alpine Linux).
 * Uses Redis to cache user->containerIP mappings for fast lookups.
 * 
 * IMPORTANT: This manager uses 'sudo lxc' for all commands. The web server user
 * (www-data or oliverbob) must have passwordless sudo access to lxc commands.
 * See /etc/sudoers.d/ginto-lxd for the required configuration.
 * 
 * @see docs/sandbox.md for full architecture documentation
 */
namespace Ginto\Helpers;

class LxdSandboxManager
{
    // LXC command - uses sudo for permission elevation
    const LXC_CMD = 'sudo /snap/bin/lxc';
    
    // Base image name - the pre-configured ginto-sandbox template
    // This image contains: PHP 8.2 (with extensions), Composer, Node.js 20, npm, 
    // Python 3.12, Git, MySQL client, SQLite, Caddy, Vim, Nano
    // Services (php-fpm82, caddy) auto-start via OpenRC
    const BASE_IMAGE = 'ginto-sandbox';
    
    // Prefix for all sandbox container names
    const CONTAINER_PREFIX = 'sandbox-';
    
    // Redis key prefix for user->IP mapping
    const REDIS_PREFIX = 'sandbox:';
    
    // Additional packages to install (base image already has most tools)
    // Only used if specific extra packages are requested
    const DEFAULT_PACKAGES = [];
    
    // Resource limits (higher for nesting support)
    const DEFAULT_CPU_LIMIT = '2';
    const DEFAULT_MEMORY_LIMIT = '1GB';
    const DEFAULT_DISK_LIMIT = '2GB';
    
    // Process limit (higher for nested containers)
    const DEFAULT_PROCESS_LIMIT = '200';
    
    /**
     * Check if LXC/LXD is installed and available on this system
     * Returns detailed status for error handling
     */
    public static function checkLxcAvailability(): array
    {
        // Check if lxc command exists
        $lxcPath = '/snap/bin/lxc';
        if (!file_exists($lxcPath)) {
            // Check if it's installed via apt instead of snap
            exec('which lxc 2>/dev/null', $output, $code);
            if ($code !== 0) {
                return [
                    'available' => false,
                    'error' => 'lxc_not_installed',
                    'message' => 'LXC/LXD is not installed on this system.',
                    'install_command' => 'sudo snap install lxd --channel=latest/stable && sudo lxd init --auto'
                ];
            }
            // LXC is installed via apt, not snap - update the constant dynamically
            $lxcPath = trim($output[0] ?? 'lxc');
        }
        
        // Check if we can run lxc with sudo
        exec('sudo ' . escapeshellarg($lxcPath) . ' version 2>&1', $output, $code);
        if ($code !== 0) {
            $errorMsg = implode("\n", $output);
            
            // Check for sudo permission issues
            if (stripos($errorMsg, 'password') !== false || stripos($errorMsg, 'sudo') !== false) {
                return [
                    'available' => false,
                    'error' => 'lxc_sudo_permission',
                    'message' => 'The web server user needs passwordless sudo access to lxc.',
                    'install_command' => 'sudo bash ~/ginto/bin/ginto.sh install'
                ];
            }
            
            // LXD not initialized
            if (stripos($errorMsg, 'not initialized') !== false || stripos($errorMsg, 'lxd init') !== false) {
                return [
                    'available' => false,
                    'error' => 'lxd_not_initialized',
                    'message' => 'LXD is installed but not initialized.',
                    'install_command' => 'sudo lxd init --auto'
                ];
            }
            
            return [
                'available' => false,
                'error' => 'lxc_execution_failed',
                'message' => 'Failed to execute lxc: ' . $errorMsg,
                'install_command' => 'sudo bash ~/ginto/bin/ginto.sh install'
            ];
        }
        
        // Check if base image exists
        exec(self::LXC_CMD . ' image list ' . self::BASE_IMAGE . ' --format csv 2>/dev/null', $imageOutput, $imageCode);
        if ($imageCode !== 0 || empty(trim(implode('', $imageOutput)))) {
            return [
                'available' => false,
                'error' => 'base_image_missing',
                'message' => 'The ginto-sandbox base image is not available.',
                'install_command' => 'sudo bash ~/ginto/bin/ginto.sh install'
            ];
        }
        
        return [
            'available' => true,
            'error' => null,
            'message' => 'LXC/LXD is properly configured.',
            'lxc_path' => $lxcPath
        ];
    }

    /**
     * Get the container name for a user ID
     */
    public static function containerName(string $userId): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $userId);
        return self::CONTAINER_PREFIX . $safe;
    }
    
    /**
     * Get a Redis connection (returns null if Redis unavailable)
     */
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
    
    /**
     * Check if a sandbox exists for the given user
     */
    public static function sandboxExists(string $userId): bool
    {
        $name = self::containerName($userId);
        
        // Check via lxc list
        $output = [];
        $code = 0;
        exec(self::LXC_CMD . " list --format csv -c n 2>/dev/null", $output, $code);
        
        if ($code !== 0) {
            return false;
        }
        
        return in_array($name, $output, true);
    }
    
    /**
     * Check if a sandbox is running
     */
    public static function sandboxRunning(string $userId): bool
    {
        $name = self::containerName($userId);
        
        $output = [];
        $code = 0;
        exec(self::LXC_CMD . " list $name --format csv -c s 2>/dev/null", $output, $code);
        
        if ($code !== 0 || empty($output)) {
            return false;
        }
        
        return strtolower(trim($output[0])) === 'running';
    }
    
    /**
     * Get the IP address of a sandbox
     */
    public static function getSandboxIp(string $userId): ?string
    {
        // Try Redis cache first
        $redis = self::getRedis();
        if ($redis) {
            $cached = $redis->get(self::REDIS_PREFIX . $userId);
            if ($cached) {
                return $cached;
            }
        }
        
        // Get from LXD
        $name = self::containerName($userId);
        $output = [];
        $code = 0;
        exec(self::LXC_CMD . " list $name -c 4 --format csv 2>/dev/null", $output, $code);
        
        if ($code !== 0 || empty($output)) {
            return null;
        }
        
        // Format is: "10.x.x.x (eth0)"
        $ip = trim(explode(' ', $output[0])[0]);
        
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
            // Cache in Redis
            if ($redis) {
                $redis->set(self::REDIS_PREFIX . $userId, $ip);
            }
            return $ip;
        }
        
        return null;
    }
    
    /**
     * Create a new sandbox for a user
     * 
     * @param string $userId User identifier
     * @param array $options Optional settings: packages, cpu, memory, disk
     * @return array ['success' => bool, 'ip' => string|null, 'sandboxId' => string, 'error' => string|null]
     */
    public static function createSandbox(string $userId, array $options = []): array
    {
        $name = self::containerName($userId);
        
        // Check if already exists
        if (self::sandboxExists($userId)) {
            // Start if not running
            if (!self::sandboxRunning($userId)) {
                exec(self::LXC_CMD . " start $name < /dev/null 2>&1", $output, $code);
                sleep(2); // Wait for container to get IP
            }
            
            $ip = self::getSandboxIp($userId);
            return [
                'success' => true,
                'ip' => $ip,
                'sandboxId' => $name,
                'error' => null,
                'message' => 'Sandbox already exists'
            ];
        }
        
        // Launch new container from base image
        // NOTE: Redirect stdin from /dev/null to prevent "bad file descriptor" error
        // when running from PHP web context (no tty available)
        $output = [];
        $code = 0;
        exec(self::LXC_CMD . " launch " . self::BASE_IMAGE . " $name < /dev/null 2>&1", $output, $code);
        
        if ($code !== 0) {
            return [
                'success' => false,
                'ip' => null,
                'sandboxId' => $name,
                'error' => 'Failed to launch container: ' . implode("\n", $output)
            ];
        }
        
        // Wait for container to be ready
        sleep(3);
        
        // =======================================================================
        // SECURITY HARDENING - Proxmox-style with nesting enabled
        // =======================================================================
        
        // Enable nesting (allows Docker/LXC inside) with protection
        exec(self::LXC_CMD . " config set $name security.nesting=true 2>&1");
        exec(self::LXC_CMD . " config set $name security.privileged=false 2>&1");
        exec(self::LXC_CMD . " config set $name security.idmap.isolated=true 2>&1");
        
        // Syscall interception for safe nesting
        exec(self::LXC_CMD . " config set $name security.syscalls.intercept.mount=true 2>&1");
        exec(self::LXC_CMD . " config set $name security.syscalls.intercept.mount.allowed=ext4,tmpfs,proc,sysfs,cgroup,cgroup2,overlay 2>&1");
        exec(self::LXC_CMD . " config set $name security.syscalls.intercept.mount.shift=true 2>&1");
        exec(self::LXC_CMD . " config set $name security.syscalls.intercept.mknod=true 2>&1");
        exec(self::LXC_CMD . " config set $name security.syscalls.intercept.setxattr=true 2>&1");
        exec(self::LXC_CMD . " config set $name security.syscalls.intercept.bpf=true 2>&1");
        exec(self::LXC_CMD . " config set $name security.syscalls.intercept.bpf.devices=true 2>&1");
        
        // Resource Limits: Apply to container AND all nested containers
        $cpu = $options['cpu'] ?? '2';  // 2 cores for nesting
        $memory = $options['memory'] ?? '1GB';  // 1GB for nesting overhead
        exec(self::LXC_CMD . " config set $name limits.cpu $cpu 2>&1");
        exec(self::LXC_CMD . " config set $name limits.memory $memory 2>&1");
        exec(self::LXC_CMD . " config set $name limits.processes 200 2>&1");  // More for nested
        exec(self::LXC_CMD . " config set $name limits.disk.priority=5 2>&1");
        
        // Kernel Protection
        exec(self::LXC_CMD . " config set $name linux.kernel_modules=\"\" 2>&1");
        
        // =======================================================================
        
        // Install additional packages only if explicitly requested
        // The ginto-sandbox template already includes: PHP 8.2 (with extensions), 
        // Composer, Node.js, npm, Python, Git, MySQL client, SQLite, Caddy
        $packages = $options['packages'] ?? [];
        if (!empty($packages)) {
            $pkgList = implode(' ', $packages);
            exec(self::LXC_CMD . " exec $name -- apk update 2>&1", $output, $code);
            exec(self::LXC_CMD . " exec $name -- apk add --no-cache $pkgList 2>&1", $output, $code);
        }
        
        // Services (php-fpm82, caddy) auto-start via OpenRC in the template
        // Just ensure they're running after container launch
        exec(self::LXC_CMD . " exec $name -- rc-service php-fpm82 start 2>&1");
        exec(self::LXC_CMD . " exec $name -- rc-service caddy start 2>&1");
        
        // Get IP
        $ip = self::getSandboxIp($userId);
        
        return [
            'success' => true,
            'ip' => $ip,
            'sandboxId' => $name,
            'error' => null
        ];
    }
    
    /**
     * Ensure services are running inside a container
     * 
     * The ginto-sandbox template already has services configured to auto-start,
     * but this method can be used to restart services if needed.
     */
    public static function ensureServicesRunning(string $containerName): void
    {
        // Ensure PHP-FPM is running
        exec(self::LXC_CMD . " exec $containerName -- rc-service php-fpm82 start 2>&1");
        
        // Ensure Caddy is running
        exec(self::LXC_CMD . " exec $containerName -- rc-service caddy start 2>&1");
    }
    
    /**
     * Setup Caddy web server inside a container (legacy - kept for compatibility)
     * 
     * Note: The ginto-sandbox template already has Caddy configured.
     * This method is only needed for custom configurations.
     */
    private static function setupContainerCaddy(string $containerName): void
    {
        // Just ensure services are running - template already has config
        self::ensureServicesRunning($containerName);
    }
    
    /**
     * Execute a command inside a sandbox
     * 
     * @return array [exit_code, stdout, stderr]
     */
    public static function execInSandbox(string $userId, string $command, string $cwd = '/home', int $timeout = 30): array
    {
        $name = self::containerName($userId);
        
        if (!self::sandboxExists($userId)) {
            return [1, '', 'Sandbox does not exist'];
        }
        
        if (!self::sandboxRunning($userId)) {
            exec(self::LXC_CMD . " start $name 2>&1");
            sleep(2);
        }
        
        $escapedCmd = escapeshellarg("cd $cwd && $command");
        $fullCmd = "timeout $timeout " . self::LXC_CMD . " exec $name -- /bin/sh -c $escapedCmd 2>&1";
        
        $output = [];
        $code = 0;
        exec($fullCmd, $output, $code);
        
        return [$code, implode("\n", $output), ''];
    }
    
    /**
     * Copy files into a sandbox
     */
    public static function pushFile(string $userId, string $localPath, string $remotePath): bool
    {
        $name = self::containerName($userId);
        
        if (!file_exists($localPath)) {
            return false;
        }
        
        $output = [];
        $code = 0;
        exec(self::LXC_CMD . " file push " . escapeshellarg($localPath) . " $name" . escapeshellarg($remotePath) . " 2>&1", $output, $code);
        
        return $code === 0;
    }
    
    /**
     * Copy files out of a sandbox
     */
    public static function pullFile(string $userId, string $remotePath, string $localPath): bool
    {
        $name = self::containerName($userId);
        
        $output = [];
        $code = 0;
        exec(self::LXC_CMD . " file pull $name" . escapeshellarg($remotePath) . " " . escapeshellarg($localPath) . " 2>&1", $output, $code);
        
        return $code === 0;
    }
    
    /**
     * Stop a sandbox (but don't delete it)
     */
    public static function stopSandbox(string $userId): bool
    {
        $name = self::containerName($userId);
        
        if (!self::sandboxExists($userId)) {
            return true; // Already doesn't exist
        }
        
        $output = [];
        $code = 0;
        exec(self::LXC_CMD . " stop $name 2>&1", $output, $code);
        
        return $code === 0;
    }
    
    /**
     * Delete a sandbox completely (LXC container + Redis cache only)
     * 
     * @deprecated Use deleteSandboxCompletely() for full atomic cleanup
     */
    public static function deleteSandbox(string $userId): bool
    {
        $name = self::containerName($userId);
        
        if (!self::sandboxExists($userId)) {
            // Remove from Redis cache anyway
            $redis = self::getRedis();
            if ($redis) {
                $redis->del(self::REDIS_PREFIX . $userId);
            }
            return true;
        }
        
        // Stop first
        exec(self::LXC_CMD . " stop $name --force 2>&1");
        
        // Delete
        $output = [];
        $code = 0;
        exec(self::LXC_CMD . " delete $name 2>&1", $output, $code);
        
        // Remove from Redis cache
        $redis = self::getRedis();
        if ($redis) {
            $redis->del(self::REDIS_PREFIX . $userId);
        }
        
        return $code === 0;
    }
    
    /**
     * Delete a sandbox completely and atomically:
     * - LXC container (stop + delete)
     * - Database entry from client_sandboxes
     * - Redis cache
     * - /clients/{sandboxId}/ directory
     * 
     * This ensures no orphaned resources remain.
     * 
     * @param string $sandboxId The sandbox ID
     * @param \Medoo\Medoo|null $db Database connection (optional, will create if needed)
     * @return array ['success' => bool, 'deleted' => ['container', 'database', 'redis', 'directory']]
     */
    public static function deleteSandboxCompletely(string $sandboxId, $db = null): array
    {
        $result = [
            'success' => true,
            'deleted' => [],
            'errors' => []
        ];
        
        $name = self::containerName($sandboxId);
        
        // 1. Delete LXC container
        if (self::sandboxExists($sandboxId)) {
            exec(self::LXC_CMD . " stop $name --force 2>&1");
            $output = [];
            $code = 0;
            exec(self::LXC_CMD . " delete $name 2>&1", $output, $code);
            
            if ($code === 0) {
                $result['deleted'][] = 'container';
            } else {
                $result['errors'][] = 'Failed to delete container: ' . implode(' ', $output);
                $result['success'] = false;
            }
        } else {
            $result['deleted'][] = 'container'; // Already gone
        }
        
        // 2. Delete Redis cache
        $redis = self::getRedis();
        if ($redis) {
            $redis->del(self::REDIS_PREFIX . $sandboxId);
            $result['deleted'][] = 'redis';
        }
        
        // 3. Delete database entry
        if ($db === null) {
            // Try to get database connection
            try {
                if (class_exists('\\Ginto\\Core\\Database')) {
                    $db = \Ginto\Core\Database::getConnection();
                }
            } catch (\Throwable $e) {
                // Ignore - will skip DB cleanup
            }
        }
        
        if ($db) {
            try {
                $deleted = $db->delete('client_sandboxes', ['sandbox_id' => $sandboxId]);
                if ($deleted && $deleted->rowCount() > 0) {
                    $result['deleted'][] = 'database';
                } else {
                    $result['deleted'][] = 'database'; // Already gone or didn't exist
                }
            } catch (\Throwable $e) {
                $result['errors'][] = 'Failed to delete database entry: ' . $e->getMessage();
            }
        }
        
        // 4. Delete /clients/{sandboxId}/ directory
        $clientsDir = defined('ROOT_PATH') ? ROOT_PATH . '/clients/' . $sandboxId : null;
        if (!$clientsDir) {
            // Try to find it relative to this file
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
            $result['deleted'][] = 'directory'; // Already gone
        }
        
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
     * List all sandboxes
     * 
     * @return array Array of ['name' => string, 'status' => string, 'ip' => string|null]
     */
    public static function listSandboxes(): array
    {
        $output = [];
        $code = 0;
        exec(self::LXC_CMD . " list --format csv -c ns4 2>/dev/null", $output, $code);
        
        if ($code !== 0) {
            return [];
        }
        
        $sandboxes = [];
        foreach ($output as $line) {
            $parts = explode(',', $line);
            if (count($parts) >= 2) {
                $name = trim($parts[0]);
                if (strpos($name, self::CONTAINER_PREFIX) === 0) {
                    $sandboxes[] = [
                        'name' => $name,
                        'status' => trim($parts[1]),
                        'ip' => isset($parts[2]) ? trim(explode(' ', $parts[2])[0]) : null
                    ];
                }
            }
        }
        
        return $sandboxes;
    }
    
    /**
     * Ensure a sandbox is running, creating it if necessary
     * 
     * @return bool True if sandbox is now running
     */
    public static function ensureSandboxRunning(string $userId, ?string $hostPath = null): bool
    {
        if (self::sandboxRunning($userId)) {
            return true;
        }
        
        if (self::sandboxExists($userId)) {
            $name = self::containerName($userId);
            exec(self::LXC_CMD . " start $name 2>&1", $output, $code);
            sleep(2);
            return self::sandboxRunning($userId);
        }
        
        // Create new sandbox
        $result = self::createSandbox($userId);
        return $result['success'] && self::sandboxRunning($userId);
    }
    
    /**
     * Get published port - returns the container IP + port 80 URL
     */
    public static function getPublishedPort(string $userId, int $containerPort = 80): ?string
    {
        $ip = self::getSandboxIp($userId);
        if (!$ip) {
            return null;
        }
        
        return "http://$ip:$containerPort";
    }
    
    /**
     * List files inside a sandbox container
     * 
     * @param string $userId User/sandbox identifier
     * @param string $remotePath Path inside container (default: /home)
     * @param int $maxDepth Maximum recursion depth
     * @return array ['success' => bool, 'tree' => array, 'error' => string|null]
     */
    public static function listFiles(string $userId, string $remotePath = '/home', int $maxDepth = 5): array
    {
        $name = self::containerName($userId);
        
        if (!self::sandboxExists($userId)) {
            return ['success' => false, 'tree' => [], 'error' => 'Sandbox does not exist'];
        }
        
        if (!self::sandboxRunning($userId)) {
            exec(self::LXC_CMD . " start $name 2>&1", $output, $code);
            sleep(2);
            if (!self::sandboxRunning($userId)) {
                return ['success' => false, 'tree' => [], 'error' => 'Failed to start sandbox'];
            }
        }
        
        // Use find command compatible with BusyBox (Alpine Linux)
        // Get directories and files separately, then combine
        $escapedPath = escapeshellarg($remotePath);
        $remotePathTrailing = rtrim($remotePath, '/') . '/';
        
        // Get directories (output: d|relative_path) - skip the root directory itself
        $dirCmd = "find $escapedPath -mindepth 1 -maxdepth $maxDepth -type d 2>/dev/null | sed 's|^$remotePathTrailing||' | sed 's|^|d\\||' | head -500";
        // Get files (output: f|relative_path)  
        $fileCmd = "find $escapedPath -maxdepth $maxDepth -type f 2>/dev/null | sed 's|^$remotePathTrailing||' | sed 's|^|f\\||' | head -500";
        
        $combinedCmd = "{ $dirCmd; $fileCmd; }";
        $escapedCmd = escapeshellarg($combinedCmd);
        
        $output = [];
        $code = 0;
        exec(self::LXC_CMD . " exec $name -- /bin/sh -c $escapedCmd 2>&1", $output, $code);
        
        if ($code !== 0 && empty($output)) {
            return ['success' => false, 'tree' => [], 'error' => 'Failed to list files'];
        }
        
        // Build tree structure
        $tree = [];
        $excludeDirs = ['vendor', 'node_modules', '.git', '__pycache__', '.cache', '.idea', '.npm'];
        
        foreach ($output as $line) {
            $parts = explode('|', $line, 2);
            if (count($parts) !== 2) continue;
            
            $type = $parts[0]; // 'd' for directory, 'f' for file
            $relPath = $parts[1];
            
            if (empty($relPath)) continue;
            
            // Skip excluded directories and their contents
            $skip = false;
            foreach ($excludeDirs as $exclude) {
                if ($relPath === $exclude || strpos($relPath, $exclude . '/') === 0) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;
            
            // Build nested structure
            $pathParts = explode('/', $relPath);
            $current = &$tree;
            $pathSoFar = '';
            
            foreach ($pathParts as $i => $part) {
                if (empty($part)) continue;
                
                $pathSoFar = $pathSoFar ? $pathSoFar . '/' . $part : $part;
                $isLast = ($i === count($pathParts) - 1);
                
                if (!isset($current[$part])) {
                    if ($isLast) {
                        $current[$part] = [
                            'type' => ($type === 'd') ? 'folder' : 'file',
                            'path' => $pathSoFar,
                            'encoded' => base64_encode($pathSoFar)
                        ];
                        if ($type === 'd') {
                            $current[$part]['children'] = [];
                        }
                    } else {
                        // Intermediate directory
                        $current[$part] = [
                            'type' => 'folder',
                            'path' => $pathSoFar,
                            'encoded' => base64_encode($pathSoFar),
                            'children' => []
                        ];
                    }
                }
                
                if (isset($current[$part]['children'])) {
                    $current = &$current[$part]['children'];
                } else {
                    break;
                }
            }
        }
        
        // Sort tree: folders first, then files, alphabetically
        $sortTree = function(&$tree) use (&$sortTree) {
            $folders = [];
            $files = [];
            foreach ($tree as $name => $item) {
                if ($item['type'] === 'folder') {
                    if (isset($item['children'])) {
                        $sortTree($item['children']);
                    }
                    $folders[$name] = $item;
                } else {
                    $files[$name] = $item;
                }
            }
            ksort($folders, SORT_NATURAL | SORT_FLAG_CASE);
            ksort($files, SORT_NATURAL | SORT_FLAG_CASE);
            $tree = array_merge($folders, $files);
        };
        $sortTree($tree);
        
        return ['success' => true, 'tree' => $tree, 'error' => null];
    }
    
    /**
     * Read a file from inside a sandbox container
     * 
     * @param string $userId User/sandbox identifier
     * @param string $filePath Path inside container relative to /home
     * @return array ['success' => bool, 'content' => string|null, 'error' => string|null]
     */
    public static function readFile(string $userId, string $filePath): array
    {
        $name = self::containerName($userId);
        
        if (!self::sandboxExists($userId)) {
            return ['success' => false, 'content' => null, 'error' => 'Sandbox does not exist'];
        }
        
        if (!self::sandboxRunning($userId)) {
            exec(self::LXC_CMD . " start $name 2>&1");
            sleep(2);
        }
        
        // Security: prevent path traversal
        $filePath = str_replace(['../', '..\\', '..'], '', $filePath);
        $fullPath = '/home/' . ltrim($filePath, '/');
        
        // Check if file exists
        $escapedPath = escapeshellarg($fullPath);
        $checkCmd = escapeshellarg("test -f $escapedPath && echo EXISTS");
        exec(self::LXC_CMD . " exec $name -- /bin/sh -c $checkCmd 2>&1", $checkOutput, $checkCode);
        
        if (empty($checkOutput) || trim($checkOutput[0]) !== 'EXISTS') {
            return ['success' => false, 'content' => null, 'error' => 'File not found'];
        }
        
        // Read file content using base64 to handle binary safely
        $catCmd = escapeshellarg("cat $escapedPath | base64");
        $output = [];
        exec(self::LXC_CMD . " exec $name -- /bin/sh -c $catCmd 2>&1", $output, $code);
        
        if ($code !== 0) {
            return ['success' => false, 'content' => null, 'error' => 'Failed to read file'];
        }
        
        $content = base64_decode(implode('', $output));
        
        return ['success' => true, 'content' => $content, 'error' => null];
    }
    
    /**
     * Write content to a file inside a sandbox container
     * 
     * @param string $userId User/sandbox identifier
     * @param string $filePath Path inside container relative to /home
     * @param string $content File content
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function writeFile(string $userId, string $filePath, string $content): array
    {
        $name = self::containerName($userId);
        
        if (!self::sandboxExists($userId)) {
            return ['success' => false, 'error' => 'Sandbox does not exist'];
        }
        
        if (!self::sandboxRunning($userId)) {
            exec(self::LXC_CMD . " start $name 2>&1");
            sleep(2);
        }
        
        // Security: prevent path traversal
        $filePath = str_replace(['../', '..\\', '..'], '', $filePath);
        $fullPath = '/home/' . ltrim($filePath, '/');
        
        // Ensure parent directory exists
        $parentDir = dirname($fullPath);
        $mkdirCmd = escapeshellarg("mkdir -p $parentDir");
        exec(self::LXC_CMD . " exec $name -- /bin/sh -c $mkdirCmd 2>&1");
        
        // Write content using base64 to handle special characters
        $b64Content = base64_encode($content);
        $escapedPath = escapeshellarg($fullPath);
        $writeCmd = escapeshellarg("echo '$b64Content' | base64 -d > $escapedPath");
        
        $output = [];
        exec(self::LXC_CMD . " exec $name -- /bin/sh -c $writeCmd 2>&1", $output, $code);
        
        if ($code !== 0) {
            return ['success' => false, 'error' => 'Failed to write file: ' . implode("\n", $output)];
        }
        
        return ['success' => true, 'error' => null, 'bytes' => strlen($content)];
    }
    
    /**
     * Create a file or folder inside a sandbox container
     * 
     * @param string $userId User/sandbox identifier
     * @param string $path Path inside container relative to /home
     * @param string $type 'file' or 'folder'
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function createItem(string $userId, string $path, string $type = 'file'): array
    {
        $name = self::containerName($userId);
        
        if (!self::sandboxExists($userId)) {
            return ['success' => false, 'error' => 'Sandbox does not exist'];
        }
        
        if (!self::sandboxRunning($userId)) {
            exec(self::LXC_CMD . " start $name 2>&1");
            sleep(2);
        }
        
        // Security: prevent path traversal
        $path = str_replace(['../', '..\\', '..'], '', $path);
        $fullPath = '/home/' . ltrim($path, '/');
        $escapedPath = escapeshellarg($fullPath);
        
        if ($type === 'folder') {
            $cmd = escapeshellarg("mkdir -p $escapedPath");
        } else {
            // Create parent directory and touch file
            $parentDir = dirname($fullPath);
            $cmd = escapeshellarg("mkdir -p $parentDir && touch $escapedPath");
        }
        
        $output = [];
        exec(self::LXC_CMD . " exec $name -- /bin/sh -c $cmd 2>&1", $output, $code);
        
        if ($code !== 0) {
            return ['success' => false, 'error' => 'Failed to create: ' . implode("\n", $output)];
        }
        
        return ['success' => true, 'error' => null];
    }
    
    /**
     * Delete a file or folder inside a sandbox container
     * 
     * @param string $userId User/sandbox identifier
     * @param string $path Path inside container relative to /home
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function deleteItem(string $userId, string $path): array
    {
        $name = self::containerName($userId);
        
        if (!self::sandboxExists($userId)) {
            return ['success' => false, 'error' => 'Sandbox does not exist'];
        }
        
        if (!self::sandboxRunning($userId)) {
            exec(self::LXC_CMD . " start $name 2>&1");
            sleep(2);
        }
        
        // Security: prevent path traversal and don't allow deleting root
        $path = str_replace(['../', '..\\', '..'], '', $path);
        if (empty($path) || $path === '/' || $path === '.') {
            return ['success' => false, 'error' => 'Cannot delete root directory'];
        }
        
        $fullPath = '/home/' . ltrim($path, '/');
        $escapedPath = escapeshellarg($fullPath);
        
        // Use rm -rf for both files and directories
        $cmd = escapeshellarg("rm -rf $escapedPath");
        
        $output = [];
        exec(self::LXC_CMD . " exec $name -- /bin/sh -c $cmd 2>&1", $output, $code);
        
        if ($code !== 0) {
            return ['success' => false, 'error' => 'Failed to delete: ' . implode("\n", $output)];
        }
        
        return ['success' => true, 'error' => null];
    }
    
    /**
     * Rename/move a file or folder inside a sandbox container
     * 
     * @param string $userId User/sandbox identifier
     * @param string $oldPath Old path relative to /home
     * @param string $newPath New path relative to /home
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function renameItem(string $userId, string $oldPath, string $newPath): array
    {
        $name = self::containerName($userId);
        
        if (!self::sandboxExists($userId)) {
            return ['success' => false, 'error' => 'Sandbox does not exist'];
        }
        
        if (!self::sandboxRunning($userId)) {
            exec(self::LXC_CMD . " start $name 2>&1");
            sleep(2);
        }
        
        // Security: prevent path traversal
        $oldPath = str_replace(['../', '..\\', '..'], '', $oldPath);
        $newPath = str_replace(['../', '..\\', '..'], '', $newPath);
        
        $oldFullPath = '/home/' . ltrim($oldPath, '/');
        $newFullPath = '/home/' . ltrim($newPath, '/');
        
        // Ensure parent directory exists
        $parentDir = dirname($newFullPath);
        $escapedOld = escapeshellarg($oldFullPath);
        $escapedNew = escapeshellarg($newFullPath);
        
        $cmd = escapeshellarg("mkdir -p $parentDir && mv $escapedOld $escapedNew");
        
        $output = [];
        exec(self::LXC_CMD . " exec $name -- /bin/sh -c $cmd 2>&1", $output, $code);
        
        if ($code !== 0) {
            return ['success' => false, 'error' => 'Failed to rename: ' . implode("\n", $output)];
        }
        
        return ['success' => true, 'error' => null];
    }
    
    /**
     * Check if a path exists inside a sandbox container
     * 
     * @param string $userId User/sandbox identifier
     * @param string $path Path relative to /home
     * @return bool
     */
    public static function pathExists(string $userId, string $path): bool
    {
        $name = self::containerName($userId);
        
        if (!self::sandboxExists($userId) || !self::sandboxRunning($userId)) {
            return false;
        }
        
        $path = str_replace(['../', '..\\', '..'], '', $path);
        $fullPath = '/home/' . ltrim($path, '/');
        $escapedPath = escapeshellarg($fullPath);
        
        $cmd = escapeshellarg("test -e $escapedPath && echo EXISTS");
        exec(self::LXC_CMD . " exec $name -- /bin/sh -c $cmd 2>&1", $output, $code);
        
        return !empty($output) && trim($output[0]) === 'EXISTS';
    }
    
    /**
     * Copy a file or folder inside a sandbox container
     * 
     * @param string $userId User/sandbox identifier
     * @param string $sourcePath Source path relative to /home
     * @param string $destPath Destination path relative to /home
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function copyItem(string $userId, string $sourcePath, string $destPath): array
    {
        $name = self::containerName($userId);
        
        if (!self::sandboxExists($userId)) {
            return ['success' => false, 'error' => 'Sandbox does not exist'];
        }
        
        if (!self::sandboxRunning($userId)) {
            exec(self::LXC_CMD . " start $name 2>&1");
            sleep(2);
        }
        
        // Security: prevent path traversal
        $sourcePath = str_replace(['../', '..\\', '..'], '', $sourcePath);
        $destPath = str_replace(['../', '..\\', '..'], '', $destPath);
        
        $sourceFullPath = '/home/' . ltrim($sourcePath, '/');
        $destFullPath = '/home/' . ltrim($destPath, '/');
        
        // Ensure parent directory exists and copy
        $parentDir = dirname($destFullPath);
        $escapedSource = escapeshellarg($sourceFullPath);
        $escapedDest = escapeshellarg($destFullPath);
        
        $cmd = escapeshellarg("mkdir -p $parentDir && cp -r $escapedSource $escapedDest");
        
        $output = [];
        exec(self::LXC_CMD . " exec $name -- /bin/sh -c $cmd 2>&1", $output, $code);
        
        if ($code !== 0) {
            return ['success' => false, 'error' => 'Failed to copy: ' . implode("\n", $output)];
        }
        
        return ['success' => true, 'error' => null];
    }
}
