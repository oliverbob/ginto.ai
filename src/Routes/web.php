<?php
// src/Routes/web.php
// Centralized route definitions for Ginto CMS

// Cloud subdomain proxy - catch requests to *.ginto.ai cloud subdomains
$router->req('/cloud-proxy', 'CloudProxyController@proxy');
// Cloud subdomain route check - returns target IP for Caddy
$router->req('/api/cloud/route', 'CloudProxyController@getRoute');

// Unified tunnel relay endpoint (pinned to vision.ginto.ai)
$router->req('/tunnel', 'TunnelController@relayVision');
$router->req('/tunnel/{path:.*}', 'TunnelController@relayVisionPath');

$router->req('/test', 'TestController@test');
$router->req('/chat/prompts/', 'PromptsController@getPrompts');
$router->req('/chat/disabled-tools', 'ChatController@disabledTools', ['POST']);

use Core\Router;
use Ginto\Helpers\TransactionHelper;

// Ensure $db exists for route closures (some contexts may not have it defined)
if (!isset($db)) {
    try {
        if (class_exists('\Ginto\\Core\\Database')) {
            $db = \Ginto\Core\Database::getInstance();
        } else {
            $db = null;
        }
    } catch (\Throwable $_) {
        $db = null;
    }
}

$router->req('/api/debug/ip-headers', 'DebugController@ipHeaders');
// Main API endpoints used by the code editor and other clients
// Simple PHP-based API endpoints (replacement for saichat/nodejs API)
$router->get('/api', 'ApiController@index');
$router->post('/api', 'ApiController@post');
$router->get('/api/messages', 'ApiController@getMessages');

$router->req('/login', 'AuthController@login');
$router->req('/transcribe', 'AudioController@transcribe');

// Home page serves chat directly (messenger works on both / and /chat)
$router->get('/', 'ChatController@index');
$router->post('/', 'ChatController@stream');

// Bible routes
$router->get('/bible', 'BibleController@index');
$router->get('/bible/search', 'BibleController@search');
$router->get('/bible/verses', 'BibleController@verses');
// Grouped legacy bible endpoints under /bible/* for organization
$router->req('/bible/book', function() use ($db) {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $bookFile = defined('ROOT_PATH') ? ROOT_PATH . '/src/Views/bible/book.php' : __DIR__ . '/../Views/bible/book.php';
    if (file_exists($bookFile)) {
        try {
            if (class_exists('\Ginto\\Core\\Database')) {
                $db = \Ginto\Core\Database::getInstance();
            }
        } catch (\Throwable $_) {
            // ignore
        }
        $GLOBALS['db'] = $db ?? null;
        if (!defined('PATHSPAGE')) define('PATHSPAGE', true);
        require $bookFile;
        return;
    }
    http_response_code(404);
    echo "Page Not Found: /bible/book not available.";
});

// Ensure GET requests also match the legacy book endpoint
$router->get('/bible/book', function() use ($db) {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $bookFile = defined('ROOT_PATH') ? ROOT_PATH . '/src/Views/bible/book.php' : __DIR__ . '/../Views/bible/book.php';
    if (file_exists($bookFile)) {
        try {
            if (class_exists('\Ginto\\Core\\Database')) {
                $db = \Ginto\Core\Database::getInstance();
            }
        } catch (\Throwable $_) {
            // ignore
        }
        $GLOBALS['db'] = $db ?? null;
        if (!defined('PATHSPAGE')) define('PATHSPAGE', true);
        require $bookFile;
        return;
    }
    http_response_code(404);
    echo "Page Not Found: /bible/book not available.";
});
// Legacy bible endpoints (support old flat PHP URLs)
$router->req('/book.php', function() use ($db) {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $bookFile = defined('ROOT_PATH') ? ROOT_PATH . '/src/Views/bible/book.php' : __DIR__ . '/../Views/bible/book.php';
    if (file_exists($bookFile)) {
        // make app DB available to legacy view
        try {
            if (class_exists('\Ginto\\Core\\Database')) {
                $db = \Ginto\Core\Database::getInstance();
            }
        } catch (\Throwable $_) {
            // ignore
        }
        $GLOBALS['db'] = $db ?? null;
        if (!defined('PATHSPAGE')) define('PATHSPAGE', true);
        require $bookFile;
        return;
    }
    http_response_code(404);
    echo "Page Not Found: book.php not available.";
});

$router->req('/index_en.php', function() use ($db) {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $file = defined('ROOT_PATH') ? ROOT_PATH . '/src/Views/bible/index_en.php' : __DIR__ . '/../Views/bible/index_en.php';
    if (file_exists($file)) {
        try {
            if (class_exists('\Ginto\\Core\\Database')) {
                $db = \Ginto\Core\Database::getInstance();
            }
        } catch (\Throwable $_) {}
        $GLOBALS['db'] = $db ?? null;
        if (!defined('PATHSPAGE')) define('PATHSPAGE', true);
        require $file;
        return;
    }
    http_response_code(404);
    echo "Page Not Found: index_en.php not available.";
});
$router->req('/user/network-tree', 'UserController@networkTree');

// Social page (renders the feed UI)
$router->get('/social', 'SocialController@index');

