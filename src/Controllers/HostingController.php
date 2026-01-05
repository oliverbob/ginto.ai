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
        if (!is_dir($certDir)) return 0;
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($certDir));
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'crt' || $file->getExtension() === 'pem') $count++;
        }
        return $count;
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

    private function listDnsZones(): array
    {
        // Placeholder - would integrate with BIND/PowerDNS
        return [];
    }

    private function getDnsRecords(string $zone): array
    {
        return [];
    }

    private function manageDnsRecord(array $input): array
    {
        return ['success' => false, 'error' => 'DNS management not configured'];
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
