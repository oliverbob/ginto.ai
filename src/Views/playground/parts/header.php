<?php
/**
 * Playground - Header partial
 * Modern top navigation bar with branding, search, and user controls
 */
$currentUser = $_SESSION['username'] ?? $_SESSION['user_id'] ?? 'Developer';
$userAvatar = strtoupper(substr($currentUser, 0, 2));
?>
<header class="fixed top-0 left-0 right-0 h-14 bg-white/80 dark:bg-gray-900/80 backdrop-blur-xl border-b border-gray-200/50 dark:border-gray-700/50 z-40">
    <div class="flex items-center justify-between h-full px-4">
        <!-- Left section: Menu toggle + Branding -->
        <div class="flex items-center gap-4">
            <!-- Mobile menu toggle -->
            <button id="sidebar-toggle" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors lg:hidden" aria-label="Toggle sidebar">
                <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            
            <!-- Collapse toggle for desktop -->
            <button id="sidebar-collapse" class="hidden lg:flex p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" aria-label="Collapse sidebar">
                <svg class="w-5 h-5 text-gray-600 dark:text-gray-300 transition-transform" id="collapse-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                </svg>
            </button>
            
            <!-- Branding -->
            <a href="/playground" class="flex items-center gap-3 group">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center shadow-lg shadow-violet-500/25">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                    </svg>
                </div>
                <h1 class="hidden sm:block text-lg font-bold bg-gradient-to-r from-violet-600 to-purple-600 dark:from-violet-400 dark:to-purple-400 bg-clip-text text-transparent">
                    Playground 1.0-alpha
                </h1>
            </a>
        </div>
        
        <!-- Center section: Search -->
        <div class="hidden md:flex flex-1 max-w-md mx-8">
            <div class="relative w-full group">
                <input type="text" 
                       id="global-search"
                       placeholder="Search tools, docs, or press ⌘K..." 
                       class="w-full h-10 pl-10 pr-4 rounded-xl bg-gray-100 dark:bg-gray-800 border border-transparent focus:border-violet-500 dark:focus:border-violet-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-violet-500/20 text-sm text-gray-700 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-500 transition-all outline-none">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 group-focus-within:text-violet-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <kbd class="absolute right-3 top-1/2 -translate-y-1/2 hidden sm:inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-medium text-gray-400 bg-gray-200 dark:bg-gray-700 rounded">
                    ⌘K
                </kbd>
            </div>
        </div>
        
        <!-- Right section: Actions -->
        <div class="flex items-center gap-2">
            <!-- Theme toggle -->
            <button id="theme-toggle" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" aria-label="Toggle theme" title="Toggle theme (Light/Dark)">
                <svg id="theme-icon" class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <!-- Icon will be updated by JavaScript based on current theme -->
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                </svg>
            </button>
            
            <!-- Notifications -->
            <button class="relative p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" aria-label="Notifications">
                <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full"></span>
            </button>
            
            <!-- Back to main site -->
            <a href="/" class="hidden sm:flex items-center gap-2 px-3 py-1.5 text-sm text-gray-600 dark:text-gray-300 hover:text-violet-600 dark:hover:text-violet-400 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span>Back to Site</span>
            </a>
            
            <!-- User menu -->
            <div class="relative" id="user-menu-container">
                <button id="user-menu-btn" class="flex items-center gap-2 p-1 pr-3 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-400 to-cyan-500 flex items-center justify-center text-white text-xs font-bold shadow-lg shadow-emerald-500/25">
                        <?= htmlspecialchars($userAvatar) ?>
                    </div>
                    <span class="hidden sm:block text-sm font-medium text-gray-700 dark:text-gray-200"><?= htmlspecialchars($currentUser) ?></span>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                
                <!-- Dropdown menu -->
                <div id="user-dropdown" class="absolute right-0 top-full mt-2 w-56 py-2 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 opacity-0 invisible translate-y-2 transition-all duration-200">
                    <div class="px-4 py-2 border-b border-gray-100 dark:border-gray-700">
                        <p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($currentUser) ?></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Developer Mode</p>
                    </div>
                    <a href="/admin" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Admin Panel
                    </a>
                    <a href="/user" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        User Dashboard
                    </a>
                    <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>
                    <a href="/logout" class="flex items-center gap-3 px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Sign Out
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<?php
// Expose a small JS flag indicating whether the current session is an admin.
// This allows client-side scripts to conditionally render admin-only UI (like lock icons).
$isAdmin = (isset($_SESSION['role_id']) && in_array((int)$_SESSION['role_id'], [1,2], true));
?>
<script>
    window.GINTO_isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
</script>
