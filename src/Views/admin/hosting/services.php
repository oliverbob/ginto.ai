<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
$currentPage = 'services';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="icon" type="image/png" href="/assets/images/ginto.png" />
  <title>Services - Server Hosting</title>
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
        <h1 class="text-2xl font-bold">System Services</h1>
        <p class="text-gray-500">Manage systemd services for web hosting</p>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Service</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Enabled</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody id="services-list" class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <script>
    const csrfToken = '<?= $csrfToken ?>';

    async function loadServices() {
      try {
        const res = await fetch('/admin/hosting/services/api');
        const data = await res.json();
        const tbody = document.getElementById('services-list');
        
        if (!data.services || Object.keys(data.services).length === 0) {
          tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No services found</td></tr>';
          return;
        }

        tbody.innerHTML = Object.entries(data.services).map(([name, svc]) => `
          <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
            <td class="px-4 py-3">
              <div class="flex items-center gap-3">
                <i class="fas fa-cog text-gray-500"></i>
                <span class="font-medium">${name}</span>
              </div>
            </td>
            <td class="px-4 py-3">
              <span class="px-2 py-1 text-xs rounded ${svc.active ? 'bg-emerald-500/10 text-emerald-500' : 'bg-red-500/10 text-red-500'}">
                ${svc.status}
              </span>
            </td>
            <td class="px-4 py-3">
              <span class="text-sm ${svc.enabled ? 'text-emerald-500' : 'text-gray-400'}">
                ${svc.enabled ? 'Yes' : 'No'}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-2">
                ${svc.active ? `
                  <button onclick="serviceAction('${name}', 'restart')" class="px-3 py-1 text-sm bg-amber-500/10 text-amber-500 rounded hover:bg-amber-500/20" title="Restart">
                    <i class="fas fa-redo mr-1"></i> Restart
                  </button>
                  <button onclick="serviceAction('${name}', 'stop')" class="px-3 py-1 text-sm bg-red-500/10 text-red-500 rounded hover:bg-red-500/20" title="Stop">
                    <i class="fas fa-stop mr-1"></i> Stop
                  </button>
                ` : `
                  <button onclick="serviceAction('${name}', 'start')" class="px-3 py-1 text-sm bg-emerald-500/10 text-emerald-500 rounded hover:bg-emerald-500/20" title="Start">
                    <i class="fas fa-play mr-1"></i> Start
                  </button>
                `}
              </div>
            </td>
          </tr>
        `).join('');
      } catch (e) { console.error(e); }
    }

    async function serviceAction(service, action) {
      try {
        const res = await fetch('/admin/hosting/services/api', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ service, action, csrf_token: csrfToken })
        });
        const data = await res.json();
        if (data.success) {
          loadServices();
        } else {
          alert(data.error || data.message || 'Action failed');
        }
      } catch (e) {
        alert('Error: ' + e.message);
      }
    }

    loadServices();
  </script>
</body>
</html>
