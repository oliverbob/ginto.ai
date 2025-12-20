<?php
/**
 * Ginto CMS - Main Entry Point
 * Unified routing system for legacy user features and new CMS functionality
 */

define('ROOT_PATH', dirname(__DIR__));
define('STORAGE_PATH', dirname(__DIR__, 2) . '/storage');

// Ensure storage directories exist with proper structure
if (!is_dir(STORAGE_PATH)) {
    @mkdir(STORAGE_PATH, 0755, true);
}
foreach (['sessions', 'logs', 'cache', 'backups', 'backups/repo', 'temp', 'uploads'] as $subdir) {
    $path = STORAGE_PATH . '/' . $subdir;
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
}

// Check if installation is complete - redirect to installer if not
$installedMarkerExists = file_exists(ROOT_PATH . '/.installed') || file_exists(STORAGE_PATH . '/.installed');
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);

// Handle /install routes directly
if (strpos($path, '/install') === 0) {
    // Serve installer files directly from the install directory
    $installPath = $path === '/install' || $path === '/install/' ? '/install/index.html' : $path;
    $filePath = ROOT_PATH . $installPath;
    
    // Handle install.php
    if ($path === '/install.php' || $path === '/install/install.php') {
        require ROOT_PATH . '/install/install.php';
        exit;
    }
    
    // Serve static files from install directory
    if (file_exists($filePath)) {
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
        ];
        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext]);
        }
        readfile($filePath);
        exit;
    }
}

// Redirect to installer if not installed
if (!$installedMarkerExists) {
    // Allow access to static assets
    if (strpos($path, '/assets') !== 0 && strpos($path, '/install') !== 0) {
        header('Location: /install/');
        exit;
    }
}

// Load Composer autoloader before using any classes
require ROOT_PATH . '/vendor/autoload.php';

// Load environment variables from .env file
$dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
$dotenv->load();

// In development disable displaying PHP warnings/errors in HTTP output so
// they don't interfere with streaming endpoints (they will still be logged).
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
// Log PHP errors to STORAGE_PATH/logs directory (outside repo)
$logDir = STORAGE_PATH . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
@ini_set('error_log', $logDir . '/ginto.log');
error_reporting(E_ALL);

// Start session and set secure cookie parameters
$longLifetime = 315360000; // 10 years in seconds
ini_set('session.save_path', STORAGE_PATH . '/sessions');
ini_set('session.gc_maxlifetime', (string)$longLifetime);
ini_set('session.cookie_lifetime', (string)$longLifetime);
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
$cookieSecure = false;
if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    ini_set('session.cookie_secure', '1');
    $cookieSecure = true;
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (session_status() === PHP_SESSION_ACTIVE) {
    setcookie(session_name(), session_id(), time() + $longLifetime, '/', '', $cookieSecure, true);
    // Generate a public_id for visitors (used for sandbox mapping)
    if (empty($_SESSION['public_id']) && empty($_SESSION['user_id'])) {
        $_SESSION['public_id'] = bin2hex(random_bytes(16));
    }
}

// Define global IS_ADMIN constant
define('IS_ADMIN', \Ginto\Controllers\UserController::isAdmin());

// Normalize session for admins
if (IS_ADMIN) {
    $_SESSION['is_admin'] = true;
}

// CSRF Helper Functions
function generateCsrfToken(bool $forVisitor = false): string {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    // For visitors (not logged in), create expiring tokens (1 hour max)
    // But reuse existing token if it hasn't expired yet
    if ($forVisitor || empty($_SESSION['user_id'])) {
        // Check if we already have a valid non-expired token
        if (!empty($_SESSION['csrf_token']) && !empty($_SESSION['csrf_token_expires'])) {
            if (time() < $_SESSION['csrf_token_expires']) {
                return $_SESSION['csrf_token']; // Reuse existing valid token
            }
        }
        // Generate new token (first time or expired)
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_expires'] = time() + 3600; // 1 hour expiration
        return $token;
    }
    
    // For logged-in users, use persistent token per session
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        unset($_SESSION['csrf_token_expires']); // No expiration for logged-in users
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    // Check if token exists
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    
    // Check expiration for visitor tokens
    if (isset($_SESSION['csrf_token_expires'])) {
        if (time() > $_SESSION['csrf_token_expires']) {
            // Token expired - clear it
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_expires']);
            return false;
        }
    }
    
    return true;
}

// Initialize database connection
use Ginto\Core\Database;
use Ginto\Helpers\CountryHelper;
use Core\Router;

