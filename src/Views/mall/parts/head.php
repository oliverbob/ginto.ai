<?php
$title       = $title       ?? 'Ginto Mall';
$ogTitle     = $ogTitle     ?? $title;
$ogDesc      = $ogDesc      ?? 'Discover products and sellers on Ginto Mall — the Filipino social commerce marketplace.';
$ogImage     = $ogImage     ?? '/assets/images/mall-og.png';
$ogType      = $ogType      ?? 'website';
$_proto      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host       = $_SERVER['HTTP_HOST'] ?? 'ginto.ai';
$ogUrl       = $ogUrl       ?? ($_proto . '://' . $_host . ($_SERVER['REQUEST_URI'] ?? '/'));
// Ensure absolute image URL for crawlers
if (!empty($ogImage) && !str_starts_with($ogImage, 'http')) {
    $ogImage = $_proto . '://' . $_host . $ogImage;
}
?>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($ogDesc) ?>">

    <!-- Open Graph —  Facebook, Messenger, Viber, LinkedIn, iMessage -->
    <meta property="og:type"        content="<?= htmlspecialchars($ogType) ?>">
    <meta property="og:url"         content="<?= htmlspecialchars($ogUrl) ?>">
    <meta property="og:title"       content="<?= htmlspecialchars($ogTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDesc) ?>">
    <meta property="og:image"       content="<?= htmlspecialchars($ogImage) ?>">
    <meta property="og:image:width"  content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name"   content="Ginto Mall">
    <meta property="og:locale"      content="en_PH">

    <!-- Twitter / X Card -->
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="<?= htmlspecialchars($ogTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($ogDesc) ?>">
    <meta name="twitter:image"       content="<?= htmlspecialchars($ogImage) ?>">

    <link rel="canonical" href="<?= htmlspecialchars($ogUrl) ?>">
    <link rel="icon" type="image/svg+xml" href="/assets/images/mall-favicon.svg">
    <link rel="shortcut icon" href="/assets/images/mall-favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
/* ========== RESET & CSS VARIABLES ========== */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ========== GLOBAL SCROLLBAR ========== */
/* Firefox */
* {
    scrollbar-width: thin;
    scrollbar-color: var(--border) transparent;
}
/* WebKit (Chrome, Edge, Safari) */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 10px;
    transition: background var(--trans);
}
::-webkit-scrollbar-thumb:hover {
    background: var(--muted);
}
::-webkit-scrollbar-corner {
    background: transparent;
}

:root {
    --bg:           #0f172a;
    --surface:      #1e293b;
    --surface2:     #263347;
    --text:         #f1f5f9;
    --muted:        #94a3b8;
    --border:       #2d3f55;
    --accent:       #3b82f6;
    --accent-h:     #2563eb;
    --gold:         #f59e0b;
    --danger:       #ef4444;
    --radius-sm:    8px;
    --radius:       12px;
    --header-h:     62px;
    --sidebar-w:    260px;
    --trans:        0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
body.light {
    --bg:           #f1f5f9;
    --surface:      #ffffff;
    --surface2:     #f8fafc;
    --text:         #0f172a;
    --muted:        #64748b;
    --border:       #e2e8f0;
}
html, body { height: 100%; }
body {
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    background: var(--bg);
    color: var(--text);
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
    transition: background var(--trans), color var(--trans);
}
a { color: inherit; text-decoration: none; }
button { cursor: pointer; font-family: inherit; border: none; outline: none; }
img { display: block; max-width: 100%; }

/* ========== HEADER ========== */
.site-header {
    position: sticky;
    top: 0;
    z-index: 1000;
    height: var(--header-h);
    background: rgba(15, 23, 42, 0.92);
    backdrop-filter: blur(14px);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    overflow: hidden;
}
body.light .site-header { background: rgba(255,255,255,0.92); }

.header-inner {
    position: relative;
    width: 100%;
    max-width: 1440px;
    margin: 0 auto;
    padding: 0 12px;
    display: flex;
    align-items: center;
    gap: 6px;
    overflow: hidden;
}

/* Hamburger (hidden on desktop, shown via media query) */
.hamburger {
    display: none;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    background: transparent;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--text);
    transition: background var(--trans);
}
.hamburger:hover { background: var(--surface); }

