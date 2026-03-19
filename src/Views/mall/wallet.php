<?php
$wallet = $wallet ?? [];
$walletTransactions = $wallet_transactions ?? [];
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
</style>

<section class="wallet-layout">
    <div class="wallet-left">

        <!-- Premium Wallet Card -->
        <div class="gw-card">
            <div style="position:relative;z-index:1;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <span class="gw-chip">
                        <svg width="8" height="8" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4" fill="#a5b4fc" opacity="0.7"/></svg>
                        Ginto Wallet
                    </span>
                    <span style="font-size:0.68rem;color:rgba(199,210,254,0.45);font-weight:600;letter-spacing:0.06em;">ACTIVE</span>
                </div>
                <div class="gw-balance">₱<?= number_format((float)($wallet['balance'] ?? 0), 2) ?></div>
                <div class="gw-balance-sub">Available balance</div>
                <div class="gw-divider"></div>
                <div class="wallet-actions">
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
                Ginto Pay top-ups (QR or card) include a fixed fee of ₱25.00 per transaction. PayPal top-ups include ₱25.00 plus a 5% service fee. Wallet funds are purchase-only and cannot be withdrawn. This may change depending on bank or payment processor, bank or international card type.
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

    function openModal() {
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
        wtConfirmBtn.textContent = meta.confirmLabel || 'Confirm Top-up';
        wtConfirmBtn.disabled = false;
        wtModal.style.display = 'flex';

        // Auto-generate QR when modal opens for QR top-ups
        if (selectedMethod === 'ginto_pay_qr') {
            if (credit <= 0) {
                setError('Please enter a top-up amount greater than ₱0.');
                return;
            }
            setError('');
            wtQrBox.style.display = 'block';
            wtQrBox.innerHTML = '<div style="display:flex;flex-direction:column;align-items:center;gap:10px;padding:12px 0;">'
                + '<div style="width:34px;height:34px;border-radius:50%;border:4px solid rgba(255,255,255,0.12);border-top-color:rgba(214,180,75,0.9);animation:spin 0.85s linear infinite;"></div>'
                + '<div style="font-size:0.92rem;color:var(--muted);">Generating QR…</div>'
                + '</div>';

            try {
                const create = await api('/api/mall/wallet/topup/create', { amount: credit, payment_method: selectedMethod });
                currentSessionRef = create.session_ref;
                currentCreate = create;
                const qr = await api('/api/mall/checkout/paymongo-qr-init', { session_ref: currentSessionRef });
                wtQrBox.innerHTML = '<h3 style="margin:0 0 10px;font-size:1rem;font-weight:800;">Scan to top up</h3>'
                    + (qr.qr_image ? '<img src="' + qr.qr_image + '" alt="Wallet top-up QR" style="max-width:320px;width:min(100%,320px);border-radius:16px;border:1px solid var(--border);background:#fff;padding:12px;">' : '<div style="color:var(--muted);font-size:0.84rem;">' + (qr.qr_string || 'QR code ready.') + '</div>');
                setInfo('Scan the QR code and keep this page open. You pay ' + formatPrice(create.amount) + ', fee ' + formatPrice(create.fee) + ', wallet credit ' + formatPrice(create.credit_amount) + '.');
                beginStatusPoll();
            } catch (err) {
                wtQrBox.style.display = 'none';
                setError(err.message || 'Failed to generate QR code.');
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
                    setInfo('Top-up confirmed and posted to your wallet. Refreshing ledger...');
                    window.location.reload();
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
    wtCancelBtn.addEventListener('click', closeModal);
    wtConfirmBtn.addEventListener('click', startTopup);

    topupBtn.addEventListener('click', startTopup);
})();
</script>

<?php include __DIR__ . '/parts/footer.php'; ?>
</body>
</html>