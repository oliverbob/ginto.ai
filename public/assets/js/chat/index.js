/**
 * Chat Module Index
 * Main entry point with lazy loading support
 * 
 * Usage:
 *   import { initChat } from '/assets/js/chat/index.js';
 *   initChat();
 * 
 * Or individual modules:
 *   import { simpleMarkdownToHtml } from '/assets/js/chat/markdown.js';
 */

// ============ MODULE REGISTRY ============
const modules = {
  core: null,
  markdown: null,
  codeBlocks: null,
  conversation: null,
  streaming: null,
  tools: null,
  attachments: null,
  audio: null
};

// ============ LAZY LOADING ============

/**
 * Load a module on demand
 */
async function loadModule(name) {
  if (modules[name]) return modules[name];
  
  const moduleMap = {
    core: () => import('./core.js'),
    markdown: () => import('./markdown.js'),
    codeBlocks: () => import('./code-blocks.js'),
    conversation: () => import('./conversation.js'),
    streaming: () => import('./streaming.js'),
    tools: () => import('./tools.js'),
    attachments: () => import('./attachments.js'),
    audio: () => import('./audio.js')
  };
  
  const loader = moduleMap[name];
  if (!loader) throw new Error(`Unknown module: ${name}`);
  
  modules[name] = await loader();
  return modules[name];
}

/**
 * Get a module (sync - must be loaded first)
 */
function getModule(name) {
  if (!modules[name]) {
    console.warn(`[Chat] Module ${name} not loaded yet`);
    return null;
  }
  return modules[name];
}

// ============ CORE EXPORTS (always available) ============

// Re-export commonly used functions that are safe to load immediately
export { escapeHtml, copyToClipboard, debounce, tryParseJsonSafe, smartScrollToElement } from './core.js';
export { simpleMarkdownToHtml, renderLatexInElement, stripToolCallJson } from './markdown.js';
export { enhanceCodeBlocks, initStickyCodeButtons } from './code-blocks.js';
export { formatToolResult, executeToolCall } from './tools.js';

// ============ LAZY ACCESSORS ============

/**
 * Get core module functions
 */
export async function getCore() {
  return await loadModule('core');
}

/**
 * Get markdown module functions
 */
export async function getMarkdown() {
  return await loadModule('markdown');
}

/**
 * Get code blocks module functions
 */
export async function getCodeBlocks() {
  return await loadModule('codeBlocks');
}

/**
 * Get conversation module functions
 */
export async function getConversation() {
  return await loadModule('conversation');
}

/**
 * Get streaming module functions
 */
export async function getStreaming() {
  return await loadModule('streaming');
}

/**
 * Get tools module functions
 */
export async function getTools() {
  return await loadModule('tools');
}

/**
 * Get attachments module functions
 */
export async function getAttachments() {
  return await loadModule('attachments');
}

/**
 * Get audio module functions
 */
export async function getAudio() {
  return await loadModule('audio');
}

// ============ INITIALIZATION ============

/**
 * Initialize all chat modules
 */
