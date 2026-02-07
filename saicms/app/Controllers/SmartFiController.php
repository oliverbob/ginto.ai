<?php
namespace App\Controllers;

use Core\Controller;
// use Medoo\Medoo; // Assuming your DB framework
use DBConnect; // Assuming this is how you get your DB connection

class SmartFiController extends Controller {

    public function __construct()
    {
        parent::__construct();
    }


    // This remains dedicated to your rewards/referral system.
    // Accessed via GET /smartfi

    /**
     * Commission percentages based on EARNER'S LEVEL (L1 is direct sale, L8 is the farthest upline).
     * Index 0 = L8 (0.25%) -> Index 7 = L1 (5.00% - direct sale)
     */
    private $commissionRates = [
        0.0025, // L8 (Index 0)
        0.0025, // L7 (Index 1)
        0.005,  // L6 (Index 2)
        0.01,   // L5 (Index 3)
        0.02,   // L4 (Index 4)
        0.03,   // L3 (Index 5)
        0.04,   // L2 (Index 6)
        0.05    // L1 (Index 7 - direct sale)
    ];
    
    // --- Standard Controller Methods ---

    public function smartFi() {
        if (!isset($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }
        $rewardsController = new RewardsController();
        $rewardsData = $rewardsController->getDashboardData();
        $this->view('dashboard/smartfi', [
            'rewards' => $rewardsData
        ]);
    }

    public function smartFi360() {
        if (!isset($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }
        $rewardsController = new RewardsController();
        $rewardsData = $rewardsController->getDashboardData();
        $this->view('dashboard/smartfi360', [
            'rewards' => $rewardsData
        ]);
    }

    public function tier() {
        $tierPlans = $this->db->select("tier_plans", "*");
        $this->view('dashboard/tier', [
            'tier_plans' => $tierPlans
        ]);
    }

    /**
     * Displays the tier selection and registration page.
     * Captures 't' as referrer ID but displays default/selected tier prices.
     *
     * GET /buytier?t={referrer_id}
     */
    public function buytier() {
        $tierPlans = $this->db->select("tier_plans", "*");

        // --- 1. Store the referral ID (t) in session to persist across steps/views ---
        $referrerId = $_GET['t'] ?? null;
        if ($referrerId) {
             // Use a distinct session key for the referrer ID
            $_SESSION['referrer_user_id'] = filter_var($referrerId, FILTER_VALIDATE_INT);
        }

        // --- 2. Determine which Tier to preselect (e.g., Tier 3 = Executive) ---
        // Since 't' is now the referrer ID, we must look for a dedicated tier parameter, 
        // or default to a standard tier (e.g., Executive = 3) if none is provided in the URL.
        // We'll look for a 'tier_id' parameter if 't' is not used for that purpose.
        $preselectedTierId = $_GET['tier_id'] ?? null; 
        $selectedTier = null;
        
        if ($preselectedTierId) {
            $selectedTier = $this->db->get("tier_plans", "*", ["id" => $preselectedTierId]);
        }
        
        // If the view relies on $_SESSION['tier_id'] for display/preselection logic:
        if ($selectedTier) {
            $_SESSION['tier_id'] = $selectedTier['id']; 
        }

        $this->view('dashboard/buytier', [
            'tier_plans' => $tierPlans,
            'selected_tier' => $selectedTier,
            // Pass referrer ID explicitly if the view needs it (e.g., hidden form field)
            'referrer_id' => $_SESSION['referrer_user_id'] ?? null 
        ]);
    }

    /**
     * Handles NEW USER registration with tier purchase
     * POST /buytier/process
     */
    public function processTierPurchase() {
        
        // --- 1. COLLECT DATA ---
        // Capture user info from the wizard (Assuming fields are posted from Step 2)
        $username = trim($_POST['username'] ?? 'user_' . uniqid());
        $email = trim($_POST['email'] ?? 'temp_' . uniqid() . '@example.com');
        $password = trim($_POST['password'] ?? 'secure_password'); 
        
        $tierPlanId = filter_var($_POST['tier_plan_id'] ?? null, FILTER_VALIDATE_INT);
        $paymentMethod = trim($_POST['payment_method_option'] ?? '');
        
        // Get Referrer ID from session (set in buytier)
        $referrerId = $_SESSION['referrer_user_id'] ?? null;
        $referrerId = filter_var($referrerId, FILTER_VALIDATE_INT);

        // --- 2. INITIAL VALIDATION ---
        if (!$tierPlanId || empty($paymentMethod) || empty($email) || empty($password)) {
            $_SESSION['error'] = "Missing required registration or purchase information.";
            // Redirect back to the purchase page using the current tier ID in session
            header('Location: /buytier?tier_id=' . ($_SESSION['tier_id'] ?? ''));
            exit;
        }
        
        $tierPlan = $this->db->get("tier_plans", "*", ["id" => $tierPlanId]);
        if (!$tierPlan) {
            $_SESSION['error'] = "Invalid tier plan.";
            header('Location: /buytier?tier_id=' . ($_SESSION['tier_id'] ?? ''));
            exit;
        }

        // --- 3. EXECUTE TRANSACTIONAL PROCESS ---
        $success = false;
        $transactionId = 'TXN_' . uniqid();
        $newUserId = null;

        $this->db->action(function($db) use ($username, $email, $password, $tierPlanId, $tierPlan, $paymentMethod, $referrerId, &$newUserId, &$transactionId, &$success) {
            
            // a) CREATE NEW USER
            $db->insert("users", [
                "username" => $username,
                "email" => $email,
                "password" => password_hash($password, PASSWORD_DEFAULT),
                "wallet_balance" => 0.00,
                "status" => 'active',
                "role" => 'user',
                "created_at" => date('Y-m-d H:i:s')
            ]);
            $newUserId = $db->id();
            
            if (!$newUserId) { return false; }

            // b) RECORD REFERRAL LINK (Uses the URL parameter captured in the session)
            if ($referrerId) {
                // Check if referrer exists (optional, but good integrity check)
                if ($db->has("users", ["id" => $referrerId])) {
                     $db->insert("referrals", [
                        "referred_user_id" => $newUserId,
                        "referrer_id" => $referrerId,
                        "status" => "completed"
                    ]);
                }
            }

            // c) PROCESS TIER PURCHASE
            $paymentStatus = ($paymentMethod === 'credit_card' || $paymentMethod === 'gcash') ? 'completed' : 'pending';
            
            $userTierData = [
                "user_id" => $newUserId,
                "tier_plan_id" => $tierPlanId,
                "purchase_price" => $tierPlan['price'],
                "payment_method" => $paymentMethod,
                "transaction_id" => $transactionId,
                "status" => $paymentStatus,
                "activated_at" => ($paymentStatus === 'completed') ? date('Y-m-d H:i:s') : null,
                "created_at" => date('Y-m-d H:i:s'),
                "updated_at" => date('Y-m-d H:i:s')
            ];
            
            $db->insert("user_tiers", $userTierData);
            $userTierId = $db->id();
            
            if (!$userTierId) { return false; }

            // d) CALCULATE COMMISSIONS
            if ($paymentStatus === 'completed' && $referrerId) {
                $this->calculateAndStoreCommissions(
                    $newUserId, 
                    $tierPlanId, 
                    $tierPlan['price'], 
                    $userTierId, 
                    $db
                );
            }
            
            $success = true;
            return true; // Commit transaction
        });

        // --- 4. REDIRECTION ---
        if ($success) {
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['tier_id'] = $tierPlanId; 
            $_SESSION['success'] = "Welcome! Your membership is active. Transaction ID: " . $transactionId;
            
            // Clean up temporary referrer session data
            unset($_SESSION['referrer_user_id']);
            
            header('Location: /dashboard');
            exit;
        } else {
            $_SESSION['error'] = "Failed to complete registration and purchase. Please contact support.";
            header('Location: /buytier?tier_id=' . $tierPlanId);
            exit;
        }
    }

    /**
     * Calculates and stores commissions for the upline based on a new tier purchase.
     */
    private function calculateAndStoreCommissions($newUserId, $tierPlanId, $purchasePrice, $userTierId, $db) {
        
        $sellerReferral = $db->get("referrals", ["referrer_id"], ["referred_user_id" => $newUserId]);
        $sellerUserId = $sellerReferral['referrer_id'] ?? null;

        if (!$sellerUserId) {
            return;
        }
        
        $currentEarnerId = $sellerUserId;
        $currentCommissionLevel = 8; 

        while ($currentEarnerId !== null && $currentCommissionLevel >= 1) {
            
            $earner_commission_level = (8 - $currentCommissionLevel) + 1; 

            $earnerTier = $db->get("user_tiers", ["tier_plan_id"], [
                "user_id" => $currentEarnerId, 
                "status" => "completed", 
                "ORDER" => ["activated_at" => "DESC"]
            ]);

            if ($earnerTier) {
                $earnerPlan = $db->get("tier_plans", ["commission_levels_access"], ["id" => $earnerTier['tier_plan_id']]);
                $earnerMaxLevelAccess = $earnerPlan['commission_levels_access'] ?? 0;

                if ($earner_commission_level <= $earnerMaxLevelAccess) {
                    
                    $commissionIndex = 8 - $earner_commission_level; 
                    $commissionPercentage = $this->commissionRates[$commissionIndex];
                    $amount = $purchasePrice * $commissionPercentage;
                    $percentDisplay = round($commissionPercentage * 100, 2); 

                    if ($amount > 0) {
                        
                        $db->insert("commissions", [
                            "earner_user_id" => $currentEarnerId,
                            "seller_user_id" => $newUserId,
                            "tier_purchase_id" => $userTierId,
                            "commission_level" => $earner_commission_level,
                            "commission_percentage" => $percentDisplay,
                            "amount" => round($amount, 2),
                            "status" => "pending",
                            "created_at" => date('Y-m-d H:i:s')
                        ]);
                    } 
                }
            }

            $nextEarnerReferral = $db->get("referrals", ["referrer_id"], ["referred_user_id" => $currentEarnerId]);
            $currentEarnerId = $nextEarnerReferral['referrer_id'] ?? null;
            $currentCommissionLevel--;
        }
    }

    // Make sure this is the method being called by your route (e.g., /analytics).
    public function showAnalyticsDashboard() 
    {
        // In a real application, you would fetch all this data from your database.
        // For this example, we use a complete set of mock data to ensure the page looks perfect.

        $view_data = [];

        // Data for the header
        $view_data['wallet_balance'] = 12530.50;
        $view_data['gift_balance'] = 500.00;
        $view_data['announcement'] = [
            'title' => 'System Update',
            'message' => 'New projection models will be deployed this weekend. Minor disruptions may occur.'
        ];

        // Data for the "Available Plans" card, as you specified
        $view_data['wifi_plans'] = [
            ['id' => 1, 'category' => 'Hourly', 'name' => '30 Minutes', 'duration_text' => '30 mins access', 'price' => 20.00, 'usage_hint' => 'For quick social media checks.'],
            ['id' => 2, 'category' => 'Hourly', 'name' => '1 Hour', 'duration_text' => 'Full hour access', 'price' => 15.00, 'usage_hint' => 'Best value for quick browsing.'],
            ['id' => 3, 'category' => 'Hourly', 'name' => '2 Hours', 'duration_text' => 'Extended session', 'price' => 25.00, 'usage_hint' => 'Good for short meetings.'],
            ['id' => 4, 'category' => 'Hourly', 'name' => '5 Hours', 'duration_text' => 'Half-day power session', 'price' => 50.00, 'usage_hint' => 'Perfect for focused work.'],
            ['id' => 5, 'category' => 'Hourly', 'name' => '10 Hours', 'duration_text' => 'Great for long projects', 'price' => 90.00, 'usage_hint' => 'Excellent bulk hour value.'],
            ['id' => 6, 'category' => 'Hourly', 'name' => '12 Hours', 'duration_text' => 'All-day access', 'price' => 100.00, 'usage_hint' => 'Covers your entire workday.'],
            ['id' => 7, 'category' => 'Daily', 'name' => '1 Day', 'duration_text' => '24 hours of internet', 'price' => 190.00, 'usage_hint' => 'Uninterrupted 24-hour access.', 'badge' => 'Most Popular', 'badge_color' => 'bg-green-600'],
            ['id' => 8, 'category' => 'Daily', 'name' => '2 Days', 'duration_text' => '48-hour weekend pass', 'price' => 380.00, 'usage_hint' => 'Cover your entire weekend.'],
            ['id' => 9, 'category' => 'Weekly', 'name' => '5 Days', 'duration_text' => 'Full work week', 'price' => 600.00, 'usage_hint' => 'Ideal for business travelers.'],
            ['id' => 10, 'category' => 'Weekly', 'name' => '7 Days', 'duration_text' => 'Full week of access', 'price' => 800.00, 'badge' => 'Best Value', 'badge_color' => 'bg-yellow-500', 'usage_hint' => 'Our best weekly rate.'],
        ];
        $view_data['plan_categories'] = ['Hourly', 'Daily', 'Weekly'];

        // Data for the right-hand side stacked cards
        $view_data['recent_transactions'] = [
            ['plan' => '1 Day', 'user' => ['name' => 'John Doe'], 'amount' => 190.00],
            ['plan' => '5 Hours', 'user' => ['name' => 'Jane Smith'], 'amount' => 50.00],
        ];
        $view_data['top_locations'] = [
            ['name' => 'Main Building Lobby', 'users' => 25],
            ['name' => 'Cafeteria Hotspot', 'users' => 12],
            ['name' => 'Library Quiet Zone', 'users' => 5],
        ];

        // Data for the bottom charts
        $view_data['revenue_chart_data'] = ['Jan'=>420, 'Feb'=>510, 'Mar'=>390, 'Apr'=>620, 'May'=>710, 'Jun'=>850, 'Jul'=>920];
        $view_data['plan_popularity'] = [
            ['name' => '1 Day', 'count' => 156, 'color' => '#10B981'],
            ['name' => '1 Hour', 'count' => 112, 'color' => '#3B82F6'],
            ['name' => '7 Days', 'count' => 45, 'color' => '#F59E0B'],
            ['name' => 'Other', 'count' => 30, 'color' => '#6B7280'],
        ];

        // This renders the view, passing ALL the data it needs.
        $this->view('dashboard/analytics', $view_data);
    }

    /**
     * [USER] Called when a user buys a wifi plan from the website.
     * POST /saifi/purchase (CSRF PROTECTED)
     */
    public function purchaseWifi() {
        if (!isset($_SESSION['user_id'])) {
            $this->jsonResponse(401, ['error' => 'You must be logged in to purchase.']);
        }
        $userId = $_SESSION['user_id'];
        $planId = (int)($_POST['plan_id'] ?? 0);
        $machineId = (int)($_POST['machine_id'] ?? 0);
        $plan = $this->db->get("wifi_plans", ["price", "duration_minutes", "name"], ["id" => $planId, "is_active" => 1]);
        if (!$plan) { $this->jsonResponse(404, ['error' => 'Invalid or inactive WiFi plan.']); }
        $user = $this->db->get("users", ["wallet_balance"], ["id" => $userId]);
        if ($user['wallet_balance'] < $plan['price']) { $this->jsonResponse(402, ['error' => 'Insufficient wallet balance.']); }
        $this->db->action(function($db) use ($userId, $plan, $planId, $machineId) {
            $db->update("users", ["wallet_balance[-]" => $plan['price']], ["id" => $userId]);
            $expiresAt = date('Y-m-d H:i:s', time() + ($plan['duration_minutes'] * 60));
            $sessionId = $db->insert("wifi_sessions", ["user_id" => $userId, "vendo_machine_id" => $machineId, "wifi_plan_id" => $planId, "status" => 'active', "expires_at" => $expiresAt]);
            $db->insert("transactions", ["user_id" => $userId, "type" => 'debit', "amount" => $plan['price'], "description" => "WiFi Purchase: " . $plan['name'], "related_id" => $sessionId]);
            return true;
        });
        $this->jsonResponse(200, ['success' => true, 'message' => 'WiFi time purchased successfully!']);
    }

    /**
     * [API] Called by the Vendo Machine to update its status.
     * POST /saifi/status (NO CSRF)
     */
    public function statusUpdate() {
        $machine = $this->getAuthenticatedMachine();
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) { $this->jsonResponse(400, ['error' => 'Invalid JSON body']); }
        $this->db->update("vendo_machines", ["status" => "online", "active_users" => (int)($input['active_users'] ?? 0), "uptime_seconds" => (int)($input['uptime_seconds'] ?? 0), "last_heartbeat" => date('Y-m-d H:i:s')], ["id" => $machine['id']]);
        $this->jsonResponse(200, ['status' => 'ok', 'message' => 'Heartbeat received']);
    }

    /**
     * [API] Called by Vendo Machine to check if a user should have access.
     * POST /saifi/check-access (NO CSRF)
     */
    public function checkAccess() {
        $machine = $this->getAuthenticatedMachine();
        $input = json_decode(file_get_contents('php://input'), true);
        $username = $input['username'] ?? null;
        if (!$username) { $this->jsonResponse(400, ['error' => 'Username identifier is required.']); }
        $user = $this->db->get("users", "id", ["email" => $username]);
        if (!$user) { $this->jsonResponse(200, ['access' => 'denied', 'reason' => 'User not found']); }
        $now = date('Y-m-d H:i:s');
        $session = $this->db->get("wifi_sessions", "*", ["user_id" => $user['id'], "status" => "active", "expires_at[>]" => $now, "ORDER" => ["expires_at" => "DESC"]]);
        if ($session) {
            $remainingSeconds = strtotime($session['expires_at']) - time();
            $this->jsonResponse(200, ['access' => 'granted', 'duration_seconds' => $remainingSeconds > 0 ? $remainingSeconds : 0]);
        } else {
            $this->jsonResponse(200, ['access' => 'denied', 'reason' => 'No active session found']);
        }
    }

    /**
     * [ADMIN] Get the status of all Vendo Machines.
     * GET /saifi/status
     */
    public function getStatus() {
        if (!isset($_SESSION['user_id'])) { $this->jsonResponse(401, ['error' => 'Unauthorized']); }
        $machines = $this->db->select("vendo_machines", ["id", "name", "location", "status", "active_users", "uptime_seconds", "last_heartbeat"]);
        $this->jsonResponse(200, ['machines' => $machines]);
    }

    /**
     * Helper function to send a standard JSON response and exit.
     */
    private function jsonResponse(int $statusCode, array $data)
    {
        // Prevent output buffering issues
        if (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Helper function to get the authenticated machine via API Key.
     * Exits with an error response if authentication fails.
     * @return array The database record for the authenticated machine.
     */
    private function getAuthenticatedMachine()
    {
        // Vendo machines must send their key in the X-API-KEY header
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
        if (!$apiKey) {
            $this->jsonResponse(401, ['error' => 'Unauthorized: API Key is missing from headers.']);
        }

        $machine = $this->db->get("vendo_machines", ["id", "api_key"], ["api_key" => $apiKey]);
        if (!$machine) {
            $this->jsonResponse(403, ['error' => 'Forbidden: Invalid API Key.']);
        }
        return $machine;
    }
}