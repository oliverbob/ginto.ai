<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
$currentPage = 'firewall';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="icon" type="image/png" href="/assets/images/ginto.png" />
  <title>Firewall - Server Hosting</title>
  <script src="/assets/js/tailwindcss.js"></script>
  <script>tailwind.config = { darkMode: 'class' }</script>
  <link rel="stylesheet" href="/lib/fontawesome/css/all.min.css">
</head>
<body class="min-h-screen bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
  <?php include __DIR__ . '/partials/header.php'; ?>
  <div class="flex h-[calc(100vh-57px)]">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <main class="flex-1 overflow-y-auto p-6">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold">Firewall Management</h1>
          <p class="text-gray-500">UFW rules and fail2ban status</p>
        </div>
        <button onclick="showAddRuleModal()" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg flex items-center gap-2">
          <i class="fas fa-plus"></i> Add Rule
        </button>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- UFW Rules -->
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
          <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h2 class="font-semibold">UFW Rules</h2>
          </div>
          <div id="rules-list" class="p-4 space-y-2">
            <div class="text-center text-gray-500 py-4"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
          </div>
        </div>

        <!-- Fail2ban Status -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
          <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h2 class="font-semibold">Fail2ban</h2>
          </div>
          <div id="fail2ban-status" class="p-4">
            <div class="text-center text-gray-500 py-4"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Add Rule Modal -->
  <div id="add-rule-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-md mx-4">
      <h3 class="text-lg font-semibold mb-4">Add Firewall Rule</h3>
      <form id="add-rule-form" class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">Action</label>
          <select name="action" class="w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600">
            <option value="allow">Allow</option>
            <option value="deny">Deny</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Port (or leave empty for IP rule)</label>
          <input type="text" name="port" placeholder="80, 443, 22/tcp" class="w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">IP Address (optional)</label>
          <input type="text" name="ip" placeholder="192.168.1.100" class="w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600">
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" onclick="hideModal('add-rule-modal')" class="px-4 py-2 border rounded-lg">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-emerald-500 text-white rounded-lg">Add Rule</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const csrfToken = '<?= $csrfToken ?>';

    async function loadData() {
      try {
        const res = await fetch('/admin/hosting/firewall/api');
        const data = await res.json();
        
        const rulesList = document.getElementById('rules-list');
        const f2bStatus = document.getElementById('fail2ban-status');
        
        if (data.rules?.length) {
          rulesList.innerHTML = data.rules.map(r => `
            <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
              <div class="flex items-center gap-3">
                <span class="w-8 h-8 rounded-lg ${r.action === 'ALLOW' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-red-500/10 text-red-500'} flex items-center justify-center">
                  <i class="fas fa-${r.action === 'ALLOW' ? 'check' : 'times'}"></i>
                </span>
                <div>
                  <div class="font-medium">[${r.number}] ${r.to}</div>
                  <div class="text-xs text-gray-500">${r.action} from ${r.from || 'Anywhere'}</div>
                </div>
              </div>
              <button onclick="deleteRule('${r.number}')" class="text-gray-400 hover:text-red-500"><i class="fas fa-trash"></i></button>
            </div>
          `).join('');
        } else {
          rulesList.innerHTML = '<div class="text-center text-gray-500 py-4">No UFW rules configured</div>';
        }

        if (data.fail2ban?.active) {
          f2bStatus.innerHTML = `
            <div class="flex items-center gap-2 mb-4">
              <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
              <span class="font-medium text-emerald-500">Active</span>
            </div>
            <div class="space-y-2">
              ${data.fail2ban.jails?.map(j => `
                <div class="p-2 rounded bg-gray-50 dark:bg-gray-700/50 text-sm">${j}</div>
              `).join('') || '<div class="text-gray-500">No jails configured</div>'}
            </div>
          `;
        } else {
          f2bStatus.innerHTML = '<div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-red-500"></span><span class="text-red-500">Inactive</span></div>';
        }
      } catch (e) { console.error(e); }
    }

    function showAddRuleModal() { document.getElementById('add-rule-modal').classList.remove('hidden'); document.getElementById('add-rule-modal').classList.add('flex'); }
    function hideModal(id) { document.getElementById(id).classList.add('hidden'); document.getElementById(id).classList.remove('flex'); }

    document.getElementById('add-rule-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = new FormData(e.target);
      const res = await fetch('/admin/hosting/firewall/api', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: form.get('action'), port: form.get('port'), ip: form.get('ip'), csrf_token: csrfToken })
      });
      const data = await res.json();
      if (data.success) { hideModal('add-rule-modal'); loadData(); } else { alert(data.error || data.message); }
    });

    async function deleteRule(num) {
      if (!confirm('Delete this rule?')) return;
      const res = await fetch('/admin/hosting/firewall/api', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', rule: num, csrf_token: csrfToken })
      });
      const data = await res.json();
      if (data.success) loadData(); else alert(data.error || data.message);
    }

    loadData();
  </script>
</body>
</html>
