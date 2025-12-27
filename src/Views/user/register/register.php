<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ginto Rewards Program - Unlock Residual Income</title>
  <script>
    // Prevent auto-scroll on page load with hash - must run before page renders
    if (window.location.hash) {
      history.replaceState(null, null, window.location.pathname + window.location.search);
    }
  </script>
  <link rel="icon" type="image/png" href="/assets/images/ginto.png">
  <link href="/assets/css/tailwind.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
  
  <!-- PayPal SDK loaded directly for testing -->
  <?php 
  $paypalEnv = $_ENV['PAYPAL_ENVIRONMENT'] ?? getenv('PAYPAL_ENVIRONMENT') ?? 'sandbox';
  $testPaypalClientId = $paypalEnv === 'sandbox' 
    ? ($_ENV['PAYPAL_CLIENT_ID_SANDBOX'] ?? getenv('PAYPAL_CLIENT_ID_SANDBOX') ?? '')
    : ($_ENV['PAYPAL_CLIENT_ID'] ?? getenv('PAYPAL_CLIENT_ID') ?? '');
  $testPaypalClientId = preg_replace('/\s+/', '', $testPaypalClientId);
  ?>
  <script src="https://www.paypal.com/sdk/js?client-id=<?= htmlspecialchars($testPaypalClientId, ENT_QUOTES, 'UTF-8') ?>&currency=USD&intent=capture&components=buttons"></script>
  
  <style>
    /* Consistent vibrant gold button for header */
    .header-login-btn {
      background: linear-gradient(90deg, #ffe53b 0%, #ffb300 100%);
      color: #6b4f00;
      font-weight: 700;
      border: none;
      border-radius: 9999px;
      box-shadow: 0 2px 8px rgba(255, 229, 59, 0.18);
      padding: 0.5rem 1.5rem;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 1rem;
      transition: background 0.2s, color 0.2s, box-shadow 0.2s;
      outline: none;
    }
    .header-login-btn:hover, .header-login-btn:focus {
      background: linear-gradient(90deg, #ffb300 0%, #ffe53b 100%);
      color: #4a3200;
      box-shadow: 0 4px 16px rgba(255, 229, 59, 0.28);
      text-decoration: none;
    }
    .dark .header-login-btn {
      color: #4a3200;
      box-shadow: 0 2px 12px rgba(255, 229, 59, 0.22);
    }
    .dark .header-login-btn:hover, .dark .header-login-btn:focus {
      color: #232946;
      background: linear-gradient(90deg, #ffe53b 0%, #ffb300 100%);
      box-shadow: 0 4px 20px rgba(255, 229, 59, 0.32);
    }

    :root {
      --primary-500: #1fa2ff; /* Energetic blue */
      --primary-600: #12d8fa; /* Lively cyan */
      --primary-700: #53ffb0; /* Fresh green */
      --accent-500: #ffe53b; /* Vibrant yellow */
      --accent-600: #ff6a00; /* Warm orange */
      --positive-500: #53ffb0; /* Positive green */
      --positive-600: #1fa2ff; /* Energetic blue */
    }

    /* Dark mode varables */
    .dark {
      --bg-primary: #181c2b;
      --bg-secondary: #232946;
      --bg-card: #232946;
      --text-primary: #f4faff;
      --text-secondary: #b8c1ec;
      --border-color: #393e5c;
    }

    /* Light mode variables */
    .light {
      --bg-primary: #f4faff;
      --bg-secondary: #e9f7fd;
      --bg-card: #ffffff;
      --text-primary: #232946;
      --text-secondary: #3a506b;
      --border-color: #b8c1ec;
    }

    body {
      background-color: var(--bg-primary);
      color: var(--text-primary);
      transition: background-color 0.3s, color 0.3s;
    }


    .glow-card {
      box-shadow: 0 0 30px 0 rgba(31, 162, 255, 0.12), 0 4px 24px 0 rgba(255, 229, 59, 0.10);
      transition: all 0.3s ease-in-out;
      background: linear-gradient(135deg, var(--bg-card) 80%, var(--accent-500) 100%);
      border: 1.5px solid var(--border-color);
    }
    .glow-card:hover {
      box-shadow: 0 0 40px 0 rgba(31, 162, 255, 0.18), 0 8px 32px 0 rgba(255, 229, 59, 0.18);
      transform: translateY(-4px) scale(1.02);
    }

    /* Theme Toggle */

    .theme-toggle {
      position: fixed;
      bottom: 2rem;
      right: 2rem;
      z-index: 1000;
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary-500), var(--accent-600));
      border: none;
      box-shadow: 0 4px 18px rgba(31, 162, 255, 0.25);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.3s;
    }
    .theme-toggle:hover {
      transform: scale(1.1) rotate(-6deg);
      box-shadow: 0 8px 28px rgba(31, 162, 255, 0.35);
    }
    .theme-toggle i {
      font-size: 26px;
      color: var(--accent-600);
      filter: drop-shadow(0 0 2px var(--primary-500));
    }

    /* Wizard styles */
    .wizard-container {
      max-width: 800px;
      margin: 0 auto;
      position: relative;
      background: linear-gradient(120deg, var(--bg-card) 80%, var(--primary-500) 100%);
    }
    .wizard-header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 20px;
      position: relative;
      z-index: 10;
    }
    .wizard-step {
      flex: 1;
      text-align: center;
      position: relative;
    }
    .step-number {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary-500) 10%, var(--accent-500) 90%);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 10px;
      font-weight: bold;
      color: #fff;
      border: 3px solid var(--border-color);
      box-shadow: 0 2px 8px rgba(31, 162, 255, 0.10);
      transition: all 0.3s;
    }
    .step-title {
      font-size: 14px;
      color: var(--text-secondary);
      transition: all 0.3s;
    }
    .wizard-step.active .step-number {
      background: linear-gradient(135deg, var(--primary-600), var(--accent-600));
      color: #fff;
      border-color: var(--primary-600);
      box-shadow: 0 0 12px var(--primary-600);
    }
    .wizard-step.active .step-title {
      color: var(--primary-600);
      font-weight: bold;
      text-shadow: 0 1px 4px var(--accent-500);
    }
    .wizard-step.completed .step-number {
      background: linear-gradient(135deg, var(--positive-500), var(--primary-500));
      color: #fff;
      border-color: var(--positive-500);
      box-shadow: 0 0 8px var(--positive-500);
    }
    .wizard-step.completed .step-title {
      color: var(--positive-500);
    }
    .wizard-step:not(:last-child)::after {
      content: '';
      position: absolute;
      top: 20px;
      left: 60%;
      right: -40%;
      height: 2px;
      background: var(--border-color);
      z-index: -1;
    }
    .wizard-step.completed:not(:last-child)::after {
      background: #10B981;
    }

    .wizard-content {
      display: none;
      width: 100%;
      min-height: 400px;
      animation: fadeIn 0.5s;
    }
    .wizard-content.active {
      display: block;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    /* Tier cards */

    .tier-card {
      border: 1.5px solid var(--border-color);
      border-radius: 14px;
      transition: all 0.3s;
      cursor: pointer;
      background: linear-gradient(120deg, var(--bg-card) 80%, var(--primary-500) 100%);
      box-shadow: 0 2px 12px rgba(31, 162, 255, 0.08);
      position: relative;
    }
    .tier-card:hover {
      transform: translateY(-6px) scale(1.03);
      box-shadow: 0 8px 24px rgba(31, 162, 255, 0.18), 0 2px 8px rgba(255, 229, 59, 0.10);
      border-color: var(--primary-600);
    }
    .tier-card.selected {
      border: 2.5px solid var(--primary-600);
      background: linear-gradient(135deg, var(--primary-500) 60%, var(--accent-500) 100%);
      box-shadow: 0 0 24px var(--primary-600);
      color: #fff !important;
    }
    .tier-card.selected h4,
    .tier-card.selected p,
    .tier-card.selected div,
    .tier-card.selected span {
      color: #fff !important;
    }
    .tier-card.selected li {
      color: #fff !important;
    }
    .tier-card.selected li i.fa-check {
      color: #e879f9 !important; /* Purple/fuchsia for contrast */
    }
    .tier-card.selected .select-tier {
      background-color: rgba(255,255,255,0.2) !important;
      color: #fff !important;
      border-color: #fff !important;
    }
    .tier-card .tier-badge {
      position: absolute;
      top: -14px;
      right: 14px;
      background: linear-gradient(135deg, var(--primary-600), var(--accent-600));
      color: #fff;
      padding: 4px 14px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: bold;
      box-shadow: 0 2px 8px rgba(31, 162, 255, 0.10);
    }

    /* Gold accent buttons */

    .btn-gold {
      background: linear-gradient(90deg, var(--primary-500) 0%, var(--accent-500) 100%);
      color: #232946;
      font-weight: 700;
      letter-spacing: 0.5px;
      transition: all 0.3s;
      box-shadow: 0 2px 8px rgba(31, 162, 255, 0.10);
      border: none;
    }
    .btn-gold:hover {
      transform: translateY(-2px) scale(1.04);
      box-shadow: 0 8px 24px rgba(31, 162, 255, 0.18), 0 2px 8px rgba(255, 229, 59, 0.18);
      background: linear-gradient(90deg, var(--accent-500) 0%, var(--primary-500) 100%);
      color: #fff;
    }
    
    /* Traveling border light effect */
    @property --border-angle {
      syntax: '<angle>';
      inherits: false;
      initial-value: 0deg;
    }
    
    @keyframes rotateBorder {
      to {
        --border-angle: 360deg;
      }
    }
    
    .btn-gold.pulse-attention {
      border: 3px solid transparent;
      background: 
        linear-gradient(90deg, var(--primary-500) 0%, var(--accent-500) 100%) padding-box,
        conic-gradient(from var(--border-angle), #1fa2ff, #12d8fa, #a6ffcb, #ffe53b, #ff6b6b, #1fa2ff) border-box;
      animation: rotateBorder 2s linear infinite;
    }
    
    .btn-gold.pulse-attention:hover {
      transform: translateY(-2px) scale(1.04);
    }

    /* Input styles */
    input, select {
      background-color: var(--bg-card);
      color: var(--text-primary);
      border: 1px solid var(--border-color);
    }
    input:focus, select:focus {
      outline: none;
      border-color: var(--primary-500);
      box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
    }


    /* Header gradient */
    .header-gradient {
      background: linear-gradient(270deg, var(--primary-500), var(--accent-500), var(--primary-700), var(--accent-600), var(--primary-500));
      background-size: 1200% 1200%;
      animation: animatedGradient 12s ease-in-out infinite;
      box-shadow: 0 4px 24px 0 rgba(31, 162, 255, 0.22), 0 2px 8px 0 rgba(255, 229, 59, 0.10);
      border-bottom: 3px solid var(--accent-500);
    }
    @keyframes animatedGradient {
      0% {background-position: 0% 50%;}
      50% {background-position: 100% 50%;}
      100% {background-position: 0% 50%;}
    }
    .header-gradient a {
      color: #232946;
      font-weight: 600;
      letter-spacing: 0.5px;
      transition: color 0.2s, text-shadow 0.2s;
      text-shadow: 0 2px 8px rgba(255, 229, 59, 0.10);
    }
    .header-gradient a:hover {
      color: var(--primary-600);
      text-shadow: 0 2px 12px var(--accent-500), 0 1px 4px var(--primary-600);
    }

    .header-logo-glow {
      box-shadow: 0 0 0 4px var(--accent-500), 0 0 16px 8px var(--primary-500), 0 0 32px 16px var(--primary-700);
      animation: logoGlow 2.5s ease-in-out infinite alternate;
    }
    @keyframes logoGlow {
      0% { box-shadow: 0 0 0 4px var(--accent-500), 0 0 16px 8px var(--primary-500), 0 0 32px 16px var(--primary-700); }
      100% { box-shadow: 0 0 0 8px var(--accent-600), 0 0 32px 16px var(--primary-600), 0 0 64px 32px var(--primary-700); }
    }
    .header-crown-animate {
      filter: drop-shadow(0 0 8px var(--accent-500));
      animation: crownPulse 1.8s infinite alternate;
    }
    @keyframes crownPulse {
      0% { transform: scale(1) rotate(-6deg); filter: drop-shadow(0 0 8px var(--accent-500)); }
      100% { transform: scale(1.12) rotate(6deg); filter: drop-shadow(0 0 16px var(--accent-600)); }
    }
    .header-title-vibrant {
      color: #232946;
      font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
      letter-spacing: 1.5px;
      text-shadow: 0 2px 8px #ffe53b, 0 1px 4px #12d8fa;
      background: none;
      -webkit-background-clip: initial;
      -webkit-text-fill-color: initial;
      animation: none;
    }
    .dark .header-title-vibrant {
      color: #ffe53b;
      text-shadow: 0 2px 8px #232946, 0 1px 4px #12d8fa;
    }
    .header-tagline {
      font-size: 1.1rem;
      font-weight: 600;
      color: #10b981;
      text-shadow: 0 1px 8px #fff, 0 1px 4px #12d8fa;
      margin-left: 12px;
      letter-spacing: 0.5px;
      animation: taglineFadeIn 2s ease-in;
      background: none;
    }
    .dark .header-tagline {
      color: #ffe53b;
      text-shadow: 0 1px 8px #232946, 0 1px 4px #12d8fa;
    }
    @keyframes taglineFadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .bg-white {
      --tw-bg-opacity: 1;
      background-color:  rgb(179 228 255 / var(--tw-bg-opacity, 1));
    }

    /* Animation classes */
    .animate-fade-in { animation: fadeIn 0.5s ease-out; }
  </style>
</head>
<body class="light font-sans">

    <?php
      // if (session_status() !== PHP_SESSION_ACTIVE) {@session_start();}
      // echo '<div style="background: #fffbe6; color: #333; padding: 8px; border: 1px solid #ffe53b; margin: 8px 0; font-size: 1rem;">';
      // echo 'DEBUG: $_SESSION["referral_code"] = ' . htmlspecialchars($_SESSION['referral_code'] ?? '') . ' | $_GET["ref"] = ' . htmlspecialchars($_GET['ref'] ?? '') . ' | $ref_id = ' . htmlspecialchars($ref_id ?? '');
      // echo '</div>';
    ?>
  
  <!-- Theme Toggle Button -->
  <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
    <i class="fas fa-moon"></i>
  </button>

  <!-- Header/Navigation -->
  <header class="header-gradient shadow-lg sticky top-0 z-50">
    <nav class="max-w-7xl mx-auto px-2 sm:px-4 lg:px-6 py-1.5">
      <div class="flex justify-between items-center min-h-0" style="min-height:unset;">
        <a href="#" class="flex items-center space-x-2">
          <span class="header-logo-glow" style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;background:#fff;box-shadow:0 2px 8px rgba(31,162,255,0.10);overflow:hidden;border:2px solid var(--primary-500);">
            <img src="/assets/images/ginto.png" alt="Ginto Logo" style="width:24px;height:24px;object-fit:cover;display:block;border-radius:50%;" />
          </span>
          <span class="text-lg font-bold header-title-vibrant" style="line-height:1.1;">Ginto Rewards</span>
          <i class="fas fa-crown text-xl header-crown-animate" style="color: var(--accent-500); margin-left: 6px;"></i>
          <span class="header-tagline hidden sm:inline-block" style="font-size:0.95rem;">Feel the Excitement. Shine with Us!</span>
        </a>
        
        <!-- Navigation with Login -->
        <div class="flex items-center space-x-4 md:space-x-6">
          <a href="#program-overview" class="hidden md:inline font-medium transition-colors" style="color: var(--text-primary);">Program</a>
          <a href="#how-it-works" class="hidden md:inline font-medium transition-colors" style="color: var(--text-primary);">Join Now</a>
          <a href="<?php echo BASE_URL; ?>/login" class="header-login-btn" tabindex="0">
            <i class="fas fa-user-circle"></i> Login
          </a>
        </div>
      </div>
    </nav>
  </header>


    <!-- Hero Section with Network Visualization -->
    <section class="text-white py-20 px-4 overflow-hidden relative" style="background: linear-gradient(120deg, var(--primary-500) 0%, var(--accent-500) 100%);">
      <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between relative z-10">
        <div class="md:w-1/2 text-center md:text-left mb-10 md:mb-0">
          <span class="text-lg font-semibold uppercase mb-3 block" style="color: var(--primary-700);">Your Path to Financial Freedom</span>
          <h1 class="text-5xl lg:text-6xl font-extrabold leading-tight mb-6">
            Build Your Network. <br>Earn <span style="color: var(--accent-500);">Residual Income</span>.
          </h1>
          <p class="text-xl mb-8 max-w-lg mx-auto md:mx-0" style="color: var(--text-secondary);">
            Join thousands earning passive income through our revolutionary 8-tier commission plan.
          </p>
          <div class="flex flex-col sm:flex-row justify-center md:justify-start space-y-4 sm:space-y-0 sm:space-x-4">
            <a href="#how-it-works" class="btn-gold px-8 py-4 rounded-full text-lg shadow-xl inline-block">
              Join Now <i class="fas fa-arrow-right ml-2" style="color: var(--primary-600);"></i>
            </a>
            <a href="#program-overview" class="bg-white bg-opacity-20 hover:bg-opacity-30 transition-all duration-300 text-white px-8 py-4 rounded-full text-lg font-semibold shadow-xl transform hover:scale-105">
              Learn More <i class="fas fa-info-circle ml-2" style="color: var(--primary-600);"></i>
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
    <section class="py-16 px-4" style="background: var(--bg-secondary);">
      <div class="max-w-7xl mx-auto text-center">
        <h2 class="text-4xl font-extrabold mb-4" style="color: var(--text-primary);">Why SmartFi Rewards?</h2>
        <p class="text-xl mb-12 max-w-2xl mx-auto" style="color: var(--text-secondary);">
          Our unique "Commission" model ensures long-term sustainability and rewards growth where it matters most – at the front lines.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div class="p-8 rounded-xl shadow-lg glow-card flex flex-col items-center transform hover:scale-105 transition-transform duration-300" style="background: var(--bg-card);">
            <div class="text-5xl mb-4" style="color: var(--primary-600);">
              <i class="fas fa-hand-holding-usd"></i>
            </div>
            <h3 class="text-2xl font-bold mb-3" style="color: var(--text-primary);">Sustainable Income</h3>
            <p class="text-center" style="color: var(--text-secondary);">
              Built for longevity, our model ensures fair and consistent payouts, reducing churn and fostering a stable community.
            </p>
          </div>

          <div class="p-8 rounded-xl shadow-lg glow-card flex flex-col items-center transform hover:scale-105 transition-transform duration-300" style="background: var(--bg-card);">
            <div class="text-5xl mb-4" style="color: var(--primary-500);">
              <i class="fas fa-chart-line"></i>
            </div>
            <h3 class="text-2xl font-bold mb-3" style="color: var(--text-primary);">Higher Direct Payouts</h3>
            <p class="text-center" style="color: var(--text-secondary);">
              The deeper your direct network, the higher your commission. You're generously rewarded for bringing in new customers.
            </p>
          </div>

          <div class="p-8 rounded-xl shadow-lg glow-card flex flex-col items-center transform hover:scale-105 transition-transform duration-300" style="background: var(--bg-card);">
            <div class="text-5xl mb-4" style="color: var(--accent-600);">
              <i class="fas fa-shield-alt"></i>
            </div>
            <h3 class="text-2xl font-bold mb-3" style="color: var(--text-primary);">Transparent & Fair</h3>
            <p class="text-center" style="color: var(--text-secondary);">
              No hidden fees, no complex calculations. See exactly how your efforts translate into earnings with our clear structure.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Program Overview & Commission Structure -->
    <section id="program-overview" class="py-16 px-4 bg-white">
      <div class="max-w-7xl mx-auto">
        <div class="rounded-xl shadow-lg overflow-hidden mb-8 glow-card" style="background: linear-gradient(135deg, var(--bg-card) 70%, var(--primary-700) 100%);">
          <div style="background: linear-gradient(90deg, var(--primary-600) 0%, var(--accent-600) 100%); padding-left: 1.5rem; padding-right: 1.5rem; padding-top: 1rem; padding-bottom: 1rem;">
            <h2 class="text-3xl font-bold flex items-center" style="color: #fff; text-shadow: 0 2px 12px rgba(31,162,255,0.18);">
              <i class="fas fa-info-circle mr-3" style="color: var(--accent-500);"></i> SmartFi Commission Structure
            </h2>
            <p class="text-lg mt-1" style="color: #f4faff; opacity: 0.92;">Unlock superior earnings for your direct sales!</p>
          </div>
          <div class="p-8" style="background: linear-gradient(120deg, rgba(31,162,255,0.10) 0%, rgba(255,229,59,0.10) 100%);">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
              <div>
                <h3 class="text-2xl font-semibold mb-5" style="color: var(--primary-500);">Commission Breakdown (Per ₱150 Sale)</h3>
                <div class="rounded-lg p-6 border" style="background: linear-gradient(135deg, var(--bg-card) 80%, var(--primary-700) 100%); border-color: var(--border-color);">
                  <div class="grid grid-cols-4 gap-4 text-sm font-bold mb-3 border-b pb-2" style="color: var(--text-primary); border-bottom: 1px solid var(--border-color);">
                    <span>Level</span>
                    <span>Percentage</span>
                    <span class="text-right">Commission</span>
                    <span class="text-right">Cumulative</span>
                  </div>
                  <div class="space-y-3">
                    <div class="grid grid-cols-4 gap-4 py-2 border-b items-center" style="border-bottom: 1px solid var(--border-color);">
                      <span class="font-medium" style="color: var(--text-primary);">1 (Sponsor)</span>
                      <span style="color: var(--primary-600);">0.25%</span>
                      <span class="text-right font-bold" style="color: var(--positive-500);">₱0.38</span>
                      <span class="text-right" style="color: var(--text-secondary);">₱0.38</span>
                    </div>
                    <div class="grid grid-cols-4 gap-4 py-2 border-b items-center" style="border-bottom: 1px solid var(--border-color);">
                      <span class="font-medium" style="color: var(--text-primary);">2</span>
                      <span style="color: var(--primary-600);">0.25%</span>
                      <span class="text-right font-bold" style="color: var(--positive-500);">₱0.38</span>
                      <span class="text-right" style="color: var(--text-secondary);">₱0.75</span>
                    </div>
                    <div class="grid grid-cols-4 gap-4 py-2 border-b items-center" style="border-bottom: 1px solid var(--border-color);">
                      <span class="font-medium" style="color: var(--text-primary);">3</span>
                      <span style="color: var(--primary-600);">0.5%</span>
                      <span class="text-right font-bold" style="color: var(--positive-500);">₱0.75</span>
                      <span class="text-right" style="color: var(--text-secondary);">₱1.50</span>
                    </div>
                    <div class="grid grid-cols-4 gap-4 py-2 border-b items-center" style="border-bottom: 1px solid var(--border-color);">
                      <span class="font-medium" style="color: var(--text-primary);">4</span>
                      <span style="color: var(--primary-600);">1%</span>
                      <span class="text-right font-bold" style="color: var(--positive-500);">₱1.50</span>
                      <span class="text-right" style="color: var(--text-secondary);">₱3.00</span>
                    </div>
                    <div class="grid grid-cols-4 gap-4 py-2 border-b items-center" style="border-bottom: 1px solid var(--border-color);">
                      <span class="font-medium" style="color: var(--text-primary);">5</span>
                      <span style="color: var(--primary-600);">2%</span>
                      <span class="text-right font-bold" style="color: var(--positive-500);">₱3.00</span>
                      <span class="text-right" style="color: var(--text-secondary);">₱6.00</span>
                    </div>
                    <div class="grid grid-cols-4 gap-4 py-2 border-b items-center" style="border-bottom: 1px solid var(--border-color);">
                      <span class="font-medium" style="color: var(--text-primary);">6</span>
                      <span style="color: var(--primary-600);">3%</span>
                      <span class="text-right font-bold" style="color: var(--positive-500);">₱4.50</span>
                      <span class="text-right" style="color: var(--text-secondary);">₱10.50</span>
                    </div>
                    <div class="grid grid-cols-4 gap-4 py-2 border-b items-center" style="border-bottom: 1px solid var(--border-color);">
                      <span class="font-medium" style="color: var(--text-primary);">7</span>
                      <span style="color: var(--primary-600);">4%</span>
                      <span class="text-right font-bold" style="color: var(--positive-500);">₱6.00</span>
                      <span class="text-right" style="color: var(--text-secondary);">₱16.50</span>
                    </div>
                    <div class="grid grid-cols-4 gap-4 py-2 items-center">
                      <span class="font-bold" style="color: var(--primary-600);">8 (Your sales)</span>
                      <span class="font-bold" style="color: var(--accent-600);">5%</span>
                      <span class="text-right font-extrabold text-lg" style="color: var(--positive-500);">₱7.50</span>
                      <span class="text-right" style="color: var(--text-secondary);">₱24.00</span>
                    </div>
                  </div>
                  <div class="mt-6 pt-4 flex justify-between items-center" style="border-top: 1px solid var(--border-color);">
                    <span class="text-xl font-bold" style="color: var(--text-primary);">Total Commissions Paid</span>
                    <span class="text-2xl font-extrabold" style="color: var(--positive-500);">₱24.00 (16% of sale)</span>
                  </div>
                </div>
              </div>
              <div>
                <h3 class="text-2xl font-semibold text-indigo-800 mb-5" style="color: var(--primary-500);">Profit Distribution & Key Advantages</h3>
                <div class="rounded-lg p-6 border" style="background: var(--bg-card); border-color: var(--border-color);">
                  <div class="flex items-center justify-between mb-5 pb-3" style="border-bottom: 1px solid var(--border-color);">
                    <div class="flex items-center">
                      <div class="w-14 h-14 rounded-full flex items-center justify-center mr-4 shadow" style="background: var(--primary-600);">
                        <i class="fas fa-building text-2xl" style="color: #fff;"></i>
                      </div>
                      <div>
                        <h4 class="font-bold text-xl" style="style=color: var(--primary-500);">Company Net Profit</h4>
                        <p class="text-sm" style="color: var(--text-secondary);">After all payouts & operational costs</p>
                      </div>
                    </div>
                    <span class="text-3xl font-extrabold" style="color: var(--positive-500);">₱24</span>
                  </div>
                  <div class="space-y-4 text-lg">
                    <div class="flex justify-between items-center">
                      <span class="font-medium" style="color: var(--text-primary);">Ginto Service Cost</span>
                      <span class="font-bold" style="color: var(--text-primary);">₱150</span>
                    </div>
                    <div class="flex justify-between items-center">
                      <span class="font-medium" style="color: var(--text-primary);">Total Commission Payouts</span>
                      <span class="font-bold" style="color: #ff4d4f;">- ₱24</span>
                    </div>
                    <div class="flex justify-between items-center">
                      <span class="font-medium" style="color: var(--text-primary);">Operational Costs</span>
                      <span class="font-bold" style="color: #ff4d4f;">- ₱75</span>
                    </div>
                    <div class="pt-4 mt-4 flex justify-between items-center" style="border-top: 1px solid var(--border-color);">
                      <span class="font-bold text-xl" style="color: var(--primary-500);">Company Net Profit</span>
                      <span class="font-extrabold text-2xl" style="color: var(--positive-500);">₱24</span>
                    </div>
                  </div>
                </div>

                <h3 class="text-2xl font-semibold mt-10 mb-5" style="color: var(--primary-500);">Key Benefits of Commission</h3>
                <div class="space-y-4 rounded-lg p-6" style="background: linear-gradient(120deg, var(--bg-secondary) 80%, var(--primary-500) 100%);">
                  <div class="flex items-start">
                    <div class="flex-shrink-0 mt-1">
                      <div class="w-8 h-8 rounded-full flex items-center justify-center shadow-sm" style="background: var(--primary-600); color: #fff;">
                        <i class="fas fa-star text-sm"></i>
                      </div>
                    </div>
                    <p class="ml-3 text-lg" style="color: var(--text-primary);">
                      <strong>Maximize Your Direct Sales:</strong> You earn the highest 5% commission on sales directly attributed to your efforts (Level 8).
                    </p>
                  </div>
                  <div class="flex items-start">
                    <div class="flex-shrink-0 mt-1">
                      <div class="w-8 h-8 rounded-full flex items-center justify-center shadow-sm" style="background: var(--primary-600); color: #fff;">
                        <i class="fas fa-users text-sm"></i>
                      </div>
                    </div>
                    <p class="ml-3 text-lg" style="color: var(--text-primary);">
                      <strong>Fair Upline Rewards:</strong> Commissions decrease as you move up the upline (to your sponsor and their upline), ensuring the person closest to the sale gets the best cut.
                    </p>
                  </div>
                  <div class="flex items-start">
                    <div class="flex-shrink-0 mt-1">
                      <div class="w-8 h-8 rounded-full flex items-center justify-center shadow-sm" style="background: var(--primary-600); color: #fff;">
                        <i class="fas fa-sync-alt text-sm"></i>
                      </div>
                    </div>
                    <p class="ml-3 text-lg" style="color: var(--text-primary);">
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

  <!-- Wizard Section -->
  <section id="how-it-works" class="py-16 px-4" style="background: linear-gradient(120deg, var(--bg-secondary) 80%, var(--primary-500) 100%);">
    <div class="max-w-7xl mx-auto">
      <h2 class="text-4xl font-extrabold text-center mb-12" style="color: var(--text-primary);">Get Started in 4 Simple Steps</h2>
      
      <div class="wizard-container rounded-xl shadow-lg p-8 glow-card">
        
        <!-- Wizard Header -->
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
          <h3 class="text-2xl font-bold mb-6" style="color: var(--text-primary);">Select Your Membership Tier</h3>
          <p class="mb-8" style="color: var(--text-secondary);">Choose the tier that matches your goals. Higher tiers offer greater earning potential.</p>
          
          <!-- Core 3 Tiers -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="tier-card p-6 relative" data-tier-id="1" data-tier-name="Starter" data-tier-price="150.00">
              <div class="tier-badge">Basic</div>
              <h4 class="text-xl font-bold mb-2" style="color: var(--text-primary);">Starter</h4>
              <p class="mb-4" style="color: var(--text-secondary);">Perfect for beginners with basic training.</p>
              <div class="text-3xl font-extrabold mb-4" style="color: var(--primary-500);">₱150</div>
              <ul class="space-y-2 mb-6 text-sm" style="color: var(--text-secondary);">
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Access to Level 1-4 commissions</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Basic training materials</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Starter kit</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Basic Access to Ginto AI</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Motivational Dashboard</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Entry level AI tools</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Weekly PowerBuilder Tech Support</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Up to P120k daily potential take-off</li>
              </ul>
              <button type="button" class="w-full font-semibold py-2 px-4 rounded-lg transition-colors select-tier" style="background-color: var(--bg-secondary); color: var(--primary-500); border: 1px solid var(--primary-500);">
                Select Plan
              </button>
            </div>

            <div class="tier-card p-6 relative" data-tier-id="2" data-tier-name="Professional" data-tier-price="1000.00">
              <div class="tier-badge">Recommended</div>
              <h4 class="text-xl font-bold mb-2" style="color: var(--text-primary);">Professional</h4>
              <p class="mb-4" style="color: var(--text-secondary);">For serious earners with advanced training.</p>
              <div class="text-3xl font-extrabold mb-4" style="color: var(--primary-500);">₱1,000</div>
              <ul class="space-y-2 mb-6 text-sm" style="color: var(--text-secondary);">
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Access to Level 1-6 commissions</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Advanced training</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Marketing materials</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Pro AI tools</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Website Kit</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Motivational Dashboard</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Entry level AI tools</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Weekly PowerBuilder Tech Support</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Up to 5x daily than starter</li>
              </ul>
              <button type="button" class="w-full font-semibold py-2 px-4 rounded-lg transition-colors select-tier" style="background-color: var(--bg-secondary); color: var(--primary-500); border: 1px solid var(--primary-500);">
                Select Plan
              </button>
            </div>

            <div class="tier-card p-6 relative" data-tier-id="3" data-tier-name="Executive" data-tier-price="5000.00">
              <div class="tier-badge">Elite</div>
              <h4 class="text-xl font-bold mb-2" style="color: var(--text-primary);">Executive</h4>
              <p class="mb-4" style="color: var(--text-secondary);">Maximum earning potential with elite training.</p>
              <div class="text-3xl font-extrabold mb-4" style="color: var(--primary-500);">₱5,000</div>
              <ul class="space-y-2 mb-6 text-sm" style="color: var(--text-secondary);">
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Access to all 8 levels</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Elite training program</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Personal mentor</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> VIP Agentic Support</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Free Website on Profile</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Motivational Dashboard</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Entry level AI tools</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Weekly PowerBuilder Tech Support</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i>10x profissional take-off</li>
              </ul>
              <button type="button" class="w-full font-semibold py-2 px-4 rounded-lg transition-colors select-tier" style="background-color: var(--bg-secondary); color: var(--primary-500); border: 1px solid var(--primary-500);">
                Select Plan
              </button>
            </div>
          </div>

          <!-- Premium Tiers -->
          <div class="pt-8 mb-8" style="border-top: 1px solid var(--border-color);">
            <h4 class="text-xl font-bold mb-6 text-center" style="color: var(--text-primary);">Premium Packages</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="tier-card p-6 relative" data-tier-id="4" data-tier-name="Gold" data-tier-price="10000.00">
                <div class="tier-badge" style="background: linear-gradient(135deg, var(--accent-500), var(--accent-600));">Gold</div>
                <h4 class="text-xl font-bold mb-2" style="color: var(--text-primary);">Gold</h4>
                <p class="mb-4" style="color: var(--text-secondary);">Advanced package for serious earners.</p>
                <div class="text-3xl font-extrabold mb-4" style="color: var(--primary-500);">₱10,000</div>
                <ul class="space-y-2 mb-6 text-sm" style="color: var(--text-secondary);">
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Access to all 8 levels</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Elite marketing kit</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Dedicated support</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Premium AI tools</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Custom backend</li>
                </ul>
                <button type="button" class="w-full font-semibold py-2 px-4 rounded-lg transition-colors select-tier" style="background-color: var(--bg-secondary); color: var(--primary-500); border: 1px solid var(--primary-500);">
                  Select Plan
                </button>
              </div>

              <div class="tier-card p-6 relative" data-tier-id="5" data-tier-name="Platinum" data-tier-price="50000.00">
                <div class="tier-badge" style="background: linear-gradient(135deg, #E5E4E2, #B0C4DE);">Platinum</div>
                <h4 class="text-xl font-bold mb-2" style="color: var(--text-primary);">Platinum</h4>
                <p class="mb-4" style="color: var(--text-secondary);">Top-tier package with VIP treatment.</p>
                <div class="text-3xl font-extrabold mb-4" style="color: var(--primary-500);">₱50,000</div>
                <ul class="space-y-2 mb-6 text-sm" style="color: var(--text-secondary);">
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Personal onboarding</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> VIP events & mentorship</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Marketing concierge</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Full AI suite access</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Free Ginto subdomain</li>
                </ul>
                <button type="button" class="w-full font-semibold py-2 px-4 rounded-lg transition-colors select-tier" style="background-color: var(--bg-secondary); color: var(--primary-500); border: 1px solid var(--primary-500);">
                  Select Plan
                </button>
              </div>
            </div>
          </div>

          <div id="next-step-container" class="mt-8 flex justify-end">
            <button type="button" id="next-personal-info-btn" class="btn-gold font-semibold py-3 px-6 rounded-lg next-step">
              Next: Personal Info <i class="fas fa-arrow-right ml-2"></i>
            </button>
          </div>
        </div>

        <!-- Step 2: Personal Info -->
        <div class="wizard-content" data-step="2">
          <h3 class="text-2xl font-bold mb-6" style="color: var(--text-primary);">Personal Information</h3>
          <p class="mb-8" style="color: var(--text-secondary);">Please provide your details to create your account.</p>
          <form id="wizardRegisterForm" action="/register" method="POST" enctype="multipart/form-data" class="space-y-5">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <!-- Hidden fields for package/payment, set by wizard JS -->
            <input type="hidden" name="package" id="selectedPackage" value="Gold">
            <input type="hidden" name="package_amount" id="selectedPackageAmount" value="10000">
            <input type="hidden" name="package_currency" id="selectedPackageCurrency" value="PHP">
            <input type="hidden" name="pay_method" id="selectedPayMethod" value="btcpay">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
              <div>
                <label class="block font-medium mb-2" style="color: var(--text-primary);">Full Name</label>
                <input type="text" name="fullname" class="w-full px-4 py-3 rounded-lg" required>
              </div>
              <div>
                <label class="block font-medium mb-2" style="color: var(--text-primary);">Username</label>
                <input type="text" name="username" class="w-full px-4 py-3 rounded-lg" required>
              </div>
              <div>
                <label class="block font-medium mb-2" style="color: var(--text-primary);">Email Address</label>
                <input type="email" name="email" class="w-full px-4 py-3 rounded-lg" required>
              </div>
              <div>
                <label class="block font-medium mb-2" style="color: var(--text-primary);">Country</label>
                <select name="country" id="countrySelect" class="w-full px-4 py-3 rounded-lg bg-white" required>
                  <option value="" disabled selected>Select your country</option>
                  <?php if (!empty($countries) && is_array($countries)): ?>
                    <?php foreach ($countries as $code => $c): ?>
                      <option value="<?= $code ?>">
                        <?= htmlspecialchars($c['name']) ?><?= $c['dial_code'] ? ' (' . $c['dial_code'] . ')' : '' ?>
                      </option>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </select>
                <style>
                  /* Theme-adaptive select for country */
                  #countrySelect {
                    background-color: var(--bg-card);
                    color: var(--text-primary);
                  }
                  #countrySelect:focus {
                    border-color: var(--primary-500);
                    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
                  }
                  #countrySelect option {
                    background: var(--bg-card);
                    color: var(--text-primary);
                  }
                </style>
              </div>
              <div>
                <label class="block font-medium mb-2" style="color: var(--text-primary);">Phone Number</label>
                <input type="text" name="phone" id="phoneInput" class="w-full px-4 py-3 rounded-lg" required pattern="[0-9]*" inputmode="numeric">
              </div>
              <div>
                <label class="block font-medium mb-2" style="color: var(--text-primary);">Password</label>
                <input type="password" name="password" id="password" class="w-full px-4 py-3 rounded-lg pr-12" required>
              </div>
              <div>
                <label class="block font-medium mb-2" style="color: var(--text-primary);">Confirm Password</label>
                <input type="password" name="password_confirm" class="w-full px-4 py-3 rounded-lg" required>
              </div>
            </div>
          </form>

          <div class="mt-8 flex justify-between">
            <button type="button" class="font-semibold py-3 px-6 rounded-lg prev-step" style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
              <i class="fas fa-arrow-left mr-2"></i> Back
            </button>
            <button type="button" class="btn-gold font-semibold py-3 px-6 rounded-lg next-step" id="wizard-next-payment">
              Next: Payment <i class="fas fa-arrow-right ml-2"></i>
            </button>
          </div>
        </div>

        <!-- Step 3: Payment -->
        <div class="wizard-content" data-step="3">
          <h3 class="text-2xl font-bold mb-6" style="color: var(--text-primary);">Payment Information</h3>
          <p class="mb-8" style="color: var(--text-secondary);">Complete your membership purchase.</p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
              <h4 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Order Summary</h4>
              <div class="p-6 rounded-lg" style="background-color: var(--bg-secondary);">
                <div class="flex justify-between items-center mb-4 pb-4" style="border-bottom: 1px solid var(--border-color);">
                  <span class="font-medium" style="color: var(--text-secondary);">Membership Tier:</span>
                  <span class="font-bold" id="selected-tier" style="color: var(--text-primary);">Professional</span>
                </div>
                <div class="flex justify-between items-center mb-4 pb-4" style="border-bottom: 1px solid var(--border-color);">
                  <span class="font-medium" style="color: var(--text-secondary);">Price:</span>
                  <span class="font-bold" id="payment-step-price" style="color: var(--text-primary);">₱1,000</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-lg font-bold" style="color: var(--text-primary);">Total:</span>
                  <span class="text-xl font-extrabold" id="payment-total" style="color: var(--primary-500);">₱1,000</span>
                </div>
              </div>
            </div>
            <div>
              <h4 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">Payment Method</h4>
              <div class="space-y-4">
                <div class="flex items-center p-4 rounded-lg cursor-pointer tier-card payment-method-container" data-radio="paypal">
                  <input type="radio" name="payment_method" id="paypal" value="paypal" class="h-5 w-5" style="accent-color: var(--primary-500);" checked>
                  <label for="paypal" class="ml-3 flex items-center gap-2 font-medium" style="color: var(--text-primary);">
                    <img src="https://www.paypalobjects.com/webstatic/mktg/Logo/pp-logo-100px.png" alt="PayPal" class="h-5">
                    PayPal
                  </label>
                </div>
                <div class="flex items-center p-4 rounded-lg cursor-pointer tier-card payment-method-container" data-radio="credit-card">
                  <input type="radio" name="payment_method" id="credit-card" value="credit_card" class="h-5 w-5" style="accent-color: var(--primary-500);">
                  <label for="credit-card" class="ml-3 flex items-center gap-2 font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-credit-card text-lg" style="color: var(--primary-500);"></i>
                    Credit/Debit Card
                  </label>
                </div>
                <div class="flex items-center p-4 rounded-lg cursor-pointer tier-card payment-method-container" data-radio="gcash">
                  <input type="radio" name="payment_method" id="gcash" value="gcash" class="h-5 w-5" style="accent-color: var(--primary-500);">
                  <label for="gcash" class="ml-3 block font-medium" style="color: var(--text-primary);">
                    <span class="inline-flex items-center gap-2">
                      <img src="https://www.gcash.com/wp-content/uploads/2019/04/gcash-logo.png" alt="GCash" class="h-5" onerror="this.style.display='none'">
                      GCash
                    </span>
                  </label>
                </div>
                <div class="flex items-center p-4 rounded-lg cursor-pointer tier-card payment-method-container" data-radio="bank-transfer">
                  <input type="radio" name="payment_method" id="bank-transfer" value="bank_transfer" class="h-5 w-5" style="accent-color: var(--primary-500);">
                  <label for="bank-transfer" class="ml-3 flex items-center gap-2 font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-university text-lg" style="color: var(--primary-500);"></i>
                    Bank Transfer / Deposit
                  </label>
                </div>
                <div class="flex items-center p-4 rounded-lg cursor-pointer tier-card payment-method-container" data-radio="crypto-usdt">
                  <input type="radio" name="payment_method" id="crypto-usdt" value="crypto_usdt_bep20" class="h-5 w-5" style="accent-color: var(--primary-500);">
                  <label for="crypto-usdt" class="ml-3 flex items-center gap-2 font-medium" style="color: var(--text-primary);">
                    <i class="fab fa-bitcoin text-lg" style="color: #f0b90b;"></i>
                    <span>Crypto <span class="text-xs px-1.5 py-0.5 rounded font-bold" style="background: linear-gradient(135deg, #f0b90b 0%, #d4a00a 100%); color: #000;">USDT BEP20</span></span>
                  </label>
                </div>
              </div>
              
              <!-- PayPal Button Container (PayPal balance only) -->
              <div id="paypal-button-container" class="mt-6 hidden"></div>
              
              <!-- Credit Card Button Container (Card funding only) -->
              <div id="card-button-container" class="mt-6 hidden"></div>
              
              <div id="paypal-loading" class="mt-6 hidden text-center py-4">
                <div class="animate-spin w-6 h-6 border-4 border-blue-500 border-t-transparent rounded-full mx-auto mb-2"></div>
                <p style="color: var(--text-secondary);">Loading payment options...</p>
              </div>
              <div id="paypal-error" class="mt-4 hidden bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 p-3 rounded-lg text-sm">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span id="paypal-error-message">Payment failed. Please try again.</span>
              </div>
              
              <!-- GCash Payment Details -->
              <div id="gcash-details" class="mt-6 hidden">
                <div class="p-5 rounded-lg" style="background: linear-gradient(135deg, #007dfe 0%, #0056b3 100%); color: white;">
                  <h5 class="font-bold text-lg mb-3 flex items-center gap-2">
                    <i class="fas fa-mobile-alt"></i> GCash Payment Instructions
                  </h5>
                  <p class="text-sm mb-4 opacity-90">Please send the exact amount to one of the following GCash accounts:</p>
                  
                  <div class="space-y-3">
                    <div class="bg-white/20 backdrop-blur rounded-lg p-3">
                      <div class="flex justify-between items-center">
                        <div>
                          <p class="font-bold text-white">09614404313</p>
                          <p class="text-sm opacity-90">Oliver Bob R. Lagumen</p>
                        </div>
                        <button type="button" onclick="copyToClipboard('09614404313')" class="bg-white/30 hover:bg-white/40 px-3 py-1 rounded text-sm font-medium transition">
                          <i class="fas fa-copy mr-1"></i> Copy
                        </button>
                      </div>
                    </div>
                    
                    <!-- <div class="bg-white/20 backdrop-blur rounded-lg p-3">
                      <div class="flex justify-between items-center">
                        <div>
                          <p class="font-bold text-white">09617128368</p>
                          <p class="text-sm opacity-90">Eleanor B. Rojas</p>
                        </div>
                        <button type="button" onclick="copyToClipboard('09617128368')" class="bg-white/30 hover:bg-white/40 px-3 py-1 rounded text-sm font-medium transition">
                          <i class="fas fa-copy mr-1"></i> Copy
                        </button>
                      </div>
                    </div> -->
                  </div>
                  
                  <div class="mt-4 p-3 bg-yellow-400/20 rounded-lg">
                    <p class="text-sm">
                      <i class="fas fa-info-circle mr-1"></i>
                      <strong>Important:</strong> After sending, please upload a screenshot of your GCash receipt below for verification.
                    </p>
                  </div>
                </div>
                
                <!-- GCash Receipt Upload -->
                <div class="mt-3 p-3 rounded-lg border-2 border-dashed" style="border-color: #007dfe; background-color: var(--bg-secondary);">
                  <div class="text-center">
                    <i class="fas fa-cloud-upload-alt text-2xl mb-2" style="color: #007dfe;"></i>
                    <h6 class="font-semibold text-sm mb-1" style="color: var(--text-primary);">Upload GCash Receipt <span class="text-red-500">*</span></h6>
                    <p class="text-xs mb-2" style="color: var(--text-secondary);">
                      Screenshot of your GCash payment confirmation
                    </p>
                    <?php $inputType = 'gcash_receipt'; include __DIR__ . '/parts/input/input.php'; ?>
                    <label for="gcash_receipt" class="inline-flex items-center gap-2 px-3 py-1.5 rounded cursor-pointer font-medium text-sm transition" style="background: #007dfe; color: white;">
                      <i class="fas fa-upload"></i> Choose File
                    </label>
                    <p id="gcash_receipt_filename" class="mt-2 text-xs hidden" style="color: var(--text-primary);"></p>
                  </div>
                </div>
                
                <!-- GCash Reference Input -->
                <div class="mt-4">
                  <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                    GCash Reference Number <span class="text-red-500">*</span>
                  </label>
                  <input type="text" name="gcash_reference" id="gcash_reference" 
                    class="w-full p-3 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                    placeholder="Enter your GCash reference number">
                  <p class="text-xs mt-1" style="color: var(--text-secondary);">Enter the reference number from your GCash payment receipt</p>
                </div>
                
                <!-- Confirm GCash Payment Button -->
                <button type="button" id="confirm-gcash-payment" class="w-full mt-4 font-semibold py-3 px-6 rounded-lg flex items-center justify-center gap-2" style="background: linear-gradient(90deg, #007dfe 0%, #0056b3 100%); color: white;">
                  <i class="fas fa-check-circle"></i> Confirm GCash Payment
                </button>
              </div>
              
              <!-- Bank Transfer Payment Details -->
              <div id="bank-transfer-details" class="mt-4 hidden">
                <div class="p-3 rounded-lg" style="background: linear-gradient(135deg, #1a365d 0%, #2c5282 100%); color: white;">
                  <h5 class="font-semibold text-base mb-2 flex items-center gap-2">
                    <i class="fas fa-university"></i> Bank Transfer / Deposit Instructions
                  </h5>
                  
                  <div class="bg-yellow-400/20 rounded p-2 mb-3">
                    <p class="text-xs flex items-start gap-2">
                      <i class="fas fa-exclamation-circle text-yellow-300 mt-0.5"></i>
                      <span><strong>Important:</strong> Make your bank transfer first, then upload the receipt below.</span>
                    </p>
                  </div>
                  
                  <div class="space-y-2">
                    <div class="bg-white/10 backdrop-blur rounded p-2">
                      <p class="text-xs opacity-80">Bank Name</p>
                      <p class="font-semibold">Asia United Bank (AUB)</p>
                    </div>
                    
                    <div class="bg-white/10 backdrop-blur rounded p-2">
                      <p class="text-xs opacity-80">Account Name</p>
                      <p class="font-semibold">AI HQ CORP.</p>
                    </div>
                    
                    <div class="bg-white/10 backdrop-blur rounded p-2">
                      <div class="flex justify-between items-center">
                        <div>
                          <p class="text-xs opacity-80">Account Number</p>
                          <p class="font-semibold">302-01-000786-1</p>
                        </div>
                        <button type="button" onclick="copyToClipboard('302-01-000786-1')" class="bg-white/20 hover:bg-white/30 px-2 py-1 rounded text-xs font-medium transition">
                          <i class="fas fa-copy mr-1"></i> Copy
                        </button>
                      </div>
                    </div>
                    
                    <!-- Bank Codes (collapsible) -->
                    <div class="bg-white/10 backdrop-blur rounded p-2">
                      <button type="button" onclick="toggleBankCodes()" class="w-full flex justify-between items-center text-left">
                        <span class="text-xs opacity-80">Bank Codes (for international transfers)</span>
                        <i id="bank-codes-icon" class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
                      </button>
                      <div id="bank-codes-content" class="hidden mt-2 space-y-1 pt-2 border-t border-white/20">
                        <div class="flex justify-between items-center">
                          <div>
                            <p class="text-xs opacity-70">SWIFT Code (Full)</p>
                            <p class="font-mono text-xs">AUBKPHMMXXX</p>
                          </div>
                          <button type="button" onclick="copyToClipboard('AUBKPHMMXXX')" class="bg-white/20 hover:bg-white/30 px-2 py-0.5 rounded text-xs transition">
                            <i class="fas fa-copy"></i>
                          </button>
                        </div>
                        <div class="flex justify-between items-center">
                          <div>
                            <p class="text-xs opacity-70">SWIFT Code (8-char)</p>
                            <p class="font-mono text-xs">AUBKPHMM</p>
                          </div>
                          <button type="button" onclick="copyToClipboard('AUBKPHMM')" class="bg-white/20 hover:bg-white/30 px-2 py-0.5 rounded text-xs transition">
                            <i class="fas fa-copy"></i>
                          </button>
                        </div>
                        <div class="flex justify-between items-center">
                          <div>
                            <p class="text-xs opacity-70">AUB Bank Code</p>
                            <p class="font-mono text-xs">011020011</p>
                          </div>
                          <button type="button" onclick="copyToClipboard('011020011')" class="bg-white/20 hover:bg-white/30 px-2 py-0.5 rounded text-xs transition">
                            <i class="fas fa-copy"></i>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- Bank Receipt Upload -->
                <div class="mt-3 p-3 rounded-lg border-2 border-dashed" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                  <div class="text-center">
                    <i class="fas fa-cloud-upload-alt text-2xl mb-2" style="color: var(--primary-500);"></i>
                    <h6 class="font-semibold text-sm mb-1" style="color: var(--text-primary);">Upload Transaction Receipt</h6>
                    <p class="text-xs mb-2" style="color: var(--text-secondary);">
                      Screenshot or photo of your deposit slip / transfer confirmation
                    </p>
                    <?php $inputType = 'bank_receipt'; include __DIR__ . '/register/parts/input/input.php'; ?>
                    <label for="bank_receipt" class="inline-flex items-center gap-2 px-3 py-1.5 rounded cursor-pointer font-medium text-sm transition" style="background: var(--primary-500); color: white;">
                      <i class="fas fa-upload"></i> Choose File
                    </label>
                    <p id="bank_receipt_filename" class="mt-2 text-xs hidden" style="color: var(--text-primary);"></p>
                  </div>
                </div>
                
                <!-- Bank Reference Input -->
                <div class="mt-3">
                  <label class="block text-xs font-medium mb-1" style="color: var(--text-primary);">
                    Transaction Reference / Deposit Slip Number
                  </label>
                  <input type="text" name="bank_reference" id="bank_reference" 
                    class="w-full p-3 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                    placeholder="Enter reference number if available">
                </div>
                
                <!-- Confirm Bank Payment Button -->
                <button type="button" id="confirm-bank-payment" class="w-full mt-4 btn-gold font-semibold py-3 px-6 rounded-lg flex items-center justify-center gap-2">
                  <i class="fas fa-check-circle"></i> Confirm Bank Payment
                </button>
              </div>
              
              <!-- Crypto USDT BEP20 Payment Details -->
              <div id="crypto-usdt-details" class="mt-4 hidden">
                <div class="p-4 rounded-lg" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: white; border: 2px solid #f0b90b;">
                  <h5 class="font-semibold text-base mb-3 flex items-center justify-center gap-2">
                    <i class="fab fa-bitcoin" style="color: #f0b90b;"></i> 
                    <span>USDT Payment</span>
                    <span class="text-xs px-2 py-0.5 rounded font-bold" style="background: #f0b90b; color: #000;">BNB Smart Chain (BEP20)</span>
                  </h5>
                  
                  <div class="bg-red-500/30 rounded p-2 mb-4 border border-red-400">
                    <p class="text-xs text-center">
                      <i class="fas fa-exclamation-triangle text-red-300 mr-1"></i>
                      <strong>CRITICAL:</strong> Only send <strong>USDT</strong> via <strong>BNB Smart Chain (BEP20)</strong>. Other networks = <strong>permanent loss!</strong>
                    </p>
                  </div>
                  
                  <!-- Loading State -->
                  <div id="crypto-loading" class="text-center py-6">
                    <div class="animate-spin w-8 h-8 border-4 border-yellow-500 border-t-transparent rounded-full mx-auto mb-3"></div>
                    <p class="text-sm opacity-80">Loading payment details...</p>
                  </div>
                  
                  <!-- Crypto Info (loaded via AJAX) -->
                  <div id="crypto-info-container" class="hidden">
                    <!-- QR Code - Centered -->
                    <div class="flex justify-center mb-4">
                      <div class="bg-white p-3 rounded-lg shadow-lg">
                        <img id="crypto-qr-image" src="" alt="USDT BEP20 QR Code" class="w-36 h-36 object-contain">
                      </div>
                    </div>
                    
                    <!-- Network & Token Row -->
                    <div class="grid grid-cols-2 gap-2 mb-3">
                      <div class="bg-white/10 backdrop-blur rounded p-2 text-center">
                        <p class="text-xs opacity-70 mb-0.5">Network</p>
                        <p class="font-semibold text-sm text-yellow-400" id="crypto-network">BNB Smart Chain (BEP20)</p>
                      </div>
                      <div class="bg-white/10 backdrop-blur rounded p-2 text-center">
                        <p class="text-xs opacity-70 mb-0.5">Token</p>
                        <p class="font-semibold text-sm text-yellow-400">USDT (Tether)</p>
                      </div>
                    </div>
                    
                    <!-- Wallet Address - Full Width -->
                    <div class="bg-white/10 backdrop-blur rounded p-3 mb-3">
                      <div class="flex items-center justify-between mb-1">
                        <p class="text-xs opacity-70">Wallet Address</p>
                        <button type="button" onclick="copyCryptoAddress()" class="bg-yellow-500 hover:bg-yellow-400 px-3 py-1 rounded text-xs font-bold transition" style="color: #000;">
                          <i class="fas fa-copy mr-1"></i> Copy
                        </button>
                      </div>
                      <p id="crypto-address" class="font-mono text-sm break-all text-yellow-400 select-all"></p>
                    </div>
                    
                    <!-- Verification Link -->
                    <div class="text-center">
                      <a id="crypto-verify-link" href="#" target="_blank" class="inline-flex items-center gap-1 text-xs text-blue-300 hover:text-blue-100 underline">
                        <i class="fas fa-external-link-alt"></i> Verify transactions on BscScan
                      </a>
                    </div>
                  </div>
                  
                  <div id="crypto-error" class="hidden bg-red-500/30 rounded p-3 text-center">
                    <i class="fas fa-exclamation-circle text-red-300 mb-2"></i>
                    <p class="text-sm">Failed to load payment details. Please refresh the page.</p>
                  </div>
                </div>
                
                <!-- Crypto Receipt Upload (optional but recommended) -->
                <div class="mt-3 p-3 rounded-lg border-2 border-dashed" style="border-color: #f0b90b; background-color: var(--bg-secondary);">
                  <div class="text-center">
                    <i class="fas fa-cloud-upload-alt text-2xl mb-2" style="color: #f0b90b;"></i>
                    <h6 class="font-semibold text-sm mb-1" style="color: var(--text-primary);">Upload Transaction Screenshot (Optional)</h6>
                    <p class="text-xs mb-2" style="color: var(--text-secondary);">
                      Screenshot of your wallet showing the completed transaction
                    </p>
                    <?php $inputType = 'crypto_receipt'; include __DIR__ . '/register/parts/input/input.php'; ?>
                    <label for="crypto_receipt" class="inline-flex items-center gap-2 px-3 py-1.5 rounded cursor-pointer font-medium text-sm transition" style="background: #f0b90b; color: #000;">
                      <i class="fas fa-upload"></i> Choose File
                    </label>
                    <p id="crypto_receipt_filename" class="mt-2 text-xs hidden" style="color: var(--text-primary);"></p>
                  </div>
                </div>
                
                <!-- Transaction Hash Input -->
                <div class="mt-3">
                  <label class="block text-xs font-medium mb-1" style="color: var(--text-primary);">
                    Transaction Hash (TxHash) <span class="text-red-500">*</span>
                  </label>
                  <input type="text" name="crypto_txhash" id="crypto_txhash" 
                    class="w-full p-3 rounded-lg border focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 font-mono text-sm"
                    style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);"
                    placeholder="0x..." pattern="^0x[a-fA-F0-9]{64}$">
                  <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    The 66-character transaction hash starting with 0x from your wallet
                  </p>
                </div>
                
                <!-- Confirm Crypto Payment Button -->
                <button type="button" id="confirm-crypto-payment" class="w-full mt-4 font-semibold py-3 px-6 rounded-lg flex items-center justify-center gap-2" style="background: linear-gradient(90deg, #f0b90b 0%, #d4a00a 100%); color: #000;">
                  <i class="fas fa-check-circle"></i> Confirm USDT Payment
                </button>
              </div>
            </div>
          </div>
          <div class="mt-8 flex justify-between">
            <button type="button" class="font-semibold py-3 px-6 rounded-lg prev-step" style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
              <i class="fas fa-arrow-left mr-2"></i> Back
            </button>
            <button type="submit" form="wizardRegisterForm" class="btn-gold font-semibold py-3 px-6 rounded-lg" id="complete-purchase">
              Complete Registration <i class="fas fa-check ml-2"></i>
            </button>
          </div>
        </div>

      </div>
    </div>
  </section>

  <script>
    (function() {
      'use strict';

      // Theme Toggle
      const themeToggle = document.getElementById('themeToggle');
      const html = document.documentElement;
      const body = document.body;
      const icon = themeToggle.querySelector('i');
      
      // Check for saved theme preference
      const savedTheme = localStorage.getItem('theme') || 'light';
      setTheme(savedTheme);

      function setTheme(theme) {
        body.className = theme;
        if (theme === 'dark') {
          icon.className = 'fas fa-sun';
        } else {
          icon.className = 'fas fa-moon';
        }
        localStorage.setItem('theme', theme);
      }

      themeToggle.addEventListener('click', () => {
        const currentTheme = body.className;
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        setTheme(newTheme);
      });

      // Modal System
            // Country Auto-Detect (like legacy)
            const countrySelect = document.getElementById('countrySelect');
            if (countrySelect && !countrySelect.value) {
              // Try multiple geolocation services for reliability
              const geoServices = [
                'https://ipapi.co/country_code/',
                'https://api.country.is/'
              ];
              
              fetch(geoServices[0])
                .then(res => res.text())
                .then(code => {
                  code = code.trim().toUpperCase();
                  const option = countrySelect.querySelector(`option[value="${code}"]`);
                  if (option) {
                    countrySelect.value = code;
                    console.log('Country auto-detected:', code);
                  }
                })
                .catch(err => {
                  console.warn('GeoIP lookup failed, trying fallback:', err);
                  // Fallback to second service
                  fetch(geoServices[1])
                    .then(res => res.json())
                    .then(data => {
                      const code = data.country?.toUpperCase();
                      if (code) {
                        const option = countrySelect.querySelector(`option[value="${code}"]`);
                        if (option) {
                          countrySelect.value = code;
                          console.log('Country auto-detected (fallback):', code);
                        }
                      }
                    })
                    .catch(e => console.warn('All GeoIP lookups failed:', e));
                });
            }

            // Numeric-only phone input (like legacy)
            const phoneInput = document.getElementById('phoneInput');
            if (phoneInput) {
              phoneInput.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '');
              });
            }
      // Make showModal globally accessible for PayPal callbacks
      // Optional callback parameter: called when user clicks "Got it"
      window.showModal = function(title, message, icon = 'fas fa-exclamation-circle', iconColor = 'text-yellow-500', onClose = null) {
        const existing = document.getElementById('modal');
        if (existing) existing.remove();

        const modal = document.createElement('div');
        modal.id = 'modal';
        modal.className = 'fixed inset-0 z-[99999] flex items-center justify-center bg-black bg-opacity-50';
        modal.innerHTML = `
          <div class="rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6 animate-fade-in" style="background-color: var(--bg-card);">
            <div class="flex items-start space-x-4 mb-6">
              <div class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0" style="background: linear-gradient(135deg, var(--primary-500), var(--accent-500));">
                <i class="${icon} text-2xl" style="color: var(--text-primary);"></i>
              </div>
              <div>
                <h3 class="text-xl font-bold mb-2" style="color: var(--text-primary);">${title}</h3>
                <p style="color: var(--text-secondary);">${message}</p>
              </div>
            </div>
            <div class="flex justify-end">
              <button id="modal-close-btn" class="btn-gold font-semibold px-6 py-2 rounded-lg">
                Got it
              </button>
            </div>
          </div>
        `;
        document.body.appendChild(modal);
        
        // Handle close button click
        var closeBtn = modal.querySelector('#modal-close-btn');
        closeBtn.addEventListener('click', function() {
          modal.remove();
          if (typeof onClose === 'function') {
            onClose();
          }
        });
        
        // Handle background click (just close, no callback)
        modal.addEventListener('click', function(e) { 
          if (e.target === modal) modal.remove(); 
        });
      }

      // Wizard State
      let selectedTier = { id: null, name: null, price: null };
      let wizardInitialized = false;

      // Navigation (globally accessible for PayPal integration)
      window.goToStep = function(step) {
        document.querySelectorAll('.wizard-step').forEach((s, i) => {
          s.classList.remove('active', 'completed');
          if (i + 1 < step) s.classList.add('completed');
          else if (i + 1 === step) s.classList.add('active');
        });
        document.querySelectorAll('.wizard-content').forEach(c => {
          c.classList.toggle('active', parseInt(c.dataset.step) === step);
        });
        // Only scroll after initial page load
        if (wizardInitialized) {
          document.querySelector('.wizard-container').scrollIntoView({ behavior: 'smooth' });
        }
        wizardInitialized = true;
        
        // Trigger appropriate payment section when reaching step 3
        if (step === 3) {
          var paymentMethod = document.querySelector('input[name="payment_method"]:checked');
          if (paymentMethod) {
            if (paymentMethod.value === 'paypal' || paymentMethod.value === 'credit_card') {
              if (typeof showPayPalButton === 'function') {
                showPayPalButton();
                document.getElementById('complete-purchase').classList.add('hidden');
              }
            } else if (paymentMethod.value === 'gcash') {
              document.getElementById('gcash-details').classList.remove('hidden');
              document.getElementById('complete-purchase').classList.remove('hidden');
            }
          }
        }
      }

      // Tier Selection - make entire card clickable
      document.querySelectorAll('.tier-card').forEach(card => {
        // Skip payment method containers
        if (card.classList.contains('payment-method-container')) return;
        
        card.style.cursor = 'pointer';
        card.addEventListener('click', function(e) {
          // Remove selected state from all tier cards (not payment methods)
          document.querySelectorAll('.tier-card:not(.payment-method-container)').forEach(c => {
            c.classList.remove('selected');
          });
          // Add selected state to clicked card
          card.classList.add('selected');
          selectedTier.id = card.dataset.tierId;
          selectedTier.name = card.dataset.tierName;
          selectedTier.price = card.dataset.tierPrice;
          
          // Update hidden form fields for registration
          document.getElementById('selectedPackage').value = selectedTier.name;
          document.getElementById('selectedPackageAmount').value = selectedTier.price;
          document.getElementById('selectedPackageCurrency').value = 'PHP';
          
          // Scroll down to the Next button after selecting a tier
          setTimeout(function() {
            var nextBtn = document.getElementById('next-step-container');
            var nextBtnElement = document.getElementById('next-personal-info-btn');
            if (nextBtn) {
              nextBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            // Add pulsing animation to draw attention
            if (nextBtnElement) {
              nextBtnElement.classList.add('pulse-attention');
            }
          }, 150);
        });
      });

      // Next Buttons
      document.querySelectorAll('.next-step').forEach(btn => {
        btn.addEventListener('click', function() {
          const step = parseInt(this.closest('.wizard-content').dataset.step);
          
          if (step === 1) {
            if (!selectedTier.id) {
              showModal('Please Select a Tier', 'Choose a membership tier before proceeding.', 'fas fa-hand-pointer');
              return;
            }
            document.getElementById('selected-tier').textContent = selectedTier.name;
            document.getElementById('payment-step-price').textContent = '₱' + parseFloat(selectedTier.price).toLocaleString();
            document.getElementById('payment-total').textContent = '₱' + parseFloat(selectedTier.price).toLocaleString();
          }
          
          if (step === 2) {
            const inputs = document.querySelectorAll('.wizard-content[data-step="2"] input');
            let valid = true;
            inputs.forEach(input => {
              if (!input.value.trim()) {
                valid = false;
                input.style.borderColor = '#ef4444';
              } else {
                input.style.borderColor = 'var(--border-color)';
              }
            });
            
            if (!valid) {
              showModal('Missing Information', 'Please fill in all required fields.', 'fas fa-edit');
              return;
            }
            
            const passwords = document.querySelectorAll('.wizard-content[data-step="2"] input[type="password"]');
            if (passwords[0].value !== passwords[1].value) {
              showModal('Password Mismatch', 'The passwords do not match.', 'fas fa-lock');
              return;
            }
          }
          
          goToStep(step + 1);
        });
      });

      // Previous Buttons
      document.querySelectorAll('.prev-step').forEach(btn => {
        btn.addEventListener('click', function() {
          const step = parseInt(this.closest('.wizard-content').dataset.step);
          goToStep(step - 1);
        });
      });

      // Make payment method container clickable
      document.querySelectorAll('.payment-method-container').forEach(function(container) {
        container.addEventListener('click', function(e) {
          // Only trigger if not clicking the radio directly
          if (e.target.tagName.toLowerCase() !== 'input') {
            var radio = container.querySelector('input[type="radio"]');
            if (radio) {
              radio.checked = true;
              // Dispatch change event to trigger the payment method handler
              radio.dispatchEvent(new Event('change', { bubbles: true }));
            }
          }
        });
      });

      // Complete Purchase: validate and submit real form (AJAX with error popup)
      document.getElementById('complete-purchase').addEventListener('click', function(e) {
        const payment = document.querySelector('input[name="payment_method"]:checked');
        if (!payment) {
          e.preventDefault();
          showModal('Payment Method Required', 'Please select a payment method.', 'fas fa-credit-card');
          return;
        }
        document.getElementById('selectedPayMethod').value = payment.value;
        e.preventDefault();
        const form = document.getElementById('wizardRegisterForm');
        const formData = new FormData(form);
        fetch('/register', {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: formData
        })
        .then(async response => {
          const data = await response.json();
          if (response.ok && data.success) {
            showModal('Registration Successful', data.message || 'You have registered successfully!', 'fas fa-check-circle', 'text-green-500');
            setTimeout(() => { window.location.href = '/login'; }, 2000);
          } else {
            showModal('Registration Error', data.message || 'Registration failed. Please check your details and try again.', 'fas fa-exclamation-circle', 'text-yellow-500');
          }
        })
        .catch(() => {
          showModal('Network Error', 'Could not connect to server. Please try again later.', 'fas fa-exclamation-triangle', 'text-red-500');
        });
      });

      // Initialize
      window.goToStep(1);
    })();
  </script>

