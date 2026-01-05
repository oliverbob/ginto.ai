/**
 * Chat Module Loader (Modular Edition)
 * 
 * This file provides a compatibility bridge between:
 * - The original monolithic chat.js (IIFE pattern)
 * - The new modular ES6 architecture in /assets/js/chat/
 * 
 * Usage options:
 * 
 * 1. ES6 Modules (recommended):
 *    <script type="module">
 *      import { initChat } from '/assets/js/chat/index.js';
 *      initChat();
 *    </script>
 * 
 * 2. Legacy (backward compatible):
 *    <script src="/assets/js/chat-loader.js"></script>
 * 
 * 3. Lazy loading individual modules:
 *    const markdown = await ChatModules.loadModule('markdown');
 *    markdown.simpleMarkdownToHtml(text);
 */

(function() {
  'use strict';
  
  // ============ MODULE LOADER ============
  const MODULES_BASE = '/assets/js/chat';
  
  const moduleCache = {};
  const pendingLoads = {};
  
  /**
   * Dynamically load an ES6 module
   */
  async function loadModule(name) {
    // Return cached module
    if (moduleCache[name]) {
      return moduleCache[name];
    }
    
    // Return pending promise if already loading
    if (pendingLoads[name]) {
      return pendingLoads[name];
    }
    
    // Map module names to file paths
    const moduleMap = {
      core: 'core.js',
      markdown: 'markdown.js',
      codeBlocks: 'code-blocks.js',
      conversation: 'conversation.js',
      streaming: 'streaming.js',
      tools: 'tools.js',
      attachments: 'attachments.js',
      audio: 'audio.js',
      index: 'index.js'
    };
    
    const fileName = moduleMap[name];
    if (!fileName) {
      throw new Error(`Unknown module: ${name}`);
    }
    
    const url = `${MODULES_BASE}/${fileName}`;
    
    // Start loading
    pendingLoads[name] = import(url).then(mod => {
      moduleCache[name] = mod;
      delete pendingLoads[name];
      console.log(`[ChatLoader] Loaded module: ${name}`);
      return mod;
    }).catch(err => {
      delete pendingLoads[name];
      console.error(`[ChatLoader] Failed to load module ${name}:`, err);
      throw err;
    });
    
    return pendingLoads[name];
  }
  
  /**
   * Get a loaded module synchronously
   */
  function getModule(name) {
    return moduleCache[name] || null;
  }
  
  /**
   * Check if a module is loaded
   */
  function isModuleLoaded(name) {
    return !!moduleCache[name];
  }
  
  /**
   * Load all essential modules
   */
  async function loadEssentials() {
    console.log('[ChatLoader] Loading essential modules...');
    
    await Promise.all([
      loadModule('core'),
      loadModule('markdown'),
      loadModule('codeBlocks'),
      loadModule('streaming')
    ]);
    
    console.log('[ChatLoader] Essential modules loaded');
  }
  
  /**
   * Load all modules
   */
  async function loadAll() {
    console.log('[ChatLoader] Loading all modules...');
    
    await Promise.all([
      loadModule('core'),
      loadModule('markdown'),
      loadModule('codeBlocks'),
      loadModule('conversation'),
      loadModule('streaming'),
      loadModule('tools'),
      loadModule('attachments'),
      loadModule('audio')
    ]);
    
    console.log('[ChatLoader] All modules loaded');
    return moduleCache;
  }
  
  /**
   * Initialize the chat system using modules
   */
  async function initChat(options = {}) {
    // Load the main index module
    const indexModule = await loadModule('index');
    
    // Call its init
    return await indexModule.initChat(options);
  }
  
  /**
   * Expose commonly used functions globally for legacy compatibility
   */
  async function exposeGlobals() {
    const core = await loadModule('core');
    const markdown = await loadModule('markdown');
    const codeBlocks = await loadModule('codeBlocks');
    const tools = await loadModule('tools');
    
    // Expose to window for inline onclick handlers
    window.copyToClipboard = core.copyToClipboard;
    window.escapeHtml = core.escapeHtml;
    window.simpleMarkdownToHtml = markdown.simpleMarkdownToHtml;
    window.renderLatexInElement = markdown.renderLatexInElement;
    window.enhanceCodeBlocks = codeBlocks.enhanceCodeBlocks;
    window.formatToolResult = tools.formatToolResult;
    
    console.log('[ChatLoader] Global functions exposed');
  }
  
  // ============ AUTO-INIT ============
  
  /**
   * Auto-initialize when DOM is ready
   */
  function autoInit() {
    // Check if user wants to use modules or legacy
    const useModules = document.querySelector('script[data-chat-modules]') !== null ||
                       document.querySelector('[data-use-chat-modules]') !== null;
    
    if (useModules) {
      console.log('[ChatLoader] Modular mode enabled');
      initChat().catch(err => {
        console.error('[ChatLoader] Init failed:', err);
      });
    } else {
      console.log('[ChatLoader] Legacy mode - modules available on demand');
      // Just expose the loader API
    }
  }
  
  // ============ EXPOSE API ============
  
  window.ChatModules = {
    // Module loading
    loadModule,
    getModule,
    isModuleLoaded,
    loadEssentials,
    loadAll,
    
    // Initialization
    initChat,
    exposeGlobals,
    
    // Meta
    BASE_URL: MODULES_BASE,
    cache: moduleCache
  };
  
  // Auto-init on DOMContentLoaded if script has auto attribute
  if (document.currentScript?.hasAttribute('data-auto-init')) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', autoInit);
    } else {
      autoInit();
    }
  }
  
  console.log('[ChatLoader] Ready. Use ChatModules.loadModule() or ChatModules.initChat()');
})();
