<?php
// Network Dashboard - LXD Container Manager
$title = 'Network Dashboard';
?>
<?php $htmlDark = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? ' class="dark"' : ''; ?>
<!DOCTYPE html>
<html lang="en"<?php echo $htmlDark; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../parts/favicons.php'; ?>
    <title>Network Dashboard - Ginto Admin</title>
    <script>
        // Ensure the correct theme class is applied before CSS loads so we don't flash
        (function () {
            try {
                var saved = null;
                try { saved = localStorage.getItem('theme'); } catch (e) { saved = null; }
                if (!saved) {
                    var m = document.cookie.match(/(?:^|; )theme=(dark|light)(?:;|$)/);
                    saved = m ? m[1] : null;
                }
                if (saved === 'dark') {
                    document.documentElement.classList.add('dark');
                } else if (saved === 'light') {
                    document.documentElement.classList.remove('dark');
                }
            } catch (err) {}
        })();
    </script>
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/tailwind.css">
    <link rel="stylesheet" href="/assets/css/dark-fallback.css">
    <style>
        /* Network card buttons - light mode defaults, dark mode via .dark class */
        .network-card-btn {
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            background: #f3f4f6;
            cursor: pointer;
            text-align: left;
            transition: all 0.15s;
        }
        .dark .network-card-btn {
            border-color: #374151;
            background: #111827;
        }
        .network-card-btn:hover { border-color: #9ca3af; }
        .dark .network-card-btn:hover { border-color: #4b5563; }
        
        .network-card-btn .label { font-weight: 600; font-size: 0.875rem; }
        .network-card-btn .sublabel { font-size: 0.7rem; }
        .network-card-btn .desc { font-size: 0.65rem; margin-top: 0.125rem; }
        
        table td, table th { padding: 8px 12px; }
        .dark #containers-tbody tr:hover { background-color: #1e293b !important; }
        .dark #containers-tbody { border-color: rgba(255,255,255,0.08) !important; }
        .dark #containers-tbody tr { border-color: rgba(255,255,255,0.08) !important; }
        .status-running { color: #22c55e; }
        .status-stopped { color: #6b7280; }
        .status-frozen { color: #3b82f6; }
        .status-error { color: #ef4444; }
        .pulse { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .terminal-output { font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace; font-size: 12px; }
    </style>
</head>
<body class="min-h-screen">
    <div class="min-h-screen">
        <?php include __DIR__ . '/../parts/sidebar.php'; ?>
        <div id="main-content" class="lg:pl-64">
            <?php include __DIR__ . '/../parts/header.php'; ?>

            <div class="p-3 sm:p-6 max-w-7xl mx-auto">
                <!-- Host Stats Card -->
                <div class="network-card-btn mb-4 sm:mb-6" style="padding: 1rem 1.5rem;">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:justify-between">
                        <!-- Container Count -->
                        <div class="flex items-center gap-4 sm:block">
                            <div>
                                <h2 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white">LXD Host</h2>
                                <div class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white" id="host-container-count"><?= count($containers ?? []) ?></div>
                                <div class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">containers</div>
                            </div>
                            <!-- Refresh button on mobile -->
                            <button onclick="refreshContainers()" class="sm:hidden bg-slate-600 hover:bg-slate-500 px-3 py-2 transition ml-auto text-slate-200" style="border-radius: 8px;">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                            </button>
                        </div>
                        <!-- Stats Grid -->
                        <div class="grid grid-cols-3 gap-4 sm:gap-8 text-center">
                            <div>
                                <div class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white" id="running-count"><?= count(array_filter($containers ?? [], fn($c) => strtolower($c['status'] ?? '') === 'running')) ?></div>
                                <div class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">Running</div>
                            </div>
                            <div>
                                <div class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white" id="stopped-count"><?= count(array_filter($containers ?? [], fn($c) => strtolower($c['status'] ?? '') === 'stopped')) ?></div>
                                <div class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">Stopped</div>
                            </div>
                            <div>
                                <div class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white" id="host-memory"><?= ($stats['memory_used'] ?? '--') . ' / ' . ($stats['memory_total'] ?? '--') ?></div>
                                <div class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">Memory</div>
                            </div>
                        </div>
                        <!-- Refresh button on desktop -->
                        <button onclick="refreshContainers()" class="hidden sm:flex bg-slate-600 hover:bg-slate-500 px-4 py-2 transition items-center gap-2 text-slate-200" style="border-radius: 8px;">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Refresh
                        </button>
                    </div>
                </div>

                <!-- Network Mode Card -->
                <?php $net = $networkInfo ?? ['mode' => 'bridge', 'ipRange' => '16.7M', 'interface' => 'lxdbr0', 'nat' => false, 'macvlan' => ['ready' => false, 'dummy' => false, 'shim' => false, 'network' => false], 'ipvlan' => ['ready' => false]]; ?>
                <div class="bg-white dark:bg-gray-800 shadow p-3 sm:p-4 mb-4 sm:mb-6" id="network-card" style="border-radius: 8px;">
                    <!-- Header -->
                    <div class="flex items-center gap-3 mb-4">
                        <div style="padding: 0.625rem; background: rgba(59,130,246,0.15); border-radius: 8px;">
                            <svg class="w-5 h-5" style="color: #3b82f6;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-gray-900 dark:text-white" style="font-weight: 600; font-size: 1rem; margin: 0;">Network Mode</h3>
                            <p class="text-gray-500 dark:text-gray-400" style="font-size: 0.75rem; margin: 0;">Select how containers connect to the network</p>
                        </div>
                    </div>
                    
                    <!-- Network Mode Cards Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3">
                        <!-- Bridge -->
                        <button onclick="setNetworkMode('bridge')" id="btn-bridge" class="network-card-btn"
                                style="<?= $net['mode'] === 'bridge' ? 'border-color: #3b82f6; background: rgba(59,130,246,0.15);' : '' ?>">
                            <div class="flex items-center gap-2 mb-1">
                                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                <span class="label text-gray-700 dark:text-gray-200" style="<?= $net['mode'] === 'bridge' ? 'color: #3b82f6;' : '' ?>">Bridge</span>
                            </div>
                            <div class="sublabel text-gray-500 dark:text-gray-400">16.7M IPs</div>
                            <div class="desc text-gray-400 dark:text-gray-500">lxdbr0</div>
                        </button>
                        
                        <!-- NAT -->
                        <button onclick="setNetworkMode('nat')" id="btn-nat" class="network-card-btn"
                                style="<?= $net['mode'] === 'nat' ? 'border-color: #f97316; background: rgba(249,115,22,0.15);' : '' ?>">
                            <div class="flex items-center gap-2 mb-1">
                                <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                </svg>
                                <span class="label text-gray-700 dark:text-gray-200" style="<?= $net['mode'] === 'nat' ? 'color: #f97316;' : '' ?>">NAT</span>
                            </div>
                            <div class="sublabel text-gray-500 dark:text-gray-400">16.7M IPs</div>
                            <div class="desc text-gray-400 dark:text-gray-500">Outbound via host</div>
                        </button>
                        
                        <!-- Macvlan -->
                        <button onclick="setNetworkMode('macvlan')" id="btn-macvlan" class="network-card-btn"
                                style="<?= $net['mode'] === 'macvlan' ? 'border-color: #22c55e; background: rgba(34,197,94,0.15);' : '' ?>">
                            <div class="flex items-center gap-2 mb-1">
                                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="label text-gray-700 dark:text-gray-200" style="<?= $net['mode'] === 'macvlan' ? 'color: #22c55e;' : '' ?>">Macvlan</span>
                            </div>
                            <div class="sublabel text-gray-500 dark:text-gray-400">4.3B IPs · L2</div>
                            <div class="desc text-gray-400 dark:text-gray-500">Real network</div>
                        </button>
                        
                        <!-- IPvlan -->
                        <button onclick="setNetworkMode('ipvlan')" id="btn-ipvlan" class="network-card-btn"
                                style="<?= $net['mode'] === 'ipvlan' ? 'border-color: #a855f7; background: rgba(168,85,247,0.15);' : '' ?>">
                            <div class="flex items-center gap-2 mb-1">
                                <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                                </svg>
                                <span class="label text-gray-700 dark:text-gray-200" style="<?= $net['mode'] === 'ipvlan' ? 'color: #a855f7;' : '' ?>">IPvlan</span>
                            </div>
                            <div class="sublabel text-gray-500 dark:text-gray-400">4.3B IPs · L3</div>
                            <div class="desc text-gray-400 dark:text-gray-500">Shared MAC</div>
                        </button>
                    </div>
                    
                    <!-- Loading Indicator -->
                    <div id="network-loading" class="hidden mt-4 p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                        <div class="flex items-center gap-2 text-blue-700 dark:text-blue-400">
                            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span id="network-loading-text">Configuring network...</span>
                        </div>
                    </div>
                    
                    <!-- Status Output -->
                    <div id="network-output" class="hidden mt-4 p-4 bg-gray-900 border border-gray-700" style="border-radius: 8px;">
                        <pre class="text-sm font-mono whitespace-pre-wrap" style="color: #d1d5db;" id="network-output-text"></pre>
                    </div>
                </div>

                <!-- Actions Bar -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <h1 class="text-xl sm:text-2xl font-semibold dark:text-white">LXD Containers</h1>
                    <div class="flex gap-2 flex-wrap">
                        <button onclick="cleanupVisitorSandboxes()" class="px-3 sm:px-4 py-2 rounded-lg transition flex items-center gap-2 text-sm bg-gray-200 hover:bg-gray-300 text-gray-700 dark:bg-orange-600 dark:hover:bg-orange-700 dark:text-white">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            <span class="hidden xs:inline">Cleanup</span>
                            <span class="hidden sm:inline"> Sandboxes</span>
                        </button>
                        <button onclick="openCreateModal()" class="px-3 sm:px-4 py-2 rounded-lg transition flex items-center gap-2 text-sm bg-gray-200 hover:bg-gray-300 text-gray-700 dark:bg-violet-600 dark:hover:bg-violet-700 dark:text-white">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            <span>New</span>
                            <span class="hidden sm:inline"> Container</span>
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
                        <table class="min-w-full text-xs sm:text-sm text-left">
                            <thead class="bg-gray-100 dark:bg-gray-700">
                                <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                                    <th class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">Container</th>
                                    <th class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">Status</th>
                                    <th class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">IP</th>
                                    <th class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap hidden md:table-cell">CPU</th>
                                    <th class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap hidden md:table-cell">Memory</th>
                                    <th class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap hidden lg:table-cell">Disk</th>
                                    <th class="px-3 sm:px-4 py-2 sm:py-3 whitespace-nowrap">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="containers-tbody" class="divide-y divide-gray-200 dark:divide-gray-800">
                                <?php foreach (($containers ?? []) as $c): ?>
                                <tr class="hover:bg-gray-100 dark:hover:bg-[#151f2e]" data-container="<?= htmlspecialchars($c['name']) ?>">
                                    <td class="px-3 sm:px-4 py-2 sm:py-3">
                                        <a href="/admin/network/<?= htmlspecialchars($c['name']) ?>" class="font-mono text-violet-600 dark:text-violet-400 hover:underline text-xs sm:text-sm">
                                            <?= htmlspecialchars($c['name']) ?>
                                        </a>
                                        <?php if (isset($c['name']) && is_string($c['name']) && str_starts_with($c['name'], 'sandbox-')): ?>
                                        <span class="ml-1 sm:ml-2 text-xs bg-violet-100 text-violet-700 px-1.5 sm:px-2 py-0.5 rounded hidden sm:inline">sandbox</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3">
                                        <span class="status-<?= strtolower($c['status'] ?? 'unknown') ?> font-medium flex items-center gap-1 text-xs sm:text-sm">
                                            <?php if (($c['status'] ?? '') === 'Running'): ?>
                                            <span class="w-2 h-2 bg-green-500 rounded-full pulse"></span>
                                            <?php else: ?>
                                            <span class="w-2 h-2 bg-gray-400 rounded-full"></span>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($c['status'] ?? 'Unknown') ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 font-mono text-xs">
                                        <?= htmlspecialchars($c['ip'] ?? '-') ?>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 hidden md:table-cell">
                                        <?php if (!empty($c['cpu'])): ?>
                                        <div class="w-16 bg-gray-200 rounded-full h-2">
                                            <div class="bg-violet-600 h-2 rounded-full" style="width: <?= min(100, floatval($c['cpu'])) ?>%"></div>
                                        </div>
                                        <span class="text-xs text-gray-500"><?= $c['cpu'] ?>%</span>
                                        <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 hidden md:table-cell">
                                        <?php if (!empty($c['memory'])): ?>
                                        <span class="text-xs"><?= $c['memory'] ?></span>
                                        <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3 hidden lg:table-cell">
                                        <?php if (!empty($c['disk'])): ?>
                                        <span class="text-xs"><?= $c['disk'] ?></span>
                                        <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 sm:px-4 py-2 sm:py-3">
                                        <div class="flex items-center gap-1">
                                            <?php if (($c['status'] ?? '') === 'Running'): ?>
                                            <form method="post" action="/admin/network/<?= htmlspecialchars($c['name']) ?>/stop" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                                <button type="submit" class="p-1.5 hover:bg-gray-200 dark:hover:bg-gray-600 rounded" title="Stop">
                                                    <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/>
                                                    </svg>
                                                </button>
                                            </form>
                                            <form method="post" action="/admin/network/<?= htmlspecialchars($c['name']) ?>/restart" class="inline">
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
                                            <form method="post" action="/admin/network/<?= htmlspecialchars($c['name']) ?>/start" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                                <button type="submit" class="p-1.5 hover:bg-gray-200 dark:hover:bg-gray-600 rounded" title="Start">
                                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <a href="/admin/network/<?= htmlspecialchars($c['name']) ?>" class="p-1.5 hover:bg-gray-200 dark:hover:bg-gray-600 rounded" title="View Details">
                                                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </a>
                                            <form method="post" action="/admin/network/<?= htmlspecialchars($c['name']) ?>/delete" class="inline" onsubmit="return handleDeleteContainer(event, '<?= htmlspecialchars($c['name']) ?>');">
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
                                    <td colspan="7" class="px-3 sm:px-4 py-6 sm:py-8 text-center text-gray-500 text-sm">
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
    <div id="create-modal" class="fixed inset-0 hidden items-center justify-center z-50 p-3 sm:p-4" style="background: rgba(0,0,0,0.75);">
        <div style="background: #1f2937; border: 1px solid #374151; border-radius: 8px; max-width: 28rem; width: 100%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);">
            <div class="p-4 sm:p-6">
                <h3 style="font-size: 1.125rem; font-weight: 600; color: #fff; margin-bottom: 1rem;">Create New Container</h3>
                <form method="post" action="/admin/network/create">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #d1d5db; margin-bottom: 0.25rem;">Container Name</label>
                        <input type="text" name="name" required pattern="[a-zA-Z0-9_-]+" 
                               style="width: 100%; padding: 0.5rem 0.75rem; background: #374151; border: 1px solid #4b5563; border-radius: 6px; color: #fff; font-size: 0.875rem;"
                               placeholder="my-container">
                        <p style="font-size: 0.75rem; color: #9ca3af; margin-top: 0.25rem;">Alphanumeric, dashes, and underscores only</p>
                    </div>
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #d1d5db; margin-bottom: 0.25rem;">Base Image</label>
                        <select name="image" style="width: 100%; padding: 0.5rem 0.75rem; background: #374151; border: 1px solid #4b5563; border-radius: 6px; color: #fff; font-size: 0.875rem;">
                            <option value="ginto">ginto (Alpine sandbox)</option>
                            <option value="alpine/3.20">Alpine 3.20</option>
                            <option value="ubuntu:22.04">Ubuntu 22.04</option>
                            <option value="debian/12">Debian 12</option>
                        </select>
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid #374151; padding-top: 1rem;">
                        <button type="button" onclick="closeCreateModal()" style="padding: 0.5rem 1rem; background: #374151; color: #e5e7eb; border: none; border-radius: 6px; cursor: pointer; font-size: 0.875rem; font-weight: 500;">
                            Cancel
                        </button>
                        <button type="submit" style="padding: 0.5rem 1rem; background: #7c3aed; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 0.875rem; font-weight: 500; display: flex; align-items: center; gap: 0.5rem;">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Create Container
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Exec Command Modal -->
    <div id="exec-modal" class="fixed inset-0 hidden items-center justify-center z-50 p-3 sm:p-4" style="background: rgba(0,0,0,0.75);">
        <div style="background: #1f2937; border: 1px solid #374151; border-radius: 8px; max-width: 40rem; width: 100%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);">
            <div class="p-4 sm:p-6">
                <h3 style="font-size: 1.125rem; font-weight: 600; color: #fff; margin-bottom: 1rem;">Execute Command in <span id="exec-container-name" style="color: #a78bfa;"></span></h3>
                <form id="exec-form" onsubmit="executeCommand(event)">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                    <input type="hidden" name="container" id="exec-container-input">
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #d1d5db; margin-bottom: 0.25rem;">Command</label>
                        <input type="text" name="command" id="exec-command-input" required 
                               style="width: 100%; padding: 0.5rem 0.75rem; background: #374151; border: 1px solid #4b5563; border-radius: 6px; color: #fff; font-family: monospace; font-size: 0.875rem;"
                               placeholder="ls -la /home">
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #d1d5db; margin-bottom: 0.25rem;">Output</label>
                        <pre id="exec-output" style="background: #111827; color: #4ade80; padding: 1rem; border-radius: 6px; height: 12rem; overflow: auto; font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace; font-size: 0.75rem; margin: 0;">Waiting for command...</pre>
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid #374151; padding-top: 1rem;">
                        <button type="button" onclick="closeExecModal()" style="padding: 0.5rem 1rem; background: #374151; color: #e5e7eb; border: none; border-radius: 6px; cursor: pointer; font-size: 0.875rem; font-weight: 500;">
                            Close
                        </button>
                        <button type="submit" style="padding: 0.5rem 1rem; background: #7c3aed; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 0.875rem; font-weight: 500; display: flex; align-items: center; gap: 0.5rem;">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
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
                const response = await fetch('/admin/network/api/exec', {
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

        // Handle delete container with confirmation modal
        let pendingDeleteForm = null;
        async function handleDeleteContainer(event, containerName) {
            event.preventDefault();
            pendingDeleteForm = event.target;
            
            const confirmed = await showConfirmModal({
                title: 'Delete Container',
                subtitle: containerName,
                message: 'This action cannot be undone. All data in the container will be permanently lost.',
                confirmText: 'Delete',
                confirmIcon: 'fa-trash',
                type: 'danger'
            });
            
            if (confirmed && pendingDeleteForm) {
                pendingDeleteForm.submit();
            }
            pendingDeleteForm = null;
            return false;
        }

        // Refresh containers list
        async function refreshContainers() {
            try {
                const response = await fetch('/admin/network/api/list');
                const data = await response.json();
                if (data.success) {
                    location.reload();
                }
            } catch (err) {
                console.error('Refresh failed:', err);
            }
        }

        // =============================================
        // Network Mode Management
        // =============================================
        
        let currentNetworkMode = '<?= $net['mode'] ?>';
        
        async function setNetworkMode(mode) {
            if (mode === currentNetworkMode) return;
            
            const modeLabels = {
                bridge: 'Bridge (16.7M IPs, direct routing)',
                nat: 'NAT (16.7M IPs, outbound via host)',
                macvlan: 'Macvlan (4.3B IPs, Layer 2)',
                ipvlan: 'IPvlan (4.3B IPs, Layer 3)'
            };
            
            const confirmed = await showConfirmModal({
                title: `Switch to ${mode.toUpperCase()} Mode`,
                subtitle: modeLabels[mode],
                message: 'This will update the network configuration. Existing containers may need to be restarted.',
                confirmText: 'Switch Network',
                confirmIcon: 'fa-exchange-alt',
                type: 'warning'
            });
            
            if (!confirmed) return;
            
            // Show loading
            document.getElementById('network-loading').classList.remove('hidden');
            document.getElementById('network-loading-text').textContent = `Configuring ${mode} network...`;
            document.getElementById('network-output').classList.add('hidden');
            
            // Disable buttons
            document.getElementById('btn-bridge').disabled = true;
            document.getElementById('btn-nat').disabled = true;
            document.getElementById('btn-macvlan').disabled = true;
            document.getElementById('btn-ipvlan').disabled = true;
            
            try {
                const response = await fetch('/admin/network/api/network/set', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '<?= htmlspecialchars($csrf_token ?? '') ?>'
                    },
                    body: JSON.stringify({ mode: mode })
                });
                
                const data = await response.json();
                
                // Hide loading
                document.getElementById('network-loading').classList.add('hidden');
                
                if (data.success) {
                    currentNetworkMode = mode;
                    
                    // Update UI
                    updateNetworkUI(data.network || { mode: mode });
                    
                    // Show output
                    if (data.output) {
                        document.getElementById('network-output').classList.remove('hidden');
                        document.getElementById('network-output-text').textContent = data.output;
                    }
                    
                    // Success notification
                    showNotification(`Network mode set to ${mode.toUpperCase()}`, 'success');
                } else {
                    showNotification(data.error || 'Failed to set network mode', 'error');
                    
                    // Show error output
                    if (data.output) {
                        document.getElementById('network-output').classList.remove('hidden');
                        document.getElementById('network-output-text').textContent = data.output;
                    }
                }
            } catch (err) {
                document.getElementById('network-loading').classList.add('hidden');
                showNotification('Error: ' + err.message, 'error');
            } finally {
                // Re-enable buttons
                document.getElementById('btn-bridge').disabled = false;
                document.getElementById('btn-nat').disabled = false;
                document.getElementById('btn-macvlan').disabled = false;
                document.getElementById('btn-ipvlan').disabled = false;
            }
        }
        
        function updateNetworkUI(network) {
            const mode = network.mode;
            
            // Card color configurations
            const cardConfigs = {
                bridge: { color: '#3b82f6', bg: 'rgba(59,130,246,0.15)', border: '#3b82f6' },
                nat: { color: '#f97316', bg: 'rgba(249,115,22,0.15)', border: '#f97316' },
                macvlan: { color: '#22c55e', bg: 'rgba(34,197,94,0.15)', border: '#22c55e' },
                ipvlan: { color: '#a855f7', bg: 'rgba(168,85,247,0.15)', border: '#a855f7' }
            };
            
            const modes = ['bridge', 'nat', 'macvlan', 'ipvlan'];
            
            modes.forEach(m => {
                const btn = document.getElementById('btn-' + m);
                if (!btn) return;
                
                const isActive = (m === mode);
                const cfg = cardConfigs[m];
                
                // Update card styling - use CSS var for inactive state
                if (isActive) {
                    btn.style.borderColor = cfg.border;
                    btn.style.background = cfg.bg;
                } else {
                    btn.style.borderColor = '';
                    btn.style.background = '';
                }
                
                // Update text color inside the card
                const label = btn.querySelector('.label');
                if (label) {
                    label.style.color = isActive ? cfg.color : '';
                }
            });
        }
        
        function showNotification(message, type = 'info') {
            // Remove any existing notifications
            document.querySelectorAll('.ginto-notification').forEach(n => n.remove());
            
            // Create notification element - positioned at top right, below header
            const notification = document.createElement('div');
            notification.className = 'ginto-notification';
            notification.style.cssText = `
                position: fixed;
                top: 5rem;
                right: 1.5rem;
                padding: 1rem 1.5rem;
                background: ${type === 'success' ? '#16a34a' : type === 'error' ? '#dc2626' : '#2563eb'};
                color: #fff;
                font-weight: 500;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                z-index: 100;
                transition: opacity 0.3s;
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Remove after 4 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            }, 4000);
        }

        // Cleanup visitor sandboxes
        async function cleanupVisitorSandboxes() {
            const confirmed = await showConfirmModal({
                title: 'Cleanup Visitor Sandboxes',
                subtitle: 'Remove all STOPPED visitor containers',
                message: 'This will delete containers not associated with logged-in users. This action cannot be undone.',
                confirmText: 'Cleanup',
                confirmIcon: 'fa-broom',
                type: 'danger'
            });
            
            if (!confirmed) return;
            const csrfToken = document.querySelector('input[name="csrf_token"]').value;
            
            try {
                const response = await fetch('/admin/network/cleanup', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'csrf_token=' + encodeURIComponent(csrfToken)
                });
                const data = await response.json();
                if (data.success) {
                    const deletedCount = Array.isArray(data.deleted) ? data.deleted.length : data.deleted;
                    const errorCount = Array.isArray(data.errors) ? data.errors.length : 0;
                    showNotification(`Cleanup complete! Deleted ${deletedCount} sandboxes${errorCount > 0 ? `, ${errorCount} failed` : ''}`, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('Error: ' + (data.error || 'Unknown error'), 'error');
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

    <?php include __DIR__ . '/../parts/confirm-modal.php'; ?>
</body>
</html>
