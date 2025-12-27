<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard Pro</title>
    <!-- Tailwind CSS CDN -->
    <link rel="stylesheet" href="/assets/css/tailwind.css">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <script>
        // Configure Tailwind dark mode
        if (typeof tailwind !== 'undefined') {
            tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'primary-blue': {
                            400: '#60a5fa', // Lighter blue for dark mode accents
                            500: '#3b82f6', // Standard blue for general use
                            900: '#1e3a8a', // Darker blue for hover/active backgrounds
                        },
                        'dark-bg': '#0f172a', // Slate-900 equivalent for main body
                        'dark-card': '#1f2937', // Slate-800 equivalent for cards/sidebar
                    }
                }
            }
            }
        }
    </script>
    <style>
        /* Base Dark/Light Mode Styling */
        body {
            background-color: #f3f4f6; /* Default light background */
        }
        .dark body {
            background-color: #0f172a; /* Custom dark background */
        }
        .sidebar {
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease;
        }
        #mainContent {
            transition: margin-left 0.3s ease-in-out;
        }

        /* Desktop: Sidebar open by default */
        @media (min-width: 1024px) {
            #sidebar {
                transform: translateX(0);
                box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            }
            #mainContent {
                margin-left: 16rem; /* 64px = 16rem */
            }
            #hamburgerBtn {
                display: none; /* Hide hamburger on desktop */
            }
        }
    </style>
</head>
<?php require_once __DIR__ . '/parts/icon_helpers.php'; ?>
<body class="dark">

<!-- Hamburger Menu Button (Mobile Only) -->
<button id="hamburgerBtn" class="fixed top-4 left-4 z-50 bg-primary-blue-500 hover:bg-primary-blue-600 text-white p-3 rounded-lg shadow-lg transition-all duration-200 lg:hidden">
    <i class="fas fa-bars text-lg"></i>
</button>

<!-- **SIDEBAR** -->
<div id="sidebar" class="sidebar fixed left-0 top-0 h-full w-64 bg-dark-card text-white shadow-2xl transform -translate-x-full transition-all duration-300 ease-in-out z-50 flex flex-col border-r border-gray-700">
    <!-- Sidebar Header -->
    <div class="flex items-center justify-between p-6 border-b border-gray-700">
        <div class="flex items-center space-x-3">
            <i class="fas fa-crown <?= activeIconClass('/admin', 'text-primary-blue-400') ?> text-2xl"></i>
            <div>
                <h2 class="font-extrabold text-xl text-white tracking-wider">Admin Panel</h2>
            </div>
        </div>
        <button id="closeSidebar" class="lg:hidden text-gray-400 hover:text-white">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 p-4 space-y-1">
        <!-- Dashboard (Active) -->
        <a href="/admin" class="flex items-center px-4 py-3 rounded-lg transition-colors group bg-primary-blue-900 text-primary-blue-400 shadow-inner">
            <i class="fas fa-tachometer-alt w-5 text-center mr-3 <?= activeIconClass('/admin', 'text-primary-blue-400') ?>"></i>
            <span class="font-semibold">Dashboard</span>
        </a>

        <!-- Other Links -->
        <a href="/admin/users" class="flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 rounded-lg transition-colors group ">
            <i class="fas fa-users w-5 text-center mr-3 <?= activeIconClass('/admin/users', 'text-gray-400 dark:text-gray-300') ?>"></i>
            <span class="font-medium">Users</span>
        </a>
        <a href="/admin/network-tree" class="flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 rounded-lg transition-colors group ">
            <i class="fas fa-sitemap w-5 text-center mr-3 <?= activeIconClass('/admin/network-tree', 'text-gray-400 dark:text-gray-300') ?>"></i>
            <span class="font-medium">Network Tree</span>
        </a>
        <a href="/admin/commissions" class="flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 rounded-lg transition-colors group ">
            <i class="fas fa-dollar-sign w-5 text-center mr-3 <?= activeIconClass('/admin/commissions', 'text-gray-400 dark:text-gray-300') ?>"></i>
            <span class="font-medium">Commissions</span>
        </a>
        <a href="/admin/finance" class="flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 rounded-lg transition-colors group ">
            <i class="fas fa-chart-line w-5 text-center mr-3 <?= activeIconClass('/admin/finance', 'text-gray-400 dark:text-gray-300') ?>"></i>
            <span class="font-medium">Finance</span>
        </a>
        <a href="/admin/settings" class="flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 rounded-lg transition-colors group ">
            <i class="fas fa-cog w-5 text-center mr-3 <?= activeIconClass('/admin/settings', 'text-gray-400 dark:text-gray-300') ?>"></i>
            <span class="font-medium">Settings</span>
        </a>

        <!-- Divider -->
        <hr class="my-4 border-gray-700">

        <!-- User Dashboard Link -->
        <a href="/dashboard" class="flex items-center px-4 py-3 text-gray-300 hover:bg-green-900 rounded-lg transition-colors group">
            <i class="fas fa-user w-5 text-center mr-3 <?= activeIconClass('/dashboard', 'text-gray-400 dark:text-gray-300') ?>"></i>
            <span class="font-medium">User Dashboard</span>
        </a>
    </nav>

    <!-- Admin Profile Section -->
    <div class="p-6 border-t border-gray-700 bg-gray-900">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-primary-blue-500 rounded-full flex items-center justify-center font-bold text-white">A</div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-white truncate">Admin User</p>
                <p class="text-xs text-gray-400 truncate">System Administrator</p>
            </div>
            <a href="/logout" class="text-gray-400 hover:text-red-400 transition-colors p-2 rounded-full hover:bg-gray-700" title="Sign Out">
                <i class="fas fa-sign-out-alt text-sm <?= activeIconClass('/logout', 'text-gray-400') ?>"></i>
            </a>
        </div>
    </div>
