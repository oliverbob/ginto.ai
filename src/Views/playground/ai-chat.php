<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$pageTitle = 'AI Chat - Playground';
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
                    <h1 class="text-xl font-semibold">AI Chat</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Chat with the configured LLM provider. Streaming and thought-mode may be available depending on provider.</p>
                </div>
            </div>

            <div class="mt-4">
                <div id="ai-chat-area" class="p-4 bg-gray-50 dark:bg-gray-800 rounded-md border border-gray-100 dark:border-gray-700">
                    <div id="ai-status" class="text-sm text-gray-700 dark:text-gray-200 mb-3">Loading model status…</div>
                    <textarea id="ai-input" class="w-full p-3 rounded border dark:border-gray-700 bg-white dark:bg-gray-900" rows="4" placeholder="Enter a message"></textarea>
                    <div class="mt-3 flex items-center gap-2">
                        <button id="ai-send" class="editor-btn">Send</button>
                        <button id="ai-clear" class="editor-btn editor-btn-secondary">Clear</button>
                    </div>
                    <pre id="ai-output" class="mt-3 h-48 overflow-auto p-3 rounded bg-gray-100 text-gray-900 dark:bg-black dark:text-green-200">No conversation yet.</pre>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/parts/footer.php'; ?>
    </div>
</main>

<?php include __DIR__ . '/parts/scripts.php'; ?>
<script>
document.getElementById('ai-clear').addEventListener('click', ()=>{ document.getElementById('ai-input').value=''; document.getElementById('ai-output').textContent='No conversation yet.'; });
(async function(){
    try {
        const j = await (await fetch('/debug/llm',{credentials:'same-origin'})).json();
        document.getElementById('ai-status').textContent = (j && j.success) ? ('Provider: '+(j.vars.LLM_PROVIDER||'n/a')+' | Model: '+(j.vars.LLM_MODEL||'n/a')) : 'LLM unavailable';
    } catch(e){ document.getElementById('ai-status').textContent = 'LLM status error'; }
})();
document.getElementById('ai-send').addEventListener('click', async ()=>{
    const t = document.getElementById('ai-input').value.trim(); if (!t) return;
    document.getElementById('ai-output').textContent += '\n> ' + t + '\n[pretend response] This is a local preview. Configure /chat to connect to real provider.';
});
</script>

</body>
</html>
