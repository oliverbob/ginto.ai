<?php
// Admin — single activity log details
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin — Log #<?= htmlspecialchars($log['id']) ?></title>
    <link rel="stylesheet" href="/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-white dark:bg-gray-900">
    <?php include __DIR__ . '/../parts/sidebar.php'; ?>
    <div id="main-content" class="lg:pl-64">
        <?php include __DIR__ . '/../parts/header.php'; ?>

        <div class="p-6 max-w-4xl mx-auto">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h1 class="text-2xl font-semibold">Log #<?= htmlspecialchars($log['id']) ?></h1>
                    <a href="/admin/logs" class="text-sm text-gray-500">Back to logs</a>
                </div>

                <dl class="grid grid-cols-2 gap-6 text-sm">
                    <div>
                        <dt class="text-xs text-gray-400">When</dt>
                        <dd class="mt-1"><?= htmlspecialchars($log['created_at']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400">User</dt>
                        <dd class="mt-1"><?= htmlspecialchars($log['user_id'] ?? '(system)') ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400">Action</dt>
                        <dd class="mt-1"><?= htmlspecialchars($log['action'] ?? '') ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-400">Model</dt>
                        <dd class="mt-1"><?= htmlspecialchars($log['model_type'] ?? '') ?> <?= htmlspecialchars($log['model_id'] ?? '') ?></dd>
                    </div>
                </dl>

                <hr class="my-4" />

                <div class="text-sm text-gray-700 dark:text-gray-300">
                    <pre style="white-space:pre-wrap; background:#0f172a; color:#cbd5e1; padding:12px; border-radius:6px;"><?= htmlspecialchars($log['description'] ?? '') ?></pre>
                </div>

            </div>
        </div>

        <?php include __DIR__ . '/../parts/footer.php'; ?>
    </div>
</body>
</html>