// Social feature routes copied from saicms and exposed under /social to avoid root conflicts
$router->post('/post', 'SocialController@post');
$router->post('/social/post', 'SocialController@post');
$router->req('/social/feed', 'SocialController@feed');
$router->req('/social/post/{id}', 'SocialController@getPostById');
// Backwards-compatible single-post endpoints: support both singular and plural variants used by clients
$router->req('/post/{id:\\d+}', 'SocialController@getPostById');
$router->req('/posts/{id:\\d+}', 'SocialController@getPostById');
$router->req('/social/ads/featured', 'SocialController@showAdsEndpoint');
// Backwards-compatible alias: some clients request /ads/featured (kept from saicms)
$router->req('/ads/featured', 'SocialController@showAdsEndpoint');
// Comment and activity endpoints (ported from saicms ActivitiesController)
// Use explicit HTTP methods to match controller expectations and client usage
$router->post('/post/comment', 'ActivitiesController@addComment');
$router->get('/post/{id:\d+}/comments', 'ActivitiesController@getComments');
$router->post('/post/comments/{id:\d+}/delete', 'ActivitiesController@deleteCommentById');
$router->post('/post/comments/{id:\d+}/edit', 'ActivitiesController@editCommentById');
// Like toggle endpoint (add both POST and GET for backwards compatibility with older clients)
$router->post('/post/like', 'ActivitiesController@toggleLike');
$router->get('/post/like', 'ActivitiesController@toggleLike');
// Delete a post (owner only) - used by feed manager when a user deletes their own post
$router->post('/post/{id:\d+}/delete', 'ActivitiesController@deletePost');
// Edit/update a post (owner only) — used by feed manager when saving edits
$router->post('/post/{id:\d+}/update', 'ActivitiesController@editPostById');
// Create post with media ( Backwards-compatible endpoint used by mediamanager )
$router->post('/post/create_with_media', 'UploadController@createPostWithMedia');
// Expose media creation and streaming endpoints under /social for the social feed UI
$router->post('/social/post/create_with_media', 'UploadController@createPostWithMedia');
$router->post('/post/create_with_stream', 'StreamController@createPostWithStream');
$router->post('/social/post/create_with_stream', 'StreamController@createPostWithStream');
$router->get('/post/stream', 'StreamController@stream');
$router->get('/social/post/stream', 'StreamController@stream');
// Legacy stories create endpoint (clients expect this path)
$router->post('/post/stories/create', 'StoriesController@postStoriesCreate');

