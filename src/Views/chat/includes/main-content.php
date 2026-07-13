<?php
/**
 * Main Content Area
 * Header, messages container, and composer
 */
?>
<!-- Main Content - offset by sidebar width on large screens -->
<main id="main-content" class="flex-1 flex flex-col min-h-screen pt-14 lg:pt-0 lg:ml-64">
  <?php
  // Determine login/admin state for view logic
  $isLoggedIn = !empty($_SESSION['user_id']);
  $isAdmin = false;
  try {
      if (class_exists('Ginto\\Controllers\\UserController') && \Ginto\Controllers\UserController::isAdmin()) {
          $isAdmin = true;
      }
  } catch (\Throwable $_) { /* ignore */ }
  ?>
  <?php if (($paymentStatus ?? null) === 'pending'): ?>
  <!-- Premium Account Pending Banner -->
  <div class="bg-gradient-to-r from-amber-500 to-orange-500 text-white px-4 py-3 text-center">
    <div class="flex items-center justify-center gap-2 flex-wrap">
      <svg class="w-5 h-5 flex-shrink-0 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <span class="font-semibold">Premium Account Pending</span>
      <span class="hidden sm:inline">—</span>
      <span class="text-white/90 text-sm">Your payment is being verified. Premium features will be unlocked once confirmed.</span>
      <button type="button" onclick="showTransactionDetails()" class="ml-2 px-2 py-0.5 bg-white/20 hover:bg-white/30 rounded text-xs font-medium transition underline-offset-2 hover:underline">
        <i class="fas fa-receipt mr-1"></i>See transaction details
      </button>
    </div>
  </div>
  <?php endif; ?>
  <!-- (mobile floating Add Key removed - moved beside mobile model search) -->
  
  <!-- Header - hidden on mobile since we have fixed mobile header -->
  <header id="main-header" class="hidden lg:block sticky top-0 z-50 bg-white/80 dark:bg-gray-950/80 backdrop-blur-sm border-b border-gray-200 dark:border-gray-800">
    <div class="flex items-center justify-between px-4 h-14">
      <!-- Model selector -->
      <div class="flex items-center gap-2">
        <!-- Model selector: interactive for admins, read-only for logged-in users, static label for visitors -->
          <div class="relative" id="model-selector-wrapper">
            <?php if ($isAdmin): ?>
            <button id="model-selector-btn" class="flex items-center gap-2 px-3 py-1.5 bg-gray-100 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors cursor-pointer max-w-[350px] min-w-0 overflow-hidden">
              <div class="w-2 h-2 rounded-full bg-green-500 flex-shrink-0" id="model-status-dot"></div>
              <span class="text-sm text-gray-700 dark:text-gray-200 truncate min-w-0" id="model-name">ginto-default</span>
              <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>
            <!-- Dropdown menu (admin only) -->
            <div id="model-dropdown" class="hidden fixed top-14 left-1 right-1 mx-0 lg:absolute lg:left-0 lg:mt-2 lg:top-auto lg:right-auto w-auto lg:w-[350px] lg:min-w-[350px] lg:max-w-[350px] max-h-[60vh] overflow-hidden bg-white dark:bg-gray-900 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-20 flex flex-col" style="max-width: calc(100vw - 10px);">
              <div class="sticky top-0 z-30 p-3 border-b border-gray-200 dark:border-gray-700 bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm flex items-center gap-2">
                <div class="relative flex-1">
                  <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                  </svg>
                  <input type="text" id="model-search" placeholder="Search models..." class="w-full pl-9 pr-3 h-10 text-sm bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg focus:outline-none focus:ring-1 focus:ring-offset-0 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <button id="add-provider-btn" class="flex-shrink-0 ml-3 flex items-center gap-2 px-4 h-10 text-sm font-semibold bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl shadow-sm transition-colors whitespace-nowrap" title="+ Add API Key">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                  </svg>
                  <span class="ml-2">+ Add API Key</span>
                </button>
              </div>
              <div id="model-list" class="py-2 overflow-y-auto flex-1">
                <div class="px-4 py-3 text-sm text-gray-500">Loading models...</div>
              </div>
            </div>
            <?php elseif ($isLoggedIn): ?>
            <!-- Non-admin logged-in user: read-only model display (model managed by admin) -->
            <div id="model-selector-btn" class="flex items-center gap-2 px-3 py-1.5 bg-gray-100 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 max-w-[350px] min-w-0 overflow-hidden" title="Model is managed by admin">
              <div class="w-2 h-2 rounded-full bg-green-500 flex-shrink-0" id="model-status-dot"></div>
              <span class="text-sm text-gray-700 dark:text-gray-200 truncate min-w-0" id="model-name">Loading...</span>
            </div>
            <?php else: ?>
            <div class="flex items-center gap-2 px-3 py-1.5 bg-transparent rounded-lg min-w-0">
              <div class="w-2 h-2 rounded-full bg-green-500 flex-shrink-0" aria-hidden="true"></div>
              <span class="text-sm text-gray-700 dark:text-gray-200 truncate">Ginto AI</span>
            </div>
            <?php endif; ?>
          </div>
      </div>
      
      <!-- Dashboard + Star on GitHub + Theme toggle -->
      <div class="flex items-center gap-2">
        <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="/dashboard" 
           class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-white transition-colors"
           title="Dashboard">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
          </svg>
        </a>
        <a href="/marketplace"
           class="text-gray-500 dark:text-gray-400 hover:text-orange-500 dark:hover:text-orange-400 transition-colors"
           title="ePower Mall">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z"/>
          </svg>
        </a>
        <button id="header-notifications" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-white transition-colors" title="Notifications">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>
        </button>
        <a href="/social" id="header-channels" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-white transition-colors" title="Newsfeed" aria-label="Newsfeed">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="6.5" cy="7.5" r="1.5" fill="currentColor" />
            <circle cx="17.5" cy="6.5" r="1.5" fill="currentColor" />
            <circle cx="12" cy="16" r="1.5" fill="currentColor" />
            <path d="M7.2 8.1 C9.2 10.2,10.4 12.6,12 14.2" fill="none" />
            <path d="M16.8 7.3 C15.2 9.0,13.8 11.0,12 12.8" fill="none" />
          </svg>
        </a>
        <!-- Messenger Icon - click opens popup on /chat via JS -->
        <button type="button" id="header-messenger-link"
           class="relative text-gray-500 dark:text-gray-400 hover:text-blue-500 dark:hover:text-blue-400 transition-colors"
           title="Messenger">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 2C6.477 2 2 6.145 2 11.243c0 2.936 1.444 5.537 3.702 7.205V22l3.427-1.88c.915.255 1.886.392 2.871.392 5.523 0 10-4.145 10-9.243C22 6.145 17.523 2 12 2zm1.07 12.44l-2.551-2.724-4.978 2.724 5.476-5.816 2.614 2.724 4.915-2.724-5.476 5.816z"/>
          </svg>
          <!-- Unread badge -->
          <span id="header-messenger-badge" class="hidden absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] bg-red-500 text-white text-[10px] rounded-full flex items-center justify-center font-bold px-1">0</span>
        </button>
        <?php endif; ?>
        <a href="https://github.com/oliverbob/ginto.ai" target="_blank" 
           class="flex items-center gap-2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-white transition-colors text-sm"
           title="Star us on GitHub">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
          <span>Star us</span>
        </a>
        <button id="theme-toggle" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-white transition-colors" title="Toggle theme">
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
    </div>
  </header>
  
  <!-- Chat Area -->
  <div class="flex-1 flex flex-col max-w-4xl mx-auto w-full px-4">
    <!-- Messages Container -->
    <div id="messages" class="pt-2 pb-20 space-y-4 flex flex-col" aria-live="polite">
      <!-- Empty State -->
      <div class="bg-hint flex flex-col items-center text-center pb-8">
        <div class="w-16 h-16 mb-6 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
          </svg>
        </div>
        <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-2">Ginto Chat</h2>
        <p class="text-gray-500 dark:text-gray-400 mb-4 max-w-md">Type a message or upload files to get started. I can help you build, analyze, and create.</p>

        <!-- Promotional banner (styled like prompts) -->
        <div class="mb-6 w-full max-w-lg">
          <div class="px-4 py-3 bg-gradient-to-br from-indigo-50/30 via-white/50 to-purple-50/30 dark:from-indigo-950/20 dark:via-gray-900/50 dark:to-purple-950/20 border border-indigo-100/40 dark:border-indigo-800/30 rounded-xl transition-colors">
            <div class="space-y-2 text-center">
              <h4 class="flex items-center justify-center gap-2 text-sm font-semibold text-gray-800 dark:text-gray-100 tracking-tight">
                <svg class="w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                A Non-bloated, Flexible Agentic UI
              </h4>
              <div class="space-y-1.5 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                <p>
                  Compatible with <span class="font-medium text-gray-600 dark:text-gray-300">Ollama</span>, 
                  <code class="px-1 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs font-mono text-indigo-500 dark:text-indigo-400">llama.cpp</code>, 
                  and OpenAI-compatible APIs.
                </p>
                <p>
                  Supports <span class="font-medium text-gray-600 dark:text-gray-300">reasoning</span>, 
                  <span class="font-medium text-gray-600 dark:text-gray-300">vision</span>, and 
                  <span class="font-medium text-gray-600 dark:text-gray-300">tool-calling</span> with multi-tenant sandboxes. 
                  Bijective routing to <span class="font-mono text-xs">4.29B</span> internal IPs.
                </p>
              </div>
              <p class="text-[10px] font-medium tracking-wider uppercase text-indigo-500 dark:text-indigo-400">Built to scale · Built to last</p>
            </div>
          </div>
        </div>

        <!-- Ginto Trading Academy ad (logged-in users; guests get the full academy banner instead) -->
        <a href="/academy" class="mb-6 w-full max-w-lg block group">
          <div class="relative overflow-hidden rounded-xl px-5 py-4 bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/20 transition-transform group-hover:scale-[1.01]">
            <div class="flex items-center gap-4">
              <div class="text-3xl leading-none">🎓</div>
              <div class="text-left flex-1 min-w-0">
                <div class="text-[10px] font-bold uppercase tracking-wider text-white/80">New · Ginto Trading Academy</div>
                <div class="text-sm font-bold leading-snug">Learn to trade crypto with a real, live AI bot</div>
                <div class="text-xs text-white/85 mt-0.5">Charts, risk, and the Ginto Trading Bot — hands-on lessons.</div>
              </div>
              <div class="shrink-0 inline-flex items-center gap-1 text-xs font-semibold bg-white/15 group-hover:bg-white/25 rounded-lg px-3 py-1.5 transition-colors">Explore <span aria-hidden="true">→</span></div>
            </div>
            <!-- Live gainer / popular / loser mini-charts (filled by renderHomeAcademyCharts) -->
            <div id="home-academy-charts" class="mt-3 grid grid-cols-3 gap-3"></div>
          </div>
        </a>

        <!-- Example prompts (loaded dynamically) -->
        <div id="welcome-prompts" class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-lg">
          <!-- Prompts will be fetched from /chat/prompts/ and injected here -->
          <div id="welcome-prompts-loading" class="text-sm text-gray-500">Loading prompts…</div>
        </div>
      </div>
    </div>
    
    <!-- Composer -->
    <div id="composer" class="sticky bottom-0 pb-6 pt-4 bg-gradient-to-t from-gray-50 dark:from-gray-950 via-gray-50 dark:via-gray-950 to-transparent z-20 lg:z-20">
      <div class="relative bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xl">
        <!-- Attachment Preview -->
        <div id="attach-preview" class="hidden px-4 pt-3 pb-2 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-start gap-3">
            <div class="relative">
              <img id="attach-preview-img" class="w-20 h-20 object-cover rounded-lg border border-gray-300 dark:border-gray-600" src="" alt="Attached file">
              <!-- Document icon (shown when image is hidden) -->
              <div id="attach-doc-icon" class="hidden w-20 h-20 rounded-lg border border-gray-300 dark:border-gray-600 bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-900/30 dark:to-purple-900/30 flex items-center justify-center">
                <svg class="w-10 h-10 text-indigo-500 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
              </div>
              <button id="attach-remove" class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center text-xs shadow-md" title="Remove attachment">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
            <div class="flex-1 text-sm text-gray-600 dark:text-gray-400">
              <p id="attach-filename" class="font-medium truncate"></p>
              <p id="attach-type" class="text-xs text-indigo-500">Image will be analyzed with vision model</p>
            </div>
          </div>
        </div>
        <textarea 
          id="prompt" 
          rows="1"
          placeholder="Ask anything..." 
          class="w-full px-4 py-4 pr-24 bg-transparent resize-none focus:outline-none text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 max-h-40"
          style="min-height: 36px;"
        ></textarea>
        
        <!-- Composer Actions -->
        <div class="absolute right-2 bottom-2 flex items-center gap-1">
          <!-- Attach file (hidden input) - accepts images and documents -->
          <input type="file" id="attach-input" accept="image/*,.pdf,.txt,.md,.doc,.docx,.rtf,.html,.htm,application/pdf,text/plain,text/markdown,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="hidden">
          <button id="attach-btn" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors" title="Attach image or document (PDF, TXT, DOC, etc.)">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
            </svg>
          </button>
          
          <!-- Send button - Messenger blue -->
          <button id="send" class="p-2.5 rounded-full bg-[#0084ff] hover:bg-[#0073e6] text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center" title="Send message">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 2L11 13"/>
              <path d="M22 2L15 22L11 13L2 9L22 2Z"/>
            </svg>
          </button>
        </div>
      </div>
      
      <!-- Keyboard hint (hidden on mobile) -->
      <div class="hidden md:flex items-center justify-center gap-4 mt-2 text-xs text-gray-500 dark:text-gray-500">
        <span>Press <kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-800 rounded text-gray-600 dark:text-gray-400">Enter</kbd> to send</span>
        <span><kbd class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-800 rounded text-gray-600 dark:text-gray-400">Shift + Enter</kbd> for new line</span>
      </div>
    </div>
  </div>
