<?php
// gtb/pages/home.php - Ginto Trading Bot dashboard (foundation)
// Controller-fed. No Binance calls yet: live portfolio/balance/holdings arrive
// in a later step. Trades & logs read from the gtb_* tables (empty until used).

$apiConfigured   = $apiConfigured ?? false;
$isTestnet       = $isTestnet ?? false;
$binanceEndpoint = $binanceEndpoint ?? ($isTestnet ? 'https://testnet.binance.vision' : 'https://api.binance.com');
$recentTrades    = $recentTrades ?? [];
$recentLogs      = $recentLogs ?? [];
$realizedPnl     = $realizedPnl ?? 0.0;

$endpointHost = preg_replace('#^https?://#', '', $binanceEndpoint);

$pnlPositive = $realizedPnl >= 0;
$pnlText = ($pnlPositive ? '+' : '-') . '$' . number_format(abs($realizedPnl), 2);
?>

<!-- Header row -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">Binance Spot &middot; single-account trading bot</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <?php if ($isTestnet): ?>
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400">
                <i class="fas fa-flask"></i> Testnet
            </span>
        <?php else: ?>
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400">
                <i class="fas fa-triangle-exclamation"></i> Mainnet (real funds)
            </span>
        <?php endif; ?>
        <span class="inline-flex items-center gap-1.5 text-xs font-mono px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
            <i class="fas fa-link"></i><?= htmlspecialchars($endpointHost) ?>
        </span>
        <?php if ($apiConfigured): ?>
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400">
                <i class="fas fa-circle-check"></i> Keys set
            </span>
            <button type="button" id="gtb-test-btn" onclick="gtbTestConnection()"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:border-primary hover:text-primary">
                <i class="fas fa-plug"></i> Test connection
            </button>
        <?php else: ?>
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                <i class="fas fa-circle-xmark"></i> API not configured
            </span>
        <?php endif; ?>
    </div>
</div>

<!-- Test connection result -->
<div id="gtb-test-result" class="hidden mb-6 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-4 text-sm"></div>

<?php if (!$apiConfigured): ?>
    <!-- Setup notice -->
    <div class="mb-6 rounded-xl border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 p-4 flex items-start gap-3">
        <i class="fas fa-triangle-exclamation text-amber-500 mt-0.5"></i>
        <div class="text-sm text-amber-800 dark:text-amber-200">
            <p class="font-semibold">Binance API not configured.</p>
            <p class="mt-0.5 text-amber-700 dark:text-amber-300/80">
                Configure your keys on the
                <a href="/gtb-settings" class="font-semibold underline hover:text-amber-900 dark:hover:text-amber-100">API Settings</a>
                page, or add <code class="px-1 rounded bg-amber-100 dark:bg-amber-500/20">BINANCE_API_KEY</code> and
                <code class="px-1 rounded bg-amber-100 dark:bg-amber-500/20">BINANCE_API_SECRET</code> to your
                <code class="px-1 rounded bg-amber-100 dark:bg-amber-500/20">.env</code>. Keys are read the same way as the rest of the app.
            </p>
            <a href="/gtb-settings" class="mt-2 inline-flex items-center gap-1.5 text-sm font-semibold text-amber-800 dark:text-amber-200 hover:underline">
                <i class="fas fa-gear"></i> Open API Settings
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- Stat cards -->
<section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <?php
    $cards = [
        ['label' => 'Portfolio Value', 'icon' => 'fa-wallet',      'value' => '—', 'note' => 'Live data next build'],
        ['label' => 'Available Balance','icon' => 'fa-money-bill-wave','value' => '—', 'note' => 'Live data next build'],
        ['label' => 'Open Holdings',   'icon' => 'fa-layer-group', 'value' => '—', 'note' => 'Live data next build'],
    ];
    foreach ($cards as $c): ?>
        <div class="rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($c['label']) ?></span>
                <i class="fas <?= htmlspecialchars($c['icon']) ?> text-primary"></i>
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-400 dark:text-gray-500"><?= htmlspecialchars($c['value']) ?></div>
            <div class="mt-1 text-xs text-gray-400 dark:text-gray-500"><?= htmlspecialchars($c['note']) ?></div>
        </div>
    <?php endforeach; ?>

    <!-- Realized P&L (real, from DB) -->
    <div class="rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
            <span class="text-sm text-gray-500 dark:text-gray-400">Realized P&amp;L</span>
            <i class="fas fa-chart-line text-primary"></i>
        </div>
        <div class="mt-2 text-2xl font-bold <?= $pnlPositive ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400' ?>">
            <?= htmlspecialchars($pnlText) ?>
        </div>
        <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">From recorded trades</div>
    </div>
