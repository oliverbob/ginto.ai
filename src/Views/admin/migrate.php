<?php
/**
 * Admin Migration Status & Runner
 * /admin/migrate
 */

$status = $status ?? ['executed' => [], 'pending' => [], 'db_type' => 'unknown'];

// Generate CSRF token if not exists
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
    <title>Database Migrations - Ginto Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {}
            }
        }
    </script>
</head>
<body class="min-h-screen bg-white dark:bg-gray-900">
    <div class="min-h-screen bg-white dark:bg-gray-900">
        <?php include __DIR__ . '/parts/sidebar.php'; ?>
        <div id="main-content" class="lg:pl-64">
            <?php include __DIR__ . '/parts/header.php'; ?>
            
            <!-- Main Content Area -->
            <main class="p-4 md:p-6 lg:p-8">
                <div class="max-w-4xl mx-auto">
                    <div class="mb-8">
                        <h1 class="text-3xl font-bold mb-2 text-gray-900 dark:text-white">🗄️ Database Migrations</h1>
                        <p class="text-gray-500 dark:text-gray-400">Database Type: <span class="text-blue-600 dark:text-blue-400"><?= htmlspecialchars($status['db_type']) ?></span></p>
                    </div>

                    <!-- Pending Migrations -->
                    <div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-6 mb-6">
                        <h2 class="text-xl font-semibold mb-4 flex items-center text-gray-900 dark:text-white">
                            <span class="mr-2">⏳</span> Pending Migrations
                            <span class="ml-2 bg-yellow-500 dark:bg-yellow-600 text-white text-sm px-2 py-1 rounded"><?= count($status['pending']) ?></span>
                        </h2>
                        
                        <?php if (empty($status['pending'])): ?>
                            <p class="text-green-600 dark:text-green-400">✅ No pending migrations - database is up to date!</p>
                        <?php else: ?>
                            <ul class="space-y-2 mb-4">
                                <?php foreach ($status['pending'] as $migration): ?>
                                    <li class="bg-yellow-100 dark:bg-yellow-900/30 border border-yellow-300 dark:border-yellow-700 rounded px-4 py-2 font-mono text-sm text-gray-800 dark:text-gray-200">
                                        <?= htmlspecialchars($migration) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            
                            <button id="runMigrations" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded transition">
                                🚀 Run Migrations
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Result Area -->
                    <div id="result" class="hidden bg-gray-100 dark:bg-gray-800 rounded-lg p-6 mb-6">
                        <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">📋 Result</h2>
                        <pre id="resultContent" class="bg-white dark:bg-gray-900 p-4 rounded overflow-x-auto text-sm text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700"></pre>
                    </div>

                    <!-- Executed Migrations -->
                    <div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-6">
                        <h2 class="text-xl font-semibold mb-4 flex items-center text-gray-900 dark:text-white">
                            <span class="mr-2">✅</span> Executed Migrations
                            <span class="ml-2 bg-green-500 dark:bg-green-600 text-white text-sm px-2 py-1 rounded"><?= count($status['executed']) ?></span>
                        </h2>
                        
                        <?php if (empty($status['executed'])): ?>
                            <p class="text-gray-500 dark:text-gray-400">No migrations have been executed yet.</p>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-200 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-gray-900 dark:text-white">Migration</th>
                                            <th class="px-4 py-2 text-left text-gray-900 dark:text-white">Batch</th>
                                            <th class="px-4 py-2 text-left text-gray-900 dark:text-white">Executed At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($status['executed'] as $migration): ?>
                                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                                <td class="px-4 py-2 font-mono text-gray-800 dark:text-gray-200"><?= htmlspecialchars($migration['migration']) ?></td>
                                                <td class="px-4 py-2 text-gray-800 dark:text-gray-200"><?= htmlspecialchars($migration['batch']) ?></td>
                                                <td class="px-4 py-2 text-gray-500 dark:text-gray-400"><?= htmlspecialchars($migration['executed_at']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
        <?php include __DIR__ . '/parts/footer.php'; ?>
    </div>

    <script>
        document.getElementById('runMigrations')?.addEventListener('click', async function() {
            const btn = this;
            btn.disabled = true;
            btn.textContent = '⏳ Running...';
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            
            try {
                const response = await fetch('/admin/migrate/run', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ csrf_token: csrfToken })
                });
                
                const data = await response.json();
                
                document.getElementById('result').classList.remove('hidden');
                document.getElementById('resultContent').textContent = JSON.stringify(data, null, 2);
                
                if (data.success) {
                    btn.textContent = '✅ Complete!';
                    btn.classList.remove('bg-green-600', 'hover:bg-green-700');
                    btn.classList.add('bg-gray-600');
                    
                    // Reload after 2 seconds to show updated status
                    setTimeout(() => location.reload(), 2000);
                } else {
                    btn.textContent = '❌ Failed - Check Result';
                    btn.classList.remove('bg-green-600', 'hover:bg-green-700');
                    btn.classList.add('bg-red-600');
                }
            } catch (error) {
                document.getElementById('result').classList.remove('hidden');
                document.getElementById('resultContent').textContent = 'Error: ' + error.message;
                btn.textContent = '❌ Error';
                btn.classList.remove('bg-green-600', 'hover:bg-green-700');
                btn.classList.add('bg-red-600');
            }
        });
    </script>
</body>
</html>
