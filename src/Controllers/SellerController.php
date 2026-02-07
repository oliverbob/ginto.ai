<?php
namespace Ginto\Controllers;

use Ginto\Models\Product;
use Ginto\Core\Database;

class SellerController extends \Core\Controller
{
    private $db;

    public function __construct($db = null)
    {
        parent::__construct();
        $this->db = $db ?? Database::getInstance();
    }

    public function kycForm()
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: /login'); exit;
        }
        $csrf = generateCsrfToken();
        $userId = (int)$_SESSION['user_id'];
        $kyc = $this->db->get('kyc_profiles', '*', ['user_id' => $userId]);
        return $this->view('mall/kyc', ['csrf_token' => $csrf, 'kyc' => $kyc]);
    }

    public function submitKyc()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Method not allowed'; return; }
        if (empty($_SESSION['user_id'])) { http_response_code(401); echo 'Login required'; return; }

        $token = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($token)) { http_response_code(400); echo 'Invalid CSRF token'; return; }

        $userId = (int)$_SESSION['user_id'];
        $first = trim($_POST['first_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $dob = trim($_POST['dob'] ?? null);
        $country = trim($_POST['country'] ?? null);
        $identifier = trim($_POST['identifier'] ?? null);

        // Handle uploaded documents
        $docs = [];
        if (!empty($_FILES['documents'])) {
            $files = $_FILES['documents'];
            $uploadDir = (defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 2) . '/../storage') . '/kyc/' . $userId . '/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                $name = preg_replace('/[^a-zA-Z0-9._-]/', '-', basename($files['name'][$i]));
                $target = $uploadDir . uniqid() . '_' . $name;
                if (move_uploaded_file($files['tmp_name'][$i], $target)) {
                    $docs[] = str_replace((defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 2) . '/../storage'), '/storage', $target);
                }
            }
        }

        $payload = [
            'user_id' => $userId,
            'first_name' => $first ?: null,
            'last_name' => $last ?: null,
            'dob' => $dob ?: null,
            'country' => $country ?: null,
            'identifier' => $identifier ?: null,
            'documents' => !empty($docs) ? json_encode($docs) : null,
            'submitted_at' => date('Y-m-d H:i:s'),
            'status' => 'pending'
        ];

        $existing = $this->db->get('kyc_profiles', 'id', ['user_id' => $userId]);
        if ($existing) {
            $this->db->update('kyc_profiles', $payload, ['user_id' => $userId]);
        } else {
            $this->db->insert('kyc_profiles', $payload);
        }

        header('Location: /marketplace/sellers/kyc');
        exit;
    }

    public function products()
    {
        if (empty($_SESSION['user_id'])) { header('Location: /login'); exit; }
        $userId = (int)$_SESSION['user_id'];

        try {
            $productModel = new Product();
            $products = $productModel->list(['seller_id' => $userId, 'status' => 'published']);
            // Also include drafts for seller
            $drafts = $productModel->list(['seller_id' => $userId, 'status' => 'draft']);

            // Enrich with seller metadata for a richer seller dashboard
            $kycRow = $this->db->get('kyc_profiles', ['status'], ['user_id' => $userId]);
            $subRow = $this->db->get('seller_subscriptions', ['status','next_billing_at'], ['user_id' => $userId]);
            $kyc_status = is_array($kycRow) ? ($kycRow['status'] ?? 'pending') : 'pending';
            $subscription_status = is_array($subRow) ? ($subRow['status'] ?? 'inactive') : 'inactive';

            $csrf = generateCsrfToken();
            return $this->view('mall/seller_products', [
                'products' => $products,
                'drafts' => $drafts,
                'csrf_token' => $csrf,
                'kyc_status' => $kyc_status,
                'subscription_status' => $subscription_status,
                'next_billing_at' => $subRow['next_billing_at'] ?? null
            ]);
        } catch (\Throwable $e) {
            // Log full stack trace and user context for debugging
            error_log('SellerController::products error: user=' . $userId . ' msg=' . $e->getMessage() . "\n" . $e->getTraceAsString());

            // Also write to a dedicated marketplace log file for easy retrieval
            $logFile = (defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__,2) . '/../storage') . '/logs/marketplace_errors.log';
            @mkdir(dirname($logFile), 0755, true);
            @file_put_contents($logFile, date('c') . ' - user=' . $userId . ' - ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);

            http_response_code(500);
            echo '<div style="max-width:800px;margin:40px auto;padding:20px;background:#fff;border-radius:8px;color:#111"><h2>Server error</h2><p>Sorry, we could not load your products right now. The incident has been logged.</p><p><a href="/dashboard">Return to dashboard</a></p></div>';
            return;
        }
    }

    public function productNew()
    {
        if (empty($_SESSION['user_id'])) { header('Location: /login'); exit; }
        $csrf = generateCsrfToken();
        $categories = $this->db->select('categories', '*') ?: [];
        return $this->view('mall/product_form', ['csrf_token' => $csrf, 'categories' => $categories]);
    }

    public function productCreate()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Method not allowed'; return; }
        if (empty($_SESSION['user_id'])) { http_response_code(401); echo 'Login required'; return; }

        $token = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($token)) { http_response_code(400); echo 'Invalid CSRF token'; return; }

        $userId = (int)$_SESSION['user_id'];
        $user = $this->db->get('users', ['id','role_id'], ['id' => $userId]);
        $isAdmin = in_array($user['role_id'] ?? 0, [1,2]);

        // Check KYC status for non-admins
        if (!$isAdmin) {
            $kycRow = $this->db->get('kyc_profiles', ['status'], ['user_id' => $userId]);
            $kycStatus = is_array($kycRow) ? ($kycRow['status'] ?? null) : $kycRow;
            if (empty($kycStatus) || $kycStatus !== 'approved') {
                http_response_code(403);
                echo 'KYC not approved - you must complete KYC to create products.'; return;
            }
        }

        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $short = trim($_POST['short_description'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $currency = $_POST['currency'] ?? 'USD';
        $category = intval($_POST['category_id'] ?? 0) ?: null;

        // Handle images upload (multiple)
        $imagesArray = [];
        if (!empty($_FILES['images'])) {
            $files = $_FILES['images'];
            $uploadDir = (defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__,2) . '/../storage') . '/products/' . $userId . '/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                $name = preg_replace('/[^a-zA-Z0-9._-]/', '-', basename($files['name'][$i]));
                $target = $uploadDir . uniqid() . '_' . $name;
                if (move_uploaded_file($files['tmp_name'][$i], $target)) {
                    $imagesArray[] = str_replace((defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__,2) . '/../storage'), '/storage', $target);
                }
            }
        }

        $productModel = new Product();
        $data = [
            'seller_id' => $userId,
            'title' => $title,
            'slug' => $slug ?: null,
            'short_description' => $short ?: null,
            'description' => $description ?: null,
            'price' => $price,
            'currency' => $currency,
            'category_id' => $category,
            'images' => $imagesArray,
            'status' => 'draft',
            'is_visible' => 0
        ];

        $created = $productModel->create($data);
        if (!$created) {
            http_response_code(500); echo 'Failed to create product'; return;
        }

        header('Location: /marketplace/sellers/products'); exit;
    }
}
