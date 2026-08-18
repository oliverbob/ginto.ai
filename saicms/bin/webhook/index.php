<?php
// PayPal Webhook Handler
// Place this file at https://wh.silverqueen.pro/index.php

// --- MODIFICATION START ---
// Load Composer's autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

// Load environment variables from the .env file located two directories up
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
    $dotenv->required(['PAYPAL_WEBHOOK_ID', 'PAYPAL_CLIENT_ID', 'PAYPAL_CLIENT_SECRET', 'PAYPAL_ENVIRONMENT']);
} catch (Exception $e) {
    // Stop execution if .env file is missing or misconfigured
    http_response_code(500);
    header('Content-Type: application/json');
    logWebhook('CRITICAL: .env file error - ' . $e->getMessage());
    echo json_encode(['error' => 'Server configuration error.']);
    exit();
}
// --- MODIFICATION END ---


error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Handle different request methods
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // PayPal webhook verification - return success for GET requests
    http_response_code(200);
    echo json_encode(['status' => 'webhook endpoint active', 'timestamp' => date('c')]);
    logWebhook('GET request received - webhook verification');
    exit();
}

// Only accept POST requests for actual webhook events
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// --- MODIFICATION START ---
// PayPal Configuration (loaded from .env file)
define('PAYPAL_WEBHOOK_ID', getenv('PAYPAL_WEBHOOK_ID'));
define('PAYPAL_CLIENT_ID', getenv('PAYPAL_CLIENT_ID'));
define('PAYPAL_CLIENT_SECRET', getenv('PAYPAL_CLIENT_SECRET'));
define('PAYPAL_ENVIRONMENT', getenv('PAYPAL_ENVIRONMENT')); // e.g., 'sandbox' or 'live'
// --- MODIFICATION END ---


// PayPal API URLs
$paypal_urls = [
    'sandbox' => [
        'api' => 'https://api-m.sandbox.paypal.com',
        'auth' => 'https://api-m.sandbox.paypal.com/v1/oauth2/token'
    ],
    'live' => [
        'api' => 'https://api-m.paypal.com',
        'auth' => 'https://api-m.paypal.com/v1/oauth2/token'
    ]
];

// Log function
function logWebhook($message, $data = null) {
    $log = date('Y-m-d H:i:s') . ' - ' . $message;
    if ($data) {
        $log .= ' - ' . json_encode($data);
    }
    $log .= PHP_EOL;
    file_put_contents('webhook.log', $log, FILE_APPEND | LOCK_EX);
}

// Get PayPal access token
function getPayPalAccessToken() {
    global $paypal_urls;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $paypal_urls[PAYPAL_ENVIRONMENT]['auth']);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_USERPWD, PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Accept-Language: en_US',
    ]);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        logWebhook('Failed to get access token', ['http_code' => $httpCode, 'response' => $result]);
        return false;
    }
    
    $data = json_decode($result, true);
    return $data['access_token'] ?? false;
}

// Verify webhook signature
function verifyWebhookSignature($headers, $body) {
    $webhook_id = PAYPAL_WEBHOOK_ID;
    $access_token = getPayPalAccessToken();
    
    if (!$access_token) {
        return false;
    }
    
    // Extract required headers
    $transmission_id = $headers['PAYPAL-TRANSMISSION-ID'] ?? '';
    $cert_id = $headers['PAYPAL-CERT-ID'] ?? '';
    $transmission_sig = $headers['PAYPAL-TRANSMISSION-SIG'] ?? '';
    $transmission_time = $headers['PAYPAL-TRANSMISSION-TIME'] ?? '';
    $auth_algo = $headers['PAYPAL-AUTH-ALGO'] ?? '';
    
    $verification_data = [
        'transmission_id' => $transmission_id,
        'cert_id' => $cert_id,
        'auth_algo' => $auth_algo,
        'transmission_time' => $transmission_time,
        'transmission_sig' => $transmission_sig,
        'webhook_id' => $webhook_id,
        'webhook_event' => json_decode($body, true)
    ];
    
    global $paypal_urls;
    $verify_url = $paypal_urls[PAYPAL_ENVIRONMENT]['api'] . '/v1/notifications/verify-webhook-signature';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $verify_url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($verification_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token,
    ]);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        logWebhook('Webhook verification failed', ['http_code' => $httpCode, 'response' => $result]);
        return false;
    }
    
    $verification = json_decode($result, true);
    return ($verification['verification_status'] ?? '') === 'SUCCESS';
}

// Process webhook event
function processWebhookEvent($event) {
    $event_type = $event['event_type'] ?? '';
    $resource = $event['resource'] ?? [];
    
    logWebhook('Processing webhook event', ['event_type' => $event_type, 'event_id' => $event['id'] ?? '']);
    
    switch ($event_type) {
        case 'PAYMENT.CAPTURE.COMPLETED':
            handlePaymentCompleted($resource);
            break;
            
        case 'PAYMENT.CAPTURE.DENIED':
            handlePaymentDenied($resource);
            break;
            
        case 'PAYMENT.CAPTURE.REFUNDED':
            handlePaymentRefunded($resource);
            break;
            
        case 'BILLING.SUBSCRIPTION.CREATED':
            handleSubscriptionCreated($resource);
            break;
            
        case 'BILLING.SUBSCRIPTION.ACTIVATED':
            handleSubscriptionActivated($resource);
            break;
            
        case 'BILLING.SUBSCRIPTION.CANCELLED':
            handleSubscriptionCancelled($resource);
            break;
            
        case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
            handleSubscriptionPaymentFailed($resource);
            break;
            
        default:
            logWebhook('Unhandled event type', ['event_type' => $event_type]);
    }
}

