<?php
// Dedicated Playground Logs viewer (admin-only)
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
// $logs, $pagination, $q, $pageTitle are provided by the route
$pageTitle = $pageTitle ?? 'Playground Logs';
?><!doctype html>
<html lang="en">
<?php include __DIR__ . '/../parts/head.php'; ?>
<body class="bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 antialiased">
    <main class="max-w-7xl mx-auto p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold"><?= htmlspecialchars($pageTitle) ?></h1>
            <div class="flex items-center gap-2">
                <a href="/playground" class="inline-flex items-center px-3 py-2 rounded bg-white dark:bg-gray-900 border border-gray-200/50 dark:border-gray-700/50">Back to Playground</a>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200/50 dark:border-gray-700/50 p-4">
            <form id="logs-search" method="get" action="/playground/logs" class="flex items-center gap-2 mb-4">
                <input type="text" name="q" value="<?= htmlspecialchars($q ?? '') ?>" placeholder="Search logs (action, model_type, description)" class="flex-1 p-2 rounded border dark:border-gray-700 bg-white dark:bg-gray-900" />
                <button class="editor-btn" type="submit">Search</button>
                <a href="/playground/logs" class="editor-btn editor-btn-secondary">Clear</a>
            </form>

            <div class="overflow-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase">
                            <th class="px-3 py-2">ID</th>
                            <th class="px-3 py-2">Time</th>
                            <th class="px-3 py-2">User</th>
                            <th class="px-3 py-2">Action</th>
                            <th class="px-3 py-2">Model</th>
                            <th class="px-3 py-2">Summary</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="6" class="px-3 py-4 text-gray-500">No logs found.</td></tr>
                        <?php else: foreach ($logs as $r): ?>
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-2"><a class="text-violet-600 dark:text-violet-300" href="/playground/logs/<?= (int)$r['id'] ?>"><?= (int)$r['id'] ?></a></td>
                                <td class="px-3 py-2 text-xs text-gray-500"><?= htmlspecialchars($r['created_at'] ?? '') ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($r['username'] ?? '') ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($r['action'] ?? '') ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($r['model_type'] ?? '') ?></td>
                                <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400"><?= htmlspecialchars($r['summary'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <div class="text-sm text-gray-600 dark:text-gray-400">Page <?= htmlspecialchars($pagination['current'] ?? 1) ?> of <?= htmlspecialchars($pagination['total'] ?? 1) ?></div>
                <div class="flex items-center gap-2">
                    <?php $cur = (int)($pagination['current'] ?? 1); $tot = (int)($pagination['total'] ?? 1); ?>
                    <?php if ($cur > 1): ?><a href="/playground/logs?page=<?= $cur-1 ?>&q=<?= urlencode($q ?? '') ?>" class="editor-btn">Prev</a><?php endif; ?>
                    <?php if ($cur < $tot): ?><a href="/playground/logs?page=<?= $cur+1 ?>&q=<?= urlencode($q ?? '') ?>" class="editor-btn">Next</a><?php endif; ?>
                </div>
            </div>
        </div>
    </main>

<?php include __DIR__ . '/../parts/scripts.php'; ?>
</body>
</html>
<?php
// Playground — Logs list (admin-only)
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
