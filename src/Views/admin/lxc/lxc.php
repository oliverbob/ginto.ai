<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="icon" type="image/png" href="/assets/images/ginto.png" />
  <title>LXC/LXD Manager - Ginto Admin</title>
  <script src="/assets/js/tailwindcss.js"></script>
  <script>
    tailwind.config = {
      darkMode: 'class'
    }
  </script>
  <link rel="stylesheet" href="/lib/fontawesome/css/all.min.css">
  <style>
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: #1f2937; }
    ::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: #6b7280; }
    
    .resource-tree-item { transition: all 0.15s; }
    .resource-tree-item:hover { background: rgba(99, 102, 241, 0.1); }
    .resource-tree-item.active { background: rgba(99, 102, 241, 0.2); border-left: 3px solid #6366f1; }
    
    .status-running { color: #22c55e; }
    .status-stopped { color: #6b7280; }
    .status-error { color: #ef4444; }
    
    .stat-card { transition: background 0.15s; }
    
    .action-btn { transition: background 0.15s; }
    
    /* Text overflow utilities */
    .text-truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .table-cell-truncate { max-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    
    /* Mobile sidebar */
    @media (max-width: 767px) {
      .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 40; }
      .sidebar-overlay.open { display: block; }
      #sidebar { transform: translateX(-100%); position: fixed; z-index: 50; height: 100vh; top: 0; }
      #sidebar.open { transform: translateX(0); }
    }
    
    /* Mobile table cards */
    @media (max-width: 767px) {
      .mobile-card { display: block !important; }
      .desktop-table { display: none !important; }
    }
    @media (min-width: 768px) {
      .mobile-card { display: none !important; }
      .desktop-table { display: table !important; }
    }
    
    /* Light mode base styles (default) */
    .lxc-card { background: #fff !important; border-color: #e5e7eb !important; }
    .lxc-input { background: #f3f4f6 !important; border-color: #d1d5db !important; color: #1f2937 !important; }
    
    /* Dark mode overrides */
    .dark .lxc-card { background: #1f2937 !important; border-color: #374151 !important; }
    .dark .lxc-input { background: #374151 !important; border-color: #4b5563 !important; color: #f3f4f6 !important; }
  </style>
  <script>
    // Apply theme immediately to prevent flash
    (function() {
      const savedTheme = localStorage.getItem('ginto-theme');
      if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
      }
    })();
  </script>
</head>
<body class="min-h-screen bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
  <!-- Sidebar Overlay (mobile) -->
  <div id="sidebar-overlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

  <!-- Top Header -->
  <header class="border-b px-3 sm:px-4 py-3 flex items-center justify-between sticky top-0 z-30 bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700">
    <div class="flex items-center gap-2 sm:gap-4 min-w-0">
      <button id="menu-toggle" class="md:hidden p-2 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-sm flex-shrink-0" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
      </button>
      <a href="javascript:history.back()" class="flex items-center gap-2 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors flex-shrink-0">
        <i class="fas fa-arrow-left"></i>
        <span class="hidden sm:inline">Back</span>
      </a>
      <div class="h-6 w-px bg-gray-300 dark:bg-gray-700 hidden sm:block flex-shrink-0"></div>
      <div class="flex items-center gap-2 min-w-0">
        <i class="fas fa-cubes text-indigo-500 flex-shrink-0"></i>
        <h1 class="text-base sm:text-lg font-semibold truncate">LXC Manager</h1>
      </div>
    </div>
    <div class="flex items-center gap-3 sm:gap-4 flex-shrink-0">
      <!-- Connection Status (left) -->
      <div id="connection-status" class="hidden lg:flex items-center gap-2 text-sm">
        <span class="w-2 h-2 rounded-full bg-green-500"></span>
        <span class="text-gray-500 dark:text-gray-400">Connected</span>
      </div>
      
      <button onclick="openHostConsole()" class="p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white transition-colors text-sm flex items-center gap-2" title="Open Host Console">
        <i class="fas fa-terminal"></i>
        <span class="hidden sm:inline">Console</span>
      </button>
      
      <button id="refresh-all" class="p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white transition-colors text-sm flex items-center gap-2">
        <i class="fas fa-sync-alt"></i>
        <span class="hidden sm:inline">Refresh</span>
      </button>
      
      <!-- Star on GitHub -->
      <a href="https://github.com/nicDamours/ginto.ai" target="_blank" 
         class="hidden sm:flex items-center gap-2 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white transition-colors text-sm p-2"
         title="Star us on GitHub">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
        <span>Star us</span>
      </a>
      
      <!-- Theme Toggle (right edge) -->
      <button id="theme-toggle" class="p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white transition-colors" title="Toggle theme">
        <!-- Sun icon (shown in dark mode) -->
        <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
        </svg>
        <!-- Moon icon (shown in light mode) -->
        <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
        </svg>
      </button>
    </div>
  </header>

  <div class="flex h-[calc(100vh-57px)]">
    <!-- Left Sidebar - Resource Tree -->
    <aside id="sidebar" class="w-64 border-r flex flex-col flex-shrink-0 transition-transform duration-300 bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700">
      <!-- Search -->
      <div class="p-3 border-b border-gray-200 dark:border-gray-700">
        <div class="relative">
          <input type="text" id="resource-search" placeholder="Search resources..." 
            class="w-full lxc-input rounded-sm px-3 py-2 pl-9 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
        </div>
      </div>
      
      <!-- Resource Tree -->
      <div class="flex-1 overflow-y-auto p-2">
        <!-- Datacenter -->
        <div class="mb-4">
          <div class="resource-tree-item flex items-center gap-2 px-3 py-2 rounded cursor-pointer" data-type="datacenter" data-id="local">
            <i class="fas fa-server text-indigo-500 flex-shrink-0"></i>
            <span class="font-medium truncate">Datacenter</span>
          </div>
        </div>
        
        <!-- Storage -->
        <div class="mb-4">
          <div class="flex items-center justify-between px-3 py-1 text-xs text-gray-500 uppercase tracking-wider">
            <span>Storage</span>
            <span id="storage-count" class="bg-gray-200 dark:bg-gray-700 px-1.5 py-0.5 rounded">0</span>
          </div>
          <div id="storage-list" class="mt-1">
            <!-- Storage items will be populated here -->
          </div>
        </div>
        
        <!-- Images -->
        <div class="mb-4">
          <div class="flex items-center justify-between px-3 py-1 text-xs text-gray-500 uppercase tracking-wider">
            <span>Images</span>
            <span id="images-count" class="bg-gray-200 dark:bg-gray-700 px-1.5 py-0.5 rounded">0</span>
          </div>
          <div id="images-list" class="mt-1">
            <!-- Image items will be populated here -->
          </div>
        </div>
        
        <!-- Containers -->
        <div class="mb-4">
          <div class="flex items-center justify-between px-3 py-1 text-xs text-gray-500 uppercase tracking-wider">
            <span>Containers</span>
            <span id="containers-count" class="bg-gray-200 dark:bg-gray-700 px-1.5 py-0.5 rounded">0</span>
          </div>
          <div id="containers-list" class="mt-1">
            <!-- Container items will be populated here -->
          </div>
        </div>
      </div>
      
      <!-- Footer Stats -->
      <div class="p-3 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-500">
        <div class="flex justify-between mb-1">
          <span>CPU</span>
          <span id="cpu-usage">--</span>
        </div>
        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 mb-2">
          <div id="cpu-bar" class="bg-indigo-500 h-1.5 rounded-full" style="width: 0%"></div>
        </div>
        <div class="flex justify-between mb-1">
          <span>Memory</span>
          <span id="mem-usage">--</span>
        </div>
        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 mb-2">
          <div id="mem-bar" class="bg-green-500 h-1.5 rounded-full" style="width: 0%"></div>
        </div>
        <div class="flex justify-between mb-1">
          <span>Storage</span>
          <span id="disk-usage">--</span>
        </div>
        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
          <div id="disk-bar" class="bg-amber-500 h-1.5 rounded-full" style="width: 0%"></div>
        </div>
      </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 overflow-y-auto bg-gray-50 dark:bg-gray-900">
      <!-- Dashboard View (default) -->
      <div id="view-dashboard" class="p-4 sm:p-6">
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 mb-4 sm:mb-6">
          <div class="stat-card lxc-card rounded-sm p-3 sm:p-4 border">
            <div class="flex items-center justify-between mb-1 sm:mb-2">
              <span class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm truncate">Containers</span>
              <i class="fas fa-box text-indigo-500 flex-shrink-0"></i>
            </div>
            <div class="text-xl sm:text-2xl font-bold" id="stat-containers">0</div>
            <div class="text-xs text-gray-500 mt-1 truncate">
              <span id="stat-running" class="text-green-500">0 run</span> · 
              <span id="stat-stopped" class="text-gray-500">0 stop</span>
            </div>
          </div>
          
          <div class="stat-card lxc-card rounded-sm p-3 sm:p-4 border">
            <div class="flex items-center justify-between mb-1 sm:mb-2">
              <span class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm">Images</span>
              <i class="fas fa-layer-group text-amber-500 flex-shrink-0"></i>
            </div>
            <div class="text-xl sm:text-2xl font-bold" id="stat-images">0</div>
            <div class="text-xs text-gray-500 mt-1 truncate" id="stat-images-size">0 MB</div>
          </div>
          
          <div class="stat-card lxc-card rounded-sm p-3 sm:p-4 border">
            <div class="flex items-center justify-between mb-1 sm:mb-2">
              <span class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm truncate">Storage</span>
              <i class="fas fa-database text-green-500 flex-shrink-0"></i>
            </div>
            <div class="text-xl sm:text-2xl font-bold" id="stat-storage">0</div>
            <div class="text-xs text-gray-500 mt-1 truncate" id="stat-storage-used">-- used</div>
          </div>
          
          <a href="/admin/network" class="stat-card lxc-card rounded-sm p-3 sm:p-4 border block hover:border-cyan-500 transition-colors cursor-pointer">
            <div class="flex items-center justify-between mb-1 sm:mb-2">
              <span class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm">Networks</span>
              <i class="fas fa-network-wired text-cyan-500 flex-shrink-0"></i>
            </div>
            <div class="text-xl sm:text-2xl font-bold" id="stat-networks">0</div>
            <div class="text-xs text-gray-500 mt-1 truncate">Bridges</div>
          </a>
        </div>
        
        <!-- Quick Actions -->
        <div class="lxc-card rounded-sm p-3 sm:p-4 border mb-4 sm:mb-6">
          <h3 class="font-medium mb-2 sm:mb-3 text-sm sm:text-base">Quick Actions</h3>
          <div class="grid grid-cols-2 sm:flex sm:flex-wrap gap-2">
            <button onclick="showCreateContainerModal()" class="action-btn px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-sm text-xs sm:text-sm flex items-center justify-center gap-1 sm:gap-2">
              <i class="fas fa-plus"></i> <span>New</span>
            </button>
            <button onclick="pullImage()" class="action-btn px-3 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-sm text-xs sm:text-sm flex items-center justify-center gap-1 sm:gap-2">
              <i class="fas fa-download"></i> <span>Pull</span>
            </button>
            <button onclick="pruneResources()" class="action-btn px-3 py-2 bg-red-500 hover:bg-red-600 text-white rounded-sm text-xs sm:text-sm flex items-center justify-center gap-1 sm:gap-2">
              <i class="fas fa-broom"></i> <span>Prune</span>
            </button>
          </div>
        </div>
        
        <!-- Recent Containers Table -->
        <div class="lxc-card rounded-sm border overflow-hidden">
          <div class="px-3 sm:px-4 py-2 sm:py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h3 class="font-medium text-sm sm:text-base">Containers</h3>
            <div class="flex items-center gap-1 sm:gap-2">
              <button onclick="startAllContainers()" class="px-2 sm:px-3 py-1 text-xs text-white bg-green-500 hover:bg-green-600">Start</button>
              <button onclick="stopAllContainers()" class="px-2 sm:px-3 py-1 text-xs text-white bg-red-500 hover:bg-red-600">Stop</button>
            </div>
          </div>
          <!-- Mobile Card View -->
          <div id="containers-cards" class="mobile-card p-3 space-y-3">
            <div class="text-center text-gray-500 py-4">Loading containers...</div>
          </div>
          <!-- Desktop Table View -->
          <div class="overflow-x-auto">
            <table class="w-full text-sm desktop-table">
              <thead class="bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-xs uppercase">
                <tr>
                  <th class="px-3 sm:px-4 py-2 sm:py-3 text-left">Status</th>
                  <th class="px-3 sm:px-4 py-2 sm:py-3 text-left">Name</th>
                  <th class="px-3 sm:px-4 py-2 sm:py-3 text-left hidden lg:table-cell">Type</th>
                  <th class="px-3 sm:px-4 py-2 sm:py-3 text-left">IPv4</th>
                  <th class="px-3 sm:px-4 py-2 sm:py-3 text-left hidden xl:table-cell">RAM</th>
                  <th class="px-3 sm:px-4 py-2 sm:py-3 text-left hidden xl:table-cell">Disk</th>
                  <th class="px-3 sm:px-4 py-2 sm:py-3 text-left hidden 2xl:table-cell">CPU</th>
                  <th class="px-3 sm:px-4 py-2 sm:py-3 text-left hidden 2xl:table-cell">Procs</th>
                  <th class="px-3 sm:px-4 py-2 sm:py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody id="containers-table" class="divide-y divide-gray-200 dark:divide-gray-700">
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Loading containers...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Container Detail View -->
      <div id="view-container" class="p-4 sm:p-6 hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-0 mb-4 sm:mb-6">
          <div class="flex items-center gap-3 min-w-0">
            <button onclick="showDashboard()" class="p-2 hover:bg-gray-200 dark:hover:bg-gray-800 rounded-sm flex-shrink-0">
              <i class="fas fa-arrow-left"></i>
            </button>
            <div class="min-w-0">
              <h2 class="text-lg sm:text-xl font-semibold truncate" id="container-name">Container</h2>
              <div class="text-sm text-gray-400" id="container-status">Status</div>
            </div>
          </div>
          <div class="flex items-center gap-2 flex-wrap" id="container-actions">
            <!-- Actions populated by JS -->
          </div>
        </div>
        
        <!-- Container tabs -->
        <div class="border-b border-gray-200 dark:border-gray-700 mb-4 overflow-x-auto">
          <nav class="flex gap-2 sm:gap-4 min-w-max">
            <button class="container-tab px-3 sm:px-4 py-2 border-b-2 border-indigo-500 font-medium text-sm whitespace-nowrap" data-tab="summary">Summary</button>
            <button class="container-tab px-3 sm:px-4 py-2 border-b-2 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white text-sm whitespace-nowrap" data-tab="console">Console</button>
            <button class="container-tab px-3 sm:px-4 py-2 border-b-2 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white text-sm whitespace-nowrap" data-tab="network">Network</button>
            <button class="container-tab px-3 sm:px-4 py-2 border-b-2 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white text-sm whitespace-nowrap" data-tab="snapshots">Snapshots</button>
          </nav>
        </div>
        
        <div id="container-content">
          <!-- Content populated by JS -->
        </div>
      </div>

      <!-- Image Detail View -->
      <div id="view-image" class="p-4 sm:p-6 hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-0 mb-4 sm:mb-6">
          <div class="flex items-center gap-3 min-w-0">
            <button onclick="showDashboard()" class="p-2 hover:bg-gray-200 dark:hover:bg-gray-800 rounded-sm flex-shrink-0">
              <i class="fas fa-arrow-left"></i>
            </button>
            <div class="min-w-0">
              <h2 class="text-lg sm:text-xl font-semibold truncate" id="image-name">Image</h2>
              <div class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 truncate font-mono" id="image-fingerprint">Fingerprint</div>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button onclick="launchFromImage()" class="px-3 sm:px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-sm text-xs sm:text-sm flex items-center gap-1 sm:gap-2">
              <i class="fas fa-rocket"></i> <span class="hidden sm:inline">Launch</span><span class="sm:hidden">Launch</span>
            </button>
            <button onclick="deleteSelectedImage()" class="px-3 sm:px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-sm text-xs sm:text-sm flex items-center gap-1 sm:gap-2">
              <i class="fas fa-trash"></i> <span class="hidden sm:inline">Delete</span>
            </button>
          </div>
        </div>
        
        <div id="image-content" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <!-- Content populated by JS -->
        </div>
      </div>
    </main>
  </div>

  <!-- Create Container Modal -->
  <div id="create-container-modal" class="fixed inset-0 bg-black/70 z-50 hidden flex items-center justify-center p-4">
    <div class="lxc-card rounded-sm max-w-lg w-full border shadow-xl">
      <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <h3 class="text-lg font-semibold">Create Container</h3>
        <button onclick="closeModal('create-container-modal')" class="p-2 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-sm">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <form id="create-container-form" class="p-6 space-y-4">
        <div>
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">Container Name</label>
          <input type="text" name="name" required pattern="[a-z0-9-]+" 
            class="lxc-input w-full rounded-sm px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            placeholder="my-container">
          <p class="text-xs text-gray-500 mt-1">Lowercase letters, numbers, and hyphens only</p>
        </div>
        <div>
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">Base Image</label>
          <select name="image" required class="lxc-input w-full rounded-sm px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">Select an image...</option>
          </select>
        </div>
        <div>
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">Profile</label>
          <select name="profile" class="lxc-input w-full rounded-sm px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="default">default</option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <input type="checkbox" name="start" id="start-after-create" checked class="rounded bg-gray-200 dark:bg-gray-700 border-gray-300 dark:border-gray-600">
          <label for="start-after-create" class="text-sm">Start container after creation</label>
        </div>
        <div class="flex justify-end gap-3 pt-4">
          <button type="button" onclick="closeModal('create-container-modal')" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-sm">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-sm flex items-center gap-2">
            <i class="fas fa-plus"></i> Create
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Pull Image Modal -->
  <div id="pull-image-modal" class="fixed inset-0 bg-black/70 z-50 hidden flex items-center justify-center p-4">
    <div class="lxc-card rounded-sm max-w-lg w-full border shadow-xl">
      <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <h3 class="text-lg font-semibold">Pull Image</h3>
        <button onclick="closeModal('pull-image-modal')" class="p-2 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-sm">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <form id="pull-image-form" class="p-6 space-y-4">
        <div>
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">Image Source</label>
          <input type="text" name="image" required 
            class="lxc-input w-full rounded-sm px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            placeholder="images:alpine/3.18 or ubuntu:22.04">
          <p class="text-xs text-gray-500 mt-1">e.g., images:alpine/3.18, ubuntu:22.04, images:debian/12</p>
        </div>
        <div>
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">Alias (optional)</label>
          <input type="text" name="alias" 
            class="lxc-input w-full rounded-sm px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            placeholder="my-image-alias">
        </div>
        <div class="flex justify-end gap-3 pt-4">
          <button type="button" onclick="closeModal('pull-image-modal')" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-sm">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-sm flex items-center gap-2">
            <i class="fas fa-download"></i> Pull
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Confirmation Modal -->
  <div id="confirm-modal" class="fixed inset-0 bg-black/70 z-50 hidden flex items-center justify-center p-4">
    <div class="lxc-card rounded-sm max-w-md w-full border shadow-2xl">
      <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center gap-3">
        <div id="confirm-icon" class="w-10 h-10 rounded-full bg-red-600/20 flex items-center justify-center flex-shrink-0">
          <i class="fas fa-exclamation-triangle text-red-500"></i>
        </div>
        <h3 id="confirm-title" class="text-lg font-semibold">Confirm Action</h3>
      </div>
      <div class="p-6">
        <p id="confirm-message" class="text-gray-600 dark:text-gray-300">Are you sure you want to proceed?</p>
      </div>
      <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
        <button onclick="closeConfirmModal()" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-sm transition-colors">Cancel</button>
        <button id="confirm-btn" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-sm flex items-center gap-2 transition-colors">
          <i id="confirm-btn-icon" class="fas fa-trash"></i>
          <span id="confirm-btn-text">Delete</span>
        </button>
      </div>
    </div>
  </div>

  <!-- Toast Notifications -->
  <div id="toast-container" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"></div>

  <script>
  (function() {
    // State
    let containers = [];
    let images = [];
    let storage = [];
    let networks = [];
    let selectedContainer = null;
    let selectedImage = null;
    let csrfToken = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    
    // Mobile sidebar toggle
    window.toggleSidebar = function() {
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('sidebar-overlay');
      sidebar.classList.toggle('open');
      overlay.classList.toggle('open');
    };
    
    // Close sidebar when clicking a resource on mobile
    function closeSidebarOnMobile() {
      if (window.innerWidth < 768) {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
      }
    }
    
    // Confirmation modal
    let confirmCallback = null;
    
    function showConfirmModal(options) {
      const { title, message, confirmText, confirmIcon, confirmClass, onConfirm } = options;
      
      document.getElementById('confirm-title').textContent = title || 'Confirm Action';
      document.getElementById('confirm-message').textContent = message || 'Are you sure you want to proceed?';
      document.getElementById('confirm-btn-text').textContent = confirmText || 'Confirm';
      document.getElementById('confirm-btn-icon').className = 'fas ' + (confirmIcon || 'fa-check');
      
      const btn = document.getElementById('confirm-btn');
      btn.className = 'px-4 py-2 rounded-sm flex items-center gap-2 transition-colors ' + (confirmClass || 'bg-red-600 hover:bg-red-700');
      
      // Update icon color based on type
      const iconWrapper = document.getElementById('confirm-icon');
      if (confirmClass && confirmClass.includes('amber')) {
        iconWrapper.className = 'w-10 h-10 rounded-full bg-amber-600/20 flex items-center justify-center flex-shrink-0';
        iconWrapper.innerHTML = '<i class="fas fa-exclamation-circle text-amber-500"></i>';
      } else {
        iconWrapper.className = 'w-10 h-10 rounded-full bg-red-600/20 flex items-center justify-center flex-shrink-0';
        iconWrapper.innerHTML = '<i class="fas fa-exclamation-triangle text-red-500"></i>';
      }
      
      confirmCallback = onConfirm;
      document.getElementById('confirm-modal').classList.remove('hidden');
    }
    
    window.closeConfirmModal = function() {
      document.getElementById('confirm-modal').classList.add('hidden');
      confirmCallback = null;
    };
    
    document.getElementById('confirm-btn').addEventListener('click', function() {
      if (confirmCallback) {
        confirmCallback();
      }
      closeConfirmModal();
    });

    // Fetch CSRF token if not available
    async function ensureCsrfToken() {
      if (csrfToken) return csrfToken;
      try {
        const res = await fetch('/dev/csrf', { credentials: 'same-origin' });
        const data = await res.json();
        if (data.csrf_token) csrfToken = data.csrf_token;
      } catch (e) {
        console.error('Failed to fetch CSRF token:', e);
      }
      return csrfToken;
    }

    // API helpers
    async function api(endpoint, method = 'GET', body = null) {
      // For mutating requests, include CSRF token
      if (method !== 'GET' && !body) body = {};
      if (method !== 'GET' && typeof body === 'object') {
        await ensureCsrfToken();
        body.csrf_token = csrfToken;
      }
      
      const opts = {
        method,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' }
      };
      if (body) opts.body = JSON.stringify(body);
      const res = await fetch('/admin/api/lxc' + endpoint, opts);
      const text = await res.text();
      try {
        return JSON.parse(text);
      } catch (e) {
        console.error('API response not JSON:', text.substring(0, 500));
        return { success: false, error: 'Invalid response from server', raw: text.substring(0, 200) };
      }
    }

    function showToast(message, type = 'info') {
      const toast = document.createElement('div');
      const colors = {
        success: 'bg-green-600',
        error: 'bg-red-600',
        info: 'bg-indigo-600',
        warning: 'bg-amber-600'
      };
      toast.className = `${colors[type] || colors.info} text-white px-4 py-3 rounded-sm shadow-lg flex items-center gap-3 animate-pulse`;
      toast.innerHTML = `<span>${message}</span><button onclick="this.parentElement.remove()" class="ml-2 hover:opacity-75">&times;</button>`;
      document.getElementById('toast-container').appendChild(toast);
      setTimeout(() => toast.remove(), 5000);
    }

    // Load all data
    async function loadAll() {
      try {
        const [containersRes, imagesRes, storageRes, networksRes] = await Promise.all([
          api('/containers'),
          api('/images'),
          api('/storage'),
          api('/networks')
        ]);
        
        containers = containersRes.containers || [];
        images = imagesRes.images || [];
        storage = storageRes.storage || [];
        networks = networksRes.networks || [];
        
        // Show warnings if any
        const warnings = [containersRes, imagesRes, storageRes, networksRes]
          .filter(r => r.warning)
          .map(r => r.warning);
        if (warnings.length > 0) {
          showToast(warnings[0], 'warning');
        }
        
        // Show errors if any
        const errors = [containersRes, imagesRes, storageRes, networksRes]
          .filter(r => r.error)
          .map(r => r.error);
        if (errors.length > 0) {
          showToast(errors[0], 'error');
        }
        
        renderResourceTree();
        renderDashboard();
        updateStats();
      } catch (err) {
        showToast('Failed to load data: ' + err.message, 'error');
      }
    }

    function updateStats() {
      const running = containers.filter(c => c.status === 'Running').length;
      const stopped = containers.filter(c => c.status === 'Stopped').length;
      
      document.getElementById('stat-containers').textContent = containers.length;
      document.getElementById('stat-running').textContent = running + ' run';
      document.getElementById('stat-stopped').textContent = stopped + ' stop';
      document.getElementById('containers-count').textContent = containers.length;
      
      document.getElementById('stat-images').textContent = images.length;
      const totalSize = images.reduce((sum, img) => sum + (img.size || 0), 0);
      document.getElementById('stat-images-size').textContent = formatBytes(totalSize) + ' total';
      document.getElementById('images-count').textContent = images.length;
      
      document.getElementById('stat-storage').textContent = storage.length;
      document.getElementById('storage-count').textContent = storage.length;
      
      document.getElementById('stat-networks').textContent = networks.length;
      
      // Fetch host CPU/Memory stats
      updateHostStats();
    }
    
    async function updateHostStats() {
      try {
        const res = await api('/stats');
        if (res.success) {
          // Update CPU
          const cpuPercent = res.cpu?.percent || 0;
          const cpuCores = res.cpu?.cores || 1;
          document.getElementById('cpu-usage').textContent = cpuCores + ' cores · ' + cpuPercent + '%';
          document.getElementById('cpu-bar').style.width = cpuPercent + '%';
          
          // Update Memory
          const memPercent = res.memory?.percent || 0;
          const memUsed = res.memory?.used || 0;
          const memTotal = res.memory?.total || 0;
          document.getElementById('mem-usage').textContent = formatBytes(memUsed) + ' / ' + formatBytes(memTotal);
          document.getElementById('mem-bar').style.width = memPercent + '%';
          
          // Update Storage
          const diskPercent = res.disk?.percent || 0;
          const diskUsed = res.disk?.used || 0;
          const diskTotal = res.disk?.total || 0;
          document.getElementById('disk-usage').textContent = formatBytes(diskUsed) + ' / ' + formatBytes(diskTotal);
          document.getElementById('disk-bar').style.width = diskPercent + '%';
        }
      } catch (err) {
        console.error('Failed to fetch host stats:', err);
      }
    }
    
    // Update host stats every 5 seconds
    setInterval(updateHostStats, 5000);

    function formatBytes(bytes) {
      if (bytes === 0) return '0 B';
      const k = 1024;
      const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function renderResourceTree() {
      // Images
      const imagesList = document.getElementById('images-list');
      imagesList.innerHTML = images.map(img => `
        <div class="resource-tree-item flex items-center gap-2 px-3 py-1.5 rounded cursor-pointer text-sm min-w-0" 
             data-type="image" data-id="${img.fingerprint}" onclick="selectImage('${img.fingerprint}')">
          <i class="fas fa-layer-group text-amber-400 text-xs flex-shrink-0"></i>
          <span class="truncate flex-1 min-w-0">${img.alias || img.fingerprint.substring(0, 12)}</span>
          <span class="text-xs text-gray-500 flex-shrink-0">${formatBytes(img.size || 0)}</span>
        </div>
      `).join('');
      
      // Containers
      const containersList = document.getElementById('containers-list');
      containersList.innerHTML = containers.map(c => `
        <div class="resource-tree-item flex items-center gap-2 px-3 py-1.5 rounded cursor-pointer text-sm min-w-0" 
             data-type="container" data-id="${c.name}" onclick="selectContainer('${c.name}')">
          <i class="fas fa-${c.status === 'Running' ? 'play-circle text-green-400' : 'stop-circle text-gray-500'} text-xs flex-shrink-0"></i>
          <span class="truncate flex-1 min-w-0">${c.name}</span>
        </div>
      `).join('');
      
      // Storage
      const storageList = document.getElementById('storage-list');
      storageList.innerHTML = storage.map(s => `
        <div class="resource-tree-item flex items-center gap-2 px-3 py-1.5 rounded cursor-pointer text-sm min-w-0" 
             data-type="storage" data-id="${s.name}">
          <i class="fas fa-database text-green-400 text-xs flex-shrink-0"></i>
          <span class="truncate flex-1 min-w-0">${s.name}</span>
          <span class="text-xs text-gray-500 flex-shrink-0">${s.used_space && s.used_space !== 'N/A' ? s.used_space + ' / ' + s.total_space : ''}</span>
        </div>
      `).join('');
    }

    function renderDashboard() {
      const tbody = document.getElementById('containers-table');
      const cards = document.getElementById('containers-cards');
      
      if (containers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No containers found</td></tr>';
        cards.innerHTML = '<div class="text-center text-gray-500 py-4">No containers found</div>';
        return;
      }
      
      // Mobile card view
      cards.innerHTML = containers.map(c => `
        <div class="lxc-card rounded-sm p-3 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors border" onclick="selectContainer('${c.name}')">
          <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2 min-w-0 flex-1">
              <span class="w-2 h-2 rounded-full ${c.status === 'Running' ? 'bg-green-500' : 'bg-gray-500'} flex-shrink-0"></span>
              <span class="font-medium truncate">${c.name}</span>
            </div>
            <span class="text-xs ${c.status === 'Running' ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'} flex-shrink-0 ml-2">${c.status}</span>
          </div>
          <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
            <span class="truncate">${c.ipv4 || 'No IP'}</span>
            <div class="flex items-center gap-1 flex-shrink-0" onclick="event.stopPropagation()">
              ${c.status === 'Running' ? `
                <button onclick="containerAction('${c.name}', 'stop')" class="p-1.5 hover:bg-gray-200 dark:hover:bg-gray-600 rounded text-yellow-500 dark:text-yellow-400"><i class="fas fa-stop"></i></button>
                <button onclick="containerAction('${c.name}', 'restart')" class="p-1.5 hover:bg-gray-200 dark:hover:bg-gray-600 rounded text-blue-500 dark:text-blue-400"><i class="fas fa-redo"></i></button>
              ` : `
                <button onclick="containerAction('${c.name}', 'start')" class="p-1.5 hover:bg-gray-200 dark:hover:bg-gray-600 rounded text-green-500 dark:text-green-400"><i class="fas fa-play"></i></button>
              `}
              <button onclick="containerAction('${c.name}', 'delete')" class="p-1.5 hover:bg-gray-200 dark:hover:bg-gray-600 rounded text-red-500 dark:text-red-400"><i class="fas fa-trash"></i></button>
            </div>
          </div>
        </div>
      `).join('');
      
      // Desktop table view
      tbody.innerHTML = containers.map(c => `
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-200 dark:border-gray-700" onclick="selectContainer('${c.name}')">
          <td class="px-3 sm:px-4 py-2 sm:py-3">
            <span class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full ${c.status === 'Running' ? 'bg-green-500' : 'bg-gray-500'}"></span>
              <span class="${c.status === 'Running' ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'}">${c.status}</span>
            </span>
          </td>
          <td class="px-3 sm:px-4 py-2 sm:py-3 font-medium max-w-[150px] truncate">${c.name}</td>
          <td class="px-3 sm:px-4 py-2 sm:py-3 text-gray-500 dark:text-gray-400 hidden lg:table-cell">${c.type || 'container'}</td>
          <td class="px-3 sm:px-4 py-2 sm:py-3 text-gray-500 dark:text-gray-400 max-w-[120px] truncate">${c.ipv4 || '-'}</td>
          <td class="px-3 sm:px-4 py-2 sm:py-3 text-gray-500 dark:text-gray-400 hidden xl:table-cell">
            <span class="text-white">${c.memory || '-'}</span><span class="text-gray-500"> / ${c.limits?.memory || '-'}</span>
          </td>
          <td class="px-3 sm:px-4 py-2 sm:py-3 text-gray-500 dark:text-gray-400 hidden xl:table-cell">
            <span class="text-white">${c.disk || '-'}</span><span class="text-gray-500"> / ${c.limits?.disk || '-'}</span>
          </td>
          <td class="px-3 sm:px-4 py-2 sm:py-3 text-gray-500 dark:text-gray-400 hidden 2xl:table-cell">${c.limits?.cpu || '-'}</td>
          <td class="px-3 sm:px-4 py-2 sm:py-3 text-gray-500 dark:text-gray-400 hidden 2xl:table-cell">${c.limits?.processes || '-'}</td>
          <td class="px-3 sm:px-4 py-2 sm:py-3 text-right">
            <div class="flex items-center justify-end gap-1" onclick="event.stopPropagation()">
              ${c.status === 'Running' ? `
                <button onclick="openLxcConsole('${c.name}')" class="p-1.5 hover:bg-gray-200 dark:hover:bg-gray-700 rounded text-green-500 dark:text-green-400" title="Console">
                  <i class="fas fa-terminal"></i>
                </button>
                <button onclick="containerAction('${c.name}', 'stop')" class="p-1.5 hover:bg-gray-200 dark:hover:bg-gray-700 rounded text-yellow-500 dark:text-yellow-400" title="Stop">
                  <i class="fas fa-stop"></i>
                </button>
                <button onclick="containerAction('${c.name}', 'restart')" class="p-1.5 hover:bg-gray-200 dark:hover:bg-gray-700 rounded text-blue-500 dark:text-blue-400" title="Restart">
                  <i class="fas fa-redo"></i>
                </button>
              ` : `
                <button onclick="containerAction('${c.name}', 'start')" class="p-1.5 hover:bg-gray-200 dark:hover:bg-gray-700 rounded text-green-500 dark:text-green-400" title="Start">
                  <i class="fas fa-play"></i>
                </button>
              `}
              <button onclick="containerAction('${c.name}', 'delete')" class="p-1.5 hover:bg-gray-200 dark:hover:bg-gray-700 rounded text-red-500 dark:text-red-400" title="Delete">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </td>
        </tr>
      `).join('');
    }

    window.selectContainer = async function(name) {
      selectedContainer = containers.find(c => c.name === name);
      if (!selectedContainer) return;
      
      closeSidebarOnMobile();
      
      document.getElementById('view-dashboard').classList.add('hidden');
      document.getElementById('view-image').classList.add('hidden');
      document.getElementById('view-container').classList.remove('hidden');
      
      document.getElementById('container-name').textContent = selectedContainer.name;
      document.getElementById('container-status').innerHTML = `
        <span class="${selectedContainer.status === 'Running' ? 'text-green-400' : 'text-gray-400'}">
          <i class="fas fa-${selectedContainer.status === 'Running' ? 'play-circle' : 'stop-circle'} mr-1"></i>
          ${selectedContainer.status}
        </span>
      `;
      
      const actions = document.getElementById('container-actions');
      actions.innerHTML = selectedContainer.status === 'Running' ? `
        <button onclick="containerAction('${name}', 'stop')" class="px-3 sm:px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-sm text-xs sm:text-sm flex items-center gap-1 sm:gap-2">
          <i class="fas fa-stop"></i> <span class="hidden sm:inline">Stop</span>
        </button>
        <button onclick="containerAction('${name}', 'restart')" class="px-3 sm:px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-sm text-xs sm:text-sm flex items-center gap-1 sm:gap-2">
          <i class="fas fa-redo"></i> <span class="hidden sm:inline">Restart</span>
        </button>
        <button onclick="openContainerConsole('${name}')" class="px-3 sm:px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-sm text-xs sm:text-sm flex items-center gap-1 sm:gap-2">
          <i class="fas fa-terminal"></i> <span class="hidden sm:inline">Console</span>
        </button>
      ` : `
        <button onclick="containerAction('${name}', 'start')" class="px-3 sm:px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-sm text-xs sm:text-sm flex items-center gap-1 sm:gap-2">
          <i class="fas fa-play"></i> <span class="hidden sm:inline">Start</span>
        </button>
      `;
      actions.innerHTML += `
        <button onclick="containerAction('${name}', 'delete')" class="px-3 sm:px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-sm text-xs sm:text-sm flex items-center gap-1 sm:gap-2">
          <i class="fas fa-trash"></i> <span class="hidden sm:inline">Delete</span>
        </button>
      `;
      
      // Load container details
      try {
        const detail = await api('/containers/' + name);
        renderContainerDetail(detail);
      } catch (err) {
        showToast('Failed to load container details', 'error');
      }
    };

    function renderContainerDetail(detail) {
      const content = document.getElementById('container-content');
      content.innerHTML = `
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 sm:gap-4">
          <div class="lxc-card rounded-sm p-3 sm:p-4 border">
            <h4 class="font-medium mb-2 sm:mb-3 text-sm sm:text-base">Configuration</h4>
            <div class="space-y-2 text-xs sm:text-sm">
              <div class="flex justify-between gap-2"><span class="text-gray-500 dark:text-gray-400 flex-shrink-0">Architecture:</span><span class="truncate text-right">${detail.architecture || '-'}</span></div>
              <div class="flex justify-between gap-2"><span class="text-gray-500 dark:text-gray-400 flex-shrink-0">Created:</span><span class="truncate text-right">${detail.created_at ? new Date(detail.created_at).toLocaleString() : '-'}</span></div>
              <div class="flex justify-between gap-2"><span class="text-gray-500 dark:text-gray-400 flex-shrink-0">Last Used:</span><span class="truncate text-right">${detail.last_used_at ? new Date(detail.last_used_at).toLocaleString() : '-'}</span></div>
              <div class="flex justify-between gap-2"><span class="text-gray-500 dark:text-gray-400 flex-shrink-0">Profiles:</span><span class="truncate text-right">${(detail.profiles || []).join(', ') || '-'}</span></div>
            </div>
          </div>
          <div class="lxc-card rounded-sm p-3 sm:p-4 border">
            <h4 class="font-medium mb-2 sm:mb-3 text-sm sm:text-base">Resources</h4>
            <div class="space-y-2 text-xs sm:text-sm">
              <div class="flex justify-between gap-2"><span class="text-gray-500 dark:text-gray-400 flex-shrink-0">Memory Limit:</span><span class="text-right">${detail.config?.['limits.memory'] || 'Unlimited'}</span></div>
              <div class="flex justify-between gap-2"><span class="text-gray-500 dark:text-gray-400 flex-shrink-0">CPU Limit:</span><span class="text-right">${detail.config?.['limits.cpu'] || 'Unlimited'}</span></div>
              <div class="flex justify-between gap-2"><span class="text-gray-500 dark:text-gray-400 flex-shrink-0">Disk Limit:</span><span class="text-right">${detail.devices?.root?.size || 'Unlimited'}</span></div>
            </div>
          </div>
        </div>
      `;
    }

    window.selectImage = async function(fingerprint) {
      selectedImage = images.find(i => i.fingerprint === fingerprint);
      if (!selectedImage) return;
      
      closeSidebarOnMobile();
      
      document.getElementById('view-dashboard').classList.add('hidden');
      document.getElementById('view-container').classList.add('hidden');
      document.getElementById('view-image').classList.remove('hidden');
      
      document.getElementById('image-name').textContent = selectedImage.alias || 'Image';
      document.getElementById('image-fingerprint').textContent = fingerprint;
      
      const content = document.getElementById('image-content');
      content.innerHTML = `
        <div class="lxc-card rounded-sm p-3 sm:p-4 border">
          <h4 class="font-medium mb-2 sm:mb-3 text-sm sm:text-base">Image Details</h4>
          <div class="space-y-2 text-xs sm:text-sm">
            <div class="flex justify-between gap-2"><span class="text-gray-500 dark:text-gray-400 flex-shrink-0">Alias:</span><span class="truncate text-right">${selectedImage.alias || '-'}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 dark:text-gray-400 flex-shrink-0">Fingerprint:</span><span class="font-mono text-xs truncate text-right">${fingerprint.substring(0, 12)}...</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 dark:text-gray-400 flex-shrink-0">Size:</span><span class="text-right">${formatBytes(selectedImage.size || 0)}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 dark:text-gray-400 flex-shrink-0">Description:</span><span class="truncate text-right">${selectedImage.description || '-'}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 dark:text-gray-400 flex-shrink-0">Architecture:</span><span class="text-right">${selectedImage.architecture || '-'}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 dark:text-gray-400 flex-shrink-0">Type:</span><span class="text-right">${selectedImage.type || '-'}</span></div>
            <div class="flex justify-between gap-2"><span class="text-gray-500 dark:text-gray-400 flex-shrink-0">Uploaded:</span><span class="truncate text-right">${selectedImage.uploaded_at ? new Date(selectedImage.uploaded_at).toLocaleString() : '-'}</span></div>
          </div>
        </div>
        <div class="lxc-card rounded-sm p-3 sm:p-4 border">
          <h4 class="font-medium mb-2 sm:mb-3 text-sm sm:text-base">Properties</h4>
          <div class="space-y-2 text-xs sm:text-sm">
            ${Object.entries(selectedImage.properties || {}).map(([k, v]) => `
              <div class="flex justify-between gap-2"><span class="text-gray-500 dark:text-gray-400 flex-shrink-0 truncate max-w-[40%]">${k}:</span><span class="truncate text-right">${v}</span></div>
            `).join('') || '<span class="text-gray-500">No properties</span>'}
          </div>
        </div>
      `;
    };

    window.showDashboard = function() {
      document.getElementById('view-dashboard').classList.remove('hidden');
      document.getElementById('view-container').classList.add('hidden');
      document.getElementById('view-image').classList.add('hidden');
      selectedContainer = null;
      selectedImage = null;
    };

    window.containerAction = async function(name, action) {
      if (action === 'delete') {
        showConfirmModal({
          title: 'Delete Container',
          message: `Are you sure you want to delete container "${name}"? This action cannot be undone.`,
          confirmText: 'Delete',
          confirmIcon: 'fa-trash',
          confirmClass: 'bg-red-600 hover:bg-red-700',
          onConfirm: () => executeContainerAction(name, action)
        });
        return;
      }
      executeContainerAction(name, action);
    };
    
    async function executeContainerAction(name, action) {
      try {
        showToast(`${action.charAt(0).toUpperCase() + action.slice(1)}ing container...`, 'info');
        const res = await api('/containers/' + name + '/' + action, 'POST');
        if (res.success) {
          showToast(`Container ${action}ed successfully`, 'success');
          await loadAll();
          if (action === 'delete') showDashboard();
        } else {
          showToast(res.error || `Failed to ${action} container`, 'error');
        }
      } catch (err) {
        showToast(`Failed to ${action} container: ${err.message}`, 'error');
      }
    }

    window.showCreateContainerModal = function() {
      const select = document.querySelector('#create-container-form select[name="image"]');
      select.innerHTML = '<option value="">Select an image...</option>' + 
        images.map(img => `<option value="${img.alias || img.fingerprint}">${img.alias || img.fingerprint.substring(0, 12)} (${formatBytes(img.size || 0)})</option>`).join('');
      document.getElementById('create-container-modal').classList.remove('hidden');
    };

    window.pullImage = function() {
      document.getElementById('pull-image-modal').classList.remove('hidden');
    };

    window.closeModal = function(id) {
      document.getElementById(id).classList.add('hidden');
    };

    window.deleteSelectedImage = async function() {
      if (!selectedImage) return;
      
      const imageName = selectedImage.alias || selectedImage.fingerprint.substring(0, 12);
      showConfirmModal({
        title: 'Delete Image',
        message: `Are you sure you want to delete image "${imageName}"? This action cannot be undone.`,
        confirmText: 'Delete',
        confirmIcon: 'fa-trash',
        confirmClass: 'bg-red-600 hover:bg-red-700',
        onConfirm: async () => {
          try {
            const res = await api('/images/' + selectedImage.fingerprint, 'DELETE');
            if (res.success) {
              showToast('Image deleted successfully', 'success');
              await loadAll();
              showDashboard();
            } else {
              showToast(res.error || 'Failed to delete image', 'error');
            }
          } catch (err) {
            showToast('Failed to delete image: ' + err.message, 'error');
          }
        }
      });
    };

    window.launchFromImage = function() {
      if (!selectedImage) return;
      showCreateContainerModal();
      const select = document.querySelector('#create-container-form select[name="image"]');
      select.value = selectedImage.alias || selectedImage.fingerprint;
    };

    window.pruneResources = async function() {
      showConfirmModal({
        title: 'Prune Resources',
        message: 'This will analyze unused images and stopped containers. Continue?',
        confirmText: 'Prune',
        confirmIcon: 'fa-broom',
        confirmClass: 'bg-amber-600 hover:bg-amber-700',
        onConfirm: async () => {
          try {
            const res = await api('/prune', 'POST');
            showToast(res.message || 'Pruned successfully', 'success');
            await loadAll();
          } catch (err) {
            showToast('Prune failed: ' + err.message, 'error');
          }
        }
      });
    };

    window.openConsole = function() {
      if (typeof window.openConsoleWithCommand === 'function') {
        window.openConsoleWithCommand('');
      } else {
        window.open('/chat', '_blank');
      }
    };

    window.openContainerConsole = function(name) {
      if (typeof window.openConsoleWithCommand === 'function') {
        window.openConsoleWithCommand('sudo lxc exec ' + name + ' -- /bin/bash');
      }
    };

    // Form handlers
    document.getElementById('create-container-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.target;
      const data = {
        name: form.name.value,
        image: form.image.value,
        profile: form.profile.value,
        start: form.start.checked
      };
      
      try {
        showToast('Creating container...', 'info');
        const res = await api('/containers', 'POST', data);
        if (res.success) {
          showToast('Container created successfully', 'success');
          closeModal('create-container-modal');
          form.reset();
          await loadAll();
        } else {
          showToast(res.error || 'Failed to create container', 'error');
        }
      } catch (err) {
        showToast('Failed to create container: ' + err.message, 'error');
      }
    });

    document.getElementById('pull-image-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.target;
      const data = {
        image: form.image.value,
        alias: form.alias.value
      };
      
      try {
        showToast('Pulling image... This may take a while.', 'info');
        const res = await api('/images/pull', 'POST', data);
        if (res.success) {
          showToast('Image pulled successfully', 'success');
          closeModal('pull-image-modal');
          form.reset();
          await loadAll();
        } else {
          showToast(res.error || 'Failed to pull image', 'error');
        }
      } catch (err) {
        showToast('Failed to pull image: ' + err.message, 'error');
      }
    });

    // Refresh button
    document.getElementById('refresh-all').addEventListener('click', loadAll);

    // Search
    document.getElementById('resource-search').addEventListener('input', (e) => {
      const query = e.target.value.toLowerCase();
      document.querySelectorAll('.resource-tree-item').forEach(el => {
        const text = el.textContent.toLowerCase();
        el.style.display = text.includes(query) ? '' : 'none';
      });
    });

    // Initialize
    loadAll();
    setInterval(loadAll, 30000); // Refresh every 30 seconds
    
    // Theme toggle functionality
    (function initTheme() {
      const html = document.documentElement;
      const themeToggle = document.getElementById('theme-toggle');
      
      // Check for saved preference or system preference
      const savedTheme = localStorage.getItem('ginto-theme');
      if (savedTheme) {
        html.classList.toggle('dark', savedTheme === 'dark');
      } else {
        // Default to system preference (or dark)
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        html.classList.toggle('dark', prefersDark);
      }
      
      // Toggle theme function
      themeToggle?.addEventListener('click', function() {
        const isDark = html.classList.toggle('dark');
        localStorage.setItem('ginto-theme', isDark ? 'dark' : 'light');
      });
    })();
  })();
  </script>

  <!-- Console Terminal Modal (Full Screen) -->
  <div id="console-modal" class="fixed inset-0 bg-black/50 dark:bg-black/90 z-50 hidden flex items-center justify-center">
    <div class="w-full h-full flex flex-col">
      <!-- Modal Header -->
      <div class="bg-gray-900 border-b border-gray-700 px-4 py-2 flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-3">
          <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z"/>
          </svg>
          <h3 class="text-white font-semibold">Console</h3>
          <span id="console-container-name" class="text-sm text-gray-400"></span>
        </div>
        <div class="flex items-center gap-2">
          <button id="console-reconnect" class="px-3 py-1.5 text-sm bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors">
            Reconnect
          </button>
          <button id="minimize-console" class="p-2 rounded-lg hover:bg-gray-800 text-white" title="Minimize">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14H5"/>
            </svg>
          </button>
          <button id="close-console" class="p-2 rounded-lg hover:bg-gray-800 text-white" title="Close">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>
      <!-- Tab Bar -->
      <div class="bg-gray-800 border-b border-gray-700 px-2 py-1 flex items-center gap-1 flex-shrink-0 overflow-x-auto">
        <div id="console-tabs" class="flex items-center gap-1">
        </div>
        <button id="add-console-tab" class="p-1.5 rounded hover:bg-gray-700 text-gray-400 hover:text-white transition-colors" title="New Tab">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
        </button>
        <span id="console-status" class="ml-auto text-xs px-2 py-0.5 rounded-full bg-gray-700 text-gray-400">Disconnected</span>
      </div>
      <!-- Terminal Container -->
      <div id="console-terminals" class="flex-1 w-full bg-black relative">
      </div>
    </div>
  </div>

  <!-- Minimized Console Indicator (Floating) -->
  <div id="console-minimized" class="fixed bottom-4 right-4 z-50 hidden">
    <button id="restore-console" class="flex items-center gap-2 px-4 py-2 bg-gray-900 hover:bg-gray-800 text-white rounded-lg shadow-lg border border-gray-700 transition-all">
      <svg class="w-4 h-4 text-green-400 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z"/>
      </svg>
      <span class="text-sm font-medium">Console Running</span>
      <span id="console-minimized-status" class="text-xs px-1.5 py-0.5 rounded bg-green-600 text-white">●</span>
    </button>
  </div>

  <!-- xterm.js for Console -->
  <link rel="stylesheet" href="/lib/xterm/xterm.css" />
  <script src="/lib/xterm/xterm.js"></script>
  <script src="/lib/xterm/xterm-addon-fit.min.js"></script>
  <script>
  (function() {
    const modal = document.getElementById('console-modal');
    const terminalsContainer = document.getElementById('console-terminals');
    const tabsContainer = document.getElementById('console-tabs');
    const addTabBtn = document.getElementById('add-console-tab');
    const closeBtn = document.getElementById('close-console');
    const reconnectBtn = document.getElementById('console-reconnect');
    const statusEl = document.getElementById('console-status');
    const containerNameEl = document.getElementById('console-container-name');
    const minimizeBtn = document.getElementById('minimize-console');
    const restoreBtn = document.getElementById('restore-console');
    const minimizedIndicator = document.getElementById('console-minimized');
    const minimizedStatus = document.getElementById('console-minimized-status');
    
    if (!modal || !terminalsContainer) return;
    
    let tabs = [];
    let activeTabId = null;
    let tabCounter = 0;
    let currentContainer = null;
    let isMinimized = false;
    const PING_INTERVAL_MS = 25000;
    const RECONNECT_DELAY_MS = 2000;
    
    function updateStatus(status, color) {
      statusEl.textContent = status;
      statusEl.className = 'ml-auto text-xs px-2 py-0.5 rounded-full ' + color;
      if (minimizedStatus) {
        if (status === 'Connected') {
          minimizedStatus.className = 'text-xs px-1.5 py-0.5 rounded bg-green-600 text-white';
          minimizedStatus.textContent = '●';
        } else {
          minimizedStatus.className = 'text-xs px-1.5 py-0.5 rounded bg-yellow-600 text-white';
          minimizedStatus.textContent = '○';
        }
      }
    }
    
    function minimizeConsole() {
      isMinimized = true;
      modal.classList.add('hidden');
      if (minimizedIndicator) minimizedIndicator.classList.remove('hidden');
    }
    
    function restoreConsole() {
      isMinimized = false;
      if (minimizedIndicator) minimizedIndicator.classList.add('hidden');
      modal.classList.remove('hidden');
      const activeTab = tabs.find(t => t.id === activeTabId);
      if (activeTab && activeTab.fitAddon && activeTab.term) {
        setTimeout(() => activeTab.fitAddon.fit(), 100);
      }
    }
    
    if (minimizeBtn) minimizeBtn.onclick = minimizeConsole;
    if (restoreBtn) restoreBtn.onclick = restoreConsole;
    
    function createTab(containerName, initialCommand) {
      tabCounter++;
      const tabId = 'tab-' + tabCounter;
      
      const tabBtn = document.createElement('div');
      tabBtn.className = 'flex items-center gap-1 px-3 py-1.5 rounded-t bg-gray-700 hover:bg-gray-600 cursor-pointer text-sm text-white transition-colors';
      tabBtn.dataset.tabId = tabId;
      tabBtn.innerHTML = `
        <span class="tab-title">${containerName || 'Terminal'} ${tabCounter}</span>
        <button class="tab-close ml-1 p-0.5 rounded hover:bg-gray-500 text-gray-400 hover:text-white" data-close-tab="${tabId}">
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      `;
      tabsContainer.appendChild(tabBtn);
      
      const termEl = document.createElement('div');
      termEl.className = 'absolute inset-0 hidden';
      termEl.id = tabId + '-terminal';
      terminalsContainer.appendChild(termEl);
      
      const term = new window.Terminal({
        cols: 120,
        rows: 30,
        cursorBlink: true,
        fontSize: 14,
        fontFamily: 'Menlo, Monaco, "Courier New", monospace',
        theme: {
          background: '#0d1117',
          foreground: '#c9d1d9',
          cursor: '#58a6ff',
          cursorAccent: '#0d1117',
          black: '#0d1117',
          red: '#ff7b72',
          green: '#3fb950',
          yellow: '#d29922',
          blue: '#58a6ff',
          magenta: '#bc8cff',
          cyan: '#39c5cf',
          white: '#b1bac4'
        }
      });
      
      const fitAddon = new window.FitAddon.FitAddon();
      term.loadAddon(fitAddon);
      term.open(termEl);
      
      const tab = {
        id: tabId,
        term: term,
        termEl: termEl,
        tabBtn: tabBtn,
        fitAddon: fitAddon,
        ws: null,
        pingInterval: null,
        reconnectTimeout: null,
        autoReconnect: true,
        containerName: containerName,
        pendingCommand: initialCommand || null
      };
      
      tabs.push(tab);
      
      term.onData(function(data) {
        if (tab.ws && tab.ws.readyState === WebSocket.OPEN) {
          tab.ws.send(data);
        }
      });
      
      tabBtn.addEventListener('click', function(e) {
        if (e.target.closest('.tab-close')) return;
        switchToTab(tabId);
      });
      
      tabBtn.querySelector('.tab-close').addEventListener('click', function(e) {
        e.stopPropagation();
        closeTab(tabId);
      });
      
      switchToTab(tabId);
      connectTab(tab);
      
      return tab;
    }
    
    function switchToTab(tabId) {
      tabs.forEach(t => {
        if (t.id === tabId) {
          t.termEl.classList.remove('hidden');
          t.tabBtn.classList.add('bg-gray-600');
          t.tabBtn.classList.remove('bg-gray-700');
          activeTabId = tabId;
          currentContainer = t.containerName;
          containerNameEl.textContent = t.containerName ? '(' + t.containerName + ')' : '';
          setTimeout(() => {
            t.fitAddon.fit();
            t.term.focus();
          }, 50);
          if (t.ws && t.ws.readyState === WebSocket.OPEN) {
            updateStatus('Connected', 'bg-green-600 text-green-100');
          } else {
            updateStatus('Disconnected', 'bg-gray-700 text-gray-400');
          }
        } else {
          t.termEl.classList.add('hidden');
          t.tabBtn.classList.remove('bg-gray-600');
          t.tabBtn.classList.add('bg-gray-700');
        }
      });
    }
    
    function closeTab(tabId) {
      const tabIndex = tabs.findIndex(t => t.id === tabId);
      if (tabIndex === -1) return;
      
      const tab = tabs[tabIndex];
      if (tab.pingInterval) clearInterval(tab.pingInterval);
      if (tab.reconnectTimeout) clearTimeout(tab.reconnectTimeout);
      if (tab.ws) tab.ws.close();
      tab.term.dispose();
      tab.termEl.remove();
      tab.tabBtn.remove();
      
      tabs.splice(tabIndex, 1);
      
      if (activeTabId === tabId) {
        if (tabs.length > 0) {
          switchToTab(tabs[tabs.length - 1].id);
        } else {
          activeTabId = null;
        }
      }
      
      if (tabs.length === 0) {
        closeConsole();
      }
    }
    
    function connectTab(tab) {
      if (tab.ws && tab.ws.readyState === WebSocket.OPEN) return;
      if (tab.reconnectTimeout) { clearTimeout(tab.reconnectTimeout); tab.reconnectTimeout = null; }
      
      if (tab.id === activeTabId) {
        updateStatus('Connecting...', 'bg-yellow-600 text-yellow-100');
      }
      
      const host = window.location.hostname || '127.0.0.1';
      const cols = tab.term.cols;
      const rows = tab.term.rows;
      const wsUrl = (location.protocol === 'https:' ? 'wss://' : 'ws://') + host + '/terminal/terminal?mode=os&cols=' + cols + '&rows=' + rows;
      
      tab.ws = new WebSocket(wsUrl);
      tab.ws.binaryType = 'arraybuffer';
      
      tab.ws.addEventListener('open', function() {
        if (tab.id === activeTabId) {
          updateStatus('Connected', 'bg-green-600 text-green-100');
        }
        tab.term.write('\r\n\x1b[32m*** Connected to host terminal ***\x1b[0m\r\n\r\n');
        
        tab.pingInterval = setInterval(function() {
          if (tab.ws && tab.ws.readyState === WebSocket.OPEN) {
            try { tab.ws.send(JSON.stringify({ type: 'ping' })); } catch(e) {}
          }
        }, PING_INTERVAL_MS);
        
        // If we have a container, exec into it
        if (tab.containerName) {
          setTimeout(function() {
            tab.ws.send('sudo lxc exec ' + tab.containerName + ' -- /bin/sh\n');
          }, 300);
        }
        
        if (tab.pendingCommand) {
          setTimeout(function() {
            tab.ws.send(tab.pendingCommand + '\n');
            tab.pendingCommand = null;
          }, 800);
        }
      });
      
      tab.ws.addEventListener('message', function(e) {
        try {
          tab.term.write(typeof e.data === 'string' ? e.data : new TextDecoder().decode(e.data));
        } catch(err) {}
      });
      
      tab.ws.addEventListener('close', function() {
        if (tab.pingInterval) { clearInterval(tab.pingInterval); tab.pingInterval = null; }
        if (tab.id === activeTabId) {
          updateStatus('Disconnected', 'bg-gray-700 text-gray-400');
        }
        tab.term.write('\r\n\x1b[31m*** Disconnected ***\x1b[0m\r\n');
        
        if (tab.autoReconnect && !modal.classList.contains('hidden')) {
          tab.reconnectTimeout = setTimeout(function() {
            connectTab(tab);
          }, RECONNECT_DELAY_MS);
        }
      });
      
      tab.ws.addEventListener('error', function() {
        if (tab.pingInterval) { clearInterval(tab.pingInterval); tab.pingInterval = null; }
        if (tab.id === activeTabId) {
          updateStatus('Error', 'bg-red-600 text-red-100');
        }
      });
    }
    
    function openConsole(containerName, initialCommand) {
      modal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
      currentContainer = containerName;
      
      if (tabs.length === 0) {
        createTab(containerName, initialCommand);
      } else {
        const activeTab = tabs.find(t => t.id === activeTabId);
        if (activeTab) {
          setTimeout(() => {
            activeTab.fitAddon.fit();
            activeTab.term.focus();
          }, 100);
        }
      }
    }
    
    window.openLxcConsole = function(containerName) {
      modal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
      createTab(containerName);
    };
    
    window.openHostConsole = function() {
      modal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
      createTab(null);
    };
    
    function closeConsole() {
      modal.classList.add('hidden');
      if (minimizedIndicator) minimizedIndicator.classList.add('hidden');
      document.body.style.overflow = '';
      isMinimized = false;
      
      tabs.forEach(tab => {
        tab.autoReconnect = false;
        if (tab.pingInterval) clearInterval(tab.pingInterval);
        if (tab.reconnectTimeout) clearTimeout(tab.reconnectTimeout);
        if (tab.ws) tab.ws.close();
        tab.term.dispose();
        tab.termEl.remove();
        tab.tabBtn.remove();
      });
      tabs = [];
      activeTabId = null;
      currentContainer = null;
    }
    
    closeBtn.addEventListener('click', closeConsole);
    
    reconnectBtn.addEventListener('click', function() {
      const activeTab = tabs.find(t => t.id === activeTabId);
      if (activeTab) {
        if (activeTab.ws) activeTab.ws.close();
        activeTab.ws = null;
        setTimeout(() => connectTab(activeTab), 100);
      }
    });
    
    addTabBtn.addEventListener('click', function() {
      createTab(currentContainer);
    });
    
    window.addEventListener('resize', function() {
      if (modal.classList.contains('hidden')) return;
      tabs.forEach(tab => {
        tab.fitAddon.fit();
        if (tab.ws && tab.ws.readyState === WebSocket.OPEN) {
          tab.ws.send(JSON.stringify({ type: 'resize', cols: tab.term.cols, rows: tab.term.rows }));
        }
      });
    });
    
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
        closeConsole();
      }
    });
  })();
  </script>
</body>
</html>
