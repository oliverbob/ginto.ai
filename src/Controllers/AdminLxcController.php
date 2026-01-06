<?php
namespace Ginto\Controllers;

/**
 * Admin LXC Controller
 * Handles admin-only LXC container management routes
 */
class AdminLxcController
{
    protected $db;

    public function __construct($db = null)
    {
        if ($db === null) {
            $db = \Ginto\Core\Database::getInstance();
        }
        $this->db = $db;
        
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    /**
     * Check if user is admin
     */
    private function requireAdmin(): bool
    {
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            exit;
        }
        return true;
    }

    /**
     * Get LXC binary path
     */
    private function getLxcBin(): ?string
    {
        return getLxcBin();
    }

    /**
     * Admin LXC Manager View (Proxmox-style datacenter interface)
     */
    public function index(): void
    {
        if (empty($_SESSION['is_admin'])) {
            if (!headers_sent()) header('Location: /login');
            exit;
        }
        include dirname(__DIR__) . '/Views/admin/lxc/lxc.php';
        exit;
    }

    /**
     * List containers (GET) or Create container (POST)
     */
    public function containers(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireAdmin();
        
        // POST = Create container
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            // CSRF validation
            $token = $input['csrf_token'] ?? '';
            if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                exit;
            }
            
            $name = $input['name'] ?? '';
            $image = $input['image'] ?? '';
            $profile = $input['profile'] ?? 'default';
            $start = !empty($input['start']);
            
            if (!preg_match('/^[a-z0-9-]+$/', $name)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid container name (lowercase, numbers, hyphens only)']);
                exit;
            }
            
            if (empty($image)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Image is required']);
                exit;
            }
            
