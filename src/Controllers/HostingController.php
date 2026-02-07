<?php
namespace Ginto\Controllers;

/**
 * Hosting Controller - Virtualmin/CyberPanel-style web hosting control panel
 * Admin-only access for bare-metal server management
 */
class HostingController
{
    protected $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? \Ginto\Core\Database::getInstance();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    /**
     * Require admin access
     */
    private function requireAdmin(): bool
    {
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            if ($this->isApiRequest()) {
                echo json_encode(['success' => false, 'error' => 'Admin access required']);
            } else {
                header('Location: /login');
            }
            exit;
        }
        return true;
    }

    /**
     * Check if request expects JSON
     */
    private function isApiRequest(): bool
    {
        return strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
            || strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
    }

    /**
     * Validate CSRF token
     */
    private function validateCsrf(): bool
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        return true;
    }

    /**
     * Run shell command safely
     */
    private function runCommand(string $cmd): array
    {
        $output = [];
        $returnCode = 0;
        exec($cmd . ' 2>&1', $output, $returnCode);
        return ['output' => implode("\n", $output), 'code' => $returnCode];
    }

    /**
     * Get system statistics
     */
    private function getSystemStats(): array
    {
        // CPU usage
        $cpuLoad = sys_getloadavg();
        $cpuCores = (int) shell_exec("nproc 2>/dev/null") ?: 1;
        $cpuPercent = round(($cpuLoad[0] / $cpuCores) * 100, 1);

        // Memory usage
        $memInfo = @file_get_contents('/proc/meminfo');
        $memTotal = $memUsed = $memFree = 0;
        if (preg_match('/MemTotal:\s+(\d+)/', $memInfo, $m)) $memTotal = (int)$m[1] * 1024;
        if (preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $m)) $memFree = (int)$m[1] * 1024;
        $memUsed = $memTotal - $memFree;

        // Disk usage
        $diskTotal = disk_total_space('/');
        $diskFree = disk_free_space('/');
        $diskUsed = $diskTotal - $diskFree;

        // Uptime
        $uptime = trim(shell_exec("uptime -p 2>/dev/null") ?: 'unknown');

        return [
            'cpu' => ['load' => $cpuLoad, 'percent' => min($cpuPercent, 100), 'cores' => $cpuCores],
            'memory' => [
                'total' => $memTotal,
                'used' => $memUsed,
                'free' => $memFree,
                'percent' => $memTotal > 0 ? round(($memUsed / $memTotal) * 100, 1) : 0
            ],
            'disk' => [
                'total' => $diskTotal,
                'used' => $diskUsed,
                'free' => $diskFree,
                'percent' => $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 1) : 0
            ],
            'uptime' => $uptime
        ];
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
    }

    // ========== DASHBOARD ==========

    /**
     * Main hosting dashboard
     */
    public function index(): void
    {
        $this->requireAdmin();
        
        $stats = $this->getSystemStats();
        $stats['memory']['total_human'] = $this->formatBytes($stats['memory']['total']);
        $stats['memory']['used_human'] = $this->formatBytes($stats['memory']['used']);
        $stats['disk']['total_human'] = $this->formatBytes($stats['disk']['total']);
        $stats['disk']['used_human'] = $this->formatBytes($stats['disk']['used']);

        // Get quick counts
        $domainCount = $this->getDomainCount();
        $dbCount = $this->getDatabaseCount();
        $emailCount = $this->getEmailAccountCount();
        $sslCount = $this->getSSLCertCount();

        // Get services status
        $services = $this->getServicesStatus();

        include dirname(__DIR__) . '/Views/admin/hosting/index.php';
        exit;
    }

    // ========== DOMAINS ==========

    /**
     * Virtual hosts management page
     */
    public function domains(): void
    {
        $this->requireAdmin();
        include dirname(__DIR__) . '/Views/admin/hosting/domains.php';
        exit;
    }

    /**
     * Quick one-click domain assignment to container
     * POST /admin/hosting/api/quick-assign
     */
    public function quickAssignDomain(): void
    {
        header('Content-Type: application/json');
        $this->requireAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        $this->validateCsrf();
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $domain = trim($input['domain'] ?? '');
        $containerName = trim($input['container'] ?? '');
        $containerIp = trim($input['ip'] ?? '');
        
        if (empty($domain)) {
            echo json_encode(['success' => false, 'error' => 'Domain is required']);
            exit;
        }
        
        if (empty($containerName) || empty($containerIp)) {
            echo json_encode(['success' => false, 'error' => 'Container name and IP are required']);
            exit;
        }
        
        // Get owner info from container
        $ownerInfo = $this->getContainerOwnerInfo($containerName);
        
        // Create the domain with container proxy
        $result = $this->createDomain([
            'domain' => $domain,
            'proxy_type' => 'container',
            'proxy_target' => "http://{$containerIp}:80",
            'proxy_container_name' => $containerName,
            'ssl' => true,
            'owner_username' => $ownerInfo['username'] ?? '',
            'owner_fullname' => $ownerInfo['fullname'] ?? ''
        ]);
        
        echo json_encode($result);
        exit;
    }
    
    /**
     * Get container owner info from client_sandboxes table
     */
    private function getContainerOwnerInfo(string $containerName): array
    {
        try {
            // Extract sandbox ID from container name (e.g., "ginto-sandbox-abc123" -> "abc123")
            $sandboxId = $containerName;
            if (preg_match('/^ginto-sandbox-(.+)$/', $containerName, $matches)) {
                $sandboxId = $matches[1];
            }
            
            $stmt = $this->db->pdo->prepare("
                SELECT u.username, u.fullname
                FROM client_sandboxes cs
                JOIN users u ON cs.user_id = u.id
                WHERE cs.sandbox_id = ?
                LIMIT 1
            ");
            $stmt->execute([$sandboxId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($row) {
                return $row;
            }
        } catch (\Throwable $e) {
            // Ignore
        }
        return [];
    }

    /**
     * Domains API - List/Create virtual hosts
     */
    public function domainsApi(): void
    {
        header('Content-Type: application/json');
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            echo json_encode($this->createDomain($input));
            exit;
        }

        // GET - list domains
        echo json_encode(['success' => true, 'domains' => $this->listDomains()]);
        exit;
    }

    /**
     * Get available containers for proxy dropdown
     */
    public function containersApi(): void
    {
        header('Content-Type: application/json');
        $this->requireAdmin();

        try {
            $lxcContainers = $this->isLxdInstalled() ? $this->getAvailableLxcContainers() : [];
            $dockerContainers = $this->isDockerInstalled() ? $this->getAvailableDockerContainers() : [];
            
            // Get users who have sandboxes for the owner dropdown
            $usersWithSandboxes = [];
            try {
                $usersWithSandboxes = $this->db->select('users', ['id', 'username', 'fullname', 'sandbox_id', 'lxc_sandbox_id'], [
                    'OR' => [
                        'sandbox_id[!]' => null,
                        'lxc_sandbox_id[!]' => null
                    ],
                    'ORDER' => ['username' => 'ASC']
                ]) ?: [];
            } catch (\Exception $e) {
                // Ignore
            }

            // Get ALL users for owner search (limit 500)
            $allUsers = [];
            try {
                $allUsers = $this->db->select('users', ['id', 'username', 'fullname'], [
                    'ORDER' => ['username' => 'ASC'],
                    'LIMIT' => 500
                ]) ?: [];
            } catch (\Exception $e) {
                // Ignore
            }

            echo json_encode([
                'success' => true,
                'lxd_installed' => $this->isLxdInstalled(),
                'docker_installed' => $this->isDockerInstalled(),
                'lxc_containers' => $lxcContainers,
                'docker_containers' => $dockerContainers,
                'users_with_sandboxes' => $usersWithSandboxes,
                'all_users' => $allUsers
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
        exit;
    }

    /**
     * Domain actions (start/stop/delete/ssl)
     */
    public function domainAction(string $domain): void
    {
        header('Content-Type: application/json');
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $action = $input['action'] ?? '';

            $result = match($action) {
                'enable' => $this->enableDomain($domain),
                'disable' => $this->disableDomain($domain),
                'delete' => $this->deleteDomain($domain),
                'ssl' => $this->requestSSL($domain),
                default => ['success' => false, 'error' => 'Unknown action']
            };
            echo json_encode($result);
            exit;
        }

        // GET - domain details
        echo json_encode(['success' => true, 'domain' => $this->getDomainDetails($domain)]);
        exit;
    }

    // ========== DNS ==========

    public function dns(): void
    {
        $this->requireAdmin();
        include dirname(__DIR__) . '/Views/admin/hosting/dns.php';
        exit;
    }

    public function dnsApi(): void
    {
        header('Content-Type: application/json');
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            echo json_encode($this->manageDnsRecord($input));
            exit;
        }

        $zone = $_GET['zone'] ?? null;
        if ($zone) {
            echo json_encode(['success' => true, 'records' => $this->getDnsRecords($zone)]);
        } else {
            echo json_encode(['success' => true, 'zones' => $this->listDnsZones()]);
        }
        exit;
    }

    // ========== EMAIL ==========

    public function email(): void
    {
        $this->requireAdmin();
        include dirname(__DIR__) . '/Views/admin/hosting/email.php';
        exit;
    }

    public function emailApi(): void
    {
        header('Content-Type: application/json');
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            echo json_encode($this->manageEmailAccount($input));
            exit;
        }

        echo json_encode(['success' => true, 'accounts' => $this->listEmailAccounts()]);
        exit;
    }

    // ========== DATABASES ==========

    public function databases(): void
    {
        $this->requireAdmin();
        include dirname(__DIR__) . '/Views/admin/hosting/databases.php';
        exit;
    }

    public function databasesApi(): void
    {
        header('Content-Type: application/json');
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            echo json_encode($this->manageDatabase($input));
            exit;
        }

        echo json_encode([
            'success' => true,
            'databases' => $this->listDatabases(),
            'users' => $this->listDatabaseUsers()
        ]);
        exit;
    }

    // ========== FTP ==========

    public function ftp(): void
    {
        $this->requireAdmin();
        include dirname(__DIR__) . '/Views/admin/hosting/ftp.php';
        exit;
    }

    public function ftpApi(): void
    {
        header('Content-Type: application/json');
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            echo json_encode($this->manageFtpAccount($input));
            exit;
        }

        echo json_encode(['success' => true, 'accounts' => $this->listFtpAccounts()]);
        exit;
    }

    // ========== BACKUPS ==========

    public function backups(): void
    {
        $this->requireAdmin();
        include dirname(__DIR__) . '/Views/admin/hosting/backups.php';
        exit;
    }

    public function backupsApi(): void
    {
        header('Content-Type: application/json');
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $action = $input['action'] ?? 'create';

            $result = match($action) {
                'create' => $this->createBackup($input),
                'restore' => $this->restoreBackup($input),
                'delete' => $this->deleteBackup($input),
                'schedule' => $this->scheduleBackup($input),
                default => ['success' => false, 'error' => 'Unknown action']
            };
            echo json_encode($result);
            exit;
        }

        echo json_encode([
            'success' => true,
            'backups' => $this->listBackups(),
            'schedules' => $this->listBackupSchedules()
        ]);
        exit;
    }

    // ========== SSL ==========

    public function ssl(): void
    {
        $this->requireAdmin();
        include dirname(__DIR__) . '/Views/admin/hosting/ssl.php';
        exit;
    }

    public function sslApi(): void
    {
        header('Content-Type: application/json');
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $action = $input['action'] ?? 'request';

            $result = match($action) {
                'request' => $this->requestSSL($input['domain'] ?? ''),
                'renew' => $this->renewSSL($input['domain'] ?? ''),
                'revoke' => $this->revokeSSL($input['domain'] ?? ''),
                default => ['success' => false, 'error' => 'Unknown action']
            };
            echo json_encode($result);
            exit;
        }

        echo json_encode(['success' => true, 'certificates' => $this->listSSLCerts()]);
        exit;
    }

    // ========== FIREWALL ==========

    public function firewall(): void
    {
        $this->requireAdmin();
        include dirname(__DIR__) . '/Views/admin/hosting/firewall.php';
        exit;
    }

    public function firewallApi(): void
    {
        header('Content-Type: application/json');
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            echo json_encode($this->manageFirewallRule($input));
            exit;
        }

        echo json_encode([
            'success' => true,
            'rules' => $this->listFirewallRules(),
            'fail2ban' => $this->getFail2banStatus()
        ]);
        exit;
    }

    // ========== SERVICES ==========

    public function services(): void
    {
        $this->requireAdmin();
        include dirname(__DIR__) . '/Views/admin/hosting/services.php';
        exit;
    }

    public function servicesApi(): void
    {
        header('Content-Type: application/json');
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $service = $input['service'] ?? '';
            $action = $input['action'] ?? '';

            if (!preg_match('/^[a-z0-9_-]+$/i', $service)) {
                echo json_encode(['success' => false, 'error' => 'Invalid service name']);
                exit;
            }

            $result = match($action) {
                'start' => $this->runCommand("sudo systemctl start " . escapeshellarg($service)),
                'stop' => $this->runCommand("sudo systemctl stop " . escapeshellarg($service)),
                'restart' => $this->runCommand("sudo systemctl restart " . escapeshellarg($service)),
                'enable' => $this->runCommand("sudo systemctl enable " . escapeshellarg($service)),
                'disable' => $this->runCommand("sudo systemctl disable " . escapeshellarg($service)),
                default => ['output' => 'Unknown action', 'code' => 1]
            };

            echo json_encode([
                'success' => $result['code'] === 0,
                'message' => $result['output'],
                'service' => $service,
                'action' => $action
            ]);
            exit;
        }

        echo json_encode(['success' => true, 'services' => $this->getServicesStatus()]);
        exit;
    }

    // ========== FRP TUNNELS ==========

    private const TUNNEL_REGISTRY_FILE = '/var/lib/ginto/tunnel-registry.json';
    private const TUNNEL_BLOCKLIST_FILE = '/var/lib/ginto/tunnel-blocklist.json';

    /**
     * Get tunnel registry (server-side tracking of all tunnels with expiry)
     */
    private function getTunnelRegistry(): array
    {
        $dir = dirname(self::TUNNEL_REGISTRY_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!file_exists(self::TUNNEL_REGISTRY_FILE)) {
            return [];
        }
        $data = json_decode(file_get_contents(self::TUNNEL_REGISTRY_FILE), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Save tunnel registry
     */
    private function saveTunnelRegistry(array $registry): void
    {
        $dir = dirname(self::TUNNEL_REGISTRY_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents(self::TUNNEL_REGISTRY_FILE, json_encode($registry, JSON_PRETTY_PRINT));
    }

    /**
     * Get tunnel blocklist
     */
    private function getTunnelBlocklist(): array
    {
        if (!file_exists(self::TUNNEL_BLOCKLIST_FILE)) {
            return [];
        }
        $data = json_decode(file_get_contents(self::TUNNEL_BLOCKLIST_FILE), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Save tunnel blocklist
     */
    private function saveTunnelBlocklist(array $blocklist): void
    {
        $dir = dirname(self::TUNNEL_BLOCKLIST_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents(self::TUNNEL_BLOCKLIST_FILE, json_encode($blocklist, JSON_PRETTY_PRINT));
    }

    public function tunnels(): void
    {
        $this->requireAdmin();
        include dirname(__DIR__) . '/Views/admin/hosting/tunnels.php';
        exit;
    }

    public function tunnelsApi(): void
    {
        header('Content-Type: application/json');
        $this->requireAdmin();

        // Get FRP dashboard credentials
        $frpDashboardPwd = trim(shell_exec("grep FRP_DASHBOARD_PWD /etc/frp/frps.env 2>/dev/null | cut -d= -f2") ?: '');
        
        $stats = ['http' => 0, 'https' => 0, 'tcp' => 0, 'clients' => 0];
        $proxies = [];
        
        // Helper function to fetch from frps API using curl
        $fetchFrp = function(string $endpoint) use ($frpDashboardPwd): ?array {
            $url = "http://127.0.0.1:7500{$endpoint}";
            $cmd = sprintf(
                "curl -s -u admin:%s %s 2>/dev/null",
                escapeshellarg($frpDashboardPwd),
                escapeshellarg($url)
            );
            $response = shell_exec($cmd);
            return $response ? json_decode($response, true) : null;
        };
        
        // Get server info for client count
        $serverInfo = $fetchFrp('/api/serverinfo');
        if ($serverInfo) {
            $stats['clients'] = $serverInfo['clientCounts'] ?? $serverInfo['client_counts'] ?? 0;
        }
        
        // Get HTTP proxies
        $httpData = $fetchFrp('/api/proxy/http');
        if ($httpData) {
            $httpProxies = $httpData['proxies'] ?? [];
            $stats['http'] = count($httpProxies);
            foreach ($httpProxies as $p) {
                $proxies[] = [
                    'name' => $p['name'] ?? 'unknown',
                    'type' => 'http',
                    'subdomain' => $p['conf']['subdomain'] ?? null,
                    'custom_domains' => $p['conf']['customDomains'] ?? [],
                    'local_addr' => ($p['conf']['localIP'] ?? '127.0.0.1') . ':' . ($p['conf']['localPort'] ?? ''),
                    'status' => $p['status'] ?? 'unknown',
                    'traffic_in' => $p['todayTrafficIn'] ?? 0,
                    'traffic_out' => $p['todayTrafficOut'] ?? 0,
                    'cur_conns' => $p['curConns'] ?? 0,
                ];
            }
        }
        
        // Get HTTPS proxies
        $httpsData = $fetchFrp('/api/proxy/https');
        if ($httpsData) {
            $httpsProxies = $httpsData['proxies'] ?? [];
            $stats['https'] = count($httpsProxies);
            foreach ($httpsProxies as $p) {
                $proxies[] = [
                    'name' => $p['name'] ?? 'unknown',
                    'type' => 'https',
                    'subdomain' => $p['conf']['subdomain'] ?? null,
                    'custom_domains' => $p['conf']['customDomains'] ?? [],
                    'local_addr' => ($p['conf']['localIP'] ?? '127.0.0.1') . ':' . ($p['conf']['localPort'] ?? ''),
                    'status' => $p['status'] ?? 'unknown',
                    'traffic_in' => $p['todayTrafficIn'] ?? 0,
                    'traffic_out' => $p['todayTrafficOut'] ?? 0,
                    'cur_conns' => $p['curConns'] ?? 0,
                ];
            }
        }
        
        // Get TCP proxies
        $tcpData = $fetchFrp('/api/proxy/tcp');
        if ($tcpData) {
            $tcpProxies = $tcpData['proxies'] ?? [];
            $stats['tcp'] = count($tcpProxies);
            foreach ($tcpProxies as $p) {
                $proxies[] = [
                    'name' => $p['name'] ?? 'unknown',
                    'type' => 'tcp',
                    'remote_port' => $p['conf']['remotePort'] ?? null,
                    'local_addr' => ($p['conf']['localIP'] ?? '127.0.0.1') . ':' . ($p['conf']['localPort'] ?? ''),
                    'status' => $p['status'] ?? 'unknown',
                    'traffic_in' => $p['todayTrafficIn'] ?? 0,
                    'traffic_out' => $p['todayTrafficOut'] ?? 0,
                    'cur_conns' => $p['curConns'] ?? 0,
                ];
            }
        }

        // Merge with tunnel registry for expiry info
        $registry = $this->getTunnelRegistry();
        $blocklist = $this->getTunnelBlocklist();
        $now = time();

        foreach ($proxies as &$p) {
            $subdomain = $p['subdomain'] ?? $p['name'] ?? '';
            
            // Add registry info (start time, expiry, client IP)
            if (isset($registry[$subdomain])) {
                $reg = $registry[$subdomain];
                $p['started_at'] = $reg['started_at'] ?? null;
                $p['expires_at'] = $reg['expires_at'] ?? null;
                $p['remaining'] = $reg['expires_at'] ? max(0, $reg['expires_at'] - $now) : null;
                $p['expired'] = $reg['expires_at'] && $now >= $reg['expires_at'];
                $p['client_ip'] = $reg['client_ip'] ?? null;
            } else {
                // Use FRP's lastStartTime if no registry entry
                $p['started_at'] = null;
                $p['expires_at'] = null;
                $p['remaining'] = null;
                $p['expired'] = false;
                $p['client_ip'] = null;
            }
            
            // Check blocklist
            $p['blocked'] = in_array($subdomain, $blocklist);
        }
        unset($p);
        
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'proxies' => $proxies,
            'registry_count' => count($registry),
            'blocklist_count' => count($blocklist)
        ]);
        exit;
    }

    /**
     * Register a tunnel (called by TunnelController when tunnel is created)
     * POST /admin/hosting/tunnels/register
     */
    public function tunnelsRegister(): void
    {
        header('Content-Type: application/json');
        
        // Allow internal calls or admin
        $input = json_decode(file_get_contents('php://input'), true);
        $subdomain = $input['subdomain'] ?? null;
        $expiresIn = $input['expires_in'] ?? 900; // Default 15 minutes for guests
        $clientIp = $input['client_ip'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        
        if (!$subdomain) {
            echo json_encode(['success' => false, 'error' => 'Missing subdomain']);
            exit;
        }
        
        $registry = $this->getTunnelRegistry();
        $registry[$subdomain] = [
            'subdomain' => $subdomain,
            'started_at' => time(),
            'expires_at' => time() + $expiresIn,
            'client_ip' => $clientIp,
            'registered_at' => date('Y-m-d H:i:s')
        ];
        $this->saveTunnelRegistry($registry);
        
        echo json_encode(['success' => true, 'subdomain' => $subdomain, 'expires_at' => $registry[$subdomain]['expires_at']]);
        exit;
    }

    /**
     * Disconnect/block a tunnel
     * POST /admin/hosting/tunnels/disconnect
     */
    public function tunnelsDisconnect(): void
    {
        header('Content-Type: application/json');
        $this->requireAdmin();
        $this->validateCsrf();
        
        $input = json_decode(file_get_contents('php://input'), true);
        $subdomain = $input['subdomain'] ?? null;
        
        if (!$subdomain) {
            echo json_encode(['success' => false, 'error' => 'Missing subdomain']);
            exit;
        }
        
        // Add to blocklist
        $blocklist = $this->getTunnelBlocklist();
        if (!in_array($subdomain, $blocklist)) {
            $blocklist[] = $subdomain;
            $this->saveTunnelBlocklist($blocklist);
        }
        
        // Remove from registry
        $registry = $this->getTunnelRegistry();
        unset($registry[$subdomain]);
        $this->saveTunnelRegistry($registry);
        
        echo json_encode(['success' => true, 'subdomain' => $subdomain, 'message' => 'Tunnel blocked']);
        exit;
    }

    /**
     * Unblock a tunnel
     * POST /admin/hosting/tunnels/unblock
     */
    public function tunnelsUnblock(): void
    {
        header('Content-Type: application/json');
        $this->requireAdmin();
        $this->validateCsrf();
        
        $input = json_decode(file_get_contents('php://input'), true);
        $subdomain = $input['subdomain'] ?? null;
        
        if (!$subdomain) {
            echo json_encode(['success' => false, 'error' => 'Missing subdomain']);
            exit;
        }
        
        $blocklist = $this->getTunnelBlocklist();
        $blocklist = array_values(array_diff($blocklist, [$subdomain]));
        $this->saveTunnelBlocklist($blocklist);
        
        echo json_encode(['success' => true, 'subdomain' => $subdomain, 'message' => 'Tunnel unblocked']);
        exit;
    }

    /**
     * Clear offline proxies from FRP server
     * POST /admin/hosting/tunnels/clear-offline
     */
    public function tunnelsClearOffline(): void
    {
        header('Content-Type: application/json');
        $this->requireAdmin();
        $this->validateCsrf();
        
        $frpDashboardPwd = trim(shell_exec("grep FRP_DASHBOARD_PWD /etc/frp/frps.env 2>/dev/null | cut -d= -f2") ?: '');
        
        $cmd = sprintf(
            "curl -s -X DELETE -u admin:%s 'http://127.0.0.1:7500/api/proxies?status=offline' 2>/dev/null",
            escapeshellarg($frpDashboardPwd)
        );
        $response = shell_exec($cmd);
        
        // Also clean up expired entries from registry
        $registry = $this->getTunnelRegistry();
        $now = time();
        $cleaned = 0;
        foreach ($registry as $subdomain => $data) {
            if (isset($data['expires_at']) && $now >= $data['expires_at']) {
                unset($registry[$subdomain]);
                $cleaned++;
            }
        }
        $this->saveTunnelRegistry($registry);
        
        echo json_encode([
            'success' => true,
            'message' => 'Cleared offline proxies',
            'registry_cleaned' => $cleaned
        ]);
        exit;
    }

    /**
     * Cleanup expired tunnels (can be called by cron)
     * GET /admin/hosting/tunnels/cleanup
     */
    public function tunnelsCleanup(): void
    {
        header('Content-Type: application/json');
        
        $registry = $this->getTunnelRegistry();
        $blocklist = $this->getTunnelBlocklist();
        $now = time();
        $expired = [];
        
        foreach ($registry as $subdomain => $data) {
            if (isset($data['expires_at']) && $now >= $data['expires_at']) {
                $expired[] = $subdomain;
                // Add to blocklist to prevent reconnection
                if (!in_array($subdomain, $blocklist)) {
                    $blocklist[] = $subdomain;
                }
                unset($registry[$subdomain]);
            }
        }
        
        if (!empty($expired)) {
            $this->saveTunnelRegistry($registry);
            $this->saveTunnelBlocklist($blocklist);
        }
        
        echo json_encode([
            'success' => true,
            'expired_count' => count($expired),
            'expired' => $expired
        ]);
        exit;
    }

    // ========== HELPER METHODS ==========

    private function getDomainCount(): int
    {
        // Count Caddy virtual hosts
        $caddyFile = '/etc/caddy/Caddyfile';
        if (!file_exists($caddyFile)) return 0;
        $content = file_get_contents($caddyFile);
        preg_match_all('/^[a-z0-9.-]+\s*\{/im', $content, $matches);
        return count($matches[0]);
    }

    private function getDatabaseCount(): int
    {
        try {
            $result = $this->db->query("SHOW DATABASES")->fetchAll();
            // Exclude system databases
            $systemDbs = ['information_schema', 'mysql', 'performance_schema', 'sys'];
            return count(array_filter($result, fn($r) => !in_array($r['Database'], $systemDbs)));
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getEmailAccountCount(): int
    {
        // Check Postfix virtual mailbox maps
        $vmailbox = '/etc/postfix/vmailbox';
        if (!file_exists($vmailbox)) return 0;
        $lines = file($vmailbox, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return count(array_filter($lines, fn($l) => !str_starts_with(trim($l), '#')));
    }

    private function getSSLCertCount(): int
    {
        $certDir = '/etc/caddy/certificates';
        if (!is_dir($certDir)) $certDir = '/var/lib/caddy/.local/share/caddy/certificates';
        if (!is_dir($certDir) || !is_readable($certDir)) return 0;
        
        try {
            $count = 0;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($certDir));
            foreach ($iterator as $file) {
                if ($file->getExtension() === 'crt' || $file->getExtension() === 'pem') $count++;
            }
            return $count;
        } catch (\Throwable $e) {
            // Directory may exist but not be readable
            return 0;
        }
    }

    private function getServicesStatus(): array
    {
        $services = ['caddy', 'mariadb', 'php8.3-fpm', 'postfix', 'dovecot', 'fail2ban', 'ufw'];
        $status = [];

        foreach ($services as $svc) {
            $result = $this->runCommand("systemctl is-active " . escapeshellarg($svc));
            $enabled = $this->runCommand("systemctl is-enabled " . escapeshellarg($svc));
            $status[$svc] = [
                'name' => $svc,
                'active' => trim($result['output']) === 'active',
                'enabled' => trim($enabled['output']) === 'enabled',
                'status' => trim($result['output'])
            ];
        }

        return $status;
    }

    private function listDomains(): array
    {
        $domains = [];
        $seenDomains = [];
        
        // First, get domains from database with owner info
        try {
            $stmt = $this->db->query("SELECT * FROM virtual_hosts ORDER BY domain ASC");
            $dbDomains = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($dbDomains as $d) {
                $seenDomains[$d['domain']] = true;
                $domains[] = [
                    'id' => $d['id'],
                    'name' => $d['domain'],
                    'enabled' => (bool)$d['is_enabled'],
                    'ssl' => $this->hasSSL($d['domain']),
                    'root' => $d['document_root'] ?: $this->getDomainRoot($d['domain']),
                    'owner_username' => $d['owner_username'],
                    'owner_fullname' => $d['owner_fullname'],
                    'proxy_type' => $d['proxy_type'] ?: 'none',
                    'proxy_target' => $d['proxy_target'],
                    'proxy_container_name' => $d['proxy_container_name']
                ];
            }
        } catch (\PDOException $e) {
            // Table might not exist yet, fall back to file-based listing
        }
        
        // Parse main Caddyfile for domains not in database
        $caddyFile = '/etc/caddy/Caddyfile';
        if (file_exists($caddyFile)) {
            $content = file_get_contents($caddyFile);
            preg_match_all('/^([a-z0-9.-]+)\s*\{/im', $content, $matches);
            foreach ($matches[1] as $domain) {
                if ($domain !== 'localhost' && !str_starts_with($domain, ':') && !isset($seenDomains[$domain])) {
                    $seenDomains[$domain] = true;
                    $domains[] = [
                        'name' => $domain,
                        'enabled' => true,
                        'ssl' => $this->hasSSL($domain),
                        'root' => $this->getDomainRoot($domain),
                        'owner_username' => null,
                        'owner_fullname' => null,
                        'proxy_type' => 'none',
                        'proxy_target' => null,
                        'proxy_container_name' => null
                    ];
                }
            }
        }

        // Also parse individual site configs from sites-enabled
        $sitesEnabledDir = '/etc/caddy/sites-enabled';
        if (is_dir($sitesEnabledDir)) {
            foreach (glob("{$sitesEnabledDir}/*.caddy") as $siteFile) {
                $content = file_get_contents($siteFile);
                preg_match_all('/^([a-z0-9.-]+)\s*\{/im', $content, $matches);
                foreach ($matches[1] as $domain) {
                    if ($domain !== 'localhost' && !str_starts_with($domain, ':') && !isset($seenDomains[$domain])) {
                        $seenDomains[$domain] = true;
                        $domains[] = [
                            'name' => $domain,
                            'enabled' => true,
                            'ssl' => $this->hasSSL($domain),
                            'root' => $this->getDomainRoot($domain),
                            'owner_username' => null,
                            'owner_fullname' => null,
                            'proxy_type' => 'none',
                            'proxy_target' => null,
                            'proxy_container_name' => null
                        ];
                    }
                }
            }
        }

        // Also check sites-available for disabled sites
        $sitesAvailableDir = '/etc/caddy/sites-available';
        if (is_dir($sitesAvailableDir)) {
            foreach (glob("{$sitesAvailableDir}/*.caddy") as $siteFile) {
                $content = file_get_contents($siteFile);
                preg_match_all('/^([a-z0-9.-]+)\s*\{/im', $content, $matches);
                foreach ($matches[1] as $domain) {
                    if ($domain !== 'localhost' && !str_starts_with($domain, ':') && !isset($seenDomains[$domain])) {
                        $seenDomains[$domain] = true;
                        // Check if enabled
                        $enabledFile = "{$sitesEnabledDir}/" . basename($siteFile);
                        $domains[] = [
                            'name' => $domain,
                            'enabled' => file_exists($enabledFile),
                            'ssl' => $this->hasSSL($domain),
                            'root' => $this->getDomainRoot($domain),
                            'owner_username' => null,
                            'owner_fullname' => null,
                            'proxy_type' => 'none',
                            'proxy_target' => null,
                            'proxy_container_name' => null
                        ];
                    }
                }
            }
        }

        return $domains;
    }

    /**
     * Get available LXC containers for proxy dropdown
     */
    private function getAvailableLxcContainers(): array
    {
        $containers = [];
        $socket = '/var/snap/lxd/common/lxd/unix.socket';
        
        if (!file_exists($socket)) {
            return [];
        }
        
        // Get list of instances via LXD API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, $socket);
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/1.0/instances');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $result = curl_exec($ch);
        curl_close($ch);
        
        if (!$result) {
            return [];
        }
        
        $data = json_decode($result, true);
        if (!isset($data['metadata']) || !is_array($data['metadata'])) {
            return [];
        }
        
        // Get each container's state to get IP
        foreach ($data['metadata'] as $instancePath) {
            $name = basename($instancePath);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, $socket);
            curl_setopt($ch, CURLOPT_URL, "http://localhost/1.0/instances/{$name}/state");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $stateResult = curl_exec($ch);
            curl_close($ch);
            
            if (!$stateResult) continue;
            
            $stateData = json_decode($stateResult, true);
            $status = strtolower($stateData['metadata']['status'] ?? 'unknown');
            
            // Get IPv4 address from eth0
            $ipv4 = null;
            $network = $stateData['metadata']['network']['eth0'] ?? null;
            if ($network && isset($network['addresses'])) {
                foreach ($network['addresses'] as $addr) {
                    if ($addr['family'] === 'inet' && $addr['scope'] === 'global') {
                        $ipv4 = $addr['address'];
                        break;
                    }
                }
            }
            
            // Get owner info from database if this is a sandbox
            $ownerUsername = null;
            $ownerFullname = null;
            if (strpos($name, 'ginto-sandbox') === 0) {
                // Extract sandbox ID: "ginto-sandbox" -> "" or "ginto-sandbox-abc123" -> "abc123"
                $sandboxId = (strlen($name) > 13 && $name[13] === '-') 
                    ? substr($name, 14) 
                    : '';
                
                if ($sandboxId) {
                    try {
                        // Look up in client_sandboxes table and join to users using raw PDO
                        $pdo = $this->db->pdo;
                        $stmt = $pdo->prepare(
                            "SELECT u.username, u.fullname 
                             FROM client_sandboxes cs 
                             JOIN users u ON (cs.user_id = u.id OR cs.public_id = u.public_id)
                             WHERE cs.sandbox_id = ?
                             LIMIT 1"
                        );
                        $stmt->execute([$sandboxId]);
                        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                        
                        if ($result) {
                            $ownerUsername = $result['username'];
                            $ownerFullname = $result['fullname'];
                        }
                    } catch (\Exception $e) {
                        // Log error for debugging
                        error_log("Container owner lookup error: " . $e->getMessage());
                    }
                }
            }
            
            $containers[] = [
                'name' => $name,
                'ip' => $ipv4,
                'status' => $status,
                'owner_username' => $ownerUsername,
                'owner_fullname' => $ownerFullname
            ];
        }
        
        return $containers;
    }

    /**
     * Get available Docker containers for proxy dropdown
     */
    private function getAvailableDockerContainers(): array
    {
        $containers = [];
        $output = [];
        exec("docker ps --format '{{.Names}}\t{{.Ports}}' 2>/dev/null", $output, $code);
        
        if ($code === 0) {
            foreach ($output as $line) {
                $parts = explode("\t", $line);
                if (count($parts) >= 1) {
                    $name = trim($parts[0]);
                    $ports = trim($parts[1] ?? '');
                    
                    // Extract port mapping (e.g., 0.0.0.0:8080->80/tcp)
                    $port = null;
                    if (preg_match('/0\.0\.0\.0:(\d+)->(\d+)/', $ports, $m)) {
                        $port = $m[1];
                    }
                    
                    $containers[] = [
                        'name' => $name,
                        'port' => $port,
                        'ports_raw' => $ports
                    ];
                }
            }
        }
        
        return $containers;
    }

    /**
     * Check if Docker is installed
     */
    private function isDockerInstalled(): bool
    {
        // Check for docker socket or command
        return file_exists('/var/run/docker.sock') || is_executable('/usr/bin/docker');
    }

    /**
     * Check if LXD is installed
     */
    private function isLxdInstalled(): bool
    {
        // Check for LXD Unix socket
        return file_exists('/var/snap/lxd/common/lxd/unix.socket');
    }

    private function createDomain(array $input): array
    {
        $domain = $input['domain'] ?? '';
        $root = $input['root'] ?? '/var/www/' . $domain;
        $php = $input['php'] ?? true;
        $ssl = $input['ssl'] ?? true;
        $ownerUsername = $input['owner_username'] ?? null;
        $ownerFullname = $input['owner_fullname'] ?? null;
        $proxyType = $input['proxy_type'] ?? 'none';
        $proxyTarget = $input['proxy_target'] ?? null;
        $proxyContainerName = $input['proxy_container_name'] ?? null;

        if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) {
            return ['success' => false, 'error' => 'Invalid domain name'];
        }

        // For proxied domains, we don't need document root
        if ($proxyType === 'none') {
            // Create document root
            if (!is_dir($root)) {
                mkdir($root, 0755, true);
            }
        }

        // Generate Caddy config
        $config = $this->generateCaddyConfig($domain, $root, $php, $ssl, $proxyType, $proxyTarget);
        $configFile = "/etc/caddy/sites-available/{$domain}.caddy";
        
        // Write directly (www-data has group write permission now)
        if (file_put_contents($configFile, $config) === false) {
            return ['success' => false, 'error' => 'Failed to write config'];
        }

        // Enable site (create symlink)
        $enabledFile = "/etc/caddy/sites-enabled/{$domain}.caddy";
        if (!file_exists($enabledFile)) {
            if (!symlink($configFile, $enabledFile)) {
                return ['success' => false, 'error' => 'Failed to create symlink'];
            }
        }

        // Save to database using Medoo
        try {
            // Check if exists first
            $exists = $this->db->has('virtual_hosts', ['domain' => $domain]);
            $data = [
                'domain' => $domain,
                'document_root' => $proxyType === 'none' ? $root : null,
                'owner_username' => $ownerUsername,
                'owner_fullname' => $ownerFullname,
                'proxy_type' => $proxyType,
                'proxy_target' => $proxyTarget,
                'proxy_container_name' => $proxyContainerName,
                'enable_php' => $php ? 1 : 0,
                'enable_ssl' => $ssl ? 1 : 0,
                'is_enabled' => 1
            ];
            
            if ($exists) {
                $this->db->update('virtual_hosts', $data, ['domain' => $domain]);
            } else {
                $this->db->insert('virtual_hosts', $data);
            }
        } catch (\Exception $e) {
            // Table might not exist yet, but config file was created
            error_log("Failed to save domain to database: " . $e->getMessage());
        }

        // Reload Caddy
        $this->runCommand('sudo systemctl reload caddy');

        // Auto-create DNS zone and A record if zone doesn't exist
        $dnsResult = $this->autoCreateDnsForDomain($domain);
        if (!$dnsResult['success']) {
            error_log("DNS auto-create warning: " . ($dnsResult['error'] ?? 'unknown'));
        }

        $message = "Domain {$domain} created";
        if ($dnsResult['success'] && $dnsResult['created']) {
            $message .= " with DNS records";
        }

        return ['success' => true, 'message' => $message, 'dns' => $dnsResult];
    }

    /**
     * Auto-create DNS zone and A record for a domain
     */
    private function autoCreateDnsForDomain(string $domain): array
    {
        try {
            // Get the server's public IP
            $serverIp = $this->getServerPublicIp();
            if (!$serverIp) {
                return ['success' => false, 'error' => 'Could not determine server IP', 'created' => false];
            }

            // Check if zone already exists
            $existingZone = $this->db->get('dns_zones', 'id', ['name' => $domain]);
            
            if (!$existingZone) {
                // Create the zone
                $zoneResult = $this->createDnsZone(['zone' => $domain, 'type' => 'NATIVE']);
                if (!$zoneResult['success']) {
                    return ['success' => false, 'error' => $zoneResult['error'] ?? 'Failed to create zone', 'created' => false];
                }
            }

            // Get the zone ID
            $zoneId = $this->db->get('dns_zones', 'id', ['name' => $domain]);
            
            // Check if A record for root domain already exists
            $existingA = $this->db->get('dns_records', 'id', [
                'zone_id' => $zoneId,
                'name' => $domain,
                'type' => 'A'
            ]);

            if (!$existingA) {
                // Add A record for root domain
                $this->db->insert('dns_records', [
                    'zone_id' => $zoneId,
                    'name' => $domain,
                    'type' => 'A',
                    'content' => $serverIp,
                    'ttl' => 3600
                ]);

                // Add www subdomain A record
                $this->db->insert('dns_records', [
                    'zone_id' => $zoneId,
                    'name' => "www.{$domain}",
                    'type' => 'A',
                    'content' => $serverIp,
                    'ttl' => 3600
                ]);
            }

            // Sync to PowerDNS
            $this->syncZoneToPowerDNS($domain);

            return ['success' => true, 'created' => !$existingZone, 'ip' => $serverIp];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'created' => false];
        }
    }

    /**
     * Get server's public IP address
     */
    private function getServerPublicIp(): ?string
    {
        // Try to get from environment first
        $ip = $_ENV['SERVER_IP'] ?? null;
        if ($ip) return $ip;

        // Try to get from hostname
        $output = shell_exec('hostname -I 2>/dev/null');
        if ($output) {
            $ips = explode(' ', trim($output));
            foreach ($ips as $ip) {
                $ip = trim($ip);
                // Skip private IPs (10.x, 192.168.x, 172.16-31.x)
                if (!preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $ip) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return $ip;
                }
            }
            // If no public IP found, return first IPv4
            foreach ($ips as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return $ip;
                }
            }
        }

        // Fallback: try external service
        $ip = @file_get_contents('https://api.ipify.org?format=text');
        if ($ip && filter_var(trim($ip), FILTER_VALIDATE_IP)) {
            return trim($ip);
        }

        return null;
    }

    private function generateCaddyConfig(string $domain, string $root, bool $php, bool $ssl, string $proxyType = 'none', ?string $proxyTarget = null): string
    {
        $config = "{$domain} {\n";
        
        if ($proxyType !== 'none' && $proxyTarget) {
            // Reverse proxy configuration
            $target = $proxyTarget;
            if (!preg_match('/^https?:\/\//', $target)) {
                $target = "http://{$target}";
            }
            $config .= "    reverse_proxy {$target}\n";
        } else {
            // Standard file server configuration
            $config .= "    root * {$root}\n";
            
            if ($php) {
                $config .= "    php_fastcgi unix/run/php/php8.3-fpm.sock\n";
            }
            
            $config .= "    file_server\n";
        }
        
        $config .= "    encode gzip\n";
        $config .= "}\n";

        return $config;
    }

    private function hasSSL(string $domain): bool
    {
        $certDir = '/var/lib/caddy/.local/share/caddy/certificates';
        return is_dir("{$certDir}/acme-v02.api.letsencrypt.org-directory/{$domain}");
    }

    private function getDomainRoot(string $domain): string
    {
        return "/var/www/{$domain}";
    }

    private function getDomainDetails(string $domain): array
    {
        return [
            'name' => $domain,
            'root' => $this->getDomainRoot($domain),
            'ssl' => $this->hasSSL($domain),
            'enabled' => true
        ];
    }

    private function enableDomain(string $domain): array
    {
        $available = "/etc/caddy/sites-available/{$domain}.caddy";
        $enabled = "/etc/caddy/sites-enabled/{$domain}.caddy";
        if (file_exists($available) && !file_exists($enabled)) {
            symlink($available, $enabled);
            $this->runCommand('sudo systemctl reload caddy');
            return ['success' => true, 'message' => 'Domain enabled'];
        }
        return ['success' => false, 'error' => 'Could not enable domain'];
    }

    private function disableDomain(string $domain): array
    {
        $enabled = "/etc/caddy/sites-enabled/{$domain}.caddy";
        if (file_exists($enabled)) {
            unlink($enabled);
            $this->runCommand('sudo systemctl reload caddy');
            return ['success' => true, 'message' => 'Domain disabled'];
        }
        return ['success' => false, 'error' => 'Domain not found'];
    }

    private function deleteDomain(string $domain): array
    {
        $available = "/etc/caddy/sites-available/{$domain}.caddy";
        $enabled = "/etc/caddy/sites-enabled/{$domain}.caddy";
        
        if (file_exists($enabled)) unlink($enabled);
        if (file_exists($available)) unlink($available);
        
        // Also remove from database using Medoo
        try {
            $this->db->delete('virtual_hosts', ['domain' => $domain]);
        } catch (\Exception $e) {
            // Table might not exist yet
        }
        
        $this->runCommand('sudo systemctl reload caddy');
        return ['success' => true, 'message' => 'Domain deleted'];
    }

    private function requestSSL(string $domain): array
    {
        // Caddy handles SSL automatically, just ensure domain is configured with HTTPS
        return ['success' => true, 'message' => 'SSL is automatically managed by Caddy'];
    }

    private function renewSSL(string $domain): array
    {
        return ['success' => true, 'message' => 'SSL auto-renewed by Caddy'];
    }

    private function revokeSSL(string $domain): array
    {
        return ['success' => false, 'error' => 'Manual revocation not supported'];
    }

    private function listSSLCerts(): array
    {
        $certs = [];
        $domains = $this->listDomains();
        foreach ($domains as $d) {
            if ($d['ssl']) {
                $certs[] = [
                    'domain' => $d['name'],
                    'issuer' => "Let's Encrypt",
                    'auto_renew' => true,
                    'status' => 'active'
                ];
            }
        }
        return $certs;
    }

    // ========== DNS ZONE MANAGEMENT ==========

    private function listDnsZones(): array
    {
        try {
            $pdo = $this->db->pdo;
            $zones = $pdo->query("SELECT z.*, 
                (SELECT COUNT(*) FROM dns_records WHERE zone_id = z.id) as record_count
                FROM dns_zones z ORDER BY z.name")->fetchAll(\PDO::FETCH_ASSOC);
            return $zones ?: [];
        } catch (\Exception $e) {
            // Table might not exist yet
            return [];
        }
    }

    private function getDnsRecords(string $zone): array
    {
        try {
            $pdo = $this->db->pdo;
            $stmt = $pdo->prepare("SELECT r.* FROM dns_records r 
                JOIN dns_zones z ON r.zone_id = z.id 
                WHERE z.name = ? ORDER BY r.type, r.name");
            $stmt->execute([$zone]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function manageDnsRecord(array $input): array
    {
        $action = $input['action'] ?? '';
        
        try {
            switch ($action) {
                case 'create_zone':
                    return $this->createDnsZone($input);
                case 'delete_zone':
                    return $this->deleteDnsZone($input);
                case 'add_record':
                    return $this->addDnsRecord($input);
                case 'update_record':
                    return $this->updateDnsRecord($input);
                case 'delete_record':
                    return $this->deleteDnsRecordById($input);
                case 'get_soa_defaults':
                    return $this->getSoaDefaults();
                case 'update_soa_defaults':
                    return $this->updateSoaDefaults($input);
                case 'sync_powerdns':
                    return $this->syncWithPowerDNS();
                default:
                    return ['success' => false, 'error' => 'Unknown action'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function createDnsZone(array $input): array
    {
        $name = strtolower(trim($input['zone'] ?? ''));
        $type = $input['type'] ?? 'NATIVE';
        
        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}$/i', $name)) {
            return ['success' => false, 'error' => 'Invalid zone name'];
        }

        // Check if zone exists
        $existing = $this->db->get('dns_zones', 'id', ['name' => $name]);
        if ($existing) {
            return ['success' => false, 'error' => 'Zone already exists'];
        }

        // Create zone
        $this->db->insert('dns_zones', ['name' => $name, 'type' => $type]);
        $zoneId = $this->db->id();

        // Get SOA defaults
        $soa = $this->getSoaDefaults()['defaults'] ?? [];
        $serial = date('Ymd') . '01';
        
        // Add default SOA record
        $soaContent = sprintf("%s %s %s %d %d %d %d",
            $soa['primary_ns'] ?? 'ns1.ginto.ai',
            str_replace('@', '.', $soa['admin_email'] ?? 'admin.ginto.ai'),
            $serial,
            $soa['refresh'] ?? 10800,
            $soa['retry'] ?? 3600,
            $soa['expire'] ?? 604800,
            $soa['minimum_ttl'] ?? 3600
        );
        
        $this->db->insert('dns_records', [
            'zone_id' => $zoneId,
            'name' => $name,
            'type' => 'SOA',
            'content' => $soaContent,
            'ttl' => $soa['default_ttl'] ?? 3600
        ]);

        // Add default NS records
        $this->db->insert('dns_records', [
            'zone_id' => $zoneId,
            'name' => $name,
            'type' => 'NS',
            'content' => $soa['primary_ns'] ?? 'ns1.ginto.ai',
            'ttl' => 86400
        ]);

        // Sync to PowerDNS if available
        $this->syncZoneToPowerDNS($name);

        return ['success' => true, 'message' => "Zone {$name} created", 'zone_id' => $zoneId];
    }

    private function deleteDnsZone(array $input): array
    {
        $name = strtolower(trim($input['zone'] ?? ''));
        
        $zone = $this->db->get('dns_zones', 'id', ['name' => $name]);
        if (!$zone) {
            return ['success' => false, 'error' => 'Zone not found'];
        }
        
        // Delete records first (foreign key)
        $this->db->delete('dns_records', ['zone_id' => $zone]);
        $this->db->delete('dns_zones', ['name' => $name]);

        // Remove from PowerDNS if available
        $this->deleteZoneFromPowerDNS($name);

        return ['success' => true, 'message' => "Zone {$name} deleted"];
    }

    private function addDnsRecord(array $input): array
    {
        $zone = strtolower(trim($input['zone'] ?? ''));
        $name = strtolower(trim($input['name'] ?? ''));
        $type = strtoupper(trim($input['type'] ?? ''));
        $content = trim($input['content'] ?? '');
        $ttl = (int)($input['ttl'] ?? 3600);
        $priority = (int)($input['priority'] ?? 0);

        // Validate
        $validTypes = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA', 'PTR'];
        if (!in_array($type, $validTypes)) {
            return ['success' => false, 'error' => 'Invalid record type'];
        }

        // Get zone ID
        $zoneRow = $this->db->get('dns_zones', ['id'], ['name' => $zone]);
        if (!$zoneRow) {
            return ['success' => false, 'error' => 'Zone not found'];
        }

        // Normalize name (append zone if needed)
        if ($name === '@' || $name === '') {
            $name = $zone;
        } elseif (!str_ends_with($name, '.' . $zone) && $name !== $zone) {
            $name = $name . '.' . $zone;
        }

        // Validate content based on type
        switch ($type) {
            case 'A':
                if (!filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return ['success' => false, 'error' => 'Invalid IPv4 address'];
                }
                break;
            case 'AAAA':
                if (!filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    return ['success' => false, 'error' => 'Invalid IPv6 address'];
                }
                break;
            case 'MX':
                if ($priority < 0 || $priority > 65535) {
                    return ['success' => false, 'error' => 'Invalid MX priority'];
                }
                break;
        }

        $this->db->insert('dns_records', [
            'zone_id' => $zoneRow['id'],
            'name' => $name,
            'type' => $type,
            'content' => $content,
            'ttl' => $ttl,
            'priority' => $priority
        ]);
        $recordId = $this->db->id();

        // Update SOA serial
        $this->incrementSoaSerial($zone);

        // Sync to PowerDNS
        $this->syncZoneToPowerDNS($zone);

        return ['success' => true, 'message' => "{$type} record added", 'id' => $recordId];
    }

    private function updateDnsRecord(array $input): array
    {
        $id = (int)($input['id'] ?? 0);
        $content = trim($input['content'] ?? '');
        $ttl = (int)($input['ttl'] ?? 3600);
        $priority = (int)($input['priority'] ?? 0);
        $disabled = (bool)($input['disabled'] ?? false);

        if (!$id) {
            return ['success' => false, 'error' => 'Record ID required'];
        }

        // Get zone name for syncing using raw PDO for JOIN
        $pdo = $this->db->pdo;
        $stmt = $pdo->prepare("SELECT z.name as zone FROM dns_records r JOIN dns_zones z ON r.zone_id = z.id WHERE r.id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return ['success' => false, 'error' => 'Record not found'];
        }

        $this->db->update('dns_records', [
            'content' => $content,
            'ttl' => $ttl,
            'priority' => $priority,
            'disabled' => $disabled ? 1 : 0
        ], ['id' => $id]);

        // Update SOA serial
        $this->incrementSoaSerial($row['zone']);

        // Sync to PowerDNS
        $this->syncZoneToPowerDNS($row['zone']);

        return ['success' => true, 'message' => 'Record updated'];
    }

    private function deleteDnsRecordById(array $input): array
    {
        $id = (int)($input['id'] ?? 0);
        
        if (!$id) {
            return ['success' => false, 'error' => 'Record ID required'];
        }

        // Get zone name for syncing using raw PDO for JOIN
        $pdo = $this->db->pdo;
        $stmt = $pdo->prepare("SELECT z.name as zone, r.type FROM dns_records r JOIN dns_zones z ON r.zone_id = z.id WHERE r.id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return ['success' => false, 'error' => 'Record not found'];
        }

        // Don't allow deleting SOA
        if ($row['type'] === 'SOA') {
            return ['success' => false, 'error' => 'Cannot delete SOA record'];
        }

        $this->db->delete('dns_records', ['id' => $id]);

        // Update SOA serial
        $this->incrementSoaSerial($row['zone']);

        // Sync to PowerDNS
        $this->syncZoneToPowerDNS($row['zone']);

        return ['success' => true, 'message' => 'Record deleted'];
    }

    private function incrementSoaSerial(string $zone): void
    {
        // Get current SOA using raw PDO for JOIN
        $pdo = $this->db->pdo;
        $stmt = $pdo->prepare("SELECT r.id, r.content FROM dns_records r 
            JOIN dns_zones z ON r.zone_id = z.id 
            WHERE z.name = ? AND r.type = 'SOA'");
        $stmt->execute([$zone]);
        $soa = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($soa) {
            // Parse and increment serial
            $parts = preg_split('/\s+/', $soa['content']);
            if (count($parts) >= 3) {
                $oldSerial = (int)$parts[2];
                $todaySerial = (int)(date('Ymd') . '00');
                $newSerial = max($oldSerial + 1, $todaySerial + 1);
                $parts[2] = $newSerial;
                $newContent = implode(' ', $parts);
                
                $this->db->update('dns_records', ['content' => $newContent], ['id' => $soa['id']]);
            }
        }
    }

    private function getSoaDefaults(): array
    {
        try {
            $defaults = $this->db->get('dns_soa_defaults', '*', ['id' => 1]);
            return ['success' => true, 'defaults' => $defaults ?: []];
        } catch (\Exception $e) {
            return ['success' => true, 'defaults' => [
                'primary_ns' => 'ns1.ginto.ai',
                'admin_email' => 'admin.ginto.ai',
                'refresh' => 10800,
                'retry' => 3600,
                'expire' => 604800,
                'minimum_ttl' => 3600,
                'default_ttl' => 3600
            ]];
        }
    }

    private function updateSoaDefaults(array $input): array
    {
        $this->db->update('dns_soa_defaults', [
            'primary_ns' => $input['primary_ns'] ?? 'ns1.ginto.ai',
            'admin_email' => $input['admin_email'] ?? 'admin.ginto.ai',
            'refresh' => (int)($input['refresh'] ?? 10800),
            'retry' => (int)($input['retry'] ?? 3600),
            'expire' => (int)($input['expire'] ?? 604800),
            'minimum_ttl' => (int)($input['minimum_ttl'] ?? 3600),
            'default_ttl' => (int)($input['default_ttl'] ?? 3600)
        ], ['id' => 1]);
        return ['success' => true, 'message' => 'SOA defaults updated'];
    }

    // ========== POWERDNS INTEGRATION ==========

    private function getPowerDNSConfig(): ?array
    {
        $apiKey = $_ENV['POWERDNS_API_KEY'] ?? getenv('POWERDNS_API_KEY') ?: null;
        $apiUrl = $_ENV['POWERDNS_API_URL'] ?? getenv('POWERDNS_API_URL') ?: 'http://127.0.0.1:8081/api/v1';
        
        if (!$apiKey) return null;
        return ['url' => $apiUrl, 'key' => $apiKey];
    }

    private function syncZoneToPowerDNS(string $zone): bool|string
    {
        $config = $this->getPowerDNSConfig();
        if (!$config) return 'PowerDNS not configured';

        $records = $this->getDnsRecords($zone);
        if (empty($records)) {
            return 'No records found for zone';
        }

        // Group records by name+type for RRsets
        $rrsets = [];
        foreach ($records as $r) {
            $key = $r['name'] . '|' . $r['type'];
            if (!isset($rrsets[$key])) {
                $rrsets[$key] = [
                    'name' => $r['name'] . '.',
                    'type' => $r['type'],
                    'ttl' => (int)$r['ttl'],
                    'changetype' => 'REPLACE',
                    'records' => []
                ];
            }
            $content = $r['content'];
            
            // Handle SOA record - add trailing dots to MNAME and RNAME
            if ($r['type'] === 'SOA') {
                $parts = preg_split('/\s+/', $content);
                if (count($parts) >= 7) {
                    // MNAME (primary NS) - add trailing dot
                    if (!str_ends_with($parts[0], '.')) $parts[0] .= '.';
                    // RNAME (admin email) - add trailing dot
                    if (!str_ends_with($parts[1], '.')) $parts[1] .= '.';
                    $content = implode(' ', $parts);
                }
            }
            
            if ($r['type'] === 'MX') {
                $content = $r['priority'] . ' ' . $content;
            }
            if (!str_ends_with($content, '.') && in_array($r['type'], ['NS', 'CNAME', 'MX', 'PTR'])) {
                $content .= '.';
            }
            $rrsets[$key]['records'][] = [
                'content' => $content,
                'disabled' => (bool)($r['disabled'] ?? false)
            ];
        }

        $payload = ['rrsets' => array_values($rrsets)];

        // Check if zone exists in PowerDNS
        $ch = curl_init($config['url'] . "/servers/localhost/zones/{$zone}.");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['X-API-Key: ' . $config['key']]
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 404) {
            // Create zone
            $createPayload = [
                'name' => $zone . '.',
                'kind' => 'Native',
                'nameservers' => [],
                'rrsets' => array_values($rrsets)
            ];
            $ch = curl_init($config['url'] . "/servers/localhost/zones");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($createPayload),
                CURLOPT_HTTPHEADER => [
                    'X-API-Key: ' . $config['key'],
                    'Content-Type: application/json'
                ]
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode >= 300) {
                $error = json_decode($response, true);
                return 'Create failed: ' . ($error['error'] ?? $response);
            }
            return true;
        }

        // Update zone
        $ch = curl_init($config['url'] . "/servers/localhost/zones/{$zone}.");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $config['key'],
                'Content-Type: application/json'
            ]
        ]);
        $response = curl_exec($ch);
        $success = curl_getinfo($ch, CURLINFO_HTTP_CODE) < 300;
        curl_close($ch);
        
        return $success;
    }

    private function deleteZoneFromPowerDNS(string $zone): bool
    {
        $config = $this->getPowerDNSConfig();
        if (!$config) return false;

        $ch = curl_init($config['url'] . "/servers/localhost/zones/{$zone}.");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => ['X-API-Key: ' . $config['key']]
        ]);
        curl_exec($ch);
        $success = curl_getinfo($ch, CURLINFO_HTTP_CODE) < 300;
        curl_close($ch);
        
        return $success;
    }

    private function syncWithPowerDNS(): array
    {
        $config = $this->getPowerDNSConfig();
        if (!$config) {
            return ['success' => false, 'error' => 'PowerDNS not configured. Set POWERDNS_API_KEY and POWERDNS_API_URL environment variables.'];
        }

        $zones = $this->listDnsZones();
        if (empty($zones)) {
            return ['success' => false, 'error' => 'No DNS zones to sync'];
        }
        
        $synced = 0;
        $failed = 0;
        $errors = [];

        foreach ($zones as $zone) {
            $result = $this->syncZoneToPowerDNS($zone['name']);
            if ($result === true) {
                $synced++;
            } else {
                $failed++;
                if (is_string($result)) {
                    $errors[] = "{$zone['name']}: {$result}";
                }
            }
        }

        if ($failed > 0 && $synced === 0) {
            return [
                'success' => false,
                'error' => "Sync failed: " . implode('; ', $errors)
            ];
        }

        return [
            'success' => $failed === 0,
            'message' => "Synced {$synced} zones to PowerDNS" . ($failed > 0 ? ", {$failed} failed: " . implode('; ', $errors) : "")
        ];
    }

    private function listEmailAccounts(): array
    {
        return [];
    }

    private function manageEmailAccount(array $input): array
    {
        return ['success' => false, 'error' => 'Email management not configured'];
    }

    private function listDatabases(): array
    {
        try {
            $result = $this->db->query("SHOW DATABASES")->fetchAll();
            $systemDbs = ['information_schema', 'mysql', 'performance_schema', 'sys'];
            $dbs = [];
            foreach ($result as $r) {
                $dbName = $r['Database'];
                if (!in_array($dbName, $systemDbs)) {
                    $dbs[] = ['name' => $dbName];
                }
            }
            return $dbs;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function listDatabaseUsers(): array
    {
        try {
            $result = $this->db->query("SELECT User, Host FROM mysql.user WHERE User NOT IN ('root', 'mysql.sys', 'mysql.session', 'mysql.infoschema', 'mariadb.sys')")->fetchAll();
            return $result ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function manageDatabase(array $input): array
    {
        $action = $input['action'] ?? '';
        $dbName = $input['database'] ?? '';
        $user = $input['user'] ?? '';
        $password = $input['password'] ?? '';

        if (!preg_match('/^[a-z0-9_]+$/i', $dbName)) {
            return ['success' => false, 'error' => 'Invalid database name'];
        }

        try {
            switch ($action) {
                case 'create_db':
                    $this->db->query("CREATE DATABASE IF NOT EXISTS `{$dbName}`");
                    return ['success' => true, 'message' => "Database {$dbName} created"];
                case 'drop_db':
                    $this->db->query("DROP DATABASE IF EXISTS `{$dbName}`");
                    return ['success' => true, 'message' => "Database {$dbName} deleted"];
                case 'create_user':
                    if (!preg_match('/^[a-z0-9_]+$/i', $user)) {
                        return ['success' => false, 'error' => 'Invalid username'];
                    }
                    $this->db->query("CREATE USER IF NOT EXISTS '{$user}'@'localhost' IDENTIFIED BY " . $this->db->quote($password));
                    $this->db->query("GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$user}'@'localhost'");
                    $this->db->query("FLUSH PRIVILEGES");
                    return ['success' => true, 'message' => "User {$user} created with access to {$dbName}"];
                default:
                    return ['success' => false, 'error' => 'Unknown action'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function listFtpAccounts(): array
    {
        return [];
    }

    private function manageFtpAccount(array $input): array
    {
        return ['success' => false, 'error' => 'FTP management not configured'];
    }

    private function listBackups(): array
    {
        $backupDir = getenv('BACKUP_DIR') ?: '/var/backups/ginto';
        if (!is_dir($backupDir)) return [];
        
        $backups = [];
        foreach (glob("{$backupDir}/*.tar.gz") as $file) {
            $backups[] = [
                'name' => basename($file),
                'size' => $this->formatBytes(filesize($file)),
                'date' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
        return $backups;
    }

    private function listBackupSchedules(): array
    {
        return [];
    }

    private function createBackup(array $input): array
    {
        $type = $input['type'] ?? 'full';
        $backupDir = getenv('BACKUP_DIR') ?: '/var/backups/ginto';
        
        if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
        
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "{$backupDir}/backup_{$type}_{$timestamp}.tar.gz";

        // Create backup in background
        $cmd = "tar -czf " . escapeshellarg($filename) . " /var/www /etc/caddy 2>&1 &";
        shell_exec($cmd);

        return ['success' => true, 'message' => 'Backup started', 'file' => basename($filename)];
    }

    private function restoreBackup(array $input): array
    {
        return ['success' => false, 'error' => 'Restore not implemented'];
    }

    private function deleteBackup(array $input): array
    {
        $file = $input['file'] ?? '';
        $backupDir = getenv('BACKUP_DIR') ?: '/var/backups/ginto';
        $path = "{$backupDir}/{$file}";
        
        if (file_exists($path) && str_ends_with($file, '.tar.gz')) {
            unlink($path);
            return ['success' => true, 'message' => 'Backup deleted'];
        }
        return ['success' => false, 'error' => 'Backup not found'];
    }

    private function scheduleBackup(array $input): array
    {
        return ['success' => false, 'error' => 'Scheduling not implemented'];
    }

    private function listFirewallRules(): array
    {
        $result = $this->runCommand('sudo ufw status numbered 2>/dev/null');
        if ($result['code'] !== 0) return [];
        
        $rules = [];
        $lines = explode("\n", $result['output']);
        foreach ($lines as $line) {
            if (preg_match('/\[\s*(\d+)\]\s+(\S+)\s+(\S+)\s+(.*)/', $line, $m)) {
                $rules[] = [
                    'number' => $m[1],
                    'to' => $m[2],
                    'action' => $m[3],
                    'from' => trim($m[4])
                ];
            }
        }
        return $rules;
    }

    private function getFail2banStatus(): array
    {
        $result = $this->runCommand('sudo fail2ban-client status 2>/dev/null');
        if ($result['code'] !== 0) return ['active' => false];
        
        $jails = [];
        if (preg_match('/Jail list:\s+(.+)/', $result['output'], $m)) {
            $jails = array_map('trim', explode(',', $m[1]));
        }
        
        return ['active' => true, 'jails' => $jails];
    }

    private function manageFirewallRule(array $input): array
    {
        $action = $input['action'] ?? '';
        $rule = $input['rule'] ?? '';
        $port = $input['port'] ?? '';
        $ip = $input['ip'] ?? '';

        switch ($action) {
            case 'allow':
                if ($port) {
                    $result = $this->runCommand("sudo ufw allow " . escapeshellarg($port));
                } elseif ($ip) {
                    $result = $this->runCommand("sudo ufw allow from " . escapeshellarg($ip));
                } else {
                    return ['success' => false, 'error' => 'Port or IP required'];
                }
                break;
            case 'deny':
                if ($port) {
                    $result = $this->runCommand("sudo ufw deny " . escapeshellarg($port));
                } elseif ($ip) {
                    $result = $this->runCommand("sudo ufw deny from " . escapeshellarg($ip));
                } else {
                    return ['success' => false, 'error' => 'Port or IP required'];
                }
                break;
            case 'delete':
                if (!is_numeric($rule)) {
                    return ['success' => false, 'error' => 'Invalid rule number'];
                }
                $result = $this->runCommand("sudo ufw --force delete " . escapeshellarg($rule));
                break;
            default:
                return ['success' => false, 'error' => 'Unknown action'];
        }

        return ['success' => $result['code'] === 0, 'message' => $result['output']];
    }
}
