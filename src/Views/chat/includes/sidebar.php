<?php
/**
 * Sidebar Navigation
 * Collapsible sidebar with navigation items and conversation list
 */
?>
<!-- Sidebar - Collapsible design -->
<aside id="sidebar" class="sidebar-expanded w-64 bg-white dark:bg-gray-900 flex flex-col fixed inset-y-0 left-0 z-50 sidebar-transition lg:translate-x-0 -translate-x-full text-gray-900 dark:text-gray-100 overflow-hidden border-r border-gray-200 dark:border-gray-800" role="navigation" aria-label="Main navigation">
  
  <!-- Top Header Row - Always visible with logo -->
  <div class="sidebar-header py-2">
    <!-- Logo and controls - always visible -->
    <div class="flex items-center justify-between">
      <!-- Logo and text separated for consistency -->
      <div class="flex items-center gap-2">
        <!-- Logo with chevron overlay for expand toggle (when collapsed) -->
        <div class="relative group">
          <a href="/" id="logo-link" class="block" title="Ginto Home">
            <img src="/assets/images/ginto.png" alt="Ginto" class="w-7 h-7 rounded flex-shrink-0" onerror="this.style.display='none'">
          </a>
          <!-- Chevron overlay (desktop only, expand - shown when collapsed) -->
          <button id="sidebar-expand-toggle" class="hidden absolute inset-0 w-7 h-7 items-center justify-center bg-transparent rounded opacity-75 hover:opacity-100 transition-opacity text-white z-10" title="Expand sidebar" aria-label="Expand sidebar">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
          </button>
        </div>
        <!-- Text separate from logo -->
        <span class="sidebar-label text-lg font-semibold bg-gradient-to-r from-indigo-500 to-purple-500 bg-clip-text text-transparent">Ginto</span>
      </div>
      <!-- Close/Collapse buttons - stacked in same position -->
      <div class="flex items-center gap-1">
        <!-- Desktop collapse button (shown when expanded) -->
        <button id="sidebar-collapse-toggle" class="lg:flex hidden p-1 rounded hover:bg-indigo-100 dark:hover:bg-indigo-900/30 transition-colors text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400" title="Collapse sidebar" aria-label="Collapse sidebar">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
          </svg>
        </button>
        <!-- Mobile close button (X) -->
        <button id="sidebar-close-mobile" class="lg:hidden p-1 rounded hover:bg-indigo-100 dark:hover:bg-indigo-900/30 transition-colors text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400" title="Close sidebar" aria-label="Close sidebar">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>
  </div>
  
  <!-- Nav Items (icon-only when collapsed) -->
  <div class="pb-2 space-y-0.5">
    <!-- New Chat -->
    <button id="new_chat" class="nav-item w-full flex items-center gap-2 py-1.5 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-700 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300 text-sm transition-colors group">
      <svg class="nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
      </svg>
      <span class="sidebar-label">New chat</span>
    </button>
    
    <!-- Search icon + Search Bar (icon overlays the search input) -->
    <div class="nav-item relative flex items-center py-1.5">
      <input id="convo-search" type="search" placeholder="Search chats" autocomplete="off" class="sidebar-label absolute py-1.5 pl-8 pr-3 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-md text-sm text-gray-800 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-offset-0 focus:ring-indigo-500 focus:border-indigo-500" style="left: 6px; right: 6px;">
      <svg class="nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 flex-shrink-0 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
      </svg>
    </div>
    
    <!-- Member Messenger (Facebook-like) -->
    <?php if (!empty($isLoggedIn)): ?>
    <a href="/messenger" id="open-messenger" class="nav-item flex items-center gap-2 py-1.5 rounded-md hover:bg-blue-50 dark:hover:bg-blue-900/20 text-gray-700 dark:text-gray-300 hover:text-blue-700 dark:hover:text-blue-300 text-sm transition-colors group relative w-full text-left">
      <div class="nav-icon relative">
        <svg class="w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-blue-600 dark:group-hover:text-blue-400 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
          <path d="M12 2C6.477 2 2 6.145 2 11.243c0 2.936 1.444 5.537 3.702 7.205V22l3.427-1.88c.915.255 1.886.392 2.871.392 5.523 0 10-4.145 10-9.243C22 6.145 17.523 2 12 2zm1.07 12.44l-2.551-2.724-4.978 2.724 5.476-5.816 2.614 2.724 4.915-2.724-5.476 5.816z"/>
        </svg>
        <!-- Unread badge -->
        <span id="messenger-unread-badge" class="hidden absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center font-medium">0</span>
      </div>
      <span class="sidebar-label">Messenger</span>
    </a>
    <?php endif; ?>
    
    <!-- Courses -->
    <a href="/courses" id="open-courses" class="nav-item flex items-center gap-2 py-1.5 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-700 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300 text-sm transition-colors group">
      <svg class="nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5"/>
      </svg>
      <span class="sidebar-label">Courses</span>
    </a>
    
    <!-- Masterclasses -->
    <a href="/masterclass" id="open-masterclass" class="nav-item flex items-center gap-2 py-1.5 rounded-md hover:bg-teal-50 dark:hover:bg-teal-900/20 text-gray-700 dark:text-gray-300 hover:text-teal-700 dark:hover:text-teal-300 text-sm transition-colors group">
      <svg class="nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-teal-600 dark:group-hover:text-teal-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/>
      </svg>
      <span class="sidebar-label">Masterclasses</span>
    </a>
    
    <!-- Dashboard (logged-in users only, requires ENABLE_DASHBOARD=true) -->
    <?php 
    $enableDashboard = filter_var(getenv('ENABLE_DASHBOARD') ?: ($_ENV['ENABLE_DASHBOARD'] ?? 'false'), FILTER_VALIDATE_BOOLEAN);
    if (($isLoggedIn ?? false) && $enableDashboard): 
    ?>
    <a href="/dashboard" id="open-dashboard" class="nav-item flex items-center gap-2 py-1.5 rounded-md hover:bg-violet-50 dark:hover:bg-violet-900/20 text-gray-700 dark:text-gray-300 hover:text-violet-700 dark:hover:text-violet-300 text-sm transition-colors group">
      <svg class="nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-violet-600 dark:group-hover:text-violet-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>
      </svg>
      <span class="sidebar-label">Dashboard</span>
      <span class="sidebar-label text-xs text-amber-500 dark:text-amber-400">(Beta)</span>
    </a>
    <?php endif; ?>
    
    <!-- My Files -->
    <a href="#" id="open-my-files" class="nav-item flex items-center gap-2 py-1.5 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-700 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300 text-sm transition-colors group">
      <svg class="nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/>
      </svg>
      <span class="sidebar-label">My Files</span>
      <span id="sandbox-status-indicator" class="hidden sidebar-label">
        <span class="w-2 h-2 rounded-full bg-gray-400 inline-block" title="Sandbox not installed"></span>
      </span>
    </a>
    
    <?php if (!empty($isAdmin)): ?>
    <!-- Admin Panel (Admin Only) -->
    <a href="/admin" class="nav-item flex items-center gap-2 py-1.5 rounded-md hover:bg-amber-50 dark:hover:bg-amber-900/20 text-gray-700 dark:text-gray-300 hover:text-amber-700 dark:hover:text-amber-300 text-sm transition-colors group">
      <svg class="nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-amber-600 dark:group-hover:text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
      </svg>
      <span class="sidebar-label">Admin</span>
    </a>
    
    <!-- Manage Sandboxes (Admin Only) -->
    <a href="/admin/lxc" class="nav-item flex items-center gap-2 py-1.5 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-700 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300 text-sm transition-colors group">
      <svg class="nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z"/>
      </svg>
      <span class="sidebar-label">Manage Sandboxes</span>
    </a>
    
    <!-- Console (Admin Only) -->
    <a href="#" id="open-console" class="nav-item flex items-center gap-2 py-1.5 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-700 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300 text-sm transition-colors group">
      <svg class="nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z"/>
      </svg>
      <span class="sidebar-label">Console</span>
    </a>
    <?php endif; ?>
    
    <!-- OpenWebUI (shows Install or Open based on status) - Only show if enabled in .env -->
    <?php if ((getenv('OPENWEBUI_ENABLED') ?: ($_ENV['OPENWEBUI_ENABLED'] ?? 'false')) === 'true'): ?>
    <a href="#" id="open-webui-link" class="nav-item flex items-center gap-2 py-1.5 rounded-md hover:bg-orange-50 dark:hover:bg-orange-900/20 text-gray-700 dark:text-gray-300 hover:text-orange-700 dark:hover:text-orange-300 text-sm transition-colors group">
      <svg class="nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-orange-600 dark:group-hover:text-orange-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/>
      </svg>
      <span id="open-webui-label" class="sidebar-label">Install OpenWebUI</span>
      <span id="open-webui-status" class="hidden sidebar-label">
        <span class="w-2 h-2 rounded-full bg-gray-400 inline-block" title="Checking..."></span>
      </span>
    </a>
    <?php endif; ?>
  </div>
  
  <!-- Divider -->
  <div class="mx-2 border-t border-gray-200 dark:border-gray-800"></div>
  
  <!-- Conversations Section (hidden when collapsed) -->
  <div class="sidebar-expanded-only flex-1 overflow-y-auto sidebar-scroll pt-2" style="flex-direction: column;">
    <h2 class="sidebar-label text-xs font-medium text-gray-500 uppercase tracking-wider px-4 mb-2">Conversations</h2>
    <nav id="conversation-list" role="list" class="px-2">
      <div class="text-sm text-gray-500 dark:text-gray-400 px-2 py-2 sidebar-label">No conversations yet</div>
    </nav>
  </div>
  
  <!-- Divider -->
  <div class="mx-2 border-t border-gray-200 dark:border-gray-800 mt-auto"></div>
  
  <!-- Footer - Admin & Settings (always at bottom) -->
  <div class="pb-2 space-y-0.5">
    <?php if (!empty($isLoggedIn) && !empty($_SESSION['user_id'])): 
      // Get user's public_id for referral link
      $userPublicId = $GLOBALS['db']->get('users', 'public_id', ['id' => $_SESSION['user_id']]);
      if ($userPublicId):
        $referralLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'ginto.ai') . '/register?ref=' . urlencode($userPublicId);
    ?>
    <!-- Referral Link -->
    <div class="py-1.5 px-1">
      <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mb-1">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/>
        </svg>
        <span class="sidebar-label">Your Referral Link</span>
      </div>
      <div class="flex items-center gap-1">
        <input type="text" readonly value="<?= htmlspecialchars($referralLink) ?>" 
          class="sidebar-label flex-1 text-xs bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded px-2 py-1 text-gray-600 dark:text-gray-300 truncate cursor-pointer"
          onclick="this.select(); navigator.clipboard.writeText(this.value).then(() => { this.nextElementSibling.classList.remove('hidden'); setTimeout(() => this.nextElementSibling.classList.add('hidden'), 1500); });"
          title="Click to copy">
        <span class="hidden text-xs text-green-500 whitespace-nowrap"><i class="fas fa-check"></i></span>
        <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($referralLink, ENT_QUOTES) ?>').then(() => { this.innerHTML='<i class=\'fas fa-check text-green-500\'></i>'; setTimeout(() => this.innerHTML='<i class=\'fas fa-copy\'></i>', 1500); });" 
          class="nav-icon p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400 transition-colors" title="Copy referral link">
          <i class="fas fa-copy"></i>
        </button>
      </div>
    </div>
    <?php endif; endif; ?>
    
    <!-- MCP Status (admin only) -->
    <button id="open-mcp-tab" class="hidden nav-item w-full flex items-center gap-2 py-1.5 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-700 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300 text-sm transition-colors group" data-admin-only="true">
      <svg class="nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
        <circle cx="12" cy="12" r="3" id="mcp-status-dot-inner"/>
      </svg>
      <span id="mcp-badge" class="sidebar-label">MCP Status</span>
    </button>
    
    <!-- Settings & Live Row -->
    <div class="flex items-center justify-between gap-2 py-1.5 pr-2">
      <button id="toggle-settings" class="nav-item flex items-center gap-2 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-700 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300 text-sm transition-colors group" aria-label="Settings">
        <svg class="nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/>
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        <span class="sidebar-label">Settings</span>
      </button>
      <?php 
      // Live button: Show if .installed doesn't exist (setup mode) OR if user is admin
      $installedExists = file_exists(ROOT_PATH . '/.installed') || file_exists(dirname(ROOT_PATH) . '/storage/.installed');
      $showLiveButton = !$installedExists || !empty($isAdmin);
      if ($showLiveButton): 
      ?>
      <a href="/live" class="nav-item flex items-center gap-1.5 px-2 py-1 rounded-md hover:bg-green-50 dark:hover:bg-green-900/20 text-gray-600 dark:text-gray-400 hover:text-green-700 dark:hover:text-green-300 text-sm transition-colors group sidebar-label" title="Live Settings">
        <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse flex-shrink-0" id="live-status-indicator"></span>
        <span>Live</span>
      </a>
      <?php endif; ?>
    </div>
  </div>
  
  <!-- User Account (always at bottom) -->
  <div class="pb-2 space-y-0.5 border-t border-gray-200 dark:border-gray-800">
    <?php if (!empty($isLoggedIn)): ?>
    <div class="nav-item flex items-center gap-2 py-1.5 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors cursor-pointer group">
      <div class="nav-icon w-5 h-5 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-xs font-medium flex-shrink-0">
        <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
      </div>
      <div class="flex-1 min-w-0 sidebar-label">
        <span class="text-sm text-gray-700 dark:text-gray-200 truncate block"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
      </div>
      <a id="logout-link" href="/logout" class="sidebar-label p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 text-red-400 hover:text-red-500 transition-all" title="Logout">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
        </svg>
      </a>
    </div>
    <?php else: ?>
    <button onclick="showLoginRequiredModal()" class="nav-item w-full flex items-center gap-2 py-1.5 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-700 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300 text-sm transition-colors group">
      <div class="nav-icon w-5 h-5 rounded-full bg-gray-200 dark:bg-gray-800 flex items-center justify-center flex-shrink-0">
        <svg class="w-3 h-3 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/>
        </svg>
      </div>
      <span class="sidebar-label">Login</span>
    </button>
    <a href="/register" class="nav-item flex items-center gap-2 py-1.5 rounded-md hover:bg-green-50 dark:hover:bg-green-900/20 text-gray-700 dark:text-gray-300 hover:text-green-700 dark:hover:text-green-300 text-sm transition-colors group">
      <div class="nav-icon w-5 h-5 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center flex-shrink-0">
        <svg class="w-3 h-3 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z"/>
        </svg>
      </div>
      <span class="sidebar-label">Register</span>
    </a>
    <?php endif; ?>
  </div>
</aside>

<!-- Mobile Sidebar Overlay -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-20 lg:hidden hidden" onclick="toggleSidebar()"></div>
