<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$pageTitle = 'API Tester - Playground';
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
                    <h1 class="text-xl font-semibold">API Tester</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Quickly craft API requests against your sandbox or local services.</p>
                </div>
            </div>

            <div class="mt-4">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-md border border-gray-100 dark:border-gray-700">
                        <label class="text-sm font-medium">Method</label>
                        <select id="api-method" class="w-full mt-2 p-2 rounded border dark:border-gray-700 bg-white dark:bg-gray-900">
                            <option>GET</option>
                            <option>POST</option>
                            <option>PUT</option>
                            <option>DELETE</option>
                        </select>
                        <label class="text-sm font-medium mt-3 block">URL</label>
                        <input id="api-url" class="w-full mt-2 p-2 rounded border dark:border-gray-700 bg-white dark:bg-gray-900" placeholder="https://api.example.local/endpoint" />
                    </div>

                    <div class="col-span-2 p-4 bg-gray-50 dark:bg-gray-800 rounded-md border border-gray-100 dark:border-gray-700">
                        <label class="text-sm font-medium">Body (JSON)</label>
                        <textarea id="api-body" class="w-full mt-2 p-3 rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900" rows="8" placeholder='{"key": "value"}'></textarea>
                        <div class="mt-3 flex items-center gap-2">
                            <button id="api-send" class="editor-btn">Send</button>
                            <button id="api-clear" class="editor-btn editor-btn-secondary">Clear</button>
                        </div>
                        <pre id="api-output" class="mt-3 h-48 overflow-auto p-3 rounded bg-gray-100 text-gray-900 dark:bg-black dark:text-green-200">No response yet.</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/parts/footer.php'; ?>
    </div>
</main>

<?php include __DIR__ . '/parts/scripts.php'; ?>
<script>
document.getElementById('api-clear').addEventListener('click', ()=>{ document.getElementById('api-url').value=''; document.getElementById('api-body').value=''; document.getElementById('api-output').textContent='No response yet.'; });
document.getElementById('api-send').addEventListener('click', async ()=>{
    const method = document.getElementById('api-method').value;
    const url = document.getElementById('api-url').value.trim();
    const body = document.getElementById('api-body').value.trim();
    if (!url) return alert('Enter a URL');
    try {
        const opts = { method, credentials: 'include', headers: {} };
        if (body && (method==='POST' || method==='PUT')) { opts.body = body; opts.headers['Content-Type']='application/json'; }
        const res = await fetch(url, opts);
        const txt = await res.text();
        document.getElementById('api-output').textContent = 'HTTP ' + res.status + '\n\n' + txt;
    } catch (e) { document.getElementById('api-output').textContent = 'Request failed: ' + e.message; }
});
</script>

</body>
</html>
