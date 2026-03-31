<?php
/** @var array $categories */
/** @var string $csrf_token */
/** @var array|null $product  — set when editing */
/** @var bool $editing */
/** @var string $kyc_status */
/** @var bool $tos_agreed */
/** @var bool $is_admin */
$editing    = $editing ?? false;
$product    = $product ?? [];
$pageTitle  = $editing ? 'Edit Product' : 'New Product';
$_pBase     = str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/wallet/') ? '/wallet/products' : '/marketplace/sellers/products';
$_kycPath   = str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/wallet/') ? '/wallet/kyc' : '/marketplace/sellers/kyc';
$action     = $editing
    ? $_pBase . '/update/' . (int)($product['id'] ?? 0)
    : $_pBase . '/create';
$kycStatus  = $kyc_status ?? 'none';
$tosAgreed  = $tos_agreed ?? false;
$isAdmin    = $is_admin ?? false;

$p = $product; // shorthand
$productAttrs = [];
if (!empty($p['attributes'])) {
    $decodedAttrs = json_decode((string)$p['attributes'], true);
    if (is_array($decodedAttrs)) {
        $productAttrs = $decodedAttrs;
    }
}
$volumeMlValue = isset($productAttrs['volume_ml']) ? (string)$productAttrs['volume_ml'] : '';
$existingImgs = [];
if (!empty($p['images'])) $existingImgs = json_decode($p['images'], true) ?: [];
if (empty($existingImgs) && !empty($p['image_path'])) $existingImgs = [$p['image_path']];
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<style>
.pf-shell {
    max-width: 820px;
    margin: 32px auto calc(58px + 68px); /* account for fixed bottom nav and extra buffer */
    padding: 0 16px;
}
.pf-back {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 0.84rem; color: var(--muted);
    margin-bottom: 20px;
    transition: color var(--trans);
}
.pf-back:hover { color: var(--text); }
.pf-header { margin-bottom: 28px; }
.pf-title { font-size: 1.4rem; font-weight: 800; margin-bottom: 3px; }
.pf-sub   { font-size: 0.845rem; color: var(--muted); }

.pf-section {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    margin-bottom: 16px;
}
.pf-section-header {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 10px;
}
.pf-section-icon {
    width: 30px; height: 30px; border-radius: 8px;
    background: rgba(59,130,246,0.1); color: var(--accent);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.pf-section-title { font-size: 0.92rem; font-weight: 700; }
.pf-section-body  { padding: 20px; display: flex; flex-direction: column; gap: 16px; }

.pf-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.pf-group  { display: flex; flex-direction: column; gap: 5px; }
.pf-label  { font-size: 0.82rem; font-weight: 600; }
.pf-label .req { color: var(--danger); }
.pf-input {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 9px 12px; width: 100%;
    color: var(--text); font-size: 0.88rem; font-family: inherit;
    outline: none;
    transition: border-color var(--trans), box-shadow var(--trans);
    box-sizing: border-box;
}
.pf-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,130,246,0.12); }
.pf-input::placeholder { color: var(--muted); }
textarea.pf-input  { resize: vertical; }
select.pf-input    { cursor: pointer; }
.pf-hint { font-size: 0.76rem; color: var(--muted); }

/* Color tags */
.color-tags-wrap {
    display: flex; flex-wrap: wrap; gap: 6px; align-items: center;
    padding: 7px 10px; min-height: 42px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    cursor: text;
    transition: border-color var(--trans), box-shadow var(--trans);
}
.color-tags-wrap:focus-within { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,130,246,0.12); }
.color-tag {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 9px; border-radius: 20px;
    font-size: 0.78rem; font-weight: 600;
    background: rgba(59,130,246,0.12); color: var(--accent);
    border: 1px solid rgba(59,130,246,0.25);
    white-space: nowrap;
}
.color-tag-close {
    cursor: pointer; opacity: 0.65; font-size: 1rem; line-height: 1;
    background: none; border: none; padding: 0; color: inherit;
}
.color-tag-close:hover { opacity: 1; }
.color-tag-input {
    border: none; background: transparent; outline: none;
    font-size: 0.86rem; font-family: inherit; color: var(--text);
    min-width: 140px; flex: 1;
}
.color-tag-input::placeholder { color: var(--muted); }
.color-presets { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.color-preset-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 9px; border-radius: 20px;
    font-size: 0.76rem; cursor: pointer;
    background: var(--surface2); border: 1px solid var(--border);
    color: var(--muted); font-family: inherit;
    transition: border-color 0.15s, color 0.15s;
}
.color-preset-btn:hover { border-color: var(--accent); color: var(--accent); }
.color-swatch { width: 10px; height: 10px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

/* Image upload */
.img-upload-area {
    border: 2px dashed var(--border);
    border-radius: var(--radius);
    padding: 28px 20px; text-align: center;
    cursor: pointer; position: relative;
    background: var(--surface2);
    transition: border-color var(--trans), background var(--trans);
}
.img-upload-area:hover,
.img-upload-area.drag-over { border-color: var(--accent); background: rgba(59,130,246,0.04); }
.img-upload-area input[type="file"] {
    position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
}
.img-upload-icon  { font-size: 2rem; margin-bottom: 8px; }
.img-upload-title { font-weight: 600; font-size: 0.88rem; margin-bottom: 3px; }
.img-upload-sub   { font-size: 0.76rem; color: var(--muted); }

.img-previews { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; }
.img-prev-item {
    width: 80px; display: flex; flex-direction: column; align-items: center; gap: 4px;
}
.img-prev-item img {
    width: 80px; height: 80px; object-fit: cover;
    border-radius: 8px; border: 1px solid var(--border);
}
.img-prev-name { font-size: 0.63rem; color: var(--muted); text-align: center; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 80px; }

/* Existing images */
.existing-imgs { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }
.existing-img-wrap { position: relative; width: 80px; }
.existing-img-wrap img {
    width: 80px; height: 80px; object-fit: cover;
    border-radius: 8px; border: 1px solid var(--border); display: block;
}
.existing-img-del {
    position: absolute; top: 3px; right: 3px;
    width: 20px; height: 20px; border-radius: 50%;
    background: rgba(239,68,68,0.9); color: #fff;
    border: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem; line-height: 1; font-weight: 700;
    padding: 0; transition: background var(--trans);
}
.existing-img-del:hover { background: rgba(220,38,38,1); }
.existing-img-wrap.deleted { opacity: 0.3; pointer-events: none; }

/* New upload preview delete */
.img-prev-item { position: relative; }
.img-prev-del {
    position: absolute; top: 3px; right: 3px;
    width: 20px; height: 20px; border-radius: 50%;
    background: rgba(239,68,68,0.9); color: #fff;
    border: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem; line-height: 1; font-weight: 700;
    padding: 0; transition: background var(--trans);
}
.img-prev-del:hover { background: rgba(220,38,38,1); }

/* Footer actions */
.pf-actions { display: flex; gap: 10px; align-items: center; margin-top: 8px; }

@media (max-width: 580px) {
    .pf-grid-2 { grid-template-columns: 1fr; }
}

/* ===== GATE MODALS ===== */
.gate-overlay {
    position: fixed; inset: 0; z-index: 9000;
    background: rgba(0,0,0,0.62);
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
}
.gate-overlay.hidden { display: none; }
.gate-modal {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    max-width: 520px; width: 100%;
    padding: 36px 32px 28px;
    box-shadow: 0 24px 64px rgba(0,0,0,0.4);
    position: relative;
}
.gate-icon { font-size: 3rem; text-align: center; margin-bottom: 16px; }
.gate-title { font-size: 1.25rem; font-weight: 800; text-align: center; margin-bottom: 8px; }
.gate-body  { font-size: 0.875rem; color: var(--muted); line-height: 1.7; text-align: center; margin-bottom: 24px; }
.gate-actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }

