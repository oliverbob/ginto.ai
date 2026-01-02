<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;
use Ginto\Core\View;

class NetworkTreeController extends \Core\Controller
{
    private $db;
    private $view;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->view = new View();
    }

    // Render front-end page for the current user
    public function index()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $user = $this->db->get('users', '*', ['id' => $_SESSION['user_id']]);
        if (!$user) {
            http_response_code(403);
            echo "Access denied";
            exit;
        }

        $stats = $this->getNetworkStats();
        $this->view->render('user/network-tree', ['title' => 'My Network Tree', 'user_data' => $user, 'stats' => $stats]);
    }

    // AJAX endpoint: returns tree JSON rooted at user_id (defaults to session user)
    public function getTreeData()
    {
        header('Content-Type: application/json');
        $userId = intval($_GET['user_id'] ?? ($_SESSION['user_id'] ?? 0));
        $depth = intval($_GET['depth'] ?? 3);

        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user id']);
            return;
        }

        try {
            $tree = $this->buildTreeData($userId, $depth);
            echo json_encode(['success' => true, 'data' => $tree]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // AJAX: search users
    public function searchUsers()
    {
        header('Content-Type: application/json');
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) {
            echo json_encode(['success' => false, 'message' => 'Query too short']);
            return;
        }
        $users = $this->db->select('users', ['id','username','email','fullname','ginto_level'], [
            'OR' => ['username[~]' => $q, 'email[~]' => $q, 'fullname[~]' => $q],
            'LIMIT' => 20
        ]);
        echo json_encode(['success' => true, 'users' => $users]);
    }

    // Build tree recursively and attach totalCommissions (from orders)
    private function buildTreeData(int $rootUserId, int $maxDepth = 3, int $currentDepth = 0)
    {
        $user = $this->db->get('users', ['id','username','fullname','ginto_level','referrer_id','created_at','status','phone','country'], ['id' => $rootUserId]);
        if (!$user) return null;

        $refs = $this->db->select('users', ['id','username','fullname','ginto_level','created_at','status','phone','country'], ['referrer_id' => $rootUserId, 'ORDER' => ['created_at' => 'ASC']]);

        $totals = $this->getUserCommissionData($rootUserId);

        $node = [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'fullname' => $user['fullname'] ?? $user['username'],
            'level' => $user['ginto_level'] ?? 0,
            'directReferrals' => count($refs),
            'totalCommissions' => $totals['total'],
            'monthlyCommissions' => $totals['monthly'],
            'children' => []
        ];

        if ($currentDepth + 1 <= $maxDepth) {
            foreach ($refs as $r) {
                $child = $this->buildTreeData((int)$r['id'], $maxDepth, $currentDepth + 1);
                if ($child) $node['children'][] = $child;
            }
        }

        return $node;
    }

    // Primary source: orders (status='completed'). Fallback: commissions
    private function getUserCommissionData(int $userId): array
    {
        $total = floatval($this->db->sum('orders', 'amount', ['user_id' => $userId, 'status' => 'completed']) ?: 0);
        $monthly = floatval($this->db->sum('orders', 'amount', ['user_id' => $userId, 'status' => 'completed', 'created_at[>=]' => date('Y-m-01 00:00:00'), 'created_at[<=]' => date('Y-m-t 23:59:59')]) ?: 0);

        if ($total === 0.0 && $monthly === 0.0) {
            try {
                $total = floatval($this->db->sum('commissions', 'amount', ['user_id' => $userId, 'status' => 'paid']) ?: 0);
                $monthly = floatval($this->db->sum('commissions', 'amount', ['user_id' => $userId, 'status' => 'paid', 'created_at[>=]' => date('Y-m-01 00:00:00'), 'created_at[<=]' => date('Y-m-t 23:59:59')]) ?: 0);
            } catch (\Throwable $_) {
                // ignore
            }
        }

        return ['total' => $total, 'monthly' => $monthly];
    }

    private function getNetworkStats(): array
    {
        $totalUsers = $this->db->count('users');
        $activeUsers = $this->db->count('users', ['status' => 'active']);

        $totalCommissions = floatval($this->db->sum('orders', 'amount', ['status' => 'completed']) ?: 0);
        $monthlyCommissions = floatval($this->db->sum('orders', 'amount', ['status' => 'completed', 'created_at[>=]' => date('Y-m-01 00:00:00'), 'created_at[<=]' => date('Y-m-t 23:59:59')]) ?: 0);

        if ($totalCommissions === 0.0 && $monthlyCommissions === 0.0) {
            try {
                $totalCommissions = floatval($this->db->sum('commissions', 'amount', ['status' => 'paid']) ?: 0);
                $monthlyCommissions = floatval($this->db->sum('commissions', 'amount', ['status' => 'paid', 'created_at[>=]' => date('Y-m-01 00:00:00'), 'created_at[<=]' => date('Y-m-t 23:59:59')]) ?: 0);
            } catch (\Throwable $_) {}
        }

        $levelDistribution = [];
        $levels = $this->db->select('users', ['ginto_level'], ['GROUP' => 'ginto_level', 'ORDER' => ['ginto_level' => 'ASC']]) ?: [];
        foreach ($levels as $lvl) {
            $count = $this->db->count('users', ['ginto_level' => $lvl['ginto_level']]);
            $levelDistribution[] = ['ginto_level' => $lvl['ginto_level'], 'count' => $count];
        }

        return ['totalUsers' => $totalUsers, 'activeUsers' => $activeUsers, 'totalCommissions' => $totalCommissions, 'monthlyCommissions' => $monthlyCommissions, 'levelDistribution' => $levelDistribution];
    }
}
?>