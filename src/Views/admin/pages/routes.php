<?php
// Admin pages: list all registered routes
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <?php include __DIR__ . '/../parts/favicons.php'; ?>
    <title>Admin — Routes</title>
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css" />
    <link rel="stylesheet" href="/assets/css/tailwind.css" />
    <style>
        /* Configurable row hover for the routes table
           - tweak these variables to control the hover tint and transition
           - keep defaults subtle and friendly in both light and dark themes */
        /* Hover/touch states handled via Tailwind utilities (no inline JS or styles) */

        /* Responsive layout for small screens: make table rows stack nicely */
        /* Keep table layout across widths — use horizontal scroll on tight screens
           to preserve columns and alignment. This keeps the header visible and keeps
           the design 'tabular' on small devices while remaining usable. */
        @media (max-width: 768px) {
            .routes-table { table-layout: auto; width: 100%; }
            .routes-table th, .routes-table td { padding: 0.5rem 0.75rem; }
            /* reduce padding on tiny screens */
            @media (max-width: 420px) {
                .routes-table th, .routes-table td { padding: 0.35rem 0.5rem; font-size: 0.9rem; }
            }
            /* allow horizontal scrolling if the viewport can't fit the table */
            .routes-container { overflow-x: auto; }
        }

          /* Fallback rule — make separators explicit if Tailwind classes get overridden or not generated
              Use a subtle green in light mode and a stronger tint in dark mode. !important keeps this durable. */
                      .routes-table tr.route-row { border-bottom: 1px solid rgba(16,185,129,0.25) !important; }
                    body.dark .routes-table tr.route-row { border-bottom-color: rgba(4,120,87,0.6) !important; }

                    /* Header fallback styling for thead/th — provides subtle shading and weight when utilities don't get applied */
                    .routes-table thead th {
                        background: linear-gradient(180deg, rgba(236,253,245,0.95), rgba(236,253,245,0.8));
                        color: #064e3b; /* emerald-800-like */
                        box-shadow: inset 0 -2px 0 rgba(16,185,129,0.08);
                        border-bottom: 1px solid rgba(16,185,129,0.18) !important;
                    }
                    body.dark .routes-table thead th {
                        background: linear-gradient(180deg, rgba(15,23,42,0.55), rgba(15,23,42,0.35));
                        color: rgba(255,255,255,0.95);
                        box-shadow: inset 0 -2px 0 rgba(4,120,87,0.25);
                        border-bottom-color: rgba(4,120,87,0.6) !important;
                    }

                    /* Page-scoped admin-btn safety override (routes page) — mirrors header/global patterns
                         This ensures the `Back to Pages` outline button on this page reads clearly in light mode. */
                    html:not(.dark) .routes-container .admin-btn { 
                        color: #0b1220 !important; background-color: #ffffff !important; border-color: rgba(15,23,42,0.14) !important;
                        font-weight: 600 !important; box-shadow: 0 1px 0 rgba(2,6,23,0.035) !important; text-shadow: none !important;
                    }
                    html:not(.dark) .routes-container .admin-btn:hover { background-color: #f7fafc !important; border-color: rgba(15,23,42,0.18) !important; }

                    /* Method badge (GET / POST / other) — strong, readable text and colors
                       Page-scoped so this is immediate and resilient to other CSS layers. */
                    .routes-table .method-badge { 
                        display: inline-block !important; 
                        text-transform: uppercase !important; 
                        font-weight: 700 !important; 
                        letter-spacing: .03em !important; 
                        padding: .22rem .5rem !important; 
                        font-size: .68rem !important; 
                        line-height: 1 !important; 
                        border-width: 1px !important; 
                        box-shadow: 0 1px 0 rgba(2,6,23,0.04) inset !important; 
                    }
                    .routes-table .method-badge.bg-emerald-800 { background-color: #065f46 !important; color: #ffffff !important; border-color: #064e3b !important; }
                    .routes-table .method-badge.bg-amber-600 { background-color: #d97706 !important; color: #ffffff !important; border-color: #b45309 !important; }
                    .routes-table .method-badge.bg-gray-100 { background-color: #f3f4f6 !important; color: #0f172a !important; border-color: rgba(15,23,42,0.06) !important; }
                    body.dark .routes-table .method-badge.bg-emerald-800 { background-color: #064e3b !important; color: #f8fafc !important; border-color: rgba(255,255,255,0.06) !important; }
                    body.dark .routes-table .method-badge.bg-amber-600 { background-color: #b45309 !important; color: #fff8ed !important; border-color: rgba(255,255,255,0.06) !important; }
                    body.dark .routes-table .method-badge.bg-gray-100 { background-color: rgba(255,255,255,0.02) !important; color: #e6eef6 !important; border-color: rgba(255,255,255,0.06) !important; }

                    /* Reduce overly-strong hover contrast in dark mode for table rows */
                    body.dark .routes-table tr.route-row:hover {
                        /* Low-contrast emerald tint for dark-mode hover (keeps harmony with separators) */
                        background-color: rgba(4,120,87,0.06) !important; /* emerald-700-ish 6% */
                    }
    </style>
    
</head>
<body class="min-h-screen bg-white dark:bg-gray-900">
    <div class="min-h-screen bg-white dark:bg-gray-900">
        <?php include __DIR__ . '/../parts/sidebar.php'; ?>
        <div id="main-content" class="lg:pl-64">
            <?php include __DIR__ . '/../parts/header.php'; ?>

            <div class="p-6">
                <div class="max-w-7xl mx-auto">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Registered Routes</h1>
                            <div class="flex items-center gap-3">
                                <?php include_once __DIR__ . '/../parts/admin_button.php'; ?>
                                <?php admin_button('Back to Pages', '/admin/pages', ['variant' => 'outline']); ?>
                            </div>
                        </div>

                        <div class="overflow-x-auto routes-container">
                            <?php if (empty($routes)): ?>
                                <div class="text-sm text-gray-600 dark:text-gray-300">No routes registered.</div>
                            <?php else: ?>
                                <table class="routes-table w-full text-left text-sm border rounded-md overflow-hidden bg-transparent border-gray-200 dark:border-gray-800">
                                    <thead>
                                        <!-- Single header row — make header visually distinct with weight + subtle shading -->
                                        <tr class="text-xs uppercase border-b border-emerald-300 dark:border-emerald-700">
                                            <th class="py-3 px-3 font-semibold tracking-wide text-gray-800 dark:text-gray-100 bg-emerald-50/80 dark:bg-gradient-to-r dark:from-gray-900/50 dark:via-gray-900/40 dark:to-gray-900/50">Method</th>
                                            <th class="py-3 px-3 font-semibold tracking-wide text-gray-800 dark:text-gray-100 bg-emerald-50/80 dark:bg-gradient-to-r dark:from-gray-900/50 dark:via-gray-900/40 dark:to-gray-900/50">Path</th>
                                            <th class="py-3 px-3 font-semibold tracking-wide text-gray-800 dark:text-gray-100 bg-emerald-50/80 dark:bg-gradient-to-r dark:from-gray-900/50 dark:via-gray-900/40 dark:to-gray-900/50">Handler</th>
                                        </tr>
                                    </thead>
                                    <!-- use Tailwind divide utilities to create visible green separators between rows -->
                                    <tbody class="divide-y divide-emerald-200 dark:divide-emerald-700">
                                        <?php foreach (($routes ?? []) as $r): ?>
                                                                                        <?php // tailwind-only: theme-aware separators and hover states handled with classes ?>
                                                                                        <!-- make row separators visible in light mode with an emerald tint, keep stronger tint in dark mode -->
                                                                                        <tr class="route-row border-b border-emerald-200 dark:border-emerald-700 hover:bg-gray-50 dark:hover:bg-emerald-900/10">
                                                <td class="py-2 px-3 align-top">
                                                    <?php foreach (($r['methods'] ?? []) as $m): ?>
                                                        <?php $mi = strtoupper(trim($m));
                                                            // Shared badge class for consistent styling and easier overrides
                                                            $badgeStyle = '';
                                                            if ($mi === 'GET') $badgeStyle = 'method-badge inline-block text-xs px-2 py-1 rounded-full mr-1 bg-emerald-800 text-white border border-emerald-700';
                                                            else if ($mi === 'POST') $badgeStyle = 'method-badge inline-block text-xs px-2 py-1 rounded-full mr-1 bg-amber-600 text-white border border-amber-700';
                                                            else $badgeStyle = 'method-badge inline-block text-xs px-2 py-1 rounded-full mr-1 bg-gray-100 text-gray-800 border border-gray-300';
                                                        ?>
                                                        <span class="<?php echo $badgeStyle; ?>"><?php echo htmlspecialchars($m); ?></span>
                                                    <?php endforeach; ?>
                                                </td>
                                                <td class="py-2 px-3 align-top code text-xs text-cyan-500 dark:text-cyan-300">
                                                    <?php echo htmlspecialchars($r['path'] ?? ''); ?>
                                                </td>
                                                <td class="py-2 px-3 align-top text-xs text-gray-700 dark:text-gray-100"><?php echo htmlspecialchars($r['handler'] ?? ''); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>

            <?php include __DIR__ . '/../parts/footer.php'; ?>
        </div>
    </div>
    <!-- Tailwind-only implementation — hover, separators and colors handled by classes -->
    <script>
        // Apply any routes settings stored in localStorage (ginto_routes_settings)
        (function(){
            try {
                var raw = localStorage.getItem('ginto_routes_settings');

                var applyConfig = function(cfg) {
                    if (!cfg || typeof cfg !== 'object') return;
                    if (typeof cfg.badgeUppercase !== 'undefined') {
                        document.querySelectorAll('.routes-table .method-badge').forEach(function(el){
                            el.style.textTransform = cfg.badgeUppercase ? 'uppercase' : 'none';
                        });
                    }
                    if (cfg.badgeFontWeight) {
                        document.querySelectorAll('.routes-table .method-badge').forEach(function(el){ el.style.fontWeight = cfg.badgeFontWeight; });
                    }
                    if (cfg.rowBorderColor) {
                        document.querySelectorAll('.routes-table tr.route-row').forEach(function(tr){ tr.style.borderBottomColor = cfg.rowBorderColor; });
                    }
                    if (cfg.rowHoverDark) {
                        var id = 'routes-settings-overrides';
                        var el = document.getElementById(id);
                        if (!el) { el = document.createElement('style'); el.id = id; document.head.appendChild(el); }
                        el.textContent = 'body.dark .routes-table tr.route-row:hover{ background-color: ' + cfg.rowHoverDark + ' !important; }';
                    }
                };

                if (raw) {
                    var cfg = JSON.parse(raw || '{}');
                    applyConfig(cfg);
                } else {
                    // try fetching server-saved value
                    try {
                        fetch('/admin/settings/routes', { credentials: 'same-origin' }).then(function(r){ if (!r.ok) throw new Error('no'); return r.json(); }).then(function(j){ applyConfig(j); }).catch(function(){});
                    } catch (_) {}
                }

            } catch (e) { /* ignore parse errors */ }
        })();
    </script>
</body>
</html>
