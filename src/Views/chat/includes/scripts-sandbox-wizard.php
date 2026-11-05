<?php
/**
 * Sandbox Installation Wizard Functions
 */
?>
<script>
  // ========================================
  // Sandbox Installation Wizard Functions
  // ========================================
  // Use window scope so sandbox_destroyed handler can clear it
  window.installedSandboxId = null;
  window.selectedSandboxType = 'docker'; // Default to docker
  
  function showSandboxWizard() {
    const modal = document.getElementById('sandbox-wizard-modal');
    if (modal) {
      modal.classList.remove('hidden');
      showWizardStep(1);
      // Initialize sandbox type selection handlers
      initSandboxTypeSelection();
    }
  }
  
  function closeSandboxWizard() {
    const modal = document.getElementById('sandbox-wizard-modal');
    if (modal) {
      modal.classList.add('hidden');
    }
  }
  
  // Initialize sandbox type radio button handlers
  function initSandboxTypeSelection() {
    const radios = document.querySelectorAll('input[name="sandbox_type"]');
    const enterpriseNotice = document.getElementById('lxc-enterprise-notice');
    const continueBtn = document.getElementById('wizard-continue-btn');
    
    radios.forEach(radio => {
      radio.addEventListener('change', function() {
        window.selectedSandboxType = this.value;
        
        // Update visual selection
        document.querySelectorAll('.sandbox-type-option').forEach(opt => {
          opt.classList.remove('border-violet-500', 'bg-violet-50', 'dark:bg-violet-900/20');
          opt.classList.add('border-gray-200', 'dark:border-gray-700');
        });
        this.closest('.sandbox-type-option').classList.remove('border-gray-200', 'dark:border-gray-700');
        this.closest('.sandbox-type-option').classList.add('border-violet-500', 'bg-violet-50', 'dark:bg-violet-900/20');
        
        // Show/hide enterprise notice
        if (enterpriseNotice) {
          if (this.value === 'lxc') {
            enterpriseNotice.classList.remove('hidden');
          } else {
            enterpriseNotice.classList.add('hidden');
          }
        }
      });
    });
  }
  
  // Continue button handler - checks sandbox type
  function continueSandboxWizard() {
    if (window.selectedSandboxType === 'lxc') {
      // LXC selected - show enterprise required message
      if (typeof showToast === 'function') {
        showToast('LXC sandboxes require Enterprise subscription. Please select Docker or upgrade your plan.', 'warning');
      } else {
        alert('LXC sandboxes require Enterprise subscription. Please select Docker or upgrade your plan.');
      }
      return;
    }
    // Docker selected - proceed to terms (step 3)
    showWizardStep(3);
  }
  
  // Expose sandbox wizard functions globally for chat.js tool handler
  window.showSandboxWizard = showSandboxWizard;
  window.closeSandboxWizard = closeSandboxWizard;
  window.continueSandboxWizard = continueSandboxWizard;
  
  function showWizardStep(step) {
    // Hide all steps
    document.querySelectorAll('.wizard-step').forEach(el => el.classList.add('hidden'));
    // Show target step
    const target = document.getElementById('wizard-step-' + step);
    if (target) target.classList.remove('hidden');
    
    // Reset install button state if going to step 3 (terms)
    if (step === 3) {
      const checkbox = document.getElementById('accept-sandbox-terms');
      const btn = document.getElementById('wizard-install-btn');
      if (checkbox && btn) {
        btn.disabled = !checkbox.checked;
      }
    }
  }
  
  // Enable install button when terms are accepted
  document.getElementById('accept-sandbox-terms')?.addEventListener('change', function() {
    const btn = document.getElementById('wizard-install-btn');
    if (btn) btn.disabled = !this.checked;
  });
  
  // Helper to refresh CSRF token (for visitors whose session was reset)
  async function refreshCsrfToken() {
    try {
      const res = await fetch('/dev/csrf', { credentials: 'same-origin' });
      const data = await res.json();
      if (data.success && data.csrf_token) {
        window.GINTO_AUTH.csrfToken = data.csrf_token;
        console.log('CSRF token refreshed');
        return true;
      }
    } catch (e) {
      console.error('Failed to refresh CSRF token:', e);
    }
    return false;
  }
  
  async function installSandbox() {
    const checkbox = document.getElementById('accept-sandbox-terms');
    if (!checkbox || !checkbox.checked) {
      alert('Please accept the terms and conditions to continue.');
      return;
    }
    
    // Show installing step (step 4)
    showWizardStep(4);
    
    // Update progress with animation
    const progressBar = document.getElementById('install-progress-bar');
    const statusText = document.getElementById('install-status-text');
    
    const updateInstallStep = (stepNum, status) => {
      const step = document.getElementById('install-step-' + stepNum);
      if (!step) return;
      const icon = step.querySelector('.install-icon');
      if (status === 'active') {
        step.classList.add('bg-violet-50', 'dark:bg-violet-900/20');
        step.classList.remove('bg-gray-50', 'dark:bg-gray-800');
        icon.innerHTML = '<svg class="w-4 h-4 text-violet-600 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
        icon.classList.add('bg-violet-100', 'dark:bg-violet-800');
        icon.classList.remove('bg-gray-200', 'dark:bg-gray-700');
      } else if (status === 'done') {
        step.classList.add('bg-emerald-50', 'dark:bg-emerald-900/20');
        step.classList.remove('bg-violet-50', 'dark:bg-violet-900/20', 'bg-gray-50', 'dark:bg-gray-800');
        icon.innerHTML = '<svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
        icon.classList.add('bg-emerald-100', 'dark:bg-emerald-800');
        icon.classList.remove('bg-violet-100', 'dark:bg-violet-800', 'bg-gray-200', 'dark:bg-gray-700');
      }
    };
    
    // Helper function to make the install request
    const doInstallRequest = async () => {
      const body = new URLSearchParams();
      body.append('csrf_token', window.GINTO_AUTH?.csrfToken || '');
      body.append('accept_terms', '1');
      // Pass sandbox type - force docker on live, allow selection on local
      body.append('sandbox_type', window.selectedSandboxType || 'docker');
      
      const res = await fetch('/api/sandbox/install', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      });
      
      const data = await res.json().catch(() => null);
      return { res, data };
    };
    
    try {
      // Animate through steps
      updateInstallStep(1, 'active');
      progressBar.style.width = '10%';
      statusText.textContent = 'Creating sandbox directory...';
      await new Promise(r => setTimeout(r, 500));
      
      updateInstallStep(1, 'done');
      updateInstallStep(2, 'active');
      progressBar.style.width = '30%';
      statusText.textContent = 'Launching container...';
      
      // Make API call to install sandbox
      let { res, data } = await doInstallRequest();
      
      // If CSRF token is invalid, try refreshing it and retry once
      if (res.status === 403 && data?.error?.toLowerCase().includes('csrf')) {
        console.log('CSRF token invalid, refreshing and retrying...');
        statusText.textContent = 'Refreshing session...';
        const refreshed = await refreshCsrfToken();
        if (refreshed) {
          statusText.textContent = 'Launching container...';
          ({ res, data } = await doInstallRequest());
        }
      }
      
      // Determine the sandbox backend from response or global config
      const backend = data?.backend || window.SANDBOX_BACKEND || 'lxd';
      
      // Check if Docker sandbox backend has issues (check BEFORE LXC logic)
      if (backend === 'docker') {
        if (data?.install_required || data?.error_code?.startsWith('docker_') || !data?.success) {
          // Show Docker-specific error
          showWizardStep('error');
          const errorMsg = document.getElementById('wizard-error-message');
          if (errorMsg) {
            let message = data?.error || 'Docker sandbox error';
            if (data?.error_code === 'docker_not_running') {
              message = 'Docker daemon is not running. Please start Docker and try again.';
            } else if (data?.error_code === 'docker_permission') {
              message = 'Docker permission denied. Add user to docker group: sudo usermod -aG docker $USER';
            } else if (data?.error_code === 'docker_not_installed') {
              message = 'Docker is not installed. Install Docker: curl -fsSL https://get.docker.com | sh';
            } else if (data?.error_code === 'no_backend' || data?.install_required) {
              message = 'Docker sandbox image not found. The admin needs to build the sandbox image: docker compose build sandbox-image';
            }
            errorMsg.textContent = message;
          }
          return;
        }
      }
      
      // Check if LXC installation is required (only for LXD backend)
      if (backend === 'lxd' && (data?.install_required || data?.error_code?.startsWith('lxc_') || data?.error_code === 'base_image_missing' || data?.error_code === 'base_container_missing' || data?.error_code === 'lxd_not_initialized')) {
        // Close the wizard and open web terminal with auto-install command (admin only)
        closeSandboxWizard();
        
        // Always start a fresh terminal session for install
        if (typeof window.openConsoleWithCommand === 'function') {
          const installPath = '<?= addslashes(dirname(__DIR__, 4)) ?>';
          
          // Generate a unique session ID for this install
          const sessionId = 'install-' + Date.now() + '-' + Math.random().toString(36).substr(2, 6);
          
          // Clear any old install session from localStorage
          localStorage.removeItem('ginto-install-session');
          
          // Note: ginto.sh uses sudo internally for lxc commands, so we don't need sudo here
          // Use script command to capture output without subshell issues from pipe
          // Use stty -echo to suppress the command line echo, show a clean header
          const installCmd = `stty -echo; clear; echo "╔════════════════════════════════════════════════════════════╗"; echo "║          Ginto Sandbox - LXD Installation                  ║"; echo "╚════════════════════════════════════════════════════════════╝"; echo ""; stty echo; script -q -c "bash ${installPath}/bin/ginto.sh --force install" ${installPath}/install.log; echo ""; echo "=== Installation complete ==="; exec bash`;
          
          window.openConsoleWithCommand(installCmd, null, {
            sessionId: sessionId,
            tabLabel: 'LXD Install'
          });
          
          // Start polling for install completion in background
          startInstallStatusPollingGlobal();
        }
        return;
      }
      
      if (!res.ok || !data || !data.success) {
        throw new Error(data?.error || 'Installation failed');
      }
      
      // Stop any lingering install status polling
      if (globalInstallPollInterval) {
        clearInterval(globalInstallPollInterval);
        globalInstallPollInterval = null;
      }
      if (installPollInterval) {
        clearInterval(installPollInterval);
        installPollInterval = null;
      }
      
      // Continue progress animation
      updateInstallStep(2, 'done');
      updateInstallStep(3, 'active');
      progressBar.style.width = '70%';
      statusText.textContent = 'Configuring environment...';
      await new Promise(r => setTimeout(r, 800));
      
      updateInstallStep(3, 'done');
      updateInstallStep(4, 'active');
      progressBar.style.width = '90%';
      statusText.textContent = 'Finalizing setup...';
      await new Promise(r => setTimeout(r, 500));
      
      updateInstallStep(4, 'done');
      progressBar.style.width = '100%';
      statusText.textContent = 'Complete!';
      
      // Store sandbox ID and backend type (use window scope for cleanup access)
      window.installedSandboxId = data.sandbox_id;
      window.installedSandboxBackend = data.backend || 'lxd';
      
      // Update GINTO_AUTH so console and other features can use the sandbox
      if (!window.GINTO_AUTH) window.GINTO_AUTH = {};
      if (!window.GINTO_AUTH.sandbox) window.GINTO_AUTH.sandbox = {};
      window.GINTO_AUTH.sandbox.id = data.sandbox_id;
      
      // Update display
      if (sandboxIdDisplay) {
        sandboxIdDisplay.textContent = data.sandbox_id.substring(0, 8);
      }
      
      // Update status indicator to running
      updateSandboxStatusIndicator('running');
      
      // Update tab visibility based on sandbox backend (Docker vs LXD)
      // Docker mode: only My Files available; LXD mode: all tabs available
      if (typeof updateTabsForBackend === 'function') {
        updateTabsForBackend(data.backend || 'lxd');
      }
      
      await new Promise(r => setTimeout(r, 500));
      
      // Show success step (step 5)
      showWizardStep(5);
      document.getElementById('wizard-sandbox-id').textContent = data.sandbox_id;
      
      // Auto-trigger OpenWebUI install if pending
      if (window.pendingOpenWebuiInstall) {
        console.log('[OWUI DEBUG] Auto-calling openSandboxAfterInstall for pending OpenWebUI install');
        await new Promise(r => setTimeout(r, 500));
        openSandboxAfterInstall();
      }
      
    } catch (err) {
      console.error('Sandbox installation error:', err);
      document.getElementById('wizard-error-message').textContent = err.message || 'An unknown error occurred.';
      showWizardStep('error');
    }
  }
  
  async function openSandboxAfterInstall() {
    console.log('[OWUI DEBUG] openSandboxAfterInstall called, pendingOpenWebuiInstall:', window.pendingOpenWebuiInstall);
    
    // Check if we have a pending OpenWebUI install
    if (window.pendingOpenWebuiInstall) {
      closeSandboxWizard();
      console.log('[OWUI DEBUG] Posting sandbox_created message');
      // Post message BEFORE clearing the flag (listener will clear it)
      window.postMessage({ type: 'sandbox_created', id: window.installedSandboxId }, '*');
      return;
    }
    
    const sandboxId = window.installedSandboxId || (sandboxIdDisplay?.textContent || '').trim();
    
    // Close the wizard and show a loading overlay
    closeSandboxWizard();
    
    // Create loading overlay
    const loadingEl = document.createElement('div');
    loadingEl.id = 'sandbox-startup-loading';
    loadingEl.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-[9999]';
    loadingEl.innerHTML = `
      <div class="bg-gray-800 text-white px-8 py-6 rounded-xl shadow-2xl text-center">
        <div class="w-8 h-8 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
        <div id="sandbox-startup-status" class="font-medium">Starting sandbox...</div>
      </div>
    `;
    document.body.appendChild(loadingEl);
    
    const statusEl = document.getElementById('sandbox-startup-status');
    
    // Poll sandbox until Caddy is responding (max 30 seconds)
    const maxAttempts = 30;
    let attempts = 0;
    let startedSandbox = false;
    
    while (attempts < maxAttempts) {
      attempts++;
      try {
        // Check if sandbox is running
        const statusRes = await fetch('/api/sandbox/status', { credentials: 'same-origin' });
        const statusData = await statusRes.json().catch(() => null);
        
        // If sandbox is stopped, start it
        if (statusData?.container_status === 'stopped' && !startedSandbox) {
          startedSandbox = true;
          if (statusEl) statusEl.textContent = 'Starting container...';
          try {
            const startBody = new URLSearchParams();
            startBody.append('csrf_token', window.GINTO_AUTH?.csrfToken || '');
            await fetch('/api/sandbox/start', {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: startBody.toString()
            });
          } catch (e) {
            console.warn('Error starting sandbox:', e);
          }
        }
        
        // If container is running, check if Caddy is responding
        if (statusData?.container_status === 'running' || statusData?.status === 'ready') {
          if (statusEl) statusEl.textContent = 'Waiting for web server...';
          
          // Check health - curl the sandbox IP and look for Caddy
          try {
            const healthRes = await fetch('/api/sandbox/health', { credentials: 'same-origin' });
            const healthData = await healthRes.json().catch(() => null);
            
            if (healthData?.healthy) {
              // Caddy is responding - ready!
              if (statusEl) statusEl.textContent = 'Loading editor...';
              break;
            }
          } catch (e) {
            console.warn('Health check error:', e);
          }
        } else if (!startedSandbox) {
          if (statusEl) statusEl.textContent = 'Initializing...';
        }
      } catch (e) {
        console.warn('Error checking sandbox status:', e);
      }
      
      // Wait 1 second before next poll
      await new Promise(r => setTimeout(r, 1000));
    }
    
    // Remove loading overlay
    loadingEl.remove();
    
    // Open editor
    let editorUrl = '/editor';
    if (sandboxId && sandboxId !== 'unavailable' && sandboxId !== 'default') {
      editorUrl += '?sandbox=' + encodeURIComponent(sandboxId);
    }
    editorIframe.src = editorUrl;
    editorModal.classList.remove('hidden');
    editorModal.classList.add('flex');
    
    // Refresh file tree after editor loads
    setTimeout(() => {
      try {
        // Try to refresh the file tree in the editor iframe
        if (editorIframe.contentWindow && typeof editorIframe.contentWindow.refreshFileTree === 'function') {
          editorIframe.contentWindow.refreshFileTree();
        } else {
          // Fallback: post message to editor
          editorIframe.contentWindow?.postMessage({ type: 'refresh-tree' }, '*');
        }
      } catch (e) {
        console.warn('Could not refresh file tree:', e);
      }
    }, 1500);
  }
  
  // Copy LXC install command to clipboard
  function copyLxcInstallCmd() {
    const cmd = document.getElementById('lxc-install-cmd').textContent;
    
    function copySuccess() {
      const copyIcon = document.getElementById('lxc-copy-icon');
      const checkIcon = document.getElementById('lxc-check-icon');
      copyIcon.classList.add('hidden');
      checkIcon.classList.remove('hidden');
      showToast('Copied to clipboard!');
      setTimeout(() => {
        copyIcon.classList.remove('hidden');
        checkIcon.classList.add('hidden');
      }, 2000);
    }
    
    function copyFailed() {
      showToast('Failed to copy. Please select and copy manually.', 3000);
    }
    
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(cmd).then(copySuccess).catch(copyFailed);
    } else {
      const textArea = document.createElement('textarea');
      textArea.value = cmd;
      textArea.style.position = 'fixed';
      textArea.style.left = '-9999px';
      textArea.style.top = '-9999px';
      document.body.appendChild(textArea);
      textArea.focus();
      textArea.select();
      try {
        document.execCommand('copy') ? copySuccess() : copyFailed();
      } catch (err) {
        copyFailed();
      }
      document.body.removeChild(textArea);
    }
  }
  
  // Start LXC auto-install via Console/Terminal
  async function startLxcAutoInstall() {
    showWizardStep('lxc-terminal');
    
    const terminalOutput = document.getElementById('lxc-terminal-output');
    const statusText = document.getElementById('lxc-terminal-status');
    const doneBtn = document.getElementById('lxc-install-done-btn');
    
    // Add initial output
    terminalOutput.innerHTML = '<span class="text-gray-500">$ sudo bash ~/silverqueen.pro/bin/ginto.sh install</span>\n<span class="text-yellow-400">Starting LXC/LXD setup...</span>\n\n';
    
    try {
      // Call the exec endpoint to run the install script
      const res = await fetch('/api/sandbox/exec', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          command: 'sudo bash ~/silverqueen.pro/bin/ginto.sh --auto',
          csrf_token: window.GINTO_AUTH?.csrfToken || ''
        })
      });
      
      // Stream the response
      const reader = res.body?.getReader();
      const decoder = new TextDecoder();
      
      if (!reader) {
        throw new Error('Streaming not supported');
      }
      
      while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        
        const text = decoder.decode(value);
        terminalOutput.innerHTML += text;
        terminalOutput.scrollTop = terminalOutput.scrollHeight;
      }
      
      terminalOutput.innerHTML += '\n<span class="text-green-400">✓ Installation complete!</span>\n';
      statusText.textContent = 'Installation complete!';
      doneBtn.disabled = false;
      
      // Start polling for completion status
      startInstallStatusPolling(terminalOutput, statusText, doneBtn);
      
    } catch (err) {
      console.error('LXC auto-install error:', err);
      terminalOutput.innerHTML += `\n<span class="text-red-400">Error: ${err.message}</span>\n`;
      terminalOutput.innerHTML += '<span class="text-yellow-400">Please run the command manually in your server terminal.</span>\n';
      terminalOutput.innerHTML += '<span class="text-gray-400">Installation continues in background. Checking status...</span>\n';
      // Start polling even on error (background install continues)
      startInstallStatusPolling(terminalOutput, statusText, doneBtn);
    }
  }
  
  // Poll for installation status (background install)
  let installPollInterval = null;
  function startInstallStatusPolling(terminalOutput, statusText, doneBtn) {
    if (installPollInterval) return; // Already polling
    
    installPollInterval = setInterval(async () => {
      try {
        const res = await fetch('/api/sandbox/image-install-status', { credentials: 'same-origin' });
        const data = await res.json();
        
        if (data.success && data.status === 'complete' && data.ready_for_sandbox) {
          clearInterval(installPollInterval);
          installPollInterval = null;
          
          // Show success message
          terminalOutput.innerHTML += '\n\n<span class="text-green-400 font-bold">🎉 Congratulations!</span>\n';
          terminalOutput.innerHTML += '<span class="text-green-400">The packages and your sandbox base image are ready!</span>\n';
          terminalOutput.innerHTML += '<span class="text-cyan-400">Auto-creating your sandbox...</span>\n';
          terminalOutput.scrollTop = terminalOutput.scrollHeight;
          
          statusText.textContent = '✓ Base image ready! Creating your sandbox...';
          statusText.classList.remove('text-yellow-400');
          statusText.classList.add('text-green-400');
          
          // Auto-create sandbox instead of requiring user to click
          setTimeout(() => {
            handleDoneClick();
          }, 1000);
        } else if (data.status === 'in_progress') {
          // Update status text
          statusText.textContent = data.message || 'Installing...';
          if (data.log_tail) {
            // Show last line of log
            const lines = data.log_tail.trim().split('\n');
            const lastLine = lines[lines.length - 1] || '';
            if (lastLine && !terminalOutput.innerHTML.includes(lastLine.substring(0, 50))) {
              terminalOutput.innerHTML += lastLine + '\n';
              terminalOutput.scrollTop = terminalOutput.scrollHeight;
            }
          }
        } else if (data.status === 'error') {
          clearInterval(installPollInterval);
          installPollInterval = null;
          terminalOutput.innerHTML += `\n<span class="text-red-400">Error: ${data.message}</span>\n`;
          statusText.textContent = 'Installation failed';
          doneBtn.disabled = false;
          doneBtn.textContent = 'Retry Installation';
        }
      } catch (err) {
        console.error('Status poll error:', err);
      }
    }, 3000); // Poll every 3 seconds
  }
  
  // Global polling function for when console is open (no wizard UI elements)
  let globalInstallPollInterval = null;
  function startInstallStatusPollingGlobal() {
    if (globalInstallPollInterval) return; // Already polling
    
    globalInstallPollInterval = setInterval(async () => {
      try {
        const res = await fetch('/api/sandbox/image-install-status', { credentials: 'same-origin' });
        const data = await res.json();
        
        if (data.success && data.status === 'complete' && data.ready_for_sandbox) {
          clearInterval(globalInstallPollInterval);
          globalInstallPollInterval = null;
          
          // Show success notification
          showInstallCompleteNotification();
        } else if (data.status === 'error') {
          clearInterval(globalInstallPollInterval);
          globalInstallPollInterval = null;
          
          // Show error notification
          showNotification('Installation failed: ' + (data.message || 'Unknown error'), 'error');
        }
      } catch (err) {
        console.error('Global status poll error:', err);
      }
    }, 3000); // Poll every 3 seconds
  }
  
  // Show install complete notification
  function showInstallCompleteNotification() {
    // Clear the install session from localStorage since it's done
    localStorage.removeItem('ginto-install-session');
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = 'fixed bottom-20 right-4 z-[100] bg-green-600 text-white px-6 py-4 rounded-lg shadow-2xl max-w-md';
    notification.innerHTML = `
      <div class="flex items-center gap-3">
        <span class="text-2xl">🎉</span>
        <div>
          <div class="font-bold">Base Image Ready!</div>
          <div class="text-sm opacity-90">Auto-creating your sandbox...</div>
        </div>
      </div>
    `;
    document.body.appendChild(notification);
    
    // Auto-proceed to sandbox creation
    setTimeout(() => {
      notification.remove();
      proceedToSandboxInstall();
    }, 1500);
  }
  
  // Proceed to sandbox installation after base image is ready
  function proceedToSandboxInstall() {
    // Minimize console if open
    if (typeof window.minimizeConsole === 'function') {
      window.minimizeConsole();
    }
    
    // Open wizard and directly start installation (skip terms - already accepted)
    const wizard = document.getElementById('sandbox-wizard-modal');
    if (wizard) {
      wizard.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
    }
    
    // Mark terms as accepted (they were already accepted when starting base image install)
    const checkbox = document.getElementById('accept-sandbox-terms');
    if (checkbox) checkbox.checked = true;
    
    // Directly trigger install
    installSandbox();
  }
  
  // Open wizard specifically for proceeding after ginto.sh install
  function openSandboxWizardForProceed() {
    const wizard = document.getElementById('sandbox-wizard-modal');
    const step1 = document.getElementById('wizard-step-1');
    const step2 = document.getElementById('wizard-step-2');
    
    if (!wizard || !step1 || !step2) return;
    
    // Show wizard
    wizard.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Hide step 1, show step 2
    step1.classList.add('hidden');
    step2.classList.remove('hidden');
    
    // Update the step 2 header to show proceed message
    const step2Header = step2.querySelector('h2');
    const step2Subtitle = step2.querySelector('p.text-gray-400');
    if (step2Header) step2Header.textContent = 'Ready to Create Sandbox';
    if (step2Subtitle) step2Subtitle.textContent = 'Base image installed. Proceed to create your personal sandbox.';
    
    // Change the icon to a checkmark/success icon
    const step2Icon = step2.querySelector('.bg-amber-500');
    if (step2Icon) {
      step2Icon.classList.remove('bg-amber-500');
      step2Icon.classList.add('bg-green-500');
      step2Icon.innerHTML = `<svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
      </svg>`;
    }
  }

  /**
   * Check for existing install screen session on page load (admin only)
   * If found, offer to reconnect to view progress
   */
  async function checkForExistingInstallSession() {
    // Only for admins
    <?php if (!empty($_SESSION['is_admin'])): ?>
    try {
      const res = await fetch('/api/sandbox/install-session-status', { credentials: 'same-origin' });
      const data = await res.json();
      
      if (data.success && data.session_exists) {
        // Show notification to reconnect
        showInstallSessionReconnectNotification();
      }
    } catch (err) {
      console.error('Error checking install session:', err);
    }
    <?php endif; ?>
  }

  /**
   * Show notification to reconnect to existing install session
   */
  function showInstallSessionReconnectNotification() {
    // Check if notification already exists
    if (document.getElementById('install-reconnect-notification')) return;
    
    const notification = document.createElement('div');
    notification.id = 'install-reconnect-notification';
    notification.className = 'fixed bottom-20 right-4 z-[100] bg-blue-600 text-white px-6 py-4 rounded-lg shadow-2xl max-w-md';
    notification.innerHTML = `
      <div class="flex items-center gap-3">
        <span class="text-2xl">⚙️</span>
        <div class="flex-1">
          <div class="font-bold">Installation in Progress</div>
          <div class="text-sm opacity-90 mt-1">A sandbox installation is running in the background.</div>
        </div>
        <div class="flex flex-col gap-2">
          <button onclick="reconnectToInstallSession()" class="px-3 py-1.5 bg-white text-blue-600 rounded-md text-sm font-medium hover:bg-blue-50 transition-colors">
            View Progress
          </button>
          <button onclick="dismissInstallReconnectNotification()" class="px-3 py-1.5 bg-blue-700 text-white rounded-md text-sm hover:bg-blue-800 transition-colors">
            Dismiss
          </button>
        </div>
      </div>
    `;
    document.body.appendChild(notification);
  }

  /**
   * Reconnect to the existing install terminal session
   */
  function reconnectToInstallSession() {
    dismissInstallReconnectNotification();
    
    if (typeof window.openConsoleWithCommand === 'function') {
      // Just open console with same session ID - terminal server will reconnect to existing PTY
      // Don't send any command - we're just reattaching
      window.openConsoleWithCommand(null, null, {
        sessionId: 'ginto-install',
        tabLabel: 'LXD Install'
      });
      
      // Start polling for completion
      startInstallStatusPollingGlobal();
    }
  }

  /**
   * Dismiss the reconnect notification
   */
  function dismissInstallReconnectNotification() {
    const notification = document.getElementById('install-reconnect-notification');
    if (notification) notification.remove();
  }

  // Check for existing install session after page loads
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', checkForExistingInstallSession);
  } else {
    // DOM already loaded
    setTimeout(checkForExistingInstallSession, 1000);
  }
</script>
