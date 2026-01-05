<?php
// Header partial for hosting views
?>
<header class="border-b px-4 py-3 flex items-center justify-between sticky top-0 z-30 bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700">
  <div class="flex items-center gap-4">
    <a href="/admin" class="flex items-center gap-2 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
      <i class="fas fa-arrow-left"></i>
      <span class="hidden sm:inline">Admin</span>
    </a>
    <div class="h-6 w-px bg-gray-300 dark:bg-gray-700"></div>
    <a href="/admin/hosting" class="flex items-center gap-2">
      <i class="fas fa-server text-emerald-500"></i>
      <h1 class="text-lg font-semibold">Server Hosting</h1>
    </a>
  </div>
  <div class="flex items-center gap-4">
    <button onclick="location.reload()" class="p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 rounded transition-colors" title="Refresh">
      <i class="fas fa-sync-alt"></i>
    </button>
    <button id="theme-toggle" class="p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 rounded transition-colors" title="Toggle theme" onclick="document.documentElement.classList.toggle('dark'); localStorage.setItem('ginto-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');">
      <i class="fas fa-moon dark:hidden"></i>
      <i class="fas fa-sun hidden dark:inline"></i>
    </button>
  </div>
</header>
