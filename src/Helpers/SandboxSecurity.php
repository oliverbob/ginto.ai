<?php
/**
 * Sandbox Security Helper
 * 
 * Provides command filtering and security checks for terminal access
 * to LXD sandbox containers.
 * 
 * IMPORTANT: This is a defense-in-depth measure. The primary security
 * comes from LXD container isolation, but we add application-level
 * restrictions as an additional layer.
 */
namespace Ginto\Helpers;

class SandboxSecurity
{
    // Commands that are completely blocked (can't be run at all)
    const BLOCKED_COMMANDS = [
        // Container escape attempts
        'lxc', 'lxd', 'docker', 'podman', 'nspawn', 'machinectl',
        'chroot', 'pivot_root', 'unshare', 'nsenter',
        
        // Privilege escalation
        'sudo', 'su', 'doas', 'pkexec',
        
        // Kernel/system manipulation
        'insmod', 'rmmod', 'modprobe', 'sysctl',
        'mount', 'umount', 'losetup',
        
        // Network attacks
        'iptables', 'ip6tables', 'nft', 'nftables',
        'tcpdump', 'wireshark', 'tshark', 'nmap', 'masscan',
        'hping', 'hping3', 'arpspoof', 'ettercap',
        
        // Crypto mining (common miners)
        'xmrig', 'minerd', 'cpuminer', 'cgminer', 'bfgminer',
        'ethminer', 'claymore', 'phoenixminer',
        
        // Dangerous system tools
        'dd', 'shred', 'mkfs', 'fdisk', 'parted',
        'shutdown', 'reboot', 'poweroff', 'halt', 'init',
        
        // Package managers (prevent installing attack tools)
        // Note: Uncomment if you want to block package installation
        // 'apk', 'apt', 'apt-get', 'yum', 'dnf', 'pacman', 'npm', 'pip',
    ];
    
    // Patterns that indicate malicious activity
    const BLOCKED_PATTERNS = [
        // Reverse shells
        '/bin/bash -i',
        '/bin/sh -i',
        'bash -c.*>/dev/tcp',
        'nc -e',
        'nc -c',
        'ncat -e',
        'socat.*exec',
        'python.*pty.spawn',
        'perl.*socket',
        'ruby.*socket',
        'php -r.*fsockopen',
        
        // Fork bombs
        ':(){ :|:& };:',
        'fork while fork',
        
        // Kernel exploitation
        '/proc/sys',
        '/sys/kernel',
        '/dev/mem',
        '/dev/kmem',
        '/dev/port',
        
        // Container escape paths
        '/run/lxd',
        '/var/lib/lxd',
        '/.dockerenv',
        '/run/docker.sock',
    ];
    
    // Rate limiting: disabled for terminal commands (high-scale deployment)
    // Note: Container-level limits (processes, memory) provide protection
    const RATE_LIMIT = 0; // 0 = disabled
    const RATE_WINDOW = 60; // seconds
    
    /**
     * Check if a command is safe to execute
     * 
     * @param string $command The command to check
     * @param string $sandboxId The sandbox identifier
     * @return array ['allowed' => bool, 'reason' => string|null]
     */
    public static function isCommandAllowed(string $command, string $sandboxId = ''): array
    {
        $command = trim($command);
        
        // Empty command is fine (just pressing enter)
        if (empty($command)) {
            return ['allowed' => true, 'reason' => null];
        }
        
        // Extract the base command (first word)
        $parts = preg_split('/\s+/', $command);
        $baseCmd = basename($parts[0] ?? '');
        
        // Check blocked commands list
        if (in_array(strtolower($baseCmd), array_map('strtolower', self::BLOCKED_COMMANDS), true)) {
            return [
                'allowed' => false,
                'reason' => "Command '$baseCmd' is not allowed in sandbox"
            ];
        }
        
        // Check blocked patterns
        foreach (self::BLOCKED_PATTERNS as $pattern) {
            if (stripos($command, $pattern) !== false || @preg_match("/$pattern/i", $command)) {
                return [
                    'allowed' => false,
                    'reason' => "Command contains blocked pattern"
                ];
            }
        }
        
        // Check for pipe to dangerous commands
        if (preg_match('/\|\s*(sudo|su|bash\s+-c|sh\s+-c)/i', $command)) {
            return [
                'allowed' => false,
                'reason' => "Piping to privileged commands is not allowed"
            ];
        }
        
        // Check for writing to sensitive paths
        if (preg_match('/>\s*\/(proc|sys|dev|run|var\/run)/i', $command)) {
            return [
                'allowed' => false,
                'reason' => "Writing to system paths is not allowed"
            ];
        }
        
        return ['allowed' => true, 'reason' => null];
    }
    
