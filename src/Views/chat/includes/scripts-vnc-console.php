<?php
/**
 * VNC Desktop and Console Tab Terminal Management Scripts
 */
?>
<script>
  // ========================================
  // My Files / My Computer Tab Switching
  // ========================================
  const tabMyFiles = document.getElementById('tab-my-files');
  const tabMyComputer = document.getElementById('tab-my-computer');
  const tabConsole = document.getElementById('tab-console');
  const vncDesktopContainer = document.getElementById('vnc-desktop-container');
  const vncUserStatus = document.getElementById('vnc-user-status');
  const vncLoading = document.getElementById('vnc-loading');
  const vncLoadingText = document.getElementById('vnc-loading-text');
  const consoleTabContainer = document.getElementById('console-tab-container');
  const consoleTabStatus = document.getElementById('console-tab-status');
  const consoleTabAddBtn = document.getElementById('console-tab-add');
  const consoleTabTabs = document.getElementById('console-tab-tabs');
  const consoleTabTerminals = document.getElementById('console-tab-terminals');
  
  let vncIframeUser = null;
  let vncConnected = false;
  let currentEditorTab = 'files'; // 'files', 'computer', or 'console'
  let currentSandboxBackend = 'lxd'; // 'lxd' or 'docker' - affects available tabs
  
  // Console tab terminal management
  let consoleTabTerminalTabs = [];
  let consoleTabActiveId = null;
  let consoleTabCounter = 0;
  const CONSOLE_PING_INTERVAL_MS = 25000;
  
  /**
   * Update tab visibility based on sandbox backend
   * Docker mode: Only "My Files" tab is available
   * LXD mode: All tabs (My Files, My Computer, Console) are available
   */
  function updateTabsForBackend(backend) {
    currentSandboxBackend = backend || 'lxd';
    
    if (backend === 'docker') {
      // Docker mode: Hide My Computer and Console tabs
      if (tabMyComputer) tabMyComputer.classList.add('hidden');
      if (tabConsole) tabConsole.classList.add('hidden');
      
      // Ensure we're on My Files tab
      if (currentEditorTab !== 'files') {
        switchToFilesTab();
      }
      
      console.log('[Sandbox] Docker mode: VNC and Console tabs hidden');
    } else {
      // LXD mode: Show all tabs
      if (tabMyComputer) tabMyComputer.classList.remove('hidden');
      if (tabConsole) tabConsole.classList.remove('hidden');
      
      console.log('[Sandbox] LXD mode: All tabs available');
    }
  }

  function updateVncStatus(status, colorClass) {
    if (vncUserStatus) {
      vncUserStatus.textContent = status;
      vncUserStatus.className = 'text-xs px-2 py-0.5 rounded-full ' + colorClass;
    }
  }
  
  function updateConsoleTabStatus(status, colorClass) {
    if (consoleTabStatus) {
      consoleTabStatus.textContent = status;
      consoleTabStatus.className = 'text-xs px-2 py-0.5 rounded-full ' + colorClass;
    }
  }
  
  function setTabActive(tabEl) {
    tabEl.classList.remove('bg-gray-50', 'dark:bg-gray-700/50', 'text-gray-600', 'dark:text-gray-400', 'border-transparent');
    tabEl.classList.add('bg-white', 'dark:bg-gray-800', 'text-indigo-600', 'dark:text-indigo-400', 'border-indigo-500');
  }
  
  function setTabInactive(tabEl) {
    tabEl.classList.remove('bg-white', 'dark:bg-gray-800', 'text-indigo-600', 'dark:text-indigo-400', 'border-indigo-500');
    tabEl.classList.add('bg-gray-50', 'dark:bg-gray-700/50', 'text-gray-600', 'dark:text-gray-400', 'border-transparent');
  }
  
  function switchToFilesTab() {
    currentEditorTab = 'files';
    // Update tab styling
    if (tabMyFiles) setTabActive(tabMyFiles);
    if (tabMyComputer) setTabInactive(tabMyComputer);
    if (tabConsole) setTabInactive(tabConsole);
    // Show editor, hide others
    if (editorIframe) editorIframe.classList.remove('hidden');
    if (vncDesktopContainer) vncDesktopContainer.classList.add('hidden');
    if (consoleTabContainer) consoleTabContainer.classList.add('hidden');
    if (vncUserStatus) vncUserStatus.classList.add('hidden');
    if (consoleTabStatus) consoleTabStatus.classList.add('hidden');
    if (consoleTabAddBtn) consoleTabAddBtn.classList.add('hidden');
  }
  
  function switchToComputerTab() {
    currentEditorTab = 'computer';
    // Update tab styling
    if (tabMyComputer) setTabActive(tabMyComputer);
    if (tabMyFiles) setTabInactive(tabMyFiles);
    if (tabConsole) setTabInactive(tabConsole);
    // Hide editor and console, show VNC
    if (editorIframe) editorIframe.classList.add('hidden');
    if (vncDesktopContainer) vncDesktopContainer.classList.remove('hidden');
    if (consoleTabContainer) consoleTabContainer.classList.add('hidden');
    if (vncUserStatus) vncUserStatus.classList.remove('hidden');
    if (consoleTabStatus) consoleTabStatus.classList.add('hidden');
    if (consoleTabAddBtn) consoleTabAddBtn.classList.add('hidden');
    
    // Connect to VNC if not already connected
    if (!vncConnected) {
      connectToVncDesktop();
    }
  }
  
  function switchToConsoleTab() {
    currentEditorTab = 'console';
    // Update tab styling
    if (tabConsole) setTabActive(tabConsole);
    if (tabMyFiles) setTabInactive(tabMyFiles);
    if (tabMyComputer) setTabInactive(tabMyComputer);
    // Hide editor and VNC, show console
    if (editorIframe) editorIframe.classList.add('hidden');
    if (vncDesktopContainer) vncDesktopContainer.classList.add('hidden');
    if (consoleTabContainer) consoleTabContainer.classList.remove('hidden');
    if (vncUserStatus) vncUserStatus.classList.add('hidden');
    if (consoleTabStatus) consoleTabStatus.classList.remove('hidden');
    if (consoleTabAddBtn) consoleTabAddBtn.classList.remove('hidden');
    
    // Create first terminal tab if none exist
    if (consoleTabTerminalTabs.length === 0) {
      createConsoleTerminalTab();
    } else {
      // Fit the active terminal
      const activeTab = consoleTabTerminalTabs.find(t => t.id === consoleTabActiveId);
      if (activeTab && activeTab.fitAddon) {
        setTimeout(() => activeTab.fitAddon.fit(), 100);
      }
    }
  }
  
  async function connectToVncDesktop() {
    if (vncLoading) vncLoading.classList.remove('hidden');
    if (vncLoadingText) vncLoadingText.textContent = 'Connecting to desktop...';
    updateVncStatus('Connecting...', 'bg-yellow-600 text-yellow-100');
    
    try {
      const csrfToken = window.GINTO_AUTH?.csrfToken || '';
      const response = await fetch('/api/sandbox/vnc', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ csrf_token: csrfToken })
      });
      
      const data = await response.json();
      
      if (!response.ok || !data.success) {
        // Check if sandbox is being upgraded
        if (data.action === 'upgrading') {
          if (vncLoadingText) vncLoadingText.textContent = 'Installing VNC desktop... This takes 30-60 seconds. Retrying automatically...';
          updateVncStatus('Upgrading', 'bg-yellow-600 text-yellow-100');
          // Auto-retry after 15 seconds
          setTimeout(() => {
            if (currentEditorTab === 'computer') {
              connectToVncDesktop();
            }
          }, 15000);
          return;
        }
        
        if (vncLoadingText) vncLoadingText.textContent = data.error || 'Failed to connect to desktop';
        updateVncStatus('Error', 'bg-red-600 text-red-100');
        showToast(data.error || 'Failed to connect to desktop', 'error');
        return;
      }
      
      // Build noVNC URL - use proxied path for HTTPS compatibility
      const port = data.port;
      const vncUrl = location.origin + '/vnc-ws/' + port + '/vnc.html?autoconnect=true&resize=scale&reconnect=true';
      
      // Hide loading, create iframe
      if (vncLoading) vncLoading.classList.add('hidden');
      
      // Remove existing VNC iframe if any
      if (vncIframeUser) {
        vncIframeUser.remove();
        vncIframeUser = null;
      }
      
      // Create VNC iframe
      vncIframeUser = document.createElement('iframe');
      vncIframeUser.src = vncUrl;
      vncIframeUser.className = 'absolute inset-0 w-full h-full border-0';
      vncIframeUser.allow = 'fullscreen';
      vncIframeUser.addEventListener('load', function() {
        updateVncStatus('Connected', 'bg-green-600 text-green-100');
        vncConnected = true;
      });
      vncIframeUser.addEventListener('error', function() {
        updateVncStatus('Error', 'bg-red-600 text-red-100');
      });
      
      vncDesktopContainer.appendChild(vncIframeUser);
      
    } catch (err) {
      console.error('VNC connection error:', err);
      if (vncLoadingText) vncLoadingText.textContent = 'Connection failed: ' + err.message;
      updateVncStatus('Error', 'bg-red-600 text-red-100');
      showToast('Failed to connect to desktop: ' + err.message, 'error');
    }
  }
  
  // ========================================
  // Console Tab Terminal Management
  // ========================================
  function createConsoleTerminalTab() {
    const tabId = ++consoleTabCounter;
    const tabName = `Terminal ${tabId}`;
    
    // Create tab button
    const tabBtn = document.createElement('button');
    tabBtn.className = 'console-term-tab flex items-center gap-1 px-3 py-1 rounded text-sm bg-gray-700 text-white';
    tabBtn.innerHTML = `
      <span>${tabName}</span>
      <span class="close-tab ml-1 hover:text-red-400" title="Close">×</span>
    `;
    
    // Create terminal container
    const termContainer = document.createElement('div');
    termContainer.className = 'absolute inset-0 hidden';
    termContainer.id = `console-term-${tabId}`;
    
    // Initialize xterm
    const term = new Terminal({
      cursorBlink: true,
      fontSize: 14,
      fontFamily: 'Menlo, Monaco, "Courier New", monospace',
      theme: {
        background: '#1a1a1a',
        foreground: '#f0f0f0',
        cursor: '#f0f0f0'
      }
    });
    const fitAddon = new FitAddon.FitAddon();
    term.loadAddon(fitAddon);
    
    // Append to DOM
    if (consoleTabTabs) consoleTabTabs.appendChild(tabBtn);
    if (consoleTabTerminals) consoleTabTerminals.appendChild(termContainer);
    
    // Open terminal
    term.open(termContainer);
    
    // Tab object
    const tabObj = {
      id: tabId,
      name: tabName,
      tabBtn,
      termContainer,
      term,
      fitAddon,
      ws: null,
      pingInterval: null
    };
    consoleTabTerminalTabs.push(tabObj);
    
    // Tab click handler
    tabBtn.addEventListener('click', (e) => {
      if (e.target.classList.contains('close-tab')) {
        closeConsoleTerminalTab(tabId);
      } else {
        activateConsoleTerminalTab(tabId);
      }
    });
    
    // Activate this tab
    activateConsoleTerminalTab(tabId);
    
    // Connect WebSocket
    connectConsoleTerminal(tabObj);
    
    return tabObj;
  }
  
  function activateConsoleTerminalTab(tabId) {
    consoleTabTerminalTabs.forEach(t => {
      if (t.id === tabId) {
        t.tabBtn.classList.add('bg-gray-600');
        t.tabBtn.classList.remove('bg-gray-700');
        t.termContainer.classList.remove('hidden');
        consoleTabActiveId = tabId;
        setTimeout(() => {
          t.fitAddon.fit();
          t.term.focus();
        }, 50);
      } else {
        t.tabBtn.classList.remove('bg-gray-600');
        t.tabBtn.classList.add('bg-gray-700');
        t.termContainer.classList.add('hidden');
      }
    });
  }
  
  function closeConsoleTerminalTab(tabId) {
    const idx = consoleTabTerminalTabs.findIndex(t => t.id === tabId);
    if (idx === -1) return;
    
    const tabObj = consoleTabTerminalTabs[idx];
    
    // Cleanup
    if (tabObj.pingInterval) clearInterval(tabObj.pingInterval);
    if (tabObj.ws) tabObj.ws.close();
    tabObj.term.dispose();
    tabObj.tabBtn.remove();
    tabObj.termContainer.remove();
    
    consoleTabTerminalTabs.splice(idx, 1);
    
    // Activate another tab if needed
    if (consoleTabActiveId === tabId && consoleTabTerminalTabs.length > 0) {
      activateConsoleTerminalTab(consoleTabTerminalTabs[0].id);
    }
    
    // Update status if no tabs left
    if (consoleTabTerminalTabs.length === 0) {
      updateConsoleTabStatus('Disconnected', 'bg-gray-700 text-gray-400');
    }
  }
  
  async function connectConsoleTerminal(tabObj) {
    updateConsoleTabStatus('Connecting...', 'bg-yellow-600 text-yellow-100');
    
    // Wait for auth to be ready (especially important for visitors where sandbox comes from /dev/csrf)
    if (!window.GINTO_AUTH?.ready) {
      tabObj.term.write('\r\n\x1b[33mWaiting for session...\x1b[0m');
      let waited = 0;
      while (!window.GINTO_AUTH?.ready && waited < 5000) {
        await new Promise(r => setTimeout(r, 100));
        waited += 100;
      }
      tabObj.term.write('\r\n');
    }
    
    const host = window.location.hostname || '127.0.0.1';
    const cols = tabObj.term.cols;
    const rows = tabObj.term.rows;
    const wsProtocol = location.protocol === 'https:' ? 'wss:' : 'ws:';
    
    // Check if user has a sandbox - connect to sandbox terminal, otherwise host terminal (admin only)
    const isAdmin = window.GINTO_AUTH?.isAdmin || false;
    const sandboxId = window.GINTO_AUTH?.sandbox?.id || null;
    
    let wsUrl;
    let connectionMessage;
    
    console.log('[Console Tab] sandboxId:', sandboxId, 'isAdmin:', isAdmin, 'GINTO_AUTH:', window.GINTO_AUTH);
    
    if (isAdmin) {
      // Admin gets host (OS) terminal by default
      wsUrl = `${wsProtocol}//${host}/terminal/terminal?mode=os&cols=${cols}&rows=${rows}`;
      connectionMessage = '\r\n\x1b[32m*** Connected to host terminal (admin) ***\x1b[0m\r\n\r\n';
    } else if (sandboxId) {
      // Non-admin users with sandbox get sandbox terminal
      const containerName = 'ginto-sandbox-' + sandboxId;
      wsUrl = `${wsProtocol}//${host}/terminal/terminal?mode=sandbox&container=${encodeURIComponent(containerName)}&cols=${cols}&rows=${rows}`;
      connectionMessage = '\r\n\x1b[32m*** Connected to your sandbox terminal ***\x1b[0m\r\n\r\n';
    } else {
      // No sandbox and not admin - show error
      updateConsoleTabStatus('No Sandbox', 'bg-yellow-600 text-yellow-100');
      tabObj.term.write('\r\n\x1b[33m*** No sandbox available. Please create a sandbox first. ***\x1b[0m\r\n');
      return;
    }
    
    const ws = new WebSocket(wsUrl);
    ws.binaryType = 'arraybuffer';
    tabObj.ws = ws;
    
    ws.onopen = () => {
      updateConsoleTabStatus('Connected', 'bg-green-600 text-green-100');
      tabObj.term.write(connectionMessage);
      
      // Ping to keep alive
      tabObj.pingInterval = setInterval(() => {
        if (ws.readyState === WebSocket.OPEN) {
          try { ws.send(JSON.stringify({ type: 'ping' })); } catch(e) {}
        }
      }, CONSOLE_PING_INTERVAL_MS);
    };
    
    ws.onmessage = (event) => {
      try {
        tabObj.term.write(typeof event.data === 'string' ? event.data : new TextDecoder().decode(event.data));
      } catch (e) {}
    };
    
    ws.onerror = () => {
      updateConsoleTabStatus('Error', 'bg-red-600 text-red-100');
    };
    
    ws.onclose = () => {
      if (tabObj.pingInterval) clearInterval(tabObj.pingInterval);
      tabObj.term.writeln('\r\n\x1b[33mConnection closed.\x1b[0m');
      
      // Check if any other tabs are connected
      const anyConnected = consoleTabTerminalTabs.some(t => t.ws && t.ws.readyState === WebSocket.OPEN);
      if (!anyConnected) {
        updateConsoleTabStatus('Disconnected', 'bg-gray-700 text-gray-400');
      }
    };
    
    // Terminal input - send raw data like the old console
    tabObj.term.onData(data => {
      if (ws.readyState === WebSocket.OPEN) {
        ws.send(data);
      }
    });
  }
  
  // Tab click handlers
  tabMyFiles?.addEventListener('click', switchToFilesTab);
  tabMyComputer?.addEventListener('click', switchToComputerTab);
  tabConsole?.addEventListener('click', switchToConsoleTab);
  consoleTabAddBtn?.addEventListener('click', createConsoleTerminalTab);
  
  // Handle window resize for console terminals
  window.addEventListener('resize', () => {
    if (currentEditorTab === 'console') {
      const activeTab = consoleTabTerminalTabs.find(t => t.id === consoleTabActiveId);
      if (activeTab && activeTab.fitAddon) {
        activeTab.fitAddon.fit();
      }
    }
  });
  
  // Reset VNC state when modal is closed
  const originalCloseEditor = closeEditorBtn?.onclick;
  closeEditorBtn?.addEventListener('click', () => {
    // Don't disconnect VNC or console on close, just hide modal
    // This preserves the sessions
  });
