<?php
namespace App\Controllers;

use Core\Controller;

class MigrationsController extends Controller {

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * GET /migrations
     * Runs SQL files in /migrations and records them in the `migrations` table.
     */
    public function install()
    {
        header('Content-Type: application/json');

        // Safety: Allow only in development environment OR admin user.
        $env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production');
        $isDev = strtolower($env) === 'development';

        $isAdmin = false;
        if (isset($_SESSION['user_id']) && $this->db) {
            $role = $this->db->get('users', 'role_id', ['id' => (int)$_SESSION['user_id']]);
            $isAdmin = ($role !== false && (int)$role === 1);
        }

        if (!($isDev || $isAdmin)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Migrations can only be run in development or by an admin.']);
            exit;
        }

        if (!$this->db) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
            exit;
        }

        $migrationsDir = __DIR__ . '/../../migrations';
        if (!is_dir($migrationsDir)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Migrations directory not found: ' . $migrationsDir]);
            exit;
        }

        // Ensure migrations table exists (Laravel-style simple schema)
        $this->db->exec("CREATE TABLE IF NOT EXISTS `migrations` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `migration` VARCHAR(255) NOT NULL,
            `batch` INT NOT NULL,
            `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_migration` (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Determine current batch number
        $maxBatchRow = $this->db->query("SELECT MAX(batch) as m FROM migrations")->fetchAll();
        $currentBatch = 1;
        if ($maxBatchRow && isset($maxBatchRow[0]['m'])) {
            $currentBatch = ((int)$maxBatchRow[0]['m']) + 1;
        }

        // Get already applied migrations
        $appliedRows = $this->db->select('migrations', 'migration') ?: [];
        $applied = array_map('strval', $appliedRows);

        // Collect SQL files
        $files = glob($migrationsDir . '/*_mysql.sql');
        sort($files, SORT_STRING);

        $appliedNow = [];
        $errors = [];

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                $errors[] = "Failed to read $name";
                continue;
            }

            // Remove single-line and block comments so we don't attempt to execute them.
            // Remove /* ... */ block comments
            $sqlClean = preg_replace('#/\*.*?\*/#s', '', $sql);
            // Remove -- comment lines
            $sqlClean = preg_replace('/^\s*--.*$/m', '', $sqlClean);
            // Remove # comment lines
            $sqlClean = preg_replace('/^\s*#.*$/m', '', $sqlClean);

            // Naive split by semicolon — good enough for simple migration files.
            $statements = array_map('trim', explode(';', $sqlClean));
            $statements = array_values(array_filter($statements, function($s) { return $s !== ''; }));

            try {
                // Run each statement
                foreach ($statements as $stmt) {
                    if ($stmt === '') continue;
                    $this->db->exec($stmt);
                }

                // Record migration as applied
                $this->db->insert('migrations', [
                    'migration' => $name,
                    'batch' => $currentBatch
                ]);
                $appliedNow[] = $name;
            } catch (\Exception $e) {
                $errors[] = "Migration $name failed: " . $e->getMessage();
            }
        }

        echo json_encode([
            'success' => empty($errors),
            'applied' => $appliedNow,
            'errors' => $errors
        ]);
        exit;
    }
}

?>
