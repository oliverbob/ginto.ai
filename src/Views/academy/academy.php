<?php
// academy/academy.php — Ginto Trading Academy public landing page.
$isLoggedIn   = $isLoggedIn ?? false;
$isAdmin      = $isAdmin ?? false;
$hasAccess    = $hasAccess ?? false;
$userFullname = $userFullname ?? null;
$plans        = $plans ?? [];
$peso = fn($v) => '₱' . number_format((float) $v, ((float) $v == floor((float) $v)) ? 0 : 2);
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Ginto Trading Academy — learn crypto trading with AI tools and a real, live AI trading bot. The country's first AI-powered Crypto Trading Academy.">
    <title><?= htmlspecialchars($title ?? 'Ginto Trading Academy') ?></title>
    <script>
        (function () {
            const t = localStorage.getItem('theme');
            const d = t === 'dark' || (t !== 'light' && true);
            document.documentElement.classList.toggle('dark', d);
        })();
        function gtaToggleTheme() {
            const d = !document.documentElement.classList.contains('dark');
            document.documentElement.classList.toggle('dark', d);
            try { localStorage.setItem('theme', d ? 'dark' : 'light'); } catch (e) {}
        }
    </script>
    <script src="/assets/js/tailwindcss.js"></script>
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { colors: { primary: '#6366f1', secondary: '#8b5cf6' } } } };
    </script>
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <style>.dark{color-scheme:dark} .hero-grad{background:radial-gradient(1200px 600px at 50% -10%, rgba(99,102,241,.25), transparent 60%)}</style>
</head>
<body class="bg-white dark:bg-[#0b1020] text-gray-900 dark:text-gray-100 min-h-screen">

<!-- Nav -->
<header class="sticky top-0 z-40 backdrop-blur bg-white/80 dark:bg-[#0b1020]/80 border-b border-gray-200 dark:border-gray-800">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
        <a href="/academy" class="flex items-center gap-2 font-bold text-lg">
            <i class="fas fa-graduation-cap text-primary"></i>
            <span>Ginto <span class="text-primary">Trading Academy</span></span>
        </a>
        <nav class="hidden md:flex items-center gap-6 text-sm text-gray-600 dark:text-gray-300">
            <a href="#curriculum" class="hover:text-primary">Curriculum</a>
            <a href="#bot" class="hover:text-primary">Live Bot</a>
            <a href="#pricing" class="hover:text-primary">Membership</a>
        </nav>
        <div class="flex items-center gap-2">
            <button onclick="gtaToggleTheme()" class="w-9 h-9 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-primary"><i class="fas fa-circle-half-stroke"></i></button>
            <?php if ($hasAccess): ?>
                <a href="/academy/enter" class="text-sm font-semibold px-4 py-2 rounded-lg bg-primary text-white hover:bg-primary/90">Enter Academy</a>
            <?php elseif ($isLoggedIn): ?>
                <a href="#pricing" class="text-sm font-semibold px-4 py-2 rounded-lg bg-primary text-white hover:bg-primary/90">Subscribe</a>
            <?php else: ?>
                <a href="/login" class="hidden sm:inline text-sm text-gray-600 dark:text-gray-300 hover:text-primary px-3 py-2">Log in</a>
                <a href="#pricing" class="text-sm font-semibold px-4 py-2 rounded-lg bg-primary text-white hover:bg-primary/90">Get started</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Hero -->