// Save site files from in-browser editor to the user's sandbox Websites/sai-code directory
$router->post('/site-save', function() use ($db) {
    header('Content-Type: application/json');
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

    // Require logged-in user or public session id
    if (empty($_SESSION['user_id']) && empty($_SESSION['public_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
        return;
    }

    $content = isset($data['content']) ? (string)$data['content'] : null;
    $filename = isset($data['filename']) ? basename((string)$data['filename']) : 'index.html';
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

    if ($content === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing content']);
        return;
    }

    // Resolve sandbox id from DB (client_sandboxes table)
    $sandboxId = null;
    try {
        if (!empty($_SESSION['user_id'])) {
            $sid = $db->get('client_sandboxes', 'sandbox_id', ['user_id' => (int)$_SESSION['user_id']]);
            if ($sid) $sandboxId = $sid;
        }
        if (!$sandboxId && !empty($_SESSION['public_id'])) {
            $sid = $db->get('client_sandboxes', 'sandbox_id', ['public_id' => $_SESSION['public_id']]);
            if ($sid) $sandboxId = $sid;
        }
    } catch (\Throwable $e) {
        error_log('site-save: sandbox lookup failed: ' . $e->getMessage());
    }

    if (!$sandboxId) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Sandbox not found']);
        return;
    }

    // Compute paths
    $clientsRoot = defined('ROOT_PATH') ? rtrim(ROOT_PATH, '/') . '/clients' : __DIR__ . '/../../clients';
    $sandboxRoot = $clientsRoot . '/' . $sandboxId;
    $targetDir = $sandboxRoot . '/Websites/sai-code';

    // Ensure clients root exists (try to create if missing)
    if (!is_dir($clientsRoot)) {
        if (!@mkdir($clientsRoot, 0755, true)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Clients root missing and could not be created', 'clientsRoot' => $clientsRoot]);
            return;
        }
    }

    if (!is_dir($sandboxRoot)) {
        // If the sandbox directory is not present on the host filesystem,
        // attempt to write into the sandbox using the UnifiedSandbox helper
        // which supports Docker/LXD backends.
        try {
            if (class_exists('Ginto\\Helpers\\UnifiedSandbox')) {
                $detected = null;
                try {
                    $detected = \Ginto\Helpers\UnifiedSandbox::detectBackendForSandbox($sandboxId);
                } catch (\Throwable $_) {
                    $detected = null;
                }

                // If detection succeeded or the sandbox exists according to the helper,
                // attempt to write the file inside the sandbox.
                $existsInUnified = false;
                try {
                    $existsInUnified = \Ginto\Helpers\UnifiedSandbox::exists($sandboxId);
                } catch (\Throwable $_) {
                    $existsInUnified = false;
                }

                if ($detected !== null || $existsInUnified) {
                    $home = \Ginto\Helpers\UnifiedSandbox::getHomePathForSandbox($sandboxId);
                    $remoteDir = rtrim($home, '/') . '/Websites/sai-code';
                    $remotePath = $remoteDir . '/' . $filename;

                    $writeResult = \Ginto\Helpers\UnifiedSandbox::writeFile($sandboxId, $remotePath, $content);
                    if (!empty($writeResult) && !empty($writeResult['success'])) {
                        echo json_encode(['success' => true, 'file' => 'sandbox://' . $sandboxId . $remotePath, 'bytes' => $writeResult['bytes'] ?? null, 'backend' => $detected]);
                        return;
                    }

                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'UnifiedSandbox write failed', 'detail' => $writeResult, 'sandboxRoot' => $sandboxRoot, 'clientsRoot' => $clientsRoot]);
                    return;
                }
            }
        } catch (\Throwable $e) {
            error_log('site-save: UnifiedSandbox check/write failed: ' . $e->getMessage());
        }

        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Sandbox directory not present on disk', 'sandboxRoot' => $sandboxRoot, 'clientsRoot' => $clientsRoot]);
        return;
    }

    if (!is_dir($targetDir)) {
        if (!@mkdir($targetDir, 0755, true)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Could not create target directory']);
            return;
        }
    }

    $filePath = $targetDir . '/' . $filename;
    $written = @file_put_contents($filePath, $content);
    if ($written === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to write file']);
        return;
    }

    echo json_encode(['success' => true, 'file' => $filePath]);
    return;
});
// Public search endpoints used by the header typeahead and participant composer
$router->req('/search', function() use ($db) {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    if ($q === '') { echo json_encode(['success' => true, 'users' => []]); return; }
    $clean = preg_replace('/[^a-zA-Z0-9 ]/', '', $q);
    $like = '%' . $clean . '%';
    try {
        $params = [':t' => $like];
        $excludeClause = '';
        if (!empty($_SESSION['user_id'])) {
            $excludeClause = ' AND id != :exclude_id';
            $params[':exclude_id'] = (int)$_SESSION['user_id'];
        }
        $sql = "SELECT id, username, COALESCE(NULLIF(fullname,''), username) AS name, profile_picture FROM users WHERE (fullname LIKE :t OR username LIKE :t OR firstname LIKE :t OR lastname LIKE :t) AND (status = 'active' OR status IS NULL)" . $excludeClause . " LIMIT 15";
        $rows = $db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
        $users = [];
        foreach ($rows as $r) {
            $users[] = [
                'id' => (int)$r['id'],
                'name' => $r['name'] ?? $r['username'],
                'avatar' => $r['profile_picture'] ?: '/assets/favicon.ico'
            ];
        }
        echo json_encode(['success' => true, 'users' => $users]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Search failed']);
    }
});

$router->req('/search/chat-participants', function() use ($db) {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    if ($q === '') { echo json_encode(['success' => true, 'users' => []]); return; }
    $clean = preg_replace('/[^a-zA-Z0-9 ]/', '', $q);
    $like = '%' . $clean . '%';
    try {
        $params = [':t' => $like];
        $excludeClause = '';
        if (!empty($_SESSION['user_id'])) {
            $excludeClause = ' AND id != :exclude_id';
            $params[':exclude_id'] = (int)$_SESSION['user_id'];
        }
        $sql = "SELECT id, username, COALESCE(NULLIF(fullname,''), username) AS name, profile_picture FROM users WHERE (fullname LIKE :t OR username LIKE :t OR firstname LIKE :t OR lastname LIKE :t) AND (status = 'active' OR status IS NULL)" . $excludeClause . " LIMIT 15";
        $rows = $db->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
        $users = [];
        foreach ($rows as $r) {
            $users[] = [
                'id' => (int)$r['id'],
                'name' => $r['name'] ?? $r['username'],
                'avatar' => $r['profile_picture'] ?: '/assets/favicon.ico'
            ];
        }
        echo json_encode(['success' => true, 'users' => $users]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Search failed']);
    }
});
// root social path: serve feed on GET and accept post on POST
$router->post('/social', 'SocialController@post');
$router->req('/downline', 'AuthController@downline');
$router->req('/logout', 'AuthController@logout');
$router->req('/register', 'AuthController@register');

// Heartbeat endpoint used by client to report activity (contacts.js)
$router->post('/user/activity', function() use ($db) {
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); echo json_encode(['success' => false, 'error' => 'Method Not Allowed']); return;
    }
    if (empty($_SESSION['user_id'])) {
        http_response_code(401); echo json_encode(['success' => false, 'error' => 'Unauthorized']); return;
    }
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRF'] ?? null;
    $sessionToken = $_SESSION['csrf_token'] ?? null;
    if (empty($csrfHeader) || empty($sessionToken) || !hash_equals($sessionToken, $csrfHeader)) {
        http_response_code(403); echo json_encode(['success' => false, 'error' => 'CSRF token mismatch']); return;
    }
    try {
        if ($db) {
            // Ensure the users.last_activity column exists before updating to avoid SQL errors
            $colExists = false;
            try {
                $stmt = $db->query("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'last_activity'");
                if ($stmt) {
                    $row = $stmt->fetch();
                    if (!empty($row) && isset($row['c']) && (int)$row['c'] > 0) {
                        $colExists = true;
                    }
                }
            } catch (\Throwable $_) {
                // If information_schema query fails, fall back to attempting update (wrapped below)
                $colExists = false;
            }

            if (!$colExists) {
                // Best-effort: try to add the column if the DB user has privileges.
                try {
                    $db->query("ALTER TABLE users ADD COLUMN last_activity DATETIME NULL DEFAULT NULL AFTER updated_at");
                    $colExists = true;
                    error_log('user/activity: created missing users.last_activity column');
                } catch (\Throwable $ex) {
                    error_log('user/activity: last_activity column missing and could not be created: ' . $ex->getMessage());
                    // Do not treat this as fatal; skip updating to avoid repeated errors
                    $colExists = false;
                }
            }

            if ($colExists) {
                $db->update('users', ['last_activity' => date('Y-m-d H:i:s')], ['id' => (int)$_SESSION['user_id']]);
            }
        }
    } catch (\Throwable $e) {
        error_log('user/activity heartbeat failed: ' . $e->getMessage());
        http_response_code(500); echo json_encode(['success' => false, 'error' => 'Server error']); return;
    }
    echo json_encode(['success' => true]);
});

