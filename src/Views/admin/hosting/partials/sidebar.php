<?php
// Sidebar partial for hosting views
$currentPage = $currentPage ?? '';
$menuItems = [
    ['id' => '', 'icon' => 'tachometer-alt', 'label' => 'Dashboard', 'href' => '/admin/hosting'],
    ['id' => 'domains', 'icon' => 'globe', 'label' => 'Virtual Hosts', 'href' => '/admin/hosting/domains'],
    ['id' => 'dns', 'icon' => 'sitemap', 'label' => 'DNS Zones', 'href' => '/admin/hosting/dns'],
    ['id' => 'email', 'icon' => 'envelope', 'label' => 'Email Server', 'href' => '/admin/hosting/email'],
    ['id' => 'databases', 'icon' => 'database', 'label' => 'Databases', 'href' => '/admin/hosting/databases'],
    ['id' => 'ftp', 'icon' => 'folder-open', 'label' => 'FTP/SFTP', 'href' => '/admin/hosting/ftp'],
    ['id' => 'backups', 'icon' => 'archive', 'label' => 'Backups', 'href' => '/admin/hosting/backups'],
    ['id' => 'ssl', 'icon' => 'lock', 'label' => 'SSL/TLS', 'href' => '/admin/hosting/ssl'],
    ['id' => 'firewall', 'icon' => 'shield-alt', 'label' => 'Firewall', 'href' => '/admin/hosting/firewall'],
    ['id' => 'services', 'icon' => 'cogs', 'label' => 'Services', 'href' => '/admin/hosting/services'],
];
?>
<aside class="w-64 border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 flex-shrink-0 overflow-y-auto">
  <nav class="p-3 space-y-1">
    <?php foreach ($menuItems as $item): 
      $isActive = $currentPage === $item['id'];
    ?>
    <a href="<?= $item['href'] ?>" class="flex items-center gap-3 px-3 py-2 rounded-lg <?= $isActive ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-medium' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
      <i class="fas fa-<?= $item['icon'] ?> w-5"></i>
      <span><?= $item['label'] ?></span>
    </a>
    <?php endforeach; ?>
  </nav>
</aside>
