<?php
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$nav = 'start';
$claimToken = preg_match('/^[0-9a-f]{32}$/', (string) ($_GET['claim'] ?? '')) ? (string) $_GET['claim'] : '';
$frame = 'open';
include __DIR__ . '/_frame.php';
?>
<style>
.hero{padding:28px 0 8px;text-align:center}
.hero h1{font-size:clamp(34px,7vw,60px)}
.hero p{max-width:640px;margin:16px auto 0;font-size:18px;color:var(--muted)}
.steps{display:flex;justify-content:center;gap:8px;flex-wrap:wrap;margin:22px 0 0}
.steps span{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:600;padding:7px 12px;border-radius:999px;background:var(--panel2)}
.steps b{display:grid;place-items:center;width:20px;height:20px;border-radius:50%;background:var(--gold);color:var(--gold-ink);font-size:11px}
.builder{margin:28px auto 0;max-width:760px;padding:22px}
.drop{border:2px dashed var(--line);border-radius:14px;padding:26px 16px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s}
.drop:hover,.drop.over{border-color:var(--gold);background:var(--panel2)}
.drop svg{width:34px;height:34px;color:var(--gold-fg)}
.drop strong{display:block;margin-top:8px;font-size:16px}
.thumbs{display:grid;grid-template-columns:repeat(auto-fill,minmax(86px,1fr));gap:8px;margin-top:12px}
.thumbs div{position:relative;aspect-ratio:1;border-radius:10px;overflow:hidden;background:var(--panel2)}
.thumbs img{width:100%;height:100%;object-fit:cover}
.thumbs button{position:absolute;top:4px;right:4px;width:24px;height:24px;border-radius:50%;border:0;background:rgba(0,0,0,.65);color:#fff;cursor:pointer;display:grid;place-items:center}
textarea{width:100%;min-height:120px;margin-top:16px;padding:14px;border-radius:12px;border:1.5px solid var(--line);background:var(--bg);color:var(--fg);font:15px/1.5 Inter,system-ui,sans-serif;resize:vertical}
textarea:focus,.claim input:focus{outline:none;border-color:var(--gold)}
.row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:14px}
.count{font-size:12px;color:var(--muted);margin-left:auto}
.say{min-height:22px;margin-top:10px;font-size:14px}.say.bad{color:var(--bad)}.say.ok{color:var(--ok)}
.progress{display:none;margin-top:18px}.progress.on{display:block}
.progress li{list-style:none;display:flex;align-items:center;gap:10px;padding:7px 0;color:var(--muted);font-weight:600}
.progress li i{width:18px;height:18px;border-radius:50%;border:2px solid var(--line);flex:none}
.progress li.on{color:var(--fg)}.progress li.on i{border-color:var(--gold);border-top-color:transparent;animation:spin .8s linear infinite}
.progress li.done{color:var(--fg)}.progress li.done i{background:var(--gold);border-color:var(--gold)}
@keyframes spin{to{transform:rotate(360deg)}}
.soon{text-align:center;padding:30px 16px}
.claim{margin:28px auto 0;max-width:760px;padding:22px}
.claim .box{display:flex;align-items:center;border:1.5px solid var(--line);border-radius:12px;background:var(--bg)}
.claim .box.ok{border-color:var(--ok)}.claim .box.bad{border-color:var(--bad)}
.claim input{flex:1;min-width:0;border:0;background:transparent;color:var(--fg);font:600 17px Inter,system-ui,sans-serif;padding:13px 0 13px 14px}
.claim .sfx{color:var(--muted);padding-right:14px;white-space:nowrap}
.fbar{max-width:760px;margin:28px auto 0;padding:18px 22px}
.fbar .head{display:flex;justify-content:space-between;font-weight:700;margin-bottom:10px}
.fbar .head b{color:var(--gold-fg)}
@media (prefers-reduced-motion:reduce){.progress li.on i{animation:none}}
</style>

<section class="hero">
  <h1>Your own Page. Your own Site. <span class="gold-text">Your own Store.</span></h1>
  <p>Upload your product photos. SilverQueen AI recognizes your products, writes your descriptions, matches your colors, and builds your site.</p>
  <div class="steps"><span><b>1</b> Upload photos</span><span><b>2</b> Tell us your shop</span><span><b>3</b> Preview free</span><span><b>4</b> Claim your name</span></div>
</section>

