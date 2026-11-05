<?php
// Admin — Sandbox Manager (list)
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin — Playgrounds Sandboxes</title>
    <link rel="stylesheet" href="/assets/css/tailwind.css">
    <style>table td, table th { padding: 8px 12px; }</style>
</head>
<body class="min-h-screen bg-white dark:bg-gray-900">
    <div class="min-h-screen bg-white dark:bg-gray-900">
        <?php include __DIR__ . '/../parts/sidebar.php'; ?>
        <div id="main-content" class="lg:pl-64">
            <?php include __DIR__ . '/../parts/header.php'; ?>

            <div class="p-6 max-w-6xl mx-auto">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h1 class="text-2xl font-semibold">Playground Sandboxes</h1>
                        <div class="text-sm text-gray-500">Total: <?= count($sandboxes) ?></div>
                    </div>

                    <div class="overflow-auto">
                        <table class="min-w-full bg-transparent text-sm text-left">
                            <thead>
                                <tr class="text-xs uppercase text-gray-400 border-b">
                                    <th>Sandbox ID</th>
                                    <th>User</th>
                                    <th>Quota</th>
                                    <th>Used</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($sandboxes ?? []) as $s): ?>
                                <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td><a href="/admin/sandboxes/<?= htmlspecialchars($s['sandbox_id']) ?>" class="font-mono text-violet-600"><?= htmlspecialchars($s['sandbox_id']) ?></a></td>
                                    <td>
                                        <?php if (!empty($s['user'])): ?>
                                            <?= htmlspecialchars($s['user']['username'] ?? $s['user']['fullname'] ?? 'user') ?>
                                            <div class="text-xs text-gray-400"><?= htmlspecialchars($s['user']['email'] ?? '') ?></div>
                                        <?php else: ?>
                                            <span class="text-sm text-gray-500">(unmapped)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= number_format($s['quota_bytes'] ?? 0) ?> bytes</td>
                                    <td><?= number_format($s['used_bytes'] ?? 0) ?> bytes</td>
                                    <td><?= htmlspecialchars($s['created_at'] ?? '') ?></td>
                                    <td>
                                        <a class="inline-block mr-2" href="/admin/sandboxes/<?= htmlspecialchars($s['sandbox_id']) ?>">View</a>
                                        <form style="display:inline" method="post" action="/admin/sandboxes/<?= htmlspecialchars($s['sandbox_id']) ?>/delete" onsubmit="return confirm('Delete sandbox mapping and files?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <button class="text-red-600">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php include __DIR__ . '/../parts/footer.php'; ?>
        </div>
    </div>
</body>
</html>
