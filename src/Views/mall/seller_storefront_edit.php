<?php
/** @var array  $storefront   Current seller_storefronts row (always set — ensureStorefront was called) */
/** @var string $csrf_token */
/** @var bool   $is_admin */
/** @var bool   $saved         True if just saved successfully */
/** @var string|null $error_msg */
?>
<?php
$title      = 'My Storefront — Seller Center';
$isLoggedIn = true;
$is_admin   = $is_admin ?? false;
$_reqUri    = $_SERVER['REQUEST_URI'] ?? '';
$basePath   = str_starts_with($_reqUri, '/wallet/') ? '/wallet' : '/marketplace/sellers';
$sf         = $storefront ?? [];
$slug       = htmlspecialchars($sf['slug'] ?? '', ENT_QUOTES, 'UTF-8');
$storeUrl   = 'https://ginto.ai/mall/' . $slug;
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<style>
/* ===== SELLER CENTER LAYOUT (shared with seller_products) ===== */
.sc-layout { display:flex; min-height:calc(100vh - var(--header-h)); max-width:1440px; margin:0 auto; }
.sc-sidebar { width:224px;flex-shrink:0;border-right:1px solid var(--border);position:sticky;top:var(--header-h);height:calc(100vh - var(--header-h));overflow-y:auto;background:var(--bg);padding:24px 0 40px;scrollbar-width:thin;scrollbar-color:var(--border) transparent; }
.sc-seller-info { padding:4px 18px 20px;border-bottom:1px solid var(--border);margin-bottom:6px; }
.sc-avatar { width:48px;height:48px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.15rem;color:white;margin-bottom:10px; }
.sc-seller-name { font-weight:700;font-size:0.9rem; }
.sc-seller-role { font-size:0.75rem;color:var(--muted);margin-bottom:8px; }
.sc-nav { list-style:none; }
.sc-nav-item a { display:flex;align-items:center;gap:9px;padding:9px 18px;font-size:0.865rem;font-weight:500;color:var(--muted);border-left:2px solid transparent;transition:all var(--trans); }
.sc-nav-item a:hover { background:var(--surface);color:var(--text); }
.sc-nav-item a.active { color:var(--accent);border-left-color:var(--accent);background:rgba(59,130,246,0.07); }
.sc-nav-divider { height:1px;background:var(--border);margin:8px 16px; }
.sc-main { flex:1;min-width:0;padding:28px 28px 56px; }
.sc-page-header { display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:24px;flex-wrap:wrap; }
.sc-page-title { font-size:1.35rem;font-weight:800;margin-bottom:2px; }
.sc-page-subtitle { font-size:0.84rem;color:var(--muted); }
.sidebar-close-row { display:none; }

/* ===== STOREFRONT EDITOR ===== */
.sf-edit-card { background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:28px 28px 32px;margin-bottom:24px; }
.sf-edit-card-title { font-size:1.05rem;font-weight:700;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border); }
.sf-field { margin-bottom:18px; }
.sf-label { display:block;font-size:0.81rem;font-weight:600;margin-bottom:6px;color:var(--text); }
.sf-hint { font-size:0.76rem;color:var(--muted);margin-top:4px;line-height:1.5; }
.sf-input { display:block;width:100%;padding:9px 12px;background:var(--bg);border:1px solid var(--border);border-radius:10px;color:var(--text);font-size:0.875rem;transition:border-color var(--trans); }
.sf-input:focus { outline:none;border-color:var(--accent); }
.sf-textarea { min-height:100px;resize:vertical; }
.sf-slug-wrap { display:flex;align-items:center;border:1px solid var(--border);border-radius:10px;background:var(--bg);overflow:hidden; }
.sf-slug-prefix { padding:9px 10px 9px 12px;font-size:0.875rem;color:var(--muted);white-space:nowrap;border-right:1px solid var(--border);background:var(--surface); }
.sf-slug-input { flex:1;border:none;background:transparent;padding:9px 12px;font-size:0.875rem;color:var(--text);outline:none; }
.sf-url-preview { font-size:0.78rem;color:var(--muted);margin-top:6px; }
.sf-url-preview a { color:var(--accent);font-weight:600; }

