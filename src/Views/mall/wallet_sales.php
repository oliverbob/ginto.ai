<?php
$sales = $sales ?? [];
$sellerStats = $seller_stats ?? ['gross_sales'=>0,'net_earnings'=>0,'order_count'=>0,'total_commissions'=>0,'pending_payout'=>0];
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<body>
<?php include __DIR__ . '/parts/header.php'; ?>
<style>
.wpage-wrap { max-width: 1100px; margin: 28px auto 60px; padding: 0 16px; }
.wpage-head { display:flex; align-items:center; gap:14px; margin-bottom:24px; flex-wrap:wrap; }
.wpage-icon { width:48px; height:48px; border-radius:14px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.wpage-back { display:inline-flex; align-items:center; gap:6px; font-size:0.8rem; color:var(--muted); font-weight:600; text-decoration:none; padding:6px 12px; border:1px solid var(--border); border-radius:8px; transition:all var(--trans); margin-bottom:16px; }
.wpage-back:hover { background:var(--surface); color:var(--text); }
.wpage-title { font-size:1.35rem; font-weight:800; }
.wpage-sub { font-size:0.8rem; color:var(--muted); margin-top:2px; }
.wstat-mini { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:24px; }
.wstat-mini-card { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:12px 18px; flex:1; min-width:120px; }
.wstat-mini-label { font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em; font-weight:700; color:var(--muted); }
.wstat-mini-val { font-size:1.1rem; font-weight:800; margin-top:3px; }
.data-table { width:100%; border-collapse:collapse; font-size:0.875rem; }
.data-table th { text-align:left; padding:10px 14px; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.07em; font-weight:700; color:var(--muted); border-bottom:1px solid var(--border); }
.data-table td { padding:13px 14px; border-bottom:1px solid rgba(255,255,255,0.04); vertical-align:middle; }
.data-table tr:last-child td { border-bottom:none; }
.data-table tbody tr:hover td { background:var(--surface2); }
.status-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 9px; border-radius:20px; font-size:0.7rem; font-weight:700; text-transform:capitalize; }
.status-paid { background:rgba(34,197,94,0.12); color:#4ade80; }
.status-pending { background:rgba(245,158,11,0.12); color:#fbbf24; }
.status-cancelled { background:rgba(239,68,68,0.1); color:#f87171; }
.table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:18px; overflow:hidden; overflow-x:auto; }
.thumb { width:38px; height:38px; border-radius:8px; object-fit:cover; background:var(--surface2); flex-shrink:0; }
.empty-page { text-align:center; padding:60px 20px; color:var(--muted); }
.empty-page-icon { font-size:3rem; margin-bottom:12px; opacity:0.3; }
</style>

<div class="wpage-wrap">
    <a class="wpage-back" href="/wallet">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Wallet
    </a>
    <div class="wpage-head">
        <div class="wpage-icon" style="background:rgba(34,197,94,0.12);color:#4ade80;">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div>
            <div class="wpage-title">My Sales</div>
            <div class="wpage-sub">All orders where you are the seller</div>
        </div>
    </div>

    <div class="wstat-mini">
        <div class="wstat-mini-card">
            <div class="wstat-mini-label">Gross Sales</div>
            <div class="wstat-mini-val" style="color:#4ade80;">₱<?= number_format((float)$sellerStats['gross_sales'], 2) ?></div>
        </div>
        <div class="wstat-mini-card">
            <div class="wstat-mini-label">Paid Orders</div>
            <div class="wstat-mini-val"><?= (int)$sellerStats['order_count'] ?></div>
        </div>
        <div class="wstat-mini-card">
            <div class="wstat-mini-label">Net Earnings</div>
            <div class="wstat-mini-val" style="color:#a5b4fc;">₱<?= number_format((float)$sellerStats['net_earnings'], 2) ?></div>
        </div>
    </div>

    <?php if (empty($sales)): ?>
    <div class="table-wrap">
        <div class="empty-page">
            <div class="empty-page-icon">₱</div>
            <div style="font-weight:700;margin-bottom:6px;">No sales yet</div>
            <div style="font-size:0.83rem;">Once buyers purchase your products, your sales will appear here.</div>
        </div>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Buyer</th>
                    <th>Gross</th>
                    <th>Net</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sales as $s): ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <?php if (!empty($s['product_image'])): ?>
                        <img class="thumb" src="<?= htmlspecialchars($s['product_image'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                        <?php else: ?>
                        <div class="thumb" style="display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:1.2rem;">📦</div>
                        <?php endif; ?>
                        <span style="font-weight:600;"><?= htmlspecialchars($s['product_title'] ?? 'Product #'.$s['id'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </td>
                <td style="color:var(--muted);"><?= htmlspecialchars($s['buyer_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td style="font-weight:700;">₱<?= number_format((float)$s['buyer_total_amount'], 2) ?></td>
                <td style="color:#4ade80;font-weight:700;">₱<?= number_format((float)$s['seller_net_amount'], 2) ?></td>
                <td><span class="status-badge status-<?= htmlspecialchars($s['payment_status'] ?? 'pending', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($s['payment_status'] ?? 'pending', ENT_QUOTES, 'UTF-8') ?></span></td>
                <td style="color:var(--muted);font-size:0.78rem;"><?= htmlspecialchars(substr((string)($s['created_at'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/parts/footer.php'; ?>
</body>
</html>
