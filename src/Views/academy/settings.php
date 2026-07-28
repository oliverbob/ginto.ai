<?php
// academy/settings.php — per-user, DB-driven bot settings (Pro Trader).
$catalog  = $catalog ?? [];
$settings = $settings ?? ['templates' => '', 'trade_size' => 200, 'max_slots' => 8];
$isPro    = !empty($isPro);
$enabled  = array_filter(array_map('trim', explode(',', (string) ($settings['templates'] ?? ''))));
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Bot Settings') ?></title>
    <script>
      (function(){const t=localStorage.getItem('theme');document.documentElement.classList.toggle('dark',t==='dark'||(t!=='light'&&true));})();
      function gtaToggleTheme(){const d=!document.documentElement.classList.contains('dark');document.documentElement.classList.toggle('dark',d);try{localStorage.setItem('theme',d?'dark':'light');}catch(e){}}
    </script>
    <script src="/assets/js/tailwindcss.js"></script>
    <script>tailwind.config={darkMode:'class',theme:{extend:{colors:{primary:'#6366f1',secondary:'#8b5cf6'}}}};</script>
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css"><style>.dark{color-scheme:dark}</style>
</head>
<body class="bg-white dark:bg-[#0b1020] text-gray-900 dark:text-gray-100 min-h-screen">
<header class="border-b border-gray-200 dark:border-gray-800 sticky top-0 bg-white/80 dark:bg-[#0b1020]/80 backdrop-blur z-30">
    <div class="max-w-4xl mx-auto px-4 h-16 flex items-center justify-between">
        <a href="/academy/bot" class="flex items-center gap-2 font-bold"><i class="fas fa-arrow-left text-primary"></i> Back to Bot Lab</a>
        <div class="flex items-center gap-3 text-sm">
            <?php include __DIR__ . '/_silverqueen_button.php'; ?>
            <button onclick="gtaToggleTheme()" title="Toggle light / dark" class="w-9 h-9 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-primary"><i class="fas fa-circle-half-stroke"></i></button>
            <span class="text-[11px] font-bold px-2.5 py-1 rounded-full <?= $isPro ? 'bg-primary/15 text-primary' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' ?>"><?= $isPro ? 'Pro Trader' : 'Starter' ?></span>
            <a href="/logout" class="inline-flex items-center gap-1 text-gray-500 hover:text-red-500" title="Log out"><i class="fas fa-arrow-right-from-bracket"></i><span class="hidden sm:inline">Log out</span></a>
        </div>
    </div>
</header>

