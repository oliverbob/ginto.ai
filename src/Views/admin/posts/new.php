<?php
// Admin create post — wrapped in admin layout parts
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Create Post — Admin</title>
    <link rel="stylesheet" href="/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-white dark:bg-gray-900">
    <div class="min-h-screen bg-white dark:bg-gray-900">
        <?php include __DIR__ . '/../parts/sidebar.php'; ?>
        <div id="main-content" class="lg:pl-64">
            <?php include __DIR__ . '/../parts/header.php'; ?>

            <div class="p-6 max-w-4xl mx-auto">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h1 class="text-xl font-semibold mb-4">Create Post</h1>
                    <form method="post" action="/admin/posts" class="space-y-4">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf_token ?? '') ?>" />
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Title</label>
                            <input type="text" name="title" value="<?= htmlspecialchars($old['title'] ?? '') ?>" class="w-full p-2 border rounded" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Content</label>
                            <textarea name="content" class="w-full p-2 border rounded" rows="6"><?= htmlspecialchars($old['content'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                            <select name="status" class="block p-2 border rounded">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="px-4 py-2 bg-yellow-400 text-white rounded">Create</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php include __DIR__ . '/../parts/footer.php'; ?>
        </div>
    </div>
</body>
</html>