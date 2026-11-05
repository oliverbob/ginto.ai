<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$pageTitle = 'Documentation - Playground';
include __DIR__ . '/parts/head.php';
?>
<body class="bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 antialiased">
    <?php include __DIR__ . '/parts/header.php'; ?>
    <?php include __DIR__ . '/parts/sidebar.php'; ?>
    <?php include __DIR__ . '/parts/content.php'; ?>

    <div class="space-y-6">
        <div class="rounded-xl bg-white dark:bg-gray-900 p-6 border border-gray-200/50 dark:border-gray-700/50">
            <h1 class="text-xl font-semibold">Documentation</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Useful docs and links related to the Playground and sandbox system.</p>

            <div class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-md border border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-medium">Sandbox Docs</h3>
                    <div class="mt-2 text-sm"><a class="text-violet-600 dark:text-violet-300" href="/docs/nspawn-sandboxes.md">nspawn-sandboxes.md</a></div>
                </div>

                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-md border border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-medium">Developer Guides</h3>
                    <ul class="list-disc pl-5 mt-2 text-sm"><li><a href="/docs/preview-debug.md">Preview Debugging</a></li><li><a href="/docs/editor-sidebar.md">Editor & Sidebar</a></li></ul>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/parts/footer.php'; ?>
    </div>
</main>

<?php include __DIR__ . '/parts/scripts.php'; ?>

</body>
</html>
