<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$pageTitle = 'Cache Manager - Playground';
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
                    <h1 class="text-xl font-semibold">Cache Manager</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">View and purge application caches for your sandbox.</p>
                </div>
                <div>
                    <button id="cache-clear" class="editor-btn">Clear Cache</button>
                </div>
            </div>

            <div class="mt-4">
                <h3 class="text-sm font-medium">Cache Status</h3>
                <pre id="cache-status" class="mt-2 p-3 rounded bg-gray-100 text-gray-900 dark:bg-black dark:text-green-200">Not available (no cache backend)</pre>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/parts/footer.php'; ?>
    </div>
</main>

<?php include __DIR__ . '/parts/scripts.php'; ?>
<script>
document.getElementById('cache-clear').addEventListener('click', function(){
    document.getElementById('cache-status').textContent = 'Clearing cache (simulated)...\nDone.';
});
</script>

</body>
</html>
