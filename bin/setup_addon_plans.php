#!/usr/bin/env php
<?php
/**
 * PayPal Addon Plans Setup Script
 * 
 * This script creates PayPal subscription plans for addon services like ImageGen.
 * 
 * Usage:
 *   php bin/setup_addon_plans.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Configuration
$environment = getenv('PAYPAL_ENVIRONMENT') ?: $_ENV['PAYPAL_ENVIRONMENT'] ?? 'sandbox';

if ($environment === 'sandbox') {
    $clientId = getenv('PAYPAL_CLIENT_ID_SANDBOX') ?: $_ENV['PAYPAL_CLIENT_ID_SANDBOX'] ?? null;
    $clientSecret = getenv('PAYPAL_CLIENT_SECRET_SANDBOX') ?: $_ENV['PAYPAL_CLIENT_SECRET_SANDBOX'] ?? null;
} else {
    $clientId = getenv('PAYPAL_CLIENT_ID') ?: $_ENV['PAYPAL_CLIENT_ID'] ?? null;
    $clientSecret = getenv('PAYPAL_CLIENT_SECRET') ?: $_ENV['PAYPAL_CLIENT_SECRET'] ?? null;
}

if (!$clientId || !$clientSecret) {
    echo "\033[31mError: PayPal credentials not set in .env\033[0m\n";
    exit(1);
}

// CLI options
$argv = $_SERVER['argv'] ?? [];
// --set-live-from-sandbox: copy sandbox plan id into live column when live is empty
$copySandboxToLive = in_array('--set-live-from-sandbox', $argv, true) || (getenv('SET_LIVE_FROM_SANDBOX') === '1');
// --set-both: set both paypal_plan_id and paypal_plan_id_sandbox to the created plan id
$setBoth = in_array('--set-both', $argv, true) || (getenv('SET_BOTH_PAYPAL_IDS') === '1');

// Debug: show CLI args (useful when running with flags)
if (!empty($argv)) {
    $cliArgs = implode(' ', array_slice($argv, 1));
    echo "CLI args: {$cliArgs}\n";
}

if ($copySandboxToLive) {
    echo "⚡ Option enabled: will copy sandbox plan IDs into live paypal_plan_id where empty\n";
}
if ($setBoth) {
    echo "⚡ Option enabled: will set both `paypal_plan_id` and `paypal_plan_id_sandbox` to created plan IDs\n";
} 

$baseUrl = ($environment === 'live' || $environment === 'production')
    ? 'https://api-m.paypal.com'
    : 'https://api-m.sandbox.paypal.com';

echo "\n\033[36m╔════════════════════════════════════════════════════════════╗\033[0m\n";
echo "\033[36m║         PayPal Addon Plans Setup Script                    ║\033[0m\n";
echo "\033[36m╚════════════════════════════════════════════════════════════╝\033[0m\n\n";
echo "Environment: \033[33m{$environment}\033[0m\n";
echo "Base URL: {$baseUrl}\n\n";

// Database connection
use Ginto\Core\Database;
$db = Database::getInstance();

// ===================== HELPER FUNCTIONS =====================

function getAccessToken(string $baseUrl, string $clientId, string $clientSecret): ?string {
    $ch = curl_init($baseUrl . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_USERPWD => $clientId . ':' . $clientSecret,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) return null;
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function apiRequest(string $method, string $endpoint, ?array $data, string $token, string $baseUrl): array {
    $ch = curl_init($baseUrl . $endpoint);
    $headers = ['Authorization: Bearer ' . $token, 'Content-Type: application/json'];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => json_decode($response, true) ?: []];
}

// Get access token
echo "🔐 Getting PayPal access token...\n";
$accessToken = getAccessToken($baseUrl, $clientId, $clientSecret);
if (!$accessToken) {
    echo "\033[31mFailed to get access token\033[0m\n";
    exit(1);
}
echo "  \033[32m✓ Access token obtained\033[0m\n\n";

// Create or get product for addons
echo "📦 Creating/Finding Ginto Addons product...\n";
$productData = [
    'name' => 'Ginto Addons',
    'description' => 'Premium addon services for Ginto platform',
    'type' => 'SERVICE',
    'category' => 'SOFTWARE',
];
$productResult = apiRequest('POST', '/v1/catalogs/products', $productData, $accessToken, $baseUrl);
if ($productResult['code'] === 201) {
    $productId = $productResult['body']['id'];
    echo "  \033[32m✓ Product created: {$productId}\033[0m\n\n";
} else {
    // Try to find existing product
    $listResult = apiRequest('GET', '/v1/catalogs/products?page_size=20', null, $accessToken, $baseUrl);
    $productId = null;
    foreach ($listResult['body']['products'] ?? [] as $p) {
        if ($p['name'] === 'Ginto Addons') {
            $productId = $p['id'];
            break;
        }
    }
    if (!$productId) {
        echo "\033[31mFailed to create/find product\033[0m\n";
        exit(1);
    }
    echo "  \033[33m⚡ Using existing product: {$productId}\033[0m\n\n";
}

// Define addon plans
$addonPlans = [
    [
        'addon_type' => 'imagegen',
        'name' => 'ImageGen Pro',
        'description' => 'Professional AI Image Generation with GPU acceleration',
        'amount_usd' => '500.00',
    ],
];

$column = ($environment === 'sandbox') ? 'paypal_plan_id_sandbox' : 'paypal_plan_id';

foreach ($addonPlans as $addon) {
    echo "📋 Creating plan: {$addon['name']}...\n";
    
    $planData = [
        'product_id' => $productId,
        'name' => $addon['name'],
        'description' => $addon['description'],
        'status' => 'ACTIVE',
        'billing_cycles' => [
            [
                'frequency' => ['interval_unit' => 'MONTH', 'interval_count' => 1],
                'tenure_type' => 'REGULAR',
                'sequence' => 1,
                'total_cycles' => 0, // Infinite
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value' => $addon['amount_usd'],
                        'currency_code' => 'USD',
                    ],
                ],
            ],
        ],
        'payment_preferences' => [
            'auto_bill_outstanding' => true,
            'setup_fee_failure_action' => 'CONTINUE',
            'payment_failure_threshold' => 3,
        ],
    ];
    
    $result = apiRequest('POST', '/v1/billing/plans', $planData, $accessToken, $baseUrl);
    
    if ($result['code'] === 201) {
        $planId = $result['body']['id'];
        echo "  \033[32m✓ Plan created: {$planId}\033[0m\n";
        
        // Update database
        $db->update('addon_plans', [$column => $planId], ['addon_type' => $addon['addon_type']]);
        echo "  \033[32m✓ Database updated with plan ID\033[0m\n";

        // If requested, set both columns to the same plan id (useful for quick setup/testing)
        if (!empty($setBoth)) {
            $db->update('addon_plans', ['paypal_plan_id' => $planId, 'paypal_plan_id_sandbox' => $planId], ['addon_type' => $addon['addon_type']]);
            echo "  \033[32m✓ Both paypal_plan_id and paypal_plan_id_sandbox set to {$planId}\033[0m\n\n";
        } elseif (!empty($copySandboxToLive) && $environment === 'sandbox') {
            // When running in sandbox mode with the flag, set live paypal_plan_id if empty
            $db->query("UPDATE addon_plans SET paypal_plan_id = ? , updated_at = NOW() WHERE addon_type = ? AND (paypal_plan_id IS NULL OR paypal_plan_id = '')", [$planId, $addon['addon_type']]);
            echo "  \033[32m✓ Live paypal_plan_id set from sandbox for addon '{$addon['addon_type']}'\033[0m\n\n";
        } else {
            echo "\n";
        }
    } else { 
        echo "  \033[31m✗ Failed to create plan\033[0m\n";
        print_r($result['body']);
        echo "\n";
    }
}

echo "\033[32m✅ Addon plans setup complete!\033[0m\n\n";

if (!empty($copySandboxToLive) || !empty($setBoth)) {
    echo "📋 Ensuring live paypal_plan_id is set from sandbox where empty...\n";
    $db->query("UPDATE addon_plans SET paypal_plan_id = paypal_plan_id_sandbox, updated_at = NOW() WHERE paypal_plan_id IS NULL OR paypal_plan_id = ''");
    echo "  \033[32m✓ Copied sandbox IDs to live where needed\033[0m\n\n";
}

// Verify database
$plans = $db->select('addon_plans', ['addon_type', 'name', 'amount_usd', 'paypal_plan_id', 'paypal_plan_id_sandbox']);
echo "Current addon_plans table:\n";
foreach ($plans as $plan) {
    echo "  - {$plan['addon_type']}: \${$plan['amount_usd']}/month\n";
    echo "    Live: " . ($plan['paypal_plan_id'] ?: 'Not set') . "\n";
    echo "    Sandbox: " . ($plan['paypal_plan_id_sandbox'] ?: 'Not set') . "\n";
}
echo "\n";
