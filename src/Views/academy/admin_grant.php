<?php
// academy/admin_grant.php — admin tool to manually grant a paid subscription.
$plans  = $plans ?? [];
$recent = $recent ?? [];
$peso = fn($v) => '₱' . number_format((float) $v, 0);
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Manual subscription grant') ?></title>
    <script>(function(){const t=localStorage.getItem('theme');document.documentElement.classList.toggle('dark',t==='dark'||(t!=='light'&&true));})();</script>
    <script src="/assets/js/tailwindcss.js"></script>
    <script>tailwind.config={darkMode:'class',theme:{extend:{colors:{primary:'#6366f1',secondary:'#8b5cf6'}}}};</script>
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css"><style>.dark{color-scheme:dark}</style>
</head>
<body class="bg-white dark:bg-[#0b1020] text-gray-900 dark:text-gray-100 min-h-screen">
<header class="border-b border-gray-200 dark:border-gray-800">
    <div class="max-w-3xl mx-auto px-4 h-16 flex items-center justify-between">
        <a href="/academy/admin" class="flex items-center gap-2 font-bold text-sm"><i class="fas fa-arrow-left text-primary"></i> Academy Admin</a>
        <span class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-primary/15 text-primary">Admin</span>
    </div>
</header>

<section class="max-w-3xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-extrabold flex items-center gap-2"><i class="fas fa-hand-holding-dollar text-primary"></i> Manual subscription grant</h1>
    <p class="mt-2 text-gray-600 dark:text-gray-300">Activate a membership for someone who paid you <strong>personally</strong> (cash, GCash, bank). It finds their account by email — or creates one — and grants the plan immediately.</p>

    <div class="mt-6 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/5 p-5">
        <div class="grid sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Customer email <span class="text-red-500">*</span></label>
                <input id="g-email" type="email" placeholder="customer@email.com" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent text-sm focus:border-primary focus:outline-none">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Full name <span class="text-gray-400">(only needed if creating a new account)</span></label>
                <input id="g-name" type="text" maxlength="100" placeholder="Juan dela Cruz" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent text-sm focus:border-primary focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Plan</label>
                <select id="g-plan" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent text-sm focus:border-primary focus:outline-none">
                    <?php foreach ($plans as $p): ?>
                    <option value="<?= htmlspecialchars($p['name'] ?? '') ?>" data-price="<?= (float) ($p['price_monthly'] ?? 0) ?>" <?= ($p['name'] ?? '') === 'academy_pro' ? 'selected' : '' ?>><?= htmlspecialchars($p['display_name'] ?? $p['name'] ?? '') ?> — <?= $peso($p['price_monthly'] ?? 0) ?>/mo</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Months</label>
                <input id="g-months" type="number" min="1" max="36" value="1" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent text-sm tabular-nums focus:border-primary focus:outline-none">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Amount received (₱)</label>
                <input id="g-amount" type="number" min="0" step="1" placeholder="auto = plan price" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent text-sm tabular-nums focus:border-primary focus:outline-none">
                <p class="mt-1 text-[11px] text-gray-400">Leave blank to record the plan's monthly price. This is just for your records.</p>
            </div>
        </div>
        <div class="mt-5 flex items-center gap-3">
            <button type="button" id="g-btn" onclick="grantSub()" class="inline-flex items-center gap-2 text-sm font-bold px-5 py-2.5 rounded-xl bg-primary text-white hover:bg-primary/90 disabled:opacity-60"><i class="fas fa-circle-check"></i> Activate subscription</button>
            <span id="g-status" class="text-sm"></span>
        </div>
        <div id="g-result" class="hidden mt-4 rounded-xl border border-green-300 dark:border-green-500/30 bg-green-50 dark:bg-green-500/10 p-4 text-sm"></div>
    </div>

    <h2 class="mt-8 mb-2 text-sm font-bold uppercase tracking-wide text-primary">Recent manual grants</h2>
    <div class="rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-white/5 text-gray-500 dark:text-gray-400 text-xs">
                <tr><th class="text-left px-3 py-2">Customer</th><th class="text-left px-3 py-2">Plan</th><th class="text-right px-3 py-2">Amount</th><th class="text-left px-3 py-2">Expires</th></tr>
            </thead>
            <tbody id="g-recent">
                <?php if (!$recent): ?>
                <tr><td colspan="4" class="px-3 py-6 text-center text-gray-400">No manual grants yet.</td></tr>
                <?php else: foreach ($recent as $r): ?>
                <tr class="border-t border-gray-100 dark:border-gray-800">
                    <td class="px-3 py-2"><div class="font-semibold"><?= htmlspecialchars($r['fullname'] ?: $r['username']) ?></div><div class="text-xs text-gray-400"><?= htmlspecialchars($r['email']) ?></div></td>
                    <td class="px-3 py-2"><?= htmlspecialchars($r['plan'] ?? '') ?></td>
                    <td class="px-3 py-2 text-right tabular-nums"><?= $peso($r['amount_paid'] ?? 0) ?></td>
                    <td class="px-3 py-2 text-gray-500 dark:text-gray-400"><?= htmlspecialchars(substr((string) ($r['expires_at'] ?? ''), 0, 10)) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
const G_CSRF = <?= json_encode($csrf_token ?? '') ?>;
function grantSub() {
    const btn = document.getElementById('g-btn'), st = document.getElementById('g-status'), out = document.getElementById('g-result');
    const email = document.getElementById('g-email').value.trim();
    if (!email) { st.textContent = 'Enter the customer email.'; st.className = 'text-sm text-amber-500'; return; }
    btn.disabled = true; st.textContent = 'Activating…'; st.className = 'text-sm text-gray-400'; out.classList.add('hidden');
    fetch('/academy/admin/grant', {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': G_CSRF }, credentials: 'same-origin',
        body: JSON.stringify({
            csrf_token: G_CSRF, email: email, name: document.getElementById('g-name').value.trim(),
            plan: document.getElementById('g-plan').value, months: +document.getElementById('g-months').value,
            amount: document.getElementById('g-amount').value,
        }),
    }).then(r => r.json()).then(d => {
        if (!d || !d.ok) { st.textContent = (d && d.error) || 'Grant failed.'; st.className = 'text-sm text-amber-500'; return; }
        st.textContent = ''; out.classList.remove('hidden');
        out.innerHTML = '<div class="font-bold text-green-700 dark:text-green-300"><i class="fas fa-circle-check mr-1"></i>Activated ' + d.plan + ' until ' + (d.expires || '').slice(0, 10) + '</div>'
            + '<div class="mt-1 text-gray-700 dark:text-gray-200">Account: <b>' + (d.username || '') + '</b> · ' + email + (d.created ? ' <span class="text-primary">(new account created)</span>' : '') + '</div>'
            + (d.temp_password ? '<div class="mt-1 text-gray-700 dark:text-gray-200">Temporary password: <b class="font-mono">' + d.temp_password + '</b> — share it; they can change it after logging in.</div>' : '')
            + '<div class="mt-1 text-xs text-gray-400">Reload to see it in the list below.</div>';
        setTimeout(() => location.reload(), 1500);
    }).catch(() => { st.textContent = 'Network error.'; st.className = 'text-sm text-amber-500'; })
      .finally(() => { btn.disabled = false; });
}
</script>
</body>
</html>
