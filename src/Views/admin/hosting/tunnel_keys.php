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
          <select id="bulkTtl" class="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
            <option value="3600">1 hour</option>
            <option value="21600">6 hours</option>
            <option value="86400">24 hours</option>
            <option value="2592000">30 days</option>
            <option value="31536000" selected>1 year</option>
            <option value="94608000">3 years</option>
            <option value="157680000">5 years</option>
          </select>
          <button onclick="bulkReactivate()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
            <i class="fas fa-bolt mr-2"></i>Bulk Reactivate
          </button>
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
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  <input id="select-all" type="checkbox" onclick="toggleAll(this.checked)" />
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subdomain</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">JTI</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expires</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Used</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody id="keys-list" class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr><td colspan="10" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <script>
    const csrfToken = <?= json_encode($csrfToken) ?>;
    function esc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])); }

    function isKeyExpired(k) {
      if (!k || !k.expires_at) return false;
      const exp = Date.parse(String(k.expires_at).replace(' ', 'T') + 'Z');
      return Number.isFinite(exp) && exp <= Date.now();
    }

    function canReactivate(k) {
      return Number(k?.revoked || 0) === 1 || isKeyExpired(k);
    }

    function toggleAll(checked) {
      document.querySelectorAll('.key-select').forEach(el => {
        if (!el.disabled) {
          el.checked = !!checked;
        }
      });
    }

    function getSelectedKeyIds() {
      const ids = [];
      document.querySelectorAll('.key-select:checked').forEach(el => {
        const id = Number(el.value || 0);
        if (id > 0) ids.push(id);
      });
      return ids;
    }

    async function bulkReactivate() {
      const ids = getSelectedKeyIds();
      if (!ids.length) {
        alert('Select at least one expired/revoked key.');
        return;
      }

      const ttl = Number(document.getElementById('bulkTtl')?.value || 31536000);
      const res = await fetch('/admin/hosting/tunnels/keys/reactivate-bulk', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ ids, ttl_seconds: ttl, csrf_token: csrfToken })
      });
      const data = await res.json();
      if (!data || !data.success) {
        alert(data?.error || 'Bulk reactivation failed');
        return;
      }

      const reactivated = Array.isArray(data.results)
        ? data.results.filter(r => r && r.status === 'reactivated')
        : [];

      if (reactivated.length) {
        const lines = reactivated.map(r => `${r.subdomain} (user ${r.user_id}): ${r.token}`);
        alert(`Reactivated ${reactivated.length} key(s). Save these new tokens now:\n\n${lines.join('\n')}`);
      } else {
        alert('No keys were reactivated (selected keys may already be active).');
      }

      loadKeys();
    }

    async function loadKeys() {
      const tbody = document.getElementById('keys-list');
      tbody.innerHTML = '<tr><td colspan="10" class="px-4 py-8 text-center text-gray-500"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
      try {
        const res = await fetch('/admin/hosting/tunnels/keys/api');
        const data = await res.json();
        const keys = Array.isArray(data.keys) ? data.keys : [];
        document.getElementById('keys-count').textContent = `Showing ${keys.length}`;
        if (!keys.length) {
          tbody.innerHTML = '<tr><td colspan="10" class="px-4 py-8 text-center text-gray-500">No keys found</td></tr>';
          return;
        }
        tbody.innerHTML = keys.map(k => {
          const revoked = Number(k.revoked || 0) === 1;
          const expired = !revoked && isKeyExpired(k);
          const eligible = canReactivate(k);
          const status = revoked
            ? '<span class="px-2 py-1 text-xs rounded bg-gray-500/10 text-gray-500">Revoked</span>'
            : (expired
                ? '<span class="px-2 py-1 text-xs rounded bg-amber-500/10 text-amber-600">Expired</span>'
                : '<span class="px-2 py-1 text-xs rounded bg-emerald-500/10 text-emerald-500">Active</span>'
              );
          const releaseBtn = `<button type="button" onclick="releaseSubdomain('${esc(k.subdomain)}')" class="px-2 py-1 text-xs rounded bg-red-600 hover:bg-red-700 text-white transition-colors">Release</button>`;
          return `
            <tr>
              <td class="px-4 py-3 text-xs"><input class="key-select" type="checkbox" value="${esc(k.id)}" ${eligible ? '' : 'disabled'} /></td>
              <td class="px-4 py-3 font-mono text-xs">${esc(k.id)}</td>
              <td class="px-4 py-3 font-mono text-xs">${esc(k.user_id)}</td>
              <td class="px-4 py-3 font-mono text-xs">${esc(k.subdomain)}</td>
              <td class="px-4 py-3 font-mono text-xs">${esc(k.jti)}</td>
              <td class="px-4 py-3 text-xs">${esc(k.created_at)}</td>
              <td class="px-4 py-3 text-xs">${esc(k.expires_at || '')}</td>
              <td class="px-4 py-3 text-xs">${esc(k.last_used_at || '')}</td>
              <td class="px-4 py-3 text-xs">${status}</td>
              <td class="px-4 py-3 text-xs">${releaseBtn}</td>
            </tr>
          `;
        }).join('');
      } catch (e) {
        tbody.innerHTML = '<tr><td colspan="10" class="px-4 py-8 text-center text-red-500">Failed to load keys</td></tr>';
      }
    }

    async function releaseSubdomain(rawSubdomain) {
      const subdomain = String(rawSubdomain || '').toLowerCase().replace(/[^a-z0-9-]/g, '');
      if (!subdomain) {
        alert('Invalid subdomain');
        return;
      }

      if (!confirm(`Release ${subdomain}.silverqueen.pro for everyone? This deletes all key records for this subdomain.`)) {
        return;
      }

      try {
        const res = await fetch('/admin/hosting/tunnels/keys/release-subdomain', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ subdomain, csrf_token: csrfToken }),
        });
        const data = await res.json();
        if (!data || !data.success) {
          alert(data?.error || 'Failed to release subdomain');
          return;
        }

        alert(`${subdomain}.silverqueen.pro is now released and available.`);
        loadKeys();
      } catch (e) {
        alert('Failed to release subdomain');
      }
    }

    loadKeys();
  </script>
</body>
</html>
