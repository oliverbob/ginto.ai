<?php
// public/install_status_tail.php
// Returns a JSON object with a tail of the installer log and
// a short listing of top-level files in the client's editor root.

header('Content-Type: application/json; charset=utf-8');

$sandbox = $_GET['sandbox'] ?? '';
if (!$sandbox) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'missing_sandbox']);
    exit;
}

// Minimal bootstrap so we can check session and DB when available.
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/vendor/autoload.php';

$repoRoot = dirname(__DIR__);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Require authenticated session to avoid exposing logs to anonymous users
if (empty($_SESSION['user_id'])) {
    // Attempt to recover session user by CSRF token provided by the client
    $csrfToken = $_GET['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if ($csrfToken) {
        // Look for session files containing this CSRF token (local dev fallback)
        $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : dirname($repoRoot) . '/storage';
        $sessDir = $storagePath . '/sessions';
        if (is_dir($sessDir)) {
            foreach (scandir($sessDir) as $f) {
                if ($f === '.' || $f === '..') continue;
                $path = $sessDir . '/' . $f;
                if (!is_file($path) || !is_readable($path)) continue;
                $contents = file_get_contents($path);
                if ($contents === false) continue;
                if (strpos($contents, $csrfToken) !== false) {
                    // Try to extract user_id from session contents
                    if (preg_match('/user_id\|i:(\d+);/', $contents, $m)) {
                        $_SESSION['user_id'] = (int)$m[1];
                        break;
                    }
                }
            }
        }
    }
}

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'unauthorized']);
    exit;
}

// sanitize
$safeId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', basename($sandbox));
$repoRoot = dirname(__DIR__);
$storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : dirname($repoRoot) . '/storage';
$logFile = $storagePath . '/backups/install_' . $safeId . '.log';

// If DB is available, ensure the requesting user is permitted to view this sandbox
$allowed = false;
try {
    $db = \Ginto\Core\Database::getInstance();
    // Admins may view any sandbox
    $userRole = $db->get('users', 'role_id', ['id' => (int)$_SESSION['user_id']]);
    if (!empty($userRole) && in_array((int)$userRole, [1,2], true)) {
        $allowed = true;
    } else {
        // Check client_sandboxes mapping
        $row = $db->get('client_sandboxes', ['sandbox_id','user_id'], ['sandbox_id' => $safeId]);
        if ($row && !empty($row['user_id']) && (int)$row['user_id'] === (int)$_SESSION['user_id']) {
            $allowed = true;
        }
    }
} catch (\Throwable $_) {
    // DB not available - fall back to filesystem check but still require login
    $editorRoot = $repoRoot . '/clients/' . $safeId;
    if (is_dir($editorRoot)) {
        $allowed = true;
    }
}

if (!$allowed) {
    // As an additional fallback, try to determine the editor root for
    // the current session using ClientSandboxHelper (without attempting
    // to start or create the sandbox). If the session's editor root
    // matches the requested sandbox, allow access.
    try {
        putenv('GINTO_SKIP_SANDBOX_START=1');
        $editorRoot = \Ginto\Helpers\ClientSandboxHelper::getOrCreateSandboxRoot($db ?? null, $_SESSION ?? null);
        putenv('GINTO_SKIP_SANDBOX_START');
        if ($editorRoot && basename($editorRoot) === $safeId) {
            $allowed = true;
        }
    } catch (\Throwable $_) {
        // ignore — fall through to forbidden response
    }
    if (!$allowed) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'forbidden']);
        exit;
    }
}

$installLogTail = '';
$daemonLogTail = '';
if (is_file($logFile) && is_readable($logFile)) {
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        $tail = array_slice($lines, -200);
        $installLogTail = implode("\n", $tail);
    }
}

// If the install log mentions a daemon-created canonical id or daemon log
// path, try to include the tail of the daemon log as well so the UI shows
// a single consolidated view of what's happening. Example mapping lines look
// like: [date] daemon_ok canonical_sandboxId=2nkouhhsydul original=... daemon_log=/var/log/ginto/sandboxd_2nkouhhsydul.log
if ($installLogTail) {
    if (preg_match('/daemon_ok.*canonical_sandboxId=([^\s]+).*daemon_log=([^\s]+)/', $installLogTail, $m)) {
        $canonical = $m[1];
        $daemonLog = $m[2];
        if (is_file($daemonLog) && is_readable($daemonLog)) {
            $dlines = @file($daemonLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($dlines)) {
                $dtail = array_slice($dlines, -400);
                $daemonLogTail = implode("\n", $dtail);
                // Append a short separator and the daemon tail to the install tail
                $installLogTail .= "\n\n[daemon_log tail: $daemonLog]\n" . $daemonLogTail;
            }
        }
    }
}

$installedFiles = [];
$editorRoot = $repoRoot . '/clients/' . $safeId;
if (is_dir($editorRoot)) {
    $entries = @scandir($editorRoot);
    if (is_array($entries)) {
        foreach ($entries as $e) {
            if ($e === '.' || $e === '..') continue;
            $installedFiles[] = $e;
            if (count($installedFiles) >= 200) break;
        }
    }
}

echo json_encode([
    'success' => true,
    'install_log_tail' => $installLogTail,
    'installed_files' => $installedFiles
]);
