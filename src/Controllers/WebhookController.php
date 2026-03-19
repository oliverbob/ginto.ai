<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;
use Ginto\Services\MallCommerceService;
use Exception;

class WebhookController
{
    /** @var mixed Database instance */
    protected $db;

    /** @var string PayPal Webhook ID from .env */
    private $paypal_webhook_id;

    /** @var string PayPal Client ID from .env */
    private $paypal_client_id;

    /** @var string PayPal Client Secret from .env */
    private $paypal_client_secret;

    /** @var string PayPal Environment ('sandbox' or 'live') from .env */
    private $paypal_environment;

    /** @var array PayPal API URLs */
    private $paypal_urls = [
        'sandbox' => [
            'api' => 'https://api-m.sandbox.paypal.com',
            'auth' => 'https://api-m.sandbox.paypal.com/v1/oauth2/token'
        ],
        'live' => [
            'api' => 'https://api-m.paypal.com',
            'auth' => 'https://api-m.paypal.com/v1/oauth2/token'
        ]
    ];

    public function loginEx()
    {   // using the medoo (for AI context, parent)
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = $this->db->get("users", "*", ["email" => $email]);
        exit;
    }


    /**
     * Constructor to load environment variables and validate them.
     */
    public function __construct($db = null)
    {
        if ($db === null) {
            $db = Database::getInstance();
        }
        $this->db = $db;

        // DEBUG: write resolved PayPal env sources for troubleshooting
        $dbg = "WEBHOOK CONSTRUCT: _ENV PAYPAL_CLIENT_ID=" . ($_ENV['PAYPAL_CLIENT_ID'] ?? 'NULL') . " | getenv PAYPAL_CLIENT_ID=" . (getenv('PAYPAL_CLIENT_ID') ?: 'NULL') . PHP_EOL;
        @file_put_contents(__DIR__ . '/../../../install_debug.log', $dbg, FILE_APPEND | LOCK_EX);

        // Assign .env values to class properties
        // Prefer $_ENV values (Dotenv may populate $_ENV) then fallback to getenv()
        $this->paypal_webhook_id = $_ENV['PAYPAL_WEBHOOK_ID'] ?? getenv('PAYPAL_WEBHOOK_ID');
        $this->paypal_client_id = $_ENV['PAYPAL_CLIENT_ID'] ?? getenv('PAYPAL_CLIENT_ID');
        $this->paypal_client_secret = $_ENV['PAYPAL_CLIENT_SECRET'] ?? getenv('PAYPAL_CLIENT_SECRET');
        $this->paypal_environment = $_ENV['PAYPAL_ENVIRONMENT'] ?? getenv('PAYPAL_ENVIRONMENT');
    }

    /**
     * Assert PayPal environment variables are present (called only by PayPal methods).
     */
    private function requirePaypalConfig(): void
    {
        if (
            empty($this->paypal_webhook_id) ||
            empty($this->paypal_client_id) ||
            empty($this->paypal_client_secret) ||
            empty($this->paypal_environment)
        ) {
            throw new \Exception(
                'CRITICAL ERROR: One or more required PayPal environment variables are not set. ' .
                'Please check that your .env file is being loaded correctly and contains all required keys.'
            );
        }
    }

    /**
     * Main method to handle incoming PayPal webhook requests.
     */
    public function webhook()
    {
        $this->requirePaypalConfig();
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
            http_response_code(200);
            echo json_encode(['status' => 'webhook endpoint active', 'timestamp' => date('c')]);
            $this->logWebhook('GET request received - webhook verification');
            exit();
        }