export async function initChat(options = {}) {
  console.log('[Chat] Initializing modular chat system...');
  
  // Load essential modules
  const [core, markdown, codeBlocks, conversation, streaming, tools, attachments, audio] = await Promise.all([
    loadModule('core'),
    loadModule('markdown'),
    loadModule('codeBlocks'),
    loadModule('conversation'),
    loadModule('streaming'),
    loadModule('tools'),
    loadModule('attachments'),
    loadModule('audio')
  ]);
  
  console.log('[Chat] All modules loaded');
  
  // Get DOM elements
  const promptEl = document.getElementById('prompt') || document.getElementById('composer-input');
  const sendBtn = document.getElementById('send-btn') || document.getElementById('composer-send');
  
  // Initialize conversation state
  conversation.initConversationState();
  
  // Set up attachment handlers
  attachments.setupAttachmentHandlers(promptEl);
  
  // Set up audio (TTS/STT)
  const ttsManager = audio.createTTSManager();
  const sttManager = audio.createSTTManager();
  audio.setupTTSControls(ttsManager);
  audio.setupSTTControls(sttManager, promptEl);
  
  // Initialize conversation
  await conversation.initializeChat(
    () => renderMessages(conversation, markdown, codeBlocks, streaming, tools),
    () => renderConvoList(conversation)
  );
  
  // Set up persistence listeners
  conversation.setupPersistenceListeners(
    () => renderConvoList(conversation),
    () => renderMessages(conversation, markdown, codeBlocks, streaming, tools)
  );
  
  // Bind logout clear
  conversation.bindLogoutClear();
  
  // Wire send button
  if (sendBtn && promptEl) {
    sendBtn.addEventListener('click', async (e) => {
      try {
        const abortController = streaming.getAbortController();
        
        // If streaming, abort instead of sending
        if (sendBtn._isStreaming && abortController) {
          abortController.abort();
          return;
        }
        
        const prompt = (promptEl.value || '').trim();
        if (!prompt) return;
        
        promptEl.value = '';
        await streamWebSearch(prompt, conversation, streaming, tools, attachments, ttsManager);
      } catch (e) {
        console.error('[Chat] Send error:', e);
      }
    });
  }
  
  // Escape to cancel
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const abortController = streaming.getAbortController();
      if (abortController) abortController.abort();
    }
  });
  
  // New chat button
  const newChatBtn = document.getElementById('new-chat-btn');
  if (newChatBtn) {
    newChatBtn.addEventListener('click', () => {
      conversation.newConvo(
        () => renderConvoList(conversation),
        () => renderMessages(conversation, markdown, codeBlocks, streaming, tools)
      );
    });
  }
  
  // Search input
  const searchEl = document.getElementById('convo-search');
  if (searchEl) {
    const debouncedSearch = core.debounce((e) => {
      conversation.performSearch(
        e.target.value,
        () => renderConvoList(conversation),
        (id) => conversation.switchConvo(id, 
          () => renderConvoList(conversation),
          () => renderMessages(conversation, markdown, codeBlocks, streaming, tools)
        ),
        (id) => conversation.deleteConvo(id,
          () => renderConvoList(conversation),
          () => renderMessages(conversation, markdown, codeBlocks, streaming, tools),
          () => conversation.newConvo(
            () => renderConvoList(conversation),
            () => renderMessages(conversation, markdown, codeBlocks, streaming, tools)
          )
        ),
        window.showConfirmModal
      );
    }, 250);
    searchEl.addEventListener('input', debouncedSearch);
  }
  
  // Initial render
  setTimeout(() => {
    const container = document.getElementById('conversation-container') || 
                     document.getElementById('chat-container');
    if (container) {
      container.querySelectorAll('.card-response').forEach(el => {
        markdown.renderLatexInElement(el);
      });
    }
  }, 500);
  
  console.log('[Chat] Initialization complete');
  
  return {
    modules,
    conversation,
    streaming,
    tools,
    attachments,
    audio: { tts: ttsManager, stt: sttManager }
  };
}

// ============ INTERNAL HELPERS ============

/**
 * Render all messages
 */
function renderMessages(conversation, markdown, codeBlocks, streaming, tools) {
  const container = streaming.getConvoContainer();
  if (!container) return;
  
  const { history } = conversation.conversationState;
  
  // Clear container
  container.innerHTML = '';
  
  if (history.length === 0) {
    container.innerHTML = '<div class="empty-state text-center py-12 text-gray-500">Start a conversation by typing a message below.</div>';
    return;
  }
  
  // Render each message pair
  for (let i = 0; i < history.length; i++) {
    const msg = history[i];
    
    if (msg.role === 'user') {
      // Find matching assistant message
      const assistantMsg = history[i + 1]?.role === 'assistant' ? history[i + 1] : null;
      
      const card = createPersistedCard(msg, assistantMsg, conversation, markdown, codeBlocks, streaming, tools);
      container.appendChild(card);
      
      if (assistantMsg) i++; // Skip the assistant message we just rendered
    }
  }
  
  // Enhance code blocks
  codeBlocks.enhanceCodeBlocks(container);
  codeBlocks.initStickyCodeButtons();
  
  // Scroll to bottom
  container.scrollTop = container.scrollHeight;
}

/**
 * Create a persisted message card
 */
