<?php
/** @var array|null $kyc */
/** @var string $csrf_token */
?>
<?php
$title      = $title ?? 'Identity Verification — Seller KYC';
$isLoggedIn = true;

$kycStatus = $kyc['status'] ?? null;
$submitted = !empty($kyc);
$docs      = (!empty($kyc['documents'])) ? (json_decode($kyc['documents'], true) ?: []) : [];

// Step: 1 = not submitted, 2 = under review, 3 = approved/rejected
$step = 1;
if ($submitted && $kycStatus === 'pending')                        $step = 2;
if ($submitted && in_array($kycStatus, ['approved', 'rejected'])) $step = 3;

// Wizard helpers
$tosAgreed        = $submitted; // pre-check TOS for returning/re-submitters
$savedAccountType = $kyc['account_type'] ?? '';

$countries = [
    'Philippines','Nigeria','United States','United Kingdom','Canada','Australia',
    'India','Germany','France','Singapore','Malaysia','Indonesia','South Africa',
    'Kenya','Ghana','Brazil','Mexico','Pakistan','Bangladesh','Vietnam',
];
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<style>
/* ===== KYC PAGE ===== */
.kyc-shell {
    max-width: 700px;
    margin: 36px auto 72px;
    padding: 0 16px;
}
.kyc-back-link {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 0.84rem; color: var(--muted);
    margin-bottom: 22px;
    transition: color var(--trans);
}
.kyc-back-link:hover { color: var(--text); }
.kyc-page-title    { font-size: 1.45rem; font-weight: 800; margin-bottom: 3px; }
.kyc-page-subtitle { font-size: 0.855rem; color: var(--muted); margin-bottom: 28px; }

/* Steps indicator */
.kyc-steps {
    display: flex; align-items: center;
    margin-bottom: 32px;
}
.kyc-step {
    display: flex; align-items: center; gap: 8px;
    flex: 1; position: relative;
}
.kyc-step:not(:last-child)::after {
    content: '';
    position: absolute;
    left: calc(18px + 8px); right: 0; top: 18px;
    height: 2px;
    background: var(--border);
}
.kyc-step.done::after   { background: #22c55e; }
.kyc-step-num {
    width: 36px; height: 36px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.84rem; flex-shrink: 0;
    position: relative; z-index: 1;
    border: 2px solid var(--border);
    background: var(--bg); color: var(--muted);
    transition: all var(--trans);
}
.kyc-step.done .kyc-step-num   { background: #22c55e; border-color: #22c55e; color: white; }
.kyc-step.active .kyc-step-num { background: var(--accent); border-color: var(--accent); color: white; }
.kyc-step-info { flex: 1; }
.kyc-step-label {
    font-size: 0.8rem; font-weight: 600; color: var(--muted);
    white-space: nowrap;
}
.kyc-step.done .kyc-step-label,
.kyc-step.active .kyc-step-label { color: var(--text); }
.kyc-step-sub { font-size: 0.7rem; color: var(--muted); }

/* Status banner */
.kyc-banner {
    display: flex; align-items: flex-start; gap: 14px;
    padding: 18px 20px; border-radius: var(--radius);
    margin-bottom: 26px;
}
.kyc-banner.pending  { background: rgba(245,158,11,0.09); border: 1px solid rgba(245,158,11,0.35); }
.kyc-banner.approved { background: rgba(34,197,94,0.09);  border: 1px solid rgba(34,197,94,0.35);  }
.kyc-banner.rejected { background: rgba(239,68,68,0.09);  border: 1px solid rgba(239,68,68,0.35);  }
.kyc-banner.none     { background: rgba(59,130,246,0.07); border: 1px solid rgba(59,130,246,0.25); }
.kyc-banner-icon  { font-size: 1.7rem; flex-shrink: 0; line-height: 1; margin-top: 2px; }
.kyc-banner-title { font-weight: 700; font-size: 0.975rem; margin-bottom: 4px; }
.kyc-banner-body  { font-size: 0.845rem; color: var(--muted); line-height: 1.6; }

/* Review notes box */
.review-notes-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.07em; color: var(--muted); font-weight: 700; margin-bottom: 6px; margin-top: 12px; }
.review-notes-box {
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: var(--radius-sm); padding: 13px 15px;
    font-size: 0.855rem; line-height: 1.65; white-space: pre-line;
}

/* Card */
.kyc-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    margin-bottom: 16px;
}
.kyc-card-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 10px;
}
.kyc-card-icon {
    width: 32px; height: 32px; border-radius: 8px;
    background: rgba(59,130,246,0.1); color: var(--accent);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.kyc-card-title { font-size: 0.95rem; font-weight: 700; }
.kyc-card-body  { padding: 20px; }

/* Form */
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-group   { display: flex; flex-direction: column; gap: 5px; }
.form-label   { font-size: 0.82rem; font-weight: 600; }
.form-label .req { color: var(--danger); margin-left: 2px; }
.form-input {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 9px 12px; width: 100%;
    color: var(--text); font-size: 0.89rem; font-family: inherit;
    outline: none;
    transition: border-color var(--trans), box-shadow var(--trans);
}
.form-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,130,246,0.12); }
.form-input::placeholder { color: var(--muted); }
select.form-input            { cursor: pointer; }
input[type="file"].form-input { cursor: pointer; padding: 7px 12px; }
.form-hint { font-size: 0.76rem; color: var(--muted); }

/* Docs upload area */
.doc-upload-area {
    border: 2px dashed var(--border);
    border-radius: var(--radius);
    padding: 30px 20px; text-align: center;
    cursor: pointer; position: relative;
    background: var(--surface2);
    transition: border-color var(--trans), background var(--trans);
}
.doc-upload-area:hover,
.doc-upload-area.drag-over { border-color: var(--accent); background: rgba(59,130,246,0.04); }
.doc-upload-area input[type="file"] {
    position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
}
.upload-icon  { font-size: 2rem; margin-bottom: 8px; }
.upload-title { font-weight: 600; font-size: 0.9rem; margin-bottom: 4px; }
.upload-sub   { font-size: 0.78rem; color: var(--muted); }

/* Existing docs list */
.doc-list { display: flex; flex-wrap: wrap; gap: 8px; }
.doc-item {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 7px 12px;
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: 0.8rem; color: var(--text); text-decoration: none;
    transition: background var(--trans);
}
.doc-item:hover { background: var(--border); }

/* Preview */
.doc-previews { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; }
.doc-prev-item { display: flex; flex-direction: column; align-items: center; gap: 3px; }
.doc-prev-item img {
    width: 68px; height: 68px; object-fit: cover;
    border-radius: 8px; border: 1px solid var(--border);
}
.doc-prev-name { font-size: 0.65rem; color: var(--muted); max-width: 68px; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Collapsible expand summary */
details summary {
    cursor: pointer;
    padding: 14px 20px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    font-weight: 600; font-size: 0.9rem;
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 16px;
    list-style: none;
}
details summary::-webkit-details-marker { display: none; }

@media (max-width: 580px) {
    .form-grid-2 { grid-template-columns: 1fr; }
    .kyc-step-info { display: none; }
}

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

/* Doc checklist label */
.doc-check-label {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 10px;
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: var(--radius-sm); cursor: pointer;
    font-size: 0.82rem; transition: background var(--trans);
}
.doc-check-label:hover { background: var(--border); }

/* ===== KYC WIZARD ===== */
.kyc-wizard-nav {
    display: flex; align-items: stretch;
    border: 1px solid var(--border); border-radius: var(--radius);
    overflow: hidden; background: var(--surface);
    margin-bottom: 24px;
}
.kwn-step {
    flex: 1; display: flex; flex-direction: column; align-items: center;
    padding: 10px 4px; border-right: 1px solid var(--border);
    text-align: center; transition: background var(--trans);
}
.kwn-step:last-child { border-right: none; }
.kwn-step-num {
    width: 22px; height: 22px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.68rem; font-weight: 700;
    background: var(--surface2); color: var(--muted);
    border: 1.5px solid var(--border); margin-bottom: 4px;
    transition: all var(--trans);
}
.kwn-step-label { font-size: 0.6rem; color: var(--muted); font-weight: 500; line-height: 1.2; }
.kwn-step.wdone .kwn-step-num  { background: #22c55e; border-color: #22c55e; color: white; }
.kwn-step.wdone .kwn-step-num::before { content: '✓'; }
.kwn-step.wactive .kwn-step-num { background: var(--accent); border-color: var(--accent); color: white; }
.kwn-step.wactive { background: rgba(59,130,246,0.05); }
.kwn-step.wactive .kwn-step-label { color: var(--text); font-weight: 700; }

/* Wizard panels */
.kyc-wstep         { display: none; }
.kyc-wstep.wactive { display: block; }

/* TOS scroll box */
.kyc-tos-box {
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: var(--radius-sm); padding: 18px 20px;
    max-height: 320px; overflow-y: auto;
    font-size: 0.81rem; color: var(--muted); line-height: 1.75;
    margin-bottom: 14px;
    scrollbar-width: thin;
}
.kyc-tos-box h3 { font-size: 0.87rem; font-weight: 700; color: var(--text); margin: 14px 0 4px; }
.kyc-tos-box h3:first-child { margin-top: 0; }
.kyc-tos-box ul  { padding-left: 18px; margin: 6px 0; }

/* Account type cards */
.acct-type-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(175px, 1fr));
    gap: 9px;
}
.acct-type-group { margin-bottom: 18px; }
.acct-type-group-label {
    font-size: 0.71rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.07em; color: var(--muted);
    margin-bottom: 8px; padding-bottom: 6px;
    border-bottom: 1px solid var(--border);
}
.acct-type-card { position: relative; cursor: pointer; }
.acct-type-card input { position: absolute; opacity: 0; pointer-events: none; }
.acct-type-label {
    display: flex; flex-direction: column; align-items: flex-start; gap: 2px;
    padding: 11px 13px;
    background: var(--surface2); border: 1.5px solid var(--border);
    border-radius: var(--radius-sm); cursor: pointer;
    transition: all var(--trans);
}
.acct-type-label:hover { border-color: var(--accent); background: rgba(59,130,246,0.05); }
.acct-type-card input:checked + .acct-type-label {
    border-color: var(--accent); background: rgba(59,130,246,0.1);
    box-shadow: 0 0 0 3px rgba(59,130,246,0.12);
}
.acct-type-emoji { font-size: 1.05rem; margin-bottom: 2px; }
.acct-type-name  { font-size: 0.82rem; font-weight: 700; color: var(--text); }
.acct-type-desc  { font-size: 0.69rem; color: var(--muted); line-height: 1.35; }

