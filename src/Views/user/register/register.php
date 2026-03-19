<?php
// Subscription pricing configuration - PHP for Philippines, USD for international
// Starter is a flat ₱250/month recurring subscription
// All other packages have same first month and recurring price
$pricingConfig = [
    'Starter' => [
        'firstMonth' => ['php' => 250, 'usd' => 5],
        'recurring' => ['php' => 250, 'usd' => 5],
    ],
    'Professional' => [
        'firstMonth' => ['php' => 1000, 'usd' => 20],
        'recurring' => ['php' => 1000, 'usd' => 20],
    ],
    'Executive' => [
        'firstMonth' => ['php' => 5000, 'usd' => 99],
        'recurring' => ['php' => 5000, 'usd' => 99],
    ],
    'Gold' => [
        'firstMonth' => ['php' => 10000, 'usd' => 199],
        'recurring' => ['php' => 10000, 'usd' => 199],
    ],
    'Platinum' => [
        'firstMonth' => ['php' => 50000, 'usd' => 999],
        'recurring' => ['php' => 50000, 'usd' => 999],
    ],
];

// PayPal subscription plan IDs - read from tier_plans database table
// Use sandbox or live column based on PAYPAL_ENVIRONMENT
$paypalEnv = $_ENV['PAYPAL_ENVIRONMENT'] ?? getenv('PAYPAL_ENVIRONMENT') ?? 'sandbox';
$planColumn = ($paypalEnv === 'sandbox') ? 'paypal_plan_id_sandbox' : 'paypal_plan_id';
$paypalPlanIds = [];
try {
    $db = \Ginto\Core\Database::getInstance();
    $tiers = $db->select('tier_plans', ['name', $planColumn], [$planColumn . '[!]' => null]);
    foreach ($tiers as $tier) {
        if (!empty($tier[$planColumn])) {
            $paypalPlanIds[$tier['name']] = $tier[$planColumn];
        }
    }
} catch (Exception $e) {
    // Fallback to empty - subscriptions won't work without plan IDs
    error_log('Failed to load PayPal plan IDs from database: ' . $e->getMessage());
}

