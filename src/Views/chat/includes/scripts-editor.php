<?php
/**
 * Editor Modal Controls and Sandbox Status
 */
?>
<script>
  // Editor Modal Controls
  const editorModal = document.getElementById('editor-modal');
  const editorIframe = document.getElementById('editor-iframe');
  const openMyFilesBtn = document.getElementById('open-my-files');
  const closeEditorBtn = document.getElementById('close-editor');
  const sandboxIdDisplay = document.getElementById('sandbox-id-display');
  
  // Update sandbox ID display when auth is ready
  window.addEventListener('gintoAuthReady', (e) => {
    if (e.detail && e.detail.sandbox && e.detail.sandbox.id && sandboxIdDisplay) {
      sandboxIdDisplay.textContent = e.detail.sandbox.id.substring(0, 8);
    }
  });

  // Also attempt to populate the sandbox display immediately from any
  // available client-side state (helps when session was set by /editor).
  (function populateSandboxDisplay() {
    function setIf(id) {
      if (!id || !sandboxIdDisplay) return false;
      sandboxIdDisplay.textContent = id.substring(0, 8);
      return true;
    }

    try {
      // 1) server-rendered variable (already handled by PHP), leave as-is
      // 2) check global auth object if present
      if (window.GINTO_AUTH && window.GINTO_AUTH.sandbox && window.GINTO_AUTH.sandbox.id) {
        if (setIf(window.GINTO_AUTH.sandbox.id)) return;
      }

      // 3) check editorConfig if populated by any prior editor load
      if (window.editorConfig && window.editorConfig.sandboxId) {
        if (setIf(window.editorConfig.sandboxId)) return;
      }

      // 4) if auth promise exists, attach a handler to update once ready
      if (window.GINTO_AUTH_PROMISE && typeof window.GINTO_AUTH_PROMISE.then === 'function') {
        window.GINTO_AUTH_PROMISE.then(function() {
          try {
            if (window.GINTO_AUTH && window.GINTO_AUTH.sandbox && window.GINTO_AUTH.sandbox.id) {
              setIf(window.GINTO_AUTH.sandbox.id);
            }
          } catch (e) {}
        }).catch(function(){});
      }

      // 5) listen for editor iframe postMessage events announcing sandbox creation or destruction
      window.addEventListener('message', function(ev) {
        try {
          var d = ev && ev.data;
          if (!d) return;
          if (d.type === 'sandbox_created' && d.id) {
            setIf(d.id);
          }
          // Handle sandbox destruction - close editor modal
          if (d.type === 'sandbox_destroyed') {
            console.log('Sandbox destroyed message received');
            var modal = document.getElementById('editor-modal');
            var iframe = document.getElementById('editor-iframe');
            if (modal) {
              modal.classList.add('hidden');
              modal.classList.remove('flex');
            }
            // Clear the iframe to prevent stale content
            if (iframe) {
              iframe.src = 'about:blank';
            }
            // Clear the sandbox ID display
            var sandboxDisplay = document.getElementById('sandbox-id-display');
            if (sandboxDisplay) {
              sandboxDisplay.textContent = '';
            }
            // Clear any cached sandbox IDs to prevent stale references
            if (window.GINTO_AUTH && window.GINTO_AUTH.sandbox) {
              window.GINTO_AUTH.sandbox = null;
            }
            if (window.editorConfig) {
              window.editorConfig.sandboxId = null;
            }
            // Clear installedSandboxId if it exists in global scope
            if (typeof window.installedSandboxId !== 'undefined') {
              window.installedSandboxId = null;
            }
            // Update status indicator directly to show sandbox not installed
            var indicator = document.getElementById('sandbox-status-indicator');
            if (indicator) {
              var dot = indicator.querySelector('span');
              if (dot) {
                dot.className = 'w-2 h-2 rounded-full bg-gray-400 inline-block';
                dot.title = 'Sandbox not installed';
              }
            }
          }
        } catch (e) {
          console.error('Error handling postMessage:', e);
        }
      }, false);
    } catch (e) {
      // ignore failures
    }
  })();
  
  // Update sandbox status indicator in sidebar
  function updateSandboxStatusIndicator(status) {
    const indicator = document.getElementById('sandbox-status-indicator');
    if (!indicator) return;
    
    const dot = indicator.querySelector('span');
    if (!dot) return;
    
    switch (status) {
      case 'running':
      case 'ready':
        indicator.classList.remove('hidden');
        dot.className = 'w-2 h-2 rounded-full bg-emerald-500 inline-block animate-pulse';
        dot.title = 'Sandbox running';
        break;
      case 'stopped':
      case 'installed':
        indicator.classList.remove('hidden');
        dot.className = 'w-2 h-2 rounded-full bg-amber-500 inline-block';
        dot.title = 'Sandbox stopped';
        break;
      case 'not_created':
      case 'not_installed':
        // Hide indicator when no sandbox exists
        indicator.classList.add('hidden');
        break;
      case 'error':
        indicator.classList.remove('hidden');
        dot.className = 'w-2 h-2 rounded-full bg-red-500 inline-block';
        dot.title = 'Sandbox error';
        break;
      default:
        indicator.classList.add('hidden');
    }
  }
  
  // Pre-fetch sandbox status on page load to show indicator
  (async function prefetchSandboxStatus() {
    try {
      await window.GINTO_AUTH_PROMISE;
      const res = await fetch('/api/sandbox/status', { credentials: 'same-origin' });
      const data = await res.json().catch(() => null);
      if (data && data.status) {
        // Only show indicator if sandbox actually exists
        if (data.status === 'not_created' || data.status === 'not_installed') {
          // Hide indicator when no sandbox exists
          const indicator = document.getElementById('sandbox-status-indicator');
          if (indicator) indicator.classList.add('hidden');
        } else {
          updateSandboxStatusIndicator(data.container_status || data.status);
        }
      }
    } catch (e) {
      // ignore
    }
  })();

  // Check sandbox status before opening files
  async function checkAndOpenSandbox() {
    try {
      await window.GINTO_AUTH_PROMISE;
      
      // Check sandbox status first
      const statusRes = await fetch('/api/sandbox/status', { credentials: 'same-origin' });
      const statusData = await statusRes.json().catch(() => null);
      
      if (!statusRes.ok || !statusData) {
        console.error('Failed to check sandbox status:', statusData);
        // If request failed, show wizard to let user create a sandbox
        showSandboxWizard();
        return;
      }
      
      // Update status indicator
      updateSandboxStatusIndicator(statusData.container_status || statusData.status);
      
      // Handle based on status
      if (statusData.status === 'unauthorized') {
        // Not logged in - show wizard (visitors can use sandbox too)
        showSandboxWizard();
        return;
      }
      
      if (statusData.status === 'not_created' || statusData.status === 'not_installed') {
        // No sandbox exists - show wizard for all users (including admins)
        showSandboxWizard();
        return;
      }
      
      // Sandbox exists (ready, installed, or stopped) - open editor directly
      if (statusData.status === 'ready' || statusData.status === 'installed' || statusData.container_status === 'running') {
        // Sandbox is running - open editor
        if (statusData.sandbox_id && sandboxIdDisplay) {
          sandboxIdDisplay.textContent = statusData.sandbox_id.substring(0, 8);
        }
        
        if (typeof updateTabsForBackend === 'function') {
          updateTabsForBackend(statusData.backend || 'lxd');
        }
        
        let editorUrl = '/editor?sandbox=' + encodeURIComponent(statusData.sandbox_id);
        editorIframe.src = editorUrl;
        editorModal.classList.remove('hidden');
        editorModal.classList.add('flex');
        return;
      }
      
      if (statusData.container_status === 'stopped') {
        // Sandbox exists but not running - try to start it
        try {
          const startBody = new URLSearchParams();
          startBody.append('csrf_token', window.GINTO_AUTH?.csrfToken || '');
          const startRes = await fetch('/api/sandbox/start', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: startBody.toString()
          });
          const startData = await startRes.json().catch(() => null);
          
          if (!startRes.ok || !startData?.success) {
            console.warn('Failed to start sandbox, opening anyway');
          } else {
            // Update indicator to running
            updateSandboxStatusIndicator('running');
          }
        } catch (e) {
          console.warn('Error starting sandbox:', e);
        }
      }
      
      // Update display with sandbox ID
      if (statusData.sandbox_id && sandboxIdDisplay) {
        sandboxIdDisplay.textContent = statusData.sandbox_id.substring(0, 8);
      }
      
      // Update tab visibility based on sandbox backend (Docker vs LXD)
      // Docker mode: only My Files available; LXD mode: all tabs available
      if (typeof updateTabsForBackend === 'function') {
        updateTabsForBackend(statusData.backend || 'lxd');
      }
      
      // Open editor with sandbox context
      // This displays the Monaco editor with the sandbox files in the explorer
      let editorUrl = '/editor?sandbox=' + encodeURIComponent(statusData.sandbox_id);
      editorIframe.src = editorUrl;
      editorModal.classList.remove('hidden');
      editorModal.classList.add('flex');
      
    } catch (err) {
      console.error('Error checking sandbox:', err);
      openEditorDirectly();
    }
  }
  
  function openEditorDirectly() {
    // Try to get sandbox ID from display
    let sandboxId = null;
    try {
      let displayNorm = (sandboxIdDisplay?.textContent || '').trim();
      if (displayNorm.startsWith('[') && displayNorm.endsWith(']')) displayNorm = displayNorm.slice(1, -1).trim();
      if (displayNorm && displayNorm !== 'unavailable' && displayNorm !== 'default') {
        sandboxId = displayNorm;
      }
    } catch (e) {}
    
    // Open editor with sandbox ID if available
    if (sandboxId) {
      editorIframe.src = '/editor?sandbox=' + encodeURIComponent(sandboxId);
    } else {
      // Fallback to editor without sandbox
      editorIframe.src = '/editor';
    }
    editorModal.classList.remove('hidden');
    editorModal.classList.add('flex');
  }
  
  // Open editor in modal - now checks sandbox status first
  openMyFilesBtn?.addEventListener('click', checkAndOpenSandbox);
  
  // Close editor modal
  closeEditorBtn?.addEventListener('click', () => {
    editorModal.classList.add('hidden');
    editorModal.classList.remove('flex');
    // Keep iframe loaded to preserve state
  });
  
  // ESC key closes editor
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !editorModal.classList.contains('hidden')) {
      closeEditorBtn.click();
    }
  });
  
  // New chat button
  document.getElementById('new_chat')?.addEventListener('click', () => {
    if (window.newConvo) {
      window.newConvo();
    } else {
      // fallback: reload page if JS not loaded
      location.reload();
    }
  });
  
  // Auto-resize textarea
  const promptEl = document.getElementById('prompt');
  promptEl?.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 160) + 'px';
  });
  
  /**
   * Auto-create sandbox for admin without showing wizard
   * Admins don't need to accept terms - they own the system
   */
  async function autoCreateSandboxForAdmin() {
    try {
      // Show a simple loading indicator
      const loadingEl = document.createElement('div');
      loadingEl.id = 'admin-sandbox-loading';
      loadingEl.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-[9999]';
      loadingEl.innerHTML = `
        <div class="bg-gray-800 text-white px-8 py-6 rounded-xl shadow-2xl text-center">
          <div class="w-8 h-8 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
          <div id="sandbox-create-status" class="font-medium">Creating your sandbox...</div>
        </div>
      `;
      document.body.appendChild(loadingEl);
      
      // Call sandbox install API
      const body = new URLSearchParams();
      body.append('csrf_token', window.GINTO_AUTH?.csrfToken || '');
      body.append('accept_terms', '1'); // Admins auto-accept
      
      const res = await fetch('/api/sandbox/install', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      });
      
      const data = await res.json().catch(() => null);
      
      if (res.ok && data?.success) {
        // Check if this is an async operation
        if (data.async && data.poll_url) {
          // Poll for completion
          const sandboxId = data.sandbox_id;
          const statusEl = document.getElementById('sandbox-create-status');
          
          let pollCount = 0;
          const maxPolls = 60; // Max 60 polls (1 minute at 1 per second)
          
          const pollStatus = async () => {
            pollCount++;
            try {
              const statusRes = await fetch('/api/sandbox/creation-status', { credentials: 'same-origin' });
              const statusData = await statusRes.json().catch(() => null);
              
              if (statusData?.ready || statusData?.status === 'ready') {
                // Sandbox is ready!
                loadingEl.remove();
                
                if (window.GINTO_AUTH) {
                  window.GINTO_AUTH.sandbox = { id: sandboxId };
                }
                if (sandboxIdDisplay) {
                  sandboxIdDisplay.textContent = sandboxId.substring(0, 8);
                }
                updateSandboxStatusIndicator('running');
                
                editorIframe.src = '/editor?sandbox=' + encodeURIComponent(sandboxId);
                editorModal.classList.remove('hidden');
                editorModal.classList.add('flex');
                return;
              }
              
              if (statusData?.status === 'error') {
                loadingEl.remove();
                console.error('Sandbox creation failed:', statusData.error);
                showSandboxWizard();
                return;
              }
              
              // Update status message
              if (statusEl) {
                const statusMap = {
                  'copying': 'Copying template...',
                  'starting': 'Starting container...',
                  'configuring': 'Configuring services...',
                  'stopped': 'Starting up...'
                };
                statusEl.textContent = statusMap[statusData?.status] || 'Creating your sandbox...';
              }
              
              // Continue polling
              if (pollCount < maxPolls) {
                setTimeout(pollStatus, 1000);
              } else {
                loadingEl.remove();
                console.error('Sandbox creation timed out');
                showSandboxWizard();
              }
            } catch (e) {
              console.error('Poll error:', e);
              if (pollCount < maxPolls) {
                setTimeout(pollStatus, 2000);
              }
            }
          };
          
          // Start polling after a brief delay
          setTimeout(pollStatus, 500);
          return;
        }
        
        // Immediate success (sandbox already existed or sync creation)
        loadingEl.remove();
        
        if (window.GINTO_AUTH) {
          window.GINTO_AUTH.sandbox = { id: data.sandbox_id };
        }
        
        if (sandboxIdDisplay) {
          sandboxIdDisplay.textContent = data.sandbox_id.substring(0, 8);
        }
        
        updateSandboxStatusIndicator('running');
        
        // Open editor
        editorIframe.src = '/editor?sandbox=' + encodeURIComponent(data.sandbox_id);
        editorModal.classList.remove('hidden');
        editorModal.classList.add('flex');
      } else {
        // Failed - fall back to wizard which will show appropriate error
        loadingEl.remove();
        console.error('Auto-create failed:', data?.error);
        showSandboxWizard();
      }
    } catch (err) {
      console.error('Error auto-creating sandbox:', err);
      document.getElementById('admin-sandbox-loading')?.remove();
      // Fall back to wizard
      showSandboxWizard();
    }
  }
</script>
