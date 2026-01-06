<?php
/** @var array $user */
/** @var array|null $recent_registered */
/** @var string $csrf_token */
/** @var array $countries */
/** @var string $temp_password */
/** @var int $direct_referral_count */
/** @var array $recent_referrals */
/** @var array|null $last_direct_referral */
require_once __DIR__ . '/../layout/header.php';
?>

<style>
    /* Ensure dashboard uses a single scrolling container to avoid double scrollbars.
       We prefer the inner dashboard content to scroll and hide the outer browser/page scrollbar. */
    html, body {
        height: 100%;
        margin: 0;
        padding: 0;
        overflow: hidden !important; /* hide outer scroll */
    }

    /* Sidebar and main content should each manage their own internal scrolling */
    .sidebar {
        height: 100vh;
        overflow: auto;
        -webkit-overflow-scrolling: touch;
    }

    #mainContentWrapper, .main-content-wrapper {
        height: 100vh;
        overflow: auto;
        -webkit-overflow-scrolling: touch;
    }

    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    .animate-slideIn {
        animation: slideIn 0.3s ease-out;
    }
    .animate-slideOut {
        animation: slideOut 0.3s ease-out;
    }
    /* Dark mode transitions and border improvements */
    .dark .border-gray-200,
    .dark .border-gray-300,
    .dark .border-gray-600,
    .dark .border-gray-700 {
        border-color: #23293a !important;
    }
    .dark .border-l-4,
    .dark .border-t,
    .dark .border-b {
        border-color: #23293a !important;
    }
    .dark .rounded-lg,
    .dark .rounded-xl,
    .dark .shadow-lg,
    .dark .shadow-inner {
        box-shadow: 0 2px 8px #23293a55 !important;
    }
    /* Elegant dark mode form fields */
    .dark input,
    .dark select,
    .dark textarea {
        background: linear-gradient(135deg, #23293a 70%, #2d3748 100%) !important;
        color: #f8fafc !important;
        border: 1.5px solid #374151 !important;
        box-shadow: 0 2px 8px 0 rgba(36,41,54,0.18);
        transition: border-color 0.2s, box-shadow 0.2s;
        font-weight: 500;
        letter-spacing: 0.01em;
        border-radius: 0.75rem !important;
        backdrop-filter: blur(2px);
    }
    .dark input:focus,
    .dark select:focus,
    .dark textarea:focus {
        border-color: #fbbf24 !important;
        box-shadow: 0 0 0 2px #fbbf24cc, 0 2px 8px 0 rgba(251,191,36,0.10);
        background: linear-gradient(135deg, #23293a 60%, #374151 100%) !important;
        color: #fff !important;
    }
    .dark input[readonly],
    .dark input[disabled],
    .dark select[disabled] {
        background: linear-gradient(135deg, #23293a 80%, #374151 100%) !important;
        color: #a1a1aa !important;
        border-color: #23293a !important;
    }
    /* Modern package card styling */
    .dark .package-option {
        border: 2px solid #23293a !important;
        background: linear-gradient(135deg, rgba(36,41,54,0.98) 80%, rgba(55,65,81,0.85) 100%) !important;
        color: #f3f4f6 !important;
        box-shadow: 0 2px 12px 0 rgba(20,20,30,0.10);
        border-radius: 1rem !important;
        transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    }
    .dark .package-option.selected,
    .dark .package-option:focus,
    .dark .package-option:hover {
        border-color: #fbbf24 !important;
        background: linear-gradient(135deg, #23293a 80%, #fbbf24 100%) !important;
        color: #fde68a !important;
        box-shadow: 0 4px 24px 0 rgba(251,191,36,0.12);
    }
    /* Label and placeholder contrast */
    .dark label,
    .dark .block,
    .dark .text-sm,
    .dark .text-xs {
        color: #cbd5e1 !important;
    }
    .dark ::placeholder {
        color: #a1a1aa !important;
        opacity: 1;
    }
    /* Mobile sidebar behaviour */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        transform: translateX(-100%);
        height: 100vh;
        width: 18rem;
        z-index: 40;
        transition: transform 0.3s ease;
    }
    .sidebar.open {
        transform: translateX(0);
    }
    .backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.4);
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
        z-index: 30;
    }
    .backdrop.open {
        opacity: 1;
        pointer-events: auto;
    }
    @media (min-width: 768px) {
        .sidebar {
            position: relative;
            transform: translateX(0);
            height: auto;
            width: 20rem;
        }
        .sidebar.open {
            transform: translateX(0);
        }
        .backdrop {
            display: none;
        }
    }
    /* Ensure dark mode persists during transitions */
    * {
        transition-property: background-color, border-color, color, fill, stroke;
        transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        transition-duration: 200ms;
    }
    /* Modern scrollbar styling for dashboard sidebar and main content */
    #sidebar, .main-content-wrapper, .flex-1, body {
        scrollbar-width: thin;
        scrollbar-color: #4B5563 #1F2937;
    }
    #sidebar::-webkit-scrollbar, .main-content-wrapper::-webkit-scrollbar, .flex-1::-webkit-scrollbar, body::-webkit-scrollbar {
        width: 8px;
        background: #1F2937;
        border-radius: 8px;
    }
    #sidebar::-webkit-scrollbar-thumb, .main-content-wrapper::-webkit-scrollbar-thumb, .flex-1::-webkit-scrollbar-thumb, body::-webkit-scrollbar-thumb {
        background: #4B5563;
        border-radius: 8px;
        border: 2px solid #1F2937;
    }
    #sidebar::-webkit-scrollbar-thumb:hover, .main-content-wrapper::-webkit-scrollbar-thumb:hover, .flex-1::-webkit-scrollbar-thumb:hover, body::-webkit-scrollbar-thumb:hover {
        background: #6B7280;
    }

/* Modern select dropdown styles */
    .modern-select {
        /* Remove default arrow for all browsers */
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        background-image: none !important;
    }
