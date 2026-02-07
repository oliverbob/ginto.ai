<?php
namespace Core;

// It's good practice to import classes you use directly, even if they are in global namespace
// or autoloaded, for clarity. Assuming DBConnect is in the global namespace or autoloaded.
// use DBConnect; // Uncomment if DBConnect is namespaced, otherwise PHP will find it.

class Controller {
    /**
     * @var \DBConnect|null The database connection wrapper instance.
     */
    protected $dbConnection = null;

    /**
     * @var \Medoo\Medoo|null The Medoo database instance.
     */
    protected $db = null; // To directly store the Medoo instance

    public function __construct() {
        // Ensure CONFV_PATH is defined. This should ideally be done in a central bootstrap/config file.
        if (!defined('CONFV_PATH')) {
            // Define a default or throw an error if critical for all controllers
            // For example, if your app root is one level above 'Core'
            // define('CONFV_PATH', dirname(__DIR__, 2) . '/config'); // Adjust path as needed
            // Or, if it's expected to be set by the entry script:
             error_log("Core\Controller Error: CONFV_PATH is not defined.");
             // Depending on your app, you might throw an exception or allow controllers
             // that don't need DB to function. For now, we'll log and proceed,
             // but db operations will fail.
        }

        // Database Connection
        // Check if CONFV_PATH is defined and files exist to prevent errors
        if (defined('CONFV_PATH') && file_exists(CONFV_PATH . '/medoo/DBConnect.php') && file_exists(CONFV_PATH . '/medoo/mdcreds.php')) {
            require_once CONFV_PATH . '/medoo/DBConnect.php';
            $db_creds = require CONFV_PATH . '/medoo/mdcreds.php';

            // Check if DBConnect class exists after requiring
            if (class_exists('\DBConnect')) {
                $this->dbConnection = new \DBConnect($db_creds);
                if (isset($this->dbConnection->db) && $this->dbConnection->db instanceof \Medoo\Medoo) {
                    $this->db = $this->dbConnection->db; // Store the Medoo instance directly
                } else {
                     error_log("Core\Controller Error: \DBConnect instance does not have a valid Medoo 'db' property.");
                     $this->db = null; // Ensure $this->db is null if connection fails at this stage
                }
            } else {
                error_log("Core\Controller Error: \DBConnect class not found after requiring the file.");
                 $this->dbConnection = null; // Ensure properties are null on failure
                 $this->db = null;
            }
        } else {
            error_log("Core\Controller Error: Database configuration files not found or CONFV_PATH not properly set. Path checked: " . (defined('CONFV_PATH') ? CONFV_PATH : 'CONFV_PATH_NOT_DEFINED'));
            $this->dbConnection = null; // Ensure properties are null on failure
            $this->db = null;
        }
    }

    public function view($view, $data = []) {
        \Core\View::render($view, $data);
    }

    /**
     * Safely count rows in a table using Medoo, returning 0 on error.
     *
     * @param string $table
     * @param mixed $where
     * @return int
     */
    protected function safeCount(string $table, $where = []): int
    {
        if (!$this->db) {
            return 0;
        }
        try {
            $result = $this->db->count($table, $where);
            return is_numeric($result) ? (int)$result : 0;
        } catch (\Throwable $e) {
            $whereJson = '@';
            try {
                $whereJson = json_encode($where);
            } catch (\Throwable $_) {
                $whereJson = 'unserializable';
            }
            error_log("safeCount error for table={$table} where={$whereJson}: " . $e->getMessage());
            return 0;
        }
    }
}