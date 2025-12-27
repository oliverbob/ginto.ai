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

  function createMessageEl(who, text, html) {
    var el = document.createElement('div');
    el.className = 'assistant-message ' + (who === 'user' ? 'user' : 'bot');
    var bubble = document.createElement('div');
    bubble.className = 'bubble';
    if (html) {
      bubble.innerHTML = html;
    } else {
      bubble.textContent = text || '';
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
        display: block;
        margin: 12px 0;
        padding: 8px 0;
      }
      /* dotted rule with left ribbon */
      .restore-checkpoint-wrapper::before {
        content: '';
        display: block;
        border-top: 1px dotted rgba(156,163,175,0.35);
        margin: 0 8px;
        position: absolute;
        left: 0; right: 0; top: 50%; transform: translateY(-50%);
        pointer-events: none;
      }
      .restore-checkpoint-wrapper::after {
        /* ribbon */
        content: '';
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        width: 10px; height: 18px;
        background: linear-gradient(180deg,#8b5cf6,#6d28d9);
        border-radius: 3px;
        box-shadow: 0 1px 0 rgba(255,255,255,0.02) inset;
      }
      .restore-checkpoint-btn,
      .redo-checkpoint-btn {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        font-size: 12px;
        font-weight: 500;
        color: #a78bfa;
        background: rgba(139, 92, 246, 0.06);
        border: 1px dashed rgba(139, 92, 246, 0.14);
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.12s ease;
        opacity: 0; /* hidden by default */
        transform: translateY(-2px);
      }
      /* reveal button when hovering the wrapper */
      .restore-checkpoint-wrapper:hover .restore-checkpoint-btn,
      .restore-checkpoint-wrapper:hover .redo-checkpoint-btn {
        opacity: 1;
        transform: translateY(0);
      }
      .restore-checkpoint-btn svg,
      .redo-checkpoint-btn svg {
        flex-shrink: 0;
      }
      .restore-checkpoint-wrapper.redo .redo-checkpoint-btn {
        color: #34d399;
        background: rgba(52, 211, 153, 0.06);
        border-color: rgba(52, 211, 153, 0.14);
      }
      .restore-checkpoint-wrapper.redo .redo-checkpoint-btn:hover {
        background: rgba(52, 211, 153, 0.12);
        border-color: #10b981;
        color: #6ee7b7;
      }
      .restore-checkpoint-btn svg,
      .redo-checkpoint-btn svg {
        flex-shrink: 0;
      }
      .restore-checkpoint-wrapper.redo .redo-checkpoint-btn {
        color: #34d399;
        background: rgba(52, 211, 153, 0.1);
        border-color: rgba(52, 211, 153, 0.4);
      }
      .restore-checkpoint-wrapper.redo .redo-checkpoint-btn:hover {
        background: rgba(52, 211, 153, 0.2);
        border-color: #10b981;
        color: #6ee7b7;
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
      @keyframes spin {
        from { transform: rotate(0deg); }
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
        return `<li>
          <span class="file-icon">${icon}</span>
          <span class="file-name" title="${escapeHtml(file.path || file.name)}">${escapeHtml(name)}</span>
          <span class="file-stats">
            <span class="added">+${file.added || 0}</span>
            <span class="removed">-${file.removed || 0}</span>
          </span>
        </li>`;
      }).join('');
      
      if (filesChanged.length > 0) show();
      saveToStorage();
    }
    
    function addFileChange(path, added = 0, removed = 0) {
      const existing = filesChanged.find(f => f.path === path);
      if (existing) {
        existing.added = (existing.added || 0) + added;
        existing.removed = (existing.removed || 0) + removed;
      } else {
        filesChanged.push({ path, added, removed });
      }
      updateFiles(filesChanged);
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
          // Clear file changes - they're "kept"
          filesChanged = [];
          updateFiles([]);
          if (window.playgroundEditor && window.playgroundEditor.showToast) {
            window.playgroundEditor.showToast('Changes kept');
          }
        });
      }
      
      if (el.undoBtn) {
        el.undoBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          // Trigger restore of all checkpoints
          const checkpoints = typeof getAiCheckpoints === 'function' ? getAiCheckpoints() : [];
          if (checkpoints.length > 0) {
            // Restore the oldest checkpoint
            const oldest = checkpoints[checkpoints.length - 1];
            if (typeof restoreAiCheckpoint === 'function') {
              restoreAiCheckpoint(oldest.id);
            }
          }
          filesChanged = [];
          updateFiles([]);
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
      getTodos: () => [...todos],
      getFiles: () => [...filesChanged]
    };
  })();
  
  // Expose globally
  window.__gintoProgressTracker = progressTracker;

  // Parse todos from AI response text
  function parseTodosFromResponse(text) {
    if (!text || !window.__gintoProgressTracker) return;
    
    const todos = [];
    
    // Look for sai-thinking blocks with TODO LIST
    const todoListMatch = text.match(/<div class="sai-thinking">[^]*?TODO LIST[^]*?<\/div>/gi);
    if (todoListMatch) {
      todoListMatch.forEach(block => {
        // Extract numbered items: 1. Task, 2. Task, etc.
        const itemMatches = block.match(/(\d+)\.\s*\[?([^\]\n<]+)\]?/g);
        if (itemMatches) {
          itemMatches.forEach(item => {
            const match = item.match(/(\d+)\.\s*\[?([xX\s]?)\]?\s*(.+)/);
            if (match) {
              const done = match[2] && match[2].toLowerCase() === 'x';
              todos.push({
                title: match[3].trim(),
                done: done,
                status: done ? 'completed' : 'pending'
              });
            }
          });
        }
      });
    }
    
    // Also look for COMPLETED or IMPLEMENTING blocks to update status
    const completedMatch = text.match(/COMPLETED[^]*?(?:\[x\]|\✓|✅)\s*([^\n<]+)/gi);
    if (completedMatch) {
      completedMatch.forEach(item => {
        const taskMatch = item.match(/(?:\[x\]|✓|✅)\s*(.+)/i);
        if (taskMatch) {
          const taskTitle = taskMatch[1].trim();
          // Find and mark as done
          const existing = todos.find(t => t.title.toLowerCase().includes(taskTitle.toLowerCase().substring(0, 20)));
          if (existing) {
            existing.done = true;
            existing.status = 'completed';
          }
        }
      });
    }
    
    // Look for IMPLEMENTING to mark in-progress
    const implementingMatch = text.match(/IMPLEMENTING[:\s]+([^\n<]+)/gi);
    if (implementingMatch) {
      implementingMatch.forEach(item => {
        const taskMatch = item.match(/IMPLEMENTING[:\s]+(.+)/i);
        if (taskMatch) {
          const taskTitle = taskMatch[1].trim();
          const existing = todos.find(t => t.title.toLowerCase().includes(taskTitle.toLowerCase().substring(0, 20)));
          if (existing) {
            existing.status = 'in-progress';
          }
        }
      });
    }
    
    if (todos.length > 0) {
      window.__gintoProgressTracker.updateTodos(todos);
    }
  }

  function scrollToBottom(container) {
    if (!container) return;
    container.scrollTop = container.scrollHeight;
  }

  function isScrolledToBottom(container, threshold) {
    try {
      if (!container) return true;
      var t = (typeof threshold === 'number') ? threshold : 40;
      var distance = container.scrollHeight - container.clientHeight - container.scrollTop;
      return distance <= t;
    } catch(e) { return true; }
  }

  // Simple markdown to HTML converter
  function simpleMarkdownToHtml(md) {
    if (!md) return '';
    let content = md.trim();
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
          // Bullet points
          if (line.match(/^[-•]\s/)) {
            return '<div class="todo-item">' + line.replace(/^[-•]\s*/, '• ') + '</div>';
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
    
    for (const line of lines) {
      if (line.startsWith('```')) {
        if (!inCodeBlock) {
          inCodeBlock = true;
          codeBlockLang = line.slice(3).trim();
          codeBlockContent = [];
        } else {
          const codeId = 'code-' + Math.random().toString(36).slice(2, 10);
          const codeContent = codeBlockContent.join('\n');
          // Add "Apply to Editor" button for code blocks when a file is open
          const editor = window.playgroundEditor;
          const hasFile = editor && editor.getCurrentFile();
          const applyButton = hasFile ? 
            '<button class="apply-code-btn" data-code-id="' + codeId + '" onclick="window.__gintoFileWrite && window.__gintoFileWrite.show(window.playgroundEditor.getCurrentFile(), document.getElementById(\'' + codeId + '\').textContent, false)" title="Apply this code to the current file">📝 Apply to Editor</button>' : 
            '';
          result.push('<div class="code-block-wrapper"><div class="code-header"><span class="code-lang">' + (codeBlockLang || 'code') + '</span>' + applyButton + '</div><pre><code class="language-' + codeBlockLang + '" id="' + codeId + '">' + codeContent + '</code></pre></div>');
          inCodeBlock = false;
        }
        continue;
      }
      if (inCodeBlock) {
        codeBlockContent.push(line);
        continue;
      }
      
      if (line.startsWith('### ')) {
        result.push('<h3>' + formatInline(line.slice(4)) + '</h3>');
      } else if (line.startsWith('## ')) {
        result.push('<h2>' + formatInline(line.slice(3)) + '</h2>');
      } else if (line.startsWith('# ')) {
        result.push('<h1>' + formatInline(line.slice(2)) + '</h1>');
      } else if (line.trim() === '') {
        // empty line
      } else {
        result.push('<p>' + formatInline(line) + '</p>');
      }
    }
    
    let finalHtml = result.join('\n');
    
    // Restore thinking blocks
    thinkingBlocks.forEach((block, idx) => {
      finalHtml = finalHtml.replace(new RegExp('<p>' + thinkingPlaceholder + idx + '___</p>', 'g'), block);
      finalHtml = finalHtml.replace(new RegExp(thinkingPlaceholder + idx + '___', 'g'), block);
    });
    
    return finalHtml;
    
    function formatInline(text) {
      return text
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/`([^`]+)`/g, '<code>$1</code>');
    }
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
    const editor = window.playgroundEditor;
    if (!editor) return;
    const currentFile = editor.getCurrentFile ? editor.getCurrentFile() : window.currentFile;
    const normalizedCurrent = normalizeRepoPath(currentFile);
    if (!normalizedCurrent || normalizedCurrent !== fileWriteState.pathNormalized) return;

    fileWriteState.newContent = content;
    queueInlineTyping(content);
  }

  function setEditorBuffer(value) {
    const editor = window.playgroundEditor;
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
      window.__gintoProgressTracker.addFileChange(path, added, removed);
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
    var editor = window.playgroundEditor;
    var currentFile = editor ? editor.getCurrentFile() : window.currentFile;
    var normalizedCurrent = normalizeRepoPath(currentFile);

    // If restoring to the currently open file, update the editor directly
    if (checkpoint.pathNormalized === normalizedCurrent && editor && typeof editor.setValue === 'function') {
      // Move this checkpoint and all checkpoints before it (newer) to redo stack
        // For stricter sandbox-like behavior, once a restore has been applied
        // we clear forward (redo) history so forward operations are unavailable.
        var remainingCheckpoints = checkpoints.slice(checkpointIndex + 1);
        saveAiCheckpoints(remainingCheckpoints);
        clearRedoStack();
      
      // Apply the restore
      editor.setValue(checkpoint.originalContent);
      if (typeof editor.setDirty === 'function') {
        editor.setDirty(true);
      }
      if (editor.showToast) {
        editor.showToast('Restored to checkpoint: ' + checkpoint.path.split('/').pop());
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
    
    var editor = window.playgroundEditor;
    var currentFile = editor ? editor.getCurrentFile() : window.currentFile;
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
    const editor = window.playgroundEditor;

    if (fileWriteState.mode === 'inline') {
      notifyInlineComplete();
      hideFileWritePreview();
      return;
    }

    if (editor) {
      const currentFile = editor.getCurrentFile ? editor.getCurrentFile() : window.currentFile;
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
    const editor = window.playgroundEditor;
    const original = fileWriteState.originalContent;
    const targetPath = fileWriteState.path;
    const normalizedTarget = fileWriteState.pathNormalized || normalizeRepoPath(targetPath);

    if (editor && typeof original === 'string') {
      const currentFile = editor.getCurrentFile ? editor.getCurrentFile() : window.currentFile;
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
    const editor = window.playgroundEditor;
    const currentFile = editor ? editor.getCurrentFile() : window.currentFile;
    
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
        // Show premium upgrade modal
        if (typeof window.showUpgradeModal === 'function') {
          window.showUpgradeModal(errorData.error || 'This feature requires a Premium subscription.');
        } else {
          // Fallback: redirect to upgrade page
          if (confirm((errorData.error || 'This feature requires Premium.') + '\n\nPremium starts at ₱200/week. Would you like to upgrade?')) {
            window.location.href = '/upgrade';
          }
        }
        throw new Error(errorData.error || 'Premium subscription required');
      }
      if (errorData?.action === 'login') {
        // Redirect to login
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

  // Helper to append bot message in continuation context (uses global helpers)
  function appendBotMessage(bodyEl, text) {
    try {
      var htmlContent = simpleMarkdownToHtml(String(text || ''));
      var msgEl = createMessageEl('bot', null, htmlContent);
      if (bodyEl) {
        bodyEl.appendChild(msgEl);
        // Scroll to bottom
        bodyEl.scrollTop = bodyEl.scrollHeight;
      }
    } catch (e) {
      console.error('[EditorAgent] appendBotMessage error:', e);
    }
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
      html += '<div style="background:rgba(31,41,55,0.5);border-radius:8px;padding:12px;font-family:monospace;font-size:13px;">';
      
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
    
    // sandbox_read_file - show file content
    if (toolName === 'sandbox_read_file' && data?.content !== undefined) {
      var path = data.path || 'file';
      var content = data.content || '';
      return '<div class="tool-result" style="margin:8px 0;">' +
        '<p style="color:#d1d5db;margin-bottom:8px;">Contents of <code style="background:#374151;padding:2px 6px;border-radius:4px;">' + escapeHtml(path) + '</code>:</p>' +
        '<pre style="background:rgba(31,41,55,0.5);border-radius:8px;padding:12px;font-size:13px;overflow-x:auto;max-height:300px;margin:0;"><code>' + escapeHtml(content) + '</code></pre>' +
        '</div>';
    }
    
    // sandbox_write_file - confirm file was written with download link
    if (toolName === 'sandbox_write_file' && data?.success) {
      var path = data.path || 'file';
      var bytes = data.bytes_written || 0;
      var url = data.url || '/clients/' + path.replace(/^\//, '');
      return '<div class="tool-result success" style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:12px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);border-radius:8px;color:#86efac;margin:8px 0;">' +
        '<div style="display:flex;align-items:center;gap:8px;">' +
        '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>' +
        '<span>Created <code style="background:#374151;padding:2px 6px;border-radius:4px;">' + escapeHtml(path) + '</code>' + (bytes > 0 ? ' (' + bytes + ' bytes)' : '') + '</span>' +
        '</div>' +
        '<a href="' + escapeHtml(url) + '" target="_blank" style="display:flex;align-items:center;gap:4px;padding:4px 8px;background:rgba(34,197,94,0.2);border-radius:4px;color:#86efac;text-decoration:none;font-size:13px;">' +
        '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>' +
        'Open</a>' +
        '</div>';
    }
    
    // sandbox_delete_file - confirm file was deleted
    if (toolName === 'sandbox_delete_file' && data?.success) {
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
    
    // sandbox_create_project - show project creation result
    if (toolName === 'sandbox_create_project') {
      if (data?.success) {
        var files = data.files_created || [];
        var projectName = data.project_name || 'project';
        var projectType = data.template_name || data.project_type || 'Project';
        var runHint = data.run_hint || '';
        return '<div class="tool-result success" style="padding:16px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);border-radius:8px;margin:8px 0;">' +
          '<div style="display:flex;align-items:center;gap:8px;color:#86efac;margin-bottom:12px;">' +
          '<svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' +
          '<span style="font-weight:600;font-size:16px;">' + escapeHtml(projectType) + ' Created!</span></div>' +
          '<div style="margin-bottom:12px;color:#d1d5db;"><span style="color:#9ca3af;">Project:</span> <code style="background:#374151;padding:4px 8px;border-radius:4px;color:#86efac;">' + escapeHtml(projectName) + '</code></div>' +
          '<details style="margin-bottom:12px;"><summary style="cursor:pointer;color:#9ca3af;font-size:13px;">' + files.length + ' files created</summary>' +
          '<ul style="margin-top:8px;font-size:13px;color:#9ca3af;list-style:none;padding-left:16px;">' +
          files.map(function(f) { return '<li style="padding:2px 0;">📄 ' + escapeHtml(f) + '</li>'; }).join('') +
          '</ul></details>' +
          (runHint ? '<div style="padding:8px;background:rgba(31,41,55,0.5);border-radius:4px;font-size:13px;color:#d1d5db;"><span style="color:#6b7280;">To run:</span> <code style="color:#60a5fa;">' + escapeHtml(runHint) + '</code></div>' : '') +
          '</div>';
      } else {
        return '<div class="tool-result error" style="padding:12px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:8px;color:#fca5a5;margin:8px 0;">' +
          '<div style="display:flex;align-items:center;gap:8px;">' +
          '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' +
          '<span>Failed to create project: ' + escapeHtml(data?.error || 'Unknown error') + '</span></div>' +
          (data?.available_types ? '<div style="margin-top:8px;font-size:13px;color:#9ca3af;">Available types: ' + data.available_types.join(', ') + '</div>' : '') +
          '</div>';
      }
    }
    
    // sandbox_list_project_types - show available templates
    if (toolName === 'sandbox_list_project_types' && data?.success) {
      var types = data.project_types || [];
      return '<div class="tool-result" style="padding:16px;background:rgba(31,41,55,0.5);border:1px solid #374151;border-radius:8px;margin:8px 0;">' +
        '<div style="display:flex;align-items:center;gap:8px;color:#e5e7eb;margin-bottom:12px;">' +
        '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#60a5fa;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>' +
        '<span style="font-weight:600;">Available Project Templates</span></div>' +
        '<div style="display:grid;gap:8px;">' +
        types.map(function(t) {
          return '<div style="padding:8px;background:rgba(17,24,39,0.5);border-radius:4px;display:flex;align-items:center;justify-content:space-between;">' +
            '<div><code style="color:#60a5fa;font-family:monospace;">' + escapeHtml(t.type) + '</code><span style="color:#9ca3af;margin-left:8px;">' + escapeHtml(t.name) + '</span></div>' +
            '<span style="font-size:12px;color:#6b7280;">' + escapeHtml(t.description) + '</span></div>';
        }).join('') +
        '</div>' +
        '<div style="margin-top:12px;font-size:13px;color:#9ca3af;">Use: "Create a [type] project called [name]"</div>' +
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

  // Clean tool_call JSON from HTML content
  function cleanToolCallFromHtml(html) {
    if (!html) return html;
    var patterns = [
      /\{"tool_call"\s*:\s*\{[^}]*"name"\s*:\s*"sandbox_[^"]*"[^}]*"arguments"\s*:\s*\{[^}]*\}[^}]*\}\}/g,
      /\{"tool_call"\s*:\s*\{[^}]*\}\}/g,
      /```json\s*\{"tool_call"[\s\S]*?\}\s*```/g,
      /\{["\s]*tool_call["\s]*:[\s\S]*?sandbox_[^}]*\}\s*\}/g
    ];
    var cleaned = html;
    for (var i = 0; i < patterns.length; i++) {
      cleaned = cleaned.replace(patterns[i], '');
    }
    // Clean up empty elements
    cleaned = cleaned.replace(/<p>\s*<\/p>/g, '');
    cleaned = cleaned.replace(/<pre><code[^>]*>\s*<\/code><\/pre>/g, '');
    return cleaned;
  }

  // Continue agent plan execution after a tool completes in the editor chat
  // This sends the tool result back to the AI to get the next step
  async function continueEditorAgentPlan(bodyEl, toolName, toolResult, previousResponse, depth) {
    depth = depth || 0;
    const MAX_DEPTH = 10;
    
    if (depth >= MAX_DEPTH) {
      console.log('[EditorAgent] Max depth reached, stopping');
      appendBotMessage(bodyEl, 'Plan execution complete (max steps reached)');
      return;
    }
    
    // Build continuation prompt with tool result
    const continuePrompt = '[TOOL RESULT] Tool "' + toolName + '" completed:\n' + JSON.stringify(toolResult, null, 2) + '\n\nContinue with the next step of your plan. If there are more steps, output the next tool_call JSON. If all steps are complete, provide a brief summary.';
    
    console.log('[EditorAgent] Continuing plan, depth:', depth);
    
    // Show continuation indicator
    var continueNote = document.createElement('div');
    continueNote.className = 'assistant-message bot';
    continueNote.innerHTML = '<div class="bubble" style="opacity:0.7;font-style:italic;">Continuing...</div>';
    if (bodyEl) bodyEl.appendChild(continueNote);
    
    try {
      // Build history for continuation
      var continuationHistory = getActiveHistory().slice();
      if (previousResponse && (!continuationHistory.length || continuationHistory[continuationHistory.length - 1].content !== previousResponse)) {
        continuationHistory.push({ role: 'assistant', content: previousResponse });
      }
      
      // Call the editor chat endpoint
      var payload = {
        message: continuePrompt,
        history: continuationHistory
      };
      
      var res = await fetch('/admin/pages/editor/chat', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'text/event-stream',
          'X-CSRF-Token': getCsrf()
        },
        body: JSON.stringify(payload)
      });
      
      console.log('[EditorAgent] Response status:', res.status);
      
      if (!res.ok) {
        console.error('[EditorAgent] Bad response:', res.status);
        if (continueNote.parentNode) continueNote.remove();
        return;
      }
      
      // Read streaming response
      var reader = res.body.getReader();
      var decoder = new TextDecoder();
      var buffer = '';
      var aiResponse = '';
      
      while (true) {
        var chunk = await reader.read();
        if (chunk.done) break;
        
        buffer += decoder.decode(chunk.value, { stream: true });
        var lines = buffer.split('\n');
        buffer = lines.pop() || '';
        
        for (var i = 0; i < lines.length; i++) {
          var line = lines[i];
          if (!line.startsWith('data:')) continue;
          var jsonStr = line.slice(5).trim();
          if (!jsonStr || jsonStr === '[DONE]') continue;
          
          try {
            var data = JSON.parse(jsonStr);
            // Handle various content formats
            if (data.content) aiResponse += data.content;
            else if (data.text) aiResponse += data.text;
            else if (data.choices && data.choices[0]) {
              if (data.choices[0].delta && data.choices[0].delta.content) aiResponse += data.choices[0].delta.content;
              else if (data.choices[0].message && data.choices[0].message.content) aiResponse += data.choices[0].message.content;
              else if (data.choices[0].text) aiResponse += data.choices[0].text;
            }
          } catch (e) {
            // Not JSON, might be raw text
            if (jsonStr && jsonStr !== '[DONE]') aiResponse += jsonStr;
          }
        }
      }
      
      // Remove continuation indicator
      if (continueNote.parentNode) continueNote.remove();
      
      console.log('[EditorAgent] AI response length:', aiResponse.length);
      
      if (!aiResponse) {
        console.log('[EditorAgent] No AI response, stopping');
        return;
      }
      
      // Clean tool_call JSON from display
      var cleanedContent = aiResponse;
      var patterns = [
        /\{"tool_call"\s*:\s*\{[^}]*"name"\s*:\s*"sandbox_[^"]*"[^}]*"arguments"\s*:\s*\{[^}]*\}[^}]*\}\}/g,
        /\{"tool_call"\s*:\s*\{[^}]*\}\}/g
      ];
      for (var p = 0; p < patterns.length; p++) {
        cleanedContent = cleanedContent.replace(patterns[p], '');
      }
      cleanedContent = cleanedContent.trim();
      
      // Display the cleaned response
      if (cleanedContent) {
        appendBotMessage(bodyEl, cleanedContent);
      }
      
      // Add to history
      getActiveHistory().push({ role: 'assistant', content: aiResponse });
      saveTabsToStorage();
      
      // Check for next tool call
      var nextToolCall = extractToolCallFromText(aiResponse);
      if (nextToolCall && nextToolCall.name && nextToolCall.name.startsWith('sandbox_')) {
        console.log('[EditorAgent] Found next tool call:', nextToolCall.name);
        
        // Show a spinner while executing
        var execNote = document.createElement('div');
        execNote.className = 'tool-exec-indicator';
        execNote.style.cssText = 'margin:8px 0;padding:12px;background:rgba(31,41,55,0.5);border:1px solid #374151;border-radius:8px;';
        execNote.innerHTML = '<div style="display:flex;align-items:center;gap:8px;color:#d1d5db;">' +
          '<svg style="width:16px;height:16px;animation:spin 1s linear infinite;" fill="none" viewBox="0 0 24 24">' +
          '<circle style="opacity:0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
          '<path style="opacity:0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>' +
          '</svg><span>Working on it...</span></div>';
        if (bodyEl) bodyEl.appendChild(execNote);
        if (bodyEl) bodyEl.scrollTop = bodyEl.scrollHeight;
        
        try {
          var nextResult = await executeToolCall(nextToolCall);
          console.log('[EditorAgent] Next tool result:', nextResult);
          
          // Replace spinner with formatted result
          execNote.className = '';
          execNote.style.cssText = '';
          execNote.innerHTML = formatToolResult(nextToolCall.name, nextResult);
          
          // Recursively continue if successful
          if (nextResult && nextResult.success !== false) {
            await continueEditorAgentPlan(bodyEl, nextToolCall.name, nextResult, aiResponse, depth + 1);
          }
        } catch (err) {
          console.error('[EditorAgent] Tool execution failed:', err);
          execNote.innerHTML = formatToolResult(nextToolCall.name, { error: err.message || 'Tool execution failed' });
        }
      }
      
    } catch (err) {
      console.error('[EditorAgent] Continuation failed:', err);
      if (continueNote.parentNode) continueNote.remove();
    }
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

  document.addEventListener('DOMContentLoaded', function(){
    var pane = document.getElementById('assistant-pane');
    var panel = document.getElementById('editor-chat-panel') || pane;
    var body = document.getElementById('assistant-body') || document.getElementById('editor-chat-body');
    var input = document.getElementById('assistant-input') || document.getElementById('editor-chat-input');
    var sendBtn = document.getElementById('assistant-send') || document.getElementById('editor-chat-send');

    if (!panel || !body || !input || !sendBtn) return;

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
        sendBtn.disabled = !((this.value||'').trim());
        if (sendBtn.disabled) sendBtn.setAttribute('aria-disabled','true'); else sendBtn.removeAttribute('aria-disabled');
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
                // Agentic continuation for sandbox tools
                if (toolCall.name && toolCall.name.startsWith('sandbox_') && result && result.success !== false) {
                  console.log('[AppendBot] Starting agent continuation for:', toolCall.name);
                  continueEditorAgentPlan(body, toolCall.name, result, text);
                }
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
      const empty = body.querySelector('.assistant-empty'); if (empty) empty.remove();

      appendUserText(text);
      input.value = '';
      try { setTimeout(function(){ autoGrowTextarea(input, 400); }, 0); } catch(e) {}

      // Add user message to active tab's history (ensure default file assumption is present)
      var activeHistory = chatTabs[activeTabId]?.history || conversationHistory;
      var defaultFileInstruction = 'You are an embedded editor assistant. Unless the user explicitly names a different file path, assume they want the currently open file. Confirm before touching any other file. When working on PHP files, preserve the literal closing tag "?>" when it exists and do not emit it HTML-escaped.';
      if (!activeHistory.some(function(msg){ return msg && msg.role === 'system' && msg.content === defaultFileInstruction; })) {
        activeHistory.unshift({ role: 'system', content: defaultFileInstruction });
      }
      activeHistory.push({ role: 'user', content: text });

      sendBtn.disabled = true; sendBtn.classList.add('sending');
      try {
        // Use FormData like /chat page does
        const form = new FormData();
        
        // Build prompt with file context if available
        let promptText = text;
        const editor = window.playgroundEditor;
        const currentFile = editor ? editor.getCurrentFile() : window.currentFile;
        
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
          
          if (isModificationRequest && editor && editor.isReady()) {
            const fileContent = editor.getValue();
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
          }
        });

        const ct = (res.headers && res.headers.get) ? (res.headers.get('Content-Type') || '') : '';
        
        // Accumulated content for history
        let accumulatedContent = '';
        
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
              // Prefer a page-provided thoughts template if available. The template
              // can be placed anywhere in the DOM with id="assistant-thought-template"
              // or injected via `window.ASSISTANT_THOUGHT_TEMPLATE` as an HTML string.
              var tplEl = document.getElementById('assistant-thought-template');
              var tplHtml = (typeof window.ASSISTANT_THOUGHT_TEMPLATE === 'string') ? window.ASSISTANT_THOUGHT_TEMPLATE : (tplEl ? tplEl.innerHTML : null);
              if (tplHtml && tplHtml.trim()) {
                // Create a structure that contains a thoughts stream area and a
                // separate assistant streaming text area. The thoughts stream will
                // be progressively filled as fragments arrive.
                bubble.innerHTML = '<div class="sai-thinking-wrapper">' + tplHtml + '<div class="sai-thinking-stream" aria-hidden="false" style="white-space:pre-wrap;margin-top:8px;color:var(--assistant-muted)"></div></div>' +
                                   '<div class="assistant-stream-text" style="margin-top:8px;white-space:pre-wrap"></div>';
              } else {
                bubble.innerHTML = '<span class="thinking-indicator">Thinking<span class="dots"><span>.</span><span>.</span><span>.</span></span></span>' +
                                   '<div class="assistant-stream-text" style="margin-top:8px;white-space:pre-wrap"></div>';
              }
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
                    const currentFile = editor ? editor.getCurrentFile() : window.currentFile;
                    const status = obj.status || 'executing';
                    const result = obj.result || {};
                    const toolPath = obj.path || result.path || null;
                    const normalizedToolPath = normalizeRepoPath(toolPath);
                    const normalizedCurrent = normalizeRepoPath(currentFile);
                    const originalFromResult = (result.original !== undefined) ? result.original : null;
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
                    if (bubble) bubble.innerHTML = obj.html;
                    if (isScrolledToBottom(body, 48)) scrollToBottom(body);
                    
                    // Check for text-based tool calls in the final response and execute them
                    // This handles cases where the LLM outputs tool calls as text rather than structured calls
                    try {
                      var toolCall = extractToolCallFromText(accumulatedContent);
                      if (toolCall) {
                        console.log('[ToolExec] Detected text-based tool call:', toolCall.name);
                        
                        // Check if it's a sandbox tool (allowed for all users)
                        var isSandboxTool = toolCall.name && toolCall.name.startsWith('sandbox_');
                        var autoRun = localStorage.getItem('ginto_auto_run_tools') === '1';
                        
                        if (isSandboxTool || autoRun || toolCall.name === 'write_file' || toolCall.name === 'replace_in_file' || toolCall.name === 'create_file') {
                          // Clean the tool_call JSON from the bubble first
                          if (bubble) {
                            bubble.innerHTML = cleanToolCallFromHtml(bubble.innerHTML);
                          }
                          
                          // Show a spinner while executing
                          var execNote = document.createElement('div');
                          execNote.className = 'tool-exec-indicator';
                          execNote.style.cssText = 'margin:8px 0;padding:12px;background:rgba(31,41,55,0.5);border:1px solid #374151;border-radius:8px;';
                          execNote.innerHTML = '<div style="display:flex;align-items:center;gap:8px;color:#d1d5db;">' +
                            '<svg style="width:16px;height:16px;animation:spin 1s linear infinite;" fill="none" viewBox="0 0 24 24">' +
                            '<circle style="opacity:0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
                            '<path style="opacity:0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>' +
                            '</svg><span>Working on it...</span></div>';
                          if (body) body.appendChild(execNote);
                          if (isScrolledToBottom(body, 48)) scrollToBottom(body);
                          
                          var toolPath = toolCall.arguments?.path || toolCall.args?.path || null;
                          var toolContent = toolCall.arguments?.content || toolCall.args?.content || '';
                          var editorRef = window.playgroundEditor;
                          var activeFile = editorRef ? editorRef.getCurrentFile() : window.currentFile;
                          
                          console.log('[ToolExec] Executing tool:', toolCall.name, 'path:', toolPath);
                          
                          // Execute the tool
                          executeToolCall(toolCall).then(function(result) {
                            console.log('[ToolExec] Tool result:', result);
                            
                            // Replace spinner with formatted result
                            execNote.className = '';
                            execNote.style.cssText = '';
                            execNote.innerHTML = formatToolResult(toolCall.name, result);
                            
                            // Create checkpoint for file modifications
                            var checkpointCreated = null;
                            if (result && result.success !== false && toolPath) {
                              checkpointCreated = createAiCheckpoint(
                                toolPath,
                                result.original || '',
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
                            
                            // === AGENTIC CONTINUATION ===
                            // For sandbox tools, continue the agent loop to execute multi-step plans
                            if (isSandboxTool && result && result.success !== false) {
                              console.log('[ToolExec] Starting agent continuation for:', toolCall.name);
                              continueEditorAgentPlan(body, toolCall.name, result, accumulatedContent);
                            }
                          }).catch(function(err) {
                            console.error('[ToolExec] Tool execution failed:', err);
                            execNote.innerHTML = formatToolResult(toolCall.name, { error: err.message || 'Tool execution failed' });
                          });
                        }
                      }
                    } catch(e) { console.debug('Text-based tool call detection failed', e); }
                    
                    continue;
                  }
                  
                  // Handle text fragments
                  var extracted = null;
                  if (obj && typeof obj === 'object') {
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
                  // Non-JSON payload — append raw text (buffered)
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

            // left-over buffer after stream end
            if (buf.trim()) {
              try { 
                const obj = JSON.parse(buf.trim()); 
                if (obj && obj.text) { 
                  accumulatedContent += obj.text;
                  var bubble = tempEl.querySelector('.bubble'); 
                  if (bubble) bubble.textContent += obj.text; 
                } else if (obj && obj.html && obj.final) {
                  // Final HTML render
                  var bubble = tempEl.querySelector('.bubble');
                  if (bubble) bubble.innerHTML = obj.html;
                } else { 
                  var bubble4 = tempEl.querySelector('.bubble'); 
                  if (bubble4) bubble4.textContent += buf; 
                } 
              } catch(e) { 
                var bubble5 = tempEl.querySelector('.bubble'); 
                if (bubble5) bubble5.textContent += buf; 
              }
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
        } catch (err) { appendBotText('Error reading response: ' + (err && err.message ? err.message : 'unknown')); }
      } catch (err) { appendBotText('Request failed: ' + (err && err.message ? err.message : 'unknown')); }
      finally { 
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

    sendBtn.addEventListener('click', function(){ sendMessage(); });
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

  });

})();
