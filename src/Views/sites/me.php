<?php
use Ginto\Sites\Config;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$nav = 'me';
$frame = 'open';
include __DIR__ . '/_frame.php';
$plan     = $draft['site']['plan'] ?? null;
$reviewed = !empty($draft['site']['reviewed_at']);
$active   = $sub && $sub['status'] === 'active';
$status   = !$name ? 'none' : ($live ? 'live' : ($name['status'] === 'lapsed' ? 'lapsed' : 'held'));
$pills    = ['live' => ['Live', 'ok'], 'held' => ['Held for you', 'gold'], 'lapsed' => ['Paused: renew to bring it back', 'bad'], 'none' => ['No name yet', 'bad']];
$slotsLeft = max(0, (int) $founding_cap - (int) $founding_filled);
$icon = static fn (string $p): string => '<svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
?>
<style>
.mh{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;padding:20px 0 6px}
.mh h1{font-size:clamp(28px,5vw,40px)}
.pill{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:700;padding:5px 12px;border-radius:999px}
.pill.ok{color:var(--ok);background:rgba(15,122,61,.12)}.pill.gold{color:var(--gold-fg);background:rgba(224,178,60,.16)}.pill.bad{color:var(--bad);background:rgba(180,35,24,.1)}
/* Open sections, a line between them: the only frames on this page are notices and the things you pick. */
.sec{margin-top:8px;padding:26px 0;border-top:1px solid var(--line)}.card.sec{margin-top:18px;padding:18px 20px;border-top:1px solid var(--line)}
.sec h2{font-size:24px;margin-bottom:6px}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
label.f{display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:600;color:var(--muted)}
.f input,.f textarea{box-sizing:border-box;width:100%;height:2.6rem;margin:0;padding:0 12px;border-radius:10px;border:1.5px solid var(--line);background:var(--bg);color:var(--fg);font:500 15px Inter,system-ui,sans-serif}
.f textarea{height:auto;min-height:84px;padding:10px 12px;resize:vertical;line-height:1.45}
.f input:focus,.f textarea:focus{outline:none;border-color:var(--gold)}
.prods{display:grid;margin-top:18px;border-top:1px solid var(--line)}
/* One flat section per product, divided by a line: never a box inside the review box. */
.prod{display:grid;grid-template-columns:150px 1fr;gap:18px;align-items:start;padding:18px 0;border-bottom:1px solid var(--line)}
.prod .ph{position:relative}
.prod img{display:block;width:100%;aspect-ratio:1;border-radius:12px;object-fit:cover;background:var(--panel2)}
.prod .ai{position:absolute;left:8px;top:8px;font-size:10.5px;font-weight:700;color:#2a1d05;background:#e0b23c;text-transform:uppercase;letter-spacing:.4px;padding:3px 8px;border-radius:999px}
.act{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:16px}
.say{font-size:14px;min-height:20px}.say.ok{color:var(--ok)}.say.bad{color:var(--bad)}
.plans3{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:14px}
.pc{position:relative;display:block;cursor:pointer;padding:16px;border:1.5px solid var(--line);border-radius:14px;background:var(--panel)}
.pc input{position:absolute;opacity:0;pointer-events:none}
.pc:has(input:checked){border-color:var(--gold);background:linear-gradient(170deg,rgba(224,178,60,.12),var(--bg) 60%)}
.pc b{display:block;font-size:17px}.pc .pr{font-size:26px;font-weight:800;margin:6px 0 2px}.pc .pr small{font-size:13px;color:var(--muted);font-weight:600}
.pc ul{list-style:none;margin:8px 0 0;padding:0;font-size:13px;color:var(--muted);display:grid;gap:4px}
.qr{display:none;margin-top:16px;padding:16px;border:1px dashed var(--gold);border-radius:14px;text-align:center}
.qr.on{display:block}
.qrwait{display:grid;place-items:center;gap:10px;width:240px;max-width:100%;aspect-ratio:1;margin:0 auto;border-radius:12px;background:var(--panel2);color:var(--muted);font-size:13px}
.spin{width:34px;height:34px;border-radius:50%;border:3px solid var(--line);border-top-color:var(--gold);animation:spn .8s linear infinite}
.btn .spin{width:16px;height:16px;border-width:2px}@keyframes spn{to{transform:rotate(360deg)}}
@media (prefers-reduced-motion:reduce){.spin{animation-duration:2.4s}}
.qr img{width:240px;max-width:100%;aspect-ratio:1;background:#fff;border-radius:12px;padding:8px}
.qact{display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin-top:14px}
.qleft{margin:4px 0 0;font-size:13px;color:var(--muted);font-variant-numeric:tabular-nums}.qleft b{color:var(--gold-fg)}
.qnote{margin:12px auto 0;max-width:420px;font-size:13px;color:var(--muted)}
.steps{list-style:none;display:flex;margin:6px 0 16px;padding:0;font-size:12.5px;color:var(--muted)}
.steps li{flex:1;display:grid;justify-items:center;align-content:start;gap:8px;position:relative;text-align:center;line-height:1.3}
#qrSay:empty{display:none}
.steps li::before{content:'';position:relative;z-index:1;width:22px;height:22px;box-sizing:border-box;border-radius:50%;border:2px solid var(--line);background:var(--bg);display:grid;place-items:center;font-size:12px;font-weight:800}
.steps li+li::after{content:'';position:absolute;top:10px;right:50%;width:100%;height:2px;background:var(--line)}
.steps li.done{color:var(--fg)}.steps li.done::before{content:'✓';border-color:var(--gold);background:var(--gold);color:var(--gold-ink)}
.steps li.done+li::after,.steps li.done+li.done::after{background:var(--gold)}
.steps li.now{color:var(--fg);font-weight:700}.steps li.now::before{border-color:var(--line);border-top-color:var(--gold);animation:spn .8s linear infinite}
.qbig{margin:0;font-size:17px;font-weight:700}
.cr{display:flex;justify-content:space-between;font-weight:700;margin-bottom:8px}
@media (max-width:760px){.row2,.plans3{grid-template-columns:1fr}.plans3{gap:8px}.pc{padding:12px 14px}.pc b{display:inline;font-size:16px}.pc .pr{display:inline;font-size:18px;margin:0 0 0 8px}.pc .pr small{font-size:12px}.pc ul{display:flex;flex-wrap:wrap;gap:2px 12px;margin:6px 0 0;font-size:12.5px}.prod{grid-template-columns:1fr;gap:12px}.prod img{aspect-ratio:16/10}}
</style>

<section class="mh">
  <div>
    <h1><?= $name ? $e($name['name']) . '<span class="muted" style="font-family:Inter,sans-serif;font-size:.55em;font-weight:600">.silverqueen.pro</span>' : 'My Site' ?></h1>
    <span class="pill <?= $pills[$status][1] ?>"><?= $e($pills[$status][0]) ?></span>
  </div>
  <?php if ($live): ?><a class="btn gold" href="https://<?= $e($name['name']) ?>.silverqueen.pro" target="_blank" rel="noopener"><?= $icon('<path d="M14 4h6v6M20 4l-9 9M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/>') ?> Open my site</a><?php endif; ?>
</section>

<?php if (!empty($simulated)): ?>
<section class="card sec" style="border-style:dashed"><b>Simulation. Only you see this.</b>
  <p class="muted" style="margin:6px 0 0">This is My Site as it will look once the code is paid. Nothing was paid, saved or published. <a href="/sites/me#pay">Back to the real page</a></p></section>
<?php endif; ?>
<?php if (!empty($just_paid) && ($phase ?? '') === 'live'): ?>
<section class="card sec" style="border-color:var(--ok)"><b>🎉 Paid. <?= $e($name['name']) ?>.silverqueen.pro is live.</b>
  <p class="muted" style="margin:6px 0 0"><?= !empty($sub['founding']) ? 'Thank you, Founding Member. Your price is locked for as long as you stay.' : 'Thank you. Your plan renews monthly; pay any time before it ends.' ?></p></section>
<?php endif; ?>
<?php
$clock = static function ($until): string {
    $s = max(0, strtotime((string) $until) - time());
    return $s >= 3600 ? floor($s / 3600) . 'h ' . floor(($s % 3600) / 60) . 'm' : max(1, (int) ceil($s / 60)) . ' min';
};
if (($phase ?? '') === 'trial'): ?>
<section class="card sec" style="border-color:var(--gold)">
  <b>🎉 Free trial: your site is live for <?= $e($clock($name['trial_until'])) ?> more.</b>
  <p class="muted" style="margin:6px 0 0">Look at it, share it, show it around. Choose a plan below to keep it up for good. Until then you can edit its words, not add to it.</p>
</section>
<?php elseif (($phase ?? '') === 'grace'): ?>
<section class="card sec" style="border-color:var(--bad)">
  <b>Your free trial has ended. Choose a plan within <?= $e($clock($name['release_at'])) ?> to keep <?= $e($name['name']) ?>.silverqueen.pro.</b>
  <p class="muted" style="margin:6px 0 0">After that the name is released for others. Your site and photos stay saved with you.</p>
</section>
<?php endif; ?>

<?php if (!$name || !$draft): ?>
<section class="sec">
  <?php if ($name): ?>
  <h2 class="serif">Build <?= $e($name['name']) ?>.silverqueen.pro</h2>
  <p class="muted">The name is yours. Pick your photos and tell SilverQueen AI about your shop: your site is built in about a minute and comes back here for your review.</p>
  <?php else: ?>
  <h2 class="serif">Start with a free preview</h2>
  <p class="muted">Upload your product photos and tell SilverQueen AI about your shop. When you like it, claim your name and come back here.</p>
  <?php endif; ?>
  <a class="btn gold" href="/sites/start"><?= $icon('<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3z"/>') ?> Start it now</a>
</section>
<?php else: ?>

<section class="sec" id="review">
  <h2 class="serif">1 · Review your site</h2>
  <p class="muted" style="margin:0">SilverQueen AI wrote these from your photos. Check every word: nothing goes live until you have.
    <?= $reviewed ? '<b style="color:var(--ok)">Reviewed.</b>' : '' ?></p>
  <form id="rv" onsubmit="return false" style="margin-top:14px">
    <div class="row2">
      <label class="f">Shop name<input name="brand" maxlength="60" value="<?= $e($plan['brand']['name']) ?>"></label>
      <label class="f">Tagline<input name="tagline" maxlength="120" value="<?= $e($plan['brand']['tagline']) ?>"></label>
    </div>
    <div class="row2" style="margin-top:12px">
      <label class="f">Headline<input name="headline" maxlength="90" value="<?= $e($plan['hero']['headline']) ?>"></label>
      <label class="f">Contact (https://, tel: or mailto:)<input name="contact" maxlength="200" placeholder="https://m.me/yourshop or tel:+639…" value="<?= $e($draft['site']['contact'] ?? '') ?>"></label>
    </div>
    <label class="f" style="margin-top:12px">About your shop<textarea name="about" maxlength="900"><?= $e($plan['about']['body']) ?></textarea></label>
    <div class="prods">
      <?php foreach ($plan['products'] as $i => $p): $sha = $draft['photos'][$p['photo']] ?? ''; ?>
      <div class="prod" data-i="<?= (int) $i ?>">
        <div class="ph"><img src="/sites/photo/<?= $e($sha) ?>" alt="" loading="lazy"><span class="ai">Written by AI · check it</span></div>
        <div style="display:grid;gap:10px">
          <label class="f">Product name<input data-k="name" maxlength="80" value="<?= $e($p['name']) ?>"></label>
          <label class="f">Description<textarea data-k="description" maxlength="320"><?= $e($p['description']) ?></textarea></label>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="act">
      <button class="btn gold" id="rvSave" type="button"><?= $icon('<path d="M5 12.5l4.2 4.2L19 7"/>') ?> <?= $reviewed ? 'Save changes' : 'I have reviewed it' ?></button>
      <span class="say" id="rvSay" aria-live="polite"></span>
    </div>
    <?php /* A plan already active (paid or complimentary) has nothing to pay: the
             next step after saving is the live site itself (owner, 2026-10-24). */ ?>
    <div class="act" id="rvNext" hidden>
      <a class="btn gold" href="https://<?= $e($name['name'] ?? '') ?>.silverqueen.pro" target="_blank" rel="noopener"><?= $icon('<path d="M14 4h6v6M20 4l-9 9M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/>') ?> Open my site</a>
      <button class="btn ghost" type="button" id="rvShare"><?= $icon('<path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7M16 6l-4-4-4 4M12 2v13"/>') ?> Share</button>
      <span class="say ok">Your site is live at <b><?= $e($name['name'] ?? '') ?>.silverqueen.pro</b>.</span>
    </div>
  </form>
</section>

<?php if (!empty($google) && $draft): $seoNow = $draft['site']['seo'] ?? null;
  $gTitle = $seoNow['title'] ?? ($plan['brand']['name'] . (($plan['brand']['tagline'] ?? '') !== '' ? ' — ' . $plan['brand']['tagline'] : ''));
  $gDesc = $seoNow['description'] ?? (($plan['hero']['subheadline'] ?? '') ?: ($plan['brand']['tagline'] ?? '')); ?>
<section class="sec" id="google">
  <h2 class="serif">On Google</h2>
  <label style="display:flex;align-items:center;gap:10px;margin-top:4px;font-weight:600"><input type="checkbox" id="gOn" <?= $google['on'] ? 'checked' : '' ?> style="width:20px;height:20px;accent-color:var(--gold)"> Show my site on Google</label>
  <p class="say <?= $google['state'] === 'bad' ? 'bad' : ($google['state'] === 'ok' ? 'ok' : '') ?>" id="gSay" style="margin:8px 0 0"><?= $e($google['text']) ?></p>
  <div style="margin-top:14px;padding:12px 14px;border-radius:12px;background:var(--panel2);max-width:600px">
    <div style="font-size:12px;color:var(--muted)"><?= $e($name['name']) ?>.silverqueen.pro</div>
    <div id="gTitle" style="font-size:18px;color:#8ab4f8;margin:2px 0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= $e(mb_substr($gTitle, 0, 70)) ?></div>
    <div id="gDesc" style="font-size:14px;color:var(--muted)"><?= $e(mb_substr($gDesc, 0, 170)) ?></div>
  </div>
  <p class="muted" style="margin:8px 0 0;font-size:13px">How your site may look in search. SilverQueen AI checks every change before Google can see it (1 AI credit).</p>
  <div class="act"><button class="btn ghost" id="seoGo" type="button"><?= $icon('<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3z"/>') ?> Improve my Google listing with AI</button><span class="say" id="seoSay" aria-live="polite">3 AI credits</span></div>
</section>
<?php endif; ?>

<section class="sec" id="ai">
  <h2 class="serif">Ask SilverQueen AI</h2>
  <p class="muted" style="margin:0">Say what to change: &ldquo;make the headline warmer&rdquo;, &ldquo;blue and white colours&rdquo;, &ldquo;shorter product descriptions&rdquo;. Your photos stay where they are. Each change uses AI credits.</p>
  <label class="f" style="margin-top:12px"><span class="sr-only" style="position:absolute;left:-9999px">What to change</span><textarea id="askText" maxlength="600" placeholder="What should change?"></textarea></label>
  <div class="act"><button class="btn gold" id="askGo" type="button"><?= $icon('<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3z"/>') ?> Change it</button><span class="say" id="askSay" aria-live="polite"></span></div>
</section>

<section class="sec" id="live">
  <h2 class="serif">Your live script</h2>
  <p class="muted" style="margin:0">A 30-second script for your next live, and three reel ideas, written from your own products. With Gold and Crown.</p>
  <div class="act"><button class="btn ghost" id="liveGo" type="button"><?= $icon('<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/>') ?> Write my live script</button><span class="say" id="liveSay" aria-live="polite"></span></div>
  <div id="liveOut" hidden style="margin-top:12px">
    <p id="liveScript" style="margin:0;white-space:pre-line;font-size:16px;line-height:1.6"></p>
    <p class="muted" style="margin:14px 0 6px;font-weight:700">Reel ideas</p><ol id="liveReels" style="margin:0;padding-left:20px;display:grid;gap:6px"></ol>
    <div class="act"><button class="btn ghost" id="liveCopy" type="button"><?= $icon('<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>') ?> Copy the script</button></div>
  </div>
</section>

<section class="sec" id="look">
  <h2 class="serif">Your look</h2>
  <p class="muted" style="margin:0">Twelve designs for the same site. Switching is free and instant<?= in_array($phase ?? '', ['trial', 'live'], true) ? ', and your site at ' . $e($name['name']) . '.silverqueen.pro changes with it' : '' ?>.</p>
  <div class="tpls" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-top:14px">
    <?php foreach (\Ginto\Sites\Templates::ALL as $tid => $t):
      $pal = is_array($t['palette']) ? $t['palette'] : ['#fff7ec', '#1b1814', '#8a3b12', '#e0b23c', '#f3e9d8']; ?>
    <label class="pc" style="padding:0;overflow:hidden"><input type="radio" name="look" value="<?= $e($tid) ?>" <?= ($draft['template'] ?? '') === $tid ? 'checked' : '' ?>>
      <span style="display:flex;align-items:flex-end;gap:4px;height:48px;padding:8px;background:<?= $e($pal[0]) ?>"><i style="flex:1;height:60%;border-radius:4px;background:<?= $e($pal[2]) ?>"></i><i style="flex:1;height:85%;border-radius:4px;background:<?= $e($pal[4]) ?>"></i><i style="flex:1;height:40%;border-radius:4px;background:<?= $e($pal[1]) ?>"></i></span>
      <b style="display:block;padding:8px 10px 0;font-size:14px"><?= $e($t['name']) ?></b><small style="display:block;padding:0 10px 10px;font-size:12px;color:var(--muted)"><?= $e($t['line']) ?></small></label>
    <?php endforeach; ?>
  </div>
  <p class="say" id="lookSay" aria-live="polite"></p>
</section>

<section class="sec" id="pay" style="scroll-margin-top:12px">
  <?php if ($active): $pl = Config::plan((string) $sub['plan']); ?>
    <h2 class="serif">2 · Your plan: <?= $e($pl['name'] ?? '') ?></h2>
    <?php $comp = \Ginto\Sites\Subscriptions::isComplimentary($sub); ?>
    <?php if ($comp): ?><p class="muted" style="margin:0"><b style="color:var(--gold-fg)">Complimentary</b> · every <?= $e($pl['name'] ?? '') ?> feature, nothing to pay.</p><?php else: ?>
    <p class="muted" style="margin:0">₱<?= number_format((int) $sub['price_php']) ?> a month<?= $sub['founding'] ? ' · <b style="color:var(--gold-fg)">🏅 Founding Member</b>, price locked while you stay' : '' ?>.
      Paid until <b><?= $e(date('j F Y', strtotime((string) $sub['period_end']))) ?></b>.</p><?php endif; ?>
    <?php if ($credits): $pct = min(100, (int) round($credits['used'] / max(1, $credits['quota']) * 100)); ?>
    <div style="margin-top:16px">
      <div class="cr"><span>AI credits used</span><span style="color:<?= $credits['warn'] ? 'var(--bad)' : 'var(--gold-fg)' ?>"><?= number_format($credits['used']) ?> / <?= number_format($credits['quota']) ?></span></div>
      <div class="meter"><i style="width:<?= max(2, $pct) ?>%"></i></div>
      <?php if ($credits['blocked']): ?><p class="say bad">This month's AI credits are used up. Your site keeps working; upgrade for more.</p>
      <?php elseif ($credits['warn']): ?><p class="say bad">You have used <?= $pct ?>% of this month's AI credits.</p><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($test_php)): ?><div class="act"><button class="btn gold" type="button" data-renew="<?= $e($sub['plan']) ?>"><?= $icon('<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3z"/>') ?> Test a real payment: ₱<?= (int) $test_php ?> by QR Ph</button><span class="say">Only you see this. The real road at a small amount.</span></div><?php endif; ?>
    <?php if ($comp && !empty($owner)): ?><div class="act"><button class="btn gold" id="payWalk" type="button" data-renew="<?= $e($sub['plan']) ?>"><?= $icon('<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v.01M14 20h.01M17 20h3v-3"/>') ?> Pay with QR Ph</button><span class="say">The real code at the real price, and the paid moment by simulation. Your plan stays complimentary.</span></div><?php endif; ?>
    <?php if (!$comp): ?><div class="act"><button class="btn ghost" type="button" data-renew="<?= $e($sub['plan']) ?>"><?= $icon('<path d="M4 12a8 8 0 0 1 14-5.3L20 9M20 4v5h-5M20 12a8 8 0 0 1-14 5.3L4 15M4 20v-5h5"/>') ?> Pay next month now</button></div><?php endif; ?>
  <?php else: ?>
    <h2 class="serif">2 · Choose your plan and go live</h2>
    <p class="muted" style="margin:0">Monthly, cancel anytime. <?= $slotsLeft > 0 ? '<b style="color:var(--gold-fg)">' . $slotsLeft . ' Founding slots left</b>: your price stays locked for as long as you stay.' : '' ?></p>
    <div class="plans3">
      <?php foreach (Config::PLANS as $id => $p): ?>
      <label class="pc"><input type="radio" name="plan" value="<?= $e($id) ?>" <?= $id === (Config::plan((string) ($plan_pick ?? '')) ? $plan_pick : 'gold') ? 'checked' : '' ?>>
        <b><?= $e($p['name']) ?></b><div class="pr">₱<?= number_format($p['php']) ?> <small>/ month</small></div>
        <ul><li><?= number_format($p['credits']) ?> AI credits a month</li><li><?= $p['storage_gb'] ?> GB · <?= $p['listings'] === null ? 'Unlimited' : $p['listings'] ?> products</li>
          <li><?= $p['chat'] ? 'AI chats with your customers' : 'AI writes for you' ?></li><li><?= $p['domain'] ? 'Your own domain' : 'Your .silverqueen.pro address' ?></li></ul>
      </label>
      <?php endforeach; ?>
    </div>
    <div class="act">
      <button class="btn gold" id="payGo" type="button" <?= $reviewed ? '' : 'disabled title="Review your site first"' ?>><?= $icon('<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v.01M14 20h.01M17 20h3v-3"/>') ?> Pay with QR Ph</button>
      <span class="say" id="paySay" aria-live="polite"><?= $reviewed ? '' : 'Review your site first.' ?></span>
    </div>
  <?php endif; ?>
  <div class="qr<?= !empty($pending) ? ' on' : '' ?>" id="qr" data-waiting="<?= !empty($pending) ? '1' : '' ?>" data-sub="<?= !empty($pending) ? (int) $pending['id'] : '' ?>" data-plan="<?= !empty($pending) ? $e($pending['plan']) : '' ?>">
    <div id="qrCode">
      <p class="qbig" style="margin-bottom:10px">Scan with GCash, Maya or your bank app</p>
      <div class="qrwait" id="qrWait"><span class="spin" aria-hidden="true"></span><span id="qrWaitWord"><?= !empty($pending) ? 'Loading your QR code…' : 'Making your QR code…' ?></span></div>
      <img id="qrImg" alt="QR Ph code" hidden>
      <p style="margin:10px 0 0;font-size:22px;font-weight:800" id="qrAmt"><?= !empty($pending) ? '₱' . number_format((int) $pending['price_php']) . ($pending['founding'] ? ' · 🏅 Founding Member' : '') : '' ?></p>
      <p class="qleft" id="qrLeft" aria-live="off"></p>
      <div class="qact">
        <button class="btn gold" id="qrPaid" type="button"><?= $icon('<path d="M20 6 9 17l-5-5"/>') ?> I have paid</button>
        <a class="btn ghost" id="qrDown" href="/sites/me/qr.png?download=1" download<?= empty($pending) ? ' hidden' : '' ?>><?= $icon('<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>') ?> Download QR</a>
      </div>
      <p class="qnote">Pay before the code expires. When it goes through, your site goes live by itself, even if you close this page.</p>
      <p class="say" id="qrSay" aria-live="polite"></p>
    </div>
    <div id="qrCheck" hidden>
      <ol class="steps"><li class="done">You paid</li><li class="now">Your bank confirms</li><li>Your site goes live</li></ol>
      <p class="qbig" id="ckSay" aria-live="polite">Confirming your payment…</p>
      <p class="qleft" id="ckTime">Usually under a minute.</p>
      <div class="qact" id="ckMore" hidden>
        <button class="btn gold" id="ckWait" type="button"><?= $icon('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>') ?> Keep waiting</button>
        <button class="btn ghost" id="ckBack" type="button"><?= $icon('<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3z"/>') ?> Show the code again</button>
      </div>
      <p class="qnote">Your payment is never lost: when it arrives, your site goes live by itself, even if you close this page.</p>
    </div>
    <div id="qrGone" hidden>
      <p class="qbig">This code expired<span id="goneAt"></span>.</p>
      <p class="qnote" style="margin-top:6px">Not paid yet? Make a new one, at the same price.</p>
      <div class="qact">
        <button class="btn gold" id="qrNew" type="button"><?= $icon('<path d="M4 12a8 8 0 0 1 14-5.3L20 9M20 4v5h-5M20 12a8 8 0 0 1-14 5.3L4 15M4 20v-5h5"/>') ?> Make a new code</button>
        <button class="btn ghost" id="qrPaidLate" type="button"><?= $icon('<path d="M20 6 9 17l-5-5"/>') ?> I paid it already</button>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<script>
(function () {
  const csrf = <?= json_encode((string) ($csrf_token ?? '')) ?>;
  const $ = (id) => document.getElementById(id);
  const tell = (el, kind, t) => { el.className = 'say' + (kind ? ' ' + kind : ''); el.textContent = t; };
  const post = async (url, body) => {
    try {
      const r = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify(Object.assign({ csrf_token: csrf }, body)) });
      return await r.json();
    } catch (e) { return { ok: false, error: 'No connection. Try again.' }; }
  };

  const save = $('rvSave');
  if (save) save.addEventListener('click', async () => {
    const f = $('rv');
    const products = {};
    f.querySelectorAll('.prod').forEach((p) => {
      products[p.dataset.i] = { name: p.querySelector('[data-k=name]').value, description: p.querySelector('[data-k=description]').value };
    });
    save.disabled = true;
    const r = await post('/sites/me/review', { brand: f.brand.value, tagline: f.tagline.value, headline: f.headline.value,
      contact: f.contact.value, about: f.about.value, products });
    save.disabled = false;
    if (!r.ok) { tell($('rvSay'), 'bad', r.error || 'Not saved.'); return; }
    const walk = $('payWalk');
    if (walk) { tell($('rvSay'), 'ok', 'Saved. Next: pay with QR Ph below.'); setTimeout(() => $('pay').scrollIntoView({ behavior: 'smooth', block: 'start' }), 350); return; }
    const payBtn = $('payGo');
    if (!payBtn && r.republished === false) { tell($('rvSay'), 'bad', 'Saved, but your site could not be updated just now. Press Save again in a minute.'); return; }
    tell($('rvSay'), 'ok', r.republished ? 'Saved, and your live site is updated.' : 'Saved. You can choose your plan now.');
    const next = $('rvNext');
    if (r.republished && next) {
      next.hidden = false;
      setTimeout(() => next.scrollIntoView({ behavior: 'smooth', block: 'center' }), 250);
    }
    if (payBtn) {
      payBtn.disabled = false; payBtn.removeAttribute('title'); tell($('paySay'), '', '');
      // Reviewed: the next step is the plan, so go there (owner, 2026-10-24).
      const pay = $('pay');
      if (pay) setTimeout(() => pay.scrollIntoView({ behavior: 'smooth', block: 'start' }), 350);
    }
  });

  const rvShare = $('rvShare');
  if (rvShare) rvShare.addEventListener('click', async () => {
    const url = rvShare.previousElementSibling.href;
    try { if (navigator.share) { await navigator.share({ url }); return; } } catch (e) { return; }
    try { await navigator.clipboard.writeText(url); tell($('rvSay'), 'ok', 'Link copied.'); } catch (e) { /* nothing to do */ }
  });

  // On Google: the switch, and the AI-written listing.
  const gOn = $('gOn');
  if (gOn) gOn.addEventListener('change', async () => {
    const r = await post('/sites/me/index', { on: gOn.checked });
    if (r.ok) tell($('gSay'), r.state === 'bad' ? 'bad' : (r.state === 'ok' ? 'ok' : ''), r.text);
    else { gOn.checked = !gOn.checked; tell($('gSay'), 'bad', r.error || 'Not changed.'); }
  });
  const seoGo = $('seoGo');
  if (seoGo) seoGo.addEventListener('click', async () => {
    seoGo.disabled = true; tell($('seoSay'), '', 'Writing…');
    const r = await post('/sites/me/seo', {});
    seoGo.disabled = false;
    if (!r.ok) { tell($('seoSay'), 'bad', r.error || 'Not written.'); return; }
    $('gTitle').textContent = r.seo.title; $('gDesc').textContent = r.seo.description;
    tell($('seoSay'), 'ok', 'Done. SilverQueen AI checks it before Google sees it.');
  });

  // Ask SilverQueen AI: the site changed as asked, checked like every build.
  const askGo = $('askGo');
  if (askGo) askGo.addEventListener('click', async () => {
    const text = $('askText').value.trim();
    if (text.length < 4) { tell($('askSay'), 'bad', 'Say what to change, in a sentence.'); return; }
    askGo.disabled = true; tell($('askSay'), '', 'SilverQueen AI is working on it…');
    const r = await post('/sites/me/ask', { text });
    askGo.disabled = false;
    if (!r.ok) { tell($('askSay'), 'bad', r.error || 'Not changed.'); return; }
    if (!r.changed || !r.changed.length) { tell($('askSay'), '', 'Nothing needed changing for that. Try saying it another way.'); return; }
    tell($('askSay'), 'ok', 'Changed: ' + r.changed.join(', ') + (r.republished ? '. Your live site is updated.' : '.') + ' Reloading…');
    setTimeout(() => location.reload(), 1600);
  });
  // The live script.
  const liveGo = $('liveGo');
  if (liveGo) liveGo.addEventListener('click', async () => {
    liveGo.disabled = true; tell($('liveSay'), '', 'Writing…');
    const r = await post('/sites/me/live-script', {});
    liveGo.disabled = false;
    if (!r.ok) { tell($('liveSay'), 'bad', r.error || 'Not written.'); return; }
    tell($('liveSay'), '', '');
    $('liveScript').textContent = r.script;
    const ol = $('liveReels'); ol.textContent = '';
    (r.reels || []).forEach((x) => { const li = document.createElement('li'); li.textContent = x; ol.appendChild(li); });
    $('liveOut').hidden = false;
    liveGo.lastChild.textContent = ' Write another';
  });
  const liveCopy = $('liveCopy');
  if (liveCopy) liveCopy.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText($('liveScript').textContent); tell($('liveSay'), 'ok', 'Copied.'); } catch (e) { tell($('liveSay'), 'bad', 'Copy did not work here; select the text instead.'); }
  });

  // Your look: the same site in another of the twelve, switched at once.
  document.querySelectorAll('input[name=look]').forEach((r) => r.addEventListener('change', async () => {
    tell($('lookSay'), '', 'Switching…');
    const res = await post('/sites/me/template', { id: r.value });
    tell($('lookSay'), res.ok ? 'ok' : 'bad', res.ok ? ('Now ' + res.name + (res.republished ? '. Your site is updated.' : '.')) : (res.error || 'Not switched.'));
  }));

  /*
   * The code and what comes after it, like a payment app's own flow (owner, 2026-10-24):
   * the code with its time left, "I have paid", then the bank confirming with
   * "Keep waiting" when it is slow, and a new code once one expires. One poller
   * asks the server; a payment made in time always counts, even arriving late.
   */
  const qr = $('qr');
  const Q = { expires: 0, expired: false, mode: '', since: 0 };
  const store = (k, v) => { try { if (v === undefined) return localStorage.getItem(k); if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, v); } catch (e) { return null; } return null; };
  const claimKey = () => 'sqp-claimed-' + (qr ? qr.dataset.sub : '');
  const clock = (ms) => { const t = Math.max(0, Math.round(ms / 1000)); return Math.floor(t / 60) + ':' + String(t % 60).padStart(2, '0'); };
  function mode(m) {
    Q.mode = m;
    $('qrCode').hidden = m !== 'code'; $('qrCheck').hidden = m !== 'check'; $('qrGone').hidden = m !== 'gone';
    if (m === 'check') { Q.since = Date.now(); $('ckMore').hidden = true; $('ckSay').textContent = 'Confirming your payment…'; }
    if (m === 'gone' && Q.expires) $('goneAt').textContent = ' at ' + new Date(Q.expires).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  }
  function tick() {
    if (!qr || !qr.classList.contains('on') || Q.done) return;
    const left = Q.expires - Date.now();
    if (Q.mode === 'code' && Q.expires) {
      if (left <= 0 || Q.expired) { mode('gone'); return; }
      $('qrLeft').innerHTML = 'Valid for <b>' + clock(left) + '</b>';
    }
    if (Q.mode === 'check') {
      const gone = Date.now() - Q.since;
      if (gone >= 90000 && $('ckMore').hidden) {
        $('ckMore').hidden = false;
        $('ckSay').textContent = 'Not received yet.';
        $('ckTime').textContent = 'Some banks take a few minutes to send it. We keep checking either way.';
      } else if ($('ckMore').hidden) {
        $('ckTime').textContent = 'Checking for ' + clock(gone) + ' · usually under a minute.';
      }
    }
  }
  function learn(st) {
    if (st.expires_at) Q.expires = Date.parse(st.expires_at) || Q.expires;
    if (typeof st.expired === 'boolean') Q.expired = st.expired;
    if (st.sub_id && qr) qr.dataset.sub = st.sub_id;
  }
  // Paid while away? Asked now, every few seconds, and every time they come back (from GCash, another tab).
  async function checkPaid() {
    let st = {};
    try { st = await (await fetch('/sites/me/checkout', { credentials: 'same-origin' })).json(); } catch (e) { return; }
    if (st.status === 'active') { store(claimKey(), null); success('/sites/me?paid=1'); return; }
    if (st.status === 'waiting') { learn(st); tick(); }
  }
  /*
   * Paid: the steps fill in one by one, then the live page. The same moment for
   * a real payment and for the owner's simulation (owner-sim.js calls it), so
   * what he sees is what a member will see.
   */
  const wait = (ms) => new Promise((r) => setTimeout(r, ms));
  async function success(next, note) {
    if (Q.done) return;
    Q.done = true;
    clearInterval(polling);
    mode('check');
    const steps = $('qrCheck').querySelectorAll('.steps li');
    const set = (i, cls) => { if (steps[i]) steps[i].className = cls; };
    $('ckMore').hidden = true;
    $('ckTime').textContent = note || '';
    set(0, 'done'); set(1, 'now'); set(2, '');
    $('ckSay').textContent = 'Confirming your payment…';
    await wait(1200);
    set(1, 'done'); set(2, 'now');
    $('ckSay').textContent = 'Payment received.';
    await wait(1400);
    set(2, 'done');
    $('ckSay').textContent = 'Paid. Your site is going live…';
    await wait(1000);
    location.assign(next);
  }
  if (qr) qr.sqpSuccess = success;

  let polling = 0;
  let ticking = 0;
  const poll = () => { clearInterval(polling); polling = setInterval(checkPaid, 4000); if (!ticking) ticking = setInterval(tick, 1000); };
  function showCode() {
    $('qrImg').onload = () => { $('qrWait').hidden = true; $('qrImg').hidden = false; };
    $('qrImg').onerror = () => { $('qrWaitWord').textContent = 'Checking the code…'; checkPaid(); };
    $('qrImg').hidden = true; $('qrWait').hidden = false;
    $('qrImg').src = '/sites/me/qr.png?t=' + Date.now();
    $('qrDown').hidden = false;
  }

  async function pay(plan) {
    const say = $('paySay') || $('qrSay');
    const btn = $('payGo');
    const label = btn ? btn.innerHTML : '';
    // Making the code takes PayMongo a few seconds, and the image a few more: say so at once.
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spin" aria-hidden="true"></span> Making your QR code…'; }
    $('qrImg').hidden = true; $('qrWait').hidden = false; $('qrWaitWord').textContent = 'Making your QR code…';
    $('qrAmt').textContent = ''; $('qrLeft').textContent = ''; tell($('qrSay'), '', '');
    Q.expires = 0; Q.expired = false; mode('code');
    qr.classList.add('on');
    qr.scrollIntoView({ behavior: 'smooth', block: 'center' });
    const r = await post('/sites/me/checkout', { plan });
    if (btn) { btn.disabled = false; btn.innerHTML = label; }
    if (!r.ok) { qr.classList.remove('on'); tell(say, 'bad', r.error || 'The QR code could not be made.'); return; }
    qr.dataset.plan = plan;
    if (r.paid) { checkPaid(); return; }
    learn(r);
    $('qrWaitWord').textContent = 'Loading the code…';
    showCode();
    $('qrAmt').textContent = '₱' + Number(r.amount).toLocaleString('en-PH') + (r.founding ? ' · 🏅 Founding Member' : '');
    tick(); poll();
  }

  if (qr && qr.dataset.waiting === '1') {
    // "I have paid" survives a reload for as long as the code could have been paid (30 minutes).
    const claimed = Number(store(claimKey()) || 0);
    mode(claimed && Date.now() - claimed < 1800000 ? 'check' : 'code');
    if (Q.mode === 'check') Q.since = claimed;
    showCode(); checkPaid(); poll();
  }
  if (qr) {
    const claim = () => { store(claimKey(), String(Date.now())); mode('check'); tick(); checkPaid(); };
    $('qrPaid').addEventListener('click', claim);
    $('qrPaidLate').addEventListener('click', claim);
    $('ckWait').addEventListener('click', () => { mode('check'); tick(); checkPaid(); });
    $('ckBack').addEventListener('click', () => { store(claimKey(), null); mode(Q.expired || (Q.expires && Q.expires < Date.now()) ? 'gone' : 'code'); tick(); });
    $('qrNew').addEventListener('click', () => {
      const c = document.querySelector('input[name=plan]:checked');
      pay(qr.dataset.plan || (c ? c.value : 'silver'));
    });
  }
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible' && qr && qr.classList.contains('on')) checkPaid(); });

  // A change of plan is kept at once, so the app and every page show the same choice.
  document.querySelectorAll('input[name=plan]').forEach((r) => r.addEventListener('change', () => { post('/sites/me/plan', { plan: r.value }); }));
  const go = $('payGo');
  if (go) go.addEventListener('click', () => { const c = document.querySelector('input[name=plan]:checked'); if (c) pay(c.value); });
  document.querySelectorAll('[data-renew]').forEach((b) => b.addEventListener('click', () => pay(b.dataset.renew)));
})();
</script>
<?php if (!empty($owner)): ?><script src="/sites/me/sim.js" defer></script><?php endif; ?>
<?php $frame = 'close'; include __DIR__ . '/_frame.php'; ?>
