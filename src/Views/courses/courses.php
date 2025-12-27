<?php
// courses/courses.php - Courses listing page
// Uses parts structure as per docs/routes.md

$isLoggedIn = $isLoggedIn ?? false;
$isAdmin = $isAdmin ?? false;
$username = $username ?? null;
$userId = $userId ?? null;
$userFullname = $userFullname ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<body class="bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen transition-colors duration-200" x-data="{ sidebarCollapsed: false, darkMode: true }" x-init="darkMode = document.documentElement.classList.contains('dark')">
    <?php include __DIR__ . '/parts/sidebar.php'; ?>
    
    <!-- Main Content Area -->
    <div class="main-content transition-all duration-300" :class="{ 'collapsed': sidebarCollapsed }">
        <?php include __DIR__ . '/parts/header.php'; ?>
        <?php include __DIR__ . '/parts/content.php'; ?>
        <?php include __DIR__ . '/parts/footer.php'; ?>
    </div>
</body>
</html>
