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
        $this->secretKey = $_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY') ?? '';
        $this->publicKey = $_ENV['PAYMONGO_PUBLIC_KEY'] ?? getenv('PAYMONGO_PUBLIC_KEY') ?? '';
    }

    /**
     * Check if PayMongo is configured
     */
    public static function isConfigured(): bool
    {
        $secretKey = $_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY') ?? '';
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
     * Create a Payment Intent for QRPH
     *
     * @param int    $amountCentavos  Amount in centavos (multiply PHP amount × 100)
     * @param string $description     Description for the payment
     * @return array ['success' => bool, 'pi_id' => string, 'client_key' => string, 'status' => string] | ['success' => false, 'message' => string]
     */
    public function createPaymentIntent(int $amountCentavos, string $description = 'Ginto Membership'): array
    {
        if (empty($this->secretKey)) {
            return ['success' => false, 'message' => 'PayMongo is not configured.'];
        }

        $body = [
            'data' => [
                'attributes' => [
                    'amount'                  => $amountCentavos,
                    'payment_method_allowed'  => ['qrph'],
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
        if (($nextAction['type'] ?? '') === 'display_qr_code') {
            $displayDetails = $nextAction['display_details'] ?? [];
            $qrImage  = $displayDetails['qr_image']  ?? '';
            $qrString = $displayDetails['qr_string'] ?? '';
        }

        return [
            'success'    => true,
            'status'     => $status,
            'qr_image'   => $qrImage,
            'qr_string'  => $qrString,
            'pi_id'      => $response['data']['id'] ?? $piId,
            '_next_action' => $nextAction,
            '_attrs_keys'  => array_keys($attrs),
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

        return [
            'success'  => true,
            'status'   => $status,
            'payments' => $payments,
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

        // Reject events older than 5 minutes (replay attack prevention)
        if (abs(time() - (int)$timestamp) > 300) {
            return false;
        }

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
        $piResult = $this->createPaymentIntent($amountCentavos, $description);
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
}
