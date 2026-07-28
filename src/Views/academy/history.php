<?php // academy/history.php — a learner's full paper-trading transaction history. ?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Trade History — Ginto Trading Academy') ?></title>
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
    <div class="max-w-5xl mx-auto px-4 h-16 flex items-center justify-between">
        <a href="/academy/bot" class="flex items-center gap-2 font-bold"><i class="fas fa-arrow-left text-primary"></i> Back to Bot Lab</a>
        <div class="flex items-center gap-3 text-sm">
            <a href="/academy/thoughts" class="text-gray-500 hover:text-primary"><i class="fas fa-brain mr-1"></i>Bot's mind</a>
            <?php include __DIR__ . '/_silverqueen_button.php'; ?>
            <button onclick="gtaToggleTheme()" title="Toggle light / dark" class="w-9 h-9 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-primary"><i class="fas fa-circle-half-stroke"></i></button>
            <a href="/logout" class="inline-flex items-center gap-1 text-gray-500 hover:text-red-500" title="Log out"><i class="fas fa-arrow-right-from-bracket"></i><span class="hidden sm:inline">Log out</span></a>
        </div>
    </div>
</header>

<section class="max-w-5xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-extrabold flex items-center gap-2"><i class="fas fa-clock-rotate-left text-primary"></i> Your trade history</h1>
    <p class="mt-2 text-gray-600 dark:text-gray-300">Every paper trade on your $10,000 practice wallet — manual, AI, and bot-followed — with entry, exit, and realized P&amp;L.</p>

    <!-- Summary tiles -->
    <div class="mt-5 grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-3"><div class="text-[11px] text-gray-500 dark:text-gray-400">Closed trades</div><div id="sum-count" class="mt-0.5 text-xl font-extrabold tabular-nums">—</div></div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-3"><div class="text-[11px] text-gray-500 dark:text-gray-400">Win rate</div><div id="sum-win" class="mt-0.5 text-xl font-extrabold tabular-nums">—</div></div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-3"><div class="text-[11px] text-gray-500 dark:text-gray-400">Realized P&amp;L (page)</div><div id="sum-pnl" class="mt-0.5 text-xl font-extrabold tabular-nums">—</div></div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-3"><div class="text-[11px] text-gray-500 dark:text-gray-400">Open now</div><div id="sum-open" class="mt-0.5 text-xl font-extrabold tabular-nums">—</div></div>
    </div>

    <!-- Filters -->
    <div id="hist-tabs" class="mt-5 inline-flex rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 text-xs font-bold">
        <button data-scope="all" class="px-4 py-2">All</button>
        <button data-scope="closed" class="px-4 py-2 border-l border-gray-200 dark:border-gray-700">Closed</button>
        <button data-scope="open" class="px-4 py-2 border-l border-gray-200 dark:border-gray-700">Open</button>
    </div>

    <div class="mt-3 overflow-x-auto rounded-2xl border border-gray-200 dark:border-gray-800">
        <table class="w-full text-sm">
            <thead class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-white/5">
                <tr>
                    <th class="text-left font-semibold px-3 py-2">Coin</th>
                    <th class="text-left font-semibold px-3 py-2">Type</th>
                    <th class="text-right font-semibold px-3 py-2">Size</th>
                    <th class="text-right font-semibold px-3 py-2">Entry</th>
                    <th class="text-right font-semibold px-3 py-2">Exit</th>
                    <th class="text-right font-semibold px-3 py-2">P&amp;L</th>
                    <th class="text-left font-semibold px-3 py-2 hidden sm:table-cell">Closed</th>
                    <th class="text-left font-semibold px-3 py-2 hidden md:table-cell">Reason</th>
                </tr>
            </thead>
            <tbody id="hist-body">
                <tr><td colspan="8" class="px-3 py-10 text-center text-gray-400">Loading…</td></tr>
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex items-center justify-between">
        <button id="hist-prev" onclick="histPage(-1)" class="text-sm px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-primary disabled:opacity-40"><i class="fas fa-chevron-left mr-1"></i>Newer</button>
        <span id="hist-pageinfo" class="text-xs text-gray-400">—</span>
        <button id="hist-next" onclick="histPage(1)" class="text-sm px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-primary disabled:opacity-40">Older<i class="fas fa-chevron-right ml-1"></i></button>
    </div>
</section>

