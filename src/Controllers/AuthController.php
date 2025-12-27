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
        $this->countries = $countries;
        $this->userModel = new User();
    }

    /**
     * Home page - redirect based on session role
     */
    public function index(): void
    {
        if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            if (!headers_sent()) header('Location: /admin');
            exit;
        }
        if (!headers_sent()) header('Location: /chat');
        exit;
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
            setcookie(session_name(), '', time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                $params['secure'] ?? false,
                $params['httponly'] ?? true
            );
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