/* TOS modal specifics */
.tos-scroll {
    max-height: 340px; overflow-y: auto;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 16px 18px;
    font-size: 0.82rem; color: var(--muted); line-height: 1.75;
    margin-bottom: 18px;
    text-align: left;
}
.tos-scroll h4 { font-size: 0.84rem; font-weight: 700; color: var(--text); margin: 14px 0 4px; }
.tos-scroll h4:first-child { margin-top: 0; }
.tos-scroll p  { margin: 0 0 8px; }
.tos-scroll ul { margin: 0 0 8px; padding-left: 18px; }
.tos-scroll li { margin-bottom: 4px; }
.tos-agree-row { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 18px; font-size: 0.84rem; }
.tos-agree-row input[type="checkbox"] { accent-color: var(--accent); margin-top: 2px; flex-shrink: 0; }

/* Product possession reminder banner */
.possession-banner {
    background: rgba(245,158,11,0.08);
    border: 1px solid rgba(245,158,11,0.35);
    border-radius: var(--radius-sm);
    padding: 14px 18px;
    display: flex; align-items: flex-start; gap: 12px;
    margin-bottom: 22px;
    font-size: 0.84rem; color: var(--text); line-height: 1.6;
}
.possession-banner-icon { font-size: 1.4rem; flex-shrink: 0; }

/* Product type toggle */
.ptype-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; }
.ptype-btn {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    gap:5px; padding:12px 8px;
    border:1.5px solid var(--border); border-radius:10px;
    background:var(--surface2); color:var(--muted);
    font-size:0.75rem; font-weight:700; cursor:pointer;
    transition:all var(--trans); text-align:center;
    line-height:1.3;
}
.ptype-btn .ptype-icon { font-size:1.5rem; }
.ptype-btn.active {
    border-color:var(--accent);
    background:rgba(59,130,246,0.09);
    color:var(--text);
}
.ptype-btn:hover:not(.active) { border-color:var(--muted); }
@media(max-width:480px) { .ptype-grid { grid-template-columns:repeat(2,1fr); } }

/* Referral plan cards */
.fee-plan-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
@media(min-width:700px) { .fee-plan-grid { grid-template-columns:1fr 1fr; } }
.fee-plan-card {
    position:relative; padding:16px 16px 14px;
    border:2px solid var(--border); border-radius:12px;
    background:var(--surface2); cursor:pointer;
    transition:all var(--trans);
}
.fee-plan-card:hover { border-color:var(--muted); }
.fee-plan-card.active { border-color:var(--accent); background:rgba(59,130,246,0.06); }
.fee-plan-card input[type=radio] { position:absolute; opacity:0; width:0; height:0; }
.fee-plan-card-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:7px; }
.fee-plan-title { font-size:0.85rem; font-weight:800; }
.fee-plan-badge {
    font-size:0.68rem; font-weight:800; padding:3px 8px; border-radius:6px;
    background:rgba(99,102,241,0.15); color:#a5b4fc; border:1px solid rgba(99,102,241,0.25);
}
.fee-plan-pct { font-size:1.35rem; font-weight:900; color:var(--accent); margin-bottom:4px; }
.fee-plan-desc { font-size:0.75rem; color:var(--muted); line-height:1.55; }
.fee-plan-upside {
    margin-top:8px; padding:7px 10px;
    border-radius:8px; background:rgba(34,197,94,0.07);
    border:1px solid rgba(34,197,94,0.18);
    font-size:0.73rem; color:#86efac; line-height:1.5;
}
@media(max-width:540px) { .fee-plan-grid { grid-template-columns:1fr; } }

/* Hamburger nav drawer — always fixed and off-screen, slides in on .open */
#sidebar {
    position: fixed;
    top: 0; left: 0;
    height: 100vh;
    width: 280px;
    z-index: 1002;
    transform: translateX(-100%);
    transition: transform 0.25s ease;
    overflow-y: auto;
    background: var(--bg);
    border-right: 1px solid var(--border);
    box-shadow: 4px 0 24px rgba(0,0,0,0.3);
}
#sidebar.open { transform: translateX(0); }
#sidebarBackdrop { display: block; }
</style>
<body>
<?php include __DIR__ . '/parts/header.php'; ?>
<div id="sidebarBackdrop" class="sidebar-backdrop" aria-hidden="true"></div>
<!-- Mobile seller nav drawer (hamburger target) -->
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Seller navigation">
    <div class="sidebar-close-row" id="sidebarCloseRow">
        <a class="sidebar-close-logo" href="/mall" aria-label="Open mall home">
            <img src="/assets/images/mall.png" alt="Mall">
            <span>ePower</span>
        </a>
        <button class="sidebar-close-btn" id="sidebarClose" aria-label="Close menu">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div class="sidebar-inner" style="padding:16px 0">
        <ul class="sc-nav" role="list" style="list-style:none;margin:0;padding:0">
            <?php $_sfBase = str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/wallet/') ? '/wallet' : '/marketplace/sellers'; ?>
            <li class="sc-nav-item"><a href="<?= $_sfBase ?>/storefront" style="display:flex;align-items:center;gap:8px;padding:10px 18px;font-size:0.875rem;color:var(--text);text-decoration:none">🏪 My Storefront</a></li>
            <li class="sc-nav-item"><a href="<?= $_pBase ?>" style="display:flex;align-items:center;gap:8px;padding:10px 18px;font-size:0.875rem;color:var(--text);text-decoration:none">📦 My Products</a></li>
            <li class="sc-nav-item"><a href="<?= $_pBase ?>/new" style="display:flex;align-items:center;gap:8px;padding:10px 18px;font-size:0.875rem;color:var(--accent);text-decoration:none;font-weight:600">➕ New Product</a></li>
            <li class="sc-nav-item"><a href="<?= $_kycPath ?>" style="display:flex;align-items:center;gap:8px;padding:10px 18px;font-size:0.875rem;color:var(--text);text-decoration:none">🪹 KYC Verification</a></li>
            <li class="sc-nav-item"><a href="/marketplace" style="display:flex;align-items:center;gap:8px;padding:10px 18px;font-size:0.875rem;color:var(--text);text-decoration:none">🏠 View Marketplace</a></li>
        </ul>
    </div>
</aside>

<?php /* ====== KYC GATE MODAL — shown only when KYC is not approved ====== */ ?>
<?php if (!$isAdmin && $kycStatus !== 'approved'): ?>
<div class="gate-overlay" id="kycGateOverlay" role="dialog" aria-modal="true" aria-labelledby="kycGateTitle">
    <div class="gate-modal">
        <div class="gate-icon">🪪</div>
        <h2 class="gate-title" id="kycGateTitle">Identity Verification Required</h2>
        <div class="gate-body">
            <?php if ($kycStatus === 'pending'): ?>
                <p><strong>Your KYC application is currently under review.</strong></p>
                <p>Our team typically completes reviews within <strong>1–3 business days</strong>. Once your identity is verified and approved, you will be able to publish products on ePower Mall.</p>
                <p>Thank you for your patience.</p>
            <?php elseif ($kycStatus === 'rejected'): ?>
                <p><strong>Your previous KYC application was not approved.</strong></p>
                <p>Please revisit your KYC submission, address the reviewer's notes, and resubmit with clear, valid documents. You will be able to upload products once your identity is verified.</p>
            <?php else: ?>
                <p>To protect buyers and maintain a trusted marketplace, <strong>all sellers must complete identity verification (KYC)</strong> before listing products.</p>
                <p>This is required under Philippine law — including RA 9160 (AMLA) and RA 10173 (Data Privacy Act) — to prevent fraud and ensure a safe trading environment for everyone.</p>
                <p>The process is simple and takes only a few minutes.</p>
            <?php endif; ?>
        </div>
        <div class="gate-actions">
            <a href="<?= $_kycPath ?>" class="btn btn-primary" style="padding:11px 28px">
                <?= $kycStatus === 'pending' ? '⏳ View KYC Status' : '🪹 Start KYC Verification' ?>
            </a>
            <a href="<?= $_pBase ?>" class="btn btn-secondary">Back to Products</a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php /* ====== SELLER TERMS OF SERVICE MODAL — shown once per user ====== */ ?>
