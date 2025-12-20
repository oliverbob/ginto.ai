<?php
// Public route registrations extracted from `public/index.php` for easier management.
// This file expects the `req($router, $path, $handler)` helper to be defined
// by the including script (`public/index.php`) and a `$router` variable present.

use Ginto\Helpers\TransactionHelper;

// --- Public routes --------------------------------------------------------
// Root redirect
req($router, '/', function() {
    if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: /admin');
        exit;
    }
    header('Location: /register');
    exit;
});

// Login
req($router, '/login', function() use ($db, $countries) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller = new \Ginto\Controllers\UserController($db, $countries);
        $controller->loginAction($_POST);
    } else {
        \Ginto\Core\View::view('user/login', [
            'title' => 'Login'
        ]);
    }
});

// Full user network tree view
req($router, '/user/network-tree', function() use ($db) {
    if (empty($_SESSION['user_id'])) {
        header('Location: /login');
        exit;
    }
    $userId = $_SESSION['user_id'];
    $userModel = new \Ginto\Models\User();
    $user_data = $userModel->find($userId);
    $stats = [
        'direct_referrals' => $userModel->countDirectReferrals($userId),
    ];
    \Ginto\Core\View::view('user/network-tree', [
        'title' => 'Network Tree',
        'user_data' => $user_data,
        'current_user_id' => $userId,
        'stats' => $stats
    ]);
});

// Downline view (legacy route)
req($router, '/downline', function() use ($db, $countries) {
    if (empty($_SESSION['user_id'])) {
        header('Location: /login');
        exit;
    }
    $controller = new \Ginto\Controllers\UserController($db, $countries);
    return $controller->downlineAction();
});

// Logout
req($router, '/logout', function() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            $params['secure'] ?? false,
            $params['httponly'] ?? true
        );
    }
    session_unset();
    session_destroy();
    header('Location: /login');
    exit;
});

// Register
req($router, '/register', function() use ($db, $countries) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller = new \Ginto\Controllers\UserController($db, $countries);
        $controller->registerAction($_POST);
    } else {
        $refId = $_GET['ref'] ?? null;
        $detectedCountryCode = null;
        $levels = [];
        try {
            $levels = $db->select('tier_plans', ['id','name','cost_amount','cost_currency','commission_rate_json'], ['ORDER' => ['id' => 'ASC']]);
        } catch (Exception $e) {
            error_log('Warning: Could not load levels for register view: ' . $e->getMessage());
        }
        \Ginto\Core\View::view('user/register/register', [
            'title' => 'Register for Ginto',
            'ref_id' => $refId,
            'error' => null,
            'old' => [],
            'countries' => $countries,
            'default_country_code' => $detectedCountryCode,
            'levels' => $levels
        ]);
    }
});

// Dashboard
req($router, '/dashboard', function() use ($db, $countries) {
    if (empty($_SESSION['user_id'])) {
        header('Location: /login');
        exit;
    }

    $controller = new \Ginto\Controllers\UserController($db, $countries);
    $controller->dashboardAction($_SESSION['user_id']);
});

// Expose admin/system logs under /dashboard/logs using playground template
req($router, '/dashboard/logs', function() use ($db) {
    if (class_exists('Ginto\\Controllers\\AdminController')) {
        try {
            $ctrl = new \Ginto\Controllers\AdminController($db);
            if (method_exists($ctrl, 'dashboardLogs')) return $ctrl->dashboardLogs();
        } catch (\Throwable $_) {}
    }
    // fallback to admin logs if controller not available
    header('Location: /admin/logs'); exit;
});

req($router, '/dashboard/logs/{id}', function($id = null) use ($db) {
    if (class_exists('Ginto\\Controllers\\AdminController')) {
        try {
            $ctrl = new \Ginto\Controllers\AdminController($db);
            if (method_exists($ctrl, 'dashboardLog')) return $ctrl->dashboardLog($id);
        } catch (\Throwable $_) {}
    }
    header('Location: /admin/logs' . ($id ? '/' . urlencode($id) : '')); exit;
});

// User profile
req($router, '/user/profile/{ident}', function($ident) use ($db) {
    $userId = null;
    if (ctype_digit($ident)) {
        $userId = intval($ident);
    } else {
        try {
            $uid = $db->get('users', 'id', ['public_id' => $ident]);
            if ($uid) $userId = intval($uid);
            else {
                $uid2 = $db->get('users', 'id', ['username' => $ident]);
                if ($uid2) $userId = intval($uid2);
            }
        } catch (\Throwable $_) {
        }
    }

    if (!$userId) {
        http_response_code(404);
        echo '<h1>User not found</h1>';
        exit;
    }

    try {
        $userModel = new \Ginto\Models\User();
        $user = $userModel->find($userId);
        if ($user) {
            \Ginto\Core\View::view('user/profile', ['user' => $user]);
            exit;
        }
    } catch (\Throwable $e) {
        http_response_code(500);
        echo 'Error rendering profile: ' . htmlspecialchars($e->getMessage());
        exit;
    }
});

