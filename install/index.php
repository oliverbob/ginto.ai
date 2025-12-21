<?php
/**
 * Installation UI Guardrail
 * 
 * This file serves as a gatekeeper for the installation interface.
 * It blocks access if the .installed marker exists (unless ?force=1).
 */

// Ensure ROOT_PATH is defined (set by public/index.php, fallback for direct access)
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// Check for .installed marker in both locations
$installedMarkerExists = file_exists(ROOT_PATH . '/.installed') || file_exists(dirname(ROOT_PATH) . '/storage/.installed');
$forceInstall = isset($_GET['force']) && $_GET['force'] === '1';

// Block access if installation is complete
if ($installedMarkerExists && !$forceInstall) {
    http_response_code(403);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Complete - Ginto AI</title>
    <link href="/assets/css/tailwind.css" rel="stylesheet">
</head>
<body class="bg-gray-100 dark:bg-gray-900 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 text-center">
            <div class="w-16 h-16 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Installation Complete</h1>
            <p class="text-gray-600 dark:text-gray-400 mb-6">
                Ginto AI has already been installed. The installer is disabled for security.
            </p>
            <div class="space-y-3">
                <a href="/" class="block w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-3 px-4 rounded-lg transition-colors">
                    Go to Application
                </a>
                <p class="text-xs text-gray-500 dark:text-gray-500">
                    To reinstall, add <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">?force=1</code> to the URL<br>
                    or delete the <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">.installed</code> file
                </p>
            </div>
        </div>
    </div>
</body>
</html>
    <?php
    exit;
}

// Installation not complete or force mode - serve the actual installer
// Read and output the static HTML file
$htmlFile = __DIR__ . '/installer.html';
if (file_exists($htmlFile)) {
    readfile($htmlFile);
} else {
    // Fallback: redirect to install.php for API-only mode
    echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=/install.php?action=check_status"></head></html>';
}
