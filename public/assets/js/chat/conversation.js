/**
 * Chat Conversation Module
 * Persistence, sync, history management
 */

// ============ CONSTANTS ============
export const STORAGE_KEY = 'ginto_conversations_v2';

// ============ STATE ============
export const conversationState = {
  allConvos: {},
  activeConvoId: null,
  history: []
};

// ============ AUTH HELPERS ============

/**
 * Check if user is logged in
 */
export function isUserLoggedIn() {
  return window.GINTO_AUTH && window.GINTO_AUTH.isLoggedIn && window.GINTO_AUTH.userId;
}

/**
 * Get CSRF token from the page
 */
export function getCsrfToken() {
  if (window.GINTO_AUTH && window.GINTO_AUTH.csrfToken) {
    return window.GINTO_AUTH.csrfToken;
  }
  const input = document.querySelector('input[name="csrf_token"]');
  return input ? input.value : '';
}

// ============ DATABASE SYNC ============

/**
 * Load conversations from database (for logged-in users)
 */
export async function loadConvosFromDb() {
  if (!isUserLoggedIn()) return null;
  try {
    const res = await fetch('/chat/conversations', { credentials: 'same-origin' });
    if (!res.ok) return null;
    const data = await res.json();
    if (data.success && data.convos) {
      console.log('[loadConvosFromDb] Loaded', Object.keys(data.convos).length, 'conversations from DB');
      return data.convos;
    }
  } catch (e) {
    console.warn('[loadConvosFromDb] Error:', e);
  }
  return null;
}

/**
 * Save a single conversation to database (for logged-in users)
 */
export async function saveConvoToDb(convoId, convo) {
  if (!isUserLoggedIn() || !convoId || !convo) return;
  try {
    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('convo_id', convoId);
    formData.append('title', convo.title || 'New chat');
    formData.append('messages', JSON.stringify(convo.messages || []));
    
    const res = await fetch('/chat/conversations/save', {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    });
    if (!res.ok) {
      console.warn('[saveConvoToDb] Failed to save conversation:', convoId);
    }
  } catch (e) {
    console.warn('[saveConvoToDb] Error:', e);
  }
}

/**
 * Delete a conversation from database (for logged-in users)
 */
export async function deleteConvoFromDb(convoId) {
  if (!isUserLoggedIn() || !convoId) return;
  try {
    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('convo_id', convoId);
    
    const res = await fetch('/chat/conversations/delete', {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    });
    if (!res.ok) {
      console.warn('[deleteConvoFromDb] Failed to delete conversation:', convoId);
    }
  } catch (e) {
    console.warn('[deleteConvoFromDb] Error:', e);
  }
}

// ============ LOCAL STORAGE ============

/**
 * Load all conversations from localStorage
 */
export function loadAllConvos() {
  try {
    // Support migration from older storage keys
    const legacyKey = 'ginto_conversations';
    const dataV2 = localStorage.getItem(STORAGE_KEY);
    const dataLegacy = localStorage.getItem(legacyKey);
    if (!dataV2 && dataLegacy) {
      try {
        const parsedLegacy = JSON.parse(dataLegacy);
        if (parsedLegacy && (parsedLegacy.convos || parsedLegacy.activeId)) {
          localStorage.setItem(STORAGE_KEY, JSON.stringify(parsedLegacy));
          console.log('[loadAllConvos] Migrated legacy conversations to', STORAGE_KEY);
        }
      } catch (e) {}
    }
    const data = localStorage.getItem(STORAGE_KEY);
    const parsed = data ? JSON.parse(data) : { convos: {}, activeId: null };
    console.log('[loadAllConvos] Loaded from localStorage:', data ? data.length + ' bytes' : 'empty', 
      'convos:', Object.keys(parsed.convos || {}).length, 
      'activeId:', parsed.activeId);
    return parsed;
  } catch (e) { 
    console.error('[loadAllConvos] Error:', e);
    return { convos: {}, activeId: null }; 
  }
}

/**
 * Save all conversations to localStorage
 */
