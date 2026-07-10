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

    <!-- Chart (full width) -->
    <div class="flex items-baseline justify-between mb-2">
        <div class="flex items-baseline gap-2">
            <span id="gtb-chart-symbol" class="text-lg font-bold text-gray-900 dark:text-white">BTC/USDT</span>
            <span id="gtb-chart-price" class="text-lg font-semibold tabular-nums text-gray-700 dark:text-gray-200"></span>
        </div>
        <span id="gtb-chart-change" class="text-sm font-semibold"></span>
    </div>
    <div id="gtb-chart" class="w-full rounded-lg overflow-hidden" style="height:360px"></div>
    <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
        Candles from Binance public market data (mainnet) — real regardless of your testnet setting. Click any coin below to chart it.
    </p>
</section>

<!-- Top Movers: Hot / Gainers / Losers (all visible at once) -->
<section class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
    <?php
    $movers = [
        ['col' => 'hot',     'title' => 'Hot Coins',   'icon' => 'fa-fire',             'tone' => 'text-primary'],
        ['col' => 'gainers', 'title' => 'Top Gainers', 'icon' => 'fa-arrow-trend-up',   'tone' => 'text-green-600 dark:text-green-400'],
        ['col' => 'losers',  'title' => 'Top Losers',  'icon' => 'fa-arrow-trend-down', 'tone' => 'text-red-500 dark:text-red-400'],
    ];
    foreach ($movers as $m): ?>
        <div class="rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4">
            <button type="button" class="gtb-sort-btn group w-full flex items-center justify-between mb-3"
                    data-col="<?= $m['col'] ?>" title="Click to sort by 24h change (toggle high↔low)">
                <h3 class="font-semibold text-gray-900 dark:text-white">
                    <i class="fas <?= $m['icon'] ?> <?= $m['tone'] ?> mr-1.5"></i><?= $m['title'] ?>
                </h3>
                <span class="text-[11px] text-gray-400 dark:text-gray-500 group-hover:text-primary flex items-center gap-1">
                    24h % <i id="gtb-sort-<?= $m['col'] ?>" class="fas fa-sort"></i>
                </span>
            </button>
            <div id="gtb-<?= $m['col'] ?>" class="space-y-0.5 max-h-[340px] overflow-y-auto pr-1">
                <div class="py-6 text-center text-gray-400 dark:text-gray-500 text-sm"><i class="fas fa-spinner fa-spin"></i></div>
            </div>
        </div>
    <?php endforeach; ?>
</section>

<!-- Bot Brain: the AI reflecting on the market (advisory) -->
<section class="mt-6 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            <i class="fas fa-robot text-primary mr-2"></i>Bot Brain
            <span class="ml-1 text-xs font-normal text-gray-400 dark:text-gray-500">Claude · reflections &amp; decisions</span>
        </h3>
        <div class="flex items-center gap-2 flex-wrap">
            <span id="gtb-mode-badge" class="text-[11px] font-bold uppercase px-2.5 py-1 rounded-full"></span>
            <span id="gtb-capital-chip" class="text-[11px] font-mono px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 dark:bg-gray-900 dark:text-gray-400"></span>
            <span id="gtb-spend-chip" title="Estimated AI spend" class="text-[11px] font-mono px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 dark:bg-gray-900 dark:text-gray-400"></span>
            <button type="button" id="gtb-reflect-btn" onclick="gtbReflect()"
                    class="inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-lg bg-primary text-white hover:bg-primary/90 disabled:opacity-60">
                <i class="fas fa-brain"></i> Reflect
            </button>
        </div>
    </div>

    <!-- Bot control row -->
    <div class="flex flex-wrap items-center gap-2 mb-3">
        <button type="button" id="gtb-step-btn" onclick="gtbStep()"
                class="inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:border-primary hover:text-primary disabled:opacity-60">
            <i class="fas fa-forward-step"></i> Step once
        </button>
        <button type="button" id="gtb-run-btn" onclick="gtbToggleBot()"
                class="inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-lg bg-green-600 text-white hover:bg-green-700">
            <i class="fas fa-play"></i> Start bot
        </button>
        <label id="gtb-arm-wrap" class="hidden inline-flex items-center gap-1.5 text-xs font-semibold text-red-600 dark:text-red-400 cursor-pointer">
            <input type="checkbox" id="gtb-arm-live" onchange="gtbSetArmLive()" <?= ($botArmLive ?? false) ? 'checked' : '' ?> class="w-4 h-4 rounded border-red-400 text-red-600 focus:ring-red-500">
            Arm LIVE trading (real money)
        </label>
        <span id="gtb-action" class="text-xs text-gray-500 dark:text-gray-400"></span>
    </div>

    <div id="gtb-brain-hint" class="hidden mb-3 rounded-lg border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 p-3 text-xs text-amber-800 dark:text-amber-200">
        No Anthropic API key set — add it on the <a href="/gtb-settings" class="font-semibold underline">API Settings</a> page to enable the AI brain.
    </div>
    <div id="gtb-position" class="hidden mb-3 rounded-lg border border-primary/30 bg-primary/5 p-3 text-sm"></div>
    <div id="gtb-brain-feed" class="space-y-3 max-h-[420px] overflow-y-auto pr-1">
        <div class="py-8 text-center text-gray-400 dark:text-gray-500 text-sm">
            <i class="fas fa-spinner fa-spin mr-1"></i> Loading…
        </div>
    </div>
    <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
        Advisory only — the brain reflects and decides out loud; it does not place orders. Deterministic strategy + hard risk rules come next.
    </p>