<?php if (!empty($held)): $pl = $plan ? \Ginto\Sites\Config::plan($plan) : null; ?>
<section class="card claim" style="border-color:var(--gold)">
  <p class="lab">Your site</p>
  <h2 class="serif" style="font-size:26px;margin:0"><?= $e($held['name']) ?><span class="muted" style="font-family:Inter,sans-serif;font-size:.6em">.silverqueen.pro</span></h2>
  <p class="muted" style="margin:8px 0 0"><?= $pl ? $e($pl['name']) . ' plan · ' : '' ?><b>1 · Build it here</b> → 2 · Review it → 3 · Go live.
    <?php if (!empty($held['preview_id'])): ?> Your site is already built: <a href="/sites/me">review it and go live</a>.<?php else: ?> Upload your product photos and describe your shop below.<?php endif; ?></p>
</section>
<?php endif; ?>

<?php if ($claimToken !== ''): ?>
<section class="card claim" id="claim">
  <p class="lab">Claim your name</p>
  <div class="box"><input id="cname" maxlength="32" placeholder="yourname" autocomplete="off" autocapitalize="off" spellcheck="false" aria-label="Your name"><span class="sfx">.silverqueen.pro</span></div>
  <div class="say" id="csay">Your Page: silverqueen.pro/<b id="cpg">yourname</b> · your Site and Store: <b id="cst">yourname</b>.silverqueen.pro</div>
  <div class="row"><button class="btn gold" id="cgo" type="button" disabled><svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.5l4.2 4.2L19 7"/></svg> Claim this name</button>
    <a class="btn ghost" href="/sites/p/<?= $e($claimToken) ?>"><svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg> See my preview</a></div>
</section>
<?php endif; ?>

<?php if (!empty($open)): ?>
<form class="card builder" id="builder" onsubmit="return false" enctype="multipart/form-data">
  <p class="lab">Your products</p>
  <label class="drop" id="drop">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="15" rx="3"/><circle cx="9" cy="11" r="2"/><path d="M21 17l-5-5-9 8"/></svg>
    <strong>Add your product photos</strong>
    <span class="muted">Up to 12 · JPG, PNG or WebP</span>
    <input id="files" type="file" accept="image/jpeg,image/png,image/webp" multiple hidden>
  </label>
  <div class="thumbs" id="thumbs"></div>
  <textarea id="desc" maxlength="2000" placeholder="Tell SilverQueen AI about your shop: what you sell, who it is for, what makes it yours. Write it in English, Filipino or Bisaya."></textarea>
  <div class="row">
    <button class="btn gold" id="go" type="button"><svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3z"/></svg> Build my free preview</button>
    <span class="muted" style="font-size:13px">No account, no payment.</span>
    <span class="count" id="count">0 / 2,000</span>
  </div>
  <div class="say" id="say" aria-live="polite"></div>
  <ol class="progress" id="progress">
    <li data-s="0"><i></i> Recognizing your products</li>
    <li data-s="1"><i></i> Matching your colors</li>
    <li data-s="2"><i></i> Writing your descriptions</li>
    <li data-s="3"><i></i> Building your site</li>
  </ol>
</form>
<?php else: ?>
<section class="card builder soon">
  <h2 class="serif" style="font-size:28px">Opening soon</h2>
  <p class="muted">SilverQueen Sites is getting ready for its Founding Members. See the plans while you wait.</p>
  <a class="btn gold" href="/sites/plans">See the plans</a>
</section>
<?php endif; ?>

<section class="card fbar">
  <div class="head"><span>Founding slots filled</span><b><?= (int) $founding_filled ?> of <?= (int) $founding_cap ?></b></div>
  <div class="meter"><i style="width:<?= max(3, min(100, (int) round($founding_filled / max(1, $founding_cap) * 100))) ?>%"></i></div>
  <p class="muted" style="margin:10px 0 0;font-size:14px">Founding Members keep their price for as long as they stay, wear a permanent Founding badge, and are on the Founders Wall.</p>
</section>