/* Sidebar toggle icon: hamburger lines by default (mobile), panel icon on desktop */
.icon-hamburger { display: block; }
.icon-sidebar-toggle { display: none; }

.brand {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    font-weight: 800;
    font-size: 1.1rem;
    color: var(--accent);
}
.brand img { width: 32px; height: 32px; border-radius: 50%; }
.brand-name { display: inline; }

/* Search — icon-only by default, expands into an overlay on click */
.search-trigger {
    width: 40px; height: 40px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    background: transparent;
    border: 1px solid transparent;
    border-radius: var(--radius-sm);
    color: var(--muted);
    cursor: pointer;
    transition: all var(--trans);
}
.search-trigger:hover { background: transparent; color: var(--text); border-color: transparent; }

/* Full-width expandable search bar that overlays the header */
.search-overlay {
    position: absolute;
    inset: 0;
    background: rgba(15,23,42,0.98);
    display: flex;
    align-items: center;
    padding: 0 12px;
    gap: 8px;
    opacity: 0;
    pointer-events: none;
    transform: scaleY(0.9);
    transform-origin: top;
    transition: opacity .18s ease, transform .18s ease;
    z-index: 10;
}
body.light .search-overlay { background: rgba(255,255,255,0.98); }
.search-overlay.open {
    opacity: 1;
    pointer-events: auto;
    transform: scaleY(1);
}
.search-overlay .search-form {
    flex: 1;
    display: flex;
    align-items: center;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 0 4px 0 14px;
    gap: 8px;
    transition: border-color var(--trans), box-shadow var(--trans);
}
.search-overlay .search-form:focus-within {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(59,130,246,0.2);
}
.search-overlay .search-form input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    color: var(--text);
    font-size: 0.95rem;
    padding: 9px 0;
    min-width: 0;
}
.search-overlay .search-form input::placeholder { color: var(--muted); }
.search-btn {
    width: 36px; height: 36px;
    display: flex; align-items: center; justify-content: center;
    background: transparent;
    color: var(--muted);
    border-radius: 20px;
    flex-shrink: 0;
    transition: color var(--trans);
}
.search-btn:hover { color: var(--text); }
.search-close {
    width: 40px; height: 40px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    background: transparent;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--muted);
    cursor: pointer;
    font-size: 1.1rem;
    transition: all var(--trans);
}
.search-close:hover { background: var(--surface); color: var(--text); }

.header-actions {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-left: auto;
    flex-shrink: 0;
    flex-wrap: nowrap;
    min-width: 0;
}
.wallet-btn {
    text-decoration: none;
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: auto;
    padding: 0 10px;
    min-width: 42px;
    color: #d4af37 !important;
}
.wallet-balance-text {
    font-size: 0.75rem;
    font-weight: 700;
    color: #d4af37;
}
.notify-wrap {
    position: relative;
    flex-shrink: 0;
}
.action-btn {
    position: relative;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: 1px solid transparent;
    border-radius: var(--radius-sm);
    color: var(--muted);
    font-size: 1rem;
    transition: all var(--trans);
}
.action-btn:hover { background: var(--surface); color: var(--text); border-color: var(--border); }
.cart-badge {
    position: absolute;
    top: 3px; right: 3px;
    min-width: 16px; height: 16px;
    background: var(--danger);
    color: white;
    font-size: 0.65rem;
    font-weight: 700;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 3px;
    border: 2px solid var(--bg);
    line-height: 1;
}
.cart-badge:empty { display: none; }

/* ========== PAGE LAYOUT ========== */
.page-layout {
    display: flex;
    max-width: 1440px;
    margin: 0 auto;
    min-height: calc(100vh - var(--header-h));
}

/* ========== SIDEBAR ========== */
.sidebar {
    width: var(--sidebar-w);
    flex-shrink: 0;
    padding: 0;
    border-right: 1px solid var(--border);
    position: sticky;
    top: var(--header-h);
    height: calc(100vh - var(--header-h));
    overflow-y: auto;
    background: var(--bg);
}