<?php if (!$isAdmin && $kycStatus === 'approved' && !$tosAgreed): ?>
<div class="gate-overlay" id="tosOverlay" role="dialog" aria-modal="true" aria-labelledby="tosTitle">
    <div class="gate-modal" style="max-width:640px">
        <div class="gate-icon">📋</div>
        <h2 class="gate-title" id="tosTitle">ePower Mall — Seller Terms of Service</h2>
        <p style="font-size:0.82rem;color:var(--muted);text-align:center;margin-bottom:14px">Please read the full terms carefully before listing any product.</p>
        <div class="tos-scroll" id="tosScroll">

            <h4>1. Acceptance of Terms</h4>
            <p>By clicking "I Agree and Continue", you confirm that you have read, understood, and agree to be bound by these Seller Terms of Service ("Terms"), the ePower Mall Privacy Policy, and all applicable Philippine laws and regulations. These Terms form a legally binding agreement between you ("Seller") and ePower Mall ("Platform").</p>

            <h4>2. Data Privacy Act Disclosure (RA 10173)</h4>
            <p>Pursuant to Republic Act No. 10173, the <strong>Data Privacy Act of 2012</strong> (DPA), and its Implementing Rules and Regulations (IRR), you are hereby informed that ePower Mall, as a Personal Information Controller (PIC), will collect, process, and store your sensitive personal information — including your full name, date of birth, address, contact details, government-issued ID, and financial information — for the following purposes:</p>
            <ul>
                <li>Verification of your identity (Know Your Customer — KYC) as mandated by RA 9160 (AMLA);</li>
                <li>Compliance with BIR regulations on online sellers (Revenue Memorandum Circular 60-2020);</li>
                <li>Prevention of fraud, money laundering, and prohibited trade;</li>
                <li>Administration of your seller account and processing of payments.</li>
            </ul>
            <p>Your data will be kept strictly confidential, encrypted at rest and in transit, and will not be shared with third parties except as required by law or authorized by you. You have the right to access, correct, and request erasure of your personal data in compliance with the DPA. For data privacy concerns, contact our Privacy Officer at <em>privacy@epower.com.ph</em>.</p>

            <h4>3. Government Investigation &amp; Juridical Authority</h4>
            <p>You acknowledge and agree that in the event of a dispute, complaint, or investigation involving fraud, unauthorized transactions, prohibited goods, or any violation of Philippine law, <strong>the personal information you provided during KYC and all transaction records may be disclosed to authorized government agencies</strong> — including the NBI, PNP, DOJ, BIR, SEC, DTI, and courts of competent jurisdiction — when a valid legal warrant, court order, or official governmental request is presented. By agreeing to these Terms, you waive confidentiality of said information to the extent required by Philippine juridical authority in pursuing such investigations.</p>

            <h4>4. Seller Obligations — Product Authenticity</h4>
            <ul>
                <li><strong>You must only list products that are actually in your physical possession at the time of listing.</strong> Listing products you do not own, cannot deliver, or are unavailable is strictly prohibited and constitutes fraud.</li>
                <li>All product descriptions, images, and prices must be accurate, truthful, and not misleading.</li>
                <li>You must not list counterfeit, stolen, prohibited, hazardous, or regulated goods.</li>
                <li>You must comply with the Consumer Act of the Philippines (RA 7394) — no deceptive labeling or misrepresentation.</li>
                <li>Products must comply with all applicable DTI regulations on product standards and labeling.</li>
            </ul>

            <h4>5. Payment Disbursement Policy</h4>
            <p>Upon a buyer's confirmation of successful delivery of each item (or after the platform's automatic confirmation period), <strong>payment will be released to your registered payout account within seven (7) calendar days</strong>. This holding period protects buyers and ensures dispute resolution before funds are disbursed. ePower Mall reserves the right to withhold payment pending resolution of any open dispute, chargeback, or investigation.</p>

            <h4>6. Shipment &amp; Delivery Standards</h4>
            <ul>
                <li>You must ship items within the handling time stated in your listing (default: 1–3 business days).</li>
                <li>You are responsible for ensuring items are properly packed to prevent damage during transit.</li>
                <li>Once an order is accepted, you must provide a valid tracking number within 24 hours of shipment.</li>
                <li>You must comply with PDEA, BOC, and CAAP regulations regarding prohibited items in parcels.</li>
                <li>For items shipped via air freight, all dangerous goods restrictions of the Civil Aviation Authority of the Philippines (CAAP) apply.</li>
                <li>If a package is lost, damaged, or significantly delayed due to your fault, you are obligated to compensate the buyer or issue a full refund.</li>
            </ul>

            <h4>7. Anti-Fraud &amp; Prohibited Conduct</h4>
            <ul>
                <li>Shill bidding, fake reviews, account manipulation, or any form of marketplace fraud is strictly prohibited and may result in permanent account termination and criminal referral.</li>
                <li>Sellers may not transact outside the platform to circumvent fees or buyer protections.</li>
                <li>Any attempt to launder money, finance terrorism, or trade in prohibited/controlled goods will be reported to the AMLC and law enforcement authorities under RA 9160 and RA 10365.</li>
            </ul>

            <h4>8. Taxes &amp; BIR Compliance</h4>
            <p>You are responsible for declaring your online selling income to the Bureau of Internal Revenue as required by RMC 60-2020. ePower Mall will provide transaction summaries upon request to support your tax filing obligations. Failure to comply with BIR regulations is your sole legal responsibility.</p>

            <h4>9. Intellectual Property</h4>
            <p>By uploading product images and descriptions, you confirm you own the copyright or have obtained proper authorization to use them. ePower Mall respects IP rights under RA 8293 (Intellectual Property Code). Infringing content will be removed and repeat violators will be suspended.</p>

            <h4>10. Consumer Protection (RA 7394)</h4>
            <p>You agree to honor the platform's return and refund policy in accordance with the Consumer Act of the Philippines. Products must match their descriptions. Buyers have the right to return defective products within the applicable warranty period. Sellers who repeatedly violate consumer protection rules may be suspended and reported to the DTI.</p>

            <h4>11. Accuracy of Information</h4>
            <p><strong>You warrant that all information provided during registration, KYC, and product listing is truthful, accurate, and up to date.</strong> Submission of false or fraudulent information is a criminal offense under RA 10175 (Cybercrime Prevention Act) and RA 3815 (Revised Penal Code), and may result in criminal prosecution, account termination, and civil liability.</p>

            <h4>12. Seller Rights &amp; Platform Obligations</h4>
            <ul>
                <li>You have the right to be informed of the reason for any account suspension or product removal.</li>
                <li>You have the right to appeal platform decisions through the official dispute resolution process.</li>
                <li>ePower Mall commits to processing your payouts promptly and transparently within the stated timelines.</li>
                <li>ePower Mall will not disclose your personal data without legal basis as defined under RA 10173.</li>
            </ul>

            <h4>13. Amendments</h4>
            <p>ePower Mall reserves the right to amend these Terms at any time. You will be notified via email and/or platform notification. Continued use of the platform after notice constitutes acceptance of the updated Terms.</p>

            <p style="margin-top:14px;font-size:0.78rem;color:var(--muted)">Last updated: <?= date('F j, Y') ?> · Governed by the laws of the Republic of the Philippines.</p>
        </div>
        <div class="tos-agree-row">
            <input type="checkbox" id="tosCheckbox" required>
            <label for="tosCheckbox">I have read and fully understand the Seller Terms of Service, including the Data Privacy Act disclosure, payment policy, and anti-fraud provisions. I agree to comply with all applicable Philippine laws as a seller on ePower Mall.</label>
        </div>
        <div class="gate-actions">
            <button type="button" id="tosBtnAgree" class="btn btn-primary" style="padding:11px 28px" disabled>✅ I Agree and Continue</button>
            <a href="<?= $_pBase ?>" class="btn btn-secondary">Decline — Back to Products</a>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="pf-shell">

    <a href="<?= $_pBase ?>" class="pf-back">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Products
    </a>

    <div class="pf-header">
        <h1 class="pf-title"><?= $pageTitle ?></h1>
        <p class="pf-sub"><?= $editing ? 'Update the details for this listing' : 'Fill in the details to create a new listing' ?></p>
    </div>

    <!-- Product Possession Reminder -->
    <?php if (!$editing): ?>
    <div class="possession-banner" role="note" aria-label="Important seller reminder">
        <div class="possession-banner-icon">📦</div>
        <div>
            <strong>Important Reminder:</strong> Only upload products that you actually have in your possession right now.
            Listing items you do not physically have — including pre-orders without confirmed stock, items belonging to others, or products you have not yet received — is <strong>strictly prohibited</strong> and may result in account suspension, buyer disputes, and legal action. By continuing, you confirm that the product you are listing is available, yours to sell, and ready to ship.
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?= htmlspecialchars($action) ?>" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <!-- Basic Info -->
        <div class="pf-section">
            <div class="pf-section-header">
                <div class="pf-section-icon">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </div>
                <h2 class="pf-section-title">Basic Information</h2>
            </div>
            <div class="pf-section-body">
                <div class="pf-group">
                    <label class="pf-label" for="pf-title">Title <span class="req" aria-hidden="true">*</span></label>
                    <input class="pf-input" id="pf-title" type="text" name="title" required
                        placeholder="What are you selling?"
                        value="<?= htmlspecialchars($p['title'] ?? '') ?>">
                </div>
                <div class="pf-group">
                    <label class="pf-label" for="pf-slug">URL Slug <span style="color:var(--muted);font-weight:400">(optional)</span></label>
                    <input class="pf-input" id="pf-slug" type="text" name="slug"
                        placeholder="auto-generated-from-title"
                        value="<?= htmlspecialchars($p['slug'] ?? '') ?>">
                    <span class="pf-hint">Leave blank to auto-generate from the title.</span>
                </div>
                <div class="pf-group">
                    <label class="pf-label" for="pf-short">Short Description</label>
                    <textarea class="pf-input" id="pf-short" name="short_description" rows="2"
                        placeholder="One-liner summary shown on listing cards…"><?= htmlspecialchars($p['short_description'] ?? '') ?></textarea>
                </div>
                <div class="pf-group">
                    <label class="pf-label" for="pf-desc">Full Description</label>
                    <textarea class="pf-input" id="pf-desc" name="description" rows="6"
                        placeholder="Detailed product description, features, specifications…"><?= htmlspecialchars($p['description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Pricing & Category -->
        <div class="pf-section">
            <div class="pf-section-header">
                <div class="pf-section-icon">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                </div>
                <h2 class="pf-section-title">Pricing &amp; Details</h2>
            </div>
            <div class="pf-section-body">

                <!-- Product Type -->
                <div class="pf-group">
                    <label class="pf-label">Product Type <span class="req" aria-hidden="true">*</span></label>
                    <div class="ptype-grid" role="group" aria-label="Product type">
                        <?php
                        $curType = $p['product_type'] ?? 'physical';
                        $ptypes = [
                            'physical'     => ['📦', 'Physical', 'Shipped to buyer'],
                            'liquid'       => ['🧪', 'Liquid', 'Measured in mL'],
                            'digital'      => ['📄', 'Digital', 'Downloadable file'],
                            'virtual'      => ['🎭', 'Virtual', 'Service / voucher'],
                            'subscription' => ['🔄', 'Subscription', 'Recurring access'],
                        ];
                        foreach ($ptypes as $val => [$icon, $label, $sub]):
                        ?>
                        <button type="button"
                            class="ptype-btn<?= $curType === $val ? ' active' : '' ?>"
                            data-ptype="<?= $val ?>">
                            <span class="ptype-icon"><?= $icon ?></span>
                            <span><?= $label ?></span>
                            <span style="font-size:0.68rem;font-weight:400;color:var(--muted)"><?= $sub ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="pf-product-type" name="product_type" value="<?= htmlspecialchars($curType) ?>">
                </div>

                <div class="pf-grid-2">
                    <div class="pf-group">
                        <label class="pf-label" for="pf-price">Price <span class="req" aria-hidden="true">*</span></label>
                        <input class="pf-input" id="pf-price" type="number" step="0.01" min="0" name="price" required
                            placeholder="0.00"
                            value="<?= htmlspecialchars($p['price'] ?? '') ?>">
                    </div>
                    <div class="pf-group">
                        <label class="pf-label" for="pf-currency">Currency</label>
                        <select class="pf-input" id="pf-currency" name="currency">
                            <?php foreach (['USD','PHP','NGN','EUR','GBP','SGD','MYR','AUD'] as $cur): ?>
                            <option value="<?= $cur ?>" <?= ($p['currency'] ?? 'USD') === $cur ? 'selected' : '' ?>><?= $cur ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="pf-grid-2">
                    <div class="pf-group">
                        <label class="pf-label" for="pf-qty">Stock Quantity</label>
                        <input class="pf-input" id="pf-qty" type="number" min="0" name="quantity"
                            placeholder="0"
                            value="<?= htmlspecialchars((string)($p['quantity'] ?? $p['stock'] ?? 0)) ?>">
                        <span class="pf-hint" id="pf-qty-hint">Leave 0 for unlimited (digital/virtual/subscription).</span>
                    </div>
                    <div class="pf-group">
                        <label class="pf-label" for="pf-cat">Category</label>
                        <select class="pf-input" id="pf-cat" name="category_id">
                            <option value="">Uncategorized</option>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?= htmlspecialchars((string)$c['id']) ?>"
                                <?= (int)($p['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Buyer Purchase Limit -->
                <div class="pf-grid-2" style="margin-top:8px;">
                    <div class="pf-group">
                        <label class="pf-label" for="pf-max-qty">Max Qty Per Buyer</label>
                        <input class="pf-input" id="pf-max-qty" type="number" min="0" name="max_qty_per_buyer"
                            placeholder="0 = no limit"
                            value="<?= htmlspecialchars((string)($p['max_qty_per_buyer'] ?? '')) ?>">
                        <span class="pf-hint">Safe threshold per buyer. 0 or blank means no limit. Exceeding this triggers a "Request More" approval from you.</span>
                    </div>
                    <div class="pf-group" style="display:flex;align-items:center;gap:10px;padding-top:28px;">
                        <label class="pf-switch" style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="hidden" name="request_more_enabled" value="0">
                            <input type="checkbox" name="request_more_enabled" value="1"
                                <?= !empty($p['request_more_enabled']) ? 'checked' : '' ?>
                                style="width:18px;height:18px;accent-color:var(--accent);">
                            <span style="font-size:0.88rem;">Enable "Request More" for repeat buyers</span>
                        </label>
                        <span class="pf-hint" style="margin:0;font-size:0.78rem;">Allow verified buyers to request quantities above the max limit for your approval.</span>
                    </div>
                </div>

                <!-- Colors / Variants -->
                <div class="pf-group">
                    <label class="pf-label">Available Colors <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
                    <div class="color-tags-wrap" id="pf-color-tags-wrap">
                        <input class="color-tag-input" id="pf-color-input" type="text"
                            placeholder="Type a color, press Enter or comma…" autocomplete="off" maxlength="50">
                    </div>
                    <input type="hidden" id="pf-colors-hidden" name="colors"
                        value="<?= htmlspecialchars($p['colors'] ?? '[]') ?>">
                    <div class="color-presets" id="pf-color-presets">
                        <?php foreach ([
                            ['Black','#222'], ['White','#f0f0f0'], ['Gray','#9ca3af'],
                            ['Red','#ef4444'], ['Orange','#f97316'], ['Yellow','#eab308'],
                            ['Green','#22c55e'], ['Blue','#3b82f6'], ['Purple','#a855f7'],
                            ['Pink','#ec4899'], ['Brown','#92400e'], ['Beige','#d4b896'],
                        ] as [$cname, $hex]): ?>
                        <button type="button" class="color-preset-btn" data-color="<?= $cname ?>">
                            <span class="color-swatch" style="background:<?= $hex ?>;border:1px solid rgba(0,0,0,0.18)"></span>
                            <?= $cname ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <span class="pf-hint" style="margin-top:2px">Press <kbd style="font-size:0.72rem;padding:1px 4px;border:1px solid var(--border);border-radius:3px;background:var(--surface2)">Enter</kbd> or <kbd style="font-size:0.72rem;padding:1px 4px;border:1px solid var(--border);border-radius:3px;background:var(--surface2)">,</kbd> to add. Click a preset to quick-add. Buyers will see these as filter options.</span>
                </div>

                <!-- Platform Fee Plan -->
                <?php
                $validPlanModels = ['hands_off','active_discovery','full_service','referral','markup','standard'];
                $savedModel = in_array($p['pricing_model'] ?? '', $validPlanModels, true)
                    ? $p['pricing_model']
                    : 'hands_off';
                $planRateMap = ['hands_off'=>12,'active_discovery'=>25,'full_service'=>35,'referral'=>15,'markup'=>0,'standard'=>10];
                $savedRate = $planRateMap[$savedModel] ?? 12;
                ?>
                <input type="hidden" id="pf-pricing-model" name="pricing_model" value="<?= htmlspecialchars($savedModel) ?>">
                <input type="hidden" id="pf-pricing-rate" name="pricing_rate" value="<?= $savedRate ?>">

                <div class="pf-group">
                    <label class="pf-label">Platform Fee Plan <span class="req" aria-hidden="true">*</span></label>
                    <div class="fee-plan-grid" role="group" aria-label="Platform fee plan">

                        <!-- Hands-Off -->
                        <label class="fee-plan-card<?= $savedModel === 'hands_off' ? ' active' : '' ?>" id="fpc-hands_off">
                            <input type="radio" name="_fee_plan_radio" value="hands_off" <?= $savedModel === 'hands_off' ? 'checked' : '' ?>>
                            <div class="fee-plan-card-top">
                                <span class="fee-plan-title">🛒 Hands-Off</span>
                            </div>
                            <div class="fee-plan-pct">10–15%</div>
                            <div class="fee-plan-desc">Standard listing. Minimal platform involvement. Product appears in search and browse. You keep the most per sale.</div>
                        </label>

                        <!-- Active Discovery -->
                        <label class="fee-plan-card<?= $savedModel === 'active_discovery' ? ' active' : '' ?>" id="fpc-active_discovery">
                            <input type="radio" name="_fee_plan_radio" value="active_discovery" <?= $savedModel === 'active_discovery' ? 'checked' : '' ?>>
                            <div class="fee-plan-card-top">
                                <span class="fee-plan-title">🔍 Active Discovery</span>
                                <span class="fee-plan-badge">Popular</span>
                            </div>
                            <div class="fee-plan-pct">20–25%</div>
                            <div class="fee-plan-desc">Platform actively promotes your product through featured placements, categories, and recommendation engines to reach more buyers.</div>
                        </label>

                        <!-- Full Service -->
                        <label class="fee-plan-card<?= $savedModel === 'full_service' ? ' active' : '' ?>" id="fpc-full_service">
                            <input type="radio" name="_fee_plan_radio" value="full_service" <?= $savedModel === 'full_service' ? 'checked' : '' ?>>
                            <div class="fee-plan-card-top">
                                <span class="fee-plan-title">🚀 Full Service</span>
                            </div>
                            <div class="fee-plan-pct">30–50%</div>
                            <div class="fee-plan-desc">Maximum platform promotion — ads, referral network, featured categories, and priority placement. Best for high-volume sellers.</div>
                            <div class="fee-plan-upside">💬 Higher chance of word-of-mouth sales from friends and connections.</div>
                        </label>

                        <!-- Referral -->
                        <label class="fee-plan-card<?= $savedModel === 'referral' ? ' active' : '' ?>" id="fpc-referral">
                            <input type="radio" name="_fee_plan_radio" value="referral" <?= $savedModel === 'referral' ? 'checked' : '' ?>>
                            <div class="fee-plan-card-top">
                                <span class="fee-plan-title">✅ Referral Program</span>
                                <span class="fee-plan-badge">Recommended</span>
                            </div>
                            <div class="fee-plan-pct">15%</div>
                            <div class="fee-plan-desc">Members share your listing and earn a commission when their friends buy — putting your product in front of warm, trusted audiences.</div>
                        </label>

                    </div>
                    <span class="pf-hint" style="margin-top:4px;">Platform fee is deducted from your earnings at checkout. Buyers are not shown this fee separately — it is already factored in.</span>
                </div>

                <!-- Markup Rate -->
                <div class="pf-group">
                    <label class="pf-label" for="pf-markup-rate">Markup Rate (%) <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
                    <input class="pf-input" id="pf-markup-rate" type="number" min="0" max="200" step="0.01" name="markup_rate"
                        value="<?= htmlspecialchars((string)($p['markup_rate'] ?? 0)) ?>">
                    <span class="pf-hint">Add a percentage on top of your base price to pass the platform fee on to the buyer, so your net payout stays closer to your listed price. Set to 0 to absorb the fee yourself.</span>
                </div>

            </div>
        </div>

        <!-- Shipping & Weight — hidden for non-physical product types -->
        <div class="pf-section" id="pf-shipping-section">
            <div class="pf-section-header">
                <div class="pf-section-icon">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                </div>
                <h2 class="pf-section-title">Shipping &amp; Weight</h2>
            </div>
            <div class="pf-section-body">
                <div class="pf-hint" style="margin-bottom:14px;background:rgba(214,180,75,0.08);border:1px solid rgba(214,180,75,0.2);border-radius:10px;padding:10px 14px;color:var(--text);line-height:1.6;">
                    <strong>⚠ Include packaging:</strong> Enter the <em>total packed weight and outer box dimensions</em> — this must include the box, bubble wrap, and any other packaging. The shipping fee shown to buyers is calculated from these values. If they are lower than the actual courier measurement, the <strong>difference will be deducted from your payout</strong>.
                </div>
                <div class="pf-grid-2">
                    <div class="pf-group">
                        <label class="pf-label">Packed Weight</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input class="pf-input" id="pf-weight-display" type="number" min="0" style="flex:1;"
                                placeholder="e.g. 500">
                            <select id="pf-weight-unit" class="pf-input" style="width:72px;flex-shrink:0;padding-left:8px;">
                                <option value="g">g</option>
                                <option value="kg">kg</option>
                            </select>
                        </div>
                        <input type="hidden" id="pf-weight" name="weight_kg"
                            value="<?= htmlspecialchars((string)($p['weight_kg'] ?? '')) ?>">
                        <span class="pf-hint">Enter total weight including packaging. Use <strong>g</strong> for grams or <strong>kg</strong> for kilograms.</span>
                    </div>
                    <div class="pf-group" id="pf-liquid-volume-group" style="display:none;">
                        <label class="pf-label" for="pf-volume-ml">Liquid Volume (mL)</label>
                        <input class="pf-input" id="pf-volume-ml" type="number" min="0" step="0.1" name="volume_ml"
                            placeholder="e.g. 500"
                            value="<?= htmlspecialchars($volumeMlValue) ?>">
                        <span class="pf-hint">For liquid items, enter the package volume in milliliters. This appears in notification context and product metadata.</span>
                    </div>
                    <div class="pf-group">
                        <label class="pf-label">Packed Box Dimensions (cm)</label>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
                            <div>
                                <input class="pf-input" id="pf-length" type="number" step="0.1" min="0" name="length_cm"
                                    placeholder="L"
                                    value="<?= htmlspecialchars((string)($p['length_cm'] ?? '')) ?>">
                                <span class="pf-hint" style="text-align:center;display:block">Length</span>
                            </div>
                            <div>
                                <input class="pf-input" type="number" step="0.1" min="0" name="width_cm"
                                    placeholder="W"
                                    value="<?= htmlspecialchars((string)($p['width_cm'] ?? '')) ?>">
                                <span class="pf-hint" style="text-align:center;display:block">Width</span>
                            </div>
                            <div>
                                <input class="pf-input" type="number" step="0.1" min="0" name="height_cm"
                                    placeholder="H"
                                    value="<?= htmlspecialchars((string)($p['height_cm'] ?? '')) ?>">
                                <span class="pf-hint" style="text-align:center;display:block">Height</span>
                            </div>
                        </div>
                        <span class="pf-hint">Outer box size in centimeters. Used to compute volumetric weight (L×W×H÷3500).</span>
                    </div>
                </div>
                <!-- Live estimate preview -->
                <div id="pf-shipping-preview" style="display:none;padding:12px 14px;border-radius:10px;border:1px solid var(--border);background:var(--surface2);font-size:0.84rem;line-height:1.8;color:var(--text);margin-top:4px;">
                    <strong style="color:var(--accent)">📦 Estimated shipping (Metro Luzon):</strong>
                    <span id="pf-shipping-fee">—</span>
                    &nbsp;·&nbsp;Chargeable weight: <span id="pf-shipping-cw">—</span> kg
                    <span id="pf-shipping-note" style="color:var(--muted);font-size:0.78rem;display:block"></span>
                </div>
            </div>
        </div>

        <!-- Delivery Zones — only for physical/liquid products -->
        <div class="pf-section" id="pf-zone-section" style="display:none;">
            <div class="pf-section-header">
                <div class="pf-section-icon">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
                </div>
                <h2 class="pf-section-title">Delivery Zones</h2>
            </div>
            <div class="pf-section-body">
                <div class="pf-hint" style="margin-bottom:14px;line-height:1.6;">
                    By default this product uses your <strong>seller delivery zones</strong>.
                    Enable custom zones below to restrict/expand delivery for just this product.
                </div>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:14px;">
                    <input type="checkbox" id="pf-zone-custom" value="1" style="width:18px;height:18px;accent-color:var(--accent);">
                    <span style="font-size:0.9rem;font-weight:600;">Use custom zones for this product</span>
                </label>
                <div id="pf-zone-editor" style="display:none;">
                    <div id="pf-zone-current" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;min-height:28px;"></div>
                    <div style="display:flex;gap:8px;">
                        <div style="flex:1;position:relative;">
                            <input id="pf-zone-search" type="text" placeholder="Search barangay…" class="pf-input" style="padding-left:32px;">
                            <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);opacity:0.4;" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        </div>
                    </div>
                    <div id="pf-zone-results" style="margin-top:6px;max-height:180px;overflow-y:auto;border:1px solid var(--border);border-radius:10px;display:none;background:var(--bg);"></div>
                    <div style="margin-top:8px;font-size:0.76rem;color:var(--muted);">
                        Zones auto-save when you save the product.
                    </div>
                </div>
            </div>
        </div>

        <!-- Images -->
        <div class="pf-section">
            <div class="pf-section-header">
                <div class="pf-section-icon">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                </div>
                <h2 class="pf-section-title">Product Images</h2>
            </div>
            <div class="pf-section-body">
                <?php if (!empty($existingImgs)): ?>
                <div>
                    <div class="pf-hint" style="margin-bottom:8px">Current images — click <strong style="color:var(--danger)">✕</strong> to remove an image:</div>
                    <div class="existing-imgs" id="existingImgs">
                        <?php foreach ($existingImgs as $imgUrl): ?>
                        <div class="existing-img-wrap" data-url="<?= htmlspecialchars($imgUrl) ?>">
                            <img src="<?= htmlspecialchars($imgUrl) ?>" alt="Product image" loading="lazy">
                            <button type="button" class="existing-img-del" aria-label="Remove image" title="Remove">✕</button>
                            <input type="hidden" name="keep_images[]" value="<?= htmlspecialchars($imgUrl) ?>" class="keep-input">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <div class="img-upload-area" id="imgUploadArea">
                    <input type="file" name="images[]" id="imgFilesInput" multiple accept="image/*" tabindex="-1">
                    <div class="img-upload-icon">🖼️</div>
                    <div class="img-upload-title">Click or drag &amp; drop images</div>
                    <div class="img-upload-sub">JPG, PNG, WebP — up to 10 MB each. First image is the cover.</div>
                </div>
                <div class="img-previews" id="imgPreviews"></div>
            </div>
        </div>

        <div class="pf-actions" style="margin-bottom: 100px;">
            <button type="submit" class="btn btn-primary" style="padding:11px 28px;font-size:0.92rem">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                <?= $editing ? 'Save Changes' : 'Create Product' ?>
            </button>
            <a href="<?= $_pBase ?>" class="btn btn-secondary">Cancel</a>
        </div>

    </form>
</div>

<script>
(function () {
    const area     = document.getElementById('imgUploadArea');
    const input    = document.getElementById('imgFilesInput');
    const previews = document.getElementById('imgPreviews');

    // Track pending files as a mutable array (FileList is read-only)
    let pendingFiles = [];

    function syncInputFiles() {
        if (!input) return;
        try {
            const dt = new DataTransfer();
            pendingFiles.forEach(function (f) { dt.items.add(f); });
            input.files = dt.files;
        } catch (_) {}
    }

    function renderPreviews() {
        if (!previews) return;
        previews.innerHTML = '';
        pendingFiles.forEach(function (file, idx) {
            const wrap = document.createElement('div');
            wrap.className = 'img-prev-item';

            // Delete button
            const del = document.createElement('button');
            del.type = 'button';
            del.className = 'img-prev-del';
            del.setAttribute('aria-label', 'Remove ' + file.name);
            del.textContent = '✕';
            del.addEventListener('click', function () {
                pendingFiles.splice(idx, 1);
                syncInputFiles();
                renderPreviews();
            });
            wrap.appendChild(del);

            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.alt = file.name;
                const reader = new FileReader();
                reader.onload = function (e) { img.src = e.target.result; };
                reader.readAsDataURL(file);
                wrap.appendChild(img);
            } else {
                const ph = document.createElement('div');
                ph.style.cssText = 'width:80px;height:80px;display:flex;align-items:center;justify-content:center;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:1.8rem';
                ph.textContent = '🖼️';
                wrap.appendChild(ph);
            }

            const nameEl = document.createElement('div');
            nameEl.className = 'img-prev-name';
            nameEl.textContent = file.name;
            wrap.appendChild(nameEl);

            previews.appendChild(wrap);
        });
    }

    if (input) {
        input.addEventListener('change', function () {
            Array.from(input.files).forEach(function (f) { pendingFiles.push(f); });
            syncInputFiles();
            renderPreviews();
        });
    }
    if (area) {
        area.addEventListener('dragover',  function (e) { e.preventDefault(); area.classList.add('drag-over'); });
        area.addEventListener('dragleave', function ()  { area.classList.remove('drag-over'); });
        area.addEventListener('drop', function (e) {
            e.preventDefault(); area.classList.remove('drag-over');
            if (e.dataTransfer.files.length) {
                Array.from(e.dataTransfer.files).forEach(function (f) { pendingFiles.push(f); });
                syncInputFiles();
                renderPreviews();
            }
        });
    }

    // Existing image delete buttons
    document.querySelectorAll('.existing-img-del').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const wrap = btn.closest('.existing-img-wrap');
            if (!wrap) return;
            wrap.classList.add('deleted');
            const keepInput = wrap.querySelector('.keep-input');
            if (keepInput) keepInput.disabled = true;
        });
    });

    // Auto-generate slug from title
    const titleInput = document.getElementById('pf-title');
    const slugInput  = document.getElementById('pf-slug');
    if (titleInput && slugInput && !slugInput.value) {
        titleInput.addEventListener('input', function () {
            if (slugInput.dataset.manual) return;
            slugInput.value = titleInput.value.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .trim().replace(/\s+/g, '-');
        });
        slugInput.addEventListener('input', function () { slugInput.dataset.manual = '1'; });
    }

    // ── Product type toggle ───────────────────────────────────────────────
    var ptypeHidden      = document.getElementById('pf-product-type');
    var shippingSection  = document.getElementById('pf-shipping-section');
    var zoneSection      = document.getElementById('pf-zone-section');
    var qtyHint          = document.getElementById('pf-qty-hint');
    var liquidVolumeGroup = document.getElementById('pf-liquid-volume-group');

    function applyProductType(type) {
        document.querySelectorAll('.ptype-btn').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.ptype === type);
        });
        if (ptypeHidden) ptypeHidden.value = type;
        var requiresShipping = (type === 'physical' || type === 'liquid');
        if (shippingSection) {
            shippingSection.style.display = requiresShipping ? '' : 'none';
        }
        if (zoneSection) {
            zoneSection.style.display = requiresShipping ? '' : 'none';
        }
        if (qtyHint) {
            qtyHint.style.display = requiresShipping ? 'none' : '';
        }
        if (liquidVolumeGroup) {
            liquidVolumeGroup.style.display = (type === 'liquid') ? '' : 'none';
        }
    }

    document.querySelectorAll('.ptype-btn').forEach(function (btn) {
        btn.addEventListener('click', function () { applyProductType(btn.dataset.ptype); });
    });

    // init
    applyProductType(ptypeHidden ? ptypeHidden.value : 'physical');

    // ── Product-level zone editor ─────────────────────────────────────────
    (function(){
        var productId = <?= (int)($p['id'] ?? 0) ?>;
        var customCb = document.getElementById('pf-zone-custom');
        var editor = document.getElementById('pf-zone-editor');
        var currentEl = document.getElementById('pf-zone-current');
        var searchEl = document.getElementById('pf-zone-search');
        var resultsEl = document.getElementById('pf-zone-results');
        if (!customCb || !editor) return;
        var pzones = [];

        customCb.addEventListener('change', function(){
            editor.style.display = this.checked ? '' : 'none';
        });

        function htmlesc(s){ var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

        function renderPZones() {
            if (!pzones.length) {
                currentEl.innerHTML = '<span style="font-size:0.82rem;color:var(--muted);">No custom zones — using seller zones.</span>';
                return;
            }
            currentEl.innerHTML = pzones.map(function(z){
                return '<span style="display:inline-flex;align-items:center;gap:4px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:20px;padding:4px 10px;font-size:0.8rem;">'
                    + '📦 ' + htmlesc(z.name+', '+z.city)
                    + '<button type="button" onclick="pfRemoveZone('+z.id+')" style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:0.95rem;padding:0 0 0 4px;">×</button>'
                    + '</span>';
            }).join('');
        }

        window.pfRemoveZone = function(id){
            pzones = pzones.filter(function(z){ return z.id !== id; });
            renderPZones();
        };

        window.pfAddZone = function(id, name, city, province){
            id = parseInt(id);
            if (pzones.some(function(z){ return z.id === id; })) return;
            if (pzones.length >= 50) { alert('Maximum 50 zones'); return; }
            pzones.push({id:id, name:name, city:city, province:province});
            resultsEl.style.display = 'none'; searchEl.value = '';
            renderPZones();
        };

        // Register a GeoNames place then add as zone
        window.pfAddGeo = function(geoId, name, city, province){
            var numericGeoId = parseInt(String(geoId).replace('geo_', ''));
            var body = new URLSearchParams();
            body.append('geoname_id', numericGeoId);
            fetch('/api/barangay/register-geo', {method:'POST', body:body})
                .then(function(r){ return r.json(); })
                .then(function(d){
                    if (d.success && d.barangay) {
                        pfAddZone(d.barangay.id, d.barangay.name, d.barangay.city||city, d.barangay.province||province);
                    }
                }).catch(function(){});
        };

        // Load existing product zones from server-side data (pre-loaded in controller)
        var serverZones = <?= json_encode($product_zones ?? []) ?>;
        var serverUseCustom = <?= json_encode($use_custom_zones ?? false) ?>;
        if (serverUseCustom) {
            customCb.checked = true;
            editor.style.display = '';
        }
        if (serverZones && serverZones.length) {
            pzones = serverZones.map(function(z){ return {id:parseInt(z.id), name:z.name, city:z.city, province:z.province||''}; });
        }
        renderPZones();

        // Search
        var _st;
        searchEl.addEventListener('input', function(){
            var q = this.value.trim(); clearTimeout(_st);
            if (q.length < 2) { resultsEl.style.display = 'none'; return; }
            _st = setTimeout(function(){
                fetch('/api/barangay/list?q='+encodeURIComponent(q)+'&limit=20')
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        if (!d.barangays||!d.barangays.length) { resultsEl.innerHTML='<div style="padding:10px 14px;color:var(--muted);font-size:0.84rem;">No results</div>'; resultsEl.style.display='block'; return; }
                        resultsEl.innerHTML = d.barangays.map(function(b){
                            var isGeo = String(b.id).indexOf('geo_') === 0;
                            var numId = isGeo ? 0 : parseInt(b.id);
                            var added = !isGeo && pzones.some(function(z){ return z.id===numId; });
                            var badge = isGeo ? ' <span style="color:#6366f1;font-size:0.68rem;background:rgba(99,102,241,0.12);padding:1px 5px;border-radius:3px;margin-left:4px;">🌍</span>' : '';
                            var onclick = added ? '' : (isGeo
                                ? 'pfAddGeo('+JSON.stringify(b.id)+','+JSON.stringify(b.name)+','+JSON.stringify(b.city||'')+','+JSON.stringify(b.province||'')+')'
                                : 'pfAddZone('+b.id+',\''+b.name.replace(/'/g,'\\\'')+'\',\''+(b.city||'').replace(/'/g,'\\\'')+'\',\''+(b.province||'').replace(/'/g,'\\\'')+'\')');
                            return '<div onclick="'+onclick+'" style="padding:8px 14px;cursor:'+(added?'default':'pointer')+';border-bottom:1px solid var(--border);font-size:0.85rem;'+(added?'opacity:0.35;':'')+'">'
                                + htmlesc(b.name+', '+(b.city||'')) + ' <span style="color:var(--muted);font-size:0.76rem;">'+htmlesc(b.province||b.region||'')+'</span>' + badge
                                + (added?' <span style="color:#22c55e;font-size:0.76rem;">✓</span>':' <span style="color:var(--accent);font-size:0.76rem;">+ Add</span>')
                                + '</div>';
                        }).join('');
                        resultsEl.style.display = 'block';
                    }).catch(function(){});
            }, 250);
        });

        // Hook into the product form submit to save zones
        var form = document.querySelector('form[action]');
        if (form) {
            form.addEventListener('submit', function(){
                // Inject hidden fields for zone data
                var existing = form.querySelectorAll('.pf-zone-hidden'); existing.forEach(function(e){ e.remove(); });
                var h = document.createElement('input'); h.type='hidden'; h.name='use_custom_zones'; h.value=customCb.checked?'1':'0'; h.className='pf-zone-hidden'; form.appendChild(h);
                pzones.forEach(function(z){
                    var inp = document.createElement('input'); inp.type='hidden'; inp.name='product_zone_ids[]'; inp.value=z.id; inp.className='pf-zone-hidden'; form.appendChild(inp);
                });
            });
        }
    })();

    // ── Fee plan radio cards ──────────────────────────────────────────────
    var pricingModelHidden = document.getElementById('pf-pricing-model');
    var pricingRateHidden  = document.getElementById('pf-pricing-rate');

    var planRates = { hands_off: '12', active_discovery: '25', full_service: '35', referral: '15', markup: '0', standard: '10' };
    var planCardIds = ['fpc-hands_off', 'fpc-active_discovery', 'fpc-full_service', 'fpc-referral'];

    document.querySelectorAll('input[name="_fee_plan_radio"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            var plan = radio.value;
            if (pricingModelHidden) pricingModelHidden.value = plan;
            if (pricingRateHidden)  pricingRateHidden.value  = planRates[plan] || '12';
            planCardIds.forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.classList.toggle('active', id === 'fpc-' + plan);
            });
        });
    });
}());

