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
        $caddyDir = '/etc/caddy/sites-enabled';
        if (!is_dir($caddyDir)) $caddyDir = '/etc/caddy';
        
        // Parse Caddyfile for domains
        $caddyFile = '/etc/caddy/Caddyfile';
        if (file_exists($caddyFile)) {
            $content = file_get_contents($caddyFile);
            preg_match_all('/^([a-z0-9.-]+)\s*\{/im', $content, $matches);
            foreach ($matches[1] as $domain) {
                if ($domain !== 'localhost' && !str_starts_with($domain, ':')) {
                    $domains[] = [
                        'name' => $domain,
                        'enabled' => true,
                        'ssl' => $this->hasSSL($domain),
                        'root' => $this->getDomainRoot($domain)
                    ];
                }
            }
        }

        return $domains;
    }

    private function createDomain(array $input): array
    {
        $domain = $input['domain'] ?? '';
        $root = $input['root'] ?? '/var/www/' . $domain;
        $php = $input['php'] ?? true;
        $ssl = $input['ssl'] ?? true;

        if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) {
            return ['success' => false, 'error' => 'Invalid domain name'];
        }

        // Create document root
        if (!is_dir($root)) {
            mkdir($root, 0755, true);
        }

        // Create Caddy config
        $config = $this->generateCaddyConfig($domain, $root, $php, $ssl);
        $configFile = "/etc/caddy/sites-available/{$domain}.caddy";
        
        if (file_put_contents($configFile, $config) === false) {
            return ['success' => false, 'error' => 'Failed to write config'];
        }

        // Enable site
        $enabledFile = "/etc/caddy/sites-enabled/{$domain}.caddy";
        if (!file_exists($enabledFile)) {
            symlink($configFile, $enabledFile);
        }

        // Reload Caddy
        $this->runCommand('sudo systemctl reload caddy');

        return ['success' => true, 'message' => "Domain {$domain} created"];
    }

    private function generateCaddyConfig(string $domain, string $root, bool $php, bool $ssl): string
    {
        $config = "{$domain} {\n";
        $config .= "    root * {$root}\n";
        
        if ($php) {
            $config .= "    php_fastcgi unix//run/php/php8.3-fpm.sock\n";
        }
        
        $config .= "    file_server\n";
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
            $zones = $this->db->query("SELECT z.*, 
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
            $stmt = $this->db->prepare("SELECT r.* FROM dns_records r 
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
        $stmt = $this->db->prepare("SELECT id FROM dns_zones WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Zone already exists'];
        }

        // Create zone
        $stmt = $this->db->prepare("INSERT INTO dns_zones (name, type) VALUES (?, ?)");
        $stmt->execute([$name, $type]);
        $zoneId = $this->db->lastInsertId();

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
        
        $stmt = $this->db->prepare("INSERT INTO dns_records (zone_id, name, type, content, ttl) VALUES (?, ?, 'SOA', ?, ?)");
        $stmt->execute([$zoneId, $name, $soaContent, $soa['default_ttl'] ?? 3600]);

        // Add default NS records
        $stmt = $this->db->prepare("INSERT INTO dns_records (zone_id, name, type, content, ttl) VALUES (?, ?, 'NS', ?, ?)");
        $stmt->execute([$zoneId, $name, $soa['primary_ns'] ?? 'ns1.ginto.ai', 86400]);

        // Sync to PowerDNS if available
        $this->syncZoneToPowerDNS($name);

        return ['success' => true, 'message' => "Zone {$name} created", 'zone_id' => $zoneId];
    }

    private function deleteDnsZone(array $input): array
    {
        $name = strtolower(trim($input['zone'] ?? ''));
        
        $stmt = $this->db->prepare("DELETE FROM dns_zones WHERE name = ?");
        $stmt->execute([$name]);
        
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'error' => 'Zone not found'];
        }

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
        $stmt = $this->db->prepare("SELECT id FROM dns_zones WHERE name = ?");
        $stmt->execute([$zone]);
        $zoneRow = $stmt->fetch(\PDO::FETCH_ASSOC);
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

        $stmt = $this->db->prepare("INSERT INTO dns_records (zone_id, name, type, content, ttl, priority) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$zoneRow['id'], $name, $type, $content, $ttl, $priority]);

        // Update SOA serial
        $this->incrementSoaSerial($zone);

        // Sync to PowerDNS
        $this->syncZoneToPowerDNS($zone);

        return ['success' => true, 'message' => "{$type} record added", 'id' => $this->db->lastInsertId()];
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

        // Get zone name for syncing
        $stmt = $this->db->prepare("SELECT z.name as zone FROM dns_records r JOIN dns_zones z ON r.zone_id = z.id WHERE r.id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return ['success' => false, 'error' => 'Record not found'];
        }

        $stmt = $this->db->prepare("UPDATE dns_records SET content = ?, ttl = ?, priority = ?, disabled = ? WHERE id = ?");
        $stmt->execute([$content, $ttl, $priority, $disabled, $id]);

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

        // Get zone name for syncing
        $stmt = $this->db->prepare("SELECT z.name as zone, r.type FROM dns_records r JOIN dns_zones z ON r.zone_id = z.id WHERE r.id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return ['success' => false, 'error' => 'Record not found'];
        }

        // Don't allow deleting SOA
        if ($row['type'] === 'SOA') {
            return ['success' => false, 'error' => 'Cannot delete SOA record'];
        }

        $stmt = $this->db->prepare("DELETE FROM dns_records WHERE id = ?");
        $stmt->execute([$id]);

        // Update SOA serial
        $this->incrementSoaSerial($row['zone']);

        // Sync to PowerDNS
        $this->syncZoneToPowerDNS($row['zone']);

        return ['success' => true, 'message' => 'Record deleted'];
    }

    private function incrementSoaSerial(string $zone): void
    {
        // Get current SOA
        $stmt = $this->db->prepare("SELECT r.id, r.content FROM dns_records r 
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
                
                $stmt = $this->db->prepare("UPDATE dns_records SET content = ? WHERE id = ?");
                $stmt->execute([$newContent, $soa['id']]);
            }
        }
    }

    private function getSoaDefaults(): array
    {
        try {
            $stmt = $this->db->query("SELECT * FROM dns_soa_defaults LIMIT 1");
            $defaults = $stmt->fetch(\PDO::FETCH_ASSOC);
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
        $stmt = $this->db->prepare("UPDATE dns_soa_defaults SET 
            primary_ns = ?, admin_email = ?, refresh = ?, retry = ?, expire = ?, minimum_ttl = ?, default_ttl = ?
            WHERE id = 1");
        $stmt->execute([
            $input['primary_ns'] ?? 'ns1.ginto.ai',
            $input['admin_email'] ?? 'admin.ginto.ai',
            (int)($input['refresh'] ?? 10800),
            (int)($input['retry'] ?? 3600),
            (int)($input['expire'] ?? 604800),
            (int)($input['minimum_ttl'] ?? 3600),
            (int)($input['default_ttl'] ?? 3600)
        ]);
        return ['success' => true, 'message' => 'SOA defaults updated'];
    }

    // ========== POWERDNS INTEGRATION ==========

    private function getPowerDNSConfig(): ?array
    {
        $apiKey = getenv('POWERDNS_API_KEY');
        $apiUrl = getenv('POWERDNS_API_URL') ?: 'http://127.0.0.1:8081/api/v1';
        
        if (!$apiKey) return null;
        return ['url' => $apiUrl, 'key' => $apiKey];
    }

    private function syncZoneToPowerDNS(string $zone): bool
    {
        $config = $this->getPowerDNSConfig();
        if (!$config) return false;

        $records = $this->getDnsRecords($zone);
        if (empty($records)) return false;

        // Group records by name+type for RRsets
        $rrsets = [];
        foreach ($records as $r) {
            $key = $r['name'] . '|' . $r['type'];
            if (!isset($rrsets[$key])) {
                $rrsets[$key] = [
                    'name' => $r['name'] . '.',
                    'type' => $r['type'],
                    'ttl' => $r['ttl'],
                    'changetype' => 'REPLACE',
                    'records' => []
                ];
            }
            $content = $r['content'];
            if ($r['type'] === 'MX') {
                $content = $r['priority'] . ' ' . $content;
            }
            if (!str_ends_with($content, '.') && in_array($r['type'], ['NS', 'CNAME', 'MX', 'PTR'])) {
                $content .= '.';
            }
            $rrsets[$key]['records'][] = [
                'content' => $content,
                'disabled' => (bool)$r['disabled']
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
            $success = curl_getinfo($ch, CURLINFO_HTTP_CODE) < 300;
            curl_close($ch);
            return $success;
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
        $synced = 0;
        $failed = 0;

        foreach ($zones as $zone) {
            if ($this->syncZoneToPowerDNS($zone['name'])) {
                $synced++;
            } else {
                $failed++;
            }
        }

        return [
            'success' => true,
            'message' => "Synced {$synced} zones to PowerDNS" . ($failed > 0 ? ", {$failed} failed" : "")
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
