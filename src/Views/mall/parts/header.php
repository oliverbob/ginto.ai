<?php
// header.php — mall header partial
$isLoggedIn = !empty($_SESSION['user_id']);
?>
<header class="site-header" role="banner">
    <div class="header-inner">

        <!-- Hamburger (mobile only — hidden on desktop via CSS) -->
        <button class="hamburger" id="menuToggle" aria-label="Open menu" aria-expanded="false" aria-controls="sidebar">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <line x1="4" y1="6" x2="20" y2="6"/>
                <line x1="4" y1="12" x2="20" y2="12"/>
                <line x1="4" y1="18" x2="20" y2="18"/>
            </svg>
        </button>

        <!-- Brand -->
        <a href="/marketplace" class="brand" aria-label="ePower Mall home">
            <img src="/assets/images/ginto.png" alt="Ginto">
            <span class="brand-name">ePower</span>
        </a>

        <!-- Search trigger icon -->
        <button class="search-trigger" id="searchTrigger" aria-label="Search products" aria-expanded="false" aria-controls="searchOverlay">
            <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
            </svg>
        </button>

        <!-- Search overlay (expands over header when open) -->
        <div class="search-overlay" id="searchOverlay" role="search" aria-label="Search products">
            <form class="search-form" id="searchForm">
                <input id="searchInput" type="search" placeholder="Search products…" aria-label="Search products" autocomplete="off">
                <button class="search-btn" type="submit" aria-label="Submit search">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                </button>
            </form>
            <button class="search-close" id="searchClose" aria-label="Close search">✕</button>
        </div>

        <!-- Actions -->
        <div class="header-actions" style="margin-left:auto">
            <?php if ($isLoggedIn): ?>
            <button class="action-btn" id="sellBtn" aria-label="Sell an item" title="Sell an item" onclick="openUploadModal()">
                <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                    <line x1="7" y1="7" x2="7.01" y2="7"/>
                </svg>
            </button>
            <?php endif; ?>

            <button class="action-btn" id="themeToggle" aria-label="Toggle theme">
                <span id="themeIcon" aria-hidden="true">☀️</span>
            </button>

            <button class="action-btn" id="cartToggle" aria-label="Open cart" onclick="toggleCart()">
                <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <path d="M16 10a4 4 0 01-8 0"/>
                </svg>
                <span class="cart-badge" id="cartBadge" aria-live="polite" aria-label="Cart items"></span>
            </button>
        </div>

    </div>
</header>
