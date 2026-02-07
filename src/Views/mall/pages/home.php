<!-- Main Layout -->
<div class="container wrapper">
    <!-- Mobile Sidebar Backdrop -->
    <div id="mobileSidebarBackdrop" class="sidebar-backdrop" onclick="toggleSidebar()" aria-hidden="true"></div>

    <!-- Sidebar Filters -->
    <aside class="sidebar" id="sidebar" aria-hidden="true" role="dialog" aria-label="Sidebar Menu">
        <div class="mobile-sidebar-header">
            <div style="display:flex;align-items:center;gap:10px;">
                <img src="/assets/images/ginto.png" alt="Ginto AI" class="w-10 h-10 rounded-full shadow-md border-2 border-amber-400 bg-white" style="object-fit:cover;height:44px;width:44px;" />
                <span class="ml-2 text-lg font-semibold text-blue-200">Ginto AI</span>
            </div>
            <button id="sidebarCloseBtn" aria-label="Close menu" class="close-btn" title="Close menu" aria-hidden="true">✕</button>
            <button class="cat-btn" data-cat="electronics">Electronics</button>
            <button class="cat-btn" data-cat="fashion">Fashion</button>
            <button class="cat-btn" data-cat="home">Home & Living</button>
            <button class="cat-btn" data-cat="sports">Sports</button>
        </div>

        <div class="filter-group">
            <div class="filter-title">Status</div>
            <label style="display:flex;gap:8px;margin-bottom:6px;cursor:pointer">
                <input type="checkbox" id="filterSale"> On Sale
            </label>
            <label style="display:flex;gap:8px;cursor:pointer">
                <input type="checkbox" id="filterShip"> Free Shipping
            </label>
        </div>
        <div class="gold-cards-heading" style="font-size:1.08rem;font-weight:400;color:#111;text-align:center;margin-bottom:10px;letter-spacing:0.01em;">Order your virtual card now!</div>
                
        <div class="gold-cards-grid">
            <div class="gold-card" onclick="addToCart(101)">
                <img class="gold-card-img" src="/assets/images/ginto2.png" alt="ePower Mall Card 1" />
                <div class="gold-card-overlay"></div>
                <div class="gold-card-label"><span class="card-num">1</span>ePower Starter</div>
            </div>
            <div class="gold-card" onclick="addToCart(102)">
                <img class="gold-card-img" src="/assets/images/ginto3.png" alt="ePower Mall Card 2" />
                <div class="gold-card-overlay"></div>
                <div class="gold-card-label"><span class="card-num">2</span>ePower Gold</div>
            </div>
            <div class="gold-card" onclick="addToCart(103)" style="grid-column:1/3;">
                <img class="gold-card-img" src="/assets/images/ginto4.png" alt="ePower Mall Card 3" />
                <div class="gold-card-overlay"></div>
                <div class="gold-card-label"><span class="card-num">3</span>ePower Premium</div>
            </div>
        </div>
    </aside>

    <!-- Product Grid -->
    <main class="main-content">
        <div class="top-controls">
            <div class="text-muted">Showing <strong id="resultCount">0</strong> results</div>
            <div>
            <?php if (!empty(
                // session check
                $_SESSION['user_id'] ?? false
            )): ?>
                <a href="/marketplace/sellers/products" class="px-3 py-2 bg-blue-600 text-white rounded mr-2">My Products</a>
                <span class="px-2 text-muted" aria-hidden="true">|</span>
                <a href="/marketplace/sellers/kyc" class="px-3 py-2 bg-yellow-500 text-white rounded">KYC</a>
            <?php endif; ?>
            </div>
        </div>
        <div id="productGrid" class="grid">
            <!-- Products Injected Here -->
        </div>
    </main>
</div>

    <!-- Upload Modal (for sellers) -->
    <div id="uploadBackdrop" class="modal-backdrop" aria-hidden="true">
        <div class="modal" id="uploadModal" role="dialog" aria-modal="true" style="max-width:720px;grid-template-columns:1fr;">
            <div style="padding:22px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <h2 style="margin:0;font-size:1.25rem">Sell an Item</h2>
                    <button class="btn-secondary" onclick="closeUploadModal()" style="padding:6px 10px">✕</button>
                </div>
                <form id="uploadForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div style="display:flex;gap:12px;flex-direction:column">
                        <label>Title <input name="title" required placeholder="Product title" style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--border)"></label>
                        <label>Price <input name="price" type="number" step="0.01" required placeholder="9.99" style="width:200px;padding:8px;border-radius:8px;border:1px solid var(--border)"></label>
                        <label>Category <input name="category" placeholder="fashion" style="width:200px;padding:8px;border-radius:8px;border:1px solid var(--border)"></label>
                        <label>Description <textarea name="description" rows="4" placeholder="Short description" style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--border)"></textarea></label>
                        <label>Image <input type="file" name="image" accept="image/*" id="uploadImageInput"></label>
                        <div style="display:flex;gap:8px;align-items:center">
                            <button class="btn btn-primary" type="submit">Upload</button>
                            <button type="button" class="btn btn-secondary" onclick="closeUploadModal()">Cancel</button>
                            <div id="uploadPreview" style="margin-left:10px"></div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Quick View Modal -->
<div id="qvBackdrop" class="modal-backdrop">
    <div class="modal" id="qvModal" role="dialog" aria-modal="true">
        <img id="qvImg" src="" alt="Product" class="modal-img">
        <div class="modal-content">
            <div style="margin-bottom: auto;">
                <div style="display:flex;justify-content:space-between;align-items:start">
                    <h2 id="qvTitle" style="margin:0;font-size:1.5rem;">Product Title</h2>
                    <button class="btn-secondary" onclick="closeModal()" style="padding:4px 8px;">✕</button>
                </div>
                <div id="qvRating" style="color:#f59e0b; margin:8px 0;">★★★★☆</div>
                <div id="qvPrice" style="font-size:1.5rem; font-weight:700; margin:12px 0; color:var(--accent);"></div>
                <p id="qvDesc" style="color:var(--text-muted); line-height:1.6;"></p>
            </div>
            <button id="qvAddBtn" class="btn btn-primary">Add to Cart</button>
        </div>
    </div>
</div>

<!-- Cart Drawer -->
<div id="cartDrawerContainer">
    <div class="drawer-backdrop" onclick="toggleCart()"></div>
    <div class="drawer">
        <div class="drawer-header">
            <h3 style="margin:0">Your Cart</h3>
            <button class="btn-secondary" onclick="toggleCart()" style="padding:6px 10px">✕</button>
        </div>
        <div class="drawer-body" id="cartItems">
            <!-- Cart Items -->
        </div>
        <div class="drawer-footer">
            <div style="display:flex;justify-content:space-between;margin-bottom:16px;font-weight:700;font-size:1.1rem">
                <span>Total</span>
                <span id="cartTotal">$0.00</span>
            </div>
            <button class="btn btn-primary" onclick="checkout()">Checkout</button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>