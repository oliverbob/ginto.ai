<?php
$storefront = $storefront ?? [];
$seller = $storefront['seller'] ?? [];
$storeProducts = $products ?? [];
$isOwner = !empty($_SESSION['user_id']) && (int)($_SESSION['user_id']) === (int)($storefront['user_id'] ?? 0);
$bannerImg = $storefront['banner_image'] ?? '';
$logoImg   = $storefront['logo_image']   ?? '';
$storeSlug = htmlspecialchars($storefront['slug'] ?? '', ENT_QUOTES, 'UTF-8');
$storeName = htmlspecialchars($storefront['display_name'] ?? 'Storefront', ENT_QUOTES, 'UTF-8');
?>
<style>
.sf-hero {
    max-width:1400px; margin:24px auto 0; padding:0 18px;
}
.sf-hero-inner {
    position:relative; overflow:hidden; border-radius:24px;
    background:linear-gradient(135deg, rgba(48,77,163,0.18), rgba(72,183,255,0.10) 50%, rgba(255,214,102,0.18));
    border:1px solid var(--border); padding:32px 32px 28px;
}
.sf-hero-banner {
    position:absolute; inset:0; background-size:cover; background-position:center;
    opacity:0.22; border-radius:inherit;
}
.sf-hero-body { display:flex; flex-wrap:wrap; align-items:flex-end; justify-content:space-between; gap:20px; position:relative; z-index:1; }
.sf-hero-left { display:flex; align-items:center; gap:20px; max-width:760px; }
.sf-store-logo {
    width:80px; height:80px; border-radius:20px; flex-shrink:0;
    background:rgba(255,255,255,0.88); border:2px solid rgba(255,255,255,0.7);
    display:flex; align-items:center; justify-content:center;
    font-size:2rem; font-weight:800; color:#1e3a5f;
    box-shadow:0 12px 28px rgba(0,0,0,0.10); overflow:hidden;
}
.sf-store-logo img { width:100%; height:100%; object-fit:cover; border-radius:inherit; }
.sf-store-label { font-size:0.74rem; letter-spacing:0.15em; text-transform:uppercase; color:var(--muted); font-weight:700; }
.sf-store-name { font-size:1.9rem; line-height:1.1; margin:6px 0 8px; font-weight:800; }
.sf-store-desc { font-size:0.9rem; line-height:1.7; color:var(--muted); margin:0; }
.sf-hero-actions { display:flex; gap:10px; flex-wrap:wrap; align-items:center; position:relative; z-index:1; }
.sf-hero-meta { display:flex; gap:10px; flex-wrap:wrap; margin-top:18px; position:relative; z-index:1; }
.sf-meta-pill {
    padding:6px 14px; border-radius:999px;
    background:rgba(255,255,255,0.7); border:1px solid rgba(255,255,255,0.6);
    font-size:0.8rem; font-weight:600; color:#234;
}
.sf-products { max-width:1400px; margin:24px auto 60px; padding:0 18px; }
.sf-toolbar {
    display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap;
    gap:12px; margin-bottom:20px; padding:12px 16px;
    background:var(--surface); border-radius:14px; border:1px solid var(--border);
}
.sf-search-wrap { display:flex; align-items:center; gap:9px; flex:1; min-width:180px; }
.sf-search-wrap input {
    border:none; background:transparent; outline:none; color:var(--text);
    font-size:0.875rem; width:100%; max-width:300px;
}
.sf-right-controls { display:flex; align-items:center; gap:14px; flex-shrink:0; }
.sf-count { font-size:0.84rem; color:var(--muted); }
.sf-count strong { color:var(--text); }
.sf-empty { text-align:center; padding:64px 20px; color:var(--muted); }
.sf-empty-icon { font-size:3rem; margin-bottom:14px; }
@media(max-width:640px) {
    .sf-hero-inner { padding:20px 18px; }
    .sf-store-logo { width:60px; height:60px; font-size:1.5rem; }
    .sf-store-name { font-size:1.4rem; }
}
</style>

