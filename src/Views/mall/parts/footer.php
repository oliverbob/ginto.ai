<!-- Footer / scripts for marketplace -->
<script src="/assets/js/dom.js"></script>
<script>
    // --- Sort By dropdown below search bar ---
    (function(){
        const btn = document.getElementById('sortByBtn');
        const dropdown = document.getElementById('sortByDropdown');
        let expanded = false;
        function openDropdown() {
            btn.setAttribute('aria-expanded', 'true');
            dropdown.classList.add('open');
            dropdown.style.display = 'block';
            expanded = true;
        }
        function closeDropdown() {
            btn.setAttribute('aria-expanded', 'false');
            dropdown.classList.remove('open');
            setTimeout(() => {
                if(!expanded) dropdown.style.display = 'none';
            }, 200);
            expanded = false;
        }
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (expanded) closeDropdown(); else openDropdown();
        });
        document.addEventListener('click', function(e) {
            if (!btn.contains(e.target) && !dropdown.contains(e.target)) closeDropdown();
        });
        dropdown.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeDropdown();
        });
        dropdown.querySelectorAll('li').forEach(li => {
            li.addEventListener('click', function() {
                dropdown.querySelectorAll('li').forEach(i => i.setAttribute('aria-selected','false'));
                li.setAttribute('aria-selected','true');
                const selectedText = li.textContent;
                const sortbyLabel = btn.querySelector('.sortby-label');
                if(sortbyLabel) {
                    sortbyLabel.textContent = li.dataset.value === 'default' ? 'Sort By' : selectedText;
                }
                closeDropdown();
                state.filters.sort = li.dataset.value || 'default';
                renderProducts();
            });
        });
    })();

    /* --- Data --- */
    const PRODUCTS = <?= json_encode(array_map(function($p) {
        return [
            'id' => (int)$p['id'],
            'title' => $p['title'] ?? '',
            'price' => floatval($p['price'] ?? 0),
            'currency' => $p['currency'] ?? 'USD',
            'cat' => $p['category_id'] ?? null,
            'rating' => isset($p['rating']) ? floatval($p['rating']) : 0,
            'img' => (!empty($p['images']) ? json_decode($p['images'])[0] ?? null : null),
            'desc' => $p['short_description'] ?? '',
            'status' => $p['status'] ?? 'published'
        ];
    }, $products)) ?>;

    const PLACEHOLDER_SVG = '/assets/images/placeholder_ceramic.svg';

    /* --- State --- */
    let state = {
        products: [...PRODUCTS],
        cart: JSON.parse(localStorage.getItem('epower_cart') || '[]'),
        filters: { cat: 'all', search: '', sort: 'default' }
    };

    /* --- DOM Elements --- */
    const grid = document.getElementById('productGrid');
    const resultCount = document.getElementById('resultCount');
    const cartCount = document.getElementById('cartCount');
    
    /* --- Initialization --- */
    async function loadServerProducts() {
        try {
            const resp = await fetch('/api/mall/products');
            const json = await resp.json();
            if (json && json.success && Array.isArray(json.products)) {
                state.products = [...json.products, ...state.products];
            }
        } catch (e) {
            console.warn('Failed to load server products', e);
        }
    }

    async function init() {
        await loadServerProducts();
        renderProducts();
        updateCartUI();
        applyTheme(localStorage.getItem('epower_theme') || 'dark');
        // Ensure sidebar close button visibility is correct on load
        updateSidebarCloseBtnVisibility();
    }

    /* --- Rendering --- */
    function renderProducts() {
        let filtered = state.products.filter(p => {
            const matchCat = state.filters.cat === 'all' || p.cat === state.filters.cat;
            const matchSearch = p.title.toLowerCase().includes(state.filters.search.toLowerCase());
            return matchCat && matchSearch;
        });

        if(state.filters.sort === 'price_asc') filtered.sort((a,b) => a.price - b.price);
        if(state.filters.sort === 'price_desc') filtered.sort((a,b) => b.price - a.price);
        if(state.filters.sort === 'rating') filtered.sort((a,b) => b.rating - a.rating);

        resultCount.textContent = filtered.length;
        grid.innerHTML = '';

        if (filtered.length === 0) {
            grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1">No products found matching your criteria.</div>`;
            return;
        }

        filtered.forEach(p => {
            const div = document.createElement('div');
            div.className = 'card';
            div.innerHTML = `
                <div class="card-img-wrap" onclick="openModal(${p.id})" style="cursor:pointer">
                    <img src="${p.img}" class="card-img" alt="${p.title}" loading="lazy" onerror="this.onerror=null;this.src=PLACEHOLDER_SVG;this.classList.add('img-broken');">
                    ${p.badge ? `<span class="card-badge">${p.badge}</span>` : ''}
                </div>
                <div class="card-body">
                    <h3 class="card-title">${p.title}</h3>
                    <div class="card-meta">
                        <span>${p.cat}</span>
                        <span style="color:#f59e0b">★ ${p.rating}</span>
                    </div>
                    <div class="card-price">${p.formatted_price || ('$' + (p.price || 0).toFixed(2))}</div>
                    <button class="btn btn-primary" onclick="addToCart(${p.id})">Add to Cart</button>
                </div>
            `;
            grid.appendChild(div);
        });
    }

    /* --- Logic: Cart --- */
    function addToCart(id) {
        const product = PRODUCTS.find(p => p.id === id);
        const existing = state.cart.find(item => item.id === id);
        
        if (existing) {
            existing.qty++;
            showToast(`Increased quantity: ${product.title}`);
        } else {
            state.cart.push({ ...product, qty: 1 });
            showToast(`Added to cart: ${product.title}`);
        }
        saveCart();
    }

    function updateCartQty(id, delta) {
        const item = state.cart.find(i => i.id === id);
        if(!item) return;
        item.qty += delta;
        if(item.qty <= 0) state.cart = state.cart.filter(i => i.id !== id);
        saveCart();
    }

    function saveCart() {
        localStorage.setItem('epower_cart', JSON.stringify(state.cart));
        updateCartUI();
    }

    function updateCartUI() {
        const totalQty = state.cart.reduce((acc, item) => acc + item.qty, 0);
        const totalPrice = state.cart.reduce((acc, item) => acc + (item.price * item.qty), 0);
        
        cartCount.textContent = totalQty;
        cartCount.style.display = totalQty > 0 ? 'block' : 'none';
        document.getElementById('cartTotal').textContent = '$' + totalPrice.toFixed(2);

        const cartList = document.getElementById('cartItems');
        cartList.innerHTML = '';
        
        if(state.cart.length === 0) {
            cartList.innerHTML = '<div class="text-center text-muted" style="margin-top:40px">Your cart is empty.</div>';
            return;
        }

        state.cart.forEach(item => {
            cartList.innerHTML += `
                <div class="cart-item">
                    <img src="${item.img}" alt="${item.title}">
                    <div class="cart-item-details">
                        <div style="font-weight:600;font-size:0.9rem">${item.title}</div>
                        <div style="font-size:0.85rem;color:var(--text-muted)">$${item.price}</div>
                        <div class="cart-controls">
                            <button class="qty-btn" onclick="updateCartQty(${item.id}, -1)">-</button>
                            <span>${item.qty}</span>
                            <button class="qty-btn" onclick="updateCartQty(${item.id}, 1)">+</button>
                        </div>
                    </div>
                    <div style="font-weight:600">$${(item.price * item.qty).toFixed(2)}</div>
                </div>
            `;
        });
    }

    function toggleCart() {
        document.getElementById('cartDrawerContainer').classList.toggle('drawer-open');
    }

    function checkout() {
        if(state.cart.length === 0) return;
        alert('Proceeding to checkout demo...');
    }

    /* --- Logic: Modal --- */
    function openModal(id) {
        const p = PRODUCTS.find(x => x.id === id);
        document.getElementById('qvImg').src = p.img;
        document.getElementById('qvTitle').textContent = p.title;
        document.getElementById('qvRating').textContent = '★'.repeat(Math.floor(p.rating)) + '☆'.repeat(5 - Math.floor(p.rating));
        document.getElementById('qvPrice').textContent = p.formatted_price || ('$' + (p.price || 0).toFixed(2));
        document.getElementById('qvDesc').textContent = p.desc;
        document.getElementById('qvAddBtn').onclick = () => { addToCart(id); closeModal(); };
        
        document.getElementById('qvBackdrop').classList.add('open');
    }
    function closeModal() {
        document.getElementById('qvBackdrop').classList.remove('open');
    }


    /* --- Logic: Filters & Listeners --- */
    document.querySelectorAll('.cat-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            state.filters.cat = e.target.dataset.cat;
            renderProducts();
            if(window.innerWidth < 800) toggleSidebar(); // close sidebar on mobile selection
        });
    });

    document.getElementById('searchInput').addEventListener('input', (e) => {
        state.filters.search = e.target.value;
        renderProducts();
    });

    /* --- Logic: UI Utilities --- */
    function showToast(msg) {
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.textContent = msg;
        document.getElementById('toastContainer').appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    function updateSidebarCloseBtnVisibility() {
        const sidebar = document.getElementById('sidebar');
        const closeBtn = document.getElementById('sidebarCloseBtn');
        if (!closeBtn || !sidebar) return;
        if (sidebar.classList.contains('open') && window.innerWidth <= 800) {
            closeBtn.style.display = 'inline-flex';
            closeBtn.setAttribute('aria-hidden', 'false');
        } else {
            closeBtn.style.display = 'none';
            closeBtn.setAttribute('aria-hidden', 'true');
        }
    }

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('mobileSidebarBackdrop');
        if (!sidebar) return;
        const isOpen = sidebar.classList.toggle('open');
        if (backdrop) backdrop.classList.toggle('open', isOpen);
        document.body.classList.toggle('overflow-hidden', isOpen);
        sidebar.setAttribute('aria-hidden', isOpen ? 'false' : 'true');

        // Update close button visibility and focus management
        updateSidebarCloseBtnVisibility();

        if (isOpen) {
            const closeBtn = document.getElementById('sidebarCloseBtn');
            if (closeBtn) setTimeout(() => closeBtn.focus({preventScroll: true}), 50);
        } else {
            const toggleBtn = document.querySelector('.mobile-toggle');
            if (toggleBtn) setTimeout(() => toggleBtn.focus({preventScroll: true}), 50);
        }
    }

    // Keep button visibility correct on resize
    window.addEventListener('resize', function() {
        updateSidebarCloseBtnVisibility();
    });

    if (document.getElementById('sidebarCloseBtn')) {
        document.getElementById('sidebarCloseBtn').addEventListener('click', toggleSidebar);
    }

    /* --- Theme Logic --- */
    const themeToggle = document.getElementById('themeToggle');
    themeToggle.addEventListener('click', () => {
        const isLight = document.body.classList.contains('light');
        applyTheme(isLight ? 'dark' : 'light');
    });

    function applyTheme(theme) {
        if(theme === 'light') {
            document.body.classList.add('light');
            document.getElementById('themeIcon').textContent = '🌙';
        } else {
            document.body.classList.remove('light');
            document.getElementById('themeIcon').textContent = '☀️';
        }
        localStorage.setItem('epower_theme', theme);
    }

    document.addEventListener('keydown', (e) => {
        if(e.key === 'Escape') { closeModal(); document.getElementById('cartDrawerContainer').classList.remove('drawer-open'); }
    });

    init();
