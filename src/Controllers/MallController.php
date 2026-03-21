<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;
use Ginto\Services\MallCommerceService;

class MallController extends \Core\Controller
{
    private $db;

    public function __construct($db = null)
    {
        // Accept DB as an optional injection, fallback to Database::getInstance if not provided
        if ($db !== null) {
            $this->db = $db;
        } else {
            try {
                $this->db = Database::getInstance();
            } catch (\Throwable $e) {
                $this->db = null;
            }
        }
    }

    public function marketplace()
    {
        // Load categories and published products from DB
        $categories = $this->db->select('categories', '*', ['ORDER' => ['name' => 'ASC']]) ?: [];

        $productModel = new \Ginto\Models\Product();
        $products = $productModel->list(['limit' => 48]);

        // Attach seller storefront slugs to each product
        $sellerIds = array_values(array_unique(array_filter(array_column($products, 'seller_id'))));
        $sellerMap = [];
        if (!empty($sellerIds)) {
            $storefronts = $this->db->select('seller_storefronts', ['user_id', 'slug', 'display_name'], ['user_id' => $sellerIds, 'is_active' => 1]) ?: [];
            foreach ($storefronts as $sf) {
                $sellerMap[(int)$sf['user_id']] = ['slug' => $sf['slug'], 'name' => $sf['display_name']];
            }
        }
        foreach ($products as &$p) {
            $sid = (int)($p['seller_id'] ?? 0);
            $p['seller_slug'] = $sellerMap[$sid]['slug'] ?? null;
            $p['seller_name'] = $sellerMap[$sid]['name'] ?? null;
        }
        unset($p);

        $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        $commerce = new MallCommerceService($this->db);
        $walletSummary = $userId > 0 ? $commerce->getWalletSummary($userId) : ['account' => []];

        // Pass data to the view
        $this->view('mall/marketplace', [
            'categories' => $categories,
            'products' => $products,
            'csrf_token' => generateCsrfToken(),
            'title' => 'ePower Mall',
            'mall_unread_notifications' => $userId > 0 ? $commerce->getMallUnreadNotificationCount($userId) : 0,
            'mall_notifications' => $userId > 0 ? $commerce->getMallNotifications($userId) : [],
            'mall_wallet_balance' => (float)($walletSummary['account']['balance'] ?? 0),
        ]);
    }

    // Handle upload from marketplace modal (AJAX)
    public function upload()
    {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); return; }
        if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Login required']); return; }

        $token = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($token)) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token']); return; }

        $userId = (int)$_SESSION['user_id'];

        try {
            $user = $this->db->get('users', ['id','role_id'], ['id' => $userId]);
            $isAdmin = in_array($user['role_id'] ?? 0, [1,2]);

            // Ensure KYC for non-admins
            if (!$isAdmin) {
                $kycRow = $this->db->get('kyc_profiles', ['status'], ['user_id' => $userId]);
                $kycStatus = is_array($kycRow) ? ($kycRow['status'] ?? null) : $kycRow;
                if (empty($kycStatus) || $kycStatus !== 'approved') {
                    http_response_code(403); echo json_encode(['success'=>false,'message'=>'KYC not approved']); return;
                }
            }

            $title = trim($_POST['title'] ?? 'Untitled');
            $price = floatval($_POST['price'] ?? 0);
            $currency = $_POST['currency'] ?? 'USD';
            $category = intval($_POST['category_id'] ?? 0) ?: null;
            $short = trim($_POST['short_description'] ?? '');
            $description = trim($_POST['description'] ?? '');

            // Image
            $images = [];
            if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['image'];
                $uploadDir = (defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 2) . '/../storage') . '/products/' . $userId . '/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $name = preg_replace('/[^a-zA-Z0-9._-]/', '-', basename($file['name']));
                $target = $uploadDir . uniqid() . '_' . $name;
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $images[] = str_replace((defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 2) . '/../storage'), '/storage', $target);
                }
            }

            $productModel = new \Ginto\Models\Product();
            $data = [
                'seller_id' => $userId,
                'title' => $title,
                'short_description' => $short ?: null,
                'description' => $description ?: null,
                'price' => $price,
                'currency' => $currency,
                'category_id' => $category,
                'images' => $images,
                'status' => 'draft',
                'is_visible' => 0
            ];

            $created = $productModel->create($data);
            if (!$created) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Failed to create product']); return; }

            echo json_encode(['success'=>true,'product'=>$created]);
        } catch (\Throwable $e) {
            error_log('MallController::upload error: ' . $e->getMessage());
            http_response_code(500); echo json_encode(['success'=>false,'message'=>'Server error occurred']); return;
        }
    }
}
