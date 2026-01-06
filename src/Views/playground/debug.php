<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$pageTitle = 'Debug Mode - Playground';
include __DIR__ . '/parts/head.php';
?>
<body class="bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 antialiased">
    <?php include __DIR__ . '/parts/header.php'; ?>
    <?php include __DIR__ . '/parts/sidebar.php'; ?>
    <?php include __DIR__ . '/parts/content.php'; ?>

    <div class="space-y-6">
        <div class="rounded-xl bg-white dark:bg-gray-900 p-6 border border-gray-200/50 dark:border-gray-700/50">
            <h1 class="text-xl font-semibold">Debug Mode</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Quick debugging helpers and environment dumps.</p>

            <div class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-md border border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-medium">Session</h3>
                    <pre id="dbg-session" class="mt-2 p-2 rounded bg-white dark:bg-gray-900 text-sm">Loading…</pre>
                </div>
                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-md border border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-medium">Environment</h3>
                    <pre id="dbg-env" class="mt-2 p-2 rounded bg-white dark:bg-gray-900 text-sm">Loading…</pre>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/parts/footer.php'; ?>
    </div>
</main>

<?php include __DIR__ . '/parts/scripts.php'; ?>
<script>
(async function(){
    try { const s = await (await fetch('/playground/editor/session_debug?ajax=1',{credentials:'same-origin'})).json(); document.getElementById('dbg-session').textContent = JSON.stringify(s, null, 2); } catch(e){ document.getElementById('dbg-session').textContent = 'Error'; }
    try { const e = await (await fetch('/playground/console/environment?ajax=1',{credentials:'same-origin'})).json(); document.getElementById('dbg-env').textContent = JSON.stringify(e, null, 2); } catch(e){ document.getElementById('dbg-env').textContent = 'Error'; }
})();
</script>

</body>
</html>
