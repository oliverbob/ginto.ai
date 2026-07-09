<?php
// gtb/gtb.php - Ginto Trading Bot (GTB) main view
// Follows the parts/ + pages/ structure documented in docs/routes.md

$isLoggedIn    = $isLoggedIn ?? false;
$isAdmin       = $isAdmin ?? false;
$username      = $username ?? null;
$userId        = $userId ?? null;
$userFullname  = $userFullname ?? null;
$page          = $page ?? 'home';
$apiConfigured = $apiConfigured ?? false;
$isTestnet     = $isTestnet ?? false;
$recentTrades  = $recentTrades ?? [];
$recentLogs    = $recentLogs ?? [];
$realizedPnl   = $realizedPnl ?? 0.0;
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<?php include __DIR__ . '/parts/head.php'; ?>
<body class="bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen transition-colors duration-200">
    <?php include __DIR__ . '/parts/header.php'; ?>
    <?php include __DIR__ . '/parts/content.php'; ?>
    <?php include __DIR__ . '/parts/footer.php'; ?>
</body>
</html>
