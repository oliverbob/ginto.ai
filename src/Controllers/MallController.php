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

    /**
     * Resolve nearest known barangay ID from coordinates (falls back to session if not explicit).
     * Helps marketplace home and API to ensure seller zone checks are accurate.
     */
    private function resolveBarangayFromCoords(float $lat, float $lng, float $radiusKm = 10): ?int
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        try {
            // First attempt: use explicit radius_m if available to improve accuracy.
            $row = $this->db->query("\n                SELECT id, ROUND(6371000 * 2 * ASIN(SQRT(\n                        POWER(SIN(RADIANS(:lat - lat) / 2), 2) +\n                        COS(RADIANS(lat)) * COS(RADIANS(:lat2)) *\n                        POWER(SIN(RADIANS(:lng - lng) / 2), 2)\n                    ))) AS dist_m, COALESCE(radius_m, 0) AS radius_m\n                FROM barangays\n                WHERE is_active = 1\n                  AND COALESCE(radius_m, 0) > 0\n                  AND lat BETWEEN :lat_min AND :lat_max\n                  AND lng BETWEEN :lng_min AND :lng_max\n                HAVING dist_m <= radius_m\n                ORDER BY dist_m ASC\n                LIMIT 1\n            ", [
                ':lat'     => $lat,
                ':lat2'    => $lat,
                ':lng'     => $lng,
                ':lat_min' => $lat - $radiusKm / 111.0,
                ':lat_max' => $lat + $radiusKm / 111.0,
                ':lng_min' => $lng - $radiusKm / (111.0 * cos(deg2rad($lat))),
                ':lng_max' => $lng + $radiusKm / (111.0 * cos(deg2rad($lat))),
            ])->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                // Fallback to nearest barangay if no explicit radius match exists.
                $row = $this->db->query("\n                    SELECT id, ROUND(6371000 * 2 * ASIN(SQRT(\n                            POWER(SIN(RADIANS(:lat - lat) / 2), 2) +\n                            COS(RADIANS(lat)) * COS(RADIANS(:lat2)) *\n                            POWER(SIN(RADIANS(:lng - lng) / 2), 2)\n                        ))) AS dist_m\n                    FROM barangays\n                    WHERE is_active = 1\n                      AND lat BETWEEN :lat_min AND :lat_max\n                      AND lng BETWEEN :lng_min AND :lng_max\n                    ORDER BY dist_m ASC\n                    LIMIT 1\n                ", [
                    ':lat'     => $lat,
                    ':lat2'    => $lat,
                    ':lng'     => $lng,
                    ':lat_min' => $lat - $radiusKm / 111.0,
                    ':lat_max' => $lat + $radiusKm / 111.0,
                    ':lng_min' => $lng - $radiusKm / (111.0 * cos(deg2rad($lat))),
                    ':lng_max' => $lng + $radiusKm / (111.0 * cos(deg2rad($lat))),
                ])->fetch(\PDO::FETCH_ASSOC);
            }

            if (!$row) return null;
            if (isset($row['dist_m']) && (float)$row['dist_m'] > ($radiusKm * 1000)) {
                return null;
            }

            return (int)$row['id'];
        } catch (\Throwable $e) {
            error_log('MallController::resolveBarangayFromCoords error: ' . $e->getMessage());
            return null;
        }
    }

    public function marketplace()
    {
        // Resolve buyer's pinned barangay (session > user profile)
        $barangayId = (int)($_SESSION['buyer_barangay_id'] ?? 0);
        $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

        if ($barangayId <= 0 && $userId > 0) {
            $userRow = $this->db->get('users', ['buyer_barangay_id'], ['id' => $userId]);
            $barangayId = (int)($userRow['buyer_barangay_id'] ?? 0);
            if ($barangayId > 0) {
                $_SESSION['buyer_barangay_id'] = $barangayId;
            }
        }

        // If session barangay is invalid or inactive (e.g. duplicate id 22), fall back to GPS resolve.
        $barangayRow = null;
        if ($barangayId > 0) {
            $barangayRow = $this->db->get('barangays', ['id','is_active','lat','lng'], ['id' => $barangayId]);
            if (!$barangayRow || empty($barangayRow['is_active'])) {
                $barangayId = 0;
                unset($_SESSION['buyer_barangay_id']);
            }
        }

        // If user has a saved lat/lng but no valid tagged barangay, resolve nearest within 10km.
        if ($barangayId <= 0 && !empty($_SESSION['buyer_lat']) && !empty($_SESSION['buyer_lng'])) {
            $coordsBarangay = $this->resolveBarangayFromCoords((float)$_SESSION['buyer_lat'], (float)$_SESSION['buyer_lng'], 10);
            if ($coordsBarangay) {
                $barangayId = $coordsBarangay;
                $_SESSION['buyer_barangay_id'] = $barangayId;
            }
        }

        $currentBarangay = null;
        if ($barangayId > 0) {
            $currentBarangay = $this->db->get('barangays', ['id', 'name', 'city', 'province', 'region'], [
                'id' => $barangayId, 'is_active' => 1
            ]);
        }

        // Load categories and published products from DB
        $categories = $this->db->select('categories', '*', ['ORDER' => ['name' => 'ASC']]) ?: [];

        $productModel = new \Ginto\Models\Product();
        $listOpts = ['limit' => 48];
        if ($barangayId > 0) $listOpts['barangay_id'] = $barangayId;
        $products = $productModel->list($listOpts);

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
            'categories'  => $categories,
            'products'    => $products,
            'csrf_token'  => generateCsrfToken(),
            'title'       => 'ePower Mall',
            'mall_unread_notifications' => $userId > 0 ? $commerce->getMallUnreadNotificationCount($userId) : 0,
            'mall_notifications'        => $userId > 0 ? $commerce->getMallNotifications($userId) : [],
            'mall_wallet_balance'       => (float)($walletSummary['account']['balance'] ?? 0),
            'current_barangay'          => $currentBarangay,
            'buyer_barangay_id'         => $barangayId,
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

    public function apiHeaderData(): void
    {
        header('Content-Type: application/json');
        $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        if (!$userId) {
            echo json_encode(['balance' => '0.00', 'notif_count' => 0, 'cart_count' => 0]);
            return;
        }
        try {
            $commerce = new MallCommerceService($this->db);
            $walletSummary = $commerce->getWalletSummary($userId);
            $notifCount = $commerce->getMallUnreadNotificationCount($userId);
            $cartCount = (int)($this->db->count('cart_items', ['user_id' => $userId]) ?? 0);
            echo json_encode([
                'balance'     => number_format((float)($walletSummary['account']['balance'] ?? 0), 2),
                'notif_count' => (int)$notifCount,
                'cart_count'  => $cartCount,
            ]);
        } catch (\Throwable $e) {
            error_log('MallController::apiHeaderData error: ' . $e->getMessage());
            echo json_encode(['balance' => '0.00', 'notif_count' => 0, 'cart_count' => 0]);
        }
    }

    /**
     * POST /api/mall/cart/sync — called by frontend saveCart() after localStorage update.
     * Stores the cart count per user and triggers a silent FCM push to their other devices.
     */
    public function apiCartSync(): void
    {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); echo json_encode(['ok' => false]); return;
        }
        $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        if (!$userId) {
            echo json_encode(['ok' => false, 'reason' => 'not_logged_in']); return;
        }
        $body  = (string)file_get_contents('php://input');
        $input = $body ? json_decode($body, true) : [];
        $count = max(0, (int)($input['count'] ?? $_POST['count'] ?? 0));

        // Upsert cart count in DB for cross-device polling
        try {
            $now = date('Y-m-d H:i:s');
            $this->db->pdo()->prepare(
                "INSERT INTO cart_items (user_id, count, updated_at)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE count = VALUES(count), updated_at = VALUES(updated_at)"
            )->execute([$userId, $count, $now]);
        } catch (\Throwable $e) {
            // Non-critical — cart_items table may not have this schema; continue to FCM
        }

        // Silent FCM push to other devices so their cart badge updates
        try {
            $pushService = new \Ginto\Services\MallPushService($this->db);
            $pushService->sendSilentCartUpdate($userId, $count);
        } catch (\Throwable $e) {
            error_log('apiCartSync FCM error: ' . $e->getMessage());
        }

        echo json_encode(['ok' => true, 'count' => $count]);
    }

    /**
     * POST /api/mall/cart/refresh — refresh cart items from authoritative product DB.
     * Request: { cart: [{id, qty}] }
     * Returns updated cart items with current price, available flag, and subtotal.
     */
    public function apiCartRefresh(): void
    {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); echo json_encode(['ok' => false, 'error' => 'POST required']); return;
        }

        $body  = (string)file_get_contents('php://input');
        $input = $body ? json_decode($body, true) : [];
        $cart  = is_array($input['cart'] ?? null) ? $input['cart'] : [];

        if (empty($cart)) {
            echo json_encode(['ok' => true, 'cart' => [], 'total' => '0.00']);
            return;
        }

        $productIds = array_values(array_unique(array_filter(array_map(function($item){ return isset($item['id']) ? (int)$item['id'] : 0; }, $cart))));
        if (empty($productIds)) {
            echo json_encode(['ok' => true, 'cart' => [], 'total' => '0.00']);
            return;
        }

        $products = $this->db->select('products', ['id','title','price','currency','status','is_visible','quantity','image_path','images'], ['id' => $productIds]) ?: [];
        $productMap = [];
        foreach ($products as $p) {
            $productMap[(int)$p['id']] = $p;
        }

        $updatedCart = [];
        $total = 0.00;
        foreach ($cart as $item) {
            $id = isset($item['id']) ? (int)$item['id'] : 0;
            $qty = max(1, (int)($item['qty'] ?? $item['quantity'] ?? 1));
            if (!$id || !isset($productMap[$id])) {
                continue;
            }
            $p = $productMap[$id];
            $available = ($p['status'] ?? '') === 'published' && ((int)($p['is_visible'] ?? 0) === 1);
            if (!$available) continue;

            $unitPrice = round((float)($p['price'] ?? 0) * 1.15, 2);
            $lineTotal = $unitPrice * $qty;
            $total += $lineTotal;

            $updatedCart[] = [
                'id' => $id,
                'title' => (string)($p['title'] ?? ''),
                'qty' => $qty,
                'price' => $unitPrice,
                'currency' => $p['currency'] ?? 'PHP',
                'img' => $this->getProductMainImage($p),
                'available' => true,
                'line_total' => round($lineTotal, 2),
            ];
        }

        echo json_encode(['ok' => true, 'cart' => $updatedCart, 'total' => number_format($total, 2, '.', ''), 'currency' => 'PHP']);
    }

    private function getProductMainImage(array $product): string
    {
        $img = '';
        if (!empty($product['images'])) {
            $decoded = json_decode((string)$product['images'], true);
            if (is_array($decoded)) {
                $imgsArr = array_values(array_filter($decoded));
                if (!empty($imgsArr)) {
                    $img = (string)$imgsArr[0];
                }
            }
        }
        if (!$img && !empty($product['image_path'])) {
            $img = (string)$product['image_path'];
        }
        return trim($img);
    }

    /**
     * GET /api/mall/products — paginated product list for infinite scroll + typeahead
     * Query params: page, limit, cat (category_id), search, sort, seller_id
     */
    public function apiProducts(): void
    {
        header('Content-Type: application/json');
        $page     = max(1, (int)($_GET['page']   ?? 1));
        $limit    = min(48, max(4, (int)($_GET['limit'] ?? 24)));
        $offset   = ($page - 1) * $limit;
        $catId    = isset($_GET['cat']) && is_numeric($_GET['cat']) ? (int)$_GET['cat'] : null;
        $search   = trim(strip_tags($_GET['search'] ?? ''));
        $sort     = preg_replace('/[^a-z_]/', '', strtolower($_GET['sort'] ?? 'default'));
        $sellerId = isset($_GET['seller_id']) && is_numeric($_GET['seller_id']) ? (int)$_GET['seller_id'] : null;
        $barangayId = isset($_GET['barangay_id']) && is_numeric($_GET['barangay_id']) ? (int)$_GET['barangay_id'] : null;

        // Also accept barangay from session (set by GPS detect or manual selector)
        if (!$barangayId && !empty($_SESSION['buyer_barangay_id'])) {
            $barangayId = (int)$_SESSION['buyer_barangay_id'];
        }

        // If an id is set but inactive, clear it (e.g., previously active id 22 duplicate)
        if ($barangayId > 0) {
            $row = $this->db->get('barangays', ['id','is_active'], ['id' => $barangayId]);
            if (!$row || empty($row['is_active'])) {
                $barangayId = 0;
                unset($_SESSION['buyer_barangay_id']);
            }
        }

        // Fallback: if user has GPS coords and no valid barangay, resolve nearest within 10km
        if (!$barangayId && !empty($_SESSION['buyer_lat']) && !empty($_SESSION['buyer_lng'])) {
            $coordsBarangay = $this->resolveBarangayFromCoords((float)$_SESSION['buyer_lat'], (float)$_SESSION['buyer_lng'], 10);
            if ($coordsBarangay) {
                $barangayId = $coordsBarangay;
                $_SESSION['buyer_barangay_id'] = $barangayId;
            }
        }

        try {
            $productModel = new \Ginto\Models\Product();
            $opts = ['offset' => $offset, 'limit' => $limit];
            if ($catId)      $opts['category_id'] = $catId;
            if ($search)     $opts['search']      = $search;
            if ($sort !== 'default') $opts['sort'] = $sort;
            if ($sellerId)   $opts['seller_id']   = $sellerId;
            if ($barangayId) $opts['barangay_id'] = $barangayId;

            $products = $productModel->list($opts);
            $locationFallback = false;

            // Attach seller storefront slugs
            $sellerIds = array_values(array_unique(array_filter(array_column($products, 'seller_id'))));
            $sellerMap = [];
            if (!empty($sellerIds)) {
                $storefronts = $this->db->select('seller_storefronts', ['user_id', 'slug', 'display_name'], ['user_id' => $sellerIds, 'is_active' => 1]) ?: [];
                foreach ($storefronts as $sf) {
                    $sellerMap[(int)$sf['user_id']] = ['slug' => $sf['slug'], 'name' => $sf['display_name']];
                }
            }

            $out = [];
            foreach ($products as $p) {
                $sid = (int)($p['seller_id'] ?? 0);
                $imgs_arr = [];
                $img = null;
                if (!empty($p['images'])) {
                    $decoded = json_decode($p['images'], true);
                    if (is_array($decoded)) { $imgs_arr = array_values(array_filter($decoded)); $img = $imgs_arr[0] ?? null; }
                }
                if (!$img && !empty($p['image_path'])) { $img = $p['image_path']; if (empty($imgs_arr)) $imgs_arr = [$img]; }
                $out[] = [
                    'id'          => (int)$p['id'],
                    'title'       => $p['title'] ?? '',
                    'price'       => round((float)($p['price'] ?? 0) * 1.15, 2),
                    'currency'    => $p['currency'] ?? 'PHP',
                    'cat'         => isset($p['category_id']) ? (int)$p['category_id'] : null,
                    'rating'      => (float)($p['rating'] ?? 0),
                    'img'         => $img,
                    'imgs'        => $imgs_arr,
                    'desc'        => $p['short_description'] ?? '',
                    'slug'        => $p['slug'] ?? '',
                    'badge'       => $p['badge'] ?? null,
                    'seller_slug'  => $sellerMap[$sid]['slug'] ?? null,
                    'seller_name'  => $sellerMap[$sid]['name'] ?? null,
                    'seller_id'    => $sid,
                    'product_type' => $p['product_type'] ?? 'physical',
                ];
            }

            // has_more: try to fetch one more to check
            $hasMore = count($products) === $limit;
            $resp = ['products' => $out, 'page' => $page, 'has_more' => $hasMore];
            if ($barangayId) {
                $brow = $this->db->get('barangays', ['id','name','city','province'], ['id' => $barangayId, 'is_active' => 1]);
                $resp['barangay'] = $brow ?: null;
            }
            echo json_encode($resp);
        } catch (\Throwable $e) {
            error_log('MallController::apiProducts error: ' . $e->getMessage());
            echo json_encode(['products' => [], 'page' => $page, 'has_more' => false]);
        }
    }

    /**
     * GET /mall/product/{slug} — SEO + social-friendly single product page
     */
    public function productPage(string $slug = ''): void
    {
        $slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($slug));
        if ($slug === '') { http_response_code(404); echo '<h1>Product not found</h1>'; return; }

        try {
            $productModel = new \Ginto\Models\Product();
            $product = $productModel->findBySlug($slug);
            if (!$product || ($product['status'] ?? '') !== 'published') {
                http_response_code(404); echo '<h1>Product not found</h1>'; return;
            }

            $sellerId  = (int)($product['seller_id'] ?? 0);
            $storefront = $sellerId > 0 ? ($this->db->get('seller_storefronts', '*', ['user_id' => $sellerId, 'is_active' => 1]) ?: []) : [];
            $seller     = $sellerId > 0 ? ($this->db->get('users', ['id', 'username', 'fullname'], ['id' => $sellerId]) ?: []) : [];

            // Related products from same seller (exclude this product)
            $related = [];
            if ($sellerId > 0) {
                $related = $productModel->list(['seller_id' => $sellerId, 'limit' => 6]);
                $related = array_filter($related, fn($r) => (int)($r['id'] ?? 0) !== (int)$product['id']);
                $related = array_values($related);
            }

            // Build OG image from first product image or mall-og
            $imgs = [];
            if (!empty($product['images'])) {
                $d = json_decode($product['images'], true);
                if (is_array($d)) $imgs = array_values(array_filter($d));
            }
            if (empty($imgs) && !empty($product['image_path'])) $imgs = [$product['image_path']];
            $_proto  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $_host   = $_SERVER['HTTP_HOST'] ?? 'silverqueen.pro';
            $ogImg   = !empty($imgs[0]) ? (str_starts_with($imgs[0], 'http') ? $imgs[0] : ($_proto . '://' . $_host . $imgs[0])) : '/assets/images/mall-og.png';
            $ogTitle = ($product['title'] ?? 'Product') . ' — Ginto Mall';
            $ogDesc  = $product['short_description'] ?: strip_tags(substr($product['description'] ?? '', 0, 160));
            $ogUrl   = $_proto . '://' . $_host . '/mall/product/' . $slug;

            $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            $commerce = new \Ginto\Services\MallCommerceService($this->db);
            $walletSummary = $userId > 0 ? $commerce->getWalletSummary($userId) : ['account' => []];

            $this->view('mall/product', [
                'title'       => $ogTitle,
                'ogTitle'     => $ogTitle,
                'ogDesc'      => $ogDesc ?: 'Buy ' . ($product['title'] ?? 'this product') . ' on Ginto Mall.',
                'ogImage'     => $ogImg,
                'ogType'      => 'product',
                'ogUrl'       => $ogUrl,
                'product'     => $product,
                'seller'      => $seller,
                'storefront'  => $storefront,
                'relatedProducts' => $related,
                'csrf_token'  => generateCsrfToken(),
                'categories'  => $this->db->select('categories', '*', ['ORDER' => ['name' => 'ASC']]) ?: [],
                'mall_unread_notifications' => $userId > 0 ? $commerce->getMallUnreadNotificationCount($userId) : 0,
                'mall_notifications' => $userId > 0 ? $commerce->getMallNotifications($userId) : [],
                'mall_wallet_balance' => (float)($walletSummary['account']['balance'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            error_log('MallController::productPage error: ' . $e->getMessage());
            http_response_code(500); echo '<h1>Error loading product</h1>';
        }
    }

    /**
     * GET /mall/notifications — full notifications page
     */
    public function notificationsPage(): void
    {
        $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        if (!$userId) {
            header('Location: /login?redirect=/mall/notifications'); exit;
        }

        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;

        try {
            $commerce = new \Ginto\Services\MallCommerceService($this->db);
            $walletSummary = $commerce->getWalletSummary($userId);

            // Fetch one extra to detect has_more
            $notifs = $this->db->select('notifications', '*', [
                'user_id'  => $userId,
                'ORDER'    => ['created_at' => 'DESC'],
                'LIMIT'    => [($page - 1) * $limit, $limit + 1],
            ]) ?: [];

            $hasMore = count($notifs) > $limit;
            if ($hasMore) $notifs = array_slice($notifs, 0, $limit);

            $unreadCount = $commerce->getMallUnreadNotificationCount($userId);

            $this->view('mall/notifications', [
                'title'           => 'Notifications — Ginto Mall',
                'notifications'   => $notifs,
                'unreadCount'     => $unreadCount,
                'page'            => $page,
                'hasMore'         => $hasMore,
                'csrf_token'      => generateCsrfToken(),
                'categories'      => $this->db->select('categories', '*', ['ORDER' => ['name' => 'ASC']]) ?: [],
                'mall_unread_notifications' => $unreadCount,
                'mall_notifications'        => array_slice($notifs, 0, 8),
                'mall_wallet_balance'       => (float)($walletSummary['account']['balance'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            error_log('MallController::notificationsPage error: ' . $e->getMessage());
            http_response_code(500); echo '<h1>Error loading notifications</h1>';
        }
    }
}

