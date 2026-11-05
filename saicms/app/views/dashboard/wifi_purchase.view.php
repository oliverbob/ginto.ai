<?php
// This is the only PHP block needed at the very top of your file.
$csrfToken = getCsrfToken();

if (!function_exists('getCsrfToken')) {
    function getCsrfToken() { 
        if (session_status() == PHP_SESSION_NONE) { session_start(); }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <!-- Head content is the same, no changes needed -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <link href="/assets/favicon/favicon.ico" rel="shortcut icon" type="image/x-icon" />
    <title>SmartFi Top Up - Sai</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: { 800: '#1E1E1E', 700: '#2D2D2D', 600: '#3A3A3A', 500: '#4A4A4A' }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/client/css/theme.css">
    <style>
        /* Combined and Harmonized Custom Styles */
        .gradient-bg { background: linear-gradient(135deg, #6b73ff 0%, #000dff 100%); }
        .card-hover:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
        .sidebar-item:hover .sidebar-icon { transform: scale(1.2); }
        .chart-container { height: 250px; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #888; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #555; }
        .dark ::-webkit-scrollbar-track { background: #2D2D2D; }
        .dark ::-webkit-scrollbar-thumb { background: #555; }
        .dark ::-webkit-scrollbar-thumb:hover { background: #777; }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
    <!-- IMPORTANT: Make sure Chart.js is included for the graphs to work -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-dark-800 font-sans">

    <!-- Chatbox Container -->
    <div id="chatboxContainer" class="chatbox-container">
        <!-- Chatboxes will be added here dynamically -->
    </div>

    <!-- Fixed Header -->
    <?php require_once __DIR__ . '/../htmlparts/header.php'; ?>

    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <div class="hidden md:flex md:flex-shrink-0">
            <div class="flex flex-col w-64 gradient-bg text-white">
                 <div class="flex items-center justify-center h-20 px-4">
                    <div class="flex items-center"><i class="fas fa-bolt text-2xl mr-2"></i><span class="text-xl font-semibold">SmartFi</span></div>
                </div>
                <div class="flex-grow overflow-y-auto">
                     <?php
                    if (file_exists(__DIR__ . '/../htmlparts/dashboard_sidebar.php')) {
                        require_once __DIR__ . '/../htmlparts/dashboard_sidebar.php';
                    }
                    ?>
                </div>
                 <div class="p-4">
                    <div class="p-4 bg-blue-800 rounded-lg">
                        <h4 class="text-sm font-semibold mb-2">Need Help?</h4>
                        <p class="text-xs text-blue-200 mb-3">Our support team is here to assist you.</p>
                        <button class="w-full bg-white text-blue-800 py-2 px-4 rounded-md text-sm font-medium hover:bg-gray-100">Contact Support</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- END OF SIDEBAR -->

        <!-- Main Content Area -->
        <div class="flex-1 overflow-auto mt-[60px]">
            <?php
            // --- THIS IS THE SINGLE SOURCE OF TRUTH FOR ALL DATA ON THIS PAGE ---
            // This data block provides all the necessary values for the page.
            $wallet_balance = $wallet_balance ?? 12530.50;
            $gift_balance = $gift_balance ?? 500.00;
            $announcement = $announcement ?? [
                'title' => 'System Update',
                'message' => 'New projection models will be deployed this weekend. Minor disruptions may occur.'
            ];
            
            // --- CRITICAL FIX: The $wifi_plans array is now correctly populated to ensure the plan cards are displayed.
            // THIS DATA IS NOW HANDLED IN THE SELF-CONTAINED BLOCK BELOW, BUT IS LEFT HERE AS A FALLBACK.
            $wifi_plans = $wifi_plans ?? [
                ['id' => 1, 'category' => 'Hourly', 'name' => '30 Minutes', 'duration_text' => '30 mins access', 'price' => 20.00, 'usage_hint' => 'For quick social media checks.'],
                ['id' => 2, 'category' => 'Hourly', 'name' => '1 Hour', 'duration_text' => 'Full hour access', 'price' => 15.00, 'usage_hint' => 'Best value for quick browsing.'],
                ['id' => 3, 'category' => 'Hourly', 'name' => '2 Hours', 'duration_text' => 'Extended session', 'price' => 25.00, 'usage_hint' => 'Good for short meetings.'],
                ['id' => 4, 'category' => 'Hourly', 'name' => '5 Hours', 'duration_text' => 'Half-day power session', 'price' => 50.00, 'usage_hint' => 'Perfect for focused work.'],
                ['id' => 5, 'category' => 'Hourly', 'name' => '10 Hours', 'duration_text' => 'Great for long projects', 'price' => 90.00, 'usage_hint' => 'Excellent bulk hour value.'],
                ['id' => 6, 'category' => 'Hourly', 'name' => '12 Hours', 'duration_text' => 'All-day access', 'price' => 100.00, 'usage_hint' => 'Covers your entire workday.'],
                ['id' => 7, 'category' => 'Daily', 'name' => '1 Day', 'duration_text' => '24 hours of internet', 'price' => 190.00, 'usage_hint' => 'Uninterrupted 24-hour access.', 'badge' => 'Most Popular', 'badge_color' => 'bg-green-600'],
                ['id' => 8, 'category' => 'Daily', 'name' => '2 Days', 'duration_text' => '48-hour weekend pass', 'price' => 380.00, 'usage_hint' => 'Cover your entire weekend.'],
                ['id' => 9, 'category' => 'Weekly', 'name' => '5 Days', 'duration_text' => 'Full work week', 'price' => 600.00, 'usage_hint' => 'Ideal for business travelers.'],
                ['id' => 10, 'category' => 'Weekly', 'name' => '7 Days', 'duration_text' => 'Full week of access', 'price' => 800.00, 'badge' => 'Best Value', 'badge_color' => 'bg-yellow-500', 'usage_hint' => 'Our best weekly rate.'],
            ];
            $plan_categories = $plan_categories ?? ['Hourly', 'Daily', 'Weekly'];

            $recent_transactions = $recent_transactions ?? [ ['plan' => '1 Day', 'user' => ['name' => 'John Doe'], 'amount' => 190.00], ['plan' => '1 Hour', 'user' => ['name' => 'Jane Smith'], 'amount' => 15.00], ];
            $top_locations = $top_locations ?? [ ['name' => 'Main Building Lobby', 'users' => 25], ['name' => 'Cafeteria Hotspot', 'users' => 12], ['name' => 'Library Quiet Zone', 'users' => 5], ];
            $revenue_chart_data = $revenue_chart_data ?? ['Jan'=>420, 'Feb'=>510, 'Mar'=>390, 'Apr'=>620, 'May'=>710, 'Jun'=>850, 'Jul'=>920];
            $plan_popularity = $plan_popularity ?? [ ['name' => '1 Day', 'count' => 156, 'color' => '#10B981'], ['name' => '1 Hour', 'count' => 112, 'color' => '#3B82F6'], ['name' => '7 Days', 'count' => 45, 'color' => '#F59E0B'], ['name' => 'Other', 'count' => 30, 'color' => '#6B7280'], ];
            ?>

            <main class="p-4 sm:p-6 lg:p-8">

                <header class="mb-8">
                    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-200">SmartFi vendo top up center</h1>
                            <p class="text-gray-400">Check usage, revenue, rewards and top up for yourself and friends.</p>
                        </div>
                        <div class="flex flex-wrap items-center justify-start sm:justify-end gap-3">
                            <div class="bg-dark-700 p-3 rounded-lg flex items-center gap-4">
                                <div class="flex items-center gap-3"><i class="fas fa-gift text-orange-400 text-xl"></i><div><p class="text-xs text-gray-400">Gift a Friend</p><p class="font-bold text-gray-200">₱<?= htmlspecialchars(number_format($gift_balance)) ?></p></div></div>
                                <button class="bg-orange-500 text-white rounded-md px-3 py-1 text-xs font-bold hover:bg-orange-600 transition flex-shrink-0"><i class="fas fa-paper-plane mr-1"></i> Send Credits</button>
                            </div>
                            <div class="bg-dark-700 p-3 rounded-lg flex items-center gap-4">
                                <div class="flex items-center gap-3"><i class="fas fa-wallet text-teal-400 text-xl"></i><div><p class="text-xs text-gray-400">Your Balance</p><p class="font-bold text-gray-200">₱<?= htmlspecialchars(number_format($wallet_balance)) ?></p></div></div>
                                <button class="bg-green-600 text-white rounded-md px-3 py-1 text-xs font-bold hover:bg-green-700 transition flex-shrink-0"><i class="fas fa-plus mr-1"></i> TOP UP</button>
                            </div>
                            <button class="px-4 py-2 bg-blue-500/20 text-blue-400 rounded-lg text-sm font-medium hover:bg-blue-500/30 transition"><i class="fas fa-chart-line mr-1"></i> Live Analytics</button>
                            <button class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition"><i class="fas fa-download mr-2"></i> Export Report</button>
                        </div>
                    </div>
                </header>
                
                <?php if (isset($announcement) && !empty($announcement)): ?>
                <div class="bg-blue-500/10 border border-blue-500/30 rounded-lg p-4 flex items-center justify-between gap-4 mb-8">
                    <div class="flex items-center gap-3"><i class="fas fa-bullhorn text-blue-400 text-xl"></i><div><h4 class="font-semibold text-gray-200"><?= htmlspecialchars($announcement['title']) ?></h4><p class="text-sm text-gray-400"><?= htmlspecialchars($announcement['message']) ?></p></div></div>
                    <button class="text-gray-500 hover:text-gray-300 text-2xl leading-none">×</button>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <!-- INTEGRATED MOBILE-RESPONSIVE PLANS SECTION -->
                    <?php
                    // Mock data for WiFi plans - This data remains unchanged.
                    $wifi_plans = [
                        ['id' => 1, 'category' => 'Hourly', 'name' => '30 Minutes', 'duration_text' => '30 mins access', 'price' => 20.00, 'usage_hint' => 'For quick social media checks.'],
                        ['id' => 2, 'category' => 'Hourly', 'name' => '1 Hour', 'duration_text' => 'Full hour access', 'price' => 15.00, 'usage_hint' => 'Best value for quick browsing.'],
                        ['id' => 3, 'category' => 'Hourly', 'name' => '2 Hours', 'duration_text' => 'Extended session', 'price' => 25.00, 'usage_hint' => 'Good for short meetings.'],
                        ['id' => 4, 'category' => 'Hourly', 'name' => '3 Hours', 'duration_text' => 'Extended work session', 'price' => 35.00, 'usage_hint' => 'Perfect for longer tasks.'],
                        ['id' => 5, 'category' => 'Hourly', 'name' => '4 Hours', 'duration_text' => 'Half-day access', 'price' => 45.00, 'usage_hint' => 'Great for work from cafe.'],
                        ['id' => 6, 'category' => 'Hourly', 'name' => '6 Hours', 'duration_text' => 'Extended day session', 'price' => 60.00, 'usage_hint' => 'Almost full day coverage.'],
                        ['id' => 7, 'category' => 'Daily', 'name' => '1 Day', 'duration_text' => '24 hours of internet', 'price' => 190.00, 'usage_hint' => 'Uninterrupted 24-hour access.', 'badge' => 'Most Popular', 'badge_color' => 'bg-green-600'],
                        ['id' => 8, 'category' => 'Daily', 'name' => '2 Days', 'duration_text' => '48-hour weekend pass', 'price' => 380.00, 'usage_hint' => 'Cover your entire weekend.'],
                        ['id' => 9, 'category' => 'Daily', 'name' => '3 Days', 'duration_text' => '72-hour extended pass', 'price' => 550.00, 'usage_hint' => 'Long weekend coverage.'],
                        ['id' => 10, 'category' => 'Weekly', 'name' => '7 Days', 'duration_text' => 'Full week of access', 'price' => 800.00, 'badge' => 'Best Value', 'badge_color' => 'bg-yellow-500', 'usage_hint' => 'Our best weekly rate.'],
                        ['id' => 11, 'category' => 'Weekly', 'name' => '14 Days', 'duration_text' => 'Two weeks unlimited', 'price' => 1500.00, 'usage_hint' => 'Extended stay package.'],
                        ['id' => 12, 'category' => 'Weekly', 'name' => '30 Days', 'duration_text' => 'Monthly unlimited access', 'price' => 2800.00, 'usage_hint' => 'Full month coverage.'],
                    ];

                    $plan_categories = ['Hourly', 'Daily', 'Weekly'];
                    ?>

                    <div class="lg:col-span-2 bg-dark-700 rounded-lg p-4 sm:p-6">
                        <h2 class="text-xl font-semibold text-gray-200 mb-6 flex items-center">
                            <i class="fas fa-tags mr-3 text-blue-500"></i>Available Plans
                        </h2>
                        
                        <?php if (empty($wifi_plans)): ?>
                            <div class="text-center py-16 flex flex-col items-center justify-center h-full">
                                <div class="bg-dark-600 h-12 w-12 rounded-full flex items-center justify-center mb-4">
                                    <i class="fas fa-exclamation-circle text-2xl text-gray-500"></i>
                                </div>
                                <h3 class="font-semibold text-lg text-gray-300">No WiFi Plans Available</h3>
                                <p class="text-gray-500">Please check back later.</p>
                            </div>
                        <?php else: ?>
                            
                            <div class="space-y-8">
                                <?php foreach($plan_categories as $category): ?>
                                    <?php 
                                        $plans_in_category = array_filter($wifi_plans, function($plan) use ($category) {
                                            return ($plan['category'] ?? 'General') === $category;
                                        });
                                        
                                        if (empty($plans_in_category)) continue;
                                    ?>
                                    
                                    <div>
                                        <!-- Category Header -->
                                        <div class="flex items-center mb-4">
                                            <span class="<?php 
                                                if($category === 'Hourly') echo 'bg-gradient-to-r from-blue-500 to-cyan-500';
                                                elseif($category === 'Daily') echo 'bg-gradient-to-r from-green-500 to-emerald-500';
                                                else echo 'bg-gradient-to-r from-purple-500 to-pink-500';
                                            ?> text-white px-3 py-1 rounded-full text-sm font-semibold">
                                                <?= htmlspecialchars($category) ?>
                                            </span>
                                            <div class="flex-1 h-px <?php 
                                                if($category === 'Hourly') echo 'bg-gradient-to-r from-blue-500/30 to-transparent';
                                                elseif($category === 'Daily') echo 'bg-gradient-to-r from-green-500/30 to-transparent';
                                                else echo 'bg-gradient-to-r from-purple-500/30 to-transparent';
                                            ?> ml-4"></div>
                                        </div>
                                        
                                        <!-- REFACTORED: Responsive grid for plans -->
                                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                                            <?php foreach ($plans_in_category as $plan): ?>
                                                
                                                <!-- Refactored Card for better spacing and touch targets -->
                                                <div class="relative bg-gradient-to-br from-dark-600 to-dark-500 rounded-lg p-3 hover-<?= strtolower($category) ?>-color transition-all duration-300 group cursor-pointer border border-transparent hover:border-opacity-50 flex flex-col"
                                                     onclick="purchasePlan(<?= $plan['id'] ?>)">
                                                    
                                                    <?php if (isset($plan['badge'])): ?>
                                                        <div class="absolute -top-1 -right-1 w-3 h-3 <?= htmlspecialchars($plan['badge_color']) ?> rounded-full border-2 border-dark-700"
                                                             title="<?= htmlspecialchars($plan['badge']) ?>"></div>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Top Section: Info & Price -->
                                                    <div class="flex items-start justify-between flex-grow">
                                                        <div class="flex-1 mr-2">
                                                            <h3 class="font-bold text-sm text-white group-hover:text-white transition-colors leading-tight">
                                                                <?= htmlspecialchars($plan['name']) ?>
                                                            </h3>
                                                            <p class="text-xs text-gray-400 mt-1 leading-tight"><?= htmlspecialchars($plan['duration_text']) ?></p>
                                                        </div>
                                                        
                                                        <div class="text-right flex-shrink-0">
                                                            <div class="text-lg font-bold text-blue-400 leading-none">₱<?= number_format($plan['price'], 0) ?></div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Bottom Section: Hint & Button -->
                                                    <div class="mt-3 pt-3 border-t border-gray-700/50 flex items-end justify-between">
                                                         <p class="text-xs text-gray-500 italic leading-snug w-2/3"><?= htmlspecialchars($plan['usage_hint']) ?></p>
                                                         <button class="bg-blue-600 hover:bg-blue-500 text-white px-3 py-1 rounded text-xs font-medium transition-colors"
                                                                onclick="event.stopPropagation(); purchasePlan(<?= $plan['id'] ?>)">
                                                            Buy
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                <?php endforeach; ?>
                            </div>
                            
                        <?php endif; ?>
                    </div>
                    <script>
                        function purchasePlan(planId) { console.log('Purchasing plan with ID:', planId); }
                        document.addEventListener('DOMContentLoaded', function() {
                            const categoryColors = { hourly: 'from-blue-600 to-cyan-500 border-blue-500', daily: 'from-green-600 to-emerald-500 border-green-500', weekly: 'from-purple-600 to-pink-500 border-purple-500' };
                            Object.keys(categoryColors).forEach(category => {
                                document.querySelectorAll(`.hover-${category}-color`).forEach(card => {
                                    const colorClass = categoryColors[category];
                                    card.addEventListener('mouseenter', function() {
                                        this.className = this.className.replace('from-dark-600 to-dark-500', colorClass.split(' border-')[0]);
                                        this.className = this.className.replace('border-transparent', colorClass.split(' ')[2]);
                                    });
                                    card.addEventListener('mouseleave', function() {
                                        this.className = this.className.replace(colorClass.split(' border-')[0], 'from-dark-600 to-dark-500');
                                        this.className = this.className.replace(colorClass.split(' ')[2], 'border-transparent');
                                    });
                                });
                            });
                        });
                    </script>
                    <!-- END OF INTEGRATED SECTION -->

                    <div class="lg:col-span-1 space-y-8">
                        <div class="bg-dark-700 rounded-lg p-6">
                            <h2 class="text-lg font-semibold text-gray-200 mb-4 flex items-center"><i class="fas fa-history mr-3 text-blue-500"></i>Recent Activity</h2>
                            <ul class="space-y-4">
                                <?php foreach($recent_transactions as $tx): ?>
                                    <li class="flex items-center space-x-3"><div class="p-3 rounded-full text-sm bg-green-500/20 text-green-400"><i class="fas fa-wifi fa-fw"></i></div><div class="flex-1"><p class="font-medium text-gray-300 text-sm"><?= htmlspecialchars($tx['plan']) ?></p><p class="text-xs text-gray-500">by <?= htmlspecialchars($tx['user']['name']) ?></p></div><p class="font-bold text-sm text-teal-400">+ ₱<?= htmlspecialchars(number_format($tx['amount'], 2)) ?></p></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="bg-dark-700 rounded-lg p-6">
                            <h2 class="text-lg font-semibold text-gray-200 mb-4 flex items-center"><i class="fas fa-map-marker-alt mr-3 text-blue-500"></i>Top Locations</h2>
                            <ul class="space-y-3">
                                <?php foreach($top_locations as $loc): ?>
                                    <li class="space-y-1"><div class="flex justify-between items-center text-gray-300 text-sm"><span><?= htmlspecialchars($loc['name']) ?></span><span class="font-medium text-gray-200"><?= htmlspecialchars($loc['users']) ?> users</span></div><div class="w-full bg-dark-600 rounded-full h-1.5"><div class="bg-blue-500 h-1.5 rounded-full" style="width: <?= ($loc['users'] / max(1, $top_locations[0]['users'])) * 100 ?>%"></div></div></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="bg-dark-700 rounded-lg p-6">
                            <h2 class="text-lg font-semibold text-gray-200 mb-4 flex items-center"><i class="fas fa-map-marker-alt mr-3 text-red-500"></i>Surf Locations</h2>
                            <ul class="space-y-3">
                                <?php foreach($top_locations as $loc): ?>
                                    <li class="space-y-1"><div class="flex justify-between items-center text-gray-300 text-sm"><span><?= htmlspecialchars($loc['name']) ?></span><span class="font-medium text-gray-200"><?= htmlspecialchars($loc['users']) ?> users</span></div><div class="w-full bg-dark-600 rounded-full h-1.5"><div class="bg-violet-500 h-1.5 rounded-full" style="width: <?= ($loc['users'] / max(1, $top_locations[0]['users'])) * 50 ?>%"></div></div></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-5 gap-8 mt-8">
                    <div class="lg:col-span-3 bg-dark-700 p-6 rounded-lg">
                        <div class="flex justify-between items-center mb-4"><h3 class="text-lg font-semibold text-gray-200">Revenue Over Time</h3><div class="flex items-center bg-dark-600 rounded-lg p-1 text-sm"><button class="px-3 py-1 rounded-md bg-blue-600 text-white">Month</button><button class="px-3 py-1 text-gray-400 hover:bg-dark-500 rounded-md">Year</button></div></div>
                        <div class="h-72"><canvas id="revenueLineChart"></canvas></div>
                    </div>
                    <div class="lg:col-span-2 bg-dark-700 p-6 rounded-lg">
                        <div class="flex justify-between items-center mb-4"><h3 class="text-lg font-semibold text-gray-200">Plan Popularity</h3><div class="text-sm text-gray-400">This Month</div></div>
                        <div class="flex flex-col sm:flex-row items-center justify-center gap-8 pt-4">
                            <div class="h-48 w-48 flex-shrink-0"><canvas id="plansDoughnutChart"></canvas></div>
                            <ul class="space-y-3 text-sm flex-grow">
                                <?php foreach($plan_popularity as $plan): ?>
                                    <li class="flex items-center gap-2"><span class="h-2 w-2 rounded-full" style="background-color: <?= $plan['color'] ?>"></span><span class="text-gray-400"><?= htmlspecialchars($plan['name']) ?></span><span class="font-medium text-gray-200 ml-auto"><?= htmlspecialchars($plan['count']) ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

<!--Notifications Modal-->
<div id="notificationContentModal" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center hidden z-50" role="dialog" aria-modal="true" aria-labelledby="notificationModalTitle" aria-hidden="true">
    <div class="relative p-6 border w-11/12 md:w-3/4 lg:w-2/3 xl:w-1/2 shadow-xl rounded-lg bg-white dark:bg-dark-800 transform transition-all sm:my-8 flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-center pb-3 border-b dark:border-dark-600 flex-shrink-0">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100" id="notificationModalTitle">Notification Details</h3>
            <button id="notificationModalCloseBtn" type="button" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center dark:hover:bg-dark-600 dark:hover:text-white" aria-label="Close modal">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
            </button>
        </div>
        <div class="mt-4 overflow-y-auto flex-grow">
            <div id="notificationModalBody">
                <p class="text-gray-700 dark:text-gray-300">Loading notification content...</p>
            </div>
        </div>
        <div class="mt-6 pt-4 border-t dark:border-dark-600 flex justify-end space-x-3 flex-shrink-0">
            <button id="notificationModalDeclineBtn" type="button" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-dark-800 hidden">Decline</button>
            <button id="notificationModalAcceptBtn" type="button" class="px-4 py-2 text-sm font-medium text-white bg-green-500 hover:bg-green-600 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:focus:ring-offset-dark-800 hidden">Accept</button>
            <a href="#" id="notificationModalViewLink" target="_blank" rel="noopener noreferrer" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-blue-700 focus:outline-none"></a>
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
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/require.js/2.3.6/require.min.js"></script>
<script src="/assets/client/js/dashboard.js"></script>
<script src="/assets/client/js/headmanager.js"></script>
<script src="/assets/client/js/notifications.js"></script>
<script src="/assets/client/js/typeahead-chat.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') { console.error('Chart.js is not loaded.'); return; }
    const darkModeGridColor = 'rgba(255, 255, 255, 0.1)';
    const darkModeTickColor = 'rgba(255, 255, 255, 0.5)';
    const revenueCtx = document.getElementById('revenueLineChart');
    if (revenueCtx) {
        new Chart(revenueCtx, {
            type: 'line',
            data: { labels: <?= json_encode(array_keys($revenue_chart_data)) ?>, datasets: [{ label: 'Revenue', data: <?= json_encode(array_values($revenue_chart_data)) ?>, borderColor: '#3B82F6', backgroundColor: 'rgba(59, 130, 246, 0.2)', fill: true, tension: 0.4, pointBackgroundColor: '#3B82F6', pointBorderColor: '#fff', pointHoverRadius: 6, pointRadius: 4, }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: false, grid: { color: darkModeGridColor }, ticks: { color: darkModeTickColor, callback: (value) => `₱` + value } }, x: { grid: { display: false }, ticks: { color: darkModeTickColor } } } }
        });
    }
    const doughnutCtx = document.getElementById('plansDoughnutChart');
    if (doughnutCtx) {
        new Chart(doughnutCtx, {
            type: 'doughnut',
            data: { labels: <?= json_encode(array_column($plan_popularity, 'name')) ?>, datasets: [{ label: 'Plan Sales', data: <?= json_encode(array_column($plan_popularity, 'count')) ?>, backgroundColor: <?= json_encode(array_column($plan_popularity, 'color')) ?>, borderColor: '#2D2D2D', borderWidth: 4, hoverOffset: 8 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => `${c.label}: ${c.parsed} sales` } } } }
        });
    }
});
</script>
</body>
</html>