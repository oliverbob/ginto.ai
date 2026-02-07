
<?php 
$csrfToken = getCsrfToken();
$isUserLoggedIn = isset($_SESSION['user']);
$username = $isUserLoggedIn ? $_SESSION['user'] : null;
// Display part of email or full username based on your preference
$userDisplay = $isUserLoggedIn ? (strpos($_SESSION['user'], '@') ? explode('@', $_SESSION['user'])[0] : $_SESSION['user']) : 'Guest';

// Mock function for getCsrfToken() if it doesn't exist in this context
if (!function_exists('getCsrfToken')) {
    function getCsrfToken() { return 'mock-csrf-token'; }
}
// Mock data for rewards link if it's not set
// Mock session data for standalone testing
if (session_status() == PHP_SESSION_NONE) { session_start(); }
$_SESSION['user_id'] = $_SESSION['user_id'] ?? 1;
$_SESSION['user_full_name'] = $_SESSION['user_full_name'] ?? 'Test User';
$_SESSION['user_username'] = $_SESSION['user_username'] ?? 'testuser';
$_SESSION['user_profile_picture'] = $_SESSION['user_profile_picture'] ?? null;
$_SESSION['user'] = $_SESSION['user'] ?? 'testuser';

?>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartFi Rewards Program</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      margin-right: 10px;
      border: 2px solid #6366f1;
    }
    .network-line {
      position: absolute;
      height: 20px;
      width: 2px;
      background-color: #6366f1;
      left: 50%;
      top: -20px;
    }
    .glow-card {
      box-shadow: 0 0 15px rgba(99, 102, 241, 0.3);
    }
  </style>