            try {
                $lxcBin = $this->getLxcBin();
                if (!$lxcBin) {
                    echo json_encode(['success' => false, 'error' => 'LXC/LXD not installed']);
                    exit;
                }
                
                $cmd = "sudo $lxcBin init " . escapeshellarg($image) . " " . escapeshellarg($name) . " -p " . escapeshellarg($profile) . " 2>&1";
                $result = shell_exec($cmd);
                
                if ($start) {
                    shell_exec("sudo $lxcBin start " . escapeshellarg($name) . " 2>&1");
                }
                
                // Verify creation
                $checkOutput = shell_exec("sudo $lxcBin list " . escapeshellarg($name) . " --format json 2>/dev/null");
                $exists = !empty(json_decode($checkOutput, true));
                
                if ($exists) {
                    echo json_encode(['success' => true, 'message' => "Container {$name} created", 'started' => $start]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to create container', 'output' => $result]);
                }
            } catch (\Throwable $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
        
        // GET = List containers
        try {
            $lxcBin = $this->getLxcBin();
            if (!$lxcBin) {
                echo json_encode(['success' => true, 'containers' => [], 'warning' => 'LXC/LXD not installed']);
                exit;
            }
            
            $output = shell_exec("sudo $lxcBin list --format json 2>&1");
            if (empty($output)) {
                echo json_encode(['success' => true, 'containers' => [], 'warning' => 'No output from lxc list']);
                exit;
            }
            
            $containers = json_decode($output, true);
            if ($containers === null) {
                echo json_encode(['success' => true, 'containers' => [], 'warning' => 'LXD returned: ' . substr($output, 0, 200)]);
                exit;
            }
            
            $result = [];
            foreach ($containers as $c) {
                $ipv4 = '-';
                if (!empty($c['state']['network'])) {
                    foreach ($c['state']['network'] as $iface => $net) {
                        if ($iface === 'lo') continue;
                        foreach ($net['addresses'] ?? [] as $addr) {
                            if ($addr['family'] === 'inet') {
                                $ipv4 = $addr['address'];
                                break 2;
                            }
                        }
                    }
                }
                
                $memory = '-';
                $disk = '-';
                if (!empty($c['state']['memory'])) {
                    $memBytes = $c['state']['memory']['usage'] ?? 0;
                    $memory = round($memBytes / 1024 / 1024) . ' MB';
                }
                
                // Get disk usage
                $diskBytes = $c['state']['disk']['root']['usage'] ?? 0;
                $diskTotal = $c['state']['disk']['root']['total'] ?? 0;
                if ($diskBytes > 0) {
                    $disk = round($diskBytes / 1024 / 1024) . ' MB';
                } elseif (($c['status'] ?? '') === 'Running') {
                    $dfOutput = shell_exec("sudo $lxcBin exec " . escapeshellarg($c['name']) . " -- df -B1 / 2>/dev/null | tail -1");
                    if (preg_match('/(\d+)\s+(\d+)\s+(\d+)\s+\d+%/', $dfOutput ?? '', $m)) {
                        $diskTotal = intval($m[1]);
                        $diskBytes = intval($m[2]);
                    }
                    if ($diskBytes > 0) {
                        $disk = round($diskBytes / 1024 / 1024) . ' MB';
                    }
                }
                
                $limits = [
                    'memory' => $c['config']['limits.memory'] ?? '-',
                    'cpu' => $c['config']['limits.cpu'] ?? '-',
                    'processes' => $c['config']['limits.processes'] ?? '-',
                    'disk' => '-',
                ];
                if (!empty($c['devices']['root']['size'])) {
                    $limits['disk'] = $c['devices']['root']['size'];
                } elseif ($diskTotal > 0) {
                    $limits['disk'] = round($diskTotal / 1024 / 1024) . 'MB';
                }
                
                // Lookup owner from client_sandboxes table
                $ownerInfo = $this->getContainerOwner($c['name']);
                
                $result[] = [
                    'name' => $c['name'],
                    'status' => $c['status'] ?? 'Unknown',
                    'type' => $c['type'] ?? 'container',
                    'ipv4' => $ipv4,
                    'memory' => $memory,
                    'disk' => $disk,
                    'limits' => $limits,
                    'architecture' => $c['architecture'] ?? null,
                    'created_at' => $c['created_at'] ?? null,
                    'last_used_at' => $c['last_used_at'] ?? null,
                    'owner_username' => $ownerInfo['username'] ?? null,
                    'owner_fullname' => $ownerInfo['fullname'] ?? null,
                    'owner_id' => $ownerInfo['user_id'] ?? null,
                ];
            }
            
            echo json_encode(['success' => true, 'containers' => $result]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage(), 'containers' => []]);
        }
        exit;
    }

    /**
     * Container actions (start/stop/restart/delete)
     */
    public function containerAction($name, $action): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        $this->requireAdmin();
        
