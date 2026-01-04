<?php
/**
 * Docker-based Sandbox Manager for Ginto
 * 
 * Manages user sandboxes using Docker containers (Alpine Linux).
 * Provides an alternative to LXD for macOS, Windows, and Docker-only environments.
 * Uses the same deterministic IP allocation via SHA256(sandboxId) as LxdSandboxManager.
 * 
 * @see docs/sandbox.md for full architecture documentation
 */
namespace Ginto\Helpers;

class DockerSandboxManager
{
    // Docker command - use Docker socket or CLI
    const DOCKER_CMD = 'docker';
    
    // Base image name - the pre-configured ginto-sandbox Docker image
    // Built from docker/sandbox/Dockerfile
    const BASE_IMAGE = 'ginto/sandbox:latest';
    
    // Prefix for all sandbox container names
    const CONTAINER_PREFIX = 'ginto-sandbox-';
    
    // Docker network name for sandboxes
    const SANDBOX_NETWORK = 'ginto-sandbox';
    
    // Default subnet for sandbox network
    const DEFAULT_SUBNET = '172.30.0.0/16';
    
    // Resource limits
    const DEFAULT_CPU_LIMIT = '2.0';
    const DEFAULT_MEMORY_LIMIT = '1g';
    const DEFAULT_DISK_LIMIT = '2g';
    
    // Process limit
    const DEFAULT_PIDS_LIMIT = 200;
    
    // Timeout for container operations
    const OPERATION_TIMEOUT = 30;
    
    /**
     * Secret key for Feistel permutation (same as LxdSandboxManager for compatibility)
     */
    const PERMUTATION_KEY = 'ginto-default-key-change-in-prod';
    
    /**
     * Check if Docker is installed and available on this system
     */
    public static function checkDockerAvailability(): array
    {
        // Check if docker command exists
        exec('which docker 2>/dev/null', $output, $code);
        if ($code !== 0) {
            return [
                'available' => false,
                'error' => 'docker_not_installed',
                'message' => 'Docker is not installed on this system.',
                'install_command' => 'curl -fsSL https://get.docker.com | sh'
            ];
        }
        
        // Check if Docker daemon is running
        exec('docker info 2>&1', $output, $code);
        if ($code !== 0) {
            $errorMsg = implode("\n", $output);
            
            // Check for permission issues
            if (stripos($errorMsg, 'permission denied') !== false) {
                return [
                    'available' => false,
                    'error' => 'docker_permission',
                    'message' => 'Permission denied. Add user to docker group: sudo usermod -aG docker $USER',
                    'install_command' => 'sudo usermod -aG docker $USER && newgrp docker'
                ];
            }
            
            // Docker daemon not running
            if (stripos($errorMsg, 'Cannot connect') !== false || stripos($errorMsg, 'Is the docker daemon running') !== false) {
                return [
                    'available' => false,
                    'error' => 'docker_not_running',
                    'message' => 'Docker daemon is not running.',
                    'install_command' => 'sudo systemctl start docker'
                ];
            }
            
            return [
                'available' => false,
                'error' => 'docker_execution_failed',
                'message' => 'Failed to connect to Docker: ' . $errorMsg,
                'install_command' => 'sudo systemctl start docker'
            ];
        }
        
        // Check if base image exists
        exec(self::DOCKER_CMD . ' image inspect ' . self::BASE_IMAGE . ' 2>/dev/null', $imageOutput, $imageCode);
        $baseImageExists = ($imageCode === 0);
        
        // Check if sandbox network exists
        exec(self::DOCKER_CMD . ' network inspect ' . self::SANDBOX_NETWORK . ' 2>/dev/null', $netOutput, $netCode);
        $networkExists = ($netCode === 0);
        
        return [
            'available' => true,
            'error' => null,
            'message' => 'Docker is properly configured.',
            'base_image_exists' => $baseImageExists,
            'network_exists' => $networkExists,
            'docker_version' => trim(shell_exec('docker --version') ?? 'unknown')
        ];
    }
    