export function saveAllConvos() {
  try {
    const { allConvos, activeConvoId } = conversationState;
    const data = JSON.stringify({ convos: allConvos, activeId: activeConvoId });
    localStorage.setItem(STORAGE_KEY, data);
    console.log('[saveAllConvos] Saved to localStorage, size:', data.length, 'bytes', 'keys:', Object.keys(allConvos));
  } catch (e) { 
    console.error('Save failed', e); 
  }
}

// ============ DEBOUNCED DB SAVE ============
let dbSaveTimeout = null;

/**
 * Save current conversation to DB (debounced)
 */
export function saveCurrentConvoToDbDebounced() {
  if (!isUserLoggedIn()) return;
  if (dbSaveTimeout) clearTimeout(dbSaveTimeout);
  dbSaveTimeout = setTimeout(() => {
    const { allConvos, activeConvoId } = conversationState;
    if (activeConvoId && allConvos[activeConvoId]) {
      saveConvoToDb(activeConvoId, allConvos[activeConvoId]);
    }
  }, 1000);
}

// ============ TITLE GENERATION ============

/**
 * Generate title from first user message
 */
export function makeTitle(msgs) {
  const first = msgs.find(m => m.role === 'user');
  if (first?.content) {
    return first.content.length > 35 ? first.content.slice(0, 35) + '...' : first.content;
  }
  return 'New chat';
}

// ============ SYNC HELPERS ============

/**
 * Save current history to active conversation
 */
export function syncCurrentConvo(renderConvoList) {
  const { allConvos, activeConvoId, history } = conversationState;
  
  if (!activeConvoId) return;
  if (!allConvos[activeConvoId]) {
    allConvos[activeConvoId] = { id: activeConvoId, title: 'New chat', messages: [], ts: Date.now() };
  }
  console.log('[syncCurrentConvo] Syncing', activeConvoId, 'with', history.length, 'messages');
  
  // Deep copy messages, but strip large base64 imageUrl data
  allConvos[activeConvoId].messages = history.map(m => {
    const copy = {...m};
    if (copy.imageUrl && copy.imageUrl.startsWith('data:')) {
      delete copy.imageUrl;
    }
    return copy;
  });
  allConvos[activeConvoId].title = makeTitle(history);
  allConvos[activeConvoId].ts = Date.now();
  console.log('[syncCurrentConvo] After copy, convo has', allConvos[activeConvoId].messages.length, 'messages');
  
  saveAllConvos();
  saveCurrentConvoToDbDebounced();
  console.log('[syncCurrentConvo] Saved. Total convos:', Object.keys(allConvos).length);
  
  if (typeof renderConvoList === 'function') {
    renderConvoList();
  }
}

/**
 * Create new conversation
 */
export function newConvo(renderConvoList, renderMessages) {
  const { allConvos, history } = conversationState;
  
  console.log('[newConvo] Before sync - allConvos:', Object.keys(allConvos).length, 'history:', history.length);
  
  // Save current first (only if it has messages)
  if (history.length > 0) {
    syncCurrentConvo(renderConvoList);
  }
  console.log('[newConvo] After sync - allConvos:', Object.keys(allConvos).length);
  
  // Create new
  conversationState.activeConvoId = 'c_' + Date.now();
  const newConvoData = { 
    id: conversationState.activeConvoId, 
    title: 'New chat', 
    messages: [], 
    ts: Date.now() 
  };
  allConvos[conversationState.activeConvoId] = newConvoData;
  history.length = 0;
  console.log('[newConvo] After create - allConvos:', Object.keys(allConvos).length);
  
  saveAllConvos();
  
  // Save to DB immediately for logged-in users
  if (isUserLoggedIn()) {
    saveConvoToDb(conversationState.activeConvoId, newConvoData);
  }
  console.log('[newConvo] Saved. Rendering...');
  
  if (typeof renderConvoList === 'function') renderConvoList();
  if (typeof renderMessages === 'function') renderMessages();
}

/**
 * Switch to a conversation
 */
