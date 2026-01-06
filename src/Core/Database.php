<?php
namespace Ginto\Core;

use Medoo\Medoo;
use Exception;

class Database
{
    private static ?Medoo $instance = null;
    private static array $config = [];

    private function __construct() {}

    /**
     * Get the single instance of the Medoo connection.
     * Loads configuration from .env file or passed config array.
     */
    public static function getInstance(array $config = []): Medoo
    {
        if (self::$instance === null) {
            self::$config = empty($config) ? self::loadEnvConfig() : $config;
            self::$instance = self::createConnection();
        }

        return self::$instance;
    }

    /**
     * Load database configuration from .env file
     */
    private static function loadEnvConfig(): array
    {
        $envFile = dirname(dirname(__DIR__)) . '/.env';
        
        if (!file_exists($envFile)) {
            throw new Exception("Configuration file .env not found. Please run the installer first.");
        }

        $config = [];
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                list($key, $value) = explode('=', $line, 2);
                $config[trim($key)] = trim($value);
            }
        }

        return [
            'type' => $config['DB_TYPE'] ?? 'sqlite',
            'host' => $config['DB_HOST'] ?? 'localhost',
            'port' => (int)($config['DB_PORT'] ?? 3306),
            'database' => $config['DB_NAME'] ?? '',
            'username' => $config['DB_USER'] ?? '',
            'password' => $config['DB_PASS'] ?? '',
            'file' => $config['DB_FILE'] ?? 'database.sqlite',
            'charset' => 'utf8mb4'
        ];
    }

    /**
     * Create database connection based on configuration
     */
    private static function createConnection(): Medoo
    {
        $config = self::$config;
        
        try {
            if ($config['type'] === 'sqlite') {
                $dbFile = $config['file'];
                if (!str_starts_with($dbFile, '/')) {
                    $dbFile = dirname(dirname(__DIR__)) . '/' . $dbFile;
                }

                return new Medoo([
                    'database_type' => 'sqlite',
                    'database_file' => $dbFile,
                    'option' => [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    ],
                ]);
            } else {
                // MySQL configuration
                if (empty($config['host']) || empty($config['database']) || empty($config['username'])) {
                    throw new Exception("Missing required MySQL configuration (host, database, username)");
                }

                return new Medoo([
                    'database_type' => 'mysql',
                    'database_name' => $config['database'],
                    'server' => $config['host'],
                    'username' => $config['username'],
                    'password' => $config['password'],
                    'port' => $config['port'],
                    'charset' => $config['charset'],
                    'option' => [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    ],
                ]);
            }
        } catch (Exception $e) {
            throw new Exception("Database Connection Error: " . $e->getMessage());
        }
    }

    /**
     * Get current database configuration
     */
    public static function getConfig(): array
    {
        return self::$config;
    }

    /**
     * Reset connection (useful for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
        self::$config = [];
    }

    /**
     * Check if installation is complete
     */
    public static function isInstalled(): bool
    {
        $envFile = dirname(dirname(__DIR__)) . '/.env';
        $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(dirname(dirname(__DIR__))) . '/storage';
        $installedFile = $storagePath . '/.installed';
        
        // Check if .env exists and has CMS-specific settings, and .installed flag exists
        if (!file_exists($envFile) || !file_exists($installedFile)) {
            return false;
        }
        
        // Also check if the .env file has CMS configuration
        $envContent = file_get_contents($envFile);
        if (strpos($envContent, 'APP_KEY') === false && strpos($envContent, 'CMS_INSTALLED') === false) {
            return false;
        }
        
        // Finally, verify that we can actually connect to the database
        try {
            // Load environment configuration
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $config = [];
                foreach ($lines as $line) {
                    if (strpos(trim($line), '#') === 0) {
                        continue;
                    }
                    if (strpos($line, '=') !== false) {
                        [$key, $value] = explode('=', $line, 2);
                        $config[trim($key)] = trim($value);
                    }
                }
            } else {
                return false;
            }
            
            if ($config['DB_TYPE'] === 'mysql') {
                // Try to connect to the specific database
                $dsn = "mysql:host=" . $config['DB_HOST'] . ";port=" . $config['DB_PORT'] . ";dbname=" . $config['DB_NAME'] . ";charset=utf8mb4";
                $pdo = new \PDO($dsn, $config['DB_USER'], $config['DB_PASS']);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                
                // Check if CMS tables exist
                $result = $pdo->query("SHOW TABLES LIKE 'cms_users'")->fetch();
                return $result !== false;
            } else {
                // For SQLite, check if the file exists and has tables
                $dbFile = dirname(dirname(__DIR__)) . '/' . $config['DB_FILE'];
                if (!file_exists($dbFile)) {
                    return false;
                }
                
                $pdo = new \PDO("sqlite:$dbFile");
                $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='cms_users'")->fetch();
                return $result !== false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }
}
