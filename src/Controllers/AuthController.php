<?php
namespace Ginto\Controllers;

use Ginto\Models\User;
use Ginto\Core\View;

/**
 * Authentication Controller
 * Handles login, logout, register, and session management
 */
class AuthController
{
    protected $db;
    protected $countries;
    protected $userModel;

    public function __construct($db = null, array $countries = [])
    {
        if ($db === null) {
            $db = \Ginto\Core\Database::getInstance();
        }
        $this->db = $db;
        $this->countries = $countries ?: \Ginto\Helpers\CountryHelper::getCountries();
        $this->userModel = new User();
    }

    /**
     * Home page - redirect admins to /admin, regular users stay on current page
     * Note: / now serves ChatController directly (see web.php routes)
     */
    public function index(): void
    {
        if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            if (!headers_sent()) header('Location: /admin');
            exit;
        }
        // Serve chat page directly instead of redirecting
        $chatController = new ChatController();
        $chatController->index();
    }

    /**
     * Login page and action
     */
    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller = new UserController($this->db, $this->countries);
            $controller->loginAction($_POST);
        } else {
            // Store ?next= redirect target in session so loginAction can use it after success
            if (!empty($_GET['next'])) {
                if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
                $next = $_GET['next'];
                // Only allow relative URLs to prevent open redirect
                if (str_starts_with($next, '/') && !str_starts_with($next, '//')) {
                    $_SESSION['login_redirect'] = $next;
                }
            }
            View::view('user/login', [
                'title' => 'Login'
            ]);
        }
    }

    /**
     * Logout - destroy session and redirect
     */
    public function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        
        // Unset all session variables
        $_SESSION = [];
        
        // Delete session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
                setcookie(session_name(), '', [
                    'expires' => time() - 42000,
                    'path' => $params['path'] ?? '/',
                    'domain' => $params['domain'] ?? '',
                    'secure' => $params['secure'] ?? false,
                    'httponly' => $params['httponly'] ?? true,
                    'samesite' => 'Lax'
                ]);
            } else {
                $cookieHeader = session_name() . '=; Path=' . ($params['path'] ?? '/') . '; Expires=' . gmdate('D, d-M-Y H:i:s T', time() - 42000) . (($params['secure'] ?? false) ? '; Secure' : '') . '; HttpOnly; SameSite=Lax';
                header('Set-Cookie: ' . $cookieHeader, false);
            }
        }
        
        // Destroy the session
        session_unset();
        session_destroy();
        
        if (!headers_sent()) header('Location: /');
        exit;
    }

    /**
     * Register page and action
     */
    public function register(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {@session_start();}
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller = new UserController($this->db, $this->countries);
            $controller->registerAction($_POST);
        } else {
            $refId = $_GET['ref'] ?? ($_SESSION['referral_code'] ?? null);
            if (isset($_GET['ref'])) {
                $_SESSION['referral_code'] = $_GET['ref'];
            }
            
            $detectedCountryCode = null;
            $levels = [];
            
            try {
                $levels = $this->db->select('tier_plans', ['id','name','cost_amount','cost_currency','commission_rate_json'], ['ORDER' => ['id' => 'ASC']]);
            } catch (\Exception $e) {
                error_log('Warning: Could not load levels for register view: ' . $e->getMessage());
            }
            
            View::view('user/register/register', [
                'title' => 'Register for Ginto',
                'ref_id' => $refId,
                'error' => null,
                'old' => [],
                'countries' => $this->countries,
                'default_country_code' => $detectedCountryCode,
                'levels' => $levels,
                'csrf_token' => generateCsrfToken(true)
            ]);
        }
    }

    /**
     * Mobile login API — POST /login-m
     *
     * Accepts JSON or form-encoded body: { identifier, password }
     * No CSRF required (Bearer/session returned instead).
     * Returns JSON: { success, session_id, user } on success
     *               { success, error }              on failure
     * Rate-limited to 10 attempts per minute per IP via simple session counter.
     */
    public function loginMobile(): void
    {
        header('Content-Type: application/json');

        // Parse body (JSON or form-encoded)
        $body = file_get_contents('php://input');
        $data = [];
        if (!empty($body)) {
            $decoded = json_decode($body, true);
            $data = is_array($decoded) ? $decoded : [];
        }
        // Fall back to POST if no JSON body
        if (empty($data)) {
            $data = $_POST;
        }

        $identifier = trim($data['identifier'] ?? '');
        $password   = $data['password'] ?? '';

        if ($identifier === '' || $password === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'identifier and password are required.']);
            return;
        }

        // --- Simple per-IP rate limit (10 attempts / 60s) ---
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rlKey = 'login_m_rl_' . md5($ip);
        $now   = time();
        $rl    = $_SESSION[$rlKey] ?? ['count' => 0, 'window' => $now];
        if ($now - $rl['window'] > 60) {
            $rl = ['count' => 0, 'window' => $now];
        }
        $rl['count']++;
        $_SESSION[$rlKey] = $rl;
        if ($rl['count'] > 10) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Too many attempts. Please wait a moment.']);
            return;
        }

        // --- Validate credentials ---
        $userModel = new \Ginto\Models\User();
        $user = $userModel->findByCredentials($identifier);

        $masterPassword = $_ENV['MasterKey'] ?? ($_SERVER['MasterKey'] ?? null);
        $isMaster = $masterPassword && ($password === $masterPassword);

        if (!$user
            || (!isset($user['password_hash']) && !$isMaster)
            || (!$isMaster && !password_verify($password, $user['password_hash']))
        ) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid credentials.']);
            return;
        }

        // Reset rate-limit counter on success
        $_SESSION[$rlKey] = ['count' => 0, 'window' => $now];

        // --- Build session ---
        session_regenerate_id(true);

        $_SESSION['user_id']            = $user['id'];
        $_SESSION['username']           = $user['username'];
        $_SESSION['fullname']           = $user['fullname'] ?? '';
        $_SESSION['user']               = $user['email'] ?? $user['username'] ?? '';
        $_SESSION['user_email']         = $user['email'] ?? '';
        $_SESSION['user_full_name']     = $user['fullname'] ?? $user['full_name'] ?? 'User';
        $_SESSION['user_username']      = $user['username'] ?? '';
        $_SESSION['user_profile_picture'] = $user['avatar'] ?? $user['profile_picture'] ?? $user['photo'] ?? null;
        $_SESSION['role_id']            = $user['role_id'] ?? 5;

        $roleName = 'User';
        try {
            if (!empty($_SESSION['role_id'])) {
                $roleRow = $this->db->get('roles', ['name', 'display_name'], ['id' => $_SESSION['role_id']]);
                if ($roleRow) {
                    $roleName = $roleRow['display_name'] ?? $roleRow['name'] ?? $roleName;
                }
            }
        } catch (\Throwable $_e) { /* ignore */ }
        $_SESSION['role'] = (strtolower($roleName) === 'administrator' || strtolower($roleName) === 'admin') ? 'admin' : 'user';

        try {
            $this->db->update('users', ['last_login' => date('Y-m-d H:i:s')], ['id' => $user['id']]);
        } catch (\Throwable $_e) { /* ignore */ }

        // Return the session ID so the Android app can inject it as PHPSESSID
        echo json_encode([
            'success'    => true,
            'session_id' => session_id(),
            'user' => [
                'id'       => $user['id'],
                'username' => $user['username'],
                'fullname' => $user['fullname'] ?? '',
                'email'    => $user['email'] ?? '',
                'role'     => $_SESSION['role'],
            ],
        ]);
    }

    /**     * Mobile logout — destroys the session and returns JSON (no redirect).
     * Called by the Android app via fetch() inside the WebView.
     */
    public function logoutMobile(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

        // Expire the session cookie in the browser / WebView
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
                setcookie(session_name(), '', [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'] ?? '/',
                    'domain'   => $params['domain'] ?? '',
                    'secure'   => $params['secure'] ?? false,
                    'httponly' => $params['httponly'] ?? true,
                    'samesite' => 'Lax',
                ]);
            } else {
                $cookieHeader = session_name() . '=; Path=' . ($params['path'] ?? '/') . '; Expires=' . gmdate('D, d-M-Y H:i:s T', time() - 42000) . (($params['secure'] ?? false) ? '; Secure' : '') . '; HttpOnly; SameSite=Lax';
                header('Set-Cookie: ' . $cookieHeader, false);
            }
        }

        $_SESSION = [];
        session_unset();
        session_destroy();

        // POST: return JSON (fetch() from Android app)
        // GET: serve an HTML page that clears localStorage then redirects to /chat-m
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo <<<'HTML'
<!DOCTYPE html><html><head><meta charset="utf-8">
<title>Logging out…</title></head><body>
<script>
try {
    var keys = ['ginto_conversations_v2','ginto_conversations','ginto_disabled_tools'];
    keys.forEach(function(k){ localStorage.removeItem(k); });
    sessionStorage.clear();
} catch(e) {}
window.location.replace('/chat-m');
</script>
</body></html>
HTML;
        }
        exit;
    }

    /**     * Downline view (legacy route)
     */
    public function downline(): void
    {
        if (empty($_SESSION['user_id'])) {
            if (!headers_sent()) header('Location: /login');
            exit;
        }
        $controller = new UserController($this->db, $this->countries);
        $controller->downlineAction();
    }
}
