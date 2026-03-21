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

        // Check if user is admin using the foolproof static method
        $isAdmin = self::isAdmin($_SESSION);

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

        // CSRF token: keep stable for the session.
        // Regenerating it on every polling request (e.g. /chat) breaks other tabs
        // that rely on the session token.
        if (empty($_SESSION['csrf_token'])) {
            $csrfToken = bin2hex(random_bytes(32));
            $_SESSION['csrf_token'] = $csrfToken;
        } else {
            $csrfToken = (string)$_SESSION['csrf_token'];
        }

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
     * Account Keys page (GET /account/keys)
     */
    public function accountKeys(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['user_id'])) {
            if (!headers_sent()) header('Location: /login');
            exit;
        }

        \Ginto\Core\View::view('user/account-keys', [
            'title' => 'Account Keys',
            'user_id' => (int)$_SESSION['user_id'],
        ]);
    }

    /**
     * Account summary page (GET /account)
     */
    public function account(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['user_id'])) {
            if (!headers_sent()) header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $user = null;
        try {
            $user = $this->db->get('users', ['id', 'username', 'fullname', 'email', 'public_id'], ['id' => $userId]);
        } catch (\Throwable $e) {
            $user = null;
        }

        \Ginto\Core\View::view('user/account', [
            'title' => 'Account',
            'user' => is_array($user) ? $user : ['id' => $userId],
        ]);
    }

    /**
     * Settings page (GET /user/settings)
     */
    public function settings(?string $success = null, ?string $error = null, array $userOverride = []): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['user_id'])) {
            if (!headers_sent()) header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        try {
            $user = $this->db->get('users', [
                'id', 'username', 'fullname', 'first_name', 'last_name',
                'firstname', 'lastname', 'email', 'phone', 'country', 'gender', 'bio',
                'shipping_address_json', 'home_address_json',
            ], ['id' => $userId]);
        } catch (\Throwable $e) {
            $user = [];
        }
        if (!is_array($user)) $user = [];
        // Decode JSON address fields
        $user['shipping_address'] = json_decode((string)($user['shipping_address_json'] ?? 'null'), true) ?: [];
        $user['home_address']     = json_decode((string)($user['home_address_json']     ?? 'null'), true) ?: [];
        if ($userOverride) $user = array_merge($user, $userOverride);

        \Ginto\Core\View::view('user/settings', [
            'title'   => 'Settings',
            'user'    => $user,
            'success' => $success,
            'error'   => $error,
        ]);
    }

    /**
     * Process settings update (POST /user/settings/update)
     */
    public function settingsUpdate(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['user_id'])) {
            if (!headers_sent()) header('Location: /login');
            exit;
        }

        // CSRF check
        $token = $_POST['csrf_token'] ?? '';
        if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            $this->settings(null, 'Invalid request. Please try again.');
            return;
        }

        $userId  = (int)$_SESSION['user_id'];
        $form    = $_POST['form'] ?? '';

        if ($form === 'profile') {
            $firstName = trim(strip_tags($_POST['first_name'] ?? ''));
            $lastName  = trim(strip_tags($_POST['last_name']  ?? ''));
            $email     = trim($_POST['email']   ?? '');
            $phone     = trim(strip_tags($_POST['phone']   ?? ''));
            $country   = strtoupper(trim(strip_tags($_POST['country'] ?? '')));
            $gender    = trim($_POST['gender']  ?? '');
            $bio       = trim(strip_tags($_POST['bio'] ?? ''));

            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->settings(null, 'Please enter a valid email address.');
                return;
            }

            // Check email uniqueness (exclude self)
            try {
                $existing = $this->db->get('users', 'id', ['email' => $email, 'id[!]' => $userId]);
                if ($existing) {
                    $this->settings(null, 'That email address is already in use.');
                    return;
                }
            } catch (\Throwable $e) {}

            $allowedGenders = ['', 'male', 'female', 'other', 'prefer_not'];
            if (!in_array($gender, $allowedGenders, true)) $gender = '';
            if (strlen($bio) > 500) $bio = substr($bio, 0, 500);
            if ($country && !preg_match('/^[A-Z]{2,5}$/', $country)) $country = '';

            try {
                $this->db->update('users', [
                    'first_name' => $firstName ?: null,
                    'last_name'  => $lastName  ?: null,
                    'fullname'   => trim($firstName . ' ' . $lastName) ?: null,
                    'email'      => $email,
                    'phone'      => $phone ?: null,
                    'country'    => $country ?: null,
                    'gender'     => $gender ?: null,
                    'bio'        => $bio ?: null,
                ], ['id' => $userId]);
            } catch (\Throwable $e) {
                $this->settings(null, 'Could not save changes. Please try again.');
                return;
            }

            $this->settings('Profile updated successfully.');
            return;
        }

        if ($form === 'address') {
            $shipping = [
                'address_line1' => trim(strip_tags($_POST['ship_address_line1'] ?? '')),
                'address_line2' => trim(strip_tags($_POST['ship_address_line2'] ?? '')),
                'city'          => trim(strip_tags($_POST['ship_city']          ?? '')),
                'province'      => trim(strip_tags($_POST['ship_province']      ?? '')),
                'postal_code'   => trim(strip_tags($_POST['ship_postal_code']   ?? '')),
                'country'       => strtoupper(trim(strip_tags($_POST['ship_country'] ?? ''))) ?: 'PH',
            ];
            $home = [
                'address_line1' => trim(strip_tags($_POST['home_address_line1'] ?? '')),
                'address_line2' => trim(strip_tags($_POST['home_address_line2'] ?? '')),
                'city'          => trim(strip_tags($_POST['home_city']          ?? '')),
                'province'      => trim(strip_tags($_POST['home_province']      ?? '')),
                'postal_code'   => trim(strip_tags($_POST['home_postal_code']   ?? '')),
                'country'       => strtoupper(trim(strip_tags($_POST['home_country'] ?? ''))) ?: 'PH',
            ];
            try {
                $this->db->update('users', [
                    'shipping_address_json' => json_encode($shipping),
                    'home_address_json'     => json_encode($home),
                ], ['id' => $userId]);
            } catch (\Throwable $e) {
                $this->settings(null, 'Could not save addresses. Please try again.');
                return;
            }
            $this->settings('Addresses saved successfully.');
            return;
        }

        if ($form === 'password') {
            $current = $_POST['current_password'] ?? '';
            $new     = $_POST['new_password']     ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (strlen($new) < 8) {
                $this->settings(null, 'New password must be at least 8 characters.');
                return;
            }
            if ($new !== $confirm) {
                $this->settings(null, 'New passwords do not match.');
                return;
            }

            try {
                $hash = $this->db->get('users', 'password_hash', ['id' => $userId]);
            } catch (\Throwable $e) {
                $this->settings(null, 'Could not verify current password.');
                return;
            }

            if (!$hash || !password_verify($current, $hash)) {
                $this->settings(null, 'Current password is incorrect.');
                return;
            }

            try {
                $this->db->update('users', [
                    'password_hash' => password_hash($new, PASSWORD_BCRYPT)
                ], ['id' => $userId]);
            } catch (\Throwable $e) {
                $this->settings(null, 'Could not update password. Please try again.');
                return;
            }

            $this->settings('Password changed successfully.');
            return;
        }

        $this->settings(null, 'Unknown form action.');
    }

    /**
     * API: Default API key status (GET /api/account/default-key/status)
     */
    public function defaultApiKeyStatus(): void
    {
        header('Content-Type: application/json');
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        $userId = (int)$_SESSION['user_id'];
        try {
            $rows = $this->db->select('api_tokens', '*', [
                'user_id' => $userId,
                'name' => 'default_api',
                'revoked' => 0,
                'ORDER' => ['created_at' => 'DESC'],
                'LIMIT' => 1,
            ]);
            $row = (is_array($rows) && isset($rows[0]) && is_array($rows[0])) ? $rows[0] : null;

            $tokenPlain = null;
            if (is_array($row) && !empty($row['token_encrypted'])) {
                $tokenPlain = $this->decryptApiTokenEncrypted((string)$row['token_encrypted']);
            }
            echo json_encode([
                'success' => true,
                'has_key' => $row ? true : false,
                'created_at' => $row['created_at'] ?? null,
                'last_used_at' => $row['last_used_at'] ?? null,
                'token' => $tokenPlain,
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'DB error']);
        }
    }

    private function getAppKeyBytes(): ?string
    {
        $appKey = (string)(getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? ''));
        $appKey = trim($appKey);
        if ($appKey === '') {
            return null;
        }

        // If the key looks like 64 hex chars, treat it as raw 32 bytes.
        if (preg_match('/^[A-Fa-f0-9]{64}$/', $appKey)) {
            $bin = @hex2bin($appKey);
            return is_string($bin) && strlen($bin) === 32 ? $bin : null;
        }

        // Fallback: derive a 32-byte key from the string.
        return hash('sha256', $appKey, true);
    }

    private function encryptApiTokenPlain(string $plain): ?string
    {
        $key = $this->getAppKeyBytes();
        if (!$key) {
            return null;
        }
        if (!function_exists('openssl_encrypt')) {
            return null;
        }

        $iv = random_bytes(12); // GCM nonce
        $tag = '';
        $ciphertext = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false || !is_string($ciphertext) || $tag === '') {
            return null;
        }

        $payload = json_encode([
            'v' => 1,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'ct' => base64_encode($ciphertext),
        ]);
        if (!is_string($payload) || $payload === '') {
            return null;
        }
        return base64_encode($payload);
    }

    private function decryptApiTokenEncrypted(string $payloadB64): ?string
    {
        $key = $this->getAppKeyBytes();
        if (!$key) {
            return null;
        }
        if (!function_exists('openssl_decrypt')) {
            return null;
        }

        $json = base64_decode($payloadB64, true);
        if (!is_string($json) || $json === '') {
            return null;
        }
        $data = json_decode($json, true);
        if (!is_array($data) || (int)($data['v'] ?? 0) !== 1) {
            return null;
        }

        $iv = base64_decode((string)($data['iv'] ?? ''), true);
        $tag = base64_decode((string)($data['tag'] ?? ''), true);
        $ct = base64_decode((string)($data['ct'] ?? ''), true);
        if (!is_string($iv) || strlen($iv) !== 12 || !is_string($tag) || $tag === '' || !is_string($ct) || $ct === '') {
            return null;
        }

        $plain = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return ($plain === false || !is_string($plain) || $plain === '') ? null : $plain;
    }

    /**
     * API: Rotate default API key (POST /api/account/default-key/rotate)
     * Returns the token once.
     */
    public function rotateDefaultApiKey(): void
    {
        header('Content-Type: application/json');
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        $raw = json_decode((string)file_get_contents('php://input'), true);
        $input = is_array($raw) ? $raw : (is_array($_POST) ? $_POST : []);
        $csrf = (string)($input['csrf_token'] ?? '');
        $sessionCsrf = (string)($_SESSION['csrf_token'] ?? '');
        if ($csrf === '' || $sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $userId = (int)$_SESSION['user_id'];
        try {
            // Revoke existing default_api tokens
            $this->db->update('api_tokens', [
                'revoked' => 1,
                'revoked_at' => date('Y-m-d H:i:s'),
            ], [
                'user_id' => $userId,
                'name' => 'default_api',
                'revoked' => 0,
            ]);

            $plain = 'ginto-' . bin2hex(random_bytes(32));
            $hash = hash('sha256', $plain);

            $encrypted = $this->encryptApiTokenPlain($plain);
            if ($encrypted === null) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Server missing APP_KEY for key storage']);
                return;
            }

            $this->db->insert('api_tokens', [
                'user_id' => $userId,
                'name' => 'default_api',
                'token' => $hash,
                'token_encrypted' => $encrypted,
                'expires_at' => null,
                'revoked' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            echo json_encode(['success' => true, 'token' => $plain]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to rotate key']);
        }
    }

    public function accountApiKeysList(): void
    {
        header('Content-Type: application/json');
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        $userId = (int)$_SESSION['user_id'];
        try {
            $rows = $this->db->select('api_tokens', [
                'id',
                'name',
                'created_at',
                'last_used_at',
                'expires_at',
                'revoked',
                'revoked_at',
            ], [
                'user_id' => $userId,
                'ORDER' => ['id' => 'DESC'],
                'LIMIT' => 200,
            ]);

            echo json_encode([
                'success' => true,
                'keys' => is_array($rows) ? $rows : [],
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'DB error']);
        }
    }

    public function createAccountApiKey(): void
    {
        header('Content-Type: application/json');
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        $raw = json_decode((string)file_get_contents('php://input'), true);
        $input = is_array($raw) ? $raw : (is_array($_POST) ? $_POST : []);
        $csrf = (string)($input['csrf_token'] ?? '');
        $sessionCsrf = (string)($_SESSION['csrf_token'] ?? '');
        if ($csrf === '' || $sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $nameRaw = strip_tags((string)($input['name'] ?? ''));
        $name = trim(preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $nameRaw));
        if ($name === '') {
            $name = 'api_key_' . date('Ymd_His');
        }
        if (strlen($name) > 64) {
            $name = substr($name, 0, 64);
        }

        $userId = (int)$_SESSION['user_id'];
        try {
            $plain = 'ginto-' . bin2hex(random_bytes(32));
            $hash = hash('sha256', $plain);

            $encrypted = $this->encryptApiTokenPlain($plain);
            if ($encrypted === null) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Server missing APP_KEY for key storage']);
                return;
            }

            $this->db->insert('api_tokens', [
                'user_id' => $userId,
                'name' => $name,
                'token' => $hash,
                'token_encrypted' => $encrypted,
                'expires_at' => null,
                'revoked' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            echo json_encode([
                'success' => true,
                'token' => $plain,
                'name' => $name,
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to create key']);
        }
    }

    public function revokeAccountApiKey(): void
    {
        header('Content-Type: application/json');
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        $raw = json_decode((string)file_get_contents('php://input'), true);
        $input = is_array($raw) ? $raw : (is_array($_POST) ? $_POST : []);
        $csrf = (string)($input['csrf_token'] ?? '');
        $sessionCsrf = (string)($_SESSION['csrf_token'] ?? '');
        if ($csrf === '' || $sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid key id']);
            return;
        }

        $userId = (int)$_SESSION['user_id'];
        try {
            $row = $this->db->get('api_tokens', ['id', 'user_id', 'revoked'], ['id' => $id]);
            if (!is_array($row) || (int)($row['user_id'] ?? 0) !== $userId) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Key not found']);
                return;
            }

            if ((int)($row['revoked'] ?? 0) === 1) {
                echo json_encode(['success' => true]);
                return;
            }

            $this->db->update('api_tokens', [
                'revoked' => 1,
                'revoked_at' => date('Y-m-d H:i:s'),
            ], [
                'id' => $id,
                'user_id' => $userId,
            ]);

            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to revoke key']);
        }
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
        // Check if admin for expanded search capability
        $isAdmin = isset($_SESSION['role_id']) && in_array($_SESSION['role_id'], [1, 2]);
        \Ginto\Core\View::view('user/network-tree', [
            'title' => 'Network Tree',
            'user_data' => $user_data,
            'current_user_id' => $userId,
            'stats' => $stats,
            'isAdmin' => $isAdmin
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

    /**
     * Capture an authorized PayPal payment after successful registration.
     * POST /register/capture-payment
     */
    public function capturePaymentAction(): void
    {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate CSRF
        if (!isset($input['csrf_token']) || !validateCsrfToken($input['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            return;
        }
        
        $authorizationId = $input['authorization_id'] ?? null;
        $orderId = $input['order_id'] ?? null;
        $userId = $input['user_id'] ?? null;
        
        if (!$authorizationId || !$orderId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing authorization or order ID']);
            return;
        }
        
        try {
            // Get PayPal credentials
            $paypalEnv = $_ENV['PAYPAL_ENVIRONMENT'] ?? getenv('PAYPAL_ENVIRONMENT') ?? 'sandbox';
            $clientId = $paypalEnv === 'sandbox'
                ? ($_ENV['PAYPAL_CLIENT_ID_SANDBOX'] ?? getenv('PAYPAL_CLIENT_ID_SANDBOX'))
                : ($_ENV['PAYPAL_CLIENT_ID'] ?? getenv('PAYPAL_CLIENT_ID'));
            $clientSecret = $paypalEnv === 'sandbox'
                ? ($_ENV['PAYPAL_CLIENT_SECRET_SANDBOX'] ?? getenv('PAYPAL_CLIENT_SECRET_SANDBOX'))
                : ($_ENV['PAYPAL_CLIENT_SECRET'] ?? getenv('PAYPAL_CLIENT_SECRET'));
            $baseUrl = $paypalEnv === 'sandbox'
                ? 'https://api-m.sandbox.paypal.com'
                : 'https://api-m.paypal.com';
            
            error_log("PayPal capture: env=$paypalEnv, baseUrl=$baseUrl, clientId=" . substr($clientId ?? '', 0, 10) . "...");
            
            // Get access token
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v1/oauth2/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
            curl_setopt($ch, CURLOPT_USERPWD, $clientId . ':' . $clientSecret);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            
            $tokenResponse = curl_exec($ch);
            $tokenData = json_decode($tokenResponse, true);
            curl_close($ch);
            
            if (empty($tokenData['access_token'])) {
                error_log('PayPal capture: Failed to get access token');
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Payment gateway authentication failed']);
                return;
            }
            
            // Capture the authorized payment
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v2/payments/authorizations/' . $authorizationId . '/capture');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $tokenData['access_token']
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $captureResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $captureData = json_decode($captureResponse, true);
            $captureStatus = $captureData['status'] ?? null;
            
            // Handle both COMPLETED and PENDING as successful states
            // PENDING means buyer was charged but funds are held by PayPal (eCheck, verification, etc)
            if ($httpCode >= 200 && $httpCode < 300 && in_array($captureStatus, ['COMPLETED', 'PENDING'])) {
                $db = \Ginto\Core\Database::getInstance();
                
                // Determine pending reason if applicable
                $pendingReason = null;
                if ($captureStatus === 'PENDING') {
                    $pendingReason = $captureData['status_details']['reason'] ?? 'PENDING_REVIEW';
                    error_log('PayPal capture PENDING - reason: ' . $pendingReason . ' for authorization: ' . $authorizationId);
                }
                
                // Update order status
                if ($userId) {
                    $orderStatus = ($captureStatus === 'COMPLETED') ? 'completed' : 'pending';
                    $metadataUpdate = [
                        '$.capture_id' => $captureData['id'] ?? $authorizationId,
                        '$.captured_at' => date('Y-m-d H:i:s'),
                        '$.capture_status' => $captureStatus
                    ];
                    if ($pendingReason) {
                        $metadataUpdate['$.pending_reason'] = $pendingReason;
                    }
                    
                    $db->update('orders', [
                        'status' => $orderStatus,
                        'metadata' => $db->raw('JSON_SET(COALESCE(metadata, "{}"), "$.capture_id", :captureId, "$.captured_at", :capturedAt, "$.capture_status", :captureStatus' . ($pendingReason ? ', "$.pending_reason", :pendingReason' : '') . ')', array_filter([
                            ':captureId' => $captureData['id'] ?? $authorizationId,
                            ':capturedAt' => date('Y-m-d H:i:s'),
                            ':captureStatus' => $captureStatus,
                            ':pendingReason' => $pendingReason
                        ]))
                    ], [
                        'user_id' => (int)$userId,
                        'metadata[LIKE]' => '%' . $orderId . '%'
                    ]);
                    
                    // Update user's payment_status to reflect the capture result
                    $userPaymentStatus = ($captureStatus === 'COMPLETED') ? 'completed' : 'pending';
                    $db->update('users', [
                        'payment_status' => $userPaymentStatus
                    ], [
                        'id' => (int)$userId
                    ]);
                }
                
                error_log('PayPal capture ' . $captureStatus . ' for authorization: ' . $authorizationId);
                echo json_encode([
                    'success' => true, 
                    'capture_id' => $captureData['id'] ?? null,
                    'status' => $captureStatus,
                    'pending' => ($captureStatus === 'PENDING'),
                    'pending_reason' => $pendingReason
                ]);
            } else {
                error_log('PayPal capture failed: ' . $captureResponse);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Payment capture failed', 'details' => $captureData]);
            }
        } catch (\Exception $e) {
            error_log('PayPal capture exception: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Payment capture error']);
        }
    }

    public function registerAction(array $postData): void
    {
        // Wrap entire method in try-catch to capture any uncaught exceptions
        try {
        // Validate CSRF token
        if (!isset($postData['csrf_token']) || !validateCsrfToken($postData['csrf_token'])) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid CSRF token. Please refresh the page and try again.'
                ]);
                exit;
            }
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

        // Save individual name fields for DB - SANITIZE to prevent XSS
        $user_first = strip_tags(trim($first));
        $user_middle = strip_tags(trim($middle));
        $user_last = strip_tags(trim($last));
        $fullname = strip_tags(trim($fullname));
        
        // Sanitize username - only allow alphanumeric, underscore, hyphen
        $cleanUsername = preg_replace('/[^a-zA-Z0-9_\-]/', '', $postData['username']);
        if (empty($cleanUsername) || $cleanUsername !== $postData['username']) {
            // Username contains invalid characters
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Username can only contain letters, numbers, underscore and hyphen.'
                ]);
                exit;
            } else {
                $this->view('user/register/register', [
                    'title' => 'Register',
                    'error' => 'Username can only contain letters, numbers, underscore and hyphen.',
                    'old' => $postData,
                    'countries' => $this->countries
                ]);
                return;
            }
        }

        // Registration attempt — avoid noisy debug logging in production

        $userData = [
            'fullname' => $fullname,
            'firstname' => $user_first,
            'middlename' => $user_middle,
            'lastname' => $user_last,
            'username' => $cleanUsername,
            'email' => filter_var($postData['email'], FILTER_SANITIZE_EMAIL),
            'password' => $postData['password'],
            'referrer_id' => $referrerId,
            'country' => strip_tags(trim($postData['country'])),
            'phone' => preg_replace('/[^0-9+\-\s]/', '', $postData['phone'])
        ];

        // Include package selection and payment metadata if provided by the UI
        $userData['package'] = isset($postData['package']) ? strip_tags($postData['package']) : (isset($postData['package_name']) ? strip_tags($postData['package_name']) : null);
        $userData['package_amount'] = isset($postData['package_amount']) ? floatval($postData['package_amount']) : (isset($postData['amount']) ? floatval($postData['amount']) : null);
        $userData['package_currency'] = isset($postData['package_currency']) ? preg_replace('/[^A-Z]/', '', strtoupper($postData['package_currency'])) : (isset($postData['currency']) ? preg_replace('/[^A-Z]/', '', strtoupper($postData['currency'])) : 'PHP');
        $userData['pay_method'] = isset($postData['pay_method']) ? strip_tags($postData['pay_method']) : (isset($postData['payment_method']) ? strip_tags($postData['payment_method']) : null);

        // Block PayMongo QRPH from using the generic /register endpoint.
        // PayMongo payments must go through /paymongo-payments which verifies
        // the payment intent status before creating the account.
        if (in_array($userData['pay_method'], ['paymongo_qrph', 'paymongo'], true)) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'PayMongo payment must be completed via the QR code flow. Please scan the QR code and wait for confirmation.',
            ]);
            exit;
        }

        // PayPal payment info
        if (!empty($postData['paypal_order_id'])) {
            $userData['paypal_order_id'] = preg_replace('/[^a-zA-Z0-9]/', '', $postData['paypal_order_id']);
        }
        if (!empty($postData['paypal_payment_status'])) {
            $userData['paypal_payment_status'] = strip_tags($postData['paypal_payment_status']);
        }

        $newUserId = $this->userModel->register($userData);
        
        // DEBUG: Log registration result
        file_put_contents('/tmp/ginto-register-debug.log', date('Y-m-d H:i:s') . " - newUserId: " . var_export($newUserId, true) . " - userData: " . json_encode($userData) . "\n", FILE_APPEND);

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

            // Auto-login the new user and redirect to intended destination (e.g. checkout)
            if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
            $_SESSION['user_id']              = $newUser['id'];
            $_SESSION['username']             = $newUser['username'];
            $_SESSION['fullname']             = $newUser['fullname'] ?? '';
            $_SESSION['user']                 = $newUser['email'] ?? $newUser['username'] ?? '';
            $_SESSION['user_email']           = $newUser['email'] ?? null;
            $_SESSION['user_full_name']       = $newUser['fullname'] ?? 'User';
            $_SESSION['user_username']        = $newUser['username'] ?? '';
            $_SESSION['user_profile_picture'] = $newUser['avatar'] ?? $newUser['profile_picture'] ?? null;
            $_SESSION['role_id']              = $newUser['role_id'] ?? 5;
            $_SESSION['role']                 = 'user';
            try { $this->db->update('users', ['last_login' => date('Y-m-d H:i:s')], ['id' => $newUser['id']]); } catch (\Throwable $__e) {}
            $redirect = '/chat';
            if (!empty($_SESSION['login_redirect'])) {
                $redirect = $_SESSION['login_redirect'];
                unset($_SESSION['login_redirect']);
            }
            header('Location: ' . $redirect);
            exit;
        } else {
            // Regular form error handling
            $this->view('user/register/register', [
                'title' => 'Register',
                'error' => 'A database error occurred during registration.',
                'old' => $postData,
                'countries' => $this->countries
            ]);
        }
        } catch (\Throwable $e) {
            // Log the full exception for debugging
            error_log('registerAction EXCEPTION: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            error_log('Stack trace: ' . $e->getTraceAsString());
            file_put_contents('/tmp/ginto-register-error.log', date('Y-m-d H:i:s') . ' - ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
            
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'An unexpected error occurred. Please try again.',
                    'debug' => $e->getMessage()
                ]);
                exit;
            }
            
            $this->view('user/register/register', [
                'title' => 'Register',
                'error' => 'An unexpected error occurred. Please try again.',
                'old' => $postData ?? [],
                'countries' => $this->countries
            ]);
        }
    }

    public function loginAction(array $postData): void
    {
        $isAjax = !empty($postData['ajax']);
        
        // Validate CSRF token
        if (!isset($postData['csrf_token']) || !validateCsrfToken($postData['csrf_token'])) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token. Please refresh the page.']);
                return;
            }
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
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'All fields are required.']);
                return;
            }
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
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid credentials.']);
                return;
            }
            $this->view('user/login', [
                'title' => 'Login',
                'error' => 'Invalid credentials.',
                'old' => $postData,
                'csrf_token' => generateCsrfToken()
            ]);
            return;
        }

        // Ensure session started
        if (session_status() !== PHP_SESSION_ACTIVE) {@session_start();}

        // Set session data (include role_id and readable role name to avoid extra DB lookups later)
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['fullname'] = $user['fullname'];

        // Additional compatibility session keys expected by other parts (saicms style)
        $_SESSION['user'] = $user['email'] ?? $user['username'] ?? $_SESSION['username'];
        $_SESSION['user_email'] = $user['email'] ?? ($_SESSION['user_email'] ?? null);
        $_SESSION['user_full_name'] = $user['fullname'] ?? ($user['full_name'] ?? ($_SESSION['fullname'] ?? 'User'));
        $_SESSION['user_username'] = $user['username'] ?? ($_SESSION['username'] ?? '');
        // Common avatar column names: 'avatar', 'profile_picture', 'photo'
        $_SESSION['user_profile_picture'] = $user['avatar'] ?? $user['profile_picture'] ?? $user['photo'] ?? ($_SESSION['user_profile_picture'] ?? null);
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

        // Update last_login timestamp
        try {
            $this->db->update('users', ['last_login' => date('Y-m-d H:i:s')], ['id' => $user['id']]);
        } catch (\Throwable $_e) {
            // Ignore if update fails
        }

        // Handle AJAX response
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'fullname' => $user['fullname']
                ]
            ]);
            return;
        }

        // Redirect to intended page if set, otherwise default to chat
        $redirect = '/chat';
        if (!empty($_SESSION['login_redirect'])) {
            $redirect = $_SESSION['login_redirect'];
            unset($_SESSION['login_redirect']);
        }
        header('Location: ' . $redirect);
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

    /**
     * POST /api/account/change-password
     * Allows a logged-in user to change their password.
     */
    public function changePassword(): void
    {
        header('Content-Type: application/json');
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Not authenticated']);
            return;
        }

        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?? [];

        // CSRF check
        $csrfOk = isset($input['csrf_token']) && hash_equals(
            $_SESSION['csrf_token'] ?? '', (string)$input['csrf_token']
        );
        if (!$csrfOk) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Security token mismatch']);
            return;
        }

        $currentPassword = $input['current_password'] ?? '';
        $newPassword     = $input['new_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword)) {
            echo json_encode(['success' => false, 'error' => 'Both current and new passwords are required']);
            return;
        }

        if (strlen($newPassword) < 8) {
            echo json_encode(['success' => false, 'error' => 'New password must be at least 8 characters']);
            return;
        }

        $userId = (int) $_SESSION['user_id'];
        $user   = $this->db->get('users', ['id', 'password_hash'], ['id' => $userId]);

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
            return;
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->db->update('users', ['password_hash' => $newHash], ['id' => $userId]);

        echo json_encode(['success' => true]);
    }

    /**
     * GET /account/delete — show confirmation page
     */
    public function deleteAccount(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        if (empty($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $user = $this->db->get('users', ['id', 'username', 'email'], ['id' => $userId]);

        \Ginto\Core\View::view('user/delete-account', [
            'title'      => 'Delete Account | Ginto',
            'user'       => is_array($user) ? $user : ['id' => $userId],
            'csrf_token' => generateCsrfToken(),
        ]);
    }

    /**
     * POST /account/delete/confirm — actually delete after CSRF + password check
     */
    public function deleteAccountConfirm(): void
    {
        header('Content-Type: application/json');
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Not authenticated']);
            return;
        }

        // CSRF
        $token = $_POST['csrf_token'] ?? '';
        if (!function_exists('validateCsrfToken') || !validateCsrfToken($token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Security token mismatch']);
            return;
        }

        // Password confirmation
        $password = $_POST['password'] ?? '';
        if ($password === '') {
            echo json_encode(['success' => false, 'error' => 'Password is required to confirm deletion']);
            return;
        }

        $userId = (int)$_SESSION['user_id'];
        $user = $this->db->get('users', ['id', 'password_hash', 'referrer_id'], ['id' => $userId]);

        if (!$user || !password_verify($password, (string)($user['password_hash'] ?? ''))) {
            echo json_encode(['success' => false, 'error' => 'Incorrect password']);
            return;
        }

        // Re-parent all direct downlines to this user's own upline before deletion.
        // e.g. deleted user was: upline → deleted → [A, B, C]
        // becomes:               upline → [A, B, C]
        $uplineId = isset($user['referrer_id']) && $user['referrer_id'] ? (int)$user['referrer_id'] : null;

        try {
            // 1. Re-point direct downline users to the upline (or NULL if top-level)
            if ($uplineId !== null) {
                $this->db->update('users', ['referrer_id' => $uplineId], ['referrer_id' => $userId]);
            } else {
                $this->db->update('users', ['referrer_id' => null], ['referrer_id' => $userId]);
            }

            // 2. Update the referrals table to reflect the new sponsor relationship
            if ($uplineId !== null) {
                $this->db->update('referrals', ['referrer_id' => $uplineId], ['referrer_id' => $userId]);
            } else {
                // No upline — remove orphaned referral rows rather than leaving broken refs
                $this->db->delete('referrals', ['referrer_id' => $userId]);
            }

            // 3. Remove the deleted user's own referral record (they were someone's downline)
            $this->db->delete('referrals', ['referred_id' => $userId]);
        } catch (\Throwable $e) {
            error_log('deleteAccount: re-parent downlines failed for user ' . $userId . ': ' . $e->getMessage());
            // Non-fatal — continue with deletion
        }

        // Soft-delete: mark as deleted, anonymise PII, revoke sessions
        try {
            $anon = 'deleted_' . bin2hex(random_bytes(8));
            $this->db->update('users', [
                'username'      => $anon,
                'email'         => $anon . '@deleted.invalid',
                'password_hash' => '',
                'fullname'      => '',
                'phone'         => '',
                'deleted_at'    => date('Y-m-d H:i:s'),
            ], ['id' => $userId]);
        } catch (\Throwable $e) {
            // deleted_at column may not exist — fall back to hard delete
            try {
                $this->db->delete('users', ['id' => $userId]);
            } catch (\Throwable $e2) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Could not delete account']);
                return;
            }
        }

        // Destroy session
        $_SESSION = [];
        session_destroy();

        echo json_encode(['success' => true]);
    }
}
?>
