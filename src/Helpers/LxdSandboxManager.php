<?php
/**
 * LXD-based Sandbox Manager for Ginto
 * 
 * Manages user sandboxes using LXD containers (Alpine Linux).
 * Uses DETERMINISTIC IP allocation via SHA256(sandboxId) for infinite scale.
 * Redis is available for agent state (queues, metrics) but NOT for IP lookup.
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
    const BASE_IMAGE = 'ginto-sandbox-image';
    
    // Prefix for all sandbox container names
    const CONTAINER_PREFIX = 'ginto-sandbox-';
    
    // Redis key prefix for agent state (NOT for IP lookup - that's deterministic)
    const REDIS_PREFIX = 'agent:';
    
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
                    'install_command' => 'sudo bash ~/ginto.ai/bin/ginto.sh install'
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
                'install_command' => 'sudo bash ~/ginto.ai/bin/ginto.sh install'
            ];
        }
        
        // Check if base image exists
        exec(self::LXC_CMD . ' image list ' . self::BASE_IMAGE . ' --format csv 2>/dev/null', $imageOutput, $imageCode);
        if ($imageCode !== 0 || empty(trim(implode('', $imageOutput)))) {
            return [
                'available' => false,
                'error' => 'base_image_missing',
                'message' => 'The ginto-sandbox-image base image is not available.',
                'install_command' => 'sudo bash ~/ginto.ai/bin/ginto.sh install'
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
     * Secret key for Feistel permutation
     */
    const PERMUTATION_KEY = 'ginto-default-key-change-in-prod';
    
    /**
     * 4-round Feistel network for bijective 32-bit permutation
     * Guarantees: same input → same output, different inputs → different outputs
     * 
     * @param int $input 32-bit unsigned integer
     * @param string $key Secret key
     * @return int Permuted 32-bit unsigned integer
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
     * Convert sandbox ID to deterministic IP address (Collision-Free)
     * 
     * Algorithm:
     * 1. SHA256(sandboxId) → first 4 bytes → 32-bit integer
     * 2. Feistel permutation with secret key → bijective mapping
     * 3. Result → IPv4 address
     * 
     * Properties:
     * - Deterministic: same ID always produces same IP
     * - Collision-free: different IDs guaranteed different IPs (bijective)
     * - Unpredictable: cannot guess IP without knowing the key
     * 
     * Modes (set via LXD_NETWORK_MODE env var):
     * - "bridge": Uses LXD bridge with prefix (default, 16.7M IPs max)
     * - "nat": Same as bridge but with NAT for outbound (16.7M IPs max)
     * - "macvlan": Uses macvlan + dummy interface, L2 mode (4.3B IPs)
     * - "ipvlan": Uses ipvlan + parent interface, L3 mode (4.3B IPs)
     * 
     * @param string $sandboxId The sandbox/agent identifier
     * @return string IP address
     */
    public static function sandboxToIp(string $sandboxId): string
    {
        $hash = hash('sha256', $sandboxId, true);
        $networkMode = getenv('LXD_NETWORK_MODE') ?: ($_ENV['LXD_NETWORK_MODE'] ?? $_SERVER['LXD_NETWORK_MODE'] ?? 'bridge');
        $networkPrefix = getenv('LXD_NETWORK_PREFIX') ?: ($_ENV['LXD_NETWORK_PREFIX'] ?? $_SERVER['LXD_NETWORK_PREFIX'] ?? null);
        
        // Get 32-bit input from hash
        $input = (ord($hash[0]) << 24) | (ord($hash[1]) << 16) | (ord($hash[2]) << 8) | ord($hash[3]);
        
        // Apply Feistel permutation for collision-free mapping
        $key = getenv('IP_PERMUTATION_KEY') ?: ($_ENV['IP_PERMUTATION_KEY'] ?? $_SERVER['IP_PERMUTATION_KEY'] ?? self::PERMUTATION_KEY);
        $permuted = self::feistelPermute($input, $key);
        
        // MACVLAN/IPVLAN MODE: Full 32-bit IP space (4.3 billion unique IPs)
        // Both use the same IP allocation strategy, differ only in L2 vs L3
        if ($networkMode === 'macvlan' || $networkMode === 'ipvlan') {
            return self::permutedToFullIp($permuted);
        }
        
        // BRIDGE/NAT MODE: Network prefix mode - constrain to subnet
        // Both use the same subnet-based allocation, differ in routing
        if ($networkPrefix && $networkPrefix !== '') {
            $prefixParts = explode('.', $networkPrefix);
            $prefixLen = count($prefixParts);
            
            if ($prefixLen === 3) {
                // /24 network (e.g., "10.170.65") - 253 hosts
                $lastOctet = 2 + ($permuted % 253);
                return "{$networkPrefix}.{$lastOctet}";
            } elseif ($prefixLen === 2) {
                // /16 network (e.g., "10.170") - 65,534 hosts
                $hostPart = $permuted & 0xFFFF; // 16 bits
                $octet3 = ($hostPart >> 8) & 255;
                $octet4 = $hostPart & 255;
                // Avoid network (.0.0), gateway (.0.1), and broadcast (.255.255)
                if ($octet3 === 0 && $octet4 < 2) $octet4 = 2;
                if ($octet3 === 255 && $octet4 === 255) $octet4 = 254;
                if ($octet4 === 0) $octet4 = 1;
                if ($octet4 === 255) $octet4 = 254;
                return "{$networkPrefix}.{$octet3}.{$octet4}";
            } elseif ($prefixLen === 1) {
                // /8 network (e.g., "10") - 16 million hosts
                $hostPart = $permuted & 0xFFFFFF; // 24 bits
                $octet2 = ($hostPart >> 16) & 255;
                $octet3 = ($hostPart >> 8) & 255;
                $octet4 = $hostPart & 255;
                if ($octet4 === 0) $octet4 = 1;
                if ($octet4 === 255) $octet4 = 254;
                return "{$networkPrefix}.{$octet2}.{$octet3}.{$octet4}";
            }
        }
        
        // Default bridge mode: 10.0.0.0/8 private range (16.7 million IPs)
        $hostPart = $permuted & 0xFFFFFF; // 24 bits for octets 2-4
        $octet2 = ($hostPart >> 16) & 255;
        $octet3 = ($hostPart >> 8) & 255;
        $octet4 = $hostPart & 255;
        
        // Avoid reserved addresses
        if ($octet2 === 0 && $octet3 === 0 && $octet4 < 2) $octet4 = 2;
        if ($octet4 === 0) $octet4 = 1;
        if ($octet4 === 255) $octet4 = 254;
        
        return "10.{$octet2}.{$octet3}.{$octet4}";
    }
    
    /**
     * Convert Feistel output to full 32-bit IP (for macvlan mode)
     * Filters out reserved/invalid IP ranges
     * 
     * Reserved ranges avoided:
     * - 0.0.0.0/8 (current network)
     * - 127.0.0.0/8 (loopback)
     * - 224.0.0.0/4 (multicast)
     * - 240.0.0.0/4 (reserved)
     * - x.x.x.0 (network addresses)
     * - x.x.x.255 (broadcast addresses)
     * 
     * @param int $permuted 32-bit permuted value
     * @return string Valid IPv4 address
     */
    private static function permutedToFullIp(int $permuted): string
    {
        $octet1 = ($permuted >> 24) & 255;
        $octet2 = ($permuted >> 16) & 255;
        $octet3 = ($permuted >> 8) & 255;
        $octet4 = $permuted & 255;
        
        // Remap reserved first octets to valid ranges
        // 0.x.x.x → 1.x.x.x (current network)
        if ($octet1 === 0) $octet1 = 1;
        
        // 127.x.x.x → 128.x.x.x (loopback)
        if ($octet1 === 127) $octet1 = 128;
        
        // 224-239.x.x.x → 64-79.x.x.x (multicast)
        if ($octet1 >= 224 && $octet1 <= 239) $octet1 = $octet1 - 160;
        
        // 240-255.x.x.x → 80-95.x.x.x (reserved)
        if ($octet1 >= 240) $octet1 = $octet1 - 160;
        
        // Avoid network and broadcast addresses
        if ($octet4 === 0) $octet4 = 1;
        if ($octet4 === 255) $octet4 = 254;
        
        return "{$octet1}.{$octet2}.{$octet3}.{$octet4}";
    }
    
    /**
     * Check if macvlan mode is enabled
     */
    /**
     * Check if using full IP range modes (macvlan or ipvlan)
     * Both modes use full 32-bit IP space with 4.3B unique IPs
     */
    public static function isFullIpMode(): bool
    {
        $mode = getenv('LXD_NETWORK_MODE') ?: ($_ENV['LXD_NETWORK_MODE'] ?? $_SERVER['LXD_NETWORK_MODE'] ?? 'bridge');
        return $mode === 'macvlan' || $mode === 'ipvlan';
    }
    
    /**
     * Check if macvlan mode is enabled
     */
    public static function isMacvlanMode(): bool
    {
        $mode = getenv('LXD_NETWORK_MODE') ?: ($_ENV['LXD_NETWORK_MODE'] ?? $_SERVER['LXD_NETWORK_MODE'] ?? 'bridge');
        return $mode === 'macvlan';
    }
    
    /**
     * Check if ipvlan mode is enabled
     */
    public static function isIpvlanMode(): bool
    {
        $mode = getenv('LXD_NETWORK_MODE') ?: ($_ENV['LXD_NETWORK_MODE'] ?? $_SERVER['LXD_NETWORK_MODE'] ?? 'bridge');
        return $mode === 'ipvlan';
    }
    
    /**
     * Get network interface name for containers based on mode
     * - bridge/nat: lxdbr0 (LXD default bridge)
     * - macvlan/ipvlan: ginto-macvlan0 (shared macvlan parent)
     * Note: Both macvlan and ipvlan share the same parent because
     * nested LXD can only use one shim interface for host routing.
     * Falls back to ginto-dummy0 for backward compatibility.
     */
    public static function getNetworkInterface(): string
    {
        if (self::isMacvlanMode() || self::isIpvlanMode()) {
            // Check env first, then prefer new name, fallback to old name
            $env = $_ENV['LXD_MACVLAN_PARENT'] ?? getenv('LXD_MACVLAN_PARENT');
            if ($env) return $env;
            
            // Check which interface actually exists
            exec('ip link show ginto-macvlan0 2>/dev/null', $out1, $code1);
            if ($code1 === 0) return 'ginto-macvlan0';
            
            exec('ip link show ginto-dummy0 2>/dev/null', $out2, $code2);
            if ($code2 === 0) return 'ginto-dummy0';
            
            // Default to new name for fresh setups
            return 'ginto-macvlan0';
        }
        return 'lxdbr0';
    }
    
    /**
     * Get current network mode
     */
    public static function getNetworkMode(): string
    {
        return $_ENV['LXD_NETWORK_MODE'] ?? $_SERVER['LXD_NETWORK_MODE'] ?? getenv('LXD_NETWORK_MODE') ?: 'bridge';
    }
    
    /**
     * Add route through shim interface (for macvlan/ipvlan modes)
     * Enables host-to-container communication
     * 
     * @param string $ip Container's IP address
     * @return bool Success
     */
    public static function addShimRoute(string $ip): bool
    {
        if (!self::isFullIpMode()) {
            return true; // Not needed for bridge/nat mode
        }
        
        // Both macvlan and ipvlan use the same shim interface
        $shimDev = 'ginto-shim';
        $cmd = "sudo ip route add " . escapeshellarg($ip) . "/32 dev " . escapeshellarg($shimDev) . " 2>/dev/null";
        exec($cmd, $output, $code);
        return $code === 0;
    }
    
    /**
     * Remove route through shim interface
     * 
     * @param string $ip Container's IP address
     * @return bool Success
     */
    public static function removeShimRoute(string $ip): bool
    {
        if (!self::isMacvlanMode()) {
            return true;
        }
        
        $cmd = "sudo ip route del " . escapeshellarg($ip) . "/32 dev ginto-shim 2>/dev/null";
        exec($cmd, $output, $code);
        return true; // Don't fail if route doesn't exist
    }
    
    /**
     * Restore all shim routes for running sandboxes
     * Call this on service start to ensure connectivity after reboot
     * 
     * @return array List of IPs for which routes were added
     */
    public static function restoreAllShimRoutes(): array
    {
        if (!self::isFullIpMode()) {
            return []; // Not needed for bridge/nat mode
        }
        
        $restored = [];
        $sandboxes = self::listSandboxes();
        
        foreach ($sandboxes as $sandbox) {
            if (strtolower($sandbox['status']) === 'running' && !empty($sandbox['ip'])) {
                $ip = $sandbox['ip'];
                self::addShimRoute($ip);
                $restored[] = $ip;
            }
        }
        
        return $restored;
    }
    
    /**
     * Ensure a specific sandbox has proper routing and services
     * Called when accessing a sandbox to ensure it's fully operational
     * Automatically migrates container to current network mode if needed
     * 
     * @param string $userId User/sandbox identifier
     * @return bool True if sandbox is accessible
     */
    public static function ensureSandboxAccessible(string $userId): bool
    {
        $name = self::containerName($userId);
        
        // Ensure container exists
        if (!self::sandboxExists($userId)) {
            return false;
        }
        
        // Check if container needs network migration
        self::migrateContainerNetworkIfNeeded($userId);
        
        // Ensure container is running
        if (!self::sandboxRunning($userId)) {
            exec(self::LXC_CMD . " start $name 2>&1");
            sleep(2);
        }
        
        // Get actual IP from container
        $ip = self::queryContainerIp($userId);
        if (!$ip) {
            return false;
        }
        
        // Ensure shim route exists (for macvlan/ipvlan)
        if (self::isFullIpMode()) {
            self::addShimRoute($ip);
        }
        
        // Ensure Caddy is running
        exec(self::LXC_CMD . " exec $name -- sh -c 'pgrep caddy || caddy start --config /etc/caddy/Caddyfile --adapter caddyfile 2>&1 &' 2>&1");
        
        return true;
    }
    
    /**
     * Check if container's network config matches current mode, and migrate if not
     * This allows containers to seamlessly switch between bridge/nat and macvlan modes
     * 
     * @param string $userId User/sandbox identifier
     * @return bool True if migration was performed
     */
    public static function migrateContainerNetworkIfNeeded(string $userId): bool
    {
        $name = self::containerName($userId);
        $currentMode = self::getNetworkMode();
        
        // Get current container network device type
        $output = [];
        exec(self::LXC_CMD . " config device show $name 2>/dev/null", $output, $code);
        $deviceConfig = implode("\n", $output);
        
        error_log("[MIGRATE] Container $name: currentMode=$currentMode, deviceConfig=" . substr(preg_replace('/\s+/', ' ', $deviceConfig), 0, 200));
        
        // Both macvlan and ipvlan modes use macvlan nictype (ipvlan conflicts with macvlan shim)
        $containerUsesMacvlan = (strpos($deviceConfig, 'nictype: macvlan') !== false ||
                                  strpos($deviceConfig, 'nictype: ipvlan') !== false);
        $containerUsesBridge = (strpos($deviceConfig, 'nictype: bridged') !== false ||
                                strpos($deviceConfig, 'network: lxdbr0') !== false);
        
        $needsMigration = false;
        
        if (($currentMode === 'macvlan' || $currentMode === 'ipvlan') && !$containerUsesMacvlan) {
            // Need to migrate TO macvlan (used for both macvlan and ipvlan modes)
            $needsMigration = true;
            error_log("[MIGRATE] Container $name needs migration: -> macvlan");
        } elseif (($currentMode === 'bridge' || $currentMode === 'nat') && !$containerUsesBridge) {
            // Need to migrate TO bridge
            $needsMigration = true;
            error_log("[MIGRATE] Container $name needs migration: -> bridge");
        }
        
        if (!$needsMigration) {
            error_log("[MIGRATE] Container $name: no migration needed (macvlan=$containerUsesMacvlan, bridge=$containerUsesBridge)");
            return false;
        }
        
        // Stop container for network reconfiguration
        $wasRunning = self::sandboxRunning($userId);
        if ($wasRunning) {
            exec(self::LXC_CMD . " stop $name --force 2>&1");
        }
        
        // Remove current eth0 device
        exec(self::LXC_CMD . " config device remove $name eth0 2>/dev/null");
        
        $ip = self::sandboxToIp($userId);
        
        if ($currentMode === 'macvlan' || $currentMode === 'ipvlan') {
            // Configure for macvlan/ipvlan - both use macvlan nictype with dummy interface
            // (ipvlan has infrastructure conflicts with macvlan shim, so we use macvlan for both)
            $parentIface = self::getNetworkInterface();
            exec(self::LXC_CMD . " config device add $name eth0 nic nictype=macvlan parent=$parentIface 2>&1");
        } else {
            // Configure for bridge/nat
            exec(self::LXC_CMD . " config device add $name eth0 nic nictype=bridged parent=lxdbr0 2>&1");
        }
        
        // Start container
        exec(self::LXC_CMD . " start $name 2>&1");
        sleep(2);
        
        if ($currentMode === 'macvlan' || $currentMode === 'ipvlan') {
            // Configure static IP inside container
            $shimIp = '0.0.0.1';
            $ipCmd = "ip addr flush dev eth0 2>/dev/null; ip addr add $ip/32 dev eth0; ip link set eth0 up; ip route add $shimIp/32 dev eth0 2>/dev/null || true";
            exec(self::LXC_CMD . " exec $name -- sh -c " . escapeshellarg($ipCmd) . " 2>&1");
            
            // Write persistent config
            $netConfig = "auto lo\niface lo inet loopback\n\nauto eth0\niface eth0 inet static\n    address $ip\n    netmask 255.255.255.255\n    post-up ip route add $shimIp/32 dev eth0 2>/dev/null || true\n";
            $escapedConfig = base64_encode($netConfig);
            exec(self::LXC_CMD . " exec $name -- sh -c 'echo $escapedConfig | base64 -d > /etc/network/interfaces' 2>&1");
            
            // Add shim route on host
            self::addShimRoute($ip);
        } else {
            // For bridge/nat, configure for DHCP (LXD handles it)
            $netConfig = "auto lo\niface lo inet loopback\n\nauto eth0\niface eth0 inet dhcp\n";
            $escapedConfig = base64_encode($netConfig);
            exec(self::LXC_CMD . " exec $name -- sh -c 'echo $escapedConfig | base64 -d > /etc/network/interfaces' 2>&1");
            exec(self::LXC_CMD . " exec $name -- sh -c 'udhcpc -i eth0 -q 2>/dev/null || dhclient eth0 2>/dev/null || true' 2>&1");
        }
        
        // Restart networking
        exec(self::LXC_CMD . " exec $name -- sh -c 'rc-service networking restart 2>/dev/null || true' 2>&1");
        
        // Start Caddy
        exec(self::LXC_CMD . " exec $name -- sh -c 'pgrep caddy || caddy start --config /etc/caddy/Caddyfile --adapter caddyfile 2>&1 &' 2>&1");
        
        // Wait for container to be reachable - poll up to 10 seconds
        $containerIp = self::queryContainerIp($userId);
        $ready = false;
        for ($i = 0; $i < 20; $i++) {
            if ($containerIp) {
                // Try to connect to port 80
                $fp = @fsockopen($containerIp, 80, $errno, $errstr, 0.5);
                if ($fp) {
                    fclose($fp);
                    $ready = true;
                    break;
                }
            }
            usleep(500000); // 0.5 seconds
            // Re-query IP in case DHCP just assigned it
            if (!$containerIp) {
                $containerIp = self::queryContainerIp($userId);
            }
        }
        
        error_log("[MIGRATE] Container $name migrated to $currentMode mode (ready=" . ($ready ? 'yes' : 'no') . ", ip=$containerIp)");
        return true;
    }
    
    /**
     * Query LXD for container's actual IP address
     * 
     * @param string $sandboxId The sandbox identifier
     * @return string|null IP address or null if not found
     */
    public static function queryLxdIp(string $sandboxId): ?string
    {
        $name = self::containerName($sandboxId);
        
        $output = [];
        $code = 0;
        exec(self::LXC_CMD . " list " . escapeshellarg($name) . " --format csv -c 4 2>/dev/null", $output, $code);
        
        if ($code !== 0 || empty($output)) {
            return null;
        }
        
        // Parse output like "10.166.3.85 (eth0)"
        $ipLine = trim($output[0]);
        if (preg_match('/(\d+\.\d+\.\d+\.\d+)/', $ipLine, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Get a Redis connection (returns null if Redis unavailable)
     * Redis is used for agent state (metrics, queues) NOT for IP routing
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
     * 
     * Always queries LXD for actual container IP first (survives mode changes and reboots).
     * Falls back to computed deterministic IP only if container not running or query fails.
     * 
     * @param string $userId User/sandbox identifier
     * @return string|null IP address or null if not found
     */
    public static function getSandboxIp(string $userId): ?string
    {
        // Always query LXD for actual IP first - this survives mode changes and reboots
        $actualIp = self::queryContainerIp($userId);
        if ($actualIp) {
            return $actualIp;
        }
        
        // Fallback to computed IP only if query fails (container not running, etc.)
        return self::sandboxToIp($userId);
    }
    
    /**
     * Query actual container IP from LXD
     * 
     * @param string $userId User/sandbox identifier
     * @return string|null IP address or null if not found
     */
    public static function queryContainerIp(string $userId): ?string
    {
        $name = self::containerName($userId);
        
        // Query LXD for container state
        exec(self::LXC_CMD . " list $name --format json 2>/dev/null", $output, $code);
        
        if ($code !== 0 || empty($output)) {
            return null;
        }
        
        try {
            $data = json_decode(implode('', $output), true);
            if (!$data || !isset($data[0]['state']['network'])) {
                return null;
            }
            
            // Find first non-loopback IPv4 address
            foreach ($data[0]['state']['network'] as $iface => $net) {
                if ($iface === 'lo') continue;
                foreach ($net['addresses'] ?? [] as $addr) {
                    if ($addr['family'] === 'inet') {
                        return $addr['address'];
                    }
                }
            }
        } catch (\Exception $e) {
            return null;
        }
        
        return null;
    }
    
    /**
     * Create a new sandbox for a user with static IP
     * 
     * IP is deterministic from userId via SHA256 - no DHCP needed.
     * 
     * @param string $userId User identifier
     * @param array $options Optional settings: packages, cpu, memory, disk
     * @return array ['success' => bool, 'ip' => string|null, 'sandboxId' => string, 'error' => string|null]
     */
    public static function createSandbox(string $userId, array $options = []): array
    {
        $name = self::containerName($userId);
        $ip = self::sandboxToIp($userId);  // Deterministic IP
        
        // Check if already exists
        if (self::sandboxExists($userId)) {
            // Start if not running
            if (!self::sandboxRunning($userId)) {
                exec(self::LXC_CMD . " start $name < /dev/null 2>&1", $output, $code);
                sleep(2);
            }
            
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
        
        // =======================================================================
        // STATIC IP ASSIGNMENT - Deterministic from sandbox ID
        // =======================================================================
        
        $networkMode = self::getNetworkMode();
        
        if ($networkMode === 'macvlan' || $networkMode === 'ipvlan') {
            // MACVLAN/IPVLAN MODE: Configure network device and set IP inside container
            $parentIface = self::getNetworkInterface();
            $nicType = ($networkMode === 'macvlan') ? 'macvlan' : 'ipvlan';
            
            // Remove default eth0 device from profile
            exec(self::LXC_CMD . " config device remove $name eth0 2>/dev/null");
            
            // Add macvlan/ipvlan device
            exec(self::LXC_CMD . " config device add $name eth0 nic nictype=$nicType parent=$parentIface 2>&1", $devOutput, $devCode);
            
            // Restart container to get new network device
            exec(self::LXC_CMD . " restart $name < /dev/null 2>&1");
            sleep(2);
            
            // Get the shim IP for routing (0.0.0.1 for macvlan, host gateway for ipvlan)
            $shimIp = ($networkMode === 'macvlan') ? '0.0.0.1' : '0.0.0.1';
            
            // Configure static IP inside container using /32 (full 32-bit IP space)
            // Use ip commands directly for reliability on Alpine
            // Also add route to shim for host-to-container communication
            $ipCmd = "ip addr flush dev eth0 2>/dev/null; ip addr add $ip/32 dev eth0; ip link set eth0 up; ip route add $shimIp/32 dev eth0 2>/dev/null";
            exec(self::LXC_CMD . " exec $name -- sh -c " . escapeshellarg($ipCmd) . " 2>&1");
            
            // Also write persistent config for reboots
            $netConfig = "auto lo\niface lo inet loopback\n\nauto eth0\niface eth0 inet static\n    address $ip\n    netmask 255.255.255.255\n    post-up ip route add $shimIp/32 dev eth0 2>/dev/null || true\n";
            $escapedConfig = base64_encode($netConfig);
            exec(self::LXC_CMD . " exec $name -- sh -c 'echo $escapedConfig | base64 -d > /etc/network/interfaces' 2>&1");
            
            // Create local.d script for Caddy auto-start on boot
            $caddyStartScript = "#!/bin/sh\ncd /home && caddy start --config /etc/caddy/Caddyfile --adapter caddyfile 2>/dev/null &\n";
            $escapedCaddy = base64_encode($caddyStartScript);
            exec(self::LXC_CMD . " exec $name -- sh -c 'mkdir -p /etc/local.d && echo $escapedCaddy | base64 -d > /etc/local.d/caddy.start && chmod +x /etc/local.d/caddy.start' 2>&1");
            exec(self::LXC_CMD . " exec $name -- rc-update add local default 2>/dev/null || true");
            
            // Add shim route for host-to-container communication
            self::addShimRoute($ip);
        } else {
            // BRIDGE/NAT MODE: Use LXD managed IP assignment
            exec(self::LXC_CMD . " config device override $name eth0 ipv4.address=$ip 2>&1", $ipOutput, $ipCode);
            
            if ($ipCode !== 0) {
                // Fallback: try adding device if override failed
                exec(self::LXC_CMD . " config device add $name eth0 nic nictype=bridged parent=lxdbr0 ipv4.address=$ip 2>&1");
            }
            
            // Restart to apply static IP
            exec(self::LXC_CMD . " restart $name < /dev/null 2>&1");
            sleep(2);
        }
        
        // Brief wait for container networking to be ready
        sleep(1);
        
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
        
        // Services (php-fpm, caddy) - start directly as rc-service may fail
        // PHP-FPM service name varies by version (php-fpm82, php-fpm83, php-fpm85, etc.)
        exec(self::LXC_CMD . " exec $name -- sh -c 'rc-service php-fpm* start 2>/dev/null || true' 2>&1");
        
        // Start Caddy directly (more reliable than rc-service)
        exec(self::LXC_CMD . " exec $name -- sh -c 'caddy start --config /etc/caddy/Caddyfile --adapter caddyfile 2>&1 &' 2>&1");
        
        // Track agent creation in Redis (if available) - for metrics/monitoring
        $redis = self::getRedis();
        if ($redis) {
            try {
                $redis->set(self::REDIS_PREFIX . $userId . ':created', time());
                $redis->set(self::REDIS_PREFIX . $userId . ':ip', $ip);
            } catch (\Throwable $e) {
                // Redis is optional for metrics
            }
        }
        
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
        // PHP-FPM service name varies by version
        exec(self::LXC_CMD . " exec $containerName -- sh -c 'rc-service php-fpm* start 2>/dev/null || pgrep php-fpm || php-fpm' 2>&1");
        
        // Start Caddy directly (more reliable than rc-service)
        exec(self::LXC_CMD . " exec $containerName -- sh -c 'pgrep caddy || caddy start --config /etc/caddy/Caddyfile --adapter caddyfile' 2>&1");
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
