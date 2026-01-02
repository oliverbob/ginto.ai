<?php
/**
 * Sandbox Proxy Helper
 * 
 * Proxies HTTP requests from /clients/ to the user's LXD container's web server.
 * The sandbox ID is determined from the user's session, not the URL.
 * Uses DETERMINISTIC IP allocation via SHA256(sandboxId) for infinite scale.
 * Redis is available for agent state (metrics, queues) but NOT for IP routing.
 * 
 * Architecture:
 *   Browser → /clients/path → (PHP gets sandbox from session) → http://{containerIp}:80/path
 *   
 * IP is computed: SHA256(sandboxId) → IP address (no lookup needed)
 */
namespace Ginto\Helpers;

class SandboxProxy
{
    /**
     * Redis key prefix for agent state (NOT for IP lookup)
     */
    const REDIS_PREFIX = 'agent:';
    
    /**
     * Container port to proxy to
     */
    const CONTAINER_PORT = 80;
    
    /**
     * Connection timeout in seconds
     */
    const CONNECT_TIMEOUT = 5;
    
    /**
     * Read timeout in seconds
     */
    const READ_TIMEOUT = 30;
    
    /**
     * Get Redis connection
     */
    private static function getRedis(): ?\Redis
    {
        if (!class_exists('Redis')) {
            return null;
        }
        
        try {
            $redis = new \Redis();
            $redis->connect('127.0.0.1', 6379, 1.0); // 1 second timeout
            return $redis;
        } catch (\Throwable $e) {
            error_log('[SandboxProxy] Redis connection failed: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get sandbox IP - DETERMINISTIC
     * 
     * IP is computed directly from sandboxId using SHA256.
     * No Redis lookup needed - pure computation, O(1).
     * 
     * @param string $sandboxId The sandbox identifier
     * @return string IP address (always returns a value)
     */
    public static function getSandboxIp(string $sandboxId): string
    {
        // Deterministic: use LxdSandboxManager's computation
        $ip = LxdSandboxManager::sandboxToIp($sandboxId);
        
        // Track access in Redis (async, non-blocking for metrics)
        self::trackAccess($sandboxId);
        
        return $ip;
    }
    
    /**
     * Track sandbox access for metrics (non-blocking)
     */
    private static function trackAccess(string $sandboxId): void
    {
        $redis = self::getRedis();
        if (!$redis) {
            return;
        }
        
        try {
            $redis->set(self::REDIS_PREFIX . $sandboxId . ':last', time());
            $redis->incr(self::REDIS_PREFIX . $sandboxId . ':requests');
        } catch (\Throwable $e) {
            // Metrics are optional - don't fail on errors
        }
    }
    
    /**
     * Validate sandbox ID format (security check)
     */
    public static function isValidSandboxId(string $sandboxId): bool
    {
        // Must match the pattern used in clients/ directories
        // Alphanumeric + underscores/hyphens, 8-32 chars
        return (bool) preg_match('/^[a-zA-Z0-9_-]{8,32}$/', $sandboxId);
    }
    
    /**
     * Check if user owns this sandbox
     * 
     * Ownership is verified via database or session - no host directory check needed
     * since containers are self-contained (files live inside the LXD container).
     */
    public static function userOwnsSandbox(string $sandboxId, ?object $db = null, ?array $session = null): bool
    {
        if (empty($session['user_id'])) {
            return false;
        }
        
        // Primary: Check database for ownership
        if ($db) {
            try {
                $record = $db->selectFirst('client_sandboxes', ['user_id'], [
                    'sandbox_id' => $sandboxId
                ]);
                
                if ($record && isset($record['user_id'])) {
                    return (int)$record['user_id'] === (int)$session['user_id'];
                }
            } catch (\Throwable $e) {
                // Log but continue - we'll verify by session sandbox_id
                error_log('[SandboxProxy] DB ownership check failed: ' . $e->getMessage());
            }
        }
        
        // Fallback: check if session sandbox matches (for current session)
        if (isset($session['sandbox_id']) && $session['sandbox_id'] === $sandboxId) {
            return true;
        }
        
        // Check if LXD container exists (container name = sandbox-{sandboxId})
        if (LxdSandboxManager::sandboxExists($sandboxId)) {
            // Container exists - if user has no sandbox yet, this might be theirs
            // This fallback is for edge cases; primary verification should be via DB
            return false; // Deny by default if we can't verify ownership
        }
        
        return false;
    }
    
    /**
     * Proxy a request to the sandbox container
     * 
     * @param string $sandboxId The sandbox identifier
     * @param string $path The path to request (e.g., "/", "/api/data")
     * @param array $options Optional: method, headers, body, query
     * @return array ['success' => bool, 'status' => int, 'headers' => array, 'body' => string, 'error' => ?string]
     */
    public static function proxyRequest(string $sandboxId, string $path = '/', array $options = []): array
    {
        // Get container IP
        $ip = self::getSandboxIp($sandboxId);
        
        if (!$ip) {
            return [
                'success' => false,
                'status' => 503,
                'headers' => [],
                'body' => '',
                'error' => 'Sandbox not available or not running'
            ];
        }
        
        // Build target URL
        $targetUrl = 'http://' . $ip . ':' . self::CONTAINER_PORT . $path;
        
        // Add query string if present
        if (!empty($options['query'])) {
            $targetUrl .= '?' . (is_array($options['query']) ? http_build_query($options['query']) : $options['query']);
        }
        
        // Set up cURL
        $ch = curl_init($targetUrl);
        
        $method = strtoupper($options['method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::READ_TIMEOUT);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Don't follow redirects - let browser handle
        
        // Forward request headers
        $forwardHeaders = [];
        $skipHeaders = ['host', 'connection', 'content-length', 'transfer-encoding'];
        
        foreach (getallheaders() as $name => $value) {
            if (!in_array(strtolower($name), $skipHeaders, true)) {
                $forwardHeaders[] = "$name: $value";
            }
        }
        
        // Add X-Forwarded headers
        $forwardHeaders[] = 'X-Forwarded-For: ' . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $forwardHeaders[] = 'X-Forwarded-Host: ' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $forwardHeaders[] = 'X-Forwarded-Proto: ' . (($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http');
        $forwardHeaders[] = 'X-Sandbox-Id: ' . $sandboxId;
        
        if (!empty($options['headers'])) {
            $forwardHeaders = array_merge($forwardHeaders, $options['headers']);
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $forwardHeaders);
        
        // Forward request body for POST/PUT/PATCH
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $body = $options['body'] ?? file_get_contents('php://input');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        
        // Execute request
        $response = curl_exec($ch);
        
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            
            return [
                'success' => false,
                'status' => 502,
                'headers' => [],
                'body' => '',
                'error' => 'Failed to connect to sandbox: ' . $error
            ];
        }
        
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        
        // Parse response headers
        $headerText = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        $headers = [];
        foreach (explode("\r\n", $headerText) as $line) {
            if (strpos($line, ':') !== false) {
                list($name, $value) = explode(':', $line, 2);
                $headers[trim($name)] = trim($value);
            }
        }
        
        return [
            'success' => true,
            'status' => $statusCode,
            'headers' => $headers,
            'body' => $body,
            'error' => null
        ];
    }
    
    /**
     * Stream the proxied response to the client
     */
    public static function streamResponse(string $sandboxId, string $path = '/'): void
    {
        $result = self::proxyRequest($sandboxId, $path);
        
        // Set status code
        http_response_code($result['status']);
        
        if (!$result['success']) {
            // Error page
            header('Content-Type: text/html; charset=utf-8');
            echo self::errorPage($result['status'], $result['error']);
            return;
        }
        
        // Forward response headers (skip some that should not be forwarded)
        $skipHeaders = ['transfer-encoding', 'connection', 'keep-alive'];
        foreach ($result['headers'] as $name => $value) {
            if (!in_array(strtolower($name), $skipHeaders, true)) {
                header("$name: $value");
            }
        }
        
        // Output body
        echo $result['body'];
    }
    
    /**
     * Generate an error page
     */
    private static function errorPage(int $status, ?string $message = null): string
    {
        $title = match($status) {
            503 => 'Sandbox Unavailable',
            502 => 'Connection Failed',
            404 => 'Sandbox Not Found',
            403 => 'Access Denied',
            default => 'Error'
        };
        
        $msg = htmlspecialchars($message ?? 'An error occurred');
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - Ginto Sandbox</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        .container {
            text-align: center;
            padding: 40px;
            background: rgba(255,255,255,0.05);
            border-radius: 16px;
            backdrop-filter: blur(10px);
            max-width: 500px;
        }
        h1 { font-size: 3em; margin-bottom: 10px; color: #f59e0b; }
        .status { font-size: 6em; font-weight: bold; color: #ef4444; }
        p { color: #9ca3af; margin: 20px 0; }
        a { color: #60a5fa; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .btn {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }
        .btn:hover { opacity: 0.9; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="status">{$status}</div>
        <h1>{$title}</h1>
        <p>{$msg}</p>
        <a href="/chat" class="btn">← Back to Chat</a>
    </div>
</body>
</html>
HTML;
    }
}
