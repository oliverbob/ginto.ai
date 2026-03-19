<?php
$wallet = $wallet ?? [];
$paypalClientId = trim((string)($paypal_client_id ?? ''));
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<?php if ($paypalClientId !== ''): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?= htmlspecialchars($paypalClientId, ENT_QUOTES, 'UTF-8') ?>&currency=PHP&intent=capture&components=buttons"></script>
<?php endif; ?>
<style>
/* ── Payment method cards ── */
.pm-card {
    position:relative; text-align:left; display:flex; align-items:center;
    gap:14px; padding:16px 18px; border-radius:20px;
    border:1.5px solid rgba(255,255,255,0.09);
    background:rgba(255,255,255,0.03);
    cursor:pointer; width:100%;
    color:inherit;
    transition:transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, background 0.18s ease;
    overflow:hidden;
}
.pm-card:hover {
    border-color:rgba(255,255,255,0.2);
    transform:translateY(-2px);
    box-shadow:0 10px 28px rgba(0,0,0,0.28);
}
.pm-card.is-selected {
    border-color:rgba(214,180,75,0.7);
    background:rgba(214,180,75,0.055);
    box-shadow:0 0 0 1px rgba(214,180,75,0.15), 0 10px 28px rgba(214,180,75,0.1);
}
.pm-icon { width:46px; height:46px; border-radius:14px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.pm-body { flex:1; min-width:0; }
.pm-name { font-weight:800; font-size:0.95rem; line-height:1.2; }
.pm-desc { font-size:0.84rem; color:var(--muted); margin-top:3px; }
.pm-check {
    width:22px; height:22px; border-radius:50%;
    border:1.5px solid rgba(255,255,255,0.16);
    display:flex; align-items:center; justify-content:center;
    font-size:0.72rem; color:transparent; flex-shrink:0;
    transition:all 0.18s ease;
}
.pm-card.is-selected .pm-check {
    background:linear-gradient(135deg,#b8860b,#f5d67b);
    border-color:transparent; color:#1a1200; font-weight:900;
}
/* ── Checkout confirmation modal ── */
@keyframes coIn {
    from { opacity:0; transform:scale(0.9) translateY(22px); }
    to   { opacity:1; transform:scale(1) translateY(0); }
}
.co-overlay {
    position:fixed; inset:0; z-index:9999;
    display:flex; align-items:center; justify-content:center;
    background:rgba(0,0,0,0.76);
    backdrop-filter:blur(10px); -webkit-backdrop-filter:blur(10px);
    padding:16px;
}
.co-card {
    background:linear-gradient(155deg,#0d1117 0%,#141a2b 55%,#1b2040 100%);
    border:1px solid rgba(255,255,255,0.1);
    border-radius:28px; width:100%; max-width:420px;
    box-shadow:0 40px 80px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.04);
    animation:coIn 0.3s cubic-bezier(0.34,1.56,0.64,1) both;
    overflow:hidden;
}
.co-head { padding:28px 28px 20px; text-align:center; position:relative; }
.co-glow {
    position:absolute; top:0; left:50%; transform:translateX(-50%);
    width:260px; height:130px;
    background:radial-gradient(ellipse at 50% 0%, rgba(214,180,75,0.18), transparent 70%);
    pointer-events:none;
}
.co-method-icon {
    width:64px; height:64px; border-radius:20px;
    margin:0 auto 14px;
    display:flex; align-items:center; justify-content:center;
}
.co-sector { font-size:0.74rem; letter-spacing:0.14em; text-transform:uppercase; color:var(--muted); font-weight:700; margin-bottom:4px; }
.co-method-name { font-size:1.45rem; font-weight:800; line-height:1.2; }
.co-divider { height:1px; background:rgba(255,255,255,0.07); margin:0 28px; }
.co-mobile-summary {
    display:none;
    margin:12px 28px 0;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px;
}
.co-mobile-chip {
    border:1px solid rgba(255,255,255,0.08);
    background:rgba(255,255,255,0.03);
    border-radius:14px;
    padding:11px 12px;
    min-width:0;
}
.co-mobile-chip-label {
    font-size:0.68rem;
    text-transform:uppercase;
    letter-spacing:0.12em;
    color:var(--muted);
    font-weight:800;
    margin-bottom:5px;
}
.co-mobile-chip-value {
    font-size:0.9rem;
    font-weight:800;
    color:var(--text);
    line-height:1.3;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}
.co-amount-box {
    margin:16px 28px; padding:18px 20px; border-radius:18px;
    background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.07);
    text-align:center;
}
.co-amount-label { font-size:0.72rem; text-transform:uppercase; letter-spacing:0.12em; color:var(--muted); font-weight:700; margin-bottom:6px; }
.co-amount {
    font-size:2.5rem; font-weight:900; line-height:1;
    background:linear-gradient(135deg,#f5d67b 0%,#d4af37 40%,#b8860b 100%);
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
}
.co-ship-box { margin:0 28px 16px; padding:14px 16px; border-radius:16px; background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.06); }
.co-ship-label { font-size:0.7rem; text-transform:uppercase; letter-spacing:0.12em; color:var(--muted); font-weight:700; margin-bottom:7px; }
.co-ship-text { font-size:0.87rem; line-height:1.65; color:var(--text); }
.co-qr-box { margin:0 28px 16px; text-align:center; }
.co-qr-box img { max-width:200px; width:100%; border-radius:14px; border:1px solid var(--border); background:#fff; padding:10px; margin:0 auto 10px; display:block; }
.co-qr-loading {
    display:none;
    align-items:center;
    justify-content:center;
    flex-direction:column;
    gap:10px;
    min-height:180px;
    padding:18px 14px;
    border-radius:18px;
    border:1px dashed rgba(255,255,255,0.12);
    background:rgba(255,255,255,0.03);
    margin-bottom:10px;
}
.co-qr-spinner {
    width:34px;
    height:34px;
    border-radius:50%;
    border:3px solid rgba(255,255,255,0.16);
    border-top-color:#d4af37;
    animation:coSpin 0.85s linear infinite;
}
.co-qr-loading-title {
    font-size:0.95rem;
    font-weight:800;
    color:var(--text);
}
.co-qr-loading-copy {
    font-size:0.82rem;
    line-height:1.5;
    color:var(--muted);
    max-width:260px;
    margin:0 auto;
}
.co-qr-box.is-loading .co-qr-loading {
    display:flex;
}
@keyframes coSpin {
    to { transform:rotate(360deg); }
}
.co-qr-actions {
    display:flex;
    gap:10px;
    justify-content:center;
    flex-wrap:wrap;
    margin:8px 0 0;
}
.co-qr-action-btn {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:122px;
    padding:9px 12px;
    border-radius:10px;
    border:1px solid rgba(255,255,255,0.14);
    background:rgba(255,255,255,0.04);
    color:var(--text);
    font-size:0.82rem;
    font-weight:700;
    text-decoration:none;
    cursor:pointer;
    transition:border-color 0.18s ease, background 0.18s ease, transform 0.18s ease;
}
.co-qr-action-btn:hover {
    border-color:rgba(255,255,255,0.24);
    background:rgba(255,255,255,0.08);
    transform:translateY(-1px);
}
.co-pp-box { margin:0 28px 16px; }
.co-actions { padding:4px 28px 26px; display:flex; flex-direction:column; gap:10px; }
.co-btn-confirm {
    width:100%; padding:14px 20px; border-radius:14px; border:none;
    background:linear-gradient(135deg,#b8860b 0%,#d4af37 45%,#f5d67b 100%);
    color:#1a1200; font-size:1rem; font-weight:800; cursor:pointer;
    transition:transform 0.18s, box-shadow 0.18s;
    box-shadow:0 8px 24px rgba(184,134,11,0.28); letter-spacing:0.01em;
}
.co-btn-confirm:hover:not(:disabled) { transform:translateY(-2px); box-shadow:0 14px 32px rgba(184,134,11,0.38); }
.co-btn-confirm:disabled { opacity:0.6; cursor:not-allowed; transform:none; }
.co-btn-cancel {
    width:100%; padding:12px 20px; border-radius:14px;
    border:1px solid rgba(255,255,255,0.1); background:transparent;
    color:var(--muted); font-size:0.9rem; font-weight:700; cursor:pointer;
    transition:border-color 0.18s, color 0.18s;
}
.co-btn-cancel:hover { border-color:rgba(255,255,255,0.22); color:var(--text); }
/* ── Form inputs ── */
.pf-input {
    width:100%; padding:11px 14px;
    background:#101a2f;
    border:1.5px solid rgba(255,255,255,0.16);
    border-radius:12px;
    color:#e9efff;
    --pf-autofill-bg:#101a2f;
    --pf-autofill-text:#e9efff;
    --pf-autofill-caret:#e9efff;
    font-family:inherit; font-size:1rem;
    transition:border-color 0.18s, box-shadow 0.18s, background 0.18s;
    -webkit-appearance:none;
}
.pf-input::placeholder { color:rgba(233,239,255,0.56); opacity:1; }
.pf-input:focus {
    outline:none;
    border-color:rgba(214,180,75,0.75);
    background:#0e172a;
    box-shadow:0 0 0 3px rgba(214,180,75,0.14);
}
.pf-input:hover:not(:focus) { border-color:rgba(255,255,255,0.16); }
textarea.pf-input { resize:vertical; line-height:1.6; }
.pf-input:-webkit-autofill,
.pf-input:-webkit-autofill:hover,
.pf-input:-webkit-autofill:focus {
    -webkit-text-fill-color:var(--pf-autofill-text) !important;
    -webkit-box-shadow:0 0 0 1000px var(--pf-autofill-bg) inset !important;
    box-shadow:0 0 0 1000px var(--pf-autofill-bg) inset !important;
    caret-color:var(--pf-autofill-caret);
    transition:background-color 0s, color 0s;
}
.pf-input.field-error {
    border-color:rgba(239,68,68,0.7) !important;
    box-shadow:0 0 0 3px rgba(239,68,68,0.12) !important;
    background:rgba(239,68,68,0.04) !important;
}
.pf-label {
    font-size:0.82rem;
    font-weight:700;
    color:rgba(232,241,255,0.86);
    text-transform:uppercase;
    letter-spacing:0.08em;
    margin-bottom:6px;
    display:block;
}
/* ── Section cards ── */
.co-section {
    border:1px solid rgba(255,255,255,0.07);
    background:rgba(255,255,255,0.025);
    backdrop-filter:blur(6px);
    border-radius:24px;
    padding:28px;
}
.co-section-title {
    font-size:0.82rem;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:0.14em;
    color:var(--muted);
    margin-bottom:16px;
    display:flex;
    align-items:center;
    gap:8px;
}
.co-section-title::before {
    content:'';
    width:3px; height:14px;
    border-radius:2px;
    background:linear-gradient(180deg,#d4af37,#3b82f6);
    display:inline-block;
    flex-shrink:0;
}
/* ── Aside cards ── */
.aside-card {
    border:1px solid rgba(255,255,255,0.07);
    background:rgba(255,255,255,0.025);
    border-radius:24px;
    padding:24px;
    overflow:hidden;
}
/* ── Total amount display ── */
#checkoutTotal {
    font-size:2.2rem; font-weight:900; line-height:1;
    background:linear-gradient(135deg,#f5d67b 0%,#d4af37 45%,#b8860b 100%);
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
}
/* ── What happens next ── */
.next-steps { list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:10px; }
.next-steps li {
    display:flex; align-items:flex-start; gap:12px;
    font-size:0.95rem; color:var(--muted); line-height:1.6;
}
.next-steps li .step-num {
    width:22px; height:22px; border-radius:50%;
    background:rgba(255,255,255,0.07); border:1px solid rgba(255,255,255,0.1);
    font-size:0.68rem; font-weight:800; color:var(--muted);
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0; margin-top:1px;
}

/* ── Light mode refinements ── */
body.light {
    background:
        radial-gradient(950px 500px at 95% -10%, rgba(59,130,246,0.10), transparent 52%),
        radial-gradient(900px 460px at -10% 0%, rgba(14,165,233,0.08), transparent 54%),
        var(--bg);
}
body.light .checkout-hero {
    border:1px solid #dbe6f7 !important;
    background:linear-gradient(130deg,#eff5ff 0%, #f5f7ff 56%, #ecf8ff 100%) !important;
    box-shadow:0 14px 34px rgba(15,23,42,0.08);
}
body.light .checkout-hero::before {
    content:'';
    position:absolute;
    inset:0;
    background:radial-gradient(ellipse at 82% 46%, rgba(59,130,246,0.14) 0%, transparent 60%);
    pointer-events:none;
}
body.light .checkout-kicker { color:#8a6a10 !important; }
body.light .checkout-title { color:#0f172a; }
body.light .checkout-subtitle { color:#334155 !important; }
body.light .checkout-back {
    border-color:#d2def2 !important;
    background:#ffffff !important;
    color:#334155 !important;
}
body.light .checkout-back:hover {
    border-color:#93c5fd !important;
    color:#0f172a !important;
    box-shadow:0 6px 16px rgba(37,99,235,0.14);
}
body.light .co-section,
body.light .aside-card {
    border:1px solid #dbe6f7;
    background:#ffffff;
    backdrop-filter:none;
    box-shadow:0 12px 28px rgba(15,23,42,0.08);
}
body.light .co-section-title,
body.light .pf-label {
    color:#334155;
}
body.light .pf-input {
    background:#ffffff;
    color:#0f172a;
    border-color:#cbd5e1;
    --pf-autofill-bg:#ffffff;
    --pf-autofill-text:#0f172a;
    --pf-autofill-caret:#0f172a;
}
body.light .pf-input::placeholder { color:#64748b; }
body.light .pf-input:focus {
    border-color:#3b82f6;
    background:#ffffff;
    box-shadow:0 0 0 3px rgba(59,130,246,0.18);
}
body.light .pm-card {
    border-color:#dbe6f7;
    background:#f8fbff;
    color:#0f172a;
}
body.light .pm-card:hover {
    border-color:#bfdbfe;
    box-shadow:0 10px 24px rgba(15,23,42,0.10);
}
body.light .pm-name { color:#0f172a; }
body.light .pm-desc  { color:#475569; }
body.light .pm-check {
    border-color:#cbd5e1;
}
body.light #walletNotice {
    background:rgba(16,185,129,0.08) !important;
    border-color:rgba(16,185,129,0.35) !important;
    color:#065f46 !important;
}
body.light #checkoutError {
    background:rgba(239,68,68,0.07) !important;
    border-color:rgba(239,68,68,0.3) !important;
    color:#991b1b !important;
}
body.light #checkoutInfo {
    background:rgba(59,130,246,0.07) !important;
    border-color:rgba(59,130,246,0.3) !important;
    color:#1e40af !important;
}
body.light .checkout-hint { color:#475569; }
body.light .next-steps li {
    color:#475569;
}
body.light #checkoutTotal {
    background:linear-gradient(135deg,#a16207 0%,#d4af37 48%,#f59e0b 100%);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    background-clip:text;
}

/* ── Light mode: confirmation modal ── */
body.light .co-card {
    background:linear-gradient(155deg,#f8fbff 0%,#ffffff 55%,#f0f6ff 100%);
    border:1px solid #dbe6f7;
    box-shadow:0 40px 80px rgba(15,23,42,0.18), 0 0 0 1px rgba(15,23,42,0.04);
}
body.light .co-glow {
    background:radial-gradient(ellipse at 50% 0%, rgba(214,180,75,0.12), transparent 70%);
}
body.light .co-method-name { color:#0f172a; }
body.light .co-sector { color:#64748b; }
body.light .co-divider { background:#e2eaf5; }
body.light .co-mobile-chip {
    background:#f8fbff;
    border-color:#dbe6f7;
}
body.light .co-mobile-chip-label { color:#64748b; }
body.light .co-mobile-chip-value { color:#1e293b; }
body.light .co-amount-box {
    background:#f1f6ff;
    border-color:#dbe6f7;
}
body.light .co-amount-label { color:#64748b; }
body.light .co-amount {
    background:linear-gradient(135deg,#a16207 0%,#d4af37 40%,#b8860b 100%);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    background-clip:text;
}
body.light .co-ship-box {
    background:#f8fbff;
    border-color:#dbe6f7;
}
body.light .co-ship-label { color:#64748b; }
body.light .co-ship-text { color:#1e293b; }
body.light .co-btn-cancel {
    border-color:#cbd5e1;
    color:#475569;
}
body.light .co-btn-cancel:hover {
    border-color:#93c5fd;
    color:#0f172a;
}
body.light #coQrStatus,
body.light #coQrFallback { color:#64748b; }
body.light .co-qr-action-btn {
    border-color:#cbd5e1;
    background:#ffffff;
    color:#334155;
}
body.light .co-qr-action-btn:hover {
    border-color:#93c5fd;
    background:#f8fbff;
    color:#0f172a;
}
body.light .co-qr-loading {
    background:#f8fbff;
    border-color:#dbe6f7;
}
body.light .co-qr-spinner {
    border-color:#dbe6f7;
    border-top-color:#d4af37;
}

@media (max-width: 980px) {
    section[style*="grid-template-columns:minmax(0,1.3fr)"] {
        grid-template-columns: minmax(0, 1fr) !important;
        gap: 16px !important;
    }
    aside[style*="position:sticky"] {
        position: static !important;
        top: auto !important;
    }
}

@media (max-width: 640px) {
    .co-overlay {
        align-items:flex-end;
        padding:0;
        overflow:auto;
    }
    .co-card {
        max-width:100%;
        border-radius:22px 22px 0 0;
        max-height:100vh;
        min-height:auto;
        overflow-y:auto;
        padding-bottom:max(10px, env(safe-area-inset-bottom));
    }
    .co-head { padding:14px 14px 10px; }
    .co-glow {
        width:180px;
        height:86px;
    }
    .co-method-icon {
        width:52px;
        height:52px;
        border-radius:16px;
        margin:0 auto 10px;
    }
    .co-sector {
        font-size:0.66rem;
        margin-bottom:2px;
    }
    .co-method-name {
        font-size:1.05rem;
    }
    .co-divider { margin:0 16px; }
    .co-mobile-summary {
        display:grid;
        margin:10px 16px 0;
    }
    .co-amount-box,
    .co-ship-box,
    .co-qr-box,
    .co-pp-box { margin-left:16px; margin-right:16px; }
    .co-amount-box,
    .co-ship-box { display:none; }
    .co-qr-box { margin-top:12px; margin-bottom:12px; }
    .co-qr-loading {
        min-height:132px;
        padding:14px 12px;
        margin-bottom:8px;
    }
    .co-qr-loading-title {
        font-size:0.88rem;
    }
    .co-qr-loading-copy {
        font-size:0.78rem;
        max-width:220px;
    }
    .co-qr-box img { max-width:136px; padding:6px; margin-bottom:8px; }
    .co-qr-actions {
        gap:8px;
        margin-top:6px;
    }
    .co-qr-action-btn {
        min-width:0;
        flex:1 1 0;
        padding:10px 10px;
        font-size:0.8rem;
    }
    .co-qr-box #coQrStatus {
        font-size:0.74rem !important;
        line-height:1.35;
        margin-top:6px !important;
    }
    .co-actions {
        position:sticky;
        bottom:0;
        padding:8px 16px 12px;
        gap:8px;
        background:linear-gradient(180deg,rgba(15,23,42,0), rgba(15,23,42,0.9) 28%, rgba(15,23,42,0.98) 100%);
        backdrop-filter:blur(10px);
    }
    .co-btn-confirm { padding:12px 16px; }
    .co-btn-cancel { padding:11px 16px; }
    body.light .co-actions {
        background:linear-gradient(180deg,rgba(255,255,255,0), rgba(255,255,255,0.92) 28%, rgba(255,255,255,0.98) 100%);
    }
    .checkout-hero {
        padding:22px 18px !important;
    }
    .checkout-title {
        font-size:1.65rem !important;
    }
    .checkout-subtitle {
        font-size:0.95rem !important;
        line-height:1.62 !important;
    }
    #paymentMethodGrid {
        grid-template-columns: minmax(0, 1fr) !important;
    }
    #checkoutShippingForm {
        grid-template-columns: minmax(0, 1fr) !important;
    }
}
/* ── Shipping field error highlight ── */
</style>
<body>
<?php include __DIR__ . '/parts/header.php'; ?>

<section style="max-width:1320px;margin:32px auto 80px;padding:0 18px;display:grid;grid-template-columns:minmax(0,1.3fr) minmax(320px,0.7fr);gap:22px;align-items:start;">
    <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Hero bar -->
        <div class="checkout-hero" style="border:1px solid rgba(255,255,255,0.07);background:linear-gradient(130deg,rgba(15,23,42,0.9) 0%,rgba(26,32,64,0.9) 50%,rgba(30,28,50,0.9) 100%);border-radius:24px;padding:30px 32px;position:relative;overflow:hidden;">
            <div style="position:absolute;inset:0;background:radial-gradient(ellipse at 80% 50%,rgba(214,180,75,0.1) 0%,transparent 60%);pointer-events:none;"></div>
            <div style="position:relative;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;">
                <div>
                    <div class="checkout-kicker" style="font-size:0.82rem;letter-spacing:0.18em;text-transform:uppercase;color:rgba(214,180,75,0.7);font-weight:800;margin-bottom:8px;">Mall Checkout</div>
                    <h1 class="checkout-title" style="margin:0 0 10px;font-size:2.1rem;line-height:1.1;font-weight:900;letter-spacing:-0.02em;">Finish your order</h1>
                    <p class="checkout-subtitle" style="margin:0;color:var(--muted);font-size:1rem;line-height:1.7;max-width:640px;">Ginto Pay routes through PayMongo. Card payment means the user pays with a regular credit or debit card.</p>
                </div>
                <a href="/marketplace" class="checkout-back" style="display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:12px;border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.05);color:var(--muted);font-size:0.88rem;font-weight:700;transition:all 0.18s;text-decoration:none;" onmouseover="this.style.borderColor='rgba(255,255,255,0.2)';this.style.color='var(--text)';" onmouseout="this.style.borderColor='rgba(255,255,255,0.1)';this.style.color='var(--muted)';">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                    Back to Mall
                </a>
            </div>
        </div>

        <!-- Shipping Details -->
        <div class="co-section">
            <div class="co-section-title">Shipping Details</div>
            <form id="checkoutShippingForm" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;">
                <label style="display:flex;flex-direction:column;">
                    <span class="pf-label">Full Name</span>
                    <input id="shipFullName" type="text" class="pf-input" placeholder="Juan Dela Cruz" autocomplete="name">
                </label>
                <label style="display:flex;flex-direction:column;">
                    <span class="pf-label">Phone</span>
                    <input id="shipPhone" type="text" class="pf-input" placeholder="09xx xxx xxxx" autocomplete="tel">
                </label>
                <label style="display:flex;flex-direction:column;grid-column:1 / -1;">
                    <span class="pf-label">Address Line 1</span>
                    <input id="shipAddress1" type="text" class="pf-input" placeholder="House number, street, barangay" autocomplete="address-line1">
                </label>
                <label style="display:flex;flex-direction:column;grid-column:1 / -1;">
                    <span class="pf-label">Address Line 2 <span style="font-weight:500;opacity:0.6;">(optional)</span></span>
                    <input id="shipAddress2" type="text" class="pf-input" placeholder="Apartment, building, landmark" autocomplete="address-line2">
                </label>
                <label style="display:flex;flex-direction:column;">
                    <span class="pf-label">City / Municipality</span>
                    <input id="shipCity" type="text" class="pf-input" placeholder="Quezon City" autocomplete="address-level2">
                </label>
                <label style="display:flex;flex-direction:column;">
                    <span class="pf-label">Province</span>
                    <input id="shipProvince" type="text" class="pf-input" placeholder="Metro Manila" autocomplete="address-level1">
                </label>
                <label style="display:flex;flex-direction:column;">
                    <span class="pf-label">Postal Code</span>
                    <input id="shipPostalCode" type="text" class="pf-input" placeholder="1100" autocomplete="postal-code">
                </label>
                <label style="display:flex;flex-direction:column;">
                    <span class="pf-label">Country</span>
                    <input id="shipCountry" type="text" class="pf-input" value="PH" autocomplete="country-name">
                </label>
                <label style="display:flex;flex-direction:column;grid-column:1 / -1;">
                    <span class="pf-label">Delivery Notes <span style="font-weight:500;opacity:0.6;">(optional)</span></span>
                    <textarea id="shipBuyerNotes" class="pf-input" rows="3" placeholder="Special instructions or landmark notes for the delivery crew"></textarea>
                </label>
            </form>
        </div>

        <!-- Payment Method -->
        <div class="co-section">
            <div class="co-section-title">Payment Method</div>

            <div id="paymentMethodGrid" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
                <button type="button" class="pm-card is-selected" data-method="ginto_pay_qr">
                    <div class="pm-icon" style="background:linear-gradient(135deg,#92650a,#d4af37);"><div style="width:28px;height:28px;border-radius:50%;overflow:hidden;box-shadow:0 0 0 1px rgba(0,0,0,0.15);"><img src="/assets/images/ginto.png" alt="" style="width:100%;height:100%;object-fit:cover;"></div></div>
                    <div class="pm-body">
                        <div class="pm-name">Ginto Pay</div>
                        <div class="pm-desc">QR · InstaPay / PESONet</div>
                    </div>
                    <div class="pm-check">✓</div>
                </button>

                <button type="button" class="pm-card" data-method="ginto_pay_card">
                    <div class="pm-icon" style="background:linear-gradient(135deg,#1e3a8a,#3b82f6);">
                        <svg width="26" height="20" viewBox="0 0 26 20" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="26" height="20" rx="4" fill="none"/><rect y="3" width="26" height="6" fill="rgba(255,255,255,0.32)"/><rect x="2" y="12" width="10" height="2.5" rx="1.2" fill="white"/><rect x="2" y="15.5" width="6" height="2" rx="1" fill="rgba(255,255,255,0.55)"/></svg>
                    </div>
                    <div class="pm-body">
                        <div class="pm-name">Card</div>
                        <div class="pm-desc">Regular credit/debit cards via PayMongo</div>
                    </div>
                    <div class="pm-check">✓</div>
                </button>

                <button type="button" class="pm-card" data-method="paypal">
                    <div class="pm-icon" style="background:linear-gradient(135deg,#003087,#0070e0);">
                        <svg width="56" height="18" viewBox="0 0 56 18" xmlns="http://www.w3.org/2000/svg"><text x="2" y="13" font-family="Arial,Helvetica,sans-serif" font-style="italic" font-weight="800" font-size="13" fill="#fff">PayPal</text></svg>
                    </div>
                    <div class="pm-body">
                        <div class="pm-name">PayPal</div>
                        <div class="pm-desc">Global · card backup</div>
                    </div>
                    <div class="pm-check">✓</div>
                </button>

                <button type="button" class="pm-card" data-method="wallet">
                    <div class="pm-icon" style="background:linear-gradient(135deg,#064e3b,#10b981);">
                        <svg width="22" height="20" viewBox="0 0 24 20" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="1" y="5" width="22" height="14" rx="3" stroke="white" stroke-width="1.8"/><path d="M1 9h22" stroke="white" stroke-width="1.8"/><circle cx="16.5" cy="14" r="1.5" fill="white"/><path d="M5 5V4a3 3 0 013-3h8a3 3 0 013 3v1" stroke="white" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </div>
                    <div class="pm-body">
                        <div class="pm-name">Ginto Wallet</div>
                        <div class="pm-desc">Balance: ₱<?= number_format((float)($wallet['balance'] ?? 0), 2) ?></div>
                    </div>
                    <div class="pm-check">✓</div>
                </button>
            </div>

            <div id="walletNotice" style="margin-top:12px;padding:10px 12px;border-radius:12px;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.24);font-size:0.79rem;color:#c7f9df;line-height:1.5;">
                Wallet funds are purchase-only and cannot be withdrawn.
            </div>

            <div id="checkoutError" style="display:none;margin-top:14px;padding:12px 14px;border-radius:14px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);color:#fecaca;"></div>
            <div id="checkoutInfo" style="display:none;margin-top:14px;padding:12px 14px;border-radius:14px;background:rgba(59,130,246,0.1);border:1px solid rgba(59,130,246,0.25);color:#bfdbfe;"></div>

            <div id="paypalButtonsWrap" style="display:none;margin-top:18px;">
                <div id="paypalButtonsContainer"></div>
            </div>

            <div id="qrWrap" style="display:none;margin-top:18px;border:1px dashed var(--border);border-radius:22px;padding:20px;background:var(--surface2);text-align:center;">
                <h3 style="margin:0 0 10px;font-size:1rem;font-weight:800;">Scan to complete Ginto Pay</h3>
                <img id="qrImage" src="" alt="Ginto Pay QR" style="max-width:320px;width:min(100%, 320px);border-radius:16px;border:1px solid var(--border);background:#fff;padding:12px;display:none;margin:0 auto 12px;">
                <div id="qrFallback" style="font-size:0.8rem;color:var(--muted);display:none;"></div>
                <div id="qrStatus" style="font-size:0.86rem;color:var(--muted);">Waiting for payment confirmation…</div>
            </div>

            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:18px;">
                <button type="button" id="startCheckoutBtn" style="display:none;" aria-hidden="true"></button>
                <a href="/wallet" class="btn btn-secondary" style="font-size:0.85rem;">Top Up Wallet</a>
                <span class="checkout-hint" style="font-size:0.82rem;color:var(--muted);">Select a payment method above to begin checkout</span>
            </div>
        </div>
    </div>

    <aside style="display:flex;flex-direction:column;gap:18px;position:sticky;top:92px;">
        <div class="aside-card" style="border:1px solid var(--border);background:var(--surface);border-radius:24px;padding:24px;">
            <h2 style="margin:0 0 14px;font-size:1.05rem;font-weight:800;">Order Summary</h2>
            <div id="checkoutItems" style="display:flex;flex-direction:column;gap:12px;"></div>
            <div style="height:1px;background:var(--border);margin:16px 0;"></div>
            <div style="display:flex;justify-content:space-between;font-size:0.95rem;color:var(--muted);margin-bottom:8px;">
                <span>Items total</span>
                <span id="checkoutSubtotal">₱0.00</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:0.95rem;color:var(--muted);margin-bottom:8px;">
                <span>Stores in this checkout</span>
                <span id="checkoutStoreCount">0</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-top:10px;">
                <div>
                    <div style="font-size:0.84rem;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);font-weight:700;">Total due</div>
                    <div id="checkoutTotal" style="font-size:2rem;font-weight:800;line-height:1;">₱0.00</div>
                </div>
                <div style="font-size:0.9rem;color:var(--muted);text-align:right;max-width:220px;line-height:1.55;">Platform fees and markups are already reflected in the checkout total according to the seller’s chosen plan.</div>
            </div>
        </div>

        <div class="aside-card" style="border:1px solid var(--border);background:var(--surface);border-radius:24px;padding:24px;">
            <h2 style="margin:0 0 12px;font-size:1.2rem;font-weight:800;">What happens next</h2>
            <ol style="margin:0;padding-left:20px;color:var(--muted);font-size:0.98rem;line-height:1.85;">
                <li>We split your cart into seller-specific orders.</li>
                <li>Successful payment notifies each seller by email and mall notification.</li>
                <li>Seller and delivery crew update the order timeline like Lazada or Shopee.</li>
                <li>You receive in-app mall notifications whenever delivery status changes.</li>
            </ol>
        </div>
    </aside>
</section>

<!-- ── Checkout Confirmation Modal ── -->
<div id="coModal" class="co-overlay" style="display:none;" aria-modal="true" role="dialog" aria-label="Payment confirmation">
    <div class="co-card">
        <div class="co-head">
            <div class="co-glow"></div>
            <div id="coIcon" class="co-method-icon"></div>
            <div class="co-sector">Pay with</div>
            <div id="coName" class="co-method-name"></div>
        </div>
        <div class="co-divider"></div>
        <div class="co-mobile-summary">
            <div class="co-mobile-chip">
                <div class="co-mobile-chip-label">Total</div>
                <div id="coAmountMobile" class="co-mobile-chip-value">₱0.00</div>
            </div>
            <div class="co-mobile-chip">
                <div class="co-mobile-chip-label">Ship To</div>
                <div id="coShipMobile" class="co-mobile-chip-value">—</div>
            </div>
        </div>
        <div class="co-amount-box">
            <div class="co-amount-label">Total Due</div>
            <div id="coAmount" class="co-amount">₱0.00</div>
        </div>
        <div class="co-ship-box">
            <div class="co-ship-label">Delivering to</div>
            <div id="coShip" class="co-ship-text">—</div>
        </div>
        <div id="coQrBox" class="co-qr-box" style="display:none;">
            <div id="coQrLoading" class="co-qr-loading">
                <div class="co-qr-spinner" aria-hidden="true"></div>
                <div class="co-qr-loading-title">Generating QR for easy-scan payment</div>
                <div class="co-qr-loading-copy">We are preparing your Ginto Pay QR so you can scan it quickly with your banking app.</div>
            </div>
            <img id="coQrImg" src="" alt="Ginto Pay QR" style="display:none;">
            <div id="coQrFallback" style="font-size:0.8rem;color:var(--muted);display:none;"></div>
            <div class="co-qr-actions">
                <a id="coQrDownload" class="co-qr-action-btn" href="#" download="ginto-pay-qr.png" style="display:none;">Download QR</a>
                <button id="coQrRefresh" type="button" class="co-qr-action-btn">Refresh QR</button>
            </div>
            <div id="coQrStatus" style="font-size:0.84rem;color:var(--muted);margin-top:8px;"></div>
        </div>
        <div id="coPpBox" class="co-pp-box" style="display:none;">
            <div id="coPpContainer"></div>
        </div>
        <div class="co-actions">
            <button id="coConfirm" class="co-btn-confirm">Confirm &amp; Pay</button>
            <button id="coCancel" class="co-btn-cancel">Cancel</button>
        </div>
    </div>
</div>

<script>
(function () {
    const csrfToken = <?= json_encode($csrf_token ?? '') ?>;
    const walletBalance = <?= json_encode((float)($wallet['balance'] ?? 0)) ?>;
    const cartKey = 'epower_cart';
    const query = new URLSearchParams(window.location.search);
    let selectedMethod = 'ginto_pay_qr';
    let currentSessionRef = query.get('session_ref') || '';
    let currentPayPalOrderId = '';
    let statusPoll = null;

    const methods = Array.from(document.querySelectorAll('.pm-card'));
    const startBtn = document.getElementById('startCheckoutBtn');
    const errorBox = document.getElementById('checkoutError');
    const infoBox = document.getElementById('checkoutInfo');
    const paypalWrap = document.getElementById('paypalButtonsWrap');
    const paypalButtonsContainer = document.getElementById('paypalButtonsContainer');
    const qrWrap = document.getElementById('qrWrap');
    const qrImage = document.getElementById('qrImage');
    const qrFallback = document.getElementById('qrFallback');
    const qrStatus = document.getElementById('qrStatus');
    const checkoutItems = document.getElementById('checkoutItems');
    const checkoutSubtotal = document.getElementById('checkoutSubtotal');
    const checkoutTotal = document.getElementById('checkoutTotal');
    const checkoutStoreCount = document.getElementById('checkoutStoreCount');

    function isWebkitAutofilled(el) {
        try {
            return el.matches(':-webkit-autofill');
        } catch (_) {
            return false;
        }
    }

    function repaintAutofillForTheme() {
        const isLight = document.body.classList.contains('light');
        const bg = isLight ? '#ffffff' : '#101a2f';
        const text = isLight ? '#0f172a' : '#e9efff';

        document.querySelectorAll('#checkoutShippingForm .pf-input').forEach(function (el) {
            el.style.setProperty('--pf-autofill-bg', bg);
            el.style.setProperty('--pf-autofill-text', text);
            el.style.setProperty('--pf-autofill-caret', text);

            if (!isWebkitAutofilled(el)) {
                el.style.webkitTextFillColor = '';
                el.style.removeProperty('-webkit-box-shadow');
                el.style.removeProperty('box-shadow');
                return;
            }

            el.style.webkitTextFillColor = text;
            el.style.caretColor = text;
            el.style.setProperty('-webkit-box-shadow', '0 0 0 1000px ' + bg + ' inset', 'important');
            el.style.setProperty('box-shadow', '0 0 0 1000px ' + bg + ' inset', 'important');
        });
    }

    function readCart() {
        try {
            return JSON.parse(localStorage.getItem(cartKey) || '[]');
        } catch (_) {
            return [];
        }
    }

    function clearCart() {
        localStorage.removeItem(cartKey);
    }

    function formatPrice(value, currency) {
        const map = { PHP: '₱', USD: '$', EUR: '€', NGN: '₦' };
        return (map[currency] || (currency + ' ')) + Number(value || 0).toFixed(2);
    }

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;' })[ch];
        });
    }

    function cartSummary() {
        const cart = readCart();
        const stores = new Set();
        let total = 0;
        let currency = 'PHP';
        cart.forEach(function (item) {
            const qty = Number(item.qty || item.quantity || 1);
            total += Number(item.price || 0) * qty;
            currency = item.currency || currency;
            if (item.seller_id) stores.add(String(item.seller_id));
        });
        return { cart, total, currency, stores: stores.size };
    }

    function renderSummary() {
        const summary = cartSummary();
        if (!summary.cart.length) {
            checkoutItems.innerHTML = '<div style="padding:14px;border-radius:14px;background:var(--surface2);border:1px solid var(--border);color:var(--muted);">Your cart is empty. Return to the mall and add products first.</div>';
            checkoutSubtotal.textContent = formatPrice(0, 'PHP');
            checkoutTotal.textContent = formatPrice(0, 'PHP');
            checkoutStoreCount.textContent = '0';
            startBtn.disabled = true;
            return;
        }
        startBtn.disabled = false;
        checkoutItems.innerHTML = summary.cart.map(function (item) {
            const qty = Number(item.qty || item.quantity || 1);
            return '<div style="display:flex;align-items:center;gap:12px;padding:12px;border-radius:16px;background:var(--surface2);border:1px solid var(--border);">'
                + '<img src="' + esc(item.img || '/assets/images/placeholder_ceramic.svg') + '" alt="' + esc(item.title) + '" style="width:58px;height:58px;border-radius:14px;object-fit:cover;border:1px solid var(--border);">'
                + '<div style="flex:1;min-width:0;">'
                + '<div style="font-weight:700;font-size:0.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + esc(item.title) + '</div>'
                + '<div style="font-size:0.78rem;color:var(--muted);">Qty ' + qty + '</div>'
                + '</div>'
                + '<div style="font-weight:700;font-size:0.88rem;">' + esc(formatPrice(Number(item.price || 0) * qty, item.currency || 'PHP')) + '</div>'
                + '</div>';
        }).join('');
        checkoutSubtotal.textContent = formatPrice(summary.total, summary.currency);
        checkoutTotal.textContent = formatPrice(summary.total, summary.currency);
        checkoutStoreCount.textContent = String(summary.stores || 1);
    }

    function shippingPayload() {
        return {
            full_name: document.getElementById('shipFullName').value,
            phone: document.getElementById('shipPhone').value,
            address_line1: document.getElementById('shipAddress1').value,
            address_line2: document.getElementById('shipAddress2').value,
            city: document.getElementById('shipCity').value,
            province: document.getElementById('shipProvince').value,
            postal_code: document.getElementById('shipPostalCode').value,
            country: document.getElementById('shipCountry').value,
            buyer_notes: document.getElementById('shipBuyerNotes').value,
        };
    }

    function setError(message) {
        if (!message) {
            errorBox.style.display = 'none';
            errorBox.textContent = '';
            return;
        }
        errorBox.style.display = 'block';
        errorBox.textContent = message;
    }

    function setInfo(message) {
        if (!message) {
            infoBox.style.display = 'none';
            infoBox.textContent = '';
            return;
        }
        infoBox.style.display = 'block';
        infoBox.textContent = message;
    }

    async function api(url, body, method) {
        const response = await fetch(url, {
            method: method || 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: body ? JSON.stringify(Object.assign({ csrf_token: csrfToken }, body)) : undefined,
        });
        const json = await response.json();
        if (!response.ok || json.success === false) {
            throw new Error(json.message || json.error || 'Request failed.');
        }
        return json;
    }

    async function createSession() {
        return api('/api/mall/checkout/create', {
            payment_method: selectedMethod,
            cart: readCart(),
            shipping: shippingPayload(),
        });
    }

    async function startPayment() {
        setError('');
        setInfo('');
        qrWrap.style.display = 'none';
        paypalWrap.style.display = 'none';
        startBtn.disabled = true;
        try {
            const session = await createSession();
            currentSessionRef = session.session_ref;
            if (selectedMethod === 'wallet') {
                clearCart();
                window.location.href = '/mall/orders';
                return;
            }
            if (selectedMethod === 'ginto_pay_qr') {
                const qr = await api('/api/mall/checkout/paymongo-qr-init', { session_ref: currentSessionRef });
                qrWrap.style.display = 'block';
                qrStatus.textContent = 'Waiting for payment confirmation...';
                if (qr.qr_image) {
                    qrImage.style.display = 'block';
                    qrImage.src = qr.qr_image;
                    qrFallback.style.display = 'none';
                } else {
                    qrImage.style.display = 'none';
                    qrFallback.style.display = 'block';
                    qrFallback.textContent = qr.qr_string || 'QR code ready. Use your banking app to complete the payment.';
                }
                setInfo('Scan the QR code using your banking app. This page will update automatically once payment succeeds.');
                beginStatusPoll();
                return;
            }
            if (selectedMethod === 'ginto_pay_card') {
                const card = await api('/api/mall/checkout/paymongo-card-init', { session_ref: currentSessionRef });
                window.location.href = card.redirect_url;
                return;
            }
            if (selectedMethod === 'paypal') {
                const paypal = await api('/api/mall/checkout/paypal-order', { session_ref: currentSessionRef });
                currentPayPalOrderId = paypal.paypal_order_id;
                renderPayPalButtons();
                paypalWrap.style.display = 'block';
                setInfo('Approve the payment using the PayPal buttons below.');
                return;
            }
        } catch (error) {
            setError(error.message);
        } finally {
            startBtn.disabled = false;
        }
    }

    function beginStatusPoll() {
        if (statusPoll) window.clearInterval(statusPoll);
        statusPoll = window.setInterval(async function () {
            try {
                const status = await fetch('/api/mall/checkout/status?session_ref=' + encodeURIComponent(currentSessionRef), {
                    headers: { 'Accept': 'application/json' },
                });
                const json = await status.json();
                if (json.status === 'completed') {
                    clearCart();
                    window.clearInterval(statusPoll);
                    const coQrStatusEl = document.getElementById('coQrStatus');
                    if (coQrStatusEl) coQrStatusEl.textContent = 'Payment confirmed! Redirecting…';
                    qrStatus.textContent = 'Payment confirmed. Redirecting to your orders...';
                    window.location.href = '/mall/orders';
                }
            } catch (_) {}
        }, 5000);
    }

    function renderPayPalButtons() {
        if (!window.paypal || !paypalButtonsContainer) {
            setError('PayPal is not available on this page right now.');
            return;
        }
        paypalButtonsContainer.innerHTML = '';
        window.paypal.Buttons({
            createOrder: function () {
                return currentPayPalOrderId;
            },
            onApprove: async function () {
                try {
                    await api('/api/mall/checkout/paypal-capture', {
                        session_ref: currentSessionRef,
                        paypal_order_id: currentPayPalOrderId,
                    });
                    clearCart();
                    window.location.href = '/mall/orders';
                } catch (error) {
                    setError(error.message);
                }
            },
            onError: function () {
                setError('PayPal payment failed.');
            },
        }).render('#paypalButtonsContainer');
    }

    // ── Payment method display metadata ──
    const pmMeta = {
        ginto_pay_qr: {
            name: 'Ginto Pay',
            iconBg: 'linear-gradient(135deg,#92650a,#d4af37)',
            iconHtml: '<div style="width:32px;height:32px;border-radius:50%;overflow:hidden;"><img src="/assets/images/ginto.png" alt="" style="width:100%;height:100%;object-fit:cover;"></div>',
            confirmLabel: 'Generate QR & Pay',
        },
        ginto_pay_card: {
            name: 'Credit / Debit Card',
            iconBg: 'linear-gradient(135deg,#1e3a8a,#3b82f6)',
            iconHtml: '<svg width="28" height="22" viewBox="0 0 26 20" fill="none"><rect width="26" height="20" rx="4"/><rect y="3" width="26" height="6" fill="rgba(255,255,255,0.32)"/><rect x="2" y="12" width="10" height="2.5" rx="1.2" fill="white"/></svg>',
            confirmLabel: 'Pay with Card',
        },
        paypal: {
            name: 'PayPal',
            iconBg: 'linear-gradient(135deg,#003087,#0070e0)',
            iconHtml: '<svg width="60" height="20" viewBox="0 0 60 20"><text x="2" y="14" font-family="Arial" font-style="italic" font-weight="800" font-size="14" fill="#fff">PayPal</text></svg>',
            confirmLabel: 'Continue to PayPal',
        },
        wallet: {
            name: 'Ginto Wallet',
            iconBg: 'linear-gradient(135deg,#064e3b,#10b981)',
            iconHtml: '<svg width="24" height="22" viewBox="0 0 24 20" fill="none"><rect x="1" y="5" width="22" height="14" rx="3" stroke="white" stroke-width="1.8"/><path d="M1 9h22" stroke="white" stroke-width="1.8"/><circle cx="16.5" cy="14" r="1.5" fill="white"/></svg>',
            confirmLabel: 'Pay with Wallet',
        },
    };

    // ── Modal DOM refs ──
    const coModal      = document.getElementById('coModal');
    const coIcon       = document.getElementById('coIcon');
    const coName       = document.getElementById('coName');
    const coAmount     = document.getElementById('coAmount');
    const coAmountMobile = document.getElementById('coAmountMobile');
    const coShip       = document.getElementById('coShip');
    const coShipMobile = document.getElementById('coShipMobile');
    const coConfirm    = document.getElementById('coConfirm');
    const coCancel     = document.getElementById('coCancel');
    const coQrBox      = document.getElementById('coQrBox');
    const coQrImg      = document.getElementById('coQrImg');
    const coQrFallback = document.getElementById('coQrFallback');
    const coQrStatus   = document.getElementById('coQrStatus');
    const coQrLoading  = document.getElementById('coQrLoading');
    const coQrDownload = document.getElementById('coQrDownload');
    const coQrRefresh  = document.getElementById('coQrRefresh');
    const coPpBox      = document.getElementById('coPpBox');
    const coPpContainer = document.getElementById('coPpContainer');

    function validateShipping() {
        const required = [
            { id: 'shipFullName', label: 'Full Name' },
            { id: 'shipPhone',    label: 'Phone' },
            { id: 'shipAddress1', label: 'Address Line 1' },
            { id: 'shipCity',     label: 'City' },
        ];
        document.querySelectorAll('#checkoutShippingForm .pf-input').forEach(function (el) {
            el.classList.remove('field-error');
        });
        const missing = [];
        required.forEach(function (f) {
            const el = document.getElementById(f.id);
            if (!el || !el.value.trim()) {
                missing.push(f.label);
                if (el) el.classList.add('field-error');
            }
        });
        return missing;
    }

    function openModal() {
        const meta    = pmMeta[selectedMethod] || {};
        const summary = cartSummary();
        const s       = shippingPayload();
        coIcon.style.background = meta.iconBg || '';
        coIcon.innerHTML        = meta.iconHtml || '';
        coName.textContent      = meta.name || selectedMethod;
        coAmount.textContent    = formatPrice(summary.total, summary.currency);
        coAmountMobile.textContent = formatPrice(summary.total, summary.currency);
        coShip.innerHTML = '<strong>' + esc(s.full_name) + '</strong>'
            + (s.phone ? ' &middot; ' + esc(s.phone) : '') + '<br>'
            + esc(s.address_line1) + (s.address_line2 ? ', ' + esc(s.address_line2) : '') + '<br>'
            + [s.city, s.province, s.postal_code].filter(Boolean).map(esc).join(', ');
        coShipMobile.textContent = s.city || s.address_line1 || s.full_name || '—';
        coConfirm.textContent    = meta.confirmLabel || 'Confirm & Pay';
        coConfirm.disabled       = false;
        coConfirm.style.display  = '';
        coQrBox.style.display    = 'none';
        coPpBox.style.display    = 'none';
        coPpContainer.innerHTML  = '';
        coCancel.textContent     = 'Cancel';
        coModal.style.display    = 'flex';
    }

    function closeModal() {
        coModal.style.display   = 'none';
        coPpContainer.innerHTML = '';
        if (statusPoll) { window.clearInterval(statusPoll); statusPoll = null; }
    }

    function renderQrInModal(qr) {
        coQrBox.classList.remove('is-loading');
        coQrLoading.style.display = 'none';
        if (qr.qr_image) {
            coQrImg.src = qr.qr_image;
            coQrImg.style.display = 'block';
            coQrFallback.style.display = 'none';
            coQrDownload.style.display = 'inline-flex';
            coQrDownload.href = qr.qr_image;
        } else {
            coQrImg.style.display = 'none';
            coQrFallback.style.display = 'block';
            coQrFallback.textContent = qr.qr_string || 'Open your banking app to complete the payment.';
            coQrDownload.style.display = 'none';
            coQrDownload.removeAttribute('href');
        }
    }

    async function startQrFlowInModal(forceNewSession) {
        coConfirm.style.display = 'none';
        coPpBox.style.display   = 'none';
        coQrBox.style.display   = 'block';
        coQrBox.classList.add('is-loading');
        coCancel.textContent    = 'Close';
        coQrImg.style.display = 'none';
        coQrFallback.style.display = 'none';
        coQrDownload.style.display = 'none';
        coQrLoading.style.display = 'flex';
        coQrStatus.textContent  = forceNewSession ? 'Refreshing QR for easy-scan payment...' : 'Generating QR for easy-scan payment...';
        coQrRefresh.disabled    = true;

        try {
            if (forceNewSession || !currentSessionRef) {
                const session = await createSession();
                currentSessionRef = session.session_ref;
            }
            const qr = await api('/api/mall/checkout/paymongo-qr-init', { session_ref: currentSessionRef });
            renderQrInModal(qr);
            coQrStatus.textContent = 'Scan with your banking app — this will update automatically when payment is confirmed.';
            beginStatusPoll();
        } catch (err) {
            coQrBox.classList.remove('is-loading');
            coQrLoading.style.display = 'none';
            setError(err.message);
            coQrStatus.textContent = 'Unable to generate QR. Tap Refresh QR to try again.';
        } finally {
            coQrRefresh.disabled = false;
        }
    }

    methods.forEach(function (button) {
        button.addEventListener('click', function () {
            selectedMethod = button.dataset.method;
            methods.forEach(function (item) {
                item.classList.toggle('is-selected', item === button);
            });
            setError('');
            setInfo('');
            // Validate shipping before showing modal
            const missing = validateShipping();
            if (missing.length) {
                setError('Please fill in: ' + missing.join(', ') + '.');
                document.getElementById('checkoutShippingForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            if (selectedMethod === 'wallet' && walletBalance <= 0) {
                setError('Your Ginto Wallet balance is ₱0.00. Please top up first.');
                return;
            }
            openModal();
            if (selectedMethod === 'ginto_pay_qr') {
                startQrFlowInModal(true);
            }
        });
    });

    startBtn.addEventListener('click', startPayment);

    coConfirm.addEventListener('click', async function () {
        coConfirm.disabled     = true;
        coConfirm.textContent  = 'Processing…';
        setError('');
        setInfo('');
        try {
            const session = await createSession();
            currentSessionRef = session.session_ref;
            if (selectedMethod === 'wallet') {
                clearCart(); closeModal();
                window.location.href = '/mall/orders';
                return;
            }
            if (selectedMethod === 'ginto_pay_qr') {
                await startQrFlowInModal(false);
                return;
            }
            if (selectedMethod === 'ginto_pay_card') {
                const card = await api('/api/mall/checkout/paymongo-card-init', { session_ref: currentSessionRef });
                closeModal();
                window.location.href = card.redirect_url;
                return;
            }
            if (selectedMethod === 'paypal') {
                const ppData = await api('/api/mall/checkout/paypal-order', { session_ref: currentSessionRef });
                currentPayPalOrderId    = ppData.paypal_order_id;
                coConfirm.style.display = 'none';
                coPpBox.style.display   = 'block';
                coCancel.textContent    = 'Cancel';
                renderPayPalInModal();
                return;
            }
        } catch (err) {
            closeModal();
            setError(err.message);
        }
    });

    coCancel.addEventListener('click', closeModal);
    coQrRefresh.addEventListener('click', function () {
        startQrFlowInModal(true);
    });
    coModal.addEventListener('click', function (e) {
        if (e.target === coModal && coQrBox.style.display === 'none') closeModal();
    });

    document.addEventListener('mall:theme-changed', function () {
        repaintAutofillForTheme();
        requestAnimationFrame(repaintAutofillForTheme);
    });

    if (window.MutationObserver) {
        const bodyObserver = new MutationObserver(function (mutations) {
            for (let i = 0; i < mutations.length; i++) {
                if (mutations[i].attributeName === 'class') {
                    repaintAutofillForTheme();
                    requestAnimationFrame(repaintAutofillForTheme);
                    break;
                }
            }
        });
        bodyObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });
    }

    function renderPayPalInModal() {
        if (!window.paypal) { closeModal(); setError('PayPal is not available right now.'); return; }
        coPpContainer.innerHTML = '';
        window.paypal.Buttons({
            createOrder: function () { return currentPayPalOrderId; },
            onApprove: async function () {
                try {
                    await api('/api/mall/checkout/paypal-capture', {
                        session_ref:     currentSessionRef,
                        paypal_order_id: currentPayPalOrderId,
                    });
                    clearCart(); closeModal();
                    window.location.href = '/mall/orders';
                } catch (err) { closeModal(); setError(err.message); }
            },
            onError: function () { closeModal(); setError('PayPal payment failed.'); },
        }).render('#coPpContainer');
    }

    renderSummary();
    repaintAutofillForTheme();
    setTimeout(repaintAutofillForTheme, 80);
    setTimeout(repaintAutofillForTheme, 320);

    if (query.get('status') === 'success' && currentSessionRef) {
        setInfo('We are finalizing your payment. This usually takes a few seconds.');
        beginStatusPoll();
    } else if (query.get('status') === 'cancelled') {
        setError('Payment was cancelled before completion.');
    }
})();
</script>

<?php include __DIR__ . '/parts/footer.php'; ?>
</body>
</html>