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
        <div class="flex items-center gap-2">
            <span id="gtb-chart-icon" class="inline-flex"></span>
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
        <button type="button" id="gtb-forcestop-btn" onclick="gtbForceStop()" title="Sell every open position at market now and fully stop."
                class="hidden inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-lg border border-red-300 dark:border-red-700 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10">
            <i class="fas fa-hand"></i> Force stop
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

<!-- AI System Prompt: inject a strategy template or your own; overrides Settings -->
<section class="mb-6 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/40 p-5">
    <div class="flex items-center justify-between mb-1">
        <h2 class="font-bold text-gray-900 dark:text-white"><i class="fas fa-wand-magic-sparkles text-primary mr-2"></i>AI System Prompt</h2>
        <span id="gtb-prompt-active" class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-primary/10 text-primary"></span>
    </div>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Click a strategy to inject it, or write your own below. The active prompt <strong>overrides</strong> the Operator instructions in <a href="/gtb-settings" class="underline">Settings</a>. Every strategy still trades only with an exchange-side stop (OCO).</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
        <?php foreach (($promptCards ?? []) as $c): ?>
            <button type="button" data-prompt-key="<?= htmlspecialchars($c['key']) ?>" onclick="gtbInjectPreset('<?= htmlspecialchars($c['key']) ?>')"
                    class="gtb-prompt-card text-left rounded-xl border p-3 transition-colors border-gray-200 dark:border-gray-700 hover:border-primary <?= (($activePromptKey ?? null) === $c['key']) ? 'ring-2 ring-primary border-primary' : '' ?>">
                <div class="font-semibold text-sm text-gray-900 dark:text-white"><?= htmlspecialchars($c['name']) ?></div>
                <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5"><?= htmlspecialchars($c['desc']) ?></div>
                <div class="mt-2 text-[10px] font-bold uppercase text-primary"><i class="fas fa-bolt mr-1"></i>Inject</div>
            </button>
        <?php endforeach; ?>
    </div>
    <div class="mt-3">
        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Or your own custom template prompt</label>
        <textarea id="gtb-custom-prompt" rows="3" maxlength="2000" placeholder="Describe the strategy the AI should follow this session…"
                  class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 focus:ring-primary focus:border-primary"><?= htmlspecialchars((($promptSource ?? '') === 'custom') ? ($activePrompt ?? '') : '', ENT_QUOTES) ?></textarea>
        <div class="flex flex-wrap items-center gap-2 mt-2">
            <button type="button" onclick="gtbInjectCustom()" class="inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-lg bg-primary text-white hover:bg-primary/90"><i class="fas fa-bolt"></i> Inject custom</button>
            <button type="button" onclick="gtbClearPrompt()" class="inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:border-primary hover:text-primary"><i class="fas fa-rotate-left"></i> Clear (use default)</button>
            <span id="gtb-prompt-status" class="text-xs"></span>
        </div>
        <details class="mt-2 text-xs text-gray-400 dark:text-gray-500">
            <summary class="cursor-pointer hover:text-primary">What runs when nothing is injected? (built-in win-focused default)</summary>
            <p class="mt-1 leading-relaxed"><?= htmlspecialchars($houseDefault ?? '') ?></p>
        </details>
    </div>
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
            <?php if (!empty($recentTrades)): ?><span class="text-sm font-normal text-gray-400 dark:text-gray-500">· <?= count($recentTrades) ?> trade<?= count($recentTrades) === 1 ? '' : 's' ?> (# = trade no.)</span><?php endif; ?>
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
                            <th class="py-2 pr-4 font-medium">#</th>
                            <th class="py-2 pr-4 font-medium">Time</th>
                            <th class="py-2 pr-4 font-medium">Symbol</th>
                            <th class="py-2 pr-4 font-medium">Side</th>
                            <th class="py-2 pr-4 font-medium">Type</th>
                            <th class="py-2 pr-4 font-medium text-right">Price</th>
                            <th class="py-2 pr-4 font-medium text-right">Qty</th>
                            <th class="py-2 pr-4 font-medium text-right" title="Capital in/out (price × qty). On a BUY, the ▲/▼ show the possible gain at take-profit and possible loss at stop-loss.">Value <span class="text-gray-400 font-normal">($)</span></th>
                            <th class="py-2 font-medium text-right" title="Realized P&amp;L, net of Binance buy+sell fees (~0.2% round-trip)">P&amp;L <span class="text-gray-400 font-normal">(net)</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php
                        // Each stored row is one position; expand it into its BUY (entry) and,
                        // once closed, its SELL (exit) so both sides of every trade are logged.
                        $events = [];
                        foreach ($recentTrades as $t) {
                            $tid = (int) ($t['id'] ?? 0);
                            $events[] = ['id' => $tid, 'time' => $t['created_at'] ?? '', 'symbol' => $t['symbol'] ?? '',
                                'side' => 'BUY', 'type' => $t['type'] ?? 'MARKET',
                                'price' => $t['price'] ?? null, 'qty' => $t['qty'] ?? '', 'pnl' => null,
                                'sl' => $t['stop_loss'] ?? null, 'tp' => $t['take_profit'] ?? null];
                            if (($t['status'] ?? '') === 'CLOSED' && ($t['exit_price'] ?? null) !== null) {
                                $events[] = ['id' => $tid, 'time' => $t['closed_at'] ?? ($t['created_at'] ?? ''), 'symbol' => $t['symbol'] ?? '',
                                    'side' => 'SELL', 'type' => 'MARKET',
                                    'price' => $t['exit_price'], 'qty' => $t['qty'] ?? '', 'pnl' => $t['realized_pnl'] ?? null];
                            }
                        }
                        usort($events, fn($a, $b) => strcmp((string) $b['time'], (string) $a['time']));
                        // Coin badge: real icon from a CDN, with a colored ticker-initials fallback (robust for meme coins).
                        $coinIcon = function (string $symbol): string {
                            $base = strtolower(preg_replace('/USDT$/', '', $symbol));
                            $up   = strtoupper(substr($base, 0, 3));
                            $h = 0; for ($i = 0; $i < strlen($base); $i++) $h = ($h * 31 + ord($base[$i])) & 0xFFFFFFFF;
                            $hue = $h % 360;   // same rolling hash as JS gtbCoinIcon so colors match
                            $url  = 'https://cdn.jsdelivr.net/gh/atomiclabs/cryptocurrency-icons@1a63530be6e374711a8554f31b17e4cb92c25fa5/128/color/' . htmlspecialchars($base) . '.png';
                            return '<span class="relative inline-flex w-6 h-6 shrink-0 align-middle">'
                                . '<span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-[8px] font-bold text-white" style="background:hsl(' . $hue . ',55%,45%)">' . htmlspecialchars($up) . '</span>'
                                . '<img src="' . $url . '" alt="" loading="lazy" class="absolute inset-0 w-6 h-6 rounded-full object-cover" onerror="this.remove()">'
                                . '</span>';
                        };
                        foreach (array_slice($events, 0, 30) as $t):
                            $pnl = $t['pnl'];
                            $pnlNeg = $pnl !== null && (float)$pnl < 0; ?>
                            <tr class="text-gray-800 dark:text-gray-200">
                                <td class="py-2.5 pr-4 text-gray-400 dark:text-gray-500 font-mono text-xs">#<?= (int)($t['id'] ?? 0) ?></td>
                                <td class="py-2.5 pr-4 text-gray-500 dark:text-gray-400 whitespace-nowrap"><?= htmlspecialchars(\Ginto\Models\GtbThought::manilaTime($t['time'] ?? '')) ?></td>
                                <td class="py-2.5 pr-4 font-medium"><span class="inline-flex items-center gap-2"><?= $coinIcon($t['symbol'] ?? '') ?><span><?= htmlspecialchars($t['symbol'] ?? '') ?></span></span></td>
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
                                <td class="py-2.5 pr-4 text-right tabular-nums">
                                    <?php
                                    $ev = ($t['price'] !== null && $t['qty'] !== '' && $t['qty'] !== null) ? (float) $t['price'] * (float) $t['qty'] : null;
                                    echo $ev !== null ? '$' . number_format($ev, 2) : '—';
                                    if (($t['side'] ?? '') === 'BUY' && $ev !== null):
                                        $e = (float) $t['price']; $tp = $t['tp'] ?? null; $sl = $t['sl'] ?? null;
                                        $gp = ($tp !== null && $e > 0) ? ((float) $tp - $e) / $e * 100 : null;
                                        $lp = ($sl !== null && $e > 0) ? ($e - (float) $sl) / $e * 100 : null; ?>
                                        <span class="block text-[10px] whitespace-nowrap">
                                            <span class="text-green-600 dark:text-green-400"><?= $gp !== null ? '▲' . number_format($gp, 1) . '%' : '▲ trail' ?></span>
                                            <?php if ($lp !== null): ?>
                                                <span class="ml-1 <?= $lp >= 0 ? 'text-red-500 dark:text-red-400' : 'text-green-600 dark:text-green-400' ?>"><?= $lp >= 0 ? '▼' . number_format($lp, 1) . '%' : '▲' . number_format(abs($lp), 1) . '% lock' ?></span>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2.5 text-right tabular-nums font-semibold <?= $pnl === null ? 'text-gray-400' : ($pnlNeg ? 'text-red-500 dark:text-red-400' : 'text-green-600 dark:text-green-400') ?>">
                                    <?php if ($pnl === null): ?>—<?php else: $pv = (float) $pnl; ?>
                                        <span class="inline-flex items-center justify-end gap-1" title="<?= $pv >= 0 ? 'Win' : 'Loss' ?>">
                                            <i class="fas fa-<?= $pv >= 0 ? 'caret-up' : 'caret-down' ?>"></i><?= ($pv >= 0 ? '+' : '−') . htmlspecialchars(number_format(abs($pv), 4)) ?>
                                        </span>
                                    <?php endif; ?>
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
        <div id="gtb-log" class="max-h-[420px] overflow-y-auto pr-1">
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
                            <span class="text-gray-400 dark:text-gray-500 font-mono text-xs mr-1">#<?= (int)($log['id'] ?? 0) ?></span>
                            <span class="text-gray-700 dark:text-gray-300"><?= htmlspecialchars($log['message'] ?? '') ?></span>
                            <span class="block text-xs text-gray-400 dark:text-gray-500"><?= htmlspecialchars($log['created_at'] ?? '') ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        </div>
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
    // Compact amount in the coin's own units (for P&L expressed in the traded currency).
    function gtbFmtCoin(x) { x = +x; const a = Math.abs(x); if (!a) return '0'; if (a >= 1000) return x.toFixed(1); if (a >= 1) return x.toFixed(3); if (a >= 0.001) return x.toFixed(5); return x.toPrecision(3); }
    // Decimal places the chart price scale needs so low-priced coins don't collapse to "0.01".
    function gtbPricePrecision(px) {
        px = Math.abs(+px) || 0;
        if (px >= 1000) return 2;
        if (px >= 1)    return 4;
        if (px >= 0.1)  return 5;
        if (px >= 0.01) return 6;
        if (px >= 0.0001) return 7;
        return 8;
    }
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
        const icoEl = document.getElementById('gtb-chart-icon');
        if (icoEl) icoEl.innerHTML = gtbCoinIcon(base, 24);
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
    // Coin badge: real icon from a CDN with a colored ticker-initials fallback (robust for meme coins).
    function gtbCoinIcon(base, size) {
        base = (base || '').toString();
        const s = base.toLowerCase();
        const up = base.substring(0, 3).toUpperCase();
        let h = 0; for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0;
        const hue = h % 360;
        const px = size || 20;
        const url = 'https://cdn.jsdelivr.net/gh/atomiclabs/cryptocurrency-icons@1a63530be6e374711a8554f31b17e4cb92c25fa5/128/color/' + s + '.png';
        return `<span class="relative inline-flex shrink-0 align-middle" style="width:${px}px;height:${px}px">`
            + `<span class="inline-flex items-center justify-center rounded-full font-bold text-white" style="width:${px}px;height:${px}px;font-size:${Math.round(px*0.38)}px;background:hsl(${hue},55%,45%)">${up}</span>`
            + `<img src="${url}" alt="" loading="lazy" class="absolute inset-0 rounded-full object-cover" style="width:${px}px;height:${px}px" onerror="this.remove()">`
            + `</span>`;
    }

    function gtbMoverRow(m) {
        const up = m.changePct >= 0;
        return `<button type="button" class="gtb-pair w-full flex items-center justify-between px-2.5 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-left transition-colors"
                  data-symbol="${m.symbol}" data-base="${m.base}" data-price="${m.price}" data-change="${m.changePct}">
                <span class="flex items-center gap-2 font-medium text-gray-800 dark:text-gray-200 text-sm">${gtbCoinIcon(m.base)}<span>${m.base}<span class="text-gray-400 text-xs font-normal">/USDT</span></span></span>
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

    // ---- AI system prompt picker (inject preset / custom, or clear to Settings default) ----
    const GTB_PROMPT_NAMES = <?= json_encode(array_column($promptCards ?? [], 'name', 'key')) ?>;
    function gtbPromptActiveLabel(source, key) {
        if (source === 'house') return 'House default (win-focused)';
        if (source === 'settings') return 'Settings default';
        if (source === 'default') return 'Settings default';
        if (source === 'custom') return 'Custom';
        return GTB_PROMPT_NAMES[key] || key;
    }
    function gtbSetPromptActive(source, key) {
        const badge = document.getElementById('gtb-prompt-active');
        if (badge) badge.textContent = 'Active: ' + gtbPromptActiveLabel(source, key);
        document.querySelectorAll('.gtb-prompt-card').forEach(el => {
            const on = (source !== 'default' && source !== 'custom' && el.dataset.promptKey === key);
            el.classList.toggle('ring-2', on);
            el.classList.toggle('ring-primary', on);
            el.classList.toggle('border-primary', on);
        });
    }
    async function gtbPromptPost(body) {
        const s = document.getElementById('gtb-prompt-status');
        if (s) { s.textContent = 'Saving…'; s.className = 'text-xs text-gray-400'; }
        try {
            const res = await fetch('/gtb/bot/prompt', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': GTB_CSRF },
                body: JSON.stringify(Object.assign({ csrf_token: GTB_CSRF }, body)),
            });
            const d = await res.json();
            if (d.ok) { gtbSetPromptActive(d.source, d.preset); if (s) { s.textContent = 'Injected ✓ — applies on the next decision'; s.className = 'text-xs text-green-600 dark:text-green-400'; } }
            else if (s) { s.textContent = d.error || 'Failed'; s.className = 'text-xs text-red-500'; }
        } catch (e) { if (s) { s.textContent = 'Network error'; s.className = 'text-xs text-red-500'; } }
    }
    function gtbInjectPreset(key) { gtbPromptPost({ preset: key }); }
    function gtbInjectCustom() { const t = document.getElementById('gtb-custom-prompt').value.trim(); if (!t) { const s = document.getElementById('gtb-prompt-status'); if (s) { s.textContent = 'Write a prompt first.'; s.className = 'text-xs text-amber-500'; } return; } gtbPromptPost({ custom: t }); }
    function gtbClearPrompt() { gtbPromptPost({ clear: true }); }

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
        const fs = document.getElementById('gtb-forcestop-btn');
        const st = gtbBotStateName();
        if (st === 'running') {
            btn.innerHTML = '<i class="fas fa-stop"></i> Stop bot';
            btn.className = 'inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-lg bg-red-600 text-white hover:bg-red-700';
            btn.title = 'Wind down: stop taking new trades, keep managing open ones to a good exit.';
        } else if (st === 'winddown') {
            btn.innerHTML = '<i class="fas fa-play"></i> Resume trading';
            btn.className = 'inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-lg bg-green-600 text-white hover:bg-green-700';
            btn.title = 'Resume: take new trades again and start a fresh session.';
        } else {
            btn.innerHTML = '<i class="fas fa-play"></i> Start bot';
            btn.className = 'inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-lg bg-green-600 text-white hover:bg-green-700';
            btn.title = 'Start the bot: open + manage trades.';
        }
        // Force-stop (sell all) is available whenever the bot is active (running or winding down).
        if (fs) fs.classList.toggle('hidden', st === 'stopped');
    }

    // Main button: Start / Stop(wind down) / Resume — drives the PERSISTED server-side runner.
    async function gtbToggleBot() {
        const st = gtbBotStateName();
        let action;
        if (st === 'running') {
            action = 'stop'; // graceful wind-down
            if (!confirm('Wind down?\n\nThe bot will STOP taking new trades but keep managing your open positions to a good exit, then fully stop once flat.\n\nYour exchange-side stops stay in place the whole time.')) return;
        } else {
            // stopped OR winddown -> (re)start a fresh trading session
            action = 'start';
            if (!GTB_TESTNET && !document.getElementById('gtb-arm-live').checked) {
                if (!confirm('LIVE mode: the bot will place REAL orders with real money (within your capital + loss limits, every trade with a stop). Tick "Arm LIVE trading" first, then start.')) return;
            }
        }
        gtbBotControl(action, document.getElementById('gtb-run-btn'));
    }

    // Separate Force-stop button: sell everything now and fully stop.
    async function gtbForceStop() {
        if (!confirm('Force stop?\n\nThis SELLS every open position at market right now and fully stops the bot. Use only if you want out immediately.')) return;
        gtbBotControl('flatten', document.getElementById('gtb-forcestop-btn'));
    }

    async function gtbBotControl(action, btn) {
        const armed = !GTB_TESTNET && document.getElementById('gtb-arm-live').checked;
        if (btn) btn.disabled = true;
        try {
            const res = await fetch('/gtb/bot/control', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': GTB_CSRF },
                body: JSON.stringify({ csrf_token: GTB_CSRF, action: action, arm_live: armed }),
            });
            const d = await res.json();
            if (d.ok) { GTB_BOT.enabled = !!d.bot.enabled; GTB_BOT.open_new = !!d.bot.open_new; gtbSetRunBtn(); }
            gtbLoadPositions();
        } catch (e) { /* ignore */ } finally { if (btn) btn.disabled = false; }
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

    function gtbTemplLabel(k) { return ({gainers:'Gainer Hunter', scalp:'Scalp', breakout:'Breakout', trend:'Trend', pullback:'Pullback'})[k] || k; }
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

    function gtbRenderLog(logs) {
        const box = document.getElementById('gtb-log');
        if (!box) return;
        if (!Array.isArray(logs) || logs.length === 0) {
            box.innerHTML = '<div class="py-10 text-center text-gray-400 dark:text-gray-500"><i class="fas fa-inbox text-2xl mb-2"></i><p class="text-sm">No activity yet.</p></div>';
            return;
        }
        const esc = s => (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
        box.innerHTML = '<ul class="space-y-2">' + logs.map(l => {
            const c = l.level === 'error' ? 'text-red-500' : 'text-primary';
            return `<li class="text-sm flex gap-2"><i class="fas fa-circle text-[6px] mt-1.5 ${c}"></i>`
                + `<div><span class="text-gray-400 dark:text-gray-500 font-mono text-xs mr-1">#${l.id || 0}</span>`
                + `<span class="text-gray-700 dark:text-gray-300">${esc(l.message)}</span>`
                + `<span class="block text-xs text-gray-400 dark:text-gray-500">${esc(l.created_at)}</span></div></li>`;
        }).join('') + '</ul>';
    }

    function gtbRenderPortfolio(d) {
        if (d.logs) gtbRenderLog(d.logs);
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
        root.className = 'rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/40 p-3 overflow-hidden min-w-0';
        root.innerHTML =
            `<div class="flex items-start justify-between gap-2 mb-1">
               <div class="min-w-0 flex flex-wrap items-center gap-x-1 gap-y-0.5 font-bold text-gray-900 dark:text-white">${gtbCoinIcon(p.symbol.replace('USDT',''), 18)}<span class="truncate max-w-full">${p.symbol.replace('USDT','')}<span class="text-gray-400 text-xs font-normal">/USDT</span></span>
                 ${gtbProfBadge(p.profile)}
                 <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-primary/10 text-primary">${gtbTemplLabel(p.template)}</span>
                 <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded ${p.mode==='live'?'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400':'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400'}">${p.mode}</span>
                 ${p.mode==='live' ? (p.protected
                    ? '<span title="Stop-loss resting on Binance" class="text-[9px] text-green-600 dark:text-green-400"><i class="fas fa-shield-halved"></i></span>'
                    : '<span title="No exchange stop yet" class="text-[9px] text-red-500"><i class="fas fa-triangle-exclamation"></i></span>') : ''}
               </div>
               <div class="text-right leading-tight shrink-0">
                 <span data-pnl class="block text-sm font-bold whitespace-nowrap"></span>
                 <span data-pct class="block text-[11px] whitespace-nowrap"></span>
               </div>
             </div>
             <div data-chart title="Click to expand & sell" class="w-full rounded overflow-hidden cursor-pointer" style="height:140px"></div>
             <div class="mt-1.5 grid grid-cols-3 gap-1 text-[10px] tabular-nums text-center">
               <div class="text-gray-500 dark:text-gray-400">entry<br><span data-entry class="text-gray-800 dark:text-gray-200"></span></div>
               <div class="text-red-500">SL<br><span data-sl></span></div>
               <div class="text-green-600 dark:text-green-400">TP<br><span data-tp></span></div>
             </div>
             <div data-rr class="mt-1 text-[10px] text-center text-gray-500 dark:text-gray-400 tabular-nums whitespace-nowrap"></div>
             <button data-close class="mt-1.5 w-full text-[11px] font-medium py-1 rounded border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:border-red-400 hover:text-red-500 transition-colors">
               <i class="fas fa-xmark mr-1"></i>Close now
             </button>`;
        grid.appendChild(root);
        root.querySelector('[data-close]').addEventListener('click', () => gtbClosePosition(p.id, p.symbol));
        const el = root.querySelector('[data-chart]');
        const card = { root, chart: null, series: null, lastKey: '', p: p };
        GTB_TRADES.cards[String(p.id)] = card;
        el.addEventListener('click', () => gtbOpenTradeModal(card.p || p));
        if (typeof LightweightCharts !== 'undefined') {
            card.chart = LightweightCharts.createChart(el, Object.assign({ autoSize: true,
                crosshair: { mode: 0 }, handleScale: false, handleScroll: false,
                rightPriceScale: { visible: true }, timeScale: { visible: false } }, gtbChartTheme()));
            const gtbPrec = gtbPricePrecision(p.entry || p.mark || 1);
            card.series = card.chart.addCandlestickSeries({ upColor: '#16a34a', downColor: '#ef4444', borderVisible: false, wickUpColor: '#16a34a', wickDownColor: '#ef4444',
                priceFormat: { type: 'price', precision: gtbPrec, minMove: Math.pow(10, -gtbPrec) } });
            card.series.createPriceLine({ price: p.entry, color: '#3b82f6', lineWidth: 1, lineStyle: 2, title: 'entry' });
            card.series.createPriceLine({ price: p.stop_loss, color: '#ef4444', lineWidth: 1, lineStyle: 2, title: 'SL' });
            if (p.take_profit) card.series.createPriceLine({ price: p.take_profit, color: '#16a34a', lineWidth: 1, lineStyle: 2, title: 'TP' });
            fetch(`/gtb/klines?symbol=${encodeURIComponent(p.symbol)}&interval=5m`)
                .then(r => r.json()).then(d => {
                    if (d.ok && card.series) { card.series.setData(d.candles.map(c => ({ time: c.time, open: c.open, high: c.high, low: c.low, close: c.close }))); card.chart.timeScale().fitContent(); }
                }).catch(() => {});
        }
    }

    function gtbUpdateTradeCard(p) {
        const card = GTB_TRADES.cards[String(p.id)];
        if (!card) return;
        card.p = p;   // keep the freshest data for the expanded view
        // If the expanded modal is open on this position, refresh its live P&L too.
        if (GTB_MODAL.id === p.id) gtbModalSetPnl(p);
        const up = (+p.unrealized) >= 0;
        // Order cards by P&L% (best on top) via CSS order — reflows the grid without moving DOM nodes.
        card.root.style.order = String(Math.round(-(+p.pnlPct || 0) * 100));
        const pnl = card.root.querySelector('[data-pnl]'); const pct = card.root.querySelector('[data-pct]');
        const coinPnl = (+p.mark > 0) ? (+p.unrealized) / (+p.mark) : 0;   // P&L in the coin's own units
        pnl.textContent = `${up?'+':''}$${(+p.unrealized).toFixed(4)}`;
        pnl.className = 'block text-sm font-bold ' + (up ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400');
        pct.textContent = `${up?'+':''}${(+p.pnlPct).toFixed(2)}% · ${coinPnl>=0?'+':''}${gtbFmtCoin(coinPnl)} ${p.symbol.replace('USDT','')}`;
        pct.className = 'block text-[11px] ' + (up ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400');
        card.root.querySelector('[data-entry]').textContent = '$' + (+p.entry).toPrecision(6);
        card.root.querySelector('[data-sl]').textContent = '$' + (+p.stop_loss).toPrecision(6);
        card.root.querySelector('[data-tp]').textContent = p.take_profit ? '$' + (+p.take_profit).toPrecision(6) : 'trail';
        // Capital in · possible gain @TP / possible loss @SL (loss flips to "lock" when stop > entry).
        const rrEl = card.root.querySelector('[data-rr]');
        if (rrEl) {
            const q = +p.qty || 0, e = +p.entry || 0;
            const gp = (p.take_profit && e > 0) ? (+p.take_profit - e) / e * 100 : null;
            const lp = e > 0 ? (e - +p.stop_loss) / e * 100 : 0;
            const rew = gp !== null ? `<span class="text-green-600 dark:text-green-400">+${gp.toFixed(1)}%</span>` : `<span class="text-green-600 dark:text-green-400">trail</span>`;
            const risk = lp < 0 ? `<span class="text-green-600 dark:text-green-400">+${Math.abs(lp).toFixed(1)}% lock</span>` : `<span class="text-red-500 dark:text-red-400">−${lp.toFixed(1)}%</span>`;
            rrEl.innerHTML = `in $${(e * q).toFixed(2)} · ${rew} / ${risk}`;
        }
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

    // ---- Expanded live trade view (on-demand; polls slowly; destroys itself on close) ----
    const GTB_MODAL = { el: null, chart: null, series: null, timer: null, id: null, ms: 15000 };
    try { const s = parseInt(localStorage.getItem('gtbModalMs')); if ([10000, 15000, 20000].includes(s)) GTB_MODAL.ms = s; } catch (e) {}

    function gtbBuildModal() {
        if (GTB_MODAL.el) return GTB_MODAL.el;
        const o = document.createElement('div');
        o.id = 'gtb-trade-modal';
        o.className = 'fixed inset-0 z-[60] hidden items-center justify-center p-3 sm:p-4 bg-black/70';
        o.innerHTML =
            `<div class="w-full max-w-2xl rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-2xl overflow-hidden">
               <div class="flex items-center justify-between gap-2 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                 <div data-m-title class="flex items-center gap-1.5 font-bold text-gray-900 dark:text-white min-w-0 flex-wrap"></div>
                 <button data-m-x class="shrink-0 text-gray-400 hover:text-primary text-lg"><i class="fas fa-xmark"></i></button>
               </div>
               <div class="px-4 py-3">
                 <div class="flex items-end justify-between mb-2 gap-2">
                   <div class="min-w-0"><span data-m-pnl class="block text-2xl font-extrabold whitespace-nowrap"></span><span data-m-pct class="block text-xs whitespace-nowrap"></span></div>
                   <div class="text-right text-[11px] text-gray-400 shrink-0">
                     <div class="inline-flex rounded-md overflow-hidden border border-gray-300 dark:border-gray-700" data-m-cadence>
                       <button type="button" data-ms="10000" class="px-2 py-0.5 font-semibold">10s</button>
                       <button type="button" data-ms="15000" class="px-2 py-0.5 font-semibold">15s</button>
                       <button type="button" data-ms="20000" class="px-2 py-0.5 font-semibold">20s</button>
                     </div>
                     <div class="mt-0.5"><span data-m-ago>loading…</span></div>
                   </div>
                 </div>
                 <div data-m-chart class="w-full rounded-lg overflow-hidden border border-gray-100 dark:border-gray-800" style="height:320px"></div>
                 <div class="mt-3 grid grid-cols-3 gap-2 text-xs tabular-nums text-center">
                   <div class="text-gray-500 dark:text-gray-400">entry<br><span data-m-entry class="text-gray-800 dark:text-gray-200 font-semibold"></span></div>
                   <div class="text-red-500">SL<br><span data-m-sl class="font-semibold"></span></div>
                   <div class="text-green-600 dark:text-green-400">TP<br><span data-m-tp class="font-semibold"></span></div>
                 </div>
                 <div class="mt-2 grid grid-cols-3 gap-2 text-[11px] tabular-nums text-center border-t border-gray-100 dark:border-gray-800 pt-2">
                   <div class="text-gray-500 dark:text-gray-400">capital in<br><span data-m-cap class="font-semibold text-gray-800 dark:text-gray-200"></span></div>
                   <div class="text-gray-500 dark:text-gray-400">reward @TP<br><span data-m-rew class="font-semibold"></span></div>
                   <div class="text-gray-500 dark:text-gray-400">risk @SL<br><span data-m-risk class="font-semibold"></span></div>
                 </div>
               </div>
               <div class="flex gap-2 px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                 <button data-m-hold class="flex-1 px-4 py-2.5 rounded-lg font-semibold border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-primary hover:text-primary">Hold · close view</button>
                 <button data-m-sell class="flex-1 px-4 py-2.5 rounded-lg font-semibold bg-red-500 text-white hover:bg-red-600"><i class="fas fa-bolt mr-1"></i>Sell at market now</button>
               </div>
             </div>`;
        document.body.appendChild(o);
        o.addEventListener('click', e => { if (e.target === o) gtbCloseTradeModal(); });
        o.querySelector('[data-m-x]').addEventListener('click', gtbCloseTradeModal);
        o.querySelector('[data-m-hold]').addEventListener('click', gtbCloseTradeModal);
        o.querySelectorAll('[data-m-cadence] button').forEach(b => b.addEventListener('click', () => gtbModalSetCadence(parseInt(b.dataset.ms))));
        GTB_MODAL.el = o;
        return o;
    }

    function gtbModalApplyCadence() {
        const o = GTB_MODAL.el; if (!o) return;
        o.querySelectorAll('[data-m-cadence] button').forEach(b => {
            const on = parseInt(b.dataset.ms) === GTB_MODAL.ms;
            b.className = 'px-2 py-0.5 font-semibold ' + (b.previousElementSibling ? 'border-l border-gray-300 dark:border-gray-700 ' : '')
                + (on ? 'bg-primary text-white' : 'text-gray-500 dark:text-gray-400 hover:text-primary');
        });
    }

    function gtbModalSetCadence(ms) {
        if (![10000, 15000, 20000].includes(ms)) return;
        GTB_MODAL.ms = ms;
        try { localStorage.setItem('gtbModalMs', String(ms)); } catch (e) {}
        gtbModalApplyCadence();
        // Restart the running poll at the new speed (if a trade view is open).
        if (GTB_MODAL.timer && GTB_MODAL.id != null) {
            clearInterval(GTB_MODAL.timer);
            const card = GTB_TRADES.cards[String(GTB_MODAL.id)];
            const p = card ? card.p : null;
            if (p) GTB_MODAL.timer = setInterval(() => gtbModalRefresh(p), GTB_MODAL.ms);
        }
    }

    function gtbModalSetPnl(p) {
        const o = GTB_MODAL.el; if (!o) return;
        const up = (+p.pnlPct) >= 0;
        const unreal = (p.unrealized !== undefined && p.unrealized !== null) ? +p.unrealized : ((+p.mark - +p.entry) * (+p.qty || 0));
        const pnlEl = o.querySelector('[data-m-pnl]'), pctEl = o.querySelector('[data-m-pct]');
        const coinPnl = (+p.mark > 0) ? unreal / (+p.mark) : 0;   // P&L in the coin's own units
        pnlEl.textContent = `${unreal >= 0 ? '+' : ''}$${unreal.toFixed(4)} USDT`;
        pnlEl.className = 'block text-2xl font-extrabold whitespace-nowrap ' + (up ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400');
        pctEl.textContent = `${up ? '+' : ''}${(+p.pnlPct).toFixed(2)}% · ${coinPnl >= 0 ? '+' : ''}${gtbFmtCoin(coinPnl)} ${p.symbol.replace('USDT','')} · mark $${(+p.mark).toPrecision(6)}`;
        pctEl.className = 'block text-xs whitespace-nowrap ' + (up ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400');

        // Capital in / reward @TP / risk @SL. Risk flips to a locked GAIN once the stop ratchets
        // above entry (the Gainer Hunter's profit-lock) — a clear "you can't lose from here" signal.
        const q = +p.qty || 0, e = +p.entry || 0;
        const capEl = o.querySelector('[data-m-cap]'), rewEl = o.querySelector('[data-m-rew]'), riskEl = o.querySelector('[data-m-risk]');
        if (capEl) capEl.textContent = '$' + (e * q).toFixed(2);
        if (rewEl) {
            if (p.take_profit) {
                const g = (+p.take_profit - e) * q, gp = e > 0 ? (+p.take_profit - e) / e * 100 : 0;
                rewEl.textContent = `+$${g.toFixed(2)} (+${gp.toFixed(1)}%)`;
                rewEl.className = 'font-semibold text-green-600 dark:text-green-400';
            } else { rewEl.textContent = 'trail (open)'; rewEl.className = 'font-semibold text-gray-500 dark:text-gray-400'; }
        }
        if (riskEl) {
            const l = (e - +p.stop_loss) * q, lp = e > 0 ? (e - +p.stop_loss) / e * 100 : 0;
            if (l < 0) { riskEl.textContent = `+$${(-l).toFixed(2)} locked`; riskEl.className = 'font-semibold text-green-600 dark:text-green-400'; }
            else { riskEl.textContent = `−$${l.toFixed(2)} (−${lp.toFixed(1)}%)`; riskEl.className = 'font-semibold text-red-500 dark:text-red-400'; }
        }
    }

    function gtbModalDestroyChart() {
        if (GTB_MODAL.chart) { try { GTB_MODAL.chart.remove(); } catch (e) {} }
        GTB_MODAL.chart = null; GTB_MODAL.series = null;
    }

    function gtbCloseTradeModal() {
        if (GTB_MODAL.timer) { clearInterval(GTB_MODAL.timer); GTB_MODAL.timer = null; }
        gtbModalDestroyChart();
        GTB_MODAL.id = null;
        if (GTB_MODAL.el) { GTB_MODAL.el.classList.add('hidden'); GTB_MODAL.el.classList.remove('flex'); }
        document.body.style.overflow = '';
    }

    function gtbModalRefresh(p) {
        if (GTB_MODAL.id !== p.id) return;  // stale (modal closed or switched)
        fetch(`/gtb/klines?symbol=${encodeURIComponent(p.symbol)}&interval=1m`)
            .then(r => r.json()).then(d => {
                if (GTB_MODAL.id !== p.id || !GTB_MODAL.series) return;
                if (d.ok && Array.isArray(d.candles)) {
                    const candles = d.candles.map(c => ({ time: c.time, open: c.open, high: c.high, low: c.low, close: c.close }));
                    GTB_MODAL.series.setData(candles);
                    const last = candles[candles.length - 1];
                    if (last) { p.mark = last.close; p.pnlPct = (+p.entry > 0) ? ((last.close - +p.entry) / +p.entry * 100) : 0; gtbModalSetPnl(p); }
                    const ago = GTB_MODAL.el.querySelector('[data-m-ago]'); if (ago) ago.textContent = 'updated ' + new Date().toLocaleTimeString();
                }
            }).catch(() => {});
    }

    function gtbOpenTradeModal(p) {
        if (!p) return;
        const o = gtbBuildModal();
        GTB_MODAL.id = p.id;
        o.querySelector('[data-m-title]').innerHTML = gtbCoinIcon(p.symbol.replace('USDT', ''), 20)
            + `<span class="truncate">${p.symbol.replace('USDT', '')}<span class="text-gray-400 text-xs font-normal">/USDT</span></span>`
            + `<span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-primary/10 text-primary">${gtbTemplLabel(p.template)}</span>`
            + `<span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded ${p.mode === 'live' ? 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400'}">${p.mode}</span>`;
        o.querySelector('[data-m-sell]').onclick = () => gtbModalSell(p.id, p.symbol);
        o.querySelector('[data-m-entry]').textContent = '$' + (+p.entry).toPrecision(6);
        o.querySelector('[data-m-sl]').textContent = '$' + (+p.stop_loss).toPrecision(6);
        o.querySelector('[data-m-tp]').textContent = p.take_profit ? '$' + (+p.take_profit).toPrecision(6) : 'trail';
        gtbModalSetPnl(p);
        o.classList.remove('hidden'); o.classList.add('flex');
        document.body.style.overflow = 'hidden';

        gtbModalDestroyChart();
        const el = o.querySelector('[data-m-chart]');
        if (typeof LightweightCharts !== 'undefined') {
            const prec = gtbPricePrecision(p.entry || p.mark || 1);
            GTB_MODAL.chart = LightweightCharts.createChart(el, Object.assign({ autoSize: true, crosshair: { mode: 1 },
                rightPriceScale: { visible: true }, timeScale: { visible: true, timeVisible: true, secondsVisible: false } }, gtbChartTheme()));
            GTB_MODAL.series = GTB_MODAL.chart.addCandlestickSeries({ upColor: '#16a34a', downColor: '#ef4444', borderVisible: false, wickUpColor: '#16a34a', wickDownColor: '#ef4444',
                priceFormat: { type: 'price', precision: prec, minMove: Math.pow(10, -prec) } });
            GTB_MODAL.series.createPriceLine({ price: +p.entry, color: '#3b82f6', lineWidth: 1, lineStyle: 2, title: 'entry' });
            GTB_MODAL.series.createPriceLine({ price: +p.stop_loss, color: '#ef4444', lineWidth: 1, lineStyle: 2, title: 'SL' });
            if (p.take_profit) GTB_MODAL.series.createPriceLine({ price: +p.take_profit, color: '#16a34a', lineWidth: 1, lineStyle: 2, title: 'TP' });
        }
        gtbModalApplyCadence();
        gtbModalRefresh(p);  // first paint immediately
        GTB_MODAL.timer = setInterval(() => gtbModalRefresh(p), GTB_MODAL.ms);
    }

    async function gtbModalSell(id, symbol) {
        if (!confirm('Sell ' + symbol + ' now at market?')) return;
        gtbCloseTradeModal();
        try {
            const res = await fetch('/gtb/bot/close', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': GTB_CSRF }, body: JSON.stringify({ csrf_token: GTB_CSRF, id }) });
            const d = await res.json();
            if (!d.ok) alert('Sell failed: ' + (d.error || 'unknown'));
        } catch (e) { alert(e.message); }
        gtbLoadPositions(); gtbLoadThoughts();
    }

    document.addEventListener('keydown', e => { if (e.key === 'Escape') gtbCloseTradeModal(); });

    document.addEventListener('DOMContentLoaded', () => {
        gtbInitChart();
        document.getElementById('gtb-chart-icon').innerHTML = gtbCoinIcon(GTB.base || 'BTC', 24);
        gtbInitIntervals();
        gtbInitSort();
        gtbLoadMovers();
        gtbModeBadge();
        gtbSetRunBtn();  // paint Start/Stop from persisted state on load
        gtbSetPromptActive(<?= json_encode($promptSource ?? 'default') ?>, <?= json_encode($activePromptKey) ?>);
        gtbLoadThoughts();
        gtbLoadPositions();
        setInterval(gtbLoadPositions, 6000);
        if (GTB_API_CONFIGURED) gtbLoadAccount();
    });
</script>

<style>
    .gtb-pair-active { background: rgba(99,102,241,0.10); box-shadow: inset 2px 0 0 #6366f1; }
</style>