</script>

<script>
  (function(){
    const sidebar = document.getElementById('sidebar');
    const btn = document.getElementById('sidebar-collapse-toggle');
    const icon = document.getElementById('sidebar-collapse-icon');
    const key = 'ginto_sidebar_collapsed';
    function applyCollapsed(collapsed){
      if(!sidebar) return;
      if(collapsed) sidebar.classList.add('collapsed'); else sidebar.classList.remove('collapsed');
      if(icon) icon.style.transform = collapsed ? 'rotate(180deg)' : 'rotate(0deg)';
    }
    try{
      const saved = localStorage.getItem(key);
      if(saved === '1' && window.innerWidth >= 1024) applyCollapsed(true);
    }catch(e){}
    btn?.addEventListener('click', function(e){
      e.preventDefault();
      const collapsed = !sidebar.classList.contains('collapsed');
      applyCollapsed(collapsed);
      try{ localStorage.setItem(key, collapsed ? '1' : '0'); }catch(e){}
      // Update aria-expanded
      try{ btn.setAttribute('aria-expanded', collapsed ? 'true' : 'false'); }catch(e){}
    });
    // Remove collapsed on small screens to show hamburger-only
    window.addEventListener('resize', function(){
      try{
        if(window.innerWidth < 1024 && sidebar.classList.contains('collapsed')) sidebar.classList.remove('collapsed');
        else {
          const saved = localStorage.getItem(key);
          if(saved === '1' && window.innerWidth >= 1024) sidebar.classList.add('collapsed');
        }
      }catch(e){}
    });
  })();
</script>