$db = null;
try {
    $db = Database::getInstance();
} catch (\Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Load countries helper
$countries = CountryHelper::getCountries();

$router = new Router($db);

// ===========================
// Dynamic Sandbox File Router
// ===========================
// Serves files from client sandboxes at /<sandbox_id>/<file_path>
// Non-admin users are jailed to their own sandbox only.
(function() use ($db) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($requestUri, PHP_URL_PATH);
    
    // Skip if not a potential sandbox path (must be /<sandbox_id>/...)
    // Sandbox IDs are alphanumeric, 12 characters
    if (!preg_match('#^/([a-z0-9]{8,16})/(.*)$#i', $path, $matches)) {
        return; // Not a sandbox URL, let normal routing handle it
    }
    
    $sandboxId = $matches[1];
    $filePath = $matches[2];
    
    // Verify this is actually a sandbox directory
    $clientsRoot = ROOT_PATH . '/clients';
    $sandboxRoot = $clientsRoot . '/' . $sandboxId;
    
    if (!is_dir($sandboxRoot)) {
        return; // Not a valid sandbox, let normal routing handle (will 404)
    }
    
    // Security: Check user access
    $isAdmin = IS_ADMIN ?? false;
    $userSandboxId = null;
    
    if (!$isAdmin) {
        // For non-admin users, get their sandbox ID and jail them to it
        try {
            $userId = $_SESSION['user_id'] ?? null;
            $publicId = $_SESSION['public_id'] ?? null;
            
            if ($userId) {
                $row = $db->get('client_sandboxes', 'sandbox_id', ['user_id' => $userId]);
                $userSandboxId = $row ?: null;
            } elseif ($publicId) {
                $row = $db->get('client_sandboxes', 'sandbox_id', ['public_id' => $publicId]);
                $userSandboxId = $row ?: null;
            }
            
            // Non-admin trying to access a sandbox that's not theirs
            if ($userSandboxId !== $sandboxId) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Access denied: You can only access your own sandbox.']);
                exit;
            }
        } catch (\Throwable $e) {
            // DB error - deny access for safety
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Access denied: Could not verify sandbox ownership.']);
            exit;
        }
    }
    
    // Build full path and validate it's within sandbox (prevent directory traversal)
    $fullPath = realpath($sandboxRoot . '/' . $filePath);
    $realSandboxRoot = realpath($sandboxRoot);
    
    // If file doesn't exist or path traversal attempt
    if (!$fullPath || strpos($fullPath, $realSandboxRoot) !== 0) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'File not found or access denied.']);
        exit;
    }
    
    // Serve the file
    if (is_dir($fullPath)) {
        // Try to serve index.html or index.php from directory
        if (file_exists($fullPath . '/index.php')) {
            $fullPath = $fullPath . '/index.php';
        } elseif (file_exists($fullPath . '/index.html')) {
            $fullPath = $fullPath . '/index.html';
        } else {
            http_response_code(403);
            echo 'Directory listing not allowed.';
            exit;
        }
    }
    
    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    
    // PHP files: execute them with security restrictions
    if ($ext === 'php') {
        // Read file content and check for dangerous functions
        $content = file_get_contents($fullPath);
        
        // List of dangerous/blocked functions for sandbox execution
        $blockedFunctions = [
            // Info disclosure
            'phpinfo',
            'phpversion',
            'php_uname',
            'php_sapi_name',
            'getmypid',
            'getmyuid',
            'getmygid',
            'get_current_user',
            'getmyinode',
            'get_cfg_var',
            'get_include_path',
            'get_loaded_extensions',
            'get_extension_funcs',
            'get_defined_functions',
            'get_defined_vars',
            'get_defined_constants',
            'get_resources',
            
            // Command execution
            'exec',
            'shell_exec',
            'system',
            'passthru',
            'popen',
            'proc_open',
            'proc_close',
            'proc_get_status',
            'proc_nice',
            'proc_terminate',
            'pcntl_exec',
            'pcntl_fork',
            'pcntl_signal',
            'pcntl_alarm',
            'pcntl_waitpid',
            
            // Code execution / eval
            'eval',
            'assert',
            'create_function',
            'call_user_func',
            'call_user_func_array',
            'forward_static_call',
            'forward_static_call_array',
            'ReflectionFunction',
            
            // File system - read
            'file_get_contents',
            'fopen',
            'fread',
            'fgets',
            'fgetc',
            'fgetss',
            'fgetcsv',
            'readfile',
            'file',
            'parse_ini_file',
            'parse_ini_string',
            'show_source',
            'highlight_file',
            
            // File system - write
            'file_put_contents',
            'fwrite',
            'fputs',
            'fputcsv',
            'ftruncate',
            'flock',
            'touch',
            
            // File system - modify
            'unlink',
            'rmdir',
            'mkdir',
            'rename',
            'copy',
            'move_uploaded_file',
            'chmod',
            'chown',
            'chgrp',
            'symlink',
            'link',
            'tempnam',
            'tmpfile',
            
            // File system - directory
            'opendir',
            'readdir',
            'scandir',
            'glob',
            'dir',
            
            // Database - MySQL/MariaDB
            'mysqli_connect',
            'mysqli_real_connect',
            'mysqli_init',
            'mysql_connect',
            'mysql_pconnect',
            
            // Database - PDO
            'PDO',
            
            // Database - PostgreSQL
            'pg_connect',
            'pg_pconnect',
            
            // Database - SQLite
            'sqlite_open',
            'sqlite_popen',
            'SQLite3',
            
            // Database - Other
            'odbc_connect',
            'oci_connect',
            'oci_pconnect',
            'db2_connect',
            'db2_pconnect',
            'ifx_connect',
            'ifx_pconnect',
            'ibase_connect',
            'ibase_pconnect',
            'mssql_connect',
            'mssql_pconnect',
            'sybase_connect',
            'sybase_pconnect',
            'maxdb_connect',
            'maxdb_real_connect',
            
            // Network
            'curl_init',
            'curl_exec',
            'curl_multi_exec',
            'curl_multi_init',
            'fsockopen',
            'pfsockopen',
            'stream_socket_client',
            'stream_socket_server',
            'stream_socket_accept',
            'socket_create',
            'socket_connect',
            'socket_bind',
            'socket_listen',
            'ftp_connect',
            'ftp_ssl_connect',
            'ssh2_connect',
            'ssh2_exec',
            
            // Email
            'mail',
            'imap_open',
            'imap_mail',
            
            // Environment
            'putenv',
            'getenv',
            'ini_set',
            'ini_alter',
            'ini_restore',
            'ini_get',
            'ini_get_all',
            'set_include_path',
            'restore_include_path',
            'dl',
            'apache_setenv',
            'apache_getenv',
            'apache_child_terminate',
            
            // Session/Headers
            'session_start',
            'session_destroy',
            'session_regenerate_id',
            'session_id',
            'session_name',
            'setcookie',
            'setrawcookie',
            'header',
            'header_remove',
            'headers_sent',
            'headers_list',
            
            // Output control (can be used to hijack)
            'ob_start',
            'ob_flush',
            'ob_end_flush',
            'ob_get_contents',
            
            // POSIX
            'posix_getpwuid',
            'posix_getpwnam',
            'posix_getgrgid',
            'posix_getgrnam',
            'posix_kill',
            'posix_mkfifo',
            'posix_setuid',
            'posix_setgid',
            'posix_seteuid',
            'posix_setegid',
            
            // Misc dangerous
            'escapeshellarg',
            'escapeshellcmd',
            'preg_replace_callback', // can execute code
            'register_shutdown_function',
            'register_tick_function',
            'set_error_handler',
            'set_exception_handler',
            'restore_error_handler',
            'restore_exception_handler',
            'debug_backtrace',
            'debug_print_backtrace',
            'var_dump', // info disclosure in production
            'print_r', // info disclosure in production
            'var_export',
            'serialize',
            'unserialize', // object injection attacks
            'extract', // variable injection
            'parse_str', // variable injection without second param
            'import_request_variables',
            'define_syslog_variables',
            
            // Superglobals access (check for direct usage)
            '_SESSION',
            '_SERVER',
            '_ENV',
            '_COOKIE',
        ];
        
        // Also block class instantiation for database classes
        $blockedClasses = [
            'PDO',
            'mysqli',
            'SQLite3',
            'MongoDB',
            'Redis',
            'Memcache',
            'Memcached',
            'ReflectionClass',
            'ReflectionMethod',
            'ReflectionFunction',
            'ReflectionProperty',
            // Block CMS internal classes
            'Ginto\\\\',  // All Ginto namespace classes
            'App\\\\',    // All App namespace classes
            'Core\\\\',   // Core namespace
            'Medoo',      // Database library
            'Dotenv',     // Env loader
            'Parsedown',  // Markdown parser
        ];
        
        // Blocked superglobals and special variables
        $blockedSuperglobals = [
            '$_SESSION',
            '$_SERVER',
            '$_ENV',
            '$_COOKIE',
            '$_GET',
            '$_POST',
            '$_REQUEST',
            '$_FILES',
            '$GLOBALS',
            '$HTTP_RAW_POST_DATA',
            '$http_response_header',
            '$argc',
            '$argv',
            // CMS internal variables
            '$db',
            '$router',
            '$countries',
            '$csrf_token',
            '$dotenv',
        ];
        
        // Blocked CMS namespaces, use statements, and class access patterns
        $blockedNamespacePatterns = [
            'Ginto\\\\',           // Ginto namespace
            'App\\\\',             // App namespace  
            'Core\\\\',            // Core namespace
            'Medoo\\\\',           // Medoo database
            'Dotenv\\\\',          // Dotenv
            'Parsedown',           // Markdown
        ];
        
        // Build regex pattern to detect blocked function calls
        $pattern = '/\b(' . implode('|', array_map('preg_quote', $blockedFunctions)) . ')\s*\(/i';
        
        // Pattern for blocked class instantiation: new ClassName or new \Namespace\Class
        $classPattern = '/\bnew\s+\\\\?(' . implode('|', array_map(function($c) {
            return preg_quote($c, '/');
        }, $blockedClasses)) . ')/i';
        
        // Pattern for superglobals: $_SESSION, $_SERVER, etc.
        $superglobalPattern = '/(' . implode('|', array_map(function($s) {
            return preg_quote($s, '/');
        }, $blockedSuperglobals)) . ')\b/';
        
        // Pattern for CMS namespace access: use Ginto\..., new \Ginto\..., Ginto\Class::method()
        $namespacePattern = '/\b(use\s+|new\s+\\\\?|\\\\?)(' . implode('|', array_map(function($n) {
            return preg_quote($n, '/');
        }, $blockedNamespacePatterns)) . ')/i';
        
        // Pattern for accessing global CMS variables: global $db, $GLOBALS['db']
        $globalAccessPattern = '/\b(global\s+\$\w+|class_exists|interface_exists|function_exists|defined|constant)\s*\(/i';
        
        // Pattern for autoloader manipulation
        $autoloadPattern = '/\b(spl_autoload|__autoload|class_alias|get_declared_classes|get_declared_interfaces|get_parent_class|get_class|get_class_vars|get_class_methods|get_object_vars|is_subclass_of|class_implements|class_parents)\s*\(/i';
        
        if (preg_match($pattern, $content, $matches)) {
            http_response_code(403);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><title>Security Error</title></head><body>';
            echo '<h1>🔒 Sandbox Security Restriction</h1>';
            echo '<p>The function <code>' . htmlspecialchars($matches[1]) . '()</code> is not allowed in sandbox mode.</p>';
            echo '<p>For security reasons, certain PHP functions are disabled in user sandboxes.</p>';
            echo '</body></html>';
            exit;
        }
        
        if (preg_match($classPattern, $content, $matches)) {
            http_response_code(403);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><title>Security Error</title></head><body>';
            echo '<h1>🔒 Sandbox Security Restriction</h1>';
            echo '<p>The class <code>' . htmlspecialchars($matches[1]) . '</code> cannot be instantiated in sandbox mode.</p>';
            echo '<p>For security reasons, database connections and certain classes are disabled in user sandboxes.</p>';
            echo '</body></html>';
            exit;
        }
        
        if (preg_match($superglobalPattern, $content, $matches)) {
            http_response_code(403);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><title>Security Error</title></head><body>';
            echo '<h1>🔒 Sandbox Security Restriction</h1>';
            echo '<p>Access to <code>' . htmlspecialchars($matches[1]) . '</code> is not allowed in sandbox mode.</p>';
            echo '<p>For security reasons, superglobal variables and session data are protected in user sandboxes.</p>';
            echo '</body></html>';
            exit;
        }
        
        if (preg_match($namespacePattern, $content, $matches)) {
            http_response_code(403);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><title>Security Error</title></head><body>';
            echo '<h1>🔒 Sandbox Security Restriction</h1>';
            echo '<p>Access to CMS namespace <code>' . htmlspecialchars($matches[2]) . '</code> is not allowed in sandbox mode.</p>';
            echo '<p>For security reasons, internal application classes and libraries are protected.</p>';
            echo '</body></html>';
            exit;
        }
        
        if (preg_match($autoloadPattern, $content, $matches)) {
            http_response_code(403);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><title>Security Error</title></head><body>';
            echo '<h1>🔒 Sandbox Security Restriction</h1>';
            echo '<p>The function <code>' . htmlspecialchars($matches[1]) . '()</code> is not allowed in sandbox mode.</p>';
            echo '<p>For security reasons, class introspection and autoloader access are disabled.</p>';
            echo '</body></html>';
            exit;
        }
        
        if (preg_match($globalAccessPattern, $content, $matches)) {
            http_response_code(403);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><title>Security Error</title></head><body>';
            echo '<h1>🔒 Sandbox Security Restriction</h1>';
            echo '<p>The function <code>' . htmlspecialchars($matches[1]) . '()</code> is not allowed in sandbox mode.</p>';
            echo '<p>For security reasons, global variable access and constant checking are disabled.</p>';
            echo '</body></html>';
            exit;
        }
        
        // Clear sensitive superglobals before executing sandbox code
        $savedSession = $_SESSION ?? [];
        $savedServer = $_SERVER;
        $savedEnv = $_ENV;
        $savedCookie = $_COOKIE;
        
        // Provide sanitized/empty versions to sandbox
        $_SESSION = [];
        $_ENV = [];
        $_COOKIE = [];
        
        // Provide minimal safe $_SERVER
        $_SERVER = [
            'REQUEST_METHOD' => $savedServer['REQUEST_METHOD'] ?? 'GET',
            'REQUEST_URI' => '/' . $sandboxId . '/' . $filePath,
            'SCRIPT_NAME' => '/' . $sandboxId . '/' . $filePath,
            'PHP_SELF' => '/' . $sandboxId . '/' . $filePath,
            'DOCUMENT_ROOT' => $realSandboxRoot,
            'SCRIPT_FILENAME' => $fullPath,
            'QUERY_STRING' => $savedServer['QUERY_STRING'] ?? '',
            'HTTP_HOST' => $savedServer['HTTP_HOST'] ?? 'localhost',
            'SERVER_NAME' => $savedServer['SERVER_NAME'] ?? 'localhost',
            'SERVER_PORT' => $savedServer['SERVER_PORT'] ?? '80',
            'HTTPS' => $savedServer['HTTPS'] ?? '',
        ];
        
        // Change to sandbox directory for relative paths in user code
        chdir($sandboxRoot);
        
        // Include and execute the PHP file
        try {
            include $fullPath;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Error executing file: ' . htmlspecialchars($e->getMessage());
        } finally {
            // Restore original superglobals after sandbox execution
            $_SESSION = $savedSession;
            $_SERVER = $savedServer;
            $_ENV = $savedEnv;
            $_COOKIE = $savedCookie;
        }
        exit;
    }
    
    // Static files: serve with proper MIME type
    $mimeTypes = [
        'html' => 'text/html',
        'htm' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'txt' => 'text/plain',
        'md' => 'text/markdown',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'pdf' => 'application/pdf',
        'zip' => 'application/zip',
    ];
    
    $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . filesize($fullPath));
    
    // Cache static assets
    if (in_array($ext, ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'webp', 'woff', 'woff2', 'ttf', 'eot'])) {
        header('Cache-Control: public, max-age=3600');
    }
    
    readfile($fullPath);
    exit;
})();