.sidebar-inner { padding: 20px 16px 32px; }

/* Close row: hidden on desktop, visible on mobile */
.sidebar-close-row {
    display: none;
    align-items: center;
    justify-content: space-between;
    height: var(--header-h);
    padding: 0 12px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.sidebar-close-logo { display: flex; align-items: center; gap: 8px; font-weight: 700; font-size: 1.05rem; color: var(--accent); }
.sidebar-close-logo img { width: 30px; height: 30px; border-radius: 50%; }
.sidebar-close-btn {
    width: 34px; height: 34px;
    display: flex; align-items: center; justify-content: center;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--text);
    font-size: 1rem;
    transition: background var(--trans);
}
.sidebar-close-btn:hover { background: var(--surface2); }

/* Fallback drawer injected by shared footer on pages without native sidebar markup */
.sidebar.fallback-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: 300px;
    height: 100%;
    padding: 0;
    z-index: 1002;
    border-right: 1px solid var(--border);
    transform: translateX(-100%);
    transition: transform var(--trans);
    box-shadow: 4px 0 24px rgba(0,0,0,0.3);
    overflow-y: auto;
    background: var(--bg);
}
.sidebar.fallback-sidebar.open { transform: translateX(0); }

/* Mobile overlay backdrop */
.sidebar-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(2, 6, 23, 0.65);
    z-index: 1001;
    backdrop-filter: blur(3px);
    opacity: 0;
    transition: opacity var(--trans);
    pointer-events: none;
}
.sidebar-backdrop.active { opacity: 1; pointer-events: auto; }

/* Sidebar sections */
.sidebar-section { margin-bottom: 26px; }
.sidebar-section-title {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--muted);
    font-weight: 700;
    margin-bottom: 8px;
    padding: 0 4px;
}
.cat-list { list-style: none; }
.cat-item {
    padding: 9px 12px;
    border-radius: var(--radius-sm);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.9rem;
    font-weight: 500;
    transition: background var(--trans), color var(--trans);
    color: var(--text);
}
.cat-item:hover { background: var(--surface); }
.cat-item.active { background: var(--accent); color: white; }
.cat-item svg { flex-shrink: 0; opacity: 0.7; }
.cat-item.active svg { opacity: 1; }

/* Filters */
.filter-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 4px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    user-select: none;
}
.filter-row input[type="checkbox"] {
    width: 17px; height: 17px;
    accent-color: var(--accent);
    cursor: pointer;
    flex-shrink: 0;
}

/* Promo cards */
.promo-section { display: flex; flex-direction: column; gap: 10px; }
.promo-card {
    position: relative;
    border-radius: var(--radius);
    overflow: hidden;
    min-height: 90px;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    transition: transform var(--trans), box-shadow var(--trans);
}
.promo-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(234,179,8,0.25); }
.promo-card img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.promo-card-overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.75) 0%, transparent 55%); }
.promo-card-label {
    position: relative;
    z-index: 1;
    padding: 8px 12px 10px;
    font-weight: 600;
    font-size: 0.85rem;
    color: white;
    display: flex;
    align-items: center;
    gap: 6px;
    text-shadow: 0 1px 4px rgba(0,0,0,0.6);
}
.promo-num { color: #fde047; font-weight: 900; font-size: 0.95rem; }

/* ========== MAIN CONTENT ========== */
.main-content {
    flex: 1;
    min-width: 0;
    padding: 24px 20px 96px; /* avoid bottom navbar overlap */
}
.main-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    gap: 12px;
    flex-wrap: wrap;
}
.results-text { font-size: 0.875rem; color: var(--muted); }
.results-text strong { color: var(--text); }
.toolbar-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 8px 16px;
    border-radius: var(--radius-sm);
    border: 1px solid transparent;
    font-size: 0.85rem;
    font-weight: 600;
    font-family: inherit;
    transition: all var(--trans);
    cursor: pointer;
    white-space: nowrap;
}
.btn-primary { background: var(--accent); color: white; }
.btn-primary:hover { background: var(--accent-h); }
.btn-secondary { background: var(--surface); color: var(--text); border-color: var(--border); }
.btn-secondary:hover { background: var(--surface2); }