$router->req('/register/promo-code', 'PaymentController@validatePromoCode', ['POST']);
$router->req('/register/capture-payment', 'UserController@capturePaymentAction', ['POST']);

// Pre-validate username/email before PayPal creates subscription
// CRITICAL: Prevents charging users for subscriptions when registration will fail
$router->req('/api/validate-registration', function() use ($db) {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['available' => false, 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    
    if (empty($username) || empty($email)) {
        echo json_encode(['available' => false, 'message' => 'Username and email are required']);
        return;
    }
    
    // Check username
    $existingUsername = $db->get('users', 'id', ['username' => $username]);
    if ($existingUsername) {
        echo json_encode(['available' => false, 'message' => 'Username already taken']);
        return;
    }
    
    // Check email
    $existingEmail = $db->get('users', 'id', ['email' => $email]);
    if ($existingEmail) {
        echo json_encode(['available' => false, 'message' => 'Email already registered']);
        return;
    }
    
    echo json_encode(['available' => true, 'message' => 'OK']);
});

// Addon subscription API routes
// Get addon info (features, pricing, PayPal plan ID)
$router->req('/api/addon/info/{addonType}', function($addonType) use ($db) {
    header('Content-Type: application/json');
    
    $environment = getenv('PAYPAL_ENVIRONMENT') ?: $_ENV['PAYPAL_ENVIRONMENT'] ?? 'sandbox';
    $planColumn = ($environment === 'sandbox') ? 'paypal_plan_id_sandbox' : 'paypal_plan_id';
    
    $addon = $db->get('addon_plans', [
        'addon_type', 'name', 'description', 'amount_usd', 'features', $planColumn
    ], ['addon_type' => $addonType, 'is_active' => 1]);
    
    if (!$addon) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Addon not found']);
        return;
    }
    
    $features = [];
    if (!empty($addon['features'])) {
        $features = is_string($addon['features']) ? json_decode($addon['features'], true) : $addon['features'];
    }

    // If user is logged in, include their addon status so the UI can avoid showing upgrade prompts when already active
    $userHasAddon = false;
    $userAddon = null;
    if (!empty($_SESSION['user_id']) && $db) {
        try {
            $userAddon = $db->get('user_addons', ['status', 'subscription_start_date', 'subscription_next_billing', 'paypal_subscription_id', 'subscription_start_date', 'subscription_next_billing'], [
                'user_id' => (int)$_SESSION['user_id'],
                'addon_type' => $addonType
            ]);
            if (!empty($userAddon)) {
                $userHasAddon = true;
            }
        } catch (\Throwable $_) {
            // ignore DB errors here - we don't want to break addon info API
            $userHasAddon = false;
            $userAddon = null;
        }
    }
    
    echo json_encode([
        'success' => true,
        'addon_type' => $addon['addon_type'],
        'name' => $addon['name'],
        'description' => $addon['description'],
        'amount_usd' => floatval($addon['amount_usd']),
        'features' => $features ?: [],
        'paypal_plan_id' => $addon[$planColumn] ?? null,
        'user_has_addon' => $userHasAddon,
        'user_addon_status' => $userAddon['status'] ?? null,
        'user_addon_subscription_start' => $userAddon['subscription_start_date'] ?? null,
        'user_addon_next_billing' => $userAddon['subscription_next_billing'] ?? null
    ]);
});

