<?php
/**
 * Ginto AI - Bootstrap Setup
 * 
 * This file handles /live when there's no .env file yet.
 * It provides a minimal interface to create the initial configuration.
 * 
 * This is loaded directly by public/index.php before Composer autoloader
 * when no .env exists.
 */

// Define ROOT_PATH if not already defined
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', dirname(ROOT_PATH) . '/storage');
}

// Ensure storage directory exists
if (!is_dir(STORAGE_PATH)) {
    @mkdir(STORAGE_PATH, 0755, true);
}

// Start session for CSRF
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle POST request - create .env and admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    // CSRF validation
    $token = $input['csrf_token'] ?? '';
    if (empty($token) || $token !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    
    try {
        // Validate required fields
        $dbHost = $input['db_host'] ?? '127.0.0.1';
        $dbPort = $input['db_port'] ?? '3306';
        $dbName = $input['db_name'] ?? '';
        $dbUser = $input['db_user'] ?? '';
        $dbPass = $input['db_pass'] ?? '';
        
        $adminEmail = filter_var($input['admin_email'] ?? '', FILTER_SANITIZE_EMAIL);
        $adminUsername = preg_replace('/[^a-zA-Z0-9_\-]/', '', $input['admin_username'] ?? '');
        $adminPassword = $input['admin_password'] ?? '';
        
        if (empty($dbName)) throw new Exception('Database name is required');
        if (empty($dbUser)) throw new Exception('Database user is required');
        if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Valid admin email is required');
        }
        if (empty($adminUsername) || strlen($adminUsername) < 3) {
            throw new Exception('Admin username must be at least 3 characters');
        }
        if (empty($adminPassword) || strlen($adminPassword) < 6) {
            throw new Exception('Admin password must be at least 6 characters');
        }
        
        // Test database connection
        try {
            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
        
        // Create .env file
        $envContent = <<<ENV
# Ginto AI Configuration
# Generated: {date('Y-m-d H:i:s')}

# Site
APP_NAME="Ginto AI"
APP_URL=http://localhost
APP_ENV=production
APP_DEBUG=false
TIMEZONE=UTC

# Database
DB_HOST={$dbHost}
DB_PORT={$dbPort}
DB_NAME={$dbName}
DB_USER={$dbUser}
DB_PASS={$dbPass}

# Admin
ADMIN_EMAIL={$adminEmail}
ADMIN_USERNAME={$adminUsername}

# LLM (configure in /live after setup)
LLM_PROVIDER=groq
DEFAULT_PROVIDER=cerebras

# Session
SESSION_LIFETIME=315360000
ENV;

        if (file_put_contents(ROOT_PATH . '/.env', $envContent) === false) {
            throw new Exception('Failed to create .env file. Check write permissions.');
        }
        
        // Create admin user in database
        $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (email, username, password_hash, firstname, lastname, is_admin, payment_status, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, 'paid', 'active', NOW(), NOW())");
        $stmt->execute([$adminEmail, $adminUsername, $hashedPassword, 'Admin', 'User']);
        $userId = $pdo->lastInsertId();
        
        // Create .installed marker
        @file_put_contents(STORAGE_PATH . '/.installed', date('Y-m-d H:i:s') . ' - installed via /live bootstrap');
        
        // Auto-login
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $adminUsername;
        $_SESSION['email'] = $adminEmail;
        $_SESSION['is_admin'] = true;
        
        echo json_encode([
            'success' => true,
            'message' => 'Setup complete! Redirecting...',
            'redirect' => '/chat'
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Render setup page
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ginto AI - Initial Setup</title>
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/tailwind.css">
    <style>
        .input-field { width: 100%; padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid #4b5563; background: #374151; color: white; }
        .input-field:focus { outline: none; ring: 2px; ring-color: #3b82f6; border-color: transparent; }
        .label-text { display: block; font-size: 0.875rem; font-weight: 500; color: #d1d5db; margin-bottom: 0.5rem; }
        .section-title { font-size: 1rem; font-weight: 600; color: white; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-900 to-gray-800 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-lg w-full">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 mb-4">
                <i class="fas fa-robot text-4xl text-white"></i>
            </div>
            <h1 class="text-3xl font-bold text-white">Ginto AI Setup</h1>
            <p class="text-gray-400 mt-2">Configure your database and create admin account</p>
        </div>

        <!-- Setup Form -->
        <div class="bg-gray-800 rounded-2xl shadow-xl p-8">
            <form id="setup-form" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <!-- Database Section -->
                <div>
                    <h3 class="section-title"><i class="fas fa-database text-blue-400"></i> Database</h3>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="label-text">Host</label>
                            <input type="text" name="db_host" class="input-field" value="127.0.0.1">
                        </div>
                        <div>
                            <label class="label-text">Port</label>
                            <input type="text" name="db_port" class="input-field" value="3306">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="label-text">Database Name <span class="text-red-400">*</span></label>
                        <input type="text" name="db_name" class="input-field" placeholder="ginto" required>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label-text">Username <span class="text-red-400">*</span></label>
                            <input type="text" name="db_user" class="input-field" placeholder="root" required>
                        </div>
                        <div>
                            <label class="label-text">Password</label>
                            <input type="password" name="db_pass" class="input-field" placeholder="••••••••">
                        </div>
                    </div>
                </div>

                <!-- Admin Section -->
                <div class="pt-4 border-t border-gray-700">
                    <h3 class="section-title"><i class="fas fa-user-shield text-purple-400"></i> Admin Account</h3>
                    <div class="mb-4">
                        <label class="label-text">Username <span class="text-red-400">*</span></label>
                        <input type="text" name="admin_username" class="input-field" placeholder="admin" required minlength="3">
                    </div>
                    <div class="mb-4">
                        <label class="label-text">Email <span class="text-red-400">*</span></label>
                        <input type="email" name="admin_email" class="input-field" placeholder="admin@example.com" required>
                    </div>
                    <div>
                        <label class="label-text">Password <span class="text-red-400">*</span></label>
                        <input type="password" name="admin_password" class="input-field" placeholder="••••••••" required minlength="6">
                    </div>
                </div>

                <button type="submit" id="submit-btn" 
                        class="w-full py-3 px-4 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white font-semibold rounded-lg transition-all duration-200 flex items-center justify-center gap-2">
                    <i class="fas fa-rocket"></i>
                    Complete Setup
                </button>
            </form>

            <p class="text-gray-500 text-sm text-center mt-6">
                <i class="fas fa-info-circle mr-1"></i>
                You can configure API keys and LLM providers after setup in /live
            </p>
        </div>
    </div>

    <script>
        document.getElementById('setup-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const btn = document.getElementById('submit-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Setting up...';
            btn.disabled = true;
            
            try {
                const formData = new FormData(e.target);
                const data = Object.fromEntries(formData.entries());
                
                const response = await fetch('/live', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    btn.innerHTML = '<i class="fas fa-check"></i> Success!';
                    setTimeout(() => {
                        window.location.href = result.redirect || '/chat';
                    }, 1000);
                } else {
                    throw new Error(result.error || 'Setup failed');
                }
            } catch (error) {
                btn.innerHTML = originalText;
                btn.disabled = false;
                alert('Error: ' + error.message);
            }
        });
    </script>
</body>
</html>
