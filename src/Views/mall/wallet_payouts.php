<?php
$payouts = $payouts ?? [];
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
.status-badge { display:inline-flex; align-items:center; padding:3px 9px; border-radius:20px; font-size:0.7rem; font-weight:700; text-transform:capitalize; }
.status-sent { background:rgba(34,197,94,0.12); color:#4ade80; }
.status-pending { background:rgba(245,158,11,0.12); color:#fbbf24; }
.status-processing { background:rgba(99,102,241,0.12); color:#a5b4fc; }
.status-failed { background:rgba(239,68,68,0.1); color:#f87171; }
.table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:18px; overflow:hidden; overflow-x:auto; }
.empty-page { text-align:center; padding:60px 20px; color:var(--muted); }
.empty-page-icon { font-size:3rem; margin-bottom:12px; opacity:0.3; }
.info-note { background:rgba(99,102,241,0.07); border:1px solid rgba(99,102,241,0.2); border-radius:12px; padding:12px 16px; font-size:0.8rem; color:#c7d2fe; margin-bottom:20px; line-height:1.55; }
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
        <div class="wpage-icon" style="background:rgba(239,68,68,0.10);color:#f87171;">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div>
            <div class="wpage-title">Pending Payouts</div>
            <div class="wpage-sub">Earnings automatically sent to your registered account on schedule</div>
        </div>
    </div>

    <div class="info-note">
        Payouts are automatically sent to your registered bank or e-wallet account on a scheduled basis. This complies with BSP regulations — no manual withdrawal is required or allowed.
        <a href="/wallet/payout-accounts" style="color:#93c5fd;font-weight:700;margin-left:8px;">Manage payout accounts →</a>
    </div>

    <div class="wstat-mini">
        <div class="wstat-mini-card">
            <div class="wstat-mini-label">Pending Amount</div>
            <div class="wstat-mini-val" style="color:#f87171;">₱<?= number_format((float)$sellerStats['pending_payout'], 2) ?></div>
        </div>
        <div class="wstat-mini-card">
            <div class="wstat-mini-label">Total Records</div>
            <div class="wstat-mini-val"><?= count($payouts) ?></div>
        </div>
    </div>

    <?php if (empty($payouts)): ?>
    <div class="table-wrap">
        <div class="empty-page">
            <div class="empty-page-icon">⏳</div>
            <div style="font-weight:700;margin-bottom:6px;">No payouts yet</div>
            <div style="font-size:0.83rem;">Your scheduled payouts will appear here once processed.</div>
        </div>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Amount</th>
                    <th>Type</th>
                    <th>Destination</th>
                    <th>Status</th>
                    <th>Scheduled</th>
                    <th>Sent</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($payouts as $p): ?>
            <tr>
                <td style="font-weight:700;color:var(--accent);">₱<?= number_format((float)$p['amount'], 2) ?></td>
                <td style="text-transform:capitalize;color:var(--muted);"><?= htmlspecialchars($p['source_type'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <?php if (!empty($p['institution_name'])): ?>
                    <div style="font-weight:600;font-size:0.83rem;"><?= htmlspecialchars($p['institution_name'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div style="color:var(--muted);font-size:0.73rem;"><?= htmlspecialchars($p['account_holder_name'] ?? '', ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($p['account_number'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                    <?php else: ?>
                    <span style="color:var(--muted);">—</span>
                    <?php endif; ?>
                </td>
                <td><span class="status-badge status-<?= htmlspecialchars($p['status'] ?? 'pending', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($p['status'] ?? 'pending', ENT_QUOTES, 'UTF-8') ?></span></td>
                <td style="color:var(--muted);font-size:0.78rem;"><?= $p['scheduled_at'] ? htmlspecialchars(substr($p['scheduled_at'], 0, 10), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                <td style="color:var(--muted);font-size:0.78rem;"><?= $p['sent_at'] ? htmlspecialchars(substr($p['sent_at'], 0, 10), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                <td style="color:var(--muted);font-size:0.78rem;"><?= htmlspecialchars(substr((string)($p['created_at'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
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
