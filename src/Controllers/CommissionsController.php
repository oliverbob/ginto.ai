<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;
use Ginto\Core\View;

class CommissionsController extends \Core\Controller
{
    private $db;
    private $view;
    private $commissionRates = null;

    private function loadCommissionRatesFromDb() {
        if ($this->commissionRates !== null) return $this->commissionRates;
        $rows = $this->db->select('commission_rates', ['rate'], [], 'ORDER BY level ASC');
        $this->commissionRates = array_map(function($r) { return floatval($r['rate']); }, $rows);
        return $this->commissionRates;
    }
    private $commissionCache = [];
    private $treeCache = [];
    // default conversion fee percent (applied as a percent to converted amount)
    // NOTE: exposed in API as `conversionRate` (percent). Default set to 4.0 per request.
    private $conversionRate = 4.0;
    // default currency and symbol used when DB does not provide one
    private $defaultCurrency = 'PHP';
    private $defaultCurrencySymbol = 'P';

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->view = new View();
    }

    // Render page
    public function index()
    {
        if (empty($_SESSION['user_id'])) {
            View::view('user/login', ['title' => 'Login']);
            return;
        }

        $userId = (int)$_SESSION['user_id'];
        $depth = 9;

        // Try to use session cached payload if present
        $cacheKey = "commissions_{$userId}_{$depth}";
        if (session_status() !== PHP_SESSION_ACTIVE) {@session_start();}
        $preloaded = null;
        if (!empty($_SESSION[$cacheKey]) && is_array($_SESSION[$cacheKey]) && isset($_SESSION[$cacheKey]['ts']) && (time() - (int)$_SESSION[$cacheKey]['ts'] <= 300)) {
            $preloaded = $_SESSION[$cacheKey]['data'];
        }

        if ($preloaded === null) {
            $preloaded = $this->computeCommissions($userId, $depth, 'month');
            $_SESSION[$cacheKey] = ['ts' => time(), 'data' => $preloaded];
        }

        // wrap preloaded payload with success=true so client can consume it
        if (is_array($preloaded) && !isset($preloaded['success'])) {
            $preloaded = array_merge(['success' => true], $preloaded);
        }

        $this->view->render('user/commissions', ['data' => $preloaded]);
    }

    // JSON API endpoint
    public function apiIndex()
    {
        header('Content-Type: application/json');
        if (empty($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            return;
        }

        $userId = intval($_GET['user_id'] ?? $_SESSION['user_id']);
        $depth = max(1, min(9, intval($_GET['depth'] ?? 9)));
        $range = $_GET['range'] ?? 'month';

        $result = $this->computeCommissions($userId, $depth, $range);
        echo json_encode(array_merge(['success' => true], $result));
    }

    // Details API for drilldown
    public function details()
    {
        header('Content-Type: application/json');
        if (empty($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            return;
        }

        $userId = intval($_GET['user_id'] ?? $_SESSION['user_id']);
        $level = max(1, min(9, intval($_GET['level'] ?? 1)));
        $page = max(1, intval($_GET['page'] ?? 1));
        $per_page = max(10, min(200, intval($_GET['per_page'] ?? 50)));

        // gather users at that level
        $levels = $this->gatherTreeUserIdsPerLevel($userId, $level);
        $ids = $levels[$level] ?? [];
        if (empty($ids)) {
            echo json_encode(['success' => true, 'data' => [], 'total' => 0]);
            return;
        }

        // Paginate
        $offset = ($page - 1) * $per_page;
        // Select orders joined with users for those ids
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT o.id, o.user_id, u.username, o.amount, o.status, o.created_at FROM orders o JOIN users u ON u.id = o.user_id WHERE o.status = 'completed' AND o.user_id IN ($placeholders) ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
            $stmt = $this->db->pdo->prepare($sql);
            $params = array_merge($ids, [$per_page, $offset]);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $countSql = "SELECT COUNT(*) AS cnt FROM orders WHERE status = 'completed' AND user_id IN ($placeholders)";
            $cstmt = $this->db->pdo->prepare($countSql);
            $cstmt->execute($ids);
            $total = (int)($cstmt->fetchColumn() ?: 0);

            echo json_encode(['success' => true, 'data' => $rows, 'total' => $total]);
            return;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            return;
        }
    }

    // Core computation: BFS gather + batch grouped sums + aggregate
    private function computeCommissions(int $userId, int $maxDepth = 9, string $range = 'month')
    {
        // gather per-level user ids
        $levels = $this->gatherTreeUserIdsPerLevel($userId, $maxDepth);
        $allIds = [];
        foreach ($levels as $lvl => $ids) {
            foreach ($ids as $id) $allIds[] = $id;
        }

        // include root for totals if not already
        if (!in_array($userId, $allIds, true)) {
            $allIds[] = $userId;
        }

        // batch load amounts
        $amounts = $this->batchLoadAmounts($allIds, $range);

        $sums = array_fill(0, $maxDepth, 0.0);
        $counts = array_fill(0, $maxDepth, 0);

        foreach ($levels as $lvl => $ids) {
            $idx = max(0, $lvl - 1);
            foreach ($ids as $uid) {
                $val = $amounts[$uid]['total'] ?? 0.0;
                if ($val > 0) {
                    $sums[$idx] += $val;
                    $counts[$idx] += 1;
                }
            }
        }

        $rates = $this->loadCommissionRatesFromDb();
        $perLevelEarnings = [];
        for ($i = 0; $i < $maxDepth; $i++) {
            $rate = $rates[$i] ?? 0.0;
            $perLevelEarnings[$i] = floatval(($sums[$i] ?? 0) * $rate);
        }

        $rootTotals = $amounts[$userId]['total'] ?? 0.0;
        $rootMonthly = $amounts[$userId]['monthly'] ?? 0.0;

        return [
            'commissionRates' => array_map(function($r) {
                $pct = $r * 100;
                if ($pct == 0.0) {
                    return '0%';
                } elseif (fmod($pct, 1) === 0.0) {
                    return rtrim(rtrim(number_format($pct, 0, '.', ''), '0'), '.') . '%';
                } else {
                    return rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.') . '%';
                }
            }, $rates),
            'user' => $this->db->get('users', '*', ['id' => $userId]),
            'depth' => $maxDepth,
            'perLevelSums' => $sums,
            'perLevelCounts' => $counts,
            'perLevelEarnings' => $perLevelEarnings,
            'totalCommissions' => $rootTotals,
            'monthlyCommissions' => $rootMonthly,
            'generated_at' => gmdate('c'),
            'currency' => $this->defaultCurrency,
            'currencySymbol' => $this->defaultCurrencySymbol,
            'conversionRate' => $this->conversionRate
        ];
    }

    // BFS gather levels -> [level => [userIds]]
    private function gatherTreeUserIdsPerLevel(int $rootUserId, int $maxDepth = 3): array
    {
        $result = [];
        $current = [$rootUserId];
        for ($level = 1; $level <= $maxDepth; $level++) {
            if (empty($current)) break;
            $refs = $this->db->select('users', ['id'], ['referrer_id' => $current]);
            $next = [];
            foreach ($refs as $r) {
                $uid = (int)$r['id'];
                $next[] = $uid;
            }
            $result[$level] = $next;
            $current = $next;
        }
        return $result;
    }

    // Batch load totals (orders primary, commissions fallback) and monthly totals
    private function batchLoadAmounts(array $userIds, string $range = 'month'): array
    {
        $userIds = array_map('intval', array_unique($userIds));
        $map = [];
        if (empty($userIds)) return $map;

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));

        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd = date('Y-m-t 23:59:59');

        try {
            // totals
            $sql = "SELECT user_id, SUM(amount) AS total FROM orders WHERE status = 'completed' AND user_id IN ($placeholders) GROUP BY user_id";
            $stmt = $this->db->pdo->prepare($sql);
            $stmt->execute($userIds);
            $ordersTotals = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // monthly totals
            $sql = "SELECT user_id, SUM(amount) AS total FROM orders WHERE status = 'completed' AND created_at >= ? AND created_at <= ? AND user_id IN ($placeholders) GROUP BY user_id";
            $stmt = $this->db->pdo->prepare($sql);
            $params = array_merge([$monthStart, $monthEnd], $userIds);
            $stmt->execute($params);
            $ordersMonthly = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $_) {
            $ordersTotals = [];
            $ordersMonthly = [];
        }

        $ordersTotalsMap = [];
        foreach ($ordersTotals as $r) $ordersTotalsMap[(int)$r['user_id']] = floatval($r['total']);
        $ordersMonthlyMap = [];
        foreach ($ordersMonthly as $r) $ordersMonthlyMap[(int)$r['user_id']] = floatval($r['total']);

        // fallback to commissions for missing totals
        try {
            $sql = "SELECT user_id, SUM(amount) AS total FROM commissions WHERE status = 'paid' AND user_id IN ($placeholders) GROUP BY user_id";
            $stmt = $this->db->pdo->prepare($sql);
            $stmt->execute($userIds);
            $commTotals = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $sql = "SELECT user_id, SUM(amount) AS total FROM commissions WHERE status = 'paid' AND created_at >= ? AND created_at <= ? AND user_id IN ($placeholders) GROUP BY user_id";
            $stmt = $this->db->pdo->prepare($sql);
            $params = array_merge([$monthStart, $monthEnd], $userIds);
            $stmt->execute($params);
            $commMonthly = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $_) {
            $commTotals = [];
            $commMonthly = [];
        }

        $commTotalsMap = [];
        foreach ($commTotals as $r) $commTotalsMap[(int)$r['user_id']] = floatval($r['total']);
        $commMonthlyMap = [];
        foreach ($commMonthly as $r) $commMonthlyMap[(int)$r['user_id']] = floatval($r['total']);

        foreach ($userIds as $uid) {
            $total = $ordersTotalsMap[$uid] ?? 0.0;
            $monthly = $ordersMonthlyMap[$uid] ?? 0.0;
            if ($total === 0.0 && ($commTotalsMap[$uid] ?? 0.0) > 0.0) {
                $total = $commTotalsMap[$uid];
            }
            if ($monthly === 0.0 && ($commMonthlyMap[$uid] ?? 0.0) > 0.0) {
                $monthly = $commMonthlyMap[$uid];
            }
            $map[$uid] = ['total' => $total, 'monthly' => $monthly];
        }

        return $map;
    }
}
