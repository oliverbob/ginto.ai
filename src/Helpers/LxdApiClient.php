<?php
/**
 * LXD REST API Client
 * 
 * Provides fast access to LXD via Unix socket instead of CLI.
 * CLI commands (lxc list, lxc info, etc.) take 100-300ms.
 * Unix socket API calls take 5-20ms - a 10-20x speedup.
 * 
 * @see https://linuxcontainers.org/lxd/docs/master/api/
 */
namespace Ginto\Helpers;

class LxdApiClient
{
    // LXD Unix socket paths (snap vs non-snap)
    const SOCKET_PATHS = [
        '/var/snap/lxd/common/lxd/unix.socket',
        '/var/lib/lxd/unix.socket',
    ];
    
    // Cache the working socket path
    private static ?string $socketPath = null;
    
    /**
     * Get the LXD Unix socket path
     * 
     * @return string|null Socket path or null if not found
     */
    public static function getSocketPath(): ?string
    {
        if (self::$socketPath !== null) {
            return self::$socketPath;
        }
        
        foreach (self::SOCKET_PATHS as $path) {
            if (file_exists($path) && is_readable($path)) {
                self::$socketPath = $path;
                return $path;
            }
        }
        
        return null;
    }
    
    /**
     * Make a GET request to the LXD API
     * 
     * @param string $endpoint API endpoint (e.g., "/1.0/instances")
     * @param int $timeout Request timeout in seconds
     * @return array|null Response data or null on error
     */
    public static function get(string $endpoint, int $timeout = 5): ?array
    {
        $socket = self::getSocketPath();
        if (!$socket) {
            return null;
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_UNIX_SOCKET_PATH => $socket,
            CURLOPT_URL => "http://localhost" . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200 || empty($response)) {
            return null;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Make a PUT request to the LXD API
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param int $timeout Request timeout in seconds
     * @return array|null Response data or null on error
     */
    public static function put(string $endpoint, array $data, int $timeout = 10): ?array
    {
        $socket = self::getSocketPath();
        if (!$socket) {
            return null;
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_UNIX_SOCKET_PATH => $socket,
            CURLOPT_URL => "http://localhost" . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if (!in_array($httpCode, [200, 202]) || empty($response)) {
            return null;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Check if an instance (container) exists
     * 
     * @param string $name Container name
     * @return bool True if exists
     */
    public static function instanceExists(string $name): bool
    {
        $data = self::get("/1.0/instances/" . urlencode($name));
        return $data !== null && isset($data['metadata']);
    }
    
    /**
     * Get instance (container) info
     * 
     * @param string $name Container name
     * @return array|null Instance metadata or null if not found
     */
    public static function getInstance(string $name): ?array
    {
        $data = self::get("/1.0/instances/" . urlencode($name));
        return $data['metadata'] ?? null;
    }
    
    /**
     * Get instance state (running status, network, etc.)
     * 
     * @param string $name Container name
     * @return array|null State metadata or null if not found
     */
    public static function getInstanceState(string $name): ?array
    {
        $data = self::get("/1.0/instances/" . urlencode($name) . "/state");
        return $data['metadata'] ?? null;
    }
    
    /**
     * Check if an instance is running
     * 
     * @param string $name Container name
     * @return bool True if running
     */
    public static function instanceRunning(string $name): bool
    {
        $state = self::getInstanceState($name);
        return $state !== null && strtolower($state['status'] ?? '') === 'running';
    }
    
    /**
     * Get the IPv4 address of an instance
     * 
     * @param string $name Container name
     * @return string|null IPv4 address or null if not found
     */
    public static function getInstanceIp(string $name): ?string
    {
        $state = self::getInstanceState($name);
        if (!$state) {
            return null;
        }
        
        // Find first non-loopback IPv4 address
        foreach ($state['network'] ?? [] as $iface => $ifData) {
            if ($iface === 'lo') continue;
            foreach ($ifData['addresses'] ?? [] as $addr) {
                if ($addr['family'] === 'inet') {
                    return $addr['address'];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Start an instance
     * 
     * @param string $name Container name
     * @return bool Success
     */
    public static function startInstance(string $name): bool
    {
        $result = self::put("/1.0/instances/" . urlencode($name) . "/state", [
            'action' => 'start',
            'timeout' => 30,
        ]);
        return $result !== null;
    }
    
    /**
     * Stop an instance
     * 
     * @param string $name Container name
     * @param bool $force Force stop
     * @return bool Success
     */
    public static function stopInstance(string $name, bool $force = false): bool
    {
        $result = self::put("/1.0/instances/" . urlencode($name) . "/state", [
            'action' => 'stop',
            'timeout' => 30,
            'force' => $force,
        ]);
        return $result !== null;
    }
    
    /**
     * Check if LXD API is available
     * 
     * Result is cached for the request lifecycle to avoid repeated checks.
     * 
     * @return bool True if API is accessible
     */
    public static function isAvailable(): bool
    {
        static $available = null;
        
        if ($available !== null) {
            return $available;
        }
        
        $data = self::get("/1.0", 2);
        $available = $data !== null && isset($data['metadata']);
        return $available;
    }
    
    /**
     * List all instances
     * 
     * @return array List of instance names
     */
    public static function listInstances(): array
    {
        $data = self::get("/1.0/instances");
        if (!$data || !isset($data['metadata'])) {
            return [];
        }
        
        // Extract instance names from paths like "/1.0/instances/container-name"
        $names = [];
        foreach ($data['metadata'] as $path) {
            $parts = explode('/', $path);
            $names[] = end($parts);
        }
        
        return $names;
    }
}
