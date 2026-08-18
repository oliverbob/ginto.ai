<?php
/**
 * Ginto CMS - Main Entry Point
 * Unified routing system for legacy user features and new CMS functionality
 */

define('ROOT_PATH', dirname(__DIR__));
// Define STORAGE_PATH for use across the app. Defaults to the parent directory's
// storage folder (e.g. ~/silverqueen.pro/../storage) which matches how some services
// place persistent data outside the repository. Can be overridden in a server
// bootstrap before including `public/index.php`.
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', dirname(ROOT_PATH) . '/storage');
}

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
// Mall image storage — owner+group readable only (no world access)
foreach (['mall', 'mall/images'] as $subdir) {
    $path = STORAGE_PATH . '/' . $subdir;
    if (!is_dir($path)) {
        @mkdir($path, 0750, true);
    }
}

// Check if installation is complete
$installedMarkerExists = file_exists(ROOT_PATH . '/.installed') || file_exists(STORAGE_PATH . '/.installed');
$envFileExists = file_exists(ROOT_PATH . '/.env');
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);

// CORS: Allow *.silverqueen.pro, silverqueen.pro, *.ginto.ai, ginto.ai, and local dev origins to call
// API endpoints cross-origin.  This enables the SQ login form on ginto.ai to fetch() to
// sq.silverqueen.pro, and vice versa.
$_gntl_origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (
    $_gntl_origin
    && (
        preg_match('#^https://[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.silverqueen\.pro$#', $_gntl_origin)
        || $_gntl_origin === 'https://silverqueen.pro'
        || preg_match('#^https://[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.ginto\.ai$#', $_gntl_origin)
        || $_gntl_origin === 'https://ginto.ai'
        || preg_match('#^https?://localhost(?::\d{1,5})?$#', $_gntl_origin)
        || preg_match('#^https?://127\.0\.0\.1(?::\d{1,5})?$#', $_gntl_origin)
    )
) {
    header('Access-Control-Allow-Origin: ' . $_gntl_origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Accept');
        header('Access-Control-Max-Age: 86400');
        http_response_code(204);
        exit;
    }
}
unset($_gntl_origin);

// V2 Install Flow: /live is accessible when .installed is missing (setup mode)
// No auto-redirect - users see /chat first (hook them with UI), click Live manually when ready
if (!$installedMarkerExists) {
    // Handle /live and /live/activate directly when .installed is missing
    if ($path === '/live' || $path === '/live/' || $path === '/live/activate') {
        if (!$envFileExists && $path !== '/live/activate') {
            // No .env - serve minimal bootstrap setup page
            require ROOT_PATH . '/src/Views/live/bootstrap.php';
            exit;
        }
        // Has .env - load autoloader and serve LiveController directly
        require ROOT_PATH . '/vendor/autoload.php';
        $dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
        $dotenv->load();
        
        $db = \Ginto\Core\Database::getInstance();
        $controller = new \Ginto\Controllers\LiveController($db);
        
        if ($path === '/live/activate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->activate();
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->save();
        } else {
            $controller->index();
        }
        exit;
    }
}

// Handle /install routes - LEGACY MODE ONLY
// By default, /install redirects to /chat. Enable legacy installer with:
// - Environment variable: GINTO_LEGACY_INSTALL=1
// - Or file marker: .legacy-install in ROOT_PATH
$legacyInstallEnabled = (getenv('GINTO_LEGACY_INSTALL') === '1') || 
                         file_exists(ROOT_PATH . '/.legacy-install');

if (strpos($path, '/install') === 0) {
    // If legacy mode is not enabled, redirect to /chat
    if (!$legacyInstallEnabled) {
        header('Location: /chat');
        exit;
    }
    
    // Legacy mode enabled - serve the v1 installer
    // Handle install.php
    if ($path === '/install.php' || $path === '/install/install.php') {
        require ROOT_PATH . '/install/install.php';
        exit;
    }
    
    // Serve installer index via index.php (handles guardrails and serves installer.html)
    if ($path === '/install' || $path === '/install/') {
        require ROOT_PATH . '/install/index.php';
        exit;
    }
    
    // Serve other static files from install directory
    $filePath = ROOT_PATH . $path;
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
            'php' => null, // Handle PHP files specially
        ];
        if ($ext === 'php') {
            require $filePath;
            exit;
        }
        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext]);
        }
        // Allow service worker scripts to claim any scope (e.g. /mall/ from /assets/js/).
        if ($ext === 'js' && str_ends_with($path, '-sw.js')) {
            header('Service-Worker-Allowed: /');
        }
        readfile($filePath);
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

