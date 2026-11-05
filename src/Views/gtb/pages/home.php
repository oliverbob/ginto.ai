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
<style>.gtb-blur{filter:blur(8px);user-select:none}</style>
<section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
            <span class="text-sm text-gray-500 dark:text-gray-400">Portfolio Value</span>
            <span class="flex items-center gap-2">
                <button type="button" id="gtb-eye-portfolio" onclick="gtbEye('portfolio','gtb-portfolio-value')" title="Hide / show" class="text-gray-400 hover:text-primary"><i class="fas fa-eye"></i></button>
                <i class="fas fa-wallet text-primary"></i>
            </span>
        </div>
        <div id="gtb-portfolio-value" class="mt-2 text-2xl font-bold text-gray-400 dark:text-gray-500">—</div>
        <div id="gtb-portfolio-note" class="mt-1 text-xs text-gray-400 dark:text-gray-500">Connect API to load</div>
    </div>
    <div class="rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
            <span class="text-sm text-gray-500 dark:text-gray-400">Available Balance</span>
            <span class="flex items-center gap-2">
                <button type="button" id="gtb-eye-balance" onclick="gtbEye('balance','gtb-balance-value')" title="Hide / show" class="text-gray-400 hover:text-primary"><i class="fas fa-eye"></i></button>
                <i class="fas fa-money-bill-wave text-primary"></i>
            </span>
        </div>
        <div id="gtb-balance-value" class="mt-2 text-2xl font-bold text-gray-400 dark:text-gray-500">—</div>
        <div id="gtb-balance-note" class="mt-1 text-xs text-gray-400 dark:text-gray-500">Free USDT</div>
    </div>
    <div class="rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
            <span class="text-sm text-gray-500 dark:text-gray-400">Open Holdings</span>
            <span class="flex items-center gap-2">
                <button type="button" id="gtb-eye-holdings" onclick="gtbEye('holdings','gtb-holdings-value')" title="Hide / show" class="text-gray-400 hover:text-primary"><i class="fas fa-eye"></i></button>
                <i class="fas fa-layer-group text-primary"></i>
            </span>
        </div>
        <div id="gtb-holdings-value" class="mt-2 text-2xl font-bold text-gray-400 dark:text-gray-500">—</div>
        <div id="gtb-holdings-note" class="mt-1 text-xs text-gray-400 dark:text-gray-500">Non-zero assets</div>
    </div>

    <!-- Realized P&L (real, from DB — scoped to the ACTIVE network) -->
    <div class="rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
            <span class="text-sm text-gray-500 dark:text-gray-400">Realized P&amp;L
                <button type="button" id="gtb-pnl-eye" onclick="gtbTogglePnl()" title="Hide / show all P&amp;L" class="ml-1 text-gray-400 hover:text-primary align-middle"><i class="fas fa-eye"></i></button>
            </span>
            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?= $isTestnet ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400' : 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400' ?>">
                <?= $isTestnet ? 'Testnet · paper' : 'Live · real funds' ?>
            </span>
        </div>
        <div class="mt-2 text-2xl font-bold gtb-pnl <?= $pnlPositive ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400' ?>">
            <?= htmlspecialchars($pnlText) ?>
        </div>
        <div class="mt-1 text-xs text-gray-400 dark:text-gray-500"><?= $isTestnet ? 'Testnet (paper) trades only' : 'Real-money trades only' ?></div>
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