    /**
     * Get the container name for a user/sandbox ID
     */
    public static function containerName(string $sandboxId): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $sandboxId);
        return self::CONTAINER_PREFIX . $safe;
    }
    
    /**
     * 4-round Feistel network for bijective 32-bit permutation
     * Same algorithm as LxdSandboxManager for IP compatibility
     */
    private static function feistelPermute(int $input, string $key): int
    {
        $left = ($input >> 16) & 0xFFFF;
        $right = $input & 0xFFFF;
        
        for ($round = 0; $round < 4; $round++) {
            $roundKey = hash('sha256', "{$key}:{$round}:{$right}", true);
            $f = (ord($roundKey[0]) << 8) | ord($roundKey[1]);
            
            $newLeft = $right;
            $newRight = $left ^ $f;
            
            $left = $newLeft;
            $right = $newRight;
        }
        
        return (($left << 16) | $right) & 0xFFFFFFFF;
    }
    
    /**
     * Convert sandbox ID to deterministic IP address
     * Same algorithm as LxdSandboxManager for compatibility
     */
    public static function sandboxToIp(string $sandboxId): string
    {
        $hash = hash('sha256', $sandboxId, true);
        $subnet = getenv('DOCKER_SANDBOX_SUBNET') ?: ($_ENV['DOCKER_SANDBOX_SUBNET'] ?? self::DEFAULT_SUBNET);
        
        // Parse the subnet (e.g., "172.30.0.0/16")
        $parts = explode('/', $subnet);
        $baseIp = $parts[0];
        $cidr = (int)($parts[1] ?? 16);
        
        // Get 32-bit input from hash
        $input = (ord($hash[0]) << 24) | (ord($hash[1]) << 16) | (ord($hash[2]) << 8) | ord($hash[3]);
        
        // Apply Feistel permutation for collision-free mapping
        $key = getenv('IP_PERMUTATION_KEY') ?: ($_ENV['IP_PERMUTATION_KEY'] ?? self::PERMUTATION_KEY);
        $permuted = self::feistelPermute($input, $key);
        
        // Calculate available host bits based on CIDR
        $hostBits = 32 - $cidr;
        $hostMask = (1 << $hostBits) - 1;
        
        // Get base IP as integer
        $baseIpParts = explode('.', $baseIp);
        $baseInt = ((int)$baseIpParts[0] << 24) | ((int)$baseIpParts[1] << 16) | 
                   ((int)$baseIpParts[2] << 8) | (int)$baseIpParts[3];
        
        // Calculate host portion from permuted value
        $hostPortion = ($permuted & $hostMask);
        
        // Avoid network address (0) and broadcast address (all 1s)
        // Also reserve first few addresses for gateway
        if ($hostPortion < 2) $hostPortion = 2;
        if ($hostPortion >= $hostMask) $hostPortion = $hostMask - 1;
        
        // Combine base network with host portion
        $networkMask = ~$hostMask;
        $resultInt = ($baseInt & $networkMask) | $hostPortion;
        
        // Convert back to IP string
        $octet1 = ($resultInt >> 24) & 255;
        $octet2 = ($resultInt >> 16) & 255;
        $octet3 = ($resultInt >> 8) & 255;
        $octet4 = $resultInt & 255;
        
        return "{$octet1}.{$octet2}.{$octet3}.{$octet4}";
    }
    
    /**
     * Ensure the sandbox Docker network exists
     */
    public static function ensureNetwork(): bool
    {
        $network = self::SANDBOX_NETWORK;
        $subnet = getenv('DOCKER_SANDBOX_SUBNET') ?: ($_ENV['DOCKER_SANDBOX_SUBNET'] ?? self::DEFAULT_SUBNET);
        
        // Check if network already exists
        exec(self::DOCKER_CMD . ' network inspect ' . escapeshellarg($network) . ' 2>/dev/null', $output, $code);
        if ($code === 0) {
            return true;
        }
        
        // Create the network
        $cmd = self::DOCKER_CMD . ' network create ' .
               '--driver bridge ' .
               '--subnet=' . escapeshellarg($subnet) . ' ' .
               escapeshellarg($network) . ' 2>&1';
        
        exec($cmd, $output, $code);
        return ($code === 0);
    }
    
    /**
     * Check if a sandbox container exists
     */
    public static function sandboxExists(string $sandboxId): bool
    {
        $name = self::containerName($sandboxId);
        exec(self::DOCKER_CMD . ' container inspect ' . escapeshellarg($name) . ' 2>/dev/null', $output, $code);
        return ($code === 0);
    }
    
    /**
     * Check if a sandbox container is running
     */
    public static function sandboxRunning(string $sandboxId): bool
    {
        $name = self::containerName($sandboxId);
        $cmd = self::DOCKER_CMD . ' container inspect -f "{{.State.Running}}" ' . escapeshellarg($name) . ' 2>/dev/null';
        $result = trim(shell_exec($cmd) ?? '');
        return ($result === 'true');
    }
    
    /**
     * Get sandbox container status
     */
    public static function getSandboxStatus(string $sandboxId): array
    {
        $name = self::containerName($sandboxId);
        
        $cmd = self::DOCKER_CMD . ' container inspect ' . escapeshellarg($name) . ' 2>/dev/null';
        exec($cmd, $output, $code);
        
        if ($code !== 0) {
            return [
                'exists' => false,
                'running' => false,
                'status' => 'not_found'
            ];
        }
        
        $data = json_decode(implode("\n", $output), true);
        if (!$data || !isset($data[0])) {
            return [
                'exists' => false,
                'running' => false,
                'status' => 'parse_error'
            ];
        }
        
        $container = $data[0];
        $state = $container['State'] ?? [];
        
        return [
            'exists' => true,
            'running' => (bool)($state['Running'] ?? false),
            'status' => $state['Status'] ?? 'unknown',
            'started_at' => $state['StartedAt'] ?? null,
            'ip_address' => self::getContainerIp($name),
            'container_id' => $container['Id'] ?? null
        ];
    }
    
    /**
     * Get container IP address
     */
    private static function getContainerIp(string $containerName): ?string
    {
        $network = self::SANDBOX_NETWORK;
        $cmd = self::DOCKER_CMD . ' container inspect -f "{{.NetworkSettings.Networks.' . $network . '.IPAddress}}" ' . 
               escapeshellarg($containerName) . ' 2>/dev/null';
        $ip = trim(shell_exec($cmd) ?? '');
        return $ip ?: null;
    }
    
    /**
     * Create a new sandbox container
     */
    public static function createSandbox(string $sandboxId, ?array $options = null): array
    {
        $name = self::containerName($sandboxId);
        $ip = self::sandboxToIp($sandboxId);
        
        // Ensure network exists
        if (!self::ensureNetwork()) {
            return [
                'success' => false,
                'error' => 'Failed to create sandbox network'
            ];
        }
        
        // Check if container already exists
        if (self::sandboxExists($sandboxId)) {
            // Start if not running
            if (!self::sandboxRunning($sandboxId)) {
                return self::startSandbox($sandboxId);
            }
            return [
                'success' => true,
                'message' => 'Sandbox already exists and running',
                'container_name' => $name,
                'ip_address' => $ip
            ];
        }
        
        // Resource limits
        $cpuLimit = $options['cpu_limit'] ?? self::DEFAULT_CPU_LIMIT;
        $memoryLimit = $options['memory_limit'] ?? self::DEFAULT_MEMORY_LIMIT;
        $pidsLimit = $options['pids_limit'] ?? self::DEFAULT_PIDS_LIMIT;
        
        // Build docker run command
        $cmd = self::DOCKER_CMD . ' run -d ' .
               '--name ' . escapeshellarg($name) . ' ' .
               '--network ' . escapeshellarg(self::SANDBOX_NETWORK) . ' ' .
               '--ip ' . escapeshellarg($ip) . ' ' .
               '--cpus=' . escapeshellarg($cpuLimit) . ' ' .
               '--memory=' . escapeshellarg($memoryLimit) . ' ' .
               '--pids-limit=' . escapeshellarg((string)$pidsLimit) . ' ' .
               '--restart=unless-stopped ' .
               '--hostname=' . escapeshellarg('sandbox-' . substr($sandboxId, 0, 8)) . ' ' .
               '-e SANDBOX_ID=' . escapeshellarg($sandboxId) . ' ' .
               '-e SANDBOX_IP=' . escapeshellarg($ip) . ' ' .
               self::BASE_IMAGE . ' 2>&1';
        
        exec($cmd, $output, $code);
        
        if ($code !== 0) {
            return [
                'success' => false,
                'error' => 'Failed to create container: ' . implode("\n", $output),
                'command' => $cmd
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Sandbox created successfully',
            'container_name' => $name,
            'container_id' => trim($output[0] ?? ''),
            'ip_address' => $ip
        ];
    }
    
    /**
     * Start a stopped sandbox container
     */
    public static function startSandbox(string $sandboxId): array
    {
        $name = self::containerName($sandboxId);
        
        if (!self::sandboxExists($sandboxId)) {
            return [
                'success' => false,
                'error' => 'Sandbox does not exist'
            ];
        }
        
        if (self::sandboxRunning($sandboxId)) {
            return [
                'success' => true,
                'message' => 'Sandbox is already running'
            ];
        }
        
        exec(self::DOCKER_CMD . ' start ' . escapeshellarg($name) . ' 2>&1', $output, $code);
        
        if ($code !== 0) {
            return [
                'success' => false,
                'error' => 'Failed to start sandbox: ' . implode("\n", $output)
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Sandbox started',
            'container_name' => $name,
            'ip_address' => self::sandboxToIp($sandboxId)
        ];
    }
    
    /**
     * Stop a running sandbox container
     */
    public static function stopSandbox(string $sandboxId): array
    {
        $name = self::containerName($sandboxId);
        
        if (!self::sandboxExists($sandboxId)) {
            return [
                'success' => false,
                'error' => 'Sandbox does not exist'
            ];
        }
        
        if (!self::sandboxRunning($sandboxId)) {
            return [
                'success' => true,
                'message' => 'Sandbox is already stopped'
            ];
        }
        
        exec(self::DOCKER_CMD . ' stop ' . escapeshellarg($name) . ' 2>&1', $output, $code);
        
        if ($code !== 0) {
            return [
                'success' => false,
                'error' => 'Failed to stop sandbox: ' . implode("\n", $output)
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Sandbox stopped',
            'container_name' => $name
        ];
    }
    
    /**
     * Delete a sandbox container
     */
    public static function deleteSandbox(string $sandboxId): array
    {
        $name = self::containerName($sandboxId);
        
        if (!self::sandboxExists($sandboxId)) {
            return [
                'success' => true,
                'message' => 'Sandbox does not exist (already deleted)'
            ];
        }
        
        // Stop first if running
        if (self::sandboxRunning($sandboxId)) {
            self::stopSandbox($sandboxId);
        }
        
        exec(self::DOCKER_CMD . ' rm -f ' . escapeshellarg($name) . ' 2>&1', $output, $code);
        
        if ($code !== 0) {
            return [
                'success' => false,
                'error' => 'Failed to delete sandbox: ' . implode("\n", $output)
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Sandbox deleted',
            'container_name' => $name
        ];
    }
    
    /**
     * Execute a command inside the sandbox
     * Returns: [exit_code, stdout, stderr]
     */
    public static function execInSandbox(string $sandboxId, string $command, string $cwd = '/home/sandbox', int $timeout = 30): array
    {
        $name = self::containerName($sandboxId);
        
        // Ensure sandbox is running
        if (!self::sandboxRunning($sandboxId)) {
            // Try to start it
            $startResult = self::startSandbox($sandboxId);
            if (!$startResult['success']) {
                // Try to create it
                $createResult = self::createSandbox($sandboxId);
                if (!$createResult['success']) {
                    return [1, '', 'Failed to start or create sandbox: ' . ($createResult['error'] ?? 'unknown error')];
                }
            }
        }
        
        // Build the exec command
        $execCmd = self::DOCKER_CMD . ' exec ' .
                   '-w ' . escapeshellarg($cwd) . ' ' .
                   escapeshellarg($name) . ' ' .
                   '/bin/sh -c ' . escapeshellarg($command);
        
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];
        
        $process = proc_open($execCmd, $descriptors, $pipes);
        
        if (!is_resource($process)) {
            return [1, '', 'Failed to execute command in sandbox'];
        }
        
        // Close stdin
        fclose($pipes[0]);
        
        // Set non-blocking and read with timeout
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        
        $stdout = '';
        $stderr = '';
        $startTime = time();
        
        while (true) {
            $status = proc_get_status($process);
            
            // Read available output
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
            
            // Check if process finished
            if (!$status['running']) {
                break;
            }
            
            // Check timeout
            if ((time() - $startTime) > $timeout) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                return [124, $stdout, $stderr . "\nCommand timed out after {$timeout} seconds"];
            }
            
            usleep(10000); // 10ms
        }
        
        // Read any remaining output
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        $exitCode = proc_close($process);
        
        return [$exitCode, $stdout, $stderr];
    }
    
    /**
     * Read a file from the sandbox
     */
    public static function readFile(string $sandboxId, string $filePath): array
    {
        [$code, $stdout, $stderr] = self::execInSandbox($sandboxId, 'cat ' . escapeshellarg($filePath));
        
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
        // Use base64 to safely transfer content
        $encoded = base64_encode($content);
        $cmd = 'echo ' . escapeshellarg($encoded) . ' | base64 -d > ' . escapeshellarg($filePath);
        
        [$code, $stdout, $stderr] = self::execInSandbox($sandboxId, $cmd);
        
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
     * List files in sandbox directory
     */
    public static function listFiles(string $sandboxId, string $path = '/home/sandbox', int $maxDepth = 5): array
    {
        // Use ls -la which works on all systems including BusyBox
        $cmd = 'ls -la ' . escapeshellarg($path) . ' 2>/dev/null';
        [$code, $stdout, $stderr] = self::execInSandbox($sandboxId, $cmd);
        
        if ($code !== 0) {
            return [
                'success' => false,
                'error' => $stderr ?: 'Failed to list files',
                'tree' => []
            ];
        }
        
        // Parse ls -la output
        $tree = [];
        $lines = explode("\n", trim($stdout));
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            if (strpos($line, 'total ') === 0) continue; // Skip total line
            
            // Parse ls -la output: drwxr-xr-x  2 sandbox sandbox  4096 Jan  4 14:13 Desktop
            // First character: d=directory, -=file, l=link
            $type = substr($line, 0, 1);
            if ($type !== 'd' && $type !== '-' && $type !== 'l') continue;
            
            // Get filename (last column, may contain spaces)
            $parts = preg_split('/\s+/', $line, 9);
            if (count($parts) < 9) continue;
            
            $name = $parts[8];
            if ($name === '.' || $name === '..') continue;
            
            // Skip hidden files except specific ones
            if (strpos($name, '.') === 0 && !in_array($name, ['.config', '.local', '.pki'])) continue;
            
            $fullPath = rtrim($path, '/') . '/' . $name;
            
            $tree[] = [
                'name' => $name,
                'path' => $fullPath,
                'type' => ($type === 'd') ? 'directory' : 'file',
                'children' => ($type === 'd') ? [] : null
            ];
        }
        
        // Sort: directories first, then files, alphabetically
        usort($tree, function($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });
        
        return [
            'success' => true,
            'tree' => $tree
        ];
    }
    
    /**
     * List all sandbox containers
     */
    public static function listSandboxes(): array
    {
        $prefix = self::CONTAINER_PREFIX;
        $cmd = self::DOCKER_CMD . ' ps -a --filter "name=' . $prefix . '" --format "{{.Names}}\t{{.Status}}\t{{.ID}}" 2>/dev/null';
        
        exec($cmd, $output, $code);
        
        if ($code !== 0) {
            return [];
        }
        
        $sandboxes = [];
        foreach ($output as $line) {
            $parts = explode("\t", $line);
            if (count($parts) >= 3) {
                $name = $parts[0];
                $sandboxId = str_replace($prefix, '', $name);
                $sandboxes[] = [
                    'sandbox_id' => $sandboxId,
                    'container_name' => $name,
                    'status' => $parts[1],
                    'container_id' => $parts[2],
                    'running' => strpos($parts[1], 'Up') !== false
                ];
            }
        }
        
        return $sandboxes;
    }
    
    /**
     * Cleanup idle sandboxes older than the specified age
     */
    public static function cleanupIdleSandboxes(int $maxAgeSeconds = 3600): array
    {
        $sandboxes = self::listSandboxes();
        $deleted = [];
        
        foreach ($sandboxes as $sandbox) {
            // Skip running containers for now
            if ($sandbox['running']) {
                continue;
            }
            
            // Check when container was last used
            $name = $sandbox['container_name'];
            $cmd = self::DOCKER_CMD . ' inspect -f "{{.State.FinishedAt}}" ' . escapeshellarg($name) . ' 2>/dev/null';
            $finishedAt = trim(shell_exec($cmd) ?? '');
            
            if ($finishedAt && $finishedAt !== '0001-01-01T00:00:00Z') {
                $finishedTime = strtotime($finishedAt);
                if ($finishedTime && (time() - $finishedTime) > $maxAgeSeconds) {
                    $result = self::deleteSandbox($sandbox['sandbox_id']);
                    if ($result['success']) {
                        $deleted[] = $sandbox['sandbox_id'];
                    }
                }
            }
        }
        
        return $deleted;
    }
    
    /**
     * Build the sandbox base image
     */
    public static function buildBaseImage(string $dockerfilePath = null): array
    {
        $dockerfilePath = $dockerfilePath ?? dirname(__DIR__, 2) . '/docker/sandbox/Dockerfile';
        $contextPath = dirname($dockerfilePath);
        
        if (!file_exists($dockerfilePath)) {
            return [
                'success' => false,
                'error' => 'Dockerfile not found: ' . $dockerfilePath
            ];
        }
        
        $cmd = self::DOCKER_CMD . ' build -t ' . self::BASE_IMAGE . ' -f ' . 
               escapeshellarg($dockerfilePath) . ' ' . escapeshellarg($contextPath) . ' 2>&1';
        
        exec($cmd, $output, $code);
        
        if ($code !== 0) {
            return [
                'success' => false,
                'error' => 'Failed to build image: ' . implode("\n", $output)
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Base image built successfully',
            'image' => self::BASE_IMAGE
        ];
    }
    
    /**
     * Check if using Docker sandboxes
     */
    public static function isDockerMode(): bool
    {
        $mode = getenv('SANDBOX_MODE') ?: ($_ENV['SANDBOX_MODE'] ?? $_SERVER['SANDBOX_MODE'] ?? 'auto');
        
        if ($mode === 'docker') {
            return true;
        }
        
        if ($mode === 'lxd') {
            return false;
        }
        
        // Auto-detect: prefer LXD if available, otherwise Docker
        if ($mode === 'auto') {
            // Check if we're inside Docker (prefer Docker sandboxes)
            if (file_exists('/.dockerenv')) {
                return true;
            }
            
            // Check if LXD is available
            $lxdCheck = LxdSandboxManager::checkLxcAvailability();
            if ($lxdCheck['available']) {
                return false; // Use LXD
            }
            
            // Check if Docker is available
            $dockerCheck = self::checkDockerAvailability();
            if ($dockerCheck['available']) {
                return true; // Use Docker
            }
        }
        
        return false;
    }
}
