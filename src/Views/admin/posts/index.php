<?php
// Admin posts index — wrapped with admin layout parts
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin — Posts</title>
    <link href="/assets/css/tailwind.css" rel="stylesheet">
        <?php include __DIR__ . '/../parts/favicons.php'; ?>
</head>
<body class="min-h-screen bg-white dark:bg-gray-900">
    <div class="min-h-screen bg-white dark:bg-gray-900">
        <?php include __DIR__ . '/../parts/sidebar.php'; ?>
        <div id="main-content" class="lg:pl-64">
            <?php include __DIR__ . '/../parts/header.php'; ?>

            <div class="p-6">
                <div class="max-w-6xl mx-auto">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h1 class="text-2xl font-semibold">Posts</h1>
                            <?php include_once __DIR__ . '/../parts/admin_button.php'; admin_button('Create new post', '/admin/posts/new', ['variant' => 'primary']); ?>
                        </div>
                        <ul class="space-y-2">
                            <?php foreach (($posts ?? []) as $p): ?>
                                <li class="p-3 border rounded hover:bg-gray-50 dark:hover:bg-gray-700"><?= htmlspecialchars($p['title']) ?> <span class="text-xs ml-2 px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300"><?= htmlspecialchars($p['status']) ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <?php include __DIR__ . '/../parts/footer.php'; ?>
        </div>
    </div>
</body>
</html>