<section class="hero-grad">
    <div class="max-w-6xl mx-auto px-4 pt-16 pb-14 text-center">
        <span class="inline-flex items-center gap-2 text-xs font-semibold px-3 py-1 rounded-full bg-primary/10 text-primary mb-5"><i class="fas fa-bolt"></i> The country's first AI-powered Crypto Trading Academy</span>
        <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight leading-tight">Learn to trade crypto<br class="hidden sm:block"> with a <span class="text-primary">real, live AI bot.</span></h1>
        <p class="mt-5 max-w-2xl mx-auto text-lg text-gray-600 dark:text-gray-300">Structured lessons, hands-on risk management, and a working AI trading bot you learn on — not slides. Built engineering-first and government-compliant.</p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <?php if ($hasAccess): ?>
                <a href="/academy/enter" class="px-6 py-3 rounded-xl font-semibold bg-primary text-white hover:bg-primary/90 shadow-lg shadow-primary/20"><i class="fas fa-play mr-1.5"></i> Enter the Academy</a>
            <?php else: ?>
                <a href="#pricing" class="px-6 py-3 rounded-xl font-semibold bg-primary text-white hover:bg-primary/90 shadow-lg shadow-primary/20">Start learning</a>
            <?php endif; ?>
            <a href="#bot" class="px-6 py-3 rounded-xl font-semibold border border-gray-300 dark:border-gray-700 hover:border-primary hover:text-primary">See the live bot</a>
        </div>
        <div class="mt-12 grid grid-cols-2 sm:grid-cols-4 gap-4 max-w-3xl mx-auto">
            <?php foreach ([['10M+','Target learners'],['4','Live AI strategies'],['24/7','Real market data'],['100%','Compliant setup']] as $s): ?>
                <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white/50 dark:bg-white/5 p-4">
                    <div class="text-2xl font-extrabold text-primary"><?= $s[0] ?></div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1"><?= $s[1] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Live bot showcase -->
<section id="bot" class="max-w-6xl mx-auto px-4 py-16">
    <div class="grid lg:grid-cols-2 gap-10 items-center">
        <div>
            <span class="text-xs font-semibold uppercase tracking-wide text-primary">The teaching centrepiece</span>
            <h2 class="text-3xl font-bold mt-2">Learn on the <span class="text-primary">Ginto Trading Bot</span></h2>
            <p class="mt-4 text-gray-600 dark:text-gray-300">Every concept is taught on a real, running AI bot — the same one our team uses. Watch it think out loud, place protected trades, and manage risk in live markets, then practice yourself.</p>
            <ul class="mt-6 space-y-3 text-sm">
                <?php foreach ([
                    ['fa-robot','AI that reasons out loud','See real BUY/SKIP decisions with the reasoning behind each one.'],
                    ['fa-shield-halved','Exchange-side stop-losses','Every trade is protected by a resting OCO/stop on the exchange — risk-first by design.'],
                    ['fa-layer-group','Multiple strategies','Breakout, pullback, trend and scalp templates — see which fits which market.'],
                    ['fa-chart-line','PineScript-aware','Bring a TradingView strategy; the AI reads and applies its logic.'],
                ] as $f): ?>
                    <li class="flex gap-3"><i class="fas <?= $f[0] ?> text-primary mt-1"></i><div><span class="font-semibold"><?= $f[1] ?></span> — <span class="text-gray-500 dark:text-gray-400"><?= $f[2] ?></span></div></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($isAdmin): ?>
                <a href="/gtb" class="inline-flex items-center gap-2 mt-6 text-sm font-semibold text-primary hover:underline">Open the live bot dashboard <i class="fas fa-arrow-right"></i></a>
            <?php endif; ?>
        </div>
        <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-gradient-to-br from-primary/10 to-secondary/10 p-6">
            <div class="rounded-xl bg-white dark:bg-[#0b1020] border border-gray-200 dark:border-gray-800 p-5 space-y-3 text-sm">
                <div class="flex items-center justify-between"><span class="font-bold">Bot Brain</span><span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400">LIVE</span></div>
                <div class="flex gap-2"><i class="fas fa-robot text-primary mt-0.5"></i><p class="text-gray-600 dark:text-gray-300">"ETH is pressing its 24h high on volume — a clean breakout on a deep, liquid pair. Hard stop under the level. <span class="text-green-600 dark:text-green-400 font-semibold">DECISION: BUY.</span>"</p></div>
                <div class="rounded-lg border border-gray-200 dark:border-gray-800 p-3 grid grid-cols-3 gap-2 text-center text-[11px]">
                    <div><div class="text-gray-400">entry</div><div class="font-semibold">$1,799</div></div>
                    <div><div class="text-red-500">stop</div><div class="font-semibold">$1,756</div></div>
                    <div><div class="text-green-600 dark:text-green-400">target</div><div class="font-semibold">$1,907</div></div>
                </div>
                <p class="text-[11px] text-gray-400"><i class="fas fa-shield-halved text-green-500 mr-1"></i>Stop-loss resting on the exchange — you learn risk-first.</p>
            </div>
        </div>
    </div>
