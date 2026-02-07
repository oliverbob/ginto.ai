/**
 * Chat Streaming Module
 * SSE handling, conversation card creation, abort control
 */

import { escapeHtml, copyToClipboard, smartScrollToElement } from './core.js';
import { simpleMarkdownToHtml, renderLatexInElement, stripToolCallJson, ensureCodeBlockAttributes } from './markdown.js';
import { enhanceCodeBlocks, initStickyCodeButtons } from './code-blocks.js';
import { conversationState, syncCurrentConvo } from './conversation.js';

// ============ STATE ============
let abortController = null;
let currentCard = null;
let isStreaming = false;
let userHasScrolledUp = false;

// ============ UI HELPERS ============

/**
 * Get conversation container element
 */
export function getConvoContainer() {
  return document.getElementById('conversation-container') || 
         document.getElementById('chat-container') || 
         document.querySelector('.chat-messages');
}

/**
 * Get query type from text
 */
export function getQueryType(query) {
  const q = query.toLowerCase();
  if (q.includes('weather')) return 'weather';
  if (q.includes('stock') || q.includes('price') || q.includes('market')) return 'stocks';
  if (q.includes('news') || q.includes('headline')) return 'news';
  if (q.includes('search') || q.includes('find') || q.includes('look up')) return 'search';
  return 'general';
}

/**
 * Get icon path for query type
 */
export function getTypeIcon(type) {
  const icons = {
    weather: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>',
    stocks: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>',
    news: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>',
    search: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>',
    general: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'
  };
  return icons[type] || icons.general;
}

// ============ REASONING FORMATTING ============

/**
 * Format reasoning text with Groq-style step indicators
 */
export function formatReasoningText(text) {
  if (!text || !text.trim()) return '';
  
  // Filter out raw JSON tool calls
  let cleanedText = text;
  cleanedText = cleanedText.replace(/\{"query"\s*:[^}]+\}/g, '');
  cleanedText = cleanedText.replace(/\{"tool_call"\s*:[^}]+\}/g, '');
  cleanedText = cleanedText.replace(/\{"tool"\s*:[^}]+\}/g, '');
  cleanedText = cleanedText.replace(/\{"browser_search"\s*:[^}]+\}/g, '');
  cleanedText = cleanedText.replace(/^\s*\{[^}]{10,200}\}\s*$/gm, '');
  cleanedText = cleanedText.replace(/\n{3,}/g, '\n\n').trim();
  
  if (!cleanedText) return '';
  
  const escapeHtmlText = (str) => {
    let escaped = str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
    
    // Apply basic markdown formatting
    escaped = escaped.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    escaped = escaped.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    escaped = escaped.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    escaped = escaped.replace(/(?<![a-zA-Z0-9])_([^_]+)_(?![a-zA-Z0-9])/g, '<em>$1</em>');
    escaped = escaped.replace(/`([^`]+)`/g, '<code style="background:rgba(255,255,255,0.1);padding:2px 4px;border-radius:3px;font-size:0.9em;">$1</code>');
    
    return escaped;
  };
  
  const createReasoningItem = (content, isLast = false) => `<div class="reasoning-item${isLast ? ' reasoning-item-last' : ''}">
    <div class="reasoning-item-indicator">
      <div class="reasoning-item-dot${isLast ? ' reasoning-item-dot-green' : ''}"></div>
      <div class="reasoning-item-line"></div>
    </div>
    <div class="reasoning-item-text"><p>${escapeHtmlText(content)}</p></div>
  </div>`;
  
  const mapWithLast = (items) => items.map((item, i) => createReasoningItem(item, i === items.length - 1)).join('');
  
  // Split by double newlines first
  let paragraphs = cleanedText.split(/\n\n+/).map(p => p.trim()).filter(p => p);
  
  if (paragraphs.length > 1) {
    return mapWithLast(paragraphs.map(p => p.replace(/\n/g, ' ')));
  }
  
  // Split by single newlines
  paragraphs = text.split(/\n/).map(p => p.trim()).filter(p => p);
  if (paragraphs.length > 1) {
    return mapWithLast(paragraphs);
  }
  
  // Single paragraph fallback
  const normalized = text.replace(/\s+/g, ' ').trim();
  return createReasoningItem(normalized, true);
}

// ============ ACTIVITY TRACKING ============

/**
 * Update card activity display
 */
export function updateCardActivity(card, activities) {
  const parts = [];
  if (activities.searches.length > 0) parts.push('searched the web');
  if (activities.reads.length > 0) parts.push(`visited ${activities.reads.length} site${activities.reads.length > 1 ? 's' : ''}`);
  if (activities.analyzing) parts.push('analyzing results');
  
  card.activityText.textContent = parts.length > 0 
    ? `🔍 ${parts.join(', ')}...` 
    : 'Searching the web...';

  if (activities.reads.length > 0) {
    const uniqueDomains = [...new Set(activities.reads.map(r => r.domain))];
    card.sitesList.innerHTML = uniqueDomains.slice(0, 6).map(d => 
      `<span class="site-badge">${d}</span>`
    ).join('');
  }
}

/**
 * Finish card activity display
 */
export function finishCardActivity(card, activities) {
  if (card.activitySpinner) {
    card.activitySpinner.classList.remove('activity-spinner');
    card.activitySpinner.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>';
  }
  const parts = [];
  if (activities.searches.length > 0) parts.push('searched the web');
  if (activities.reads.length > 0) parts.push(`visited ${activities.reads.length} site${activities.reads.length > 1 ? 's' : ''}`);
  if (activities.analyzed) parts.push('analyzed results');
  card.activityText.textContent = parts.length > 0 ? `✓ ${parts.join(', ')}` : '✓ Done';
  
  if (activities.reads.length > 0) {
    card.sourcesSpan.textContent = ` • ${activities.reads.length} source${activities.reads.length > 1 ? 's' : ''}`;
  }
}

/**
 * Update card citations display
 */
export function updateCardCitations(card, citations) {
  if (citations.length === 0) {
    card.citations.classList.add('hidden');
    card.sourcesStack.classList.add('hidden');
    return;
  }
  card.citations.classList.remove('hidden');
  card.citationsList.innerHTML = citations.map((c, i) => {
    const faviconUrl = c.favicon || `https://www.google.com/s2/favicons?domain=${c.domain}&sz=16`;
    const title = c.title || c.domain;
    return `<a href="${c.url}" target="_blank" class="citation" onclick="event.stopPropagation()" title="${escapeHtml(title)}">
      <img src="${faviconUrl}" alt="" class="w-4 h-4 rounded-full" style="object-fit:cover;" onerror="this.style.display='none'" loading="lazy">
      <span>${c.domain}</span>
    </a>`;
  }).join('');
  
  card.sourcesStack.classList.remove('hidden');
  const iconsToShow = citations.slice(0, 4);
  card.sourcesIcons.innerHTML = iconsToShow.map((c, i) => {
    const faviconUrl = c.favicon || `https://www.google.com/s2/favicons?domain=${c.domain}&sz=16`;
    const title = c.title || c.domain;
    const marginLeft = i === 0 ? '0' : '-4px';
    return `<img src="${faviconUrl}" alt="${c.domain}" title="${escapeHtml(title)}" loading="lazy" style="width:16px;height:16px;border-radius:50%;border:1px solid #1f2937;object-fit:cover;margin-left:${marginLeft};" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%236b7280%22><circle cx=%2212%22 cy=%2212%22 r=%2210%22/></svg>'">`;
  }).join('');
}