// ===== KYC GATE =====
(function () {
    var overlay = document.getElementById('kycGateOverlay');
    if (overlay) {
        // Prevent closing by clicking backdrop — user must take action
        overlay.addEventListener('click', function (e) { e.stopPropagation(); });
    }
}());

// ===== TERMS OF SERVICE MODAL =====
(function () {
    var tosOverlay  = document.getElementById('tosOverlay');
    var tosCheckbox = document.getElementById('tosCheckbox');
    var tosBtnAgree = document.getElementById('tosBtnAgree');
    var csrfToken   = '<?= htmlspecialchars($csrf_token) ?>';

    if (!tosOverlay) {
        // TOS already agreed server-side — also check localStorage (belt & braces)
        return;
    }

    // Enable agree button only when checkbox is ticked
    if (tosCheckbox && tosBtnAgree) {
        tosCheckbox.addEventListener('change', function () {
            tosBtnAgree.disabled = !tosCheckbox.checked;
        });
    }

    if (tosBtnAgree) {
        tosBtnAgree.addEventListener('click', function () {
            if (!tosCheckbox || !tosCheckbox.checked) return;

            // Persist locally immediately so modal doesn't flash on retry
            try { localStorage.setItem('epower_seller_tos_v1', '1'); } catch (_) {}

            // Record server-side via AJAX
            var fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fetch('/marketplace/sellers/tos/agree', { method: 'POST', body: fd })
                .catch(function () { /* best-effort */ });

            // Dismiss modal and let user continue
            tosOverlay.classList.add('hidden');
        });
    }

    // Respect prior localStorage agreement (e.g., server hasn't updated yet)
    try {
        if (localStorage.getItem('epower_seller_tos_v1') === '1') {
            if (tosOverlay) tosOverlay.classList.add('hidden');
        }
    } catch (_) {}
}());

