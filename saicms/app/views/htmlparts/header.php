<header class="bg-white dark:bg-dark-700 shadow-sm fixed top-0 left-0 right-0 z-30">
    <div class="max-w-6xl mx-auto px-4 py-2 flex items-center justify-between">
        <!-- Logo and Search -->
        <!-- MODIFIED: Added flex-grow md:flex-grow-0 for mobile expansion, and md:space-x-2 to apply spacing only on md+ -->
        <div class="flex items-center flex-grow md:flex-grow-0 md:space-x-2">
            <!-- MODIFIED: Added hidden md:block to hide logo on mobile -->
            <a href="/" class="hidden md:block text-facebook text-3xl font-bold" aria-label="Sai Home">
                <img src="/assets/favicon/apple-touch-icon.png" alt="Site Logo" class="w-8 h-8 rounded-md">
            </a>
            <!-- MODIFIED: Removed 'hidden md:block', added 'w-full md:w-auto' to make search visible and responsive -->
            <div class="relative w-full md:w-auto">
                <div class="relative">
                    <input type="text" id="searchInput" placeholder="Search Sai"
                        class="search-input bg-gray-100 dark:bg-dark-600 dark:text-white rounded-full py-2 px-4 pl-10 w-full md:w-64 focus:outline-none"
                        aria-label="Search Sai">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-500 dark:text-gray-400"></i>
                    <div id="searchDropdown" class="search-dropdown absolute hidden w-full mt-1 bg-white dark:bg-dark-700 rounded-lg shadow-lg z-50">
                        <div id="searchResults" class="search-results max-h-80 overflow-y-auto scrollbar-hide">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
<?php
$current_uri = $_SERVER['REQUEST_URI'];

// Ensure session active and compute user avatar/full name for header
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
// Read common session keys (prefer full name, then user, then username)
$sessionUser = isset($_SESSION['user']) ? trim((string)$_SESSION['user']) : '';
$sessionFullName = isset($_SESSION['user_full_name']) ? trim((string)$_SESSION['user_full_name']) : '';
$sessionUsername = isset($_SESSION['user_username']) ? trim((string)$_SESSION['user_username']) : '';
$header_fullName = $header_fullName ?? ($sessionFullName ?: ($sessionUser ?: ($sessionUsername ?: 'User')));
$header_initial = strtoupper(substr((string)$header_fullName, 0, 1));
$header_profilePicture = $header_profilePicture ?? (isset($_SESSION['user_profile_picture']) ? trim((string)$_SESSION['user_profile_picture']) : '');
if (!is_string($header_profilePicture) || trim($header_profilePicture) === '') {
    $svg_h = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64">'
           . '<circle cx="32" cy="32" r="32" fill="#4B5563"/>'
           . '<text x="50%" y="50%" text-anchor="middle" dy=".35em" font-family="Arial, sans-serif" font-size="28" fill="#ffffff">'
           . htmlspecialchars($header_initial)
           . '</text></svg>';
    $header_profilePicture = 'data:image/svg+xml;base64,' . base64_encode($svg_h);
}

