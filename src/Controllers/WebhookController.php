<?php
namespace App\Controllers;

use Core\Controller;
use Dotenv\Dotenv;
use Exception;

class WebhookController extends Controller
{
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

        // DEBUG: write resolved PayPal env sources for troubleshooting
        $dbg = "WEBHOOK CONSTRUCT: _ENV PAYPAL_CLIENT_ID=" . ($_ENV['PAYPAL_CLIENT_ID'] ?? 'NULL') . " | getenv PAYPAL_CLIENT_ID=" . (getenv('PAYPAL_CLIENT_ID') ?: 'NULL') . PHP_EOL;
        @file_put_contents(__DIR__ . '/../../../install_debug.log', $dbg, FILE_APPEND | LOCK_EX);

        // Assign .env values to class properties
        // Prefer $_ENV values (Dotenv may populate $_ENV) then fallback to getenv()
        $this->paypal_webhook_id = $_ENV['PAYPAL_WEBHOOK_ID'] ?? getenv('PAYPAL_WEBHOOK_ID');
        $this->paypal_client_id = $_ENV['PAYPAL_CLIENT_ID'] ?? getenv('PAYPAL_CLIENT_ID');
        $this->paypal_client_secret = $_ENV['PAYPAL_CLIENT_SECRET'] ?? getenv('PAYPAL_CLIENT_SECRET');
        $this->paypal_environment = $_ENV['PAYPAL_ENVIRONMENT'] ?? getenv('PAYPAL_ENVIRONMENT');

        // Optional DB injection for controllers that require DB access
        $this->db = $db ?? null;

        // --- VALIDATION BLOCK ---
        // Check if any of the required variables failed to load.
        if (
            empty($this->paypal_webhook_id) ||
            empty($this->paypal_client_id) ||
            empty($this->paypal_client_secret) ||
            empty($this->paypal_environment)
        ) {
            // Stop everything and throw a clear, specific error.
            // This is much better than letting other methods fail mysteriously.
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
    // ===================================================================
    // LOGIC METHODS - These do the actual work
    // ===================================================================

}