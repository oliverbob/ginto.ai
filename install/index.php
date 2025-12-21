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

// Block access if installation is complete - redirect to application
if ($installedMarkerExists) {
    header('Location: /');
    ob_end_clean(); // Clear any output buffer
    die(); // Ensure absolutely nothing is sent
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
