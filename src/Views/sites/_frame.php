<?php
/*
 * The SilverQueen Premium frame: head, header, and the terms footer every Sites
 * page carries. Usage: $frame = 'open' (before the page) / 'close' (after).
 * The footer is the owner's terms in brief (2026-10-24), naming the company
 * only as the one that MANAGES SilverQueen.
 */
$e = $e ?? static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
if (($frame ?? 'open') === 'open'): ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= $e($title ?? 'SilverQueen Premium') ?></title>
<meta name="description" content="Your own Page. Your own Site. Your own Store. All on SilverQueen.">
<meta name="theme-color" content="#0b0e14">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Inter:wght@400;500;600;700&display=swap">
<style>
:root{--shine:#b8862a;--bg:#fbf8f1;--panel:#fff;--panel2:#f3eee2;--line:#e7e0cf;--fg:#17140d;--muted:#6b6455;--gold:#e0b23c;--gold-ink:#2a1d05;--gold-fg:#7a5600;--ok:#0f7a3d;--bad:#b42318;color-scheme:light}
@media (prefers-color-scheme:dark){:root:not([data-theme=light]){--shine:#f6dc8c;--bg:#0b0e14;--panel:#11151f;--panel2:#161b28;--line:#232a3a;--fg:#ecebe6;--muted:#9097a8;--gold-fg:#f0c75a;--ok:#4ade80;--bad:#f87171;color-scheme:dark}}
:root[data-theme=dark]{--shine:#f6dc8c;--bg:#0b0e14;--panel:#11151f;--panel2:#161b28;--line:#232a3a;--fg:#ecebe6;--muted:#9097a8;--gold-fg:#f0c75a;--ok:#4ade80;--bad:#f87171;color-scheme:dark}
*{box-sizing:border-box}html{-webkit-text-size-adjust:100%}
body{margin:0;background:var(--bg);color:var(--fg);font:16px/1.55 Inter,system-ui,sans-serif;-webkit-font-smoothing:antialiased}
a{color:inherit}
.wrap{max-width:1080px;margin:0 auto;padding:0 16px}
.top{display:flex;align-items:center;gap:10px;padding:18px 0}
.logo{display:flex;align-items:center;gap:8px;font-weight:700;text-decoration:none;color:var(--gold-fg);letter-spacing:.2px}
.top nav{margin-left:auto;display:flex;gap:4px;flex-wrap:wrap}
.top nav a{text-decoration:none;font-size:14px;font-weight:600;color:var(--muted);padding:7px 10px;border-radius:8px}
.top nav a[aria-current]{color:var(--fg);background:var(--panel2)}
.logo{white-space:nowrap}
@media (max-width:520px){.top{flex-wrap:wrap;padding:14px 0 6px}.top nav{margin-left:0;width:100%;justify-content:space-between;flex-wrap:nowrap}.top nav a{flex:1 1 auto;text-align:center;white-space:nowrap;font-size:13px;padding:7px 6px}}
h1,h2,.serif{font-family:"Playfair Display",Georgia,serif;letter-spacing:-.5px;line-height:1.1;margin:0}
.gold-text{background:linear-gradient(90deg,var(--gold-fg),var(--shine) 45%,var(--gold-fg) 70%);-webkit-background-clip:text;background-clip:text;color:transparent}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:5px;padding:13px 20px;font:700 15px Inter,system-ui,sans-serif;cursor:pointer;text-decoration:none}
.btn.gold{background:var(--gold);color:var(--gold-ink);box-shadow:0 10px 24px -14px rgba(224,178,60,.9)}
.btn.ghost{background:var(--panel);color:var(--fg);border:1px solid var(--line)}
.btn:disabled{opacity:.55;cursor:default}
.card{background:var(--panel);border:1px solid var(--line);border-radius:18px}
.muted{color:var(--muted)}
.lab{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin:0 0 10px}
.meter{height:10px;border-radius:999px;background:var(--panel2);overflow:hidden}
.meter i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#b88a1e,var(--gold),#fff1b8)}
.terms{border-top:1px solid var(--line);margin-top:56px;padding:22px 0 40px;font-size:12.5px;color:var(--muted)}
.terms p{margin:0 0 8px;max-width:820px}
svg.i{width:18px;height:18px;flex:none}
</style>
</head>
<body>
<div class="wrap">
<header class="top">
  <a class="logo" href="/sites"><svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M3 7l4.5 4L12 4l4.5 7L21 7l-2 12H5L3 7z"/></svg> SilverQueen Premium</a>
  <nav>
    <a href="/sites" <?= ($nav ?? '') === 'dir' ? 'aria-current="page"' : '' ?>>Directory</a>
    <a href="/sites/plans" <?= ($nav ?? '') === 'plans' ? 'aria-current="page"' : '' ?>>Plans</a>
    <a href="/sites/start" <?= ($nav ?? '') === 'start' ? 'aria-current="page"' : '' ?>>Start it now</a>
    <a href="/sites/me" <?= ($nav ?? '') === 'me' ? 'aria-current="page"' : '' ?>>My Site</a>
  </nav>
</header>
<?php else: ?>
<footer class="terms">
  <p>SilverQueen is managed by Ginto Services Corporation (SEC Reg. No. 2026040246055-25). SilverQueen Premium are
     monthly hosting and builder services. Plans are services, not investments. They grant no ownership of any server or company
     asset and no right to profits or returns. Plans are non-transferable. Cancel anytime; the Founding price ends if the
     subscription lapses. <a href="https://silverqueen.pro/terms">Terms</a></p>
</footer>
</div>
</body>
</html>
<?php endif;
