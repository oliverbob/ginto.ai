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
    <div class="rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
            <span class="text-sm text-gray-500 dark:text-gray-400">Portfolio Value</span>
            <i class="fas fa-wallet text-primary"></i>
        </div>
        <div id="gtb-portfolio-value" class="mt-2 text-2xl font-bold text-gray-400 dark:text-gray-500">—</div>
        <div id="gtb-portfolio-note" class="mt-1 text-xs text-gray-400 dark:text-gray-500">Connect API to load</div>
    </div>
    <div class="rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
            <span class="text-sm text-gray-500 dark:text-gray-400">Available Balance</span>
            <i class="fas fa-money-bill-wave text-primary"></i>
        </div>
        <div id="gtb-balance-value" class="mt-2 text-2xl font-bold text-gray-400 dark:text-gray-500">—</div>
        <div id="gtb-balance-note" class="mt-1 text-xs text-gray-400 dark:text-gray-500">Free USDT</div>
    </div>
    <div class="rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
            <span class="text-sm text-gray-500 dark:text-gray-400">Open Holdings</span>
            <i class="fas fa-layer-group text-primary"></i>
        </div>
        <div id="gtb-holdings-value" class="mt-2 text-2xl font-bold text-gray-400 dark:text-gray-500">—</div>
        <div id="gtb-holdings-note" class="mt-1 text-xs text-gray-400 dark:text-gray-500">Non-zero assets</div>
    </div>

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

