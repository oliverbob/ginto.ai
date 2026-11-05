<?php
// Admin tags index — wrapped with admin layout parts
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin — Tags</title>
    <link rel="stylesheet" href="/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-white dark:bg-gray-900">
    <div class="min-h-screen bg-white dark:bg-gray-900">
        <?php include __DIR__ . '/../parts/sidebar.php'; ?>
        <div id="main-content" class="lg:pl-64">
            <?php include __DIR__ . '/../parts/header.php'; ?>

            <div class="p-6 max-w-4xl mx-auto">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h1 class="text-2xl font-semibold mb-4">Tags</h1>
                    <ul class="space-y-2">
                        <?php foreach (($tags ?? []) as $t): ?>
                            <li class="p-3 border rounded hover:bg-gray-50 dark:hover:bg-gray-700"><?= htmlspecialchars($t['name']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <?php include __DIR__ . '/../parts/footer.php'; ?>
        </div>
    </div>
</body>
</html>