// Use storage path outside repo (same as non-docker LXC setup)
// In docker: /var/www/storage/sessions (bind-mounted from host)
// Non-docker: ~/silverqueen.pro/../storage/sessions
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
    if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
        setcookie(session_name(), session_id(), [
            'expires' => time() + $longLifetime,
            'path' => '/',
            'domain' => '',
            'secure' => $cookieSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        // Older PHP: send explicit header with SameSite and HttpOnly
        $cookieHeader = session_name() . '=' . rawurlencode(session_id()) . '; Path=/; Expires=' . gmdate('D, d-M-Y H:i:s T', time() + $longLifetime) . ($cookieSecure ? '; Secure' : '') . '; HttpOnly; SameSite=Lax';
        header('Set-Cookie: ' . $cookieHeader, false);
    }
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
    
    // Logged-in users always get a persistent session token — never expiring,
    // regardless of the $forVisitor hint (e.g. called from shared form helpers).
    if (!empty($_SESSION['user_id'])) {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        unset($_SESSION['csrf_token_expires']); // Strip any stale expiry
        return $_SESSION['csrf_token'];
    }

    // For visitors (not logged in), reuse token if still valid (8-hour window)
    if (!empty($_SESSION['csrf_token']) && !empty($_SESSION['csrf_token_expires'])) {
        if (time() < $_SESSION['csrf_token_expires']) {
            return $_SESSION['csrf_token'];
        }
    }
    // Generate new visitor token
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    $_SESSION['csrf_token_expires'] = time() + 28800; // 8 hours for visitors
    return $token;
}