// User commissions page
req($router, '/user/commissions', function() use ($db) {
    if (empty($_SESSION['user_id'])) {
        header('Location: /login');
        exit;
    }

    try {
        $ctrl = new \Ginto\Controllers\CommissionsController();
        return $ctrl->index();
    } catch (\Throwable $e) {
        $viewPath = ROOT_PATH . '/src/Views/user/commissions.php';
        if (file_exists($viewPath)) {
            include $viewPath;
            exit;
        }
        http_response_code(500);
        echo 'Commissions page not available: ' . $e->getMessage();
        exit;
    }
});

// Compact-only user network view
req($router, '/user/network-tree/compact-view', function() use ($db) {
    if (empty($_SESSION['user_id'])) {
        try {
            $userId = $db->get('users', 'id', ['username' => 'oliverbob']);
            if ($userId) {
                $_SESSION['user_id'] = (int)$userId;
            }
        } catch (\Throwable $_) {
        }
    }
    $viewPath = ROOT_PATH . '/src/Views/user/network-tree/compact-view.php';
    if (file_exists($viewPath)) { include $viewPath; exit; }
    $fallback = ROOT_PATH . '/src/Views/users/network-tree/compact-view.php';
    if (file_exists($fallback)) { include $fallback; exit; }
    http_response_code(500);
    echo "Compact view not found. Expected: $viewPath (or fallback: $fallback)";
});

// Webhook
req($router, '/webhook', function() use ($db) {
    try {
        if (class_exists('\App\Controllers\WebhookController')) {
            try {
                $ctrl = new \App\Controllers\WebhookController($db);
                return $ctrl->webhook();
            } catch (\Throwable $e) {
                error_log('WebhookController init failed: ' . $e->getMessage());
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    http_response_code(500);
                    echo json_encode(['error' => 'Webhook controller not configured']);
                    exit;
                }
            }
        }
        $viewPath = ROOT_PATH . '/src/Views/webhook/webhook.php';
        if (file_exists($viewPath)) { include $viewPath; exit; }
        http_response_code(500); echo 'Webhook handler not available'; exit;
    } catch (\Throwable $e) {
        http_response_code(500); error_log('Webhook route error: ' . $e->getMessage()); echo 'Webhook route error'; exit;
    }
});

req($router, '/webhook/status', function() use ($db) {
    try {
        if (class_exists('\App\Controllers\WebhookController')) {
            try {
                $ctrl = new \App\Controllers\WebhookController($db);
                return $ctrl->saiCodeCheck();
            } catch (\Throwable $e) {
                error_log('WebhookController init failed (status): ' . $e->getMessage());
            }
        }
        $viewPath = ROOT_PATH . '/src/Views/webhook/webhook.php';
        if (file_exists($viewPath)) { include $viewPath; exit; }
        http_response_code(500); echo 'Webhook status page not available'; exit;
    } catch (\Throwable $e) { error_log('Webhook status route error: ' . $e->getMessage()); http_response_code(500); echo 'Webhook status route error'; exit; }
});