/* Wizard nav buttons */
.kyc-wiz-btns {
    display: flex; align-items: center; gap: 10px;
    margin-top: 22px; padding-top: 16px;
    border-top: 1px solid var(--border);
}
.kyc-wiz-btns .kwb-space { flex: 1; }
.kyc-wiz-btns .kwb-back {
    background: var(--surface2); border: 1px solid var(--border);
    color: var(--text); padding: 9px 20px;
    border-radius: var(--radius-sm); font-size: 0.87rem;
    cursor: pointer; transition: background var(--trans); font-family: inherit;
}
.kyc-wiz-btns .kwb-back:hover { background: var(--border); }
.kyc-wiz-btns .kwb-next { padding: 9px 24px; font-size: 0.87rem; font-weight: 600; }
</style>
<body>
<?php include __DIR__ . '/parts/header.php'; ?>
<div id="sidebarBackdrop" class="sidebar-backdrop" aria-hidden="true"></div>
<!-- Mobile seller nav drawer (hamburger target) -->
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Seller navigation">
    <div class="sidebar-close-row" id="sidebarCloseRow">
        <div class="sidebar-close-logo">
            <img src="/assets/images/ginto.png" alt="Ginto">
            <span>ePower</span>
        </div>
        <button class="sidebar-close-btn" id="sidebarClose" aria-label="Close menu">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div class="sidebar-inner" style="padding:16px 0">
        <ul class="sc-nav" role="list" style="list-style:none;margin:0;padding:0">
            <li class="sc-nav-item"><a href="/marketplace/sellers/products" style="display:flex;align-items:center;gap:8px;padding:10px 18px;font-size:0.875rem;color:var(--text);text-decoration:none">📦 My Products</a></li>
            <li class="sc-nav-item"><a href="/marketplace/sellers/products/new" style="display:flex;align-items:center;gap:8px;padding:10px 18px;font-size:0.875rem;color:var(--text);text-decoration:none">➕ New Product</a></li>
            <li class="sc-nav-item"><a href="/marketplace/sellers/kyc" style="display:flex;align-items:center;gap:8px;padding:10px 18px;font-size:0.875rem;color:var(--accent);text-decoration:none;font-weight:600">🪪 KYC Verification</a></li>
            <li class="sc-nav-item"><a href="/marketplace" style="display:flex;align-items:center;gap:8px;padding:10px 18px;font-size:0.875rem;color:var(--text);text-decoration:none">🏠 View Marketplace</a></li>
        </ul>
    </div>
</aside>

