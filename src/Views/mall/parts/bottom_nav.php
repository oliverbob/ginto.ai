<?php
/**
 * Bottom navigation bar — Shopee-like (Home / Mall / Delivery / Me)
 *
 * Suppressed inside native Android/iOS WebView.
 */
$_ua_bn = $_SERVER['HTTP_USER_AGENT'] ?? '';
$_isAppWebViewBN =
    (isset($_GET['device']) && in_array($_GET['device'], ['smartphone', 'android', 'ios'], true))
    || str_contains($_ua_bn, 'GintoApp')
    || ((str_contains($_ua_bn, ' wv)') || str_contains($_ua_bn, '; wv)')) && stripos($_ua_bn, 'Android') !== false);
if ($_isAppWebViewBN) { return; }

$_bnUnread = isset($mall_unread_notifications) ? (int)$mall_unread_notifications : 0;
$_bnPage   = $_SERVER['REQUEST_URI'] ?? '';
$_bnActive = function(string $path) use ($_bnPage) {
    return str_starts_with(parse_url($_bnPage, PHP_URL_PATH) ?? '', $path) ? 'bn-active' : '';
};
?>
<style>
.bottom-nav{position:fixed;bottom:0;left:0;right:0;height:58px;background:var(--surface,#1e293b);border-top:1px solid var(--border,#334155);display:flex;align-items:center;justify-content:space-around;z-index:1100;padding:0 4px env(safe-area-inset-bottom,0)}
.bn-item{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;text-decoration:none;color:var(--muted,#94a3b8);font-size:.68rem;font-weight:500;padding:6px 12px;border-radius:8px;position:relative;transition:color .2s}
.bn-item:hover,.bn-item.bn-active{color:var(--accent,#3b82f6)}
.bn-item svg{width:22px;height:22px;stroke:currentColor;fill:none;stroke-width:1.8}
.bn-badge{position:absolute;top:1px;right:4px;min-width:16px;height:16px;background:#ef4444;color:#fff;font-size:.62rem;font-weight:700;border-radius:8px;display:flex;align-items:center;justify-content:center;padding:0 4px}
/* Ensure page content doesn't hide behind the bar */
body{padding-bottom:64px!important}
</style>

<nav class="bottom-nav" aria-label="Main navigation">
    <!-- Home -->
    <a class="bn-item <?= $_bnActive('/chat') ?: ($_bnPage === '/' ? 'bn-active' : '') ?>" href="/" aria-label="Home">
        <svg viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z"/></svg>
        Home
    </a>

    <!-- Mall -->
    <a class="bn-item <?= $_bnActive('/mall') && !str_starts_with(parse_url($_bnPage, PHP_URL_PATH) ?: '', '/mall/me') && !str_starts_with(parse_url($_bnPage, PHP_URL_PATH) ?: '', '/mall/orders') ? 'bn-active' : '' ?>" href="/mall" aria-label="Mall">
        <svg viewBox="0 0 24 24"><path d="M3 3h18l-2 13H5L3 3zm0 0l-1-1"/><circle cx="9" cy="20" r="1.5"/><circle cx="17" cy="20" r="1.5"/></svg>
        Mall
    </a>

    <!-- Delivery -->
    <a class="bn-item <?= $_bnActive('/mall/orders') ?>" href="/mall/orders" aria-label="Delivery">
        <svg viewBox="0 0 24 24"><rect x="1" y="6" width="15" height="12" rx="1"/><path d="M16 10h4l3 4v4h-7V10z"/><circle cx="6.5" cy="19.5" r="1.5"/><circle cx="19.5" cy="19.5" r="1.5"/></svg>
        Delivery
    </a>

    <!-- Me -->
    <a class="bn-item <?= $_bnActive('/mall/me') ?>" href="/mall/me" aria-label="Me">
        <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Me
        <?php if ($_bnUnread > 0): ?>
        <span class="bn-badge"><?= $_bnUnread > 99 ? '99+' : $_bnUnread ?></span>
        <?php endif; ?>
    </a>
</nav>
