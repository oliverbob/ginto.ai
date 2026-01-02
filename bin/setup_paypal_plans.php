#!/usr/bin/env php
<?php
/**
 * PayPal Subscription Plans Setup Script
 * 
 * This script creates the PayPal product and subscription plans for Ginto.
 * Run this once to set up your PayPal billing infrastructure.
 * 
 * Usage:
 *   php bin/setup_paypal_plans.php
 *   
 * Or via CLI:
 *   bin/ginto paypal:setup
 * 
 * This script will:
 * 1. Create a "Ginto Subscription" product in PayPal
 * 2. Create Go, Plus, and Pro subscription plans
 * 3. Output the plan IDs to add to your .env file
 */

declare(strict_types=1);

// Load composer autoload
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Configuration
$environment = getenv('PAYPAL_ENVIRONMENT') ?: $_ENV['PAYPAL_ENVIRONMENT'] ?? 'sandbox';

// Use environment-specific credentials
if ($environment === 'sandbox') {
    $clientId = getenv('PAYPAL_CLIENT_ID_SANDBOX') ?: $_ENV['PAYPAL_CLIENT_ID_SANDBOX'] ?? null;
    $clientSecret = getenv('PAYPAL_CLIENT_SECRET_SANDBOX') ?: $_ENV['PAYPAL_CLIENT_SECRET_SANDBOX'] ?? null;
} else {
    $clientId = getenv('PAYPAL_CLIENT_ID') ?: $_ENV['PAYPAL_CLIENT_ID'] ?? null;
    $clientSecret = getenv('PAYPAL_CLIENT_SECRET') ?: $_ENV['PAYPAL_CLIENT_SECRET'] ?? null;
}

if (!$clientId || !$clientSecret) {
    echo "\033[31mError: PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET must be set in .env\033[0m\n";
    exit(1);
}

$baseUrl = $environment === 'live' || $environment === 'production'
    ? 'https://api-m.paypal.com'
    : 'https://api-m.sandbox.paypal.com';

echo "\n\033[36m╔════════════════════════════════════════════════════════════╗\033[0m\n";
echo "\033[36m║         PayPal Subscription Plans Setup Script            ║\033[0m\n";
echo "\033[36m╚════════════════════════════════════════════════════════════╝\033[0m\n\n";

echo "Environment: \033[33m{$environment}\033[0m\n";
echo "Base URL: {$baseUrl}\n\n";

// ===================== HELPER FUNCTIONS =====================

function getAccessToken(string $baseUrl, string $clientId, string $clientSecret): ?string
{
    echo "🔐 Getting PayPal access token...\n";
    
    $ch = curl_init($baseUrl . '/v1/oauth2/token');
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

    if ($httpCode !== 200) {
        echo "\033[31m  ✗ Failed to get access token (HTTP {$httpCode})\033[0m\n";
        return null;
    }

    $data = json_decode($response, true);
    echo "  \033[32m✓ Access token obtained\033[0m\n\n";
    return $data['access_token'] ?? null;
}

function apiRequest(string $method, string $endpoint, ?array $data, string $token, string $baseUrl): array
{
    $url = $baseUrl . $endpoint;
    $ch = curl_init($url);

    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
        'Prefer: return=representation',
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
    }

    curl_setopt_array($ch, $opts);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => 'cURL error: ' . $error, '_http_code' => 0];
    }

    $decoded = json_decode($response, true) ?? [];
    $decoded['_http_code'] = $httpCode;

    return $decoded;
}

// ===================== MAIN SCRIPT =====================

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
if (!$token) {
    echo "\033[31mFailed to authenticate with PayPal. Check your credentials.\033[0m\n";
    exit(1);
}

// Step 1: Create Product
echo "📦 Creating Ginto Subscription Product...\n";

$productPayload = [
    'name' => 'Ginto Subscription',
    'type' => 'SERVICE',
    'description' => 'Ginto AI-powered learning platform subscription',
    'category' => 'SOFTWARE',
];

$productResult = apiRequest('POST', '/v1/catalogs/products', $productPayload, $token, $baseUrl);

