<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Core/Database.php';

// Dump provider_keys table as JSON for debugging
try {
    $db = \Ginto\Core\Database::getInstance();
    $rows = $db->select('provider_keys', ['id','provider','is_active','rate_limit_reset_at','key_name'], ['ORDER'=>['id'=>'ASC']]);
    echo json_encode($rows, JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
