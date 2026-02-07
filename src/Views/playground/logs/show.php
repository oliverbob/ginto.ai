<?php
// Show single playground log entry (admin)
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$pageTitle = $pageTitle ?? ('Playground Log');
?><!doctype html>
<html lang="en">
<?php include __DIR__ . '/../parts/head.php'; ?>
<body class="bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 antialiased">
    <main class="max-w-4xl mx-auto p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold"><?= htmlspecialchars($pageTitle) ?></h1>
            <div>
                <a href="/playground/logs" class="editor-btn editor-btn-secondary">Back to Logs</a>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200/50 dark:border-gray-700/50 p-4">
            <div class="mb-3 text-sm text-gray-600 dark:text-gray-400">ID: <?= (int)($log['id'] ?? 0) ?> — User: <?= htmlspecialchars($log['username'] ?? '(system)') ?> — Action: <?= htmlspecialchars($log['action'] ?? '') ?></div>

            <h3 class="text-sm font-medium">Description</h3>
            <pre class="mt-2 p-3 rounded bg-gray-100 dark:bg-black dark:text-green-200 text-sm overflow-auto"><?= htmlspecialchars($log['description'] ?? '') ?></pre>

            <?php if (!empty($log['description_json'])): ?>
                <h3 class="text-sm font-medium mt-4">Parsed JSON</h3>
                <pre class="mt-2 p-3 rounded bg-gray-100 dark:bg-black dark:text-green-200 text-sm overflow-auto"><?= htmlspecialchars($log['description_json']) ?></pre>
            <?php endif; ?>

            <div class="mt-4 text-xs text-gray-500">Created at: <?= htmlspecialchars($log['created_at'] ?? '') ?></div>
        </div>
    </main>

<?php include __DIR__ . '/../parts/scripts.php'; ?>
</body>
</html>
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