<!-- Markets: one integrated panel — chart with Popular / Gainers / Losers tabs -->
<section class="mt-6 rounded-2xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4">
    <div class="grid lg:grid-cols-3 gap-4">
        <!-- Chart -->
        <div class="lg:col-span-2 min-w-0">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <div class="flex items-center gap-2 min-w-0">
                    <span id="gtb-chart-icon" class="inline-flex"></span>
                    <span id="gtb-chart-symbol" class="text-lg font-bold text-gray-900 dark:text-white">BTC/USDT</span>
                    <span id="gtb-chart-price" class="text-base font-semibold tabular-nums text-gray-700 dark:text-gray-200"></span>
                    <span id="gtb-chart-change" class="text-sm font-semibold"></span>
                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400">● LIVE</span>
                </div>
                <div id="gtb-intervals" class="inline-flex rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden text-xs">
                    <?php foreach (['1m','5m','15m','1h','4h','1d'] as $iv): ?>
                        <button type="button" data-interval="<?= $iv ?>"
                                class="gtb-iv px-3 py-1.5 <?= $iv==='1h' ? 'bg-primary text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>"><?= $iv ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div id="gtb-chart" class="w-full rounded-lg overflow-hidden" style="height:392px"></div>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <button type="button" id="gtb-analyze-coin-btn" onclick="gtbAnalyzeCoin()"
                        class="inline-flex items-center gap-1.5 text-sm font-semibold px-3.5 py-2 rounded-lg bg-primary text-white hover:bg-primary/90 disabled:opacity-60">
                    <i class="fas fa-brain"></i> <span id="gtb-analyze-coin-label">Analyze this coin</span>
                </button>
                <span class="text-[11px] text-gray-400 dark:text-gray-500">Candles from Binance mainnet · advisory only, no orders placed.</span>
            </div>
            <div id="gtb-analyze-coin-out" class="hidden mt-2 rounded-xl border border-primary/30 bg-primary/5 p-3 text-sm"></div>
        </div>
        <!-- Markets tabs (integrated, divider to the chart) -->
        <div class="min-w-0 lg:border-l lg:border-gray-200 lg:dark:border-gray-700 lg:pl-4">
            <div id="gtb-mtabs" class="flex rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-2 text-xs font-bold">
                <button type="button" data-mtab="hot" class="flex-1 py-1.5">Popular</button>
                <button type="button" data-mtab="gainers" class="flex-1 py-1.5 border-l border-gray-200 dark:border-gray-700">Gainers</button>
                <button type="button" data-mtab="losers" class="flex-1 py-1.5 border-l border-gray-200 dark:border-gray-700">Losers</button>
            </div>
            <div id="gtb-hot" class="gtb-mlist space-y-0.5 overflow-y-auto pr-1" style="max-height:404px"><div class="py-6 text-center text-gray-400 dark:text-gray-500 text-sm"><i class="fas fa-spinner fa-spin"></i></div></div>
            <div id="gtb-gainers" class="gtb-mlist hidden space-y-0.5 overflow-y-auto pr-1" style="max-height:404px"></div>
            <div id="gtb-losers" class="gtb-mlist hidden space-y-0.5 overflow-y-auto pr-1" style="max-height:404px"></div>
        </div>
    </div>
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
                <i class="fas fa-magnifying-glass-chart"></i> Analyze market
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
            <span id="gtb-port-summary" class="gtb-pnl ml-2 text-xs font-normal text-gray-400 dark:text-gray-500"></span>
        </h3>
        <span id="gtb-port-unreal" class="gtb-pnl text-sm font-bold"></span>
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
                            <th class="py-2 pr-4 font-medium text-right" title="USDT spent to buy (capital in)">Bought <span class="text-gray-400 font-normal">($)</span></th>
                            <th class="py-2 pr-4 font-medium text-right" title="USDT received on sell (gross proceeds)">Sold <span class="text-gray-400 font-normal">($)</span></th>
                            <th class="py-2 font-medium text-right" title="Gain or loss in USDT, net of Binance buy+sell fees (~0.2% round-trip)">Gain / Loss <span class="text-gray-400 font-normal">($ net)</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php
                        // One row per trade, in plain USDT: amount bought ($), amount sold ($), and
                        // net gain/loss ($). Open trades show "holding" + the forward plan.
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
                        $rows = $recentTrades;
                        usort($rows, fn($a, $b) => strcmp((string) ($b['closed_at'] ?? $b['created_at'] ?? ''), (string) ($a['closed_at'] ?? $a['created_at'] ?? '')));
                        foreach (array_slice($rows, 0, 30) as $t):
                            $tid    = (int) ($t['id'] ?? 0);
                            $sym    = $t['symbol'] ?? '';
                            $entry  = (float) ($t['price'] ?? 0);
                            $qty    = (float) ($t['qty'] ?? 0);
                            $bought = (($t['quote_qty'] ?? null) !== null && (float) $t['quote_qty'] > 0) ? (float) $t['quote_qty'] : $entry * $qty;
                            $closed = ($t['status'] ?? '') === 'CLOSED' && ($t['exit_price'] ?? null) !== null;
                            $sold   = $closed ? (float) $t['exit_price'] * $qty : null;
                            $pnl    = ($closed && isset($t['realized_pnl'])) ? (float) $t['realized_pnl'] : null;
                            $time   = $closed ? ($t['closed_at'] ?? $t['created_at'] ?? '') : ($t['created_at'] ?? '');
                            $tp = $t['take_profit'] ?? null; $sl = $t['stop_loss'] ?? null;
                            $gp = (!$closed && $tp !== null && $entry > 0) ? ((float) $tp - $entry) / $entry * 100 : null;
                            $lp = (!$closed && $sl !== null && $entry > 0) ? ($entry - (float) $sl) / $entry * 100 : null;
                        ?>
                            <tr class="text-gray-800 dark:text-gray-200">
                                <td class="py-2.5 pr-4 text-gray-400 dark:text-gray-500 font-mono text-xs">#<?= $tid ?></td>
                                <td class="py-2.5 pr-4 text-gray-500 dark:text-gray-400 whitespace-nowrap"><?= htmlspecialchars(\Ginto\Models\GtbThought::manilaTime($time)) ?></td>
                                <td class="py-2.5 pr-4 font-medium">
                                    <span class="inline-flex items-center gap-2"><?= $coinIcon($sym) ?><span><?= htmlspecialchars($sym) ?></span>
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded <?= $closed ? 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400' ?>"><?= $closed ? 'CLOSED' : 'OPEN' ?></span>
                                    </span>
                                </td>
                                <td class="py-2.5 pr-4 text-right tabular-nums">$<?= number_format($bought, 2) ?></td>
                                <td class="py-2.5 pr-4 text-right tabular-nums"><?= $sold !== null ? '$' . number_format($sold, 2) : '<span class="text-amber-600 dark:text-amber-400">holding</span>' ?></td>
                                <td class="gtb-pnl py-2.5 text-right tabular-nums font-semibold <?= $pnl === null ? 'text-gray-400' : ((float) $pnl < 0 ? 'text-red-500 dark:text-red-400' : 'text-green-600 dark:text-green-400') ?>">
                                    <?php if ($pnl !== null): $pv = (float) $pnl; ?>
                                        <span class="inline-flex items-center justify-end gap-1" title="<?= $pv >= 0 ? 'Win' : 'Loss' ?>">
                                            <i class="fas fa-<?= $pv >= 0 ? 'caret-up' : 'caret-down' ?>"></i><?= ($pv >= 0 ? '+$' : '−$') . htmlspecialchars(number_format(abs($pv), 4)) ?>
                                        </span>
                                    <?php elseif (!$closed): ?>
                                        <span class="text-[10px] whitespace-nowrap font-normal text-gray-400">open ·
                                            <span class="text-green-600 dark:text-green-400"><?= $gp !== null ? '▲' . number_format($gp, 1) . '%' : '▲ trail' ?></span><?php if ($lp !== null): ?><span class="ml-0.5 <?= $lp >= 0 ? 'text-red-500 dark:text-red-400' : 'text-green-600 dark:text-green-400' ?>"><?= $lp >= 0 ? '▼' . number_format($lp, 1) . '%' : '▲' . number_format(abs($lp), 1) . '% lock' ?></span><?php endif; ?>
                                        </span>
                                    <?php else: ?>—<?php endif; ?>
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

    // Styled modal dialogs (replace native alert()/confirm()). Promise-based; theme-aware.
    function gtbDialog({ title, message, confirmText, cancelText, danger, alertOnly }) {
        return new Promise(resolve => {
            const o = document.createElement('div');
            o.className = 'fixed inset-0 z-[80] flex items-center justify-center p-4 bg-black/60';
            o.innerHTML =
                `<div class="w-full max-w-sm rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-2xl p-5">
                   <h3 data-title class="font-bold text-gray-900 dark:text-white mb-1.5${title ? '' : ' hidden'}"></h3>
                   <p data-msg class="text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line"></p>
                   <div class="mt-5 flex gap-2 justify-end">
                     <button data-cancel class="px-4 py-2 rounded-lg text-sm font-semibold border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-primary hover:text-primary${alertOnly ? ' hidden' : ''}"></button>
                     <button data-ok class="px-4 py-2 rounded-lg text-sm font-semibold text-white ${danger ? 'bg-red-500 hover:bg-red-600' : 'bg-primary hover:bg-primary/90'}"></button>
                   </div>
                 </div>`;
            o.querySelector('[data-title]').textContent = title || '';
            o.querySelector('[data-msg]').textContent = message || '';
            o.querySelector('[data-ok]').textContent = confirmText || 'OK';
            o.querySelector('[data-cancel]').textContent = cancelText || 'Cancel';
            document.body.appendChild(o);
            const done = v => { o.remove(); document.removeEventListener('keydown', onKey); resolve(v); };
            function onKey(e) { if (e.key === 'Escape') done(!!alertOnly); if (e.key === 'Enter') done(true); }
            o.querySelector('[data-ok]').addEventListener('click', () => done(true));
            o.querySelector('[data-cancel]').addEventListener('click', () => done(false));
            o.addEventListener('click', e => { if (e.target === o) done(!!alertOnly); });
            document.addEventListener('keydown', onKey);
            o.querySelector('[data-ok]').focus();
        });
    }
    function gtbConfirm(message, opts = {}) { return gtbDialog({ message, ...opts }); }
    function gtbAlert(message, opts = {}) { return gtbDialog({ message, alertOnly: true, ...opts }); }
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

    // Tabbed markets panel (Popular / Gainers / Losers) — one visible at a time.
    function gtbSetMarketTab(tab) {
        document.querySelectorAll('.gtb-mlist').forEach(el => el.classList.toggle('hidden', el.id !== 'gtb-' + tab));
        document.querySelectorAll('#gtb-mtabs button').forEach(b => {
            const on = b.dataset.mtab === tab;
            b.classList.toggle('bg-primary', on); b.classList.toggle('text-white', on);
            b.classList.toggle('text-gray-500', !on); b.classList.toggle('dark:text-gray-400', !on);
        });
    }
    function gtbInitMarketTabs() {
        document.querySelectorAll('#gtb-mtabs button').forEach(b => b.addEventListener('click', () => gtbSetMarketTab(b.dataset.mtab)));
        gtbSetMarketTab('hot');
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
            const keep = el => el.classList.contains('gtb-blur') ? ' gtb-blur' : '';
            pv.textContent = gtbFmtUsd(d.portfolioUsdt); pv.className = 'mt-2 text-2xl font-bold text-gray-900 dark:text-white' + keep(pv);
            pvn.textContent = (d.testnet ? 'Testnet' : 'Mainnet') + ' · est. USDT (incl. Earn)';
            bv.textContent = gtbFmtUsd(d.freeUsdt); bv.className = 'mt-2 text-2xl font-bold text-gray-900 dark:text-white' + keep(bv);
            bvn.textContent = (+d.earnUsdt > 0)
                ? `Free to trade · ${gtbFmtUsd(d.earnUsdt)} in Earn`
                : 'Free USDT (spot)';
            hv.textContent = d.holdingsCount; hv.className = 'mt-2 text-2xl font-bold text-gray-900 dark:text-white' + keep(hv);
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
    const GTB_FEE_RATE = <?= json_encode((float) (\Ginto\Support\Env::get('GTB_FEE_RATE', '0.001') ?? 0.001)) ?>;  // per-side Binance fee
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
        if (armed && !(await gtbConfirm('The bot may place REAL orders with real money (capped at your base, every trade with an exchange-side stop).', { title: 'Arm LIVE trading?', confirmText: 'Arm LIVE', danger: true }))) {
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
            if (!(await gtbConfirm('The bot will STOP taking new trades but keep managing your open positions to a good exit, then fully stop once flat.\n\nYour exchange-side stops stay in place the whole time.', { title: 'Wind down?', confirmText: 'Wind down' }))) return;
        } else {
            // stopped OR winddown -> (re)start a fresh trading session
            action = 'start';
            if (!GTB_TESTNET && !document.getElementById('gtb-arm-live').checked) {
                if (!(await gtbConfirm('The bot will place REAL orders with real money (within your capital + loss limits, every trade with a stop). Tick "Arm LIVE trading" first, then start.', { title: 'LIVE mode', confirmText: 'Continue', danger: true }))) return;
            }
        }
        gtbBotControl(action, document.getElementById('gtb-run-btn'));
    }

    // Separate Force-stop button: sell everything now and fully stop.
    async function gtbForceStop() {
        if (!(await gtbConfirm('This SELLS every open position at market right now and fully stops the bot. Use only if you want out immediately.', { title: 'Force stop?', confirmText: 'Sell all & stop', danger: true }))) return;
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

    // Analyze the coin currently on the chart (advisory; also logs to the brain feed).
    async function gtbAnalyzeCoin() {
        const btn = document.getElementById('gtb-analyze-coin-btn');
        const lbl = document.getElementById('gtb-analyze-coin-label');
        const out = document.getElementById('gtb-analyze-coin-out');
        if (!btn || btn.disabled) return;
        btn.disabled = true; if (lbl) lbl.textContent = 'Thinking…';
        out.classList.remove('hidden');
        out.innerHTML = '<div class="flex items-center gap-2 text-gray-500"><i class="fas fa-spinner fa-spin"></i> Analyzing ' + (GTB.base || 'BTC') + '/USDT…</div>';
        try {
            const res = await fetch('/gtb/bot/analyze-coin', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': GTB_CSRF },
                body: JSON.stringify({ csrf_token: GTB_CSRF, symbol: GTB.symbol }),
            });
            const d = await res.json();
            if (!d || !d.ok) { out.innerHTML = '<div class="text-amber-600 dark:text-amber-400"><i class="fas fa-circle-info mr-1"></i>' + ((d && d.error) || 'Analysis unavailable.') + '</div>'; return; }
            const dec = (d.decision || '').toUpperCase();
            let cls = '', badge = '';
            if (dec.indexOf('BUY') === 0) { cls = 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400'; badge = dec; }
            else if (dec === 'HOLD') { cls = 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400'; badge = 'HOLD'; }
            else if (dec === 'SKIP') { cls = 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'; badge = 'SKIP'; }
            const body = (d.text || '').replace(/[<>&]/g, c => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c])).replace(/\n/g, '<br>');
            out.innerHTML = '<div class="flex items-center gap-2 mb-1.5 font-bold">' + gtbCoinIcon(d.base, 20) + d.base + '/USDT'
                + (badge ? '<span class="ml-1 text-[10px] font-bold uppercase px-2 py-0.5 rounded-full ' + cls + '">' + badge + '</span>' : '')
                + '<span class="ml-auto text-[10px] text-gray-400 font-normal">AI · advisory</span></div>'
                + '<div class="text-gray-700 dark:text-gray-200 leading-relaxed">' + body + '</div>';
            gtbLoadThoughts();
        } catch (e) {
            out.innerHTML = '<div class="text-amber-600 dark:text-amber-400">Network error — try again.</div>';
        } finally {
            btn.disabled = false; if (lbl) lbl.textContent = 'Analyze this coin';
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
        // P&L is tracked separately per network. Label it so paper testing is never mistaken
        // for real-money results (and vice-versa).
        const modeTag = (d.mode === 'live' || (!GTB_TESTNET && d.mode !== 'paper')) ? 'LIVE · real funds' : 'PAPER · testnet';
        const sum = document.getElementById('gtb-port-summary');
        if (sum) sum.textContent = `${p.open||0}/${p.slots||0} slots · ${modeTag} · realized $${(+(p.realized||0)).toFixed(2)}`;
        const un = document.getElementById('gtb-port-unreal');
        if (un) {
            const v = +(p.unrealized || 0), up = v >= 0;
            un.textContent = `${modeTag} · unrealized ${up?'+':''}$${v.toFixed(4)}`;
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
        root.className = 'relative rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/40 p-3 overflow-hidden min-w-0';
        root.innerHTML =
            `<button data-expand title="Expand & sell" class="absolute top-2 right-2 z-20 w-6 h-6 rounded-md bg-black/5 dark:bg-white/10 text-gray-500 dark:text-gray-300 hover:text-primary hover:bg-black/10 dark:hover:bg-white/20 flex items-center justify-center text-xs transition-colors"><i class="fas fa-up-right-and-down-left-from-center"></i></button>
             <div class="flex items-start justify-between gap-2 mb-1">
               <div class="min-w-0 flex flex-wrap items-center gap-x-1 gap-y-0.5 font-bold text-gray-900 dark:text-white">${gtbCoinIcon(p.symbol.replace('USDT',''), 18)}<span class="truncate max-w-full">${p.symbol.replace('USDT','')}<span class="text-gray-400 text-xs font-normal">/USDT</span></span>
                 ${gtbProfBadge(p.profile)}
                 <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-primary/10 text-primary">${gtbTemplLabel(p.template)}</span>
                 <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded ${p.mode==='live'?'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400':'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400'}">${p.mode}</span>
                 ${p.mode==='live' ? (p.protected
                    ? '<span title="Stop-loss resting on Binance" class="text-[9px] text-green-600 dark:text-green-400"><i class="fas fa-shield-halved"></i></span>'
                    : '<span title="No exchange stop yet" class="text-[9px] text-red-500"><i class="fas fa-triangle-exclamation"></i></span>') : ''}
               </div>
               <div class="text-right leading-tight shrink-0 pr-8">
                 <span data-pnl class="gtb-pnl block text-sm font-bold whitespace-nowrap"></span>
                 <span data-pct class="gtb-pnl block text-[11px] whitespace-nowrap"></span>
               </div>
             </div>
             <div data-chart title="Click to expand & sell" class="w-full rounded overflow-hidden cursor-pointer" style="height:140px"></div>
             <div class="mt-1.5 grid grid-cols-3 gap-1 text-[10px] tabular-nums text-center">
               <div class="text-gray-500 dark:text-gray-400">entry<br><span data-entry class="text-gray-800 dark:text-gray-200"></span></div>
               <div class="text-red-500">SL<br><span data-sl></span></div>
               <div class="text-green-600 dark:text-green-400">TP<br><span data-tp></span></div>
             </div>
             <div data-rr class="gtb-pnl mt-1 text-[10px] text-center text-gray-500 dark:text-gray-400 tabular-nums whitespace-nowrap"></div>
             <button data-close class="mt-1.5 w-full text-[11px] font-medium py-1 rounded border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:border-red-400 hover:text-red-500 transition-colors">
               <i class="fas fa-xmark mr-1"></i>Close now
             </button>`;
        grid.appendChild(root);
        root.querySelector('[data-close]').addEventListener('click', () => gtbClosePosition(p.id, p.symbol));
        const el = root.querySelector('[data-chart]');
        const card = { root, chart: null, series: null, lastKey: '', p: p };
        GTB_TRADES.cards[String(p.id)] = card;
        el.addEventListener('click', () => gtbOpenTradeModal(card.p || p));
        root.querySelector('[data-expand]').addEventListener('click', (e) => { e.stopPropagation(); gtbOpenTradeModal(card.p || p); });
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
        if (!(await gtbConfirm('Close ' + symbol + ' now at market?', { title: 'Sell at market', confirmText: 'Sell now', danger: true }))) return;
        try {
            const res = await fetch('/gtb/bot/close', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': GTB_CSRF },
                body: JSON.stringify({ csrf_token: GTB_CSRF, id }),
            });
            const d = await res.json();
            if (!d.ok) gtbAlert('Close failed: ' + (d.error || 'unknown'), { title: 'Error', danger: true });
        } catch (e) { gtbAlert(e.message, { title: 'Error', danger: true }); }
        gtbLoadPositions();
        gtbLoadThoughts();
    }

    // ---- Expanded live trade view (on-demand; polls slowly; destroys itself on close) ----
    // Real-time expanded view: streams live candles straight from Binance's public WebSocket
    // (no AJAX polling). The toggle selects the candle interval, Binance-style.
    const GTB_MODAL = { el: null, chart: null, series: null, ws: null, id: null, interval: '1s' };
    try { const s = localStorage.getItem('gtbModalInterval'); if (['1s', '1m', '5m', '15m'].includes(s)) GTB_MODAL.interval = s; } catch (e) {}

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
                   <div class="min-w-0"><span data-m-pnl class="gtb-pnl block text-2xl font-extrabold whitespace-nowrap"></span><span data-m-pct class="gtb-pnl block text-xs whitespace-nowrap"></span></div>
                   <div class="text-right text-[11px] text-gray-400 shrink-0">
                     <div class="inline-flex rounded-md overflow-hidden border border-gray-300 dark:border-gray-700" data-m-interval>
                       <button type="button" data-iv="1s" class="px-2 py-0.5 font-semibold">1s</button>
                       <button type="button" data-iv="1m" class="px-2 py-0.5 font-semibold">1m</button>
                       <button type="button" data-iv="5m" class="px-2 py-0.5 font-semibold">5m</button>
                       <button type="button" data-iv="15m" class="px-2 py-0.5 font-semibold">15m</button>
                     </div>
                     <div class="mt-0.5"><span data-m-status>connecting…</span></div>
                   </div>
                 </div>
                 <div data-m-fee class="mb-2 text-[11px] font-semibold px-2 py-1 rounded text-center"></div>
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
        o.querySelectorAll('[data-m-interval] button').forEach(b => b.addEventListener('click', () => gtbModalSetInterval(b.dataset.iv)));
        GTB_MODAL.el = o;
        return o;
    }

    function gtbModalApplyInterval() {
        const o = GTB_MODAL.el; if (!o) return;
        o.querySelectorAll('[data-m-interval] button').forEach(b => {
            const on = b.dataset.iv === GTB_MODAL.interval;
            b.className = 'px-2 py-0.5 font-semibold ' + (b.previousElementSibling ? 'border-l border-gray-300 dark:border-gray-700 ' : '')
                + (on ? 'bg-primary text-white' : 'text-gray-500 dark:text-gray-400 hover:text-primary');
        });
    }

    function gtbModalSetStatus(text, live) {
        const el = GTB_MODAL.el && GTB_MODAL.el.querySelector('[data-m-status]');
        if (!el) return;
        const dot = live ? '<span class="inline-block w-1.5 h-1.5 rounded-full bg-green-500 mr-1 align-middle"></span>'
                         : '<span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-500 mr-1 align-middle"></span>';
        el.innerHTML = dot + text;
    }

    function gtbModalSetInterval(iv) {
        if (!['1s', '1m', '5m', '15m'].includes(iv)) return;
        GTB_MODAL.interval = iv;
        try { localStorage.setItem('gtbModalInterval', iv); } catch (e) {}
        gtbModalApplyInterval();
        if (GTB_MODAL.id != null) {
            const card = GTB_TRADES.cards[String(GTB_MODAL.id)];
            const p = card ? card.p : null;
            if (p) { gtbModalCloseStream(); gtbModalBackfill(p).then(() => gtbModalOpenStream(p)); }
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

        // Fee threshold: `unrealized` is already NET of the round-trip fee, so its sign is the
        // break-even. Show whether selling now clears charges, or how much more it needs.
        const feeEl = o.querySelector('[data-m-fee]');
        if (feeEl) {
            const fee = GTB_FEE_RATE || 0.001, rt = (fee * 2 * 100).toFixed(1);  // round-trip %
            if (unreal > 0) {
                feeEl.textContent = `✓ Clears fees — sell now nets +$${unreal.toFixed(4)} after ~${rt}% round-trip charges`;
                feeEl.className = 'mb-2 text-[11px] font-semibold px-2 py-1 rounded text-center bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-300';
            } else {
                const be = e * (1 + fee) / (1 - fee);                       // break-even mark price
                const need = (+p.mark > 0) ? Math.max(0, (be - +p.mark) / (+p.mark) * 100) : 0;
                feeEl.textContent = `⚠ Below fees — needs +${need.toFixed(2)}% more (to $${be.toPrecision(6)}) to clear ~${rt}% charges`;
                feeEl.className = 'mb-2 text-[11px] font-semibold px-2 py-1 rounded text-center bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300';
            }
        }
    }

    function gtbModalCloseStream() {
        if (GTB_MODAL.ws) { try { GTB_MODAL.ws.onclose = null; GTB_MODAL.ws.onerror = null; GTB_MODAL.ws.close(); } catch (e) {} }
        GTB_MODAL.ws = null;
    }

    function gtbModalDestroyChart() {
        if (GTB_MODAL.chart) { try { GTB_MODAL.chart.remove(); } catch (e) {} }
        GTB_MODAL.chart = null; GTB_MODAL.series = null;
    }

    function gtbCloseTradeModal() {
        gtbModalCloseStream();       // close the live socket so nothing streams in the background
        gtbModalDestroyChart();
        GTB_MODAL.id = null;
        if (GTB_MODAL.el) { GTB_MODAL.el.classList.add('hidden'); GTB_MODAL.el.classList.remove('flex'); }
        document.body.style.overflow = '';
    }

    // Recompute mark-based P&L (NET of round-trip fee) and repaint.
    function gtbModalApplyMark(p, mark) {
        const e = +p.entry, q = +p.qty || 0, fee = GTB_FEE_RATE || 0.001;
        p.mark = mark;
        p.pnlPct = e > 0 ? (mark - e) / e * 100 : 0;
        p.unrealized = (mark - e) * q - fee * q * (e + mark);
        gtbModalSetPnl(p);
    }

    // One-time history backfill for the current interval, so the chart isn't empty before the stream.
    function gtbModalBackfill(p) {
        return fetch(`/gtb/klines?symbol=${encodeURIComponent(p.symbol)}&interval=${GTB_MODAL.interval}`)
            .then(r => r.json()).then(d => {
                if (GTB_MODAL.id !== p.id || !GTB_MODAL.series) return;
                if (d.ok && Array.isArray(d.candles) && d.candles.length) {
                    GTB_MODAL.series.setData(d.candles.map(c => ({ time: c.time, open: c.open, high: c.high, low: c.low, close: c.close })));
                    gtbModalApplyMark(p, +d.candles[d.candles.length - 1].close);
                }
            }).catch(() => {});
    }

    // Live candles straight from Binance's public WebSocket (real-time; auto-reconnects).
    function gtbModalOpenStream(p) {
        gtbModalCloseStream();
        if (typeof WebSocket === 'undefined') { gtbModalSetStatus('live unavailable', false); return; }
        let ws;
        try { ws = new WebSocket('wss://stream.binance.com:9443/ws/' + p.symbol.toLowerCase() + '@kline_' + GTB_MODAL.interval); }
        catch (e) { gtbModalSetStatus('live unavailable', false); return; }
        GTB_MODAL.ws = ws;
        gtbModalSetStatus('connecting…', false);
        ws.onopen = () => { if (GTB_MODAL.id === p.id) gtbModalSetStatus('LIVE', true); };
        ws.onmessage = (ev) => {
            if (GTB_MODAL.id !== p.id || !GTB_MODAL.series) return;
            let m; try { m = JSON.parse(ev.data); } catch (e) { return; }
            const k = m && m.k; if (!k) return;
            try { GTB_MODAL.series.update({ time: Math.floor(k.t / 1000), open: +k.o, high: +k.h, low: +k.l, close: +k.c }); } catch (e) {}
            gtbModalApplyMark(p, +k.c);
        };
        ws.onerror = () => { if (GTB_MODAL.id === p.id) gtbModalSetStatus('reconnecting…', false); };
        ws.onclose = () => {
            if (GTB_MODAL.id === p.id && GTB_MODAL.ws === ws) {   // unexpected drop — retry
                gtbModalSetStatus('reconnecting…', false);
                setTimeout(() => { if (GTB_MODAL.id === p.id) gtbModalOpenStream(p); }, 2000);
            }
        };
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
                rightPriceScale: { visible: true }, timeScale: { visible: true, timeVisible: true, secondsVisible: GTB_MODAL.interval === '1s' } }, gtbChartTheme()));
            GTB_MODAL.series = GTB_MODAL.chart.addCandlestickSeries({ upColor: '#16a34a', downColor: '#ef4444', borderVisible: false, wickUpColor: '#16a34a', wickDownColor: '#ef4444',
                priceFormat: { type: 'price', precision: prec, minMove: Math.pow(10, -prec) } });
            GTB_MODAL.series.createPriceLine({ price: +p.entry, color: '#3b82f6', lineWidth: 1, lineStyle: 2, title: 'entry' });
            GTB_MODAL.series.createPriceLine({ price: +p.stop_loss, color: '#ef4444', lineWidth: 1, lineStyle: 2, title: 'SL' });
            if (p.take_profit) GTB_MODAL.series.createPriceLine({ price: +p.take_profit, color: '#16a34a', lineWidth: 1, lineStyle: 2, title: 'TP' });
        }
        gtbModalApplyInterval();
        gtbModalBackfill(p).then(() => gtbModalOpenStream(p));  // history, then live stream
    }

    async function gtbModalSell(id, symbol) {
        if (!(await gtbConfirm('Sell ' + symbol + ' now at market?', { title: 'Sell at market', confirmText: 'Sell now', danger: true }))) return;
        gtbCloseTradeModal();
        try {
            const res = await fetch('/gtb/bot/close', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': GTB_CSRF }, body: JSON.stringify({ csrf_token: GTB_CSRF, id }) });
            const d = await res.json();
            if (!d.ok) gtbAlert('Sell failed: ' + (d.error || 'unknown'), { title: 'Error', danger: true });
        } catch (e) { gtbAlert(e.message, { title: 'Error', danger: true }); }
        gtbLoadPositions(); gtbLoadThoughts();
    }

    document.addEventListener('keydown', e => { if (e.key === 'Escape') gtbCloseTradeModal(); });

    // Eye toggle: blur/reveal every P&L figure (works on testnet and live). Remembered per browser.
    function gtbTogglePnl() {
        const hidden = document.body.classList.toggle('gtb-pnl-hidden');
        try { localStorage.setItem('gtbHidePnl', hidden ? '1' : '0'); } catch (e) {}
        const ic = document.querySelector('#gtb-pnl-eye i');
        if (ic) ic.className = hidden ? 'fas fa-eye-slash' : 'fas fa-eye';
    }

    // Per-card eye toggle: blur/reveal a single stat card's value. Remembered per browser.
    function gtbEye(key, id) {
        const el = document.getElementById(id), btn = document.getElementById('gtb-eye-' + key), i = btn && btn.querySelector('i');
        if (!el) return;
        const hide = !(i && i.classList.contains('fa-eye-slash'));
        el.classList.toggle('gtb-blur', hide);
        if (i) i.className = hide ? 'fas fa-eye-slash' : 'fas fa-eye';
        try { localStorage.setItem('gtbeye_' + key, hide ? '1' : '0'); } catch (e) {}
    }
    function gtbInitEyes() {
        [['portfolio', 'gtb-portfolio-value'], ['balance', 'gtb-balance-value'], ['holdings', 'gtb-holdings-value']].forEach(g => {
            let on = false; try { on = localStorage.getItem('gtbeye_' + g[0]) === '1'; } catch (e) {}
            if (on) {
                const el = document.getElementById(g[1]); if (el) el.classList.add('gtb-blur');
                const i = document.querySelector('#gtb-eye-' + g[0] + ' i'); if (i) i.className = 'fas fa-eye-slash';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Restore the hide-P&L preference before anything paints.
        try {
            if (localStorage.getItem('gtbHidePnl') === '1') {
                document.body.classList.add('gtb-pnl-hidden');
                const ic = document.querySelector('#gtb-pnl-eye i'); if (ic) ic.className = 'fas fa-eye-slash';
            }
        } catch (e) {}
        gtbInitEyes();
        gtbInitChart();
        document.getElementById('gtb-chart-icon').innerHTML = gtbCoinIcon(GTB.base || 'BTC', 24);
        gtbInitIntervals();
        gtbInitSort();
        gtbInitMarketTabs();
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
    /* Privacy: eye toggle blurs every P&L figure (totals, cards, modal, history). */
    body.gtb-pnl-hidden .gtb-pnl { filter: blur(8px); user-select: none; cursor: default; }
</style>