.sf-img-row { display:grid;grid-template-columns:1fr 1fr;gap:16px; }
@media(max-width:640px) { .sf-img-row { grid-template-columns:1fr; } }

.sf-img-upload { border:2px dashed var(--border);border-radius:14px;padding:18px;text-align:center;cursor:pointer;transition:border-color var(--trans); }
.sf-img-upload:hover { border-color:var(--accent); }
.sf-img-preview { max-width:100%;max-height:120px;object-fit:cover;border-radius:10px;margin-bottom:10px;display:none; }
.sf-img-upload label { cursor:pointer; }

.sf-toggle-row { display:flex;align-items:center;gap:12px; }
.sf-toggle { position:relative;display:inline-block;width:44px;height:24px; }
.sf-toggle input { opacity:0;width:0;height:0; }
.sf-toggle-thumb { position:absolute;cursor:pointer;inset:0;background:var(--border);border-radius:999px;transition:.25s; }
.sf-toggle-thumb:before { content:'';position:absolute;height:18px;width:18px;left:3px;bottom:3px;background:white;border-radius:50%;transition:.25s; }
.sf-toggle input:checked + .sf-toggle-thumb { background:var(--accent); }
.sf-toggle input:checked + .sf-toggle-thumb:before { transform:translateX(20px); }

.sf-actions { display:flex;gap:12px;align-items:center;flex-wrap:wrap; }
.sf-copy-btn { font-size:0.82rem;padding:8px 16px;border-radius:10px;border:1px solid var(--border);background:var(--surface);color:var(--text);cursor:pointer;transition:all var(--trans); }
.sf-copy-btn:hover { border-color:var(--accent);color:var(--accent); }