// ── Product colors tag input ────────────────────────────────────────────────
(function () {
    var wrap    = document.getElementById('pf-color-tags-wrap');
    var input   = document.getElementById('pf-color-input');
    var hidden  = document.getElementById('pf-colors-hidden');
    if (!wrap || !input || !hidden) return;

    var colors = [];
    try { colors = JSON.parse(hidden.value || '[]'); } catch (_) { colors = []; }
    if (!Array.isArray(colors)) colors = [];

    function render() {
        wrap.querySelectorAll('.color-tag').forEach(function (t) { t.remove(); });
        colors.forEach(function (c) {
            var tag = document.createElement('span');
            tag.className = 'color-tag';
            var txt = document.createTextNode(c + '\u00a0');
            tag.appendChild(txt);
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'color-tag-close';
            btn.setAttribute('aria-label', 'Remove ' + c);
            btn.textContent = '×';
            btn.addEventListener('click', function () {
                colors = colors.filter(function (x) { return x !== c; });
                render();
            });
            tag.appendChild(btn);
            wrap.insertBefore(tag, input);
        });
        hidden.value = JSON.stringify(colors);
    }

    function addColor(raw) {
        var c = raw.trim().replace(/,+$/, '').trim();
        if (!c || c.length > 40) return;
        c = c.charAt(0).toUpperCase() + c.slice(1);
        if (!colors.includes(c)) { colors.push(c); render(); }
    }

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addColor(input.value);
            input.value = '';
        } else if (e.key === 'Backspace' && input.value === '' && colors.length > 0) {
            colors.pop();
            render();
        }
    });

    input.addEventListener('blur', function () {
        if (input.value.trim()) { addColor(input.value); input.value = ''; }
    });

    document.querySelectorAll('#pf-color-presets .color-preset-btn').forEach(function (btn) {
        btn.addEventListener('click', function () { addColor(btn.dataset.color); input.focus(); });
    });

    wrap.addEventListener('click', function (e) {
        if (e.target === wrap) input.focus();
    });

    render();
}());

