<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;
use Core\Controller;

class AdminController extends \Core\Controller
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
        
        // Check if user is authenticated and has admin role
        $this->requireAdmin();
    }

    /**
     * List activity logs for admin review.
     */
    public function logs()
    {
        $page = (int)($_GET['page'] ?? 1);
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $logs = $this->db->select('activity_logs', '*', [
            'ORDER' => ['created_at' => 'DESC'],
            'LIMIT' => [$offset, $perPage]
        ]) ?: [];

        $total = $this->db->count('activity_logs') ?: 0;
        $totalPages = max(1, ceil($total / $perPage));

        $this->view('admin/logs/index', [
            'title' => 'System Logs',
            'logs' => $logs,
            'pagination' => ['current' => $page, 'total' => $totalPages]
        ]);
    }

    /**
     * Show one activity log entry
     */
    public function log($id)
    {
        $entry = $this->db->get('activity_logs', '*', ['id' => (int)$id]);
        if (!$entry) { http_response_code(404); echo 'Log not found'; return; }

        $this->view('admin/logs/show', ['title' => 'Log #' . $entry['id'], 'log' => $entry]);
    }

    /**
     * Render the system logs under the dashboard path using the playground template
     */
    public function dashboardLogs()
    {
        $page = (int)($_GET['page'] ?? 1);
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $logs = [];
        try {
            $cols = [
                'activity_logs.id',
                'activity_logs.user_id',
                'users.username(user_name)',
                'activity_logs.action',
                'activity_logs.model_type',
                'activity_logs.model_id',
                'activity_logs.description',
                'activity_logs.created_at'
            ];

            $logs = $this->db->select('activity_logs', [
                '[>]users' => ['user_id' => 'id']
            ], $cols, [
                'ORDER' => ['created_at' => 'DESC'],
                'LIMIT' => [$offset, $perPage]
            ]) ?: [];

            foreach ($logs as &$r) {
                $r['username'] = $r['user_name'] ?? ($r['user_id'] ? (string)$r['user_id'] : '(system)');

                $desc = (string)($r['description'] ?? '');
                $summary = '';
                $trim = ltrim($desc);
                if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
                    $json = json_decode($desc, true);
                    if (is_array($json)) {
                        $keys = ['error', 'message', 'msg', 'file', 'path', 'input', 'prompt', 'reason'];
                        foreach ($keys as $k) {
                            if (isset($json[$k]) && is_scalar($json[$k]) && trim((string)$json[$k]) !== '') { $summary = (string)$json[$k]; break; }
                        }
                        if ($summary === '') {
                            $flat = [];
                            foreach ($json as $k => $v) {
                                if (is_scalar($v)) $flat[] = $k . ': ' . mb_strimwidth((string)$v, 0, 50, '…');
                            }
                            $summary = implode(' | ', array_slice($flat, 0, 3));
                        }
                    }
                }
                if ($summary === '') {
                    $first = strtok($desc, "\n");
                    $summary = mb_strimwidth((string)$first, 0, 140, '…');
                }
                $r['summary'] = $summary;
            }
            unset($r);
        } catch (Exception $_) { $logs = []; }

        try { $total = (int)$this->db->count('activity_logs') ?: count($logs); } catch (Exception $_) { $total = count($logs); }
        $totalPages = max(1, ceil($total / $perPage));
        $pagination = ['current' => $page, 'total' => $totalPages];

        $this->view('playground/logs/index', [
            'pageTitle' => 'System Logs',
            'logs' => $logs,
            'pagination' => $pagination
        ]);
    }

    /**
     * Render a single system log under the dashboard path using the playground template
     */
    public function dashboardLog($id)
    {
        $entry = $this->db->get('activity_logs', [
            '[>]users' => ['user_id' => 'id']
        ], [
            'activity_logs.id', 'activity_logs.user_id', 'users.username(user_name)', 'activity_logs.action', 'activity_logs.model_type', 'activity_logs.model_id', 'activity_logs.description', 'activity_logs.created_at'
        ], ['activity_logs.id' => (int)$id]);

        if (!$entry) { http_response_code(404); echo 'Log not found'; return; }

        $entry['username'] = $entry['user_name'] ?? ($entry['user_id'] ? (string)$entry['user_id'] : '(system)');
        $desc = (string)($entry['description'] ?? '');
        $trim = ltrim($desc);
        if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
            $json = json_decode($desc, true);
            if (is_array($json)) {
                $entry['description_json'] = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
        }

        $this->view('playground/logs/show', ['pageTitle' => 'Log #' . $entry['id'], 'log' => $entry]);
    }

    /**
     * Main admin dashboard - shows both legacy user stats and CMS overview
     */
    public function dashboard()
    {
        // Legacy user stats (maintain compatibility)
        $totalUsers = $this->db->count('users');
        
        // CMS stats - with error handling for missing tables
        $totalPages = $totalPosts = $publishedPages = $publishedPosts = $draftPages = $draftPosts = 0;
        $recentPosts = $recentPages = [];
        
        try {
            $totalPages = $this->db->count('pages') ?: 0;
            $publishedPages = $this->db->count('pages', ['status' => 'published']) ?: 0;
            $draftPages = $this->db->count('pages', ['status' => 'draft']) ?: 0;
            
            $recentPages = $this->db->select('pages', [
                '[>]users' => ['author_id' => 'id']
            ], [
                'pages.id',
                'pages.title',
                'pages.status',
                'pages.created_at',
                'users.fullname'
            ], [
                'ORDER' => ['pages.created_at' => 'DESC'],
                'LIMIT' => 5
            ]) ?: [];
        } catch (Exception $e) {
            // Pages table doesn't exist or has schema issues
            error_log("Admin dashboard pages query error: " . $e->getMessage());
        }
        
        try {
            $totalPosts = $this->db->count('posts') ?: 0;
            $publishedPosts = $this->db->count('posts', ['status' => 'published']) ?: 0;
            $draftPosts = $this->db->count('posts', ['status' => 'draft']) ?: 0;
            
            $recentPosts = $this->db->select('posts', [
                '[>]users' => ['author_id' => 'id']
            ], [
                'posts.id',
                'posts.title',
                'posts.status',
                'posts.created_at',
                'users.fullname'
            ], [
                'ORDER' => ['posts.created_at' => 'DESC'],
                'LIMIT' => 5
            ]) ?: [];
        } catch (Exception $e) {
            // Posts table doesn't exist or has schema issues
            error_log("Admin dashboard posts query error: " . $e->getMessage());
        }

        // Financial overview
        $totalBalance = $this->db->sum('users', 'total_balance') ?: 0;
        $totalCommissions = 0;
        $pendingPayouts = 0;
        $totalSales = 0;
        
        try {
            $totalCommissions = $this->db->sum('commissions', 'amount') ?: 0;
            $pendingPayouts = $this->db->sum('payouts', 'amount', ['status' => 'pending']) ?: 0;
        } catch (Exception $e) {
            // Commission/payout tables don't exist yet
        }
                // Compute total sales (orders) safely
                try {
                    $totalSales = $this->db->sum('orders', 'amount', ['status' => 'completed']) ?: 0;
                } catch (Exception $e) {
                    // Orders table might not exist yet
                    $totalSales = 0;
                }
        
        // User distribution by level
        $levelDistribution = [];
        try {
            $levels = $this->db->select('tier_plans', ['id', 'name']);
            foreach ($levels as $level) {
                $count = $this->db->count('users', ['current_level_id' => $level['id']]);
                $levelDistribution[] = ['name' => $level['name'], 'count' => $count];
            }
        } catch (Exception $e) {
            // Levels table might not exist
        }
        
        // Recent registrations (last 7 days)
        $recentRegistrations = $this->db->count('users', [
            'created_at[>=]' => date('Y-m-d', strtotime('-7 days'))
        ]);

        // Add human-friendly key name for view
        $newUsers = $recentRegistrations;
        
        $this->view('admin/dashboard', [
            'title' => 'Admin Dashboard',
            'stats' => [
                'total_users' => $totalUsers,
                'total_balance' => $totalBalance,
                'total_commissions' => $totalCommissions,
                'total_sales' => $totalSales,
                'pending_payouts' => $pendingPayouts,
                'recent_registrations' => $recentRegistrations,
                'new_users' => $newUsers,
                'total_pages' => $totalPages,
                'total_posts' => $totalPosts,
                'published_pages' => $publishedPages,
                'published_posts' => $publishedPosts,
                'draft_pages' => $draftPages,
                'draft_posts' => $draftPosts,
            ],
            'level_distribution' => $levelDistribution,
            'recent_posts' => $recentPosts,
            'recent_pages' => $recentPages,
            'user' => $this->getCurrentUser(),
        ]);
    }

    /**
     * Dedicated CMS dashboard (new interface)
     */
    public function cmsDashboard()
    {
        // More detailed CMS statistics
        $stats = [
            'content' => [
                'pages' => $this->db->count('pages') ?: 0,
                'posts' => $this->db->count('posts') ?: 0,
                'categories' => $this->db->count('categories') ?: 0,
                'tags' => $this->db->count('tags') ?: 0,
                'media' => $this->db->count('media') ?: 0,
            ],
            'users' => [
                'total' => $this->db->count('users') ?: 0,
                'active' => $this->db->count('users', ['status' => 'active']) ?: 0,
                'admins' => $this->db->count('users', ['role_id[<=]' => 2]) ?: 0,
            ],
            'system' => [
                'themes' => $this->db->count('themes') ?: 0,
                'plugins' => $this->db->count('plugins') ?: 0,
                'active_themes' => $this->db->count('themes', ['is_active' => 1]) ?: 0,
                'active_plugins' => $this->db->count('plugins', ['is_active' => 1]) ?: 0,
            ]
        ];

        // Recent content activity
        $recentActivity = [];
        
        // Get recent posts
        $recentPosts = $this->db->select('posts', [
            '[>]users' => ['author_id' => 'id']
        ], [
            'posts.title',
            'posts.status',
            'posts.created_at',
            'users.first_name',
            'users.last_name'
        ], [
            'ORDER' => ['posts.created_at' => 'DESC'],
            'LIMIT' => 10
        ]) ?: [];

        foreach ($recentPosts as $post) {
            $recentActivity[] = [
                'type' => 'post',
                'title' => $post['title'],
                'status' => $post['status'],
                'author' => $post['first_name'] . ' ' . $post['last_name'],
                'created_at' => $post['created_at']
            ];
        }

        // Sort by creation date
        usort($recentActivity, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        $this->view('admin/cms-dashboard', [
            'title' => 'CMS Dashboard',
            'stats' => $stats,
            'recent_activity' => array_slice($recentActivity, 0, 10)
        ]);
    }

    /**
     * Check if current user has admin privileges
     */
    private function requireAdmin()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        // Check if user has admin role (role_id 1 or 2)
        $user = $this->db->get('users', ['role_id'], ['id' => $_SESSION['user_id']]);
        
        if (!$user || !in_array($user['role_id'], [1, 2])) {
            header('Location: /dashboard');
            exit;
        }
    }

    private function getCurrentUser()
    {
        if (!isset($_SESSION['username'])) {
           header('Location: /login');
            exit;
        }

        return $_SESSION['username'];
    }

}