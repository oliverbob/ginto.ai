<?php
/**
 * Settings Panel (Slide-over)
 * Contains Settings, MCP Tools, and API Keys tabs
 */

// Determine login/admin state for settings panel
$isLoggedIn = !empty($_SESSION['user_id']);
$isAdmin = false;
try {
  if (class_exists('Ginto\\Controllers\\UserController') && \Ginto\Controllers\UserController::isAdmin()) {
    $isAdmin = true;
  }
} catch (\Throwable $_) { /* ignore */ }
?>
<!-- Settings Panel (Slide-over) -->
<div id="settings-panel" class="fixed inset-y-0 right-0 w-96 bg-white dark:bg-gray-900 border-l border-gray-200 dark:border-gray-800 transform translate-x-full transition-transform duration-300 z-[60] flex flex-col shadow-2xl">
  <!-- Header with close button -->
  <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-800 flex-shrink-0">
    <h3 class="font-semibold" id="settings-panel-title">Settings</h3>
    <button id="close-settings" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>
  
  <!-- Tabs -->
  <div class="flex border-b border-gray-200 dark:border-gray-800 flex-shrink-0">
    <button id="tab-settings" class="flex-1 px-4 py-3 text-sm font-medium text-indigo-600 dark:text-indigo-400 border-b-2 border-indigo-600 dark:border-indigo-400 transition-colors" data-tab="settings">
      Settings
    </button>
    <!-- MCP tab hidden by default, shown only for admin users -->
    <button id="tab-mcp" class="flex-1 px-4 py-3 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 border-b-2 border-transparent transition-colors <?= empty($isAdmin) ? 'hidden' : '' ?>" data-tab="mcp">
      MCP Tools
    </button>
    <!-- API Keys tab (visible to logged-in users; admin sees full admin view) -->
    <button id="tab-admin" class="flex-1 px-4 py-3 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 border-b-2 border-transparent transition-colors <?= empty($isLoggedIn) ? 'hidden' : '' ?>" data-tab="admin">
      API Keys
    </button>
  </div>
  
  <!-- Tab Content: Settings -->
  <div id="panel-settings" class="flex-1 overflow-y-auto sidebar-scroll p-4 space-y-6">
    <!-- Audio Settings -->
    <div>
      <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Audio</h4>
      <div class="space-y-3">
        <label class="flex items-center justify-between">
          <span class="text-sm">Enable TTS</span>
          <input type="checkbox" id="enable_audio" class="w-4 h-4 rounded bg-gray-200 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-indigo-500 focus:ring-indigo-500">
        </label>
        <button id="stop_audio" class="w-full px-3 py-2 text-sm bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors">
          Stop Audio
        </button>
        <div id="tts_state" class="text-xs text-gray-500">(idle)</div>
      </div>
    </div>
    
    <!-- Speech-to-Text -->
    <div>
      <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Speech-to-Text</h4>
      <div class="space-y-3">
        <div class="flex gap-2">
          <button id="start_stt" class="flex-1 px-3 py-2 text-sm bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition-colors">
            Start Listening
          </button>
          <button id="stop_stt" disabled class="flex-1 px-3 py-2 text-sm bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors disabled:opacity-50">
            Stop
          </button>
        </div>
        <div id="stt_transcript" class="p-3 bg-gray-100 dark:bg-gray-800 rounded-lg text-sm text-gray-600 dark:text-gray-400 min-h-[60px]">(not listening)</div>
        <div id="stt_debug" class="text-xs text-gray-500 dark:text-gray-600">&nbsp;</div>
      </div>
    </div>
    
    <!-- Wake Word -->
    <div>
      <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Wake Word</h4>
      <div class="space-y-3">
        <button id="train_wake" class="w-full px-3 py-2 text-sm bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors">
          Train Wake Word
        </button>
        <label class="flex items-center justify-between">
          <span class="text-sm">Enable wake-word</span>
          <input type="checkbox" id="enable_wake" class="w-4 h-4 rounded bg-gray-200 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-indigo-500 focus:ring-indigo-500">
        </label>
        <div id="wake_status" class="text-xs text-gray-500">(wake not trained)</div>
      </div>
    </div>
    
    <!-- Tools -->
    <div>
      <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Tools</h4>
      <div id="auto-run-tools-container" class="mb-3">
        <!-- Auto-run tools toggle will be injected by JS -->
      </div>
    </div>
    
    <!-- Chat Controls -->
    <div>
      <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Chat</h4>
      <div class="space-y-2">
        <button id="describe_repo" class="w-full px-3 py-2 text-sm bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors text-left">
          📁 Describe Repository
        </button>
        <button id="reset_history" class="w-full px-3 py-2 text-sm bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors text-left">
          🗑️ Reset History
        </button>
        <button id="clear" class="w-full px-3 py-2 text-sm bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors text-left">
          ✨ Clear Display
        </button>
      </div>
    </div>
    
    <!-- Sandbox Controls (only shown if user has sandbox) -->
    <div id="sandbox-controls-section" class="hidden">
      <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Sandbox</h4>
      <div class="space-y-2">
        <button id="destroy_sandbox_btn" class="w-full px-3 py-2 text-sm bg-red-100 dark:bg-red-900/30 hover:bg-red-200 dark:hover:bg-red-900/50 text-red-700 dark:text-red-400 rounded-lg transition-colors text-left">
          🛑 Destroy Sandbox
        </button>
      </div>
    </div>
  </div>
  
  <!-- Tab Content: MCP (admin only) -->
  <div id="panel-mcp" class="flex-1 overflow-y-auto sidebar-scroll p-4 hidden" data-admin-only="true">
    <!-- MCP Status Header -->
    <div class="flex items-center gap-2 mb-4 p-3 bg-gray-100 dark:bg-gray-800 rounded-lg">
      <div class="w-3 h-3 rounded-full bg-gray-400" id="mcp-status-dot-panel"></div>
      <span class="font-medium">MCP Status:</span>
      <span id="mcp-status-text" class="text-gray-500 dark:text-gray-400">Checking...</span>
    </div>
    
    <!-- Enable/Disable All Tools -->
    <div class="mb-4 flex gap-2">
      <button id="mcp-enable-all" class="flex-1 px-3 py-2 text-sm bg-green-100 dark:bg-green-900/30 hover:bg-green-200 dark:hover:bg-green-900/50 text-green-700 dark:text-green-400 rounded-lg transition-colors">
        Enable All
      </button>
      <button id="mcp-disable-all" class="flex-1 px-3 py-2 text-sm bg-red-100 dark:bg-red-900/30 hover:bg-red-200 dark:hover:bg-red-900/50 text-red-700 dark:text-red-400 rounded-lg transition-colors">
        Disable All
      </button>
    </div>
    
    <!-- MCP Tools List with Toggles -->
    <div>
      <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Available Tools</h4>
      <div id="mcp-capabilities" class="space-y-2 text-sm">
        <div class="text-gray-500">Loading MCP tools...</div>
      </div>
    </div>
    
    <!-- Copy JSON Button -->
    <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-800">
      <button id="mcp-copy-json" class="w-full px-3 py-2 text-sm bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors">
        📋 Copy Discovery JSON
      </button>
    </div>
  </div>
  
  <!-- Tab Content: API Keys (visible to logged-in users; some sections admin-only) -->
  <div id="panel-admin" class="flex-1 overflow-y-auto sidebar-scroll p-4 hidden" <?= empty($isLoggedIn) ? 'data-admin-only="true"' : '' ?> >
    <!-- Add New Key Form -->
    <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
      <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Add New API Key</h4>
      <form id="add-api-key-form" class="space-y-3">
        <div>
          <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Provider</label>
          <select name="provider" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200">
            <option value="cerebras">Cerebras</option>
            <option value="groq">Groq</option>
            <option value="ginto_tunnel">Ginto Tunnel</option>
            <option value="ollama">Ollama</option>
            <option value="openai">OpenAI</option>
            <option value="anthropic">Anthropic</option>
            <option value="together">Together</option>
            <option value="fireworks">Fireworks</option>
          </select>
        </div>
        <div id="ginto-tunnel-base-url-wrap" class="hidden">
          <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Endpoint Domain (OpenAI-compatible)</label>
          <input type="text" name="base_url" placeholder="ollama.ginto.ai" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200" autocapitalize="off" spellcheck="false" inputmode="url">
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Place your Ginto Tunnel address here or your openai compatible api link.</p>
        </div>
        <div>
          <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Key Name</label>
          <input type="text" name="key_name" placeholder="e.g., Production Key 2" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200">
        </div>
        <div>
          <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">API Key</label>
          <input type="password" name="api_key" required placeholder="gsk_... or csk-..." class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200">
        </div>
        <div>
          <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Tier</label>
          <select name="tier" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200">
            <option value="basic">Basic (Free)</option>
            <option value="production">Production (Paid)</option>
          </select>
        </div>
        <div class="flex items-center gap-4">
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_default" class="w-4 h-4 rounded">
            <span>Set as default</span>
          </label>
        </div>
        <button type="submit" class="w-full px-3 py-2 text-sm bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition-colors">
          Add API Key
        </button>
      </form>
    </div>
    
    <!-- Existing Keys List -->
    <div>
      <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Existing API Keys</h4>
      <div id="api-keys-list" class="space-y-2">
        <div class="text-gray-500 text-sm">Loading keys...</div>
      </div>
    </div>
  </div>
</div>

<!-- Settings Overlay -->
<div id="settings-overlay" class="fixed inset-0 bg-black/50 z-30 hidden" onclick="closeSettings()"></div>

<!-- Hidden elements for JS compatibility -->
<input type="hidden" id="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
<script>window.CSRF_TOKEN = <?= json_encode($csrf_token ?? '') ?>;</script>
