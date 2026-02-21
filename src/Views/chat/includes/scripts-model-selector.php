<?php
/**
 * Admin Model Selector Scripts
 * For admin users to select and configure AI models
 */
?>
<!-- Model Selector Script (available to admin and non-admin users) -->
<script>
(function() {
  // Support both desktop and mobile model selector elements.
  const btnEls = Array.from(document.querySelectorAll('#model-selector-btn, #model-selector-btn-mobile'));
  const dropdownDesktop = document.getElementById('model-dropdown');
  const dropdownMobile = document.getElementById('model-dropdown-mobile');
  const modelList = document.getElementById('model-list');
  const modelListMobile = document.getElementById('model-list-mobile');
  const modelName = document.getElementById('model-name');
  const statusDot = document.getElementById('model-status-dot');
  const mobileModelName = document.getElementById('mobile-model-name');
  const mobileStatusDot = document.getElementById('mobile-model-status-dot');
  const searchInput = document.getElementById('model-search') || document.getElementById('model-search-mobile');
  const addProviderBtn = document.getElementById('add-provider-btn') || document.getElementById('add-provider-btn-mobile');
  
  if (btnEls.length === 0) return;
  
  // Provider priority order (groq and cerebras first)
  const PROVIDER_PRIORITY = ['local', 'ollama', 'cerebras', 'groq', 'openai', 'anthropic', 'novita', 'together', 'fireworks'];
  
  // Helper to update both desktop and mobile displays
  function updateModelDisplay(model, dotClass) {
    if (modelName) {
      modelName.textContent = model;
      modelName.title = model;
      modelName.setAttribute('aria-label', model);
    }
    if (mobileModelName) {
      mobileModelName.textContent = model;
      mobileModelName.title = model;
      mobileModelName.setAttribute('aria-label', model);
    }
    if (statusDot) statusDot.className = dotClass;
    if (mobileStatusDot) mobileStatusDot.className = dotClass.replace('w-2 h-2', 'w-2 h-2') + ' hidden min-[350px]:block';
  }
  
  let modelsData = null;
  let isOpen = false;

  const searchInputDesktop = document.getElementById('model-search');
  const searchInputMobile = document.getElementById('model-search-mobile');

  function getActiveDropdown() {
    const dDesktop = document.getElementById('model-dropdown');
    const dMobile = document.getElementById('model-dropdown-mobile');
    // Prefer the dropdown that is currently visible in the layout (offsetParent != null)
    if (dMobile && dMobile.offsetParent !== null) return dMobile;
    if (dDesktop && dDesktop.offsetParent !== null) return dDesktop;
    // Fallback to mobile then desktop if neither visible
    return dMobile || dDesktop;
  }
  
  // Toggle dropdown for each button separately (desktop and mobile)
  function hideAllDropdowns() {
    if (dropdownDesktop) {
      dropdownDesktop.classList.add('hidden');
      dropdownDesktop.style.width = '';
      dropdownDesktop.style.left = '';
      dropdownDesktop.style.top = '';
    }
    if (dropdownMobile) {
      dropdownMobile.classList.add('hidden');
      dropdownMobile.style.width = '';
      dropdownMobile.style.left = '';
      dropdownMobile.style.top = '';
    }
  }

  btnEls.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isMobileBtn = btn.id && btn.id.includes('mobile');
      const targetDropdown = isMobileBtn ? dropdownMobile : dropdownDesktop;
      if (!targetDropdown) return;

      const wasHidden = targetDropdown.classList.contains('hidden');
      hideAllDropdowns();
      if (wasHidden) {
        targetDropdown.classList.remove('hidden');
        // Size dropdown to match the selector button width when possible
        try {
          const rect = btn.getBoundingClientRect();
          const btnWidth = Math.round(Math.min(350, rect.width));
          targetDropdown.style.width = `${btnWidth}px`;
          // For mobile dropdown (fixed), align left origin to the button's left edge and position top under the button
          if (isMobileBtn && targetDropdown) {
            // Keep mobile dropdown pinned with 5px margins from left/right
            targetDropdown.style.left = '5px';
            targetDropdown.style.right = '5px';
            // top should be just below the button
            targetDropdown.style.top = `${Math.round(rect.bottom + 6)}px`;
            // unset width so CSS max-width controls sizing
            targetDropdown.style.width = '';
          }
        } catch (e) {
          // ignore
        }
        // Lazy-load models
        if (!modelsData) loadModels();
        // Focus appropriate search input
        setTimeout(() => {
          const toFocus = isMobileBtn ? searchInputMobile : searchInputDesktop;
          if (toFocus) toFocus.focus();
        }, 100);
      }
    });
  });
  
  // Close on outside click
  document.addEventListener('click', (e) => {
    // If click is outside both dropdowns and not on any selector button, hide both
    const clickedOnBtn = btnEls.some(b => b.contains(e.target));
    const clickedInsideDesktop = dropdownDesktop && dropdownDesktop.contains(e.target);
    const clickedInsideMobile = dropdownMobile && dropdownMobile.contains(e.target);
    if (!clickedOnBtn && !clickedInsideDesktop && !clickedInsideMobile) {
      hideAllDropdowns();
    }
  });
  
  // Search filtering
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      const query = e.target.value.toLowerCase().trim();
      filterModels(query);
    });
  }
  
  function filterModels(query) {
    const lists = [];
    if (modelList) lists.push(modelList);
    if (modelListMobile) lists.push(modelListMobile);

    lists.forEach(listEl => {
      const buttons = listEl.querySelectorAll('button[data-model]');
      const headers = listEl.querySelectorAll('div[data-provider-header]');

      buttons.forEach(btn => {
        const model = (btn.dataset.model || '').toLowerCase();
        const provider = (btn.dataset.provider || '').toLowerCase();
        const matches = model.includes(query) || provider.includes(query);
        btn.style.display = matches ? '' : 'none';
      });

      // Hide provider headers if all their models are hidden
      headers.forEach(header => {
        const providerName = header.dataset.providerHeader;
        const visibleModels = listEl.querySelectorAll(`button[data-provider="${providerName}"]:not([style*="display: none"])`);
        header.style.display = visibleModels.length > 0 ? '' : 'none';
      });
    });
  }
  
  // Add Key button opens settings panel with admin (API Keys) tab
  if (addProviderBtn) {
    addProviderBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      // Close the model dropdown
      isOpen = false;
      const ad = getActiveDropdown(); if (ad) ad.classList.add('hidden');
      // Open settings with admin (API Keys) tab
      openSettings('admin');
    });
  }
  
  // Load models from API (exposed globally for use by key management)
  async function loadModels() {
    const loadingHtml = '<div class="px-4 py-3 text-sm text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading models...</div>';
    if (modelList) modelList.innerHTML = loadingHtml;
    if (modelListMobile) modelListMobile.innerHTML = loadingHtml;
    
    try {
      const res = await fetch('/api/models', { credentials: 'same-origin' });
      const data = await res.json();
      
      if (!data.success) {
        modelList.innerHTML = '<div class="px-4 py-3 text-sm text-red-500">Error loading models</div>';
        return;
      }
      
      modelsData = data;
      renderModels(data);
      
      // Update current model display
      if (data.current_model) {
        const isOllama = data.current_provider === 'ollama';
        const runningModels = data.running_models || [];
        const isModelRunning = !isOllama || runningModels.some(m => m === data.current_model || m.startsWith(data.current_model));
        const dotClass = isModelRunning ? 'w-2 h-2 rounded-full bg-green-500' : 'w-2 h-2 rounded-full bg-red-500';
        updateModelDisplay(data.current_model, dotClass);
        
        // Update capability UI on initial load
        if (data.current_capabilities) {
          updateCapabilityUI(data.current_capabilities);
        }
      }
    } catch (err) {
      modelList.innerHTML = '<div class="px-4 py-3 text-sm text-red-500">Failed to load models</div>';
      console.error('Model load error:', err);
    }
  }
  
  // Render model list with priority ordering
  function renderModels(data) {
    let html = '';
    const providers = data.providers || {};
    const isAdmin = !!data.is_admin;
    const currentUserId = data.current_user_id || null;
    
    // Convert providers object to array and optionally filter unavailable providers for non-admin users
    const providerEntries = Object.keys(providers).map(k => ({ key: k, val: providers[k] }));
    const availableProviders = providerEntries.filter(entry => {
      if (isAdmin) return true;
      const p = entry.val || {};
      // Show provider only if an env key exists or the current user has a key for it
      if (p.env_key) return true;
      if (p.has_user_key) return true;
      // Also allow if any db_key belongs to current user
      if (Array.isArray(p.db_keys)) {
        for (const k of p.db_keys) {
          if (k.user_id && String(k.user_id) === String(currentUserId)) return true;
        }
      }
      return false;
    });

    // Sort providers by priority
    const sortedProviders = availableProviders.map(e => e.key).sort((a, b) => {
      const aIdx = PROVIDER_PRIORITY.indexOf(a);
      const bIdx = PROVIDER_PRIORITY.indexOf(b);
      return (aIdx === -1 ? 999 : aIdx) - (bIdx === -1 ? 999 : bIdx);
    });
    
      if (sortedProviders.length === 0) {
      html = '<div class="px-4 py-3 text-sm text-gray-500">No providers configured</div>';
    } else {
      for (const providerName of sortedProviders) {
        const providerData = providers[providerName];
        // If not admin and provider wasn't included in availableProviders, skip
        if (!isAdmin) {
          const isIncluded = availableProviders.some(en => en.key === providerName);
          if (!isIncluded) continue;
        }
        const displayName = providerData.display_name || providerName;
        const isLocalProvider = providerName === 'local' || providerName === 'ollama';
        
        html += `<div data-provider-header="${escapeHtml(providerName)}" class="px-4 py-2 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide bg-gray-50 dark:bg-gray-800/50 flex items-center gap-2">
          ${isLocalProvider ? '<i class="fas fa-server text-green-500" title="Local/Offline"></i>' : '<i class="fas fa-cloud text-gray-400" title="Cloud API"></i>'}
          <span>${escapeHtml(displayName)}</span>
        </div>`;
        
        // Use models_with_names if available (for local provider with display names)
        const models = providerData.models_with_names || providerData.models?.map(m => ({ id: m, name: m })) || [];
        // Get capabilities for this provider's models
        const providerCapabilities = providerData.capabilities || {};
        
        for (const modelInfo of models) {
          const modelId = typeof modelInfo === 'string' ? modelInfo : modelInfo.id;
          const modelDisplay = typeof modelInfo === 'string' ? modelInfo : (modelInfo.name || modelInfo.id);
          const isActive = data.current_provider === providerName && data.current_model === modelId;
          
          // Get model capabilities for badges
          const modelCaps = providerCapabilities[modelId] || {};
          let capBadges = '';
          
          // Vision capability - outline eye icon
          if (modelCaps.vision) {
            capBadges += '<span class="ml-1 text-xs text-blue-400 dark:text-blue-300" title="Vision/Multimodal"><i class="far fa-eye"></i></span>';
          }
          // Thinking/Reasoning capability - outline brain icon
          if (modelCaps.thinking) {
            capBadges += '<span class="ml-1 text-xs text-purple-400 dark:text-purple-300" title="Reasoning/Thinking"><i class="far fa-lightbulb"></i></span>';
          }
          // TTS (Text-to-Speech) capability - speaker icon
          if (modelCaps.tts) {
            capBadges += '<span class="ml-1 text-xs text-orange-400 dark:text-orange-300" title="Text-to-Speech"><i class="far fa-volume-up"></i></span>';
          }
          // STT (Speech-to-Text) capability - microphone icon
          if (modelCaps.stt) {
            capBadges += '<span class="ml-1 text-xs text-teal-400 dark:text-teal-300" title="Speech-to-Text"><i class="far fa-microphone"></i></span>';
          }
          // Local/Offline indicator for individual models
          if (isLocalProvider) {
            capBadges += '<span class="ml-1 text-xs text-green-400 dark:text-green-300" title="Runs locally"><i class="far fa-hdd"></i></span>';
          }
          
          html += `
            <button 
              class="w-full text-left px-4 py-2 text-sm hover:bg-indigo-50 dark:hover:bg-indigo-900/30 flex items-center gap-2 ${isActive ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400' : 'text-gray-700 dark:text-gray-300'}"
              data-provider="${escapeHtml(providerName)}"
              data-model="${escapeHtml(modelId)}"
            >
              ${isActive ? '<i class="fas fa-check text-xs text-indigo-500"></i>' : '<span class="w-3"></span>'}
              <span class="truncate flex-1">${escapeHtml(modelDisplay)}</span>${capBadges}
            </button>
          `;
        }
      }
    }
    
    // Populate desktop and mobile lists where present
    if (modelList) modelList.innerHTML = html;
    if (modelListMobile) modelListMobile.innerHTML = html;

    // Add click handlers for both lists
    const listsToBind = [];
    if (modelList) listsToBind.push(modelList);
    if (modelListMobile) listsToBind.push(modelListMobile);
    listsToBind.forEach(listEl => {
      listEl.querySelectorAll('button[data-provider]').forEach(btn => {
        btn.addEventListener('click', () => selectModel(btn.dataset.provider, btn.dataset.model));
      });
    });
  }
  
  // Update UI based on model capabilities (vision/thinking)
  function updateCapabilityUI(capabilities) {
    const attachBtn = document.getElementById('attach-btn');
    const attachInput = document.getElementById('attach-input');
    const attachPreview = document.getElementById('attach-preview');
    
    // Store capabilities globally for chat handlers
    window.GINTO_MODEL_CAPABILITIES = capabilities || { vision: false, thinking: false };
    
    if (attachBtn) {
      if (capabilities?.vision) {
        // Show attachment button for vision-enabled models
        attachBtn.classList.remove('hidden');
        attachBtn.title = 'Attach image (vision enabled)';
      } else {
        // Hide attachment button for non-vision models
        attachBtn.classList.add('hidden');
        // Clear any pending attachment
        if (attachInput) attachInput.value = '';
        if (attachPreview) attachPreview.classList.add('hidden');
      }
    }
    
    // Update thinking model indicator
    const modelSelectorBtn = document.getElementById('model-selector-btn');
    if (modelSelectorBtn) {
      // Remove existing thinking indicator
      const existingIndicator = modelSelectorBtn.querySelector('.thinking-indicator');
      if (existingIndicator) existingIndicator.remove();
      
      if (capabilities?.thinking) {
        // Add thinking indicator for reasoning models
        const indicator = document.createElement('span');
        indicator.className = 'thinking-indicator ml-1 text-xs text-purple-500 dark:text-purple-400';
        indicator.innerHTML = '<i class="fas fa-brain"></i>';
        indicator.title = 'Reasoning model - will show thinking process';
        modelSelectorBtn.appendChild(indicator);
      }
    }
  }
  
  // Select a model
  async function selectModel(provider, model) {
    updateModelDisplay(model, 'w-2 h-2 rounded-full bg-yellow-500 animate-pulse');
    
    try {
      const res = await fetch('/api/models/set', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.GINTO_AUTH?.csrfToken || window.CSRF_TOKEN || ''
        },
        body: JSON.stringify({ provider, model })
      });
      const data = await res.json();
      
      if (data.success) {
        updateModelDisplay(model, 'w-2 h-2 rounded-full bg-green-500');
        
        // Update UI based on capabilities
        if (data.capabilities) {
          updateCapabilityUI(data.capabilities);
        }
        
        // Update local state
        if (modelsData) {
          modelsData.current_provider = provider;
          modelsData.current_model = model;
          modelsData.current_capabilities = data.capabilities;
          renderModels(modelsData);
        }
        
        // Close dropdown
        isOpen = false;
        const ad = getActiveDropdown(); if (ad) ad.classList.add('hidden');
        
        // Show toast notification
        let toastMsg = `Switched to ${provider} / ${model}`;
        if (data.capabilities?.vision) toastMsg += ' 🖼️';
        if (data.capabilities?.thinking) toastMsg += ' 🧠';
        showToast(toastMsg, 'success');
      } else {
        updateModelDisplay(modelName?.textContent || 'Ginto AI', 'w-2 h-2 rounded-full bg-red-500');
        showToast(data.error || 'Failed to switch model', 'error');
      }
    } catch (err) {
      updateModelDisplay(modelName?.textContent || 'Ginto AI', 'w-2 h-2 rounded-full bg-red-500');
      showToast('Network error', 'error');
      console.error('Model switch error:', err);
    }
  }
  
  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }
  
  // Expose loadModels globally for cross-script access
  window.refreshModelsList = loadModels;
  
  // Load current model on page load
  loadModels();
})();
</script>
