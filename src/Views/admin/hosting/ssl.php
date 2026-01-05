<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
$currentPage = 'ssl';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="icon" type="image/png" href="/assets/images/ginto.png" />
  <title>SSL/TLS - Server Hosting</title>
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
        <h1 class="text-2xl font-bold">SSL/TLS Certificates</h1>
        <p class="text-gray-500">Manage Let's Encrypt certificates (auto-managed by Caddy)</p>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 mb-6 p-4">
        <div class="flex items-start gap-4">
          <div class="w-12 h-12 rounded-xl bg-emerald-500/10 flex items-center justify-center flex-shrink-0">
            <i class="fas fa-info-circle text-2xl text-emerald-500"></i>
          </div>
          <div>
            <h3 class="font-semibold mb-1">Automatic SSL with Caddy</h3>
            <p class="text-sm text-gray-500">Caddy automatically provisions and renews Let's Encrypt certificates for all configured domains. No manual intervention required.</p>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
          <h2 class="font-semibold">Active Certificates</h2>
        </div>
        <div id="certs-list" class="p-4">
          <div class="text-center text-gray-500 py-4"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
        </div>
      </div>
    </main>
  </div>

  <script>
    async function loadCerts() {
      try {
        const res = await fetch('/admin/hosting/ssl/api');
        const data = await res.json();
        const list = document.getElementById('certs-list');
        
        if (!data.certificates?.length) {
          list.innerHTML = '<div class="text-center text-gray-500 py-8">No SSL certificates found. Add domains to automatically provision certificates.</div>';
          return;
        }

        list.innerHTML = '<div class="space-y-3">' + data.certificates.map(c => `
          <div class="flex items-center justify-between p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50">
            <div class="flex items-center gap-4">
              <div class="w-10 h-10 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                <i class="fas fa-lock text-emerald-500"></i>
              </div>
              <div>
                <div class="font-medium">${c.domain}</div>
                <div class="text-sm text-gray-500">Issued by ${c.issuer}</div>
              </div>
            </div>
            <div class="flex items-center gap-4">
              <span class="px-3 py-1 text-sm rounded-full ${c.status === 'active' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-amber-500/10 text-amber-500'}">
                ${c.status === 'active' ? 'Active' : c.status}
              </span>
              ${c.auto_renew ? '<span class="text-xs text-gray-400"><i class="fas fa-sync-alt mr-1"></i>Auto-renew</span>' : ''}
            </div>
          </div>
        `).join('') + '</div>';
      } catch (e) { console.error(e); }
    }

    loadCerts();
  </script>
</body>
</html>
