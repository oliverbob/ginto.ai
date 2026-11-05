<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;
use Core\Controller;

class FinanceController extends \Core\Controller
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->requireAdmin();
    }

    /**
     * Financial overview dashboard
     */
    public function dashboard()
    {
        // Total platform statistics
        $totalBalance = $this->db->sum('users', 'total_balance') ?: 0;
        $totalUsers = $this->db->count('users');
        $totalCommissions = $this->db->sum('commissions', 'amount') ?: 0;
        $pendingPayouts = $this->db->sum('payouts', 'amount', ['status' => 'pending']) ?: 0;
        
        // Level distribution
        $levelStats = [];
        $levels = $this->db->select('tier_plans', ['id', 'name', 'cost_amount', 'cost_currency']);
        foreach ($levels as $level) {
            $userCount = $this->db->count('users', ['current_level_id' => $level['id']]);
            $levelStats[] = [
                'level' => $level,
                'user_count' => $userCount,
                'revenue' => $userCount * $level['cost_amount']
            ];
        }
        
        // Recent financial activity
        $recentCommissions = $this->db->select('commissions', [
            '[>]users' => ['user_id' => 'id']
        ], [
            'commissions.id',
            'commissions.amount',
            'commissions.type',
            'commissions.created_at',
            'users.username',
            'users.fullname'
        ], [
            'ORDER' => ['commissions.created_at' => 'DESC'],
            'LIMIT' => 10
        ]) ?: [];
        
        $this->view('admin/finance/dashboard', [
            'title' => 'Financial Dashboard',
            'stats' => [
                'total_balance' => $totalBalance,
                'total_users' => $totalUsers,
                'total_commissions' => $totalCommissions,
                'pending_payouts' => $pendingPayouts
            ],
            'level_stats' => $levelStats,
            'recent_commissions' => $recentCommissions
        ]);
    }

    /**
     * Commission management
     */
    public function commissions()
    {
        $page = (int)($_GET['page'] ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $commissions = $this->db->select('commissions', [
            '[>]users' => ['user_id' => 'id'],
            '[>]users (referrer)' => ['referrer_id' => 'id']
        ], [
            'commissions.id',
            'commissions.amount',
            'commissions.type',
            'commissions.level',
            'commissions.created_at',
            'users.username',
            'users.fullname',
            'referrer.username(referrer_username)',
            'referrer.fullname(referrer_name)'
        ], [
            'ORDER' => ['commissions.created_at' => 'DESC'],
            'LIMIT' => [$offset, $limit]
        ]) ?: [];
        
        $totalCommissions = $this->db->count('commissions');
        $totalPages = ceil($totalCommissions / $limit);
        
        $this->view('admin/finance/commissions', [
            'title' => 'Commission Management',
            'commissions' => $commissions,
            'pagination' => [
                'current' => $page,
                'total' => $totalPages,
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages
            ]
        ]);
    }

    /**
     * Payout management
     */
    public function payouts()
    {
        $pendingPayouts = $this->db->select('payouts', [
            '[>]users' => ['user_id' => 'id']
        ], [
            'payouts.id',
            'payouts.amount',
            'payouts.wallet_address',
            'payouts.status',
            'payouts.requested_at',
            'users.username',
            'users.fullname'
        ], [
            'status' => 'pending',
            'ORDER' => ['payouts.requested_at' => 'ASC']
        ]) ?: [];
        
        $this->view('admin/finance/payouts', [
            'title' => 'Payout Management',
            'pending_payouts' => $pendingPayouts,
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