<section class="max-w-4xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-extrabold flex items-center gap-2"><i class="fas fa-sliders text-primary"></i> Your bot settings</h1>
    <p class="mt-2 text-gray-600 dark:text-gray-300">Configure how your $10,000 paper wallet follows the bot. These settings are saved to <strong>your account</strong> — they only affect your own trading.</p>

    <?php if (!$isPro): ?>
    <div class="mt-4 rounded-xl border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 p-4 text-sm text-amber-800 dark:text-amber-200">
        <i class="fas fa-lock mr-1"></i> Your <strong>loss guardrails</strong> below are saveable on Starter. Customising the bot's <strong>strategy templates and sizing</strong> is a <strong>Pro Trader</strong> feature.
        <a href="/academy#pricing" class="font-semibold underline">Upgrade to Pro</a> to run your own configuration.
    </div>
    <?php endif; ?>

    <!-- Strategy templates -->
    <h2 class="mt-8 mb-1 text-sm font-bold uppercase tracking-wide text-primary">Strategy templates</h2>
    <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">Pick which strategies your bot is allowed to trade. These are the same templates powering the live bot.</p>
    <div class="grid sm:grid-cols-2 gap-3">
        <?php foreach ($catalog as $key => $t):
            $on = in_array($key, $enabled, true) || !$enabled;
            $meta = $t['meta'] ?? []; ?>
        <label class="tpl-card flex gap-3 items-start rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/5 p-3 cursor-pointer hover:border-primary transition-colors">
            <input type="checkbox" class="tpl-check mt-1 w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary" value="<?= htmlspecialchars($key) ?>" <?= $on ? 'checked' : '' ?> <?= $isPro ? '' : 'disabled' ?>>
            <div class="min-w-0">
                <div class="font-bold flex items-center gap-2"><?= htmlspecialchars($t['name'] ?? $key) ?><span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-primary/10 text-primary"><?= htmlspecialchars($key) ?></span></div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"><?= htmlspecialchars($t['description'] ?? '') ?></div>
                <?php if (!empty($meta['max_hold_min'])): ?><div class="text-[10px] text-gray-400 mt-1">max hold ~<?= (int) $meta['max_hold_min'] ?>m</div><?php endif; ?>
            </div>
        </label>
        <?php endforeach; ?>
    </div>

    <!-- Risk guardrails (available to EVERY member — they protect manual trades too) -->
    <h2 class="mt-8 mb-1 text-sm font-bold uppercase tracking-wide text-primary">Risk guardrails</h2>
    <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">Automatic exit levels on your paper wallet. These apply to <strong>all</strong> your trades — manual, AI, and bot — and are saveable on any plan. Each open trade shows its stop-loss and take-profit in price and dollars on the chart.</p>
    <div class="grid sm:grid-cols-3 gap-4">
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4">
            <label class="text-sm font-semibold">Per-trade stop-loss</label>
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">Auto-close any single position once it drops this % below entry (0.1–50%).</div>
            <div class="flex items-center gap-2">
                <input id="set-stop" type="number" min="0.1" max="50" step="0.1" value="<?= htmlspecialchars(rtrim(rtrim(number_format((float) ($settings['stop_loss_pct'] ?? 1), 2), '0'), '.')) ?>"
                       class="w-28 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent tabular-nums focus:border-primary focus:outline-none"><span class="text-gray-400">%</span>
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4">
            <label class="text-sm font-semibold">Per-trade take-profit</label>
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">Auto-close your manual/AI trades once they rise this % above entry. Set <strong>0</strong> to let winners run (0–50%).</div>
            <div class="flex items-center gap-2">
                <input id="set-tp" type="number" min="0" max="50" step="0.1" value="<?= htmlspecialchars(rtrim(rtrim(number_format((float) ($settings['take_profit_pct'] ?? 2), 2), '0'), '.')) ?>"
                       class="w-28 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent tabular-nums focus:border-primary focus:outline-none"><span class="text-gray-400">%</span>
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4">
            <label class="text-sm font-semibold">Daily-loss halt</label>
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">If your wallet is down this % on the day, close everything &amp; pause the bot until tomorrow (0.1–50%).</div>
            <div class="flex items-center gap-2">
                <input id="set-daily" type="number" min="0.1" max="50" step="0.1" value="<?= htmlspecialchars(rtrim(rtrim(number_format((float) ($settings['max_daily_loss_pct'] ?? 1), 2), '0'), '.')) ?>"
                       class="w-28 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent tabular-nums focus:border-primary focus:outline-none"><span class="text-gray-400">%</span>
            </div>
        </div>
    </div>

    <!-- Sizing -->
    <h2 class="mt-8 mb-3 text-sm font-bold uppercase tracking-wide text-primary">Bot sizing<?= $isPro ? '' : ' <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400">Pro</span>' ?></h2>
    <div class="grid sm:grid-cols-2 gap-4">
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4">
            <label class="text-sm font-semibold">Paper size per trade</label>
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">How much of your paper wallet to put into each trade ($10–$2,000).</div>
            <div class="flex items-center gap-2"><span class="text-gray-400">$</span>
                <input id="set-size" type="number" min="10" max="2000" step="10" value="<?= (int) ($settings['trade_size'] ?? 200) ?>" <?= $isPro ? '' : 'disabled' ?>
                       class="w-32 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent tabular-nums focus:border-primary focus:outline-none">
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4">
            <label class="text-sm font-semibold">Max concurrent trades</label>
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">How many trades your bot can hold at once (1–20).</div>
            <input id="set-slots" type="number" min="1" max="20" step="1" value="<?= (int) ($settings['max_slots'] ?? 8) ?>" <?= $isPro ? '' : 'disabled' ?>
                   class="w-32 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent tabular-nums focus:border-primary focus:outline-none">
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4">
            <label class="text-sm font-semibold">Bot follow interval</label>
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">How often your bot syncs with the live bot — mirrors trades, auto-buys, and refreshes its thinking (5–300s).</div>
            <div class="flex items-center gap-2">
                <input id="set-interval" type="number" min="5" max="300" step="1" value="<?= (int) ($settings['bot_interval_sec'] ?? 15) ?>" <?= $isPro ? '' : 'disabled' ?>
                       class="w-32 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent tabular-nums focus:border-primary focus:outline-none"><span class="text-gray-400">sec</span>
            </div>
        </div>
    </div>

    <div class="mt-6 flex items-center gap-3">
        <button type="button" id="set-save" onclick="gtaSaveSettings()" class="inline-flex items-center gap-2 text-sm font-bold px-5 py-2.5 rounded-xl bg-primary text-white hover:bg-primary/90 disabled:opacity-60"><i class="fas fa-floppy-disk"></i> Save settings</button>
        <span id="set-status" class="text-sm text-gray-400"></span>
    </div>
</section>

<script>
const GTA_CSRF = <?= json_encode($csrf_token ?? '') ?>;
const GTA_IS_PRO = <?= $isPro ? 'true' : 'false' ?>;
function gtaSaveSettings() {
    var btn = document.getElementById('set-save'), st = document.getElementById('set-status');
    if (!btn || btn.disabled) return;
    // Everyone saves risk guardrails; Pro also saves strategy templates + sizing.
    var payload = {
        csrf_token: GTA_CSRF,
        stop_loss_pct: +document.getElementById('set-stop').value,
        take_profit_pct: +document.getElementById('set-tp').value,
        max_daily_loss_pct: +document.getElementById('set-daily').value,
    };
    if (GTA_IS_PRO) {
        var tpls = Array.prototype.slice.call(document.querySelectorAll('.tpl-check:checked')).map(function (c) { return c.value; });
        if (!tpls.length) { st.textContent = 'Pick at least one strategy.'; st.className = 'text-sm text-amber-500'; return; }
        payload.templates = tpls;
        payload.trade_size = +document.getElementById('set-size').value;
        payload.max_slots = +document.getElementById('set-slots').value;
        payload.bot_interval_sec = +document.getElementById('set-interval').value;
    }
    btn.disabled = true; st.textContent = 'Saving…'; st.className = 'text-sm text-gray-400';
    fetch('/academy/settings/save', {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': GTA_CSRF }, credentials: 'same-origin',
        body: JSON.stringify(payload),
    }).then(function (r) { return r.json(); }).then(function (d) {
        if (d && d.ok) { st.textContent = '✓ Saved — your bot will use these on its next trade.'; st.className = 'text-sm text-green-600 dark:text-green-400'; }
        else { st.textContent = (d && d.error) || 'Could not save.'; st.className = 'text-sm text-amber-500'; }
    }).catch(function () { st.textContent = 'Network error.'; st.className = 'text-sm text-amber-500'; })
      .finally(function () { btn.disabled = false; });
}
</script>
</body>
</html>