// ── Packed weight g/kg unit toggle ──────────────────────────────────────────
(function () {
    var display = document.getElementById('pf-weight-display');
    var unit    = document.getElementById('pf-weight-unit');
    var hidden  = document.getElementById('pf-weight');
    if (!display || !unit || !hidden) return;

    // Populate display from existing kg value (edit page)
    var existingKg = parseFloat(hidden.value);
    if (!isNaN(existingKg) && existingKg > 0) {
        if (existingKg < 1) {
            unit.value = 'g';
            display.value = Math.round(existingKg * 1000);
            display.step = '1';
        } else {
            unit.value = 'kg';
            display.value = existingKg;
            display.step = '0.001';
        }
    } else {
        unit.value = 'g';
        display.step = '1';
    }
    display.dataset.prevUnit = unit.value;

    function sync() {
        var v = parseFloat(display.value) || 0;
        hidden.value = unit.value === 'g' ? (v / 1000).toFixed(3) : v.toFixed(3);
        hidden.dispatchEvent(new Event('input'));
    }

    display.addEventListener('input', sync);

    unit.addEventListener('change', function () {
        var v = parseFloat(display.value) || 0;
        var prev = display.dataset.prevUnit || 'g';
        if (unit.value === 'g' && prev === 'kg') {
            display.value = Math.round(v * 1000);
            display.step = '1';
        } else if (unit.value === 'kg' && prev === 'g') {
            display.value = (v / 1000).toFixed(3);
            display.step = '0.001';
        }
        display.dataset.prevUnit = unit.value;
        sync();
    });

    // Ensure hidden is synced on form submit
    var form = display.closest('form');
    if (form) form.addEventListener('submit', sync, true);
}());

