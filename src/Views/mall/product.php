<?php
/**
 * product.php — Individual product detail page
 * SEO + social network friendly (OG, Twitter, JSON-LD)
 * Variables: $product (array), $seller (array), $storefront (array), $relatedProducts (array)
 * Meta variables set in controller: $ogTitle, $ogDesc, $ogImage, $ogType, $ogUrl
 */
$product       = $product ?? [];
$seller        = $seller  ?? [];
$storefront    = $storefront ?? [];
$related       = $relatedProducts ?? [];
$isLoggedIn    = !empty($_SESSION['user_id']);

$pid           = (int)($product['id'] ?? 0);
$title         = htmlspecialchars($product['title'] ?? 'Product', ENT_QUOTES, 'UTF-8');
$price         = round((float)($product['price'] ?? 0) * 1.15, 2);
$currency      = $product['currency'] ?? 'PHP';
$desc          = htmlspecialchars($product['description'] ?? $product['short_description'] ?? '', ENT_QUOTES, 'UTF-8');
$shortDesc     = htmlspecialchars($product['short_description'] ?? '', ENT_QUOTES, 'UTF-8');
$slug          = htmlspecialchars($product['slug'] ?? '', ENT_QUOTES, 'UTF-8');
$rating        = (float)($product['rating'] ?? 0);
$badge         = $product['badge'] ?? null;
$storeSlug     = htmlspecialchars($storefront['slug'] ?? '', ENT_QUOTES, 'UTF-8');
$storeName     = htmlspecialchars($storefront['display_name'] ?? ($seller['fullname'] ?? 'Seller'), ENT_QUOTES, 'UTF-8');
$canonicalUrl  = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'ginto.ai') . '/mall/product/' . $slug;

// Images
$images = [];
if (!empty($product['images'])) {
    $decoded = json_decode($product['images'], true);
    if (is_array($decoded)) $images = array_values(array_filter($decoded));
}
if (empty($images) && !empty($product['image_path'])) $images = [$product['image_path']];
$mainImg = $images[0] ?? '/assets/images/placeholder_ceramic.svg';

// Currency symbol
$currencySymbol = ['USD' => '$', 'PHP' => '₱', 'NGN' => '₦', 'EUR' => '€'][$currency] ?? ($currency . ' ');
$priceFormatted = $currencySymbol . number_format($price, 2);

// Schema.org JSON-LD
$_proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host  = $_SERVER['HTTP_HOST'] ?? 'ginto.ai';
$absImg = (str_starts_with($mainImg, 'http')) ? $mainImg : ($_proto . '://' . $_host . $mainImg);
$jsonLd = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Product',
    'name'        => $product['title'] ?? 'Product',
    'description' => strip_tags($product['description'] ?? $product['short_description'] ?? ''),
    'image'       => $absImg,
    'url'         => $canonicalUrl,
    'sku'         => 'GINTO-' . $pid,
    'offers'      => [
        '@type'         => 'Offer',
        'price'         => $price,
        'priceCurrency' => $currency,
        'availability'  => ($product['status'] ?? '') === 'published' ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'url'           => $canonicalUrl,
        'seller'        => ['@type' => 'Organization', 'name' => $storefront['display_name'] ?? 'Ginto Seller'],
    ],
    'aggregateRating' => $rating > 0 ? [
        '@type'       => 'AggregateRating',
        'ratingValue' => $rating,
        'bestRating'  => 5,
        'worstRating' => 1,
        'reviewCount' => (int)($product['review_count'] ?? 1),
    ] : null,
];
$jsonLd = array_filter($jsonLd, fn($v) => $v !== null);
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<body>
<?php include __DIR__ . '/parts/header.php'; ?>

<!-- Product JSON-LD structured data -->
<script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>