// API: Subscription Activation (called after PayPal approval)
req($router, '/api/subscription/activate', function() use ($db) {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $subscriptionId = $input['subscription_id'] ?? null;
    $planName = $input['plan'] ?? null;
    $userId = $input['user_id'] ?? ($_SESSION['user_id'] ?? null);
    
    if (!$subscriptionId || !$planName || !$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields', 'received' => $input]);
        exit;
    }
    
    try {
        // Get plan details
        $plan = $db->get('subscription_plans', '*', ['name' => $planName, 'is_active' => 1]);
        if (!$plan) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid plan']);
            exit;
        }
        
        // Check if subscription already exists
        $existing = $db->get('user_subscriptions', 'id', ['paypal_subscription_id' => $subscriptionId]);
        if ($existing) {
            echo json_encode(['success' => true, 'message' => 'Subscription already activated']);
            exit;
        }
        
        // Cancel any existing active subscriptions for this user
        $db->update('user_subscriptions', [
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s')
        ], [
            'user_id' => $userId,
            'status' => 'active'
        ]);
        
        // Create new subscription
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 month'));
        
        $db->insert('user_subscriptions', [
            'user_id' => $userId,
            'plan_id' => $plan['id'],
            'status' => 'active',
            'started_at' => $now,
            'expires_at' => $expiresAt,
            'payment_method' => 'paypal',
            'paypal_subscription_id' => $subscriptionId,
            'paypal_plan_id' => $input['paypal_plan_id'] ?? null,
            'amount_paid' => $plan['price_monthly'],
            'currency' => $plan['price_currency'] ?? 'PHP',
            'auto_renew' => 1,
            'created_at' => $now,
            'updated_at' => $now
        ]);
        
        $newSubId = $db->id();
        
        // Log the payment
        $transactionId = TransactionHelper::generateTransactionId($db);
        $auditData = TransactionHelper::captureAuditData();
        $db->insert('subscription_payments', array_merge([
            'user_id' => $userId,
            'subscription_id' => $newSubId,
            'plan_id' => $plan['id'],
            'amount' => $plan['price_monthly'],
            'currency' => $plan['price_currency'] ?? 'PHP',
            'payment_method' => 'paypal',
            'payment_reference' => $subscriptionId,
            'status' => 'completed',
            'paid_at' => $now,
            'notes' => 'PayPal subscription activated',
            'created_at' => $now,
            'transaction_id' => $transactionId
        ], $auditData));
        
        // Update user's plan in users table if applicable
        $db->update('users', [
            'subscription_plan' => $planName
        ], ['id' => $userId]);
        
        error_log("PayPal subscription activated: user=$userId, plan=$planName, subscription=$subscriptionId");
        
        echo json_encode([
            'success' => true,
            'subscription_id' => $newSubId,
            'plan' => $planName,
            'expires_at' => $expiresAt
        ]);
        
    } catch (\Throwable $e) {
        error_log('Subscription activation error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to activate subscription', 'details' => $e->getMessage()]);
    }
    exit;
});

// Circle view
req($router, '/user/network-tree/circle-view', function() use ($db) {
    if (empty($_SESSION['user_id'])) {
        try {
            $userId = $db->get('users', 'id', ['username' => 'oliverbob']);
            if ($userId) { $_SESSION['user_id'] = (int)$userId; }
        } catch (\Throwable $_) { }
    }
    $viewPath = ROOT_PATH . '/src/Views/user/network-tree/circle-view.php';
    if (file_exists($viewPath)) { include $viewPath; exit; }
    $fallback = ROOT_PATH . '/src/Views/user/network-tree/compact-view.php';
    if (file_exists($fallback)) { include $fallback; exit; }
});

// API: network tree data
req($router, '/api/user/network-tree', function() use ($db) {
    if (empty($_SESSION['user_id'])) { http_response_code(403); echo json_encode(['error' => 'Not authenticated']); exit; }
    $userId = $_SESSION['user_id'];
    $depth = isset($_GET['depth']) ? intval($_GET['depth']) : 3;
    $depth = max(1, min(9, $depth));
    $userModel = new \Ginto\Models\User();
    $tree = $userModel->getNetworkTree($userId, $depth);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $tree]);
});

// API: network search
req($router, '/api/user/network-search', function() use ($db) {
    $controller = new \Ginto\Controllers\ApiController($db);
    $controller->searchUsers();
});

// Restore admin routes (fallback)
req($router, '/admin', function() use ($db) {
    if (class_exists('Ginto\\Controllers\\AdminController')) {
        try {
            $ctrl = new \Ginto\Controllers\AdminController($db);
            if (method_exists($ctrl, 'dashboard')) { return $ctrl->dashboard(); }
        } catch (\Throwable $e) { }
    }
    $view = ROOT_PATH . '/src/Views/admin/dashboard.php';
    if (file_exists($view)) { include $view; exit; }
    header('Location: /');
    exit;
});

req($router, '/admin/network-tree', function() use ($db) {
    if (class_exists('Ginto\\Controllers\\AdminController')) {
        try {
            $ctrl = new \Ginto\Controllers\AdminController($db);
            if (method_exists($ctrl, 'networkTree')) { return $ctrl->networkTree(); }
        } catch (\Throwable $e) { }
    }
    $viewPath = ROOT_PATH . '/src/Views/admin/network-tree.php';
    if (file_exists($viewPath)) { include $viewPath; exit; }
    try { \Ginto\Core\View::view('admin/network-tree', ['title' => 'Admin Network Tree']); exit; } catch (\Throwable $_) { http_response_code(404); echo 'Admin network tree not found'; exit; }
});

