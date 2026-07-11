<?php
// academy/admin.php — admin editor for Academy plan prices + lessons.
$plans      = $plans ?? [];
$lessons    = $lessons ?? [];
$editLesson = $editLesson ?? null;
$csrf       = $csrf_token ?? '';
$planBy = [];
foreach ($plans as $p) { $planBy[$p['name']] = $p; }
$e = fn($k, $d = '') => htmlspecialchars((string) ($editLesson[$k] ?? $d), ENT_QUOTES);
$ck = fn($k) => !empty($editLesson[$k]) ? 'checked' : '';
$sel = fn($v) => (($editLesson['tier'] ?? 'trader') === $v) ? 'selected' : '';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Academy Admin') ?></title>
    <script>(function(){const t=localStorage.getItem('theme');document.documentElement.classList.toggle('dark',t==='dark'||(t!=='light'&&true));})();</script>
    <script src="/assets/js/tailwindcss.js"></script>
    <script>tailwind.config={darkMode:'class',theme:{extend:{colors:{primary:'#6366f1'}}}};</script>
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css"><style>.dark{color-scheme:dark}</style>
</head>
<body class="bg-white dark:bg-[#0b1020] text-gray-900 dark:text-gray-100 min-h-screen">
<header class="border-b border-gray-200 dark:border-gray-800">
    <div class="max-w-5xl mx-auto px-4 h-16 flex items-center justify-between">
        <span class="font-bold"><i class="fas fa-sliders text-primary mr-1.5"></i>Academy Admin</span>
        <a href="/academy" class="text-sm text-gray-500 hover:text-primary">View Academy →</a>
    </div>
