<?php // academy/bot.php — read-only "Live Bot Lab" for Academy members (watch the testnet bot). ?>
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
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css"><style>.dark{color-scheme:dark}</style>
</head>
<body class="bg-white dark:bg-[#0b1020] text-gray-900 dark:text-gray-100 min-h-screen">
<header class="border-b border-gray-200 dark:border-gray-800 sticky top-0 bg-white/80 dark:bg-[#0b1020]/80 backdrop-blur z-30">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
        <a href="/academy/learn" class="flex items-center gap-2 font-bold"><i class="fas fa-graduation-cap text-primary"></i> Ginto <span class="text-primary">Trading Academy</span></a>
        <div class="flex items-center gap-3 text-sm">
            <a href="/academy/learn" class="text-gray-500 hover:text-primary"><i class="fas fa-book-open mr-1"></i>Lessons</a>
            <button onclick="gtaToggleTheme()" title="Toggle light / dark" class="w-9 h-9 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-primary"><i class="fas fa-circle-half-stroke"></i></button>
            <span class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400">Member</span>
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

    <!-- Stats -->
    <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4"><div class="text-xs text-gray-500 dark:text-gray-400">Open positions</div><div id="lab-open" class="mt-1 text-2xl font-extrabold">—</div></div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4"><div class="text-xs text-gray-500 dark:text-gray-400">Unrealized (paper)</div><div id="lab-unreal" class="mt-1 text-2xl font-extrabold">—</div></div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4"><div class="text-xs text-gray-500 dark:text-gray-400">Realized (paper)</div><div id="lab-realized" class="mt-1 text-2xl font-extrabold">—</div></div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4"><div class="text-xs text-gray-500 dark:text-gray-400">Updated</div><div id="lab-updated" class="mt-1 text-sm font-semibold text-gray-500 dark:text-gray-400 pt-2">—</div></div>
    </div>

    <!-- Live market movers -->
    <h2 class="mt-8 mb-3 text-sm font-bold uppercase tracking-wide text-primary">Today's market</h2>
    <div id="lab-movers" class="grid grid-cols-1 sm:grid-cols-3 gap-3"></div>

    <div class="mt-8 grid lg:grid-cols-3 gap-6">
        <!-- Positions -->
        <div class="lg:col-span-2">
            <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-primary">What the bot is trading</h2>
            <div id="lab-positions" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div>
        </div>
        <!-- AI reasoning stream -->
        <div>
            <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-primary"><i class="fas fa-brain mr-1"></i>The bot's mind</h2>
            <div id="lab-thoughts" class="space-y-2 max-h-[520px] overflow-y-auto pr-1 text-sm"></div>
        </div>
    </div>

    <div class="mt-8 rounded-xl border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 p-4 text-sm text-amber-800 dark:text-amber-200">
        <i class="fas fa-circle-info mr-1"></i> Educational only. This is a paper (testnet) simulation on live prices — no real money is at stake here. Crypto trading carries real risk of loss; the Academy teaches disciplined, risk-first methods, never guaranteed returns.
    </div>
</section>

<script>
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
function labFmt(n, d) { n = +n; return (n >= 0 ? '+' : '') + '$' + Math.abs(n).toFixed(d == null ? 4 : d).replace('-', ''); }
function labPrec(p) { p = Math.abs(+p) || 1; return p >= 1000 ? 2 : p >= 1 ? 4 : p >= 0.01 ? 6 : 8; }