/* Sort dropdown */
.sort-wrap { position: relative; }
.sort-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--text);
    font-size: 0.85rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    transition: border-color var(--trans);
}
.sort-btn:hover { border-color: var(--accent); }
.sort-dropdown {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    min-width: 190px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: 0 8px 24px rgba(0,0,0,0.25);
    z-index: 500;
    padding: 6px;
    list-style: none;
    display: none;
}
.sort-dropdown.open { display: block; }
.sort-dropdown li {
    padding: 9px 12px;
    border-radius: 6px;
    font-size: 0.875rem;
    cursor: pointer;
    font-weight: 500;
    transition: background var(--trans);
}
.sort-dropdown li:hover,
.sort-dropdown li[aria-selected="true"] { background: var(--accent); color: white; }

/* ========== PRODUCT GRID ========== */
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 18px;
}
.product-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform var(--trans), box-shadow var(--trans), border-color var(--trans);
    cursor: pointer;
}
.product-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    border-color: var(--accent);
}
.product-img-wrap {
    position: relative;
    padding-top: 72%;
    overflow: hidden;
    background: var(--surface2);
}
.product-img {
    position: absolute;
    inset: 0;
    width: 100%; height: 100%;
    object-fit: cover;
    transition: transform 0.45s ease;
}
.product-img-wrap:hover .product-img { transform: scale(1.08); }
/* multi-image indicator dot (bottom-right badge only) */
.card-multi-badge {
    position: absolute;
    bottom: 8px; right: 8px;
    background: rgba(0,0,0,0.55);
    color: #fff;
    font-size: 0.66rem; font-weight: 700;
    padding: 2px 7px;
    border-radius: 20px;
    pointer-events: none;
    z-index: 2;
    letter-spacing: 0.02em;
}
.product-badge {
    position: absolute;
    top: 10px; left: 10px;
    background: var(--accent);
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
}
.product-body {
    padding: 13px;
    display: flex;
    flex-direction: column;
    flex: 1;
    gap: 5px;
}
.product-title {
    font-size: 0.925rem;
    font-weight: 600;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.product-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.775rem;
    color: var(--muted);
}
.star-rating { color: #f59e0b; }
.product-price { font-size: 1.05rem; font-weight: 700; margin-top: auto; }
.product-add-btn {
    width: 100%;
    margin-top: 10px;
    padding: 8px;
    background: var(--accent);
    color: white;
    border-radius: var(--radius-sm);
    font-weight: 600;
    font-size: 0.85rem;
    font-family: inherit;
    transition: background var(--trans);
}
.product-add-btn:hover { background: var(--accent-h); }

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 72px 20px;
    color: var(--muted);
}
.empty-state-icon { font-size: 2.5rem; margin-bottom: 12px; }
.empty-state p { font-size: 0.95rem; }

/* ========== CART FAB ========== */
.cart-fab {
    position: fixed;
    right: 28px;
    z-index: 1100;
    bottom: calc(28px + 58px + env(safe-area-inset-bottom, 0));
    width: 62px;
    height: 62px;
    border-radius: 50%;
    background: transparent;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 20px rgba(0,0,0,0.35), 0 2px 8px rgba(0,0,0,0.25);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    padding: 0;
}

body.no-bottom-nav .cart-fab {
    bottom: calc(28px + env(safe-area-inset-bottom, 0));
}
.cart-fab-logo {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
    display: block;
    pointer-events: none;
    overflow: hidden;
}
.cart-fab:hover {
    transform: scale(1.08);
    box-shadow: 0 6px 28px rgba(0,0,0,0.45), 0 2px 12px rgba(0,0,0,0.3);
}
.cart-fab:active { transform: scale(0.93); }
.cart-fab-badge {
    position: absolute;
    top: -2px; right: -2px;
    min-width: 20px; height: 20px;
    background: var(--danger);
    color: #fff;
    font-size: 0.68rem;
    font-weight: 700;
    border-radius: 10px;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    border: 2px solid var(--bg);
    line-height: 1;
    pointer-events: none;
}
@keyframes cart-fab-pop {
    0%   { transform: scale(1); }
    40%  { transform: scale(1.2); }
    70%  { transform: scale(0.93); }
    100% { transform: scale(1); }
}
.cart-fab.pop { animation: cart-fab-pop 0.35s ease; }

