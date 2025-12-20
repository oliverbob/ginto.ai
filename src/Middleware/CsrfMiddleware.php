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
        '/webhook', // PayPal webhook - external caller
        '/register', // Public registration - validates CSRF internally in controller
        '/bank-payments', // Bank transfer registration - validates CSRF internally in handler
        '/gcash-payments', // GCash registration - validates CSRF internally in handler
        '/crypto-payments', // Crypto USDT registration - validates CSRF internally in handler
        '/api/payments/crypto-info', // Crypto info API - AJAX only, no mutation
    ];

    public function handle()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        // We only validate for mutating methods; GET/HEAD/OPTIONS can skip
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            // Check if this path should skip CSRF
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
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
                    $jsonData = json_decode($rawBody, true);
                    if (is_array($jsonData)) {
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
