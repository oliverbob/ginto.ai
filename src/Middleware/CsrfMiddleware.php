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
        '/api/mall/wallet/topup/create', // Wallet top-up session create - validated by MallCheckoutController
        '/api/mall/cart/sync',            // Cart sync call from frontend localStorage
        '/api/mall/cart/refresh',         // Cart refresh call from frontend localStorage
        '/debug/session-set',             // Debug helper: allow localhost POST to set session for testing
        '/login-m',                       // Mobile app JSON login — authenticates via credentials, no CSRF session
        '/logout-m',                      // Mobile app JSON logout — kills session, CSRF not applicable
        '/api/tunnel/bind',               // Tunnel clients (frpc hosts) authenticate with a gtnl- key, not a browser session
        '/api/v1/relay/trade/buy',        // Relay callers carry a signed, single-use token; there is no session to hold a CSRF token
        '/api/v1/relay/trade/sell',
        '/api/v1/relay/analyze',
    ];

    /**
     * Additional skip paths registered at runtime.
     * Use addSkipPaths() to merge environment values or other sources during bootstrap.
     */
    private static array $customSkipPaths = [];

    /**
     * Cached list parsed from the CSRF_WHITELIST environment variable.
     */
    private static ?array $envSkipPaths = null;

    /**
     * Register extra paths that should bypass CSRF validation.
     */
    public static function addSkipPaths(array $paths): void
    {
        $merged = self::$customSkipPaths;
        foreach ($paths as $path) {
            $normalized = self::normalizePath((string)$path);
            if ($normalized === '') {
                continue;
            }
            $merged[] = $normalized;
        }
        self::$customSkipPaths = array_values(array_unique($merged));
    }

    private static function isPathWhitelisted(string $path): bool
    {
        return in_array($path, self::getEffectiveSkipPaths(), true);
    }

    private static function getEffectiveSkipPaths(): array
    {
        return array_values(array_unique(array_merge(
            self::$skipPaths,
            self::$customSkipPaths,
            self::getEnvSkipPaths()
        )));
    }

    private static function getEnvSkipPaths(): array
    {
        if (self::$envSkipPaths !== null) {
            return self::$envSkipPaths;
        }
        $raw = getenv('CSRF_WHITELIST') ?: ($_ENV['CSRF_WHITELIST'] ?? '');
        if (!$raw) {
            return self::$envSkipPaths = [];
        }
        $parts = preg_split('/[\r\n,]+/', $raw);
        $paths = [];
        if (is_array($parts)) {
            foreach ($parts as $part) {
                $normalized = self::normalizePath((string)$part);
                if ($normalized !== '') {
                    $paths[] = $normalized;
                }
            }
        }
        return self::$envSkipPaths = array_values(array_unique($paths));
    }

    private static function normalizePath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return '';
        }
        if ($trimmed[0] !== '/') {
            $trimmed = '/' . ltrim($trimmed, '/');
        }
        return $trimmed;
    }

    public function handle()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        // We only validate for mutating methods; GET/HEAD/OPTIONS can skip
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            // Ensure session is started so we can read tokens for logging/debug
            if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

            // Check if this path should skip CSRF
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
            $path = self::normalizePath($path);
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
            // Temporary bypass: allow barangay zone save and geo registration during debugging (session-based login already required)
            if (in_array($path, ['/api/barangay/seller/zones/save', '/api/barangay/product/zones/save', '/api/barangay/register-geo'], true)) {
                error_log('CsrfMiddleware: bypassing CSRF for ' . $path);
                return;
            }
            if (self::isPathWhitelisted($path)) {
                return;
            }
            // Accept common token names used across the app: '_csrf' (admin), 'csrf_token' (public forms)
            // Also parse JSON body if Content-Type is application/json. Make parsing idempotent
            // because middleware may be invoked more than once during dispatch.
            $token = $_POST['_csrf'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

            // Reuse previously-parsed raw/json body if available (avoid re-consuming php://input)
            $rawBody = $GLOBALS['_RAW_BODY'] ?? null;
            $jsonData = $GLOBALS['_JSON_BODY'] ?? null;

            if (!$token) {
                $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
                if (stripos($contentType, 'application/json') !== false) {
                    if (is_array($jsonData)) {
                        // Use cached parsed JSON
                        $token = $jsonData['_csrf'] ?? $jsonData['csrf_token'] ?? null;
                    } else {
                        // First time parsing this request body: read and cache it
                        $rawBody = @file_get_contents('php://input');
                        $GLOBALS['_RAW_BODY'] = $rawBody;
                        $jsonData = json_decode($rawBody, true);
                        if (is_array($jsonData)) {
                            $GLOBALS['_JSON_BODY'] = $jsonData;
                            $token = $jsonData['_csrf'] ?? $jsonData['csrf_token'] ?? null;
                        }
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
                // Extra debug: capture request headers and raw body to /tmp for live troubleshooting
                try {
                    // Prefer cached raw body when available
                    $rawBodyDebug = $rawBody ?? ($GLOBALS['_RAW_BODY'] ?? @file_get_contents('php://input'));
                    $allHeaders = [];
                    if (function_exists('getallheaders')) {
                        $allHeaders = getallheaders();
                    } else {
                        // Fallback: collect common HTTP_ SERVER_ vars
                        foreach ($_SERVER as $k => $v) {
                            if (strpos($k, 'HTTP_') === 0) { $allHeaders[$k] = $v; }
                        }
                    }
                    $extra = [
                        'ts' => date('c'),
                        'path' => $path,
                        'method' => $method,
                        'token_present' => $token !== null,
                        'token_value' => is_string($token) ? substr($token,0,256) : null,
                        'headers' => $allHeaders,
                        'cookies' => array_intersect_key($_COOKIE, array_flip([session_name()])),
                        'raw_body_snippet' => is_string($rawBodyDebug) ? substr($rawBodyDebug,0,2000) : null,
                        'session_csrf' => $_SESSION['csrf_token'] ?? null,
                        'session_id' => session_id(),
                    ];
                    // Always write to /tmp for quick access
                    file_put_contents('/tmp/csrf-debug-extra.log', json_encode($extra, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
                    // Also write to the project's sibling storage/logs directory (one level up from silverqueen.pro)
                    try {
                        $projectParent = dirname(__DIR__, 3); // /.../parent of silverqueen.pro
                        $storageDir = $projectParent . '/storage/logs';
                        if (!is_dir($storageDir)) @mkdir($storageDir, 0755, true);
                        $storageFile = rtrim($storageDir, '/') . '/csrf-debug-extra.log';
                        file_put_contents($storageFile, json_encode($extra, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
                    } catch (\Throwable $_) {
                        // ignore storage write failures; /tmp is primary fallback
                    }
                } catch (\Throwable $_) {
                    error_log('CsrfMiddleware: failed to write extra debug: ' . $_->getMessage());
                }

                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                exit;
            }
        }
    }
}