</section>

<!-- Quick actions (routes arrive in later build steps) -->
<section class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-6">
    <?php
    $actions = [
        ['label' => 'Manual Buy',  'icon' => 'fa-arrow-trend-up',   'color' => 'text-green-600 dark:text-green-400', 'href' => null],
        ['label' => 'Manual Sell', 'icon' => 'fa-arrow-trend-down', 'color' => 'text-red-500 dark:text-red-400',     'href' => null],
        ['label' => 'History',     'icon' => 'fa-clock-rotate-left','color' => 'text-primary',                        'href' => null],
        ['label' => 'API Settings','icon' => 'fa-gear',             'color' => 'text-primary',                        'href' => '/gtb-settings'],
    ];
    foreach ($actions as $a):
        if (!empty($a['href'])): ?>
            <a href="<?= htmlspecialchars($a['href']) ?>"
               class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/40 px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-200 hover:border-primary hover:text-primary transition-colors">
                <i class="fas <?= htmlspecialchars($a['icon']) ?> <?= htmlspecialchars($a['color']) ?>"></i>
                <?= htmlspecialchars($a['label']) ?>
            </a>
        <?php else: ?>
            <button type="button" disabled
                    title="Available in the next build step"
                    class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/40 px-4 py-3 text-sm font-medium text-gray-500 dark:text-gray-400 opacity-70 cursor-not-allowed">
                <i class="fas <?= htmlspecialchars($a['icon']) ?> <?= htmlspecialchars($a['color']) ?>"></i>
                <?= htmlspecialchars($a['label']) ?>
            </button>
        <?php endif;
    endforeach; ?>
</section>

<!-- Markets (live, from Binance public data) -->
<section class="mt-6 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-5">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            <i class="fas fa-chart-line text-primary mr-2"></i>Markets
            <span class="ml-1 text-xs font-normal text-gray-400 dark:text-gray-500">live · 24h</span>
        </h3>
        <button type="button" onclick="gtbLoadMarkets()" class="text-xs text-gray-500 dark:text-gray-400 hover:text-primary">
            <i class="fas fa-rotate"></i> Refresh
        </button>
    </div>
    <div id="gtb-markets" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <div class="col-span-full py-8 text-center text-gray-400 dark:text-gray-500 text-sm">
            <i class="fas fa-spinner fa-spin mr-1"></i> Loading live markets…
        </div>
    </div>
    <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
        Prices from Binance public market data (mainnet). Charts are real regardless of your testnet setting.
    </p>
