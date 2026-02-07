<?php
/**
 * Ginto Tunnel Server
 * 
 * A SirTunnel-inspired reverse tunnel service that allows local OpenWebUI
 * installations to be exposed to the web via [subdomain].ginto.ai
 * 
 * Architecture:
 * 1. Client connects via WebSocket and requests a subdomain
 * 2. Server generates JWT token and assigns subdomain
 * 3. Server creates DNS record and Caddy config
 * 4. HTTP requests to subdomain are forwarded through WebSocket to client
 * 5. Tunnel expires after 10 minutes for unregistered users
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Http\HttpServerInterface;
use Psr\Http\Message\RequestInterface;
use Ratchet\WebSocket\WsServer;
use React\Socket\SocketServer;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

class TunnelServer implements MessageComponentInterface
{
    protected \SplObjectStorage $clients;
    protected array $tunnels = [];      // subdomain => connection
    protected array $tokens = [];       // connection => token data
    protected array $pendingRequests = []; // requestId => deferred promise
    protected array $lastPong = [];     // connection => timestamp of last pong
    protected ?\Medoo\Medoo $db = null;
    
    private string $jwtSecret;
    public string $baseDomain = 'ginto.ai';
    private int $defaultExpiry = 600; // 10 minutes
    private int $pingInterval = 30;   // seconds between pings
    private int $pingTimeout = 90;    // seconds without pong before disconnect
    
    public function __construct(?string $jwtSecret = null)
    {
        $this->clients = new \SplObjectStorage();
        $this->jwtSecret = $jwtSecret ?? ($_ENV['TUNNEL_JWT_SECRET'] ?? bin2hex(random_bytes(32)));
        
        // Try to connect to database
        try {
            $envFile = dirname(__DIR__, 2) . '/.env';
            if (file_exists($envFile)) {
                $env = parse_ini_file($envFile);
                $this->db = new \Medoo\Medoo([
                    'type' => 'mysql',
                    'host' => $env['DB_HOST'] ?? 'localhost',
                    'database' => $env['DB_DATABASE'] ?? 'ginto',
                    'username' => $env['DB_USERNAME'] ?? 'ginto',
                    'password' => $env['DB_PASSWORD'] ?? '',
                ]);
            }
        } catch (\Exception $e) {
            error_log("[TunnelServer] Database connection failed: " . $e->getMessage());
        }
        
        echo "[TunnelServer] Started with base domain: {$this->baseDomain}\n";
    }
    
    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        $this->lastPong[$conn->resourceId] = time();
        echo "[TunnelServer] New WebSocket connection: {$conn->resourceId}\n";
    }
    
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        try {
            $data = json_decode($msg, true);
            if (!$data || !isset($data['type'])) {
                error_log("[TunnelServer] Invalid message payload: " . (is_string($msg) ? $msg : var_export($msg, true)));
                $this->sendError($from, 'Invalid message format');
                return;
            }
            
            switch ($data['type']) {
                case 'register':
                    $this->handleRegister($from, $data);
                    break;
                    
                case 'tunnel_response':
                    $this->handleTunnelResponse($from, $data);
                    break;
                    
                case 'ping':
                    $this->send($from, ['type' => 'pong']);
                    break;
                    
                case 'pong':
                    $this->lastPong[$from->resourceId] = time();
                    break;
                    
                default:
                    $this->sendError($from, 'Unknown message type: ' . $data['type']);
            }
        } catch (\Exception $e) {
            error_log("[TunnelServer] Error: " . $e->getMessage());
            $this->sendError($from, $e->getMessage());
        }
    }
    
    public function onClose(ConnectionInterface $conn): void
    {
        // Find and remove tunnel
        $tokenData = $this->tokens[$conn->resourceId] ?? null;
        if ($tokenData && isset($tokenData['subdomain'])) {
            $subdomain = $tokenData['subdomain'];
            unset($this->tunnels[$subdomain]);
            
            // Remove DNS record and Caddy config
            $this->removeTunnelConfig($subdomain);
            
            echo "[TunnelServer] Tunnel closed: {$subdomain}.{$this->baseDomain}\n";
        }
        
        unset($this->tokens[$conn->resourceId]);
        unset($this->lastPong[$conn->resourceId]);
        $this->clients->detach($conn);
        echo "[TunnelServer] WebSocket connection closed: {$conn->resourceId}\n";
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        error_log("[TunnelServer] Error on {$conn->resourceId}: " . $e->getMessage());
        $conn->close();
    }
    
    /**
     * Handle tunnel registration request
     */
    protected function handleRegister(ConnectionInterface $conn, array $data): void
    {
        $requestedSubdomain = $data['subdomain'] ?? null;
        $authToken = $data['auth_token'] ?? null;
        
        // Validate subdomain format
        if (!$requestedSubdomain || !preg_match('/^[a-z0-9][a-z0-9-]{1,30}[a-z0-9]$/', $requestedSubdomain)) {
            $this->sendError($conn, 'Invalid subdomain format. Use 3-32 lowercase alphanumeric characters and hyphens.');
            return;
        }
        
        // Check reserved subdomains
        $reserved = ['www', 'api', 'admin', 'mail', 'ftp', 'ssh', 'oi', 'tunnel', 'app', 'dev', 'test', 'staging'];
        if (in_array($requestedSubdomain, $reserved)) {
            $this->sendError($conn, 'This subdomain is reserved. Please choose another.');
            return;
        }
        
        // Check if subdomain is already in use
        if (isset($this->tunnels[$requestedSubdomain])) {
            $this->sendError($conn, 'Subdomain is already in use. Please choose another.');
            return;
        }
        
        // Determine expiry based on auth
        $expiry = time() + $this->defaultExpiry; // 10 minutes default
        $isAuthenticated = false;
        $userId = null;
        
        if ($authToken) {
            $authResult = $this->validateAuthToken($authToken);
            if ($authResult['valid']) {
                $isAuthenticated = true;
                $userId = $authResult['user_id'];
                $expiry = time() + (30 * 24 * 3600); // 30 days for authenticated users
            }
        }
        
        // Generate JWT for this tunnel session
        $tunnelToken = $this->generateTunnelToken($requestedSubdomain, $expiry, $userId);
        
        // Register the tunnel
        $this->tunnels[$requestedSubdomain] = $conn;
        $this->tokens[$conn->resourceId] = [
            'subdomain' => $requestedSubdomain,
            'token' => $tunnelToken,
            'expiry' => $expiry,
            'user_id' => $userId,
            'authenticated' => $isAuthenticated,
            'created_at' => time()
        ];
        
        // Create DNS record and Caddy config
        $configResult = $this->createTunnelConfig($requestedSubdomain, $conn->resourceId);
        
        if (!$configResult['success']) {
            unset($this->tunnels[$requestedSubdomain]);
            unset($this->tokens[$conn->resourceId]);
            $this->sendError($conn, 'Failed to setup tunnel: ' . ($configResult['error'] ?? 'Unknown error'));
            return;
        }
        
        // Send success response
        $this->send($conn, [
            'type' => 'registered',
            'subdomain' => $requestedSubdomain,
            'url' => "https://{$requestedSubdomain}.{$this->baseDomain}/",
            'token' => $tunnelToken,
            'expires_at' => $expiry,
            'expires_in' => $expiry - time(),
            'authenticated' => $isAuthenticated
        ]);
        
        echo "[TunnelServer] Tunnel registered: {$requestedSubdomain}.{$this->baseDomain} (expires in " . ($expiry - time()) . "s)\n";
        
        // Schedule expiry check
        if (!$isAuthenticated) {
            $this->scheduleExpiry($conn, $requestedSubdomain, $expiry);
        }
    }
    
    /**
     * Handle HTTP request forwarding response from client
     */
    protected function handleTunnelResponse(ConnectionInterface $from, array $data): void
    {
        $requestId = $data['request_id'] ?? null;
        if (!$requestId || !isset($this->pendingRequests[$requestId])) {
            return;
        }
        
        $deferred = $this->pendingRequests[$requestId];
        unset($this->pendingRequests[$requestId]);
        
        // Resolve the pending request with response data
        $deferred->resolve($data);
    }
    
    /**
     * Forward an HTTP request through the tunnel
     */
    public function forwardRequest(string $subdomain, array $request): PromiseInterface
    {
        $deferred = new Deferred();
        
        if (!isset($this->tunnels[$subdomain])) {
            $deferred->reject(new \Exception('Tunnel not found'));
            return $deferred->promise();
        }
        
        $conn = $this->tunnels[$subdomain];
        $requestId = bin2hex(random_bytes(16));
        
        // Store pending request
        $this->pendingRequests[$requestId] = $deferred;
        
        // Send request to client
        $this->send($conn, [
            'type' => 'http_request',
            'request_id' => $requestId,
            'method' => $request['method'] ?? 'GET',
            'uri' => $request['uri'] ?? '/',
            'headers' => $request['headers'] ?? [],
            'body' => $request['body'] ?? ''
        ]);
        
        // Set timeout
        $timeout = Loop::addTimer(30.0, function() use ($requestId, $deferred) {
            if (isset($this->pendingRequests[$requestId])) {
                unset($this->pendingRequests[$requestId]);
                $deferred->reject(new \Exception('Request timeout'));
            }
        });
        
        // Clean up timeout on resolve/reject
        $deferred->promise()->then(
            function() use ($timeout) { Loop::cancelTimer($timeout); },
            function() use ($timeout) { Loop::cancelTimer($timeout); }
        );
        
        return $deferred->promise();
    }
    
    /**
     * Generate JWT token for tunnel session
     */
    protected function generateTunnelToken(string $subdomain, int $expiry, ?int $userId = null): string
    {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'sub' => $subdomain,
            'exp' => $expiry,
            'iat' => time(),
            'uid' => $userId,
            'jti' => bin2hex(random_bytes(16))
        ]));
        $signature = base64_encode(hash_hmac('sha256', "{$header}.{$payload}", $this->jwtSecret, true));
        
        return "{$header}.{$payload}.{$signature}";
    }
    
    /**
     * Validate a tunnel JWT token
     */
    public function validateTunnelToken(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return ['valid' => false, 'error' => 'Invalid token format'];
        }
        
        [$header, $payload, $signature] = $parts;
        
        $expectedSig = base64_encode(hash_hmac('sha256', "{$header}.{$payload}", $this->jwtSecret, true));
        if (!hash_equals($expectedSig, $signature)) {
            return ['valid' => false, 'error' => 'Invalid signature'];
        }
        
        $data = json_decode(base64_decode($payload), true);
        if (!$data) {
            return ['valid' => false, 'error' => 'Invalid payload'];
        }
        
        if (isset($data['exp']) && $data['exp'] < time()) {
            return ['valid' => false, 'error' => 'Token expired'];
        }
        
        return ['valid' => true, 'data' => $data];
    }
    
    /**
     * Validate user auth token (from ginto.ai registration)
     */
    protected function validateAuthToken(string $token): array
    {
        if (!$this->db) {
            return ['valid' => false];
        }
        
        try {
            // Check api_tokens table
            $tokenRecord = $this->db->get('api_tokens', ['user_id', 'expires_at'], [
                'token' => hash('sha256', $token),
                'revoked' => 0
            ]);
            
            if (!$tokenRecord) {
                return ['valid' => false];
            }
            
            if ($tokenRecord['expires_at'] && strtotime($tokenRecord['expires_at']) < time()) {
                return ['valid' => false];
            }
            
            return ['valid' => true, 'user_id' => (int)$tokenRecord['user_id']];
        } catch (\Exception $e) {
            return ['valid' => false];
        }
    }
    
    /**
     * Create DNS record and Caddy config for tunnel using Caddy API
     */
    protected function createTunnelConfig(string $subdomain, int $connectionId): array
    {
        $domain = "{$subdomain}.{$this->baseDomain}";
        $tunnelId = "tunnel-{$subdomain}";
        
        try {
            // Use Caddy API to add route (like SirTunnel)
            $caddyRoute = [
                '@id' => $tunnelId,
                'match' => [
                    ['host' => [$domain]]
                ],
                'handle' => [
                    [
                        'handler' => 'reverse_proxy',
                        'upstreams' => [
                            ['dial' => '127.0.0.1:8765']
                        ],
                        'headers' => [
                            'request' => [
                                'set' => [
                                    'X-Tunnel-Subdomain' => [$subdomain]
                                ]
                            ]
                        ]
                    ]
                ],
                'terminal' => true
            ];
            
            $json = json_encode($caddyRoute);
            $ch = curl_init('http://127.0.0.1:2019/config/apps/http/servers/srv0/routes');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                echo "[TunnelServer] Caddy route added for {$domain}\n";
                return ['success' => true, 'tunnel_id' => $tunnelId];
            } else {
                return ['success' => false, 'error' => "Caddy API error: HTTP {$httpCode} - {$response}"];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Remove tunnel config on disconnect/expiry using Caddy API
     */
    protected function removeTunnelConfig(string $subdomain): void
    {
        $tunnelId = "tunnel-{$subdomain}";
        
        try {
            // Use Caddy API to remove route by ID
            $ch = curl_init("http://127.0.0.1:2019/id/{$tunnelId}");
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                echo "[TunnelServer] Caddy route removed for {$subdomain}.{$this->baseDomain}\n";
            } else {
                error_log("[TunnelServer] Failed to remove Caddy route for {$subdomain}: HTTP {$httpCode}");
            }
        } catch (\Exception $e) {
            error_log("[TunnelServer] Failed to remove config for {$subdomain}: " . $e->getMessage());
        }
    }
    
    /**
     * Schedule tunnel expiry
     */
    protected function scheduleExpiry(ConnectionInterface $conn, string $subdomain, int $expiry): void
    {
        // This would be handled by the event loop in practice
        // For now, the expiry is checked on each message
    }
    
    /**
     * Check and handle expired tunnels
     */
    public function checkExpiredTunnels(): void
    {
        $now = time();
        $closedCount = 0;
        
        foreach ($this->tokens as $connId => $tokenData) {
            if (!$tokenData['authenticated'] && $tokenData['expiry'] < $now) {
                $subdomain = $tokenData['subdomain'];
                
                // Find and close connection
                foreach ($this->clients as $conn) {
                    if ($conn->resourceId === $connId) {
                        echo "[TunnelServer] Expiring tunnel: {$subdomain}.{$this->baseDomain}\n";
                        $this->send($conn, [
                            'type' => 'expired',
                            'message' => 'Tunnel expired. Register at https://ginto.ai/register for non-expiring tunnels.'
                        ]);
                        $conn->close();
                        $closedCount++;
                        break;
                    }
                }
            }
        }
        
        if ($closedCount > 0) {
            echo "[TunnelServer] Expired {$closedCount} tunnel(s)\n";
        }
    }
    
    /**
     * Send ping to all connected clients and check for dead connections
     */
    public function pingClients(): void
    {
        $now = time();
        $deadConnections = [];
        
        foreach ($this->clients as $conn) {
            $lastPong = $this->lastPong[$conn->resourceId] ?? 0;
            
            // If no pong received within timeout, mark as dead
            if ($now - $lastPong > $this->pingTimeout) {
                $deadConnections[] = $conn;
                continue;
            }
            
            // Send ping
            $this->send($conn, ['type' => 'ping', 'timestamp' => $now]);
        }
        
        // Close dead connections
        foreach ($deadConnections as $conn) {
            $subdomain = $this->tokens[$conn->resourceId]['subdomain'] ?? 'unknown';
            echo "[TunnelServer] Connection timeout for tunnel: {$subdomain} (no pong for {$this->pingTimeout}s)\n";
            $conn->close();
        }
    }
    
    /**
     * Get statistics for monitoring
     */
    public function getStats(): array
    {
        return [
            'active_tunnels' => count($this->tunnels),
            'connected_clients' => count($this->clients),
            'pending_requests' => count($this->pendingRequests),
            'tunnels' => array_keys($this->tunnels)
        ];
    }
    
    /**
     * Get active tunnel by subdomain
     */
    public function getTunnel(string $subdomain): ?ConnectionInterface
    {
        return $this->tunnels[$subdomain] ?? null;
    }
    
    /**
     * Send message to connection
     */
    protected function send(ConnectionInterface $conn, array $data): void
    {
        $conn->send(json_encode($data));
    }
    
    /**
     * Send error message
     */
    protected function sendError(ConnectionInterface $conn, string $message): void
    {
        $this->send($conn, ['type' => 'error', 'message' => $message]);
    }
}