// Include all main application routes from src/Routes/web.php
require_once ROOT_PATH . '/src/Routes/web.php';
// ...existing code...

// User commissions page (renders `src/Views/user/commissions.php` via controller)
req($router, '/user/commissions', function() use ($db) {
    if (empty($_SESSION['user_id'])) {
        header('Location: /login');
        exit;
    }

    try {
        $ctrl = new \Ginto\Controllers\CommissionsController();
        return $ctrl->index();
    } catch (\Throwable $e) {
        // Fallback: attempt to include view directly if controller fails
        $viewPath = ROOT_PATH . '/src/Views/user/commissions.php';
        if (file_exists($viewPath)) {
            include $viewPath;
            exit;
        }
        http_response_code(500);
        echo 'Commissions page not available: ' . $e->getMessage();
        exit;
    }
});


// Compact-only user network view (dev route)
req($router, '/user/network-tree/compact-view', function() use ($db) {
    // Dev convenience: if no session user, try to auto-login user 'oliverbob'
    if (empty($_SESSION['user_id'])) {
        try {
            $userId = $db->get('users', 'id', ['username' => 'oliverbob']);
            if ($userId) {
                $_SESSION['user_id'] = (int)$userId;
            }
        } catch (\Throwable $_) {
            // ignore - proceed without login if DB not available
        }
    }
    // Include the compact view file at `src/Views/user/network-tree/compact-view.php`
    // (previously lived at `Views/...`)
    $viewPath = ROOT_PATH . '/src/Views/user/network-tree/compact-view.php';
    if (file_exists($viewPath)) {
        include $viewPath;
        exit;
    }

    // Fallback: check for `src/Views/users/...` (older layout) to be tolerant
    $fallback = ROOT_PATH . '/src/Views/users/network-tree/compact-view.php';
    if (file_exists($fallback)) {
        include $fallback;
        exit;
    }

    http_response_code(500);
    echo "Compact view not found. Expected: $viewPath (or fallback: $fallback)";
});

