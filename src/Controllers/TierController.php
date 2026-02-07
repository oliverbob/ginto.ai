<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;
use Core\Controller;

class TierController extends \Core\Controller
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->requireAdmin();
    }

    /**
     * List membership tiers (paginated)
     */
    public function index()
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $where = [
            'ORDER' => ['id' => 'DESC'],
            'LIMIT' => [$offset, $perPage]
        ];

        try {
            $tiers = $this->db->select('tier_plans', '*', $where);
            unset($where['ORDER'], $where['LIMIT']);
            $totalCount = $this->db->count('tier_plans', $where);
            $totalPages = max(1, (int)ceil($totalCount / $perPage));
        } catch (\Throwable $e) {
            $tiers = [];
            $totalCount = 0;
            $totalPages = 1;
        }

        $this->view('admin/tier/index', [
            'title' => 'Tiers',
            'tiers' => $tiers,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    /**
     * Create a new tier (POST)
     */
    public function store()
    {
        error_log('TierController::store called by user_id=' . ($_SESSION['user_id'] ?? 'guest'));
        // Ensure logs directory exists and write to both storage root and storage/logs
        $logDir = STORAGE_PATH . '/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        $msg = "store called by user_id=" . ($_SESSION['user_id'] ?? 'guest') . "\n";
        @file_put_contents(STORAGE_PATH . '/debug-tier.log', $msg, FILE_APPEND);
        @file_put_contents($logDir . '/debug-tier.log', $msg, FILE_APPEND);
        // Fallback: also send to configured PHP error log so it always appears in ginto.log
        error_log($msg);
        // Temporary debug: also write to /tmp so we can verify execution from outside webroot
        @file_put_contents('/tmp/debug-tier-start.log', $msg, FILE_APPEND);

        if (!$this->verifyCsrfToken()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $costAmount = isset($_POST['cost_amount']) ? floatval($_POST['cost_amount']) : (isset($_POST['amount']) ? floatval($_POST['amount']) : 0);
        $costCurrency = trim($_POST['cost_currency'] ?? ($_POST['currency'] ?? 'PHP')) ?: 'PHP';
        $setupFee = isset($_POST['setup_fee']) ? floatval($_POST['setup_fee']) : 0;
        $recurringAmount = isset($_POST['recurring_amount']) ? floatval($_POST['recurring_amount']) : null;
        $billingInterval = trim($_POST['billing_interval'] ?? 'MONTH') ?: 'MONTH';
        if (!in_array($billingInterval, ['MONTH', 'YEAR'])) $billingInterval = 'MONTH';
        $commissionJson = trim($_POST['commission_rate_json'] ?? '');
        $paypalPlanId = trim($_POST['paypal_plan_id'] ?? '');
        $paypalPlanIdSandbox = trim($_POST['paypal_plan_id_sandbox'] ?? '');

        error_log('TierController::store payload: name=' . substr($name,0,100) . ' amount=' . $costAmount . ' currency=' . $costCurrency . ' setup_fee=' . $setupFee);

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Tier name is required']);
            return;
        }

        if ($commissionJson !== '') {
            json_decode($commissionJson);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid JSON for commission rates']);
                return;
            }
        } else {
            // DB requires a non-null commission JSON; use empty JSON object
            $commissionJson = '{}';
        }

        // Normalize recurring: if missing, non-numeric, or <= 0, use cost amount as fallback
        $recurringValidated = (is_numeric($recurringAmount) && (float)$recurringAmount > 0) ? (float)$recurringAmount : (float)$costAmount;

        $data = [
            'name' => $name,
            'cost_amount' => $costAmount ?: 0,
            'setup_fee' => $setupFee ?: 0,
            'recurring_amount' => $recurringValidated,
            'billing_interval' => $billingInterval ?: 'MONTH',
            'cost_currency' => $costCurrency ?: 'PHP',
            'commission_rate_json' => $commissionJson,
            'paypal_plan_id' => $paypalPlanId ?: null,
            'paypal_plan_id_sandbox' => $paypalPlanIdSandbox ?: null,
            'created_at' => (new \DateTime('now', new \DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s')
        ];

        // Filter to only allowed columns to avoid inserting unexpected keys (eg. short_name)
        $allowedColumns = [
            'name','cost_amount','setup_fee','recurring_amount','billing_interval',
            'cost_currency','commission_rate_json','paypal_plan_id','paypal_plan_id_sandbox',
            'created_at'
        ];
        $data = array_intersect_key($data, array_flip($allowedColumns));

        try {
            // Try inserting with retry on duplicate-name (unique constraint) errors
            $insertAttempts = 3;
            $baseName = $data['name'];
            $newId = null;
            $lastException = null;

            for ($attempt = 1; $attempt <= $insertAttempts; $attempt++) {
                try {
                    // Debug: record the prepared data before DB insert
                    @file_put_contents('/tmp/debug-tier-before-insert.log', var_export($data, true) . "\n", FILE_APPEND);

                    $this->db->insert('tier_plans', $data);
                    $newId = $this->db->id();

                    // Debug: record the new inserted ID
                    @file_put_contents('/tmp/debug-tier-after-insert.log', "newId=" . ($newId ?? 'NULL') . "\n", FILE_APPEND);

                    // Insert succeeded; break out of retry loop
                    break;
                } catch (\Throwable $e) {
                    // Detect duplicate-entry / unique constraint errors (MySQL error code 1062)
                    $isDuplicate = false;
                    if ($e instanceof \PDOException) {
                        $errNo = $e->errorInfo[1] ?? null;
                        if ($errNo === 1062) $isDuplicate = true;
                    }
                    if (!$isDuplicate && strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        $isDuplicate = true;
                    }

                    if ($isDuplicate && $attempt < $insertAttempts) {
                        // Generate a short random suffix and retry with modified name
                        try {
                            $suffix = substr(bin2hex(random_bytes(3)), 0, 6);
                        } catch (\Throwable $r) {
                            $suffix = time();
                        }
                        $data['name'] = $baseName . ' ' . $suffix;
                        @file_put_contents('/tmp/debug-tier-duplicate.log', "Duplicate name detected on attempt {$attempt}; retrying with name={$data['name']}\n", FILE_APPEND);
                        // continue to next attempt
                        continue;
                    }

                    // Not a duplicate or out of attempts - remember exception and break
                    $lastException = $e;
                    break;
                }
            }

            if (empty($newId)) {
                if ($lastException) throw $lastException;
                throw new \Exception('Failed to insert tier (unknown error)');
            }

            // Auto-create PayPal plans if missing
            $created = [];
            // Use the final name value used for the DB row (may have been modified on duplicate retries)
            $finalName = $data['name'] ?? $name;

            if (empty($paypalPlanId)) {
                $planId = $this->createPaypalPlan($finalName, ($commissionJson ?: ''), $costCurrency, $setupFee, $recurringAmount ?? $costAmount, $billingInterval, 'live');
                if ($planId) {
                    $this->db->update('tier_plans', ['paypal_plan_id' => $planId], ['id' => $newId]);
                    $created['paypal_plan_id'] = $planId;
                }
            }

            if (empty($paypalPlanIdSandbox)) {
                $planIdSandbox = $this->createPaypalPlan($finalName, ($commissionJson ?: ''), $costCurrency, $setupFee, $recurringAmount ?? $costAmount, $billingInterval, 'sandbox');
                if ($planIdSandbox) {
                    $this->db->update('tier_plans', ['paypal_plan_id_sandbox' => $planIdSandbox], ['id' => $newId]);
                    $created['paypal_plan_id_sandbox'] = $planIdSandbox;
                }
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Tier created successfully', 'paypal_created' => $created]);
        } catch (\Throwable $e) {
            // Log and return exception details for debugging
            error_log('TierController::store exception: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            $logDir = STORAGE_PATH . '/logs';
            if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
            $msg = "exception: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
            @file_put_contents(STORAGE_PATH . '/debug-tier.log', $msg, FILE_APPEND);
            @file_put_contents($logDir . '/debug-tier.log', $msg, FILE_APPEND);
            // Fallback: ensure exception is also recorded in main log
            error_log($msg);
            // Temporary debug: write exception details to /tmp as well so we can inspect
            @file_put_contents('/tmp/debug-tier-exception.log', $msg, FILE_APPEND);
            http_response_code(500);
            // Return exception message in the response (temporary for debugging)
            echo json_encode(['success' => false, 'error' => 'Failed to create tier', 'exception' => $e->getMessage()]);
        }
    }

    /**
     * Update a tier (POST)
     */
    public function update($id)
    {
        if (!$this->verifyCsrfToken()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $existing = $this->db->get('tier_plans', '*', ['id' => $id]);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Tier not found']);
            return;
        }

        $name = trim($_POST['name'] ?? $existing['name']);
        $costAmount = isset($_POST['cost_amount']) ? floatval($_POST['cost_amount']) : ($existing['cost_amount'] ?? ($existing['amount'] ?? 0));
        $costCurrency = trim($_POST['cost_currency'] ?? $existing['cost_currency'] ?? ($existing['currency'] ?? 'PHP')) ?: 'PHP';
        $setupFee = isset($_POST['setup_fee']) ? floatval($_POST['setup_fee']) : ($existing['setup_fee'] ?? 0);
        $recurringAmount = isset($_POST['recurring_amount']) ? floatval($_POST['recurring_amount']) : ($existing['recurring_amount'] ?? null);
        $billingInterval = trim($_POST['billing_interval'] ?? $existing['billing_interval'] ?? 'MONTH') ?: 'MONTH';
        if (!in_array($billingInterval, ['MONTH', 'YEAR'])) $billingInterval = 'MONTH';
        $commissionJson = trim($_POST['commission_rate_json'] ?? $existing['commission_rate_json'] ?? '');
        $paypalPlanId = trim($_POST['paypal_plan_id'] ?? $existing['paypal_plan_id'] ?? '');
        $paypalPlanIdSandbox = trim($_POST['paypal_plan_id_sandbox'] ?? $existing['paypal_plan_id_sandbox'] ?? '');

        if ($commissionJson !== '') {
            json_decode($commissionJson);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid JSON for commission rates']);
                return;
            }
        } else {
            $commissionJson = null;
        }

        // Normalize recurring for update: prefer provided recurring when > 0, otherwise keep existing or fallback to cost_amount
        $recurringValidated = (is_numeric($recurringAmount) && (float)$recurringAmount > 0) ? (float)$recurringAmount : (($existing['recurring_amount'] ?? 0) > 0 ? (float)$existing['recurring_amount'] : (float)$costAmount);

        $data = [
            'name' => $name,
            'cost_amount' => $costAmount ?: 0,
            'setup_fee' => $setupFee ?: 0,
            'recurring_amount' => $recurringValidated,
            'billing_interval' => $billingInterval ?: 'MONTH',
            'cost_currency' => $costCurrency ?: 'PHP',
            'commission_rate_json' => $commissionJson,
            'paypal_plan_id' => $paypalPlanId ?: null,
            'paypal_plan_id_sandbox' => $paypalPlanIdSandbox ?: null,
            'updated_at' => (new \DateTime('now', new \DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s')
        ];

        // Only keep allowed columns for update as well
        $allowedColumnsUpdate = [
            'name','cost_amount','setup_fee','recurring_amount','billing_interval',
            'cost_currency','commission_rate_json','paypal_plan_id','paypal_plan_id_sandbox',
            'updated_at'
        ];
        $data = array_intersect_key($data, array_flip($allowedColumnsUpdate));

        try {
            $this->db->update('tier_plans', $data, ['id' => $id]);

            // If PayPal plan IDs were empty and billing data changed, try creating missing plans
            if (empty($paypalPlanId)) {
                $planId = $this->createPaypalPlan($name, ($commissionJson ?: ''), $costCurrency, $setupFee, $recurringAmount ?? $costAmount, $billingInterval, 'live');
                if ($planId) {
                    $this->db->update('tier_plans', ['paypal_plan_id' => $planId], ['id' => $id]);
                }
            }
            if (empty($paypalPlanIdSandbox)) {
                $planIdSandbox = $this->createPaypalPlan($name, ($commissionJson ?: ''), $costCurrency, $setupFee, $recurringAmount ?? $costAmount, $billingInterval, 'sandbox');
                if ($planIdSandbox) {
                    $this->db->update('tier_plans', ['paypal_plan_id_sandbox' => $planIdSandbox], ['id' => $id]);
                }
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Tier updated successfully']);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update tier']);
        }
    }

    /**
     * Delete a tier (POST)
     */
    public function delete($id)
    {
        if (!$this->verifyCsrfToken()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $existing = $this->db->get('tier_plans', '*', ['id' => $id]);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Tier not found']);
            return;
        }

        $paypalIds = [
            'live' => $existing['paypal_plan_id'] ?? null,
            'sandbox' => $existing['paypal_plan_id_sandbox'] ?? null,
        ];

        $paypalResults = [];
        // Attempt to deactivate PayPal plans (best-effort)
        foreach ($paypalIds as $env => $pid) {
            if (!empty($pid)) {
                try {
                    $ok = $this->deletePaypalPlan($pid, $env);
                    $paypalResults[$env] = $ok ? 'deactivated' : 'failed';
                } catch (\Throwable $e) {
                    $paypalResults[$env] = 'error';
                    error_log('deletePaypalPlan exception for ' . $env . ': ' . $e->getMessage());
                }
            } else {
                $paypalResults[$env] = 'none';
            }
        }

        try {
            $this->db->delete('tier_plans', ['id' => $id]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Tier deleted successfully', 'paypal' => $paypalResults]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to delete tier', 'paypal' => $paypalResults]);
        }
    }

    /**
     * Deactivate a PayPal plan (best-effort). Returns true on success.
     */
    private function deletePaypalPlan(string $planId, string $environment = 'sandbox'): bool
    {
        // Ensure .env is loaded when running in web context
        if ((getenv('PAYPAL_CLIENT_ID') === false || getenv('PAYPAL_CLIENT_ID') === null) && class_exists('\Dotenv\Dotenv')) {
            try {
                $root = dirname(__DIR__, 2);
                if (file_exists($root . '/.env')) {
                    \Dotenv\Dotenv::createImmutable($root)->safeLoad();
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if ($environment === 'sandbox') {
            $clientId = getenv('PAYPAL_CLIENT_ID_SANDBOX') ?: $_ENV['PAYPAL_CLIENT_ID_SANDBOX'] ?? null;
            $clientSecret = getenv('PAYPAL_CLIENT_SECRET_SANDBOX') ?: $_ENV['PAYPAL_CLIENT_SECRET_SANDBOX'] ?? null;
            $baseUrl = 'https://api-m.sandbox.paypal.com';
        } else {
            $clientId = getenv('PAYPAL_CLIENT_ID') ?: $_ENV['PAYPAL_CLIENT_ID'] ?? null;
            $clientSecret = getenv('PAYPAL_CLIENT_SECRET') ?: $_ENV['PAYPAL_CLIENT_SECRET'] ?? null;
            $baseUrl = 'https://api-m.paypal.com';
        }

        if (empty($clientId) || empty($clientSecret)) {
            error_log('PayPal credentials missing for delete operation: ' . $environment);
            return false;
        }

        // Get access token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_USERPWD, $clientId . ':' . $clientSecret);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Accept-Language: en_US']);
        $tokenResp = curl_exec($ch);
        $tokenCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $tokenErr = curl_error($ch);
        curl_close($ch);

        if ($tokenErr) {
            error_log('PayPal token error during delete for ' . $environment . ': ' . $tokenErr);
            return false;
        }
        if ($tokenCode !== 200) {
            error_log('PayPal token HTTP ' . $tokenCode . ' during delete for ' . $environment . ' resp=' . substr((string)$tokenResp,0,400));
            return false;
        }
        $tokenData = json_decode($tokenResp, true) ?: [];
        $accessToken = $tokenData['access_token'] ?? null;
        if (empty($accessToken)) {
            error_log('No PayPal access token for delete ' . $environment);
            return false;
        }

        // Deactivate plan
        $ch = curl_init($baseUrl . '/v1/billing/plans/' . urlencode($planId) . '/deactivate');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            error_log('PayPal deactivate cURL error for ' . $environment . ': ' . $curlErr);
            return false;
        }

        if (in_array($httpCode, [200, 204])) {
            return true;
        }

        error_log('PayPal deactivate returned HTTP ' . $httpCode . ' for ' . $environment . ' resp=' . substr((string)$resp,0,1000));
        return false;
    }

    private function requireAdmin()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $user = $this->db->get('users', ['role_id'], ['id' => $_SESSION['user_id']]);
        if (!$user || !in_array($user['role_id'], [1, 2])) {
            http_response_code(403);
            echo '<h1>403 Forbidden</h1>';
            exit;
        }
    }

    private function generateCsrfToken()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    private function verifyCsrfToken()
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return $token && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Create a PayPal subscription plan for given tier data. Returns the created plan ID or null on failure.
     */
    private function createPaypalPlan(string $name, string $description, string $currency, float $setupFee, $recurringAmount, string $billingInterval = 'MONTH', string $environment = 'sandbox') : ?string
    {
        // Ensure .env is loaded when running in web context (match bin/setup_paypal_plans.php behavior)
        if ((getenv('PAYPAL_CLIENT_ID') === false || getenv('PAYPAL_CLIENT_ID') === null) && class_exists('\Dotenv\Dotenv')) {
            try {
                $root = dirname(__DIR__, 2);
                if (file_exists($root . '/.env')) {
                    \Dotenv\Dotenv::createImmutable($root)->safeLoad();
                }
            } catch (\Throwable $e) {
                // ignore dotenv load errors and fall back to getenv/$_ENV
            }
        }

        // Determine credentials
        if ($environment === 'sandbox') {
            $clientId = getenv('PAYPAL_CLIENT_ID_SANDBOX') ?: $_ENV['PAYPAL_CLIENT_ID_SANDBOX'] ?? null;
            $clientSecret = getenv('PAYPAL_CLIENT_SECRET_SANDBOX') ?: $_ENV['PAYPAL_CLIENT_SECRET_SANDBOX'] ?? null;
            $baseUrl = 'https://api-m.sandbox.paypal.com';
        } else {
            $clientId = getenv('PAYPAL_CLIENT_ID') ?: $_ENV['PAYPAL_CLIENT_ID'] ?? null;
            $clientSecret = getenv('PAYPAL_CLIENT_SECRET') ?: $_ENV['PAYPAL_CLIENT_SECRET'] ?? null;
            $baseUrl = 'https://api-m.paypal.com';
        }

        if (empty($clientId) || empty($clientSecret)) {
            error_log('PayPal credentials missing for environment: ' . $environment);
            return null;
        }

        // Get access token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_USERPWD, $clientId . ':' . $clientSecret);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Accept-Language: en_US']);
        $tokenResp = curl_exec($ch);
        $tokenCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $tokenErr = curl_error($ch);
        curl_close($ch);

        if ($tokenErr) {
            error_log('PayPal token cURL error for ' . $environment . ': ' . $tokenErr);
            @file_put_contents('/tmp/debug-paypal-token.log', "tokenErr={$tokenErr}\nresp=" . substr((string)$tokenResp,0,1000) . "\n", FILE_APPEND);
            return null;
        }

        if ($tokenCode !== 200) {
            error_log('PayPal token request failed for ' . $environment . ' code=' . $tokenCode . ' resp=' . substr((string)$tokenResp, 0, 400));
            @file_put_contents('/tmp/debug-paypal-token.log', "code={$tokenCode}\nresp=" . substr((string)$tokenResp,0,2000) . "\n", FILE_APPEND);
            return null;
        }

        $tokenData = json_decode($tokenResp, true) ?: [];
        $accessToken = $tokenData['access_token'] ?? null;
        if (empty($accessToken)) {
            error_log('PayPal access token missing for ' . $environment);
            @file_put_contents('/tmp/debug-paypal-token.log', "no access token\nresp=" . substr((string)$tokenResp,0,2000) . "\n", FILE_APPEND);
            return null;
        }

        // Try creating a product first to attach to the plan
        $productPayload = [
            'name' => 'Ginto Subscription',
            'type' => 'SERVICE',
            'description' => 'Subscription product for ' . $name,
            'category' => 'SOFTWARE',
        ];

        $ch = curl_init($baseUrl . '/v1/catalogs/products');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($productPayload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        $prodResp = curl_exec($ch);
        $prodCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $prodErr = curl_error($ch);
        curl_close($ch);

        $productId = null;
        if ($prodErr) {
            error_log('PayPal product create cURL error for ' . $environment . ': ' . $prodErr);
            @file_put_contents('/tmp/debug-paypal-product.log', "err={$prodErr}\nresp=" . substr((string)$prodResp,0,2000) . "\n", FILE_APPEND);
        } elseif ($prodCode >= 400) {
            error_log('PayPal product creation failed for ' . $environment . ' code=' . $prodCode . ' resp=' . substr((string)$prodResp,0,400));
            @file_put_contents('/tmp/debug-paypal-product.log', "code={$prodCode}\nresp=" . substr((string)$prodResp,0,2000) . "\n", FILE_APPEND);
        } else {
            $prodDecoded = json_decode($prodResp, true) ?: [];
            $productId = $prodDecoded['id'] ?? null;
            if ($productId) {
                @file_put_contents('/tmp/debug-paypal-product.log', "productId={$productId}\n", FILE_APPEND);
            }
        }

        // Build plan payload
        $billingCycles = [];
        $recurring = is_numeric($recurringAmount) ? number_format((float)$recurringAmount, 2, '.', '') : number_format((float)$recurringAmount, 2, '.', '');

        $billingCycles[] = [
            'frequency' => [
                'interval_unit' => strtoupper($billingInterval === 'YEAR' ? 'YEAR' : 'MONTH'),
                'interval_count' => 1,
            ],
            'tenure_type' => 'REGULAR',
            'sequence' => 1,
            'total_cycles' => 0,
            'pricing_scheme' => [
                'fixed_price' => [
                    'value' => $recurring,
                    'currency_code' => $currency,
                ],
            ],
        ];

        $planPayload = [
            'product_id' => $productId, // use created product id when available
            'name' => $name,
            'description' => $description ?: 'Subscription plan for ' . $name,
            'status' => 'ACTIVE',
            'billing_cycles' => $billingCycles,
            'payment_preferences' => [
                'auto_bill_outstanding' => true,
                'setup_fee_failure_action' => 'CANCEL',
                'payment_failure_threshold' => 3,
            ],
        ];

        if (!empty($setupFee) && (float)$setupFee > 0) {
            $planPayload['payment_preferences']['setup_fee'] = [
                'value' => number_format((float)$setupFee, 2, '.', ''),
                'currency_code' => $currency,
            ];
        }

        // Send plan creation request
        $ch = curl_init($baseUrl . '/v1/billing/plans');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($planPayload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'Prefer: return=representation',
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            error_log('PayPal plan create cURL error for ' . $environment . ': ' . $curlErr);
            @file_put_contents('/tmp/debug-paypal-plan.log', "err={$curlErr}\nresp=" . substr((string)$resp,0,2000) . "\n", FILE_APPEND);
            return null;
        }

        $decoded = json_decode($resp, true) ?: [];
        if ($httpCode >= 400) {
            error_log('Failed to create PayPal plan (' . $environment . '): ' . $httpCode . ' - ' . substr((string)$resp, 0, 1000));
            @file_put_contents('/tmp/debug-paypal-plan.log', "code={$httpCode}\nresp=" . substr((string)$resp,0,4000) . "\n", FILE_APPEND);
            return null;
        }

        $planId = $decoded['id'] ?? null;
        if ($planId) {
            @file_put_contents('/tmp/debug-paypal-plan.log', "planId={$planId}\n", FILE_APPEND);
            return $planId;
        }

        return null;
    }
}
