<?php
/**
 * OpenWebUI Integration Scripts
 * Handles checking status, installing, and launching OpenWebUI in user's sandbox
 */
?>
<script>
(function() {
  // Get CSRF token for POST requests - wait for auth promise if needed
  async function getCsrfToken() {
    // Wait for auth to be ready if promise exists
    if (window.GINTO_AUTH_PROMISE) {
      await window.GINTO_AUTH_PROMISE;
    }
    return window.GINTO_AUTH?.csrfToken || window.CSRF_TOKEN || '';
  }
  
  // Wait for a URL to be reachable (port listening)
  async function waitForUrlReady(url, timeoutMs = 30000) {
    const startTime = Date.now();
    const checkInterval = 2000; // Check every 2 seconds
    
    while (Date.now() - startTime < timeoutMs) {
      try {
        // Use fetch with mode: 'no-cors' to check if server responds
        // This won't give us the response, but will tell us if port is open
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 3000);
        
        await fetch(url, { 
          method: 'HEAD',
          mode: 'no-cors',
          signal: controller.signal
        });
        clearTimeout(timeoutId);
        
        // If we get here, the server responded
        console.log('[OWUI] URL is ready:', url);
        return true;
      } catch (e) {
        // Connection refused or timeout - wait and retry
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
        updateOpenWebuiUI();
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
    
    if (!sandboxExists) {
      openWebuiLabel.textContent = 'Install OpenWebUI';
      openWebuiStatus.classList.add('hidden');
    } else if (!openWebuiInstalled) {
      openWebuiLabel.textContent = 'Install OpenWebUI';
      openWebuiStatus.classList.remove('hidden');
      openWebuiStatus.querySelector('span').className = 'w-2 h-2 rounded-full bg-gray-400 inline-block';
      openWebuiStatus.querySelector('span').title = 'Not installed';
    } else if (openWebuiRunning) {
      openWebuiLabel.textContent = 'OpenWebUI';
      openWebuiStatus.classList.remove('hidden');
      openWebuiStatus.querySelector('span').className = 'w-2 h-2 rounded-full bg-green-400 inline-block';
      openWebuiStatus.querySelector('span').title = 'Running - Click to open';
    } else {
      openWebuiLabel.textContent = 'Start OpenWebUI';
      openWebuiStatus.classList.remove('hidden');
      openWebuiStatus.querySelector('span').className = 'w-2 h-2 rounded-full bg-amber-400 inline-block';
      openWebuiStatus.querySelector('span').title = 'Installed but not running';
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
        showToast('OpenWebUI installed! Waiting for service to start...', 'success');
        
        // Minimize the console
        if (typeof window.minimizeConsole === 'function') {
          window.minimizeConsole();
        }
        
        // Wait for the port to actually be ready before opening iframe
        await waitForUrlReady(openWebuiUrl, 60000); // Wait up to 60 seconds
        
        // Open OpenWebUI in iframe modal
        if (typeof window.openIframeModal === 'function') {
          const owuiIcon = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/></svg>';
          window.openIframeModal(openWebuiUrl, { title: 'OpenWebUI', icon: owuiIcon });
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
    
    if (!sandboxExists) {
      // Need to create sandbox first - show wizard with terms acceptance
      // Set a flag so after sandbox is created, we auto-install OpenWebUI
      window.pendingOpenWebuiInstall = true;
      console.log('[OWUI DEBUG] Set pendingOpenWebuiInstall = true');
      showToast('Please accept the terms to create a sandbox first', 'info');
      
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
  
  function openOpenWebUI() {
    const url = openWebuiUrl || ('http://' + window.location.hostname + ':8088/');
    
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
})();
</script>
