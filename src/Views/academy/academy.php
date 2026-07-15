<?php
// academy/academy.php — Ginto Trading Academy public landing page.
$isLoggedIn   = $isLoggedIn ?? false;
$isAdmin      = $isAdmin ?? false;
$hasAccess    = $hasAccess ?? false;
$currentPlan  = $currentPlan ?? '';
$userFullname = $userFullname ?? null;
$plans        = $plans ?? [];
$referralLink = $referralLink ?? '';
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
                <?php if ($currentPlan !== 'academy_pro'): ?><a href="#pricing" class="hidden sm:inline text-sm font-semibold text-primary hover:underline px-2"><i class="fas fa-crown mr-0.5"></i>Upgrade</a><?php endif; ?>
                <a href="/academy/enter" class="text-sm font-semibold px-4 py-2 rounded-lg bg-primary text-white hover:bg-primary/90">Enter Academy</a>
                <a href="/logout" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-red-500 px-2" title="Log out"><i class="fas fa-arrow-right-from-bracket"></i><span class="hidden sm:inline">Log out</span></a>
            <?php elseif ($isLoggedIn): ?>
                <a href="#pricing" class="text-sm font-semibold px-4 py-2 rounded-lg bg-primary text-white hover:bg-primary/90">Subscribe</a>
                <a href="/logout" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-red-500 px-2" title="Log out"><i class="fas fa-arrow-right-from-bracket"></i><span class="hidden sm:inline">Log out</span></a>
            <?php else: ?>
                <a href="/login?redirect=<?= urlencode('/academy') ?>" class="hidden sm:inline text-sm text-gray-600 dark:text-gray-300 hover:text-primary px-3 py-2">Log in</a>
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
        <div class="mt-6 max-w-lg mx-auto rounded-lg bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-300 px-4 py-3 text-sm text-center"><i class="fas fa-circle-check mr-1"></i> You're a member — <a href="/academy/enter" class="underline font-semibold">enter the Academy</a><?php if ($currentPlan !== 'academy_pro'): ?> · <a href="#pricing" class="underline font-semibold">upgrade to Pro</a> for automated trading<?php endif; ?>.</div>
    <?php endif; ?>

    <?php if ($hasAccess && $referralLink !== ''): ?>
        <div class="mt-6 max-w-lg mx-auto rounded-2xl border border-primary/30 bg-primary/5 p-5">
            <div class="flex items-center gap-2 font-semibold"><i class="fas fa-user-plus text-primary"></i> Invite others to the Academy</div>
            <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-400">Share your link. Anyone who subscribes through it — Starter Trader or Trader Pro — is credited to you, just like your /register link.</p>
            <div class="mt-3 flex gap-2">
                <input id="gtaRefLink" type="text" readonly value="<?= htmlspecialchars($referralLink) ?>"
                       onclick="this.select()"
                       class="flex-1 min-w-0 px-3 py-2 rounded-lg text-sm border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#0b1020] text-gray-900 dark:text-gray-100">
                <button type="button" onclick="gtaCopyRef(this)" class="shrink-0 px-4 py-2 rounded-lg font-semibold text-sm bg-primary text-white hover:bg-primary/90"><i class="fas fa-copy mr-1"></i>Copy</button>
            </div>
        </div>
        <script>
        function gtaCopyRef(btn) {
            const input = document.getElementById('gtaRefLink');
            const done = () => { const h = btn.innerHTML; btn.innerHTML = '<i class="fas fa-check mr-1"></i>Copied'; setTimeout(() => btn.innerHTML = h, 1500); };
            if (navigator.clipboard) {
                navigator.clipboard.writeText(input.value).then(done).catch(() => { input.select(); document.execCommand('copy'); done(); });
            } else {
                input.select(); document.execCommand('copy'); done();
            }
        }
        </script>
    <?php endif; ?>

    <?php
        $currentPlan = $currentPlan ?? '';
        $curPrice = 0.0;
        if ($hasAccess && $currentPlan !== '') {
            foreach ($plans as $pp) { if (($pp['name'] ?? '') === $currentPlan) { $curPrice = (float) ($pp['price_monthly'] ?? 0); break; } }
        }
    ?>
    <?php if (!empty($plans)): ?>
        <div class="mt-10 grid sm:grid-cols-2 lg:grid-cols-<?= min(3, max(1, count($plans))) ?> gap-5 max-w-4xl mx-auto">
            <?php foreach ($plans as $i => $p):
                $pname = $p['name'] ?? '';
                $name  = $p['display_name'] ?? ($pname ?: 'Plan');
                $price = $p['price_monthly'] ?? 0;
                $featured  = $pname === 'academy_pro';
                $isCurrent = $hasAccess && $currentPlan === $pname;
                $isUpgrade = $hasAccess && !$isCurrent && (float) $price > $curPrice; ?>
                <div class="rounded-2xl border p-6 flex flex-col <?= $featured ? 'border-primary ring-2 ring-primary/30 bg-primary/5' : 'border-gray-200 dark:border-gray-800' ?>">
                    <?php if ($featured): ?><div class="text-[10px] font-bold uppercase text-primary mb-2">Most popular</div><?php endif; ?>
                    <h3 class="font-bold text-lg"><?= htmlspecialchars($name) ?></h3>
                    <div class="mt-2 text-3xl font-extrabold"><?= $peso($price) ?><span class="text-sm font-normal text-gray-400">/mo</span></div>
                    <div class="text-xs text-gray-400 mt-0.5">+12% VAT at checkout</div>
                    <?php if (!empty($p['description'])): ?><p class="mt-2 text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($p['description']) ?></p><?php endif; ?>
                    <ul class="mt-4 space-y-2 text-sm text-gray-600 dark:text-gray-300 flex-1">
                        <?php if (!empty($p['has_ai_tutor'])): ?><li><i class="fas fa-check text-green-500 mr-1.5"></i>AI tutor access</li><?php endif; ?>
                        <?php if (!empty($p['has_certificates'])): ?><li><i class="fas fa-check text-green-500 mr-1.5"></i>Certificates</li><?php endif; ?>
                        <li><i class="fas fa-check text-green-500 mr-1.5"></i>Full curriculum</li>
                        <li><i class="fas fa-check text-green-500 mr-1.5"></i>Live bot walkthroughs</li>
                    </ul>
                    <?php if ($isCurrent): ?>
                        <a href="/academy/enter" class="mt-6 block text-center px-4 py-2.5 rounded-lg font-semibold bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-300 hover:opacity-90"><i class="fas fa-circle-check mr-1"></i>Your plan — Enter</a>
                    <?php elseif ($isUpgrade): ?>
                        <button type="button" onclick="gtaBuy('<?= htmlspecialchars($pname) ?>', '<?= htmlspecialchars($name, ENT_QUOTES) ?>', '<?= htmlspecialchars($peso($price)) ?>', false)" class="mt-6 w-full px-4 py-2.5 rounded-lg font-semibold bg-primary text-white hover:bg-primary/90"><i class="fas fa-crown mr-1"></i>Upgrade to <?= htmlspecialchars($name) ?> — <?= $peso($price) ?>/mo</button>
                    <?php elseif ($hasAccess): ?>
                        <a href="/academy/enter" class="mt-6 block text-center px-4 py-2.5 rounded-lg font-semibold border border-gray-300 dark:border-gray-700 hover:border-primary hover:text-primary">Included — Enter the Academy</a>
                    <?php else: ?>
                        <button type="button" onclick="gtaBuy('<?= htmlspecialchars($pname) ?>', '<?= htmlspecialchars($name, ENT_QUOTES) ?>', '<?= htmlspecialchars($peso($price)) ?>', <?= $isLoggedIn ? 'false' : 'true' ?>)" class="mt-6 w-full px-4 py-2.5 rounded-lg font-semibold <?= $featured ? 'bg-primary text-white hover:bg-primary/90' : 'border border-gray-300 dark:border-gray-700 hover:border-primary hover:text-primary' ?>"><?= $isLoggedIn ? 'Subscribe' : 'Get ' . htmlspecialchars($name) ?> — <?= $peso($price) ?>/mo</button>
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