/* ========== PRODUCT STORE LINK ========== */
.product-store-link {
    display: block;
    margin-top: 6px;
    font-size: 0.78rem;
    color: var(--accent);
    text-decoration: none;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: color var(--trans);
}
.product-store-link:hover { color: var(--accent-h); text-decoration: underline; }

/* ========== CART DRAWER ========== */
.drawer-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    z-index: 1200;
    opacity: 0;
    pointer-events: none;
    transition: opacity var(--trans);
}
.drawer-overlay.active { opacity: 1; pointer-events: auto; }

.cart-drawer {
    position: fixed;
    top: 0; right: 0; bottom: 0;
    width: min(400px, 100vw);
    background: var(--surface);
    border-left: 1px solid var(--border);
    z-index: 1201;
    display: flex;
    flex-direction: column;
    transform: translateX(100%);
    transition: transform var(--trans);
    box-shadow: -8px 0 32px rgba(0,0,0,0.25);
}
.cart-drawer.active { transform: translateX(0); }
.drawer-header {
    padding: 18px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}
.drawer-header h2 { font-size: 1.05rem; font-weight: 700; }
.drawer-body { flex: 1; overflow-y: auto; padding: 16px 20px; }
.drawer-footer {
    padding: 16px 20px;
    border-top: 1px solid var(--border);
    background: var(--bg);
    flex-shrink: 0;
}
.cart-total-row {
    display: flex;
    justify-content: space-between;
    font-weight: 700;
    margin-bottom: 12px;
    font-size: 0.95rem;
}
.cart-item {
    display: flex;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}
