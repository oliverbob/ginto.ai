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
        showToast('OpenWebUI installed! Opening...', 'success');
        // Auto-navigate to OpenWebUI
        setTimeout(() => {
          window.open(openWebuiUrl, '_blank');
        }, 1000);
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
    // Use the URL from status check (http://server:8088/)
    if (openWebuiUrl) {
      window.open(openWebuiUrl, '_blank');
    } else {
      // Fallback: construct URL from current host
      const host = window.location.hostname;
      window.open('http://' + host + ':8088/', '_blank');
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