<?php if (!empty($plans) && $currentPlan !== 'academy_pro'): /* render for guests + non-Pro members (so upgrade works) */ ?>
<!-- On-site sign-up + QR Ph payment — the QR is generated and paid on ginto.ai (no PayMongo redirect). -->
<div id="join-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/60" onclick="if(event.target===this)gtaCloseJoin()">
    <div class="w-full max-w-md rounded-2xl bg-white dark:bg-[#0b1020] border border-gray-200 dark:border-gray-800 p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-1">
            <h3 class="font-bold text-lg">Join the Academy</h3>
            <button type="button" onclick="gtaCloseJoin()" class="text-gray-400 hover:text-primary"><i class="fas fa-xmark"></i></button>
        </div>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4"><span id="join-plan-label" class="font-semibold text-primary"></span></p>

        <div id="join-error" class="hidden mb-3 rounded-lg border border-red-300 dark:border-red-500/40 bg-red-50 dark:bg-red-500/10 px-3 py-2 text-sm text-red-700 dark:text-red-300"></div>

        <?php if (!$isLoggedIn): ?>
        <!-- Pane 1: guest account details -->
        <div id="join-form-pane" class="space-y-3">
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Full name</label>
                <input id="join-name" type="text" maxlength="100" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none" placeholder="Juan dela Cruz">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Email</label>
                <input id="join-email" type="email" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none" placeholder="you@email.com">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Password</label>
                <input id="join-password" type="password" minlength="6" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none" placeholder="At least 6 characters">
            </div>
            <button type="button" onclick="gtaChooseMethod()" class="w-full mt-1 px-4 py-2.5 rounded-lg font-semibold bg-primary text-white hover:bg-primary/90">Continue to payment</button>
            <p class="text-center text-xs text-gray-400">Already have an account? <a href="/login?redirect=<?= urlencode('/academy#pricing') ?>" class="text-primary hover:underline">Log in</a>.</p>
        </div>
        <?php endif; ?>

        <!-- Pane 2: choose a payment method -->
        <div id="join-method-pane" class="hidden space-y-3">
            <div id="join-total-line" class="rounded-lg bg-gray-50 dark:bg-white/5 px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                <div class="flex justify-between"><span>Subtotal</span><span id="jm-sub">—</span></div>
                <div class="flex justify-between"><span>VAT (12%)</span><span id="jm-vat">—</span></div>
                <div class="flex justify-between font-bold text-base text-gray-900 dark:text-gray-100 mt-1 pt-1 border-t border-gray-200 dark:border-gray-700"><span>Total due</span><span id="jm-total">—</span></div>
            </div>
            <button type="button" onclick="gtaPickQr()" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-700 hover:border-primary hover:text-primary text-left"><i class="fas fa-qrcode text-primary text-lg"></i><span><span class="font-semibold block">QR Ph</span><span class="text-xs text-gray-400">GCash, Maya, GoTyme, BPI &amp; banks</span></span></button>
            <button type="button" onclick="gtaPickCard()" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-700 hover:border-primary hover:text-primary text-left"><i class="fas fa-credit-card text-primary text-lg"></i><span><span class="font-semibold block">Credit / Debit card</span><span class="text-xs text-gray-400">Visa, Mastercard — entered on ginto.ai</span></span></button>
        </div>

        <!-- Pane 3a: on-site QR Ph payment -->
        <div id="join-pay-pane" class="hidden text-center">
            <div id="join-pay-loading" class="py-8 text-sm text-gray-500"><i class="fas fa-spinner fa-spin mr-1"></i> Generating your QR…</div>
            <div id="join-qr-wrap" class="hidden">
                <p class="text-sm text-gray-500 dark:text-gray-400">Scan with GCash, Maya, GoTyme, BPI or any QR Ph app.</p>
                <div class="mt-3 inline-block rounded-xl bg-white p-3 border border-gray-200"><img id="join-qr" alt="QR Ph code" class="w-56 h-56 object-contain"></div>
                <div class="mt-3 mx-auto max-w-[220px] text-sm text-gray-500 dark:text-gray-400 text-left">
                    <div class="flex justify-between"><span>Subtotal</span><span id="join-sub">—</span></div>
                    <div class="flex justify-between"><span>VAT (12%)</span><span id="join-vat">—</span></div>
                    <div class="flex justify-between font-bold text-base text-gray-900 dark:text-gray-100 mt-1 pt-1 border-t border-gray-200 dark:border-gray-700"><span>Total</span><span id="join-pay-amount">—</span></div>
                </div>
                <div class="mt-2 text-sm" id="join-pay-status"><i class="fas fa-circle-notch fa-spin mr-1 text-primary"></i> Waiting for your payment…</div>
                <a id="join-qr-dl" download="ginto-academy-qrph.png" class="mt-3 inline-block text-xs text-primary hover:underline"><i class="fas fa-download mr-1"></i> Download QR</a>
            </div>
        </div>

        <!-- Pane 3b: on-site card payment -->
        <div id="join-card-pane" class="hidden space-y-3">
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400">Name on card</label>
                    <button type="button" onclick="gtaUseMyName()" class="text-[11px] text-primary hover:underline"><i class="fas fa-user mr-0.5"></i>Use my registered name</button>
                </div>
                <input id="card-name" type="text" autocomplete="cc-name" maxlength="100" value="<?= htmlspecialchars($userFullname ?? '') ?>" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none" placeholder="Juan D. Cruz">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Card number</label>
                <input id="card-number" type="text" inputmode="numeric" autocomplete="cc-number" maxlength="23" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none" placeholder="1234 5678 9012 3456">
            </div>
            <div class="grid grid-cols-3 gap-2">
                <div><label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">MM</label><input id="card-mm" type="text" inputmode="numeric" maxlength="2" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none" placeholder="09"></div>
                <div><label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">YY</label><input id="card-yy" type="text" inputmode="numeric" maxlength="2" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none" placeholder="28"></div>
                <div><label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">CVC</label><input id="card-cvc" type="text" inputmode="numeric" autocomplete="cc-csc" maxlength="4" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none" placeholder="123"></div>
            </div>
            <label class="flex items-start gap-2 text-xs text-gray-500 dark:text-gray-400 cursor-pointer">
                <input id="card-autorenew" type="checkbox" class="mt-0.5" style="accent-color:#6366f1;">
                <span>Save my card and remind me to renew each month (assisted auto-renew). We'll email a 1-click renewal near expiry — you confirm the CVC to pay.</span>
            </label>
            <button type="button" id="card-pay-btn" onclick="gtaPayCard()" class="w-full px-4 py-2.5 rounded-lg font-semibold bg-primary text-white hover:bg-primary/90">Pay <span id="card-pay-amount"></span></button>
            <div class="text-sm text-center" id="card-pay-status"></div>
            <p class="text-center text-[11px] text-gray-400"><i class="fas fa-lock mr-1"></i> Card details are sent directly to PayMongo over TLS. Your bank may ask for OTP/3DS.</p>
        </div>

        <p class="mt-4 text-center text-[11px] text-gray-400"><i class="fas fa-shield-halved mr-1"></i> Paid securely on ginto.ai. QR Ph &amp; card, incl. 12% VAT.</p>
    </div>
