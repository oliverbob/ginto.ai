<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$pageTitle = 'Help & Support - Playground';
include __DIR__ . '/parts/head.php';
?>
<body class="bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 antialiased">
    <?php include __DIR__ . '/parts/header.php'; ?>
    <?php include __DIR__ . '/parts/sidebar.php'; ?>
    <?php include __DIR__ . '/parts/content.php'; ?>

    <div class="space-y-6">
        <div class="rounded-xl bg-white dark:bg-gray-900 p-6 border border-gray-200/50 dark:border-gray-700/50">
            <h1 class="text-xl font-semibold">Help & Support</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Resources, FAQs, and contact details for Playground support.</p>

            <div class="mt-4">
                <h3 class="text-sm font-medium">FAQ</h3>
                <ul class="list-disc pl-5 mt-2 text-sm"><li>How to create a sandbox: see Documentation &gt; nspawn-sandboxes.md</li><li>How to preview: use Console &gt; Open Sandbox</li></ul>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/parts/footer.php'; ?>
    </div>
</main>

<?php include __DIR__ . '/parts/scripts.php'; ?>

</body>
</html>
