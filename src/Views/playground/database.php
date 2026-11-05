<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$pageTitle = 'Database Explorer - Playground';
include __DIR__ . '/parts/head.php';
?>
<body class="bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 antialiased">
    <?php include __DIR__ . '/parts/header.php'; ?>
    <?php include __DIR__ . '/parts/sidebar.php'; ?>
    <?php include __DIR__ . '/parts/content.php'; ?>

    <div class="space-y-6">
        <div class="rounded-xl bg-white dark:bg-gray-900 p-6 border border-gray-200/50 dark:border-gray-700/50">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Database Explorer</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">View database tables and run read-only queries from the playground (preview only).</p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="col-span-1 p-4 bg-gray-50 dark:bg-gray-800 rounded-md border border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-medium">Connection</h3>
                    <div id="db-conn" class="mt-2 text-sm text-gray-700 dark:text-gray-200">Loading…</div>
                </div>

                <div class="col-span-2 p-4 bg-gray-50 dark:bg-gray-800 rounded-md border border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-medium">Run Query (read-only)</h3>
                    <textarea id="db-query" class="w-full mt-2 p-3 rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm" rows="6" placeholder="SELECT * FROM products LIMIT 10;"></textarea>
                    <div class="mt-3 flex items-center gap-2">
                        <button id="db-run" class="editor-btn">Run (Preview)</button>
                        <button id="db-clear" class="editor-btn editor-btn-secondary">Clear</button>
                    </div>
                    <pre id="db-output" class="mt-3 h-48 overflow-auto p-3 rounded bg-gray-100 text-gray-900 dark:bg-black dark:text-green-200">No results yet.</pre>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/parts/footer.php'; ?>
    </div>
</main>

<?php include __DIR__ . '/parts/scripts.php'; ?>
<script>
document.getElementById('db-clear').addEventListener('click', function(){ document.getElementById('db-query').value=''; document.getElementById('db-output').textContent='No results yet.'; });
document.getElementById('db-run').addEventListener('click', async function(){
    const q = document.getElementById('db-query').value.trim();
    if (!q) return alert('Enter a query');
    document.getElementById('db-output').textContent = 'Preview mode: backend query not enabled from UI.';
});
// show basic env
(async function(){ try { const r = await fetch('/playground/console/environment?ajax=1',{credentials:'same-origin'}); const j = await r.json(); document.getElementById('db-conn').textContent = 'Editor Root: ' + (j.editor_root||'n/a'); } catch(e){ document.getElementById('db-conn').textContent = 'Unavailable'; } })();
</script>

</body>
</html>
