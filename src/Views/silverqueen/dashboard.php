<?php
// silverqueen/dashboard.php — the SilverQueen resource-allocation console.
// Mobile-first: one column on phones, two from md, three from xl. Every number on
// this page is settled server-side; the ticking figures are clearly labelled as
// projections toward the next 24h boundary.
$snapshot   = $snapshot   ?? [];
$products   = $products   ?? [];
$admin      = $admin      ?? null;
$isElevated = $isElevated ?? false;
$inviteBase = $inviteBase ?? '';
$csrf       = $csrf_token ?? '';

$wallet     = $snapshot['wallet'] ?? [];
$owned      = $snapshot['owned_cards'] ?? [];
$qualified  = !empty($snapshot['qualified']);
$allocs     = $snapshot['allocations'] ?? [];
$downline   = $snapshot['downline'] ?? [1 => [], 2 => []];
$commission = $snapshot['commissions'] ?? ['total' => 0, 'levels' => [1 => 0, 2 => 0], 'count' => 0];
$referral   = $snapshot['referral'] ?? [];
$txns       = $snapshot['transactions'] ?? [];
$ratePct    = rtrim(rtrim(number_format(((float) ($snapshot['daily_rate'] ?? 0.005)) * 100, 3), '0'), '.');

$cardLabels = ['card_virtual' => 'Virtual', 'card_physical' => 'Physical', 'card_nft' => 'NFT Tracker'];
$money = static fn($n, $d = 2) => number_format((float) $n, $d);
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title><?= htmlspecialchars($title ?? 'SilverQueen') ?></title>
<script>
  (function(){const t=localStorage.getItem('theme');document.documentElement.classList.toggle('dark',t==='dark'||(t!=='light'&&true));})();
  function sqToggleTheme(){const d=!document.documentElement.classList.contains('dark');document.documentElement.classList.toggle('dark',d);try{localStorage.setItem('theme',d?'dark':'light');}catch(e){}}
</script>
<script src="/assets/js/tailwindcss.js"></script>
<script>tailwind.config={darkMode:'class',theme:{extend:{colors:{primary:'#6366f1',secondary:'#8b5cf6',silver:'#94a3b8'}}}};</script>
<link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
<style>
  .dark{color-scheme:dark}
  .sq-num{font-variant-numeric:tabular-nums;letter-spacing:-.02em}
  /* Thin meter, rounded ends, anchored at the track's start. */
  .sq-meter{height:6px;border-radius:999px;overflow:hidden}
  .sq-meter>span{display:block;height:100%;border-radius:999px}
  /* Tree connectors, drawn with borders so they survive text scaling. */
  .sq-branch{position:relative;padding-left:1.25rem}
  .sq-branch::before{content:'';position:absolute;left:.4rem;top:0;bottom:50%;width:1px;background:currentColor;opacity:.25}
  .sq-branch::after{content:'';position:absolute;left:.4rem;top:50%;width:.7rem;height:1px;background:currentColor;opacity:.25}
  .sq-branch:last-child::before{bottom:50%}
  @media (prefers-reduced-motion:no-preference){.sq-pulse{animation:sqp 2.4s ease-in-out infinite}}
  @keyframes sqp{0%,100%{opacity:1}50%{opacity:.45}}
</style>
</head>
<body class="bg-gray-50 dark:bg-[#0b1020] text-gray-900 dark:text-gray-100 min-h-screen antialiased">

<header class="sticky top-0 z-30 border-b border-gray-200 dark:border-gray-800 bg-white/90 dark:bg-[#0b1020]/90 backdrop-blur">
  <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between gap-3">
    <div class="flex items-center gap-2 min-w-0">
      <span class="w-9 h-9 shrink-0 rounded-xl bg-gradient-to-br from-slate-300 to-slate-500 dark:from-slate-400 dark:to-slate-700 text-white inline-flex items-center justify-center">
        <i class="fas fa-chess-queen"></i>
      </span>
      <div class="min-w-0">
        <div class="font-extrabold leading-tight truncate">Silver<span class="text-primary">Queen</span></div>
        <div class="text-[10px] uppercase tracking-wider text-gray-400 hidden sm:block">Resource allocation console</div>
      </div>
    </div>
    <div class="flex items-center gap-2 sm:gap-3">
      <div class="text-right leading-tight">
        <div class="text-[10px] uppercase tracking-wider text-gray-400">Wallet</div>
        <div class="font-bold sq-num text-sm sm:text-base" id="sqWalletChip">$<?= $money($wallet['balance'] ?? 0) ?></div>
      </div>
      <?php if ($isElevated): ?>
        <span class="hidden sm:inline text-[10px] font-bold px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">
          <i class="fas fa-shield-halved mr-1"></i>Elevated
        </span>
      <?php endif; ?>
      <button onclick="sqToggleTheme()" title="Toggle light / dark"
              class="w-9 h-9 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-primary">
        <i class="fas fa-circle-half-stroke"></i>
      </button>
      <a href="/logout" title="Log out" class="w-9 h-9 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-red-500 inline-flex items-center justify-center">
        <i class="fas fa-arrow-right-from-bracket"></i>
      </a>
    </div>
  </div>
