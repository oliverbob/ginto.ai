<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
$currentPage = 'databases';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="icon" type="image/png" href="/assets/images/ginto.png" />
  <title>Databases - Server Hosting</title>
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
          <h1 class="text-2xl font-bold">Database Management</h1>
          <p class="text-gray-500">Manage MySQL/MariaDB databases and users</p>
        </div>
        <div class="flex gap-2">
          <button onclick="showCreateDbModal()" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg flex items-center gap-2">
            <i class="fas fa-plus"></i> New Database
          </button>
          <button onclick="showCreateUserModal()" class="px-4 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg flex items-center gap-2">
            <i class="fas fa-user-plus"></i> New User
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Databases -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
          <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h2 class="font-semibold">Databases</h2>
          </div>
          <div id="databases-list" class="p-4 space-y-2">
            <div class="text-center text-gray-500 py-4"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
          </div>
        </div>

        <!-- Users -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
          <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h2 class="font-semibold">Database Users</h2>
          </div>
          <div id="users-list" class="p-4 space-y-2">
            <div class="text-center text-gray-500 py-4"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Create DB Modal -->
  <div id="create-db-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-md mx-4">
      <h3 class="text-lg font-semibold mb-4">Create Database</h3>
      <form id="create-db-form" class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">Database Name</label>
          <input type="text" name="database" required pattern="[a-zA-Z0-9_]+" class="w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600">
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" onclick="hideModal('create-db-modal')" class="px-4 py-2 border rounded-lg">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-emerald-500 text-white rounded-lg">Create</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Create User Modal -->
  <div id="create-user-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-md mx-4">
      <h3 class="text-lg font-semibold mb-4">Create Database User</h3>
      <form id="create-user-form" class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">Username</label>
          <input type="text" name="user" required pattern="[a-zA-Z0-9_]+" class="w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Password</label>
          <input type="password" name="password" required class="w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Grant Access To Database</label>
          <select name="database" id="db-select" class="w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600"></select>
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" onclick="hideModal('create-user-modal')" class="px-4 py-2 border rounded-lg">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-indigo-500 text-white rounded-lg">Create User</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const csrfToken = '<?= $csrfToken ?>';

    async function loadData() {
      try {
        const res = await fetch('/admin/hosting/databases/api');
        const data = await res.json();
        
        const dbList = document.getElementById('databases-list');
        const usersList = document.getElementById('users-list');
        const dbSelect = document.getElementById('db-select');
        
        dbList.innerHTML = data.databases?.length ? data.databases.map(db => `
          <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
            <div class="flex items-center gap-3">
              <i class="fas fa-database text-purple-500"></i>
              <span class="font-medium">${db.name}</span>
            </div>
            <button onclick="deleteDb('${db.name}')" class="text-gray-400 hover:text-red-500"><i class="fas fa-trash"></i></button>
          </div>
        `).join('') : '<div class="text-center text-gray-500 py-4">No databases</div>';

        usersList.innerHTML = data.users?.length ? data.users.map(u => `
          <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
            <div class="flex items-center gap-3">
              <i class="fas fa-user text-indigo-500"></i>
              <span class="font-medium">${u.User}@${u.Host}</span>
            </div>
          </div>
        `).join('') : '<div class="text-center text-gray-500 py-4">No users</div>';

        dbSelect.innerHTML = (data.databases || []).map(db => `<option value="${db.name}">${db.name}</option>`).join('');
      } catch (e) { console.error(e); }
    }

    function showCreateDbModal() { document.getElementById('create-db-modal').classList.remove('hidden'); document.getElementById('create-db-modal').classList.add('flex'); }
    function showCreateUserModal() { document.getElementById('create-user-modal').classList.remove('hidden'); document.getElementById('create-user-modal').classList.add('flex'); }
    function hideModal(id) { document.getElementById(id).classList.add('hidden'); document.getElementById(id).classList.remove('flex'); }

    document.getElementById('create-db-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = new FormData(e.target);
      const res = await fetch('/admin/hosting/databases/api', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'create_db', database: form.get('database'), csrf_token: csrfToken })
      });
      const data = await res.json();
      if (data.success) { hideModal('create-db-modal'); loadData(); } else { alert(data.error); }
    });

    document.getElementById('create-user-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = new FormData(e.target);
      const res = await fetch('/admin/hosting/databases/api', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'create_user', user: form.get('user'), password: form.get('password'), database: form.get('database'), csrf_token: csrfToken })
      });
      const data = await res.json();
      if (data.success) { hideModal('create-user-modal'); loadData(); } else { alert(data.error); }
    });

    async function deleteDb(name) {
      if (!confirm(`Delete database ${name}? This cannot be undone.`)) return;
      const res = await fetch('/admin/hosting/databases/api', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'drop_db', database: name, csrf_token: csrfToken })
      });
      const data = await res.json();
      if (data.success) loadData(); else alert(data.error);
    }

    loadData();
  </script>
</body>
</html>
