<?php 
$csrfToken = getCsrfToken();
$isUserLoggedIn = isset($_SESSION['user']);
$username = $isUserLoggedIn ? $_SESSION['user'] : null;
// Display part of email or full username based on your preference
$userDisplay = $isUserLoggedIn ? (strpos($_SESSION['user'], '@') ? explode('@', $_SESSION['user'])[0] : $_SESSION['user']) : 'Guest';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
        <link href="/assets/favicon/favicon.ico" rel="shortcut icon" type="image/x-icon" />
    <title>Dashboard - Sai</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        facebook: {
                            DEFAULT: '#1877F2',
                            dark: '#0B64C9',
                            light: '#E7F3FF'
                        },
                        dark: { // Custom dark theme palette colors
                            800: '#1E1E1E',
                            700: '#2D2D2D',
                            600: '#3A3A3A',
                            500: '#4A4A4A'
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/client/css/theme.css">
    <style>
        /* Combined and Harmonized Custom Styles */

        /* Base Dashboard Styles */
        .gradient-bg {
            background: linear-gradient(135deg, #6b73ff 0%, #000dff 100%);
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .sidebar-item:hover .sidebar-icon {
            transform: scale(1.2);
        }
        .chart-container {
            height: 250px;
        }
        
        /* Custom scrollbar from Profile Page (more comprehensive) */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1; /* Light mode track */
        }
        ::-webkit-scrollbar-thumb {
            background: #888; /* Light mode thumb */
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #555; /* Light mode thumb hover */
        }
        
        .dark ::-webkit-scrollbar-track {
            background: #2D2D2D; /* Dark mode track */
        }
        .dark ::-webkit-scrollbar-thumb {
            background: #555; /* Dark mode thumb */
        }
        .dark ::-webkit-scrollbar-thumb:hover {
            background: #777; /* Dark mode thumb hover */
        }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

        /* Story gradient (if used elsewhere) */
        .story-gradient {
            background: linear-gradient(to bottom, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);
        }

        /* Custom animations */
        @keyframes fadeInProfile { /* Renamed to avoid conflict if dashboard's fadeIn is different */
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .fade-in { /* From Profile Page */
            animation: fadeInProfile 0.3s ease-in;
        }

        @keyframes fadeInDashboard { /* Dashboard's animation */
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { /* From Dashboard */
            animation: fadeInDashboard 0.5s ease-out forwards;
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-dark-800 font-sans transition-colors duration-200">
    <!-- Fixed Header from Profile Page -->
<?php
require_once 'htmlparts/header.php';
?>

    <!-- Chatbox Container -->
    <div id="chatboxContainer" class="chatbox-container">
        <!-- Chatboxes will be added here dynamically -->
    </div>

    <!-- Dashboard Content -->
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <div class="hidden md:flex md:flex-shrink-0">
            <div class="flex flex-col w-64 gradient-bg text-white sidebar-container">
                <div class="flex items-center justify-center h-20 px-4">
                    <div class="flex items-center">
                        <i class="fas fa-dollar text-2xl mr-2"></i>
                        <span class="text-xl font-semibold">Reward Center</span>
                    </div>
                </div>
                <div class="flex flex-col flex-grow px-4 overflow-y-auto scrollbar-hide">
                <?php
                    require_once 'htmlparts/dashboard_sidebar.php';
                    ?>
                    <div class="mt-auto pb-4">
                        <div class="p-4 bg-blue-800 rounded-lg">
                            <h4 class="text-sm font-semibold mb-2">Need Help?</h4>
                            <p class="text-xs text-blue-200 mb-3">Our team is here to help you with any questions about TailwindCSS.</p>
                            <button class="w-full bg-white text-blue-800 py-2 px-4 rounded-md text-sm font-medium hover:bg-gray-100 transition-colors duration-200">
                                Contact Support
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- NEW: Grow Network Referral Modal -->
        <div id="referralModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4 transition-opacity duration-300" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div id="referralModalContent" class="bg-white dark:bg-dark-700 rounded-xl shadow-2xl w-full max-w-md transform transition-all duration-300 scale-95 opacity-0">
                <!-- Modal Header -->
                <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-dark-600">
                    <h3 id="modal-title" class="text-lg font-semibold text-gray-800 dark:text-white">Grow Your Network</h3>
                    <button id="closeReferralModalBtn" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <!-- Modal Body -->
                <div class="p-6">
                    <p class="text-center text-gray-600 dark:text-gray-400 mb-4">
                        Share this unique link with friends to invite them to our platform and earn rewards!
                    </p>
                    <div class="flex items-center p-2 border border-gray-300 dark:border-dark-600 rounded-lg bg-gray-100 dark:bg-dark-600">
                        <input id="referralModalLinkInput" type="text" readonly class="flex-grow bg-transparent text-gray-700 dark:text-gray-300 focus:outline-none" value="<?= htmlspecialchars($rewards['referral_link'] ?? '') ?>">
                        <button id="copyModalLinkBtn" class="ml-2 bg-blue-600 text-white py-2 px-4 rounded-md text-sm font-medium hover:bg-blue-700 transition-colors duration-200 flex-shrink-0">
                            Copy
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto scrollbar-hide mt-[60px]"> <!-- Added scrollbar-hide for main content area if preferred -->
            <main class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">

                    <!-- NEW: Referrals Stats Card -->
                    <div class="bg-white dark:bg-dark-700 rounded-xl shadow-sm p-6 card-hover transition-all duration-300 animate-fade-in">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-teal-100 dark:bg-teal-900 text-teal-600 dark:text-teal-300">
                                <i class="fas fa-user-plus text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Your Affiliates</h3>
                                <p class="text-2xl font-semibold text-gray-800 dark:text-white">
                                    <?= htmlspecialchars($rewards['total_referrals'] ?? 0) ?>
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center text-sm text-gray-500 dark:text-gray-400">
                            <i class="fas fa-star mr-1 text-yellow-400"></i>
                            <span>
                                <?= htmlspecialchars($rewards['reward_points'] ?? 0) ?> Reward Points
                            </span>
                        </div>
                    </div>


                    <!-- Stats Cards -->
                    <div class="bg-white dark:bg-dark-700 rounded-xl shadow-sm p-6 card-hover transition-all duration-300 animate-fade-in">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-300">
                                <i class="fas fa-hammer text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Productivity Tools</h3>
                                <p class="text-2xl font-semibold text-gray-800 dark:text-white">SmartFi</p>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center text-sm text-green-500">
                            <i class="fas fa-arrow-up mr-1"></i>
                            <span><a href="/smartfi">View SmartFi Vendo</a></span>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-dark-700 rounded-xl shadow-sm p-6 card-hover transition-all duration-300 animate-fade-in" style="animation-delay: 0.1s;">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900 text-purple-600 dark:text-purple-300">
                                <i class="fas fa-building-wheat text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Products unlocked</h3>
                                <p class="text-2xl font-semibold text-gray-800 dark:text-white">2</p>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center text-sm text-green-500">
                            <i class="fas fa-arrow-up mr-1"></i>
                            <span>Earn 3.2% on typing tutor & Sai Code on your members. Subscription starts at $12.00.</span>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-dark-700 rounded-xl shadow-sm p-6 card-hover transition-all duration-300 animate-fade-in" style="animation-delay: 0.2s;">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 dark:bg-green-900 text-green-600 dark:text-green-300">
                                <i class="fas fa-users text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">My network</h3>
                                <p class="text-2xl font-semibold text-gray-800 dark:text-white"><?=htmlspecialchars($rewards['reward_points'] ?? 0)?></p>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center text-sm text-red-500">
                            <i class="fas fa-arrow-down mr-1"></i>
                            <span>1.4% decrease</span>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-dark-700 rounded-xl shadow-sm p-6 card-hover transition-all duration-300 animate-fade-in" style="animation-delay: 0.3s;">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 dark:bg-yellow-900 text-yellow-600 dark:text-yellow-300">
                                <i class="fas fa-dollar-sign text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Revenue</h3>
                                <p class="text-2xl font-semibold text-gray-800 dark:text-white">$0.00</p>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center text-sm text-green-500">
                            <i class="fas fa-arrow-up mr-1"></i>
                            <span>8.7% increase</span>
                        </div>
                    </div>
                     <div class="bg-white dark:bg-dark-700 rounded-xl shadow-sm p-6 card-hover transition-all duration-300 animate-fade-in" style="animation-delay: 0.3s;">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 dark:bg-yellow-900 text-yellow-600 dark:text-yellow-300">
                                <i class="fas fa-people-roof text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">SmartFed Leader</h3>
                                <p class="text-2xl font-semibold text-gray-800 dark:text-white">Earn $1k/m</p>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center text-sm text-green-500">
                            <i class="fas fa-arrow-up mr-1"></i>
                            <span>8.7% increase</span>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-dark-700 rounded-xl shadow-sm p-6 card-hover transition-all duration-300 animate-fade-in" style="animation-delay: 0.2s;">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-300">
                                <i class="fas fa-users-rays text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">SmartFed Member</h3>
                                <p class="text-2xl font-semibold text-gray-800 dark:text-white">Earn $200/m</p>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center text-sm text-red-500">
                            <i class="fas fa-arrow-down mr-1"></i>
                            <span>1.4% decrease</span>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-dark-700 rounded-xl shadow-sm p-6 card-hover transition-all duration-300 animate-fade-in" style="animation-delay: 0.2s;">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-pink-100 dark:bg-pink-900 text-pink-600 dark:text-pink-300">
                                <i class="fas fa-charging-station text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Power Bank Pro</h3>
                                <p class="text-2xl font-semibold text-gray-800 dark:text-white">Solar Grid @ $20k</p>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center text-sm text-red-500">
                            <i class="fas fa-arrow-down mr-1"></i>
                            <span>1.4% decrease</span>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div class="bg-white dark:bg-dark-700 rounded-xl shadow-sm p-6 animate-fade-in" style="animation-delay: 0.4s;">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Market impression</h2>
                            <div class="flex space-x-2">
                                <button class="px-3 py-1 text-xs bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-md">Week</button>
                                <button class="px-3 py-1 text-xs bg-gray-100 dark:bg-dark-600 text-gray-800 dark:text-gray-200 rounded-md">Month</button>
                                <button class="px-3 py-1 text-xs bg-gray-100 dark:bg-dark-600 text-gray-800 dark:text-gray-200 rounded-md">Year</button>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="usageChart"></canvas>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-dark-700 rounded-xl shadow-sm p-6 animate-fade-in" style="animation-delay: 0.5s;">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Project Status</h2>
                            <div class="flex space-x-2">
                                <button class="px-3 py-1 text-xs bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-md">Active</button>
                                <button class="px-3 py-1 text-xs bg-gray-100 dark:bg-dark-600 text-gray-800 dark:text-gray-200 rounded-md">All</button>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Projects -->
                <div class="bg-white dark:bg-dark-700 rounded-xl shadow-sm overflow-hidden animate-fade-in" style="animation-delay: 0.6s;">

                    <!-- NEW: Recent Referrals & CTA Section -->
                    <div class="bg-white dark:bg-dark-700 rounded-xl shadow-sm overflow-hidden animate-fade-in mt-6" style="animation-delay: 0.7s;">
                        
                        <?php if (empty($rewards['recent_referrals'])): ?>
                            
                            <!-- CTA when there are NO referrals -->
                            <div class="px-6 py-8 text-center">
                                <div class="mx-auto h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-blue-800 dark:text-blue-200 mb-4">
                                    <i class="fas fa-share-alt text-2xl"></i>
                                </div>
                                <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-2">Grow the Community & Earn Rewards!</h2>
                                <p class="text-gray-600 dark:text-gray-400 mb-4">Share your unique link with friends. You'll earn points for every new user who joins.</p>
                                <div class="max-w-md mx-auto">
                                    <div class="flex items-center p-2 border border-gray-300 dark:border-dark-600 rounded-lg bg-gray-50 dark:bg-dark-800">
                                        <input id="referralLinkInput" type="text" readonly class="flex-grow bg-transparent text-gray-700 dark:text-gray-300 focus:outline-none" value="<?= htmlspecialchars($rewards['referral_link'] ?? '') ?>">
                                        <button id="copyLinkBtn" class="ml-2 bg-blue-600 text-white py-2 px-4 rounded-md text-sm font-medium hover:bg-blue-700 transition-colors duration-200">
                                            Copy Link
                                        </button>
                                    </div>
                                </div>
                            </div>

                        <?php else: ?>
                        
                            <!-- Display list when there ARE referrals -->
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-dark-600">
                                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Recent Affiliates</h2>
                            </div>
                            <div class="divide-y divide-gray-200 dark:divide-dark-600">
                                <?php foreach ($rewards['recent_referrals'] as $referral): ?>
                                    <div class="p-6 hover:bg-gray-50 dark:hover:bg-dark-600 transition-colors duration-200">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                
                                                <?php if (!empty($referral['profile_picture'])): ?>
                                                    <img class="h-10 w-10 rounded-full object-cover" src="<?= htmlspecialchars($referral['profile_picture']) ?>" alt="<?= htmlspecialchars($referral['full_name']) ?>">
                                                <?php else: ?>
                                                    <div class="h-10 w-10 rounded-full bg-gray-200 dark:bg-dark-500 flex items-center justify-center text-gray-500 dark:text-gray-400 font-bold">
                                                        <?= strtoupper(substr($referral['full_name'], 0, 1)) ?>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="ml-4">
                                                    <h3 class="font-medium text-gray-800 dark:text-white"><?= htmlspecialchars($referral['full_name']) ?></h3>
                                                    <p class="text-sm text-gray-500 dark:text-gray-400">Joined on <?= date('M d, Y', strtotime($referral['created_at'])) ?></p>
                                                </div>
                                            </div>
                                            <div class="flex items-center">
                                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">Completed</span>
                                                <a href="/profile/<?= (int)$referral['id'] ?>" class="ml-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="px-6 py-4 border-t border-gray-200 dark:border-dark-600 text-center">
                                <button class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium">
                                    View All Affiliates
                                </button>
                            </div>
                            
                        <?php endif; ?>
                    </div>

                    <div class="px-6 py-4 border-b border-gray-200 dark:border-dark-600">
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Recent Projects</h2>
                    </div>
                    <div class="divide-y divide-gray-200 dark:divide-dark-600">
                        <div class="p-6 hover:bg-gray-50 dark:hover:bg-dark-600 transition-colors duration-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-blue-800 dark:text-blue-200">
                                        <i class="fas fa-laptop-code"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h3 class="font-medium text-gray-800 dark:text-white">Dashboard UI</h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Created 3 days ago</p>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">Active</span>
                                    <button class="ml-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                         <div class="p-6 hover:bg-gray-50 dark:hover:bg-dark-600 transition-colors duration-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="h-10 w-10 rounded-full bg-purple-100 dark:bg-purple-900 flex items-center justify-center text-purple-800 dark:text-purple-200">
                                        <i class="fas fa-mobile-alt"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h3 class="font-medium text-gray-800 dark:text-white">Mobile App</h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Created 1 week ago</p>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">Pending</span>
                                    <button class="ml-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-dark-600 text-center">
                        <button class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium">
                            View All Projects
                        </button>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Mobile Bottom Navigation (from Profile Page, optional for dashboard) -->
    <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white dark:bg-dark-700 shadow-lg border-t border-gray-200 dark:border-dark-600 z-50">
        <div class="flex justify-around py-2">
            <a href="/" class="p-3 text-gray-500 dark:text-gray-400" aria-label="Home">
                <i class="fas fa-home text-xl"></i>
            </a>
            <a href="/dashboard" class="p-3 text-facebook" aria-label="Dashboard"> <!-- Active for dashboard -->
                <i class="fas fa-dashboard text-xl"></i>
            </a>
            <a href="#" id="growBtn" class="p-3 text-gray-500 dark:text-gray-400" aria-label="Marketplace">
                <i class="fas fa-comments-dollar text-xl"></i>
            </a>
            <a href="#" class="p-3 text-gray-500 dark:text-gray-400" aria-label="Notifications">
                <i class="fas fa-bell text-xl"></i>
            </a>
            <a href="#" id="mobileMenuBtn" class="p-3 text-gray-500 dark:text-gray-400" aria-label="Menu"> <!-- Consider connecting this to userMenuBtn logic or a mobile-specific menu -->
                <i class="fas fa-bars text-xl"></i>
            </a>
        </div>
    </nav>

    <!-- Ringing Call Modal -->
    <div id="ringingCallModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 dark:bg-opacity-75 hidden transition-opacity duration-300 ease-in-out opacity-0">
        <div id="ringingCallModalContent" class="bg-white dark:bg-dark-700 p-6 sm:p-8 rounded-xl shadow-2xl text-center w-full max-w-xs sm:max-w-sm transform scale-95 transition-all duration-300 ease-in-out">
            <img id="ringingCallerAvatar" src="" alt="Caller Avatar" class="w-20 h-20 sm:w-24 sm:h-24 rounded-full object-cover mx-auto mb-4 border-4 border-gray-200 dark:border-dark-600 shadow-md">
            <h3 id="ringingCallerName" class="text-xl sm:text-2xl font-semibold text-gray-800 dark:text-white mb-1"></h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Incoming video call...</p>
            <div class="flex justify-around space-x-3">
                <button id="declineCallButton" type="button" class="flex-1 bg-red-500 hover:bg-red-600 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-opacity-50 transition-colors duration-150 ease-in-out flex items-center justify-center space-x-2">
                    <i class="fas fa-phone-slash"></i>
                    <span>Decline</span>
                </button>
                <button id="acceptCallButton" type="button" class="flex-1 bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-opacity-50 transition-colors duration-150 ease-in-out flex items-center justify-center space-x-2">
                    <i class="fas fa-phone"></i>
                    <span>Accept</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Full Screen Video Call Modal -->
    <div id="fullScreenVideoModal" class="fixed inset-0 bg-black bg-opacity-90 flex flex-col items-center justify-center z-50 hidden">
        <div class="relative w-full h-full max-w-full max-h-full">
            <span id="fullScreenVideoStatusOverlay" 
                class="absolute top-4 left-4 z-10 text-white text-lg bg-black bg-opacity-70 py-2 px-4 rounded-md shadow-lg">
            </span>
            <video id="fullScreenRemoteVideo" autoplay playsinline class="w-full h-full object-contain"></video>
            <video id="fullScreenLocalVideo" autoplay playsinline muted class="absolute bottom-4 right-4 w-1/5 max-w-[200px] h-auto bg-gray-800 rounded border-2 border-white shadow-md"></video>

            <div id="fullScreenVideoControls" class="absolute bottom-4 left-1/2 transform -translate-x-1/2 p-3 bg-black bg-opacity-60 rounded-xl flex justify-center items-center space-x-4 z-20">
                <button type="button" id="fullScreenToggleMicBtn" class="video-control-btn text-xl" aria-label="Mute microphone"><i class="fas fa-microphone"></i></button>
                <button type="button" id="fullScreenHangupBtn" class="bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded-full text-xl" aria-label="Hang up"><i class="fas fa-phone-slash"></i></button>
                <button type="button" id="fullScreenToggleCameraBtn" class="video-control-btn text-xl" aria-label="Disable camera"><i class="fas fa-video"></i></button>
                <button type="button" id="fullScreenMinimizeBtn" class="video-control-btn text-xl ml-auto" aria-label="Minimize video"><i class="fas fa-compress"></i></button>
            </div>
        </div>
    </div>

    <!-- Create Group Modal -->
    <div id="createGroupModal" 
        class="fixed inset-0 z-[60] hidden 
                flex items-center justify-center
                bg-black bg-opacity-50 
                transition-opacity duration-300 ease-in-out opacity-0">

        <div id="createGroupModalContent" 
            class="bg-white dark:bg-dark-800 p-5 sm:p-6 rounded-lg shadow-xl 
                    w-full max-w-md 
                    transform transition-all duration-300 ease-in-out scale-95">
            
            <!-- Header -->
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Create New Group</h3>
                <button type="button" id="closeCreateGroupModalBtn" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                    <span class="sr-only">Close</span>
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Group Name Input -->
            <div>
                <label for="groupNameInput" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Group Name <span class="text-red-500">*</span></label>
                <input type="text" id="groupNameInput" maxlength="100" class="mt-1 block w-full px-3 py-2 bg-white dark:bg-dark-700 border border-gray-300 dark:border-dark-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Enter group name">
            </div>

            <!-- Group Icon URL Input (Optional) -->
            <div class="mt-4">
                <label for="groupIconUrlInput" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Group Icon URL (Optional)</label>
                <input type="url" id="groupIconUrlInput" class="mt-1 block w-full px-3 py-2 bg-white dark:bg-dark-700 border border-gray-300 dark:border-dark-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="https://example.com/icon.png">
            </div>

            <!-- Add Participants Section -->
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Add Participants <span class="text-red-500">*</span></label>
                <div class="relative">
                    <input type="text" id="groupParticipantSearchInput" class="mt-1 block w-full px-3 py-2 bg-white dark:bg-dark-700 border border-gray-300 dark:border-dark-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Search users to add...">
                    <div id="groupParticipantSearchDropdown" class="absolute z-[70] mt-1 w-full bg-white dark:bg-dark-700 rounded-md shadow-lg hidden ring-1 ring-black ring-opacity-5 dark:ring-gray-700 max-h-48 sm:max-h-60 overflow-y-auto">
                        <ul id="groupParticipantSearchResults" class="py-1">
                            <!-- Search results populated here -->
                        </ul>
                    </div>
                </div>
                <div id="selectedGroupParticipants" class="mt-2 space-y-1 max-h-24 sm:max-h-32 overflow-y-auto p-1 border dark:border-dark-600 rounded-md empty:hidden">
                    <!-- Selected participants listed here -->
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" id="cancelCreateGroupBtn" class="px-3 sm:px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-dark-700 border border-gray-300 dark:border-dark-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-dark-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Cancel
                </button>
                <button type="button" id="submitCreateGroupBtn" class="px-3 sm:px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 border border-transparent rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Create Group
                </button>
            </div>
        </div>
    </div>

    <!--Notifications Modal-->
    <div id="notificationContentModal" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center hidden z-50" role="dialog" aria-modal="true" aria-labelledby="notificationModalTitle" aria-hidden="true">
        <!--
            Removed: overflow-y-auto from the main overlay.
            The main overlay should just center the modal dialog.
        -->
        <div class="relative p-6 border w-11/12 md:w-3/4 lg:w-2/3 xl:w-1/2 shadow-xl rounded-lg bg-white dark:bg-dark-800 transform transition-all sm:my-8 flex flex-col max-h-[90vh]">
            <!--
                Added: flex flex-col (to make children (header, body, footer) stack vertically and allow body to grow/shrink)
                Added: max-h-[90vh] (sets a maximum height for the entire modal dialog, e.g., 90% of viewport height)
                    Adjust 90vh as needed (e.g., max-h-[600px], max-h-[80vh]).
            -->

            <!-- Modal Header -->
            <div class="flex justify-between items-center pb-3 border-b dark:border-dark-600 flex-shrink-0">
                <!--
                    Added: flex-shrink-0 (so the header doesn't shrink if content is large)
                -->
                <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100" id="notificationModalTitle">
                    Notification Details
                </h3>
                <button id="notificationModalCloseBtn"
                        type="button"
                        class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center dark:hover:bg-dark-600 dark:hover:text-white"
                        aria-label="Close modal">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="mt-4 overflow-y-auto flex-grow">
                <!--
                    Added: overflow-y-auto (this makes THIS specific div scrollable if its content exceeds its allocated space)
                    Added: flex-grow (this allows the body to take up available vertical space between header and footer)
                -->
                <div id="notificationModalBody"> <!-- This inner div is where your JS injects content -->
                    <p class="text-gray-700 dark:text-gray-300">Loading notification content...</p>
                    <!-- Dynamic content will be injected here -->
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="mt-6 pt-4 border-t dark:border-dark-600 flex justify-end space-x-3 flex-shrink-0">
                <button id="notificationModalDeclineBtn" type="button" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-dark-800 hidden">
                    Decline
                </button>
                <button id="notificationModalAcceptBtn" type="button" class="px-4 py-2 text-sm font-medium text-white bg-green-500 hover:bg-green-600 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:focus:ring-offset-dark-800 hidden">
                    Accept
                </button>
                <!-- MODIFIED "View Link" / "View Profile" Button -->
                <a href="#" id="notificationModalViewLink" target="_blank" rel="noopener noreferrer"
                   class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-blue-700 focus:outline-none">
                    <!-- Text content ("View Profile", "Close", "View Original Context") will be dynamically injected by notification-manager.js -->
                </a>
                <!-- END OF MODIFIED Button -->
                <!-- You can remove notificationModalSecondaryActionBtn if it's no longer used -->
                <!--
                <button id="notificationModalSecondaryActionBtn" type="button" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-dark-700 hover:bg-gray-200 dark:hover:bg-dark-600 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-dark-800 hidden">
                    Secondary Action
                </button>
                -->
            </div>


        </div>
    </div>

    <script>
    window.APP_USER_ID = <?php echo json_encode($_SESSION['user_id'] ?? null); ?>;
    window.APP_USER_FULL_NAME = <?php echo json_encode($_SESSION['user_full_name'] ?? 'You'); ?>;
    window.APP_USER_AVATAR = <?php echo json_encode($_SESSION['user_profile_picture'] ?? null); ?>;
    const currentUserData = {
        id: <?php echo json_encode($_SESSION['user_id'] ?? null); ?>,
        fullName: <?php echo json_encode($_SESSION['user_full_name'] ?? 'Guest'); ?>,
        username: <?php echo json_encode($_SESSION['user_username'] ?? 'guest'); ?>,
        profilePicture: <?php echo json_encode($_SESSION['user_profile_picture'] ?? null); ?>
    };
    </script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- ProfileApp JavaScript (handles header, chat, dark mode, search) -->
    <script>




    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/require.js/2.3.6/require.min.js"></script>
    <script src="/assets/client/js/dashboard.js"></script>
    <script src="/assets/client/js/headmanager.js"></script>
    <script src="/assets/client/js/notifications.js"></script>
    <script src="/assets/client/js/typeahead-chat.js"></script>

    
</body>
</html>