function createPersistedCard(userMsg, assistantMsg, conversation, markdown, codeBlocks, streaming, tools) {
  const card = document.createElement('div');
  card.className = 'messenger-pair';
  
  // User message
  const userContent = userMsg.content || '';
  const userHtml = `
    <div class="messenger-row messenger-row-user" style="display:flex;flex-direction:column;align-items:flex-end;width:100%;margin-bottom:8px;">
      ${userMsg.imageUrl ? `<div class="card-user-image" style="max-width:70%;margin-bottom:8px;">
        <img src="${userMsg.imageUrl}" alt="Attached image" class="max-w-xs max-h-48 rounded-lg border border-gray-600 cursor-pointer hover:opacity-90 transition-opacity" onclick="window.showImageModal && window.showImageModal(this.src)">
      </div>` : ''}
      <div class="messenger-bubble messenger-bubble-user">
        <div class="user-message-content" style="white-space:pre-wrap;">${escapeHtmlBasic(userContent)}</div>
      </div>
    </div>
  `;
  
  // Assistant message
  let assistantHtml = '';
  if (assistantMsg) {
    let responseHtml = assistantMsg.html || '';
    if (!responseHtml && assistantMsg.content) {
      responseHtml = markdown.simpleMarkdownToHtml(assistantMsg.content);
    }
    
    // Render tool results if present
    let toolResultsHtml = '';
    if (assistantMsg.toolResults && assistantMsg.toolResults.length > 0) {
      toolResultsHtml = assistantMsg.toolResults.map(tr => 
        `<div class="mt-3 tool-result-formatted">${tools.formatToolResult(tr.name, tr.result)}</div>`
      ).join('');
    }
    
    assistantHtml = `
      <div class="convo-card-body">
        ${assistantMsg.reasoning ? `
          <div class="card-reasoning mb-3">
            <div class="reasoning-timeline">
              <div class="reasoning-header card-reasoning-toggle">
                <svg class="reasoning-chevron" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
                <span>Reasoning</span>
              </div>
              <div class="card-reasoning-content reasoning-content modern-scroll" style="display:none;">${streaming.formatReasoningText(assistantMsg.reasoning)}</div>
            </div>
          </div>
        ` : ''}
        <div class="card-response prose">${toolResultsHtml}${responseHtml}</div>
      </div>
    `;
  }
  
  card.innerHTML = userHtml + assistantHtml;
  
  return card;
}

/**
 * Render conversation list in sidebar
 */
function renderConvoList(conversation) {
  const el = document.getElementById('conversation-list');
  if (!el) return;
  
  const { allConvos, activeConvoId } = conversation.conversationState;
  
  const list = Object.values(allConvos)
    .filter(c => c.messages && c.messages.length > 0)
    .sort((a, b) => {
      if (a.pinned && !b.pinned) return -1;
      if (!a.pinned && b.pinned) return 1;
      return (b.ts || 0) - (a.ts || 0);
    });
  
  if (list.length === 0) {
    el.innerHTML = '<div class="text-sm text-gray-500 py-4 text-center">No conversations yet</div>';
    return;
  }
  
  el.innerHTML = list.map(c => `
    <div class="convo-item group flex items-center gap-2 px-4 py-2 cursor-pointer transition-colors text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 hover:text-indigo-700 dark:hover:text-indigo-300 text-sm ${c.id === activeConvoId ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300' : ''}" data-id="${c.id}">
      <svg class="w-4 h-4 flex-shrink-0 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
      <span class="sidebar-label flex-1 truncate">${escapeHtmlBasic(c.title)}</span>
    </div>
  `).join('');
  
  // Click handlers
  el.querySelectorAll('.convo-item').forEach(item => {
    item.addEventListener('click', () => {
      conversation.switchConvo(
        item.dataset.id,
        () => renderConvoList(conversation),
        () => {} // Will be called internally
      );
    });
  });
}

/**
 * Stream web search (simplified - main logic in original chat.js)
 */
async function streamWebSearch(query, conversation, streaming, tools, attachments, ttsManager) {
  // This is a placeholder - the full implementation would be in streaming.js
  // For now, defer to the original chat.js implementation
  console.log('[Chat] streamWebSearch called with:', query);
}

/**
 * Basic HTML escape
 */
function escapeHtmlBasic(text) {
  return String(text || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

// ============ GLOBAL EXPOSURE ============

// Expose for legacy compatibility
if (typeof window !== 'undefined') {
  window.ChatModules = {
    loadModule,
    getModule,
    initChat,
    getCore,
    getMarkdown,
    getCodeBlocks,
    getConversation,
    getStreaming,
    getTools,
    getAttachments,
    getAudio
  };
}
