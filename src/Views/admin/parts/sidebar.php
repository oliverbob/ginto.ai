<?php
// Determine current request path so we can highlight active links and open sections
$currentPath = '/';
if (!empty($_SERVER['REQUEST_URI'])) {
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
}

// Helper closures for active state
$isActive = function($prefixes) use ($currentPath) {
    foreach ((array)$prefixes as $p) {
        if (!is_string($p)) continue;
        if ($p === '/') {
            if (is_string($currentPath) && $currentPath === '/') return true;
        } else {
            if (is_string($currentPath) && str_starts_with($currentPath, $p)) return true;
        }
    }
    return false;
};

// Whether the 'Content' section should be open (pages, posts, categories, tags)
$contentOpen = $isActive(['/admin/pages', '/admin/pages/routes', '/admin/posts', '/admin/categories', '/admin/tags']);

// CSS helper for active link styling
$activeClass = function($path) use ($currentPath) {
    // add a visibly distinctive left border + stronger text color in dark mode
    if (is_string($currentPath) && is_string($path) && str_starts_with($currentPath, $path)) {
        return 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white border-l-4 border-yellow-400';
    }
    return '';
};

// Use a shared icon helper so SVG / FA icons get a colored default and a configurable active color
    // admin-local helper is in the same folder (keeps admin customisations isolated)
    require_once __DIR__ . '/icon_helpers.php';

    // Small local wrappers: prefer the admin helper functions if available, otherwise
    // fall back to a conservative mapping so inactive icons get their default colors.
    if (!function_exists('sidebar_icon_class')) {
        function sidebar_icon_class(string $path) : string
        {
            if (function_exists('iconClassForAdmin')) {
                return iconClassForAdmin($path);
            }

            // Conservative fallback mapping (keeps names in-sync with admin helper defaults)
            $defaults = [
                '/admin' => 'text-sky-500 dark:text-sky-400',
                '/dashboard' => 'text-yellow-600 dark:text-yellow-300',
                '/admin/pages' => 'text-sky-500 dark:text-sky-400',
                '/admin/pages/routes' => 'text-sky-500 dark:text-sky-400',
                '/admin/posts' => 'text-blue-500 dark:text-blue-400',
                '/admin/categories' => 'text-indigo-500 dark:text-indigo-400',
                '/admin/tags' => 'text-pink-500 dark:text-pink-400',
                '/admin/media' => 'text-violet-500 dark:text-violet-400',
                '/admin/users' => 'text-green-500 dark:text-green-400',
                '/admin/menus' => 'text-emerald-500 dark:text-emerald-400',
                '/admin/themes' => 'text-cyan-500 dark:text-cyan-400',
                '/admin/plugins' => 'text-fuchsia-500 dark:text-fuchsia-400',
                '/admin/settings' => 'text-teal-500 dark:text-teal-400',
                '/logout' => 'text-red-500 dark:text-red-400',
            ];

            // prefer exact or longest-match
            $best = '';
            foreach ($defaults as $k => $v) {
                if (!is_string($k) || !is_string($path)) continue;
                if (str_starts_with($path, $k) && strlen($k) > strlen($best)) {
                    $best = $k;
                }
            }

            $svgBase = ' stroke-current fill-current';
            if ($best !== '') return $defaults[$best] . $svgBase;
            return 'text-gray-500 dark:text-gray-400' . $svgBase;
        }
    }

    if (!function_exists('sidebar_icon_style')) {
        function sidebar_icon_style(string $path, ?string $fallbackHex = null) : string
        {
            if (function_exists('iconStyleForAdmin')) {
                return iconStyleForAdmin($path, $fallbackHex);
            }

            $lookup = [
                '/admin' => '#0ea5e9',
                '/dashboard' => '#d97706',
                '/admin/pages' => '#0ea5e9',
                '/admin/pages/routes' => '#0ea5e9',
                '/admin/posts' => '#3b82f6',
                '/admin/categories' => '#6366f1',
                '/admin/tags' => '#ec4899',
                '/admin/media' => '#8b5cf6',
                '/admin/users' => '#16a34a',
                '/admin/menus' => '#10b981',
                '/admin/themes' => '#06b6d4',
                '/admin/plugins' => '#d946ef',
                '/admin/settings' => '#14b8a6',
                '/logout' => '#ef4444',
            ];

            $best = '';
            foreach ($lookup as $k => $v) {
                if (!is_string($k) || !is_string($path)) continue;
                if (str_starts_with($path, $k) && strlen($k) > strlen($best)) {
                    $best = $k;
                }
            }

            if ($best !== '') return 'style="color: ' . $lookup[$best] . ';"';
            if ($fallbackHex !== null) return 'style="color: ' . $fallbackHex . ';"';
            return 'style="color: #6b7280;"';
        }
    }
