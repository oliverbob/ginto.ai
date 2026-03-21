<?php
$wallet = $wallet ?? [];
$walletTransactions = $wallet_transactions ?? [];
$sellerStats  = $seller_stats  ?? ['gross_sales'=>0,'net_earnings'=>0,'order_count'=>0,'total_commissions'=>0,'pending_payout'=>0];
$payoutAccount = $payout_account ?? null;
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<body>
<?php include __DIR__ . '/parts/header.php'; ?>

<style>
.gw-card {
    position: relative;
    overflow: hidden;
    border-radius: 28px;
    padding: 20px 16px 20px;
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 45%, #312e81 75%, #4338ca 100%);
    border: 1px solid rgba(99,102,241,0.35);
    box-shadow: 0 12px 52px rgba(67,56,202,0.35), 0 2px 8px rgba(0,0,0,0.5);
}
@media (min-width: 480px) {
    .gw-card {
        padding: 30px 28px 28px;
    }
}
.gw-card::before {
    content: '';
    position: absolute;
    top: -60px; right: -50px;
    width: 260px; height: 260px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(167,139,250,0.2) 0%, transparent 70%);
    pointer-events: none;
}
.gw-card::after {
    content: '';
    position: absolute;
    bottom: -80px; left: -50px;
    width: 300px; height: 240px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(250,204,21,0.1) 0%, transparent 70%);
    pointer-events: none;
}
.gw-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.7rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    font-weight: 800;
    color: rgba(199,210,254,0.75);
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 8px;
    padding: 4px 10px;
}
.gw-balance {
    font-size: 2.2rem;
    font-weight: 900;
    line-height: 1;
    color: #fff;
    letter-spacing: -0.04em;
    margin: 14px 0 4px;
}
@media (min-width: 480px) {
    .gw-balance {
        font-size: 2.8rem;
    }
}
.gw-balance-sub {
    font-size: 0.76rem;
    color: rgba(199,210,254,0.55);
    font-weight: 600;
    letter-spacing: 0.04em;
    margin-bottom: 22px;
}
.gw-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 16px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    border: none;
    letter-spacing: 0.01em;
    transition: all 0.18s ease;
    white-space: nowrap;
}
@media (min-width: 480px) {
    .gw-btn {
        font-size: 0.82rem;
    }
}
.gw-btn-ghost {
    background: rgba(255,255,255,0.09);
    color: rgba(255,255,255,0.88);
    border: 1px solid rgba(255,255,255,0.14);
}
.gw-btn-ghost:hover {
    background: rgba(255,255,255,0.16);
    border-color: rgba(255,255,255,0.24);
    color: #fff;
    transform: translateY(-1px);
}
.gw-btn-gold {
    background: linear-gradient(135deg, #facc15, #f59e0b);
    color: #0f172a;
    font-weight: 800;
    box-shadow: 0 4px 16px rgba(245,158,11,0.4);
}
.gw-btn-gold:hover {
    background: linear-gradient(135deg, #fde047, #facc15);
    transform: translateY(-1px);
    box-shadow: 0 6px 22px rgba(245,158,11,0.5);
}
.gw-divider {
    height: 1px;
    background: rgba(255,255,255,0.08);
    margin: 20px 0;
}
.topup-panel {
    border: 1px solid var(--border);
    background: var(--surface);
    border-radius: 24px;
    padding: 16px 18px;
    overflow: hidden;
}
@media (min-width: 480px) {
    .topup-panel {
        padding: 22px 24px;
    }
}
.topup-method-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 13px 8px;
    border-radius: 14px;
    background: var(--surface2);
    border: 2px solid var(--border);
    color: var(--text);
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.18s ease;
    line-height: 1.2;
}
.topup-method-btn .sub {
    font-size: 0.67rem;
    font-weight: 500;
    opacity: 0.6;
}
.topup-method-btn.is-selected {
    background: rgba(99,102,241,0.12);
    border-color: rgba(99,102,241,0.6);
    color: #a5b4fc;
}
.topup-method-btn:hover:not(.is-selected) {
    background: rgba(255,255,255,0.05);
    border-color: rgba(255,255,255,0.18);
}
.pf-input {
    width: 100%;
    padding: 11px 14px;
    border-radius: 12px;
    border: 1.5px solid rgba(255,255,255,0.16);
    background: #101a2f;
    color: #e9efff;
    font-size: 0.92rem;
    box-sizing: border-box;
    transition: border-color 0.18s, box-shadow 0.18s;
}
.pf-input::placeholder { color: rgba(233,239,255,0.56); }
.pf-input:focus { outline: none; border-color: rgba(214,180,75,0.75); box-shadow: 0 0 0 3px rgba(214,180,75,0.14); }
.pf-input:-webkit-autofill,
.pf-input:-webkit-autofill:hover,
.pf-input:-webkit-autofill:focus {
    -webkit-text-fill-color: #e9efff;
    -webkit-box-shadow: 0 0 0 1000px #101a2f inset;
    transition: background-color 5000s ease-in-out 0s;
}
.txn-row {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 12px;
    align-items: center;
    padding: 13px 15px;
    border-radius: 16px;
    background: var(--surface2);
    border: 1px solid var(--border);
    transition: background 0.15s;
}
.txn-row:hover { background: rgba(255,255,255,0.04); }
.txn-icon {
    width: 40px; height: 40px;
    border-radius: 13px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.txn-credit .txn-icon { background: rgba(34,197,94,0.12); color: #4ade80; }
.txn-debit .txn-icon { background: rgba(239,68,68,0.12); color: #f87171; }
.wallet-layout {
    max-width: 1200px;
    margin: 20px auto 40px;
    padding: 0 12px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    align-items: stretch;
}
.wallet-left {
    display: flex;
    flex-direction: column;
    gap: 16px;
    min-width: 0;
}
.wallet-right {
    border: 1px solid var(--border);
    background: var(--surface);
    border-radius: 24px;
    padding: 22px;
    min-width: 0;
}
.wallet-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}
@media (min-width: 480px) {
    .wallet-layout {
        margin: 30px auto 72px;
        padding: 0 18px;
    }
}
@media (max-width: 350px) {
    .wallet-actions {
        flex-direction: column;
        align-items: stretch;
    }
}
@media (min-width: 1024px) {
    .wallet-layout {
        flex-direction: row;
        align-items: flex-start;
    }
    .wallet-left {
        flex: 0 0 420px;
    }
    .wallet-right {
        flex: 1 1 auto;
    }
}
/* ── Earnings / Stats Row ── */
.wallet-stats-row {
    max-width: 1200px;
    margin: 16px auto 0;
    padding: 0 12px;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}
@media (min-width: 480px) {
    .wallet-stats-row {
        padding: 0 18px;
        gap: 12px;
    }
}
@media (min-width: 768px) {
    .wallet-stats-row {
        grid-template-columns: repeat(4, 1fr);
    }
}
.wstat {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 14px 16px 12px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    text-decoration: none;
    color: inherit;
    transition: border-color var(--trans), transform var(--trans), box-shadow var(--trans);
    cursor: pointer;
}
.wstat:hover {
    border-color: var(--accent);
    transform: translateY(-2px);
    box-shadow: 0 4px 18px rgba(99,102,241,0.15);
}
.wstat-icon {
    width: 34px; height: 34px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    margin-bottom: 6px;
    flex-shrink: 0;
}
.wstat-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-weight: 700;
    color: var(--muted);
    line-height: 1.2;
}
.wstat-value {
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--text);
    line-height: 1.1;
}
.wstat-sub {
    font-size: 0.7rem;
    color: var(--muted);
    margin-top: 2px;
}
/* ── Payout Account Panel ── */
.payout-panel {
    border: 1px solid var(--border);
    background: var(--surface);
    border-radius: 24px;
    padding: 16px 18px;
}
@media (min-width: 480px) {
    .payout-panel { padding: 22px 24px; }
}
.payout-type-btn {
    flex: 1;
    padding: 9px 12px;
    border-radius: 10px;
    background: var(--surface2);
    border: 2px solid var(--border);
    color: var(--text);
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
}
.payout-type-btn.active {
    background: rgba(59,130,246,0.12);
    border-color: rgba(59,130,246,0.55);
    color: #93c5fd;
}
.payout-type-btn:hover:not(.active) {
    background: rgba(255,255,255,0.05);
    border-color: rgba(255,255,255,0.18);
}
/* ── Wallet Top-up Confirmation Modal ── */
@keyframes wtIn {
    from { opacity:0; transform:scale(0.9) translateY(22px); }
    to   { opacity:1; transform:scale(1) translateY(0); }
}
.wt-overlay {
    position:fixed; inset:0; z-index:9999;
    display:flex; align-items:center; justify-content:center;
    background:rgba(0,0,0,0.76);
    backdrop-filter:blur(10px); -webkit-backdrop-filter:blur(10px);
    padding:16px;
}
.wt-card {
    background:linear-gradient(155deg,#0d1117 0%,#141a2b 55%,#1b2040 100%);
    border:1px solid rgba(255,255,255,0.1);
    border-radius:28px; width:100%; max-width:420px;
    box-shadow:0 40px 80px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.04);
    animation:wtIn 0.3s cubic-bezier(0.34,1.56,0.64,1) both;
    overflow:hidden;
    position:relative;
}
.wt-close-btn {
    position:absolute; top:14px; right:14px; z-index:2;
    width:32px; height:32px;
    border:none; background:none; padding:0;
    color:var(--muted); font-size:1.35rem; line-height:1;
    cursor:pointer; display:flex; align-items:center; justify-content:center;
    transition:color 0.15s;
}
.wt-close-btn:hover { color:var(--text); }
.wt-card.is-scrollable {
    max-height:min(92vh, 860px);
    overflow-y:auto;
    overscroll-behavior:contain;
    scrollbar-gutter:stable;
}
.wt-head { padding:20px 22px 10px; text-align:center; position:relative; }
.wt-glow {
    position:absolute; top:0; left:50%; transform:translateX(-50%);
    width:260px; height:130px;
    background:radial-gradient(ellipse at 50% 0%, rgba(214,180,75,0.18), transparent 70%);
    pointer-events:none;
}
.wt-method-icon {
    width:64px; height:64px; border-radius:20px;
    margin:0 auto 10px;
    display:flex; align-items:center; justify-content:center;
}
.wt-method-icon img {
    width:100%;
    height:100%;
    object-fit:contain;
}
.wt-sector { font-size:0.74rem; letter-spacing:0.14em; text-transform:uppercase; color:var(--muted); font-weight:700; margin-bottom:2px; }
.wt-method-name { font-size:1.45rem; font-weight:800; line-height:1.2; }
.wt-amount-box {
    margin:10px 28px; padding:12px 14px; border-radius:18px;
    background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.07);
    text-align:center;
}
.wt-amount-label { font-size:0.72rem; text-transform:uppercase; letter-spacing:0.12em; color:var(--muted); font-weight:700; margin-bottom:6px; }
.wt-amount {
    font-size:2rem; font-weight:900; line-height:1;
    background:linear-gradient(135deg,#f5d67b 0%,#d4af37 40%,#b8860b 100%);
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
}
.wt-breakdown { margin:0 28px 16px; padding:14px 16px; border-radius:16px; background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.06); }
.wt-breakdown-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; }
.wt-breakdown-row:last-child { margin-bottom:0; }
.wt-breakdown-label { font-size:0.87rem; color:var(--text); }
.wt-breakdown-value { font-size:0.87rem; font-weight:800; color:var(--text); }
.wt-qr-box { margin:0 28px 16px; text-align:center; }
.wt-qr-box img { max-width:200px; width:100%; border-radius:14px; border:1px solid var(--border); background:#fff; padding:10px; margin:0 auto 10px; display:block; }
.wt-actions { padding:0 28px 28px; display:flex; gap:12px; }
.wt-btn {
    flex:1; padding:14px 16px; border-radius:16px; font-size:0.9rem; font-weight:800;
    border:none; cursor:pointer; transition:all 0.18s ease;
}
.wt-btn-cancel {
    background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.12);
    color:var(--text);
}
.wt-btn-cancel:hover {
    background:rgba(255,255,255,0.14); border-color:rgba(255,255,255,0.2);
}
.wt-btn-confirm {
    background:linear-gradient(135deg,#f5d67b 0%,#d4af37 40%,#b8860b 100%);
    color:#1a1200; box-shadow:0 4px 16px rgba(214,180,75,0.3);
}
.wt-btn-confirm:hover {
    background:linear-gradient(135deg,#fde047 0%,#f5d67b 40%,#d4af37 100%);
    box-shadow:0 6px 22px rgba(214,180,75,0.4);
    transform:translateY(-1px);
}
.wt-btn:disabled {
    opacity:0.6; cursor:not-allowed; transform:none !important;
}
@media (max-width: 640px) {
    .wt-overlay {
        align-items:flex-end;
        padding:0;
        overflow:auto;
    }
    .wt-card {
        max-width:100%;
        border-radius:22px 22px 0 0;
        max-height:100vh;
        min-height:60vh;
        overflow-y:auto;
        padding-bottom:max(10px, env(safe-area-inset-bottom));
    }
    .wt-head { padding:10px 12px 6px; }
    .wt-glow {
        width:180px;
        height:86px;
    }
    .wt-method-icon {
        width:36px;
        height:36px;
        border-radius:12px;
        margin:0 auto 6px;
    }
    .wt-sector {
        font-size:0.62rem;
        margin-bottom:1px;
    }
    .wt-method-name {
        font-size:0.9rem;
    }
    .wt-amount-box,
    .wt-breakdown,
    .wt-qr-box { margin-left:16px; margin-right:16px; }
    .wt-actions { padding:0 16px 20px; }
}
/* Institution autocomplete */
.inst-autocomplete { position: relative; }
.inst-dropdown {
    position: absolute; top: calc(100% + 3px); left: 0; right: 0;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 12px; z-index: 1000; max-height: 230px;
    overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.4);
    display: none;
}
.inst-option {
    padding: 9px 14px; cursor: pointer; font-size: 0.84rem; color: var(--text);
}
.inst-option:hover, .inst-option.ac-focused { background: rgba(99,102,241,0.14); }
.inst-group-label {
    padding: 7px 14px 3px; font-size: 0.67rem; font-weight: 800;
    color: var(--muted); text-transform: uppercase; letter-spacing: 0.09em;
    pointer-events: none; border-top: 1px solid var(--border); margin-top: 4px;
}
.inst-group-label:first-child { margin-top: 0; border-top: none; }
.inst-no-results { padding: 12px 14px; font-size: 0.82rem; color: var(--muted); text-align: center; }
</style>

<section class="wallet-stats-row">
    <!-- Sales -->
    <a class="wstat" href="/wallet/sales">
        <div class="wstat-icon" style="background:rgba(34,197,94,0.12);color:#4ade80;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="wstat-label">Sales</div>
        <div class="wstat-value">₱<?= number_format((float)$sellerStats['gross_sales'], 2) ?></div>
        <div class="wstat-sub"><?= (int)$sellerStats['order_count'] ?> paid order<?= (int)$sellerStats['order_count'] !== 1 ? 's' : '' ?></div>
    </a>
    <!-- Referral Rewards -->
    <a class="wstat" href="/wallet/commissions">
        <div class="wstat-icon" style="background:rgba(245,158,11,0.12);color:#fbbf24;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="wstat-label">Referral Rewards <small style="font-size:0.65em;color:var(--muted);font-weight:500;">(Off-platform)</small></div>
        <div class="wstat-value">₱<?= number_format((float)$sellerStats['total_commissions'], 2) ?></div>
        <div class="wstat-sub">Issued separately, not in wallet</div>
    </a>
    <!-- Estimated Revenue -->
    <a class="wstat" href="/wallet/earnings">
        <div class="wstat-icon" style="background:rgba(99,102,241,0.12);color:#a5b4fc;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
        </div>
        <div class="wstat-label">Estimated Revenue</div>
        <div class="wstat-value">₱<?= number_format((float)$sellerStats['net_earnings'], 2) ?></div>
        <div class="wstat-sub">For reporting purposes only</div>
    </a>
    <!-- Pending Settlements -->
    <a class="wstat" href="/wallet/payouts">
        <div class="wstat-icon" style="background:rgba(239,68,68,0.10);color:#f87171;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="wstat-label">Pending Settlements</div>
        <div class="wstat-value">₱<?= number_format((float)$sellerStats['pending_payout'], 2) ?></div>
        <div class="wstat-sub">Processed by your payment provider</div>
    </a>
</section>

<section class="wallet-layout">
    <div class="wallet-left">

        <!-- Premium Wallet Card -->
        <div class="gw-card">
            <div style="position:relative;z-index:1;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <span class="gw-chip">
                        <svg width="8" height="8" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4" fill="#a5b4fc" opacity="0.7"/></svg>
                        Platform Credits
                    </span>
                    <span style="font-size:0.68rem;color:rgba(239,68,68,0.55);font-weight:600;letter-spacing:0.06em;">NON-WITHDRAWABLE</span>
                </div>
                <div class="gw-balance">₱<?= number_format((float)($wallet['balance'] ?? 0), 2) ?></div>
                <div class="gw-balance-sub">Can only be used for Ginto Mall purchases</div>
                <div class="gw-divider"></div>
                <div class="wallet-actions">
                    <a href="/wallet/storefront" class="gw-btn gw-btn-ghost">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        My Storefront
                    </a>
                    <a href="/mall/checkout" class="gw-btn gw-btn-ghost">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        Checkout
                    </a>
                    <a href="/mall/orders" class="gw-btn gw-btn-ghost">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        My Orders
                    </a>
                    <button type="button" class="gw-btn gw-btn-gold" id="toggleTopupBtn">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M12 5v14M5 12h14"/></svg>
                        Top Up
                    </button>
                </div>
            </div>
        </div>

        <!-- Top Up Panel (toggle) -->
        <div class="topup-panel" id="topupPanel" style="display:none;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
                <div>
                    <h2 style="margin:0 0 2px;font-size:1rem;font-weight:800;">Add Funds</h2>
                    <p style="margin:0;font-size:0.76rem;color:var(--muted);">Choose amount and payment method.</p>
                </div>
                <button type="button" id="closeTopupBtn" style="display:flex;align-items:center;justify-content:center;width:30px;height:30px;background:var(--surface2);border:1px solid var(--border);border-radius:9px;color:var(--muted);cursor:pointer;font-size:0.85rem;line-height:1;">✕</button>
            </div>
            <div style="margin-bottom:14px;padding:11px 12px;border-radius:12px;background:rgba(214,180,75,0.08);border:1px solid rgba(214,180,75,0.24);font-size:0.78rem;color:#f3ddb0;line-height:1.55;">
                Ginto Pay top-ups (QR or card) include a fixed fee of ₱25.00 per transaction. PayPal top-ups include ₱25.00 plus a 5% service fee. Platform credits are non-withdrawable and can only be used for Ginto Mall purchases. This may change depending on bank or payment processor, bank or international card type.
            </div>
            <div style="display:flex;flex-direction:column;gap:14px;">
                <label style="display:flex;flex-direction:column;gap:6px;">
                    <span style="font-size:0.78rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.06em;">Amount (₱)</span>
                    <input id="walletTopupAmount" type="number" min="1" step="0.01" class="pf-input" placeholder="500.00">
                </label>
                <div>
                    <div style="font-size:0.78rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:9px;">Payment Method</div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(80px,1fr));gap:8px;" id="payment-methods-grid">
                        <style>
                            @media (min-width: 480px) {
                                #payment-methods-grid {
                                    grid-template-columns: repeat(3, minmax(0,1fr));
                                }
                            }
                        </style>
                        <button type="button" class="topup-method-btn is-selected wallet-method-card" data-method="ginto_pay_qr">
                            GCash / QR Ph / Maya
                            <span class="sub">Ginto Pay</span>
                        </button>
                        <button type="button" class="topup-method-btn wallet-method-card" data-method="ginto_pay_card">
                            Credit / Debit
                            <span class="sub">Ginto Pay</span>
                        </button>
                        <button type="button" class="topup-method-btn wallet-method-card" data-method="paypal">
                            PayPal
                            <span class="sub">International</span>
                        </button>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(70px,1fr));gap:8px;padding:10px 12px;border-radius:12px;background:rgba(255,255,255,0.04);border:1px solid var(--border);font-size:0.76rem;" id="breakdown-grid">
                    <style>
                        @media (min-width: 480px) {
                            #breakdown-grid {
                                grid-template-columns: repeat(3, minmax(0,1fr));
                            }
                        }
                    </style>
                    <div>
                        <div style="color:var(--muted);">You pay</div>
                        <div id="topupGross" style="font-weight:800;color:var(--text);margin-top:3px;">₱0.00</div>
                    </div>
                    <div>
                        <div style="color:var(--muted);">Fee</div>
                        <div id="topupFee" style="font-weight:800;color:#fca5a5;margin-top:3px;">₱0.00</div>
                    </div>
                    <div>
                        <div style="color:var(--muted);">Wallet credit</div>
                        <div id="topupCredit" style="font-weight:800;color:#86efac;margin-top:3px;">₱0.00</div>
                    </div>
                </div>
                <div id="walletTopupError" style="display:none;padding:12px 14px;border-radius:13px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);color:#fecaca;font-size:0.85rem;"></div>
                <div id="walletTopupInfo" style="display:none;padding:12px 14px;border-radius:13px;background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.3);color:#c7d2fe;font-size:0.85rem;"></div>
                <div id="walletTopupQr" style="display:none;text-align:center;padding:20px;border-radius:18px;border:1px dashed var(--border);background:var(--surface2);"></div>
                <button type="button" id="walletTopupBtn" class="btn btn-primary" style="border-radius:14px;font-size:0.9rem;font-weight:800;padding:12px 18px;">Confirm Top Up</button>
            </div>
        </div>

        <!-- Settlement Account Panel -->
        <div class="payout-panel">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                <div>
                    <h2 style="margin:0 0 2px;font-size:1rem;font-weight:800;">Settlement Account (External)</h2>
                    <p style="margin:0;font-size:0.76rem;color:var(--muted);">Where your payment provider sends your sales proceeds.</p>
                </div>
                <?php if ($payoutAccount): ?>
                <span style="font-size:0.65rem;padding:3px 8px;border-radius:6px;background:rgba(34,197,94,0.12);color:#4ade80;font-weight:700;border:1px solid rgba(34,197,94,0.2);">SAVED</span>
                <?php endif; ?>
            </div>
            <div style="margin:12px 0;padding:10px 12px;border-radius:10px;background:rgba(59,130,246,0.07);border:1px solid rgba(59,130,246,0.18);font-size:0.76rem;color:#93c5fd;line-height:1.55;">
                Ginto Mall does not hold or process user funds. All payments and settlements are handled by licensed payment providers.
            </div>
            <div style="display:flex;gap:8px;margin-bottom:16px;" id="payoutTypeRow">
                <button type="button" class="payout-type-btn active" data-type="bank" id="ptypeBank">🏦 Bank Account</button>
                <button type="button" class="payout-type-btn" data-type="ewallet" id="ptypeEwallet">📱 E-Wallet</button>
            </div>
            <div style="display:flex;flex-direction:column;gap:12px;">
                <label style="display:flex;flex-direction:column;gap:6px;">
                    <span style="font-size:0.78rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.06em;">Institution</span>
                    <div class="inst-autocomplete">
                        <input type="text" id="payoutInstitution_search" class="pf-input" placeholder="Search institution…" autocomplete="off" spellcheck="false">
                        <input type="hidden" id="payoutInstitution">
                        <div class="inst-dropdown" id="payoutInstitution_list"></div>
                    </div>
                </label>
                <label style="display:flex;flex-direction:column;gap:6px;">
                    <span style="font-size:0.78rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.06em;">Account Holder Name</span>
                    <input id="payoutHolderName" type="text" class="pf-input" placeholder="Full name on account" value="<?= htmlspecialchars($payoutAccount['account_holder_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <label style="display:flex;flex-direction:column;gap:6px;">
                    <span style="font-size:0.78rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.06em;">Account Number / Mobile Number</span>
                    <input id="payoutAccountNumber" type="text" class="pf-input" placeholder="e.g. 09XX XXX XXXX or account number" value="<?= htmlspecialchars($payoutAccount['account_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <div id="payoutSaveMsg" style="display:none;padding:10px 12px;border-radius:10px;font-size:0.82rem;"></div>
                <button type="button" id="payoutSaveBtn" class="btn btn-primary" style="border-radius:14px;font-size:0.88rem;font-weight:800;padding:11px 18px;">Save Settlement Account</button>
            </div>
        </div>
    </div>

    <!-- Transaction History -->
    <div class="wallet-right">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
            <div>
                <h2 style="margin:0 0 2px;font-size:1rem;font-weight:800;">Wallet Ledger</h2>
                <p style="margin:0;font-size:0.77rem;color:var(--muted);">All wallet activity</p>
            </div>
            <span style="font-size:0.73rem;color:var(--muted);background:var(--surface2);padding:5px 11px;border-radius:9px;border:1px solid var(--border);font-weight:600;">Newest first</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;">
            <?php if (!empty($walletTransactions)): ?>
                <?php foreach ($walletTransactions as $row): ?>
                <?php $isCredit = ($row['direction'] ?? '') === 'credit'; ?>
                <div class="txn-row txn-<?= $isCredit ? 'credit' : 'debit' ?>">
                    <div class="txn-icon"><?= $isCredit ? '↑' : '↓' ?></div>
                    <div>
                        <div style="font-size:0.88rem;font-weight:700;"><?= htmlspecialchars($row['description'] ?? ucfirst((string)($row['type'] ?? 'Transaction')), ENT_QUOTES, 'UTF-8') ?></div>
                        <div style="font-size:0.73rem;color:var(--muted);margin-top:3px;"><?= htmlspecialchars($row['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?> · <span style="text-transform:capitalize;"><?= htmlspecialchars($row['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:0.92rem;font-weight:800;color:<?= $isCredit ? '#4ade80' : '#f87171' ?>;"><?= $isCredit ? '+' : '-' ?>₱<?= number_format((float)($row['amount'] ?? 0), 2) ?></div>
                        <div style="font-size:0.72rem;color:var(--muted);margin-top:2px;">bal ₱<?= number_format((float)($row['balance_after'] ?? 0), 2) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div style="padding:48px 18px;border-radius:18px;background:var(--surface2);border:1px dashed var(--border);text-align:center;">
                <div style="font-size:2.2rem;margin-bottom:10px;opacity:0.3;">₱</div>
                <div style="font-size:0.88rem;color:var(--muted);font-weight:600;">No transactions yet</div>
                <div style="font-size:0.77rem;color:var(--muted);margin-top:4px;opacity:0.7;">Top up your wallet to get started.</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ── Wallet Top-up Confirmation Modal ── -->
<div id="wtModal" class="wt-overlay" style="display:none;" aria-modal="true" role="dialog" aria-label="Wallet top-up confirmation">
    <div class="wt-card">
        <button type="button" class="wt-close-btn" id="wtCloseBtn" aria-label="Close modal">&times;</button>
        <div class="wt-head">
            <div class="wt-glow"></div>
            <div class="wt-method-icon" id="wtMethodIcon"></div>
            <div class="wt-sector">Wallet Top-up</div>
            <div class="wt-method-name" id="wtMethodName"></div>
        </div>
        <div class="wt-amount-box">
            <div class="wt-amount-label">Total Amount</div>
            <div class="wt-amount" id="wtAmount"></div>
        </div>
        <div class="wt-breakdown">
            <div class="wt-breakdown-row">
                <span class="wt-breakdown-label">Wallet Credit</span>
                <span class="wt-breakdown-value" id="wtCredit"></span>
            </div>
            <div class="wt-breakdown-row">
                <span class="wt-breakdown-label">Service Fee</span>
                <span class="wt-breakdown-value" id="wtFee"></span>
            </div>
        </div>
        <div class="wt-qr-box" id="wtQrBox" style="display:none;"></div>
        <div class="wt-actions">
            <button type="button" class="wt-btn wt-btn-cancel" id="wtCancelBtn">Cancel</button>
            <button type="button" class="wt-btn wt-btn-confirm" id="wtConfirmBtn">Confirm Top-up</button>
        </div>
    </div>
</div>

<script>
(function () {
    const csrfToken = <?= json_encode($csrf_token ?? '') ?>;
    const amountInput = document.getElementById('walletTopupAmount');
    const topupBtn = document.getElementById('walletTopupBtn');
    const errorBox = document.getElementById('walletTopupError');
    const infoBox = document.getElementById('walletTopupInfo');
    const qrWrap = document.getElementById('walletTopupQr');
    const topupGrossEl = document.getElementById('topupGross');
    const topupFeeEl = document.getElementById('topupFee');
    const topupCreditEl = document.getElementById('topupCredit');
    const topupPanel = document.getElementById('topupPanel');
    const toggleTopupBtn = document.getElementById('toggleTopupBtn');
    const closeTopupBtn = document.getElementById('closeTopupBtn');
    const methodButtons = Array.from(document.querySelectorAll('.wallet-method-card'));
    const wtModal = document.getElementById('wtModal');
    const wtCloseBtn = document.getElementById('wtCloseBtn');
    const wtCancelBtn = document.getElementById('wtCancelBtn');
    const wtConfirmBtn = document.getElementById('wtConfirmBtn');
    const wtMethodIcon = document.getElementById('wtMethodIcon');
    const wtMethodName = document.getElementById('wtMethodName');
    const wtAmount = document.getElementById('wtAmount');
    const wtCredit = document.getElementById('wtCredit');
    const wtFee = document.getElementById('wtFee');
    const wtQrBox = document.getElementById('wtQrBox');
    let selectedMethod = 'ginto_pay_qr';
    let currentSessionRef = '';
    let currentCreate = null;
    let currentQr = null;
    let statusPoll = null;

    const methodMeta = {
        ginto_pay_qr: {
            confirmLabel: 'Generate QR & Pay',
            helper: 'Use your GCash / Maya / QR PH app to scan and complete the payment.',
            name: 'QR Code Payment',
            iconBg: 'linear-gradient(135deg, #f59e0b, #d97706)',
            iconHtml: '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="5" height="5"/><rect x="16" y="3" width="5" height="5"/><rect x="3" y="16" width="5" height="5"/><path d="M21 16h-3a2 2 0 0 0-2 2v3"/><path d="M21 21v.01"/><path d="M12 7v3a2 2 0 0 1-2 2H7"/><path d="M3 12h.01"/><path d="M12 3h.01"/><path d="M12 16v.01"/><path d="M16 12h1"/><path d="M21 12v.01"/><path d="M12 21v-1"/></svg>',
        },
        ginto_pay_card: {
            confirmLabel: 'Pay with Card',
            helper: 'You will be redirected to PayMongo secure card checkout.',
            name: 'Credit/Debit Card',
            iconBg: 'linear-gradient(135deg, #3b82f6, #1d4ed8)',
            iconHtml: '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>',
        },
        paypal: {
            confirmLabel: 'Continue to PayPal',
            helper: 'A PayPal order will be created for this wallet top-up.',
            name: 'PayPal',
            iconBg: 'linear-gradient(135deg, #0070ba, #003087)',
            iconHtml: '<svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor"><path d="M7.076 21.337H2.47a.641.641 0 0 1-.633-.74L4.944.901C5.026.382 5.474 0 5.998 0h7.46c2.57 0 4.578.543 5.622 1.562 1.035.992 1.449 2.467 1.149 4.162a6.681 6.681 0 0 1-.672 2.806 6.212 6.212 0 0 1-1.752 2.04c-1.466 1.001-3.586 1.52-6.198 1.52H9.66c-.51 0-.995.196-1.355.52a1.63 1.63 0 0 0-.386.535l-.453 2.27a.641.641 0 0 1-.633.522H7.076zm1.913-10.223h3.137c1.424 0 2.686-.28 3.595-.84.896-.553 1.297-1.478 1.297-2.733 0-1.332-.44-2.197-1.325-2.576-.896-.387-2.176-.58-3.694-.58H9.66c-.51 0-.995.196-1.355.52a1.63 1.63 0 0 0-.386.535l-.453 2.27a.641.641 0 0 1-.633.522zm-.98 6.676h2.324c.51 0 .995-.196 1.355-.52.36-.324.542-.806.542-1.36 0-.56-.182-1.042-.542-1.366-.36-.324-.845-.52-1.355-.52H7.99a.641.641 0 0 1-.633-.522l-.453-2.27a1.63 1.63 0 0 0-.386-.535c-.36-.324-.845-.52-1.355-.52H2.47a.641.641 0 0 1-.633-.74L4.944.901C5.026.382 5.474 0 5.998 0h7.46c2.57 0 4.578.543 5.622 1.562 1.035.992 1.449 2.467 1.149 4.162a6.681 6.681 0 0 1-.672 2.806 6.212 6.212 0 0 1-1.752 2.04c-1.466 1.001-3.586 1.52-6.198 1.52H9.66c-.51 0-.995.196-1.355.52a1.63 1.63 0 0 0-.386.535l-.453 2.27a.641.641 0 0 1-.633.522H7.076z"/></svg>',
        },
    };

    toggleTopupBtn.addEventListener('click', function () {
        const isOpen = topupPanel.style.display !== 'none';
        topupPanel.style.display = isOpen ? 'none' : 'block';
        if (!isOpen) {
            setTimeout(function () { topupPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }, 50);
        }
    });

    closeTopupBtn.addEventListener('click', function () {
        topupPanel.style.display = 'none';
    });

    function setError(message) {
        errorBox.style.display = message ? 'block' : 'none';
        errorBox.textContent = message || '';
    }

    function setInfo(message) {
        infoBox.style.display = message ? 'block' : 'none';
        infoBox.textContent = message || '';
    }

    function formatPrice(value) {
        return '₱' + Number(value || 0).toFixed(2);
    }

    function isPayMongoMethod(method) {
        return method === 'ginto_pay_qr' || method === 'ginto_pay_card';
    }

    function isPayPalMethod(method) {
        return method === 'paypal';
    }

    async function openModal() {
        const meta = methodMeta[selectedMethod] || {};
        const credit = Math.max(0, Number(amountInput.value || 0));
        let fee = 0;
        if (credit > 0) {
            if (isPayMongoMethod(selectedMethod)) {
                fee = 25;
            } else if (isPayPalMethod(selectedMethod)) {
                fee = 25 + (credit * 0.05);
            }
        }
        const gross = credit + fee;

        wtMethodIcon.style.background = meta.iconBg || '';
        wtMethodIcon.innerHTML = meta.iconHtml || '';
        wtMethodName.textContent = meta.name || selectedMethod;
        wtAmount.textContent = formatPrice(gross);
        wtCredit.textContent = formatPrice(credit);
        wtFee.textContent = formatPrice(fee);
        wtQrBox.style.display = 'none';
        wtQrBox.innerHTML = '';
        // For QR method, change modal actions: Confirm -> Download QR, Cancel -> Reload QR
        if (selectedMethod === 'ginto_pay_qr') {
            wtConfirmBtn.textContent = 'Download QR';
            wtCancelBtn.textContent = 'Reload QR';
            wtConfirmBtn.disabled = false;
        } else {
            wtConfirmBtn.textContent = meta.confirmLabel || 'Confirm Top-up';
            wtCancelBtn.textContent = 'Cancel';
            wtConfirmBtn.disabled = false;
        }
        wtModal.style.display = 'flex';

        // Set modal action handlers based on selected method
        if (selectedMethod === 'ginto_pay_qr') {
            wtQrBox.style.display = 'block';
            wtQrBox.innerHTML = ''
                + '<div style="display:flex;flex-direction:column;align-items:center;gap:10px;padding:12px 0;">'
                + '<div id="wtQrStatus" style="font-size:0.92rem;color:var(--muted);margin-top:8px;">Generating QR…</div>'
                + '<div id="wtQrPreview" style="margin-top:10px;width:100%;text-align:center;"></div>'
                + '</div>';

            // Auto-generate QR when modal opens if a valid amount is present
            if (credit > 0) {
                regenerateQr().catch(function () {});
            }
            wtConfirmBtn.onclick = downloadQr;
            wtCancelBtn.onclick = regenerateQr;
        } else {
            wtConfirmBtn.onclick = startTopup;
            wtCancelBtn.onclick = closeModal;
        }
    }

    async function regenerateQr() {
        const credit = Math.max(0, Number(amountInput.value || 0));
        const statusEl = document.getElementById('wtQrStatus');
        const previewEl = document.getElementById('wtQrPreview');
        if (credit <= 0) {
            setError('Please enter a top-up amount greater than ₱0.');
            return;
        }
        setError('');
        statusEl.textContent = 'Generating QR…';
        try {
            // create session
            const create = await api('/api/mall/wallet/topup/create', { amount: credit, payment_method: selectedMethod });
            currentSessionRef = create.session_ref;
            currentCreate = create;
            // fetch qr
            const qr = await api('/api/mall/checkout/paymongo-qr-init', { session_ref: currentSessionRef });
            currentQr = qr;
            if (qr.qr_image) {
                previewEl.innerHTML = '<img src="' + qr.qr_image + '" alt="Wallet QR" style="max-width:260px;width:100%;border-radius:12px;border:1px solid var(--border);background:#fff;padding:8px;">';
                statusEl.textContent = 'QR ready. Scan the code with your mobile app.';
            } else if (qr.qr_string) {
                previewEl.innerHTML = '<div style="color:var(--muted);">' + qr.qr_string + '</div>';
                statusEl.textContent = 'QR string ready. Use your QR app to scan.';
            } else {
                statusEl.textContent = 'QR not available.';
            }
            setInfo('You pay ' + formatPrice(create.amount) + ', fee ' + formatPrice(create.fee) + ', wallet credit ' + formatPrice(create.credit_amount) + '.');
            beginStatusPoll();
        } catch (err) {
            statusEl.textContent = '';
            setError(err.message || 'Failed to generate QR code.');
        }
    }

    async function downloadQr() {
        const credit = Math.max(0, Number(amountInput.value || 0));
        const statusEl = document.getElementById('wtQrStatus');
        const previewEl = document.getElementById('wtQrPreview');
        if (currentQr && currentQr.qr_image) {
            // download the current QR image via fetch to get blob (handles CORS safer)
            try {
                const res = await fetch(currentQr.qr_image);
                const blob = await res.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'ginto-wallet-qr.png';
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
                statusEl.textContent = 'Download started. Scan or check your downloads.';
            } catch (e) {
                statusEl.textContent = 'Download failed. You can scan the QR preview.';
            }
        } else {
            // no current QR; generate then download
            await regenerateQr();
            if (currentQr && currentQr.qr_image) {
                try {
                    const res = await fetch(currentQr.qr_image);
                    const blob = await res.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'ginto-wallet-qr.png';
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    window.URL.revokeObjectURL(url);
                    statusEl.textContent = 'Download started. Scan or check your downloads.';
                } catch (e) {
                    statusEl.textContent = 'Download failed. You can scan the QR preview.';
                }
            }
        }
    }

    function closeModal() {
        wtModal.style.display = 'none';
        wtQrBox.innerHTML = '';
        if (statusPoll) { window.clearInterval(statusPoll); statusPoll = null; }
    }

    function updateTopupBreakdown() {
        const credit = Math.max(0, Number(amountInput.value || 0));
        let fee = 0;
        if (credit > 0) {
            if (isPayMongoMethod(selectedMethod)) {
                fee = 25;
            } else if (isPayPalMethod(selectedMethod)) {
                fee = 25 + (credit * 0.05);
            }
        }
        const gross = credit + fee;
        topupGrossEl.textContent = formatPrice(gross);
        topupFeeEl.textContent = formatPrice(fee);
        topupCreditEl.textContent = formatPrice(credit);
    }

    function applySelectedMethodUI() {
        const meta = methodMeta[selectedMethod] || {};
        topupBtn.textContent = meta.confirmLabel || 'Confirm Top Up';

        methodButtons.forEach(function (button) {
            button.classList.toggle('is-selected', button.dataset.method === selectedMethod);
        });

        setError('');
        qrWrap.style.display = 'none';
        qrWrap.innerHTML = '';
        if (meta.helper) {
            setInfo(meta.helper);
        } else {
            setInfo('');
        }
        updateTopupBreakdown();
    }

    function beginStatusPoll() {
        if (!currentSessionRef) return;
        if (statusPoll) window.clearInterval(statusPoll);
        statusPoll = window.setInterval(async function () {
            try {
                const response = await fetch('/api/mall/checkout/status?session_ref=' + encodeURIComponent(currentSessionRef), {
                    headers: { 'Accept': 'application/json' },
                });
                const json = await response.json();
                if (json.status === 'completed') {
                    window.clearInterval(statusPoll);
                    setInfo('Top-up confirmed and posted to your wallet. Updating balance...');
                    // Try to update top-bar wallet balance immediately using the current session's credited amount
                    try {
                        const creditAdded = (currentCreate && Number(currentCreate.credit_amount)) ? Number(currentCreate.credit_amount) : 0;
                        if (creditAdded > 0) {
                            const elems = document.querySelectorAll('.wallet-balance-text');
                            elems.forEach(function (el) {
                                const cur = parseFloat(String(el.textContent || '').replace(/[^0-9.-]+/g, '')) || 0;
                                const updated = Math.round((cur + creditAdded) * 100) / 100;
                                el.textContent = updated.toFixed(2);
                            });
                            const walletBtn = document.querySelector('.action-btn.wallet-btn');
                            if (walletBtn) {
                                const first = document.querySelector('.wallet-balance-text');
                                const shown = first ? first.textContent : (creditAdded).toFixed(2);
                                walletBtn.title = 'Ginto Wallet balance: ₱' + shown;
                            }
                        }
                    } catch (e) {}
                    // Refresh ledger/transactions shortly to ensure server state is reflected
                    setTimeout(function(){ window.location.reload(); }, 1200);
                }
            } catch (_) {}
        }, 5000);
    }

    async function api(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify(Object.assign({ csrf_token: csrfToken }, body || {})),
        });
        const json = await response.json();
        if (!response.ok || json.success === false) {
            throw new Error(json.message || 'Request failed.');
        }
        return json;
    }

    async function startTopup() {
        setError('');
        setInfo('');
        qrWrap.style.display = 'none';
        topupBtn.disabled = true;
        try {
            const amount = Number(amountInput.value || 0);
            let create = null;
            if (!currentSessionRef) {
                create = await api('/api/mall/wallet/topup/create', { amount: amount, payment_method: selectedMethod });
                currentSessionRef = create.session_ref;
                currentCreate = create;
            } else {
                create = currentCreate;
            }

            if (selectedMethod === 'ginto_pay_qr') {
                // If modal already created a session and QR, reuse it; otherwise initialize QR
                if (currentSessionRef && wtQrBox && wtQrBox.innerHTML.trim()) {
                    setInfo('Scan the QR code and keep this page open. You pay ' + formatPrice(create.amount) + ', fee ' + formatPrice(create.fee) + ', wallet credit ' + formatPrice(create.credit_amount) + '.');
                    beginStatusPoll();
                } else {
                    const qr = await api('/api/mall/checkout/paymongo-qr-init', { session_ref: currentSessionRef });
                    try {
                        wtQrBox.style.display = 'block';
                        wtQrBox.innerHTML = '<h3 style="margin:0 0 10px;font-size:1rem;font-weight:800;">Scan to top up</h3>'
                            + (qr.qr_image ? '<img src="' + qr.qr_image + '" alt="Wallet top-up QR" style="max-width:320px;width:min(100%,320px);border-radius:16px;border:1px solid var(--border);background:#fff;padding:12px;">' : '<div style="color:var(--muted);font-size:0.84rem;">' + (qr.qr_string || 'QR code ready.') + '</div>');
                    } catch (_) {
                        qrWrap.style.display = 'block';
                        qrWrap.innerHTML = '<h3 style="margin:0 0 10px;font-size:1rem;font-weight:800;">Scan to top up</h3>'
                            + (qr.qr_image ? '<img src="' + qr.qr_image + '" alt="Wallet top-up QR" style="max-width:320px;width:min(100%,320px);border-radius:16px;border:1px solid var(--border);background:#fff;padding:12px;">' : '<div style="color:var(--muted);font-size:0.84rem;">' + (qr.qr_string || 'QR code ready.') + '</div>');
                    }
                    setInfo('Scan the QR code and keep this page open. You pay ' + formatPrice(create.amount) + ', fee ' + formatPrice(create.fee) + ', wallet credit ' + formatPrice(create.credit_amount) + '.');
                    beginStatusPoll();
                }
            } else if (selectedMethod === 'ginto_pay_card') {
                const card = await api('/api/mall/checkout/paymongo-card-init', { session_ref: currentSessionRef });
                setInfo('Redirecting to PayMongo secure card page. You pay ' + formatPrice(create.amount) + ', fee ' + formatPrice(create.fee) + ', wallet credit ' + formatPrice(create.credit_amount) + '.');
                window.location.href = card.redirect_url;
                return;
            } else {
                const paypal = await api('/api/mall/checkout/paypal-order', { session_ref: currentSessionRef });
                setInfo('PayPal order created: ' + paypal.paypal_order_id + '. Use the checkout page if you want to complete it there.');
            }
        } catch (error) {
            setError(error.message);
        } finally {
            topupBtn.disabled = false;
        }
    }

    methodButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            selectedMethod = button.dataset.method;
            applySelectedMethodUI();
            openModal();
        });
    });

    amountInput.addEventListener('input', updateTopupBreakdown);
    applySelectedMethodUI();

    wtCloseBtn.addEventListener('click', closeModal);

    topupBtn.addEventListener('click', startTopup);
})();
</script>