function labRenderMovers() {
    var wrap = document.getElementById('lab-movers'); if (!wrap) return;
    fetch('/api/academy/movers', { cache: 'no-store', credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d || !d.ok || !d.movers || !d.movers.length) return;
            wrap.innerHTML = d.movers.map(function (m) {
                var up = (+m.chg) >= 0, col = up ? '#22c55e' : '#ef4444';
                return '<div style="border-radius:10px;overflow:hidden;" class="border border-gray-200 dark:border-gray-800 bg-white/50 dark:bg-white/5">'
                    + '<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px 3px;font-size:11px;font-weight:700;">'
                    + '<span class="text-gray-500 dark:text-gray-400">' + m.tag + ' · ' + m.base + '</span>'
                    + '<span style="color:' + col + ';">' + (up ? '+' : '') + m.chg + '%</span></div>'
                    + '<div style="position:relative;height:44px;">' + labSparkline(m.closes || [], col) + '</div></div>';
            }).join('');
        }).catch(function () {});
}

function labRender(d) {
    document.getElementById('lab-run').textContent = d.running ? 'Live · trading' : 'Paused';
    document.getElementById('lab-run').className = 'text-[11px] font-bold px-2.5 py-1 rounded-full ' + (d.running ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400');
    document.getElementById('lab-open').textContent = (d.positions || []).length;
    var ur = document.getElementById('lab-unreal'); ur.textContent = labFmt(d.unrealized); ur.className = 'mt-1 text-2xl font-extrabold ' + ((+d.unrealized) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400');
    var rz = document.getElementById('lab-realized'); rz.textContent = labFmt(d.realized); rz.className = 'mt-1 text-2xl font-extrabold ' + ((+d.realized) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400');
    document.getElementById('lab-updated').textContent = new Date().toLocaleTimeString();

    var pos = d.positions || [], pw = document.getElementById('lab-positions');
    if (!pos.length) { pw.innerHTML = '<div class="col-span-full py-10 text-center text-gray-400 text-sm">No open positions right now — the bot is waiting for a clean setup. Keep watching.</div>'; }
    else {
        pw.innerHTML = pos.map(function (p) {
            var up = (+p.pnlPct) >= 0, col = up ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400', prec = labPrec(p.entry);
            return '<div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/5 p-3">'
                + '<div class="flex items-center justify-between gap-2 mb-2">'
                +   '<div class="flex items-center gap-1.5 font-bold min-w-0">' + labCoinIcon(p.base, 20) + '<span class="truncate">' + p.base + '<span class="text-gray-400 text-xs font-normal">/USDT</span></span>'
                +     '<span class="ml-1 text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-primary/10 text-primary">' + (p.template || '') + '</span></div>'
                +   '<div class="text-right shrink-0"><div class="text-sm font-bold ' + col + '">' + labFmt(p.unrealized) + '</div><div class="text-[11px] ' + col + '">' + (up ? '+' : '') + (+p.pnlPct).toFixed(2) + '%</div></div>'
                + '</div>'
                + '<div class="grid grid-cols-3 gap-1 text-[10px] tabular-nums text-center">'
                +   '<div class="text-gray-500 dark:text-gray-400">entry<br><span class="text-gray-800 dark:text-gray-200">$' + (+p.entry).toPrecision(6) + '</span></div>'
                +   '<div class="text-red-500">SL<br><span>$' + (+p.stop_loss).toPrecision(6) + '</span></div>'
                +   '<div class="text-green-600 dark:text-green-400">TP<br><span>' + (p.take_profit ? '$' + (+p.take_profit).toPrecision(6) : 'trail') + '</span></div>'
                + '</div>'
                + '<div class="mt-1 text-[10px] text-center text-gray-400">mark $' + (+p.mark).toPrecision(6) + '</div>'
                + '</div>';
        }).join('');
    }

    var th = d.thoughts || [], tw = document.getElementById('lab-thoughts');
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

var labTimer = null;
function labPoll() {
    fetch('/academy/bot/data', { cache: 'no-store', credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d && d.ok) labRender(d); })
        .catch(function () {});
}
document.addEventListener('DOMContentLoaded', function () {
    labRenderMovers(); labPoll();
    labTimer = setInterval(labPoll, 6000);
    setInterval(labRenderMovers, 60000);
});
</script>
</body>
</html>
