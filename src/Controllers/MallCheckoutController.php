<?php
namespace Ginto\Controllers;

use Core\Controller;
use Ginto\Core\Database;
use Ginto\Services\MallCommerceService;

class MallCheckoutController extends Controller
{
    private $db;
    private MallCommerceService $commerce;

    public function __construct($db = null)
    {
        parent::__construct();
        $this->db = $db ?? Database::getInstance();
        $this->commerce = new MallCommerceService($this->db);
    }

    public function storefront($slug = null)
    {
        $slug = trim((string)$slug);
        if ($slug === '') {
            http_response_code(404);
            echo '<h1>Storefront not found</h1>';
            return;
        }

        $data = $this->commerce->getStorefrontProducts($slug);
        if (empty($data['storefront'])) {
            http_response_code(404);
            echo '<h1>Storefront not found</h1>';
            return;
        }

        $viewerId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $this->commerce->recordStorefrontImpression((int)($data['storefront']['user_id'] ?? 0), $viewerId, 'viewed');

        // Remember the store owner so buyer registration can assign them as upline
        $sellerId = (int)($data['storefront']['user_id'] ?? 0);
        if ($sellerId > 0) {
            if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
            $_SESSION['mall_checkout_seller_id'] = $sellerId;
        }

        $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        $walletSummary = $userId > 0 ? $this->commerce->getWalletSummary($userId) : ['account' => []];

        $paypalEnv = strtolower((string)($_ENV['PAYPAL_ENVIRONMENT'] ?? getenv('PAYPAL_ENVIRONMENT') ?? 'sandbox'));
        $paypalClientId = $paypalEnv === 'sandbox'
            ? (string)($_ENV['PAYPAL_CLIENT_ID_SANDBOX'] ?? getenv('PAYPAL_CLIENT_ID_SANDBOX') ?? '')
            : (string)($_ENV['PAYPAL_CLIENT_ID'] ?? getenv('PAYPAL_CLIENT_ID') ?? '');

        $this->view('mall/storefront', [
            'title' => ($data['storefront']['display_name'] ?? 'Storefront') . ' - Ginto Mall',
            'categories' => $this->db->select('categories', '*', ['ORDER' => ['name' => 'ASC']]) ?: [],
            'products' => $data['products'],
            'storefront' => $data['storefront'],
            'csrf_token' => generateCsrfToken(),
            'mall_unread_notifications' => $userId > 0 ? $this->commerce->getMallUnreadNotificationCount($userId) : 0,
            'mall_notifications' => $userId > 0 ? $this->commerce->getMallNotifications($userId) : [],
            'mall_wallet_balance' => (float)($walletSummary['account']['balance'] ?? 0),
            'paypal_client_id' => $paypalClientId,
        ]);
    }

    public function checkoutPage()
    {
        $userId = $this->requireUser();
        $walletSummary = $this->commerce->getWalletSummary($userId);
        $paypalEnv = strtolower((string)($_ENV['PAYPAL_ENVIRONMENT'] ?? getenv('PAYPAL_ENVIRONMENT') ?? 'sandbox'));
        $paypalClientId = $paypalEnv === 'sandbox'
            ? (string)($_ENV['PAYPAL_CLIENT_ID_SANDBOX'] ?? getenv('PAYPAL_CLIENT_ID_SANDBOX') ?? '')
            : (string)($_ENV['PAYPAL_CLIENT_ID'] ?? getenv('PAYPAL_CLIENT_ID') ?? '');

        // Load user's saved addresses for pre-filling checkout forms
        $savedShipping = [];
        $savedHome = [];
        try {
            $userRow = $this->db->get('users', [
                'shipping_address_json', 'home_address_json',
                'first_name', 'last_name', 'fullname', 'phone',
            ], ['id' => $userId]);
            if ($userRow) {
                $savedShipping = json_decode((string)($userRow['shipping_address_json'] ?? 'null'), true) ?: [];
                $savedHome     = json_decode((string)($userRow['home_address_json']     ?? 'null'), true) ?: [];
                // Supplement from profile fields if not stored in address
                if (empty($savedShipping['full_name'])) {
                    $savedShipping['full_name'] = trim(
                        ($userRow['first_name'] ?? '') . ' ' . ($userRow['last_name'] ?? '')
                    ) ?: ($userRow['fullname'] ?? '');
                }
                if (empty($savedShipping['phone'])) {
                    $savedShipping['phone'] = $userRow['phone'] ?? '';
                }
            }
        } catch (\Throwable $e) {}

        $this->view('mall/checkout', [
            'title' => 'Checkout - Ginto Mall',
            'csrf_token' => generateCsrfToken(),
            'wallet' => $walletSummary['account'],
            'mall_wallet_balance' => (float)($walletSummary['account']['balance'] ?? 0),
            'mall_unread_notifications' => $this->commerce->getMallUnreadNotificationCount($userId),
            'mall_notifications' => $this->commerce->getMallNotifications($userId),
            'paypal_client_id' => $paypalClientId,
            'saved_shipping' => $savedShipping,
            'saved_home' => $savedHome,
        ]);
    }

