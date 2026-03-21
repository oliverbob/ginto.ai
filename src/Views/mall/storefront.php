<?php
$isLoggedIn  = !empty($_SESSION['user_id']);
$_sfName     = $storefront['display_name'] ?? 'Storefront';
$_sfSlug     = $storefront['slug'] ?? '';
$_sfDesc     = $storefront['description'] ?? '';
$_sfBanner   = $storefront['banner_image'] ?? '';
$_sfLogo     = $storefront['logo_image']   ?? '';

$title   = $title ?? ($_sfName . ' — Official Store on Ginto Mall');
$ogTitle = $_sfName . ' — Official Store on Ginto Mall';
$ogDesc  = !empty($_sfDesc)
    ? mb_strimwidth(strip_tags($_sfDesc), 0, 160, '…')
    : 'Browse products from ' . $_sfName . ' on Ginto Mall — the Filipino social commerce marketplace.';
// Banner preferred (wide), then logo, then default
$ogImage = !empty($_sfBanner) ? $_sfBanner : (!empty($_sfLogo) ? $_sfLogo : '/assets/images/mall-og.png');
$ogType  = 'website';
$_proto  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host   = $_SERVER['HTTP_HOST'] ?? 'ginto.ai';
$ogUrl   = $_proto . '://' . $_host . '/mall/' . rawurlencode($_sfSlug);

$page = 'storefront';
?>
<!doctype html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<body>
    <?php include __DIR__ . '/parts/header.php'; ?>
    <?php include __DIR__ . '/parts/content.php'; ?>
    <?php include __DIR__ . '/parts/footer.php'; ?>
</body>
</html>