export function switchConvo(id, renderConvoList, renderMessages) {
  const { allConvos, history } = conversationState;
  
  if (!allConvos[id]) return;
  
  // Save current first
  syncCurrentConvo(renderConvoList);
  
  // Switch
  conversationState.activeConvoId = id;
  history.length = 0;
  allConvos[id].messages.forEach(m => history.push({...m}));
  
  saveAllConvos();
  if (typeof renderConvoList === 'function') renderConvoList();
  if (typeof renderMessages === 'function') renderMessages();
}

/**
 * Delete a conversation
 */
export function deleteConvo(id, renderConvoList, renderMessages, newConvoFn) {
  const { allConvos, history } = conversationState;
  
  console.log('[deleteConvo] Deleting:', id, 'active:', conversationState.activeConvoId);
  delete allConvos[id];
  
  // Also delete from database for logged-in users
  deleteConvoFromDb(id);
  
  if (conversationState.activeConvoId === id) {
    conversationState.activeConvoId = null;
    history.length = 0;
    
    const ids = Object.keys(allConvos);
    if (ids.length > 0) {
      conversationState.activeConvoId = ids[0];
      allConvos[ids[0]].messages.forEach(m => history.push({...m}));
      saveAllConvos();
      if (typeof renderConvoList === 'function') renderConvoList();
      if (typeof renderMessages === 'function') renderMessages();
    } else {
      if (typeof newConvoFn === 'function') {
        newConvoFn();
      }
    }
  } else {
    saveAllConvos();
    if (typeof renderConvoList === 'function') renderConvoList();
  }
  console.log('[deleteConvo] Done. Remaining convos:', Object.keys(allConvos));
}

// ============ ARTICLE GENERATION ============

/**
 * Generate article HTML from conversation
 */
