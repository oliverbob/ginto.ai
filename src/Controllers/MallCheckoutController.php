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

        $this->view('mall/checkout', [
            'title' => 'Checkout - Ginto Mall',
            'csrf_token' => generateCsrfToken(),
            'wallet' => $walletSummary['account'],
            'mall_unread_notifications' => $this->commerce->getMallUnreadNotificationCount($userId),
            'mall_notifications' => $this->commerce->getMallNotifications($userId),
            'paypal_client_id' => $paypalClientId,
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
            'wallet_transactions' => $walletSummary['transactions'],
            'mall_unread_notifications' => $this->commerce->getMallUnreadNotificationCount($userId),
            'mall_notifications' => $this->commerce->getMallNotifications($userId),
        ]);
    }

    public function buyerOrdersPage()
    {
        $userId = $this->requireUser();
        $this->view('mall/orders', [
            'title' => 'My Mall Orders',
            'csrf_token' => generateCsrfToken(),
            'orders' => $this->commerce->listBuyerOrders($userId),
            'page_kind' => 'buyer',
            'mall_unread_notifications' => $this->commerce->getMallUnreadNotificationCount($userId),
            'mall_notifications' => $this->commerce->getMallNotifications($userId),
        ]);
    }

    public function sellerOrdersPage()
    {
        $userId = $this->requireUser();
        $this->view('mall/orders', [
            'title' => 'Seller Orders',
            'csrf_token' => generateCsrfToken(),
            'orders' => $this->commerce->listSellerOrders($userId),
            'page_kind' => 'seller',
            'mall_unread_notifications' => $this->commerce->getMallUnreadNotificationCount($userId),
            'mall_notifications' => $this->commerce->getMallNotifications($userId),
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

        $this->view('mall/orders', [
            'title' => 'Delivery Dashboard',
            'csrf_token' => generateCsrfToken(),
            'orders' => $this->commerce->listDeliveryOrders($userId),
            'page_kind' => 'delivery',
            'mall_unread_notifications' => $this->commerce->getMallUnreadNotificationCount($userId),
            'mall_notifications' => $this->commerce->getMallNotifications($userId),
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
            header('Location: /login');
            exit;
        }
        return (int)$_SESSION['user_id'];
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