req($router, '/admin/network-tree/data', function() use ($db) {
    header('Content-Type: application/json');
    $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
    $depth = isset($_GET['depth']) ? intval($_GET['depth']) : 3;
    $depth = max(1, min(9, $depth));
    if (!$userId) { echo json_encode(['success' => false, 'message' => 'No user_id provided']); exit; }
    try {
        $userModel = new \Ginto\Models\User();
        if (method_exists($userModel, 'getNetworkTree')) {
            $tree = $userModel->getNetworkTree($userId, $depth);
            echo json_encode(['success' => true, 'data' => $tree]); exit;
        }
        $tree = $userModel->find($userId);
        if (!$tree) { echo json_encode(['success' => false, 'message' => 'User not found']); exit; }
        $tree['children'] = $userModel->getDirectReferrals($userId);
        echo json_encode(['success' => true, 'data' => $tree]); exit;
    } catch (\Throwable $e) { http_response_code(500); echo json_encode(['success' => false, 'message' => $e->getMessage()]); exit; }
});

req($router, '/api/user/profile', function() use ($db) {
    $controller = new \Ginto\Controllers\ApiController($db);
    $controller->userProfile();
});

req($router, '/test-phone', function() use ($db) {
    $users = $db->select('users', ['id', 'username', 'phone', 'country']);
    echo '<pre>' . print_r($users, true) . '</pre>';
});

req($router, '/api/user-id', function() use ($db) {
    header('Content-Type: application/json');
    $username = $_GET['username'] ?? '';
    if (!$username) { echo json_encode(['error' => 'No username provided']); exit; }
    $userId = $db->get('users', 'id', ['username' => $username]);
    if ($userId) { echo json_encode(['id' => $userId]); } else { echo json_encode(['id' => null, 'error' => 'User not found']); }
    exit;
});

// 404 handler
req($router, '/404', function() {
    http_response_code(404);
    echo "<h1>404 Not Found</h1><p>The requested page could not be found.</p>";
});

// API direct downlines
req($router, '/api/user/direct-downlines', function() use ($db) {
    if (empty($_SESSION['user_id'])) { http_response_code(403); echo json_encode(['error' => 'Not authenticated']); exit; }
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (int)$_SESSION['user_id'];
    $maxLevel = isset($_GET['max_level']) ? (int)$_GET['max_level'] : 3;
    $maxLevel = max(1, min(9, $maxLevel));
    if ($userId <= 0) { http_response_code(400); echo json_encode(['error' => 'Invalid user ID']); exit; }
    try {
        $userModel = new \Ginto\Models\User();
        $tree = $userModel->getNetworkTree($userId, $maxLevel);
        $downlines = $tree['children'] ?? [];
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'downlines' => $downlines]);
        exit;
    } catch (\Throwable $e) { http_response_code(500); echo json_encode(['success' => false, 'message' => $e->getMessage()]); exit; }
});

req($router, '/api/user/commissions', function() use ($db) {
    if (!empty($_GET['user']) && empty($_GET['user_id'])) {
        $username = trim($_GET['user']);
        $uid = $db->get('users', 'id', ['username' => $username]);
        if ($uid) $_GET['user_id'] = (int)$uid;
    }
    if (!empty($_GET['userId']) && empty($_GET['user_id'])) { $_GET['user_id'] = intval($_GET['userId']); }
    $ctrl = new \Ginto\Controllers\CommissionsController();
    $ctrl->apiIndex();
});

req($router, '/network/earnings', function() use ($db) {
    if (!empty($_GET['user']) && empty($_GET['user_id'])) { $username = trim($_GET['user']); $uid = $db->get('users', 'id', ['username' => $username]); if ($uid) $_GET['user_id'] = (int)$uid; }
    if (!empty($_GET['userId']) && empty($_GET['user_id'])) { $_GET['user_id'] = intval($_GET['userId']); }
    header('Content-Type: application/json');
    $ctrl = new \Ginto\Controllers\CommissionsController();
    $ctrl->apiIndex();
});

req($router, '/api/data', function() use ($db) {
    header('Content-Type: application/json');
    $controller = new \Ginto\Controllers\DataController();
    $controller->index();
});

