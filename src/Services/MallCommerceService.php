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
    private const PAYPAL_TOPUP_SERVICE_FEE_PERCENT = 0.07;

    // ── Order constraints ────────────────────────────────────────────────
    /** Minimum order subtotal in PHP */
    private const MIN_ORDER_SUBTOTAL_PHP = 50.00;

    // ── Gateway processing fees ──────────────────────────────────────────
    private const PROCESSING_FEE_PAYMONGO   = 25.00;  // flat fee for QR / Card
    private const PROCESSING_FEE_PAYPAL_BASE = 100.00; // flat fee per PayPal sale
    private const PROCESSING_FEE_PAYPAL_SMALL = 50.00; // extra charge if order < ₱500
    private const PROCESSING_FEE_PAYPAL_SMALL_THRESHOLD = 500.00;
    private const PROCESSING_FEE_PAYPAL_LARGE_PCT = 0.10; // 10% if order > ₱1,000
    private const PROCESSING_FEE_PAYPAL_LARGE_THRESHOLD = 1000.00;

    // ── Delivery fee constants (Grab premium-style) ──────────────────────
    private const DELIVERY_BASE_FEE         = 49.00;  // base fare (same barangay)
    private const DELIVERY_PER_KM_PER_ITEM  = 50.00;  // ₱50 per km per item
    private const DELIVERY_PLATFORM_MARKUP  = 0.50;   // +50% platform fee on delivery

    // ── Compulsory platform markup ────────────────────────────────────────
    private const COMPULSORY_MARKUP_RATE    = 15.00;   // 15% on all products

    private const PRODUCT_PRICING_DEFAULTS = [
        'hands_off'        => 12.00,   // standard listing, minimal promotion
        'active_discovery' => 25.00,   // active platform promotion
        'full_service'     => 35.00,   // maximum promotion
        'referral'         => 15.00,   // referral network program
        'markup'           =>  0.00,   // seller-driven markup, no separate platform fee
        'standard'         => 10.00,   // legacy alias
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

    /**
     * Update a seller's storefront record. Handles image uploads via B2 or local storage.
     * @param array $files  Subset of $_FILES — keys 'banner_image' and/or 'logo_image'
     */
    public function saveStorefront(int $userId, array $data, array $files = []): array
    {
        $storefront = $this->ensureStorefront($userId);
        $storeId    = (int)$storefront['id'];

        $displayName = substr(trim((string)($data['display_name'] ?? '')), 0, 100);
        if ($displayName === '') {
            throw new \RuntimeException('Store name is required.');
        }

        $slug = $this->sanitizeSlug((string)($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = $storefront['slug'];
        }

        // Ensure slug not taken by another storefront
        $taken = $this->db->get('seller_storefronts', ['id'], ['slug' => $slug]);
        if ($taken && (int)$taken['id'] !== $storeId) {
            throw new \RuntimeException('That store URL is already taken. Please choose a different one.');
        }

        $description = substr(trim((string)($data['description'] ?? '')), 0, 1000);
        $isActive    = isset($data['is_active']) ? 1 : 0;

        $update = [
            'display_name' => $displayName,
            'slug'         => $slug,
            'description'  => $description,
            'is_active'    => $isActive,
            'updated_at'   => date('Y-m-d H:i:s'),
        ];

        // Banner image upload
        if (!empty($files['banner_image']['tmp_name']) && $files['banner_image']['error'] === UPLOAD_ERR_OK) {
            $update['banner_image'] = $this->uploadStorefrontAsset($files['banner_image'], 'banners');
        }
        // Logo image upload
        if (!empty($files['logo_image']['tmp_name']) && $files['logo_image']['error'] === UPLOAD_ERR_OK) {
            $update['logo_image'] = $this->uploadStorefrontAsset($files['logo_image'], 'logos');
        }

        $this->db->update('seller_storefronts', $update, ['id' => $storeId]);
        return $this->db->get('seller_storefronts', '*', ['id' => $storeId]) ?: [];
    }

    private function uploadStorefrontAsset(array $file, string $folder): string
    {
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '-', basename($file['name']));
        if (\Ginto\Helpers\B2Helper::isEnabled()) {
            $bytes    = file_get_contents($file['tmp_name']);
            $path     = 'mall/storefronts/' . $folder . '/' . uniqid() . '_' . $name;
            $mimeType = $file['type'] ?: 'image/jpeg';
            return \Ginto\Helpers\B2Helper::upload($bytes, $path, $mimeType);
        }
        $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 2) . '/../storage';
        $dir = $storagePath . '/mall/storefronts/' . $folder . '/';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $target = $dir . uniqid() . '_' . $name;
        move_uploaded_file($file['tmp_name'], $target);
        chmod($target, 0640);
        return str_replace($storagePath, '/storage', $target);
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
            'ORDER' => ['created_at' => 'DESC'],
            'LIMIT' => [0, $limit],
        ]) ?: [];
    }

    public function getMallUnreadNotificationCount(int $userId): int
    {
        $count = $this->db->count('notifications', [
            'user_id' => $userId,
            'is_read' => 0,
        ]);

        return is_numeric($count) ? (int)$count : 0;
    }

    public function markMallNotificationsRead(int $userId): void
    {
        $this->db->update('notifications', ['is_read' => 1], [
            'user_id' => $userId,
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
            foreach ($bundle['orders'] as $orderSummary) {
                $sellerId = (int)($orderSummary['seller_id'] ?? 0);
                if ($sellerId <= 0) {
                    continue;
                }
                $firstItem = is_array($orderSummary['items'] ?? null) ? ($orderSummary['items'][0] ?? null) : null;
                $context = [];
                if (is_array($firstItem)) {
                    $context['product_id'] = (int)($firstItem['product_id'] ?? 0);
                    $context['product_title'] = (string)($firstItem['title'] ?? '');
                }
                $context['storefront_slug'] = (string)($orderSummary['storefront_slug'] ?? '');
                $this->recordStorefrontImpression($sellerId, $buyerId, 'added_to_cart', $context);
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
        $items = [];

        if (($session['purpose'] ?? '') === 'wallet_topup') {
            // Wallet top-up: single item for the gross amount
            $grossAmount = round((float)$session['amount_total'], 2);
            $items[] = [
                'name' => 'Ginto Wallet Top-Up',
                'quantity' => 1,
                'unit_price' => $grossAmount,
            ];
        } else {
            // Product checkout: extract items from orders
            $orders = $payload['orders'] ?? [];
            foreach ($orders as $order) {
                foreach (($order['items'] ?? []) as $item) {
                    $items[] = [
                        'name' => $item['title'],
                        'quantity' => (int)$item['quantity'],
                        'unit_price' => (float)$item['charged_unit_price'],
                    ];
                }
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

    public function recordStorefrontImpression(int $sellerId, ?int $viewerUserId, string $activity = 'viewed', array $context = []): void
    {
        if ($sellerId <= 0) {
            return;
        }
        if (!empty($viewerUserId) && $viewerUserId === $sellerId) {
            return;
        }

        $buyer = null;
        if (!empty($viewerUserId)) {
            $buyer = $this->db->get('users', ['id', 'username', 'fullname', 'public_id'], ['id' => (int)$viewerUserId]);
        }

        $buyerLabel = 'A visitor';
        $buyerIdent = null;
        if (!empty($buyer)) {
            $buyerLabel = trim((string)($buyer['fullname'] ?: $buyer['username'] ?: ('Buyer #' . (int)$buyer['id'])));
            $buyerIdent = trim((string)($buyer['public_id'] ?: $buyer['username'] ?: ''));
        }

        $productId = (int)($context['product_id'] ?? 0);
        $productTitle = trim((string)($context['product_title'] ?? ''));
        $productSlug = '';
        if ($productId > 0) {
            $p = $this->db->get('products', ['slug', 'title'], ['id' => $productId]);
            if ($productTitle === '') {
                $productTitle = trim((string)($p['title'] ?? ''));
            }
            $productSlug = trim((string)($p['slug'] ?? ''));
        }

        $notifContext = [
            'activity' => $activity,
            'privacy_mode' => 'seller_context',
        ];
        if (!empty($viewerUserId)) {
            $notifContext['buyer_id'] = (int)$viewerUserId;
        }
        if ($buyerLabel !== '') {
            $notifContext['buyer_name'] = $buyerLabel;
        }
        if ($buyerIdent !== null && $buyerIdent !== '') {
            $notifContext['buyer_link'] = '/user/profile/' . rawurlencode($buyerIdent);
        }
        if ($productId > 0) {
            $notifContext['product_id'] = $productId;
        }
        if ($productTitle !== '') {
            $notifContext['product_title'] = $productTitle;
        }
        if ($productSlug !== '') {
            $notifContext['product_link'] = '/mall/product/' . rawurlencode($productSlug);
            $notifContext['link'] = '/mall/product/' . rawurlencode($productSlug);
        } else {
            $notifContext['link'] = '/marketplace/sellers/storefront';
        }

        $message = match ($activity) {
            'added_to_cart' => $buyerLabel . ' added ' . ($productTitle !== '' ? ('"' . $productTitle . '"') : 'items') . ' to cart but has not paid yet.',
            default => $buyerLabel . ' viewed your storefront but has not paid yet.',
        };

        $this->createMallNotification(
            $sellerId,
            !empty($viewerUserId) ? $viewerUserId : null,
            'mall_seller_impression',
            $message,
            $notifContext
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

        // Enhanced notifications based on status
        try {
            $pushService = new MallPushService($this->db);
            $buyer = $this->db->get('users', ['fullname', 'username'], ['id' => (int)$order['buyer_id']]);
            $buyerName = trim(($buyer['fullname'] ?? '') ?: ($buyer['username'] ?? 'Buyer'));

            if ($toStatus === 'in_transit') {
                // Seller shipped the order — email buyer + owner
                $this->emailShipmentOnTheWay($orderId);
                $pushService->notifyAdminDeliveryStatus($orderId, 'In Transit', "Order {$order['order_code']} shipped to {$buyerName}");
                $this->logAdminActivity('order_shipped', $actorUserId, (int)$order['buyer_id'], $orderId, null, ['status' => $toStatus]);
            } elseif ($toStatus === 'delivered') {
                // Item delivered — email buyer + owner + notify seller about payment processing
                $this->emailDeliveryCompleted($orderId);
                $sellerNet = (float)($order['seller_net_amount'] ?? 0);
                $pushService->notifySellerPaymentPending((int)$order['seller_id'], $orderId, $sellerNet);
                $pushService->notifyAdminDeliveryStatus($orderId, 'Delivered', "Order {$order['order_code']} delivered to {$buyerName}");
                $this->logAdminActivity('order_delivered', $actorUserId, (int)$order['buyer_id'], $orderId, null, [
                    'status' => $toStatus,
                    'seller_net' => $sellerNet,
                ]);
            } elseif ($toStatus === 'processing') {
                $pushService->notifyAdminDeliveryStatus($orderId, 'Processing', "Seller is preparing order {$order['order_code']}");
                $this->logAdminActivity('order_processing', $actorUserId, (int)$order['buyer_id'], $orderId, null, ['status' => $toStatus]);
            }
        } catch (\Throwable $e) {
            error_log('[MallCommerceService] updateOrderStatus notifications error: ' . $e->getMessage());
        }
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

        // -- Minimum order check -------------------------------------------
        $grandSubtotal = array_sum(array_column($grouped, 'subtotal'));
        if ($grandSubtotal < self::MIN_ORDER_SUBTOTAL_PHP && $currency === 'PHP') {
            throw new \RuntimeException('Minimum order amount is ₱' . number_format(self::MIN_ORDER_SUBTOTAL_PHP, 2) . '. Your subtotal is ₱' . number_format($grandSubtotal, 2) . '.');
        }

        // -- Buyer's barangay for delivery fee (from session or address) ---
        $buyerBarangayId = !empty($_SESSION['buyer_barangay_id']) ? (int)$_SESSION['buyer_barangay_id'] : null;

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

            // Grab-style delivery fee based on seller→buyer distance (per item)
            $physicalItemCount = 0;
            foreach ($group['items'] as $gi) {
                $pt = $this->db->get('products', 'product_type', ['id' => $gi['product_id']]);
                if (in_array($pt, ['physical', 'liquid', null, ''], true)) {
                    $physicalItemCount += (int)($gi['quantity'] ?? 1);
                }
            }
            $deliveryFee = $physicalItemCount > 0
                ? $this->calculateDeliveryFee($sellerId, $buyerBarangayId, $physicalItemCount)
                : 0.00;
            $grouped[$sellerId]['delivery_fee'] = $deliveryFee;

            // Buyer pays product subtotal + shipping fee + delivery fee
            $grouped[$sellerId]['buyer_total'] += $shippingResult['estimated_fee'] + $deliveryFee;
        }

        $now = date('Y-m-d H:i:s');
        $orderIds = [];
        $orders = [];
        $checkoutRef = $this->generateSessionRef('CHK');

        $this->db->pdo->beginTransaction();
        try {
            foreach ($grouped as $sellerId => $group) {
                // Processing fee depends on buyer total before this fee is added
                $processingFee = $this->calculateProcessingFee($paymentMethod, $group['buyer_total']);
                $buyerTotalWithFees = round($group['buyer_total'] + $processingFee, 2);
                $payoutEta = $this->sellerPayoutEta($paymentMethod);

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
                    'processing_fee_amount' => $processingFee,
                    'delivery_fee_amount' => round($group['delivery_fee'] ?? 0.00, 2),
                    'seller_net_amount' => round($group['seller_net'], 2),
                    'buyer_total_amount' => $buyerTotalWithFees,
                    'shipping_fee_estimated' => round($group['shipping_fee'] ?? 0.00, 2),
                    'shipping_zone' => $group['shipping_zone'] ?? null,
                    'chargeable_weight_kg' => $group['chargeable_weight'] ?? null,
                    'shipping_dimensions_json' => $group['shipping_dimensions_json'] ?? null,
                    'seller_payout_eta' => $payoutEta,
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
                    'buyer_total' => $buyerTotalWithFees,
                    'shipping_fee' => round($group['shipping_fee'] ?? 0.00, 2),
                    'delivery_fee' => round($group['delivery_fee'] ?? 0.00, 2),
                    'processing_fee' => $processingFee,
                    'shipping_zone' => $group['shipping_zone'] ?? null,
                    'chargeable_weight_kg' => $group['chargeable_weight'] ?? null,
                    'seller_payout_eta' => $payoutEta,
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
        $pricingModel = (string)($product['pricing_model'] ?? 'standard');
        // Normalise legacy values
        if (in_array($pricingModel, ['hands_off', 'active_discovery', 'full_service'], true)) {
            $pricingModel = 'standard';
        }
        if (!array_key_exists($pricingModel, self::PRODUCT_PRICING_DEFAULTS)) {
            $pricingModel = 'standard';
        }

        $pricingRate = isset($product['pricing_rate']) ? (float)$product['pricing_rate'] : self::PRODUCT_PRICING_DEFAULTS[$pricingModel];
        $markupRate  = isset($product['markup_rate'])  ? (float)$product['markup_rate']  : 0.00;

        // Compulsory 15% platform markup on ALL products
        $compulsoryMarkup = self::COMPULSORY_MARKUP_RATE;
        $effectiveMarkup = max($markupRate, $compulsoryMarkup);

        if ($pricingModel === 'markup') {
            // Legacy markup mode: buyer price = base * (1 + markup%)
            $effectiveMarkup = min(200.00, max($compulsoryMarkup, $markupRate ?: $compulsoryMarkup));
            $chargedUnitPrice   = round($baseUnitPrice * (1 + ($effectiveMarkup / 100)), 2);
            $platformFeePerUnit = round($chargedUnitPrice - $baseUnitPrice, 2);
            $sellerNetPerUnit   = $baseUnitPrice;
            $pricingRate = 0.00;
        } else {
            // standard / referral: apply compulsory markup first, then platform fee
            $pricingRate = max(0.00, min(100.00, $pricingRate ?: self::PRODUCT_PRICING_DEFAULTS[$pricingModel]));
            $effectiveMarkup = min(200.00, max($compulsoryMarkup, $markupRate));
            $chargedUnitPrice   = round($baseUnitPrice * (1 + ($effectiveMarkup / 100)), 2);
            $platformFeePerUnit = round($chargedUnitPrice * ($pricingRate / 100), 2);
            $sellerNetPerUnit   = round($chargedUnitPrice - $platformFeePerUnit, 2);
        }

        return [
            'pricing_model' => $pricingModel,
            'pricing_rate' => $pricingRate,
            'markup_rate' => $effectiveMarkup,
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

        // Enhanced purchase notifications: per-seller bell + FCM + email
        try {
            $pushService = new MallPushService($this->db);
            $buyer = $this->db->get('users', ['id', 'fullname', 'username', 'email'], ['id' => (int)$order['buyer_id']]);
            $buyerName = trim(($buyer['fullname'] ?? '') ?: ($buyer['username'] ?? 'Buyer'));
            $totalAmount = number_format((float)($order['buyer_total_amount'] ?? 0), 2);

            // Notify buyer about successful purchase
            $pushService->notifyBuyerPurchaseSuccess((int)$order['buyer_id'], $orderId, $totalAmount);

            // Group items by seller and notify each seller
            $itemsBySeller = [];
            foreach ($items as $item) {
                $sid = (int)($item['seller_id'] ?? $order['seller_id']);
                $itemsBySeller[$sid][] = $item;
            }
            $pushService->notifySellerPurchase($itemsBySeller, [
                'order_id' => $orderId,
                'buyer_name' => $buyerName,
            ]);

            // Admin notification for ledgering
            $pushService->notifyAdminPurchase($orderId, (int)$order['buyer_id'], $buyerName, (float)($order['buyer_total_amount'] ?? 0), count($items));

            // Admin activity log
            $this->logAdminActivity('purchase_completed', (int)$order['buyer_id'], (int)$order['seller_id'], $orderId, null, [
                'buyer_name' => $buyerName,
                'total_amount' => (float)($order['buyer_total_amount'] ?? 0),
                'item_count' => count($items),
                'payment_method' => $paymentMethod,
            ]);

            // Email to owner about successful purchase
            $this->emailPurchaseNotification($orderId);

            // Save buyer's address for future checkouts
            $shippingData = json_decode($order['shipping_address_json'] ?? '{}', true);
            if (is_array($shippingData) && !empty($shippingData['full_name'])) {
                $this->saveBuyerAddress((int)$order['buyer_id'], $shippingData, $paymentMethod);
            }
        } catch (\Throwable $e) {
            error_log('[MallCommerceService] markOrderPaid notification error: ' . $e->getMessage());
        }
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

        // Realtime push to Android/web devices so notifications arrive live.
        try {
            $push = new \Ginto\Services\MallPushService($this->db);
            $push->pushRealtimeNotification($userId, $type, $message, $context);
        } catch (\Throwable $_) {
            // Non-blocking: notification persistence succeeded already.
        }
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
        $payoutEtaHtml = '';
        if (!empty($order['seller_payout_eta'])) {
            $payoutEtaHtml = '<p><strong>Payout timing:</strong> ' . htmlspecialchars((string)$order['seller_payout_eta'], ENT_QUOTES, 'UTF-8') . '</p>';
        }
        $body = '<p>You received a new paid order on Ginto Mall.</p>'
            . '<p><strong>Order:</strong> ' . htmlspecialchars((string)$order['order_code'], ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Total:</strong> ' . number_format((float)$order['buyer_total_amount'], 2) . ' ' . htmlspecialchars((string)$order['currency'], ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Buyer:</strong> ' . htmlspecialchars((string)($order['buyer']['fullname'] ?: $order['buyer']['username']), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<ul>' . $itemsHtml . '</ul>'
            . $payoutEtaHtml
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

    /** Email to oliverbob.lagumen@gmail.com about every successful purchase. */
    private function emailPurchaseNotification(int $orderId): void
    {
        try {
            $orders = $this->hydrateOrders([$this->db->get('mall_orders', '*', ['id' => $orderId])]);
            $order = $orders[0] ?? null;
            if (!$order) return;

            $buyerName = htmlspecialchars(trim(($order['buyer']['fullname'] ?? '') ?: ($order['buyer']['username'] ?? 'Buyer')), ENT_QUOTES, 'UTF-8');
            $sellerName = htmlspecialchars(trim(($order['seller']['fullname'] ?? '') ?: ($order['seller']['username'] ?? 'Seller')), ENT_QUOTES, 'UTF-8');
            $itemsHtml = '';
            foreach ($order['items'] as $item) {
                $itemsHtml .= '<li>' . htmlspecialchars((string)$item['title_snapshot'], ENT_QUOTES, 'UTF-8') . ' x' . (int)$item['quantity']
                    . ' — ₱' . number_format((float)$item['line_subtotal'], 2) . '</li>';
            }
            $total = number_format((float)$order['buyer_total_amount'], 2);
            $orderCode = htmlspecialchars((string)$order['order_code'], ENT_QUOTES, 'UTF-8');

            $body = "<h2>🛒 New Purchase on Ginto Mall</h2>"
                . "<p><strong>Order:</strong> {$orderCode}</p>"
                . "<p><strong>Buyer:</strong> {$buyerName}</p>"
                . "<p><strong>Seller:</strong> {$sellerName}</p>"
                . "<p><strong>Total:</strong> ₱{$total}</p>"
                . "<p><strong>Items:</strong></p><ul>{$itemsHtml}</ul>"
                . "<p><strong>Payment:</strong> " . htmlspecialchars((string)$order['payment_method'], ENT_QUOTES, 'UTF-8') . "</p>"
                . "<p><em>Paid at: " . htmlspecialchars((string)($order['paid_at'] ?? date('Y-m-d H:i:s')), ENT_QUOTES, 'UTF-8') . "</em></p>";

            MailHelper::send('oliverbob.lagumen@gmail.com', "Ginto Purchase: {$orderCode} by {$buyerName}", $body);

            // Also email the buyer
            if (!empty($order['buyer']['email'])) {
                $buyerBody = "<h2>✅ Your Ginto Mall Purchase Confirmed!</h2>"
                    . "<p>Thank you for your purchase, {$buyerName}!</p>"
                    . "<p><strong>Order:</strong> {$orderCode}</p>"
                    . "<p><strong>Total:</strong> ₱{$total}</p>"
                    . "<p><strong>Items:</strong></p><ul>{$itemsHtml}</ul>"
                    . "<p>Track your delivery status at <a href='https://ginto.ai/mall/orders'>My Orders</a>.</p>"
                    . "<p>— Ginto Mall Team</p>";
                MailHelper::send((string)$order['buyer']['email'], "Ginto: Purchase Confirmed — {$orderCode}", $buyerBody);
            }
        } catch (\Throwable $e) {
            error_log('[MallCommerceService] emailPurchaseNotification error: ' . $e->getMessage());
        }
    }

    /** Email notification when seller ships an order. */
    public function emailShipmentOnTheWay(int $orderId): void
    {
        try {
            $orders = $this->hydrateOrders([$this->db->get('mall_orders', '*', ['id' => $orderId])]);
            $order = $orders[0] ?? null;
            if (!$order) return;

            $buyerName = htmlspecialchars(trim(($order['buyer']['fullname'] ?? '') ?: ($order['buyer']['username'] ?? 'Buyer')), ENT_QUOTES, 'UTF-8');
            $orderCode = htmlspecialchars((string)$order['order_code'], ENT_QUOTES, 'UTF-8');
            $body = "<h2>🚚 Your Order is On the Way!</h2>"
                . "<p>Hi {$buyerName}, your order <strong>{$orderCode}</strong> is now on its way to you.</p>"
                . "<p>Track delivery at <a href='https://ginto.ai/mall/orders'>My Orders</a>.</p>"
                . "<p>— Ginto Mall Team</p>";

            if (!empty($order['buyer']['email'])) {
                MailHelper::send((string)$order['buyer']['email'], "Ginto: Your order {$orderCode} is on the way!", $body);
            }
            MailHelper::send('oliverbob.lagumen@gmail.com', "Ginto Shipping: {$orderCode} is on the way", $body);
        } catch (\Throwable $e) {
            error_log('[MallCommerceService] emailShipmentOnTheWay error: ' . $e->getMessage());
        }
    }

    /** Email notification when item is delivered. */
    public function emailDeliveryCompleted(int $orderId): void
    {
        try {
            $orders = $this->hydrateOrders([$this->db->get('mall_orders', '*', ['id' => $orderId])]);
            $order = $orders[0] ?? null;
            if (!$order) return;

            $buyerName = htmlspecialchars(trim(($order['buyer']['fullname'] ?? '') ?: ($order['buyer']['username'] ?? 'Buyer')), ENT_QUOTES, 'UTF-8');
            $orderCode = htmlspecialchars((string)$order['order_code'], ENT_QUOTES, 'UTF-8');
            $body = "<h2>✅ Order Delivered!</h2>"
                . "<p>Hi {$buyerName}, your order <strong>{$orderCode}</strong> has been delivered.</p>"
                . "<p>Please take a photo of the received product and rate your experience at <a href='https://ginto.ai/mall/orders'>My Orders</a>.</p>"
                . "<p>— Ginto Mall Team</p>";

            if (!empty($order['buyer']['email'])) {
                MailHelper::send((string)$order['buyer']['email'], "Ginto: Order {$orderCode} Delivered!", $body);
            }
            MailHelper::send('oliverbob.lagumen@gmail.com', "Ginto Delivered: {$orderCode}", $body);
        } catch (\Throwable $e) {
            error_log('[MallCommerceService] emailDeliveryCompleted error: ' . $e->getMessage());
        }
    }

    /** Log admin activity for ledgering. */
    public function logAdminActivity(string $eventType, ?int $actorUserId, ?int $targetUserId, ?int $orderId, ?int $shipmentId, array $details = []): void
    {
        try {
            $this->db->insert('mall_admin_activity_log', [
                'event_type' => $eventType,
                'actor_user_id' => $actorUserId,
                'target_user_id' => $targetUserId,
                'order_id' => $orderId,
                'shipment_id' => $shipmentId,
                'details' => !empty($details) ? json_encode($details) : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('[MallCommerceService] logAdminActivity error: ' . $e->getMessage());
        }
    }

    /** Upload delivery proof photo. Returns the URL. */
    public function uploadDeliveryProof(int $orderId, int $userId, string $role, array $file, ?string $conditionRating = null, ?string $notes = null, ?float $lat = null, ?float $lng = null, string $photoType = 'product_arrival'): array
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/heic'];
        $mime = $file['type'] ?? '';
        if (!in_array($mime, $allowedTypes, true)) {
            throw new \RuntimeException('Invalid image type. Allowed: JPEG, PNG, WebP, HEIC.');
        }
        if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
            throw new \RuntimeException('Image too large. Max 10MB.');
        }

        $name = preg_replace('/[^a-zA-Z0-9._-]/', '-', basename($file['name']));
        $useB2 = \Ginto\Helpers\B2Helper::isEnabled();
        $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 2) . '/../storage';
        $url = '';

        if ($useB2) {
            try {
                $fileData = file_get_contents($file['tmp_name']);
                $remotePath = 'mall/customer_proof/' . $orderId . '/' . uniqid() . '_' . $name;
                $url = \Ginto\Helpers\B2Helper::upload($fileData, $remotePath, $mime);
            } catch (\Throwable $e) {
                // Fallback to local
                $useB2 = false;
            }
        }
        if (!$useB2) {
            $uploadDir = $storagePath . '/mall/customer_proof/' . $orderId . '/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0750, true);
            $target = $uploadDir . uniqid() . '_' . $name;
            if (!move_uploaded_file($file['tmp_name'], $target)) {
                throw new \RuntimeException('Failed to save photo.');
            }
            chmod($target, 0640);
            $url = str_replace($storagePath, '/storage', $target);
        }

        // Find shipment if exists
        $shipmentId = null;
        $shipment = $this->db->get('delivery_shipments', ['id'], ['order_id' => $orderId]);
        if ($shipment) $shipmentId = (int)$shipment['id'];

        $this->db->insert('delivery_proofs', [
            'order_id' => $orderId,
            'shipment_id' => $shipmentId,
            'uploaded_by_user_id' => $userId,
            'role' => $role,
            'photo_url' => $url,
            'photo_type' => $photoType,
            'condition_rating' => $conditionRating,
            'notes' => $notes ? strip_tags($notes) : null,
            'lat' => $lat,
            'lng' => $lng,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return ['url' => $url, 'id' => (int)$this->db->id()];
    }

    /** Submit product + seller rating for an order item. */
    public function submitRating(int $orderId, int $productId, int $buyerId, int $productRating, int $sellerRating, ?string $reviewText = null): array
    {
        $order = $this->db->get('mall_orders', ['id', 'buyer_id', 'seller_id', 'status'], ['id' => $orderId]);
        if (!$order || (int)$order['buyer_id'] !== $buyerId) {
            throw new \RuntimeException('You can only rate orders you purchased.');
        }

        $productRating = max(1, min(5, $productRating));
        $sellerRating = max(1, min(5, $sellerRating));

        $existing = $this->db->get('product_ratings', ['id'], [
            'order_id' => $orderId,
            'product_id' => $productId,
            'buyer_id' => $buyerId,
        ]);
        if ($existing) {
            $this->db->update('product_ratings', [
                'product_rating' => $productRating,
                'seller_rating' => $sellerRating,
                'review_text' => $reviewText ? strip_tags($reviewText) : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => (int)$existing['id']]);
            return ['id' => (int)$existing['id'], 'updated' => true];
        }

        $this->db->insert('product_ratings', [
            'product_id' => $productId,
            'order_id' => $orderId,
            'buyer_id' => $buyerId,
            'seller_id' => (int)$order['seller_id'],
            'product_rating' => $productRating,
            'seller_rating' => $sellerRating,
            'review_text' => $reviewText ? strip_tags($reviewText) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Update product average rating
        $avgRating = $this->db->avg('product_ratings', 'product_rating', ['product_id' => $productId]);
        if ($avgRating !== null) {
            $this->db->update('products', ['rating' => round((float)$avgRating, 1)], ['id' => $productId]);
        }

        // Notify seller about new review
        try {
            $pushService = new MallPushService($this->db);
            $productTitle = $this->db->get('products', ['title'], ['id' => $productId])['title'] ?? 'a product';
            $pushService->notify(
                [(int)$order['seller_id']],
                "New {$productRating}⭐ review on \"{$productTitle}\"",
                'product_rating',
                ['product_id' => $productId, 'url' => '/marketplace/sellers/products', 'event_key' => 'product_rating']
            );
        } catch (\Throwable $e) {}

        return ['id' => (int)$this->db->id(), 'updated' => false];
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

    /**
     * Compute gateway processing fee by payment method.
     * - PayMongo (QR / Card): flat P25
     * - PayPal: P50 + 7% of order total
     * - Wallet: free
     */
    private function calculateProcessingFee(string $paymentMethod, float $orderTotal): float
    {
        if (in_array($paymentMethod, ['ginto_pay_qr', 'ginto_pay_card'], true)) {
            return self::PROCESSING_FEE_PAYMONGO; // ₱25 flat
        }
        if ($paymentMethod === 'paypal') {
            $fee = self::PROCESSING_FEE_PAYPAL_BASE; // ₱100 base
            if ($orderTotal < self::PROCESSING_FEE_PAYPAL_SMALL_THRESHOLD) {
                $fee += self::PROCESSING_FEE_PAYPAL_SMALL; // +₱50 for small orders
            }
            if ($orderTotal > self::PROCESSING_FEE_PAYPAL_LARGE_THRESHOLD) {
                $fee += round($orderTotal * self::PROCESSING_FEE_PAYPAL_LARGE_PCT, 2); // +10% for large orders
            }
            return round($fee, 2);
        }
        return 0.00; // wallet = free
    }

    /**
     * Grab-style delivery fee estimate based on haversine distance
     * between seller's main-zone barangay and buyer's barangay.
     *
     * Formula: base_fare + per_km * max(0, distance - free_km)
     *          + 5% platform markup + 1% per extra km surcharge
     *
     * Returns 0 if distance cannot be determined (e.g. no GPS data).
     */
    private function calculateDeliveryFee(int $sellerId, ?int $buyerBarangayId, int $itemCount = 1): float
    {
        if (!$buyerBarangayId || $itemCount <= 0) return 0.00;

        // Get seller main zone (is_home = 1)
        $sellerZone = $this->db->get('seller_delivery_zones', ['barangay_id'], [
            'seller_id' => $sellerId,
            'is_home' => 1,
        ]);
        if (!$sellerZone) return 0.00;
        $sellerBarangayId = (int)$sellerZone['barangay_id'];
        if ($sellerBarangayId === $buyerBarangayId) return self::DELIVERY_BASE_FEE;

        // Get lat/lng for both barangays
        $ids = [$sellerBarangayId, $buyerBarangayId];
        $rows = $this->db->select('barangays', ['id', 'lat', 'lng'], ['id' => $ids]);
        $coords = [];
        foreach ($rows ?: [] as $r) {
            $coords[(int)$r['id']] = [(float)$r['lat'], (float)$r['lng']];
        }

        $sCoord = $coords[$sellerBarangayId] ?? null;
        $bCoord = $coords[$buyerBarangayId] ?? null;
        if (!$sCoord || !$bCoord || ($sCoord[0] == 0 && $sCoord[1] == 0) || ($bCoord[0] == 0 && $bCoord[1] == 0)) {
            return self::DELIVERY_BASE_FEE; // fallback: base fee only
        }

        $distKm = $this->haversineKm($sCoord[0], $sCoord[1], $bCoord[0], $bCoord[1]);

        // ₱50 per km per item
        $rawFee = $distKm * self::DELIVERY_PER_KM_PER_ITEM * $itemCount;

        // +50% platform fee on delivery
        $rawFee *= (1 + self::DELIVERY_PLATFORM_MARKUP);

        return round(max($rawFee, self::DELIVERY_BASE_FEE), 2);
    }

    /**
     * Haversine distance between two lat/lng points in km.
     */
    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Payout ETA string based on payment method.
     * PayMongo: 7-12 days; PayPal: 9-12 days; Wallet: 3-5 days.
     */
    private function sellerPayoutEta(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'ginto_pay_qr', 'ginto_pay_card' => '7-12 business days after delivery',
            'paypal' => '9-12 business days after delivery',
            default => '3-5 business days after delivery',
        };
    }

    /**
     * Save or update the buyer's default address from checkout.
     */
    public function saveBuyerAddress(int $userId, array $shipping, string $paymentMethod): void
    {
        $existing = $this->db->get('buyer_saved_addresses', 'id', [
            'user_id' => $userId,
            'is_default' => 1,
        ]);

        $data = [
            'user_id' => $userId,
            'label' => 'Home',
            'is_default' => 1,
            'full_name' => trim(strip_tags((string)($shipping['full_name'] ?? ''))),
            'phone' => preg_replace('/[^0-9+\-\s]/', '', (string)($shipping['phone'] ?? '')),
            'address_line1' => trim(strip_tags((string)($shipping['address_line1'] ?? ''))),
            'address_line2' => trim(strip_tags((string)($shipping['address_line2'] ?? ''))),
            'city' => trim(strip_tags((string)($shipping['city'] ?? ''))),
            'province' => trim(strip_tags((string)($shipping['province'] ?? ''))),
            'postal_code' => preg_replace('/[^0-9A-Za-z\-\s]/', '', (string)($shipping['postal_code'] ?? '')),
            'country' => strtoupper(trim(strip_tags((string)($shipping['country'] ?? 'PH')))),
            'payment_method' => $paymentMethod,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $this->db->update('buyer_saved_addresses', $data, ['id' => $existing]);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert('buyer_saved_addresses', $data);
        }

        // Also update users table for backwards compatibility
        $this->db->update('users', [
            'shipping_address_json' => json_encode($shipping),
        ], ['id' => $userId]);
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

    // ── Seller stats: sales, commissions, earnings, pending payouts ──────────

    public function getSellerStats(int $userId): array
    {
        $stats = [
            'gross_sales'       => 0.0,
            'net_earnings'      => 0.0,
            'order_count'       => 0,
            'total_commissions' => 0.0,
            'pending_payout'    => 0.0,
        ];

        try {
            $stmt = $this->db->pdo->prepare(
                "SELECT COALESCE(SUM(buyer_total_amount),0) AS gross_sales,
                        COALESCE(SUM(seller_net_amount),0)  AS net_earnings,
                        COUNT(*) AS order_count
                   FROM mall_orders
                  WHERE seller_id = :uid AND payment_status = 'paid'"
            );
            $stmt->execute([':uid' => $userId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $stats['gross_sales']  = (float)$row['gross_sales'];
                $stats['net_earnings'] = (float)$row['net_earnings'];
                $stats['order_count']  = (int)$row['order_count'];
            }
        } catch (\Throwable $e) {}

        try {
            $stmt = $this->db->pdo->prepare(
                "SELECT COALESCE(SUM(amount),0) AS total
                   FROM commissions
                  WHERE user_id = :uid AND status != 'cancelled'"
            );
            $stmt->execute([':uid' => $userId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $stats['total_commissions'] = (float)$row['total'];
            }
        } catch (\Throwable $e) {}

        try {
            $stmt = $this->db->pdo->prepare(
                "SELECT COALESCE(SUM(amount),0) AS pending
                   FROM mall_seller_payouts
                  WHERE user_id = :uid AND status IN ('pending','processing')"
            );
            $stmt->execute([':uid' => $userId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $stats['pending_payout'] = (float)$row['pending'];
            }
        } catch (\Throwable $e) {}

        return $stats;
    }

    public function getPayoutAccount(int $userId): ?array
    {
        try {
            return $this->db->get('mall_payout_accounts', '*', [
                'user_id'    => $userId,
                'is_primary' => 1,
                'ORDER'      => ['updated_at' => 'DESC'],
            ]) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getSalesList(int $userId): array
    {
        try {
            $stmt = $this->db->pdo->prepare(
                "SELECT o.id, o.buyer_total_amount, o.seller_net_amount, o.payment_status,
                        o.created_at, o.updated_at,
                        p.title AS product_title, p.image_path AS product_image,
                        u.name AS buyer_name
                   FROM mall_orders o
                   LEFT JOIN mall_products p ON p.id = o.product_id
                   LEFT JOIN users u ON u.id = o.buyer_id
                  WHERE o.seller_id = :uid
                  ORDER BY o.created_at DESC
                  LIMIT 200"
            );
            $stmt->execute([':uid' => $userId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) { return []; }
    }

    public function getCommissionsList(int $userId): array
    {
        try {
            $stmt = $this->db->pdo->prepare(
                "SELECT c.id, c.amount, c.status, c.created_at, c.source_id,
                        u.name AS from_user_name
                   FROM commissions c
                   LEFT JOIN users u ON u.id = c.from_user_id
                  WHERE c.user_id = :uid
                  ORDER BY c.created_at DESC
                  LIMIT 200"
            );
            $stmt->execute([':uid' => $userId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) { return []; }
    }

    public function getPendingPayoutsList(int $userId): array
    {
        try {
            $stmt = $this->db->pdo->prepare(
                "SELECT sp.id, sp.amount, sp.source_type, sp.status, sp.scheduled_at,
                        sp.sent_at, sp.reference, sp.note, sp.created_at,
                        pa.institution_name, pa.account_holder_name, pa.account_number, pa.account_type
                   FROM mall_seller_payouts sp
                   LEFT JOIN mall_payout_accounts pa ON pa.id = sp.payout_account_id
                  WHERE sp.user_id = :uid
                  ORDER BY sp.created_at DESC
                  LIMIT 200"
            );
            $stmt->execute([':uid' => $userId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) { return []; }
    }

    public function getAllPayoutAccounts(int $userId): array
    {
        try {
            return $this->db->select('mall_payout_accounts', '*', [
                'user_id' => $userId,
                'ORDER'   => ['is_primary' => 'DESC', 'updated_at' => 'DESC'],
            ]) ?: [];
        } catch (\Throwable $e) { return []; }
    }

    public function setPrimaryPayoutAccount(int $userId, int $accountId): void
    {
        try {
            $this->db->pdo->prepare("UPDATE mall_payout_accounts SET is_primary = 0 WHERE user_id = :uid")
                ->execute([':uid' => $userId]);
            $this->db->pdo->prepare("UPDATE mall_payout_accounts SET is_primary = 1 WHERE id = :id AND user_id = :uid")
                ->execute([':id' => $accountId, ':uid' => $userId]);
        } catch (\Throwable $e) {}
    }

    public function deletePayoutAccount(int $userId, int $accountId): void
    {
        try {
            $this->db->delete('mall_payout_accounts', ['id' => $accountId, 'user_id' => $userId]);
        } catch (\Throwable $e) {}
    }

    public function savePayoutAccount(int $userId, string $accountType, string $institutionName, string $holderName, string $accountNumber): array
    {
        $existing = null;
        try {
            $existing = $this->db->get('mall_payout_accounts', ['id'], [
                'user_id'    => $userId,
                'is_primary' => 1,
            ]);
        } catch (\Throwable $e) {}

        $now  = date('Y-m-d H:i:s');
        $data = [
            'account_type'        => in_array($accountType, ['bank', 'ewallet'], true) ? $accountType : 'bank',
            'institution_name'    => substr(strip_tags($institutionName), 0, 255),
            'account_holder_name' => substr(strip_tags($holderName), 0, 255),
            'account_number'      => substr(preg_replace('/[^a-zA-Z0-9\-\s]/', '', $accountNumber), 0, 100),
            'updated_at'          => $now,
        ];

        if ($existing) {
            $this->db->update('mall_payout_accounts', $data, ['id' => (int)$existing['id']]);
            return ['id' => (int)$existing['id']];
        }

        $data['user_id']    = $userId;
        $data['is_primary'] = 1;
        $data['created_at'] = $now;
        $this->db->insert('mall_payout_accounts', $data);
        return ['id' => (int)$this->db->id()];
    }
}