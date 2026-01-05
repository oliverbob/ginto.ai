<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
$currentPage = 'dns';

// Detect server public IP
$serverIp = $_SERVER['SERVER_ADDR'] ?? '';
if (empty($serverIp) || $serverIp === '127.0.0.1' || str_starts_with($serverIp, '10.') || str_starts_with($serverIp, '192.168.')) {
    // Try to get public IP from external service (cached)
    $cacheFile = sys_get_temp_dir() . '/ginto_public_ip.txt';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
        $serverIp = trim(file_get_contents($cacheFile));
    } else {
        $serverIp = @file_get_contents('https://api.ipify.org') ?: @file_get_contents('https://icanhazip.com') ?: '';
        $serverIp = trim($serverIp);
        if ($serverIp) @file_put_contents($cacheFile, $serverIp);
    }
}
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
  <script src="/assets/js/ui-components.js"></script>
  <link rel="stylesheet" href="/lib/fontawesome/css/all.min.css">
  <style>
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: #1f2937; }
    ::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 3px; }
    .record-row:hover { background: rgba(99, 102, 241, 0.05); }
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
          <h1 class="text-2xl font-bold">DNS Zone Management</h1>
          <p class="text-gray-500">Manage DNS zones and records for your domains</p>
        </div>
        <div class="flex gap-2">
          <button onclick="syncPowerDNS()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center gap-2">
            <i class="fas fa-sync"></i> Sync PowerDNS
          </button>
          <button onclick="showAddZoneModal()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg flex items-center gap-2">
            <i class="fas fa-plus"></i> Add Zone
          </button>
        </div>
      </div>

      <!-- Zone List -->
      <div id="zones-container" class="space-y-4">
        <div class="text-center py-12 text-gray-500">
          <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
          <p>Loading zones...</p>
        </div>
      </div>

      <!-- SOA Settings -->
      <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
        <button onclick="toggleSoaSettings()" class="w-full px-4 py-3 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50 rounded-xl">
          <div class="flex items-center gap-3">
            <i class="fas fa-cog text-gray-400"></i>
            <span class="font-semibold">SOA Defaults</span>
          </div>
          <i id="soa-toggle-icon" class="fas fa-chevron-down text-gray-400"></i>
        </button>
        <div id="soa-settings" class="hidden border-t border-gray-200 dark:border-gray-700 p-4">
          <form id="soa-form" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm text-gray-500 mb-1">Primary NS</label>
              <input type="text" name="primary_ns" class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg" value="ns1.ginto.ai">
            </div>
            <div>
              <label class="block text-sm text-gray-500 mb-1">Admin Email</label>
              <input type="text" name="admin_email" class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg" value="admin.ginto.ai">
            </div>
            <div>
              <label class="block text-sm text-gray-500 mb-1">Refresh (sec)</label>
              <input type="number" name="refresh" class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg" value="10800">
            </div>
            <div>
              <label class="block text-sm text-gray-500 mb-1">Retry (sec)</label>
              <input type="number" name="retry" class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg" value="3600">
            </div>
            <div>
              <label class="block text-sm text-gray-500 mb-1">Expire (sec)</label>
              <input type="number" name="expire" class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg" value="604800">
            </div>
            <div>
              <label class="block text-sm text-gray-500 mb-1">Minimum TTL</label>
              <input type="number" name="minimum_ttl" class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg" value="3600">
            </div>
            <div>
              <label class="block text-sm text-gray-500 mb-1">Default TTL</label>
              <input type="number" name="default_ttl" class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg" value="3600">
            </div>
            <div class="flex items-end">
              <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg">Save SOA Defaults</button>
            </div>
          </form>
        </div>
      </div>
    </main>
  </div>

  <!-- Add Zone Modal -->
  <div id="add-zone-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-md mx-4">
      <h3 class="text-xl font-bold mb-4">Add DNS Zone</h3>
      <form id="add-zone-form">
        <div class="mb-4">
          <label class="block text-sm text-gray-500 mb-1">Zone Name (domain)</label>
          <input type="text" name="zone" placeholder="example.com" class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg" required>
        </div>
        <div class="mb-4">
          <label class="block text-sm text-gray-500 mb-1">Zone Type</label>
          <select name="type" class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg">
            <option value="NATIVE">Native</option>
            <option value="MASTER">Master</option>
            <option value="SLAVE">Slave</option>
          </select>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" onclick="hideAddZoneModal()" class="px-4 py-2 text-gray-500 hover:text-gray-700">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg">Create Zone</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Add Record Modal -->
  <div id="add-record-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-lg mx-4">
      <h3 class="text-xl font-bold mb-4">Add DNS Record</h3>
      <form id="add-record-form">
        <input type="hidden" name="zone" id="record-zone">
        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-sm text-gray-500 mb-1">Name</label>
            <input type="text" name="name" placeholder="@ or subdomain" class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg">
            <p class="text-xs text-gray-400 mt-1">Use @ for root domain</p>
          </div>
          <div>
            <label class="block text-sm text-gray-500 mb-1">Type</label>
            <select name="type" id="record-type" onchange="updateRecordForm()" class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg">
              <option value="A">A (IPv4)</option>
              <option value="AAAA">AAAA (IPv6)</option>
              <option value="CNAME">CNAME</option>
              <option value="MX">MX (Mail)</option>
              <option value="TXT">TXT</option>
              <option value="NS">NS</option>
              <option value="SRV">SRV</option>
              <option value="CAA">CAA</option>
            </select>
          </div>
        </div>
        <div class="mb-4">
          <label class="block text-sm text-gray-500 mb-1">Content</label>
          <div class="flex gap-2">
            <input type="text" name="content" id="record-content" placeholder="IP address or hostname" class="flex-1 px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg" required>
            <?php if ($serverIp): ?>
            <button type="button" onclick="document.getElementById('record-content').value='<?= htmlspecialchars($serverIp) ?>'" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm whitespace-nowrap" title="Use server IP">
              Use <?= htmlspecialchars($serverIp) ?>
            </button>
            <?php endif; ?>
          </div>
          <p id="content-hint" class="text-xs text-gray-400 mt-1">Enter IPv4 address (e.g., 10.0.0.1)</p>
        </div>
        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-sm text-gray-500 mb-1">TTL (seconds)</label>
            <select name="ttl" class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg">
              <option value="300">5 minutes</option>
              <option value="3600" selected>1 hour</option>
              <option value="14400">4 hours</option>
              <option value="86400">1 day</option>
            </select>
          </div>
          <div id="priority-field" class="hidden">
            <label class="block text-sm text-gray-500 mb-1">Priority</label>
            <input type="number" name="priority" value="10" min="0" max="65535" class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg">
          </div>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" onclick="hideAddRecordModal()" class="px-4 py-2 text-gray-500 hover:text-gray-700">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg">Add Record</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const csrfToken = '<?= $csrfToken ?>';
    let zones = [];

    async function loadZones() {
      try {
        const res = await fetch('/admin/hosting/dns/api');
        const data = await res.json();
        zones = data.zones || [];
        renderZones();
      } catch (e) {
        document.getElementById('zones-container').innerHTML = `
          <div class="bg-red-500/10 border border-red-500/20 rounded-xl p-4 text-red-400">
            <i class="fas fa-exclamation-triangle mr-2"></i> Failed to load zones: ${e.message}
          </div>`;
      }
    }

    function renderZones() {
      const container = document.getElementById('zones-container');
      if (zones.length === 0) {
        container.innerHTML = `
          <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8 text-center">
            <i class="fas fa-sitemap text-4xl text-gray-400 mb-4"></i>
            <h3 class="font-semibold mb-2">No DNS Zones</h3>
            <p class="text-gray-500 mb-4">Create your first zone to start managing DNS records</p>
            <button onclick="showAddZoneModal()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg">
              <i class="fas fa-plus mr-2"></i> Add Zone
            </button>
          </div>`;
        return;
      }

      container.innerHTML = zones.map(z => `
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <i class="fas fa-globe text-emerald-500"></i>
              <div>
                <h3 class="font-semibold">${z.name}</h3>
                <span class="text-xs text-gray-500">${z.record_count || 0} records · ${z.type}</span>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <button onclick="toggleZoneRecords('${z.name}')" class="px-3 py-1 text-sm text-gray-500 hover:text-emerald-500">
                <i class="fas fa-chevron-down" id="toggle-${z.name}"></i>
              </button>
              <button onclick="showAddRecordModal('${z.name}')" class="px-3 py-1 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded">
                <i class="fas fa-plus mr-1"></i> Record
              </button>
              <button onclick="deleteZone('${z.name}')" class="px-3 py-1 text-sm text-red-500 hover:text-red-400">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </div>
          <div id="records-${z.name}" class="hidden"></div>
        </div>
      `).join('');
    }

    async function toggleZoneRecords(zone) {
      const container = document.getElementById(`records-${zone}`);
      const icon = document.getElementById(`toggle-${zone}`);
      
      if (!container.classList.contains('hidden')) {
        container.classList.add('hidden');
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
        return;
      }

      container.innerHTML = '<div class="p-4 text-center text-gray-500"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
      container.classList.remove('hidden');
      icon.classList.remove('fa-chevron-down');
      icon.classList.add('fa-chevron-up');

      try {
        const res = await fetch(`/admin/hosting/dns/api?zone=${encodeURIComponent(zone)}`);
        const data = await res.json();
        renderRecords(zone, data.records || []);
      } catch (e) {
        container.innerHTML = `<div class="p-4 text-red-400">Failed to load records</div>`;
      }
    }

    function renderRecords(zone, records) {
      const container = document.getElementById(`records-${zone}`);
      if (records.length === 0) {
        container.innerHTML = '<div class="p-4 text-center text-gray-500">No records</div>';
        return;
      }

      const typeColors = {
        'A': 'bg-blue-500', 'AAAA': 'bg-purple-500', 'CNAME': 'bg-yellow-500',
        'MX': 'bg-pink-500', 'TXT': 'bg-gray-500', 'NS': 'bg-green-500',
        'SOA': 'bg-red-500', 'SRV': 'bg-indigo-500', 'CAA': 'bg-orange-500'
      };

      container.innerHTML = `
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-900/50">
            <tr class="text-left text-gray-500">
              <th class="px-4 py-2 w-16">Type</th>
              <th class="px-4 py-2">Name</th>
              <th class="px-4 py-2">Content</th>
              <th class="px-4 py-2 w-20">TTL</th>
              <th class="px-4 py-2 w-24"></th>
            </tr>
          </thead>
          <tbody>
            ${records.map(r => `
              <tr class="record-row border-t border-gray-200 dark:border-gray-700 ${r.disabled ? 'opacity-50' : ''}">
                <td class="px-4 py-2">
                  <span class="px-2 py-0.5 rounded text-xs text-white ${typeColors[r.type] || 'bg-gray-500'}">${r.type}</span>
                </td>
                <td class="px-4 py-2 font-mono text-xs">${r.name}</td>
                <td class="px-4 py-2 font-mono text-xs truncate max-w-xs" title="${r.content}">${r.content}</td>
                <td class="px-4 py-2 text-gray-500">${r.ttl}s</td>
                <td class="px-4 py-2 text-right">
                  ${r.type !== 'SOA' ? `
                    <button onclick="deleteRecord(${r.id}, '${zone}')" class="text-red-500 hover:text-red-400 p-1" title="Delete">
                      <i class="fas fa-trash"></i>
                    </button>
                  ` : '<span class="text-gray-400 text-xs">Protected</span>'}
                </td>
              </tr>
            `).join('')}
          </tbody>
        </table>`;
    }

    // Modal functions
    function showAddZoneModal() {
      document.getElementById('add-zone-modal').classList.remove('hidden');
      document.getElementById('add-zone-modal').classList.add('flex');
    }
    function hideAddZoneModal() {
      document.getElementById('add-zone-modal').classList.add('hidden');
      document.getElementById('add-zone-modal').classList.remove('flex');
    }
    function showAddRecordModal(zone) {
      document.getElementById('record-zone').value = zone;
      document.getElementById('add-record-modal').classList.remove('hidden');
      document.getElementById('add-record-modal').classList.add('flex');
      updateRecordForm();
    }
    function hideAddRecordModal() {
      document.getElementById('add-record-modal').classList.add('hidden');
      document.getElementById('add-record-modal').classList.remove('flex');
    }

    function updateRecordForm() {
      const type = document.getElementById('record-type').value;
      const priorityField = document.getElementById('priority-field');
      const contentHint = document.getElementById('content-hint');
      
      priorityField.classList.toggle('hidden', !['MX', 'SRV'].includes(type));
      
      const hints = {
        'A': 'Enter IPv4 address (e.g., 10.0.0.1)',
        'AAAA': 'Enter IPv6 address (e.g., 2001:db8::1)',
        'CNAME': 'Enter target hostname (e.g., www.example.com)',
        'MX': 'Enter mail server hostname (e.g., mail.example.com)',
        'TXT': 'Enter text value (e.g., v=spf1 include:_spf.google.com ~all)',
        'NS': 'Enter nameserver hostname (e.g., ns1.example.com)',
        'SRV': 'Format: weight port target (e.g., 5 5060 sipserver.example.com)',
        'CAA': 'Format: flag tag value (e.g., 0 issue "letsencrypt.org")'
      };
      contentHint.textContent = hints[type] || '';
    }

    function toggleSoaSettings() {
      const settings = document.getElementById('soa-settings');
      const icon = document.getElementById('soa-toggle-icon');
      settings.classList.toggle('hidden');
      icon.classList.toggle('fa-chevron-down');
      icon.classList.toggle('fa-chevron-up');
    }

    // API calls
    async function apiCall(action, data = {}) {
      const res = await fetch('/admin/hosting/dns/api', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...data, action, csrf_token: csrfToken })
      });
      return res.json();
    }

    document.getElementById('add-zone-form').onsubmit = async (e) => {
      e.preventDefault();
      const form = new FormData(e.target);
      const result = await apiCall('create_zone', { zone: form.get('zone'), type: form.get('type') });
      if (result.success) {
        hideAddZoneModal();
        loadZones();
        GintoUI.success('Zone created successfully');
      } else {
        GintoUI.error(result.error || 'Failed to create zone');
      }
    };

    document.getElementById('add-record-form').onsubmit = async (e) => {
      e.preventDefault();
      const form = new FormData(e.target);
      const result = await apiCall('add_record', {
        zone: form.get('zone'),
        name: form.get('name'),
        type: form.get('type'),
        content: form.get('content'),
        ttl: form.get('ttl'),
        priority: form.get('priority')
      });
      if (result.success) {
        hideAddRecordModal();
        toggleZoneRecords(form.get('zone')); // Refresh records
        toggleZoneRecords(form.get('zone'));
        loadZones();
        GintoUI.success('Record added successfully');
      } else {
        GintoUI.error(result.error || 'Failed to add record');
      }
    };

    document.getElementById('soa-form').onsubmit = async (e) => {
      e.preventDefault();
      const form = new FormData(e.target);
      const data = {};
      form.forEach((v, k) => data[k] = v);
      const result = await apiCall('update_soa_defaults', data);
      if (result.success) {
        GintoUI.success(result.message || 'SOA defaults updated');
      } else {
        GintoUI.error(result.error || 'Failed to update SOA defaults');
      }
    };

    async function deleteZone(zone) {
      const confirmed = await GintoUI.confirm(`Delete zone <strong>${zone}</strong>? This will remove all records.`, 'Delete Zone');
      if (!confirmed) return;
      const result = await apiCall('delete_zone', { zone });
      if (result.success) {
        loadZones();
        GintoUI.success(`Zone ${zone} deleted`);
      } else {
        GintoUI.error(result.error || 'Failed to delete zone');
      }
    }

    async function deleteRecord(id, zone) {
      const confirmed = await GintoUI.confirm('Delete this DNS record?', 'Delete Record');
      if (!confirmed) return;
      const result = await apiCall('delete_record', { id });
      if (result.success) {
        toggleZoneRecords(zone);
        toggleZoneRecords(zone);
        GintoUI.success('Record deleted');
      } else {
        GintoUI.error(result.error || 'Failed to delete record');
      }
    }

    async function syncPowerDNS() {
      const result = await apiCall('sync_powerdns');
      if (result.success && !result.message?.includes('failed')) {
        GintoUI.success(result.message || 'Synced to PowerDNS');
      } else if (result.message?.includes('failed')) {
        GintoUI.error(result.message);
      } else {
        GintoUI.error(result.error || 'Sync failed');
      }
    }

    // Load on init
    loadZones();
  </script>
</body>
</html>
