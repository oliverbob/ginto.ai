<?php
// Playground - User sandbox logs view
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
// expects $logs, $pagination, $q, $pageTitle
$pageTitle = $pageTitle ?? 'My Sandbox Logs';
?>
<?php include __DIR__ . '/../parts/head.php'; ?>
<body class="bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 antialiased">
    <?php include __DIR__ . '/../parts/header.php'; ?>
    <?php include __DIR__ . '/../parts/sidebar.php'; ?>
    <?php include __DIR__ . '/../parts/content.php'; ?>

    <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200/50 dark:border-gray-700/50 p-4">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-medium"><?= htmlspecialchars($pageTitle) ?></h2>
            <div class="flex items-center gap-2">
                <a href="/playground" class="editor-btn editor-btn-secondary">Back</a>
            </div>
        </div>

        <form id="logs-search" method="get" action="/playground/logs" class="flex items-center gap-2 mb-4">
            <input type="text" name="q" value="<?= htmlspecialchars($q ?? '') ?>" placeholder="Search your sandbox logs" class="flex-1 p-2 rounded border dark:border-gray-700 bg-white dark:bg-gray-900" />
            <button class="editor-btn" type="submit">Search</button>
            <a href="/playground/logs" class="editor-btn editor-btn-secondary">Clear</a>
        </form>

        <div class="overflow-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-500 uppercase">
                        <th class="px-3 py-2">Time</th>
                        <th class="px-3 py-2">Action</th>
                        <th class="px-3 py-2">Summary</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="3" class="px-3 py-4 text-gray-500">No logs found for your sandbox.</td></tr>
                    <?php else: foreach ($logs as $r): ?>
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="px-3 py-2 text-xs text-gray-500"><?= htmlspecialchars($r['created_at'] ?? '') ?></td>
                            <td class="px-3 py-2"><?= htmlspecialchars($r['action'] ?? '') ?></td>
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

    <?php include __DIR__ . '/../parts/footer.php'; ?>
    <?php include __DIR__ . '/../parts/scripts.php'; ?>
</body>
</html>