    /**
     * Check rate limiting for a sandbox
     * 
     * @param string $sandboxId The sandbox identifier
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_in' => int]
     */
    public static function checkRateLimit(string $sandboxId): array
    {
        // Rate limiting disabled for high-scale deployment
        if (self::RATE_LIMIT <= 0) {
            return ['allowed' => true, 'remaining' => PHP_INT_MAX, 'reset_in' => 0];
        }
        
        // Try Redis first
        try {
            if (class_exists('Redis')) {
                $redis = new \Redis();
                $redis->connect('127.0.0.1', 6379);
                
                $key = "sandbox_rate:{$sandboxId}";
                $count = (int)$redis->get($key);
                $ttl = $redis->ttl($key);
                
                if ($ttl < 0) {
                    // Key doesn't exist or has no TTL, start fresh
                    $redis->setex($key, self::RATE_WINDOW, 1);
                    return ['allowed' => true, 'remaining' => self::RATE_LIMIT - 1, 'reset_in' => self::RATE_WINDOW];
                }
                
                if ($count >= self::RATE_LIMIT) {
                    return ['allowed' => false, 'remaining' => 0, 'reset_in' => $ttl];
                }
                
                $redis->incr($key);
                return ['allowed' => true, 'remaining' => self::RATE_LIMIT - $count - 1, 'reset_in' => $ttl];
            }
        } catch (\Throwable $e) {
            // Redis not available, skip rate limiting
        }
        
        // Fallback: always allow if Redis isn't available
        return ['allowed' => true, 'remaining' => self::RATE_LIMIT, 'reset_in' => 0];
    }
    
    /**
     * Sanitize a sandbox ID for safe use in shell commands
     */
    public static function sanitizeSandboxId(string $sandboxId): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $sandboxId);
    }
    
    /**
     * Log a security event
     */
    public static function logSecurityEvent(string $type, string $sandboxId, string $message, array $context = []): void
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $type,
            'sandbox_id' => $sandboxId,
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 3) . '/storage';
        $logFile = $storagePath . '/logs/sandbox_security.log';
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        
        @file_put_contents(
            $logFile,
            json_encode($logEntry) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
    
    /**
     * Get security summary for a sandbox
     */
    public static function getSecurityConfig(): array
    {
        return [
            'blocked_commands' => self::BLOCKED_COMMANDS,
            'rate_limit' => self::RATE_LIMIT,
            'rate_window' => self::RATE_WINDOW,
            'features' => [
                'nesting_enabled' => true,  // Docker/LXC allowed inside
                'nesting_protection' => 'proxmox-style',  // Syscall interception
                'privileged_disabled' => true,
                'idmap_isolated' => true,  // UID namespace isolation
                'process_limit' => 200,
                'memory_limit' => '1GB',
                'cpu_limit' => 2,
                'syscall_interception' => [
                    'mount' => 'ext4,tmpfs,proc,sysfs,cgroup,cgroup2,overlay',
                    'mknod' => true,
                    'setxattr' => true,
                    'bpf' => true,
                ],
            ]
        ];
    }
}