function validateCsrfToken($token): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    // Normalize inputs
    $provided = is_string($token) ? $token : '';
    $sessionToken = isset($_SESSION['csrf_token']) ? (string)$_SESSION['csrf_token'] : '';

    // Basic match check
    $matches = ($sessionToken !== '' && hash_equals($sessionToken, $provided));
    if (!$matches) {
        // Log truncated values to avoid leaking full tokens in logs
        $remote = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? ''; 
        $pShort = $provided === '' ? '<empty>' : substr($provided, 0, 8) . '...';
        $sShort = $sessionToken === '' ? '<missing>' : substr($sessionToken, 0, 8) . '...';
        error_log("CSRF validation failed: uri={$uri} ip={$remote} provided={$pShort} session={$sShort}");

        // If visitor tokens with expiry exist, remove expired tokens
        if (isset($_SESSION['csrf_token_expires']) && time() > $_SESSION['csrf_token_expires']) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_expires']);
            error_log("CSRF token expired and cleared for session_id=" . session_id());
        }

        return false;
    }

    // Check expiration for visitor tokens
    if (isset($_SESSION['csrf_token_expires'])) {
        if (time() > $_SESSION['csrf_token_expires']) {
            // Token expired - clear it
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_expires']);
            error_log("CSRF token expired for session_id=" . session_id());
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
$router->req('/user/commissions', function() use ($db) {
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
$router->req('/user/network-tree/compact-view', function() use ($db) {
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
$router->req('/webhook', function() use ($db) {
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

$router->req('/webhook/status', function() use ($db) {
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
$router->req('/auth/github/callback', function() use ($db) {
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
$router->req('/webhook/github', function() {
    try {
        $logDir = STORAGE_PATH . '/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

        $raw = file_get_contents('php://input');
        $entry = [
            'time' => date('c'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'headers' => [
                'x-hub-signature-256' => $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? null,
                'x-github-event' => $_SERVER['HTTP_X_GITHUB_EVENT'] ?? null,
            ],
            'payload' => $raw ? json_decode($raw, true) : null,
        ];
        file_put_contents($logDir . '/github_webhook.log', json_encode($entry) . PHP_EOL, FILE_APPEND | LOCK_EX);

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

// Products API for marketplace infinite scroll
$router->req('/api/mall/products', function() {
    $controller = new \Ginto\Controllers\MallController();
    $controller->apiProducts();
    exit;
});

// Dev helper: return a JSON containing the current session CSRF token (only allowed from localhost or when not in production)
$router->req('/dev/csrf', function() use ($db) {
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
    
    // Include sandbox info for visitors (if they have one)
    if ($isVisitor) {
        $sandboxId = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxId($db, $_SESSION);
        if ($sandboxId) {
            $response['sandbox'] = [
                'id' => $sandboxId,
                'enabled' => true
            ];
        }
    }
    
    echo json_encode($response);
    exit;
});

// Dev helper: toggle CSRF bypass for debugging (localhost only, non-production only)
// CSRF bypass route removed - CSRF validation is always enforced

// Dev helper: quickly log in as the 'oliverbob' user (localhost only)
$router->req('/dev/login/oliverbob', function() use ($db) {
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
$router->req('/dev/login/admin', function() use ($db) {
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
$router->req('/admin/pages/editor/chat_local_test', function() use ($db) {
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
$router->req('/dev/editor/chat_local_test', function() use ($db) {
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
$router->req('/user/network-tree/circle-view', function() use ($db) {
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
$router->req('/api/user/network-tree', function() use ($db) {
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
$router->req('/api/user/network-search', function() use ($db) {
    $controller = new \Ginto\Controllers\ApiController($db);
    $controller->searchUsers();
});

// Restore admin routes for quick access / demonstration
$router->req('/admin', function() use ($db) {
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

$router->req('/admin/network-tree', function() use ($db) {
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
$router->req('/admin/network-tree/data', function() use ($db) {
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
$router->req('/api/user/profile', function() use ($db) {
    $controller = new \Ginto\Controllers\ApiController($db);
    $controller->userProfile();
});

// Quick debug route: list users with phone and country
$router->req('/test-phone', function() use ($db) {
    $users = $db->select('users', ['id', 'username', 'phone', 'country']);
    echo '<pre>' . print_r($users, true) . '</pre>';
});

// API endpoint to resolve user ID by username
$router->req('/api/user-id', function() use ($db) {
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

// API endpoint to fetch sandbox job status by job_id
$router->req('/api/sandbox/job-status', function() use ($db) {
    header('Content-Type: application/json');
    $jobId = $_GET['job_id'] ?? '';
    if (!$jobId) {
        echo json_encode(['success' => false, 'error' => 'No job_id provided']);
        exit;
    }

    try {
        $res = \Ginto\Helpers\UnifiedSandbox::getJobStatus($jobId);
        echo json_encode($res);
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
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
$router->req('/404', function() {
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
$router->req('/api/user/direct-downlines', function() use ($db) {
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
$router->req('/api/user/commissions', function() use ($db) {
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
$router->req('/network/earnings', function() use ($db) {
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

$router->req('/api/data', function() use ($db) {
    header('Content-Type: application/json');
    $controller = new \Ginto\Controllers\DataController();
    $controller->index();
});

// Unified API: delegate to MallController for DB-backed pagination + search.
$router->req('/api/mall/products', function() {
    $controller = new \Ginto\Controllers\MallController();
    $controller->apiProducts();
    exit;
});

// Marketplace route
$router->req('/marketplace', function() {
    $controller = new \Ginto\Controllers\MallController();
    $controller->marketplace();
});

// Admin-only mail test endpoint
$router->req('/mail', function() {
    if (!defined('IS_ADMIN') || !IS_ADMIN) {
        http_response_code(403);
        echo '<h1>403 Forbidden</h1><p>Admin access only.</p>';
        return;
    }

    $csrfToken = generateCsrfToken();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submittedToken = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($submittedToken)) {
            echo '<p style="color:red;">Invalid CSRF token.</p>';
        } else {
            $to = 'aihqcorp@gmail.com';
            $subject = trim((string)($_POST['subject'] ?? 'Ginto Mail Test')) ?: 'Ginto Mail Test';
            $body = trim((string)($_POST['body'] ?? 'This is a test email from Ginto.'));
            $from = 'no-reply@silverqueen.pro';

            // Ensure from is no-reply regardless of config
            $_ENV['MAIL_FROM'] = $from;
            putenv('MAIL_FROM=' . $from);

            // Try direct PHP mail() to avoid live-domain guard in MailHelper for tests.
            $from = 'no-reply@silverqueen.pro';
            $headers = implode("\r\n", [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: Ginto <' . $from . '>',
                'Reply-To: ' . $from,
                'X-Mailer: PHP/' . phpversion(),
            ]);
            $htmlBody = '<html><body>' . nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')) . '</body></html>';
            $result = @mail($to, $subject, $htmlBody, $headers, '-f' . $from);
            if ($result) {
                echo '<p style="color:green;">Email sent to ' . htmlspecialchars($to) . ' via PHP mail().</p>';
            } else {
                echo '<p style="color:red;">Failed to send email via PHP mail(). Check server mail logs.</p>';
            }
        }
    }

    echo '<h1>Admin Mail Test</h1>';
    echo '<form method="POST" action="/mail">';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">';
    echo '<label>Subject:<br><input type="text" name="subject" value="Ginto Mail Test" style="width:100%;max-width:500px;"></label><br><br>';
    echo '<label>Body:<br><textarea name="body" rows="6" style="width:100%;max-width:500px;">This is a test email from Ginto.</textarea></label><br><br>';
    echo '<button type="submit" style="padding:10px 14px;">Send Test Email</button>';
    echo '</form>';

    // Log inspector section for quick debugging
    $logFile = '';
    if (defined('STORAGE_PATH') && STORAGE_PATH) {
        $logFile = rtrim(STORAGE_PATH, '/\\') . '/logs/ginto.log';
    } else {
        $logFile = __DIR__ . '/../storage/logs/ginto.log';
    }

    echo '<h2 style="margin-top:24px;">Recent PHP app log entries</h2>';
    if (is_readable($logFile)) {
        $lines = 30;
        $content = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($content === false) {
            echo '<p style="color:red;">Unable to read log file.</p>';
        } else {
            $tail = array_slice($content, -$lines);
            echo '<pre style="background:#111;color:#fff;padding:10px;border-radius:6px;max-height:300px;overflow:auto;">' . htmlspecialchars(implode("\n", $tail), ENT_QUOTES, 'UTF-8') . '</pre>';
        }
    } else {
        echo '<p style="color:orange;">Log file not found or not readable: ' . htmlspecialchars($logFile, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    // Include postfix mail delivery logs for debugging
    $possibleMailLogs = ['/var/log/mail.log', '/var/log/maillog', '/var/log/mail/mail.log'];
    $mailLogFile = null;
    foreach ($possibleMailLogs as $path) {
        if (file_exists($path)) {
            $mailLogFile = $path;
            break;
        }
    }

    echo '<h2 style="margin-top:24px;">Recent Postfix mail log entries</h2>';
    if ($mailLogFile !== null) {
        if (is_readable($mailLogFile)) {
            $lines = 40;
            $mlog = file($mailLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($mlog === false) {
                echo '<p style="color:red;">Unable to read postfix mail log file: ' . htmlspecialchars($mailLogFile, ENT_QUOTES, 'UTF-8') . '</p>';
            } else {
                $tail = array_slice($mlog, -$lines);
                echo '<pre style="background:#111;color:#fff;padding:10px;border-radius:6px;max-height:300px;overflow:auto;">' . htmlspecialchars(implode("\n", $tail), ENT_QUOTES, 'UTF-8') . '</pre>';
            }
        } else {
            echo '<p style="color:orange;">Postfix mail log exists but is not readable by PHP user (www-data). Try: <code>sudo usermod -aG adm www-data</code> and restart php-fpm. Path: ' . htmlspecialchars($mailLogFile, ENT_QUOTES, 'UTF-8') . '</p>';
            echo '<pre style="background:#fff;color:#000;padding:10px;border:1px solid #ccc;">' . htmlspecialchars(shell_exec('ls -l ' . escapeshellarg($mailLogFile)), ENT_QUOTES, 'UTF-8') . '</pre>';
        }
    } else {
        echo '<p style="color:orange;">Postfix mail log file not found (checked /var/log/mail.log, /var/log/maillog, /var/log/mail/mail.log).</p>';
    }

    return;
});

// /mall is an alias for /marketplace
$router->req('/mall', function() {
    header('Location: /marketplace', true, 301); exit;
});

// Upload product endpoint (AJAX)
$router->req('/mall/upload', function() {
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

// Privacy Policy (Google Play / public)
$router->req('/privacy', function() {
    \Ginto\Core\View::view('privacy', ['title' => 'Privacy Policy | Ginto']);
});

// Terms of Service
$router->req('/terms', function() {
    \Ginto\Core\View::view('terms', ['title' => 'Terms of Service | Ginto']);
});

// Delete account — confirmation page (GET) and execution (POST)
$router->req('/account/delete', function() use ($db) {
    $ctrl = new \Ginto\Controllers\UserController($db);
    $ctrl->deleteAccount();
});
$router->req('/account/delete/confirm', function() use ($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: /account/delete');
        exit;
    }
    $ctrl = new \Ginto\Controllers\UserController($db);
    $ctrl->deleteAccountConfirm();
});

// SmartFi revenue calculator
$router->req('/smartfi', function() use ($db) {
    $user = [];
    if (!empty($_SESSION['user_id'])) {
        $u = $db->get('users', ['id', 'public_id', 'username'], ['id' => (int)$_SESSION['user_id']]);
        if (is_array($u)) {
            $user = $u;
            $_SESSION['user_public_id'] = $u['public_id'] ?? $u['id'];
        }
    }
    \Ginto\Core\View::view('smartfi', ['title' => 'SmartFi | Ginto']);
});

// User settings (GET) and update (POST)
$router->req('/user/settings', function() use ($db) {
    $ctrl = new \Ginto\Controllers\UserController($db);
    $ctrl->settings();
});
$router->req('/user/settings/update', function() use ($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: /user/settings');
        exit;
    }
    $ctrl = new \Ginto\Controllers\UserController($db);
    $ctrl->settingsUpdate();
});

// Gaming hub — /gaming and /gaming?game=typing
$router->req('/gaming', function() use ($db) {
    $user = [];
    if (isset($_SESSION['user_id'])) {
        $user = $db->get('users', '*', ['id' => (int)$_SESSION['user_id']]) ?: [];
    }
    \Ginto\Core\View::view('gaming', ['title' => 'Ginto Gaming', 'user' => $user]);
});

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

