<header class="sticky top-0 z-40 bg-transparent text-gray-900 dark:text-gray-100">
<?php
// Get admin user info for display
$adminDisplayName = 'Admin';
$adminInitials = 'AD';

if (!empty($_SESSION['user_id'])) {
    try {
        // Get database connection
        $db = \Ginto\Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT username, fullname, firstname, middlename, lastname FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $adminUser = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($adminUser) {
            // Build display name: prefer fullname, then firstname+lastname, then username
            if (!empty($adminUser['fullname'])) {
                $adminDisplayName = trim($adminUser['fullname']);
            } elseif (!empty($adminUser['firstname']) || !empty($adminUser['lastname'])) {
                $parts = array_filter([
                    $adminUser['firstname'] ?? '',
                    $adminUser['middlename'] ?? '',
                    $adminUser['lastname'] ?? ''
                ]);
                $adminDisplayName = trim(implode(' ', $parts)) ?: ($adminUser['username'] ?? 'Admin');
            } elseif (!empty($adminUser['username'])) {
                $adminDisplayName = $adminUser['username'];
            }
            
            // Build initials from display name
            $nameParts = preg_split('/\s+/', $adminDisplayName);
            if (count($nameParts) >= 2) {
                $adminInitials = strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1));
            } else {
                $adminInitials = strtoupper(substr($adminDisplayName, 0, 2));
            }
        }
    } catch (\Throwable $e) {
        // Silently use defaults
    }
}
// Store for use in body.php
$GLOBALS['adminDisplayName'] = $adminDisplayName;
$GLOBALS['adminInitials'] = $adminInitials;
?>
        <!-- Ensure every admin page loads the dark-mode fallback CSS and apply theme early -->
        <link rel="stylesheet" href="/assets/css/dark-fallback.css">
        <!-- Admin button safety override: make outline/admin buttons readable in light mode
                 This sits here so it applies early and clearly across admin pages even if other
                 styles are present. Use specificity + !important to beat soft panel styles. -->
        <style>
            html:not(.dark) .admin-btn {
                color: #0b1220 !important; /* strong readable text */
                background-color: #ffffff !important;
                border-color: rgba(15,23,42,0.14) !important;
                font-weight: 600 !important;
                box-shadow: 0 1px 0 rgba(2,6,23,0.035) !important;
            }
            html:not(.dark) .admin-btn:hover { background-color: #f7fafc !important; border-color: rgba(15,23,42,0.18) !important; }
        </style>
    <script>
        // Apply theme as early as possible inside the header so admin pages respect
        // the user's stored preference (localStorage / cookie) and avoid a flash.
        (function () {
            try {
                var saved = null;
                try { saved = localStorage.getItem('theme'); } catch (e) { saved = null; }
                if (!saved) {
                    var m = document.cookie.match(/(?:^|; )theme=(dark|light)(?:;|$)/);
                    saved = m ? m[1] : null;
                }
                if (saved === 'dark') {
                    document.documentElement.classList.add('dark');
                } else if (saved === 'light') {
                    document.documentElement.classList.remove('dark');
                }
            } catch (_) {}
        })();
    </script>
        <!-- Align header inner content with the main page content (which uses lg:pl-64) so the
            header controls line up with the right-hand page content when the sidebar is visible. -->
        <div class="flex items-center justify-between h-16 px-4 md:px-6 lg:px-8 lg:pl-64 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center space-x-4">
            <?php require_once __DIR__ . '/icon_helpers.php'; ?>
            <button id="menu-button" class="lg:hidden">
                <!-- Menu Icon -->
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 <?= activeIconClass('/admin', 'text-gray-300 dark:text-gray-400') ?>" <?= iconColorAttr('/admin') ?>>
                    <line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/>
                </svg>
            </button>
            <div class="relative hidden md:block">
                <!-- Search Icon -->
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400 dark:text-gray-300">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                </svg>
                 <input
                     type="text"
                     placeholder="Search..."
                     class="pl-10 pr-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-yellow-500 w-64"
                 />
            </div>
        </div>

        <div class="flex items-center space-x-3">
                <button id="theme-toggle" class="p-2 rounded-lg bg-transparent text-yellow-500 dark:bg-gray-800 dark:text-yellow-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:scale-110 transition-transform focus:outline-none focus:ring-2 focus:ring-yellow-400" aria-label="Toggle theme" title="Toggle dark/light mode">
                <!-- Sun/Moon Icon (dynamic) -->
                <span id="theme-toggle-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
                        <circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>
                    </svg>
                </span>
            </button>
                <button class="notification-btn p-2 rounded-lg bg-transparent dark:bg-transparent text-gray-700 dark:text-gray-200 relative border border-transparent dark:border-gray-300 shadow-none dark:shadow-sm hover:bg-gray-100 dark:hover:bg-transparent focus:outline-none focus:ring-2 focus:ring-yellow-400" aria-label="Notifications">
                <!-- Bell Icon -->
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 <?= activeIconClass('/admin/notifications', 'text-gray-700 dark:text-gray-200') ?>" <?= iconColorAttr('/admin/notifications') ?>>
                    <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.375 21a2 2 0 1 0 3.25 0"/>
                </svg>
                <span class="notification-badge absolute top-1 right-1 w-2 h-2 bg-red-500 border-2 border-white dark:border-transparent rounded-full shadow"></span>
            </button>
            <button class="p-2 rounded-lg bg-transparent dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-yellow-400">
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center text-white font-semibold text-sm">
                    <?= htmlspecialchars($adminInitials) ?>
                </div>
            </button>
        </div>
    </div>
    <!-- shared theme manager (keeps toggles & UI in sync) -->
    <script src="/assets/js/theme.js"></script>
    <!-- icon palette/styling manager (reads server/user settings and applies colors) -->
    <script src="/assets/js/admin-icon-color-picker.js"></script>
</header>
