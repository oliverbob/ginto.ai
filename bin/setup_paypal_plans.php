#!/usr/bin/env php
<?php
/**
 * Robust PayPal Subscription Plans Setup Script
 *
 * Improvements over the previous version:
 * - Accepts CLI flag --env=sandbox|live to override .env
 * - Prints helpful debug on API failures (cURL/http responses)
 * - Fixes setup_fee calculation for promotional starter plan
 * - Optionally saves plan IDs to DB (or prints SQL if DB unavailable)
 *
 * Usage:
 *   php bin/setup_paypal_plans.php [--env=sandbox|live] [--no-save-db]
 */

declare(strict_types=1);

// Autoload (fail fast with helpful message)
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "Missing vendor/autoload.php. Run composer install.\n");
    exit(1);
}
require_once $autoload;

// Load environment variables (if any)
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// CLI args
$options = getopt('', ['env::', 'no-save-db']);
$cliEnv = $options['env'] ?? null;
$saveDb = !isset($options['no-save-db']);

// Determine environment (cli overrides .env)
$environment = $cliEnv ?: (getenv('PAYPAL_ENVIRONMENT') ?: ($_ENV['PAYPAL_ENVIRONMENT'] ?? 'sandbox'));
$environment = strtolower($environment) === 'live' ? 'live' : 'sandbox';

// Credential selection
if ($environment === 'sandbox') {
    $clientId = getenv('PAYPAL_CLIENT_ID_SANDBOX') ?: ($_ENV['PAYPAL_CLIENT_ID_SANDBOX'] ?? null);
    $clientSecret = getenv('PAYPAL_CLIENT_SECRET_SANDBOX') ?: ($_ENV['PAYPAL_CLIENT_SECRET_SANDBOX'] ?? null);
} else {
    $clientId = getenv('PAYPAL_CLIENT_ID') ?: ($_ENV['PAYPAL_CLIENT_ID'] ?? null);
    $clientSecret = getenv('PAYPAL_CLIENT_SECRET') ?: ($_ENV['PAYPAL_CLIENT_SECRET'] ?? null);
}

$baseUrl = ($environment === 'live') ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';

echo "Environment: {$environment}\n";
echo "Base URL: {$baseUrl}\n";
echo ($saveDb ? "Will attempt to save to DB.\n\n" : "Will NOT save to DB (dry run).\n\n");

// ===================== HELPERS =====================

function getAccessToken(string $baseUrl, string $clientId, string $clientSecret): ?string
{
    echo "Getting PayPal access token...\n";
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
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        fwrite(STDERR, "cURL error while obtaining token: {$err}\n");
        return null;
    }

    if ($httpCode !== 200) {
        fwrite(STDERR, "Failed to get access token (HTTP {$httpCode}). Response: {$response}\n");
        return null;
    }

    $data = json_decode($response, true) ?: [];
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
        CURLOPT_TIMEOUT => 60,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        if ($data !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($data);
        }
    }
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['error' => 'cURL error: ' . $err, '_http_code' => 0, '_raw' => $response];
    }

    $decoded = json_decode($response, true) ?: [];
    $decoded['_http_code'] = $httpCode;
    $decoded['_raw'] = $response;
    return $decoded;
}

function savePlanIdsToDb(array $createdPlans, string $environment): void
{
    if (empty($createdPlans)) {
        echo "No plans created; nothing to save.\n";
        return;
    }

    // Map plan keys to tier_plans IDs (adjust if your IDs differ)
    $tierIdMap = [
        'starter' => 1,
        'professional' => 2,
        'executive' => 3,
        'gold' => 4,
        'platinum' => 5,
    ];

    $dbHost = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
    $dbName = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'ginto';
    $dbUser = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'ginto';
    $dbPass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';

    $column = ($environment === 'sandbox') ? 'paypal_plan_id_sandbox' : 'paypal_plan_id';

    try {
        $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare("UPDATE tier_plans SET {$column} = ? WHERE id = ?");
        foreach ($createdPlans as $key => $planId) {
            $tierId = $tierIdMap[$key] ?? null;
            if ($tierId) {
                $stmt->execute([$planId, $tierId]);
                echo "Saved {$key} => {$planId} into tier id {$tierId} ({$column}).\n";
            } else {
                echo "No mapping for plan key {$key}; skipping DB save.\n";
            }
        }
        echo "All plan IDs saved to DB ({$column}).\n";
    } catch (PDOException $e) {
        fwrite(STDERR, "DB error: " . $e->getMessage() . "\n");
        echo "Manual SQL to run:\n";
        foreach ($createdPlans as $key => $planId) {
            $tierId = $tierIdMap[$key] ?? 0;
            echo "UPDATE tier_plans SET {$column} = '{$planId}' WHERE id = {$tierId};\n";
        }
    }
}

// ===================== MAIN =====================

// Ensure credentials are present
if (empty($clientId) || empty($clientSecret)) {
    fwrite(STDERR, "PAYPAL client credentials are not set for environment '{$environment}'.\n");
    fwrite(STDERR, "Set PAYPAL_CLIENT_ID_... and PAYPAL_CLIENT_SECRET_... in .env or export them.\n");
    exit(1);
}

$token = getAccessToken($baseUrl, $clientId, $clientSecret);
if (!$token) {
    fwrite(STDERR, "Failed to authenticate with PayPal. Check your credentials and network.\n");
    exit(1);
}