</style>
<body class="bg-gray-100 dark:bg-gray-900 font-sans transition-colors duration-200">
    
    <!-- Backdrop for Mobile Overlay -->
    <div id="sidebarBackdrop" class="backdrop md:hidden"></div>

    <!-- Hamburger Button (Fixed in Top-Left) -->
    <button id="hamburgerToggle" class="fixed top-4 left-4 z-50 p-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 md:hidden transition-all duration-200">
        <i class="fas fa-bars text-xl"></i>
    </button>

    <!-- Back to Top Button (Bottom Right) -->
    <button id="backToTop" class="fixed bottom-6 right-4 p-3 rounded-full bg-blue-500 text-white shadow-2xl hover:bg-blue-600 transition-all duration-200 transform z-40 opacity-0 pointer-events-none translate-y-4" aria-label="Back to top">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path d="M3 10a1 1 0 001.707.707L9 6.414V17a1 1 0 102 0V6.414l4.293 4.293A1 1 0 0017 10l-6-6-6 6z" />
        </svg>
    </button>

    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <div id="sidebar" class="sidebar bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 flex flex-col">
            <div class="h-16 px-4 border-b border-gray-200 dark:border-gray-700 flex items-center">
                    <div class="flex items-center gap-3 md:ml-0 ml-9">
                    <img src="/assets/images/ginto.png" alt="Ginto Logo" class="w-10 h-10 rounded-full shadow-md border-2 border-amber-400 bg-white dark:bg-gray-900" style="object-fit:cover;" />
                    <h1 class="text-2xl font-bold text-amber-600 dark:text-white">Ginto AI</h1>
                    <span class="ml-2 flex items-center justify-center">
                        <svg class="w-7 h-7 text-yellow-400 dark:text-yellow-300 drop-shadow" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M5 17L3 7l6 5 3-7 3 7 6-5-2 10H5z"/>
                            <circle cx="4" cy="6" r="1.5"/>
                            <circle cx="12" cy="3" r="1.5"/>
                            <circle cx="20" cy="6" r="1.5"/>
                        </svg>
                    </span>
                </div>
            </div>
            <!-- Welcome Message removed per request (redundant on Dashboard) -->
            <div class="flex-1 overflow-y-auto">
                <nav class="p-4">
                    <div class="space-y-2">
                        <a href="/chat" class="flex items-center px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-blue-600/10 dark:hover:bg-blue-700/20 rounded-lg transition-colors duration-200">
                            <i class="fas fa-comments mr-3 text-blue-400"></i>
                            <span class="font-medium">Ginto Chat</span>
                        </a>
                        <!-- Playground - Developer Tools -->
                        <a href="/playground" class="w-full flex items-center space-x-3 px-4 py-3 rounded-lg transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5" style="color: #8b5cf6;">
                                <path d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                            </svg>
                            <span class="font-medium" style="color: #8b5cf6;">Playground</span>
                        </a>
                        <!-- Link to the full downline list -->
                        <a href="/downline" class="flex items-center px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700/50 rounded-lg transition-colors duration-200">
                            <i class="fas fa-users mr-3"></i>
                            <span>My Network</span>
                        </a>
                        <!-- Network Tree Visualization -->
                        <a href="/user/network-tree" class="flex items-center px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700/50 rounded-lg transition-colors duration-200">
                            <i class="fas fa-sitemap mr-3 text-blue-500"></i>
                            <span>Network Tree</span>
                        </a>
                        <!-- ePower Mall Link -->
                        <a href="/marketplace" class="flex items-center px-4 py-2 mb-1 rounded-lg transition-colors group" style="background-color: #6b4a1b; color: #fff;">
                            <i class="fas fa-store mr-3" style="color: #ffc107;"></i>
                            <span>ePower Mall</span>
                        </a>
                        <a href="#" class="flex items-center px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700/50 rounded-lg transition-colors duration-200">
                            <i class="fab fa-bitcoin mr-3 text-amber-500"></i>
                            <span>BTC Earnings</span>
                        </a>
                        <a href="#registrationForm" class="flex items-center px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700/50 rounded-lg transition-colors duration-200">
                            <i class="fas fa-user-plus mr-3 text-green-500 dark:text-green-400"></i>
                            <span>New Registration</span>
                        </a>
                        <a href="#" class="flex items-center px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700/50 rounded-lg transition-colors duration-200">
                            <i class="fas fa-exchange-alt mr-3 text-purple-500 dark:text-purple-400"></i>
                            <span>Transactions</span>
                        </a>
                        <a href="#" class="flex items-center px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700/50 rounded-lg transition-colors duration-200">
                            <i class="fas fa-cog mr-3 text-gray-500 dark:text-gray-400"></i>
                            <span>Settings</span>
                        </a>

                        <?php if (isset($user['role_id']) && in_array($user['role_id'], [1, 2])): ?>
                        <!-- Admin Panel Link (only for admins) -->
                        <a href="/admin" class="flex items-center px-4 py-2 text-white bg-gradient-to-r from-red-600 to-red-500 dark:from-red-500 dark:to-red-400 rounded-lg shadow-md hover:shadow-lg transition-all duration-200">
                            <i class="fas fa-user-shield mr-3"></i>
                            <span>Admin Panel</span>
                        </a>
                        <?php endif; ?>

                        <!-- Theme Toggle Button -->
                        <button id="themeToggleNav" class="flex items-center px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700/50 rounded-lg w-full group transition-colors duration-200 theme-toggle" aria-pressed="false" role="button">
                            <!-- themeManager will update '#theme-toggle-text' and '#theme-toggle-icon' when present -->
                            <span id="theme-toggle-icon" class="mr-3" aria-hidden="true"><i class="fas fa-moon text-blue-600 dark:hidden"></i><i class="fas fa-sun hidden dark:block text-amber-400"></i></span>
                            <span id="theme-toggle-text">Dark Mode</span>
                        </button>

                        <a href="/logout" class="flex items-center px-4 py-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors duration-200">
                            <i class="fas fa-sign-out mr-3"></i>
                            <span>Logout</span>
                        </a>

                    </div>
                </nav>
            </div>
            <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                <a href="/user/profile/<?= urlencode($user['public_id'] ?? '') ?>" class="flex items-center group">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'User') ?>&background=3b82f6&color=fff" alt="<?= htmlspecialchars($user['fullname'] ?? 'User') ?>" class="w-10 h-10 rounded-full ring-2 ring-blue-500 dark:ring-blue-400 group-hover:ring-4 group-hover:ring-orange-400 transition-all">
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 group-hover:text-orange-600 transition-all"><?= htmlspecialchars($user['fullname'] ?? 'User') ?></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Platinum Member</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Main Content Wrapper -->
        <div id="mainContentWrapper" class="flex-1 overflow-auto main-content-wrapper bg-gray-100 dark:bg-gray-900 transition-colors duration-200">
            <!-- Header -->
            <header class="bg-white dark:bg-gray-800 shadow-sm dark:shadow-gray-900/10 sticky top-0 z-10 transition-colors duration-200">
                <div class="flex items-center justify-between px-6 h-16">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100 pl-8 md:pl-0">Network Dashboard</h2>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <button class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors duration-200">
                                <i class="fas fa-bell text-xl"></i>
                                <span class="absolute top-0 right-0 w-3 h-3 bg-red-500 rounded-full border-2 border-white dark:border-gray-800"></span>
                            </button>
                        </div>
                        <!-- Ads Button -->
                        <a href="/ads" class="inline-flex items-center gap-2 justify-center h-11 px-5 bg-gradient-to-r from-pink-500 to-yellow-500 dark:from-pink-600 dark:to-yellow-600 text-white rounded-lg hover:shadow-lg transition-all duration-200">
                            <i class="fas fa-bullhorn"></i>
                            <span>Ads</span>
                        </a>
                        <a href="#registrationForm" class="inline-flex items-center gap-2 justify-center h-11 px-5 bg-gradient-to-r from-orange-500 to-orange-600 dark:from-orange-600 dark:to-orange-700 text-white rounded-lg hover:shadow-lg transition-all duration-200">
                            <i class="fas fa-link"></i>
                            <span>Register New</span>
                        </a>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <main class="p-6">
                
                <!-- Latest Recruit Flash Message (Moved outside the grid to make space for a permanent card) -->
                <?php if (!empty($recent_registered) && is_array($recent_registered)): ?>
                    <div class="mb-6 p-4 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 rounded-lg shadow-inner flex justify-between items-center animate-pulse">
                        <p class="font-semibold flex items-center">
                            <i class="fas fa-bullhorn mr-3"></i> New Registration Alert!
                        </p>
                        <p class="text-sm">
                            <span class="font-bold"><?= htmlspecialchars($recent_registered['fullname'] ?? $recent_registered['username']) ?></span> just joined under you!
                            <a href="/user/profile/<?= urlencode($recent_registered['public_id'] ?? '') ?>" class="text-blue-600 dark:text-blue-400 hover:underline ml-3">View</a>
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 animate-fade-in">

                    <!-- NEW: Last Direct Referral Card (Permanent) -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow dark:shadow-gray-700/20 p-6 transition-colors duration-200 border-l-4 border-teal-500 dark:border-teal-400">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="mb-2 text-sm font-medium text-gray-600 dark:text-gray-400">Last Direct Recruit</p>
                                <p class="text-lg font-semibold text-gray-700 dark:text-gray-200">
                                    <?php if ($last_direct_referral): ?>
                                        <?= htmlspecialchars($last_direct_referral['fullname'] ?? '—') ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    <?php if ($last_direct_referral): ?>
                                        Joined: <?= date('M d, Y', strtotime($last_direct_referral['created_at'])) ?>
                                    <?php else: ?>
                                        No direct referrals yet.
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="p-3 rounded-full bg-teal-100 dark:bg-teal-900/30">
                                <svg class="h-8 w-8 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4 text-sm">
                            <?php if ($last_direct_referral): ?>
                                <a href="/user/profile/<?= urlencode($last_direct_referral['public_id'] ?? '') ?>" class="text-blue-600 dark:text-blue-400 hover:underline">View Profile</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- END NEW CARD -->

                    <!-- Total Sales Card -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow dark:shadow-gray-700/20 p-6 transition-colors duration-200">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 dark:bg-green-900/30">
                                <svg class="h-8 w-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="mb-2 text-sm font-medium text-gray-600 dark:text-gray-400">Total Sales</p>
                                <p class="text-lg font-semibold text-gray-700 dark:text-gray-200"><?= '$' . number_format($total_sales, 2) ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- New Users Card -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow dark:shadow-gray-700/20 p-6 transition-colors duration-200">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900/30">
                                <svg class="h-8 w-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="mb-2 text-sm font-medium text-gray-600 dark:text-gray-400">New Users</p>
                                <p class="text-lg font-semibold text-gray-700 dark:text-gray-200"><?= number_format($new_users_30) ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Active Sessions Card -->
                    <div class="relative rounded-xl overflow-hidden shadow-lg h-48 flex items-end justify-start bg-cover bg-center" style="background-image: url('/assets/images/ginto4.png');">
                        <div class="bg-black/50 w-full p-4 flex items-center">
                            <span class="text-3xl font-bold text-white mr-3">3</span>
                            <span class="text-lg text-white">ePower Mall Card 3</span>
                        </div>
                    </div>

                    <!-- Elegant Copy Referral Link Card -->
                    <div class="bg-gradient-to-br from-blue-50/80 via-blue-100/60 to-blue-200/40 dark:from-blue-900/40 dark:via-blue-900/60 dark:to-blue-800/60 rounded-lg shadow dark:shadow-gray-700/20 p-0 transition-colors duration-200 flex flex-col h-full min-h-[120px] relative overflow-hidden">
                        <?php
                            $publicId = $user['public_id'] ?? $user['id'];
                            $referralLink = rtrim(BASE_URL, '/') . '/register?ref=' . rawurlencode($publicId);
                        ?>
                        <div class="flex flex-col justify-between h-full w-full p-0">
                            <div class="flex items-center w-full px-6 pt-5 pb-2">
                                <div class="w-12 h-12 rounded-full bg-blue-500/90 dark:bg-blue-700/80 flex items-center justify-center shadow-lg">
                                    <svg class="h-7 w-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v10a2 2 0 01-2 2H7a2 2 0 01-2-2V10a2 2 0 012-2h2M15 3h-6a2 2 0 00-2 2v4a2 2 0 002 2h6a2 2 0 002-2V5a2 2 0 00-2-2z" />
                                    </svg>
                                </div>
                                <span class="ml-4 text-lg font-semibold text-blue-900 dark:text-blue-100 tracking-wide">Referral Link</span>
                            </div>
                            <div class="flex-1 flex items-center justify-center w-full px-6 pb-6 pt-2">
                                <div class="relative w-full">
                                    <input id="dashboardReferralLink" type="text" readonly value="<?= htmlspecialchars($referralLink) ?>"
                                        class="block w-full text-center text-base font-mono font-semibold rounded-lg px-4 py-4 bg-white/90 dark:bg-gray-900/80 text-blue-700 dark:text-blue-200 border-2 border-blue-200 dark:border-blue-700 shadow-inner focus:outline-none focus:ring-2 focus:ring-blue-400 transition-all select-all cursor-pointer hover:bg-blue-50/80 dark:hover:bg-blue-800/60"
                                        style="word-break:break-all; min-height:3.5rem; line-height:1.4;"
                                        title="<?= htmlspecialchars($referralLink) ?>"
                                    />
                                    <button id="copyDashboardReferralLink" class="absolute top-2 right-2 p-2 rounded-full bg-blue-500 hover:bg-blue-600 text-white shadow transition-colors" title="Copy Referral Link">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 17h8m-4-4v8m-4-4a4 4 0 118 0 4 4 0 01-8 0z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div id="dashboardCopyNotice" class="text-center pb-2 text-green-600 dark:text-green-400 text-xs hidden">Copied to clipboard</div>
                        </div>
                    </div>

                    <!-- Total Earnings Card -->
                    <div class="relative rounded-xl overflow-hidden shadow-lg h-48 flex items-end justify-start bg-cover bg-center" style="background-image: url('/assets/images/ginto2.png');">
                        <div class="bg-black/50 w-full p-4 flex items-center">
                            <span class="text-3xl font-bold text-white mr-3">1</span>
                            <span class="text-lg text-white">ePower Mall Card 1</span>
                        </div>
                    </div>

                    <!-- Total Team Card -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-gray-700/20 p-6 hover:shadow-xl dark:hover:shadow-gray-700/40 transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Team</p>
                                <p class="text-3xl font-bold text-gray-800 dark:text-gray-100 mt-1">1,248</p>
                            </div>
                            <div class="p-3 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">
                                <i class="fas fa-users text-2xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center justify-between text-sm">
                            <span class="text-green-500 dark:text-green-400 font-bold flex items-center"><i class="fas fa-arrow-up mr-1"></i>+12.5%</span>
                            <span class="text-gray-500 dark:text-gray-400">vs last month</span>
                        </div>
                    </div>

                    <!-- DIRECT REFERRALS CARD (MODIFIED) -->
                    <div id="referralCardContainer" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-gray-700/20 p-6 hover:shadow-xl dark:hover:shadow-gray-700/40 transition-all duration-300 border-l-4 border-amber-500 dark:border-amber-400">
                        <!-- Upper part: Click to show modal -->
                        <div id="referralCardBody" class="cursor-pointer">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Direct Referrals (L1)</p>
                                    <p class="text-3xl font-bold text-gray-800 dark:text-gray-100 mt-1"><?= $direct_referral_count ?? 0 ?></p>
                                </div>
                                <div class="p-3 rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400">
                                    <i class="fas fa-user-plus text-2xl"></i>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center justify-between text-sm">
                                <span class="text-amber-600 dark:text-amber-400 font-bold flex items-center">
                                    <i class="fas fa-hand-pointer mr-1"></i> Click to view recent
                                </span>
                                <span class="text-gray-500 dark:text-gray-400">Last <?= count($recent_referrals) ?> joiners</span>
                            </div>
                        </div>

                        <!-- Bottom part: Direct link to /downline -->
                        <div class="mt-4 pt-3 border-t dark:border-gray-700">
                            <a href="/downline" class="text-blue-600 dark:text-blue-400 font-bold flex items-center justify-center hover:underline text-sm transition-colors duration-200">
                                <i class="fas fa-list-alt mr-2"></i> Go to Full Downline List
                            </a>
                        </div>
                    </div>
                    <!-- END MODIFIED CARD -->

                    <!-- Pending Commissions Card -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-gray-700/20 p-6 hover:shadow-xl dark:hover:shadow-gray-700/40 transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Commissions</p>
                                <p class="text-3xl font-bold text-gray-800 dark:text-gray-100 mt-1">0.42 BTC</p>
                            </div>
                            <div class="p-3 rounded-lg bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400">
                                <i class="fas fa-clock text-2xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center justify-between text-sm">
                            <span class="text-green-500 dark:text-green-400 font-bold flex items-center"><i class="fas fa-arrow-up mr-1"></i>+3.1%</span>
                            <span class="text-gray-500">vs last month</span>
                        </div>
                    </div>
                    <!-- Custom Image Cards (next to Pending Commissions) -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-gray-700/20 p-6 hover:shadow-xl dark:hover:shadow-gray-700/40 transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Earnings (BTC)</p>
                                <p class="text-3xl font-bold text-gray-800 dark:text-gray-100 mt-1">2.84 BTC</p>
                            </div>
                            <div class="p-3 rounded-lg gradient-bg text-white dark:text-gray-100">
                                <i class="fab fa-bitcoin text-2xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center justify-between text-sm">
                            <span class="text-green-500 dark:text-green-400 font-bold flex items-center"><i class="fas fa-arrow-up mr-1"></i>+8.7%</span>
                            <span class="text-gray-500 dark:text-gray-400">vs last month</span>
                        </div>
                    </div>
                    <div class="relative rounded-xl overflow-hidden shadow-lg h-48 flex items-end justify-start bg-cover bg-center" style="background-image: url('/assets/images/ginto3.png');">
                        <div class="bg-black/50 w-full p-4 flex items-center">
                            <span class="text-3xl font-bold text-white mr-3">2</span>
                            <span class="text-lg text-white">ePower Mall Card 2</span>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow dark:shadow-gray-700/20 p-6 transition-colors duration-200">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900/30">
                                <svg class="h-8 w-8 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="mb-2 text-sm font-medium text-gray-600 dark:text-gray-400">Active Sessions</p>
                                <p class="text-lg font-semibold text-gray-700 dark:text-gray-200">1,259</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Network Visualization & Earnings Chart -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Network Visualization (2/3 width) -->
                    <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-gray-900/30 p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Your Network Structure</h3>
                            <div class="flex space-x-2">
                                <a href="/user/network-tree" class="px-4 py-2 text-sm bg-gradient-to-r from-purple-600 to-purple-500 dark:from-purple-500 dark:to-purple-400 text-white rounded-lg transition-all duration-200 hover:shadow-lg hover:from-purple-700 hover:to-purple-600 dark:hover:from-purple-600 dark:hover:to-purple-500 flex items-center space-x-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                    <span>Full Network Tree</span>
                                </a>
                                <button id="unilevel-toggle" class="px-3 py-1 text-sm bg-gradient-to-r from-blue-600 to-blue-500 dark:from-blue-500 dark:to-blue-400 text-white rounded-lg transition-all duration-200 hover:shadow-lg">Unilevel/Multilevel</button>
                                <button id="direct-sponsor-toggle" class="px-3 py-1 text-sm bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg transition-all duration-200 hover:bg-gray-300 dark:hover:bg-gray-600">Direct Sponsors</button>
                            </div>
                        </div>
                        <div class="relative h-96 bg-gray-50 dark:bg-gray-900/50 rounded-lg overflow-hidden flex items-center justify-center transition-colors duration-200">
                            <div id="network-view" class="text-center">
                                <!-- UNILEVEL/MULTILEVEL VISUALIZATION (Dynamic HTML placeholder) -->
                                <div class="network-node mx-auto w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 rounded-full flex items-center justify-center text-white font-bold text-lg shadow-xl dark:shadow-blue-900/30 mb-6 cursor-pointer">
                                    YOU
                                </div>
                                <p class="text-gray-600 dark:text-gray-300 font-medium mb-4">Level 1 (Direct Sponsors)</p>
                                <div class="flex justify-center space-x-6">
                                    <div class="network-node w-14 h-14 bg-gradient-to-br from-green-400 to-green-500 dark:from-green-500 dark:to-green-600 rounded-full flex flex-col items-center justify-center text-white text-xs font-bold shadow-lg dark:shadow-green-900/30 cursor-pointer">
                                        L1<br>+20
                                    </div>
                                    <div class="network-node w-14 h-14 bg-gradient-to-br from-green-400 to-green-500 dark:from-green-500 dark:to-green-600 rounded-full flex flex-col items-center justify-center text-white text-xs font-bold shadow-lg dark:shadow-green-900/30 cursor-pointer">
                                        L1<br>+15
                                    </div>
                                    <div class="network-node w-14 h-14 bg-gradient-to-br from-green-400 to-green-500 dark:from-green-500 dark:to-green-600 rounded-full flex flex-col items-center justify-center text-white text-xs font-bold shadow-lg dark:shadow-green-900/30 cursor-pointer">
                                        L1<br>+30
                                    </div>
                                    <div class="network-node w-14 h-14 bg-gray-300 dark:bg-gray-600 rounded-full flex items-center justify-center text-gray-700 dark:text-gray-300 text-sm font-bold shadow-lg dark:shadow-gray-900/30 cursor-pointer">
                                        ...
                                    </div>
                                </div>
                                <p class="text-sm text-gray-400 dark:text-gray-500 mt-6">Unilevel shows unlimited width (L1) and full depth (L2+ Multilevel).</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Earnings Chart (1/3 width) -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-gray-900/30 p-6">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Earnings Breakdown (BTC)</h3>
                        <div class="h-80">
                            <canvas id="earningsChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Add New Member Form -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-gray-900/30 p-6" id="registrationForm">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-6 border-b border-gray-200 dark:border-gray-700 pb-3">Register New Member <span class="text-orange-500 dark:text-orange-400 ml-2">via BTCPay</span></h3>
                    <form id="memberRegistrationForm" action="/register?ref=<?= htmlspecialchars($user['id'] ?? 2) ?>" method="POST">
                                                <div id="sponsorErrorMsg" class="text-red-600 text-sm font-bold mt-2 hidden">Sponsor username not found.</div>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                        <input type="hidden" name="password" id="hiddenPassword" value="<?= htmlspecialchars($temp_password ?? '') ?>">
                        <input type="hidden" name="fullname" id="hiddenFullname" value="">
                        <input type="hidden" name="dashboard_source" value="1">
                        <input type="hidden" id="sponsorId" name="sponsor_id" value="">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Field: Username -->
                            <div>
                                <input type="text" id="reg-username" name="username" required 
                                    class="w-full px-4 py-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-gray-100 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-colors duration-200" placeholder="Username*">
                            </div>
                            <!-- Field: Sponsor (Readonly) -->
                            <div>
                                <input type="text" id="sponsorInput" name="sponsor" autocomplete="off"
                                    class="w-full px-4 py-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-gray-100 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-colors duration-200" placeholder="Sponsor Username (optional)">
                            </div>
                            <!-- Field: First Name -->
                            <div>
                                <input type="text" id="firstName" name="firstname" required 
                                    class="w-full px-4 py-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-gray-100 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-colors duration-200" placeholder="First Name*">
                            </div>
                            <!-- Field: Middle Name -->
                            <div>
                                <input type="text" id="middleName" name="middlename" 
                                    class="w-full px-4 py-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-gray-100 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-colors duration-200" placeholder="Middle Name (optional)">
                            </div>
                            <!-- Field: Last Name -->
                            <div>
                                <input type="text" id="lastName" name="lastname" required 
                                    class="w-full px-4 py-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-gray-100 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-colors duration-200" placeholder="Last Name*">
                            </div>
                            <!-- Field: Email -->
                            <div>
                                <input type="email" id="email" name="email" required 
                                    class="w-full px-4 py-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-gray-100 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-colors duration-200" placeholder="Email*">
                            </div>
                            <!-- Field: Phone Number -->
                            <div>
                                <input type="tel" id="phone" name="phone" required pattern="[0-9]+" inputmode="numeric"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                    class="w-full px-4 py-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-gray-100 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-colors duration-200" placeholder="Phone Number*">
                            </div>
                            <!-- Field: Password (optional - will use server temp password if left blank) -->
                            <div>
                                <div class="relative">
                                    <input type="password" id="passwordInput" name="password"
                                        class="w-full px-4 py-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-gray-100 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-colors duration-200 pr-10" placeholder="Set a password for the new user (optional)">
                                    <button type="button" id="togglePasswordVisibility" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                                        <i class="fas fa-eye" id="passwordEyeIcon"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Field: Country (dynamic, with GeoIP auto-detect) -->
                            <div class="relative">
                                <select id="country" name="country" required
                                    class="modern-select w-full px-4 py-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:border-blue-500 dark:focus:border-blue-400 transition-all duration-200 appearance-none pr-10" aria-label="Country*">
                                    <option value="" disabled>Loading country...</option>
                                    <?php if (!empty($countries) && is_array($countries)): ?>
                                        <?php 
                                        // Sort countries by name
                                        uasort($countries, function($a, $b) {
                                            return strcmp($a['name'], $b['name']);
                                        });
                                        foreach ($countries as $code => $c): ?>
                                            <option value="<?= htmlspecialchars($code) ?>">
                                                <?= htmlspecialchars($c['name']) ?><?= !empty($c['dial_code']) ? ' (' . htmlspecialchars($c['dial_code']) . ')' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <!-- Fallback list if $countries is not available -->
                                        <option value="US">United States</option>
                                        <option value="UK">United Kingdom</option>
                                        <option value="PH">Philippines</option>
                                        <option value="NG">Nigeria</option>
                                    <?php endif; ?>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                    <svg class="w-5 h-5 text-gray-400 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </div>
                            
                            <!-- Membership Package Selection -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Membership Package*</label>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <?php if (!empty($levels) && is_array($levels)): ?>
                                        <?php foreach ($levels as $lvl): ?>
                                            <?php $label = htmlspecialchars($lvl['name']); $cost = number_format((float)$lvl['cost_amount'], 2); $curr = htmlspecialchars($lvl['cost_currency']); ?>
                                            <label class="package-option border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-orange-500 dark:hover:border-orange-400 cursor-pointer transition-all duration-200 bg-white dark:bg-gray-800">
                                                <input type="radio" name="package" value="<?= htmlspecialchars($lvl['id']) ?>" data-package-name="<?= $label ?>" required class="sr-only">
                                                <div class="flex items-center justify-between">
                                                    <h4 class="font-medium text-gray-900 dark:text-gray-100"><?= $label ?></h4>
                                                    <span class="text-sm font-bold text-orange-600 dark:text-orange-400"><?= $cost ?> <?= $curr ?></span>
                                                </div>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Cost: <?= $cost ?> <?= $curr ?></p>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <!-- Fallback: original static options -->
                                        <label class="package-option border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                            <input type="radio" name="package" value="starter" data-package-name="Starter" required class="sr-only">
                                            <div class="flex items-center justify-between"><h4 class="font-medium">Starter</h4><span class="text-sm font-bold text-orange-600">0.01 BTC</span></div>
                                            <p class="text-xs text-gray-500 mt-1">$423 USD</p>
                                        </label>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                        <div class="mt-8 flex justify-end">
                            <button type="button" id="btcPayButton" class="px-8 py-3 bg-orange-500 text-white font-bold rounded-lg hover:bg-orange-600 transition flex items-center shadow-lg">
                                <i class="fab fa-bitcoin mr-3 text-lg"></i> Complete Registration & Pay with BTCPay
                            </button>
                        </div>
                    </form>
                </div>
                <!-- Custom Image Cards Row -->
                <div class="mt-12 grid grid-cols-1 md:grid-cols-3 gap-6">
                    
                </div>
            </main>
        </div>
    </div>
    <!-- DIRECT REFERRALS MODAL -->
    <div id="referralModal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/75 dark:bg-gray-900/90 flex items-center justify-center invisible opacity-0 transition-opacity duration-300">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-lg w-full m-4">
            <!-- Modal Header -->
            <div class="p-5 border-b dark:border-gray-700 flex justify-between items-center">
                <h3 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                    Direct Downline (L1) List
                </h3>
                <button onclick="toggleModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div class="p-5 max-h-96 overflow-y-auto">
                <?php if (empty($recent_referrals)): ?>
                    <p class="text-gray-600 dark:text-gray-400 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        You currently have no direct referrals. Share your referral link!
                    </p>
                <?php else: ?>
                    <ul class="space-y-3">
                        <?php foreach ($recent_referrals as $ref): ?>
                            <li class="p-3 border dark:border-gray-700 rounded-lg bg-yellow-50 dark:bg-yellow-900/10 flex justify-between items-center transition-colors duration-200">
                                <div>
                                    <p class="font-medium text-gray-800 dark:text-gray-100">
                                        <?= htmlspecialchars($ref['fullname'] ?? $ref['username']) ?>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Username: <?= htmlspecialchars($ref['username']) ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-200 text-green-800 dark:bg-green-700 dark:text-green-100">
                                        L<?= $ref['ginto_level'] ?>
                                    </span>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                        Joined: <?= date('M d, Y', strtotime($ref['created_at'])) ?>
                                    </p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="mt-6 text-sm text-center text-gray-700 dark:text-gray-300 border-t dark:border-gray-700 pt-4">
                        Showing <?= count($recent_referrals) ?> most recent joiners. 
                        <?php if ($direct_referral_count > count($recent_referrals)): ?>
                            <span class="font-semibold block mt-1">Total: <?= $direct_referral_count ?>.</span>
                        <?php endif; ?>
                        <a href="/downline" class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 font-medium mt-2 inline-block">View All Downline &rarr;</a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- END DIRECT REFERRALS MODAL -->


    <!-- BTCPay Payment Modal -->
    <div id="btcPayModal" class="fixed inset-0 bg-black/50 dark:bg-black/70 flex items-center justify-center z-50 hidden backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-md shadow-xl transition-all duration-200">
            <div class="flex justify-between items-center mb-4 border-b dark:border-gray-700 pb-2">
                <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Payment for <span id="modalPackageName" class="text-blue-600 dark:text-blue-400">Gold</span> Package</h3>
                <button id="closeModal" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mb-6 text-center">
                <p class="text-gray-600 dark:text-gray-300 mb-4">Total Amount Due: <span class="text-2xl font-bold text-orange-500 dark:text-orange-400" id="modalBtcAmount">0.05 BTC</span></p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Scan the QR code or copy the address to pay securely via **BTCPay Server**.</p>
            </div>
            <div class="flex flex-col items-center mb-6">
                <!-- Placeholder for QR Code (In a real app, this would be generated by BTCPay API) -->
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=bitcoin:3FZbgi29cpjq2GjdwV8eyHuJJnkLtktZc5?amount=0.05" alt="Bitcoin QR Code" class="w-48 h-48 border-4 border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-900 p-2 rounded-lg mb-4">
                <div class="bg-gray-100 dark:bg-gray-900 p-3 rounded-lg w-full">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-mono text-gray-800 dark:text-gray-200 truncate" id="modalBtcAddress">3FZbgi29cpjq2GjdwV8eyHuJJnkLtktZc5</span>
                        <button class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm font-bold ml-2 transition-colors duration-200">
                            <i class="fas fa-copy mr-1"></i> Copy
                        </button>
                    </div>
                </div>
            </div>
            <div class="flex justify-between items-center text-sm text-gray-500 dark:text-gray-400 pt-3 border-t dark:border-gray-700">
                <div>
                    <i class="fas fa-bolt mr-1 text-yellow-500 dark:text-yellow-400"></i>
                    <span>Lightning & On-Chain supported</span>
                </div>
                <div>
                    <i class="fas fa-stopwatch mr-1"></i>
                    <span>Expires in 15:00</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Consolidated Dashboard JavaScript -->
    <script>
        // Dashboard copy referral link functionality
        (function(){
            var btn = document.getElementById('copyDashboardReferralLink');
            var input = document.getElementById('dashboardReferralLink');
            if (input) {
                input.addEventListener('click', function(){
                    input.select();
                });
            }
            var notice = document.getElementById('dashboardCopyNotice');
            if (btn && input) {
                btn.addEventListener('click', function(){
                    try {
                        input.select();
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(input.value).then(function(){
                                if (notice) { notice.classList.remove('hidden'); setTimeout(function(){ notice.classList.add('hidden'); }, 2000); }
                            }, function(){
                                document.execCommand('copy'); if (notice) { notice.classList.remove('hidden'); setTimeout(function(){ notice.classList.add('hidden'); }, 2000); }
                            });
                        } else {
                            document.execCommand('copy'); if (notice) { notice.classList.remove('hidden'); setTimeout(function(){ notice.classList.add('hidden'); }, 2000); }
                        }
                    } catch (e) {
                        alert('Copy failed — select and copy the link manually.');
                    }
                });
            }
        })();
    document.addEventListener('DOMContentLoaded', function () {
                        // Helper: force theme classes and styles for obvious elements
                        function forceThemeVisuals() {
                            const isDark = document.documentElement.classList.contains('dark');
                            // Sidebar
                            const sidebar = document.getElementById('sidebar');
                            if (sidebar) sidebar.style.backgroundColor = isDark ? '#1f2937' : '#fff';
                            // Header
                            document.querySelectorAll('header').forEach(h => {
                                h.style.backgroundColor = isDark ? '#1f2937' : '#fff';
                                h.style.color = isDark ? '#f3f4f6' : '#1f2937';
                            });
                            // Main content wrapper
                            const mainContent = document.getElementById('mainContentWrapper');
                            if (mainContent) mainContent.style.backgroundColor = isDark ? '#111827' : '#f3f4f6';
                            // All cards
                            document.querySelectorAll('.rounded-lg, .rounded-xl, .shadow-lg, .shadow-inner').forEach(card => {
                                card.style.backgroundColor = isDark ? '#1f2937' : '#fff';
                                card.style.color = isDark ? '#e5e7eb' : '#1f2937';
                                card.style.boxShadow = isDark ? '0 2px 8px #11182755' : '0 2px 8px #d1d5db55';
                            });
                            // Modals
                            document.querySelectorAll('.modal, #referralModal, #btcPayModal').forEach(modal => {
                                modal.style.backgroundColor = isDark ? '#1f2937' : '#fff';
                                modal.style.color = isDark ? '#e5e7eb' : '#1f2937';
                            });
                            // Buttons
                            document.querySelectorAll('button, .btn, .theme-toggle').forEach(btn => {
                                btn.style.backgroundColor = isDark ? '#374151' : '#fff';
                                btn.style.color = isDark ? '#fbbf24' : '#1f2937';
                                btn.style.borderColor = isDark ? '#4b5563' : '#d1d5db';
                            });
                            // Flash messages
                            document.querySelectorAll('.bg-green-100, .bg-red-100, .bg-blue-50').forEach(msg => {
                                msg.style.backgroundColor = isDark ? '#065e3b' : '#d1fae5';
                                msg.style.color = isDark ? '#a7f3d0' : '#065e3b';
                            });
                        }

                        // Listen for theme changes and apply visuals
                        window.addEventListener('site-theme-changed', forceThemeVisuals);
                        // Also run once on load
                        forceThemeVisuals();
                // Reset registration form after successful registration (AJAX or normal)
                function resetRegistrationForm() {
                    const form = document.getElementById('memberRegistrationForm');
                    if (form) form.reset();
                    // Optionally clear custom highlights or error messages
                    document.querySelectorAll('.package-option').forEach(function(c) {
                        c.classList.remove('ring', 'ring-2', 'ring-orange-500', 'border-orange-500', 'dark:border-orange-400', 'bg-orange-50', 'dark:bg-orange-900/20');
                    });
                }

                // If using AJAX for registration, call resetRegistrationForm() on success
                // If not, add a check for a success message and reset
                if (window.location.search.includes('register_success=1')) {
                    resetRegistrationForm();
                }
        // Make package cards fully selectable and highlight selected
        document.querySelectorAll('.package-option').forEach(function(card) {
            card.addEventListener('click', function(e) {
                // Only trigger if not clicking the radio directly
                if (e.target.tagName.toLowerCase() !== 'input') {
                    // Prevent default label behavior which may focus the hidden radio
                    // and cause the browser to scroll the page. We'll manage focus
                    // programmatically and avoid scrolling where possible.
                    e.preventDefault();
                    var radio = card.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                        // Try to focus without scrolling (modern browsers support this)
                        try {
                            radio.focus({preventScroll: true});
                        } catch (err) {
                            // Fallback: briefly blur to avoid leaving focus that could scroll
                            try { radio.blur(); } catch (_) {}
                        }
                    }
                    // Remove highlight from all
                    document.querySelectorAll('.package-option').forEach(function(c) {
                        c.classList.remove('ring', 'ring-2', 'ring-orange-500', 'border-orange-500', 'dark:border-orange-400', 'bg-orange-50', 'dark:bg-orange-900/20');
                    });
                    // Add highlight to selected
                    card.classList.add('ring', 'ring-2', 'ring-orange-500', 'border-orange-500', 'dark:border-orange-400', 'bg-orange-50', 'dark:bg-orange-900/20');
                }
            });
            // Also highlight if already checked on load
            var radio = card.querySelector('input[type="radio"]');
            if (radio && radio.checked) {
                card.classList.add('ring', 'ring-2', 'ring-orange-500', 'border-orange-500', 'dark:border-orange-400', 'bg-orange-50', 'dark:bg-orange-900/20');
            }
        });
        // Sponsor Username to ID Resolver (single declaration)
        if (!window._sponsorInputResolverInitialized) {
            window._sponsorInputResolverInitialized = true;
            const sponsorInput = document.getElementById('sponsorInput');
            const sponsorIdField = document.getElementById('sponsorId');
            const registrationForm = document.getElementById('memberRegistrationForm');
            let sponsorValid = false;
            if (sponsorInput && sponsorIdField && registrationForm) {
                async function resolveSponsorId() {
                    const username = sponsorInput.value.trim();
                    if (!username) {
                        sponsorIdField.value = '';
                        sponsorValid = false;
                        sponsorInput.classList.remove('border-green-500');
                        sponsorInput.classList.add('border-red-500');
                        const regBtn = document.getElementById('btcPayButton');
                        if (regBtn) regBtn.disabled = true;
                        document.getElementById('sponsorErrorMsg').classList.remove('hidden');
                        
                        return;
                    }
                    try {
                        const response = await fetch(`/api/user-id?username=${encodeURIComponent(username)}`);
                        const data = await response.json();
                            if (data && data.id) {
                            sponsorIdField.value = data.id;
                            sponsorValid = true;
                            sponsorInput.classList.remove('border-red-500');
                            sponsorInput.classList.add('border-green-500');
                            const regBtn = document.getElementById('btcPayButton');
                            if (regBtn) regBtn.disabled = false;
                            document.getElementById('sponsorErrorMsg').classList.add('hidden');
                            
                        } else {
                            sponsorIdField.value = '';
                            sponsorValid = false;
                            sponsorInput.classList.remove('border-green-500');
                            sponsorInput.classList.add('border-red-500');
                            const regBtn = document.getElementById('btcPayButton');
                            if (regBtn) regBtn.disabled = true;
                            document.getElementById('sponsorErrorMsg').classList.remove('hidden');
                            
                        }
                    } catch (e) {
                        sponsorIdField.value = '';
                        sponsorValid = false;
                        sponsorInput.classList.remove('border-green-500');
                        sponsorInput.classList.add('border-red-500');
                        const regBtn = document.getElementById('btcPayButton');
                        if (regBtn) regBtn.disabled = true;
                        document.getElementById('sponsorErrorMsg').classList.remove('hidden');
                        
                    }
                }
                sponsorInput.addEventListener('blur', resolveSponsorId);
                sponsorInput.addEventListener('change', resolveSponsorId);
                sponsorInput.addEventListener('input', resolveSponsorId);
            }
        }
        
        // ===========================================
        // SIDEBAR TOGGLE
        // ===========================================
        const sidebar = document.getElementById('sidebar');
        const hamburgerToggle = document.getElementById('hamburgerToggle');
        const sidebarBackdrop = document.getElementById('sidebarBackdrop');

        if (hamburgerToggle) {
            function toggleSidebar() {
                if (sidebar) sidebar.classList.toggle('open');
                if (sidebarBackdrop) sidebarBackdrop.classList.toggle('open');
                document.body.classList.toggle('overflow-hidden');
            }
            hamburgerToggle.addEventListener('click', toggleSidebar);
            if (sidebarBackdrop) sidebarBackdrop.addEventListener('click', toggleSidebar);
        }

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768) {
                if (sidebar) sidebar.classList.remove('open');
                if (sidebarBackdrop) sidebarBackdrop.classList.remove('open');
                document.body.classList.remove('overflow-hidden');
            }
        });

        // ===========================================
        // THEME TOGGLE (DARK MODE)
        // ===========================================
    const themeToggleNav = document.getElementById('themeToggleNav');

        // Centralized theme toggle with fallback behavior.
        // Prefer a global `window.themeManager` if present; otherwise manage a
        // class-based dark mode on the root element and persist choice to
        // localStorage under the key `ginto_theme`.
        function applyTheme(dark) {
            // If a centralized theme manager is present, prefer its implementation
            // so persistence and broadcasts remain consistent across the app.
            try {
                if (window.themeManager && typeof window.themeManager.applyTheme === 'function') {
                    try { window.themeManager.applyTheme(!!dark); } catch (_) {}
                    if (themeToggleNav) themeToggleNav.setAttribute('aria-pressed', !!dark ? 'true' : 'false');
                    return;
                }
            } catch (_) {}

            try {
                if (dark) {
                    document.documentElement.classList.add('dark');
                    document.body.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                    document.body.classList.remove('dark');
                }
                // update aria-pressed for accessibility
                if (themeToggleNav) themeToggleNav.setAttribute('aria-pressed', dark ? 'true' : 'false');
                // Try to keep other simple globals in sync (best-effort, no-ops if missing)
                try {
                    if (window.themes && typeof window.themes.setTheme === 'function') {
                        window.themes.setTheme(dark ? 'dark' : 'light');
                    } else if (window.themesManager && typeof window.themesManager.set === 'function') {
                        window.themesManager.set(dark ? 'dark' : 'light');
                    }
                } catch (_) {}
            } catch (e) {
                // ignore DOM exceptions
            }
        }

        function getStoredPreference() {
            try {
                // Prefer the canonical key used by the global theme manager
                var primary = localStorage.getItem('theme');
                if (primary !== null) return primary;
                return localStorage.getItem('ginto_theme');
            } catch (_) { return null; }
        }

        function setStoredPreference(value) {
            try {
                // Keep both keys in sync so older code paths still read the value
                localStorage.setItem('theme', value);
                localStorage.setItem('ginto_theme', value);
            } catch (_) {}
        }

        function systemPrefersDark() {
            try { return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches; } catch (_) { return false; }
        }

        function initTheme() {
            // If a global theme manager exists, let it initialize. Otherwise apply
            // stored or system preference. Support multiple possible globals
            // (`themeManager`, `themes`, `themesManager`) to remain compatible
            // with different theme libraries (including themes.js).
            try {
                if (window.themeManager && typeof window.themeManager.init === 'function') {
                    window.themeManager.init();
                    return;
                }
                if (window.themes && typeof window.themes.init === 'function') {
                    window.themes.init();
                    return;
                }
                if (window.themesManager && typeof window.themesManager.init === 'function') {
                    window.themesManager.init();
                    return;
                }
            } catch (_) {}

            const stored = getStoredPreference();
            if (stored === 'dark') {
                applyTheme(true);
            } else if (stored === 'light') {
                applyTheme(false);
            } else {
                applyTheme(systemPrefersDark());
            }
        }

        function toggleTheme(event) {
            // If a central themeManager is available, prefer it. Support a few
            // common APIs without modifying any external library.
            try {
                if (window.themeManager && typeof window.themeManager.toggle === 'function') {
                    window.themeManager.toggle();
                    return;
                }
                if (window.themes && typeof window.themes.toggleTheme === 'function') {
                    window.themes.toggleTheme();
                    return;
                }
                if (window.themes && typeof window.themes.setTheme === 'function') {
                    const isDarkNow = document.documentElement.classList.contains('dark');
                    window.themes.setTheme(isDarkNow ? 'light' : 'dark');
                    setStoredPreference(!isDarkNow ? 'dark' : 'light');
                    return;
                }
            } catch (_) {}

            const isDark = document.documentElement.classList.contains('dark');
            applyTheme(!isDark);
            setStoredPreference(!isDark ? 'dark' : 'light');

            if (event && event.currentTarget) {
                // provide a brief visual feedback
                event.currentTarget.classList.add('opacity-90');
                setTimeout(() => event.currentTarget.classList.remove('opacity-90'), 200);
            }
        }

        // Initialize theme on page load and wire up the toggle button
        initTheme();
        if (themeToggleNav) {
            themeToggleNav.setAttribute('role', 'button');
            themeToggleNav.addEventListener('click', toggleTheme);
        }

        // Listen for global theme changes and update dashboard widgets
        function updateDashboardThemeWidgets() {
            // Update all cards, modals, and widgets to match the current theme
            const isDark = document.documentElement.classList.contains('dark');
            // Example: update Chart.js colors if chart exists
            if (window.dashboardChart && typeof window.dashboardChart.options === 'object') {
                window.dashboardChart.options.plugins.legend.labels.color = isDark ? '#e5e7eb' : '#374151';
                window.dashboardChart.options.scales.x.ticks.color = isDark ? '#e5e7eb' : '#374151';
                window.dashboardChart.options.scales.y.ticks.color = isDark ? '#e5e7eb' : '#374151';
                window.dashboardChart.options.plugins.tooltip.backgroundColor = isDark ? '#1f2937' : '#fff';
                window.dashboardChart.update();
            }
            // Update modals and notification backgrounds if needed
            document.querySelectorAll('.notification, .modal, .package-option, .main-content-wrapper, .sidebar').forEach(function(el) {
                // Force Tailwind dark: classes to re-apply
                el.classList.toggle('dark', isDark);
            });
        }

        window.addEventListener('site-theme-changed', updateDashboardThemeWidgets);
        // Also run once on load
        updateDashboardThemeWidgets();

        // ===========================================
        // NETWORK VIEW TOGGLE
        // ===========================================
        const networkView = document.getElementById('network-view');
        const unilevelToggle = document.getElementById('unilevel-toggle');
        const directSponsorToggle = document.getElementById('direct-sponsor-toggle');
        const directReferralCount = <?= $direct_referral_count ?? 0 ?>;

        function switchNetworkView(view) {
            if (!networkView) return;

            if (view === 'direct') {
                networkView.innerHTML = `
                    <p class="text-2xl font-bold text-gray-700 dark:text-gray-200 mt-24">Direct Sponsors (Level 1)</p>
                    <p class="text-gray-500 dark:text-gray-400 mt-4">A scrollable list of ${directReferralCount} direct recruits with their individual performance metrics.</p>
                    <div class="w-full mt-8 flex justify-center space-x-4">
                        <a href="/downline" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">View Full List</a>
                        <button class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">Filter</button>
                    </div>
                `;
                directSponsorToggle.classList.add('from-blue-600', 'to-blue-500', 'dark:from-blue-500', 'dark:to-blue-400', 'text-white');
                directSponsorToggle.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
                unilevelToggle.classList.remove('from-blue-600', 'to-blue-500', 'dark:from-blue-500', 'dark:to-blue-400', 'text-white');
                unilevelToggle.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
            } else {
                networkView.innerHTML = `
                    <div class="network-node mx-auto w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 rounded-full flex items-center justify-center text-white font-bold text-lg shadow-xl dark:shadow-blue-900/30 mb-6 cursor-pointer">
                        YOU
                    </div>
                    <p class="text-gray-600 dark:text-gray-300 font-medium mb-4">Level 1 (Direct Sponsors)</p>
                    <div class="flex justify-center space-x-6">
                        <div class="network-node w-14 h-14 bg-gradient-to-br from-green-400 to-green-500 dark:from-green-500 dark:to-green-600 rounded-full flex flex-col items-center justify-center text-white text-xs font-bold shadow-lg dark:shadow-green-900/30 cursor-pointer">
                            L1<br>+20
                        </div>
                        <div class="network-node w-14 h-14 bg-gradient-to-br from-green-400 to-green-500 dark:from-green-500 dark:to-green-600 rounded-full flex flex-col items-center justify-center text-white text-xs font-bold shadow-lg dark:shadow-green-900/30 cursor-pointer">
                            L1<br>+15
                        </div>
                        <div class="network-node w-14 h-14 bg-gradient-to-br from-green-400 to-green-500 dark:from-green-500 dark:to-green-600 rounded-full flex flex-col items-center justify-center text-white text-xs font-bold shadow-lg dark:shadow-green-900/30 cursor-pointer">
                            L1<br>+30
                        </div>
                        <div class="network-node w-14 h-14 bg-gray-300 dark:bg-gray-600 rounded-full flex items-center justify-center text-gray-700 dark:text-gray-300 text-sm font-bold shadow-lg dark:shadow-gray-900/30 cursor-pointer">
                            ...
                        </div>
                    </div>
                    <p class="text-sm text-gray-400 dark:text-gray-500 mt-6">Unilevel shows unlimited width (L1) and full depth (L2+ Multilevel).</p>
                `;
                unilevelToggle.classList.add('from-blue-600', 'to-blue-500', 'dark:from-blue-500', 'dark:to-blue-400', 'text-white');
                unilevelToggle.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
                directSponsorToggle.classList.remove('from-blue-600', 'to-blue-500', 'dark:from-blue-500', 'dark:to-blue-400', 'text-white');
                directSponsorToggle.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
            }
        }

        if (unilevelToggle) unilevelToggle.addEventListener('click', () => switchNetworkView('unilevel'));
        if (directSponsorToggle) directSponsorToggle.addEventListener('click', () => switchNetworkView('direct'));

        // ===========================================
        // PASSWORD VISIBILITY TOGGLE
        // ===========================================
        const passwordInput = document.getElementById('passwordInput');
        const toggleButton = document.getElementById('togglePasswordVisibility');
        const eyeIcon = document.getElementById('passwordEyeIcon');

        if (toggleButton && passwordInput && eyeIcon) {
            toggleButton.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                eyeIcon.classList.toggle('fa-eye');
                eyeIcon.classList.toggle('fa-eye-slash');
            });
        }

        // ===========================================
        // NOTIFICATION SYSTEM
        // ===========================================
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            const typeClasses = type === 'success' 
                ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200' 
                : 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200';
            
            notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 animate-slideIn ${typeClasses}`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
                    <p>${message}</p>
                </div>
            `;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.classList.add('animate-slideOut');
                setTimeout(() => notification.remove(), 500);
            }, 5000);
        }

        // ===========================================
        // REFERRAL MODAL
        // ===========================================
        const referralCardBody = document.getElementById('referralCardBody');
        const referralModal = document.getElementById('referralModal');
        
        function toggleReferralModal() {
            if (referralModal) {
                referralModal.classList.toggle('invisible');
                referralModal.classList.toggle('opacity-0');
                document.body.classList.toggle('overflow-hidden');
            }
        }

        if (referralCardBody) {
            referralCardBody.addEventListener('click', toggleReferralModal);
        }
        
        if (referralModal) {
            referralModal.addEventListener('click', (e) => {
                if (e.target.id === 'referralModal') {
                    toggleReferralModal();
                }
            });
            
            const closeButton = referralModal.querySelector('button');
            if (closeButton) {
                closeButton.addEventListener('click', toggleReferralModal);
            }
        }

        // ===========================================
        // BTCPAY MODAL
        // ===========================================
        const btcPayModal = document.getElementById('btcPayModal');
        const closeModal = document.getElementById('closeModal');

        if (closeModal && btcPayModal) {
            closeModal.addEventListener('click', function() {
                btcPayModal.classList.add('hidden');
            });
        }

        // Copy BTC address functionality
        const copyButton = document.querySelector('#btcPayModal .fa-copy')?.parentElement;
        if (copyButton) {
            copyButton.addEventListener('click', async function() {
                const addressElement = document.getElementById('modalBtcAddress');
                if (addressElement) {
                    const address = addressElement.textContent;
                    try {
                        await navigator.clipboard.writeText(address);
                        showNotification('BTC address copied to clipboard!', 'success');
                    } catch (err) {
                        // Fallback for older browsers
                        const textArea = document.createElement('textarea');
                        textArea.value = address;
                        document.body.appendChild(textArea);
                        textArea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textArea);
                        showNotification('BTC address copied to clipboard!', 'success');
                    }
                }
            });
        }

            // ===========================================
            // SPONSOR USERNAME TO ID LOOKUP
            // ===========================================
            const sponsorInput = document.getElementById('sponsorInput');
            const sponsorIdInput = document.getElementById('sponsorId');
            if (sponsorInput && sponsorIdInput) {
                sponsorInput.addEventListener('blur', function() {
                    const username = sponsorInput.value.trim();
                    if (!username) return;
                    // Simple AJAX to fetch sponsor ID by username
                    fetch(`/api/user-id?username=${encodeURIComponent(username)}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data && data.id) {
                                sponsorIdInput.value = data.id;
                            } else {
                                sponsorIdInput.value = '';
                            }
                        })
                        .catch(() => {
                            sponsorIdInput.value = '';
                        });
                });
            }

        // ===========================================
        // FORM SUBMISSION & BTCPAY
        // ===========================================
        const btcPayButton = document.getElementById('btcPayButton');
        const memberRegistrationForm = document.getElementById('memberRegistrationForm');
        
        if (btcPayButton && memberRegistrationForm) {
            btcPayButton.addEventListener('click', async function(e) {
                e.preventDefault();
                // Form validation
                if (!memberRegistrationForm.checkValidity()) {
                    memberRegistrationForm.reportValidity();
                    return;
                }
                // Package selection check
                const selectedPackage = document.querySelector('input[name="package"]:checked');
                if (!selectedPackage) {
                    showNotification("Please select a membership package.", 'error');
                    return;
                }
                // Assemble fullname and ensure all name fields are sent
                const first = document.getElementById('firstName')?.value?.trim() || '';
                const middle = document.getElementById('middleName')?.value?.trim() || '';
                const last = document.getElementById('lastName')?.value?.trim() || '';
                const fullname = [first, middle, last].filter(Boolean).join(' ');
                // Update hidden fullname field
                const hiddenFullname = document.getElementById('hiddenFullname');
                if (hiddenFullname) {
                    hiddenFullname.value = fullname;
                }
                // Show loading state
                const originalButtonText = btcPayButton.innerHTML;
                btcPayButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-3"></i> Processing...';
                btcPayButton.disabled = true;
                try {
                    const formData = new FormData(memberRegistrationForm);
                    // Ensure individual name fields are included in FormData
                    formData.set('firstname', first);
                    formData.set('middlename', middle);
                    formData.set('lastname', last);
                    const response = await fetch(memberRegistrationForm.action, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    // Read raw text first for better diagnostics (some servers may return HTML on error)
                    const rawText = await response.text();
                    
                    // If HTTP status indicates failure, show server message (if any) or generic error
                    if (!response.ok) {
                        let parsedErr = null;
                        try {
                            parsedErr = JSON.parse(rawText);
                        } catch (e) {
                            // Not JSON, fall through
                        }
                        const errMsg = (parsedErr && parsedErr.message) ? parsedErr.message : 'Registration failed. Please try again.';
                        showNotification(errMsg, 'error');
                        return;
                    }

                    let result = null;
                    try {
                        result = JSON.parse(rawText);
                    } catch (e) {
                        console.error('Failed to parse registration JSON:', e);
                        showNotification('Invalid server response. Please try again.', 'error');
                        return;
                    }

                    if (result && result.success) {
                        showNotification(result.message || 'Registration successful!', 'success');

                        // Update last direct referral card
                        const lastReferralCard = document.querySelector('.border-teal-500');
                        if (lastReferralCard && result.member) {
                            const nameEl = lastReferralCard.querySelector('.text-lg.font-semibold');
                            if (nameEl) nameEl.textContent = result.member.fullname;
                            const dateEl = lastReferralCard.querySelector('.text-xs.text-gray-500');
                            if (dateEl) dateEl.textContent = 'Joined: Just now';
                        }

                        // Reset form
                        memberRegistrationForm.reset();
                        
                        // Redetect country after reset
                        setTimeout(detectAndSetCountry, 100);

                        // Show BTCPay modal
                        const modalPackageName = document.getElementById('modalPackageName');
                        const modalBtcAmount = document.getElementById('modalBtcAmount');
                        if (modalPackageName && modalBtcAmount && btcPayModal) {
                            modalPackageName.textContent = selectedPackage.dataset.packageName;
                            modalBtcAmount.textContent = selectedPackage.value + ' BTC';
                            btcPayModal.classList.remove('hidden');
                        }
                    } else {
                        showNotification(result && result.message ? result.message : 'Registration failed. Please try again.', 'error');
                    }
                } catch (error) {
                    console.error('Registration error:', error);
                    showNotification('An error occurred during registration. Please try again.', 'error');
                } finally {
                    btcPayButton.innerHTML = originalButtonText;
                    btcPayButton.disabled = false;
                }
            });
        }

        // ===========================================
        // COUNTRY AUTO-DETECTION
        // ===========================================
        function detectAndSetCountry() {
            const countrySelect = document.getElementById('country');
            if (!countrySelect) return;

            countrySelect.disabled = true;

            function setCountry(code) {
                if (!code) return false;
                
                code = code.trim().toUpperCase();
                const option = Array.from(countrySelect.options).find(opt => opt.value.toUpperCase() === code);
                
                if (option) {
                    const loadingOption = countrySelect.querySelector('option[value=""]');
                    if (loadingOption) loadingOption.remove();
                    
                    option.selected = true;
                    countrySelect.value = option.value;
                    countrySelect.disabled = false;
                    countrySelect.dispatchEvent(new Event('change'));
                    return true;
                }
                return false;
            }

            // Try IP-based detection first
            fetch('https://ipapi.co/json/')
                .then(response => response.json())
                .then(data => {
                    const success = setCountry(data.country_code);
                    if (!success) throw new Error('Country not found in list');
                })
                .catch(err => {
                    console.warn('Primary GeoIP detection failed:', err);
                    
                    // Fallback to browser locale
                    try {
                        const locale = navigator.language || navigator.userLanguage;
                        let countryCode = null;
                        
                        if (locale) {
                            countryCode = locale.split('-')[1] || locale.split('_')[1];
                        }
                        
                        if (countryCode && !setCountry(countryCode)) {
                            throw new Error('Browser locale country not found in list');
                        }
                    } catch (e) {
                        console.warn('Browser locale detection failed:', e);
                        const loadingOption = countrySelect.querySelector('option[value=""]');
                        if (loadingOption) loadingOption.remove();
                        countrySelect.disabled = false;
                    }
                });
        }

        // Detect country on page load
        detectAndSetCountry();

        // Handle form reset - redetect country
        if (memberRegistrationForm) {
            memberRegistrationForm.addEventListener('reset', () => {
                setTimeout(detectAndSetCountry, 100);
            });
        }

        // ===========================================
        // CHART.JS - EARNINGS CHART
        // ===========================================
        const earningsChartCtx = document.getElementById('earningsChart');
        if (earningsChartCtx && typeof Chart !== 'undefined') {
            // Store chart instance globally for theme updates
            window.dashboardChart = new Chart(earningsChartCtx, {
                type: 'bar',
                data: {
                    labels: ['L1', 'L2', 'L3', 'L4', 'L5', 'Bonus', 'Override'],
                    datasets: [{
                        label: 'Commissions Earned (BTC)',
                        data: [0.35, 0.28, 0.15, 0.08, 0.03, 0.12, 0.05],
                        backgroundColor: [
                            '#10B981', '#3B82F6', '#10B981', '#3B82F6', '#10B981', '#F7931A', '#1D4ED8'
                        ],
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false, labels: { color: document.documentElement.classList.contains('dark') ? '#e5e7eb' : '#374151' } },
                        tooltip: {
                            backgroundColor: document.documentElement.classList.contains('dark') ? '#1f2937' : '#fff',
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.raw.toFixed(3) + ' BTC';
                                }
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: document.documentElement.classList.contains('dark') ? '#e5e7eb' : '#374151' } },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: document.documentElement.classList.contains('dark') ? '#e5e7eb' : '#374151',
                                callback: function(value) {
                                    return value.toFixed(2) + ' BTC';
                                }
                            }
                        }
                    }
                }
            });
        }
    });
    </script>
    <?php
    if (!isset($_SESSION)) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrf_token = $_SESSION['csrf_token'];
    ?>
    <script src="/assets/js/sponsor-typeahead.js"></script>
    <?php if (isset(
    $direct_referrals_json) && $direct_referrals_json): ?>
<script>
window.INITIAL_DIRECT_REFERRALS = <?= $direct_referrals_json ?>;
</script>
<?php endif; ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Sponsor Typeahead Initialization
        window.initSponsorTypeahead({
            inputId: 'sponsorInput',
            apiUrl: '/api/user/network-search',
            csrfToken: '<?= $csrf_token ?>',
            initialList: window.INITIAL_DIRECT_REFERRALS || [],
            onSelect: function(user) {
                document.getElementById('sponsorInput').value = user.username;
                document.getElementById('sponsorId').value = user.id;
            }
        });
    });
    </script>
</body>
</html>
