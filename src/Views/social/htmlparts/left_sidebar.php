<?php
// Ensure session is started and user variables are available when this partial is included standalone.
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// Basic login detection and safe fallbacks
$isUserLoggedIn = false;
$session_user_id = $session_user_id ?? ($_SESSION['user_id'] ?? null);
if (!empty($session_user_id)) {
    $isUserLoggedIn = true;
}

$fullName = $fullName ?? ($_SESSION['user_full_name'] ?? ($_SESSION['user_username'] ?? ($_SESSION['user'] ?? 'User')));
// If session exists but no meaningful name, mark as guest
if (is_array($fullName)) $fullName = 'User';
$initial = strtoupper(substr((string)$fullName, 0, 1));

$profilePicture = $profilePicture ?? ($_SESSION['user_profile_picture'] ?? '');
// If profile picture is empty or contains only whitespace, treat as empty
if (!is_string($profilePicture) || trim($profilePicture) === '') {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32">'
         . '<circle cx="16" cy="16" r="16" fill="#4B5563"/>'
         . '<text x="50%" y="50%" text-anchor="middle" dy=".35em" font-family="Arial, sans-serif" font-size="16" fill="#ffffff">'
         . htmlspecialchars($initial)
         . '</text></svg>';
    $profilePicture = 'data:image/svg+xml;base64,' . base64_encode($svg);
}
?>

        <!-- Left Sidebar -->
        <aside class="hidden lg:block lg:col-span-2 pr-4 left-sidebar">
            <div class="sidebar-container sticky top-16 h-[calc(100vh-64px)] overflow-y-auto scrollbar-hide">
                <div class="space-y-2 mt-2">
                    <a href="/profile/<?= htmlspecialchars($session_user_id) ?>" class="flex items-center p-2 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-lg">
                        <img src="<?= $profilePicture ?>" alt="User" class="w-8 h-8 rounded-full">
                        <span class="ml-2 font-medium dark:text-white"><?= htmlspecialchars($fullName) ?></span>
                    </a>
                    <a href="/friends/<?= htmlspecialchars($_SESSION['user_id']) ?>" class="flex items-center p-2 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-dark-500 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <span class="ml-2 dark:text-white">Friends</span>
                    </a>
                    <a href="/code" class="flex items-center p-2 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-dark-500 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <i class="fas fa-code"></i>
                        </div>
                        <span class="ml-2 dark:text-white">Code</span>
                    </a>
                    <a href="/classroom" class="flex items-center p-2 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-dark-500 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <span class="ml-2 dark:text-white">Classroom</span>
                    </a>
                    <a href="/sai/" class="flex items-center p-2 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-dark-500 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <i class="fas fa-atom"></i>
                        </div>
                        <span class="ml-2 dark:text-white">Sai Chat</span>
                    </a>
                    <a href="/code" class="flex items-center p-2 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-dark-500 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <i class="fas fa-flag"></i>
                        </div>
                        <span class="ml-2 dark:text-white">Sai Business</span>
                    </a>
                    <a href="/social" class="flex items-center p-2 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-dark-500 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <i class="fas fa-save"></i>
                        </div>
                        <span class="ml-2 dark:text-white">Saved</span>
                    </a>
                    <a href="/downline" class="flex items-center p-2 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-dark-500 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <i class="fas fa-users"></i>
                        </div>
                        <span class="ml-2 dark:text-white">Network & Groups</span>
                    </a>
                    <a href="/" class="flex items-center p-2 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-dark-500 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <i class="fas fa-store"></i>
                        </div>
                        <span class="ml-2 dark:text-white">Sell on Sai</span>
                    </a>
                    <a href="/social" class="flex items-center p-2 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-dark-500 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <span class="ml-2 dark:text-white">Sai Events</span>
                    </a>
                    <a href="/social" class="flex items-center p-2 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-dark-500 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <i class="fas fa-bookmark"></i>
                        </div>
                        <span class="ml-2 dark:text-white">Saved</span>
                    </a>
                    <a href="/social" class="flex items-center p-2 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-lg">
                        <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-dark-500 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <span class="ml-2 dark:text-white">See More</span>
                    </a>
                </div>
                
                <div class="mt-4 pt-4 border-t border-gray-300 dark:border-dark-600">
                    <h3 class="font-semibold text-gray-500 dark:text-gray-400 px-2 mb-2">Your Shortcuts</h3>
                    <a href="/tier" class="flex items-center p-2 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-lg">
                        <img src="https://picsum.photos/200" alt="Group" class="w-8 h-8 rounded-md">
                        <span class="ml-2 dark:text-white">Why SmartFed?</span>
                    </a>
                    <a href="/tier" class="flex items-center p-2 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-lg">
                        <img src="https://picsum.photos/201" alt="Group" class="w-8 h-8 rounded-md">
                        <span class="ml-2 dark:text-white">SmartFed for Business</span>
                    </a>
                    <a href="/" class="flex items-center p-2 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-lg">
                        <img src="https://picsum.photos/202" alt="Group" class="w-8 h-8 rounded-md">
                        <span class="ml-2 dark:text-white">Claim your website!</span>
                    </a>
                </div>
            </div>
        </aside>