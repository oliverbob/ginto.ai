<?php
/**
 * Ginto FRP Tunnel Controller
 * 
 * Manages FRP-based tunnel tokens and serves client files.
 * FRP (Fast Reverse Proxy) provides high-performance tunneling.
 */

declare(strict_types=1);

namespace Ginto\Controllers;

class FrpController
{
    protected ?\Medoo\Medoo $db;
    protected string $frpDir;
    protected string $baseDomain = 'ginto.ai';
    
    public function __construct(?\Medoo\Medoo $db = null)
    {
        $this->db = $db;
        $this->frpDir = dirname(__DIR__, 2) . '/tools/tunnel/frp';
    }
    
    /**
     * Serve the FRP install script
     * GET /frp/install.sh
     */
    public function serveInstaller(): void
    {
        $file = $this->frpDir . '/install.sh';
        if (!file_exists($file)) {
            http_response_code(404);
            echo "Install script not found";
            return;
        }
        
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: inline; filename="install.sh"');
        readfile($file);
    }
    
    /**
     * Serve the ginto-frpc client helper script
     * GET /frp/ginto-frpc.sh
     */
    public function serveClient(): void
    {
        $file = $this->frpDir . '/ginto-frpc.sh';
        if (!file_exists($file)) {
            http_response_code(404);
            echo "Client script not found";
            return;
        }
        
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: inline; filename="ginto-frpc.sh"');
        readfile($file);
    }
    
    /**
     * Serve the example frpc config
     * GET /frp/frpc.toml
     */
    public function serveConfig(): void
    {
        $file = $this->frpDir . '/frpc.toml.example';
        if (!file_exists($file)) {
            http_response_code(404);
            echo "Config template not found";
            return;
        }
        
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: inline; filename="frpc.toml"');
        readfile($file);
    }
    
    /**
     * Generate an FRP auth token for a user
     * POST /api/frp/token
     * 
     * @requires Authentication
     */
    public function generateToken(): void
    {
        header('Content-Type: application/json');
        
        // Check authentication
        $userId = $this->getAuthenticatedUserId();
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            return;
        }
        
