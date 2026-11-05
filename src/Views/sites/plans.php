<?php
use Ginto\Sites\Config;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$nav = 'plans';
$frame = 'open';
include __DIR__ . '/_frame.php';
$P = Config::PLANS;
$yes = '<svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-label="Included"><path d="M5 12.5l4.2 4.2L19 7"/></svg>';
$no = '<span class="muted" aria-label="Not included">—</span>';
$cell = static fn ($v) => $v === true ? $yes : ($v === false ? $no : $e($v));
$rows = [
    ['Page: silverqueen.pro/yourname', true, true, true],
    ['Site: yourname.silverqueen.pro', true, true, true],
    ['Merchant store + live selling', true, true, true],
    ['AI builds your site from your photos', true, true, true],
    ['Custom design and code', true, true, true],
    ['AI Assistant edits your site by chat', true, true, true],
    ['AI product descriptions and captions', true, true, true],
    ['AI customer chat on your Site', $P['silver']['chat'], $P['gold']['chat'], $P['crown']['chat']],
    ['AI live-selling scripts and reel ideas', $P['silver']['live_ai'], $P['gold']['live_ai'], $P['crown']['live_ai']],
    ['AI sales insights', $P['silver']['insights'], $P['gold']['insights'], $P['crown']['insights']],
    ['AI credits a month', number_format($P['silver']['credits']), number_format($P['gold']['credits']), number_format($P['crown']['credits'])],
    ['Storage', $P['silver']['storage_gb'] . ' GB', $P['gold']['storage_gb'] . ' GB', $P['crown']['storage_gb'] . ' GB'],
    ['Product listings', (string) $P['silver']['listings'], (string) $P['gold']['listings'], 'Unlimited'],
    ['Render minutes a month', (string) $P['silver']['render_min'], (string) $P['gold']['render_min'], (string) $P['crown']['render_min']],
    ['Live-streaming hours a month', (string) $P['silver']['live_hours'], (string) $P['gold']['live_hours'], (string) $P['crown']['live_hours']],
    ['Render queue', $P['silver']['queue'], $P['gold']['queue'], $P['crown']['queue']],
    ['Directory', $P['silver']['directory'], $P['gold']['directory'], $P['crown']['directory']],
    ['Custom domain', $P['silver']['domain'], $P['gold']['domain'], $P['crown']['domain']],
    ['Beta Tester Panel', $P['silver']['beta'], $P['gold']['beta'], $P['crown']['beta']],
];
$tags = ['silver' => 'Everything to start', 'gold' => 'Most chosen', 'crown' => 'For the biggest sellers'];
$keys = [
    'silver' => ['300 AI credits a month', '2 GB storage', '50 products', '10 live hours a month'],
    'gold'   => ['1,000 AI credits a month', 'AI chats with your customers', 'Your own domain', '200 products · 30 live hours'],
    'crown'  => ['3,000 AI credits a month', 'AI sales insights', 'Featured in the Directory', 'Unlimited products · 60 live hours'],
];
$name = preg_match(Config::NAME_RE, (string) ($_GET['name'] ?? '')) ? (string) $_GET['name'] : '';
?>
<style>
.ph{text-align:center;padding:24px 0 6px}.ph h1{font-size:clamp(32px,6vw,52px)}.ph p{color:var(--muted);margin:12px auto 0;max-width:560px}
.plans{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:28px}
.plan{padding:22px;position:relative;display:flex;flex-direction:column}
.plan.gold{border:2px solid var(--gold);background:linear-gradient(170deg,rgba(224,178,60,.14),var(--panel) 55%)}
.plan .nm{display:flex;align-items:center;gap:8px;font-weight:800;font-size:19px}.plan .nm svg{color:var(--gold-fg)}
.plan .tg{font-size:13px;color:var(--muted);margin-top:2px}.plan.gold .tg{color:var(--gold-fg);font-weight:700}
.plan .pr{font-size:38px;font-weight:800;letter-spacing:-1px;margin:16px 0 4px}.plan .pr small{font-size:15px;font-weight:600;color:var(--muted);letter-spacing:0}
.plan ul{list-style:none;padding:0;margin:14px 0 20px;display:grid;gap:9px;font-size:14.5px}
.plan li{display:flex;gap:8px}.plan li svg{color:var(--ok);margin-top:2px}
.plan .btn{margin-top:auto}
.all{text-align:center;color:var(--muted);margin:18px 0 0;font-size:14px}
.cmp{margin-top:36px;overflow-x:auto}
.cmp table{width:100%;border-collapse:collapse;font-size:14px;min-width:560px}
.cmp th,.cmp td{padding:11px 10px;border-bottom:1px solid var(--line);text-align:center}
.cmp th:first-child,.cmp td:first-child{text-align:left}
.cmp thead th{font-size:15px;color:var(--gold-fg)}
.cmp td svg{color:var(--ok)}
.cmp .g{background:rgba(224,178,60,.07)}
.fw{margin-top:28px;padding:20px 22px;display:grid;grid-template-columns:1.3fr 1fr;gap:20px;align-items:center}
.fw ul{margin:0;padding:0;list-style:none;display:grid;gap:8px;font-size:14.5px}
@media (max-width:820px){.plans{grid-template-columns:1fr}.plan.gold{order:-1}.fw{grid-template-columns:1fr}}
</style>

