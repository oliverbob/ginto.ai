<?php
// Debug helper for phpMyAdmin session problems.
// Place this under /pma/debug_session.php and visit it in the browser
// to inspect session save path, session name, cookie params, and current
// $_COOKIE and $_SESSION contents.

// Ensure same session settings as bootstrap
$projectRoot = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR;
$sessionDir = $projectRoot . 'storage' . DIRECTORY_SEPARATOR . 'sessions' . DIRECTORY_SEPARATOR;
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0755, true);
}
@chmod($sessionDir, 0755);
ini_set('session.save_path', $sessionDir);
// Match the bootstrap: use site-wide cookie path so cookies are sent for / and /pma.
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', 1);
// Persistent sessions: match bootstrap long lifetime so sessions survive restarts
ini_set('session.gc_maxlifetime', 31536000);
ini_set('session.cookie_lifetime', 31536000);

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

header('Content-Type: text/plain; charset=utf-8');
echo "Session Debug\n";
echo "===========\n";
echo "session.save_path: " . ini_get('session.save_path') . "\n";
echo "session_name: " . session_name() . "\n";
echo "session_status: " . session_status() . "\n";
echo "session_id: " . session_id() . "\n";
echo "session cookie params: " . print_r(session_get_cookie_params(), true) . "\n";
echo "\nFiles in session dir (recent):\n";
$files = @scandir($sessionDir);
if ($files === false) {
    echo "  (unable to list session dir)\n";
} else {
    foreach (array_slice(array_reverse($files), 0, 20) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $sessionDir . $f;
        echo "  " . $f . " - size=" . @filesize($p) . " modified=" . @date('c', @filemtime($p)) . "\n";
    }
}

echo "\n";
echo "\$_COOKIE:\n";
print_r($_COOKIE);
echo "\n";
echo "\$_SESSION:\n";
print_r($_SESSION);

// Provide a tiny link back to the phpMyAdmin login
echo "\nVisit /pma to test login. Use ?logout=1 to clear session.\n";

?>
