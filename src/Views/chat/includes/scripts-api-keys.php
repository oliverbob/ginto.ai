<?php
/**
 * API Keys Management Scripts
 */
?>
<script>
  // ============= API Keys Management =============
  function normalizeBaseUrl(url) {
    const raw = (url || '').trim();
    if (!raw) return '';
    return raw.endsWith('/') ? raw : raw + '/';
  }

  function toggleGintoTunnelFields(form) {
    if (!form) return;
    const providerSelect = form.querySelector('select[name="provider"]');
    const wrapper = document.getElementById('ginto-tunnel-base-url-wrap');
    const baseUrlInput = form.querySelector('input[name="base_url"]');
    if (!providerSelect || !wrapper || !baseUrlInput) return;

    const selectedProvider = (providerSelect.value || '').toLowerCase();
    const enabled = selectedProvider === 'ginto_tunnel';
    wrapper.classList.toggle('hidden', !enabled);
    if (enabled) {
      if (!baseUrlInput.value.trim()) {
        baseUrlInput.value = 'https://ollama.ginto.ai/v1/';
      }
      baseUrlInput.setAttribute('required', 'required');
    } else {
      baseUrlInput.removeAttribute('required');
      baseUrlInput.value = '';
    }
  }

  const addApiKeyForm = document.getElementById('add-api-key-form');
  const providerSelectEl = addApiKeyForm?.querySelector('select[name="provider"]');
  if (providerSelectEl) {
    providerSelectEl.addEventListener('change', () => toggleGintoTunnelFields(addApiKeyForm));
  }
  toggleGintoTunnelFields(addApiKeyForm);

  async function loadApiKeys() {
    const list = document.getElementById('api-keys-list');
    if (!list) return;
    
    try {
      const res = await fetch('/api/provider-keys', { credentials: 'same-origin' });
      const data = await res.json();
      
      if (!data.success || !data.keys || data.keys.length === 0) {
        list.innerHTML = '<div class="text-gray-500 text-sm">No API keys configured.</div>';
        return;
      }
      
      list.innerHTML = data.keys.map(key => `
        <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700" data-key-id="${key.id}">
          <div class="flex items-center justify-between mb-2 flex-wrap gap-1">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-medium text-sm">${escapeHtml(key.key_name || 'Unnamed')}</span>
              ${key.is_default ? '<span class="text-xs bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 px-2 py-0.5 rounded">Default</span>' : ''}
            </div>
            <div class="flex items-center gap-2">
              <span class="text-xs px-2 py-0.5 rounded flex-shrink-0 ${key.tier === 'production' ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400'}">${key.tier}</span>
              <button onclick="deleteApiKey(${key.id}, '${escapeHtml(key.key_name || 'this key')}')" class="p-1 rounded text-red-500 hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors" title="Delete key">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
              </button>
            </div>
          </div>
          <div class="text-xs font-mono text-gray-500 dark:text-gray-400 mb-1 break-all">${escapeHtml(key.api_key_masked)}</div>
          ${key.base_url ? `<div class="text-xs text-blue-500 dark:text-blue-300 mb-1 break-all">${escapeHtml(normalizeBaseUrl(key.base_url))}</div>` : ''}
          <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
            <span>${key.provider}</span>
            <span class="text-gray-400">•</span>
            <span>ID: ${key.id}</span>
          </div>
          ${key.rate_limit_reset_at ? `<div class="text-xs text-amber-600 dark:text-amber-400 mt-2">⚠️ Rate limited until ${new Date(key.rate_limit_reset_at).toLocaleTimeString()}</div>` : ''}
          ${key.error_count > 0 ? `<div class="text-xs text-red-500 mt-1">Errors: ${key.error_count}</div>` : ''}
          ${key.last_used_at ? `<div class="text-xs text-gray-400 mt-1">Last used: ${new Date(key.last_used_at).toLocaleString()}</div>` : ''}
        </div>
      `).join('');
    } catch (e) {
      list.innerHTML = '<div class="text-red-500 text-sm">Failed to load API keys.</div>';
    }
  }
  
  function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  
  // Delete API key function
  async function deleteApiKey(id, name) {
    const confirmed = await showConfirmModal({
      title: 'Delete API Key',
      message: `Are you sure you want to delete "${name}"? This action cannot be undone.`,
      confirmText: 'Delete',
      type: 'danger'
    });
    
    if (!confirmed) return;
    
    try {
      const res = await fetch('/api/provider-keys', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.GINTO_AUTH.csrfToken || window.CSRF_TOKEN || ''
        },
        body: JSON.stringify({ action: 'delete', id }),
        credentials: 'same-origin'
      });
      const data = await res.json();
      if (data.success) {
        loadApiKeys();
        showToast('API key deleted successfully', 'success');
      } else {
        showToast(data.error || 'Failed to delete API key', 'error');
      }
    } catch (e) {
      console.error('Delete API key error:', e);
      showToast('Failed to delete API key', 'error');
    }
  }
  
  // Add new API key form handler
  document.getElementById('add-api-key-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    
    // Get CSRF token from multiple sources
    const csrfToken = window.GINTO_AUTH?.csrfToken 
      || window.CSRF_TOKEN 
      || document.getElementById('csrf_token')?.value 
      || '';
    
    if (!csrfToken) {
      showToast('Session expired. Please refresh the page.', 'error');
      return;
    }
    
    const provider = String(formData.get('provider') || '').toLowerCase();
    const baseUrl = normalizeBaseUrl(String(formData.get('base_url') || ''));

    try {
      const res = await fetch('/api/provider-keys', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
          action: 'add',
          provider,
          key_name: formData.get('key_name'),
          api_key: formData.get('api_key'),
          base_url: provider === 'ginto_tunnel' ? baseUrl : '',
          tier: formData.get('tier'),
          is_default: formData.get('is_default') === 'on'
        }),
        credentials: 'same-origin'
      });
      const data = await res.json();
      console.log('Add API key response:', data);
      if (data.success) {
        form.reset();
        toggleGintoTunnelFields(form);
        loadApiKeys();
        
        // Refresh the models list to show newly available models
        if (typeof window.refreshModelsList === 'function') {
          await window.refreshModelsList();
          showToast('API key added! Models are now available.', 'success');
        } else {
          showToast('API key added successfully!', 'success');
        }
      } else {
        showToast(data.error || 'Failed to add API key', 'error');
      }
    } catch (e) {
      console.error('Add API key error:', e);
      showToast('Failed to add API key: ' + e.message, 'error');
    }
  });
  
  document.getElementById('close-settings')?.addEventListener('click', closeSettings);
</script>