        // CSRF validation
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $token = $input['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        if (empty($name) || !preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid container name']);
            exit;
        }
        
        $allowedActions = ['start', 'stop', 'restart', 'delete'];
        if (!in_array($action, $allowedActions)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
        }
        
        try {
            $lxcBin = $this->getLxcBin();
            if (!$lxcBin) {
                echo json_encode(['success' => false, 'error' => 'LXC/LXD not installed']);
                exit;
            }
            
            if ($action === 'delete') {
                shell_exec("sudo $lxcBin stop {$name} --force 2>/dev/null");
                $result = shell_exec("sudo $lxcBin delete {$name} --force 2>&1");
            } else {
                $result = shell_exec("sudo $lxcBin {$action} {$name} 2>&1");
            }
            
            $checkOutput = shell_exec("sudo $lxcBin list {$name} --format json 2>/dev/null");
            $exists = !empty(json_decode($checkOutput, true));
            
            if ($action === 'delete' && !$exists) {
                echo json_encode(['success' => true, 'message' => "Container {$name} deleted"]);
            } elseif ($action !== 'delete') {
                echo json_encode(['success' => true, 'message' => "Container {$name} {$action}ed"]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to delete container', 'output' => $result]);
            }
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Get single container details
     */
    public function containerDetails($params): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireAdmin();
        
        $name = $params['name'] ?? '';
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid container name']);
            exit;
        }
        
        try {
            $lxcBin = $this->getLxcBin();
            if (!$lxcBin) {
                echo json_encode(['success' => false, 'error' => 'LXC/LXD not installed']);
                exit;
            }
            
            $output = shell_exec("sudo $lxcBin config show {$name} --expanded 2>/dev/null");
            $config = [];
            if ($output) {
                foreach (explode("\n", $output) as $line) {
                    if (preg_match('/^(\s*)([^:]+):\s*(.*)$/', $line, $m)) {
                        $config[$m[2]] = $m[3];
                    }
                }
            }
            
            $infoOutput = shell_exec("sudo $lxcBin info {$name} 2>/dev/null");
            $info = [];
            if ($infoOutput) {
                foreach (explode("\n", $infoOutput) as $line) {
                    if (preg_match('/^([^:]+):\s*(.*)$/', $line, $m)) {
                        $info[trim($m[1])] = trim($m[2]);
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'name' => $name,
                'architecture' => $info['Architecture'] ?? null,
                'created_at' => $info['Created'] ?? null,
                'last_used_at' => $info['Last Used'] ?? null,
                'status' => $info['Status'] ?? null,
                'profiles' => explode(', ', $info['Profiles'] ?? 'default'),
                'config' => $config
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * List images
     */
    public function images(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireAdmin();
        
        try {
            $lxcBin = $this->getLxcBin();
            if (!$lxcBin) {
                echo json_encode(['success' => true, 'images' => [], 'warning' => 'LXC/LXD not installed']);
                exit;
            }
            
            $output = shell_exec("sudo $lxcBin image list --format json 2>&1");
            if (empty($output)) {
                echo json_encode(['success' => true, 'images' => []]);
                exit;
            }
            $images = json_decode($output, true);
            if ($images === null) {
                echo json_encode(['success' => true, 'images' => [], 'warning' => 'LXD returned: ' . substr($output, 0, 200)]);
                exit;
            }
            
            $result = [];
            foreach ($images as $img) {
                $alias = '';
                if (!empty($img['aliases'])) {
                    $alias = $img['aliases'][0]['name'] ?? '';
                }
                
                $result[] = [
                    'fingerprint' => $img['fingerprint'] ?? '',
                    'alias' => $alias,
                    'description' => $img['properties']['description'] ?? ($img['update_source']['alias'] ?? ''),
                    'size' => $img['size'] ?? 0,
                    'architecture' => $img['architecture'] ?? '',
                    'type' => $img['type'] ?? 'container',
                    'uploaded_at' => $img['uploaded_at'] ?? null,
                    'properties' => $img['properties'] ?? [],
                ];
            }
            
            echo json_encode(['success' => true, 'images' => $result]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage(), 'images' => []]);
        }
        exit;
    }

    /**
     * Delete image
     */
    public function imageDelete($fingerprint = ''): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        $this->requireAdmin();
        
        // CSRF validation
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $token = $input['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        if (!preg_match('/^[a-f0-9]+$/i', $fingerprint)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid fingerprint', 'received' => $fingerprint, 'length' => strlen($fingerprint)]);
            exit;
        }
        
        try {
            $lxcBin = $this->getLxcBin();
            if (!$lxcBin) {
                echo json_encode(['success' => false, 'error' => 'LXC/LXD not installed']);
                exit;
            }
            
            $result = shell_exec("sudo $lxcBin image delete {$fingerprint} 2>&1");
            echo json_encode(['success' => true, 'message' => 'Image deleted']);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Pull image
     */
    public function imagePull(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        $this->requireAdmin();
        
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        // CSRF validation
        $token = $input['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        $image = $input['image'] ?? '';
        $alias = $input['alias'] ?? '';
        
        if (empty($image)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Image source is required']);
            exit;
        }
        
        try {
            $lxcBin = $this->getLxcBin();
            if (!$lxcBin) {
                echo json_encode(['success' => false, 'error' => 'LXC/LXD not installed']);
                exit;
            }
            
            $cmd = "sudo $lxcBin image copy " . escapeshellarg($image) . " local: --copy-aliases";
            if (!empty($alias)) {
                $cmd .= " --alias " . escapeshellarg($alias);
            }
            $cmd .= " 2>&1";
            
            $result = shell_exec($cmd);
            
            echo json_encode(['success' => true, 'message' => 'Image pulled successfully', 'output' => $result]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * List storage pools
     */
    public function storage(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireAdmin();
        
        try {
            $lxcBin = $this->getLxcBin();
            if (!$lxcBin) {
                echo json_encode(['success' => true, 'storage' => [], 'warning' => 'LXC/LXD not installed']);
                exit;
            }
            
            $output = shell_exec("sudo $lxcBin storage list --format json 2>&1");
            if (empty($output)) {
                echo json_encode(['success' => true, 'storage' => []]);
                exit;
            }
            $pools = json_decode($output, true);
            if ($pools === null) {
                echo json_encode(['success' => true, 'storage' => [], 'warning' => 'LXD returned: ' . substr($output, 0, 200)]);
                exit;
            }
            
            $result = [];
            foreach ($pools as $pool) {
                $infoOutput = shell_exec("sudo $lxcBin storage info " . escapeshellarg($pool['name']) . " 2>/dev/null");
                $usedSpace = 'N/A';
                $totalSpace = 'N/A';
                if ($infoOutput) {
                    if (preg_match('/Space used:\s*(.+)/', $infoOutput, $m)) {
                        $usedSpace = trim($m[1]);
                    }
                    if (preg_match('/Total space:\s*(.+)/', $infoOutput, $m)) {
                        $totalSpace = trim($m[1]);
                    }
                }
                
                $result[] = [
                    'name' => $pool['name'],
                    'driver' => $pool['driver'] ?? '',
                    'description' => $pool['description'] ?? '',
                    'status' => $pool['status'] ?? 'Unknown',
                    'used_space' => $usedSpace,
                    'total_space' => $totalSpace,
                ];
            }
            
            echo json_encode(['success' => true, 'storage' => $result]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage(), 'storage' => []]);
        }
        exit;
    }

    /**
     * List networks
     */
    public function networks(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireAdmin();
        
        try {
            $lxcBin = $this->getLxcBin();
            if (!$lxcBin) {
                echo json_encode(['success' => true, 'networks' => [], 'warning' => 'LXC/LXD not installed']);
                exit;
            }
            
            $output = shell_exec("sudo $lxcBin network list --format json 2>&1");
            if (empty($output)) {
                echo json_encode(['success' => true, 'networks' => []]);
                exit;
            }
            $networks = json_decode($output, true);
            if ($networks === null) {
                echo json_encode(['success' => true, 'networks' => [], 'warning' => 'LXD returned: ' . substr($output, 0, 200)]);
                exit;
            }
            
            $result = [];
            foreach ($networks as $net) {
                $result[] = [
                    'name' => $net['name'],
                    'type' => $net['type'] ?? '',
                    'managed' => $net['managed'] ?? false,
                    'description' => $net['description'] ?? '',
                    'status' => $net['status'] ?? 'Unknown',
                ];
            }
            
            echo json_encode(['success' => true, 'networks' => $result]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage(), 'networks' => []]);
        }
        exit;
    }

    /**
     * Get host system stats (CPU/Memory)
     */
    public function stats(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireAdmin();
        
        try {
            // Get CPU usage from /proc/stat
            $cpuPercent = 0;
            $stat1 = file_get_contents('/proc/stat');
            usleep(100000); // 100ms sample
            $stat2 = file_get_contents('/proc/stat');
            
            if ($stat1 && $stat2) {
                preg_match('/^cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/m', $stat1, $m1);
                preg_match('/^cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/m', $stat2, $m2);
                
                if ($m1 && $m2) {
                    $idle1 = $m1[4];
                    $idle2 = $m2[4];
                    $total1 = array_sum(array_slice($m1, 1));
                    $total2 = array_sum(array_slice($m2, 1));
                    
                    $idleDelta = $idle2 - $idle1;
                    $totalDelta = $total2 - $total1;
                    
                    if ($totalDelta > 0) {
                        $cpuPercent = round(100 * (1 - $idleDelta / $totalDelta), 1);
                    }
                }
            }
            
            // Get memory usage from /proc/meminfo
            $memInfo = file_get_contents('/proc/meminfo');
            $memTotal = 0;
            $memAvailable = 0;
            $memUsed = 0;
            $memPercent = 0;
            
            if ($memInfo) {
                if (preg_match('/MemTotal:\s+(\d+)/', $memInfo, $m)) {
                    $memTotal = (int)$m[1] * 1024;
                }
                if (preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $m)) {
                    $memAvailable = (int)$m[1] * 1024;
                }
                $memUsed = $memTotal - $memAvailable;
                if ($memTotal > 0) {
                    $memPercent = round(100 * $memUsed / $memTotal, 1);
                }
            }
            
            // Get LXC storage pool usage
            $diskTotal = 0;
            $diskUsed = 0;
            $diskPercent = 0;
            $lxcBin = $this->getLxcBin();
            if ($lxcBin) {
                $infoOutput = shell_exec("sudo $lxcBin storage info default 2>/dev/null");
                if ($infoOutput) {
                    if (preg_match('/space used:\s*([\d.]+)\s*(\w+)/i', $infoOutput, $m)) {
                        $diskUsed = (float)$m[1];
                        $unit = strtoupper($m[2]);
                        if ($unit === 'GIB' || $unit === 'GB') $diskUsed *= 1024 * 1024 * 1024;
                        elseif ($unit === 'MIB' || $unit === 'MB') $diskUsed *= 1024 * 1024;
                        elseif ($unit === 'KIB' || $unit === 'KB') $diskUsed *= 1024;
                    }
                    if (preg_match('/total space:\s*([\d.]+)\s*(\w+)/i', $infoOutput, $m)) {
                        $diskTotal = (float)$m[1];
                        $unit = strtoupper($m[2]);
                        if ($unit === 'GIB' || $unit === 'GB') $diskTotal *= 1024 * 1024 * 1024;
                        elseif ($unit === 'MIB' || $unit === 'MB') $diskTotal *= 1024 * 1024;
                        elseif ($unit === 'KIB' || $unit === 'KB') $diskTotal *= 1024;
                    }
                    if ($diskTotal > 0) {
                        $diskPercent = round(100 * $diskUsed / $diskTotal, 1);
                    }
                }
            }
            
            // Get CPU core count
            $cpuCores = 1;
            $cpuInfo = file_get_contents('/proc/cpuinfo');
            if ($cpuInfo) {
                $cpuCores = preg_match_all('/^processor\s*:/m', $cpuInfo);
                if ($cpuCores < 1) $cpuCores = 1;
            }
            
            echo json_encode([
                'success' => true,
                'cpu' => [
                    'percent' => $cpuPercent,
                    'cores' => $cpuCores,
                ],
                'memory' => [
                    'total' => $memTotal,
                    'used' => $memUsed,
                    'available' => $memAvailable,
                    'percent' => $memPercent,
                ],
                'disk' => [
                    'total' => (int)$diskTotal,
                    'used' => (int)$diskUsed,
                    'percent' => $diskPercent,
                ]
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Prune unused resources
     */
    public function prune(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        $this->requireAdmin();
        
        // CSRF validation
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $token = $input['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        try {
            $lxcBin = $this->getLxcBin();
            if (!$lxcBin) {
                echo json_encode(['success' => false, 'error' => 'LXC/LXD not installed']);
                exit;
            }
            
            $imagesOutput = shell_exec("sudo $lxcBin image list --format json 2>/dev/null");
            $images = json_decode($imagesOutput, true) ?: [];
            
            $containersOutput = shell_exec("sudo $lxcBin list --format json 2>/dev/null");
            $containers = json_decode($containersOutput, true) ?: [];
            
            $unusedImages = count($images);
            $stoppedContainers = 0;
            foreach ($containers as $c) {
                if (($c['status'] ?? '') === 'Stopped') {
                    $stoppedContainers++;
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Found {$unusedImages} images and {$stoppedContainers} stopped containers. Use individual delete actions to remove them."
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * VNC Connect - Start VNC server in container and return connection info
     * POST /admin/lxc/vnc/{name}
     */
    public function vncConnect(string $name): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireAdmin();
        
        // CSRF validation
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $token = $input['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        try {
            $lxcBin = $this->getLxcBin();
            if (!$lxcBin) {
                echo json_encode(['success' => false, 'error' => 'LXC/LXD not installed']);
                exit;
            }
            
            // Sanitize container name
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
                echo json_encode(['success' => false, 'error' => 'Invalid container name']);
                exit;
            }
            
            // Use LXD REST API via Unix socket (much faster than CLI)
            $socket = '/var/snap/lxd/common/lxd/unix.socket';
            $apiUrl = "http://localhost/1.0/instances/" . urlencode($name) . "/state";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, $socket);
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $data = json_decode($response, true);
            $metadata = $data['metadata'] ?? [];
            
            if ($httpCode !== 200 || ($metadata['status'] ?? '') !== 'Running') {
                echo json_encode(['success' => false, 'error' => 'Container is not running']);
                exit;
            }
            
            // Get container IP from API response
            $containerIp = null;
            $network = $metadata['network'] ?? [];
            foreach ($network as $iface => $ifData) {
                if ($iface === 'lo') continue;
                foreach ($ifData['addresses'] ?? [] as $addr) {
                    if ($addr['family'] === 'inet') {
                        $containerIp = $addr['address'];
                        break 2;
                    }
                }
            }
            
            if (!$containerIp) {
                echo json_encode(['success' => false, 'error' => 'Container has no IP address']);
                exit;
            }
            
            // Check if VNC server is running in container (port 5900)
            $checkVnc = "sudo $lxcBin exec " . escapeshellarg($name) . " -- sh -c 'netstat -tln 2>/dev/null | grep -q :5900 || ss -tln 2>/dev/null | grep -q :5900' 2>/dev/null";
            $vncRunning = shell_exec($checkVnc . " && echo 'yes' || echo 'no'");
            
            if (trim($vncRunning) !== 'yes') {
                // Ensure XFCE panel config exists before starting VNC
                \Ginto\Helpers\LxdSandboxManager::ensureXfcePanel($name);
                
                // Start Xvfb (virtual framebuffer) and then x11vnc
                $startVnc = "sudo $lxcBin exec " . escapeshellarg($name) . " -- sh -c '
                    if ! command -v x11vnc >/dev/null 2>&1; then
                        echo \"NO_VNC_SERVER\"
                        exit 1
                    fi
                    
                    # Kill any existing sessions
                    pkill -9 Xvfb 2>/dev/null || true
                    pkill -9 x11vnc 2>/dev/null || true
                    pkill -9 xfce4-session 2>/dev/null || true
                    pkill -9 dbus-daemon 2>/dev/null || true
                    sleep 1
                    
                    # Setup environment
                    export DISPLAY=:0
                    export HOME=/root
                    export XDG_RUNTIME_DIR=/tmp/runtime-root
                    mkdir -p \$XDG_RUNTIME_DIR
                    chmod 700 \$XDG_RUNTIME_DIR
                    
                    # Start Xvfb (virtual X display)
                    Xvfb :0 -screen 0 1280x720x24 &
                    sleep 1
                    
                    # Start dbus for desktop
                    export DBUS_SESSION_BUS_ADDRESS=unix:path=/tmp/dbus-session
                    dbus-daemon --session --address=\"\$DBUS_SESSION_BUS_ADDRESS\" --fork 2>/dev/null || true
                    
                    # Start XFCE desktop
                    startxfce4 &
                    sleep 3
                    
                    # Start x11vnc to share the display
                    x11vnc -display :0 -nopw -forever -shared -bg -rfbport 5900 -xkb
                    
                    echo \"VNC_STARTED\"
                ' 2>&1";
                $result = shell_exec($startVnc);
                
                if (strpos($result, 'NO_VNC_SERVER') !== false) {
                    echo json_encode([
                        'success' => false, 
                        'error' => 'No VNC server installed in container. Run: ginto.sh upgrade ' . $name
                    ]);
                    exit;
                }
                
                // Wait for VNC to start
                sleep(2);
            }
            
            // Generate a port for websockify (6170-6190 range to match Caddy proxy config)
            $wsPort = 6170 + (abs(crc32($name)) % 21);
            
            // Check if websockify is already running for this container (check by port listening)
            $checkPort = "ss -tlnp 2>/dev/null | grep -q ':$wsPort ' && echo 'running'";
            $wsRunning = trim(shell_exec($checkPort) ?? '');
            
            if ($wsRunning !== 'running') {
                // Start websockify - use the path relative to where PHP is running (inside box container)
                $appRoot = dirname(__DIR__, 2);
                $scriptPath = $appRoot . '/bin/start_websockify.sh';
                $webRoot = $appRoot . '/public/lib/novnc';
                $cmd = "bash " . escapeshellarg($scriptPath) . " $wsPort $containerIp:5900 " . escapeshellarg($webRoot) . " 2>&1";
                
                $output = shell_exec($cmd);
                
                // Check if it started
                usleep(500000);
                $wsRunning = trim(shell_exec($checkPort) ?? '');
                if ($wsRunning !== 'running') {
                    error_log("Failed to start websockify: $output");
                }
            }
            
            // Return connection info - use the websockify port for noVNC to load files from
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            // Remove any port from host
            $hostOnly = preg_replace('/:\d+$/', '', $host);
            
            // Verify websockify is running
            $finalCheck = trim(shell_exec("ss -tlnp 2>/dev/null | grep -q ':$wsPort ' && echo 'running'") ?? '');
            
            echo json_encode([
                'success' => true,
                'wsUrl' => 'ws://' . $hostOnly . ':' . $wsPort . '/',
                'port' => $wsPort,
                'path' => '',
                'password' => '',
                'containerIp' => $containerIp,
                'debug' => [
                    'wsPort' => $wsPort,
                    'containerName' => $name,
                    'wsRunning' => $finalCheck === 'running' ? 'YES' : 'NO'
                ]
            ]);
            
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * Get the owner of a container by looking up in client_sandboxes table
     */
    private function getContainerOwner(string $containerName): array
    {
        try {
            // Extract sandbox ID from container name (e.g., "ginto-sandbox-abc123" -> "abc123")
            $sandboxId = $containerName;
            if (preg_match('/^ginto-sandbox-(.+)$/', $containerName, $matches)) {
                $sandboxId = $matches[1];
            }
            
            $stmt = $this->db->pdo->prepare("
                SELECT cs.user_id, u.username, u.fullname
                FROM client_sandboxes cs
                JOIN users u ON cs.user_id = u.id
                WHERE cs.sandbox_id = ?
                LIMIT 1
            ");
            $stmt->execute([$sandboxId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($row) {
                return [
                    'user_id' => $row['user_id'],
                    'username' => $row['username'],
                    'fullname' => $row['fullname']
                ];
            }
        } catch (\Throwable $e) {
            // Ignore errors - owner info is optional
        }
        
        return [];
    }
}