// Webhook endpoint (PayPal and status view)
req($router, '/webhook', function() use ($db) {
    try {
        // Prefer the dedicated controller if available
        if (class_exists('\App\Controllers\WebhookController')) {
            try {
                $ctrl = new \App\Controllers\WebhookController($db);
                return $ctrl->webhook();
            } catch (\Throwable $e) {
                error_log('WebhookController init failed: ' . $e->getMessage());
                // If it's a POST (webhook delivery) return 500 so sender can retry.
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    http_response_code(500);
                    echo json_encode(['error' => 'Webhook controller not configured']);
                    exit;
                }
                // For GET or OPTIONS, fall back to the static view to show status/info.
            }
        }

        // Fallback: include the view file directly
        $viewPath = ROOT_PATH . '/src/Views/webhook/webhook.php';
        if (file_exists($viewPath)) { include $viewPath; exit; }

        http_response_code(500); echo 'Webhook handler not available'; exit;
    } catch (\Throwable $e) {
        http_response_code(500); error_log('Webhook route error: ' . $e->getMessage()); echo 'Webhook route error'; exit;
    }
});

req($router, '/webhook/status', function() use ($db) {
    try {
        if (class_exists('\App\Controllers\WebhookController')) {
            try {
                $ctrl = new \App\Controllers\WebhookController($db);
                return $ctrl->saiCodeCheck();
            } catch (\Throwable $e) {
                error_log('WebhookController init failed (status): ' . $e->getMessage());
                // Fall back to view below
            }
        }
        $viewPath = ROOT_PATH . '/src/Views/webhook/webhook.php';
        if (file_exists($viewPath)) { include $viewPath; exit; }
        http_response_code(500); echo 'Webhook status page not available'; exit;
    } catch (\Throwable $e) { error_log('Webhook status route error: ' . $e->getMessage()); http_response_code(500); echo 'Webhook status route error'; exit; }
});

// OAuth callback for third-party integrations (GitHub OAuth / App callback)
req($router, '/auth/github/callback', function() use ($db) {
    try {
        // Log the incoming callback for debugging (do not leak secrets)
        $logDir = STORAGE_PATH . '/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        $payload = [
            'time' => date('c'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'query' => $_GET,
        ];
        // If POST, capture raw body too (careful with secrets)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $raw = file_get_contents('php://input');
            if ($raw) $payload['raw_body'] = substr($raw, 0, 4096); // truncate
        }
        file_put_contents($logDir . '/github_oauth_callback.log', json_encode($payload) . PHP_EOL, FILE_APPEND | LOCK_EX);

        // Simple response to show the callback was received
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>GitHub Callback Received</h1>';
        echo '<p>Thank you — the callback was received. Check <code>storage/github_oauth_callback.log</code> for details.</p>';
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo 'Callback handler error';
        error_log('Auth callback error: ' . $e->getMessage());
        exit;
    }
});

