<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;

class DataController extends \Core\Controller
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function index()
    {
        // Write DB config and current DB to debug log
        $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(ROOT_PATH) . '/storage';
        $debugLog = $storagePath . '/debug_db.log';
        try {
            $dbConfig = Database::getConfig();
            file_put_contents($debugLog, "DB config: " . json_encode($dbConfig) . PHP_EOL, FILE_APPEND);
            if (($dbConfig['type'] ?? '') === 'mysql') {
                try {
                    $pdoStmt = $this->db->pdo->query('SELECT DATABASE() AS dbname');
                    $dbRow = $pdoStmt ? $pdoStmt->fetch() : null;
                    file_put_contents($debugLog, "Active DB: " . json_encode($dbRow) . PHP_EOL, FILE_APPEND);
                } catch (\Throwable $_) {
                    file_put_contents($debugLog, "Active DB: unknown (pdo query failed)" . PHP_EOL, FILE_APPEND);
                }
            }
        } catch (\Throwable $_) {
            // ignore logging failures
        }

        $totalSales = 0;
        $totalUsers = 0;

        // Fetch total sales independently
        try {
            $totalSalesStmt = $this->db->query("SELECT SUM(amount) as total FROM sales");
            $totalSales = $totalSalesStmt ? (float)($totalSalesStmt->fetch()['total'] ?? 0) : 0;
            file_put_contents($debugLog, "totalSales fetch: " . json_encode($totalSales) . PHP_EOL, FILE_APPEND);
        } catch (\Throwable $e) {
            file_put_contents($debugLog, "sales error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }

        // Fetch total users independently
        try {
            $totalUsersStmt = $this->db->query("SELECT COUNT(*) as count FROM users");
            $totalUsers = $totalUsersStmt ? (int)($totalUsersStmt->fetch()['count'] ?? 0) : 0;
            file_put_contents($debugLog, "totalUsers fetch: " . json_encode($totalUsers) . PHP_EOL, FILE_APPEND);
        } catch (\Throwable $e) {
            file_put_contents($debugLog, "users error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }

        $data = ['total_sales' => $totalSales, 'total_users' => $totalUsers];
        $this->view('api/data', ['data' => $data]);
    }
}