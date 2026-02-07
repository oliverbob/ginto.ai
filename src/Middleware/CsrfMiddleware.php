<?php
namespace Ginto\Middleware;

class CsrfMiddleware
{
    // Routes that skip CSRF validation (API endpoints called with session cookie)
    // Note: /chat is intentionally NOT in this list below. It will be allowed
    // for localhost requests or when a valid admin token header is present.
    // All other POST/PUT/PATCH/DELETE requests must provide a CSRF token.
    private static array $skipPaths = [
        '/mcp/call',
        '/mcp/chat',
        '/mcp/discover',
        '/audio/tts',
        '/audio/stt',
        '/websearch', // Isolated test page for GPT-OSS browser_search
        '/api/subscription/activate', // PayPal callback - no CSRF available
        '/api/register/paypal-order', // PayPal order creation - JS SDK call
        '/api/register/paypal-capture', // PayPal payment capture - JS SDK call
        '/api/validate-registration', // Pre-validates username/email before PayPal subscription - JS SDK call
        '/api/addon/activate', // Addon subscription activation - PayPal SDK call
        '/register', // Registration - validates CSRF internally in UserController
        '/webhook', // PayPal webhook - external caller
        '/bank-payments', // Bank transfer registration - validates CSRF internally in handler
        '/gcash-payments', // GCash registration - validates CSRF internally in handler
        '/crypto-payments', // Crypto USDT registration - validates CSRF internally in handler
        '/api/payments/crypto-info', // Crypto info API - AJAX only, no mutation
        '/debug/session-set', // Debug helper: allow localhost POST to set session for testing
    ];

    public function handle()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        // We only validate for mutating methods; GET/HEAD/OPTIONS can skip
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            // Ensure session is started so we can read tokens for logging/debug
            if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

            // Check if this path should skip CSRF
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
            // Log received token & session token (temp debug)
            $received = $_POST['_csrf'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '(none)';
            error_log('CsrfMiddleware: path=' . $path . ' method=' . $method . ' received_token=' . (is_string($received) ? substr($received,0,64) : '(array)'));
            error_log('CsrfMiddleware: session_csrf=' . ($_SESSION['csrf_token'] ?? '(none)'));
            error_log('CsrfMiddleware: session_id=' . session_id() . ' session_name=' . session_name() . ' cookie=' . ($_COOKIE[session_name()] ?? '(none)'));
            // Dump session contents (trim to avoid huge logs)
            error_log('CsrfMiddleware: session_dump=' . substr(var_export(array_intersect_key($_SESSION, array_flip(['csrf_token','user_id'])), true), 0, 400));

            // ALSO write debug info to /tmp to guarantee it is visible from the container
            try {
                $debug = [
                    'ts' => date('c'),
                    'path' => $path,
                    'method' => $method,
                    'received_token' => is_string($received) ? substr($received, 0, 256) : '(array)',
                    'session_csrf' => $_SESSION['csrf_token'] ?? '(none)',
                    'session_id' => session_id(),
                    'session_name' => session_name(),
                    'cookie_value' => $_COOKIE[session_name()] ?? '(none)',
                ];
                file_put_contents('/tmp/csrf-debug.log', json_encode($debug, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
                // Also write to application storage logs for easier discovery (should be writable by php-fpm)
                try {
                    $storageLog = defined('STORAGE_PATH') ? rtrim(STORAGE_PATH, '/') . '/logs/csrf-debug.log' : '/var/log/csrf-debug.log';
                    if (!is_dir(dirname($storageLog))) @mkdir(dirname($storageLog), 0755, true);
                    file_put_contents($storageLog, json_encode($debug, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
                } catch (\Throwable $e) {
                    error_log('CsrfMiddleware: failed to write storage debug file: ' . $e->getMessage());
                }
            } catch (\Throwable $e) {
                // If writing fails, emit an error_log but continue
                error_log('CsrfMiddleware: failed to write /tmp debug file: ' . $e->getMessage());
            }

            // Special-case /chat: allow if request originates from localhost or
            // if caller supplies a valid admin token header. Otherwise require CSRF.
            if ($path === '/chat') {
                $remote = $_SERVER['REMOTE_ADDR'] ?? '';
                $tokenHeader = $_SERVER['HTTP_X_GINTO_ADMIN_TOKEN'] ?? $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? null;
                $expected = getenv('GINTO_ADMIN_TOKEN') ?: getenv('ADMIN_TOKEN');
                if ($remote === '127.0.0.1' || $remote === '::1') {
                    return;
                }
                if ($expected && $tokenHeader && hash_equals((string)$expected, (string)$tokenHeader)) {
                    return;
                }
                // else fall through and require CSRF token below
            }
            if (in_array($path, self::$skipPaths)) {
                return;
            }
            // Accept common token names used across the app: '_csrf' (admin), 'csrf_token' (public forms)
            // Also parse JSON body if Content-Type is application/json
            $token = $_POST['_csrf'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            
            // If no token found in POST, check JSON body
            if (!$token) {
                $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
                if (stripos($contentType, 'application/json') !== false) {
                    $rawBody = file_get_contents('php://input');
                    // Store raw body for controllers to use (php://input can only be read once)
                    $GLOBALS['_RAW_BODY'] = $rawBody;
                    $jsonData = json_decode($rawBody, true);
                    if (is_array($jsonData)) {
                        // Store parsed JSON for controllers
                        $GLOBALS['_JSON_BODY'] = $jsonData;
                        $token = $jsonData['_csrf'] ?? $jsonData['csrf_token'] ?? null;
                    }
                }
            }
            if (!function_exists('validateCsrfToken')) {
                error_log('CSRF validation helper missing');
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'CSRF validation not configured']);
                exit;
            }
            if (!$token || !validateCsrfToken($token)) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                exit;
            }
        }
    }
}