// Dedicated GitHub webhook endpoint (separate from PayPal /webhook)
req($router, '/github/webhook', function() use ($db) {
    try {
        $secret = getenv('GITHUB_WEBHOOK_SECRET') ?: ($_ENV['GITHUB_WEBHOOK_SECRET'] ?? null);
        if (empty($secret)) {
            error_log('GitHub webhook received but GITHUB_WEBHOOK_SECRET is not configured');
            http_response_code(500);
            echo json_encode(['error' => 'Webhook secret not configured']);
            exit;
        }

        $raw = file_get_contents('php://input');
        $headerSig = null;
        // Prefer the server-populated header
        if (!empty($_SERVER['HTTP_X_HUB_SIGNATURE_256'])) {
            $headerSig = $_SERVER['HTTP_X_HUB_SIGNATURE_256'];
        } else {
            // Fallback to getallheaders if available
            if (function_exists('getallheaders')) {
                $h = getallheaders();
                if (!empty($h['X-Hub-Signature-256'])) $headerSig = $h['X-Hub-Signature-256'];
                elseif (!empty($h['x-hub-signature-256'])) $headerSig = $h['x-hub-signature-256'];
            }
        }

        if (empty($headerSig)) {
            error_log('GitHub webhook missing X-Hub-Signature-256');
            http_response_code(400);
            echo json_encode(['error' => 'Missing signature header']);
            exit;
        }

        $computed = 'sha256=' . hash_hmac('sha256', $raw ?? '', $secret);
        if (!hash_equals($computed, $headerSig)) {
            error_log('GitHub webhook signature mismatch');
            http_response_code(401);
            echo json_encode(['error' => 'Invalid signature']);
            exit;
        }

        // Parse payload for logging and quick inspection
        $payload = json_decode($raw, true) ?: ['raw' => substr($raw ?? '', 0, 4096)];
        $logDir = STORAGE_PATH . '/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        $entry = [
            'time' => date('c'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'headers' => [
                'x-hub-signature-256' => $headerSig,
                'x-github-event' => $_SERVER['HTTP_X_GITHUB_EVENT'] ?? null,
            ],
            'payload' => $payload,
        ];
        file_put_contents($logDir . '/github_webhook.log', json_encode($entry) . PHP_EOL, FILE_APPEND | LOCK_EX);

        // Return success quickly
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    } catch (\Throwable $e) {
        error_log('GitHub webhook handler error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'internal error']);
        exit;
    }
});

// Dev helper: return a JSON containing the current session CSRF token (only allowed from localhost or when not in production)
req($router, '/dev/csrf', function() use ($db) {
    // This endpoint provides CSRF tokens for both logged-in users and visitors
    // Visitors get expiring tokens (1 hour max) for security
    
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    $isLoggedIn = !empty($_SESSION['user_id']);
    $isVisitor = !$isLoggedIn;
    
    // Generate token (with expiration for visitors)
    $token = generateCsrfToken($isVisitor);
    
    header('Content-Type: application/json');
    
    $response = [
        'success' => true, 
        'csrf_token' => $token, 
        'session_id' => session_id()
    ];
    
    // Include expiration info for visitors
    if ($isVisitor && isset($_SESSION['csrf_token_expires'])) {
        $response['expires_at'] = $_SESSION['csrf_token_expires'];
        $response['expires_in'] = $_SESSION['csrf_token_expires'] - time();
    }
    
    echo json_encode($response);
    exit;
});

// Dev helper: toggle CSRF bypass for debugging (localhost only, non-production only)
// Usage: POST /dev/csrf-bypass with {"enable": true|false}
// This sets a session flag that allows /chat endpoint to skip CSRF validation
req($router, '/dev/csrf-bypass', function() {
    // Strict security: localhost only AND non-production environment
    $remote = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production');
    
    if (!in_array($remote, ['127.0.0.1', '::1'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Localhost only']);
        exit;
    }
    
    if ($env === 'production') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Not available in production']);
        exit;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Return current status
        echo json_encode([
            'success' => true,
            'enabled' => !empty($_SESSION['dev_csrf_bypass']),
            'env' => $env,
            'session_id' => session_id()
        ]);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $enable = $input['enable'] ?? false;
        
        if ($enable) {
            $_SESSION['dev_csrf_bypass'] = true;
            echo json_encode(['success' => true, 'message' => 'CSRF bypass enabled for this session', 'warning' => 'DO NOT use in production!']);
        } else {
            unset($_SESSION['dev_csrf_bypass']);
            echo json_encode(['success' => true, 'message' => 'CSRF bypass disabled']);
        }
        exit;
    }
    
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
});

// Dev helper: quickly log in as the 'oliverbob' user (localhost only)
req($router, '/dev/login/oliverbob', function() use ($db) {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (!in_array($remote, ['127.0.0.1', '::1'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }

    try {
        $userId = $db->get('users', 'id', ['username' => 'oliverbob']);
        if (!$userId) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION['user_id'] = (int)$userId;
        // regenerate CSRF token to avoid reuse issues
        if (function_exists('generateCsrfToken')) generateCsrfToken();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'user_id' => $_SESSION['user_id'], 'session_id' => session_id()]);
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
});

// Dev helper: quickly log in as the 'admin' user (localhost only)
req($router, '/dev/login/admin', function() use ($db) {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (!in_array($remote, ['127.0.0.1', '::1'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }

    try {
        $userId = $db->get('users', 'id', ['username' => 'admin']);
        if (!$userId) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION['user_id'] = (int)$userId;
        if (function_exists('generateCsrfToken')) generateCsrfToken();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'user_id' => $_SESSION['user_id'], 'session_id' => session_id()]);
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
});

// Local dev helper: lightweight endpoint to POST a test chat message and proxy to the MCP
// Registered with the router so it runs before CSRF admin filters. Only allowed from localhost.
req($router, '/admin/pages/editor/chat_local_test', function() use ($db) {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production');
    if (!in_array($remote, ['127.0.0.1', '::1']) && $env === 'production') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];
    $message = '';
    if (!empty($data['message'])) {
        $message = trim($data['message']);
    } elseif (!empty($_GET['message'])) {
        $message = trim($_GET['message']);
    }
    if ($message === '') $message = "Hello from local test";

    $payload = [
        'jsonrpc' => '2.0',
        'id' => 'local_test_' . time(),
        'method' => 'tools/call',
        'params' => [
            'name' => 'chat_completion',
            'arguments' => [
                'model' => 'kimi_k2',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful assistant for testing.'],
                    ['role' => 'user', 'content' => $message]
                ]
            ]
        ]
    ];

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => json_encode($payload),
            'timeout' => 15
        ]
    ];

    $ctx = stream_context_create($opts);
    $mcpUrl = 'http://127.0.0.1:9010/mcp';
    $res = @file_get_contents($mcpUrl, false, $ctx);
    if ($res === false) {
        // Fallback to CLI curl if allow_url_fopen is disabled or file_get_contents failed
        $cmd = 'curl -sS -X POST ' . escapeshellarg($mcpUrl) . ' -H ' . escapeshellarg('Content-Type: application/json') . ' -H ' . escapeshellarg('Accept: application/json') . ' -d ' . escapeshellarg(json_encode($payload));
        $res = shell_exec($cmd);
        if ($res === null || $res === '') {
            http_response_code(502);
            echo json_encode(['success' => false, 'message' => 'MCP request failed']);
            exit;
        }
    }

    $json = json_decode($res, true);
    $outputText = '';
    if (!empty($json['result']['content']) && is_array($json['result']['content'])) {
        foreach ($json['result']['content'] as $c) {
            if (!empty($c['text'])) $outputText .= $c['text'];
            elseif (!empty($c['type']) && $c['type'] === 'text' && !empty($c['text'])) $outputText .= $c['text'];
        }
    }

    if ($outputText === '') {
        header('Content-Type: application/json');
        echo $res;
        exit;
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo $outputText;
    exit;
});

// Alternate dev route (non-admin) to avoid CSRF filters: /dev/editor/chat_local_test
req($router, '/dev/editor/chat_local_test', function() use ($db) {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production');
    if (!in_array($remote, ['127.0.0.1', '::1']) && $env === 'production') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];
    $message = '';
    if (!empty($data['message'])) {
        $message = trim($data['message']);
    } elseif (!empty($_GET['message'])) {
        $message = trim($_GET['message']);
    }
    if ($message === '') $message = "Hello from local test";

    // Optional: accept a base64-encoded repo-relative path via GET or POST 'file'
    $fileEnc = $data['file'] ?? ($_GET['file'] ?? '');
    $sysExtra = '';
    if (!empty($fileEnc)) {
        $decoded = base64_decode(urldecode($fileEnc));
        if ($decoded && is_string($decoded)) {
            $normalized = str_replace('..', '', $decoded);
            $fullPath = realpath(ROOT_PATH . '/' . $normalized);
            if ($fullPath && str_starts_with($fullPath, realpath(ROOT_PATH))) {
                $content = @file_get_contents($fullPath);
                if ($content !== false) {
                    $snippet = substr($content, 0, 2000);
                    $sysExtra = "\nFile: " . $normalized . "\n" . $snippet;
                }
            }
        }
    }

    $sysMsg = 'You are a helpful assistant for testing.' . $sysExtra;
    $payload = [
        'jsonrpc' => '2.0',
        'id' => 'dev_local_test_' . time(),
        'method' => 'tools/call',
        'params' => [
            'name' => 'chat_completion',
            'arguments' => [
                'model' => 'kimi_k2',
                'messages' => [
                    ['role' => 'system', 'content' => $sysMsg],
                    ['role' => 'user', 'content' => $message]
                ]
            ]
        ]
    ];

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => json_encode($payload),
            'timeout' => 15
        ]
    ];

    $ctx = stream_context_create($opts);
    $mcpUrl = 'http://127.0.0.1:9010/mcp';
    $res = @file_get_contents($mcpUrl, false, $ctx);
    if ($res === false) {
        $cmd = 'curl -sS -X POST ' . escapeshellarg($mcpUrl) . ' -H ' . escapeshellarg('Content-Type: application/json') . ' -H ' . escapeshellarg('Accept: application/json') . ' -d ' . escapeshellarg(json_encode($payload));
        $res = shell_exec($cmd);
        if ($res === null || $res === '') {
            http_response_code(502);
            echo json_encode(['success' => false, 'message' => 'MCP request failed']);
            exit;
        }
    }

    $json = json_decode($res, true);
    $outputText = '';
    if (!empty($json['result']['content']) && is_array($json['result']['content'])) {
        foreach ($json['result']['content'] as $c) {
            if (!empty($c['text'])) $outputText .= $c['text'];
            elseif (!empty($c['type']) && $c['type'] === 'text' && !empty($c['text'])) $outputText .= $c['text'];
        }
    }

    if ($outputText === '') {
        header('Content-Type: application/json');
        echo $res;
        exit;
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo $outputText;
    exit;
});

