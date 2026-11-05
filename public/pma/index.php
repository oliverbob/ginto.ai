<?php
// Lightweight bootstrap for phpMyAdmin installed via Composer into vendor/phpmyadmin/phpmyadmin
// Maps the path /pma to the phpMyAdmin package. This bootstrap keeps logic
// minimal and avoids starting sessions unless needed to prevent session-name
// mismatches with phpMyAdmin.

$vendorPma = __DIR__ . '/../../vendor/phpmyadmin/phpmyadmin';
if (!is_dir($vendorPma)) {
    http_response_code(503);
    echo "phpMyAdmin not installed.\n";
    echo "To install: from project root run:\n";
    echo "  composer require phpmyadmin/phpmyadmin --prefer-dist\n";
    exit;
}

// Ensure trailing slash so phpMyAdmin's relative asset paths resolve correctly.
$reqUri = $_SERVER['REQUEST_URI'] ?? '';
if ($reqUri === '/pma') {
    header('Location: /pma/');
    exit;
}

// Load project .env (if available) so PMA_HTTP_USER/PMA_HTTP_PASS can be enforced
// without requiring system environment variables.
$projectRoot = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR;
$composerAutoload = $projectRoot . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_readable($composerAutoload)) {
    require_once $composerAutoload;
    if (class_exists(\Dotenv\Dotenv::class)) {
        try {
            $dotenv = \Dotenv\Dotenv::createImmutable($projectRoot);
            $dotenv->safeLoad();
        } catch (\Throwable $e) {
            // ignore failures to load .env
        }
    }
}

// Ensure a writable session directory inside the project and configure PHP to use it.
$sessionDir = $projectRoot . 'storage' . DIRECTORY_SEPARATOR . 'sessions' . DIRECTORY_SEPARATOR;
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0755, true);
}
@chmod($sessionDir, 0755);
ini_set('session.save_path', $sessionDir);
// Make sessions long-lived so users stay logged in until they explicitly logout.
// Set to one year (seconds) — change as needed.
ini_set('session.gc_maxlifetime', 31536000);
// Make session cookie persistent (survive browser restarts) for same duration.
ini_set('session.cookie_lifetime', 31536000);
// Use a site-wide cookie path so the session cookie is sent for / and /pma.
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', 1);
// If behind HTTPS, prefer secure cookies (best-effort detection)
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
}

// Access restriction: optional HTTP Basic Auth using PMA_HTTP_USER/PMA_HTTP_PASS
$httpUser = $_ENV['PMA_HTTP_USER'] ?? null;
$httpPass = $_ENV['PMA_HTTP_PASS'] ?? null;
if ($httpUser && $httpPass) {
    if (!isset($_SERVER['PHP_AUTH_USER'])) {
        header('WWW-Authenticate: Basic realm="phpMyAdmin"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Authentication required.';
        exit;
    } else {
        if ($_SERVER['PHP_AUTH_USER'] !== $httpUser || ($_SERVER['PHP_AUTH_PW'] ?? '') !== $httpPass) {
            header('HTTP/1.0 403 Forbidden');
            echo 'Forbidden';
            exit;
        }
    }
}

// Provide a minimal session-backed login when no HTTP auth is configured.
// We do not attempt to set a custom session name — we start the session
// with PHP's current session name so phpMyAdmin (which uses the same name)
// can read the data on the next request.
if (empty($httpUser) || empty($httpPass)) {
    // Handle logout: destroy current session and clear common cookies
    if (isset($_GET['logout'])) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = [];
        @session_destroy();

        $paths = ['/pma/', '/pma', '/'];
        $cookieNames = array_unique(array_merge(array_keys($_COOKIE), ['pma_lang', 'phpMyAdmin', 'PMA_token']));
        foreach ($paths as $path) {
            foreach ($cookieNames as $name) {
                if (!$name) continue;
                setcookie($name, '', time() - 3600, $path, '', false, true);
            }
        }

        header('Location: /pma');
        exit;
    }

    // Let phpMyAdmin handle login POSTs and the login form itself.
    // We intentionally do not intercept POST requests here because phpMyAdmin
    // expects the credentials via POST (or cookies) in the same request.
    // The bootstrap still ensures session settings and save path are configured.
}

// The phpMyAdmin project expects to run from its own directory; change working dir.
chdir($vendorPma);

// Handle logout early so we can clear session and phpMyAdmin cookies.
if (isset($_GET['logout'])) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    // Clear session data and destroy session
    $_SESSION = [];
    @session_destroy();

    // Clear common phpMyAdmin cookies and the PHP session cookie
    $paths = ['/pma/', '/pma', '/'];
    $cookieNames = array_unique(array_merge(array_keys($_COOKIE), ['pma_lang', 'phpMyAdmin', 'PMA_token', session_name()]));
    foreach ($paths as $path) {
        foreach ($cookieNames as $name) {
            if (!$name) continue;
            // unset cookie for both site and pma paths
            setcookie($name, '', time() - 3600, $path, '', false, true);
        }
    }

    header('Location: /pma');
    exit;
}

// Locate the phpMyAdmin entry point
$entry = $vendorPma . '/index.php';
if (!file_exists($entry)) {
    $entry = $vendorPma . '/src/index.php';
}

if (!file_exists($entry)) {
    http_response_code(500);
    echo "phpMyAdmin installed but entry file not found (checked $entry).\n";
    exit;
}

// Forward the request to phpMyAdmin bootstrap
require $entry;
