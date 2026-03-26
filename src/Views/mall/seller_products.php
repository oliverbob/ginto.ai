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
// Use /wallet/ namespace when requested from there, else legacy path
$_reqUri    = $_SERVER['REQUEST_URI'] ?? '';
$basePath   = str_starts_with($_reqUri, '/wallet/') ? '/wallet/products' : '/marketplace/sellers/products';

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
            <a class="sidebar-close-logo" href="/mall" aria-label="Open mall home">
                <img src="/assets/images/mall.png" alt="Mall">
                <span>ePower</span>
            </a>
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
            <?php $sfBase = str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/wallet/') ? '/wallet' : '/marketplace/sellers'; ?>
            <li class="sc-nav-item">
                <a href="<?= $sfBase ?>/storefront">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    My Storefront
                </a>
            </li>
            <li class="sc-nav-item">
                <a href="<?= $basePath ?>" class="active" aria-current="page">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                    My Products
                </a>
            </li>
            <li class="sc-nav-item">
                <a href="<?= $basePath ?>/new">
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
            <a href="<?= $basePath ?>/new" class="btn btn-primary">
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

        <!-- Delivery Zone Card -->
        <div class="sc-card" id="deliveryZoneCard" style="margin-bottom:20px;padding:20px 18px;">
            <!-- Header -->
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                <div style="width:44px;height:44px;border-radius:14px;background:linear-gradient(135deg,#1e3a5f,#2563eb);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <svg width="22" height="22" fill="none" stroke="#fff" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:700;font-size:1.05rem;margin-bottom:2px;">Delivery Zones</div>
                    <div style="font-size:0.82rem;color:var(--muted);line-height:1.4;">
                        Physical products are shown only within your zones. Digital products are shown everywhere.
                    </div>
                </div>
            </div>

            <!-- GPS Detect Button -->
            <button id="dz-gps-btn" type="button" onclick="dzDetectGPS()"
                style="width:100%;padding:12px 16px;margin-bottom:14px;border-radius:14px;border:1px dashed var(--accent);background:rgba(37,99,235,0.06);color:var(--accent);font-size:0.88rem;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all 0.2s;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3"/><circle cx="12" cy="12" r="9" stroke-dasharray="4 2"/></svg>
                📍 Detect My Location &amp; Suggest Zones
            </button>

            <!-- Suggested zones from GPS -->
            <div id="dz-suggestions" style="display:none;margin-bottom:14px;">
                <div style="font-size:0.8rem;font-weight:600;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">📡 Nearby Zones (tap to add)</div>
                <div id="dz-suggested-list" style="display:flex;flex-wrap:wrap;gap:6px;"></div>
            </div>

            <!-- Map container -->
            <div id="dz-map-wrap" style="display:none;margin-bottom:14px;border-radius:14px;overflow:hidden;border:1px solid var(--border);position:relative;">
                <div id="dz-map" style="width:100%;height:240px;"></div>
                <div style="position:absolute;bottom:8px;left:8px;right:8px;background:rgba(0,0,0,0.6);color:#fff;font-size:0.75rem;padding:6px 10px;border-radius:8px;text-align:center;">
                    Tap the map to add a zone at that location
                </div>
            </div>

            <!-- Current zones -->
            <div id="dz-current" style="margin-bottom:12px;min-height:36px;display:flex;flex-wrap:wrap;gap:6px;"></div>

            <!-- Main zone hint -->
            <div id="dz-main-hint" style="display:none;font-size:0.78rem;color:var(--muted);margin-bottom:12px;">
                🏠 = Main zone (delivery fees calculated from here). Tap a zone to set main. ✕ to remove.
            </div>

            <!-- Search -->
            <div style="margin-bottom:8px;">
                <div style="position:relative;">
                    <svg style="position:absolute;left:12px;top:50%;transform:translateY(-50%);opacity:0.4;" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input id="dz-search" type="text" placeholder="Search barangay…"
                        style="width:100%;padding:12px 14px 12px 36px;border:1px solid var(--border);border-radius:14px;background:var(--bg);color:var(--text);font-size:0.88rem;box-sizing:border-box;">
                </div>
                <div id="dz-results" style="margin-top:4px;max-height:220px;overflow-y:auto;border:1px solid var(--border);border-radius:12px;display:none;background:var(--surface);"></div>
            </div>

            <!-- Actions -->
            <div style="display:flex;gap:8px;align-items:center;">
                <button id="dz-save-btn" class="btn btn-primary btn-sm" style="flex:1;padding:12px 20px;border-radius:14px;font-weight:600;font-size:0.9rem;">💾 Save Zones</button>
                <div id="dz-count" style="font-size:0.78rem;color:var(--muted);white-space:nowrap;"></div>
            </div>
            <input type="hidden" id="dz-home-id" value="">
        </div>

        <script>
        (function(){
            var _csrf = <?= json_encode($csrf_token ?? '') ?>;
            var selectedZones = []; // [{id, name, city, province, lat, lng}]
            var homeId = 0;
            var _map = null;
            var _markers = [];
            var _sellerLat = null, _sellerLng = null;

            function htmlesc(s){ var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

            function renderZones() {
                var el = document.getElementById('dz-current');
                var hint = document.getElementById('dz-main-hint');
                var count = document.getElementById('dz-count');
                if (!selectedZones.length) {
                    el.innerHTML = '<div style="padding:14px 16px;background:rgba(37,99,235,0.06);border:1px dashed var(--accent);border-radius:14px;font-size:0.85rem;color:var(--accent);display:flex;align-items:center;gap:10px;width:100%;box-sizing:border-box;">'
                        + '<span style="font-size:1.2rem;">📍</span> Use the button above to detect your location, or search for barangays below.</div>';
                    hint.style.display = 'none';
                    count.textContent = '';
                    return;
                }
                hint.style.display = 'block';
                count.textContent = selectedZones.length + ' / 50 zones';
                el.innerHTML = selectedZones.map(function(z) {
                    var isHome = z.id === homeId;
                    return '<span onclick="setMainZone(' + z.id + ')" style="display:inline-flex;align-items:center;gap:5px;'
                        + 'background:' + (isHome ? 'linear-gradient(135deg,rgba(214,180,75,0.18),rgba(214,180,75,0.08))' : 'rgba(255,255,255,0.05)') + ';'
                        + 'border:1px solid ' + (isHome ? 'rgba(214,180,75,0.45)' : 'rgba(255,255,255,0.1)') + ';'
                        + 'border-radius:20px;padding:6px 12px;font-size:0.82rem;cursor:pointer;transition:all 0.15s;">'
                        + (isHome ? '<span title="Main zone">🏠</span> ' : '<span style="opacity:0.35;">📦</span> ')
                        + '<span>' + htmlesc(z.name) + ', <span style="color:var(--muted);font-size:0.76rem;">' + htmlesc(z.city) + '</span></span>'
                        + '<button onclick="event.stopPropagation();removeZone(' + z.id + ')" style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:1.05rem;padding:0 0 0 6px;line-height:1;" title="Remove zone">✕</button>'
                        + '</span>';
                }).join('');
                updateMapMarkers();
            }

            window.setMainZone = function(id) {
                homeId = id;
                document.getElementById('dz-home-id').value = homeId;
                renderZones();
            };

            window.removeZone = function(id) {
                selectedZones = selectedZones.filter(function(z){ return z.id !== id; });
                if (homeId === id) homeId = selectedZones.length ? selectedZones[0].id : 0;
                document.getElementById('dz-home-id').value = homeId;
                renderZones();
            };

            function addZoneObj(z) {
                var id = parseInt(z.id);
                if (selectedZones.some(function(x){ return x.id === id; })) return false;
                if (selectedZones.length >= 50) { alert('Maximum 50 delivery zones'); return false; }
                selectedZones.push({id:id, name:z.name, city:z.city, province:z.province, lat:parseFloat(z.lat)||0, lng:parseFloat(z.lng)||0});
                if (!homeId) { homeId = id; document.getElementById('dz-home-id').value = homeId; }
                return true;
            }

            // ── GPS Detect ──────────────────────────────────────────────────
            window.dzDetectGPS = function() {
                var btn = document.getElementById('dz-gps-btn');
                btn.disabled = true;
                btn.innerHTML = '<span style="animation:spin 1s linear infinite;display:inline-block;">⏳</span> Detecting location…';

                if (!navigator.geolocation) {
                    btn.innerHTML = '❌ Geolocation not supported';
                    btn.disabled = false;
                    return;
                }

                navigator.geolocation.getCurrentPosition(function(pos) {
                    _sellerLat = pos.coords.latitude;
                    _sellerLng = pos.coords.longitude;
                    btn.innerHTML = '✅ Location detected — loading nearby zones…';

                    // Fetch nearby barangays
                    fetch('/api/barangay/nearby?lat=' + _sellerLat + '&lng=' + _sellerLng + '&limit=15')
                        .then(function(r){ return r.json(); })
                        .then(function(d) {
                            if (!d.barangays || !d.barangays.length) {
                                btn.innerHTML = '📍 No zones found nearby. Search manually below.';
                                btn.disabled = false;
                                return;
                            }
                            // Show suggestions
                            var sugWrap = document.getElementById('dz-suggestions');
                            var sugList = document.getElementById('dz-suggested-list');
                            sugWrap.style.display = 'block';

                            // Auto-add the closest barangay as home zone
                            var closest = d.barangays[0];
                            addZoneObj(closest);
                            homeId = parseInt(closest.id);
                            document.getElementById('dz-home-id').value = homeId;
                            renderZones();

                            // Show remaining as suggestions
                            sugList.innerHTML = d.barangays.slice(1).map(function(b) {
                                var dist = b.dist_m < 1000 ? (b.dist_m + 'm') : ((b.dist_m/1000).toFixed(1) + 'km');
                                var already = selectedZones.some(function(z){ return z.id === parseInt(b.id); });
                                return '<button onclick="dzAddSuggested(this,' + b.id + ',\'' + b.name.replace(/'/g,'\\\'') + '\',\'' + b.city.replace(/'/g,'\\\'') + '\',\'' + b.province.replace(/'/g,'\\\'') + '\',' + (b.lat||0) + ',' + (b.lng||0) + ')" '
                                    + 'style="padding:6px 12px;border-radius:20px;border:1px solid ' + (already ? 'rgba(34,197,94,0.3)' : 'var(--border)') + ';'
                                    + 'background:' + (already ? 'rgba(34,197,94,0.08)' : 'var(--surface)') + ';color:var(--text);font-size:0.8rem;cursor:pointer;transition:all 0.15s;" '
                                    + (already ? 'disabled' : '') + '>'
                                    + (already ? '✓ ' : '+ ') + htmlesc(b.name) + ' <span style="color:var(--muted);font-size:0.72rem;">(' + dist + ')</span>'
                                    + '</button>';
                            }).join('');

                            btn.innerHTML = '✅ Location detected — ' + htmlesc(closest.name) + ' added as main zone';
                            btn.disabled = false;
                            btn.onclick = function(){ dzDetectGPS(); };

                            // Show map
                            initMap(_sellerLat, _sellerLng);
                        })
                        .catch(function() {
                            btn.innerHTML = '❌ Failed to load nearby zones';
                            btn.disabled = false;
                        });
                }, function(err) {
                    btn.disabled = false;
                    if (err.code === 1) {
                        btn.innerHTML = '🔒 Location permission denied. Enable it in your browser/app settings.';
                    } else {
                        btn.innerHTML = '❌ Could not get location. Try again.';
                    }
                    btn.onclick = function(){ dzDetectGPS(); };
                }, { enableHighAccuracy: true, timeout: 15000 });
            };

            window.dzAddSuggested = function(el, id, name, city, province, lat, lng) {
                if (addZoneObj({id:id, name:name, city:city, province:province, lat:lat, lng:lng})) {
                    el.disabled = true;
                    el.style.borderColor = 'rgba(34,197,94,0.3)';
                    el.style.background = 'rgba(34,197,94,0.08)';
                    el.innerHTML = '✓ ' + htmlesc(name);
                    renderZones();
                }
            };

            // ── Map (Leaflet from CDN) ──────────────────────────────────────
            window.initMap = function(lat, lng) {
                var wrap = document.getElementById('dz-map-wrap');
                wrap.style.display = 'block';

                if (_map) { _map.setView([lat, lng], 14); updateMapMarkers(); return; }

                // Load Leaflet CSS+JS if not already loaded
                if (!document.getElementById('leaflet-css')) {
                    var lnk = document.createElement('link');
                    lnk.id = 'leaflet-css';
                    lnk.rel = 'stylesheet';
                    lnk.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                    document.head.appendChild(lnk);
                }
                if (!window.L) {
                    var sc = document.createElement('script');
                    sc.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                    sc.onload = function(){ createMap(lat, lng); };
                    document.head.appendChild(sc);
                } else {
                    createMap(lat, lng);
                }
            }

            function createMap(lat, lng) {
                _map = L.map('dz-map', { zoomControl: true, attributionControl: false }).setView([lat, lng], 14);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                }).addTo(_map);

                // Seller location marker
                L.marker([lat, lng], {
                    icon: L.divIcon({ className: '', html: '<div style="background:#2563eb;width:16px;height:16px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,0.4);"></div>', iconSize:[22,22], iconAnchor:[11,11] })
                }).addTo(_map).bindPopup('📍 Your location');

                // Click map to add zone
                _map.on('click', function(e) {
                    var clickedLat = e.latlng.lat;
                    var clickedLng = e.latlng.lng;

                    fetch('/api/barangay/nearby?lat=' + clickedLat + '&lng=' + clickedLng + '&limit=1')
                        .then(function(r){ return r.json(); })
                        .then(function(d) {
                            if (d.barangays && d.barangays.length) {
                                var b = d.barangays[0];
                                if (addZoneObj(b)) {
                                    renderZones();
                                    // center the map on the detected barangay location and show candidates around that spot
                                    if (b.lat && b.lng) {
                                        _map.setView([b.lat, b.lng], 14);
                                    } else {
                                        _map.setView([clickedLat, clickedLng], 14);
                                    }
                                    loadNearbySuggestions(clickedLat, clickedLng);
                                } else {
                                    alert(b.name + ' is already in your zones.');
                                }
                            } else {
                                alert('No barangay found at this location. Try elsewhere.');
                            }
                        })
                        .catch(function() {
                            alert('Unable to determine barangay from clicked map location.');
                        });
                });

                updateMapMarkers();
            }

            function updateMapMarkers() {
                if (!_map) return;
                _markers.forEach(function(m){ _map.removeLayer(m); });
                _markers = [];
                selectedZones.forEach(function(z) {
                    if (!z.lat || !z.lng) return;
                    var isHome = z.id === homeId;
                    var m = L.marker([z.lat, z.lng], {
                        icon: L.divIcon({
                            className: '',
                            html: '<div style="background:' + (isHome ? '#d4b44b' : '#22c55e') + ';width:12px;height:12px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,0.3);"></div>',
                            iconSize:[16,16], iconAnchor:[8,8]
                        })
                    }).addTo(_map).bindPopup((isHome ? '🏠 ' : '📦 ') + htmlesc(z.name));
                    _markers.push(m);
                });
            }

            // ── Load existing zones ─────────────────────────────────────────
            fetch('/api/barangay/seller/zones?seller_id=<?= (int)($_SESSION['user_id'] ?? 0) ?>')
                .then(function(r){ return r.json(); })
                .then(function(d) {
                    if (!d.zones || !d.zones.length) return;
                    selectedZones = d.zones.map(function(z){ return {id:parseInt(z.id), name:z.name, city:z.city, province:z.province, lat:parseFloat(z.lat)||0, lng:parseFloat(z.lng)||0}; });
                    homeId = d.zones.find(function(z){ return z.is_home==1 || z.is_home===true; });
                    homeId = homeId ? parseInt(homeId.id) : (selectedZones[0] ? selectedZones[0].id : 0);
                    document.getElementById('dz-home-id').value = homeId;
                    renderZones();
                }).catch(function(){});

            // ── Live search ─────────────────────────────────────────────────
            var _st;
            document.getElementById('dz-search').addEventListener('input', function() {
                var q = this.value.trim();
                clearTimeout(_st);
                if (q.length < 2) { document.getElementById('dz-results').style.display='none'; return; }
                _st = setTimeout(function(){
                    fetch('/api/barangay/list?q=' + encodeURIComponent(q) + '&limit=20')
                        .then(function(r){ return r.json(); })
                        .then(function(d) {
                            var res = document.getElementById('dz-results');
                            if (!d.barangays || !d.barangays.length) {
                                res.innerHTML='<div style="padding:14px 16px;color:var(--muted);font-size:0.84rem;">No barangays found</div>';
                                res.style.display='block';
                                return;
                            }
                            res.innerHTML = d.barangays.map(function(b) {
                                var isGeo = String(b.id).indexOf('geo_') === 0;
                                var numId = isGeo ? 0 : parseInt(b.id);
                                var added = !isGeo && selectedZones.some(function(z){ return z.id === numId; });
                                var badge = isGeo ? ' <span style="color:#6366f1;font-size:0.68rem;background:rgba(99,102,241,0.12);padding:1px 5px;border-radius:3px;margin-left:4px;">🌍 GeoNames</span>' : '';
                                var onclick = added ? '' : (isGeo
                                    ? 'dzAddGeo(' + JSON.stringify(b.id).replace(/"/g,'&quot;') + ',' + JSON.stringify(b.name).replace(/"/g,'&quot;') + ',' + JSON.stringify(b.city||'').replace(/"/g,'&quot;') + ',' + JSON.stringify(b.province||'').replace(/"/g,'&quot;') + ',' + (b.lat||0) + ',' + (b.lng||0) + ')'
                                    : 'dzAddFromSearch(' + b.id + ',\'' + b.name.replace(/'/g,'\\\'') + '\',\'' + (b.city||'').replace(/'/g,'\\\'') + '\',\'' + (b.province||'').replace(/'/g,'\\\'') + '\',' + (b.lat||0) + ',' + (b.lng||0) + ')'); 
                                var onclickWithMap = added ? '' : (isGeo
                                    ? 'dzAddGeo(' + JSON.stringify(b.id).replace(/"/g,'&quot;') + ',' + JSON.stringify(b.name).replace(/"/g,'&quot;') + ',' + JSON.stringify(b.city||'').replace(/"/g,'&quot;') + ',' + JSON.stringify(b.province||'').replace(/"/g,'&quot;') + ',' + (b.lat||0) + ',' + (b.lng||0) + '); initMap(' + (b.lat||0) + ',' + (b.lng||0) + ')' 
                                    : 'dzAddFromSearch(' + b.id + ',\'' + b.name.replace(/'/g,'\\\'') + '\',\'' + (b.city||'').replace(/'/g,'\\\'') + '\',\'' + (b.province||'').replace(/'/g,'\\\'') + '\',' + (b.lat||0) + ',' + (b.lng||0) + '); initMap(' + (b.lat||0) + ',' + (b.lng||0) + ')' );
                                return '<div onclick="' + onclickWithMap + '" '
                                    + 'style="padding:12px 16px;cursor:' + (added ? 'default' : 'pointer') + ';border-bottom:1px solid var(--border);font-size:0.87rem;display:flex;justify-content:space-between;align-items:center;'
                                    + (added ? 'opacity:0.4;' : '') + 'transition:background 0.1s;" '
                                    + (added ? '' : 'onmouseover="this.style.background=\'rgba(255,255,255,0.04)\'" onmouseout="this.style.background=\'\'"') + '>'
                                    + '<span>' + htmlesc(b.name + ', ' + (b.city||'')) + ' <span style="color:var(--muted);font-size:0.76rem;">' + htmlesc(b.province||b.region||'') + '</span>' + badge + '</span>'
                                    + (added ? '<span style="color:#22c55e;font-size:0.78rem;">✓ Added</span>' : '<span style="color:var(--accent);font-size:0.78rem;font-weight:600;">+ Add</span>')
                                    + '</div>';
                            }).join('');
                            res.style.display = 'block';
                        }).catch(function(){});
                }, 250);
            });

            function loadNearbySuggestions(lat, lng) {
                var sugWrap = document.getElementById('dz-suggestions');
                var sugList = document.getElementById('dz-suggested-list');
                if (!sugWrap || !sugList) return;

                fetch('/api/barangay/nearby?lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng) + '&limit=15')
                    .then(function(r){ return r.json(); })
                    .then(function(d) {
                        if (!d.barangays || !d.barangays.length) {
                            sugWrap.style.display = 'none';
                            return;
                        }

                        sugWrap.style.display = 'block';
                        sugList.innerHTML = d.barangays.map(function(b){
                            var added = selectedZones.some(function(z){ return z.id === parseInt(b.id); });
                            return '<button onclick="dzAddSuggested(this,' + b.id + ',\'' + b.name.replace(/'/g,'\\\'') + '\',\'' + (b.city||'').replace(/'/g,'\\\'') + '\',\'' + (b.province||'').replace(/'/g,'\\\'') + '\',' + (b.lat||0) + ',' + (b.lng||0) + ')" '
                                + 'style="padding:6px 12px;border-radius:20px;border:1px solid ' + (added ? 'rgba(34,197,94,0.3)' : 'var(--border)') + ';'
                                + 'background:' + (added ? 'rgba(34,197,94,0.08)' : 'var(--surface)') + ';color:var(--text);font-size:0.8rem;cursor:pointer;transition:all 0.15s;" '
                                + (added ? 'disabled' : '') + '>'
                                + (added ? '✓ ' : '+ ') + htmlesc(b.name) + ' <span style="color:var(--muted);font-size:0.72rem;">(' + (b.dist_m<1000 ? (b.dist_m + 'm') : ((b.dist_m/1000).toFixed(1) + 'km')) + ')</span>'
                                + '</button>';
                        }).join('');
                    }).catch(function() {
                        // ignore nearby load failures
                    });
            }

            window.dzAddGeo = function(geoId, name, city, province, lat, lng) {
                var numericGeoId = parseInt(String(geoId).replace('geo_', ''));
                var body = new URLSearchParams();
                body.append('geoname_id', numericGeoId);
                body.append('csrf_token', _csrf);
                fetch('/api/barangay/register-geo', {method:'POST', body:body})
                    .then(function(r){ return r.json(); })
                    .then(function(d) {
                        if (d.success && d.barangay) {
                            dzAddFromSearch(d.barangay.id, d.barangay.name, d.barangay.city||city, d.barangay.province||province, d.barangay.lat||lat, d.barangay.lng||lng);
                        }
                    }).catch(function(){});
            };

            window.dzAddFromSearch = function(id, name, city, province, lat, lng) {
                if (addZoneObj({id:id, name:name, city:city, province:province, lat:lat, lng:lng})) {
                    document.getElementById('dz-results').style.display = 'none';
                    document.getElementById('dz-search').value = '';
                    renderZones();
                    // show map for selected search location
                    if (!_map) {
                        initMap(lat, lng);
                    } else {
                        _map.setView([lat, lng], 14);
                        updateMapMarkers();
                    }
                    // now show nearby candidate barangays around map point
                    loadNearbySuggestions(lat, lng);
                }
            };

            // ── Save ────────────────────────────────────────────────────────
            document.getElementById('dz-save-btn').addEventListener('click', function(){
                var btn = this; btn.disabled = true; btn.textContent = '⏳ Saving…';
                var body = new URLSearchParams();
                body.append('csrf_token', _csrf);
                body.append('home_barangay_id', homeId);
                selectedZones.forEach(function(z){ body.append('barangay_ids[]', z.id); });
                fetch('/api/barangay/seller/zones/save', { method:'POST', body: body })
                    .then(function(r){ return r.json(); })
                    .then(function(d) {
                        btn.disabled = false;
                        if (d.success) {
                            btn.textContent = '✅ Saved!';
                            btn.style.background = '#16a34a';
                            setTimeout(function(){ btn.textContent = '💾 Save Zones'; btn.style.background = ''; }, 2500);
                        } else {
                            btn.textContent = '💾 Save Zones';
                            alert('Error: ' + (d.error || 'Failed'));
                        }
                    }).catch(function(){ btn.disabled=false; btn.textContent='💾 Save Zones'; alert('Network error'); });
            });
        })();
        </script>

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
                                <a href="<?= $basePath ?>/edit/<?= htmlspecialchars($p['id']) ?>"
                                   class="sc-action-btn edit"
                                   aria-label="Edit <?= htmlspecialchars($p['title'] ?? '') ?>">
                                    <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    Edit
                                </a>
                                <form method="POST" action="<?= $basePath ?>/toggle" style="display:contents">
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
                                <form method="POST" action="<?= $basePath ?>/delete" style="display:contents"
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
                    <a href="<?= $basePath ?>/new" class="btn btn-primary">
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
