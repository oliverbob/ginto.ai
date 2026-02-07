<?php
// src/Core/UrlHelper.php
// Helper to define BASE_URL for use in views, with proxy and scheme detection

// Detect scheme
$scheme = 'http';
if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    $scheme = 'https';
}

// Detect host (proxy-aware)
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
}

// Detect if local or remote
$isLocal = (
    strpos($host, 'localhost') !== false ||
    strpos($host, '127.0.0.1') !== false ||
    preg_match('/^192\.168\./', $host) ||
    preg_match('/^10\./', $host) ||
    preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host)
);

// If proxied from local, force remote domain
if ($isLocal && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    define('BASE_URL', 'https://ginto.ai');
} else {
    define('BASE_URL', $scheme . '://' . $host);
}
