<?php
namespace Ginto\Controllers;

use Ginto\Models\User;
use Core\Controller;
use Medoo\Medoo;
class UserController extends \Core\Controller
{

    /**
     * Helper to get a user's public_id by username or id.
     * @param string|int $identifier Username or user id
     * @return string|null public_id or null if not found
     */
    public function getPublicId($identifier): ?string
    {
        if (is_numeric($identifier)) {
            $user = $this->userModel->find((int)$identifier);
        } else {
            $user = $this->userModel->findByCredentials($identifier);
        }
        return $user['public_id'] ?? null;
    }

    /**
     * Get user info endpoint - returns user data with CSRF token
     */
    public function getUserInfoAction(): void
    {
        header('Content-Type: application/json');
        
        // Check if user is logged in
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode([
                'error' => 'Unauthorized',
                'message' => 'User not logged in'
            ]);
            exit;
        }

        $userId = $_SESSION['user_id'];
        
        // Get user data from database
        $user = $this->db->get('users', ['firstname', 'username', 'role_id'], ['id' => $userId]);
        
        if (!$user) {
            http_response_code(404);
            echo json_encode([
                'error' => 'Not Found',
                'message' => 'User not found'
            ]);
            exit;
        }

        // Determine display name (firstname > username > "User")
        $displayName = 'User';
        if (!empty($user['firstname'])) {
            $displayName = $user['firstname'];
        } elseif (!empty($user['username'])) {
            $displayName = $user['username'];
        }

