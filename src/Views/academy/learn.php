<?php
// academy/learn.php — branded Academy lessons facility.
$hasAccess  = $hasAccess ?? false;
$isLoggedIn = $isLoggedIn ?? false;
$lessons    = $lessons ?? [];
// Group by module, preserving order.
$byModule = [];
foreach ($lessons as $l) { $byModule[$l['module'] ?? 'Lessons'][] = $l; }
$tierBadge = ['free' => ['Free', 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400'], 'trader' => ['Trader', 'bg-primary/10 text-primary'], 'pro' => ['Pro', 'bg-purple-100 text-purple-700 dark:bg-purple-500/20 dark:text-purple-400']];
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Learn — Ginto Trading Academy') ?></title>
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
        <a href="/academy" class="flex items-center gap-2 font-bold"><i class="fas fa-graduation-cap text-primary"></i> Ginto <span class="text-primary">Trading Academy</span></a>
        <div class="flex items-center gap-3">
        <?php if ($hasAccess): ?><a href="/academy/bot" class="text-sm text-gray-500 hover:text-primary"><i class="fas fa-flask mr-1"></i>Bot Lab</a><?php endif; ?>
        <button onclick="gtaToggleTheme()" title="Toggle light / dark" class="w-9 h-9 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-primary"><i class="fas fa-circle-half-stroke"></i></button>
        <?php if ($hasAccess): ?><span class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400">Member</span>
        <?php endif; ?>
        <?php if ($isLoggedIn): ?><a href="/logout" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-red-500" title="Log out"><i class="fas fa-arrow-right-from-bracket"></i><span class="hidden sm:inline">Log out</span></a>
        <?php else: ?><a href="/login?redirect=<?= urlencode('/academy/learn') ?>" class="text-sm text-gray-500 hover:text-primary">Log in</a><a href="/academy#pricing" class="text-sm font-semibold px-4 py-2 rounded-lg bg-primary text-white hover:bg-primary/90">Become a member</a><?php endif; ?>
        </div>
    </div>
</header>

<section class="max-w-5xl mx-auto px-4 py-12">
    <h1 class="text-3xl font-extrabold">Your trading curriculum</h1>
    <p class="mt-2 text-gray-600 dark:text-gray-300">Work through the modules in order. <?= $hasAccess ? 'You have full access.' : 'Preview lessons are open — unlock the rest with a membership.' ?></p>
    <?php if ($hasAccess): ?>
    <a href="/academy/bot" class="mt-6 block rounded-2xl overflow-hidden border border-primary/30 bg-gradient-to-br from-primary/10 to-secondary/10 hover:from-primary/20 hover:to-secondary/20 transition-colors">
        <div class="p-5 flex items-center gap-4">
            <span class="w-12 h-12 rounded-xl bg-primary/15 text-primary inline-flex items-center justify-center text-xl"><i class="fas fa-flask"></i></span>
            <div class="flex-1 min-w-0">
                <div class="text-[10px] font-bold uppercase tracking-wide text-primary">Live · testnet</div>
                <div class="font-bold text-lg">Live Bot Lab</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Watch the Ginto Trading Bot trade in real time on paper money — every entry, stop, target, and the AI's reasoning.</div>
            </div>
            <span class="shrink-0 text-primary font-semibold text-sm">Open <i class="fas fa-arrow-right ml-1"></i></span>
        </div>
    </a>
    <?php endif; ?>

    <?php if (empty($lessons)): ?>
        <div class="mt-8 text-gray-400">Lessons are being added — check back soon.</div>
    <?php else: foreach ($byModule as $module => $items): ?>
        <div class="mt-10">
            <h2 class="text-sm font-bold uppercase tracking-wide text-primary mb-3"><?= htmlspecialchars($module) ?></h2>
            <div class="space-y-2">
                <?php foreach ($items as $l):
                    $locked = empty($l['is_preview']) && !$hasAccess;
                    $tb = $tierBadge[$l['tier'] ?? 'trader'] ?? $tierBadge['trader'];
                    $href = $locked ? '/academy#pricing' : '/academy/lesson/' . urlencode($l['slug']); ?>
                    <a href="<?= $href ?>" class="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-800 hover:border-primary p-4 transition-colors <?= $locked ? 'opacity-80' : '' ?>">
                        <span class="w-9 h-9 rounded-lg inline-flex items-center justify-center <?= $locked ? 'bg-gray-100 dark:bg-gray-800 text-gray-400' : 'bg-primary/10 text-primary' ?>">
                            <i class="fas <?= $locked ? 'fa-lock' : 'fa-play' ?>"></i>
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="font-semibold block truncate"><?= htmlspecialchars($l['title']) ?></span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 block truncate"><?= htmlspecialchars($l['summary'] ?? '') ?></span>
                        </span>
                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?= $tb[1] ?>"><?= $tb[0] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; endif; ?>
</section>
</body>
</html>
