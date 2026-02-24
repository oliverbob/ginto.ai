<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
$currentPage = 'tunnels';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="icon" type="image/png" href="/assets/images/ginto.png" />
  <title>Tunnel Access Keys - Server Hosting</title>
  <script src="/assets/js/tailwindcss.js"></script>
  <script>tailwind.config = { darkMode: 'class' }</script>
  <link rel="stylesheet" href="/lib/fontawesome/css/all.min.css">
</head>
<body class="min-h-screen bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
  <?php include __DIR__ . '/partials/header.php'; ?>
  <div class="flex h-[calc(100vh-57px)]">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <main class="flex-1 overflow-y-auto p-6">
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">Tunnel Access Keys</h1>
          <p class="text-gray-500">Admin view of generated keys (tokens are not displayed)</p>
        </div>
        <div class="flex gap-2">
          <a href="/admin/hosting/tunnels" class="px-4 py-2 bg-gray-700 hover:bg-gray-800 text-white rounded-lg transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Back
          </a>
          <button onclick="loadKeys()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors">
            <i class="fas fa-sync-alt mr-2"></i>Refresh
          </button>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
          <h2 class="font-semibold">Recent Keys</h2>
          <span id="keys-count" class="text-sm text-gray-500">-</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subdomain</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">JTI</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expires</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Used</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
              </tr>
            </thead>
            <tbody id="keys-list" class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <script>
    function esc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])); }

    async function loadKeys() {
      const tbody = document.getElementById('keys-list');
      tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
      try {
        const res = await fetch('/admin/hosting/tunnels/keys/api');
        const data = await res.json();
        const keys = Array.isArray(data.keys) ? data.keys : [];
        document.getElementById('keys-count').textContent = `Showing ${keys.length}`;
        if (!keys.length) {
          tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">No keys found</td></tr>';
          return;
        }
        tbody.innerHTML = keys.map(k => {
          const revoked = Number(k.revoked || 0) === 1;
          const status = revoked
            ? '<span class="px-2 py-1 text-xs rounded bg-gray-500/10 text-gray-500">Revoked</span>'
            : '<span class="px-2 py-1 text-xs rounded bg-emerald-500/10 text-emerald-500">Active</span>';
          return `
            <tr>
              <td class="px-4 py-3 font-mono text-xs">${esc(k.id)}</td>
              <td class="px-4 py-3 font-mono text-xs">${esc(k.user_id)}</td>
              <td class="px-4 py-3 font-mono text-xs">${esc(k.subdomain)}</td>
              <td class="px-4 py-3 font-mono text-xs">${esc(k.jti)}</td>
              <td class="px-4 py-3 text-xs">${esc(k.created_at)}</td>
              <td class="px-4 py-3 text-xs">${esc(k.expires_at || '')}</td>
              <td class="px-4 py-3 text-xs">${esc(k.last_used_at || '')}</td>
              <td class="px-4 py-3 text-xs">${status}</td>
            </tr>
          `;
        }).join('');
      } catch (e) {
        tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-red-500">Failed to load keys</td></tr>';
      }
    }

    loadKeys();
  </script>
</body>
</html>
