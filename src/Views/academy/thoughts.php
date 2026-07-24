<?php // academy/thoughts.php — full paginated history of the class demo bot's reasoning. ?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? "The Bot's Mind — Ginto Trading Academy") ?></title>
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
            <a href="/academy/history" class="text-gray-500 hover:text-primary"><i class="fas fa-clock-rotate-left mr-1"></i>My trades</a>
            <button onclick="gtaToggleTheme()" title="Toggle light / dark" class="w-9 h-9 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-primary"><i class="fas fa-circle-half-stroke"></i></button>
            <a href="/logout" class="inline-flex items-center gap-1 text-gray-500 hover:text-red-500" title="Log out"><i class="fas fa-arrow-right-from-bracket"></i><span class="hidden sm:inline">Log out</span></a>
        </div>
    </div>
</header>

<section class="max-w-4xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-extrabold flex items-center gap-2"><i class="fas fa-brain text-primary"></i> The bot's mind</h1>
    <p class="mt-2 text-gray-600 dark:text-gray-300">The full reasoning history of the shared <strong>class demo bot</strong> — every scan, decision, entry and exit it has logged. This is the teaching bot everyone studies; it is not your own wallet.</p>

    <div id="th-list" class="mt-6 space-y-2">
        <div class="py-12 text-center text-gray-400">Loading the bot's thoughts…</div>
    </div>

    <div class="mt-4 flex items-center justify-between">
        <button id="th-prev" onclick="thPage(-1)" class="text-sm px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-primary disabled:opacity-40"><i class="fas fa-chevron-left mr-1"></i>Newer</button>
        <span id="th-pageinfo" class="text-xs text-gray-400">—</span>
        <button id="th-next" onclick="thPage(1)" class="text-sm px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-primary disabled:opacity-40">Older<i class="fas fa-chevron-right ml-1"></i></button>
    </div>
</section>

<script>
var TH = { page: 1, per: 50, total: 0 };
function thEsc(s){return String(s==null?'':s).replace(/[<>&]/g,function(c){return {'<':'&lt;','>':'&gt;','&':'&amp;'}[c];});}
function thDot(phase){var p=String(phase||'').toLowerCase();if(p.indexOf('error')!==-1)return 'bg-red-500';if(p.indexOf('trade')!==-1||p.indexOf('enter')!==-1||p.indexOf('exit')!==-1)return 'bg-primary';if(p.indexOf('decision')!==-1||p.indexOf('reflect')!==-1)return 'bg-amber-400';return 'bg-gray-400';}
function thLoad(){
    var list=document.getElementById('th-list');
    list.innerHTML='<div class="py-12 text-center text-gray-400">Loading the bot\'s thoughts…</div>';
    fetch('/academy/thoughts/data?page='+TH.page,{cache:'no-store',credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(d){
            if(!d||!d.ok){list.innerHTML='<div class="py-12 text-center text-amber-500">Could not load the thought history.</div>';return;}
            TH.total=d.total;TH.per=d.per;
            var rows=d.rows||[];
            if(!rows.length){list.innerHTML='<div class="py-12 text-center text-gray-400">No thoughts logged yet.</div>';}
            else{
                list.innerHTML=rows.map(function(t){
                    return '<div class="flex gap-3 rounded-lg border border-gray-100 dark:border-gray-800 p-3">'
                        +'<span class="mt-1.5 w-1.5 h-1.5 rounded-full shrink-0 '+thDot(t.phase)+'"></span>'
                        +'<div class="min-w-0 flex-1">'
                        +'<div class="text-gray-700 dark:text-gray-300 leading-snug whitespace-pre-wrap break-words">'+thEsc(t.message)+'</div>'
                        +'<div class="mt-1 flex items-center gap-2 text-[10px] text-gray-400">'
                        +(t.phase?'<span class="uppercase tracking-wide font-bold">'+thEsc(t.phase)+'</span>':'')
                        +(t.role?'<span>· '+thEsc(t.role)+'</span>':'')
                        +'<span>· '+thEsc(t.created_at||'')+'</span></div>'
                        +'</div></div>';
                }).join('');
            }
            var pages=Math.max(1,Math.ceil(TH.total/TH.per));
            document.getElementById('th-pageinfo').textContent='Page '+d.page+' of '+pages+' · '+TH.total+' entries';
            document.getElementById('th-prev').disabled=d.page<=1;
            document.getElementById('th-next').disabled=d.page>=pages;
            window.scrollTo({top:0,behavior:'smooth'});
        }).catch(function(){list.innerHTML='<div class="py-12 text-center text-amber-500">Network error.</div>';});
}
function thPage(delta){var pages=Math.max(1,Math.ceil(TH.total/TH.per));TH.page=Math.min(pages,Math.max(1,TH.page+delta));thLoad();}
document.addEventListener('DOMContentLoaded',thLoad);
</script>
</body>
</html>
