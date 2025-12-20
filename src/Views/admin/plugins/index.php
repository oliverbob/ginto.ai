<?php
// Admin plugins index — wrapped with admin layout parts
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin — Plugins</title>
    <link rel="stylesheet" href="/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-white dark:bg-gray-900">
    <div class="min-h-screen bg-white dark:bg-gray-900">
        <?php include __DIR__ . '/../parts/sidebar.php'; ?>
        <div id="main-content" class="lg:pl-64">
            <?php include __DIR__ . '/../parts/header.php'; ?>

            <div class="p-6 max-w-6xl mx-auto">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h1 class="text-2xl font-semibold mb-4">Plugins</h1>
                    <ul class="space-y-2">
                        <?php foreach (($plugins ?? []) as $p): ?>
                            <li class="p-2 border rounded"><?= htmlspecialchars($p['name']) ?> v<?= htmlspecialchars($p['version']) ?> <?= $p['is_active'] ? '<span class="ml-2 text-xs px-2 py-1 rounded bg-green-100 dark:bg-green-800 text-green-800 dark:text-green-200">Active</span>' : '' ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <?php include __DIR__ . '/../parts/footer.php'; ?>
        </div>
    </div>
</body>
</html>