</header>
<div class="max-w-5xl mx-auto px-4 py-10 space-y-10">
    <?php if (isset($_GET['saved'])): ?><div class="rounded-lg bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400 px-4 py-2 text-sm"><i class="fas fa-check mr-1"></i> Saved.</div><?php endif; ?>

    <!-- Plans -->
    <section class="rounded-2xl border border-gray-200 dark:border-gray-800 p-6">
        <h2 class="font-bold text-lg mb-4"><i class="fas fa-tags text-primary mr-1.5"></i>Membership prices</h2>
        <form method="POST" action="/academy/admin/save" class="grid sm:grid-cols-2 gap-6">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
            <input type="hidden" name="action" value="plans">
            <?php foreach (['academy_trader' => 'Trader', 'academy_pro' => 'Pro Trader'] as $key => $label): $p = $planBy[$key] ?? []; ?>
                <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4 space-y-2">
                    <div class="text-xs font-bold uppercase text-gray-400"><?= $label ?> · <?= $key ?></div>
                    <label class="block text-xs text-gray-500">Display name</label>
                    <input name="display_<?= $key ?>" value="<?= htmlspecialchars((string)($p['display_name'] ?? ''), ENT_QUOTES) ?>" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm px-2 py-1.5">
                    <label class="block text-xs text-gray-500">Price (₱ / month)</label>
                    <input name="price_<?= $key ?>" type="number" step="1" min="0" value="<?= htmlspecialchars((string)($p['price_monthly'] ?? ''), ENT_QUOTES) ?>" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm px-2 py-1.5">
                    <label class="block text-xs text-gray-500">Description</label>
                    <textarea name="desc_<?= $key ?>" rows="2" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm px-2 py-1.5"><?= htmlspecialchars((string)($p['description'] ?? '')) ?></textarea>
                </div>
            <?php endforeach; ?>
            <div class="sm:col-span-2"><button class="px-5 py-2.5 rounded-lg font-semibold bg-primary text-white hover:bg-primary/90">Save prices</button></div>
        </form>
    </section>

    <!-- Lesson editor -->
    <section class="rounded-2xl border border-gray-200 dark:border-gray-800 p-6">
        <h2 class="font-bold text-lg mb-4"><i class="fas fa-<?= $editLesson ? 'pen' : 'plus' ?> text-primary mr-1.5"></i><?= $editLesson ? 'Edit lesson' : 'New lesson' ?></h2>
        <form method="POST" action="/academy/admin/save" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
            <input type="hidden" name="action" value="lesson">
            <input type="hidden" name="id" value="<?= (int)($editLesson['id'] ?? 0) ?>">
            <div class="grid sm:grid-cols-2 gap-3">
                <div><label class="block text-xs text-gray-500 mb-1">Module</label><input name="module" value="<?= $e('module','Foundations') ?>" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm px-2 py-1.5"></div>
                <div><label class="block text-xs text-gray-500 mb-1">Title</label><input name="ltitle" value="<?= $e('title') ?>" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm px-2 py-1.5"></div>
            </div>
            <div><label class="block text-xs text-gray-500 mb-1">Summary</label><input name="summary" value="<?= $e('summary') ?>" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm px-2 py-1.5"></div>
            <div><label class="block text-xs text-gray-500 mb-1">Body (HTML allowed)</label><textarea name="body" rows="8" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 font-mono text-xs px-2 py-2"><?= htmlspecialchars((string)($editLesson['body'] ?? '')) ?></textarea></div>
            <div class="grid sm:grid-cols-4 gap-3 items-end">
                <div><label class="block text-xs text-gray-500 mb-1">Video (YouTube id/url)</label><input name="video_url" value="<?= $e('video_url') ?>" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm px-2 py-1.5"></div>
                <div><label class="block text-xs text-gray-500 mb-1">Tier</label><select name="tier" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm px-2 py-1.5"><option value="free" <?= $sel('free') ?>>Free (preview)</option><option value="trader" <?= $sel('trader') ?>>Trader</option><option value="pro" <?= $sel('pro') ?>>Pro</option></select></div>
                <div><label class="block text-xs text-gray-500 mb-1">Sort order</label><input name="sort_order" type="number" value="<?= $e('sort_order','0') ?>" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm px-2 py-1.5"></div>
                <div class="flex items-center gap-4 text-sm">
                    <label class="inline-flex items-center gap-1.5"><input type="checkbox" name="is_preview" value="1" <?= $ck('is_preview') ?>> Preview</label>
                    <label class="inline-flex items-center gap-1.5"><input type="checkbox" name="is_published" value="1" <?= $editLesson ? $ck('is_published') : 'checked' ?>> Published</label>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button class="px-5 py-2.5 rounded-lg font-semibold bg-primary text-white hover:bg-primary/90"><?= $editLesson ? 'Update lesson' : 'Create lesson' ?></button>
                <?php if ($editLesson): ?><a href="/academy/admin" class="text-sm text-gray-500 hover:text-primary">Cancel / new</a><?php endif; ?>
            </div>
        </form>
    </section>

    <!-- Lessons list -->
    <section class="rounded-2xl border border-gray-200 dark:border-gray-800 p-6">
        <h2 class="font-bold text-lg mb-4"><i class="fas fa-list text-primary mr-1.5"></i>Lessons (<?= count($lessons) ?>)</h2>
        <div class="space-y-1">
            <?php foreach ($lessons as $l): ?>
                <div class="flex items-center gap-3 text-sm border-b border-gray-100 dark:border-gray-800 py-2">
                    <span class="w-8 text-gray-400 font-mono text-xs"><?= (int)$l['sort_order'] ?></span>
                    <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-500"><?= htmlspecialchars($l['tier']) ?></span>
                    <span class="flex-1 truncate <?= empty($l['is_published']) ? 'text-gray-400 line-through' : '' ?>"><?= htmlspecialchars($l['title']) ?> <span class="text-gray-400">· <?= htmlspecialchars($l['module']) ?></span></span>
                    <a href="/academy/admin?edit=<?= (int)$l['id'] ?>" class="text-primary hover:underline">Edit</a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
</body>
</html>
