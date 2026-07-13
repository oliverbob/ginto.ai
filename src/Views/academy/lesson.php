<?php
// academy/lesson.php — branded lesson viewer.
$lesson    = $lesson ?? [];
$lessons   = $lessons ?? [];
$hasAccess = $hasAccess ?? false;
$curSlug   = $lesson['slug'] ?? '';
// Video embed (accepts a YouTube id or full url).
$video = trim((string) ($lesson['video_url'] ?? ''));
$embed = '';
if ($video !== '') {
    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/))([A-Za-z0-9_-]{6,})~', $video, $m)) $embed = 'https://www.youtube.com/embed/' . $m[1];
    elseif (preg_match('~^[A-Za-z0-9_-]{6,}$~', $video)) $embed = 'https://www.youtube.com/embed/' . $video;
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Lesson') ?></title>
    <script>
      (function(){const t=localStorage.getItem('theme');document.documentElement.classList.toggle('dark',t==='dark'||(t!=='light'&&true));})();
      function gtaToggleTheme(){const d=!document.documentElement.classList.contains('dark');document.documentElement.classList.toggle('dark',d);try{localStorage.setItem('theme',d?'dark':'light');}catch(e){}}
    </script>
    <script src="/assets/js/tailwindcss.js"></script>
    <script>tailwind.config={darkMode:'class',theme:{extend:{colors:{primary:'#6366f1'}}}};</script>
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <style>.dark{color-scheme:dark} .prose p{margin:0 0 1rem} .prose ul{margin:0 0 1rem 1.2rem;list-style:disc} .prose li{margin:.25rem 0} .prose strong{font-weight:700}</style>
</head>
<body class="bg-white dark:bg-[#0b1020] text-gray-900 dark:text-gray-100 min-h-screen">
<header class="border-b border-gray-200 dark:border-gray-800">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
        <a href="/academy/learn" class="text-sm text-gray-500 hover:text-primary"><i class="fas fa-arrow-left mr-1"></i> All lessons</a>
        <div class="flex items-center gap-3">
            <a href="/academy" class="flex items-center gap-2 font-bold text-sm"><i class="fas fa-graduation-cap text-primary"></i> Ginto Trading Academy</a>
            <button onclick="gtaToggleTheme()" title="Toggle light / dark" class="w-9 h-9 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-primary"><i class="fas fa-circle-half-stroke"></i></button>
        </div>
    </div>
</header>

<div class="max-w-6xl mx-auto px-4 py-10 grid lg:grid-cols-[1fr_260px] gap-10">
    <article>
        <div class="text-xs font-bold uppercase tracking-wide text-primary"><?= htmlspecialchars($lesson['module'] ?? '') ?></div>
        <h1 class="text-3xl font-extrabold mt-1"><?= htmlspecialchars($lesson['title'] ?? '') ?></h1>
        <?php if (!empty($lesson['summary'])): ?><p class="mt-2 text-gray-500 dark:text-gray-400"><?= htmlspecialchars($lesson['summary']) ?></p><?php endif; ?>

        <?php if ($embed !== ''): ?>
            <div class="mt-6 relative w-full" style="padding-bottom:56.25%">
                <iframe class="absolute inset-0 w-full h-full rounded-xl" src="<?= htmlspecialchars($embed) ?>" title="Lesson video" frameborder="0" allow="accelerometer; clipboard-write; encrypted-media; picture-in-picture" allowfullscreen></iframe>
            </div>
        <?php endif; ?>

        <div class="prose mt-6 text-gray-700 dark:text-gray-300 leading-relaxed">
            <?= $lesson['body'] ?? '' ?>
        </div>

        <div class="mt-10 rounded-xl border border-primary/30 bg-primary/5 p-4 text-sm">
            <i class="fas fa-robot text-primary mr-1"></i> Practice this on the live bot walkthrough — see the concept applied in real markets.
        </div>
    </article>

    <aside class="lg:sticky lg:top-6 self-start">
        <div class="text-xs font-bold uppercase text-gray-400 mb-2">Curriculum</div>
        <nav class="space-y-1">
            <?php foreach ($lessons as $l):
                $locked = empty($l['is_preview']) && !$hasAccess;
                $active = ($l['slug'] ?? '') === $curSlug;
                $href = $locked ? '/academy#pricing' : '/academy/lesson/' . urlencode($l['slug']); ?>
                <a href="<?= $href ?>" class="flex items-center gap-2 text-sm px-3 py-2 rounded-lg <?= $active ? 'bg-primary/10 text-primary font-semibold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800' ?>">
                    <i class="fas <?= $locked ? 'fa-lock text-gray-400' : ($active ? 'fa-play' : 'fa-circle text-[6px]') ?>"></i>
                    <span class="truncate"><?= htmlspecialchars($l['title']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>
</div>
</body>
</html>
