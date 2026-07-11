<?php
// academy/pricing.php — Ginto Trading Academy membership (its own, not /courses/pricing).
$isLoggedIn = $isLoggedIn ?? false;
$hasAccess  = $hasAccess ?? false;
$plans      = $plans ?? [];
$peso = fn($v) => '₱' . number_format((float) $v, ((float) $v == floor((float) $v)) ? 0 : 2);
// Feature lines per plan (derived from flags + a couple of standard inclusions).
$featuresFor = function (array $p): array {
    $f = ['Full trading curriculum', 'Live AI bot walkthroughs', 'Risk-management masterclass', 'Weekly market breakdowns'];
    if (!empty($p['has_ai_tutor']))         $f[] = 'AI tutor Q&A';
    if (!empty($p['has_certificates']))     $f[] = 'Certificate of completion';
    if (!empty($p['has_priority_support'])) $f[] = 'Priority support + cohort sessions';
    if (($p['name'] ?? '') === 'academy_pro') $f[] = 'PineScript strategy reviews';
    return $f;
};
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Membership — Ginto Trading Academy') ?></title>
    <script>(function(){const t=localStorage.getItem('theme');document.documentElement.classList.toggle('dark',t==='dark'||(t!=='light'&&true));})();</script>
    <script src="/assets/js/tailwindcss.js"></script>
    <script>tailwind.config={darkMode:'class',theme:{extend:{colors:{primary:'#6366f1',secondary:'#8b5cf6'}}}};</script>
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <style>.dark{color-scheme:dark}</style>
</head>
<body class="bg-white dark:bg-[#0b1020] text-gray-900 dark:text-gray-100 min-h-screen">
<header class="border-b border-gray-200 dark:border-gray-800">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
        <a href="/academy" class="flex items-center gap-2 font-bold"><i class="fas fa-graduation-cap text-primary"></i> Ginto <span class="text-primary">Trading Academy</span></a>
        <a href="/academy" class="text-sm text-gray-500 hover:text-primary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
    </div>
</header>

<section class="max-w-5xl mx-auto px-4 py-14 text-center">
    <span class="inline-flex items-center gap-2 text-xs font-semibold px-3 py-1 rounded-full bg-primary/10 text-primary mb-4"><i class="fas fa-crown"></i> Membership</span>
    <h1 class="text-4xl font-extrabold">Choose your trading path</h1>
    <p class="mt-3 text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">Learn on a real, live AI bot. Cancel anytime. <span class="font-semibold">No membership, no access to the facility.</span></p>

    <?php if ($hasAccess): ?>
        <div class="mt-6 inline-flex items-center gap-2 text-sm px-4 py-2 rounded-lg bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400"><i class="fas fa-circle-check"></i> You're a member — <a href="/academy/enter" class="underline font-semibold">enter the Academy</a></div>
    <?php endif; ?>

    <?php if (empty($plans)): ?>
        <div class="mt-10 rounded-xl border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 p-6 text-sm text-amber-800 dark:text-amber-200 max-w-lg mx-auto">
            Plans are being set up. Please check back shortly.
        </div>
    <?php else: ?>
        <div class="mt-10 grid sm:grid-cols-2 gap-6 max-w-3xl mx-auto text-left">
            <?php foreach ($plans as $p):
                $featured = ($p['name'] ?? '') === 'academy_pro'; ?>
                <div class="rounded-2xl border p-6 flex flex-col <?= $featured ? 'border-primary ring-2 ring-primary/30 bg-primary/5' : 'border-gray-200 dark:border-gray-800' ?>">
                    <?php if ($featured): ?><div class="text-[10px] font-bold uppercase text-primary mb-1">Most popular</div><?php endif; ?>
                    <h3 class="font-bold text-xl"><?= htmlspecialchars($p['display_name'] ?? $p['name']) ?></h3>
                    <div class="mt-1 text-3xl font-extrabold"><?= $peso($p['price_monthly'] ?? 0) ?><span class="text-sm font-normal text-gray-400">/mo</span></div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($p['description'] ?? '') ?></p>
                    <ul class="mt-4 space-y-2 text-sm text-gray-600 dark:text-gray-300 flex-1">
                        <?php foreach ($featuresFor($p) as $line): ?>
                            <li><i class="fas fa-check text-green-500 mr-1.5"></i><?= htmlspecialchars($line) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($isLoggedIn): ?>
                        <a href="/academy/subscribe?plan=<?= urlencode($p['name']) ?>" class="mt-6 block text-center px-4 py-2.5 rounded-lg font-semibold <?= $featured ? 'bg-primary text-white hover:bg-primary/90' : 'border border-gray-300 dark:border-gray-700 hover:border-primary hover:text-primary' ?>">Subscribe — <?= $peso($p['price_monthly'] ?? 0) ?>/mo</a>
                    <?php else: ?>
                        <a href="/login?redirect=<?= urlencode('/academy/pricing') ?>" class="mt-6 block text-center px-4 py-2.5 rounded-lg font-semibold <?= $featured ? 'bg-primary text-white hover:bg-primary/90' : 'border border-gray-300 dark:border-gray-700 hover:border-primary hover:text-primary' ?>">Log in to subscribe</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="mt-6 text-xs text-gray-400"><i class="fas fa-lock mr-1"></i> Secure checkout via PayMongo (cards / InstaPay / QR Ph). Billed in PHP.</p>
    <?php endif; ?>

    <div class="mt-10 max-w-2xl mx-auto rounded-xl border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 p-4 text-sm text-amber-800 dark:text-amber-200">
        <i class="fas fa-circle-info mr-1"></i> Educational only. Crypto trading carries real risk of loss — the Academy teaches disciplined, risk-first methods, never guaranteed returns.
    </div>
</section>
</body>
</html>