    public function walletPage()
    {
        $userId = $this->requireUser();
        $walletSummary = $this->commerce->getWalletSummary($userId);
        $this->view('mall/wallet', [
            'title' => 'Ginto Wallet',
            'csrf_token' => generateCsrfToken(),
            'wallet' => $walletSummary['account'],
            'mall_wallet_balance' => (float)($walletSummary['account']['balance'] ?? 0),
            'wallet_transactions' => $walletSummary['transactions'],
            'mall_unread_notifications' => $this->commerce->getMallUnreadNotificationCount($userId),
            'mall_notifications' => $this->commerce->getMallNotifications($userId),
            'seller_stats' => $this->commerce->getSellerStats($userId),
            'payout_account' => $this->commerce->getPayoutAccount($userId),
        ]);
    }

    public function savePayoutAccount()
    {
        $userId = $this->requireUserJson();
        $this->requirePostJson();
        $input = $this->jsonInput();
        $this->validateCsrfFromPayload($input);

        $accountType     = trim((string)($input['account_type'] ?? 'bank'));
        $institutionName = trim((string)($input['institution_name'] ?? ''));
        $holderName      = trim((string)($input['account_holder_name'] ?? ''));
        $accountNumber   = trim((string)($input['account_number'] ?? ''));

        if ($institutionName === '' || $holderName === '' || $accountNumber === '') {
            $this->json(['success' => false, 'message' => 'All fields are required.']);
            return;
        }

        try {
            $result = $this->commerce->savePayoutAccount($userId, $accountType, $institutionName, $holderName, $accountNumber);
            $this->json(['success' => true, 'id' => $result['id']]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function walletSalesPage()
    {
        $userId = $this->requireUser();
        $walletSummary = $this->commerce->getWalletSummary($userId);
        $this->view('mall/wallet_sales', [
            'title' => 'My Sales',
            'csrf_token' => generateCsrfToken(),
            'sales' => $this->commerce->getSalesList($userId),
            'seller_stats' => $this->commerce->getSellerStats($userId),
            'mall_wallet_balance' => (float)($walletSummary['account']['balance'] ?? 0),
            'mall_unread_notifications' => $this->commerce->getMallUnreadNotificationCount($userId),
            'mall_notifications' => $this->commerce->getMallNotifications($userId),
        ]);
    }

    public function walletCommissionsPage()
    {
        $userId = $this->requireUser();
        $walletSummary = $this->commerce->getWalletSummary($userId);
        $this->view('mall/wallet_commissions', [
            'title' => 'My Commissions',
            'csrf_token' => generateCsrfToken(),
            'commissions' => $this->commerce->getCommissionsList($userId),
            'seller_stats' => $this->commerce->getSellerStats($userId),
            'mall_wallet_balance' => (float)($walletSummary['account']['balance'] ?? 0),
            'mall_unread_notifications' => $this->commerce->getMallUnreadNotificationCount($userId),
            'mall_notifications' => $this->commerce->getMallNotifications($userId),
        ]);
    }

    public function walletEarningsPage()
    {
        $userId = $this->requireUser();
        $walletSummary = $this->commerce->getWalletSummary($userId);
        $this->view('mall/wallet_earnings', [
            'title' => 'My Earnings',
            'csrf_token' => generateCsrfToken(),
            'seller_stats' => $this->commerce->getSellerStats($userId),
            'wallet' => $walletSummary['account'],
            'mall_wallet_balance' => (float)($walletSummary['account']['balance'] ?? 0),
            'mall_unread_notifications' => $this->commerce->getMallUnreadNotificationCount($userId),
            'mall_notifications' => $this->commerce->getMallNotifications($userId),
        ]);
    }

    public function walletPayoutsPage()
    {
        $userId = $this->requireUser();
        $walletSummary = $this->commerce->getWalletSummary($userId);
        $this->view('mall/wallet_payouts', [
            'title' => 'Pending Payouts',
            'csrf_token' => generateCsrfToken(),
            'payouts' => $this->commerce->getPendingPayoutsList($userId),
            'seller_stats' => $this->commerce->getSellerStats($userId),
            'mall_wallet_balance' => (float)($walletSummary['account']['balance'] ?? 0),
            'mall_unread_notifications' => $this->commerce->getMallUnreadNotificationCount($userId),
            'mall_notifications' => $this->commerce->getMallNotifications($userId),
        ]);
    }

    public function walletPayoutAccountsPage()
    {
        $userId = $this->requireUser();
        $walletSummary = $this->commerce->getWalletSummary($userId);
        $this->view('mall/wallet_payout_accounts', [
            'title' => 'Payout Accounts',
            'csrf_token' => generateCsrfToken(),
            'payout_accounts' => $this->commerce->getAllPayoutAccounts($userId),
            'mall_wallet_balance' => (float)($walletSummary['account']['balance'] ?? 0),
            'mall_unread_notifications' => $this->commerce->getMallUnreadNotificationCount($userId),
            'mall_notifications' => $this->commerce->getMallNotifications($userId),
        ]);
    }

    public function setDefaultPayoutAccount()
    {
        $userId = $this->requireUserJson();
        $this->requirePostJson();
        $input = $this->jsonInput();
        $this->validateCsrfFromPayload($input);
        $accountId = (int)($input['account_id'] ?? 0);
        if ($accountId <= 0) { $this->json(['success' => false, 'message' => 'Invalid account.']); return; }
        $this->commerce->setPrimaryPayoutAccount($userId, $accountId);
        $this->json(['success' => true]);
    }

    public function deletePayoutAccount()
    {
        $userId = $this->requireUserJson();
        $this->requirePostJson();
        $input = $this->jsonInput();
        $this->validateCsrfFromPayload($input);
        $accountId = (int)($input['account_id'] ?? 0);
        if ($accountId <= 0) { $this->json(['success' => false, 'message' => 'Invalid account.']); return; }
        $this->commerce->deletePayoutAccount($userId, $accountId);
        $this->json(['success' => true]);
    }

    public function buyerOrdersPage()
    {
        $userId = $this->requireUser();
        $this->commerce->purgeUnpaidOrdersForUser($userId, 'buyer');
        $walletSummary = $this->commerce->getWalletSummary($userId);
        $this->view('mall/orders', [
            'title' => 'My Mall Orders',
            'csrf_token' => generateCsrfToken(),
            'orders' => $this->commerce->listBuyerOrders($userId),
            'page_kind' => 'buyer',
            'mall_unread_notifications' => $this->commerce->getMallUnreadNotificationCount($userId),
            'mall_notifications' => $this->commerce->getMallNotifications($userId),
            'mall_wallet_balance' => (float)($walletSummary['account']['balance'] ?? 0),
        ]);
    }

    public function sellerOrdersPage()
    {
        $userId = $this->requireUser();
        $this->commerce->purgeUnpaidOrdersForUser($userId, 'seller');
        $walletSummary = $this->commerce->getWalletSummary($userId);
        $this->view('mall/orders', [
            'title' => 'Seller Orders',
            'csrf_token' => generateCsrfToken(),
            'orders' => $this->commerce->listSellerOrders($userId),
            'page_kind' => 'seller',
            'mall_unread_notifications' => $this->commerce->getMallUnreadNotificationCount($userId),
            'mall_notifications' => $this->commerce->getMallNotifications($userId),
            'mall_wallet_balance' => (float)($walletSummary['account']['balance'] ?? 0),
        ]);
    }

    public function deliveryOrdersPage()
    {
        $userId = $this->requireUser();
        if (!$this->commerce->isDeliveryAccount($userId) && (!defined('IS_ADMIN') || !IS_ADMIN)) {
            http_response_code(403);
            echo 'Delivery account required.';
            return;
        }
        $walletSummary = $this->commerce->getWalletSummary($userId);
        $this->view('mall/orders', [
            'title' => 'Delivery Dashboard',
            'csrf_token' => generateCsrfToken(),
            'orders' => $this->commerce->listDeliveryOrders($userId),
            'page_kind' => 'delivery',
            'mall_unread_notifications' => $this->commerce->getMallUnreadNotificationCount($userId),
            'mall_notifications' => $this->commerce->getMallNotifications($userId),
            'mall_wallet_balance' => (float)($walletSummary['account']['balance'] ?? 0),
        ]);
    }

    public function notificationsApi()
    {
        $userId = $this->requireUserJson();
        $this->json([
            'success' => true,
            'count' => $this->commerce->getMallUnreadNotificationCount($userId),
            'notifications' => $this->commerce->getMallNotifications($userId),
        ]);
    }

    public function notificationsMarkRead()
    {
        $userId = $this->requireUserJson();
        $this->requirePostJson();
        $this->validateCsrfFromJson();
        $this->commerce->markMallNotificationsRead($userId);
        $this->json(['success' => true]);
    }

    public function checkoutCreate()
    {
        $userId = $this->requireUserJson();
        $this->requirePostJson();
        $input = $this->jsonInput();
        $this->validateCsrfFromPayload($input);

        $paymentMethod = (string)($input['payment_method'] ?? 'wallet');
        $cart = is_array($input['cart'] ?? null) ? $input['cart'] : [];
        $shipping = is_array($input['shipping'] ?? null) ? $input['shipping'] : [];

        try {
            $result = $this->commerce->createCheckoutSession($userId, $cart, $shipping, $paymentMethod);
            $this->json(['success' => true] + $result);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 422);
        }
    }

    public function checkoutPaymongoQrInit()
    {
        $userId = $this->requireUserJson();
        $this->requirePostJson();
        $input = $this->jsonInput();
        $this->validateCsrfFromPayload($input);

        try {
            $result = $this->commerce->initializePayMongoQrCheckout((string)($input['session_ref'] ?? ''), $userId);
            $this->json($result);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 422);
        }
    }

    public function checkoutPaymongoCardInit()
    {
        $userId = $this->requireUserJson();
        $this->requirePostJson();
        $input = $this->jsonInput();
        $this->validateCsrfFromPayload($input);

        try {
            $result = $this->commerce->initializePayMongoCardCheckout(
                (string)($input['session_ref'] ?? ''),
                $userId,
                is_array($input['card'] ?? null) ? $input['card'] : [],
                is_array($input['billing'] ?? null) ? $input['billing'] : []
            );
            $this->json($result);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 422);
        }
    }