class TunnelHttpHandler implements HttpServerInterface
{
    private TunnelServer $tunnelServer;
    
    public function __construct(TunnelServer $tunnelServer)
    {
        $this->tunnelServer = $tunnelServer;
    }
    
    public function onOpen(ConnectionInterface $conn, RequestInterface $request = null): void
    {
        if (!$request) {
            $conn->close();
            return;
        }
        
        // Check if this is a tunnel request
        $subdomain = $request->getHeaderLine('X-Tunnel-Subdomain');
        if (!$subdomain) {
            $this->sendErrorResponse($conn, 400, 'Missing X-Tunnel-Subdomain header');
            return;
        }
        
        echo "[TunnelHttpHandler] Request for {$subdomain}.{$this->tunnelServer->baseDomain}: {$request->getMethod()} {$request->getUri()}\n";
        
        // Prepare request data
        $requestData = [
            'method' => $request->getMethod(),
            'uri' => $request->getUri()->getPath() . ($request->getUri()->getQuery() ? '?' . $request->getUri()->getQuery() : ''),
            'headers' => [],
            'body' => $request->getBody()->getContents()
        ];
        
        // Copy headers (excluding hop-by-hop headers)
        foreach ($request->getHeaders() as $name => $values) {
            if (!in_array(strtolower($name), ['connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization', 'te', 'trailers', 'transfer-encoding', 'upgrade'])) {
                $requestData['headers'][$name] = implode(', ', $values);
            }
        }
        
        // Forward request through tunnel
        $this->tunnelServer->forwardRequest($subdomain, $requestData)->then(
            function($response) use ($conn) {
                $this->sendResponse($conn, $response);
            },
            function($error) use ($conn, $subdomain) {
                error_log("[TunnelHttpHandler] Error forwarding request for {$subdomain}: " . $error->getMessage());
                $this->sendErrorResponse($conn, 502, 'Bad Gateway: ' . $error->getMessage());
            }
        );
    }
    
