// playground-console.js
(function(){
    async function fetchJson(url) {
        try {
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) return { error: 'network', status: res.status };
            return await res.json();
        } catch (e) { return { error: 'exception', message: e.message }; }
    }

    function el(id) { return document.getElementById(id); }

    async function loadEnvironment() {
        const env = await fetchJson('/playground/console/environment?ajax=1');
        const target = el('pc-environment');
        if (!env || env.error) { target.textContent = 'Failed to load environment'; return; }
        const items = [
            ['PHP Version', env.php_version],
            ['SAPI', env.php_sapi],
            ['Server', env.server_software || 'unknown'],
            ['ROOT_PATH', env.root_path || 'unknown'],
            ['Editor Root', env.editor_root || 'unknown'],
            ['User', env.username || '(anonymous)'],
            ['Detected Admin', env.detected_is_admin ? 'yes' : 'no'],
            ['Playground Sandbox', env.sandbox_id ? env.sandbox_id : (env.playground_use_sandbox ? 'enabled' : 'disabled')]
        ];
        target.innerHTML = '';
        items.forEach(([k,v]) => {
            const d = document.createElement('div'); d.style.marginBottom='6px';
            d.innerHTML = '<strong>'+k+':</strong> ' + (v===null||v===undefined?'<em>n/a</em>':escapeHtml(String(v)));
            target.appendChild(d);
        });
    }

    async function loadLLM() {
        // Populate a neutral "Integrations" panel — do NOT surface LLM provider details.
        const tgt = el('pc-llm');
        if (!tgt) return;
        tgt.innerHTML = '';
        // Prefer environment info if available
        let env = null;
        try { env = await fetchJson('/playground/console/environment?ajax=1'); } catch(e) { env = null; }
        const rows = [];
        rows.push(['Tools', 'Console, File Tree, Live Preview']);
        try {
            const preview = env && (env.preview_url || env.preview_port || env.sandbox_id) ? 'available' : 'none';
            rows.push(['Sandbox Preview', preview]);
        } catch(e) { rows.push(['Sandbox Preview', 'unknown']); }
        rows.push(['AI Features', 'disabled']);
        rows.forEach(([k,v]) => { const div = document.createElement('div'); div.innerHTML = '<strong>'+k+':</strong> '+escapeHtml(String(v)); tgt.appendChild(div); });
    }

    async function loadLogs(lines=200) {
        const res = await fetch('/playground/console/logs?lines=' + encodeURIComponent(lines), { credentials: 'same-origin' });
        const pre = el('pc-logs');
        try {
            if (!res.ok) { pre.textContent = 'Failed to load logs: ' + res.status; return; }
            const txt = await res.text();
            pre.textContent = txt || '(no logs)';
            pre.scrollTop = pre.scrollHeight;
        } catch (e) { pre.textContent = 'Error loading logs: ' + e.message; }
    }

    function escapeHtml(s) { return s.replace(/[&<>"']/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; }); }

    async function init() {
        // Theme toggle
        const themeToggle = document.getElementById('pc-theme-toggle');
        if (themeToggle) themeToggle.addEventListener('click', () => {
            try {
                const cur = localStorage.getItem('playground-theme') || (document.documentElement.classList.contains('dark') ? 'dark' : 'light');
                const next = cur === 'dark' ? 'light' : 'dark';
                localStorage.setItem('playground-theme', next);
                if (next === 'dark') document.documentElement.classList.add('dark'); else document.documentElement.classList.remove('dark');
            } catch (e) { console.warn('theme toggle failed', e); }
        });

        // Fullscreen toggle for console area
        const fsBtn = document.getElementById('pc-fullscreen');
        if (fsBtn) fsBtn.addEventListener('click', () => {
            const el = document.querySelector('.playground-console-shell');
            if (!document.fullscreenElement) {
                el.requestFullscreen?.() || el.webkitRequestFullscreen?.();
            } else {
                document.exitFullscreen?.() || document.webkitExitFullscreen?.();
            }
        });
        // If an xterm PTY is present, we don't use the single-line input to run commands.
        // Keep existing input as a fallback for quick commands in the fieldbox.
        document.getElementById('pc-refresh-logs').addEventListener('click', () => loadLogs());
        document.getElementById('pc-refresh-all').addEventListener('click', () => { loadEnvironment(); loadLLM(); loadLogs(); });
        document.getElementById('pc-refresh-tree').addEventListener('click', async () => {
            try {
                const res = await fetch('/playground/editor/tree', { credentials: 'same-origin' });
                if (!res.ok) throw new Error('Tree refresh failed: ' + res.status);
                const j = await res.json();
                showSessionOutput('Tree refreshed. Root contains ' + Object.keys(j.tree || {}).length + ' entries.');
            } catch (e) { showSessionOutput('Failed to refresh tree: ' + e.message); }
        });

        document.getElementById('pc-session-json').addEventListener('click', async () => {
            const j = await fetchJson('/playground/editor/session_debug?ajax=1');
            showSessionOutput(JSON.stringify(j, null, 2));
        });

        const openSandboxBtn = document.getElementById('pc-open-sandbox');
        if (openSandboxBtn) openSandboxBtn.addEventListener('click', async () => {
            const env = await fetchJson('/playground/console/environment?ajax=1');
            if (!env || !env.sandbox_id) { showSessionOutput('No sandbox available for this session'); return; }
            if (env.preview_url) {
                window.open(env.preview_url, '_blank');
                return;
            }
            // Fallback: use /clients/ route (proxied via port 1800, sandbox from session)
            const url = '/clients/';
            window.open(url, '_blank');
        });

        // initial load
        await loadEnvironment();
        await loadLLM();
        await loadLogs();

        // Terminal wiring
        const termInput = document.getElementById('pc-terminal-input');
        const termSend = document.getElementById('pc-terminal-send');
        const termClear = document.getElementById('pc-terminal-clear');
        const termOutput = document.getElementById('pc-terminal-output');

        function appendTermLine(s) { if (!termOutput) return; termOutput.textContent += '\n' + s; termOutput.scrollTop = termOutput.scrollHeight; }

        if (termClear) termClear.addEventListener('click', () => { if (termOutput) termOutput.textContent = ''; });

        async function sendCommand(cmd) {
            if (!cmd || !cmd.trim()) return;
            appendTermLine('> ' + cmd);
            termSend.disabled = true;
                try {
                    const form = new FormData();
                    const tokenMeta = document.querySelector('meta[name="csrf-token"]');
                    const csrf = (window.CSRF_TOKEN || (tokenMeta && tokenMeta.getAttribute('content')) || '');
                    form.append('csrf_token', csrf);
                    form.append('command', cmd);
                    // mode: allow admin to force host os shell via checkbox
                    try {
                        const modeEl = document.getElementById('pc-mode-os');
                        if (modeEl && modeEl.checked) form.append('mode', 'os'); else form.append('mode', 'sandbox');
                    } catch (e) {}

                    // Try streaming endpoint first
                    const streamUrl = '/playground/console/exec?stream=1';
                    const res = await fetch(streamUrl, { method: 'POST', credentials: 'same-origin', body: form });
                    if (!res.ok) {
                        const txt = await res.text().catch(() => '');
                        appendTermLine('[error] HTTP ' + res.status + ' ' + txt);
                        termSend.disabled = false; return;
                    }

                    // Read streamed text body
                    if (!res.body) {
                        // No streaming support - fall back to JSON endpoint
                        const j = await res.json().catch(() => ({ success: false, error: 'invalid-json' }));
                        if (!res.ok || !j.success) appendTermLine('[error] ' + (j.error || ('HTTP '+res.status)));
                        else { appendTermLine((j.output || '').replace(/\n$/, '')); if (j.truncated) appendTermLine('[output truncated]'); }
                        termSend.disabled = false; return;
                    }

                    const reader = res.body.getReader();
                    const decoder = new TextDecoder();
                    let done = false;
                    let buffer = '';
                    while (!done) {
                        const r = await reader.read();
                        done = r.done;
                        const chunk = r.value ? decoder.decode(r.value, {stream: !done}) : '';
                        if (chunk) {
                            buffer += chunk;
                            // look for exit marker
                            const markerIdx = buffer.indexOf('\n--EXIT-CODE:');
                            if (markerIdx !== -1) {
                                const before = buffer.substring(0, markerIdx);
                                if (before) appendTermLine(before.replace(/\n$/, ''));
                                const rest = buffer.substring(markerIdx + 1);
                                const m = rest.match(/--EXIT-CODE:(\d+)--/);
                                if (m) {
                                    appendTermLine('[process exited with code ' + m[1] + ']');
                                }
                                buffer = '';
                            } else {
                                // no marker yet; flush buffer as-is
                                appendTermLine(chunk.replace(/\n$/, ''));
                                buffer = '';
                            }
                        }
                    }
                } catch (e) { appendTermLine('[exception] ' + e.message); }
            termSend.disabled = false;
        }

        if (termSend) termSend.addEventListener('click', () => { sendCommand(termInput.value); termInput.value = ''; });
        if (termInput) termInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); sendCommand(termInput.value); termInput.value = ''; } });

        // poll logs periodically
        setInterval(loadLogs, 5000);

        // poll logs periodically
        setInterval(loadLogs, 5000);
    }

    function showSessionOutput(text) { const o = el('pc-session-output'); if (o) o.textContent = text; }

    // Initialize when DOM ready
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
