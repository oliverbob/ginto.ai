<?php
// Admin edit page — use admin layout parts
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="repo-file" content="src/Views/admin/pages/edit.php">
    <title>Edit Page — Admin</title>
    <link href="/assets/css/tailwind.css" rel="stylesheet">
</head>
<body class="min-h-screen bg-white dark:bg-gray-900">
    <div class="min-h-screen bg-white dark:bg-gray-900">
        <?php include __DIR__ . '/../parts/sidebar.php'; ?>
        <div id="main-content" class="lg:pl-64">
            <?php include __DIR__ . '/../parts/header.php'; ?>

            <div class="p-6 max-w-4xl mx-auto">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h1 class="text-xl font-semibold mb-4">Edit Page</h1>
                    <form method="post" action="/admin/pages/<?= htmlspecialchars($page['id'] ?? '') ?>/edit" class="space-y-4">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf_token ?? '') ?>" />
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Title</label>
                            <input type="text" name="title" value="<?= htmlspecialchars($page['title'] ?? '') ?>" class="w-full p-2 border rounded" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Content</label>
                            <textarea name="content" class="w-full p-2 border rounded" rows="6"><?= htmlspecialchars($page['content'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                            <select name="status" class="block p-2 border rounded">
                                <option value="draft" <?= isset($page['status']) && $page['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="published" <?= isset($page['status']) && $page['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="px-4 py-2 bg-yellow-400 text-white rounded">Update</button>
                        </div>
                    </form>

                    <!-- Dev: Ask assistant about this file -->
                    <div class="mt-4">
                        <button id="ask-assistant-file" type="button" class="px-4 py-2 bg-blue-600 text-white rounded">Ask assistant about this file</button>
                        <div id="assistant-file-response" class="mt-3 p-3 bg-gray-50 dark:bg-gray-800 rounded border text-sm whitespace-pre-wrap"></div>
                    </div>

                    <!-- MCP Tools panel (moved inside editor card for visibility) -->
                    <div id="mcp_tools_panel" class="mt-4 p-3 bg-gray-50 dark:bg-gray-800 rounded border text-sm">
                        <div class="flex items-center justify-between">
                            <div class="font-medium">MCP Tools</div>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <button id="mcp_tools_refresh" type="button" class="text-xs px-2 py-0.5 bg-transparent border border-gray-200 dark:border-gray-700 rounded text-gray-600 dark:text-gray-300">Refresh</button>
                                <div id="mcp_tools_status" class="text-xs text-gray-500">Checking...</div>
                            </div>
                        </div>
                        <ul id="mcp_tools_list" class="mt-2 list-disc list-inside text-sm text-gray-700 dark:text-gray-300"></ul>
                    </div>
                </div>
            </div>
            <?php include __DIR__ . '/../parts/footer.php'; ?>
        </div>
    </div>
            <script>
            (function(){
                // Button posts the current repo file path to the editor chat endpoint
                const btn = document.getElementById('ask-assistant-file');
                if (!btn) return;
                const resp = document.getElementById('assistant-file-response');
                // repo-relative path for this view file
                const repoPath = 'src/Views/admin/pages/edit.php';
                btn.addEventListener('click', async function(){
                    btn.disabled = true;
                    btn.textContent = 'Asking...';
                    try {
                        // read CSRF token from hidden input
                        const csrfInput = document.querySelector('input[name="_csrf"]');
                        const csrf = csrfInput ? csrfInput.value : '';

                        const payload = {
                            message: 'Please inspect this file and explain its purpose, potential issues, and any edits you would propose.',
                            file: btoa(repoPath)
                        };

                        const res = await fetch('/admin/pages/editor/chat', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'text/plain',
                                'X-CSRF-TOKEN': csrf
                            },
                            body: JSON.stringify(payload)
                        });

                        if (!res.ok) {
                            const text = await res.text();
                            resp.textContent = 'Error: ' + res.status + '\n' + text;
                        } else {
                            const text = await res.text();
                            resp.textContent = text;
                        }
                    } catch (e) {
                        resp.textContent = 'Request failed: ' + e.message;
                    } finally {
                        btn.disabled = false;
                        btn.textContent = 'Ask assistant about this file';
                    }
                });
            })();
            </script>
            <script>
            (function(){
                // Query proxied MCP tools endpoint and render results into the panel
                const statusEl = document.getElementById('mcp_tools_status');
                const listEl = document.getElementById('mcp_tools_list');
                function setStatus(s) { try { if (statusEl) statusEl.textContent = s; } catch(e){} }
                async function loadMcpTools(){
                    try {
                        const csrfInput = document.querySelector('input[name="_csrf"]');
                        const csrf = csrfInput ? csrfInput.value : '';
                        const res = await fetch('/admin/pages/editor/mcp-tools?csrf_token=' + encodeURIComponent(csrf), { credentials: 'same-origin' });
                        if (!res.ok) {
                            setStatus('Error: ' + res.status);
                            listEl.innerHTML = '<li>Failed to load MCP tools (HTTP ' + res.status + ')</li>';
                            return;
                        }
                        const j = await res.json();
                        if (!j || !j.success) {
                            setStatus('Unavailable');
                            listEl.innerHTML = '<li>No MCP tools returned</li>';
                            return;
                        }
                        let tools = [];
                        if (Array.isArray(j.tools)) tools = j.tools;
                        else if (j.result && Array.isArray(j.result.tools)) tools = j.result.tools;
                        else if (j.raw && typeof j.raw === 'string') {
                            tools = [{ name: 'local_registry_fallback', description: 'Fallback discovery output', meta: { raw: j.raw } }];
                        } else if (Array.isArray(j.raw?.result?.tools)) tools = j.raw.result.tools;

                        if (!Array.isArray(tools) || tools.length === 0) {
                            setStatus('No tools');
                            listEl.innerHTML = '<li>No tools registered</li>';
                            return;
                        }
                        setStatus(tools.length + ' tool(s)');
                        listEl.innerHTML = '';
                        for (const t of tools) {
                            const name = t.name || t['tool'] || t['id'] || '(unnamed)';
                            const desc = t.description || t.summary || (t.meta && t.meta.description) || '';
                            const li = document.createElement('li');
                            li.className = 'mb-1';
                            const label = document.createElement('div');
                            label.textContent = name + (desc ? ' — ' + desc : '');
                            label.style.cursor = 'pointer';
                            label.title = 'Click to view tool details';
                            li.appendChild(label);
                            const details = document.createElement('pre');
                            details.style.display = 'none';
                            details.style.whiteSpace = 'pre-wrap';
                            details.style.maxHeight = '240px';
                            details.style.overflow = 'auto';
                            details.style.background = 'rgba(0,0,0,0.03)';
                            details.style.padding = '8px';
                            details.style.borderRadius = '4px';
                            try { details.textContent = JSON.stringify(t, null, 2); } catch (e) { details.textContent = String(t); }
                            label.addEventListener('click', () => {
                                details.style.display = details.style.display === 'none' ? 'block' : 'none';
                            });
                            li.appendChild(details);
                            listEl.appendChild(li);
                        }
                    } catch (e) {
                        setStatus('Error');
                        if (listEl) listEl.innerHTML = '<li>Failed to fetch MCP tools: ' + (e?.message || e) + '</li>';
                    }
                }
                try { document.addEventListener('DOMContentLoaded', loadMcpTools); } catch(e) { loadMcpTools(); }
                try {
                    const btn = document.getElementById('mcp_tools_refresh');
                    if (btn) btn.addEventListener('click', function(){ setStatus('Refreshing...'); loadMcpTools(); });
                } catch(e){}
            })();
            </script>
</body>
</html>