    public function onClose(ConnectionInterface $conn): void
    {
        // HTTP connections are short-lived, nothing to clean up
    }
    
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        // HTTP handler doesn't receive messages
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        error_log("[TunnelHttpHandler] Error: " . $e->getMessage());
        $this->sendErrorResponse($conn, 500, 'Internal Server Error');
    }
    
    private function sendResponse(ConnectionInterface $conn, array $response): void
    {
        $status = $response['status'] ?? 200;
        $headers = $response['headers'] ?? [];
        $body = $response['body'] ?? '';
        
        // Build HTTP response
        $httpResponse = "HTTP/1.1 {$status} " . $this->getStatusText($status) . "\r\n";
        
        foreach ($headers as $name => $value) {
            $httpResponse .= "{$name}: {$value}\r\n";
        }
        
        $httpResponse .= "Content-Length: " . strlen($body) . "\r\n";
        $httpResponse .= "\r\n";
        $httpResponse .= $body;
        
        $conn->send($httpResponse);
        $conn->close();
    }
    
    private function sendErrorResponse(ConnectionInterface $conn, int $status, string $message): void
    {
        $body = json_encode(['error' => $message]);
        $response = "HTTP/1.1 {$status} " . $this->getStatusText($status) . "\r\n";
        $response .= "Content-Type: application/json\r\n";
        $response .= "Content-Length: " . strlen($body) . "\r\n";
        $response .= "\r\n";
        $response .= $body;
        
        $conn->send($response);
        $conn->close();
    }
    
    private function getStatusText(int $status): string
    {
        $statusTexts = [
            200 => 'OK',
            400 => 'Bad Request',
            404 => 'Not Found',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            504 => 'Gateway Timeout'
        ];
        
        return $statusTexts[$status] ?? 'Unknown';
    }
}

