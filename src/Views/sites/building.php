<?php
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$frame = 'open';
include __DIR__ . '/_frame.php';
$failed = ($status ?? '') === 'failed';
?>
<meta name="robots" content="noindex,nofollow">
<section class="card" style="max-width:620px;margin:40px auto 0;padding:30px;text-align:center">
  <?php if ($failed): ?>
    <h1 style="font-size:30px">We could not build it this time</h1>
    <p class="muted"><?= $e($error ?: 'Please try again.') ?></p>
    <a class="btn gold" href="/sites/start">Try again</a>
  <?php else: ?>
    <h1 style="font-size:30px">Building your site…</h1>
    <p class="muted">SilverQueen AI is recognizing your products, matching your colors and writing your descriptions. This page opens your preview by itself.</p>
    <div class="meter" style="margin-top:18px"><i id="bar" style="width:8%;transition:width 2s"></i></div>
    <script>
    (function () {
      let w = 8;
      const bar = document.getElementById('bar');
      const poll = async () => {
        let s = {};
        try { s = await (await fetch('/sites/p/<?= $e($token) ?>/status', { credentials: 'same-origin' })).json(); } catch (e) {}
        if (s.status === 'ready' || s.status === 'failed') { location.reload(); return; }
        w = Math.min(92, w + 7); bar.style.width = w + '%';
        setTimeout(poll, 2500);
      };
      setTimeout(poll, 2000);
    })();
    </script>
  <?php endif; ?>
</section>
<?php $frame = 'close'; include __DIR__ . '/_frame.php'; ?>
