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
                                <option value="Church/Parish Clearance Certificate" <?= ($kyc['id_type'] ?? '') === 'Church/Parish Clearance Certificate' ? 'selected' : '' ?>>Church / Parish Clearance Certificate</option>
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
                            <optgroup label="🏢 Business &amp; Entity Documents">
                                <option value="Entity Endorsement Letter" <?= ($kyc['id_type'] ?? '') === 'Entity Endorsement Letter' ? 'selected' : '' ?>>Entity Endorsement Letter (NGO / Cooperative / Association)</option>
                                <option value="DTI Business Name Registration" <?= ($kyc['id_type'] ?? '') === 'DTI Business Name Registration' ? 'selected' : '' ?>>DTI Business Name Registration Certificate</option>
                                <option value="SEC Certificate of Registration" <?= ($kyc['id_type'] ?? '') === 'SEC Certificate of Registration' ? 'selected' : '' ?>>SEC Certificate of Registration</option>
                                <option value="Business Permit / Mayor's Permit" <?= ($kyc['id_type'] ?? '') === "Business Permit / Mayor's Permit" ? 'selected' : '' ?>>Business Permit / Mayor's Permit</option>
                                <option value="CDA Cooperative Registration" <?= ($kyc['id_type'] ?? '') === 'CDA Cooperative Registration' ? 'selected' : '' ?>>CDA Cooperative Registration Certificate</option>
                                <option value="BIR Certificate of Registration (Form 2303)" <?= ($kyc['id_type'] ?? '') === 'BIR Certificate of Registration (Form 2303)' ? 'selected' : '' ?>>BIR Certificate of Registration (Form 2303)</option>
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

        <!-- Documents upload -->
        <div class="kyc-card" style="margin-bottom:24px">
            <div class="kyc-card-header">
                <div class="kyc-card-icon">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                </div>
                <h2 class="kyc-card-title">Upload Supporting Documents</h2>
            </div>
            <div class="kyc-card-body">
                <!-- Document category checklist -->
                <div style="margin-bottom:18px">
                    <div class="form-label" style="margin-bottom:8px">Which documents are you uploading? <span class="req">*</span></div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:8px">
                        <?php
                        $docCategories = [
                            'id_front'          => 'ID / Document — Front',
                            'id_back'           => 'ID / Document — Back',
                            'selfie_with_id'    => 'Selfie Holding Your ID / Document',
                            'proof_of_address'  => 'Proof of Address (Utility Bill / Barangay Cert.)',
                            'birth_certificate' => 'Birth Certificate (PSA)',
                            'barangay_clearance'=> 'Barangay Clearance',
                            'dti_certificate'   => 'DTI Business Name Certificate',
                            'sec_certificate'   => 'SEC Certificate of Registration',
                            'business_permit'   => 'Business Permit / Mayor's Permit',
                            'bir_cor'           => 'BIR Certificate of Registration (Form 2303)',
                            'cda_certificate'   => 'CDA Cooperative Registration',
                            'ncip_certificate'  => 'NCIP Certificate of Membership',
                            'church_clearance'  => 'Church / Parish Clearance Certificate',
                            'entity_endorsement'=> 'Entity / Organization Endorsement Letter',
                            'other'             => 'Other Supporting Document',
                        ];
                        $savedDocTypes = (!empty($kyc['doc_types'])) ? (json_decode($kyc['doc_types'], true) ?: []) : [];
                        foreach ($docCategories as $val => $label):
                        ?>
                        <label style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);cursor:pointer;font-size:0.82rem;transition:background var(--trans)"
                               onmouseover="this.style.background='var(--border)'" onmouseout="this.style.background='var(--surface2)'">
                            <input type="checkbox" name="doc_types[]" value="<?= htmlspecialchars($val) ?>"
                                <?= in_array($val, $savedDocTypes) ? 'checked' : '' ?>
                                style="accent-color:var(--accent)">
                            <?= htmlspecialchars($label) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-hint" style="margin-top:6px">Check all document types included in your upload below.</div>
                </div>

                <div class="doc-upload-area" id="docUploadArea" role="button" tabindex="0" aria-label="Upload documents">
                    <input type="file" name="documents[]" id="docFilesInput" multiple accept="image/*,.pdf" tabindex="-1">
                    <div class="upload-icon">📎</div>
                    <div class="upload-title">Click or drag &amp; drop files here</div>
                    <div class="upload-sub">
                        Required: ID / Document (front &amp; back) · Selfie holding your ID · Proof of address<br>
                        Business sellers: include DTI / SEC / Business Permit &amp; BIR COR<br>
                        Accepted: JPG, PNG, PDF · Max 10 MB each<br>
                        <span style="color:var(--accent)">Mandated under RA 9160 (AMLA) &amp; RA 10173 (Data Privacy Act)</span>
                    </div>
                </div>
                <div class="doc-previews" id="docPreviews"></div>
            </div>
        </div>

        <div style="display:flex;gap:10px;align-items:center">
            <button type="submit" class="btn btn-primary" style="padding:11px 26px;font-size:0.92rem">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <?= $submitted ? 'Re-submit KYC' : 'Submit for Review' ?>
            </button>
            <a href="/marketplace/sellers/products" class="btn btn-secondary">Cancel</a>
        </div>
    </form>

    <?php if ($showCollapsed): ?>
    </details>
    <?php endif; ?>

</div>

<script>
(function () {
    const area     = document.getElementById('docUploadArea');
    const input    = document.getElementById('docFilesInput');
    const previews = document.getElementById('docPreviews');

    function showPreviews() {
        if (!previews || !input || !input.files.length) return;
        previews.innerHTML = '';
        Array.from(input.files).forEach(function (file) {
            const item    = document.createElement('div');
            item.className = 'doc-prev-item';
            const nameEl  = document.createElement('div');
            nameEl.className = 'doc-prev-name';
            nameEl.textContent = file.name;
            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.alt   = file.name;
                const reader = new FileReader();
                reader.onload = function (e) { img.src = e.target.result; };
                reader.readAsDataURL(file);
                item.appendChild(img);
            } else {
                item.innerHTML = '<div style="width:68px;height:68px;display:flex;align-items:center;justify-content:center;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:1.6rem">📄</div>';
            }
            item.appendChild(nameEl);
            previews.appendChild(item);
        });
    }

    if (input) input.addEventListener('change', showPreviews);

    if (area) {
        area.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input && input.click(); }
        });
        area.addEventListener('dragover',  function (e) { e.preventDefault(); area.classList.add('drag-over'); });
        area.addEventListener('dragleave', function ()  { area.classList.remove('drag-over'); });
        area.addEventListener('drop', function (e) {
            e.preventDefault();
            area.classList.remove('drag-over');
            if (input && e.dataTransfer.files.length) {
                try { input.files = e.dataTransfer.files; } catch (_) {}
                showPreviews();
            }
        });
    }
}());
</script>

<?php include __DIR__ . '/parts/footer.php'; ?>
</body>
</html>
