<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
$currentPage = 'dns';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="icon" type="image/png" href="/assets/images/ginto.png" />
  <title>DNS Zones - Server Hosting</title>
  <script src="/assets/js/tailwindcss.js"></script>
  <script>tailwind.config = { darkMode: 'class' }</script>
  <link rel="stylesheet" href="/lib/fontawesome/css/all.min.css">
</head>
<body class="min-h-screen bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
  <?php include __DIR__ . '/partials/header.php'; ?>
  <div class="flex h-[calc(100vh-57px)]">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <main class="flex-1 overflow-y-auto p-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold">DNS Zone Management</h1>
        <p class="text-gray-500">Manage DNS zones and records</p>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 mb-6 p-4">
        <div class="flex items-start gap-4">
          <div class="w-12 h-12 rounded-xl bg-amber-500/10 flex items-center justify-center flex-shrink-0">
            <i class="fas fa-exclamation-triangle text-2xl text-amber-500"></i>
          </div>
          <div>
            <h3 class="font-semibold mb-1">DNS Server Not Configured</h3>
            <p class="text-sm text-gray-500">To manage DNS zones, you need to install and configure BIND9 or PowerDNS. For most setups, use your domain registrar's DNS management or a service like Cloudflare.</p>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
          <h2 class="font-semibold">DNS Zones</h2>
          <button disabled class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-500 rounded-lg cursor-not-allowed flex items-center gap-2">
            <i class="fas fa-plus"></i> Add Zone
          </button>
        </div>
        <div class="p-8 text-center text-gray-500">
          <i class="fas fa-sitemap text-4xl mb-4 opacity-50"></i>
          <p>No DNS zones configured</p>
          <p class="text-sm mt-2">Install BIND9 or PowerDNS to enable DNS management</p>
        </div>
      </div>

      <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
        <h3 class="font-semibold mb-3">Quick Setup Guide</h3>
        <div class="space-y-2 text-sm text-gray-500">
          <p><strong>Option 1: BIND9</strong> - <code class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">sudo apt install bind9 bind9utils</code></p>
          <p><strong>Option 2: PowerDNS</strong> - <code class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">sudo apt install pdns-server pdns-backend-mysql</code></p>
          <p><strong>Option 3: External DNS</strong> - Use Cloudflare, Route53, or your registrar's DNS</p>
        </div>
      </div>
    </main>
  </div>
</body>
</html>
