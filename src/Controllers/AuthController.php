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
     * Downline view (legacy route)
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
