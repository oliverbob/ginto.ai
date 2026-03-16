<?php
/** @var array $products Published products for this seller */
/** @var array $drafts   Draft products for this seller */
/** @var string $csrf_token */
/** @var string $kyc_status */
/** @var string $subscription_status */
/** @var bool $is_admin */
?>
<?php
$title      = $title ?? 'My Products — Seller Center';
$isLoggedIn = true;
$is_admin   = $is_admin ?? false;

$allProductsList = array_merge($products ?? [], $drafts ?? []);
usort($allProductsList, fn($a, $b) => strtotime($b['created_at'] ?? 0) - strtotime($a['created_at'] ?? 0));

$totalPublished = count($products ?? []);
$totalDrafts    = count($drafts ?? []);
$totalAll       = $totalPublished + $totalDrafts;

$kycBadgeClass = match ($kyc_status ?? 'none') {
    'approved' => 'badge-success',
    'rejected' => 'badge-danger',
    'pending'  => 'badge-warning',
    default    => 'badge-muted',
};
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<style>
/* ===== SELLER CENTER LAYOUT ===== */
.sc-layout {
    display: flex;
    min-height: calc(100vh - var(--header-h));
    max-width: 1440px;
    margin: 0 auto;
}

/* Sidebar */
.sc-sidebar {
    width: 224px;
    flex-shrink: 0;
    border-right: 1px solid var(--border);
    position: sticky;
    top: var(--header-h);
    height: calc(100vh - var(--header-h));
    overflow-y: auto;
    background: var(--bg);
    padding: 24px 0 40px;
    scrollbar-width: thin;
    scrollbar-color: var(--border) transparent;
}
.sc-seller-info {
    padding: 4px 18px 20px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 6px;
}
.sc-avatar {
    width: 48px; height: 48px;
    border-radius: 50%;
    background: var(--accent);
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 1.15rem; color: white;
    margin-bottom: 10px;
}
.sc-seller-name { font-weight: 700; font-size: 0.9rem; }
.sc-seller-role { font-size: 0.75rem; color: var(--muted); margin-bottom: 8px; }

