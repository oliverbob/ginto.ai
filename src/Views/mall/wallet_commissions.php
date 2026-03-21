<?php
$commissions = $commissions ?? [];
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
.status-paid, .status-completed { background:rgba(34,197,94,0.12); color:#4ade80; }
.status-pending { background:rgba(245,158,11,0.12); color:#fbbf24; }
.status-cancelled { background:rgba(239,68,68,0.1); color:#f87171; }
.table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:18px; overflow:hidden; overflow-x:auto; }
.empty-page { text-align:center; padding:60px 20px; color:var(--muted); }
.empty-page-icon { font-size:3rem; margin-bottom:12px; opacity:0.3; }
.info-note { background:rgba(245,158,11,0.07); border:1px solid rgba(245,158,11,0.2); border-radius:12px; padding:12px 16px; font-size:0.8rem; color:#fbbf24; margin-bottom:20px; line-height:1.55; }
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
        <div class="wpage-icon" style="background:rgba(245,158,11,0.12);color:#fbbf24;">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div>
            <div class="wpage-title">Referral Rewards</div>
            <div class="wpage-sub">Rewards are issued separately and are not stored in your wallet</div>
        </div>
    </div>

    <div class="info-note">
        <strong>Off-platform rewards (Off-platform):</strong> These figures are for reference only. Rewards are issued separately and are not stored in your Ginto Mall wallet. Ginto Mall does not hold or process user funds.
    </div>

    <div class="wstat-mini">
        <div class="wstat-mini-card">
            <div class="wstat-mini-label">Total Commissions</div>
            <div class="wstat-mini-val" style="color:#fbbf24;">₱<?= number_format((float)$sellerStats['total_commissions'], 2) ?></div>
        </div>
        <div class="wstat-mini-card">
            <div class="wstat-mini-label">Entries</div>
            <div class="wstat-mini-val"><?= count($commissions) ?></div>
        </div>
    </div>

    <?php if (empty($commissions)): ?>
    <div class="table-wrap">
        <div class="empty-page">
            <div class="empty-page-icon">🤝</div>
            <div style="font-weight:700;margin-bottom:6px;">No commissions yet</div>
            <div style="font-size:0.83rem;">Invite others to ePower Mall and earn a 10% commission from their transactions.</div>
        </div>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>From</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Source ID</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($commissions as $c): ?>
            <tr>
                <td style="font-weight:600;"><?= htmlspecialchars($c['from_user_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td style="font-weight:700;color:#fbbf24;">₱<?= number_format((float)$c['amount'], 2) ?></td>
                <td><span class="status-badge status-<?= htmlspecialchars($c['status'] ?? 'pending', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($c['status'] ?? 'pending', ENT_QUOTES, 'UTF-8') ?></span></td>
                <td style="color:var(--muted);font-size:0.78rem;"><?= htmlspecialchars((string)($c['source_id'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="color:var(--muted);font-size:0.78rem;"><?= htmlspecialchars(substr((string)($c['created_at'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
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