export function generateArticleFromConvo(convo) {
  const title = convo.title || 'Untitled Article';
  const date = new Date(convo.ts || Date.now()).toLocaleDateString('en-US', { 
    year: 'numeric', month: 'long', day: 'numeric' 
  });
  
  let content = '';
  if (convo.messages) {
    convo.messages.forEach(m => {
      if (m.role === 'assistant' && m.content) {
        content += `<div class="section">${m.html || m.content.replace(/\n/g, '<br>')}</div>\n`;
      }
    });
  }
  
  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>${title}</title>
  <style>
    body { font-family: Georgia, serif; max-width: 800px; margin: 0 auto; padding: 2rem; line-height: 1.8; color: #333; }
    h1 { font-size: 2.5rem; margin-bottom: 0.5rem; }
    .meta { color: #666; font-size: 0.9rem; margin-bottom: 2rem; border-bottom: 1px solid #eee; padding-bottom: 1rem; }
    .section { margin-bottom: 1.5rem; }
    pre { background: #f5f5f5; padding: 1rem; border-radius: 8px; overflow-x: auto; }
    code { font-family: 'Fira Code', monospace; font-size: 0.9em; }
    blockquote { border-left: 4px solid #6366f1; margin: 1rem 0; padding-left: 1rem; color: #555; }
  </style>
</head>
<body>
  <article>
    <h1>${title}</h1>
    <div class="meta">Generated from Ginto AI conversation • ${date}</div>
    ${content || '<p>No content available.</p>'}
  </article>
</body>
</html>`;
}

// ============ SEARCH ============

/**
 * Escape HTML for safe insertion
 */
function escapeHtml(text) {
  return String(text || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

/**
 * Create a snippet around the first match
 */
function makeSnippet(content, idx, len = 120) {
  const start = Math.max(0, idx - Math.floor(len/3));
  const snippet = content.substring(start, start + len);
  return (start > 0 ? '... ' : '') + snippet + (start + len < content.length ? ' ...' : '');
}

/**
 * Perform search across allConvos titles and messages
 */
export function performSearch(q, renderConvoList, switchConvoFn, deleteConvoFn, showConfirmModal) {
  const { allConvos, activeConvoId } = conversationState;
  const outEl = document.getElementById('conversation-list');
  if (!outEl) return;
  
  q = String(q || '').trim();
  if (!q) {
    if (typeof renderConvoList === 'function') renderConvoList();
    return;
  }
  
  const term = q.toLowerCase();
  const matches = [];
  
  Object.values(allConvos).forEach(c => {
    // Search title
    if ((c.title || '').toLowerCase().includes(term)) {
      matches.push({ convo: c, snippet: escapeHtml(c.title) });
      return;
    }
    // Search messages
    if (Array.isArray(c.messages)) {
      for (const m of c.messages) {
        const text = (m.content || '') + '';
        const li = text.toLowerCase().indexOf(term);
        if (li !== -1) {
          const rawSnippet = makeSnippet(text, li);
          const escaped = escapeHtml(rawSnippet).replace(
            new RegExp(term.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'), 'ig'), 
            (m1) => `<mark>${m1}</mark>`
          );
          matches.push({ convo: c, snippet: escaped });
          break;
        }
      }
    }
  });

  if (!matches.length) {
    outEl.innerHTML = '<div class="text-sm text-gray-500 py-4 text-center">No matches</div>';
    return;
  }

  outEl.innerHTML = matches.map(m => `
    <div class="convo-item group flex flex-col gap-1 px-4 py-2 cursor-pointer transition-colors text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 hover:text-indigo-700 dark:hover:text-indigo-300 text-sm ${m.convo.id === activeConvoId ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300' : ''}" data-id="${m.convo.id}">
      <div class="flex items-center gap-2 w-full">
        <svg class="w-4 h-4 flex-shrink-0 ${m.convo.id === activeConvoId ? 'text-indigo-500' : 'text-gray-500'}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        <span class="sidebar-label flex-1 truncate text-sm">${escapeHtml(m.convo.title || 'Chat')}</span>
        <button class="del-btn sidebar-label opacity-0 group-hover:opacity-100 p-1 text-gray-500 hover:text-red-400" data-id="${m.convo.id}">✕</button>
      </div>
      <div class="sidebar-label text-xs text-gray-500 dark:text-gray-400 truncate">${m.snippet}</div>
    </div>
  `).join('');

  // Attach handlers
  outEl.querySelectorAll('.convo-item').forEach(item => {
    item.addEventListener('click', () => {
      if (typeof switchConvoFn === 'function') switchConvoFn(item.dataset.id);
    });
  });
  outEl.querySelectorAll('.del-btn').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.stopPropagation();
      const id = btn.dataset.id;
      const convo = allConvos[id];
      
      if (typeof showConfirmModal === 'function') {
        const confirmed = await showConfirmModal({
          title: 'Delete Conversation',
          message: `Are you sure you want to delete "${convo?.title || 'this conversation'}"? This action cannot be undone.`,
          confirmText: 'Delete',
          type: 'danger'
        });
        
        if (confirmed && typeof deleteConvoFn === 'function') {
          deleteConvoFn(id);
        }
      } else if (confirm(`Delete "${convo?.title || 'this conversation'}"?`)) {
        if (typeof deleteConvoFn === 'function') deleteConvoFn(id);
      }
    });
  });
}

// ============ INITIALIZATION ============

/**
 * Initialize conversation state from storage
 */
export function initConversationState() {
  const stored = loadAllConvos();
  Object.assign(conversationState.allConvos, stored.convos || {});
  conversationState.activeConvoId = stored.activeId;
  
  console.log('[conversation.js init] allConvos keys:', Object.keys(conversationState.allConvos), 
    'activeConvoId:', conversationState.activeConvoId);
  
  return conversationState;
}

/**
 * Initialize chat after auth is ready
 */
export async function initializeChat(renderMessages, renderConvoList) {
  const { allConvos, history } = conversationState;
  
  // Wait for user info to be loaded
  if (window.GINTO_AUTH_PROMISE) {
    await window.GINTO_AUTH_PROMISE;
  }

  // For logged-in users, load conversations from database
  if (isUserLoggedIn()) {
    console.log('[initializeChat] User is logged in, loading conversations from DB');
    const dbConvos = await loadConvosFromDb();
    if (dbConvos) {
      Object.keys(allConvos).forEach(k => delete allConvos[k]);
      Object.assign(allConvos, dbConvos);
      
      const convoIds = Object.keys(allConvos);
      if (convoIds.length > 0) {
        convoIds.sort((a, b) => (allConvos[b].ts || 0) - (allConvos[a].ts || 0));
        conversationState.activeConvoId = convoIds[0];
      } else {
        conversationState.activeConvoId = null;
      }
      console.log('[initializeChat] Loaded', convoIds.length, 'conversations from DB, active:', conversationState.activeConvoId);
    }
  }
  
  console.log('[initializeChat] activeConvoId:', conversationState.activeConvoId, 
    'exists:', !!allConvos[conversationState.activeConvoId]);
  
  // If we have an active convo, load its messages
  if (conversationState.activeConvoId && allConvos[conversationState.activeConvoId]) {
    const messages = allConvos[conversationState.activeConvoId].messages || [];
    console.log('[initializeChat] Loading convo with', messages.length, 'messages');
    messages.forEach(m => {
      if (m && m.role && (m.content !== undefined || m.html !== undefined)) {
        history.push({...m});
      }
    });
    console.log('[initializeChat] Loaded', history.length, 'messages into history');
  } else {
    // Create first conversation
    conversationState.activeConvoId = 'c_' + Date.now();
    allConvos[conversationState.activeConvoId] = { 
      id: conversationState.activeConvoId, 
      title: 'New chat', 
      messages: [], 
      ts: Date.now() 
    };
    console.log('[initializeChat] Created new conversation:', conversationState.activeConvoId);
  }
  
  // Render initial state
  if (typeof renderMessages === 'function') renderMessages();
  if (typeof renderConvoList === 'function') renderConvoList();
}

/**
 * Set up event listeners for persistence
 */
export function setupPersistenceListeners(renderConvoList, renderMessages) {
  const { allConvos, history } = conversationState;
  
  // Save on unload
  window.addEventListener('beforeunload', () => {
    try { saveAllConvos(); } catch (e) {}
  });

  // Listen for storage changes across tabs
  window.addEventListener('storage', (e) => {
    try {
      if (!e || !e.key) return;
      if (e.key === STORAGE_KEY) {
        console.log('[storage] Detected changes to', STORAGE_KEY, 'reloading');
        const loaded = loadAllConvos();
        Object.keys(allConvos).forEach(k => delete allConvos[k]);
        Object.assign(allConvos, loaded.convos || {});
        conversationState.activeConvoId = loaded.activeId;
        
        if (typeof renderConvoList === 'function') renderConvoList();
        history.length = 0;
        if (conversationState.activeConvoId && allConvos[conversationState.activeConvoId]) {
          (allConvos[conversationState.activeConvoId].messages || []).forEach(m => history.push({...m}));
        }
        if (typeof renderMessages === 'function') renderMessages();
      }
    } catch (e) {
      console.warn('[storage] error handling', e);
    }
  });
  
  // Listen for auth ready event
  window.addEventListener('gintoAuthReady', () => {
    console.log('[Auth] User info loaded, re-rendering messages');
    if (typeof renderMessages === 'function') renderMessages();
  });
}

/**
 * Bind logout clear handler
 */
export function bindLogoutClear() {
  const { allConvos, history } = conversationState;
  
  try {
    const logoutEl = document.getElementById('logout-link');
    if (!logoutEl) return;
    
    logoutEl.addEventListener('click', function(e) {
      try {
        // Clear ALL localStorage
        const keysToRemove = [];
        for (let i = 0; i < localStorage.length; i++) {
          const k = localStorage.key(i);
          if (k) keysToRemove.push(k);
        }
        keysToRemove.forEach(k => {
          try { localStorage.removeItem(k); } catch (er) {}
        });
        console.log('[logout] Cleared all localStorage keys:', keysToRemove.length);
      } catch (err) { 
        console.error('Failed clearing storage on logout', err); 
      }

      // Wipe in-memory convos
      try {
        Object.keys(allConvos).forEach(k => delete allConvos[k]);
        conversationState.activeConvoId = null;
        history.length = 0;
      } catch (er) {}
      
      // Clear sessionStorage too
      try { sessionStorage.clear(); } catch (er) {}
    });
  } catch (e) {}
}

/**
 * Generate unique message ID
 */
export function generateMsgId() {
  return Date.now().toString(36) + Math.random().toString(36).slice(2,6);
}
