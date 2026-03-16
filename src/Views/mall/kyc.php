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
</style>
<body>
<?php include __DIR__ . '/parts/header.php'; ?>
<div id="sidebarBackdrop" class="sidebar-backdrop" aria-hidden="true"></div>
<!-- Mobile seller nav drawer (hamburger target) -->
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Seller navigation" style="width:224px">
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
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="form-group">
                        <label class="form-label" for="kyc-first">First Name <span class="req" aria-hidden="true">*</span></label>
                        <input class="form-input" id="kyc-first" type="text" name="first_name" required
                            placeholder="John"
                            value="<?= htmlspecialchars($kyc['first_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="kyc-last">Last Name <span class="req" aria-hidden="true">*</span></label>
                        <input class="form-input" id="kyc-last" type="text" name="last_name" required
                            placeholder="Doe"
                            value="<?= htmlspecialchars($kyc['last_name'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="kyc-dob">Date of Birth</label>
                        <input class="form-input" id="kyc-dob" type="date" name="dob"
                            value="<?= htmlspecialchars($kyc['dob'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="kyc-country">Country</label>
                        <select class="form-input" id="kyc-country" name="country">
                            <option value="">Select country…</option>
                            <?php foreach ($countries as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"
                                <?= ($kyc['country'] ?? '') === $c ? 'selected' : '' ?>>
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
                <h2 class="kyc-card-title">Government ID</h2>
            </div>
            <div class="kyc-card-body">
                <div class="form-group">
                    <label class="form-label" for="kyc-id">ID Number</label>
                    <input class="form-input" id="kyc-id" type="text" name="identifier"
                        placeholder="Passport / National ID / Driver's License"
                        value="<?= htmlspecialchars($kyc['identifier'] ?? '') ?>">
                    <div class="form-hint">Kept confidential — used only for identity verification.</div>
                </div>
            </div>
        </div>

        <!-- Documents upload -->
        <div class="kyc-card" style="margin-bottom:24px">
            <div class="kyc-card-header">
                <div class="kyc-card-icon">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                </div>
                <h2 class="kyc-card-title">Upload Documents</h2>
            </div>
            <div class="kyc-card-body">
                <div class="doc-upload-area" id="docUploadArea" role="button" tabindex="0" aria-label="Upload documents">
                    <input type="file" name="documents[]" id="docFilesInput" multiple accept="image/*,.pdf" tabindex="-1">
                    <div class="upload-icon">📎</div>
                    <div class="upload-title">Click or drag &amp; drop files here</div>
                    <div class="upload-sub">Accepted: JPG, PNG, PDF — ID front &amp; back, selfie, proof of address (max 10 MB each)</div>
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
