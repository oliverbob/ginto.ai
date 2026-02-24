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
    private string $lastRelayApprovalDetail = '';
    private const VISION_RELAY_SUBDOMAIN = 'vision';
    private const TUNNEL_REGISTRY_FILE = '/var/lib/ginto/tunnel-registry.json';
    private const TUNNEL_BLOCKLIST_FILE = '/var/lib/ginto/tunnel-blocklist.json';
    private const TUNNEL_REGISTRY_FALLBACK_FILE = '/tmp/ginto-tunnel-registry.json';
    private const TUNNEL_BLOCKLIST_FALLBACK_FILE = '/tmp/ginto-tunnel-blocklist.json';
    private const TUNNEL_RELAY_CHECKS_FILE = '/var/lib/ginto/tunnel-relay-checks.json';
    private const APPROVAL_SERVER = 'https://ginto.ai';
    private const FRP_ONLINE_CACHE_FILE = '/tmp/ginto-frp-online-subdomains.json';
    private const FRP_ONLINE_CACHE_LOCK = '/tmp/ginto-frp-online-subdomains.lock';

    public function __construct(?\Medoo\Medoo $db = null)
    {
        $this->db = $db;
    }

    public function relayVision(): void
    {
        $this->proxyVisionRequest('/');
    }

    public function relayVisionPath(string $path = ''): void
    {
        $this->proxyVisionRequest('/' . ltrim($path, '/'));
    }

    public function relayApproval(): void
    {
        header('Content-Type: application/json');
        $subdomain = strtolower((string)($_GET['subdomain'] ?? self::VISION_RELAY_SUBDOMAIN));

        $approved = $this->isSubdomainApprovedLocally($subdomain);
        $registry = $this->readRegistry();
        $entry = is_array($registry) ? ($registry[$subdomain] ?? null) : null;
        $expiresAt = (int)($entry['expires_at'] ?? 0);
        $blocked = $this->isSubdomainBlockedLocally($subdomain);

        $this->recordRelayApprovalCheck($subdomain, [
            'checked_at' => time(),
            'checked_at_iso' => date('c'),
            'client_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'host' => $_SERVER['HTTP_HOST'] ?? '',
            'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 220),
            'approved' => $approved,
            'blocked' => $blocked,
            'expires_at' => $expiresAt,
        ]);

        echo json_encode([
            'success' => true,
            'subdomain' => $subdomain,
            'approved' => $approved,
            'blocked' => $blocked,
            'expires_at' => $expiresAt,
            'remaining' => $expiresAt > 0 ? max(0, $expiresAt - time()) : 0,
        ]);
    }

    private function proxyVisionRequest(string $path): void
    {
        $localRelayPort = (int)(getenv('TUNNEL_RELAY_LOCAL_PORT') ?: ($_ENV['TUNNEL_RELAY_LOCAL_PORT'] ?? 18080));
        if ($localRelayPort < 1024 || $localRelayPort > 65535) {
            $localRelayPort = 18080;
        }

        $requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $requestHost = preg_replace('/:\\d+$/', '', $requestHost);
        $isLocalRequest = in_array($requestHost, ['localhost', '127.0.0.1', '::1'], true);

        if ($isLocalRequest) {
            $this->proxyToTargetHost('127.0.0.1:' . $localRelayPort, $path, '/tunnel', true);
            return;
        }

        if (!$this->isVisionRelayApprovedRemote()) {
            http_response_code(403);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><html><head><meta charset="utf-8"><title>Tunnel Not Approved</title></head><body style="font-family:sans-serif;padding:24px;"><h2>vision.ginto.ai relay is not approved</h2><p>Approve or re-enable it in /admin/hosting/tunnels first.</p><p>Approval check: <code>' . htmlspecialchars($this->lastRelayApprovalDetail, ENT_QUOTES, 'UTF-8') . '</code></p><p>Local relay port reserved: <code>http://127.0.0.1:' . $localRelayPort . '</code></p></body></html>';
            return;
        }

        $this->proxyToTargetHost(self::VISION_RELAY_SUBDOMAIN . '.ginto.ai', $path, '/tunnel', false);
    }

    private function proxyToTargetHost(string $targetHost, string $path, string $locationPrefix, bool $isHttpTarget): void
    {
        $normalizedPath = '/' . ltrim($path, '/');
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        $targetScheme = $isHttpTarget ? 'http' : 'https';
        $targetUrl = $targetScheme . '://' . $targetHost . $normalizedPath . ($queryString !== '' ? ('?' . $queryString) : '');
        $timeoutSeconds = $this->resolveRelayTimeoutSeconds($normalizedPath);

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $relayBody = null;
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            $relayBody = file_get_contents('php://input');
        }

        $ch = curl_init($targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        if ($relayBody !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $relayBody);
        }

        $headers = [];
        foreach ($this->getIncomingHeaders() as $name => $value) {
            $lower = strtolower((string)$name);
            if (in_array($lower, ['host', 'connection', 'content-length', 'transfer-encoding', 'keep-alive', 'upgrade', 'proxy-authorization', 'proxy-authenticate', 'te', 'trailer'], true)) {
                continue;
            }
            $headers[] = $name . ': ' . $value;
        }
        $headers[] = 'Host: ' . $targetHost;
        $headers[] = 'X-Forwarded-For: ' . ($_SERVER['REMOTE_ADDR'] ?? '');
        $headers[] = 'X-Forwarded-Host: ' . ($_SERVER['HTTP_HOST'] ?? '');
        $headers[] = 'X-Forwarded-Proto: ' . ($isHttpTarget ? 'http' : 'https');
        $headers[] = 'X-Ginto-Tunnel-Relay: ' . self::VISION_RELAY_SUBDOMAIN . '.ginto.ai';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($response === false) {
            http_response_code(502);
            header('Content-Type: text/html; charset=utf-8');
            $title = $isHttpTarget ? 'Failed to reach local relay target' : 'Failed to reach vision relay endpoint';
            $displayTarget = ($isHttpTarget ? 'http://' : 'https://') . $targetHost;
            echo '<!doctype html><html><head><meta charset="utf-8"><title>Relay Error</title></head><body style="font-family:sans-serif;padding:24px;"><h2>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2><p>' . htmlspecialchars($curlError ?: 'Unknown upstream error', ENT_QUOTES, 'UTF-8') . '</p><p>Relay target: <code>' . htmlspecialchars($displayTarget, ENT_QUOTES, 'UTF-8') . '</code></p></body></html>';
            return;
        }

        $rawHeaders = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        http_response_code($status > 0 ? $status : 200);
        header('X-Ginto-Tunnel-Relay: ' . $targetHost);

        $headerLines = preg_split('/\r\n|\n|\r/', $rawHeaders) ?: [];
        foreach ($headerLines as $line) {
            if ($line === '' || stripos($line, 'HTTP/') === 0) {
                continue;
            }
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $name = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            $lower = strtolower($name);
            if (in_array($lower, ['content-length', 'transfer-encoding', 'connection', 'strict-transport-security'], true)) {
                continue;
            }
            if ($lower === 'location') {
                $value = str_replace('http://' . $targetHost, $locationPrefix, $value);
                $value = str_replace('https://' . $targetHost, $locationPrefix, $value);
                if (str_starts_with($value, '/') && !str_starts_with($value, $locationPrefix . '/') && $value !== $locationPrefix) {
                    $value = $locationPrefix . $value;
                }
            }
            header($name . ': ' . $value, false);
        }

        echo $body;
    }

    private function resolveRelayTimeoutSeconds(string $normalizedPath): int
    {
        // Image generation can take much longer than standard API calls,
        // especially through local CPU relay and model warm-up.
        if (str_starts_with($normalizedPath, '/api/generate-stream')) {
            return 600;
        }
        if (str_starts_with($normalizedPath, '/api/generate')) {
            return 300;
        }

        return 60;
    }

    private function isVisionRelayApprovedRemote(): bool
    {
        $server = rtrim((string)(getenv('TUNNEL_APPROVAL_SERVER') ?: ($_ENV['TUNNEL_APPROVAL_SERVER'] ?? self::APPROVAL_SERVER)), '/');
        $subdomain = self::VISION_RELAY_SUBDOMAIN;
        $approvalUrl = $server . '/api/tunnel/relay/approval?subdomain=' . rawurlencode($subdomain);
        $this->lastRelayApprovalDetail = 'request=' . $approvalUrl;

        $ch = curl_init($approvalUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Ginto-Relay-Check: 1']);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            $localApproved = $this->isSubdomainApprovedLocally($subdomain);
            $this->lastRelayApprovalDetail = 'request=' . $approvalUrl . ' http=' . $httpCode . ' error=' . ($curlError !== '' ? $curlError : 'none') . ' fallback_local=' . ($localApproved ? 'true' : 'false');
            return $localApproved;
        }

        $decoded = json_decode((string)$response, true);
        $approved = is_array($decoded) && !empty($decoded['approved']);
        $this->lastRelayApprovalDetail = 'request=' . $approvalUrl . ' http=' . $httpCode . ' approved=' . ($approved ? 'true' : 'false');
        return $approved;
    }

    private function isSubdomainApprovedLocally(string $subdomain): bool
    {
        if ($this->isSubdomainBlockedLocally($subdomain)) {
            return false;
        }

        $registry = $this->readRegistry();
        if (!is_array($registry) || !isset($registry[$subdomain])) {
            return false;
        }

        $expiresAt = (int)($registry[$subdomain]['expires_at'] ?? 0);
        if ($expiresAt <= time()) {
            return false;
        }

        return true;
    }

    private function isSubdomainBlockedLocally(string $subdomain): bool
    {
        $path = $this->resolveTunnelDataReadPath(self::TUNNEL_BLOCKLIST_FILE, self::TUNNEL_BLOCKLIST_FALLBACK_FILE);
        if ($path === null) {
            return false;
        }
        $blocklist = json_decode((string)file_get_contents($path), true);
        return is_array($blocklist) && in_array($subdomain, $blocklist, true);
    }

    private function readRegistry(): array
    {
        $path = $this->resolveTunnelDataReadPath(self::TUNNEL_REGISTRY_FILE, self::TUNNEL_REGISTRY_FALLBACK_FILE);
        if ($path === null) {
            return [];
        }
        $registry = json_decode((string)file_get_contents($path), true);
        return is_array($registry) ? $registry : [];
    }

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

    private function recordRelayApprovalCheck(string $subdomain, array $check): void
    {
        try {
            $dir = dirname(self::TUNNEL_RELAY_CHECKS_FILE);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            $data = [];
            if (file_exists(self::TUNNEL_RELAY_CHECKS_FILE)) {
                $decoded = json_decode((string)file_get_contents(self::TUNNEL_RELAY_CHECKS_FILE), true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }

            $count = (int)($data[$subdomain]['check_count'] ?? 0) + 1;
            $check['check_count'] = $count;
            $data[$subdomain] = $check;
            @file_put_contents(self::TUNNEL_RELAY_CHECKS_FILE, json_encode($data, JSON_PRETTY_PRINT));
        } catch (\Throwable $_) {
        }
    }

    private function getIncomingHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            return is_array($headers) ? $headers : [];
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (!str_starts_with($key, 'HTTP_')) {
                continue;
            }
            $header = str_replace('_', '-', ucwords(strtolower(substr($key, 5)), '_'));
            $headers[$header] = $value;
        }
        return $headers;
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
            $reserved = ['www', 'api', 'admin', 'mail', 'ftp', 'ssh', 'oi', 'tunnel', 'app', 'dev', 'staging', 'ginto'];
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

        // If an operator key is enabled for this subdomain, only approve if the currently-online
        // FRP proxy presents the matching key via proxy metas.
        $requiredOperatorKeyHash = '';
        $registryFile = '/var/lib/ginto/tunnel-registry.json';
        $registryFallback = '/tmp/ginto-tunnel-registry.json';
        $registryPath = file_exists($registryFile) ? $registryFile : (file_exists($registryFallback) ? $registryFallback : null);
        if ($registryPath !== null) {
            $registry = json_decode((string)@file_get_contents($registryPath), true) ?: [];
            if (is_array($registry) && isset($registry[$subdomain]) && is_array($registry[$subdomain])) {
                $entry = $registry[$subdomain];
                $enabled = !empty($entry['access_key_enabled']);
                $hash = (string)($entry['access_key_hash'] ?? '');
                if ($enabled && $hash !== '') {
                    $requiredOperatorKeyHash = $hash;
                }
            }
        }
        
        // Check reserved subdomains - deny certificate
        $reserved = ['www', 'api', 'admin', 'mail', 'ftp', 'ssh', 'tunnel', 'app', 'dev', 'staging', 'ginto', 'ns1', 'ns2', 'mx'];
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

        // 6. Check FRP dashboard API for an active proxy for this subdomain.
        // This supports real FRP clients that run off-server (no local PID file).
        // Uses a small on-disk cache to avoid hammering frps during bot/scanner traffic.
        if ($requiredOperatorKeyHash !== '') {
            if ($this->isFrpSubdomainOnlineWithOperatorKey($subdomain, $requiredOperatorKeyHash)) {
                http_response_code(200);
                echo "OK - FRP dashboard + operator key";
                return;
            }
        } elseif ($this->isFrpSubdomainOnline($subdomain)) {
            http_response_code(200);
            echo "OK - FRP dashboard";
            return;
        }
        
        // 7. Check FRP tunnel registry (tracks registered tunnels)
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

    private function isFrpSubdomainOnline(string $subdomain): bool
    {
        $subdomain = strtolower(trim($subdomain));
        if ($subdomain === '') {
            return false;
        }

        // Fast path: very short cache window.
        $cached = $this->readFrpOnlineCache(2);
        if ($cached !== null) {
            return in_array($subdomain, $cached, true);
        }

        // Update path guarded by a lock to prevent thundering herd.
        $lockHandle = @fopen(self::FRP_ONLINE_CACHE_LOCK, 'c');
        if ($lockHandle === false) {
            // If we can't lock, do a best-effort single fetch without caching.
            $online = $this->fetchFrpOnlineSubdomains();
            return in_array($subdomain, $online, true);
        }

        $gotLock = @flock($lockHandle, LOCK_EX);
        if (!$gotLock) {
            @fclose($lockHandle);
            $online = $this->fetchFrpOnlineSubdomains();
            return in_array($subdomain, $online, true);
        }

        // Re-check cache after acquiring lock.
        $cached = $this->readFrpOnlineCache(2);
        if ($cached !== null) {
            @flock($lockHandle, LOCK_UN);
            @fclose($lockHandle);
            return in_array($subdomain, $cached, true);
        }

        $online = $this->fetchFrpOnlineSubdomains();
        $this->writeFrpOnlineCache($online);

        @flock($lockHandle, LOCK_UN);
        @fclose($lockHandle);

        return in_array($subdomain, $online, true);
    }

    private function readFrpOnlineCache(int $maxAgeSeconds): ?array
    {
        if (!file_exists(self::FRP_ONLINE_CACHE_FILE)) {
            return null;
        }
        $mtime = @filemtime(self::FRP_ONLINE_CACHE_FILE);
        if (!is_int($mtime) || (time() - $mtime) > $maxAgeSeconds) {
            return null;
        }
        $decoded = json_decode((string)@file_get_contents(self::FRP_ONLINE_CACHE_FILE), true);
        if (!is_array($decoded)) {
            return null;
        }
        $subdomains = $decoded['subdomains'] ?? null;
        if (!is_array($subdomains)) {
            return null;
        }
        $out = [];
        foreach ($subdomains as $sd) {
            $sd = strtolower(trim((string)$sd));
            if ($sd !== '') {
                $out[] = $sd;
            }
        }
        return array_values(array_unique($out));
    }

    private function writeFrpOnlineCache(array $subdomains): void
    {
        $payload = [
            'updated_at' => time(),
            'subdomains' => array_values(array_unique(array_map('strval', $subdomains))),
        ];
        @file_put_contents(self::FRP_ONLINE_CACHE_FILE, json_encode($payload));
    }

    private function fetchFrpOnlineSubdomains(): array
    {
        $dashPwd = trim((string)(getenv('FRP_DASHBOARD_PWD') ?: ($_ENV['FRP_DASHBOARD_PWD'] ?? '')));
        if ($dashPwd === '') {
            $envFile = '/etc/frp/frps.env';
            if (file_exists($envFile) && is_readable($envFile)) {
                $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (is_array($lines)) {
                    foreach ($lines as $line) {
                        $line = trim((string)$line);
                        if ($line === '' || str_starts_with($line, '#')) {
                            continue;
                        }
                        if (!str_starts_with($line, 'FRP_DASHBOARD_PWD=')) {
                            continue;
                        }
                        $dashPwd = trim(substr($line, strlen('FRP_DASHBOARD_PWD=')));
                        break;
                    }
                }
            }
        }

        if ($dashPwd === '') {
            return [];
        }

        $online = [];
        foreach (['/api/proxy/http', '/api/proxy/https'] as $endpoint) {
            $ch = curl_init('http://127.0.0.1:7500' . $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($ch, CURLOPT_USERPWD, 'admin:' . $dashPwd);
            $resp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($resp === false || $code < 200 || $code >= 300) {
                continue;
            }

            $decoded = json_decode((string)$resp, true);
            $proxies = is_array($decoded) ? ($decoded['proxies'] ?? null) : null;
            if (!is_array($proxies)) {
                continue;
            }

            foreach ($proxies as $proxy) {
                if (!is_array($proxy)) {
                    continue;
                }
                if ((string)($proxy['status'] ?? '') !== 'online') {
                    continue;
                }
                $conf = is_array($proxy['conf'] ?? null) ? $proxy['conf'] : [];
                $sd = strtolower(trim((string)($conf['subdomain'] ?? '')));
                if ($sd !== '') {
                    $online[] = $sd;
                }
            }
        }

        return array_values(array_unique($online));
    }

    private function isFrpSubdomainOnlineWithOperatorKey(string $subdomain, string $requiredHash): bool
    {
        $subdomain = strtolower(trim($subdomain));
        if ($subdomain === '' || $requiredHash === '') {
            return false;
        }

        $cached = $this->readFrpOnlineKeyCache(2);
        if ($cached !== null) {
            $seen = (string)($cached[$subdomain] ?? '');
            return $seen !== '' && hash_equals($requiredHash, $seen);
        }

        $lockHandle = @fopen('/tmp/ginto-frp-online-keys.lock', 'c');
        if ($lockHandle === false) {
            $map = $this->fetchFrpOnlineSubdomainKeyHashes();
            $seen = (string)($map[$subdomain] ?? '');
            return $seen !== '' && hash_equals($requiredHash, $seen);
        }

        $gotLock = @flock($lockHandle, LOCK_EX);
        if (!$gotLock) {
            @fclose($lockHandle);
            $map = $this->fetchFrpOnlineSubdomainKeyHashes();
            $seen = (string)($map[$subdomain] ?? '');
            return $seen !== '' && hash_equals($requiredHash, $seen);
        }

        $cached = $this->readFrpOnlineKeyCache(2);
        if ($cached !== null) {
            @flock($lockHandle, LOCK_UN);
            @fclose($lockHandle);
            $seen = (string)($cached[$subdomain] ?? '');
            return $seen !== '' && hash_equals($requiredHash, $seen);
        }

        $map = $this->fetchFrpOnlineSubdomainKeyHashes();
        $this->writeFrpOnlineKeyCache($map);

        @flock($lockHandle, LOCK_UN);
        @fclose($lockHandle);

        $seen = (string)($map[$subdomain] ?? '');
        return $seen !== '' && hash_equals($requiredHash, $seen);
    }

    private function readFrpOnlineKeyCache(int $maxAgeSeconds): ?array
    {
        $file = '/tmp/ginto-frp-online-keys.json';
        if (!file_exists($file)) {
            return null;
        }
        $mtime = @filemtime($file);
        if (!is_int($mtime) || (time() - $mtime) > $maxAgeSeconds) {
            return null;
        }
        $decoded = json_decode((string)@file_get_contents($file), true);
        if (!is_array($decoded)) {
            return null;
        }
        $map = $decoded['keys'] ?? null;
        return is_array($map) ? $map : null;
    }

    private function writeFrpOnlineKeyCache(array $keysBySubdomain): void
    {
        $payload = [
            'updated_at' => time(),
            'keys' => $keysBySubdomain,
        ];
        @file_put_contents('/tmp/ginto-frp-online-keys.json', json_encode($payload));
    }

    private function fetchFrpOnlineSubdomainKeyHashes(): array
    {
        $dashPwd = trim((string)(getenv('FRP_DASHBOARD_PWD') ?: ($_ENV['FRP_DASHBOARD_PWD'] ?? '')));
        if ($dashPwd === '') {
            $envFile = '/etc/frp/frps.env';
            if (file_exists($envFile) && is_readable($envFile)) {
                $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (is_array($lines)) {
                    foreach ($lines as $line) {
                        $line = trim((string)$line);
                        if ($line === '' || str_starts_with($line, '#')) {
                            continue;
                        }
                        if (!str_starts_with($line, 'FRP_DASHBOARD_PWD=')) {
                            continue;
                        }
                        $dashPwd = trim(substr($line, strlen('FRP_DASHBOARD_PWD=')));
                        break;
                    }
                }
            }
        }

        if ($dashPwd === '') {
            return [];
        }

        $out = [];
        foreach (['/api/proxy/http', '/api/proxy/https'] as $endpoint) {
            $ch = curl_init('http://127.0.0.1:7500' . $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($ch, CURLOPT_USERPWD, 'admin:' . $dashPwd);
            $resp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($resp === false || $code < 200 || $code >= 300) {
                continue;
            }

            $decoded = json_decode((string)$resp, true);
            $proxies = is_array($decoded) ? ($decoded['proxies'] ?? null) : null;
            if (!is_array($proxies)) {
                continue;
            }

            foreach ($proxies as $proxy) {
                if (!is_array($proxy) || (string)($proxy['status'] ?? '') !== 'online') {
                    continue;
                }
                $conf = is_array($proxy['conf'] ?? null) ? $proxy['conf'] : [];
                $sd = strtolower(trim((string)($conf['subdomain'] ?? '')));
                if ($sd === '') {
                    continue;
                }
                $metas = $conf['metas'] ?? null;
                $rawKey = '';
                if (is_array($metas)) {
                    $rawKey = trim((string)($metas['ginto_key'] ?? $metas['ginto-key'] ?? ''));
                }
                if ($rawKey === '' && isset($conf['meta_ginto_key'])) {
                    $rawKey = trim((string)$conf['meta_ginto_key']);
                }

                if ($rawKey !== '') {
                    $out[$sd] = hash('sha256', $rawKey);
                }
            }
        }

        return $out;
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

    /**
     * API: Generate a tunnel access key (JWT) for a subdomain.
     * POST /api/tunnel/access-key/generate
    * Body JSON: {"subdomain":"test","ttl_seconds":2592000}
     *
     * Returns the token once. The server stores only sha256(token) for verification.
     */
    public function generateAccessKey(): void
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
        $this->validateCsrfFromInput($input);

        $subdomain = strtolower(trim((string)($input['subdomain'] ?? '')));
        $subdomain = preg_replace('/[^a-z0-9\-]/', '', $subdomain);

        if ($subdomain === '' || !preg_match('/^[a-z0-9](?:[a-z0-9-]{0,30}[a-z0-9])?$/', $subdomain)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid subdomain']);
            return;
        }

        // Reserved words still blocked.
        $reserved = ['www', 'api', 'admin', 'mail', 'ftp', 'ssh', 'oi', 'tunnel', 'app', 'dev', 'staging', 'ginto', 'ns1', 'ns2', 'mx'];
        if (in_array($subdomain, $reserved, true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Reserved subdomain']);
            return;
        }

        // Default TTL should be long enough that subdomains don't randomly flip back
        // to unauthorized during normal usage. Revocation remains the primary control.
        $ttl = (int)($input['ttl_seconds'] ?? (86400 * 30));
        if ($ttl < 60) {
            $ttl = 60;
        }
        // Cap TTL to reduce long-lived token risk while still being practical.
        if ($ttl > (86400 * 365)) {
            $ttl = 86400 * 365;
        }

        // Prevent users from generating keys for subdomains owned by someone else.
        // Ownership is tracked in the tunnel registry (written during tunnel registration / FRP key setup).
        try {
            $registry = $this->readRegistry();
            $entry = (isset($registry[$subdomain]) && is_array($registry[$subdomain])) ? $registry[$subdomain] : [];
            $owner = (int)($entry['owner_user_id'] ?? 0);
            if ($owner !== 0 && $owner !== (int)$userId) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'code' => 'DOMAIN_NOT_AVAILABLE',
                    'error' => 'Domain is not available.',
                ]);
                return;
            }
        } catch (\Throwable $_) {
            // If registry cannot be read, do not block here.
        }

        // Additional safety: if another user already has an active key for this subdomain,
        // treat it as unavailable.
        if ($this->db) {
            try {
                $nowSql = date('Y-m-d H:i:s');
                $other = $this->db->get('tunnel_access_keys', ['id'], [
                    'subdomain' => $subdomain,
                    'revoked' => 0,
                    'expires_at[>]' => $nowSql,
                    'user_id[!]' => (int)$userId,
                    'ORDER' => ['id' => 'DESC'],
                ]);
                if ($other) {
                    http_response_code(409);
                    echo json_encode([
                        'success' => false,
                        'code' => 'DOMAIN_NOT_AVAILABLE',
                        'error' => 'Domain is not available.',
                    ]);
                    return;
                }
            } catch (\Throwable $_) {
                // ignore
            }
        }

        // Enforce user key limits (admins unlimited).
        // Rule: non-admins can have 3 active (unrevoked, unexpired) keys for free.
        // Additional keys require an active "serverless_key" addon subscription ($105/mo per key).
        $isAdmin = false;
        try {
            $isAdmin = UserController::isAdmin();
        } catch (\Throwable $_) {
            $isAdmin = false;
        }

        if (!$isAdmin && $this->db) {
            try {
                $nowSql = date('Y-m-d H:i:s');
                $activeKeys = (int)$this->db->count('tunnel_access_keys', [
                    'user_id' => (int)$userId,
                    'revoked' => 0,
                    'expires_at[>]' => $nowSql,
                ]);

                $extraSlots = 0;
                try {
                    $extraSlots = (int)$this->db->count('user_addons', [
                        'user_id' => (int)$userId,
                        'addon_type' => 'serverless_key',
                        'status' => 'active',
                    ]);
                } catch (\Throwable $_) {
                    $extraSlots = 0;
                }

                $limit = 3 + max(0, $extraSlots);
                if ($activeKeys >= $limit) {
                    http_response_code(402);
                    echo json_encode([
                        'success' => false,
                        'code' => 'KEY_LIMIT_REACHED',
                        'error' => 'Key limit reached. Upgrade to add more keys.',
                        'active_keys' => $activeKeys,
                        'limit' => $limit,
                        'addon_type' => 'serverless_key',
                        'addon_price_usd' => 105,
                    ]);
                    return;
                }
            } catch (\Throwable $_) {
                // If counting fails, do not block key generation.
            }
        }

        try {
            // Enforce one active key per user+subdomain.
            // User must revoke the existing key before generating a new one.
            if ($this->db) {
                $existingActive = $this->db->get('tunnel_access_keys', ['id', 'expires_at'], [
                    'user_id' => (int)$userId,
                    'subdomain' => $subdomain,
                    'revoked' => 0,
                    'ORDER' => ['id' => 'DESC'],
                ]);
                if ($existingActive) {
                    $expiresAt = (string)($existingActive['expires_at'] ?? '');
                    $isExpired = ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) <= time());
                    if (!$isExpired) {
                        http_response_code(409);
                        echo json_encode([
                            'success' => false,
                            'error' => 'Key already exists for this subdomain. Revoke it first.',
                        ]);
                        return;
                    }
                }
            }

            $jti = bin2hex(random_bytes(16));
            $now = time();
            $payload = [
                'iss' => 'ginto.ai',
                'sub' => (int)$userId,
                'sd' => $subdomain,
                'jti' => $jti,
                'iat' => $now,
                'exp' => $now + $ttl,
            ];

            $secret = $this->getTunnelKeySigningSecret();
            if ($secret === '') {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Signing secret not configured']);
                return;
            }

            $jwt = $this->jwtEncodeHs256($payload, $secret);
            $token = 'gtnl-' . $jwt;

            if (!$this->db) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Database not available']);
                return;
            }

            $this->db->insert('tunnel_access_keys', [
                'user_id' => (int)$userId,
                'subdomain' => $subdomain,
                'jti' => $jti,
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', $now + $ttl),
                'revoked' => 0,
            ]);

            echo json_encode([
                'success' => true,
                'subdomain' => $subdomain,
                'expires_at' => $now + $ttl,
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to generate access key']);
        }
    }

    /**
     * API: List user's tunnel access keys.
     * GET /api/tunnel/access-keys
     */
    public function listAccessKeys(): void
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
            $rows = $this->db->select('tunnel_access_keys', [
                'id',
                'subdomain',
                'created_at',
                'expires_at',
                'last_used_at',
                'revoked',
                'revoked_at',
            ], [
                'user_id' => (int)$userId,
                'ORDER' => ['id' => 'DESC'],
                'LIMIT' => 200,
            ]);

            echo json_encode(['success' => true, 'keys' => is_array($rows) ? $rows : []]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to load keys']);
        }
    }

    /**
     * API: Revoke a user's tunnel access key.
     * POST /api/tunnel/access-key/revoke
     * Body: {"id":123,"csrf_token":"..."}
     */
    public function revokeAccessKey(): void
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

        $raw = json_decode((string)file_get_contents('php://input'), true);
        $input = is_array($raw) ? $raw : (is_array($_POST) ? $_POST : []);
        $this->validateCsrfFromInput($input);

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing id']);
            return;
        }

        try {
            $row = $this->db->get('tunnel_access_keys', ['id', 'user_id', 'revoked'], ['id' => $id]);
            if (!$row || (int)($row['user_id'] ?? 0) !== (int)$userId) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Key not found']);
                return;
            }
            if ((int)($row['revoked'] ?? 0) === 1) {
                echo json_encode(['success' => true]);
                return;
            }
            $this->db->update('tunnel_access_keys', [
                'revoked' => 1,
                'revoked_at' => date('Y-m-d H:i:s'),
            ], [
                'id' => $id,
                'user_id' => (int)$userId,
            ]);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to revoke key']);
        }
    }

    private function getAuthenticatedUserId(): ?int
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (!empty($_SESSION['user']['id'])) {
            return (int)$_SESSION['user']['id'];
        }

        // Most of the web app uses a flat session key.
        if (!empty($_SESSION['user_id'])) {
            return (int)$_SESSION['user_id'];
        }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            $bearer = trim($m[1]);
            if ($bearer !== '' && $this->db) {
                $tokenRecord = $this->db->get('api_tokens', ['user_id'], [
                    'token' => hash('sha256', $bearer),
                    'revoked' => 0,
                ]);
                if ($tokenRecord) {
                    return (int)$tokenRecord['user_id'];
                }
            }
        }

        return null;
    }

    private function validateCsrfFromInput(array $input): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $token = (string)($input['csrf_token'] ?? '');
        $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
        if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
    }

    private function getTunnelKeySigningSecret(): string
    {
        $appKey = (string)(getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? ''));
        if ($appKey === '') {
            // Fail closed if secret missing.
            return '';
        }
        return hash('sha256', $appKey . '|tunnel_access_keys', true);
    }

    private function jwtEncodeHs256(array $payload, string $secret): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $h = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $p = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $sig = hash_hmac('sha256', $h . '.' . $p, $secret, true);
        $s = $this->base64UrlEncode($sig);
        return $h . '.' . $p . '.' . $s;
    }

    private function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
