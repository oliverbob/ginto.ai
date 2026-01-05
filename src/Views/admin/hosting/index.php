<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="icon" type="image/png" href="/assets/images/ginto.png" />
  <title>Server Hosting - Ginto Admin</title>
  <script src="/assets/js/tailwindcss.js"></script>
  <script>tailwind.config = { darkMode: 'class' }</script>
  <link rel="stylesheet" href="/lib/fontawesome/css/all.min.css">
  <style>
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: #1f2937; }
    ::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 3px; }
    .stat-card { transition: all 0.2s; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
    .feature-card { transition: all 0.2s; cursor: pointer; }
    .feature-card:hover { background: rgba(99, 102, 241, 0.1); border-color: #6366f1; }
  </style>
  <script>
    (function() {
      const savedTheme = localStorage.getItem('ginto-theme');
      if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
      }
    })();
  </script>
</head>
<body class="min-h-screen bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
  <!-- Header -->
  <header class="border-b px-4 py-3 flex items-center justify-between sticky top-0 z-30 bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700">
    <div class="flex items-center gap-4">
      <a href="/admin" class="flex items-center gap-2 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
        <i class="fas fa-arrow-left"></i>
        <span>Back to Admin</span>
      </a>
      <div class="h-6 w-px bg-gray-300 dark:bg-gray-700"></div>
      <div class="flex items-center gap-2">
        <i class="fas fa-server text-emerald-500"></i>
        <h1 class="text-lg font-semibold">Server Hosting</h1>
      </div>
    </div>
    <div class="flex items-center gap-4">
      <button onclick="refreshData()" class="p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 rounded transition-colors" title="Refresh">
        <i class="fas fa-sync-alt"></i>
      </button>
      <button id="theme-toggle" class="p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 rounded transition-colors" title="Toggle theme">
        <i class="fas fa-moon dark:hidden"></i>
        <i class="fas fa-sun hidden dark:inline"></i>
      </button>
    </div>
  </header>

  <div class="flex h-[calc(100vh-57px)]">
    <!-- Sidebar -->
    <aside class="w-64 border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 flex-shrink-0 overflow-y-auto">
      <nav class="p-3 space-y-1">
        <a href="/admin/hosting" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-medium">
          <i class="fas fa-tachometer-alt w-5"></i>
          <span>Dashboard</span>
        </a>
        <a href="/admin/hosting/domains" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
          <i class="fas fa-globe w-5"></i>
          <span>Virtual Hosts</span>
        </a>
        <a href="/admin/hosting/dns" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
          <i class="fas fa-sitemap w-5"></i>
          <span>DNS Zones</span>
        </a>
        <a href="/admin/hosting/email" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
          <i class="fas fa-envelope w-5"></i>
          <span>Email Server</span>
        </a>
        <a href="/admin/hosting/databases" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
          <i class="fas fa-database w-5"></i>
          <span>Databases</span>
        </a>
        <a href="/admin/hosting/ftp" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
          <i class="fas fa-folder-open w-5"></i>
          <span>FTP/SFTP</span>
        </a>
        <a href="/admin/hosting/backups" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
          <i class="fas fa-archive w-5"></i>
          <span>Backups</span>
        </a>
        <a href="/admin/hosting/ssl" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
          <i class="fas fa-lock w-5"></i>
          <span>SSL/TLS</span>
        </a>
        <a href="/admin/hosting/firewall" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
          <i class="fas fa-shield-alt w-5"></i>
          <span>Firewall</span>
        </a>
        <a href="/admin/hosting/services" class="flex items-center gap-3 px-3 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
          <i class="fas fa-cogs w-5"></i>
          <span>Services</span>
        </a>
      </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto p-6">
      <!-- System Stats -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- CPU -->
        <div class="stat-card bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm text-gray-500 dark:text-gray-400">CPU Usage</span>
            <div class="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center">
              <i class="fas fa-microchip text-blue-500"></i>
            </div>
          </div>
          <div class="text-2xl font-bold mb-2"><?= $stats['cpu']['percent'] ?>%</div>
          <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
            <div class="bg-blue-500 h-2 rounded-full transition-all" style="width: <?= min($stats['cpu']['percent'], 100) ?>%"></div>
          </div>
          <div class="text-xs text-gray-500 mt-2"><?= $stats['cpu']['cores'] ?> cores • Load: <?= implode(', ', array_map(fn($l) => number_format($l, 2), $stats['cpu']['load'])) ?></div>
        </div>

        <!-- Memory -->
        <div class="stat-card bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm text-gray-500 dark:text-gray-400">Memory</span>
            <div class="w-10 h-10 rounded-lg bg-purple-500/10 flex items-center justify-center">
              <i class="fas fa-memory text-purple-500"></i>
            </div>
          </div>
          <div class="text-2xl font-bold mb-2"><?= $stats['memory']['used_human'] ?></div>
          <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
            <div class="bg-purple-500 h-2 rounded-full transition-all" style="width: <?= $stats['memory']['percent'] ?>%"></div>
          </div>
          <div class="text-xs text-gray-500 mt-2">of <?= $stats['memory']['total_human'] ?> (<?= $stats['memory']['percent'] ?>%)</div>
        </div>

        <!-- Disk -->
        <div class="stat-card bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm text-gray-500 dark:text-gray-400">Disk Usage</span>
            <div class="w-10 h-10 rounded-lg bg-amber-500/10 flex items-center justify-center">
              <i class="fas fa-hdd text-amber-500"></i>
            </div>
          </div>
          <div class="text-2xl font-bold mb-2"><?= $stats['disk']['used_human'] ?></div>
          <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
            <div class="bg-amber-500 h-2 rounded-full transition-all" style="width: <?= $stats['disk']['percent'] ?>%"></div>
          </div>
          <div class="text-xs text-gray-500 mt-2">of <?= $stats['disk']['total_human'] ?> (<?= $stats['disk']['percent'] ?>%)</div>
        </div>

        <!-- Uptime -->
        <div class="stat-card bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm text-gray-500 dark:text-gray-400">Uptime</span>
            <div class="w-10 h-10 rounded-lg bg-emerald-500/10 flex items-center justify-center">
              <i class="fas fa-clock text-emerald-500"></i>
            </div>
          </div>
          <div class="text-2xl font-bold mb-2"><?= str_replace('up ', '', $stats['uptime']) ?></div>
          <div class="text-xs text-gray-500 mt-2">Server is running smoothly</div>
        </div>
      </div>

      <!-- Quick Stats -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <a href="/admin/hosting/domains" class="feature-card bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 text-center">
          <div class="w-12 h-12 mx-auto rounded-xl bg-blue-500/10 flex items-center justify-center mb-3">
            <i class="fas fa-globe text-2xl text-blue-500"></i>
          </div>
          <div class="text-3xl font-bold"><?= $domainCount ?></div>
          <div class="text-sm text-gray-500">Domains</div>
        </a>
        <a href="/admin/hosting/databases" class="feature-card bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 text-center">
          <div class="w-12 h-12 mx-auto rounded-xl bg-purple-500/10 flex items-center justify-center mb-3">
            <i class="fas fa-database text-2xl text-purple-500"></i>
          </div>
          <div class="text-3xl font-bold"><?= $dbCount ?></div>
          <div class="text-sm text-gray-500">Databases</div>
        </a>
        <a href="/admin/hosting/email" class="feature-card bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 text-center">
          <div class="w-12 h-12 mx-auto rounded-xl bg-pink-500/10 flex items-center justify-center mb-3">
            <i class="fas fa-envelope text-2xl text-pink-500"></i>
          </div>
          <div class="text-3xl font-bold"><?= $emailCount ?></div>
          <div class="text-sm text-gray-500">Email Accounts</div>
        </a>
        <a href="/admin/hosting/ssl" class="feature-card bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 text-center">
          <div class="w-12 h-12 mx-auto rounded-xl bg-emerald-500/10 flex items-center justify-center mb-3">
            <i class="fas fa-lock text-2xl text-emerald-500"></i>
          </div>
          <div class="text-3xl font-bold"><?= $sslCount ?></div>
          <div class="text-sm text-gray-500">SSL Certs</div>
        </a>
      </div>

      <!-- Services Status & Quick Actions -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Services Status -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
          <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h2 class="font-semibold">Services Status</h2>
            <a href="/admin/hosting/services" class="text-sm text-indigo-500 hover:text-indigo-400">Manage →</a>
          </div>
          <div class="p-4 space-y-3">
            <?php foreach ($services as $name => $svc): ?>
            <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
              <div class="flex items-center gap-3">
                <span class="w-2 h-2 rounded-full <?= $svc['active'] ? 'bg-emerald-500' : 'bg-red-500' ?>"></span>
                <span class="font-medium"><?= htmlspecialchars($name) ?></span>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-xs px-2 py-1 rounded <?= $svc['active'] ? 'bg-emerald-500/10 text-emerald-500' : 'bg-red-500/10 text-red-500' ?>">
                  <?= $svc['active'] ? 'Running' : 'Stopped' ?>
                </span>
                <button onclick="serviceAction('<?= $name ?>', '<?= $svc['active'] ? 'restart' : 'start' ?>')" 
                        class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" title="<?= $svc['active'] ? 'Restart' : 'Start' ?>">
                  <i class="fas fa-<?= $svc['active'] ? 'redo' : 'play' ?> text-sm"></i>
                </button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
          <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h2 class="font-semibold">Quick Actions</h2>
          </div>
          <div class="p-4 grid grid-cols-2 gap-3">
            <a href="/admin/hosting/domains" class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
              <div class="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center">
                <i class="fas fa-plus text-blue-500"></i>
              </div>
              <span class="font-medium">Add Domain</span>
            </a>
            <a href="/admin/hosting/databases" class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
              <div class="w-10 h-10 rounded-lg bg-purple-500/10 flex items-center justify-center">
                <i class="fas fa-plus text-purple-500"></i>
              </div>
              <span class="font-medium">New Database</span>
            </a>
            <a href="/admin/hosting/email" class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
              <div class="w-10 h-10 rounded-lg bg-pink-500/10 flex items-center justify-center">
                <i class="fas fa-user-plus text-pink-500"></i>
              </div>
              <span class="font-medium">Email Account</span>
            </a>
            <a href="/admin/hosting/backups" class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
              <div class="w-10 h-10 rounded-lg bg-amber-500/10 flex items-center justify-center">
                <i class="fas fa-download text-amber-500"></i>
              </div>
              <span class="font-medium">Create Backup</span>
            </a>
            <a href="/admin/hosting/ssl" class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
              <div class="w-10 h-10 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                <i class="fas fa-certificate text-emerald-500"></i>
              </div>
              <span class="font-medium">Request SSL</span>
            </a>
            <a href="/admin/hosting/firewall" class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
              <div class="w-10 h-10 rounded-lg bg-red-500/10 flex items-center justify-center">
                <i class="fas fa-shield-alt text-red-500"></i>
              </div>
              <span class="font-medium">Firewall Rules</span>
            </a>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script>
    const csrfToken = '<?= $csrfToken ?>';

    // Theme toggle
    document.getElementById('theme-toggle')?.addEventListener('click', () => {
      document.documentElement.classList.toggle('dark');
      localStorage.setItem('ginto-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
    });

    function refreshData() {
      location.reload();
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
          location.reload();
        } else {
          alert(data.error || 'Action failed');
        }
      } catch (e) {
        alert('Error: ' + e.message);
      }
    }
  </script>
</body>
</html>
