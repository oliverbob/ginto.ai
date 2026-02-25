<?php
/**
 * Mobile Header
 * Fixed header with hamburger menu for mobile devices
 */
?>
<?php
// Determine login/admin state for mobile view
$isLoggedIn = !empty($_SESSION['user_id']);
$isAdmin = false;
try {
  if (class_exists('Ginto\\Controllers\\UserController') && \Ginto\Controllers\UserController::isAdmin()) {
    $isAdmin = true;
  }
} catch (\Throwable $_) { /* ignore */ }
?>
<!-- Mobile Header with Hamburger -->
<header id="mobile-header" class="lg:hidden fixed top-0 left-0 right-0 z-40 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between px-4 py-2">
  <style>
    /* Very tiny screens: replace the model selector with a simple brand label
       but keep the hamburger and other header icons visible. */
    @media (max-width: 340px) {
      /* Show the model selector at very small widths and hide the brand label */
      #mobile-header .model-replace-on-tiny { display: inline-flex !important; }
      #mobile-header .hide-on-tiny { display: none !important; }
      #mobile-brand-tiny { display: none !important; }
    }
    /* Hide AI icon by default to avoid Tailwind `hidden` conflicts */
    #mobile-header .show-ai-icon-on-300 { display: none !important; }
    @media (max-width: 300px) {
      /* At 300px and below replace the model selector with a compact AI icon */
      #mobile-header .model-replace-on-tiny { display: none !important; }
      #mobile-header .show-ai-icon-on-300 { display: inline-flex !important; }
    }
  </style>
  <div class="flex items-center gap-2 flex-1 min-w-0">
    <button id="mobile-menu-toggle" class="p-2 rounded-lg hover:bg-indigo-100 dark:hover:bg-indigo-900/30 text-gray-600 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300 flex-shrink-0" aria-label="Toggle menu">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
    </button>
    <!-- Compact AI icon shown at <=300px (placed beside hamburger to avoid parent hide rules) -->
    <button id="mobile-ai-icon" onclick="document.getElementById('model-selector-btn-mobile')?.click()" class="p-2 ml-1 rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors show-ai-icon-on-300" title="Models" style="display:none!important">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3v3M12 18v3M4.22 4.22l2.12 2.12M17.66 17.66l2.12 2.12M4 12H1M23 12h-3M4.22 19.78l2.12-2.12M17.66 6.34l2.12-2.12M7 7h10v10H7z"/>
      </svg>
    </button>
    <?php if ($isLoggedIn || $isAdmin): ?>
    <button id="model-selector-btn-mobile" class="model-replace-on-tiny flex items-center gap-2 px-2 h-8 min-h-8 py-0.5 bg-gray-100 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors cursor-pointer inline-flex flex-1 min-w-0 justify-start overflow-hidden max-w-[350px]" aria-haspopup="true" aria-expanded="false">
      <div class="w-2 h-2 rounded-full bg-green-500 flex-shrink-0" id="mobile-model-status-dot" aria-hidden="true"></div>
      <span class="text-sm font-medium text-gray-700 dark:text-gray-200 truncate min-w-0" id="mobile-model-name" title="Ginto AI">Ginto AI</span>
      <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
      </svg>
    </button>
    <!-- Mobile dropdown (hidden by default) -->
    <div id="model-dropdown-mobile" class="hidden fixed top-12 mx-0 w-auto max-w-[350px] bg-white dark:bg-gray-900 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-50 max-h-[50vh] overflow-hidden flex flex-col" style="left:5px; right:5px; max-width: calc(100vw - 10px);">
      <div class="p-3 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
        <div class="relative flex-1">
          <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <input type="text" id="model-search-mobile" placeholder="Search models..." class="w-full pl-9 pr-3 py-1.5 text-sm bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg focus:outline-none focus:ring-1 focus:ring-offset-0 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <?php if ($isLoggedIn): ?>
        <button id="add-provider-btn-mobile" class="flex-shrink-0 ml-3 flex items-center gap-2 px-3 h-9 text-sm font-semibold bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl shadow-sm transition-colors whitespace-nowrap" title="+ Add Ginto Tunnel">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          <span class="ml-2">+ Add Ginto Tunnel</span>
        </button>
        <?php else: ?>
        <a href="/register" id="add-provider-register-mobile" class="flex-shrink-0 ml-3 flex items-center gap-2 px-3 h-9 text-sm font-semibold bg-amber-600 hover:bg-amber-500 text-white rounded-xl shadow-sm transition-colors whitespace-nowrap" title="Create account to add key">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
          <span class="ml-2">Create account</span>
        </a>
        <?php endif; ?>
      </div>
      <div id="model-list-mobile" class="py-2 overflow-y-auto flex-1">
        <div class="px-4 py-3 text-sm text-gray-500">Loading models...</div>
      </div>
    </div>
    <?php else: ?>
    <div class="flex items-center gap-2 px-2 h-8 min-h-8 py-0.5 rounded-lg min-w-0">
      <div class="w-2 h-2 rounded-full bg-green-500 flex-shrink-0" aria-hidden="true"></div>
      <span class="text-sm font-medium text-gray-700 dark:text-gray-200 truncate">Ginto AI</span>
    </div>
    <?php endif; ?>
  </div>
  </div>
  <div id="mobile-brand-tiny" class="hidden flex-1 text-center font-semibold text-lg" aria-hidden="false">Ginto AI</div>
  <?php if (empty($isLoggedIn)): ?>
  <div class="flex items-center gap-1">
    <button onclick="showLoginRequiredModal()" class="flex items-center gap-1 px-2 py-1.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors text-xs font-medium" title="Login">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
      </svg>
      <span class="hidden min-[400px]:inline">Login</span>
    </button>
    <a href="/register" class="flex items-center gap-1 px-2 py-1.5 rounded-lg text-green-600 dark:text-green-400 hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors text-xs font-medium" title="Register">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
      </svg>
      <span class="hidden min-[400px]:inline">Register</span>
    </a>
    <a href="https://github.com/oliverbob/ginto.ai" target="_blank" class="p-1.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors hide-on-tiny" title="Star us on GitHub">
      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
    </a>
    <!-- Compact AI icon shown at <=300px -->
    <button id="mobile-ai-icon" onclick="document.getElementById('model-selector-btn-mobile')?.click()" class="p-1.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors show-ai-icon-on-300" title="Models">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3v3M12 18v3M4.22 4.22l2.12 2.12M17.66 17.66l2.12 2.12M4 12H1M23 12h-3M4.22 19.78l2.12-2.12M17.66 6.34l2.12-2.12M7 7h10v10H7z"/>
      </svg>
    </button>
    <button id="mobile-theme-toggle" class="p-1.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" title="Toggle theme">
      <svg class="w-4 h-4 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
      </svg>
      <svg class="w-4 h-4 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
      </svg>
    </button>
  </div>
  <?php else: ?>
  <div class="flex items-center gap-1">
    <a href="/dashboard" class="p-1.5 rounded-lg text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white transition-colors" title="Dashboard">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
      </svg>
    </a>
    <button id="mobile-notifications" class="p-1.5 rounded-lg text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white transition-colors" title="Notifications">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
      </svg>
    </button>
    <a href="/social" id="mobile-channels" class="p-1.5 rounded-lg text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white transition-colors" title="Newsfeed" aria-label="Newsfeed">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="6.5" cy="7.5" r="1.5" fill="currentColor" />
        <circle cx="17.5" cy="6.5" r="1.5" fill="currentColor" />
        <circle cx="12" cy="16" r="1.5" fill="currentColor" />
        <path d="M7.2 8.1 C9.2 10.2,10.4 12.6,12 14.2" fill="none" />
        <path d="M16.8 7.3 C15.2 9.0,13.8 11.0,12 12.8" fill="none" />
      </svg>
    </a>
    <!-- Mobile Messenger Icon -->
    <button type="button" id="mobile-messenger-link" class="relative p-1.5 rounded-lg text-gray-600 dark:text-gray-300 hover:text-blue-500 dark:hover:text-blue-400 transition-colors" title="Messenger">
      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
        <path d="M12 2C6.477 2 2 6.145 2 11.243c0 2.936 1.444 5.537 3.702 7.205V22l3.427-1.88c.915.255 1.886.392 2.871.392 5.523 0 10-4.145 10-9.243C22 6.145 17.523 2 12 2zm1.07 12.44l-2.551-2.724-4.978 2.724 5.476-5.816 2.614 2.724 4.915-2.724-5.476 5.816z"/>
      </svg>
      <span id="mobile-messenger-badge" class="hidden absolute -top-0.5 -right-0.5 min-w-[14px] h-[14px] bg-red-500 text-white text-[8px] rounded-full flex items-center justify-center font-bold px-0.5">0</span>
    </button>
    <a href="https://github.com/oliverbob/ginto.ai" target="_blank" class="p-1.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors hide-on-tiny" title="Star us on GitHub">
      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
    </a>
    <button id="mobile-theme-toggle" class="p-1.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" title="Toggle theme">
      <svg class="w-4 h-4 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
      </svg>
      <svg class="w-4 h-4 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
      </svg>
    </button>
    <button id="mobile-settings" class="p-1.5 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/30 transition-colors hide-on-tiny" aria-label="Settings">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
    </button>
  </div>
  <?php endif; ?>
</header>