<!-- ===== HERO BANNER ===== -->
<div class="sf-hero">
    <div class="sf-hero-inner">
        <?php if ($bannerImg): ?>
        <div class="sf-hero-banner" style="background-image:url('<?= htmlspecialchars($bannerImg, ENT_QUOTES, 'UTF-8') ?>');"></div>
        <?php endif; ?>

        <div class="sf-hero-body">
            <div class="sf-hero-left">
                <div class="sf-store-logo">
                    <?php if ($logoImg): ?>
                    <img src="<?= htmlspecialchars($logoImg, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $storeName ?> logo">
                    <?php else: ?>
                    <?= strtoupper(substr($storefront['display_name'] ?? 'S', 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="sf-store-label">Official Seller Storefront</div>
                    <h1 class="sf-store-name"><?= $storeName ?></h1>
                    <p class="sf-store-desc"><?= htmlspecialchars($storefront['description'] ?? 'Browse products from this seller on Ginto Mall.', ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            <div class="sf-hero-actions">
                <a href="/marketplace" class="btn btn-secondary">← Mall</a>
                <?php if (!empty($seller['username'])): ?>
                <a href="/user/profile/<?= htmlspecialchars($seller['username'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary">Seller Profile</a>
                <?php endif; ?>
                <?php if ($isOwner): ?>
                <a href="/marketplace/sellers/storefront" class="btn btn-primary">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true" style="flex-shrink:0"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit Store
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="sf-hero-meta">
            <span class="sf-meta-pill">🔗 ginto.ai/mall/<?= $storeSlug ?></span>
            <span class="sf-meta-pill">👤 <?= htmlspecialchars($seller['fullname'] ?? ($seller['username'] ?? 'Seller'), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="sf-meta-pill">📦 <?= count($storeProducts) ?> listing<?= count($storeProducts) !== 1 ? 's' : '' ?></span>
        </div>
    </div>
</div>

<!-- ===== PRODUCTS ===== -->
<div class="sf-products">
    <!-- Toolbar -->
    <div class="sf-toolbar">
        <div class="sf-search-wrap">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" style="color:var(--muted);flex-shrink:0"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="searchInput" placeholder="Search in <?= $storeName ?>..." aria-label="Search store products">
        </div>
        <div class="sf-right-controls">
            <span class="sf-count"><strong id="resultCount">0</strong> result<?= count($storeProducts) !== 1 ? 's' : '' ?></span>
            <div style="position:relative;">
                <button class="sort-btn" id="sortBtn" aria-haspopup="listbox" aria-expanded="false" aria-label="Sort products">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 18h6M3 6h18M3 12h12"/></svg>
                    <span id="sortLabel">Sort</span>
                    <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <ul class="sort-dropdown" id="sortDropdown" role="listbox" aria-label="Sort options">
                    <li role="option" aria-selected="true"  data-sort="default">Default</li>
                    <li role="option" aria-selected="false" data-sort="price_asc">Price: Low → High</li>
                    <li role="option" aria-selected="false" data-sort="price_desc">Price: High → Low</li>
                    <li role="option" aria-selected="false" data-sort="rating">Highest Rated</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Grid (populated by footer.php JS) -->
    <div id="productGrid" class="product-grid" aria-live="polite" aria-label="<?= $storeName ?> products"></div>
</div>

<!-- Cart drawer (required by footer.php JS) -->
<div id="drawerOverlay" class="drawer-overlay" aria-hidden="true"></div>
<aside id="cartDrawer" class="cart-drawer" role="dialog" aria-modal="true" aria-label="Shopping cart" aria-hidden="true">
    <div class="drawer-header">
        <h2>Your Cart</h2>
        <button class="action-btn" onclick="toggleCart()" aria-label="Close cart">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div class="drawer-body" id="cartItems"><p class="cart-empty-msg">Your cart is empty.</p></div>
    <div class="drawer-footer">
        <div class="cart-total-row"><span>Total</span><span id="cartTotal">$0.00</span></div>
        <button class="btn btn-primary" style="width:100%" onclick="checkout()">Checkout</button>
    </div>
</aside>

<!-- Quick View modal (required by footer.php JS) -->
<div id="qvOverlay" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="qvTitle" aria-hidden="true">
    <div class="modal-box">
        <div class="modal-img-side">
            <div class="qv-main-wrap" id="qvMainWrap">
                <img id="qvImg" src="" alt="" draggable="false">
                <div class="qv-zoom-lens" id="qvZoomLens"></div>
                <button class="qv-arrow qv-prev" id="qvPrev" onclick="qvNav(-1)" aria-label="Previous image">&#8249;</button>
                <button class="qv-arrow qv-next" id="qvNext" onclick="qvNav(1)" aria-label="Next image">&#8250;</button>
                <div class="qv-counter" id="qvCounter" style="display:none"></div>
            </div>
            <div class="qv-thumbs" id="qvThumbs" style="display:none"></div>
        </div>
        <div class="modal-info-side">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                <h2 id="qvTitle" style="font-size:1.25rem;font-weight:700;line-height:1.3;flex:1;"></h2>
                <button onclick="closeQV()" class="action-btn" aria-label="Close quick view" style="flex-shrink:0;margin-top:-6px;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div id="qvRating" style="color:#f59e0b;font-size:1.1rem;letter-spacing:1px;"></div>
            <div id="qvPrice" style="font-size:1.5rem;font-weight:700;"></div>
            <p id="qvDesc" style="color:var(--muted);font-size:0.875rem;line-height:1.65;flex:1;"></p>
            <button class="btn btn-primary" id="qvAddBtn" style="margin-top:8px;">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                Add to Cart
            </button>
        </div>
    </div>
</div>