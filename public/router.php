<?php
/**
 * Router for PHP Built-in Server
 * This file routes all requests through index.php for proper handling
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Handle install requests
if (str_contains($uri, '/install/install.php')) {
    $_SERVER['REQUEST_URI'] = $uri;
    require_once __DIR__ . '/index.php';
    return true;
}

// Serve static files directly
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Route everything else through index.php
require_once __DIR__ . '/index.php';