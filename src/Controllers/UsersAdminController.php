<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;
use Core\Controller;

class UsersAdminController extends \Core\Controller
{
    private $db;
    // private $view; // Removed redundant view property

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
        // $this->view = new View(); // Removed initialization of view property
        $this->requireAdmin();
    }

    /**
     * List all client sandboxes
     */
    public function sandboxes()
    {
        // fetch sandboxes from database
        $rows = $this->db->select('client_sandboxes', ['id', 'user_id', 'public_id', 'sandbox_id', 'quota_bytes', 'used_bytes', 'created_at', 'updated_at']);

        // Get all LXD containers for status lookup
        $lxdContainers = \Ginto\Helpers\LxdSandboxManager::listSandboxes();
        $lxdByName = [];
        foreach ($lxdContainers as $c) {
            $lxdByName[$c['name']] = $c;
        }

        // enrich with user info and LXD status
        $sandboxes = [];
        foreach ($rows as $r) {
            $user = $r['user_id'] ? $this->db->get('users', ['id', 'username', 'fullname', 'email'], ['id' => $r['user_id']]) : null;
            
            // Check LXD container status
            $containerName = \Ginto\Helpers\LxdSandboxManager::containerName($r['sandbox_id']);
            $lxdInfo = $lxdByName[$containerName] ?? null;
            
            $sandboxes[] = array_merge($r, [
                'user' => $user,
                'lxd_container' => $containerName,
                'lxd_status' => $lxdInfo ? strtolower($lxdInfo['status']) : 'missing',
                'lxd_ip' => $lxdInfo['ip'] ?? null
            ]);
        }

        // Also include orphaned LXD containers (not in DB)
        $dbContainerNames = array_map(function($r) {
            return \Ginto\Helpers\LxdSandboxManager::containerName($r['sandbox_id']);
        }, $rows);
        
        $orphanedContainers = [];
        foreach ($lxdContainers as $c) {
            if (!in_array($c['name'], $dbContainerNames)) {
                $orphanedContainers[] = [
                    'name' => $c['name'],
                    'status' => strtolower($c['status']),
                    'ip' => $c['ip'] ?? null
                ];
            }
        }

        $this->view('admin/sandboxes/index', [
            'title' => 'Playground Sandboxes',
            'sandboxes' => $sandboxes,
            'orphanedContainers' => $orphanedContainers,
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    /**
     * Show details for a single sandbox
     */
    public function sandbox($sandboxId)
    {
        $row = $this->db->get('client_sandboxes', '*', ['sandbox_id' => $sandboxId]);
        if (!$row) {
            http_response_code(404); echo 'Sandbox not found'; return;
        }

        $user = $row['user_id'] ? $this->db->get('users', ['id','username','fullname','email'], ['id' => $row['user_id']]) : null;

        // compute directory path and size
        $clientsRoot = dirname(dirname(__DIR__)) . '/clients';
        $path = realpath($clientsRoot . '/' . $row['sandbox_id']);

        $size = 0;
        $fileCount = 0;
        if ($path && is_dir($path)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) { $size += $f->getSize(); $fileCount++; }
        }

        $this->view('admin/sandboxes/show', [
            'title' => "Sandbox: {$row['sandbox_id']}",
            'sandbox' => $row,
            'user' => $user,
            'path' => $path,
            'size' => $size,
            'fileCount' => $fileCount,
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    /**
     * Reset a sandbox (delete files inside) - POST
     */
    public function resetSandbox($sandboxId)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Method not allowed'; return; }
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) { http_response_code(403); echo 'Invalid token'; return; }

        $row = $this->db->get('client_sandboxes', '*', ['sandbox_id' => $sandboxId]);
        if (!$row) { http_response_code(404); echo 'Sandbox not found'; return; }

        $clientsRoot = dirname(dirname(__DIR__)) . '/clients';
        $path = realpath($clientsRoot . '/' . $row['sandbox_id']);
        if ($path && is_dir($path)) {
            $this->deleteRecursive($path);
            // recreate dir
            @mkdir($path, 0755, true);
        }

        header('Location: /admin/sandboxes/' . urlencode($sandboxId));
        exit;
    }

    private function deleteRecursive($path)
    {
        $items = array_diff(scandir($path), ['.', '..']);
        foreach ($items as $item) {
            $p = $path . '/' . $item;
            if (is_dir($p)) $this->deleteRecursive($p);
            else @unlink($p);
        }
        return @rmdir($path);
    }

    /**
     * Delete sandbox completely (container + DB + Redis + directory) - POST
     */
    public function deleteSandbox($sandboxId)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Method not allowed'; return; }
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) { http_response_code(403); echo 'Invalid token'; return; }

        // Use unified delete method to ensure no orphans
        $result = \Ginto\Helpers\LxdSandboxManager::deleteSandboxCompletely($sandboxId, $this->db);

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        }

        header('Location: /admin/sandboxes'); exit;
    }

    /**
     * Update quota for a sandbox - POST
     */
    public function setQuota($sandboxId)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Method not allowed'; return; }
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) { http_response_code(403); echo 'Invalid token'; return; }

        $quota = (int)($_POST['quota_bytes'] ?? 0);
        if ($quota <= 0) { http_response_code(400); echo 'Invalid quota'; return; }

        $ok = $this->db->update('client_sandboxes', ['quota_bytes' => $quota], ['sandbox_id' => $sandboxId]);
        header('Location: /admin/sandboxes/' . urlencode($sandboxId)); exit;
    }

    // ==========================================
    // LXD Container Management Actions
    // ==========================================

    /**
     * Start an LXD container - POST
     */
    public function startContainer($sandboxId)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Method not allowed'; return; }
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) { http_response_code(403); echo 'Invalid token'; return; }

        $result = \Ginto\Helpers\LxdSandboxManager::ensureSandboxRunning($sandboxId);
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => $result, 'action' => 'start']);
            exit;
        }
        
        header('Location: /admin/sandboxes'); exit;
    }

    /**
     * Stop an LXD container - POST
     */
    public function stopContainer($sandboxId)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Method not allowed'; return; }
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) { http_response_code(403); echo 'Invalid token'; return; }

        $result = \Ginto\Helpers\LxdSandboxManager::stopSandbox($sandboxId);
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => $result, 'action' => 'stop']);
            exit;
        }
        
        header('Location: /admin/sandboxes'); exit;
    }

    /**
     * Delete an LXD container completely (container + DB + Redis + directory) - POST
     */
    public function deleteContainer($sandboxId)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Method not allowed'; return; }
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) { http_response_code(403); echo 'Invalid token'; return; }

        // Use unified delete method to ensure no orphans
        $result = \Ginto\Helpers\LxdSandboxManager::deleteSandboxCompletely($sandboxId, $this->db);
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        }
        
        header('Location: /admin/sandboxes'); exit;
    }

    /**
     * Create a new LXD container for sandbox - POST
     */
    public function createContainer($sandboxId)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Method not allowed'; return; }
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) { http_response_code(403); echo 'Invalid token'; return; }

        $result = \Ginto\Helpers\LxdSandboxManager::createSandbox($sandboxId);
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        }
        
        header('Location: /admin/sandboxes'); exit;
    }

    /**
     * Get container info/stats - GET (AJAX)
     */
    public function containerInfo($sandboxId)
    {
        header('Content-Type: application/json');
        
        $containerName = \Ginto\Helpers\LxdSandboxManager::containerName($sandboxId);
        $exists = \Ginto\Helpers\LxdSandboxManager::sandboxExists($sandboxId);
        $running = \Ginto\Helpers\LxdSandboxManager::sandboxRunning($sandboxId);
        $ip = \Ginto\Helpers\LxdSandboxManager::getSandboxIp($sandboxId);
        
        // Get detailed info via lxc info
        $info = [];
        if ($exists) {
            exec("lxc info $containerName 2>&1", $output, $code);
            if ($code === 0) {
                $info['raw'] = implode("\n", $output);
                // Parse some key fields
                foreach ($output as $line) {
                    if (preg_match('/^\s*Memory \(current\):\s*(.+)$/', $line, $m)) {
                        $info['memory'] = trim($m[1]);
                    }
                    if (preg_match('/^\s*Processes:\s*(\d+)$/', $line, $m)) {
                        $info['processes'] = (int)$m[1];
                    }
                    if (preg_match('/^\s*CPU usage \(in seconds\):\s*(.+)$/', $line, $m)) {
                        $info['cpu'] = trim($m[1]);
                    }
                }
            }
        }
        
        echo json_encode([
            'sandbox_id' => $sandboxId,
            'container_name' => $containerName,
            'exists' => $exists,
            'running' => $running,
            'ip' => $ip,
            'info' => $info
        ]);
        exit;
    }

    /**
     * Get container logs - GET (AJAX)
     */
    public function containerLogs($sandboxId)
    {
        header('Content-Type: application/json');
        
        $containerName = \Ginto\Helpers\LxdSandboxManager::containerName($sandboxId);
        $lines = (int)($_GET['lines'] ?? 100);
        
        $output = [];
        exec("lxc exec $containerName -- tail -n $lines /var/log/messages 2>&1", $output, $code);
        
        echo json_encode([
            'sandbox_id' => $sandboxId,
            'success' => $code === 0,
            'logs' => implode("\n", $output)
        ]);
        exit;
    }

    /**
     * Execute command in container - POST (AJAX)
     */
    public function containerExec($sandboxId)
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            echo json_encode(['error' => 'Invalid token']);
            exit;
        }
        
        $command = $_POST['command'] ?? '';
        if (empty($command)) {
            echo json_encode(['error' => 'No command provided']);
            exit;
        }
        
        $cwd = $_POST['cwd'] ?? '/home';
        $timeout = min(30, max(5, (int)($_POST['timeout'] ?? 10)));
        
        list($code, $stdout, $stderr) = \Ginto\Helpers\LxdSandboxManager::execInSandbox($sandboxId, $command, $cwd, $timeout);
        
        echo json_encode([
            'sandbox_id' => $sandboxId,
            'exit_code' => $code,
            'stdout' => $stdout,
            'stderr' => $stderr
        ]);
        exit;
    }

    /**
     * Users overview dashboard
     */
    public function dashboard()
    {
        // User statistics
        $totalUsers = $this->db->count('users');
        $activeUsers = $this->db->count('users', ['status' => 'active']);
        $adminUsers = $this->db->count('users', ['role_id[<=]' => 2]);
        $recentUsers = $this->db->count('users', [
            'created_at[>=]' => date('Y-m-d', strtotime('-30 days'))
        ]);
        
        // Registration trends (last 30 days)
        $registrationTrends = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $count = $this->db->count('users', [
                'created_at[>=]' => $date . ' 00:00:00',
                'created_at[<]' => date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00'
            ]);
            $registrationTrends[] = ['date' => $date, 'count' => $count];
        }
        
        // Top referrers
        $topReferrers = $this->db->query("
            SELECT u.id, u.username, u.fullname, u.email, 
                   COUNT(r.id) as referral_count,
                   SUM(r.total_balance) as total_referred_value
            FROM users u 
            LEFT JOIN users r ON u.id = r.referrer_id 
            GROUP BY u.id 
            HAVING referral_count > 0 
            ORDER BY referral_count DESC 
            LIMIT 10
        ")->fetchAll();
        
        $this->view('admin/users/dashboard', [
            'title' => 'Users Management',
            'stats' => [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'admin_users' => $adminUsers,
                'recent_users' => $recentUsers
            ],
            'registration_trends' => $registrationTrends,
            'top_referrers' => $topReferrers
        ]);
    }

    /**
     * User list with search and filtering
     */
    public function userList()
    {
        $page = (int)($_GET['page'] ?? 1);
        $search = trim($_GET['search'] ?? '');
        $status = $_GET['status'] ?? '';
        $role = $_GET['role'] ?? '';
        $level = $_GET['level'] ?? '';
        
        $limit = 25;
        $offset = ($page - 1) * $limit;
        
        // Build where conditions
        $where = [];
        if ($search) {
            $where['OR'] = [
                'username[~]' => $search,
                'email[~]' => $search,
                'fullname[~]' => $search
            ];
        }
        if ($status) $where['status'] = $status;
        if ($role) $where['role_id'] = $role;
        if ($level) $where['current_level_id'] = $level;
        
        $users = $this->db->select('users', [
            '[>]roles' => ['role_id' => 'id'],
            '[>]levels' => ['current_level_id' => 'id'],
            '[>]users (referrer)' => ['referrer_id' => 'id']
        ], [
            'users.id',
            'users.username',
            'users.email',
            'users.fullname',
            'users.status',
            'users.total_balance',
            'users.created_at',
            'users.last_login',
            'roles.display_name(role_name)',
            'levels.name(level_name)',
            'referrer.username(referrer_name)'
        ], array_merge($where, [
            'ORDER' => ['users.created_at' => 'DESC'],
            'LIMIT' => [$offset, $limit]
        ])) ?: [];
        
        $totalUsers = $this->db->count('users', $where);
        $totalPages = ceil($totalUsers / $limit);
        
        // Get filter options
        $roles = $this->db->select('roles', ['id', 'display_name']);
        $levels = $this->db->select('tier_plans', ['id', 'name']);
        
        $this->view('admin/users/list', [
            'title' => 'All Users',
            'users' => $users,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'role' => $role,
                'level' => $level
            ],
            'roles' => $roles,
            'levels' => $levels,
            'pagination' => [
                'current' => $page,
                'total' => $totalPages,
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages
            ],
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    /**
     * User profile view/edit
     */
    public function userProfile($userId)
    {
        $user = $this->db->get('users', [
            '[>]roles' => ['role_id' => 'id'],
            '[>]levels' => ['current_level_id' => 'id'],
            '[>]users (referrer)' => ['referrer_id' => 'id']
        ], [
            'users.*',
            'roles.display_name(role_name)',
            'levels.name(level_name)',
            'referrer.username(referrer_name)',
            'referrer.fullname(referrer_fullname)'
        ], ['users.id' => $userId]);
        
        if (!$user) {
            http_response_code(404);
            echo "User not found";
            return;
        }
        
        // Get user's referrals
        $referrals = $this->db->select('users', [
            'id', 'username', 'fullname', 'email', 'created_at', 'total_balance'
        ], [
            'referrer_id' => $userId,
            'ORDER' => ['created_at' => 'DESC']
        ]);
        
        // Get user's commission history
        $commissions = $this->db->select('commissions', [
            'id', 'amount', 'type', 'level', 'created_at'
        ], [
            'user_id' => $userId,
            'ORDER' => ['created_at' => 'DESC'],
            'LIMIT' => 20
        ]) ?: [];
        
        $this->view('admin/users/profile', [
            'title' => "User: {$user['fullname']}",
            'user' => $user,
            'referrals' => $referrals,
            'commissions' => $commissions,
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    private function requireAdmin()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $user = $this->db->get('users', ['role_id'], ['id' => $_SESSION['user_id']]);
        if (!$user || !in_array($user['role_id'], [1, 2])) {
            http_response_code(403);
            echo '<h1>403 Forbidden</h1>';
            exit;
        }
    }

    private function generateCsrfToken()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}