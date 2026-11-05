<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$pageTitle = 'MCP Tools - Playground';
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
                    <h1 class="text-xl font-semibold">MCP Tools</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Monitor and interact with MCP providers and adapters.</p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-md border border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-medium">Provider Status</h3>
                    <div id="mcp-status" class="mt-2 text-sm text-gray-700 dark:text-gray-200">Loading…</div>
                </div>

                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-md border border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-medium">Tools</h3>
                    <div class="mt-2 text-sm text-gray-700 dark:text-gray-200">
                        <ul class="list-disc pl-5">
                            <li>Transcription</li>
                            <li>Text generation</li>
                            <li>Streaming provider debugger</li>
                        </ul>
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
(async function(){
    try {
        const j = await (await fetch('/debug/llm',{credentials:'same-origin'})).json();
        const s = j && j.vars ? ('Provider: ' + (j.vars.LLM_PROVIDER||'n/a') + '\nModel: ' + (j.vars.LLM_MODEL||'n/a')) : JSON.stringify(j);
        document.getElementById('mcp-status').textContent = s;
    } catch(e){ document.getElementById('mcp-status').textContent = 'Unavailable: ' + e.message; }
})();
</script>

</body>
</html>
