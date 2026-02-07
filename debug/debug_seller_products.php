<?php
// Minimal debug script to invoke SellerController::products() from CLI
require __DIR__ . '/../vendor/autoload.php';

// Start session for controller helpers (CSRF/session checks may require it)
session_start();

// Define minimal CSRF helper if app bootstrap isn't present (CLI debug)
if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken(bool $forVisitor = false): string {
        return bin2hex(random_bytes(32));
    }
}

// Set a test user id (adjust if needed)
$_SESSION['user_id'] = 1;

try {
    $controller = new \Ginto\Controllers\SellerController();
    // Capture output of the controller so we can see if it renders or throws
    ob_start();
    $controller->products();
    $out = ob_get_clean();
    echo $out;
} catch (\Throwable $e) {
    $errFile = __DIR__ . '/seller_products_error.log';
    @file_put_contents($errFile, date('c') . " - " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
    echo "ERROR: " . $e->getMessage() . "\nSee " . $errFile . " for trace.\n";
}