// Circle view (derived from compact template)
req($router, '/user/network-tree/circle-view', function() use ($db) {
    // Dev convenience: if no session user, try to auto-login user 'oliverbob'
    if (empty($_SESSION['user_id'])) {
        try {
            $userId = $db->get('users', 'id', ['username' => 'oliverbob']);
            if ($userId) {
                $_SESSION['user_id'] = (int)$userId;
            }
        } catch (\Throwable $_) {
            // ignore - proceed without login if DB not available
        }
    }

    $viewPath = ROOT_PATH . '/src/Views/user/network-tree/circle-view.php';
    if (file_exists($viewPath)) {
        include $viewPath;
        exit;
    }

    // Fallback: try including compact view as a last resort
    $fallback = ROOT_PATH . '/src/Views/user/network-tree/compact-view.php';
    if (file_exists($fallback)) {
        include $fallback;
        exit;
    }
});

// API endpoint for user network tree data
req($router, '/api/user/network-tree', function() use ($db) {
    if (empty($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    $userId = $_SESSION['user_id'];
    $depth = isset($_GET['depth']) ? intval($_GET['depth']) : 3;
    // Clamp depth between 1 and 9
    $depth = max(1, min(9, $depth));
    $userModel = new \Ginto\Models\User();
    $tree = $userModel->getNetworkTree($userId, $depth);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $tree]);
});

// API endpoint for user network search
req($router, '/api/user/network-search', function() use ($db) {
    $controller = new \Ginto\Controllers\ApiController($db);
    $controller->searchUsers();
});

// Restore admin routes for quick access / demonstration
req($router, '/admin', function() use ($db) {
    // Prefer AdminController if available
    if (class_exists('Ginto\\Controllers\\AdminController')) {
        try {
            $ctrl = new \Ginto\Controllers\AdminController($db);
            if (method_exists($ctrl, 'dashboard')) {
                return $ctrl->dashboard();
            }
        } catch (\Throwable $e) {
            // fallthrough to include view file
        }
    }

    $view = ROOT_PATH . '/src/Views/admin/dashboard.php';
    if (file_exists($view)) {
        include $view;
        exit;
    }

    header('Location: /');
    exit;
});

req($router, '/admin/network-tree', function() use ($db) {
    // Admin network tree view - prefer controller, otherwise include view
    if (class_exists('Ginto\\Controllers\\AdminController')) {
        try {
            $ctrl = new \Ginto\Controllers\AdminController($db);
            if (method_exists($ctrl, 'networkTree')) {
                return $ctrl->networkTree();
            }
        } catch (\Throwable $e) {
            // ignore and fallback
        }
    }

    $viewPath = ROOT_PATH . '/src/Views/admin/network-tree.php';
    if (file_exists($viewPath)) {
        include $viewPath;
        exit;
    }

    // Fallback: try legacy view helper
    try {
        \Ginto\Core\View::view('admin/network-tree', ['title' => 'Admin Network Tree']);
        exit;
    } catch (\Throwable $_) {
        http_response_code(404);
        echo 'Admin network tree not found';
        exit;
    }
});

// API endpoint for admin to fetch network tree data (used by admin/network-tree view)
req($router, '/admin/network-tree/data', function() use ($db) {
    header('Content-Type: application/json');
    $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
    $depth = isset($_GET['depth']) ? intval($_GET['depth']) : 3;
    $depth = max(1, min(9, $depth));

    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'No user_id provided']);
        exit;
    }

    try {
        $userModel = new \Ginto\Models\User();
        if (method_exists($userModel, 'getNetworkTree')) {
            $tree = $userModel->getNetworkTree($userId, $depth);
            echo json_encode(['success' => true, 'data' => $tree]);
            exit;
        }

        // Fallback: minimal response using direct referrals
        $tree = $userModel->find($userId);
        if (!$tree) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        $tree['children'] = $userModel->getDirectReferrals($userId);
        echo json_encode(['success' => true, 'data' => $tree]);
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
});

// API endpoint to fetch a single user's profile (used by tooltips)
req($router, '/api/user/profile', function() use ($db) {
    $controller = new \Ginto\Controllers\ApiController($db);
    $controller->userProfile();
});

// Quick debug route: list users with phone and country
req($router, '/test-phone', function() use ($db) {
    $users = $db->select('users', ['id', 'username', 'phone', 'country']);
    echo '<pre>' . print_r($users, true) . '</pre>';
});

// API endpoint to resolve user ID by username
req($router, '/api/user-id', function() use ($db) {
    header('Content-Type: application/json');
    $username = $_GET['username'] ?? '';
    if (!$username) {
        echo json_encode(['error' => 'No username provided']);
        exit;
    }
    $userId = $db->get('users', 'id', ['username' => $username]);
    if ($userId) {
        echo json_encode(['id' => $userId]);
    } else {
        echo json_encode(['id' => null, 'error' => 'User not found']);
    }
    exit;
});

// Helper function to get all downline IDs
function getAllDownlineIds($db, $userId, $visited = []) {
    $userModel = new \Ginto\Models\User();
    $ids = [];
    $direct = $userModel->getDirectReferrals($userId);
    foreach ($direct as $ref) {
        if (!in_array($ref['id'], $visited)) {
            $ids[] = $ref['id'];
            $visited[] = $ref['id'];
            $ids = array_merge($ids, getAllDownlineIds($db, $ref['id'], $visited));
        }
    }
    return $ids;
}

// CMS Admin Routes (new functionality)
$router->filter('csrf', ['Ginto\\Middleware\\CsrfMiddleware', 'handle']);