<section class="ph">
  <h1>Every plan gets it all.</h1>
  <p>Page, Site, Merchant store, the AI builder, custom design and code — in every plan. Monthly; cancel anytime.</p>
</section>

<div class="plans">
<?php foreach ($P as $id => $p): ?>
  <div class="card plan <?= $e($id) ?>">
    <div class="nm"><svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M3 7l4.5 4L12 4l4.5 7L21 7l-2 12H5L3 7z"/></svg><?= $e($p['name']) ?></div>
    <div class="tg"><?= $e($tags[$id]) ?></div>
    <div class="pr">₱<?= number_format($p['php']) ?> <small>/ month</small></div>
    <ul><?php foreach ($keys[$id] as $k): ?><li><?= $yes ?><span><?= $e($k) ?></span></li><?php endforeach; ?></ul>
    <a class="btn <?= $id === 'gold' ? 'gold' : 'ghost' ?>" href="<?= $e(($next ?? '/sites/start') . '?plan=' . $id) ?>"><svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg> Start with <?= $e($p['name']) ?></a>
  </div>
<?php endforeach; ?>
</div>
<p class="all">Preview free before you pay a single peso. Checkout opens with the Founding launch.</p>

<div class="card cmp">
  <table>
    <thead><tr><th></th><?php foreach ($P as $id => $p): ?><th class="<?= $id === 'gold' ? 'g' : '' ?>"><?= $e($p['name']) ?><br><span class="muted" style="font-size:13px">₱<?= number_format($p['php']) ?>/mo</span></th><?php endforeach; ?></tr></thead>
    <tbody><?php foreach ($rows as $r): ?><tr><td><?= $e($r[0]) ?></td><td><?= $cell($r[1]) ?></td><td class="g"><?= $cell($r[2]) ?></td><td><?= $cell($r[3]) ?></td></tr><?php endforeach; ?></tbody>
  </table>
</div>

<section class="card fw">
  <div>
    <p class="lab">Founding Members</p>
    <ul>
      <li>🏅 Your price locked for as long as you stay subscribed</li>
      <li>👑 A permanent Founding badge on your Page and Site</li>
      <li>✨ A place on the Founders Wall</li>
    </ul>
  </div>
  <div>
    <div style="display:flex;justify-content:space-between;font-weight:700;margin-bottom:8px"><span>Founding slots filled</span><span style="color:var(--gold-fg)"><?= (int) $founding_filled ?> of <?= (int) $founding_cap ?></span></div>
    <div class="meter"><i style="width:<?= max(3, min(100, (int) round($founding_filled / max(1, $founding_cap) * 100))) ?>%"></i></div>
    <p class="muted" style="font-size:13px;margin:10px 0 0">We are moving to a new video server for faster rendering, smoother lives and more viewers per stream. Founding Members power it.</p>
  </div>
</section>
<?php $frame = 'close'; include __DIR__ . '/_frame.php'; ?>
