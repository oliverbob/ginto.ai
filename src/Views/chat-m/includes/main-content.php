<?php
/**
 * Main Content Area — Mobile WebView version (/chat-m)
 *
 * Differences from chat/includes/main-content.php:
 * - No top/left offsets (no fixed mobile header, no sidebar)
 * - Desktop header (<header id="main-header">) is fully removed
 */
?>
<!-- Main Content - full-width, no offsets (mobile WebView embed) -->
<main id="main-content" class="flex-1 flex flex-col min-h-screen">
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
