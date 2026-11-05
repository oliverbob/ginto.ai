<?php
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$nav = 'dir';
$frame = 'open';
include __DIR__ . '/_frame.php';
$sites = $sites ?? [];
?>
<style>
.dh{display:grid;grid-template-columns:1.4fr 1fr;gap:24px;align-items:end;padding:26px 0 8px}
.dh h1{font-size:clamp(34px,6vw,58px)}.dh p{color:var(--muted);font-size:17px;margin:14px 0 22px;max-width:520px}
.slots{padding:20px 22px}
.list{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:14px;margin-top:30px}
.site{padding:18px;text-decoration:none;display:flex;flex-direction:column;gap:6px;transition:transform .2s,border-color .2s}
.site:hover{transform:translateY(-2px);border-color:var(--gold)}
.site b{font-size:17px}.site span{font-size:13px;color:var(--muted)}
.empty{margin-top:30px;padding:34px 20px;text-align:center}
.empty h2{font-size:26px}
@media (max-width:760px){.dh{grid-template-columns:1fr}}
</style>

<section class="dh">
  <div>
    <h1>The SilverQueen <span class="gold-text">Directory</span></h1>
    <p>Every Page, Site and Store on SilverQueen, in one place. Founding Members are listed first.</p>
    <a class="btn gold" href="/sites/start"><svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg> Start it now</a>
  </div>
  <div class="card slots">
    <div style="display:flex;justify-content:space-between;font-weight:700;margin-bottom:10px"><span>Founding slots filled</span><span style="color:var(--gold-fg)"><?= (int) $founding_filled ?> of <?= (int) $founding_cap ?></span></div>
    <div class="meter"><i style="width:<?= max(3, min(100, (int) round($founding_filled / max(1, $founding_cap) * 100))) ?>%"></i></div>
    <p class="muted" style="font-size:13px;margin:10px 0 0">Once your name is taken, it is gone.</p>
  </div>
</section>

<?php if ($sites): ?>
<div class="list">
  <?php foreach ($sites as $s): ?>
  <a class="card site" href="https://<?= $e($s['name']) ?>.silverqueen.pro"><b><?= $e($s['name']) ?></b><span><?= $e($s['name']) ?>.silverqueen.pro</span></a>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card empty">
  <h2 class="serif">The Founders Wall is waiting for its first names.</h2>
  <p class="muted">Be one of the first <?= (int) $founding_cap ?>. Preview your site free, then claim your name.</p>
</div>
<?php endif; ?>
<?php $frame = 'close'; include __DIR__ . '/_frame.php'; ?>