</head>
<body class="bg-gray-50">
  <div class="min-h-screen">
    <!-- Header -->
    <div class="bg-gradient-to-r from-indigo-700 to-purple-800 text-white shadow-lg">
      <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row justify-between items-center">
          <div class="flex items-center space-x-4 mb-4 md:mb-0">
            <i class="fas fa-network-wired text-4xl"></i>
            <div>
              <h1 class="text-3xl font-bold">SmartFi Rewards Program</h1>
              <p class="text-indigo-200">Direct uplines earn higher commissions</p>
            </div>
          </div>
          <div class="bg-white text-indigo-800 px-4 py-2 rounded-lg shadow-md">
            <div class="flex items-center">
              <i class="fas fa-calendar-alt mr-2"></i>
              <span>October 23, 2025</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-8">
      <!-- Program Overview -->
      <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8 glow-card">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4">
          <h2 class="text-xl font-bold text-white flex items-center">
            <i class="fas fa-info-circle mr-2"></i> Reverse Commission Structure
          </h2>
        </div>
        <div class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
              <h3 class="text-lg font-semibold text-indigo-800 mb-3">Commission Breakdown</h3>
              <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <div class="grid grid-cols-4 gap-2 text-sm font-medium text-gray-600 mb-2">
                  <span>Level</span>
                  <span>Percentage</span>
                  <span class="text-right">Amount</span>
                  <span class="text-right">Cumulative</span>
                </div>
                <div class="space-y-2">
                  <div class="grid grid-cols-4 gap-2 py-2 border-b border-gray-100">
                    <span class="font-medium">1 (Sponsor)</span>
                    <span>0.25%</span>
                    <span class="text-right font-medium text-indigo-600">₱25</span>
                    <span class="text-right text-gray-500">₱25</span>
                  </div>
                  <div class="grid grid-cols-4 gap-2 py-2 border-b border-gray-100">
                    <span class="font-medium">2</span>
                    <span>0.25%</span>
                    <span class="text-right font-medium text-indigo-600">₱25</span>
                    <span class="text-right text-gray-500">₱50</span>
                  </div>
                  <div class="grid grid-cols-4 gap-2 py-2 border-b border-gray-100">
                    <span class="font-medium">3</span>
                    <span>0.5%</span>
                    <span class="text-right font-medium text-indigo-600">₱50</span>
                    <span class="text-right text-gray-500">₱100</span>
                  </div>
                  <div class="grid grid-cols-4 gap-2 py-2 border-b border-gray-100">
                    <span class="font-medium">4</span>
                    <span>1%</span>
                    <span class="text-right font-medium text-indigo-600">₱100</span>
                    <span class="text-right text-gray-500">₱200</span>
                  </div>
                  <div class="grid grid-cols-4 gap-2 py-2 border-b border-gray-100">
                    <span class="font-medium">5</span>
                    <span>2%</span>
                    <span class="text-right font-medium text-indigo-600">₱200</span>
                    <span class="text-right text-gray-500">₱400</span>
                  </div>
                  <div class="grid grid-cols-4 gap-2 py-2 border-b border-gray-100">
                    <span class="font-medium">6</span>
                    <span>3%</span>
                    <span class="text-right font-medium text-indigo-600">₱300</span>
                    <span class="text-right text-gray-500">₱700</span>
                  </div>
                  <div class="grid grid-cols-4 gap-2 py-2 border-b border-gray-100">
                    <span class="font-medium">7</span>
                    <span>4%</span>
                    <span class="text-right font-medium text-indigo-600">₱400</span>
                    <span class="text-right text-gray-500">₱1,100</span>
                  </div>
                  <div class="grid grid-cols-4 gap-2 py-2">
                    <span class="font-medium">8 (Direct)</span>
                    <span>5%</span>
                    <span class="text-right font-medium text-indigo-600">₱500</span>
                    <span class="text-right text-gray-500">₱1,600</span>
                  </div>
                </div>
                <div class="mt-4 pt-3 border-t border-gray-200">
                  <div class="grid grid-cols-4 gap-2 font-bold">
                    <span>Total</span>
                    <span>16%</span>
                    <span class="text-right text-green-600">₱1,600</span>
                    <span class="text-right">₱10,000 sale</span>
                  </div>
                </div>
              </div>
            </div>
            <div>
              <h3 class="text-lg font-semibold text-indigo-800 mb-3">Profit Distribution</h3>
              <div class="bg-indigo-50 rounded-lg p-4 border border-indigo-100">
                <div class="flex items-center justify-between mb-4">
                  <div class="flex items-center">
                    <div class="w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center mr-3">
                      <i class="fas fa-store text-indigo-600"></i>
                    </div>
                    <div>
                      <h4 class="font-medium text-indigo-800">Company Profit</h4>
                      <p class="text-sm text-gray-600">After all payouts</p>
                    </div>
                  </div>
                  <span class="text-2xl font-bold text-green-600">₱1,600</span>
                </div>
                <div class="space-y-3">
                  <div class="flex justify-between">
                    <span class="text-gray-700">Product Cost</span>
                    <span class="font-medium">₱5,000</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-700">Commission Payouts</span>
                    <span class="font-medium">₱1,600</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-700">Operational Costs</span>
                    <span class="font-medium">₱1,800</span>
                  </div>
                  <div class="pt-2 mt-2 border-t border-gray-200">
                    <div class="flex justify-between font-bold">
                      <span>Net Profit</span>
                      <span class="text-blue-600">₱1,600 (16%)</span>
                    </div>
                  </div>
                </div>
              </div>

              <h3 class="text-lg font-semibold text-indigo-800 mt-6 mb-3">Key Features</h3>
              <div class="space-y-3">
                <div class="flex items-start">
                  <div class="flex-shrink-0 mt-1">
                    <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center">
                      <i class="fas fa-check text-xs"></i>
                    </div>
                  </div>
                  <p class="ml-2 text-gray-700">Direct upline (Level 8) earns highest 5% commission</p>
                </div>
                <div class="flex items-start">
                  <div class="flex-shrink-0 mt-1">
                    <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center">
                      <i class="fas fa-check text-xs"></i>
                    </div>
                  </div>
                  <p class="ml-2 text-gray-700">Commission decreases as you move up the upline</p>
                </div>
                <div class="flex items-start">
                  <div class="flex-shrink-0 mt-1">
                    <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center">
                      <i class="fas fa-check text-xs"></i>
                    </div>
                  </div>
                  <p class="ml-2 text-gray-700">Sustainable model with capped total commission at 16%</p>
                </div>
                <div class="flex items-start">
                  <div class="flex-shrink-0 mt-1">
                    <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center">
                      <i class="fas fa-check text-xs"></i>
                    </div>
                  </div>
                  <p class="ml-2 text-gray-700">Sponsor (Level 1) receives smallest 0.25% commission</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Network Visualization -->
      <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8 glow-card">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4">
          <h2 class="text-xl font-bold text-white flex items-center">
            <i class="fas fa-project-diagram mr-2"></i> Reverse Network Visualization
          </h2>
        </div>
        <div class="p-6">
          <div class="flex justify-center">
            <div class="relative">
              <!-- Level 1 (Sponsor) -->
              <div class="relative mb-4">
                <div class="flex justify-center space-x-4">
                  <div class="w-32 bg-indigo-200 text-indigo-900 rounded-lg p-2 text-center shadow-sm">
                    <div class="flex justify-center mb-1">
                      <img src="https://randomuser.me/api/portraits/women/68.jpg" class="avatar" style="width: 30px; height: 30px;">
                    </div>
                    <h3 class="font-bold text-xs">Aira</h3>
                    <p class="text-2xs opacity-90">Level 1 - 0.25%</p>
                    <div class="mt-1 bg-indigo-300 rounded px-1 py-0.5 text-2xs font-medium text-white">
                      ₱25
                    </div>
                  </div>
                </div>
                <div class="network-line left-1/2" style="top: auto; bottom: -20px;"></div>
              </div>

              <!-- Level 2 -->
              <div class="relative mb-4">
                <div class="flex justify-center space-x-4">
                  <div class="w-32 bg-indigo-200 text-indigo-900 rounded-lg p-2 text-center shadow-sm">
                    <div class="flex justify-center mb-1">
                      <img src="https://randomuser.me/api/portraits/men/67.jpg" class="avatar" style="width: 30px; height: 30px;">
                    </div>
                    <h3 class="font-bold text-xs">Bella</h3>
                    <p class="text-2xs opacity-90">Level 2 - 0.25%</p>
                    <div class="mt-1 bg-indigo-300 rounded px-1 py-0.5 text-2xs font-medium text-white">
                      ₱25
                    </div>
                  </div>
                </div>
                <div class="network-line left-1/2" style="top: auto; bottom: -20px;"></div>
              </div>

              <!-- Level 3 -->
              <div class="relative mb-4">
                <div class="flex justify-center space-x-4">
                  <div class="w-32 bg-indigo-200 text-indigo-900 rounded-lg p-2 text-center shadow-sm">
                    <div class="flex justify-center mb-1">
                      <img src="https://randomuser.me/api/portraits/women/25.jpg" class="avatar" style="width: 30px; height: 30px;">
                    </div>
                    <h3 class="font-bold text-xs">Cara</h3>
                    <p class="text-2xs opacity-90">Level 3 - 0.5%</p>
                    <div class="mt-1 bg-indigo-300 rounded px-1 py-0.5 text-2xs font-medium text-white">
                      ₱50
                    </div>
                  </div>
                </div>
                <div class="network-line left-1/2" style="top: auto; bottom: -20px;"></div>
              </div>

              <!-- Level 4 -->
              <div class="relative mb-4">
                <div class="flex justify-center space-x-4">
                  <div class="w-32 bg-indigo-200 text-indigo-900 rounded-lg p-2 text-center shadow-sm">
                    <div class="flex justify-center mb-1">
                      <img src="https://randomuser.me/api/portraits/men/76.jpg" class="avatar" style="width: 30px; height: 30px;">
                    </div>
                    <h3 class="font-bold text-xs">Dane</h3>
                    <p class="text-2xs opacity-90">Level 4 - 1%</p>
                    <div class="mt-1 bg-indigo-300 rounded px-1 py-0.5 text-2xs font-medium text-white">
                      ₱100
                    </div>
                  </div>
                </div>
                <div class="network-line left-1/2" style="top: auto; bottom: -20px;"></div>
              </div>

              <!-- Level 5 -->
              <div class="relative mb-6">
                <div class="flex justify-center space-x-4">
                  <div class="w-40 bg-indigo-300 text-indigo-900 rounded-lg p-2 text-center shadow-sm">
                    <div class="flex justify-center mb-1">
                      <img src="https://randomuser.me/api/portraits/women/33.jpg" class="avatar">
                    </div>
                    <h3 class="font-bold text-sm">Ella</h3>
                    <p class="text-2xs opacity-90">Level 5 - 2%</p>
                    <div class="mt-1 bg-indigo-400 rounded px-1 py-0.5 text-2xs font-medium text-white">
                      ₱200
                    </div>
                  </div>
                </div>
                <div class="network-line left-1/2" style="top: auto; bottom: -20px;"></div>
              </div>

              <!-- Level 6 -->
              <div class="relative mb-8">
                <div class="flex justify-center space-x-6">
                  <div class="w-48 bg-indigo-400 text-white rounded-lg p-3 text-center shadow">
                    <div class="flex justify-center mb-1">
                      <img src="https://randomuser.me/api/portraits/men/32.jpg" class="avatar">
                    </div>
                    <h3 class="font-bold text-sm">Faye</h3>
                    <p class="text-xs opacity-90">Level 6 - 3%</p>
                    <div class="mt-1 bg-indigo-500 rounded px-1 py-0.5 text-xs font-medium">
                       ₱300
                    </div>
                  </div>
                </div>
                <div class="network-line left-1/2" style="top: auto; bottom: -20px;"></div>
              </div>

              <!-- Level 7 -->
              <div class="relative mb-10">
                <div class="flex justify-center space-x-8">
                  <div class="w-56 bg-indigo-500 text-white rounded-lg p-4 text-center shadow-md">
                    <div class="flex justify-center mb-2">
                      <img src="https://randomuser.me/api/portraits/women/63.jpg" class="avatar">
                    </div>
                    <h3 class="font-bold">Gina</h3>
                    <p class="text-sm opacity-90">Level 7 - 4%</p>
                    <div class="mt-2 bg-indigo-600 rounded px-2 py-1 text-xs font-medium">
                       ₱400
                    </div>
                  </div>
                </div>
                <div class="network-line left-1/2" style="top: auto; bottom: -20px;"></div>
              </div>

              <!-- Level 8 (Direct Upline) -->
              <div class="relative mb-12">
                <div class="flex justify-center">
                  <div class="w-64 bg-indigo-600 text-white rounded-lg p-4 mx-auto text-center shadow-lg">
                    <div class="flex justify-center mb-2">
                      <img src="https://randomuser.me/api/portraits/women/44.jpg" class="avatar">
                    </div>
                    <h3 class="font-bold">Hana (Direct Upline)</h3>
                    <p class="text-sm opacity-90">Level 8 - 5%</p>
                    <div class="mt-2 bg-indigo-700 rounded px-2 py-1 text-xs font-medium">
                      ₱500 per sale
                    </div>
                  </div>
                </div>
                <div class="network-line left-1/2" style="top: auto; bottom: -20px;"></div>
              </div>

              <!-- Customer -->
              <div class="text-center">
                <div class="inline-block bg-gray-100 rounded-full p-3">
                  <div class="w-16 h-16 rounded-full bg-gray-300 flex items-center justify-center mx-auto mb-2">
                    <i class="fas fa-user-tag text-gray-600 text-2xl"></i>
                  </div>
                  <h3 class="font-bold text-gray-800">Customer</h3>
                  <p class="text-sm text-gray-600">Level 9 (No commission)</p>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>