// Activate addon subscription after PayPal approval
$router->req('/api/addon/activate', function() use ($db) {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        return;
    }
    
    // Require login
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Login required']);
        return;
    }
    
    $userId = (int)$_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    $addonType = trim($input['addon_type'] ?? '');
    $subscriptionId = trim($input['subscription_id'] ?? '');
    
    if (empty($addonType) || empty($subscriptionId)) {
        echo json_encode(['success' => false, 'error' => 'Missing addon_type or subscription_id']);
        return;
    }
    
    // Verify addon exists
    $addon = $db->get('addon_plans', ['id', 'name', 'amount_usd', 'paypal_plan_id', 'paypal_plan_id_sandbox'], ['addon_type' => $addonType]);
    if (!$addon) {
        echo json_encode(['success' => false, 'error' => 'Invalid addon type']);
        return;
    }

    // --- Server-side PayPal verification ---
    $environment = getenv('PAYPAL_ENVIRONMENT') ?: $_ENV['PAYPAL_ENVIRONMENT'] ?? 'sandbox';
    $planColumn = ($environment === 'sandbox') ? 'paypal_plan_id_sandbox' : 'paypal_plan_id';
    $expectedPlanId = $addon[$planColumn] ?? null;

    // Attempt to verify subscription details with PayPal to prevent spoofed activations
    // Use sandbox credentials when running in sandbox environment
    if ($environment === 'sandbox') {
        $paypalClientId = getenv('PAYPAL_CLIENT_ID_SANDBOX') ?: ($_ENV['PAYPAL_CLIENT_ID_SANDBOX'] ?? null);
        $paypalClientSecret = getenv('PAYPAL_CLIENT_SECRET_SANDBOX') ?: ($_ENV['PAYPAL_CLIENT_SECRET_SANDBOX'] ?? null);
    } else {
        $paypalClientId = getenv('PAYPAL_CLIENT_ID') ?: ($_ENV['PAYPAL_CLIENT_ID'] ?? null);
        $paypalClientSecret = getenv('PAYPAL_CLIENT_SECRET') ?: ($_ENV['PAYPAL_CLIENT_SECRET'] ?? null);
    }

    // PayPal environment logging disabled to avoid noisy logs

    if (empty($paypalClientId) || empty($paypalClientSecret)) {
        // If PayPal credentials are missing, refuse to auto-activate to avoid spoofing
        echo json_encode(['success' => false, 'error' => 'Server configuration missing PayPal credentials']);
        return;
    }

    // Get access token
    $authUrl = ($environment === 'live') ? 'https://api-m.paypal.com/v1/oauth2/token' : 'https://api-m.sandbox.paypal.com/v1/oauth2/token';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $authUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_USERPWD, $paypalClientId . ':' . $paypalClientSecret);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Accept-Language: en_US']);
    $tokenResp = curl_exec($ch);
    $tokenCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($tokenCode !== 200) {
        echo json_encode(['success' => false, 'error' => 'Failed to verify PayPal subscription (token)']);
        return;
    }

    $tokenData = json_decode($tokenResp, true);
    $accessToken = $tokenData['access_token'] ?? null;
    if (empty($accessToken)) {
        echo json_encode(['success' => false, 'error' => 'Failed to obtain PayPal access token']);
        return;
    }

    // Fetch subscription details
    $apiBase = ($environment === 'live') ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    $subUrl = $apiBase . '/v1/billing/subscriptions/' . urlencode($subscriptionId);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $subUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $accessToken]);
    $subResp = curl_exec($ch);
    $subCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($subCode !== 200) {
        echo json_encode(['success' => false, 'error' => 'Failed to fetch PayPal subscription details']);
        return;
    }

    $subData = json_decode($subResp, true);
    $liveStatus = $subData['status'] ?? null;
    $livePlanId = $subData['plan_id'] ?? null;

    if (strtoupper((string)$liveStatus) !== 'ACTIVE') {
        echo json_encode(['success' => false, 'error' => 'PayPal subscription is not active: ' . ($liveStatus ?? 'unknown')]);
        return;
    }

    if ($expectedPlanId && $livePlanId && $expectedPlanId !== $livePlanId) {
        echo json_encode(['success' => false, 'error' => 'PayPal subscription plan ID does not match expected addon plan']);
        return;
    }
    
    // Check if user already has this addon
    $existing = $db->get('user_addons', ['id', 'status'], [
        'user_id' => $userId,
        'addon_type' => $addonType
    ]);
    
    if ($existing) {
        // Update existing record
        $db->update('user_addons', [
            'paypal_subscription_id' => $subscriptionId,
            'status' => 'active',
            'subscription_start_date' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $existing['id']]);
    } else {
        // Insert new record
        $db->insert('user_addons', [
            'user_id' => $userId,
            'addon_type' => $addonType,
            'paypal_subscription_id' => $subscriptionId,
            'status' => 'active',
            'amount_usd' => $addon['amount_usd'],
            'billing_interval' => 'MONTH',
            'subscription_start_date' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    // Log the activation
    error_log("Addon activated: user={$userId}, addon={$addonType}, subscription={$subscriptionId}");
    
    // Also attempt to create a corresponding user_subscriptions entry
    try {
        $plan = $db->get('subscription_plans', '*', [
            'OR' => [
                'paypal_plan_id' => $livePlanId,
                'paypal_plan_id_sandbox' => $livePlanId
            ],
            'is_active' => 1
        ]);

        if ($plan) {
            $now = date('Y-m-d H:i:s');
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 month'));

            // Check for an existing active subscription for this user with the same PayPal plan
            $existingPlanActive = $db->get('user_subscriptions', '*', [
                'user_id' => $userId,
                'status' => 'active',
                'paypal_plan_id' => $livePlanId
            ]);

            if ($existingPlanActive) {
                // If it's the same PayPal subscription ID, this is idempotent — nothing to do
                if (($existingPlanActive['paypal_subscription_id'] ?? '') === $subscriptionId) {
                    // already recorded; no new subscription/payment needed
                    error_log("Addon activation idempotent: user={$userId}, plan={$plan['name']}, subscription={$subscriptionId}");
                } else {
                    // User already has an active subscription for this plan under a different PayPal subscription ID.
                    // To avoid double billing, do NOT create another user_subscriptions record or payment here.
                    // The addon record is already linked above; record an informational log for operators.
                    error_log("Duplicate subscription prevented: user={$userId}, plan={$plan['name']}, existing_subscription=" . ($existingPlanActive['paypal_subscription_id'] ?? 'none') . ", new_subscription={$subscriptionId}");
                }
            } else {
                // No existing active subscription for this plan — cancel other active subscriptions and create a new one
                $db->update('user_subscriptions', [
                    'status' => 'cancelled',
                    'cancelled_at' => $now
                ], [
                    'user_id' => $userId,
                    'status' => 'active'
                ]);

                // Insert new subscription if it doesn't already exist
                $existingSub = $db->get('user_subscriptions', 'id', ['paypal_subscription_id' => $subscriptionId]);
                if (!$existingSub) {
                    $db->insert('user_subscriptions', [
                        'user_id' => $userId,
                        'plan_id' => $plan['id'],
                        'status' => 'active',
                        'started_at' => $now,
                        'expires_at' => $expiresAt,
                        'payment_method' => 'paypal',
                        'paypal_subscription_id' => $subscriptionId,
                        'paypal_plan_id' => $livePlanId,
                        'amount_paid' => $plan['price_monthly'],
                        'currency' => $plan['price_currency'] ?? 'PHP',
                        'auto_renew' => 1,
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);

                    $newSubId = $db->id();

                    // Log the payment in subscription_payments
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
                        'notes' => 'Activated via addon flow',
                        'created_at' => $now,
                        'transaction_id' => $transactionId
                    ], $auditData));

                    // Update user's plan
                    $db->update('users', ['subscription_plan' => $plan['name']], ['id' => $userId]);
                    error_log("user_subscriptions created for user={$userId}, plan={$plan['name']}, subscription={$subscriptionId}");
                }
            }
        } else {
            error_log("No matching subscription_plan found for PayPal plan: {$livePlanId}");
        }
    } catch (\Throwable $e) {
        error_log('Failed to create user_subscriptions entry: ' . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Subscription activated successfully',
        'addon_type' => $addonType,
        'addon_name' => $addon['name']
    ]);
});

