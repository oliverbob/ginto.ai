<?php // academy/bot.php — "Live Bot Lab" for Academy members (watch + follow the testnet bot).
$catalog = $catalog ?? [];
$isPro   = !empty($isPro);
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Live Bot Lab — Ginto Trading Academy') ?></title>
    <script>
      (function(){const t=localStorage.getItem('theme');document.documentElement.classList.toggle('dark',t==='dark'||(t!=='light'&&true));})();
      function gtaToggleTheme(){const d=!document.documentElement.classList.contains('dark');document.documentElement.classList.toggle('dark',d);try{localStorage.setItem('theme',d?'dark':'light');}catch(e){}}
    </script>
    <script src="/assets/js/tailwindcss.js"></script>
    <script>tailwind.config={darkMode:'class',theme:{extend:{colors:{primary:'#6366f1',secondary:'#8b5cf6'}}}};</script>
    <script src="https://cdn.jsdelivr.net/npm/lightweight-charts@4.2.0/dist/lightweight-charts.standalone.production.js"></script>
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css"><style>.dark{color-scheme:dark}.lab-blur{filter:blur(9px);user-select:none}</style>
</head>
<body class="bg-white dark:bg-[#0b1020] text-gray-900 dark:text-gray-100 min-h-screen">
<header class="border-b border-gray-200 dark:border-gray-800 sticky top-0 bg-white/80 dark:bg-[#0b1020]/80 backdrop-blur z-30">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
        <a href="/academy/learn" class="flex items-center gap-2 font-bold"><i class="fas fa-graduation-cap text-primary"></i> Ginto <span class="text-primary">Trading Academy</span></a>
        <div class="flex items-center gap-3 text-sm">
            <a href="/academy/learn" class="text-gray-500 hover:text-primary"><i class="fas fa-book-open mr-1"></i>Lessons</a>
            <a href="/academy/settings" class="text-gray-500 hover:text-primary" title="Bot settings"><i class="fas fa-sliders"></i></a>
            <button onclick="gtaToggleTheme()" title="Toggle light / dark" class="w-9 h-9 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-primary"><i class="fas fa-circle-half-stroke"></i></button>
            <span class="hidden sm:inline text-gray-500 dark:text-gray-400"><?= htmlspecialchars($_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Member') ?></span>
            <a href="/logout" class="inline-flex items-center gap-1 text-gray-500 hover:text-red-500" title="Log out"><i class="fas fa-arrow-right-from-bracket"></i><span class="hidden sm:inline">Log out</span></a>
        </div>
    </div>
</header>

<section class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-3xl font-extrabold flex items-center gap-2"><i class="fas fa-flask text-primary"></i> Live Bot Lab</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-300 max-w-2xl">Watch the <strong>Ginto Trading Bot</strong> trade in real time on <strong>testnet</strong> — real market data, paper money. Study every entry, stop-loss, take-profit, and the AI's reasoning behind each move. This is a read-only window: you learn by watching, risk-free.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-[11px] font-bold uppercase px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400">Testnet · paper</span>
            <span id="lab-run" class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400">—</span>
        </div>
    </div>

    <!-- Your own paper wallet (per-user) -->
    <div class="mt-6 rounded-2xl border border-primary/30 bg-gradient-to-br from-primary/10 to-secondary/10 p-5 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="text-xs font-bold uppercase tracking-wide text-primary flex items-center gap-2"><span><i class="fas fa-wallet mr-1"></i>Your paper wallet</span>
                <button type="button" id="eye-lab-wallet" onclick="labEye('lab-wallet',['lab-wallet','lab-wallet-pnl'])" title="Hide / show" class="text-primary/60 hover:text-primary"><i class="fas fa-eye"></i></button></div>
            <div id="lab-wallet" class="mt-1 text-4xl font-extrabold tabular-nums">$10,000.00</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Your practice balance — real market prices, zero real risk. This account is yours alone.</div>
        </div>
        <div class="text-right">
            <div id="lab-wallet-pnl" class="text-lg font-bold text-gray-400">$0.00</div>
            <div class="text-[11px] text-gray-400">vs $10,000 start</div>
        </div>
    </div>

    <!-- Bot controls (prominent) -->
    <div class="mt-5 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/5 p-4">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" id="lab-anz-mkt-btn" onclick="labAnalyzeMarket()"
                    class="inline-flex items-center gap-1.5 text-sm font-bold px-4 py-2.5 rounded-xl bg-primary text-white hover:bg-primary/90 disabled:opacity-60">
                <i class="fas fa-magnifying-glass-chart"></i> <span id="lab-anz-mkt-label">Analyze market with AI</span>
            </button>
            <span class="text-xs text-gray-400">Scans today's movers and names the single best momentum setup — advisory, no orders placed.</span>
        </div>
        <div id="lab-anz-mkt-out" class="hidden mt-3 rounded-xl border border-primary/30 bg-primary/5 p-3 text-sm"></div>
    </div>

    <!-- The shared class bot the learner studies (clearly separated from their own money) -->
    <div class="mt-5 flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-wide text-gray-400">
        <i class="fas fa-robot"></i> The class demo bot
        <span class="font-normal normal-case">— a shared teaching bot you watch. Its wins and losses are the lesson, not your balance.</span>
        <span id="lab-updated" class="ml-auto font-normal normal-case">—</span>
    </div>
    <div class="mt-2 grid grid-cols-2 gap-3">
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4"><div class="flex items-center justify-between"><span class="text-xs text-gray-500 dark:text-gray-400">Open positions</span><button type="button" id="eye-lab-open" onclick="labEye('lab-open',['lab-open'])" title="Hide / show" class="text-gray-400 hover:text-primary"><i class="fas fa-eye"></i></button></div><div id="lab-open" class="mt-1 text-2xl font-extrabold">—</div></div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4"><div class="flex items-center justify-between"><span class="text-xs text-gray-500 dark:text-gray-400">Open-trade P&amp;L (live)</span><button type="button" id="eye-lab-unreal" onclick="labEye('lab-unreal',['lab-unreal'])" title="Hide / show" class="text-gray-400 hover:text-primary"><i class="fas fa-eye"></i></button></div><div id="lab-unreal" class="mt-1 text-2xl font-extrabold">—</div></div>
    </div>

    <!-- Live chart + markets (same engine as /gtb, testnet-only) -->
    <div class="mt-8 flex items-center justify-between">
        <h2 class="text-sm font-bold uppercase tracking-wide text-primary"><i class="fas fa-chart-line mr-1"></i>Live charts &amp; markets</h2>
        <span class="text-[11px] text-gray-400">Real Binance prices · practice testnet</span>
    </div>
    <div class="mt-3 grid lg:grid-cols-3 gap-4">
        <!-- Chart -->
        <div class="lg:col-span-2 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/5 p-3">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <div class="flex items-center gap-2 min-w-0">
                    <span id="lab-chart-icon"></span>
                    <span id="lab-chart-symbol" class="font-bold truncate">BTC/USDT</span>
                    <span id="lab-chart-price" class="text-sm tabular-nums text-gray-500 dark:text-gray-300">—</span>
                    <span id="lab-chart-change" class="text-sm font-semibold">—</span>
                    <span id="lab-chart-live" class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800">…</span>
                </div>
                <div id="lab-iv" class="inline-flex rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 text-xs font-semibold">
                    <button data-iv="1s" class="px-2 py-1">1s</button>
                    <button data-iv="1m" class="px-2 py-1 border-l border-gray-200 dark:border-gray-700">1m</button>
                    <button data-iv="5m" class="px-2 py-1 border-l border-gray-200 dark:border-gray-700">5m</button>
                    <button data-iv="15m" class="px-2 py-1 border-l border-gray-200 dark:border-gray-700">15m</button>
                    <button data-iv="1h" class="px-2 py-1 border-l border-gray-200 dark:border-gray-700">1h</button>
                    <button data-iv="4h" class="px-2 py-1 border-l border-gray-200 dark:border-gray-700">4h</button>
                    <button data-iv="1d" class="px-2 py-1 border-l border-gray-200 dark:border-gray-700">1d</button>
                </div>
            </div>
            <div id="lab-chart" class="w-full rounded-lg overflow-hidden" style="height:360px"></div>
            <!-- Analyze the specific coin on the chart (original placement) -->
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <button type="button" id="lab-anz-coin-btn" onclick="labAnalyzeCoin()"
                        class="inline-flex items-center gap-1.5 text-sm font-semibold px-3.5 py-2 rounded-lg bg-primary text-white hover:bg-primary/90 disabled:opacity-60">
                    <i class="fas fa-brain"></i> <span id="lab-anz-coin-label">Analyze this coin with AI</span>
                </button>
                <span class="text-[11px] text-gray-400">Advisory only — the bot reasons out loud; no orders are placed.</span>
            </div>
            <div id="lab-anz-coin-out" class="hidden mt-3 rounded-xl border border-primary/30 bg-primary/5 p-3 text-sm"></div>
            <!-- Manual paper trade (Starter + Pro) — trade the coin on the chart -->
            <div class="mt-3 rounded-xl border border-green-200 dark:border-green-500/30 bg-green-50/50 dark:bg-green-500/5 p-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-bold"><i class="fas fa-hand-pointer text-green-600 mr-1"></i>Trade <span id="lab-trade-sym">BTC</span>/USDT yourself</span>
                    <span class="text-[11px] text-gray-400">manual paper trade — you pick the entry, you close it</span>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-1"><span class="text-gray-400">$</span>
                        <input id="lab-trade-amt" type="number" min="10" step="10" value="100" class="w-28 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent tabular-nums focus:border-primary focus:outline-none">
                    </div>
                    <button type="button" id="lab-buy-btn" onclick="labBuy()" class="inline-flex items-center gap-1.5 text-sm font-bold px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700 disabled:opacity-60"><i class="fas fa-cart-plus"></i> Buy on paper</button>
                    <span id="lab-trade-status" class="text-xs text-gray-400"></span>
                </div>
            </div>
        </div>
        <!-- Markets -->
        <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/5 p-3">
            <div id="lab-tabs" class="flex rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-2 text-xs font-bold">
                <button data-tab="hot" class="flex-1 py-1.5">Popular</button>
                <button data-tab="gainers" class="flex-1 py-1.5 border-l border-gray-200 dark:border-gray-700">Gainers</button>
                <button data-tab="losers" class="flex-1 py-1.5 border-l border-gray-200 dark:border-gray-700">Losers</button>
            </div>
            <div id="lab-market-list" class="space-y-0.5 overflow-y-auto pr-1" style="max-height:352px">
                <div class="py-10 text-center text-gray-400 text-sm">Loading markets…</div>
            </div>
        </div>
    </div>

    <!-- Start AI bot trading: the learner's wallet follows the class bot -->
    <div class="mt-8 rounded-2xl border border-primary/30 bg-gradient-to-br from-primary/5 to-secondary/5 p-4 sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-extrabold flex items-center gap-2"><i class="fas fa-robot text-primary"></i>Your AI trading bot <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?= $isPro ? 'bg-primary/15 text-primary' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' ?>"><?= $isPro ? 'Pro' : 'Pro only' ?></span></h2>
                <?php if ($isPro): ?>
                <p id="lab-bot-note" class="mt-1 text-sm text-gray-600 dark:text-gray-300 max-w-xl">Turn this on and your <strong>$10,000 paper wallet automatically follows the class bot</strong> — the same momentum strategy templates, entries, stops and take-profits. Watch the AI trade for you, risk-free, and learn by following.</p>
                <?php else: ?>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300 max-w-xl">Hands-free automation — the bot trades your paper wallet for you — is a <strong>Pro Trader</strong> feature. On Starter you trade manually (with AI assistance) using the <strong>Buy on paper</strong> panel under the chart above.</p>
                <?php endif; ?>
                <div id="lab-heartbeat" class="mt-2 text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5"></div>
            </div>
            <div class="flex flex-col items-end gap-2">
                <?php if ($isPro): ?>
                <button type="button" id="lab-bot-toggle" onclick="labToggleBot()"
                        class="inline-flex items-center gap-2 text-sm font-bold px-5 py-3 rounded-xl bg-green-600 text-white hover:bg-green-700 disabled:opacity-60">
                    <i class="fas fa-play"></i> <span id="lab-bot-toggle-label">Start AI bot trading</span>
                </button>
                <a href="/academy/settings" class="text-xs text-gray-500 hover:text-primary"><i class="fas fa-sliders mr-1"></i>Bot settings</a>
                <?php else: ?>
                <a href="/academy#pricing" class="inline-flex items-center gap-2 text-sm font-bold px-5 py-3 rounded-xl bg-primary text-white hover:bg-primary/90"><i class="fas fa-crown"></i> Upgrade to Pro</a>
                <a href="/academy/settings" class="text-xs text-gray-500 hover:text-primary"><i class="fas fa-sliders mr-1"></i>Preview templates</a>
                <?php endif; ?>
            </div>
        </div>
        <div id="lab-my-positions" class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3"></div>

        <?php if ($catalog): ?>
        <div class="mt-5 pt-4 border-t border-gray-200 dark:border-gray-800">
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold uppercase tracking-wide text-primary"><?= $isPro ? 'Tap a strategy to activate your bot' : 'Strategy templates the bot uses' ?></div>
                <a href="/academy/settings" class="text-xs text-primary hover:underline">Fine-tune <i class="fas fa-arrow-right ml-0.5"></i></a>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                <?php foreach ($catalog as $key => $t):
                    $nm = htmlspecialchars($t['name'] ?? $key, ENT_QUOTES); ?>
                <?php if ($isPro): ?>
                <button type="button" data-tpl="<?= htmlspecialchars($key) ?>" onclick="labActivateStrategy('<?= htmlspecialchars($key) ?>','<?= $nm ?>',this)" class="lab-tpl text-left block rounded-lg border border-gray-200 dark:border-gray-800 bg-white/60 dark:bg-white/5 p-2.5 hover:border-primary hover:shadow-sm transition-colors group">
                    <div class="font-bold text-sm flex items-center gap-1.5"><?= htmlspecialchars($t['name'] ?? $key) ?><span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-primary/10 text-primary"><?= htmlspecialchars($key) ?></span><i class="fas fa-play text-[10px] text-gray-300 group-hover:text-primary ml-auto"></i></div>
                    <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 leading-snug"><?= htmlspecialchars($t['description'] ?? '') ?></div>
                </button>
                <?php else: ?>
                <a href="/academy#pricing" class="block rounded-lg border border-gray-200 dark:border-gray-800 bg-white/60 dark:bg-white/5 p-2.5 hover:border-primary transition-colors group">
                    <div class="font-bold text-sm flex items-center gap-1.5"><?= htmlspecialchars($t['name'] ?? $key) ?><span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-primary/10 text-primary"><?= htmlspecialchars($key) ?></span><i class="fas fa-lock text-[10px] text-gray-300 group-hover:text-primary ml-auto"></i></div>
                    <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 leading-snug"><?= htmlspecialchars($t['description'] ?? '') ?></div>
                </a>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="mt-8 grid lg:grid-cols-3 gap-6">
        <!-- Demo bot positions -->
        <div class="lg:col-span-2">
            <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-primary">What the class bot is trading</h2>
            <div id="lab-positions" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div>
        </div>
        <!-- AI reasoning stream -->
        <div>
            <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-primary"><i class="fas fa-brain mr-1"></i>The bot's mind</h2>
            <div id="lab-thoughts" class="space-y-2 max-h-[520px] overflow-y-auto pr-1 text-sm"></div>
        </div>
    </div>

    <!-- Expandable live trade modal (same idea as /gtb) -->
    <div id="lab-modal" class="hidden fixed inset-0 z-[70] bg-black/70 items-center justify-center p-4">
        <div class="bg-white dark:bg-[#0b1020] rounded-2xl border border-gray-200 dark:border-gray-700 w-full max-w-3xl max-h-[92vh] overflow-hidden flex flex-col">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-800">
                <div id="lab-modal-title" class="flex items-center gap-2 font-bold min-w-0"></div>
                <div class="flex items-center gap-3">
                    <button type="button" id="lab-modal-sell" onclick="labModalSell()" class="hidden text-[11px] font-bold px-3 py-1.5 rounded-lg bg-red-500 text-white hover:bg-red-600"><i class="fas fa-xmark mr-1"></i>Sell now</button>
                    <span id="lab-modal-live" class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800">…</span>
                    <button type="button" onclick="labModalClose()" class="text-gray-400 hover:text-primary"><i class="fas fa-xmark text-lg"></i></button>
                </div>
            </div>
            <div class="p-4 overflow-y-auto">
                <div id="lab-modal-pnl" class="mb-2 text-sm"></div>
                <div id="lab-modal-chart" class="w-full rounded-lg overflow-hidden" style="height:340px"></div>
                <div id="lab-modal-meta" class="mt-3 grid grid-cols-3 gap-2 text-center text-[11px] tabular-nums"></div>
                <div class="mt-4">
                    <div class="text-xs font-bold uppercase tracking-wide text-primary mb-2"><i class="fas fa-brain mr-1"></i>Why the bot is in this trade</div>
                    <div id="lab-modal-why" class="space-y-2 text-sm"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-8 rounded-xl border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 p-4 text-sm text-amber-800 dark:text-amber-200">
        <i class="fas fa-circle-info mr-1"></i> Educational only. This is a paper (testnet) simulation on live prices — no real money is at stake here. Crypto trading carries real risk of loss; the Academy teaches disciplined, risk-first methods, never guaranteed returns.
    </div>
</section>

<script>
const LAB_CSRF = <?= json_encode($csrf_token ?? '') ?>;
function labCoinIcon(base, size) {
    var b = String(base || '').toLowerCase(), up = String(base || '').toUpperCase().slice(0, 3);
    var h = 0; for (var i = 0; i < b.length; i++) h = (h * 31 + b.charCodeAt(i)) & 0xffffffff;
    var hue = ((h % 360) + 360) % 360, s = size || 22;
    var url = 'https://cdn.jsdelivr.net/gh/atomiclabs/cryptocurrency-icons@1a63530be6e374711a8554f31b17e4cb92c25fa5/128/color/' + b + '.png';
    return '<span style="position:relative;display:inline-flex;width:' + s + 'px;height:' + s + 'px;flex:none;vertical-align:middle;">'
        + '<span style="display:inline-flex;align-items:center;justify-content:center;width:' + s + 'px;height:' + s + 'px;border-radius:50%;font-size:' + (s * 0.42) + 'px;font-weight:700;color:#fff;background:hsl(' + hue + ',55%,45%);">' + up + '</span>'
        + '<img src="' + url + '" alt="" loading="lazy" style="position:absolute;inset:0;width:' + s + 'px;height:' + s + 'px;border-radius:50%;object-fit:cover;" onerror="this.remove()"></span>';
}
function labSparkline(vals, col) {
    if (!vals || vals.length < 2) return '';
    var w = 240, h = 44, mn = Math.min.apply(null, vals), mx = Math.max.apply(null, vals), rng = (mx - mn) || 1;
    var pts = vals.map(function (v, i) { return ((i / (vals.length - 1)) * w).toFixed(1) + ',' + (h - 5 - ((v - mn) / rng) * (h - 12)).toFixed(1); });
    var id = 'l' + Math.random().toString(36).slice(2, 7);
    return '<svg viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="none" style="position:absolute;inset:0;width:100%;height:100%;display:block;">'
        + '<defs><linearGradient id="' + id + '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' + col + '" stop-opacity="0.25"/><stop offset="1" stop-color="' + col + '" stop-opacity="0"/></linearGradient></defs>'
        + '<path d="M0,' + h + ' L' + pts.join(' L') + ' L' + w + ',' + h + ' Z" fill="url(#' + id + ')"/>'
        + '<path d="M' + pts.join(' L') + '" fill="none" stroke="' + col + '" stroke-width="1.75" stroke-linejoin="round" vector-effect="non-scaling-stroke"/></svg>';
}
function labFmt(n, d) { n = +n; return (n < 0 ? '−' : '+') + '$' + Math.abs(n).toFixed(d == null ? 4 : d); }
function labPrec(p) { p = Math.abs(+p) || 1; return p >= 1000 ? 2 : p >= 1 ? 4 : p >= 0.01 ? 6 : 8; }

function labFmtPrice(p) { p = +p; if (!isFinite(p)) return '0'; if (p >= 1000) return p.toLocaleString(undefined, { maximumFractionDigits: 2 }); if (p >= 1) return p.toFixed(4); if (p >= 0.01) return p.toFixed(6); return p.toPrecision(4); }
function labChgClass(up) { return up ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'; }

// ---- TradingView (Lightweight) candlestick chart + live Binance stream --------
var LAB = { chart: null, series: null, symbol: 'BTCUSDT', base: 'BTC', interval: '15m', ws: null, markets: { hot: [], gainers: [], losers: [] }, tab: 'hot', isPro: <?= $isPro ? 'true' : 'false' ?>, cards: {}, sig: {} };

function labChartTheme() {
    var dark = document.documentElement.classList.contains('dark');
    var grid = dark ? 'rgba(148,163,184,0.12)' : 'rgba(0,0,0,0.06)';
    return {
        layout: { background: { color: 'transparent' }, textColor: dark ? '#94a3b8' : '#475569' },
        grid: { vertLines: { color: grid }, horzLines: { color: grid } },
        rightPriceScale: { borderColor: grid },
        timeScale: { borderColor: grid, timeVisible: true, secondsVisible: false },
    };
}
function labApplyChartTheme() { if (LAB.chart) LAB.chart.applyOptions(labChartTheme()); }

function labInitChart() {
    var el = document.getElementById('lab-chart');
    if (!el || typeof LightweightCharts === 'undefined') return;
    LAB.chart = LightweightCharts.createChart(el, Object.assign({
        width: el.clientWidth, height: 360,
        crosshair: { mode: LightweightCharts.CrosshairMode ? LightweightCharts.CrosshairMode.Normal : 0 },
        handleScale: true, handleScroll: true,
    }, labChartTheme()));
    LAB.series = LAB.chart.addCandlestickSeries({ upColor: '#16a34a', downColor: '#ef4444', borderVisible: false, wickUpColor: '#16a34a', wickDownColor: '#ef4444' });
    new ResizeObserver(function () { if (LAB.chart) LAB.chart.applyOptions({ width: el.clientWidth }); }).observe(el);
}

function labLoadChart() {
    if (!LAB.series) return;
    fetch('/academy/klines?symbol=' + encodeURIComponent(LAB.symbol) + '&interval=' + LAB.interval, { cache: 'no-store', credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.ok || !Array.isArray(d.candles)) return;
            LAB.series.setData(d.candles.map(function (c) { return { time: c.time, open: c.open, high: c.high, low: c.low, close: c.close }; }));
            LAB.chart.timeScale().fitContent();
            if (d.candles.length) labSetPrice(+d.candles[d.candles.length - 1].close);
            labOpenStream();
        }).catch(function () {});
}

function labSetPrice(px) { var el = document.getElementById('lab-chart-price'); if (el) el.textContent = '$' + labFmtPrice(px); }
function labSetLive(txt, on) {
    var el = document.getElementById('lab-chart-live'); if (!el) return;
    el.textContent = txt;
    el.className = 'text-[10px] font-bold px-1.5 py-0.5 rounded-full ' + (on ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800');
}

function labCloseStream() { if (LAB.ws) { try { LAB.ws.onclose = null; LAB.ws.close(); } catch (e) {} LAB.ws = null; } }
function labOpenStream() {
    labCloseStream();
    if (typeof WebSocket === 'undefined') { labSetLive('offline', false); return; }
    var sym = LAB.symbol, iv = LAB.interval, ws;
    try { ws = new WebSocket('wss://stream.binance.com:9443/ws/' + sym.toLowerCase() + '@kline_' + iv); }
    catch (e) { labSetLive('offline', false); return; }
    LAB.ws = ws; labSetLive('connecting…', false);
    ws.onopen = function () { if (LAB.symbol === sym && LAB.interval === iv) labSetLive('● LIVE', true); };
    ws.onmessage = function (ev) {
        if (LAB.symbol !== sym || LAB.interval !== iv || !LAB.series) return;
        var m; try { m = JSON.parse(ev.data); } catch (e) { return; }
        var k = m && m.k; if (!k) return;
        try { LAB.series.update({ time: Math.floor(k.t / 1000), open: +k.o, high: +k.h, low: +k.l, close: +k.c }); } catch (e) {}
        labSetPrice(+k.c);
    };
    ws.onerror = function () { if (LAB.symbol === sym && LAB.interval === iv) labSetLive('reconnecting…', false); };
    ws.onclose = function () { if (LAB.symbol === sym && LAB.interval === iv && LAB.ws === ws) { labSetLive('reconnecting…', false); setTimeout(function () { if (LAB.symbol === sym && LAB.interval === iv) labOpenStream(); }, 2000); } };
}

function labSelectSymbol(symbol, base, price, chg) {
    LAB.symbol = symbol; LAB.base = base;
    document.getElementById('lab-chart-symbol').textContent = base + '/USDT';
    var ts = document.getElementById('lab-trade-sym'); if (ts) ts.textContent = base;
    var ico = document.getElementById('lab-chart-icon'); if (ico) ico.innerHTML = labCoinIcon(base, 22);
    if (price != null) labSetPrice(price);
    var up = (+chg) >= 0, chgEl = document.getElementById('lab-chart-change');
    chgEl.textContent = (up ? '+' : '') + (+chg).toFixed(2) + '%';
    chgEl.className = 'text-sm font-semibold ' + labChgClass(up);
    document.querySelectorAll('.lab-pair').forEach(function (b) { b.classList.toggle('bg-primary/10', b.dataset.symbol === symbol); });
    labLoadChart();
}

function labRenderMarketList() {
    var wrap = document.getElementById('lab-market-list'); if (!wrap) return;
    var list = LAB.markets[LAB.tab] || [];
    if (!list.length) { wrap.innerHTML = '<div class="py-10 text-center text-gray-400 text-sm">No markets.</div>'; return; }
    wrap.innerHTML = list.map(function (m) {
        var up = (+m.changePct) >= 0;
        return '<button type="button" class="lab-pair w-full flex items-center justify-between px-2 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/60 text-left ' + (m.symbol === LAB.symbol ? 'bg-primary/10' : '') + '"'
            + ' data-symbol="' + m.symbol + '" data-base="' + m.base + '" data-price="' + m.price + '" data-change="' + m.changePct + '">'
            + '<span class="flex items-center gap-2 font-medium text-sm min-w-0">' + labCoinIcon(m.base, 20) + '<span class="truncate">' + m.base + '<span class="text-gray-400 text-xs font-normal">/USDT</span></span></span>'
            + '<span class="text-right leading-tight shrink-0"><span class="block text-xs tabular-nums text-gray-800 dark:text-gray-100">$' + labFmtPrice(m.price) + '</span>'
            + '<span class="block text-[11px] ' + labChgClass(up) + '">' + (up ? '+' : '') + (+m.changePct).toFixed(2) + '%</span></span></button>';
    }).join('');
    wrap.querySelectorAll('.lab-pair').forEach(function (b) {
        b.addEventListener('click', function () { labSelectSymbol(b.dataset.symbol, b.dataset.base, +b.dataset.price, +b.dataset.change); });
    });
}

function labSetTab(tab) {
    LAB.tab = tab;
    document.querySelectorAll('#lab-tabs button').forEach(function (b) {
        var on = b.dataset.tab === tab;
        b.className = (b.dataset.tab === 'hot' ? 'flex-1 py-1.5 ' : 'flex-1 py-1.5 border-l border-gray-200 dark:border-gray-700 ') + (on ? 'bg-primary text-white' : 'text-gray-500 dark:text-gray-400');
    });
    labRenderMarketList();
}

function labLoadMarkets() {
    fetch('/academy/markets', { cache: 'no-store', credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d || !d.ok) return;
            LAB.markets = { hot: d.hot || [], gainers: d.gainers || [], losers: d.losers || [] };
            labRenderMarketList();
        }).catch(function () {});
}

function labHeartbeat(d) {
    var el = document.getElementById('lab-heartbeat'); if (!el) return;
    if (!d.running) { el.innerHTML = '<span class="w-2 h-2 rounded-full bg-gray-400"></span> Class bot is paused.'; return; }
    var ago = '', act = (d.last_action || '').replace(/[<>&]/g, function (c) { return { '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c]; });
    if (d.last_run_at) {
        var t = new Date(d.last_run_at.replace(' ', 'T') + 'Z'), s = Math.max(0, Math.round((Date.now() - t) / 1000));
        ago = s < 60 ? s + 's ago' : Math.round(s / 60) + 'm ago';
    }
    var stale = ago && parseInt(ago) > 5 && ago.indexOf('m') !== -1;   // >5m since last scan → likely stalled
    el.innerHTML = '<span class="w-2 h-2 rounded-full ' + (stale ? 'bg-amber-400' : 'bg-green-500 animate-pulse') + '"></span> '
        + '<span class="font-semibold ' + (stale ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400') + '">Class bot active</span>'
        + (ago ? ' · last scan ' + ago : '')
        + (act ? ' · <span class="text-gray-600 dark:text-gray-300">' + act + '</span>' : '');
}

function labRender(d) {
    document.getElementById('lab-run').textContent = d.running ? 'Live · trading' : 'Paused';
    document.getElementById('lab-run').className = 'text-[11px] font-bold px-2.5 py-1 rounded-full ' + (d.running ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400');
    labHeartbeat(d);
    document.getElementById('lab-open').textContent = (d.positions || []).length;
    labApplyVal('lab-unreal', labFmt(d.unrealized), 'mt-1 text-2xl font-extrabold ' + ((+d.unrealized) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'));
    labApplyVal('lab-realized', labFmt(d.realized), 'mt-1 text-2xl font-extrabold ' + ((+d.realized) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'));
    document.getElementById('lab-updated').textContent = new Date().toLocaleTimeString();

    labRenderTradeCards('lab-positions', d.positions || [], false, 'No open positions right now — the bot is waiting for a clean setup. Keep watching.');

    var th = d.thoughts || [], tw = document.getElementById('lab-thoughts');
    LAB.thoughts = th;   // keep for per-trade reasoning in the modal
    if (LABM.symbol) labModalWhy(LABM.symbol);   // refresh open modal's reasoning live
    if (!th.length) { tw.innerHTML = '<div class="py-8 text-center text-gray-400 text-sm">The bot hasn\'t spoken yet.</div>'; }
    else {
        tw.innerHTML = th.map(function (t) {
            var type = t.type || '', dotc = type === 'error' ? 'bg-red-500' : type === 'trade' ? 'bg-primary' : type === 'decision' ? 'bg-amber-400' : 'bg-gray-400';
            var msg = (t.message || '').replace(/[<>&]/g, function (c) { return { '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c]; });
            return '<div class="flex gap-2 rounded-lg border border-gray-100 dark:border-gray-800 p-2.5">'
                + '<span class="mt-1.5 w-1.5 h-1.5 rounded-full shrink-0 ' + dotc + '"></span>'
                + '<div class="min-w-0"><div class="text-gray-700 dark:text-gray-300 leading-snug">' + msg + '</div>'
                + '<div class="text-[10px] text-gray-400 mt-0.5">' + (t.created_at || '') + '</div></div></div>';
        }).join('');
    }
}

// Set a value + its color classes WITHOUT clobbering the eye-toggle blur.
function labApplyVal(id, text, base) {
    var el = document.getElementById(id); if (!el) return;
    var blurred = el.classList.contains('lab-blur');
    el.textContent = text;
    el.className = base + (blurred ? ' lab-blur' : '');
}

// Per-card hide toggle (blur the number; state persists per learner).
function labEye(key, ids) {
    var btn = document.getElementById('eye-' + key), i = btn && btn.querySelector('i');
    var hide = !(i && i.classList.contains('fa-eye-slash'));
    ids.forEach(function (id) { var el = document.getElementById(id); if (el) el.classList.toggle('lab-blur', hide); });
    if (i) i.className = hide ? 'fas fa-eye-slash' : 'fas fa-eye';
    try { localStorage.setItem('labeye_' + key, hide ? '1' : '0'); } catch (e) {}
}
function labInitEyes() {
    [['lab-wallet', ['lab-wallet', 'lab-wallet-pnl']], ['lab-open', ['lab-open']], ['lab-unreal', ['lab-unreal']], ['lab-realized', ['lab-realized']]].forEach(function (g) {
        var on = false; try { on = localStorage.getItem('labeye_' + g[0]) === '1'; } catch (e) {}
        if (on) {
            g[1].forEach(function (id) { var el = document.getElementById(id); if (el) el.classList.add('lab-blur'); });
            var btn = document.getElementById('eye-' + g[0]), i = btn && btn.querySelector('i'); if (i) i.className = 'fas fa-eye-slash';
        }
    });
}

function labLoadWallet() {
    fetch('/academy/wallet', { cache: 'no-store', credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d || !d.ok) return;
            var eq = +d.equity, start = +d.starting || 10000, pnl = eq - start;
            labApplyVal('lab-wallet', '$' + eq.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }), 'mt-1 text-4xl font-extrabold tabular-nums');
            labApplyVal('lab-wallet-pnl', (pnl >= 0 ? '+' : '−') + '$' + Math.abs(pnl).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                'text-lg font-bold ' + (pnl > 0 ? 'text-green-600 dark:text-green-400' : pnl < 0 ? 'text-red-500 dark:text-red-400' : 'text-gray-400'));
            LAB.botOn = !!d.bot_enabled;
            labSetToggle();
            labRenderMyPositions(d.positions || []);
        }).catch(function () {});
}

function labSetToggle() {
    var b = document.getElementById('lab-bot-toggle'), l = document.getElementById('lab-bot-toggle-label');
    if (!b) return;
    if (LAB.botOn) { b.className = 'inline-flex items-center gap-2 text-sm font-bold px-5 py-3 rounded-xl bg-red-500 text-white hover:bg-red-600 disabled:opacity-60'; b.querySelector('i').className = 'fas fa-stop'; l.textContent = 'Stop AI bot trading'; }
    else { b.className = 'inline-flex items-center gap-2 text-sm font-bold px-5 py-3 rounded-xl bg-green-600 text-white hover:bg-green-700 disabled:opacity-60'; b.querySelector('i').className = 'fas fa-play'; l.textContent = 'Start AI bot trading'; }
}

function labToggleBot() {
    var b = document.getElementById('lab-bot-toggle'); if (!b || b.disabled) return;
    b.disabled = true;
    fetch('/academy/bot/toggle', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': LAB_CSRF }, credentials: 'same-origin', body: JSON.stringify({ csrf_token: LAB_CSRF, on: !LAB.botOn }) })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d && d.ok) { LAB.botOn = !!d.bot_enabled; labSetToggle(); labLoadWallet(); } })
        .catch(function () {})
        .finally(function () { b.disabled = false; });
}

function labRenderMyPositions(pos) {
    var empty = LAB.botOn ? 'Bot is on — waiting for the next clean setup. Your followed trades will appear here automatically.'
        : (LAB.isPro ? 'No open trades. Buy a coin on paper below the chart, or press <b>Start AI bot trading</b> to follow the class bot.'
                     : 'No open trades yet. Pick a coin, then use <b>Buy on paper</b> under the chart to open your first manual trade.');
    labRenderTradeCards('lab-my-positions', pos, true, empty);
}

function labKey(gridId, p) { return gridId + '|' + (p.id != null ? p.id : (p.symbol + '-' + p.entry)); }

// Rich, /gtb-style trade cards: mini candlestick + entry/SL/TP price lines + live P&L (+ sell if manual).
// Cards are rebuilt only when the set of positions changes; otherwise updated in place (no chart flicker).
function labRenderTradeCards(gridId, pos, sellable, emptyMsg) {
    var wrap = document.getElementById(gridId); if (!wrap) return;
    pos = pos || [];
    var cleanup = function () { Object.keys(LAB.cards).forEach(function (k) { if (k.indexOf(gridId + '|') === 0) { try { LAB.cards[k].chart && LAB.cards[k].chart.remove(); } catch (e) {} delete LAB.cards[k]; } }); };
    if (!pos.length) { wrap.innerHTML = '<div class="col-span-full py-8 text-center text-sm text-gray-400">' + emptyMsg + '</div>'; LAB.sig[gridId] = ''; cleanup(); return; }
    var sig = pos.map(function (p) { return p.id != null ? p.id : (p.symbol + p.entry); }).join(',');
    if (LAB.sig[gridId] !== sig) { cleanup(); wrap.innerHTML = ''; pos.forEach(function (p) { labBuildTradeCard(wrap, gridId, p, sellable); }); LAB.sig[gridId] = sig; }
    pos.forEach(function (p) { labUpdateTradeCard(gridId, p); });
}

function labBuildTradeCard(wrap, gridId, p, sellable) {
    var base = p.base || p.symbol.replace('USDT', '');
    var isManual = sellable && p.id != null && !p.auto;
    var root = document.createElement('div');
    root.className = 'relative rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/5 p-3 overflow-hidden min-w-0';
    root.innerHTML =
        '<button data-expand title="Expand" class="absolute top-2 right-2 z-20 w-6 h-6 rounded-md bg-black/5 dark:bg-white/10 text-gray-500 dark:text-gray-300 hover:text-primary flex items-center justify-center text-xs"><i class="fas fa-up-right-and-down-left-from-center"></i></button>'
        + '<div class="flex items-start justify-between gap-2 mb-1">'
        +   '<div class="min-w-0 flex flex-wrap items-center gap-1 font-bold">' + labCoinIcon(base, 18) + '<span class="truncate">' + base + '<span class="text-gray-400 text-xs font-normal">/USDT</span></span>'
        +     (p.template ? '<span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-primary/10 text-primary">' + p.template + '</span>' : '')
        +     (isManual ? '<span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400">manual</span>' : '')
        +   '</div>'
        +   '<div class="text-right leading-tight shrink-0 pr-7"><span data-pnl class="block text-sm font-bold whitespace-nowrap"></span><span data-pct class="block text-[11px] whitespace-nowrap"></span></div>'
        + '</div>'
        + '<div data-chart title="Click to expand" class="w-full rounded overflow-hidden cursor-pointer" style="height:120px"></div>'
        + '<div class="mt-1.5 grid grid-cols-3 gap-1 text-[10px] tabular-nums text-center">'
        +   '<div class="text-gray-500 dark:text-gray-400">entry<br><span data-entry class="text-gray-800 dark:text-gray-200"></span></div>'
        +   '<div class="text-red-500">SL<br><span data-sl></span></div>'
        +   '<div class="text-green-600 dark:text-green-400">TP<br><span data-tp></span></div>'
        + '</div>'
        + (isManual ? '<button data-sell class="mt-1.5 w-full text-[11px] font-bold py-1.5 rounded-lg bg-red-500 text-white hover:bg-red-600"><i class="fas fa-xmark mr-1"></i>Close · sell now</button>'
                    : '<div class="mt-1.5 text-[10px] text-center text-gray-400">bot-managed</div>');
    wrap.appendChild(root);
    var card = { root: root, chart: null, series: null, p: p };
    LAB.cards[labKey(gridId, p)] = card;
    var chartEl = root.querySelector('[data-chart]');
    var open = function () { labExpandTrade(p.symbol, base, +p.entry, p.stop_loss || 0, p.take_profit || 0, isManual ? p.id : 0); };
    chartEl.addEventListener('click', open);
    root.querySelector('[data-expand]').addEventListener('click', function (e) { e.stopPropagation(); open(); });
    if (isManual) root.querySelector('[data-sell]').addEventListener('click', function () { labSell(p.id, this); });
    if (typeof LightweightCharts !== 'undefined') {
        card.chart = LightweightCharts.createChart(chartEl, Object.assign({ autoSize: true, crosshair: { mode: 0 }, handleScale: false, handleScroll: false, rightPriceScale: { visible: true }, timeScale: { visible: false } }, labChartTheme()));
        var prec = labPrec(p.entry || p.mark || 1);
        card.series = card.chart.addCandlestickSeries({ upColor: '#16a34a', downColor: '#ef4444', borderVisible: false, wickUpColor: '#16a34a', wickDownColor: '#ef4444', priceFormat: { type: 'price', precision: prec, minMove: Math.pow(10, -prec) } });
        if (p.entry) card.series.createPriceLine({ price: +p.entry, color: '#3b82f6', lineWidth: 1, lineStyle: 2, title: 'entry' });
        if (p.stop_loss) card.series.createPriceLine({ price: +p.stop_loss, color: '#ef4444', lineWidth: 1, lineStyle: 2, title: 'SL' });
        if (p.take_profit) card.series.createPriceLine({ price: +p.take_profit, color: '#16a34a', lineWidth: 1, lineStyle: 2, title: 'TP' });
        fetch('/academy/klines?symbol=' + encodeURIComponent(p.symbol) + '&interval=5m', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (d.ok && card.series && Array.isArray(d.candles)) { card.series.setData(d.candles.map(function (c) { return { time: c.time, open: c.open, high: c.high, low: c.low, close: c.close }; })); card.chart.timeScale().fitContent(); } })
            .catch(function () {});
    }
}

function labUpdateTradeCard(gridId, p) {
    var card = LAB.cards[labKey(gridId, p)]; if (!card) return;
    card.p = p;
    var up = (+p.unrealized) >= 0, cls = up ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400';
    var pnl = card.root.querySelector('[data-pnl]'), pct = card.root.querySelector('[data-pct]');
    if (pnl) { pnl.textContent = labFmt(p.unrealized); pnl.className = 'block text-sm font-bold ' + cls; }
    if (pct) { pct.textContent = (up ? '+' : '') + (+p.pnlPct).toFixed(2) + '%'; pct.className = 'block text-[11px] ' + cls; }
    var e = card.root.querySelector('[data-entry]'), s = card.root.querySelector('[data-sl]'), t = card.root.querySelector('[data-tp]');
    if (e) e.textContent = '$' + (+p.entry).toPrecision(6);
    if (s) s.textContent = p.stop_loss ? '$' + (+p.stop_loss).toPrecision(6) : '—';
    if (t) t.textContent = p.take_profit ? '$' + (+p.take_profit).toPrecision(6) : 'trail';
}

function labBuy() {
    var btn = document.getElementById('lab-buy-btn'), st = document.getElementById('lab-trade-status');
    if (!btn || btn.disabled) return;
    var amt = +document.getElementById('lab-trade-amt').value;
    if (!(amt >= 10)) { st.textContent = 'Minimum $10.'; st.className = 'text-xs text-amber-500'; return; }
    btn.disabled = true; st.textContent = 'Placing…'; st.className = 'text-xs text-gray-400';
    fetch('/academy/trade/buy', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': LAB_CSRF }, credentials: 'same-origin', body: JSON.stringify({ csrf_token: LAB_CSRF, symbol: LAB.symbol, amount: amt }) })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d && d.ok) { st.textContent = '✓ Bought ' + d.base + ' at $' + (+d.entry).toPrecision(6); st.className = 'text-xs text-green-600 dark:text-green-400'; labLoadWallet(); }
            else { st.textContent = (d && d.error) || 'Could not buy.'; st.className = 'text-xs text-amber-500'; }
        }).catch(function () { st.textContent = 'Network error.'; st.className = 'text-xs text-amber-500'; })
        .finally(function () { btn.disabled = false; });
}

function labSell(id, btn, fromModal) {
    if (!window.confirm('Close this trade now at the current market price?')) return;
    if (btn) btn.disabled = true;
    fetch('/academy/trade/sell', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': LAB_CSRF }, credentials: 'same-origin', body: JSON.stringify({ csrf_token: LAB_CSRF, id: id }) })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            var st = document.getElementById('lab-trade-status');
            if (d && d.ok) { if (st) { st.textContent = '✓ Closed · P&L ' + labFmt(d.realized); st.className = 'text-xs ' + ((+d.realized) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-500'); } labLoadWallet(); if (fromModal) labModalClose(); }
            else { if (st) { st.textContent = (d && d.error) || 'Could not close.'; st.className = 'text-xs text-amber-500'; } if (btn) btn.disabled = false; }
        }).catch(function () { if (btn) btn.disabled = false; });
}

function labActivateStrategy(key, name, btn) {
    if (btn && btn.disabled) return;
    if (btn) btn.classList.add('opacity-60');
    fetch('/academy/bot/activate', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': LAB_CSRF }, credentials: 'same-origin', body: JSON.stringify({ csrf_token: LAB_CSRF, template: key }) })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d && d.ok) {
                LAB.botOn = true; labSetToggle(); labLoadWallet();
                document.querySelectorAll('.lab-tpl').forEach(function (b) { var on = b.dataset.tpl === key; b.classList.toggle('border-primary', on); b.classList.toggle('ring-1', on); b.classList.toggle('ring-primary', on); });
                var hb = document.getElementById('lab-heartbeat'); if (hb) hb.innerHTML = '<span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span> <span class="font-semibold text-green-600 dark:text-green-400">Bot started on ' + name + '</span> — trades will follow this strategy.';
            } else if (d && d.upgrade) { window.location.href = '/academy#pricing'; }
            else if (d) { alert(d.error || 'Could not activate the bot.'); }
        }).catch(function () {})
        .finally(function () { if (btn) btn.classList.remove('opacity-60'); });
}

// ---- Expandable live trade modal (own chart + Binance WS) ---------------------
var LABM = { chart: null, series: null, ws: null, symbol: '', posId: 0 };
function labModalSell() {
    if (!LABM.posId) return;
    labSell(LABM.posId, document.getElementById('lab-modal-sell'), true);
}
function labModalClose() {
    var o = document.getElementById('lab-modal'); if (!o) return;
    if (LABM.ws) { try { LABM.ws.onclose = null; LABM.ws.close(); } catch (e) {} LABM.ws = null; }
    if (LABM.chart) { try { LABM.chart.remove(); } catch (e) {} LABM.chart = null; LABM.series = null; }
    o.classList.add('hidden'); o.classList.remove('flex');
}
function labExpandTrade(symbol, base, entry, sl, tp, posId) {
    var o = document.getElementById('lab-modal'); if (!o || typeof LightweightCharts === 'undefined') return;
    LABM.symbol = symbol; LABM.posId = posId || 0;
    var sellBtn = document.getElementById('lab-modal-sell');
    if (sellBtn) sellBtn.classList.toggle('hidden', !LABM.posId);
    document.getElementById('lab-modal-title').innerHTML = labCoinIcon(base, 22) + '<span class="truncate">' + base + '<span class="text-gray-400 text-xs font-normal">/USDT</span></span>';
    document.getElementById('lab-modal-meta').innerHTML =
        '<div class="text-gray-500 dark:text-gray-400">entry<br><span class="text-gray-800 dark:text-gray-200">$' + (+entry).toPrecision(6) + '</span></div>'
        + '<div class="text-red-500">stop-loss<br><span>' + (sl ? '$' + (+sl).toPrecision(6) : '—') + '</span></div>'
        + '<div class="text-green-600 dark:text-green-400">take-profit<br><span>' + (tp ? '$' + (+tp).toPrecision(6) : 'trailing') + '</span></div>';
    o.classList.remove('hidden'); o.classList.add('flex');
    var el = document.getElementById('lab-modal-chart'); el.innerHTML = '';
    LABM.chart = LightweightCharts.createChart(el, Object.assign({ width: el.clientWidth, height: 340 }, labChartTheme()));
    LABM.series = LABM.chart.addCandlestickSeries({ upColor: '#16a34a', downColor: '#ef4444', borderVisible: false, wickUpColor: '#16a34a', wickDownColor: '#ef4444' });
    new ResizeObserver(function () { if (LABM.chart) LABM.chart.applyOptions({ width: el.clientWidth }); }).observe(el);
    fetch('/academy/klines?symbol=' + encodeURIComponent(symbol) + '&interval=15m', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.ok || !Array.isArray(d.candles) || !LABM.series) return;
            LABM.series.setData(d.candles.map(function (c) { return { time: c.time, open: c.open, high: c.high, low: c.low, close: c.close }; }));
            LABM.chart.timeScale().fitContent();
            if (d.candles.length) labModalPnl(entry, +d.candles[d.candles.length - 1].close);
            labModalStream(symbol, entry);
        }).catch(function () {});
    labModalWhy(symbol);
}

// Show the class bot's actual reasoning for this specific coin (filtered from its live thought stream).
function labModalWhy(symbol) {
    var box = document.getElementById('lab-modal-why'); if (!box) return;
    var base = (symbol || '').replace(/USDT$/, '');
    var mine = (LAB.thoughts || []).filter(function (t) {
        var m = (t.message || '').toUpperCase();
        return m.indexOf(base.toUpperCase()) !== -1 || m.indexOf(symbol.toUpperCase()) !== -1;
    });
    if (!mine.length) {
        box.innerHTML = '<div class="text-gray-400 text-xs">No recent notes on ' + base + ' yet. The bot logs its reasoning as it evaluates and manages this trade — check back in a moment, or read the full stream in <b>The bot\'s mind</b> below.</div>';
        return;
    }
    box.innerHTML = mine.slice(0, 8).map(function (t) {
        var type = t.type || '', dotc = type === 'error' ? 'bg-red-500' : type === 'trade' ? 'bg-primary' : type === 'decision' ? 'bg-amber-400' : 'bg-gray-400';
        var msg = (t.message || '').replace(/[<>&]/g, function (c) { return { '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c]; });
        return '<div class="flex gap-2 rounded-lg border border-gray-100 dark:border-gray-800 p-2.5">'
            + '<span class="mt-1.5 w-1.5 h-1.5 rounded-full shrink-0 ' + dotc + '"></span>'
            + '<div class="min-w-0"><div class="text-gray-700 dark:text-gray-300 leading-snug">' + msg + '</div>'
            + '<div class="text-[10px] text-gray-400 mt-0.5">' + (t.created_at || '') + '</div></div></div>';
    }).join('');
}
function labModalPnl(entry, mark) {
    var up = mark >= entry, col = up ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400';
    var pct = entry > 0 ? (mark - entry) / entry * 100 : 0;
    document.getElementById('lab-modal-pnl').innerHTML = 'Mark <span class="font-bold tabular-nums">$' + mark.toPrecision(6) + '</span> · <span class="font-bold ' + col + '">' + (up ? '+' : '') + pct.toFixed(2) + '%</span>';
}
function labModalStream(symbol, entry) {
    if (LABM.ws) { try { LABM.ws.onclose = null; LABM.ws.close(); } catch (e) {} LABM.ws = null; }
    var el = document.getElementById('lab-modal-live');
    if (typeof WebSocket === 'undefined') { el.textContent = 'offline'; return; }
    var ws; try { ws = new WebSocket('wss://stream.binance.com:9443/ws/' + symbol.toLowerCase() + '@kline_15m'); } catch (e) { return; }
    LABM.ws = ws; el.textContent = 'connecting…';
    ws.onopen = function () { if (LABM.symbol === symbol) { el.textContent = '● LIVE'; el.className = 'text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400'; } };
    ws.onmessage = function (ev) {
        if (LABM.symbol !== symbol || !LABM.series) return;
        var m; try { m = JSON.parse(ev.data); } catch (e) { return; } var k = m && m.k; if (!k) return;
        try { LABM.series.update({ time: Math.floor(k.t / 1000), open: +k.o, high: +k.h, low: +k.l, close: +k.c }); } catch (e) {}
        labModalPnl(entry, +k.c);
    };
    ws.onclose = function () { if (LABM.symbol === symbol && LABM.ws === ws) { LABM.ws = null; } };
}

function labAnalyzeCoin() { labAnalyzeRun({ scope: 'coin', btn: 'lab-anz-coin-btn', label: 'lab-anz-coin-label', out: 'lab-anz-coin-out', reset: 'Analyze this coin with AI' }); }
function labAnalyzeMarket() { labAnalyzeRun({ scope: 'market', btn: 'lab-anz-mkt-btn', label: 'lab-anz-mkt-label', out: 'lab-anz-mkt-out', reset: 'Analyze market with AI' }); }

function labAnalyzeRun(o) {
    var btn = document.getElementById(o.btn), lbl = document.getElementById(o.label), out = document.getElementById(o.out);
    if (!btn || btn.disabled) return;
    btn.disabled = true; lbl.textContent = 'Thinking…';
    out.classList.remove('hidden');
    var what = o.scope === 'market' ? "today's market" : (LAB.base + '/USDT');
    out.innerHTML = '<div class="flex items-center gap-2 text-gray-500"><i class="fas fa-spinner fa-spin"></i> The bot is analysing ' + what + '…</div>';
    fetch('/academy/bot/analyze', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': LAB_CSRF }, credentials: 'same-origin', body: JSON.stringify({ csrf_token: LAB_CSRF, symbol: LAB.symbol, scope: o.scope }) })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d || !d.ok) { out.innerHTML = '<div class="text-amber-600 dark:text-amber-400"><i class="fas fa-circle-info mr-1"></i>' + ((d && d.error) || 'Analysis unavailable.') + '</div>'; return; }
            var dec = (d.decision || '').toUpperCase(), badge = '', cls = '';
            if (dec.indexOf('BUY') === 0) { cls = 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400'; badge = dec; }
            else if (dec === 'HOLD') { cls = 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400'; badge = 'HOLD'; }
            else if (dec === 'SKIP') { cls = 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'; badge = 'SKIP'; }
            var head = o.scope === 'market' ? '<i class="fas fa-magnifying-glass-chart text-primary"></i> Market scan' : (labCoinIcon(d.base, 20) + d.base + '/USDT');
            var body = (d.text || '').replace(/[<>&]/g, function (c) { return { '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c]; }).replace(/\n/g, '<br>');
            out.innerHTML = '<div class="flex items-center gap-2 mb-1.5 font-bold">' + head
                + (badge ? '<span class="ml-1 text-[10px] font-bold uppercase px-2 py-0.5 rounded-full ' + cls + '">' + badge + '</span>' : '')
                + '<span class="ml-auto text-[10px] text-gray-400 font-normal">AI · advisory</span></div>'
                + '<div class="text-gray-700 dark:text-gray-200 leading-relaxed">' + body + '</div>';
        })
        .catch(function () { out.innerHTML = '<div class="text-amber-600 dark:text-amber-400">Network error — try again.</div>'; })
        .finally(function () { btn.disabled = false; lbl.textContent = o.reset; });
}

var labTimer = null;
function labPoll() {
    fetch('/academy/bot/data', { cache: 'no-store', credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d && d.ok) labRender(d); })
        .catch(function () {});
}
document.addEventListener('DOMContentLoaded', function () {
    labInitChart();
    labInitEyes();
    document.getElementById('lab-chart-icon').innerHTML = labCoinIcon('BTC', 22);
    labSetTab('hot');
    labLoadWallet();          // this learner's own $10k paper wallet
    labLoadMarkets();
    labLoadChart();            // default BTCUSDT / 15m
    // Interval selector
    document.querySelectorAll('#lab-iv button').forEach(function (b) {
        b.addEventListener('click', function () {
            LAB.interval = b.dataset.iv;
            document.querySelectorAll('#lab-iv button').forEach(function (x) {
                x.classList.toggle('bg-primary', x.dataset.iv === LAB.interval);
                x.classList.toggle('text-white', x.dataset.iv === LAB.interval);
            });
            labLoadChart();
        });
    });
    var iv15 = document.querySelector('#lab-iv button[data-iv="15m"]'); if (iv15) { iv15.classList.add('bg-primary', 'text-white'); }
    // Tabs
    document.querySelectorAll('#lab-tabs button').forEach(function (b) {
        b.addEventListener('click', function () { labSetTab(b.dataset.tab); });
    });
    // Re-theme chart when the light/dark toggle flips
    var _toggle = window.gtaToggleTheme;
    window.gtaToggleTheme = function () { if (_toggle) _toggle(); labApplyChartTheme(); };
    // Bot state / positions / reasoning
    labPoll();
    labTimer = setInterval(labPoll, 6000);
    setInterval(labLoadMarkets, 60000);
});
</script>
</body>
</html>
