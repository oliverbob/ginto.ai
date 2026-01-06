<?php
namespace Ginto\Controllers;

use Ginto\Core\View;

/**
 * Subscription Controller
 * Handles subscription-related routes: upgrade, subscribe, success, activate
 */
class SubscriptionController
{
    protected $db;

    public function __construct($db = null)
    {
        if ($db === null) {
            $db = \Ginto\Core\Database::getInstance();
        }
        $this->db = $db;
        
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    /**
     * Upgrade Page (standalone upgrade/pricing page)
     */
    public function upgrade(): void
    {
        $isLoggedIn = !empty($_SESSION['user_id']);
        $userId = $_SESSION['user_id'] ?? 0;
        $isAdmin = \Ginto\Controllers\UserController::isAdmin();
        $username = $_SESSION['username'] ?? null;
        $userFullname = $_SESSION['fullname'] ?? $_SESSION['username'] ?? null;
        
        // Get subscription type from query param (masterclass, course, other)
        $subscriptionType = $_GET['type'] ?? 'other';
        if (!in_array($subscriptionType, ['registration', 'course', 'masterclass', 'other'])) {
            $subscriptionType = 'other';
        }
        
        // Get plans based on subscription type (masterclass plans are 2x courses)
        $courseController = new \Ginto\Controllers\CourseController($this->db);
        if ($subscriptionType === 'masterclass') {
            $masterclassController = new \Ginto\Controllers\MasterclassController($this->db);
            $plans = $masterclassController->getSubscriptionPlans();
        } else {
            $plans = $courseController->getSubscriptionPlans('courses');
        }
        $currentPlan = $isLoggedIn ? $courseController->getUserPlanName($userId) : 'free';
        
        \Ginto\Core\View::view('upgrade', [
            'title' => 'Upgrade | Ginto',
            'isLoggedIn' => $isLoggedIn,
            'isAdmin' => $isAdmin,
            'username' => $username,
            'userId' => $userId,
            'userFullname' => $userFullname,
            'plans' => $plans,
            'currentPlan' => $currentPlan,
            'subscriptionType' => $subscriptionType,
        ]);
    }

    /**
     * Subscribe Page - PayPal Checkout
     */
    public function subscribe(): void
    {
        $isLoggedIn = !empty($_SESSION['user_id']);
        $userId = $_SESSION['user_id'] ?? 0;
        
        $planName = $_GET['plan'] ?? null;
        $plan = null;
        $currentPlan = 'free';
        
        // Get subscription type from query param (masterclass, course, other)
        $subscriptionType = $_GET['type'] ?? 'other';
        if (!in_array($subscriptionType, ['registration', 'course', 'masterclass', 'other'])) {
            $subscriptionType = 'other';
        }
        
        // Map subscription type to plan_type in database
        $planType = ($subscriptionType === 'masterclass') ? 'masterclass' : 'courses';
        
        if ($planName) {
            $plan = $this->db->get('subscription_plans', '*', [
                'name' => $planName, 
                'plan_type' => $planType,
                'is_active' => 1
            ]);
        }
        
        if ($isLoggedIn) {
            $courseController = new \Ginto\Controllers\CourseController($this->db);
            $currentPlan = $courseController->getUserPlanName($userId);
        }
        
        // PayPal Plan IDs from .env or config
        $paypalPlanIds = [
            'go' => $_ENV['PAYPAL_PLAN_GO'] ?? getenv('PAYPAL_PLAN_GO') ?? '',
            'plus' => $_ENV['PAYPAL_PLAN_PLUS'] ?? getenv('PAYPAL_PLAN_PLUS') ?? '',
            'pro' => $_ENV['PAYPAL_PLAN_PRO'] ?? getenv('PAYPAL_PLAN_PRO') ?? '',
        ];
        
        // Use sandbox or live credentials based on environment
        $paypalEnv = $_ENV['PAYPAL_ENVIRONMENT'] ?? getenv('PAYPAL_ENVIRONMENT') ?? 'sandbox';
        if ($paypalEnv === 'sandbox') {
            $paypalClientId = $_ENV['PAYPAL_CLIENT_ID_SANDBOX'] ?? getenv('PAYPAL_CLIENT_ID_SANDBOX') ?? '';
        } else {
            $paypalClientId = $_ENV['PAYPAL_CLIENT_ID'] ?? getenv('PAYPAL_CLIENT_ID') ?? '';
        }
        
        \Ginto\Core\View::view('subscribe', [
            'title' => 'Subscribe | Ginto',
            'isLoggedIn' => $isLoggedIn,
            'userId' => $userId,
            'plan' => $plan,
            'currentPlan' => $currentPlan,
            'paypalClientId' => $paypalClientId,
            'paypalPlanIds' => $paypalPlanIds,
            'subscriptionType' => $subscriptionType,
        ]);
    }

    /**
     * Subscribe Success Page
     */
    public function success(): void
    {
        $subscriptionId = $_GET['subscription'] ?? null;
        $planName = 'Plus'; // Default
        
        if ($subscriptionId && !empty($_SESSION['user_id'])) {
            // Get subscription details from our database
            $subscription = $this->db->get('user_subscriptions', '*', ['paypal_subscription_id' => $subscriptionId]);
            if ($subscription) {
                $plan = $this->db->get('subscription_plans', ['display_name'], ['id' => $subscription['plan_id']]);
                $planName = $plan['display_name'] ?? 'Plus';
            }
        }
        
        \Ginto\Core\View::view('subscribe_success', [
            'title' => 'Subscription Successful | Ginto',
            'subscriptionId' => $subscriptionId,
            'planName' => $planName,
        ]);
    }

    /**
     * API: Subscription Activation (called after PayPal approval)
     */
    public function activate(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        // Origin validation for CSRF protection (production security)
        $appUrl = $_ENV['APP_URL'] ?? 'https://ginto.app';
        $prodUrl = $_ENV['PRODUCTION_URL'] ?? 'https://ginto.ai';
        $allowedOrigins = [
            $appUrl, 
            rtrim($appUrl, '/'),
            $prodUrl,
            rtrim($prodUrl, '/'),
            'https://ginto.ai',
            'https://www.ginto.ai',
            'http://localhost', 
            'http://localhost:8000', 
            'http://127.0.0.1:8000'
        ];
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $referer = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
        $appHost = parse_url($appUrl, PHP_URL_HOST);
        $prodHost = parse_url($prodUrl, PHP_URL_HOST);
        
        $originAllowed = in_array($origin, $allowedOrigins) || $origin === '';
        $refererAllowed = $referer === $appHost || $referer === $prodHost || $referer === 'ginto.ai' || $referer === 'www.ginto.ai' || $referer === 'localhost' || $referer === '127.0.0.1' || empty($referer);
        
        if (!$originAllowed && !$refererAllowed) {
            error_log("CSRF blocked: origin=$origin, referer=$referer, allowed_host=$appHost");
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden - invalid origin']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $subscriptionId = $input['subscription_id'] ?? null;
        $planName = $input['plan'] ?? null;
        $userId = $input['user_id'] ?? ($_SESSION['user_id'] ?? null);
        
        if (!$subscriptionId || !$planName || !$userId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields', 'received' => $input]);
            exit;
        }
        
        try {
            // Get plan details
            $plan = $this->db->get('subscription_plans', '*', ['name' => $planName, 'is_active' => 1]);
            if (!$plan) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid plan']);
                exit;
            }
            
            // Check if subscription already exists
            $existing = $this->db->get('user_subscriptions', 'id', ['paypal_subscription_id' => $subscriptionId]);
            if ($existing) {
                echo json_encode(['success' => true, 'message' => 'Subscription already activated']);
                exit;
            }
            
            // Cancel any existing active subscriptions for this user
            $this->db->update('user_subscriptions', [
                'status' => 'cancelled',
                'cancelled_at' => date('Y-m-d H:i:s')
            ], [
                'user_id' => $userId,
                'status' => 'active'
            ]);
            
            // Create new subscription
            $now = date('Y-m-d H:i:s');
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 month'));
            
            $this->db->insert('user_subscriptions', [
                'user_id' => $userId,
                'plan_id' => $plan['id'],
                'status' => 'active',
                'started_at' => $now,
                'expires_at' => $expiresAt,
                'payment_method' => 'paypal',
                'paypal_subscription_id' => $subscriptionId,
                'paypal_plan_id' => $input['paypal_plan_id'] ?? null,
                'amount_paid' => $plan['price_monthly'],
                'currency' => $plan['price_currency'] ?? 'PHP',
                'auto_renew' => 1,
                'created_at' => $now,
                'updated_at' => $now
            ]);
            
            $newSubId = $this->db->id();
            
            echo json_encode([
                'success' => true,
                'subscription_id' => $newSubId,
                'paypal_subscription_id' => $subscriptionId,
                'plan' => $plan['display_name']
            ]);
        } catch (\Throwable $e) {
            error_log("Subscription activation error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal error']);
        }
        exit;
    }
}
