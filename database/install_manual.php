<?php
/**
 * Manual Installation Script for Ginto CMS
 * Run this after completing the installation wizard
 */

// Set execution time limit for installation
set_time_limit(300);

echo "=== Ginto CMS Installation ===\n\n";

// Step 1: Check if .env file exists and load it
echo "1. Loading configuration...\n";
$envFile = dirname(__DIR__) . '/.env';
if (!file_exists($envFile)) {
    die("Error: .env file not found. Please run the web installer first.\n");
}

// Load environment configuration
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$config = [];
foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    if (strpos($line, '=') !== false) {
        [$key, $value] = explode('=', $line, 2);
        $config[trim($key)] = trim($value);
    }
}

echo "Configuration loaded successfully.\n\n";

// Step 2: Create database connection
echo "2. Connecting to database...\n";
try {
    if ($config['DB_TYPE'] === 'mysql') {
        // First connect without database to create it
        $dsn = "mysql:host={$config['DB_HOST']};port={$config['DB_PORT']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database if it doesn't exist
        $dbName = $config['DB_NAME'];
        echo "Creating database '$dbName'...\n";
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Connect to the specific database
        $dsn = "mysql:host={$config['DB_HOST']};port={$config['DB_PORT']};dbname={$config['DB_NAME']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "Connected to MySQL database successfully.\n\n";
    } else {
        // SQLite
        $dbFile = dirname(__DIR__) . '/' . $config['DB_FILE'];
        $pdo = new PDO("sqlite:$dbFile");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "Connected to SQLite database successfully.\n\n";
    }
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// Step 3: Run database migrations
echo "3. Creating database tables...\n";
$sqlFile = __DIR__ . '/setup-cms.sql';
if (!file_exists($sqlFile)) {
    die("Error: setup-cms.sql not found.\n");
}

$sql = file_get_contents($sqlFile);
$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $statement) {
    if (!empty($statement)) {
        try {
            $pdo->exec($statement);
            echo ".";
        } catch (Exception $e) {
            echo "\nWarning: " . $e->getMessage() . "\n";
        }
    }
}
echo "\nDatabase tables created successfully.\n\n";

// Step 4: Create admin user (if provided via command line)
echo "4. Setting up admin user...\n";
if ($argc > 1 && $argv[1] === 'create-admin') {
    $adminEmail = $argv[2] ?? 'admin@example.com';
    $adminUsername = $argv[3] ?? 'admin';
    $adminPassword = $argv[4] ?? 'admin123';
    $adminFirstName = $argv[5] ?? 'Admin';
    $adminLastName = $argv[6] ?? 'User';
    
    $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO cms_users (first_name, last_name, email, username, password, role_id, status, created_at) VALUES (?, ?, ?, ?, ?, 1, 'active', NOW())");
        $stmt->execute([$adminFirstName, $adminLastName, $adminEmail, $adminUsername, $hashedPassword]);
        echo "Admin user created: $adminUsername ($adminEmail)\n";
    } catch (Exception $e) {
        echo "Warning: Could not create admin user: " . $e->getMessage() . "\n";
    }
} else {
    echo "Skipping admin user creation. Use: php install_manual.php create-admin [email] [username] [password] [firstname] [lastname]\n";
}

echo "\n";

// Step 5: Create .installed flag
echo "5. Finalizing installation...\n";
$storageDir = dirname(__DIR__) . '/storage';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}
file_put_contents($storageDir . '/.installed', 'installed');

echo "Installation completed successfully!\n\n";
echo "Next steps:\n";
echo "1. Access your site at: {$config['APP_URL']}\n";
echo "2. Access admin panel at: {$config['APP_URL']}/admin\n";
echo "3. Check the installation by visiting your site\n\n";

?>