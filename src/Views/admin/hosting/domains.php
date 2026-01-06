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
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owner</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Target</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SSL</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody id="domains-list" class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
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
  <div id="add-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 overflow-y-auto">
    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-lg mx-4 my-8">
      <h3 class="text-lg font-semibold mb-4">Add New Domain</h3>
      <form id="add-form" class="space-y-4">
        <!-- Domain Name -->
        <div>
          <label class="block text-sm font-medium mb-1">Domain Name <span class="text-red-500">*</span></label>
          <input type="text" name="domain" placeholder="example.com" required
            class="w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600">
        </div>

        <!-- Owner Information (searchable dropdowns) -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Owner Username</label>
            <input type="text" name="owner_username" id="owner-username" placeholder="Search username..." list="owner-username-list"
              class="w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600">
            <datalist id="owner-username-list"></datalist>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Owner Full Name</label>
            <input type="text" name="owner_fullname" id="owner-fullname" placeholder="Auto-filled from username" readonly
              class="w-full px-3 py-2 border rounded-lg bg-gray-100 dark:bg-gray-600 border-gray-300 dark:border-gray-600 text-gray-500">
          </div>
        </div>

        <!-- Proxy Type -->
        <div>
          <label class="block text-sm font-medium mb-1">Hosting Type</label>
          <select name="proxy_type" id="proxy-type" onchange="toggleProxyOptions()"
            class="w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600">
            <option value="none">Standard (Document Root)</option>
            <option value="lxc" id="lxc-option">LXC/LXD Container</option>
            <option value="docker" id="docker-option">Docker Container</option>
            <option value="external">External IP/Port</option>
          </select>
        </div>

        <!-- Standard Options (Document Root) -->
        <div id="standard-options">
          <div>
            <label class="block text-sm font-medium mb-1">Document Root</label>
            <input type="text" name="root" placeholder="/var/www/example.com"
              class="w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600">
          </div>
          <div class="flex items-center gap-4 mt-3">
            <label class="flex items-center gap-2">
              <input type="checkbox" name="php" checked class="rounded">
              <span class="text-sm">Enable PHP</span>
            </label>
          </div>
        </div>

        <!-- LXC Container Options -->
        <div id="lxc-options" class="hidden">
          <label class="block text-sm font-medium mb-1">Select LXC Container</label>
          <select name="lxc_container" id="lxc-container-select" onchange="onLxcContainerSelect()"
            class="w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600">
            <option value="">Loading containers...</option>
          </select>
          <div class="mt-2">
            <label class="block text-sm font-medium mb-1">Container Port</label>
            <input type="number" name="lxc_port" value="80" placeholder="80"
              class="w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600">
          </div>
        </div>

        <!-- Docker Container Options -->
        <div id="docker-options" class="hidden">
          <label class="block text-sm font-medium mb-1">Select Docker Container</label>
          <select name="docker_container" id="docker-container-select"
            class="w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600">
            <option value="">Loading containers...</option>
          </select>
        </div>

        <!-- External Proxy Options -->
        <div id="external-options" class="hidden">
          <label class="block text-sm font-medium mb-1">Proxy Target (IP:Port)</label>
          <input type="text" name="external_target" placeholder="192.168.1.100:8080"
            class="w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600">
        </div>

        <!-- SSL Option (always visible) -->
        <div class="flex items-center gap-2">
          <input type="checkbox" name="ssl" checked class="rounded" id="ssl-checkbox">
          <label for="ssl-checkbox" class="text-sm">Enable SSL (Let's Encrypt)</label>
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
    let containerData = { lxc_containers: [], docker_containers: [], lxd_installed: false, docker_installed: false };

    // Load available containers on page load
    async function loadContainers() {
      try {
        const res = await fetch('/admin/hosting/domains/containers');
        containerData = await res.json();
        
        // Populate LXC dropdown with owner info
        const lxcSelect = document.getElementById('lxc-container-select');
        if (containerData.lxc_containers?.length) {
          lxcSelect.innerHTML = '<option value="">-- Select Container --</option>' + containerData.lxc_containers.map(c => {
            const ownerInfo = c.owner_username ? ` [${c.owner_username}]` : '';
            const statusIcon = c.status === 'running' ? '🟢' : '⚪';
            return `<option value="${c.ip || ''}" data-name="${c.name}" data-owner="${c.owner_username || ''}" data-fullname="${c.owner_fullname || ''}" data-status="${c.status}">${statusIcon} ${c.name}${ownerInfo} ${c.ip ? '(' + c.ip + ')' : '(no IP)'}</option>`;
          }).join('');
        } else {
          lxcSelect.innerHTML = '<option value="">No LXC containers found</option>';
        }
        
        // Populate Docker dropdown
        const dockerSelect = document.getElementById('docker-container-select');
        if (containerData.docker_containers?.length) {
          dockerSelect.innerHTML = '<option value="">-- Select Container --</option>' + containerData.docker_containers.map(c => 
            `<option value="127.0.0.1:${c.port}" data-name="${c.name}">${c.name} (port ${c.port || 'N/A'})</option>`
          ).join('');
        } else {
          dockerSelect.innerHTML = '<option value="">No running Docker containers</option>';
        }
        
        // Populate owner username datalist
        const ownerDatalist = document.getElementById('owner-username-list');
        if (containerData.users_with_sandboxes?.length) {
          ownerDatalist.innerHTML = containerData.users_with_sandboxes.map(u => 
            `<option value="${u.username}" data-fullname="${u.name || ''}">${u.username} - ${u.name || 'No name'}</option>`
          ).join('');
        }
        
        // Hide unavailable options
        if (!containerData.lxd_installed) {
          document.getElementById('lxc-option').disabled = true;
          document.getElementById('lxc-option').textContent += ' (not installed)';
        }
        if (!containerData.docker_installed) {
          document.getElementById('docker-option').disabled = true;
          document.getElementById('docker-option').textContent += ' (not installed)';
        }
      } catch (e) {
        console.error('Failed to load containers:', e);
      }
    }

    // Auto-fill owner when selecting LXC container
    function onLxcContainerSelect() {
      const select = document.getElementById('lxc-container-select');
      const option = select.options[select.selectedIndex];
      if (option && option.dataset.owner) {
        document.getElementById('owner-username').value = option.dataset.owner;
        document.getElementById('owner-fullname').value = option.dataset.fullname || '';
      }
    }

    // Auto-fill fullname when typing username
    document.getElementById('owner-username').addEventListener('input', function() {
      const username = this.value;
      const user = containerData.users_with_sandboxes?.find(u => u.username === username);
      if (user) {
        document.getElementById('owner-fullname').value = user.name || '';
      }
    });

    function toggleProxyOptions() {
      const type = document.getElementById('proxy-type').value;
      document.getElementById('standard-options').classList.toggle('hidden', type !== 'none');
      document.getElementById('lxc-options').classList.toggle('hidden', type !== 'lxc');
      document.getElementById('docker-options').classList.toggle('hidden', type !== 'docker');
      document.getElementById('external-options').classList.toggle('hidden', type !== 'external');
    }

    async function loadDomains() {
      try {
        const res = await fetch('/admin/hosting/domains/api');
        const data = await res.json();
        const tbody = document.getElementById('domains-list');
        
        if (!data.domains?.length) {
          tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No domains configured</td></tr>';
          return;
        }

        tbody.innerHTML = data.domains.map(d => {
          // Determine target display
          let targetDisplay = '';
          const proxyType = d.proxy_type || 'none';
          if (proxyType === 'none') {
            targetDisplay = `<span class="text-gray-500 text-sm">${d.root || '/var/www/' + d.name}</span>`;
          } else if (proxyType === 'lxc') {
            targetDisplay = `<span class="text-purple-500"><i class="fas fa-cube mr-1"></i>LXC: ${d.proxy_target || d.proxy_container_name}</span>`;
          } else if (proxyType === 'docker') {
            targetDisplay = `<span class="text-blue-500"><i class="fab fa-docker mr-1"></i>Docker: ${d.proxy_container_name || d.proxy_target}</span>`;
          } else if (proxyType === 'external') {
            targetDisplay = `<span class="text-orange-500"><i class="fas fa-external-link-alt mr-1"></i>${d.proxy_target}</span>`;
          }

          // Owner display
          const ownerDisplay = d.owner_username 
            ? `<div class="text-sm"><span class="font-medium">${d.owner_username}</span>${d.owner_fullname ? `<br><span class="text-gray-400 text-xs">${d.owner_fullname}</span>` : ''}</div>`
            : '<span class="text-gray-400 text-sm">—</span>';

          return `
          <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <i class="fas fa-globe text-blue-500"></i>
                <span class="font-medium">${d.name}</span>
              </div>
            </td>
            <td class="px-4 py-3">${ownerDisplay}</td>
            <td class="px-4 py-3">${targetDisplay}</td>
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
        `}).join('');
      } catch (e) {
        console.error(e);
      }
    }

    function showAddModal() { document.getElementById('add-modal').classList.remove('hidden'); document.getElementById('add-modal').classList.add('flex'); }
    function hideAddModal() { document.getElementById('add-modal').classList.add('hidden'); document.getElementById('add-modal').classList.remove('flex'); }

    document.getElementById('add-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = new FormData(e.target);
      const proxyType = form.get('proxy_type');
      
      // Determine proxy target based on type
      let proxyTarget = null;
      let proxyContainerName = null;
      
      if (proxyType === 'lxc') {
        const lxcSelect = document.getElementById('lxc-container-select');
        const lxcPort = form.get('lxc_port') || '80';
        proxyTarget = lxcSelect.value + ':' + lxcPort;
        proxyContainerName = lxcSelect.options[lxcSelect.selectedIndex]?.text.split(' ')[0];
      } else if (proxyType === 'docker') {
        const dockerSelect = document.getElementById('docker-container-select');
        proxyTarget = dockerSelect.value;
        proxyContainerName = dockerSelect.options[dockerSelect.selectedIndex]?.dataset.name;
      } else if (proxyType === 'external') {
        proxyTarget = form.get('external_target');
      }

      try {
        const res = await fetch('/admin/hosting/domains/api', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            domain: form.get('domain'),
            root: proxyType === 'none' ? (form.get('root') || '/var/www/' + form.get('domain')) : null,
            php: form.has('php'),
            ssl: form.has('ssl'),
            owner_username: form.get('owner_username') || null,
            owner_fullname: form.get('owner_fullname') || null,
            proxy_type: proxyType,
            proxy_target: proxyTarget,
            proxy_container_name: proxyContainerName,
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
    loadContainers();
  </script>
</body>
</html>
