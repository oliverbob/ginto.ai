<?php
// Playground — single log detail (admin-only)
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
?>
<?php include __DIR__ . '/../parts/head.php'; ?>
<body class="bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 antialiased">
    <?php include __DIR__ . '/../parts/header.php'; ?>
    <?php include __DIR__ . '/../parts/sidebar.php'; ?>
    <?php include __DIR__ . '/../parts/content.php'; ?>

    <?php include __DIR__ . '/content.php'; ?>

    <?php include __DIR__ . '/../parts/footer.php'; ?>
    <?php include __DIR__ . '/../parts/scripts.php'; ?>
</body>
</html>
