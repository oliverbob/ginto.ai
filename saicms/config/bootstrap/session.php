<?php
// IMPORTANT: All session configuration must happen BEFORE session_start()

// Define how long a session should last (1 year in seconds)
$oneYearInSeconds = 365 * 24 * 60 * 60; // 31,536,000

// 1. Set session cookie lifetime (for the browser)
// This tells the browser how long to keep the session cookie.
ini_set('session.cookie_lifetime', $oneYearInSeconds);

// 2. Set garbage collection max lifetime (for the server)
// This tells PHP's garbage collector how long to keep the session data
// on the server if it's inactive. This should generally be equal to
// or greater than session.cookie_lifetime.
ini_set('session.gc_maxlifetime', $oneYearInSeconds);

// 3. Optional but HIGHLY RECOMMENDED security settings for the session cookie:
//    a. cookie_secure: Ensure the cookie is only sent over HTTPS.
//       Set to 'true' if your site is served over HTTPS (which it should be).
//       If you are developing locally without HTTPS, you might set this to 'false'
//       or make it conditional, e.g., isset($_SERVER['HTTPS']).
ini_set('session.cookie_secure', true); // Or: (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')

//    b. cookie_httponly: Prevent JavaScript from accessing the session cookie.
//       This helps mitigate XSS attacks. Always recommended.
ini_set('session.cookie_httponly', true);

//    c. cookie_samesite: Mitigates CSRF attacks. 'Lax' is a good default.
//       Requires PHP 7.3+ to set via ini_set. For older versions, you might need
//       to set it via session_set_cookie_params or header() function.
if (PHP_VERSION_ID >= 70300) {
    ini_set('session.cookie_samesite', 'Lax');
}

// 4. If you have a custom session save path, set it here (also before session_start)
// Ensure this directory exists and is writable by the web server.
// ini_set('session.save_path', __DIR__ . '/../../storage/sessions');

// Now, start the session if it hasn't been started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Your application logic using sessions would go after this ---
// For example:
// if (!isset($_SESSION['user_id'])) {
//     $_SESSION['user_id'] = uniqid('user_', true);
//     $_SESSION['created_at'] = time();
// }
// echo "Session ID: " . session_id() . "<br>";
// echo "User ID from session: " . $_SESSION['user_id'] . "<br>";
// echo "Session cookie lifetime: " . ini_get('session.cookie_lifetime') . " seconds<br>";
// echo "Session GC maxlifetime: " . ini_get('session.gc_maxlifetime') . " seconds<br>";


// Function to generate a CSRF token
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Generate a cryptographically secure random token
    }
    return $_SESSION['csrf_token'];
}

// Function to get the current CSRF token (or generate if it doesn't exist)
function getCsrfToken() {
    return generateCsrfToken(); // Ensure it's generated if not present
}
?>