</section>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
    <!-- Trading history -->
    <section class="lg:col-span-2 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-5">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            <i class="fas fa-receipt text-primary mr-2"></i>Trading History
        </h3>
        <?php if (empty($recentTrades)): ?>
            <div class="py-10 text-center text-gray-400 dark:text-gray-500">
                <i class="fas fa-inbox text-2xl mb-2"></i>
                <p class="text-sm">No trades yet.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 pr-4 font-medium">Time</th>
                            <th class="py-2 pr-4 font-medium">Symbol</th>
                            <th class="py-2 pr-4 font-medium">Side</th>
                            <th class="py-2 pr-4 font-medium">Type</th>
                            <th class="py-2 pr-4 font-medium text-right">Price</th>
                            <th class="py-2 pr-4 font-medium text-right">Qty</th>
                            <th class="py-2 font-medium text-right">P&amp;L</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($recentTrades as $t):
                            $pnl = $t['realized_pnl'] ?? null;
                            $pnlNeg = $pnl !== null && (float)$pnl < 0; ?>
                            <tr class="text-gray-800 dark:text-gray-200">
                                <td class="py-2.5 pr-4 text-gray-500 dark:text-gray-400"><?= htmlspecialchars($t['created_at'] ?? '') ?></td>
                                <td class="py-2.5 pr-4 font-medium"><?= htmlspecialchars($t['symbol'] ?? '') ?></td>
                                <td class="py-2.5 pr-4">
                                    <span class="inline-block text-[11px] font-bold px-2 py-0.5 rounded
                                        <?= ($t['side'] ?? '') === 'BUY' ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400'
                                                                         : 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400' ?>">
                                        <?= htmlspecialchars($t['side'] ?? '') ?>
                                    </span>
                                </td>
                                <td class="py-2.5 pr-4 text-gray-500 dark:text-gray-400"><?= htmlspecialchars($t['type'] ?? '') ?></td>
                                <td class="py-2.5 pr-4 text-right tabular-nums"><?= $t['price'] !== null ? htmlspecialchars($t['price']) : '—' ?></td>
                                <td class="py-2.5 pr-4 text-right tabular-nums"><?= htmlspecialchars($t['qty'] ?? '') ?></td>
                                <td class="py-2.5 text-right tabular-nums font-semibold <?= $pnl === null ? 'text-gray-400' : ($pnlNeg ? 'text-red-500 dark:text-red-400' : 'text-green-600 dark:text-green-400') ?>">
                                    <?= $pnl === null ? '—' : htmlspecialchars($pnl) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- Trading log -->
    <section class="lg:col-span-1 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-5">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            <i class="fas fa-list-ul text-primary mr-2"></i>Trading Log
        </h3>
        <?php if (empty($recentLogs)): ?>
            <div class="py-10 text-center text-gray-400 dark:text-gray-500">
                <i class="fas fa-inbox text-2xl mb-2"></i>
                <p class="text-sm">No activity yet.</p>
            </div>
        <?php else: ?>
            <ul class="space-y-2">
                <?php foreach ($recentLogs as $log):
                    $lvl = $log['level'] ?? 'info';
                    $lvlColor = $lvl === 'error' ? 'text-red-500' : ($lvl === 'trade' ? 'text-primary' : 'text-gray-400'); ?>
                    <li class="text-sm flex gap-2">
                        <i class="fas fa-circle text-[6px] mt-1.5 <?= $lvlColor ?>"></i>
                        <div>
                            <span class="text-gray-700 dark:text-gray-300"><?= htmlspecialchars($log['message'] ?? '') ?></span>
                            <span class="block text-xs text-gray-400 dark:text-gray-500"><?= htmlspecialchars($log['created_at'] ?? '') ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<script>
    // ---- Live markets + sparklines --------------------------------------------
    function gtbSparkline(closes, up) {
        if (!closes || closes.length < 2) return '';
        const w = 120, h = 32, pad = 2;
        const min = Math.min(...closes), max = Math.max(...closes);
        const range = (max - min) || 1;
        const stepX = (w - pad * 2) / (closes.length - 1);
        const pts = closes.map((c, i) =>
            `${(pad + i * stepX).toFixed(1)},${(h - pad - ((c - min) / range) * (h - pad * 2)).toFixed(1)}`).join(' ');
        const stroke = up ? '#16a34a' : '#ef4444';
        return `<svg viewBox="0 0 ${w} ${h}" width="100%" height="${h}" preserveAspectRatio="none" class="block">` +
               `<polyline fill="none" stroke="${stroke}" stroke-width="1.5" points="${pts}" /></svg>`;
    }

    function gtbFmtPrice(p) {
        if (p >= 1000) return p.toLocaleString(undefined, { maximumFractionDigits: 2 });
        if (p >= 1) return p.toFixed(3);
        return p.toPrecision(4);
    }

    async function gtbLoadMarkets() {
        const box = document.getElementById('gtb-markets');
        box.innerHTML = '<div class="col-span-full py-8 text-center text-gray-400 text-sm"><i class="fas fa-spinner fa-spin mr-1"></i> Loading live markets…</div>';
        try {
            const res = await fetch('/gtb/markets');
            const d = await res.json();
            if (!d.ok) { box.innerHTML = `<div class="col-span-full py-6 text-center text-red-500 text-sm">✗ ${d.error || 'Failed to load markets'}</div>`; return; }
            box.innerHTML = d.markets.map(m => {
                const up = m.changePct >= 0;
                const chg = (up ? '+' : '') + m.changePct.toFixed(2) + '%';
                const chgCls = up ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400';
                return `
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/40 p-4">
                    <div class="flex items-center justify-between">
                        <div class="font-semibold text-gray-900 dark:text-white">${m.base}<span class="text-gray-400 text-xs font-normal">/USDT</span></div>
                        <div class="text-xs font-semibold ${chgCls}">${chg}</div>
                    </div>
                    <div class="mt-1 text-lg font-bold tabular-nums text-gray-900 dark:text-gray-100">$${gtbFmtPrice(m.price)}</div>
                    <div class="mt-2">${gtbSparkline(m.closes, up)}</div>
                    <div class="mt-2 flex justify-between text-[11px] text-gray-400 dark:text-gray-500 tabular-nums">
                        <span>H $${gtbFmtPrice(m.high)}</span>
                        <span>L $${gtbFmtPrice(m.low)}</span>
                    </div>
                </div>`;
            }).join('');
        } catch (e) {
            box.innerHTML = `<div class="col-span-full py-6 text-center text-red-500 text-sm">✗ ${e.message}</div>`;
        }
    }

    // ---- Test connection (signed account probe) -------------------------------
    async function gtbTestConnection() {
        const btn = document.getElementById('gtb-test-btn');
        const boxEl = document.getElementById('gtb-test-result');
        const orig = btn ? btn.innerHTML : '';
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing…'; }
        boxEl.classList.remove('hidden');
        boxEl.innerHTML = '<span class="text-gray-500">Contacting Binance…</span>';
        try {
            const res = await fetch('/gtb/test-connection');
            const d = await res.json();
            if (d.ok) {
                const rows = (d.balances || []).map(b =>
                    `<tr><td class="pr-4 font-medium">${b.asset}</td>` +
                    `<td class="pr-4 text-right tabular-nums">${(+b.free).toLocaleString(undefined,{maximumFractionDigits:8})}</td>` +
                    `<td class="text-right tabular-nums text-gray-400">${(+b.locked).toLocaleString(undefined,{maximumFractionDigits:8})}</td></tr>`).join('');
                boxEl.innerHTML =
                    `<div class="text-green-600 dark:text-green-400 font-semibold mb-1"><i class="fas fa-circle-check"></i> Connected</div>` +
                    `<div class="text-xs text-gray-500 dark:text-gray-400 mb-2">${d.testnet ? 'Testnet' : 'Mainnet'} · ${d.endpoint} · canTrade: ${d.canTrade}</div>` +
                    (rows
                        ? `<table class="w-full text-xs"><thead><tr class="text-gray-400 text-left"><th class="pr-4">Asset</th><th class="pr-4 text-right">Free</th><th class="text-right">Locked</th></tr></thead><tbody>${rows}</tbody></table>`
                        : `<div class="text-xs text-gray-400">No non-zero balances (empty wallet — normal for a fresh testnet key).</div>`);
            } else {
                boxEl.innerHTML =
                    `<div class="text-red-500 font-semibold mb-1"><i class="fas fa-circle-xmark"></i> Failed</div>` +
                    `<div class="text-xs text-gray-500 dark:text-gray-400">${d.error || 'Unknown error'}</div>` +
                    (d.endpoint ? `<div class="text-xs text-gray-400 mt-1">${d.testnet ? 'Testnet' : 'Mainnet'} · ${d.endpoint}</div>` : '');
            }
        } catch (e) {
            boxEl.innerHTML = `<div class="text-red-500"><i class="fas fa-circle-xmark"></i> ${e.message}</div>`;
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = orig; }
        }
    }

    document.addEventListener('DOMContentLoaded', gtbLoadMarkets);
</script>
