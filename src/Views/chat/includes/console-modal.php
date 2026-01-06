<?php
/**
 * Console Terminal Modal (Full Screen - Admin Only)
 * Provides multi-tab terminal access with WebSocket connection
 */
if (empty($isAdmin)) return;
?>
<!-- Console Terminal Modal (Full Screen - Admin Only) -->
<div id="console-modal" class="fixed inset-0 bg-black/50 dark:bg-black/90 z-50 hidden flex items-center justify-center">
  <div class="w-full h-full flex flex-col">
    <!-- Modal Header -->
    <div class="bg-gray-900 border-b border-gray-700 px-4 py-2 flex items-center justify-between flex-shrink-0">
      <div class="flex items-center gap-3">
        <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z"/>
        </svg>
        <h3 class="text-white font-semibold">Console</h3>
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
        <!-- Tabs will be added here dynamically -->
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
      <!-- Terminal panes will be added here dynamically -->
    </div>
  </div>
</div>

<!-- Minimized Console uses shared iframe-minimized-container -->

<script>
(function() {
  const modal = document.getElementById('console-modal');
  const terminalsContainer = document.getElementById('console-terminals');
  const tabsContainer = document.getElementById('console-tabs');
  const addTabBtn = document.getElementById('add-console-tab');
  const openBtn = document.getElementById('open-console');
  const closeBtn = document.getElementById('close-console');
  const reconnectBtn = document.getElementById('console-reconnect');
  const statusEl = document.getElementById('console-status');
  
  if (!modal || !terminalsContainer || !openBtn) return;
  
  // Tab management
  let tabs = [];
  let activeTabId = null;
  let tabCounter = 0;
  let isMinimized = false;
  const PING_INTERVAL_MS = 25000;
  const RECONNECT_DELAY_MS = 2000;
  
  const minimizeBtn = document.getElementById('minimize-console');
  let consoleMinimizedEl = null;
  
  const CONSOLE_STORAGE_KEY = 'ginto_console_minimized';
  
  // Get shared container (may not exist at init time)
  function getSharedMinimizedContainer() {
    return document.getElementById('iframe-minimized-container');
  }
  
  // Create minimized console indicator dynamically
  function createConsoleMinimizedIndicator() {
    if (consoleMinimizedEl) return;
    
    const container = getSharedMinimizedContainer();
    if (!container) {
      console.warn('[Console] Shared minimized container not found');
      return;
    }
    
    const div = document.createElement('div');
    div.id = 'console-minimized-indicator';
    div.className = 'minimized-tab flex items-center bg-gray-800 hover:bg-gray-700 text-white shadow-lg border border-gray-600 cursor-pointer';
    div.innerHTML = `
      <button class="flex items-center justify-center gap-2 p-3 flex-shrink-0">
        <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z"/>
        </svg>
        <span class="tab-title text-sm font-medium whitespace-nowrap">Console</span>
      </button>
      <button class="tab-close text-white/70 hover:text-red-300 transition-colors flex-shrink-0 pr-3" title="Close Console">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    `;
    
    // Click to restore
    div.querySelector('button:first-child').addEventListener('click', restoreConsole);
    // Close button
    div.querySelector('.tab-close').addEventListener('click', (e) => {
      e.stopPropagation();
      closeConsoleCompletely();
    });
    
    container.appendChild(div);
    consoleMinimizedEl = div;
  }
  
  function removeConsoleMinimizedIndicator() {
    if (consoleMinimizedEl) {
      consoleMinimizedEl.remove();
      consoleMinimizedEl = null;
    }
  }
  
  function saveConsoleState() {
    try {
      localStorage.setItem(CONSOLE_STORAGE_KEY, JSON.stringify({ 
        isMinimized: isMinimized,
        hasOpenTabs: tabs.length > 0
      }));
    } catch (e) {}
  }
  
  function loadConsoleState() {
    try {
      const data = JSON.parse(localStorage.getItem(CONSOLE_STORAGE_KEY) || '{}');
      if (data.isMinimized && data.hasOpenTabs) {
        // Console was minimized with open tabs - show minimized indicator
        isMinimized = true;
        createConsoleMinimizedIndicator();
      }
    } catch (e) {}
  }
  
  function updateStatus(status, color) {
    statusEl.textContent = status;
    statusEl.className = 'ml-auto text-xs px-2 py-0.5 rounded-full ' + color;
  }
  
  function minimizeConsole() {
    isMinimized = true;
    modal.classList.add('hidden');
    createConsoleMinimizedIndicator();
    saveConsoleState();
  }
  
  function restoreConsole() {
    isMinimized = false;
    removeConsoleMinimizedIndicator();
    modal.classList.remove('hidden');
    const activeTab = tabs.find(t => t.id === activeTabId);
    if (activeTab && activeTab.fitAddon && activeTab.term) {
      setTimeout(() => activeTab.fitAddon.fit(), 100);
    }
    saveConsoleState();
  }
  
  function closeConsoleCompletely() {
    // Close all tabs and remove minimized indicator
    tabs.forEach(t => {
      if (t.ws) t.ws.close();
    });
    tabs = [];
    isMinimized = false;
    removeConsoleMinimizedIndicator();
    modal.classList.add('hidden');
    localStorage.removeItem(CONSOLE_STORAGE_KEY);
  }
  
  window.minimizeConsole = minimizeConsole;
  window.restoreConsole = restoreConsole;
  
  if (minimizeBtn) minimizeBtn.onclick = minimizeConsole;
  
  // Load console state after DOM is fully ready (iframe-modal container needs to exist)
  setTimeout(loadConsoleState, 100);
  
  function createTab(initialCommand, targetMode = null) {
    tabCounter++;
    const tabId = 'tab-' + tabCounter;
    
    // Create tab button
    const tabBtn = document.createElement('div');
    tabBtn.className = 'flex items-center gap-1 px-3 py-1.5 rounded-t bg-gray-700 hover:bg-gray-600 cursor-pointer text-sm text-white transition-colors';
    tabBtn.dataset.tabId = tabId;
    tabBtn.innerHTML = `
      <span class="tab-title">Terminal ${tabCounter}</span>
      <button class="tab-close ml-1 p-0.5 rounded hover:bg-gray-500 text-gray-400 hover:text-white" data-close-tab="${tabId}">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    `;
    tabsContainer.appendChild(tabBtn);
    
    // Create terminal container
    const termEl = document.createElement('div');
    termEl.className = 'absolute inset-0 hidden';
    termEl.id = tabId + '-terminal';
    terminalsContainer.appendChild(termEl);
    
    // Initialize terminal
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
      pendingCommand: initialCommand || null,
      targetMode: targetMode,  // 'sandbox' or null (default to host for admin)
      sessionId: 'session-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9)
    };
    
    tabs.push(tab);
    
    // Handle terminal input
    term.onData(function(data) {
      if (tab.ws && tab.ws.readyState === WebSocket.OPEN) {
        tab.ws.send(data);
      }
    });
    
    // Tab click handler
    tabBtn.addEventListener('click', function(e) {
      if (e.target.closest('.tab-close')) return;
      switchToTab(tabId);
    });
    
    // Close tab handler
    tabBtn.querySelector('.tab-close').addEventListener('click', function(e) {
      e.stopPropagation();
      closeTab(tabId);
    });
    
    switchToTab(tabId);
    connectTab(tab, targetMode);
    
    return tab;
  }
  
  function switchToTab(tabId) {
    tabs.forEach(t => {
      if (t.id === tabId) {
        t.termEl.classList.remove('hidden');
        t.tabBtn.classList.add('bg-gray-600');
        t.tabBtn.classList.remove('bg-gray-700');
        activeTabId = tabId;
        setTimeout(() => {
          t.fitAddon.fit();
          t.term.focus();
        }, 50);
        // Update status based on this tab's connection
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
    
    // Clean up
    if (tab.pingInterval) clearInterval(tab.pingInterval);
    if (tab.reconnectTimeout) clearTimeout(tab.reconnectTimeout);
    if (tab.ws) tab.ws.close();
    tab.term.dispose();
    tab.termEl.remove();
    tab.tabBtn.remove();
    
    tabs.splice(tabIndex, 1);
    
    // If we closed the active tab, switch to another
    if (activeTabId === tabId) {
      if (tabs.length > 0) {
        switchToTab(tabs[tabs.length - 1].id);
      } else {
        activeTabId = null;
      }
    }
    
    // If no tabs left, close the modal
    if (tabs.length === 0) {
      closeConsole();
    }
  }
  
  async function connectTab(tab, targetMode = null) {
    if (tab.ws && tab.ws.readyState === WebSocket.OPEN) return;
    if (tab.reconnectTimeout) { clearTimeout(tab.reconnectTimeout); tab.reconnectTimeout = null; }
    
    if (tab.id === activeTabId) {
      updateStatus('Connecting...', 'bg-yellow-600 text-yellow-100');
    }
    
    // Wait for auth to be ready
    if (window.GINTO_AUTH_PROMISE) {
      await window.GINTO_AUTH_PROMISE;
    }
    
    const host = window.location.hostname || '127.0.0.1';
    const cols = tab.term.cols;
    const rows = tab.term.rows;
    const wsProtocol = location.protocol === 'https:' ? 'wss:' : 'ws:';
    
    const isAdmin = window.GINTO_AUTH?.isAdmin || false;
    const sandboxId = window.GINTO_AUTH?.sandbox?.id || null;
    
    console.log('[Console] Auth ready, sandboxId:', sandboxId, 'isAdmin:', isAdmin, 'targetMode:', targetMode);
    
    let wsUrl;
    
    // targetMode can be 'sandbox' (for OpenWebUI install) or 'os' (default for admin console)
    if (targetMode === 'sandbox' && sandboxId) {
      // Explicitly requested sandbox connection (e.g. for OpenWebUI install)
      const containerName = 'ginto-sandbox-' + sandboxId;
      wsUrl = `${wsProtocol}//${host}/terminal/terminal?mode=sandbox&container=${encodeURIComponent(containerName)}&cols=${cols}&rows=${rows}&session=${encodeURIComponent(tab.sessionId)}`;
      tab.tabBtn.querySelector('.tab-title').textContent = 'Sandbox';
    } else if (isAdmin) {
      // Admin gets host terminal by default
      wsUrl = `${wsProtocol}//${host}/terminal/terminal?mode=os&cols=${cols}&rows=${rows}&session=${encodeURIComponent(tab.sessionId)}`;
    } else if (sandboxId) {
      // Non-admin users with sandbox get sandbox terminal
      const containerName = 'ginto-sandbox-' + sandboxId;
      wsUrl = `${wsProtocol}//${host}/terminal/terminal?mode=sandbox&container=${encodeURIComponent(containerName)}&cols=${cols}&rows=${rows}&session=${encodeURIComponent(tab.sessionId)}`;
      tab.tabBtn.querySelector('.tab-title').textContent = 'Sandbox';
    } else {
      // No sandbox - show error in terminal
      tab.term.write('\r\n\x1b[33m*** No sandbox available. Please create one first. ***\x1b[0m\r\n');
      updateStatus('No Sandbox', 'bg-yellow-600 text-yellow-100');
      return;
    }
    
    tab.ws = new WebSocket(wsUrl);
    tab.ws.binaryType = 'arraybuffer';
    
    tab.ws.addEventListener('open', function() {
      if (tab.id === activeTabId) {
        updateStatus('Connected', 'bg-green-600 text-green-100');
      }
      
      // Start keepalive pings
      tab.pingInterval = setInterval(function() {
        if (tab.ws && tab.ws.readyState === WebSocket.OPEN) {
          try { tab.ws.send(JSON.stringify({ type: 'ping' })); } catch(e) {}
        }
      }, PING_INTERVAL_MS);
      
      // Send pending command
      if (tab.pendingCommand) {
        setTimeout(function() {
          tab.ws.send(tab.pendingCommand + '\n');
          tab.pendingCommand = null;
        }, 500);
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
      
      // Auto-reconnect
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
  
  function openConsole(initialCommand) {
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    isMinimized = false;
    removeConsoleMinimizedIndicator();
    
    // Create first tab if none exist
    if (tabs.length === 0) {
      createTab(initialCommand);
    } else {
      // Switch to active tab and maybe run command
      const activeTab = tabs.find(t => t.id === activeTabId);
      if (activeTab) {
        setTimeout(() => {
          activeTab.fitAddon.fit();
          activeTab.term.focus();
        }, 100);
        if (initialCommand && activeTab.ws && activeTab.ws.readyState === WebSocket.OPEN) {
          activeTab.ws.send(initialCommand + '\n');
        }
      }
    }
    saveConsoleState();
  }
  
  // Open console with command - creates new tab if session already active
  // targetMode: 'sandbox' to connect to user's sandbox, null for default (host for admin)
  window.openConsoleWithCommand = function(command, targetMode = null) {
    // Hide minimized indicator if visible
    isMinimized = false;
    removeConsoleMinimizedIndicator();
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    if (tabs.length === 0) {
      // No active session - create first tab with command
      createTab(command, targetMode);
    } else {
      // Already have active session(s) - create new tab for this command
      createTab(command, targetMode);
    }
    saveConsoleState();
  };
  
  function closeConsole() {
    modal.classList.add('hidden');
    document.body.style.overflow = '';
    removeConsoleMinimizedIndicator();
    
    // Close all tabs
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
    isMinimized = false;
    localStorage.removeItem(CONSOLE_STORAGE_KEY);
  }
  
  openBtn.addEventListener('click', function(e) {
    e.preventDefault();
    openConsole();
  });
  
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
    createTab();
  });
  
  // Handle resize for all terminals
  window.addEventListener('resize', function() {
    if (modal.classList.contains('hidden')) return;
    tabs.forEach(tab => {
      tab.fitAddon.fit();
      if (tab.ws && tab.ws.readyState === WebSocket.OPEN) {
        tab.ws.send(JSON.stringify({ type: 'resize', cols: tab.term.cols, rows: tab.term.rows }));
      }
    });
  });
  
  // Close on Escape
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
      closeConsole();
    }
  });
})();
</script>
