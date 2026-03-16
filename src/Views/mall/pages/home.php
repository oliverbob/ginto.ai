<?php
/**
 * home.php — ePower Mall main page
 * Variables: $categories (array), $products (array), $csrf_token (string), $isLoggedIn (bool)
 */
$isLoggedIn = !empty($_SESSION['user_id']);
?>
<!-- Mobile sidebar backdrop -->
<div id="sidebarBackdrop" class="sidebar-backdrop" aria-hidden="true"></div>

<div class="page-layout">

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar" role="complementary" aria-label="Categories and filters">

        <!-- Close row (mobile only, shown via CSS) -->
        <div class="sidebar-close-row" id="sidebarCloseRow">
            <div class="sidebar-close-logo">
                <img src="/assets/images/ginto.png" alt="Ginto">
                <span>ePower</span>
            </div>
            <button class="sidebar-close-btn" id="sidebarClose" aria-label="Close menu">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <div class="sidebar-inner">

            <!-- Categories -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">Categories</div>
                <ul class="cat-list" role="list" id="catList">
                    <li class="cat-item active" data-cat="all" role="button" tabindex="0" aria-pressed="true">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        All Products
                    </li>
                    <?php foreach ($categories as $cat): ?>
                    <li class="cat-item"
                        data-cat="<?= htmlspecialchars((string)$cat['id'], ENT_QUOTES, 'UTF-8') ?>"
                        data-slug="<?= htmlspecialchars($cat['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        role="button" tabindex="0" aria-pressed="false">
                        <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Status filters -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">Status</div>
                <label class="filter-row">
                    <input type="checkbox" id="filterSale" aria-label="On sale only"> On Sale
                </label>
                <label class="filter-row">
                    <input type="checkbox" id="filterShip" aria-label="Free shipping only"> Free Shipping
                </label>
            </div>

            <!-- Virtual card promo -->
            <div class="sidebar-section">
                <div class="sidebar-section-title">Virtual Cards</div>
                <div class="promo-section">
                    <div class="promo-card" onclick="addToCart(101)" role="button" tabindex="0" aria-label="Add ePower Starter to cart">
                        <img src="/assets/images/ginto2.png" alt="ePower Starter">
                        <div class="promo-card-overlay"></div>
                        <div class="promo-card-label"><span class="promo-num">1</span> ePower Starter</div>
                    </div>
                    <div class="promo-card" onclick="addToCart(102)" role="button" tabindex="0" aria-label="Add ePower Gold to cart">
                        <img src="/assets/images/ginto3.png" alt="ePower Gold">
                        <div class="promo-card-overlay"></div>
                        <div class="promo-card-label"><span class="promo-num">2</span> ePower Gold</div>
                    </div>
                    <div class="promo-card" onclick="addToCart(103)" role="button" tabindex="0" aria-label="Add ePower Premium to cart">
                        <img src="/assets/images/ginto4.png" alt="ePower Premium">
                        <div class="promo-card-overlay"></div>
                        <div class="promo-card-label"><span class="promo-num">3</span> ePower Premium</div>
                    </div>
                </div>
            </div>

        </div>
    </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content" id="mainContent">

        <!-- Toolbar -->
        <div class="main-toolbar">
            <p class="results-text">Showing <strong id="resultCount">0</strong> results</p>

            <div class="toolbar-actions">
                <?php if ($isLoggedIn): ?>
                <a href="/marketplace/sellers/products" class="btn btn-secondary">My Products</a>
                <a href="/marketplace/sellers/kyc" class="btn btn-secondary">KYC</a>
                <button class="btn btn-primary" onclick="openUploadModal()">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12l7-7 7 7"/></svg>
                    Sell
                </button>
                <?php endif; ?>

                <div class="sort-wrap">
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

        <!-- Product grid (populated by JS) -->
        <div id="productGrid" class="product-grid" aria-live="polite" aria-label="Products"></div>

    </main>

</div>

<!-- ===== CART DRAWER ===== -->
<div id="drawerOverlay" class="drawer-overlay" aria-hidden="true"></div>
<aside id="cartDrawer" class="cart-drawer" role="dialog" aria-modal="true" aria-label="Shopping cart" aria-hidden="true">
    <div class="drawer-header">
        <h2>Your Cart</h2>
        <button class="action-btn" onclick="toggleCart()" aria-label="Close cart">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div class="drawer-body" id="cartItems">
        <p class="cart-empty-msg">Your cart is empty.</p>
    </div>
    <div class="drawer-footer">
        <div class="cart-total-row">
            <span>Total</span>
            <span id="cartTotal">$0.00</span>
        </div>
        <button class="btn btn-primary" style="width:100%" onclick="checkout()">Checkout</button>
    </div>
</aside>

<!-- ===== QUICK VIEW MODAL ===== -->
<div id="qvOverlay" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="qvTitle" aria-hidden="true">
    <div class="modal-box">
        <div class="modal-img-side">
            <img id="qvImg" src="" alt="">
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

<!-- ===== SELL / UPLOAD MODAL (logged-in users only) ===== -->
<?php if ($isLoggedIn): ?>
<div id="uploadOverlay" class="upload-overlay" role="dialog" aria-modal="true" aria-labelledby="uploadTitle" aria-hidden="true">
    <div class="upload-modal">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;">
            <h2 id="uploadTitle" style="font-size:1.15rem;font-weight:700;">List a Product</h2>
            <button onclick="closeUploadModal()" class="action-btn" aria-label="Close" style="width:32px;height:32px;">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="uploadForm" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">
                <label class="form-label" for="up-title">Title <span aria-hidden="true">*</span></label>
                <input class="form-input" id="up-title" name="title" type="text" required placeholder="What are you selling?">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label class="form-label" for="up-price">Price <span aria-hidden="true">*</span></label>
                    <input class="form-input" id="up-price" name="price" type="number" step="0.01" min="0" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label" for="up-currency">Currency</label>
                    <select class="form-input" id="up-currency" name="currency">
                        <option value="USD">USD</option>
                        <option value="PHP">PHP</option>
                        <option value="NGN">NGN</option>
                        <option value="EUR">EUR</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="up-category">Category</label>
                <select class="form-input" id="up-category" name="category_id">
                    <option value="">No Category</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars((string)$cat['id'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="up-desc">Short Description</label>
                <textarea class="form-input" id="up-desc" name="short_description" rows="3" placeholder="Brief description of your product…"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="up-image">Product Image</label>
                <input class="form-input" id="up-image" name="image" type="file" accept="image/*">
                <div id="uploadPreview" style="margin-top:8px;"></div>
            </div>

            <div id="uploadError" style="display:none;color:#ef4444;font-size:0.85rem;margin-bottom:10px;" role="alert"></div>

            <div style="display:flex;gap:10px;margin-top:6px;">
                <button type="submit" class="btn btn-primary" style="flex:1" id="uploadSubmitBtn">Upload Product</button>
                <button type="button" onclick="closeUploadModal()" class="btn btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Toast container -->
<div id="toastContainer" class="toast-container" aria-live="assertive" aria-atomic="false"></div>
