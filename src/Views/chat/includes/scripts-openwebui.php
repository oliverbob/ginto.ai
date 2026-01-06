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
  
  // Check OpenWebUI status on page load
  async function checkOpenWebuiStatus() {
    try {
      const res = await fetch('/api/sandbox/openwebui/status');
      const data = await res.json();
      
      if (data.success) {
        sandboxExists = data.sandbox_exists;
        openWebuiInstalled = data.installed;
        openWebuiRunning = data.running;
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
      openWebuiStatus.querySelector('span').title = 'Running';
    } else {
      openWebuiLabel.textContent = 'Start OpenWebUI';
      openWebuiStatus.classList.remove('hidden');
      openWebuiStatus.querySelector('span').className = 'w-2 h-2 rounded-full bg-amber-400 inline-block';
      openWebuiStatus.querySelector('span').title = 'Installed but not running';
    }
  }
  
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
      // Show sandbox wizard to create sandbox first
      showToast('Please create a sandbox first using "My Files"', 'info');
      document.getElementById('open-my-files')?.click();
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
      
      if (!confirmed) {
        return;
      }
      
      isInstalling = true;
      updateOpenWebuiUI();
      
      // Get the pip install command from API
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
        
        // Open console and run the pip install command
        // Pass 'sandbox' as targetMode to connect to user's sandbox, not host
        if (typeof window.openConsoleWithCommand === 'function') {
          const cmd = data.command || 'pip install open-webui && open-webui serve';
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
      
      // Poll for completion
      const pollInstall = setInterval(async () => {
        await checkOpenWebuiStatus();
        if (openWebuiInstalled) {
          clearInterval(pollInstall);
          isInstalling = false;
          updateOpenWebuiUI();
          showToast('OpenWebUI installed! Click to open.', 'success');
        }
      }, 10000); // Check every 10 seconds
      
      // Stop polling after 15 minutes
      setTimeout(() => {
        clearInterval(pollInstall);
        isInstalling = false;
        updateOpenWebuiUI();
      }, 15 * 60 * 1000);
      
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
    // Open in user's sandbox via clients proxy
    // The sandbox Caddy is configured to forward port 80 to 3000
    fetch('/api/sandbox/status')
      .then(r => r.json())
      .then(data => {
        if (data.sandbox_id) {
          window.open('/clients/' + data.sandbox_id + '/', '_blank');
        } else {
          showToast('Could not determine sandbox URL', 'error');
        }
      })
      .catch(e => {
        showToast('Failed to get sandbox info: ' + e.message, 'error');
      });
  }
  
  // Check status on page load
  setTimeout(checkOpenWebuiStatus, 1000);
  
  // Re-check periodically (every 30 seconds)
  setInterval(checkOpenWebuiStatus, 30000);
})();
</script>
