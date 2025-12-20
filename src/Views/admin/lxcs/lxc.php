<?php
// Admin — LXD Container Details
$title = $container['name'] ?? 'Container';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin — <?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="/assets/css/tailwind.css">
    <style>
        table td, table th { padding: 8px 12px; }
        .status-running { color: #22c55e; }
        .status-stopped { color: #6b7280; }
        .terminal-output { font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace; font-size: 12px; }
        .pulse { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    </style>
</head>
<body class="min-h-screen bg-white dark:bg-gray-900">
    <div class="min-h-screen bg-white dark:bg-gray-900">
        <?php include __DIR__ . '/../parts/sidebar.php'; ?>
        <div id="main-content" class="lg:pl-64">
            <?php include __DIR__ . '/../parts/header.php'; ?>

            <div class="p-6 max-w-6xl mx-auto">
                <!-- Breadcrumb -->
                <nav class="mb-4 text-sm">
                    <a href="/admin/lxcs" class="text-violet-600 hover:underline">LXD Containers</a>
                    <span class="mx-2 text-gray-400">/</span>
                    <span class="text-gray-600 dark:text-gray-300"><?= htmlspecialchars($container['name'] ?? '') ?></span>
                </nav>

                <!-- Container Header -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-violet-100 dark:bg-violet-900 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                                </svg>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold dark:text-white"><?= htmlspecialchars($container['name'] ?? '') ?></h1>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="status-<?= strtolower($container['status'] ?? 'unknown') ?> font-medium flex items-center gap-1">
                                        <?php if (($container['status'] ?? '') === 'Running'): ?>
                                        <span class="w-2 h-2 bg-green-500 rounded-full pulse"></span>
                                        <?php else: ?>
                                        <span class="w-2 h-2 bg-gray-400 rounded-full"></span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($container['status'] ?? 'Unknown') ?>
                                    </span>
                                    <?php if (!empty($container['ip'])): ?>
                                    <span class="text-gray-500 dark:text-gray-400">•</span>
                                    <span class="font-mono text-sm text-gray-600 dark:text-gray-400"><?= htmlspecialchars($container['ip']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <?php if (($container['status'] ?? '') === 'Running'): ?>
                            <form method="post" action="/admin/lxcs/<?= htmlspecialchars($container['name']) ?>/stop" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                <button type="submit" class="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/>
                                    </svg>
                                    Stop
                                </button>
                            </form>
                            <form method="post" action="/admin/lxcs/<?= htmlspecialchars($container['name']) ?>/restart" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                <button type="submit" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    Restart
                                </button>
                            </form>
                            <?php else: ?>
                            <form method="post" action="/admin/lxcs/<?= htmlspecialchars($container['name']) ?>/start" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                <button type="submit" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Start
                                </button>
                            </form>
                            <?php endif; ?>
                            <form method="post" action="/admin/lxcs/<?= htmlspecialchars($container['name']) ?>/delete" class="inline" onsubmit="return confirm('Delete container <?= htmlspecialchars($container['name']) ?>? This cannot be undone.');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                <button type="submit" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">CPU Usage</div>
                        <div class="text-2xl font-bold dark:text-white"><?= htmlspecialchars($container['cpu'] ?? '-') ?>%</div>
                        <?php if (!empty($container['cpu'])): ?>
                        <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-violet-600 h-2 rounded-full" style="width: <?= min(100, floatval($container['cpu'])) ?>%"></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Memory</div>
                        <div class="text-2xl font-bold dark:text-white"><?= htmlspecialchars($container['memory'] ?? '-') ?></div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Disk</div>
                        <div class="text-2xl font-bold dark:text-white"><?= htmlspecialchars($container['disk'] ?? '-') ?></div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Processes</div>
                        <div class="text-2xl font-bold dark:text-white"><?= htmlspecialchars($container['processes'] ?? '-') ?></div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div class="border-b border-gray-200 dark:border-gray-700">
                        <nav class="flex -mb-px">
                            <button onclick="showTab('info')" id="tab-info" class="tab-btn px-6 py-3 border-b-2 border-violet-600 text-violet-600 font-medium">Info</button>
                            <button onclick="showTab('exec')" id="tab-exec" class="tab-btn px-6 py-3 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium">Execute</button>
                            <button onclick="showTab('logs')" id="tab-logs" class="tab-btn px-6 py-3 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium">Logs</button>
                            <button onclick="showTab('config')" id="tab-config" class="tab-btn px-6 py-3 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium">Config</button>
                        </nav>
                    </div>

                    <!-- Info Tab -->
                    <div id="panel-info" class="tab-panel p-6">
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <h3 class="text-lg font-semibold mb-3 dark:text-white">Container Details</h3>
                                <table class="w-full text-sm">
                                    <tr class="border-b dark:border-gray-700">
                                        <td class="py-2 text-gray-500 dark:text-gray-400">Name</td>
                                        <td class="py-2 dark:text-white font-mono"><?= htmlspecialchars($container['name'] ?? '') ?></td>
                                    </tr>
                                    <tr class="border-b dark:border-gray-700">
                                        <td class="py-2 text-gray-500 dark:text-gray-400">Status</td>
                                        <td class="py-2 dark:text-white"><?= htmlspecialchars($container['status'] ?? '') ?></td>
                                    </tr>
                                    <tr class="border-b dark:border-gray-700">
                                        <td class="py-2 text-gray-500 dark:text-gray-400">Type</td>
                                        <td class="py-2 dark:text-white"><?= htmlspecialchars($container['type'] ?? 'container') ?></td>
                                    </tr>
                                    <tr class="border-b dark:border-gray-700">
                                        <td class="py-2 text-gray-500 dark:text-gray-400">Architecture</td>
                                        <td class="py-2 dark:text-white"><?= htmlspecialchars($container['architecture'] ?? '-') ?></td>
                                    </tr>
                                    <tr class="border-b dark:border-gray-700">
                                        <td class="py-2 text-gray-500 dark:text-gray-400">Created</td>
                                        <td class="py-2 dark:text-white"><?= htmlspecialchars($container['created_at'] ?? '-') ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold mb-3 dark:text-white">Network</h3>
                                <table class="w-full text-sm">
                                    <tr class="border-b dark:border-gray-700">
                                        <td class="py-2 text-gray-500 dark:text-gray-400">IPv4</td>
                                        <td class="py-2 dark:text-white font-mono"><?= htmlspecialchars($container['ip'] ?? '-') ?></td>
                                    </tr>
                                    <tr class="border-b dark:border-gray-700">
                                        <td class="py-2 text-gray-500 dark:text-gray-400">IPv6</td>
                                        <td class="py-2 dark:text-white font-mono"><?= htmlspecialchars($container['ipv6'] ?? '-') ?></td>
                                    </tr>
                                    <?php if (!empty($container['network'])): ?>
                                    <?php foreach ($container['network'] as $iface => $net): ?>
                                    <tr class="border-b dark:border-gray-700">
                                        <td class="py-2 text-gray-500 dark:text-gray-400"><?= htmlspecialchars($iface) ?></td>
                                        <td class="py-2 dark:text-white font-mono text-xs"><?= htmlspecialchars(json_encode($net)) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Exec Tab -->
                    <div id="panel-exec" class="tab-panel p-6 hidden">
                        <h3 class="text-lg font-semibold mb-3 dark:text-white">Execute Command</h3>
                        <form id="exec-form" onsubmit="executeCommand(event)">
                            <div class="flex gap-2 mb-4">
                                <input type="text" name="command" id="exec-command" 
                                       class="flex-1 px-3 py-2 border rounded-lg font-mono dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                       placeholder="ls -la /home" required>
                                <button type="submit" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-lg">
                                    Execute
                                </button>
                            </div>
                        </form>
                        <div class="bg-gray-900 rounded-lg p-4 h-64 overflow-auto">
                            <pre id="exec-output" class="text-green-400 terminal-output">Ready for commands...</pre>
                        </div>
                    </div>

                    <!-- Logs Tab -->
                    <div id="panel-logs" class="tab-panel p-6 hidden">
                        <h3 class="text-lg font-semibold mb-3 dark:text-white">Container Logs</h3>
                        <div class="bg-gray-900 rounded-lg p-4 h-96 overflow-auto">
                            <pre class="text-green-400 terminal-output"><?= htmlspecialchars($logs ?? 'No logs available') ?></pre>
                        </div>
                    </div>

                    <!-- Config Tab -->
                    <div id="panel-config" class="tab-panel p-6 hidden">
                        <h3 class="text-lg font-semibold mb-3 dark:text-white">Container Configuration</h3>
                        <?php if (!empty($container['config'])): ?>
                        <div class="bg-gray-100 dark:bg-gray-900 rounded-lg p-4 overflow-auto max-h-96">
                            <pre class="text-sm dark:text-gray-300"><?= htmlspecialchars(json_encode($container['config'], JSON_PRETTY_PRINT)) ?></pre>
                        </div>
                        <?php else: ?>
                        <p class="text-gray-500">No configuration data available.</p>
                        <?php endif; ?>

                        <?php if (!empty($container['devices'])): ?>
                        <h3 class="text-lg font-semibold mt-6 mb-3 dark:text-white">Devices</h3>
                        <div class="bg-gray-100 dark:bg-gray-900 rounded-lg p-4 overflow-auto max-h-96">
                            <pre class="text-sm dark:text-gray-300"><?= htmlspecialchars(json_encode($container['devices'], JSON_PRETTY_PRINT)) ?></pre>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php include __DIR__ . '/../parts/footer.php'; ?>
        </div>
    </div>

    <script>
        const containerName = '<?= htmlspecialchars($container['name'] ?? '') ?>';
        const csrfToken = '<?= htmlspecialchars($csrf_token ?? '') ?>';

        function showTab(tabName) {
            // Hide all panels
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
            // Deactivate all tabs
            document.querySelectorAll('.tab-btn').forEach(t => {
                t.classList.remove('border-violet-600', 'text-violet-600');
                t.classList.add('border-transparent', 'text-gray-500');
            });
            // Show selected panel
            document.getElementById('panel-' + tabName).classList.remove('hidden');
            // Activate selected tab
            const tab = document.getElementById('tab-' + tabName);
            tab.classList.remove('border-transparent', 'text-gray-500');
            tab.classList.add('border-violet-600', 'text-violet-600');
        }

        async function executeCommand(e) {
            e.preventDefault();
            const command = document.getElementById('exec-command').value;
            const output = document.getElementById('exec-output');
            
            output.textContent = 'Executing: ' + command + '\n\n';
            
            try {
                const response = await fetch('/admin/lxcs/api/exec', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ container: containerName, command, csrf_token: csrfToken })
                });
                const data = await response.json();
                if (data.success) {
                    output.textContent += data.output || '(no output)';
                } else {
                    output.textContent += 'Error: ' + (data.error || 'Unknown error');
                }
            } catch (err) {
                output.textContent += 'Error: ' + err.message;
            }
        }
    </script>
</body>
</html>
