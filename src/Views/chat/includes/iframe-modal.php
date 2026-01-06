<?php
/**
 * Universal Iframe Modal
 * Full-screen iframe viewer with minimize, maximize, and exit controls
 */
?>
<!-- Universal Iframe Modal -->
<div id="iframe-modal" class="fixed inset-0 bg-black/80 z-[60] hidden flex items-center justify-center">
  <div id="iframe-modal-container" class="iframe-modal-normal bg-gray-900 rounded-xl shadow-2xl flex flex-col overflow-hidden transition-all duration-300">
    <!-- Modal Header -->
    <div id="iframe-modal-header" class="bg-gray-800 border-b border-gray-700 px-4 py-2 flex items-center justify-between flex-shrink-0">
      <div class="flex items-center gap-3">
        <div id="iframe-modal-icon" class="w-6 h-6 text-indigo-400">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/>
          </svg>
        </div>
        <span id="iframe-modal-title" class="text-white font-medium text-sm">External App</span>
        <span id="iframe-modal-url" class="text-gray-400 text-xs truncate max-w-[300px]"></span>
      </div>
      <div class="flex items-center gap-1">
        <!-- Open in new tab -->
        <button id="iframe-modal-newtab" class="p-2 rounded-lg hover:bg-gray-700 text-gray-400 hover:text-white transition-colors" title="Open in new tab">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
          </svg>
        </button>
        <div class="w-px h-4 bg-gray-600 mx-1"></div>
        <!-- Minimize (-) -->
        <button id="iframe-modal-minimize" class="p-2 rounded-lg hover:bg-gray-700 text-gray-400 hover:text-yellow-400 transition-colors" title="Minimize">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
          </svg>
        </button>
        <!-- Maximize (square) -->
        <button id="iframe-modal-maximize" class="p-2 rounded-lg hover:bg-gray-700 text-gray-400 hover:text-green-400 transition-colors" title="Maximize">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/>
          </svg>
        </button>
        <!-- Fullscreen (arrows out) -->
        <button id="iframe-modal-fullscreen" class="p-2 rounded-lg hover:bg-gray-700 text-gray-400 hover:text-blue-400 transition-colors" title="Fullscreen">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
          </svg>
        </button>
        <!-- Close (x) -->
        <button id="iframe-modal-close" class="p-2 rounded-lg hover:bg-red-600/20 text-gray-400 hover:text-red-400 transition-colors" title="Close">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>
    
    <!-- Iframe Container -->
    <div class="flex-1 bg-white relative">
      <iframe id="iframe-modal-frame" class="w-full h-full border-0" src="about:blank" allow="clipboard-read; clipboard-write; fullscreen"></iframe>
      <!-- Loading overlay -->
      <div id="iframe-modal-loading" class="absolute inset-0 bg-gray-900 flex items-center justify-center">
        <div class="flex flex-col items-center gap-4">
          <div class="w-10 h-10 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
          <span class="text-gray-400 text-sm">Loading...</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Minimized Iframe Indicator -->
<div id="iframe-minimized-indicator" class="fixed bottom-4 right-20 z-[55] hidden">
  <button class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-full shadow-lg transition-colors">
    <div id="iframe-minimized-icon" class="w-4 h-4">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/>
      </svg>
    </div>
    <span id="iframe-minimized-title" class="text-sm font-medium">App</span>
  </button>
</div>

<style>
.iframe-modal-normal {
  width: calc(100vw - 80px);
  height: calc(100vh - 80px);
}
.iframe-modal-maximized {
  width: 100vw;
  height: 100vh;
  border-radius: 0;
}
@media (max-width: 768px) {
  .iframe-modal-normal {
    width: 100vw;
    height: 100vh;
    border-radius: 0;
  }
}
</style>