    public function checkoutPayPalOrder()
    {
        $userId = $this->requireUserJson();
        $this->requirePostJson();
        $input = $this->jsonInput();
        $this->validateCsrfFromPayload($input);

        try {
            $result = $this->commerce->initializePayPalCheckout((string)($input['session_ref'] ?? ''), $userId);
            $this->json($result);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 422);
        }
    }

    public function checkoutPayPalCapture()
    {
        $userId = $this->requireUserJson();
        $this->requirePostJson();
        $input = $this->jsonInput();
        $this->validateCsrfFromPayload($input);

        try {
            $result = $this->commerce->capturePayPalCheckout((string)($input['session_ref'] ?? ''), $userId, (string)($input['paypal_order_id'] ?? ''));
            $this->json($result);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 422);
        }
    }

    public function checkoutStatus()
    {
        $userId = $this->requireUserJson();
        $sessionRef = trim((string)($_GET['session_ref'] ?? ''));
        if ($sessionRef === '') {
            $this->jsonError('Missing session_ref.', 400);
            return;
        }
        try {
            $result = $this->commerce->getPayMongoSessionStatus($sessionRef, $userId);
            $this->json($result);
        } catch (\Throwable $e) {
            $session = $this->commerce->getPaymentSession($sessionRef, $userId);
            if ($session) {
                $this->json(['success' => true, 'status' => $session['status']]);
                return;
            }
            $this->jsonError($e->getMessage(), 404);
        }
    }

    public function walletTopupCreate()
    {
        $userId = $this->requireUserJson();
        $this->requirePostJson();
        $input = $this->jsonInput();
        $this->validateCsrfFromPayload($input);

        $amount = round((float)($input['amount'] ?? 0), 2);
        $paymentMethod = (string)($input['payment_method'] ?? 'ginto_pay_qr');
        try {
            $result = $this->commerce->createWalletTopupSession($userId, $amount, $paymentMethod);
            $this->json(['success' => true] + $result);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 422);
        }
    }

    public function deliveryClaim()
    {
        $userId = $this->requireUserJson();
        $this->requirePostJson();
        $input = $this->jsonInput();
        $this->validateCsrfFromPayload($input);
        if (!$this->commerce->isDeliveryAccount($userId) && (!defined('IS_ADMIN') || !IS_ADMIN)) {
            $this->jsonError('Delivery account required.', 403);
            return;
        }

        try {
            $this->commerce->claimDeliveryOrder((int)($input['order_id'] ?? 0), $userId);
            $this->json(['success' => true]);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 422);
        }
    }

    public function orderStatusUpdate()
    {
        $userId = $this->requireUserJson();
        $this->requirePostJson();
        $input = $this->jsonInput();
        $this->validateCsrfFromPayload($input);

        try {
            $orderId = (int)($input['order_id'] ?? 0);
            $status = trim((string)($input['status'] ?? ''));
            $message = trim(strip_tags((string)($input['message'] ?? '')));
            $role = trim((string)($input['actor_type'] ?? 'seller'));
            $this->commerce->updateOrderStatus($orderId, $userId, $role, $status, $message);
            $this->json(['success' => true]);
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 422);
        }
    }

    private function requireUser(): int
    {
        if (empty($_SESSION['user_id'])) {
            if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
            $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'] ?? '/mall/checkout';
            // Capture the seller id passed via URL so buyer registration uses the correct upline
            $refSeller = (int)($_GET['ref_seller'] ?? 0);
            if ($refSeller > 0) {
                $_SESSION['mall_checkout_seller_id'] = $refSeller;
            }
            header('Location: /mall/buyer-register');
            exit;
        }
        return (int)$_SESSION['user_id'];
    }

    public function buyerRegisterPage(): void
    {
        if (!empty($_SESSION['user_id'])) {
            header('Location: /mall/checkout');
            exit;
        }
        $this->view('mall/buyer_register', [
            'title'       => 'Create Buyer Account - Ginto Mall',
            'csrf_token'  => generateCsrfToken(),
            'error'       => $_SESSION['buyer_reg_error'] ?? null,
            'old'         => $_SESSION['buyer_reg_old'] ?? [],
        ]);
        unset($_SESSION['buyer_reg_error'], $_SESSION['buyer_reg_old']);
    }

    public function buyerRegisterAction(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /mall/buyer-register');
            exit;
        }

        $post = $_POST;

        // CSRF
        if (empty($post['csrf_token']) || !validateCsrfToken($post['csrf_token'])) {
            $_SESSION['buyer_reg_error'] = 'Invalid request. Please try again.';
            $_SESSION['buyer_reg_old']   = $post;
            header('Location: /mall/buyer-register');
            exit;
        }

        // Required fields
        $fullname = strip_tags(trim($post['fullname'] ?? ''));
        $email    = filter_var(trim($post['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $phone    = preg_replace('/[^0-9+\-\s]/', '', $post['phone'] ?? '');
        $password = $post['password'] ?? '';

        if (!$fullname || !$email || !$phone || !$password) {
            $_SESSION['buyer_reg_error'] = 'All fields are required.';
            $_SESSION['buyer_reg_old']   = $post;
            header('Location: /mall/buyer-register');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['buyer_reg_error'] = 'Please enter a valid email address.';
            $_SESSION['buyer_reg_old']   = $post;
            header('Location: /mall/buyer-register');
            exit;
        }

        if (strlen($password) < 6) {
            $_SESSION['buyer_reg_error'] = 'Password must be at least 6 characters.';
            $_SESSION['buyer_reg_old']   = $post;
            header('Location: /mall/buyer-register');
            exit;
        }

        // Duplicate check
        $existingEmail = $this->db->get('users', 'id', ['email' => $email]);
        if ($existingEmail) {
            $_SESSION['buyer_reg_error'] = 'An account with this email already exists. <a href="/login" style="color:var(--accent)">Login instead?</a>';
            $_SESSION['buyer_reg_old']   = $post;
            header('Location: /mall/buyer-register');
            exit;
        }

        // Generate a username from email prefix + random suffix
        $baseUsername = preg_replace('/[^a-zA-Z0-9]/', '', strtolower(explode('@', $email)[0]));
        if (empty($baseUsername)) { $baseUsername = 'buyer'; }
        $username = $baseUsername;
        $attempts = 0;
        while ($this->db->get('users', 'id', ['username' => $username]) && $attempts < 10) {
            $username = $baseUsername . rand(100, 9999);
            $attempts++;
        }

        // Upline = store owner they visited, fallback to user id 2
        $referrerId = (int)($_SESSION['mall_checkout_seller_id'] ?? 2);
        if (!$this->db->get('users', 'id', ['id' => $referrerId])) {
            $referrerId = 2;
        }

        $nameParts = explode(' ', $fullname, 2);
        $firstName = $nameParts[0];
        $lastName  = $nameParts[1] ?? '';

        $userModel = new \Ginto\Models\User($this->db);
        $newUserId = $userModel->register([
            'fullname'    => $fullname,
            'firstname'   => $firstName,
            'lastname'    => $lastName,
            'username'    => $username,
            'email'       => $email,
            'phone'       => $phone,
            'password'    => $password,
            'referrer_id' => $referrerId,
            'country'     => '',
            'is_buyer'    => 1,
            'role_id'     => 5,
            'package'     => 'Starter',
        ]);

        if (!$newUserId) {
            $_SESSION['buyer_reg_error'] = 'Registration failed. Please try again.';
            $_SESSION['buyer_reg_old']   = $post;
            header('Location: /mall/buyer-register');
            exit;
        }

        $newUser = $userModel->find($newUserId);

        // Auto-login
        $_SESSION['user_id']              = $newUser['id'];
        $_SESSION['username']             = $newUser['username'];
        $_SESSION['fullname']             = $newUser['fullname'] ?? '';
        $_SESSION['user']                 = $newUser['email'] ?? $newUser['username'] ?? '';
        $_SESSION['user_email']           = $newUser['email'] ?? null;
        $_SESSION['user_full_name']       = $newUser['fullname'] ?? 'Buyer';
        $_SESSION['user_username']        = $newUser['username'] ?? '';
        $_SESSION['user_profile_picture'] = null;
        $_SESSION['role_id']              = $newUser['role_id'] ?? 5;
        $_SESSION['role']                 = 'user';
        try { $this->db->update('users', ['last_login' => date('Y-m-d H:i:s')], ['id' => $newUser['id']]); } catch (\Throwable $__e) {}

        // Push notification to the first-product seller about new registration
        try {
            $push = new \Ginto\Services\MallPushService($this->db);
            $push->notifySellerVisitorRegistered($referrerId, $fullname);
        } catch (\Throwable $__e) {}

        $redirect = '/mall/checkout';
        if (!empty($_SESSION['login_redirect'])) {
            $redirect = $_SESSION['login_redirect'];
            unset($_SESSION['login_redirect']);
        }
        header('Location: ' . $redirect);
        exit;
    }

    private function requireUserJson(): int
    {
        if (empty($_SESSION['user_id'])) {
            $this->jsonError('Authentication required.', 401);
            exit;
        }
        return (int)$_SESSION['user_id'];
    }

    private function requirePostJson(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('Method not allowed.', 405);
            exit;
        }
    }

    private function jsonInput(): array
    {
        $raw = file_get_contents('php://input');
        $input = json_decode((string)$raw, true);
        return is_array($input) ? $input : [];
    }

    private function validateCsrfFromJson(): void
    {
        $input = $this->jsonInput();
        $this->validateCsrfFromPayload($input);
    }

    private function validateCsrfFromPayload(array $input): void
    {
        $token = (string)($input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!validateCsrfToken($token)) {
            $this->jsonError('Invalid CSRF token.', 403);
            exit;
        }
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    private function jsonError(string $message, int $status): void
    {
        $this->json(['success' => false, 'message' => $message], $status);
    }
}