// Event handlers
function handlePaymentCompleted($payment) {
    $payment_id = $payment['id'] ?? '';
    $amount = $payment['amount'] ?? [];
    $payer = $payment['payer'] ?? [];
    
    logWebhook('Payment completed', [
        'payment_id' => $payment_id,
        'amount' => $amount['value'] ?? '',
        'currency' => $amount['currency_code'] ?? '',
        'payer_email' => $payer['email_address'] ?? ''
    ]);
    
    // Add your payment completion logic here
    // Examples:
    // - Update database with payment status
    // - Send confirmation email
    // - Activate user account or service
    // - Update inventory
}

function handlePaymentDenied($payment) {
    $payment_id = $payment['id'] ?? '';
    
    logWebhook('Payment denied', ['payment_id' => $payment_id]);
    
    // Add your payment denial logic here
    // Examples:
    // - Update database with failed payment
    // - Send failure notification
    // - Deactivate pending services
}

function handlePaymentRefunded($refund) {
    $refund_id = $refund['id'] ?? '';
    $amount = $refund['amount'] ?? [];
    
    logWebhook('Payment refunded', [
        'refund_id' => $refund_id,
        'amount' => $amount['value'] ?? '',
        'currency' => $amount['currency_code'] ?? ''
    ]);
    
    // Add your refund logic here
    // Examples:
    // - Update database with refund status
    // - Send refund confirmation
    // - Adjust inventory or credits
}

function handleSubscriptionCreated($subscription) {
    $subscription_id = $subscription['id'] ?? '';
    $subscriber = $subscription['subscriber'] ?? [];
    
    logWebhook('Subscription created', [
        'subscription_id' => $subscription_id,
        'subscriber_email' => $subscriber['email_address'] ?? ''
    ]);
    
    // Add your subscription creation logic here
}

function handleSubscriptionActivated($subscription) {
    $subscription_id = $subscription['id'] ?? '';
    
    logWebhook('Subscription activated', ['subscription_id' => $subscription_id]);
    
    // Add your subscription activation logic here
    // Examples:
    // - Activate user's premium features
    // - Send welcome email
    // - Update user status in database
}

function handleSubscriptionCancelled($subscription) {
    $subscription_id = $subscription['id'] ?? '';
    
    logWebhook('Subscription cancelled', ['subscription_id' => $subscription_id]);
    
    // Add your subscription cancellation logic here
    // Examples:
    // - Deactivate premium features
    // - Send cancellation confirmation
    // - Update database status
}

function handleSubscriptionPaymentFailed($subscription) {
    $subscription_id = $subscription['id'] ?? '';
    
    logWebhook('Subscription payment failed', ['subscription_id' => $subscription_id]);
    
    // Add your failed payment logic here
    // Examples:
    // - Send payment retry notification
    // - Update account status
    // - Implement grace period logic
}

// Main webhook processing
try {
    // Get raw POST data
    $raw_post_data = file_get_contents('php://input');
    
    if (empty($raw_post_data)) {
        http_response_code(400);
        echo json_encode(['error' => 'No data received']);
        exit();
    }
    
    // Get headers (handle different server configurations)
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_PAYPAL_') === 0) {
            $header_name = str_replace('HTTP_', '', $key);
            $header_name = str_replace('_', '-', $header_name);
            $headers[$header_name] = $value;
        }
    }
    
    // Also check for headers without HTTP_ prefix
    $paypal_headers = [
        'PAYPAL-TRANSMISSION-ID',
        'PAYPAL-CERT-ID',
        'PAYPAL-TRANSMISSION-SIG',
        'PAYPAL-TRANSMISSION-TIME',
        'PAYPAL-AUTH-ALGO'
    ];
    
    foreach ($paypal_headers as $header) {
        $server_key = 'HTTP_' . str_replace('-', '_', $header);
        if (isset($_SERVER[$server_key])) {
            $headers[$header] = $_SERVER[$server_key];
        }
    }
    
    logWebhook('Webhook received', ['headers' => array_keys($headers)]);
    
    // Verify webhook signature (recommended for production)
    if (PAYPAL_ENVIRONMENT === 'live' && !verifyWebhookSignature($headers, $raw_post_data)) {
        logWebhook('Webhook signature verification failed');
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }
    
    // Parse the webhook event
    $webhook_event = json_decode($raw_post_data, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        logWebhook('Invalid JSON received');
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit();
    }
    
    // Process the event
    processWebhookEvent($webhook_event);
    
    // Return success response
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    
} catch (Exception $e) {
    logWebhook('Exception occurred', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>