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
            <a class="sidebar-close-logo" href="/mall" aria-label="Open mall home">
                <img src="/assets/images/mall.png" alt="Mall">
                <span>ePower</span>
            </a>
            <button class="sidebar-close-btn" id="sidebarClose" aria-label="Close menu">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <div class="sidebar-inner">

            <!-- Back to Ginto Home -->
            <div class="sidebar-section" style="margin-bottom:16px">
                <a href="/" class="cat-item" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:inherit;" aria-label="Back to Ginto home">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    Ginto Home
                </a>
            </div>

            <!-- Mall quick links -->
            <div class="sidebar-section" style="margin-bottom:16px">
                <div class="sidebar-section-title">Mall</div>
                <a class="cat-item" href="/marketplace" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">
                    <span style="width:26px;height:26px;border-radius:8px;background:rgba(99,102,241,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="13" height="13" fill="none" stroke="#a5b4fc" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    </span>
                    Mall Home
                </a>
                <a class="cat-item" href="/mall/orders" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">
                    <span style="width:26px;height:26px;border-radius:8px;background:rgba(99,102,241,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="13" height="13" fill="none" stroke="#a5b4fc" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                    </span>
                    My Orders
                </a>
                <?php if ($isLoggedIn): ?>
                <a class="cat-item" href="/marketplace/sellers/storefront" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">
                    <span style="width:26px;height:26px;border-radius:8px;background:rgba(16,185,129,0.14);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="13" height="13" fill="none" stroke="#34d399" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10h18"/><path d="M5 10v10h14V10"/><path d="M2 10l2-6h16l2 6"/></svg>
                    </span>
                    My Storefront
                </a>
                <?php endif; ?>
                <a class="cat-item" href="/wallet" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">
                    <span style="width:26px;height:26px;border-radius:8px;background:linear-gradient(135deg,rgba(214,180,75,0.18),rgba(245,210,90,0.10));border:1px solid rgba(214,180,75,0.22);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:0.75rem;font-weight:800;color:#d4af37;">₱</span>
                    Ginto Pay<?php if ($isLoggedIn && isset($mall_wallet_balance)): ?> <span style="margin-left:auto;font-size:0.75rem;font-weight:700;color:#d4af37;">₱<?= number_format((float)$mall_wallet_balance, 2) ?></span><?php endif; ?>
                </a>
                <a class="cat-item" href="/wallet" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">
                    <span style="width:26px;height:26px;border-radius:8px;background:linear-gradient(135deg,rgba(99,102,241,0.18),rgba(139,92,246,0.12));border:1px solid rgba(99,102,241,0.22);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="13" height="13" fill="none" stroke="#a5b4fc" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M16 13a1 1 0 1 1 2 0 1 1 0 0 1-2 0z" fill="#a5b4fc" stroke="none"/><path d="M2 10h20"/></svg>
                    </span>
                    Ginto Wallet<?php if ($isLoggedIn && isset($mall_wallet_balance)): ?> <span style="margin-left:auto;font-size:0.75rem;font-weight:700;color:#a5b4fc;">₱<?= number_format((float)$mall_wallet_balance, 2) ?></span><?php endif; ?>
                </a>
                <a class="cat-item" href="/mall/delivery" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">
                    <span style="width:26px;height:26px;border-radius:8px;background:rgba(139,92,246,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="13" height="13" fill="none" stroke="#a78bfa" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 5v4h-7V8z"/><circle cx="5.5" cy="18.5" r="1.5"/><circle cx="18.5" cy="18.5" r="1.5"/></svg>
                    </span>
                    Delivery &amp; Tracking
                </a>
                <?php if ($isLoggedIn && !empty($_SESSION['role_id']) && (int)$_SESSION['role_id'] <= 2): ?>
                <a class="cat-item" href="/mall/delivery/admin" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">
                    <span style="width:26px;height:26px;border-radius:8px;background:rgba(59,130,246,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="13" height="13" fill="none" stroke="#93c5fd" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h18v4H3z"/><path d="M3 10h18v4H3z"/><path d="M3 17h18v4H3z"/></svg>
                    </span>
                    Logistics Dashboard
                </a>
                <?php endif; ?>
                <?php if ($isLoggedIn && !empty($_SESSION['is_rider'])): ?>
                <a class="cat-item" href="/mall/delivery/rider" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">
                    <span style="width:26px;height:26px;border-radius:8px;background:rgba(34,197,94,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="13" height="13" fill="none" stroke="#86efac" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                    </span>
                    Rider Dashboard
                </a>
                <?php endif; ?>
            </div>

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
                <!-- Barangay GPS Pill -->
                <div id="barangayPillWrap" style="position:relative;">
                    <button id="barangayPill"
                        onclick="toggleBarangayDropdown()"
                        style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:20px;border:1px solid var(--border);background:var(--bg-secondary,var(--bg));color:var(--text);font-size:0.82rem;cursor:pointer;white-space:nowrap;max-width:200px;overflow:hidden;text-overflow:ellipsis;"
                        title="Filter by barangay">
                        <span id="barangayPillIcon" style="font-size:1rem;"><?= empty($current_barangay) ? '🔍' : '📍' ?></span>
                        <span id="barangayPillText"><?php
                            if (!empty($current_barangay)):
                                echo htmlspecialchars($current_barangay['name'] . ', ' . $current_barangay['city'], ENT_QUOTES, 'UTF-8');
                            else: ?>Detecting location…<?php endif; ?></span>
                        <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div id="barangayDropdown" style="display:none;position:absolute;top:calc(100% + 6px);left:0;min-width:300px;background:var(--bg);border:1px solid var(--border);border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,0.18);z-index:1000;padding:12px;">
                        <div style="font-size:0.8rem;font-weight:600;color:var(--muted);margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid var(--border);">Your location</div>

                        <!-- Current location display -->
                        <div id="barangayCurrentDisplay" style="<?= empty($current_barangay) ? 'display:none;' : '' ?>margin-bottom:10px;padding:10px 12px;background:var(--bg-secondary,#f8fafc);border:1px solid var(--border,#cbd5e1);border-radius:8px;color:var(--text,#0f172a);">
                            <div style="font-size:0.75rem;color:var(--muted,#64748b);margin-bottom:2px;">Your current location is:</div>
                            <div id="barangayCurrentName" style="font-size:0.9rem;font-weight:700;color:var(--text,#0f172a);"><?php
                                if (!empty($current_barangay)):
                                    echo htmlspecialchars($current_barangay['name'] . ', ' . $current_barangay['city'], ENT_QUOTES, 'UTF-8');
                                endif; ?></div>
                            <div id="barangayCurrentProvince" style="font-size:0.78rem;color:var(--text, #f8fafc);opacity:0.9;"><?php
                                if (!empty($current_barangay)):
                                    echo htmlspecialchars($current_barangay['province'] ?? '', ENT_QUOTES, 'UTF-8');
                                endif; ?></div>
                        </div>

                        <button onclick="autoDetectBarangay(true)" style="width:100%;display:flex;align-items:center;gap:8px;padding:9px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg-secondary,var(--bg));color:var(--text);font-size:0.87rem;cursor:pointer;margin-bottom:8px;">
                            <span>📍</span> <span>Auto-detect my location</span>
                        </button>
                        <button onclick="openBarangayMapModal()" style="width:100%;display:flex;align-items:center;gap:8px;padding:9px 12px;border:1px solid var(--border);border-radius:8px;background:var(--surface3);color:var(--text);font-size:0.87rem;cursor:pointer;margin-bottom:8px;">
                            <span>📌</span><span>Pin your location on map</span>
                        </button>
                        <div style="font-size:0.78rem;color:var(--muted);margin-bottom:8px;">Pin your current location on the map to show real-time product availability in your area.</div>
                        <input id="barangaySearchInput" type="text" placeholder="Or search barangay manually…"
                            autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                            style="width:100%;padding:8px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text);font-size:0.87rem;margin-bottom:8px;box-sizing:border-box;"
                            oninput="searchBarangay(this.value)">
                        <div id="barangayResults" style="max-height:220px;overflow-y:auto;"></div>
                        <div id="barangayClearWrap" style="<?= empty($current_barangay) ? 'display:none;' : '' ?>border-top:1px solid var(--border);margin-top:8px;padding-top:8px;text-align:center;">
                            <button onclick="clearBarangay()" style="font-size:0.8rem;color:var(--muted);background:none;border:none;cursor:pointer;">✕ Clear — show all products</button>
                        </div>
                    </div>
                </div>
                <div id="barangayMapModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:2000;align-items:center;justify-content:center;padding:14px;">
                    <div style="width:100%;max-width:560px;background:var(--bg);border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:0 12px 30px rgba(0,0,0,0.45);">
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid var(--border);">
                            <strong style="font-size:0.95rem;">Pin location for products nearby</strong>
                            <button onclick="closeBarangayMapModal()" style="border:none;background:none;color:var(--muted);font-size:1.2rem;cursor:pointer;">✕</button>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:8px;padding:10px 14px;position:relative;">
                            <input type="text" autocomplete="off" name="fakeusernameremember" style="position:absolute;opacity:0;pointer-events:none;height:0;width:0;margin:0;padding:0;border:0;" />
                            <input id="barangayMapSearchInput" type="search" name="barangayMapSearch" placeholder="Search a place or address"
                                autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                                onfocus="this.setAttribute('autocomplete','off');"
                                oninput="window.handleBarangayMapTypeahead?.(this.value);"
                                style="padding:10px 12px;border:1px solid #94a3b8;border-radius:10px;background:var(--bg, #ffffff);color:var(--text, #0f172a);font-size:0.88rem;outline:none;" />
                            <div id="barangayMapTypeahead" style="position:absolute;top:52px;left:14px;right:80px;max-height:200px;overflow:auto;background:var(--bg);border:1px solid var(--border);border-radius:8px;box-shadow:0 8px 20px rgba(0,0,0,0.15);z-index:2500;display:none;"></div>
                            <button onclick="geocodeBarangayMapLocation()" style="position:absolute;top:10px;right:14px;padding:8px 14px;border:none;border-radius:10px;background:linear-gradient(135deg,#4250ff,#34d399);color:#042f4a;font-weight:700;letter-spacing:0.01em;box-shadow:0 8px 20px rgba(31,41,55,0.4);cursor:pointer;transition:transform .13s ease,box-shadow .13s ease;">Go</button>
                        </div>
                        <div id="barangayMapContainer" style="height:340px;"></div>
                        <div style="padding:10px 14px;">
                            <p id="barangayMapHint" style="margin:0 0 10px;font-size:0.85rem;color:var(--muted);">Tap on map to pick your location; you may drag marker and press Confirm.</p>
                            <button id="coMapConfirm" onclick="confirmBarangayMapPin()" style="width:100%;padding:12px 16px;border-radius:10px;border:none;background:linear-gradient(135deg,#38bdf8,#22d3ee);color:#0f172a;font-size:0.92rem;font-weight:700;cursor:pointer;letter-spacing:0.012em;box-shadow:0 10px 24px rgba(56,189,248,0.45);transition:transform .15s ease,box-shadow .15s ease;">
                                Confirm location and show products
                            </button>
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const inp = document.getElementById('barangayMapSearchInput');
                                    const typeahead = document.getElementById('barangayMapTypeahead');
                                    if (!inp) return;

                                    inp.setAttribute('autocomplete', 'off');

                                    inp.addEventListener('focus', function() {
                                        inp.setAttribute('autocomplete', 'off');
                                    });

                                    inp.addEventListener('input', function() {
                                        handleBarangayMapTypeahead(inp.value);
                                    });

                                    inp.addEventListener('keydown', function(e) {
                                        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                                            const items = typeahead ? Array.from(typeahead.querySelectorAll('.barangay-typeahead-item')) : [];
                                            if (!items.length) return;
                                            e.preventDefault();
                                            const active = typeahead.querySelector('.active');
                                            let index = active ? items.indexOf(active) : -1;
                                            if (e.key === 'ArrowDown') index = (index + 1) % items.length;
                                            if (e.key === 'ArrowUp') index = (index <= 0 ? items.length - 1 : index - 1);
                                            items.forEach(i => i.classList.remove('active'));
                                            items[index].classList.add('active');
                                            items[index].scrollIntoView({ block: 'nearest' });
                                        }
                                        if (e.key === 'Enter' && typeahead && typeahead.style.display === 'block') {
                                            const active = typeahead.querySelector('.barangay-typeahead-item.active');
                                            if (active) {
                                                active.click();
                                                e.preventDefault();
                                            } else {
                                                geocodeBarangayMapLocation();
                                            }
                                        }
                                    });

                                    inp.addEventListener('blur', function() {
                                        setTimeout(function() {
                                            inp.value = inp.value.trim();
                                            if (typeahead) {
                                                typeahead.style.display = 'none';
                                            }
                                        }, 180);
                                    });
                                });
                            </script>
                        </div>
                    </div>
                </div>
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