        // Only accept POST requests for actual webhook events
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit();
        }

        // Main webhook processing
        try {
            $raw_post_data = file_get_contents('php://input');
            
            if (empty($raw_post_data)) {
                http_response_code(400);
                echo json_encode(['error' => 'No data received']);
                exit();
            }
            
            $headers = $this->getPayPalHeaders();
            $this->logWebhook('Webhook received', ['headers' => array_keys($headers)]);
            
            // Verify webhook signature (recommended for production)
            if ($this->paypal_environment === 'live' && !$this->verifyWebhookSignature($headers, $raw_post_data)) {
                $this->logWebhook('Webhook signature verification failed');
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                exit();
            }
            
            $webhook_event = json_decode($raw_post_data, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logWebhook('Invalid JSON received');
                http_response_code(400);
                echo json_encode(['error' => 'Invalid JSON']);
                exit();
            }
            
            $this->processWebhookEvent($webhook_event);
            
            http_response_code(200);
            echo json_encode(['status' => 'success']);
            
        } catch (Exception $e) {
            $this->logWebhook('Exception occurred', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    // --- Private Helper Methods (formerly global functions) ---

    private function getPayPalHeaders()
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_PAYPAL_') === 0) {
                $header_name = str_replace('HTTP_', '', $key);
                $header_name = str_replace('_', '-', $header_name);
                $headers[$header_name] = $value;
            }
        }
        
        $paypal_header_keys = [
            'PAYPAL-TRANSMISSION-ID', 'PAYPAL-CERT-ID', 'PAYPAL-TRANSMISSION-SIG',
            'PAYPAL-TRANSMISSION-TIME', 'PAYPAL-AUTH-ALGO'
        ];
        
        foreach ($paypal_header_keys as $header) {
            $server_key = 'HTTP_' . str_replace('-', '_', $header);
            if (isset($_SERVER[$server_key])) {
                $headers[$header] = $_SERVER[$server_key];
            }
        }
        return $headers;
    }

    private function logWebhook($message, $data = null)
    {
        $log = date('Y-m-d H:i:s') . ' - ' . $message;
        if ($data) {
            $log .= ' - ' . json_encode($data);
        }
        $log .= PHP_EOL;
        // Ensure the log file path is correct for your framework structure
        file_put_contents(__DIR__ . '/../../../webhook.log', $log, FILE_APPEND | LOCK_EX);
    }

    private function getPayPalAccessToken()
    {
        $auth_url = $this->paypal_urls[$this->paypal_environment]['auth'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $auth_url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_USERPWD, $this->paypal_client_id . ':' . $this->paypal_client_secret);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Language: en_US',
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $this->logWebhook('Failed to get access token', ['http_code' => $httpCode, 'response' => $result]);
            return false;
        }
        
        $data = json_decode($result, true);
        return $data['access_token'] ?? false;
    }

    private function verifyWebhookSignature($headers, $body)
    {
        $access_token = $this->getPayPalAccessToken();
        if (!$access_token) {
            return false;
        }
        
        $verification_data = [
            'transmission_id'   => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
            'cert_id'           => $headers['PAYPAL-CERT-ID'] ?? '',
            'auth_algo'         => $headers['PAYPAL-AUTH-ALGO'] ?? '',
            'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
            'transmission_sig'  => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
            'webhook_id'        => $this->paypal_webhook_id,
            'webhook_event'     => json_decode($body, true)
        ];
        
        $verify_url = $this->paypal_urls[$this->paypal_environment]['api'] . '/v1/notifications/verify-webhook-signature';
        
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
            $this->logWebhook('Webhook verification failed', ['http_code' => $httpCode, 'response' => $result]);
            return false;
        }
        
        $verification = json_decode($result, true);
        return ($verification['verification_status'] ?? '') === 'SUCCESS';
    }

    /**
     * Processes a webhook event, ensuring it is handled only once (idempotency) using Medoo.
     *
     * @param array $event The webhook event data from PayPal.
     * @throws Exception if a database error occurs, to signal PayPal to retry.
     */
    private function processWebhookEvent($event)
    {
        $event_id = $event['id'] ?? null;
        $event_type = $event['event_type'] ?? '';

        if (!$event_id) {
            $this->logWebhook('Webhook event is missing an ID. Cannot process.', $event);
            return;
        }

        // --- IDEMPOTENCY CHECK WITH MEDOO ---
        // Medoo's `action` method handles transactions beautifully. It automatically
        // commits if the function returns true, and rolls back if it returns false or throws an exception.
        $transactionResult = $this->db->action(function($database) use ($event_id) {
            
            // 1. Use `has()` to efficiently check if the event ID already exists.
            $isDuplicate = $database->has('paypal_processed_events', ['event_id' => $event_id]);

            if ($isDuplicate) {
                // Return a specific string to signal that this is a duplicate.
                // We don't want to roll back, so we return a non-false value.
                return 'DUPLICATE';
            }
            
            // 2. If it's a new event, insert its ID.
            $insertResult = $database->insert('paypal_processed_events', [
                'event_id' => $event_id
            ]);

            // `insert()` returns a PDOStatement object on success, or false on failure.
            // If it fails, returning false here will cause `action()` to automatically roll back.
            return $insertResult !== false;
        });

        // --- HANDLE THE TRANSACTION OUTCOME ---

        // Case 1: The event was a duplicate.
        if ($transactionResult === 'DUPLICATE') {
            $this->logWebhook('Duplicate event received and ignored.', ['event_id' => $event_id]);
            return; // Exit gracefully with a 200 OK so PayPal doesn't retry.
        }

        // Case 2: The transaction failed (e.g., database error during insert).
        if ($transactionResult === false) {
            // Medoo's `action` has already rolled back. We just need to log and throw.
            $this->logWebhook('CRITICAL: Database transaction failed. PayPal will retry.', [
                'event_id' => $event_id,
                'error' => $this->db->error() // Get Medoo's last error info.
            ]);
            // Throw an exception. The main `webhook()` method will catch this
            // and return a 500 error, telling PayPal to try again later.
            throw new Exception('Database transaction failed for event ID: ' . $event_id);
        }

        // --- END OF IDEMPOTENCY CHECK ---

        // If we reach here, the transaction was successful and the event is new.
        $this->logWebhook('Processing new event', ['event_type' => $event_type, 'event_id' => $event_id]);
        
        $resource = $event['resource'] ?? [];
        
        switch ($event_type) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                $this->handlePaymentCompleted($resource);
                break;
            case 'PAYMENT.CAPTURE.DENIED':
                $this->handlePaymentDenied($resource);
                break;
            case 'PAYMENT.CAPTURE.REFUNDED':
                $this->handlePaymentRefunded($resource);
                break;
            case 'BILLING.SUBSCRIPTION.CREATED':
                $this->handleSubscriptionCreated($resource);
                break;
            case 'BILLING.SUBSCRIPTION.ACTIVATED':
                $this->handleSubscriptionActivated($resource);
                break;
            case 'BILLING.SUBSCRIPTION.CANCELLED':
                $this->handleSubscriptionCancelled($resource);
                break;
            case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
                $this->handleSubscriptionPaymentFailed($resource);
                break;
            default:
                $this->logWebhook('Unhandled event type', ['event_type' => $event_type]);
        }
    }

    private function handlePaymentCompleted($payment)
    {
        $this->logWebhook('Payment completed', [
            'payment_id' => $payment['id'] ?? '',
            'amount' => $payment['amount']['value'] ?? '',
            'currency' => $payment['amount']['currency_code'] ?? '',
            'payer_email' => $payment['payer']['email_address'] ?? ''
        ]);
        // Add your payment completion logic here
    }

    private function handlePaymentDenied($payment)
    {
        $this->logWebhook('Payment denied', ['payment_id' => $payment['id'] ?? '']);
        // Add your payment denial logic here
    }

    private function handlePaymentRefunded($refund)
    {
        $this->logWebhook('Payment refunded', [
            'refund_id' => $refund['id'] ?? '',
            'amount' => $refund['amount']['value'] ?? '',
            'currency' => $refund['amount']['currency_code'] ?? ''
        ]);
        // Add your refund logic here
    }

    private function handleSubscriptionCreated($subscription)
    {
        $this->logWebhook('Subscription created', [
            'subscription_id' => $subscription['id'] ?? '',
            'subscriber_email' => $subscription['subscriber']['email_address'] ?? ''
        ]);
        // Add your subscription creation logic here
    }

    private function handleSubscriptionActivated($subscription)
    {
        $subscription_id = $subscription['id'] ?? null;
        $paypal_plan_id = $subscription['plan_id'] ?? null;
        $user_id = $subscription['custom_id'] ?? null; // Get user ID from the JS SDK call
        
        // Extract dates from the webhook payload
        $start_date = isset($subscription['start_time']) ? date('Y-m-d H:i:s', strtotime($subscription['start_time'])) : date('Y-m-d H:i:s');
        $period_end_date = isset($subscription['billing_info']['next_billing_time']) ? date('Y-m-d H:i:s', strtotime($subscription['billing_info']['next_billing_time'])) : date('Y-m-d H:i:s', strtotime('+1 month'));

        if (!$user_id || !$subscription_id) {
            $this->logWebhook('CRITICAL: Missing user_id or subscription_id in ACTIVATED event.', $subscription);
            return;
        }

        $this->logWebhook('Subscription activated via webhook', [
            'subscription_id' => $subscription_id, 
            'user_id' => $user_id,
            'paypal_plan_id' => $paypal_plan_id
        ]);

        // Map PayPal plan ID to our plan
        $planMapping = [
            $_ENV['PAYPAL_PLAN_GO'] ?? getenv('PAYPAL_PLAN_GO') => 'go',
            $_ENV['PAYPAL_PLAN_PLUS'] ?? getenv('PAYPAL_PLAN_PLUS') => 'plus',
            $_ENV['PAYPAL_PLAN_PRO'] ?? getenv('PAYPAL_PLAN_PRO') => 'pro',
        ];
        
        $planName = $planMapping[$paypal_plan_id] ?? 'plus';
        $plan = $this->db->get('subscription_plans', '*', ['name' => $planName]);
        
        if (!$plan) {
            $this->logWebhook('CRITICAL: Could not find plan for PayPal plan ID: ' . $paypal_plan_id);
            return;
        }

        // Check if subscription already exists in user_subscriptions
        $existing = $this->db->get('user_subscriptions', 'id', ['paypal_subscription_id' => $subscription_id]);
        
        if ($existing) {
            // Update existing subscription
            $this->db->update('user_subscriptions', [
                'status' => 'active',
                'started_at' => $start_date,
                'expires_at' => $period_end_date,
                'updated_at' => date('Y-m-d H:i:s')
            ], ['paypal_subscription_id' => $subscription_id]);
        } else {
            // Cancel any existing active subscriptions for this user
            $this->db->update('user_subscriptions', [
                'status' => 'cancelled',
                'cancelled_at' => date('Y-m-d H:i:s')
            ], [
                'user_id' => $user_id,
                'status' => 'active'
            ]);
            
            // Insert new subscription
            $this->db->insert('user_subscriptions', [
                'user_id' => $user_id,
                'plan_id' => $plan['id'],
                'status' => 'active',
                'started_at' => $start_date,
                'expires_at' => $period_end_date,
                'payment_method' => 'paypal',
                'paypal_subscription_id' => $subscription_id,
                'paypal_plan_id' => $paypal_plan_id,
                'amount_paid' => $plan['price_monthly'],
                'currency' => $plan['price_currency'] ?? 'PHP',
                'auto_renew' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Update user's plan in users table
        $this->db->update('users', [
            'subscription_plan' => $planName
        ], ['id' => $user_id]);
        
        $this->logWebhook('Subscription activated successfully', [
            'user_id' => $user_id,
            'plan' => $planName
        ]);
    }

    private function handleSubscriptionCancelled($subscription)
    {
        $subscription_id = $subscription['id'] ?? null;
        
        if (!$subscription_id) {
            $this->logWebhook('CRITICAL: Missing subscription_id in CANCELLED event.', $subscription);
            return;
        }

        $this->logWebhook('Subscription cancelled', ['subscription_id' => $subscription_id]);

        // Get the subscription to find the user
        $existingSub = $this->db->get('user_subscriptions', ['id', 'user_id'], ['paypal_subscription_id' => $subscription_id]);
        
        // Update subscription status to cancelled
        $this->db->update('user_subscriptions', [
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s'),
            'auto_renew' => 0,
            'updated_at' => date('Y-m-d H:i:s')
        ], ['paypal_subscription_id' => $subscription_id]);
        
        // Update user's plan back to free if subscription was cancelled
        if ($existingSub && $existingSub['user_id']) {
            $this->db->update('users', [
                'subscription_plan' => 'free'
            ], ['id' => $existingSub['user_id']]);
        }
        
        $this->logWebhook('Subscription cancelled successfully', ['subscription_id' => $subscription_id]);
    }

    private function handleSubscriptionPaymentFailed($subscription)
    {
        $subscription_id = $subscription['id'] ?? null;
        $this->logWebhook('Subscription payment failed', ['subscription_id' => $subscription_id]);
        
        // Mark subscription as having payment issues
        if ($subscription_id) {
            $this->db->update('user_subscriptions', [
                'status' => 'pending',
                'updated_at' => date('Y-m-d H:i:s')
            ], ['paypal_subscription_id' => $subscription_id]);
        }
    }

    /**
     * Checks if the currently logged-in user has an active subscription to a specific plan.
     *
     * @param string $planId The PayPal Plan ID (P-...) to check against.
     * @return bool True if the user has an active subscription, false otherwise.
     */
    public function isUserSubscribedToPlan(string $planId): bool
    {
        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) {
            return false;
        }

        return $this->db->has('user_subscriptions', [
            "AND" => [
                'user_id' => $user_id,
                'paypal_plan_id' => $planId,
                'status' => 'active'
            ]
        ]);
    }

    /**
     * PUBLIC method to check the live status of the "Sai Code Daily" subscription.
     * This is the method you will call from your application.
     *
     * @return array An array containing the status and a user-friendly message.
     */
    public function checkSaiCodeDailyStatus(): array
    {
        // 1. Identify the user and the specific plan we are checking for.
        $userId = $_SESSION['user_id'] ?? null;
        $saiCodeDailyPlanId = 'P-43S89794RD1094113NAV52CA';

        if (!$userId) {
            return ['is_active' => false, 'message' => 'User not logged in.'];
        }

        // 2. Find the user's subscription ID from your local database.
        $localSubscription = $this->db->get('subscriptions', [
            'paypal_subscription_id',
            'status'
        ], [
            "AND" => [
                'user_id' => $userId,
                'paypal_plan_id' => $saiCodeDailyPlanId
            ],
            'ORDER' => ['created_at' => 'DESC'] // Get the most recent one
        ]);

        $lastQuery = $this->db->log();

        if (!$localSubscription) {
            return ['is_active' => false, 'message' => 'No subscription found for this plan.', 'last_query' => $lastQuery];
        }
        
        $subscriptionId = $localSubscription['paypal_subscription_id'];

        // 3. Fetch the live details for that subscription ID from PayPal.
        $paypalDetails = $this->getSubscriptionDetailsFromPayPal($subscriptionId);

        if ($paypalDetails === false) {
            // API call failed. We'll trust our local database as a fallback.
            $isActive = ($localSubscription['status'] === 'ACTIVE');
            $message = 'Could not contact PayPal. Status shown is based on last known record: ' . $localSubscription['status'];
            return ['is_active' => $isActive, 'message' => $message];
        }

        // 4. We have a live response from PayPal. This is the absolute truth.
        $liveStatus = $paypalDetails['status'] ?? 'UNKNOWN'; // e.g., 'ACTIVE', 'CANCELLED', 'SUSPENDED'
        
        // 5. (Self-Healing) If our local DB is out of sync, update it now.
        if ($liveStatus !== $localSubscription['status']) {
            $this->logWebhook('Syncing local status based on live check.', [
                'subscription_id' => $subscriptionId,
                'old_status' => $localSubscription['status'],
                'new_status' => $liveStatus
            ]);
            $this->db->update('subscriptions', 
                ['status' => $liveStatus], 
                ['paypal_subscription_id' => $subscriptionId]
            );
        }

        // 6. Return the final, authoritative result.
        if ($liveStatus === 'ACTIVE') {
            return ['is_active' => true, 'message' => 'Subscription is active on PayPal.'];
        } else {
            return ['is_active' => false, 'message' => 'PayPal reports subscription is not active. Status: ' . $liveStatus];
        }

        return [
        'is_active' => true, 
        'message' => 'PayPal confirmed your subscription is ACTIVE.', 
        'subscription_details' => $paypalDetails,
        'last_query' => $lastQuery // Add it here too
    ];
    }


    /**
     * PRIVATE helper method to fetch details of a specific subscription from the PayPal API.
     *
     * @param string $subscriptionId The PayPal Subscription ID (starts with 'I-').
     * @return array|false The subscription data as an array on success, or false on failure.
     */
    private function getSubscriptionDetailsFromPayPal(string $subscriptionId)
    {
        $access_token = $this->getPayPalAccessToken();
        if (!$access_token) {
            $this->logWebhook('Failed to get access token for direct subscription check.');
            return false;
        }

        $url = $this->paypal_urls[$this->paypal_environment]['api'] . '/v1/billing/subscriptions/' . $subscriptionId;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token,
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->logWebhook('Failed to fetch subscription details from PayPal.', [
                'subscription_id' => $subscriptionId,
                'http_code' => $httpCode,
                'response' => $result
            ]);
            return false;
        }

        return json_decode($result, true);
    }

    // ===================================================================
    // NEW METHOD TO DISPLAY THE STATUS PAGE (Your 'whatever' method)
    // ===================================================================
    public function saiCodeCheck()
    {
        // 1. Call our internal logic method to get the live status from PayPal.
        $statusData = $this->checkSaiCodeDailyStatus();
        
        // 2. Pass the entire result array to your view file.
        // Your framework's `$this->view()` will make the keys of this array
        // available as variables inside webhook.view.php (e.g., $is_active, $message).
        $this->view('webhook', ['webhook' => $statusData]);
    }

    /**
     * Handle incoming PayMongo webhook events.
     * Endpoint: POST /webhooks/paymongo
     *
     * Verifies the Paymongo-Signature header using HMAC-SHA256 and the
     * PAYMONGO_WEBHOOK_SECRET env var, then dispatches on event type.
     */
    public function paymongoWebhook()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            http_response_code(200);
            echo json_encode(['status' => 'paymongo webhook endpoint active']);
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit();
        }

        $rawBody = file_get_contents('php://input');
        if (empty($rawBody)) {
            http_response_code(400);
            echo json_encode(['error' => 'Empty body']);
            exit();
        }

        // Verify webhook signature
        $sigHeader = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';
        if (!\Ginto\Handlers\PayMongoHandler::verifyWebhookSignature($sigHeader, $rawBody)) {
            error_log('PayMongo webhook: invalid signature');
            http_response_code(401);
            echo json_encode(['error' => 'Invalid signature']);
            exit();
        }

        $event = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($event['data']['type'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            exit();
        }

        $eventType = $event['data']['type'] ?? '';
        error_log("PayMongo webhook received: {$eventType}");

        try {
            switch ($eventType) {
                case 'payment.paid':
                    $this->handlePaymongoPaymentPaid($event);
                    break;
                case 'payment.failed':
                    $this->handlePaymongoPaymentFailed($event);
                    break;
                case 'checkout_session.payment.paid':
                    $this->handleGintoPayCheckoutPaid($event);
                    break;
                default:
                    // Acknowledge unknown events
                    error_log("PayMongo webhook: unhandled event type {$eventType}");
                    break;
            }
        } catch (\Throwable $e) {
            error_log('PayMongo webhook error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal error']);
            exit();
        }

        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        exit();
    }

    /**
     * Handle payment.paid event from PayMongo.
     * Updates any pending subscription_payments record to 'completed'.
     */
    private function handlePaymongoPaymentPaid(array $event): void
    {
        $attributes = $event['data']['attributes']['data']['attributes'] ?? [];
        $piId       = $attributes['payment_intent_id'] ?? '';
        $paymentId  = $event['data']['attributes']['data']['id'] ?? null;
        $amount     = (int)($attributes['amount'] ?? 0); // centavos

        if (empty($piId)) {
            error_log('PayMongo payment.paid: missing payment_intent_id');
            return;
        }

        error_log("PayMongo payment.paid: pi_id={$piId}, amount={$amount}");

        // Mark any pending paymongo_qrph payments for this PI as completed
        $this->db->update('subscription_payments', [
            'status'   => 'completed',
            'paid_at'  => date('Y-m-d H:i:s'),
            'notes'    => 'Confirmed via PayMongo webhook',
            'updated_at' => date('Y-m-d H:i:s'),
        ], [
            'payment_reference' => $piId,
            'payment_method'    => 'paymongo_qrph',
            'status'            => 'pending',
        ]);

        // Standalone GintoPay card flow uses payment_intent_id as tracking key in pending_registrations.
        $pending = $this->db->get('pending_registrations', '*', [
            'checkout_session_id' => $piId,
            'status'              => 'pending',
        ]);

        if ($pending) {
            $this->processPendingRegistration(
                $pending,
                $piId,
                $piId,
                $paymentId,
                'payment.paid_webhook'
            );
        }

        // Mall QR checkout sessions also use PayMongo payment_intent_id as gateway reference.
        if (!empty($piId)) {
            try {
                $commerce = new MallCommerceService($this->db);
                $session = $commerce->getPaymentSessionByGatewayReference($piId);
                if ($session) {
                    $commerce->completePaymentSession((string)$session['session_ref'], [
                        'gateway' => 'paymongo',
                        'gateway_reference' => $piId,
                        'gateway_payment_id' => $paymentId,
                        'gateway_payload_json' => json_encode($event),
                    ]);
                }
            } catch (\Throwable $e) {
                error_log('PayMongo payment.paid mall finalization error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Handle payment.failed event from PayMongo.
     */
    private function handlePaymongoPaymentFailed(array $event): void
    {
        $attributes = $event['data']['attributes']['data']['attributes'] ?? [];
        $piId       = $attributes['payment_intent_id'] ?? '';

        if (empty($piId)) {
            return;
        }

        error_log("PayMongo payment.failed: pi_id={$piId}");

        $this->db->update('subscription_payments', [
            'status'     => 'failed',
            'notes'      => 'Failed via PayMongo webhook',
            'updated_at' => date('Y-m-d H:i:s'),
        ], [
            'payment_reference' => $piId,
            'payment_method'    => 'paymongo_qrph',
            'status'            => 'pending',
        ]);

        // Mark mall payment sessions as failed for matching PayMongo payment intent IDs.
        try {
            $commerce = new MallCommerceService($this->db);
            $commerce->failPaymentSessionByGatewayReference($piId, 'PayMongo payment.failed webhook');
        } catch (\Throwable $e) {
            error_log('PayMongo payment.failed mall finalization error: ' . $e->getMessage());
        }
    }

    // ===================================================================
    // LOGIC METHODS - These do the actual work
    // ===================================================================

    /**
     * Handle checkout_session.payment.paid event from PayMongo.
     * This is fired when a user completes payment on the hosted Ginto Pay checkout.
     * Creates the user account and subscription from the stored pending_registrations row.
     */
    private function handleGintoPayCheckoutPaid(array $event): void
    {
        // Event structure: data.attributes.data = { id: 'cs_xxx', attributes: { payments: [...] } }
        $sessionData      = $event['data']['attributes']['data'] ?? [];
        $checkoutSessionId = $sessionData['id'] ?? '';
        $sessionAttrs     = $sessionData['attributes'] ?? [];
        $payments         = $sessionAttrs['payments'] ?? [];

        if (empty($checkoutSessionId)) {
            error_log('GintoPay webhook: missing checkout_session_id in event');
            return;
        }

        error_log("GintoPay webhook checkout_session.payment.paid: cs_id={$checkoutSessionId}");

        // Extract PayMongo payment and payment_intent IDs from the payments array
        $gatewayPaymentId = null;
        $paymentIntentId  = null;
        if (!empty($payments[0]['id'])) {
            $gatewayPaymentId = $payments[0]['id'];
            $paymentIntentId  = $payments[0]['attributes']['payment_intent_id'] ?? null;
        }

        // Look up the pending registration by checkout_session_id
        $pending = $this->db->get('pending_registrations', '*', [
            'checkout_session_id' => $checkoutSessionId,
            'status'              => 'pending',
        ]);

        if (!$pending) {
            // Could be a duplicate delivery or an already-processed session — safe to ignore
            error_log("GintoPay webhook: no pending registration for cs_id={$checkoutSessionId} (already processed or unknown)");
            // Continue checking mall card checkout sessions which also use checkout_session IDs.
        } else {
            $this->processPendingRegistration(
                $pending,
                $checkoutSessionId,
                $paymentIntentId,
                $gatewayPaymentId,
                'checkout_session.payment.paid'
            );
        }

        // Mall card checkout sessions store gateway_reference as checkout_session_id.
        try {
            $commerce = new MallCommerceService($this->db);
            $session = $commerce->getPaymentSessionByGatewayReference($checkoutSessionId);
            if ($session) {
                $commerce->completePaymentSession((string)$session['session_ref'], [
                    'gateway' => 'paymongo',
                    'gateway_reference' => $checkoutSessionId,
                    'gateway_payment_id' => $gatewayPaymentId,
                    'gateway_payload_json' => json_encode($event),
                ]);
            }
        } catch (\Throwable $e) {
            error_log('PayMongo checkout_session.payment.paid mall finalization error: ' . $e->getMessage());
        }
    }

    /**
     * Create account/subscription/payment records from a pending registration row.
     */
    private function processPendingRegistration(
        array $pending,
        string $paymentReference,
        ?string $paymentIntentId,
        ?string $gatewayPaymentId,
        string $sourceEvent
    ): void {
        $regData = json_decode($pending['reg_data'] ?? '', true);
        if (empty($regData) || !is_array($regData)) {
            error_log("GintoPay webhook: corrupt reg_data for ref={$paymentReference}");
            $this->db->update('pending_registrations', ['status' => 'failed'], ['id' => $pending['id']]);
            return;
        }

        // Guard against duplicate processing
        $existing = $this->db->get('subscription_payments', 'id', ['payment_reference' => $paymentReference]);
        if ($existing) {
            $this->db->update('pending_registrations', [
                'status'       => 'completed',
                'processed_at' => date('Y-m-d H:i:s'),
            ], ['id' => $pending['id']]);
            return;
        }

        $planIdMap   = ['free' => 1, 'go' => 2, 'plus' => 3, 'pro' => 4, 'starter' => 1, 'professional' => 2, 'executive' => 3, 'gold' => 4, 'platinum' => 5];
        $packageName = strtolower($regData['package'] ?? 'go');
        $planId      = $planIdMap[$packageName] ?? 2;
        // Ginto Pay subscriptions are monthly-only.
        $duration    = '1m';
        $amountPhp   = (float)($pending['amount'] ?? $regData['amount'] ?? 0);
        $now         = date('Y-m-d H:i:s');
        $expiresAt   = date('Y-m-d H:i:s', strtotime('+1 month'));
        $publicId    = substr(md5(uniqid(mt_rand(), true)), 0, 12);

        try {
            $this->db->insert('users', [
                'email'             => $regData['email'],
                'username'          => $regData['username'],
                'password_hash'     => $regData['password_hash'],
                'fullname'          => $regData['fullname'],
                'phone'             => $regData['phone'],
                'country'           => $regData['country'],
                'referrer_id'       => $regData['referrer_id'] ?? null,
                'public_id'         => $publicId,
                'payment_status'    => 'paid',
                'subscription_plan' => $packageName,
                'created_at'        => $now,
            ]);

            $userId = $this->db->id();
            if (!$userId) {
                error_log("GintoPay webhook: failed to insert user for ref={$paymentReference}");
                return;
            }

            $transactionId = \Ginto\Helpers\TransactionHelper::generateTransactionId($this->db);
            $auditData     = \Ginto\Helpers\TransactionHelper::captureAuditData();

            $paymentNotes = json_encode([
                'email'               => $regData['email'],
                'username'            => $regData['username'],
                'fullname'            => $regData['fullname'],
                'phone'               => $regData['phone'],
                'country'             => $regData['country'],
                'billing_line1'       => $regData['billing_line1'] ?? '',
                'billing_line2'       => $regData['billing_line2'] ?? '',
                'billing_city'        => $regData['billing_city'] ?? '',
                'billing_state'       => $regData['billing_state'] ?? '',
                'billing_postal_code' => $regData['billing_postal_code'] ?? '',
                'billing_country'     => $regData['billing_country'] ?? ($regData['country'] ?? ''),
                'paymongo_session_id' => (strpos($paymentReference, 'cs_') === 0) ? $paymentReference : null,
                'paymongo_pi_id'      => $paymentIntentId,
                'paymongo_payment_id' => $gatewayPaymentId,
                'payment_gateway'     => 'paymongo',
                'payment_type'        => 'card',
                'duration'            => $duration,
                'source'              => $sourceEvent,
            ]);

            $this->db->insert('subscription_payments', array_merge([
                'user_id'            => $userId,
                'subscription_id'    => null,
                'plan_id'            => $planId,
                'type'               => 'registration',
                'amount'             => $amountPhp,
                'currency'           => 'PHP',
                'payment_method'     => 'ginto_pay_card',
                'payment_reference'  => $paymentReference,
                'gateway_payment_id' => $gatewayPaymentId,
                'status'             => 'paid',
                'notes'              => $paymentNotes,
                'transaction_id'     => $transactionId,
            ], $auditData));

            $paymentRowId = $this->db->id();
            if (!$paymentRowId) {
                $this->db->delete('users', ['id' => $userId]);
                error_log("GintoPay webhook: failed to insert payment for ref={$paymentReference}");
                return;
            }

            $this->db->insert('user_subscriptions', [
                'user_id'            => $userId,
                'plan_id'            => $planId,
                'status'             => 'active',
                'started_at'         => $now,
                'expires_at'         => $expiresAt,
                'payment_method'     => 'ginto_pay_card',
                'payment_reference'  => $paymentReference,
                'gateway_payment_id' => $gatewayPaymentId,
                'amount_paid'        => $amountPhp,
                'currency'           => 'PHP',
                'auto_renew'         => 1,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);

            $subscriptionId = $this->db->id();
            if ($subscriptionId) {
                $this->db->update('subscription_payments', ['subscription_id' => $subscriptionId], ['id' => $paymentRowId]);
            }

            $this->db->update('pending_registrations', [
                'status'       => 'completed',
                'user_id'      => $userId,
                'processed_at' => $now,
            ], ['id' => $pending['id']]);

            error_log("GintoPay webhook: account created user_id={$userId}, payment_id={$paymentRowId}, ref={$paymentReference}");
        } catch (\Throwable $e) {
            error_log('GintoPay webhook processPendingRegistration error: ' . $e->getMessage());
        }
    }

}