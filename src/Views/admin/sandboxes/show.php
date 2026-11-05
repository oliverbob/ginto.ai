<?php
// Admin — single sandbox details
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin — Sandbox <?= htmlspecialchars($sandbox['sandbox_id']) ?></title>
    <link rel="stylesheet" href="/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-white dark:bg-gray-900">
<?php include __DIR__ . '/../parts/sidebar.php'; ?>
<div id="main-content" class="lg:pl-64">
    <?php include __DIR__ . '/../parts/header.php'; ?>

    <div class="p-6 max-w-4xl mx-auto">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-2xl font-semibold">Sandbox <span class="font-mono text-violet-600"><?= htmlspecialchars($sandbox['sandbox_id']) ?></span></h1>
                <a href="/admin/sandboxes" class="text-sm text-gray-500">Back to list</a>
            </div>

            <dl class="grid grid-cols-2 gap-6 text-sm">
                <div>
                    <dt class="text-xs text-gray-400">Mapped user</dt>
                    <dd class="mt-1"><?php if ($user): ?><?= htmlspecialchars($user['username'] . ' • ' . $user['email']) ?><?php else: ?><span class="text-gray-500">(none)</span><?php endif; ?></dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400">DB quota</dt>
                    <dd class="mt-1"><?= number_format($sandbox['quota_bytes'] ?? 0) ?> bytes</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400">Used (actual)</dt>
                    <dd class="mt-1"><?= number_format($size) ?> bytes (<?= number_format($fileCount) ?> files)</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400">Path</dt>
                    <dd class="mt-1 font-mono text-xs text-gray-600"><?= htmlspecialchars($path ?? '(missing)') ?></dd>
                </div>
            </dl>

            <hr class="my-4" />

            <div class="flex gap-4">
                <form method="post" action="/admin/sandboxes/<?= htmlspecialchars($sandbox['sandbox_id']) ?>/reset" onsubmit="return confirm('Reset sandbox contents? This will delete all files!');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>" />
                    <button class="px-4 py-2 rounded bg-yellow-500 text-white">Reset Files</button>
                </form>

                <form method="post" action="/admin/sandboxes/<?= htmlspecialchars($sandbox['sandbox_id']) ?>/delete" onsubmit="return confirm('Delete sandbox mapping and remove files? This cannot be undone.');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>" />
                    <button class="px-4 py-2 rounded bg-red-600 text-white">Delete Sandbox</button>
                </form>

                <form method="post" action="/admin/sandboxes/<?= htmlspecialchars($sandbox['sandbox_id']) ?>/quota" class="ml-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>" />
                    <label class="text-xs text-gray-400">Set quota (bytes)</label>
                    <div class="mt-1 flex gap-2 items-center">
                        <input type="number" name="quota_bytes" value="<?= htmlspecialchars($sandbox['quota_bytes']) ?>" class="border rounded px-2 py-1 text-sm" />
                        <button class="px-3 py-1 rounded bg-green-600 text-white">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../parts/footer.php'; ?>
</div>
</body>
</html>
