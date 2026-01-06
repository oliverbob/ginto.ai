<?php
/**
 * Sandbox Router for clients/ directory
 * This file enforces security restrictions when serving sandbox files directly.
 * 
 * Usage: php -S 0.0.0.0:8080 -t clients clients/router.php
 */

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);

// Block access to router.php itself
if ($path === '/router.php' || basename($path) === 'router.php') {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

// Get the actual file path
$filePath = __DIR__ . $path;

// If it's a directory, look for index files
if (is_dir($filePath)) {
    if (file_exists($filePath . '/index.php')) {
        $filePath = $filePath . '/index.php';
    } elseif (file_exists($filePath . '/index.html')) {
        $filePath = $filePath . '/index.html';
    } else {
        http_response_code(403);
        echo 'Directory listing not allowed';
        exit;
    }
}

// File doesn't exist
if (!file_exists($filePath)) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

// Block hidden files and .htaccess
$basename = basename($filePath);
if ($basename[0] === '.' || $basename === '.htaccess') {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

// For static files, let PHP's built-in server handle them
if ($ext !== 'php') {
    return false; // Let built-in server serve static files
}

// =====================
// PHP FILE SECURITY
// =====================

$content = file_get_contents($filePath);

// List of dangerous/blocked functions for sandbox execution
$blockedFunctions = [
    // Info disclosure
    'phpinfo', 'phpversion', 'php_uname', 'php_sapi_name',
    'getmypid', 'getmyuid', 'getmygid', 'get_current_user', 'getmyinode',
    'get_cfg_var', 'get_include_path', 'get_loaded_extensions', 'get_extension_funcs',
    'get_defined_functions', 'get_defined_vars', 'get_defined_constants', 'get_resources',
    
    // Command execution
    'exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open',
    'proc_close', 'proc_get_status', 'proc_nice', 'proc_terminate',
    'pcntl_exec', 'pcntl_fork', 'pcntl_signal', 'pcntl_alarm', 'pcntl_waitpid',
    
    // Code execution / eval
    'eval', 'assert', 'create_function', 'call_user_func', 'call_user_func_array',
    'forward_static_call', 'forward_static_call_array', 'ReflectionFunction',
    
    // File system - read
    'file_get_contents', 'fopen', 'fread', 'fgets', 'fgetc', 'fgetss', 'fgetcsv',
    'readfile', 'file', 'parse_ini_file', 'parse_ini_string',
    'show_source', 'highlight_file',
    
    // File system - write
    'file_put_contents', 'fwrite', 'fputs', 'fputcsv', 'ftruncate', 'flock', 'touch',
    
    // File system - modify
    'unlink', 'rmdir', 'mkdir', 'rename', 'copy', 'move_uploaded_file',
    'chmod', 'chown', 'chgrp', 'symlink', 'link', 'tempnam', 'tmpfile',
    
    // File system - directory
    'opendir', 'readdir', 'scandir', 'glob', 'dir',
    
    // Database
    'mysqli_connect', 'mysqli_real_connect', 'mysqli_init',
    'mysql_connect', 'mysql_pconnect',
    'pg_connect', 'pg_pconnect',
    'sqlite_open', 'sqlite_popen',
    'odbc_connect', 'oci_connect', 'oci_pconnect',
    
    // Network
    'curl_init', 'curl_exec', 'curl_multi_exec', 'curl_multi_init',
    'fsockopen', 'pfsockopen', 'stream_socket_client', 'stream_socket_server',
    'socket_create', 'socket_connect', 'socket_bind', 'socket_listen',
    'ftp_connect', 'ftp_ssl_connect', 'ssh2_connect', 'ssh2_exec',
    
    // Email
    'mail', 'imap_open', 'imap_mail',
    
    // Environment
    'putenv', 'getenv', 'ini_set', 'ini_alter', 'ini_restore', 'ini_get', 'ini_get_all',
    'set_include_path', 'restore_include_path', 'dl',
    'apache_setenv', 'apache_getenv', 'apache_child_terminate',
    
    // Session/Headers
    'session_start', 'session_destroy', 'session_regenerate_id', 'session_id', 'session_name',
    'setcookie', 'setrawcookie', 'header', 'header_remove', 'headers_sent', 'headers_list',
    
    // Output control
    'ob_start', 'ob_flush', 'ob_end_flush', 'ob_get_contents',
    
    // Introspection
    'get_declared_classes', 'class_exists', 'interface_exists', 'function_exists',
    'defined', 'constant', 'get_class_methods', 'get_parent_class',
    'is_subclass_of', 'class_implements', 'class_parents',
    'spl_autoload_register', 'spl_autoload_functions', 'class_alias',
    
    // Misc dangerous
    'escapeshellarg', 'escapeshellcmd', 'preg_replace_callback',
    'register_shutdown_function', 'register_tick_function',
    'set_error_handler', 'set_exception_handler',
    'debug_backtrace', 'debug_print_backtrace',
    'serialize', 'unserialize', 'extract', 'parse_str',
];

// Blocked classes
$blockedClasses = [
    'PDO', 'mysqli', 'SQLite3', 'MongoDB', 'Redis', 'Memcache', 'Memcached',
    'ReflectionClass', 'ReflectionMethod', 'ReflectionFunction', 'ReflectionProperty',
];

// Blocked superglobals
$blockedSuperglobals = [
    '$_SESSION', '$_SERVER', '$_ENV', '$_COOKIE',
    '$_GET', '$_POST', '$_REQUEST', '$_FILES', '$GLOBALS',
];

// Build patterns
$funcPattern = '/\b(' . implode('|', array_map('preg_quote', $blockedFunctions)) . ')\s*\(/i';
$classPattern = '/\bnew\s+\\\\?(' . implode('|', array_map(function($c) {
    return preg_quote($c, '/');
}, $blockedClasses)) . ')/i';
$superglobalPattern = '/(' . implode('|', array_map(function($s) {
    return preg_quote($s, '/');
}, $blockedSuperglobals)) . ')\b/';

// Check for blocked functions
if (preg_match($funcPattern, $content, $matches)) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Security Error</title></head><body>';
    echo '<h1>🔒 Sandbox Security Restriction</h1>';
    echo '<p>The function <code>' . htmlspecialchars($matches[1]) . '()</code> is not allowed in sandbox mode.</p>';
    echo '<p>For security reasons, certain PHP functions are disabled in user sandboxes.</p>';
    echo '</body></html>';
    exit;
}

// Check for blocked classes
if (preg_match($classPattern, $content, $matches)) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Security Error</title></head><body>';
    echo '<h1>🔒 Sandbox Security Restriction</h1>';
    echo '<p>The class <code>' . htmlspecialchars($matches[1]) . '</code> cannot be instantiated in sandbox mode.</p>';
    echo '<p>For security reasons, database connections and certain classes are disabled.</p>';
    echo '</body></html>';
    exit;
}

// Check for blocked superglobals
if (preg_match($superglobalPattern, $content, $matches)) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Security Error</title></head><body>';
    echo '<h1>🔒 Sandbox Security Restriction</h1>';
    echo '<p>Access to <code>' . htmlspecialchars($matches[1]) . '</code> is not allowed in sandbox mode.</p>';
    echo '<p>For security reasons, superglobal variables are protected in user sandboxes.</p>';
    echo '</body></html>';
    exit;
}

// Execute the PHP file in a clean environment
$_SESSION = [];
$_ENV = [];
$_COOKIE = [];
$_SERVER = [
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'REQUEST_URI' => $requestUri,
    'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? '',
    'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'localhost',
];

include $filePath;
