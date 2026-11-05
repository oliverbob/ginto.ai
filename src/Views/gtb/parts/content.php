<?php
// gtb/parts/content.php - Content wrapper: includes the active page from pages/
$page = $page ?? 'home';
$pagePath = __DIR__ . '/../pages/' . basename($page) . '.php';
?>
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <?php
    if (file_exists($pagePath)) {
        include $pagePath;
    } else {
        include __DIR__ . '/../pages/home.php';
    }
    ?>
</main>