req($router, '/api/mall/products', function() {
    header('Content-Type: application/json');
    $products = [];
    try {
        if (class_exists('Ginto\\Core\\Database') && \Ginto\Core\Database::isInstalled()) {
            require_once ROOT_PATH . '/src/Models/Product.php';
            $pm = new \Ginto\Models\Product();
            $opts = [];
            if (isset($_GET['category'])) $opts['category'] = $_GET['category'];
            if (isset($_GET['search'])) $opts['search'] = $_GET['search'];
            if (isset($_GET['sort'])) $opts['sort'] = $_GET['sort'];
            if (isset($_GET['limit'])) $opts['limit'] = (int)$_GET['limit'];
            if (isset($_GET['offset'])) $opts['offset'] = (int)$_GET['offset'];
            $products = $pm->list($opts);
        } else {
            $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(ROOT_PATH) . '/storage';
            $storeFile = $storagePath . '/mall_products.json';
            if (file_exists($storeFile)) { $json = @file_get_contents($storeFile); $products = json_decode($json, true) ?: []; }
        }
        require_once ROOT_PATH . '/src/Helpers/CurrencyHelper.php';
        $chClass = '\\Ginto\\Helpers\\CurrencyHelper';
        $detectedCurrency = $chClass::detectCurrency();
        foreach ($products as &$p) {
            $currency = $p['price_currency'] ?? $p['currency'] ?? $detectedCurrency;
            $p['price_currency'] = $currency;
            $p['formatted_price'] = $chClass::formatAmount($p['price_amount'] ?? ($p['price'] ?? 0), $currency);
        }
        unset($p);
    } catch (\Throwable $e) { http_response_code(500); echo json_encode(['success' => false, 'message' => $e->getMessage()]); exit; }
    echo json_encode(['success' => true, 'products' => $products]);
    exit;
});

req($router, '/marketplace', function() {
    $controller = new \Ginto\Controllers\MallController();
    $controller->marketplace();
});

req($router, '/mall/upload', function() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Content-Type: application/json'); echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit; }
    header('Content-Type: application/json');
    if (empty($_SESSION['user_id'])) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Authentication required']); exit; }
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!function_exists('validateCsrfToken') || !validateCsrfToken($token)) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']); exit; }
    $title = trim($_POST['title'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $category = trim($_POST['category'] ?? 'user');
    $desc = trim($_POST['description'] ?? '');
    if ($title === '' || $price <= 0) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Title and valid price are required']); exit; }
    $uploadPath = ROOT_PATH . '/public/assets/uploads';
    if (!is_dir($uploadPath)) { @mkdir($uploadPath, 0755, true); }
    $imageUrl = '';
    if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $ext = strtolower($ext);
        $allowed = ['jpg','jpeg','png','gif','webp','svg'];
        if (!in_array($ext, $allowed)) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Unsupported image type']); exit; }
        $filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest = $uploadPath . '/' . $filename;
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $dest)) { http_response_code(500); echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']); exit; }
        $imageUrl = '/assets/uploads/' . $filename;
    }
    try { require_once ROOT_PATH . '/src/Helpers/CurrencyHelper.php'; $ch = '\\Ginto\\Helpers\\CurrencyHelper'; $currency = $ch::detectCurrency(); } catch (\Throwable $e) { $currency = getenv('APP_DEFAULT_CURRENCY') ?: 'USD'; }
    $product = [
        'id' => intval(time()),
        'title' => htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'price' => round($price, 2),
        'currency' => $currency,
        'cat' => $category,
        'rating' => 0,
        'img' => $imageUrl ?: '/assets/images/placeholder_ceramic.svg',
        'badge' => '',
        'desc' => htmlspecialchars($desc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'owner_id' => (int)$_SESSION['user_id']
    ];
    $saved = null;
    try {
        if (class_exists('Ginto\\Models\\Product')) {
            require_once ROOT_PATH . '/src/Models/Product.php';
            $prodModel = new \Ginto\Models\Product();
            $dbProduct = $prodModel->create([
                'owner_id' => $product['owner_id'],
                'title' => $product['title'],
                'description' => $product['desc'],
                'price' => $product['price'],
                'currency' => $product['currency'],
                'category' => $product['cat'],
                'image_path' => $product['img'],
                'badge' => $product['badge'],
                'rating' => $product['rating'] ?? 0,
                'status' => 'published'
            ]);
            if ($dbProduct) { $saved = $dbProduct; }
        }
    } catch (\Throwable $e) { $saved = null; }
    if (!$saved) {
        $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(ROOT_PATH) . '/storage';
        $storeFile = $storagePath . '/mall_products.json';
        $existing = [];
        if (file_exists($storeFile)) { $json = @file_get_contents($storeFile); $existing = json_decode($json, true) ?: []; }
        $existing[] = $product;
        if (false === @file_put_contents($storeFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) { http_response_code(500); echo json_encode(['success' => false, 'message' => 'Failed to save product']); exit; }
        echo json_encode(['success' => true, 'product' => $product]); exit;
    }
    echo json_encode(['success' => true, 'product' => $saved]); exit;
});
