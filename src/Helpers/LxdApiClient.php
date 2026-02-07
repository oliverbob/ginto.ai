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
     * Make a POST request to the LXD API
     * 
     * LXD async operations return immediately with an operation URL.
     * Use getOperation() or waitOperation() to check status.
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param int $timeout Request timeout in seconds
     * @return array|null Response data or null on error
     */
    public static function post(string $endpoint, array $data, int $timeout = 10): ?array
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
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // LXD returns 200, 201 (created), or 202 (accepted/async)
        if (!in_array($httpCode, [200, 201, 202]) || empty($response)) {
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
     * Make a DELETE request to the LXD API
     * 
     * @param string $endpoint API endpoint
     * @param int $timeout Request timeout in seconds
     * @return array|null Response data or null on error
     */
    public static function delete(string $endpoint, int $timeout = 10): ?array
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
            CURLOPT_CUSTOMREQUEST => 'DELETE',
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
     * Get operation status by ID
     * 
     * Operations are created by async LXD requests (copy, start, etc.)
     * 
     * @param string $operationId Operation UUID (from previous response)
     * @return array|null Operation metadata or null on error
     */
    public static function getOperation(string $operationId): ?array
    {
        // Handle full path or just UUID
        if (strpos($operationId, '/') !== false) {
            $data = self::get($operationId);
        } else {
            $data = self::get("/1.0/operations/" . urlencode($operationId));
        }
        return $data['metadata'] ?? null;
    }
    
    /**
     * Wait for an operation to complete (blocking)
     * 
     * @param string $operationId Operation UUID
     * @param int $timeout Max wait time in seconds
     * @return array|null Final operation state or null on timeout/error
     */
    public static function waitOperation(string $operationId, int $timeout = 60): ?array
    {
        // LXD's /wait endpoint blocks until operation completes
        $opPath = strpos($operationId, '/') !== false 
            ? $operationId 
            : "/1.0/operations/" . urlencode($operationId);
        
        $data = self::get($opPath . "/wait?timeout=" . $timeout, $timeout + 5);
        return $data['metadata'] ?? null;
    }
    
    /**
     * Copy an instance (container) - async
     * 
     * Returns immediately with operation ID. Poll with getOperation().
     * 
     * @param string $source Source container name
     * @param string $target Target container name
     * @return array ['success' => bool, 'operation' => string|null, 'error' => string|null]
     */
    public static function copyInstanceAsync(string $source, string $target): array
    {
        $data = [
            'name' => $target,
            'source' => [
                'type' => 'copy',
                'source' => $source,
            ],
        ];
        
        $result = self::post("/1.0/instances", $data, 5);
        
        if (!$result) {
            return ['success' => false, 'operation' => null, 'error' => 'API request failed'];
        }
        
        // Check for async operation
        if (isset($result['operation'])) {
            return [
                'success' => true,
                'operation' => $result['operation'],
                'error' => null,
            ];
        }
        
        // Sync success (unlikely for copy)
        if (($result['status_code'] ?? 0) >= 200 && ($result['status_code'] ?? 0) < 300) {
            return ['success' => true, 'operation' => null, 'error' => null];
        }
        
        return [
            'success' => false,
            'operation' => null,
            'error' => $result['error'] ?? 'Unknown error',
        ];
    }
    
    /**
     * Start an instance - async
     * 
     * @param string $name Container name
     * @return array ['success' => bool, 'operation' => string|null, 'error' => string|null]
     */
    public static function startInstanceAsync(string $name): array
    {
        $result = self::put("/1.0/instances/" . urlencode($name) . "/state", [
            'action' => 'start',
            'timeout' => 30,
        ], 5);
        
        if (!$result) {
            return ['success' => false, 'operation' => null, 'error' => 'API request failed'];
        }
        
        if (isset($result['operation'])) {
            return [
                'success' => true,
                'operation' => $result['operation'],
                'error' => null,
            ];
        }
        
        return [
            'success' => ($result['status_code'] ?? 0) >= 200 && ($result['status_code'] ?? 0) < 300,
            'operation' => null,
            'error' => $result['error'] ?? null,
        ];
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
        
        // Skip virtual/internal interfaces - prefer eth0 or enp* (real network)
        $skipInterfaces = ['lo', 'docker0', 'br-', 'veth', 'virbr'];
        
        // First try to find eth0 or enp* specifically
        foreach ($state['network'] ?? [] as $iface => $ifData) {
            if (preg_match('/^(eth|enp)/', $iface)) {
                foreach ($ifData['addresses'] ?? [] as $addr) {
                    if ($addr['family'] === 'inet') {
                        return $addr['address'];
                    }
                }
            }
        }
        
        // Fallback: find any non-virtual interface
        foreach ($state['network'] ?? [] as $iface => $ifData) {
            $skip = false;
            foreach ($skipInterfaces as $prefix) {
                if (str_starts_with($iface, $prefix)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;
            
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
    
    /**
     * List all instances with full details (recursion=2 for state info)
     * 
     * Much faster than calling getInstance() + getInstanceState() for each container.
     * Single API call returns all containers with their state.
     * 
     * @return array List of instance data with state
     */
    public static function listInstancesWithState(): array
    {
        // recursion=2 includes full instance info + state in one call
        $data = self::get("/1.0/instances?recursion=2", 10);
        if (!$data || !isset($data['metadata'])) {
            return [];
        }
        
        return $data['metadata'];
    }
    
    /**
     * Stop an instance - async
     * 
     * @param string $name Container name
     * @param bool $force Force stop
     * @return array ['success' => bool, 'operation' => string|null, 'error' => string|null]
     */
    public static function stopInstanceAsync(string $name, bool $force = false): array
    {
        $result = self::put("/1.0/instances/" . urlencode($name) . "/state", [
            'action' => 'stop',
            'timeout' => 30,
            'force' => $force,
        ], 5);
        
        if (!$result) {
            return ['success' => false, 'operation' => null, 'error' => 'API request failed'];
        }
        
        if (isset($result['operation'])) {
            return [
                'success' => true,
                'operation' => $result['operation'],
                'error' => null,
            ];
        }
        
        return [
            'success' => ($result['status_code'] ?? 0) >= 200 && ($result['status_code'] ?? 0) < 300,
            'operation' => null,
            'error' => $result['error'] ?? null,
        ];
    }
    
    /**
     * Restart an instance - async
     * 
     * @param string $name Container name
     * @return array ['success' => bool, 'operation' => string|null, 'error' => string|null]
     */
    public static function restartInstanceAsync(string $name): array
    {
        $result = self::put("/1.0/instances/" . urlencode($name) . "/state", [
            'action' => 'restart',
            'timeout' => 30,
        ], 5);
        
        if (!$result) {
            return ['success' => false, 'operation' => null, 'error' => 'API request failed'];
        }
        
        if (isset($result['operation'])) {
            return [
                'success' => true,
                'operation' => $result['operation'],
                'error' => null,
            ];
        }
        
        return [
            'success' => ($result['status_code'] ?? 0) >= 200 && ($result['status_code'] ?? 0) < 300,
            'operation' => null,
            'error' => $result['error'] ?? null,
        ];
    }
    
    /**
     * Delete an instance - async
     * 
     * @param string $name Container name
     * @return array ['success' => bool, 'operation' => string|null, 'error' => string|null]
     */
    public static function deleteInstanceAsync(string $name): array
    {
        $result = self::delete("/1.0/instances/" . urlencode($name), 5);
        
        if (!$result) {
            return ['success' => false, 'operation' => null, 'error' => 'API request failed'];
        }
        
        if (isset($result['operation'])) {
            return [
                'success' => true,
                'operation' => $result['operation'],
                'error' => null,
            ];
        }
        
        return [
            'success' => ($result['status_code'] ?? 0) >= 200 && ($result['status_code'] ?? 0) < 300,
            'operation' => null,
            'error' => $result['error'] ?? null,
        ];
    }
}
