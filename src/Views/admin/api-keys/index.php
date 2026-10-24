<?php
// Admin → API Keys. External (third-party, e.g. Groq) and Internal (keys we issue).
$htmlDark   = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? ' class="dark"' : '';
$external   = $external ?? [];
$internal   = $internal ?? ['tokens' => [], 'env' => []];
$needsCode  = !empty($needs_code);
$csrf_token = $csrf_token ?? '';
$models     = class_exists(\Ginto\Sites\Config::class) ? \Ginto\Sites\Config::models() : [];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$when = static fn ($v): string => $v ? date('M j, Y', strtotime((string) $v)) : '—';
?>
<!DOCTYPE html>
<html lang="en"<?= $htmlDark ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $e($csrf_token) ?>">
    <?php include __DIR__ . '/../parts/favicons.php'; ?>
    <title>API Keys - Ginto Admin</title>
    <script>
        (function () {
            try {
                var saved = null;
                try { saved = localStorage.getItem('theme'); } catch (e) { saved = null; }
                if (!saved) { var m = document.cookie.match(/(?:^|; )theme=(dark|light)(?:;|$)/); saved = m ? m[1] : null; }
                if (saved === 'dark') document.documentElement.classList.add('dark');
                else if (saved === 'light') document.documentElement.classList.remove('dark');
            } catch (err) {}
        })();
    </script>
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/tailwind.css">
    <link rel="stylesheet" href="/assets/css/dark-fallback.css">
    <style>
        #sidebar nav { max-height: calc(100vh - 120px); overflow-y: auto; -webkit-overflow-scrolling: touch; }
        :root { --ak-card:#fff; --ak-line:#e5e7eb; --ak-soft:#f9fafb; --ak-fg:#111827; --ak-muted:#6b7280; --ak-ok:#047857; --ak-okbg:#ecfdf5; --ak-bad:#b91c1c; --ak-badbg:#fef2f2; --ak-gold:#f59e0b; --ak-ink:#1f1503; --ak-input:#fff; }
        html.dark { --ak-card:#1f2937; --ak-line:#374151; --ak-soft:#111827; --ak-fg:#f3f4f6; --ak-muted:#9ca3af; --ak-ok:#6ee7b7; --ak-okbg:rgba(16,185,129,.12); --ak-bad:#fca5a5; --ak-badbg:rgba(239,68,68,.12); --ak-input:#111827; }
        .ak-tabs { display:flex; gap:6px; border-bottom:1px solid var(--ak-line); margin-bottom:18px; }
        .ak-tab { padding:10px 14px; font-weight:600; color:var(--ak-muted); border:0; background:none; border-bottom:2px solid transparent; margin-bottom:-1px; cursor:pointer; display:flex; gap:8px; align-items:center; }
        .ak-tab[aria-selected="true"] { color:var(--ak-fg); border-bottom-color:var(--ak-gold); }
        .ak-card { background:var(--ak-card); border:1px solid var(--ak-line); border-radius:12px; padding:18px; margin-bottom:16px; color:var(--ak-fg); }
        .ak-head { display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; }
        .ak-name { font-size:18px; font-weight:700; display:flex; gap:10px; align-items:center; }
        .ak-uses, .ak-note { color:var(--ak-muted); font-size:13px; margin-top:4px; }
        .ak-pill { font-size:12px; font-weight:600; padding:3px 10px; border-radius:999px; display:inline-flex; gap:6px; align-items:center; }
        .ak-pill.ok { color:var(--ak-ok); background:var(--ak-okbg); } .ak-pill.bad { color:var(--ak-bad); background:var(--ak-badbg); }
        .ak-table { width:100%; border-collapse:collapse; font-size:13px; margin-top:12px; }
        .ak-table th { text-align:left; color:var(--ak-muted); font-weight:600; padding:8px; border-bottom:1px solid var(--ak-line); background:var(--ak-soft); }
        .ak-table td { padding:8px; border-bottom:1px solid var(--ak-line); }
        .ak-mono { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; }
        .ak-form { display:grid; grid-template-columns:1fr 150px; gap:10px; margin-top:14px; }
        .ak-form .ak-row { grid-column:1 / -1; display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        .ak-in { width:100%; padding:9px 12px; border:1px solid var(--ak-line); border-radius:8px; background:var(--ak-input); color:var(--ak-fg); }
        .ak-in:focus { outline:2px solid var(--ak-gold); outline-offset:-1px; }
        .ak-keywrap { position:relative; } .ak-keywrap button { position:absolute; right:6px; top:50%; transform:translateY(-50%); border:0; background:none; color:var(--ak-muted); padding:6px; cursor:pointer; }
        .ak-btn { display:inline-flex; gap:8px; align-items:center; padding:9px 14px; border-radius:5px; font-weight:600; border:1px solid var(--ak-line); background:var(--ak-card); color:var(--ak-fg); cursor:pointer; }
        .ak-btn.gold { background:var(--ak-gold); border-color:var(--ak-gold); color:var(--ak-ink); }
        .ak-btn.danger { color:var(--ak-bad); }
        .ak-btn:disabled { opacity:.6; cursor:default; }
        .ak-code { width:130px; letter-spacing:3px; text-align:center; }
        .ak-msg { font-size:13px; min-height:18px; } .ak-msg.ok { color:var(--ak-ok); } .ak-msg.bad { color:var(--ak-bad); }
        .ak-chips { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
        .ak-models { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:8px; margin-top:12px; }
        .ak-models div { border:1px solid var(--ak-line); border-radius:8px; padding:8px 10px; background:var(--ak-soft); font-size:12.5px; }
        .ak-models b { display:block; font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:var(--ak-muted); }
        @media (max-width:640px) { .ak-form { grid-template-columns:1fr; } .ak-table .ak-hide { display:none; } }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-white min-h-screen">
    <?php include __DIR__ . '/../parts/sidebar.php'; ?>
    <div class="lg:ml-64 min-h-screen flex flex-col">
        <?php include __DIR__ . '/../parts/header.php'; ?>
        <main class="flex-1 p-6">
            <div class="max-w-5xl mx-auto">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><i class="fas fa-key" style="color:#f59e0b"></i> API Keys</h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">Keys from third parties we use, and keys we issue ourselves. A key is never shown whole again after it is saved.</p>
                </div>

                <div class="ak-tabs" role="tablist">
                    <button class="ak-tab" role="tab" aria-selected="true" data-tab="external" type="button"><i class="fas fa-globe"></i> External</button>
                    <button class="ak-tab" role="tab" aria-selected="false" data-tab="internal" type="button"><i class="fas fa-shield-halved"></i> Internal</button>
                </div>

                <section data-pane="external">
                    <p class="ak-note" style="margin:0 0 14px">Third-party APIs, such as Groq. Saving a key tests it with the provider, then writes it to <span class="ak-mono">.env</span> (what the app reads) and to the database (what key rotation reads).</p>
                    <?php foreach ($external as $p): ?>
                    <div class="ak-card" data-provider="<?= $e($p['id']) ?>">
                        <div class="ak-head">
                            <div>
                                <div class="ak-name"><i class="fas fa-plug" style="color:#f59e0b"></i> <?= $e($p['label']) ?></div>
                                <div class="ak-uses">Used for: <?= $e($p['uses']) ?></div>
                            </div>
                            <?php if ($p['env_set']): ?>
                                <span class="ak-pill ok"><i class="fas fa-circle-check"></i> In use <span class="ak-mono"><?= $e($p['env_mask']) ?></span></span>
                            <?php else: ?>
                                <span class="ak-pill bad"><i class="fas fa-circle-exclamation"></i> Not set</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($p['id'] === 'groq' && $models): ?>
                        <div class="ak-models">
                            <?php foreach ($models as $role => $id): ?><div><b><?= $e(str_replace('_', ' ', $role)) ?></b><span class="ak-mono"><?= $e($id) ?></span></div><?php endforeach; ?>
                        </div>
                        <p class="ak-note">Change a model with its <span class="ak-mono">SITES_*_MODEL</span> line in .env; nothing in the features names a model.</p>
                        <?php endif; ?>

                        <?php if ($p['rows']): ?>
                        <table class="ak-table">
                            <thead><tr><th>Key</th><th>Tier</th><th class="ak-hide">Last used</th><th class="ak-hide">Errors</th><th class="ak-hide">Added</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($p['rows'] as $r): ?>
                                <tr>
                                    <td><span class="ak-mono"><?= $e($r['mask']) ?></span>
                                        <?php if ($r['in_env']): ?> <span class="ak-pill ok">.env</span><?php endif; ?>
                                        <?php if ($r['default']): ?> <span class="ak-pill ok">default</span><?php endif; ?>
                                        <?php if (!$r['active']): ?> <span class="ak-pill bad">off</span><?php endif; ?></td>
                                    <td><?= $e(ucfirst($r['tier'])) ?></td>
                                    <td class="ak-hide"><?= $e($when($r['last_used_at'])) ?></td>
                                    <td class="ak-hide"><?= (int) $r['errors'] ?></td>
                                    <td class="ak-hide"><?= $e($when($r['created_at'])) ?></td>
                                    <td style="text-align:right"><button class="ak-btn danger" type="button" data-remove="<?= (int) $r['id'] ?>"><i class="fas fa-trash"></i> Remove</button></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>

                        <form class="ak-form" autocomplete="off" onsubmit="return false">
                            <div class="ak-keywrap">
                                <input class="ak-in ak-mono" name="key" type="password" placeholder="<?= $e($p['prefix']) ?>…" spellcheck="false" autocomplete="new-password" aria-label="<?= $e($p['label']) ?> API key">
                                <button type="button" data-peek aria-label="Show the key"><i class="fas fa-eye"></i></button>
                            </div>
                            <select class="ak-in" name="tier" aria-label="Tier">
                                <option value="basic">Free tier</option>
                                <option value="production">Paid tier</option>
                            </select>
                            <div class="ak-row">
                                <?php if ($needsCode): ?><input class="ak-in ak-code ak-mono" name="code" inputmode="numeric" maxlength="6" placeholder="2FA code" aria-label="Authenticator code"><?php endif; ?>
                                <button class="ak-btn" type="button" data-test><i class="fas fa-vial"></i> Test</button>
                                <button class="ak-btn gold" type="button" data-save><i class="fas fa-floppy-disk"></i> Save key</button>
                                <a class="ak-note" href="<?= $e($p['site']) ?>" target="_blank" rel="noopener" style="margin-left:auto"><i class="fas fa-arrow-up-right-from-square"></i> Get a key</a>
                            </div>
                            <div class="ak-row ak-msg" aria-live="polite"></div>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </section>

                <section data-pane="internal" hidden>
                    <div class="ak-card">
                        <div class="ak-name"><i class="fas fa-ticket" style="color:#f59e0b"></i> Issued API tokens</div>
                        <div class="ak-uses">Keys our platform issued to accounts (Account → API). Revoking one stops it at once. New ones are issued from the account page.</div>
                        <?php if (!$internal['tokens']): ?>
                            <p class="ak-note" style="margin-top:12px">No active tokens.</p>
                        <?php else: ?>
                        <table class="ak-table">
                            <thead><tr><th>Account</th><th>Name</th><th class="ak-hide">Last used</th><th class="ak-hide">Issued</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($internal['tokens'] as $t): ?>
                                <tr>
                                    <td><?= $e($t['username'] ?? '—') ?></td>
                                    <td class="ak-mono"><?= $e($t['name']) ?> <span style="color:var(--ak-muted)">#<?= (int) $t['id'] ?></span></td>
                                    <td class="ak-hide"><?= $e($when($t['last_used_at'])) ?></td>
                                    <td class="ak-hide"><?= $e($when($t['created_at'])) ?></td>
                                    <td style="text-align:right"><button class="ak-btn danger" type="button" data-revoke="<?= (int) $t['id'] ?>"><i class="fas fa-ban"></i> Revoke</button></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                    <div class="ak-card">
                        <div class="ak-name"><i class="fas fa-lock" style="color:#f59e0b"></i> Platform secrets</div>
                        <div class="ak-uses">Secrets our own services share with each other. They are rotated on both sides together, never from this page.</div>
                        <div class="ak-chips">
                            <?php foreach ($internal['env'] as $s): ?>
                                <span class="ak-pill <?= $s['set'] ? 'ok' : 'bad' ?>"><i class="fas <?= $s['set'] ? 'fa-circle-check' : 'fa-circle-minus' ?>"></i> <span class="ak-mono"><?= $e($s['name']) ?></span> <?= $s['set'] ? 'set' : 'not set' ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if ($needsCode): ?>
                    <div class="ak-card">
                        <label class="ak-note" for="akInternalCode">Authenticator code for revoking</label>
                        <input id="akInternalCode" class="ak-in ak-code ak-mono" inputmode="numeric" maxlength="6" placeholder="2FA code" style="margin-top:6px">
                        <div class="ak-msg" id="akInternalMsg" aria-live="polite" style="margin-top:6px"></div>
                    </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    async function post(path, body) {
        const r = await fetch(path, { method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(Object.assign({ csrf_token: csrf }, body)) });
        let data = {};
        try { data = await r.json(); } catch (e) { data = { error: 'Unexpected answer (' + r.status + ').' }; }
        return Object.assign({ httpOk: r.ok }, data);
    }
    function say(el, kind, text) { el.className = el.className.replace(/\b(ok|bad)\b/g, '').trim() + (kind ? ' ' + kind : ''); el.textContent = text; }

    document.querySelectorAll('.ak-tab').forEach((t) => t.addEventListener('click', () => {
        document.querySelectorAll('.ak-tab').forEach((x) => x.setAttribute('aria-selected', String(x === t)));
        document.querySelectorAll('[data-pane]').forEach((p) => { p.hidden = p.dataset.pane !== t.dataset.tab; });
        try { localStorage.setItem('ak.tab', t.dataset.tab); } catch (e) {}
    }));
    try { const last = localStorage.getItem('ak.tab'); if (last) { const t = document.querySelector('.ak-tab[data-tab="' + last + '"]'); if (t) t.click(); } } catch (e) {}

    document.querySelectorAll('.ak-card[data-provider]').forEach((card) => {
        const provider = card.dataset.provider;
        const form = card.querySelector('.ak-form');
        const key = form.querySelector('[name=key]');
        const msg = form.querySelector('.ak-msg');
        const code = () => (form.querySelector('[name=code]') || {}).value || '';
        form.querySelector('[data-peek]').addEventListener('click', (ev) => {
            key.type = key.type === 'password' ? 'text' : 'password';
            ev.currentTarget.innerHTML = key.type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });
        form.querySelector('[data-test]').addEventListener('click', async (ev) => {
            if (!key.value.trim()) { say(msg, 'bad', 'Paste a key first.'); return; }
            ev.currentTarget.disabled = true; say(msg, '', 'Asking ' + provider + '…');
            const r = await post('/admin/api-keys/test', { provider, key: key.value });
            ev.currentTarget.disabled = false;
            say(msg, r.ok ? 'ok' : 'bad', r.ok ? 'The provider accepts this key.' : (r.error || 'Refused.'));
        });
        form.querySelector('[data-save]').addEventListener('click', async (ev) => {
            if (!key.value.trim()) { say(msg, 'bad', 'Paste a key first.'); return; }
            const btn = ev.currentTarget; btn.disabled = true; say(msg, '', 'Testing and saving…');
            const r = await post('/admin/api-keys/save', { provider, key: key.value, tier: form.querySelector('[name=tier]').value, code: code() });
            btn.disabled = false;
            if (r.success) { key.value = ''; say(msg, 'ok', 'Saved ' + r.mask + ' to .env and the database.'); setTimeout(() => location.reload(), 900); }
            else say(msg, 'bad', r.error || 'Not saved.');
        });
        card.querySelectorAll('[data-remove]').forEach((b) => b.addEventListener('click', async () => {
            if (b.dataset.armed !== '1') { b.dataset.armed = '1'; b.innerHTML = '<i class="fas fa-trash"></i> Press again to remove'; return; }
            b.disabled = true;
            const r = await post('/admin/api-keys/remove', { provider, id: Number(b.dataset.remove), code: code() });
            if (r.success) location.reload(); else { b.disabled = false; say(msg, 'bad', r.error || 'Not removed.'); }
        }));
    });

    document.querySelectorAll('[data-revoke]').forEach((b) => b.addEventListener('click', async () => {
        const out = document.getElementById('akInternalMsg');
        if (b.dataset.armed !== '1') { b.dataset.armed = '1'; b.innerHTML = '<i class="fas fa-ban"></i> Press again to revoke'; return; }
        b.disabled = true;
        const c = document.getElementById('akInternalCode');
        const r = await post('/admin/api-keys/revoke', { id: Number(b.dataset.revoke), code: c ? c.value : '' });
        if (r.success) b.closest('tr').remove(); else { b.disabled = false; if (out) say(out, 'bad', r.error || 'Not revoked.'); }
    }));
})();
</script>
</body>
</html>