class TunnelRouter implements HttpServerInterface
{
    private TunnelServer $wsServer;
    private TunnelHttpHandler $httpHandler;
    private WsServer $wsHandler;
    private array $wsConnections = [];
    
    public function __construct(TunnelServer $wsServer, TunnelHttpHandler $httpHandler)
    {
        $this->wsServer = $wsServer;
        $this->httpHandler = $httpHandler;
        $this->wsHandler = new WsServer($wsServer);
    }
    
    public function onOpen(ConnectionInterface $conn, RequestInterface $request = null): void
    {
        if (!$request) {
            $conn->close();
            return;
        }
        
        $path = $request->getUri()->getPath();
        
        // Check for WebSocket upgrade on /tunnel/ws path
        $upgradeHeader = $request->getHeaderLine('Upgrade');
        if ($path === '/tunnel/ws' && strtolower($upgradeHeader) === 'websocket') {
            // Mark this as a WebSocket connection
            $this->wsConnections[$conn->resourceId] = true;
            // Delegate to WsServer which handles the upgrade
            $this->wsHandler->onOpen($conn, $request);
            return;
        }
        
        // All other requests go to HTTP handler
        $this->httpHandler->onOpen($conn, $request);
    }
    
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        // Only WebSocket connections receive messages
        if (isset($this->wsConnections[$from->resourceId])) {
            $this->wsHandler->onMessage($from, $msg);
        }
    }
    
    public function onClose(ConnectionInterface $conn): void
    {
        if (isset($this->wsConnections[$conn->resourceId])) {
            unset($this->wsConnections[$conn->resourceId]);
            $this->wsHandler->onClose($conn);
        } else {
            $this->httpHandler->onClose($conn);
        }
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        if (isset($this->wsConnections[$conn->resourceId])) {
            $this->wsHandler->onError($conn, $e);
        } else {
            $this->httpHandler->onError($conn, $e);
        }
    }
}

