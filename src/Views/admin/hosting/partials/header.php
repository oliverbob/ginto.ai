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
  <div class="flex items-center gap-3">
    <button onclick="location.reload()" class="flex items-center gap-2 px-3 py-1.5 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors" title="Refresh">
      <i class="fas fa-sync-alt"></i>
      <span class="hidden sm:inline text-sm">Refresh</span>
    </button>
    <a href="https://github.com/ginto-ai/ginto.ai" target="_blank" rel="noopener" class="flex items-center gap-2 px-3 py-1.5 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors" title="Star us on GitHub">
      <i class="fab fa-github"></i>
      <span class="hidden sm:inline text-sm">Star us</span>
    </a>
    <button id="theme-toggle" class="p-2 text-gray-500 dark:text-gray-400 hover:text-yellow-500 dark:hover:text-yellow-400 transition-colors" title="Toggle theme" onclick="document.documentElement.classList.toggle('dark'); localStorage.setItem('ginto-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');">
      <i class="fas fa-sun"></i>
    </button>
  </div>
</header>
