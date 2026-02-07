<?php
/**
 * OpenWebUI Integration Scripts
 * Handles checking status, installing, and launching OpenWebUI in user's sandbox
 */
?>
<script>
(function() {
  // Check if user is admin (only admins can install/start/stop OpenWebUI)
  const isAdmin = <?php echo json_encode(!empty($_SESSION['is_admin']) || (isset($_SESSION['user_id']) && in_array($_SESSION['user']['role_id'] ?? 0, [1, 2]))); ?>;
  
  // Get CSRF token for POST requests - wait for auth promise if needed
  async function getCsrfToken() {
    // Wait for auth to be ready if promise exists
    if (window.GINTO_AUTH_PROMISE) {
      await window.GINTO_AUTH_PROMISE;
    }
    return window.GINTO_AUTH?.csrfToken || window.CSRF_TOKEN || '';
  }
  
  // Wait for a URL to be reachable (returns HTTP 200)
  async function waitForUrlReady(url, timeoutMs = 30000) {
    const startTime = Date.now();
    const checkInterval = 2000; // Check every 2 seconds
    
    while (Date.now() - startTime < timeoutMs) {
      try {
        // Use backend endpoint to check URL readiness (can detect 500 errors)
        const res = await fetch('/api/sandbox/check-url-ready?url=' + encodeURIComponent(url));
        const data = await res.json();
        
        if (data.success && data.ready) {
          console.log('[OWUI] URL is ready:', url, 'HTTP', data.http_code);
          return true;
        }
        
        console.log('[OWUI] URL not ready yet, HTTP code:', data.http_code || 'N/A');
        await new Promise(r => setTimeout(r, checkInterval));
      } catch (e) {
        // Network error - wait and retry
        console.log('[OWUI] Waiting for URL to be ready...', e.message);
        await new Promise(r => setTimeout(r, checkInterval));
      }
    }
    
    console.log('[OWUI] Timeout waiting for URL');
    return false;
  }
  
  const openWebuiLink = document.getElementById('open-webui-link');
  const openWebuiLabel = document.getElementById('open-webui-label');
  const openWebuiStatus = document.getElementById('open-webui-status');
  
  if (!openWebuiLink) return;
  
  let openWebuiInstalled = false;
  let openWebuiRunning = false;
  let sandboxExists = false;
  let isInstalling = false;
  let openWebuiUrl = null;
  let sandboxBackend = null; // 'lxd' or 'docker'
  let openWebuiEnabled = true; // Admin toggle for OpenWebUI installation
  
  // Check OpenWebUI status on page load
  async function checkOpenWebuiStatus() {
    try {
      const res = await fetch('/api/sandbox/openwebui/status');
      const data = await res.json();
      
      if (data.success) {
        sandboxExists = data.sandbox_exists;
        openWebuiInstalled = data.installed;
        openWebuiRunning = data.running;
        openWebuiUrl = data.url || null;
        sandboxBackend = data.backend || null;
        openWebuiEnabled = data.openwebui_enabled !== false; // Default to true if not specified
        updateOpenWebuiUI();
        if (typeof updateExposeButtonVisibility === 'function') {
          updateExposeButtonVisibility();
        }
      }
    } catch (e) {
      console.error('Failed to check OpenWebUI status:', e);
    }
  }
  
  function updateOpenWebuiUI() {
    if (isInstalling) {
      openWebuiLabel.textContent = 'Installing...';
      openWebuiStatus.classList.remove('hidden');
      openWebuiStatus.querySelector('span').className = 'w-2 h-2 rounded-full bg-yellow-400 inline-block animate-pulse';
      openWebuiStatus.querySelector('span').title = 'Installing OpenWebUI...';
      return;
    }
    
    // If OpenWebUI is disabled by admin and not already installed/running
    if (!openWebuiEnabled && !openWebuiInstalled) {
      openWebuiLabel.textContent = 'OpenWebUI';
      openWebuiStatus.classList.remove('hidden');
      openWebuiStatus.querySelector('span').className = 'w-2 h-2 rounded-full bg-gray-400 inline-block';
      openWebuiStatus.querySelector('span').title = 'Disabled by administrator';
      return;
    }
    
    if (!sandboxExists) {
      // Hide install button for non-admins
      if (!isAdmin) {
        openWebuiLabel.textContent = 'OpenWebUI';
        openWebuiStatus.classList.remove('hidden');
        openWebuiStatus.querySelector('span').className = 'w-2 h-2 rounded-full bg-gray-400 inline-block';
        openWebuiStatus.querySelector('span').title = 'Not available - Contact admin';
      } else {
        openWebuiLabel.textContent = 'Install OpenWebUI';
        openWebuiStatus.classList.add('hidden');
      }
    } else if (!openWebuiInstalled) {
      if (!isAdmin) {
        openWebuiLabel.textContent = 'OpenWebUI';
        openWebuiStatus.classList.remove('hidden');
        openWebuiStatus.querySelector('span').className = 'w-2 h-2 rounded-full bg-gray-400 inline-block';
        openWebuiStatus.querySelector('span').title = 'Not installed - Contact admin';
      } else {
        openWebuiLabel.textContent = 'Install OpenWebUI';
        openWebuiStatus.classList.remove('hidden');
        openWebuiStatus.querySelector('span').className = 'w-2 h-2 rounded-full bg-gray-400 inline-block';
        openWebuiStatus.querySelector('span').title = 'Not installed';
      }
    } else if (openWebuiRunning) {
      openWebuiLabel.textContent = 'OpenWebUI';
      openWebuiStatus.classList.remove('hidden');
      openWebuiStatus.querySelector('span').className = 'w-2 h-2 rounded-full bg-green-400 inline-block';
      openWebuiStatus.querySelector('span').title = 'Running - Click to open';
    } else {
      if (!isAdmin) {
        openWebuiLabel.textContent = 'OpenWebUI';
        openWebuiStatus.classList.remove('hidden');
        openWebuiStatus.querySelector('span').className = 'w-2 h-2 rounded-full bg-red-400 inline-block';
        openWebuiStatus.querySelector('span').title = 'Not running - Contact admin';
      } else {
        openWebuiLabel.textContent = 'Start OpenWebUI';
        openWebuiStatus.classList.remove('hidden');
        openWebuiStatus.querySelector('span').className = 'w-2 h-2 rounded-full bg-amber-400 inline-block';
        openWebuiStatus.querySelector('span').title = 'Installed but not running';
      }
    }
  }
  
  // Install OpenWebUI (called after sandbox is ready)
  async function installOpenWebUI() {
    isInstalling = true;
    updateOpenWebuiUI();
    
    // Get the docker install command from API
    try {
      const csrfToken = await getCsrfToken();
      const res = await fetch('/api/sandbox/openwebui/install', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken }
      });
      const data = await res.json();
      
      if (!data.success) {
        showToast('Failed to prepare install: ' + (data.error || 'Unknown error'), 'error');
        isInstalling = false;
        updateOpenWebuiUI();
        return;
      }
      
      // Open console and run the docker install command
      // Pass 'sandbox' as targetMode to connect to user's sandbox, not host
      if (typeof window.openConsoleWithCommand === 'function') {
        const cmd = data.command;
        window.openConsoleWithCommand(cmd, 'sandbox');
      } else {
        showToast('Console not available', 'error');
        isInstalling = false;
        updateOpenWebuiUI();
        return;
      }
    } catch (e) {
      showToast('Failed to prepare install: ' + e.message, 'error');
      isInstalling = false;
      updateOpenWebuiUI();
      return;
    }
    
    // Poll for completion and auto-navigate on success
    const pollInstall = setInterval(async () => {
      await checkOpenWebuiStatus();
      if (openWebuiRunning && openWebuiUrl) {
        clearInterval(pollInstall);
        isInstalling = false;
        updateOpenWebuiUI();
        
        // Register the OpenWebUI domain with Caddy (only for LXD backend - creates oi.ginto.ai proxy)
        if (sandboxBackend === 'lxd') {
          try {
            const csrfToken = await getCsrfToken();
            const domainRes = await fetch('/api/sandbox/openwebui/register-domain', {
              method: 'POST',
              headers: { 'X-CSRF-Token': csrfToken }
            });
            const domainData = await domainRes.json();
            if (domainData.success) {
              console.log('[OWUI] Domain registered:', domainData.domain);
            } else {
              console.warn('[OWUI] Domain registration failed:', domainData.error);
            }
          } catch (e) {
            console.warn('[OWUI] Domain registration error:', e.message);
          }
        } else {
          console.log('[OWUI] Docker backend - skipping domain registration, using direct IP');
        }
        
        // Minimize the console
        if (typeof window.minimizeConsole === 'function') {
          window.minimizeConsole();
        }
        
        // Open iframe modal immediately with loading state
        const owuiIcon = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/></svg>';
        if (typeof window.openIframeModal === 'function') {
          window.openIframeModal(openWebuiUrl, { 
            title: 'OpenWebUI', 
            icon: owuiIcon,
            waitForReady: true,
            loadingMessage: 'Starting OpenWebUI...'
          });
          
          // Poll until service is ready, then load the iframe
          const pollReady = async () => {
            const ready = await waitForUrlReady(openWebuiUrl, 5000); // Quick check
            if (ready) {
              if (typeof window.loadIframeUrl === 'function') {
                window.loadIframeUrl(openWebuiUrl);
              }
            } else {
              // Update message and retry
              if (typeof window.updateIframeLoadingMessage === 'function') {
                window.updateIframeLoadingMessage('Waiting for OpenWebUI to start...');
              }
              setTimeout(pollReady, 2000);
            }
          };
          pollReady();
        } else {
          // Fallback: open in new tab
          window.open(openWebuiUrl, '_blank');
        }
      }
    }, 5000); // Check every 5 seconds
    
    // Stop polling after 10 minutes
    setTimeout(() => {
      clearInterval(pollInstall);
      if (isInstalling) {
        isInstalling = false;
        updateOpenWebuiUI();
      }
    }, 10 * 60 * 1000);
  }
  
  // Expose installOpenWebUI globally so it can be called from sandbox wizard
  window.installOpenWebUI = installOpenWebUI;
  
  // Handle click on OpenWebUI link
  openWebuiLink.addEventListener('click', async (e) => {
    e.preventDefault();
    
    if (isInstalling) {
      showToast('Installation in progress - check the Console', 'info');
      // Open console to show progress
      if (typeof window.openConsoleWithCommand === 'function') {
        window.openConsoleWithCommand('');
      }
      return;
    }
    
    // If OpenWebUI is running, allow all users to open it (viewing only)
    if (openWebuiRunning) {
      openOpenWebUI();
      return;
    }
    
    // Check if OpenWebUI is disabled by admin
    if (!openWebuiEnabled && !openWebuiInstalled) {
      showToast('This feature has been disabled by the administrator.', 'error');
      return;
    }
    
    // All other actions require admin access
    if (!isAdmin) {
      showToast('Only administrators can install, start, or stop OpenWebUI. Contact your admin for access.', 'error');
      return;
    }
    
    if (!sandboxExists) {
      // Need to create sandbox first - show wizard with terms acceptance
      // Set a flag so after sandbox is created, we auto-install OpenWebUI
      window.pendingOpenWebuiInstall = true;
      console.log('[OWUI DEBUG] Set pendingOpenWebuiInstall = true');
      showToast('Please accept the terms to create a sandbox first to install OpenWebUI.', 'info');
      // Trigger the sandbox wizard (same as "My Files" does)
      if (typeof showSandboxWizard === 'function') {
        console.log('[OWUI DEBUG] Calling showSandboxWizard()');
        showSandboxWizard();
      } else {
        // Fallback: click the My Files button
        document.getElementById('open-my-files')?.click();
      }
      return;
    }
    
    if (!openWebuiInstalled) {
      // Install OpenWebUI via Console so user can see progress
      const confirmed = await showConfirmModal({
        title: 'Install OpenWebUI',
        message: 'Install OpenWebUI in your sandbox? This will open a Console to show installation progress.',
        confirmText: 'Install',
        confirmIcon: 'fa-download',
        type: 'info'
      });
      
      if (!confirmed) return;
      
      await installOpenWebUI();
      return;
    }
    
    if (!openWebuiRunning) {
      // Start OpenWebUI
      await startOpenWebUI();
      return;
    }
    
    // OpenWebUI is running - open it in new tab
    openOpenWebUI();
  });
  
  async function startOpenWebUI() {
    showToast('Starting OpenWebUI...', 'info');
    
    try {
      const csrfToken = await getCsrfToken();
      const res = await fetch('/api/sandbox/openwebui/start', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken }
      });
      const data = await res.json();
      
      if (data.success) {
        openWebuiRunning = true;
        updateOpenWebuiUI();
        showToast('OpenWebUI started!', 'success');
        
        // Wait a moment for it to fully start, then open
        setTimeout(() => {
          openOpenWebUI();
        }, 2000);
      } else {
        showToast('Failed to start: ' + (data.error || 'Unknown error'), 'error');
      }
    } catch (e) {
      showToast('Failed to start: ' + e.message, 'error');
    }
  }
  
  // Ensure OpenWebUI domain is registered with Caddy before opening (LXD only)
  async function ensureDomainRegistered() {
    // Only register domain for LXD backend
    if (sandboxBackend !== 'lxd') {
      console.log('[OWUI] Docker backend - skipping domain registration');
      return;
    }
    
    try {
      const csrfToken = await getCsrfToken();
      const res = await fetch('/api/sandbox/openwebui/register-domain', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken }
      });
      const data = await res.json();
      if (data.success) {
        console.log('[OWUI] Domain ensured:', data.domain);
        // Give Caddy a moment to reload
        await new Promise(r => setTimeout(r, 500));
      } else {
        console.warn('[OWUI] Domain registration check failed:', data.error);
      }
    } catch (e) {
      console.warn('[OWUI] Domain registration error:', e.message);
    }
  }
  
  async function openOpenWebUI() {
    const url = openWebuiUrl || (sandboxBackend === 'lxd' ? 'https://oi.ginto.ai/' : null);
    if (!url) {
      showToast('OpenWebUI URL not available. Is it running?', 'error');
      return;
    }
    
    // Ensure domain is registered with Caddy before opening (LXD only)
    await ensureDomainRegistered();
    
    // Open in iframe modal if available
    if (typeof window.openIframeModal === 'function') {
      const owuiIcon = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/></svg>';
      window.openIframeModal(url, { title: 'OpenWebUI', icon: owuiIcon });
    } else {
      // Fallback: open in new tab
      window.open(url, '_blank');
    }
  }
  
  // Listen for sandbox creation events to auto-install OpenWebUI
  window.addEventListener('message', async (ev) => {
    try {
      const d = ev && ev.data;
      if (!d) return;
      
      console.log('[OWUI DEBUG] Message received:', d.type, 'pendingOpenWebuiInstall:', window.pendingOpenWebuiInstall);
      
      // When sandbox is created and we have a pending OpenWebUI install
      if (d.type === 'sandbox_created' && window.pendingOpenWebuiInstall) {
        console.log('[OWUI DEBUG] Triggering installOpenWebUI!');
        window.pendingOpenWebuiInstall = false;
        sandboxExists = true;
        
        // Only admins can install OpenWebUI
        if (!isAdmin) {
          showToast('Only administrators can install OpenWebUI', 'error');
          return;
        }
        
        // Update GINTO_AUTH with new sandbox ID so Console can connect
        if (d.id && window.GINTO_AUTH) {
          if (!window.GINTO_AUTH.sandbox) window.GINTO_AUTH.sandbox = {};
          window.GINTO_AUTH.sandbox.id = d.id;
          console.log('[OWUI DEBUG] Updated GINTO_AUTH.sandbox.id to:', d.id);
        }
        
        // Wait a moment for sandbox to be ready
        await new Promise(r => setTimeout(r, 2000));
        
        showToast('Sandbox created! Installing OpenWebUI...', 'success');
        await installOpenWebUI();
      }
    } catch (e) {
      console.error('Error handling sandbox message:', e);
    }
  });
  
  // Check status on page load
  setTimeout(checkOpenWebuiStatus, 1000);
  
  // Re-check periodically (every 30 seconds)
  setInterval(checkOpenWebuiStatus, 30000);
  
  // ============================================
  // Ginto Tunnel - Expose to Web (Docker mode)
  // ============================================
  
  let tunnelActive = false;
  let tunnelUrl = null;
  let tunnelExpiry = null;
  let tunnelSubdomain = null;
  let countdownInterval = null;
  
  // Persistence key
  const TUNNEL_STORAGE_KEY = 'ginto_tunnel_state';
  
  // Save tunnel state to localStorage
  function saveTunnelState() {
    if (tunnelActive && tunnelSubdomain) {
      localStorage.setItem(TUNNEL_STORAGE_KEY, JSON.stringify({
        active: tunnelActive,
        url: tunnelUrl,
        subdomain: tunnelSubdomain,
        expiry: tunnelExpiry
      }));
    } else {
      localStorage.removeItem(TUNNEL_STORAGE_KEY);
    }
  }
  
  // Load tunnel state from localStorage
  function loadTunnelState() {
    try {
      const stored = localStorage.getItem(TUNNEL_STORAGE_KEY);
      if (!stored) return;
      
      const state = JSON.parse(stored);
      if (state.active && state.subdomain && state.expiry) {
        // Check if not expired
        if (state.expiry > Date.now()) {
          tunnelActive = true;
          tunnelUrl = state.url;
          tunnelSubdomain = state.subdomain;
          tunnelExpiry = state.expiry;
          
          // Restart countdown interval
          if (countdownInterval) clearInterval(countdownInterval);
          countdownInterval = setInterval(updateCountdown, 1000);
          
          // Restart server sync
          if (window.tunnelSyncInterval) clearInterval(window.tunnelSyncInterval);
          window.tunnelSyncInterval = setInterval(() => syncTunnelTime(tunnelSubdomain), 30000);
          
          // Verify with server
          syncTunnelTime(tunnelSubdomain);
          
          console.log('[TUNNEL] Restored tunnel state:', tunnelSubdomain);
        } else {
          // Expired, clear
          localStorage.removeItem(TUNNEL_STORAGE_KEY);
        }
      }
    } catch (e) {
      console.error('Failed to load tunnel state:', e);
      localStorage.removeItem(TUNNEL_STORAGE_KEY);
    }
  }
  
  // Load persisted state on page load
  loadTunnelState();
  
  // Show/hide the "Expose" button in iframe modal toolbar
  function updateExposeButtonVisibility() {
    const exposeContainer = document.getElementById('iframe-modal-expose-container');
    const exposeBtn = document.getElementById('iframe-modal-expose');
    const disconnectBtn = document.getElementById('iframe-modal-disconnect');
    const countdownEl = document.getElementById('iframe-expose-countdown');
    const urlEl = document.getElementById('iframe-expose-url');
    const copyBtn = document.getElementById('iframe-expose-copy');
    
    if (!exposeContainer || !exposeBtn) return;
    
    // Show only for Docker backend when OpenWebUI iframe is open
    const modalTitle = document.getElementById('iframe-modal-title')?.textContent || '';
    const isOpenWebUI = modalTitle.toLowerCase().includes('openwebui');
    
    if (sandboxBackend === 'docker' && isOpenWebUI) {
      exposeContainer.classList.remove('hidden');
      exposeBtn.onclick = showExposeModal;
      
      // Update button state if tunnel is active
      if (tunnelActive) {
        exposeBtn.innerHTML = '<svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> Exposed';
        exposeBtn.classList.add('text-green-400');
        exposeBtn.classList.remove('text-blue-400');
        exposeBtn.title = tunnelUrl || 'Tunnel active';
        
        // Show exposed URL link and copy button
        if (urlEl && tunnelUrl) {
          urlEl.href = tunnelUrl;
          urlEl.textContent = tunnelUrl.replace(/\/$/, '');
          urlEl.classList.remove('hidden');
        }
        if (copyBtn && tunnelUrl) {
          copyBtn.classList.remove('hidden');
          copyBtn.onclick = () => {
            navigator.clipboard.writeText(tunnelUrl.replace(/\/$/, '')).then(() => {
              copyBtn.innerHTML = '<svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
              setTimeout(() => {
                copyBtn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>';
              }, 1500);
            });
          };
        }
        
        // Show countdown and disconnect button
        if (countdownEl) {
          countdownEl.classList.remove('hidden');
          updateHeaderCountdown();
        }
        if (disconnectBtn) {
          disconnectBtn.classList.remove('hidden');
          disconnectBtn.onclick = disconnectTunnel;
        }
      } else {
        exposeBtn.innerHTML = '<svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> Expose to Web';
        exposeBtn.classList.remove('text-green-400');
        exposeBtn.classList.add('text-blue-400');
        exposeBtn.title = 'Share on the internet';
        
        // Hide URL, copy button, countdown and disconnect
        if (urlEl) urlEl.classList.add('hidden');
        if (copyBtn) copyBtn.classList.add('hidden');
        if (countdownEl) countdownEl.classList.add('hidden');
        if (disconnectBtn) disconnectBtn.classList.add('hidden');
      }
    } else {
      exposeContainer.classList.add('hidden');
    }
  }
  
  // Update the header countdown display
  function updateHeaderCountdown() {
    const countdownEl = document.getElementById('iframe-expose-countdown');
    if (!countdownEl || !tunnelExpiry) return;
    
    const remaining = Math.max(0, tunnelExpiry - Date.now());
    const mins = Math.floor(remaining / 60000);
    const secs = Math.floor((remaining % 60000) / 1000);
    
    if (remaining <= 0) {
      countdownEl.textContent = '0:00';
      countdownEl.className = 'text-xs text-red-400 font-mono animate-pulse';
    } else if (remaining <= 60000) {
      countdownEl.textContent = `0:${secs.toString().padStart(2, '0')}`;
      countdownEl.className = 'text-xs text-red-400 font-mono animate-pulse';
    } else if (remaining <= 120000) {
      countdownEl.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
      countdownEl.className = 'text-xs text-orange-400 font-mono';
    } else {
      countdownEl.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
      countdownEl.className = 'text-xs text-yellow-400 font-mono';
    }
  }
  
  // Listen for iframe modal shown event (works for both new tabs and restored tabs)
  window.addEventListener('iframeModalShown', () => {
    setTimeout(updateExposeButtonVisibility, 50);
  });
  
  // Also check on script load in case modal was restored from persistence before this script loaded
  setTimeout(() => {
    const modal = document.getElementById('iframe-modal');
    if (modal && !modal.classList.contains('hidden')) {
      updateExposeButtonVisibility();
    }
  }, 100);
  
  // Show expose modal
  function showExposeModal() {
    // Remove existing modal
    document.getElementById('expose-modal')?.remove();
    
    // If tunnel is already active, show status modal instead
    if (tunnelActive && tunnelUrl) {
      showTunnelStatusModal();
      return;
    }
    
    const modal = document.createElement('div');
    modal.id = 'expose-modal';
    modal.className = 'fixed inset-0 bg-black/70 z-[100] flex items-center justify-center';
    modal.innerHTML = `
      <div class="bg-gray-800 rounded-xl p-6 max-w-md w-full mx-4 shadow-2xl">
        <h3 class="text-xl font-bold text-white mb-4">🌐 Expose to Web</h3>
        <p class="text-gray-300 text-sm mb-4">
          Share your local OpenWebUI with anyone on the internet.
          Choose a subdomain for your public URL.
        </p>
        
        <div class="mb-4">
          <label class="block text-gray-400 text-sm mb-2">Your subdomain</label>
          <div class="flex items-center gap-2">
            <input type="text" id="expose-subdomain" 
              class="flex-1 px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-purple-500" 
              placeholder="my-openwebui"
              pattern="[a-z0-9][a-z0-9-]{1,30}[a-z0-9]"
              maxlength="32">
            <span class="text-gray-400">.ginto.ai</span>
          </div>
          <p class="text-gray-500 text-xs mt-1">3-32 characters, lowercase letters, numbers, and hyphens</p>
        </div>
        
        <div id="expose-result" class="hidden mb-4 p-3 rounded-lg"></div>
        <div class="flex gap-2">
          <button id="expose-cancel" class="flex-1 px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors">Cancel</button>
          <button id="expose-submit" class="flex-1 px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white rounded-lg transition-colors">Expose</button>
        </div>
        
        <p class="text-gray-500 text-xs mt-4 text-center">
          ⏱ Free tunnels expire in 10 minutes.<br>
          <a href="/register" class="text-purple-400 hover:text-purple-300">Register</a> for non-expiring tunnels.
        </p>
      </div>
    `;
    
    document.body.appendChild(modal);
    
    // Generate random subdomain like git commit hash (7 hex chars)
    const hexChars = '0123456789abcdef';
    let subdomain = '';
    for (let i = 0; i < 7; i++) {
      subdomain += hexChars[Math.floor(Math.random() * 16)];
    }
    document.getElementById('expose-subdomain').value = subdomain;
    document.getElementById('expose-subdomain').focus();
    document.getElementById('expose-subdomain').select();
    
    // Event handlers
    document.getElementById('expose-cancel').onclick = () => modal.remove();
    document.getElementById('expose-submit').onclick = startTunnel;
    document.getElementById('expose-subdomain').onkeydown = (e) => { if (e.key === 'Enter') startTunnel(); };
    
    // Close on backdrop click
    modal.onclick = (e) => {
      if (e.target === modal) modal.remove();
    };
  }
  
  // Show tunnel status modal (when tunnel is active)
  function showTunnelStatusModal() {
    document.getElementById('expose-modal')?.remove();
    
    const modal = document.createElement('div');
    modal.id = 'expose-modal';
    modal.className = 'fixed inset-0 bg-black/70 z-[100] flex items-center justify-center';
    modal.innerHTML = `
      <div class="bg-gray-800 rounded-xl p-6 max-w-md w-full mx-4 shadow-2xl">
        <h3 class="text-xl font-bold text-white mb-4">🌐 Expose to Web</h3>
        <p class="text-gray-300 text-sm mb-4">
          Share your local OpenWebUI with anyone on the internet.
          Choose a subdomain for your public URL.
        </p>
        
        <div class="mb-4">
          <label class="block text-gray-400 text-sm mb-2">Your subdomain</label>
          <div class="flex items-center gap-2">
            <input type="text" id="expose-subdomain" 
              class="flex-1 px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-purple-500" 
              value="${tunnelSubdomain || ''}"
              readonly>
            <span class="text-gray-400">.ginto.ai</span>
          </div>
          <p class="text-gray-500 text-xs mt-1">3-32 characters, lowercase letters, and hyphens</p>
        </div>
        
        <div class="mb-4 p-3 rounded-lg bg-gray-700">
          <div class="text-center">
            <p class="text-green-400 font-bold mb-2">✓ Tunnel Active!</p>
            <p class="text-white text-sm mb-2">Your public URL:</p>
            <a href="${tunnelUrl}" target="_blank" class="text-blue-400 hover:text-blue-300 font-mono text-lg break-all">${tunnelUrl}</a>
            <p id="tunnel-countdown" class="text-yellow-400 text-xs mt-3">⏱ Expires in 10 minutes</p>
          </div>
        </div>
        
        <div class="flex gap-2">
          <button id="expose-cancel" class="flex-1 px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors">Cancel</button>
          <button id="expose-disconnect" class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg transition-colors">Connected!</button>
        </div>
        
        <p class="text-gray-500 text-xs mt-4 text-center">
          ⏱ Free tunnels expire in 10 minutes.<br>
          <a href="/register" class="text-purple-400 hover:text-purple-300">Register</a> for non-expiring tunnels.
        </p>
      </div>
    `;
    
    document.body.appendChild(modal);
    
    // Update countdown
    updateCountdown();
    
    // Event handlers
    document.getElementById('expose-cancel').onclick = () => modal.remove();
    document.getElementById('expose-disconnect').onclick = disconnectTunnel;
    // Close on backdrop click
    modal.onclick = (e) => { if (e.target === modal) modal.remove(); };
  }
  
  // Update countdown timer (both modal and header)
  function updateCountdown() {
    // Update header countdown
    updateHeaderCountdown();
    
    const countdownEl = document.getElementById('tunnel-countdown');
    if (!countdownEl || !tunnelExpiry) return;
    
    const remaining = Math.max(0, tunnelExpiry - Date.now());
    const mins = Math.floor(remaining / 60000);
    const secs = Math.floor((remaining % 60000) / 1000);
    
    if (remaining <= 0) {
      countdownEl.innerHTML = '⚠️ Tunnel expired - disconnecting...';
      countdownEl.className = 'text-red-400 text-xs mt-3';
      // Actually disconnect the tunnel when it expires
      disconnectTunnel();
      return;
    }
    
    if (remaining <= 60000) {
      countdownEl.innerHTML = `⚠️ Expires in ${secs}s`;
      countdownEl.className = 'text-red-400 text-xs mt-3 animate-pulse';
    } else if (remaining <= 120000) {
      countdownEl.innerHTML = `⏱ Expires in ${mins}m ${secs}s`;
      countdownEl.className = 'text-orange-400 text-xs mt-3';
    } else {
      countdownEl.innerHTML = `⏱ Expires in ${mins} minutes`;
      countdownEl.className = 'text-yellow-400 text-xs mt-3';
    }
  }
  
  // Disconnect tunnel
  async function disconnectTunnel() {
    // Prevent multiple calls
    if (!tunnelActive && !tunnelSubdomain) return;
    
    const subdomain = tunnelSubdomain; // Save before clearing
    
    const disconnectBtn = document.getElementById('expose-disconnect');
    if (disconnectBtn) {
      disconnectBtn.disabled = true;
      disconnectBtn.textContent = 'Disconnecting...';
    }
    
    try {
      const csrfToken = await getCsrfToken();
      await fetch('/api/tunnel/disconnect', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ subdomain })
      });
    } catch (e) {
      console.error('Error disconnecting tunnel:', e);
    }
    
    // Clear local state
    if (countdownInterval) {
      clearInterval(countdownInterval);
      countdownInterval = null;
    }
    if (window.tunnelSyncInterval) {
      clearInterval(window.tunnelSyncInterval);
      window.tunnelSyncInterval = null;
    }
    tunnelActive = false;
    tunnelUrl = null;
    tunnelExpiry = null;
    tunnelSubdomain = null;
    
    // Clear persistence
    saveTunnelState();
    
    updateExposeButtonVisibility();
    document.getElementById('expose-modal')?.remove();
  }
  
  // Start tunnel
  async function startTunnel() {
    const subdomain = document.getElementById('expose-subdomain')?.value?.toLowerCase()?.trim();
    const resultDiv = document.getElementById('expose-result');
    const submitBtn = document.getElementById('expose-submit');
    
    if (!subdomain) {
      showExposeResult('Please enter a subdomain', 'error');
      return;
    }
    
    // Validate subdomain format
    if (!/^[a-z0-9][a-z0-9-]{1,30}[a-z0-9]$/.test(subdomain)) {
      showExposeResult('Invalid subdomain format. Use 3-32 lowercase letters, numbers, and hyphens.', 'error');
      return;
    }
    
    submitBtn.disabled = true;
    submitBtn.textContent = 'Connecting...';
    
    try {
      const csrfToken = await getCsrfToken();
      const res = await fetch('/api/tunnel/request', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ subdomain, port: 8088 })
      });
      
      const data = await res.json();
      
      if (data.success) {
        tunnelActive = true;
        tunnelUrl = data.url;
        tunnelSubdomain = subdomain;
        // Use server's expires_at timestamp for accuracy
        tunnelExpiry = data.expires_at ? (data.expires_at * 1000) : (Date.now() + (data.expires_in || 600) * 1000);
        
        // Save to persistence
        saveTunnelState();
        
        showExposeResult(`
          <div class="text-center">
            <p class="text-green-400 font-bold mb-2">✓ Tunnel Active!</p>
            <p class="text-white text-sm mb-2">Your public URL:</p>
            <a href="${data.url}" target="_blank" class="text-blue-400 hover:text-blue-300 font-mono text-lg break-all">${data.url}</a>
            <p id="tunnel-countdown" class="text-yellow-400 text-xs mt-3">⏱ Expires in ${Math.floor((data.expires_in || 600) / 60)} minutes</p>
          </div>
        `, 'success');
        
        submitBtn.textContent = 'Connected!';
        submitBtn.className = submitBtn.className.replace('bg-purple-600', 'bg-green-600');
        // Start countdown interval - also sync with server every 30 seconds
        if (countdownInterval) clearInterval(countdownInterval);
        countdownInterval = setInterval(updateCountdown, 1000);
        
        // Start server sync interval
        if (window.tunnelSyncInterval) clearInterval(window.tunnelSyncInterval);
        window.tunnelSyncInterval = setInterval(() => syncTunnelTime(subdomain), 30000);
        
        updateExposeButtonVisibility();
      } else {
        showExposeResult(data.error || 'Failed to create tunnel', 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Expose';
      }
    } catch (e) {
      showExposeResult('Connection error: ' + e.message, 'error');
      submitBtn.disabled = false;
      submitBtn.textContent = 'Expose';
    }
  }
  
  // Sync tunnel time with server
  async function syncTunnelTime(subdomain) {
    if (!tunnelActive || !subdomain) return;
    
    try {
      const res = await fetch(`/api/tunnel/time?subdomain=${encodeURIComponent(subdomain)}`);
      const data = await res.json();
      
      if (!data.success || !data.active) {
        // Server says tunnel is expired/inactive
        console.log('Server reports tunnel expired, disconnecting...');
        tunnelActive = false;
        tunnelUrl = null;
        tunnelExpiry = 0;
        tunnelSubdomain = null;
        if (countdownInterval) clearInterval(countdownInterval);
        if (window.tunnelSyncInterval) clearInterval(window.tunnelSyncInterval);
        
        // Clear persistence
        saveTunnelState();
        
        updateExposeButtonVisibility();
        document.getElementById('expose-modal')?.remove();
        return;
      }
      
      // Update expiry from server
      if (data.expires_at) {
        tunnelExpiry = data.expires_at * 1000;
        saveTunnelState();
      }
    } catch (e) {
      console.error('Failed to sync tunnel time:', e);
    }
  }
  
  function showExposeResult(html, type) {
    const resultDiv = document.getElementById('expose-result');
    if (!resultDiv) return;
    
    resultDiv.innerHTML = html;
    resultDiv.className = 'mb-4 p-3 rounded-lg ' + (type === 'error' ? 'bg-red-900/50 text-red-300' : 'bg-gray-700');
    resultDiv.classList.remove('hidden');
  }
  
  // No longer needed - button is now in iframe modal toolbar
})();
</script>