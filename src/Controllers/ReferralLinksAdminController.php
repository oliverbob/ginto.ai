<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;
use Core\Controller;

class ReferralLinksAdminController extends \Core\Controller
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->requireAdmin();
    }

    private function requireAdmin()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        
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

    private function generateCsrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * List users with search and referral links
     */
    public function index()
    {
        $search = trim($_GET['search'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        
        // Build where clause
        $where = [];
        
        if ($search !== '') {
            $where['OR'] = [
                'fullname[~]' => $search,
                'username[~]' => $search,
                'email[~]' => $search,
                'public_id[~]' => $search
            ];
        }
        
        // Get total count for pagination
        $totalCount = $this->db->count('users', $where ?: null);
        $totalPages = ceil($totalCount / $perPage);
        
        // Add ordering and pagination
        $where['ORDER'] = ['created_at' => 'DESC'];
        $where['LIMIT'] = [$offset, $perPage];
        
        // Get users
        $users = $this->db->select('users', [
            'id',
            'public_id',
            'fullname',
            'username',
            'email',
            'created_at',
            'status'
        ], $where);
        
        $this->view('admin/referrallinks/index', [
            'title' => 'Referral Links',
            'users' => $users,
            'search' => $search,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    /**
     * AJAX search endpoint - returns JSON
     */
    public function search()
    {
        header('Content-Type: application/json');
        
        $search = trim($_GET['q'] ?? '');
        $perPage = 50;
        
        $where = [];
        
        if ($search !== '') {
            $where['OR'] = [
                'fullname[~]' => $search,
                'username[~]' => $search,
                'email[~]' => $search,
                'public_id[~]' => $search
            ];
        }
        
        $where['ORDER'] = ['created_at' => 'DESC'];
        $where['LIMIT'] = $perPage;
        
        $users = $this->db->select('users', [
            'id',
            'public_id',
            'fullname',
            'username',
            'email',
            'status'
        ], $where);
        
        echo json_encode([
            'success' => true,
            'users' => $users,
            'count' => count($users)
        ]);
        exit;
    }
}