        if (!$this->db) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database not available']);
            return;
        }
        
        try {
            // Check if user already has an FRP token
            $existingToken = $this->db->get('frp_tokens', ['id', 'token', 'created_at'], [
                'user_id' => $userId,
                'revoked' => 0
            ]);
            
            if ($existingToken) {
                echo json_encode([
                    'success' => true,
                    'token' => $existingToken['token'],
                    'created_at' => $existingToken['created_at'],
                    'message' => 'Using existing token'
                ]);
                return;
            }
            
            // Generate new token
            $token = $this->generateSecureToken();
            
            $this->db->insert('frp_tokens', [
                'user_id' => $userId,
                'token' => $token,
                'created_at' => date('Y-m-d H:i:s'),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+1 year')),
                'revoked' => 0
            ]);
            
            echo json_encode([
                'success' => true,
                'token' => $token,
                'expires_in' => 365 * 24 * 3600,
                'server' => $this->baseDomain,
                'port' => 7000,
                'config' => $this->generateUserConfig($token)
            ]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to generate token: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Revoke an FRP token
     * DELETE /api/frp/token
     */
    public function revokeToken(): void
    {
        header('Content-Type: application/json');
        
        $userId = $this->getAuthenticatedUserId();
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            return;
        }
        
        if (!$this->db) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database not available']);
            return;
        }
        
        try {
            $this->db->update('frp_tokens', [
                'revoked' => 1,
                'revoked_at' => date('Y-m-d H:i:s')
            ], [
                'user_id' => $userId,
                'revoked' => 0
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Token revoked']);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to revoke token']);
        }
    }
    
    /**
     * Get current user's FRP token info
     * GET /api/frp/token
     */
    public function getTokenInfo(): void
    {
        header('Content-Type: application/json');
        
        $userId = $this->getAuthenticatedUserId();
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            return;
        }
        
        if (!$this->db) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database not available']);
            return;
        }
        
        try {
            $token = $this->db->get('frp_tokens', '*', [
                'user_id' => $userId,
                'revoked' => 0
            ]);
            
            if (!$token) {
                echo json_encode([
                    'success' => true,
                    'has_token' => false
                ]);
                return;
            }
            
            echo json_encode([
                'success' => true,
                'has_token' => true,
                'token' => $token['token'],
                'created_at' => $token['created_at'],
                'expires_at' => $token['expires_at'],
                'server' => $this->baseDomain,
                'port' => 7000
            ]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to get token info']);
        }
    }
    
    /**
     * List active tunnels for user
     * GET /api/frp/tunnels
     */
    public function listTunnels(): void
    {
        header('Content-Type: application/json');
        
        $userId = $this->getAuthenticatedUserId();
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            return;
        }
        
        // Query frps dashboard API for active tunnels
        // This would require frps to be running with dashboard enabled
        $dashboardUrl = 'http://127.0.0.1:7500/api/proxy/http';
        
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'header' => 'Authorization: Basic ' . base64_encode('admin:' . ($_ENV['FRP_DASHBOARD_PWD'] ?? ''))
                ]
            ]);
            
            $response = @file_get_contents($dashboardUrl, false, $context);
            
            if ($response === false) {
                echo json_encode([
                    'success' => true,
                    'tunnels' => [],
                    'message' => 'FRP server not available or no active tunnels'
                ]);
                return;
            }
            
            $proxies = json_decode($response, true);
            
            // Filter by user token if possible
            echo json_encode([
                'success' => true,
                'tunnels' => $proxies['proxies'] ?? []
            ]);
            
        } catch (\Exception $e) {
            echo json_encode([
                'success' => true,
                'tunnels' => [],
                'error' => 'Could not query tunnel status'
            ]);
        }
    }
    
    /**
     * Get FRP connection info (public)
     * GET /api/frp/info
     */
    public function getConnectionInfo(): void
    {
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => true,
            'server' => $this->baseDomain,
            'port' => 7000,
            'protocols' => ['http', 'https', 'tcp', 'udp', 'stcp'],
            'subdomain_host' => $this->baseDomain,
            'docs_url' => "https://{$this->baseDomain}/docs/tunnel",
            'install_command' => "curl -sSL https://{$this->baseDomain}/frp/install.sh | bash"
        ]);
    }
    
    /**
     * Validate an FRP token (used by frps server plugin)
     * POST /api/frp/validate
     */
    public function validateToken(): void
    {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['token'] ?? '';
        
        if (empty($token)) {
            echo json_encode(['valid' => false, 'error' => 'Token required']);
            return;
        }
        
        if (!$this->db) {
            // If no DB, accept all tokens (fallback to shared secret mode)
            $sharedToken = $_ENV['FRP_AUTH_TOKEN'] ?? '';
            echo json_encode(['valid' => $token === $sharedToken]);
            return;
        }
        
        try {
            $tokenRecord = $this->db->get('frp_tokens', ['user_id', 'expires_at'], [
                'token' => $token,
                'revoked' => 0
            ]);
            
            if (!$tokenRecord) {
                echo json_encode(['valid' => false, 'error' => 'Token not found']);
                return;
            }
            
            if ($tokenRecord['expires_at'] && strtotime($tokenRecord['expires_at']) < time()) {
                echo json_encode(['valid' => false, 'error' => 'Token expired']);
                return;
            }
            
            echo json_encode([
                'valid' => true,
                'user_id' => $tokenRecord['user_id']
            ]);
            
        } catch (\Exception $e) {
            echo json_encode(['valid' => false, 'error' => 'Validation failed']);
        }
    }
    
    /**
     * Generate a secure random token
     */
    protected function generateSecureToken(): string
    {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Get authenticated user ID from session/token
     */
    protected function getAuthenticatedUserId(): ?int
    {
        // Check session first
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['user']['id'])) {
            return (int)$_SESSION['user']['id'];
        }
        
        // Check Bearer token
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            $bearerToken = $matches[1];
            
            if ($this->db) {
                $tokenRecord = $this->db->get('api_tokens', ['user_id'], [
                    'token' => hash('sha256', $bearerToken),
                    'revoked' => 0
                ]);
                
                if ($tokenRecord) {
                    return (int)$tokenRecord['user_id'];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Generate a user-specific frpc config
     */
    protected function generateUserConfig(string $token): string
    {
        return <<<TOML
# Ginto FRP Client Configuration
# Generated for your account

serverAddr = "{$this->baseDomain}"
serverPort = 7000

auth.method = "token"
auth.token = "{$token}"

transport.tls.enable = true

# Example: Expose local port 8088 as yourname.ginto.ai
[[proxies]]
name = "my-app"
type = "http"
localPort = 8088
subdomain = "yourname"  # Change this!

# Optional: per-subdomain operator key (server will deny if it doesn't match)
# Set/generate it via POST /api/frp/subdomain/key, then put it here:
# metas = { ginto_key = "YOUR_TUNNEL_KEY" }
TOML;
    }

    private const TUNNEL_REGISTRY_FILE = '/var/lib/ginto/tunnel-registry.json';
    private const TUNNEL_REGISTRY_FALLBACK_FILE = '/tmp/ginto-tunnel-registry.json';

    private function resolveTunnelDataReadPath(string $primary, string $fallback): ?string
    {
        if (file_exists($primary)) {
            return $primary;
        }
        if (file_exists($fallback)) {
            return $fallback;
        }
        return null;
    }

    private function resolveTunnelDataWritePath(string $primary, string $fallback): ?string
    {
        $primaryDir = dirname($primary);
        if (!is_dir($primaryDir)) {
            @mkdir($primaryDir, 0755, true);
        }
        if (is_dir($primaryDir) && is_writable($primaryDir)) {
            return $primary;
        }

        $fallbackDir = dirname($fallback);
        if (!is_dir($fallbackDir)) {
            @mkdir($fallbackDir, 0755, true);
        }
        if (is_dir($fallbackDir) && is_writable($fallbackDir)) {
            return $fallback;
        }

        return null;
    }

    private function getTunnelRegistry(): array
    {
        $path = $this->resolveTunnelDataReadPath(self::TUNNEL_REGISTRY_FILE, self::TUNNEL_REGISTRY_FALLBACK_FILE);
        if ($path === null) {
            return [];
        }
        $data = json_decode((string)@file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    private function saveTunnelRegistry(array $registry): bool
    {
        $path = $this->resolveTunnelDataWritePath(self::TUNNEL_REGISTRY_FILE, self::TUNNEL_REGISTRY_FALLBACK_FILE);
        if ($path === null) {
            return false;
        }
        return @file_put_contents($path, json_encode($registry, JSON_PRETTY_PRINT)) !== false;
    }

    /**
     * Set or generate a per-subdomain operator key.
     * POST /api/frp/subdomain/key
     * Body JSON: {"subdomain":"az","access_key":"..."} OR {"subdomain":"az","generate":true} OR {"subdomain":"az","enabled":false}
     */
    public function setSubdomainKey(): void
    {
        header('Content-Type: application/json');

        $userId = $this->getAuthenticatedUserId();
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            return;
        }

        $raw = json_decode((string)file_get_contents('php://input'), true);
        $input = is_array($raw) ? $raw : (is_array($_POST) ? $_POST : []);

        $subdomain = strtolower(trim((string)($input['subdomain'] ?? '')));
        $subdomain = preg_replace('/[^a-z0-9\-]/', '', $subdomain);

        if ($subdomain === '' || !preg_match('/^[a-z0-9](?:[a-z0-9-]{0,30}[a-z0-9])?$/', $subdomain)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid subdomain']);
            return;
        }

        $reserved = ['www', 'api', 'admin', 'mail', 'ftp', 'ssh', 'tunnel', 'app', 'dev', 'test', 'staging', 'ginto', 'ns1', 'ns2', 'mx'];
        if (in_array($subdomain, $reserved, true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Reserved subdomain']);
            return;
        }

        $enabled = $input['enabled'] ?? null;
        $generate = !empty($input['generate']);
        $providedKey = trim((string)($input['access_key'] ?? ''));

        if ($enabled === null) {
            $enabled = true;
        }
        $enabled = (bool)$enabled;

        $registry = $this->getTunnelRegistry();
        $entry = (isset($registry[$subdomain]) && is_array($registry[$subdomain])) ? $registry[$subdomain] : [];

        $owner = (int)($entry['owner_user_id'] ?? 0);
        if ($owner !== 0 && $owner !== (int)$userId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Subdomain is owned by another user']);
            return;
        }

        $plainKey = null;
        if ($enabled) {
            if ($generate || $providedKey === '') {
                $plainKey = bin2hex(random_bytes(24));
                $providedKey = $plainKey;
            }
            if ($providedKey === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing access_key']);
                return;
            }
        }

        $entry['owner_user_id'] = (int)$userId;
        $entry['access_key_enabled'] = $enabled ? 1 : 0;
        if ($enabled) {
            $entry['access_key_hash'] = hash('sha256', $providedKey);
        } else {
            $entry['access_key_hash'] = '';
        }
        $entry['updated_at'] = time();

        $registry[$subdomain] = $entry;
        if (!$this->saveTunnelRegistry($registry)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save registry']);
            return;
        }

        $out = [
            'success' => true,
            'subdomain' => $subdomain,
            'enabled' => $enabled,
        ];
        if ($plainKey !== null) {
            $out['access_key'] = $plainKey;
        }
        echo json_encode($out);
    }
}
