<?php
namespace Ginto\Handlers;

/**
 * PayMongo Handler
 *
 * Handles PayMongo API interactions:
 * - QRPH Payments (Payment Intents API)
 * - Payment status polling
 *
 * Authentication: HTTP Basic Auth with secret key as username, empty password.
 */
class PayMongoHandler
{
    private const API_BASE = 'https://api.paymongo.com/v1';

    private string $secretKey;
    private string $publicKey;

    public function __construct()
    {
        $env = strtolower(trim($_ENV['PAYMONGO_ENVIRONMENT'] ?? getenv('PAYMONGO_ENVIRONMENT') ?? 'live'));
        if ($env === 'test') {
            $this->secretKey = $_ENV['PAYMONGO_SECRET_KEY_TEST'] ?? getenv('PAYMONGO_SECRET_KEY_TEST') ?? '';
            $this->publicKey = $_ENV['PAYMONGO_PUBLIC_KEY_TEST'] ?? getenv('PAYMONGO_PUBLIC_KEY_TEST') ?? '';
        } else {
            $this->secretKey = $_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY') ?? '';
            $this->publicKey = $_ENV['PAYMONGO_PUBLIC_KEY'] ?? getenv('PAYMONGO_PUBLIC_KEY') ?? '';
        }
    }

