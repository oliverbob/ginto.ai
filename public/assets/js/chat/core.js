/**
 * Chat Core Module
 * State management, utilities, and shared helpers
 */

// ============ STATE MANAGEMENT ============
export const state = {
  // Scroll tracking
  userHasScrolledUp: false,
  isStreaming: false,
  
  // Current attachment
  currentAttachment: null,
  
  // Conversation state
  history: [],
  allConvos: {},
  activeConvoId: null,
  
  // Abort controller for streaming
  abortController: null,
  currentCard: null,
  
  // Debounce timers
  dbSaveTimeout: null,
};

// ============ STORAGE KEYS ============
export const STORAGE_KEY = 'ginto_conversations_v2';

// ============ DOM ELEMENT CACHE ============
let cachedElements = null;

export function getElements() {
  if (cachedElements) return cachedElements;
  
  cachedElements = {
    sendBtn: document.getElementById('send'),
    clearBtn: document.getElementById('clear'),
    resetHistoryBtn: document.getElementById('reset_history'),
    promptEl: document.getElementById('prompt'),
    messagesEl: document.getElementById('messages'),
    attachBtn: document.getElementById('attach-btn'),
    attachInput: document.getElementById('attach-input'),
    attachPreview: document.getElementById('attach-preview'),
    attachPreviewImg: document.getElementById('attach-preview-img'),
    attachFilename: document.getElementById('attach-filename'),
    attachRemove: document.getElementById('attach-remove'),
    composerEl: document.getElementById('composer'),
    convoListEl: document.getElementById('conversation-list'),
    convoSearchEl: document.getElementById('convo-search'),
    newChatBtn: document.getElementById('new_chat'),
  };
  
  return cachedElements;
}

// ============ UTILITY FUNCTIONS ============

/**
 * Escape HTML to prevent XSS
 */
export function escapeHtml(text) {
  return String(text || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/**
 * Generate unique message ID
 */
export function msgId() {
  return Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
}

/**
 * Copy text to clipboard with fallback for non-HTTPS
 */
export async function copyToClipboard(text) {
  try {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      await navigator.clipboard.writeText(text);
      return true;
    }
  } catch (err) {
    console.log('Clipboard API failed, using fallback:', err.message);
  }
  
  // Fallback for older browsers or non-HTTPS contexts
  try {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-9999px';
    textArea.style.top = '0';
    textArea.setAttribute('readonly', '');
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    const success = document.execCommand('copy');
    document.body.removeChild(textArea);
    
    if (success) return true;
  } catch (fallbackErr) {
    console.error('Fallback copy also failed:', fallbackErr);
  }
  
  return false;
}

/**
 * Debounce function
 */
export function debounce(fn, wait) {
  let t;
  return function(...args) {
    clearTimeout(t);
    t = setTimeout(() => fn.apply(this, args), wait);
  };
}

// ============ SCROLL HELPERS ============

/**
 * Get composer height dynamically
 */
export function getComposerHeight() {
  const composer = document.querySelector('.composer-container') || document.querySelector('#chat-form')?.closest('div');
  if (composer) {
    return composer.offsetHeight + 20;
  }
  return 140;
}

/**
 * Get scroll info for the document
 */
export function getScrollInfo() {
  const scrollTop = window.scrollY || document.documentElement.scrollTop;
  const windowHeight = window.innerHeight;
  const docHeight = document.documentElement.scrollHeight;
  const distanceFromBottom = docHeight - scrollTop - windowHeight;
  return { scrollTop, windowHeight, docHeight, distanceFromBottom };
}

/**
 * Check if we should auto-scroll
 */
export function shouldAutoScroll() {
  if (state.userHasScrolledUp) return false;
  return true;
}

/**
 * Scroll to bottom of page
 */
export function scrollToBottom() {
  if (!shouldAutoScroll()) return;
  const composerHeight = getComposerHeight();
  requestAnimationFrame(() => {
    const targetScroll = document.documentElement.scrollHeight - window.innerHeight + composerHeight;
    window.scrollTo({
      top: targetScroll,
      behavior: 'auto'
    });
  });
}

/**
 * Smart scroll that respects user position and composer visibility
 */
export function smartScrollToElement(element, options = { behavior: 'smooth', block: 'end' }) {
  if (!shouldAutoScroll() || !element) return;
  
  const composerHeight = getComposerHeight();
  const rect = element.getBoundingClientRect();
  const viewportHeight = window.innerHeight;
  
  const visibleBottom = viewportHeight - composerHeight;
  if (rect.bottom > visibleBottom) {
    const scrollAmount = rect.bottom - visibleBottom + 20;
    window.scrollBy({
      top: scrollAmount,
      behavior: 'auto'
    });
  }
}

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

// ============ SCROLL TRACKING ============

/**
 * Initialize scroll tracking during streaming
 */
export function initScrollTracking() {
  window.addEventListener('scroll', () => {
    if (!state.isStreaming) return;
    state.userHasScrolledUp = true;
  }, { passive: true });
}

// ============ JSON HELPERS ============

/**
 * Safe JSON parse with error handling
 */
export function tryParseJsonSafe(s) {
  if (!s) return null;
  if (typeof s !== 'string') return s;
  try { 
    return JSON.parse(s); 
  } catch (e) {
    try {
      const fixed = s.replace(/'(.*?)'/g, '"$1"').replace(/,\s*}/g, '}').replace(/,\s*]/g, ']');
      return JSON.parse(fixed);
    } catch (e2) { 
      return null; 
    }
  }
}

// Initialize scroll tracking
initScrollTracking();
