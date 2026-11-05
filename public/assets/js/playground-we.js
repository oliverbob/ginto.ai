(async function(){
    // Read initializer provided by server
    const init = window.__we_init || {};
    const isAdmin = !!init.isAdmin;
    const hasEnv = !!init.hasEnv;
    const sandboxId = init.sandboxId || null;

    // On load, if this is a non-admin session we should verify remotely
    // whether a sandbox actually exists for this user. In some environments
    // the server-side boolean may be stale; a realtime status check ensures
    // the install wizard appears if there is no sandbox running.
    if (!isAdmin) {
        try {
            const res = await fetch('/playground/editor/install_status?ajax=1', { credentials: 'same-origin' });
            const j = await res.json().catch(()=>null);
            // Show modal if server reports sandbox doesn't exist OR sandboxId is present but hasEnv false
            if ((j && j.success && !j.sandbox_exists) || (!hasEnv && sandboxId)) {
                // fall-through to main flow below
            } else {
                // nothing to do — sandbox exists or admin
                return;
            }
        } catch (e) {
            // If status check fails, fall back to existing client-side logic
            if (isAdmin || hasEnv || !sandboxId) return;
        }
    }

    if (!isAdmin && !hasEnv && sandboxId) {
        function showModal() {
            const m = document.getElementById('we-install-modal');
            if (!m) return;
            m.style.display = 'block';
            const pathEl = document.getElementById('we-path');
            if (pathEl) pathEl.textContent = '/home/' + sandboxId + '/';
            const startBtn = document.getElementById('we-start');
            if (startBtn) startBtn.addEventListener('click', startInstall);
            const cancelBtn = document.getElementById('we-cancel');
            if (cancelBtn) cancelBtn.addEventListener('click', () => { m.style.display='none'; });
        }

        let __we_last_log = '';
        async function startInstall() {
            const btn = document.getElementById('we-start');
            const log = document.getElementById('we-log');
            if (btn) { btn.disabled = true; btn.textContent = 'Starting...'; }
            if (log) { log.style.display = 'block'; log.textContent = 'Requesting Working Environment installation...\n'; }
            const filesDiv = document.getElementById('we-files');
            if (filesDiv) filesDiv.style.display = 'none';
            try {
                const form = new FormData();
                form.append('csrf_token', window.CSRF_TOKEN || '');
                // 'Riskless' option removed; no riskless flag appended.
                const res = await fetch('/playground/editor/install_env', { method: 'POST', credentials: 'same-origin', body: form });
                const j = await res.json();
                if (!j || !j.success) {
                    if (log) log.textContent += 'Failed to start: ' + (j && j.error ? j.error : res.status) + '\n';
                    if (btn) { btn.disabled=false; btn.textContent='Install Working Environment'; }
                    return;
                }
                if (log) log.textContent += 'Installation started. Waiting for environment to become available...\n';
                __we_last_log = '';
                pollStatus();
            } catch (e) {
                if (log) log.textContent += 'Request failed: ' + e.message + '\n';
                if (btn) { btn.disabled=false; btn.textContent='Install Working Environment'; }
            }
        }

        async function pollStatus() {
            const log = document.getElementById('we-log');
            let attempts = 0;
            while (attempts < 120) {
                attempts++;
                try {
                    const res = await fetch('/playground/editor/install_status?ajax=1', { credentials: 'same-origin' });
                    const j = await res.json();
                    if (j && j.success && j.sandbox_exists) {
                        if (log) log.textContent += 'Working Environment ready. Refreshing file tree...\n';
                        const m = document.getElementById('we-install-modal');
                        if (m) m.style.display = 'none';
                        try { location.reload(); } catch(e) { }
                        return;
                    } else {
                        if (log) log.textContent += '.';
                    }
                    // fetch tail
                    try {
                            const tailRes = await fetch('/install_status_tail.php?sandbox=' + encodeURIComponent(sandboxId) + '&csrf=' + encodeURIComponent(window.CSRF_TOKEN), { credentials: 'same-origin' });
                        if (tailRes) {
                            if (tailRes.status === 403) {
                                if (log) log.textContent += '\n[!] Permission denied reading install log (403). Are you still logged in?\n';
                            }
                            if (tailRes.ok) {
                                const tj = await tailRes.json().catch(()=>null);
                                if (tj && tj.install_log_tail !== undefined) {
                                    const tail = tj.install_log_tail || '';
                                    if (tail.startsWith(__we_last_log)) {
                                        const newpart = tail.slice(__we_last_log.length);
                                        if (newpart.length) {
                                            if (log) log.textContent += newpart;
                                            if (log) log.scrollTop = log.scrollHeight;
                                            __we_last_log = tail;
                                        }
                                        // Detect common daemon errors and surface a run-as-root remediation command
                                        if (tail.indexOf('daemon_error') !== -1) {
                                            try {
                                                displayDaemonRemedy(tail);
                                            } catch (e) { /* ignore */ }
                                        }
                                    } else if (tail !== __we_last_log) {
                                        if (log) log.textContent = 'Installation started. Waiting for environment to become available...\n' + tail;
                                        if (log) log.scrollTop = log.scrollHeight;
                                        __we_last_log = tail;
                                    }
                                }
                                if (tj && Array.isArray(tj.installed_files)) {
                                    const filesEl = document.getElementById('we-files');
                                    if (tj.installed_files.length && filesEl) {
                                        filesEl.style.display = 'block';
                                        filesEl.innerHTML = '<strong>Installed files:</strong> ' + tj.installed_files.slice(0,50).map(x=>escapeHtml(x)).join(', ');
                                    }
                                }
                            }
                        }
                    } catch (e) {
                        // ignore tail errors
                    }
                } catch (e) {
                    if (log) log.textContent += '\nStatus poll error: ' + e.message + '\n';
                }
                await new Promise(r => setTimeout(r, 2000));
            }
            const startBtn = document.getElementById('we-start');
            if (log) log.textContent += '\nTimed out waiting for Working Environment. You can check the install log or try again.';
            if (startBtn) { startBtn.disabled = false; startBtn.textContent='Install Working Environment'; }
        }

        function escapeHtml(s) {
            return String(s).replace(/[&<>\"'`]/g, function(ch){
                return {'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#39;', '`':'&#96;'}[ch];
            });
        }

        function displayDaemonRemedy(tail) {
            const remId = 'we-remedy';
            let rem = document.getElementById(remId);
            if (!rem) {
                rem = document.createElement('div');
                rem.id = remId;
                rem.style.marginTop = '12px';
                rem.style.padding = '10px';
                rem.style.border = '1px solid rgba(255,255,255,0.06)';
                rem.style.background = 'rgba(0,0,0,0.15)';
                rem.style.borderRadius = '6px';
                rem.style.fontSize = '0.9em';
                const body = document.getElementById('we-body');
                if (body) body.appendChild(rem);
            }

            // Use values available from server initializer when possible
            const repoRoot = window.REPO_ROOT || '/home/oliverbob/ginto';
            const editorRoot = window.editorRootFromServer || '/home/oliverbob/ginto/clients/UNKNOWN';
            const originalId = (window.__we_init && window.__we_init.sandboxId) ? window.__we_init.sandboxId : 'SANDBOXID';
            const suggestedUser = window.WE_SESSION_USER || '$USER';

            const cmd = `cd ${repoRoot} && sudo ./deploy/install_sandboxd.sh ${suggestedUser} --create-now ${originalId} ${editorRoot}`;

            rem.innerHTML = '<strong>Install help</strong><div style="margin-top:6px">The daemon reported an error while trying to start your Working Environment. You can run the following as root (one-shot) to complete installation for this user:</div>' +
                '<pre style="margin-top:8px; background:#111; padding:8px; color:#cfc; border-radius:4px;">' + escapeHtml(cmd) + '</pre>' +
                '<div style="margin-top:6px"><button id="we-copy-install-cmd">Copy command</button></div>';

            const copyBtn = document.getElementById('we-copy-install-cmd');
            if (copyBtn) {
                copyBtn.addEventListener('click', () => {
                    navigator.clipboard.writeText(cmd).then(()=>{
                        copyBtn.textContent = 'Copied';
                        setTimeout(()=>copyBtn.textContent = 'Copy command', 2000);
                    });
                });
            }
        }

        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', showModal); else showModal();
    }
})();