// ============ ABORT CONTROL ============

/**
 * Get abort controller
 */
export function getAbortController() {
  return abortController;
}

/**
 * Create new abort controller
 */
export function createAbortController() {
  if (abortController) abortController.abort();
  abortController = new AbortController();
  return abortController;
}

/**
 * Get streaming state
 */
export function getStreamingState() {
  return {
    isStreaming,
    userHasScrolledUp,
    currentCard
  };
}

/**
 * Set streaming state
 */
export function setStreamingState(streaming, scrolledUp = false) {
  isStreaming = streaming;
  userHasScrolledUp = scrolledUp;
}

/**
 * Set current card
 */
export function setCurrentCard(card) {
  currentCard = card;
}

// ============ BUSY STATE ============

/**
 * Set UI busy state
 */
export function setBusy(busy) {
  const sendBtn = document.getElementById('send-btn') || document.getElementById('composer-send');
  const promptEl = document.getElementById('prompt') || document.getElementById('composer-input');
  
  if (sendBtn) {
    sendBtn._isStreaming = busy;
    if (busy) {
      sendBtn.innerHTML = '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
      sendBtn.title = 'Stop generating';
    } else {
      sendBtn.innerHTML = '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>';
      sendBtn.title = 'Send message';
    }
  }
  
  if (promptEl) {
    promptEl.disabled = busy;
  }
}

// ============ TTS SANITIZATION ============

/**
 * Sanitize text for TTS (remove markdown, code, etc.)
 */
export function sanitizeForTTS(text) {
  if (!text) return '';
  let clean = text;
  // Remove code blocks
  clean = clean.replace(/```[\s\S]*?```/g, ' ');
  // Remove inline code
  clean = clean.replace(/`[^`]+`/g, ' ');
  // Remove markdown tables
  clean = clean.replace(/^\|.*\|$/gm, '');
  clean = clean.replace(/^[\s]*[-|:]+[\s]*$/gm, '');
  // Remove emoji
  clean = clean.replace(/[\u{1F300}-\u{1F9FF}]|[\u{2600}-\u{26FF}]|[\u{2700}-\u{27BF}]/gu, '');
  // Remove special chars
  clean = clean.replace(/[•→←↑↓…—–""''✓✗✨🎉🙏]/g, '');
  // Remove markdown
  clean = clean.replace(/[*_`#\[\]]/g, '');
  // Remove citations
  clean = clean.replace(/\[\d+\]/g, '');
  // Remove URLs
  clean = clean.replace(/https?:\/\/[^\s]+/g, '');
  // Collapse whitespace
  clean = clean.replace(/\s+/g, ' ').trim();
  return clean;
}

// ============ EXPORTS ============

export {
  abortController,
  currentCard,
  isStreaming,
  userHasScrolledUp
};
