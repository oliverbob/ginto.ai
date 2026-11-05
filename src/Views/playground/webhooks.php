<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$pageTitle = 'Webhooks - Playground';
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
                    <h1 class="text-xl font-semibold">Webhooks</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Inspect and replay incoming webhooks for your sandboxed projects.</p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="col-span-1 p-4 bg-gray-50 dark:bg-gray-800 rounded-md border border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-medium">Webhook Endpoint</h3>
                    <div id="wh-endpoint" class="mt-2 text-sm text-gray-700 dark:text-gray-200">No endpoint configured</div>
                </div>

                <div class="col-span-2 p-4 bg-gray-50 dark:bg-gray-800 rounded-md border border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-medium">Recent Webhooks</h3>
                    <pre id="wh-list" class="mt-3 h-48 overflow-auto p-3 rounded bg-gray-100 text-gray-900 dark:bg-black dark:text-green-200">(No webhooks captured)</pre>
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
    try {
        const env = await (await fetch('/playground/console/environment?ajax=1',{credentials:'same-origin'})).json();
        document.getElementById('wh-endpoint').textContent = env.preview_url ? (env.preview_url + 'webhook/') : 'Preview URL unavailable';
    } catch(e){ document.getElementById('wh-endpoint').textContent = 'Unavailable'; }
})();
</script>

</body>
</html>
