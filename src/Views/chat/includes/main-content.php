<?php
/**
 * Main Content Area
 * Header, messages container, and composer
 */
?>
<!-- Main Content - offset by sidebar width on large screens -->
<main id="main-content" class="flex-1 flex flex-col min-h-screen pt-14 lg:pt-0 lg:ml-64">
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
  
  <!-- Header - hidden on mobile since we have fixed mobile header -->
  <header id="main-header" class="hidden lg:block sticky top-0 z-50 bg-white/80 dark:bg-gray-950/80 backdrop-blur-sm border-b border-gray-200 dark:border-gray-800">
    <div class="flex items-center justify-between px-4 h-14">
      <!-- Model selector -->
      <div class="flex items-center gap-2">
        <?php if (!empty($isAdmin)): ?>
        <!-- Admin model selector dropdown -->
        <div class="relative" id="model-selector-wrapper">
          <button id="model-selector-btn" class="flex items-center gap-2 px-3 py-1.5 bg-gray-100 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors cursor-pointer">
            <div class="w-2 h-2 rounded-full bg-green-500" id="model-status-dot"></div>
            <span class="text-sm text-gray-700 dark:text-gray-200" id="model-name">ginto-default</span>
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>
          <!-- Dropdown menu -->
          <div id="model-dropdown" class="hidden absolute left-0 mt-2 w-96 max-h-[80vh] overflow-hidden bg-white dark:bg-gray-900 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-50 flex flex-col">
            <!-- Search bar and Add Provider button -->
            <div class="p-3 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
              <div class="relative flex-1">
                <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" id="model-search" placeholder="Search models..." class="w-full pl-9 pr-3 py-1.5 text-sm bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg focus:outline-none focus:ring-1 focus:ring-offset-0 focus:ring-indigo-500 focus:border-indigo-500">
              </div>
              <button id="add-provider-btn" class="flex items-center gap-1 px-2 py-1.5 text-xs font-medium bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition-colors whitespace-nowrap">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Add Key</span>
              </button>
            </div>
            <div id="model-list" class="py-2 overflow-y-auto flex-1">
              <div class="px-4 py-3 text-sm text-gray-500">Loading models...</div>
            </div>
          </div>
        </div>
        <?php else: ?>
        <!-- Non-admin static display -->
        <div class="flex items-center gap-2 px-3 py-1.5 bg-gray-100 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
          <div class="w-2 h-2 rounded-full bg-green-500"></div>
          <span class="text-sm text-gray-700 dark:text-gray-200" id="model-name">Ginto AI</span>
        </div>
        <?php endif; ?>
      </div>
      
      <!-- Star on GitHub + Theme toggle -->
      <div class="flex items-center gap-2">
        <a href="https://github.com/oliverbob/ginto.ai" target="_blank" 
           class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-sm"
           title="Star us on GitHub">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
          <span>Star us</span>
        </a>
        <button id="theme-toggle" class="p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-800 transition-colors" title="Toggle theme">
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

        <!-- Example prompts (loaded dynamically) -->
        <div id="welcome-prompts" class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-lg">
          <!-- Prompts will be fetched from /chat/prompts/ and injected here -->
          <div id="welcome-prompts-loading" class="text-sm text-gray-500">Loading prompts…</div>
        </div>
      </div>
    </div>
    
    <!-- Composer -->
    <div id="composer" class="sticky bottom-0 pb-6 pt-4 bg-gradient-to-t from-gray-50 dark:from-gray-950 via-gray-50 dark:via-gray-950 to-transparent z-40 lg:z-50">
      <div class="relative bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xl">
        <!-- Attachment Preview -->
        <div id="attach-preview" class="hidden px-4 pt-3 pb-2 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-start gap-3">
            <div class="relative">
              <img id="attach-preview-img" class="w-20 h-20 object-cover rounded-lg border border-gray-300 dark:border-gray-600" src="" alt="Attached image">
              <button id="attach-remove" class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center text-xs shadow-md" title="Remove attachment">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
            <div class="flex-1 text-sm text-gray-600 dark:text-gray-400">
              <p id="attach-filename" class="font-medium truncate"></p>
              <p class="text-xs text-indigo-500">Image will be analyzed with vision model</p>
            </div>
          </div>
        </div>
        <textarea 
          id="prompt" 
          rows="1"
          placeholder="Ask anything..." 
          class="w-full px-4 py-4 pr-24 bg-transparent resize-none focus:outline-none text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 max-h-40"
          style="min-height: 56px;"
        ></textarea>
        
        <!-- Composer Actions -->
        <div class="absolute right-2 bottom-2 flex items-center gap-1">
          <!-- Attach file (hidden input) -->
          <input type="file" id="attach-input" accept="image/*" class="hidden">
          <button id="attach-btn" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors" title="Attach image">
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
