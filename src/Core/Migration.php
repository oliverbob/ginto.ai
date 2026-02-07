<?php
/**
 * Database Migration System
 * Handles database schema updates and migrations
 */

namespace Ginto\Core;

use Medoo\Medoo;
use Exception;

class Migration
{
    private $database;
    private $migrationsPath;
    private $migrationsTable = 'migrations';

    public function __construct(Medoo $database)
    {
        $this->database = $database;
        $this->migrationsPath = dirname(dirname(__DIR__)) . '/database/migrations';
        $this->createMigrationsTable();
    }

    /**
     * Create migrations tracking table if it doesn't exist
     */
    private function createMigrationsTable()
    {
        $dbType = $this->getDatabaseType();
        
        if ($dbType === 'mysql') {
            $query = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INT NOT NULL,
                executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            // SQLite
            $query = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INTEGER NOT NULL,
                executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )";
        }
        
        $this->database->query($query);
    }

    /**
     * Run all pending migrations
     */
    public function migrate()
    {
        $migrations = $this->getPendingMigrations();
        $batch = $this->getNextBatchNumber();

        $executed = [];
        foreach ($migrations as $migration) {
            try {
                $this->runMigration($migration);
                $this->markAsExecuted($migration, $batch);
                $executed[] = $migration;
                echo "Migrated: {$migration}\n";
            } catch (Exception $e) {
                echo "Failed to migrate {$migration}: " . $e->getMessage() . "\n";
                break;
            }
        }

        return $executed;
    }

    /**
     * Get all migration files that haven't been executed
     */
    private function getPendingMigrations()
    {
        $allMigrations = $this->getAllMigrationFiles();
        $executedMigrations = $this->getExecutedMigrations();
        
        return array_diff($allMigrations, $executedMigrations);
    }

    /**
     * Get all migration files from the migrations directory
     */
    private function getAllMigrationFiles()
    {
        $files = [];
        $dbType = $this->getDatabaseType();
        
        if (is_dir($this->migrationsPath)) {
            $handle = opendir($this->migrationsPath);
            while (($file = readdir($handle)) !== false) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                    $filename = pathinfo($file, PATHINFO_FILENAME);
                    
                    // Prefer database-specific migrations if they exist
                    if ($dbType === 'mysql' && str_contains($filename, '_mysql')) {
                        $files[] = $filename;
                    } elseif ($dbType === 'sqlite' && str_contains($filename, '_sqlite')) {
                        $files[] = $filename;
                    } elseif (!str_contains($filename, '_mysql') && !str_contains($filename, '_sqlite')) {
                        // Generic migration file
                        $files[] = $filename;
                    }
                }
            }
            closedir($handle);
        }
        
        sort($files);
        return $files;
    }

    /**
     * Get the database type from Medoo connection
     */
    private function getDatabaseType()
    {
        // Try to determine database type from the Medoo connection
        $pdo = $this->database->pdo;
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        
        return $driver;
    }

    /**
     * Get list of already executed migrations
     */
    private function getExecutedMigrations()
    {
        $result = $this->database->select($this->migrationsTable, 'migration');
        return $result ?: [];
    }

    /**
     * Execute a migration file
     */
    private function runMigration($migration)
    {
        $filePath = $this->migrationsPath . '/' . $migration . '.sql';
        
        if (!file_exists($filePath)) {
            throw new Exception("Migration file not found: {$filePath}");
        }

        $sql = file_get_contents($filePath);
        
        // Split the SQL file into individual statements
        $statements = $this->splitSqlStatements($sql);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $this->database->query($statement);
            }
        }
    }

    /**
     * Split SQL file into individual statements
     */
    private function splitSqlStatements($sql)
    {
        // Remove comments
        $sql = preg_replace('/--.*$/m', '', $sql);
        
        // Split by semicolons (simple approach)
        $statements = explode(';', $sql);
        
        return array_filter($statements, function($statement) {
            return !empty(trim($statement));
        });
    }

    /**
     * Mark migration as executed
     */
    private function markAsExecuted($migration, $batch)
    {
        $this->database->insert($this->migrationsTable, [
            'migration' => $migration,
            'batch' => $batch
        ]);
    }

    /**
     * Get next batch number
     */
    private function getNextBatchNumber()
    {
        $result = $this->database->max($this->migrationsTable, 'batch');
        return ($result ?: 0) + 1;
    }

    /**
     * Rollback migrations (basic implementation)
     */
    public function rollback($steps = 1)
    {
        // Get the last batch(es) to rollback
        $batches = $this->database->select($this->migrationsTable, 'batch', [
            'ORDER' => ['batch' => 'DESC'],
            'LIMIT' => $steps,
            'GROUP' => 'batch'
        ]);

        $rolledBack = [];
        foreach ($batches as $batch) {
            $migrations = $this->database->select($this->migrationsTable, 'migration', [
                'batch' => $batch,
                'ORDER' => ['id' => 'DESC']
            ]);

            foreach ($migrations as $migration) {
                // Try to find and run rollback file
                $rollbackFile = $this->migrationsPath . '/' . $migration . '_rollback.sql';
                if (file_exists($rollbackFile)) {
                    try {
                        $sql = file_get_contents($rollbackFile);
                        $statements = $this->splitSqlStatements($sql);
                        
                        foreach ($statements as $statement) {
                            $statement = trim($statement);
                            if (!empty($statement)) {
                                $this->database->query($statement);
                            }
                        }

                        $this->database->delete($this->migrationsTable, [
                            'migration' => $migration
                        ]);

                        $rolledBack[] = $migration;
                        echo "Rolled back: {$migration}\n";
                    } catch (Exception $e) {
                        echo "Failed to rollback {$migration}: " . $e->getMessage() . "\n";
                    }
                } else {
                    echo "No rollback file found for: {$migration}\n";
                }
            }
        }

        return $rolledBack;
    }

    /**
     * Get migration status
     */
    public function status()
    {
        $allMigrations = $this->getAllMigrationFiles();
        $executedMigrations = $this->getExecutedMigrations();
        
        $status = [];
        foreach ($allMigrations as $migration) {
            $status[] = [
                'migration' => $migration,
                'status' => in_array($migration, $executedMigrations) ? 'Migrated' : 'Pending'
            ];
        }

        return $status;
    }
}