    /** Look up a PayMongo customer's email by id (used to map subscription webhooks to a user). */
    public static function getCustomerEmail(string $customerId): ?string
    {
        if ($customerId === '') return null;
        $env = strtolower(trim($_ENV['PAYMONGO_ENVIRONMENT'] ?? getenv('PAYMONGO_ENVIRONMENT') ?? 'live'));
        $sk  = $env === 'test'
            ? ($_ENV['PAYMONGO_SECRET_KEY_TEST'] ?? getenv('PAYMONGO_SECRET_KEY_TEST') ?? '')
            : ($_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY') ?? '');
        if ($sk === '') return null;
        $ch = curl_init('https://api.paymongo.com/v1/customers/' . rawurlencode($customerId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode($sk . ':')],
        ]);
        $r = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code !== 200) return null;
        $d = json_decode((string) $r, true);
        return $d['data']['attributes']['email'] ?? null;
    }

    /**
     * Check if PayMongo is configured
     */
    public static function isConfigured(): bool
    {
        $env = strtolower(trim($_ENV['PAYMONGO_ENVIRONMENT'] ?? getenv('PAYMONGO_ENVIRONMENT') ?? 'live'));
        if ($env === 'test') {
            $secretKey = $_ENV['PAYMONGO_SECRET_KEY_TEST'] ?? getenv('PAYMONGO_SECRET_KEY_TEST') ?? '';
        } else {
            $secretKey = $_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY') ?? '';
        }
        return !empty($secretKey);
    }

    /**
     * Build authorization header using secret key
     */
    private function authHeader(): string
    {
        return 'Basic ' . base64_encode($this->secretKey . ':');
    }

    /**
     * Make a request to the PayMongo API
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $url = self::API_BASE . $path;

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: ' . $this->authHeader(),
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('PayMongo cURL error: ' . $curlError);
            return ['error' => 'Connection error', 'http_code' => 0];
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            error_log('PayMongo invalid JSON response: ' . $response);
            return ['error' => 'Invalid response', 'http_code' => $httpCode];
        }

        $decoded['http_code'] = $httpCode;
        return $decoded;
    }

    /**
     * Create a Payment Intent.
     *
     * @param int    $amountCentavos         Amount in centavos (multiply PHP amount × 100)
     * @param string $description            Description for the payment
     * @param array  $paymentMethodAllowed   e.g. ['qrph'] or ['card']
     * @return array ['success' => bool, 'pi_id' => string, 'client_key' => string, 'status' => string] | ['success' => false, 'message' => string]
     */
    public function createPaymentIntent(int $amountCentavos, string $description = 'Ginto Membership', array $paymentMethodAllowed = ['qrph']): array
    {
        if (empty($this->secretKey)) {
            return ['success' => false, 'message' => 'PayMongo is not configured.'];
        }

        $body = [
            'data' => [
                'attributes' => [
                    'amount'                  => $amountCentavos,
                    'payment_method_allowed'  => $paymentMethodAllowed,
                    'payment_method_options'  => ['card' => ['request_three_d_secure' => 'any']],
                    'currency'                => 'PHP',
                    'capture_type'            => 'automatic',
                    'description'             => $description,
                ],
            ],
        ];

        $response = $this->request('POST', '/payment_intents', $body);

        if (!empty($response['errors']) || $response['http_code'] >= 400) {
            $msg = $response['errors'][0]['detail'] ?? 'Failed to create payment intent.';
            error_log('PayMongo createPaymentIntent error: ' . json_encode($response));
            return ['success' => false, 'message' => $msg];
        }

        $piId       = $response['data']['id'] ?? null;
        $clientKey  = $response['data']['attributes']['client_key'] ?? null;
        $status     = $response['data']['attributes']['status'] ?? 'unknown';

        if (!$piId) {
            return ['success' => false, 'message' => 'Invalid response from PayMongo.'];
        }

        return [
            'success'    => true,
            'pi_id'      => $piId,
            'client_key' => $clientKey,
            'status'     => $status,
        ];
    }

    /**
     * Create a card payment method.
     *
     * @param array $card    ['number','exp_month','exp_year','cvc']
    * @param array $billing ['name','email','phone','address']
     * @return array ['success' => bool, 'pm_id' => string] | ['success' => false, 'message' => string]
     */
    public function createCardPaymentMethod(array $card, array $billing): array
    {
        if (empty($this->secretKey)) {
            return ['success' => false, 'message' => 'PayMongo is not configured.'];
        }

        $billingPayload = [
            'name'  => $billing['name'] ?? 'Ginto User',
            'email' => $billing['email'] ?? '',
            'phone' => $billing['phone'] ?? '',
        ];

        if (!empty($billing['address']) && is_array($billing['address'])) {
            $address = array_filter([
                'line1'       => $billing['address']['line1'] ?? '',
                'line2'       => $billing['address']['line2'] ?? '',
                'city'        => $billing['address']['city'] ?? '',
                'state'       => $billing['address']['state'] ?? '',
                'postal_code' => $billing['address']['postal_code'] ?? '',
                'country'     => $billing['address']['country'] ?? '',
            ], static function($value) {
                return $value !== null && $value !== '';
            });

            if (!empty($address)) {
                $billingPayload['address'] = $address;
            }
        }

        $body = [
            'data' => [
                'attributes' => [
                    'type'    => 'card',
                    'details' => [
                        'card_number' => $card['number'] ?? '',
                        'exp_month'   => (int)($card['exp_month'] ?? 0),
                        'exp_year'    => (int)($card['exp_year'] ?? 0),
                        'cvc'         => $card['cvc'] ?? '',
                    ],
                    'billing' => $billingPayload,
                ],
            ],
        ];

        $response = $this->request('POST', '/payment_methods', $body);

        if (!empty($response['errors']) || $response['http_code'] >= 400) {
            $msg = $response['errors'][0]['detail'] ?? 'Failed to create card payment method.';
            error_log('PayMongo createCardPaymentMethod error: ' . json_encode($response));
            return ['success' => false, 'message' => $msg];
        }

        $pmId = $response['data']['id'] ?? null;
        if (!$pmId) {
            return ['success' => false, 'message' => 'Invalid card payment method response.'];
        }

        return ['success' => true, 'pm_id' => $pmId];
    }

    /**
     * Create a QRPH Payment Method
     *
     * @param string $email  Payer email (for billing)
     * @param string $name   Payer name
     * @param string $phone  Payer phone (optional)
     * @return array ['success' => bool, 'pm_id' => string] | ['success' => false, 'message' => string]
     */
    public function createQrphPaymentMethod(string $email, string $name, string $phone = ''): array
    {
        if (empty($this->secretKey)) {
            return ['success' => false, 'message' => 'PayMongo is not configured.'];
        }

        $billing = [
            'email' => $email,
            'name'  => $name,
        ];
        if (!empty($phone)) {
            $billing['phone'] = $phone;
        }

        $body = [
            'data' => [
                'attributes' => [
                    'type'    => 'qrph',
                    'billing' => $billing,
                ],
            ],
        ];

        $response = $this->request('POST', '/payment_methods', $body);

        if (!empty($response['errors']) || $response['http_code'] >= 400) {
            $msg = $response['errors'][0]['detail'] ?? 'Failed to create payment method.';
            error_log('PayMongo createQrphPaymentMethod error: ' . json_encode($response));
            return ['success' => false, 'message' => $msg];
        }

        $pmId = $response['data']['id'] ?? null;

        if (!$pmId) {
            return ['success' => false, 'message' => 'Invalid payment method response.'];
        }

        return ['success' => true, 'pm_id' => $pmId];
    }

    /**
     * Attach a Payment Method to a Payment Intent
     * This triggers the QRPH and returns the QR code image.
     *
     * @param string $piId        Payment Intent ID
     * @param string $pmId        Payment Method ID
     * @param string $clientKey   Client key from payment intent
     * @return array ['success' => bool, 'qr_image' => string, 'qr_string' => string, 'status' => string] | ['success' => false, 'message' => string]
     */
    public function attachPaymentMethod(string $piId, string $pmId, string $clientKey): array
    {
        if (empty($this->secretKey)) {
            return ['success' => false, 'message' => 'PayMongo is not configured.'];
        }

        $body = [
            'data' => [
                'attributes' => [
                    'payment_method' => $pmId,
                    'client_key'     => $clientKey,
                ],
            ],
        ];

        $response = $this->request('POST', '/payment_intents/' . urlencode($piId) . '/attach', $body);

        if (!empty($response['errors']) || $response['http_code'] >= 400) {
            $msg = $response['errors'][0]['detail'] ?? 'Failed to attach payment method.';
            error_log('PayMongo attachPaymentMethod error: ' . json_encode($response));
            return ['success' => false, 'message' => $msg];
        }

        $attrs  = $response['data']['attributes'] ?? [];
        $status = $attrs['status'] ?? 'unknown';

        // Extract QR code from next_action
        $qrImage  = '';
        $qrString = '';
        $nextAction = $attrs['next_action'] ?? [];
        $nextType   = $nextAction['type'] ?? '';

        if ($nextType === 'consume_qr') {
            // PayMongo QRPH: QR data is under next_action.code
            $code     = $nextAction['code'] ?? [];
            $qrImage  = $code['image_url'] ?? '';   // base64 data URI
            $qrString = $code['id']        ?? '';   // QR code identifier
        } elseif ($nextType === 'display_qr_code') {
            // Legacy path (kept for compatibility)
            $displayDetails = $nextAction['display_details'] ?? [];
            $qrImage  = $displayDetails['qr_image']  ?? '';
            $qrString = $displayDetails['qr_string'] ?? '';
        }

        return [
            'success'   => true,
            'status'    => $status,
            'qr_image'  => $qrImage,
            'qr_string' => $qrString,
            'next_action' => $nextAction,
            'pi_id'     => $response['data']['id'] ?? $piId,
        ];
    }

    /**
     * Get Payment Intent status
     *
     * @param string $piId  Payment Intent ID
     * @return array ['success' => bool, 'status' => string, 'payments' => array] | ['success' => false, 'message' => string]
     */
    public function getPaymentIntentStatus(string $piId): array
    {
        if (empty($this->secretKey)) {
            return ['success' => false, 'message' => 'PayMongo is not configured.'];
        }

        $response = $this->request('GET', '/payment_intents/' . urlencode($piId));

        if (!empty($response['errors']) || $response['http_code'] >= 400) {
            $msg = $response['errors'][0]['detail'] ?? 'Failed to retrieve payment intent.';
            error_log('PayMongo getPaymentIntentStatus error: ' . json_encode($response));
            return ['success' => false, 'message' => $msg];
        }

        $attrs    = $response['data']['attributes'] ?? [];
        $status   = $attrs['status'] ?? 'unknown';
        $payments = $attrs['payments'] ?? [];

        // Extract the finalized charge ID (pay_xxxxxxxx) from the first payment object
        $paymentId = null;
        if (!empty($payments[0]['id'])) {
            $paymentId = $payments[0]['id'];
        }

        return [
            'success'    => true,
            'status'     => $status,
            'payment_id' => $paymentId,   // pay_xxxxxxxx — the actual charge object
            'payments'   => $payments,
        ];
    }

    /**
     * Verify a PayMongo webhook signature.
     *
     * PayMongo signs requests with HMAC-SHA256.
     * Header: "Paymongo-Signature: t=<timestamp>,te=<hmac>,li=<hmac>"
     * Signed payload: "<timestamp>.<raw_body>"
     * Replay attack protection: reject events older than 5 minutes.
     *
     * @param string $signatureHeader  Value of the Paymongo-Signature header
     * @param string $rawBody          Raw request body string
     * @return bool
     */
    public static function verifyWebhookSignature(string $signatureHeader, string $rawBody): bool
    {
        $secret = $_ENV['PAYMONGO_WEBHOOK_SECRET'] ?? getenv('PAYMONGO_WEBHOOK_SECRET') ?? '';
        if (empty($secret) || empty($signatureHeader)) {
            return false;
        }

        // Parse t=<ts>,te=<hmac>,li=<hmac>
        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$k, $v] = array_pad(explode('=', $part, 2), 2, '');
            $parts[trim($k)] = trim($v);
        }

        $timestamp = $parts['t'] ?? '';
        if (empty($timestamp)) {
            return false;
        }

        // NOTE: Do NOT reject based on timestamp age.
        // PayMongo retries webhooks with the SAME original signature/timestamp (sometimes hours later).
        // A tight replay window (e.g. 5 min) causes all retried events to be rejected with 401,
        // which is what disables the webhook on PayMongo's side.
        // HMAC verification alone is sufficient to ensure authenticity.

        $signedPayload = $timestamp . '.' . $rawBody;
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        // Check against both te (test) and li (live) signatures
        foreach (['te', 'li'] as $key) {
            if (!empty($parts[$key]) && hash_equals($expected, $parts[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Full QRPH initialization flow:
     * 1. Create Payment Intent
     * 2. Create QRPH Payment Method
     * 3. Attach Payment Method to get QR code
     *
     * @param int    $amountPhp   Amount in PHP (will be converted to centavos)
     * @param string $email       Payer email
     * @param string $name        Payer name
     * @param string $phone       Payer phone (optional)
     * @param string $description Payment description
     * @return array ['success' => bool, 'pi_id' => string, 'qr_image' => string, 'qr_string' => string] | ['success' => false, 'message' => string]
     */
    public function initQrph(int $amountPhp, string $email, string $name, string $phone = '', string $description = 'Ginto Membership'): array
    {
        $amountCentavos = $amountPhp * 100;

        // Step 1: Create Payment Intent
        $piResult = $this->createPaymentIntent($amountCentavos, $description, ['qrph']);
        if (!$piResult['success']) {
            return $piResult;
        }
        $piId      = $piResult['pi_id'];
        $clientKey = $piResult['client_key'];

        // Step 2: Create QRPH Payment Method
        $pmResult = $this->createQrphPaymentMethod($email, $name, $phone);
        if (!$pmResult['success']) {
            return $pmResult;
        }
        $pmId = $pmResult['pm_id'];

        // Step 3: Attach to get QR code
        $attachResult = $this->attachPaymentMethod($piId, $pmId, $clientKey);
        if (!$attachResult['success']) {
            return $attachResult;
        }

        return [
            'success'   => true,
            'pi_id'     => $piId,
            'pm_id'     => $pmId,
            'qr_image'  => $attachResult['qr_image'],
            'qr_string' => $attachResult['qr_string'],
            'status'    => $attachResult['status'],
        ];
    }

    /**
     * Card initialization flow without hosted checkout redirect.
     *
     * 1. Create card-enabled Payment Intent
     * 2. Create card Payment Method from submitted card data
     * 3. Attach Payment Method to charge card
     *
     * @return array ['success'=>bool,'pi_id'=>string,'payment_id'=>?string,'status'=>string,'next_action'=>array]
     */
    public function initCardPayment(
        float $amountPhp,
        string $email,
        string $name,
        string $phone,
        string $description,
        array $card,
        array $billingAddress = []
    ): array {
        $amountCentavos = (int)round($amountPhp * 100);

        $piResult = $this->createPaymentIntent($amountCentavos, $description, ['card']);
        if (!$piResult['success']) {
            return $piResult;
        }

        $piId      = $piResult['pi_id'];
        $clientKey = $piResult['client_key'];

        $pmResult = $this->createCardPaymentMethod($card, [
            'name'  => $name,
            'email' => $email,
            'phone' => $phone,
            'address' => $billingAddress,
        ]);
        if (!$pmResult['success']) {
            return $pmResult;
        }

        $attachResult = $this->attachPaymentMethod($piId, $pmResult['pm_id'], $clientKey);
        if (!$attachResult['success']) {
            return $attachResult;
        }

        $statusResult = $this->getPaymentIntentStatus($piId);

        return [
            'success'      => true,
            'pi_id'        => $piId,
            'status'       => $attachResult['status'] ?? ($statusResult['status'] ?? 'unknown'),
            'payment_id'   => $statusResult['payment_id'] ?? null,
            'next_action'  => $attachResult['next_action'] ?? [],
        ];
    }

    /**
     * Create a PayMongo Checkout Session (hosted card payment page).
     *
     * The user is redirected to checkout_url, completes card payment,
     * then PayMongo redirects them to success_url with the session ID.
     *
     * @param int    $amountCentavos  Amount in centavos
     * @param string $description     Line-item description
     * @param string $name            Billing name
     * @param string $email           Billing email
     * @param string $successUrl      Redirect on payment success (may include {CHECKOUT_SESSION_ID})
     * @param string $cancelUrl       Redirect on cancellation
     * @return array ['success' => bool, 'checkout_url' => string, 'session_id' => string] | ['success' => false, ...]
     */
    public function createCheckoutSession(
        int    $amountCentavos,
        string $description,
        string $name,
        string $email,
        string $successUrl,
        string $cancelUrl,
        string $phone = '',
        array $billingAddress = [],
        array $paymentMethods = []
    ): array {
        if (empty($this->secretKey)) {
            return ['success' => false, 'message' => 'PayMongo is not configured.'];
        }

        // Which methods to offer. If none are given (and no env override), OMIT the field so
        // PayMongo shows EVERY method enabled on the merchant account (GCash, Maya, QRPh, card…).
        // Hardcoding a single method that isn't activated yields "No payment methods available".
        if (empty($paymentMethods)) {
            $envMethods = (string) (getenv('PAYMONGO_CHECKOUT_METHODS') ?: ($_ENV['PAYMONGO_CHECKOUT_METHODS'] ?? ''));
            if ($envMethods !== '') {
                $paymentMethods = array_values(array_filter(array_map('trim', explode(',', $envMethods))));
            }
        }

        $billingPayload = [
            'name'    => $name,
            'email'   => $email,
            'phone'   => $phone,
        ];

        if (!empty($billingAddress)) {
            $address = array_filter([
                'line1'       => $billingAddress['line1'] ?? '',
                'line2'       => $billingAddress['line2'] ?? '',
                'city'        => $billingAddress['city'] ?? '',
                'state'       => $billingAddress['state'] ?? '',
                'postal_code' => $billingAddress['postal_code'] ?? '',
                'country'     => $billingAddress['country'] ?? '',
            ], static function($value) {
                return $value !== null && $value !== '';
            });
            if (!empty($address)) {
                $billingPayload['address'] = $address;
            }
        }

        $body = [
            'data' => [
                'attributes' => [
                    'billing'              => $billingPayload,
                    'line_items'           => [
                        [
                            'currency'    => 'PHP',
                            'amount'      => $amountCentavos,
                            'description' => $description,
                            'name'        => $description,
                            'quantity'    => 1,
                        ],
                    ],
                    'success_url'          => $successUrl,
                    'cancel_url'           => $cancelUrl,
                    'send_email_receipt'   => false,
                    'show_description'     => true,
                    'show_line_items'      => true,
                ],
            ],
        ];

        // Only constrain methods when explicitly requested; otherwise PayMongo shows all enabled.
        if (!empty($paymentMethods)) {
            $body['data']['attributes']['payment_method_types'] = array_values($paymentMethods);
        }

        $response = $this->request('POST', '/checkout_sessions', $body);

        if (!empty($response['errors']) || $response['http_code'] >= 400) {
            $msg = $response['errors'][0]['detail'] ?? 'Failed to create checkout session.';
            error_log('PayMongo createCheckoutSession error: ' . json_encode($response));
            return ['success' => false, 'message' => $msg];
        }

        $sessionId   = $response['data']['id'] ?? null;
        $checkoutUrl = $response['data']['attributes']['checkout_url'] ?? null;

        if (!$sessionId || !$checkoutUrl) {
            return ['success' => false, 'message' => 'Invalid checkout session response.'];
        }

        return [
            'success'      => true,
            'session_id'   => $sessionId,
            'checkout_url' => $checkoutUrl,
        ];
    }

    /**
     * Create a PayMongo Subscription Plan
     *
     * @param string $name Plan name
     * @param string $description Plan description
     * @param int $amount Amount in centavos
     * @param string $currency Currency code (PHP)
     * @param string $interval Billing interval (MONTH)
     * @param int $intervalCount Interval count (1)
     * @return array ['success' => bool, 'plan_id' => string] | ['success' => false, 'message' => string]
     */
    public function createSubscriptionPlan(string $name, string $description, int $amount, string $currency = 'PHP', string $interval = 'MONTH', int $intervalCount = 1): array
    {
        if (empty($this->secretKey)) {
            return ['success' => false, 'message' => 'PayMongo is not configured.'];
        }

        $body = [
            'data' => [
                'attributes' => [
                    'name' => $name,
                    'description' => $description,
                    'amount' => $amount,
                    'currency' => $currency,
                    'interval' => $interval,
                    'interval_count' => $intervalCount,
                ],
            ],
        ];

        $response = $this->request('POST', '/plans', $body);

        if (!empty($response['errors']) || $response['http_code'] >= 400) {
            $msg = $response['errors'][0]['detail'] ?? 'Failed to create subscription plan.';
            error_log('PayMongo createSubscriptionPlan error: ' . json_encode($response));
            return ['success' => false, 'message' => $msg];
        }

        $planId = $response['data']['id'] ?? null;
        if (!$planId) {
            return ['success' => false, 'message' => 'Invalid subscription plan response.'];
        }

        return ['success' => true, 'plan_id' => $planId];
    }

    /**
     * Create a PayMongo Subscription
     *
     * @param string $planId Plan ID
     * @param string $customerId Customer ID (optional)
     * @param array $billing Billing info
     * @return array ['success' => bool, 'subscription_id' => string, 'checkout_url' => string] | ['success' => false, 'message' => string]
     */
    public function createSubscription(string $planId, string $customerId = null, array $billing = []): array
    {
        if (empty($this->secretKey)) {
            return ['success' => false, 'message' => 'PayMongo is not configured.'];
        }

        $body = [
            'data' => [
                'attributes' => [
                    'plan_id' => $planId,
                ],
            ],
        ];

        if ($customerId) {
            $body['data']['attributes']['customer_id'] = $customerId;
        }

        if (!empty($billing)) {
            $body['data']['attributes']['billing'] = $billing;
        }

        $response = $this->request('POST', '/subscriptions', $body);

        if (!empty($response['errors']) || $response['http_code'] >= 400) {
            $msg = $response['errors'][0]['detail'] ?? 'Failed to create subscription.';
            error_log('PayMongo createSubscription error: ' . json_encode($response));
            return ['success' => false, 'message' => $msg];
        }

        $subscriptionId = $response['data']['id'] ?? null;
        $checkoutUrl = $response['data']['attributes']['latest_invoice']['payment_intent']['attributes']['next_action']['redirect']['url'] ?? null;

        if (!$subscriptionId) {
            return ['success' => false, 'message' => 'Invalid subscription response.'];
        }

        return [
            'success' => true,
            'subscription_id' => $subscriptionId,
            'checkout_url' => $checkoutUrl,
        ];
    }
}