// Payment routes
$router->req('/bank-payments', 'PaymentController@bankPayments');
$router->req('/gcash-payments', 'PaymentController@gcashPayments');
$router->req('/crypto-payments', 'PaymentController@cryptoPayments');
$router->req('/api/payments/crypto-info', 'PaymentController@cryptoInfo');
$router->req('/api/user/payment-details', 'PaymentController@paymentDetails');
$router->req('/api/payment/check-status/{paymentId}', 'PaymentController@checkStatus');
$router->req('/api/payment/request-review/{paymentId}', 'PaymentController@requestReview');
$router->req('/receipt-image/{filename}', 'PaymentController@receiptImage');

$router->req('/dashboard', 'UserController@dashboard');

// Seller KYC and Product management (organized under /marketplace/sellers)
$router->req('/marketplace/sellers/kyc', 'SellerController@kycForm');
$router->req('/marketplace/sellers/kyc/submit', 'SellerController@submitKyc', ['POST']);
$router->req('/marketplace/sellers/products', 'SellerController@products');
$router->req('/marketplace/sellers/products/new', 'SellerController@productNew');
$router->req('/marketplace/sellers/products/create', 'SellerController@productCreate', ['POST']);

// Admin KYC review
$router->req('/admin/kyc', 'AdminKycController@index');
$router->req('/admin/kyc/review/{id}', 'AdminKycController@review', ['POST']);

// API: get marketplace products (published & visible)
$router->req('/api/mall/products', function() use ($db) {

// Upload endpoint used by marketplace upload modal (seller area)
$router->req('/marketplace/sellers/upload', 'MallController@upload', ['POST']);

    header('Content-Type: application/json');
    $products = [];
    try {
        $rows = $db->select('products', '*', ['status' => 'published', 'is_visible' => 1, 'ORDER' => ['created_at' => 'DESC'], 'LIMIT' => [0, 200]]);
        foreach ($rows as $r) {
            $products[] = [
                'id' => (int)$r['id'],
                'title' => $r['title'],
                'price' => floatval($r['price']),
                'currency' => $r['currency'],
                'category_id' => $r['category_id'],
                'short_description' => $r['short_description'],
                'images' => !empty($r['images']) ? json_decode($r['images'], true) : [],
            ];
        }
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'DB error']); return;
    }
    echo json_encode(['success' => true, 'products' => $products]);
});
$router->req('/user/profile/{ident}', 'UserController@profile');
$router->req('/user/commissions', 'CommissionsController@index');
$router->req('/user/network-tree/compact-view', 'UserController@networkTreeCompact');
$router->req('/user', 'UserController@user');

// Webhooks
$router->req('/webhook', 'WebhookController@webhook');
$router->req('/webhook/status', 'WebhookController@saiCodeCheck');

// Editor routes
$router->req('/editor', 'EditorController@index');
$router->req('/code', 'CodeController@code');
$router->req('/editor/toggle_sandbox', 'EditorController@toggleSandbox', ['POST']);
$router->req('/editor/settings', 'EditorController@settings', ['POST']);
$router->req('/editor/tree', 'EditorController@tree');
$router->req('/editor/create', 'EditorController@create', ['POST']);
$router->req('/editor/rename', 'EditorController@rename', ['POST']);
$router->req('/editor/delete', 'EditorController@delete', ['POST']);
$router->req('/editor/paste', 'EditorController@paste', ['POST']);
$router->req('/editor/save', 'EditorController@save', ['POST']);
$router->req('/editor/file', 'EditorController@file');

// Chat routes
$router->get('/chat', 'ChatController@index');
$router->post('/chat', 'ChatController@stream');
$router->req('/chat/create_sandbox', 'ChatController@createSandbox', ['POST']);

// Live Settings (v2 install - admin configuration panel)
// Note: CSRF validated internally since /live needs to work before .installed exists
$router->get('/live', 'LiveController@index');
$router->post('/live', 'LiveController@save');
$router->post('/live/activate', 'LiveController@activate');
$router->get('/live/imagegen/model-status', 'LiveController@imagegenModelStatus');
$router->req('/chat/upload-image', 'ChatController@uploadImage', ['POST']);
$router->req('/chat/upload-document', 'ChatController@uploadDocument', ['POST']);
$router->req('/chat/documents', 'ChatController@getDocuments');
$router->req('/chat/documents/delete', 'ChatController@deleteDocument', ['POST']);
$router->req('/storage/chat_images/{userId}/{filename}', 'ChatController@serveImage');
$router->req('/storage/generated_images/{userId}/{filename}', 'ChatController@serveGeneratedImage');
$router->req('/chat/conversations', 'ChatController@conversations');
$router->req('/chat/conversations/save', 'ChatController@saveConversation', ['POST']);
$router->req('/chat/conversations/delete', 'ChatController@deleteConversation', ['POST']);
$router->req('/chat/conversations/sync', 'ChatController@syncConversations', ['POST']);

// PandaSearch - Isolated web search test endpoint
$router->get('/pandasearch', 'ChatController@pandaSearchInfo');
$router->post('/pandasearch', 'ChatController@pandaSearch');

