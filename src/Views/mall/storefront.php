<?php
$isLoggedIn = !empty($_SESSION['user_id']);
$title = $title ?? (($storefront['display_name'] ?? 'Storefront') . ' - Ginto Mall');
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