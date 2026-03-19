<?php
namespace Ginto\Services;

use Ginto\Core\Database;
use Ginto\Handlers\PayMongoHandler;
use Ginto\Handlers\PayPalHandler;
use Ginto\Helpers\MailHelper;
use Ginto\Services\ShippingCalculator;

class MallCommerceService
{
    private $db;

    private const MIN_TOPUP_FEE_PHP = 25.00;
    private const PAYPAL_TOPUP_SERVICE_FEE_PERCENT = 0.05;

    private const PRODUCT_PRICING_DEFAULTS = [
        'hands_off' => 12.00,
        'active_discovery' => 25.00,
        'full_service' => 35.00,
        'markup' => 0.00,
    ];

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function ensureWalletAccount(int $userId): array
    {
        $wallet = $this->db->get('wallet_accounts', '*', ['user_id' => $userId]);
        if ($wallet) {
            return $wallet;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->insert('wallet_accounts', [
            'user_id' => $userId,
            'currency' => 'PHP',
            'balance' => 0.00,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->db->get('wallet_accounts', '*', ['user_id' => $userId]) ?: [];
    }

    public function getWalletSummary(int $userId): array
    {
        $wallet = $this->ensureWalletAccount($userId);
        $transactions = $this->db->select('wallet_transactions', '*', [
            'user_id' => $userId,
            'ORDER' => ['created_at' => 'DESC'],
            'LIMIT' => [0, 20],
        ]) ?: [];

        return [
            'account' => $wallet,
            'transactions' => $transactions,
        ];
    }

    public function ensureStorefront(int $userId): array
    {
        $existing = $this->db->get('seller_storefronts', '*', ['user_id' => $userId]);
        if ($existing) {
            return $existing;
        }

        $user = $this->db->get('users', ['id', 'username', 'fullname', 'public_id'], ['id' => $userId]);
        if (!$user) {
            throw new \RuntimeException('Seller account not found.');
        }

        $baseSlug = $this->sanitizeSlug((string)($user['public_id'] ?? $user['username'] ?? 'store-' . $userId));
        if ($baseSlug === '') {
            $baseSlug = 'store-' . $userId;
        }
        $slug = $this->uniqueStorefrontSlug($baseSlug);
        $displayName = trim((string)($user['fullname'] ?: $user['username'] ?: ('Store ' . $userId)));
        $now = date('Y-m-d H:i:s');

        $this->db->insert('seller_storefronts', [
            'user_id' => $userId,
            'slug' => $slug,
            'display_name' => $displayName,
            'description' => 'Official storefront on Ginto Mall',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->db->get('seller_storefronts', '*', ['user_id' => $userId]) ?: [];
    }

    public function getStorefrontBySlug(string $slug): ?array
    {
        $slug = $this->sanitizeSlug($slug);
        if ($slug === '') {
            return null;
        }

        $store = $this->db->get('seller_storefronts', '*', ['slug' => $slug, 'is_active' => 1]);
        if (!$store) {
            return null;
        }

        $seller = $this->db->get('users', ['id', 'username', 'fullname', 'public_id'], ['id' => (int)$store['user_id']]);
        if ($seller) {
            $store['seller'] = $seller;
        }

        return $store;
    }

    public function getStorefrontProducts(string $slug, int $limit = 48): array
    {
        $store = $this->getStorefrontBySlug($slug);
        if (!$store) {
            return ['storefront' => null, 'products' => []];
        }

        $products = $this->db->select('products', '*', [
            'seller_id' => (int)$store['user_id'],
            'status' => 'published',
            'is_visible' => 1,
            'ORDER' => ['created_at' => 'DESC'],
            'LIMIT' => [0, $limit],
        ]) ?: [];

        return ['storefront' => $store, 'products' => $products];
    }

    public function getMallNotifications(int $userId, int $limit = 8): array
    {
        return $this->db->select('notifications', '*', [
            'user_id' => $userId,
            'type[~]' => 'mall_',
            'ORDER' => ['created_at' => 'DESC'],
            'LIMIT' => [0, $limit],
        ]) ?: [];
    }

    public function getMallUnreadNotificationCount(int $userId): int
    {
        $count = $this->db->count('notifications', [
            'user_id' => $userId,
            'type[~]' => 'mall_',
            'is_read' => 0,
        ]);

        return is_numeric($count) ? (int)$count : 0;
    }

    public function markMallNotificationsRead(int $userId): void
    {
        $this->db->update('notifications', ['is_read' => 1], [
            'user_id' => $userId,
            'type[~]' => 'mall_',
            'is_read' => 0,
        ]);
    }

    public function createCheckoutSession(int $buyerId, array $cart, array $shipping, string $paymentMethod): array
    {
        $buyer = $this->db->get('users', ['id', 'email', 'fullname', 'username', 'phone'], ['id' => $buyerId]);
        if (!$buyer) {
            throw new \RuntimeException('Buyer account not found.');
        }

        $bundle = $this->buildOrderBundle($buyerId, $cart, $shipping, $paymentMethod);
        $sessionRef = $this->generateSessionRef('MPS');
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        $this->db->insert('mall_payment_sessions', [
            'session_ref' => $sessionRef,
            'buyer_id' => $buyerId,
            'purpose' => 'order_checkout',
            'payment_method' => $this->normalizePaymentMethod($paymentMethod),
            'gateway' => $this->paymentGatewayForMethod($paymentMethod),
            'currency' => $bundle['currency'],
            'amount_total' => $bundle['total'],
            'status' => $paymentMethod === 'wallet' ? 'processing' : 'pending',
            'order_ids_json' => json_encode($bundle['order_ids']),
            'payload_json' => json_encode([
                'shipping' => $shipping,
                'orders' => $bundle['orders'],
            ]),
            'expires_at' => $expiresAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $session = $this->getPaymentSession($sessionRef, $buyerId);
        if (!$session) {
            throw new \RuntimeException('Failed to create checkout session.');
        }

        if ($paymentMethod !== 'wallet') {
            $sellerIds = [];
            foreach ($bundle['orders'] as $orderSummary) {
                $sellerId = (int)($orderSummary['seller_id'] ?? 0);
                if ($sellerId > 0) {
                    $sellerIds[$sellerId] = true;
                }
            }
            foreach (array_keys($sellerIds) as $sellerId) {
                $this->recordStorefrontImpression($sellerId, $buyerId, 'added_to_cart');
            }
        }

        if ($paymentMethod === 'wallet') {
            $wallet = $this->ensureWalletAccount($buyerId);
            $balance = (float)($wallet['balance'] ?? 0);
            if ($balance < (float)$bundle['total']) {
                throw new \RuntimeException('Insufficient Ginto Wallet balance. Please top up your wallet first.');
            }
            $this->applyWalletDebit($buyerId, (float)$bundle['total'], $bundle['currency'], $sessionRef, 'Mall checkout payment');
            $this->completePaymentSession($sessionRef, [
                'gateway_reference' => $sessionRef,
                'gateway_payload_json' => json_encode(['source' => 'wallet']),
            ]);
            $session = $this->getPaymentSession($sessionRef, $buyerId);
        }

        return [
            'session_ref' => $sessionRef,
            'currency' => $bundle['currency'],
            'total' => $bundle['total'],
            'orders' => $bundle['orders'],
            'session' => $session,
        ];
    }

    public function initializePayMongoQrCheckout(string $sessionRef, int $buyerId): array
    {
        $session = $this->getPaymentSession($sessionRef, $buyerId);
        if (!$session || $session['payment_method'] !== 'ginto_pay_qr') {
            throw new \RuntimeException('Invalid payment session.');
        }
        if ($session['status'] === 'completed') {
            return ['success' => true, 'session_ref' => $sessionRef, 'status' => 'completed'];
        }

        if (!PayMongoHandler::isConfigured()) {
            throw new \RuntimeException('Ginto Pay is not configured.');
        }

        $buyer = $this->db->get('users', ['fullname', 'username', 'email', 'phone'], ['id' => $buyerId]);
        $description = 'Ginto Mall Order ' . $sessionRef;
        $amountCentavos = (int)round(((float)$session['amount_total']) * 100);

        $handler = new PayMongoHandler();
        $pi = $handler->createPaymentIntent($amountCentavos, $description, ['qrph']);
        if (empty($pi['success'])) {
            throw new \RuntimeException($pi['message'] ?? 'Unable to start QR payment.');
        }

        $pm = $handler->createQrphPaymentMethod(
            (string)($buyer['email'] ?? ''),
            (string)($buyer['fullname'] ?: $buyer['username'] ?: 'Ginto Buyer'),
            (string)($buyer['phone'] ?? '')
        );
        if (empty($pm['success'])) {
            throw new \RuntimeException($pm['message'] ?? 'Unable to create QR payment method.');
        }

        $attached = $handler->attachPaymentMethod((string)$pi['pi_id'], (string)$pm['pm_id'], (string)$pi['client_key']);
        if (empty($attached['success'])) {
            throw new \RuntimeException($attached['message'] ?? 'Unable to generate QR code.');
        }

        $this->db->update('mall_payment_sessions', [
            'status' => 'processing',
            'gateway' => 'paymongo',
            'gateway_reference' => $pi['pi_id'],
            'gateway_payload_json' => json_encode($attached),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['session_ref' => $sessionRef]);

        return [
            'success' => true,
            'session_ref' => $sessionRef,
            'pi_id' => $pi['pi_id'],
            'status' => $attached['status'] ?? 'awaiting_next_action',
            'qr_image' => $attached['qr_image'] ?? '',
            'qr_string' => $attached['qr_string'] ?? '',
        ];
    }

    public function initializePayMongoCardCheckout(string $sessionRef, int $buyerId, array $cardInput = [], array $billingInput = []): array
    {
        $session = $this->getPaymentSession($sessionRef, $buyerId);
        if (!$session || $session['payment_method'] !== 'ginto_pay_card') {
            throw new \RuntimeException('Invalid payment session.');
        }
        if (!PayMongoHandler::isConfigured()) {
            throw new \RuntimeException('Ginto Pay is not configured.');
        }

        $buyer = $this->db->get('users', ['fullname', 'username', 'email', 'phone'], ['id' => $buyerId]);
        $handler = new PayMongoHandler();
        $description = 'Ginto Mall Order ' . $sessionRef;
        $amountPhp = (float)$session['amount_total'];

        $cardNumber = preg_replace('/[^0-9]/', '', (string)($cardInput['number'] ?? ''));
        $expMonth = preg_replace('/[^0-9]/', '', (string)($cardInput['exp_month'] ?? ''));
        $expYear = preg_replace('/[^0-9]/', '', (string)($cardInput['exp_year'] ?? ''));
        $cvc = preg_replace('/[^0-9]/', '', (string)($cardInput['cvc'] ?? ''));

        if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
            throw new \RuntimeException('Invalid card number.');
        }
        if ((int)$expMonth < 1 || (int)$expMonth > 12 || strlen($expYear) < 2 || strlen($cvc) < 3) {
            throw new \RuntimeException('Invalid card expiry or CVC.');
        }

        $billingAddress = [
            'line1' => trim(strip_tags((string)($billingInput['line1'] ?? ''))),
            'line2' => trim(strip_tags((string)($billingInput['line2'] ?? ''))),
            'city' => trim(strip_tags((string)($billingInput['city'] ?? ''))),
            'state' => trim(strip_tags((string)($billingInput['state'] ?? ''))),
            'postal_code' => preg_replace('/[^a-zA-Z0-9\-\s]/', '', (string)($billingInput['postal_code'] ?? '')),
            'country' => strtoupper(preg_replace('/[^a-zA-Z]/', '', (string)($billingInput['country'] ?? 'PH'))),
        ];

        $result = $handler->initCardPayment(
            $amountPhp,
            (string)($buyer['email'] ?? ''),
            (string)($buyer['fullname'] ?: $buyer['username'] ?: 'Ginto Buyer'),
            (string)($buyer['phone'] ?? ''),
            $description,
            [
                'number' => $cardNumber,
                'exp_month' => $expMonth,
                'exp_year' => $expYear,
                'cvc' => $cvc,
            ],
            $billingAddress
        );

        if (empty($result['success'])) {
            throw new \RuntimeException($result['message'] ?? 'Unable to start card checkout.');
        }

        $this->db->update('mall_payment_sessions', [
            'status' => 'processing',
            'gateway' => 'paymongo',
            'gateway_reference' => $result['pi_id'],
            'gateway_payment_id' => $result['payment_id'] ?? null,
            'gateway_payload_json' => json_encode($result),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['session_ref' => $sessionRef]);

        return [
            'success' => true,
            'session_ref' => $sessionRef,
            'pi_id' => $result['pi_id'],
            'status' => $result['status'] ?? 'processing',
            'payment_id' => $result['payment_id'] ?? null,
            'requires_action' => !empty($result['next_action']['redirect']['url'] ?? $result['next_action']['url'] ?? null),
            'next_action_url' => $result['next_action']['redirect']['url'] ?? $result['next_action']['url'] ?? null,
        ];
    }

    public function initializePayPalCheckout(string $sessionRef, int $buyerId): array
    {
        $session = $this->getPaymentSession($sessionRef, $buyerId);
        if (!$session || $session['payment_method'] !== 'paypal') {
            throw new \RuntimeException('Invalid payment session.');
        }

        $payload = json_decode((string)($session['payload_json'] ?? '{}'), true) ?: [];
        $orders = $payload['orders'] ?? [];
        $items = [];
        foreach ($orders as $order) {
            foreach (($order['items'] ?? []) as $item) {
                $items[] = [
                    'name' => $item['title'],
                    'quantity' => (int)$item['quantity'],
                    'unit_price' => (float)$item['charged_unit_price'],
                ];
            }
        }

        $handler = new PayPalHandler();
        $order = $handler->createOrder($items, (string)$session['currency']);
        if (!empty($order['error']) || empty($order['id'])) {
            throw new \RuntimeException($order['error'] ?? 'Unable to create PayPal order.');
        }

        $this->db->update('mall_payment_sessions', [
            'status' => 'processing',
            'gateway' => 'paypal',
            'gateway_reference' => $order['id'],
            'gateway_payload_json' => json_encode($order),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['session_ref' => $sessionRef]);

        return [
            'success' => true,
            'session_ref' => $sessionRef,
            'paypal_order_id' => $order['id'],
        ];
    }

    public function capturePayPalCheckout(string $sessionRef, int $buyerId, string $paypalOrderId): array
    {
        $session = $this->getPaymentSession($sessionRef, $buyerId);
        if (!$session || $session['payment_method'] !== 'paypal') {
            throw new \RuntimeException('Invalid payment session.');
        }

        $handler = new PayPalHandler();
        $capture = $handler->payOrder($paypalOrderId);
        $status = strtoupper((string)($capture['status'] ?? ''));
        if ($status !== 'COMPLETED') {
            throw new \RuntimeException('PayPal capture was not completed.');
        }

        $captureId = $capture['purchase_units'][0]['payments']['captures'][0]['id'] ?? $paypalOrderId;
        $this->completePaymentSession($sessionRef, [
            'gateway' => 'paypal',
            'gateway_reference' => $paypalOrderId,
            'gateway_payment_id' => $captureId,
            'gateway_payload_json' => json_encode($capture),
        ]);

        return [
            'success' => true,
            'session_ref' => $sessionRef,
            'capture_id' => $captureId,
        ];
    }

    public function createWalletTopupSession(int $userId, float $amount, string $paymentMethod): array
    {
        if ($amount <= 0) {
            throw new \RuntimeException('Top-up amount must be greater than zero.');
        }

        $normalizedMethod = $this->normalizePaymentMethod($paymentMethod);
        if (!in_array($normalizedMethod, ['ginto_pay_qr', 'ginto_pay_card', 'paypal'], true)) {
            throw new \RuntimeException('Unsupported top-up payment method.');
        }

        $feeAmount = $this->calculateTopupFeeAmount($normalizedMethod, $amount);
        $creditAmount = round($amount, 2);
        $grossAmount = round($creditAmount + $feeAmount, 2);

        $sessionRef = $this->generateSessionRef('WTU');
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        $this->db->insert('mall_payment_sessions', [
            'session_ref' => $sessionRef,
            'buyer_id' => $userId,
            'purpose' => 'wallet_topup',
            'payment_method' => $normalizedMethod,
            'gateway' => $this->paymentGatewayForMethod($normalizedMethod),
            'currency' => 'PHP',
            'amount_total' => $grossAmount,
            'status' => 'pending',
            'payload_json' => json_encode([
                'topup_amount' => $creditAmount,
                'topup_fee' => round($feeAmount, 2),
                'topup_credit_amount' => $creditAmount,
                'topup_total_amount' => $grossAmount,
                'fee_policy' => $this->topupFeePolicyName($normalizedMethod, $feeAmount),
            ]),
            'expires_at' => $expiresAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'session_ref' => $sessionRef,
            'amount' => $grossAmount,
            'fee' => round($feeAmount, 2),
            'credit_amount' => $creditAmount,
        ];
    }

    public function getPaymentSession(string $sessionRef, int $userId): ?array
    {
        return $this->db->get('mall_payment_sessions', '*', [
            'session_ref' => $sessionRef,
            'buyer_id' => $userId,
        ]);
    }

    public function getPaymentSessionByGatewayReference(string $gatewayReference): ?array
    {
        return $this->db->get('mall_payment_sessions', '*', [
            'gateway_reference' => $gatewayReference,
            'status' => ['pending', 'processing'],
        ]);
    }

    public function completePaymentSession(string $sessionRef, array $gatewayMeta = []): void
    {
        $session = $this->db->get('mall_payment_sessions', '*', ['session_ref' => $sessionRef]);
        if (!$session) {
            throw new \RuntimeException('Payment session not found.');
        }
        if ($session['status'] === 'completed') {
            return;
        }

        $buyerId = (int)$session['buyer_id'];
        $now = date('Y-m-d H:i:s');

        $this->db->pdo->beginTransaction();
        try {
            $sessionUpdate = [
                'status' => 'completed',
                'completed_at' => $now,
                'updated_at' => $now,
            ];
            foreach (['gateway', 'gateway_reference', 'gateway_payment_id', 'gateway_payload_json'] as $field) {
                if (array_key_exists($field, $gatewayMeta) && $gatewayMeta[$field] !== null) {
                    $sessionUpdate[$field] = $gatewayMeta[$field];
                }
            }
            $this->db->update('mall_payment_sessions', $sessionUpdate, ['session_ref' => $sessionRef]);

            if ($session['purpose'] === 'wallet_topup') {
                $payload = json_decode((string)($session['payload_json'] ?? '{}'), true) ?: [];
                $grossAmount = round((float)($payload['topup_total_amount'] ?? $session['amount_total'] ?? 0), 2);
                $feeAmount = round((float)($payload['topup_fee'] ?? 0), 2);
                $creditAmount = round((float)($payload['topup_credit_amount'] ?? $payload['topup_amount'] ?? ($grossAmount - $feeAmount)), 2);

                if ($creditAmount <= 0) {
                    throw new \RuntimeException('Wallet top-up credit amount is invalid.');
                }

                $creditDescription = $feeAmount > 0
                    ? 'Ginto Wallet top-up (plus ₱' . number_format($feeAmount, 2) . ' Ginto Cash In fee)'
                    : 'Ginto Wallet top-up';

                $this->applyWalletCredit(
                    $buyerId,
                    $creditAmount,
                    'PHP',
                    $sessionRef,
                    $creditDescription,
                    [
                        'gross_amount' => $grossAmount,
                        'fee_amount' => $feeAmount,
                        'net_credited_amount' => $creditAmount,
                        'payment_method' => (string)($session['payment_method'] ?? ''),
                    ]
                );

                $this->createMallNotification($buyerId, null, 'mall_wallet_topup_completed', 'Your Ginto Wallet top-up has been credited.', [
                    'session_ref' => $sessionRef,
                    'gross_amount' => $grossAmount,
                    'fee' => $feeAmount,
                    'credited_amount' => $creditAmount,
                ]);
            } else {
                $orderIds = json_decode((string)($session['order_ids_json'] ?? '[]'), true) ?: [];
                foreach ($orderIds as $orderId) {
                    $this->markOrderPaid((int)$orderId, (string)$session['payment_method'], (string)($gatewayMeta['gateway_reference'] ?? $session['gateway_reference'] ?? $sessionRef), (string)($gatewayMeta['gateway_payment_id'] ?? $session['gateway_payment_id'] ?? ''));
                }
            }

            $this->db->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->db->pdo->inTransaction()) {
                $this->db->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function failPaymentSessionByGatewayReference(string $gatewayReference, string $reason): void
    {
        $this->db->update('mall_payment_sessions', [
            'status' => 'failed',
            'updated_at' => date('Y-m-d H:i:s'),
            'gateway_payload_json' => json_encode(['reason' => $reason]),
        ], ['gateway_reference' => $gatewayReference]);
    }

    public function getPayMongoSessionStatus(string $sessionRef, int $buyerId): array
    {
        $session = $this->getPaymentSession($sessionRef, $buyerId);
        if (!$session) {
            throw new \RuntimeException('Payment session not found.');
        }
        if ($session['status'] === 'completed') {
            return ['success' => true, 'status' => 'completed'];
        }
        if (empty($session['gateway_reference'])) {
            return ['success' => true, 'status' => 'pending'];
        }

        // Hosted Ginto Pay card checkout is finalized by PayMongo webhook using
        // checkout_session.payment.paid. The gateway_reference here is a checkout
        // session ID, not a payment intent ID, so status polling should wait for
        // the webhook instead of querying the payment intent endpoint.
        if (($session['payment_method'] ?? '') === 'ginto_pay_card') {
            return ['success' => true, 'status' => ($session['status'] ?: 'processing')];
        }

        $handler = new PayMongoHandler();
        $status = $handler->getPaymentIntentStatus((string)$session['gateway_reference']);
        if (empty($status['success'])) {
            throw new \RuntimeException($status['message'] ?? 'Unable to retrieve payment status.');
        }

        $paymentStatus = strtolower((string)($status['status'] ?? 'pending'));
        if (in_array($paymentStatus, ['succeeded', 'awaiting_capture'], true)) {
            $gatewayPaymentId = $status['payment_id'] ?? null;
            $this->completePaymentSession($sessionRef, [
                'gateway' => 'paymongo',
                'gateway_reference' => $session['gateway_reference'],
                'gateway_payment_id' => $gatewayPaymentId,
                'gateway_payload_json' => json_encode($status),
            ]);
            return ['success' => true, 'status' => 'completed'];
        }

        return ['success' => true, 'status' => $paymentStatus];
    }

    public function listBuyerOrders(int $buyerId): array
    {
        return $this->hydrateOrders($this->db->select('mall_orders', '*', [
            'buyer_id' => $buyerId,
            'payment_status' => ['paid', 'refunded'],
            'ORDER' => ['created_at' => 'DESC'],
            'LIMIT' => [0, 100],
        ]) ?: []);
    }

    public function listSellerOrders(int $sellerId): array
    {
        return $this->hydrateOrders($this->db->select('mall_orders', '*', [
            'seller_id' => $sellerId,
            'payment_status' => ['paid', 'refunded'],
            'ORDER' => ['created_at' => 'DESC'],
            'LIMIT' => [0, 100],
        ]) ?: []);
    }

    public function purgeUnpaidOrdersForUser(int $userId, string $scope = 'buyer'): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $scopeColumn = $scope === 'seller' ? 'seller_id' : 'buyer_id';
        $orders = $this->db->select('mall_orders', ['id'], [
            $scopeColumn => $userId,
            'payment_status[!]' => ['paid', 'refunded'],
        ]) ?: [];

        $orderIds = [];
        foreach ($orders as $order) {
            $id = (int)($order['id'] ?? 0);
            if ($id > 0) {
                $orderIds[] = $id;
            }
        }

        if (empty($orderIds)) {
            return 0;
        }

        $this->db->pdo->beginTransaction();
        try {
            $this->db->delete('mall_order_items', ['order_id' => $orderIds]);
            $this->db->delete('mall_order_status_history', ['order_id' => $orderIds]);
            $this->db->delete('mall_orders', ['id' => $orderIds]);
            $this->db->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->db->pdo->inTransaction()) {
                $this->db->pdo->rollBack();
            }
            throw $e;
        }

        return count($orderIds);
    }

    public function recordStorefrontImpression(int $sellerId, ?int $viewerUserId, string $activity = 'viewed'): void
    {
        if ($sellerId <= 0) {
            return;
        }
        if (!empty($viewerUserId) && $viewerUserId === $sellerId) {
            return;
        }

        $message = match ($activity) {
            'added_to_cart' => 'User added items to cart but has not paid yet.',
            default => 'User viewed your storefront but never paid yet.',
        };

        $this->createMallNotification(
            $sellerId,
            !empty($viewerUserId) ? $viewerUserId : null,
            'mall_seller_impression',
            $message,
            [
                'activity' => $activity,
                'privacy_mode' => 'no_pii',
            ]
        );
    }

    public function listDeliveryOrders(int $deliveryUserId): array
    {
        return $this->hydrateOrders($this->db->select('mall_orders', '*', [
            'OR' => [
                'delivery_assignee_user_id' => $deliveryUserId,
                'AND #claimable' => [
                    'delivery_assignee_user_id' => null,
                    'status' => ['processing', 'ready_for_pickup'],
                    'payment_status' => 'paid',
                ],
            ],
            'ORDER' => ['created_at' => 'DESC'],
            'LIMIT' => [0, 100],
        ]) ?: []);
    }

    public function claimDeliveryOrder(int $orderId, int $deliveryUserId): void
    {
        $order = $this->db->get('mall_orders', '*', ['id' => $orderId]);
        if (!$order) {
            throw new \RuntimeException('Order not found.');
        }
        if (!empty($order['delivery_assignee_user_id']) && (int)$order['delivery_assignee_user_id'] !== $deliveryUserId) {
            throw new \RuntimeException('This order is already assigned to another delivery account.');
        }

        $deliveryUser = $this->db->get('users', ['fullname', 'username'], ['id' => $deliveryUserId]);
        $name = trim((string)($deliveryUser['fullname'] ?: $deliveryUser['username'] ?: ('Delivery #' . $deliveryUserId)));
        $this->db->update('mall_orders', [
            'delivery_assignee_user_id' => $deliveryUserId,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $orderId]);
        $this->addOrderHistory($orderId, $deliveryUserId, 'delivery', $order['status'], $order['status'], $name . ' accepted the delivery assignment.');

        $this->createMallNotification((int)$order['seller_id'], $deliveryUserId, 'mall_delivery_claimed', 'A delivery service accepted order ' . $order['order_code'] . '.', ['order_id' => $orderId]);
        $this->createMallNotification((int)$order['buyer_id'], $deliveryUserId, 'mall_delivery_claimed', 'A delivery service has been assigned to your order ' . $order['order_code'] . '.', ['order_id' => $orderId]);
    }

    public function updateOrderStatus(int $orderId, int $actorUserId, string $actorType, string $toStatus, string $message = ''): void
    {
        $allowed = ['paid', 'processing', 'ready_for_pickup', 'in_transit', 'delivered', 'completed', 'cancelled'];
        if (!in_array($toStatus, $allowed, true)) {
            throw new \RuntimeException('Unsupported order status.');
        }

        $order = $this->db->get('mall_orders', '*', ['id' => $orderId]);
        if (!$order) {
            throw new \RuntimeException('Order not found.');
        }

        $updates = [
            'status' => $toStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($toStatus === 'delivered') {
            $updates['delivered_at'] = date('Y-m-d H:i:s');
        }
        if ($toStatus === 'cancelled') {
            $updates['payment_status'] = $order['payment_status'] === 'paid' ? 'refunded' : $order['payment_status'];
        }

        $this->db->update('mall_orders', $updates, ['id' => $orderId]);
        $this->addOrderHistory($orderId, $actorUserId, $actorType, (string)$order['status'], $toStatus, $message ?: ('Order updated to ' . $this->humanizeStatus($toStatus) . '.'));

        $buyerMessage = 'Your order ' . $order['order_code'] . ' is now ' . $this->humanizeStatus($toStatus) . '.';
        $this->createMallNotification((int)$order['buyer_id'], $actorUserId, 'mall_order_status_updated', $buyerMessage, ['order_id' => $orderId, 'status' => $toStatus]);
        $this->emailOrderStatusUpdate((int)$order['buyer_id'], $buyerMessage, $order, $message);
    }

    public function isDeliveryAccount(int $userId): bool
    {
        $row = $this->db->get('kyc_profiles', ['account_type', 'status'], ['user_id' => $userId]);
        return !empty($row) && ($row['account_type'] ?? '') === 'delivery_service' && in_array(($row['status'] ?? ''), ['pending', 'approved', 'review'], true);
    }

    private function buildOrderBundle(int $buyerId, array $cart, array $shipping, string $paymentMethod): array
    {
        if (empty($cart)) {
            throw new \RuntimeException('Your cart is empty.');
        }

        $productIds = [];
        $qtyById = [];
        foreach ($cart as $item) {
            $productId = (int)($item['id'] ?? 0);
            $qty = max(1, (int)($item['qty'] ?? $item['quantity'] ?? 1));
            if ($productId <= 0) {
                continue;
            }
            $productIds[] = $productId;
            $qtyById[$productId] = $qty;
        }
        $productIds = array_values(array_unique($productIds));
        if (empty($productIds)) {
            throw new \RuntimeException('No valid products were found in your cart.');
        }

        $products = $this->db->select('products', '*', ['id' => $productIds]) ?: [];
        if (count($products) !== count($productIds)) {
            throw new \RuntimeException('One or more products are no longer available.');
        }

        $currency = null;
        $grouped = [];
        foreach ($products as $product) {
            if (($product['status'] ?? '') !== 'published' || (int)($product['is_visible'] ?? 0) !== 1) {
                throw new \RuntimeException('One or more products are no longer published.');
            }
            $productCurrency = (string)($product['currency'] ?? 'PHP');
            if ($currency === null) {
                $currency = $productCurrency;
            }
            if ($currency !== $productCurrency) {
                throw new \RuntimeException('Mixed-currency carts are not supported yet. Please checkout one currency at a time.');
            }
            if (in_array($paymentMethod, ['ginto_pay_qr', 'ginto_pay_card', 'wallet'], true) && $productCurrency !== 'PHP') {
                throw new \RuntimeException('Ginto Pay and Ginto Wallet currently support PHP-priced products only.');
            }

            $quantity = $qtyById[(int)$product['id']] ?? 1;
            $availableQty = isset($product['quantity']) ? (int)$product['quantity'] : 0;
            if ($availableQty > 0 && $quantity > $availableQty) {
                throw new \RuntimeException('Insufficient stock for ' . ($product['title'] ?? 'a product') . '.');
            }

            $pricing = $this->calculatePricing($product, $quantity);
            $sellerId = (int)$product['seller_id'];
            $storefront = $this->ensureStorefront($sellerId);
            if (!isset($grouped[$sellerId])) {
                $grouped[$sellerId] = [
                    'seller_id' => $sellerId,
                    'storefront_id' => (int)($storefront['id'] ?? 0),
                    'storefront_slug' => $storefront['slug'] ?? '',
                    'items' => [],
                    'subtotal' => 0.00,
                    'platform_fee' => 0.00,
                    'seller_net' => 0.00,
                    'buyer_total' => 0.00,
                ];
            }
            $grouped[$sellerId]['items'][] = array_merge($pricing, [
                'product_id' => (int)$product['id'],
                'title' => (string)($product['title'] ?? ''),
                'image_url' => $this->firstProductImage($product),
                // Shipping dimension snapshot — carried into order items
                'weight_kg' => max(0.0, (float)($product['weight_kg'] ?? 0)),
                'length_cm' => max(0.0, (float)($product['length_cm'] ?? 0)),
                'width_cm'  => max(0.0, (float)($product['width_cm']  ?? 0)),
                'height_cm' => max(0.0, (float)($product['height_cm'] ?? 0)),
            ]);
            $grouped[$sellerId]['subtotal'] += $pricing['line_subtotal'];
            $grouped[$sellerId]['platform_fee'] += $pricing['platform_fee_amount'];
            $grouped[$sellerId]['seller_net'] += $pricing['seller_net_amount'];
            $grouped[$sellerId]['buyer_total'] += $pricing['line_subtotal'];
        }

        $sanitizedShipping = $this->sanitizeShipping($shipping);
        if (empty($sanitizedShipping['full_name']) || empty($sanitizedShipping['phone']) || empty($sanitizedShipping['address_line1']) || empty($sanitizedShipping['city'])) {
            throw new \RuntimeException('Shipping full name, phone, address, and city are required.');
        }

        // -- Shipping fee estimation ----------------------------------------
        // Infer delivery zone from buyer address; no logistics partner yet so
        // we use the conservative safe divisor (3,500) to protect sellers from
        // shortfall surprises when a courier takes an actual measurement.
        $shippingCalc = new ShippingCalculator();
        $buyerZone    = ShippingCalculator::inferZone(
            (string)($sanitizedShipping['province'] ?? ''),
            (string)($sanitizedShipping['city']     ?? '')
        );

        // Compute per-seller shipping estimate (each seller = 1 parcel group)
        foreach ($grouped as $sellerId => $group) {
            $shippingResult = $shippingCalc->estimate(
                $group['items'],
                $buyerZone,
                ShippingCalculator::DIVISOR_SAFE, // conservative — safe default
                false                              // no logistics partner yet
            );
            $grouped[$sellerId]['shipping_fee']       = $shippingResult['estimated_fee'];
            $grouped[$sellerId]['shipping_zone']      = $shippingResult['zone'];
            $grouped[$sellerId]['chargeable_weight']  = $shippingResult['chargeable_weight_kg'];
            $grouped[$sellerId]['shipping_dimensions_json'] = json_encode($shippingResult);
            // Buyer pays product subtotal + shipping fee
            $grouped[$sellerId]['buyer_total'] += $shippingResult['estimated_fee'];
        }

        $now = date('Y-m-d H:i:s');
        $orderIds = [];
        $orders = [];
        $checkoutRef = $this->generateSessionRef('CHK');

        $this->db->pdo->beginTransaction();
        try {
            foreach ($grouped as $sellerId => $group) {
                $orderCode = $this->generateOrderCode();
                $this->db->insert('mall_orders', [
                    'order_code' => $orderCode,
                    'checkout_ref' => $checkoutRef,
                    'buyer_id' => $buyerId,
                    'seller_id' => $sellerId,
                    'storefront_id' => $group['storefront_id'] ?: null,
                    'currency' => $currency ?: 'PHP',
                    'subtotal_amount' => round($group['subtotal'], 2),
                    'platform_fee_amount' => round($group['platform_fee'], 2),
                    'seller_net_amount' => round($group['seller_net'], 2),
                    'buyer_total_amount' => round($group['buyer_total'], 2),
                    'shipping_fee_estimated' => round($group['shipping_fee'] ?? 0.00, 2),
                    'shipping_zone' => $group['shipping_zone'] ?? null,
                    'chargeable_weight_kg' => $group['chargeable_weight'] ?? null,
                    'shipping_dimensions_json' => $group['shipping_dimensions_json'] ?? null,
                    'payment_status' => 'pending',
                    'status' => 'pending_payment',
                    'payment_method' => $this->normalizePaymentMethod($paymentMethod),
                    'shipping_address_json' => json_encode($sanitizedShipping),
                    'buyer_notes' => $sanitizedShipping['buyer_notes'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $orderId = (int)$this->db->id();
                $orderIds[] = $orderId;

                foreach ($group['items'] as $item) {
                    $this->db->insert('mall_order_items', [
                        'order_id' => $orderId,
                        'product_id' => $item['product_id'],
                        'seller_id' => $sellerId,
                        'title_snapshot' => $item['title'],
                        'quantity' => $item['quantity'],
                        'currency' => $currency ?: 'PHP',
                        'base_unit_price' => round($item['base_unit_price'], 2),
                        'charged_unit_price' => round($item['charged_unit_price'], 2),
                        'line_subtotal' => round($item['line_subtotal'], 2),
                        'platform_fee_amount' => round($item['platform_fee_amount'], 2),
                        'seller_net_amount' => round($item['seller_net_amount'], 2),
                        'pricing_model' => $item['pricing_model'],
                        'pricing_rate' => round($item['pricing_rate'], 2),
                        'markup_rate' => round($item['markup_rate'], 2),
                        'image_url' => $item['image_url'],
                        'weight_kg_snapshot' => $item['weight_kg'] > 0 ? $item['weight_kg'] : null,
                        'length_cm_snapshot' => $item['length_cm'] > 0 ? $item['length_cm'] : null,
                        'width_cm_snapshot'  => $item['width_cm']  > 0 ? $item['width_cm']  : null,
                        'height_cm_snapshot' => $item['height_cm'] > 0 ? $item['height_cm'] : null,
                        'metadata' => json_encode(['storefront_slug' => $group['storefront_slug']]),
                    ]);
                }

                $this->addOrderHistory($orderId, $buyerId, 'buyer', null, 'pending_payment', 'Order created and awaiting payment.');
                $orders[] = [
                    'order_id' => $orderId,
                    'order_code' => $orderCode,
                    'seller_id' => $sellerId,
                    'storefront_slug' => $group['storefront_slug'],
                    'buyer_total' => round($group['buyer_total'], 2),
                    'shipping_fee' => round($group['shipping_fee'] ?? 0.00, 2),
                    'shipping_zone' => $group['shipping_zone'] ?? null,
                    'chargeable_weight_kg' => $group['chargeable_weight'] ?? null,
                    'items' => $group['items'],
                ];
            }
            $this->db->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->db->pdo->inTransaction()) {
                $this->db->pdo->rollBack();
            }
            throw $e;
        }

        $total = 0.00;
        foreach ($orders as $order) {
            $total += (float)$order['buyer_total'];
        }

        return [
            'checkout_ref' => $checkoutRef,
            'currency' => $currency ?: 'PHP',
            'total' => round($total, 2),
            'orders' => $orders,
            'order_ids' => $orderIds,
        ];
    }

    private function calculatePricing(array $product, int $quantity): array
    {
        $baseUnitPrice = round((float)($product['price'] ?? 0), 2);
        $pricingModel = (string)($product['pricing_model'] ?? 'hands_off');
        if (!array_key_exists($pricingModel, self::PRODUCT_PRICING_DEFAULTS)) {
            $pricingModel = 'hands_off';
        }

        $pricingRate = isset($product['pricing_rate']) ? (float)$product['pricing_rate'] : self::PRODUCT_PRICING_DEFAULTS[$pricingModel];
        $markupRate = isset($product['markup_rate']) ? (float)$product['markup_rate'] : 0.00;

        if ($pricingModel === 'markup') {
            $markupRate = min(50.00, max(10.00, $markupRate ?: 10.00));
            $chargedUnitPrice = round($baseUnitPrice * (1 + ($markupRate / 100)), 2);
            $platformFeePerUnit = round($chargedUnitPrice - $baseUnitPrice, 2);
            $sellerNetPerUnit = $baseUnitPrice;
            $pricingRate = 0.00;
        } else {
            $pricingRate = max(10.00, min(50.00, $pricingRate ?: self::PRODUCT_PRICING_DEFAULTS[$pricingModel]));
            $chargedUnitPrice = $baseUnitPrice;
            $platformFeePerUnit = round($baseUnitPrice * ($pricingRate / 100), 2);
            $sellerNetPerUnit = round($baseUnitPrice - $platformFeePerUnit, 2);
        }

        return [
            'pricing_model' => $pricingModel,
            'pricing_rate' => $pricingRate,
            'markup_rate' => $markupRate,
            'quantity' => $quantity,
            'base_unit_price' => $baseUnitPrice,
            'charged_unit_price' => $chargedUnitPrice,
            'line_subtotal' => round($chargedUnitPrice * $quantity, 2),
            'platform_fee_amount' => round($platformFeePerUnit * $quantity, 2),
            'seller_net_amount' => round($sellerNetPerUnit * $quantity, 2),
        ];
    }

    private function markOrderPaid(int $orderId, string $paymentMethod, string $paymentReference, string $gatewayPaymentId): void
    {
        $order = $this->db->get('mall_orders', '*', ['id' => $orderId]);
        if (!$order) {
            return;
        }
        if (($order['payment_status'] ?? '') === 'paid') {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->update('mall_orders', [
            'payment_status' => 'paid',
            'status' => 'paid',
            'payment_method' => $paymentMethod,
            'payment_reference' => $gatewayPaymentId !== '' ? $gatewayPaymentId : $paymentReference,
            'paid_at' => $now,
            'updated_at' => $now,
        ], ['id' => $orderId]);

        $items = $this->db->select('mall_order_items', '*', ['order_id' => $orderId]) ?: [];
        foreach ($items as $item) {
            $product = $this->db->get('products', ['id', 'quantity'], ['id' => (int)$item['product_id']]);
            if ($product && isset($product['quantity'])) {
                $remaining = max(0, (int)$product['quantity'] - (int)$item['quantity']);
                $this->db->update('products', [
                    'quantity' => $remaining,
                    'updated_at' => $now,
                ], ['id' => (int)$item['product_id']]);
            }
        }

        $this->addOrderHistory($orderId, (int)$order['buyer_id'], 'buyer', (string)$order['status'], 'paid', 'Payment confirmed. Seller has been notified.');

        $this->createMallNotification((int)$order['seller_id'], (int)$order['buyer_id'], 'mall_order_paid', 'You received a new paid order: ' . $order['order_code'] . '.', ['order_id' => $orderId]);
        $this->createMallNotification((int)$order['buyer_id'], null, 'mall_order_paid', 'Your order ' . $order['order_code'] . ' has been paid successfully.', ['order_id' => $orderId]);
        $this->emailSellerPaidOrder($orderId);
    }

    private function hydrateOrders(array $orders): array
    {
        if (empty($orders)) {
            return [];
        }
        $orderIds = array_map(static function ($order) {
            return (int)$order['id'];
        }, $orders);
        $items = $this->db->select('mall_order_items', '*', ['order_id' => $orderIds]) ?: [];
        $history = $this->db->select('mall_order_status_history', '*', [
            'order_id' => $orderIds,
            'ORDER' => ['created_at' => 'DESC'],
        ]) ?: [];
        $itemsByOrder = [];
        foreach ($items as $item) {
            $itemsByOrder[(int)$item['order_id']][] = $item;
        }
        $historyByOrder = [];
        foreach ($history as $row) {
            $historyByOrder[(int)$row['order_id']][] = $row;
        }

        foreach ($orders as &$order) {
            $order['items'] = $itemsByOrder[(int)$order['id']] ?? [];
            $order['history'] = $historyByOrder[(int)$order['id']] ?? [];
            $shipping = json_decode((string)($order['shipping_address_json'] ?? '{}'), true) ?: [];
            $order['shipping_address'] = $shipping;
            $store = null;
            if (!empty($order['storefront_id'])) {
                $store = $this->db->get('seller_storefronts', '*', ['id' => (int)$order['storefront_id']]);
            }
            if ($store) {
                $order['storefront'] = $store;
            }
            $buyer = $this->db->get('users', ['id', 'fullname', 'username', 'email'], ['id' => (int)$order['buyer_id']]);
            $seller = $this->db->get('users', ['id', 'fullname', 'username', 'email'], ['id' => (int)$order['seller_id']]);
            if ($buyer) {
                $order['buyer'] = $buyer;
            }
            if ($seller) {
                $order['seller'] = $seller;
            }
        }
        unset($order);

        return $orders;
    }

    private function applyWalletDebit(int $userId, float $amount, string $currency, string $referenceId, string $description): void
    {
        $wallet = $this->ensureWalletAccount($userId);
        $balance = round((float)($wallet['balance'] ?? 0), 2);
        if ($balance < $amount) {
            throw new \RuntimeException('Insufficient Ginto Wallet balance.');
        }
        $newBalance = round($balance - $amount, 2);
        $this->db->update('wallet_accounts', [
            'balance' => $newBalance,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => (int)$wallet['id']]);
        $this->db->insert('wallet_transactions', [
            'wallet_account_id' => (int)$wallet['id'],
            'user_id' => $userId,
            'type' => 'purchase',
            'direction' => 'debit',
            'status' => 'completed',
            'currency' => $currency,
            'amount' => round($amount, 2),
            'balance_after' => $newBalance,
            'reference_type' => 'mall_payment_session',
            'reference_id' => $referenceId,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function applyWalletCredit(int $userId, float $amount, string $currency, string $referenceId, string $description, array $metadata = []): void
    {
        $wallet = $this->ensureWalletAccount($userId);
        $balance = round((float)($wallet['balance'] ?? 0), 2);
        $newBalance = round($balance + $amount, 2);
        $this->db->update('wallet_accounts', [
            'balance' => $newBalance,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => (int)$wallet['id']]);
        $this->db->insert('wallet_transactions', [
            'wallet_account_id' => (int)$wallet['id'],
            'user_id' => $userId,
            'type' => 'topup',
            'direction' => 'credit',
            'status' => 'completed',
            'currency' => $currency,
            'amount' => round($amount, 2),
            'balance_after' => $newBalance,
            'reference_type' => 'mall_payment_session',
            'reference_id' => $referenceId,
            'description' => $description,
            'metadata' => !empty($metadata) ? json_encode($metadata) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function isPayMongoTopupMethod(string $paymentMethod): bool
    {
        return in_array($paymentMethod, ['ginto_pay_qr', 'ginto_pay_card'], true);
    }

    private function calculateTopupFeeAmount(string $paymentMethod, float $creditAmount): float
    {
        $creditAmount = round(max(0, $creditAmount), 2);
        if ($creditAmount <= 0) {
            return 0.00;
        }

        if ($this->isPayMongoTopupMethod($paymentMethod)) {
            return self::MIN_TOPUP_FEE_PHP;
        }

        if ($paymentMethod === 'paypal') {
            return round(self::MIN_TOPUP_FEE_PHP + ($creditAmount * self::PAYPAL_TOPUP_SERVICE_FEE_PERCENT), 2);
        }

        return 0.00;
    }

    private function topupFeePolicyName(string $paymentMethod, float $feeAmount): string
    {
        if ($feeAmount <= 0) {
            return 'no_fee';
        }

        if ($this->isPayMongoTopupMethod($paymentMethod)) {
            return 'fixed_minimum_fee';
        }

        if ($paymentMethod === 'paypal') {
            return 'fixed_plus_percent_fee';
        }

        return 'fixed_minimum_fee';
    }

    private function addOrderHistory(int $orderId, ?int $actorUserId, string $actorType, ?string $fromStatus, string $toStatus, string $message): void
    {
        $this->db->insert('mall_order_status_history', [
            'order_id' => $orderId,
            'actor_user_id' => $actorUserId ?: null,
            'actor_type' => $actorType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function createMallNotification(int $userId, ?int $actorUserId, string $type, string $message, array $context = []): void
    {
        if ($userId <= 0) {
            return;
        }
        $this->db->insert('notifications', [
            'user_id' => $userId,
            'actor_user_id' => $actorUserId,
            'type' => $type,
            'message' => $message,
            'context_json' => !empty($context) ? json_encode($context) : null,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function emailSellerPaidOrder(int $orderId): void
    {
        $orders = $this->hydrateOrders([$this->db->get('mall_orders', '*', ['id' => $orderId])]);
        $order = $orders[0] ?? null;
        if (!$order || empty($order['seller']['email'])) {
            return;
        }

        $subject = 'New paid mall order: ' . $order['order_code'];
        $itemsHtml = '';
        foreach ($order['items'] as $item) {
            $itemsHtml .= '<li>' . htmlspecialchars((string)$item['title_snapshot'], ENT_QUOTES, 'UTF-8') . ' x ' . (int)$item['quantity'] . '</li>';
        }
        $body = '<p>You received a new paid order on Ginto Mall.</p>'
            . '<p><strong>Order:</strong> ' . htmlspecialchars((string)$order['order_code'], ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Total:</strong> ' . number_format((float)$order['buyer_total_amount'], 2) . ' ' . htmlspecialchars((string)$order['currency'], ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Buyer:</strong> ' . htmlspecialchars((string)($order['buyer']['fullname'] ?: $order['buyer']['username']), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<ul>' . $itemsHtml . '</ul>'
            . '<p>Open your seller orders dashboard to prepare this shipment.</p>';
        MailHelper::send((string)$order['seller']['email'], $subject, $body);
    }

    private function emailOrderStatusUpdate(int $userId, string $subjectLine, array $order, string $message = ''): void
    {
        $user = $this->db->get('users', ['email', 'fullname', 'username'], ['id' => $userId]);
        if (!$user || empty($user['email'])) {
            return;
        }
        $body = '<p>' . htmlspecialchars($subjectLine, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Order:</strong> ' . htmlspecialchars((string)$order['order_code'], ENT_QUOTES, 'UTF-8') . '</p>'
            . ($message !== '' ? '<p>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>' : '');
        MailHelper::send((string)$user['email'], 'Ginto Mall order update', $body);
    }

    private function sanitizeSlug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9-]+/', '-', $value) ?? '';
        $value = trim($value, '-');
        return substr($value, 0, 64);
    }

    private function uniqueStorefrontSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $i = 2;
        while ($this->db->has('seller_storefronts', ['slug' => $slug])) {
            $slug = substr($baseSlug, 0, 56) . '-' . $i;
            $i++;
        }
        return $slug;
    }

    private function sanitizeShipping(array $shipping): array
    {
        return [
            'full_name' => trim(strip_tags((string)($shipping['full_name'] ?? ''))),
            'phone' => preg_replace('/[^0-9+\-\s]/', '', (string)($shipping['phone'] ?? '')),
            'address_line1' => trim(strip_tags((string)($shipping['address_line1'] ?? ''))),
            'address_line2' => trim(strip_tags((string)($shipping['address_line2'] ?? ''))),
            'city' => trim(strip_tags((string)($shipping['city'] ?? ''))),
            'province' => trim(strip_tags((string)($shipping['province'] ?? ''))),
            'postal_code' => preg_replace('/[^0-9A-Za-z\-\s]/', '', (string)($shipping['postal_code'] ?? '')),
            'country' => strtoupper(trim(strip_tags((string)($shipping['country'] ?? 'PH')))),
            'buyer_notes' => trim(strip_tags((string)($shipping['buyer_notes'] ?? ''))),
        ];
    }

    private function firstProductImage(array $product): ?string
    {
        if (!empty($product['images'])) {
            $images = json_decode((string)$product['images'], true);
            if (is_array($images) && !empty($images[0])) {
                return (string)$images[0];
            }
        }
        if (!empty($product['image_path'])) {
            return (string)$product['image_path'];
        }
        return null;
    }

    private function paymentGatewayForMethod(string $paymentMethod): ?string
    {
        return match ($paymentMethod) {
            'ginto_pay_qr', 'ginto_pay_card' => 'paymongo',
            'paypal' => 'paypal',
            default => null,
        };
    }

    private function normalizePaymentMethod(string $paymentMethod): string
    {
        $allowed = ['ginto_pay_qr', 'ginto_pay_card', 'paypal', 'wallet'];
        return in_array($paymentMethod, $allowed, true) ? $paymentMethod : 'wallet';
    }

    private function generateOrderCode(): string
    {
        return 'GM' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
    }

    private function generateSessionRef(string $prefix): string
    {
        return $prefix . strtoupper(substr(bin2hex(random_bytes(10)), 0, 20));
    }

    private function humanizeStatus(string $status): string
    {
        return str_replace('_', ' ', ucfirst($status));
    }
}