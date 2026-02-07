<?php
/**
 * Ginto Tunnel HTTP Handler
 * 
 * This handles incoming HTTP requests to tunnel subdomains
 * and forwards them through the WebSocket tunnel to the client.
 */

declare(strict_types=1);

namespace Ginto\Controllers;

class TunnelController
{
    protected ?\Medoo\Medoo $db;
    
    public function __construct(?\Medoo\Medoo $db = null)
    {
        $this->db = $db;
    }
    
    /**
     * Handle incoming HTTP request to a tunnel subdomain
     * This is called by Caddy via reverse proxy
     */
    public function handleRequest(): void
    {
        $subdomain = $_SERVER['HTTP_X_TUNNEL_SUBDOMAIN'] ?? null;
        
        if (!$subdomain) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing tunnel subdomain header']);
            return;
        }
        
        // Connect to tunnel server and forward request
        $tunnelWsPort = $_ENV['TUNNEL_WS_PORT'] ?? 9012;
        
        // For now, return a simple response - full implementation would
        // communicate with the tunnel server to forward the request
        http_response_code(502);
        echo json_encode([
            'error' => 'Tunnel not connected',
            'subdomain' => $subdomain,
            'message' => 'The tunnel client has disconnected. Please reconnect.'
        ]);
    }
    
    /**
     * API: Request a new tunnel
     * POST /api/tunnel/request
     * 
     * Uses FRP for high-performance tunneling with server-side expiry
     */
    public function requestTunnel(): void
    {
        header('Content-Type: application/json');
        
        // Clean up any expired tunnels first
        $this->cleanupExpiredTunnels();
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $subdomain = $input['subdomain'] ?? null;
            $localPort = $input['port'] ?? 8088;
            
            // Determine tunnel duration based on user status
            // Default: 15 minutes for guests
            // Registered users: 1 hour
            $expiresIn = 900; // 15 minutes default for guests
            $creditUsed = false;
            
            if (!empty($_SESSION['user_id']) && $this->db) {
                // Registered user gets 1 hour
                $expiresIn = 3600;
            }
            
            // Validate subdomain (7+ hex chars like git commits, or alphanumeric with hyphens)
            if (!$subdomain || !preg_match('/^[a-z0-9][a-z0-9-]{0,30}[a-z0-9]$|^[a-f0-9]{7,}$/', $subdomain)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid subdomain format. Use lowercase alphanumeric characters and hyphens.'
                ]);
                return;
            }
            
            // Check reserved
            $reserved = ['www', 'api', 'admin', 'mail', 'ftp', 'ssh', 'oi', 'tunnel', 'app', 'dev', 'test', 'staging', 'ginto'];
            if (in_array($subdomain, $reserved)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'This subdomain is reserved.'
                ]);
                return;
            }
            
            // Use FRP (Fast Reverse Proxy) for high-performance tunneling
            $result = $this->startTunnelClient($subdomain, $localPort);
            
            if (!$result['success']) {
                echo json_encode($result);
                return;
            }
            
            // Store tunnel expiry info
            $expiryTime = time() + $expiresIn;
            $this->storeTunnelExpiry($subdomain, $result['pid'] ?? 0, $expiryTime);
            
            // Register tunnel with server for unified tracking
            $this->registerTunnelWithServer($subdomain, $expiresIn);
            
            echo json_encode([
                'success' => true,
                'subdomain' => $subdomain,
                'url' => "https://{$subdomain}.ginto.ai/",
                'expires_in' => $expiresIn,
                'expires_at' => $expiryTime,
                'mode' => 'frp',
                'pid' => $result['pid'] ?? null,
                'credit_used' => $creditUsed
            ]);
            
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * Register tunnel with the central server for unified tracking
     */
    protected function registerTunnelWithServer(string $subdomain, int $expiresIn): void
    {
        try {
            $data = json_encode([
                'subdomain' => $subdomain,
                'expires_in' => $expiresIn,
                'client_ip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            
            $ch = curl_init('https://ginto.ai/admin/hosting/tunnels/register');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false // Local/dev environments
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $e) {
            // Non-critical, log and continue
            error_log("Failed to register tunnel with server: " . $e->getMessage());
        }
    }
    
    /**
     * Start SSH tunnel using gtunnel.py (SirTunnel pattern)
     * This auto-generates SSH keys if they don't exist
     */
    protected function startSshTunnel(string $subdomain, int $localPort): array
    {
        $projectRoot = dirname(dirname(__DIR__));
        $gtunnelScript = "{$projectRoot}/bin/gtunnel.py";
        
        if (!file_exists($gtunnelScript)) {
            return ['success' => false, 'error' => 'gtunnel.py not found'];
        }
        
        // Get user home directory
        $homeDir = $_SERVER['HOME'] ?? getenv('HOME') ?: posix_getpwuid(posix_getuid())['dir'];
        $sshDir = "{$homeDir}/.ssh";
        $privateKey = "{$sshDir}/id_ed25519";
        $publicKey = "{$sshDir}/id_ed25519.pub";
        $knownHosts = "{$sshDir}/known_hosts";
        $tunnelServer = 'ginto.ai';
        $tunnelUser = 'oliverbob';
        
        // Ensure .ssh directory exists
        if (!is_dir($sshDir)) {
            mkdir($sshDir, 0700, true);
        }
        
        // Generate SSH keys if they don't exist
        if (!file_exists($privateKey)) {
            $keygenCmd = "ssh-keygen -t ed25519 -N '' -f {$privateKey} -C 'ginto-tunnel' 2>&1";
            exec($keygenCmd, $keygenOutput, $keygenCode);
            
            if ($keygenCode !== 0 || !file_exists($privateKey)) {
                return [
                    'success' => false, 
                    'error' => 'Failed to generate SSH key: ' . implode("\n", $keygenOutput)
                ];
            }
            
            // Read the public key to show user (they need to add it to server)
            $pubKeyContent = trim(file_get_contents($publicKey));
            
            // For now, we'll assume the key needs to be added manually
            // In a production setup, you'd have an API to register keys
            return [
                'success' => false,
                'error' => 'SSH key generated. Please add this key to the server and try again.',
                'public_key' => $pubKeyContent,
                'needs_key_setup' => true
            ];
        }
        
        // Add server to known_hosts if not present
        if (!file_exists($knownHosts) || strpos(file_get_contents($knownHosts), $tunnelServer) === false) {
            exec("ssh-keyscan -H {$tunnelServer} >> {$knownHosts} 2>/dev/null");
        }
        
        // Test SSH connectivity first
        $testCmd = "ssh -o BatchMode=yes -o ConnectTimeout=5 -o StrictHostKeyChecking=accept-new {$tunnelUser}@{$tunnelServer} 'echo OK' 2>&1";
        exec($testCmd, $testOutput, $testCode);
        
        if ($testCode !== 0) {
            $pubKeyContent = file_exists($publicKey) ? trim(file_get_contents($publicKey)) : null;
            return [
                'success' => false,
                'error' => 'SSH connection failed. Your key may not be authorized.',
                'public_key' => $pubKeyContent,
                'needs_key_setup' => true,
                'ssh_output' => implode("\n", $testOutput)
            ];
        }
        
        // Kill any existing tunnel for this subdomain
        exec("pkill -f 'gtunnel.py.*{$subdomain}' 2>/dev/null");
        
        $logFile = "/tmp/gtunnel-{$subdomain}.log";
        $pidFile = "/tmp/gtunnel-{$subdomain}.pid";
        
        // Clear old log
        if (file_exists($logFile)) {
            unlink($logFile);
        }
        
        // Start gtunnel in client mode (creates SSH tunnel)
        $host = "{$subdomain}.ginto.ai";
        $cmd = "nohup python3 {$gtunnelScript} --expose {$host} {$localPort} --server {$tunnelServer} --user {$tunnelUser} > {$logFile} 2>&1 & echo \$!";
        
        $pid = trim(shell_exec($cmd));
        
        if (empty($pid) || !is_numeric($pid)) {
            return ['success' => false, 'error' => 'Failed to start SSH tunnel process'];
        }
        
        file_put_contents($pidFile, $pid);
        
        // Wait for connection
        $maxWait = 5;
        $connected = false;
        for ($i = 0; $i < $maxWait; $i++) {
            usleep(500000); // 0.5 seconds
            
            // Check if process is still running
            exec("ps -p {$pid}", $psOutput, $psCode);
            if ($psCode !== 0) {
                $log = file_exists($logFile) ? trim(file_get_contents($logFile)) : 'No log available';
                return ['success' => false, 'error' => 'SSH tunnel failed: ' . $log];
            }
            
            // Check log for success
            if (file_exists($logFile)) {
                $log = file_get_contents($logFile);
                if (strpos($log, 'Tunnel Active') !== false) {
                    $connected = true;
                    break;
                }
            }
        }
        
        return ['success' => true, 'pid' => (int)$pid, 'connected' => $connected];
    }
    
    /**
     * Start FRP tunnel client process to connect to ginto.ai
     * Uses frpc (Fast Reverse Proxy) for high-performance tunneling
     */
    protected function startTunnelClient(string $subdomain, int $localPort): array
    {
        $homeDir = $_SERVER['HOME'] ?? getenv('HOME') ?: '/tmp';
        $frpDir = "{$homeDir}/.ginto-frp";
        $frpcBinary = "{$frpDir}/frpc";
        $configFile = "{$frpDir}/frpc-{$subdomain}.toml";
        $logFile = "/tmp/frpc-{$subdomain}.log";
        $pidFile = "/tmp/frpc-{$subdomain}.pid";
        
        // FRP server details
        $frpServer = 'ginto.ai';
        $frpPort = 7000;
        $frpToken = '0868d7a0943085871e506e79c8589bd1d80fbd9852b441165237deea6e16955a';
        
        // Kill existing tunnel for this subdomain
        if (file_exists($pidFile)) {
            $oldPid = trim(file_get_contents($pidFile));
            if ($oldPid && is_numeric($oldPid)) {
                exec("kill {$oldPid} 2>/dev/null");
            }
            unlink($pidFile);
        }
        exec("pkill -f 'frpc.*{$subdomain}' 2>/dev/null");
        usleep(100000); // 100ms
        
        // Ensure frpc binary exists
        if (!file_exists($frpcBinary)) {
            // Download frpc if not exists
            if (!is_dir($frpDir)) {
                mkdir($frpDir, 0755, true);
            }
            
            $arch = php_uname('m');
            $frpArch = match($arch) {
                'x86_64', 'amd64' => 'amd64',
                'aarch64', 'arm64' => 'arm64',
                default => 'amd64'
            };
            
            $version = '0.66.0';
            $tarball = "frp_{$version}_linux_{$frpArch}.tar.gz";
            $downloadUrl = "https://github.com/fatedier/frp/releases/download/v{$version}/{$tarball}";
            
            // Download and extract
            $tmpFile = "/tmp/{$tarball}";
            exec("curl -sL '{$downloadUrl}' -o '{$tmpFile}' 2>&1", $dlOutput, $dlCode);
            
            if ($dlCode !== 0 || !file_exists($tmpFile)) {
                return ['success' => false, 'error' => 'Failed to download FRP client'];
            }
            
            exec("tar -xzf '{$tmpFile}' -C /tmp && cp /tmp/frp_{$version}_linux_{$frpArch}/frpc '{$frpcBinary}' && chmod +x '{$frpcBinary}' 2>&1", $extractOutput, $extractCode);
            exec("rm -rf '{$tmpFile}' /tmp/frp_{$version}_linux_{$frpArch}");
            
            if (!file_exists($frpcBinary)) {
                return ['success' => false, 'error' => 'Failed to extract FRP client'];
            }
        }
        
        // Create config file for this subdomain
        $config = <<<TOML
serverAddr = "{$frpServer}"
serverPort = {$frpPort}
auth.method = "token"
auth.token = "{$frpToken}"
log.to = "{$logFile}"
log.level = "info"

[[proxies]]
name = "{$subdomain}"
type = "http"
localPort = {$localPort}
subdomain = "{$subdomain}"
TOML;
        
        file_put_contents($configFile, $config);
        
        // Clear old log
        if (file_exists($logFile)) {
            unlink($logFile);
        }
        
        // Start frpc in background
        $cmd = "nohup {$frpcBinary} -c {$configFile} > {$logFile} 2>&1 & echo \$!";
        $pid = trim(shell_exec($cmd));
        
        if (empty($pid) || !is_numeric($pid)) {
            return ['success' => false, 'error' => 'Failed to start FRP tunnel process'];
        }
        
        file_put_contents($pidFile, $pid);
        
        // Wait for connection
        $maxWait = 5;
        $connected = false;
        for ($i = 0; $i < $maxWait; $i++) {
            usleep(500000); // 0.5 seconds
            
            // Check if process is still running
            exec("ps -p {$pid}", $psOutput, $psCode);
            $psOutput = []; // Reset for next iteration
            
            if ($psCode !== 0) {
                $log = file_exists($logFile) ? trim(file_get_contents($logFile)) : 'No log available';
                
                if (strpos($log, 'already exists') !== false || strpos($log, 'proxy name') !== false) {
                    return ['success' => false, 'error' => 'This subdomain is already in use. Please choose another.'];
                }
                if (strpos($log, 'auth failed') !== false) {
                    return ['success' => false, 'error' => 'Authentication failed'];
                }
                
                return ['success' => false, 'error' => 'FRP tunnel failed: ' . ($log ?: 'Unknown error')];
            }
            
            // Check log for successful connection
            if (file_exists($logFile)) {
                $log = file_get_contents($logFile);
                if (strpos($log, 'start proxy success') !== false || strpos($log, 'login to server success') !== false) {
                    $connected = true;
                    break;
                }
            }
        }
        
        return ['success' => true, 'pid' => (int)$pid, 'connected' => $connected, 'mode' => 'frp'];
    }
    
    /**
     * API: Get tunnel status
     * GET /api/tunnel/status
     */
    public function tunnelStatus(): void
    {
        header('Content-Type: application/json');
        
        $subdomain = $_GET['subdomain'] ?? null;
        
        if (!$subdomain) {
            echo json_encode(['success' => false, 'error' => 'Missing subdomain parameter']);
            return;
        }
        
        // Check if Caddy config exists for this subdomain
        $domain = "{$subdomain}.ginto.ai";
        $configFile = "/etc/caddy/sites-enabled/{$domain}.caddy";
        
        $active = file_exists($configFile);
        
        echo json_encode([
            'success' => true,
            'subdomain' => $subdomain,
            'active' => $active,
            'url' => $active ? "https://{$domain}/" : null
        ]);
    }
    
    /**
     * Get or create tunnel auth token for user
     */
    protected function getOrCreateTunnelToken(int $userId): ?string
    {
        if (!$this->db) {
            return null;
        }
        
        try {
            // Check for existing tunnel token
            $existing = $this->db->get('api_tokens', ['token', 'expires_at'], [
                'user_id' => $userId,
                'name' => 'tunnel_auth',
                'revoked' => 0
            ]);
            
            if ($existing && (!$existing['expires_at'] || strtotime($existing['expires_at']) > time())) {
                // Return existing valid token (the actual token, not hash)
                // Note: In production, store the plain token somewhere retrievable
                return null; // Can't retrieve hashed token
            }
            
            // Generate new token
            $plainToken = bin2hex(random_bytes(32));
            $hashedToken = hash('sha256', $plainToken);
            
            $this->db->insert('api_tokens', [
                'user_id' => $userId,
                'name' => 'tunnel_auth',
                'token' => $hashedToken,
                'expires_at' => null, // Never expires for registered users
                'revoked' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return $plainToken;
            
        } catch (\Exception $e) {
            error_log("Failed to create tunnel token: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * API: Disconnect a tunnel
     * POST /api/tunnel/disconnect
     */
    public function disconnectTunnel(): void
    {
        header('Content-Type: application/json');
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $subdomain = $input['subdomain'] ?? null;
            
            if (!$subdomain) {
                echo json_encode(['success' => false, 'error' => 'Missing subdomain parameter']);
                return;
            }
            
            // Kill the FRP tunnel client process
            $pidFile = "/tmp/frpc-{$subdomain}.pid";
            if (file_exists($pidFile)) {
                $pid = trim(file_get_contents($pidFile));
                if ($pid && is_numeric($pid)) {
                    exec("kill {$pid} 2>/dev/null");
                }
                unlink($pidFile);
            }
            
            // Also try to kill by pattern
            exec("pkill -f 'frpc.*{$subdomain}' 2>/dev/null");
            
            // Clean up config, log, and expiry files
            $homeDir = $_SERVER['HOME'] ?? getenv('HOME') ?: '/tmp';
            $configFile = "{$homeDir}/.ginto-frp/frpc-{$subdomain}.toml";
            $logFile = "/tmp/frpc-{$subdomain}.log";
            $expiryFile = "/tmp/frpc-{$subdomain}.expiry";
            
            @unlink($configFile);
            @unlink($logFile);
            @unlink($expiryFile);
            
            echo json_encode([
                'success' => true,
                'subdomain' => $subdomain,
                'message' => 'Tunnel disconnected'
            ]);
            
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Verify if a tunnel domain should be allowed for on-demand TLS
     * GET /api/tunnel/verify?domain=subdomain.ginto.ai
     * 
     * Called by Caddy's on_demand_tls to verify certificate requests
     * Return 200 to allow, 4xx to deny
     * 
     * IMPORTANT: Only approve subdomains that are actually in use to prevent
     * Let's Encrypt rate limiting and ACME challenge delays on random requests.
     */
    public function verifyTunnel(): void
    {
        $domain = $_GET['domain'] ?? '';
        
        // Must be a valid *.ginto.ai subdomain (2-32 chars)
        if (!preg_match('/^([a-z0-9](?:[a-z0-9-]{0,30}[a-z0-9])?)\.ginto\.ai$/', $domain, $matches)) {
            http_response_code(400);
            echo "Invalid domain format";
            return;
        }
        
        $subdomain = $matches[1];
        
        // Check reserved subdomains - deny certificate
        $reserved = ['www', 'api', 'admin', 'mail', 'ftp', 'ssh', 'tunnel', 'app', 'dev', 'test', 'staging', 'ginto', 'ns1', 'ns2', 'mx'];
        if (in_array($subdomain, $reserved)) {
            http_response_code(403);
            echo "Reserved subdomain";
            return;
        }
        
        // 1. Check if there's an active tunnel PID file
        $pidFile = "/tmp/tunnel-{$subdomain}.pid";
        if (file_exists($pidFile)) {
            http_response_code(200);
            echo "OK - active tunnel";
            return;
        }
        
        // 2. Check for static Caddy config in sites-enabled
        $staticConfig = "/etc/caddy/sites-enabled/{$domain}.caddy";
        if (file_exists($staticConfig)) {
            http_response_code(200);
            echo "OK - static config";
            return;
        }
        
        // 3. Check OpenWebUI pattern (owui-xxxxxx)
        if (preg_match('/^owui-[a-z0-9]{6}$/', $subdomain)) {
            http_response_code(200);
            echo "OK - OpenWebUI subdomain";
            return;
        }
        
        // 4. Check virtual_hosts database table for registered cloud domains
        try {
            if ($this->db && $this->db->has('virtual_hosts', [
                'domain' => $domain,
                'is_enabled' => 1
            ])) {
                http_response_code(200);
                echo "OK - registered domain";
                return;
            }
        } catch (\Exception $e) {
            // Database check failed, log but continue with denial
            error_log("TunnelController::verifyTunnel DB check failed: " . $e->getMessage());
        }
        
        // 5. Check for FRP client PID file (legacy pattern)
        $frpcPidFile = "/tmp/frpc-{$subdomain}.pid";
        if (file_exists($frpcPidFile)) {
            http_response_code(200);
            echo "OK - FRP tunnel";
            return;
        }
        
        // 6. Check FRP tunnel registry (tracks registered tunnels)
        $registryFile = '/var/lib/ginto/tunnel-registry.json';
        if (file_exists($registryFile)) {
            $registry = json_decode(file_get_contents($registryFile), true) ?: [];
            if (isset($registry[$subdomain])) {
                // Check if not expired
                $expiresAt = $registry[$subdomain]['expires_at'] ?? 0;
                if ($expiresAt > time()) {
                    http_response_code(200);
                    echo "OK - FRP registry";
                    return;
                }
            }
        }
        
        // Subdomain not in use - deny certificate request
        // This prevents ACME challenges for random/arbitrary subdomains
        http_response_code(404);
        echo "Subdomain not registered or in use";
    }
    
    /**
     * Store tunnel expiry information
     */
    protected function storeTunnelExpiry(string $subdomain, int $pid, int $expiryTime): void
    {
        $expiryFile = "/tmp/frpc-{$subdomain}.expiry";
        file_put_contents($expiryFile, json_encode([
            'subdomain' => $subdomain,
            'pid' => $pid,
            'created_at' => time(),
            'expires_at' => $expiryTime
        ]));
    }
    
    /**
     * Clean up expired tunnels (called on each tunnel request)
     */
    protected function cleanupExpiredTunnels(): void
    {
        $expiryFiles = glob('/tmp/frpc-*.expiry');
        $now = time();
        
        foreach ($expiryFiles as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (!$data) {
                @unlink($file);
                continue;
            }
            
            $expiresAt = $data['expires_at'] ?? 0;
            $subdomain = $data['subdomain'] ?? '';
            $pid = $data['pid'] ?? 0;
            
            // If expired, kill the tunnel
            if ($expiresAt > 0 && $now >= $expiresAt) {
                error_log("Tunnel {$subdomain} expired, cleaning up...");
                
                // Kill the process
                if ($pid > 0) {
                    exec("kill {$pid} 2>/dev/null");
                }
                exec("pkill -f 'frpc.*{$subdomain}' 2>/dev/null");
                
                // Clean up files
                $homeDir = $_SERVER['HOME'] ?? getenv('HOME') ?: '/tmp';
                @unlink("{$homeDir}/.ginto-frp/frpc-{$subdomain}.toml");
                @unlink("/tmp/frpc-{$subdomain}.pid");
                @unlink("/tmp/frpc-{$subdomain}.log");
                @unlink($file);
            }
        }
    }
    
    /**
     * API: Get tunnel status and remaining time
     * GET /api/tunnel/time?subdomain=xxx
     */
    public function tunnelTime(): void
    {
        header('Content-Type: application/json');
        
        $subdomain = $_GET['subdomain'] ?? null;
        
        if (!$subdomain) {
            echo json_encode(['success' => false, 'error' => 'Missing subdomain']);
            return;
        }
        
        // Clean up expired tunnels
        $this->cleanupExpiredTunnels();
        
        $expiryFile = "/tmp/frpc-{$subdomain}.expiry";
        
        if (!file_exists($expiryFile)) {
            echo json_encode([
                'success' => false,
                'active' => false,
                'error' => 'Tunnel not found or expired'
            ]);
            return;
        }
        
        $data = json_decode(file_get_contents($expiryFile), true);
        $now = time();
        $expiresAt = $data['expires_at'] ?? 0;
        $remaining = max(0, $expiresAt - $now);
        
        echo json_encode([
            'success' => true,
            'active' => $remaining > 0,
            'subdomain' => $subdomain,
            'expires_at' => $expiresAt,
            'remaining' => $remaining,
            'created_at' => $data['created_at'] ?? 0
        ]);
    }
}