.badge {
    display: inline-flex; align-items: center;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 0.68rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.04em;
}
.badge-success { background: rgba(34,197,94,0.12);  color: #22c55e; }
.badge-warning { background: rgba(245,158,11,0.12); color: #f59e0b; }
.badge-danger  { background: rgba(239,68,68,0.12);  color: #ef4444; }
.badge-muted   { background: rgba(148,163,184,0.12);color: var(--muted); }
.badge-pub     { background: rgba(34,197,94,0.12);  color: #22c55e; }
.badge-draft   { background: rgba(148,163,184,0.1); color: var(--muted); }
.badge-pending { background: rgba(245,158,11,0.12); color: #f59e0b; }

.sc-nav { list-style: none; }
.sc-nav-item a {
    display: flex; align-items: center; gap: 9px;
    padding: 9px 18px;
    font-size: 0.865rem; font-weight: 500;
    color: var(--muted);
    border-left: 2px solid transparent;
    transition: all var(--trans);
}
.sc-nav-item a:hover { background: var(--surface); color: var(--text); }
.sc-nav-item a.active { color: var(--accent); border-left-color: var(--accent); background: rgba(59,130,246,0.07); }
.sc-nav-divider { height: 1px; background: var(--border); margin: 8px 16px; }

/* Main */
.sc-main { flex: 1; min-width: 0; padding: 28px 28px 56px; }
.sc-page-header {
    display: flex; align-items: flex-start;
    justify-content: space-between; gap: 12px;
    margin-bottom: 24px; flex-wrap: wrap;
}
.sc-page-title    { font-size: 1.35rem; font-weight: 800; margin-bottom: 2px; }
.sc-page-subtitle { font-size: 0.84rem; color: var(--muted); }

/* Alert banner */
.sc-alert {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 13px 17px;
    border-radius: var(--radius);
    margin-bottom: 20px;
    font-size: 0.855rem;
}
.sc-alert.warning { background: rgba(245,158,11,0.09); border: 1px solid rgba(245,158,11,0.3); }
.sc-alert.info    { background: rgba(59,130,246,0.08);  border: 1px solid rgba(59,130,246,0.25); }
.sc-alert.danger  { background: rgba(239,68,68,0.08);   border: 1px solid rgba(239,68,68,0.3); color:#ef4444; }
.sc-alert-title   { font-weight: 700; margin-bottom: 2px; }
.sc-alert-body    { flex: 1; }
.sc-alert-link    { text-decoration: underline; opacity: 0.85; }

/* Stats */
.sc-stats {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 13px;
    margin-bottom: 24px;
}
.sc-stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 17px 18px;
}
.sc-stat-label { font-size: 0.72rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 6px; }
.sc-stat-value { font-size: 1.7rem; font-weight: 800; line-height: 1; }
.sc-stat-sub   { font-size: 0.72rem; color: var(--muted); margin-top: 4px; }

/* Products card */
.sc-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
}
.sc-tab-bar {
    display: flex;
    padding: 0 16px;
    border-bottom: 1px solid var(--border);
    gap: 0;
}
.sc-tab {
    padding: 12px 16px;
    font-size: 0.845rem; font-weight: 500;
    color: var(--muted);
    border-bottom: 2px solid transparent;
    background: none; border-top: none; border-left: none; border-right: none;
    cursor: pointer;
    display: flex; align-items: center; gap: 5px;
    transition: all var(--trans);
}
.sc-tab:hover { color: var(--text); }
.sc-tab.active { color: var(--accent); border-bottom-color: var(--accent); }
.sc-tab-count {
    font-size: 0.68rem; font-weight: 700;
    background: var(--surface2);
    border-radius: 10px;
    padding: 1px 6px;
    min-width: 18px; text-align: center;
}
.sc-tab.active .sc-tab-count { background: rgba(59,130,246,0.18); color: var(--accent); }

/* Toolbar */
.sc-toolbar {
    display: flex; align-items: center; gap: 10px;
    padding: 13px 18px;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
}
.sc-search-wrap { position: relative; flex: 1; min-width: 180px; max-width: 320px; }
.sc-search-wrap svg { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; }
.sc-search-input {
    width: 100%;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 8px 12px 8px 34px;
    color: var(--text); font-size: 0.855rem; font-family: inherit;
    outline: none;
    transition: border-color var(--trans);
}
.sc-search-input:focus { border-color: var(--accent); }

/* Table */
.sc-table { width: 100%; border-collapse: collapse; }
.sc-table th {
    padding: 11px 14px;
    text-align: left;
    font-size: 0.72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.07em;
    color: var(--muted); background: var(--surface2);
    border-bottom: 1px solid var(--border); white-space: nowrap;
}
.sc-table th:first-child { padding-left: 20px; }
.sc-table td {
    padding: 14px; border-bottom: 1px solid var(--border);
    vertical-align: middle; font-size: 0.855rem;
}
.sc-table td:first-child { padding-left: 20px; }
.sc-table tr:last-child td { border-bottom: none; }
.sc-table tbody tr { transition: background var(--trans); }
.sc-table tbody tr:hover { background: rgba(255,255,255,0.02); }

.sc-product-cell { display: flex; align-items: center; gap: 13px; }
.sc-product-img {
    width: 62px; height: 62px;
    border-radius: 8px; object-fit: cover;
    flex-shrink: 0; background: var(--surface2);
    border: 1px solid var(--border);
}
.sc-product-img-placeholder {
    width: 62px; height: 62px;
    border-radius: 8px; background: var(--surface2);
    border: 1px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    color: var(--muted); flex-shrink: 0;
}
.sc-product-name { font-weight: 600; margin-bottom: 2px; line-height: 1.3; }
.sc-product-desc { font-size: 0.76rem; color: var(--muted); max-width: 240px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sc-product-id   { font-size: 0.68rem; color: var(--muted); margin-top: 2px; }

/* Action buttons */
.sc-actions { display: flex; align-items: center; gap: 5px; flex-wrap: nowrap; }
.sc-action-btn {
    padding: 5px 11px;
    border-radius: 6px;
    font-size: 0.76rem; font-weight: 600; font-family: inherit;
    cursor: pointer;
    border: 1px solid var(--border);
    background: transparent; color: var(--text);
    transition: all var(--trans);
    display: inline-flex; align-items: center; gap: 4px;
    text-decoration: none; white-space: nowrap;
}
.sc-action-btn:hover  { background: var(--surface2); }
.sc-action-btn.edit   { color: var(--accent);  border-color: rgba(59,130,246,0.3); }
.sc-action-btn.edit:hover { background: rgba(59,130,246,0.1); }
.sc-action-btn.pub    { color: #22c55e; border-color: rgba(34,197,94,0.3); }
.sc-action-btn.pub:hover  { background: rgba(34,197,94,0.1); }
.sc-action-btn.unpub  { color: var(--muted); }
.sc-action-btn.del    { color: var(--danger); border-color: rgba(239,68,68,0.3); }
.sc-action-btn.del:hover  { background: rgba(239,68,68,0.08); }

/* Empty state */
.sc-empty { text-align: center; padding: 60px 20px; color: var(--muted); }
.sc-empty-icon { font-size: 2.8rem; margin-bottom: 12px; }
.sc-empty h3   { font-size: 1rem; font-weight: 700; color: var(--text); margin-bottom: 7px; }
.sc-empty p    { font-size: 0.855rem; margin-bottom: 18px; }

@media (max-width: 900px) {
    .sc-sidebar {
        position: fixed;
        top: 0; left: 0;
        height: 100vh;
        z-index: 1002;
        transform: translateX(-100%);
        transition: transform 0.25s ease;
        box-shadow: 4px 0 24px rgba(0,0,0,0.3);
        background: var(--bg);
    }
    .sc-sidebar.open { transform: translateX(0); }
    #sidebarBackdrop { display: block; }
    .sc-main { padding: 16px 14px 32px; }
    .sc-stats { grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .sc-table th:nth-child(4), .sc-table td:nth-child(4),
    .sc-table th:nth-child(5), .sc-table td:nth-child(5) { display: none; }
    .sc-product-desc { display: none; }
}
</style>
<body>
<?php include __DIR__ . '/parts/header.php'; ?>
<div id="sidebarBackdrop" class="sidebar-backdrop" aria-hidden="true"></div>

<div class="sc-layout">

    <!-- ===== SELLER SIDEBAR ===== -->
    <aside class="sc-sidebar" id="sidebar" aria-label="Seller navigation">
        <div class="sidebar-close-row" id="sidebarCloseRow">
            <div class="sidebar-close-logo">
                <img src="/assets/images/ginto.png" alt="Ginto">
                <span>ePower</span>
            </div>
            <button class="sidebar-close-btn" id="sidebarClose" aria-label="Close menu">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="sc-seller-info">
            <div class="sc-avatar"><?= strtoupper(substr($_SESSION['username'] ?? $_SESSION['email'] ?? 'S', 0, 1)) ?></div>
            <div class="sc-seller-name"><?= htmlspecialchars($_SESSION['username'] ?? 'Seller') ?></div>
            <div class="sc-seller-role"><?= $is_admin ? 'Admin' : 'Seller Account' ?></div>
            <?php if (!$is_admin): ?>
            <span class="badge <?= $kycBadgeClass ?>">KYC: <?= htmlspecialchars(ucfirst($kyc_status ?? 'none')) ?></span>
            <?php endif; ?>
        </div>
        <ul class="sc-nav" role="list">
            <li class="sc-nav-item">
                <a href="/marketplace/sellers/products" class="active" aria-current="page">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                    My Products
                </a>
            </li>
            <li class="sc-nav-item">
                <a href="/marketplace/sellers/products/new">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    New Product
                </a>
            </li>
            <li class="sc-nav-divider" role="separator"></li>
            <?php if ($is_admin): ?>
            <li class="sc-nav-item">
                <a href="/admin/kyc">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                    KYC Submissions
                </a>
            </li>
            <li class="sc-nav-divider" role="separator"></li>
            <?php else: ?>
            <li class="sc-nav-item">
                <a href="/marketplace/sellers/kyc">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><polyline points="2 10 22 10"/></svg>
                    KYC Verification
                </a>
            </li>
            <li class="sc-nav-divider" role="separator"></li>
            <?php endif; ?>
            <li class="sc-nav-item">
                <a href="/marketplace">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                    View Marketplace
                </a>
            </li>
        </ul>
    </aside>

    <!-- ===== MAIN ===== -->
    <main class="sc-main">

        <!-- Page header -->
        <div class="sc-page-header">
            <div>
                <h1 class="sc-page-title">Product Management</h1>
                <p class="sc-page-subtitle">Manage your listings, drafts, and publish status</p>
            </div>
            <a href="/marketplace/sellers/products/new" class="btn btn-primary">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12l7-7 7 7"/></svg>
                Add Product
            </a>
        </div>

        <!-- KYC alerts -->
        <?php if (($kyc_status ?? '') === 'pending'): ?>
        <div class="sc-alert warning" role="alert">
            <svg width="17" height="17" fill="none" stroke="#f59e0b" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div class="sc-alert-body">
                <div class="sc-alert-title" style="color:#f59e0b">KYC Under Review</div>
                <div>Your documents are being reviewed. Listings won't be publicly visible until approved.
                    <a href="/marketplace/sellers/kyc" class="sc-alert-link" style="color:#f59e0b">Check status →</a>
                </div>
            </div>
        </div>
        <?php elseif (($kyc_status ?? '') === 'rejected'): ?>
        <div class="sc-alert danger" role="alert">
            <svg width="17" height="17" fill="none" stroke="#ef4444" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <div class="sc-alert-body">
                <div class="sc-alert-title">KYC Rejected — Action Required</div>
                <div>Your KYC was rejected. Resubmit with valid documents.
                    <a href="/marketplace/sellers/kyc" class="sc-alert-link">Update KYC →</a>
                </div>
            </div>
        </div>
        <?php elseif (!in_array($kyc_status ?? '', ['approved', 'pending'])): ?>
        <div class="sc-alert info" role="alert">
            <svg width="17" height="17" fill="none" stroke="var(--accent)" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div class="sc-alert-body">
                <div class="sc-alert-title" style="color:var(--accent)">Complete KYC to start selling</div>
                <div>Verify your identity to publish products.
                    <a href="/marketplace/sellers/kyc" class="sc-alert-link" style="color:var(--accent)">Start KYC →</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="sc-stats">
            <div class="sc-stat-card">
                <div class="sc-stat-label">All Products</div>
                <div class="sc-stat-value"><?= $totalAll ?></div>
                <div class="sc-stat-sub">Total listings</div>
            </div>
            <div class="sc-stat-card">
                <div class="sc-stat-label">Published</div>
                <div class="sc-stat-value" style="color:#22c55e"><?= $totalPublished ?></div>
                <div class="sc-stat-sub">Live on store</div>
            </div>
            <div class="sc-stat-card">
                <div class="sc-stat-label">Drafts</div>
                <div class="sc-stat-value" style="color:var(--muted)"><?= $totalDrafts ?></div>
                <div class="sc-stat-sub">Not published</div>
            </div>
        </div>

        <!-- Product table card -->
        <div class="sc-card">
            <!-- Tabs -->
            <div class="sc-tab-bar" role="tablist">
                <button class="sc-tab active" role="tab" aria-selected="true" data-tab="all">
                    All <span class="sc-tab-count" id="tabCountAll"><?= $totalAll ?></span>
                </button>
                <button class="sc-tab" role="tab" aria-selected="false" data-tab="published">
                    Published <span class="sc-tab-count" id="tabCountPublished"><?= $totalPublished ?></span>
                </button>
                <button class="sc-tab" role="tab" aria-selected="false" data-tab="draft">
                    Drafts <span class="sc-tab-count" id="tabCountDraft"><?= $totalDrafts ?></span>
                </button>
            </div>

            <!-- Toolbar -->
            <div class="sc-toolbar">
                <div class="sc-search-wrap">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input type="search" id="productSearch" class="sc-search-input" placeholder="Search products…" autocomplete="off" aria-label="Search products">
                </div>
            </div>

            <!-- Table -->
            <div style="overflow-x:auto">
                <table class="sc-table" id="productTable" aria-label="Your products">
                    <thead>
                        <tr>
                            <th style="min-width:260px">Product</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Added</th>
                            <th style="min-width:160px">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="productTableBody">
                    <?php foreach ($allProductsList as $p):
                        $imgs       = [];
                        if (!empty($p['images'])) { $imgs = json_decode($p['images'], true) ?: []; }
                        if (empty($imgs) && !empty($p['image_path'])) $imgs = [$p['image_path']];
                        $imgSrc   = $imgs[0] ?? null;
                        $status   = $p['status'] ?? 'draft';
                        $badgeCls = match ($status) {
                            'published' => 'badge-pub',
                            'draft'     => 'badge-draft',
                            'pending'   => 'badge-pending',
                            default     => 'badge-muted',
                        };
                        $price    = $p['price'] ?? $p['price_amount'] ?? 0;
                        $currency = $p['currency'] ?? $p['price_currency'] ?? 'USD';
                        $qty      = $p['quantity'] ?? $p['stock'] ?? 0;
                    ?>
                    <tr data-status="<?= htmlspecialchars($status) ?>" data-title="<?= htmlspecialchars(strtolower($p['title'] ?? '')) ?>">
                        <td>
                            <div class="sc-product-cell">
                                <?php if ($imgSrc): ?>
                                <img class="sc-product-img" src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($p['title'] ?? '') ?>" loading="lazy">
                                <?php else: ?>
                                <div class="sc-product-img-placeholder" aria-hidden="true">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <div class="sc-product-name"><?= htmlspecialchars($p['title'] ?? '') ?></div>
                                    <div class="sc-product-desc"><?= htmlspecialchars($p['short_description'] ?? '') ?></div>
                                    <div class="sc-product-id">#<?= htmlspecialchars($p['id']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td style="font-weight:700;white-space:nowrap"><?= htmlspecialchars($currency) ?> <?= number_format((float)$price, 2) ?></td>
                        <td><?= (int)$qty ?></td>
                        <td><span class="badge <?= $badgeCls ?>"><?= htmlspecialchars(ucfirst($status)) ?></span></td>
                        <td style="white-space:nowrap;color:var(--muted);font-size:0.775rem"><?= htmlspecialchars(date('M j, Y', strtotime($p['created_at'] ?? 'now'))) ?></td>
                        <td>
                            <div class="sc-actions">
                                <?php if ($status === 'published'): ?>
                                <a href="/marketplace" class="sc-action-btn" style="color:#22c55e;border-color:rgba(34,197,94,0.3)" target="_blank" aria-label="View <?= htmlspecialchars($p['title'] ?? '') ?> on store">
                                    <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                    View
                                </a>
                                <?php endif; ?>
                                <a href="/marketplace/sellers/products/edit/<?= htmlspecialchars($p['id']) ?>"
                                   class="sc-action-btn edit"
                                   aria-label="Edit <?= htmlspecialchars($p['title'] ?? '') ?>">
                                    <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    Edit
                                </a>
                                <form method="POST" action="/marketplace/sellers/products/toggle" style="display:contents">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($p['id']) ?>">
                                    <input type="hidden" name="current_status" value="<?= htmlspecialchars($status) ?>">
                                    <button type="submit"
                                        class="sc-action-btn <?= $status === 'published' ? 'unpub' : 'pub' ?>"
                                        aria-label="<?= $status === 'published' ? 'Unpublish' : 'Publish' ?>">
                                        <?php if ($status === 'published'): ?>
                                        <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                                        Hide
                                        <?php else: ?>
                                        <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                        Publish
                                        <?php endif; ?>
                                    </button>
                                </form>
                                <form method="POST" action="/marketplace/sellers/products/delete" style="display:contents"
                                      onsubmit="return confirm('Delete this product? This action cannot be undone.')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($p['id']) ?>">
                                    <button type="submit" class="sc-action-btn del" aria-label="Delete product">
                                        <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($totalAll === 0): ?>
                <div class="sc-empty">
                    <div class="sc-empty-icon">📦</div>
                    <h3>No products yet</h3>
                    <p>Start building your store by adding your first product listing.</p>
                    <a href="/marketplace/sellers/products/new" class="btn btn-primary">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12l7-7 7 7"/></svg>
                        Add Your First Product
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </main>
</div>

<script>
(function () {
    const tabs     = document.querySelectorAll('.sc-tab[data-tab]');
    const rows     = document.querySelectorAll('#productTableBody tr');
    const search   = document.getElementById('productSearch');
    let   activeTab = 'all';

    function filterRows() {
        const q = search ? search.value.toLowerCase() : '';
        rows.forEach(function (row) {
            const matchTab  = activeTab === 'all' || row.dataset.status === activeTab;
            const matchQ    = !q || (row.dataset.title || '').includes(q);
            row.style.display = (matchTab && matchQ) ? '' : 'none';
        });
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('active'); t.setAttribute('aria-selected', 'false'); });
            tab.classList.add('active');
            tab.setAttribute('aria-selected', 'true');
            activeTab = tab.dataset.tab;
            filterRows();
        });
    });

    if (search) search.addEventListener('input', filterRows);
}());
</script>

<?php include __DIR__ . '/parts/footer.php'; ?>
</body>
</html>