.cart-item:last-child { border-bottom: none; }
.cart-img {
    width: 62px; height: 62px;
    border-radius: 8px;
    object-fit: cover;
    flex-shrink: 0;
    background: var(--surface2);
}
.cart-info { flex: 1; min-width: 0; }
.cart-name { font-size: 0.85rem; font-weight: 600; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cart-price-line { font-size: 0.775rem; color: var(--muted); }
.cart-qty-row, .cart-item-qty-row { display: flex; align-items: center; gap: 8px; margin-top: 6px; }
.qty-btn {
    width: 30px; height: 30px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1rem;
    transition: background var(--trans);
    font-family: inherit;
}
.qty-btn:hover { background: var(--accent); color: white; border-color: var(--accent); }
.prod-qty-btn { width: 30px; height: 30px; font-size: 1rem; }
.cart-empty-msg { text-align: center; color: var(--muted); padding: 40px 0; font-size: 0.9rem; }

/* ========== QUICK VIEW MODAL ========== */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.7);
    z-index: 1300;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    opacity: 0;
    pointer-events: none;
    transition: opacity var(--trans);
}
.modal-overlay.active { opacity: 1; pointer-events: auto; }
.modal-box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    width: 100%;
    max-width: 860px;
    max-height: 90vh;
    overflow: hidden;
    display: grid;
    grid-template-columns: 1fr 1fr;
    transform: scale(0.95);
    transition: transform var(--trans);
}
.modal-overlay.active .modal-box { transform: scale(1); }
.modal-img-side {
    overflow: hidden;
    background: var(--surface2);
    display: flex;
    flex-direction: column;
}
/* QV main image wrap — carousel + zoom lens live here */
.qv-main-wrap {
    position: relative;
    flex: 1;
    min-height: 300px;
    overflow: hidden;
    cursor: crosshair;
    background: var(--surface2);
}
.qv-main-wrap img {
    width: 100%; height: 100%;
    object-fit: contain;
    display: block;
    min-height: 300px;
    user-select: none;
    -webkit-user-drag: none;
}
/* Carousel arrows inside QV */
.qv-arrow {
    position: absolute;
    top: 50%; transform: translateY(-50%);
    background: rgba(0,0,0,0.55);
    color: #fff;
    width: 36px; height: 36px;
    border-radius: 50%;
    border: none; outline: none;
    cursor: pointer;
    font-size: 1.6rem; line-height: 1;
    display: flex; align-items: center; justify-content: center;
    z-index: 5;
    opacity: 0;
    transition: background 0.15s, opacity 0.15s;
    font-family: inherit;
}
.qv-main-wrap:hover .qv-arrow { opacity: 1; }
.qv-arrow:hover { background: rgba(0,0,0,0.85); }
.qv-prev { left: 8px; }
.qv-next { right: 8px; }
/* Image counter badge */
.qv-counter {
    position: absolute;
    bottom: 9px; right: 10px;
    background: rgba(0,0,0,0.52);
    color: #fff;
    font-size: 0.7rem; font-weight: 600;
    padding: 3px 9px;
    border-radius: 20px;
    pointer-events: none;
    z-index: 4;
}
/* Zoom magnifier lens */
.qv-zoom-lens {
    display: none;
    position: absolute;
    width: 140px; height: 140px;
    border-radius: 50%;
    border: 2.5px solid rgba(255,255,255,0.9);
    box-shadow: 0 0 0 1px rgba(0,0,0,0.2), 0 4px 24px rgba(0,0,0,0.45);
    pointer-events: none;
    z-index: 10;
    background-repeat: no-repeat;
    background-color: #000;
    transform: translate(-50%, -50%);
}
.qv-main-wrap:hover .qv-zoom-lens { display: block; }
/* Thumbnail strip */
.qv-thumbs {
    display: flex;
    gap: 6px;
    padding: 8px;
    overflow-x: auto;
    background: var(--surface2);
    border-top: 1px solid var(--border);
    scrollbar-width: thin;
    scrollbar-color: var(--border) transparent;
    flex-shrink: 0;
}
.qv-thumb {
    width: 52px; height: 52px;
    border-radius: 6px;
    object-fit: cover;
    cursor: pointer;
    border: 2px solid transparent;
    flex-shrink: 0;
    transition: border-color 0.15s, opacity 0.15s;
    opacity: 0.55;
    user-select: none;
}
.qv-thumb.active { border-color: var(--accent); opacity: 1; }
.qv-thumb:hover  { opacity: 1; }
.modal-info-side {
    padding: 28px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    overflow-y: auto;
}
.modal-close {
    position: absolute;
    background: transparent;
    border: none;
    font-size: 1.2rem;
    color: var(--muted);
    padding: 4px;
    cursor: pointer;
}

/* ========== UPLOAD / SELL MODAL ========== */
.upload-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.7);
    z-index: 1300;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    opacity: 0;
    pointer-events: none;
    transition: opacity var(--trans);
}
.upload-overlay.active { opacity: 1; pointer-events: auto; }
.upload-modal {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    width: 100%;
    max-width: 540px;
    max-height: 92vh;
    overflow-y: auto;
    padding: 26px;
    transform: scale(0.95);
    transition: transform var(--trans);
}
.upload-overlay.active .upload-modal { transform: scale(1); }

/* ========== FORM STYLES ========== */
.form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; }
.form-label { font-size: 0.8rem; font-weight: 600; color: var(--muted); }
.form-input {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 10px 12px;
    color: var(--text);
    font-size: 0.9rem;
    font-family: inherit;
    width: 100%;
    outline: none;
    transition: border-color var(--trans);
}
.form-input:focus { border-color: var(--accent); }
textarea.form-input { resize: vertical; min-height: 80px; }
select.form-input { cursor: pointer; }
input[type="file"].form-input { padding: 7px 12px; }