// ── Live shipping estimate preview ──────────────────────────────────────────
(function () {
    var DIVISOR = 3500; // conservative safe divisor
    var ZONE_RATES = {
        metro_luzon: { base: 80, per_kg: 15, free_kg: 1 }
    };

    function volWeight(l, w, h) {
        return (l > 0 && w > 0 && h > 0) ? (l * w * h) / DIVISOR : 0;
    }

    function estimate(weightKg, l, w, h) {
        var actual = Math.max(0, weightKg);
        var vol    = volWeight(l, w, h);
        var cw     = Math.max(actual, vol, 0.5);
        var r      = ZONE_RATES.metro_luzon;
        var fee    = r.base + Math.max(0, cw - r.free_kg) * r.per_kg;
        return { cw: cw, fee: fee };
    }

    var wInput = document.getElementById('pf-weight');
    var lInput = document.getElementById('pf-length');
    var preview = document.getElementById('pf-shipping-preview');
    var feeEl   = document.getElementById('pf-shipping-fee');
    var cwEl    = document.getElementById('pf-shipping-cw');
    var noteEl  = document.getElementById('pf-shipping-note');

    function update() {
        if (!wInput || !preview) return;
        var wv = parseFloat(wInput.value) || 0;
        var lv = parseFloat(lInput ? lInput.value : 0) || 0;
        var wv2 = parseFloat((document.querySelector('[name="width_cm"]') || {}).value) || 0;
        var hv  = parseFloat((document.querySelector('[name="height_cm"]') || {}).value) || 0;

        if (wv <= 0 && lv <= 0) { preview.style.display = 'none'; return; }

        var r = estimate(wv, lv, wv2, hv);
        feeEl.textContent = '₱' + r.fee.toFixed(2);
        cwEl.textContent  = r.cw.toFixed(3);

        var incomplete = lv <= 0 || wv2 <= 0 || hv <= 0;
        noteEl.textContent = incomplete
            ? '⚠ Dimensions incomplete — volumetric weight not applied. Complete L×W×H for an accurate estimate.'
            : 'Volumetric weight: ' + volWeight(lv, wv2, hv).toFixed(3) + ' kg · Estimate for Metro Luzon zone.';
        preview.style.display = 'block';
    }

    ['pf-weight', 'pf-length'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', update);
    });
    ['[name="width_cm"]', '[name="height_cm"]'].forEach(function (sel) {
        var el = document.querySelector(sel);
        if (el) el.addEventListener('input', update);
    });

    update(); // populate on edit page
}());
</script>

<?php include __DIR__ . '/parts/footer.php'; ?>
</body>
</html>