// ============================================
// Main execution - Start the tunnel server
// ============================================
if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    $port = (int)($_ENV['TUNNEL_PORT'] ?? 8765);
    $host = $_ENV['TUNNEL_HOST'] ?? '0.0.0.0';
    
    echo "===========================================\n";
    echo " Ginto Tunnel Server\n";
    echo "===========================================\n";
    echo "Starting on http://{$host}:{$port}\n";
    echo "WebSocket endpoint: ws://{$host}:{$port}/tunnel/ws\n";
    
    try {
        // Create the event loop
        $loop = Loop::get();
        
        $tunnelServer = new TunnelServer();
        $httpHandler = new TunnelHttpHandler($tunnelServer);
        $router = new TunnelRouter($tunnelServer, $httpHandler);
        
        $server = new HttpServer($router);
        
        // Create socket with the loop (3rd argument in new SocketServer)
        $socket = new \React\Socket\SocketServer("{$host}:{$port}", [], $loop);
        $app = new \Ratchet\Server\IoServer($server, $socket, $loop);
        
        // Add periodic timer for ping/pong health checks (every 30 seconds)
        $loop->addPeriodicTimer(30, function() use ($tunnelServer) {
            $tunnelServer->pingClients();
        });
        
        // Add periodic timer to check for expired tunnels (every 10 seconds)
        $loop->addPeriodicTimer(10, function() use ($tunnelServer) {
            $tunnelServer->checkExpiredTunnels();
        });
        
        // Add periodic timer to log stats (every 60 seconds)
        $loop->addPeriodicTimer(60, function() use ($tunnelServer) {
            $stats = $tunnelServer->getStats();
            echo "[TunnelServer] Stats: " . json_encode($stats) . "\n";
        });
        
        echo "Server started. Waiting for connections...\n";
        echo "Heartbeat: every 30s | Expiry check: every 10s\n";
        $loop->run();
    } catch (\Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
}