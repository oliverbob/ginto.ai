<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$pageTitle = 'Speech (STT/TTS) - Playground';
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
                    <h1 class="text-xl font-semibold">Speech (STT / TTS)</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Transcribe audio or synthesize text using configured providers.</p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-md border border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-medium">Transcribe (Upload)</h3>
                    <input id="stt-file" type="file" accept="audio/*" class="mt-2" />
                    <div class="mt-3"><button id="stt-send" class="editor-btn">Upload & Transcribe (Preview)</button></div>
                </div>
                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-md border border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-medium">Synthesize (TTS)</h3>
                    <textarea id="tts-text" rows="3" class="w-full mt-2 p-2 rounded border dark:border-gray-700 bg-white dark:bg-gray-900" placeholder="Enter text to synthesize"></textarea>
                    <div class="mt-3"><button id="tts-play" class="editor-btn">Play (Preview)</button></div>
                </div>
            </div>
            <pre id="speech-output" class="mt-3 p-3 rounded bg-gray-100 text-gray-900 dark:bg-black dark:text-green-200">No activity yet.</pre>
        </div>
    </div>

    <?php include __DIR__ . '/parts/footer.php'; ?>
    </div>
</main>

<?php include __DIR__ . '/parts/scripts.php'; ?>
<script>
document.getElementById('stt-send').addEventListener('click', ()=>{ document.getElementById('speech-output').textContent = 'Upload disabled in preview mode.'; });
document.getElementById('tts-play').addEventListener('click', ()=>{
    const t = document.getElementById('tts-text').value.trim(); if (!t) return alert('Enter text');
    try { const u = new SpeechSynthesisUtterance(t); speechSynthesis.speak(u); document.getElementById('speech-output').textContent='Playing via Web Speech API'; } catch(e){ document.getElementById('speech-output').textContent='TTS not supported: '+e.message; }
});
</script>

</body>
</html>