</main>

<!-- Toast Notification Container -->
<div id="toast-container" class="fixed bottom-4 right-4 z-[200] flex flex-col gap-2 pointer-events-none"></div>

<?php if (!empty($isAdmin)): ?>
<!-- Admin console overlay (admin-only) -->
<div id="admin-console-overlay" class="hidden fixed inset-0 z-50 flex items-end justify-end p-6 pointer-events-none">
  <div class="w-full max-w-xl bg-white/90 dark:bg-black/80 backdrop-blur-md rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 p-4 pointer-events-auto">
    <div class="flex items-start gap-3">
      <div class="flex-1">
        <div class="flex items-center justify-between mb-2">
          <div class="text-sm font-semibold">Admin Chat Console</div>
          <div class="text-xs text-gray-500">Debug info (admin only)</div>
        </div>
        <div class="text-xs text-gray-600 dark:text-gray-300 mb-2">Provider: <span id="admin-console-provider" class="font-medium">-</span> · Model: <span id="admin-console-model" class="font-medium">-</span></div>
        <div id="admin-console-raw" class="text-sm text-gray-800 dark:text-gray-100 bg-gray-50 dark:bg-gray-900/40 rounded p-2 max-h-48 overflow-auto whitespace-pre-wrap break-words"></div>
      </div>
      <div class="flex-shrink-0">
        <button id="admin-console-close" class="px-3 py-1 text-sm bg-red-500 hover:bg-red-600 text-white rounded">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Admin overlay toggle: moved into minimized tabs container for stacking order -->
