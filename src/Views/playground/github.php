<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$pageTitle = 'GitHub - Playground';
include __DIR__ . '/parts/head.php';
?>
<body class="bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 antialiased">
    <?php include __DIR__ . '/parts/header.php'; ?>
    <?php include __DIR__ . '/parts/sidebar.php'; ?>
    <?php include __DIR__ . '/parts/content.php'; ?>

    <div class="space-y-6">
        <div class="rounded-xl bg-white dark:bg-gray-900 p-6 border border-gray-200/50 dark:border-gray-700/50">
            <h1 class="text-xl font-semibold">GitHub</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Quick links to the repository and common actions.</p>

            <div class="mt-4">
                <a class="inline-flex items-center gap-2 px-4 py-2 bg-violet-600 text-white rounded" href="https://github.com/">Open Repository</a>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/parts/footer.php'; ?>
    </div>
</main>

<?php include __DIR__ . '/parts/scripts.php'; ?>

</body>
</html>
