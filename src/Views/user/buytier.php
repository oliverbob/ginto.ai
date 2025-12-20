
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SmartFi Rewards Program - Unlock Residual Income</title>
  <link href="/assets/css/tailwind.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      --primary-500: #6366f1;
      --primary-600: #4f46e5;
      --primary-700: #4338ca;
      --purple-500: #a855f7;
      --purple-600: #9333ea;
    }

    .glow-card {
      box-shadow: 0 0 25px rgba(99, 102, 241, 0.2);
      transition: all 0.3s ease-in-out;
    }
    .glow-card:hover {
      box-shadow: 0 0 35px rgba(99, 102, 241, 0.4);
      transform: translateY(-2px);
    }

    /* Wizard styles */
    .wizard-container {
      max-width: 800px;
      margin: 0 auto;
    }
    .wizard-header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 20px;
    }
    .wizard-step {
      flex: 1;
      text-align: center;
      position: relative;
    }
    .step-number {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: #e0e0e0;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 10px;
      font-weight: bold;
      color: #666;
      border: 3px solid #e0e0e0;
      transition: all 0.3s;
    }
    .step-title {
      font-size: 14px;
      color: #666;
      transition: all 0.3s;
    }
    .wizard-step.active .step-number {
      background: var(--primary-500);
      color: white;
      border-color: var(--primary-500);
    }
    .wizard-step.active .step-title {
      color: var(--primary-500);
      font-weight: bold;
    }
    .wizard-step.completed .step-number {
      background: #10B981;
      color: white;
      border-color: #10B981;
    }
    .wizard-step.completed .step-title {
      color: #10B981;
    }
    .wizard-step:not(:last-child)::after {
      content: '';
      position: absolute;
      top: 20px;
      left: 60%;
      right: -40%;
      height: 2px;
      background: #e0e0e0;
      z-index: -1;
    }
    .wizard-step.completed:not(:last-child)::after {
      background: #10B981;
    }

    .wizard-content {
      display: none;
      animation: fadeIn 0.5s;
    }
    .wizard-content.active {
      display: block;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    /* Network graph container */
    #networkGraph {
      width: 100%;
      height: 400px;
      background: #f8f9fa;
      border-radius: 10px;
      margin: 20px 0;
      position: relative;
      overflow: hidden;
      background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiPgogIDxkZWZzPgogICAgPHBhdHRlcm4gaWQ9Im5ldHdvcmtQYXR0ZXJuIiBwYXR0ZXJuVW5pdHM9InVzZXJTcGFjZU9uVXNlIiB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCI+CiAgICAgIDxjaXJjbGUgY3g9IjUwIiBjeT0iNTAiIHI9IjMiIGZpbGw9IiM2MzY2ZjEiIG9wYWNpdHk9IjAuNSIvPgogICAgICA8Y2lyY2xlIGN4PSIxMCIgY3k9IjEwIiByPSIyIiBmaWxsPSIjNjM2NmYxIiBvcGFjaXR5PSIwLjMiLz4KICAgICAgPGNpcmNsZSBjeD0iOTAiIGN5PSIxMCIgcj0iMiIgZmlsbD0iIzYzNjZmMSIgb3BhY2l0eT0iMC4zIi8+CiAgICAgIDxjaXJjbGUgY3g9IjEwIiBjeT0iOTAiIHI9IjIiIGZpbGw9IiM2MzY2ZjEiIG9wYWNpdHk9IjAuMyIvPgogICAgICA8Y2lyY2xlIGN4PSI5MCIgY3k9IjkwIiByPSIyIiBmaWxsPSIjNjM2NmYxIiBvcGFjaXR5PSIwLjMiLz4KICAgICAgPGxpbmUgeDE9IjUwIiB5MT0iNTAiIHgyPSIxMCIgeTI9IjEwIiBzdHJva2U9IiM2MzY2ZjEiIHN0cm9rZS13aWR0aD0iMSIgb3BhY2l0eT0iMC4yIi8+CiAgICAgIDxsaW5lIHgxPSI1MCIgeTE9IjUwIiB4Mj0iOTAiIHkyPSIxMCIgc3Ryb2tlPSIjNjM2NmYxIiBzdHJva2Utd2lkdGg9IjEiIG9wYWNpdHk9IjAuMiIvPgogICAgICA8bGluZSB4MT0iNTAiIHkxPSI1MCIgeDI9IjEwIiB5Mj0iOTAiIHN0cm9rZT0iIzYzNjZmMSIgc3Ryb2tlLXdpZHRoPSIxIiBvcGFjaXR5PSIwLjIiLz4KICAgICAgPGxpbmUgeDE9IjUwIiB5MT0iNTAiIHgyPSI5MCIgeTI9IjkwIiBzdHJva2U9IiM2MzY2ZjEiIHN0cm9rZS13aWR0aD0iMSIgb3BhY2l0eT0iMC4yIi8+CiAgICA8L3BhdHRlcm4+CiAgPC9kZWZzPgogIDxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbGw9InVybCgjbmV0d29ya1BhdHRlcm4pIiAvPgo8L3N2Zz4=');
      background-size: cover;
    }

    .node {
      position: absolute;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: var(--primary-500);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      transition: all 0.3s;
      cursor: pointer;
      z-index: 2;
    }
    .node:hover {
      transform: scale(1.1);
      box-shadow: 0 6px 10px rgba(0,0,0,0.15);
    }
    .node.level-1 { background: #10B981; }
    .node.level-2 { background: #3B82F6; }
    .node.level-3 { background: #F59E0B; }
    .node.level-4 { background: #EF4444; }
    .node.level-5 { background: #8B5CF6; }
    .node.level-6 { background: #EC4899; }
    .node.level-7 { background: #14B8A6; }
    .node.level-8 { background: var(--primary-600); }

    .connector {
      position: absolute;
      background: #ddd;
      height: 2px;
      transform-origin: left center;
      z-index: 1;
    }

    /* Tier cards */
    .tier-card {
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      transition: all 0.3s;
      cursor: pointer;
    }
    .tier-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    }
    .tier-card.selected {
      border: 2px solid var(--primary-500);
      background: rgba(99, 102, 241, 0.05);
    }
    .tier-card .tier-badge {
      position: absolute;
      top: -10px;
      right: 10px;
      background: var(--primary-500);
      color: white;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: bold;
    }

    /* Animation classes */
    .animate-fade-in { animation: fadeIn 0.5s ease-out; }
    .animate-pulse-light { animation: pulseLight 2s infinite; }
    @keyframes pulseLight {
      0%, 100% { box-shadow: 0 0 0 0 rgba(168, 85, 247, 0.4); }
      50% { box-shadow: 0 0 0 10px rgba(168, 85, 247, 0); }
    }

    /* Program overview styles */
    .commission-grid {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr 1fr;
      gap: 1rem;
    }
    .commission-header {
      font-weight: bold;
      border-bottom: 1px solid #e5e7eb;
      padding-bottom: 0.5rem;
    }
    .commission-row {
      display: contents;
    }
    .commission-row > div {
      padding: 0.5rem 0;
      border-bottom: 1px solid #f3f4f6;
    }
    .commission-row:last-child > div {
      border-bottom: none;
    }

    /* FAQ styles */
    .faq-item {
      border-bottom: 1px solid #e5e7eb;
      padding-bottom: 1.5rem;
      margin-bottom: 1.5rem;
    }
    .faq-question {
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .faq-answer {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease-out;
    }
    .faq-answer.show {
      max-height: 500px;
      transition: max-height 0.5s ease-in;
    }
  </style>
</head>
<body class="bg-gray-50 font-sans text-gray-800">
  <div class="min-h-screen flex flex-col">
    <!-- Header/Navigation Bar -->
    <header class="bg-gradient-to-r from-indigo-800 to-purple-900 text-white shadow-lg sticky top-0 z-50">
        <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex justify-between items-center">
            <!-- Logo/Brand -->
            <a href="#" class="flex items-center space-x-3">
                <i class="fas fa-network-wired text-3xl text-indigo-300"></i>
                <span class="text-2xl font-bold">SmartFi Rewards</span>
            </a>
            
            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center space-x-6">
                <a href="#program-overview" class="hover:text-indigo-200 transition-colors">Program</a>
                <a href="#earnings-estimator" class="hover:text-indigo-200 transition-colors">Earnings</a>
                <a href="#how-it-works" class="hover:text-indigo-200 transition-colors">Join Now</a>
                <a href="#faq" class="hover:text-indigo-200 transition-colors">FAQ</a>
                <a href="https://smartfed.ai/login" class="bg-indigo-600 hover:bg-indigo-700 transition-colors px-4 py-2 rounded-lg font-semibold shadow-md flex items-center space-x-2">
                <i class="fas fa-user-circle"></i>
                <span>Login</span>
                </a>
            </div>
            
            <!-- Mobile Menu Button -->
            <button id="mobile-menu-btn" class="md:hidden text-white focus:outline-none">
                <i class="fas fa-bars text-2xl"></i>
            </button>
            </div>
            
            <!-- Mobile Menu (hidden by default) -->
            <div id="mobile-menu" class="hidden md:hidden mt-4 pb-4 space-y-3">
            <a href="#program-overview" class="block hover:text-indigo-200 transition-colors py-2">Program</a>
            <a href="#earnings-estimator" class="block hover:text-indigo-200 transition-colors py-2">Earnings</a>
            <a href="#how-it-works" class="block hover:text-indigo-200 transition-colors py-2">Join Now</a>
            <a href="#faq" class="block hover:text-indigo-200 transition-colors py-2">FAQ</a>
            <a href="https://smartfed.ai/login" class="bg-indigo-600 hover:bg-indigo-700 transition-colors px-4 py-2 rounded-lg font-semibold shadow-md flex items-center justify-center space-x-2 mt-4">
                <i class="fas fa-user-circle"></i>
                <span>Login</span>
            </a>
            </div>
        </nav>
    </header>

<script>
  const btn = document.getElementById('mobile-menu-btn');
  const menu = document.getElementById('mobile-menu');
  const menuItems = menu.querySelectorAll('a');
  
  // Toggle menu when hamburger button is clicked
  btn.addEventListener('click', () => {
    menu.classList.toggle('hidden');
    // Toggle icon between bars and X
    const icon = btn.querySelector('i');
    icon.classList.toggle('fa-bars');
    icon.classList.toggle('fa-times');
  });
  
  // Close menu when any menu item is clicked
  menuItems.forEach(item => {
    item.addEventListener('click', () => {
      menu.classList.add('hidden');
      // Reset icon to hamburger
      const icon = btn.querySelector('i');
      icon.classList.remove('fa-times');
      icon.classList.add('fa-bars');
    });
  });
  
  // Optional: Close menu when clicking outside
  document.addEventListener('click', (e) => {
    if (!menu.contains(e.target) && !btn.contains(e.target)) {
      menu.classList.add('hidden');
      const icon = btn.querySelector('i');
      icon.classList.remove('fa-times');
      icon.classList.add('fa-bars');
    }
  });
</script>

    <!-- Hero Section with Network Visualization -->
    <section class="bg-gradient-to-br from-indigo-700 to-purple-800 text-white py-20 px-4 overflow-hidden relative">
      <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between relative z-10">
        <div class="md:w-1/2 text-center md:text-left mb-10 md:mb-0">
          <span class="text-indigo-200 text-lg font-semibold uppercase mb-3 block">Your Path to Financial Freedom</span>
          <h1 class="text-5xl lg:text-6xl font-extrabold leading-tight mb-6">
            Build Your Network. <br>Earn <span class="text-purple-300">Residual Income</span>.
          </h1>
          <p class="text-indigo-200 text-xl mb-8 max-w-lg mx-auto md:mx-0">
            Join thousands earning passive income through our revolutionary 8-tier commission plan.
          </p>
          <div class="flex flex-col sm:flex-row justify-center md:justify-start space-y-4 sm:space-y-0 sm:space-x-4">
            <a href="#how-it-works" class="bg-purple-500 hover:bg-purple-600 transition-all duration-300 text-white px-8 py-4 rounded-full text-lg font-bold shadow-xl transform hover:scale-105">
              Join Now <i class="fas fa-arrow-right ml-2"></i>
            </a>
            <a href="#program-overview" class="bg-white bg-opacity-20 hover:bg-opacity-30 transition-all duration-300 text-white px-8 py-4 rounded-full text-lg font-semibold shadow-xl transform hover:scale-105">
              Learn More <i class="fas fa-info-circle ml-2"></i>
            </a>
          </div>
        </div>
        <div class="md:w-1/2 flex justify-center">
          <div id="networkGraph" class="animate-pulse-light">
            <!-- Network visualization will be displayed here -->
          </div>
        </div>
      </div>
    </section>

    <!-- Benefits & Features Section -->
    <section class="py-16 px-4 bg-gray-50">
      <div class="max-w-7xl mx-auto text-center">
        <h2 class="text-4xl font-extrabold text-gray-900 mb-4">Why SmartFi Rewards?</h2>
        <p class="text-xl text-gray-600 mb-12 max-w-2xl mx-auto">
          Our unique "Commission" model ensures long-term sustainability and rewards growth where it matters most – at the front lines.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div class="bg-white p-8 rounded-xl shadow-lg glow-card flex flex-col items-center transform hover:scale-105 transition-transform duration-300">
            <div class="text-5xl text-purple-600 mb-4">
              <i class="fas fa-hand-holding-usd"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-3">Sustainable Income</h3>
            <p class="text-gray-600 text-center">
              Built for longevity, our model ensures fair and consistent payouts, reducing churn and fostering a stable community.
            </p>
          </div>

          <div class="bg-white p-8 rounded-xl shadow-lg glow-card flex flex-col items-center transform hover:scale-105 transition-transform duration-300">
            <div class="text-5xl text-indigo-600 mb-4">
              <i class="fas fa-chart-line"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-3">Higher Direct Payouts</h3>
            <p class="text-gray-600 text-center">
              The deeper your direct network, the higher your commission. You're generously rewarded for bringing in new customers.
            </p>
          </div>

          <div class="bg-white p-8 rounded-xl shadow-lg glow-card flex flex-col items-center transform hover:scale-105 transition-transform duration-300">
            <div class="text-5xl text-green-600 mb-4">
              <i class="fas fa-shield-alt"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-3">Transparent & Fair</h3>
            <p class="text-gray-600 text-center">
              No hidden fees, no complex calculations. See exactly how your efforts translate into earnings with our clear structure.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Program Overview & Commission Structure -->
    <section id="program-overview" class="py-16 px-4 bg-white">
      <div class="max-w-7xl mx-auto">
        <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-8 glow-card">
          <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4">
            <h2 class="text-3xl font-bold text-white flex items-center">
              <i class="fas fa-info-circle mr-3"></i> SmartFi Commission Structure
            </h2>
            <p class="text-indigo-200 text-lg mt-1">Unlock superior earnings for your direct sales!</p>
          </div>
          <div class="p-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
              <div>
                <h3 class="text-2xl font-semibold text-indigo-800 mb-5">Commission Breakdown (Per ₱10,000 Sale)</h3>
                <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                  <div class="grid grid-cols-4 gap-4 text-sm font-bold text-gray-700 mb-3 border-b pb-2 border-gray-200">
                    <span>Level</span>
                    <span>Percentage</span>
                    <span class="text-right">Commission</span>
                    <span class="text-right">Cumulative</span>
                  </div>
                  <div class="space-y-3">
                    <div class="grid grid-cols-4 gap-4 py-2 border-b border-gray-100 items-center">
                      <span class="font-medium text-gray-700">1 (Sponsor)</span>
                      <span class="text-indigo-600">0.25%</span>
                      <span class="text-right font-bold text-green-600">₱25</span>
                      <span class="text-right text-gray-500">₱25</span>
                    </div>
                    <div class="grid grid-cols-4 gap-4 py-2 border-b border-gray-100 items-center">
                      <span class="font-medium text-gray-700">2</span>
                      <span class="text-indigo-600">0.25%</span>
                      <span class="text-right font-bold text-green-600">₱25</span>
                      <span class="text-right text-gray-500">₱50</span>
                    </div>
                    <div class="grid grid-cols-4 gap-4 py-2 border-b border-gray-100 items-center">
                      <span class="font-medium text-gray-700">3</span>
                      <span class="text-indigo-600">0.5%</span>
                      <span class="text-right font-bold text-green-600">₱50</span>
                      <span class="text-right text-gray-500">₱100</span>
                    </div>
                    <div class="grid grid-cols-4 gap-4 py-2 border-b border-gray-100 items-center">
                      <span class="font-medium text-gray-700">4</span>
                      <span class="text-indigo-600">1%</span>
                      <span class="text-right font-bold text-green-600">₱100</span>
                      <span class="text-right text-gray-500">₱200</span>
                    </div>
                    <div class="grid grid-cols-4 gap-4 py-2 border-b border-gray-100 items-center">
                      <span class="font-medium text-gray-700">5</span>
                      <span class="text-indigo-600">2%</span>
                      <span class="text-right font-bold text-green-600">₱200</span>
                      <span class="text-right text-gray-500">₱400</span>
                    </div>
                    <div class="grid grid-cols-4 gap-4 py-2 border-b border-gray-100 items-center">
                      <span class="font-medium text-gray-700">6</span>
                      <span class="text-indigo-600">3%</span>
                      <span class="text-right font-bold text-green-600">₱300</span>
                      <span class="text-right text-gray-500">₱700</span>
                    </div>
                    <div class="grid grid-cols-4 gap-4 py-2 border-b border-gray-100 items-center">
                      <span class="font-medium text-gray-700">7</span>
                      <span class="text-indigo-600">4%</span>
                      <span class="text-right font-bold text-green-600">₱400</span>
                      <span class="text-right text-gray-500">₱1,100</span>
                    </div>
                    <div class="grid grid-cols-4 gap-4 py-2 items-center">
                      <span class="font-bold text-indigo-800">8 (Direct Sale)</span>
                      <span class="font-bold text-purple-600">5%</span>
                      <span class="text-right font-extrabold text-green-600 text-lg">₱500</span>
                      <span class="text-right text-gray-600">₱1,600</span>
                    </div>
                  </div>
                  <div class="mt-6 pt-4 border-t border-gray-200 flex justify-between items-center">
                    <span class="text-xl font-bold text-gray-900">Total Commissions Paid</span>
                    <span class="text-2xl font-extrabold text-green-700">₱1,600 (16% of sale)</span>
                  </div>
                </div>
              </div>
              <div>
                <h3 class="text-2xl font-semibold text-indigo-800 mb-5">Profit Distribution & Key Advantages</h3>
                <div class="bg-indigo-50 rounded-lg p-6 border border-indigo-100">
                  <div class="flex items-center justify-between mb-5 pb-3 border-b border-indigo-200">
                    <div class="flex items-center">
                      <div class="w-14 h-14 rounded-full bg-indigo-100 flex items-center justify-center mr-4 shadow">
                        <i class="fas fa-building text-indigo-700 text-2xl"></i>
                      </div>
                      <div>
                        <h4 class="font-bold text-indigo-800 text-xl">Company Net Profit</h4>
                        <p class="text-sm text-gray-600">After all payouts & operational costs</p>
                      </div>
                    </div>
                    <span class="text-3xl font-extrabold text-green-600">₱1,600</span>
                  </div>
                  <div class="space-y-4 text-lg">
                    <div class="flex justify-between items-center">
                      <span class="text-gray-700 font-medium">Product Cost</span>
                      <span class="font-bold text-gray-800">₱5,000</span>
                    </div>
                    <div class="flex justify-between items-center">
                      <span class="text-gray-700 font-medium">Total Commission Payouts</span>
                      <span class="font-bold text-red-500">- ₱1,600</span>
                    </div>
                    <div class="flex justify-between items-center">
                      <span class="text-gray-700 font-medium">Operational Costs</span>
                      <span class="font-bold text-red-500">- ₱1,800</span>
                    </div>
                    <div class="pt-4 mt-4 border-t border-gray-200 flex justify-between items-center">
                      <span class="font-bold text-xl text-gray-900">Company Net Profit</span>
                      <span class="font-extrabold text-green-600 text-2xl">₱1,600</span>
                    </div>
                  </div>
                </div>

                <h3 class="text-2xl font-semibold text-indigo-800 mt-10 mb-5">Key Benefits of Commission</h3>
                <div class="space-y-4">
                  <div class="flex items-start">
                    <div class="flex-shrink-0 mt-1">
                      <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center shadow-sm">
                        <i class="fas fa-star text-sm"></i>
                      </div>
                    </div>
                    <p class="ml-3 text-lg text-gray-700">
                      <strong>Maximize Your Direct Sales:</strong> You earn the highest 5% commission on sales directly attributed to your efforts (Level 8).
                    </p>
                  </div>
                  <div class="flex items-start">
                    <div class="flex-shrink-0 mt-1">
                      <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center shadow-sm">
                        <i class="fas fa-users text-sm"></i>
                      </div>
                    </div>
                    <p class="ml-3 text-lg text-gray-700">
                      <strong>Fair Upline Rewards:</strong> Commissions decrease as you move up the upline (to your sponsor and their upline), ensuring the person closest to the sale gets the best cut.
                    </p>
                  </div>
                  <div class="flex items-start">
                    <div class="flex-shrink-0 mt-1">
                      <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center shadow-sm">
                        <i class="fas fa-sync-alt text-sm"></i>
                      </div>
                    </div>
                    <p class="ml-3 text-lg text-gray-700">
                      <strong>Long-term Sustainability:</strong> With total commission capped at 16%, our model is designed for stable growth and avoids aggressive payout structures that often lead to instability.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Interactive Earnings Estimator -->
<section id="earnings-estimator" class="py-16 px-4 bg-gray-50">
  <div class="max-w-7xl mx-auto text-center mb-12">
    <h2 class="text-4xl font-extrabold text-gray-900 mb-4">Calculate Your Potential Earnings!</h2>
    <p class="text-xl text-gray-600 max-w-2xl mx-auto">
      See for yourself how SmartFi's Commission structure can lead to substantial residual income. Adjust the sliders and watch your earnings grow!
    </p>
  </div>

  <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-12">
    <!-- Individual Earnings -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden glow-card p-8">
      <div class="bg-gradient-to-r from-purple-500 to-indigo-500 px-6 py-4 -mx-8 -mt-8 mb-8">
        <h3 class="text-2xl font-bold text-white flex items-center">
          <i class="fas fa-user-tie mr-3"></i> Your Personal Earnings Projection
        </h3>
      </div>
      <div class="space-y-8">
        <div>
          <label for="individualPriceSlider" class="block text-lg font-medium text-gray-700 mb-3">Product Price (₱)</label>
          <input type="range" min="5000" max="50000" step="1000" value="10000" id="individualPriceSlider" class="w-full h-2 bg-indigo-200 rounded-lg appearance-none cursor-pointer accent-indigo-600">
          <div class="flex justify-between mt-2 text-sm text-gray-500">
            <span>₱5,000</span>
            <span>₱50,000</span>
          </div>
          <div class="mt-4 relative">
            <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-500 text-xl font-medium">₱</span>
            <input type="number" id="individualPriceInput" value="10000" class="pl-10 w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-xl font-medium">
          </div>
        </div>

        <div>
          <label for="directDownlinesSlider" class="block text-lg font-medium text-gray-700 mb-3">Number of Direct Downlines (Your Powerleg)</label>
          <input type="range" min="1" max="10" step="1" value="5" id="directDownlinesSlider" class="w-full h-2 bg-indigo-200 rounded-lg appearance-none cursor-pointer accent-indigo-600">
          <div class="flex justify-between mt-2 text-sm text-gray-500">
            <span>1 Person</span>
            <span>10 People</span>
          </div>
          <div class="mt-4">
            <input type="number" id="directDownlinesInput" value="5" min="1" max="10" class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-xl font-medium">
          </div>
        </div>

        <div>
          <label for="salesPerPersonSlider" class="block text-lg font-medium text-gray-700 mb-3">Monthly Sales per Person in Your Network</label>
          <input type="range" min="1" max="10" step="1" value="1" id="salesPerPersonSlider" class="w-full h-2 bg-indigo-200 rounded-lg appearance-none cursor-pointer accent-indigo-600">
          <div class="flex justify-between mt-2 text-sm text-gray-500">
            <span>1 Sale</span>
            <span>10 Sales</span>
          </div>
          <div class="mt-4">
            <input type="number" id="salesPerPersonInput" value="1" min="1" max="10" class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-xl font-medium">
          </div>
        </div>
      </div>

      <div class="bg-indigo-50 rounded-xl p-6 border border-indigo-100 mt-10">
        <h3 class="text-xl font-semibold text-indigo-800 mb-5">Your Projected Monthly Earnings</h3>
        <div id="individualEarningsBreakdown" class="space-y-4">
          <!-- Earnings per level will be populated here by JS -->
        </div>
        <div class="pt-6 mt-6 border-t border-gray-200 flex justify-between items-center">
          <span class="font-bold text-xl text-gray-800">Total Individual Earnings</span>
          <span class="text-3xl font-extrabold text-green-600" id="totalIndividualEarnings">₱0</span>
        </div>
      </div>
      <div class="mt-6 text-center text-sm text-gray-500 italic">
        *Calculations assume a stable, expanding network based on your inputs.
      </div>
    </div>

    <!-- Company Earnings / Network-Wide Earnings -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden glow-card p-8">
  <div class="bg-gradient-to-r from-indigo-500 to-purple-500 px-6 py-4 -mx-8 -mt-8 mb-8">
    <h3 class="text-2xl font-bold text-white flex items-center">
      <i class="fas fa-building mr-3"></i> Network-Wide Earnings Potential
    </h3>
  </div>
  <div class="space-y-8">
    <div>
      <label for="priceSlider" class="block text-lg font-medium text-gray-700 mb-3">Product Price (₱)</label>
      <input type="range" min="5000" max="50000" step="1000" value="10000" id="priceSlider" class="w-full h-2 bg-purple-200 rounded-lg appearance-none cursor-pointer accent-purple-600">
      <div class="flex justify-between mt-2 text-sm text-gray-500">
        <span>₱5,000</span>
        <span>₱50,000</span>
      </div>
      <div class="mt-4 relative">
        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-500 text-xl font-medium">₱</span>
        <input type="number" id="priceInput" value="10000" class="pl-10 w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-xl font-medium">
      </div>
    </div>

    <div>
      <label for="networkSalesPerPersonSlider" class="block text-lg font-medium text-gray-700 mb-3">Monthly Sales per Person in Network</label>
      <input type="range" min="1" max="10" step="1" value="1" id="networkSalesPerPersonSlider" class="w-full h-2 bg-purple-200 rounded-lg appearance-none cursor-pointer accent-purple-600">
      <div class="flex justify-between mt-2 text-sm text-gray-500">
        <span>1 Sale</span>
        <span>10 Sales</span>
      </div>
      <div class="mt-4">
        <input type="number" id="networkSalesPerPersonInput" value="1" min="1" max="10" class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-xl font-medium">
      </div>
    </div>

    <div>
      <label class="block text-lg font-medium text-gray-700 mb-3">Estimate Your Network Size (People per Level)</label>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="col-span-1">
          <label class="block text-sm text-gray-600 mb-1">L1 <span class="text-2xs">(0.25%)</span></label>
          <input type="number" value="5" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md level-size-input focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div class="col-span-1">
          <label class="block text-sm text-gray-600 mb-1">L2 <span class="text-2xs">(0.25%)</span></label>
          <input type="number" value="25" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md level-size-input">
        </div>
        <div class="col-span-1">
          <label class="block text-sm text-gray-600 mb-1">L3 <span class="text-2xs">(0.5%)</span></label>
          <input type="number" value="125" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md level-size-input">
        </div>
        <div class="col-span-1">
          <label class="block text-sm text-gray-600 mb-1">L4 <span class="text-2xs">(1%)</span></label>
          <input type="number" value="625" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md level-size-input">
        </div>
        <div class="col-span-1">
          <label class="block text-sm text-gray-600 mb-1">L5 <span class="text-2xs">(2%)</span></label>
          <input type="number" value="3125" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md level-size-input">
        </div>
        <div class="col-span-1">
          <label class="block text-sm text-gray-600 mb-1">L6 <span class="text-2xs">(3%)</span></label>
          <input type="number" value="15625" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md level-size-input">
        </div>
        <div class="col-span-1">
          <label class="block text-sm text-gray-600 mb-1">L7 <span class="text-2xs">(4%)</span></label>
          <input type="number" value="78125" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md level-size-input">
        </div>
        <div class="col-span-1">
          <label class="block text-sm text-gray-600 mb-1">L8 <span class="text-2xs">(5%)</span></label>
          <input type="number" value="390625" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md level-size-input">
        </div>
      </div>
    </div>
  </div>

  <div class="bg-purple-50 rounded-xl p-6 border border-purple-100 mt-10">
    <h3 class="text-xl font-semibold text-purple-800 mb-5">Network-Wide Projected Earnings by Level</h3>
    <div id="networkEarningsBreakdown" class="space-y-3">
      <!-- Earnings breakdown will be populated here -->
    </div>
    <div class="pt-6 mt-6 border-t-2 border-purple-200">
      <div class="flex justify-between items-center mb-3">
        <span class="font-bold text-xl text-gray-800">Total Network Earnings</span>
        <span class="text-3xl font-extrabold text-green-600" id="totalNetworkEarnings">₱0</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-gray-600">Your Share from L8 Sales</span>
        <span class="font-bold text-purple-600 text-xl" id="yourNetworkShare">₱0</span>
      </div>
    </div>
  </div>
</div>
</section>

    <!-- Wizard Section -->
    <section id="how-it-works" class="py-16 px-4 bg-white">
      <div class="max-w-7xl mx-auto">
        <h2 class="text-4xl font-extrabold text-center text-gray-900 mb-12">Get Started in 4 Simple Steps</h2>
        
        <div class="wizard-container bg-white rounded-xl shadow-lg p-8 glow-card">
          <div class="wizard-header">
            <div class="wizard-step active" data-step="1">
              <div class="step-number">1</div>
              <div class="step-title">Choose Tier</div>
            </div>
            <div class="wizard-step" data-step="2">
              <div class="step-number">2</div>
              <div class="step-title">Personal Info</div>
            </div>
            <div class="wizard-step" data-step="3">
              <div class="step-number">3</div>
              <div class="step-title">Payment</div>
            </div>
            <div class="wizard-step" data-step="4">
              <div class="step-number">4</div>
              <div class="step-title">Complete</div>
            </div>
          </div>

          <!-- Step 1: Choose Tier -->
          <div class="wizard-content active" data-step="1">
            <h3 class="text-2xl font-bold text-gray-800 mb-6">Select Your Membership Tier</h3>
            <p class="text-gray-600 mb-8">Choose the tier that matches your goals. Higher tiers offer greater earning potential.</p>
            
            <!-- Inside Step 1: Choose Tier -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                              <div class="tier-card p-6 relative " data-tier-id="1" data-tier-name="Starter" data-tier-price="5000.00">
                                      <div class="tier-badge">Basic</div>
                                    <h4 class="text-xl font-bold text-gray-800 mb-2">Starter</h4>
                  <p class="text-gray-600 mb-4">Perfect for beginners with basic training.</p>
                  <div class="text-3xl font-extrabold text-indigo-600 mb-4">₱5,000</div>
                  <ul class="space-y-2 mb-6 text-sm text-gray-700">
                    <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Access to Level 1-4 commissions</li>
                                          <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Basic training materials</li>
                      <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Starter kit</li>
                                      </ul>
                  <button type="button" class="w-full font-semibold py-2 px-4 rounded-lg transition-colors select-tier bg-indigo-100 hover:bg-indigo-200 text-indigo-700">
                    Select Plan
                  </button>
                </div>
                                              <div class="tier-card p-6 relative " data-tier-id="2" data-tier-name="Professional" data-tier-price="15000.00">
                                      <div class="tier-badge">Recommended</div>
                                    <h4 class="text-xl font-bold text-gray-800 mb-2">Professional</h4>
                  <p class="text-gray-600 mb-4">For serious earners with advanced training and marketing materials.</p>
                  <div class="text-3xl font-extrabold text-indigo-600 mb-4">₱15,000</div>
                  <ul class="space-y-2 mb-6 text-sm text-gray-700">
                    <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Access to Level 1-6 commissions</li>
                                          <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Advanced training</li>
                      <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Marketing materials</li>
                      <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Priority support</li>
                                      </ul>
                  <button type="button" class="w-full font-semibold py-2 px-4 rounded-lg transition-colors select-tier bg-indigo-100 hover:bg-indigo-200 text-indigo-700">
                    Select Plan
                  </button>
                </div>
                                              <div class="tier-card p-6 relative " data-tier-id="3" data-tier-name="Executive" data-tier-price="45000.00">
                                      <div class="tier-badge">Elite</div>
                                    <h4 class="text-xl font-bold text-gray-800 mb-2">Executive</h4>
                  <p class="text-gray-600 mb-4">Maximum earning potential with elite training and personal mentor.</p>
                  <div class="text-3xl font-extrabold text-indigo-600 mb-4">₱45,000</div>
                  <ul class="space-y-2 mb-6 text-sm text-gray-700">
                    <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Access to Level 1-8 commissions</li>
                                          <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Elite training program</li>
                      <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Personal mentor</li>
                      <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Exclusive events</li>
                      <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> VIP support</li>
                                      </ul>
                  <button type="button" class="w-full font-semibold py-2 px-4 rounded-lg transition-colors select-tier bg-indigo-100 hover:bg-indigo-200 text-indigo-700">
                    Select Plan
                  </button>
                </div>
                          </div>

            <div class="mt-8 flex justify-end">
              <button class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors next-step">
                Next: Personal Info <i class="fas fa-arrow-right ml-2"></i>
              </button>
            </div>
          </div>

          <!-- Step 2: Personal Info -->
          <div class="wizard-content" data-step="2">
            <h3 class="text-2xl font-bold text-gray-800 mb-6">Personal Information</h3>
            <p class="text-gray-600 mb-8">Please provide your details to create your account.</p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
              <div>
                <label class="block text-gray-700 font-medium mb-2">First Name</label>
                <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              </div>
              <div>
                <label class="block text-gray-700 font-medium mb-2">Last Name</label>
                <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              </div>
              <div>
                <label class="block text-gray-700 font-medium mb-2">Email Address</label>
                <input type="email" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              </div>
              <div>
                <label class="block text-gray-700 font-medium mb-2">Phone Number</label>
                <input type="tel" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              </div>
              <div>
                <label class="block text-gray-700 font-medium mb-2">Password</label>
                <input type="password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              </div>
              <div>
                <label class="block text-gray-700 font-medium mb-2">Confirm Password</label>
                <input type="password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              </div>
            </div>

            <div class="mt-8 flex justify-between">
              <button class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-3 px-6 rounded-lg transition-colors prev-step">
                <i class="fas fa-arrow-left mr-2"></i> Back
              </button>
              <button class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors next-step">
                Next: Payment <i class="fas fa-arrow-right ml-2"></i>
              </button>
            </div>
          </div>

          <!-- Step 3: Payment -->
          <div class="wizard-content" data-step="3">
            <h3 class="text-2xl font-bold text-gray-800 mb-6">Payment Information</h3>
            <p class="text-gray-600 mb-8">Complete your membership purchase.</p>

            <!-- The form for submission -->
            <form id="tierPurchaseForm" action="/buytier/process" method="POST">
              <!-- Add your CSRF token here -->
              <input type="hidden" name="csrf_token" value="28510e721a83ae3807d6d9253b353cee97c665274df0c60973bb3060c8347361">
              <input type="hidden" name="tier_plan_id" id="form-tier-plan-id">
              <input type="hidden" name="purchase_price" id="form-purchase-price">
              <input type="hidden" name="payment_method" id="form-payment-method">
              
              <!-- HIDDEN INPUT FOR TIER PLAN ID -->
              <input type="hidden" name="tier_plan_id" id="hiddenTierPlanId" value="">

              <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Order Summary (can remain display-only or include hidden inputs) -->
                <div>
                  <h4 class="text-lg font-semibold text-gray-800 mb-4">Order Summary</h4>
                  <div class="bg-gray-50 p-6 rounded-lg">
                    <div class="flex justify-between items-center mb-4 pb-4 border-b border-gray-200">
                      <span class="font-medium">Membership Tier:</span>
                      <span class="font-bold" id="selected-tier">Professional</span>
                    </div>
                    <div class="flex justify-between items-center mb-4 pb-4 border-b border-gray-200">
                      <span class="font-medium">Price:</span>
                      <span class="font-bold" id="payment-step-price">₱15,000</span>
                    </div>
                    <div class="flex justify-between items-center mb-4 pb-4 border-b border-gray-200">
                      <span class="font-medium">Tax:</span>
                      <span class="font-bold">₱0</span>
                    </div>
                    <div class="flex justify-between items-center">
                      <span class="text-lg font-bold">Total:</span>
                      <span class="text-xl font-extrabold text-indigo-600" id="payment-total">₱15,000</span>
                    </div>
                  </div>
                </div>

                <!-- Payment Method & Details -->
                <div>
                  <h4 class="text-lg font-semibold text-gray-800 mb-4">Payment Method</h4>
                  <div class="space-y-4">
                    <div class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:border-indigo-500">
                      <input type="radio" name="payment_method_option" id="credit-card" value="credit_card" class="h-5 w-5 text-indigo-600 focus:ring-indigo-500" checked>
                      <label for="credit-card" class="ml-3 block font-medium text-gray-700">Credit/Debit Card</label>
                    </div>
                    <div class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:border-indigo-500">
                      <input type="radio" name="payment_method_option" id="gcash" value="gcash" class="h-5 w-5 text-indigo-600 focus:ring-indigo-500">
                      <label for="gcash" class="ml-3 block font-medium text-gray-700">GCash</label>
                    </div>
                    <div class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:border-indigo-500">
                      <input type="radio" name="payment_method_option" id="bank-transfer" value="bank_transfer" class="h-5 w-5 text-indigo-600 focus:ring-indigo-500">
                      <label for="bank-transfer" class="ml-3 block font-medium text-gray-700">Bank Transfer</label>
                    </div>
                    <!-- Add Maya/GoTyme if you want them selectable here -->
                  </div>

                  <div id="credit-card-form" class="mt-6">
                    <div class="grid grid-cols-1 gap-4">
                      <div>
                        <label class="block text-gray-700 font-medium mb-2">Card Number</label>
                        <input type="text" name="card_number" placeholder="1234 5678 9012 3456" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                      </div>
                      <div class="grid grid-cols-2 gap-4">
                        <div>
                          <label class="block text-gray-700 font-medium mb-2">Expiry Date</label>
                          <input type="text" name="expiry_date" placeholder="MM/YY" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                          <label class="block text-gray-700 font-medium mb-2">CVV</label>
                          <input type="text" name="cvv" placeholder="123" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                      </div>
                      <div>
                        <label class="block text-gray-700 font-medium mb-2">Cardholder Name</label>
                        <input type="text" name="cardholder_name" placeholder="John Doe" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                      </div>
                    </div>
                  </div>
                  <!-- Other payment forms can go here, hidden by default -->
                </div>
              </div>

              <div class="mt-8 flex justify-between">
                <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-3 px-6 rounded-lg transition-colors prev-step">
                  <i class="fas fa-arrow-left mr-2"></i> Back
                </button>
                <button type="submit" id="completePurchaseButton" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                  Complete Purchase <i class="fas fa-check ml-2"></i>
                </button>
              </div>
            </form>
          </div>

          <!-- Step 4: Complete -->
          <div class="wizard-content" data-step="4">
            <div class="text-center py-10">
              <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-check text-green-500 text-4xl"></i>
              </div>
              <h3 class="text-3xl font-bold text-gray-800 mb-4">Congratulations!</h3>
              <p class="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
                Your SmartFi Rewards membership has been successfully activated. Welcome to the team!
              </p>
              <div class="bg-indigo-50 p-6 rounded-lg max-w-2xl mx-auto mb-8">
                <h4 class="text-lg font-semibold text-indigo-800 mb-4">Your Membership Details</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <span class="block text-sm text-gray-500">Membership ID:</span>
                    <span class="block font-bold">SMF-2025-78945</span>
                  </div>
                  <div>
                    <span class="block text-sm text-gray-500">Tier:</span>
                    <span class="block font-bold" id="completed-tier">Professional</span>
                  </div>
                  <div>
                    <span class="block text-sm text-gray-500">Activation Date:</span>
                    <span class="block font-bold">October 23, 2025</span>
                  </div>
                  <div>
                    <span class="block text-sm text-gray-500">Next Payment:</span>
                    <span class="block font-bold">November 23, 2025</span>
                  </div>
                </div>
              </div>
              <div class="space-y-4 max-w-md mx-auto">
                <a href="#" class="block bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                  Go to Dashboard <i class="fas fa-tachometer-alt ml-2"></i>
                </a>
                <a href="#" class="block bg-white hover:bg-gray-50 text-indigo-600 font-semibold py-3 px-6 rounded-lg border border-indigo-600 transition-colors">
                  Invite Friends <i class="fas fa-user-plus ml-2"></i>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="py-16 px-4 bg-gray-50">
      <div class="max-w-7xl mx-auto">
        <h2 class="text-4xl font-extrabold text-center text-gray-900 mb-12">Frequently Asked Questions</h2>
        
        <div class="bg-white rounded-xl shadow-lg overflow-hidden glow-card">
          <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4">
            <h3 class="text-3xl font-bold text-white flex items-center">
              <i class="fas fa-question-circle mr-3"></i> Common Questions
            </h3>
          </div>
          <div class="p-8">
            <div class="space-y-6">
              <div class="faq-item">
                <div class="faq-question">
                  <span class="font-semibold text-lg text-gray-800">How does the 8-tier commission structure work?</span>
                  <i class="fas fa-plus text-indigo-600 ml-3"></i>
                </div>
                <div class="faq-answer mt-3 text-gray-600">
                  <p>Our 8-tier commission structure rewards you for both direct sales and the sales made by those in your network. Commissions decrease as you move up the upline, ensuring the person closest to the sale earns the most. You earn:</p>
                  <ul class="list-disc list-inside mt-2 pl-4 space-y-1">
                    <li>0.25% from Level 1 (your direct sponsor)</li>
                    <li>0.25% from Level 2</li>
                    <li>0.5% from Level 3</li>
                    <li>1% from Level 4</li>
                    <li>2% from Level 5</li>
                    <li>3% from Level 6</li>
                    <li>4% from Level 7</li>
                    <li>5% from Level 8 (your direct sales)</li>
                  </ul>
                </div>
              </div>

              <div class="faq-item">
                <div class="faq-question">
                  <span class="font-semibold text-lg text-gray-800">What are the requirements to join SmartFi Rewards?</span>
                  <i class="fas fa-plus text-indigo-600 ml-3"></i>
                </div>
                <div class="faq-answer mt-3 text-gray-600">
                  <p>To join SmartFi Rewards, you must:</p>
                  <ul class="list-disc list-inside mt-2 pl-4 space-y-1">
                    <li>Be at least 18 years old</li>
                    <li>Select a membership tier (Basic, Professional, or Executive)</li>
                    <li>Complete the registration process</li>
                    <li>Accept our terms and conditions</li>
                  </ul>
                  <p class="mt-3">No prior experience is required—we provide training and support to help you succeed.</p>
                </div>
              </div>

              <div class="faq-item">
                <div class="faq-question">
                  <span class="font-semibold text-lg text-gray-800">How and when do I get paid?</span>
                  <i class="fas fa-plus text-indigo-600 ml-3"></i>
                </div>
                <div class="faq-answer mt-3 text-gray-600">
                  <p>Commissions are calculated daily and paid out weekly, every Friday. Payments are made directly to your:</p>
                  <ul class="list-disc list-inside mt-2 pl-4 space-y-1">
                    <li>Bank account (via direct deposit)</li>
                    <li>GCash wallet</li>
                    <li>GoTyme</li>
                    <li>Crypto Wallet</li>
                    <li>Other digital payment methods (Maya, etc.)</li>
                  </ul>
                  <p class="mt-3">Minimum payout threshold is ₱500. Payments are processed automatically once you reach this amount.</p>
                </div>
              </div>

              <div class="faq-item">
                <div class="faq-question">
                  <span class="font-semibold text-lg text-gray-800">What's the difference between the membership tiers?</span>
                  <i class="fas fa-plus text-indigo-600 ml-3"></i>
                </div>
                <div class="faq-answer mt-3 text-gray-600">
                  <p>The three membership tiers offer different levels of access and earning potential:</p>
                  <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-indigo-50 p-4 rounded-lg">
                      <h4 class="font-bold text-indigo-800 mb-2">Basic (₱5,000)</h4>
                      <ul class="list-disc list-inside pl-4 space-y-1 text-sm">
                        <li>Access to Levels 1-4 commissions</li>
                        <li>Basic training materials</li>
                        <li>Digital starter kit</li>
                      </ul>
                    </div>
                    <div class="bg-purple-50 p-4 rounded-lg">
                      <h4 class="font-bold text-purple-800 mb-2">Professional (₱15,000)</h4>
                      <ul class="list-disc list-inside pl-4 space-y-1 text-sm">
                        <li>Access to Levels 1-6 commissions</li>
                        <li>Advanced training program</li>
                        <li>Marketing materials</li>
                        <li>Priority support</li>
                      </ul>
                    </div>
                    <div class="bg-indigo-50 p-4 rounded-lg">
                      <h4 class="font-bold text-indigo-800 mb-2">Executive (₱45,000)</h4>
                      <ul class="list-disc list-inside pl-4 space-y-1 text-sm">
                        <li>Access to all 8 levels</li>
                        <li>Elite training & mentorship</li>
                        <li>Exclusive events</li>
                        <li>VIP support</li>
                        <li>Physical welcome kit</li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>

              <div class="faq-item">
                <div class="faq-question">
                  <span class="font-semibold text-lg text-gray-800">Is there a monthly fee or recurring charges?</span>
                  <i class="fas fa-plus text-indigo-600 ml-3"></i>
                </div>
                <div class="faq-answer mt-3 text-gray-600">
                  <p>No! SmartFi Rewards has a one-time membership fee based on the tier you select (Basic, Professional, or Executive). There are no recurring monthly fees.</p>
                  <p class="mt-3">Your membership includes lifetime access to our platform, training materials, and commission structure.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Call to Action -->
    <section class="py-16 px-4 bg-gradient-to-r from-indigo-700 to-purple-800 text-white">
      <div class="max-w-7xl mx-auto text-center">
        <h2 class="text-4xl font-extrabold mb-6">Ready to Start Earning?</h2>
        <p class="text-xl text-indigo-200 mb-8 max-w-2xl mx-auto">
          Join thousands of members already building their financial future with SmartFi Rewards.
        </p>
        <a href="#how-it-works" class="inline-block bg-white hover:bg-gray-100 text-indigo-700 font-bold px-8 py-4 rounded-full text-lg shadow-xl transform hover:scale-105 transition-transform duration-300">
          Get Started Today <i class="fas fa-arrow-right ml-2"></i>
        </a>
      </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-300 py-12 px-4">
      <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-4 gap-10">
        <div>
          <div class="flex items-center space-x-3 mb-4">
            <i class="fas fa-network-wired text-3xl text-indigo-300"></i>
            <span class="text-2xl font-bold text-white">SmartFi Rewards</span>
          </div>
          <p class="text-gray-400 mb-4">
            Building financial futures through sustainable network marketing since 2025.
          </p>
          <div class="flex space-x-4">
            <a href="#" class="text-gray-400 hover:text-white transition-colors">
              <i class="fab fa-facebook-f text-xl"></i>
            </a>
            <a href="#" class="text-gray-400 hover:text-white transition-colors">
              <i class="fab fa-twitter text-xl"></i>
            </a>
            <a href="#" class="text-gray-400 hover:text-white transition-colors">
              <i class="fab fa-instagram text-xl"></i>
            </a>
            <a href="#" class="text-gray-400 hover:text-white transition-colors">
              <i class="fab fa-youtube text-xl"></i>
            </a>
          </div>
        </div>

        <div>
          <h4 class="text-white font-bold text-lg mb-4">Quick Links</h4>
          <ul class="space-y-3">
            <li><a href="#" class="hover:text-white transition-colors">Home</a></li>
            <li><a href="#program-overview" class="hover:text-white transition-colors">Program</a></li>
            <li><a href="#earnings-estimator" class="hover:text-white transition-colors">Earnings Calculator</a></li>
            <li><a href="#how-it-works" class="hover:text-white transition-colors">Join Now</a></li>
            <li><a href="#faq" class="hover:text-white transition-colors">FAQ</a></li>
          </ul>
        </div>

        <div>
          <h4 class="text-white font-bold text-lg mb-4">Company</h4>
          <ul class="space-y-3">
            <li><a href="#" class="hover:text-white transition-colors">About Us</a></li>
            <li><a href="#" class="hover:text-white transition-colors">Leadership</a></li>
            <li><a href="#" class="hover:text-white transition-colors">Careers</a></li>
            <li><a href="#" class="hover:text-white transition-colors">Blog</a></li>
            <li><a href="#" class="hover:text-white transition-colors">Contact</a></li>
          </ul>
        </div>

        <div>
          <h4 class="text-white font-bold text-lg mb-4">Legal</h4>
          <ul class="space-y-3">
            <li><a href="#" class="hover:text-white transition-colors">Terms of Service</a></li>
            <li><a href="#" class="hover:text-white transition-colors">Privacy Policy</a></li>
            <li><a href="#" class="hover:text-white transition-colors">Refund Policy</a></li>
            <li><a href="#" class="hover:text-white transition-colors">Compliance</a></li>
          </ul>
        </div>
      </div>

      <div class="max-w-7xl mx-auto mt-12 pt-8 border-t border-gray-800 text-center text-gray-500 text-sm">
        <p>&copy; 2025 SmartFi Rewards. All rights reserved.</p>
        <p class="mt-2">SmartFi Rewards is an independent marketing program and is not affiliated with or endorsed by any major financial institution.</p>
      </div>
    </footer>
  </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Smooth scrolling for navigation links
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
      e.preventDefault();
      const target = this.getAttribute('href');
      if (target !== '#') {
        document.querySelector(target).scrollIntoView({
          behavior: 'smooth'
        });
      }
    });
  });

  // Wizard functionality
  const wizardSteps = document.querySelectorAll('.wizard-step');
  const wizardContents = document.querySelectorAll('.wizard-content');
  const nextButtons = document.querySelectorAll('.next-step');
  const prevButtons = document.querySelectorAll('.prev-step');
  const tierCards = document.querySelectorAll('.tier-card');
  const selectTierButtons = document.querySelectorAll('.select-tier');
  const hiddenTierPlanIdInput = document.getElementById('hiddenTierPlanId');

  // Track selected tier
  let selectedTierId = hiddenTierPlanIdInput ? hiddenTierPlanIdInput.value ? parseInt(hiddenTierPlanIdInput.value) : null : null;
  let selectedTierName = '';
  let selectedTierPrice = 0;

  // Helper to format currency
  const formatCurrency = (amount) => {
    return `₱${amount.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
  };

  // Function to update tier selection UI and hidden input
  function updateTierSelection(tierId, tierName, tierPrice) {
    selectedTierId = tierId;
    selectedTierName = tierName;
    selectedTierPrice = tierPrice;

    // Update hidden input field
    if (hiddenTierPlanIdInput) {
      hiddenTierPlanIdInput.value = tierId;
    }

    tierCards.forEach(c => {
      const cardTierId = parseInt(c.dataset.tierId);
      const btn = c.querySelector('button');

      if (cardTierId === tierId) {
        c.classList.add('selected', 'border-2', 'border-indigo-300');
        btn.classList.remove('bg-indigo-100', 'hover:bg-indigo-200', 'text-indigo-700');
        btn.classList.add('bg-indigo-600', 'hover:bg-indigo-700', 'text-white');
      } else {
        c.classList.remove('selected', 'border-2', 'border-indigo-300');
        btn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700', 'text-white');
        btn.classList.add('bg-indigo-100', 'hover:bg-indigo-200', 'text-indigo-700');
      }
    });
  }

  // Initialize selection based on preselected tier
  if (selectedTierId && tierCards.length > 0) {
    const preselectedCard = document.querySelector(`.tier-card[data-tier-id="${selectedTierId}"]`);
    if (preselectedCard) {
      updateTierSelection(
        selectedTierId,
        preselectedCard.dataset.tierName,
        parseFloat(preselectedCard.dataset.tierPrice)
      );
    }
  } else if (tierCards.length > 0) {
    // Default to Professional or first card
    const professionalCard = document.querySelector('.tier-card[data-tier-name="Professional"]');
    if (professionalCard) {
      updateTierSelection(
        parseInt(professionalCard.dataset.tierId),
        professionalCard.dataset.tierName,
        parseFloat(professionalCard.dataset.tierPrice)
      );
    } else {
      const firstCard = tierCards[0];
      updateTierSelection(
        parseInt(firstCard.dataset.tierId),
        firstCard.dataset.tierName,
        parseFloat(firstCard.dataset.tierPrice)
      );
    }
  }

  // Tier selection event listener
  selectTierButtons.forEach(button => {
    button.addEventListener('click', function(e) {
      e.stopPropagation();
      const card = this.closest('.tier-card');
      const tierId = parseInt(card.dataset.tierId);
      const tierName = card.dataset.tierName;
      const tierPrice = parseFloat(card.dataset.tierPrice);
      updateTierSelection(tierId, tierName, tierPrice);
    });
  });

  // Wizard navigation function
  function goToStep(step) {
    // Update wizard step indicators
    wizardSteps.forEach((s, index) => {
      const stepNum = index + 1;
      s.classList.remove('active', 'completed');
      
      if (stepNum < step) {
        s.classList.add('completed');
      } else if (stepNum === step) {
        s.classList.add('active');
      }
    });

    // Update wizard content
    wizardContents.forEach(content => {
      const contentStep = parseInt(content.dataset.step);
      content.classList.remove('active');
      
      if (contentStep === step) {
        content.classList.add('active');
      }
    });

    // Update details for payment and complete steps
    if (step === 3) {
      const selectedTierEl = document.getElementById('selected-tier');
      const paymentStepPriceEl = document.getElementById('payment-step-price');
      const paymentTotalEl = document.getElementById('payment-total');
      
      if (selectedTierEl) selectedTierEl.textContent = selectedTierName;
      if (paymentStepPriceEl) paymentStepPriceEl.textContent = formatCurrency(selectedTierPrice);
      if (paymentTotalEl) paymentTotalEl.textContent = formatCurrency(selectedTierPrice);
    } else if (step === 4) {
      const completedTierEl = document.getElementById('completed-tier');
      if (completedTierEl) completedTierEl.textContent = selectedTierName;
    }

    // Scroll to wizard container
    const wizardContainer = document.querySelector('.wizard-container');
    if (wizardContainer) {
      wizardContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }

  // Next button handlers
  nextButtons.forEach(button => {
    button.addEventListener('click', function() {
      const currentStep = parseInt(this.closest('.wizard-content').dataset.step);
      goToStep(currentStep + 1);
    });
  });

  // Previous button handlers
  prevButtons.forEach(button => {
    button.addEventListener('click', function() {
      const currentStep = parseInt(this.closest('.wizard-content').dataset.step);
      goToStep(currentStep - 1);
    });
  });

  // Initialize wizard to step 1
  goToStep(1);

  // Payment method toggle
  const paymentMethods = document.querySelectorAll('input[name="payment_method_option"]');
  const creditCardForm = document.getElementById('credit-card-form');
  
  paymentMethods.forEach(method => {
    method.addEventListener('change', function() {
      if (creditCardForm) {
        creditCardForm.style.display = this.value === 'credit_card' ? 'block' : 'none';
      }
    });
  });

  // Network Graph Visualization
  const networkGraphDiv = document.getElementById('networkGraph');
  if (networkGraphDiv) {
    const nodes = [];
    const connectors = [];
    const maxLevels = 8;
    const nodesToDisplayPerLevel = [1, 2, 3, 4, 5, 6, 7, 1];

    function createNode(id, level, label, isYou = false) {
      const node = document.createElement('div');
      node.className = `node level-${level}`;
      node.textContent = label;
      node.dataset.id = id;
      node.dataset.level = level;
      if (isYou) node.classList.add('node-you');
      networkGraphDiv.appendChild(node);
      nodes.push({
        element: node,
        id: id,
        level: level,
        label: label
      });
      return node;
    }

    function createConnector(node1, node2) {
      const connector = document.createElement('div');
      connector.className = 'connector';
      networkGraphDiv.appendChild(connector);
      connectors.push({
        element: connector,
        node1: node1,
        node2: node2
      });
      return connector;
    }

    function updateConnectorPosition(connector) {
      const rect1 = connector.node1.element.getBoundingClientRect();
      const rect2 = connector.node2.element.getBoundingClientRect();
      const graphRect = networkGraphDiv.getBoundingClientRect();

      const x1 = rect1.left + rect1.width / 2 - graphRect.left;
      const y1 = rect1.top + rect1.height / 2 - graphRect.top;
      const x2 = rect2.left + rect2.width / 2 - graphRect.left;
      const y2 = rect2.top + rect2.height / 2 - graphRect.top;

      const length = Math.sqrt((x2 - x1) ** 2 + (y2 - y1) ** 2);
      const angle = Math.atan2(y2 - y1, x2 - x1) * 180 / Math.PI;

      connector.element.style.width = `${length}px`;
      connector.element.style.transform = `translate(${x1}px, ${y1}px) rotate(${angle}deg)`;
    }

    function drawNetworkGraph() {
      networkGraphDiv.innerHTML = '';
      nodes.length = 0;
      connectors.length = 0;

      const graphWidth = networkGraphDiv.offsetWidth;
      const graphHeight = networkGraphDiv.offsetHeight;
      const nodeSize = 40;
      const verticalSpacing = (graphHeight - nodeSize) / (maxLevels + 1);
      const horizontalPadding = 30;

      let currentNodeId = 0;
      const nodesByLevel = new Array(maxLevels + 1).fill(0).map(() => []);

      for (let level = 1; level <= maxLevels; level++) {
        const numNodesInLevel = nodesToDisplayPerLevel[level - 1];
        const yPos = (level * verticalSpacing) - (nodeSize / 2);

        const availableWidth = graphWidth - (2 * horizontalPadding);
        const horizontalGap = numNodesInLevel > 1 ? availableWidth / (numNodesInLevel - 1) : 0;
        const startX = numNodesInLevel > 1 ? horizontalPadding : (graphWidth / 2 - nodeSize / 2);

        for (let i = 0; i < numNodesInLevel; i++) {
          const isYou = (level === maxLevels && i === Math.floor(numNodesInLevel / 2));
          const nodeLabel = isYou ? 'YOU' : `L${level}`;
          const node = createNode(currentNodeId++, level, nodeLabel, isYou);

          const xPos = startX + (i * horizontalGap) - (nodeSize / 2);
          node.style.left = `${xPos}px`;
          node.style.top = `${yPos}px`;
          nodesByLevel[level].push(node);

          if (level < maxLevels && nodesByLevel[level + 1].length > 0) {
            const targetNodeIndex = Math.floor(Math.random() * nodesByLevel[level + 1].length);
            createConnector({
              element: node
            }, {
              element: nodesByLevel[level + 1][targetNodeIndex]
            });
          }
        }
      }

      setTimeout(() => {
        connectors.forEach(updateConnectorPosition);
      }, 50);
    }

    drawNetworkGraph();
    window.addEventListener('resize', drawNetworkGraph);
  }

  // FAQ Accordion
  document.querySelectorAll('.faq-question').forEach(question => {
    question.addEventListener('click', function() {
      const answer = this.nextElementSibling;
      const icon = this.querySelector('i');

      answer.classList.toggle('show');
      if (answer.classList.contains('show')) {
        icon.classList.remove('fa-plus');
        icon.classList.add('fa-minus');
      } else {
        icon.classList.remove('fa-minus');
        icon.classList.add('fa-plus');
      }
    });
  });

  // Earnings Estimator Logic
  const commissionRates = [0.0025, 0.0025, 0.005, 0.01, 0.02, 0.03, 0.04, 0.05];

  // Individual Earnings Calculator
  const individualPriceSlider = document.getElementById('individualPriceSlider');
  const individualPriceInput = document.getElementById('individualPriceInput');
  const directDownlinesSlider = document.getElementById('directDownlinesSlider');
  const directDownlinesInput = document.getElementById('directDownlinesInput');
  const salesPerPersonSlider = document.getElementById('salesPerPersonSlider');
  const salesPerPersonInput = document.getElementById('salesPerPersonInput');
  const individualEarningsBreakdown = document.getElementById('individualEarningsBreakdown');
  const totalIndividualEarnings = document.getElementById('totalIndividualEarnings');

  function updateIndividualEarnings() {
    if (!individualPriceInput || !directDownlinesInput || !salesPerPersonInput) return;

    const price = parseFloat(individualPriceInput.value);
    const directDownlinesCount = parseFloat(directDownlinesInput.value);
    const salesPerDownline = parseFloat(salesPerPersonInput.value);

    let totalEarnings = 0;
    let breakdownHtml = '';

    const yourDirectSalesAmount = salesPerDownline;
    const yourDirectCommission = yourDirectSalesAmount * price * commissionRates[7];
    totalEarnings += yourDirectCommission;
    breakdownHtml += `
      <div class="flex justify-between items-center py-2 border-b border-gray-100">
        <span class="font-medium text-gray-700">Your Direct Sales (L8): <span class="text-indigo-600">${yourDirectSalesAmount.toFixed(0)} sales</span></span>
        <span class="font-bold text-green-600">${formatCurrency(yourDirectCommission)}</span>
      </div>
    `;

    let currentLevelPeople = directDownlinesCount;
    let maxNetworkDepth = 5;

    for (let level = 7; level >= 1; level--) {
      if (currentLevelPeople === 0) break;

      const salesByThisLevel = currentLevelPeople * salesPerDownline;
      const commission = salesByThisLevel * price * commissionRates[level - 1];

      totalEarnings += commission;
      breakdownHtml += `
        <div class="flex justify-between items-center py-2 border-b border-gray-100">
          <span class="font-medium text-gray-700">Sales by your L${level} downlines: <span class="text-indigo-600">${currentLevelPeople.toFixed(0)} people, ${salesByThisLevel.toFixed(0)} sales</span></span>
          <span class="font-bold text-green-600">${formatCurrency(commission)}</span>
        </div>
      `;

      if ((level - 1) > (8 - maxNetworkDepth)) {
        currentLevelPeople *= directDownlinesCount;
        if (currentLevelPeople > 100000) currentLevelPeople = 100000;
      } else {
        currentLevelPeople = 0;
      }
    }

    if (individualEarningsBreakdown) individualEarningsBreakdown.innerHTML = breakdownHtml;
    if (totalIndividualEarnings) totalIndividualEarnings.textContent = formatCurrency(totalEarnings);
  }

  if (individualPriceSlider) {
    individualPriceSlider.addEventListener('input', () => {
      individualPriceInput.value = individualPriceSlider.value;
      updateIndividualEarnings();
    });
  }

  if (individualPriceInput) {
    individualPriceInput.addEventListener('change', () => {
      let val = parseFloat(individualPriceInput.value);
      if (isNaN(val) || val < 5000) val = 5000;
      if (val > 50000) val = 50000;
      individualPriceInput.value = val;
      if (individualPriceSlider) individualPriceSlider.value = val;
      updateIndividualEarnings();
    });
  }

  if (directDownlinesSlider) {
    directDownlinesSlider.addEventListener('input', () => {
      directDownlinesInput.value = directDownlinesSlider.value;
      updateIndividualEarnings();
    });
  }

  if (directDownlinesInput) {
    directDownlinesInput.addEventListener('change', () => {
      let val = parseFloat(directDownlinesInput.value);
      if (isNaN(val) || val < 1) val = 1;
      if (val > 10) val = 10;
      directDownlinesInput.value = val;
      if (directDownlinesSlider) directDownlinesSlider.value = val;
      updateIndividualEarnings();
    });
  }

  if (salesPerPersonSlider) {
    salesPerPersonSlider.addEventListener('input', () => {
      salesPerPersonInput.value = salesPerPersonSlider.value;
      updateIndividualEarnings();
    });
  }

  if (salesPerPersonInput) {
    salesPerPersonInput.addEventListener('change', () => {
      let val = parseFloat(salesPerPersonInput.value);
      if (isNaN(val) || val < 1) val = 1;
      if (val > 10) val = 10;
      salesPerPersonInput.value = val;
      if (salesPerPersonSlider) salesPerPersonSlider.value = val;
      updateIndividualEarnings();
    });
  }

  updateIndividualEarnings();

  // Network-Wide Earnings Calculator
  const priceSlider = document.getElementById('priceSlider');
  const priceInput = document.getElementById('priceInput');
  const networkSalesPerPersonSlider = document.getElementById('networkSalesPerPersonSlider');
  const networkSalesPerPersonInput = document.getElementById('networkSalesPerPersonInput');
  const levelSizeInputs = document.querySelectorAll('.level-size-input');
  const totalNetworkEarningsEl = document.getElementById('totalNetworkEarnings');
  const yourNetworkShareEl = document.getElementById('yourNetworkShare');
  const networkEarningsBreakdown = document.getElementById('networkEarningsBreakdown');

  const commissionPercentages = [0.25, 0.25, 0.5, 1, 2, 3, 4, 5];

  function updateNetworkEarnings() {
    if (!priceInput || !networkSalesPerPersonInput) return;

    const productPrice = parseFloat(priceInput.value);
    const networkSalesPerPerson = parseFloat(networkSalesPerPersonInput.value);
    let totalNetworkCommission = 0;
    let yourShare = 0;
    let breakdownHtml = '';

    levelSizeInputs.forEach((input, index) => {
      const level = index + 1;
      const numPeople = parseFloat(input.value);
      const commissionRate = commissionPercentages[index] / 100;

      const levelSales = numPeople * networkSalesPerPerson;
      const commissionForLevel = levelSales * productPrice * commissionRate;
      totalNetworkCommission += commissionForLevel;

      const colors = [
        'bg-red-100 border-red-200 text-red-700',
        'bg-orange-100 border-orange-200 text-orange-700',
        'bg-yellow-100 border-yellow-200 text-yellow-700',
        'bg-green-100 border-green-200 text-green-700',
        'bg-blue-100 border-blue-200 text-blue-700',
        'bg-indigo-100 border-indigo-200 text-indigo-700',
        'bg-purple-100 border-purple-200 text-purple-700',
        'bg-pink-100 border-pink-200 text-pink-700'
      ];

      breakdownHtml += `
        <div class="flex justify-between items-center p-3 rounded-lg border ${colors[index]}">
          <div>
            <span class="font-bold">Level ${level}</span>
            <span class="text-sm ml-2">(${commissionPercentages[index]}% commission)</span>
            <div class="text-xs mt-1 opacity-75">${numPeople.toLocaleString()} people × ${networkSalesPerPerson} sales = ${levelSales.toLocaleString()} total sales</div>
          </div>
          <span class="font-bold text-lg">${formatCurrency(commissionForLevel)}</span>
        </div>
      `;

      if (level === 8) {
        yourShare = commissionForLevel;
      }
    });

    if (networkEarningsBreakdown) networkEarningsBreakdown.innerHTML = breakdownHtml;
    if (totalNetworkEarningsEl) totalNetworkEarningsEl.textContent = formatCurrency(totalNetworkCommission);
    if (yourNetworkShareEl) yourNetworkShareEl.textContent = formatCurrency(yourShare);
  }

  if (priceSlider) {
    priceSlider.addEventListener('input', () => {
      priceInput.value = priceSlider.value;
      updateNetworkEarnings();
    });
  }

  if (priceInput) {
    priceInput.addEventListener('change', () => {
      let val = parseFloat(priceInput.value);
      if (isNaN(val) || val < 5000) val = 5000;
      if (val > 50000) val = 50000;
      priceInput.value = val;
      if (priceSlider) priceSlider.value = val;
      updateNetworkEarnings();
    });
  }

  if (networkSalesPerPersonSlider) {
    networkSalesPerPersonSlider.addEventListener('input', () => {
      networkSalesPerPersonInput.value = networkSalesPerPersonSlider.value;
      updateNetworkEarnings();
    });
  }

  if (networkSalesPerPersonInput) {
    networkSalesPerPersonInput.addEventListener('change', () => {
      let val = parseFloat(networkSalesPerPersonInput.value);
      if (isNaN(val) || val < 1) val = 1;
      if (val > 10) val = 10;
      networkSalesPerPersonInput.value = val;
      if (networkSalesPerPersonSlider) networkSalesPerPersonSlider.value = val;
      updateNetworkEarnings();
    });
  }

  levelSizeInputs.forEach(input => {
    input.addEventListener('input', () => {
      let val = parseFloat(input.value);
      if (isNaN(val) || val < 0) val = 0;
      input.value = val;
      updateNetworkEarnings();
    });
  });

  updateNetworkEarnings();
});
</script>
</body>
</html>