<!-- PayPal SDK for one-time payments -->
<?php 
$paypalEnv = $_ENV['PAYPAL_ENVIRONMENT'] ?? getenv('PAYPAL_ENVIRONMENT') ?? 'sandbox';
$paypalClientId = $paypalEnv === 'sandbox' 
  ? ($_ENV['PAYPAL_CLIENT_ID_SANDBOX'] ?? getenv('PAYPAL_CLIENT_ID_SANDBOX') ?? '')
  : ($_ENV['PAYPAL_CLIENT_ID'] ?? getenv('PAYPAL_CLIENT_ID') ?? '');
$paypalClientId = preg_replace('/\s+/', '', $paypalClientId);

// Fallback to 'test' if no client ID is set (will show error but won't crash)
if (empty($paypalClientId)) {
    $paypalClientId = 'test';
}
?>

<!-- PayPal SDK is already loaded in the head, just use it -->
<script>
(function() {
  'use strict';
  
  var paypalButtonsRendered = false;
  var currentTierPrice = 1000;
  var currentTierName = 'Basic';
  
  function updatePayPalAmount(tierName, price) {
    currentTierPrice = parseFloat(price);
    currentTierName = tierName;
  }
  
  function getSelectedTier() {
    var tierCard = document.querySelector('.tier-card.selected');
    if (tierCard) {
      return {
        id: tierCard.dataset.tierId,
        name: tierCard.dataset.tierName,
        price: tierCard.dataset.tierPrice
      };
    }
    return null;
  }
  
  // Make showPayPalButton globally accessible
  window.showPayPalButton = showPayPalButton;
  
  // Helper function to copy to clipboard
  window.copyToClipboard = function(text) {
    navigator.clipboard.writeText(text).then(function() {
      window.showModal('Copied!', 'Account number copied to clipboard.', 'fas fa-check-circle', 'text-green-500');
    }).catch(function() {
      // Fallback for older browsers
      var textArea = document.createElement('textarea');
      textArea.value = text;
      document.body.appendChild(textArea);
      textArea.select();
      document.execCommand('copy');
      document.body.removeChild(textArea);
      window.showModal('Copied!', 'Account number copied to clipboard.', 'fas fa-check-circle', 'text-green-500');
    });
  };
  
  // Hide all payment detail sections - make it globally accessible
  window.hideAllPaymentSections = function() {
    var paypalContainer = document.getElementById('paypal-button-container');
    var cardContainer = document.getElementById('card-button-container');
    var paypalLoading = document.getElementById('paypal-loading');
    var paypalError = document.getElementById('paypal-error');
    var gcashDetails = document.getElementById('gcash-details');
    var bankDetails = document.getElementById('bank-transfer-details');
    var cryptoDetails = document.getElementById('crypto-usdt-details');
    
    if (paypalContainer) paypalContainer.classList.add('hidden');
    if (cardContainer) cardContainer.classList.add('hidden');
    if (paypalLoading) paypalLoading.classList.add('hidden');
    if (paypalError) paypalError.classList.add('hidden');
    if (gcashDetails) gcashDetails.classList.add('hidden');
    if (bankDetails) bankDetails.classList.add('hidden');
    if (cryptoDetails) cryptoDetails.classList.add('hidden');
  };
  
  // Toggle bank SWIFT/codes visibility
  window.toggleBankCodes = function() {
    var extraCodes = document.getElementById('bank-codes-content');
    var toggleIcon = document.getElementById('bank-codes-icon');
    if (!extraCodes) return;
    if (extraCodes.classList.contains('hidden')) {
      extraCodes.classList.remove('hidden');
      if (toggleIcon) toggleIcon.classList.add('rotate-180');
    } else {
      extraCodes.classList.add('hidden');
      if (toggleIcon) toggleIcon.classList.remove('rotate-180');
    }
  };
  
  // Bank receipt file input handler
  var bankReceiptInput = document.getElementById('bank_receipt');
  if (bankReceiptInput) {
    bankReceiptInput.addEventListener('change', function() {
      var fileName = this.files[0] ? this.files[0].name : '';
      var filenameDisplay = document.getElementById('bank_receipt_filename');
      var label = this.nextElementSibling; // The label is after the input
      if (fileName) {
        // Show filename below the button
        if (filenameDisplay) {
          filenameDisplay.textContent = fileName;
          filenameDisplay.classList.remove('hidden');
          filenameDisplay.classList.add('text-green-600', 'font-medium');
        }
        // Update label to show success state
        if (label) {
          label.innerHTML = '<i class="fas fa-check mr-1"></i> File Selected';
          label.style.background = '#22c55e';
        }
      } else {
        if (filenameDisplay) {
          filenameDisplay.classList.add('hidden');
        }
        if (label) {
          label.innerHTML = '<i class="fas fa-upload"></i> Choose File';
          label.style.background = 'var(--primary-500)';
        }
      }
    });
  }
  
  // Bank payment confirmation handler
  var confirmBankBtn = document.getElementById('confirm-bank-payment');
  if (confirmBankBtn) {
    confirmBankBtn.addEventListener('click', function() {
      var form = document.getElementById('wizardRegisterForm');
      var receiptInput = document.getElementById('bank_receipt');
      var referenceInput = document.getElementById('bank_reference');
      
      // Validate form fields
      var email = form.querySelector('input[name="email"]').value;
      var username = form.querySelector('input[name="username"]').value;
      var password = form.querySelector('input[name="password"]').value;
      
      if (!email || !username || !password) {
        window.showModal('Incomplete Form', 'Please complete Steps 1 & 2 before submitting payment.', 'fas fa-exclamation-circle', 'text-yellow-500');
        return;
      }
      
      if (!receiptInput.files[0]) {
        window.showModal('Receipt Required', 'Please upload a screenshot or photo of your bank transfer receipt.', 'fas fa-exclamation-circle', 'text-yellow-500');
        return;
      }
      
      if (!referenceInput.value.trim()) {
        window.showModal('Reference Required', 'Please enter your bank transaction reference number.', 'fas fa-exclamation-circle', 'text-yellow-500');
        return;
      }
      
      // Validate file type and size
      var file = receiptInput.files[0];
      var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
      if (!allowedTypes.includes(file.type)) {
        window.showModal('Invalid File', 'Please upload an image (JPG, PNG, GIF, WebP) or PDF file.', 'fas fa-exclamation-circle', 'text-red-500');
        return;
      }
      if (file.size > 10 * 1024 * 1024) { // 10MB max
        window.showModal('File Too Large', 'Please upload a file smaller than 10MB.', 'fas fa-exclamation-circle', 'text-red-500');
        return;
      }
      
      // Disable button and show loading
      confirmBankBtn.disabled = true;
      confirmBankBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
      
      // Prepare form data - create new FormData and manually add all fields
      // (bank_receipt is outside the form, so we need to append it explicitly)
      var formData = new FormData(form);
      
      // Explicitly append the file since it's outside the form element
      formData.append('bank_receipt', receiptInput.files[0]);
      
      var tier = getSelectedTier();
      formData.set('package', tier ? tier.name : currentTierName);
      formData.set('package_amount', tier ? tier.price : currentTierPrice.toFixed(2));
      formData.set('package_currency', 'PHP');
      formData.set('pay_method', 'bank_transfer');
      formData.set('payment_method', 'bank_transfer');
      formData.set('bank_reference', referenceInput.value.trim());
      formData.set('payment_status', 'pending'); // Bank transfers are pending until verified
      
      // Submit to bank-payments endpoint
      fetch('/bank-payments', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
      })
      .then(function(response) {
        return response.json();
      })
      .then(function(result) {
        if (result.success) {
          // Redirect to chat (user is now logged in with pending status)
          var redirectUrl = result.redirect || '/chat';
          window.showModal(
            'Account Created!', 
            result.message || 'Your account is now active! Your premium status will be unlocked once we verify your bank transfer (usually within 24 hours).', 
            'fas fa-check-circle', 
            'text-green-500',
            function() {
              window.location.href = redirectUrl;
            }
          );
        } else {
          confirmBankBtn.disabled = false;
          confirmBankBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i> Confirm Bank Payment';
          window.showModal('Submission Failed', result.message || 'There was an issue submitting your registration. Please try again.', 'fas fa-exclamation-circle', 'text-red-500');
        }
      })
      .catch(function(error) {
        console.error('Bank payment submission error:', error);
        confirmBankBtn.disabled = false;
        confirmBankBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i> Confirm Bank Payment';
        window.showModal('Network Error', 'Could not submit your registration. Please check your connection and try again.', 'fas fa-exclamation-triangle', 'text-red-500');
      });
    });
  }
  
  // GCash receipt file input handler
  var gcashReceiptInput = document.getElementById('gcash_receipt');
  if (gcashReceiptInput) {
    gcashReceiptInput.addEventListener('change', function() {
      var fileName = this.files[0] ? this.files[0].name : '';
      var filenameDisplay = document.getElementById('gcash_receipt_filename');
      var label = this.nextElementSibling; // The label is after the input
      if (fileName) {
        // Show filename below the button
        if (filenameDisplay) {
          filenameDisplay.textContent = fileName;
          filenameDisplay.classList.remove('hidden');
          filenameDisplay.classList.add('text-green-600', 'font-medium');
        }
        // Update label to show success state
        if (label) {
          label.innerHTML = '<i class="fas fa-check mr-1"></i> File Selected';
          label.style.background = '#22c55e';
        }
      } else {
        if (filenameDisplay) {
          filenameDisplay.classList.add('hidden');
        }
        if (label) {
          label.innerHTML = '<i class="fas fa-upload"></i> Choose File';
          label.style.background = '#007dfe';
        }
      }
    });
  }
  
  // GCash payment confirmation handler
  var confirmGcashBtn = document.getElementById('confirm-gcash-payment');
  if (confirmGcashBtn) {
    confirmGcashBtn.addEventListener('click', function() {
      var referenceInput = document.getElementById('gcash_reference');
      var receiptInput = document.getElementById('gcash_receipt');
      
      // Validate reference number
      if (!referenceInput || !referenceInput.value.trim()) {
        window.showModal('Reference Required', 'Please enter your GCash reference number.', 'fas fa-exclamation-circle', 'text-yellow-500');
        return;
      }
      
      // Validate receipt upload
      if (!receiptInput || !receiptInput.files || !receiptInput.files[0]) {
        window.showModal('Receipt Required', 'Please upload a screenshot of your GCash payment receipt.', 'fas fa-exclamation-circle', 'text-yellow-500');
        return;
      }
      
      // Show loading state
      confirmGcashBtn.disabled = true;
      confirmGcashBtn.innerHTML = '<div class="animate-spin w-5 h-5 border-2 border-white border-t-transparent rounded-full mr-2"></div> Processing...';
      
      // Build form data from the wizard form
      var form = document.getElementById('wizardRegisterForm');
      var formData = new FormData(form);
      
      // Explicitly append the file since it's outside the form element
      formData.append('gcash_receipt', receiptInput.files[0]);
      
      // Use selectedTier if available, otherwise fall back to form values
      var tier = window.selectedTier || null;
      var currentTierName = formData.get('package') || 'Professional';
      var currentTierPrice = parseFloat(formData.get('package_amount')) || 1000;
      
      formData.set('package', tier ? tier.name : currentTierName);
      formData.set('package_amount', tier ? tier.price : currentTierPrice.toFixed(2));
      formData.set('package_currency', 'PHP');
      formData.set('pay_method', 'gcash');
      formData.set('payment_method', 'gcash');
      formData.set('gcash_reference', referenceInput.value.trim());
      formData.set('payment_status', 'pending'); // GCash payments are pending until verified
      
      // Submit to gcash-payments endpoint
      fetch('/gcash-payments', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
      })
      .then(function(response) {
        return response.json();
      })
      .then(function(result) {
        if (result.success) {
          // Redirect to chat (user is now logged in with pending status)
          var redirectUrl = result.redirect || '/chat';
          window.showModal(
            'Account Created!', 
            result.message || 'Your account is now active! Your premium status will be unlocked once we verify your GCash payment (usually within 24 hours).', 
            'fas fa-check-circle', 
            'text-green-500',
            function() {
              window.location.href = redirectUrl;
            }
          );
        } else {
          confirmGcashBtn.disabled = false;
          confirmGcashBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i> Confirm GCash Payment';
          window.showModal('Submission Failed', result.message || 'There was an issue submitting your registration. Please try again.', 'fas fa-exclamation-circle', 'text-red-500');
        }
      })
      .catch(function(error) {
        console.error('GCash payment submission error:', error);
        confirmGcashBtn.disabled = false;
        confirmGcashBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i> Confirm GCash Payment';
        window.showModal('Network Error', 'Could not submit your registration. Please check your connection and try again.', 'fas fa-exclamation-triangle', 'text-red-500');
      });
    });
  }
  
  // Crypto USDT BEP20 payment handling
  
  // Load crypto payment info via AJAX
  function loadCryptoInfo() {
    var loadingEl = document.getElementById('crypto-loading');
    var infoContainer = document.getElementById('crypto-info-container');
    var errorEl = document.getElementById('crypto-error');
    
    fetch('/api/payments/crypto-info', {
      method: 'GET',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(response) {
      return response.json();
    })
    .then(function(data) {
      if (data.success) {
        // Populate the crypto info
        document.getElementById('crypto-qr-image').src = data.qr_image;
        document.getElementById('crypto-network').textContent = data.network;
        document.getElementById('crypto-address').textContent = data.address;
        document.getElementById('crypto-verify-link').href = data.verification_api;
        
        // Store address for copy function
        window.cryptoWalletAddress = data.address;
        
        // Hide loading, show info
        loadingEl.classList.add('hidden');
        infoContainer.classList.remove('hidden');
        cryptoInfoLoaded = true;
      } else {
        loadingEl.classList.add('hidden');
        errorEl.classList.remove('hidden');
      }
    })
    .catch(function(error) {
      console.error('Failed to load crypto info:', error);
      loadingEl.classList.add('hidden');
      errorEl.classList.remove('hidden');
    });
  }
  
  // Copy crypto address to clipboard
  window.copyCryptoAddress = function() {
    var address = window.cryptoWalletAddress || document.getElementById('crypto-address').textContent;
    navigator.clipboard.writeText(address).then(function() {
      window.showModal('Copied!', 'USDT BEP20 wallet address copied to clipboard.', 'fas fa-check-circle', 'text-green-500');
    }).catch(function() {
      // Fallback for older browsers
      var textArea = document.createElement('textarea');
      textArea.value = address;
      document.body.appendChild(textArea);
      textArea.select();
      document.execCommand('copy');
      document.body.removeChild(textArea);
      window.showModal('Copied!', 'USDT BEP20 wallet address copied to clipboard.', 'fas fa-check-circle', 'text-green-500');
    });
  };
  
  // Crypto receipt file input handler
  var cryptoReceiptInput = document.getElementById('crypto_receipt');
  if (cryptoReceiptInput) {
    cryptoReceiptInput.addEventListener('change', function() {
      var fileName = this.files[0] ? this.files[0].name : '';
      var filenameDisplay = document.getElementById('crypto_receipt_filename');
      var label = this.nextElementSibling;
      if (fileName) {
        if (filenameDisplay) {
          filenameDisplay.textContent = fileName;
          filenameDisplay.classList.remove('hidden');
          filenameDisplay.classList.add('text-green-600', 'font-medium');
        }
        if (label) {
          label.innerHTML = '<i class="fas fa-check mr-1"></i> File Selected';
          label.style.background = '#22c55e';
          label.style.color = '#fff';
        }
      } else {
        if (filenameDisplay) {
          filenameDisplay.classList.add('hidden');
        }
        if (label) {
          label.innerHTML = '<i class="fas fa-upload"></i> Choose File';
          label.style.background = '#f0b90b';
          label.style.color = '#000';
        }
      }
    });
  }
  
  // Crypto payment confirmation handler
  var confirmCryptoBtn = document.getElementById('confirm-crypto-payment');
  if (confirmCryptoBtn) {
    confirmCryptoBtn.addEventListener('click', function() {
      var form = document.getElementById('wizardRegisterForm');
      var receiptInput = document.getElementById('crypto_receipt');
      var txHashInput = document.getElementById('crypto_txhash');
      
      // Validate form fields
      var email = form.querySelector('input[name="email"]').value;
      var username = form.querySelector('input[name="username"]').value;
      var password = form.querySelector('input[name="password"]').value;
      
      if (!email || !username || !password) {
        window.showModal('Incomplete Form', 'Please complete Steps 1 & 2 before submitting payment.', 'fas fa-exclamation-circle', 'text-yellow-500');
        return;
      }
      
      var txHash = txHashInput.value.trim();
      if (!txHash) {
        window.showModal('Transaction Hash Required', 'Please enter your USDT transaction hash (TxHash).', 'fas fa-exclamation-circle', 'text-yellow-500');
        return;
      }
      
      // Validate transaction hash format
      if (!/^0x[a-fA-F0-9]{64}$/.test(txHash)) {
        window.showModal('Invalid Transaction Hash', 'Transaction hash should be 66 characters starting with 0x. Please check and try again.', 'fas fa-exclamation-circle', 'text-red-500');
        return;
      }
      
      // Validate file if provided
      if (receiptInput.files[0]) {
        var file = receiptInput.files[0];
        var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
        if (!allowedTypes.includes(file.type)) {
          window.showModal('Invalid File', 'Please upload an image (JPG, PNG, GIF, WebP) or PDF file.', 'fas fa-exclamation-circle', 'text-red-500');
          return;
        }
        if (file.size > 10 * 1024 * 1024) {
          window.showModal('File Too Large', 'Please upload a file smaller than 10MB.', 'fas fa-exclamation-circle', 'text-red-500');
          return;
        }
      }
      
      // Disable button and show loading
      confirmCryptoBtn.disabled = true;
      confirmCryptoBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
      
      // Prepare form data
      var formData = new FormData(form);
      
      // Append crypto-specific fields
      if (receiptInput.files[0]) {
        formData.append('crypto_receipt', receiptInput.files[0]);
      }
      
      var tier = getSelectedTier();
      formData.set('package', tier ? tier.name : currentTierName);
      formData.set('package_amount', tier ? tier.price : currentTierPrice.toFixed(2));
      formData.set('package_currency', 'USDT');
      formData.set('pay_method', 'crypto_usdt_bep20');
      formData.set('payment_method', 'crypto_usdt_bep20');
      formData.set('crypto_txhash', txHash);
      formData.set('payment_status', 'pending');
      
      // Submit to crypto-payments endpoint
      fetch('/crypto-payments', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
      })
      .then(function(response) {
        return response.json();
      })
      .then(function(result) {
        if (result.success) {
          var redirectUrl = result.redirect || '/chat';
          var verifyUrl = result.verification_url || '';
          var message = result.message || 'Your account is now active! Your premium status will be unlocked once we verify your USDT payment on the blockchain (usually within 24 hours).';
          if (verifyUrl) {
            message += '<br><br><a href="' + verifyUrl + '" target="_blank" class="text-blue-400 underline">View transaction on BscScan</a>';
          }
          window.showModal(
            'Account Created!', 
            message, 
            'fas fa-check-circle', 
            'text-green-500',
            function() {
              window.location.href = redirectUrl;
            }
          );
        } else {
          confirmCryptoBtn.disabled = false;
          confirmCryptoBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i> Confirm USDT Payment';
          window.showModal('Submission Failed', result.message || 'There was an issue submitting your registration. Please try again.', 'fas fa-exclamation-circle', 'text-red-500');
        }
      })
      .catch(function(error) {
        console.error('Crypto payment submission error:', error);
        confirmCryptoBtn.disabled = false;
        confirmCryptoBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i> Confirm USDT Payment';
        window.showModal('Network Error', 'Could not submit your registration. Please check your connection and try again.', 'fas fa-exclamation-triangle', 'text-red-500');
      });
    });
  }
  
  // Track if card buttons have been rendered separately
  var cardButtonsRendered = false;
  
  // Track if crypto info has been loaded
  var cryptoInfoLoaded = false;
  
  // Payment method change handler
  document.querySelectorAll('input[name="payment_method"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
      document.getElementById('selectedPayMethod').value = this.value;
      
      // Hide all payment sections first
      window.hideAllPaymentSections();
      
      // Hide the complete purchase button by default
      document.getElementById('complete-purchase').classList.add('hidden');
      
      if (this.value === 'paypal') {
        // Show PayPal-only button
        showPayPalButton('paypal');
      } else if (this.value === 'credit_card') {
        // Show Card-only button
        showPayPalButton('card');
      } else if (this.value === 'gcash') {
        // Show GCash account details
        document.getElementById('gcash-details').classList.remove('hidden');
        // Don't show complete-purchase, GCash has its own confirm button
      } else if (this.value === 'bank_transfer') {
        // Show Bank Transfer details
        document.getElementById('bank-transfer-details').classList.remove('hidden');
        // Don't show complete-purchase, bank has its own confirm button
      } else if (this.value === 'crypto_usdt_bep20') {
        // Show Crypto USDT BEP20 details
        document.getElementById('crypto-usdt-details').classList.remove('hidden');
        // Load crypto info via AJAX if not already loaded
        if (!cryptoInfoLoaded) {
          loadCryptoInfo();
        }
        // Don't show complete-purchase, crypto has its own confirm button
      }
    });
  });
  
  function showPayPalButton(fundingType) {
    var container, loading, errorDiv;
    
    if (fundingType === 'card') {
      container = document.getElementById('card-button-container');
      loading = document.getElementById('paypal-loading');
      errorDiv = document.getElementById('paypal-error');
      container.classList.remove('hidden');
      
      if (cardButtonsRendered) return;
      loading.classList.remove('hidden');
    } else {
      container = document.getElementById('paypal-button-container');
      loading = document.getElementById('paypal-loading');
      errorDiv = document.getElementById('paypal-error');
      container.classList.remove('hidden');
      
      if (paypalButtonsRendered) return;
      loading.classList.remove('hidden');
    }
    
    errorDiv.classList.add('hidden');
    
    // PayPal SDK is already loaded in head, just render the buttons
    if (typeof paypal !== 'undefined' && paypal.Buttons) {
      renderPayPalButtons(fundingType);
    } else {
      errorDiv.classList.remove('hidden');
      document.getElementById('paypal-error-message').textContent = 'PayPal failed to initialize. Please refresh.';
      loading.classList.add('hidden');
    }
  }
  
  function renderPayPalButtons(fundingType) {
    var loading = document.getElementById('paypal-loading');
    var errorDiv = document.getElementById('paypal-error');
    var containerId = fundingType === 'card' ? '#card-button-container' : '#paypal-button-container';
    var payMethodValue = fundingType === 'card' ? 'credit_card' : 'paypal';
    
    // Check if already rendered
    if (fundingType === 'card' && cardButtonsRendered) return;
    if (fundingType !== 'card' && paypalButtonsRendered) return;
    
    var tier = getSelectedTier();
    if (tier) {
      updatePayPalAmount(tier.name, tier.price);
    }
    
    try {
      var buttonConfig = {
        style: {
          shape: 'rect',
          color: fundingType === 'card' ? 'black' : 'gold',
          layout: 'vertical',
          label: 'pay'
        },
        createOrder: function(data, actions) {
          var form = document.getElementById('wizardRegisterForm');
          var email = form.querySelector('input[name="email"]').value;
          var username = form.querySelector('input[name="username"]').value;
          
          if (!email || !username) {
            errorDiv.classList.remove('hidden');
            document.getElementById('paypal-error-message').textContent = 'Please complete Steps 1 & 2 first.';
            throw new Error('Form incomplete');
          }
          
          var phpToUsd = currentTierPrice / 56;
          var usdAmount = Math.max(1, phpToUsd).toFixed(2);
          
          return actions.order.create({
            purchase_units: [{
              description: 'Ginto ' + currentTierName + ' Membership',
              amount: {
                currency_code: 'USD',
                value: usdAmount
              }
            }]
          });
        },
        onApprove: function(data, actions) {
          return actions.order.capture().then(function(orderData) {
            var form = document.getElementById('wizardRegisterForm');
            var formData = new FormData(form);
            
            var tier = getSelectedTier();
            formData.set('package', tier ? tier.name : currentTierName);
            formData.set('package_amount', tier ? tier.price : currentTierPrice.toFixed(2));
            formData.set('package_currency', 'PHP');
            formData.set('pay_method', payMethodValue);
            formData.append('paypal_order_id', data.orderID);
            formData.append('paypal_payment_status', 'COMPLETED');
            formData.append('payment_method', payMethodValue);
            
            var successContainer = fundingType === 'card' ? 'card-button-container' : 'paypal-button-container';
            document.getElementById(successContainer).innerHTML = 
              '<div class="text-center py-4"><i class="fas fa-check-circle text-green-500 text-3xl mb-2"></i><p class="text-green-600 font-semibold">Payment Successful!</p><p class="text-sm text-gray-500">Completing registration...</p></div>';
            
            return fetch('/register', {
              method: 'POST',
              headers: { 'X-Requested-With': 'XMLHttpRequest' },
              body: formData
            })
            .then(function(response) {
              return response.json();
            })
            .then(function(result) {
              if (result.success) {
                window.showModal('Welcome to Ginto!', 'Your payment was successful and your account is now active!', 'fas fa-check-circle', 'text-green-500');
                setTimeout(function() { window.location.href = '/login'; }, 2500);
              } else {
                window.showModal('Registration Issue', result.message || 'Payment received but registration had an issue. Please contact support.', 'fas fa-exclamation-circle', 'text-yellow-500');
              }
            })
            .catch(function(error) {
              console.error('Registration error:', error);
              window.showModal('Network Error', 'Payment successful but could not complete registration. Please contact support with Order ID: ' + data.orderID, 'fas fa-exclamation-triangle', 'text-red-500');
            });
          });
        },
        onError: function(err) {
          console.error('PayPal error:', err);
          errorDiv.classList.remove('hidden');
          document.getElementById('paypal-error-message').textContent = 'Payment failed. Please try again.';
        },
        onCancel: function() {
          console.log('Payment cancelled by user');
        }
      };
      
      // Add funding source restriction
      if (fundingType === 'card') {
        buttonConfig.fundingSource = paypal.FUNDING.CARD;
      } else {
        buttonConfig.fundingSource = paypal.FUNDING.PAYPAL;
      }
      
      paypal.Buttons(buttonConfig).render(containerId).then(function() {
        loading.classList.add('hidden');
        if (fundingType === 'card') {
          cardButtonsRendered = true;
          console.log('Card buttons rendered successfully');
        } else {
          paypalButtonsRendered = true;
          console.log('PayPal buttons rendered successfully');
        }
      }).catch(function(err) {
        console.error('PayPal render error:', err);
        loading.classList.add('hidden');
        errorDiv.classList.remove('hidden');
        document.getElementById('paypal-error-message').textContent = 'Failed to load payment buttons. Please refresh the page.';
      });
    } catch (error) {
      console.error('PayPal initialization error:', error);
      loading.classList.add('hidden');
      errorDiv.classList.remove('hidden');
      document.getElementById('paypal-error-message').textContent = 'Failed to initialize payment. Please refresh the page.';
    }
  }
})();

// Smooth scroll only on click, not on page load
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function(e) {
    const targetId = this.getAttribute('href');
    if (targetId && targetId !== '#') {
      const targetEl = document.querySelector(targetId);
      if (targetEl) {
        e.preventDefault();
        targetEl.scrollIntoView({ behavior: 'smooth' });
        // Update URL without triggering scroll
        history.pushState(null, null, targetId);
      }
    }
  });
});
</script>
</body>
</html>