<?php endif; ?>

<?php if (!empty($_SESSION['user_id'])): ?>
<!-- User console overlay (logged-in users) -->
<div id="user-console-overlay" class="hidden fixed inset-0 z-50 flex items-end justify-end p-6 pointer-events-none">
  <div class="w-full max-w-xl bg-white/90 dark:bg-black/80 backdrop-blur-md rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 p-4 pointer-events-auto">
    <div class="flex items-start gap-3">
      <div class="flex-1">
        <div class="flex items-center justify-between mb-2">
          <div class="text-sm font-semibold">User Console</div>
          <div class="text-xs text-gray-500">Usage & API key logs</div>
        </div>
        <div class="text-xs text-gray-600 dark:text-gray-300 mb-2">
          Provider: <span id="user-console-provider" class="font-medium">-</span>
          · Model: <span id="user-console-model" class="font-medium">-</span>
          · Tokens left: <span id="user-console-tokens" class="font-medium">-</span>
        </div>
        <div id="user-console-usage" class="text-sm text-gray-800 dark:text-gray-100 bg-gray-50 dark:bg-gray-900/40 rounded p-2 max-h-48 overflow-auto whitespace-pre-wrap break-words"></div>
      </div>
      <div class="flex-shrink-0">
        <button id="user-console-refresh" class="px-3 py-1 text-sm bg-blue-500 hover:bg-blue-600 text-white rounded mb-2">Refresh</button>
        <button id="user-console-close" class="px-3 py-1 text-sm bg-red-500 hover:bg-red-600 text-white rounded">Close</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
