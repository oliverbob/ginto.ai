<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;
use Core\Controller;

class PaymentsAdminController extends \Core\Controller
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->requireAdmin();
    }

    /**
     * List payments with filtering
     */
    public function index()
    {
        $filter = $_GET['filter'] ?? 'pending'; // pending, all, completed, failed
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        
        // Build where clause
        $where = [];
        if ($filter === 'pending') {
            $where['status'] = 'pending';
        } elseif ($filter === 'completed') {
            $where['status'] = 'completed';
        } elseif ($filter === 'failed') {
            $where['status'] = 'failed';
        } elseif ($filter === 'review') {
            $where['admin_review_requested'] = 1;
            $where['status'] = 'pending';
        }
        
        $where['ORDER'] = ['created_at' => 'DESC'];
        $where['LIMIT'] = [$offset, $perPage];
        
        // Get payments
        $payments = $this->db->select('subscription_payments', [
            'id',
            'user_id',
            'transaction_id',
            'plan_id',
            'type',
            'amount',
            'currency',
            'payment_method',
            'payment_reference',
            'status',
            'receipt_filename',
            'admin_review_requested',
            'admin_review_requested_at',
            'rejection_reason',
            'verified_at',
            'verified_by',
            'created_at',
            'ip_address',
            'geo_country',
            'geo_city'
        ], $where);
        
        // Get total count for pagination
        unset($where['ORDER'], $where['LIMIT']);
        $totalCount = $this->db->count('subscription_payments', $where);
        $totalPages = ceil($totalCount / $perPage);
        
        // Enrich with user info and add payment_status alias
        foreach ($payments as &$p) {
            $p['user'] = $p['user_id'] ? $this->db->get('users', ['id', 'username', 'fullname', 'email'], ['id' => $p['user_id']]) : null;
            $p['payment_status'] = $p['status']; // Alias for view compatibility
        }
        
        // Count by status for tabs
        $counts = [
            'pending' => $this->db->count('subscription_payments', ['status' => 'pending']),
            'review' => $this->db->count('subscription_payments', ['status' => 'pending', 'admin_review_requested' => 1]),
            'completed' => $this->db->count('subscription_payments', ['status' => 'completed']),
            'failed' => $this->db->count('subscription_payments', ['status' => 'failed']),
            'all' => $this->db->count('subscription_payments')
        ];
        
        $this->view('admin/payments/index', [
            'title' => 'Payment Management',
            'payments' => $payments,
            'filter' => $filter,
            'counts' => $counts,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }
    
    /**
     * Approve a payment
     */
    public function approve($paymentId)
    {
        header('Content-Type: application/json');
        
        if (!$this->verifyCsrfToken()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        
        $payment = $this->db->get('subscription_payments', ['id', 'user_id', 'status', 'plan_id'], ['id' => $paymentId]);
        
        if (!$payment) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Payment not found']);
            exit;
        }
        
        if ($payment['status'] === 'completed') {
            echo json_encode(['success' => false, 'message' => 'Payment already approved']);
            exit;
        }
        
        // Update payment status
        $this->db->update('subscription_payments', [
            'status' => 'completed',
            'verified_at' => date('Y-m-d H:i:s'),
            'verified_by' => $_SESSION['user_id']
        ], ['id' => $paymentId]);
        
        // Update user status - get plan name from subscription_plans
        $planName = $this->db->get('subscription_plans', 'name', ['id' => $payment['plan_id']]) ?? 'premium';
        $this->db->update('users', [
            'payment_status' => 'paid',
            'subscription_plan' => $planName
        ], ['id' => $payment['user_id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment approved successfully'
        ]);
        exit;
    }
    
    /**
     * Reject a payment
     */
    public function reject($paymentId)
    {
        header('Content-Type: application/json');
        
        if (!$this->verifyCsrfToken()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $reason = trim($input['reason'] ?? '');
        
        $payment = $this->db->get('subscription_payments', ['id', 'user_id', 'status'], ['id' => $paymentId]);
        
        if (!$payment) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Payment not found']);
            exit;
        }
        
        if ($payment['status'] === 'completed') {
            echo json_encode(['success' => false, 'message' => 'Cannot reject an approved payment']);
            exit;
        }
        
        // Update payment status
        $this->db->update('subscription_payments', [
            'status' => 'failed',
            'rejection_reason' => $reason ?: 'Rejected by admin',
            'verified_at' => date('Y-m-d H:i:s'),
            'verified_by' => $_SESSION['user_id']
        ], ['id' => $paymentId]);
        
        // Update user status
        $this->db->update('users', [
            'payment_status' => 'failed'
        ], ['id' => $payment['user_id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment rejected'
        ]);
        exit;
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

    private function generateCsrfToken()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    private function verifyCsrfToken()
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return $token && hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
}
