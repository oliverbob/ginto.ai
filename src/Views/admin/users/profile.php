<?php
// Admin user profile — wrapped with admin layout parts
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>User Profile — Admin</title>
    <link rel="stylesheet" href="/assets/css/tailwind.css">
</head>
<body class="min-h-screen bg-white dark:bg-gray-900">
    <div class="min-h-screen bg-white dark:bg-gray-900">
        <?php include __DIR__ . '/../parts/sidebar.php'; ?>
        <div id="main-content" class="lg:pl-64">
            <?php include __DIR__ . '/../parts/header.php'; ?>

            <div class="p-6 max-w-4xl mx-auto">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h1 class="text-2xl font-semibold mb-2"><?= htmlspecialchars($user['fullname'] ?? 'User Profile') ?></h1>
                    <p class="text-sm text-gray-600 mb-4"><?= htmlspecialchars($user['email'] ?? '') ?></p>

                    <h2 class="text-lg font-semibold mb-2">Referrals</h2>
                    <ul class="space-y-2">
                        <?php foreach (($referrals ?? []) as $r): ?>
                            <li class="p-2 border rounded"><?= htmlspecialchars($r['username']) ?> — <span class="text-xs text-gray-500"><?= htmlspecialchars($r['created_at']) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <?php include __DIR__ . '/../parts/footer.php'; ?>
        </div>
    </div>
</body>
</html>