</header>

<main class="max-w-7xl mx-auto px-4 py-6 space-y-6">

  <!-- ── Headline tiles ──────────────────────────────────────────────────── -->
  <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
    <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-[#111834] p-4">
      <div class="text-[11px] uppercase tracking-wider text-gray-400">Allocated principal</div>
      <div class="mt-1 text-2xl sm:text-3xl font-extrabold sq-num" id="sqPrincipal">$<?= $money($snapshot['principal'] ?? 0) ?></div>
      <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        <span id="sqUnits"><?= (int) ($snapshot['units'] ?? 0) ?></span> SQB unit<?= ((int) ($snapshot['units'] ?? 0)) === 1 ? '' : 's' ?>
        · <span id="sqActive"><?= (int) ($snapshot['active_count'] ?? 0) ?></span> active
      </div>
    </div>
    <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-[#111834] p-4">
      <div class="text-[11px] uppercase tracking-wider text-gray-400">Daily yield rate</div>
      <div class="mt-1 text-2xl sm:text-3xl font-extrabold sq-num" id="sqDaily">$<?= $money($snapshot['daily_yield'] ?? 0) ?></div>
      <div class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?= $ratePct ?>%/day · <?= (int) ($snapshot['term_days'] ?? 365) ?>-day term</div>
    </div>
    <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-[#111834] p-4">
      <div class="text-[11px] uppercase tracking-wider text-gray-400">Transferred to date</div>
      <div class="mt-1 text-2xl sm:text-3xl font-extrabold sq-num" id="sqClaimed">$<?= $money($snapshot['claimed_total'] ?? 0) ?></div>
      <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Lifetime yield moved to wallet</div>
    </div>
    <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-[#111834] p-4">
      <div class="text-[11px] uppercase tracking-wider text-gray-400">Compounded</div>
      <div class="mt-1 text-2xl sm:text-3xl font-extrabold sq-num" id="sqCompounded">$<?= $money($wallet['total_compounded'] ?? 0) ?></div>
      <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Earned by assets left in wallet</div>
    </div>
  </section>

  <!-- ── Yield tracker + wallet ──────────────────────────────────────────── -->
  <section class="grid gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-[#111834] p-5">
      <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
          <div class="flex items-center gap-2 font-bold"><i class="fas fa-wave-square text-primary"></i> Yield tracker</div>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Yield settles every 24 hours from the moment a unit is purchased — Day&nbsp;1 pays nothing, Day&nbsp;2 onwards pays <?= $ratePct ?>%.
          </p>
        </div>
        <span class="text-[11px] px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300 inline-flex items-center gap-1.5">
          <i class="fas fa-circle text-[6px] sq-pulse"></i> Live
        </span>
      </div>

      <div class="mt-5 grid sm:grid-cols-2 gap-4">
        <div>
          <div class="text-[11px] uppercase tracking-wider text-gray-400">Available to transfer</div>
          <div class="mt-1 text-4xl font-extrabold sq-num text-primary" id="sqPending">$<?= $money($snapshot['pending_yield'] ?? 0, 4) ?></div>
          <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Next settlement in <span class="font-semibold sq-num" id="sqNextAccrual" data-at="<?= htmlspecialchars((string) ($snapshot['next_accrual_at'] ?? '')) ?>">—</span>
          </div>
          <button type="button" id="sqClaimBtn" onclick="sqClaim(this)"
                  class="mt-4 w-full sm:w-auto px-5 py-3 rounded-xl font-semibold text-white bg-primary hover:bg-primary/90 disabled:opacity-40 disabled:cursor-not-allowed transition"
                  <?= ((float) ($snapshot['pending_yield'] ?? 0)) > 0 ? '' : 'disabled' ?>>
            <i class="fas fa-arrow-right-arrow-left mr-2"></i>Transfer to Wallet
          </button>
        </div>
        <div class="rounded-xl bg-gray-50 dark:bg-[#0b1020] border border-gray-200 dark:border-gray-800 p-4">
          <div class="text-[11px] uppercase tracking-wider text-gray-400">Wallet balance</div>
          <div class="mt-1 text-3xl font-extrabold sq-num" id="sqWallet">$<?= $money($wallet['balance'] ?? 0, 4) ?></div>
          <dl class="mt-3 space-y-1.5 text-xs">
            <div class="flex justify-between gap-2">
              <dt class="text-gray-500 dark:text-gray-400">Compounding at</dt>
              <dd class="font-semibold sq-num"><?= $ratePct ?>%/24h</dd>
            </div>
            <div class="flex justify-between gap-2">
              <dt class="text-gray-500 dark:text-gray-400">Next recalculation</dt>
              <dd class="font-semibold sq-num" id="sqNextCompound" data-at="<?= htmlspecialchars((string) ($snapshot['next_compound_at'] ?? '')) ?>">—</dd>
            </div>
            <div class="flex justify-between gap-2">
              <dt class="text-gray-500 dark:text-gray-400">Projected next cycle</dt>
              <dd class="font-semibold sq-num text-emerald-600 dark:text-emerald-400" id="sqWalletDaily">+$<?= $money($snapshot['wallet_daily'] ?? 0, 4) ?></dd>
            </div>
          </dl>
          <p class="mt-3 text-[11px] text-gray-400 leading-snug">
            Assets left in the wallet are re-rated every 24 hours, compounding onto the base allocation.
          </p>
        </div>
      </div>
    </div>

    <!-- Qualification -->
    <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-[#111834] p-5">
      <div class="flex items-center gap-2 font-bold"><i class="fas fa-id-card-clip text-primary"></i> Hardware qualification</div>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">All three cards unlock SQB engine units.</p>
      <ul class="mt-4 space-y-2.5">
        <?php foreach ($cardLabels as $code => $label):
          $has = in_array($code, $owned, true); ?>
          <li class="flex items-center gap-3">
            <span class="w-7 h-7 shrink-0 rounded-lg inline-flex items-center justify-center text-xs <?= $has
                  ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                  : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' ?>">
              <i class="fas <?= $has ? 'fa-check' : 'fa-lock' ?>"></i>
            </span>
            <span class="flex-1 text-sm font-medium"><?= htmlspecialchars($label) ?></span>
            <span class="text-xs <?= $has ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' ?>">
              <?= $has ? 'Held' : 'Missing' ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="mt-4 sq-meter bg-gray-100 dark:bg-gray-800">
        <span class="bg-primary" style="width:<?= (int) round(count(array_intersect(array_keys($cardLabels), $owned)) / 3 * 100) ?>%"></span>
      </div>
      <div class="mt-3 text-sm font-semibold <?= $qualified ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400' ?>">
        <i class="fas <?= $qualified ? 'fa-unlock' : 'fa-lock' ?> mr-1.5"></i>
        <?= $qualified ? 'SQB engines unlocked' : 'SQB engines locked' ?>
      </div>
    </div>
  </section>

  <!-- ── Catalogue ───────────────────────────────────────────────────────── -->
  <section>
    <h2 class="text-lg font-bold flex items-center gap-2"><i class="fas fa-layer-group text-primary"></i> Products</h2>
    <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <?php foreach ($products as $p):
        $code   = (string) $p['code'];
        $isCard = $p['kind'] === 'card';
        $has    = $isCard && in_array($code, $owned, true);
        $locked = !$isCard && !$qualified; ?>
        <article class="rounded-2xl border p-5 flex flex-col <?= $has
              ? 'border-emerald-300 dark:border-emerald-500/40 bg-emerald-50/50 dark:bg-emerald-500/5'
              : 'border-gray-200 dark:border-gray-800 bg-white dark:bg-[#111834]' ?>">
          <div class="flex items-start justify-between gap-2">
            <span class="w-10 h-10 rounded-xl inline-flex items-center justify-center <?= $isCard
                  ? 'bg-primary/10 text-primary' : 'bg-secondary/10 text-secondary' ?>">
              <i class="fas <?= $isCard ? 'fa-credit-card' : 'fa-microchip' ?>"></i>
            </span>
            <?php if ($has): ?>
              <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">
                <i class="fas fa-check mr-1"></i>Owned
              </span>
            <?php elseif ($locked): ?>
              <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                <i class="fas fa-lock mr-1"></i>Locked
              </span>
            <?php endif; ?>
          </div>

          <h3 class="mt-3 font-bold leading-tight"><?= htmlspecialchars((string) $p['name']) ?></h3>
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 flex-1"><?= htmlspecialchars((string) ($p['description'] ?? '')) ?></p>

          <div class="mt-3 flex items-baseline gap-1">
            <span class="text-2xl font-extrabold sq-num">$<?= $money($p['price']) ?></span>
            <?php if (!$isCard): ?><span class="text-xs text-gray-400">/ unit</span><?php endif; ?>
          </div>
          <?php if (!$isCard): ?>
            <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
              <?= rtrim(rtrim(number_format((float) $p['daily_rate'] * 100, 3), '0'), '.') ?>%/day · <?= (int) $p['term_days'] ?> days
            </div>
            <div class="mt-3 flex items-center gap-2">
              <label class="text-xs text-gray-500 dark:text-gray-400" for="sqUnitsInput">Units</label>
              <input id="sqUnitsInput" type="number" min="1" max="1000" value="1" <?= $locked ? 'disabled' : '' ?>
                     class="w-20 px-2 py-1.5 rounded-lg text-sm sq-num border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#0b1020] disabled:opacity-40"
                     oninput="sqUpdateEngineTotal()">
              <span class="text-xs text-gray-500 dark:text-gray-400 sq-num" id="sqEngineTotal">= $<?= $money($p['price']) ?></span>
            </div>
          <?php endif; ?>

          <button type="button" onclick="sqBuy(this,'<?= htmlspecialchars($code) ?>',<?= $isCard ? 'false' : 'true' ?>)"
                  class="mt-4 w-full px-4 py-2.5 rounded-xl font-semibold text-sm transition <?= ($has || $locked)
                    ? 'bg-gray-100 dark:bg-gray-800 text-gray-400 cursor-not-allowed'
                    : ($isCard ? 'bg-primary text-white hover:bg-primary/90' : 'bg-secondary text-white hover:bg-secondary/90') ?>"
                  <?= ($has || $locked) ? 'disabled' : '' ?>>
            <?php if ($has): ?><i class="fas fa-check mr-1.5"></i>In your account
            <?php elseif ($locked): ?><i class="fas fa-lock mr-1.5"></i>Hold all three cards
            <?php else: ?><i class="fas fa-cart-shopping mr-1.5"></i>Purchase<?php endif; ?>
          </button>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ── Allocations ─────────────────────────────────────────────────────── -->
  <section class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-[#111834] p-5">
    <h2 class="text-lg font-bold flex items-center gap-2"><i class="fas fa-server text-primary"></i> SQB allocations</h2>
    <?php if (!$allocs): ?>
      <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
        No engine units yet. <?= $qualified ? 'Buy your first SQB unit above to start accruing.' : 'Collect all three membership cards to unlock engine units.' ?>
      </p>
    <?php else: ?>
      <div class="mt-4 space-y-3">
        <?php foreach ($allocs as $a):
          $days   = (int) $a['days_accrued'];
          $term   = max(1, (int) $a['term_days']);
          $pct    = min(100, (int) round($days / $term * 100));
          $active = $a['status'] === 'active'; ?>
          <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4">
            <div class="flex items-center justify-between gap-3 flex-wrap">
              <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-lg bg-secondary/10 text-secondary inline-flex items-center justify-center"><i class="fas fa-microchip"></i></span>
                <div>
                  <div class="font-semibold text-sm"><?= (int) $a['units'] ?> unit<?= (int) $a['units'] === 1 ? '' : 's' ?> · $<?= $money($a['principal']) ?></div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">
                    Started <?= htmlspecialchars(date('M j, Y', strtotime((string) $a['start_at']))) ?>
                  </div>
                </div>
              </div>
              <div class="flex items-center gap-4 text-right">
                <div>
                  <div class="text-[10px] uppercase tracking-wider text-gray-400">Accrued</div>
                  <div class="font-bold sq-num text-sm">$<?= $money($a['accrued'], 4) ?></div>
                </div>
                <div>
                  <div class="text-[10px] uppercase tracking-wider text-gray-400">Transferred</div>
                  <div class="font-bold sq-num text-sm">$<?= $money($a['claimed_total'], 4) ?></div>
                </div>
                <span class="text-[10px] font-bold px-2 py-1 rounded-full <?= $active
                      ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                      : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' ?>">
                  <?= $active ? 'Active' : 'Matured' ?>
                </span>
              </div>
            </div>
            <div class="mt-3 flex items-center gap-3">
              <div class="flex-1 sq-meter bg-gray-100 dark:bg-gray-800"><span class="bg-secondary" style="width:<?= $pct ?>%"></span></div>
              <span class="text-xs text-gray-500 dark:text-gray-400 sq-num shrink-0">Day <?= $days ?> / <?= $term ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- ── AntFun referral tree ────────────────────────────────────────────── -->
  <section class="grid gap-4 lg:grid-cols-3">
    <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-[#111834] p-5">
      <h2 class="text-lg font-bold flex items-center gap-2"><i class="fas fa-sitemap text-primary"></i> AntFun network</h2>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        A hierarchy of its own — separate from how you joined the platform.
      </p>

      <div class="mt-4">
        <label class="text-[11px] uppercase tracking-wider text-gray-400" for="sqInvite">Your invite link</label>
        <div class="mt-1.5 flex gap-2">
          <input id="sqInvite" type="text" readonly onclick="this.select()"
                 value="<?= htmlspecialchars($inviteBase . (string) ($referral['code'] ?? '')) ?>"
                 class="flex-1 min-w-0 px-3 py-2 rounded-lg text-xs border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-[#0b1020]">
          <button type="button" onclick="sqCopyInvite(this)" class="shrink-0 px-3 py-2 rounded-lg text-sm font-semibold bg-primary text-white hover:bg-primary/90">
            <i class="fas fa-copy"></i>
          </button>
        </div>
      </div>

      <?php if (empty($referral['sponsor_id'])): ?>
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-800">
          <label class="text-[11px] uppercase tracking-wider text-gray-400" for="sqSponsor">Sponsor code</label>
          <div class="mt-1.5 flex gap-2">
            <input id="sqSponsor" type="text" placeholder="Paste an invite code"
                   class="flex-1 min-w-0 px-3 py-2 rounded-lg text-sm border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#0b1020]">
            <button type="button" onclick="sqJoin(this)" class="shrink-0 px-4 py-2 rounded-lg text-sm font-semibold bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 hover:opacity-90">Link</button>
          </div>
          <p class="mt-1.5 text-[11px] text-gray-400">Placement is permanent once set.</p>
        </div>
      <?php endif; ?>

      <dl class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-800 space-y-2 text-sm">
        <div class="flex justify-between gap-2">
          <dt class="text-gray-500 dark:text-gray-400">Level 1 · 15%</dt>
          <dd class="font-bold sq-num"><?= count($downline[1] ?? []) ?> · $<?= $money($commission['levels'][1] ?? 0) ?></dd>
        </div>
        <div class="flex justify-between gap-2">
          <dt class="text-gray-500 dark:text-gray-400">Level 2 · 5%</dt>
          <dd class="font-bold sq-num"><?= count($downline[2] ?? []) ?> · $<?= $money($commission['levels'][2] ?? 0) ?></dd>
        </div>
        <div class="flex justify-between gap-2 pt-2 border-t border-gray-200 dark:border-gray-800">
          <dt class="font-semibold">Total earned</dt>
          <dd class="font-extrabold sq-num text-emerald-600 dark:text-emerald-400">$<?= $money($commission['total'] ?? 0) ?></dd>
        </div>
      </dl>
    </div>

    <div class="lg:col-span-2 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-[#111834] p-5">
      <h2 class="text-lg font-bold flex items-center gap-2"><i class="fas fa-diagram-project text-primary"></i> Your two levels</h2>
      <?php if (empty($downline[1])): ?>
        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No one has joined under you yet. Share your invite link to start building.</p>
      <?php else: ?>
        <div class="mt-4 space-y-2 text-gray-900 dark:text-gray-100">
          <?php foreach ($downline[1] as $l1): ?>
            <div class="rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
              <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-[#0b1020]">
                <span class="w-8 h-8 shrink-0 rounded-lg bg-primary/10 text-primary inline-flex items-center justify-center text-xs font-bold">L1</span>
                <div class="flex-1 min-w-0">
                  <div class="font-semibold text-sm truncate"><?= htmlspecialchars((string) $l1['name']) ?></div>
                  <div class="text-[11px] text-gray-500 dark:text-gray-400"><?= (int) $l1['children'] ?> in their level 1</div>
                </div>
                <div class="text-right shrink-0">
                  <div class="text-[10px] uppercase tracking-wider text-gray-400">Volume</div>
                  <div class="font-bold sq-num text-sm">$<?= $money($l1['volume']) ?></div>
                </div>
              </div>
              <?php $kids = array_values(array_filter($downline[2] ?? [], static fn($c) => (int) $c['sponsor_id'] === (int) $l1['user_id']));
              if ($kids): ?>
                <div class="p-3 space-y-2">
                  <?php foreach ($kids as $l2): ?>
                    <div class="sq-branch flex items-center gap-3">
                      <span class="w-7 h-7 shrink-0 rounded-lg bg-secondary/10 text-secondary inline-flex items-center justify-center text-[10px] font-bold">L2</span>
                      <div class="flex-1 min-w-0 text-sm truncate"><?= htmlspecialchars((string) $l2['name']) ?></div>
                      <div class="font-semibold sq-num text-xs shrink-0">$<?= $money($l2['volume']) ?></div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ── Wallet ledger ───────────────────────────────────────────────────── -->
  <section class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-[#111834] p-5">
    <h2 class="text-lg font-bold flex items-center gap-2"><i class="fas fa-receipt text-primary"></i> Wallet activity</h2>
    <?php if (!$txns): ?>
      <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Nothing yet. Transfers, compounding, and referral overrides land here.</p>
    <?php else: ?>
      <div class="mt-4 -mx-5 px-5 overflow-x-auto">
        <table class="w-full text-sm min-w-[520px]">
          <thead>
            <tr class="text-left text-[11px] uppercase tracking-wider text-gray-400 border-b border-gray-200 dark:border-gray-800">
              <th class="pb-2 font-medium">When</th>
              <th class="pb-2 font-medium">Type</th>
              <th class="pb-2 font-medium">Detail</th>
              <th class="pb-2 font-medium text-right">Amount</th>
              <th class="pb-2 font-medium text-right">Balance</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800" id="sqTxnBody">
            <?php
            $typeMeta = [
              'yield_claim' => ['Transfer',  'fa-arrow-right-arrow-left'],
              'compound'    => ['Compound',  'fa-arrows-rotate'],
              'commission'  => ['Override',  'fa-user-group'],
              'purchase'    => ['Purchase',  'fa-cart-shopping'],
              'adjustment'  => ['Adjustment','fa-sliders'],
            ];
            foreach ($txns as $t):
              [$label, $icon] = $typeMeta[$t['type']] ?? ['Entry', 'fa-circle'];
              $amt = (float) $t['amount']; ?>
              <tr>
                <td class="py-2.5 text-gray-500 dark:text-gray-400 whitespace-nowrap"><?= htmlspecialchars(date('M j, H:i', strtotime((string) $t['created_at']))) ?></td>
                <td class="py-2.5 whitespace-nowrap"><i class="fas <?= $icon ?> text-gray-400 mr-1.5"></i><?= $label ?></td>
                <td class="py-2.5 text-gray-500 dark:text-gray-400"><?= htmlspecialchars((string) ($t['note'] ?? '')) ?></td>
                <td class="py-2.5 text-right font-semibold sq-num <?= $amt >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' ?>">
                  <?= $amt >= 0 ? '+' : '−' ?>$<?= $money(abs($amt), 4) ?>
                </td>
                <td class="py-2.5 text-right sq-num text-gray-500 dark:text-gray-400">$<?= $money($t['balance_after'], 4) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($isElevated && is_array($admin)): ?>
  <!-- ── Elevated: system-wide ledger ────────────────────────────────────── -->
  <section class="rounded-2xl border-2 border-amber-300 dark:border-amber-500/40 bg-amber-50/40 dark:bg-amber-500/5 p-5">
    <div class="flex items-center justify-between gap-3 flex-wrap">
      <h2 class="text-lg font-bold flex items-center gap-2">
        <i class="fas fa-shield-halved text-amber-600 dark:text-amber-400"></i> System ledger
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">Elevated</span>
      </h2>
      <button type="button" onclick="sqAdminRun(this)"
              class="px-4 py-2 rounded-lg text-sm font-semibold bg-amber-600 text-white hover:bg-amber-700">
        <i class="fas fa-play mr-1.5"></i>Force accrual pass
      </button>
    </div>

    <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
      <?php
      $adminTiles = [
        ['Pool principal',     '$' . $money($admin['pool_principal']),   $admin['active_allocs'] . ' active allocations'],
        ['Wallet float',       '$' . $money($admin['wallet_float']),     'liquidity held by members'],
        ['Unclaimed yield',    '$' . $money($admin['unclaimed_yield']),  'accrued, not yet transferred'],
        ['Total liability',    '$' . $money($admin['liability']),        $admin['coverage_pct'] . '% of gross sales'],
        ['Gross sales',        '$' . $money($admin['gross_sales']),      $admin['members'] . ' enrolled members'],
        ['Commissions paid',   '$' . $money($admin['commissions_paid']), 'L1 15% + L2 5%'],
        ['Net inflow',         '$' . $money($admin['net_inflow']),       'sales − overrides'],
        ['Daily payout cost',  '$' . $money($admin['pool_daily_cost'] + $admin['wallet_daily_cost']), 'allocations + compounding'],
      ];
      foreach ($adminTiles as [$label, $value, $sub]): ?>
        <div class="rounded-xl border border-amber-200 dark:border-amber-500/30 bg-white/70 dark:bg-[#111834] p-3.5">
          <div class="text-[11px] uppercase tracking-wider text-gray-400"><?= htmlspecialchars($label) ?></div>
          <div class="mt-0.5 text-xl font-extrabold sq-num"><?= htmlspecialchars($value) ?></div>
          <div class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400"><?= htmlspecialchars((string) $sub) ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
      <div class="rounded-xl border border-amber-200 dark:border-amber-500/30 bg-white/70 dark:bg-[#111834] p-4">
        <div class="font-semibold text-sm">Revenue by product</div>
        <table class="mt-3 w-full text-sm">
          <thead><tr class="text-left text-[11px] uppercase tracking-wider text-gray-400">
            <th class="pb-1.5 font-medium">Product</th><th class="pb-1.5 font-medium text-right">Orders</th>
            <th class="pb-1.5 font-medium text-right">Units</th><th class="pb-1.5 font-medium text-right">Revenue</th>
          </tr></thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            <?php foreach ($admin['by_product'] as $r): ?>
              <tr>
                <td class="py-1.5"><?= htmlspecialchars((string) $r['product_code']) ?></td>
                <td class="py-1.5 text-right sq-num"><?= (int) $r['orders'] ?></td>
                <td class="py-1.5 text-right sq-num"><?= (int) $r['units'] ?></td>
                <td class="py-1.5 text-right sq-num font-semibold">$<?= $money($r['revenue']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$admin['by_product']): ?><tr><td colspan="4" class="py-2 text-gray-400 text-xs">No completed purchases yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="rounded-xl border border-amber-200 dark:border-amber-500/30 bg-white/70 dark:bg-[#111834] p-4">
        <div class="font-semibold text-sm">Largest holders</div>
        <table class="mt-3 w-full text-sm">
          <thead><tr class="text-left text-[11px] uppercase tracking-wider text-gray-400">
            <th class="pb-1.5 font-medium">Member</th><th class="pb-1.5 font-medium text-right">Units</th>
            <th class="pb-1.5 font-medium text-right">Principal</th>
          </tr></thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            <?php foreach ($admin['top_holders'] as $h): ?>
              <tr>
                <td class="py-1.5 truncate max-w-[12rem]"><?= htmlspecialchars((string) $h['name']) ?></td>
                <td class="py-1.5 text-right sq-num"><?= (int) $h['units'] ?></td>
                <td class="py-1.5 text-right sq-num font-semibold">$<?= $money($h['principal']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$admin['top_holders']): ?><tr><td colspan="3" class="py-2 text-gray-400 text-xs">No allocations yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <details class="mt-4 rounded-xl border border-amber-200 dark:border-amber-500/30 bg-white/70 dark:bg-[#111834]">
      <summary class="p-4 cursor-pointer font-semibold text-sm select-none"><i class="fas fa-bug mr-1.5 text-gray-400"></i>Engine parameters &amp; worker state</summary>
      <div class="px-4 pb-4">
        <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-1.5 text-xs">
          <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">Accrual rows written</dt><dd class="sq-num font-semibold"><?= (int) $admin['accrual_rows'] ?></dd></div>
          <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">Matured allocations</dt><dd class="sq-num font-semibold"><?= (int) $admin['matured_allocs'] ?></dd></div>
          <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">Qualified members</dt><dd class="sq-num font-semibold"><?= (int) $admin['qualified'] ?> / <?= (int) $admin['members'] ?></dd></div>
          <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">Worker last run</dt><dd class="sq-num font-semibold"><?= htmlspecialchars((string) ($admin['worker_last_run'] ?? 'never')) ?></dd></div>
          <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">Last accrual settled</dt><dd class="sq-num font-semibold"><?= htmlspecialchars((string) ($admin['last_accrual']['accrued_for'] ?? '—')) ?></dd></div>
          <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">Server time</dt><dd class="sq-num font-semibold"><?= htmlspecialchars((string) $admin['params']['server_time']) ?></dd></div>
        </dl>
        <pre class="mt-3 p-3 rounded-lg bg-gray-900 text-gray-100 text-[11px] overflow-x-auto"><?= htmlspecialchars(json_encode($admin['params'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
      </div>
    </details>
  </section>
  <?php endif; ?>

  <p class="text-center text-[11px] text-gray-400 pb-4">
    SilverQueen is a simulation environment. Yields shown are modelled, not guaranteed returns.
  </p>
</main>

<!-- Toast -->
<div id="sqToast" class="fixed bottom-4 inset-x-4 sm:inset-x-auto sm:right-4 sm:w-80 z-50 hidden">
  <div class="rounded-xl px-4 py-3 shadow-lg text-sm font-medium flex items-center gap-2" id="sqToastBody"></div>
</div>

<script>
(function () {
  const CSRF = <?= json_encode($csrf) ?>;
  const RATE = <?= json_encode((float) ($snapshot['daily_rate'] ?? 0.005)) ?>;
  let state = {
    pending:  <?= json_encode((float) ($snapshot['pending_yield'] ?? 0)) ?>,
    daily:    <?= json_encode((float) ($snapshot['daily_yield'] ?? 0)) ?>,
    nextAccrual:  <?= json_encode($snapshot['next_accrual_at'] ?? null) ?>,
    nextCompound: <?= json_encode($snapshot['next_compound_at'] ?? null) ?>,
  };

  const $ = (id) => document.getElementById(id);
  const usd = (n, d = 2) => '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });

  function toast(message, ok = true) {
    const box = $('sqToast'), body = $('sqToastBody');
    body.className = 'rounded-xl px-4 py-3 shadow-lg text-sm font-medium flex items-center gap-2 ' +
      (ok ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white');
    body.innerHTML = '<i class="fas ' + (ok ? 'fa-circle-check' : 'fa-circle-exclamation') + '"></i><span></span>';
    body.querySelector('span').textContent = message;
    box.classList.remove('hidden');
    clearTimeout(toast._t);
    toast._t = setTimeout(() => box.classList.add('hidden'), 4000);
  }

  /** HH:MM:SS until an ISO timestamp, or '—' when there's nothing pending. */
  function countdown(iso) {
    if (!iso) return '—';
    const ms = new Date(iso).getTime() - Date.now();
    if (!isFinite(ms)) return '—';
    if (ms <= 0) return 'due now';
    const s = Math.floor(ms / 1000);
    return String(Math.floor(s / 3600)).padStart(2, '0') + ':' +
           String(Math.floor(s / 60) % 60).padStart(2, '0') + ':' +
           String(s % 60).padStart(2, '0');
  }

  // The settled figure is authoritative; this only ticks the countdowns beside it.
  setInterval(() => {
    $('sqNextAccrual').textContent  = countdown(state.nextAccrual);
    $('sqNextCompound').textContent = countdown(state.nextCompound);
  }, 1000);

  function paint(s) {
    if (!s) return;
    state.pending      = Number(s.pending_yield || 0);
    state.daily        = Number(s.daily_yield || 0);
    state.nextAccrual  = s.next_accrual_at;
    state.nextCompound = s.next_compound_at;

    $('sqPrincipal').textContent  = usd(s.principal);
    $('sqDaily').textContent      = usd(s.daily_yield);
    $('sqClaimed').textContent    = usd(s.claimed_total);
    $('sqCompounded').textContent = usd(s.wallet && s.wallet.total_compounded);
    $('sqPending').textContent    = usd(s.pending_yield, 4);
    $('sqWallet').textContent     = usd(s.wallet && s.wallet.balance, 4);
    $('sqWalletChip').textContent = usd(s.wallet && s.wallet.balance);
    $('sqWalletDaily').textContent = '+' + usd(s.wallet_daily, 4);
    $('sqUnits').textContent      = s.units;
    $('sqActive').textContent     = s.active_count;
    $('sqClaimBtn').disabled      = !(Number(s.pending_yield) > 0);
  }

  async function post(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      credentials: 'same-origin',
      body: JSON.stringify(Object.assign({ csrf_token: CSRF }, body || {})),
    });
    return res.json();
  }

  window.sqUpdateEngineTotal = function () {
    const input = $('sqUnitsInput'), out = $('sqEngineTotal');
    if (!input || !out) return;
    const unit = <?= json_encode((float) (array_values(array_filter($products, static fn($p) => $p['kind'] === 'engine'))[0]['price'] ?? 100)) ?>;
    out.textContent = '= ' + usd(Math.max(1, parseInt(input.value, 10) || 1) * unit);
  };

  window.sqBuy = async function (btn, code, isEngine) {
    const units = isEngine ? Math.max(1, parseInt(($('sqUnitsInput') || {}).value, 10) || 1) : 1;
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-1.5"></i>Processing';
    try {
      const r = await post('/silverqueen/purchase', { code, units });
      if (r.ok) {
        toast(r.product + ' purchased.' + (r.commissions && r.commissions.length ? ' Overrides paid to your upline.' : ''));
        setTimeout(() => location.reload(), 900);
        return;
      }
      toast(r.error || 'Purchase failed.', false);
    } catch (e) {
      toast('Network error — try again.', false);
    }
    btn.disabled = false;
    btn.innerHTML = original;
  };

  window.sqClaim = async function (btn) {
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-2"></i>Transferring';
    try {
      const r = await post('/silverqueen/claim', {});
      if (r.ok) {
        toast(usd(r.amount, 4) + ' moved to your wallet — it starts compounding now.');
        paint(r.snapshot);
        setTimeout(() => location.reload(), 1200);
        return;
      }
      toast(r.error || 'Nothing to transfer.', false);
    } catch (e) {
      toast('Network error — try again.', false);
    }
    btn.disabled = false;
    btn.innerHTML = original;
  };

  window.sqJoin = async function (btn) {
    const code = ($('sqSponsor').value || '').trim();
    if (!code) { toast('Enter a sponsor code first.', false); return; }
    btn.disabled = true;
    try {
      const r = await post('/silverqueen/enroll', { sponsor: code });
      toast(r.ok ? r.message : (r.error || 'Could not link.'), !!r.ok && !!r.changed);
      if (r.ok && r.changed) setTimeout(() => location.reload(), 900);
    } catch (e) {
      toast('Network error — try again.', false);
    }
    btn.disabled = false;
  };

  window.sqCopyInvite = function (btn) {
    const input = $('sqInvite');
    const done = () => { const h = btn.innerHTML; btn.innerHTML = '<i class="fas fa-check"></i>'; setTimeout(() => btn.innerHTML = h, 1500); };
    if (navigator.clipboard) {
      navigator.clipboard.writeText(input.value).then(done).catch(() => { input.select(); document.execCommand('copy'); done(); });
    } else { input.select(); document.execCommand('copy'); done(); }
  };

  window.sqAdminRun = async function (btn) {
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-1.5"></i>Running';
    try {
      const r = await post('/silverqueen/admin/run', {});
      if (r.ok) {
        toast('Settled ' + usd(r.accrual.amount, 4) + ' of yield and ' + usd(r.compound.amount, 4) + ' of compounding.');
        setTimeout(() => location.reload(), 1200);
        return;
      }
      toast(r.error || 'The pass failed.', false);
    } catch (e) {
      toast('Network error — try again.', false);
    }
    btn.disabled = false;
    btn.innerHTML = original;
  };

  // Re-settle from the server every 60s so a crossed 24h boundary shows up
  // without a reload, and pause while the tab is hidden.
  setInterval(async () => {
    if (document.hidden) return;
    try {
      const res = await fetch('/silverqueen/data', { credentials: 'same-origin' });
      const r = await res.json();
      if (r.ok) paint(r.snapshot);
    } catch (e) { /* transient — the next tick retries */ }
  }, 60000);
})();
</script>
</body>
</html>
