<?php
// Static debug PHP file placed in /public to avoid router conflicts.
// Usage: http://localhost:8000/user-debug-static.php?confirm=yes-run

// Restrict to localhost
$remote = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$allowed = ['127.0.0.1', '::1'];
if (!in_array($remote, $allowed)) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$confirm = $_GET['confirm'] ?? null;
if ($confirm !== 'yes-run') {
    echo "This will return commissions debug JSON. Add '?confirm=yes-run' to run.\n";
    exit;
}

require __DIR__ . '/../vendor/autoload.php';
use Ginto\Core\Database;
use Ginto\Models\User as UserModel;
header('Content-Type: application/json');

try {
    file_put_contents('/tmp/user-debug.log', "enter_try\n", FILE_APPEND);
    $db = Database::getInstance();
    file_put_contents('/tmp/user-debug.log', "after_db\n", FILE_APPEND);
    $userRow = $db->get('users', '*', ['username' => 'oliverbob']);
    file_put_contents('/tmp/user-debug.log', "after_get_user\n", FILE_APPEND);
    if (!$userRow) {
        echo json_encode(['ok' => false, 'message' => "User 'oliverbob' not found"], JSON_PRETTY_PRINT);
        exit;
    }

    $level = null;
    if (!empty($userRow['current_level_id'])) {
        $level = $db->get('tier_plans', '*', ['id' => $userRow['current_level_id']]);
        file_put_contents('/tmp/user-debug.log', "after_get_level\n", FILE_APPEND);
    }

    $um = new UserModel();
    file_put_contents('/tmp/user-debug.log', "after_user_model_new\n", FILE_APPEND);
    $tree = $um->getNetworkTree((int)$userRow['id'], 9);
    file_put_contents('/tmp/user-debug.log', "after_get_tree\n", FILE_APPEND);

    $sums = array_fill(0, 9, 0.0);
    $walk = function($node) use (&$walk, &$sums) {
        $lvlIndex = (isset($node['level']) ? intval($node['level']) : 0) + 1;
        if ($lvlIndex >= 1 && $lvlIndex <= 9) {
            $sums[$lvlIndex - 1] += floatval($node['totalCommissions'] ?? 0);
        }
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $c) $walk($c);
        }
    };
    $walk($tree);
    file_put_contents('/tmp/user-debug.log', "after_walk\n", FILE_APPEND);

    $commissionRates = [0.05,0.04,0.03,0.02,0.01,0.005,0.0025,0.0025,0.00];
    file_put_contents('/tmp/user-debug.log', "before_commission_rates\n", FILE_APPEND);
    if (!empty($level) && !empty($level['commission_rate_json'])) {
        $decoded = json_decode($level['commission_rate_json'], true);
        if (is_array($decoded)) {
            // Normalize associative maps like {"L1": ...} to numeric arrays
            $isAssoc = array_keys($decoded) !== range(0, count($decoded) - 1);
            if ($isAssoc) {
                $norm = [];
                foreach ($decoded as $k => $v) {
                    if (preg_match('/(\d+)/', $k, $m)) {
                        $idx = max(0, intval($m[1]) - 1);
                        $norm[$idx] = floatval($v);
                    }
                }
                ksort($norm);
                $decoded = array_values($norm);
            } else {
                $decoded = array_map(function($v){ return floatval($v); }, $decoded);
            }
            if (count($decoded) < 9) while (count($decoded) < 9) $decoded[] = 0.0;
            if (count($decoded) > 9) $decoded = array_slice($decoded, 0, 9);
            $commissionRates = $decoded;
        }
    }

    $demoBase = 10000;
    $demoBreakdown = [];
    $demoTotal = 0.0;
    foreach (array_values($commissionRates) as $i => $r) {
        file_put_contents('/tmp/user-debug.log', "rate_index={$i} type_rate=" . gettype($r) . "\n", FILE_APPEND);
        $amt = $demoBase * floatval($r);
        $demoBreakdown[] = ['level' => $i+1, 'rate' => $r, 'amount' => $amt];
        $demoTotal += $amt;
    }
    file_put_contents('/tmp/user-debug.log', "after_commission_loop\n", FILE_APPEND);

    echo json_encode([
        'ok' => true,
        'user' => $userRow,
        'level' => $level,
        'perLevelSums' => $sums,
        'commissionRates' => $commissionRates,
        'demo' => ['base' => $demoBase, 'breakdown' => $demoBreakdown, 'total' => $demoTotal],
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], JSON_PRETTY_PRINT);
}
