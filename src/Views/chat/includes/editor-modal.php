<?php
/**
 * Editor Modal (Full Screen) with My Files, My Computer, Console, Ginto Cloud tabs
 */
?>
<!-- Editor Modal (Full Screen) -->
<div id="editor-modal" class="fixed inset-0 bg-black/50 dark:bg-black/90 z-50 hidden flex items-center justify-center">
  <div class="w-full h-full flex flex-col">
    <!-- Modal Header with Tabs -->
    <div class="bg-gray-100 dark:bg-gray-900 border-b border-gray-300 dark:border-gray-700 px-4 py-2 flex items-center justify-between flex-shrink-0">
      <div class="flex items-center gap-1">
        <!-- My Files Tab -->
        <button id="tab-my-files" class="editor-tab flex items-center gap-2 px-4 py-2 rounded-t-lg border-b-2 border-indigo-500 bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 font-medium text-sm transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/>
          </svg>
          <span class="tab-label-full">My Files</span><span class="tab-label-short">Files</span>
        </button>
        <?php if ($sandboxBackend !== 'docker'): ?>
        <!-- My Computer Tab (LXD only) -->
        <button id="tab-my-computer" class="editor-tab flex items-center gap-2 px-4 py-2 rounded-t-lg border-b-2 border-transparent bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white font-medium text-sm transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25"/>
          </svg>
          <span class="tab-label-full">My Computer</span><span class="tab-label-short">Computer</span>
        </button>
        <!-- Console Tab (LXD only) -->
        <button id="tab-console" class="editor-tab flex items-center gap-2 px-4 py-2 rounded-t-lg border-b-2 border-transparent bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white font-medium text-sm transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z"/>
          </svg>
          <span class="tab-label-full">Console</span>
        </button>
        <!-- Ginto Cloud Tab (LXD only) - Share sandbox via subdomain -->
        <button id="tab-cloud" class="editor-tab flex items-center gap-2 px-4 py-2 rounded-t-lg border-b-2 border-transparent bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white font-medium text-sm transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z"/>
          </svg>
          <span class="tab-label-full">Ginto Cloud</span><span class="tab-label-short">Cloud</span>
        </button>
        <?php endif; ?>
      </div>
      <div class="flex items-center gap-2">
        <!-- VNC Status (only visible in My Computer view) -->
        <span id="vnc-user-status" class="hidden text-xs px-2 py-0.5 rounded-full bg-gray-300 dark:bg-gray-700 text-gray-600 dark:text-gray-400">Disconnected</span>
        <!-- Console Status (only visible in Console view) -->
        <span id="console-tab-status" class="hidden text-xs px-2 py-0.5 rounded-full bg-gray-300 dark:bg-gray-700 text-gray-600 dark:text-gray-400">Disconnected</span>
        <!-- Cloud Status (only visible in Ginto Cloud view) -->
        <span id="cloud-tab-status" class="hidden text-xs px-2 py-0.5 rounded-full bg-gray-300 dark:bg-gray-700 text-gray-600 dark:text-gray-400">Not Active</span>
        <!-- Cloud Countdown (only visible when cloud subdomain active) -->
        <span id="cloud-tab-countdown" class="hidden text-xs px-2 py-0.5 rounded-full bg-yellow-600 text-yellow-100">⏱ 5:00</span>
        <!-- Console Add Tab Button (only visible in Console view) -->
        <button id="console-tab-add" class="hidden p-1.5 rounded hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors" title="New Terminal Tab">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
        </button>
        <button id="close-editor" class="p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-800 text-gray-700 dark:text-white">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>
    <!-- Content Views -->
    <div class="flex-1 relative">
      <!-- Editor iFrame (My Files) -->
      <iframe id="editor-iframe" src="" class="absolute inset-0 w-full h-full border-0"></iframe>
      <!-- VNC Desktop (My Computer) -->
      <div id="vnc-desktop-container" class="absolute inset-0 w-full h-full bg-black hidden flex items-center justify-center">
        <!-- Loading/Connection State -->
        <div id="vnc-loading" class="flex flex-col items-center gap-4 text-white">
          <svg class="w-12 h-12 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span id="vnc-loading-text">Connecting to desktop...</span>
        </div>
        <!-- VNC iframe will be inserted here -->
      </div>
      <!-- Console Terminal (Console Tab) -->
      <div id="console-tab-container" class="absolute inset-0 w-full h-full bg-gray-900 hidden flex flex-col">
        <!-- Console Tab Bar -->
        <div class="bg-gray-800 border-b border-gray-700 px-2 py-1 flex items-center gap-1 flex-shrink-0 overflow-x-auto">
          <div id="console-tab-tabs" class="flex items-center gap-1">
            <!-- Terminal tabs will be added here dynamically -->
          </div>
        </div>
        <!-- Terminal Container -->
        <div id="console-tab-terminals" class="flex-1 w-full bg-black relative">
          <!-- Terminal panes will be added here dynamically -->
        </div>
      </div>
      <!-- Ginto Cloud (Cloud Tab) - Preview sandbox via subdomain -->
      <div id="cloud-tab-container" class="absolute inset-0 w-full h-full bg-gray-900 hidden flex flex-col">
        <!-- Cloud Info Header -->
        <div class="bg-gray-800 border-b border-gray-700 px-4 py-3 flex items-center justify-between flex-shrink-0">
          <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z"/>
              </svg>
              <span class="text-white font-medium">Ginto Cloud</span>
            </div>
            <div id="cloud-subdomain-display" class="hidden">
              <a id="cloud-subdomain-link" href="#" target="_blank" class="text-cyan-400 hover:text-cyan-300 text-sm font-mono underline">loading...</a>
              <button id="cloud-copy-url" class="ml-2 p-1 rounded hover:bg-gray-700 text-gray-400 hover:text-white" title="Copy URL">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                </svg>
              </button>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <span id="cloud-timer-display" class="text-yellow-400 text-sm font-mono hidden">⏱ 5:00</span>
            <button id="cloud-refresh-btn" class="hidden px-3 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white text-sm rounded-lg transition-colors flex items-center gap-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
              </svg>
              Refresh
            </button>
          </div>
        </div>
        <!-- Cloud Content Area -->
        <div id="cloud-content" class="flex-1 relative">
          <!-- Loading/Setup State -->
          <div id="cloud-loading" class="absolute inset-0 flex flex-col items-center justify-center text-white">
            <svg class="w-12 h-12 animate-spin text-cyan-500 mb-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span id="cloud-loading-text" class="text-gray-300">Setting up your cloud subdomain...</span>
          </div>
          <!-- Cloud Preview iframe (inserted when subdomain is active) -->
        </div>
      </div>
    </div>
  </div>
</div>

<!-- xterm.js for Console Tab (loaded for all users) -->
<link rel="stylesheet" href="/lib/xterm/xterm.css" />
<script src="/lib/xterm/xterm.js"></script>
<script src="/lib/xterm/xterm-addon-fit.min.js"></script>
