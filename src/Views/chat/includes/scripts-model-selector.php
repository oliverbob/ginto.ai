<?php
/**
 * Admin Model Selector Scripts
 * For admin users to select and configure AI models
 */
?>
<?php if (!empty($isAdmin)): ?>
<!-- Admin Model Selector Script -->
<script>
(function() {
  const btn = document.getElementById('model-selector-btn');
  const dropdown = document.getElementById('model-dropdown');
  const modelList = document.getElementById('model-list');
  const modelName = document.getElementById('model-name');
  const statusDot = document.getElementById('model-status-dot');
  const mobileModelName = document.getElementById('mobile-model-name');
  const mobileStatusDot = document.getElementById('mobile-model-status-dot');
  const searchInput = document.getElementById('model-search');
  const addProviderBtn = document.getElementById('add-provider-btn');
  
  if (!btn || !dropdown) return;
  
  // Provider priority order (groq and cerebras first)
  const PROVIDER_PRIORITY = ['local', 'ollama', 'cerebras', 'groq', 'openai', 'anthropic', 'together', 'fireworks'];
  
  // Helper to update both desktop and mobile displays
  function updateModelDisplay(model, dotClass) {
    if (modelName) modelName.textContent = model;
    if (mobileModelName) mobileModelName.textContent = model;
    if (statusDot) statusDot.className = dotClass;
    if (mobileStatusDot) mobileStatusDot.className = dotClass.replace('w-2 h-2', 'w-2 h-2') + ' hidden min-[350px]:block';
  }
  
  let modelsData = null;
  let isOpen = false;
  
  // Toggle dropdown
  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    isOpen = !isOpen;
    dropdown.classList.toggle('hidden', !isOpen);
    if (isOpen && !modelsData) {
      loadModels();
    }
    if (isOpen && searchInput) {
      setTimeout(() => searchInput.focus(), 100);
    }
  });
  
  // Close on outside click
  document.addEventListener('click', (e) => {
    if (!dropdown.contains(e.target) && e.target !== btn) {
      isOpen = false;
      dropdown.classList.add('hidden');
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
    const buttons = modelList.querySelectorAll('button[data-model]');
    const headers = modelList.querySelectorAll('div[data-provider-header]');
    
    buttons.forEach(btn => {
      const model = btn.dataset.model.toLowerCase();
      const provider = btn.dataset.provider.toLowerCase();
      const matches = model.includes(query) || provider.includes(query);
      btn.style.display = matches ? '' : 'none';
    });
    
    // Hide provider headers if all their models are hidden
    headers.forEach(header => {
      const providerName = header.dataset.providerHeader;
      const visibleModels = modelList.querySelectorAll(`button[data-provider="${providerName}"]:not([style*="display: none"])`);
      header.style.display = visibleModels.length > 0 ? '' : 'none';
    });
  }
  
  // Add Key button opens settings panel with admin (API Keys) tab
  if (addProviderBtn) {
    addProviderBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      // Close the model dropdown
      isOpen = false;
      dropdown.classList.add('hidden');
      // Open settings with admin (API Keys) tab
      openSettings('admin');
    });
  }
  
  // Load models from API (exposed globally for use by key management)
  async function loadModels() {
    if (modelList) {
      modelList.innerHTML = '<div class="px-4 py-3 text-sm text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading models...</div>';
    }
    
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
    
    // Sort providers by priority
    const sortedProviders = Object.keys(providers).sort((a, b) => {
      const aIdx = PROVIDER_PRIORITY.indexOf(a);
      const bIdx = PROVIDER_PRIORITY.indexOf(b);
      return (aIdx === -1 ? 999 : aIdx) - (bIdx === -1 ? 999 : bIdx);
    });
    
    if (sortedProviders.length === 0) {
      html = '<div class="px-4 py-3 text-sm text-gray-500">No providers configured</div>';
    } else {
      for (const providerName of sortedProviders) {
        const providerData = providers[providerName];
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
    
    modelList.innerHTML = html;
    
    // Add click handlers
    modelList.querySelectorAll('button[data-provider]').forEach(btn => {
      btn.addEventListener('click', () => selectModel(btn.dataset.provider, btn.dataset.model));
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
        dropdown.classList.add('hidden');
        
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
<?php endif; ?>