<script>
(function () {
  const csrf = <?= json_encode((string) ($csrf_token ?? '')) ?>;
  const $ = (id) => document.getElementById(id);

  // ---- the builder ----
  const form = $('builder');
  if (form) {
    let files = [];
    const thumbs = $('thumbs'), say = $('say'), desc = $('desc'), go = $('go');
    const tell = (kind, text) => { say.className = 'say' + (kind ? ' ' + kind : ''); say.textContent = text; };
    const paint = () => {
      thumbs.textContent = '';
      files.forEach((f, i) => {
        const d = document.createElement('div');
        const img = document.createElement('img');
        img.src = URL.createObjectURL(f); img.alt = '';
        const x = document.createElement('button');
        x.type = 'button'; x.setAttribute('aria-label', 'Remove photo'); x.textContent = '×';
        x.onclick = () => { files.splice(i, 1); paint(); };
        d.append(img, x); thumbs.appendChild(d);
      });
    };
    const add = (list) => {
      for (const f of list) {
        if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { tell('bad', 'Use JPG, PNG or WebP photos.'); continue; }
        if (files.length >= 12) { tell('bad', 'Up to 12 photos for a preview.'); break; }
        files.push(f);
      }
      paint();
    };
    $('files').addEventListener('change', (ev) => { add(ev.target.files); ev.target.value = ''; });
    const drop = $('drop');
    ['dragenter', 'dragover'].forEach((t) => drop.addEventListener(t, (ev) => { ev.preventDefault(); drop.classList.add('over'); }));
    ['dragleave', 'drop'].forEach((t) => drop.addEventListener(t, (ev) => { ev.preventDefault(); drop.classList.remove('over'); }));
    drop.addEventListener('drop', (ev) => add(ev.dataTransfer.files));
    desc.addEventListener('input', () => { $('count').textContent = desc.value.length.toLocaleString() + ' / 2,000'; });

    const steps = [...document.querySelectorAll('#progress li')];
    let tick = 0;
    const advance = () => {
      steps.forEach((li, i) => { li.classList.toggle('done', i < tick); li.classList.toggle('on', i === tick); });
    };

    go.addEventListener('click', async () => {
      if (!files.length) { tell('bad', 'Add at least one product photo.'); return; }
      if (desc.value.trim().length < 20) { tell('bad', 'Tell us a little more about your shop (a sentence or two).'); desc.focus(); return; }
      go.disabled = true; tell('', 'Uploading your photos…');
      const body = new FormData();
      files.forEach((f) => body.append('photos[]', f));
      body.append('description', desc.value);
      body.append('csrf_token', csrf);
      let r;
      try { r = await (await fetch('/sites/start', { method: 'POST', body, credentials: 'same-origin' })).json(); }
      catch (e) { r = { ok: false, error: 'The upload did not go through. Check your connection and try again.' }; }
      if (!r.ok) { go.disabled = false; tell('bad', r.error || 'Not built.'); return; }
      tell('', ''); $('progress').classList.add('on'); advance();
      const stepper = setInterval(() => { if (tick < steps.length - 1) { tick++; advance(); } }, 6000);
      const poll = async () => {
        let s = {};
        try { s = await (await fetch('/sites/p/' + r.token + '/status', { credentials: 'same-origin' })).json(); } catch (e) {}
        if (s.status === 'ready') { clearInterval(stepper); tick = steps.length; advance(); location.assign(r.url); return; }
        if (s.status === 'failed') { clearInterval(stepper); go.disabled = false; $('progress').classList.remove('on'); tell('bad', s.error || 'We could not build it this time.'); return; }
        setTimeout(poll, 2500);
      };
      setTimeout(poll, 2500);
    });
  }

  // ---- claiming a name ----
  const cname = $('cname');
  if (cname) {
    const box = cname.closest('.box'), csay = $('csay'), cgo = $('cgo');
    let seq = 0, timer = 0, ok = false;
    const paintAddr = (n) => { $('cpg').textContent = n || 'yourname'; $('cst').textContent = n || 'yourname'; };
    cname.addEventListener('input', () => {
      const n = cname.value.toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 32);
      if (n !== cname.value) cname.value = n;
      ok = false; cgo.disabled = true; box.className = 'box'; paintAddr(n);
      clearTimeout(timer);
      if (n.length < 3) return;
      const mine = ++seq;
      timer = setTimeout(async () => {
        let v = {};
        try { v = await (await fetch('/sites/name?n=' + encodeURIComponent(n), { credentials: 'same-origin' })).json(); } catch (e) {}
        if (mine !== seq) return;
        ok = !!v.ok; cgo.disabled = !ok; box.className = 'box ' + (ok ? 'ok' : 'bad');
        csay.className = 'say ' + (ok ? 'ok' : 'bad');
        csay.textContent = ok ? (v.mine ? 'This name is already yours.' : n + '.silverqueen.pro is available.') : (v.reason || 'Not available.');
      }, 300);
    });
    cgo.addEventListener('click', async () => {
      cgo.disabled = true;
      let r = {};
      try {
        const res = await fetch('/sites/claim', { method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
          body: JSON.stringify({ name: cname.value, token: <?= json_encode($claimToken) ?>, csrf_token: csrf }) });
        r = await res.json();
      } catch (e) { r = { ok: false, reason: 'Not claimed. Try again.' }; }
      if (r.login) { location.assign('/login?next=' + encodeURIComponent(location.pathname + location.search + '#claim')); return; }
      csay.className = 'say ' + (r.ok ? 'ok' : 'bad');
      csay.textContent = r.ok ? r.name + '.silverqueen.pro is yours. Next: review your site and choose your plan.' : (r.reason || 'Not claimed.');
      if (r.ok && r.next) setTimeout(() => location.assign(r.next), 1200); else cgo.disabled = false;
    });
  }
})();
</script>
<?php $frame = 'close'; include __DIR__ . '/_frame.php'; ?>
