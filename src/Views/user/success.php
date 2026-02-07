<!-- src/Views/user/success.php -->
<?php
// Ensure a session is active before accessing $_SESSION.
// If the application bootstrap or controller already starts the session,
// this will not start a second one.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/** @var string $title */
/** @var string $message */

// Read registered_fullname safely with a fallback
$fullname = $_SESSION['registered_fullname'] ?? 'User'; // fallback
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <link href="/assets/css/tailwind.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-amber-300 to-yellow-500 min-h-screen flex items-center justify-center p-6">

<div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-8 text-center">
    <h1 class="text-3xl font-bold mb-4 text-amber-600">🎉 Welcome, <?= htmlspecialchars($fullname) ?>!</h1>
    <p class="mb-6 text-gray-700"><?= htmlspecialchars($message) ?></p>

    <a href="/login"
       class="inline-block w-full bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600 
              text-white font-semibold py-3 rounded-lg shadow-lg 
              hover:from-yellow-500 hover:via-yellow-600 hover:to-yellow-700 
              transition-all duration-300">
        Go to Login
    </a>
</div>

</body>
</html>
<?php
// Optionally clear the session variable after using it
unset($_SESSION['registered_fullname']);
?>