<div class="kyc-shell">

    <a href="/marketplace/sellers/products" class="kyc-back-link">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Products
    </a>

    <h1 class="kyc-page-title">Identity Verification</h1>
    <p class="kyc-page-subtitle">Verify your identity to publish and sell on ePower Mall</p>

    <!-- Steps -->
    <div class="kyc-steps" aria-label="Verification progress" role="list">
        <div class="kyc-step <?= $step > 1 ? 'done' : ($step === 1 ? 'active' : '') ?>" role="listitem">
            <div class="kyc-step-num">
                <?php if ($step > 1): ?>
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                <?php else: ?>1<?php endif; ?>
            </div>
            <div class="kyc-step-info">
                <div class="kyc-step-label">Submit Details</div>
                <div class="kyc-step-sub">Personal info &amp; ID docs</div>
            </div>
        </div>
        <div class="kyc-step <?= $step > 2 ? 'done' : ($step === 2 ? 'active' : '') ?>" role="listitem">
            <div class="kyc-step-num">
                <?php if ($step > 2): ?>
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                <?php else: ?>2<?php endif; ?>
            </div>
            <div class="kyc-step-info">
                <div class="kyc-step-label">Under Review</div>
                <div class="kyc-step-sub">1–3 business days</div>
            </div>
        </div>
        <div class="kyc-step <?= $step === 3 ? ($kycStatus === 'approved' ? 'done' : 'active') : '' ?>" role="listitem">
            <div class="kyc-step-num">3</div>
            <div class="kyc-step-info">
                <div class="kyc-step-label">Decision</div>
                <div class="kyc-step-sub">Approved or rejected</div>
            </div>
        </div>
    </div>

    <!-- Status banner -->
    <?php if ($kycStatus === 'approved'): ?>
    <div class="kyc-banner approved" role="status">
        <div class="kyc-banner-icon">✅</div>
        <div>
            <div class="kyc-banner-title" style="color:#22c55e">You're Verified!</div>
            <div class="kyc-banner-body">Your identity has been confirmed. You can now list and publish products on ePower Mall.</div>
        </div>
    </div>

    <?php elseif ($kycStatus === 'pending'): ?>
    <div class="kyc-banner pending" role="status">
        <div class="kyc-banner-icon">⏳</div>
        <div>
            <div class="kyc-banner-title" style="color:#f59e0b">Application Under Review</div>
            <div class="kyc-banner-body">Your documents have been submitted and are being reviewed. This typically takes 1–3 business days. You'll receive an email with the outcome.
                <?php if (!empty($kyc['submitted_at'])): ?>
                    <br><span style="font-size:0.78rem;margin-top:6px;display:inline-block">Submitted: <?= htmlspecialchars(date('F j, Y \a\t g:i A', strtotime($kyc['submitted_at']))) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php elseif ($kycStatus === 'rejected'): ?>
    <div class="kyc-banner rejected" role="alert">
        <div class="kyc-banner-icon">❌</div>
        <div>
            <div class="kyc-banner-title" style="color:#ef4444">Application Rejected</div>
            <div class="kyc-banner-body">Your KYC was not approved. Please review the notes below and resubmit with corrected or clearer documents.</div>
            <?php if (!empty($kyc['review_notes'])): ?>
            <div class="review-notes-label">Reviewer Notes</div>
            <div class="review-notes-box"><?= htmlspecialchars($kyc['review_notes']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <?php else: ?>
    <div class="kyc-banner none" role="note">
        <div class="kyc-banner-icon">🔒</div>
        <div>
            <div class="kyc-banner-title" style="color:var(--accent)">Your information is protected</div>
            <div class="kyc-banner-body">All documents are encrypted at rest and in transit. They are only used to verify your identity and are never shared with third parties.</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Existing submitted docs (if any) -->
    <?php if ($submitted && !empty($docs) && $kycStatus !== 'rejected'): ?>
    <div class="kyc-card" style="margin-bottom:20px">
        <div class="kyc-card-header">
            <div class="kyc-card-icon">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
            <h2 class="kyc-card-title">Submitted Documents</h2>
        </div>
        <div class="kyc-card-body">
            <div class="doc-list">
                <?php foreach ($docs as $i => $doc): ?>
                <a href="<?= htmlspecialchars($doc) ?>" target="_blank" rel="noopener noreferrer" class="doc-item">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Document <?= $i + 1 ?>
                    <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- KYC Form -->
    <?php $showCollapsed = $submitted && $kycStatus !== 'rejected' && $kycStatus !== null; ?>
    <?php if ($showCollapsed): ?>
    <details>
        <summary>
            <span><?= $kycStatus === 'approved' ? 'View my submission' : 'Update submission' ?></span>
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
        </summary>
    <?php endif; ?>

    <form method="POST" action="/marketplace/sellers/kyc/submit" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <!-- Wizard step indicator (inside the form) -->
        <div class="kyc-wizard-nav" id="kycWizNav" role="list" aria-label="Application steps">
            <div class="kwn-step wactive" id="kwn1" role="listitem"><div class="kwn-step-num">1</div><div class="kwn-step-label">Terms</div></div>
            <div class="kwn-step" id="kwn2" role="listitem"><div class="kwn-step-num">2</div><div class="kwn-step-label">Account Type</div></div>
            <div class="kwn-step" id="kwn3" role="listitem"><div class="kwn-step-num">3</div><div class="kwn-step-label">Your Info</div></div>
            <div class="kwn-step" id="kwn4" role="listitem"><div class="kwn-step-num">4</div><div class="kwn-step-label">Documents</div></div>
            <div class="kwn-step" id="kwn5" role="listitem"><div class="kwn-step-num">5</div><div class="kwn-step-label">Submit</div></div>
        </div>

        <!-- ===================== STEP 1: TERMS ===================== -->
        <div class="kyc-wstep wactive" id="kycStep1">
            <div class="kyc-card">
                <div class="kyc-card-header">
                    <div class="kyc-card-icon">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    </div>
                    <h2 class="kyc-card-title">Terms &amp; Conditions — Please Read Before Proceeding</h2>
                </div>
                <div class="kyc-card-body">
                    <p style="font-size:0.83rem;color:var(--muted);margin-bottom:13px">Read all terms below. You must agree to continue your identity verification.</p>
                    <div class="kyc-tos-box" id="kycTosBox">
                        <h3>1. Identity Verification &amp; Data Privacy (RA 10173)</h3>
                        <p>Under the Philippines Data Privacy Act of 2012 (Republic Act No. 10173), your personal information and identity documents are collected solely for identity verification. Your data is processed with your consent, encrypted at rest and in transit, and will not be shared with any third party without your explicit written permission, except as required by law. You have the right to access, correct, and request deletion of your data by writing to <strong>privacy@ginto.ai</strong>.</p>
                        <h3>2. Anti-Money Laundering Compliance (RA 9160)</h3>
                        <p>ePower Mall is obligated under the Anti-Money Laundering Act to verify the identity of all sellers. Submitting fraudulent documents or inaccurate information may result in immediate account suspension and referral to appropriate government authorities.</p>
                        <h3>3. Business Name Registration (RA 3883)</h3>
                        <p>Commercial sellers operating under a business name are required by law to register with the DTI, SEC, or CDA. ePower Mall may require proof of registration for business accounts as required under RA 3883 (Business Name Law).</p>
                        <h3>4. BIR Tax Obligations (RMC 60-2020)</h3>
                        <p>All persons engaging in online selling are required by BIR Revenue Memorandum Circular 60-2020 to register as taxpayers, maintain books of accounts, and issue official receipts. By selling on this platform, you agree to comply with your BIR registration and tax obligations.</p>
                        <h3>5. Payment Disbursement Policy</h3>
                        <p>Payments for completed orders are held for <strong>seven (7) calendar days</strong> from the Order Completed / Delivered date to ensure buyer protection and transaction integrity. Disbursements are credited to your registered payment method within 2 business days after the holding period ends.</p>
                        <h3>6. Authentic Listings &amp; Anti-Fraud</h3>
                        <p>You agree to list only products and services that you legally own, possess, or have the right to sell. Listing counterfeit goods, pirated content, fraudulent services, or items prohibited under Philippine law (including RA 8293 — Intellectual Property Code) is strictly prohibited and may result in permanent termination and legal action.</p>
                        <h3>7. Shipping &amp; Fulfillment</h3>
                        <ul>
                            <li>Orders must be prepared and handed to the courier within the committed timeframe stated in your listing.</li>
                            <li>Failure to ship confirmed orders without valid reason may result in account penalties.</li>
                        </ul>
                        <h3>8. Platform Rules</h3>
                        <ul>
                            <li>Product listings must be accurate and comply with consumer protection laws (RA 7394).</li>
                            <li>Prices must include all applicable taxes and charges.</li>
                            <li>Abuse of the platform (fake reviews, spam, fee circumvention) will result in immediate termination.</li>
                        </ul>
                        <h3>9. Seller Eligibility — Minimum Age Requirement</h3>
                        <p>You must be at least <strong>18 years of age</strong> to register and sell on ePower Mall. By submitting this application, you confirm that you are 18 years old or older. Minors may not create seller accounts. If a minor is found to have submitted a false date of birth, the account will be immediately suspended and any earnings held pending legal guardian verification, in accordance with RA 7610 (Special Protection of Children Against Abuse) and applicable civil law provisions.</p>
                        <h3>10. Intellectual Property (RA 8293)</h3>
                        <p>Sellers of digital content, creative works, software, music, videos, e-books, courses, or any intellectual property must hold the legal right to distribute or sell such works. Uploading, listing, or selling pirated, plagiarized, or unauthorized copies of copyrighted materials is strictly prohibited under the Intellectual Property Code of the Philippines (RA 8293) and may result in civil and criminal liability.</p>
                        <h3>11. Data Retention</h3>
                        <p>KYC documents and identity records are retained per applicable law. Deletion requests can be submitted to our Data Privacy Officer at <strong>privacy@ginto.ai</strong>, subject to legal retention obligations under RA 10173, Section 11(c).</p>
                    </div>
                    <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-size:0.83rem;padding:12px 14px;background:rgba(59,130,246,0.06);border:1px solid rgba(59,130,246,0.2);border-radius:var(--radius-sm);margin-bottom:4px">
                        <input type="checkbox" id="kycTosCheck" style="margin-top:3px;accent-color:var(--accent);flex-shrink:0" <?= $tosAgreed ? 'checked' : '' ?>>
                        <span>I have read and fully agree to the <strong>Seller Terms &amp; Conditions</strong>, including data collection under <strong>RA 10173</strong>, anti-money laundering compliance under <strong>RA 9160</strong>, and the 7-day payment disbursement policy. I confirm that I am <strong>18 years of age or older</strong>. I understand this is a legally binding agreement.</span>
                    </label>
                    <div id="kycTosErr" style="color:var(--danger);font-size:0.77rem;margin-top:4px;display:none">⚠ You must agree to the terms before continuing.</div>
                    <div class="kyc-wiz-btns" style="justify-content:flex-end">
                        <button type="button" class="btn btn-primary kwb-next" onclick="kycWizardGoTo(2)">Continue →</button>
                    </div>
                </div>
            </div>
        </div><!-- /kycStep1 -->

        <!-- ================ STEP 2: ACCOUNT TYPE ================= -->
        <div class="kyc-wstep" id="kycStep2">
            <div class="kyc-card">
                <div class="kyc-card-header">
                    <div class="kyc-card-icon">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                    </div>
                    <h2 class="kyc-card-title">Account Type</h2>
                </div>
                <div class="kyc-card-body">
                    <p style="font-size:0.83rem;color:var(--muted);margin-bottom:18px">Select the type that best describes your selling activity. This helps us tailor your onboarding, fees, and features. You may update this after approval.</p>
                    <input type="hidden" name="account_type" id="accountTypeInput" value="<?= htmlspecialchars($savedAccountType) ?>">

                    <!-- Group: Individual -->
                    <div class="acct-type-group">
                        <div class="acct-type-group-label">👤 Individual Sellers</div>
                        <div class="acct-type-grid">
                            <?php foreach ([
                                ['personal',  '🙋', 'Personal',   'Selling personal items, second-hand goods, or individual crafts'],
                                ['livelihood','🌾', 'Livelihood', 'Small-scale: farmers, fisherfolk, artisans, backyard producers'],
                            ] as [$val,$emoji,$name,$desc]): ?>
                            <label class="acct-type-card">
                                <input type="radio" name="_acct_radio" value="<?= $val ?>" <?= $savedAccountType === $val ? 'checked' : '' ?> onchange="document.getElementById('accountTypeInput').value=this.value">
                                <div class="acct-type-label"><span class="acct-type-emoji"><?= $emoji ?></span><span class="acct-type-name"><?= htmlspecialchars($name) ?></span><span class="acct-type-desc"><?= htmlspecialchars($desc) ?></span></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Group: Retail & Commerce -->
                    <div class="acct-type-group">
                        <div class="acct-type-group-label">🏪 Retail &amp; Commerce</div>
                        <div class="acct-type-grid">
                            <?php foreach ([
                                ['retailer',            '🏪', 'Retailer',            'Sell directly to consumers in individual units'],
                                ['wholesale',           '📦', 'Wholesale',           'Bulk sales with minimum order quantities'],
                                ['general_merchandise', '🛒', 'General Merchandise', 'Mixed categories — sari-sari, variety, tiangge'],
                                ['mall',                '🏬', 'Mall / Tiangge',      'Multi-category storefront, boutique, or kiosk'],
                            ] as [$val,$emoji,$name,$desc]): ?>
                            <label class="acct-type-card">
                                <input type="radio" name="_acct_radio" value="<?= $val ?>" <?= $savedAccountType === $val ? 'checked' : '' ?> onchange="document.getElementById('accountTypeInput').value=this.value">
                                <div class="acct-type-label"><span class="acct-type-emoji"><?= $emoji ?></span><span class="acct-type-name"><?= htmlspecialchars($name) ?></span><span class="acct-type-desc"><?= htmlspecialchars($desc) ?></span></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Group: Category-Specific -->
                    <div class="acct-type-group">
                        <div class="acct-type-group-label">📦 Category-Specific</div>
                        <div class="acct-type-grid">
                            <?php foreach ([
                                ['products',     '🛍️', 'Products',     'Physical goods — manufactured, imported, or locally-made'],
                                ['services',     '🔧', 'Services',     'Service-based: repairs, tutoring, freelance, consultancy'],
                                ['real_estate',  '🏠', 'Real Estate',  'Property listings for sale, lease, or pre-selling'],
                                ['rentals',      '🔑', 'Rentals',      'Equipment, vehicles, property, and event item rentals'],
                                        ['multi_purpose',        '🔀', 'Multi-Purpose / Multi-Type', 'Combines multiple categories — products, services, rentals, and more'],
                                ['digital_content',      '💻', 'Digital Content',    'E-books, courses, music, videos, templates, software downloads'],
                                ['intellectual_property','🧠', 'Intellectual Property','Licensing, patents, trademarks, royalties, franchises, creative rights'],
                                ['food_beverage',        '🍱', 'Food & Beverage',     'Food products, beverages, catering, baked goods, ready-to-eat items'],
                                ['fashion_apparel',      '👗', 'Fashion & Apparel',   'Clothing, footwear, bags, accessories, and wearable goods'],
                                ['health_wellness',      '💊', 'Health & Wellness',   'Supplements, beauty products, personal care, medical/health supplies'],
                                ['arts_crafts',          '🎨', 'Arts & Crafts',       'Handmade items, custom art, crafts, collectibles, and creative goods'],
                            ] as [$val,$emoji,$name,$desc]): ?>
                            <label class="acct-type-card">
                                <input type="radio" name="_acct_radio" value="<?= $val ?>" <?= $savedAccountType === $val ? 'checked' : '' ?> onchange="document.getElementById('accountTypeInput').value=this.value">
                                <div class="acct-type-label"><span class="acct-type-emoji"><?= $emoji ?></span><span class="acct-type-name"><?= htmlspecialchars($name) ?></span><span class="acct-type-desc"><?= htmlspecialchars($desc) ?></span></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Group: Organizational -->
                    <div class="acct-type-group">
                        <div class="acct-type-group-label">🤝 Organizational</div>
                        <div class="acct-type-grid">
                            <?php foreach ([
                                ['business',     '🏢', 'Business',     'DTI / SEC registered company or enterprise'],
                                ['cooperative',  '🤝', 'Cooperative',  'CDA-registered cooperative or multi-stakeholder group'],
                            ] as [$val,$emoji,$name,$desc]): ?>
                            <label class="acct-type-card">
                                <input type="radio" name="_acct_radio" value="<?= $val ?>" <?= $savedAccountType === $val ? 'checked' : '' ?> onchange="document.getElementById('accountTypeInput').value=this.value">
                                <div class="acct-type-label"><span class="acct-type-emoji"><?= $emoji ?></span><span class="acct-type-name"><?= htmlspecialchars($name) ?></span><span class="acct-type-desc"><?= htmlspecialchars($desc) ?></span></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Group: Ginto Programs -->
                    <div class="acct-type-group" style="margin-bottom:4px">
                        <div class="acct-type-group-label">⭐ Ginto Platform Programs</div>
                        <div class="acct-type-grid">
                            <?php foreach ([
                                ['ginto_sell_for_me',         '⭐', 'Sell for Me',        'Ginto platform sells on your behalf — you provide inventory & details'],
                                ['ginto_special_agreement',   '📋', 'Special Agreement',   'Custom terms individually negotiated with the Ginto team'],
                                ['ginto_partnership_program', '🤜', 'Partnership Program', 'Formal revenue-sharing partnership with Ginto ePower Mall'],
                            ] as [$val,$emoji,$name,$desc]): ?>
                            <label class="acct-type-card">
                                <input type="radio" name="_acct_radio" value="<?= $val ?>" <?= $savedAccountType === $val ? 'checked' : '' ?> onchange="document.getElementById('accountTypeInput').value=this.value">
                                <div class="acct-type-label"><span class="acct-type-emoji"><?= $emoji ?></span><span class="acct-type-name"><?= htmlspecialchars($name) ?></span><span class="acct-type-desc"><?= htmlspecialchars($desc) ?></span></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div id="kycAcctErr" style="color:var(--danger);font-size:0.77rem;margin-top:4px;display:none">⚠ Please choose an account type to continue.</div>
                    <div class="kyc-wiz-btns">
                        <button type="button" class="kwb-back" onclick="kycWizardGoTo(1)">← Back</button>
                        <span class="kwb-space"></span>
                        <button type="button" class="btn btn-primary kwb-next" onclick="kycWizardGoTo(3)">Continue →</button>
                    </div>
                </div>
            </div>
        </div><!-- /kycStep2 -->

        <!-- ============= STEP 3: PERSONAL INFO + ADDRESS ============= -->
        <div class="kyc-wstep" id="kycStep3">

        <!-- Personal info -->
        <div class="kyc-card">
            <div class="kyc-card-header">
                <div class="kyc-card-icon">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <h2 class="kyc-card-title">Personal Information</h2>
            </div>
            <div class="kyc-card-body">
                <!-- Row 1: First / Middle / Last -->
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="form-group">
                        <label class="form-label" for="kyc-first">First Name <span class="req" aria-hidden="true">*</span></label>
                        <input class="form-input" id="kyc-first" type="text" name="first_name" required
                            placeholder="e.g. Juan"
                            value="<?= htmlspecialchars($kyc['first_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="kyc-middle">Middle Name</label>
                        <input class="form-input" id="kyc-middle" type="text" name="middle_name"
                            placeholder="e.g. Santos (or N/A)"
                            value="<?= htmlspecialchars($kyc['middle_name'] ?? '') ?>">
                    </div>
                </div>
                <!-- Row 2: Last name / DOB -->
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="form-group">
                        <label class="form-label" for="kyc-last">Last Name / Surname <span class="req" aria-hidden="true">*</span></label>
                        <input class="form-input" id="kyc-last" type="text" name="last_name" required
                            placeholder="e.g. dela Cruz"
                            value="<?= htmlspecialchars($kyc['last_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="kyc-dob">Date of Birth <span class="req" aria-hidden="true">*</span></label>
                        <input class="form-input" id="kyc-dob" type="date" name="dob" required
                            value="<?= htmlspecialchars($kyc['dob'] ?? '') ?>">
                    </div>
                </div>
                <!-- Row 3: Place of birth / Nationality -->
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="form-group">
                        <label class="form-label" for="kyc-pob">Place of Birth <span class="req" aria-hidden="true">*</span></label>
                        <input class="form-input" id="kyc-pob" type="text" name="place_of_birth" required
                            placeholder="e.g. Quezon City, Metro Manila"
                            value="<?= htmlspecialchars($kyc['place_of_birth'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="kyc-nationality">Nationality <span class="req" aria-hidden="true">*</span></label>
                        <input class="form-input" id="kyc-nationality" type="text" name="nationality" required
                            placeholder="e.g. Filipino"
                            value="<?= htmlspecialchars($kyc['nationality'] ?? 'Filipino') ?>">
                    </div>
                </div>
                <!-- Row 4: Mobile / TIN -->
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="kyc-phone">Mobile Number <span class="req" aria-hidden="true">*</span></label>
                        <input class="form-input" id="kyc-phone" type="tel" name="phone" required
                            placeholder="e.g. 09171234567"
                            value="<?= htmlspecialchars($kyc['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="kyc-tin">TIN (Tax ID Number) <span class="req" aria-hidden="true">*</span></label>
                        <input class="form-input" id="kyc-tin" type="text" name="tin" required
                            placeholder="e.g. 123-456-789-000"
                            value="<?= htmlspecialchars($kyc['tin'] ?? '') ?>">
                        <div class="form-hint">Required by BIR for online sellers (RMC 60-2020).</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Address -->
        <div class="kyc-card">
            <div class="kyc-card-header">
                <div class="kyc-card-icon">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                </div>
                <h2 class="kyc-card-title">Home Address</h2>
            </div>
            <div class="kyc-card-body">
                <div class="form-group" style="margin-bottom:14px">
                    <label class="form-label" for="kyc-street">Street Address / Barangay <span class="req" aria-hidden="true">*</span></label>
                    <input class="form-input" id="kyc-street" type="text" name="address_street" required
                        placeholder="e.g. 123 Rizal St., Brgy. Poblacion"
                        value="<?= htmlspecialchars($kyc['address_street'] ?? '') ?>">
                </div>
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="form-group">
                        <label class="form-label" for="kyc-city">City / Municipality <span class="req" aria-hidden="true">*</span></label>
                        <input class="form-input" id="kyc-city" type="text" name="address_city" required
                            placeholder="e.g. Makati City"
                            value="<?= htmlspecialchars($kyc['address_city'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="kyc-province">Province / Region <span class="req" aria-hidden="true">*</span></label>
                        <input class="form-input" id="kyc-province" type="text" name="address_province" required
                            placeholder="e.g. Metro Manila"
                            value="<?= htmlspecialchars($kyc['address_province'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="kyc-zip">ZIP Code</label>
                        <input class="form-input" id="kyc-zip" type="text" name="address_zip"
                            placeholder="e.g. 1200"
                            value="<?= htmlspecialchars($kyc['address_zip'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="kyc-country">Country <span class="req" aria-hidden="true">*</span></label>
                        <select class="form-input" id="kyc-country" name="country" required>
                            <option value="">Select country…</option>
                            <?php foreach ($countries as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"
                                <?= ($kyc['country'] ?? 'Philippines') === $c ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c) ?>
                            </option>
                            <?php endforeach; ?>
                            <?php if (!empty($kyc['country']) && !in_array($kyc['country'], $countries)): ?>
                            <option value="<?= htmlspecialchars($kyc['country']) ?>" selected><?= htmlspecialchars($kyc['country']) ?></option>
                            <?php else: ?>
                            <option value="Other">Other</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 3 nav buttons -->
        <div class="kyc-wiz-btns">
            <button type="button" class="kwb-back" onclick="kycWizardGoTo(2)">← Back</button>
            <span class="kwb-space"></span>
            <button type="button" class="btn btn-primary kwb-next" onclick="kycWizardGoTo(4)">Continue →</button>
        </div>
        </div><!-- /kycStep3 -->

        <!-- ============= STEP 4: IDENTITY DOCUMENT ============= -->
        <div class="kyc-wstep" id="kycStep4">

        <!-- ID verification -->
        <div class="kyc-card">
            <div class="kyc-card-header">
                <div class="kyc-card-icon">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><polyline points="2 10 22 10"/></svg>
                </div>
                <h2 class="kyc-card-title">Primary Identity Document</h2>
            </div>
            <div class="kyc-card-body">
                <div style="background:rgba(59,130,246,0.06);border:1px solid rgba(59,130,246,0.2);border-radius:var(--radius-sm);padding:12px 14px;margin-bottom:16px;font-size:0.82rem;color:var(--muted);line-height:1.6">
                    <strong style="color:var(--text)">📋 Note:</strong> We accept a wide range of documents to ensure all Filipinos — including students, senior citizens, indigenous peoples, and those without government IDs — can participate. Select the document that best represents your identity.
                </div>
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="form-group">
                        <label class="form-label" for="kyc-id-type">Document / ID Type <span class="req" aria-hidden="true">*</span></label>
                        <select class="form-input" id="kyc-id-type" name="id_type" required>
                            <option value="">Select document type…</option>
                            <optgroup label="🏛️ Primary Government-Issued IDs">
                                <option value="Philippine National ID (PhilSys)" <?= ($kyc['id_type'] ?? '') === 'Philippine National ID (PhilSys)' ? 'selected' : '' ?>>Philippine National ID (PhilSys)</option>
                                <option value="Philippine Passport" <?= ($kyc['id_type'] ?? '') === 'Philippine Passport' ? 'selected' : '' ?>>Philippine Passport</option>
                                <option value="Driver's License (LTO)" <?= ($kyc['id_type'] ?? '') === "Driver's License (LTO)" ? 'selected' : '' ?>>Driver's License (LTO)</option>
                                <option value="Unified Multi-Purpose ID (UMID)" <?= ($kyc['id_type'] ?? '') === 'Unified Multi-Purpose ID (UMID)' ? 'selected' : '' ?>>Unified Multi-Purpose ID (UMID)</option>
                                <option value="SSS ID" <?= ($kyc['id_type'] ?? '') === 'SSS ID' ? 'selected' : '' ?>>SSS ID</option>
                                <option value="GSIS ID" <?= ($kyc['id_type'] ?? '') === 'GSIS ID' ? 'selected' : '' ?>>GSIS ID</option>
                                <option value="PRC Professional ID" <?= ($kyc['id_type'] ?? '') === 'PRC Professional ID' ? 'selected' : '' ?>>PRC Professional ID</option>
                                <option value="Voter's ID (COMELEC)" <?= ($kyc['id_type'] ?? '') === "Voter's ID (COMELEC)" ? 'selected' : '' ?>>Voter's ID (COMELEC)</option>
                                <option value="Senior Citizen ID (OSCA)" <?= ($kyc['id_type'] ?? '') === 'Senior Citizen ID (OSCA)' ? 'selected' : '' ?>>Senior Citizen ID (OSCA)</option>
                                <option value="PhilHealth ID" <?= ($kyc['id_type'] ?? '') === 'PhilHealth ID' ? 'selected' : '' ?>>PhilHealth ID</option>
                                <option value="Pag-IBIG (HDMF) ID" <?= ($kyc['id_type'] ?? '') === 'Pag-IBIG (HDMF) ID' ? 'selected' : '' ?>>Pag-IBIG (HDMF) ID</option>
                                <option value="Postal ID (PHLPost)" <?= ($kyc['id_type'] ?? '') === 'Postal ID (PHLPost)' ? 'selected' : '' ?>>Postal ID (PHLPost)</option>
                                <option value="NBI Clearance" <?= ($kyc['id_type'] ?? '') === 'NBI Clearance' ? 'selected' : '' ?>>NBI Clearance</option>
                                <option value="TIN Card (BIR)" <?= ($kyc['id_type'] ?? '') === 'TIN Card (BIR)' ? 'selected' : '' ?>>TIN Card (BIR)</option>
                                <option value="OFW ID / iDOLE" <?= ($kyc['id_type'] ?? '') === 'OFW ID / iDOLE' ? 'selected' : '' ?>>OFW ID / iDOLE</option>
                                <option value="PWD ID (NCDA)" <?= ($kyc['id_type'] ?? '') === 'PWD ID (NCDA)' ? 'selected' : '' ?>>PWD ID (NCDA – Persons with Disability)</option>
                            </optgroup>
                            <optgroup label="🎓 Student &amp; OJT Documents">
                                <option value="Student ID (School-Issued)" <?= ($kyc['id_type'] ?? '') === 'Student ID (School-Issued)' ? 'selected' : '' ?>>Student ID (School-Issued)</option>
                                <option value="OJT / Internship Endorsement Letter" <?= ($kyc['id_type'] ?? '') === 'OJT / Internship Endorsement Letter' ? 'selected' : '' ?>>OJT / Internship Endorsement Letter</option>
                                <option value="School Enrollment Certificate" <?= ($kyc['id_type'] ?? '') === 'School Enrollment Certificate' ? 'selected' : '' ?>>School Enrollment Certificate</option>
                            </optgroup>
                            <optgroup label="📜 Civil &amp; Community Documents">
                                <option value="Birth Certificate (PSA)" <?= ($kyc['id_type'] ?? '') === 'Birth Certificate (PSA)' ? 'selected' : '' ?>>Birth Certificate (PSA)</option>
                                <option value="Barangay Clearance" <?= ($kyc['id_type'] ?? '') === 'Barangay Clearance' ? 'selected' : '' ?>>Barangay Clearance</option>
                                <option value="Barangay Certificate of Residency" <?= ($kyc['id_type'] ?? '') === 'Barangay Certificate of Residency' ? 'selected' : '' ?>>Barangay Certificate of Residency</option>
                                <option value="Barangay Indigency Certificate" <?= ($kyc['id_type'] ?? '') === 'Barangay Indigency Certificate' ? 'selected' : '' ?>>Barangay Indigency Certificate</option>
                                <option value="Church Clearance Certificate" <?= ($kyc['id_type'] ?? '') === 'Church Clearance Certificate' ? 'selected' : '' ?>>Church Clearance Certificate</option>
                                <option value="Church Baptismal Certificate" <?= ($kyc['id_type'] ?? '') === 'Church Baptismal Certificate' ? 'selected' : '' ?>>Church Baptismal Certificate</option>
                                <option value="Marriage Certificate (PSA)" <?= ($kyc['id_type'] ?? '') === 'Marriage Certificate (PSA)' ? 'selected' : '' ?>>Marriage Certificate (PSA)</option>
                            </optgroup>
                            <optgroup label="🏘️ Indigenous Peoples &amp; Indigent Filipinos">
                                <option value="NCIP Certificate of Membership (IP)" <?= ($kyc['id_type'] ?? '') === 'NCIP Certificate of Membership (IP)' ? 'selected' : '' ?>>NCIP Certificate of Membership (IP)</option>
                                <option value="Tribal Leader Endorsement Letter" <?= ($kyc['id_type'] ?? '') === 'Tribal Leader Endorsement Letter' ? 'selected' : '' ?>>Tribal Leader Endorsement Letter</option>
                                <option value="IP Community ID / Tribal ID" <?= ($kyc['id_type'] ?? '') === 'IP Community ID / Tribal ID' ? 'selected' : '' ?>>IP Community ID / Tribal ID</option>
                                <option value="DSWD Beneficiary Certificate (4Ps / Pantawid)" <?= ($kyc['id_type'] ?? '') === 'DSWD Beneficiary Certificate (4Ps / Pantawid)' ? 'selected' : '' ?>>DSWD Beneficiary Certificate (4Ps / Pantawid)</option>
                                <option value="Certificate of Indigency (Municipal / City Hall)" <?= ($kyc['id_type'] ?? '') === 'Certificate of Indigency (Municipal / City Hall)' ? 'selected' : '' ?>>Certificate of Indigency (Municipal / City Hall)</option>
                                <option value="Solo Parent ID (DSWD)" <?= ($kyc['id_type'] ?? '') === 'Solo Parent ID (DSWD)' ? 'selected' : '' ?>>Solo Parent ID (DSWD)</option>
                            </optgroup>
                            <optgroup label="🤝 Organization &amp; Entity Identity">
                                <option value="Entity Endorsement Letter" <?= ($kyc['id_type'] ?? '') === 'Entity Endorsement Letter' ? 'selected' : '' ?>>Entity Endorsement Letter (NGO / Cooperative / Association)</option>
                            </optgroup>
                            <optgroup label="🖼️ Proof of Identity (Alternate)">
                                <option value="COMELEC Voter Registration Form" <?= ($kyc['id_type'] ?? '') === 'COMELEC Voter Registration Form' ? 'selected' : '' ?>>COMELEC Voter Registration Form</option>
                                <option value="Government Employee ID" <?= ($kyc['id_type'] ?? '') === 'Government Employee ID' ? 'selected' : '' ?>>Government Employee ID</option>
                                <option value="Company/Employer ID with Address" <?= ($kyc['id_type'] ?? '') === 'Company/Employer ID with Address' ? 'selected' : '' ?>>Company/Employer ID with Official Address</option>
                                <option value="Bank or E-Wallet Statement (GCash/Maya)" <?= ($kyc['id_type'] ?? '') === 'Bank or E-Wallet Statement (GCash/Maya)' ? 'selected' : '' ?>>Bank or E-Wallet Statement (GCash / Maya)</option>
                            </optgroup>
                        </select>
                        <div class="form-hint">Cannot find your document? Select the closest match and explain in the document notes below.</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="kyc-id">ID / Reference Number <span class="req" aria-hidden="true">*</span></label>
                        <input class="form-input" id="kyc-id" type="text" name="identifier" required
                            placeholder="As printed on your document"
                            value="<?= htmlspecialchars($kyc['identifier'] ?? '') ?>">
                        <div class="form-hint">If your document has no number (e.g. Barangay Clearance), write the date of issuance.</div>
                    </div>
                </div>
                <div class="form-hint">All identity information is encrypted and kept strictly confidential — used only for identity verification as required by RA 9160 (AMLA), RA 10173 (Data Privacy Act), and RA 3883 (Business Name Law).</div>
            </div>
        </div>

        <!-- Step 4 nav buttons -->
        <div class="kyc-wiz-btns">
            <button type="button" class="kwb-back" onclick="kycWizardGoTo(3)">← Back</button>
            <span class="kwb-space"></span>
            <button type="button" class="btn btn-primary kwb-next" onclick="kycWizardGoTo(5)">Continue →</button>
        </div>
        </div><!-- /kycStep4 -->

        <!-- ============= STEP 5: BUSINESS REG + DOCUMENTS + SUBMIT ============= -->
        <div class="kyc-wstep" id="kycStep5">

        <!-- Business Registration (optional) -->
        <div class="kyc-card">
            <div class="kyc-card-header">
                <div class="kyc-card-icon">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>
                </div>
                <h2 class="kyc-card-title">Business Registration <span style="font-weight:400;font-size:0.8rem;color:var(--muted)">(optional)</span></h2>
            </div>
            <div class="kyc-card-body">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="kyc-biz-name">Registered Business Name</label>
                        <input class="form-input" id="kyc-biz-name" type="text" name="business_name"
                            placeholder="e.g. Juan's Online Shop"
                            value="<?= htmlspecialchars($kyc['business_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="kyc-biz-reg">DTI / SEC / CDA Registration No.</label>
                        <input class="form-input" id="kyc-biz-reg" type="text" name="business_reg"
                            placeholder="e.g. DTI-2024-0012345"
                            value="<?= htmlspecialchars($kyc['business_reg'] ?? '') ?>">
                        <div class="form-hint">Required for registered sellers under RA 3883 (Business Name Law).</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== IDENTITY DOCUMENTS ===== -->
        <div class="kyc-card" style="margin-bottom:16px">
            <div class="kyc-card-header">
                <div class="kyc-card-icon">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><polyline points="2 10 22 10"/></svg>
                </div>
                <h2 class="kyc-card-title">Identity Documents <span style="color:var(--danger);font-size:0.78rem;font-weight:500">— Required</span></h2>
            </div>
            <div class="kyc-card-body">
                <p style="font-size:0.83rem;color:var(--muted);margin-bottom:13px">Upload clear photos or scans of your ID or identity certificate. Front and back are required where applicable. <strong>This section is mandatory for all applicants.</strong></p>
                <?php $savedDocTypes = (!empty($kyc['doc_types'])) ? (json_decode($kyc['doc_types'], true) ?: []) : []; ?>
                <div style="margin-bottom:14px">
                    <div class="form-label" style="margin-bottom:8px">Identity documents you are uploading <span class="req" aria-hidden="true">*</span></div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:8px">
                        <?php foreach ([
                            'id_front'          => '🪪 ID / Document — Front',
                            'id_back'           => '🪪 ID / Document — Back',
                            'selfie_with_id'    => '🤳 Selfie Holding Your ID / Document',
                            'birth_certificate' => '📄 Birth Certificate (PSA)',
                            'barangay_clearance'=> '📋 Barangay Clearance / Certificate',
                            'church_clearance'  => '⛪ Church Clearance Certificate',
                            'ncip_certificate'  => '🏘️ NCIP Certificate of Membership',
                            'entity_endorsement'=> '🤝 Entity / Organization Endorsement Letter',
                            'other_id'          => '📎 Other Identity Document',
                        ] as $val => $label): ?>
                        <label class="doc-check-label">
                            <input type="checkbox" name="doc_types[]" value="<?= htmlspecialchars($val) ?>"
                                <?= in_array($val, $savedDocTypes) ? 'checked' : '' ?>
                                style="accent-color:var(--accent)" onchange="kycClearErr('kycIdErr')">
                            <span><?= htmlspecialchars($label) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="doc-upload-area" id="idUploadArea" role="button" tabindex="0" aria-label="Upload identity documents">
                    <input type="file" name="id_files[]" id="idFilesInput" multiple accept="image/*,.pdf" tabindex="-1">
                    <div class="upload-icon">🪪</div>
                    <div class="upload-title" id="idUploadTitle">Click or drag &amp; drop ID files here</div>
                    <div class="upload-sub">
                        JPG, PNG, PDF accepted · Multiple files · Max 10 MB each<br>
                        <span style="color:var(--accent)">Required under RA 9160 (AMLA) &amp; RA 10173 (Data Privacy Act)</span>
                    </div>
                </div>
                <div id="idFileCount" style="display:none;margin-top:8px;padding:8px 12px;background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.3);border-radius:var(--radius-sm);font-size:0.8rem;color:#22c55e;font-weight:600"></div>
                <div class="doc-previews" id="idPreviews"></div>
                <div id="kycIdErr" style="display:none;margin-top:10px;padding:10px 14px;background:rgba(239,68,68,0.07);border:1px solid rgba(239,68,68,0.3);border-radius:var(--radius-sm);font-size:0.82rem;color:var(--danger)"></div>
            </div>
        </div>

        <!-- ===== SUPPORTING / BUSINESS DOCUMENTS ===== -->
        <div class="kyc-card" style="margin-bottom:24px">
            <div class="kyc-card-header">
                <div class="kyc-card-icon">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <h2 class="kyc-card-title">Supporting / Business Documents <span id="supportReqBadge" style="display:none;color:var(--danger);font-size:0.78rem;font-weight:500">— Required for your account type</span></h2>
            </div>
            <div class="kyc-card-body">
                <p style="font-size:0.83rem;color:var(--muted);margin-bottom:6px">Upload business registrations, permits, or proof of address. <strong>Mandatory for all non-personal account types.</strong></p>
                <div id="supportMandatoryNote" style="display:none;margin-bottom:13px;padding:9px 12px;background:rgba(245,158,11,0.07);border:1px solid rgba(245,158,11,0.3);border-radius:var(--radius-sm);font-size:0.79rem;color:#b45309">
                    ⚠ Your selected account type requires at least one business or legal document.
                </div>
                <div style="margin-bottom:14px">
                    <div class="form-label" style="margin-bottom:8px">Supporting documents you are uploading <span id="supportCheckReq" class="req" style="display:none" aria-hidden="true">*</span></div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:8px">
                        <?php foreach ([
                            'proof_of_address'  => '🏠 Proof of Address (Utility Bill / Barangay Cert.)',
                            'dti_certificate'   => '🏢 DTI Business Name Certificate',
                            'sec_certificate'   => '🏛️ SEC Certificate of Registration',
                            'business_permit'   => "📋 Business Permit / Mayor's Permit",
                            'bir_cor'           => '🧾 BIR Certificate of Registration (Form 2303)',
                            'cda_certificate'   => '🤝 CDA Cooperative Registration',
                            'other_support'     => '📎 Other Supporting Document',
                        ] as $val => $label): ?>
                        <label class="doc-check-label">
                            <input type="checkbox" name="doc_types[]" value="<?= htmlspecialchars($val) ?>"
                                <?= in_array($val, $savedDocTypes) ? 'checked' : '' ?>
                                style="accent-color:var(--accent)" onchange="kycClearErr('kycSupportErr')">
                            <span><?= htmlspecialchars($label) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="doc-upload-area" id="supportUploadArea" role="button" tabindex="0" aria-label="Upload supporting documents">
                    <input type="file" name="support_files[]" id="supportFilesInput" multiple accept="image/*,.pdf" tabindex="-1">
                    <div class="upload-icon">📂</div>
                    <div class="upload-title" id="supportUploadTitle">Click or drag &amp; drop business / support files here</div>
                    <div class="upload-sub">
                        JPG, PNG, PDF accepted · Multiple files · Max 10 MB each<br>
                        <span style="color:var(--accent)">DTI / SEC / CDA / BIR / Business Permit accepted</span>
                    </div>
                </div>
                <div id="supportFileCount" style="display:none;margin-top:8px;padding:8px 12px;background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.3);border-radius:var(--radius-sm);font-size:0.8rem;color:#22c55e;font-weight:600"></div>
                <div class="doc-previews" id="supportPreviews"></div>
                <div id="kycSupportErr" style="display:none;margin-top:10px;padding:10px 14px;background:rgba(239,68,68,0.07);border:1px solid rgba(239,68,68,0.3);border-radius:var(--radius-sm);font-size:0.82rem;color:var(--danger)"></div>
                <input type="hidden" id="kycHasExistingDocs" value="<?= !empty($docs) ? '1' : '0' ?>">
            </div>
        </div>

        <div class="kyc-wiz-btns">
            <button type="button" class="kwb-back" onclick="kycWizardGoTo(4)">← Back</button>
            <span class="kwb-space"></span>
            <button type="button" class="btn btn-primary kwb-next" style="padding:10px 26px" onclick="kycValidateAndSubmit(this)">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <?= $submitted ? 'Re-submit KYC' : 'Submit for Review' ?>
            </button>
        </div>

        </div><!-- /kycStep5 -->
    </form>

    <?php if ($showCollapsed): ?>
    </details>
    <?php endif; ?>

</div>

<script>
/* ===== KYC WIZARD NAVIGATION ===== */
(function () {
    var currentStep = 1;
    var TOTAL = 5;

    // Auto-advance to last completed step for re-submitters
    var savedAccountType = <?= json_encode($savedAccountType) ?>;
    if (savedAccountType) {
        currentStep = 3; // at least past TOS + account type
    }

    function updateNav() {
        for (var i = 1; i <= TOTAL; i++) {
            var step  = document.getElementById('kycStep' + i);
            var nav   = document.getElementById('kwn' + i);
            if (!step || !nav) continue;
            step.classList.toggle('wactive', i === currentStep);
            nav.classList.toggle('wactive', i === currentStep);
            nav.classList.toggle('wdone',   i < currentStep);
            if (i < currentStep) {
                nav.querySelector('.kwn-step-num').innerHTML = '✓';
            } else if (!nav.querySelector('.kwn-step-num').dataset.orig) {
                nav.querySelector('.kwn-step-num').dataset.orig = String(i);
                if (i > currentStep) nav.querySelector('.kwn-step-num').textContent = String(i);
            } else if (i > currentStep) {
                nav.querySelector('.kwn-step-num').textContent = nav.querySelector('.kwn-step-num').dataset.orig || String(i);
            }
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    window.kycWizardGoTo = function (target) {
        // Validate before advancing
        if (target > currentStep) {
            if (currentStep === 1) {
                var tos = document.getElementById('kycTosCheck');
                var err = document.getElementById('kycTosErr');
                if (!tos.checked) { err.style.display = 'block'; tos.focus(); return; }
                err.style.display = 'none';
            }
            if (currentStep === 2) {
                var acctInput = document.getElementById('accountTypeInput');
                var acctErr   = document.getElementById('kycAcctErr');
                if (!acctInput.value) { acctErr.style.display = 'block'; return; }
                acctErr.style.display = 'none';
            }
            if (currentStep === 3) {
                var req3 = ['kyc-first','kyc-last','kyc-dob','kyc-pob','kyc-nationality','kyc-phone','kyc-tin','kyc-street','kyc-city','kyc-province'];
                var bad = req3.filter(function(id){ var el=document.getElementById(id); return el && !el.value.trim(); });
                if (bad.length) {
                    var el = document.getElementById(bad[0]);
                    if (el) { el.focus(); el.style.outline = '2px solid var(--danger)'; setTimeout(function(){ el.style.outline=''; },2000); }
                    return;
                }
                // Age check — must be 18+
                var dobEl = document.getElementById('kyc-dob');
                if (dobEl && dobEl.value) {
                    var dob = new Date(dobEl.value);
                    var today = new Date();
                    var age = today.getFullYear() - dob.getFullYear();
                    var m = today.getMonth() - dob.getMonth();
                    if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
                    if (age < 18) {
                        dobEl.focus();
                        dobEl.style.outline = '2px solid var(--danger)';
                        setTimeout(function(){ dobEl.style.outline=''; }, 3000);
                        var ageErrId = 'kycAgeErr';
                        var ageErr = document.getElementById(ageErrId);
                        if (!ageErr) {
                            ageErr = document.createElement('div');
                            ageErr.id = ageErrId;
                            ageErr.style.cssText = 'color:var(--danger);font-size:0.78rem;margin-top:6px;padding:8px 12px;background:rgba(239,68,68,0.07);border:1px solid rgba(239,68,68,0.3);border-radius:6px';
                            dobEl.parentNode.appendChild(ageErr);
                        }
                        ageErr.textContent = '⚠ You must be 18 years of age or older to register as a seller.';
                        return;
                    }
                }
            }
            if (currentStep === 4) {
                var idTypeEl  = document.getElementById('kyc-id-type');
                var identEl   = document.getElementById('kyc-id');
                if (idTypeEl && !idTypeEl.value) { idTypeEl.focus(); return; }
                if (identEl && !identEl.value.trim()) { identEl.focus(); return; }
            }
        }
        currentStep = target;
        updateNav();
    };

    updateNav();
})();

/* ===== HELPERS ===== */
window.kycClearErr = function (id) {
    var el = document.getElementById(id);
    if (el) el.style.display = 'none';
};

var SUPPORT_REQUIRED_TYPES = [
    'retailer','wholesale','general_merchandise','mall',
    'products','services','real_estate','rentals','multi_purpose',
    'digital_content','intellectual_property','food_beverage',
    'fashion_apparel','health_wellness','arts_crafts',
    'business','cooperative',
    'ginto_sell_for_me','ginto_special_agreement','ginto_partnership_program'
];

function kycIsSupportRequired() {
    var acct = document.getElementById('accountTypeInput');
    return acct && SUPPORT_REQUIRED_TYPES.indexOf(acct.value) !== -1;
}

function kycUpdateSupportUI() {
    var req     = kycIsSupportRequired();
    var badge   = document.getElementById('supportReqBadge');
    var note    = document.getElementById('supportMandatoryNote');
    var reqStar = document.getElementById('supportCheckReq');
    if (badge)   badge.style.display   = req ? 'inline' : 'none';
    if (note)    note.style.display    = req ? 'block'  : 'none';
    if (reqStar) reqStar.style.display = req ? 'inline' : 'none';
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[name="_acct_radio"]').forEach(function (r) {
        r.addEventListener('change', kycUpdateSupportUI);
    });
    kycUpdateSupportUI();
});