if (isset($productResult['error']) || ($productResult['_http_code'] ?? 0) >= 400) {
    echo "\033[31m  ✗ Failed to create product\033[0m\n";
    echo "  Response: " . json_encode($productResult, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

$productId = $productResult['id'] ?? null;
if (!$productId) {
    echo "\033[31m  ✗ No product ID returned\033[0m\n";
    exit(1);
}

echo "  \033[32m✓ Product created: {$productId}\033[0m\n\n";

// Step 2: Create Subscription Plans
$plans = [
    'go' => [
        'name' => 'Ginto Go',
        'description' => 'Basic plan with essential features',
        'price' => '199.00',
        'currency' => 'PHP',
    ],
    'plus' => [
        'name' => 'Ginto Plus',
        'description' => 'Enhanced plan with more features',
        'price' => '499.00',
        'currency' => 'PHP',
    ],
    'pro' => [
        'name' => 'Ginto Pro',
        'description' => 'Premium plan with all features',
        'price' => '999.00',
        'currency' => 'PHP',
    ],
];

$createdPlans = [];

foreach ($plans as $key => $plan) {
    echo "📋 Creating {$plan['name']} plan (₱{$plan['price']}/month)...\n";
    
    $planPayload = [
        'product_id' => $productId,
        'name' => $plan['name'],
        'description' => $plan['description'],
        'status' => 'ACTIVE',
        'billing_cycles' => [
            [
                'frequency' => [
                    'interval_unit' => 'MONTH',
                    'interval_count' => 1,
                ],
                'tenure_type' => 'REGULAR',
                'sequence' => 1,
                'total_cycles' => 0, // 0 = infinite
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value' => $plan['price'],
                        'currency_code' => $plan['currency'],
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

    $planResult = apiRequest('POST', '/v1/billing/plans', $planPayload, $token, $baseUrl);

    if (isset($planResult['error']) || ($planResult['_http_code'] ?? 0) >= 400) {
        echo "\033[31m  ✗ Failed to create {$plan['name']} plan\033[0m\n";
        echo "  Response: " . json_encode($planResult, JSON_PRETTY_PRINT) . "\n";
        continue;
    }

    $planId = $planResult['id'] ?? null;
    if (!$planId) {
        echo "\033[31m  ✗ No plan ID returned for {$plan['name']}\033[0m\n";
        continue;
    }

    $createdPlans[$key] = $planId;
    echo "  \033[32m✓ Plan created: {$planId}\033[0m\n\n";
}

// Step 3: Output results
echo "\n\033[36m════════════════════════════════════════════════════════════\033[0m\n";
echo "\033[32m✓ Setup Complete!\033[0m\n";
echo "\033[36m════════════════════════════════════════════════════════════\033[0m\n\n";

echo "Product ID: \033[33m{$productId}\033[0m\n\n";

echo "Add the following to your \033[33m.env\033[0m file:\n\n";
echo "┌─────────────────────────────────────────────────────────────┐\n";
echo "│ \033[32mPAYPAL_PRODUCT_ID={$productId}\033[0m\n";

foreach ($createdPlans as $key => $planId) {
    $envKey = 'PAYPAL_PLAN_' . strtoupper($key);
    echo "│ \033[32m{$envKey}={$planId}\033[0m\n";
}

echo "└─────────────────────────────────────────────────────────────┘\n\n";

// Ask to auto-update .env
echo "Would you like to automatically add these to your .env file? [y/N]: ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
$answer = strtolower(trim($line));
fclose($handle);

if ($answer === 'y' || $answer === 'yes') {
    $envFile = __DIR__ . '/../.env';
    
    if (!file_exists($envFile)) {
        echo "\033[31m.env file not found!\033[0m\n";
        exit(1);
    }
    
    $envContent = file_get_contents($envFile);
    
    // Check if keys already exist
    $keysToAdd = [];
    
    if (strpos($envContent, 'PAYPAL_PRODUCT_ID=') === false) {
        $keysToAdd[] = "PAYPAL_PRODUCT_ID={$productId}";
    }
    
    foreach ($createdPlans as $key => $planId) {
        $envKey = 'PAYPAL_PLAN_' . strtoupper($key);
        if (strpos($envContent, "{$envKey}=") === false) {
            $keysToAdd[] = "{$envKey}={$planId}";
        }
    }
    
    if (!empty($keysToAdd)) {
        // Ensure file ends with newline
        if (substr($envContent, -1) !== "\n") {
            $envContent .= "\n";
        }
        
        // Add comment and keys
        $envContent .= "\n# PayPal Subscription Plans (auto-generated)\n";
        $envContent .= implode("\n", $keysToAdd) . "\n";
        
        file_put_contents($envFile, $envContent);
        
        echo "\n\033[32m✓ Added to .env:\033[0m\n";
        foreach ($keysToAdd as $key) {
            echo "  • {$key}\n";
        }
    } else {
        echo "\n\033[33mAll keys already exist in .env\033[0m\n";
    }
}

echo "\n\033[32mDone!\033[0m Your PayPal subscription plans are ready.\n";
echo "Users can now subscribe at /upgrade\n\n";
