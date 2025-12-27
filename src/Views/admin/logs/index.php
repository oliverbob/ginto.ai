<?php
// Admin - Activity Logs list
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin — System Logs</title>
    <link rel="stylesheet" href="/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-white dark:bg-gray-900">
    <?php include __DIR__ . '/../parts/sidebar.php'; ?>
    <div id="main-content" class="lg:pl-64">
        <?php include __DIR__ . '/../parts/header.php'; ?>
        <div class="p-6 max-w-6xl mx-auto">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h1 class="text-2xl font-semibold">System Activity Logs</h1>
                    <div class="text-sm text-gray-500">Showing <?= count($logs) ?> recent entries</div>
                </div>

                <div class="overflow-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead class="text-xs text-gray-400 uppercase border-b">
                            <tr>
                                <th>ID</th>
                                <th>When</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Model</th>
                                <th>Description</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($logs ?? []) as $l): ?>
                            <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="font-mono text-xs"><?= htmlspecialchars($l['id']) ?></td>
                                <td class="text-xs text-gray-500"><?= htmlspecialchars($l['created_at']) ?></td>
                                <td><?= htmlspecialchars($l['user_id'] ?? '(system)') ?></td>
                                <td class="text-xs font-semibold"><?= htmlspecialchars($l['action'] ?? '') ?></td>
                                <td class="text-xs"><?= htmlspecialchars($l['model_type'] ?? '') ?></td>
                                <td class="text-xs text-gray-500"><?= htmlspecialchars(mb_strimwidth($l['description'] ?? '', 0, 140, '…')) ?></td>
                                <td class="text-right"><a href="/admin/logs/<?= htmlspecialchars($l['id']) ?>" class="text-sm text-blue-600">View</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    <!-- pagination controls -->
                    <?php if (!empty($pagination) && $pagination['total'] > 1): ?>
                        <div class="flex gap-2 items-center">
                            <?php for ($p = 1; $p <= $pagination['total']; $p++): ?>
                                <a class="px-2 py-1 rounded <?= $p === $pagination['current'] ? 'bg-violet-600 text-white' : 'text-gray-600 bg-gray-100' ?>" href="/admin/logs?page=<?= $p ?>"><?= $p ?></a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
        <?php include __DIR__ . '/../parts/footer.php'; ?>
    </div>
</body>
</html>
