<?php
// Admin pages index — use shared admin layout parts for consistent UI
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../parts/favicons.php'; ?>
    <title>Admin — Pages</title>
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css" />
    <link rel="stylesheet" href="/assets/css/tailwind.css">
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
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Pages</h1>
                            <div class="flex items-center gap-3">
                                <?php include_once __DIR__ . '/../parts/admin_button.php'; ?>
                                <?php admin_button('User view scripts', '/admin/pages/scripts', ['variant' => 'outline']); ?>
                                <?php admin_button('Routes', '/admin/pages/routes', ['variant' => 'outline']); ?>
                                <?php admin_button('Create new page', '/admin/pages/new', ['variant' => 'primary']); ?>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-6">
                            <div class="col-span-2">
                                <ul class="space-y-2">
                                    <?php foreach (($pages ?? []) as $p): ?>
                                        <li class="p-3 border rounded hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <a href="/admin/pages/<?= $p['id'] ?>" class="font-medium text-gray-900 dark:text-gray-200"><?= htmlspecialchars($p['title']) ?></a>
                                            <span class="text-xs ml-2 px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300"><?= htmlspecialchars($p['status']) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>

                            <div class="col-span-1">
                                <div class="bg-gray-50 dark:bg-gray-700 rounded p-3 text-sm overflow-hidden">
                                    <div class="flex items-center justify-between mb-2">
                                        <strong>User view scripts</strong>
                                        <a href="/admin/pages/scripts" class="text-xs text-gray-600 dark:text-gray-300">Open full page</a>
                                    </div>
                                    <?php if (empty($tree)): ?>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">No user view scripts found in <code>src/Views/user</code>.</div>
                                    <?php else: ?>
                                        <div class="space-y-1 text-xs text-gray-700 dark:text-gray-200 max-h-64 overflow-auto pr-2">
                                            <?php function render_small_tree($node, $prefix = '') {
                                                foreach ($node as $name => $n) {
                                                    if ($n['type'] === 'dir') {
                                                        echo '<div class="font-medium">' . htmlspecialchars($name) . '</div>';
                                                        echo '<div class="ml-3 text-xs">';
                                                        render_small_tree($n['children'], $prefix . $name . '/');
                                                        echo '</div>';
                                                    } else {
                                                        $parts = explode('/', $n['path']);
                                                        $fileName = array_pop($parts);
                                                        $enc = htmlspecialchars($n['encoded']);
                                                        echo '<div class="truncate">';
                                                        // Use a slightly muted accent on dark theme for readability and avoid overly bright orange
                                                        // Use a darker yellow on light theme to improve contrast; keep lighter shade in dark mode
                                                        echo '<a class="text-xs text-yellow-600 dark:text-yellow-300 hover:underline" href="/admin/pages/scripts/edit?file=' . $enc . '">' . htmlspecialchars($fileName) . '</a>';
                                                        echo '</div>';
                                                    }
                                                }
                                            }
                                            render_small_tree($tree);
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>

            <?php include __DIR__ . '/../parts/footer.php'; ?>
        </div>
    </div>
</body>
</html>