// ImageGen - LightPanda-based image generation via Raphael AI
$router->get('/imagegen', 'ChatController@imageGenInfo');
$router->post('/imagegen', 'ChatController@imageGen');
$router->req('/storage/imagegen/{filename}', 'ChatController@serveImageGenFile');

// Sandbox API routes (using /api/sandbox to avoid conflict with sandbox-proxy at /sandbox/*)
$router->req('/api/sandbox/image-install-status', 'SandboxController@imageInstallStatus');
$router->req('/api/sandbox/install-session-status', 'SandboxController@installSessionStatus');
$router->req('/api/sandbox/status', 'SandboxController@status');
$router->req('/api/sandbox/health', 'SandboxController@health');
$router->req('/api/sandbox/creation-status', 'SandboxController@creationStatus');
$router->req('/api/sandbox/create-async', 'SandboxController@createAsync', ['POST']);
$router->req('/api/sandbox/install', 'SandboxController@install', ['POST']);
$router->req('/api/sandbox/start', 'SandboxController@start', ['POST']);
$router->req('/api/sandbox/call', 'SandboxController@call', ['POST']);
$router->req('/api/sandbox/vnc', 'SandboxController@vnc', ['POST']);
$router->req('/api/sandbox/destroy', 'SandboxController@destroy', ['POST']);

// OpenWebUI API routes
$router->req('/api/sandbox/openwebui/status', 'SandboxController@openwebuiStatus');
$router->req('/api/sandbox/openwebui/install', 'SandboxController@openwebuiInstall', ['POST']);
$router->req('/api/sandbox/openwebui/start', 'SandboxController@openwebuiStart', ['POST']);
$router->req('/api/sandbox/openwebui/stop', 'SandboxController@openwebuiStop', ['POST']);
$router->req('/api/sandbox/openwebui/register-domain', 'SandboxController@registerOpenwebuiDomain', ['POST']);
$router->req('/api/sandbox/check-url-ready', 'SandboxController@checkUrlReady');

// Ginto Cloud API routes (sandbox subdomain access)
$router->req('/api/sandbox/cloud/register', 'SandboxController@registerCloudDomain', ['POST']);
$router->req('/api/sandbox/cloud/status', 'SandboxController@cloudDomainStatus');

// Ginto Tunnel API routes (expose local services to web)
$router->req('/api/tunnel/request', 'TunnelController@requestTunnel', ['POST']);
$router->req('/api/tunnel/status', 'TunnelController@tunnelStatus');
$router->req('/api/tunnel/disconnect', 'TunnelController@disconnectTunnel', ['POST']);
$router->req('/api/tunnel/verify', 'TunnelController@verifyTunnel');
$router->req('/api/tunnel/relay/approval', 'TunnelController@relayApproval');
$router->req('/api/tunnel/time', 'TunnelController@tunnelTime');

// Member Messenger routes (Facebook-like chat between members)
$router->req('/messenger', 'MessengerController@index');
$router->req('/messenger/conversations', 'MessengerController@getConversations');
$router->req('/messenger/messages', 'MessengerController@getMessages');
$router->req('/messenger/send', 'MessengerController@sendMessage', ['POST']);
$router->req('/messenger/start', 'MessengerController@startConversation', ['POST']);
$router->req('/messenger/create-group', 'MessengerController@createGroupConversation', ['POST']);
$router->req('/messenger/group-members/{id}', 'MessengerController@getGroupMembers');
$router->req('/messenger/search-users', 'MessengerController@searchUsers');
$router->req('/messenger/suggested-users', 'MessengerController@getSuggestedUsers');
$router->req('/messenger/read', 'MessengerController@markRead', ['POST']);
$router->req('/messenger/status', 'MessengerController@updateOnlineStatus', ['POST']);
$router->req('/messenger/unread-count', 'MessengerController@getUnreadCount');
$router->req('/messenger/archive', 'MessengerController@archiveConversation', ['POST']);
$router->req('/messenger/delete', 'MessengerController@deleteConversation', ['POST']);

// Ginto FRP Tunnel routes (high-performance tunnel using frp)
$router->req('/frp/install.sh', 'FrpController@serveInstaller');
$router->req('/frp/ginto-frpc.sh', 'FrpController@serveClient');
$router->req('/frp/frpc.toml', 'FrpController@serveConfig');
$router->req('/api/frp/info', 'FrpController@getConnectionInfo');
$router->req('/api/frp/token', 'FrpController@getTokenInfo');
$router->req('/api/frp/token/generate', 'FrpController@generateToken', ['POST']);
$router->req('/api/frp/token/revoke', 'FrpController@revokeToken', ['DELETE', 'POST']);
$router->req('/api/frp/tunnels', 'FrpController@listTunnels');
$router->req('/api/frp/validate', 'FrpController@validateToken', ['POST']);

// LXC binary path helper
function getLxcBin(): ?string {
    static $lxcBin = null;
    static $checked = false;
    
    if (!$checked) {
        $checked = true;
        foreach (['/snap/bin/lxc', '/usr/bin/lxc', '/usr/local/bin/lxc'] as $path) {
            if (file_exists($path) && is_executable($path)) {
                $lxcBin = $path;
                break;
            }
        }
        if (!$lxcBin) {
            $which = trim(shell_exec('which lxc 2>/dev/null') ?? '');
            if (!empty($which) && file_exists($which)) {
                $lxcBin = $which;
            }
        }
    }
    return $lxcBin;
}