/* ========== TOAST ========== */
.toast-container {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 1500;
    display: flex;
    flex-direction: column;
    gap: 8px;
    pointer-events: none;
    max-width: 90vw;
}
.toast {
    background: var(--surface);
    color: var(--text);
    border: 1px solid var(--border);
    border-left: 4px solid var(--accent);
    border-radius: var(--radius-sm);
    padding: 14px 20px;
    font-size: 0.9rem;
    box-shadow: 0 8px 32px rgba(0,0,0,0.4);
    animation: toastPop 0.3s ease forwards;
    pointer-events: all;
    max-width: 340px;
    word-break: break-word;
    text-align: center;
}
.toast.toast-error { border-left-color: var(--danger); }
@keyframes toastPop {
    from { transform: scale(0.9); opacity: 0; }
    to   { transform: scale(1);   opacity: 1; }
}

/* ========== MOBILE <= 767px ========== */
@media (max-width: 767px) {
    :root { --header-h: 56px; }

    .site-header { overflow: hidden; }
    .header-inner { gap: 4px; padding: 0 8px; }

    /* Hamburger visible on mobile */
    .hamburger { display: flex; }

    /* Brand name hidden on small screens */
    .brand-name { display: none; }

    /* Page layout stack vertically (sidebar is overlay, not in flex row) */
    .page-layout { display: block; }

    /* Sidebar becomes a fixed overlay panel */
    .sidebar {
        position: fixed;
        top: 0; left: 0; bottom: 0;
        width: 300px;
        height: 100%;
        padding: 0;
        z-index: 1002;
        border-right: 1px solid var(--border);
        transform: translateX(-100%);
        transition: transform var(--trans);
        box-shadow: 4px 0 24px rgba(0,0,0,0.3);
        overflow-y: auto;
    }
    .sidebar.open { transform: translateX(0); }

    .sidebar.fallback-sidebar {
        display: block;
        width: 300px;
    }

    /* Show backdrop */
    .sidebar-backdrop { display: block; }

    /* Show the close row inside sidebar */
    .sidebar-close-row { display: flex; }

    .main-content { padding: 14px 10px 24px; }

    .product-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
    .product-title { font-size: 0.825rem; }
    .product-price { font-size: 0.95rem; }
    .product-body { padding: 10px; gap: 4px; }
    .product-add-btn { padding: 7px; font-size: 0.8rem; margin-top: 8px; }
    .product-badge { font-size: 0.65rem; padding: 2px 6px; }

    /* Modal stacks vertically */
    .modal-box {
        grid-template-columns: 1fr;
        overflow-y: auto;
    }
    .qv-main-wrap { min-height: 240px; }
    .qv-main-wrap img { min-height: 240px; }

    .main-toolbar { gap: 8px; }
}

/* ========== ULTRA-NARROW MOBILE <= 390px ========== */
@media (max-width: 390px) {
    .wallet-balance-text { display: none; }
    .wallet-btn { padding: 0 6px; min-width: 34px; }
    .header-actions { gap: 2px; }
    .action-btn, .hamburger, .search-trigger { width: 34px; height: 34px; }
}

/* ========== ULTRA-NARROW MOBILE <= 360px ========== */
@media (max-width: 360px) {
    .header-inner {
        padding: 0 6px;
        gap: 2px;
    }

    .brand img {
        width: 26px;
        height: 26px;
    }

    .search-trigger,
    .action-btn,
    .hamburger {
        width: 32px;
        height: 32px;
    }

    .header-actions { gap: 1px; }

    .wallet-balance-text { display: none; }

    #sellBtn {
        display: none;
    }
}

/* ========== EXTREME NARROW <= 320px ========== */
@media (max-width: 320px) {
    #themeToggle {
        display: none;
    }
}