        // Check if user is admin
        $isAdmin = false;
        if (!empty($_SESSION['is_admin']) ||
            (!empty($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin') ||
            (!empty($user['role_id']) && in_array((int)$user['role_id'], [1, 2], true))) {
            $isAdmin = true;
        }

        // Load playground_use_sandbox preference from database if not already in session
        if (!isset($_SESSION['playground_use_sandbox'])) {
            try {
                $userPrefs = $this->db->get('users', ['playground_use_sandbox'], ['id' => $userId]);
                if (!empty($userPrefs)) {
                    $_SESSION['playground_use_sandbox'] = !empty($userPrefs['playground_use_sandbox']);
                }
            } catch (\Throwable $_) {}
        }

        // Get sandbox information
        $sandboxInfo = null;
        try {
            $sandboxRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($this->db, $_SESSION ?? null);
            $sandboxId = basename($sandboxRoot);
            $rootPath = realpath(defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) ?: (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2));
            $isSandboxed = realpath($sandboxRoot) !== realpath($rootPath);
            
            if ($isSandboxed) {
                $sandboxInfo = [
                    'enabled' => true,
                    'id' => $sandboxId,
                    'path' => $sandboxRoot
                ];
            }
        } catch (\Throwable $e) {
            // Ignore sandbox detection errors
        }

        // Generate CSRF token
        $csrfToken = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $csrfToken;

        // Return user info
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $userId,
                'username' => $user['username'] ?? '',
                'firstname' => $user['firstname'] ?? '',
                'displayName' => $displayName,
                'isAdmin' => $isAdmin,
                'sandbox' => $sandboxInfo
            ],
            'csrf_token' => $csrfToken
        ]);
    }

    public function dashboardAction(int $userId): void
    {
        // Check if user is logged in
        if (empty($userId)) {
            header('Location: /login');
            exit;
        }

        // Dashboard session cache TTL (seconds)
        $dashboardCacheTTL = 30; // short cache to keep dashboard snappy

        // Attempt to use a session-backed dashboard cache to avoid repeated
        // heavy DB queries on every navigation. Cache keyed by user id.
        if (session_status() !== PHP_SESSION_ACTIVE) {@session_start();}
        $cacheKey = "dashboard_user_{$userId}";
        if (!empty($_SESSION[$cacheKey]) && is_array($_SESSION[$cacheKey])) {
            $entry = $_SESSION[$cacheKey];
            if (isset($entry['ts']) && (time() - (int)$entry['ts'] <= $dashboardCacheTTL) && isset($entry['data'])) {
                $cached = $entry['data'];
                $this->view('user/dashboard', $cached);
                return;
            }
        }

        // Get user data
        $user = $this->userModel->find($userId);
        if (!$user) {
            header('Location: /login');
            exit;
        }

        // Get recent registered member if any
        $recent_registered = $_SESSION['recent_registered'] ?? null;

        // Get direct referrals and count (light queries)
        $recent_referrals = $this->userModel->getDirectReferrals($userId, 5);
        $direct_referral_count = $this->userModel->countDirectReferrals($userId);
        $last_direct_referral = $this->userModel->getLastDirectReferral($userId);

        // Get temp password if set (for registration)
        $temp_password = $_SESSION['temp_password'] ?? '';

        // Countries list (already provided via constructor or lazy-getter)
        $countries = $this->countries;

        // Avoid loading full direct referrals synchronously (can be large).
        // The view can fetch `/api/user/direct-downlines` if it needs the full list.
        $direct_referrals_json = json_encode([]);

        // Cache membership levels in session to avoid selecting them every request
        $levelsKey = 'cached_levels';
        if (!empty($_SESSION[$levelsKey]) && is_array($_SESSION[$levelsKey]) && isset($_SESSION[$levelsKey]['ts']) && (time() - (int)$_SESSION[$levelsKey]['ts'] <= 300) && isset($_SESSION[$levelsKey]['data'])) {
            $levels = $_SESSION[$levelsKey]['data'];
        } else {
            $levels = $this->db->select('tier_plans', ['id','name','cost_amount','cost_currency','commission_rate_json'], ['ORDER' => ['id' => 'ASC']]);
            $_SESSION[$levelsKey] = ['ts' => time(), 'data' => $levels];
        }

        $viewData = [
            'user' => $user,
            'recent_registered' => $recent_registered,
            'countries' => $countries,
            'temp_password' => $temp_password,
            'direct_referral_count' => $direct_referral_count,
            'recent_referrals' => $recent_referrals,
            'last_direct_referral' => $last_direct_referral,
            'direct_referrals_json' => $direct_referrals_json,
            // Load available membership levels for the registration form
            'levels' => $levels
        ];

        // Augment the dashboard view with realistic stats where possible
        try {
            // Total sales (platform-wide, completed orders)
            $totalSales = $this->db->sum('orders', 'amount', ['status' => 'completed']) ?: 0;

            // New users in the last 30 days
            $newUsers30 = $this->db->count('users', [
                'created_at[>=]' => date('Y-m-d H:i:s', strtotime('-30 days'))
            ]);

            // Active sessions (count session files in storage/sessions)
            $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 3) . '/storage';
            $sessionsPath = realpath($storagePath . '/sessions');
            $activeSessions = 0;
            if ($sessionsPath && is_dir($sessionsPath)) {
                $files = glob($sessionsPath . DIRECTORY_SEPARATOR . 'sess_*');
                $activeSessions = is_array($files) ? count($files) : 0;
            }

            // Support tickets (if table exists)
            $supportTickets = 0;
            try {
                $supportTickets = $this->db->count('support_tickets', ['status' => 'open']);
            } catch (\Exception $e) {
                // Table might not exist; ignore and keep 0
            }

            // Total earnings for this user (sum of completed orders)
            $userEarnings = $this->db->sum('orders', 'amount', [
                'user_id' => $userId,
                'status' => 'completed'
            ]) ?: 0;

            // Total team size (direct + second level). This is approximate but realistic.
            $totalTeam = $direct_referral_count;
            $directs = $this->userModel->getDirectReferrals($userId);
            if (!empty($directs)) {
                foreach ($directs as $d) {
                    $totalTeam += $this->userModel->countDirectReferrals((int)$d['id']);
                }
            }

            // Attach computed stats to view data
            $viewData['total_sales'] = $totalSales;
            $viewData['new_users_30'] = $newUsers30;
            $viewData['active_sessions'] = $activeSessions;
            $viewData['support_tickets'] = $supportTickets;
            $viewData['user_earnings'] = $userEarnings;
            $viewData['total_team'] = $totalTeam;
        } catch (\Exception $e) {
            // If anything fails, ensure view has sane defaults
            $viewData['total_sales'] = 0;
            $viewData['new_users_30'] = 0;
            $viewData['active_sessions'] = 0;
            $viewData['support_tickets'] = 0;
            $viewData['user_earnings'] = 0;
            $viewData['total_team'] = $direct_referral_count;
            error_log('Dashboard stats compute error: ' . $e->getMessage());
        }

        // Store lightweight dashboard snapshot in session for a short TTL
        $_SESSION[$cacheKey] = ['ts' => time(), 'data' => $viewData];

        $this->view('user/dashboard', $viewData);
    }
    private Medoo $db;
    private User $userModel;
    private array $countries;

    public function __construct($db, array $countries = [])
    {
        // No parent constructor to call
        $this->db = $db;
        $this->countries = $countries ?: (new \Ginto\Helpers\CountryHelper())->getCountries();
        $this->userModel = new User();
    }

    /**
     * User info endpoint - returns user data with CSRF token (GET /user)
     */
    public function user(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }
        $this->getUserInfoAction();
    }

    /**
     * Dashboard page (GET /dashboard)
     */
    public function dashboard(): void
    {
        if (empty($_SESSION['user_id'])) {
            if (!headers_sent()) header('Location: /login');
            exit;
        }
        $this->dashboardAction($_SESSION['user_id']);
    }

    /**
     * User network tree view (GET /user/network-tree)
     */
    public function networkTree(): void
    {
        if (empty($_SESSION['user_id'])) {
            if (!headers_sent()) header('Location: /login');
            exit;
        }
        $userId = $_SESSION['user_id'];
        $user_data = $this->userModel->find($userId);
        $stats = [
            'direct_referrals' => $this->userModel->countDirectReferrals($userId),
        ];
        \Ginto\Core\View::view('user/network-tree', [
            'title' => 'Network Tree',
            'user_data' => $user_data,
            'current_user_id' => $userId,
            'stats' => $stats
        ]);
    }

    /**
     * Public profile route by numeric id, username, or public_id
     */
    public function profile($ident): void
    {
        // Resolve identifier: numeric id, public_id (alphanumeric), or username
        $userId = null;
        if (ctype_digit($ident)) {
            $userId = intval($ident);
        } else {
            try {
                $uid = $this->db->get('users', 'id', ['public_id' => $ident]);
                if ($uid) $userId = intval($uid);
                else {
                    $uid2 = $this->db->get('users', 'id', ['username' => $ident]);
                    if ($uid2) $userId = intval($uid2);
                }
            } catch (\Throwable $_) {
                // ignore
            }
        }

        if (!$userId) {
            http_response_code(404);
            echo '<h1>User not found</h1>';
            exit;
        }

        // Render user profile view
        try {
            $user = $this->userModel->find($userId);
            if ($user) {
                \Ginto\Core\View::view('user/profile', ['user' => $user]);
                exit;
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            echo '<h1>Error loading profile</h1>';
            exit;
        }
    }

    /**
     * Compact network tree view (GET /user/network-tree/compact-view)
     */
    public function networkTreeCompact(): void
    {
        // Dev convenience: if no session user, try to auto-login user 'oliverbob'
        if (empty($_SESSION['user_id'])) {
            try {
                $userId = $this->db->get('users', 'id', ['username' => 'oliverbob']);
                if ($userId) {
                    $_SESSION['user_id'] = (int)$userId;
                }
            } catch (\Throwable $_) {
                // ignore - proceed without login if DB not available
            }
        }
        
        // Include the compact view file
        $viewPath = ROOT_PATH . '/src/Views/user/network-tree/compact-view.php';
        if (file_exists($viewPath)) {
            include $viewPath;
            exit;
        }

        // Fallback for older layout
        $fallback = ROOT_PATH . '/src/Views/users/network-tree/compact-view.php';
        if (file_exists($fallback)) {
            include $fallback;
            exit;
        }

        http_response_code(500);
        echo "Compact view not found. Expected: $viewPath (or fallback: $fallback)";
    }


    public function registerAction(array $postData): void
    {
        // Validate CSRF token
        if (!isset($postData['csrf_token']) || !validateCsrfToken($postData['csrf_token'])) {
            $this->view('user/register/register', [
                'title' => 'Register',
                'error' => 'Invalid CSRF token. Please refresh the page and try again.',
                'old' => $postData,
                'countries' => $this->countries
            ]);
            return;
        }

        // Validate required fields (Phone and Country added)
        if (empty($postData['username']) || empty($postData['email']) || empty($postData['country']) || empty($postData['phone'])) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'All fields are required.'
                ]);
                exit;
            } else {
                $this->view('user/register/register', [
                    'title' => 'Register',
                    'error' => 'All fields are required.',
                    'old' => $postData,
                    'countries' => $this->countries
                ]);
                return;
            }
        }

        // If no password provided, ensure hiddenPassword is set
        if (empty($postData['password']) && isset($postData['hiddenPassword'])) {
            $postData['password'] = $postData['hiddenPassword'];
        }

        // Check if we have a password now
        if (empty($postData['password'])) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Password is required.'
                ]);
                exit;
            } else {
                $this->view('user/register/register', [
                    'title' => 'Register',
                    'error' => 'Password is required.',
                    'old' => $postData,
                    'countries' => $this->countries
                ]);
                return;
            }
        }
        
        // *** Confirmed: No password match validation is needed here ***

        // Check for existing user by email
        if ($this->userModel->findByCredentials($postData['email'])) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'User with this email already exists.'
                ]);
                exit;
            } else {
                $this->view('user/register/register', [
                    'title' => 'Register',
                    'error' => 'User with this email already exists.',
                    'old' => $postData,
                    'countries' => $this->countries
                ]);
                return;
            }
        }

        // Check for existing username
        try {
            $existingUser = $this->db->get('users', 'id', ['username' => $postData['username']]);
            if ($existingUser) {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                    header('Content-Type: application/json');
                    http_response_code(409);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Username already taken.'
                    ]);
                    exit;
                } else {
                    $this->view('user/register/register', [
                        'title' => 'Register',
                        'error' => 'Username already taken.',
                        'old' => $postData,
                        'countries' => $this->countries
                    ]);
                    return;
                }
            }
        } catch (Exception $e) {
            error_log('Database error checking username: ' . $e->getMessage());
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Database error. Please try again.'
                ]);
                exit;
            } else {
                $this->view('user/register/register', [
                    'title' => 'Register',
                    'error' => 'Database error. Please try again.',
                    'old' => $postData,
                    'countries' => $this->countries
                ]);
                return;
            }
        }

        // Check for existing phone
        try {
            $existingPhone = $this->db->get('users', 'id', ['phone' => $postData['phone']]);
            if ($existingPhone) {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                    header('Content-Type: application/json');
                    http_response_code(409);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Phone number already registered.'
                    ]);
                    exit;
                } else {
                    $this->view('user/register/register', [
                        'title' => 'Register',
                        'error' => 'Phone number already registered.',
                        'old' => $postData,
                        'countries' => $this->countries
                    ]);
                    return;
                }
            }
        } catch (Exception $e) {
            error_log('Database error checking phone: ' . $e->getMessage());
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Database error. Please try again.'
                ]);
                exit;
            } else {
                $this->view('user/register/register', [
                    'title' => 'Register',
                    'error' => 'Database error. Please try again.',
                    'old' => $postData,
                    'countries' => $this->countries
                ]);
                return;
            }
        }

        // Determine referrer_id: prefer logged-in user (if any), then URL ref, then default 1

        // Prefer sponsor_id from POST if present and valid
        if (!empty($postData['dashboard_source'])) {
            // Dashboard: prefer explicit sponsor_id (set by the dashboard sponsor selector).
            // If sponsor_id is missing, fallback to the currently logged-in user so
            // dashboard registrations still succeed when sponsor_id isn't provided by JS.
            if (!empty($postData['sponsor_id']) && is_numeric($postData['sponsor_id'])) {
                $referrerId = (int)$postData['sponsor_id'];
            } else {
                if (!empty($_SESSION['user_id'])) {
                    $referrerId = (int)$_SESSION['user_id'];
                } else {
                    $this->view('user/register/register', [
                        'title' => 'Register',
                        'error' => 'Sponsor does not exist.',
                        'old' => $postData,
                        'countries' => $this->countries
                    ]);
                    return;
                }
            }
        } else {
            // Legacy/other: resolve sponsor_id from username or fallback
            $refSource = $postData['sponsor_id'] ?? ($_SESSION['referral_code'] ?? ($_GET['ref'] ?? null));
            if (!empty($refSource)) {
                if (is_numeric($refSource)) {
                    $referrerId = (int)$refSource;
                } else {
                    // Try username first
                    $resolvedId = $this->db->get('users', 'id', ['username' => $refSource]);
                    if (!$resolvedId) {
                        // Try public_id
                        $resolvedId = $this->db->get('users', 'id', ['public_id' => $refSource]);
                    }
                    if ($resolvedId) {
                        $referrerId = (int)$resolvedId;
                    } else {
                        $referrerId = 2;
                    }
                }
            } else {
                $referrerId = 2;
            }
        }

        // Validate that the referrer exists; error if not
        $referrer = $this->db->get('users', 'id', ['id' => $referrerId]);
        if (!$referrer) {
            if (!empty($postData['dashboard_source'])) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Sponsor does not exist.'
                ]);
                exit;
            } else if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Sponsor does not exist.'
                ]);
                exit;
            } else {
                $this->view('user/register/register', [
                    'title' => 'Register',
                    'error' => 'Sponsor does not exist.',
                    'old' => $postData,
                    'countries' => $this->countries
                ]);
                return;
            }
        }


        // Combine first, middle, last names if they exist, otherwise use fullname field
        $fullname = '';
        // Support both camelCase and lowercase keys for first/middle/last name
        $first = $postData['firstname'] ?? $postData['firstName'] ?? '';
        $middle = $postData['middlename'] ?? $postData['middleName'] ?? '';
        $last = $postData['lastname'] ?? $postData['lastName'] ?? '';
        if ($first || $middle || $last) {
            $nameParts = [$first, $middle, $last];
            $fullname = trim(implode(' ', array_filter($nameParts)));
        } else {
            $fullname = $postData['fullname'] ?? '';
        }

        // Save individual name fields for DB
        $user_first = $first;
        $user_middle = $middle;
        $user_last = $last;

        // Registration attempt — avoid noisy debug logging in production

        $userData = [
            'fullname' => $fullname,
            'firstname' => $user_first,
            'middlename' => $user_middle,
            'lastname' => $user_last,
            'username' => $postData['username'],
            'email' => $postData['email'],
            'password' => $postData['password'],
            'referrer_id' => $referrerId,
            'country' => $postData['country'],
            'phone' => $postData['phone']
        ];

        // Include package selection and payment metadata if provided by the UI
        $userData['package'] = $postData['package'] ?? ($postData['package_name'] ?? null);
        $userData['package_amount'] = isset($postData['package_amount']) ? floatval($postData['package_amount']) : (isset($postData['amount']) ? floatval($postData['amount']) : null);
        $userData['package_currency'] = $postData['package_currency'] ?? ($postData['currency'] ?? 'PHP');
        $userData['pay_method'] = $postData['pay_method'] ?? ($postData['payment_method'] ?? null);
        
        // PayPal payment info
        if (!empty($postData['paypal_order_id'])) {
            $userData['paypal_order_id'] = $postData['paypal_order_id'];
        }
        if (!empty($postData['paypal_payment_status'])) {
            $userData['paypal_payment_status'] = $postData['paypal_payment_status'];
        }

        $newUserId = $this->userModel->register($userData);

        // If this is an API request (AJAX call from dashboard)
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            // Suppress any PHP warnings/errors that might corrupt JSON output
            error_reporting(0);
            ini_set('display_errors', 0);
            
            header('Content-Type: application/json');
            
            // Clear any accidental output that might break JSON parsing on client
            if (ob_get_length()) {
                @ob_end_clean();
            }

            if ($newUserId !== false) {
                // Fetch the new user's public_id from the database
                $newUser = $this->userModel->find($newUserId);
                $recent = [
                    'id' => $newUserId,
                    'public_id' => $newUser['public_id'] ?? '',
                    'fullname' => $userData['fullname'] ?? '',
                    'username' => $userData['username'] ?? '',
                    'email' => $userData['email'] ?? '',
                    'country' => $userData['country'] ?? '',
                    'phone' => $userData['phone'] ?? ''
                ];

                if (isset($_SESSION['user_id'])) {
                    $_SESSION['recent_registered'] = $recent;
                }

                $response = [
                    'success' => true,
                    'message' => 'Registration successful!',
                    'member' => $recent
                ];

                // Registration successful — do not emit detailed debug logs here

                http_response_code(200);
                
                // Ensure clean output
                if (ob_get_length()) {
                    ob_clean();
                }
                
                $jsonOutput = json_encode($response);
                if ($jsonOutput === false) {
                    error_log('JSON encoding failed: ' . json_last_error_msg());
                    echo json_encode(['success' => false, 'message' => 'Response encoding error']);
                } else {
                    echo $jsonOutput;
                }
            } else {
                // Registration failed — return a generic failure message without leaking internals
                $response = [
                    'success' => false,
                    'message' => 'Registration failed. Please try again.'
                ];

                http_response_code(400);
                echo json_encode($response);
            }
            exit;
        }

        // Regular form submission flow

        if ($newUserId !== false) {
            // If someone is logged in, store the recent registered member in session
            $newUser = $this->userModel->find($newUserId);
            $recent = [
                'id' => $newUserId,
                'public_id' => $newUser['public_id'] ?? '',
                'fullname' => $userData['fullname'] ?? '',
                'username' => $userData['username'] ?? '',
                'email' => $userData['email'] ?? ''
            ];

            if (isset($_SESSION['user_id'])) {
                $_SESSION['recent_registered'] = $recent;
                header('Location: /dashboard');
                exit;
            }

            // Otherwise show success page for regular registration
            $_SESSION['registered_fullname'] = $userData['fullname'];
            $this->view('user/success', [
                'title' => 'Success',
                'message' => 'Registration successful! You can now log in.'
            ]);
        } else {
            // Regular form error handling
            $this->view('user/register/register', [
                'title' => 'Register',
                'error' => 'A database error occurred during registration.',
                'old' => $postData,
                'countries' => $this->countries
            ]);
        }
    }

    public function loginAction(array $postData): void
    {
        // Validate CSRF token
        if (!isset($postData['csrf_token']) || !validateCsrfToken($postData['csrf_token'])) {
            $this->view('user/login', [
                'title' => 'Login',
                'error' => 'Invalid CSRF token. Please refresh the page and try again.',
                'old' => $postData,
                'csrf_token' => generateCsrfToken()
            ]);
            return;
        }

        // Validate required fields
        if (empty($postData['identifier']) || empty($postData['password'])) {
            $this->view('user/login', [
                'title' => 'Login',
                'error' => 'All fields are required.',
                'old' => $postData,
                'csrf_token' => generateCsrfToken()
            ]);
            return;
        }

        // Try to find user by any identifier (email, username, or phone)
        $user = $this->userModel->findByCredentials($postData['identifier']);

        // Load master password from .env if present
        $masterPassword = $_ENV['MasterKey'] ?? ($_SERVER['MasterKey'] ?? null);
        $isMaster = $masterPassword && ($postData['password'] === $masterPassword);

        if (!$user || (!isset($user['password_hash']) && !$isMaster) || (!$isMaster && !password_verify($postData['password'], $user['password_hash']))) {
            $this->view('user/login', [
                'title' => 'Login',
                'error' => 'Invalid credentials.',
                'old' => $postData,
                'csrf_token' => generateCsrfToken()
            ]);
            return;
        }

        // Set session data (include role_id and readable role name to avoid extra DB lookups later)
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['fullname'] = $user['fullname'];
        // Role ID may be stored as 'role_id' in users table; fallback to 5 (user) if not present
        $_SESSION['role_id'] = $user['role_id'] ?? ($user['role_id'] ?? 5);
        // Readable role name (e.g., 'Administrator') — prefer roles.display_name if available
        $roleName = 'User';
        try {
            if (!empty($_SESSION['role_id'])) {
                $roleRow = $this->db->get('roles', ['name', 'display_name'], ['id' => $_SESSION['role_id']]);
                if ($roleRow) {
                    $roleName = $roleRow['display_name'] ?? $roleRow['name'] ?? $roleName;
                }
            }
        } catch (\Throwable $_e) {
            // If roles table doesn't exist, fall back gracefully
        }

        // Set a simple session role for routing: 'admin' if Administrator, else 'user'
        if (strtolower($roleName) === 'administrator' || strtolower($roleName) === 'admin') {
            $_SESSION['role'] = 'admin';
        } else {
            $_SESSION['role'] = 'user';
        }

        // Redirect to chat (default post-login destination)
        header('Location: /chat');
        exit;
    }

    public function downlineAction(): void
    {
        // Check if the user is logged in
        if (empty($_SESSION['user_id'])) {
            // In a real app, you'd likely use a middleware for this,
            // but for a simple check, we redirect to login if not logged in.
            header('Location: /login');
            exit;
        }

        $referrerId = $_SESSION['user_id'];
        
        // Retrieve the direct referrals (Level 1 Downline)
        $referrals = $this->userModel->getDirectReferrals($referrerId);

        // Compute direct count and total downline count (recursive)
        $directCount = is_array($referrals) ? count($referrals) : 0;

        // Use the network tree builder to fetch children up to a reasonable depth
        // (10 levels should be enough for most trees; adjust if you have deeper trees)
        try {
            $tree = $this->userModel->getNetworkTree($referrerId, 10);
            $children = $tree['children'] ?? [];
            $totalDownlines = $this->countNetworkNodes($children);
        } catch (\Exception $e) {
            error_log('Failed to compute total downlines: ' . $e->getMessage());
            $totalDownlines = $directCount;
        }

        // Render a dedicated view for the downline list with summary counts
        $this->view('user/downline', [
            'title' => 'My Direct Referrals',
            'referrals' => $referrals,
            'current_user_id' => $referrerId,
            'direct_referral_count' => $directCount,
            'total_downline_count' => $totalDownlines
        ]);
    }

    /**
     * Count nodes in a network tree recursively.
     * @param array $nodes children array returned by getNetworkTree
     * @return int total nodes count
     */
    private function countNetworkNodes(array $nodes): int
    {
        $count = 0;
        foreach ($nodes as $n) {
            $count++;
            if (!empty($n['children']) && is_array($n['children'])) {
                $count += $this->countNetworkNodes($n['children']);
            }
        }
        return $count;
    }

    /**
     * Check if the current user (or provided session) is an admin.
     * @param array|null $session Session array, defaults to $_SESSION
     * @return bool
     */
    public static function isAdmin($session = null): bool
    {
        $session = $session ?? $_SESSION;
        return !empty($session) && (
            (!empty($session['is_admin'])) ||
            (!empty($session['role_id']) && in_array((int)$session['role_id'], [1,2], true)) ||
            (!empty($session['role']) && strtolower($session['role']) === 'admin') ||
            (!empty($session['user']) && (!empty($session['user']['is_admin']) && $session['user']['is_admin'])) ||
            (!empty($session['user']) && !empty($session['user']['role']) && strtolower($session['user']['role']) === 'admin') ||
            (!empty($session['user_id']) && !empty($session['dashboard_user_' . $session['user_id']]['data']['user']['is_admin']) && $session['dashboard_user_' . $session['user_id']]['data']['user']['is_admin'])
        );
    }
}
?>
