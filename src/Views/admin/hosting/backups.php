<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
$currentPage = 'backups';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="icon" type="image/png" href="/assets/images/ginto.png" />
  <title>Backups - Server Hosting</title>
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
          <h1 class="text-2xl font-bold">Backup Management</h1>
          <p class="text-gray-500">Create and restore server backups</p>
        </div>
        <button onclick="createBackup()" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg flex items-center gap-2">
          <i class="fas fa-download"></i> Create Backup
        </button>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-center">
          <div class="w-12 h-12 mx-auto rounded-xl bg-blue-500/10 flex items-center justify-center mb-3">
            <i class="fas fa-archive text-2xl text-blue-500"></i>
          </div>
          <div class="text-2xl font-bold" id="backup-count">-</div>
          <div class="text-sm text-gray-500">Total Backups</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-center">
          <div class="w-12 h-12 mx-auto rounded-xl bg-purple-500/10 flex items-center justify-center mb-3">
            <i class="fas fa-hdd text-2xl text-purple-500"></i>
          </div>
          <div class="text-2xl font-bold" id="backup-size">-</div>
          <div class="text-sm text-gray-500">Storage Used</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-center">
          <div class="w-12 h-12 mx-auto rounded-xl bg-amber-500/10 flex items-center justify-center mb-3">
            <i class="fas fa-clock text-2xl text-amber-500"></i>
          </div>
          <div class="text-2xl font-bold" id="last-backup">-</div>
          <div class="text-sm text-gray-500">Last Backup</div>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
          <h2 class="font-semibold">Backup History</h2>
        </div>
        <div id="backups-list" class="p-4">
          <div class="text-center text-gray-500 py-4"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
        </div>
      </div>
    </main>
  </div>

  <script>
    const csrfToken = '<?= $csrfToken ?>';

    async function loadBackups() {
      try {
        const res = await fetch('/admin/hosting/backups/api');
        const data = await res.json();
        const list = document.getElementById('backups-list');
        
        document.getElementById('backup-count').textContent = data.backups?.length || 0;
        
        if (!data.backups?.length) {
          list.innerHTML = '<div class="text-center text-gray-500 py-8">No backups found. Create your first backup!</div>';
          document.getElementById('backup-size').textContent = '0 MB';
          document.getElementById('last-backup').textContent = 'Never';
          return;
        }

        document.getElementById('last-backup').textContent = data.backups[0].date.split(' ')[0];
        
        list.innerHTML = '<div class="space-y-2">' + data.backups.map(b => `
          <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
            <div class="flex items-center gap-3">
              <i class="fas fa-file-archive text-amber-500"></i>
              <div>
                <div class="font-medium">${b.name}</div>
                <div class="text-xs text-gray-500">${b.date} • ${b.size}</div>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <button onclick="deleteBackup('${b.name}')" class="p-2 text-gray-400 hover:text-red-500" title="Delete">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </div>
        `).join('') + '</div>';
      } catch (e) { console.error(e); }
    }

    async function createBackup() {
      try {
        const res = await fetch('/admin/hosting/backups/api', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'create', type: 'full', csrf_token: csrfToken })
        });
        const data = await res.json();
        if (data.success) {
          alert('Backup started: ' + data.file);
          setTimeout(loadBackups, 3000);
        } else {
          alert(data.error);
        }
      } catch (e) { alert('Error: ' + e.message); }
    }

    async function deleteBackup(file) {
      if (!confirm('Delete this backup?')) return;
      const res = await fetch('/admin/hosting/backups/api', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', file, csrf_token: csrfToken })
      });
      const data = await res.json();
      if (data.success) loadBackups(); else alert(data.error);
    }

    loadBackups();
  </script>
</body>
</html>
