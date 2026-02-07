<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once realpath(__DIR__ . '/../../config/bootstrap.php'); // Load env + medoo
phpinfo();
exit;
use Medoo\Medoo;

class ConnectionTest
{
    protected $db;

    public function __construct(Medoo $database)
    {
        $this->db = $database;
    }

    public function checkConnection()
    {
        try {
            // Attempt a simple query
            $this->db->query("SELECT 1");
            return "✅ Database connection successful.";
        } catch (PDOException $e) {
            return "❌ Connection failed: " . $e->getMessage();
        }
    }
}

// Instantiate and test
$test = new ConnectionTest($db);  // $database comes from bootstrap.php
echo $test->checkConnection();
