<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;
use Core\Controller;

class PromoCodesAdminController extends \Core\Controller
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->requireAdmin();
    }

    /**
     * List all promo codes
     */
    public function index()
    {
        $filter = $_GET['filter'] ?? 'active'; // active, expired, all
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        
        // Build where clause - use Manila timezone
        $where = [];
        $tz = new \DateTimeZone('Asia/Manila');
        $now = (new \DateTime('now', $tz))->format('Y-m-d H:i:s');
        
        if ($filter === 'active') {
            $where['is_active'] = 1;
        } elseif ($filter === 'inactive') {
            $where['is_active'] = 0;
        } elseif ($filter === 'expired') {
            $where['valid_until[<]'] = $now;
        }
        
        $where['ORDER'] = ['created_at' => 'DESC'];
        $where['LIMIT'] = [$offset, $perPage];
        
        // Get promo codes
        $promoCodes = $this->db->select('promo_codes', '*', $where);
        
        // Get total count for pagination
        unset($where['ORDER'], $where['LIMIT']);
        $totalCount = $this->db->count('promo_codes', $where);
        $totalPages = ceil($totalCount / $perPage);
        
        // Count by status for tabs
        $counts = [
            'active' => $this->db->count('promo_codes', ['is_active' => 1]),
            'inactive' => $this->db->count('promo_codes', ['is_active' => 0]),
            'expired' => $this->db->count('promo_codes', ['valid_until[<]' => $now]),
            'all' => $this->db->count('promo_codes')
        ];
        
        $this->view('admin/promocodes/index', [
            'title' => 'Promo Codes',
            'promoCodes' => $promoCodes,
            'filter' => $filter,
            'counts' => $counts,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    /**
     * Create a new promo code (POST)
     */
    public function store()
    {
        if (!$this->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $code = strtoupper(trim($_POST['code'] ?? ''));
        $description = trim($_POST['description'] ?? '');
        $discountType = $_POST['discount_type'] ?? 'percentage';
        $discountValue = floatval($_POST['discount_value'] ?? 0);
        $minPackageAmount = !empty($_POST['min_package_amount']) ? floatval($_POST['min_package_amount']) : null;
        $maxUses = !empty($_POST['max_uses']) ? intval($_POST['max_uses']) : null;
        $maxUsesPerUser = !empty($_POST['max_uses_per_user']) ? intval($_POST['max_uses_per_user']) : 1;
        $validFrom = !empty($_POST['valid_from']) ? $_POST['valid_from'] : null;
        $validUntil = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
        $applicablePackages = !empty($_POST['applicable_packages']) ? json_encode(array_map('trim', explode(',', $_POST['applicable_packages']))) : null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($code)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Promo code is required']);
            return;
        }

        // Check if code already exists
        $existing = $this->db->get('promo_codes', 'id', ['code' => $code]);
        if ($existing) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Promo code already exists']);
            return;
        }

        $data = [
            'code' => $code,
            'description' => $description ?: null,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'min_package_amount' => $minPackageAmount,
            'max_uses' => $maxUses,
            'max_uses_per_user' => $maxUsesPerUser,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'applicable_packages' => $applicablePackages,
            'created_by' => $_SESSION['user_id'] ?? null,
            'is_active' => $isActive,
            'created_at' => (new \DateTime('now', new \DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s')
        ];

        $this->db->insert('promo_codes', $data);
        $newId = $this->db->id();

        if ($newId) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Promo code created successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to create promo code']);
        }
    }

    /**
     * Update a promo code (POST)
     */
    public function update($id)
    {
        if (!$this->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $existing = $this->db->get('promo_codes', '*', ['id' => $id]);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Promo code not found']);
            return;
        }

        $data = [
            'description' => trim($_POST['description'] ?? ''),
            'discount_type' => $_POST['discount_type'] ?? 'percentage',
            'discount_value' => floatval($_POST['discount_value'] ?? 0),
            'min_package_amount' => !empty($_POST['min_package_amount']) ? floatval($_POST['min_package_amount']) : null,
            'max_uses' => !empty($_POST['max_uses']) ? intval($_POST['max_uses']) : null,
            'max_uses_per_user' => !empty($_POST['max_uses_per_user']) ? intval($_POST['max_uses_per_user']) : 1,
            'valid_from' => !empty($_POST['valid_from']) ? $_POST['valid_from'] : null,
            'valid_until' => !empty($_POST['valid_until']) ? $_POST['valid_until'] : null,
            'applicable_packages' => !empty($_POST['applicable_packages']) ? json_encode(array_map('trim', explode(',', $_POST['applicable_packages']))) : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        $this->db->update('promo_codes', $data, ['id' => $id]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Promo code updated successfully']);
    }

    /**
     * Toggle promo code active status (POST)
     */
    public function toggle($id)
    {
        if (!$this->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $existing = $this->db->get('promo_codes', ['id', 'is_active'], ['id' => $id]);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Promo code not found']);
            return;
        }

        $newStatus = $existing['is_active'] ? 0 : 1;
        $this->db->update('promo_codes', ['is_active' => $newStatus], ['id' => $id]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'is_active' => $newStatus, 'message' => $newStatus ? 'Promo code activated' : 'Promo code deactivated']);
    }

    /**
     * Delete a promo code (POST)
     */
    public function delete($id)
    {
        if (!$this->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $existing = $this->db->get('promo_codes', 'id', ['id' => $id]);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Promo code not found']);
            return;
        }

        $this->db->delete('promo_codes', ['id' => $id]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Promo code deleted successfully']);
    }

    /**
     * API: Validate a promo code (for registration form)
     */
    public function validate()
    {
        $code = strtoupper(trim($_POST['code'] ?? $_GET['code'] ?? ''));
        $packageAmount = floatval($_POST['package_amount'] ?? $_GET['package_amount'] ?? 0);
        $packageName = trim($_POST['package_name'] ?? $_GET['package_name'] ?? '');

        if (empty($code)) {
            header('Content-Type: application/json');
            echo json_encode(['valid' => false, 'error' => 'Promo code is required']);
            return;
        }

        // Use Manila timezone for date comparisons
        $tz = new \DateTimeZone('Asia/Manila');
        $now = (new \DateTime('now', $tz))->format('Y-m-d H:i:s');
        $promo = $this->db->get('promo_codes', '*', ['code' => $code]);

        if (!$promo) {
            header('Content-Type: application/json');
            echo json_encode(['valid' => false, 'error' => 'Invalid promo code']);
            return;
        }

        // Check if active
        if (!$promo['is_active']) {
            header('Content-Type: application/json');
            echo json_encode(['valid' => false, 'error' => 'This promo code is no longer active']);
            return;
        }

        // Check validity dates
        if ($promo['valid_from'] && $promo['valid_from'] > $now) {
            header('Content-Type: application/json');
            echo json_encode(['valid' => false, 'error' => 'This promo code is not yet valid']);
            return;
        }

        if ($promo['valid_until'] && $promo['valid_until'] < $now) {
            header('Content-Type: application/json');
            echo json_encode(['valid' => false, 'error' => 'This promo code has expired']);
            return;
        }

        // Check max uses
        if ($promo['max_uses'] !== null && $promo['used_count'] >= $promo['max_uses']) {
            header('Content-Type: application/json');
            echo json_encode(['valid' => false, 'error' => 'This promo code has reached its usage limit']);
            return;
        }

        // Check minimum package amount
        if ($promo['min_package_amount'] !== null && $packageAmount < $promo['min_package_amount']) {
            header('Content-Type: application/json');
            echo json_encode(['valid' => false, 'error' => 'Minimum package amount of ₱' . number_format($promo['min_package_amount'], 2) . ' required']);
            return;
        }

        // Check applicable packages
        if ($promo['applicable_packages'] && $packageName) {
            $applicablePackages = json_decode($promo['applicable_packages'], true);
            if (is_array($applicablePackages) && !in_array($packageName, $applicablePackages)) {
                header('Content-Type: application/json');
                echo json_encode(['valid' => false, 'error' => 'This promo code is not valid for the selected package']);
                return;
            }
        }

        // Calculate discount
        $discountAmount = 0;
        if ($promo['discount_type'] === 'percentage') {
            $discountAmount = $packageAmount * ($promo['discount_value'] / 100);
        } else {
            $discountAmount = $promo['discount_value'];
        }

        header('Content-Type: application/json');
        echo json_encode([
            'valid' => true,
            'code' => $promo['code'],
            'discount_type' => $promo['discount_type'],
            'discount_value' => $promo['discount_value'],
            'discount_amount' => round($discountAmount, 2),
            'description' => $promo['description'],
            'message' => $promo['discount_type'] === 'percentage' 
                ? $promo['discount_value'] . '% discount applied!' 
                : '₱' . number_format($promo['discount_value'], 2) . ' discount applied!'
        ]);
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

    private function verifyCsrfToken($token)
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
