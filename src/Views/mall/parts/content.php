<?php
// content.php - content wrapper that includes a page from pages/
$page = $page ?? 'home';
$pagePath = __DIR__ . '/../pages/' . $page . '.php';

if (file_exists($pagePath)) {
    include $pagePath;
} else {
    include __DIR__ . '/../pages/home.php';
}
?>