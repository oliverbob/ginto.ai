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
        <!-- Expose to Web Container (shown only for Docker OpenWebUI) -->
        <div id="iframe-modal-expose-container" class="hidden flex items-center gap-2">
          <!-- Exposed URL (clickable) -->
          <a id="iframe-expose-url" href="#" target="_blank" class="hidden text-xs text-emerald-400 hover:text-emerald-300 font-mono" title="Open exposed URL"></a>
          <!-- Copy URL Button -->
          <button id="iframe-expose-copy" class="hidden p-1 rounded hover:bg-gray-700 text-gray-400 hover:text-emerald-400 transition-colors" title="Copy URL">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
            </svg>
          </button>
          <!-- Countdown Timer -->
          <span id="iframe-expose-countdown" class="text-xs text-yellow-400 font-mono hidden">--:--</span>
          <!-- Expose/Disconnect Button -->
          <button id="iframe-modal-expose" class="px-2 py-1 rounded-lg hover:bg-blue-600/30 text-blue-400 hover:text-blue-300 transition-colors text-xs font-medium" title="Share on the internet">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Expose to Web
          </button>
          <!-- Disconnect Button (shown when tunnel active) -->
          <button id="iframe-modal-disconnect" class="hidden px-2 py-1 rounded-lg hover:bg-red-600/20 text-red-400 hover:text-red-300 transition-colors text-xs font-medium" title="Disconnect tunnel">
            <svg class="w-3 h-3 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
        <!-- Refresh -->
        <button id="iframe-modal-refresh" class="p-2 rounded-lg hover:bg-gray-700 text-gray-400 hover:text-cyan-400 transition-colors" title="Refresh">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
          </svg>
        </button>
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
        <!-- Open in new tab -->
        <button id="iframe-modal-newtab" class="p-2 rounded-lg hover:bg-gray-700 text-gray-400 hover:text-white transition-colors" title="Open in new tab">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
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
      <iframe id="iframe-modal-frame" class="w-full h-full border-0" src="about:blank" allow="clipboard-read; clipboard-write; fullscreen" sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-modals allow-downloads"></iframe>
      <!-- Loading overlay -->
      <div id="iframe-modal-loading" class="absolute inset-0 bg-gray-900 flex items-center justify-center">
        <div class="flex flex-col items-center gap-4">
          <div class="w-10 h-10 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
          <span id="iframe-modal-loading-text" class="text-gray-400 text-sm">Loading...</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Minimized Iframe Tabs Container (stacked vertically above composer) -->
<div id="iframe-minimized-container" class="fixed bottom-36 right-4 z-[55] flex flex-col items-end gap-2"></div>

<style>
/* Minimized tab - circle by default, expands on hover */
.minimized-tab {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  overflow: hidden;
  transition: all 0.2s ease;
}
.minimized-tab:hover {
  width: auto;
  border-radius: 9999px;
}
.minimized-tab .tab-title,
.minimized-tab .tab-close {
  opacity: 0;
  width: 0;
  padding: 0;
  overflow: hidden;
  transition: all 0.2s ease;
}
.minimized-tab:hover .tab-title,
.minimized-tab:hover .tab-close {
  opacity: 1;
  width: auto;
}
.minimized-tab:hover .tab-close {
  padding-right: 0.75rem;
}

