<?php
// Admin Users dashboard (overview) — wrapped with admin layout parts
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin — Users Dashboard</title>
    <link rel="stylesheet" href="/assets/css/tailwind.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="min-h-screen bg-white dark:bg-gray-900">
    <div class="min-h-screen bg-white dark:bg-gray-900">
        <?php include __DIR__ . '/../parts/sidebar.php'; ?>
        <div id="main-content" class="lg:pl-64">
            <?php include __DIR__ . '/../parts/header.php'; ?>

            <main class="p-6 max-w-6xl mx-auto">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h1 class="text-2xl font-semibold">Users — Overview</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400">A quick look at your users and referral performance.</p>
                        </div>
                        <div>
                            <?php include_once __DIR__ . '/../parts/admin_button.php'; admin_button('Users dashboard', '/admin/users', ['variant' => 'primary']); ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-white dark:bg-gray-900/10 rounded-xl p-4 border dark:border-gray-700 shadow-sm">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total users</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($stats['total_users'] ?? 0) ?></p>
                        </div>
                        <div class="bg-white dark:bg-gray-900/10 rounded-xl p-4 border dark:border-gray-700 shadow-sm">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Active users (30d)</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($stats['active_users'] ?? 0) ?></p>
                        </div>
                        <div class="bg-white dark:bg-gray-900/10 rounded-xl p-4 border dark:border-gray-700 shadow-sm">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Admin accounts</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($stats['admin_users'] ?? 0) ?></p>
                        </div>
                        <div class="bg-white dark:bg-gray-900/10 rounded-xl p-4 border dark:border-gray-700 shadow-sm">
                            <p class="text-sm text-gray-500 dark:text-gray-400">New last 30 days</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($stats['recent_users'] ?? 0) ?></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Registration trend (30d)</h2>
                                <span class="text-sm text-gray-500 dark:text-gray-400">Daily</span>
                            </div>
                            <div class="h-64">
                                <canvas id="usersRegistrationChart"></canvas>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Top referrers</h2>
                            <ul class="space-y-2">
                                <?php foreach (($top_referrers ?? []) as $r): ?>
                                    <li class="flex items-center justify-between border p-2 rounded">
                                        <div>
                                            <div class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($r['fullname'] ?? $r['username'] ?? '') ?></div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">@<?= htmlspecialchars($r['username'] ?? '') ?></div>
                                        </div>
                                        <div class="text-sm text-gray-700 dark:text-gray-300 text-right">
                                            <div><?= intval($r['referral_count'] ?? 0) ?> referrals</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">value: <?= htmlspecialchars($r['total_referred_value'] ?? '0') ?></div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                </div>
            </main>

            <?php include __DIR__ . '/../parts/footer.php'; ?>
        </div>
    </div>

    <script>
        (function(){
            const labels = <?= json_encode(array_column($registration_trends ?? [], 'date')) ?>;
            const data = <?= json_encode(array_map(function($i){return intval($i['count']);}, ($registration_trends ?? []))) ?>;

            const ctx = document.getElementById('usersRegistrationChart');
            if (ctx) {
                function cssVarAccent() {
                    const curPath = (window.location && window.location.pathname) ? window.location.pathname : '';
                    // prefer a per-path or admin palette if available
                    if (window._gintoPalette) {
                        if (curPath && window._gintoPalette[curPath] && window._gintoPalette[curPath].accent) return window._gintoPalette[curPath].accent;
                        if (window._gintoPalette['/admin'] && window._gintoPalette['/admin'].accent) return window._gintoPalette['/admin'].accent;
                    }
                    const computed = getComputedStyle(document.documentElement).getPropertyValue('--dashboard-accent') || getComputedStyle(document.documentElement).getPropertyValue('--accent');
                    return (computed || '').trim() || '#6366f1';
                }
                function hexToRgba(hex, alpha) {
                    if (!hex) return null;
                    hex = hex.trim();
                    if (hex.indexOf('#') === 0) hex = hex.slice(1);
                    if (hex.length === 3) hex = hex.split('').map(c => c + c).join('');
                    const r = parseInt(hex.slice(0,2), 16);
                    const g = parseInt(hex.slice(2,4), 16);
                    const b = parseInt(hex.slice(4,6), 16);
                    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
                }

                const accentColor = cssVarAccent();
                const bgColor = hexToRgba(accentColor, 0.08) || 'rgba(99,102,241,0.08)';
                const borderColor = accentColor || 'rgba(99,102,241,0.9)';

                const usersChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Registrations',
                            data: data,
                            fill: true,
                            backgroundColor: bgColor,
                            borderColor: borderColor,
                            tension: 0.3,
                            pointRadius: 2,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true } }
                    }
                });

                // update function for palette/theme changes
                function updateUsersChartColors(evt) {
                    try {
                        // If an explicit palette event arrives for this path use it
                        const curPath = (window.location && window.location.pathname) ? window.location.pathname : '';
                        let accent = null;
                        if (evt && evt.detail && evt.detail.palette) {
                            const d = evt.detail;
                            if (d.key && curPath.indexOf(d.key) === 0) accent = (d.palette && d.palette.accent) || d.accent;
                            else if (d.path && curPath.indexOf(d.path) === 0) accent = (d.palette && d.palette.accent) || d.accent;
                        }
                        if (!accent) accent = cssVarAccent();
                        const bg = hexToRgba(accent, 0.08) || 'rgba(99,102,241,0.08)';
                        const border = accent || 'rgba(99,102,241,0.9)';
                        usersChart.data.datasets[0].backgroundColor = bg;
                        usersChart.data.datasets[0].borderColor = border;
                        usersChart.update();
                    } catch (e) { /* ignore */ }
                }
                try { window.addEventListener('site-palette-changed', updateUsersChartColors); } catch(_){}
                try { window.addEventListener('site-theme-changed', updateUsersChartColors); } catch(_){}
            }
        })();
    </script>
</body>
</html>