/* ===== SUBMIT VALIDATION ===== */
window.kycValidateAndSubmit = function (btn) {
    var hasExist        = document.getElementById('kycHasExistingDocs');
    var hasExistingDocs = hasExist && hasExist.value === '1';

    var ID_KEYS  = ['id_front','id_back','selfie_with_id','birth_certificate','barangay_clearance',
                    'church_clearance','ncip_certificate','entity_endorsement','other_id'];
    var SUP_KEYS = ['proof_of_address','dti_certificate','sec_certificate',
                    'business_permit','bir_cor','cda_certificate','other_support'];

    var allChecked = Array.from(document.querySelectorAll('input[name="doc_types[]"]:checked'));
    var checkedId  = allChecked.filter(function (c) { return ID_KEYS.indexOf(c.value)  !== -1; });
    var checkedSup = allChecked.filter(function (c) { return SUP_KEYS.indexOf(c.value) !== -1; });

    var idInput  = document.getElementById('idFilesInput');
    var supInput = document.getElementById('supportFilesInput');
    var idCount  = idInput  ? idInput.files.length  : 0;
    var supCount = supInput ? supInput.files.length : 0;

    var valid = true;

    // Identity — always required
    var idErr = document.getElementById('kycIdErr');
    var idMsgs = [];
    if (checkedId.length === 0)
        idMsgs.push('⚠ Tick at least one identity document type from the checklist.');
    if (idCount === 0 && !hasExistingDocs)
        idMsgs.push('⚠ No identity files attached — click the 🪪 upload area above to select your ID photos or scans.');
    if (idMsgs.length) {
        idErr.style.display = 'block';
        idErr.innerHTML = idMsgs.join('<br>');
        idErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
        valid = false;
    } else {
        idErr.style.display = 'none';
    }

    // Supporting — required only for non-personal accounts
    var supErr = document.getElementById('kycSupportErr');
    if (kycIsSupportRequired()) {
        var supMsgs = [];
        if (checkedSup.length === 0)
            supMsgs.push('⚠ Your account type requires at least one supporting document type to be ticked.');
        if (supCount === 0 && !hasExistingDocs)
            supMsgs.push('⚠ No supporting files attached — click the 📂 upload area to attach your business or legal documents.');
        if (supMsgs.length) {
            supErr.style.display = 'block';
            supErr.innerHTML = supMsgs.join('<br>');
            if (valid) supErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
            valid = false;
        } else {
            supErr.style.display = 'none';
        }
    } else {
        supErr.style.display = 'none';
    }

    if (!valid) return;
    btn.disabled = true;
    btn.innerHTML = '⏳ Uploading &amp; Submitting…';
    btn.closest('form').submit();
};