</script>

<script>
    // Upload modal logic
    function openUploadModal() {
        const el = document.getElementById('uploadBackdrop');
        if (el) el.classList.add('open');
    }
    function closeUploadModal() {
        const el = document.getElementById('uploadBackdrop');
        if (el) el.classList.remove('open');
        const form = document.getElementById('uploadForm');
        if (form) form.reset();
        const prev = document.getElementById('uploadPreview'); if(prev) prev.innerHTML = '';
    }

    // Image preview
    const imgInput = document.getElementById('uploadImageInput');
    if (imgInput) {
        imgInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            const prev = document.getElementById('uploadPreview');
            if (!file) { if(prev) prev.innerHTML = ''; return; }
            const reader = new FileReader();
            reader.onload = function(evt) { if(prev) prev.innerHTML = `<img src="${evt.target.result}" style="height:48px;border-radius:6px;object-fit:cover">`; };
            reader.readAsDataURL(file);
        });
    }

    // Handle upload submit
    const uploadForm = document.getElementById('uploadForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type=submit]');
            if (btn) btn.disabled = true;
            const fd = new FormData(this);
            try {
                const res = await fetch('/marketplace/sellers/upload', { method: 'POST', body: fd });
                const json = await res.json();
                if (!json || !json.success) {
                    showToast(json.message || 'Upload failed');
                    if (btn) btn.disabled = false;
                    return;
                }
                try {
                    const r = await fetch('/api/mall/products');
                    const d = await r.json();
                    if (d && d.success && Array.isArray(d.products)) {
                        state.products = [...d.products, ...state.products.filter(p => !d.products.find(sp => sp.id === p.id))];
                    } else {
                        state.products.unshift(json.product);
                    }
                    renderProducts();
                } catch (e) {
                    state.products.unshift(json.product);
                    renderProducts();
                }
                showToast('Product uploaded');
                closeUploadModal();
            } catch (err) {
                console.error('Upload error', err);
                showToast('Upload failed');
            } finally {
                if (btn) btn.disabled = false;
            }
        });
    }
</script>