<script>
(function() {
  const modal = document.getElementById('iframe-modal');
  const container = document.getElementById('iframe-modal-container');
  const iframe = document.getElementById('iframe-modal-frame');
  const loading = document.getElementById('iframe-modal-loading');
  const titleEl = document.getElementById('iframe-modal-title');
  const urlEl = document.getElementById('iframe-modal-url');
  const iconEl = document.getElementById('iframe-modal-icon');
  const minimizedIndicator = document.getElementById('iframe-minimized-indicator');
  const minimizedTitle = document.getElementById('iframe-minimized-title');
  const minimizedIcon = document.getElementById('iframe-minimized-icon');
  
  const closeBtn = document.getElementById('iframe-modal-close');
  const minimizeBtn = document.getElementById('iframe-modal-minimize');
  const maximizeBtn = document.getElementById('iframe-modal-maximize');
  const newtabBtn = document.getElementById('iframe-modal-newtab');
  
  let isMaximized = false;
  let isMinimized = false;
  let currentUrl = null;
  let currentTitle = 'App';
  let currentIcon = null;
  
  /**
   * Open URL in iframe modal
   * @param {string} url - URL to load
   * @param {object} options - { title, icon (svg html string) }
   */
  window.openIframeModal = function(url, options = {}) {
    currentUrl = url;
    currentTitle = options.title || 'External App';
    currentIcon = options.icon || null;
    
    titleEl.textContent = currentTitle;
    urlEl.textContent = url;
    minimizedTitle.textContent = currentTitle;
    
    if (currentIcon) {
      iconEl.innerHTML = currentIcon;
      minimizedIcon.innerHTML = currentIcon;
    }
    
    // Show loading
    loading.classList.remove('hidden');
    
    // Load iframe
    iframe.src = url;
    
    // Show modal
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    isMinimized = false;
    minimizedIndicator.classList.add('hidden');
  };
  
  window.closeIframeModal = function() {
    modal.classList.add('hidden');
    minimizedIndicator.classList.add('hidden');
    document.body.style.overflow = '';
    iframe.src = 'about:blank';
    currentUrl = null;
    isMinimized = false;
    isMaximized = false;
    container.classList.remove('iframe-modal-maximized');
    container.classList.add('iframe-modal-normal');
  };
  
  function minimizeIframeModal() {
    modal.classList.add('hidden');
    minimizedIndicator.classList.remove('hidden');
    isMinimized = true;
  }
  
  function restoreIframeModal() {
    modal.classList.remove('hidden');
    minimizedIndicator.classList.add('hidden');
    isMinimized = false;
  }
  
  function toggleMaximize() {
    isMaximized = !isMaximized;
    if (isMaximized) {
      container.classList.remove('iframe-modal-normal');
      container.classList.add('iframe-modal-maximized');
      maximizeBtn.title = 'Restore';
    } else {
      container.classList.remove('iframe-modal-maximized');
      container.classList.add('iframe-modal-normal');
      maximizeBtn.title = 'Maximize';
    }
  }
  
  function toggleFullscreen() {
    if (!document.fullscreenElement) {
      // Enter fullscreen
      container.requestFullscreen().catch(err => {
        console.log('Fullscreen error:', err);
        // Fallback: just maximize
        if (!isMaximized) toggleMaximize();
      });
    } else {
      // Exit fullscreen
      document.exitFullscreen();
    }
  }
  
  // Update fullscreen button state on fullscreen change
  document.addEventListener('fullscreenchange', function() {
    const fullscreenBtn = document.getElementById('iframe-modal-fullscreen');
    if (document.fullscreenElement) {
      fullscreenBtn.title = 'Exit Fullscreen';
      fullscreenBtn.classList.add('text-blue-400');
    } else {
      fullscreenBtn.title = 'Fullscreen';
      fullscreenBtn.classList.remove('text-blue-400');
    }
  });
  
  function openInNewTab() {
    if (currentUrl) {
      window.open(currentUrl, '_blank');
    }
  }
  
  // Iframe loaded
  iframe.addEventListener('load', function() {
    if (iframe.src !== 'about:blank') {
      loading.classList.add('hidden');
    }
  });
  
  const fullscreenBtn = document.getElementById('iframe-modal-fullscreen');
  
  // Button handlers
  closeBtn.addEventListener('click', window.closeIframeModal);
  minimizeBtn.addEventListener('click', minimizeIframeModal);
  maximizeBtn.addEventListener('click', toggleMaximize);
  fullscreenBtn.addEventListener('click', toggleFullscreen);
  newtabBtn.addEventListener('click', openInNewTab);
  minimizedIndicator.addEventListener('click', restoreIframeModal);
  
  // Close on Escape (only if not minimized and not fullscreen)
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && !modal.classList.contains('hidden') && !document.fullscreenElement) {
      window.closeIframeModal();
    }
  });
  
  // Click outside to close (on backdrop)
  modal.addEventListener('click', function(e) {
    if (e.target === modal) {
      window.closeIframeModal();
    }
  });
})();
</script>
