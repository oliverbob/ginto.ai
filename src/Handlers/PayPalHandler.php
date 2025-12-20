<?php

declare(strict_types=1);

namespace App\Handlers;

use PhpMcp\Server\Attributes\McpTool;
use Ginto\Controllers\UserController;

/**
 * PayPalHandler
 * 
 * Handles PayPal MCP tool invocations for payment automation.
 * This handler bridges local tool calls to the PayPal MCP server.
 * 
 * SECURITY: All methods require admin access (role_id 1 or 2).
 * 
 * Supported operations:
 * - Product catalog management (create, list, show)
 * - Subscription plans & billing (create plans, subscriptions, cancel, update)
 * - Invoicing (create, send, remind, cancel)
 * - Orders & payments (create, capture, refund)
 * - Reporting & disputes
 * - Shipment tracking
 */
class PayPalHandler
{
    private string $environment;
    private ?string $accessToken;
    private string $baseUrl;

    /** @var array Allowed caller contexts that bypass session check */
    private static array $allowedContexts = [
        'webhook',      // PayPal webhook callbacks
        'cron',         // Scheduled billing tasks
        'internal_api', // Internal service calls
    ];

    public function __construct()
    {
        $this->environment = getenv('PAYPAL_ENVIRONMENT') ?: 'SANDBOX';
        $this->accessToken = getenv('PAYPAL_ACCESS_TOKEN') ?: null;
        $this->baseUrl = $this->environment === 'PRODUCTION'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * Check if current request has admin access or is from allowed context.
     * 
     * @param string|null $context Optional caller context (webhook, cron, internal_api)
     * @param string|null $apiKey Optional API key for programmatic access
     * @return bool
     */
    public static function hasAccess(?string $context = null, ?string $apiKey = null): bool
    {
        // Allow specific trusted contexts
        if ($context && in_array($context, self::$allowedContexts, true)) {
            // Verify API key for programmatic contexts
            $expectedKey = getenv('PAYPAL_INTERNAL_API_KEY');
            if ($expectedKey && $apiKey === $expectedKey) {
                return true;
            }
            // Allow webhook context without key if it's verified elsewhere
            if ($context === 'webhook') {
                return true;
            }
        }

        // Check admin session via UserController
        if (class_exists(UserController::class)) {
            return UserController::isAdmin();
        }

        // Fallback: check session directly
        $session = $_SESSION ?? [];
        return !empty($session) && (
            !empty($session['is_admin']) ||
            (!empty($session['role_id']) && in_array((int)$session['role_id'], [1, 2], true)) ||
            (!empty($session['role']) && strtolower($session['role']) === 'admin')
        );
    }

    /**
     * Require admin access, throw exception if not authorized.
     * 
     * @param string|null $context Caller context
     * @param string|null $apiKey API key for programmatic access
     * @throws \RuntimeException If access denied
     */
    private function requireAccess(?string $context = null, ?string $apiKey = null): void
    {
        if (!self::hasAccess($context, $apiKey)) {
            throw new \RuntimeException('Access denied: PayPal operations require admin privileges.');
        }
    }

    /**
     * Create a handler instance for internal/programmatic use with API key authentication.
     * Use this for cron jobs, webhooks, or internal service calls.
     * 
     * @param string $apiKey The PAYPAL_INTERNAL_API_KEY
     * @param string $context The caller context (webhook, cron, internal_api)
     * @return self|null Returns handler if authenticated, null otherwise
     */
    public static function forInternalUse(string $apiKey, string $context = 'internal_api'): ?self
    {
        if (!self::hasAccess($context, $apiKey)) {
            return null;
        }
        return new self();
    }

    /**
     * Get OAuth access token using client credentials
     */
    private function getAccessToken(): ?string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $clientId = getenv('PAYPAL_CLIENT_ID');
        $clientSecret = getenv('PAYPAL_CLIENT_SECRET');

        if (!$clientId || !$clientSecret) {
            return null;
        }

        $ch = curl_init($this->baseUrl . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Language: en_US',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_USERPWD => $clientId . ':' . $clientSecret,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        $data = json_decode($response, true);
        $this->accessToken = $data['access_token'] ?? null;

        return $this->accessToken;
    }

    /**
     * Make authenticated API request to PayPal
     * Automatically enforces access control before making the request.
     */
    private function apiRequest(string $method, string $endpoint, ?array $data = null, ?string $context = null, ?string $apiKey = null): array
    {
        // Enforce access control on every API request
        $this->requireAccess($context, $apiKey);

        $token = $this->getAccessToken();
        if (!$token) {
            return ['error' => 'No PayPal access token available. Set PAYPAL_ACCESS_TOKEN or PAYPAL_CLIENT_ID/SECRET.'];
        }

        $url = $this->baseUrl . $endpoint;
        $ch = curl_init($url);

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            if ($data) {
                $opts[CURLOPT_POSTFIELDS] = json_encode($data);
            }
        } elseif ($method === 'PATCH') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'PATCH';
            if ($data) {
                $opts[CURLOPT_POSTFIELDS] = json_encode($data);
            }
        } elseif ($method === 'DELETE') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        }

        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => 'cURL error: ' . $error];
        }

        $decoded = json_decode($response, true) ?? [];
        $decoded['_http_code'] = $httpCode;

        return $decoded;
    }

    // ==================== CATALOG MANAGEMENT ====================

    #[McpTool(name: 'paypal_create_product', description: 'Create a new product in PayPal catalog (PHYSICAL, DIGITAL, SERVICE) - ADMIN ONLY')]
    public function createProduct(string $name, string $type, ?string $description = null): array
    {
        $payload = [
            'name' => $name,
            'type' => strtoupper($type),
        ];

        if ($description) {
            $payload['description'] = $description;
        }

        return $this->apiRequest('POST', '/v1/catalogs/products', $payload);
    }

    #[McpTool(name: 'paypal_list_products', description: 'List products from PayPal catalog')]
    public function listProducts(?int $page = 1, ?int $pageSize = 10): array
    {
        $query = http_build_query(['page' => $page, 'page_size' => $pageSize]);
        return $this->apiRequest('GET', '/v1/catalogs/products?' . $query);
    }

    #[McpTool(name: 'paypal_show_product_details', description: 'Get details of a specific product')]
    public function showProductDetails(string $productId): array
    {
        return $this->apiRequest('GET', '/v1/catalogs/products/' . urlencode($productId));
    }

    // ==================== SUBSCRIPTION MANAGEMENT ====================

    #[McpTool(name: 'paypal_create_subscription_plan', description: 'Create a subscription billing plan')]
    public function createSubscriptionPlan(
        string $productId,
        string $name,
        array $billingCycles,
        ?bool $autoBillOutstanding = true
    ): array {
        $payload = [
            'product_id' => $productId,
            'name' => $name,
            'billing_cycles' => $billingCycles,
            'payment_preferences' => [
                'auto_bill_outstanding' => $autoBillOutstanding,
            ],
        ];

        return $this->apiRequest('POST', '/v1/billing/plans', $payload);
    }

    #[McpTool(name: 'paypal_create_subscription', description: 'Create a new subscription for a customer')]
    public function createSubscription(
        string $planId,
        ?string $subscriberName = null,
        ?string $subscriberEmail = null
    ): array {
        $payload = ['plan_id' => $planId];

        if ($subscriberName || $subscriberEmail) {
            $payload['subscriber'] = [];
            if ($subscriberName) {
                $payload['subscriber']['name'] = ['given_name' => $subscriberName];
            }
            if ($subscriberEmail) {
                $payload['subscriber']['email_address'] = $subscriberEmail;
            }
        }

        return $this->apiRequest('POST', '/v1/billing/subscriptions', $payload);
    }

    #[McpTool(name: 'paypal_list_subscription_plans', description: 'List all subscription plans')]
    public function listSubscriptionPlans(?string $productId = null, ?int $page = 1, ?int $pageSize = 10): array
    {
        $params = ['page' => $page, 'page_size' => $pageSize];
        if ($productId) {
            $params['product_id'] = $productId;
        }
        $query = http_build_query($params);
        return $this->apiRequest('GET', '/v1/billing/plans?' . $query);
    }

    #[McpTool(name: 'paypal_show_subscription_details', description: 'Get details of a specific subscription')]
    public function showSubscriptionDetails(string $subscriptionId): array
    {
        return $this->apiRequest('GET', '/v1/billing/subscriptions/' . urlencode($subscriptionId));
    }

    #[McpTool(name: 'paypal_cancel_subscription', description: 'Cancel an active subscription')]
    public function cancelSubscription(string $subscriptionId, ?string $reason = null): array
    {
        $payload = ['reason' => $reason ?? 'Customer requested cancellation'];
        return $this->apiRequest('POST', '/v1/billing/subscriptions/' . urlencode($subscriptionId) . '/cancel', $payload);
    }

    #[McpTool(name: 'paypal_show_subscription_plan_details', description: 'Get details of a subscription plan')]
    public function showSubscriptionPlanDetails(string $planId): array
    {
        return $this->apiRequest('GET', '/v1/billing/plans/' . urlencode($planId));
    }

    // ==================== INVOICING ====================

    #[McpTool(name: 'paypal_create_invoice', description: 'Create a new invoice with line items')]
    public function createInvoice(string $recipientEmail, array $items): array
    {
        $invoiceItems = [];
        foreach ($items as $item) {
            $invoiceItems[] = [
                'name' => $item['name'],
                'quantity' => (string)$item['quantity'],
                'unit_amount' => [
                    'currency_code' => 'USD',
                    'value' => (string)$item['unit_price'],
                ],
            ];
        }

        $payload = [
            'detail' => [
                'currency_code' => 'USD',
            ],
            'primary_recipients' => [
                ['billing_info' => ['email_address' => $recipientEmail]],
            ],
            'items' => $invoiceItems,
        ];

        return $this->apiRequest('POST', '/v2/invoicing/invoices', $payload);
    }

    #[McpTool(name: 'paypal_list_invoices', description: 'List invoices with optional status filter')]
    public function listInvoices(?string $status = null, ?int $page = 1, ?int $pageSize = 10): array
    {
        $params = ['page' => $page, 'page_size' => $pageSize];
        if ($status) {
            $params['status'] = strtoupper($status);
        }
        $query = http_build_query($params);
        return $this->apiRequest('GET', '/v2/invoicing/invoices?' . $query);
    }

    #[McpTool(name: 'paypal_get_invoice', description: 'Get details of a specific invoice')]
    public function getInvoice(string $invoiceId): array
    {
        return $this->apiRequest('GET', '/v2/invoicing/invoices/' . urlencode($invoiceId));
    }

    #[McpTool(name: 'paypal_send_invoice', description: 'Send an invoice to the recipient')]
    public function sendInvoice(string $invoiceId): array
    {
        return $this->apiRequest('POST', '/v2/invoicing/invoices/' . urlencode($invoiceId) . '/send', []);
    }

    #[McpTool(name: 'paypal_send_invoice_reminder', description: 'Send a reminder for an unpaid invoice')]
    public function sendInvoiceReminder(string $invoiceId): array
    {
        return $this->apiRequest('POST', '/v2/invoicing/invoices/' . urlencode($invoiceId) . '/remind', []);
    }

    #[McpTool(name: 'paypal_cancel_invoice', description: 'Cancel a sent invoice')]
    public function cancelInvoice(string $invoiceId): array
    {
        return $this->apiRequest('POST', '/v2/invoicing/invoices/' . urlencode($invoiceId) . '/cancel', []);
    }

    #[McpTool(name: 'paypal_generate_invoice_qr', description: 'Generate a QR code for an invoice')]
    public function generateInvoiceQr(string $invoiceId): array
    {
        return $this->apiRequest('POST', '/v2/invoicing/invoices/' . urlencode($invoiceId) . '/generate-qr-code', [
            'width' => 400,
            'height' => 400,
        ]);
    }

    // ==================== ORDERS & PAYMENTS ====================

    #[McpTool(name: 'paypal_create_order', description: 'Create a payment order')]
    public function createOrder(array $items, string $currency = 'USD'): array
    {
        $total = 0;
        $orderItems = [];

        foreach ($items as $item) {
            $itemTotal = ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
            $total += $itemTotal;
            $orderItems[] = [
                'name' => $item['name'],
                'quantity' => (string)($item['quantity'] ?? 1),
                'unit_amount' => [
                    'currency_code' => $currency,
                    'value' => number_format($item['unit_price'] ?? 0, 2, '.', ''),
                ],
            ];
        }

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($total, 2, '.', ''),
                        'breakdown' => [
                            'item_total' => [
                                'currency_code' => $currency,
                                'value' => number_format($total, 2, '.', ''),
                            ],
                        ],
                    ],
                    'items' => $orderItems,
                ],
            ],
        ];

        return $this->apiRequest('POST', '/v2/checkout/orders', $payload);
    }

    #[McpTool(name: 'paypal_get_order', description: 'Get details of a specific order')]
    public function getOrder(string $orderId): array
    {
        return $this->apiRequest('GET', '/v2/checkout/orders/' . urlencode($orderId));
    }

    #[McpTool(name: 'paypal_pay_order', description: 'Capture payment for an authorized order')]
    public function payOrder(string $orderId): array
    {
        return $this->apiRequest('POST', '/v2/checkout/orders/' . urlencode($orderId) . '/capture', []);
    }

    #[McpTool(name: 'paypal_create_refund', description: 'Process a refund for a captured payment')]
    public function createRefund(string $captureId, ?float $amount = null, string $currency = 'USD'): array
    {
        $payload = [];
        if ($amount !== null) {
            $payload['amount'] = [
                'currency_code' => $currency,
                'value' => number_format($amount, 2, '.', ''),
            ];
        }

        return $this->apiRequest('POST', '/v2/payments/captures/' . urlencode($captureId) . '/refund', $payload ?: null);
    }

    #[McpTool(name: 'paypal_get_refund', description: 'Get details of a specific refund')]
    public function getRefund(string $refundId): array
    {
        return $this->apiRequest('GET', '/v2/payments/refunds/' . urlencode($refundId));
    }

    // ==================== TRANSACTIONS & REPORTING ====================

    #[McpTool(name: 'paypal_list_transactions', description: 'List all transactions')]
    public function listTransactions(?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ?: date('Y-m-d', strtotime('-31 days')) . 'T00:00:00Z';
        $end = $endDate ?: date('Y-m-d') . 'T23:59:59Z';

        $query = http_build_query([
            'start_date' => $start,
            'end_date' => $end,
        ]);

        return $this->apiRequest('GET', '/v1/reporting/transactions?' . $query);
    }

    // ==================== DISPUTES ====================

    #[McpTool(name: 'paypal_list_disputes', description: 'List all disputes')]
    public function listDisputes(?string $status = null): array
    {
        $params = [];
        if ($status) {
            $params['dispute_state'] = strtoupper($status);
        }
        $query = $params ? '?' . http_build_query($params) : '';
        return $this->apiRequest('GET', '/v1/customer/disputes' . $query);
    }

    #[McpTool(name: 'paypal_get_dispute', description: 'Get details of a specific dispute')]
    public function getDispute(string $disputeId): array
    {
        return $this->apiRequest('GET', '/v1/customer/disputes/' . urlencode($disputeId));
    }

    #[McpTool(name: 'paypal_accept_dispute', description: 'Accept a dispute claim')]
    public function acceptDispute(string $disputeId): array
    {
        return $this->apiRequest('POST', '/v1/customer/disputes/' . urlencode($disputeId) . '/accept-claim', []);
    }

    // ==================== SHIPMENT TRACKING ====================

    #[McpTool(name: 'paypal_create_shipment_tracking', description: 'Add tracking info to a transaction')]
    public function createShipmentTracking(
        string $trackingNumber,
        string $transactionId,
        string $carrier,
        ?string $orderId = null,
        string $status = 'SHIPPED'
    ): array {
        $payload = [
            'trackers' => [
                [
                    'transaction_id' => $transactionId,
                    'tracking_number' => $trackingNumber,
                    'status' => strtoupper($status),
                    'carrier' => strtoupper($carrier),
                ],
            ],
        ];

        return $this->apiRequest('POST', '/v1/shipping/trackers-batch', $payload);
    }

    #[McpTool(name: 'paypal_get_shipment_tracking', description: 'Get tracking info for an order')]
    public function getShipmentTracking(string $orderId, ?string $transactionId = null): array
    {
        $id = $transactionId ?: $orderId;
        return $this->apiRequest('GET', '/v1/shipping/trackers/' . urlencode($id));
    }

    #[McpTool(name: 'paypal_update_shipment_tracking', description: 'Update tracking info')]
    public function updateShipmentTracking(
        string $transactionId,
        string $trackingNumber,
        string $status,
        ?string $newTrackingNumber = null,
        ?string $carrier = null
    ): array {
        $trackerId = $transactionId . '-' . $trackingNumber;
        $payload = [
            [
                'op' => 'replace',
                'path' => '/status',
                'value' => strtoupper($status),
            ],
        ];

        if ($newTrackingNumber) {
            $payload[] = [
                'op' => 'replace',
                'path' => '/tracking_number',
                'value' => $newTrackingNumber,
            ];
        }

        if ($carrier) {
            $payload[] = [
                'op' => 'replace',
                'path' => '/carrier',
                'value' => strtoupper($carrier),
            ];
        }

        return $this->apiRequest('PATCH', '/v1/shipping/trackers/' . urlencode($trackerId), $payload);
    }
}