</section>

<!-- Active Trades: live monitoring grid with per-trade mini charts -->
<section class="mt-6 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            <i class="fas fa-layer-group text-primary mr-2"></i>Active Trades
            <span id="gtb-port-summary" class="ml-2 text-xs font-normal text-gray-400 dark:text-gray-500"></span>
        </h3>
        <span id="gtb-port-unreal" class="text-sm font-bold"></span>
    </div>
    <div id="gtb-trades-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        <div data-empty class="col-span-full py-10 text-center text-gray-400 dark:text-gray-500 text-sm">
            No open positions — start the bot to trade.
        </div>
    </div>
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
                            <th class="py-2 font-medium text-right" title="Realized P&amp;L, net of Binance buy+sell fees (~0.2% round-trip)">P&amp;L <span class="text-gray-400 font-normal">(net)</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php
                        // Each stored row is one position; expand it into its BUY (entry) and,
                        // once closed, its SELL (exit) so both sides of every trade are logged.
                        $events = [];
                        foreach ($recentTrades as $t) {
                            $events[] = ['time' => $t['created_at'] ?? '', 'symbol' => $t['symbol'] ?? '',
                                'side' => 'BUY', 'type' => $t['type'] ?? 'MARKET',
                                'price' => $t['price'] ?? null, 'qty' => $t['qty'] ?? '', 'pnl' => null];
                            if (($t['status'] ?? '') === 'CLOSED' && ($t['exit_price'] ?? null) !== null) {
                                $events[] = ['time' => $t['closed_at'] ?? ($t['created_at'] ?? ''), 'symbol' => $t['symbol'] ?? '',
                                    'side' => 'SELL', 'type' => 'MARKET',
                                    'price' => $t['exit_price'], 'qty' => $t['qty'] ?? '', 'pnl' => $t['realized_pnl'] ?? null];
                            }
                        }
                        usort($events, fn($a, $b) => strcmp((string) $b['time'], (string) $a['time']));
                        foreach (array_slice($events, 0, 30) as $t):
                            $pnl = $t['pnl'];
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
    const GTB = { symbol: 'BTCUSDT', base: 'BTC', interval: '1h', chart: null, series: null,
                  markets: { hot: [], gainers: [], losers: [] },
                  // sort direction by 24h change per column (null = as-loaded: hot by volume)
                  sortDir: { hot: null, gainers: 'desc', losers: 'asc' } };

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
    function gtbChartTheme() {
        const dark = gtbIsDark();
        const grid = dark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
        return {
            layout: { background: { color: 'transparent' }, textColor: dark ? '#9ca3af' : '#374151', fontSize: 11 },
            grid: { vertLines: { color: grid }, horzLines: { color: grid } },
            rightPriceScale: { borderColor: grid },
            timeScale: { borderColor: grid, timeVisible: true, secondsVisible: false },
        };
    }
    // Called by the header theme toggle (defined in head.php).
    function gtbApplyChartTheme() { if (GTB.chart) GTB.chart.applyOptions(gtbChartTheme()); }

    function gtbInitChart() {
        const el = document.getElementById('gtb-chart');
        if (!el || typeof LightweightCharts === 'undefined') return;
        GTB.chart = LightweightCharts.createChart(el, Object.assign({
            width: el.clientWidth, height: 360,
            crosshair: { mode: LightweightCharts.CrosshairMode ? LightweightCharts.CrosshairMode.Normal : 0 },
            handleScale: true, handleScroll: true,
        }, gtbChartTheme()));
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

    // ---- Top movers: Hot / Gainers / Losers (three visible columns) -----------
    function gtbMoverRow(m) {
        const up = m.changePct >= 0;
        return `<button type="button" class="gtb-pair w-full flex items-center justify-between px-2.5 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-left transition-colors"
                  data-symbol="${m.symbol}" data-base="${m.base}" data-price="${m.price}" data-change="${m.changePct}">
                <span class="font-medium text-gray-800 dark:text-gray-200 text-sm">${m.base}<span class="text-gray-400 text-xs font-normal">/USDT</span></span>
                <span class="text-right leading-tight">
                    <span class="block text-sm tabular-nums text-gray-800 dark:text-gray-100">$${gtbFmtPrice(m.price)}</span>
                    <span class="block text-[11px] ${gtbChgClass(up)}">${up ? '+' : ''}${(+m.changePct).toFixed(2)}%</span>
                </span></button>`;
    }

    function gtbSortList(list, dir) {
        if (!dir) return list; // null = keep server order (hot: by volume)
        return list.slice().sort((a, b) => dir === 'desc' ? b.changePct - a.changePct : a.changePct - b.changePct);
    }

    function gtbFillCol(col) {
        const box = document.getElementById('gtb-' + col);
        if (!box) return;
        const list = gtbSortList(GTB.markets[col] || [], GTB.sortDir[col]);
        if (!list.length) { box.innerHTML = '<div class="py-4 text-center text-gray-400 text-xs">No data</div>'; return; }
        box.innerHTML = list.slice(0, 12).map(gtbMoverRow).join('');
        box.querySelectorAll('.gtb-pair').forEach(btn => {
            btn.addEventListener('click', () => gtbSelectSymbol(btn.dataset.symbol, btn.dataset.base, btn.dataset.price, btn.dataset.change));
            btn.classList.toggle('gtb-pair-active', btn.dataset.symbol === GTB.symbol);
        });
        gtbUpdateSortArrow(col);
    }

    function gtbUpdateSortArrow(col) {
        const el = document.getElementById('gtb-sort-' + col);
        if (!el) return;
        const dir = GTB.sortDir[col];
        el.className = 'fas ' + (dir === 'desc' ? 'fa-arrow-down text-primary'
                              : dir === 'asc' ? 'fa-arrow-up text-primary'
                              : 'fa-sort');
    }

    function gtbToggleSort(col) {
        GTB.sortDir[col] = GTB.sortDir[col] === 'desc' ? 'asc' : 'desc';
        gtbFillCol(col);
    }

    function gtbInitSort() {
        document.querySelectorAll('.gtb-sort-btn').forEach(btn =>
            btn.addEventListener('click', () => gtbToggleSort(btn.dataset.col)));
    }

    function gtbRenderMovers() {
        gtbFillCol('hot');
        gtbFillCol('gainers');
        gtbFillCol('losers');
    }

    async function gtbLoadMovers() {
        try {
            const res = await fetch('/gtb/markets');
            const d = await res.json();
            if (!d.ok) return;
            GTB.markets = { hot: d.hot || [], gainers: d.gainers || [], losers: d.losers || [] };
            gtbRenderMovers();
            const first = GTB.markets.hot[0];
            if (first) gtbSelectSymbol(first.symbol, first.base, first.price, first.changePct);
        } catch (e) { /* ignore */ }
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
            const bvn = document.getElementById('gtb-balance-note');
            const hv = document.getElementById('gtb-holdings-value');
            if (!d.ok) { pvn.textContent = d.error || 'Not connected'; return; }
            pv.textContent = gtbFmtUsd(d.portfolioUsdt); pv.className = 'mt-2 text-2xl font-bold text-gray-900 dark:text-white';
            pvn.textContent = (d.testnet ? 'Testnet' : 'Mainnet') + ' · est. USDT (incl. Earn)';
            bv.textContent = gtbFmtUsd(d.freeUsdt); bv.className = 'mt-2 text-2xl font-bold text-gray-900 dark:text-white';
            bvn.textContent = (+d.earnUsdt > 0)
                ? `Free to trade · ${gtbFmtUsd(d.earnUsdt)} in Earn`
                : 'Free USDT (spot)';
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

    // ---- Bot Brain: reflections + capital ------------------------------------
    const GTB_CSRF = <?= json_encode($csrf_token ?? '') ?>;
    const GTB_TESTNET = <?= ($isTestnet ?? false) ? 'true' : 'false' ?>;
    const GTB_BOT = {
        enabled: <?= ($botEnabled ?? false) ? 'true' : 'false' ?>,
        open_new: <?= (!isset($botOpenNew) || $botOpenNew) ? 'true' : 'false' ?>,
        arm_live: <?= ($botArmLive ?? false) ? 'true' : 'false' ?>,
    };

    function gtbModeBadge() {
        const b = document.getElementById('gtb-mode-badge');
        if (GTB_TESTNET) { b.textContent = 'Paper (testnet)'; b.className = 'text-[11px] font-bold uppercase px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400'; }
        else { b.textContent = 'LIVE (real funds)'; b.className = 'text-[11px] font-bold uppercase px-2.5 py-1 rounded-full bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400'; }
        document.getElementById('gtb-arm-wrap').classList.toggle('hidden', GTB_TESTNET);
    }

    function gtbRenderState(d) {
        if (d.action) document.getElementById('gtb-action').textContent = '→ ' + d.action;
        if (d.capital) {
            const c = d.capital;
            document.getElementById('gtb-capital-chip').textContent =
                `tradable $${(+c.tradable).toFixed(2)} · size $${(+c.perTradeSize).toFixed(2)} · slots ${c.slots}`;
        }
        const pos = document.getElementById('gtb-position');
        if (d.position) {
            const p = d.position, up = (+p.unrealized) >= 0;
            pos.classList.remove('hidden');
            pos.innerHTML =
                `<div class="flex flex-wrap items-center justify-between gap-2">
                   <div><span class="font-bold text-gray-900 dark:text-white">${p.symbol}</span>
                     <span class="ml-2 text-[10px] font-bold uppercase px-1.5 py-0.5 rounded ${p.mode==='live'?'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400':'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400'}">${p.mode}</span></div>
                   <div class="font-semibold ${up?'text-green-600 dark:text-green-400':'text-red-500 dark:text-red-400'}">${up?'+':''}$${(+p.unrealized).toFixed(4)}</div>
                 </div>
                 <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 tabular-nums">
                   entry $${(+p.entry).toPrecision(6)} · mark $${(+p.mark).toPrecision(6)} · SL $${(+p.stop_loss).toPrecision(6)} · TP $${(+p.take_profit).toPrecision(6)}
                 </div>`;
        } else {
            pos.classList.add('hidden');
        }
    }

    async function gtbStep() {
        const btn = document.getElementById('gtb-step-btn');
        const armed = !GTB_TESTNET && document.getElementById('gtb-arm-live').checked;
        const wasDisabled = btn.disabled;
        btn.disabled = true;
        try {
            const res = await fetch('/gtb/bot/step', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': GTB_CSRF },
                body: JSON.stringify({ csrf_token: GTB_CSRF, arm_live: armed }),
            });
            const d = await res.json();
            if (d.ok) gtbRenderState(d);
            else document.getElementById('gtb-action').textContent = '✗ ' + (d.error || 'step failed');
            await gtbLoadThoughts();
            gtbLoadPositions();
        } catch (e) {
            document.getElementById('gtb-action').textContent = '✗ ' + e.message;
        } finally {
            btn.disabled = wasDisabled;
        }
    }

    // Persist Arm-LIVE independently of Start/Stop so the state survives reloads and
    // takes effect on the next runner step (arm or disarm real-money trading live).
    async function gtbSetArmLive() {
        if (GTB_TESTNET) return;
        const armed = document.getElementById('gtb-arm-live').checked;
        if (armed && !confirm('Arm LIVE trading? The bot may place REAL orders with real money (capped at your $7 base, every trade with an exchange-side stop).')) {
            document.getElementById('gtb-arm-live').checked = false;
            return;
        }
        GTB_BOT.arm_live = armed;
        try {
            const res = await fetch('/gtb/bot/control', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': GTB_CSRF },
                body: JSON.stringify({ csrf_token: GTB_CSRF, action: 'arm', arm_live: armed }),
            });
            const d = await res.json();
            if (d.ok) GTB_BOT.arm_live = !!d.bot.arm_live;
        } catch (e) { /* ignore */ }
    }

    // Three states: stopped -> [Start]; running -> [Stop bot] (wind down); winding down -> [Force stop].
    function gtbBotStateName() {
        if (!GTB_BOT.enabled) return 'stopped';
        return GTB_BOT.open_new ? 'running' : 'winddown';
    }
    function gtbSetRunBtn() {
        const btn = document.getElementById('gtb-run-btn');
        const st = gtbBotStateName();
        if (st === 'running') {
            btn.innerHTML = '<i class="fas fa-stop"></i> Stop bot';
            btn.className = 'inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-lg bg-red-600 text-white hover:bg-red-700';
            btn.title = 'Wind down: stop taking new trades, keep managing open ones to a good exit.';
        } else if (st === 'winddown') {
            btn.innerHTML = '<i class="fas fa-hand"></i> Force stop (sell all)';
            btn.className = 'inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-lg bg-red-700 text-white hover:bg-red-800';
            btn.title = 'Sell every open position at market now and fully stop.';
        } else {
            btn.innerHTML = '<i class="fas fa-play"></i> Start bot';
            btn.className = 'inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-lg bg-green-600 text-white hover:bg-green-700';
            btn.title = 'Start the bot: open + manage trades.';
        }
    }

    // Start / Stop(wind down) / Force stop(flatten) drive the PERSISTED server-side runner.
    async function gtbToggleBot() {
        const st = gtbBotStateName();
        let action;
        if (st === 'stopped') {
            action = 'start';
            if (!GTB_TESTNET && !document.getElementById('gtb-arm-live').checked) {
                if (!confirm('LIVE mode: the bot will place REAL orders with real money (capped at your $7 base, with stop-losses). Tick "Arm LIVE trading" first, then start.')) return;
            }
        } else if (st === 'running') {
            action = 'stop'; // graceful wind-down
            if (!confirm('Wind down?\n\nThe bot will STOP taking new trades but keep managing your open positions to a good exit (trailing stops, targets, time-box), then fully stop once flat.\n\nYour exchange-side stops stay in place the whole time.')) return;
        } else { // winddown -> force stop
            action = 'flatten';
            if (!confirm('Force stop?\n\nThis SELLS every open position at market right now and fully stops the bot. Use only if you want out immediately.')) return;
        }
        const armed = !GTB_TESTNET && document.getElementById('gtb-arm-live').checked;
        const btn = document.getElementById('gtb-run-btn');
        btn.disabled = true;
        try {
            const res = await fetch('/gtb/bot/control', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': GTB_CSRF },
                body: JSON.stringify({ csrf_token: GTB_CSRF, action: action, arm_live: armed }),
            });
            const d = await res.json();
            if (d.ok) { GTB_BOT.enabled = !!d.bot.enabled; GTB_BOT.open_new = !!d.bot.open_new; gtbSetRunBtn(); }
            gtbLoadPositions();
        } catch (e) { /* ignore */ } finally { btn.disabled = false; }
    }


    function gtbBrainBubble(t) {
        const isClaude = t.role === 'claude';
        const isErr = t.phase === 'error' || t.role === 'system';
        const icon = isClaude ? 'fa-robot text-primary' : (isErr ? 'fa-triangle-exclamation text-amber-500' : 'fa-circle-info text-gray-400');
        const who = isClaude ? 'Claude' : (isErr ? 'system' : 'bot');
        let dec = '';
        if (t.decision) {
            const buy = /^BUY/.test(t.decision);
            dec = `<span class="ml-2 text-[10px] font-bold uppercase px-2 py-0.5 rounded ${buy ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'}">${t.decision}</span>`;
        }
        const body = (t.message || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/\n/g,'<br>');
        return `<div class="flex gap-2.5">
            <i class="fas ${icon} mt-1"></i>
            <div class="flex-1 min-w-0">
                <div class="text-xs text-gray-400 dark:text-gray-500">${who}${dec}<span class="ml-2">${(t.created_at||'')}</span></div>
                <div class="mt-0.5 text-sm text-gray-700 dark:text-gray-200 leading-relaxed">${body}</div>
            </div></div>`;
    }

    async function gtbLoadThoughts() {
        const feed = document.getElementById('gtb-brain-feed');
        try {
            const res = await fetch('/gtb/thoughts');
            const d = await res.json();
            if (!d.ok) return;
            document.getElementById('gtb-brain-hint').classList.toggle('hidden', !!d.brainReady);
            if (d.capital) {
                const c = d.capital;
                document.getElementById('gtb-capital-chip').textContent =
                    `tradable $${(+c.tradable).toFixed(2)} · size $${(+c.perTradeSize).toFixed(2)} · slots ${c.slots}`;
            }
            if (d.spend) {
                document.getElementById('gtb-spend-chip').innerHTML =
                    `<i class="fas fa-coins"></i> $${(+d.spend.total).toFixed(4)} · ${d.spend.count} refl`;
            }
            if (!d.thoughts.length) {
                feed.innerHTML = '<div class="py-8 text-center text-gray-400 dark:text-gray-500 text-sm">No reflections yet — hit “Reflect now”.</div>';
            } else {
                feed.innerHTML = d.thoughts.map(gtbBrainBubble).join('');
                feed.scrollTop = feed.scrollHeight;
            }
        } catch (e) { /* ignore */ }
    }

    async function gtbReflect() {
        const btn = document.getElementById('gtb-reflect-btn');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Thinking…';
        try {
            const res = await fetch('/gtb/bot/reflect', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': GTB_CSRF },
                body: JSON.stringify({ csrf_token: GTB_CSRF }),
            });
            const d = await res.json();
            if (!d.ok) {
                const feed = document.getElementById('gtb-brain-feed');
                feed.insertAdjacentHTML('beforeend',
                    `<div class="text-sm text-red-500"><i class="fas fa-triangle-exclamation"></i> ${d.error || 'Reflection failed'}</div>`);
            }
            await gtbLoadThoughts();
        } catch (e) {
            /* ignore */
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }

    // ---- Active Trades grid (per-trade mini charts) ---------------------------
    const GTB_TRADES = { cards: {} };  // id -> { root, chart, series, lastKey }

    function gtbTemplLabel(k) { return ({scalp:'Scalp', breakout:'Breakout', trend:'Trend', pullback:'Pullback'})[k] || k; }
    function gtbProfLabel(k) { return ({conservative:'Conservative', aggressive:'Aggressive'})[k] || k; }
    function gtbProfBadge(k) {
        const cls = k === 'aggressive'
            ? 'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-400'
            : 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-400';
        return `<span class="ml-1 text-[9px] font-bold uppercase px-1.5 py-0.5 rounded ${cls}">${gtbProfLabel(k)}</span>`;
    }

    async function gtbLoadPositions() {
        try {
            const res = await fetch('/gtb/bot/positions');
            const d = await res.json();
            if (!d.ok) return;
            gtbRenderPortfolio(d);
            gtbRenderGrid(d.positions || []);
        } catch (e) { /* ignore */ }
    }

    function gtbRenderPortfolio(d) {
        if (d.bot) {
            GTB_BOT.enabled = !!d.bot.enabled;
            GTB_BOT.open_new = ('open_new' in d.bot) ? !!d.bot.open_new : true;
            // Reflect persisted arm-live (unless the user is mid-toggle on the checkbox).
            const armEl = document.getElementById('gtb-arm-live');
            if (armEl && 'arm_live' in d.bot && document.activeElement !== armEl) {
                armEl.checked = !!d.bot.arm_live;
                GTB_BOT.arm_live = !!d.bot.arm_live;
            }
            gtbSetRunBtn();
            const act = document.getElementById('gtb-action');
            const st = gtbBotStateName();
            const dot = st === 'running' ? '● running' : (st === 'winddown' ? '◐ winding down' : '○ stopped');
            if (act && d.bot.last_action) act.textContent = dot + ' · ' + d.bot.last_action;
        }
        const p = d.portfolio || {};
        const sum = document.getElementById('gtb-port-summary');
        if (sum) sum.textContent = `${p.open||0}/${p.slots||0} slots · realized $${(+(p.realized||0)).toFixed(2)}`;
        const un = document.getElementById('gtb-port-unreal');
        if (un) {
            const v = +(p.unrealized || 0), up = v >= 0;
            un.textContent = `Unrealized ${up?'+':''}$${v.toFixed(4)}`;
            un.className = 'text-sm font-bold ' + (up ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400');
        }
    }

    function gtbRenderGrid(positions) {
        const grid = document.getElementById('gtb-trades-grid');
        const ids = new Set(positions.map(p => String(p.id)));
        // remove closed
        Object.keys(GTB_TRADES.cards).forEach(id => {
            if (!ids.has(id)) {
                const c = GTB_TRADES.cards[id];
                try { c.chart && c.chart.remove(); } catch (e) {}
                c.root && c.root.remove();
                delete GTB_TRADES.cards[id];
            }
        });
        // empty state
        const empty = grid.querySelector('[data-empty]') || (positions.length === 0 && !Object.keys(GTB_TRADES.cards).length ? grid : null);
        if (positions.length === 0) {
            grid.innerHTML = '<div data-empty class="col-span-full py-10 text-center text-gray-400 dark:text-gray-500 text-sm">No open positions — start the bot to trade.</div>';
            return;
        }
        const emptyEl = grid.querySelector('[data-empty]');
        if (emptyEl) emptyEl.remove();
        // add / update
        positions.forEach(p => {
            if (!GTB_TRADES.cards[String(p.id)]) gtbCreateTradeCard(grid, p);
            gtbUpdateTradeCard(p);
        });
    }

    function gtbCreateTradeCard(grid, p) {
        const root = document.createElement('div');
        root.className = 'rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/40 p-3';
        root.innerHTML =
            `<div class="flex items-center justify-between mb-1">
               <div class="font-bold text-gray-900 dark:text-white">${p.symbol.replace('USDT','')}<span class="text-gray-400 text-xs font-normal">/USDT</span>
                 ${gtbProfBadge(p.profile)}
                 <span class="ml-1 text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-primary/10 text-primary">${gtbTemplLabel(p.template)}</span>
                 <span class="ml-1 text-[9px] font-bold uppercase px-1.5 py-0.5 rounded ${p.mode==='live'?'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400':'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400'}">${p.mode}</span>
                 ${p.mode==='live' ? (p.protected
                    ? '<span title="Stop-loss resting on Binance" class="ml-1 text-[9px] text-green-600 dark:text-green-400"><i class="fas fa-shield-halved"></i></span>'
                    : '<span title="No exchange stop yet" class="ml-1 text-[9px] text-red-500"><i class="fas fa-triangle-exclamation"></i></span>') : ''}
               </div>
               <div class="text-right leading-tight">
                 <span data-pnl class="block text-sm font-bold"></span>
                 <span data-pct class="block text-[11px]"></span>
               </div>
             </div>
             <div data-chart class="w-full rounded overflow-hidden" style="height:140px"></div>
             <div class="mt-1.5 grid grid-cols-3 gap-1 text-[10px] tabular-nums text-center">
               <div class="text-gray-500 dark:text-gray-400">entry<br><span data-entry class="text-gray-800 dark:text-gray-200"></span></div>
               <div class="text-red-500">SL<br><span data-sl></span></div>
               <div class="text-green-600 dark:text-green-400">TP<br><span data-tp></span></div>
             </div>
             <button data-close class="mt-1.5 w-full text-[11px] font-medium py-1 rounded border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:border-red-400 hover:text-red-500 transition-colors">
               <i class="fas fa-xmark mr-1"></i>Close now
             </button>`;
        grid.appendChild(root);
        root.querySelector('[data-close]').addEventListener('click', () => gtbClosePosition(p.id, p.symbol));
        const el = root.querySelector('[data-chart]');
        const card = { root, chart: null, series: null, lastKey: '' };
        GTB_TRADES.cards[String(p.id)] = card;
        if (typeof LightweightCharts !== 'undefined') {
            card.chart = LightweightCharts.createChart(el, Object.assign({ width: el.clientWidth, height: 140,
                crosshair: { mode: 0 }, handleScale: false, handleScroll: false,
                rightPriceScale: { visible: true }, timeScale: { visible: false } }, gtbChartTheme()));
            card.series = card.chart.addCandlestickSeries({ upColor: '#16a34a', downColor: '#ef4444', borderVisible: false, wickUpColor: '#16a34a', wickDownColor: '#ef4444' });
            card.series.createPriceLine({ price: p.entry, color: '#3b82f6', lineWidth: 1, lineStyle: 2, title: 'entry' });
            card.series.createPriceLine({ price: p.stop_loss, color: '#ef4444', lineWidth: 1, lineStyle: 2, title: 'SL' });
            if (p.take_profit) card.series.createPriceLine({ price: p.take_profit, color: '#16a34a', lineWidth: 1, lineStyle: 2, title: 'TP' });
            new ResizeObserver(() => { try { card.chart.applyOptions({ width: el.clientWidth }); } catch (e) {} }).observe(el);
            fetch(`/gtb/klines?symbol=${encodeURIComponent(p.symbol)}&interval=5m`)
                .then(r => r.json()).then(d => {
                    if (d.ok && card.series) { card.series.setData(d.candles.map(c => ({ time: c.time, open: c.open, high: c.high, low: c.low, close: c.close }))); card.chart.timeScale().fitContent(); }
                }).catch(() => {});
        }
    }

    function gtbUpdateTradeCard(p) {
        const card = GTB_TRADES.cards[String(p.id)];
        if (!card) return;
        const up = (+p.unrealized) >= 0;
        const pnl = card.root.querySelector('[data-pnl]'); const pct = card.root.querySelector('[data-pct]');
        pnl.textContent = `${up?'+':''}$${(+p.unrealized).toFixed(4)}`;
        pnl.className = 'block text-sm font-bold ' + (up ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400');
        pct.textContent = `${up?'+':''}${(+p.pnlPct).toFixed(2)}% · $${(+p.mark).toPrecision(6)}`;
        pct.className = 'block text-[11px] ' + (up ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400');
        card.root.querySelector('[data-entry]').textContent = '$' + (+p.entry).toPrecision(6);
        card.root.querySelector('[data-sl]').textContent = '$' + (+p.stop_loss).toPrecision(6);
        card.root.querySelector('[data-tp]').textContent = p.take_profit ? '$' + (+p.take_profit).toPrecision(6) : 'trail';
    }

    async function gtbClosePosition(id, symbol) {
        if (!confirm('Close ' + symbol + ' now at market?')) return;
        try {
            const res = await fetch('/gtb/bot/close', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': GTB_CSRF },
                body: JSON.stringify({ csrf_token: GTB_CSRF, id }),
            });
            const d = await res.json();
            if (!d.ok) alert('Close failed: ' + (d.error || 'unknown'));
        } catch (e) { alert(e.message); }
        gtbLoadPositions();
        gtbLoadThoughts();
    }

    document.addEventListener('DOMContentLoaded', () => {
        gtbInitChart();
        gtbInitIntervals();
        gtbInitSort();
        gtbLoadMovers();
        gtbModeBadge();
        gtbSetRunBtn();  // paint Start/Stop from persisted state on load
        gtbLoadThoughts();
        gtbLoadPositions();
        setInterval(gtbLoadPositions, 6000);
        if (GTB_API_CONFIGURED) gtbLoadAccount();
    });
</script>

<style>
    .gtb-pair-active { background: rgba(99,102,241,0.10); box-shadow: inset 2px 0 0 #6366f1; }
</style>