</div>
<!-- **SIDEBAR** -->

<!-- Overlay for mobile -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-70 z-30 hidden lg:hidden"></div>

<script>
    // Sidebar logic for mobile responsiveness (kept simple)
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const closeSidebar = document.getElementById('closeSidebar');
        const overlay = document.getElementById('sidebarOverlay');

        function openSidebar() {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
        }

        function closeSidebarFn() {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        }

        hamburgerBtn?.addEventListener('click', openSidebar);
        closeSidebar?.addEventListener('click', closeSidebarFn);
        overlay?.addEventListener('click', closeSidebarFn);

        // Theme management for the toggle button
        const themeIcon = document.getElementById('themeIcon');
        const themeText = document.getElementById('themeText');

        function initializeTheme() {
            try { if (window.themeManager && typeof window.themeManager.init === 'function') { window.themeManager.init(); return; } } catch (_) {}
            const savedTheme = localStorage.getItem('theme') || 'dark';
            applyTheme(savedTheme);
        }

        window.toggleTheme = function() {
            try { window.themeManager && window.themeManager.toggle(); return; } catch (_) {}
            const currentTheme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            applyTheme(newTheme);
            try { localStorage.setItem('theme', newTheme); } catch (_) {}
        }

        function applyTheme(theme) {
            const html = document.documentElement;
            if (theme === 'dark') {
                html.classList.add('dark');
                if (themeIcon && themeText) {
                    themeIcon.className = 'fas fa-sun';
                    themeText.textContent = 'Light';
                }
            } else {
                html.classList.remove('dark');
                if (themeIcon && themeText) {
                    themeIcon.className = 'fas fa-moon';
                    themeText.textContent = 'Dark';
                }
            }
        }

        initializeTheme();
    });
</script>