$router->group(['prefix' => '/admin', 'before' => 'csrf'], function($router) {
    $router->get('/', 'AdminController@dashboard');
    $router->get('/cms', 'AdminController@cmsDashboard');

    // Provide a compact $req helper for controller-style routes so included files can register handlers
    // Usage: $req('/path', 'Controller@method', $methods = null)
    // - $methods can be array(['GET','POST','PUT']) or null to infer by method name
    // Note: don't capture $db in the closure's `use` list — some execution
    // environments may not have `$db` set yet which causes a PHP notice at
    // closure creation time. Resolve `$db` at runtime from $GLOBALS or via
    // Database::getInstance() to be robust.
    $req = function(string $path, string $target, $methods = null) use ($router) {
        $handler = function() use ($target) {
            // Ensure a DB instance is available to controller handlers. Some include paths
            // or earlier refactors caused intermittent "undefined $db" warnings — use a
            // safe fallback to Database::getInstance() when possible.
            // Resolve DB instance at runtime — prefer a global $db if present,
            // otherwise attempt to get a singleton Database instance.
            $dbInstance = ($GLOBALS['db'] ?? null) ?: (class_exists('\Ginto\Core\Database') ? \Ginto\Core\Database::getInstance() : null);
            $args = func_get_args();
            if (!is_string($target) || strpos($target, '@') === false) return null;
            list($controller, $method) = explode('@', $target, 2);
            $class = "\\Ginto\\Controllers\\{$controller}";
            try {
                if (!class_exists($class)) return null;
                // Prefer passing a database instance to the controller if available. Use
                // the prepared fallback ($dbInstance) so controllers are stable irrespective
                // of how/where the route file was included.
                if ($dbInstance !== null) {
                    $ctrl = new $class($dbInstance);
                } else {
                    // last-resort: try parameterless constructor
                    $ctrl = new $class();
                }
                $verb = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
                if ($verb !== 'GET') $args[] = $_REQUEST ?: $_POST;
                return call_user_func_array([$ctrl, $method], $args);
            } catch (\Throwable $e) {
                error_log("Route handler failed for {$target}: " . $e->getMessage());
                return null;
            }
        };

                if (is_array($methods)) {
            foreach ($methods as $m) {
                $m = strtoupper($m);
                if ($m === 'GET') $router->get($path, $handler);
                elseif ($m === 'POST') $router->post($path, $handler);
                elseif (method_exists($router, strtolower($m))) $router->{strtolower($m)}($path, $handler);
                else $router->get($path, $handler);
            }
            return;
        }

        $write = ['store','update','save','delete','destroy','put','patch'];
        $delete = ['delete','destroy'];
        $read = ['index','show','edit','create','new'];
        $methodName = strtolower(explode('@', $target)[1] ?? '');

            if (in_array($methodName, $write)) {
                // Router only supports GET/POST; register writes as POST for compatibility
                $router->post($path, $handler);
        } elseif (in_array($methodName, $read)) {
            $router->get($path, $handler);
        } else {
                // Register only GET and POST for unknown handlers (Router does not support put/patch)
            $router->get($path, $handler);
            $router->post($path, $handler);
            if (method_exists($router, 'delete')) $router->delete($path, $handler);
        }
    };

    // Include the CMS admin routes defined in src/Routes/admin_controller_routes.php
    // These routes are defined without the '/admin' prefix so they get mounted under the group.
    $adminRoutesFile = ROOT_PATH . '/src/Routes/admin_controller_routes.php';
    if (file_exists($adminRoutesFile)) {
        require $adminRoutesFile;
    }
});

// 404 Handler
req($router, '/404', function() {
    http_response_code(404);
    echo "<h1>404 Not Found</h1><p>The requested page could not be found.</p>";
});


// Recursive function to get downlines up to maxLevel
function getDownlinesRecursive($userModel, $userId, $level, $maxLevel) {
    $downlines = $userModel->getDirectReferrals($userId);
    foreach ($downlines as &$downline) {
        if ($level < $maxLevel && $downline['id']) {
            $downline['children'] = getDownlinesRecursive($userModel, $downline['id'], $level + 1, $maxLevel);
        } else {
            $downline['children'] = [];
        }
    }
    return $downlines;
}

// API endpoint for direct downlines (for lazy loading)
req($router, '/api/user/direct-downlines', function() use ($db) {
    if (empty($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (int)$_SESSION['user_id'];
    $maxLevel = isset($_GET['max_level']) ? (int)$_GET['max_level'] : 3;
    // Clamp requested max level to 1..9 to avoid excessively deep recursion
    $maxLevel = max(1, min(9, $maxLevel));
    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid user ID']);
        exit;
    }
    // Use User::getNetworkTree which already includes computed fields like totalCommissions
    try {
        $userModel = new \Ginto\Models\User();
        $tree = $userModel->getNetworkTree($userId, $maxLevel);
        $downlines = $tree['children'] ?? [];
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'downlines' => $downlines]);
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
});

// API endpoint for commissions: delegate to CommissionsController (keeps logic centralized)
req($router, '/api/user/commissions', function() use ($db) {
    // allow `user` (username) param or `userId` (camelCase) for backward compatibility; map to user_id
    if (!empty($_GET['user']) && empty($_GET['user_id'])) {
        $username = trim($_GET['user']);
        $uid = $db->get('users', 'id', ['username' => $username]);
        if ($uid) $_GET['user_id'] = (int)$uid;
    }
    if (!empty($_GET['userId']) && empty($_GET['user_id'])) {
        $_GET['user_id'] = intval($_GET['userId']);
    }

    $ctrl = new \Ginto\Controllers\CommissionsController();
    $ctrl->apiIndex();
});

// Backwards-compatible alias used by older frontend code: /network/earnings
req($router, '/network/earnings', function() use ($db) {
    // Backwards compat: accept `user` (username) or `userId` and map to `user_id`
    if (!empty($_GET['user']) && empty($_GET['user_id'])) {
        $username = trim($_GET['user']);
        $uid = $db->get('users', 'id', ['username' => $username]);
        if ($uid) $_GET['user_id'] = (int)$uid;
    }
    if (!empty($_GET['userId']) && empty($_GET['user_id'])) {
        $_GET['user_id'] = intval($_GET['userId']);
    }
    header('Content-Type: application/json');
    $ctrl = new \Ginto\Controllers\CommissionsController();
    $ctrl->apiIndex();
});

req($router, '/api/data', function() use ($db) {
    header('Content-Type: application/json');
    $controller = new \Ginto\Controllers\DataController();
    $controller->index();
});

