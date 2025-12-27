<?php
/**
 * Playground - Console Tool
 * Uses the standard playground layout (head, header, sidebar, content)
 * and injects the console UI into the main content area.
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$pageTitle = 'Playground Console';
include __DIR__ . '/parts/head.php';
?>
<body class="bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 antialiased">
    <?php include __DIR__ . '/parts/header.php'; ?>
    <?php include __DIR__ . '/parts/sidebar.php'; ?>
    <?php include __DIR__ . '/parts/content.php'; ?>

    <?php
    // Expose a CSRF token for console JS
    if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
    $csrf_token = $_SESSION['csrf_token'];
    $detectedAdmin = !empty($_SESSION['is_admin']) || (!empty($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin') || (!empty($GLOBALS['DETECTED_IS_ADMIN']) && $GLOBALS['DETECTED_IS_ADMIN']);
    ?>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
    <script>window.CSRF_TOKEN = <?= json_encode($csrf_token) ?>;</script>

    <div class="space-y-6">
        <div class="rounded-xl bg-white dark:bg-gray-900 p-6 border border-gray-200/50 dark:border-gray-700/50">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold">Playground Console</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Access a sandboxed terminal and environment tools. Admins have elevated privileges.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button id="pc-theme-toggle" class="editor-btn editor-btn-secondary">Theme</button>
                    <button id="pc-fullscreen" class="editor-btn">Fullscreen</button>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="col-span-1 p-4 bg-gray-50 dark:bg-gray-800 rounded-md border border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-medium">Environment</h3>
                    <div id="pc-environment" class="mt-2 text-sm text-gray-700 dark:text-gray-200">Loading…</div>

                    <h3 class="text-sm font-medium mt-4">Integrations</h3>
                    <div id="pc-llm" class="mt-2 text-sm text-gray-700 dark:text-gray-200">Loading…</div>
                </div>

                <div class="col-span-2 p-4 bg-gray-50 dark:bg-gray-800 rounded-md border border-gray-100 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium">Recent Logs</h3>
                        <div class="flex items-center gap-2">
                            <button id="pc-refresh-logs" class="editor-btn">Refresh</button>
                            <button id="pc-refresh-all" class="editor-btn editor-btn-secondary">Refresh All</button>
                        </div>
                    </div>
                    <pre id="pc-logs" class="mt-3 h-64 overflow-auto p-3 rounded bg-gray-100 text-gray-900 dark:bg-black dark:text-green-200">Loading…</pre>

                    <div class="mt-3 flex items-center gap-2">
                        <button id="pc-refresh-tree" class="editor-btn">Refresh File Tree</button>
                        <button id="pc-session-json" class="editor-btn editor-btn-secondary">Show Session JSON</button>
                        <?php if ($detectedAdmin): ?>
                        <button id="pc-open-sandbox" class="editor-btn" style="background:#fef3c7;color:#92400e;">Open Sandbox</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

                <div class="mt-6">
                <div class="p-4 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded border border-gray-100 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div><strong>Terminal</strong> <span class="text-sm text-gray-300 ml-2"><?= $detectedAdmin ? 'Admin mode' : 'Sandbox-limited' ?></span></div>
                    </div>
                    <pre id="pc-terminal-output" class="mt-3 h-48 overflow-auto p-3 rounded bg-gray-100 text-gray-900 dark:bg-black dark:text-green-200">Welcome to the Playground terminal. Enter commands below.</pre>
                    <div class="mt-3 flex items-center gap-2">
                        <input id="pc-terminal-input" class="flex-1 p-2 rounded border border-gray-300 dark:border-gray-700" type="text" placeholder="Enter command (e.g. ls -la)" />
                        <button id="pc-terminal-send" class="editor-btn">Run</button>
                        <button id="pc-terminal-clear" class="editor-btn editor-btn-secondary">Clear</button>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">Non-admin users are restricted to a whitelist of safe commands; admins have full shell access.</p>
                </div>
            </div>
        </div>
    </div>

<!-- Working Environment install modal for Console -->
<div id="pc-we-install-modal" style="display:none;">
    <div class="input-dialog-overlay" role="dialog" aria-modal="true">
        <div class="input-dialog" style="max-width:540px;">
            <h3 id="pc-we-title">Working Environment</h3>
            <div id="pc-we-body">
                <p>This console can prepare a personal Working Environment where your files will appear at <code id="pc-we-path">/home/.../</code>. The process sets up an isolated environment for your session.</p>
                <ol>
                    <li>Create the environment on the host.</li>
                    <li>Start the environment and mount your console folder.</li>
                    <li>Confirm and open the console when ready.</li>
                </ol>
                <pre id="pc-we-log" style="height:160px; overflow:auto; background:#111; color:#eee; padding:8px; border-radius:6px; display:none;"></pre>
            </div>
            <div class="input-dialog-buttons">
                <button id="pc-we-cancel">Close</button>
                <button id="pc-we-start" class="primary">Install Working Environment</button>
            </div>
        </div>
    </div>
</div>


                    <!-- True PTY terminal (xterm.js will initialise this) -->
                    <div class="mt-6">
                        <div class="p-4 bg-white dark:bg-gray-900 rounded border border-gray-100 dark:border-gray-700">
                            <h3 class="text-sm font-medium">Interactive Terminal</h3>
                            <div id="pc-pty" style="width:100%;height:360px;margin-top:8px;background:#000"></div>
                        </div>
                    </div>

    <script>
        // Admin toggle: allow admin to force host OS shell
        <?php if ($detectedAdmin): ?>
        (function(){
            var container = document.getElementById('pc-terminal-output');
            if (!container) return;
            var togg = document.createElement('label'); togg.style.fontSize='12px'; togg.style.marginLeft='12px';
            togg.innerHTML = '<input type="checkbox" id="pc-mode-os" style="margin-right:8px" /> Use OS Shell (admin)';
            container.parentNode.insertBefore(togg, container.nextSibling);
        })();
        <?php endif; ?>
    </script>

    <script>
        (function(){
            // Determine whether to show the install modal for non-admin users
            async function maybeShowInstall() {
                try {
                    const res = await fetch('/playground/console/environment?ajax=1', { credentials: 'same-origin' });
                    const j = await res.json();
                    const isAdmin = j && j.detected_is_admin;
                    const sandboxExists = j && j.sandbox_exists;
                    const sandboxId = j && j.sandbox_id;
                    if (!isAdmin && !sandboxExists && sandboxId) {
                        const m = document.getElementById('pc-we-install-modal');
                        if (!m) return;
                        m.style.display = 'block';
                        document.getElementById('pc-we-path').textContent = '/home/' + sandboxId + '/';
                        document.getElementById('pc-we-start').addEventListener('click', startInstall);
                        document.getElementById('pc-we-cancel').addEventListener('click', () => { m.style.display='none'; });
                    }
                } catch (e) { console.warn('env check failed', e); }
            }

            async function startInstall() {
                const btn = document.getElementById('pc-we-start');
                const log = document.getElementById('pc-we-log');
                btn.disabled = true; btn.textContent = 'Starting...';
                log.style.display = 'block'; log.textContent = 'Requesting Working Environment installation...\n';
                try {
                    const form = new FormData();
                    form.append('csrf_token', window.CSRF_TOKEN || '');
                    const res = await fetch('/playground/editor/install_env', { method: 'POST', credentials: 'same-origin', body: form });
                    const j = await res.json();
                    if (!j || !j.success) { log.textContent += 'Failed to start: ' + (j && j.error ? j.error : res.status) + '\n'; btn.disabled=false; btn.textContent='Install Working Environment'; return; }
                    log.textContent += 'Installation started. Waiting for environment to become available...\n';
                    pollStatus();
                } catch (e) { log.textContent += 'Request failed: ' + e.message + '\n'; btn.disabled=false; btn.textContent='Install Working Environment'; }
            }

            async function pollStatus() {
                const log = document.getElementById('pc-we-log');
                let attempts = 0;
                while (attempts < 120) {
                    attempts++;
                    try {
                        const res = await fetch('/playground/editor/install_status?ajax=1', { credentials: 'same-origin' });
                        const j = await res.json();
                        if (j && j.success && j.sandbox_exists) {
                            log.textContent += 'Working Environment ready. Refreshing...\n';
                            document.getElementById('pc-we-install-modal').style.display = 'none';
                            try { location.reload(); } catch(e) {}
                            return;
                        } else {
                            log.textContent += '.';
                        }
                    } catch (e) {
                        log.textContent += '\nStatus poll error: ' + e.message + '\n';
                    }
                    await new Promise(r => setTimeout(r, 2000));
                }
                log.textContent += '\nTimed out waiting for Working Environment. You can check the install log or try again.';
                document.getElementById('pc-we-start').disabled = false; document.getElementById('pc-we-start').textContent='Install Working Environment';
            }

            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', maybeShowInstall); else maybeShowInstall();
        })();
    </script>

    <?php include __DIR__ . '/parts/footer.php'; ?>
    </div>
</main>

<?php include __DIR__ . '/parts/scripts.php'; ?>

<script src="/assets/js/playground-console.js"></script>

</body>
</html>

<!-- xterm + PTY client wiring -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.1.0/css/xterm.css" />
<script src="https://cdn.jsdelivr.net/npm/xterm@5.1.0/lib/xterm.js"></script>
<script>
// PTY WebSocket client: connects to the local PTY server
(function(){
    const termEl = document.getElementById('pc-pty');
    if (!termEl) return;
    const term = new window.Terminal({ cols: 80, rows: 24, convertEol: true });
    term.open(termEl);

    async function connectPty() {
        const env = await fetch('/playground/console/environment?ajax=1', { credentials: 'same-origin' }).then(r=>r.json()).catch(()=>null);
        let host = window.location.hostname || '';
        if (!host || host === '0.0.0.0' || host === '::' || host === '::1') host = '127.0.0.1';
        const port = (window.location.port && window.location.port !== '') ? window.location.port : window.location.hostname.indexOf(':')>-1 ? window.location.hostname.split(':')[1] : '';
        // PTY server host/port (defaults to 8081)
        const wsHost = window.location.protocol === 'https:' ? window.location.hostname : window.location.hostname;
        // Prefer explicitly-configured PTY/Ratchet port; fall back to Ratchet default
        const wsPort = (window.GINTO_TERMINAL_PORT || 31827);
        const detectedAdmin = env && env.detected_is_admin;
        const sandboxId = env && env.sandbox_id ? env.sandbox_id : '';
        const mode = (detectedAdmin && document.getElementById('pc-mode-os') && document.getElementById('pc-mode-os').checked) ? 'os' : 'sandbox';

        let q = '?mode=' + encodeURIComponent(mode) + '&cols=' + term.cols + '&rows=' + term.rows;
        if (mode === 'sandbox' && sandboxId) q += '&container=' + encodeURIComponent(sandboxId);
        // Connect to the Ratchet PTY endpoint at /terminal
        const wsUrl = (location.protocol === 'https:' ? 'wss://' : 'ws://') + host + ':' + wsPort + '/terminal' + q;
        const ws = new WebSocket(wsUrl);
        ws.binaryType = 'arraybuffer';

        ws.addEventListener('open', function(){ term.write('\r\n*** Connected to remote terminal ***\r\n'); });
        ws.addEventListener('message', function(e){ try { term.write(typeof e.data === 'string' ? e.data : new TextDecoder().decode(e.data)); } catch(e){} });
        ws.addEventListener('close', function(){ term.write('\r\n*** Disconnected ***\r\n'); });

        term.onData(function(d){ try { ws.send(d); } catch(e){} });
        // handle resize
        window.addEventListener('resize', function(){ try { ws.send(JSON.stringify({type:'resize',cols: term.cols, rows: term.rows})); } catch(e){} });
    }

    connectPty().catch(e => console.warn('PTY connect failed', e));
})();
</script>