<!-- Main Content Area - lg:ml-64 moves content past the fixed sidebar on desktop -->
<div id="mainContent" class="min-h-screen bg-dark-bg transition-all duration-300 ease-in-out">
    
    <!-- **HEADER** -->
    <div class="bg-dark-card shadow-lg border-b border-gray-700">
        <div class="px-6 lg:px-8 py-5">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-extrabold text-white">Admin Dashboard</h1>
                    <p class="mt-1 text-sm text-gray-400">
                        Welcome to the administration panel.
                    </p>
                </div>
                <div class="flex space-x-3">
                    <!-- Theme Toggle -->
                    <button id="themeToggle" onclick="toggleTheme()" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center space-x-2 shadow-md" title="Toggle Dark/Light Mode">
                        <i id="themeIcon" class="fas fa-sun"></i>
                        <span id="themeText">Light</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- **HEADER** -->

    <div class="p-6 lg:p-8">
        <!-- **BODY** -->

        <!-- 4-Column Statistics Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            
            <!-- Total Sales Card (Large Icon) -->
            <div class="bg-dark-card rounded-xl shadow-lg p-6 border-l-4 border-green-500">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm font-semibold text-gray-400 uppercase">Total Sales</p>
                        <p class="text-4xl font-extrabold text-white mt-2">$0.00</p>
                    </div>
                    <div class="w-12 h-12 bg-green-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-sack-dollar text-green-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- New Users Card (Large Icon) -->
            <div class="bg-dark-card rounded-xl shadow-lg p-6 border-l-4 border-primary-blue-500">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm font-semibold text-gray-400 uppercase">New Users</p>
                        <p class="text-4xl font-extrabold text-white mt-2">2</p>
                    </div>
                    <div class="w-12 h-12 bg-primary-blue-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-user-plus text-primary-blue-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Total Users Card (Secondary Stat) -->
            <div class="bg-dark-card rounded-xl shadow-lg p-6">
                <p class="text-sm font-semibold text-gray-400 uppercase">Total Users</p>
                <p class="text-3xl font-bold text-white mt-2">2</p>
                <div class="mt-3 flex items-center text-primary-blue-400">
                    <i class="fas fa-users mr-2"></i>
                    <span class="text-xs">Active Users</span>
                </div>
            </div>
            
            <!-- Pending Payouts Card (Secondary Stat) -->
            <div class="bg-dark-card rounded-xl shadow-lg p-6">
                <p class="text-sm font-semibold text-gray-400 uppercase">Pending Payouts</p>
                <p class="text-3xl font-bold text-white mt-2 text-yellow-500">$0.00</p>
                <div class="mt-3 flex items-center text-yellow-400">
                    <i class="fas fa-hourglass-half mr-2"></i>
                    <span class="text-xs">Awaiting processing</span>
                </div>
            </div>
        </div>

        <!-- Secondary Grid: Distribution, Status, Quick Actions -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- User Level Distribution -->
            <div class="lg:col-span-1 bg-dark-card rounded-xl shadow-lg p-6">
                <h3 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">User Level Distribution</h3>
                <div class="space-y-4">
                    <!-- Levels -->
                    <div class="flex justify-between items-center text-gray-400">
                        <span class="font-medium">Starter</span>
                        <span class="font-bold text-primary-blue-400">2 users</span>
                    </div>
                    <div class="flex justify-between items-center text-gray-400">
                        <span class="font-medium">Basic</span>
                        <span class="font-bold text-white">0 users</span>
                    </div>
                    <div class="flex justify-between items-center text-gray-400">
                        <span class="font-medium">Silver</span>
                        <span class="font-bold text-white">0 users</span>
                    </div>
                    <div class="flex justify-between items-center text-gray-400">
                        <span class="font-medium">Gold</span>
                        <span class="font-bold text-white">0 users</span>
                    </div>
                    <div class="flex justify-between items-center text-gray-400">
                        <span class="font-medium">Platinum</span>
                        <span class="font-bold text-white">0 users</span>
                    </div>
                </div>
            </div>

            <!-- System Status -->
            <div class="lg:col-span-1 bg-dark-card rounded-xl shadow-lg p-6">
                <h3 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">System Status</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center text-gray-400">
                        <span class="font-medium">Platform Status</span>
                        <div class="flex items-center space-x-2 text-green-500 font-bold">
                            <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                            <span>Online</span>
                        </div>
                    </div>
                    <div class="flex justify-between items-center text-gray-400">
                        <span class="font-medium">Database Connection</span>
                        <div class="flex items-center space-x-2 text-green-500 font-bold">
                            <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                            <span>Connected</span>
                        </div>
                    </div>
                    <div class="flex justify-between items-center text-gray-400">
                        <span class="font-medium">Log Server Status</span>
                        <div class="flex items-center space-x-2 text-green-500 font-bold">
                            <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                            <span>Operational</span>
                        </div>
                    </div>
                    <div class="flex justify-between items-center text-gray-400">
                        <span class="font-medium">API Health Check</span>
                        <div class="flex items-center space-x-2 text-green-500 font-bold">
                            <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                            <span>Good</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions (ePower Mall Button) - Combined with a list of other actions -->
            <div class="lg:col-span-1 bg-dark-card rounded-xl shadow-lg p-6">
                <h3 class="text-xl font-bold text-white mb-4 border-b border-gray-700 pb-3">Key Actions</h3>
                <div class="space-y-3">
                    <a href="/marketplace" class="w-full flex items-center px-4 py-3 font-semibold rounded-lg text-white transition-colors justify-center bg-pink-600 hover:bg-pink-700 shadow-md">
                        <i class="fas fa-store mr-2"></i>
                        ePower Mall
                    </a>
                    <a href="/admin/users/add" class="w-full flex items-center px-4 py-3 font-semibold rounded-lg text-white transition-colors justify-center bg-primary-blue-500 hover:bg-primary-blue-600 shadow-md">
                        <i class="fas fa-user-plus mr-2"></i>
                        Add New User
                    </a>
                    <a href="/admin/finance/payouts" class="w-full flex items-center px-4 py-3 font-semibold rounded-lg text-white transition-colors justify-center bg-green-600 hover:bg-green-700 shadow-md">
                        <i class="fas fa-money-check-dollar mr-2"></i>
                        Process Payouts
                    </a>
                </div>
            </div>
        </div>
        
        <!-- **BODY** -->

        <!-- **FOOTER** -->
        <footer class="mt-12 text-center text-sm text-gray-600">
            &copy; 2025 Admin Dashboard Pro. All rights reserved.
        </footer>
        <!-- **FOOTER** -->
    </div>
</div>

</body>
</html>