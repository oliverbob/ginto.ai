<?php
// Admin — LXD Container Manager
$title = 'LXD Containers';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin — LXD Containers</title>
    <link rel="stylesheet" href="/assets/css/tailwind.css">
    <style>
        table td, table th { padding: 8px 12px; }
        .status-running { color: #22c55e; }
        .status-stopped { color: #6b7280; }
        .status-frozen { color: #3b82f6; }
        .status-error { color: #ef4444; }
        .pulse { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .terminal-output { font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace; font-size: 12px; }
    </style>
</head>
<body class="min-h-screen bg-white dark:bg-gray-900">
    <div class="min-h-screen bg-white dark:bg-gray-900">
        <?php include __DIR__ . '/../parts/sidebar.php'; ?>
        <div id="main-content" class="lg:pl-64">
            <?php include __DIR__ . '/../parts/header.php'; ?>

            <div class="p-6 max-w-7xl mx-auto">
                <!-- Host Stats Card -->
                <div class="bg-gradient-to-r from-violet-600 to-purple-600 rounded-lg shadow p-6 mb-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold opacity-90">LXD Host</h2>
                            <div class="text-3xl font-bold" id="host-container-count"><?= count($containers ?? []) ?></div>
                            <div class="text-sm opacity-75">containers</div>
                        </div>
                        <div class="grid grid-cols-3 gap-8 text-center">
                            <div>
                                <div class="text-2xl font-bold" id="running-count"><?= count(array_filter($containers ?? [], fn($c) => ($c['status'] ?? '') === 'Running')) ?></div>
                                <div class="text-sm opacity-75">Running</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold" id="stopped-count"><?= count(array_filter($containers ?? [], fn($c) => ($c['status'] ?? '') === 'Stopped')) ?></div>
                                <div class="text-sm opacity-75">Stopped</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold" id="host-cpu">--</div>
                                <div class="text-sm opacity-75">Host CPU</div>
                            </div>
                        </div>
                        <button onclick="refreshContainers()" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition">
                            <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Refresh
                        </button>
                    </div>
                </div>

                <!-- Actions Bar -->
                <div class="flex items-center justify-between mb-4">
                    <h1 class="text-2xl font-semibold dark:text-white">LXD Containers</h1>
                    <div class="flex gap-2">
                        <button onclick="cleanupVisitorSandboxes()" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg transition flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Cleanup Visitor Sandboxes
                        </button>
                        <button onclick="openCreateModal()" class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-lg transition flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            New Container
                        </button>
                    </div>
                </div>

                <!-- Flash Messages -->
                <?php if (!empty($flash_success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?= htmlspecialchars($flash_success) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($flash_error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= htmlspecialchars($flash_error) ?>
                </div>
                <?php endif; ?>

                <!-- Containers Table -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm text-left">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-3">Container</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">IP Address</th>
                                    <th class="px-4 py-3">CPU</th>
                                    <th class="px-4 py-3">Memory</th>
                                    <th class="px-4 py-3">Disk</th>
                                    <th class="px-4 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="containers-tbody" class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach (($containers ?? []) as $c): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50" data-container="<?= htmlspecialchars($c['name']) ?>">
                                    <td class="px-4 py-3">
                                        <a href="/admin/lxcs/<?= htmlspecialchars($c['name']) ?>" class="font-mono text-violet-600 dark:text-violet-400 hover:underline">
                                            <?= htmlspecialchars($c['name']) ?>
                                        </a>
                                        <?php if (str_starts_with($c['name'], 'sandbox-')): ?>
                                        <span class="ml-2 text-xs bg-violet-100 text-violet-700 px-2 py-0.5 rounded">sandbox</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="status-<?= strtolower($c['status'] ?? 'unknown') ?> font-medium flex items-center gap-1">
                                            <?php if (($c['status'] ?? '') === 'Running'): ?>
                                            <span class="w-2 h-2 bg-green-500 rounded-full pulse"></span>
                                            <?php else: ?>
                                            <span class="w-2 h-2 bg-gray-400 rounded-full"></span>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($c['status'] ?? 'Unknown') ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs">
                                        <?= htmlspecialchars($c['ip'] ?? '-') ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if (!empty($c['cpu'])): ?>
                                        <div class="w-16 bg-gray-200 rounded-full h-2">
                                            <div class="bg-violet-600 h-2 rounded-full" style="width: <?= min(100, floatval($c['cpu'])) ?>%"></div>
                                        </div>
                                        <span class="text-xs text-gray-500"><?= $c['cpu'] ?>%</span>
                                        <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if (!empty($c['memory'])): ?>
                                        <span class="text-xs"><?= $c['memory'] ?></span>
                                        <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if (!empty($c['disk'])): ?>
                                        <span class="text-xs"><?= $c['disk'] ?></span>
                                        <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-1">
                                            <?php if (($c['status'] ?? '') === 'Running'): ?>
                                            <form method="post" action="/admin/lxcs/<?= htmlspecialchars($c['name']) ?>/stop" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                                <button type="submit" class="p-1.5 hover:bg-gray-200 dark:hover:bg-gray-600 rounded" title="Stop">
                                                    <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/>
                                                    </svg>
                                                </button>
                                            </form>
                                            <form method="post" action="/admin/lxcs/<?= htmlspecialchars($c['name']) ?>/restart" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                                <button type="submit" class="p-1.5 hover:bg-gray-200 dark:hover:bg-gray-600 rounded" title="Restart">
                                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                    </svg>
                                                </button>
                                            </form>
                                            <button onclick="openExecModal('<?= htmlspecialchars($c['name']) ?>')" class="p-1.5 hover:bg-gray-200 dark:hover:bg-gray-600 rounded" title="Execute Command">
                                                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                            </button>
                                            <?php else: ?>
                                            <form method="post" action="/admin/lxcs/<?= htmlspecialchars($c['name']) ?>/start" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                                <button type="submit" class="p-1.5 hover:bg-gray-200 dark:hover:bg-gray-600 rounded" title="Start">
                                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <a href="/admin/lxcs/<?= htmlspecialchars($c['name']) ?>" class="p-1.5 hover:bg-gray-200 dark:hover:bg-gray-600 rounded" title="View Details">
                                                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </a>
                                            <form method="post" action="/admin/lxcs/<?= htmlspecialchars($c['name']) ?>/delete" class="inline" onsubmit="return confirm('Delete container <?= htmlspecialchars($c['name']) ?>? This cannot be undone.');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                                <button type="submit" class="p-1.5 hover:bg-red-100 dark:hover:bg-red-900/30 rounded" title="Delete">
                                                    <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($containers)): ?>
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                        No LXD containers found. Create one to get started.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php include __DIR__ . '/../parts/footer.php'; ?>
        </div>
    </div>

    <!-- Create Container Modal -->
    <div id="create-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 dark:text-white">Create New Container</h3>
                <form method="post" action="/admin/lxcs/create">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1 dark:text-gray-300">Container Name</label>
                        <input type="text" name="name" required pattern="[a-zA-Z0-9_-]+" 
                               class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                               placeholder="my-container">
                        <p class="text-xs text-gray-500 mt-1">Alphanumeric, dashes, and underscores only</p>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1 dark:text-gray-300">Base Image</label>
                        <select name="image" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="ginto">ginto (Alpine sandbox)</option>
                            <option value="alpine/3.20">Alpine 3.20</option>
                            <option value="ubuntu:22.04">Ubuntu 22.04</option>
                            <option value="debian/12">Debian 12</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeCreateModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg dark:text-gray-300 dark:hover:bg-gray-700">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-lg">
                            Create Container
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Exec Command Modal -->
    <div id="exec-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full mx-4">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4 dark:text-white">Execute Command in <span id="exec-container-name" class="text-violet-600"></span></h3>
                <form id="exec-form" onsubmit="executeCommand(event)">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                    <input type="hidden" name="container" id="exec-container-input">
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1 dark:text-gray-300">Command</label>
                        <input type="text" name="command" id="exec-command-input" required 
                               class="w-full px-3 py-2 border rounded-lg font-mono dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                               placeholder="ls -la /home">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1 dark:text-gray-300">Output</label>
                        <pre id="exec-output" class="bg-gray-900 text-green-400 p-4 rounded-lg h-48 overflow-auto terminal-output">
Waiting for command...</pre>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeExecModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg dark:text-gray-300 dark:hover:bg-gray-700">
                            Close
                        </button>
                        <button type="submit" class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-lg">
                            Execute
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openCreateModal() {
            document.getElementById('create-modal').classList.remove('hidden');
            document.getElementById('create-modal').classList.add('flex');
        }
        function closeCreateModal() {
            document.getElementById('create-modal').classList.add('hidden');
            document.getElementById('create-modal').classList.remove('flex');
        }
        function openExecModal(containerName) {
            document.getElementById('exec-container-name').textContent = containerName;
            document.getElementById('exec-container-input').value = containerName;
            document.getElementById('exec-output').textContent = 'Waiting for command...';
            document.getElementById('exec-modal').classList.remove('hidden');
            document.getElementById('exec-modal').classList.add('flex');
            document.getElementById('exec-command-input').focus();
        }
        function closeExecModal() {
            document.getElementById('exec-modal').classList.add('hidden');
            document.getElementById('exec-modal').classList.remove('flex');
        }

        // Execute command via API
        async function executeCommand(e) {
            e.preventDefault();
            const container = document.getElementById('exec-container-input').value;
            const command = document.getElementById('exec-command-input').value;
            const output = document.getElementById('exec-output');
            const csrfToken = document.querySelector('input[name="csrf_token"]').value;
            
            output.textContent = 'Executing...';
            
            try {
                const response = await fetch('/admin/lxcs/api/exec', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ container, command, csrf_token: csrfToken })
                });
                const data = await response.json();
                if (data.success) {
                    output.textContent = data.output || '(no output)';
                } else {
                    output.textContent = 'Error: ' + (data.error || 'Unknown error');
                }
            } catch (err) {
                output.textContent = 'Error: ' + err.message;
            }
        }

        // Refresh containers list
        async function refreshContainers() {
            try {
                const response = await fetch('/admin/lxcs/api/list');
                const data = await response.json();
                if (data.success) {
                    location.reload();
                }
            } catch (err) {
                console.error('Refresh failed:', err);
            }
        }

        // Cleanup visitor sandboxes
        async function cleanupVisitorSandboxes() {
            if (!confirm('This will delete all STOPPED visitor sandboxes (containers not associated with logged-in users). Continue?')) {
                return;
            }
            const csrfToken = document.querySelector('input[name="csrf_token"]').value;
            
            try {
                const response = await fetch('/admin/lxcs/cleanup', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'csrf_token=' + encodeURIComponent(csrfToken)
                });
                const data = await response.json();
                if (data.success) {
                    const deletedCount = Array.isArray(data.deleted) ? data.deleted.length : data.deleted;
                    const errorCount = Array.isArray(data.errors) ? data.errors.length : 0;
                    alert('Cleanup complete!\n\nDeleted: ' + deletedCount + ' sandboxes\nFailed: ' + errorCount);
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            } catch (err) {
                alert('Error: ' + err.message);
            }
        }

        // Close modals on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeCreateModal();
                closeExecModal();
            }
        });

        // Close modals when clicking outside
        document.getElementById('create-modal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeCreateModal();
        });
        document.getElementById('exec-modal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeExecModal();
        });
    </script>
</body>
</html>