// Active payment processors - comma-separated list from env
$activeProcessorsRaw = $_ENV['ACTIVE_PAYMENT_PROCESSORS'] ?? getenv('ACTIVE_PAYMENT_PROCESSORS') ?? 'paypal,gcash,bank_transfer,crypto';
$activeProcessors = array_map('trim', explode(',', $activeProcessorsRaw));
$paymongoEnabled = in_array('paymongo', $activeProcessors, true);
?>
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
    
    // Subscription pricing configuration passed from PHP
    window.GINTO_PRICING = <?= json_encode($pricingConfig) ?>;
    window.GINTO_PAYPAL_PLANS = <?= json_encode($paypalPlanIds) ?>;
    window.GINTO_ACTIVE_PROCESSORS = <?= json_encode($activeProcessors) ?>;
    window.GINTO_PAYMONGO_ENABLED = <?= $paymongoEnabled ? 'true' : 'false' ?>;
  </script>
  <link rel="icon" type="image/png" href="/assets/images/ginto.png">
  <link href="/assets/css/tailwind.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
  
  <!-- PayPal SDK for subscriptions -->
  <?php 
  // PayPal env already set above
  $testPaypalClientId = $paypalEnv === 'sandbox' 
    ? ($_ENV['PAYPAL_CLIENT_ID_SANDBOX'] ?? getenv('PAYPAL_CLIENT_ID_SANDBOX') ?? '')
    : ($_ENV['PAYPAL_CLIENT_ID'] ?? getenv('PAYPAL_CLIENT_ID') ?? '');
  $testPaypalClientId = preg_replace('/\s+/', '', $testPaypalClientId);
  ?>
  <script src="https://www.paypal.com/sdk/js?client-id=<?= htmlspecialchars($testPaypalClientId, ENT_QUOTES, 'UTF-8') ?>&vault=true&intent=subscription&components=buttons"></script>
  
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

    .payment-modal-shell {
      display: flex;
      align-items: flex-start;
      justify-content: center;
      min-height: 100%;
      padding: 1rem 0.85rem 2.5rem;
    }
    .payment-modal-card {
      background: var(--bg-primary);
      border-radius: 1rem;
      box-shadow: 0 25px 60px rgba(0,0,0,0.5);
      width: 100%;
      max-width: 32rem;
      display: flex;
      flex-direction: column;
    }
    .payment-modal-tabs {
      display: grid;
      grid-template-columns: 1fr;
      gap: 0.55rem;
      padding: 0.85rem 0.9rem;
      border-bottom: 1px solid var(--border-color);
    }
    .payment-modal-tab {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      width: 100%;
      min-height: 3rem;
      padding: 0.72rem 0.95rem;
      border-radius: 0.7rem;
      border: 2px solid var(--border-color);
      background: transparent;
      color: var(--text-secondary);
      font-size: 0.9rem;
      font-weight: 700;
      cursor: pointer;
      transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    }
    .payment-modal-tab:hover {
      transform: translateY(-1px);
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
    }
    .payment-modal-tab.active {
      color: #ffffff;
      box-shadow: 0 10px 22px rgba(0, 0, 0, 0.28);
      border-color: transparent;
    }

    .ginto-pay-option {
      border: 2px solid #d4af37;
      box-shadow: 0 0 0 1px rgba(245, 214, 123, 0.45), 0 8px 20px rgba(212, 175, 55, 0.18);
      background: linear-gradient(115deg, var(--bg-card) 72%, rgba(245, 214, 123, 0.38) 100%);
    }
    .ginto-pay-logo {
      width: 1.3rem;
      height: 1.3rem;
      object-fit: contain;
      border-radius: 9999px;
      box-shadow: 0 0 0 1px rgba(212, 175, 55, 0.55), 0 0 8px rgba(245, 214, 123, 0.4);
      background: #ffffff;
    }
    .ginto-pay-badge {
      background: linear-gradient(135deg, #b8860b 0%, #d4af37 40%, #f5d67b 100%);
      color: #2b2110;
      border: 1px solid rgba(139, 105, 20, 0.6);
      text-shadow: 0 1px 0 rgba(255, 255, 255, 0.35);
    }
    .payment-method-container {
      align-items: center;
      cursor: pointer;
    }
    .payment-method-container * {
      cursor: pointer;
    }
    .payment-method-container label {
      min-width: 0;
      width: 100%;
      white-space: normal;
      overflow-wrap: anywhere;
      word-break: break-word;
      line-height: 1.28;
    }
    .payment-method-container label > span,
    .payment-method-container label > div,
    .payment-method-container .inline-flex {
      min-width: 0;
      white-space: normal;
      overflow-wrap: anywhere;
      word-break: break-word;
    }
    .payment-method-copy {
      display: flex;
      flex-direction: column;
      gap: 0.2rem;
      min-width: 0;
    }
    .payment-method-title {
      font-size: 1rem;
      font-weight: 700;
      letter-spacing: 0.01em;
      line-height: 1.2;
      color: var(--text-primary);
    }
    .payment-method-meta {
      display: inline-flex;
      align-items: center;
      width: fit-content;
      max-width: 100%;
      padding: 0.18rem 0.5rem;
      border-radius: 0.45rem;
      font-size: 0.82rem;
      font-weight: 700;
      line-height: 1.2;
      white-space: normal;
      overflow-wrap: anywhere;
      word-break: break-word;
    }
    .meta-orange {
      background: linear-gradient(135deg, #f97316 0%, #fb923c 100%);
      color: #fff;
    }
    .meta-green {
      background: linear-gradient(135deg, #16a34a 0%, #22c55e 100%);
      color: #fff;
    }
    .meta-gold {
      background: linear-gradient(135deg, #b8860b 0%, #d4af37 42%, #f5d67b 100%);
      color: #2b2110;
      border: 1px solid rgba(139, 105, 20, 0.6);
      text-shadow: 0 1px 0 rgba(255, 255, 255, 0.35);
    }
    .meta-yellow {
      background: linear-gradient(135deg, #f0b90b 0%, #d4a00a 100%);
      color: #000;
    }

    @media (min-width: 1024px) {
      /* Desktop: remove heavy highlight chips for cleaner, elegant labels */
      .payment-method-meta {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0;
        border-radius: 0;
        color: var(--text-secondary) !important;
        font-weight: 600;
        font-size: 0.84rem;
      }
    }
    .payment-modal-tab.ginto-pay-tab.active {
      color: #2b2110;
      border-color: #b8860b;
      background: linear-gradient(135deg, #b8860b 0%, #d4af37 42%, #f5d67b 100%);
      box-shadow: 0 10px 24px rgba(184, 134, 11, 0.35);
    }
    .payment-modal-tab.ginto-pay-tab .ginto-pay-logo {
      width: 1rem;
      height: 1rem;
    }

    @media (min-width: 640px) {
      .payment-modal-shell {
        padding: 1.5rem 1rem 3rem;
      }
      .payment-modal-tabs {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
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
    <!-- Global CSRF token for AJAX requests -->
    <input type="hidden" id="global-csrf-token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

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
        <a href="/" class="flex items-center space-x-2">
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
          <span class="text-lg font-semibold uppercase mb-3 block" style="color: var(--primary-700);">The shortcut to your best work.</span>
          <h1 class="text-5xl lg:text-6xl font-extrabold leading-tight mb-6">
            <br>Think faster. <span style="color: var(--accent-500);"> Build more. Do more!</span>
          </h1>
          <p class="text-xl mb-8 max-w-lg mx-auto md:mx-0" style="color: var(--text-secondary);">
            Cutting-edge agentic technology designed to help you achieve more, at the fastest speed.
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
        <h2 class="text-4xl font-extrabold mb-4" style="color: var(--text-primary);">Built-in rewards with usage and recommendations</h2>
        <p class="text-xl mb-12 max-w-2xl mx-auto" style="color: var(--text-secondary);">
          Our unique "Commission" model ensures long-term sustainability and rewards growth where it matters most – at the front lines.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div class="p-8 rounded-xl shadow-lg glow-card flex flex-col items-center transform hover:scale-105 transition-transform duration-300" style="background: var(--bg-card);">
            <div class="text-5xl mb-4" style="color: var(--primary-600);">
              <i class="fas fa-hand-holding-usd"></i>
            </div>
            <h3 class="text-2xl font-bold mb-3" style="color: var(--text-primary);">Earning While Learning</h3>
            <p class="text-center" style="color: var(--text-secondary);">
              We prioritize a stable ecosystem with a transparent and predictable incentive structure as you grow with our tools, designed to support your long-term growth with simplicity & clarity.
            </p>
          </div>

          <div class="p-8 rounded-xl shadow-lg glow-card flex flex-col items-center transform hover:scale-105 transition-transform duration-300" style="background: var(--bg-card);">
            <div class="text-5xl mb-4" style="color: var(--primary-500);">
              <i class="fas fa-chart-line"></i>
            </div>
            <h3 class="text-2xl font-bold mb-3" style="color: var(--text-primary);">Tiered Revenue Share</h3>
            <p class="text-center" style="color: var(--text-secondary);">
              Scale your earnings as your impact grows. Our partner program offers increasing percentages based on the volume of successful referrals you drive to the platform.
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
              <i class="fas fa-info-circle mr-3" style="color: var(--accent-500);"></i> Ginto Commission Structure
            </h2>
            <p class="text-lg mt-1" style="color: #f4faff; opacity: 0.92;">Unlock superior earnings for your direct sales!</p>
          </div>
          <div class="p-8" style="background: linear-gradient(120deg, rgba(31,162,255,0.10) 0%, rgba(255,229,59,0.10) 100%);">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
              <div>
                <h3 class="text-2xl font-semibold mb-5" style="color: var(--primary-500);">Tiered Commission Breakdown (Per ₱150 Sale)</h3>
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
                    <span class="text-2xl font-extrabold" style="color: var(--positive-500);">₱24.00 (16% of ₱150)</span>
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
                    <span class="text-3xl font-extrabold" style="color: var(--positive-500);">₱51</span>
                  </div>
                  <div class="space-y-4 text-lg">
                    <div class="flex justify-between items-center">
                      <span class="font-medium" style="color: var(--text-primary);">Ginto Service Cost</span>
                      <span class="font-bold" style="color: var(--text-primary);">₱250</span>
                    </div>
                    <div class="flex justify-between items-center">
                      <span class="font-medium" style="color: var(--text-primary);">Direct Tier Commission (40%)</span>
                      <span class="font-bold" style="color: #ff4d4f;">- ₱100</span>
                    </div>
                    <div class="flex justify-between items-center">
                      <span class="font-medium" style="color: var(--text-primary);">Tiered Commission Payouts (9.6%)</span>
                      <span class="font-bold" style="color: #ff4d4f;">- ₱24</span>
                    </div>
                    <div class="flex justify-between items-center">
                      <span class="font-medium" style="color: var(--text-primary);">Operational Costs</span>
                      <span class="font-bold" style="color: #ff4d4f;">- ₱75</span>
                    </div>
                    <div class="pt-4 mt-4 flex justify-between items-center" style="border-top: 1px solid var(--border-color);">
                      <span class="font-bold text-xl" style="color: var(--primary-500);">Company Net Profit</span>
                      <span class="font-extrabold text-2xl" style="color: var(--positive-500);">₱51</span>
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
                      <strong>Fair Multi-tier Rewards:</strong> Commissions decrease as you move up the upline (to your sponsor and their upline), ensuring the person closest to the sale gets the best cut.
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
          <p class="mb-8" style="color: var(--text-secondary);">Choose the tier that matches your goals. Monthly subscription with greater earning potential.</p>
          
          <!-- Core 3 Tiers -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="tier-card p-6 relative" data-tier-id="1" data-tier-name="Starter" 
                 data-tier-first-php="250" data-tier-first-usd="5" 
                 data-tier-recurring-php="250" data-tier-recurring-usd="5">
              <img src="/assets/images/atome.png" alt="Atome Toothpaste" class="w-full rounded-lg mb-4 object-cover" style="max-height: 160px;">
              <div class="tier-badge" style="background: linear-gradient(135deg, #ff6a00, #ffe53b);">🔥 PROMO</div>
              <h4 class="text-xl font-bold mb-2" style="color: var(--text-primary);">Starter</h4>
              <p class="mb-4" style="color: var(--text-secondary);">Perfect for beginners with basic training.</p>
              <div class="mb-4">
                <div class="tier-price-display" style="color: var(--primary-500);">
                  <span class="text-3xl font-extrabold first-month-price">₱250</span>
                  <span class="text-sm font-medium" style="color: var(--text-secondary);"> /month</span>
                </div>

              </div>
              <ul class="space-y-2 mb-6 text-sm" style="color: var(--text-secondary);">
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> One Atome toothpaste</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> 40% outright referral commission</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Monthly residual earnings</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Ginto General MasterClass</li>
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

            <div class="tier-card p-6 relative" data-tier-id="2" data-tier-name="Professional" 
                 data-tier-first-php="1000" data-tier-first-usd="20" 
                 data-tier-recurring-php="1000" data-tier-recurring-usd="20">
              <img src="/assets/images/build-pro.png" alt="Build Pro Package" class="w-full rounded-lg mb-4 object-cover" style="max-height: 160px;">
              <div class="tier-badge">Recommended</div>
              <h4 class="text-xl font-bold mb-2" style="color: var(--text-primary);">Professional</h4>
              <p class="mb-4" style="color: var(--text-secondary);">For serious earners with advanced training.</p>
              <div class="mb-4">
                <div class="tier-price-display" style="color: var(--primary-500);">
                  <span class="text-3xl font-extrabold first-month-price">₱1,000</span>
                  <span class="text-sm font-medium" style="color: var(--text-secondary);">/month</span>
                </div>
              </div>
              <ul class="space-y-2 mb-6 text-sm" style="color: var(--text-secondary);">
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Build Pro kit given free every 6 months</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Optional P3000.00 (PH only) for the multivitamin kit</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> 45% outright commission</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Monthly residual income</li>
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

            <div class="tier-card p-6 relative" data-tier-id="3" data-tier-name="Executive" 
                 data-tier-first-php="5000" data-tier-first-usd="99" 
                 data-tier-recurring-php="5000" data-tier-recurring-usd="99">
              <img src="/assets/images/smartfi.png" alt="SmartFi Device" class="w-full rounded-lg mb-4 object-cover" style="max-height: 160px;">
              <div class="tier-badge">Elite</div>
              <h4 class="text-xl font-bold mb-2" style="color: var(--text-primary);">Executive</h4>
              <p class="mb-4" style="color: var(--text-secondary);">Maximum earning potential with elite training.</p>
              <div class="mb-4">
                <div class="tier-price-display" style="color: var(--primary-500);">
                  <span class="text-3xl font-extrabold first-month-price">₱5,000</span>
                  <span class="text-sm font-medium" style="color: var(--text-secondary);">/month</span>
                </div>
              </div>
              <ul class="space-y-2 mb-6 text-sm" style="color: var(--text-secondary);">
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> One SmartFi 360 Device every 3 months of subscription</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> SmartFi Masterclass</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Access to all 8 levels</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Elite training program</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Personal mentor</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> VIP Agentic Support</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Free Website on Profile</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Motivational Dashboard</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Entry level AI tools</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Weekly PowerBuilder Tech Support</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Wifi Business service to 1km radius</li>
                <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> 5% referral commission</li>
              </ul>
              <button type="button" class="w-full font-semibold py-2 px-4 rounded-lg transition-colors select-tier" style="background-color: var(--bg-secondary); color: var(--primary-500); border: 1px solid var(--primary-500);">
                Select Plan
              </button>
            </div>
          </div>

          <!-- Premium Tiers -->
          <div class="pt-8 mb-8" style="border-top: 1px solid var(--border-color);">
            <h4 class="text-xl font-bold mb-6 text-center" style="color: var(--text-primary);">Premium Packages (Ginto Datacenter Technology)</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="tier-card p-6 relative" data-tier-id="4" data-tier-name="Gold" 
                   data-tier-first-php="10000" data-tier-first-usd="199" 
                   data-tier-recurring-php="10000" data-tier-recurring-usd="199">
                <img src="/assets/images/gold-solar.png" alt="Gold Solar Package" class="w-full rounded-lg mb-4 object-cover" style="max-height: 160px;">
                <div class="tier-badge" style="background: linear-gradient(135deg, var(--accent-500), var(--accent-600));">Gold</div>
                <h4 class="text-xl font-bold mb-2" style="color: var(--text-primary);">Gold</h4>
                <p class="mb-4" style="color: var(--text-secondary);">Advanced package for serious earners.</p>
                <div class="mb-4">
                  <div class="tier-price-display" style="color: var(--primary-500);">
                    <span class="text-3xl font-extrabold first-month-price">₱10,000</span>
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">/month</span>
                  </div>
                </div>
                <ul class="space-y-2 mb-6 text-sm" style="color: var(--text-secondary);">
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> One piece of solar panel (Free)</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Hands on tutorial for solar panel installation</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Access to 5 tier system</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Advanced Multi-Tier Business Framework</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Free Subdomain</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Trinee Tech Support</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Premium AI tools</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Custom business backend</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Serverless Premium Hands on</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Proprietary Serverless Backend Training Technology Certification program</li>
                </ul>
                <button type="button" class="w-full font-semibold py-2 px-4 rounded-lg transition-colors select-tier" style="background-color: var(--bg-secondary); color: var(--primary-500); border: 1px solid var(--primary-500);">
                  Select Plan
                </button>
              </div>

              <div class="tier-card p-6 relative" data-tier-id="5" data-tier-name="Platinum" 
                   data-tier-first-php="50000" data-tier-first-usd="999" 
                   data-tier-recurring-php="50000" data-tier-recurring-usd="999">
                <img src="/assets/images/server.png" alt="Server Deployment" class="w-full rounded-lg mb-4 object-cover" style="max-height: 160px;">
                <div class="tier-badge" style="background: linear-gradient(135deg, #E5E4E2, #B0C4DE);">Platinum</div>
                <h4 class="text-xl font-bold mb-2" style="color: var(--text-primary);">Platinum</h4>
                <p class="mb-4" style="color: var(--text-secondary);">Top-tier package with VIP treatment.</p>
                <div class="mb-4">
                  <div class="tier-price-display" style="color: var(--primary-500);">
                    <span class="text-3xl font-extrabold first-month-price">₱50,000</span>
                    <span class="text-sm font-medium" style="color: var(--text-secondary);">/month</span>
                  </div>
                </div>
                <ul class="space-y-2 mb-6 text-sm" style="color: var(--text-secondary);">
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Staggered full solar system deployment</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Ginto Decentralized Datacenter Masterclass</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> VIP events, mentorship and tech workshops</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Ginto Serverless Hyperscaling</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Ginto Multi-tier concierge server farm framework</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Full AI suite early access and latest updates</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Inclusion to AI dev community</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Offline AI Home Framework</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Market share to 10km radius</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Managed Serverless Mall</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Free Ginto Marketplace Listing (with KYC)</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Free Ginto subdomain + one fully available domain</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Scalable hyperscaler infrastructure</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Free access to up to 50% of Ginto AI Farm power</li>
                  <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Full Tech Support</li>
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
            <input type="hidden" name="promo_code" id="appliedPromoCode" value="">
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
          <div class="grid grid-cols-1 gap-8">
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
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?php if ($paymongoEnabled): ?>
                <div class="flex items-center p-4 rounded-lg cursor-pointer tier-card payment-method-container ginto-pay-option" data-radio="ginto-pay">
                  <input type="radio" name="payment_method" id="ginto-pay" value="ginto_pay" class="h-5 w-5" style="accent-color: var(--primary-500);">
                  <label for="ginto-pay" class="ml-3 flex items-center gap-2 font-medium" style="color: var(--text-primary);">
                    <img src="https://ginto.ai/assets/images/ginto.png" alt="Ginto" class="ginto-pay-logo" loading="lazy" onerror="this.style.display='none'">
                    <i class="fas fa-credit-card text-lg" style="color: #6366f1;"></i>
                    <span class="payment-method-copy">
                      <span class="payment-method-title">Ginto Pay</span>
                      <span class="payment-method-meta meta-gold">Fast Card Checkout</span>
                    </span>
                  </label>
                </div>
                <div class="flex items-center p-4 rounded-lg cursor-pointer tier-card payment-method-container" data-radio="paymongo-qrph">
                  <input type="radio" name="payment_method" id="paymongo-qrph" value="paymongo_qrph" class="h-5 w-5" style="accent-color: var(--primary-500);">
                  <label for="paymongo-qrph" class="ml-3 flex items-center gap-2 font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-qrcode text-lg" style="color: #f97316;"></i>
                    <span class="payment-method-copy">
                      <span class="payment-method-title">QR Pay - 1 Month</span>
                      <span class="payment-method-meta meta-orange">InstaPay / PESONet</span>
                    </span>
                  </label>
                </div>
                <div class="flex items-center p-4 rounded-lg cursor-pointer tier-card payment-method-container" data-radio="paymongo-qrph-annual">
                  <input type="radio" name="payment_method" id="paymongo-qrph-annual" value="paymongo_qrph_annual" class="h-5 w-5" style="accent-color: var(--primary-500);">
                  <label for="paymongo-qrph-annual" class="ml-3 flex items-center gap-2 font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-qrcode text-lg" style="color: #f97316;"></i>
                    <span class="payment-method-copy">
                      <span class="payment-method-title">QR Pay - 12 Months</span>
                      <span class="payment-method-meta meta-green">One-Time</span>
                    </span>
                  </label>
                </div>
                <?php endif; ?>
                <div class="flex items-center p-4 rounded-lg cursor-pointer tier-card payment-method-container" data-radio="paypal">
                  <input type="radio" name="payment_method" id="paypal" value="paypal" class="h-5 w-5" style="accent-color: var(--primary-500);">
                  <label for="paypal" class="ml-3 flex items-center gap-2 font-medium" style="color: var(--text-primary);">
                    <img src="https://www.paypalobjects.com/webstatic/mktg/Logo/pp-logo-100px.png" alt="PayPal" class="h-5">
                    <span class="sr-only">PayPal</span>
                  </label>
                </div>
                <div class="flex items-center p-4 rounded-lg cursor-pointer tier-card payment-method-container" data-radio="credit-card">
                  <input type="radio" name="payment_method" id="credit-card" value="credit_card" class="h-5 w-5" style="accent-color: var(--primary-500);">
                  <label for="credit-card" class="ml-3 flex items-center gap-2 font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-credit-card text-lg" style="color: var(--primary-500);"></i>
                    <span class="payment-method-title">Credit/Debit Card</span>
                  </label>
                </div>
                <div class="flex items-center p-4 rounded-lg cursor-pointer tier-card payment-method-container" data-radio="gcash">
                  <input type="radio" name="payment_method" id="gcash" value="gcash" class="h-5 w-5" style="accent-color: var(--primary-500);">
                  <label for="gcash" class="ml-3 block font-medium" style="color: var(--text-primary);">
                    <span class="inline-flex items-center gap-2">
                      <img src="https://www.gcash.com/wp-content/uploads/2019/04/gcash-logo.png" alt="GCash" class="h-5" onerror="this.style.display='none'">
                      <span class="payment-method-title">GCash</span>
                    </span>
                  </label>
                </div>
                <div class="flex items-center p-4 rounded-lg cursor-pointer tier-card payment-method-container" data-radio="bank-transfer">
                  <input type="radio" name="payment_method" id="bank-transfer" value="bank_transfer" class="h-5 w-5" style="accent-color: var(--primary-500);">
                  <label for="bank-transfer" class="ml-3 flex items-center gap-2 font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-university text-lg" style="color: var(--primary-500);"></i>
                    <span class="payment-method-title">Bank Transfer / Deposit</span>
                  </label>
                </div>
                <div class="flex items-center p-4 rounded-lg cursor-pointer tier-card payment-method-container" data-radio="crypto-usdt">
                  <input type="radio" name="payment_method" id="crypto-usdt" value="crypto_usdt_bep20" class="h-5 w-5" style="accent-color: var(--primary-500);">
                  <label for="crypto-usdt" class="ml-3 flex items-center gap-2 font-medium" style="color: var(--text-primary);">
                    <i class="fab fa-bitcoin text-lg" style="color: #f0b90b;"></i>
                    <span class="payment-method-copy">
                      <span class="payment-method-title">Crypto USDT</span>
                      <span class="payment-method-meta meta-yellow">BEP20</span>
                    </span>
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
            </div>
            <div>
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
                      <strong>CRITICAL:</strong> Only send <strong>USDT</strong> via <strong>BNB Smart Chain (BEP20)</strong>. Other networks = <strong>permanent loss!</strong> By proceeding, you acknowledge this is <strong>at your own risk</strong> and <strong>Ginto is not liable</strong> for funds sent on the wrong network.
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
              
              <?php if ($paymongoEnabled): ?>
              <!-- PayMongo QRPH Payment Details -->
              <div id="paymongo-qrph-details" class="mt-4 hidden">
                <div class="p-4 rounded-lg" style="background: linear-gradient(135deg, #1a1a2e 0%, #0f3460 100%); color: white; border: 2px solid #f97316;">
                  <h5 class="font-semibold text-base mb-3 flex items-center justify-center gap-2">
                    <i class="fas fa-qrcode" style="color: #f97316;"></i>
                    <span>QRPH Payment</span>
                    <span class="text-xs px-2 py-0.5 rounded font-bold" style="background: #f97316; color: #fff;">InstaPay / PESONet</span>
                  </h5>
                  
                  <p class="text-xs text-center opacity-80 mb-4">Scan the QR code below using any bank app, GCash, Maya, or any QR Ph-enabled app.</p>
                  
                  <!-- Loading State -->
                  <div id="paymongo-qrph-loading" class="text-center py-6">
                    <div class="animate-spin w-8 h-8 border-4 border-orange-500 border-t-transparent rounded-full mx-auto mb-3"></div>
                    <p class="text-sm opacity-80">Generating QR code...</p>
                  </div>
                  
                  <!-- QR Code Display -->
                  <div id="paymongo-qrph-qr" class="hidden">
                    <!-- QR Image -->
                    <div class="flex justify-center mb-4">
                      <div class="bg-white p-3 rounded-lg shadow-lg">
                        <img id="paymongo-qr-image" src="" alt="QRPH QR Code" class="w-48 h-48 object-contain">
                      </div>
                    </div>
                    
                    <!-- Amount -->
                    <div class="bg-white/10 backdrop-blur rounded p-3 mb-3 text-center">
                      <p class="text-xs opacity-70 mb-0.5">Amount to Pay</p>
                      <p class="font-bold text-xl text-orange-300" id="paymongo-qr-amount">₱0</p>
                    </div>
                    
                    <!-- Status Polling -->
                    <div id="paymongo-status-banner" class="text-center py-2 px-3 rounded mb-3 text-sm font-medium" style="background: rgba(249,115,22,0.2);">
                      <i class="fas fa-clock mr-1 text-orange-300"></i>
                      <span id="paymongo-status-text">Waiting for payment...</span>
                    </div>
                    
                    <!-- Refresh QR button + Download -->
                    <div class="text-center flex items-center justify-center gap-4">
                      <button type="button" id="paymongo-refresh-qr" class="text-xs text-orange-300 hover:text-orange-100 underline">
                        <i class="fas fa-sync-alt mr-1"></i> Refresh QR Code
                      </button>
                      <a id="paymongo-download-qr" download="qrph-payment.png" class="text-xs text-orange-300 hover:text-orange-100 underline cursor-pointer">
                        <i class="fas fa-download mr-1"></i> Download QR
                      </a>
                    </div>
                  </div>
                  
                  <!-- Error State -->
                  <div id="paymongo-qrph-error" class="hidden bg-red-500/30 border border-red-400 rounded p-3 text-center text-sm">
                    <i class="fas fa-exclamation-circle text-red-300 mb-2 block text-xl"></i>
                    <span id="paymongo-qrph-error-msg">Failed to generate QR code. Please try again.</span>
                    <br>
                    <button type="button" id="paymongo-retry-btn" class="mt-2 px-4 py-1 rounded text-xs font-bold" style="background: #f97316; color: #fff;">
                      <i class="fas fa-redo mr-1"></i> Retry
                    </button>
                  </div>
                  
                  <!-- Payment Confirmed State -->
                  <div id="paymongo-qrph-confirmed" class="hidden text-center py-4">
                    <i class="fas fa-check-circle text-green-400 text-4xl mb-3 block"></i>
                    <p class="font-bold text-green-300 text-lg mb-1">Payment Confirmed!</p>
                    <p class="text-sm opacity-80">Creating your account...</p>
                  </div>
                </div>
                
                <!-- Confirm QRPH Payment Button (shown after QR is paid) -->
                <button type="button" id="confirm-paymongo-payment" class="w-full mt-4 font-semibold py-3 px-6 rounded-lg flex items-center justify-center gap-2 hidden" style="background: linear-gradient(90deg, #f97316 0%, #fb923c 100%); color: #fff;">
                  <i class="fas fa-check-circle"></i> Complete Registration
                </button>
              </div>
              <?php endif; ?>

              <?php if ($paymongoEnabled): ?>
              <!-- Ginto Pay (Card) Panel -->
              <div id="ginto-pay-details" class="mt-4 hidden">
                <div class="p-4 rounded-lg" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); color: white; border: 2px solid #6366f1;">
                  <h5 class="font-semibold text-base mb-3 flex items-center justify-center gap-2">
                    <i class="fas fa-credit-card" style="color: #a5b4fc;"></i>
                    <span>Ginto Pay</span>
                    <span class="text-xs px-2 py-0.5 rounded font-bold" style="background: #6366f1; color: #fff;">Credit / Debit Card</span>
                  </h5>
                  <p class="text-xs text-center opacity-80 mb-4">Pay securely with your card. If OTP is required, verification opens in-page.</p>

                  <div class="bg-white/10 backdrop-blur rounded p-3 mb-4 text-center">
                    <p class="text-xs opacity-70 mb-0.5">Amount to Pay</p>
                    <p class="font-bold text-xl text-indigo-300" id="ginto-pay-amount">₱0</p>
                    <p class="text-xs opacity-60 mt-1" id="ginto-pay-duration-label">1-Month Subscription</p>
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                    <div>
                      <label class="block text-xs mb-1 opacity-80">Card Number</label>
                      <input type="text" id="ginto-card-number" class="w-full px-3 py-2 rounded" placeholder="4240 3204 8016 5087" style="color:#111;">
                    </div>
                    <div>
                      <label class="block text-xs mb-1 opacity-80">CVC</label>
                      <input type="text" id="ginto-card-cvc" class="w-full px-3 py-2 rounded" placeholder="123" style="color:#111;">
                    </div>
                    <div>
                      <label class="block text-xs mb-1 opacity-80">Exp Month</label>
                      <input type="text" id="ginto-card-exp-month" class="w-full px-3 py-2 rounded" placeholder="06" style="color:#111;">
                    </div>
                    <div>
                      <label class="block text-xs mb-1 opacity-80">Exp Year</label>
                      <input type="text" id="ginto-card-exp-year" class="w-full px-3 py-2 rounded" placeholder="2030" style="color:#111;">
                    </div>
                    <div>
                      <label class="block text-xs mb-1 opacity-80">Billing Address Line 1</label>
                      <input type="text" id="ginto-billing-line1" class="w-full px-3 py-2 rounded" placeholder="Street address" style="color:#111;">
                    </div>
                    <div>
                      <label class="block text-xs mb-1 opacity-80">Billing City</label>
                      <input type="text" id="ginto-billing-city" class="w-full px-3 py-2 rounded" placeholder="City" style="color:#111;">
                    </div>
                    <div>
                      <label class="block text-xs mb-1 opacity-80">Billing State/Province</label>
                      <input type="text" id="ginto-billing-state" class="w-full px-3 py-2 rounded" placeholder="State" style="color:#111;">
                    </div>
                    <div>
                      <label class="block text-xs mb-1 opacity-80">Billing Postal Code</label>
                      <input type="text" id="ginto-billing-postal" class="w-full px-3 py-2 rounded" placeholder="9000" style="color:#111;">
                    </div>
                  </div>

                  <button type="button" id="ginto-pay-btn" class="w-full font-semibold py-3 px-6 rounded-lg flex items-center justify-center gap-2" style="background: linear-gradient(90deg, #6366f1 0%, #8b5cf6 100%); color: #fff;">
                    <i class="fas fa-lock mr-1"></i> Pay with Ginto Pay
                  </button>
                  <p id="ginto-pay-status" class="text-xs mt-2 opacity-80 hidden"></p>
                  <p class="text-xs text-center opacity-50 mt-2">
                    <i class="fas fa-shield-alt mr-1"></i> Secured by PayMongo. We never see your card details.
                  </p>
                  <div id="ginto-pay-error" class="hidden mt-3 bg-red-500/30 border border-red-400 rounded p-3 text-center text-sm">
                    <i class="fas fa-exclamation-circle text-red-300 mb-1 block text-lg"></i>
                    <span id="ginto-pay-error-msg">An error occurred. Please try again.</span>
                  </div>
                </div>
              </div>

              <div id="ginto-otp-backdrop" class="hidden fixed inset-0 z-50 items-center justify-center" style="background: rgba(0,0,0,0.75);">
                <div class="w-[96vw] max-w-4xl h-[90vh] rounded-lg overflow-hidden" style="background: #0b1220; border: 1px solid #334155;">
                  <div class="flex items-center justify-between px-3 py-2" style="border-bottom: 1px solid #334155;">
                    <div class="text-sm font-semibold">Complete OTP Verification</div>
                    <div class="flex items-center gap-2">
                      <a id="ginto-otp-open-tab" href="#" target="_blank" rel="noopener" class="text-xs underline" style="color:#93c5fd;">Open in new tab</a>
                      <button type="button" id="ginto-otp-close" class="px-2 py-1 text-xs rounded" style="background:#1e293b;">Close</button>
                    </div>
                  </div>
                  <iframe id="ginto-otp-iframe" title="Ginto OTP" class="w-full h-[calc(90vh-44px)]" style="background:#fff;"></iframe>
                </div>
              </div>
              <?php endif; ?>
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
            // Currency state - default to PHP (Philippines), switch to USD for international
            window.GINTO_CURRENCY = 'PHP';
            window.GINTO_IS_PHILIPPINES = true;
            
            // Function to update all prices based on currency (subscription pricing)
            function updatePriceDisplays(currency) {
              window.GINTO_CURRENCY = currency;
              window.GINTO_IS_PHILIPPINES = (currency === 'PHP');
              
              document.querySelectorAll('.tier-card').forEach(function(card) {
                var firstPhp = parseFloat(card.dataset.tierFirstPhp) || 0;
                var firstUsd = parseFloat(card.dataset.tierFirstUsd) || 0;
                var recurringPhp = parseFloat(card.dataset.tierRecurringPhp) || 0;
                var recurringUsd = parseFloat(card.dataset.tierRecurringUsd) || 0;
                var hasPromo = card.dataset.tierPromo === 'true';
                
                var firstMonthEl = card.querySelector('.first-month-price');
                var recurringEl = card.querySelector('.recurring-price');
                
                if (firstMonthEl) {
                  if (currency === 'PHP') {
                    firstMonthEl.textContent = '₱' + firstPhp.toLocaleString();
                  } else {
                    firstMonthEl.textContent = '$' + firstUsd.toLocaleString();
                  }
                }
                
                // Update recurring price for Starter (only one with different recurring)
                if (recurringEl && hasPromo) {
                  if (currency === 'PHP') {
                    recurringEl.innerHTML = 'then <span class="font-semibold" style="color: var(--primary-500);">₱' + recurringPhp.toLocaleString() + '</span>/month';
                  } else {
                    recurringEl.innerHTML = 'then <span class="font-semibold" style="color: var(--primary-500);">$' + recurringUsd.toLocaleString() + '</span>/month';
                  }
                }
                
                // Hide promo badge for USD (it's PHP-specific amount)
                var promoBadge = card.querySelector('.promo-badge-container');
                if (promoBadge) {
                  if (currency === 'PHP') {
                    promoBadge.style.display = '';
                    promoBadge.querySelector('span').textContent = 'INCLUDES ₱100 PROMO FEE';
                  } else {
                    promoBadge.style.display = '';
                    promoBadge.querySelector('span').textContent = 'INCLUDES $2 PROMO FEE';
                  }
                }
              });
              
              // Update payment summary if visible
              updatePaymentSummaryPrices();
            }
            
            function updatePaymentSummaryPrices() {
              var selectedTier = window.selectedTier;
              if (!selectedTier) return;
              
              var currency = window.GINTO_CURRENCY;
              var firstPrice = currency === 'PHP' ? selectedTier.firstPhp : selectedTier.firstUsd;
              var recurringPrice = currency === 'PHP' ? selectedTier.recurringPhp : selectedTier.recurringUsd;
              var symbol = currency === 'PHP' ? '₱' : '$';
              
              var priceEl = document.getElementById('payment-step-price');
              var totalEl = document.getElementById('payment-total');
              
              if (firstPrice !== recurringPrice) {
                // Starter with promo
                if (priceEl) priceEl.innerHTML = symbol + firstPrice.toLocaleString() + ' <span class="text-sm font-normal">(first month)</span>';
                if (totalEl) totalEl.innerHTML = symbol + firstPrice.toLocaleString() + ' <span class="text-sm font-normal">first, then ' + symbol + recurringPrice.toLocaleString() + '/mo</span>';
              } else {
                if (priceEl) priceEl.textContent = symbol + firstPrice.toLocaleString() + '/month';
                if (totalEl) totalEl.textContent = symbol + firstPrice.toLocaleString() + '/month';
              }
            }
            
            // Country Auto-Detect (like legacy)
            const countrySelect = document.getElementById('countrySelect');
            if (countrySelect) {
              // Listen for country changes to update currency
              countrySelect.addEventListener('change', function() {
                var isPhilippines = this.value === 'PH';
                updatePriceDisplays(isPhilippines ? 'PHP' : 'USD');
              });
              
              if (!countrySelect.value) {
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
                    // Update currency based on detected country
                    updatePriceDisplays(code === 'PH' ? 'PHP' : 'USD');
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
                          updatePriceDisplays(code === 'PH' ? 'PHP' : 'USD');
                        }
                      }
                    })
                    .catch(e => console.warn('All GeoIP lookups failed:', e));
                });
              } else {
                // Country already selected, update prices
                updatePriceDisplays(countrySelect.value === 'PH' ? 'PHP' : 'USD');
              }
            }

            // Numeric-only phone input (like legacy)
            const phoneInput = document.getElementById('phoneInput');
            if (phoneInput) {
              phoneInput.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '');
              });
            }
      // Make showModal globally accessible for PayPal callbacks
      // Optional callback parameter: called when user clicks the button
      // Optional buttonText parameter: custom button text (default: "Got it")
      window.showModal = function(title, message, icon = 'fas fa-exclamation-circle', iconColor = 'text-yellow-500', onClose = null, buttonText = 'Got it') {
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
                ${buttonText}
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
        
        // Only allow background click to close if there's no callback (not a critical action)
        if (!onClose) {
          modal.addEventListener('click', function(e) { 
            if (e.target === modal) modal.remove(); 
          });
        }
      }

      // Wizard State - expose to window for payment handlers
      window.selectedTier = { id: null, name: null, price: null, pricePhp: null, priceUsd: null, firstPhp: null, firstUsd: null, recurringPhp: null, recurringUsd: null };
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
            } else if (paymentMethod.value === 'paymongo_qrph' || paymentMethod.value === 'paymongo_qrph_annual') {
              var qrph = document.getElementById('paymongo-qrph-details');
              if (qrph) {
                qrph.classList.remove('hidden');
                document.getElementById('complete-purchase').classList.add('hidden');
                if (typeof window.initPaymongoQrph === 'function') { window.initPaymongoQrph(); }
              }
            } else if (paymentMethod.value === 'ginto_pay') {
              var gintoPayPanel = document.getElementById('ginto-pay-details');
              if (gintoPayPanel) {
                gintoPayPanel.classList.remove('hidden');
                document.getElementById('complete-purchase').classList.add('hidden');
                updateGintoPayDisplay();
              }
            }
            // Always open the payment modal when reaching step 3 with a method pre-selected
            if (typeof window.openPaymentModal === 'function') window.openPaymentModal(paymentMethod.value);
          }
        }
      }

      // Show promotional confirmation modal
      window.showPromoModal = function(onAgree) {
        const existing = document.getElementById('promo-modal');
        if (existing) existing.remove();

        const modal = document.createElement('div');
        modal.id = 'promo-modal';
        modal.className = 'fixed inset-0 z-[99999] flex items-center justify-center bg-black bg-opacity-50';
        modal.innerHTML = `
          <div class="rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6 animate-fade-in" style="background-color: var(--bg-card);">
            <div class="flex items-start space-x-4 mb-6">
              <div class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0" style="background: linear-gradient(135deg, #ff6a00, #ffe53b);">
                <i class="fas fa-gift text-2xl" style="color: #fff;"></i>
              </div>
              <div>
                <h3 class="text-xl font-bold mb-2" style="color: var(--text-primary);">🎉 Promotional Package!</h3>
                <p style="color: var(--text-secondary);">
                  You are about to grab our <strong>cheapest package</strong> — the Starter Package for <strong>₱250</strong>!
                </p>
                <p class="mt-3" style="color: var(--text-secondary);">
                  By selecting this package, you agree to a <strong>one-time promotional fee</strong> of <strong>₱100</strong> (40% of ₱250), which is <strong>already included</strong> in your ₱250 payment.
                </p>
                <p class="mt-3" style="color: var(--text-secondary);">
                  No hidden charges — what you see is what you pay!
                </p>
              </div>
            </div>
            <div class="mb-4">
              <label class="block font-medium mb-2" style="color: var(--text-primary);">
                <i class="fas fa-tag mr-2" style="color: var(--accent-500);"></i>Have a Promo Code? <span class="text-sm font-normal" style="color: var(--text-secondary);">(optional)</span>
              </label>
              <input type="text" id="promo-code-input" placeholder="Enter promo code" class="w-full px-4 py-2 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary); text-transform: uppercase;">
              <p id="promo-code-message" class="mt-2 text-sm hidden"></p>
            </div>
            <div class="flex justify-end gap-3">
              <button id="promo-modal-cancel" class="font-semibold px-6 py-2 rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border-color);">
                Cancel
              </button>
              <button id="promo-modal-agree" class="btn-gold font-semibold px-6 py-2 rounded-lg">
                <i class="fas fa-check mr-2"></i> I Agree, Select This Package
              </button>
            </div>
          </div>
        `;
        document.body.appendChild(modal);
        
        // Convert promo code to uppercase as user types
        modal.querySelector('#promo-code-input').addEventListener('input', function() {
          this.value = this.value.toUpperCase();
        });
        
        // Handle agree button - validate promo code if entered
        modal.querySelector('#promo-modal-agree').addEventListener('click', async function() {
          const promoInput = modal.querySelector('#promo-code-input');
          const promoMessage = modal.querySelector('#promo-code-message');
          const promoCode = promoInput.value.trim();
          const agreeBtn = this;
          
          // If promo code is entered, validate it first
          if (promoCode) {
            agreeBtn.disabled = true;
            agreeBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Validating...';
            promoMessage.classList.add('hidden');
            
            try {
              // Get CSRF token from global hidden field or form
              const csrfToken = document.getElementById('global-csrf-token')?.value || document.querySelector('input[name="csrf_token"]')?.value || '';
              
              const response = await fetch('/register/promo-code', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                  code: promoCode,
                  package_name: 'Starter',
                  package_amount: 250,
                  csrf_token: csrfToken
                })
              });
              const result = await response.json();
              
              if (result.valid) {
                // Promo code is valid - store it and show expiration info
                let successMsg = result.message || 'Promo code applied!';
                
                // Add expiration warning if there's an expiry date
                if (result.valid_until) {
                  const expiryDate = new Date(result.valid_until);
                  const formattedDate = expiryDate.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                  });
                  successMsg += `<br><span class="text-yellow-500"><i class="fas fa-clock mr-1"></i>This promo code expires on ${formattedDate}</span>`;
                }
                
                promoMessage.innerHTML = successMsg;
                promoMessage.className = 'mt-2 text-sm text-green-500';
                promoMessage.classList.remove('hidden');
                promoInput.style.borderColor = '#22c55e';
                promoInput.disabled = true;
                
                // Store promo code for form submission
                window.appliedPromoCode = promoCode;
                
                // Change button to "I Understand" and enable it
                agreeBtn.disabled = false;
                agreeBtn.innerHTML = '<i class="fas fa-check mr-2"></i> I Understand';
                agreeBtn.onclick = function() {
                  modal.remove();
                  if (typeof onAgree === 'function') {
                    onAgree();
                  }
                };
              } else {
                // Invalid promo code
                promoMessage.textContent = result.error || 'Invalid promo code';
                promoMessage.className = 'mt-2 text-sm text-red-500';
                promoMessage.classList.remove('hidden');
                promoInput.style.borderColor = '#ef4444';
                agreeBtn.disabled = false;
                agreeBtn.innerHTML = '<i class="fas fa-check mr-2"></i> I Agree, Select This Package';
              }
            } catch (err) {
              promoMessage.textContent = 'Error validating promo code. Please try again.';
              promoMessage.className = 'mt-2 text-sm text-red-500';
              promoMessage.classList.remove('hidden');
              agreeBtn.disabled = false;
              agreeBtn.innerHTML = '<i class="fas fa-check mr-2"></i> I Agree, Select This Package';
            }
          } else {
            // No promo code entered - Starter package requires a promo code
            // Show error message but keep modal open - user must enter code or cancel
            promoMessage.textContent = "We're so sorry, this package is not available without a promo code. Please enter a valid promo code or choose a higher package.";
            promoMessage.className = 'mt-2 text-sm text-red-500';
            promoMessage.classList.remove('hidden');
            promoInput.style.borderColor = '#ef4444';
            promoInput.focus();
          }
        });
        
        // Handle cancel button
        modal.querySelector('#promo-modal-cancel').addEventListener('click', function() {
          modal.remove();
        });
        
        // Handle background click (cancel)
        modal.addEventListener('click', function(e) { 
          if (e.target === modal) modal.remove(); 
        });
      };

      // Function to complete tier selection
      function completeTierSelection(card) {
        // Remove selected state from all tier cards (not payment methods)
        document.querySelectorAll('.tier-card:not(.payment-method-container)').forEach(c => {
          c.classList.remove('selected');
        });
        // Add selected state to clicked card
        card.classList.add('selected');
        
        // Get subscription prices from data attributes
        var firstPhp = parseFloat(card.dataset.tierFirstPhp) || 0;
        var firstUsd = parseFloat(card.dataset.tierFirstUsd) || 0;
        var recurringPhp = parseFloat(card.dataset.tierRecurringPhp) || 0;
        var recurringUsd = parseFloat(card.dataset.tierRecurringUsd) || 0;
        var currentCurrency = window.GINTO_CURRENCY || 'PHP';
        
        window.selectedTier.id = card.dataset.tierId;
        window.selectedTier.name = card.dataset.tierName;
        window.selectedTier.firstPhp = firstPhp;
        window.selectedTier.firstUsd = firstUsd;
        window.selectedTier.recurringPhp = recurringPhp;
        window.selectedTier.recurringUsd = recurringUsd;
        // Legacy compatibility
        window.selectedTier.pricePhp = firstPhp;
        window.selectedTier.priceUsd = firstUsd;
        window.selectedTier.price = currentCurrency === 'PHP' ? firstPhp : firstUsd;
        
        // Update hidden form fields for registration
        document.getElementById('selectedPackage').value = window.selectedTier.name;
        // Store first month PHP price for backend (consistent currency in DB)
        document.getElementById('selectedPackageAmount').value = firstPhp;
        document.getElementById('selectedPackageCurrency').value = 'PHP';
        
        // Store promo code in hidden field if applied (for Starter with promo)
        const promoField = document.getElementById('appliedPromoCode');
        if (promoField) {
          promoField.value = window.appliedPromoCode || '';
        }
        
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
      }

      // Tier Selection - make entire card clickable
      document.querySelectorAll('.tier-card').forEach(card => {
        // Skip payment method containers
        if (card.classList.contains('payment-method-container')) return;
        
        card.style.cursor = 'pointer';
        card.addEventListener('click', function(e) {
          // Check if this is a promotional tier
          if (card.dataset.tierPromo === 'true') {
            // Show promo modal and wait for user agreement
            showPromoModal(function() {
              completeTierSelection(card);
            });
          } else {
            // Regular tier selection - clear any previously applied promo code
            window.appliedPromoCode = null;
            completeTierSelection(card);
          }
        });
      });

      // Next Buttons
      document.querySelectorAll('.next-step').forEach(btn => {
        btn.addEventListener('click', function() {
          const step = parseInt(this.closest('.wizard-content').dataset.step);
          
          if (step === 1) {
            if (!window.selectedTier.id) {
              showModal('Please Select a Tier', 'Choose a membership tier before proceeding.', 'fas fa-hand-pointer');
              return;
            }
            // Display price in user's currency
            var currency = window.GINTO_CURRENCY || 'PHP';
            var displayPrice = currency === 'PHP' 
              ? '₱' + parseFloat(window.selectedTier.pricePhp).toLocaleString()
              : '$' + parseFloat(window.selectedTier.priceUsd).toLocaleString();
            document.getElementById('selected-tier').textContent = window.selectedTier.name;
            document.getElementById('payment-step-price').textContent = displayPrice;
            document.getElementById('payment-total').textContent = displayPrice;
          }
          
          if (step === 2) {
            const inputs = document.querySelectorAll('.wizard-content[data-step="2"] input:not([type="hidden"])');
            let valid = true;
            inputs.forEach(input => {
              if (!input.value.trim()) {
                valid = false;
                input.style.borderColor = '#ef4444';
              } else {
                input.style.borderColor = 'var(--border-color)';
              }
            });
            
            // Also check country select
            const countrySelect = document.getElementById('countrySelect');
            if (countrySelect && !countrySelect.value) {
              valid = false;
              countrySelect.style.borderColor = '#ef4444';
            } else if (countrySelect) {
              countrySelect.style.borderColor = 'var(--border-color)';
            }
            
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
        // PayMongo uses its own confirm button — block standard form submit
        if (payment.value === 'paymongo_qrph') {
          e.preventDefault();
          showModal('Scan QR to Pay', 'Please scan the QRPH QR code using GCash, Maya, or your bank app and wait for payment confirmation before completing registration.', 'fas fa-qrcode', 'text-orange-500');
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
            showModal(
              'Registration Successful', 
              data.message || 'You have registered successfully! Click below to proceed to login.', 
              'fas fa-check-circle', 
              'text-green-500',
              function() { window.location.href = '/login'; },
              'Proceed to Login'
            );
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
  var currentTierPriceUsd = 20;
  var currentTierName = 'Basic';
  
  function updatePayPalAmount(tierName, pricePhp, priceUsd) {
    currentTierPrice = parseFloat(pricePhp);
    currentTierPriceUsd = parseFloat(priceUsd);
    currentTierName = tierName;
  }
  
  function getSelectedTier() {
    var tierCard = document.querySelector('.tier-card.selected');
    if (tierCard) {
      var firstPhp = parseFloat(tierCard.dataset.tierFirstPhp) || 0;
      var firstUsd = parseFloat(tierCard.dataset.tierFirstUsd) || 0;
      var recurringPhp = parseFloat(tierCard.dataset.tierRecurringPhp) || 0;
      var recurringUsd = parseFloat(tierCard.dataset.tierRecurringUsd) || 0;
      return {
        id: tierCard.dataset.tierId,
        name: tierCard.dataset.tierName,
        firstPhp: firstPhp,
        firstUsd: firstUsd,
        recurringPhp: recurringPhp,
        recurringUsd: recurringUsd,
        // Legacy compatibility
        price: firstPhp,
        pricePhp: firstPhp,
        priceUsd: firstUsd
      };
    }
    return null;
  }
  
  // Make showPayPalButton globally accessible
  window.showPayPalButton = showPayPalButton;
  // Make getSelectedTier globally accessible (used by PayMongo and other handlers)
  window.getSelectedTier = getSelectedTier;
  
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
    var paymongoDetails = document.getElementById('paymongo-qrph-details');
    var gintoPayDetails = document.getElementById('ginto-pay-details');
    
    if (paypalContainer) paypalContainer.classList.add('hidden');
    if (cardContainer) cardContainer.classList.add('hidden');
    if (paypalLoading) paypalLoading.classList.add('hidden');
    if (paypalError) paypalError.classList.add('hidden');
    if (gcashDetails) gcashDetails.classList.add('hidden');
    if (bankDetails) bankDetails.classList.add('hidden');
    if (cryptoDetails) cryptoDetails.classList.add('hidden');
    if (paymongoDetails) paymongoDetails.classList.add('hidden');
    if (gintoPayDetails) gintoPayDetails.classList.add('hidden');
  };

  // -------------------------------------------------------
  // Payment Modal — only X button closes it
  // -------------------------------------------------------
  var PAYMENT_METHOD_INFO = {
    'paypal':               { icon: 'fab fa-paypal',      color: '#0070e0', label: 'PayPal'        },
    'credit_card':          { icon: 'fas fa-credit-card', color: '#0070e0', label: 'Card'          },
    'gcash':                { icon: 'fas fa-mobile-alt',  color: '#007dfe', label: 'GCash'         },
    'bank_transfer':        { icon: 'fas fa-university',  color: '#2c5282', label: 'Bank'          },
    'crypto_usdt_bep20':    { icon: 'fab fa-bitcoin',     color: '#f0b90b', label: 'Crypto'        },
    'paymongo_qrph':        { icon: 'fas fa-qrcode',      color: '#f97316', label: 'QR 1 Month'   },
    'paymongo_qrph_annual': { icon: 'fas fa-qrcode',      color: '#16a34a', label: 'QR 12 Months' },
    'ginto_pay':            { icon: 'fas fa-credit-card', color: '#d4af37', label: 'Ginto Pay', isGold: true }
  };

  function buildPaymentModal() {
    if (document.getElementById('payment-modal')) return;
    var modal = document.createElement('div');
    modal.id = 'payment-modal';
    modal.style.cssText = 'position:fixed;inset:0;z-index:500;display:none;background:rgba(0,0,0,0.82);overflow-y:auto;';
    modal.innerHTML =
      '<div class="payment-modal-shell">' +
        '<div class="payment-modal-card">' +
          '<div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid var(--border-color);">' +
            '<div style="display:flex;align-items:center;gap:0.6rem;">' +
              '<span id="pmodal-icon" style="font-size:1.5rem;line-height:1;"></span>' +
              '<div>' +
                '<h3 id="pmodal-title" style="font-weight:700;font-size:0.95rem;color:var(--text-primary);margin:0;line-height:1.2;"></h3>' +
                '<p style="font-size:0.7rem;color:var(--text-secondary);margin:0.15rem 0 0;">Choose a secure checkout method</p>' +
              '</div>' +
            '</div>' +
            '<button type="button" id="pmodal-close" title="Close" style="width:2rem;height:2rem;display:flex;align-items:center;justify-content:center;border-radius:50%;border:none;cursor:pointer;font-size:0.9rem;background:var(--bg-secondary);color:var(--text-secondary);flex-shrink:0;">' +
              '<i class="fas fa-times"></i>' +
            '</button>' +
          '</div>' +
          '<div id="pmodal-tabs" class="payment-modal-tabs"></div>' +
          '<div id="pmodal-body" style="overflow-y:auto;padding:1rem;"></div>' +
        '</div>' +
      '</div>';
    document.body.appendChild(modal);
    // Move all payment panels into modal body (no HTML clone — live DOM move)
    var body = document.getElementById('pmodal-body');
    ['paypal-button-container','card-button-container','paypal-loading','paypal-error',
      'gcash-details','bank-transfer-details','crypto-usdt-details',
      'paymongo-qrph-details','confirm-paymongo-payment','ginto-pay-details'
    ].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) body.appendChild(el);
    });
    // Only X closes the modal
    document.getElementById('pmodal-close').addEventListener('click', window.closePaymentModal);
  }

  window.openPaymentModal = function(value) {
    buildPaymentModal();
    var info = PAYMENT_METHOD_INFO[value] || { icon: 'fas fa-credit-card', color: 'var(--primary-500)', label: 'Payment' };
    var iconEl = document.getElementById('pmodal-icon');
    var titleEl = document.getElementById('pmodal-title');
    if (iconEl) {
      if (value === 'ginto_pay') {
        iconEl.innerHTML = '<span style="display:inline-flex;align-items:center;gap:0.35rem;">' +
          '<img src="https://ginto.ai/assets/images/ginto.png" alt="Ginto" class="ginto-pay-logo" onerror="this.style.display=\'none\'">' +
          '<i class="' + info.icon + '" style="color:#d4af37"></i>' +
        '</span>';
      } else {
        iconEl.innerHTML = '<i class="' + info.icon + '" style="color:' + info.color + '"></i>';
      }
    }
    if (titleEl) titleEl.textContent = info.label;
    // Rebuild method thumbnail tabs
    var tabs = document.getElementById('pmodal-tabs');
    if (tabs) {
      tabs.innerHTML = '';
      document.querySelectorAll('input[name="payment_method"]').forEach(function(radio) {
        var mi = PAYMENT_METHOD_INFO[radio.value];
        if (!mi) return;
        var active = radio.value === value;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'payment-modal-tab' + (active ? ' active' : '');
        if (radio.value === 'ginto_pay') {
          btn.classList.add('ginto-pay-tab');
        }
        btn.style.borderColor = active ? mi.color : 'var(--border-color)';
        if (active) {
          if (radio.value !== 'ginto_pay') {
            btn.style.background = 'linear-gradient(135deg, ' + mi.color + ', #111827)';
          }
        }
        if (radio.value === 'ginto_pay') {
          btn.innerHTML = '<img src="https://ginto.ai/assets/images/ginto.png" alt="Ginto" class="ginto-pay-logo" onerror="this.style.display=\'none\'">' +
            '<i class="' + mi.icon + '"></i><span>' + mi.label + '</span>';
        } else {
          btn.innerHTML = '<i class="' + mi.icon + '"></i><span>' + mi.label + '</span>';
        }
        btn.addEventListener('click', function() {
          radio.checked = true;
          radio.dispatchEvent(new Event('change', { bubbles: true }));
        });
        tabs.appendChild(btn);
      });
    }
    var modal = document.getElementById('payment-modal');
    modal.style.display = 'block';
    var body = document.getElementById('pmodal-body');
    if (body) body.scrollTop = 0;
  };

  window.closePaymentModal = function() {
    var modal = document.getElementById('payment-modal');
    if (modal) modal.style.display = 'none';
    window.hideAllPaymentSections();
    document.querySelectorAll('input[name="payment_method"]').forEach(function(r) { r.checked = false; });
    var sp = document.getElementById('selectedPayMethod');
    if (sp) sp.value = '';
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
      formData.set('package_amount', tier ? tier.pricePhp : currentTierPrice);
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
      
      // Use window.selectedTier if available, otherwise use getSelectedTier(), then fall back to form values
      var tier = (window.selectedTier && window.selectedTier.id) ? window.selectedTier : getSelectedTier();
      var currentTierName = formData.get('package') || 'Professional';
      var currentTierPrice = parseFloat(formData.get('package_amount')) || 1000;
      
      // Use tier data if available, otherwise use form values
      var finalPackage = (tier && tier.name) ? tier.name : currentTierName;
      var finalAmount = (tier && tier.pricePhp) ? parseFloat(tier.pricePhp) : currentTierPrice;
      
      formData.set('package', finalPackage);
      formData.set('package_amount', finalAmount);
      formData.set('package_currency', 'PHP');
      formData.set('pay_method', 'gcash');
      formData.set('payment_method', 'gcash');
      formData.set('gcash_reference', referenceInput.value.trim());
      formData.set('payment_status', 'pending'); // GCash payments are pending until verified
      
      // Debug log the submission data
      console.log('GCash payment submission:', {
        package: finalPackage,
        package_amount: finalAmount,
        tier: tier
      });
      
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
      // USDT is dollar-denominated, use USD price
      formData.set('package_amount', tier ? tier.priceUsd : currentTierPriceUsd);
      formData.set('package_currency', 'USD');
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
      } else if (this.value === 'paymongo_qrph' || this.value === 'paymongo_qrph_annual') {
        // Show PayMongo QRPH details
        var qrphDetails = document.getElementById('paymongo-qrph-details');
        if (qrphDetails) {
          qrphDetails.classList.remove('hidden');
          // Initialize QRPH (reads selected radio value to determine 1m vs 12m)
          if (typeof initPaymongoQrph === 'function') {
            initPaymongoQrph();
          }
        }
        // Don't show complete-purchase - PayMongo has its own confirm button
      } else if (this.value === 'ginto_pay') {
        // Show Ginto Pay (card) details
        var gintoPayDetails = document.getElementById('ginto-pay-details');
        if (gintoPayDetails) {
          gintoPayDetails.classList.remove('hidden');
          if (typeof window.updateGintoPayDisplay === 'function') {
            window.updateGintoPayDisplay();
          }
        }
        // Don't show complete-purchase - Ginto Pay redirects externally
      }
      // Show payment modal (only X button closes it)
      if (typeof window.openPaymentModal === 'function') window.openPaymentModal(this.value);
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
      updatePayPalAmount(tier.name, tier.pricePhp, tier.priceUsd);
    }
    
    try {
      // Get the PayPal plan ID for the selected tier
      var planId = window.GINTO_PAYPAL_PLANS[currentTierName] || '';
      
      if (!planId) {
        console.error('No PayPal plan ID for tier:', currentTierName);
        errorDiv.classList.remove('hidden');
        document.getElementById('paypal-error-message').textContent = 'Payment not available for this tier. Please contact support.';
        loading.classList.add('hidden');
        return;
      }
      
      var buttonConfig = {
        style: {
          shape: 'rect',
          color: fundingType === 'card' ? 'black' : 'gold',
          layout: 'vertical',
          label: 'subscribe'
        },
        createSubscription: function(data, actions) {
          var form = document.getElementById('wizardRegisterForm');
          var email = form.querySelector('input[name="email"]').value;
          var username = form.querySelector('input[name="username"]').value;
          
          if (!email || !username) {
            errorDiv.classList.remove('hidden');
            document.getElementById('paypal-error-message').textContent = 'Please complete Steps 1 & 2 first.';
            throw new Error('Form incomplete');
          }
          
          // CRITICAL: Validate username/email availability BEFORE creating subscription
          // This prevents charging users for subscriptions that can't complete registration
          return fetch('/api/validate-registration', {
            method: 'POST',
            headers: { 
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ username: username, email: email })
          })
          .then(function(response) {
            if (!response.ok) {
              throw new Error('Validation service unavailable. Please try again.');
            }
            return response.json();
          })
          .then(function(result) {
            if (!result.available) {
              errorDiv.classList.remove('hidden');
              document.getElementById('paypal-error-message').textContent = result.message || 'Username or email already taken.';
              throw new Error(result.message || 'Username or email already taken');
            }
            
            // Get current plan ID (might have changed if user switched tiers)
            var currentPlanId = window.GINTO_PAYPAL_PLANS[currentTierName] || planId;
            console.log('Creating subscription with plan:', currentPlanId, 'for tier:', currentTierName);
            
            return actions.subscription.create({
              plan_id: currentPlanId,
              application_context: {
                brand_name: 'Ginto',
                user_action: 'SUBSCRIBE_NOW',
                shipping_preference: 'NO_SHIPPING'
              }
            });
          })
          .catch(function(err) {
            console.error('Pre-validation or subscription creation error:', err);
            errorDiv.classList.remove('hidden');
            document.getElementById('paypal-error-message').textContent = err.message || 'Could not start subscription. Please try again.';
            throw err; // Re-throw to let PayPal handle it
          });
        },
        onApprove: function(data, actions) {
          // Subscription approved - data contains subscriptionID
          var subscriptionID = data.subscriptionID;
          
          var form = document.getElementById('wizardRegisterForm');
          var formData = new FormData(form);
          
          var tier = getSelectedTier();
          var pricing = window.GINTO_PRICING[currentTierName] || {};
          
          // Store first month price in database
          formData.set('package', tier ? tier.name : currentTierName);
          formData.set('package_amount', pricing.firstMonth ? pricing.firstMonth.php : (tier ? tier.pricePhp : currentTierPrice));
          formData.set('package_currency', 'PHP');
          formData.set('pay_method', payMethodValue);
          formData.append('paypal_subscription_id', subscriptionID);
          formData.append('paypal_payment_status', 'SUBSCRIPTION_ACTIVE');
          formData.append('payment_method', payMethodValue);
          formData.append('payment_type', 'subscription');
          // Store USD prices for reconciliation
          formData.append('paypal_usd_first_month', pricing.firstMonth ? pricing.firstMonth.usd : currentTierPriceUsd);
          formData.append('paypal_usd_recurring', pricing.recurring ? pricing.recurring.usd : currentTierPriceUsd);
          
          var successContainer = fundingType === 'card' ? 'card-button-container' : 'paypal-button-container';
          document.getElementById(successContainer).innerHTML = 
            '<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-blue-500 text-3xl mb-2"></i><p class="text-blue-600 font-semibold">Subscription Active</p><p class="text-sm text-gray-500">Completing registration...</p></div>';
          
          // Register user with subscription ID
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
              document.getElementById(successContainer).innerHTML = 
                '<div class="text-center py-4"><i class="fas fa-check-circle text-green-500 text-3xl mb-2"></i><p class="text-green-600 font-semibold">Welcome to Ginto!</p></div>';
              
              // Show subscription success modal
              var recurringPrice = pricing.recurring 
                ? (window.GINTO_IS_PHILIPPINES ? '₱' + pricing.recurring.php : '$' + pricing.recurring.usd)
                : '';
              
              window.showModal(
                'Subscription Active!', 
                'Your subscription is now active. You will be charged ' + recurringPrice + '/month starting next month. Enjoy your Ginto membership!', 
                'fas fa-check-circle', 
                'text-green-500',
                function() { window.location.href = '/login'; },
                'Proceed to Login'
              );
            } else {
              // Registration failed - but subscription was created in PayPal
              document.getElementById(successContainer).innerHTML = 
                '<div class="text-center py-4"><i class="fas fa-exclamation-triangle text-yellow-500 text-3xl mb-2"></i><p class="text-yellow-600 font-semibold">Registration Issue</p></div>';
              window.showModal('Registration Issue', 'Your subscription was created but registration failed: ' + (result.message || 'Unknown error') + '. Please contact support with Subscription ID: ' + subscriptionID, 'fas fa-exclamation-triangle', 'text-yellow-500');
            }
          })
          .catch(function(error) {
            console.error('Registration error:', error);
            window.showModal('Network Error', 'Could not complete registration. Please contact support with Subscription ID: ' + subscriptionID, 'fas fa-exclamation-triangle', 'text-red-500');
          });
        },
        onError: function(err) {
          console.error('PayPal error:', err);
          errorDiv.classList.remove('hidden');
          document.getElementById('paypal-error-message').textContent = 'Payment failed. Please try again.';
        },
        onCancel: function() {
          console.log('Subscription cancelled by user');
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
</script>

<?php if ($paymongoEnabled): ?>
<script>
(function() {
  'use strict';

  // PayMongo QRPH Payment Handler
  var paymongoQrphInit = false;      // Has QR been generated?
  var paymongoQrphPaid = false;      // Has payment been confirmed?
  var paymongoPollingTimer = null;
  var paymongoCurrentPiId = null;
  var paymongoCurrentDuration = null; // '1m' or '12m' — regenerate if changed
  var PAYMONGO_POLL_INTERVAL = 3000; // 3 seconds

  function getEl(id) { return document.getElementById(id); }

  function showQrphSection(sectionId) {
    ['paymongo-qrph-loading', 'paymongo-qrph-qr', 'paymongo-qrph-error', 'paymongo-qrph-confirmed'].forEach(function(id) {
      var el = getEl(id);
      if (el) el.classList.add('hidden');
    });
    var target = getEl(sectionId);
    if (target) target.classList.remove('hidden');
  }

  function stopPolling() {
    if (paymongoPollingTimer) {
      clearInterval(paymongoPollingTimer);
      paymongoPollingTimer = null;
    }
  }

  function startPolling(piId) {
    stopPolling();
    paymongoPollingTimer = setInterval(function() {
      if (!piId) { stopPolling(); return; }

      fetch('/api/payments/paymongo-qrph-status?pi_id=' + encodeURIComponent(piId), {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!data.success) {
          console.warn('PayMongo status check failed:', data.message);
          return;
        }

        if (data.paid) {
          stopPolling();
          paymongoQrphPaid = true;

          // Update status banner
          var banner = getEl('paymongo-status-banner');
          if (banner) {
            banner.style.background = 'rgba(34,197,94,0.2)';
            banner.innerHTML = '<i class="fas fa-check-circle mr-1 text-green-400"></i><span class="text-green-300 font-bold">Payment Received!</span>';
          }

          // Show confirmed state and complete button
          showQrphSection('paymongo-qrph-confirmed');
          var confirmBtn = getEl('confirm-paymongo-payment');
          if (confirmBtn) {
            confirmBtn.classList.remove('hidden');
            confirmBtn.disabled = false;
          }
        }
      })
      .catch(function(err) {
        console.error('PayMongo poll error:', err);
      });
    }, PAYMONGO_POLL_INTERVAL);
  }

  window.initPaymongoQrph = function() {
    if (paymongoQrphPaid) {
      // Already paid — show confirmed state
      showQrphSection('paymongo-qrph-confirmed');
      var confirmBtn = getEl('confirm-paymongo-payment');
      if (confirmBtn) { confirmBtn.classList.remove('hidden'); confirmBtn.disabled = false; }
      return;
    }
    // Determine what duration we need now
    var selectedMethodNow = document.querySelector('input[name="payment_method"]:checked');
    var wantDuration = (selectedMethodNow && selectedMethodNow.value === 'paymongo_qrph_annual') ? '12m' : '1m';

    if (paymongoQrphInit && paymongoCurrentPiId && paymongoCurrentDuration === wantDuration) {
      // QR already generated for same duration — show it and resume polling
      showQrphSection('paymongo-qrph-qr');
      startPolling(paymongoCurrentPiId);
      return;
    }

    // Different duration (or first time) — reset and regenerate
    if (paymongoQrphInit && paymongoCurrentDuration !== wantDuration) {
      stopPolling();
      paymongoQrphInit = false;
      paymongoCurrentPiId = null;
    }

    showQrphSection('paymongo-qrph-loading');
    paymongoQrphInit = false;

    var form = document.getElementById('wizardRegisterForm');
    if (!form) return;

    var email    = form.querySelector('input[name="email"]').value.trim();
    var username = form.querySelector('input[name="username"]').value.trim();
    var password = form.querySelector('input[name="password"]').value;
    var phone    = (form.querySelector('input[name="phone"]') || {}).value || '';
    var fullname = ((form.querySelector('input[name="firstname"]') || {}).value || '') + ' ' +
                   ((form.querySelector('input[name="lastname"]') || {}).value || '');
    fullname = fullname.trim() || username;

    if (!email || !username || !password) {
      showQrphSection('paymongo-qrph-error');
      var errMsg = getEl('paymongo-qrph-error-msg');
      if (errMsg) errMsg.textContent = 'Please complete Steps 1 & 2 before proceeding with payment.';
      return;
    }

    var tier = (typeof getSelectedTier === 'function') ? getSelectedTier() : null;
    // Determine duration and amount based on which QRPH option is selected
    var selectedMethod = document.querySelector('input[name="payment_method"]:checked');
    var isAnnual = selectedMethod && selectedMethod.value === 'paymongo_qrph_annual';
    var duration = isAnnual ? '12m' : '1m';
    var recurringPhp = tier ? parseInt(tier.recurringPhp || tier.pricePhp || 0, 10) : 0;
    var amountPhp = isAnnual ? recurringPhp * 12 : recurringPhp;
    var tierName  = tier ? (tier.name || 'Membership') : 'Membership';

    if (amountPhp <= 0) {
      showQrphSection('paymongo-qrph-error');
      var errMsg = getEl('paymongo-qrph-error-msg');
      if (errMsg) errMsg.textContent = 'Please select a membership tier first.';
      return;
    }

    // Get CSRF token
    var csrfInput = form.querySelector('input[name="csrf_token"]');
    var csrfToken = csrfInput ? csrfInput.value : '';

    var formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('amount', amountPhp);
    formData.append('email', email);
    formData.append('name', fullname);
    formData.append('phone', phone);
    formData.append('tier', tierName);
    formData.append('duration', duration);

    fetch('/api/payments/paymongo-qrph-init', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.success) {
        showQrphSection('paymongo-qrph-error');
        var errMsg = getEl('paymongo-qrph-error-msg');
        if (errMsg) errMsg.textContent = data.message || 'Failed to generate QR code.';
        return;
      }

      paymongoCurrentPiId = data.pi_id;
      paymongoCurrentDuration = wantDuration;
      paymongoQrphInit = true;

      // Show QR code
      var qrImg = getEl('paymongo-qr-image');
      if (qrImg && data.qr_image) {
        qrImg.src = data.qr_image;
        // Wire up download link
        var dlLink = getEl('paymongo-download-qr');
        if (dlLink) dlLink.href = data.qr_image;
      }

      var amountEl = getEl('paymongo-qr-amount');
      if (amountEl) {
        amountEl.textContent = '₱' + amountPhp.toLocaleString() + (wantDuration === '12m' ? ' (12 months)' : ' (1 month)');
      }

      showQrphSection('paymongo-qrph-qr');

      // Start polling for payment
      startPolling(data.pi_id);
    })
    .catch(function(err) {
      console.error('PayMongo QRPH init error:', err);
      showQrphSection('paymongo-qrph-error');
      var errMsg = getEl('paymongo-qrph-error-msg');
      if (errMsg) errMsg.textContent = 'Network error. Please check your connection and try again.';
    });
  };

  // Retry button
  var retryBtn = getEl('paymongo-retry-btn');
  if (retryBtn) {
    retryBtn.addEventListener('click', function() {
      paymongoQrphInit = false;
      stopPolling();
      window.initPaymongoQrph();
    });
  }

  // Refresh QR button
  var refreshBtn = getEl('paymongo-refresh-qr');
  if (refreshBtn) {
    refreshBtn.addEventListener('click', function() {
      paymongoQrphInit = false;
      stopPolling();
      window.initPaymongoQrph();
    });
  }

  // Confirm QRPH Payment - complete registration after confirmed payment
  var confirmPaymongoBtn = getEl('confirm-paymongo-payment');
  if (confirmPaymongoBtn) {
    confirmPaymongoBtn.addEventListener('click', function() {
      if (!paymongoQrphPaid || !paymongoCurrentPiId) {
        window.showModal('Payment Not Confirmed', 'Your payment has not been confirmed yet. Please scan the QR code and complete the payment first.', 'fas fa-exclamation-circle', 'text-yellow-500');
        return;
      }

      var form = document.getElementById('wizardRegisterForm');
      var formData = new FormData(form);

      var tier = (typeof getSelectedTier === 'function') ? getSelectedTier() : null;
      // Use the same duration/amount logic as initPaymongoQrph
      var selectedMethod = document.querySelector('input[name="payment_method"]:checked');
      var isAnnual = selectedMethod && selectedMethod.value === 'paymongo_qrph_annual';
      var duration = isAnnual ? '12m' : '1m';
      var recurringPhp = tier ? parseInt(tier.recurringPhp || tier.pricePhp || 0, 10) : 0;
      var amountPhp = isAnnual ? recurringPhp * 12 : recurringPhp;

      formData.set('package', tier ? tier.name : 'Membership');
      formData.set('package_amount', amountPhp);
      formData.set('package_currency', 'PHP');
      formData.set('pay_method', 'paymongo_qrph');
      formData.set('payment_method', 'paymongo_qrph');
      formData.set('pi_id', paymongoCurrentPiId);

      confirmPaymongoBtn.disabled = true;
      confirmPaymongoBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Completing Registration...';

      fetch('/paymongo-payments', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
      })
      .then(function(r) { return r.json(); })
      .then(function(result) {
        if (result.success) {
          var redirectUrl = result.redirect || '/chat';
          window.showModal(
            'Welcome to Ginto!',
            result.message || 'Your account is now active! You\'re ready to start.',
            'fas fa-check-circle',
            'text-green-500',
            function() { window.location.href = redirectUrl; }
          );
        } else {
          confirmPaymongoBtn.disabled = false;
          confirmPaymongoBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i> Complete Registration';
          window.showModal('Registration Error', result.message || 'Could not complete registration. Please contact support.', 'fas fa-exclamation-circle', 'text-red-500');
        }
      })
      .catch(function(err) {
        console.error('PayMongo registration error:', err);
        confirmPaymongoBtn.disabled = false;
        confirmPaymongoBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i> Complete Registration';
        window.showModal('Network Error', 'Could not connect to server. Please try again.', 'fas fa-exclamation-triangle', 'text-red-500');
      });
    });
  }

  // Stop polling when leaving the page
  window.addEventListener('beforeunload', stopPolling);

  // -------------------------------------------------------
  // Ginto Pay (Card) — update display and handle click
  // -------------------------------------------------------
  function updateGintoPayDisplay() {
    var tier = window.selectedTier;
    var selectedMethod = document.querySelector('input[name="payment_method"]:checked');
    var isAnnual = false; // Ginto Pay panel doesn't distinguish — use 12m by default for card
    var amountPhp = tier ? parseInt(tier.recurringPhp || tier.pricePhp || 0, 10) : 0;
    var amountEl = document.getElementById('ginto-pay-amount');
    if (amountEl) amountEl.textContent = '₱' + amountPhp.toLocaleString();
    var labelEl = document.getElementById('ginto-pay-duration-label');
    if (labelEl) labelEl.textContent = 'Monthly Subscription — renew anytime';
  }

  var gintoPayBtn = document.getElementById('ginto-pay-btn');
  var gintoPayCurrentPiId = null;
  var gintoPayPollTimer = null;

  function openGintoOtpModal(url) {
    var backdrop = document.getElementById('ginto-otp-backdrop');
    var iframe = document.getElementById('ginto-otp-iframe');
    var openTab = document.getElementById('ginto-otp-open-tab');
    if (!backdrop || !iframe || !openTab) return;
    openTab.href = url;
    iframe.src = url;
    backdrop.classList.remove('hidden');
    backdrop.classList.add('flex');
  }

  function closeGintoOtpModal() {
    var backdrop = document.getElementById('ginto-otp-backdrop');
    var iframe = document.getElementById('ginto-otp-iframe');
    if (!backdrop || !iframe) return;
    backdrop.classList.add('hidden');
    backdrop.classList.remove('flex');
    iframe.src = 'about:blank';
  }

  function stopGintoPayPolling() {
    if (gintoPayPollTimer) {
      clearInterval(gintoPayPollTimer);
      gintoPayPollTimer = null;
    }
  }

  function startGintoPayPolling(piId) {
    stopGintoPayPolling();
    var statusEl = document.getElementById('ginto-pay-status');
    if (statusEl) {
      statusEl.classList.remove('hidden');
      statusEl.textContent = 'Waiting for webhook confirmation...';
    }
    gintoPayPollTimer = setInterval(function() {
      fetch('/api/payments/gintopay-status?pi_id=' + encodeURIComponent(piId))
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.processed) {
            stopGintoPayPolling();
            closeGintoOtpModal();
            if (statusEl) statusEl.textContent = 'Payment verified. Redirecting...';
            window.location.href = data.redirect || '/chat';
          } else if (data.failed) {
            stopGintoPayPolling();
            var errEl = document.getElementById('ginto-pay-error');
            var errMsg = document.getElementById('ginto-pay-error-msg');
            if (errEl) errEl.classList.remove('hidden');
            if (errMsg) errMsg.textContent = data.message || 'Payment verification failed.';
            if (statusEl) statusEl.textContent = '';
            gintoPayBtn.disabled = false;
            gintoPayBtn.innerHTML = '<i class="fas fa-lock mr-1"></i> Pay with Ginto Pay';
          }
        })
        .catch(function() {
          // Keep polling silently
        });
    }, 3000);
  }

  if (gintoPayBtn) {
    gintoPayBtn.addEventListener('click', function() {
      var form = document.getElementById('wizardRegisterForm');
      if (!form) return;

      var email    = (form.querySelector('input[name="email"]') || {}).value || '';
      var username = (form.querySelector('input[name="username"]') || {}).value || '';
      var password = (form.querySelector('input[name="password"]') || {}).value || '';

      if (!email || !username || !password) {
        var errEl = document.getElementById('ginto-pay-error');
        var errMsg = document.getElementById('ginto-pay-error-msg');
        if (errEl) errEl.classList.remove('hidden');
        if (errMsg) errMsg.textContent = 'Please complete Steps 1 & 2 before proceeding with payment.';
        return;
      }

      var tier = (typeof getSelectedTier === 'function') ? getSelectedTier() : window.selectedTier;
      var amountPhp = tier ? parseInt(tier.recurringPhp || tier.pricePhp || 0, 10) : 0;
      var tierName  = tier ? (tier.name || 'Membership') : 'Membership';
      var cardNumber = ((document.getElementById('ginto-card-number') || {}).value || '').replace(/\s+/g, '');
      var cardCvc = ((document.getElementById('ginto-card-cvc') || {}).value || '').trim();
      var expMonth = ((document.getElementById('ginto-card-exp-month') || {}).value || '').trim();
      var expYear = ((document.getElementById('ginto-card-exp-year') || {}).value || '').trim();
      var billLine1 = ((document.getElementById('ginto-billing-line1') || {}).value || '').trim();
      var billCity = ((document.getElementById('ginto-billing-city') || {}).value || '').trim();
      var billState = ((document.getElementById('ginto-billing-state') || {}).value || '').trim();
      var billPostal = ((document.getElementById('ginto-billing-postal') || {}).value || '').trim();

      if (amountPhp <= 0) {
        var errEl = document.getElementById('ginto-pay-error');
        var errMsg = document.getElementById('ginto-pay-error-msg');
        if (errEl) errEl.classList.remove('hidden');
        if (errMsg) errMsg.textContent = 'Please select a membership tier first.';
        return;
      }

      if (!cardNumber || !cardCvc || !expMonth || !expYear) {
        var cardErrEl = document.getElementById('ginto-pay-error');
        var cardErrMsg = document.getElementById('ginto-pay-error-msg');
        if (cardErrEl) cardErrEl.classList.remove('hidden');
        if (cardErrMsg) cardErrMsg.textContent = 'Please complete your card details.';
        return;
      }

      if (!billLine1 || !billCity || !billState || !billPostal) {
        var billErrEl = document.getElementById('ginto-pay-error');
        var billErrMsg = document.getElementById('ginto-pay-error-msg');
        if (billErrEl) billErrEl.classList.remove('hidden');
        if (billErrMsg) billErrMsg.textContent = 'Please complete billing address details.';
        return;
      }

      gintoPayBtn.disabled = true;
      gintoPayBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Initializing payment...';

      var errElReset = document.getElementById('ginto-pay-error');
      if (errElReset) errElReset.classList.add('hidden');

      var formData = new FormData(form);
      formData.set('amount', amountPhp);
      formData.set('tier', tierName);
      formData.set('duration', '1m');
      formData.set('card_number', cardNumber);
      formData.set('cvc', cardCvc);
      formData.set('exp_month', expMonth);
      formData.set('exp_year', expYear);
      formData.set('billing_line1', billLine1);
      formData.set('billing_city', billCity);
      formData.set('billing_state', billState);
      formData.set('billing_postal_code', billPostal);
      formData.set('billing_country', (form.querySelector('[name="country"]') || {}).value || 'PH');

      fetch('/api/payments/gintopay-init', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!data.success) {
          gintoPayBtn.disabled = false;
          gintoPayBtn.innerHTML = '<i class="fas fa-lock mr-1"></i> Pay with Ginto Pay';
          var errEl = document.getElementById('ginto-pay-error');
          var errMsg = document.getElementById('ginto-pay-error-msg');
          if (errEl) errEl.classList.remove('hidden');
          if (errMsg) errMsg.textContent = data.message || 'Could not initialize checkout. Please try again.';
          return;
        }

        gintoPayCurrentPiId = data.pi_id;

        if (data.requires_action && data.next_action_url) {
          openGintoOtpModal(data.next_action_url);
        }

        startGintoPayPolling(gintoPayCurrentPiId);
      })
      .catch(function(err) {
        console.error('Ginto Pay init error:', err);
        gintoPayBtn.disabled = false;
        gintoPayBtn.innerHTML = '<i class="fas fa-lock mr-1"></i> Pay with Ginto Pay';
        var errEl = document.getElementById('ginto-pay-error');
        var errMsg = document.getElementById('ginto-pay-error-msg');
        if (errEl) errEl.classList.remove('hidden');
        if (errMsg) errMsg.textContent = 'Network error. Please check your connection and try again.';
      });
    });
  }

  var gintoOtpCloseBtn = document.getElementById('ginto-otp-close');
  if (gintoOtpCloseBtn) {
    gintoOtpCloseBtn.addEventListener('click', closeGintoOtpModal);
  }

  var gintoOtpBackdrop = document.getElementById('ginto-otp-backdrop');
  if (gintoOtpBackdrop) {
    gintoOtpBackdrop.addEventListener('click', function(e) {
      if (e.target && e.target.id === 'ginto-otp-backdrop') {
        closeGintoOtpModal();
      }
    });
  }

  // Expose updateGintoPayDisplay globally so step-switch can call it
  window.updateGintoPayDisplay = updateGintoPayDisplay;
})();
</script>
<?php endif; ?>

<script>
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