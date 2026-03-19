<?php
// header.php — mall header partial
$isLoggedIn = !empty($_SESSION['user_id']);
$mallUnreadNotifications = isset($mall_unread_notifications) ? (int)$mall_unread_notifications : 0;
$mallNotifications = is_array($mall_notifications ?? null) ? $mall_notifications : [];
$mallWalletBalance = isset($mall_wallet_balance) ? (float)$mall_wallet_balance : 0.00;
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
        <a href="/" class="brand" aria-label="Ginto home">
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
            <a class="action-btn" href="/mall/wallet" aria-label="Open Ginto Wallet" title="Ginto Wallet balance: ₱<?= number_format($mallWalletBalance, 2) ?>" style="text-decoration:none;position:relative;display:inline-flex;align-items:center;justify-content:center;gap:6px;width:auto;padding:0 10px;min-width:42px;">
                <span aria-hidden="true">₱</span>
                <span style="font-size:0.75rem;font-weight:700;"><?= number_format($mallWalletBalance, 2) ?></span>
            </a>

            <a class="action-btn" href="/mall/orders" aria-label="Open mall orders" title="My Mall Orders" style="text-decoration:none;">
                <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 7h18"/>
                    <path d="M6 3h12l1 4H5l1-4z"/>
                    <rect x="4" y="7" width="16" height="13" rx="2"/>
                    <path d="M9 11h6M9 15h4"/>
                </svg>
            </a>

            <div style="position:relative;">
                <button class="action-btn" id="mallNotifyToggle" aria-label="Open mall notifications" title="Mall notifications">
                    <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5"/>
                        <path d="M9 17a3 3 0 006 0"/>
                    </svg>
                    <?php if ($mallUnreadNotifications > 0): ?>
                    <span class="cart-badge" id="mallNotifyBadge" style="display:flex;"><?= $mallUnreadNotifications > 99 ? '99+' : $mallUnreadNotifications ?></span>
                    <?php else: ?>
                    <span class="cart-badge" id="mallNotifyBadge" style="display:none;"></span>
                    <?php endif; ?>
                </button>
                <div id="mallNotifyPanel" style="display:none;position:absolute;right:0;top:calc(100% + 10px);width:320px;max-width:calc(100vw - 32px);background:var(--surface);border:1px solid var(--border);border-radius:16px;box-shadow:0 24px 50px rgba(0,0,0,0.24);padding:14px;z-index:1200;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px;">
                        <strong style="font-size:0.92rem;">Mall Notifications</strong>
                        <a href="/mall/orders" style="font-size:0.75rem;color:var(--accent);text-decoration:none;">View orders</a>
                    </div>
                    <div id="mallNotifyList" style="display:flex;flex-direction:column;gap:8px;max-height:300px;overflow:auto;">
                        <?php if (!empty($mallNotifications)): ?>
                            <?php foreach ($mallNotifications as $notification): ?>
                            <div style="padding:10px 12px;border-radius:12px;background:var(--surface2);border:1px solid var(--border);">
                                <div style="font-size:0.82rem;font-weight:600;"><?= htmlspecialchars($notification['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                <div style="font-size:0.7rem;color:var(--muted);margin-top:4px;"><?= htmlspecialchars($notification['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <div style="padding:12px;border-radius:12px;background:var(--surface2);border:1px solid var(--border);font-size:0.8rem;color:var(--muted);">
                            No mall notifications yet.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

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
