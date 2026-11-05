<?php
/**
 * SmartFi Licensing Revenue Calculator
 * Adapted from saicms/app/views/dashboard/smartfi.view.php
 */
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// CSRF token (compatible with both Ginto and legacy helpers)
if (empty($_SESSION['csrf_token'])) {
    try { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
    catch (\Throwable $e) { $_SESSION['csrf_token'] = ''; }
}
$csrfToken = $_SESSION['csrf_token'];

// Pull session data with safe fallbacks (no mocking — real session only)
$userId     = $_SESSION['user_id'] ?? null;
$userFullName = $_SESSION['user_full_name'] ?? ($_SESSION['user'] ?? 'User');

// Referral link (passed by controller or derived from session)
$referralLink = $referralLink ?? '';
if (!$referralLink && $userId) {
    $publicId = $_SESSION['user_public_id'] ?? $userId;
    $scheme = (!empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? 'https' : 'http';
    $referralLink = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'silverqueen.pro') . '/register?ref=' . rawurlencode((string)$publicId);
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <link href="/assets/favicon/favicon.ico" rel="shortcut icon" type="image/x-icon">
    <title>SmartFi &mdash; Ginto</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        facebook: { DEFAULT: '#1877F2', dark: '#0B64C9', light: '#E7F3FF' },
                        dark: { 800: '#1E1E1E', 700: '#2D2D2D', 600: '#3A3A3A', 500: '#4A4A4A' }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/client/css/theme.css">
    <style>
        .gradient-bg { background: linear-gradient(135deg, #6b73ff 0%, #000dff 100%); }
        .card-hover:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); }
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
</head>
<body class="bg-gray-100 dark:bg-dark-800 font-sans transition-colors duration-200">

<?php require_once __DIR__ . '/social/htmlparts/header.php'; ?>

<!-- Chatbox Container (required by headmanager.js) -->
<div id="chatboxContainer" class="chatbox-container"></div>

<!-- Dashboard Content -->
<div class="flex h-screen overflow-hidden mt-[60px]">

    <!-- Desktop Sidebar -->
    <div class="hidden md:flex md:flex-shrink-0">
        <div class="flex flex-col w-64 gradient-bg text-white sidebar-container">
            <div class="flex items-center justify-center h-20 px-4">
                <div class="flex items-center">
                    <i class="fas fa-wifi text-2xl mr-2"></i>
                    <span class="text-xl font-semibold">SmartFi</span>
                </div>
            </div>
            <div class="flex flex-col flex-grow px-4 overflow-y-auto scrollbar-hide">
                <?php
                $dashSidebar = __DIR__ . '/social/htmlparts/dashboard_sidebar.php';
                if (file_exists($dashSidebar)) {
                    require_once $dashSidebar;
                }
                ?>
                <div class="mt-auto pb-4">
                    <div class="p-4 bg-blue-800 rounded-lg">
                        <h4 class="text-sm font-semibold mb-2">Need Help?</h4>
                        <p class="text-xs text-blue-200 mb-3">Questions about your SmartFi setup? Our team is here.</p>
                        <a href="mailto:support@silverqueen.pro" class="block w-full bg-white text-blue-800 py-2 px-4 rounded-md text-sm font-medium hover:bg-gray-100 transition-colors duration-200 text-center">
                            Contact Support
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Grow Network Referral Modal -->
    <div id="referralModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4 transition-opacity duration-300" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div id="referralModalContent" class="bg-white dark:bg-dark-700 rounded-xl shadow-2xl w-full max-w-md transform transition-all duration-300 scale-95 opacity-0">
            <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-dark-600">
                <h3 id="modal-title" class="text-lg font-semibold text-gray-800 dark:text-white">Grow Your Network</h3>
                <button id="closeReferralModalBtn" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6">
                <p class="text-center text-gray-600 dark:text-gray-400 mb-4">
                    Share this unique link with friends to invite them and earn rewards!
                </p>
                <div class="flex items-center p-2 border border-gray-300 dark:border-dark-600 rounded-lg bg-gray-100 dark:bg-dark-600">
                    <input id="referralModalLinkInput" type="text" readonly
                           class="flex-grow bg-transparent text-gray-700 dark:text-gray-300 focus:outline-none"
                           value="<?= htmlspecialchars($referralLink) ?>">
                    <button id="copyModalLinkBtn" class="ml-2 bg-blue-600 text-white py-2 px-4 rounded-md text-sm font-medium hover:bg-blue-700 transition-colors duration-200 flex-shrink-0">
                        Copy
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 overflow-auto scrollbar-hide">
        <main class="p-4 sm:p-6 lg:p-8">

            <!-- Page Header -->
            <header class="mb-10">
                <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800 dark:text-white">SmartFi Licensing Revenue Calculator</h1>
                        <p class="text-gray-500 dark:text-gray-400">Forecast and monitor your WiFi business growth</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-300 rounded-full text-sm font-medium">
                            <i class="fas fa-chart-line mr-1"></i> Live Analytics
                        </span>
                        <button onclick="exportToCSV()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            <i class="fas fa-download mr-2"></i> Export Report
                        </button>
                    </div>
                </div>
            </header>

            <!-- Calculator Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                <!-- Input Panel -->
                <div class="bg-white dark:bg-dark-700 p-6 rounded-xl shadow-sm lg:col-span-1">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white mb-6 flex items-center">
                        <i class="fas fa-calculator mr-3 text-blue-600 dark:text-blue-400"></i> Service Parameters
                    </h2>
                    <div class="space-y-5">
                        <div>
                            <label for="barangays" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Number of Locations</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none"><i class="fas fa-map-marker-alt text-gray-400"></i></div>
                                <input type="number" id="barangays" value="1" min="1" class="w-full pl-10 pr-4 py-3 border border-gray-300 dark:border-dark-600 rounded-lg bg-gray-50 dark:bg-dark-600 text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        <div>
                            <label for="DevicesPerBarangay" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Devices per Location</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none"><i class="fas fa-wifi text-gray-400"></i></div>
                                <input type="number" id="DevicesPerBarangay" value="1" min="1" class="w-full pl-10 pr-4 py-3 border border-gray-300 dark:border-dark-600 rounded-lg bg-gray-50 dark:bg-dark-600 text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        <div>
                            <label for="concurrentUsers" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Max Users per Device</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none"><i class="fas fa-users text-gray-400"></i></div>
                                <input type="number" id="concurrentUsers" value="50" class="w-full pl-10 pr-4 py-3 border border-gray-300 dark:border-dark-600 rounded-lg bg-gray-50 dark:bg-dark-600 text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        <div>
                            <label for="ratePerHour" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rate per Hour (₱)</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none"><span class="text-gray-400">₱</span></div>
                                <input type="number" id="ratePerHour" value="5" class="w-full pl-10 pr-4 py-3 border border-gray-300 dark:border-dark-600 rounded-lg bg-gray-50 dark:bg-dark-600 text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        <div>
                            <label for="dailyHours" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Daily Active Hours</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none"><i class="fas fa-clock text-gray-400"></i></div>
                                <input type="number" id="dailyHours" value="24" class="w-full pl-10 pr-4 py-3 border border-gray-300 dark:border-dark-600 rounded-lg bg-gray-50 dark:bg-dark-600 text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        <div>
                            <label for="initialInvestment" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CapEx per Device (₱)</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none"><span class="text-gray-400">₱</span></div>
                                <input type="number" id="initialInvestment" value="25000" class="w-full pl-10 pr-4 py-3 border border-gray-300 dark:border-dark-600 rounded-lg bg-gray-50 dark:bg-dark-600 text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        <div>
                            <label for="monthlyOperatingCost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">OpEx per Location (₱)</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none"><span class="text-gray-400">₱</span></div>
                                <input type="number" id="monthlyOperatingCost" value="20000" class="w-full pl-10 pr-4 py-3 border border-gray-300 dark:border-dark-600 rounded-lg bg-gray-50 dark:bg-dark-600 text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        <div class="pt-2">
                            <label for="efficiencySlider" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Projection Efficiency</label>
                            <div class="flex items-center space-x-3">
                                <input type="range" id="efficiencySlider" min="50" max="120" value="100" class="w-full h-2 bg-gray-200 dark:bg-dark-600 rounded-lg appearance-none cursor-pointer">
                                <span id="efficiencyDisplay" class="font-semibold text-blue-600 dark:text-blue-400 w-24 text-center">100%</span>
                            </div>
                        </div>
                        <div class="pt-2">
                            <label for="trendSlider" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Growth Trend</label>
                            <div class="flex items-center space-x-3">
                                <input type="range" id="trendSlider" min="0" max="100" value="50" class="w-full h-2 bg-gray-200 dark:bg-dark-600 rounded-lg appearance-none cursor-pointer">
                                <span id="trendDisplay" class="font-semibold text-blue-600 dark:text-blue-400 w-24 text-center">Neutral</span>
                            </div>
                        </div>
                        <div class="pt-2">
                            <label for="investorPayoutSlider" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Vendor Payout Period</label>
                            <div class="flex items-center space-x-3">
                                <input type="range" id="investorPayoutSlider" min="1" max="36" value="12" class="w-full h-2 bg-gray-200 dark:bg-dark-600 rounded-lg appearance-none cursor-pointer">
                                <span id="investorPayoutMonthsDisplay" class="font-semibold text-blue-600 dark:text-blue-400 w-24 text-center">12 months</span>
                            </div>
                        </div>
                        <button onclick="runCalculations()" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 px-4 rounded-lg font-medium transition mt-4 flex items-center justify-center">
                            <i class="fas fa-chart-pie mr-2"></i> Recalculate Projections
                        </button>
                    </div>
                </div>

                <!-- Results Panel -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-white dark:bg-dark-700 p-5 rounded-xl shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Initial CapEx</p>
                                    <h3 id="totalInvestment" class="text-2xl font-bold text-gray-800 dark:text-white mt-1">₱0</h3>
                                </div>
                                <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-300"><i class="fas fa-coins text-xl"></i></div>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-dark-700 p-5 rounded-xl shadow-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p id="volatileOutcomeLabel" class="text-sm font-medium text-gray-500 dark:text-gray-400">Est. Monthly Revenue</p>
                                    <h3 id="volatileOutcomeValue" class="text-2xl font-bold text-teal-600 dark:text-teal-400 mt-1">₱0</h3>
                                </div>
                                <div id="volatileOutcomeIconContainer" class="p-3 rounded-full bg-teal-100 dark:bg-teal-900 text-teal-600 dark:text-teal-300">
                                    <i class="fas fa-money-bill-wave text-xl"></i>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-dark-700 p-5 rounded-xl shadow-sm flex flex-col justify-between">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Vendor Payout Period</p>
                                    <h3 id="payoutPeriodDisplay" class="text-2xl font-bold text-gray-800 dark:text-white mt-1">12 months</h3>
                                </div>
                                <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900 text-purple-600 dark:text-purple-300 flex-shrink-0">
                                    <i class="fas fa-calendar-alt text-xl"></i>
                                </div>
                            </div>
                            <div class="mt-2 pt-2 border-t border-gray-200 dark:border-dark-600">
                                <label for="payoutAliasSlider" class="text-xs font-medium text-gray-600 dark:text-gray-300 block mb-1">Adjust Period</label>
                                <input type="range" id="payoutAliasSlider" min="1" max="36" value="12" class="w-full h-1 bg-gray-200 dark:bg-dark-600 rounded-lg appearance-none cursor-pointer">
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-dark-700 p-6 rounded-xl shadow-sm">
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white mb-6 flex items-center">
                            <i class="fas fa-chart-bar mr-3 text-blue-600 dark:text-blue-400"></i> Projection Details
                        </h2>
                        <div id="output" class="space-y-4">
                            <div class="text-center py-10 text-gray-400">
                                <i class="fas fa-calculator text-4xl mb-3"></i>
                                <p>Enter your parameters to see projections</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-dark-700 p-6 rounded-xl shadow-sm">
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-white mb-6 flex items-center">
                            <i class="fas fa-project-diagram mr-3 text-blue-600 dark:text-blue-400"></i> Revenue &amp; Profit Forecast
                        </h2>
                        <div class="chart-container" id="chart"><canvas id="revenueChart"></canvas></div>
                        <div class="pt-4 mt-4 border-t border-gray-200 dark:border-dark-600">
                            <label for="volatilitySlider" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Market Volatility: <span class="text-xs">(Stable <i class="fas fa-arrow-right"></i> Volatile)</span>
                            </label>
                            <div class="flex items-center space-x-3">
                                <input type="range" id="volatilitySlider" min="0" max="100" value="0" class="w-full h-2 bg-gray-200 dark:bg-dark-600 rounded-lg appearance-none cursor-pointer">
                                <span id="volatilityDisplay" class="font-semibold text-blue-600 dark:text-blue-400 w-24 text-center">Stable</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /grid -->
        </main>
    </div>
</div><!-- /flex h-screen -->

<!-- Notification Detail Modal -->
<div id="notificationContentModal" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center hidden z-50" role="dialog" aria-modal="true" aria-labelledby="notificationModalTitle" aria-hidden="true">
    <div class="relative p-6 border w-11/12 md:w-3/4 lg:w-2/3 xl:w-1/2 shadow-xl rounded-lg bg-white dark:bg-dark-800 transform transition-all sm:my-8 flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-center pb-3 border-b dark:border-dark-600 flex-shrink-0">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100" id="notificationModalTitle">Notification Details</h3>
            <button id="notificationModalCloseBtn" type="button" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center dark:hover:bg-dark-600 dark:hover:text-white" aria-label="Close modal">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
            </button>
        </div>
        <div class="mt-4 overflow-y-auto flex-grow">
            <div id="notificationModalBody"><p class="text-gray-700 dark:text-gray-300">Loading...</p></div>
        </div>
        <div class="mt-6 pt-4 border-t dark:border-dark-600 flex justify-end space-x-3 flex-shrink-0">
            <button id="notificationModalDeclineBtn" type="button" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md hidden">Decline</button>
            <button id="notificationModalAcceptBtn" type="button" class="px-4 py-2 text-sm font-medium text-white bg-green-500 hover:bg-green-600 rounded-md hidden">Accept</button>
            <a href="#" id="notificationModalViewLink" target="_blank" rel="noopener noreferrer" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-blue-700 focus:outline-none"></a>
        </div>
    </div>
</div>

<script>
window.APP_USER_ID = <?= json_encode($userId) ?>;
window.APP_USER_FULL_NAME = <?= json_encode($userFullName) ?>;
window.APP_USER_AVATAR = <?= json_encode($_SESSION['user_profile_picture'] ?? null) ?>;
window.currentUserData = {
    id: <?= json_encode($userId) ?>,
    fullName: <?= json_encode($userFullName) ?>,
    username: <?= json_encode($_SESSION['user_username'] ?? ($_SESSION['user'] ?? 'guest')) ?>,
    profilePicture: <?= json_encode($_SESSION['user_profile_picture'] ?? null) ?>
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
// Referral modal copy button
document.addEventListener('DOMContentLoaded', function() {
    var copyBtn = document.getElementById('copyModalLinkBtn');
    var linkInput = document.getElementById('referralModalLinkInput');
    if (copyBtn && linkInput) {
        copyBtn.addEventListener('click', function() {
            navigator.clipboard.writeText(linkInput.value).then(function() {
                copyBtn.textContent = 'Copied!';
                setTimeout(function() { copyBtn.textContent = 'Copy'; }, 2000);
            }).catch(function() {
                linkInput.select();
                document.execCommand('copy');
                copyBtn.textContent = 'Copied!';
                setTimeout(function() { copyBtn.textContent = 'Copy'; }, 2000);
            });
        });
    }

    var closeModal = document.getElementById('closeReferralModalBtn');
    var modal = document.getElementById('referralModal');
    if (closeModal && modal) {
        closeModal.addEventListener('click', function() { modal.classList.add('hidden'); });
        modal.addEventListener('click', function(e) { if (e.target === modal) modal.classList.add('hidden'); });
    }

    var openReferralBtn = document.getElementById('openReferralModalBtn');
    if (openReferralBtn && modal) {
        openReferralBtn.addEventListener('click', function(e) {
            e.preventDefault();
            modal.classList.remove('hidden');
            var content = document.getElementById('referralModalContent');
            if (content) {
                setTimeout(function() {
                    content.classList.remove('scale-95', 'opacity-0');
                    content.classList.add('scale-100', 'opacity-100');
                }, 10);
            }
        });
    }
});
</script>

<script>
// ==================================================================
//               SMARTFI FRANCHISE CALCULATOR
// ==================================================================
const FranchiseCalculator = {
    revenueChart: null,
    currentProjections: null,
    elements: {},
    CHART_MONTHS: 36,

    config: {
        efficiency: { min: 1, max: 120, default: 100 },
        trend: { min: 1, max: 100, default: 50, max_trend_impact: 0.75, neutral_threshold: 5 },
        payout: { min: 1, max: 36, default: 12 },
        volatility: {
            min: 0, max: 100, default: 0,
            max_event_probability: 0.75,
            max_impact_percentage: 0.60
        }
    },

    init() {
        this.cacheDOMElements();
        this.applyInitialConfigs();
        this.addEventListeners();
        this.updateAllSliderDisplays();
        this.runCalculations();
        this.observeThemeChanges();
    },

    cacheDOMElements() {
        const ids = [
            'barangays', 'DevicesPerBarangay', 'concurrentUsers', 'ratePerHour', 'dailyHours',
            'initialInvestment', 'monthlyOperatingCost',
            'investorPayoutSlider', 'investorPayoutMonthsDisplay', 'payoutAliasSlider', 'payoutPeriodDisplay',
            'efficiencySlider', 'efficiencyDisplay', 'trendSlider', 'trendDisplay',
            'volatilitySlider', 'volatilityDisplay',
            'totalInvestment',
            'volatileOutcomeLabel', 'volatileOutcomeValue', 'volatileOutcomeIconContainer',
            'output', 'revenueChart'
        ];
        ids.forEach(id => { this.elements[id] = document.getElementById(id); });
        this.elements.allNumberInputs = document.querySelectorAll('input[type="number"]');
    },

    applyInitialConfigs() {
        this.elements.efficiencySlider.min = this.config.efficiency.min;
        this.elements.efficiencySlider.max = this.config.efficiency.max;
        this.elements.efficiencySlider.value = this.config.efficiency.default;

        this.elements.trendSlider.min = this.config.trend.min;
        this.elements.trendSlider.max = this.config.trend.max;
        this.elements.trendSlider.value = this.config.trend.default;

        this.elements.investorPayoutSlider.min = this.config.payout.min;
        this.elements.investorPayoutSlider.max = this.config.payout.max;
        this.elements.investorPayoutSlider.value = this.config.payout.default;
        this.elements.payoutAliasSlider.min = this.config.payout.min;
        this.elements.payoutAliasSlider.max = this.config.payout.max;
        this.elements.payoutAliasSlider.value = this.config.payout.default;

        this.elements.volatilitySlider.min = this.config.volatility.min;
        this.elements.volatilitySlider.max = this.config.volatility.max;
        this.elements.volatilitySlider.value = this.config.volatility.default;
    },

    addEventListeners() {
        this.elements.allNumberInputs.forEach(input => input.addEventListener('input', () => this.runCalculations()));
        const payoutSync = (source) => {
            const value = source.value;
            this.elements.investorPayoutSlider.value = value;
            this.elements.payoutAliasSlider.value = value;
            this.updatePayoutSliderDisplay(value);
            this.runCalculations();
        };
        this.elements.investorPayoutSlider.addEventListener('input', (e) => payoutSync(e.target));
        this.elements.payoutAliasSlider.addEventListener('input', (e) => payoutSync(e.target));
        this.elements.efficiencySlider.addEventListener('input', (e) => { this.updateEfficiencyDisplay(e.target.value); this.runCalculations(); });
        this.elements.trendSlider.addEventListener('input', (e) => { this.updateTrendDisplay(e.target.value); this.runCalculations(); });
        this.elements.volatilitySlider.addEventListener('input', (e) => { this.updateVolatilityDisplay(e.target.value); this.runCalculations(); });
    },

    runCalculations() {
        this.currentProjections = this.calculateProjections();
        this.updateUI(this.currentProjections);
        this.updateChart(this.currentProjections);
    },

    calculateProjections() {
        const inputs = {
            barangays: parseInt(this.elements.barangays.value) || 1,
            devices: parseInt(this.elements.DevicesPerBarangay.value) || 1,
            usersPerDevice: parseInt(this.elements.concurrentUsers.value) || 0,
            rate: parseFloat(this.elements.ratePerHour.value) || 0,
            hours: parseInt(this.elements.dailyHours.value) || 0,
            initialInvestmentPerDevice: parseFloat(this.elements.initialInvestment.value) || 0,
            monthlyOperatingCostPerLocation: parseFloat(this.elements.monthlyOperatingCost.value) || 0,
            investorPayoutMonths: parseInt(this.elements.investorPayoutSlider.value) || 1,
            efficiency: parseInt(this.elements.efficiencySlider.value) || 100,
            trend: parseInt(this.elements.trendSlider.value) || 50,
            volatility: parseInt(this.elements.volatilitySlider.value) || 0,
        };

        const projected = {};
        projected.totalInitialInvestment = inputs.barangays * inputs.devices * inputs.initialInvestmentPerDevice;
        projected.totalMonthlyOperatingCost = inputs.barangays * inputs.monthlyOperatingCostPerLocation;

        const actual = { monthlyNetProfits: [], monthlyInvestorCuts: [] };
        const trendDirection = (inputs.trend - this.config.trend.default) / this.config.trend.default;

        const baseTheoreticalMonthlyRevenue = (inputs.barangays * inputs.devices * inputs.usersPerDevice) * inputs.rate * inputs.hours * 30;
        projected.monthlyRevenue = baseTheoreticalMonthlyRevenue * (inputs.efficiency / 100);

        for (let i = 0; i < this.CHART_MONTHS; i++) {
            const trendProgress = i / (this.CHART_MONTHS - 1);
            const monthlyTrendMultiplier = 1 + (trendDirection * this.config.trend.max_trend_impact * trendProgress);

            let monthUsers = inputs.usersPerDevice * monthlyTrendMultiplier;
            let monthRate = inputs.rate;
            let monthOpCost = projected.totalMonthlyOperatingCost;

            const volatilityLevel = inputs.volatility / 100;
            if (volatilityLevel > 0 && Math.random() < (volatilityLevel * this.config.volatility.max_event_probability)) {
                const impactMagnitude = Math.random() * volatilityLevel * this.config.volatility.max_impact_percentage;
                const isPositiveShock = Math.random() > 0.5;
                const shockMultiplier = isPositiveShock ? (1 + impactMagnitude) : (1 - impactMagnitude);
                switch (Math.floor(Math.random() * 3)) {
                    case 0: monthUsers *= shockMultiplier; break;
                    case 1: monthRate *= shockMultiplier; break;
                    case 2: monthOpCost *= shockMultiplier; break;
                }
            }

            const monthTheoreticalRevenue = (inputs.barangays * inputs.devices * monthUsers) * monthRate * inputs.hours * 30;
            const monthGrossRevenue = monthTheoreticalRevenue * (inputs.efficiency / 100);
            const monthInvestorCut = (i < inputs.investorPayoutMonths) ? monthGrossRevenue * 0.20 : 0;
            const monthNetProfit = monthGrossRevenue - monthOpCost - monthInvestorCut;
            actual.monthlyNetProfits.push(monthNetProfit);
            actual.monthlyInvestorCuts.push(monthInvestorCut);
        }

        const summary = {
            totalRevenue: projected.monthlyRevenue * inputs.investorPayoutMonths,
            totalCosts: projected.totalMonthlyOperatingCost * inputs.investorPayoutMonths,
            paidToInvestor: (projected.monthlyRevenue * 0.20) * inputs.investorPayoutMonths,
            franchiseeProfit: (projected.monthlyRevenue - projected.totalMonthlyOperatingCost - (projected.monthlyRevenue * 0.20)) * inputs.investorPayoutMonths
        };

        projected.investorCut = projected.monthlyRevenue * 0.20;
        projected.netProfitWithInvestorCut = projected.monthlyRevenue - projected.totalMonthlyOperatingCost - projected.investorCut;
        projected.netProfitAfterCut = projected.monthlyRevenue - projected.totalMonthlyOperatingCost;

        return { inputs, projected, actual, summary };
    },

    updateUI(data) {
        const { inputs, projected, actual, summary } = data;
        const format = (num) => `₱${num.toLocaleString('en-US', { maximumFractionDigits: 0 })}`;

        this.elements.totalInvestment.textContent = format(projected.totalInitialInvestment);

        const labelEl = this.elements.volatileOutcomeLabel;
        const valueEl = this.elements.volatileOutcomeValue;
        const iconEl  = this.elements.volatileOutcomeIconContainer;

        valueEl.classList.remove('text-teal-600', 'dark:text-teal-400', 'text-red-500', 'dark:text-red-400');
        iconEl.classList.remove('bg-teal-100', 'dark:bg-teal-900', 'text-teal-600', 'dark:text-teal-300', 'bg-red-100', 'dark:bg-red-900', 'text-red-600', 'dark:text-red-300');

        labelEl.textContent = 'Average Payout Profit';
        valueEl.textContent = format(summary.paidToInvestor);
        valueEl.classList.add('text-teal-600', 'dark:text-teal-400');
        iconEl.classList.add('bg-teal-100', 'dark:bg-teal-900', 'text-teal-600', 'dark:text-teal-300');

        this.elements.output.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-gray-100 dark:bg-dark-600 p-4 rounded-lg">
                    <h3 class="font-medium text-gray-800 dark:text-white mb-3 flex items-center">
                        <i class="fas fa-calendar-alt text-purple-500 mr-2"></i> Payout Period Summary (Baseline)
                        <span class="ml-auto text-xs font-normal bg-purple-100 dark:bg-purple-900 text-purple-600 dark:text-purple-300 px-2 py-0.5 rounded-full">${inputs.investorPayoutMonths} mo</span>
                    </h3>
                    <ul class="space-y-2 text-sm">
                        <li class="flex justify-between items-center"><span class="text-gray-600 dark:text-gray-400">Total Revenue</span><span class="font-medium text-gray-800 dark:text-white">${format(summary.totalRevenue)}</span></li>
                        <li class="flex justify-between items-center"><span class="text-gray-600 dark:text-gray-400">Total Costs</span><span class="font-medium text-red-500">- ${format(summary.totalCosts)}</span></li>
                        <li class="flex justify-between items-center border-t border-dashed pt-2 mt-2 border-gray-300 dark:border-dark-500"><span class="text-gray-600 dark:text-gray-400">Vendor Profit</span><span class="font-semibold text-amber-500">${format(summary.paidToInvestor)}</span></li>
                        <li class="flex justify-between items-center"><span class="text-gray-600 dark:text-gray-400">Franchise Profit</span><span class="font-semibold text-green-500">${format(summary.franchiseeProfit)}</span></li>
                    </ul>
                </div>
                <div class="bg-gray-100 dark:bg-dark-600 p-4 rounded-lg">
                    <h3 class="font-medium text-gray-800 dark:text-white mb-3 flex items-center"><i class="fas fa-money-bill-wave text-teal-500 mr-2"></i> Monthly Financials (Baseline)</h3>
                    <ul class="space-y-2 text-sm">
                        <li class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Gross Revenue (at ${inputs.efficiency}%)</span><span class="font-medium text-gray-800 dark:text-white">${format(projected.monthlyRevenue)}</span></li>
                        <li class="flex justify-between border-t border-dashed pt-2 mt-2 border-gray-300 dark:border-dark-500"><span class="text-gray-600 dark:text-gray-400">Vendor's Cut (20%)</span><span class="font-medium text-amber-500">${format(projected.investorCut)}</span></li>
                        <li class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Operating Cost</span><span class="font-medium text-red-500">- ${format(projected.totalMonthlyOperatingCost)}</span></li>
                        <li class="flex justify-between border-t pt-2 border-gray-300 dark:border-dark-500"><span class="text-gray-600 dark:text-gray-400">Net Profit (w/ Vendor)</span><span class="font-bold text-teal-600 dark:text-teal-400">${format(projected.netProfitWithInvestorCut)}</span></li>
                        <li class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Total Vendo Profit</span><span class="font-bold text-blue-500 dark:text-blue-400">${format(projected.netProfitAfterCut)}</span></li>
                    </ul>
                </div>
            </div>`;
    },

    updateChart(data) {
        if (this.revenueChart) { this.revenueChart.destroy(); }
        const ctx = this.elements.revenueChart.getContext('2d');
        const darkMode = this.isDarkMode();
        const gridColor  = darkMode ? '#3A3A3A' : '#E5E7EB';
        const tickColor  = darkMode ? '#9CA3AF' : '#4B5563';
        const legendColor = darkMode ? '#D1D5DB' : '#1F2937';

        const monthsLabels = Array.from({length: this.CHART_MONTHS}, (_, i) => `M${i+1}`);
        const { inputs, projected, actual } = data;
        const cumulativeData = (series) => series.reduce((acc, val) => [...acc, (acc.length > 0 ? acc[acc.length-1] : -projected.totalInitialInvestment) + val], []);
        const projectedMonthlyProfits = monthsLabels.map((_, i) => (i < inputs.investorPayoutMonths) ? projected.netProfitWithInvestorCut : projected.netProfitAfterCut);
        const revenueData     = monthsLabels.map((_, i) => projected.monthlyRevenue * (i + 1));
        const costData        = monthsLabels.map((_, i) => projected.totalInitialInvestment + (projected.totalMonthlyOperatingCost * (i + 1)));
        const investorShareData = monthsLabels.map((_, i) => (i < inputs.investorPayoutMonths) ? projected.investorCut * (i + 1) : projected.investorCut * inputs.investorPayoutMonths);

        this.revenueChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: monthsLabels,
                datasets: [
                    { label: 'Monthly Net Profit', data: actual.monthlyNetProfits, backgroundColor: (ctx) => (ctx.raw >= 0 ? 'rgba(34,197,94,0.4)' : 'rgba(239,68,68,0.4)'), borderColor: 'transparent', order: 5 },
                    { type: 'line', label: 'Cumulative Revenue', data: revenueData, borderColor: '#14B8A6', tension: 0.3, pointRadius: 0, order: 2 },
                    { type: 'line', label: 'Total Costs', data: costData, borderColor: '#EF4444', tension: 0.3, pointRadius: 0, order: 3 },
                    { type: 'line', label: "Vendor's Share", data: investorShareData, borderColor: '#F59E0B', tension: 0.3, pointRadius: 0, order: 4 },
                    { type: 'line', label: 'Projected Net Profit (Baseline)', data: cumulativeData(projectedMonthlyProfits), borderColor: '#84CC16', borderDash: [5,5], tension: 0.3, pointRadius: 0, order: 1 },
                    { type: 'line', label: 'Volatile Net Profit', data: cumulativeData(actual.monthlyNetProfits), borderColor: '#3B82F6', backgroundColor: darkMode ? 'rgba(59,130,246,0.2)' : 'rgba(59,130,246,0.1)', fill: 'start', tension: 0.3, pointRadius: 0, order: 0 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: false, grid: { color: gridColor }, ticks: { color: tickColor, callback: v => '₱' + (v/1000) + 'k' } },
                    x: { grid: { display: false }, ticks: { color: tickColor, maxRotation: 0, minRotation: 0, autoSkip: true, maxTicksLimit: 12 } }
                },
                plugins: {
                    legend: { position: 'top', labels: { color: legendColor } },
                    tooltip: { callbacks: { label: ctx => `${ctx.dataset.label}: ₱${Math.round(ctx.raw).toLocaleString()}` } },
                    annotation: { annotations: {
                        payout: { type: 'box', xMin: -0.5, xMax: inputs.investorPayoutMonths - 0.5, backgroundColor: darkMode ? 'rgba(96,77,226,0.15)' : 'rgba(199,210,254,0.5)', borderColor: 'transparent', drawTime: 'beforeDatasetsDraw' },
                        zeroLine: { type: 'line', yMin: 0, yMax: 0, borderColor: gridColor, borderWidth: 1, borderDash: [2,2] }
                    }}
                },
                interaction: { intersect: false, mode: 'index' }
            }
        });
    },

    isDarkMode: () => document.documentElement.classList.contains('dark'),

    updateAllSliderDisplays() {
        this.updatePayoutSliderDisplay(this.elements.investorPayoutSlider.value);
        this.updateEfficiencyDisplay(this.elements.efficiencySlider.value);
        this.updateTrendDisplay(this.elements.trendSlider.value);
        this.updateVolatilityDisplay(this.elements.volatilitySlider.value);
    },
    updatePayoutSliderDisplay(value) {
        const period = parseInt(value, 10);
        const label = `${period} month${period > 1 ? 's' : ''}`;
        this.elements.investorPayoutMonthsDisplay.textContent = label;
        this.elements.payoutPeriodDisplay.textContent = label;
    },
    updateEfficiencyDisplay(value) {
        this.elements.efficiencyDisplay.textContent = `${value}%`;
    },
    updateTrendDisplay(value) {
        const val = parseInt(value, 10);
        const lo = this.config.trend.default - this.config.trend.neutral_threshold;
        const hi = this.config.trend.default + this.config.trend.neutral_threshold;
        this.elements.trendDisplay.textContent = val < lo ? 'Downtrend' : (val > hi ? 'Uptrend' : 'Neutral');
    },
    updateVolatilityDisplay(value) {
        const val = parseInt(value, 10);
        this.elements.volatilityDisplay.textContent = val === 0 ? 'Stable' : (val < 50 ? 'Unstable' : 'Volatile');
    },
    observeThemeChanges() {
        new MutationObserver(() => {
            if (this.currentProjections) this.updateChart(this.currentProjections);
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    },
    exportToCSV() {
        if (!this.currentProjections) { alert('Please calculate projections first!'); return; }
        const { inputs, projected, summary } = this.currentProjections;
        const rows = [
            ['Parameter', 'Value'],
            ['Locations', inputs.barangays],
            ['Devices per Location', inputs.devices],
            ['Max Users per Device', inputs.usersPerDevice],
            ['Rate per Hour (₱)', inputs.rate],
            ['Daily Active Hours', inputs.hours],
            ['CapEx per Device (₱)', inputs.initialInvestmentPerDevice],
            ['OpEx per Location/mo (₱)', inputs.monthlyOperatingCostPerLocation],
            ['Efficiency (%)', inputs.efficiency],
            ['Vendor Payout Months', inputs.investorPayoutMonths],
            [],
            ['Projection', 'Value'],
            ['Total CapEx (₱)', projected.totalInitialInvestment],
            ['Monthly Revenue (₱)', projected.monthlyRevenue],
            ['Monthly Op Cost (₱)', projected.totalMonthlyOperatingCost],
            ['Vendor Cut/mo (₱)', projected.investorCut],
            ['Net Profit w/ Vendor (₱)', projected.netProfitWithInvestorCut],
            [],
            ['Payout Period Summary', inputs.investorPayoutMonths + ' months'],
            ['Total Revenue (₱)', summary.totalRevenue],
            ['Total Costs (₱)', summary.totalCosts],
            ['Paid to Vendor (₱)', summary.paidToInvestor],
            ['Franchisee Profit (₱)', summary.franchiseeProfit],
        ];
        const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = 'smartfi-projection.csv'; a.click();
        URL.revokeObjectURL(url);
    }
};

// Global shims for onclick attributes
function runCalculations() { FranchiseCalculator.runCalculations(); }
function exportToCSV() { FranchiseCalculator.exportToCSV(); }

document.addEventListener('DOMContentLoaded', () => FranchiseCalculator.init());
</script>

</body>
</html>