<script>
// ── Payout Account ──────────────────────────────────────────────────────────
(function () {
    const csrfToken   = <?= json_encode($csrf_token ?? '') ?>;
    const savedType   = <?= json_encode($payoutAccount['account_type'] ?? 'bank') ?>;
    const savedInst   = <?= json_encode($payoutAccount['institution_name'] ?? '') ?>;

    let currentType = savedType || 'bank';

    const ptypeBank    = document.getElementById('ptypeBank');
    const ptypeEwallet = document.getElementById('ptypeEwallet');
    const saveBtn      = document.getElementById('payoutSaveBtn');
    const saveMsg      = document.getElementById('payoutSaveMsg');

    // ── Institution autocomplete data ────────────────────────────────────────
    const INST = {
        bank: {
            'Universal / Commercial Banks': [
                'Asia United Bank Corporation','Bank of China (HK) Limited \u2013 Manila Branch',
                'Bank of Commerce','Bank of the Philippine Islands (BPI)','BDO Unibank, Inc.',
                'China Banking Corporation (China Bank)','CIMB Bank Philippines, Inc.',
                'CTBC Bank (Philippines) Corporation','Development Bank of the Philippines (DBP)',
                'East West Banking Corporation','Land Bank of the Philippines',
                'Maybank Philippines, Inc.','Metropolitan Bank and Trust Company (Metrobank)',
                'Philippine Bank of Communications (PBCom)','Philippine National Bank (PNB)',
                'Philippine Trust Company (Philtrust)','Philippine Veterans Bank',
                'Rizal Commercial Banking Corporation (RCBC)','Security Bank Corporation',
                'Standard Chartered Bank','The Hongkong and Shanghai Banking Corporation (HSBC)',
                'Union Bank of the Philippines'
            ],
            'Thrift Banks': [
                'AllBank (A Thrift Bank), Inc.','BDO Network Bank, Inc.',
                'BPI Direct BanKo, Inc., A Savings Bank','Card SME Bank Inc., A Thrift Bank',
                'China Bank Savings, Inc.','City Savings Bank, Inc.',
                'Equicom Savings Bank, Inc.','ISLA Bank (A Thrift Bank), Inc.',
                'Legazpi Savings Bank, Inc.','Luzon Development Bank',
                'Malayan Savings Bank, Inc.','Pacific Ace Savings Bank, Inc.',
                'Philippine Business Bank, Inc., A Savings Bank','Philippine Savings Bank (PSBank)',
                'Producers Savings Bank Corporation',
                'Queen City Development Bank (Queenbank), A Thrift Bank',
                'Sterling Bank of Asia, Inc. (A Savings Bank)','Sun Savings Bank, Inc.',
                'UCPB Savings Bank','Wealth Development Bank Corporation'
            ],
            'Rural / Cooperative Banks': [
                'Bangko Mabuhay (A Rural Bank), Inc.','Camalig Bank, Inc. (A Rural Bank)',
                'Cantilan Bank, Inc. (A Rural Bank)',
                'Card Bank, Inc. (A Microfinance-Oriented Rural Bank)',
                'CARD MRI Rizal Bank, Inc.','Cebuana Lhuillier Rural Bank, Inc.',
                'Dungganon Bank (A Microfinance Rural Bank), Inc.',
                'East West Rural Bank, Inc.','Entrepreneur Rural Bank, Inc.',
                'MariBank Philippines Inc. (A Rural Bank)',
                'Mindanao Consolidated Cooperative Bank','Netbank (A Rural Bank), Inc.',
                'Own Bank, The Rural Bank of Cavite City, Inc.',
                'Partner Rural Bank (Cotabato), Inc.','Quezon Capital Rural Bank, Inc.',
                'Rang-Ay Bank, Inc. (A Rural Bank)','Rural Bank of Guinobatan, Inc.',
                'Vigan Banco Rural, Incorporada (VBRI)'
            ],
            'Digital Banks': [
                'GoTyme Bank Corporation','Maya Bank, Inc.','Tonik Digital Bank, Inc.',
                'Union Digital Bank','UNObank, Inc.'
            ]
        },
        ewallet: {
            'E-Wallets / EMI-NBFIs': [
                'Alipay Philippines, Inc.','CIS Bayad Center, Inc.',
                'DCPAY Philippines, Inc.','Easypay Global EMI Corporation',
                'Ecashpay Asia, Inc.','G-Xchange, Inc. (GCash)',
                'Gpay Network PH, Inc. (GrabPay)','I-Remit, Inc.','Infoserve, Inc.',
                'MarcoPay, Inc.','Maya Philippines, Inc.','OmniPay, Inc.',
                'PayMongo Payments, Inc.','Paynamics Technologies, Inc.',
                'Peppermint Bizmoto Inc.','Philippine Digital Asset Exchange, Inc.',
                'PPS-PEPP Financial Services Corp. (PalawanPay)',
                'ShopeePay Philippines, Inc.','SpeedyPay, Inc.','StarPay Corporation',
                'TayoCash, Inc.','Toktokwallet, Inc.','TopJuan Tech Corporation',
                'Toyota Financial Services Philippines Corporation',
                'Traxion Pay, Inc.','USSC Money Services, Inc.',
                'Wise Pilipinas, Inc.','Zybi Tech, Inc.'
            ]
        }
    };

    // ── Autocomplete init ────────────────────────────────────────────────────
    const acInput  = document.getElementById('payoutInstitution_search');
    const acHidden = document.getElementById('payoutInstitution');
    const acList   = document.getElementById('payoutInstitution_list');
    let acType = currentType;
    let acFocusIdx = -1;

    function acGetOptions(query) {
        const q = (query || '').toLowerCase().trim();
        const groups = acType === 'bank'
            ? Object.entries(INST.bank)
            : Object.entries(INST.ewallet);
        const out = [];
        for (const [grp, items] of groups) {
            const filtered = q ? items.filter(i => i.toLowerCase().includes(q)) : items;
            if (filtered.length) {
                out.push({ t: 'g', label: grp });
                filtered.forEach(v => out.push({ t: 'i', value: v }));
            }
        }
        return out;
    }

    function acRender(query) {
        const opts = acGetOptions(query);
        acList.innerHTML = '';
        acFocusIdx = -1;
        if (!opts.length) {
            const el = document.createElement('div');
            el.className = 'inst-no-results';
            el.textContent = query ? 'No matches found.' : 'No institutions available.';
            acList.appendChild(el);
        } else {
            opts.forEach(opt => {
                const el = document.createElement('div');
                if (opt.t === 'g') {
                    el.className = 'inst-group-label';
                    el.textContent = opt.label;
                } else {
                    el.className = 'inst-option';
                    el.textContent = opt.value;
                    el.dataset.value = opt.value;
                    el.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        acSelect(opt.value);
                    });
                }
                acList.appendChild(el);
            });
        }
        acList.style.display = 'block';
    }

    function acSelect(value) {
        acInput.value = value;
        acHidden.value = value;
        acList.style.display = 'none';
        acFocusIdx = -1;
    }

    acInput.addEventListener('input', function () {
        acHidden.value = '';
        acRender(this.value);
    });
    acInput.addEventListener('focus', function () { acRender(this.value); });
    acInput.addEventListener('blur', function () {
        setTimeout(function () { acList.style.display = 'none'; }, 200);
    });
    acInput.addEventListener('keydown', function (e) {
        const focusable = [...acList.querySelectorAll('.inst-option')];
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            acFocusIdx = Math.min(acFocusIdx + 1, focusable.length - 1);
            focusable.forEach((o, i) => o.classList.toggle('ac-focused', i === acFocusIdx));
            if (focusable[acFocusIdx]) focusable[acFocusIdx].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            acFocusIdx = Math.max(acFocusIdx - 1, 0);
            focusable.forEach((o, i) => o.classList.toggle('ac-focused', i === acFocusIdx));
        } else if (e.key === 'Enter' && acFocusIdx >= 0 && focusable[acFocusIdx]) {
            e.preventDefault();
            acSelect(focusable[acFocusIdx].dataset.value);
        } else if (e.key === 'Escape') {
            acList.style.display = 'none';
        }
    });

    function filterInstitutions(type) {
        acType = type;
        acInput.value = '';
        acHidden.value = '';
        acList.style.display = 'none';
    }

    function applyType(type) {
        currentType = type;
        ptypeBank.classList.toggle('active', type === 'bank');
        ptypeEwallet.classList.toggle('active', type === 'ewallet');
        filterInstitutions(type);
    }

    ptypeBank.addEventListener('click', function () { applyType('bank'); });
    ptypeEwallet.addEventListener('click', function () { applyType('ewallet'); });

    // Restore saved state
    applyType(savedType || 'bank');
    if (savedInst) acSelect(savedInst);

    saveBtn.addEventListener('click', async function () {
        const institution  = acHidden.value.trim();
        const holderName   = document.getElementById('payoutHolderName').value.trim();
        const accountNum   = document.getElementById('payoutAccountNumber').value.trim();

        if (!institution || !holderName || !accountNum) {
            saveMsg.style.display = 'block';
            saveMsg.style.background = 'rgba(239,68,68,0.1)';
            saveMsg.style.border = '1px solid rgba(239,68,68,0.25)';
            saveMsg.style.color = '#fecaca';
            saveMsg.textContent = 'Please fill in all fields.';
            return;
        }

        saveBtn.disabled = true;
        saveMsg.style.display = 'none';

        try {
            const res = await fetch('/api/mall/wallet/payout-account', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({
                    csrf_token:           csrfToken,
                    account_type:         currentType,
                    institution_name:     institution,
                    account_holder_name:  holderName,
                    account_number:       accountNum,
                }),
            });
            const json = await res.json();
            if (json.success) {
                saveMsg.style.display = 'block';
                saveMsg.style.background = 'rgba(34,197,94,0.1)';
                saveMsg.style.border = '1px solid rgba(34,197,94,0.2)';
                saveMsg.style.color = '#86efac';
                saveMsg.textContent = 'Payout account saved successfully.';
            } else {
                throw new Error(json.message || 'Save failed.');
            }
        } catch (err) {
            saveMsg.style.display = 'block';
            saveMsg.style.background = 'rgba(239,68,68,0.1)';
            saveMsg.style.border = '1px solid rgba(239,68,68,0.25)';
            saveMsg.style.color = '#fecaca';
            saveMsg.textContent = err.message;
        } finally {
            saveBtn.disabled = false;
        }
    });
})();
</script>

<?php include __DIR__ . '/parts/footer.php'; ?>
</body>
</html>