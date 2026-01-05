<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
$currentPage = 'ftp';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="icon" type="image/png" href="/assets/images/ginto.png" />
  <title>FTP/SFTP - Server Hosting</title>
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
        <h1 class="text-2xl font-bold">FTP/SFTP Management</h1>
        <p class="text-gray-500">Manage FTP accounts for file transfers</p>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 mb-6 p-4">
        <div class="flex items-start gap-4">
          <div class="w-12 h-12 rounded-xl bg-blue-500/10 flex items-center justify-center flex-shrink-0">
            <i class="fas fa-info-circle text-2xl text-blue-500"></i>
          </div>
          <div>
            <h3 class="font-semibold mb-1">SFTP Recommended</h3>
            <p class="text-sm text-gray-500">SFTP (SSH File Transfer Protocol) is more secure than FTP. SSH access already provides SFTP capability without additional configuration.</p>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
          <h2 class="font-semibold">FTP Accounts</h2>
          <button disabled class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-500 rounded-lg cursor-not-allowed flex items-center gap-2">
            <i class="fas fa-plus"></i> Add FTP User
          </button>
        </div>
        <div class="p-8 text-center text-gray-500">
          <i class="fas fa-folder-open text-4xl mb-4 opacity-50"></i>
          <p>No FTP accounts configured</p>
          <p class="text-sm mt-2">Install ProFTPD or Pure-FTPd to enable FTP management</p>
        </div>
      </div>

      <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
        <h3 class="font-semibold mb-3">Connection Info</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
            <div class="text-gray-500 mb-1">SFTP Host</div>
            <div class="font-mono"><?= gethostname() ?></div>
          </div>
          <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
            <div class="text-gray-500 mb-1">SFTP Port</div>
            <div class="font-mono">22</div>
          </div>
        </div>
      </div>

      <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
        <h3 class="font-semibold mb-3">Setup FTP Server</h3>
        <div class="space-y-2 text-sm text-gray-500">
          <p><strong>Option 1: ProFTPD</strong> - <code class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">sudo apt install proftpd</code></p>
          <p><strong>Option 2: Pure-FTPd</strong> - <code class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">sudo apt install pure-ftpd</code></p>
          <p><strong>Option 3: vsftpd</strong> - <code class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">sudo apt install vsftpd</code></p>
        </div>
      </div>
    </main>
  </div>
</body>
</html>
