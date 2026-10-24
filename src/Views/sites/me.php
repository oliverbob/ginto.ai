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
.qr img{width:240px;max-width:100%;aspect-ratio:1;background:#fff;border-radius:12px;padding:8px}
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
  <h2 class="serif">Start with a free preview</h2>
  <p class="muted">Upload your product photos and tell SilverQueen AI about your shop. When you like it, claim your name and come back here.</p>
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
  </form>
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

<section class="sec" id="pay">
  <?php if ($active): $pl = Config::plan((string) $sub['plan']); ?>
    <h2 class="serif">2 · Your plan: <?= $e($pl['name'] ?? '') ?></h2>
    <p class="muted" style="margin:0">₱<?= number_format((int) $sub['price_php']) ?> a month<?= $sub['founding'] ? ' · <b style="color:var(--gold-fg)">🏅 Founding Member</b>, price locked while you stay' : '' ?>.
      Paid until <b><?= $e(date('j F Y', strtotime((string) $sub['period_end']))) ?></b>.</p>
    <?php if ($credits): $pct = min(100, (int) round($credits['used'] / max(1, $credits['quota']) * 100)); ?>
    <div style="margin-top:16px">
      <div class="cr"><span>AI credits used</span><span style="color:<?= $credits['warn'] ? 'var(--bad)' : 'var(--gold-fg)' ?>"><?= number_format($credits['used']) ?> / <?= number_format($credits['quota']) ?></span></div>
      <div class="meter"><i style="width:<?= max(2, $pct) ?>%"></i></div>
      <?php if ($credits['blocked']): ?><p class="say bad">This month's AI credits are used up. Your site keeps working; upgrade for more.</p>
      <?php elseif ($credits['warn']): ?><p class="say bad">You have used <?= $pct ?>% of this month's AI credits.</p><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="act"><button class="btn ghost" type="button" data-renew="<?= $e($sub['plan']) ?>"><?= $icon('<path d="M4 12a8 8 0 0 1 14-5.3L20 9M20 4v5h-5M20 12a8 8 0 0 1-14 5.3L4 15M4 20v-5h5"/>') ?> Pay next month now</button></div>
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
  <div class="qr" id="qr">
    <p style="margin:0 0 10px;font-weight:700">Scan with GCash, Maya or your bank app</p>
    <img id="qrImg" alt="QR Ph code">
    <p style="margin:10px 0 0;font-size:22px;font-weight:800" id="qrAmt"></p>
    <p class="say" id="qrSay" aria-live="polite">Waiting for your payment…</p>
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
    tell($('rvSay'), 'ok', r.republished ? 'Saved, and your live site is updated.' : 'Saved. You can choose your plan now.');
    document.querySelectorAll('input[name=look]').forEach((r) => r.addEventListener('change', async () => {
    tell($('lookSay'), '', 'Switching…');
    const res = await post('/sites/me/template', { id: r.value });
    tell($('lookSay'), res.ok ? 'ok' : 'bad', res.ok ? ('Now ' + res.name + (res.republished ? '. Your site is updated.' : '.')) : (res.error || 'Not switched.'));
  }));
  const go = $('payGo');
    if (go) { go.disabled = false; go.removeAttribute('title'); tell($('paySay'), '', ''); }
  });

  let polling = 0;
  async function pay(plan) {
    const say = $('paySay') || $('qrSay');
    const r = await post('/sites/me/checkout', { plan });
    if (!r.ok) { tell(say, 'bad', r.error || 'The QR code could not be made.'); return; }
    $('qrImg').src = r.qr_image;
    $('qrAmt').textContent = '₱' + Number(r.amount).toLocaleString('en-PH') + (r.founding ? ' · 🏅 Founding Member' : '');
    $('qr').classList.add('on');
    $('qr').scrollIntoView({ behavior: 'smooth', block: 'center' });
    clearInterval(polling);
    polling = setInterval(async () => {
      let s = {};
      try { s = await (await fetch('/sites/me/checkout', { credentials: 'same-origin' })).json(); } catch (e) { return; }
      if (s.status === 'active') {
        clearInterval(polling);
        tell($('qrSay'), 'ok', s.published ? 'Paid. Your site is live!' : 'Paid. Publishing your site…');
        setTimeout(() => location.reload(), 1500);
      }
    }, 3000);
  }
  const go = $('payGo');
  if (go) go.addEventListener('click', () => { const c = document.querySelector('input[name=plan]:checked'); if (c) pay(c.value); });
  document.querySelectorAll('[data-renew]').forEach((b) => b.addEventListener('click', () => pay(b.dataset.renew)));
})();
</script>
<?php $frame = 'close'; include __DIR__ . '/_frame.php'; ?>
