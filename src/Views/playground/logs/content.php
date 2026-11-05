<?php
// Shared content for Playground logs — used by both index and show wrappers
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
?>

<div class="space-y-6 p-6 max-w-6xl mx-auto">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <?php if (!empty($log)): ?>
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-2xl font-semibold">Log #<?= htmlspecialchars($log['id']) ?></h1>
                <a href="/playground/logs" class="text-sm text-gray-500">Back to logs</a>
            </div>

            <dl class="grid grid-cols-2 gap-6 text-sm">
                <div>
                    <dt class="text-xs text-gray-400">When</dt>
                    <dd class="mt-1"><?= htmlspecialchars($log['created_at']) ?></dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400">User</dt>
                    <dd class="mt-1"><?= htmlspecialchars($log['username'] ?? ($log['user_id'] ?? '(system)')) ?></dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400">Action</dt>
                    <dd class="mt-1"><?= htmlspecialchars($log['action'] ?? '') ?></dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-400">Model</dt>
                    <dd class="mt-1"><?= htmlspecialchars($log['model_type'] ?? '') ?> <?= htmlspecialchars($log['model_id'] ?? '') ?></dd>
                </div>
            </dl>

            <hr class="my-4" />

            <div class="text-sm text-gray-700 dark:text-gray-300">
                <?php if (!empty($log['description_json'])): ?>
                    <pre class="whitespace-pre-wrap bg-slate-900 text-slate-200 dark:bg-slate-800 dark:text-slate-100 p-3 rounded-md"><?= htmlspecialchars($log['description_json']) ?></pre>
                <?php else: ?>
                    <pre class="whitespace-pre-wrap bg-slate-900 text-slate-200 dark:bg-slate-800 dark:text-slate-100 p-3 rounded-md"><?= htmlspecialchars($log['description'] ?? '') ?></pre>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-4">
                    <h1 class="text-2xl font-semibold">Playground Logs</h1>
                    <form method="get" action="/playground/logs" class="flex items-center gap-2">
                        <input name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Search action / description" class="px-3 py-1 rounded border bg-gray-50 dark:bg-gray-900 text-sm" />
                        <button class="px-3 py-1 rounded bg-violet-600 text-white text-sm">Search</button>
                    </form>
                </div>
                <div class="flex items-center gap-3">
                    <div class="text-sm text-gray-500">Showing <?= count($logs) ?> recent entries</div>
                    <button id="create-sample" class="px-3 py-1 rounded bg-emerald-600 text-white text-sm">Create sample</button>
                </div>
            </div>

            <div class="overflow-auto">
                <table class="min-w-full text-sm text-left">
                    <thead class="text-xs text-gray-400 uppercase border-b">
                        <tr>
                            <th>ID</th>
                            <th>When</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Model</th>
                            <th>Description</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($logs ?? []) as $l): ?>
                        <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="font-mono text-xs"><?= htmlspecialchars($l['id']) ?></td>
                            <td class="text-xs text-gray-500"><?= htmlspecialchars($l['created_at']) ?></td>
                            <td><?= htmlspecialchars($l['username'] ?? ($l['user_id'] ?? '(system)')) ?></td>
                            <td class="text-xs font-semibold"><?= htmlspecialchars($l['action'] ?? '') ?></td>
                            <td class="text-xs"><?= htmlspecialchars($l['model_type'] ?? '') ?></td>
                            <td class="text-xs text-gray-500"><?= htmlspecialchars(mb_strimwidth($l['summary'] ?? ($l['description'] ?? ''), 0, 140, '…')) ?></td>
                            <td class="text-right"><a href="/playground/logs/<?= htmlspecialchars($l['id']) ?>" class="text-sm text-blue-600">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <script>
            (function(){
                const btn = document.getElementById('create-sample');
                if (!btn) return;
                btn.addEventListener('click', async function(e){
                    e.preventDefault();
                    btn.disabled = true;
                    btn.textContent = 'Creating...';
                    const csrf = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>';
                    try {
                        const res = await fetch('/playground/logs/create-sample', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ csrf_token: csrf, message: 'Sample log created from UI' })
                        });
                        const json = await res.json();
                        if (json && json.success) {
                            // reload page to show it
                            location.reload();
                        } else {
                            alert('Failed to create sample log');
                            btn.disabled = false; btn.textContent = 'Create sample';
                        }
                    } catch (err) {
                        alert('Failed to create sample log');
                        btn.disabled = false; btn.textContent = 'Create sample';
                    }
                });
            })();
            </script>

            <div class="mt-4">
                <?php if (!empty($pagination) && $pagination['total'] > 1): ?>
                    <div class="flex gap-2 items-center">
                        <?php for ($p = 1; $p <= $pagination['total']; $p++): ?>
                            <a class="px-2 py-1 rounded <?= $p === $pagination['current'] ? 'bg-violet-600 text-white' : 'text-gray-600 bg-gray-100' ?>" href="/playground/logs?page=<?= $p ?>"><?= $p ?></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>
</div>

<script>
// Ensure theme class is applied when this fragment is rendered (covers in-page swaps)
(function(){
    try {
        const stored = localStorage.getItem('playground-theme');
        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        const shouldBeDark = stored === 'dark' || (!stored && prefersDark);
        if (shouldBeDark) document.documentElement.classList.add('dark');
        else document.documentElement.classList.remove('dark');
        window.__initialTheme = shouldBeDark ? 'dark' : 'light';
        // Notify other scripts (header theme icon) if they listen
        try { document.dispatchEvent(new CustomEvent('playground-theme-updated', { detail: { theme: window.__initialTheme } })); } catch(e) {}
    } catch (e) {
        // ignore
    }
})();
</script>