/* ===== UPLOAD AREAS ===== */
function makeUploadArea(areaId, inputId, previewId, countId, titleId, errId) {
    var area     = document.getElementById(areaId);
    var input    = document.getElementById(inputId);
    var previews = document.getElementById(previewId);
    var countEl  = document.getElementById(countId);
    var titleEl  = document.getElementById(titleId);
    if (!area || !input) return;

    function updateCount() {
        var n = input.files ? input.files.length : 0;
        if (n > 0) {
            if (countEl) { countEl.style.display = 'block'; countEl.textContent = '✅ ' + n + ' file' + (n > 1 ? 's' : '') + ' selected and ready to upload.'; }
            if (titleEl) titleEl.textContent = n + ' file' + (n > 1 ? 's' : '') + ' selected';
            var err = document.getElementById(errId); if (err) err.style.display = 'none';
        } else {
            if (countEl) countEl.style.display = 'none';
        }
    }

    function showPreviews() {
        if (!previews || !input || !input.files.length) return;
        previews.innerHTML = '';
        Array.from(input.files).forEach(function (file) {
            var item = document.createElement('div'); item.className = 'doc-prev-item';
            var nameEl = document.createElement('div'); nameEl.className = 'doc-prev-name'; nameEl.textContent = file.name;
            if (file.type.startsWith('image/')) {
                var img = document.createElement('img'); img.alt = file.name;
                var reader = new FileReader(); reader.onload = function (e) { img.src = e.target.result; }; reader.readAsDataURL(file);
                item.appendChild(img);
            } else {
                item.innerHTML = '<div style="width:68px;height:68px;display:flex;align-items:center;justify-content:center;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:1.6rem">📄</div>';
            }
            item.appendChild(nameEl); previews.appendChild(item);
        });
    }

    input.addEventListener('change', function () { updateCount(); showPreviews(); });
    area.addEventListener('click',   function () { input.click(); });
    area.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
    area.addEventListener('dragover',  function (e) { e.preventDefault(); area.classList.add('drag-over'); });
    area.addEventListener('dragleave', function ()  { area.classList.remove('drag-over'); });
    area.addEventListener('drop', function (e) {
        e.preventDefault(); area.classList.remove('drag-over');
        if (e.dataTransfer.files.length) { try { input.files = e.dataTransfer.files; } catch (_) {} updateCount(); showPreviews(); }
    });
}

