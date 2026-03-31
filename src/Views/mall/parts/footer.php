<!-- Footer / scripts for marketplace -->
<script>
(function () {
    'use strict';

    /* ============================
     * PHP DATA INJECTION
     * ============================ */
    const PRODUCTS = <?= json_encode(array_map(function ($p) {
        $imgs_arr = [];
        $img = null;
        if (!empty($p['images'])) {
            $decoded = json_decode($p['images'], true);
            if (is_array($decoded)) {
                $imgs_arr = array_values(array_filter($decoded));
                $img = $imgs_arr[0] ?? null;
            }
        }
        if (!$img && !empty($p['image_path'])) {
            $img = $p['image_path'];
            if (empty($imgs_arr)) $imgs_arr = [$img];
        }
        return [
            'id'       => (int)($p['id'] ?? 0),
            'title'    => $p['title'] ?? '',
            'price'    => round((float)($p['price'] ?? $p['price_amount'] ?? 0) * 1.15, 2),
            'currency' => $p['currency'] ?? $p['price_currency'] ?? 'USD',
            'cat'      => isset($p['category_id']) ? (int)$p['category_id'] : null,
            'rating'   => (float)($p['rating'] ?? 0),
            'img'      => $img,
            'imgs'     => $imgs_arr,
            'desc'     => $p['short_description'] ?? '',
            'slug'     => $p['slug'] ?? '',
            'badge'        => $p['badge'] ?? null,
            'seller_slug'  => $p['seller_slug'] ?? null,
            'seller_name'  => $p['seller_name'] ?? null,
            'seller_id'    => (int)($p['seller_id'] ?? 0),
        ];
    }, $products ?? [])) ?>;

    const CSRF_TOKEN = <?= json_encode($csrf_token ?? '') ?>;
    const SELLER_ID  = <?= json_encode(isset($storefront['user_id']) ? (int)$storefront['user_id'] : 0) ?>; // 0 = marketplace
    const PLACEHOLDER = '/assets/images/placeholder_ceramic.svg';

    // Barangay GPS geofencing — persisted via PHP session + localStorage for offline persistence
    let currentBarangayId = <?= json_encode((int)($buyer_barangay_id ?? 0)) ?>;
    const _savedBarangay = localStorage.getItem('ginto_barangay_id');
    if (!currentBarangayId && _savedBarangay) currentBarangayId = parseInt(_savedBarangay) || 0;

    async function setCurrentBarangay(barangayId) {
        if (!barangayId || isNaN(barangayId)) return;
        currentBarangayId = Number(barangayId);
        localStorage.setItem('ginto_barangay_id', currentBarangayId);
        try {
            await fetch('/api/barangay/set', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF_TOKEN },
                body: 'barangay_id=' + encodeURIComponent(currentBarangayId)
            });
        } catch (e) {
            console.warn('Failed to set barangay session', e);
        }
    }

    async function autoDetectBarangayAndReload() {
        if (!navigator.geolocation) return;
        try {
            const pos = await new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 15000, maximumAge: 60000 });
            });
            const r = await fetch('/api/barangay/detect?lat=' + encodeURIComponent(pos.coords.latitude) + '&lng=' + encodeURIComponent(pos.coords.longitude), {
                credentials: 'same-origin', headers: { 'Accept': 'application/json' }
            });
            const d = await r.json();
            if (d && d.barangay && d.barangay.id) {
                // If detection chooses nearest and not containment, we may need user confirmation.
                if (d.source && d.source !== 'containment') {
                    console.warn('Barangay detected by nearest fallback:', d);
                }

                // Always update to latest detection even if we already had one.
                if (currentBarangayId !== d.barangay.id) {
                    console.info('Updating barangay from', currentBarangayId, 'to', d.barangay.id);
                }
                await setCurrentBarangay(d.barangay.id);

                // Update badge/UI to match detected barangay as well.
                if (typeof _setBarangayPillText === 'function') {
                    _setBarangayPillText('📍', d.barangay.name + ', ' + d.barangay.city);
                }
                if (typeof _updateCurrentLocationPanel === 'function') {
                    _updateCurrentLocationPanel(d.barangay, 'gps');
                }

                refreshSearchResultsFromServer();
            } else {
                console.warn('Barangay detect returned no match', d);
            }
        } catch (e) {
            console.warn('Auto detect barangay failed', e);
        }
    }

    autoDetectBarangayAndReload();

    function ensureMallShellElements() {
        if (!document.getElementById('sidebarBackdrop')) {
            const sidebarBackdrop = document.createElement('div');
            sidebarBackdrop.id = 'sidebarBackdrop';
            sidebarBackdrop.className = 'sidebar-backdrop';
            sidebarBackdrop.setAttribute('aria-hidden', 'true');
            document.body.appendChild(sidebarBackdrop);
        }

        if (!document.getElementById('sidebar')) {
            const sidebar = document.createElement('aside');
            sidebar.id = 'sidebar';
            sidebar.className = 'sidebar fallback-sidebar';
            sidebar.setAttribute('role', 'navigation');
            sidebar.setAttribute('aria-label', 'Mall navigation');
            sidebar.innerHTML = ''
                + '<div class="sidebar-close-row" id="sidebarCloseRow">'
                + '  <a class="sidebar-close-logo" href="/mall" aria-label="Open mall home">'
                + '    <img src="/assets/images/mall.png" alt="Mall">'
                + '    <span>ePower</span>'
                + '  </a>'
                + '  <button class="sidebar-close-btn" id="sidebarClose" aria-label="Close menu">'
                + '    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">'
                + '      <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>'
                + '    </svg>'
                + '  </button>'
                + '</div>'
                + '<div class="sidebar-inner">'
                + '  <div class="sidebar-section">'
                + '    <div class="sidebar-section-title">Mall</div>'
                + '    <a class="cat-item" href="/marketplace" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">'
                + '      <span style="width:26px;height:26px;border-radius:8px;background:rgba(99,102,241,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
                + '        <svg width="13" height="13" fill="none" stroke="#a5b4fc" stroke-width="2.2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>'
                + '      </span>Mall Home</a>'
                + '    <a class="cat-item" href="/mall/orders" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">'
                + '      <span style="width:26px;height:26px;border-radius:8px;background:rgba(99,102,241,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
                + '        <svg width="13" height="13" fill="none" stroke="#a5b4fc" stroke-width="2.2" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>'
                + '      </span>My Orders</a>'
                + '  </div>'
                + '  <div class="sidebar-section">'
                + '    <div class="sidebar-section-title">Earnings</div>'
                + '    <a class="cat-item" href="/wallet" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">'
                + '      <span style="width:26px;height:26px;border-radius:8px;background:rgba(34,197,94,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
                + '        <svg width="13" height="13" fill="none" stroke="#4ade80" stroke-width="2.2" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>'
                + '      </span>Wallet</a>'
                + '    <a class="cat-item" href="/wallet/sales" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">'
                + '      <span style="width:26px;height:26px;border-radius:8px;background:rgba(34,197,94,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
                + '        <svg width="13" height="13" fill="none" stroke="#4ade80" stroke-width="2.2" viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>'
                + '      </span>Sales</a>'
                + '    <a class="cat-item" href="/wallet/commissions" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">'
                + '      <span style="width:26px;height:26px;border-radius:8px;background:rgba(245,158,11,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
                + '        <svg width="13" height="13" fill="none" stroke="#fbbf24" stroke-width="2.2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'
                + '      </span>Commissions</a>'
                + '    <a class="cat-item" href="/wallet/earnings" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">'
                + '      <span style="width:26px;height:26px;border-radius:8px;background:rgba(99,102,241,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
                + '        <svg width="13" height="13" fill="none" stroke="#a5b4fc" stroke-width="2.2" viewBox="0 0 24 24"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>'
                + '      </span>Earnings</a>'
                + '    <a class="cat-item" href="/wallet/payouts" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">'
                + '      <span style="width:26px;height:26px;border-radius:8px;background:rgba(239,68,68,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
                + '        <svg width="13" height="13" fill="none" stroke="#f87171" stroke-width="2.2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'
                + '      </span>Pending Payouts</a>'
                + '  </div>'
                + '  <div class="sidebar-section">'
                + '    <div class="sidebar-section-title">Account</div>'
                + '    <a class="cat-item" href="/wallet/payout-accounts" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">'
                + '      <span style="width:26px;height:26px;border-radius:8px;background:rgba(59,130,246,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
                + '        <svg width="13" height="13" fill="none" stroke="#60a5fa" stroke-width="2.2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>'
                + '      </span>Bank Accounts</a>'
                + '    <a class="cat-item" href="/wallet/products" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">'
                + '      <span style="width:26px;height:26px;border-radius:8px;background:rgba(59,130,246,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
                + '        <svg width="13" height="13" fill="none" stroke="#60a5fa" stroke-width="2.2" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>'
                + '      </span>My Products</a>'
                + '    <a class="cat-item" href="/wallet/kyc" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;">'

                + '      <span style="width:26px;height:26px;border-radius:8px;background:rgba(59,130,246,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
                + '        <svg width="13" height="13" fill="none" stroke="#60a5fa" stroke-width="2.2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>'
                + '      </span>Seller KYC</a>'
                + '  </div>'
                + '</div>';
            document.body.appendChild(sidebar);
        }

        if (!document.getElementById('drawerOverlay')) {
            const drawerOverlay = document.createElement('div');
            drawerOverlay.id = 'drawerOverlay';
            drawerOverlay.className = 'drawer-overlay';
            drawerOverlay.setAttribute('aria-hidden', 'true');
            document.body.appendChild(drawerOverlay);
        }

        if (!document.getElementById('cartFab')) {
            const cartFab = document.createElement('button');
            cartFab.id = 'cartFab';
            cartFab.className = 'cart-fab';
            cartFab.setAttribute('aria-label', 'Open shopping cart');
            cartFab.onclick = function() { toggleCart(); };
            cartFab.innerHTML = '<img src="/assets/images/ginto.png" alt="Ginto AI" class="cart-fab-logo" aria-hidden="true">'
                + '<span class="cart-fab-badge" id="cartFabBadge" aria-live="polite"></span>';
            document.body.appendChild(cartFab);
        }

        if (!document.getElementById('cartDrawer')) {
            const cartDrawer = document.createElement('aside');
            cartDrawer.id = 'cartDrawer';
            cartDrawer.className = 'cart-drawer';
            cartDrawer.setAttribute('role', 'dialog');
            cartDrawer.setAttribute('aria-modal', 'true');
            cartDrawer.setAttribute('aria-label', 'Shopping cart');
            cartDrawer.setAttribute('aria-hidden', 'true');
            cartDrawer.innerHTML = ''
                + '<div class="drawer-header">'
                + '  <h2>Your Cart</h2>'
                + '  <button class="action-btn" onclick="toggleCart()" aria-label="Close cart">'
                + '    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">'
                + '      <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>'
                + '    </svg>'
                + '  </button>'
                + '</div>'
                + '<div class="drawer-body" id="cartItems">'
                + '  <p class="cart-empty-msg">Your cart is empty.</p>'
                + '</div>'
                + '<div class="drawer-footer">'
                + '  <div class="cart-total-row"><span>Total</span><span id="cartTotal">$0.00</span></div>'
                + '  <button class="btn btn-primary" style="width:100%" onclick="checkout()">Checkout</button>'
                + '</div>';
            document.body.appendChild(cartDrawer);
        }
    }

    ensureMallShellElements();

    /* ============================
     * STATE
     * ============================ */
    let state = {
        products: [...PRODUCTS],
        localPool: [...PRODUCTS],
        cart: [],
        filters: { cat: 'all', search: '', sort: 'default', sale: false, ship: false },
        page: 1,
        hasMore: true,
        loading: false,
        serverSearchQuery: '',
        searchUsingServerResults: false,
        requestSeq: 0,
        searchInputDebounce: null,
    };
    try { state.cart = JSON.parse(localStorage.getItem('epower_cart') || '[]'); } catch (e) { state.cart = []; }

    /* ============================
     * DOM REFS
     * ============================ */
    const grid        = document.getElementById('productGrid');
    const resultCount = document.getElementById('resultCount');
    const cartBadge   = document.getElementById('cartBadge');
    const cartItems   = document.getElementById('cartItems');
    const cartTotal   = document.getElementById('cartTotal');
    const cartDrawer  = document.getElementById('cartDrawer');
    const drawerOvl   = document.getElementById('drawerOverlay');
    const qvOverlay   = document.getElementById('qvOverlay');
    const sidebar     = document.getElementById('sidebar');
    const sidebarBd   = document.getElementById('sidebarBackdrop');
    const menuToggle  = document.getElementById('menuToggle');
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon   = document.getElementById('themeIcon');
    const toastCont   = document.getElementById('toastContainer');
    const searchInput = document.getElementById('searchInput');
    const sortBtn     = document.getElementById('sortBtn');
    const sortLabel   = document.getElementById('sortLabel');
    const sortDropdown= document.getElementById('sortDropdown');
    const sellBtn     = document.getElementById('sellBtn');
    const isFallbackSidebar = !!(sidebar && sidebar.classList.contains('fallback-sidebar'));
    const sidebarStateKey = 'mall_fallback_sidebar_open';
    if (isFallbackSidebar) {
        document.body.classList.add('has-fallback-sidebar');
    }

    function isDesktopViewport() {
        return window.matchMedia('(min-width: 768px)').matches;
    }

    function setSidebarState(isOpen, options) {
        if (!sidebar) return;
        const opts = Object.assign({ persist: true, focusToggle: false }, options || {});
        const isDesktop = isDesktopViewport();

        sidebar.classList.toggle('open', !!isOpen);
        if (menuToggle) menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

        if (isFallbackSidebar && isDesktop) {
            document.body.classList.toggle('fallback-sidebar-open', !!isOpen);
            if (sidebarBd) {
                sidebarBd.classList.remove('active');
                sidebarBd.setAttribute('aria-hidden', 'true');
            }
            document.body.style.overflow = '';
            if (opts.persist) {
                try { localStorage.setItem(sidebarStateKey, isOpen ? '1' : '0'); } catch (_) {}
            }
            return;
        }

        if (isOpen) {
            if (sidebarBd) {
                sidebarBd.classList.add('active');
                sidebarBd.removeAttribute('aria-hidden');
            }
            document.body.style.overflow = 'hidden';
            const closeBtn = document.getElementById('sidebarClose');
            if (closeBtn) setTimeout(() => closeBtn.focus({ preventScroll: true }), 50);
        } else {
            if (sidebarBd) {
                sidebarBd.classList.remove('active');
                sidebarBd.setAttribute('aria-hidden', 'true');
            }
            document.body.style.overflow = '';
            if (opts.focusToggle && menuToggle) menuToggle.focus({ preventScroll: true });
        }
    }

    /* ============================
     * SIDEBAR
     * ============================ */
    function openSidebar() {
        setSidebarState(true);
    }
    function closeSidebar() {
        setSidebarState(false, { focusToggle: true });
    }
    function toggleSidebar() {
        if (!sidebar) return;
        const isOpen = sidebar.classList.contains('open');
        setSidebarState(!isOpen);
    }
    if (menuToggle) menuToggle.addEventListener('click', toggleSidebar);
    const sidebarClose = document.getElementById('sidebarClose');
    if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
    if (sidebarBd) sidebarBd.addEventListener('click', closeSidebar);

    if (isFallbackSidebar) {
        const savedDesktopState = (() => {
            try { return localStorage.getItem(sidebarStateKey); } catch (_) { return null; }
        })();
        if (isDesktopViewport()) {
            setSidebarState(savedDesktopState === null ? true : savedDesktopState === '1', { persist: false });
        } else {
            setSidebarState(false, { persist: false });
        }

        let wasDesktop = isDesktopViewport();
        window.addEventListener('resize', function () {
            const isDesktop = isDesktopViewport();
            if (isDesktop === wasDesktop) return;
            wasDesktop = isDesktop;
            if (isDesktop) {
                const pref = (() => {
                    try { return localStorage.getItem(sidebarStateKey); } catch (_) { return null; }
                })();
                setSidebarState(pref === null ? true : pref === '1', { persist: false });
            } else {
                document.body.classList.remove('fallback-sidebar-open');
                setSidebarState(false, { persist: false });
            }
        });
    }

    /* ============================
     * CART DRAWER
     * ============================ */
    function toggleCart() {
        const isOpen = cartDrawer && cartDrawer.classList.contains('active');
        if (isOpen) closeCart(); else openCart();
    }
    function openCart() {
        if (cartDrawer) { cartDrawer.classList.add('active'); cartDrawer.setAttribute('aria-hidden', 'false'); }
        if (drawerOvl)  { drawerOvl.classList.add('active'); drawerOvl.removeAttribute('aria-hidden'); }
        document.body.style.overflow = 'hidden';
    }
    function closeCart() {
        if (cartDrawer) { cartDrawer.classList.remove('active'); cartDrawer.setAttribute('aria-hidden', 'true'); }
        if (drawerOvl)  { drawerOvl.classList.remove('active'); }
        document.body.style.overflow = '';
    }
    if (drawerOvl) drawerOvl.addEventListener('click', closeCart);

    /* ============================
     * PRODUCT RENDERING
     * ============================ */
    function renderProducts() {
        if (!grid) return;
        const serverSearchMode = !!(state.filters.search && state.filters.search.trim()) && state.searchUsingServerResults;
        let list = state.products.filter(p => {
            const matchCat = state.filters.cat === 'all' || String(p.cat) === String(state.filters.cat);
            const matchSearch = serverSearchMode
                ? true // In search mode, dataset already comes from server-side DB query.
                : (!state.filters.search || p.title.toLowerCase().includes(state.filters.search.toLowerCase()));
            return matchCat && matchSearch;
        });

        if (state.filters.sort === 'price_asc')  list.sort((a, b) => a.price - b.price);
        if (state.filters.sort === 'price_desc') list.sort((a, b) => b.price - a.price);
        if (state.filters.sort === 'rating')     list.sort((a, b) => b.rating - a.rating);

        if (resultCount) resultCount.textContent = list.length;
        grid.innerHTML = '';

        if (list.length === 0) {
            grid.innerHTML = '<div class="empty-state"><div class="empty-state-icon">🛍️</div><p>No products found matching your criteria.</p></div>';
            return;
        }

        const frag = document.createDocumentFragment();
        list.forEach(p => {
            const card = document.createElement('div');
            card.className = 'product-card';
            card.dataset.id = p.id;
            const imgs    = (p.imgs && p.imgs.length) ? p.imgs : (p.img ? [p.img] : []);
            const imgSrc  = imgs[0] || PLACEHOLDER;
            const stars   = '&#9733;'.repeat(Math.round(p.rating)) + '&#9734;'.repeat(5 - Math.round(p.rating));
            const multiBadge = imgs.length > 1 ? `<span class="card-multi-badge">&#128247; ${imgs.length}</span>` : '';
            const itemHref = p.slug ? `/mall/product/${esc(p.slug)}` : '#';
            card.innerHTML = `
                <div class="product-img-wrap" role="button" tabindex="0" aria-label="Quick view ${esc(p.title)}" onclick="openQV(${p.id})" onkeydown="if(event.key==='Enter'||event.key===' ')openQV(${p.id})">
                    <img class="product-img" src="${esc(imgSrc)}" alt="${esc(p.title)}" loading="lazy" onerror="this.src=PLACEHOLDER">
                    ${p.badge ? `<span class="product-badge">${esc(p.badge)}</span>` : ''}
                    ${multiBadge}
                </div>
                <div class="product-body">
                    <div class="product-title">${esc(p.title)}</div>
                    <div class="product-meta">
                        <span>${esc(formatPrice(p.price, p.currency))}</span>
                        <span class="star-rating" aria-label="${p.rating} stars">${stars}</span>
                    </div>
                    <div class="product-price">${esc(formatPrice(p.price, p.currency))}</div>
                    <div style="display:flex;gap:7px;margin-top:8px;">
                        <button class="product-add-btn" onclick="addToCart(${p.id})" aria-label="Add ${esc(p.title)} to cart" style="flex:1;">Add to Cart</button>
                        ${p.slug
                            ? `<a class="btn btn-secondary" href="${itemHref}" onclick="event.stopPropagation()" style="padding:7px 10px;font-size:0.72rem;flex-shrink:0;" aria-label="View ${esc(p.title)} product page">View</a>`
                            : `<button class="btn btn-secondary" type="button" onclick="event.stopPropagation();openQV(${p.id})" style="padding:7px 10px;font-size:0.72rem;flex-shrink:0;" aria-label="Quick view ${esc(p.title)}">View</button>`}
                    </div>
                    ${p.seller_slug ? `<a class="product-store-link" href="/mall/${esc(p.seller_slug)}" onclick="event.stopPropagation()">🏪 ${esc(p.seller_name || 'View Store')}</a>` : ''}
                </div>`;
            frag.appendChild(card);
        });
        grid.appendChild(frag);
        setupInfiniteScroll();
    }

    async function refreshSearchResultsFromServer() {
        const requestId = ++state.requestSeq;
        state.loading = true;
        showScrollSpinner(true);
        if (grid) {
            grid.innerHTML = '<div class="empty-state"><div class="empty-state-icon" style="animation:spin 1s linear infinite;">⏳</div><p>Searching…</p></div>';
        }
        if (resultCount) resultCount.textContent = '0';

        try {
            const res = await fetch(buildCleanApiUrl(1), { cache: 'no-store' });
            if (requestId !== state.requestSeq) return;
            if (!res.ok) {
                state.products = [];
                state.page = 1;
                state.hasMore = false;
                showEmptyState();
                return;
            }

            const json = await res.json();
            const incoming = Array.isArray(json.products) ? json.products.map(normaliseProduct) : [];
            if (incoming.length > 0) {
                state.products = incoming;
                state.searchUsingServerResults = true;
                const seen = new Set(state.localPool.map(p => p.id));
                incoming.forEach(p => { if (!seen.has(p.id)) state.localPool.push(p); });
                state.page = 1;
                state.hasMore = !!json.has_more;
            } else {
                // Fallback: keep search functional even when server returns no page-1 rows.
                state.products = [...state.localPool];
                state.searchUsingServerResults = false;
                state.page = 1;
                state.hasMore = false;
            }
            renderProducts();
        } catch (e) {
            if (requestId !== state.requestSeq) return;
            state.products = [...state.localPool];
            state.searchUsingServerResults = false;
            state.page = 1;
            state.hasMore = false;
            renderProducts();
        } finally {
            if (requestId === state.requestSeq) {
                state.loading = false;
                showScrollSpinner(false);
            }
        }
    }

    function esc(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function formatPrice(price, currency) {
        const sym = { USD:'$', PHP:'₱', NGN:'₦', EUR:'€' }[currency] || (currency + ' ');
        return sym + Number(price).toFixed(2);
    }

    /* ============================
     * CART LOGIC
     * ============================ */
    function addToCart(id) {
        const p = state.products.find(x => x.id === id);
        if (!p) return;
        const existing = state.cart.find(i => i.id === id);
        if (existing) {
            existing.qty++;
            showToast('Quantity updated: ' + p.title);
        } else {
            state.cart.push({ ...p, qty: 1 });
            showToast('Added: ' + p.title);
        }
        saveCart();
    }
    function updateCartQty(id, delta) {
        const item = state.cart.find(i => i.id === id);
        if (!item) return;
        item.qty += delta;
        if (item.qty <= 0) state.cart = state.cart.filter(i => i.id !== id);
        saveCart();
    }
    async function refreshCartFromServer() {
        if (!state.cart.length) return;
        try {
            const res = await fetch('/api/mall/cart/refresh', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN,
                },
                body: JSON.stringify({ cart: state.cart, csrf_token: CSRF_TOKEN }),
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (data && data.ok && Array.isArray(data.cart)) {
                const previousCart = state.cart.slice();
                state.cart = data.cart.map(function(item) {
                    const existing = previousCart.find(function(i) { return i.id === item.id; });
                    return {
                        id: item.id,
                        title: item.title,
                        qty: item.qty,
                        price: item.price,
                        currency: item.currency,
                        img: item.img || item.image || item.image_path || item.image_url || (existing ? existing.img : ''),
                        seller_id: item.seller_id || (existing ? existing.seller_id : null),
                    };
                });
                saveCart(false);
            }
        } catch (err) {
            console.warn('Unable to sync cart from server', err);
        }
    }

    function saveCart(shouldSync = true) {
        try { localStorage.setItem('epower_cart', JSON.stringify(state.cart)); } catch (e) {}
        updateCartUI();
        try {
            var total = state.cart.reduce(function(s, i){ return s + (i.qty || 1); }, 0);
            if (window.AndroidCart) { window.AndroidCart.onUpdate(total); }
            if (document.cookie.indexOf('PHPSESSID') !== -1) {
                fetch('/api/mall/cart/sync', {
                    method: 'POST', headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({count: total}), keepalive: true
                }).catch(function(){});
            }
        } catch(e) {}

        if (shouldSync) {
            refreshCartFromServer();
        }
    }
    function updateCartUI() {
        const total = state.cart.reduce((s, i) => s + i.qty, 0);
        if (cartBadge) {
            cartBadge.textContent = total > 0 ? (total > 99 ? '99+' : total) : '';
            cartBadge.style.display = total > 0 ? 'flex' : 'none';
        }
        const fabBadge = document.getElementById('cartFabBadge');
        const fab = document.getElementById('cartFab');
        if (fabBadge) {
            fabBadge.textContent = total > 0 ? (total > 99 ? '99+' : total) : '';
            fabBadge.style.display = total > 0 ? 'flex' : 'none';
        }
        if (fab && total > 0) {
            fab.classList.remove('pop');
            void fab.offsetWidth;
            fab.classList.add('pop');
        }
        const totalPrice = state.cart.reduce((s, i) => s + i.price * i.qty, 0);
        const currency   = state.cart[0]?.currency || 'USD';
        if (cartTotal) cartTotal.textContent = formatPrice(totalPrice, currency);
        if (!cartItems) return;
        if (state.cart.length === 0) {
            cartItems.innerHTML = '<p class="cart-empty-msg">Your cart is empty.</p>';
            return;
        }
        cartItems.innerHTML = state.cart.map(item => `
            <div class="cart-item">
                <img class="cart-img" src="${esc(item.img || PLACEHOLDER)}" alt="${esc(item.title)}" onerror="this.src=PLACEHOLDER">
                <div class="cart-info">
                    <div class="cart-name">${esc(item.title)}</div>
                    <div class="cart-price-line">${esc(formatPrice(item.price, item.currency))}</div>
                    <div class="cart-qty-row">
                        <button class="qty-btn" onclick="updateCartQty(${item.id},-1)" aria-label="Decrease quantity">−</button>
                        <span>${item.qty}</span>
                        <button class="qty-btn" onclick="updateCartQty(${item.id},1)" aria-label="Increase quantity">+</button>
                    </div>
                </div>
                <div style="font-weight:700;font-size:0.9rem;white-space:nowrap">${esc(formatPrice(item.price * item.qty, item.currency))}</div>
            </div>`).join('');
    }
    function checkout() {
        if (state.cart.length === 0) { showToast('Your cart is empty'); return; }
        // Pass the primary seller from the cart so the server can assign the correct upline
        const primarySellerId = (state.cart[0] && state.cart[0].seller_id) ? state.cart[0].seller_id : 0;
        const url = primarySellerId > 0 ? '/mall/checkout?ref_seller=' + primarySellerId : '/mall/checkout';
        window.location.href = url;
    }

    /* ============================
     * QUICK VIEW MODAL — carousel + zoom
     * ============================ */
    let _qvImgs = [];
    let _qvIdx  = 0;
    let _qvZoomFn = null;

    function openQV(id) {
        const p = state.products.find(x => x.id === id);
        if (!p || !qvOverlay) return;

        // Push history entry so Android back button closes the modal
        history.pushState({ qvProduct: id }, '', '?product=' + id);

        _qvImgs = (p.imgs && p.imgs.length) ? p.imgs : (p.img ? [p.img] : [PLACEHOLDER]);
        _qvIdx  = 0;

        const imgEl = document.getElementById('qvImg');
        imgEl.src = _qvImgs[0] || PLACEHOLDER;
        imgEl.alt = p.title;

        document.getElementById('qvTitle').textContent  = p.title;
        document.getElementById('qvRating').textContent = '\u2605'.repeat(Math.round(p.rating)) + '\u2606'.repeat(5 - Math.round(p.rating));
        document.getElementById('qvPrice').textContent  = formatPrice(p.price, p.currency);
        document.getElementById('qvDesc').textContent   = p.desc || 'No description available.';
        document.getElementById('qvAddBtn').onclick = function () { addToCart(id); closeQV(); };

        // Thumbnails & counter
        const thumbsEl  = document.getElementById('qvThumbs');
        const counterEl = document.getElementById('qvCounter');
        const prevBtn   = document.getElementById('qvPrev');
        const nextBtn   = document.getElementById('qvNext');
        thumbsEl.innerHTML = '';
        if (_qvImgs.length > 1) {
            _qvImgs.forEach(function(src, i) {
                const t = new Image();
                t.src = src || PLACEHOLDER;
                t.className = 'qv-thumb' + (i === 0 ? ' active' : '');
                t.onerror = function () { this.src = PLACEHOLDER; };
                t.onclick  = function () { qvGoTo(i); };
                t.setAttribute('aria-label', 'Image ' + (i + 1));
                thumbsEl.appendChild(t);
            });
            counterEl.textContent = '1 / ' + _qvImgs.length;
            counterEl.style.display = 'block';
            thumbsEl.style.display  = 'flex';
            prevBtn.style.display = nextBtn.style.display = '';
        } else {
            counterEl.style.display = 'none';
            thumbsEl.style.display  = 'none';
            prevBtn.style.display = nextBtn.style.display = 'none';
        }

        setupQVZoom(imgEl);
        qvOverlay.classList.add('active');
        qvOverlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function qvGoTo(idx) {
        if (!_qvImgs.length) return;
        _qvIdx  = idx;
        const imgEl = document.getElementById('qvImg');
        imgEl.src = _qvImgs[idx] || PLACEHOLDER;
        document.querySelectorAll('.qv-thumb').forEach(function(t, i) {
            t.classList.toggle('active', i === idx);
        });
        const counterEl = document.getElementById('qvCounter');
        if (counterEl && counterEl.style.display !== 'none') {
            counterEl.textContent = (idx + 1) + ' / ' + _qvImgs.length;
        }
        setupQVZoom(imgEl);
    }

    function qvNav(dir) {
        qvGoTo((_qvIdx + dir + _qvImgs.length) % _qvImgs.length);
    }

    function setupQVZoom(imgEl) {
        const wrap = document.getElementById('qvMainWrap');
        const lens = document.getElementById('qvZoomLens');
        if (!wrap || !lens) return;
        if (_qvZoomFn) wrap.removeEventListener('mousemove', _qvZoomFn);
        const ZF = 3, R = 70; // 3× zoom, lens radius 70 px
        _qvZoomFn = function (e) {
            const rect = wrap.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            lens.style.left = x + 'px';
            lens.style.top  = y + 'px';
            const src = imgEl.src || '';
            lens.style.backgroundImage    = "url('" + src.replace(/'/g, "\\'") + "')";
            lens.style.backgroundSize     = (rect.width * ZF) + 'px ' + (rect.height * ZF) + 'px';
            lens.style.backgroundPosition = (-(x * ZF - R)) + 'px ' + (-(y * ZF - R)) + 'px';
        };
        wrap.addEventListener('mousemove', _qvZoomFn);
    }

    function closeQV(opts) {
        if (!qvOverlay) return;
        qvOverlay.classList.remove('active');
        qvOverlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        const wrap = document.getElementById('qvMainWrap');
        if (wrap && _qvZoomFn) { wrap.removeEventListener('mousemove', _qvZoomFn); _qvZoomFn = null; }
        // Clean URL unless already handled by popstate
        if (!opts || !opts._fromPopstate) {
            if (location.search.includes('product=')) {
                history.back();
            }
        }
    }
    if (qvOverlay) qvOverlay.addEventListener('click', function (e) { if (e.target === qvOverlay) closeQV(); });

    // Handle Android/browser back button
    window.addEventListener('popstate', function (e) {
        if (qvOverlay && qvOverlay.classList.contains('active')) {
            closeQV({ _fromPopstate: true });
        }
    });

    /* ============================
     * UPLOAD MODAL
     * ============================ */
    function openUploadModal() {
        const el = document.getElementById('uploadOverlay');
        if (!el) return;
        el.classList.add('active');
        el.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    function closeUploadModal() {
        const el = document.getElementById('uploadOverlay');
        if (!el) return;
        el.classList.remove('active');
        el.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        const form = document.getElementById('uploadForm');
        if (form) form.reset();
        const prev = document.getElementById('uploadPreview');
        if (prev) prev.innerHTML = '';
        const err = document.getElementById('uploadError');
        if (err) { err.style.display = 'none'; err.textContent = ''; }
    }
    if (sellBtn) {
        sellBtn.addEventListener('click', function (e) {
            const uploadOverlayEl = document.getElementById('uploadOverlay');
            if (uploadOverlayEl) {
                e.preventDefault();
                openUploadModal();
            } else {
                e.preventDefault();
                window.location.href = '/marketplace?sell=1';
            }
        });
    }
    const uploadOverlay = document.getElementById('uploadOverlay');
    if (uploadOverlay) uploadOverlay.addEventListener('click', function (e) { if (e.target === uploadOverlay) closeUploadModal(); });

    // Image preview
    const imgInput = document.getElementById('up-image');
    if (imgInput) {
        imgInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            const prev = document.getElementById('uploadPreview');
            if (!prev) return;
            if (!file) { prev.innerHTML = ''; return; }
            const reader = new FileReader();
            reader.onload = function (evt) {
                prev.innerHTML = `<img src="${evt.target.result}" style="height:56px;border-radius:6px;object-fit:cover;margin-top:4px;" alt="Preview">`;
            };
            reader.readAsDataURL(file);
        });
    }

    // Upload form submit
    const uploadForm = document.getElementById('uploadForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const errEl  = document.getElementById('uploadError');
            const submitBtn = this.querySelector('#uploadSubmitBtn');
            if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
            if (submitBtn) submitBtn.disabled = true;
            try {
                const fd  = new FormData(this);
                const res = await fetch('/marketplace/sellers/upload', { method: 'POST', body: fd });
                const json = await res.json();
                if (!json || !json.success) {
                    const msg = json?.message || 'Upload failed. Please try again.';
                    if (errEl) { errEl.textContent = msg; errEl.style.display = 'block'; }
                    return;
                }
                // Refresh product list from server
                try {
                    const r = await fetch('/api/mall/products');
                    const d = await r.json();
                    if (d?.success && Array.isArray(d.products)) {
                        state.products = [...d.products];
                        renderProducts();
                    } else if (json.product) {
                        state.products.unshift(json.product);
                        renderProducts();
                    }
                } catch (_) {
                    if (json.product) { state.products.unshift(json.product); renderProducts(); }
                }
                showToast('Product listed successfully!');
                closeUploadModal();
            } catch (err) {
                console.error('Upload error', err);
                if (errEl) { errEl.textContent = 'Network error. Please try again.'; errEl.style.display = 'block'; }
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });
    }

    /* ============================
     * TOAST
     * ============================ */
    function showToast(msg) {
        if (!toastCont) return;
        const t = document.createElement('div');
        t.className = 'toast';
        t.setAttribute('role', 'status');
        t.textContent = msg;
        toastCont.appendChild(t);
        setTimeout(() => t.remove(), 3400);
    }

    /* ============================
     * THEME
     * ============================ */
    function applyTheme(theme) {
        if (theme === 'light') {
            document.body.classList.add('light');
            if (themeIcon) themeIcon.textContent = '🌙';
        } else {
            document.body.classList.remove('light');
            if (themeIcon) themeIcon.textContent = '☀️';
        }
        try { localStorage.setItem('epower_theme', theme); } catch (e) {}
        document.dispatchEvent(new CustomEvent('mall:theme-changed', { detail: { theme: theme } }));
    }
    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            applyTheme(document.body.classList.contains('light') ? 'dark' : 'light');
        });
    }

    /* ============================
     * SORT DROPDOWN
     * ============================ */
    if (sortBtn && sortDropdown) {
        sortBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = sortDropdown.classList.contains('open');
            sortDropdown.classList.toggle('open', !isOpen);
            sortBtn.setAttribute('aria-expanded', String(!isOpen));
        });
        document.addEventListener('click', function (e) {
            if (!sortBtn.contains(e.target) && !sortDropdown.contains(e.target)) {
                sortDropdown.classList.remove('open');
                sortBtn.setAttribute('aria-expanded', 'false');
            }
        });
        sortDropdown.querySelectorAll('li[data-sort]').forEach(function (li) {
            li.addEventListener('click', function () {
                sortDropdown.querySelectorAll('li').forEach(i => i.setAttribute('aria-selected', 'false'));
                li.setAttribute('aria-selected', 'true');
                state.filters.sort = li.dataset.sort;
                if (sortLabel) sortLabel.textContent = li.dataset.sort === 'default' ? 'Sort' : li.textContent.trim();
                sortDropdown.classList.remove('open');
                sortBtn.setAttribute('aria-expanded', 'false');
                renderProducts();
            });
        });
        sortDropdown.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { sortDropdown.classList.remove('open'); sortBtn.setAttribute('aria-expanded', 'false'); sortBtn.focus(); }
        });
    }

    /* ============================
     * MALL NOTIFICATIONS
     * ============================ */
    const mallNotifyToggle = document.getElementById('mallNotifyToggle');
    const mallNotifyPanel = document.getElementById('mallNotifyPanel');
    const mallNotifyList = document.getElementById('mallNotifyList');
    const mallNotifyBadge = document.getElementById('mallNotifyBadge');

    function notifIcon(type) {
        const t = String(type || '');
        if (t.indexOf('order') !== -1) return '📦';
        if (t.indexOf('payment') !== -1) return '💳';
        if (t.indexOf('delivery') !== -1) return '🚚';
        if (t.indexOf('wallet') !== -1) return '💰';
        if (t.indexOf('product_listed') !== -1) return '🏷️';
        return '🔔';
    }

    function renderNotificationPanel(items) {
        if (!mallNotifyList) return;
        if (!Array.isArray(items) || items.length === 0) {
            mallNotifyList.innerHTML = '<div style="padding:12px;border-radius:12px;background:var(--surface2);border:1px solid var(--border);font-size:0.8rem;color:var(--muted);">No mall notifications yet.</div>';
            return;
        }
        mallNotifyList.innerHTML = items.slice(0, 8).map(function (n) {
            const msg = esc(String(n.message || ''));
            const dt  = esc(String(n.created_at || ''));
            const icon = notifIcon(n.type);
            const isUnread = !!(n.is_unread || (!n.is_read && n.is_read !== 1));
            const livePill = isUnread
                ? '<span style="display:inline-flex;align-items:center;justify-content:center;min-height:19px;padding:0 7px;border-radius:999px;font-size:0.62rem;font-weight:800;background:rgba(37,99,235,0.28);border:1px solid rgba(96,165,250,0.42);color:#bfdbfe;">LIVE</span>'
                : '<span style="display:inline-flex;align-items:center;justify-content:center;min-height:19px;padding:0 7px;border-radius:999px;font-size:0.62rem;font-weight:800;background:rgba(71,85,105,0.25);border:1px solid rgba(100,116,139,0.34);color:#cbd5e1;">READ</span>';
            const link = n.link ? ('<a href="' + esc(String(n.link)) + '" style="display:inline-block;margin-top:6px;font-size:0.72rem;color:var(--accent);text-decoration:none;">View details →</a>') : '';
            const buyerBtn = n.buyer_link
                ? ('<a href="' + esc(String(n.buyer_link)) + '" style="display:inline-flex;align-items:center;justify-content:center;min-height:26px;padding:0 8px;border-radius:8px;border:1px solid var(--border);background:var(--surface2);font-size:0.68rem;font-weight:700;color:var(--text);text-decoration:none;">Buyer</a>')
                : '';
            const productBtn = n.product_link
                ? ('<a href="' + esc(String(n.product_link)) + '" style="display:inline-flex;align-items:center;justify-content:center;min-height:26px;padding:0 8px;border-radius:8px;border:1px solid var(--border);background:var(--surface2);font-size:0.68rem;font-weight:700;color:var(--text);text-decoration:none;">Product</a>')
                : '';
            const actionRow = (buyerBtn || productBtn)
                ? ('<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:7px;">' + buyerBtn + productBtn + '</div>')
                : '';
            return '<div style="padding:10px 12px;border-radius:12px;background:var(--surface2);border:1px solid var(--border);">'
                + '<div style="font-size:0.82rem;font-weight:600;line-height:1.4;">' + icon + ' ' + msg + '</div>'
                + '<div style="font-size:0.7rem;color:var(--muted);margin-top:4px;display:flex;align-items:center;gap:6px;">' + dt + livePill + '</div>'
                + link
                + actionRow
                + '</div>';
        }).join('');
    }

    function updateNotificationBadge(count) {
        if (!mallNotifyBadge) return;
        if (count > 0) {
            mallNotifyBadge.textContent = count > 99 ? '99+' : String(count);
            mallNotifyBadge.style.display = 'flex';
        } else {
            mallNotifyBadge.textContent = '';
            mallNotifyBadge.style.display = 'none';
        }
    }

    // Bell sound for new notifications
    let _lastNotifCount = parseInt(mallNotifyBadge ? mallNotifyBadge.textContent : '0', 10) || 0;
    const _bellAudio = (function() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            return function playBell() {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.type = 'sine';
                osc.frequency.setValueAtTime(830, ctx.currentTime);
                osc.frequency.setValueAtTime(660, ctx.currentTime + 0.15);
                gain.gain.setValueAtTime(0.3, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.4);
            };
        } catch (_) { return function(){}; }
    })();

    async function refreshNotifications() {
        try {
            const res = await fetch('/api/mall/notifications?page=1', {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store'
            });
            if (!res.ok) return;
            const json = await res.json();
            if (!json || json.success !== true) return;
            const newCount = parseInt(json.count || 0, 10) || 0;
            if (newCount > _lastNotifCount && _lastNotifCount >= 0) {
                try { _bellAudio(); } catch(_) {}
            }
            _lastNotifCount = newCount;
            updateNotificationBadge(newCount);
            renderNotificationPanel(Array.isArray(json.notifications) ? json.notifications : []);
        } catch (_) { }
    }

    if (mallNotifyToggle && mallNotifyPanel) {
        mallNotifyToggle.addEventListener('click', async function (e) {
            e.stopPropagation();
            const isOpen = mallNotifyPanel.style.display === 'block';
            mallNotifyPanel.style.display = isOpen ? 'none' : 'block';
            if (!isOpen) {
                try {
                    await refreshNotifications();
                    await fetch('/api/mall/notifications/mark-read', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': CSRF_TOKEN,
                        },
                        body: JSON.stringify({ csrf_token: CSRF_TOKEN }),
                    });
                    updateNotificationBadge(0);
                } catch (_) {}
            }
        });
        document.addEventListener('click', function (e) {
            if (!mallNotifyPanel.contains(e.target) && !mallNotifyToggle.contains(e.target)) {
                mallNotifyPanel.style.display = 'none';
            }
        });

        // Keep notification panel and badge live for web clients.
        refreshNotifications();
        setInterval(refreshNotifications, 15000);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) refreshNotifications();
        });
    }

    /* ============================
     * CATEGORY FILTER
     * ============================ */
    const catList = document.getElementById('catList');
    if (catList) {
        catList.addEventListener('click', function (e) {
            const item = e.target.closest('.cat-item');
            if (!item) return;
            catList.querySelectorAll('.cat-item').forEach(i => { i.classList.remove('active'); i.setAttribute('aria-pressed', 'false'); });
            item.classList.add('active');
            item.setAttribute('aria-pressed', 'true');
            state.filters.cat = item.dataset.cat || 'all';
            renderProducts();
            // Close sidebar on mobile after selecting
            if (window.innerWidth < 768) closeSidebar();
        });
        catList.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                const item = e.target.closest('.cat-item');
                if (item) { e.preventDefault(); item.click(); }
            }
        });
    }

    /* ============================
     * STATUS FILTERS
     * ============================ */
    const filterSale = document.getElementById('filterSale');
    const filterShip = document.getElementById('filterShip');
    // Note: current products don't have sale/ship flags — wiring for future use
    // (filters apply when products carry the flags)

    /* ============================
     * SEARCH OVERLAY
     * ============================ */
    var searchTrigger = document.getElementById('searchTrigger');
    var searchOverlay = document.getElementById('searchOverlay');
    var searchClose   = document.getElementById('searchClose');

    function openSearch() {
        if (!searchOverlay) return;
        searchOverlay.classList.add('open');
        if (searchTrigger) searchTrigger.setAttribute('aria-expanded', 'true');
        setTimeout(function () { if (searchInput) searchInput.focus(); }, 60);
    }
    function closeSearch() {
        if (!searchOverlay) return;
        searchOverlay.classList.remove('open');
        if (searchTrigger) searchTrigger.setAttribute('aria-expanded', 'false');
    }

    if (searchTrigger) searchTrigger.addEventListener('click', openSearch);
    if (searchClose)   searchClose.addEventListener('click', closeSearch);

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            state.filters.search = this.value;
            if (state.searchInputDebounce) {
                clearTimeout(state.searchInputDebounce);
            }
            state.searchInputDebounce = setTimeout(function () {
                refreshSearchResultsFromServer();
            }, 220);
        });
        // Prevent form submit reload; trigger search immediately on Enter
        var searchForm = document.getElementById('searchForm');
        if (searchForm) searchForm.addEventListener('submit', function (e) {
            e.preventDefault();
            state.filters.search = searchInput.value;
            if (state.searchInputDebounce) clearTimeout(state.searchInputDebounce);
            searchInput.blur();
            closeSearch();
            refreshSearchResultsFromServer();
        });
    }

    /* ============================
     * KEYBOARD / ESCAPE
     * ============================ */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft'  && qvOverlay && qvOverlay.classList.contains('active') && _qvImgs.length > 1) { qvNav(-1); return; }
        if (e.key === 'ArrowRight' && qvOverlay && qvOverlay.classList.contains('active') && _qvImgs.length > 1) { qvNav(1);  return; }
        if (e.key === 'Escape') {
            if (searchOverlay && searchOverlay.classList.contains('open'))     { closeSearch(); return; }
            if (qvOverlay && qvOverlay.classList.contains('active'))           { closeQV(); return; }
            const upOvl = document.getElementById('uploadOverlay');
            if (upOvl && upOvl.classList.contains('active'))                   { closeUploadModal(); return; }
            if (cartDrawer && cartDrawer.classList.contains('active'))          { closeCart(); return; }
            if (sidebar && sidebar.classList.contains('open'))                  { closeSidebar(); }
        }
    });

    /* ============================
     * SERVER PRODUCT LOAD
     * ============================ */
    function normaliseProduct(p) {
        const raw  = Array.isArray(p.images) ? p.images : (Array.isArray(p.imgs) ? p.imgs : []);
        const imgs = raw.filter(Boolean);
        const img  = p.img || imgs[0] || null;
        return {
            id:          p.id,
            title:       p.title || '',
            price:       parseFloat(p.price) || 0,
            currency:    p.currency || 'PHP',
            cat:         p.cat != null ? p.cat : (p.category_id != null ? parseInt(p.category_id, 10) : null),
            rating:      parseFloat(p.rating) || 0,
            img:         img,
            imgs:        imgs.length ? imgs : (img ? [img] : []),
            desc:        p.desc || p.short_description || '',
            slug:        p.slug || '',
            badge:       p.badge || null,
            seller_slug: p.seller_slug || null,
            seller_name: p.seller_name || null,
            seller_id:   parseInt(p.seller_id, 10) || 0,
        };
    }

    /* ============================
     * INFINITE SCROLL + SERVER SEARCH
     * ============================ */
    function buildApiUrl(extraPage) {
        const p    = extraPage || state.page;
        const base = '/api/mall/products?page=' + p + '&limit=24';
        const parts = [base];
        if (SELLER_ID > 0)           parts.push('seller_id=' + SELLER_ID);
        if (state.filters.cat !== 'all' && state.filters.cat)
                                     parts.push('cat=' + encodeURIComponent(state.filters.cat));
        if (state.filters.search)    parts.push('search=' + encodeURIComponent(state.filters.search));
        if (state.filters.sort !== 'default')
                                     parts.push('sort=' + encodeURIComponent(state.filters.sort));
        return parts.join('&').replace('/api/mall/products?page=', '/api/mall/products?page=').replace('&', '&').replace(/&([a-z])/g, '&$1');
        // rebuild cleanly
    }

    function buildCleanApiUrl(page) {
        const params = new URLSearchParams({ page, limit: 24 });
        if (SELLER_ID > 0)                              params.set('seller_id', SELLER_ID);
        if (currentBarangayId > 0)                      params.set('barangay_id', currentBarangayId);
        if (state.filters.cat && state.filters.cat !== 'all') params.set('cat', state.filters.cat);
        if (state.filters.search)                       params.set('search', state.filters.search);
        if (state.filters.sort && state.filters.sort !== 'default') params.set('sort', state.filters.sort);
        return '/api/mall/products?' + params.toString();
    }

    async function loadMoreProducts() {
        if (state.loading || !state.hasMore) return;
        state.loading = true;
        showScrollSpinner(true);
        try {
            const nextPage = state.page + 1;
            const res  = await fetch(buildCleanApiUrl(nextPage));
            if (!res.ok) { state.hasMore = false; return; }
            const json = await res.json();
            const incoming = Array.isArray(json.products) ? json.products.map(normaliseProduct) : [];
            if (incoming.length === 0) { state.hasMore = false; return; }
            const existingIds = new Set(state.products.map(p => p.id));
            const newProds    = incoming.filter(p => !existingIds.has(p.id));
            state.products    = state.products.concat(newProds);
            const localSeen = new Set(state.localPool.map(p => p.id));
            newProds.forEach(p => { if (!localSeen.has(p.id)) state.localPool.push(p); });
            state.page        = nextPage;
            state.hasMore     = !!json.has_more;
            appendProducts(newProds);
            if (resultCount) resultCount.textContent = state.products.filter(applyFilters).length;
        } catch (e) {
            console.warn('loadMoreProducts error', e);
        } finally {
            state.loading = false;
            showScrollSpinner(false);
        }
    }

    async function serverSearch() {
        // Called when local filter returns 0 products — fetch from server with current filters
        const q    = state.filters.search;
        const cat  = state.filters.cat;
        const key  = q + '::' + cat;
        if (key === state.serverSearchQuery) return; // already fetched
        state.serverSearchQuery = key;
        if (grid) grid.innerHTML = '<div class="empty-state"><div class="empty-state-icon" style="animation:spin 1s linear infinite;">⏳</div><p>Searching…</p></div>';
        try {
            const res  = await fetch(buildCleanApiUrl(1));
            if (!res.ok) { showEmptyState(); return; }
            const json = await res.json();
            const incoming = Array.isArray(json.products) ? json.products.map(normaliseProduct) : [];
            if (incoming.length === 0) { showEmptyState(); return; }
            const existing = new Set(state.products.map(p => p.id));
            const newProds = incoming.filter(p => !existing.has(p.id));
            state.products = state.products.concat(newProds);
            state.hasMore  = !!json.has_more;
            renderProducts(); // re-run filter with updated local pool
        } catch(e) {
            showEmptyState();
        }
    }

    function showEmptyState() {
        if (grid) grid.innerHTML = '<div class="empty-state"><div class="empty-state-icon">🛍️</div><p>No products found matching your criteria.</p></div>';
        if (resultCount) resultCount.textContent = '0';
    }

    function applyFilters(p) {
        const matchCat    = state.filters.cat === 'all' || String(p.cat) === String(state.filters.cat);
        const matchSearch = !state.filters.search || p.title.toLowerCase().includes(state.filters.search.toLowerCase());
        return matchCat && matchSearch;
    }

    function appendProducts(list) {
        if (!grid || !list.length) return;
        const frag = document.createDocumentFragment();
        list.forEach(p => {
            const card = document.createElement('div');
            card.className = 'product-card';
            card.dataset.id = p.id;
            const imgs = (p.imgs && p.imgs.length) ? p.imgs : (p.img ? [p.img] : []);
            const imgSrc = imgs[0] || PLACEHOLDER;
            const stars  = '&#9733;'.repeat(Math.round(p.rating)) + '&#9734;'.repeat(5 - Math.round(p.rating));
            const multiBadge = imgs.length > 1 ? `<span class="card-multi-badge">&#128247; ${imgs.length}</span>` : '';
            const itemHref = p.slug ? `/mall/product/${esc(p.slug)}` : '#';
            card.innerHTML = `
                <div class="product-img-wrap" role="button" tabindex="0" aria-label="Quick view ${esc(p.title)}" onclick="openQV(${p.id})" onkeydown="if(event.key==='Enter'||event.key===' ')openQV(${p.id})">
                    <img class="product-img" src="${esc(imgSrc)}" alt="${esc(p.title)}" loading="lazy" onerror="this.src=PLACEHOLDER">
                    ${p.badge ? `<span class="product-badge">${esc(p.badge)}</span>` : ''}
                    ${multiBadge}
                </div>
                <div class="product-body">
                    <div class="product-title">${esc(p.title)}</div>
                    <div class="product-meta">
                        <span>${esc(formatPrice(p.price, p.currency))}</span>
                        <span class="star-rating" aria-label="${p.rating} stars">${stars}</span>
                    </div>
                    <div class="product-price">${esc(formatPrice(p.price, p.currency))}</div>
                    <div style="display:flex;gap:7px;margin-top:8px;">
                        <button class="product-add-btn" onclick="addToCart(${p.id})" aria-label="Add ${esc(p.title)} to cart" style="flex:1;">Add to Cart</button>
                        ${p.slug
                            ? `<a class="btn btn-secondary" href="${itemHref}" onclick="event.stopPropagation()" style="padding:7px 10px;font-size:0.72rem;flex-shrink:0;">View</a>`
                            : `<button class="btn btn-secondary" type="button" onclick="event.stopPropagation();openQV(${p.id})" style="padding:7px 10px;font-size:0.72rem;flex-shrink:0;">View</button>`}
                    </div>
                    ${p.seller_slug ? `<a class="product-store-link" href="/mall/${esc(p.seller_slug)}" onclick="event.stopPropagation()">🏪 ${esc(p.seller_name || 'View Store')}</a>` : ''}
                </div>`;
            frag.appendChild(card);
        });
        // Insert before the sentinel
        const sentinel = document.getElementById('productSentinel');
        if (sentinel) grid.insertBefore(frag, sentinel);
        else grid.appendChild(frag);
    }

    function showScrollSpinner(show) {
        let el = document.getElementById('scrollSpinner');
        if (!el && show) {
            el = document.createElement('div');
            el.id = 'scrollSpinner';
            el.style.cssText = 'width:100%;text-align:center;padding:20px;color:var(--muted);font-size:0.85rem;grid-column:1/-1;';
            el.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 1s linear infinite;vertical-align:middle;"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg> Loading…';
            if (grid) grid.appendChild(el);
        } else if (el && !show) {
            el.remove();
        }
    }

    function setupInfiniteScroll() {
        if (!grid || !window.IntersectionObserver) return;
        // Create sentinel if not already present
        let sentinel = document.getElementById('productSentinel');
        if (!sentinel) {
            sentinel = document.createElement('div');
            sentinel.id = 'productSentinel';
            sentinel.style.cssText = 'height:1px;width:100%;grid-column:1/-1;';
            grid.appendChild(sentinel);
        }
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) loadMoreProducts();
            });
        }, { rootMargin: '200px' });
        observer.observe(sentinel);
    }

    async function loadServerProducts() {
        try {
            const res = await fetch(buildCleanApiUrl(1));
            if (!res.ok) return;
            const json = await res.json();
            if (Array.isArray(json.products)) {
                const normalised = json.products.map(normaliseProduct);
                const ids = new Set(normalised.map(p => p.id));
                state.products  = [...normalised, ...state.products.filter(p => !ids.has(p.id))];
                state.localPool = [...state.products];
                state.hasMore   = !!json.has_more;
                state.page      = 1;
            }
        } catch (e) {
            console.warn('Failed to fetch server products', e);
        }
    }

    /* ============================
     * INIT
     * ============================ */
    async function init() {
        // Hydrate filters from URL so deep links like ?search=... work from app/web.
        const urlParams = new URLSearchParams(window.location.search);
        const urlSearch = (urlParams.get('search') || urlParams.get('q') || '').trim();
        const urlCat = (urlParams.get('cat') || '').trim();
        if (urlSearch) {
            state.filters.search = urlSearch;
            if (searchInput) searchInput.value = urlSearch;
        }
        if (urlCat) {
            state.filters.cat = urlCat;
            if (catList) {
                const target = Array.from(catList.querySelectorAll('.cat-item')).find(function (el) {
                    return String(el.getAttribute('data-cat') || '') === urlCat;
                });
                if (target) {
                    catList.querySelectorAll('.cat-item').forEach(i => { i.classList.remove('active'); i.setAttribute('aria-pressed', 'false'); });
                    target.classList.add('active');
                    target.setAttribute('aria-pressed', 'true');
                }
            }
        }

        await loadServerProducts();
        renderProducts();
        setupInfiniteScroll();
        updateCartUI();
        applyTheme(localStorage.getItem('epower_theme') || 'dark');

        const hasSellQuery = new URLSearchParams(window.location.search).get('sell') === '1';
        const uploadOverlayEl = document.getElementById('uploadOverlay');
        if (hasSellQuery && uploadOverlayEl) {
            openUploadModal();
            const url = new URL(window.location.href);
            url.searchParams.delete('sell');
            window.history.replaceState({}, '', url.pathname + (url.search ? url.search : '') + url.hash);
        }
    }

    init();

    // Expose globals needed by inline onclick handlers
    window.openQV           = openQV;
    window.closeQV          = closeQV;
    window.qvNav            = qvNav;
    window.addToCart        = addToCart;
    window.updateCartQty    = updateCartQty;
    window.toggleCart       = toggleCart;
    window.checkout         = checkout;
    window.openUploadModal  = openUploadModal;
    window.closeUploadModal = closeUploadModal;

    // ── Barangay GPS selector ─────────────────────────────────────────────
    let _barangayDropdownOpen = false;

    function toggleBarangayDropdown() {
        const dd = document.getElementById('barangayDropdown');
        if (!dd) return;
        _barangayDropdownOpen = !_barangayDropdownOpen;
        dd.style.display = _barangayDropdownOpen ? 'block' : 'none';
        if (_barangayDropdownOpen) {
            const inp = document.getElementById('barangaySearchInput');
            if (inp) { inp.focus(); _doSearchBarangay(''); }
        }
    }

    document.addEventListener('click', function(e) {
        const wrap = document.getElementById('barangayPillWrap');
        if (wrap && !wrap.contains(e.target)) {
            const dd = document.getElementById('barangayDropdown');
            if (dd) dd.style.display = 'none';
            _barangayDropdownOpen = false;
        }
    });

    let _bzTimer = null;
    function searchBarangay(q) {
        clearTimeout(_bzTimer);
        const res = document.getElementById('barangayResults');
        if (!res) return;
        if (!q || q.trim().length < 1) {
            _doSearchBarangay('');
            return;
        }
        _bzTimer = setTimeout(function() { _doSearchBarangay(q); }, 250);
    }

    function _doSearchBarangay(q) {
        const res = document.getElementById('barangayResults');
        if (!res) return;
        const url = '/api/barangay/list?limit=20' + (q.trim() ? '&q=' + encodeURIComponent(q.trim()) : '');
        fetch(url)
            .then(r => r.json())
            .then(function(d) {
                if (!d.barangays || !d.barangays.length) {
                    res.innerHTML = '<div style="padding:8px 14px;color:var(--muted);font-size:0.83rem;">' +
                        (q.trim() ? 'No results' : 'No barangays found') + '</div>';
                    return;
                }
                res.innerHTML = d.barangays.map(function(b) {
                    const active = (b.id === currentBarangayId) ? ' style="background:var(--primary-bg,#f0f7ff);"' : '';
                    return '<div onclick="selectBarangay(' + b.id + ')"' + active +
                        ' style="padding:9px 14px;cursor:pointer;border-bottom:1px solid var(--border,#eee);font-size:0.87rem;">' +
                        '<strong>' + b.name + '</strong>, ' + b.city +
                        ' <span style="color:var(--muted,#888);font-size:0.78rem;">' + b.province + '</span>' +
                        '</div>';
                }).join('');
            })
            .catch(function() {
                if (res) res.innerHTML = '<div style="padding:8px 14px;color:var(--muted);font-size:0.83rem;">Error loading barangays</div>';
            });
    }

    function selectBarangay(id) {
        // Handle geo_places fallback entries from search (id = 'geo_<geoname_id>').
        if (typeof id === 'string' && id.startsWith('geo_')) {
            const geonameId = parseInt(id.replace('geo_', ''), 10);
            if (!geonameId) return;
            const body = new URLSearchParams({ geoname_id: geonameId, csrf_token: CSRF_TOKEN });
            fetch('/api/barangay/register-geo', { method: 'POST', body })
                .then(r => r.json())
                .then(function(d) {
                    if (d.success && d.barangay && d.barangay.id) {
                        selectBarangay(d.barangay.id);
                    } else {
                        console.warn('Failed to register geo barangay for', id, d);
                    }
                })
                .catch(function(e) {
                    console.warn('register-geo error', e);
                });
            return;
        }

        const barangayId = Number(id);
        if (!barangayId || barangayId <= 0) {
            console.warn('Invalid barangay id selected:', id);
            return;
        }

        const body = new URLSearchParams({ barangay_id: barangayId, _token: CSRF_TOKEN });
        fetch('/api/barangay/set', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body })
            .then(r => r.json())
            .then(function(d) {
                if (d.success && d.barangay) {
                    currentBarangayId = d.barangay.id;
                    localStorage.setItem('ginto_barangay_id', currentBarangayId);
                    _setBarangayPillText('📍', d.barangay.name + ', ' + d.barangay.city);
                    // Update "Your current location is:" panel
                    const disp      = document.getElementById('barangayCurrentDisplay');
                    const nameEl    = document.getElementById('barangayCurrentName');
                    const provEl    = document.getElementById('barangayCurrentProvince');
                    const clearWrap = document.getElementById('barangayClearWrap');
                    if (nameEl) nameEl.textContent = d.barangay.name + ', ' + d.barangay.city;
                    if (provEl) provEl.textContent = d.barangay.province || '';
                    if (disp)   disp.style.display = 'block';
                    if (clearWrap) clearWrap.style.display = 'block';
                }
                const dd = document.getElementById('barangayDropdown');
                if (dd) dd.style.display = 'none';
                _barangayDropdownOpen = false;
                refreshSearchResultsFromServer();
            })
            .catch(function() { window.location.reload(); });
    }

    function clearBarangay() {
        const body = new URLSearchParams({ barangay_id: 0, _token: CSRF_TOKEN });
        fetch('/api/barangay/set', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body })
            .then(function() {
                currentBarangayId = 0;
                localStorage.removeItem('ginto_barangay_id');
                window.location.reload();
            })
            .catch(function() { window.location.reload(); });
    }

    // ── Auto-detect via browser Geolocation API ───────────────────────────────
    // On Android, window.AndroidLocation is a JavascriptInterface bridge.
    // The native app sets coords via FusedLocationProviderClient (requestLocationUpdates).
    // We poll the bridge every 500ms up to 10s to get the GPS fix, then fetch+pin silently.
    function autoDetectBarangay(userInitiated) {
        _setBarangayPillText('🔍', 'Detecting…');
        const dd = document.getElementById('barangayDropdown');
        if (dd) dd.style.display = 'none';
        _barangayDropdownOpen = false;

        // 1. Native Android bridge
        if (window.AndroidLocation) {
            if (AndroidLocation.hasLocation()) {
                _fetchAndPinBarangay(AndroidLocation.getLat(), AndroidLocation.getLng(), 'gps');
                return;
            }
            // GPS not fixed yet — poll until coordinates arrive (up to 10 seconds)
            var pollCount = 0;
            var pollTimer = setInterval(function() {
                pollCount++;
                if (window.AndroidLocation && AndroidLocation.hasLocation()) {
                    clearInterval(pollTimer);
                    _fetchAndPinBarangay(AndroidLocation.getLat(), AndroidLocation.getLng(), 'gps');
                } else if (pollCount >= 20) {
                    // 10s elapsed — fall back to IP geolocation
                    clearInterval(pollTimer);
                    _fetchAndPinBarangay(null, null, 'ip');
                }
            }, 500);
            return;
        }

        // 2. Browser navigator.geolocation (desktop / iOS)
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(pos) {
                    _fetchAndPinBarangay(pos.coords.latitude, pos.coords.longitude, 'gps');
                },
                function() {
                    // 3. IP fallback — server resolves from request IP
                    _fetchAndPinBarangay(null, null, 'ip');
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 300000 }
            );
        } else {
            _fetchAndPinBarangay(null, null, 'ip');
        }
    }

    function _fetchAndPinBarangay(lat, lng, source) {
        const url = (lat !== null && lng !== null)
            ? '/api/barangay/detect?lat=' + lat + '&lng=' + lng
            : '/api/barangay/detect';
        fetch(url)
            .then(r => r.json())
            .then(function(d) {
                if (!d.success || !d.barangay) { _setBarangayPillText('📍', 'Set location'); return; }
                const params = { barangay_id: d.barangay.id, _token: CSRF_TOKEN };
                if (lat !== null && lng !== null) { params.lat = lat; params.lng = lng; }
                const body = new URLSearchParams(params);
                fetch('/api/barangay/set', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body })
                    .then(function() {
                        currentBarangayId = d.barangay.id;
                        localStorage.setItem('ginto_barangay_id', currentBarangayId);
                        if (lat !== null && lng !== null) {
                            localStorage.setItem('ginto_buyer_lat', lat);
                            localStorage.setItem('ginto_buyer_lng', lng);
                        }
                        const label = source === 'ip'
                            ? d.barangay.name + ', ' + d.barangay.city + ' (approx.)'
                            : d.barangay.name + ', ' + d.barangay.city;
                        _setBarangayPillText('📍', label);
                        _updateCurrentLocationPanel(d.barangay, source);
                        refreshSearchResultsFromServer();
                    });
            })
            .catch(function() { _setBarangayPillText('📍', 'Set location'); });
    }

    function _updateCurrentLocationPanel(brgy, source) {
        const disp      = document.getElementById('barangayCurrentDisplay');
        const nameEl    = document.getElementById('barangayCurrentName');
        const provEl    = document.getElementById('barangayCurrentProvince');
        const clearWrap = document.getElementById('barangayClearWrap');
        if (nameEl) nameEl.textContent = brgy.name + ', ' + brgy.city
            + (source === 'ip' ? ' (approximate)' : '');
        if (provEl) provEl.textContent = brgy.province || '';
        if (disp)   disp.style.display = 'block';
        if (clearWrap) clearWrap.style.display = 'block';
    }

    // Called by native Android after FusedLocationProvider resolves GPS barangay
    window.gintoOnNativeBarangay = function(id, name, city, province) {
        if (!id) return;
        currentBarangayId = id;
        localStorage.setItem('ginto_barangay_id', id);
        _setBarangayPillText('📍', name + ', ' + city);
        _updateCurrentLocationPanel({ name: name, city: city, province: province }, 'gps');
        refreshSearchResultsFromServer();
    };

    function _setBarangayPillText(icon, text) {
        const iconEl = document.getElementById('barangayPillIcon');
        const textEl = document.getElementById('barangayPillText');
        if (iconEl) iconEl.textContent = icon;
        if (textEl) textEl.textContent = text;
    }

    // Always auto-detect on page load to refresh the displayed region (avoid stale pinned state).
    autoDetectBarangay(false);

    window.toggleBarangayDropdown = toggleBarangayDropdown;
    window.searchBarangay         = searchBarangay;
    window.selectBarangay         = selectBarangay;
    window.clearBarangay          = clearBarangay;
    window.autoDetectBarangay     = autoDetectBarangay;

}());

</script>
<?php include __DIR__ . '/bottom_nav.php'; ?>
