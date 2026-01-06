<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
$currentPage = 'domains';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="icon" type="image/png" href="/assets/images/ginto.png" />
  <title>Virtual Hosts - Server Hosting</title>
  <script src="/assets/js/tailwindcss.js"></script>
  <script>tailwind.config = { darkMode: 'class' }</script>
  <link rel="stylesheet" href="/lib/fontawesome/css/all.min.css">
  <script src="/assets/js/ui-components.js"></script>
  <style>
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: #1f2937; }
    ::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 3px; }
  </style>
</head>
<body class="min-h-screen bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
  <?php include __DIR__ . '/partials/header.php'; ?>

  <div class="flex h-[calc(100vh-57px)]">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold">Virtual Hosts</h1>
          <p class="text-gray-500 dark:text-gray-400">Manage web server virtual hosts and domains</p>
        </div>
        <button onclick="showAddModal()" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg flex items-center gap-2">
          <i class="fas fa-plus"></i>
          Add Domain
        </button>
      </div>

      <!-- Domains Table -->
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domain</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Document Root</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SSL</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody id="domains-list" class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr>
                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                  <i class="fas fa-spinner fa-spin mr-2"></i> Loading domains...
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <!-- Add Domain Modal -->
  <div id="add-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-md mx-4">
      <h3 class="text-lg font-semibold mb-4">Add New Domain</h3>
      <form id="add-form" class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">Domain Name</label>
          <input type="text" name="domain" placeholder="example.com" required
            class="w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Document Root</label>
          <input type="text" name="root" placeholder="/var/www/example.com"
            class="w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600">
        </div>
        <div class="flex items-center gap-4">
          <label class="flex items-center gap-2">
            <input type="checkbox" name="php" checked class="rounded">
            <span class="text-sm">Enable PHP</span>
          </label>
          <label class="flex items-center gap-2">
            <input type="checkbox" name="ssl" checked class="rounded">
            <span class="text-sm">Enable SSL</span>
          </label>
        </div>
        <div class="flex justify-end gap-3 mt-6">
          <button type="button" onclick="hideAddModal()" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
            Cancel
          </button>
          <button type="submit" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg">
            Create Domain
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const csrfToken = '<?= $csrfToken ?>';

    async function loadDomains() {
      try {
        const res = await fetch('/admin/hosting/domains/api');
        const data = await res.json();
        const tbody = document.getElementById('domains-list');
        
        if (!data.domains?.length) {
          tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No domains configured</td></tr>';
          return;
        }

        tbody.innerHTML = data.domains.map(d => `
          <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <i class="fas fa-globe text-blue-500"></i>
                <span class="font-medium">${d.name}</span>
              </div>
            </td>
            <td class="px-4 py-3 text-sm text-gray-500">${d.root}</td>
            <td class="px-4 py-3">
              ${d.ssl ? '<span class="text-emerald-500"><i class="fas fa-lock"></i> Active</span>' : '<span class="text-gray-400"><i class="fas fa-lock-open"></i> None</span>'}
            </td>
            <td class="px-4 py-3">
              <span class="px-2 py-1 text-xs rounded ${d.enabled ? 'bg-emerald-500/10 text-emerald-500' : 'bg-gray-500/10 text-gray-500'}">${d.enabled ? 'Active' : 'Disabled'}</span>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-2">
                <button onclick="domainAction('${d.name}', 'ssl')" class="p-1 text-gray-400 hover:text-emerald-500" title="Request SSL">
                  <i class="fas fa-certificate"></i>
                </button>
                <button onclick="domainAction('${d.name}', '${d.enabled ? 'disable' : 'enable'}')" class="p-1 text-gray-400 hover:text-amber-500" title="${d.enabled ? 'Disable' : 'Enable'}">
                  <i class="fas fa-${d.enabled ? 'pause' : 'play'}"></i>
                </button>
                <button onclick="domainAction('${d.name}', 'delete')" class="p-1 text-gray-400 hover:text-red-500" title="Delete">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </td>
          </tr>
        `).join('');
      } catch (e) {
        console.error(e);
      }
    }

    function showAddModal() { document.getElementById('add-modal').classList.remove('hidden'); document.getElementById('add-modal').classList.add('flex'); }
    function hideAddModal() { document.getElementById('add-modal').classList.add('hidden'); document.getElementById('add-modal').classList.remove('flex'); }

    document.getElementById('add-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = new FormData(e.target);
      try {
        const res = await fetch('/admin/hosting/domains/api', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            domain: form.get('domain'),
            root: form.get('root') || '/var/www/' + form.get('domain'),
            php: form.has('php'),
            ssl: form.has('ssl'),
            csrf_token: csrfToken
          })
        });
        const data = await res.json();
        if (data.success) {
          hideAddModal();
          GintoUI.success(`Domain ${form.get('domain')} created successfully`);
          loadDomains();
        } else {
          GintoUI.error(data.error || 'Failed to create domain');
        }
      } catch (err) {
        GintoUI.error('Error: ' + err.message);
      }
    });

    async function domainAction(domain, action) {
      if (action === 'delete') {
        const confirmed = await GintoUI.confirm(`Delete domain <strong>${domain}</strong>? This will remove the virtual host configuration.`, 'Delete Domain');
        if (!confirmed) return;
      }
      try {
        const res = await fetch(`/admin/hosting/domains/${domain}`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action, csrf_token: csrfToken })
        });
        const data = await res.json();
        if (data.success) {
          GintoUI.success(action === 'delete' ? `Domain ${domain} deleted` : `Domain ${domain} ${action}d`);
          loadDomains();
        } else {
          GintoUI.error(data.error || 'Action failed');
        }
      } catch (e) {
        GintoUI.error('Error: ' + e.message);
      }
    }

    loadDomains();
  </script>
</body>
</html>
