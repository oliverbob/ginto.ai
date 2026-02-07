<?php
// Dashboard template using includes for header, sidebar, body, and footer
?>
<?php $htmlDark = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? ' class="dark"' : ''; ?>
<!DOCTYPE html>
<html lang="en"<?php echo $htmlDark; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/parts/favicons.php'; ?>
    <title>Ginto AI Dashboard</title>
    <script>
        // Ensure the correct theme class is applied before CSS loads so we don't flash
        // the wrong theme. We respect localStorage first and fall back to cookie.
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
            } catch (err) { /* ignore */ }
        })();
    </script>
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/tailwind.css">
    <link rel="stylesheet" href="/assets/css/dark-fallback.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        /* Sidebar scrollbar styling for modern browsers, and ensure nav is limited by viewport */
        #sidebar nav { max-height: calc(100vh - 120px); overflow-y: auto; -webkit-overflow-scrolling: touch; }
        #sidebar nav::-webkit-scrollbar { width: 8px; }
        #sidebar nav::-webkit-scrollbar-track { background: transparent; }
        #sidebar nav::-webkit-scrollbar-thumb { background-color: rgba(156,163,175,0.5); border-radius: 9999px; }
    </style>
    <script>
        if (typeof tailwind !== 'undefined') {
            tailwind.config = { darkMode: 'class' };
        }
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const menuButton = document.getElementById('menu-button');
            const closeButton = document.getElementById('close-button');
            const htmlEl = document.documentElement;
            const toggleSidebar = () => { sidebar.classList.toggle('-translate-x-full'); };
            if (menuButton) menuButton.onclick = toggleSidebar;
            if (closeButton) closeButton.onclick = toggleSidebar;
            // Theme toggling is managed centrally by /assets/js/theme.js (keeps UI consistent
            // across multiple templates). Do not attach additional click handlers here to
            // avoid duplicate toggles.

            // --- Chart.js Live Data Logic ---
            const ctx = document.getElementById('performanceChart');
            if (ctx) {
                let performanceChart;
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

                function updateChartColors(evt) {
                    try {
                        if (!performanceChart) return;
                        const isDark = document.documentElement.classList.contains('dark');
                        // Prefer a palette for the current admin route if one exists
                        const curPath = (window.location && window.location.pathname) ? window.location.pathname : '';
                        let accent = null;
                        // If the event contains a palette and it targets this page, use it
                        if (evt && evt.detail && evt.detail.palette) {
                            const d = evt.detail;
                            if (d.key && curPath.indexOf(d.key) === 0) accent = (d.palette && d.palette.accent) || d.accent;
                            else if (d.path && curPath.indexOf(d.path) === 0) accent = (d.palette && d.palette.accent) || d.accent;
                        }
                        // fallback to any stored per-path palette (window._gintoPalette)
                        if (!accent && window._gintoPalette) {
                            if (curPath.indexOf('/admin') === 0 && window._gintoPalette['/admin'] && window._gintoPalette['/admin'].accent) accent = window._gintoPalette['/admin'].accent;
                            else if (window._gintoPalette[curPath] && window._gintoPalette[curPath].accent) accent = window._gintoPalette[curPath].accent;
                        }
                        // lastly fallback to the CSS variables
                        if (!accent) {
                            const computed = getComputedStyle(document.documentElement).getPropertyValue('--dashboard-accent') || getComputedStyle(document.documentElement).getPropertyValue('--accent');
                            accent = (computed || '#cfA055').trim();
                        }
                        const bg = hexToRgba(accent, isDark ? 0.7 : 0.92);
                        performanceChart.data.datasets[0].backgroundColor = bg;
                        performanceChart.data.datasets[0].borderColor = accent;
                        performanceChart.update();
                    } catch (err) { /* ignore */ }
                }
                function fetchLiveChartData() {
                    return new Promise(resolve => {
                        setTimeout(() => {
                            const today = new Date();
                            const labels = [];
                            const data = [];
                            for (let i = 6; i >= 0; i--) {
                                const date = new Date(today);
                                date.setDate(today.getDate() - i);
                                labels.push(date.toLocaleDateString('en-US', { weekday: 'short' }));
                                data.push(Math.floor(Math.random() * (100 - 40 + 1)) + 40);
                            }
                            resolve({
                                labels: labels,
                                data: data,
                                datasetLabel: 'Network Performance (%)'
                            });
                        }, 500);
                    });
                }
                async function renderChart() {
                    const chartData = await fetchLiveChartData();
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

                    const chartConfig = {
                        type: 'bar',
                        data: {
                            labels: chartData.labels,
                            datasets: [{
                                label: chartData.datasetLabel,
                                data: chartData.data,
                                /* Softer amber that matches the darker palette */
                                backgroundColor: (ctx) => {
                                    const isDark = document.documentElement.classList.contains('dark');
                                    // prefer the per-route palette for /admin if present
                                    const curPath = (window.location && window.location.pathname) ? window.location.pathname : '';
                                    let accent = null;
                                    if (window._gintoPalette && curPath.indexOf('/admin') === 0 && window._gintoPalette['/admin']) accent = window._gintoPalette['/admin'].accent;
                                    if (!accent) {
                                        const computed = getComputedStyle(document.documentElement).getPropertyValue('--dashboard-accent') || getComputedStyle(document.documentElement).getPropertyValue('--accent');
                                        accent = (computed || '#cfA055').trim();
                                    }
                                    return hexToRgba(accent, isDark ? 0.7 : 0.92);
                                },
                                borderColor: (ctx) => {
                                    const curPath = (window.location && window.location.pathname) ? window.location.pathname : '';
                                    let accent = null;
                                    if (window._gintoPalette && curPath.indexOf('/admin') === 0 && window._gintoPalette['/admin']) accent = window._gintoPalette['/admin'].accent;
                                    if (!accent) {
                                        const computed = getComputedStyle(document.documentElement).getPropertyValue('--dashboard-accent') || getComputedStyle(document.documentElement).getPropertyValue('--accent');
                                        accent = (computed || '#cfA055').trim();
                                    }
                                    // if accent is rgba (set elsewhere), just return it; otherwise return hex (works as borderColor)
                                    return accent;
                                },
                                borderWidth: 1,
                                borderRadius: 4,
                                maxBarThickness: 32
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: 100,
                                    title: { display: false },
                                    ticks: {
                                        callback: function(value) { return value + "%" }
                                    },
                                    grid: {
                                        color: (context) => {
                                            const isDark = document.documentElement.classList.contains('dark');
                                            if (isDark) {
                                                return context.tick.value === 0 ? 'rgba(255,255,255,0.06)' : 'rgba(255,255,255,0.03)';
                                            }
                                            return context.tick.value === 0 ? 'rgba(209, 213, 219, 1)' : 'rgba(209, 213, 219, 0.5)';
                                        },
                                        borderDash: [5, 5]
                                    }
                                },
                                x: {
                                    grid: { display: false }
                                }
                            }
                        }
                    };
                    if (performanceChart) {
                        performanceChart.data.labels = chartData.labels;
                        performanceChart.data.datasets[0].data = chartData.data;
                        performanceChart.update();
                    } else {
                        performanceChart = new Chart(ctx, chartConfig);
                    }
                }
                renderChart();

                // When the palette changes (settings applied) or theme toggles, update chart colors
                try {
                    window.addEventListener('site-palette-changed', updateChartColors);
                    window.addEventListener('site-theme-changed', updateChartColors);
                } catch (_) {}
                // setInterval(renderChart, 60000);
                const timeRangeButton = document.getElementById('time-range-button');
                if (timeRangeButton) {
                    timeRangeButton.addEventListener('click', () => {
                        renderChart();
                    });
                }
            }
        });
    </script>
</head>
<body class="min-h-screen bg-white dark:bg-gray-900">
    <div class="min-h-screen bg-white dark:bg-gray-900">
        <?php include __DIR__ . '/parts/sidebar.php'; ?>
        <div id="main-content" class="lg:pl-64">
            <?php include __DIR__ . '/parts/header.php'; ?>
            <?php include __DIR__ . '/parts/body.php'; ?>
        </div>
        <?php include __DIR__ . '/parts/footer.php'; ?>
    </div>
</body>
</html>