makeUploadArea('idUploadArea',      'idFilesInput',      'idPreviews',      'idFileCount',      'idUploadTitle',      'kycIdErr');
makeUploadArea('supportUploadArea', 'supportFilesInput', 'supportPreviews', 'supportFileCount', 'supportUploadTitle', 'kycSupportErr');
</script>

<footer style="text-align:center;padding:32px 16px 40px;border-top:1px solid var(--border);margin-top:48px">
    <div style="max-width:700px;margin:0 auto">
        <div style="font-size:0.72rem;color:var(--muted);line-height:1.9">
            <strong style="color:var(--text);font-size:0.8rem">Ginto Mall</strong>,
            <strong style="color:var(--text);font-size:0.8rem">Ginto ePower Mall</strong>, and
            <strong style="color:var(--text);font-size:0.8rem">Ginto Marketplace</strong>
            are trademarks of <strong style="color:var(--text)">BusinessWeek Mindanao</strong>,
            <strong style="color:var(--text)">AI HQ Corp</strong>, and
            <strong style="color:var(--text)">Conglomerates</strong>.
            All rights reserved.<br>
            &copy; <?= date('Y') ?> Ginto ePower Mall. Unauthorized use, reproduction, or distribution of these marks is prohibited.<br>
            <span style="font-size:0.68rem;opacity:0.7">Powered by Ginto &middot; Secured by RA 10173 &middot; Compliant with RA 9160</span>
        </div>
    </div>
</footer>

<?php include __DIR__ . '/parts/footer.php'; ?>
</body>
</html>