.sf-alert { display:flex;align-items:flex-start;gap:10px;padding:12px 16px;border-radius:12px;font-size:0.84rem;margin-bottom:20px; }
.sf-alert-success { background:rgba(34,197,94,0.09);border:1px solid rgba(34,197,94,0.22);color:#22c55e; }
.sf-alert-error { background:rgba(239,68,68,0.09);border:1px solid rgba(239,68,68,0.2);color:#ef4444; }

/* Mobile sidebar */
@media(max-width:767px) {
    .sc-layout { display:block; }
    .sc-sidebar { display:none; }
    .sc-main { padding:20px 16px 48px; }
    .sidebar-close-row { display:flex;align-items:center;justify-content:space-between;padding:14px 18px 0; }
}
</style>
<body>
<?php include __DIR__ . '/parts/header.php'; ?>

<div class="sc-layout">
    <!-- ===== SIDEBAR ===== -->
    <aside class="sc-sidebar" id="sidebar" aria-label="Seller navigation">
        <div class="sc-seller-info">
            <div class="sc-avatar"><?= strtoupper(substr($_SESSION['username'] ?? $_SESSION['email'] ?? 'S', 0, 1)) ?></div>
            <div class="sc-seller-name"><?= htmlspecialchars($_SESSION['username'] ?? 'Seller') ?></div>
            <div class="sc-seller-role"><?= $is_admin ? 'Admin' : 'Seller Account' ?></div>
        </div>
        <ul class="sc-nav" role="list">
            <li class="sc-nav-item">
                <a href="<?= $basePath ?>/products">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                    My Products
                </a>
            </li>
            <li class="sc-nav-item">
                <a href="<?= $basePath ?>/products/new">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    New Product
                </a>
            </li>
            <li class="sc-nav-item">
                <a href="<?= $basePath ?>/storefront" class="active" aria-current="page">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    My Storefront
                </a>
            </li>
            <li class="sc-nav-divider" role="separator"></li>
            <?php if ($is_admin): ?>
            <li class="sc-nav-item">
                <a href="/admin/kyc">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    KYC Submissions
                </a>
            </li>
            <?php else: ?>
            <li class="sc-nav-item">
                <a href="<?= $basePath === '/wallet' ? '/wallet/kyc' : '/marketplace/sellers/kyc' ?>">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><polyline points="2 10 22 10"/></svg>
                    KYC Verification
                </a>
            </li>
            <?php endif; ?>
            <li class="sc-nav-divider" role="separator"></li>
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
        <div class="sc-page-header">
            <div>
                <h1 class="sc-page-title">My Storefront</h1>
                <p class="sc-page-subtitle">Customize your store page — share the link to promote your listings</p>
            </div>
            <div class="sf-actions">
                <button class="sf-copy-btn" id="copyLinkBtn" type="button" onclick="copyStoreLink()">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="vertical-align:-2px;margin-right:4px"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                    Copy Store Link
                </button>
                <a href="/mall/<?= $slug ?>" target="_blank" class="btn btn-secondary">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="flex-shrink:0"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    Preview Store
                </a>
            </div>
        </div>

        <!-- Success / error banners -->
        <?php if ($saved ?? false): ?>
        <div class="sf-alert sf-alert-success" role="status">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            Storefront saved! Your store page is live at <a href="/mall/<?= $slug ?>" target="_blank" style="color:inherit;font-weight:700;">/mall/<?= $slug ?></a>
        </div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
        <div class="sf-alert sf-alert-error" role="alert">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= $error_msg ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= $basePath ?>/storefront/save" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

            <!-- Store Identity -->
            <div class="sf-edit-card">
                <div class="sf-edit-card-title">Store Identity</div>

                <div class="sf-field">
                    <label class="sf-label" for="sf-display-name">Store Name <span style="color:#ef4444">*</span></label>
                    <input class="sf-input" id="sf-display-name" type="text" name="display_name" maxlength="100" required
                        value="<?= htmlspecialchars($sf['display_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="e.g. Maria's Fashion Corner">
                    <span class="sf-hint">This is the name buyers will see on your storefront page.</span>
                </div>

                <div class="sf-field">
                    <label class="sf-label" for="sf-slug">Store URL</label>
                    <div class="sf-slug-wrap">
                        <span class="sf-slug-prefix">ginto.ai/mall/</span>
                        <input class="sf-slug-input" id="sf-slug" type="text" name="slug"
                            maxlength="64" pattern="[a-z0-9][a-z0-9\-]*"
                            value="<?= $slug ?>"
                            oninput="updateSlugPreview(this.value)"
                            placeholder="your-store-name">
                    </div>
                    <div class="sf-url-preview">
                        Your store: <a id="slugPreviewLink" href="/mall/<?= $slug ?>" target="_blank"><?= htmlspecialchars('https://ginto.ai/mall/' . ($sf['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
                    </div>
                    <span class="sf-hint">Lowercase letters, numbers, and hyphens only. This is the shareable URL for your store.</span>
                </div>

                <div class="sf-field">
                    <label class="sf-label" for="sf-description">Store Description</label>
                    <textarea class="sf-input sf-textarea" id="sf-description" name="description" maxlength="1000"
                        placeholder="Tell buyers what you sell and why they should shop with you…"><?= htmlspecialchars($sf['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    <span class="sf-hint">Shown on your public storefront. Up to 1,000 characters.</span>
                </div>

                <div class="sf-field">
                    <div class="sf-toggle-row">
                        <label class="sf-toggle" aria-label="Store active">
                            <input type="checkbox" name="is_active" value="1" <?= !empty($sf['is_active']) ? 'checked' : '' ?>>
                            <span class="sf-toggle-thumb"></span>
                        </label>
                        <div>
                            <div style="font-size:0.875rem;font-weight:600;">Store is active</div>
                            <div style="font-size:0.76rem;color:var(--muted);">When off, your storefront page will not be publicly visible.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Store Media -->
            <div class="sf-edit-card">
                <div class="sf-edit-card-title">Store Images</div>
                <div class="sf-img-row">

                    <!-- Logo -->
                    <div class="sf-field">
                        <label class="sf-label">Store Logo</label>
                        <div class="sf-img-upload" onclick="document.getElementById('logo-upload').click()">
                            <?php if (!empty($sf['logo_image'])): ?>
                            <img src="<?= htmlspecialchars($sf['logo_image'], ENT_QUOTES, 'UTF-8') ?>" alt="Current logo" class="sf-img-preview" id="logoPreview" style="display:block">
                            <?php else: ?>
                            <img src="" alt="" class="sf-img-preview" id="logoPreview">
                            <?php endif; ?>
                            <div style="color:var(--muted);font-size:0.82rem;line-height:1.6;">
                                <div style="font-size:1.5rem;margin-bottom:6px;">🏪</div>
                                <div style="font-weight:600;">Upload Logo</div>
                                <div>Square image recommended · PNG or JPG · max 2 MB</div>
                            </div>
                        </div>
                        <input type="file" id="logo-upload" name="logo_image" accept="image/*" style="display:none" onchange="previewImage(this,'logoPreview')">
                    </div>

                    <!-- Banner -->
                    <div class="sf-field">
                        <label class="sf-label">Banner Image</label>
                        <div class="sf-img-upload" onclick="document.getElementById('banner-upload').click()">
                            <?php if (!empty($sf['banner_image'])): ?>
                            <img src="<?= htmlspecialchars($sf['banner_image'], ENT_QUOTES, 'UTF-8') ?>" alt="Current banner" class="sf-img-preview" id="bannerPreview" style="display:block">
                            <?php else: ?>
                            <img src="" alt="" class="sf-img-preview" id="bannerPreview">
                            <?php endif; ?>
                            <div style="color:var(--muted);font-size:0.82rem;line-height:1.6;">
                                <div style="font-size:1.5rem;margin-bottom:6px;">🖼️</div>
                                <div style="font-weight:600;">Upload Banner</div>
                                <div>Wide image (1200×300) recommended · PNG or JPG · max 5 MB</div>
                            </div>
                        </div>
                        <input type="file" id="banner-upload" name="banner_image" accept="image/*" style="display:none" onchange="previewImage(this,'bannerPreview')">
                    </div>

                </div>
            </div>

            <!-- Save -->
            <div style="display:flex;gap:12px;align-items:center;">
                <button type="submit" class="btn btn-primary">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true" style="flex-shrink:0"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Save Storefront
                </button>
                <a href="/mall/<?= $slug ?>" class="btn btn-secondary" target="_blank">
                    Preview →
                </a>
            </div>

        </form>
    </main>
</div>

<?php include __DIR__ . '/parts/footer.php'; ?>

<script>
function updateSlugPreview(val) {
    var clean = val.toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
    var url = 'https://ginto.ai/mall/' + (clean || '…');
    var link = document.getElementById('slugPreviewLink');
    if (link) { link.textContent = url; link.href = '/mall/' + clean; }
}
function previewImage(input, previewId) {
    var file = input.files[0];
    var img = document.getElementById(previewId);
    if (!img || !file) return;
    var reader = new FileReader();
    reader.onload = function(e) { img.src = e.target.result; img.style.display = 'block'; };
    reader.readAsDataURL(file);
}
function copyStoreLink() {
    var url = '<?= htmlspecialchars('https://ginto.ai/mall/' . ($sf['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?>';
    navigator.clipboard.writeText(url).then(function() {
        var btn = document.getElementById('copyLinkBtn');
        if (btn) { var orig = btn.innerHTML; btn.innerHTML = '✓ Copied!'; setTimeout(function() { btn.innerHTML = orig; }, 2000); }
    });
}
</script>
</body>
</html>