/* ========== DESKTOP >= 768px ========== */
@media (min-width: 768px) {
    /* Panel icon on desktop, hamburger lines hidden */
    .icon-hamburger { display: none; }
    .icon-sidebar-toggle { display: block; }

    /* Hamburger button invisible on desktop unless page has fallback sidebar */
    .hamburger { display: none; }
    body.has-fallback-sidebar .hamburger { display: flex !important; }

    /* Close row hidden by default on desktop; show for fallback sidebar (provides logo branding) */
    .sidebar-close-row { display: none !important; }
    body.has-fallback-sidebar .sidebar-close-row { display: flex !important; }

    /* Backdrop never needed on desktop */
    .sidebar-backdrop { display: none !important; }

    /* Fallback sidebar: full-height Claude-style column, desktop-visible and toggleable */
    .sidebar.fallback-sidebar {
        display: block !important;
        top: 0;
        height: 100vh;
        transform: translateX(-100%);
        z-index: 999;
    }
    body.has-fallback-sidebar.fallback-sidebar-open .sidebar.fallback-sidebar {
        transform: translateX(0);
    }

    /* Header moves right when sidebar opens (Claude-style) */
    body.has-fallback-sidebar .site-header {
        transition: margin-left var(--trans);
    }
    body.has-fallback-sidebar.fallback-sidebar-open .site-header {
        margin-left: 300px;
    }

    /* Content also moves right */
    body.has-fallback-sidebar .wallet-layout,
    body.has-fallback-sidebar .wallet-stats-row,
    body.has-fallback-sidebar .page-layout,
    body.has-fallback-sidebar .mall-content-push,
    body.has-fallback-sidebar .wpage-wrap,
    body.has-fallback-sidebar section[style*="max-width"],
    body.has-fallback-sidebar .checkout-layout {
        transition: margin-left var(--trans);
    }
    body.has-fallback-sidebar.fallback-sidebar-open .wallet-layout,
    body.has-fallback-sidebar.fallback-sidebar-open .wallet-stats-row,
    body.has-fallback-sidebar.fallback-sidebar-open .page-layout,
    body.has-fallback-sidebar.fallback-sidebar-open .mall-content-push,
    body.has-fallback-sidebar.fallback-sidebar-open .wpage-wrap,
    body.has-fallback-sidebar.fallback-sidebar-open section[style*="max-width"],
    body.has-fallback-sidebar.fallback-sidebar-open .checkout-layout {
        margin-left: 300px;
    }
}
    </style>

    <!-- Service Worker + Web Push registration -->
    <?php
    $__csrfForSw = (function_exists('generateCsrfToken') ? generateCsrfToken() : ($_SESSION['csrf_token'] ?? ''));
    $__loggedIn  = !empty($_SESSION['user_id']) ? '1' : '0';
    ?>
    <meta name="csrf-token" content="<?= htmlspecialchars($__csrfForSw, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="mall-li"    content="<?= $__loggedIn ?>">
    <script>
    (function () {
        if (!('serviceWorker' in navigator)) return;
        navigator.serviceWorker.register('/mall-push-sw.js', { scope: '/mall/' })
            .then(function (reg) {
                window._mallSWReg = reg;
            }).catch(function () {});

        async function subscribePush(csrfToken) {
            if (!window._mallSWReg) return;
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') return;
            try {
                // Fetch VAPID public key
                const res  = await fetch('/api/mall/push/vapid-public-key');
                const json = await res.json();
                if (!json.public_key) return;
                // Convert base64url to Uint8Array
                const b64  = json.public_key.replace(/-/g, '+').replace(/_/g, '/');
                const raw  = atob(b64);
                const key  = new Uint8Array(raw.length);
                for (let i = 0; i < raw.length; i++) key[i] = raw.charCodeAt(i);
                const sub = await window._mallSWReg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: key
                });
                const subJson = sub.toJSON();
                await fetch('/api/mall/push/subscribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        endpoint: subJson.endpoint,
                        p256dh:   subJson.keys.p256dh,
                        auth:     subJson.keys.auth,
                        scope:    'mall'
                    })
                });
            } catch (_) {}
        }

        window._mallSubscribePush = subscribePush;
        // Auto-subscribe if user is logged-in (signalled by data attribute on body)
        document.addEventListener('DOMContentLoaded', function () {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const liMeta   = document.querySelector('meta[name="mall-li"]');
            if (csrfMeta && liMeta && liMeta.content === '1') {
                window._mallSubscribePush(csrfMeta.content);
            }
        });
    })();
    </script>
</head>