// Admin LXC Manager (Proxmox-style)
$router->req('/admin/lxc', 'AdminLxcController@index');
$router->req('/admin/api/lxc/containers', 'AdminLxcController@containers');
$router->req('/admin/api/lxc/containers/{name}/{action}', 'AdminLxcController@containerAction');
$router->req('/admin/api/lxc/containers/{name}', 'AdminLxcController@containerDetails');
$router->req('/admin/api/lxc/images', 'AdminLxcController@images');
$router->req('/admin/api/lxc/images/{fingerprint}', 'AdminLxcController@imageDelete');
$router->req('/admin/api/lxc/images/pull', 'AdminLxcController@imagePull', ['POST']);
$router->req('/admin/api/lxc/storage', 'AdminLxcController@storage');
$router->req('/admin/api/lxc/networks', 'AdminLxcController@networks');
$router->get('/admin/api/lxc/stats', 'AdminLxcController@stats');
$router->req('/admin/api/lxc/prune', 'AdminLxcController@prune', ['POST']);
$router->req('/admin/lxc/vnc/{name}', 'AdminLxcController@vncConnect', ['POST']);

// Client sandbox proxy routes
$router->get('/clients', 'ClientsController@proxyRoot');
$router->get('/clients/{path:.*}', 'ClientsController@proxy');
$router->post('/clients', 'ClientsController@proxyRootPost');
$router->post('/clients/{path:.*}', 'ClientsController@proxy');
$router->get('/sandbox-preview/{sandboxId}/{path:.*}', 'ClientsController@preview');
$router->get('/sandbox-preview/{sandboxId}', 'ClientsController@previewRoot');

$router->req('/rate-limits', 'ApiController@rateLimits');

// Courses
$router->req('/courses', 'CourseController@index');
$router->req('/courses/pricing', 'CourseController@pricing');
$router->req('/courses/{slug}', 'CourseController@detail');
$router->req('/courses/{courseSlug}/lesson/{lessonSlug}', 'CourseController@lesson');
$router->req('/api/courses/complete-lesson', 'CourseController@completeLesson');

// Subscriptions
$router->req('/upgrade', 'SubscriptionController@upgrade');
$router->req('/subscribe', 'SubscriptionController@subscribe');
$router->req('/subscribe/success', 'SubscriptionController@success');
$router->req('/api/subscription/activate', 'SubscriptionController@activate', ['POST']);
$router->req('/api/register/paypal-order', 'PaymentController@paypalOrder');
$router->req('/api/register/paypal-capture', 'PaymentController@paypalCapture');
$router->req('/api/register/paypal-plan', 'PaymentController@paypalCreatePlan');
$router->req('/api/register/paypal-subscription', 'PaymentController@paypalSubscription');
$router->req('/api/register/paypal-verify-subscription', 'PaymentController@paypalVerifySubscription');

// Masterclass
$router->req('/masterclass', 'MasterclassController@index');
$router->req('/masterclass/pricing', 'MasterclassController@pricing');
$router->req('/masterclass/{slug}', 'MasterclassController@detail');
$router->req('/masterclass/{masterclassSlug}/lesson/{lessonSlug}', 'MasterclassController@lesson');
$router->req('/api/masterclass/complete-lesson', 'MasterclassController@completeLesson');

$router->req('/websearch', 'ApiController@websearch');

// MCP routes (admin only)
$router->req('/mcp/call', 'McpController@call');
$router->req('/mcp/probe', 'McpController@probe');
$router->req('/mcp/chat', 'McpController@chat');
$router->req('/mcp/invoke', 'McpController@invoke');
$router->req('/mcp/discover', 'McpController@discover');
$router->req('/mcp/unified', 'McpController@unified');

// Debug/API routes
$router->req('/debug/llm', 'DebugController@llm');
$router->req('/api/models', 'ApiController@models');
$router->req('/api/models/set', 'ApiController@modelsSet');
// Debug-only route to set session provider/model via localhost (for testing)
$router->req('/debug/session-set', 'DebugController@setSession', ['POST']);
$router->req('/api/provider-keys', 'ApiController@providerKeys');

// User console API
$router->req('/api/console/logs', 'ApiController@consoleLogs', ['GET']);

// Audio routes
$router->req('/audio/tts', 'AudioController@tts', ['POST']);
$router->req('/audio/stt', 'AudioController@stt', ['POST']);

// Playground routes
$router->req('/playground', 'PlaygroundController@index');
$router->req('/playground/logs', 'PlaygroundController@logs');
$router->req('/playground/logs/create-sample', 'PlaygroundController@createSampleLog', ['POST']);
$router->req('/playground/logs/{id}', 'PlaygroundController@logDetail');
$router->req('/playground/editor/install_env', 'PlaygroundController@installEnv', ['POST']);
$router->req('/playground/editor/install_status', 'PlaygroundController@installStatus', ['GET']);
$router->req('/playground/editor/save', 'PlaygroundController@save', ['POST']);
$router->req('/playground/editor/toggle_sandbox', 'PlaygroundController@toggleSandbox', ['POST']);
$router->req('/playground/editor/session_debug', 'PlaygroundController@sessionDebug', ['GET']);
$router->req('/playground/editor/tree', 'PlaygroundController@tree', ['GET']);
$router->req('/playground/editor/create', 'PlaygroundController@create', ['POST']);
$router->req('/playground/editor/rename', 'PlaygroundController@rename', ['POST']);
$router->req('/playground/editor/delete', 'PlaygroundController@delete', ['POST']);
$router->req('/playground/editor/paste', 'PlaygroundController@paste', ['POST']);
$router->req('/playground/console/environment', 'PlaygroundController@consoleEnvironment', ['GET']);
$router->req('/playground/console/exec', 'PlaygroundController@consoleExec', ['POST']);
$router->req('/playground/console/logs', 'PlaygroundController@consoleLogs', ['GET']);
$router->req('/playground/{tool}', 'PlaygroundController@tool'); // Catch-all must be last