<!-- Markets + candlestick chart (live, Binance public data) -->
<section class="mt-6 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            <i class="fas fa-chart-line text-primary mr-2"></i>Markets
            <span class="ml-1 text-xs font-normal text-gray-400 dark:text-gray-500">live</span>
        </h3>
        <div id="gtb-intervals" class="inline-flex rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden text-xs">
            <?php foreach (['1m','5m','15m','1h','4h','1d'] as $iv): ?>
                <button type="button" data-interval="<?= $iv ?>"
                        class="gtb-iv px-3 py-1.5 <?= $iv==='1h' ? 'bg-primary text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>"><?= $iv ?></button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
        <!-- Chart -->
        <div class="lg:col-span-3 order-1">
            <div class="flex items-baseline justify-between mb-2">
                <div class="flex items-baseline gap-2">
                    <span id="gtb-chart-symbol" class="text-lg font-bold text-gray-900 dark:text-white">BTC/USDT</span>
                    <span id="gtb-chart-price" class="text-lg font-semibold tabular-nums text-gray-700 dark:text-gray-200"></span>
                </div>
                <span id="gtb-chart-change" class="text-sm font-semibold"></span>
            </div>
            <div id="gtb-chart" class="w-full rounded-lg overflow-hidden" style="height:360px"></div>
        </div>
        <!-- Pair list -->
        <div class="lg:col-span-1 order-2">
            <div id="gtb-pairs" class="space-y-1 lg:max-h-[392px] overflow-y-auto pr-1">
                <div class="py-6 text-center text-gray-400 dark:text-gray-500 text-sm">
                    <i class="fas fa-spinner fa-spin mr-1"></i> Loading…
                </div>
            </div>
        </div>
    </div>
    <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
        Prices &amp; candles from Binance public market data (mainnet) — real regardless of your testnet setting. Click a pair to chart it.
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
    const GTB_API_CONFIGURED = <?= $apiConfigured ? 'true' : 'false' ?>;
    const GTB = { symbol: 'BTCUSDT', base: 'BTC', interval: '1h', chart: null, series: null };

    function gtbIsDark() { return document.documentElement.classList.contains('dark'); }
    function gtbFmtPrice(p) {
        p = +p;
        if (p >= 1000) return p.toLocaleString(undefined, { maximumFractionDigits: 2 });
        if (p >= 1) return p.toFixed(3);
        return p.toPrecision(4);
    }
    function gtbFmtUsd(n) { return '$' + (+n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function gtbChgClass(up) { return up ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'; }

    // ---- Candlestick chart ----------------------------------------------------
    function gtbInitChart() {
        const el = document.getElementById('gtb-chart');
        if (!el || typeof LightweightCharts === 'undefined') return;
        const dark = gtbIsDark();
        const grid = dark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
        GTB.chart = LightweightCharts.createChart(el, {
            width: el.clientWidth,
            height: 360,
            layout: { background: { color: 'transparent' }, textColor: dark ? '#9ca3af' : '#374151', fontSize: 11 },
            grid: { vertLines: { color: grid }, horzLines: { color: grid } },
            rightPriceScale: { borderColor: grid },
            timeScale: { borderColor: grid, timeVisible: true, secondsVisible: false },
            crosshair: { mode: LightweightCharts.CrosshairMode ? LightweightCharts.CrosshairMode.Normal : 0 },
            handleScale: true, handleScroll: true,
        });
        GTB.series = GTB.chart.addCandlestickSeries({
            upColor: '#16a34a', downColor: '#ef4444', borderVisible: false,
            wickUpColor: '#16a34a', wickDownColor: '#ef4444',
        });
        new ResizeObserver(() => { if (GTB.chart) GTB.chart.applyOptions({ width: el.clientWidth }); }).observe(el);
    }

    async function gtbLoadChart() {
        if (!GTB.series) return;
        try {
            const res = await fetch(`/gtb/klines?symbol=${encodeURIComponent(GTB.symbol)}&interval=${GTB.interval}`);
            const d = await res.json();
            if (!d.ok || !Array.isArray(d.candles)) return;
            GTB.series.setData(d.candles.map(c => ({ time: c.time, open: c.open, high: c.high, low: c.low, close: c.close })));
            GTB.chart.timeScale().fitContent();
        } catch (e) { /* leave prior chart */ }
    }

    function gtbSelectSymbol(symbol, base, price, changePct) {
        GTB.symbol = symbol; GTB.base = base;
        document.getElementById('gtb-chart-symbol').textContent = base + '/USDT';
        if (price != null) document.getElementById('gtb-chart-price').textContent = '$' + gtbFmtPrice(price);
        const up = (+changePct) >= 0;
        const chgEl = document.getElementById('gtb-chart-change');
        chgEl.textContent = (up ? '+' : '') + (+changePct).toFixed(2) + '%';
        chgEl.className = 'text-sm font-semibold ' + gtbChgClass(up);
        document.querySelectorAll('.gtb-pair').forEach(b =>
            b.classList.toggle('gtb-pair-active', b.dataset.symbol === symbol));
        gtbLoadChart();
    }

    // ---- Pair list ------------------------------------------------------------
    async function gtbLoadPairs() {
        const box = document.getElementById('gtb-pairs');
        try {
            const res = await fetch('/gtb/markets');
            const d = await res.json();
            if (!d.ok) { box.innerHTML = `<div class="py-4 text-center text-red-500 text-xs">✗ ${d.error || 'Failed'}</div>`; return; }
            box.innerHTML = d.markets.map(m => {
                const up = m.changePct >= 0;
                return `<button type="button" class="gtb-pair w-full flex items-center justify-between px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-left transition-colors"
                          data-symbol="${m.symbol}" data-base="${m.base}" data-price="${m.price}" data-change="${m.changePct}">
                        <span class="font-medium text-gray-800 dark:text-gray-200">${m.base}<span class="text-gray-400 text-xs font-normal">/USDT</span></span>
                        <span class="text-right leading-tight">
                            <span class="block text-sm tabular-nums text-gray-800 dark:text-gray-100">$${gtbFmtPrice(m.price)}</span>
                            <span class="block text-[11px] ${gtbChgClass(up)}">${up ? '+' : ''}${m.changePct.toFixed(2)}%</span>
                        </span></button>`;
            }).join('');
            box.querySelectorAll('.gtb-pair').forEach(btn => btn.addEventListener('click', () =>
                gtbSelectSymbol(btn.dataset.symbol, btn.dataset.base, btn.dataset.price, btn.dataset.change)));
            const first = d.markets[0];
            if (first) gtbSelectSymbol(first.symbol, first.base, first.price, first.changePct);
        } catch (e) {
            box.innerHTML = `<div class="py-4 text-center text-red-500 text-xs">✗ ${e.message}</div>`;
        }
    }

    // ---- Interval buttons -----------------------------------------------------
    function gtbInitIntervals() {
        document.querySelectorAll('.gtb-iv').forEach(btn => btn.addEventListener('click', () => {
            GTB.interval = btn.dataset.interval;
            document.querySelectorAll('.gtb-iv').forEach(b => {
                const on = b === btn;
                b.classList.toggle('bg-primary', on);
                b.classList.toggle('text-white', on);
                b.classList.toggle('text-gray-600', !on);
                b.classList.toggle('dark:text-gray-300', !on);
            });
            gtbLoadChart();
        }));
    }

    // ---- Live account: portfolio, balance, holdings ---------------------------
    async function gtbLoadAccount() {
        try {
            const res = await fetch('/gtb/account');
            const d = await res.json();
            const pv = document.getElementById('gtb-portfolio-value');
            const pvn = document.getElementById('gtb-portfolio-note');
            const bv = document.getElementById('gtb-balance-value');
            const hv = document.getElementById('gtb-holdings-value');
            if (!d.ok) { pvn.textContent = d.error || 'Not connected'; return; }
            pv.textContent = gtbFmtUsd(d.portfolioUsdt); pv.className = 'mt-2 text-2xl font-bold text-gray-900 dark:text-white';
            pvn.textContent = (d.testnet ? 'Testnet' : 'Mainnet') + ' · est. USDT';
            bv.textContent = gtbFmtUsd(d.freeUsdt); bv.className = 'mt-2 text-2xl font-bold text-gray-900 dark:text-white';
            hv.textContent = d.holdingsCount; hv.className = 'mt-2 text-2xl font-bold text-gray-900 dark:text-white';
        } catch (e) { /* leave placeholders */ }
    }

    async function gtbTestConnection() {
        const btn = document.getElementById('gtb-test-btn');
        const boxEl = document.getElementById('gtb-test-result');
        const orig = btn ? btn.innerHTML : '';
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing…'; }
        boxEl.classList.remove('hidden');
        boxEl.innerHTML = '<span class="text-gray-500">Contacting Binance…</span>';
        try {
            const res = await fetch('/gtb/account');
            const d = await res.json();
            if (d.ok) {
                const rows = (d.balances || []).map(b =>
                    `<tr><td class="pr-4 font-medium">${b.asset}</td>` +
                    `<td class="pr-4 text-right tabular-nums">${(+b.free).toLocaleString(undefined,{maximumFractionDigits:8})}</td>` +
                    `<td class="pr-4 text-right tabular-nums text-gray-400">${(+b.locked).toLocaleString(undefined,{maximumFractionDigits:8})}</td>` +
                    `<td class="text-right tabular-nums">${gtbFmtUsd(b.usdt)}</td></tr>`).join('');
                boxEl.innerHTML =
                    `<div class="text-green-600 dark:text-green-400 font-semibold mb-1"><i class="fas fa-circle-check"></i> Connected — ${gtbFmtUsd(d.portfolioUsdt)} est. portfolio</div>` +
                    `<div class="text-xs text-gray-500 dark:text-gray-400 mb-2">${d.testnet ? 'Testnet' : 'Mainnet'} · ${d.endpoint} · canTrade: ${d.canTrade}</div>` +
                    (rows
                        ? `<div class="overflow-x-auto"><table class="w-full text-xs"><thead><tr class="text-gray-400 text-left"><th class="pr-4">Asset</th><th class="pr-4 text-right">Free</th><th class="pr-4 text-right">Locked</th><th class="text-right">≈USDT</th></tr></thead><tbody>${rows}</tbody></table></div>`
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

    document.addEventListener('DOMContentLoaded', () => {
        gtbInitChart();
        gtbInitIntervals();
        gtbLoadPairs();
        if (GTB_API_CONFIGURED) gtbLoadAccount();
    });
</script>

<style>
    .gtb-pair-active { background: rgba(99,102,241,0.10); box-shadow: inset 2px 0 0 #6366f1; }
</style>