</section>

<!-- Curriculum -->
<section id="curriculum" class="bg-gray-50 dark:bg-white/5 border-y border-gray-200 dark:border-gray-800">
    <div class="max-w-6xl mx-auto px-4 py-16">
        <div class="text-center max-w-2xl mx-auto">
            <h2 class="text-3xl font-bold">What you'll learn</h2>
            <p class="mt-3 text-gray-600 dark:text-gray-300">A practical path from zero to trading with discipline — theory first, then live practice on the bot.</p>
        </div>
        <div class="mt-10 grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ([
                ['fa-book-open','Foundations','Markets, exchanges, order types, and how crypto really moves.'],
                ['fa-chart-simple','Technical Analysis','Trends, breakouts, pullbacks, volume — reading a chart with intent.'],
                ['fa-shield-halved','Risk Management','Position sizing, stop-losses, and why capital preservation wins.'],
                ['fa-robot','The Ginto Bot','How the AI decides, sizes, and protects every trade — and how to steer it.'],
                ['fa-code','PineScript & Automation','Turn a strategy into rules the bot can follow; inspect it for bugs first.'],
                ['fa-flask','Live Practice','Trade alongside the bot on testnet, then graduate to your own plan.'],
            ] as $i => $m): ?>
                <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-[#0b1020] p-5">
                    <div class="flex items-center gap-3 mb-2"><span class="w-9 h-9 rounded-lg bg-primary/10 text-primary inline-flex items-center justify-center"><i class="fas <?= $m[0] ?>"></i></span><span class="text-xs font-mono text-gray-400">Module <?= $i + 1 ?></span></div>
                    <h3 class="font-semibold"><?= $m[1] ?></h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1"><?= $m[2] ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Pricing / Join -->
<?php
$errMsgs = [
    'form'     => 'Please enter your name, a valid email, and a password of at least 6 characters.',
    'exists'   => 'That email already has an account — please log in to continue.',
    'signup'   => 'We could not create your account. Please try again.',
    'checkout' => 'We could not start checkout. Please try again in a moment.',
    'email'    => 'Your account has no email on file — please contact support.',
    'plan'     => 'That plan is unavailable. Please pick one below.',
    '1'        => 'Something went wrong. Please try again.',
];
$err = isset($_GET['err']) ? ($errMsgs[$_GET['err']] ?? $errMsgs['1']) : null;
$csrf = $csrf_token ?? '';
?>
<section id="pricing" class="max-w-6xl mx-auto px-4 py-16">
    <div class="text-center max-w-2xl mx-auto">
        <h2 class="text-3xl font-bold">Membership</h2>
        <p class="mt-3 text-gray-600 dark:text-gray-300">A subscription unlocks the full academy — lessons, the live bot walkthroughs, and updates. <span class="font-semibold">No subscription, no access to the facility.</span></p>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No login required to join — create your account and pay in one step.</p>
    </div>

    <?php if ($err): ?>
        <div class="mt-6 max-w-lg mx-auto rounded-lg border border-red-300 dark:border-red-500/40 bg-red-50 dark:bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-300"><i class="fas fa-circle-exclamation mr-1"></i> <?= htmlspecialchars($err) ?></div>
    <?php endif; ?>
    <?php if ($hasAccess): ?>
        <div class="mt-6 max-w-lg mx-auto rounded-lg bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-300 px-4 py-3 text-sm text-center"><i class="fas fa-circle-check mr-1"></i> You're a member — <a href="/academy/enter" class="underline font-semibold">enter the Academy</a>.</div>
    <?php endif; ?>

    <?php if (!empty($plans)): ?>
        <div class="mt-10 grid sm:grid-cols-2 lg:grid-cols-<?= min(3, max(1, count($plans))) ?> gap-5 max-w-4xl mx-auto">
            <?php foreach ($plans as $i => $p):
                $pname = $p['name'] ?? '';
                $name  = $p['display_name'] ?? ($pname ?: 'Plan');
                $price = $p['price_monthly'] ?? 0;
                $featured = $pname === 'academy_pro'; ?>
                <div class="rounded-2xl border p-6 flex flex-col <?= $featured ? 'border-primary ring-2 ring-primary/30 bg-primary/5' : 'border-gray-200 dark:border-gray-800' ?>">
                    <?php if ($featured): ?><div class="text-[10px] font-bold uppercase text-primary mb-2">Most popular</div><?php endif; ?>
                    <h3 class="font-bold text-lg"><?= htmlspecialchars($name) ?></h3>
                    <div class="mt-2 text-3xl font-extrabold"><?= $peso($price) ?><span class="text-sm font-normal text-gray-400">/mo</span></div>
                    <?php if (!empty($p['description'])): ?><p class="mt-2 text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($p['description']) ?></p><?php endif; ?>
                    <ul class="mt-4 space-y-2 text-sm text-gray-600 dark:text-gray-300 flex-1">
                        <?php if (!empty($p['has_ai_tutor'])): ?><li><i class="fas fa-check text-green-500 mr-1.5"></i>AI tutor access</li><?php endif; ?>
                        <?php if (!empty($p['has_certificates'])): ?><li><i class="fas fa-check text-green-500 mr-1.5"></i>Certificates</li><?php endif; ?>
                        <li><i class="fas fa-check text-green-500 mr-1.5"></i>Full curriculum</li>
                        <li><i class="fas fa-check text-green-500 mr-1.5"></i>Live bot walkthroughs</li>
                    </ul>
                    <?php if ($hasAccess): ?>
                        <a href="/academy/enter" class="mt-6 block text-center px-4 py-2.5 rounded-lg font-semibold border border-gray-300 dark:border-gray-700 hover:border-primary hover:text-primary">Enter the Academy</a>
                    <?php elseif ($isLoggedIn): ?>
                        <form action="/academy/join" method="post" class="mt-6">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="plan" value="<?= htmlspecialchars($pname) ?>">
                            <button type="submit" class="w-full px-4 py-2.5 rounded-lg font-semibold <?= $featured ? 'bg-primary text-white hover:bg-primary/90' : 'border border-gray-300 dark:border-gray-700 hover:border-primary hover:text-primary' ?>">Subscribe — <?= $peso($price) ?>/mo</button>
                        </form>
                    <?php else: ?>
                        <button type="button" onclick="gtaOpenJoin('<?= htmlspecialchars($pname) ?>', '<?= htmlspecialchars($name, ENT_QUOTES) ?>', '<?= htmlspecialchars($peso($price)) ?>')" class="mt-6 w-full px-4 py-2.5 rounded-lg font-semibold <?= $featured ? 'bg-primary text-white hover:bg-primary/90' : 'border border-gray-300 dark:border-gray-700 hover:border-primary hover:text-primary' ?>">Get <?= htmlspecialchars($name) ?> — <?= $peso($price) ?>/mo</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="mt-6 text-center text-xs text-gray-400"><i class="fas fa-lock mr-1"></i> Secure checkout via PayMongo (cards / InstaPay / QR Ph). Billed monthly in PHP.
            <?php if (!$isLoggedIn): ?> Already have an account? <a href="/login?redirect=<?= urlencode('/academy#pricing') ?>" class="text-primary hover:underline">Log in</a>.<?php endif; ?>
        </p>
    <?php else: ?>
        <div class="mt-10 text-center text-gray-500 dark:text-gray-400">Plans are being set up — please check back shortly.</div>
    <?php endif; ?>

    <div class="mt-10 max-w-2xl mx-auto rounded-xl border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 p-4 text-sm text-amber-800 dark:text-amber-200">
        <i class="fas fa-circle-info mr-1"></i> Educational only. Trading crypto carries real risk of loss — the academy teaches disciplined, risk-first methods, never guaranteed returns.
    </div>
</section>

<?php if (!$isLoggedIn && !empty($plans)): ?>
<!-- Guest sign-up + checkout modal (standalone: creates the account and pays in one step) -->
<div id="join-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/60" onclick="if(event.target===this)gtaCloseJoin()">
    <div class="w-full max-w-md rounded-2xl bg-white dark:bg-[#0b1020] border border-gray-200 dark:border-gray-800 p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-1">
            <h3 class="font-bold text-lg">Join the Academy</h3>
            <button type="button" onclick="gtaCloseJoin()" class="text-gray-400 hover:text-primary"><i class="fas fa-xmark"></i></button>
        </div>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4"><span id="join-plan-label" class="font-semibold text-primary"></span> — create your account and pay in one step.</p>
        <form action="/academy/join" method="post" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="plan" id="join-plan" value="">
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Full name</label>
                <input type="text" name="name" required maxlength="100" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none" placeholder="Juan dela Cruz">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Email</label>
                <input type="email" name="email" required class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none" placeholder="you@email.com">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Password</label>
                <input type="password" name="password" required minlength="6" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none" placeholder="At least 6 characters">
            </div>
            <button type="submit" class="w-full mt-2 px-4 py-2.5 rounded-lg font-semibold bg-primary text-white hover:bg-primary/90"><i class="fas fa-lock mr-1"></i> Create account &amp; pay <span id="join-price"></span></button>
            <p class="text-center text-xs text-gray-400">Already have an account? <a href="/login?redirect=<?= urlencode('/academy#pricing') ?>" class="text-primary hover:underline">Log in</a>.</p>
        </form>
    </div>
</div>
<script>
    function gtaOpenJoin(plan, label, price) {
        document.getElementById('join-plan').value = plan;
        document.getElementById('join-plan-label').textContent = label;
        document.getElementById('join-price').textContent = price ? '· ' + price + '/mo' : '';
        const m = document.getElementById('join-modal');
        m.classList.remove('hidden'); m.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }
    function gtaCloseJoin() {
        const m = document.getElementById('join-modal');
        m.classList.add('hidden'); m.classList.remove('flex');
        document.body.style.overflow = '';
    }
    document.addEventListener('keydown', e => { if (e.key === 'Escape') gtaCloseJoin(); });
    // If we returned with an error, reopen the sign-up so the visitor can retry.
    <?php if ($err): ?>window.addEventListener('DOMContentLoaded', () => { document.getElementById('pricing')?.scrollIntoView(); });<?php endif; ?>
</script>
<?php endif; ?>

<footer class="border-t border-gray-200 dark:border-gray-800">
    <div class="max-w-6xl mx-auto px-4 py-8 text-sm text-gray-500 dark:text-gray-400 flex flex-col sm:flex-row items-center justify-between gap-3">
        <span>© <?= date('Y') ?> Ginto Services Corporation — Ginto Trading Academy</span>
        <span class="flex items-center gap-4">
            <a href="/" class="hover:text-primary">Home</a>
            <a href="/courses" class="hover:text-primary">Courses</a>
            <?php if ($isLoggedIn): ?><a href="/logout" class="hover:text-primary">Log out</a><?php else: ?><a href="/login" class="hover:text-primary">Log in</a><?php endif; ?>
        </span>
    </div>
</footer>
</body>
</html>