.admin-minimized-tab {
    /* Use a green -> teal gradient for the Admin Console to distinguish it */
  background: linear-gradient(135deg, #10B981 0%, #0EA5A4 100%); /* emerald -> teal */
  color: white;
}
.admin-minimized-tab .tab-title { font-weight: 600; }

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
  const loadingText = document.getElementById('iframe-modal-loading-text');
  const titleEl = document.getElementById('iframe-modal-title');
  const urlEl = document.getElementById('iframe-modal-url');
  const iconEl = document.getElementById('iframe-modal-icon');
  const minimizedContainer = document.getElementById('iframe-minimized-container');
  
  const closeBtn = document.getElementById('iframe-modal-close');
  const minimizeBtn = document.getElementById('iframe-modal-minimize');
  const maximizeBtn = document.getElementById('iframe-modal-maximize');
  const newtabBtn = document.getElementById('iframe-modal-newtab');
  const refreshBtn = document.getElementById('iframe-modal-refresh');
  const fullscreenBtn = document.getElementById('iframe-modal-fullscreen');
  
  // Tab management
  const STORAGE_KEY = 'ginto_iframe_tabs';
  let tabs = []; // Array of { id, url, title, icon, isMinimized, isMaximized }
  let activeTabId = null;
  let tabIdCounter = 0;
  
  // Generate unique tab ID
  function generateTabId() {
    return 'tab_' + Date.now() + '_' + (++tabIdCounter);
  }
  
  // Save tabs to localStorage
  function saveTabs() {
    try {
      const saveData = tabs.map(t => ({
        id: t.id,
        url: t.url,
        title: t.title,
        icon: t.icon,
        isMinimized: t.isMinimized,
        isMaximized: t.isMaximized
      }));
      localStorage.setItem(STORAGE_KEY, JSON.stringify({ tabs: saveData, activeTabId }));
    } catch (e) {
      console.warn('[IframeModal] Failed to save tabs:', e);
    }
  }
  
  // Load tabs from localStorage
  function loadTabs() {
    try {
      const data = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
      if (data.tabs && data.tabs.length > 0) {
        tabs = data.tabs;
        activeTabId = data.activeTabId;
        tabIdCounter = tabs.length;
        
        // Render minimized tabs
        tabs.forEach(tab => {
          if (tab.isMinimized) {
            createMinimizedTab(tab);
          }
        });
        
        // If there was an active non-minimized tab, restore it
        const activeTab = tabs.find(t => t.id === activeTabId && !t.isMinimized);
        if (activeTab) {
          showTab(activeTab);
        }
      }
    } catch (e) {
      console.warn('[IframeModal] Failed to load tabs:', e);
    }
  }
  
  // Create minimized tab button
  function createMinimizedTab(tab) {
    const existing = document.getElementById('minimized-' + tab.id);
    if (existing) existing.remove();
    
    const btn = document.createElement('div');
    btn.id = 'minimized-' + tab.id;
    btn.className = 'minimized-tab flex items-center bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg cursor-pointer';
    btn.innerHTML = `
      <button class="flex items-center justify-center gap-2 p-3 flex-shrink-0" title="Restore ${tab.title}">
        <div class="w-5 h-5 flex-shrink-0">${tab.icon || getDefaultIcon()}</div>
        <span class="tab-title text-sm font-medium whitespace-nowrap">${tab.title}</span>
      </button>
      <button class="tab-close text-white/70 hover:text-red-300 transition-colors flex-shrink-0" title="Close">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    `;
    
    // Restore on click (main button)
    btn.querySelector('button:first-child').addEventListener('click', () => restoreTab(tab.id));
    // Close on X click
    btn.querySelector('.tab-close').addEventListener('click', (e) => {
      e.stopPropagation();
      closeTab(tab.id);
    });
    
    minimizedContainer.appendChild(btn);
  }
  
  // Remove minimized tab button
  function removeMinimizedTab(tabId) {
    const el = document.getElementById('minimized-' + tabId);
    if (el) el.remove();
  }
  
  // Get default icon SVG
  function getDefaultIcon() {
    return `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/>
    </svg>`;
  }
  
  // Show tab in modal
  function showTab(tab) {
    titleEl.textContent = tab.title;
    urlEl.textContent = tab.url;
    if (tab.icon) {
      iconEl.innerHTML = tab.icon;
    } else {
      iconEl.innerHTML = getDefaultIcon();
    }
    
    // Apply maximized state
    if (tab.isMaximized) {
      container.classList.remove('iframe-modal-normal');
      container.classList.add('iframe-modal-maximized');
      maximizeBtn.title = 'Restore';
    } else {
      container.classList.remove('iframe-modal-maximized');
      container.classList.add('iframe-modal-normal');
      maximizeBtn.title = 'Maximize';
    }
    
    loading.classList.remove('hidden');
    iframe.src = tab.url;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    activeTabId = tab.id;
    
    // Mark as not minimized
    tab.isMinimized = false;
    removeMinimizedTab(tab.id);
    saveTabs();
    
    // Dispatch event for extensions (e.g., Expose to Web button)
    window.dispatchEvent(new CustomEvent('iframeModalShown', { detail: { tab } }));
  }
  
  /**
   * Open URL in iframe modal
   * @param {string} url - URL to load
   * @param {object} options - { title, icon (svg html string), loadingMessage, waitForReady }
   */
  window.openIframeModal = function(url, options = {}) {
    // Check if this URL is already open in a tab
    let existingTab = tabs.find(t => t.url === url);
    if (existingTab) {
      // Restore existing tab
      showTab(existingTab);
      return;
    }
    
    // Create new tab
    const tab = {
      id: generateTabId(),
      url: url,
      title: options.title || 'External App',
      icon: options.icon || null,
      isMinimized: false,
      isMaximized: false
    };
    tabs.push(tab);
    
    // If waitForReady is true, show modal with loading message first
    if (options.waitForReady) {
      showTabWithLoading(tab, options.loadingMessage || 'Starting service...');
    } else {
      showTab(tab);
    }
  };
  
  // Show tab with loading state (doesn't load iframe yet)
  function showTabWithLoading(tab, message) {
    titleEl.textContent = tab.title;
    urlEl.textContent = tab.url;
    if (tab.icon) {
      iconEl.innerHTML = tab.icon;
    } else {
      iconEl.innerHTML = getDefaultIcon();
    }
    
    // Show loading with custom message
    loadingText.textContent = message;
    loading.classList.remove('hidden');
    iframe.src = 'about:blank'; // Don't load yet
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    activeTabId = tab.id;
    
    tab.isMinimized = false;
    removeMinimizedTab(tab.id);
    saveTabs();
  }
  
  // Update loading message for current tab
  window.updateIframeLoadingMessage = function(message) {
    loadingText.textContent = message;
  };
  
  // Load the iframe URL (after waiting for service to be ready)
  window.loadIframeUrl = function(url) {
    const tab = tabs.find(t => t.id === activeTabId);
    if (tab && tab.url === url) {
      loadingText.textContent = 'Loading...';
      iframe.src = url;
    }
  };
  
  // Close specific tab
  function closeTab(tabId) {
    const idx = tabs.findIndex(t => t.id === tabId);
    if (idx === -1) return;
    
    tabs.splice(idx, 1);
    removeMinimizedTab(tabId);
    
    if (activeTabId === tabId) {
      modal.classList.add('hidden');
      document.body.style.overflow = '';
      iframe.src = 'about:blank';
      activeTabId = null;
      container.classList.remove('iframe-modal-maximized');
      container.classList.add('iframe-modal-normal');
    }
    
    saveTabs();
  }
  
  window.closeIframeModal = function() {
    if (activeTabId) {
      closeTab(activeTabId);
    }
  };
  
  // Minimize current tab
  function minimizeCurrentTab() {
    const tab = tabs.find(t => t.id === activeTabId);
    if (!tab) return;
    
    tab.isMinimized = true;
    modal.classList.add('hidden');
    document.body.style.overflow = '';
    createMinimizedTab(tab);
    activeTabId = null;
    saveTabs();
  }
  
  // Restore tab from minimized state
  function restoreTab(tabId) {
    const tab = tabs.find(t => t.id === tabId);
    if (!tab) return;
    
    // If there's an active tab, minimize it first
    if (activeTabId && activeTabId !== tabId) {
      const currentTab = tabs.find(t => t.id === activeTabId);
      if (currentTab) {
        currentTab.isMinimized = true;
        createMinimizedTab(currentTab);
      }
    }
    
    showTab(tab);
  }
  
  // Toggle maximize
  function toggleMaximize() {
    const tab = tabs.find(t => t.id === activeTabId);
    if (!tab) return;
    
    tab.isMaximized = !tab.isMaximized;
    if (tab.isMaximized) {
      container.classList.remove('iframe-modal-normal');
      container.classList.add('iframe-modal-maximized');
      maximizeBtn.title = 'Restore';
    } else {
      container.classList.remove('iframe-modal-maximized');
      container.classList.add('iframe-modal-normal');
      maximizeBtn.title = 'Maximize';
    }
    saveTabs();
  }
  
  // Toggle fullscreen
  function toggleFullscreen() {
    if (!document.fullscreenElement) {
      container.requestFullscreen().catch(err => {
        console.log('Fullscreen error:', err);
        if (!tabs.find(t => t.id === activeTabId)?.isMaximized) toggleMaximize();
      });
    } else {
      document.exitFullscreen();
    }
  }
  
  // Refresh iframe
  function refreshIframe() {
    const tab = tabs.find(t => t.id === activeTabId);
    if (!tab) return;
    
    loading.classList.remove('hidden');
    iframe.src = 'about:blank';
    setTimeout(() => {
      iframe.src = tab.url;
    }, 100);
  }
  
  // Open in new tab
  function openInNewTab() {
    const tab = tabs.find(t => t.id === activeTabId);
    if (tab) {
      window.open(tab.url, '_blank');
    }
  }
  
  // Update fullscreen button state on fullscreen change
  document.addEventListener('fullscreenchange', function() {
    if (document.fullscreenElement) {
      fullscreenBtn.title = 'Exit Fullscreen';
      fullscreenBtn.classList.add('text-blue-400');
    } else {
      fullscreenBtn.title = 'Fullscreen';
      fullscreenBtn.classList.remove('text-blue-400');
    }
  });
  
  // Iframe loaded
  iframe.addEventListener('load', function() {
    if (iframe.src !== 'about:blank') {
      loading.classList.add('hidden');
    }
  });
  
  // Button handlers
  closeBtn.addEventListener('click', window.closeIframeModal);
  minimizeBtn.addEventListener('click', minimizeCurrentTab);
  maximizeBtn.addEventListener('click', toggleMaximize);
  fullscreenBtn.addEventListener('click', toggleFullscreen);
  newtabBtn.addEventListener('click', openInNewTab);
  refreshBtn.addEventListener('click', refreshIframe);
  
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
  
  // Load saved tabs on init
  loadTabs();
})();
</script>
