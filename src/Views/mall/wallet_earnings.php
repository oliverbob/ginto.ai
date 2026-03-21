<?php
$sellerStats = $seller_stats ?? ['gross_sales'=>0,'net_earnings'=>0,'order_count'=>0,'total_commissions'=>0,'pending_payout'=>0];
$wallet = $wallet ?? [];
$balance = (float)($wallet['balance'] ?? 0);
$gross = (float)$sellerStats['gross_sales'];
$commissions = (float)$sellerStats['total_commissions'];
$net = (float)$sellerStats['net_earnings'];
$deductions = $gross - $net;
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<body>
<?php include __DIR__ . '/parts/header.php'; ?>
<style>
.wpage-wrap { max-width: 900px; margin: 28px auto 60px; padding: 0 16px; }
.wpage-head { display:flex; align-items:center; gap:14px; margin-bottom:24px; flex-wrap:wrap; }
.wpage-icon { width:48px; height:48px; border-radius:14px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.wpage-back { display:inline-flex; align-items:center; gap:6px; font-size:0.8rem; color:var(--muted); font-weight:600; text-decoration:none; padding:6px 12px; border:1px solid var(--border); border-radius:8px; transition:all var(--trans); margin-bottom:16px; }
.wpage-back:hover { background:var(--surface); color:var(--text); }
.wpage-title { font-size:1.35rem; font-weight:800; }
.wpage-sub { font-size:0.8rem; color:var(--muted); margin-top:2px; }
.earnings-card { background:var(--surface); border:1px solid var(--border); border-radius:20px; padding:24px; margin-bottom:16px; }
.earnings-card-title { font-size:0.72rem; text-transform:uppercase; letter-spacing:0.09em; font-weight:700; color:var(--muted); margin-bottom:16px; }
.breakdown-row { display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid rgba(255,255,255,0.05); font-size:0.9rem; }
.breakdown-row:last-child { border-bottom:none; }
.breakdown-label { color:var(--muted); font-weight:500; }
.breakdown-val { font-weight:700; }
.breakdown-divider { height:1px; background:var(--border); margin:8px 0; opacity:0.5; }
.big-num { font-size:2.4rem; font-weight:900; color:#a5b4fc; line-height:1; }
.big-sub { font-size:0.78rem; color:var(--muted); margin-top:6px; }
.balance-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:16px; }
@media (max-width:480px) { .balance-grid { grid-template-columns:1fr; } }
.balance-item { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:16px; }
.balance-item-label { font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em; font-weight:700; color:var(--muted); margin-bottom:6px; }
.balance-item-val { font-size:1.25rem; font-weight:800; }
</style>

<div class="wpage-wrap">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
    <a class="wpage-back" style="margin-bottom:0;" href="/wallet">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Wallet
    </a>
    <a class="wpage-back" style="margin-bottom:0;" href="/wallet/storefront">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        My Storefront
    </a>
    </div>
    <div class="wpage-head">
        <div class="wpage-icon" style="background:rgba(99,102,241,0.12);color:#a5b4fc;">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
        </div>
        <div>
            <div class="wpage-title">My Earnings</div>
            <div class="wpage-sub">Net earnings after taxes, shipping, and platform charges</div>
        </div>
    </div>

    <div class="balance-grid">
        <div class="balance-item">
            <div class="balance-item-label">Wallet Balance</div>
            <div class="balance-item-val" style="color:var(--accent);">₱<?= number_format($balance, 2) ?></div>
        </div>
        <div class="balance-item">
            <div class="balance-item-label">Net Earnings (Total)</div>
            <div class="balance-item-val" style="color:#a5b4fc;">₱<?= number_format($net, 2) ?></div>
        </div>
    </div>

    <div class="earnings-card">
        <div class="earnings-card-title">Earnings Breakdown</div>
        <div class="breakdown-row">
            <span class="breakdown-label">Gross Sales</span>
            <span class="breakdown-val">₱<?= number_format($gross, 2) ?></span>
        </div>
        <div class="breakdown-row">
            <span class="breakdown-label" style="color:#f87171;">Fees &amp; Deductions</span>
            <span class="breakdown-val" style="color:#f87171;">− ₱<?= number_format(max(0, $deductions), 2) ?></span>
        </div>
        <div class="breakdown-row">
            <span class="breakdown-label">Commissions Earned</span>
            <span class="breakdown-val" style="color:#fbbf24;">+ ₱<?= number_format($commissions, 2) ?></span>
        </div>
        <div class="breakdown-divider"></div>
        <div class="breakdown-row">
            <span style="font-weight:800;font-size:0.92rem;">Net Earnings</span>
            <span class="breakdown-val" style="color:#4ade80;font-size:1.05rem;">₱<?= number_format($net + $commissions, 2) ?></span>
        </div>
    </div>

    <div class="earnings-card" style="text-align:center;padding:32px;">
        <div class="big-num">₱<?= number_format($net, 2) ?></div>
        <div class="big-sub">Total net earnings from <?= (int)$sellerStats['order_count'] ?> paid order<?= (int)$sellerStats['order_count'] !== 1 ? 's' : '' ?></div>
        <div style="margin-top:20px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
            <a href="/wallet/sales" class="btn btn-secondary" style="text-decoration:none;font-size:0.82rem;">View Sales</a>
            <a href="/wallet/payout-accounts" class="btn btn-primary" style="text-decoration:none;font-size:0.82rem;">Manage Payout Account</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/parts/footer.php'; ?>
</body>
</html>
