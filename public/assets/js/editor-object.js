/* Minimal embedded assistant client — focused on UI wiring and a simple POST to the server. */
/* Extended with TTS, STT, tool calls, and agent features from /chat */

(function(){
  'use strict';

  // ============ CONVERSATION PERSISTENCE ============
  var STORAGE_KEY = 'playground-editor-chat-tabs';
  
  function loadTabsFromStorage() {
    try {
      var stored = localStorage.getItem(STORAGE_KEY);
      if (stored) {
        var data = JSON.parse(stored);
        if (data && data.tabs && typeof data.activeTabId === 'number' && typeof data.nextTabId === 'number') {
          return data;
        }
      }
    } catch (e) {
      console.debug('Failed to load chat tabs from storage:', e);
    }
    return null;
  }
  
  function saveTabsToStorage() {
    try {
      var data = {
        tabs: chatTabs,
        activeTabId: activeTabId,
        nextTabId: nextTabId
      };
      localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    } catch (e) {
      console.debug('Failed to save chat tabs to storage:', e);
    }
  }

  // ============ TAB MANAGEMENT ============
  // Each tab has its own conversation history and messages
  var storedData = loadTabsFromStorage();
  var chatTabs = storedData ? storedData.tabs : { 1: { history: [], messagesHtml: '' } };
  var activeTabId = storedData ? storedData.activeTabId : 1;
  var nextTabId = storedData ? storedData.nextTabId : 2;
  
  // Ensure at least one tab exists
  if (!chatTabs[activeTabId]) {
    chatTabs[1] = { history: [], messagesHtml: '' };
    activeTabId = 1;
  }
  
  // Get current tab's history
  function getActiveHistory() {
    return chatTabs[activeTabId]?.history || [];
  }
  
  // Alias for backward compatibility
  var conversationHistory = chatTabs[activeTabId]?.history || [];

  // ============ UTILITY FUNCTIONS ============
  function getCsrf() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.content) return meta.content;
    var hidden = document.querySelector('input[name="csrf_token"]');
    if (hidden) return hidden.value;
    return window.csrf_token || window.CSRF_TOKEN || '';
  }

  // Get API base URL based on current page
  function getApiBaseUrl() {
    return window.location.pathname.startsWith('/editor') ? '/editor' : '/playground/editor';
  }

  // Helper to generate action buttons HTML for bot messages
  function getActionButtonsHtml() {
    return '<div class="message-actions">' +
      '<button class="msg-action-btn" data-action="copy" title="Copy message">' +
        '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>' +
      '</button>' +
      '<button class="msg-action-btn" data-action="read" title="Read aloud">' +
        '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>' +
      '</button>' +
      '<button class="msg-action-btn" data-action="regenerate" title="Regenerate response">' +
        '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>' +
      '</button>' +
    '</div>';
  }

  // Render KaTeX math in an element AFTER innerHTML is set
  function renderLatexInElement(element) {
    if (!element) return;
    if (typeof renderMathInElement === 'undefined' || typeof katex === 'undefined') {
      // KaTeX not loaded, skip silently
      return;
    }
    
    // Check if element contains potential math
    var text = element.textContent || '';
    var hasMath = text.includes('$') || text.includes('\\[') || text.includes('\\(');
    if (!hasMath) return;
    
    try {
      renderMathInElement(element, {
        delimiters: [
          { left: '$$', right: '$$', display: true },
          { left: '$', right: '$', display: false },
          { left: '\\[', right: '\\]', display: true },
          { left: '\\(', right: '\\)', display: false }
        ],
        throwOnError: false,
        errorColor: '#cc0000',
        ignoredTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code']
      });
    } catch (e) {
      console.debug('[Editor] KaTeX error:', e);
    }
  }

  // Top-level helper: sanitize bubble text for copying
  function sanitizeBubbleTextForCopyElement(bubbleEl) {
    if (!bubbleEl) return '';
    const orig = bubbleEl;
    const clone = orig.cloneNode(true);

    // Remove UI and control elements
    ['button', '.message-actions', '.msg-action-btn', '.code-block-header', '.code-header-buttons', '.line-numbers', '.user-message-actions'].forEach(sel => {
      try { clone.querySelectorAll(sel).forEach(e => e.remove()); } catch (e) {}
    });

    // Replace code-block-wrapper with plain <pre> containing raw code if possible
    const origBlocks = Array.from(orig.querySelectorAll('.code-block-wrapper'));
    const cloneBlocks = Array.from(clone.querySelectorAll('.code-block-wrapper'));
    for (let i = 0; i < cloneBlocks.length; i++) {
      const cClone = cloneBlocks[i];
      const oOrig = origBlocks[i];
      let codeText = '';

      if (oOrig) {
        if (oOrig.cmInstance && typeof oOrig.cmInstance.getValue === 'function') {
          try { codeText = oOrig.cmInstance.getValue(); } catch (e) { codeText = ''; }
        }
        if (!codeText && oOrig.dataset.codeB64) {
          try { codeText = decodeURIComponent(escape(atob(oOrig.dataset.codeB64))); } catch (e) { codeText = ''; }
        }
        if (!codeText && oOrig.dataset.code) {
          codeText = oOrig.dataset.code.replace(/\\n/g, '\n');
        }
        if (!codeText) {
          const ta = oOrig.querySelector('textarea');
          if (ta) codeText = ta.value || ta.textContent || '';
        }
        if (!codeText) {
          const pre = oOrig.querySelector('pre');
          if (pre) codeText = pre.textContent || pre.innerText || '';
        }
      }

      const pre = document.createElement('pre');
      pre.textContent = (codeText || '').trim();
      cClone.parentNode.replaceChild(pre, cClone);
    }

    let text = clone.innerText || clone.textContent || '';
    // Remove standalone numbers (line numbers) and leftover labels
    text = text.split('\n').map(l => l.trim()).filter(l => l && !/^[0-9]+$/.test(l) && !/^(save|copy|code)$/i.test(l)).join('\n');
    text = text.replace(/\s+\n/g, '\n').replace(/\n\s+/g, '\n').replace(/[ \t]{2,}/g, ' ').trim();
    return text;
  }

  function createMessageEl(who, text, html) {
    var el = document.createElement('div');
    el.className = 'assistant-message ' + (who === 'user' ? 'user' : 'bot');
    var bubble = document.createElement('div');
    bubble.className = 'bubble';
    if (html) {
      bubble.innerHTML = html;
      // Render LaTeX math expressions
      renderLatexInElement(bubble);
    } else {
      bubble.textContent = text || '';
    }
    
    // Add action bar for bot messages (inside the bubble)
    if (who === 'bot') {
      var msgId = 'msg-' + Math.random().toString(36).slice(2, 10);
      el.dataset.msgId = msgId;
      var actionBar = document.createElement('div');
      actionBar.className = 'message-actions';
      actionBar.innerHTML = 
        '<button class="msg-action-btn" data-action="copy" title="Copy message">' +
          '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>' +
        '</button>' +
        '<button class="msg-action-btn" data-action="read" title="Read aloud">' +
          '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>' +
        '</button>' +
        '<button class="msg-action-btn" data-action="regenerate" title="Regenerate response">' +
          '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>' +
        '</button>';
      bubble.appendChild(actionBar);
      
      // Attach event listeners for action buttons
      // Helper: sanitize bubble text for copying (remove UI, extract code blocks cleanly)
      function sanitizeBubbleTextForCopy(bubbleEl) {
        if (!bubbleEl) return '';
        const orig = bubbleEl;
        const clone = orig.cloneNode(true);

        // Remove UI and control elements
        ['button', '.message-actions', '.msg-action-btn', '.code-block-header', '.code-header-buttons', '.line-numbers', '.user-message-actions'].forEach(sel => {
          try { clone.querySelectorAll(sel).forEach(e => e.remove()); } catch (e) {}
        });

        // Replace code-block-wrapper with plain <pre> containing raw code if possible
        const origBlocks = Array.from(orig.querySelectorAll('.code-block-wrapper'));
        const cloneBlocks = Array.from(clone.querySelectorAll('.code-block-wrapper'));
        for (let i = 0; i < cloneBlocks.length; i++) {
          const cClone = cloneBlocks[i];
          const oOrig = origBlocks[i];
          let codeText = '';

          if (oOrig) {
            if (oOrig.cmInstance && typeof oOrig.cmInstance.getValue === 'function') {
              try { codeText = oOrig.cmInstance.getValue(); } catch (e) { codeText = ''; }
            }
            if (!codeText && oOrig.dataset.codeB64) {
              try { codeText = decodeURIComponent(escape(atob(oOrig.dataset.codeB64))); } catch (e) { codeText = ''; }
            }
            if (!codeText && oOrig.dataset.code) {
              codeText = oOrig.dataset.code.replace(/\\n/g, '\n');
            }
            if (!codeText) {
              const ta = oOrig.querySelector('textarea');
              if (ta) codeText = ta.value || ta.textContent || '';
            }
            if (!codeText) {
              const pre = oOrig.querySelector('pre');
              if (pre) codeText = pre.textContent || pre.innerText || '';
            }
          }

          const pre = document.createElement('pre');
          pre.textContent = (codeText || '').trim();
          cClone.parentNode.replaceChild(pre, cClone);
        }

        let text = clone.innerText || clone.textContent || '';
        // Remove standalone numbers (line numbers) and leftover labels
        text = text.split('\n').map(l => l.trim()).filter(l => l && !/^[0-9]+$/.test(l) && !/^(save|copy|code)$/i.test(l)).join('\n');
        text = text.replace(/\s+\n/g, '\n').replace(/\n\s+/g, '\n').replace(/[ \t]{2,}/g, ' ').trim();
        return text;
      }

      actionBar.addEventListener('click', function(e) {
        var btn = e.target.closest('.msg-action-btn');
        if (!btn) return;
        var action = btn.dataset.action;
        var bubbleText = sanitizeBubbleTextForCopy(bubble);
        
        if (action === 'copy') {
          // Try modern clipboard API first, then fallback to execCommand
          var copySuccess = function() {
            btn.innerHTML = '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
            setTimeout(function() {
              btn.innerHTML = '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>';
            }, 1500);
          };
          
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(bubbleText).then(copySuccess).catch(function(err) {
              console.warn('Clipboard API failed, trying fallback:', err);
              // Fallback: create a textarea off-screen to prevent scroll jump
              var textarea = document.createElement('textarea');
              textarea.value = bubbleText;
              textarea.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;pointer-events:none;';
              document.body.appendChild(textarea);
              textarea.focus({preventScroll: true});
              textarea.select();
              try {
                document.execCommand('copy');
                copySuccess();
              } catch (e) {
                console.error('Copy failed:', e);
              }
              document.body.removeChild(textarea);
            });
          } else {
            // Fallback for older browsers or non-secure contexts
            var textarea = document.createElement('textarea');
            textarea.value = bubbleText;
            textarea.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;pointer-events:none;';
            document.body.appendChild(textarea);
            textarea.focus({preventScroll: true});
            textarea.select();
            try {
              document.execCommand('copy');
              copySuccess();
            } catch (e) {
              console.error('Copy failed:', e);
            }
            document.body.removeChild(textarea);
          }
        } else if (action === 'read') {
          if (window.__gintoAudio && typeof window.__gintoAudio.speak === 'function') {
            window.__gintoAudio.speak(bubbleText);
          } else if (window.speechSynthesis) {
            var utter = new SpeechSynthesisUtterance(bubbleText);
            window.speechSynthesis.speak(utter);
          }
        } else if (action === 'regenerate') {
          // Find the previous user message and resend
          var messages = el.parentElement.querySelectorAll('.assistant-message.user');
          var lastUserMsg = messages[messages.length - 1];
          if (lastUserMsg) {
            var userText = lastUserMsg.querySelector('.bubble').textContent;
            // Remove this bot message
            el.remove();
            // Trigger resend (the input element should be available)
            var inputEl = document.getElementById('assistant-prompt') || document.getElementById('chat-input');
            if (inputEl && userText) {
              inputEl.value = userText;
              var sendBtn = document.getElementById('assistant-send') || document.getElementById('chat-send');
              if (sendBtn) sendBtn.click();
            }
          }
        }
      });
    }
    
    el.appendChild(bubble);
    return el;
  }

  // Inject restore checkpoint button styles
  (function injectCheckpointStyles() {
    if (document.getElementById('ginto-checkpoint-styles')) return;
    var style = document.createElement('style');
    style.id = 'ginto-checkpoint-styles';
    style.textContent = `
      .restore-checkpoint-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        margin: 8px 0;
        padding: 4px 0;
        height: 24px;
      }
      /* Ribbon icon on the left */
      .restore-checkpoint-wrapper::before {
        content: '';
        flex-shrink: 0;
        width: 14px;
        height: 18px;
        margin-left: 8px;
        background: rgba(156, 163, 175, 0.4);
        clip-path: polygon(0 0, 100% 0, 100% 100%, 50% 75%, 0 100%);
      }
      /* Dashed line extending to the right */
      .restore-checkpoint-wrapper::after {
        content: '';
        flex: 1;
        height: 0;
        border-top: 1px dashed rgba(156, 163, 175, 0.4);
        margin-left: 8px;
        margin-right: 16px;
      }
      .restore-checkpoint-btn,
      .redo-checkpoint-btn {
        display: none;
        order: -1;
      }
      /* Show button on hover - positioned after ribbon, before dashed line */
      .restore-checkpoint-wrapper:hover .restore-checkpoint-btn,
      .restore-checkpoint-wrapper:hover .redo-checkpoint-btn {
        display: inline-flex;
        position: relative;
        margin-left: 8px;
        flex-shrink: 0;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        font-size: 11px;
        font-weight: 500;
        color: #a78bfa;
        background: rgba(139, 92, 246, 0.1);
        border: 1px solid rgba(139, 92, 246, 0.3);
        border-radius: 4px;
        cursor: pointer;
        z-index: 1;
      }
      .restore-checkpoint-wrapper:hover .restore-checkpoint-btn:hover {
        background: rgba(139, 92, 246, 0.2);
        border-color: #8b5cf6;
      }
      .restore-checkpoint-btn svg,
      .redo-checkpoint-btn svg {
        flex-shrink: 0;
        width: 12px;
        height: 12px;
      }
      .restore-checkpoint-wrapper.redo::before {
        background: rgba(52, 211, 153, 0.4);
      }
      .restore-checkpoint-wrapper.redo::after {
        border-color: rgba(52, 211, 153, 0.4);
      }
      .restore-checkpoint-wrapper.redo:hover .redo-checkpoint-btn {
        color: #34d399;
        background: rgba(52, 211, 153, 0.1);
        border-color: rgba(52, 211, 153, 0.3);
      }
      .restore-checkpoint-wrapper.redo:hover .redo-checkpoint-btn:hover {
        background: rgba(52, 211, 153, 0.2);
        border-color: #10b981;
      }
      
      /* Sai Thinking/Planning Blocks */
      .sai-thinking {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(139, 92, 246, 0.08) 100%);
        border: 1px solid rgba(139, 92, 246, 0.2);
        border-left: 3px solid #8b5cf6;
        border-radius: 8px;
        padding: 12px 16px;
        margin: 12px 0;
        font-size: 13px;
        line-height: 1.6;
        max-height: 400px;
        overflow-y: auto;
        overflow-x: auto;
      }
      /* Tool Execution Summary Styling */
      .bubble hr {
        border: none;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        margin: 16px 0;
      }
      .bubble ul {
        list-style: none;
        padding-left: 0;
        margin: 8px 0;
      }
      .bubble ul li {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 0;
        font-size: 13px;
      }
      .bubble ul li code {
        background: rgba(99, 102, 241, 0.15);
        padding: 2px 8px;
        border-radius: 4px;
        font-family: 'Monaco', 'Consolas', monospace;
        font-size: 12px;
        color: #a5b4fc;
      }
      .sai-thinking::-webkit-scrollbar {
        width: 6px;
        height: 6px;
      }
      .sai-thinking::-webkit-scrollbar-track {
        background: rgba(139, 92, 246, 0.1);
        border-radius: 3px;
      }
      .sai-thinking::-webkit-scrollbar-thumb {
        background: rgba(139, 92, 246, 0.3);
        border-radius: 3px;
      }
      .sai-thinking::-webkit-scrollbar-thumb:hover {
        background: rgba(139, 92, 246, 0.5);
      }
      .sai-thinking strong {
        color: #a78bfa;
        display: block;
        margin-bottom: 6px;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }
      .sai-thinking ul,
      .sai-thinking ol {
        margin: 4px 0 4px 20px;
        padding: 0;
      }
      .sai-thinking li {
        margin: 2px 0;
        color: #d1d5db;
      }
      .sai-thinking code {
        background: rgba(0, 0, 0, 0.3);
        padding: 2px 6px;
        border-radius: 4px;
        font-family: 'Fira Code', 'Consolas', monospace;
        font-size: 12px;
        white-space: nowrap;
      }
      .sai-thinking pre {
        background: rgba(0, 0, 0, 0.4);
        padding: 10px 12px;
        border-radius: 6px;
        overflow-x: auto;
        margin: 8px 0;
        white-space: pre;
      }
      .sai-thinking pre code {
        background: none;
        padding: 0;
        white-space: pre;
      }
      
      /* Task status indicators */
      .sai-thinking .task-done {
        color: #34d399;
      }
      .sai-thinking .task-pending {
        color: #fbbf24;
      }
      .sai-thinking .task-current {
        color: #60a5fa;
        font-weight: 600;
      }
      .sai-thinking .todo-item {
        padding: 2px 0;
        color: #d1d5db;
      }
      .sai-thinking .todo-item.done {
        color: #34d399;
      }
      .sai-thinking .todo-item.pending {
        color: #9ca3af;
      }
      .sai-thinking > div {
        margin: 2px 0;
      }
      
      /* Progress Tracker Styles */
      .chat-progress-tracker {
        background: transparent;
        font-size: 13px;
        padding: 0;
        width: 100%;
        box-sizing: border-box;
      }
      .tracker-section {
        margin-bottom: 4px;
        width: 100%;
        background: rgba(30, 30, 40, 0.5);
        border: 1px solid rgba(139, 92, 246, 0.2);
        border-radius: 8px;
        overflow: hidden;
      }
      .tracker-row {
        display: flex;
        align-items: center;
        width: 100%;
      }
      .tracker-header {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1;
        padding: 10px 12px;
        background: none;
        border: none;
        color: #e5e7eb;
        cursor: pointer;
        text-align: left;
        transition: background 0.15s;
        width: 100%;
      }
      .tracker-header:hover {
        background: rgba(255, 255, 255, 0.05);
      }
      .tracker-icon {
        font-size: 16px;
        color: #a78bfa;
        transition: transform 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 16px;
        height: 16px;
        font-weight: bold;
      }
      .tracker-header[aria-expanded="true"] .tracker-icon {
        transform: rotate(90deg);
      }
      .tracker-label {
        font-weight: 600;
        font-size: 13px;
      }
      .tracker-count {
        color: #9ca3af;
        font-size: 12px;
      }
      .tracker-stats {
        display: inline-flex;
        gap: 8px;
        margin-left: 8px;
        font-size: 12px;
      }
      .tracker-stats .added {
        color: #34d399;
      }
      .tracker-stats .removed {
        color: #f87171;
      }
      .tracker-actions {
        display: flex;
        gap: 8px;
        padding-right: 12px;
        flex-shrink: 0;
      }
      .tracker-action-btn {
        padding: 4px 12px;
        font-size: 12px;
        font-weight: 500;
        border-radius: 4px;
        border: none;
        cursor: pointer;
        transition: all 0.15s;
      }
      .tracker-keep {
        background: #3b82f6;
        color: white;
      }
      .tracker-keep:hover {
        background: #2563eb;
      }
      .tracker-undo {
        background: rgba(255, 255, 255, 0.1);
        color: #e5e7eb;
        border: 1px solid rgba(255, 255, 255, 0.2);
      }
      .tracker-undo:hover {
        background: rgba(255, 255, 255, 0.15);
      }
      .tracker-content {
        padding: 4px 12px 12px 36px;
        border-top: 1px solid rgba(255, 255, 255, 0.05);
      }
      .tracker-list {
        list-style: none;
        margin: 0;
        padding: 0;
      }
      .tracker-list li {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 0;
        color: #d1d5db;
        font-size: 13px;
      }
      .tracker-list .todo-checkbox {
        width: 16px;
        height: 16px;
        border-radius: 3px;
        border: 1px solid #6b7280;
        background: transparent;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
      }
      .tracker-list .todo-checkbox.done {
        background: #10b981;
        border-color: #10b981;
        color: white;
      }
      .tracker-list .todo-checkbox.in-progress {
        border-color: #3b82f6;
        background: rgba(59, 130, 246, 0.2);
      }
      .tracker-list .todo-text.done {
        text-decoration: line-through;
        color: #6b7280;
      }
      .tracker-list .file-icon {
        font-size: 14px;
      }
      .tracker-list .file-name {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
      .tracker-list .file-stats {
        font-size: 12px;
        display: flex;
        gap: 6px;
      }
      .tracker-list .file-undo-btn {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #ef4444;
        width: 20px;
        height: 20px;
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        margin-left: 4px;
        transition: all 0.15s;
      }
      .tracker-list .file-undo-btn:hover {
        background: rgba(239, 68, 68, 0.2);
        border-color: #ef4444;
      }
      .tracker-list .file-undo-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
      }
      .tracker-list .spinner-small {
        width: 10px;
        height: 10px;
        border: 2px solid transparent;
        border-top-color: #ef4444;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
      }
      @keyframes spin {
        to { transform: rotate(360deg); }
      }
    `;
    document.head.appendChild(style);
  })();

  // ============ PROGRESS TRACKER (Todos & Files Changed) ============
  const TRACKER_TODOS_KEY = 'ginto_tracker_todos';
  const TRACKER_FILES_KEY = 'ginto_tracker_files';
  
  const progressTracker = (function() {
    let todos = [];
    let filesChanged = [];
    
    // Load from localStorage
    function loadFromStorage() {
      try {
        const storedTodos = localStorage.getItem(TRACKER_TODOS_KEY);
        if (storedTodos) todos = JSON.parse(storedTodos);
        const storedFiles = localStorage.getItem(TRACKER_FILES_KEY);
        if (storedFiles) filesChanged = JSON.parse(storedFiles);
      } catch (e) {
        console.debug('Failed to load tracker state:', e);
      }
    }
    
    // Save to localStorage
    function saveToStorage() {
      try {
        localStorage.setItem(TRACKER_TODOS_KEY, JSON.stringify(todos));
        localStorage.setItem(TRACKER_FILES_KEY, JSON.stringify(filesChanged));
      } catch (e) {
        console.debug('Failed to save tracker state:', e);
      }
    }
    
    function getElements() {
      return {
        container: document.getElementById('chat-progress-tracker'),
        todosToggle: document.getElementById('tracker-todos-toggle'),
        todosCount: document.getElementById('tracker-todos-count'),
        todosContent: document.getElementById('tracker-todos-content'),
        todosList: document.getElementById('tracker-todos-list'),
        filesToggle: document.getElementById('tracker-files-toggle'),
        filesStats: document.getElementById('tracker-files-stats'),
        filesActions: document.getElementById('tracker-files-actions'),
        filesContent: document.getElementById('tracker-files-content'),
        filesList: document.getElementById('tracker-files-list'),
        keepBtn: document.getElementById('tracker-keep-btn'),
        undoBtn: document.getElementById('tracker-undo-btn')
      };
    }
    
    function show() {
      const el = getElements();
      if (el.container) el.container.style.display = 'block';
    }
    
    function hide() {
      const el = getElements();
      if (el.container) el.container.style.display = 'none';
    }
    
    function updateTodos(newTodos) {
      todos = newTodos || [];
      const el = getElements();
      if (!el.todosList || !el.todosCount) return;
      
      const completed = todos.filter(t => t.done || t.status === 'completed').length;
      el.todosCount.textContent = `(${completed}/${todos.length})`;
      
      el.todosList.innerHTML = todos.map((todo, i) => {
        const isDone = todo.done || todo.status === 'completed';
        const isInProgress = todo.status === 'in-progress';
        const checkboxClass = isDone ? 'done' : (isInProgress ? 'in-progress' : '');
        const textClass = isDone ? 'done' : '';
        const checkmark = isDone ? '✓' : (isInProgress ? '◉' : '');
        return `<li>
          <span class="todo-checkbox ${checkboxClass}">${checkmark}</span>
          <span class="todo-text ${textClass}">${escapeHtml(todo.title || todo.text || `Task ${i+1}`)}</span>
        </li>`;
      }).join('');
      
      if (todos.length > 0) show();
      saveToStorage();
    }
    
    function addTodo(text, done = false) {
      todos.push({ text, done, status: done ? 'completed' : 'pending' });
      updateTodos(todos);
    }
    
    function setTodoDone(index, done = true) {
      if (todos[index]) {
        todos[index].done = done;
        todos[index].status = done ? 'completed' : 'pending';
        updateTodos(todos);
      }
    }
    
    function updateFiles(files) {
      filesChanged = files || [];
      const el = getElements();
      if (!el.filesList || !el.filesStats) return;
      
      let totalAdded = 0;
      let totalRemoved = 0;
      filesChanged.forEach(f => {
        totalAdded += f.added || 0;
        totalRemoved += f.removed || 0;
      });
      
      el.filesStats.innerHTML = filesChanged.length > 0 
        ? `<span class="added">+${totalAdded}</span><span class="removed">-${totalRemoved}</span>`
        : '';
      
      if (el.filesActions) {
        el.filesActions.style.display = filesChanged.length > 0 ? 'flex' : 'none';
      }
      
      el.filesList.innerHTML = filesChanged.map(file => {
        const icon = getFileIcon(file.path || file.name);
        const name = (file.path || file.name || '').split('/').pop();
        const filePath = file.path || file.name || '';
        const hasOriginal = file.originalContent !== null && file.originalContent !== undefined;
        return `<li data-path="${escapeHtml(filePath)}">
          <span class="file-icon">${icon}</span>
          <span class="file-name" title="${escapeHtml(filePath)}">${escapeHtml(name)}</span>
          <span class="file-stats">
            <span class="added">+${file.added || 0}</span>
            <span class="removed">-${file.removed || 0}</span>
          </span>
          ${hasOriginal ? `<button class="file-undo-btn" title="Undo changes to this file" data-path="${escapeHtml(filePath)}">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 10h12a5 5 0 0 1 5 5v0a5 5 0 0 1-5 5H8"/>
              <polyline points="7 14 3 10 7 6"/>
            </svg>
          </button>` : ''}
        </li>`;
      }).join('');
      
      // Attach undo handlers
      el.filesList.querySelectorAll('.file-undo-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
          e.stopPropagation();
          const path = btn.getAttribute('data-path');
          if (path) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-small"></span>';
            await undoFileChange(path);
          }
        });
      });
      
      if (filesChanged.length > 0) show();
      saveToStorage();
    }
    
    function addFileChange(path, added = 0, removed = 0, originalContent = null, newContent = null) {
      const existing = filesChanged.find(f => f.path === path);
      if (existing) {
        existing.added = (existing.added || 0) + added;
        existing.removed = (existing.removed || 0) + removed;
        // Update content but keep original from first change
        if (newContent !== null) existing.newContent = newContent;
      } else {
        filesChanged.push({ 
          path, 
          added, 
          removed, 
          originalContent: originalContent,
          newContent: newContent,
          timestamp: Date.now()
        });
      }
      updateFiles(filesChanged);
    }
    
    // Undo a specific file change
    async function undoFileChange(path) {
      const file = filesChanged.find(f => f.path === path);
      if (!file || file.originalContent === null || file.originalContent === undefined) {
        console.error('[Undo] No original content for:', path);
        if (window.playgroundEditor?.showToast) {
          window.playgroundEditor.showToast('Cannot undo: no original content saved', true);
        }
        return false;
      }
      
      try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || window.editorConfig?.csrfToken || '';
        const encodedPath = btoa(path);
        const res = await fetch('/editor/save', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            csrf_token: csrfToken,
            file: encodedPath,
            content: file.originalContent
          })
        });
        const data = await res.json();
        if (data.success) {
          // Remove this file from changed list
          filesChanged = filesChanged.filter(f => f.path !== path);
          updateFiles(filesChanged);
          
          // Update editor if this is the current file
          const editor = window.playgroundEditor || window.GintoEditor;
          const currentFile = editor?.getCurrentFile?.() || window.currentFile;
          if (currentFile && normalizeRepoPath(currentFile) === normalizeRepoPath(path)) {
            editor.setValue(file.originalContent);
          }
          
          if (window.refreshPreview) window.refreshPreview();
          if (window.refreshTree) window.refreshTree();
          if (window.playgroundEditor?.showToast) {
            window.playgroundEditor.showToast('Restored: ' + path.split('/').pop());
          }
          return true;
        } else {
          if (window.playgroundEditor?.showToast) {
            window.playgroundEditor.showToast('Undo failed: ' + (data.error || 'Unknown error'), true);
          }
          return false;
        }
      } catch (e) {
        console.error('[Undo] Error:', e);
        if (window.playgroundEditor?.showToast) {
          window.playgroundEditor.showToast('Undo failed: ' + e.message, true);
        }
        return false;
      }
    }
    
    // Undo ALL file changes
    async function undoAllChanges() {
      const filesToUndo = [...filesChanged];
      let successCount = 0;
      
      for (const file of filesToUndo) {
        if (file.originalContent !== null && file.originalContent !== undefined) {
          const success = await undoFileChange(file.path);
          if (success) successCount++;
        }
      }
      
      // Clear tracker
      todos = [];
      filesChanged = [];
      updateTodos([]);
      updateFiles([]);
      hide();
      
      if (window.playgroundEditor?.showToast) {
        window.playgroundEditor.showToast(`Restored ${successCount} file(s)`);
      }
    }
    
    function getFileIcon(path) {
      if (!path) return '📄';
      const ext = path.split('.').pop().toLowerCase();
      const icons = {
        'html': '🌐', 'htm': '🌐',
        'css': '🎨',
        'js': '📜', 'ts': '📘', 'jsx': '⚛️', 'tsx': '⚛️',
        'php': '🐘',
        'json': '📋',
        'md': '📝',
        'py': '🐍',
        'sql': '🗃️'
      };
      return icons[ext] || '📄';
    }
    
    function escapeHtml(str) {
      if (!str) return '';
      return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    
    function clear() {
      todos = [];
      filesChanged = [];
      const el = getElements();
      if (el.todosList) el.todosList.innerHTML = '';
      if (el.todosCount) el.todosCount.textContent = '(0/0)';
      if (el.filesList) el.filesList.innerHTML = '';
      if (el.filesStats) el.filesStats.innerHTML = '';
      if (el.filesActions) el.filesActions.style.display = 'none';
      hide();
      saveToStorage();
    }
    
    function init() {
      const el = getElements();
      
      // Load persisted state
      loadFromStorage();
      
      // Render persisted data
      if (todos.length > 0 || filesChanged.length > 0) {
        if (todos.length > 0) updateTodos(todos);
        if (filesChanged.length > 0) updateFiles(filesChanged);
      }
      
      // Toggle handlers
      if (el.todosToggle) {
        el.todosToggle.addEventListener('click', function(e) {
          e.preventDefault();
          const expanded = this.getAttribute('aria-expanded') === 'true';
          this.setAttribute('aria-expanded', !expanded);
          if (el.todosContent) {
            el.todosContent.style.display = expanded ? 'none' : 'block';
          }
        });
      }
      
      if (el.filesToggle) {
        el.filesToggle.addEventListener('click', function(e) {
          // Don't toggle if clicking action buttons
          if (e.target.closest('.tracker-actions')) return;
          e.preventDefault();
          const expanded = this.getAttribute('aria-expanded') === 'true';
          this.setAttribute('aria-expanded', !expanded);
          if (el.filesContent) {
            el.filesContent.style.display = expanded ? 'none' : 'block';
          }
        });
      }
      
      // Keep/Undo handlers
      if (el.keepBtn) {
        el.keepBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          // User accepted the changes - clear everything and hide tracker
          todos = [];
          filesChanged = [];
          updateTodos([]);
          updateFiles([]);
          // Also clear checkpoints since user accepted the changes
          if (typeof saveAiCheckpoints === 'function') {
            saveAiCheckpoints([]);
          }
          hide();
          if (window.playgroundEditor && window.playgroundEditor.showToast) {
            window.playgroundEditor.showToast('Changes accepted');
          }
        });
      }
      
      if (el.undoBtn) {
        el.undoBtn.addEventListener('click', async function(e) {
          e.stopPropagation();
          // Undo all file changes using stored original content
          if (filesChanged.length > 0) {
            this.disabled = true;
            this.innerHTML = '<span class="spinner-small"></span> Undoing...';
            await undoAllChanges();
            this.disabled = false;
            this.textContent = 'Undo';
          } else {
            if (window.playgroundEditor?.showToast) {
              window.playgroundEditor.showToast('No changes to undo');
            }
          }
        });
      }
    }
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init);
    } else {
      init();
    }
    
    return {
      show,
      hide,
      clear,
      updateTodos,
      addTodo,
      setTodoDone,
      updateFiles,
      addFileChange,
      undoFileChange,
      undoAllChanges,
      getTodos: () => [...todos],
      getFiles: () => [...filesChanged]
    };
  })();
  
  // Expose globally
  window.__gintoProgressTracker = progressTracker;

  // Parse todos from AI response text
  function parseTodosFromResponse(text) {
    if (!text || !window.__gintoProgressTracker) return;
    
    const todos = window.__gintoProgressTracker.getTodos() || [];
    let updated = false;
    
    // Look for <tasks> block with task list
    const tasksMatch = text.match(/<tasks>([\s\S]*?)<\/tasks>/i);
    if (tasksMatch) {
      const tasksContent = tasksMatch[1];
      const taskLines = tasksContent.split('\n').filter(line => line.trim());
      const newTodos = [];
      
      taskLines.forEach((line, index) => {
        // Match [ ] or [x] or plain text tasks
        const match = line.match(/^\s*\[([xX\s]?)\]\s*(.+)$/) || line.match(/^\s*[-•\d.]+\s*(.+)$/);
        if (match) {
          const isDone = match[1] && match[1].toLowerCase() === 'x';
          const title = (match[2] || match[1] || '').trim();
          if (title) {
            newTodos.push({
              title: title,
              done: isDone,
              status: isDone ? 'completed' : 'pending'
            });
          }
        }
      });
      
      if (newTodos.length > 0) {
        window.__gintoProgressTracker.updateTodos(newTodos);
        updated = true;
      }
    }
    
    // Look for <task-done>N</task-done> to mark tasks complete
    const doneMatches = text.matchAll(/<task-done>(\d+)<\/task-done>/gi);
    for (const match of doneMatches) {
      const taskNum = parseInt(match[1], 10);
      if (taskNum > 0 && taskNum <= todos.length) {
        window.__gintoProgressTracker.setTodoDone(taskNum - 1, true);
        updated = true;
      }
    }
    
    // Fallback: Look for sai-thinking blocks with TODO LIST
    if (!updated) {
      const todoListMatch = text.match(/<div class="sai-thinking">[^]*?TODO LIST[^]*?<\/div>/gi);
      if (todoListMatch) {
        const newTodos = [];
        todoListMatch.forEach(block => {
          const itemMatches = block.match(/(\d+)\.\s*\[?([^\]\n<]+)\]?/g);
          if (itemMatches) {
            itemMatches.forEach(item => {
              const match = item.match(/(\d+)\.\s*\[?([xX\s]?)\]?\s*(.+)/);
              if (match) {
                const done = match[2] && match[2].toLowerCase() === 'x';
                newTodos.push({
                  title: match[3].trim(),
                  done: done,
                  status: done ? 'completed' : 'pending'
                });
              }
            });
          }
        });
        if (newTodos.length > 0) {
          window.__gintoProgressTracker.updateTodos(newTodos);
        }
      }
    }
  }

  function scrollToBottom(container) {
    if (!container) return;
    container.scrollTop = container.scrollHeight;
  }

  function isScrolledToBottom(container, threshold) {
    // Always scroll to bottom to show latest response (VS Code style)
    return true;
  }

  // Strip AI task/planning tags that shouldn't be visible to user
  function stripTaskTags(text) {
    if (!text) return '';
    // Remove <tasks>...</tasks> blocks entirely
    text = text.replace(/<tasks>[\s\S]*?<\/tasks>/gi, '');
    // Remove <task-done>N</task-done> markers
    text = text.replace(/<task-done>\d+<\/task-done>/gi, '');
    // Remove any stray opening/closing tags
    text = text.replace(/<\/?tasks>/gi, '');
    text = text.replace(/<\/?task-done>/gi, '');
    return text.trim();
  }

  // Simple markdown to HTML converter
  function simpleMarkdownToHtml(md) {
    if (!md) return '';
    // Strip task tags first
    let content = stripTaskTags(md.trim());
    if (content.startsWith('```') && content.endsWith('```')) {
      content = content.replace(/^```\w*\n?/, '').replace(/\n?```$/, '');
    }
    
    // Extract and preserve sai-thinking blocks BEFORE escaping
    const thinkingBlocks = [];
    const thinkingPlaceholder = '___SAI_THINKING_BLOCK_';
    content = content.replace(/<div class="sai-thinking">([\s\S]*?)<\/div>/gi, function(match, inner) {
      const idx = thinkingBlocks.length;
      // Process inner content: preserve <strong> tags but escape the rest
      let processed = inner
        .replace(/<strong>/gi, '___STRONG_OPEN___')
        .replace(/<\/strong>/gi, '___STRONG_CLOSE___')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/___STRONG_OPEN___/g, '<strong>')
        .replace(/___STRONG_CLOSE___/g, '</strong>');
      
      // Convert line breaks to <br> and format lists
      processed = processed
        .split('\n')
        .map(line => {
          line = line.trim();
          if (!line) return '';
          // Checkbox items
          if (line.match(/^\[x\]/i)) {
            return '<div class="todo-item done">✓ ' + line.replace(/^\[x\]\s*/i, '') + '</div>';
          }
          if (line.match(/^\[\s?\]/)) {
            return '<div class="todo-item pending">○ ' + line.replace(/^\[\s?\]\s*/, '') + '</div>';
          }
          // Numbered items
          if (line.match(/^\d+\.\s/)) {
            return '<div class="todo-item">' + line + '</div>';
          }
          // Bullet points (dash, bullet, or asterisk)
          if (line.match(/^[-•*]\s/)) {
            return '<div class="todo-item">' + line.replace(/^[-•*]\s*/, '• ') + '</div>';
          }
          return '<div>' + line + '</div>';
        })
        .filter(l => l)
        .join('');
      
      thinkingBlocks.push('<div class="sai-thinking">' + processed + '</div>');
      return thinkingPlaceholder + idx + '___';
    });
    
    // Now escape the remaining content
    let html = content
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
    
    const lines = html.split('\n');
    const result = [];
    let inCodeBlock = false;
    let codeBlockLang = '';
    let codeBlockContent = [];
    let inTable = false;
    let tableRows = [];
    
    // Helper to flush accumulated table rows into HTML
    function flushTable() {
      if (tableRows.length === 0) return;
      let tableHtml = '<table class="md-table" style="border-collapse:collapse;width:100%;margin:12px 0;font-size:14px;">';
      tableRows.forEach((row, idx) => {
        // Skip separator rows (|---|---|)
        if (row.match(/^\|[\s\-:]+\|$/)) return;
        const cells = row.split('|').filter((c, i, arr) => i > 0 && i < arr.length - 1);
        const tag = idx === 0 ? 'th' : 'td';
        const bgStyle = idx === 0 ? 'background:#2d2d2d;font-weight:600;' : (idx % 2 === 0 ? 'background:#252525;' : '');
        tableHtml += '<tr>';
        cells.forEach(cell => {
          const cellContent = formatInline(cell.trim().replace(/&lt;br&gt;/gi, '<br>'));
          tableHtml += '<' + tag + ' style="padding:8px 12px;border:1px solid #3c3c3c;text-align:left;' + bgStyle + '">' + cellContent + '</' + tag + '>';
        });
        tableHtml += '</tr>';
      });
      tableHtml += '</table>';
      result.push(tableHtml);
      tableRows = [];
      inTable = false;
    }
    
    for (const line of lines) {
      if (line.startsWith('```')) {
        if (!inCodeBlock) {
          inCodeBlock = true;
          codeBlockLang = line.slice(3).trim();
          codeBlockContent = [];
        } else {
          const codeId = 'code-' + Math.random().toString(36).slice(2, 10);
          const codeContent = codeBlockContent.join('\n');
          // Add Copy and "Apply to Editor" buttons for code blocks
          const editor = window.playgroundEditor || window.GintoEditor;
          const hasFile = (editor && typeof editor.getCurrentFile === 'function' && editor.getCurrentFile()) || window.currentFile;
          const currentFilePath = (editor && typeof editor.getCurrentFile === 'function') ? editor.getCurrentFile() : window.currentFile;
          const copyButton = '<button class="code-action-btn copy-btn" data-code-id="' + codeId + '" title="Copy code">📋 Copy</button>';
          const applyButton = hasFile ? 
            '<button class="code-action-btn apply-btn" data-code-id="' + codeId + '" title="Apply this code to the current file">📝 Apply</button>' : 
            '';
          result.push('<div class="code-block-wrapper"><div class="code-header"><span class="code-lang">' + (codeBlockLang || 'code') + '</span><div class="code-header-buttons">' + copyButton + applyButton + '</div></div><pre><code class="language-' + codeBlockLang + '" id="' + codeId + '">' + codeContent + '</code></pre></div>');
          inCodeBlock = false;
        }
        continue;
      }
      if (inCodeBlock) {
        codeBlockContent.push(line);
        continue;
      }
      
      // Detect markdown table rows (lines starting and ending with |)
      if (line.trim().startsWith('|') && line.trim().endsWith('|')) {
        inTable = true;
        tableRows.push(line.trim());
        continue;
      } else if (inTable) {
        // End of table - flush it
        flushTable();
      }
      
      if (line.startsWith('### ')) {
        result.push('<h3>' + formatInline(line.slice(4)) + '</h3>');
      } else if (line.startsWith('## ')) {
        result.push('<h2>' + formatInline(line.slice(3)) + '</h2>');
      } else if (line.startsWith('# ')) {
        result.push('<h1>' + formatInline(line.slice(2)) + '</h1>');
      } else if (line.match(/^\* /)) {
        // Bullet point with asterisk
        result.push('<li>' + formatInline(line.slice(2)) + '</li>');
      } else if (line.match(/^- /)) {
        // Bullet point with dash
        result.push('<li>' + formatInline(line.slice(2)) + '</li>');
      } else if (line.match(/^\d+\. /)) {
        // Numbered list item
        result.push('<li>' + formatInline(line.replace(/^\d+\.\s*/, '')) + '</li>');
      } else if (line.trim() === '') {
        // empty line
      } else {
        result.push('<p>' + formatInline(line) + '</p>');
      }
    }
    
    // Flush any remaining table at end of content
    if (inTable) flushTable();
    
    let finalHtml = result.join('\n');
    
    // Restore thinking blocks
    thinkingBlocks.forEach((block, idx) => {
      finalHtml = finalHtml.replace(new RegExp('<p>' + thinkingPlaceholder + idx + '___</p>', 'g'), block);
      finalHtml = finalHtml.replace(new RegExp(thinkingPlaceholder + idx + '___', 'g'), block);
    });
    
    return finalHtml;
    
    function formatInline(text) {
      return text
        .replace(/&lt;br&gt;/gi, '<br>')  // Restore <br> tags that were escaped
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/`([^`]+)`/g, '<code>$1</code>');
    }
  }

  // Format reasoning text into timeline format (like /chat has)
  function formatReasoningHtml(text) {
    if (!text || !text.trim()) return '';
    
    // Escape HTML
    const escapeHtmlText = (str) => str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
    
    // Create a reasoning item with timeline dot and line
    const createReasoningItem = (content, isLast = false) => `<div class="reasoning-item${isLast ? ' reasoning-item-last' : ''}">
      <div class="reasoning-item-indicator">
        <div class="reasoning-item-dot${isLast ? ' reasoning-item-dot-green' : ''}"></div>
        <div class="reasoning-item-line"></div>
      </div>
      <div class="reasoning-item-text"><p>${escapeHtmlText(content)}</p></div>
    </div>`;
    
    // Map items with last-item flag
    const mapWithLast = (items) => items.map((item, i) => createReasoningItem(item, i === items.length - 1)).join('');
    
    // First, try to split by double newlines (explicit paragraphs)
    let paragraphs = text.split(/\n\n+/).map(p => p.trim()).filter(p => p);
    if (paragraphs.length > 1) {
      return mapWithLast(paragraphs.map(p => p.replace(/\n/g, ' ')));
    }
    
    // Otherwise, split by single newlines
    paragraphs = text.split(/\n/).map(p => p.trim()).filter(p => p);
    if (paragraphs.length > 1) {
      return mapWithLast(paragraphs);
    }
    
    // Split on sentence boundaries that indicate new reasoning steps
    const normalized = text.replace(/\s+/g, ' ').trim();
    const stepPattern = /([.!?])\s+(?=(The |User |But |However |Now |Let's |Let us |We |I |Need |Should |Could |Open |Search |Find |Check |Read |Visit |Look |Get |Try |Maybe |Also |Next |Then |First |Second |Third |Finally |Result|Found |Using |Based |According |After |Before |From |Provide |Use |This |That |It |Access|Blocked|Seems|Likely|Possibly|Could be|So ))/gi;
    
    const parts = normalized.split(stepPattern).filter(p => p && p.trim());
    if (parts.length > 1) {
      const steps = [];
      let current = '';
      for (let i = 0; i < parts.length; i++) {
        const part = parts[i].trim();
        if (/^[.!?]$/.test(part)) {
          current += part;
        } else {
          if (current && /^(The |User |But |However |Now |Let's |Let us |We |I |Need |Should |Could |Open |Search |Find |Check |Read |Visit |Look |Get |Try |Maybe |Also |Next |Then |First |Second |Third |Finally |Result|Found |Using |Based |According |After |Before |From |Provide |Use |This |That |It |Access|Blocked|Seems|Likely|Possibly|Could be|So )/i.test(part)) {
            steps.push(current.trim());
            current = part;
          } else {
            current += (current && !current.endsWith('.') && !current.endsWith('!') && !current.endsWith('?') ? ' ' : '') + part;
          }
        }
      }
      if (current.trim()) {
        steps.push(current.trim());
      }
      if (steps.length > 1) {
        return mapWithLast(steps);
      }
    }
    
    // Fallback: split long text by sentences
    if (normalized.length > 100) {
      const sentences = normalized.match(/[^.!?]+[.!?]+/g) || [];
      if (sentences.length >= 2) {
        const steps = [];
        let current = '';
        for (let i = 0; i < sentences.length; i++) {
          current += sentences[i];
          if (current.length > 80 || i === sentences.length - 1) {
            steps.push(current.trim());
            current = '';
          }
        }
        if (steps.length > 0) {
          return mapWithLast(steps);
        }
      }
    }
    
    // Single paragraph fallback
    return createReasoningItem(normalized, true);
  }

  // ============ TOOL CALL EXTRACTION & EXECUTION ============
  function tryParseJsonSafe(s) {
    if (!s) return null;
    if (typeof s !== 'string') return s;
    try { return JSON.parse(s); } catch (e) {
      try {
        const fixed = s.replace(/'(.*?)'/g, '"$1"').replace(/,\s*}/g, '}').replace(/,\s*]/g, ']');
        return JSON.parse(fixed);
      } catch (e2) { return null; }
    }
  }

  function extractToolCallFromText(s) {
    if (!s || typeof s !== 'string') return null;
    const trimmed = s.trim();
    
    // Check for XML-like function call format: <function>write_file{"path":"...", "content":"..."}</function>
    // or <function>write_file</function>{"path":"...", "content":"..."}
    const xmlFuncMatch = s.match(/<function>\s*(\w+)\s*(?:<\/function>)?\s*(\{[\s\S]*\})\s*(?:<\/function>)?/i);
    if (xmlFuncMatch) {
      const funcName = xmlFuncMatch[1];
      try {
        // The JSON might have escaped quotes - try to parse it
        let jsonStr = xmlFuncMatch[2];
        // Handle the case where content has newlines represented as \n
        const args = JSON.parse(jsonStr);
        return { name: funcName, arguments: args };
      } catch (e) {
        // Try to extract path and content manually
        const pathMatch = xmlFuncMatch[2].match(/"path"\s*:\s*"([^"]+)"/);
        const contentMatch = xmlFuncMatch[2].match(/"content"\s*:\s*"([\s\S]*?)(?:"\s*}|"\s*,)/);
        if (pathMatch) {
          let content = '';
          if (contentMatch) {
            content = contentMatch[1].replace(/\\n/g, '\n').replace(/\\"/g, '"').replace(/\\\\/g, '\\');
          }
          return { name: funcName, arguments: { path: pathMatch[1], content: content } };
        }
      }
    }
    
    // Check for simpler format: <function>name</function> followed by arguments
    const simpleFuncMatch = s.match(/<function>\s*(\w+)\s*<\/function>\s*[:\-]?\s*(\{[\s\S]*?\})/i);
    if (simpleFuncMatch) {
      try {
        const args = JSON.parse(simpleFuncMatch[2]);
        return { name: simpleFuncMatch[1], arguments: args };
      } catch (e) {}
    }
    
    try {
      if (trimmed.startsWith('{') && trimmed.endsWith('}')) {
        const j = JSON.parse(trimmed);
        if (j.tool_call) return j.tool_call;
        if (j.tool_calls && Array.isArray(j.tool_calls) && j.tool_calls.length) return j.tool_calls[0];
        if (j.function_call) return { name: j.function_call.name, arguments: tryParseJsonSafe(j.function_call.arguments) };
        if (j.tool && (j.tool.name || j.tool.arguments)) return { name: j.tool.name || j.tool, arguments: j.tool.arguments || {} };
      }
    } catch (e) {}

    const markers = ['"tool_call"', '"tool_calls"', '"function_call"', '"function-call"', '"tool"'];
    let found = false;
    for (const m of markers) if (s.indexOf(m) !== -1) { found = true; break; }
    if (!found) return null;

    const firstMarkerIdx = markers.map(m => s.indexOf(m)).filter(i => i >= 0).sort((a,b) => a-b)[0];
    if (firstMarkerIdx === undefined) return null;
    let start = s.lastIndexOf('{', firstMarkerIdx);
    if (start === -1) start = s.indexOf('{');
    if (start === -1) return null;
    let depth = 0; let end = -1;
    for (let i = start; i < s.length; i++) {
      const ch = s[i];
      if (ch === '{') depth++;
      else if (ch === '}') { depth--; if (depth === 0) { end = i; break; } }
    }
    if (end === -1) return null;
    const cand = s.slice(start, end + 1);
    try {
      const j = JSON.parse(cand);
      if (!j) return null;
      if (j.tool_call) return j.tool_call;
      if (j.tool_calls && Array.isArray(j.tool_calls) && j.tool_calls.length) return j.tool_calls[0];
      if (j.function_call) return { name: j.function_call.name, arguments: tryParseJsonSafe(j.function_call.arguments) };
      if (j.tool && (j.tool.name || j.tool.arguments)) return { name: j.tool.name || j.tool, arguments: j.tool.arguments || {} };
    } catch (e) {}
    return null;
  }

  // ============ FILE WRITE STREAMING ============

  // Inline typing playback tuning (exposed on window.__gintoInlineTypingConfig)
  const defaultInlineTypingConfig = Object.freeze({
    minDelay: 1,
    maxDelay: 3,
    deleteChunkMin: 20,
    deleteChunkSteps: 8,
    appendChunkMin: 100,
    appendChunkSteps: 10
  });

  function initInlineTypingConfig() {
    if (window.__gintoInlineTypingConfig && typeof window.__gintoInlineTypingConfig.get === 'function') {
      return window.__gintoInlineTypingConfig;
    }

    const state = { ...defaultInlineTypingConfig };

    const api = {
      defaults: { ...defaultInlineTypingConfig },
      get() {
        return { ...state };
      },
      set(updates = {}) {
        if (typeof updates.minDelay === 'number' && updates.minDelay >= 0) {
          state.minDelay = updates.minDelay;
        }
        if (typeof updates.maxDelay === 'number' && updates.maxDelay >= 0) {
          state.maxDelay = updates.maxDelay;
        }
        if (typeof updates.deleteChunkMin === 'number' && updates.deleteChunkMin > 0) {
          state.deleteChunkMin = Math.floor(updates.deleteChunkMin);
        }
        if (typeof updates.deleteChunkSteps === 'number' && updates.deleteChunkSteps > 0) {
          state.deleteChunkSteps = Math.max(1, Math.floor(updates.deleteChunkSteps));
        }
        if (typeof updates.appendChunkMin === 'number' && updates.appendChunkMin > 0) {
          state.appendChunkMin = Math.floor(updates.appendChunkMin);
        }
        if (typeof updates.appendChunkSteps === 'number' && updates.appendChunkSteps > 0) {
          state.appendChunkSteps = Math.max(1, Math.floor(updates.appendChunkSteps));
        }

        if (state.maxDelay < state.minDelay) {
          state.maxDelay = state.minDelay;
        }

        return api.get();
      },
      reset() {
        Object.assign(state, defaultInlineTypingConfig);
        return api.get();
      }
    };

    window.__gintoInlineTypingConfig = api;
    return api;
  }

  const inlineTypingConfig = initInlineTypingConfig();

  function normalizeRepoPath(path) {
    if (!path || typeof path !== 'string') return '';
    return path
      .replace(/\\/g, '/')
      .replace(/^\.\/+/, '')
      .replace(/\/{2,}/g, '/');
  }

  function pathsMatch(a, b) {
    return normalizeRepoPath(a) === normalizeRepoPath(b);
  }

  function applyInlineEditorContent(content) {
    if (typeof content !== 'string') return;
    const editor = window.playgroundEditor || window.GintoEditor;
    if (!editor) return;
    const currentFile = (editor && typeof editor.getCurrentFile === 'function') ? editor.getCurrentFile() : window.currentFile;
    const normalizedCurrent = normalizeRepoPath(currentFile);
    if (!normalizedCurrent || normalizedCurrent !== fileWriteState.pathNormalized) return;

    fileWriteState.newContent = content;
    queueInlineTyping(content);
  }

  function setEditorBuffer(value) {
    const editor = window.playgroundEditor || window.GintoEditor;
    if (editor && typeof editor.setValue === 'function') {
      editor.setValue(value);
      if (typeof editor.setDirty === 'function') {
        editor.setDirty(true);
      }
    } else if (typeof textarea !== 'undefined' && textarea) {
      textarea.value = value;
    }
  }

  function queueInlineTyping(targetContent) {
    const editor = window.playgroundEditor;
    if (!editor) return;

    const currentDisplayed = fileWriteState.inlineTypingWorking !== null
      ? fileWriteState.inlineTypingWorking
      : getCurrentEditorValue();

    if (currentDisplayed === targetContent) {
      return;
    }

    const appliedConfig = inlineTypingConfig.get();
    const snapshots = buildInlineSnapshots(currentDisplayed, targetContent, appliedConfig);
    if (!snapshots.length) {
      return;
    }

    fileWriteState.inlineTypingQueue = snapshots;
    fileWriteState.inlineTypingWorking = currentDisplayed;
    fileWriteState.inlineTypingActive = true;
    fileWriteState.inlineTypingFlushOnDone = false;
    fileWriteState.inlineTypingConfig = appliedConfig;

    if (typeof editor.setDirty === 'function') {
      editor.setDirty(true);
    }

    if (!fileWriteState.inlineTypingTimer) {
      playNextInlineSnapshot();
    }
  }

  function buildInlineSnapshots(current, target, config) {
    const snapshots = [];
    if (current === target) return snapshots;

    const lcp = longestCommonPrefix(current, target);

    const effectiveConfig = config || inlineTypingConfig.get();
    const deleteChunkMin = Math.max(1, effectiveConfig.deleteChunkMin || defaultInlineTypingConfig.deleteChunkMin);
    const deleteChunkSteps = Math.max(1, effectiveConfig.deleteChunkSteps || defaultInlineTypingConfig.deleteChunkSteps);
    const appendChunkMin = Math.max(1, effectiveConfig.appendChunkMin || defaultInlineTypingConfig.appendChunkMin);
    const appendChunkSteps = Math.max(1, effectiveConfig.appendChunkSteps || defaultInlineTypingConfig.appendChunkSteps);

    let working = current;
    const deleteChunk = Math.max(deleteChunkMin, Math.ceil(Math.max(0, working.length - lcp) / deleteChunkSteps));
    while (working.length > lcp) {
      const nextLength = Math.max(lcp, working.length - deleteChunk);
      working = working.slice(0, nextLength);
      snapshots.push(working);
    }

    let appendLength = lcp;
    const appendTotal = Math.max(0, target.length - lcp);
    const appendChunk = Math.max(appendChunkMin, Math.ceil(appendTotal / appendChunkSteps));
    while (appendLength < target.length) {
      appendLength = Math.min(target.length, appendLength + appendChunk);
      snapshots.push(target.slice(0, appendLength));
    }

    if (snapshots.length === 0 || snapshots[snapshots.length - 1] !== target) {
      snapshots.push(target);
    }

    return snapshots;
  }

  function playNextInlineSnapshot() {
    if (!fileWriteState.inlineTypingQueue || fileWriteState.inlineTypingQueue.length === 0) {
      fileWriteState.inlineTypingTimer = null;
      if (fileWriteState.inlineTypingFlushOnDone) {
        clearInlineTyping();
      } else {
        fileWriteState.inlineTypingActive = false;
        fileWriteState.inlineTypingWorking = getCurrentEditorValue();
      }
      return;
    }

    const nextValue = fileWriteState.inlineTypingQueue.shift();
    setEditorBuffer(nextValue);
    fileWriteState.inlineTypingWorking = nextValue;

    const config = fileWriteState.inlineTypingConfig || inlineTypingConfig.get();
    const minDelay = Math.max(0, Number(config.minDelay) || defaultInlineTypingConfig.minDelay);
    const maxDelayCandidate = Math.max(minDelay, Number(config.maxDelay) || defaultInlineTypingConfig.maxDelay);
    const jitter = Math.max(0, maxDelayCandidate - minDelay);
    const delay = jitter > 0 ? (minDelay + Math.random() * jitter) : minDelay;
    fileWriteState.inlineTypingTimer = setTimeout(playNextInlineSnapshot, delay);
  }

  function clearInlineTyping() {
    if (fileWriteState.inlineTypingTimer) {
      clearTimeout(fileWriteState.inlineTypingTimer);
      fileWriteState.inlineTypingTimer = null;
    }
    fileWriteState.inlineTypingQueue = [];
    fileWriteState.inlineTypingActive = false;
    fileWriteState.inlineTypingWorking = null;
    fileWriteState.inlineTypingFlushOnDone = false;
    fileWriteState.inlineTypingConfig = null;
  }

  function getCurrentEditorValue() {
    const editor = window.playgroundEditor;
    if (editor && typeof editor.getValue === 'function') {
      return editor.getValue();
    }
    if (typeof textarea !== 'undefined' && textarea) {
      return textarea.value;
    }
    return '';
  }

  function longestCommonPrefix(a, b) {
    const maxLen = Math.min(a.length, b.length);
    let i = 0;
    while (i < maxLen && a.charCodeAt(i) === b.charCodeAt(i)) {
      i++;
    }
    return i;
  }

  function notifyInlineStart() {
    if (fileWriteState.inlineStartNotified) return;
    const editor = window.playgroundEditor;
    if (editor && editor.showToast) {
      editor.showToast('Streaming AI changes into editor...');
    }
    fileWriteState.inlineStartNotified = true;
  }

  function notifyInlineComplete() {
    if (fileWriteState.inlineCompleteNotified) return;
    const editor = window.playgroundEditor;
    if (editor && editor.showToast) {
      editor.showToast('AI changes applied to editor');
    }
    fileWriteState.inlineCompleteNotified = true;
  }

  // Shows a preview panel when the AI wants to write to a file
  var fileWriteState = {
    active: false,
    path: null,
    pathNormalized: null,
    originalContent: null,
    newContent: '',
    overlay: null,
    streaming: false,
    mode: 'overlay',
    inlineStartNotified: false,
    inlineCompleteNotified: false,
    inlineTypingQueue: [],
    inlineTypingTimer: null,
    inlineTypingWorking: null,
    inlineTypingActive: false,
    inlineTypingFlushOnDone: false,
    inlineTypingConfig: null
  };

  // ============ AI MODIFICATION CHECKPOINTS ============
  var AI_CHECKPOINT_KEY = 'playground-editor-ai-checkpoints';
  var AI_REDO_KEY = 'playground-editor-ai-redo-stack';

  function getAiCheckpoints() {
    try {
      var stored = localStorage.getItem(AI_CHECKPOINT_KEY);
      return stored ? JSON.parse(stored) : [];
    } catch (e) {
      return [];
    }
  }

  function saveAiCheckpoints(checkpoints) {
    try {
      localStorage.setItem(AI_CHECKPOINT_KEY, JSON.stringify(checkpoints));
    } catch (e) {
      console.error('Failed to save AI checkpoints:', e);
    }
  }

  function getRedoStack() {
    try {
      var stored = localStorage.getItem(AI_REDO_KEY);
      return stored ? JSON.parse(stored) : [];
    } catch (e) {
      return [];
    }
  }

  function saveRedoStack(stack) {
    try {
      localStorage.setItem(AI_REDO_KEY, JSON.stringify(stack));
    } catch (e) {
      console.error('Failed to save redo stack:', e);
    }
  }

  function clearRedoStack() {
    try {
      localStorage.removeItem(AI_REDO_KEY);
    } catch (e) {}
  }

  function createAiCheckpoint(path, originalContent, newContent, description) {
    var checkpoint = {
      id: Date.now().toString(36) + Math.random().toString(36).slice(2, 6),
      path: path,
      pathNormalized: normalizeRepoPath(path),
      originalContent: originalContent,
      newContent: newContent,
      description: description || 'AI modification',
      // Capture the last user prompt visible in the active tab's history so
      // we can offer it back to the user when restoring this checkpoint.
      triggerPrompt: (function(){
        try {
          var hist = chatTabs[activeTabId] && chatTabs[activeTabId].history ? chatTabs[activeTabId].history : conversationHistory || [];
          for (var i = hist.length - 1; i >= 0; i--) {
            if (hist[i] && hist[i].role === 'user' && hist[i].content) return String(hist[i].content);
          }
        } catch (e) {}
        return '';
      })(),
      timestamp: Date.now()
    };

    var checkpoints = getAiCheckpoints();
    checkpoints.unshift(checkpoint);

    // Keep only last 30 checkpoints
    if (checkpoints.length > 30) {
      checkpoints.length = 30;
    }

    saveAiCheckpoints(checkpoints);
    
    // Clear redo stack when new checkpoint is created (new timeline branch)
    clearRedoStack();
    
    // Track file change in progress tracker
    if (window.__gintoProgressTracker) {
      var originalLines = (originalContent || '').split('\n').length;
      var newLines = (newContent || '').split('\n').length;
      var added = Math.max(0, newLines - originalLines);
      var removed = Math.max(0, originalLines - newLines);
      // Rough estimate: if content changed significantly, count line differences
      if (originalContent !== newContent) {
        added = Math.max(added, 1);
      }
      window.__gintoProgressTracker.addFileChange(path, added, removed, originalContent, newContent);
    }
    
    return checkpoint;
  }

  function restoreAiCheckpoint(checkpointId, buttonWrapper) {
    var checkpoints = getAiCheckpoints();
    var checkpointIndex = checkpoints.findIndex(function(cp) { return cp.id === checkpointId; });
    if (checkpointIndex === -1) {
      console.error('Checkpoint not found:', checkpointId);
      return false;
    }
    
    var checkpoint = checkpoints[checkpointIndex];
    var editor = window.playgroundEditor || window.GintoEditor;
    var currentFile = (editor && typeof editor.getCurrentFile === 'function') ? editor.getCurrentFile() : window.currentFile;
    var normalizedCurrent = normalizeRepoPath(currentFile);

    // If restoring to the currently open file, update the editor directly
    if (checkpoint.pathNormalized === normalizedCurrent && editor && typeof editor.setValue === 'function') {
      // VS Code-like behavior: restoring to checkpoint N removes all checkpoints from N onwards
      // Checkpoints are stored newest first, so we keep only those AFTER this index (older ones)
      // If checkpoints = [3, 2, 1] (newest first) and we restore to 2 (index 1),
      // we keep only [1] (index 2 onwards = older checkpoints)
      var remainingCheckpoints = checkpoints.slice(checkpointIndex + 1);
      saveAiCheckpoints(remainingCheckpoints);
      clearRedoStack();
      
      // Apply the restore to editor
      editor.setValue(checkpoint.originalContent);
      if (typeof editor.setDirty === 'function') {
        editor.setDirty(true);
      }
      
      // Save the restored content to the sandbox via the editor save endpoint
      (async function() {
        try {
          var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || window.editorConfig?.csrfToken || '';
          if (checkpoint.path) {
            var encodedPath = btoa(checkpoint.path);
            var res = await fetch('/editor/save', {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: new URLSearchParams({
                csrf_token: csrfToken,
                file: encodedPath,
                content: checkpoint.originalContent
              })
            });
            var data = await res.json();
            if (data.success) {
              console.log('[Restore] File saved to sandbox:', checkpoint.path);
              if (window.refreshPreview) window.refreshPreview();
              if (window.refreshTree) window.refreshTree();
            } else {
              console.error('[Restore] Failed to save to sandbox:', data.error);
              if (editor.showToast) editor.showToast('Restore failed: ' + (data.error || 'Unknown error'), true);
            }
          }
        } catch (e) {
          console.error('[Restore] Save error:', e);
          if (editor.showToast) editor.showToast('Restore failed: ' + e.message, true);
        }
      })();
      
      if (editor.showToast) {
        editor.showToast('Restored: ' + checkpoint.path.split('/').pop());
      }
      // Populate the prompt with the user prompt that triggered this checkpoint
      try {
        var inputEl = document.getElementById('assistant-input') || document.getElementById('editor-chat-input');
        if (inputEl && checkpoint.triggerPrompt) {
          inputEl.value = checkpoint.triggerPrompt || '';
          // trigger autogrow and focus so user can edit/send
          try { inputEl.focus(); autoGrowTextarea(inputEl); } catch(e){}
        }
      } catch (e) {}
      
      // Replace the restore button with a small inline label confirming
      // restore (no redo available since forward history was cleared).
      if (buttonWrapper && buttonWrapper.parentNode) {
        var note = document.createElement('div');
        note.style.fontSize = '12px';
        note.style.color = '#9CA3AF';
        note.textContent = 'Restored';
        buttonWrapper.parentNode.replaceChild(note, buttonWrapper);
      }
      
      return true;
    }

    // Otherwise show a message that user needs to open the file first
    if (editor && editor.showToast) {
      editor.showToast('Open ' + checkpoint.path + ' to restore');
    }
    return false;
  }

  function redoAiCheckpoint(redoEntryId, buttonWrapper) {
    var redoStack = getRedoStack();
    var redoIndex = redoStack.findIndex(function(entry) { return entry.id === redoEntryId; });
    var redoEntry = null;
    
    if (redoIndex !== -1) {
      redoEntry = redoStack[redoIndex];
    } else if (buttonWrapper) {
      // Fallback: try to get embedded redo entry data from the button wrapper
      try {
        var embeddedData = buttonWrapper.getAttribute('data-redo-entry');
        if (embeddedData) {
          redoEntry = JSON.parse(embeddedData);
          console.log('Using embedded redo entry data');
        }
      } catch (e) {
        console.error('Failed to parse embedded redo entry:', e);
      }
    }
    
    if (!redoEntry) {
      console.error('Redo entry not found:', redoEntryId);
      var editor = window.playgroundEditor;
      if (editor && editor.showToast) {
        editor.showToast('Redo data not available (session expired)');
      }
      return false;
    }
    var checkpointsToRestore = redoEntry.checkpoints;
    if (!checkpointsToRestore || !checkpointsToRestore.length) {
      console.error('No checkpoints in redo entry');
      return false;
    }
    
    // Get the most recent checkpoint (the one that was restored from)
    var targetCheckpoint = checkpointsToRestore.find(function(cp) { return cp.id === redoEntry.restoredFrom; });
    if (!targetCheckpoint) {
      targetCheckpoint = checkpointsToRestore[checkpointsToRestore.length - 1];
    }
    
    var editor = window.playgroundEditor || window.GintoEditor;
    var currentFile = (editor && typeof editor.getCurrentFile === 'function') ? editor.getCurrentFile() : window.currentFile;
    var normalizedCurrent = normalizeRepoPath(currentFile);

    if (targetCheckpoint.pathNormalized === normalizedCurrent && editor && typeof editor.setValue === 'function') {
      // Restore the checkpoints back to main stack
      var currentCheckpoints = getAiCheckpoints();
      var restoredCheckpoints = checkpointsToRestore.concat(currentCheckpoints);
      saveAiCheckpoints(restoredCheckpoints);
      
      // Remove this entry from redo stack (only if it was in the stack)
      if (redoIndex !== -1) {
        redoStack.splice(redoIndex, 1);
        saveRedoStack(redoStack);
      }
      
      // Apply the new content (redo the change)
      editor.setValue(targetCheckpoint.newContent);
      if (typeof editor.setDirty === 'function') {
        editor.setDirty(true);
      }
      if (editor.showToast) {
        editor.showToast('Redo applied: ' + targetCheckpoint.path.split('/').pop());
      }
      
      // Replace the redo button with restore button again
      if (buttonWrapper && buttonWrapper.parentNode) {
        var restoreBtn = createRestoreCheckpointButton(targetCheckpoint.id, targetCheckpoint.path);
        buttonWrapper.parentNode.replaceChild(restoreBtn, buttonWrapper);
      }
      
      return true;
    }

    if (editor && editor.showToast) {
      editor.showToast('Open ' + targetCheckpoint.path + ' to redo');
    }
    return false;
  }

  function createRestoreCheckpointButton(checkpointId, path) {
    var wrapper = document.createElement('div');
    wrapper.className = 'restore-checkpoint-wrapper';
    wrapper.setAttribute('data-checkpoint-id', checkpointId);
    wrapper.innerHTML = '<button class="restore-checkpoint-btn" data-checkpoint-id="' + checkpointId + '" title="Restore to state before this AI modification">' +
      '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>' +
      '<span>Restore Checkpoint</span>' +
      '</button>';

    var btn = wrapper.querySelector('.restore-checkpoint-btn');
    btn.addEventListener('click', function() {
      var cpId = this.getAttribute('data-checkpoint-id');
      if (cpId) {
        restoreAiCheckpoint(cpId, wrapper);
      }
    });

    return wrapper;
  }

  function createRedoCheckpointButton(redoEntryId, path, redoEntry) {
    var wrapper = document.createElement('div');
    wrapper.className = 'restore-checkpoint-wrapper redo';
    wrapper.setAttribute('data-redo-id', redoEntryId);
    // Embed the redo entry data directly so it survives page reloads
    if (redoEntry) {
      try {
        wrapper.setAttribute('data-redo-entry', JSON.stringify(redoEntry));
      } catch (e) {
        console.warn('Failed to embed redo entry data:', e);
      }
    }
    wrapper.innerHTML = '<button class="redo-checkpoint-btn" data-redo-id="' + redoEntryId + '" title="Redo the AI modification">' +
      '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>' +
      '<span>Redo Checkpoint</span>' +
      '</button>';

    var btn = wrapper.querySelector('.redo-checkpoint-btn');
    btn.addEventListener('click', function() {
      var redoId = this.getAttribute('data-redo-id');
      if (redoId) {
        redoAiCheckpoint(redoId, wrapper);
      }
    });

    return wrapper;
  }

  function showFileWritePreview(path, content, isStreaming = false, originalOverride = null) {
    // Get current file content for comparison using the exposed API unless provided
    const editor = window.playgroundEditor;
    const currentFile = editor && typeof editor.getCurrentFile === 'function' ? editor.getCurrentFile() : window.currentFile;
    const currentMatchesTarget = pathsMatch(currentFile, path);
    const originalContent = (originalOverride !== null && originalOverride !== undefined)
      ? originalOverride
      : (currentMatchesTarget && editor && typeof editor.getValue === 'function' ? editor.getValue() : '');
    
    if (!currentMatchesTarget) {
      clearInlineTyping();
    }

    fileWriteState.active = true;
    fileWriteState.path = path;
    fileWriteState.pathNormalized = normalizeRepoPath(path);
    fileWriteState.originalContent = originalContent;
    fileWriteState.newContent = content;
    fileWriteState.streaming = isStreaming;
    fileWriteState.mode = currentMatchesTarget ? 'inline' : 'overlay';
    fileWriteState.inlineStartNotified = false;
    fileWriteState.inlineCompleteNotified = false;

    if (fileWriteState.mode === 'inline') {
      if (window.__gintoFileWrite && window.__gintoFileWrite.__lastInlineCompleted) {
        delete window.__gintoFileWrite.__lastInlineCompleted;
      }
      if (fileWriteState.overlay) {
        try { fileWriteState.overlay.remove(); } catch (e) {}
        fileWriteState.overlay = null;
      }
      if (isStreaming) notifyInlineStart();
      if (typeof content === 'string') {
        applyInlineEditorContent(content);
      }
      return;
    }

    // Create or update overlay
    if (!fileWriteState.overlay) {
      const overlay = document.createElement('div');
      overlay.className = 'file-write-overlay';
      overlay.innerHTML = `
        <div class="file-write-panel">
          <div class="file-write-header">
            <h3>
              <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
              </svg>
              <span class="title-text">Writing to: ${path}</span>
              <span class="status ${isStreaming ? 'streaming' : ''}">${isStreaming ? '● Streaming...' : '✓ Complete'}</span>
            </h3>
          </div>
          <div class="file-write-content">
            <div class="file-write-diff">
              <div class="diff-pane original">
                <div class="diff-header">Original</div>
                <pre class="diff-content original-content"></pre>
              </div>
              <div class="diff-pane modified">
                <div class="diff-header">New Content</div>
                <pre class="diff-content modified-content"></pre>
              </div>
            </div>
          </div>
          <div class="file-write-footer">
            <button class="reject">✕ Reject</button>
            <button class="accept" ${isStreaming ? 'disabled' : ''}>✓ Accept & Apply</button>
          </div>
        </div>
      `;
      document.body.appendChild(overlay);
      fileWriteState.overlay = overlay;

      // Event handlers
      overlay.querySelector('.reject').addEventListener('click', rejectFileWrite);
      overlay.querySelector('.accept').addEventListener('click', acceptFileWrite);
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) rejectFileWrite();
      });
    }

    // Update content
    const originalPre = fileWriteState.overlay.querySelector('.original-content');
    const modifiedPre = fileWriteState.overlay.querySelector('.modified-content');
    const statusEl = fileWriteState.overlay.querySelector('.status');
    const acceptBtn = fileWriteState.overlay.querySelector('.accept');
    const titleEl = fileWriteState.overlay.querySelector('.title-text');

    originalPre.textContent = originalContent ? originalContent : '(empty file)';
    modifiedPre.innerHTML = escapeHtml(content) + (isStreaming ? '<span class="streaming-cursor"></span>' : '');
    titleEl.textContent = 'Writing to: ' + path;
    
    if (isStreaming) {
      statusEl.textContent = '● Streaming...';
      statusEl.className = 'status streaming';
      acceptBtn.disabled = true;
    } else {
      statusEl.textContent = '✓ Complete';
      statusEl.className = 'status';
      acceptBtn.disabled = false;
    }

    // Scroll modified content to bottom during streaming
    if (isStreaming) {
      modifiedPre.parentElement.scrollTop = modifiedPre.parentElement.scrollHeight;
    }
  }

  function updateFileWriteContent(content, isComplete = false, originalOverride = null) {
    if (!fileWriteState.active) return;
    if (!fileWriteState.overlay && fileWriteState.mode !== 'inline') return;
    
    fileWriteState.newContent = content;
    fileWriteState.streaming = !isComplete;
    if (fileWriteState.path) {
      fileWriteState.pathNormalized = normalizeRepoPath(fileWriteState.path);
    }

    if (fileWriteState.mode === 'inline') {
      if (!isComplete) notifyInlineStart();
      if (typeof content === 'string') {
        applyInlineEditorContent(content);
      }
      if (isComplete) {
        notifyInlineComplete();
        hideFileWritePreview();
      }
      if (originalOverride !== null && originalOverride !== undefined) {
        fileWriteState.originalContent = originalOverride;
      }
      return;
    }

    if (originalOverride !== null && originalOverride !== undefined) {
      fileWriteState.originalContent = originalOverride;
      const originalPre = fileWriteState.overlay.querySelector('.original-content');
      if (originalPre) {
        originalPre.textContent = originalOverride ? originalOverride : '(empty file)';
      }
    }

    const modifiedPre = fileWriteState.overlay.querySelector('.modified-content');
    const statusEl = fileWriteState.overlay.querySelector('.status');
    const acceptBtn = fileWriteState.overlay.querySelector('.accept');

    modifiedPre.innerHTML = escapeHtml(content) + (!isComplete ? '<span class="streaming-cursor"></span>' : '');
    
    if (isComplete) {
      statusEl.textContent = '✓ Complete';
      statusEl.className = 'status';
      acceptBtn.disabled = false;
    }

    // Auto-scroll during streaming
    if (!isComplete) {
      modifiedPre.parentElement.scrollTop = modifiedPre.parentElement.scrollHeight;
    }
  }

  function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function acceptFileWrite() {
    if (!fileWriteState.active) return;
    
    const content = fileWriteState.newContent;
    const path = fileWriteState.path;
    const normalizedTarget = fileWriteState.pathNormalized || normalizeRepoPath(path);
    const editor = window.playgroundEditor || window.GintoEditor;

    if (fileWriteState.mode === 'inline') {
      notifyInlineComplete();
      hideFileWritePreview();
      return;
    }

    if (editor) {
      const currentFile = (editor && typeof editor.getCurrentFile === 'function') ? editor.getCurrentFile() : window.currentFile;
      const normalizedCurrent = normalizeRepoPath(currentFile);

      if (
        currentFile &&
        normalizedCurrent &&
        normalizedTarget &&
        normalizedCurrent === normalizedTarget &&
        window.currentEncoded &&
        typeof editor.loadFile === 'function'
      ) {
        editor.loadFile(window.currentEncoded, currentFile);
      } else if (typeof editor.setValue === 'function') {
        editor.setValue(content);
        if (typeof editor.setDirty === 'function') {
          editor.setDirty(false);
        }
      }

      if (editor.showToast) {
        editor.showToast('Editor updated with AI changes');
      }
    } else {
      console.log('Applied changes to: ' + path);
    }

    hideFileWritePreview();
  }

  function rejectFileWrite() {
    const editor = window.playgroundEditor || window.GintoEditor;
    const original = fileWriteState.originalContent;
    const targetPath = fileWriteState.path;
    const normalizedTarget = fileWriteState.pathNormalized || normalizeRepoPath(targetPath);

    if (editor && typeof original === 'string') {
      const currentFile = (editor && typeof editor.getCurrentFile === 'function') ? editor.getCurrentFile() : window.currentFile;
      const normalizedCurrent = normalizeRepoPath(currentFile);

      if (
        currentFile &&
        normalizedCurrent &&
        normalizedTarget &&
        normalizedCurrent === normalizedTarget &&
        typeof editor.setValue === 'function'
      ) {
        editor.setValue(original);
        if (typeof editor.setDirty === 'function') {
          editor.setDirty(true);
        }
      }
    }

    hideFileWritePreview();

    if (editor && editor.showToast) {
      editor.showToast('Changes rejected — editor restored to previous content');
    }
  }

  function hideFileWritePreview() {
    if (fileWriteState.overlay) {
      fileWriteState.overlay.remove();
      fileWriteState.overlay = null;
    }
    if (fileWriteState.inlineTypingActive || (fileWriteState.inlineTypingQueue && fileWriteState.inlineTypingQueue.length)) {
      fileWriteState.inlineTypingFlushOnDone = true;
    } else {
      clearInlineTyping();
    }
    fileWriteState.active = false;
    fileWriteState.path = null;
    fileWriteState.pathNormalized = null;
    fileWriteState.originalContent = null;
    fileWriteState.newContent = '';
    fileWriteState.streaming = false;
    fileWriteState.mode = 'overlay';
    fileWriteState.inlineStartNotified = false;
    fileWriteState.inlineCompleteNotified = false;
    if (window.__gintoFileWrite && window.__gintoFileWrite.__lastInlineCompleted) {
      delete window.__gintoFileWrite.__lastInlineCompleted;
    }
  }

  // Detect file write tool calls and intercept them
  function detectFileWriteToolCall(text) {
    // Look for write_file, replace_in_file, or create_file tool calls
    const toolCall = extractToolCallFromText(text);
    if (!toolCall) return null;
    
    const name = toolCall.name || '';
    const args = toolCall.arguments || toolCall.args || {};
    
    if (['write_file', 'create_file'].includes(name) && args.path && args.content) {
      return { action: 'write', path: args.path, content: args.content };
    }
    
    if (name === 'replace_in_file' && args.path && args.oldText && args.newText) {
      return { action: 'replace', path: args.path, oldText: args.oldText, newText: args.newText };
    }
    
    return null;
  }

  // Expose for use in streaming response handling
  window.__gintoFileWrite = {
    show: showFileWritePreview,
    update: updateFileWriteContent,
    accept: acceptFileWrite,
    reject: rejectFileWrite,
    hide: hideFileWritePreview,
    detect: detectFileWriteToolCall,
    getState: () => fileWriteState
  };

  // Expose checkpoint functions globally
  window.__gintoCheckpoints = {
    getAll: getAiCheckpoints,
    getRedoStack: getRedoStack,
    create: createAiCheckpoint,
    restore: restoreAiCheckpoint,
    redo: redoAiCheckpoint,
    createRestoreButton: createRestoreCheckpointButton,
    createRedoButton: createRedoCheckpointButton,
    clearRedo: clearRedoStack
  };

  // ============ DELETE FILE CONFIRMATION GUARDRAIL ============
  function confirmDeleteFile(filePath) {
    return new Promise(function(resolve) {
      // Use SweetAlert2 if available, otherwise fallback to native confirm
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Delete File?',
          html: '<p>The AI is requesting to <strong>permanently delete</strong> this file:</p>' +
                '<code style="display:block;padding:8px;background:rgba(0,0,0,0.1);border-radius:4px;margin:10px 0;word-break:break-all;">' + 
                escapeHtmlForAlert(filePath) + '</code>' +
                '<p style="color:#ef4444;font-weight:500;">This action cannot be undone!</p>',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#dc2626',
          cancelButtonColor: '#6b7280',
          confirmButtonText: 'Yes, delete it',
          cancelButtonText: 'Cancel',
          focusCancel: true,
          customClass: {
            popup: 'ginto-delete-confirm-popup'
          }
        }).then(function(result) {
          resolve(result.isConfirmed === true);
        });
      } else {
        // Fallback to native confirm
        var confirmed = confirm('⚠️ DELETE FILE?\n\nThe AI wants to permanently delete:\n' + filePath + '\n\nThis cannot be undone. Continue?');
        resolve(confirmed);
      }
    });
  }

  function escapeHtmlForAlert(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  async function executeToolCall(toolCall) {
    if (!toolCall) throw new Error('invalid toolCall');
    let name = toolCall.name || toolCall.function?.name || toolCall.function_name || toolCall.tool || null;
    let args = toolCall.arguments || toolCall.args || toolCall.function?.arguments || {};
    if (typeof args === 'string') args = tryParseJsonSafe(args) || {};
    if (!name) throw new Error('toolCall missing name');

    // GUARDRAIL: Confirm before deleting files
    if (name === 'delete_file' || name === 'remove_file') {
      var filePath = args.path || args.file || args.filename || 'unknown file';
      var confirmed = await confirmDeleteFile(filePath);
      if (!confirmed) {
        return { success: false, message: 'File deletion cancelled by user', cancelled: true };
      }
    }

    // Intercept file write operations for the current file
    const editor = window.playgroundEditor || window.GintoEditor;
    const currentFile = (editor && typeof editor.getCurrentFile === 'function') ? editor.getCurrentFile() : window.currentFile;
    
    if (['write_file', 'create_file'].includes(name) && args.path && args.content) {
      if (currentFile && pathsMatch(currentFile, args.path)) {
        try { showFileWritePreview(args.path, '', true); } catch (e) { /* ignore */ }
      }
    }

    const body = { tool: name, args: args };
    // Use /sandbox/call for sandbox-prefixed tools (available to all users with sandbox)
    // Use /mcp/call for other tools (admin-only)
    const endpoint = name.startsWith('sandbox_') ? '/sandbox/call' : '/mcp/call';
    const res = await fetch(endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrf() }, body: JSON.stringify(body) });
    
    // Handle non-OK responses with special actions (login, upgrade)
    if (!res.ok) {
      const txt = await res.text().catch(()=>'(no body)');
      let errorData = null;
      try { errorData = JSON.parse(txt); } catch(e) {}
      
      // Check for special actions requiring user interaction
      if (errorData?.action === 'upgrade') {
        // Show premium upgrade modal or fallback to confirm
        if (typeof window.showUpgradeModal === 'function') {
          window.showUpgradeModal(errorData.error || 'This feature requires a Premium subscription.');
        } else {
          if (confirm((errorData.error || 'This feature requires Premium.') + '\n\nPremium starts at ₱200/week. Would you like to upgrade?')) {
            window.location.href = '/upgrade';
          }
        }
        throw new Error(errorData.error || 'Premium subscription required');
      }
      if (errorData?.action === 'login') {
        if (confirm((errorData.error || 'Please log in to continue.') + '\n\nWould you like to go to the login page?')) {
          window.location.href = '/login';
        }
        throw new Error(errorData.error || 'Login required');
      }
      
      throw new Error('HTTP ' + res.status + ': ' + txt);
    }
    const j = await res.json().catch(()=>null);
    return j;
  }

  // Debounced helper to trigger Monaco editor relayout without thrashing
  function debouncedMonacoLayout(ms) {
    try {
      if (!window) return;
      if (!window.__gintoMonacoLayoutT) window.__gintoMonacoLayoutT = null;
      var delay = (typeof ms === 'number') ? ms : 60;
      if (window.__gintoMonacoLayoutT) clearTimeout(window.__gintoMonacoLayoutT);
      window.__gintoMonacoLayoutT = setTimeout(function(){
        try { if (window.gintoMonacoEditor && typeof window.gintoMonacoEditor.layout === 'function') window.gintoMonacoEditor.layout(); } catch(e){}
        window.__gintoMonacoLayoutT = null;
      }, delay);
    } catch(e) { /* ignore */ }
  }

  // Format tool results with nice UI (matches /chat styling)
  function formatToolResult(toolName, result) {
    var data = result?.result || result;
    
    // Error case
    if (result?.error || data?.error) {
      var errorMsg = result?.error || data?.error;
      return '<div class="tool-result error" style="padding:12px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:8px;color:#fca5a5;margin:8px 0;">' +
        '<div style="display:flex;align-items:center;gap:8px;">' +
        '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' +
        '<span>' + escapeHtml(errorMsg) + '</span>' +
        '</div></div>';
    }
    
    // sandbox_list_files - show as a nice file tree
    if (toolName === 'sandbox_list_files' && data?.tree) {
      var tree = data.tree;
      var files = [];
      var folders = [];
      
      for (var name in tree) {
        if (tree[name].type === 'folder') {
          folders.push(name);
        } else {
          files.push(name);
        }
      }
      
      var html = '<div class="tool-result" style="margin:8px 0;">';
      html += '<p style="color:#d1d5db;margin-bottom:8px;">Here are your files:</p>';
      html += '<div style="border-radius:8px;padding:0;font-family:monospace;font-size:13px;">';
      
      // Show folders first
      folders.sort().forEach(function(folder) {
        html += '<div style="display:flex;align-items:center;gap:8px;color:#60a5fa;padding:2px 0;">' +
          '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>' +
          '<span>' + escapeHtml(folder) + '/</span></div>';
      });
      
      // Then files
      files.sort().forEach(function(file) {
        html += '<div style="display:flex;align-items:center;gap:8px;color:#d1d5db;padding:2px 0;">' +
          '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#6b7280;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>' +
          '<span>' + escapeHtml(file) + '</span></div>';
      });
      
      if (folders.length === 0 && files.length === 0) {
        html += '<div style="color:#6b7280;font-style:italic;">This folder is empty</div>';
      }
      
      html += '</div></div>';
      return html;
    }
    
    // sandbox_read_file - show file content (support text, images, PDF, and binary previews)
    if (toolName === 'sandbox_read_file' && data?.content !== undefined) {
      var path = data.path || 'file';
      var content = data.content || '';
      var encoding = data.encoding || '';
      var isBinary = data.is_binary || (encoding === 'base64');
      var ext = (path.split('.').pop() || '').toLowerCase();

      // Helper to map extension -> mime
      function extToMime(e) {
        var m = {
          'png': 'image/png', 'jpg': 'image/jpeg', 'jpeg': 'image/jpeg', 'gif': 'image/gif', 'webp': 'image/webp', 'svg': 'image/svg+xml',
          'pdf': 'application/pdf', 'docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'doc': 'application/msword', 'odt': 'application/vnd.oasis.opendocument.text'
        };
        return m[e] || 'application/octet-stream';
      }

      // If binary/base64 and image/pdf
      if (isBinary && ['png','jpg','jpeg','gif','webp','svg','pdf'].includes(ext)) {
        var mime = extToMime(ext);
        var dataUrl = 'data:' + mime + ';base64,' + content;
        if (ext === 'pdf') {
          return '<div class="tool-result" style="margin:8px 0;">' +
            '<p style="color:#d1d5db;margin-bottom:8px;">Preview of <code style="background:#374151;padding:2px 6px;border-radius:4px;">' + escapeHtml(path) + '</code>:</p>' +
            '<div style="border-radius:8px;overflow:hidden;border:1px solid rgba(148,163,184,0.12)">' +
              '<iframe src="' + dataUrl + '" style="width:100%;height:500px;border:0;" title="PDF preview"></iframe>' +
            '</div>' +
            '</div>';
        }

        // image
        return '<div class="tool-result" style="margin:8px 0;">' +
          '<p style="color:#d1d5db;margin-bottom:8px;">Preview of <code style="background:#374151;padding:2px 6px;border-radius:4px;">' + escapeHtml(path) + '</code>:</p>' +
          '<div style="border:1px solid rgba(148,163,184,0.08);padding:8px;border-radius:8px;text-align:center;">' +
            '<img src="' + dataUrl + '" alt="' + escapeHtml(path) + '" style="max-width:100%;max-height:480px;border-radius:6px;" />' +
          '</div>' +
        '</div>';
      }

      // For other binary container types (docx/doc/odt) show a download and Office viewer link
      if (isBinary && ['docx','doc','odt','rtf'].includes(ext)) {
        var sandboxId = window.editorConfig && window.editorConfig.sandboxId ? window.editorConfig.sandboxId : '';
        var clientUrl = sandboxId ? ('/clients/' + sandboxId + '/' + path.replace(/^\//, '')) : ('/clients/' + path.replace(/^\//, ''));
        var officeViewer = 'https://view.officeapps.live.com/op/view.aspx?src=' + encodeURIComponent(window.location.origin + clientUrl);
        return '<div class="tool-result" style="margin:8px 0;">' +
          '<p style="color:#d1d5db;margin-bottom:8px;">File: <code style="background:#374151;padding:2px 6px;border-radius:4px;">' + escapeHtml(path) + '</code></p>' +
          '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">' +
            '<a href="' + clientUrl + '" target="_blank" rel="noopener" style="padding:8px 12px;background:#1f2937;color:#fff;border-radius:6px;text-decoration:none;">Open / Download</a>' +
            '<a href="' + officeViewer + '" target="_blank" rel="noopener" style="padding:8px 12px;background:#2563eb;color:#fff;border-radius:6px;text-decoration:none;">View in Office Online</a>' +
          '</div>' +
        '</div>';
      }

      // Fallback: show as text (assume utf-8)
      var safeContent = encoding === 'base64' ? '(binary file - use Open/Download button)' : content;
      return '<div class="tool-result" style="margin:8px 0;">' +
        '<p style="color:#d1d5db;margin-bottom:8px;">Contents of <code style="background:#374151;padding:2px 6px;border-radius:4px;">' + escapeHtml(path) + '</code>:</p>' +
        '<pre style="background:rgba(31,41,55,0.5);border-radius:8px;padding:12px;font-size:13px;overflow-x:auto;max-height:300px;margin:0;"><code>' + escapeHtml(safeContent) + '</code></pre>' +
        '</div>';
    }
    
    // sandbox_write_file - confirm file was written with download link
    if (toolName === 'sandbox_write_file' && data?.success) {
      var path = data.path || 'file';
      var bytes = data.bytes_written || 0;
      var url = data.url || '/clients/' + path.replace(/^\//, '');
      return '<div class="tool-result success" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);border-radius:8px;color:#86efac;margin:8px 0;flex-wrap:wrap;">' +
        '<div style="display:flex;align-items:center;gap:8px;min-width:0;flex:1;">' +
        '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>' +
        '<span style="word-break:break-word;">Created <code style="background:#374151;padding:2px 6px;border-radius:4px;">' + escapeHtml(path) + '</code>' + (bytes > 0 ? ' (' + bytes + ' bytes)' : '') + '</span>' +
        '</div>' +
        '<a href="' + escapeHtml(url) + '" target="_blank" style="display:inline-flex;align-items:center;gap:4px;padding:6px 12px;background:rgba(34,197,94,0.2);border-radius:6px;color:#86efac;text-decoration:none;font-size:13px;white-space:nowrap;flex-shrink:0;">' +
        '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>' +
        'Open</a>' +
        '</div>';
    }
    
    // sandbox_delete - confirm file was deleted
    if (toolName === 'sandbox_delete' && data?.success) {
      var path = data.path || 'file';
      return '<div class="tool-result" style="display:flex;align-items:center;gap:8px;padding:12px;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);border-radius:8px;color:#fcd34d;margin:8px 0;">' +
        '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>' +
        '<span>Deleted <code style="background:#374151;padding:2px 6px;border-radius:4px;">' + escapeHtml(path) + '</code></span>' +
        '</div>';
    }
    
    // sandbox_exec - show command output
    if (toolName === 'sandbox_exec') {
      var output = data?.output || data?.stdout || '';
      var exitCode = data?.exit_code ?? data?.exitCode ?? 0;
      var isError = exitCode !== 0;
      return '<div class="tool-result" style="margin:8px 0;">' +
        '<div style="display:flex;align-items:center;gap:8px;color:' + (isError ? '#fca5a5' : '#86efac') + ';margin-bottom:8px;">' +
        '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>' +
        '<span>Command ' + (isError ? 'failed' : 'completed') + '</span></div>' +
        (output ? '<pre style="background:rgba(31,41,55,0.5);border-radius:8px;padding:12px;font-size:13px;overflow-x:auto;max-height:200px;color:#d1d5db;margin:0;"><code>' + escapeHtml(output) + '</code></pre>' : '') +
        '</div>';
    }
    
    // Default: show success message
    if (data?.success) {
      var msg = data.message || 'Done!';
      return '<div class="tool-result success" style="display:flex;align-items:center;gap:8px;padding:12px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);border-radius:8px;color:#86efac;margin:8px 0;">' +
        '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>' +
        '<span>' + escapeHtml(msg) + '</span></div>';
    }
    
    // Fallback: show raw JSON in a collapsible
    return '<details style="margin:8px 0;"><summary style="cursor:pointer;color:#9ca3af;font-size:13px;">View raw result</summary>' +
      '<pre style="margin-top:4px;background:rgba(31,41,55,0.5);border-radius:8px;padding:8px;font-size:12px;overflow-x:auto;">' + escapeHtml(JSON.stringify(data, null, 2)) + '</pre></details>';
  }

  document.addEventListener('DOMContentLoaded', function(){
    var pane = document.getElementById('assistant-pane');
    var panel = document.getElementById('editor-chat-panel') || pane;
    var body = document.getElementById('assistant-body') || document.getElementById('editor-chat-body');
    var input = document.getElementById('assistant-input') || document.getElementById('editor-chat-input');
    var sendBtn = document.getElementById('assistant-send') || document.getElementById('editor-chat-send');
    
    // AbortController for stopping streaming responses
    var currentAbortController = null;
    var isStreaming = false;
    
    // Store original send button HTML
    var sendBtnOriginalHTML = sendBtn ? sendBtn.innerHTML : '';
    var stopBtnHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>';

    if (!panel || !body || !input || !sendBtn) return;
    
    // Function to toggle button between send and stop
    function setSendButtonMode(mode) {
      if (!sendBtn) return;
      if (mode === 'stop') {
        sendBtn.innerHTML = stopBtnHTML;
        sendBtn.title = 'Stop generating';
        sendBtn.classList.add('stop-mode');
        sendBtn.disabled = false;
      } else {
        sendBtn.innerHTML = sendBtnOriginalHTML;
        sendBtn.title = 'Send message';
        sendBtn.classList.remove('stop-mode');
      }
    }
    
    // Function to stop the current streaming response
    function stopStreaming() {
      if (currentAbortController) {
        currentAbortController.abort();
        currentAbortController = null;
      }
      isStreaming = false;
      setSendButtonMode('send');
      sendBtn.disabled = false;
      sendBtn.classList.remove('sending');
    }

    // ============ EVENT DELEGATION FOR CHECKPOINT BUTTONS ============
    // This handles clicks on restore/redo buttons even after page reload
    // when the buttons are restored from localStorage HTML but lose their listeners
    body.addEventListener('click', function(e) {
      // Handle restore checkpoint button clicks
      var restoreBtn = e.target.closest('.restore-checkpoint-btn');
      if (restoreBtn) {
        e.preventDefault();
        var cpId = restoreBtn.getAttribute('data-checkpoint-id');
        var wrapper = restoreBtn.closest('.restore-checkpoint-wrapper');
        if (cpId) {
          var result = restoreAiCheckpoint(cpId, wrapper);
          if (result === false) {
            // Checkpoint not found - remove the stale button
            if (wrapper) {
              wrapper.innerHTML = '<span style="color:#6b7280;font-size:12px;font-style:italic;">Checkpoint expired</span>';
              setTimeout(function() { wrapper.remove(); }, 3000);
            }
          }
        }
        return;
      }
      
      // Handle redo checkpoint button clicks
      var redoBtn = e.target.closest('.redo-checkpoint-btn');
      if (redoBtn) {
        e.preventDefault();
        var redoId = redoBtn.getAttribute('data-redo-id');
        var wrapper = redoBtn.closest('.restore-checkpoint-wrapper');
        if (redoId) {
          var result = redoAiCheckpoint(redoId, wrapper);
          if (result === false) {
            // Redo entry not found - remove the stale button
            if (wrapper) {
              wrapper.innerHTML = '<span style="color:#6b7280;font-size:12px;font-style:italic;">Redo expired</span>';
              setTimeout(function() { wrapper.remove(); }, 3000);
            }
          }
        }
        return;
      }
      
      // Handle code block copy button clicks
      var copyBtn = e.target.closest('.code-action-btn.copy-btn');
      if (copyBtn) {
        e.preventDefault();
        e.stopPropagation();
        var codeId = copyBtn.getAttribute('data-code-id');
        var codeEl = document.getElementById(codeId);
        if (codeEl) {
          var codeText = codeEl.textContent || '';
          var copySuccess = function() {
            copyBtn.textContent = '✓ Copied';
            setTimeout(function() {
              copyBtn.textContent = '📋 Copy';
            }, 1500);
          };
          
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(codeText).then(copySuccess).catch(function() {
              // Fallback - position textarea off-screen to prevent scroll jump
              var textarea = document.createElement('textarea');
              textarea.value = codeText;
              textarea.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;pointer-events:none;';
              document.body.appendChild(textarea);
              textarea.focus({preventScroll: true});
              textarea.select();
              try { document.execCommand('copy'); copySuccess(); } catch (e) {}
              document.body.removeChild(textarea);
            });
          } else {
            var textarea = document.createElement('textarea');
            textarea.value = codeText;
            textarea.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;pointer-events:none;';
            document.body.appendChild(textarea);
            textarea.focus({preventScroll: true});
            textarea.select();
            try { document.execCommand('copy'); copySuccess(); } catch (e) {}
            document.body.removeChild(textarea);
          }
        }
        return;
      }
      
      // Handle message action button clicks (copy, read, regenerate)
      var msgActionBtn = e.target.closest('.msg-action-btn');
      if (msgActionBtn) {
        e.preventDefault();
        var action = msgActionBtn.dataset.action;
        var bubble = msgActionBtn.closest('.bubble');
        // Use sanitized text for copy/read to avoid copying UI chrome like 'Save/Copy' labels
        var bubbleText = bubble ? sanitizeBubbleTextForCopyElement(bubble) : '';
        
        if (action === 'copy') {
          var copySuccess = function() {
            msgActionBtn.innerHTML = '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
            setTimeout(function() {
              msgActionBtn.innerHTML = '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>';
            }, 1500);
          };
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(bubbleText).then(copySuccess).catch(function() {
              var textarea = document.createElement('textarea');
              textarea.value = bubbleText;
              textarea.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;pointer-events:none;';
              document.body.appendChild(textarea);
              textarea.focus({preventScroll: true});
              textarea.select();
              try { document.execCommand('copy'); copySuccess(); } catch (e) {}
              document.body.removeChild(textarea);
            });
          } else {
            var textarea = document.createElement('textarea');
            textarea.value = bubbleText;
            textarea.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;pointer-events:none;';
            document.body.appendChild(textarea);
            textarea.focus({preventScroll: true});
            textarea.select();
            try { document.execCommand('copy'); copySuccess(); } catch (e) {}
            document.body.removeChild(textarea);
          }
        } else if (action === 'read') {
          if (window.__gintoAudio && typeof window.__gintoAudio.speak === 'function') {
            window.__gintoAudio.speak(bubbleText);
          } else if (window.speechSynthesis) {
            var utter = new SpeechSynthesisUtterance(bubbleText);
            window.speechSynthesis.speak(utter);
          }
        } else if (action === 'regenerate') {
          // Find the previous user message and resend
          var msgEl = msgActionBtn.closest('.assistant-message');
          var allMessages = msgEl && msgEl.parentElement ? msgEl.parentElement.querySelectorAll('.assistant-message.user') : [];
          var lastUserMsg = allMessages[allMessages.length - 1];
          if (lastUserMsg) {
            var userBubble = lastUserMsg.querySelector('.bubble');
            var userText = userBubble ? userBubble.textContent : '';
            // Remove this bot message
            if (msgEl) msgEl.remove();
            // Resend the message
            var input = document.getElementById('chat-input');
            if (input && userText) {
              input.value = userText;
              var sendBtn = document.getElementById('send-btn');
              if (sendBtn) sendBtn.click();
            }
          }
        }
        return;
      }
      
      // Handle code block apply button clicks
      var applyBtn = e.target.closest('.code-action-btn.apply-btn');
      if (applyBtn) {
        e.preventDefault();
        var codeId = applyBtn.getAttribute('data-code-id');
        var codeEl = document.getElementById(codeId);
        if (codeEl && window.__gintoFileWrite) {
          var codeText = codeEl.textContent || '';
          var currentFile = (window.playgroundEditor && window.playgroundEditor.getCurrentFile) 
            ? window.playgroundEditor.getCurrentFile() 
            : window.currentFile;
          window.__gintoFileWrite.show(currentFile, codeText, false);
        }
        return;
      }
    });

    // autogrow helper: grow textarea up to max height, then make it scrollable
    // Uses an offscreen clone to measure required height without mutating
    // the live element during measurement (prevents caret/scroll jumps).
    function autoGrowTextarea(el, maxHeight) {
      try {
        if (!el) return;
        // Allow max height to be driven by CSS token if present (e.g. --assistant-token-maxheight)
        var cssRootMax = null;
        try { cssRootMax = window.getComputedStyle(document.documentElement).getPropertyValue('--assistant-token-maxheight'); } catch(e) { cssRootMax = null }
        var parsedCssMax = cssRootMax ? parseFloat(cssRootMax) : NaN;
        var requestedMax = (typeof maxHeight === 'number') ? maxHeight : (!isNaN(parsedCssMax) ? parsedCssMax : 300);
        var computed = window.getComputedStyle(el);
        var cssMax = computed.maxHeight && computed.maxHeight !== 'none' ? parseFloat(computed.maxHeight) : NaN;
        var maxH = !isNaN(cssMax) ? Math.min(requestedMax, cssMax) : requestedMax;

        // helper: measure using an offscreen clone so we don't touch the live textarea
        function measureWithClone() {
          var clone = document.createElement('textarea');
          // copy key visual metrics to the clone so wrapping/height match
          var style = clone.style;
          style.position = 'absolute';
          style.visibility = 'hidden';
          style.overflow = 'hidden';
          style.height = 'auto';
          style.left = '-9999px';
          style.top = '0';
          style.whiteSpace = 'pre-wrap';

          // Copy computed metrics that affect layout
          try {
            style.boxSizing = computed.boxSizing;
            style.width = el.offsetWidth + 'px';
            style.fontFamily = computed.fontFamily;
            style.fontSize = computed.fontSize;
            style.fontWeight = computed.fontWeight;
            style.lineHeight = computed.lineHeight;
            style.letterSpacing = computed.letterSpacing;
            style.paddingTop = computed.paddingTop;
            style.paddingBottom = computed.paddingBottom;
            style.paddingLeft = computed.paddingLeft;
            style.paddingRight = computed.paddingRight;
            style.borderTopWidth = computed.borderTopWidth;
            style.borderBottomWidth = computed.borderBottomWidth;
            style.borderLeftWidth = computed.borderLeftWidth;
            style.borderRightWidth = computed.borderRightWidth;
            style.whiteSpace = computed.whiteSpace || 'pre-wrap';
            clone.wrap = el.wrap || 'soft';
          } catch(e){}

          // copy value (ensure trailing newline to better match scrollHeight in some browsers)
          clone.value = (el.value || '') + '\n';
          document.body.appendChild(clone);
          var h = clone.scrollHeight;
          // if border-box, include borders in outer size
          if ((computed.boxSizing || '') === 'border-box') {
            h += (parseFloat(computed.borderTopWidth) || 0) + (parseFloat(computed.borderBottomWidth) || 0);
          }
          document.body.removeChild(clone);
          return h;
        }

        // If the textarea is empty, don't expand it to a large height — keep it at the
        // computed min-height so the UI stays compact (prevents large empty boxes on load).
        try {
          if (!((el.value || '').trim())) {
            var minH = computed.minHeight && computed.minHeight !== 'auto' ? parseFloat(computed.minHeight) : NaN;
            // Use computed minHeight when available, otherwise fall back to 1.2em equivalent in px
            var fallbackMin = !isNaN(minH) ? minH : (parseFloat(computed.fontSize || '13') * 1.2);
            requestAnimationFrame(function(){
              try {
                el.style.height = (fallbackMin || 18) + 'px';
                el.style.overflowY = 'hidden';
                el.dataset._lastHeight = String(fallbackMin || 18);
              } catch(e){}
            });
            return;
          }
        } catch(e) { /* ignore */ }

        var prev = el.dataset._lastHeight ? parseFloat(el.dataset._lastHeight) : null;
        // measure without touching the live element to avoid caret jumps
        var measured = measureWithClone();
        // small buffer for fractional pixels
        var buffer = 2;
        var minH = computed.minHeight && computed.minHeight !== 'auto' ? parseFloat(computed.minHeight) : 0;
        var newH = Math.min(measured + buffer, maxH);
        if (newH < minH) newH = minH;

        if (prev === null || Math.abs(prev - newH) > 1) {
          // apply in RAF to keep layout smooth
          requestAnimationFrame(function(){
            try {
              el.style.height = newH + 'px';
              el.style.overflowY = (measured + buffer > maxH) ? 'auto' : 'hidden';
              el.dataset._lastHeight = String(newH);
            } catch(e){}
          });
        } else {
          // still ensure overflow state correct even when not changing height
          el.style.overflowY = (measured + buffer > maxH) ? 'auto' : 'hidden';
        }

        try { if (typeof adjustLayout === 'function') adjustLayout(); } catch(e){}
      } catch(e) { /* ignore */ }
    }

    // make autogrow respond to paste/cut events that can affect the content
    try {
      if (input) {
        // per-textarea scheduler to debounce height recalculation (avoids shared timers)
        function scheduleAutoGrow(el, cap) {
          try {
            if (!el) return;
            if (el._agTimeout) clearTimeout(el._agTimeout);
            el._agTimeout = setTimeout(function(){ try { autoGrowTextarea(el, cap); } catch(e){} el._agTimeout = null; }, 40);
          } catch(e){}
        }

        // attach lightweight handlers (avoid keydown which fires before value updates)
        ['input','cut','paste'].forEach(function(ev){
          input.addEventListener(ev, function(){ scheduleAutoGrow(input, 400); });
        });

        // also run an initial sizing
        setTimeout(function(){ autoGrowTextarea(input, 400); }, 0);
      }
    } catch(e) { /* ignore */ }

    // watch for width changes on textarea to re-run autogrowth (wrapping changes lines)
    try {
      if (window.ResizeObserver) {
        var inputRO = new ResizeObserver(function(){ autoGrowTextarea(input, 400); });
        inputRO.observe(input);
      }
    } catch(e) { /* ignore */ }

    // initial send button state based on input content
    try { sendBtn.disabled = !((input.value || '').trim()); } catch(e) {}
    // set an initial height (defer slightly for layout)
    try { setTimeout(function(){ autoGrowTextarea(input, 400); }, 0); } catch(e) {}

    // keep send disabled/enabled in sync with input and auto-grow textarea
    input.addEventListener('input', function(){
      try {
        // Don't disable the button during streaming - it should stay enabled for stopping
        if (!isStreaming) {
          sendBtn.disabled = !((this.value||'').trim());
          if (sendBtn.disabled) sendBtn.setAttribute('aria-disabled','true'); else sendBtn.removeAttribute('aria-disabled');
        }
        // schedule autogrow rather than running synchronously to avoid layout thrash
        try { if (typeof scheduleAutoGrow === 'function') scheduleAutoGrow(this, 400); else autoGrowTextarea(this, 400); } catch(e){}
      } catch(e){}
    });

    function appendUserText(text) {
      try {
        var wasAtBottom = isScrolledToBottom(body, 48);
        body.appendChild(createMessageEl('user', text));
        if (wasAtBottom) scrollToBottom(body);
      } catch(e) { try { body.appendChild(createMessageEl('user', text)); } catch(e){} }
    }
    function appendBotText(text) {
      try {
        var wasAtBottom = isScrolledToBottom(body, 48);
        
        // Parse todos from AI thinking blocks BEFORE markdown conversion
        // (because the conversion modifies the sai-thinking structure)
        try {
          parseTodosFromResponse(String(text || ''));
        } catch(e) { console.debug('Todo parsing failed', e); }
        
        // Convert markdown to HTML for better rendering
        var htmlContent = simpleMarkdownToHtml(String(text || ''));
        body.appendChild(createMessageEl('bot', null, htmlContent));
        if (wasAtBottom) scrollToBottom(body);
        
        // Queue text for TTS playback
        try {
          if (window.__gintoAudio && window.__gintoAudio.enabled && typeof window.__gintoAudio.queueFragment === 'function') {
            window.__gintoAudio.queueFragment(text);
          }
        } catch(e) { console.debug('TTS queue failed', e); }
        // Check for tool calls and execute if auto-run is enabled
        try {
          var toolCall = extractToolCallFromText(text);
          if (toolCall) {
            var autoRun = localStorage.getItem('ginto_auto_run_tools') === '1';
            if (autoRun) {
              appendBotText('[Running tool: ' + toolCall.name + ']');
              executeToolCall(toolCall).then(function(result) {
                appendBotText('[Tool result] ' + (typeof result === 'string' ? result : JSON.stringify(result)));
              }).catch(function(err) {
                appendBotText('[Tool error] ' + (err?.message || err));
              });
            } else {
              appendBotText('[Tool call detected: ' + toolCall.name + '] Enable auto-run to execute.');
            }
          }
        } catch(e) { console.debug('Tool call detection failed', e); }
      } catch(e) { try { body.appendChild(createMessageEl('bot', String(text || ''))); } catch(e){} }
    }

    // ============ TAB UI MANAGEMENT ============
    var tabsContainer = pane ? pane.querySelector('#chat-tabs-container') : null;
    
    function saveCurrentTabMessages() {
      if (!chatTabs[activeTabId]) return;
      // Save the current body HTML
      chatTabs[activeTabId].messagesHtml = body.innerHTML;
      // Persist to localStorage
      saveTabsToStorage();
    }
    
    function switchToTab(tabId) {
      if (tabId === activeTabId) return;
      
      // Ensure the tab exists, if not create it
      if (!chatTabs[tabId]) {
        chatTabs[tabId] = { history: [], messagesHtml: '' };
      }
      
      // Save current tab state
      saveCurrentTabMessages();
      
      // Update active tab
      activeTabId = tabId;
      conversationHistory = chatTabs[tabId].history || [];
      
      // Update tab UI
      if (tabsContainer) {
        tabsContainer.querySelectorAll('.assistant-tab').forEach(function(t) {
          t.classList.remove('active');
          t.setAttribute('aria-pressed', 'false');
        });
        var activeTab = tabsContainer.querySelector('[data-tab-id="' + tabId + '"]');
        if (activeTab) {
          activeTab.classList.add('active');
          activeTab.setAttribute('aria-pressed', 'true');
        }
      }
      
      // Restore tab messages
      if (chatTabs[tabId] && chatTabs[tabId].messagesHtml) {
        body.innerHTML = chatTabs[tabId].messagesHtml;
      } else {
        body.innerHTML = '<div class="assistant-empty">No conversation yet — ask me about the open file.</div>';
      }
      
      // Persist state
      saveTabsToStorage();
      scrollToBottom(body);
    }
    
    function createNewTab() {
      var newId = nextTabId++;
      chatTabs[newId] = { history: [], messages: [], messagesHtml: '' };
      
      // Create tab button with proper structure
      var tabBtn = document.createElement('button');
      tabBtn.className = 'assistant-tab';
      tabBtn.setAttribute('data-tab-id', newId);
      tabBtn.setAttribute('aria-pressed', 'false');
      tabBtn.innerHTML = '<span class="tab-label">Chat ' + newId + '</span><span class="tab-close" title="Close tab">×</span>';
      
      // Tab click handler
      tabBtn.addEventListener('click', function(e) {
        if (e.target.classList.contains('tab-close')) {
          // Close tab
          e.stopPropagation();
          closeTab(newId);
        } else {
          switchToTab(newId);
        }
      });
      
      if (tabsContainer) {
        tabsContainer.appendChild(tabBtn);
      }
      
      // Switch to new tab
      switchToTab(newId);
      
      // Persist
      saveTabsToStorage();
      input.focus();
    }
    
    function closeTab(tabId) {
      // Can't close the last tab
      var tabCount = Object.keys(chatTabs).length;
      if (tabCount <= 1) return;
      
      // If closing active tab, switch to another first
      if (tabId === activeTabId) {
        var remainingIds = Object.keys(chatTabs).map(Number).filter(function(id) { return id !== tabId; });
        if (remainingIds.length > 0) {
          // Force the switch by temporarily setting activeTabId to null
          activeTabId = null;
          switchToTab(remainingIds[0]);
        }
      }
      
      // Remove from data
      delete chatTabs[tabId];
      
      // Remove from UI
      var tabBtn = tabsContainer ? tabsContainer.querySelector('[data-tab-id="' + tabId + '"]') : null;
      if (tabBtn) tabBtn.remove();
      
      // Persist
      saveTabsToStorage();
    }
    
    // Clear current chat (without closing tab)
    function clearCurrentChat() {
      if (!chatTabs[activeTabId]) return;
      chatTabs[activeTabId].history = [];
      chatTabs[activeTabId].messagesHtml = '';
      body.innerHTML = '<div class="assistant-empty">No conversation yet — ask me about the open file.</div>';
      input.value = '';
      saveTabsToStorage();
      input.focus();
      
      // Clear progress tracker
      if (window.__gintoProgressTracker) {
        window.__gintoProgressTracker.clear();
      }
    }
    
    // First tab already has close button in HTML, just add click handler
    var firstTab = tabsContainer ? tabsContainer.querySelector('.assistant-tab') : null;
    if (firstTab) {
      firstTab.addEventListener('click', function(e) {
        if (e.target.classList.contains('tab-close')) {
          e.stopPropagation();
          closeTab(1);
        } else {
          switchToTab(1);
        }
      });
    }
    
    // New tab button
    var newTabBtn = pane ? pane.querySelector('#new-chat-tab-btn') : null;
    if (newTabBtn) newTabBtn.addEventListener('click', createNewTab);
    
    // Clear chat button
    var clearChatBtn = pane ? pane.querySelector('#clear-chat-btn') : null;
    if (clearChatBtn) clearChatBtn.addEventListener('click', clearCurrentChat);
    
    // ============ RESTORE PERSISTED STATE ============
    // Restore tabs and messages from localStorage on page load
    (function restorePersistedState() {
      var tabIds = Object.keys(chatTabs).map(Number).sort(function(a, b) { return a - b; });
      
      // If there are stored tabs beyond tab 1, create their UI elements
      tabIds.forEach(function(tabId) {
        if (tabId === 1) return; // Tab 1 already exists in HTML
        
        var tabBtn = document.createElement('button');
        tabBtn.className = 'assistant-tab';
        tabBtn.setAttribute('data-tab-id', tabId);
        tabBtn.setAttribute('aria-pressed', 'false');
        tabBtn.innerHTML = '<span class="tab-label">Chat ' + tabId + '</span><span class="tab-close" title="Close tab">×</span>';
        
        tabBtn.addEventListener('click', function(e) {
          if (e.target.classList.contains('tab-close')) {
            e.stopPropagation();
            closeTab(tabId);
          } else {
            switchToTab(tabId);
          }
        });
        
        if (tabsContainer) {
          tabsContainer.appendChild(tabBtn);
        }
      });
      
      // Highlight the active tab
      if (tabsContainer) {
        tabsContainer.querySelectorAll('.assistant-tab').forEach(function(t) {
          var tid = parseInt(t.getAttribute('data-tab-id'), 10);
          if (tid === activeTabId) {
            t.classList.add('active');
            t.setAttribute('aria-pressed', 'true');
          } else {
            t.classList.remove('active');
            t.setAttribute('aria-pressed', 'false');
          }
        });
      }
      
      // Restore messages for the active tab
      if (chatTabs[activeTabId] && chatTabs[activeTabId].messagesHtml) {
        body.innerHTML = chatTabs[activeTabId].messagesHtml;
        scrollToBottom(body);
      }
    })();

    var closeBtn = pane ? pane.querySelector('.as-close') : null;
    if (closeBtn) closeBtn.addEventListener('click', function(){ if (panel.classList) panel.classList.add('collapsed'); });

    var expandBtn = pane ? pane.querySelector('.as-compact.expand') : null;
    if (expandBtn) expandBtn.addEventListener('click', function(){ if (panel.classList) panel.classList.toggle('collapsed'); });

    async function sendMessage() {
      const text = (input.value || '').trim();
      if (!text) return;
      
      // If already streaming, abort the previous request first
      if (isStreaming && currentAbortController) {
        currentAbortController.abort();
        currentAbortController = null;
        isStreaming = false;
      }
      
      const empty = body.querySelector('.assistant-empty'); if (empty) empty.remove();

      appendUserText(text);
      
      // Set streaming state BEFORE clearing input to prevent input handler from disabling button
      currentAbortController = new AbortController();
      isStreaming = true;
      setSendButtonMode('stop');
      
      input.value = '';
      try { setTimeout(function(){ autoGrowTextarea(input, 400); }, 0); } catch(e) {}

      // Add user message to active tab's history (ensure default file assumption is present)
      var activeHistory = chatTabs[activeTabId]?.history || conversationHistory;
      var defaultFileInstruction = 'You are an embedded editor assistant. Unless the user explicitly names a different file path, assume they want the currently open file. Confirm before touching any other file. When working on PHP files, preserve the literal closing tag "?>" when it exists and do not emit it HTML-escaped.';
      if (!activeHistory.some(function(msg){ return msg && msg.role === 'system' && msg.content === defaultFileInstruction; })) {
        activeHistory.unshift({ role: 'system', content: defaultFileInstruction });
      }
      activeHistory.push({ role: 'user', content: text });

      try {
        // Use FormData like /chat page does
        const form = new FormData();
        
        // Build prompt with file context if available
        let promptText = text;
        const editor = window.playgroundEditor || window.GintoEditor;
        const currentFile = (editor && typeof editor.getCurrentFile === 'function') ? editor.getCurrentFile() : window.currentFile;
        
        if (currentFile) {
          // Prepend file context so the model knows what file is open
          var defaultFileReminder = 'Default assumption: Apply requested actions to this current file unless the user specifies another path. When asked to modify, clear, or change the file, USE THE write_file OR replace_in_file TOOL — do not just describe the changes.';
          var phpReminder = (/\.php$/i.test(currentFile)) ? '\n[Important: Keep the literal closing tag "?>" (no HTML escaping) whenever the original file includes it.]' : '';
          promptText = `[Current file: ${currentFile}]\n[${defaultFileReminder}]${phpReminder}\n\n${text}`;
          
          // If user seems to be asking for file modifications, include the file content
          const lowerText = text.toLowerCase();
          const isModificationRequest = lowerText.includes('write') || 
                                         lowerText.includes('edit') || 
                                         lowerText.includes('modify') || 
                                         lowerText.includes('change') ||
                                         lowerText.includes('update') ||
                                         lowerText.includes('add') ||
                                         lowerText.includes('fix') ||
                                         lowerText.includes('refactor') ||
                                         lowerText.includes('clear') ||
                                         lowerText.includes('empty') ||
                                         lowerText.includes('replace') ||
                                         lowerText.includes('rewrite') ||
                                         lowerText.includes('remove line') ||
                                         lowerText.includes('remove function') ||
                                         lowerText.includes('remove method') ||
                                         lowerText.includes('remove class') ||
                                         lowerText.includes('remove code') ||
                                         lowerText.includes('delete line') ||
                                         lowerText.includes('delete function') ||
                                         lowerText.includes('delete method') ||
                                         lowerText.includes('delete class') ||
                                         lowerText.includes('delete code');
          
          if (isModificationRequest && editor && typeof editor.getContent === 'function') {
            const fileContent = editor.getContent();
            if (fileContent && fileContent.length < 50000) { // Only include if not too large
              promptText += `\n\n[File content of ${currentFile}]:\n\`\`\`\n${fileContent}\n\`\`\``;
            }
          }
        }
        
        form.append('prompt', promptText);
        try { form.append('history', JSON.stringify(activeHistory)); } catch (e) {}

        // Send to /chat endpoint (same as main chat page)
        const res = await fetch('/chat', {
          method: 'POST',
          credentials: 'same-origin',
          body: form,
          headers: {
            'X-CSRF-Token': getCsrf()
          },
          signal: currentAbortController ? currentAbortController.signal : undefined
        });

        const ct = (res.headers && res.headers.get) ? (res.headers.get('Content-Type') || '') : '';
        
        // Accumulated content for history
        let accumulatedContent = '';
        // Accumulated tool results HTML (to be prepended before text in final render)
        let accumulatedToolResults = '';
        // Accumulated reasoning text for timeline display
        let accumulatedReasoning = '';
        // Track if any file operations occurred (to refresh tree at end)
        let hadFileOperations = false;
        // Pending checkpoint buttons to add after stream ends
        let pendingCheckpointButtons = [];
        
        try {
          // The /chat endpoint returns text/plain with SSE-style data: lines
          // Read streaming body
          const reader = res.body?.getReader?.();
          if (reader) {
            const decoder = new TextDecoder('utf-8');
            let buf = '';
            let thinkingCleared = false;
            // Performance instrumentation: track time of last chunk arrival
            let __lastChunkAt = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
            // Create a temporary bot message container with thinking indicator
            var tempEl = createMessageEl('bot', '');
            var bubble = tempEl.querySelector('.bubble');
            if (bubble) {
              // Create structure with reasoning timeline container and response area
              bubble.innerHTML = '<div class="reasoning-container" style="display:none"></div>' +
                                 '<span class="thinking-indicator">Thinking<span class="dots"><span>.</span><span>.</span><span>.</span></span></span>' +
                                 '<div class="assistant-stream-text" style="margin-top:8px;white-space:pre-wrap"></div>';
            }
            body.appendChild(tempEl);
            scrollToBottom(body);
            
            // Helper to clear thinking indicator on first content
            function clearThinking() {
              if (!thinkingCleared) {
                thinkingCleared = true;
                var b = tempEl.querySelector('.bubble');
                if (b) {
                  // If the bubble contains a thoughts wrapper, keep that wrapper
                  // but clear any placeholder static text inside the stream area so
                  // fragments can be appended. Otherwise clear the bubble so live
                  // text can be appended into a fresh container.
                  var thoughtsStream = b.querySelector('.sai-thinking-stream');
                  var asmStream = b.querySelector('.assistant-stream-text');
                  if (thoughtsStream) thoughtsStream.textContent = '';
                  if (asmStream) asmStream.textContent = '';
                  if (!thoughtsStream && b && !asmStream) b.textContent = '';
                }
              }
            }

            // Buffering to reduce DOM thrash: accumulate small token chunks
            // and flush to DOM at short intervals (batching). This avoids
            // many small reflows when providers stream token-by-token.
            let __asmBuffer = '';
            let __thoughtsBuffer = '';
            let __flushScheduled = false;
            function __flushBuffers() {
              __flushScheduled = false;
              try {
                const b = tempEl.querySelector('.bubble');
                const asmStream = b ? b.querySelector('.assistant-stream-text') : null;
                const thoughtsStream = b ? b.querySelector('.sai-thinking-stream') : null;
                if (asmStream && __asmBuffer) {
                  asmStream.textContent += __asmBuffer;
                } else if (b && __asmBuffer && !asmStream) {
                  b.textContent += __asmBuffer;
                }
                if (thoughtsStream && __thoughtsBuffer) {
                  thoughtsStream.textContent += __thoughtsBuffer;
                  // Auto-scroll the thoughts container to show latest content
                  thoughtsStream.scrollTop = thoughtsStream.scrollHeight;
                }
                // Auto-scroll chat body to show latest streaming content (VS Code style)
                if (body && (__asmBuffer || __thoughtsBuffer)) {
                  scrollToBottom(body);
                }
              } catch (e) {
                console.debug('flushBuffers failed', e);
              } finally {
                __asmBuffer = '';
                __thoughtsBuffer = '';
              }
            }
            function __scheduleFlush() {
              if (!__flushScheduled) {
                __flushScheduled = true;
                setTimeout(__flushBuffers, 60);
              }
            }

            while (true) {
              const { value, done } = await reader.read();
              if (done) break;
              buf += decoder.decode(value, { stream: true });
              
              // Debug: log raw SSE data with timing
              if (value && value.length > 0) {
                try {
                  const now = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
                  const delta = Math.round(now - __lastChunkAt);
                  __lastChunkAt = now;
                  console.log('[SSE] Received chunk — buf length:', buf.length, 'chunkBytes:', value.length, 'ms since last:', delta);
                } catch (e) {
                  console.log('[SSE] Received chunk, buf length:', buf.length);
                }
              }

              // Process complete SSE event blocks separated by blank line
              while (buf.indexOf('\n\n') !== -1) {
                const idx = buf.indexOf('\n\n');
                const block = buf.slice(0, idx);
                buf = buf.slice(idx + 2);

                // Extract data: lines and concatenate
                const lines = block.split(/\r?\n/);
                let payload = '';
                lines.forEach(function(ln){ if (ln.indexOf('data:') === 0) payload += ln.replace(/^data:\s?/, '') + '\n'; });
                payload = payload.trim();
                if (!payload) continue;
                
                // Check for toolExecution before parsing
                const isToolExec = payload.indexOf('"toolExecution"') !== -1;
                console.log('[SSE] Parsed payload:', isToolExec ? 'TOOL EXECUTION: ' + payload.substring(0, 300) : payload.substring(0, 100));

                try {
                  const obj = JSON.parse(payload);
                  
                  // Handle tool execution notifications - reload file if current file was modified
                  if (obj && obj.toolExecution) {
                    console.log('[SSE] *** TOOL EXECUTION DETECTED ***', obj.status, obj.path);
                    const editor = window.playgroundEditor;
                    const currentFile = (editor && typeof editor.getCurrentFile === 'function') ? editor.getCurrentFile() : window.currentFile;
                    const status = obj.status || 'executing';
                    const result = obj.result || {};
                    const toolPath = obj.path || result.path || null;
                    const normalizedToolPath = normalizeRepoPath(toolPath);
                    const normalizedCurrent = normalizeRepoPath(currentFile);
                    // Support both 'original' and 'original_content' field names for backwards compatibility
                    const originalFromResult = (result.original !== undefined) ? result.original : 
                                              (result.original_content !== undefined) ? result.original_content : null;
                    const newContentFromResult = (typeof result.content === 'string') ? result.content : (typeof result.newContent === 'string' ? result.newContent : '');
                    
                    // Debug logging for tool execution
                    console.log('[ToolExec]', status, toolPath, {
                      hasResult: !!result,
                      resultKeys: Object.keys(result),
                      contentType: typeof result.content,
                      contentLength: (result.content || '').length,
                      newContentLength: newContentFromResult.length,
                      originalLength: (originalFromResult || '').length
                    });

                    if (toolPath) {
                      if (status === 'executing') {
                        try {
                          if (window.__gintoFileWrite) {
                            window.__gintoFileWrite.show(toolPath, newContentFromResult || '', true, originalFromResult);
                          }
                        } catch (e) { console.debug('File write preview start failed', e); }
                      } else if (status === 'completed') {
                        // Create a checkpoint before applying the modification
                        var checkpointCreated = null;
                        try {
                          if (window.__gintoFileWrite) {
                            const state = window.__gintoFileWrite.getState ? window.__gintoFileWrite.getState() : null;
                            const wasInline = state && state.mode === 'inline';
                            
                            // Create checkpoint with original content before applying changes
                            if (originalFromResult !== null && originalFromResult !== undefined) {
                              checkpointCreated = createAiCheckpoint(
                                toolPath,
                                originalFromResult,
                                newContentFromResult || '',
                                'AI modified ' + (toolPath ? toolPath.split('/').pop() : 'file')
                              );
                            }
                            
                            console.log('[ToolExec] Applying content, state.active:', state && state.active, 'pathsMatch:', state && pathsMatch(state.path, toolPath));
                            if (state && state.active && pathsMatch(state.path, toolPath)) {
                              console.log('[ToolExec] Calling update() with content length:', (newContentFromResult || '').length);
                              window.__gintoFileWrite.update(newContentFromResult || '', true, originalFromResult);
                            } else {
                              console.log('[ToolExec] Calling show() with content length:', (newContentFromResult || '').length);
                              window.__gintoFileWrite.show(toolPath, newContentFromResult || '', false, originalFromResult);
                            }
                            // Preserve inline detection for reload toast below
                            if (wasInline) {
                              window.__gintoFileWrite.__lastInlineCompleted = true;
                            } else if (window.__gintoFileWrite.__lastInlineCompleted) {
                              delete window.__gintoFileWrite.__lastInlineCompleted;
                            }
                          }
                        } catch (e) { console.debug('File write preview update failed', e); }

                        if (
                          toolPath &&
                          currentFile &&
                          normalizedToolPath &&
                          normalizedCurrent &&
                          normalizedToolPath === normalizedCurrent
                        ) {
                          console.log('File modified by tool:', toolPath);
                          setTimeout(function() {
                            if (window.currentEncoded && editor && typeof editor.loadFile === 'function') {
                              console.log('[ToolExec] Calling loadFile for:', window.currentEncoded, currentFile);
                              editor.loadFile(window.currentEncoded, currentFile).then(function() {
                                console.log('[ToolExec] loadFile completed, editor value length:', editor.getValue ? editor.getValue().length : 'N/A');
                              }).catch(function(err) {
                                console.error('[ToolExec] loadFile failed:', err);
                              });
                              const skipToast = window.__gintoFileWrite && window.__gintoFileWrite.__lastInlineCompleted;
                              if (skipToast && window.__gintoFileWrite) {
                                delete window.__gintoFileWrite.__lastInlineCompleted;
                              }
                              if (!skipToast && editor.showToast) {
                                editor.showToast('File updated by AI');
                              }
                            }
                            
                            // Add restore checkpoint button to chat after modification
                            if (checkpointCreated && body) {
                              var restoreBtn = createRestoreCheckpointButton(checkpointCreated.id, toolPath);
                              body.appendChild(restoreBtn);
                              if (isScrolledToBottom(body, 48)) scrollToBottom(body);
                            }
                          }, 400);
                        } else if (checkpointCreated && body) {
                          // Still add the button even if it's not the current file
                          var restoreBtn = createRestoreCheckpointButton(checkpointCreated.id, toolPath);
                          body.appendChild(restoreBtn);
                          if (isScrolledToBottom(body, 48)) scrollToBottom(body);
                        }
                      }
                    } else if (status === 'completed' && obj.tool_name) {
                      // For non-file tools (e.g., sandbox_list_files), display the result nicely
                      try {
                        var toolResultHtml = formatToolResult(obj.tool_name, result);
                        if (toolResultHtml) {
                          var resultNote = document.createElement('div');
                          resultNote.className = 'message bot';
                          resultNote.innerHTML = '<div class="bubble">' + toolResultHtml + '</div>';
                          body.appendChild(resultNote);
                          if (isScrolledToBottom(body, 48)) scrollToBottom(body);
                        }
                      } catch (e) { console.debug('Tool result display failed', e); }
                    }
                    continue;
                  }
                  
                  // Handle final HTML response from /chat endpoint
                  if (obj && obj.final && obj.html) {
                    // Flush any buffered fragments immediately so streamed thoughts
                    // are materialized before we replace the bubble with final HTML.
                    try { __flushBuffers(); } catch (e) {}
                    clearThinking();
                    var bubble = tempEl.querySelector('.bubble');
                    // If we have a recorded thoughts stream, append it as its own
                    // assistant message before replacing the bubble with final HTML
                    var thoughtsStream = tempEl.querySelector('.sai-thinking-stream');
                    if (thoughtsStream && thoughtsStream.textContent && thoughtsStream.textContent.trim()) {
                      try {
                        var thoughtsMsg = createMessageEl('bot', '', null);
                        thoughtsMsg.querySelector('.bubble').innerHTML = '<div class="sai-thinking"><strong>Thoughts</strong><pre style="white-space:pre-wrap;margin-top:8px">' + (typeof escapeHtml === 'function' ? escapeHtml(thoughtsStream.textContent) : thoughtsStream.textContent) + '</pre></div>';
                        body.appendChild(thoughtsMsg);
                      } catch (e) { console.debug('failed to append thoughts message', e); }
                    }
                    // Build final content with reasoning if present
                    var finalContent = '';
                    // Include reasoning from server's reasoningHtml (if we didn't stream it already)
                    if (obj.reasoningHtml && !accumulatedReasoning) {
                      finalContent += `<div class="reasoning-container" style="display:block">
                        <div class="reasoning-timeline">
                          <div class="reasoning-header reasoning-toggle" onclick="this.classList.toggle('collapsed'); this.nextElementSibling.classList.toggle('collapsed'); this.querySelector('.reasoning-chevron').classList.toggle('closed');">
                            <svg class="reasoning-chevron open" viewBox="0 0 20 20" fill="currentColor">
                              <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                            <span>Reasoning</span>
                          </div>
                          <div class="reasoning-content">${obj.reasoningHtml}</div>
                        </div>
                      </div>`;
                    } else if (accumulatedReasoning) {
                      // Use client-side accumulated reasoning
                      finalContent += `<div class="reasoning-container" style="display:block">
                        <div class="reasoning-timeline">
                          <div class="reasoning-header reasoning-toggle" onclick="this.classList.toggle('collapsed'); this.nextElementSibling.classList.toggle('collapsed'); this.querySelector('.reasoning-chevron').classList.toggle('closed');">
                            <svg class="reasoning-chevron open" viewBox="0 0 20 20" fill="currentColor">
                              <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                            <span>Reasoning</span>
                          </div>
                          <div class="reasoning-content">${formatReasoningHtml(accumulatedReasoning)}</div>
                        </div>
                      </div>`;
                    }
                    finalContent += obj.html;
                    finalContent += getActionButtonsHtml();
                    if (bubble) bubble.innerHTML = finalContent;
                    if (isScrolledToBottom(body, 48)) scrollToBottom(body);
                    
                    // Check for text-based tool calls in the final response and execute them
                    // This handles cases where the LLM outputs tool calls as text rather than structured calls
                    try {
                      var toolCall = extractToolCallFromText(accumulatedContent);
                      if (toolCall) {
                        console.log('[ToolExec] Detected text-based tool call:', toolCall.name);
                        var autoRun = localStorage.getItem('ginto_auto_run_tools') === '1';
                        if (autoRun || toolCall.name === 'write_file' || toolCall.name === 'replace_in_file' || toolCall.name === 'create_file') {
                          // Always auto-run file write operations since user is in editor context
                          var toolPath = toolCall.arguments?.path || toolCall.args?.path || null;
                          var toolContent = toolCall.arguments?.content || toolCall.args?.content || '';
                          var editorRef = window.playgroundEditor;
                          var activeFile = editorRef ? editorRef.getCurrentFile() : window.currentFile;
                          
                          console.log('[ToolExec] Executing tool:', toolCall.name, 'path:', toolPath);
                          
                          // Execute the tool
                          executeToolCall(toolCall).then(function(result) {
                            console.log('[ToolExec] Tool result:', result);
                            
                            // Create checkpoint for the modification
                            if (result && result.success !== false && toolPath) {
                              var checkpointCreated = createAiCheckpoint(
                                toolPath,
                                result.original || result.original_content || '',
                                result.content || toolContent,
                                'AI modified ' + (toolPath ? toolPath.split('/').pop() : 'file')
                              );
                              
                              // Add restore button
                              if (checkpointCreated && body) {
                                var restoreBtn = createRestoreCheckpointButton(checkpointCreated.id, toolPath);
                                body.appendChild(restoreBtn);
                                if (isScrolledToBottom(body, 48)) scrollToBottom(body);
                              }
                            }
                            
                            // Reload the file if it's the current file
                            var normalizedToolPath = normalizeRepoPath(toolPath);
                            var normalizedActive = normalizeRepoPath(activeFile);
                            if (normalizedToolPath && normalizedActive && normalizedToolPath === normalizedActive) {
                              setTimeout(function() {
                                if (window.currentEncoded && editorRef && typeof editorRef.loadFile === 'function') {
                                  console.log('[ToolExec] Reloading file after text-based tool execution');
                                  editorRef.loadFile(window.currentEncoded, activeFile);
                                  if (editorRef.showToast) {
                                    editorRef.showToast('File updated by AI');
                                  }
                                }
                              }, 300);
                            }
                          }).catch(function(err) {
                            console.error('[ToolExec] Tool execution failed:', err);
                          });
                        }
                      }
                    } catch(e) { console.debug('Text-based tool call detection failed', e); }
                    
                    continue;
                  }
                  
                  // Handle text fragments
                  var extracted = null;
                  if (obj && typeof obj === 'object') {
                    // Check if this is a tool result that should be formatted nicely
                    // Server sends: { tool_result: true, tool_name: '...', result: {...} }
                    if (obj.tool_result && obj.tool_name && obj.result) {
                      try {
                        var toolResultHtml = formatToolResult(obj.tool_name, obj.result);
                        if (toolResultHtml) {
                          // Accumulate tool results - they'll be rendered at stream end
                          accumulatedToolResults += toolResultHtml;
                          console.log('[SSE] Accumulated tool result:', obj.tool_name);
                        }
                        // Track file operations to refresh tree at end
                        if (obj.tool_name === 'sandbox_write_file' || obj.tool_name === 'sandbox_delete') {
                          hadFileOperations = true;
                        }
                        
                        // Create checkpoint for file write operations (for undo/restore support)
                        if (obj.tool_name === 'sandbox_write_file' && obj.result && obj.result.success) {
                          var toolResultPath = obj.result.path || (obj.tool_args && obj.tool_args.path);
                          var originalContent = obj.result.original_content !== undefined ? obj.result.original_content : 
                                               (obj.result.original !== undefined ? obj.result.original : null);
                          var newContent = obj.tool_args && obj.tool_args.content ? obj.tool_args.content : '';
                          
                          if (toolResultPath && originalContent !== null) {
                            var checkpoint = createAiCheckpoint(
                              toolResultPath,
                              originalContent,
                              newContent,
                              'AI modified ' + (toolResultPath ? toolResultPath.split('/').pop() : 'file')
                            );
                            if (checkpoint && body) {
                              // Store for adding button after stream ends
                              if (!pendingCheckpointButtons) pendingCheckpointButtons = [];
                              pendingCheckpointButtons.push({ id: checkpoint.id, path: toolResultPath, newContent: newContent });
                              console.log('[tool_result] Created checkpoint for:', toolResultPath);
                            }
                          }
                          
                          // Update editor if this file is currently open
                          var editorRef = window.playgroundEditor || window.GintoEditor;
                          var currentFileOpen = editorRef?.getCurrentFile?.() || window.currentFile;
                          if (currentFileOpen && newContent) {
                            var normalizedCurrent = normalizeRepoPath(currentFileOpen);
                            var normalizedWritten = normalizeRepoPath(toolResultPath);
                            if (normalizedCurrent === normalizedWritten) {
                              console.log('[SSE] AI modified current file, updating editor:', toolResultPath);
                              if (editorRef && typeof editorRef.setValue === 'function') {
                                editorRef.setValue(newContent);
                              }
                            }
                          }
                        }
                      } catch (e) { console.debug('Tool result display failed', e); }
                      continue;
                    }
                    // Also check for status === 'completed' format
                    if (obj.status === 'completed' && obj.tool_name && obj.result) {
                      try {
                        var toolResultHtml = formatToolResult(obj.tool_name, obj.result);
                        if (toolResultHtml) {
                          // Accumulate tool results - they'll be rendered at stream end
                          accumulatedToolResults += toolResultHtml;
                          console.log('[SSE] Accumulated tool result:', obj.tool_name);
                        }
                        // Track file operations to refresh tree at end
                        if (obj.tool_name === 'sandbox_write_file' || obj.tool_name === 'sandbox_delete') {
                          hadFileOperations = true;
                        }
                      } catch (e) { console.debug('Tool result display failed', e); }
                      continue;
                    }
                    // Handle reasoning fragments (from models like Gemini with reasoning/thinking)
                    if (obj.reasoning && typeof obj.reasoning === 'string') {
                      accumulatedReasoning += obj.reasoning;
                      // Update the reasoning container in real-time
                      try {
                        var reasoningContainer = tempEl.querySelector('.reasoning-container');
                        if (reasoningContainer) {
                          reasoningContainer.style.display = 'block';
                          var reasoningHtml = formatReasoningHtml(accumulatedReasoning);
                          reasoningContainer.innerHTML = `
                            <div class="reasoning-timeline">
                              <div class="reasoning-header">
                                <svg class="reasoning-chevron open" viewBox="0 0 20 20" fill="currentColor">
                                  <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                                <span>Reasoning</span>
                              </div>
                              <div class="reasoning-content">${reasoningHtml}</div>
                            </div>`;
                          // Auto-scroll the reasoning content to show latest
                          var reasoningContent = reasoningContainer.querySelector('.reasoning-content');
                          if (reasoningContent) {
                            reasoningContent.scrollTop = reasoningContent.scrollHeight;
                          }
                          if (isScrolledToBottom(body, 48)) scrollToBottom(body);
                        }
                      } catch (e) { console.debug('Reasoning update failed', e); }
                      continue;
                    }
                    // Skip internal messages that shouldn't be displayed
                    if (obj.toolExecution !== undefined || obj.tool_call || obj.error || obj.status === 'executing' || obj.tool_use) {
                      // These are internal tool execution notifications - don't display as text
                      console.log('[SSE] Skipping internal message:', Object.keys(obj).join(','));
                      continue;
                    }
                    if (obj.text && typeof obj.text === 'string') extracted = obj.text;
                    // Common LLM shape: choices[0].message.content
                    else if (Array.isArray(obj.choices) && obj.choices[0] && obj.choices[0].message && typeof obj.choices[0].message.content === 'string') extracted = obj.choices[0].message.content;
                    // Other common shape: choices[0].text
                    else if (Array.isArray(obj.choices) && obj.choices[0] && typeof obj.choices[0].text === 'string') extracted = obj.choices[0].text;
                    // Some providers put 'result.content' as string
                    else if (obj.result && typeof obj.result.content === 'string') extracted = obj.result.content;
                  }
                    if (extracted !== null) {
                    // Filter common sentinel tokens emitted by some providers
                    try {
                      var _t = (typeof extracted === 'string') ? extracted.trim() : '';
                      if (_t === '' || _t.toUpperCase() === '[DONE]' || _t.toUpperCase() === '[END]') {
                        // ignore sentinel-only fragments
                      } else {
                        clearThinking();
                        accumulatedContent += extracted;
                        // Parse for task updates in real-time
                        parseTodosFromResponse(accumulatedContent);
                        // Buffer fragments and flush periodically to avoid DOM thrash
                        __asmBuffer += extracted;
                        __thoughtsBuffer += extracted;
                        __scheduleFlush();
                        if (isScrolledToBottom(body, 48)) scrollToBottom(body);
                        // Queue for TTS
                        try {
                          if (window.__gintoAudio && window.__gintoAudio.enabled && typeof window.__gintoAudio.queueFragment === 'function') {
                            window.__gintoAudio.queueFragment(extracted);
                          }
                        } catch(e) {}
                      }
                    } catch (e) {
                      clearThinking();
                      var bubbleErr = tempEl.querySelector('.bubble'); if (bubbleErr) bubbleErr.textContent += extracted; if (isScrolledToBottom(body,48)) scrollToBottom(body);
                    }
                  } else if (obj && typeof obj === 'object') {
                    // Valid JSON but no extractable text - skip to avoid displaying raw JSON
                    console.log('[SSE] Skipping JSON object without text:', Object.keys(obj).join(','));
                  } else {
                        clearThinking();
                        var asmStream2 = tempEl.querySelector('.assistant-stream-text');
                        if (asmStream2) asmStream2.textContent += payload;
                        else { var bubble2 = tempEl.querySelector('.bubble'); if (bubble2) bubble2.textContent += payload; }
                        if (isScrolledToBottom(body,48)) scrollToBottom(body);
                    // Queue for TTS
                    try {
                      if (window.__gintoAudio && window.__gintoAudio.enabled && typeof window.__gintoAudio.queueFragment === 'function') {
                        window.__gintoAudio.queueFragment(payload);
                      }
                    } catch(e) {}
                  }
                } catch (e) {
                  // Non-JSON payload — only append if it doesn't look like a JSON object
                  // Skip payloads that look like raw JSON but failed to parse (likely malformed tool calls)
                  var trimmedPayload = payload.trim();
                  if (trimmedPayload.startsWith('{') || trimmedPayload.startsWith('[')) {
                    console.log('[SSE] Skipping malformed JSON payload');
                  } else {
                    clearThinking();
                    accumulatedContent += payload;
                    __asmBuffer += payload;
                    __thoughtsBuffer += payload;
                    __scheduleFlush();
                    if (isScrolledToBottom(body,48)) scrollToBottom(body);
                    // Queue for TTS
                    try {
                      if (window.__gintoAudio && window.__gintoAudio.enabled && typeof window.__gintoAudio.queueFragment === 'function') {
                        window.__gintoAudio.queueFragment(payload);
                      }
                    } catch(e) {}
                  }
                }
              }
            }

            // left-over buffer after stream end
            if (buf.trim()) {
              try { 
                const obj = JSON.parse(buf.trim()); 
                // Handle tool result in leftover buffer
                if (obj && obj.tool_result && obj.tool_name && obj.result) {
                  console.log('[SSE] Handling leftover tool result:', obj.tool_name);
                  const formattedHtml = formatToolResult(obj.tool_name, obj.result);
                  if (formattedHtml) {
                    accumulatedToolResults += formattedHtml;
                  }
                }
                // Skip internal messages
                else if (obj && (obj.toolExecution !== undefined || obj.tool_call || obj.error || obj.status === 'executing' || obj.tool_use)) {
                  console.log('[SSE] Skipping leftover internal message');
                } else if (obj && obj.text) { 
                  accumulatedContent += obj.text;
                } else if (obj && obj.html && obj.final) {
                  // Final HTML render - handled below
                } else { 
                  // Don't display raw JSON objects
                  console.log('[SSE] Skipping leftover JSON object');
                } 
              } catch(e) { 
                // Only append non-JSON looking text
                var trimmedBuf = buf.trim();
                if (!trimmedBuf.startsWith('{') && !trimmedBuf.startsWith('[')) {
                  accumulatedContent += buf;
                }
              }
            }
            
            // Final render: Reasoning first, then tool results, then text response
            if (accumulatedReasoning || accumulatedToolResults || accumulatedContent) {
              var bubble = tempEl.querySelector('.bubble');
              if (bubble) {
                // Clear thinking indicator completely
                clearThinking();
                // Build final HTML: reasoning + tool results + text response
                var finalHtml = '';
                // Add reasoning if accumulated during streaming
                if (accumulatedReasoning) {
                  finalHtml += `<div class="reasoning-container" style="display:block">
                    <div class="reasoning-timeline">
                      <div class="reasoning-header reasoning-toggle" onclick="this.classList.toggle('collapsed'); this.nextElementSibling.classList.toggle('collapsed'); this.querySelector('.reasoning-chevron').classList.toggle('closed');">
                        <svg class="reasoning-chevron open" viewBox="0 0 20 20" fill="currentColor">
                          <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                        <span>Reasoning</span>
                      </div>
                      <div class="reasoning-content">${formatReasoningHtml(accumulatedReasoning)}</div>
                    </div>
                  </div>`;
                }
                if (accumulatedToolResults) {
                  finalHtml += accumulatedToolResults;
                }
                if (accumulatedContent) {
                  // Convert markdown to HTML for proper rendering
                  finalHtml += '<div class="assistant-text-response">' + simpleMarkdownToHtml(accumulatedContent) + '</div>';
                }
                // Add action buttons directly (inline HTML)
                finalHtml += '<div class="message-actions" style="display:flex;gap:4px;margin-top:8px;">' +
                  '<button class="msg-action-btn" data-action="copy" title="Copy" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;padding:0;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);border-radius:6px;color:rgba(255,255,255,0.7);cursor:pointer;">' +
                    '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>' +
                  '</button>' +
                  '<button class="msg-action-btn" data-action="read" title="Read aloud" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;padding:0;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);border-radius:6px;color:rgba(255,255,255,0.7);cursor:pointer;">' +
                    '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>' +
                  '</button>' +
                  '<button class="msg-action-btn" data-action="regenerate" title="Regenerate" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;padding:0;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);border-radius:6px;color:rgba(255,255,255,0.7);cursor:pointer;">' +
                    '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>' +
                  '</button>' +
                '</div>';
                bubble.innerHTML = finalHtml;
                // Render LaTeX math expressions
                renderLatexInElement(bubble);
                if (isScrolledToBottom(body, 48)) scrollToBottom(body);
              }
            }
            
            // Add checkpoint restore buttons for any file operations that created checkpoints
            if (pendingCheckpointButtons && pendingCheckpointButtons.length > 0 && body) {
              pendingCheckpointButtons.forEach(function(cp) {
                var restoreBtn = createRestoreCheckpointButton(cp.id, cp.path);
                if (restoreBtn) {
                  body.appendChild(restoreBtn);
                }
              });
              if (isScrolledToBottom(body, 48)) scrollToBottom(body);
              console.log('[SSE] Added', pendingCheckpointButtons.length, 'checkpoint restore button(s)');
            }
            
            // Refresh file explorer if any file operations occurred
            if (hadFileOperations && window.refreshTree) {
              console.log('[SSE] Refreshing file tree after file operations');
              window.refreshTree();
            }
            
            // Refresh preview iframe if any file operations occurred
            if (hadFileOperations && window.refreshPreview) {
              console.log('[SSE] Refreshing preview after file operations');
              window.refreshPreview();
            }
            
            // Save assistant response to active tab's history
            if (accumulatedContent) {
              var activeHistory = chatTabs[activeTabId]?.history || conversationHistory;
              activeHistory.push({ role: 'assistant', content: accumulatedContent });
            }
            
            // Try to detect file proposal header on end via response headers
            const hdr = (res.headers && res.headers.get) ? res.headers.get('X-Assistant-File-Proposed') : null;
            if (hdr) {
              try { var prop = JSON.parse(atob(hdr)); showFileProposal(prop); } catch(e){}
            }
          } else {
            // Non-streaming fallback: read entire body
            const textBody = await res.text();
            appendBotText(textBody || 'No response');
            if (textBody) {
              var activeHistory = chatTabs[activeTabId]?.history || conversationHistory;
              activeHistory.push({ role: 'assistant', content: textBody });
            }
          }
        } catch (err) {
          // Don't show error message if user aborted the stream
          if (err && (err.name === 'AbortError' || (err.message && err.message.toLowerCase().includes('abort')))) {
            console.log('[SSE] Stream aborted by user');
          } else {
            appendBotText('Error reading response: ' + (err && err.message ? err.message : 'unknown'));
          }
        }
      } catch (err) {
        // Don't show error message if user aborted
        if (err && (err.name === 'AbortError' || (err.message && err.message.toLowerCase().includes('abort')))) {
          console.log('[Chat] Request aborted by user');
        } else {
          appendBotText('Request failed: ' + (err && err.message ? err.message : 'unknown'));
        }
      }
      finally { 
        isStreaming = false;
        currentAbortController = null;
        setSendButtonMode('send');
        sendBtn.disabled = false; 
        sendBtn.classList.remove('sending'); 
        // Persist conversation after each exchange
        saveCurrentTabMessages();
      }
    }

    // Present a file proposal UI with diff, full preview and commit-message input
    function showFileProposal(proposal) {
      try {
        if (!proposal || !proposal.path) return;
        var p = proposal;
        var existing = panel.querySelector('.assistant-file-proposal');
        if (existing) existing.remove();

        var box = document.createElement('div');
        box.className = 'assistant-file-proposal';
        box.style.cssText = 'position:relative;padding:10px;border-radius:8px;background:rgba(0,0,0,0.06);margin-top:8px;';

        var title = document.createElement('div'); title.style.fontWeight='600'; title.textContent = 'Assistant proposes file: ' + p.path;

        // Toolbar: actions + toggles
        var toolbar = document.createElement('div'); toolbar.style.display='flex'; toolbar.style.alignItems='center'; toolbar.style.gap='8px'; toolbar.style.marginTop='6px';
        var createBtn = document.createElement('button'); createBtn.className='as-apply'; createBtn.textContent='Create file';
        var prBtn = document.createElement('button'); prBtn.className='as-pr'; prBtn.textContent='Create PR';
        var dismiss = document.createElement('button'); dismiss.className='as-dismiss'; dismiss.textContent='Dismiss';

        // Diff toggle
        var diffToggle = document.createElement('button'); diffToggle.className='as-diff-toggle'; diffToggle.textContent='Show diff'; diffToggle.setAttribute('aria-pressed','false');
        // Full preview toggle
        var fullToggle = document.createElement('button'); fullToggle.className='as-full-toggle'; fullToggle.textContent='Full preview'; fullToggle.setAttribute('aria-pressed','false');

        toolbar.appendChild(createBtn); toolbar.appendChild(prBtn); toolbar.appendChild(diffToggle); toolbar.appendChild(fullToggle); toolbar.appendChild(dismiss);

        // Commit message input and suggestions
        var commitRow = document.createElement('div'); commitRow.style.display='flex'; commitRow.style.flexDirection='column'; commitRow.style.gap='6px'; commitRow.style.marginTop='8px';
        var commitInput = document.createElement('input'); commitInput.type='text'; commitInput.placeholder = 'Commit message (optional)'; commitInput.style.padding='8px'; commitInput.style.borderRadius='6px'; commitInput.style.border='1px solid rgba(0,0,0,0.12)';
        var suggestions = document.createElement('div'); suggestions.style.display='flex'; suggestions.style.gap='6px'; suggestions.style.flexWrap='wrap';
        var suggs = (p.suggested_commit_messages && Array.isArray(p.suggested_commit_messages) && p.suggested_commit_messages.length) ? p.suggested_commit_messages : [ 'Add file via assistant', 'Add ' + p.path, 'Create ' + p.path ];
        suggs.forEach(function(s){ var b = document.createElement('button'); b.className='as-commit-sugg'; b.textContent = s; b.style.fontSize='12px'; b.addEventListener('click', function(){ commitInput.value = s; }); suggestions.appendChild(b); });
        commitRow.appendChild(commitInput); commitRow.appendChild(suggestions);

        // Preview area: truncated by default, can expand
        var preview = document.createElement('pre'); preview.style.maxHeight = '240px'; preview.style.overflow='auto'; preview.style.margin='8px 0'; preview.style.whiteSpace = 'pre-wrap';
        var short = (p.content || '').substring(0, 2000);
        preview.textContent = short + ((p.content && p.content.length > 2000) ? '\n\n... (truncated) ...' : '');

        // Diff container (hidden until toggled)
        var diffContainer = document.createElement('div'); diffContainer.style.display='none'; diffContainer.style.marginTop='8px'; diffContainer.style.maxHeight='320px'; diffContainer.style.overflow='auto'; diffContainer.style.background='rgba(255,255,255,0.02)'; diffContainer.style.padding='8px'; diffContainer.style.borderRadius='6px';

        // Helper: naive line-by-line diff view if old_content provided
        function renderDiff(oldStr, newStr) {
          diffContainer.innerHTML = '';
          if (!oldStr) {
            var noOld = document.createElement('div'); noOld.textContent = 'No existing file to diff against.'; diffContainer.appendChild(noOld); return;
          }
          var oldLines = String(oldStr).split(/\r?\n/);
          var newLines = String(newStr).split(/\r?\n/);
          var max = Math.max(oldLines.length, newLines.length);
          var table = document.createElement('div'); table.style.fontFamily='monospace'; table.style.whiteSpace='pre';
          for (var i=0;i<max;i++) {
            var o = oldLines[i] || '';
            var n = newLines[i] || '';
            var row = document.createElement('div');
            if (o === n) {
              var el = document.createElement('span'); el.textContent = '  ' + n; row.appendChild(el);
            } else if (o && !n) {
              var el = document.createElement('span'); el.textContent = '- ' + o; el.style.color = '#d9534f'; row.appendChild(el);
            } else if (!o && n) {
              var el = document.createElement('span'); el.textContent = '+ ' + n; el.style.color = '#5cb85c'; row.appendChild(el);
            } else {
              var el1 = document.createElement('span'); el1.textContent = '- ' + o; el1.style.color='#d9534f'; var br = document.createElement('br'); var el2 = document.createElement('span'); el2.textContent = '+ ' + n; el2.style.color='#5cb85c'; row.appendChild(el1); row.appendChild(br); row.appendChild(el2);
            }
            table.appendChild(row);
          }
          diffContainer.appendChild(table);
        }

        box.appendChild(title); box.appendChild(toolbar); box.appendChild(commitRow); box.appendChild(preview); box.appendChild(diffContainer);
        panel.appendChild(box);

        // Wire toggles
        diffToggle.addEventListener('click', function(){
          try {
            var on = diffToggle.getAttribute('aria-pressed') === 'true';
            if (!on) {
              diffToggle.setAttribute('aria-pressed','true'); diffToggle.textContent = 'Hide diff';
              diffContainer.style.display = 'block';
              renderDiff(p.old_content || null, p.content || '');
            } else {
              diffToggle.setAttribute('aria-pressed','false'); diffToggle.textContent = 'Show diff';
              diffContainer.style.display = 'none';
            }
          } catch(e){}
        });

        fullToggle.addEventListener('click', function(){
          try {
            var on = fullToggle.getAttribute('aria-pressed') === 'true';
            if (!on) {
              fullToggle.setAttribute('aria-pressed','true'); fullToggle.textContent = 'Truncate preview'; preview.textContent = (p.content || ''); preview.style.maxHeight = 'none';
            } else {
              fullToggle.setAttribute('aria-pressed','false'); fullToggle.textContent = 'Full preview'; preview.textContent = short + ((p.content && p.content.length > 2000) ? '\n\n... (truncated) ...' : ''); preview.style.maxHeight = '240px';
            }
          } catch(e){}
        });

        createBtn.addEventListener('click', async function(){
          try {
            createBtn.disabled = true; createBtn.textContent = 'Creating...';
            const message = (commitInput && commitInput.value) ? commitInput.value : ('Add ' + p.path);
            const res = await fetch('/admin/pages/editor/file', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': getCsrf() }, body: JSON.stringify({ filename: p.path, content: p.content || '', overwrite: false, message: message }) });
            const j = await res.json();
            if (j && j.success) { showBanner('File created: ' + p.path, 'success', 3000); box.remove(); }
            else { showBanner('Create failed: ' + (j.message||'unknown'), 'error', 4000); createBtn.disabled=false; createBtn.textContent='Create file'; }
          } catch(e){ showBanner('Create failed: ' + (e && e.message?e.message:'unknown'), 'error', 4000); createBtn.disabled=false; createBtn.textContent='Create file'; }
        });

        prBtn.addEventListener('click', async function(){
          try {
            prBtn.disabled = true; prBtn.textContent = 'Creating PR...';
            const branch = 'mcp/autogen-' + Math.random().toString(36).slice(2,8);
            var repoFull = null;
            try { var m = document.querySelector('meta[name="repo-fullname"]') || document.querySelector('meta[name="repo"]'); if (m && m.content) repoFull = m.content; } catch(e){}
            try { if (!repoFull) { var el = document.getElementById('repo-fullname'); if (el && el.dataset && el.dataset.repo) repoFull = el.dataset.repo; } } catch(e){}
            if (!repoFull) { showBanner('Repository not configured for PR creation. Add meta[name="repo-fullname"].', 'error', 5000); prBtn.disabled=false; prBtn.textContent='Create PR'; return; }

            const message = (commitInput && commitInput.value) ? commitInput.value : ('Add ' + p.path);
            const createRes = await fetch('/admin/pages/editor/mcp-call', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': getCsrf() }, body: JSON.stringify({ tool: 'github/create_or_update_file', arguments: { repoFullName: repoFull, path: p.path, content: p.content || '', message: message, branch: branch, overwrite: false } }) });
            const createJson = await createRes.json();
            if (!createJson || !createJson.success) { showBanner('Failed to create file on branch', 'error'); prBtn.disabled=false; prBtn.textContent='Create PR'; return; }
            const prTitle = message || ('MCP: Add ' + p.path);
            const prBody = (p.description || 'Automated PR from assistant');
            const prRes = await fetch('/admin/pages/editor/mcp-call', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': getCsrf() }, body: JSON.stringify({ tool: 'github/create_pr', arguments: { repoFullName: repoFull, headBranch: branch, baseBranch: 'main', title: prTitle, body: prBody } }) });
            const prJson = await prRes.json();
            if (prJson && prJson.success) { showBanner('PR created', 'success', 4000); box.remove(); } else { showBanner('PR creation failed', 'error', 4000); prBtn.disabled=false; prBtn.textContent='Create PR'; }
          } catch(e) { showBanner('PR creation failed: ' + (e && e.message?e.message:'unknown'), 'error', 4000); prBtn.disabled=false; prBtn.textContent='Create PR'; }
        });

        dismiss.addEventListener('click', function(){ box.remove(); });
      } catch(e) { console.warn('showFileProposal failed', e); }
    }

    sendBtn.addEventListener('click', function(){
      if (isStreaming) {
        stopStreaming();
      } else {
        sendMessage();
      }
    });
    // Enter sends the message; Shift+Enter inserts a newline. Keep Ctrl/Cmd+Enter as an alternative.
    input.addEventListener('keydown', function(ev){
      try {
        if (ev.key === 'Enter' && !ev.shiftKey) {
          // prevent inserting a newline and send the message instead
          ev.preventDefault();
          sendMessage();
        } else if ((ev.ctrlKey || ev.metaKey) && ev.key === 'Enter') {
          // also support Ctrl/Cmd+Enter as a shortcut
          ev.preventDefault();
          sendMessage();
        }
      } catch(e) { /* ignore */ }
    });

    try {
      if (panel.classList && panel.classList.contains('embedded')) {
        var persisted = localStorage.getItem('ginto.assistant.collapsed'); if (persisted === '1') panel.classList.add('collapsed');
        var observer = new MutationObserver(function(){
          try {
            var collapsed = panel.classList.contains('collapsed') ? '1' : '0';
            try { localStorage.setItem('ginto.assistant.collapsed', collapsed); } catch(e){}
            // Trigger Monaco to relayout when assistant panel collapses/expands so the
            // editor repaints correctly without requiring fullscreen toggles.
            try {
              if (window.gintoMonacoEditor && typeof window.gintoMonacoEditor.layout === 'function') {
                // slight delay to allow CSS/layout changes to settle
                setTimeout(function(){ try { window.gintoMonacoEditor.layout(); } catch(e){} }, 50);
              }
            } catch(e){}
          } catch(e){}
        });
        observer.observe(panel, { attributes: true, attributeFilter: ['class'] });
      }
    } catch(e) { /* ignore */ }

    // Use a MutationObserver instead of deprecated DOMNodeInserted. Only auto-scroll
    // when the user is already at (or near) the bottom to avoid stealing scroll
    // focus when they are reading earlier messages or interacting with the editor.
    try {
      var bodyMO = new MutationObserver(function(mutations){
        try {
          // if user is near bottom, auto-scroll; otherwise respect their position
          if (isScrolledToBottom(body, 48)) scrollToBottom(body);
        } catch(e){}
      });
      bodyMO.observe(body, { childList: true, subtree: true });
    } catch(e) {
      // fallback for older browsers: conservative auto-scroll only when at bottom
      try { body.addEventListener('DOMNodeInserted', function(){ if (isScrolledToBottom(body,48)) scrollToBottom(body); }); } catch(e){}
    }

    // smallest helper: show an inline banner inside panel
    function showBanner(message, type='info', timeout=3500) {
      try {
        if (!panel) return;
        var existing = panel.querySelector('.assistant-inline-banner'); if (existing) existing.remove();
        var b = document.createElement('div'); b.className = 'assistant-inline-banner ' + type; b.textContent = message;
        b.style.cssText = 'position:absolute;top:8px;right:10px;padding:8px 12px;border-radius:8px;background:rgba(0,0,0,0.6);color:white;font-size:12px;z-index:120000';
        panel.appendChild(b);
        setTimeout(function(){ try { b.remove(); } catch(e){} }, timeout);
      } catch(e) { console.warn('banner failed', e); }
    }

    // dynamically adjust body & textarea heights so footer/attachments remain visible
    function adjustLayout() {
      try {
        if (!pane || !body || !input) return;
        var paneRect = pane.getBoundingClientRect();
        var header = pane.querySelector('.assistant-header');
        var footer = pane.querySelector('.assistant-composer--vscode') || pane.querySelector('.assistant-composer') || pane.querySelector('.assistant-footer') || pane.querySelector('.assistant-footer-overlay');
        var headerH = header ? header.getBoundingClientRect().height : 0;
        var footerH = footer ? footer.getBoundingClientRect().height : 0;

        // reserve space for composer/attachments and a small margin
        var reserve = Math.max(footerH, 96);
        var avail = Math.max(120, Math.floor(paneRect.height - headerH - reserve - 12));

        // set a sensible max-height for the conversation body
        body.style.maxHeight = avail + 'px';

        // textarea should grow but not exceed a cap relative to available space
        var cap = Math.min(220, Math.max(80, Math.floor(avail * 0.42)));
        input.style.maxHeight = cap + 'px';
        input.style.overflowY = (input.scrollHeight > cap) ? 'auto' : 'hidden';

        // ensure autogrow runs now that the computed maxHeight changed so the
        // textarea height is recalculated including the new cap. Use a small
        // timeout to let styles settle (helps with some browsers / fractional px).
        try { setTimeout(function(){ if (typeof autoGrowTextarea === 'function') autoGrowTextarea(input, cap); }, 0); } catch(e){}
      } catch(e) { /* ignore layout errors */ }
    }

    // Observe pane/header/footer size changes and mutations that may affect layout
    try {
      var layoutRO = new ResizeObserver(function(){ adjustLayout(); });
      layoutRO.observe(pane);
      var hdr = pane.querySelector('.assistant-header'); if (hdr) layoutRO.observe(hdr);
      var ftr = pane.querySelector('.assistant-composer--vscode') || pane.querySelector('.assistant-composer') || pane.querySelector('.assistant-footer') || pane.querySelector('.assistant-footer-overlay'); if (ftr) layoutRO.observe(ftr);
    } catch(e) { /* ResizeObserver not available */ }

    try {
      var layoutMO = new MutationObserver(function(){ adjustLayout(); });
      layoutMO.observe(body, { childList: true, subtree: true });
      var attached = panel.querySelector('.attached-file'); if (attached) layoutMO.observe(attached, { attributes: true, childList: true, subtree: true });
    } catch(e) { /* MutationObserver not available */ }

    window.addEventListener('resize', function(){ adjustLayout(); debouncedMonacoLayout(60); });

    // When the page becomes visible again, ensure Monaco relayouts (helps tab switches)
    try {
      document.addEventListener('visibilitychange', function(){ if (document.visibilityState === 'visible') { adjustLayout(); debouncedMonacoLayout(80); } });
    } catch(e) {}

    // If there are common sidebar/preview toggle buttons, wire them so Monaco relayouts
    try {
      var possibleToggles = ['#sidebar-toggle', '.toggle-sidebar', '#preview-toggle', '.toggle-preview'];
      possibleToggles.forEach(function(sel){
        try {
          var el = document.querySelector(sel);
          if (el) el.addEventListener('click', function(){ setTimeout(function(){ adjustLayout(); debouncedMonacoLayout(80); }, 40); });
        } catch(e){}
      });
    } catch(e) {}

    // Observe common sidebar/preview containers for attribute/style changes
    try {
      var watchSelectors = ['#editor-sidebar', '#preview-pane', '.sidebar', '.preview'];
      var nodes = [];
      watchSelectors.forEach(function(sel){ try { var n = document.querySelector(sel); if (n) nodes.push(n); } catch(e){} });
      if (nodes.length) {
        var visMO = new MutationObserver(function(){ try { adjustLayout(); debouncedMonacoLayout(60); } catch(e){} });
        nodes.forEach(function(n){ try { visMO.observe(n, { attributes: true, attributeFilter: ['class','style'], subtree: false }); } catch(e){} });
      }
    } catch(e) {}

    // run once shortly after load to prime sizes
    try { setTimeout(function(){ adjustLayout(); debouncedMonacoLayout(80); }, 50); } catch(e){}

    // wire the new attach + file-actions elements
    try {
      var attachBtn = document.getElementById('assistant-attach');
      var attachedFileEl = panel.querySelector('.attached-file');
      var fileSelect = document.getElementById('file-select');
      if (attachBtn) {
        attachBtn.addEventListener('click', function(){
          try {
            // pick selected file from editor file-select if available
            var chosen = (fileSelect && fileSelect.options && fileSelect.selectedOptions && fileSelect.selectedOptions[0]) ? fileSelect.selectedOptions[0] : null;
            var path = chosen ? (chosen.textContent || chosen.value) : 'new_file.txt';
            if (attachedFileEl) {
              attachedFileEl.querySelector('.file-name').textContent = path;
              attachedFileEl.dataset.path = chosen ? (chosen.value || path) : path;
              showBanner('Attached: ' + path, 'success', 2200);
            }
          } catch(e) { console.warn('attach click failed', e); }
        });
      }

      // Keep / Undo / Export buttons
      var keepBtn = panel.querySelector('.as-keep');
      var undoBtn = panel.querySelector('.as-undo');
      var exportBtn = panel.querySelector('.as-export');
      if (keepBtn) keepBtn.addEventListener('click', function(){ showBanner('Kept changes', 'success'); });
      if (undoBtn) undoBtn.addEventListener('click', function(){ showBanner('Undid changes', 'info'); });
      if (exportBtn) exportBtn.addEventListener('click', function(){ showBanner('Export started', 'info'); });

      // Todos toggle — simply switch arrow and expanded state
      var todosToggle = panel.querySelector('.as-todos-toggle');
      if (todosToggle) {
        todosToggle.addEventListener('click', function(){
          try {
            var isOpen = todosToggle.getAttribute('aria-expanded') === 'true';
            if (isOpen) { todosToggle.textContent = '▸'; todosToggle.setAttribute('aria-expanded','false'); panel.classList.remove('todos-open'); }
            else { todosToggle.textContent = '▾'; todosToggle.setAttribute('aria-expanded','true'); panel.classList.add('todos-open'); }
          } catch(e) { console.warn('todos toggle failed', e); }
        });
      }

      // clicking attached file can navigate the editor file-select if present
      if (attachedFileEl) {
        attachedFileEl.addEventListener('click', function(){
          try {
            var p = attachedFileEl.dataset.path || null;
            if (p && fileSelect) {
              // try to find an option matching the filename
              var opt = Array.from(fileSelect.options).find(o => (o.textContent||o.value||'').indexOf(p) !== -1);
              if (opt) { fileSelect.value = opt.value; fileSelect.dispatchEvent(new Event('change')); showBanner('Opened: ' + (opt.textContent||opt.value)); }
            }
          } catch(e) { console.warn('attached-file click failed', e); }
        });
      }
      // model selector: keep select element annotated with the chosen variant (e.g. raptor)
      try {
        var modelSelect = panel.querySelector('.agent-select');
        if (modelSelect) {
          // set initial variant from selected option dataset if present
          try {
            var sel = modelSelect.selectedOptions && modelSelect.selectedOptions[0] ? modelSelect.selectedOptions[0] : modelSelect.options[modelSelect.selectedIndex];
            if (sel && sel.dataset && sel.dataset.variant) {
              modelSelect.dataset.variant = sel.dataset.variant;
              // mirror as class for older selectors
              modelSelect.classList.remove('raptor');
              modelSelect.classList.add(sel.dataset.variant);
            }
          } catch(e) { /* ignore */ }

          // update class/variant on change so closed select matches the chosen model
          modelSelect.addEventListener('change', function(){
            try {
              var opt = this.selectedOptions && this.selectedOptions[0] ? this.selectedOptions[0] : this.options[this.selectedIndex];
              var variant = (opt && opt.dataset && opt.dataset.variant) ? opt.dataset.variant : null;
              while (this.classList.contains('raptor')) this.classList.remove('raptor');
              if (variant) {
                this.dataset.variant = variant;
                this.classList.add(variant);
              } else {
                delete this.dataset.variant;
              }

              // Keep any custom-select UI in sync if present
              try {
                var panelRoot = panel || pane;
                var custom = panelRoot.querySelector('.custom-select');
                if (custom) {
                  var value = opt ? (opt.value || opt.textContent) : '';
                  // update display and data-value
                  custom.dataset.value = value;
                  var display = custom.querySelector('.custom-select__value');
                  if (display) display.textContent = opt ? (opt.textContent || opt.value) : '';
                  // ensure variant classes
                  var rclass = variant || null;
                  ['raptor'].forEach(function(c){ custom.classList.remove(c); });
                  if (rclass) custom.classList.add(rclass);
                }
              } catch(e){}
            } catch(e) { /* ignore */ }
          });
        }
      } catch(e) { /* ignore */ }

        // Custom select UI wiring (mirrors native select, accessible keyboard support)
        try {
          var custom = panel.querySelector('.custom-select');
          var native = panel.querySelector('.native-agent-select');
          if (custom) {
            var trigger = custom.querySelector('.custom-select__trigger');
            var optionsEl = custom.querySelector('.custom-select__options');
            var optionEls = Array.from(custom.querySelectorAll('.custom-option'));

            function closeCustom() {
              custom.setAttribute('aria-expanded','false');
              if (optionsEl) optionsEl.setAttribute('aria-hidden','true');
            }
            function openCustom() {
              custom.setAttribute('aria-expanded','true');
              if (optionsEl) optionsEl.setAttribute('aria-hidden','false');
            }

            // toggle on click
            custom.addEventListener('click', function(ev){
              ev.stopPropagation();
              var expanded = custom.getAttribute('aria-expanded') === 'true';
              if (expanded) closeCustom(); else openCustom();
            });

            // option click
            optionEls.forEach(function(optEl){
              optEl.addEventListener('click', function(ev){
                ev.stopPropagation();
                var val = this.dataset.value || this.textContent;
                var variant = this.dataset.variant || null;
                // set display
                var disp = custom.querySelector('.custom-select__value');
                if (disp) disp.textContent = this.textContent;
                custom.dataset.value = val;
                // set selected aria states
                optionEls.forEach(function(o){ o.setAttribute('aria-selected','false'); });
                this.setAttribute('aria-selected','true');
                // sync native select if present
                try {
                  if (native) {
                    // find matching option by value
                    var found = Array.from(native.options).find(function(o){ return (o.value === val) || (o.textContent === val); });
                    if (found) { native.value = found.value; native.dispatchEvent(new Event('change')); }
                  }
                } catch(e){}
                closeCustom();
              });
            });

            // keyboard navigation
            custom.addEventListener('keydown', function(ev){
              var expanded = custom.getAttribute('aria-expanded') === 'true';
              var focusedIndex = optionEls.findIndex(function(o){ return o.getAttribute('aria-selected') === 'true'; });
              if (ev.key === ' ' || ev.key === 'Enter') {
                ev.preventDefault();
                if (!expanded) openCustom(); else if (focusedIndex >= 0) { optionEls[focusedIndex].click(); }
              } else if (ev.key === 'ArrowDown') {
                ev.preventDefault();
                if (!expanded) { openCustom(); } else { var ni = Math.min(optionEls.length-1, Math.max(0, focusedIndex+1)); optionEls[ni].focus(); optionEls.forEach(o=>o.setAttribute('tabindex','-1')); optionEls[ni].setAttribute('tabindex','0'); }
              } else if (ev.key === 'ArrowUp') {
                ev.preventDefault();
                if (!expanded) { openCustom(); } else { var pi = Math.max(0, focusedIndex-1); optionEls[pi].focus(); optionEls.forEach(o=>o.setAttribute('tabindex','-1')); optionEls[pi].setAttribute('tabindex','0'); }
              } else if (ev.key === 'Escape') { ev.preventDefault(); closeCustom(); }
            });

            // close on outside click
            document.addEventListener('click', function(){ closeCustom(); });
          }
        } catch(e) { /* ignore custom select wiring errors */ }
    } catch(e) { /* ignore attach wiring errors */ }

    // ============ TTS AUDIO MANAGER ============
    (function setupEditorTTS() {
      var ttsToggleBtn = document.getElementById('editor-tts-toggle');
      var ttsStateEl = document.getElementById('editor-tts-state');
      
      window.__gintoAudio = {
        enabled: false,
        queue: [],
        inFlight: false,
        currentAudio: null,
        queueFragment: function(fragment) {
          if (!this.enabled) return;
          var f = ('' + fragment).trim();
          if (!f) return;
          this.queue.push(f);
          console.log('[TTS] Queued, queue length now=' + this.queue.length);
        }
      };

      function updateTtsState(state) {
        if (ttsStateEl) ttsStateEl.textContent = 'TTS: ' + state;
      }

      async function ttsFlush() {
        var am = window.__gintoAudio;
        if (!am || !am.enabled) return;
        if (am.inFlight) return;
        if (am.queue.length === 0) return;
        
        console.log('[TTS] Flushing queue, length=' + am.queue.length);
        var toSpeak = am.queue.join(' ');
        am.queue.length = 0;
        am.inFlight = true;
        updateTtsState('fetching...');
        
        try {
          var res = await fetch('/audio/tts', {
            method: 'POST',
            credentials: 'same-origin',
            body: toSpeak,
            headers: { 'Content-Type': 'text/plain', 'X-CSRF-Token': getCsrf() }
          });
          
          // If server indicates TTS is not configured, treat 204 as "disabled"
          if (res.status === 204) {
            console.log('[TTS] Server reports TTS disabled (204), skipping playback');
            am.inFlight = false;
            updateTtsState('disabled');
            return;
          }
          
          // Handle rate limit - show modal and disable TTS
          if (res.status === 429) {
            console.log('[TTS] Rate limit hit (429)');
            try {
              var data = await res.json();
              if (typeof window.showTtsLimitModal === 'function') {
                window.showTtsLimitModal(data);
              }
            } catch (parseErr) {
              console.debug('[TTS] Rate limit response parse error:', parseErr);
            }
            am.enabled = false;
            am.inFlight = false;
            updateTtsState('rate limited');
            return;
          }

          if (!res.ok) {
            // Redact provider-specific details from server error before logging
            const errBody = await res.text().catch(()=>'(no body)');
            const redacted = ('' + (errBody || '')).replace(/groq/ig, '[provider]').replace(/GROQ_API_KEY/ig, '[redacted]').replace(/api\.groq\.com/ig, '[provider]');
            console.error('TTS fetch failed', res.status, redacted.slice(0,2000));
            am.inFlight = false;
            updateTtsState('error');
            return;
          }

          var ab = await res.arrayBuffer();
          var blob = new Blob([ab], { type: 'audio/mpeg' });
          var url = URL.createObjectURL(blob);
          
          if (am.currentAudio) {
            try { am.currentAudio.pause(); am.currentAudio.src = ''; } catch(e) {}
          }
          
          var audio = new Audio(url);
          am.currentAudio = audio;
          updateTtsState('speaking');
          
          audio.addEventListener('ended', function() {
            URL.revokeObjectURL(url);
            am.currentAudio = null;
            am.inFlight = false;
            updateTtsState('idle');
            // Auto-start STT after TTS ends
            if (typeof window.__gintoStartRecording === 'function' && window.__gintoSttAutoStart) {
              setTimeout(function() { try { window.__gintoStartRecording(); } catch(e) {} }, 300);
            }
          });
          
          audio.addEventListener('error', function() {
            URL.revokeObjectURL(url);
            am.currentAudio = null;
            am.inFlight = false;
            updateTtsState('error');
          });
          
          try {
            await audio.play();
          } catch (e) {
            console.warn('audio.play() failed, trying WebAudio', e);
            try {
              var AudioCtx = window.AudioContext || window.webkitAudioContext;
              if (AudioCtx) {
                var ctx = new AudioCtx();
                var decoded = await ctx.decodeAudioData(ab.slice(0));
                var src = ctx.createBufferSource();
                src.buffer = decoded;
                src.connect(ctx.destination);
                src.start(0);
                src.onended = function() {
                  ctx.close();
                  am.currentAudio = null;
                  am.inFlight = false;
                  updateTtsState('idle');
                };
              }
            } catch (e2) {
              am.inFlight = false;
              updateTtsState('error');
            }
          }
        } catch (e) {
          am.inFlight = false;
          updateTtsState('error');
        }
      }

      // Flush TTS queue every 900ms
      setInterval(ttsFlush, 900);
      
      // Toggle TTS on/off
      if (ttsToggleBtn) {
        ttsToggleBtn.addEventListener('click', function() {
          window.__gintoAudio.enabled = !window.__gintoAudio.enabled;
          updateTtsState(window.__gintoAudio.enabled ? 'on' : 'off');
          ttsToggleBtn.style.opacity = window.__gintoAudio.enabled ? '1' : '0.5';
          if (!window.__gintoAudio.enabled && window.__gintoAudio.currentAudio) {
            try { window.__gintoAudio.currentAudio.pause(); } catch(e) {}
            window.__gintoAudio.currentAudio = null;
          }
        });
      }
    })();

    // ============ STT RECORDING ============
    (function setupEditorSTT() {
      var sttToggleBtn = document.getElementById('editor-stt-toggle');
      var sttStateEl = document.getElementById('editor-stt-state');
      var inputEl = document.getElementById('assistant-input');
      
      var mediaRecorder = null;
      var recordedChunks = [];
      var sttStream = null;
      var silenceTimer = null;
      var audioCtx = null;
      var analyser = null;

      function updateSttState(state) {
        if (sttStateEl) sttStateEl.textContent = 'STT: ' + state;
      }

      async function startRecording() {
        if (mediaRecorder) return;
        updateSttState('starting...');
        
        try {
          if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            updateSttState('no mic');
            return;
          }
          
          var stream = await navigator.mediaDevices.getUserMedia({ audio: true });
          sttStream = stream;
          recordedChunks = [];
          
          var mimeType = '';
          var candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus'];
          for (var i = 0; i < candidates.length; i++) {
            if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(candidates[i])) {
              mimeType = candidates[i];
              break;
            }
          }
          
          var opts = mimeType ? { mimeType: mimeType } : undefined;
          mediaRecorder = new MediaRecorder(stream, opts);
          
          // Setup silence detection
          try {
            var AudioCtxClass = window.AudioContext || window.webkitAudioContext;
            if (AudioCtxClass) {
              audioCtx = new AudioCtxClass();
              var src = audioCtx.createMediaStreamSource(stream);
              analyser = audioCtx.createAnalyser();
              analyser.fftSize = 2048;
              src.connect(analyser);
              
              var lastSpoke = Date.now();
              var data = new Float32Array(analyser.fftSize);
              
              silenceTimer = setInterval(function() {
                analyser.getFloatTimeDomainData(data);
                var sum = 0;
                for (var i = 0; i < data.length; i++) sum += data[i] * data[i];
                var rms = Math.sqrt(sum / data.length);
                
                if (rms >= 0.01) {
                  lastSpoke = Date.now();
                } else if (Date.now() - lastSpoke > 1500) {
                  // Silence for 1.5s - auto stop
                  stopRecording();
                }
              }, 200);
            }
          } catch (e) { console.debug('VAD setup failed', e); }
          
          mediaRecorder.addEventListener('dataavailable', function(e) {
            if (e.data && e.data.size) recordedChunks.push(e.data);
          });
          
          mediaRecorder.addEventListener('stop', function() {
            try { stream.getTracks().forEach(function(t) { t.stop(); }); } catch(e) {}
          });
          
          mediaRecorder.start();
          updateSttState('listening...');
          if (sttToggleBtn) sttToggleBtn.style.color = '#ef4444';
          
        } catch (e) {
          updateSttState('error');
          console.error('STT start error', e);
        }
      }

      async function stopRecording() {
        if (!mediaRecorder) return;
        updateSttState('processing...');
        
        try {
          if (silenceTimer) { clearInterval(silenceTimer); silenceTimer = null; }
          if (audioCtx) { audioCtx.close(); audioCtx = null; analyser = null; }
          
          mediaRecorder.stop();
          await new Promise(function(r) { setTimeout(r, 150); });
          
          var blob = new Blob(recordedChunks, { type: mediaRecorder.mimeType || 'audio/webm' });
          if (!blob || !blob.size) {
            updateSttState('no audio');
            mediaRecorder = null;
            recordedChunks = [];
            return;
          }
          
          var form = new FormData();
          form.append('file', blob, 'stt.webm');
          form.append('csrf_token', getCsrf());
          
          var res = await fetch('/transcribe', { method: 'POST', credentials: 'same-origin', body: form });
          var bodyText = await res.text().catch(function() { return ''; });
          
          if (!res.ok) {
            updateSttState('error');
            return;
          }
          
          var parsed = null;
          try { parsed = JSON.parse(bodyText); } catch(e) {}
          
          var transcript = '';
          if (parsed) {
            transcript = parsed.text || parsed.transcript || parsed.result || '';
          } else {
            transcript = bodyText.trim();
          }
          
          if (transcript && inputEl) {
            inputEl.value = (inputEl.value ? inputEl.value + ' ' : '') + transcript;
            inputEl.dispatchEvent(new Event('input'));
            // Auto-send the message
            var sendBtn = document.getElementById('assistant-send');
            if (sendBtn && !sendBtn.disabled) {
              setTimeout(function() { sendBtn.click(); }, 100);
            }
          }
          
          updateSttState('idle');
          
        } catch (e) {
          updateSttState('error');
          console.error('STT stop error', e);
        } finally {
          mediaRecorder = null;
          recordedChunks = [];
          if (sttToggleBtn) sttToggleBtn.style.color = '';
          if (sttStream) { try { sttStream.getTracks().forEach(function(t) { t.stop(); }); } catch(e) {} sttStream = null; }
        }
      }

      // Expose for TTS auto-start
      window.__gintoStartRecording = startRecording;
      window.__gintoSttAutoStart = false;

      // Toggle STT on/off
      if (sttToggleBtn) {
        sttToggleBtn.addEventListener('click', function() {
          if (mediaRecorder) {
            stopRecording();
          } else {
            startRecording();
          }
        });
      }
    })();

    // ============ AUTO-RUN TOOLS CHECKBOX ============
    (function setupAutoRun() {
      var autoRunCheckbox = document.getElementById('editor-auto-run');
      if (autoRunCheckbox) {
        // Load saved preference
        var saved = localStorage.getItem('ginto_auto_run_tools');
        if (saved === '1') autoRunCheckbox.checked = true;
        
        autoRunCheckbox.addEventListener('change', function() {
          localStorage.setItem('ginto_auto_run_tools', this.checked ? '1' : '0');
        });
      }
    })();

    // ============ FILE TREE FUNCTIONALITY ============
    (function setupFileTree() {
      var treeContent = document.getElementById('tree-content');
      var contextMenu = document.getElementById('file-context-menu');
      var filePath = document.getElementById('file-path') || document.getElementById('current-file-path');
      var langDisplay = document.getElementById('lang-display') || document.getElementById('current-lang');
      var editorStatus = document.getElementById('editor-status');
      
      // Skip if we're on a page without file tree
      if (!treeContent) return;
      
      // Simple toast notification function
      function showToast(message, isError) {
        // Try to use the editor's toast if available
        var editor = window.playgroundEditor || window.GintoEditor;
        if (editor && editor.showToast) {
          editor.showToast(message, isError);
          return;
        }
        
        // Create a simple toast
        var existing = document.querySelector('.ginto-toast');
        if (existing) existing.remove();
        
        var toast = document.createElement('div');
        toast.className = 'ginto-toast' + (isError ? ' error' : '');
        toast.textContent = message;
        toast.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);' +
          'background:' + (isError ? '#ef4444' : '#1f2937') + ';color:#fff;padding:10px 20px;' +
          'border-radius:6px;font-size:13px;z-index:10000;box-shadow:0 4px 12px rgba(0,0,0,0.3);' +
          'animation:fadeIn 0.2s ease;';
        document.body.appendChild(toast);
        setTimeout(function() { if (toast.parentNode) toast.remove(); }, 3000);
      }
      
      // Expanded folders tracking
      var EXPANDED_KEY = 'ginto_editor_expanded_folders';
      var expandedFolders = new Set();
      
      try {
        var stored = localStorage.getItem(EXPANDED_KEY);
        if (stored) expandedFolders = new Set(JSON.parse(stored));
      } catch(e) {}
      
      function saveExpandedFolders() {
        try {
          localStorage.setItem(EXPANDED_KEY, JSON.stringify([...expandedFolders]));
        } catch(e) {}
      }
      
      // Context menu state
      var contextTarget = null;
      var clipboard = { path: null, action: null };
      
      // Multi-select state
      var selectedFiles = new Set();
      var lastSelectedFile = null;
      
      function clearSelection() {
        selectedFiles.clear();
        lastSelectedFile = null;
        document.querySelectorAll('.file-item.selected, .folder-item.selected').forEach(function(el) {
          el.classList.remove('selected');
        });
      }
      
      function getSelectedPaths() {
        return Array.from(selectedFiles);
      }
      
      function getAllFileItems() {
        return Array.from(document.querySelectorAll('.file-item, .folder-item'));
      }
      
      function hideContextMenu() {
        if (contextMenu) {
          contextMenu.classList.remove('visible');
          contextTarget = null;
        }
      }
      
      function showContextMenu(e, target) {
        if (!contextMenu) return;
        e.preventDefault();
        contextTarget = target;
        
        var x = Math.min(e.clientX, window.innerWidth - 200);
        var y = Math.min(e.clientY, window.innerHeight - 350);
        contextMenu.style.left = x + 'px';
        contextMenu.style.top = y + 'px';
        
        var hasSelection = target && target.type !== 'root' && target.path;
        
        var pasteItem = contextMenu.querySelector('[data-action="paste"]');
        if (pasteItem) {
          pasteItem.classList.toggle('disabled', !clipboard.path);
        }
        
        ['cut', 'copy', 'rename', 'delete', 'copy-path'].forEach(function(action) {
          var item = contextMenu.querySelector('[data-action="' + action + '"]');
          if (item) {
            item.classList.toggle('disabled', !hasSelection);
          }
        });
        
        contextMenu.classList.add('visible');
      }
      
      // Generate indent guides HTML
      function getIndentGuides(lvl) {
        if (lvl === 0) return '';
        var guides = '<span class="tree-indent">';
        for (var i = 0; i < lvl; i++) {
          guides += '<span class="tree-indent-guide"></span>';
        }
        guides += '</span>';
        return guides;
      }
      
      // Show hidden files setting (persisted to DB for logged-in users)
      var showHiddenFiles = window.GINTO_EDITOR_SETTINGS?.showHiddenFiles || false;
      
      // Helper to check if a name is hidden (starts with .)
      function isHiddenFile(name) {
        return name && name.charAt(0) === '.';
      }
      
      // Render file tree
      function renderTree(tree, container, level, pathPrefix) {
        level = level || 0;
        pathPrefix = pathPrefix || '';
        
        var sorted = Object.entries(tree).sort(function(a, b) {
          var aIsDir = a[1].type === 'dir' || a[1].children;
          var bIsDir = b[1].type === 'dir' || b[1].children;
          if (aIsDir && !bIsDir) return -1;
          if (!aIsDir && bIsDir) return 1;
          return a[0].localeCompare(b[0]);
        });
        
        sorted.forEach(function(entry) {
          var name = entry[0];
          var item = entry[1];
          var fullPath = pathPrefix ? pathPrefix + '/' + name : name;
          
          // Skip hidden files unless showHiddenFiles is enabled
          if (!showHiddenFiles && isHiddenFile(name)) {
            return;
          }
          
          if (item.type === 'dir' || item.children) {
            var folder = document.createElement('div');
            var isExpanded = expandedFolders.has(fullPath);
            folder.className = 'folder-item' + (isExpanded ? '' : ' collapsed');
            folder.dataset.path = fullPath;
            folder.style.paddingLeft = '8px';
            folder.draggable = true;
            folder.innerHTML = getIndentGuides(level) +
              '<span class="folder-toggle">' +
                '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                  '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>' +
                '</svg>' +
              '</span>' +
              '<svg class="text-yellow-500" fill="currentColor" viewBox="0 0 20 20">' +
                '<path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>' +
              '</svg>' +
              '<span>' + name + '</span>';
            
            // Drag and drop events for folders
            folder.addEventListener('dragstart', function(e) {
              e.stopPropagation();
              e.dataTransfer.setData('text/plain', fullPath);
              e.dataTransfer.effectAllowed = 'move';
              folder.classList.add('dragging');
              window.__draggedPath = fullPath;
              window.__draggedType = 'folder';
            });
            folder.addEventListener('dragend', function() {
              folder.classList.remove('dragging');
              window.__draggedPath = null;
              window.__draggedType = null;
            });
            folder.addEventListener('dragover', function(e) {
              e.preventDefault();
              e.stopPropagation();
              if (window.__draggedPath && window.__draggedPath !== fullPath && !fullPath.startsWith(window.__draggedPath + '/')) {
                folder.classList.add('drag-over');
              }
            });
            folder.addEventListener('dragleave', function() {
              folder.classList.remove('drag-over');
            });
            folder.addEventListener('drop', function(e) {
              e.preventDefault();
              e.stopPropagation();
              folder.classList.remove('drag-over');
              var sourcePath = window.__draggedPath;
              if (sourcePath && sourcePath !== fullPath && !fullPath.startsWith(sourcePath + '/')) {
                moveItem(sourcePath, fullPath);
              }
            });
            
            folder.addEventListener('click', function(e) {
              e.stopPropagation();
              var isNowCollapsed = folder.classList.toggle('collapsed');
              if (isNowCollapsed) {
                expandedFolders.delete(fullPath);
              } else {
                expandedFolders.add(fullPath);
              }
              saveExpandedFolders();
            });
            container.appendChild(folder);
            
            var children = document.createElement('div');
            children.className = 'folder-children';
            children.dataset.path = fullPath;
            
            // Folder children can also receive drops
            children.addEventListener('dragover', function(e) {
              e.preventDefault();
              if (window.__draggedPath && !fullPath.startsWith(window.__draggedPath + '/')) {
                children.classList.add('drag-over');
              }
            });
            children.addEventListener('dragleave', function() {
              children.classList.remove('drag-over');
            });
            children.addEventListener('drop', function(e) {
              e.preventDefault();
              e.stopPropagation();
              children.classList.remove('drag-over');
              var sourcePath = window.__draggedPath;
              if (sourcePath && !fullPath.startsWith(sourcePath + '/')) {
                moveItem(sourcePath, fullPath);
              }
            });
            
            renderTree(item.children || item, children, level + 1, fullPath);
            container.appendChild(children);
          } else if (item.type === 'file') {
            var file = document.createElement('div');
            file.className = 'file-item';
            file.style.paddingLeft = '8px';
            file.dataset.encoded = item.encoded;
            file.dataset.path = item.path;
            file.draggable = true;
            
            // Drag events for files
            file.addEventListener('dragstart', function(e) {
              e.stopPropagation();
              e.dataTransfer.setData('text/plain', item.path);
              e.dataTransfer.effectAllowed = 'move';
              file.classList.add('dragging');
              window.__draggedPath = item.path;
              window.__draggedType = 'file';
            });
            file.addEventListener('dragend', function() {
              file.classList.remove('dragging');
              window.__draggedPath = null;
              window.__draggedType = null;
            });
            
            if (item.path === window.currentFile) {
              file.classList.add('active');
            }
            
            var ext = name.split('.').pop().toLowerCase();
            var iconColor = 'text-gray-400';
            if (['php'].indexOf(ext) >= 0) iconColor = 'text-purple-500';
            else if (['js', 'ts'].indexOf(ext) >= 0) iconColor = 'text-yellow-500';
            else if (['html', 'htm'].indexOf(ext) >= 0) iconColor = 'text-orange-500';
            else if (['css', 'scss'].indexOf(ext) >= 0) iconColor = 'text-blue-500';
            else if (['json'].indexOf(ext) >= 0) iconColor = 'text-green-500';
            else if (['md'].indexOf(ext) >= 0) iconColor = 'text-cyan-500';
            
            file.innerHTML = getIndentGuides(level) +
              '<svg class="' + iconColor + '" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>' +
              '</svg>' +
              '<span>' + name + '</span>';
            
            file.addEventListener('click', function(e) { 
              // Multi-select handling
              if (e.ctrlKey || e.metaKey) {
                // Ctrl+click: toggle selection
                if (selectedFiles.has(item.path)) {
                  selectedFiles.delete(item.path);
                  file.classList.remove('selected');
                } else {
                  selectedFiles.add(item.path);
                  file.classList.add('selected');
                }
                lastSelectedFile = item.path;
                return;
              } else if (e.shiftKey && lastSelectedFile) {
                // Shift+click: range selection
                var allItems = getAllFileItems();
                var startIdx = -1, endIdx = -1;
                allItems.forEach(function(el, idx) {
                  if (el.dataset.path === lastSelectedFile) startIdx = idx;
                  if (el.dataset.path === item.path) endIdx = idx;
                });
                if (startIdx !== -1 && endIdx !== -1) {
                  var from = Math.min(startIdx, endIdx);
                  var to = Math.max(startIdx, endIdx);
                  for (var i = from; i <= to; i++) {
                    var el = allItems[i];
                    if (el.dataset.path) {
                      selectedFiles.add(el.dataset.path);
                      el.classList.add('selected');
                    }
                  }
                }
                return;
              } else {
                // Normal click: clear selection and select this one
                clearSelection();
                selectedFiles.add(item.path);
                file.classList.add('selected');
                lastSelectedFile = item.path;
              }
              
              // Check if preview overlay is visible (view mode)
              var overlay = document.getElementById('editor-views-overlay');
              var isViewMode = overlay && overlay.style.display !== 'none';
              
              if (isViewMode) {
                // In view mode: update preview instead of switching to code view
                // Update current file tracking
                window.currentFile = item.path;
                window.currentEncoded = item.encoded;
                
                // Update save button text (Save vs Save as)
                if (typeof window.updateSaveButtonText === 'function') {
                  window.updateSaveButtonText();
                }
                
                // Update active file highlight
                document.querySelectorAll('.file-item').forEach(function(el) { el.classList.remove('active'); });
                file.classList.add('active');
                
                // Update file path display
                var filePathEl = document.getElementById('file-path');
                if (filePathEl) filePathEl.textContent = item.path || 'No file selected';
                
                // Update the preview iframe
                var iframe = overlay.querySelector('iframe');
                if (iframe) {
                  var sandboxId = window.editorConfig?.sandboxId;
                  console.log('[Preview] sandboxId:', sandboxId, 'item.path:', item.path, 'editorConfig:', window.editorConfig);
                  if (sandboxId && item.path) {
                    // Use sandbox preview URL
                    var previewUrl = '/sandbox-preview/' + sandboxId + '/' + item.path.replace(/^\//, '');
                    console.log('[Preview] Setting iframe src to:', previewUrl);
                    iframe.src = previewUrl;
                  } else {
                    // Fallback: try to load and display file content
                    console.log('[Preview] No sandboxId, using fallback');
                    iframe.srcdoc = '<p style="padding:20px;font-family:sans-serif;">Loading ' + item.path + '...</p>';
                  }
                } else {
                  console.log('[Preview] No iframe found in overlay');
                }
              } else {
                // Normal mode: load file into code editor
                loadFile(item.encoded, item.path);
              }
            });
            container.appendChild(file);
          }
        });
      }
      
      // Get Monaco language for file extension
      function getLanguage(filename) {
        var ext = (filename || '').split('.').pop().toLowerCase();
        var map = {
          'php': 'php', 'js': 'javascript', 'ts': 'typescript', 
          'html': 'html', 'htm': 'html', 'css': 'css', 'scss': 'scss',
          'json': 'json', 'md': 'markdown', 'sql': 'sql',
          'py': 'python', 'rb': 'ruby', 'sh': 'shell', 'bash': 'shell',
          'xml': 'xml', 'yaml': 'yaml', 'yml': 'yaml'
        };
        return map[ext] || 'plaintext';
      }
      
      // Load file content
      async function loadFile(encoded, path) {
        try {
          if (editorStatus) editorStatus.textContent = 'Loading...';
          var fileUrl = getApiBaseUrl() + '/file?file=' + encodeURIComponent(encoded);
          var res = await fetch(fileUrl, { credentials: 'same-origin' });
          var data = await res.json();
          
          if (!data.success) {
            throw new Error(data.error || 'Failed to load file');
          }
          
          window.currentFile = data.path;
          window.currentEncoded = data.encoded;

          // If this is a binary file, do not load into the code editor - show preview/download instead
          var isBinaryFile = data.is_binary || (data.encoding === 'base64');
          var ext = (data.path || '').split('.').pop().toLowerCase();
          // If PDF, always show a PDF viewer in both code and preview views
          if (ext === 'pdf') {
            if (editorStatus) editorStatus.textContent = 'PDF Viewer';
            // Hide Monaco/code editor if visible
            var codeEditor = document.getElementById('editor-monaco');
            if (codeEditor) codeEditor.style.display = 'none';
            // Show or create a PDF iframe inside the code editor area
            var pdfContainer = document.getElementById('editor-pdf-viewer');
            if (!pdfContainer) {
              pdfContainer = document.createElement('div');
              pdfContainer.id = 'editor-pdf-viewer';
              pdfContainer.style.width = '100%';
              pdfContainer.style.height = '100%';
              pdfContainer.style.background = '#222';
              pdfContainer.style.position = 'relative';
              pdfContainer.style.overflow = 'auto';
              var parent = document.getElementById('editor-main') || document.body;
              parent.appendChild(pdfContainer);
            }
            pdfContainer.innerHTML = '';
            var iframe = document.createElement('iframe');
            iframe.style.width = '100%';
            iframe.style.height = '100%';
            iframe.style.border = 'none';
            var sandboxId = window.editorConfig?.sandboxId;
            if (sandboxId && data.path) {
              iframe.src = '/sandbox-preview/' + sandboxId + '/' + data.path.replace(/^\//, '');
            } else {
              var clientUrl = (window.editorConfig?.sandboxId) ? ('/clients/' + window.editorConfig.sandboxId + '/' + data.path.replace(/^\//, '')) : ('/clients/' + data.path.replace(/^\//, ''));
              iframe.src = clientUrl;
            }
            pdfContainer.appendChild(iframe);
            pdfContainer.style.display = 'block';
            // Hide preview overlay if open
            var overlay = document.getElementById('editor-views-overlay');
            if (overlay) overlay.style.display = 'none';
            // Update active state in tree
            document.querySelectorAll('.file-item').forEach(function(el) { el.classList.remove('active'); });
            var activeFile = document.querySelector('.file-item[data-path="' + data.path + '"]');
            if (activeFile) activeFile.classList.add('active');
            return;
          }
          // Hide PDF viewer if present for non-PDF files
          var pdfContainer = document.getElementById('editor-pdf-viewer');
          if (pdfContainer) pdfContainer.style.display = 'none';
          // Show Monaco/code editor for non-PDF files
          var codeEditor = document.getElementById('editor-monaco');
          if (codeEditor) codeEditor.style.display = '';
          if (isBinaryFile && ext !== 'pdf') {
            if (editorStatus) editorStatus.textContent = 'Preview';
            // Show views overlay if exists
            var overlay = document.getElementById('editor-views-overlay');
            if (overlay) {
              overlay.style.display = 'block';
              var iframe = overlay.querySelector('iframe');
              if (iframe) {
                // If we have a sandbox preview route available, prefer it (keeps same-origin)
                var sandboxId = window.editorConfig?.sandboxId;
                if (sandboxId && data.path) {
                  iframe.src = '/sandbox-preview/' + sandboxId + '/' + data.path.replace(/^\//, '');
                } else {
                  // Build data URL for common previewable types (excluding PDF)
                  var previewMime = {
                    'png': 'image/png', 'jpg': 'image/jpeg', 'jpeg': 'image/jpeg', 'gif': 'image/gif', 'webp': 'image/webp', 'svg': 'image/svg+xml'
                  }[ext] || null;
                  if (previewMime && data.content) {
                    iframe.src = 'data:' + previewMime + ';base64,' + data.content;
                  } else {
                    // Fallback: show an HTML page with download/open links
                    var clientUrl = (window.editorConfig?.sandboxId) ? ('/clients/' + window.editorConfig.sandboxId + '/' + data.path.replace(/^\//, '')) : ('/clients/' + data.path.replace(/^\//, ''));
                    iframe.srcdoc = '<div style="font-family:system-ui;padding:20px;"><h3>' + escapeHtml(data.path.split('/').pop()) + '</h3>' +
                      '<p>Binary file preview not available. <a href="' + clientUrl + '" target="_blank">Open / Download</a></p>' +
                      '<p><a href="https://view.officeapps.live.com/op/view.aspx?src=' + encodeURIComponent(window.location.origin + clientUrl) + '" target="_blank">View in Office Online</a></p></div>';
                  }
                }
              }
            }
            // Update active state in tree
            document.querySelectorAll('.file-item').forEach(function(el) { el.classList.remove('active'); });
            var activeFile = document.querySelector('.file-item[data-path="' + data.path + '"]');
            if (activeFile) activeFile.classList.add('active');
            if (editorStatus) editorStatus.textContent = 'Preview';
            return;
          }
          
          // Update save button text (Save vs Save as)
          if (typeof window.updateSaveButtonText === 'function') {
            window.updateSaveButtonText();
          }
          
          if (filePath) filePath.textContent = data.path || 'No file selected';
          var saveInput = document.getElementById('save-file');
          if (saveInput) saveInput.value = data.encoded;
          
          var lang = getLanguage(data.path);
          window.currentLanguage = lang;
          if (langDisplay) langDisplay.textContent = lang.toUpperCase();
          
          // Update editor content - support both Monaco and CodeMirror (mobile)
          var editor = null;
          var isMobile = window.isMobileDevice || window.isMobileEditor;
          
          if (window.GintoEditor && window.GintoEditor.getEditor) {
            editor = window.GintoEditor.getEditor();
          } else if (window.playgroundEditor && window.playgroundEditor.getEditor) {
            editor = window.playgroundEditor.getEditor();
          }
          
          if (editor) {
            if (isMobile && window.GintoEditor && window.GintoEditor.setContent) {
              // CodeMirror via mobile editor
              window.GintoEditor.setContent(data.content || '');
              if (window.GintoEditor.setLanguage) {
                window.GintoEditor.setLanguage(lang);
              }
            } else if (window.monaco) {
              // Monaco editor
              monaco.editor.setModelLanguage(editor.getModel(), lang);
              editor.setValue(data.content || '');
            } else if (editor.setValue) {
              // Generic editor with setValue
              editor.setValue(data.content || '');
            }
          }
          
          // Update active state in tree
          document.querySelectorAll('.file-item').forEach(function(el) { el.classList.remove('active'); });
          var activeFile = document.querySelector('.file-item[data-path="' + data.path + '"]');
          if (activeFile) activeFile.classList.add('active');
          
          // Update file select dropdown
          var fileSelectEl = document.getElementById('file-select');
          if (fileSelectEl) fileSelectEl.value = data.encoded;
          
          // Update attached file display in composer
          var attachedFileEl = document.querySelector('.attached-file');
          if (attachedFileEl) {
            var fileNameEl = attachedFileEl.querySelector('.file-name');
            var fileTagEl = attachedFileEl.querySelector('.file-tag');
            if (fileNameEl) fileNameEl.textContent = data.path ? data.path.split('/').pop() : 'No file';
            if (fileTagEl) {
              var ext = (data.path || '').split('.').pop().toUpperCase();
              fileTagEl.textContent = ext || 'FILE';
            }
            attachedFileEl.dataset.path = data.path || '';
          }
          
          if (editorStatus) editorStatus.textContent = 'Ready';
          
          // Update URL based on current route
          var basePath = window.location.pathname.startsWith('/editor') ? '/editor' : '/playground/editor';
          history.pushState({}, '', basePath + '?file=' + encodeURIComponent(encoded));
          
        } catch(e) {
          console.error('Load failed:', e);
          showToast('Failed to load file', true);
          if (editorStatus) editorStatus.textContent = 'Error';
        }
      }
      
      // Refresh tree from server
      async function refreshTree() {
        console.log('[refreshTree] Starting tree refresh...');
        try {
          var treeUrl = getApiBaseUrl() + '/tree?ajax=1&t=' + Date.now();
          console.log('[refreshTree] Fetching:', treeUrl);
          var res = await fetch(treeUrl, { credentials: 'same-origin' });
          var data = await res.json();
          console.log('[refreshTree] Got data:', data);
          if (data.tree) {
            window.playgroundRepoTree = data.tree;
            window.editorTree = data.tree;
            console.log('[refreshTree] Clearing and re-rendering tree...');
            treeContent.innerHTML = '';
            renderTree(data.tree, treeContent);
            // Force a repaint to ensure UI updates immediately
            void treeContent.offsetHeight;
            console.log('[refreshTree] Tree refresh complete. Items:', Object.keys(data.tree).length);
          } else {
            console.warn('[refreshTree] No tree data in response');
          }
        } catch(e) {
          console.error('[refreshTree] Failed to refresh tree:', e);
        }
      }
      
      // Input dialog helper
      function showInputDialog(title, defaultValue) {
        defaultValue = defaultValue || '';
        return new Promise(function(resolve) {
          var overlay = document.createElement('div');
          overlay.className = 'input-dialog-overlay';
          overlay.innerHTML = '<div class="input-dialog">' +
            '<h3>' + title + '</h3>' +
            '<input type="text" value="' + defaultValue + '" autofocus>' +
            '<div class="input-dialog-buttons">' +
              '<button class="cancel">Cancel</button>' +
              '<button class="primary confirm">OK</button>' +
            '</div>' +
          '</div>';
          document.body.appendChild(overlay);
          
          var input = overlay.querySelector('input');
          var confirm = overlay.querySelector('.confirm');
          var cancel = overlay.querySelector('.cancel');
          
          input.select();
          
          function close(value) {
            document.body.removeChild(overlay);
            resolve(value);
          }
          
          confirm.onclick = function() { close(input.value.trim()); };
          cancel.onclick = function() { close(null); };
          overlay.onclick = function(e) { if (e.target === overlay) close(null); };
          input.onkeydown = function(e) {
            if (e.key === 'Enter') close(input.value.trim());
            if (e.key === 'Escape') close(null);
          };
        });
      }
      
      function showDeleteConfirmDialog(options) {
        options = options || {};
        var title = options.title || 'Delete Item';
        var message = options.message || 'Delete this item? This cannot be undone.';
        var confirmText = options.confirmText || 'Delete';
        var cancelText = options.cancelText || 'Cancel';
        
        return new Promise(function(resolve) {
          var overlay = document.createElement('div');
          overlay.className = 'input-dialog-overlay';
          overlay.innerHTML = '<div class="input-dialog confirm-dialog">' +
            '<h3>' + title + '</h3>' +
            '<p>' + message + '</p>' +
            '<div class="input-dialog-buttons">' +
              '<button class="cancel">' + cancelText + '</button>' +
              '<button class="confirm danger">' + confirmText + '</button>' +
            '</div>' +
          '</div>';
          document.body.appendChild(overlay);
          
          var confirm = overlay.querySelector('.confirm');
          var cancel = overlay.querySelector('.cancel');
          
          function close(result) {
            overlay.remove();
            resolve(result);
          }
          
          confirm.onclick = function() { close(true); };
          cancel.onclick = function() { close(false); };
          overlay.onclick = function(e) { if (e.target === overlay) close(false); };
          document.addEventListener('keydown', function handler(e) {
            if (e.key === 'Escape') { close(false); document.removeEventListener('keydown', handler, true); }
            if (e.key === 'Enter') { close(true); document.removeEventListener('keydown', handler, true); }
          }, true);
          
          setTimeout(function() { confirm.focus(); }, 0);
        });
      }
      
      // File operations
      async function createNewFile(parentPath) {
        console.log('[createNewFile] Called with parentPath:', parentPath);
        var name = await showInputDialog('New File Name:');
        if (!name) return;
        
        var path = parentPath ? parentPath + '/' + name : name;
        console.log('[createNewFile] Creating file:', path);
        try {
          var res = await fetch(getApiBaseUrl() + '/create', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
              csrf_token: getCsrf(),
              path: path,
              type: 'file'
            })
          });
          var data = await res.json();
          console.log('[createNewFile] Server response:', data);
          if (data.success) {
            showToast('Created ' + name);
            // Ensure parent folder is expanded so new file is visible
            if (parentPath) {
              expandedFolders.add(parentPath);
              saveExpandedFolders();
            }
            console.log('[createNewFile] Calling refreshTree...');
            await refreshTree();
            console.log('[createNewFile] refreshTree completed');
            if (data.encoded) loadFile(data.encoded, path);
          } else {
            showToast(data.error || 'Failed to create file', true);
          }
        } catch(e) {
          console.error('Create file error:', e);
          showToast('Failed to create file', true);
        }
      }
      
      async function createNewFolder(parentPath) {
        var name = await showInputDialog('New Folder Name:');
        if (!name) return;
        
        var path = parentPath ? parentPath + '/' + name : name;
        try {
          var res = await fetch(getApiBaseUrl() + '/create', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
              csrf_token: getCsrf(),
              path: path,
              type: 'folder'
            })
          });
          var data = await res.json();
          if (data.success) {
            showToast('Created folder ' + name);
            // Ensure parent folder is expanded so new folder is visible
            if (parentPath) {
              expandedFolders.add(parentPath);
              saveExpandedFolders();
            }
            // Also expand the newly created folder
            expandedFolders.add(path);
            saveExpandedFolders();
            await refreshTree();
          } else {
            showToast(data.error || 'Failed to create folder', true);
          }
        } catch(e) {
          console.error('Create folder error:', e);
          showToast('Failed to create folder', true);
        }
      }
      
      async function renameItem(path, isFolder) {
        var oldName = path.split('/').pop();
        var newName = await showInputDialog('Rename to:', oldName);
        if (!newName || newName === oldName) return;
        
        var parentPath = path.substring(0, path.lastIndexOf('/'));
        var newPath = parentPath ? parentPath + '/' + newName : newName;
        
        try {
          var res = await fetch(getApiBaseUrl() + '/rename', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
              csrf_token: getCsrf(),
              oldPath: path,
              newPath: newPath
            })
          });
          var data = await res.json();
          if (data.success) {
            showToast('Renamed to ' + newName);
            refreshTree();
          } else {
            showToast(data.error || 'Failed to rename', true);
          }
        } catch(e) {
          showToast('Failed to rename', true);
        }
      }
      
      async function deleteItem(path, isFolder) {
        var name = path.split('/').pop();
        var confirmed = await showDeleteConfirmDialog({
          title: isFolder ? 'Delete Folder' : 'Delete File',
          message: 'Delete "' + name + '"? This cannot be undone.',
          confirmText: 'Delete',
          cancelText: 'Cancel'
        });
        if (!confirmed) return;
        
        try {
          var res = await fetch(getApiBaseUrl() + '/delete', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
              csrf_token: getCsrf(),
              path: path
            })
          });
          var data = await res.json();
          if (data.success) {
            showToast('Deleted ' + name);
            refreshTree();
            if (window.currentFile === path || (window.currentFile && window.currentFile.startsWith(path + '/'))) {
              window.currentFile = null;
              window.currentEncoded = null;
              // Update save button text (Save vs Save as)
              if (typeof window.updateSaveButtonText === 'function') {
                window.updateSaveButtonText();
              }
              if (window.GintoEditor && window.GintoEditor.getEditor) {
                var editor = window.GintoEditor.getEditor();
                if (editor) editor.setValue('');
              }
              if (filePath) filePath.textContent = 'No file selected';
            }
          } else {
            showToast(data.error || 'Failed to delete', true);
          }
        } catch(e) {
          showToast('Failed to delete', true);
        }
      }
      
      async function pasteItem(destPath) {
        if (!clipboard.path) return;
        
        try {
          var res = await fetch(getApiBaseUrl() + '/paste', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
              csrf_token: getCsrf(),
              source: clipboard.path,
              destination: destPath,
              action: clipboard.action
            })
          });
          var data = await res.json();
          if (data.success) {
            showToast(clipboard.action === 'cut' ? 'Moved successfully' : 'Copied successfully');
            if (clipboard.action === 'cut') clipboard = { path: null, action: null };
            refreshTree();
          } else {
            showToast(data.error || 'Failed to paste', true);
          }
        } catch(e) {
          showToast('Failed to paste', true);
        }
      }
      
      // Move item via drag and drop
      async function moveItem(sourcePath, destFolder) {
        if (!sourcePath || !destFolder) return;
        
        var fileName = sourcePath.split('/').pop();
        var newPath = destFolder + '/' + fileName;
        
        // Don't move to same location
        if (sourcePath === newPath) return;
        
        try {
          var res = await fetch(getApiBaseUrl() + '/paste', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
              csrf_token: getCsrf(),
              source: sourcePath,
              destination: destFolder,
              action: 'cut'
            })
          });
          var data = await res.json();
          if (data.success) {
            showToast('Moved ' + fileName + ' to ' + destFolder);
            await refreshTree();
          } else {
            showToast(data.error || 'Failed to move', true);
          }
        } catch(e) {
          console.error('Move failed:', e);
          showToast('Failed to move', true);
        }
      }
      
      // Enable drag and drop on root tree content (for dropping to root)
      treeContent.addEventListener('dragover', function(e) {
        // Only show drop zone if dropping directly on treeContent, not a child
        if (e.target === treeContent && window.__draggedPath) {
          e.preventDefault();
          treeContent.classList.add('drag-over');
        }
      });
      treeContent.addEventListener('dragleave', function(e) {
        if (e.target === treeContent) {
          treeContent.classList.remove('drag-over');
        }
      });
      treeContent.addEventListener('drop', function(e) {
        if (e.target === treeContent && window.__draggedPath) {
          e.preventDefault();
          treeContent.classList.remove('drag-over');
          // Move to root (empty string)
          moveItem(window.__draggedPath, '');
        }
      });
      
      // Right-click context menu on file tree
      var fileTree = document.getElementById('file-tree');
      if (fileTree && contextMenu) {
        fileTree.addEventListener('contextmenu', function(e) {
          e.preventDefault();
          var item = e.target.closest('.file-item, .folder-item');
          if (item) {
            var isFolder = item.classList.contains('folder-item');
            showContextMenu(e, { 
              type: isFolder ? 'folder' : 'file', 
              path: item.dataset.path 
            });
          } else {
            showContextMenu(e, { type: 'root', path: '' });
          }
        });
        
        // Hide on click outside
        document.addEventListener('click', function(e) {
          if (!contextMenu.contains(e.target)) {
            hideContextMenu();
          }
        });
        
        // Hide on Escape
        document.addEventListener('keydown', function(e) {
          if (e.key === 'Escape') hideContextMenu();
        });
        
        // Context menu action handlers
        contextMenu.addEventListener('click', async function(e) {
          var menuItem = e.target.closest('.context-menu-item');
          if (!menuItem || menuItem.classList.contains('disabled')) return;
          
          var action = menuItem.dataset.action;
          var isFolder = contextTarget && (contextTarget.type === 'folder' || contextTarget.type === 'root');
          var path = contextTarget ? contextTarget.path || '' : '';
          
          hideContextMenu();
          
          switch(action) {
            case 'new-file':
              await createNewFile(isFolder ? path : path.substring(0, path.lastIndexOf('/')));
              break;
            case 'new-folder':
              await createNewFolder(isFolder ? path : path.substring(0, path.lastIndexOf('/')));
              break;
            case 'cut':
              clipboard = { path: path, action: 'cut' };
              showToast('Ready to move');
              break;
            case 'copy':
              clipboard = { path: path, action: 'copy' };
              showToast('Copied to clipboard');
              break;
            case 'paste':
              await pasteItem(isFolder ? path : path.substring(0, path.lastIndexOf('/')));
              break;
            case 'rename':
              if (path) await renameItem(path, isFolder);
              break;
            case 'delete':
              // Use multi-selection if available, otherwise use context target
              var selectedPaths = getSelectedPaths();
              if (selectedPaths.length > 1) {
                // Multiple files selected - delete all
                var confirmed = await showDeleteConfirmDialog({
                  title: 'Delete Multiple Items',
                  message: 'Delete ' + selectedPaths.length + ' selected items? This cannot be undone.',
                  confirmText: 'Delete All',
                  cancelText: 'Cancel'
                });
                if (confirmed) {
                  await Promise.all(selectedPaths.map(function(p) {
                    return fetch(getApiBaseUrl() + '/delete', {
                      method: 'POST',
                      credentials: 'same-origin',
                      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                      body: new URLSearchParams({ csrf_token: getCsrf(), path: p })
                    });
                  }));
                  showToast('Deleted ' + selectedPaths.length + ' items');
                  clearSelection();
                  await refreshTree();
                }
              } else if (path) {
                await deleteItem(path, isFolder);
              }
              break;
            case 'copy-path':
              if (path) {
                navigator.clipboard.writeText(path);
                showToast('Path copied');
              }
              break;
            case 'refresh':
              await refreshTree();
              showToast('Refreshed');
              break;
          }
        });
      }
      
      // Expose functions globally for header buttons
      window.createNewFile = createNewFile;
      window.createNewFolder = createNewFolder;
      window.refreshTree = refreshTree;
      window.loadFile = loadFile;
      window.getSelectedPaths = getSelectedPaths;
      window.clearFileSelection = clearSelection;
      
      // Expose setter for hidden files preference
      window.setShowHiddenFiles = function(val) {
        showHiddenFiles = !!val;
      };
      
      // Keyboard shortcuts for file operations
      document.addEventListener('keydown', function(e) {
        // Only handle when focus is on file tree or body (not in editor/input)
        var target = e.target;
        var isInput = target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable;
        var isEditor = target.closest('.monaco-editor') || target.closest('.CodeMirror');
        
        if (isInput || isEditor) return;
        
        // Delete key: delete selected files
        if (e.key === 'Delete' || e.key === 'Backspace') {
          var paths = getSelectedPaths();
          if (paths.length > 0) {
            e.preventDefault();
            if (paths.length === 1) {
              deleteItem(paths[0], false);
            } else {
              // Multiple files delete
              showDeleteConfirmDialog({
                title: 'Delete Multiple Items',
                message: 'Delete ' + paths.length + ' selected items? This cannot be undone.',
                confirmText: 'Delete All',
                cancelText: 'Cancel'
              }).then(function(confirmed) {
                if (confirmed) {
                  Promise.all(paths.map(function(p) {
                    return fetch(getApiBaseUrl() + '/delete', {
                      method: 'POST',
                      credentials: 'same-origin',
                      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                      body: new URLSearchParams({ csrf_token: getCsrf(), path: p })
                    });
                  })).then(function() {
                    showToast('Deleted ' + paths.length + ' items');
                    clearSelection();
                    refreshTree();
                  });
                }
              });
            }
          }
        }
        
        // Ctrl+N: create new file
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
          e.preventDefault();
          // Get current folder context or use root
          var parentPath = '';
          var paths = getSelectedPaths();
          if (paths.length > 0) {
            var path = paths[0];
            // Check if it's a folder
            var item = document.querySelector('.folder-item[data-path="' + path + '"]');
            if (item) {
              parentPath = path;
            } else {
              // It's a file, get parent folder
              parentPath = path.substring(0, path.lastIndexOf('/'));
            }
          }
          createNewFile(parentPath);
        }
        
        // Escape: clear selection
        if (e.key === 'Escape') {
          clearSelection();
        }
        
        // Ctrl+A: select all (when tree is focused)
        if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
          var treeEl = document.getElementById('tree-content');
          if (treeEl && (target === treeEl || treeEl.contains(target) || target === document.body)) {
            e.preventDefault();
            getAllFileItems().forEach(function(el) {
              if (el.dataset.path) {
                selectedFiles.add(el.dataset.path);
                el.classList.add('selected');
              }
            });
          }
        }
      });
      
      // Initial tree load
      refreshTree().then(function() {
        // Dispatch event so file select can populate
        document.dispatchEvent(new CustomEvent('treeRefreshed'));
      });
    })();

    // ============ ADMIN SANDBOX TOGGLE ============
    (function setupAdminSandboxToggle() {
      var adminAction = document.getElementById('admin-sandbox-action');
      var debugRoot = document.getElementById('editor-debug-root');
      var sandboxBadge = document.querySelector('.sandbox-badge');
      var fileSelect = document.getElementById('file-select');
      
      // Skip if no toggle button
      if (!adminAction) return;
      
      // Get initial state from server config
      var isAdmin = (typeof window.editorConfig !== 'undefined' && window.editorConfig.isAdmin);
      var currentUseSandbox = (typeof window.editorConfig !== 'undefined' && window.editorConfig.sandboxId);
      var sandboxId = (typeof window.editorConfig !== 'undefined') ? window.editorConfig.sandboxId : null;
      
      // Hide toggle if not admin
      if (!isAdmin) {
        adminAction.style.display = 'none';
        return;
      }
      
      // Update UI state
      function setAdminUI(useSandbox, sid) {
        currentUseSandbox = useSandbox;
        sandboxId = sid;
        
        // Update toggle button color
        adminAction.style.color = useSandbox ? '#16a34a' : '#6b7280';
        adminAction.title = useSandbox ? 'Switch to Repo view' : 'Switch to Sandbox view';
        
        // Update debug root display (removed debug output for sandbox)
        if (debugRoot) {
          // Debug output intentionally removed to avoid duplicate sandbox debug messaging.
        }
        
        // Update sandbox badge
        if (sandboxBadge) {
          if (useSandbox && sid) {
            var host = (window.location.hostname || 'localhost').split(':')[0];
            var clientUrl = 'http://' + host + ':8080/' + encodeURIComponent(sid) + '/';
            sandboxBadge.innerHTML = 'Sandbox • <strong>' + escapeHtml(sid) + '</strong> — <a href="' + clientUrl + '" target="_blank">open</a>';
            sandboxBadge.style.display = '';
          } else {
            sandboxBadge.style.display = 'none';
          }
        }
      }
      
      function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
      }
      
      // Handle toggle click
      adminAction.addEventListener('click', async function(e) {
        e.preventDefault();
        
        try {
          showToast('Switching workspace...');
          
          // Toggle state
          var next = currentUseSandbox ? '0' : '1';
          
          var csrfToken = window.CSRF_TOKEN || (document.querySelector('input[name="csrf_token"]') || {}).value || '';
          
          var res = await fetch('/editor/toggle_sandbox', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'csrf_token=' + encodeURIComponent(csrfToken) + '&use_sandbox=' + next
          });
          
          var json = await res.json().catch(function() { return { success: false, error: 'invalid-json' }; });
          
          if (!res.ok || !json.success) {
            throw new Error(json.error || 'toggle_failed');
          }
          
          // Update CSRF token if returned
          if (json.csrf_token) {
            window.CSRF_TOKEN = json.csrf_token;
          }
          
          // Update UI
          setAdminUI(!!json.use_sandbox, json.sandbox_id || null);
          
          // Refresh tree
          if (window.refreshTree) {
            await window.refreshTree();
          }
          
          showToast(json.use_sandbox ? 'Switched to sandbox view' : 'Switched to repo view');
          
        } catch (err) {
          console.error('admin toggle failed', err);
          showToast('Failed to toggle: ' + (err.message || err), true);
        }
      });
      
      // Set initial state
      setAdminUI(!!sandboxId, sandboxId);
    })();

    // Ensure non-admin and anonymous users still see sandbox id (if server provided it)
    (function ensureSandboxDisplayForAll() {
      var debugRoot = document.getElementById('editor-debug-root');
      var sandboxBadge = document.querySelector('.sandbox-badge');
      var adminAction = document.getElementById('admin-sandbox-action');
      var cfg = (typeof window.editorConfig !== 'undefined') ? window.editorConfig : {};
      var sid = cfg.sandboxId || null;

      function escapeHtmlLocal(str) {
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
      }

      // Hide admin lock/toggle if user is not admin
      if (adminAction && !cfg.isAdmin) {
        try { adminAction.style.display = 'none'; } catch (e) { /* ignore */ }
      }

      // Debug root display removed for non-admin users to avoid duplicate debug output
      if (debugRoot) {
        // intentionally left blank
      }

      if (sandboxBadge) {
        try {
          if (sid) {
            var host = (window.location.hostname || 'localhost').split(':')[0];
            var clientUrl = 'http://' + host + ':8080/' + encodeURIComponent(sid) + '/';
            sandboxBadge.innerHTML = 'Sandbox • <strong>' + escapeHtmlLocal(sid) + '</strong> — <a href="' + clientUrl + '" target="_blank">open</a>';
            sandboxBadge.style.display = '';
          } else {
            sandboxBadge.style.display = 'none';
          }
        } catch (e) { /* ignore */ }
      }
    })();

    // ============ FILE SELECT DROPDOWN ============
    (function setupFileSelect() {
      var fileSelect = document.getElementById('file-select');
      if (!fileSelect) return;
      
      // When tree is loaded, populate the file-select with all files
      var origRefreshTree = window.refreshTree;
      if (origRefreshTree) {
        window.refreshTree = async function() {
          await origRefreshTree();
          populateFileSelect();
        };
      }
      
      // Also populate after a short delay on initial load (tree may already be loaded)
      setTimeout(function() {
        populateFileSelect();
      }, 500);
      
      // And listen for custom event when tree is refreshed
      document.addEventListener('treeRefreshed', function() {
        populateFileSelect();
      });
      
      function populateFileSelect() {
        var tree = window.editorTree || window.playgroundRepoTree || {};
        var currentVal = fileSelect.value;
        
        // Clear options except first
        while (fileSelect.options.length > 1) {
          fileSelect.remove(1);
        }
        
        // Collect all files from tree
        var files = [];
        function collectFiles(node, prefix) {
          for (var name in node) {
            var item = node[name];
            var path = prefix ? prefix + '/' + name : name;
            if (item.type === 'file') {
              files.push({ name: path, encoded: item.encoded || '' });
            } else if (item.children) {
              collectFiles(item.children, path);
            }
          }
        }
        collectFiles(tree, '');
        
        // Sort and add options
        files.sort(function(a, b) { return a.name.localeCompare(b.name); });
        files.forEach(function(f) {
          var opt = document.createElement('option');
          opt.value = f.encoded;
          opt.textContent = f.name;
          fileSelect.appendChild(opt);
        });
        
        // Restore selection
        if (currentVal) fileSelect.value = currentVal;
      }
      
      // Handle file selection
      fileSelect.addEventListener('change', function() {
        var encoded = fileSelect.value;
        if (!encoded) return;
        
        var selectedOpt = fileSelect.options[fileSelect.selectedIndex];
        var path = selectedOpt ? selectedOpt.textContent : '';
        
        if (window.loadFile) {
          window.loadFile(encoded, path);
        }
      });
    })();

    // ============ MONACO EDITOR INITIALIZATION ============
    (function setupMonaco() {
      var monacoContainer = document.getElementById('monaco-editor');
      var textarea = document.getElementById('editor-content') || document.getElementById('code-textarea');
      var lineNumEl = document.getElementById('line-num');
      var colNumEl = document.getElementById('col-num');
      var langDisplay = document.getElementById('lang-display') || document.getElementById('current-lang');
      var editorStatus = document.getElementById('editor-status');
      
      // Skip if no Monaco container
      if (!monacoContainer) return;
      
      var monacoEditor = null;
      var monacoReady = false;
      var isDirty = false;
      var lastSavedContent = '';
      var autoSaveTimer = null;
      
      function getCurrentTheme() {
        // Check ginto-theme (parent chat) first, then editor-specific keys
        var stored = localStorage.getItem('ginto-theme') || localStorage.getItem('editor-theme') || localStorage.getItem('playground-theme');
        if (stored === 'dark') return 'dark';
        if (stored === 'light') return 'light';
        if (document.documentElement.classList.contains('dark')) return 'dark';
        if (window.matchMedia('(prefers-color-scheme: dark)').matches) return 'dark';
        return 'light';
      }
      
      function syncMonacoTheme() {
        if (window.monaco && window.monaco.editor) {
          var theme = getCurrentTheme();
          monaco.editor.setTheme(theme === 'dark' ? 'vs-dark' : 'vs');
        }
      }
      
      function getLanguage(filename) {
        var ext = (filename || '').split('.').pop().toLowerCase();
        var map = {
          'php': 'php', 'js': 'javascript', 'ts': 'typescript', 
          'html': 'html', 'htm': 'html', 'css': 'css', 'scss': 'scss',
          'json': 'json', 'md': 'markdown', 'sql': 'sql',
          'py': 'python', 'rb': 'ruby', 'sh': 'shell', 'bash': 'shell',
          'xml': 'xml', 'yaml': 'yaml', 'yml': 'yaml'
        };
        return map[ext] || 'plaintext';
      }
      
      function updateCursor() {
        if (!monacoEditor) return;
        var pos = monacoEditor.getPosition();
        if (pos) {
          if (lineNumEl) lineNumEl.textContent = pos.lineNumber;
          if (colNumEl) colNumEl.textContent = pos.column;
        }
      }
      
      function getEditorContent() {
        if (monacoReady && monacoEditor) {
          return monacoEditor.getValue();
        }
        return textarea ? textarea.value : '';
      }
      
      function scheduleAutoSave() {
        if (autoSaveTimer) clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(function() {
          saveFile(true);
        }, 2000);
      }
      
      function clearAutoSaveTimer() {
        if (autoSaveTimer) {
          clearTimeout(autoSaveTimer);
          autoSaveTimer = null;
        }
      }
      
      async function saveFile(silent) {
        if (!window.currentEncoded) {
          // No file selected - show Save As dialog
          if (typeof window.openSaveAsModal === 'function') {
            window.openSaveAsModal();
            return false;
          }
          if (!silent) showToast('No file selected', true);
          return false;
        }
        
        var content = getEditorContent();
        if (content === lastSavedContent && !isDirty) return true;
        
        try {
          if (editorStatus) editorStatus.textContent = 'Saving...';
          
          var res = await fetch(getApiBaseUrl() + '/save', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
              csrf_token: getCsrf(),
              file: window.currentEncoded,
              content: content
            })
          });
          
          var data = await res.json();
          if (data.success) {
            isDirty = false;
            lastSavedContent = content;
            if (editorStatus) editorStatus.textContent = 'Saved';
            setTimeout(function() {
              if (editorStatus && editorStatus.textContent === 'Saved') {
                editorStatus.textContent = 'Ready';
              }
            }, 2000);
            return true;
          } else {
            if (!silent) showToast(data.error || 'Failed to save', true);
            if (editorStatus) editorStatus.textContent = 'Error';
            return false;
          }
        } catch(e) {
          if (!silent) showToast('Failed to save', true);
          if (editorStatus) editorStatus.textContent = 'Error';
          return false;
        }
      }
      
      // Simple toast function for Monaco section
      function showToast(message, isError) {
        var existing = document.querySelector('.ginto-toast');
        if (existing) existing.remove();
        
        var toast = document.createElement('div');
        toast.className = 'ginto-toast' + (isError ? ' error' : '');
        toast.textContent = message;
        toast.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);' +
          'background:' + (isError ? '#ef4444' : '#1f2937') + ';color:#fff;padding:10px 20px;' +
          'border-radius:6px;font-size:13px;z-index:10000;box-shadow:0 4px 12px rgba(0,0,0,0.3);';
        document.body.appendChild(toast);
        setTimeout(function() { if (toast.parentNode) toast.remove(); }, 3000);
      }
      
      function bootstrapMonaco() {
        // Skip Monaco on mobile - CodeMirror handles it via editor-mobile.js
        if (window.isMobileDevice || window.isMobileEditor) {
          console.log('[editor-object] Skipping Monaco bootstrap - mobile editor active');
          return;
        }
        
        try {
          require.config({ paths: { vs: '/assets/vendor/monaco-editor/min/vs' } });
        } catch(e) {
          try {
            require.config({ paths: { vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.39.0/min/vs' } });
          } catch(e2) {}
        }
        
        require(['vs/editor/editor.main'], function() {
          var isDark = getCurrentTheme() === 'dark';
          var initialContent = textarea ? textarea.value : '';
          var initialLang = window.currentLanguage || 'plaintext';
          
          monacoEditor = monaco.editor.create(monacoContainer, {
            value: initialContent,
            language: initialLang,
            theme: isDark ? 'vs-dark' : 'vs',
            automaticLayout: true,
            minimap: { enabled: true, side: 'right' },
            scrollBeyondLastLine: false,
            fontSize: 13,
            wordWrap: 'off',
            renderLineHighlight: 'all',
            lineNumbers: 'on',
            folding: true,
            tabSize: 4,
            insertSpaces: true,
            stickyScroll: {
              enabled: true,
              maxLineCount: 5,
              defaultModel: 'outlineModel'
            }
          });
          
          if (textarea) textarea.style.display = 'none';
          monacoContainer.style.display = 'block';
          monacoReady = true;
          lastSavedContent = initialContent;
          
          // Track changes
          monacoEditor.onDidChangeModelContent(function() {
            isDirty = true;
            if (editorStatus) editorStatus.textContent = 'Modified';
            scheduleAutoSave();
          });
          
          monacoEditor.onDidChangeCursorPosition(updateCursor);
          
          // Ctrl+S to save
          monacoEditor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, function() {
            saveFile(false);
          });
          
          // Sync theme
          window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', syncMonacoTheme);
          
          // Watch class changes on documentElement
          var observer = new MutationObserver(function(mutations) {
            for (var i = 0; i < mutations.length; i++) {
              if (mutations[i].attributeName === 'class') {
                syncMonacoTheme();
              }
            }
          });
          observer.observe(document.documentElement, { attributes: true });
          
          updateCursor();
          if (langDisplay && initialLang) langDisplay.textContent = initialLang.toUpperCase();
          
          // Expose for external access
          window.GintoEditor = {
            getEditor: function() { return monacoEditor; },
            getContent: getEditorContent,
            setContent: function(content) {
              if (monacoEditor) monacoEditor.setValue(content);
            },
            getCurrentFile: function() { return window.currentFile; },
            getCurrentEncoded: function() { return window.currentEncoded; },
            showToast: showToast,
            save: saveFile,
            loadFile: function(encoded, path) {
              if (window.loadFile) window.loadFile(encoded, path);
            }
          };
          
          // Also expose as playgroundEditor for compatibility
          window.playgroundEditor = window.GintoEditor;
        });
      }
      
      // Check if Monaco loader is available (desktop only)
      // On mobile, GintoEditor is set up by editor-mobile.js
      if (window.isMobileDevice || window.isMobileEditor) {
        console.log('[editor-object] Mobile device - skipping Monaco loader check');
      } else if (typeof require !== 'undefined' && require.config) {
        bootstrapMonaco();
      } else {
        // Wait for Monaco loader
        var checkInterval = setInterval(function() {
          if (window.isMobileDevice || window.isMobileEditor) {
            clearInterval(checkInterval);
            return;
          }
          if (typeof require !== 'undefined' && require.config) {
            clearInterval(checkInterval);
            bootstrapMonaco();
          }
        }, 100);
        
        // Timeout after 5 seconds
        setTimeout(function() {
          clearInterval(checkInterval);
        }, 5000);
      }
    })();

  });

})();