</div>

<!-- 3DS / OTP verification overlay (card) -->
<div id="join-3ds" class="hidden fixed inset-0 z-[60] bg-black/70 items-center justify-center p-4">
    <div class="w-full max-w-md h-[80vh] rounded-2xl bg-white overflow-hidden flex flex-col shadow-2xl">
        <div class="flex items-center justify-between px-4 py-2 border-b border-gray-200">
            <span class="text-sm font-semibold text-gray-700">Verify your card</span>
            <button type="button" onclick="gtaClose3ds()" class="text-gray-400 hover:text-primary"><i class="fas fa-xmark"></i></button>
        </div>
        <iframe id="join-3ds-frame" class="flex-1 w-full border-0" src="about:blank" title="Card verification"></iframe>
        <div id="join-3ds-fallback" class="hidden px-4 py-2 text-center text-xs text-gray-500 border-t border-gray-200">If verification doesn't load, <a id="join-3ds-newtab" target="_blank" rel="noopener" class="text-primary underline">open it in a new tab</a>. This window updates once you're done.</div>
    </div>
</div>
<script>
(function () {
    var CSRF = <?= json_encode($csrf) ?>;
    var plan = null, piId = null, pollTimer = null, isGuest = <?= $isLoggedIn ? 'false' : 'true' ?>;
    var curBase = 0, curVat = 0, curTotal = 0;
    function el(id) { return document.getElementById(id); }
    function peso(n) { return '₱' + Number(n).toLocaleString(); }
    function showErr(m) { var e = el('join-error'); if (!e) return; e.textContent = m; e.classList.remove('hidden'); }
    function clearErr() { var e = el('join-error'); if (!e) return; e.textContent = ''; e.classList.add('hidden'); }
    function hide(id) { if (el(id)) el(id).classList.add('hidden'); }
    function show(id) { if (el(id)) el(id).classList.remove('hidden'); }
    function guestValid() {
        if (!(isGuest && el('join-form-pane'))) return true;
        var n = el('join-name').value.trim(), e = el('join-email').value.trim(), p = el('join-password').value;
        return n && /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(e) && p.length >= 6;
    }
    function appendGuest(fd) {
        if (isGuest && el('join-form-pane')) {
            fd.append('name', el('join-name').value.trim());
            fd.append('email', el('join-email').value.trim());
            fd.append('password', el('join-password').value);
        }
    }

    window.gtaBuy = function (planName, label, price, guest) {
        plan = planName; isGuest = !!guest; clearErr();
        curBase = Number(String(price).replace(/[^\d.]/g, '')) || 0;
        curVat = Math.round(curBase * 0.12); curTotal = curBase + curVat;
        el('join-plan-label').textContent = label + ' — ' + price + '/mo';
        // reset all panes
        hide('join-method-pane'); hide('join-pay-pane'); hide('join-qr-wrap'); hide('join-card-pane');
        show('join-pay-loading');
        // seed totals
        if (el('jm-sub')) el('jm-sub').textContent = peso(curBase);
        if (el('jm-vat')) el('jm-vat').textContent = peso(curVat);
        if (el('jm-total')) el('jm-total').textContent = peso(curTotal);
        if (el('card-pay-amount')) el('card-pay-amount').textContent = peso(curTotal);
        el('join-modal').classList.remove('hidden'); el('join-modal').classList.add('flex');
        document.body.style.overflow = 'hidden';
        if (isGuest && el('join-form-pane')) { show('join-form-pane'); }
        else { gtaChooseMethod(); }  // logged-in: straight to the method chooser
    };
    window.gtaChooseMethod = function () {
        clearErr();
        if (!guestValid()) { showErr('Enter your name, a valid email, and a 6+ character password.'); return; }
        hide('join-form-pane'); show('join-method-pane');
    };
    var GTA_MYNAME = <?= json_encode($userFullname ?? '') ?>;
    window.gtaUseMyName = function () { var n = GTA_MYNAME || (el('join-name') && el('join-name').value) || ''; if (el('card-name')) { el('card-name').value = n; el('card-name').focus(); } };
    window.gtaPickQr = function () { hide('join-method-pane'); gtaStartPay(); };
    window.gtaPickCard = function () { clearErr(); hide('join-method-pane'); show('join-card-pane'); if (el('card-name') && !el('card-name').value.trim()) { el('card-name').value = GTA_MYNAME || (el('join-name') && el('join-name').value) || ''; } };
    window.gtaCloseJoin = function () {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
        gtaClose3ds();
        el('join-modal').classList.add('hidden'); el('join-modal').classList.remove('flex');
        document.body.style.overflow = '';
    };

    // ---- QR Ph ----
    window.gtaStartPay = function () {
        clearErr();
        var fd = new FormData(); fd.append('csrf_token', CSRF); fd.append('plan', plan); appendGuest(fd);
        show('join-pay-pane'); hide('join-qr-wrap'); show('join-pay-loading');
        fetch('/academy/qrph/init', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) { hide('join-pay-pane'); show('join-method-pane'); showErr(d.message || 'Could not start the payment.'); return; }
                piId = d.pi_id;
                if (d.qr_image) { el('join-qr').src = d.qr_image; el('join-qr-dl').href = d.qr_image; }
                el('join-pay-amount').textContent = peso(d.amount);
                if (el('join-sub')) el('join-sub').textContent = peso(d.base);
                if (el('join-vat')) el('join-vat').textContent = peso(d.vat);
                hide('join-pay-loading'); show('join-qr-wrap'); startPoll();
            })
            .catch(function () { hide('join-pay-pane'); show('join-method-pane'); showErr('Network error — please try again.'); });
    };

    // ---- Card ----
    window.gtaPayCard = function () {
        clearErr();
        var digits = (el('card-number').value || '').replace(/\D/g, '');
        var mm = (el('card-mm').value || '').replace(/\D/g, ''), yy = (el('card-yy').value || '').replace(/\D/g, ''), cvc = (el('card-cvc').value || '').replace(/\D/g, '');
        if (digits.length < 13 || digits.length > 19) { showErr('Please enter a valid card number.'); return; }
        if (+mm < 1 || +mm > 12 || yy.length < 2 || cvc.length < 3) { showErr('Please check the card expiry and CVC.'); return; }
        var cardName = (el('card-name') && el('card-name').value || '').trim();
        if (cardName.length < 2) { showErr('Please enter the name on the card.'); return; }
        var fd = new FormData();
        fd.append('csrf_token', CSRF); fd.append('plan', plan);
        fd.append('card_name', cardName);
        fd.append('card_number', digits); fd.append('exp_month', mm); fd.append('exp_year', yy); fd.append('cvc', cvc);
        if (el('card-autorenew') && el('card-autorenew').checked) fd.append('auto_renew', '1');
        appendGuest(fd);
        var btn = el('card-pay-btn'); if (btn) { btn.disabled = true; btn.textContent = 'Processing…'; }
        el('card-pay-status').textContent = '';
        fetch('/academy/card/init', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (btn) { btn.disabled = false; btn.innerHTML = 'Pay ' + peso(curTotal); }
                if (!d.success) { showErr(d.message || 'The card could not be processed.'); return; }
                piId = d.pi_id;
                if (d.requires_action && d.next_action_url) { el('card-pay-status').innerHTML = '<i class="fas fa-shield-halved mr-1 text-primary"></i> Complete the bank verification…'; gtaOpen3ds(d.next_action_url); startPoll(); }
                else if (d.status === 'succeeded') { finalize(); }
                else if (d.status === 'awaiting_next_action') { showErr('We couldn\'t complete card verification (3DS) — this is a limitation on our side that we\'re fixing, not a problem with your card. Please pay with QR Ph (GCash / Maya / bank) for now — tap the QR Ph option above; it works instantly.'); }
                else { el('card-pay-status').innerHTML = '<i class="fas fa-circle-notch fa-spin mr-1 text-primary"></i> Confirming your payment…'; startPoll(); }
            })
            .catch(function () { if (btn) { btn.disabled = false; btn.innerHTML = 'Pay ' + peso(curTotal); } showErr('Network error — please try again.'); });
    };
    window.gtaOpen3ds = function (url) {
        el('join-3ds-frame').src = url;
        if (el('join-3ds-newtab')) el('join-3ds-newtab').href = url;
        hide('join-3ds-fallback');
        el('join-3ds').classList.remove('hidden'); el('join-3ds').classList.add('flex');
        setTimeout(function () { if (!el('join-3ds').classList.contains('hidden')) show('join-3ds-fallback'); }, 4500);
    };
    window.gtaClose3ds = function () {
        var o = el('join-3ds'); if (!o) return;
        o.classList.add('hidden'); o.classList.remove('flex');
        if (el('join-3ds-frame')) el('join-3ds-frame').src = 'about:blank';
    };

    // ---- shared poll + finalize (QR or card) ----
    function startPoll() {
        if (pollTimer) clearInterval(pollTimer);
        var tries = 0;
        pollTimer = setInterval(function () {
            if (++tries > 40) {   // ~2 min — give up rather than spin forever
                clearInterval(pollTimer); pollTimer = null;
                showErr('We couldn\'t confirm the payment in time. If you weren\'t charged, please try again or use another card.');
                return;
            }
            fetch('/api/payments/paymongo-qrph-status?pi_id=' + encodeURIComponent(piId), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) { if (d.paid) { clearInterval(pollTimer); pollTimer = null; finalize(); } })
                .catch(function () {});
        }, 3000);
    }
    function finalize() {
        gtaClose3ds();
        if (el('join-pay-status')) el('join-pay-status').innerHTML = '<i class="fas fa-circle-check mr-1 text-green-500"></i> Payment received — activating your membership…';
        if (el('card-pay-status')) el('card-pay-status').innerHTML = '<i class="fas fa-circle-check mr-1 text-green-500"></i> Payment received — activating…';
        var fd = new FormData(); fd.append('csrf_token', CSRF); fd.append('pi_id', piId);
        fetch('/academy/qrph/finalize', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) { window.location.href = d.redirect || '/academy/learn'; }
                else { showErr(d.message || 'Payment received but activation failed — please contact support.'); }
            })
            .catch(function () { showErr('Payment received but activation failed — please contact support.'); });
    }
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { if (!el('join-3ds').classList.contains('hidden')) gtaClose3ds(); else gtaCloseJoin(); } });
    <?php if ($err): ?>window.addEventListener('DOMContentLoaded', function () { var s = document.getElementById('pricing'); if (s) s.scrollIntoView(); });<?php endif; ?>
})();
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