?>
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 transform transition-transform duration-300 bg-white dark:bg-gray-800 -translate-x-full lg:translate-x-0 flex flex-col h-screen">
        <!-- Use fixed header height here so sidebar header and top controls align exactly with the
            top app header (h-16) across large screens. This keeps both components visually in-line. -->
        <div class="flex items-center justify-between h-16 px-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center space-x-2">
            <div class="w-8 h-8 rounded-md overflow-hidden bg-transparent flex items-center justify-center transition-all duration-200">
                <!-- Use the brand logo image (keeps visuals consistent when collapsed) -->
                <img src="/assets/images/ginto.png" alt="Ginto" class="w-7 h-7 object-contain"/>
            </div>
            <span class="font-bold text-xl text-gray-900 dark:text-white sidebar-title transition-all duration-200 ease-out">Ginto Admin</span>
        </div>
        <!-- desktop collapse/expand button — shows on large screens and toggles the compact sidebar -->
        <button id="sidebar-collapse-button" class="hidden lg:inline-flex items-center justify-center w-9 h-9 rounded-md themed-text-secondary themed-hover transition-all" aria-label="Collapse sidebar" title="Collapse sidebar">
            <svg id="sidebar-collapse-icon" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 transform transition-transform themed-text-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        </button>

        <button id="close-button" class="lg:hidden">
            <!-- X Icon -->
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-gray-400">
                <path d="M18 6 6 18"/>
                <path d="m6 6 12 12"/>
            </svg>
        </button>
    </div>
    <nav class="flex-1 px-2 space-y-0.5 overflow-y-auto min-h-0 pb-6 pr-2">
        <!-- CMS: Admin Dashboard (content overview) -->
        <a href="/admin" class="w-full flex items-center space-x-3 px-4 py-1 transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 <?= $activeClass('/admin') ?> <?= activeIconClass('/admin') ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 <?= iconClassForAdmin('/admin') ?>" <?= iconStyleForAdmin('/admin') ?>>
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            <span class="font-medium">CMS Dashboard</span>
        </a>
        
        <!-- Playground - Developer Tools -->
        <a href="/playground" class="w-full flex items-center space-x-3 px-4 py-1 transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 <?= $activeClass('/playground') ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5" style="color: #8b5cf6;">
                <path d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
            </svg>
            <span class="font-medium" style="color: #8b5cf6;">Playground</span>
        </a>
        
        <!-- CMS: Main Dashboard -->
        <a href="/dashboard" class="w-full flex items-center space-x-3 px-4 py-1 transition-all bg-gradient-to-r from-yellow-400 to-yellow-500 text-white shadow-lg <?= $isActive(['/dashboard']) ? 'active-dashboard' : '' ?> <?= activeIconClass('/dashboard') ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 <?= iconClassForAdmin('/dashboard') ?>" <?= iconStyleForAdmin('/dashboard') ?>>
                <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            <span class="font-medium">Network Dashboard!</span>
        </a>
        
        <!-- CMS: Content Group -->
        <div id="content-group" class="space-y-0.5">
            <button id="content-toggle" class="w-full flex items-center justify-between space-x-3 px-4 py-1 transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                <span class="flex items-center space-x-3"><svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg><span class="font-medium">Content</span></span>
                <svg xmlns="http://www.w3.org/2000/svg" id="content-chevron" class="w-4 h-4 transform transition-transform <?= $contentOpen ? 'rotate-180' : '' ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div id="content-sub" class="pl-10 space-y-0.5 <?= $contentOpen ? '' : 'hidden' ?>">
                <a href="/admin/pages" class="w-full flex items-center space-x-3 px-4 py-1 transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 <?= $activeClass('/admin/pages') ?> <?= activeIconClass('/admin/pages') ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 <?= iconClassForAdmin('/admin/pages') ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" <?= iconStyleForAdmin('/admin/pages') ?>><path d="M4 21V7a2 2 0 0 1 2-2h10l4 4v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/></svg>
                    <span class="font-medium" <?= iconStyleForAdmin('/admin/pages') ?>>Pages</span>
                </a>
                <a href="/admin/posts" class="w-full flex items-center space-x-3 px-4 py-1 transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 <?= $activeClass('/admin/posts') ?> <?= activeIconClass('/admin/posts') ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 <?= iconClassForAdmin('/admin/posts') ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" <?= iconStyleForAdmin('/admin/posts') ?>><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h4"/><path d="M7 7V3h10v4"/></svg>
                    <span class="font-medium">Posts</span>
                </a>
                <a href="/admin/categories" class="w-full flex items-center space-x-3 px-4 py-1 transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 <?= $activeClass('/admin/categories') ?> <?= activeIconClass('/admin/categories') ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 <?= iconClassForAdmin('/admin/categories') ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" <?= iconStyleForAdmin('/admin/categories') ?>><path d="M4 6h16M4 12h8M4 18h16"/></svg>
                    <span class="font-medium">Categories</span>
                </a>
                <a href="/admin/tags" class="w-full flex items-center space-x-3 px-4 py-1 transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 <?= $activeClass('/admin/tags') ?> <?= activeIconClass('/admin/tags') ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 <?= iconClassForAdmin('/admin/tags') ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" <?= iconStyleForAdmin('/admin/tags') ?>><path d="M3 8l7-7 7 7-7 7-7-7z"/></svg>
                    <span class="font-medium">Tags</span>
                </a>
                <a href="/admin/pages/routes" class="w-full flex items-center space-x-3 px-4 py-1 transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 <?= $activeClass('/admin/pages/routes') ?> <?= activeIconClass('/admin/pages/routes') ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 <?= iconClassForAdmin('/admin/pages/routes') ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" <?= iconStyleForAdmin('/admin/pages/routes') ?>><path d="M3 7v14h18V7H3zm0-4h18v2H3V3zM7 10h10v4H7v-4z"/></svg>
                    <span class="font-medium">Routes</span>
                </a>
            </div>
        </div>
        
        <!-- Media -->
        <a href="/admin/media" class="w-full flex items-center space-x-3 px-4 py-1 transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 <?= $activeClass('/admin/media') ?> <?= activeIconClass('/admin/media') ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 <?= iconClassForAdmin('/admin/media') ?>" <?= iconStyleForAdmin('/admin/media') ?>>
                <polyline points="22 7 13.5 15.5 10 12 2 20"/>
                <polyline points="16 7 22 7 22 13"/>
            </svg>
            <span class="font-medium">Media</span>
        </a>
        
        <!-- Users Management (picker button inserted by client-side script) -->
        <a href="/admin/users" class="w-full flex items-center space-x-3 px-4 py-1 transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 <?= $activeClass('/admin/users') ?> <?= activeIconClass('/admin/users') ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 <?= iconClassForAdmin('/admin/users') ?>" <?= iconStyleForAdmin('/admin/users') ?>>
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <line x1="19" x2="19" y1="8" y2="14"/><line x1="16" x2="22" y1="11" y2="11"/>
            </svg>
            <span class="font-medium">Users</span>
        </a>
        
        <!-- Payments (Admin Actions) -->
        <a href="/admin/payments" class="w-full flex items-center space-x-3 px-4 py-1 transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 <?= $activeClass('/admin/payments') ?> <?= activeIconClass('/admin/payments') ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5" style="color: #f59e0b;">
                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                <line x1="1" y1="10" x2="23" y2="10"/>
            </svg>
            <span class="font-medium">Payments</span>
        </a>

        <!-- Color picker temporarily removed from per-nav UI. Use Admin → Settings to edit icon colours. -->
        
        <!-- Navigation/Menus -->
        <a href="/admin/menus" class="w-full flex items-center space-x-3 px-4 py-1 transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 <?= $activeClass('/admin/menus') ?> <?= activeIconClass('/admin/menus') ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 <?= iconClassForAdmin('/admin/menus') ?>" <?= iconStyleForAdmin('/admin/menus') ?>>
                <polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/>
                <polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>
            </svg>
            <span class="font-medium">Menus</span>
        </a>
        
        <!-- Appearance / Theme -->
        <a href="/admin/themes" class="w-full flex items-center space-x-3 px-4 py-1 transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 <?= $activeClass('/admin/themes') ?> <?= activeIconClass('/admin/themes') ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 <?= iconClassForAdmin('/admin/themes') ?>" <?= iconStyleForAdmin('/admin/themes') ?>>
                <circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/>
                <path d="M12 2v2"/><path d="M12 20v2"/><path d="M2 12h2"/><path d="M20 12h2"/>
            </svg>
                <span class="font-medium">Appearance</span>
        </a>
        
        <!-- Plugins -->
        <a href="/admin/plugins" class="w-full flex items-center space-x-3 px-4 py-1 transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 <?= $activeClass('/admin/plugins') ?> <?= activeIconClass('/admin/plugins') ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 <?= iconClassForAdmin('/admin/plugins') ?>" <?= iconStyleForAdmin('/admin/plugins') ?>>
                <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/>
                <path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/>
                <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/>
                <path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>
            </svg>
                <span class="font-medium">Plugins</span>
        </a>
        
        <!-- Migrate (Database Migrations) -->
        <a href="/admin/migrate" class="w-full flex items-center space-x-3 px-4 py-1 transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 <?= $activeClass('/admin/migrate') ?> <?= activeIconClass('/admin/migrate') ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5" style="color: #22c55e;">
                <ellipse cx="12" cy="5" rx="9" ry="3"/>
                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
                <path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"/>
            </svg>
            <span class="font-medium">Migrate</span>
        </a>
        
        <!-- LXC/LXD Manager (Proxmox-style) -->
        <a href="/admin/lxc" class="w-full flex items-center space-x-3 px-4 py-1 transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 <?= $activeClass('/admin/lxc') ?> <?= activeIconClass('/admin/lxc') ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5" style="color: #6366f1;">
                <rect x="2" y="3" width="20" height="14" rx="2"/>
                <path d="M8 21h8"/><path d="M12 17v4"/>
                <path d="M7 8h2v4H7z"/><path d="M11 8h2v4h-2z"/><path d="M15 8h2v4h-2z"/>
            </svg>
            <span class="font-medium">LXC Manager</span>
        </a>
        
        <!-- Settings -->
        <a href="/admin/settings" class="w-full flex items-center space-x-3 px-4 py-1 transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 <?= $activeClass('/admin/settings') ?> <?= activeIconClass('/admin/settings') ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 <?= iconClassForAdmin('/admin/settings') ?>" <?= iconStyleForAdmin('/admin/settings') ?>>
                <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
                <span class="font-medium">Settings</span>
        </a>
    </nav>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var contentToggle = document.getElementById('content-toggle');
            var contentSub = document.getElementById('content-sub');
            var chevron = document.getElementById('content-chevron');
            if (contentToggle && contentSub) {
                contentToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    contentSub.classList.toggle('hidden');
                    if (chevron) chevron.classList.toggle('rotate-180');
                });
            }

                // Sidebar collapse/expand (desktop)
                try {
                    const collapseBtn = document.getElementById('sidebar-collapse-button');
                    const collapseIcon = document.getElementById('sidebar-collapse-icon');
                    const SIDEBAR_KEY = 'sidebarCollapsed';

                    function setSidebarCollapsed(collapsed) {
                        try { localStorage.setItem(SIDEBAR_KEY, collapsed ? '1' : '0'); } catch (_) {}
                        if (collapsed) {
                            document.body.classList.add('sidebar-collapsed');
                            if (collapseIcon) collapseIcon.classList.add('rotate-180');
                            if (collapseBtn) collapseBtn.setAttribute('aria-pressed', 'true');
                        } else {
                            document.body.classList.remove('sidebar-collapsed');
                            if (collapseIcon) collapseIcon.classList.remove('rotate-180');
                            if (collapseBtn) collapseBtn.setAttribute('aria-pressed', 'false');
                        }
                    }

                    // Initialize from stored value
                    var stored = null;
                    try { stored = localStorage.getItem(SIDEBAR_KEY); } catch (_) { stored = null; }
                    if (stored === '1') setSidebarCollapsed(true);

                    if (collapseBtn) {
                        collapseBtn.addEventListener('click', function (e) {
                            e.preventDefault();
                            const now = document.body.classList.contains('sidebar-collapsed');
                            setSidebarCollapsed(!now);
                        });
                    }
                } catch (err) { /* ignore */ }
        });
    </script>
    <div class="mt-auto px-4 py-2 border-r border-t border-gray-200 dark:border-gray-700">
        <?php
        // Fetch the first admin user from the database

        $adminInitials = 'AD';
        $adminName = 'Admin';
        $adminRank = '';
        if (isset($user) && is_array($user) && !empty($user)) {
            $adminName = $user['fullname'] ?? ($user ?? $adminName);
            $adminRank = $user['role'] ?? $user['rank'] ?? ($user['role_id'] ?? $adminRank);
            $names = explode(' ', $adminName);
            $adminInitials = strtoupper(substr($names[0] ?? '', 0, 1) . substr($names[1] ?? '', 0, 1));
        }
        ?>
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center text-white font-semibold text-sm">
                <?= htmlspecialchars($adminInitials) ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate"><?= htmlspecialchars((string)$adminName) ?></p>
                <p class="text-xs text-gray-500 dark:text-gray-400 truncate"><?= htmlspecialchars((string)$adminRank) ?></p>
            </div>
            <a href="/logout" title="Sign Out" aria-label="Sign Out" class="ml-auto w-8 h-8 flex items-center justify-center rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                <!-- Inline SVG fallback for logout icon (shows even if Font Awesome doesn't load) -->
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 <?= iconClassForAdmin('/logout') ?>" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true" <?= iconStyleForAdmin('/logout') ?>>
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                    <path d="M10 17l5-5-5-5" />
                    <path d="M15 12H3" />
                </svg>
            </a>
        </div>
    </div>
</aside>

