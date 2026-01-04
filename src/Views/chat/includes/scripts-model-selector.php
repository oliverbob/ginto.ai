<?php
/**
 * Admin Model Selector Scripts
 * For admin users to select and configure AI models
 */
?>
<?php if ($isAdmin ?? false): ?>
<script>
  // ========================================
  // Admin Model Selector
  // ========================================
  const modelSelectorContainer = document.getElementById('model-selector-admin');
  const modelDropdown = document.getElementById('model-dropdown');
  const modelDropdownBtn = document.getElementById('model-dropdown-btn');
  const modelDropdownList = document.getElementById('model-dropdown-list');
  const selectedModelName = document.getElementById('selected-model-name');
  const selectedModelIcon = document.getElementById('selected-model-icon');
  
  let availableModels = [];
  let selectedModel = null;
  let modelDropdownOpen = false;
  
  async function loadModels() {
    try {
      const csrfToken = window.GINTO_AUTH?.csrfToken || '';
      const response = await fetch('/api/models/list', {
        method: 'GET',
        headers: { 'X-CSRF-Token': csrfToken },
        credentials: 'same-origin'
      });
      
      const data = await response.json();
      
      if (!response.ok || !data.success) {
        console.warn('[Models] Failed to load models:', data.error);
        return;
      }
      
      availableModels = data.models || [];
      
      // Set default model if not already set
      if (!selectedModel && availableModels.length > 0) {
        // Try to find a default model
        const defaultModel = availableModels.find(m => m.is_default) || availableModels[0];
        selectModel(defaultModel);
      }
      
      renderModels();
      
    } catch (err) {
      console.error('[Models] Error loading models:', err);
    }
  }
  
  function renderModels() {
    if (!modelDropdownList) return;
    
    if (availableModels.length === 0) {
      modelDropdownList.innerHTML = `
        <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
          No models available
        </div>
      `;
      return;
    }
    
    let html = '';
    
    // Group models by provider
    const providers = {};
    availableModels.forEach(model => {
      const provider = model.provider || 'Other';
      if (!providers[provider]) {
        providers[provider] = [];
      }
      providers[provider].push(model);
    });
    
    Object.keys(providers).sort().forEach(provider => {
      html += `
        <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800/50">
          ${escapeHtml(provider)}
        </div>
      `;
      
      providers[provider].forEach(model => {
        const isSelected = selectedModel && selectedModel.id === model.id;
        const iconClass = getModelIcon(model);
        
        html += `
          <button class="model-option w-full flex items-center gap-3 px-4 py-2.5 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors ${isSelected ? 'bg-indigo-50 dark:bg-indigo-900/20' : ''}"
                  data-model-id="${escapeHtml(model.id)}"
                  onclick="selectModelById('${escapeHtml(model.id)}')">
            <i class="${iconClass} text-lg ${isSelected ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-400 dark:text-gray-500'}"></i>
            <div class="flex-1 text-left">
              <div class="text-sm font-medium ${isSelected ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-900 dark:text-white'}">
                ${escapeHtml(model.display_name || model.name || model.id)}
              </div>
              ${model.description ? `
              <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                ${escapeHtml(model.description)}
              </div>
              ` : ''}
            </div>
            ${model.is_default ? `
            <span class="px-1.5 py-0.5 text-xs bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded">Default</span>
            ` : ''}
            ${isSelected ? `<i class="fas fa-check text-indigo-600 dark:text-indigo-400"></i>` : ''}
          </button>
        `;
      });
    });
    
    modelDropdownList.innerHTML = html;
  }
  
  function getModelIcon(model) {
    const provider = (model.provider || '').toLowerCase();
    const modelId = (model.id || '').toLowerCase();
    
    if (provider === 'openai' || modelId.includes('gpt')) {
      return 'fas fa-robot';
    } else if (provider === 'anthropic' || modelId.includes('claude')) {
      return 'fas fa-brain';
    } else if (provider === 'google' || modelId.includes('gemini')) {
      return 'fab fa-google';
    } else if (provider === 'ollama') {
      return 'fas fa-server';
    } else if (provider === 'groq') {
      return 'fas fa-bolt';
    } else if (modelId.includes('llama')) {
      return 'fas fa-horse';
    } else if (modelId.includes('mistral')) {
      return 'fas fa-wind';
    } else {
      return 'fas fa-microchip';
    }
  }
  
  function selectModel(model) {
    selectedModel = model;
    
    // Update UI
    if (selectedModelName) {
      selectedModelName.textContent = model.display_name || model.name || model.id;
    }
    if (selectedModelIcon) {
      selectedModelIcon.className = getModelIcon(model) + ' text-gray-500 dark:text-gray-400';
    }
    
    // Update global config
    if (window.GINTO_MODEL_CAPABILITIES) {
      window.GINTO_MODEL_CAPABILITIES.currentModel = model.id;
    }
    
    // Store in session storage for persistence
    try {
      sessionStorage.setItem('ginto_selected_model', model.id);
    } catch (e) {}
    
    // Close dropdown
    closeModelDropdown();
    
    // Update capability UI (vision, code, etc.)
    updateCapabilityUI(model);
    
    console.log('[Models] Selected model:', model.id);
  }
  
  function selectModelById(modelId) {
    const model = availableModels.find(m => m.id === modelId);
    if (model) {
      selectModel(model);
    }
  }
  
  function toggleModelDropdown() {
    if (modelDropdownOpen) {
      closeModelDropdown();
    } else {
      openModelDropdown();
    }
  }
  
  function openModelDropdown() {
    if (modelDropdown) {
      modelDropdown.classList.remove('hidden');
      modelDropdownOpen = true;
    }
  }
  
  function closeModelDropdown() {
    if (modelDropdown) {
      modelDropdown.classList.add('hidden');
      modelDropdownOpen = false;
    }
  }
  
  function updateCapabilityUI(model) {
    // Update vision capability indicator
    const visionIndicator = document.getElementById('capability-vision');
    if (visionIndicator) {
      if (model.capabilities?.vision || model.supports_vision) {
        visionIndicator.classList.remove('hidden');
        visionIndicator.title = 'This model supports image analysis';
      } else {
        visionIndicator.classList.add('hidden');
      }
    }
    
    // Update code capability indicator
    const codeIndicator = document.getElementById('capability-code');
    if (codeIndicator) {
      if (model.capabilities?.code || model.supports_code) {
        codeIndicator.classList.remove('hidden');
        codeIndicator.title = 'This model excels at code generation';
      } else {
        codeIndicator.classList.add('hidden');
      }
    }
    
    // Update context length indicator
    const contextIndicator = document.getElementById('capability-context');
    if (contextIndicator && model.context_length) {
      contextIndicator.classList.remove('hidden');
      contextIndicator.textContent = Math.floor(model.context_length / 1000) + 'K';
      contextIndicator.title = `${model.context_length.toLocaleString()} token context window`;
    }
  }
  
  // Event listeners
  modelDropdownBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    toggleModelDropdown();
  });
  
  // Close dropdown on outside click
  document.addEventListener('click', (e) => {
    if (modelDropdownOpen && modelSelectorContainer && !modelSelectorContainer.contains(e.target)) {
      closeModelDropdown();
    }
  });
  
  // Escape to close
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modelDropdownOpen) {
      closeModelDropdown();
    }
  });
  
  // Load models on page load (admin only)
  if (modelSelectorContainer) {
    loadModels();
    
    // Restore previously selected model
    try {
      const savedModelId = sessionStorage.getItem('ginto_selected_model');
      if (savedModelId) {
        // Will be applied after models are loaded
        setTimeout(() => {
          const model = availableModels.find(m => m.id === savedModelId);
          if (model) {
            selectModel(model);
          }
        }, 500);
      }
    } catch (e) {}
  }
</script>
<?php endif; ?>
