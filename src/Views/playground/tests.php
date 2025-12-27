<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$pageTitle = 'Unit Tests - Playground';
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
                    <h1 class="text-xl font-semibold">Unit Tests</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Run or view recent test results for the code in your sandbox.</p>
                </div>
            </div>

            <div class="mt-4">
                <div class="flex items-center gap-2">
                    <button id="tests-run" class="editor-btn">Run Tests</button>
                    <button id="tests-refresh" class="editor-btn editor-btn-secondary">Refresh</button>
                </div>
                <pre id="tests-output" class="mt-3 p-3 rounded bg-gray-100 text-gray-900 dark:bg-black dark:text-green-200">No tests run yet.</pre>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/parts/footer.php'; ?>
    </div>
</main>

<?php include __DIR__ . '/parts/scripts.php'; ?>
<script>
document.getElementById('tests-run').addEventListener('click', ()=>{ document.getElementById('tests-output').textContent = 'Running tests not enabled in this preview.'; });
</script>

</body>
</html>
