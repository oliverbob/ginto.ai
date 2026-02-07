<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Core/Database.php';

// Quick test: simulate a session requesting Groq and run selection logic
if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
$_SESSION['llm_provider_name'] = 'groq';
$_SESSION['llm_model'] = 'meta-llama/llama-4-maverick-17b-128e-instruct';

try {
    $db = \Ginto\Core\Database::getInstance();
    $keyManager = new \App\Core\ProviderKeyManager($db);

    $sessionProvider = $_SESSION['llm_provider_name'] ?? ($_SESSION['current_provider'] ?? null);
    $sessionModel = $_SESSION['llm_model'] ?? ($_SESSION['current_model'] ?? null);

    $sessionCloudProvider = $sessionProvider;
    $sessionCloudModel = $sessionModel;

    $apiKey = null;
    $selectedProvider = null;

    if ($sessionCloudProvider) {
        $sessionKeyData = $keyManager->getAvailableKey($sessionCloudProvider);
        if ($sessionKeyData) {
            $apiKey = $sessionKeyData['api_key'];
            $selectedProvider = $sessionCloudProvider;
        } else {
            $envKeyName = strtoupper($sessionCloudProvider) . '_API_KEY';
            $apiKey = getenv($envKeyName) ?: ($_ENV[$envKeyName] ?? '');
            if ($apiKey) {
                $selectedProvider = $sessionCloudProvider;
            } else {
                $sessionCloudProvider = null;
                $sessionCloudModel = null;
            }
        }
    }

    if (!$apiKey) {
        $keyData = $keyManager->getFirstAvailableKey();
        if ($keyData) {
            $apiKey = $keyData['api_key'];
            $selectedProvider = $keyData['provider'];
        }
    }

    echo json_encode([
        'sessionProvider' => $sessionProvider,
        'sessionModel' => $sessionModel,
        'selectedProvider' => $selectedProvider,
        'apiKeyPresent' => !empty($apiKey),
        'apiKeyLen' => $apiKey ? strlen($apiKey) : 0,
    ], JSON_PRETTY_PRINT) . PHP_EOL;

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