<style>
.prod-page { max-width:1100px; margin:28px auto 80px; padding:0 18px; }
.prod-breadcrumb { font-size:0.78rem; color:var(--muted); margin-bottom:20px; display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.prod-breadcrumb a { color:var(--accent); text-decoration:none; }
.prod-breadcrumb a:hover { text-decoration:underline; }
.prod-main { display:grid; grid-template-columns:1fr 1fr; gap:36px; align-items:start; }
@media(max-width:700px){ .prod-main { grid-template-columns:1fr; gap:24px; } }

/* Gallery */
.prod-gallery { position:relative; }
.prod-main-img-wrap {
    border-radius:20px; overflow:hidden; background:var(--surface2);
    aspect-ratio:1/1; position:relative; cursor:zoom-in;
    border:1px solid var(--border);
}
.prod-main-img { width:100%; height:100%; object-fit:contain; display:block; padding:8px; }
.prod-thumbs { display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
.prod-thumb {
    width:64px; height:64px; border-radius:10px; overflow:hidden;
    border:2px solid transparent; cursor:pointer; transition:border-color .15s;
    background:var(--surface2);
}
.prod-thumb.active { border-color:var(--accent); }
.prod-thumb img { width:100%; height:100%; object-fit:cover; }

/* Info panel */
.prod-info { display:flex; flex-direction:column; gap:18px; }
.prod-badge-wrap { display:flex; gap:8px; flex-wrap:wrap; }
.prod-badge {
    padding:4px 12px; border-radius:999px; font-size:0.73rem; font-weight:700;
    background:rgba(239,68,68,0.10); color:#ef4444; border:1px solid rgba(239,68,68,0.2);
}
.prod-title { font-size:1.7rem; font-weight:800; line-height:1.2; }
.prod-rating { display:flex; align-items:center; gap:8px; }
.prod-stars { color:#f59e0b; font-size:1.1rem; letter-spacing:1px; }
.prod-rating-count { font-size:0.78rem; color:var(--muted); }
.prod-price { font-size:2.2rem; font-weight:800; color:var(--accent); }
.prod-short-desc { font-size:0.9rem; line-height:1.7; color:var(--muted); }
.prod-seller-card {
    display:flex; align-items:center; gap:12px; padding:12px 14px;
    border-radius:12px; background:var(--surface2); border:1px solid var(--border);
    text-decoration:none; color:inherit; transition:border-color .15s;
}
.prod-seller-card:hover { border-color:var(--accent); }
.prod-seller-logo {
    width:40px; height:40px; border-radius:10px; background:var(--surface);
    border:1px solid var(--border); display:flex; align-items:center; justify-content:center;
    font-size:1.1rem; font-weight:800; flex-shrink:0; overflow:hidden;
}
.prod-seller-logo img { width:100%; height:100%; object-fit:cover; }
.prod-seller-label { font-size:0.7rem; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:0.1em; }
.prod-seller-name { font-size:0.9rem; font-weight:700; }

.prod-actions { display:flex; flex-direction:column; gap:10px; }
.prod-qty-row { display:flex; align-items:center; gap:10px; }
.prod-qty-btn {
    width:36px; height:36px; border-radius:8px; border:1px solid var(--border);
    background:var(--surface2); color:var(--text); font-size:1.2rem; cursor:pointer;
    display:flex; align-items:center; justify-content:center; font-weight:700;
    transition:background .15s;
}
.prod-qty-btn:hover { background:var(--border); }
.prod-qty-val { font-size:1rem; font-weight:700; min-width:24px; text-align:center; }
.prod-add-btn { flex:1; }
.prod-buy-btn { width:100%; padding:13px; border-radius:12px; font-size:0.95rem; font-weight:700; }

/* Share row */
.prod-share-row { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.prod-share-label { font-size:0.78rem; color:var(--muted); font-weight:600; }
.prod-share-btn {
    padding:6px 14px; border-radius:8px; border:1px solid var(--border);
    background:var(--surface2); color:var(--text); font-size:0.78rem; font-weight:600;
    cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:5px;
    transition:background .15s;
}
.prod-share-btn:hover { background:var(--border); }
.prod-share-btn.copy-done { border-color:rgba(34,197,94,0.4); color:#22c55e; background:rgba(34,197,94,0.08); }

/* Description */
.prod-description { max-width:1100px; margin:36px auto 0; padding:0 18px; }
.prod-desc-title { font-size:1.1rem; font-weight:700; margin-bottom:14px; }
.prod-desc-body { font-size:0.9rem; line-height:1.8; color:var(--muted); white-space:pre-wrap; }

/* Related */
.prod-related { max-width:1100px; margin:40px auto 0; padding:0 18px; }
.prod-related-title { font-size:1.1rem; font-weight:700; margin-bottom:16px; }
.prod-related-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:14px; }
.prod-related-card {
    border-radius:14px; border:1px solid var(--border); background:var(--surface);
    overflow:hidden; text-decoration:none; color:inherit; transition:box-shadow .2s, border-color .2s;
}
.prod-related-card:hover { box-shadow:0 8px 24px rgba(0,0,0,0.12); border-color:var(--accent); }
.prod-related-img { width:100%; aspect-ratio:1/1; object-fit:cover; background:var(--surface2); }
.prod-related-info { padding:10px 12px; }
.prod-related-name { font-size:0.82rem; font-weight:600; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.prod-related-price { font-size:0.85rem; font-weight:700; color:var(--accent); }
</style>

<div class="prod-page">
    <!-- Breadcrumb -->
    <nav class="prod-breadcrumb" aria-label="Breadcrumb">
        <a href="/mall">Mall</a>
        <span aria-hidden="true">›</span>
        <?php if ($storeSlug): ?>
        <a href="/mall/<?= $storeSlug ?>"><?= $storeName ?></a>
        <span aria-hidden="true">›</span>
        <?php endif; ?>
        <span aria-current="page"><?= $title ?></span>
    </nav>

    <!-- Main Product Block -->
    <div class="prod-main">
        <!-- Gallery -->
        <div class="prod-gallery">
            <div class="prod-main-img-wrap" id="prodImgWrap">
                <img id="prodMainImg" class="prod-main-img"
                    src="<?= htmlspecialchars($mainImg, ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= $title ?>">
            </div>
            <?php if (count($images) > 1): ?>
            <div class="prod-thumbs" id="prodThumbs">
                <?php foreach ($images as $i => $img): ?>
                <div class="prod-thumb <?= $i === 0 ? 'active' : '' ?>"
                    data-src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>"
                    onclick="switchImg(this)"
                    tabindex="0" role="button"
                    aria-label="Product image <?= $i + 1 ?>">
                    <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>"
                        alt="<?= $title ?> image <?= $i + 1 ?>"
                        loading="lazy">
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Info -->
        <div class="prod-info">
            <?php if ($badge): ?>
            <div class="prod-badge-wrap">
                <span class="prod-badge"><?= htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <?php endif; ?>

            <h1 class="prod-title"><?= $title ?></h1>

            <?php if ($rating > 0): ?>
            <div class="prod-rating" aria-label="Rating: <?= $rating ?> out of 5">
                <span class="prod-stars" aria-hidden="true">
                    <?= str_repeat('★', round($rating)) . str_repeat('☆', 5 - round($rating)) ?>
                </span>
                <span class="prod-rating-count"><?= number_format($rating, 1) ?> / 5.0</span>
            </div>
            <?php endif; ?>

            <div class="prod-price" aria-label="Price: <?= $priceFormatted ?>"><?= htmlspecialchars($priceFormatted, ENT_QUOTES, 'UTF-8') ?></div>

            <?php if ($shortDesc): ?>
            <p class="prod-short-desc"><?= $shortDesc ?></p>
            <?php endif; ?>

            <!-- Seller card -->
            <?php if ($storeSlug): ?>
            <a class="prod-seller-card" href="/mall/<?= $storeSlug ?>" aria-label="Visit <?= $storeName ?> store">
                <div class="prod-seller-logo">
                    <?php if (!empty($storefront['logo_image'])): ?>
                    <img src="<?= htmlspecialchars($storefront['logo_image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= $storeName ?> logo">
                    <?php else: ?>
                    <?= strtoupper(substr($storefront['display_name'] ?? 'S', 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="prod-seller-label">Sold by</div>
                    <div class="prod-seller-name"><?= $storeName ?></div>
                </div>
                <svg style="margin-left:auto;flex-shrink:0;color:var(--muted)" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
            </a>
            <?php endif; ?>

            <!-- Add to cart -->
            <div class="prod-actions">
                <div class="prod-qty-row">
                    <button class="prod-qty-btn" id="qtyDec" aria-label="Decrease quantity">−</button>
                    <span class="prod-qty-val" id="qtyVal" aria-live="polite">1</span>
                    <button class="prod-qty-btn" id="qtyInc" aria-label="Increase quantity">+</button>
                    <span style="font-size:0.78rem;color:var(--muted);margin-left:4px;">qty</span>
                </div>
                <div style="display:flex;gap:10px;">
                    <button class="btn btn-secondary prod-add-btn" id="prodAddToCart" onclick="addToCartFromPage()">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true" style="flex-shrink:0"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                        Add to Cart
                    </button>
                    <button class="btn btn-primary" id="prodBuyNow" onclick="buyNowFromPage()" style="flex-shrink:0;padding:12px 22px;">
                        Buy Now
                    </button>
                </div>
            </div>

            <!-- Share -->
            <div class="prod-share-row">
                <span class="prod-share-label">Share:</span>
                <button class="prod-share-btn" id="copyLinkBtn" onclick="copyProductLink()" title="Copy link">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                    Copy Link
                </button>
                <a class="prod-share-btn"
                    href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($canonicalUrl) ?>"
                    target="_blank" rel="noopener noreferrer" title="Share on Facebook">
                    <svg width="13" height="13" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                    Facebook
                </a>
                <a class="prod-share-btn"
                    href="https://twitter.com/intent/tweet?text=<?= urlencode($product['title'] ?? 'Check this out') ?>&url=<?= urlencode($canonicalUrl) ?>"
                    target="_blank" rel="noopener noreferrer" title="Share on X / Twitter">
                    <svg width="13" height="13" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4l16 16M4 20L20 4"/></svg>
                    X / Twitter
                </a>
                <a class="prod-share-btn"
                    href="viber://forward?text=<?= urlencode(($product['title'] ?? '') . ' ' . $canonicalUrl) ?>"
                    title="Share via Viber">
                    📲 Viber
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Full Description -->
<?php if (!empty($product['description'])): ?>
<div class="prod-description">
    <h2 class="prod-desc-title">Product Description</h2>
    <div class="prod-desc-body"><?= htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8') ?></div>
</div>
<?php endif; ?>

<!-- Related Products -->
<?php if (!empty($related)): ?>
<div class="prod-related">
    <h2 class="prod-related-title">More from this store</h2>
    <div class="prod-related-grid">
        <?php foreach ($related as $r): ?>
        <?php
            $rImgs = [];
            if (!empty($r['images'])) {
                $rd = json_decode($r['images'], true);
                if (is_array($rd)) $rImgs = array_values(array_filter($rd));
            }
            if (empty($rImgs) && !empty($r['image_path'])) $rImgs = [$r['image_path']];
            $rImg  = $rImgs[0] ?? '/assets/images/placeholder_ceramic.svg';
            $rSlug = $r['slug'] ?? '';
            $rCur  = ['USD' => '$', 'PHP' => '₱', 'NGN' => '₦', 'EUR' => '€'][$r['currency'] ?? 'PHP'] ?? '₱';
        ?>
        <a class="prod-related-card"
            href="/mall/product/<?= htmlspecialchars($rSlug, ENT_QUOTES, 'UTF-8') ?>"
            title="<?= htmlspecialchars($r['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <img class="prod-related-img"
                src="<?= htmlspecialchars($rImg, ENT_QUOTES, 'UTF-8') ?>"
                alt="<?= htmlspecialchars($r['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                loading="lazy"
                onerror="this.src='/assets/images/placeholder_ceramic.svg'">
            <div class="prod-related-info">
                <div class="prod-related-name"><?= htmlspecialchars($r['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                <div class="prod-related-price"><?= $rCur . number_format(round((float)($r['price'] ?? 0) * 1.15, 2), 2) ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Cart drawer -->
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
        <div class="cart-total-row"><span>Total</span><span id="cartTotal">₱0.00</span></div>
        <button class="btn btn-primary" style="width:100%" onclick="checkout()">Checkout</button>
    </div>
</aside>

<script>
(function () {
    'use strict';
    // Product data for this page
    const PRODUCT = <?= json_encode([
        'id'       => $pid,
        'title'    => $product['title'] ?? '',
        'price'    => $price,
        'currency' => $currency,
        'cat'      => isset($product['category_id']) ? (int)$product['category_id'] : null,
        'rating'   => $rating,
        'img'      => $mainImg,
        'imgs'     => $images,
        'desc'     => $product['short_description'] ?? '',
        'slug'     => $product['slug'] ?? '',
        'seller_slug' => $storefront['slug'] ?? null,
        'seller_name' => $storefront['display_name'] ?? null,
    ]) ?>;
    const CANON_URL = <?= json_encode($canonicalUrl) ?>;
    const CSRF_TOKEN = <?= json_encode($csrf_token ?? '') ?>;

    // Qty controls
    let qty = 1;
    const qtyVal = document.getElementById('qtyVal');
    document.getElementById('qtyDec').addEventListener('click', function () {
        if (qty > 1) { qty--; qtyVal.textContent = qty; }
    });
    document.getElementById('qtyInc').addEventListener('click', function () {
        qty++; qtyVal.textContent = qty;
    });

    // Gallery switch
    window.switchImg = function (thumb) {
        const mainImg = document.getElementById('prodMainImg');
        mainImg.src = thumb.dataset.src;
        document.querySelectorAll('.prod-thumb').forEach(function (t) { t.classList.remove('active'); });
        thumb.classList.add('active');
    };

    function sanitizeShareText(value) {
        const text = String(value || '');
        return text
            // Remove control chars except newline and tab.
            .replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/g, '')
            // Remove hidden/bidi chars often used in spoofed text.
            .replace(/[\u200B-\u200F\u202A-\u202E\u2060\u2066-\u2069\uFEFF]/g, '')
            // Remove obvious HTML/script delimiters from user-origin fields.
            .replace(/[<>`]/g, '')
            .replace(/\s{2,}/g, ' ')
            .trim();
    }

    function buildProductShareText() {
        const safeTitle = sanitizeShareText(PRODUCT.title || 'Product');
        const safeStore = sanitizeShareText(PRODUCT.seller_name || 'Ginto Mall');
        const safeUrl = sanitizeShareText(CANON_URL);
        const symbols = { USD: '$', PHP: '₱', NGN: '₦', EUR: '€' };
        const currency = String(PRODUCT.currency || 'PHP').toUpperCase();
        const symbol = symbols[currency] || (currency + ' ');
        const amount = Number(PRODUCT.price || 0);
        const safePrice = Number.isFinite(amount) ? (symbol + amount.toFixed(2)) : (symbol + '0.00');

        return [
            '🛍️ ' + safeTitle,
            '💰 Price: ' + safePrice,
            '🏪 Store: ' + safeStore,
            '🔗 ' + safeUrl,
        ].join('\n');
    }

    // Share: copy social-ready product text
    window.copyProductLink = function () {
        const btn = document.getElementById('copyLinkBtn');
        const shareText = buildProductShareText();
        navigator.clipboard.writeText(shareText).then(function () {
            btn.textContent = '✓ Copied!';
            btn.classList.add('copy-done');
            setTimeout(function () {
                btn.innerHTML = '<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg> Copy Link';
                btn.classList.remove('copy-done');
            }, 2200);
        }).catch(function () {
            // Fallback
            const ta = document.createElement('textarea');
            ta.value = shareText;
            ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta); ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            btn.textContent = '✓ Copied!';
            btn.classList.add('copy-done');
            setTimeout(function () { btn.textContent = 'Copy Link'; btn.classList.remove('copy-done'); }, 2200);
        });
    };

    // Cart helpers (footer.php is not loaded on this standalone page — implement inline)
    let cart = [];
    try { cart = JSON.parse(localStorage.getItem('epower_cart') || '[]'); } catch(e) {}

    function persistCart() {
        try { localStorage.setItem('epower_cart', JSON.stringify(cart)); } catch(e) {}
        const total = cart.reduce(function(s, i) { return s + (i.qty || 1); }, 0);
        const badge = document.getElementById('cartBadge');
        if (badge) { badge.textContent = total > 0 ? (total > 99 ? '99+' : total) : ''; badge.style.display = total > 0 ? 'flex' : 'none'; }
        if (window.AndroidCart) { window.AndroidCart.onUpdate(total); }
        if (document.cookie.indexOf('PHPSESSID') !== -1) {
            fetch('/api/mall/cart/sync', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({count:total}), keepalive:true }).catch(function(){});
        }
        renderCart();
    }

    function renderCart() {
        const body = document.getElementById('cartItems');
        const totalEl = document.getElementById('cartTotal');
        if (!body) return;
        if (cart.length === 0) { body.innerHTML = '<p class="cart-empty-msg">Your cart is empty.</p>'; if (totalEl) totalEl.textContent = '₱0.00'; return; }
        const sym = {'USD':'$','PHP':'₱','NGN':'₦','EUR':'€'};
        let html = '', total = 0;
        cart.forEach(function(item) {
            const s = sym[item.currency] || (item.currency + ' ');
            total += item.price * item.qty;
            const imageUrl = esc(item.img || '/assets/images/placeholder_ceramic.svg');
            html += '<div class="cart-item"><img class="cart-item-img" src="' + imageUrl + '" alt="' + esc(item.title) + '" onerror="this.src=\'/assets/images/placeholder_ceramic.svg\'">'
                + '<div class="cart-item-info"><div class="cart-item-name">' + esc(item.title) + '</div>'
                + '<div class="cart-item-price">' + s + Number(item.price).toFixed(2) + '</div>'
                + '<div class="cart-item-qty-row"><button onclick="changeQty(' + item.id + ',-1)" class="qty-btn" aria-label="Decrease">-</button>'
                + '<span>' + item.qty + '</span><button onclick="changeQty(' + item.id + ',1)" class="qty-btn" aria-label="Increase">+</button></div></div></div>';
        });
        body.innerHTML = html;
        if (totalEl) totalEl.textContent = '₱' + total.toFixed(2);
    }

    window.changeQty = function(id, delta) {
        const item = cart.find(function(i) { return i.id === id; });
        if (!item) return;
        item.qty += delta;
        if (item.qty <= 0) cart = cart.filter(function(i) { return i.id !== id; });
        persistCart();
    };

    function toggleCart() {
        const drawer = document.getElementById('cartDrawer');
        const ovl    = document.getElementById('drawerOverlay');
        if (!drawer) return;
        const isOpen = drawer.classList.contains('active');
        drawer.classList.toggle('active', !isOpen);
        drawer.setAttribute('aria-hidden', isOpen ? 'true' : 'false');
        if (ovl) { ovl.classList.toggle('active', !isOpen); }
        document.body.style.overflow = isOpen ? '' : 'hidden';
    }
    window.toggleCart = toggleCart;

    const ovl = document.getElementById('drawerOverlay');
    if (ovl) ovl.addEventListener('click', function() { toggleCart(); });

    window.checkout = function() { window.location.href = '/mall/checkout'; };

    window.addToCartFromPage = function () {
        const existing = cart.find(function(i) { return i.id === PRODUCT.id; });
        if (existing) { existing.qty += qty; }
        else { cart.push(Object.assign({}, PRODUCT, {qty: qty})); }
        persistCart();
        toggleCart();
    };

    window.buyNowFromPage = function () {
        addToCartFromPage();
        window.location.href = '/mall/checkout';
    };

    function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    // Init
    renderCart();
    const badge = document.getElementById('cartBadge');
    if (badge) {
        const total = cart.reduce(function(s,i){ return s + (i.qty||1); }, 0);
        badge.textContent = total > 0 ? (total > 99 ? '99+' : total) : '';
        badge.style.display = total > 0 ? 'flex' : 'none';
    }

    // Header search behavior for standalone product page.
    var searchTrigger = document.getElementById('searchTrigger');
    var searchOverlay = document.getElementById('searchOverlay');
    var searchClose = document.getElementById('searchClose');
    var searchInput = document.getElementById('searchInput');
    var searchForm = document.getElementById('searchForm');

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
    if (searchClose) searchClose.addEventListener('click', closeSearch);

    if (searchForm) {
        searchForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var q = (searchInput && searchInput.value ? searchInput.value.trim() : '');
            if (!q) return;
            window.location.href = '/mall?q=' + encodeURIComponent(q);
        });
    }

    // Theme toggle behavior for standalone product page.
    var themeToggle = document.getElementById('themeToggle');
    var themeIcon = document.getElementById('themeIcon');

    function applyTheme(theme) {
        if (theme === 'light') {
            document.body.classList.add('light');
            if (themeIcon) themeIcon.textContent = '🌙';
        } else {
            document.body.classList.remove('light');
            if (themeIcon) themeIcon.textContent = '☀️';
        }
        localStorage.setItem('epower_theme', theme);
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            applyTheme(document.body.classList.contains('light') ? 'dark' : 'light');
        });
    }
    applyTheme(localStorage.getItem('epower_theme') || 'dark');

    // Mall notification panel behavior for standalone product page.
    var mallNotifyToggle = document.getElementById('mallNotifyToggle');
    var mallNotifyPanel = document.getElementById('mallNotifyPanel');
    var mallNotifyList = document.getElementById('mallNotifyList');
    var mallNotifyBadge = document.getElementById('mallNotifyBadge');

    function escHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function notifDate(v) {
        if (!v) return '';
        var d = new Date(v);
        if (Number.isNaN(d.getTime())) return String(v);
        return d.toLocaleString();
    }

    function notificationTarget(n) {
        if (!n || typeof n !== 'object') return '/mall/notifications';
        return n.link || n.action_url || n.product_link || n.buyer_link || '/mall/notifications';
    }

    function renderNotifications(list) {
        if (!mallNotifyList) return;
        if (!Array.isArray(list) || list.length === 0) {
            mallNotifyList.innerHTML =
                '<div style="padding:12px;border-radius:12px;background:var(--surface2);border:1px solid var(--border);font-size:0.8rem;color:var(--muted);">No mall notifications yet.</div>';
            return;
        }

        mallNotifyList.innerHTML = list.map(function (n) {
            var unread = Number(n.is_read || 0) === 0;
            return ''
                + '<a href="' + escHtml(notificationTarget(n)) + '"'
                + ' data-notif-id="' + escHtml(n.id || '') + '"'
                + ' style="display:block;padding:10px 12px;border-radius:12px;background:var(--surface2);border:1px solid ' + (unread ? 'rgba(59,130,246,.35)' : 'var(--border)') + ';text-decoration:none;color:inherit;">'
                + '  <div style="font-size:0.82rem;font-weight:600;line-height:1.35;">' + escHtml(n.message || '') + '</div>'
                + '  <div style="font-size:0.7rem;color:var(--muted);margin-top:4px;">' + escHtml(notifDate(n.created_at)) + '</div>'
                + '</a>';
        }).join('');

        mallNotifyList.querySelectorAll('[data-notif-id]').forEach(function (el) {
            el.addEventListener('click', function () {
                var id = this.getAttribute('data-notif-id');
                if (!id) return;
                var body = JSON.stringify({ ids: [Number(id)] });
                fetch('/api/mall/notifications/mark-read-app', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: body,
                    keepalive: true,
                    credentials: 'same-origin'
                }).catch(function () {});
                fetch('/api/mall/notifications/mark-read', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: [Number(id)], csrf_token: CSRF_TOKEN }),
                    keepalive: true,
                    credentials: 'same-origin'
                }).catch(function () {});
            });
        });
    }

    async function refreshNotifications() {
        if (!mallNotifyPanel) return;
        try {
            var res = await fetch('/api/mall/notifications?limit=8', { credentials: 'same-origin' });
            if (!res.ok) return;
            var json = await res.json();
            var list = Array.isArray(json) ? json : (Array.isArray(json.notifications) ? json.notifications : []);
            var unread = Number(json.unread_count != null ? json.unread_count : json.unreadCount);
            if (Number.isNaN(unread)) {
                unread = list.reduce(function (sum, n) { return sum + (Number(n.is_read || 0) === 0 ? 1 : 0); }, 0);
            }
            renderNotifications(list);
            if (mallNotifyBadge) {
                if (unread > 0) {
                    mallNotifyBadge.style.display = 'flex';
                    mallNotifyBadge.textContent = unread > 99 ? '99+' : String(unread);
                } else {
                    mallNotifyBadge.style.display = 'none';
                    mallNotifyBadge.textContent = '';
                }
            }
        } catch (e) {
            // Keep server-rendered notifications if API refresh fails.
        }
    }

    if (mallNotifyToggle && mallNotifyPanel) {
        mallNotifyToggle.addEventListener('click', function (e) {
            e.preventDefault();
            var show = mallNotifyPanel.style.display !== 'block';
            mallNotifyPanel.style.display = show ? 'block' : 'none';
            if (show) refreshNotifications();
        });

        document.addEventListener('click', function (e) {
            if (!mallNotifyPanel.contains(e.target) && !mallNotifyToggle.contains(e.target)) {
                mallNotifyPanel.style.display = 'none';
            }
        });

        refreshNotifications();
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && searchOverlay && searchOverlay.classList.contains('open')) {
            closeSearch();
        }
    });
}());
</script>

<?php include __DIR__ . '/parts/bottom_nav.php'; ?>
</body>
</html>
