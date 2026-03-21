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
            'price'    => (float)($p['price'] ?? $p['price_amount'] ?? 0),
            'currency' => $p['currency'] ?? $p['price_currency'] ?? 'USD',
            'cat'      => isset($p['category_id']) ? (int)$p['category_id'] : null,
            'rating'   => (float)($p['rating'] ?? 0),
            'img'      => $img,
            'imgs'     => $imgs_arr,
            'desc'     => $p['short_description'] ?? '',
            'badge'        => $p['badge'] ?? null,
            'seller_slug'  => $p['seller_slug'] ?? null,
            'seller_name'  => $p['seller_name'] ?? null,
            'seller_id'    => (int)($p['seller_id'] ?? 0),
        ];
    }, $products ?? [])) ?>;

    const CSRF_TOKEN = <?= json_encode($csrf_token ?? '') ?>;
    const PLACEHOLDER = '/assets/images/placeholder_ceramic.svg';

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
        cart: [],
        filters: { cat: 'all', search: '', sort: 'default', sale: false, ship: false }
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
        let list = state.products.filter(p => {
            const matchCat    = state.filters.cat === 'all' || String(p.cat) === String(state.filters.cat);
            const matchSearch = !state.filters.search || p.title.toLowerCase().includes(state.filters.search.toLowerCase());
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
                    <button class="product-add-btn" onclick="addToCart(${p.id})" aria-label="Add ${esc(p.title)} to cart">Add to Cart</button>
                    ${p.seller_slug ? `<a class="product-store-link" href="/mall/${esc(p.seller_slug)}" onclick="event.stopPropagation()">🏪 ${esc(p.seller_name || 'View Store')}</a>` : ''}
                </div>`;
            frag.appendChild(card);
        });
        grid.appendChild(frag);
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
    function saveCart() {
        try { localStorage.setItem('epower_cart', JSON.stringify(state.cart)); } catch (e) {}
        updateCartUI();
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

    function closeQV() {
        if (!qvOverlay) return;
        qvOverlay.classList.remove('active');
        qvOverlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        const wrap = document.getElementById('qvMainWrap');
        if (wrap && _qvZoomFn) { wrap.removeEventListener('mousemove', _qvZoomFn); _qvZoomFn = null; }
    }
    if (qvOverlay) qvOverlay.addEventListener('click', function (e) { if (e.target === qvOverlay) closeQV(); });

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
    if (mallNotifyToggle && mallNotifyPanel) {
        mallNotifyToggle.addEventListener('click', async function (e) {
            e.stopPropagation();
            const isOpen = mallNotifyPanel.style.display === 'block';
            mallNotifyPanel.style.display = isOpen ? 'none' : 'block';
            if (!isOpen) {
                try {
                    await fetch('/api/mall/notifications/mark-read', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': CSRF_TOKEN,
                        },
                        body: JSON.stringify({ csrf_token: CSRF_TOKEN }),
                    });
                    const badge = document.getElementById('mallNotifyBadge');
                    if (badge) badge.style.display = 'none';
                } catch (_) {}
            }
        });
        document.addEventListener('click', function (e) {
            if (!mallNotifyPanel.contains(e.target) && !mallNotifyToggle.contains(e.target)) {
                mallNotifyPanel.style.display = 'none';
            }
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
            renderProducts();
        });
        // Prevent form submit reload
        var searchForm = document.getElementById('searchForm');
        if (searchForm) searchForm.addEventListener('submit', function (e) { e.preventDefault(); });
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
            id:       p.id,
            title:    p.title || '',
            price:    parseFloat(p.price) || 0,
            currency: p.currency || 'USD',
            cat:      p.cat != null ? p.cat : (p.category_id != null ? parseInt(p.category_id, 10) : null),
            rating:   parseFloat(p.rating) || 0,
            img:      img,
            imgs:     imgs.length ? imgs : (img ? [img] : []),
            desc:     p.desc || p.short_description || '',
            badge:    p.badge || null,
        };
    }

    async function loadServerProducts() {
        try {
            const res = await fetch('/api/mall/products');
            if (!res.ok) return;
            const json = await res.json();
            if (json?.success && Array.isArray(json.products)) {
                // Merge: server products first (normalised), then any local stubs not in server list
                const normalised = json.products.map(normaliseProduct);
                const ids = new Set(normalised.map(p => p.id));
                state.products = [...normalised, ...state.products.filter(p => !ids.has(p.id))];
            }
        } catch (e) {
            console.warn('Failed to fetch server products', e);
        }
    }

    /* ============================
     * INIT
     * ============================ */
    async function init() {
        await loadServerProducts();
        renderProducts();
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

}());

</script>
