<main class="p-4 md:p-6 lg:p-8 bg-white dark:bg-gray-900">
<?php
// Get admin display name from header.php or use default
$welcomeName = $GLOBALS['adminDisplayName'] ?? 'Admin';
// Extract first name for greeting
$firstNameForGreeting = explode(' ', $welcomeName)[0];
?>
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
            Welcome back, <?= htmlspecialchars($firstNameForGreeting) ?>! 👋
        </h1>
        <p class="text-gray-600 dark:text-gray-400">
            Here's what's happening with your network today.
        </p>
    </div>    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Stat 1: Total Revenue -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 hover:shadow-xl transition-shadow">
            <div class="flex items-start justify-between mb-4">
                <div class="p-3 rounded-lg bg-gradient-to-br from-yellow-500 to-yellow-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6 text-white">
                        <line x1="12" x2="12" y1="2" y2="22"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
                <span class="text-green-500 text-sm font-semibold">+12.5%</span>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mb-1">$124,563</p>
            <p class="text-sm text-gray-600 dark:text-gray-400">Total Revenue</p>
        </div>
        <!-- Stat 2: Active Members -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 hover:shadow-xl transition-shadow">
            <div class="flex items-start justify-between mb-4">
                <div class="p-3 rounded-lg bg-gradient-to-br from-blue-500 to-blue-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6 text-white">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <span class="text-green-500 text-sm font-semibold">+8.2%</span>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mb-1">2,847</p>
            <p class="text-sm text-gray-600 dark:text-gray-400">total Members</p>
        </div>
        <!-- Stat 3: Network Size -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 hover:shadow-xl transition-shadow">
            <div class="flex items-start justify-between mb-4">
                <div class="p-3 rounded-lg bg-gradient-to-br from-green-500 to-green-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6 text-white">
                        <polyline points="22 7 13.5 15.5 10 12 2 20"/>
                        <polyline points="16 7 22 7 22 13"/>
                    </svg>
                </div>
                <span class="text-green-500 text-sm font-semibold">+15.3%</span>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mb-1"><?php echo $stats['total_users']; ?></p>
            <p class="text-sm text-gray-600 dark:text-gray-400">Network Size</p>
        </div>
        <!-- Stat 4: Achievements -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 hover:shadow-xl transition-shadow">
            <div class="flex items-start justify-between mb-4">
                <div class="p-3 rounded-lg bg-gradient-to-br from-purple-500 to-purple-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6 text-white">
                        <circle cx="12" cy="12" r="10"/><path d="M12 17.5V12"/>
                        <path d="m14.1 10.1 2.5 1.5-2.5 1.5"/><path d="m9.9 10.1-2.5 1.5 2.5 1.5"/>
                    </svg>
                </div>
                <span class="text-green-500 text-sm font-semibold">+3</span>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mb-1">47</p>
            <p class="text-sm text-gray-600 dark:text-gray-400">Achievements</p>
        </div>
    </div>

    <!-- Charts and Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Inside the performance overview card -->
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                    Performance Overview
                </h2>
                <button id="time-range-button" class="flex items-center space-x-2 px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                    <span>This Month</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                        <path d="m6 9 6 6 6-6"/>
                    </svg>
                </button>
            </div>
            
            <!-- REPLACED STATIC GRAPH WITH CANVAS -->
            <div class="h-64">
                <!-- Chart.js will render the bar chart here -->
                <canvas id="performanceChart"></canvas>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
                Recent Activity
            </h2>
            <div class="space-y-4">
                <!-- Activity 1 -->
                <div class="flex items-start space-x-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center text-white font-semibold text-sm flex-shrink-0">
                        SJ
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                            Sarah Johnson
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-400 truncate">
                            joined your network
                        </p>
                        <p class="text-xs text-gray-500 mt-1">2m ago</p>
                    </div>
                </div>
                <!-- Activity 2 -->
                <div class="flex items-start space-x-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center text-white font-semibold text-sm flex-shrink-0">
                        MC
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                            Mike Chen
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-400 truncate">
                            achieved Gold Rank
                        </p>
                        <p class="text-xs text-gray-500 mt-1">15m ago</p>
                    </div>
                </div>
                <!-- Activity 3 -->
                <div class="flex items-start space-x-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center text-white font-semibold text-sm flex-shrink-0">
                        ED
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                            Emma Davis
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-400 truncate">
                            made a sale of $450
                        </p>
                        <p class="text-xs text-gray-500 mt-1">1h ago</p>
                    </div>
                </div>
                <!-- Activity 4 -->
                <div class="flex items-start space-x-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center text-white font-semibold text-sm flex-shrink-0">
                        JW
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                            James Wilson
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-400 truncate">
                            recruited 3 new members
                        </p>
                        <p class="text-xs text-gray-500 mt-1">2h ago</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
