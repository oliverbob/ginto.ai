<?php
// header.php - marketplace header partial
// Expected variables: $isLoggedIn (optional)
$isLoggedIn = $isLoggedIn ?? (!empty($_SESSION['user_id']));
?>
<header class="header">
    <div class="container header-inner shopee-header">
        <div class="header-left">
            <button class="mobile-toggle" aria-label="Toggle Menu" onclick="toggleSidebar()">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
            </button>
            <a href="#" class="brand">
                <img src="/assets/images/ginto.png" alt="ginto.ai" style="height:32px;width:auto;display:block;" />
                <span class="brand-text">
                    <svg class="brand-lightning" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;margin-right:4px;"><path d="M11.5 1L3 11.5H9L8.5 19L17 8.5H11L11.5 1Z" fill="#facc15" stroke="#eab308" stroke-width="1.2" stroke-linejoin="round"/></svg>
                    ePower
                </span>
            </a>
        </div>
        <form class="search-bar shopee-search" id="searchContainer" onsubmit="event.preventDefault();">
            <div class="input-group">
                <input id="searchInput" type="text" placeholder="Search products..." aria-label="Search">
            </div>
            <button type="submit" class="search-btn" aria-label="Search">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </button>
        </form>
        <div class="sortby-wrap">
            <button id="sortByBtn" class="sortby-btn" aria-haspopup="listbox" aria-expanded="false" type="button">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle"><path d="M3 18h6M3 6h18M3 12h12"/></svg>
                <span class="sortby-label">Sort By</span>
                <span class="chev">▾</span>
            </button>
            <ul id="sortByDropdown" class="sortby-dropdown" role="listbox" tabindex="-1" style="display:none">
                <li role="option" data-value="default" aria-selected="true">Sort By</li>
                <li role="option" data-value="price_asc">Price: Low to High</li>
                <li role="option" data-value="price_desc">Price: High to Low</li>
                <li role="option" data-value="rating">Highest Rated</li>
            </ul>
        </div>
        <div class="header-actions">
            <?php if ($isLoggedIn): ?>
            <button class="icon-btn" id="sellBtn" aria-label="Sell an item" onclick="openUploadModal()" title="Sell an item">🏷️</button>
            <?php endif; ?>
            <button class="icon-btn" id="themeToggle" aria-label="Toggle Theme">
                <span id="themeIcon">☀️</span>
            </button>
            <button class="icon-btn" onclick="toggleCart()" aria-label="Open Cart">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 110-4 2 2 0 010 4z"/></svg>
                <span class="badge-count" id="cartCount">0</span>
            </button>
        </div>
    </div>
</header>