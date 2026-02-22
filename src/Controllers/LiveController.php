<?php
namespace Ginto\Controllers;

use Ginto\Core\View;

/**
 * Live Controller
 * 
 * Admin settings panel for configuring Ginto.ai after installation.
 * This is the "v2 install" - accessible to admins at /live for:
 * - Viewing/editing environment configuration
 * - Managing API keys and providers
 * - Configuring LLM settings
 * 
 * Unlike /install which requires database setup, /live assumes
 * the application is already running and provides configuration UI.
 */
class LiveController
{
    protected $db;
    private ?\PDO $pdo = null;

    public function __construct($db = null)
    {
        if ($db === null) {
            $db = \Ginto\Core\Database::getInstance();
        }
        $this->db = $db;
        
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    /**
     * Main settings page (GET /live)
     * Admin-only access, OR accessible to anyone if no users exist yet (first-time setup)
     */
    public function index(): void
    {
        // Check if .installed marker exists
        $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(dirname(__DIR__), 2) . '/storage';
        $installedExists = file_exists(dirname(__DIR__, 2) . '/.installed') || file_exists($storagePath . '/.installed');
        
        // Check if any users exist in the database
        $userCount = 0;
        try {
            $userCount = $this->db->count('users');
        } catch (\Exception $e) {
            // Database might not be set up yet
            $userCount = 0;
        }
        
        // Access control:
        // - No users exist: FULL access (first person to visit can configure everything)
        // - Users exist + .installed: Admin login required
        // - Users exist + no .installed: Still allow access (setup mode)
        if ($userCount > 0 && $installedExists && !UserController::isAdmin()) {
            http_response_code(403);
            header('Location: /chat');
            exit;
        }

        // Get current env values
        $envValues = $this->getEnvValues();

        // Generate CSRF token
        if (function_exists('generateCsrfToken')) {
            $csrf_token = generateCsrfToken();
        } else {
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            $csrf_token = $_SESSION['csrf_token'];
        }

        View::view('live/settings', [
            'title' => 'Ginto AI - Live Settings',
            'envValues' => $envValues,
            'csrf_token' => $csrf_token,
            'success' => $_GET['success'] ?? null,
            'error' => $_GET['error'] ?? null,
            'userCount' => $userCount  // Pass this so settings.php can show admin creation if needed
        ]);
        exit;
    }

    /**
     * Save settings (POST /live)
     */
    public function save(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // Ensure session is started for CSRF validation
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        // Check if this is first-time admin creation (no users exist)
        $userCount = 0;
        try {
            $userCount = $this->db->count('users');
        } catch (\Exception $e) {
            $userCount = 0;
        }
        
        // Read JSON body (not using CsrfMiddleware for /live routes)
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        // CSRF validation - check header first, then body
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        
        if (empty($token) || empty($sessionToken) || !hash_equals($sessionToken, $token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
        
        // Handle first-time admin creation
        if ($userCount === 0 && isset($input['action']) && $input['action'] === 'create_admin') {
            $this->createFirstAdmin($input);
            return;
        }

        // Check if .installed marker exists - if not, this is first-time setup, allow save
        $installedMarkerExists = file_exists(ROOT_PATH . '/.installed') || file_exists(dirname(ROOT_PATH) . '/storage/.installed');
        
        // Check if user is admin for settings update (skip if first-time setup)
        if ($installedMarkerExists && !UserController::isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            exit;
        }

        try {
            $data = $input['data'] ?? $input;
            $this->updateEnvFile($data);
            echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Activate the site - runs database setup, creates admin user, creates .installed marker (POST /live/activate)
     * After activation, /live will require admin login
     */
    public function activate(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // Ensure session is started for CSRF validation
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        // Read JSON body
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        // CSRF validation - check header first, then body
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        
        if (empty($token) || empty($sessionToken) || !hash_equals($sessionToken, $token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        // Check if already installed - still allow updating settings
        $installedPath = ROOT_PATH . '/.installed';
        if (file_exists($installedPath)) {
            // Site already activated - just return success message
            echo json_encode(['success' => true, 'message' => 'Live site updated successfully!']);
            exit;
        }

        try {
            // Step 1: Run database migrations
            $this->runDatabaseMigrations();
            
            // Step 2: Create admin and default users (reads from .env, same as install.php)
            $this->createAdminAccountStep();
            
            // Step 3: Create .installed marker file with timestamp
            $content = "Installed: " . date('Y-m-d H:i:s') . "\n";
            $content .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
            
            if (file_put_contents($installedPath, $content) === false) {
                throw new \Exception('Failed to create installation marker');
            }

            echo json_encode([
                'success' => true, 
                'message' => 'Site activated successfully',
                'redirect' => '/chat'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * Run database migrations - ported from /install/install.php
     */
    private function runDatabaseMigrations(): void
    {
        // Load .env config
        $envFile = ROOT_PATH . '/.env';
        if (!file_exists($envFile)) {
            throw new \Exception('.env file not found');
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
        
        $dbType = $config['DB_TYPE'] ?? 'mysql';
        
        // Create PDO connection
        if ($dbType === 'mysql') {
            // First connect without database to create it if needed
            $testDsn = "mysql:host={$config['DB_HOST']};port={$config['DB_PORT']};charset=utf8mb4";
            $testPdo = new \PDO($testDsn, $config['DB_USER'], $config['DB_PASS']);
            $testPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            // Create database if it doesn't exist
            $dbName = $config['DB_NAME'];
            $testPdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Now connect to the specific database
            $dsn = "mysql:host={$config['DB_HOST']};port={$config['DB_PORT']};dbname={$config['DB_NAME']};charset=utf8mb4";
            $pdo = new \PDO($dsn, $config['DB_USER'], $config['DB_PASS']);
        } else {
            $dbPath = ROOT_PATH . '/' . ($config['DB_FILE'] ?? 'database.sqlite');
            $pdo = new \PDO("sqlite:{$dbPath}");
        }
        
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        // Create migrations tracking table
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
        
        // Get already executed migrations
        $executedMigrations = [];
        $stmt = $pdo->query("SELECT migration FROM migrations");
        if ($stmt) {
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $executedMigrations[] = $row['migration'];
            }
        }
        
        // Get next batch number
        $batchStmt = $pdo->query("SELECT MAX(batch) as max_batch FROM migrations");
        $batchRow = $batchStmt->fetch(\PDO::FETCH_ASSOC);
        $nextBatch = ($batchRow['max_batch'] ?? 0) + 1;
        
        // Run SQL migration files
        $migrationsDir = ROOT_PATH . '/database/migrations';
        if (!is_dir($migrationsDir)) {
            throw new \Exception("Migrations directory not found");
        }
        
        $files = glob($migrationsDir . '/*.sql');
        if (!$files) {
            throw new \Exception('No migration files found');
        }
        
        // Filter by database type
        $files = array_filter($files, function ($file) use ($dbType) {
            if ($dbType === 'mysql') {
                return preg_match('/_mysql\.sql$/', $file);
            }
            return !preg_match('/_mysql\.sql$/', $file);
        });
        
        sort($files, SORT_STRING);
        
        // Filter out already executed
        $pendingFiles = array_filter($files, function($file) use ($executedMigrations) {
            $migrationName = pathinfo($file, PATHINFO_FILENAME);
            return !in_array($migrationName, $executedMigrations);
        });
        
        // Run pending migrations
        foreach ($pendingFiles as $file) {
            $migrationName = pathinfo($file, PATHINFO_FILENAME);
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \Exception("Failed to read migration file: {$file}");
            }
            
            // Normalize SQL - remove DELIMITER directives
            $normalizedSql = preg_replace('/^\s*DELIMITER\s+\S+\s*$/mi', '', $sql);
            $normalizedSql = preg_replace('/END\s*;?\/\//i', 'END;', $normalizedSql);
            $normalizedSql = preg_replace('/END\s*;?\$\$/i', 'END;', $normalizedSql);
            $normalizedSql = str_replace(["END;//", "END//", "END$$", "END $$"], 'END;', $normalizedSql);
            
            $pdo->exec($normalizedSql);
            
            // Record migration
            $insertStmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
            $insertStmt->execute([$migrationName, $nextBatch]);
        }
        
        // Verify users table was created
        if ($dbType === 'mysql') {
            $checkStmt = $pdo->query("SHOW TABLES LIKE 'users'");
            if ($checkStmt->rowCount() === 0) {
                throw new \Exception('Database migration failed: users table not created');
            }
        }
        
        // Store PDO for admin user creation
        $this->pdo = $pdo;
    }
    
    /**
     * Helper function to check if a table exists
     */
    private function tableExists(\PDO $pdo, string $dbType, string $tableName): bool
    {
        if ($dbType === 'mysql') {
            $stmt = $pdo->query("SHOW TABLES LIKE '" . $tableName . "'");
            return $stmt && $stmt->fetch();
        }
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
        $stmt->execute([$tableName]);
        return (bool)$stmt->fetchColumn();
    }
    
    /**
     * Seed tier_plans if missing
     */
    private function seedLevelsIfMissing(\PDO $pdo, string $dbType): bool
    {
        try {
            if (!$this->tableExists($pdo, $dbType, 'tier_plans')) {
                error_log("tier_plans table does not exist (create migration missing)");
                return false;
            }

            $countStmt = $pdo->query("SELECT COUNT(*) FROM tier_plans");
            $count = $countStmt ? (int)$countStmt->fetchColumn() : 0;
            if ($count > 0) {
                error_log("Tier plans already present in database (count={$count})");
                return true;
            }

            $seedFile = ROOT_PATH . '/database/migrations/002_seed_tier_plans' . ($dbType === 'mysql' ? '_mysql.sql' : '.sql');
            if (file_exists($seedFile)) {
                $sql = file_get_contents($seedFile);
                $normalizedSql = preg_replace('/^\s*DELIMITER\s+\S+\s*$/mi', '', $sql);
                $normalizedSql = preg_replace('/END\s*;?\/\//i', 'END;', $normalizedSql);
                $normalizedSql = preg_replace('/END\s*;?\$\$/i', 'END;', $normalizedSql);
                $normalizedSql = str_replace(["END;//", "END//", "END$$", "END $$"], 'END;', $normalizedSql);
                $pdo->exec($normalizedSql);
                error_log("Seeded tier_plans using file: {$seedFile}");
                return true;
            }

            // Fallback: run prepared inserts directly
            error_log("Seed file not found. Inserting tier_plans fallback directly.");
            $seedValues = [
                [1, 'Starter', 150.00, 'PHP', json_encode(['L1' => 0.01])],
                [2, 'Basic', 1000.00, 'PHP', json_encode(['L1' => 0.02])],
                [3, 'Silver', 5000.00, 'PHP', json_encode(['L1' => 0.05, 'L2' => 0.02])],
                [4, 'Gold', 10000.00, 'PHP', json_encode(['L1' => 0.07, 'L2' => 0.03, 'L3' => 0.02])],
                [5, 'Platinum', 50000.00, 'PHP', json_encode(['L1' => 0.10, 'L2' => 0.05, 'L3' => 0.03, 'L4' => 0.02, 'L5' => 0.01])],
            ];

            $placeholders = [];
            $params = [];
            foreach ($seedValues as $row) {
                $placeholders[] = '(?, ?, ?, ?, ?)';
                $params[] = $row[0];
                $params[] = $row[1];
                $params[] = $row[2];
                $params[] = $row[3];
                $params[] = $row[4];
            }
            if ($dbType === 'mysql') {
                $sql = "INSERT IGNORE INTO tier_plans (id, name, cost_amount, cost_currency, commission_rate_json) VALUES " . implode(', ', $placeholders);
            } else {
                $sql = "INSERT OR IGNORE INTO tier_plans (id, name, cost_amount, cost_currency, commission_rate_json) VALUES " . implode(', ', $placeholders);
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            error_log("Inserted fallback tier_plans rows (count: " . count($seedValues) . ")");
            return true;
        } catch (\Exception $e) {
            error_log("seedLevelsIfMissing failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create admin and default users - ported directly from install.php createAdminAccountStep()
     * Reads credentials from .env file
     */
    private function createAdminAccountStep(): void
    {
        $pdo = $this->pdo ?? null;
        if (!$pdo) {
            throw new \Exception('Database connection not available');
        }
        
        // Load .env config
        $envValues = $this->getEnvValues();
        
        $email = $envValues['ADMIN_EMAIL'] ?? 'admin@example.com';
        $username = $envValues['ADMIN_USERNAME'] ?? 'admin';
        $password = $envValues['ADMIN_PASSWORD'] ?? '';
        
        // Validate required admin password
        if (empty($password) || strlen($password) < 6) {
            throw new \Exception('Admin password is required and must be at least 6 characters long. Please set ADMIN_PASSWORD in /live settings.');
        }
        
        $dbType = $envValues['DB_TYPE'] ?? 'mysql';
        
        // Check if users table exists
        if (!$this->tableExists($pdo, $dbType, 'users')) {
            throw new \Exception("Users table does not exist. Database migration may have failed.");
        }
        error_log("Users table exists");
        
        // Check if roles table exists and has admin role
        if (!$this->tableExists($pdo, $dbType, 'roles')) {
            throw new \Exception("Roles table does not exist. Database migration may have failed.");
        }
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE id = 2");
        $stmt->execute();
        $adminRoleExists = $stmt->fetchColumn();
        if (!$adminRoleExists) {
            throw new \Exception("Admin role (ID: 2) does not exist in roles table. Database migration may have failed to insert default roles.");
        }
        error_log("Users and roles tables exist with proper data");
        
        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        error_log("Admin password hashed successfully");
        
        // Check for existing admin user
        error_log("Checking for existing admin user: $username, $email");
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $existingUser = $stmt->fetch();
        
        $adminCreated = false;
        if ($existingUser) {
            error_log("Admin user already exists: $username or $email - skipping creation (preserving existing data)");
        } else {
            // Insert new admin user
            error_log("Inserting new admin user: $username, $email");
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, fullname, role_id, status, created_at) VALUES (?, ?, ?, ?, 2, 'active', NOW())");
            $first = trim($envValues['ADMIN_FIRST_NAME'] ?? '');
            $middle = trim($envValues['ADMIN_MIDDLE_NAME'] ?? '');
            $last = trim($envValues['ADMIN_LAST_NAME'] ?? '');
            $parts = array_filter([$first, $middle, $last]);
            $fullname = count($parts) ? implode(' ', $parts) : $username;
            $result = $stmt->execute([$username, $email, $hashedPassword, $fullname]);
            error_log("Admin user inserted: " . ($result ? "SUCCESS" : "FAILED"));
            $adminCreated = true;
        }
        
        // Ensure levels exist before attempting to create users (prevent FK constraint errors)
        $this->seedLevelsIfMissing($pdo, $dbType);

        // Create the default user account (first user in the system)
        $defaultUsername = $envValues['DEFAULT_USERNAME'] ?? '001';
        $defaultEmail = $envValues['DEFAULT_EMAIL'] ?? '001@ginto.local';
        $defaultPasswordPlain = $envValues['DEFAULT_PASSWORD'] ?? '001default';
        $defaultPassword = password_hash($defaultPasswordPlain, PASSWORD_DEFAULT);
        
        error_log("Checking for existing default user: $defaultUsername, $defaultEmail");
        
        // Check if default user already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$defaultUsername, $defaultEmail]);
        $existingDefaultUser = $stmt->fetch();
        
        if ($existingDefaultUser) {
            error_log("Default user already exists: $defaultUsername or $defaultEmail - skipping creation (preserving existing data)");
        } else {
            error_log("Inserting new default user: $defaultUsername, $defaultEmail");
            $stmt001 = $pdo->prepare("INSERT INTO users (username, email, password_hash, fullname, role_id, status, current_level_id, ginto_level, created_at) VALUES (?, ?, ?, ?, 5, 'active', 1, 0, NOW())");
            $result001 = $stmt001->execute([$defaultUsername, $defaultEmail, $defaultPassword, 'Default User - ' . $defaultUsername]);
            error_log("Default user inserted: " . ($result001 ? "SUCCESS" : "FAILED"));
        }
    }

    /**
     * Get current environment values
     */
    private function getEnvValues(): array
    {
        $envPath = ROOT_PATH . '/.env';
        $values = [];
        
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) continue;
                if (strpos($line, '=') !== false) {
                    [$key, $val] = explode('=', $line, 2);
                    $values[trim($key)] = trim($val, '"\'');
                }
            }
        }
        
        return $values;
    }

    /**
     * Update .env file with new values
     */
    private function updateEnvFile(array $data): void
    {
        $envPath = ROOT_PATH . '/.env';
        
        // Load existing values
        $existing = $this->getEnvValues();
        
        // Map form fields to env keys
        $keyMap = [
            // Users
            'admin_username' => 'ADMIN_USERNAME',
            'admin_email' => 'ADMIN_EMAIL',
            'admin_password' => 'ADMIN_PASSWORD',
            'default_username' => 'DEFAULT_USERNAME',
            'default_email' => 'DEFAULT_EMAIL',
            'default_password' => 'DEFAULT_PASSWORD',
            
            // Site
            'site_name' => 'APP_NAME',
            'site_description' => 'APP_DESCRIPTION',
            'site_url' => 'APP_URL',
            'timezone' => 'TIMEZONE',
            'openwebui_enabled' => 'OPENWEBUI_ENABLED',
            'sdcpu_active' => 'SDCPU_ACTIVE',
            'sdcpu_tunnel' => 'SDCPU_TUNNEL',
            'groq_vision_for_all_models' => 'GROQ_VISION_FOR_ALL_MODELS',
            'imagegen_profile' => 'IMAGEGEN_PROFILE',
            'imagegen_compute_mode' => 'IMAGEGEN_COMPUTE_MODE',
            'imagegen_steps' => 'IMAGEGEN_STEPS',
            'imagegen_guidance_scale' => 'IMAGEGEN_GUIDANCE_SCALE',
            'imagegen_width' => 'IMAGEGEN_WIDTH',
            'imagegen_height' => 'IMAGEGEN_HEIGHT',
            
            // LLM Provider
            'llm_provider' => 'LLM_PROVIDER',
            'llm_model' => 'LLM_MODEL',
            'default_provider' => 'DEFAULT_PROVIDER',
            
            // API Keys
            'groq_api_key' => 'GROQ_API_KEY',
            'novita_api_key' => 'NOVITA_API_KEY',
            'groq_tts_model' => 'GROQ_TTS_MODEL',
            'groq_stt_model' => 'GROQ_STT_MODEL',
            'cerebras_api_key' => 'CEREBRAS_API_KEY',
            'cerebras_api_url' => 'CEREBRAS_API_URL',
            'openrouter_api_key' => 'OPENROUTER_API_KEY',

                // Datacenter / Backblaze B2 / CDN
                'b2_account_id' => 'B2_ACCOUNT_ID',
                'b2_app_key' => 'B2_APP_KEY',
                'b2_bucket_id' => 'B2_BUCKET_ID',
                'b2_bucket_name' => 'B2_BUCKET_NAME',
                'file_cdn_base_url' => 'FILE_CDN_BASE_URL',
                'datacenter' => 'DATACENTER',
            
            // Ecommerce / PayPal
            'paypal_webhook_id' => 'PAYPAL_WEBHOOK_ID',
            'paypal_client_id' => 'PAYPAL_CLIENT_ID',
            'paypal_client_secret' => 'PAYPAL_CLIENT_SECRET',
            'paypal_environment' => 'PAYPAL_ENVIRONMENT',
            'paypal_internal_api_key' => 'PAYPAL_INTERNAL_API_KEY',
            'paypal_client_id_sandbox' => 'PAYPAL_CLIENT_ID_SANDBOX',
            'paypal_client_secret_sandbox' => 'PAYPAL_CLIENT_SECRET_SANDBOX',

            // Local LLM
            'local_llm_url' => 'LOCAL_LLM_URL',
            'local_llm_model' => 'LOCAL_LLM_MODEL',
            'vision_model_url' => 'VISION_MODEL_URL',
            'vision_model_name' => 'VISION_MODEL_NAME',
            
            // MCP
            'mcp_server_url' => 'MCP_SERVER_URL',
            'use_py_stt' => 'USE_PY_STT',
            'python3_path' => 'PYTHON3_PATH',
            
            // Rate Limiting
            'rate_limit_admin_percent' => 'RATE_LIMIT_ADMIN_PERCENT',
            'rate_limit_user_percent' => 'RATE_LIMIT_USER_PERCENT',
            'rate_limit_visitor_percent' => 'RATE_LIMIT_VISITOR_PERCENT',
            'rate_limit_fallback_provider' => 'RATE_LIMIT_FALLBACK_PROVIDER',
            'rate_limit_fallback_threshold' => 'RATE_LIMIT_FALLBACK_THRESHOLD',
            
            // Token Limits
            'max_tokens_base' => 'MAX_TOKENS_BASE',
            'max_tokens_admin_percent' => 'MAX_TOKENS_ADMIN_PERCENT',
            'max_tokens_user_percent' => 'MAX_TOKENS_USER_PERCENT',
            'max_tokens_visitor_percent' => 'MAX_TOKENS_VISITOR_PERCENT',
            
            // TTS Limits
            'tts_limit_admin_hourly' => 'TTS_LIMIT_ADMIN_HOURLY',
            'tts_limit_user_hourly' => 'TTS_LIMIT_USER_HOURLY',
            'tts_limit_visitor_session' => 'TTS_LIMIT_VISITOR_SESSION',
            'tts_silent_stop_percent' => 'TTS_SILENT_STOP_PERCENT',
            
            // HuggingFace Models
            'reasoning_hf_model' => 'REASONING_HF_MODEL',
            'vision_hf_model' => 'VISION_HF_MODEL',
            
            // Expected Users
            'expected_users' => 'EXPECTED_USERS',
        ];
        
        // Handle checkbox fields (convert to true/false)
        $data['openwebui_enabled'] = isset($data['openwebui_enabled']) && $data['openwebui_enabled'] ? 'true' : 'false';
        $data['sdcpu_active'] = isset($data['sdcpu_active']) && $data['sdcpu_active'] ? 'true' : 'false';
        $data['sdcpu_tunnel'] = isset($data['sdcpu_tunnel']) && $data['sdcpu_tunnel'] ? 'true' : 'false';
        $data['groq_vision_for_all_models'] = isset($data['groq_vision_for_all_models']) && $data['groq_vision_for_all_models'] ? 'true' : 'false';

        // Validate select fields with strict allowlists
        $allowedImagegenProfiles = ['fast', 'balanced', 'quality', 'ultra'];
        $imagegenProfile = (string)($data['imagegen_profile'] ?? 'balanced');
        $data['imagegen_profile'] = in_array($imagegenProfile, $allowedImagegenProfiles, true)
            ? $imagegenProfile
            : 'balanced';

        $allowedComputeModes = ['auto', 'cpu', 'gpu'];
        $imagegenComputeMode = (string)($data['imagegen_compute_mode'] ?? 'auto');
        $data['imagegen_compute_mode'] = in_array($imagegenComputeMode, $allowedComputeModes, true)
            ? $imagegenComputeMode
            : 'auto';

        // Numeric image generation overrides (blank = use profile defaults)
        $stepsRaw = trim((string)($data['imagegen_steps'] ?? ''));
        $data['imagegen_steps'] = $stepsRaw === ''
            ? ''
            : (string)max(1, min(50, (int)$stepsRaw));

        $guidanceRaw = trim((string)($data['imagegen_guidance_scale'] ?? ''));
        if ($guidanceRaw === '') {
            $data['imagegen_guidance_scale'] = '';
        } else {
            $guidance = (float)$guidanceRaw;
            $guidance = max(0.1, min(20.0, $guidance));
            $data['imagegen_guidance_scale'] = rtrim(rtrim(number_format($guidance, 2, '.', ''), '0'), '.');
        }

        $widthRaw = trim((string)($data['imagegen_width'] ?? ''));
        $data['imagegen_width'] = $widthRaw === ''
            ? ''
            : (string)max(256, min(1536, (int)$widthRaw));

        $heightRaw = trim((string)($data['imagegen_height'] ?? ''));
        $data['imagegen_height'] = $heightRaw === ''
            ? ''
            : (string)max(256, min(1536, (int)$heightRaw));
        
        // Update values
        foreach ($keyMap as $formKey => $envKey) {
            if (isset($data[$formKey])) {
                $existing[$envKey] = $data[$formKey];
            }
        }
        
        // Build new .env content
        $content = "# Ginto AI Configuration\n";
        $content .= "# Updated: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Group settings logically
        $groups = [
            'Ecommerce' => ['PAYPAL_WEBHOOK_ID', 'PAYPAL_CLIENT_ID', 'PAYPAL_CLIENT_SECRET', 'PAYPAL_ENVIRONMENT', 'PAYPAL_INTERNAL_API_KEY', 'PAYPAL_CLIENT_ID_SANDBOX', 'PAYPAL_CLIENT_SECRET_SANDBOX'],
            'Datacenter' => ['B2_ACCOUNT_ID', 'B2_APP_KEY', 'B2_BUCKET_ID', 'B2_BUCKET_NAME', 'FILE_CDN_BASE_URL', 'DATACENTER'],
            'Site Configuration' => ['APP_NAME', 'APP_DESCRIPTION', 'APP_URL', 'TIMEZONE', 'APP_ENV', 'APP_DEBUG', 'OPENWEBUI_ENABLED', 'SDCPU_ACTIVE', 'SDCPU_TUNNEL', 'GROQ_VISION_FOR_ALL_MODELS', 'IMAGEGEN_PROFILE', 'IMAGEGEN_COMPUTE_MODE', 'IMAGEGEN_STEPS', 'IMAGEGEN_GUIDANCE_SCALE', 'IMAGEGEN_WIDTH', 'IMAGEGEN_HEIGHT'],
            'Database' => ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_GUEST_USER', 'DB_GUEST_PASSWORD'],
            'LLM Provider' => ['LLM_PROVIDER', 'LLM_MODEL', 'DEFAULT_PROVIDER'],
            'GROQ API' => ['GROQ_API_KEY', 'GROQ_TTS_MODEL', 'GROQ_STT_MODEL'],
            'Cerebras API' => ['CEREBRAS_API_KEY', 'CEREBRAS_API_URL'],
            'Novita API' => ['NOVITA_API_KEY'],
            'Local LLM' => ['LOCAL_LLM_URL', 'LOCAL_LLM_MODEL', 'VISION_MODEL_URL', 'VISION_MODEL_NAME'],
            'MCP Configuration' => ['MCP_SERVER_URL', 'USE_PY_STT', 'PYTHON3_PATH'],
            'Rate Limiting' => ['RATE_LIMIT_ADMIN_PERCENT', 'RATE_LIMIT_USER_PERCENT', 'RATE_LIMIT_VISITOR_PERCENT', 'RATE_LIMIT_FALLBACK_PROVIDER', 'RATE_LIMIT_FALLBACK_THRESHOLD'],
            'Token Limits' => ['MAX_TOKENS_BASE', 'MAX_TOKENS_ADMIN_PERCENT', 'MAX_TOKENS_USER_PERCENT', 'MAX_TOKENS_VISITOR_PERCENT'],
            'TTS Limits' => ['TTS_LIMIT_ADMIN_HOURLY', 'TTS_LIMIT_USER_HOURLY', 'TTS_LIMIT_VISITOR_SESSION', 'TTS_SILENT_STOP_PERCENT'],
            'HuggingFace Models' => ['REASONING_HF_MODEL', 'VISION_HF_MODEL'],
            'Users' => ['EXPECTED_USERS', 'ADMIN_EMAIL', 'ADMIN_USERNAME', 'ADMIN_PASSWORD', 'DEFAULT_USERNAME', 'DEFAULT_EMAIL', 'DEFAULT_PASSWORD'],
        ];
        
        $written = [];
        
        foreach ($groups as $groupName => $keys) {
            $groupContent = '';
            foreach ($keys as $key) {
                if (isset($existing[$key])) {
                    $val = $existing[$key];
                    // Quote values with spaces
                    if (strpos($val, ' ') !== false && !str_starts_with($val, '"')) {
                        $val = '"' . $val . '"';
                    }
                    $groupContent .= "{$key}={$val}\n";
                    $written[$key] = true;
                }
            }
            if ($groupContent) {
                // Special header for Ecommerce / PayPal config
                if ($groupName === 'Ecommerce') {
                    $content .= "# -------------------------------------------------------\n# PayPal Configuration\n# Webhook and API credentials (if present). Avoid storing secrets in public repos.\n# -------------------------------------------------------\n";
                } elseif ($groupName === 'Datacenter') {
                    $content .= "# -------------------------------------------------------\n# Backblaze B2 / CDN\n# Backblaze / CDN configuration used for file uploads/serving.\n# -------------------------------------------------------\n";
                }
                $content .= "# {$groupName}\n{$groupContent}\n";
            }
        }
        
        // Write any remaining keys not in groups
        $remaining = '';
        foreach ($existing as $key => $val) {
            if (!isset($written[$key])) {
                if (strpos($val, ' ') !== false && !str_starts_with($val, '"')) {
                    $val = '"' . $val . '"';
                }
                $remaining .= "{$key}={$val}\n";
            }
        }
        if ($remaining) {
            $content .= "# Other\n{$remaining}";
        }
        
        // Backup current .env
        if (file_exists($envPath)) {
            $backupPath = STORAGE_PATH . '/backups/.env.' . date('Ymd_His');
            @copy($envPath, $backupPath);
        }
        
        // Write new .env
        if (file_put_contents($envPath, $content) === false) {
            throw new \Exception('Failed to write .env file');
        }
    }

    /**
     * Show first-time setup page (create admin)
     */
    private function showFirstTimeSetup(): void
    {
        // Generate CSRF token
        if (function_exists('generateCsrfToken')) {
            $csrf_token = generateCsrfToken();
        } else {
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            $csrf_token = $_SESSION['csrf_token'];
        }

        // Get current env values for prefill
        $envValues = $this->getEnvValues();

        View::view('live/setup', [
            'title' => 'Ginto AI - First Time Setup',
            'envValues' => $envValues,
            'csrf_token' => $csrf_token,
            'error' => $_GET['error'] ?? null
        ]);
        exit;
    }

    /**
     * Create the first admin user
     */
    private function createFirstAdmin(array $input): void
    {
        try {
            // Validate input
            $email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $username = preg_replace('/[^a-zA-Z0-9_\-]/', '', $input['username'] ?? '');
            $password = $input['password'] ?? '';
            $firstName = strip_tags($input['first_name'] ?? '');
            $lastName = strip_tags($input['last_name'] ?? '');

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('Valid email is required');
            }
            if (empty($username) || strlen($username) < 3) {
                throw new \Exception('Username must be at least 3 characters');
            }
            if (empty($password) || strlen($password) < 6) {
                throw new \Exception('Password must be at least 6 characters');
            }

            // Create admin user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $this->db->insert('users', [
                'email' => $email,
                'username' => $username,
                'password' => $hashedPassword,
                'first_name' => $firstName ?: 'Admin',
                'last_name' => $lastName ?: 'User',
                'role' => 'admin',
                'payment_status' => 'paid',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            $userId = $this->db->id();

            // Auto-login the new admin
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = 'admin';
            $_SESSION['is_admin'] = true;

            echo json_encode([
                'success' => true,
                'message' => 'Admin account created successfully',
                'redirect' => '/live'
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