<script>
var HIST = { scope: 'all', page: 1, per: 25, total: 0 };
function histCoinIcon(base){var up=String(base||'').toUpperCase().slice(0,3),b=String(base||'').toLowerCase(),h=0;for(var i=0;i<b.length;i++)h=(h*31+b.charCodeAt(i))&0xffffffff;var hue=((h%360)+360)%360;var url='https://cdn.jsdelivr.net/gh/atomiclabs/cryptocurrency-icons@1a63530be6e374711a8554f31b17e4cb92c25fa5/128/color/'+b+'.png';return '<span style="position:relative;display:inline-flex;width:20px;height:20px;flex:none;vertical-align:middle;"><span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;font-size:8px;font-weight:700;color:#fff;background:hsl('+hue+',55%,45%);">'+up+'</span><img src="'+url+'" alt="" loading="lazy" style="position:absolute;inset:0;width:20px;height:20px;border-radius:50%;object-fit:cover;" onerror="this.remove()"></span>';}
function histNum(p){p=+p;if(!isFinite(p))return '0';if(p>=1000)return p.toLocaleString(undefined,{maximumFractionDigits:2});if(p>=1)return p.toFixed(4);if(p>=0.01)return p.toFixed(6);return p.toPrecision(4);}
function histMoney(n){n=+n;return (n<0?'−':'+')+'$'+Math.abs(n).toFixed(2);}
function histEsc(s){return String(s==null?'':s).replace(/[<>&]/g,function(c){return {'<':'&lt;','>':'&gt;','&':'&amp;'}[c];});}
function histTypeBadge(r){
    if(r.template==='ai') return '<span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-primary/10 text-primary">AI</span>';
    if(r.auto) return '<span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-secondary/10 text-secondary">bot'+(r.template?' · '+histEsc(r.template):'')+'</span>';
    return '<span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400">manual</span>';
}
function histLoad(){
    var body=document.getElementById('hist-body');
    body.innerHTML='<tr><td colspan="8" class="px-3 py-10 text-center text-gray-400">Loading…</td></tr>';
    fetch('/academy/history/data?scope='+HIST.scope+'&page='+HIST.page,{cache:'no-store',credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(d){
            if(!d||!d.ok){body.innerHTML='<tr><td colspan="8" class="px-3 py-10 text-center text-amber-500">Could not load history.</td></tr>';return;}
            HIST.total=d.total;HIST.per=d.per;
            var rows=d.rows||[];
            if(!rows.length){body.innerHTML='<tr><td colspan="8" class="px-3 py-12 text-center text-gray-400">No trades yet. Open your first paper trade in the <a href="/academy/bot" class="text-primary hover:underline">Bot Lab</a>.</td></tr>';}
            else{
                body.innerHTML=rows.map(function(r){
                    var open=r.status==='open';
                    var pnlCls=r.realized==null?'text-gray-400':((+r.realized)>=0?'text-green-600 dark:text-green-400':'text-red-500 dark:text-red-400');
                    var pnl=r.realized==null?(open?'<span class="text-[11px] text-amber-500">open</span>':'—'):(histMoney(r.realized)+(r.pnlPct!=null?' <span class="text-[10px]">('+((+r.pnlPct)>=0?'+':'')+(+r.pnlPct).toFixed(2)+'%)</span>':''));
                    return '<tr class="border-t border-gray-100 dark:border-gray-800">'
                        +'<td class="px-3 py-2"><span class="flex items-center gap-2 font-semibold">'+histCoinIcon(r.base)+r.base+'<span class="text-gray-400 text-xs font-normal">/USDT</span></span></td>'
                        +'<td class="px-3 py-2">'+histTypeBadge(r)+'</td>'
                        +'<td class="px-3 py-2 text-right tabular-nums">$'+(+r.spent).toFixed(2)+'</td>'
                        +'<td class="px-3 py-2 text-right tabular-nums">$'+histNum(r.entry)+'</td>'
                        +'<td class="px-3 py-2 text-right tabular-nums">'+(r.exit!=null?'$'+histNum(r.exit):'—')+'</td>'
                        +'<td class="px-3 py-2 text-right tabular-nums font-bold '+pnlCls+'">'+pnl+'</td>'
                        +'<td class="px-3 py-2 hidden sm:table-cell text-xs text-gray-500 dark:text-gray-400">'+histEsc(r.closed_at||'—')+'</td>'
                        +'<td class="px-3 py-2 hidden md:table-cell text-xs text-gray-500 dark:text-gray-400">'+histEsc(r.reason||'—')+'</td>'
                        +'</tr>';
                }).join('');
            }
            histSummary(rows);
            var pages=Math.max(1,Math.ceil(HIST.total/HIST.per));
            document.getElementById('hist-pageinfo').textContent='Page '+d.page+' of '+pages+' · '+HIST.total+' trades';
            document.getElementById('hist-prev').disabled=d.page<=1;
            document.getElementById('hist-next').disabled=d.page>=pages;
        }).catch(function(){body.innerHTML='<tr><td colspan="8" class="px-3 py-10 text-center text-amber-500">Network error.</td></tr>';});
}
function histSummary(rows){
    var closed=rows.filter(function(r){return r.status==='closed';});
    var wins=closed.filter(function(r){return (+r.realized)>0;}).length;
    var pnl=closed.reduce(function(a,r){return a+(+r.realized||0);},0);
    var open=rows.filter(function(r){return r.status==='open';}).length;
    document.getElementById('sum-count').textContent=closed.length;
    document.getElementById('sum-win').textContent=closed.length?Math.round(wins/closed.length*100)+'%':'—';
    var pe=document.getElementById('sum-pnl');pe.textContent=histMoney(pnl);pe.className='mt-0.5 text-xl font-extrabold tabular-nums '+(pnl>=0?'text-green-600 dark:text-green-400':'text-red-500 dark:text-red-400');
    document.getElementById('sum-open').textContent=open;
}
function histPage(delta){var pages=Math.max(1,Math.ceil(HIST.total/HIST.per));HIST.page=Math.min(pages,Math.max(1,HIST.page+delta));histLoad();}
function histSetScope(s){HIST.scope=s;HIST.page=1;document.querySelectorAll('#hist-tabs button').forEach(function(b){var on=b.dataset.scope===s;b.className=(b.dataset.scope==='all'?'px-4 py-2 ':'px-4 py-2 border-l border-gray-200 dark:border-gray-700 ')+(on?'bg-primary text-white':'text-gray-500 dark:text-gray-400');});histLoad();}
document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('#hist-tabs button').forEach(function(b){b.addEventListener('click',function(){histSetScope(b.dataset.scope);});});
    histSetScope('all');
});
</script>
</body>
</html>
