<?php
// Admin settings index — wrapped with admin layout parts
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin — Settings</title>
    <link href="/assets/css/tailwind.css" rel="stylesheet">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token ?? '') ?>">
</head>
<body class="min-h-screen bg-white dark:bg-gray-900">
    <div class="min-h-screen bg-white dark:bg-gray-900">
        <?php include __DIR__ . '/../parts/sidebar.php'; ?>
        <div id="main-content" class="lg:pl-64">
            <?php include __DIR__ . '/../parts/header.php'; ?>

            <div class="p-6 max-w-6xl mx-auto">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h1 class="text-2xl font-semibold mb-4">Settings</h1>
                    <?php if (session_status() !== PHP_SESSION_ACTIVE) session_start(); ?>
                    <?php if (!empty($_SESSION['flash_message'])): ?>
                        <div class="mb-4 p-3 rounded bg-green-50 dark:bg-green-900 text-green-800 dark:text-green-200 border border-green-200">
                            <?= htmlspecialchars($_SESSION['flash_message']) ?>
                        </div>
                        <?php unset($_SESSION['flash_message']); ?>
                    <?php endif; ?>

                        <ul class="space-y-2">
                            <?php foreach (($settings ?? []) as $s): ?>
                            <li class="p-2 border rounded<?= $s['key'] === 'admin_icon_colors' ? ' bg-gray-50 dark:bg-gray-800/50' : '' ?>">
                                <?= htmlspecialchars($s['key']) ?> =
                                <!-- allow server-side JSON values to wrap and break so long lines don't overflow -->
                                <strong class="block font-normal mt-1 whitespace-pre-wrap break-words text-sm" style="word-break: break-word; white-space: pre-wrap;"><?= htmlspecialchars($s['value']) ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="mt-6 bg-white dark:bg-gray-900 rounded-lg border p-4">
                        <h2 class="text-lg font-semibold mb-2">Sidebar icon colours (server)</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">This setting stores a JSON object mapping sidebar route keys (href or custom key) to a hex color. Use this to persist icon colors across users.</p>

                        <form id="icon-colors-form" method="post" action="/admin/settings/save">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                            <textarea id="icon-colors-text" name="icon_colors" rows="8" wrap="soft" style="white-space: pre-wrap; word-break: break-word;" class="w-full p-3 border rounded font-mono text-sm bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100" placeholder='{"/admin/users":"#ef4444"}'></textarea>
                            <div class="flex items-center justify-between mt-3">
                                <div class="flex items-center gap-2">
                                    <button type="submit" class="px-3 py-2 rounded bg-indigo-600 text-white">Save to server</button>
                                    <button id="icon-colors-load" type="button" class="px-3 py-2 rounded bg-gray-200 dark:bg-gray-700">Load current</button>
                                    <button id="icon-colors-apply" type="button" class="px-3 py-2 rounded bg-yellow-500 text-white">Apply to this browser</button>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Saved in 'settings' table under <code>admin_icon_colors</code></div>
                            </div>
                        </form>

                        <div id="icon-colors-editor" class="mt-4"></div>
                        <script>
                            // small client-side helpers: fetch and load server value into the textarea
                            document.addEventListener('DOMContentLoaded', function(){
                                const txt = document.getElementById('icon-colors-text');
                                const loadBtn = document.getElementById('icon-colors-load');
                                const applyBtn = document.getElementById('icon-colors-apply');
                                async function load() {
                                    try {
                                        const res = await fetch('/admin/settings/icon-colors');
                                        if (!res.ok) throw new Error('no');
                                        const json = await res.json();
                                        txt.value = JSON.stringify(json, null, 2);
                                    } catch (e) {
                                        const existing = Array.from(document.querySelectorAll('li')).find(li => li.textContent && li.textContent.startsWith('admin_icon_colors'));
                                        if (existing) {
                                            const parts = existing.textContent.split('=');
                                            txt.value = parts.slice(1).join('=').trim();
                                        }
                                    }
                                }
                                loadBtn.addEventListener('click', function(e){ e.preventDefault(); load(); });
                                applyBtn.addEventListener('click', function(e){
                                    e.preventDefault();
                                    try { const map = JSON.parse(txt.value); localStorage.setItem('ginto_admin_icon_colors', JSON.stringify(map)); window.location && window.location.reload && setTimeout(()=>window.location.reload(), 200); } catch (err) { alert('Invalid JSON'); }
                                });
                                // auto-load on page open
                                load();
                            });
                        </script>
                    </div>

                    <div class="mt-6 bg-white dark:bg-gray-900 rounded-lg border p-4">
                        <h2 class="text-lg font-semibold mb-2">Routes page settings (server)</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">Configure display options for the admin Routes page. This setting stores a JSON object and is applied client-side (you can also load and apply the settings in this browser).</p>

                        <form id="routes-settings-form" method="post" action="/admin/settings/save">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                            <textarea id="routes-settings-text" name="routes_settings" rows="8" wrap="soft" style="white-space: pre-wrap; word-break: break-word;" class="w-full p-3 border rounded font-mono text-sm bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100" placeholder='{"badgeUppercase":true,"rowBorderColor":"emerald-200"}'></textarea>
                            <div class="flex items-center justify-between mt-3">
                                <div class="flex items-center gap-2">
                                    <button type="submit" class="px-3 py-2 rounded bg-indigo-600 text-white">Save to server</button>
                                    <button id="routes-settings-load" type="button" class="px-3 py-2 rounded bg-gray-200 dark:bg-gray-700">Load current</button>
                                    <button id="routes-settings-apply" type="button" class="px-3 py-2 rounded bg-yellow-500 text-white">Apply to this browser</button>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Saved under <code>routes_settings</code> in the settings table.</div>
                            </div>
                        </form>

                        <div id="routes-settings-editor" class="mt-4"></div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function(){
                                const txt = document.getElementById('routes-settings-text');
                                const loadBtn = document.getElementById('routes-settings-load');
                                const applyBtn = document.getElementById('routes-settings-apply');
                                async function load() {
                                    try {
                                        const res = await fetch('/admin/settings/routes');
                                        if (!res.ok) throw new Error('no');
                                        const json = await res.json();
                                        txt.value = JSON.stringify(json, null, 2);
                                    } catch (e) {
                                        const existing = Array.from(document.querySelectorAll('li')).find(li => li.textContent && li.textContent.startsWith('routes_settings'));
                                        if (existing) {
                                            const parts = existing.textContent.split('=');
                                            txt.value = parts.slice(1).join('=').trim();
                                        }
                                    }
                                }
                                loadBtn.addEventListener('click', function(e){ e.preventDefault(); load(); });
                                applyBtn.addEventListener('click', function(e){
                                    e.preventDefault();
                                    try {
                                        const map = JSON.parse(txt.value);
                                        localStorage.setItem('ginto_routes_settings', JSON.stringify(map));
                                        window.location && window.location.reload && setTimeout(()=>window.location.reload(), 200);
                                    } catch (err) { alert('Invalid JSON'); }
                                });
                                // auto-load on page open
                                load();
                            });
                        </script>
                    </div>
                </div>
            </div>

            <script defer src="/assets/js/admin-icon-color-picker.js"></script>
            <?php include __DIR__ . '/../parts/footer.php'; ?>
        </div>
    </div>
</body>
</html>