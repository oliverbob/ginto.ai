<?php

namespace Ginto\Controllers;

use Ginto\Core\Database;
use PDO;
use PDOException;
use Exception;

/**
 * MigrationController - Run database migrations without reinstalling
 * 
 * Access via: /admin/migrate
 * Requires admin authentication
 */
class MigrationController
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Check if current user is admin
     */
    private function isAdmin(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        
        // Use UserController's isAdmin if available
        if (class_exists('\\Ginto\\Controllers\\UserController') && method_exists('\\Ginto\\Controllers\\UserController', 'isAdmin')) {
            return \Ginto\Controllers\UserController::isAdmin();
        }
        
        // Fallback check
        $session = $_SESSION ?? [];
        return !empty($session) && (
            (!empty($session['is_admin'])) ||
            (!empty($session['role_id']) && in_array((int)$session['role_id'], [1,2], true)) ||
            (!empty($session['role']) && strtolower($session['role']) === 'admin') ||
            (!empty($session['user']) && !empty($session['user']['is_admin'])) ||
            (!empty($session['user']) && !empty($session['user']['role']) && strtolower($session['user']['role']) === 'admin')
        );
    }

    /**
     * Validate CSRF token
     */
    private function validateCsrf(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if (empty($sessionToken)) {
            return false;
        }
        
        // Check header first, then body
        $providedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($providedToken)) {
            $input = json_decode(file_get_contents('php://input'), true);
            $providedToken = $input['csrf_token'] ?? '';
        }
        
        return hash_equals($sessionToken, $providedToken);
    }

    /**
     * Show migration status and run pending migrations (JSON)
     */
    public function index()
    {
        if (!$this->isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Admin access required']);
            return;
        }

        if (!$this->validateCsrf()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        header('Content-Type: application/json');

        try {
            $result = $this->runMigrations();
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'executed' => $result['executed'],
                'pending' => $result['pending'],
                'already_executed' => $result['already_executed']
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Show migration status page (HTML)
     */
    public function status()
    {
        if (!$this->isAdmin()) {
            http_response_code(403);
            echo '<h1>403 Forbidden</h1><p>Admin access required</p>';
            return;
        }

        $status = $this->getMigrationStatus();
        
        include ROOT_PATH . '/src/Views/admin/migrate.php';
    }

    /**
     * Get migration status without running
     */
    public function getMigrationStatus(): array
    {
        $config = $this->loadEnvConfig();
        $dbType = $config['DB_TYPE'] ?? 'sqlite';
        $pdo = $this->getConnection($config, $dbType);

        // Get executed migrations
        $executedMigrations = [];
        try {
            $stmt = $pdo->query("SELECT migration, batch, executed_at FROM migrations ORDER BY id");
            if ($stmt) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $executedMigrations[] = $row;
                }
            }
        } catch (PDOException $e) {
            // migrations table might not exist
        }

        // Get all migration files
        $files = $this->getMigrationFiles($dbType);
        $executedNames = array_column($executedMigrations, 'migration');

        $pending = [];
        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if (!in_array($name, $executedNames)) {
                $pending[] = $name;
            }
        }

        return [
            'executed' => $executedMigrations,
            'pending' => $pending,
            'db_type' => $dbType
        ];
    }

    /**
     * Run all pending migrations
     */
    public function runMigrations(): array
    {
        $config = $this->loadEnvConfig();
        $dbType = $config['DB_TYPE'] ?? 'sqlite';
        $pdo = $this->getConnection($config, $dbType);

        // Ensure migrations table exists
        $this->ensureMigrationsTable($pdo, $dbType);

        // Get executed migrations
        $executedMigrations = [];
        $stmt = $pdo->query("SELECT migration FROM migrations");
        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $executedMigrations[] = $row['migration'];
            }
        }

        // Get next batch number
        $batchStmt = $pdo->query("SELECT MAX(batch) as max_batch FROM migrations");
        $batchRow = $batchStmt->fetch(PDO::FETCH_ASSOC);
        $nextBatch = ($batchRow['max_batch'] ?? 0) + 1;

        // Get pending migration files
        $files = $this->getMigrationFiles($dbType);
        $pendingFiles = array_filter($files, function($file) use ($executedMigrations) {
            $migrationName = pathinfo($file, PATHINFO_FILENAME);
            return !in_array($migrationName, $executedMigrations);
        });

        if (count($pendingFiles) === 0) {
            return [
                'message' => 'Database is already up to date (no pending migrations)',
                'executed' => [],
                'pending' => [],
                'already_executed' => $executedMigrations
            ];
        }

        $executedNow = [];
        foreach ($pendingFiles as $file) {
            $migrationName = pathinfo($file, PATHINFO_FILENAME);
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new Exception("Failed to read migration file: {$file}");
            }

            // Normalize SQL for PDO execution
            $normalizedSql = preg_replace('/^\s*DELIMITER\s+\S+\s*$/mi', '', $sql);
            $normalizedSql = preg_replace('/END\s*;?\/\//i', 'END;', $normalizedSql);
            $normalizedSql = preg_replace('/END\s*;?\$\$/i', 'END;', $normalizedSql);
            $normalizedSql = str_replace(["END;//", "END//", "END$$", "END $$"], 'END;', $normalizedSql);

            error_log("Migration: Executing {$migrationName}");
            $pdo->exec($normalizedSql);

            // Record migration as executed
            $insertStmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
            $insertStmt->execute([$migrationName, $nextBatch]);
            $executedNow[] = $migrationName;
        }

        return [
            'message' => 'Successfully executed ' . count($executedNow) . ' migration(s)',
            'executed' => $executedNow,
            'pending' => [],
            'already_executed' => $executedMigrations
        ];
    }

    /**
     * Load environment configuration
     */
    private function loadEnvConfig(): array
    {
        $envFile = ROOT_PATH . '/.env';
        if (!file_exists($envFile)) {
            throw new Exception('.env file not found');
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $config = [];
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $config[trim($key)] = trim($value, '"');
            }
        }

        return $config;
    }

    /**
     * Get PDO connection
     */
    private function getConnection(array $config, string $dbType): PDO
    {
        if ($dbType === 'mysql') {
            $dsn = "mysql:host={$config['DB_HOST']};port={$config['DB_PORT']};dbname={$config['DB_NAME']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS']);
        } else {
            $dbPath = ROOT_PATH . "/{$config['DB_FILE']}";
            $dsn = "sqlite:{$dbPath}";
            $pdo = new PDO($dsn);
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    /**
     * Ensure migrations table exists
     */
    private function ensureMigrationsTable(PDO $pdo, string $dbType): void
    {
        if ($dbType === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INT NOT NULL,
                executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INTEGER NOT NULL,
                executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        }
    }

    /**
     * Get migration files for database type
     */
    private function getMigrationFiles(string $dbType): array
    {
        $migrationsDir = ROOT_PATH . '/database/migrations';
        if (!is_dir($migrationsDir)) {
            throw new Exception("Migrations directory not found: {$migrationsDir}");
        }

        $files = glob($migrationsDir . '/*.sql');
        if (!$files) {
            return [];
        }

        // Filter files by database type
        $files = array_filter($files, function ($file) use ($dbType) {
            if ($dbType === 'mysql') {
                return preg_match('/_mysql\.sql$/', $file);
            }
            return !preg_match('/_mysql\.sql$/', $file);
        });

        sort($files, SORT_STRING);
        return $files;
    }
}