// Serve uploaded mall products stored on the server
req($router, '/api/mall/products', function() {
    header('Content-Type: application/json');
    // If DB is available, serve from DB; otherwise use JSON file
    $products = [];
    try {
        if (class_exists('Ginto\\Core\\Database') && \Ginto\Core\Database::isInstalled()) {
            require_once ROOT_PATH . '/src/Models/Product.php';
            $pm = new \Ginto\Models\Product();
            // Basic support for query params: category, search, sort, limit, offset
            $opts = [];
            if (isset($_GET['category'])) $opts['category'] = $_GET['category'];
            if (isset($_GET['search'])) $opts['search'] = $_GET['search'];
            if (isset($_GET['sort'])) $opts['sort'] = $_GET['sort'];
            if (isset($_GET['limit'])) $opts['limit'] = (int)$_GET['limit'];
            if (isset($_GET['offset'])) $opts['offset'] = (int)$_GET['offset'];
            $products = $pm->list($opts);
        } else {
            $storeFile = STORAGE_PATH . '/mall_products.json';
            if (file_exists($storeFile)) {
                $json = @file_get_contents($storeFile);
                $products = json_decode($json, true) ?: [];
            }
        }

        // Add currency formatted price using helper
        require_once ROOT_PATH . '/src/Helpers/CurrencyHelper.php';
        $chClass = '\\Ginto\\Helpers\\CurrencyHelper';
        $detectedCurrency = $chClass::detectCurrency();
        foreach ($products as &$p) {
            $currency = $p['price_currency'] ?? $p['currency'] ?? $detectedCurrency;
            $p['price_currency'] = $currency;
            $p['formatted_price'] = $chClass::formatAmount($p['price_amount'] ?? ($p['price'] ?? 0), $currency);
        }
        unset($p);
    } catch (\Throwable $e) {
        // On any failure return an empty list and an error message
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }

    echo json_encode(['success' => true, 'products' => $products]);
    exit;
});

// Marketplace route
req($router, '/marketplace', function() {
    $controller = new \Ginto\Controllers\MallController();
    $controller->marketplace();
});

// Upload product endpoint (AJAX)
req($router, '/mall/upload', function() {
    // Only accept POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    header('Content-Type: application/json');

    // Require authentication
    if (empty($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }

    // CSRF validation (expects `csrf_token` in POST)
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!function_exists('validateCsrfToken') || !validateCsrfToken($token)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    // Basic validation
    $title = trim($_POST['title'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $category = trim($_POST['category'] ?? 'user');
    $desc = trim($_POST['description'] ?? '');

    if ($title === '' || $price <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Title and valid price are required']);
        exit;
    }

    // Handle file upload
    $uploadPath = ROOT_PATH . '/public/assets/uploads';
    if (!is_dir($uploadPath)) {
        @mkdir($uploadPath, 0755, true);
    }

    $imageUrl = '';
    if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $ext = strtolower($ext);
        $allowed = ['jpg','jpeg','png','gif','webp','svg'];
        if (!in_array($ext, $allowed)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unsupported image type']);
            exit;
        }

        $filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest = $uploadPath . '/' . $filename;
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
            exit;
        }
        // Public URL
        $imageUrl = '/assets/uploads/' . $filename;
    }

    // Determine currency for this upload (detected country or site default)
    try {
        require_once ROOT_PATH . '/src/Helpers/CurrencyHelper.php';
        $ch = '\\Ginto\\Helpers\\CurrencyHelper';
        $currency = $ch::detectCurrency();
    } catch (\Throwable $e) {
        $currency = getenv('APP_DEFAULT_CURRENCY') ?: 'USD';
    }

    // Compose product object
    $product = [
        'id' => intval(time()),
        'title' => htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'price' => round($price, 2),
        'currency' => $currency,
        'cat' => $category,
        'rating' => 0,
        'img' => $imageUrl ?: '/assets/images/placeholder_ceramic.svg',
        'badge' => '',
        'desc' => htmlspecialchars($desc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'owner_id' => (int)$_SESSION['user_id']
    ];

    // Attempt to persist to the database if available; otherwise fall back to JSON file
    $saved = null;
    try {
        // Use Database::isInstalled() to decide
        if (class_exists('Ginto\\Core\\Database') && \Ginto\Core\Database::isInstalled()) {
            // Use Product model
            require_once ROOT_PATH . '/src/Models/Product.php';
            $prodModel = new \Ginto\Models\Product();
            $dbProduct = $prodModel->create([
                'owner_id' => $product['owner_id'],
                'title' => $product['title'],
                'description' => $product['desc'],
                'price' => $product['price'],
                'currency' => $product['currency'],
                'category' => $product['cat'],
                'image_path' => $product['img'],
                'badge' => $product['badge'],
                'rating' => $product['rating'] ?? 0,
                'status' => 'published'
            ]);
            if ($dbProduct) {
                $saved = $dbProduct;
            }
        }
    } catch (\Throwable $e) {
        // DB not available or insertion failed; fall back to JSON
        $saved = null;
    }

    if (!$saved) {
        // Persist to storage/mall_products.json (append)
        $storeFile = STORAGE_PATH . '/mall_products.json';
        $existing = [];
        if (file_exists($storeFile)) {
            $json = @file_get_contents($storeFile);
            $existing = json_decode($json, true) ?: [];
        }
        $existing[] = $product;
        if (false === @file_put_contents($storeFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save product']);
            exit;
        }
        echo json_encode(['success' => true, 'product' => $product]);
        exit;
    }

    echo json_encode(['success' => true, 'product' => $saved]);
    exit;
});

// Note: the `/dev/chat` repo-summary helper has been removed to avoid
// accidental repository dumps for generic prompts. Use a dedicated UI
// action if you want an explicit repository description.

// Start routing
$router->dispatch($_SERVER['REQUEST_URI']);
// Local dev helper: lightweight endpoint to POST a test chat message and proxy to the MCP
// Accessible only from localhost to avoid exposing an open proxy.
if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] === '/admin/pages/editor/chat_local_test') {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (!in_array($remote, ['127.0.0.1', '::1'])) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];
    $message = trim($data['message'] ?? "Hello from local test");

    $payload = [
        'jsonrpc' => '2.0',
        'id' => 'local_test_' . time(),
        'method' => 'tools/call',
        'params' => [
            'name' => 'chat_completion',
            'arguments' => [
                'model' => 'kimi_k2',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful assistant for testing.'],
                    ['role' => 'user', 'content' => $message]
                ]
            ]
        ]
    ];

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => json_encode($payload),
            'timeout' => 10
        ]
    ];

    $ctx = stream_context_create($opts);
    $mcpUrl = 'http://127.0.0.1:9010/mcp';
    $res = @file_get_contents($mcpUrl, false, $ctx);
    if ($res === false) {
        http_response_code(502);
        echo 'MCP request failed';
        exit;
    }

    $json = json_decode($res, true);
    $outputText = '';
    if (!empty($json['result']['content']) && is_array($json['result']['content'])) {
        // Prefer the first content block's text field
        foreach ($json['result']['content'] as $c) {
            if (!empty($c['text'])) {
                $outputText .= $c['text'];
            } elseif (!empty($c['type']) && $c['type'] === 'text' && !empty($c['text'])) {
                $outputText .= $c['text'];
            } elseif (!empty($c['text'])) {
                $outputText .= $c['text'];
            }
        }
    }

    if ($outputText === '') {
        // Fallback: return raw MCP response
        header('Content-Type: application/json');
        echo $res;
        exit;
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo $outputText;
    exit;
}

