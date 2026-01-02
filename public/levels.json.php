<?php
// Temporary endpoint to dump `tier_plans` rows as JSON for debugging.
require __DIR__ . '/../vendor/autoload.php';
use Ginto\Core\Database;
header('Content-Type: application/json');
try {
    $db = Database::getInstance();
    $rows = $db->select('tier_plans', ['id','name','commission_rate_json','short_name','amount'], ['ORDER' => ['id' => 'ASC']]);
    echo json_encode(['ok' => true, 'levels' => $rows], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