echo "Creating product...\n";
$productPayload = [
    'name' => 'Ginto Subscription',
    'type' => 'SERVICE',
    'description' => 'Ginto AI-powered learning platform subscription',
    'category' => 'SOFTWARE',
];

$productResult = apiRequest('POST', '/v1/catalogs/products', $productPayload, $token, $baseUrl);
if (isset($productResult['error']) || ($productResult['_http_code'] ?? 0) >= 400) {
    fwrite(STDERR, "Failed to create product. HTTP: {$productResult['_http_code']} Response: {$productResult['_raw']}\n");
    exit(1);
}

$productId = $productResult['id'] ?? null;
if (!$productId) {
    fwrite(STDERR, "No product ID returned. Response: {$productResult['_raw']}\n");
    exit(1);
}
echo "Product created: {$productId}\n\n";

$plans = [
    'starter' => [
        'name' => 'Ginto Starter',
        'description' => 'Perfect for beginners - includes promotional fee on first month',
        'first_month' => '5.00',
        'recurring' => '3.00',
        'currency' => 'USD',
        'has_setup_fee' => true,
    ],
    'professional' => [
        'name' => 'Ginto Professional',
        'description' => 'For serious earners with advanced training',
        'first_month' => '20.00',
        'recurring' => '20.00',
        'currency' => 'USD',
        'has_setup_fee' => false,
    ],
    'executive' => [
        'name' => 'Ginto Executive',
        'description' => 'Maximum earning potential with elite training',
        'first_month' => '99.00',
        'recurring' => '99.00',
        'currency' => 'USD',
        'has_setup_fee' => false,
    ],
    'gold' => [
        'name' => 'Ginto Gold',
        'description' => 'Premium tier with priority support',
        'first_month' => '199.00',
        'recurring' => '199.00',
        'currency' => 'USD',
        'has_setup_fee' => false,
    ],
    'platinum' => [
        'name' => 'Ginto Platinum',
        'description' => 'Ultimate tier with maximum benefits',
        'first_month' => '999.00',
        'recurring' => '999.00',
        'currency' => 'USD',
        'has_setup_fee' => false,
    ],
];

$createdPlans = [];
foreach ($plans as $key => $plan) {
    // Ensure we have a valid recurring price. If missing/invalid, fall back to first_month (package price).
    $recurring = null;
    if (isset($plan['recurring']) && $plan['recurring'] !== '' && is_numeric($plan['recurring'])) {
        $recurring = number_format((float)$plan['recurring'], 2, '.', '');
    } else {
        // fallback to package price (first_month) when recurring is absent
        $recurring = number_format((float)($plan['first_month'] ?? 0), 2, '.', '');
    }

    $displayPrice = $plan['has_setup_fee']
        ? "{$plan['first_month']} first, {$recurring}/mo"
        : "{$recurring}/month";
    echo "Creating {$plan['name']} ({$displayPrice})...\n";

    // Build billing cycles: PayPal primary regular cycle with recurring price
    $billingCycles = [
        [
            'frequency' => ['interval_unit' => 'MONTH', 'interval_count' => 1],
            'tenure_type' => 'REGULAR',
            'sequence' => 1,
            'total_cycles' => 0,
            'pricing_scheme' => [
                'fixed_price' => ['value' => $recurring, 'currency_code' => $plan['currency']],
            ],
        ],
    ];

    $planPayload = [
        'product_id' => $productId,
        'name' => $plan['name'],
        'description' => $plan['description'],
        'status' => 'ACTIVE',
        'billing_cycles' => $billingCycles,
        'payment_preferences' => [
            'auto_bill_outstanding' => true,
            'setup_fee_failure_action' => 'CANCEL',
            'payment_failure_threshold' => 3,
        ],
    ];

    // For plans with promotional first month, use setup_fee = first_month - recurring
    if ($plan['has_setup_fee']) {
        // use the validated/formatted recurring value when calculating setup fee
        $setupFee = number_format((float)$plan['first_month'] - (float)$recurring, 2, '.', '');
        if ((float)$setupFee > 0) {
            $planPayload['payment_preferences']['setup_fee'] = ['value' => $setupFee, 'currency_code' => $plan['currency']];
        }
    }

    $planResult = apiRequest('POST', '/v1/billing/plans', $planPayload, $token, $baseUrl);
    if (isset($planResult['error']) || ($planResult['_http_code'] ?? 0) >= 400) {
        fwrite(STDERR, "Failed to create {$plan['name']}. HTTP: {$planResult['_http_code']} Response: {$planResult['_raw']}\n");
        continue;
    }

    $planId = $planResult['id'] ?? null;
    if (!$planId) {
        fwrite(STDERR, "No plan ID returned for {$plan['name']}. Response: {$planResult['_raw']}\n");
        continue;
    }

    $createdPlans[$key] = $planId;
    echo "Plan created: {$planId}\n\n";
}

// Save or print SQL
if ($saveDb) {
    savePlanIdsToDb($createdPlans, $environment);
} else {
    echo "Dry run complete. Generated plan IDs (not saved):\n";
    foreach ($createdPlans as $k => $id) {
        echo "  {$k}: {$id}\n";
    }
    echo "If you want to save to DB, run the script without --no-save-db.\n";
}

echo "\nDone.\n";