// Define your navigation items
// 'match_type' can be 'exact' or 'prefix'
$nav_items = [
    ['href' => '/', 'label' => 'Home', 'icon' => 'fas fa-home', 'match_type' => 'exact'],
    ['href' => '/dashboard', 'label' => 'Watch', 'icon' => 'fas fa-tv', 'match_type' => 'prefix'],
    ['href' => '/marketplace', 'label' => 'Marketplace', 'icon' => 'fas fa-store', 'match_type' => 'prefix'],
    ['href' => '/groups', 'label' => 'Groups', 'icon' => 'fas fa-users', 'match_type' => 'prefix'],
    ['href' => '/gaming', 'label' => 'Gaming', 'icon' => 'fas fa-gamepad', 'match_type' => 'prefix'],
];

        $active_classes_str = "text-facebook border-b-2 border-facebook";
        $inactive_classes_str = "text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-600 rounded-lg";
        $base_padding_classes = "p-3 md:px-6";
        ?>

        <!-- Main Navigation (Static) -->
        <!-- MODIFIED: Added 'hidden md:flex' to hide nav on mobile -->
        <nav class="hidden md:flex space-x-1 md:space-x-2">
            <?php foreach ($nav_items as $item): ?>
                <?php
                $is_active = false;
                if ($item['match_type'] === 'exact' && $current_uri === $item['href']) {
                    $is_active = true;
                } elseif ($item['match_type'] === 'prefix' && $item['href'] !== '/' && strpos($current_uri, $item['href']) === 0) {
                    $is_active = true;
                } elseif ($item['match_type'] === 'prefix' && $item['href'] === '/' && $current_uri === '/') {
                    $is_active = true;
                }

                if ($item['href'] === '/' && $item['match_type'] === 'prefix' && $current_uri === '/') {
                    $home_is_exact_active = false;
                    foreach($nav_items as $check_item) {
                        if ($check_item['href'] === '/' && $check_item['match_type'] === 'exact' && $current_uri === $check_item['href']) {
                            $home_is_exact_active = true;
                            break;
                        }
                    }
                    if ($home_is_exact_active && $item['match_type'] === 'prefix') $is_active = false;
                }

                $link_classes = $base_padding_classes . " " . ($is_active ? $active_classes_str : $inactive_classes_str);
                $href_value = ($item['href'] === '#' && $item['label'] !== 'Home') ? '#' : htmlspecialchars($item['href']);
                ?>
                <a href="<?php echo $href_value; ?>" class="<?php echo $link_classes; ?>" aria-label="<?php echo htmlspecialchars($item['label']); ?>">
                    <i class="<?php echo htmlspecialchars($item['icon']); ?> text-xl"></i>
                </a>
            <?php endforeach; ?>
        </nav>
        
        <!-- User Menu - Placeholders will be filled by JS -->
        <div class="flex items-center space-x-2">
            <div class="relative">
                <button id="notificationBtn" class="p-2 bg-gray-200 dark:bg-dark-600 rounded-full hover:bg-gray-300 dark:hover:bg-dark-500 relative" aria-label="Notifications">
                    <i class="fas fa-bell text-gray-700 dark:text-gray-300"></i>
                    <!-- Unread count badge will be inserted here by JS if needed -->
                    <span id="notificationUnreadBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs w-4 h-4 rounded-full flex items-center justify-center hidden"></span>
                </button>
                <div id="notificationDropdown" class="notification-dropdown hidden absolute right-0 mt-2 w-72 md:w-80 bg-white dark:bg-dark-700 rounded-lg shadow-xl z-50">
                    <div class="p-3 border-b dark:border-dark-600 flex justify-between items-center">
                        <h3 class="font-semibold dark:text-white">Notifications</h3>
                        <button id="markAllNotificationsReadBtn" class="text-facebook text-sm hover:underline hidden">Mark all as read</button>
                    </div>
                    <div id="notificationListContainer" class="max-h-80 overflow-y-auto">
                        <div id="notificationEmptyState" class="p-4 text-center text-sm text-gray-500 dark:text-gray-400 hidden">
                            No notifications yet.
                        </div>
                        <div id="notificationLoadingState" class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Loading notifications...
                        </div>
                        <!-- Notification items are dynamically inserted here -->
                    </div>
                    <!-- **** ADD THIS ELEMENT **** -->
                    <div id="notificationLoadMoreSpinner" class="hidden text-center p-2">
                        <i class="fas fa-spinner fa-spin text-gray-500 dark:text-gray-400"></i>
                        <span class="text-sm text-gray-500 dark:text-gray-400 ml-1">Loading more...</span>
                    </div>
                    <!-- ************************** -->
                    <div id="notificationViewAllContainer" class="p-2 text-center border-t dark:border-dark-600 hidden">
                        <a href="/notifications" class="text-facebook text-sm hover:underline">View all notifications</a>
                    </div>
                </div>
            </div>
            
            <div class="relative">
                <button id="chatNotificationsBtn" class="p-2 bg-gray-200 dark:bg-dark-600 rounded-full hover:bg-gray-300 dark:hover:bg-dark-500" aria-label="Chats">
                    <i class="fas fa-comment-dots text-gray-700 dark:text-gray-300"></i>
                    <span id="globalChatUnreadBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs w-4 h-4 rounded-full flex items-center justify-center hidden">0</span>
                </button>
                <div id="chatNotificationsDropdown" class="chat-notifications-dropdown hidden absolute right-0 mt-2 w-72 md:w-80 bg-white dark:bg-dark-700 rounded-lg shadow-xl z-50">
                    <div class="p-3 border-b dark:border-dark-600 flex justify-between items-center">
                        <h3 class="font-semibold dark:text-white">Chats</h3>
                    </div>
                    <div id="chatNotificationList" class="max-h-80 overflow-y-auto scrollbar-hide">
                         <!-- Populated by chatmanager.js -->
                    </div>
                    <div id="chatNotificationEmptyState" class="p-4 text-center text-sm text-gray-500 dark:text-gray-400 hidden">
                        No active chats.
                    </div>
                </div>
            </div>
            
            <!-- MODIFIED: Added 'hidden md:block' to hide profile button container on mobile -->
            <div class="relative"> 
                <button id="userMenuBtn" class="flex items-center space-x-1 bg-gray-200 dark:bg-dark-600 rounded-full p-1 hover:bg-gray-300 dark:hover:bg-dark-500" aria-label="Account">
                    <!-- User avatar -->
                    <img id="userMenuAvatar" src="<?= htmlspecialchars($header_profilePicture) ?>" alt="<?= htmlspecialchars($header_fullName) ?>" class="w-8 h-8 rounded-full">
                </button>
                <div id="userDropdown" class="absolute right-0 mt-2 w-48 bg-white dark:bg-dark-700 rounded-md shadow-lg py-1 hidden z-50">
                    <a href="/" class="block px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-600">Home</a>
                    <a href="/sai/" class="block px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-600">Sai</a>
                    <a id="userDropdownProfileLink" href="/profile" class="block px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-600">Profile</a>
                    <a href="/dashboard" class="block px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-600">Dashboard</a>
                    <a href="/smartfi" class="block px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-600">SmartFi</a>
                    <a href="#" class="block px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-600">Settings</a>
                    <button id="darkModeToggle" class="block w-full text-left px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-600">
                        <span class="light-mode-text">Dark Mode</span>
                        <span class="dark-mode-text hidden">Light Mode</span>
                    </button>
                    <a href="/logout" class="block px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-dark-600">Logout</a>
                </div>
            </div>
        </div>
    </div>
</header>