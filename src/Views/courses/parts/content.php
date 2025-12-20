<?php
// parts/content.php - Content wrapper
// Includes page-specific content from pages/ directory

$page = $page ?? 'home';
$pagePath = __DIR__ . '/../pages/' . $page . '.php';

if (file_exists($pagePath)) {
    include $pagePath;
} else {
    // Fallback to home.php if page not found
    include __DIR__ . '/../pages/home.php';
}
?>
