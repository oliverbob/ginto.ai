<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$pageTitle = 'Performance - Playground';
include __DIR__ . '/parts/head.php';
?>
<body class="bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 antialiased">
    <?php include __DIR__ . '/parts/header.php'; ?>
    <?php include __DIR__ . '/parts/sidebar.php'; ?>
    <?php include __DIR__ . '/parts/content.php'; ?>

    <div class="space-y-6">
        <div class="rounded-xl bg-white dark:bg-gray-900 p-6 border border-gray-200/50 dark:border-gray-700/50">
            <h1 class="text-xl font-semibold">Performance</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Lightweight performance checks and profiling hints for your sandbox.</p>

            <div class="mt-4">
                <button id="perf-run" class="editor-btn">Run Quick Check</button>
                <pre id="perf-output" class="mt-3 p-3 rounded bg-gray-100 text-gray-900 dark:bg-black dark:text-green-200">No checks run yet.</pre>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/parts/footer.php'; ?>
    </div>
</main>

<?php include __DIR__ . '/parts/scripts.php'; ?>
<script>
document.getElementById('perf-run').addEventListener('click', async function(){
    document.getElementById('perf-output').textContent = 'Quick check: simulated results. For in-depth profiling run tools on host.';
});
</script>

</body>
</html>