<!-- Potential Individual Earnings -->
      <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8 glow-card">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4">
          <h2 class="text-xl font-bold text-white flex items-center">
            <i class="fas fa-hand-holding-usd mr-2"></i> Potential Individual Earnings
          </h2>
        </div>
        <div class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
              <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Product Price (₱)</label>
                <input type="range" min="5000" max="50000" step="1000" value="10000" id="individualPriceSlider" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                <div class="flex justify-between mt-2">
                  <span class="text-sm text-gray-500">₱5,000</span>
                  <span class="text-sm text-gray-500">₱50,000</span>
                </div>
                <div class="mt-4">
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <span class="text-gray-500">₱</span>
                    </div>
                    <input type="number" id="individualPriceInput" value="10000" class="pl-8 w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-lg font-medium">
                  </div>
                </div>
              </div>
              
              <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Number of Direct Downlines (Powerleg)</label>
                <input type="range" min="1" max="10" step="1" value="5" id="directDownlinesSlider" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                <div class="flex justify-between mt-2">
                  <span class="text-sm text-gray-500">1</span>
                  <span class="text-sm text-gray-500">10</span>
                </div>
                <div class="mt-4">
                  <input type="number" id="directDownlinesInput" value="5" min="1" max="10" class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-lg font-medium">
                </div>
              </div>

              <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Monthly Sales per Person</label>
                <input type="range" min="1" max="10" step="1" value="1" id="salesPerPersonSlider" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                <div class="flex justify-between mt-2">
                  <span class="text-sm text-gray-500">1 Sale</span>
                  <span class="text-sm text-gray-500">10 Sales</span>
                </div>
                <div class="mt-4">
                  <input type="number" id="salesPerPersonInput" value="1" min="1" max="10" class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-lg font-medium">
                </div>
              </div>
            </div>

            <div>
              <div class="bg-indigo-50 rounded-xl p-6 border border-indigo-100 h-full">
                <h3 class="text-lg font-semibold text-indigo-800 mb-4">Your Projected Monthly Earnings</h3>
                <div class="space-y-4">
                  <div id="individualEarningsBreakdown">
                    <!-- Earnings per level will be populated here by JS -->
                  </div>
                  <div class="pt-4 mt-4 border-t border-gray-200">
                    <div class="flex justify-between">
                      <span class="font-medium text-gray-800">Total Individual Earnings</span>
                      <span class="text-xl font-bold text-green-600" id="totalIndividualEarnings">₱0</span>
                    </div>
                  </div>
                </div>

                <div class="mt-6 bg-white p-4 rounded-lg border border-gray-200">
                  <h4 class="text-sm font-medium text-gray-700 mb-2">Assumptions:</h4>
                  <ul class="text-xs text-gray-600 space-y-1">
                    <li class="flex items-start">
                      <i class="fas fa-circle text-2xs mt-1 mr-2 text-indigo-500"></i>
                      <span>You are at the top of this 8-level structure.</span>
                    </li>
                    <li class="flex items-start">
                      <i class="fas fa-circle text-2xs mt-1 mr-2 text-indigo-500"></i>
                      <span>Each of your direct downlines also recruits the same number of direct downlines, creating a flat hierarchy.</span>
                    </li>
                    <li class="flex items-start">
                      <i class="fas fa-circle text-2xs mt-1 mr-2 text-indigo-500"></i>
                      <span>Earnings are based on sales from your entire 8-level downline.</span>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Earnings Calculator -->
      <div class="bg-white rounded-xl shadow-md overflow-hidden glow-card">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4">
          <h2 class="text-xl font-bold text-white flex items-center">
            <i class="fas fa-calculator mr-2"></i> Company Earnings Calculator
          </h2>
        </div>
        <div class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
              <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Product Price (₱)</label>
                <input type="range" min="5000" max="50000" step="1000" value="10000" id="priceSlider" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                <div class="flex justify-between mt-2">
                  <span class="text-sm text-gray-500">₱5,000</span>
                  <span class="text-sm text-gray-500">₱100,000</span>
                </div>
                <div class="mt-4">
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <span class="text-gray-500">₱</span>
                    </div>
                    <input type="number" id="priceInput" value="10000" class="pl-8 w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-lg font-medium">
                  </div>
                </div>
              </div>
              
              <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Your Network Size</label>
                <div class="grid grid-cols-4 gap-2">
                  <div class="col-span-1">
                    <label class="block text-xs text-gray-500 mb-1">Level 1</label>
                    <input type="number" value="5" min="0" class="w-full px-2 py-1 border border-gray-300 rounded-md level-size-input" data-commission="0.0025">
                  </div>
                  <div class="col-span-1">
                    <label class="block text-xs text-gray-500 mb-1">Level 2</label>
                    <input type="number" value="25" min="0" class="w-full px-2 py-1 border border-gray-300 rounded-md level-size-input" data-commission="0.0025">
                  </div>
                  <div class="col-span-1">
                    <label class="block text-xs text-gray-500 mb-1">Level 3</label>
                    <input type="number" value="125" min="0" class="w-full px-2 py-1 border border-gray-300 rounded-md level-size-input" data-commission="0.005">
                  </div>
                  <div class="col-span-1">
                    <label class="block text-xs text-gray-500 mb-1">Level 4</label>
                    <input type="number" value="625" min="0" class="w-full px-2 py-1 border border-gray-300 rounded-md level-size-input" data-commission="0.01">
                  </div>
                </div>
                <div class="grid grid-cols-4 gap-2 mt-2">
                  <div class="col-span-1">
                    <label class="block text-xs text-gray-500 mb-1">Level 5</label>
                    <input type="number" value="3125" min="0" class="w-full px-2 py-1 border border-gray-300 rounded-md level-size-input" data-commission="0.02">
                  </div>
                  <div class="col-span-1">
                    <label class="block text-xs text-gray-500 mb-1">Level 6</label>
                    <input type="number" value="15625" min="0" class="w-full px-2 py-1 border border-gray-300 rounded-md level-size-input" data-commission="0.03">
                  </div>
                  <div class="col-span-1">
                    <label class="block text-xs text-gray-500 mb-1">Level 7</label>
                    <input type="number" value="78125" min="0" class="w-full px-2 py-1 border border-gray-300 rounded-md level-size-input" data-commission="0.04">
                  </div>
                  <div class="col-span-1">
                    <label class="block text-xs text-gray-500 mb-1">Level 8</label>
                    <input type="number" value="390625" min="0" class="w-full px-2 py-1 border border-gray-300 rounded-md level-size-input" data-commission="0.05">
                  </div>
                </div>
              </div>
            </div>

            <div>
              <div class="bg-indigo-50 rounded-xl p-6 border border-indigo-100 h-full">
                <h3 class="text-lg font-semibold text-indigo-800 mb-4">Projected Monthly Earnings</h3>
                <div class="space-y-4">
                  <div>
                    <div class="flex justify-between mb-1">
                      <span class="text-sm font-medium text-gray-700">Direct Sales (Level 8)</span>
                      <span class="text-sm font-medium text-indigo-600" id="directSalesEarnings">₱2,500</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                      <div class="bg-indigo-600 h-2 rounded-full" style="width: 25%" id="directSalesProgress"></div>
                    </div>
                  </div>
                  <div>
                    <div class="flex justify-between mb-1">
                      <span class="text-sm font-medium text-gray-700">Upline Overrides (Levels 1-7)</span>
                      <span class="text-sm font-medium text-indigo-600" id="uplineOverridesEarnings">₱11,100</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                      <div class="bg-indigo-500 h-2 rounded-full" style="width: 70%" id="uplineOverridesProgress"></div>
                    </div>
                  </div>
                  <div class="pt-4 mt-4 border-t border-gray-200">
                    <div class="flex justify-between">
                      <span class="font-medium text-gray-800">Total Earnings</span>
                      <span class="text-xl font-bold text-green-600" id="totalEarnings">₱13,600</span>
                    </div>
                  </div>
                </div>

                <div class="mt-6 bg-white p-4 rounded-lg border border-gray-200">
                  <h4 class="text-sm font-medium text-gray-700 mb-2">Assumptions:</h4>
                  <ul class="text-xs text-gray-600 space-y-1">
                    <li class="flex items-start">
                      <i class="fas fa-circle text-2xs mt-1 mr-2 text-indigo-500"></i>
                      <span>Each member makes 1 sale per month</span>
                    </li>
                    <li class="flex items-start">
                      <i class="fas fa-circle text-2xs mt-1 mr-2 text-indigo-500"></i>
                      <span>5% monthly growth in network size</span>
                    </li>
                    <li class="flex items-start">
                      <i class="fas fa-circle text-2xs mt-1 mr-2 text-indigo-500"></i>
                      <span>Based on current network size inputs</span>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gradient-to-r from-gray-800 to-gray-900 text-white py-8 mt-12">
      <div class="max-w-7xl mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
          <div>
            <h3 class="text-lg font-semibold mb-4 flex items-center">
              <i class="fas fa-network-wired mr-2"></i> Reverse 8-Tier
            </h3>
            <p class="text-sm text-gray-400">A sustainable network marketing model where direct uplines earn higher commissions, decreasing as you move up.</p>
          </div>
          <div>
            <h3 class="text-lg font-semibold mb-4">Reverse Commission</h3>
            <ul class="space-y-2 text-sm text-gray-400">
              <li class="flex items-center">
                <i class="fas fa-level-up-alt rotate-90 mr-2 text-xs text-indigo-400"></i>
                <span>Level 1: 0.25% (Sponsor)</span>
              </li>
              <li class="flex items-center">
                <i class="fas fa-level-up-alt rotate-90 mr-2 text-xs text-indigo-400"></i>
                <span>Level 2: 0.25%</span>
              </li>
              <li class="flex items-center">
                <i class="fas fa-level-up-alt rotate-90 mr-2 text-xs text-indigo-400"></i>
                <span>Level 3: 0.5%</span>
              </li>
              <li class="flex items-center">
                <i class="fas fa-level-up-alt rotate-90 mr-2 text-xs text-indigo-400"></i>
                <span>Level 4: 1%</span>
              </li>
            </ul>
          </div>
          <div>
            <h3 class="text-lg font-semibold mb-4">&nbsp;</h3>
            <ul class="space-y-2 text-sm text-gray-400">
              <li class="flex items-center">
                <i class="fas fa-level-up-alt rotate-90 mr-2 text-xs text-indigo-400"></i>
                <span>Level 5: 2%</span>
              </li>
              <li class="flex items-center">
                <i class="fas fa-level-up-alt rotate-90 mr-2 text-xs text-indigo-400"></i>
                <span>Level 6: 3%</span>
              </li>
              <li class="flex items-center">
                <i class="fas fa-level-up-alt rotate-90 mr-2 text-xs text-indigo-400"></i>
                <span>Level 7: 4%</span>
              </li>
              <li class="flex items-center">
                <i class="fas fa-level-up-alt rotate-90 mr-2 text-xs text-indigo-400"></i>
                <span>Level 8: 5%</span>
              </li>
            </ul>
          </div>
          <div>
            <h3 class="text-lg font-semibold mb-4">Contact</h3>
            <div class="space-y-3 text-sm text-gray-400">
              <div class="flex items-center">
                <i class="fas fa-envelope mr-2 text-indigo-400"></i>
                <span>support@reverse8tier.com</span>
              </div>
              <div class="flex items-center">
                <i class="fas fa-phone-alt mr-2 text-indigo-400"></i>
                <span>+63 961 440 4313</span>
              </div>
              <div class="flex items-center">
                <i class="fas fa-map-marker-alt mr-2 text-indigo-400"></i>
                <span>Amaya View, Indahag, Cagayan de Oro</span>
              </div>
              <div class="flex space-x-4 mt-4">
                <a href="#" class="text-gray-400 hover:text-white transition-colors">
                  <i class="fab fa-facebook-f"></i>
                </a>
                <a href="#" class="text-gray-400 hover:text-white transition-colors">
                  <i class="fab fa-twitter"></i>
                </a>
                <a href="#" class="text-gray-400 hover:text-white transition-colors">
                  <i class="fab fa-instagram"></i>
                </a>
                <a href="#" class="text-gray-400 hover:text-white transition-colors">
                  <i class="fab fa-linkedin-in"></i>
                </a>
              </div>
            </div>
          </div>
        </div>
        <div class="mt-8 pt-6 border-t border-gray-700 text-center text-sm text-gray-400">
          <p>© 2025 SmartFi Rewards Program. All rights reserved.</p>
        </div>
      </div>
    </footer>
  </div>

  <script>
    const priceSlider = document.getElementById('priceSlider');
    const priceInput = document.getElementById('priceInput');
    const directSalesEarnings = document.getElementById('directSalesEarnings');
    const uplineOverridesEarnings = document.getElementById('uplineOverridesEarnings');
    const totalEarnings = document.getElementById('totalEarnings');
    const directSalesProgress = document.getElementById('directSalesProgress');
    const uplineOverridesProgress = document.getElementById('uplineOverridesProgress');
    const levelSizeInputs = document.querySelectorAll('.level-size-input');

    const commissionRates = {
        '1': 0.0025, '2': 0.0025, '3': 0.005, '4': 0.01,
        '5': 0.02, '6': 0.03, '7': 0.04, '8': 0.05
    };

    function calculateEarnings() {
      const productPrice = parseFloat(priceInput.value);
      if (isNaN(productPrice)) return;

      let directSales = 0;
      let uplineOverrides = 0;

      levelSizeInputs.forEach(input => {
        const level = parseInt(input.previousElementSibling.textContent.replace('Level ', ''));
        const networkSize = parseInt(input.value);
        const commissionRate = commissionRates[level];

        if (!isNaN(networkSize) && networkSize > 0 && commissionRate) {
          const earnings = productPrice * commissionRate * networkSize;
          if (level === 8) {
            directSales += earnings;
          } else {
            uplineOverrides += earnings;
          }
        }
      });

      const total = directSales + uplineOverrides;

      directSalesEarnings.textContent = `₱${directSales.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}`;
      uplineOverridesEarnings.textContent = `₱${uplineOverrides.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}`;
      totalEarnings.textContent = `₱${total.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}`;

      const directSalesPercentage = total > 0 ? (directSales / total) * 100 : 0;
      const uplineOverridesPercentage = total > 0 ? (uplineOverrides / total) * 100 : 0;

      directSalesProgress.style.width = `${directSalesPercentage}%`;
      uplineOverridesProgress.style.width = `${uplineOverridesPercentage}%`;
    }

    priceSlider.addEventListener('input', function() {
      priceInput.value = this.value;
      calculateEarnings();
    });

    priceInput.addEventListener('input', function() {
      if (this.value > 50000) this.value = 50000;
      if (this.value < 5000) this.value = 5000;
      priceSlider.value = this.value;
      calculateEarnings();
    });

    levelSizeInputs.forEach(input => {
        input.addEventListener('input', calculateEarnings);
    });

    // Initial calculation
    calculateEarnings();



    // leg-worker earnings:

const individualPriceSlider = document.getElementById('individualPriceSlider');
    const individualPriceInput = document.getElementById('individualPriceInput');
    const directDownlinesSlider = document.getElementById('directDownlinesSlider');
    const directDownlinesInput = document.getElementById('directDownlinesInput');
    const salesPerPersonSlider = document.getElementById('salesPerPersonSlider');
    const salesPerPersonInput = document.getElementById('salesPerPersonInput');
    const totalIndividualEarnings = document.getElementById('totalIndividualEarnings');
    const individualEarningsBreakdown = document.getElementById('individualEarningsBreakdown');

    // Commission rates are already defined as 'commissionRates'

    function calculateIndividualEarnings() {
      const productPrice = parseFloat(individualPriceInput.value);
      const directDownlines = parseInt(directDownlinesInput.value);
      const salesPerPerson = parseInt(salesPerPersonInput.value);

      if (isNaN(productPrice) || isNaN(directDownlines) || isNaN(salesPerPerson) || productPrice <= 0 || directDownlines <= 0 || salesPerPerson <= 0) {
        totalIndividualEarnings.textContent = `₱0`;
        individualEarningsBreakdown.innerHTML = '';
        return;
      }

      let totalEarnings = 0;
      let breakdownHtml = '';
      let currentLevelMembers = 1; // You are level 0, your direct downlines are level 1
      let cumulativeMembers = 0;

      for (let level = 1; level <= 8; level++) {
        // For the first level (your direct downlines), it's the directDownlines input
        // For subsequent levels, it's currentLevelMembers * directDownlines (powerleg)
        const membersAtThisLevel = (level === 1) ? directDownlines : (currentLevelMembers * directDownlines);
        
        // This is a cumulative count for visualization
        cumulativeMembers += membersAtThisLevel; 

        // Important: Your commission on level X comes from the sales of people X levels below you.
        // So, if you are calculating for Level 1's sales (your direct downlines), you earn the Level 8 commission.
        // If you are calculating for Level 2's sales, you earn the Level 7 commission, and so on.
        // The mapping is (9 - level_number) -> commissionRates key
        const effectiveCommissionLevel = 9 - level; // For Level 1 downlines, you get Level 8 commission
        const commissionRate = commissionRates[effectiveCommissionLevel.toString()];

        if (commissionRate) {
          const earningsFromThisLevel = productPrice * commissionRate * membersAtThisLevel * salesPerPerson;
          totalEarnings += earningsFromThisLevel;

          breakdownHtml += `
            <div class="flex justify-between mb-1">
              <span class="text-sm font-medium text-gray-700">From Level ${level} Downlines (${membersAtThisLevel} people)</span>
              <span class="text-sm font-medium text-indigo-600">₱${earningsFromThisLevel.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                <div class="bg-indigo-600 h-2 rounded-full" style="width: ${Math.min(100, (earningsFromThisLevel / totalEarnings) * 100)}%"></div>
            </div>
          `;
        }
        currentLevelMembers = membersAtThisLevel; // Update for the next iteration
      }

      totalIndividualEarnings.textContent = `₱${totalEarnings.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}`;
      individualEarningsBreakdown.innerHTML = breakdownHtml;
    }

    individualPriceSlider.addEventListener('input', function() {
      individualPriceInput.value = this.value;
      calculateIndividualEarnings();
    });

    individualPriceInput.addEventListener('input', function() {
      if (this.value > 50000) this.value = 50000;
      if (this.value < 5000) this.value = 5000;
      individualPriceSlider.value = this.value;
      calculateIndividualEarnings();
    });

    directDownlinesSlider.addEventListener('input', function() {
      directDownlinesInput.value = this.value;
      calculateIndividualEarnings();
    });

    directDownlinesInput.addEventListener('input', function() {
      if (this.value > 10) this.value = 10;
      if (this.value < 1) this.value = 1;
      directDownlinesSlider.value = this.value;
      calculateIndividualEarnings();
    });

    salesPerPersonSlider.addEventListener('input', function() {
      salesPerPersonInput.value = this.value;
      calculateIndividualEarnings();
    });

    salesPerPersonInput.addEventListener('input', function() {
      if (this.value > 10) this.value = 10;
      if (this.value < 1) this.value = 1;
      salesPerPersonSlider.value = this.value;
      calculateIndividualEarnings();
    });

    // Initial calculation for the new segment
    calculateIndividualEarnings();

  </script>
</body>
</html>