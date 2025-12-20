/**
 * Mobile Editor - CodeMirror 5 based editor for mobile devices
 * Drop-in replacement for Monaco when on mobile/touch devices
 * 
 * This file provides the same API as GintoEditor but uses CodeMirror 5
 * which has proper touch/mobile support.
 */

(function() {
  'use strict';

  // ============ MOBILE DETECTION ============
  window.isMobileEditor = (function() {
    // Check for touch capability + small screen
    var hasTouchScreen = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    var isSmallScreen = window.innerWidth < 768;
    var isMobileUA = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    
    // Mobile if: (touch + small screen) OR mobile user agent
    return (hasTouchScreen && isSmallScreen) || (isMobileUA && isSmallScreen);
  })();

  // Only initialize if mobile
  if (!window.isMobileEditor) {
    console.log('[MobileEditor] Desktop detected, skipping CodeMirror initialization');
    return;
  }

  console.log('[MobileEditor] Mobile detected, initializing CodeMirror editor');

  // ============ CONFIGURATION ============
  var CM_BASE = '/assets/lib/codemirror/5.65.18';
  
  // Language mode mapping (file extension -> CodeMirror mode)
  var LANGUAGE_MODES = {
    'js': 'javascript',
    'javascript': 'javascript',
    'json': { name: 'javascript', json: true },
    'ts': 'javascript', // TypeScript uses JS mode
    'jsx': 'jsx',
    'tsx': 'jsx',
    'php': 'php',
    'html': 'htmlmixed',
    'htm': 'htmlmixed',
    'css': 'css',
    'scss': 'css',
    'less': 'css',
    'py': 'python',
    'python': 'python',
    'sh': 'shell',
    'bash': 'shell',
    'zsh': 'shell',
    'sql': 'sql',
    'xml': 'xml',
    'svg': 'xml',
    'md': 'markdown',
    'markdown': 'markdown',
    'c': 'clike',
    'cpp': 'clike',
    'h': 'clike',
    'java': 'clike',
    'cs': 'clike',
    'go': 'clike',
    'plaintext': null,
    'txt': null
  };

  // Mode to file mapping for dynamic loading
  var MODE_FILES = {
    'javascript': 'mode/javascript/javascript.min.js',
    'php': 'mode/php/php.min.js',
    'htmlmixed': 'mode/htmlmixed/htmlmixed.min.js',
    'css': 'mode/css/css.min.js',
    'python': 'mode/python/python.min.js',
    'shell': 'mode/shell/shell.min.js',
    'sql': 'mode/sql/sql.min.js',
    'xml': 'mode/xml/xml.min.js',
    'clike': 'mode/clike/clike.min.js',
    'markdown': 'mode/markdown/markdown.min.js'
  };

  // ============ STATE ============
  var cmEditor = null;
  var isReady = false;
  var isDirty = false;
  var lastSavedContent = '';
  var currentFile = '';
  var currentEncoded = '';
  var autoSaveTimer = null;
  var loadedModes = {};

  // DOM elements
  var editorContainer = null;
  var editorStatus = null;
  var langDisplay = null;
  var cursorPos = null;

  // ============ UTILITY FUNCTIONS ============
  function getCurrentTheme() {
    // Primary check: look at the actual DOM state
    if (document.documentElement.classList.contains('dark')) return 'dark';
    return 'light';
  }

  function getLanguageFromPath(filepath) {
    if (!filepath) return 'plaintext';
    var ext = filepath.split('.').pop().toLowerCase();
    return ext || 'plaintext';
  }

  function getModeForLanguage(lang) {
    return LANGUAGE_MODES[lang] || null;
  }

  function loadScript(src, callback) {
    var script = document.createElement('script');
    script.src = src;
    script.onload = callback;
    script.onerror = function() {
      console.warn('[MobileEditor] Failed to load:', src);
      if (callback) callback();
    };
    document.head.appendChild(script);
  }

  function loadStylesheet(href) {
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
  }

  function loadMode(modeName, callback) {
    if (!modeName || loadedModes[modeName]) {
      if (callback) callback();
      return;
    }
    
    var modeFile = MODE_FILES[modeName];
    if (!modeFile) {
      if (callback) callback();
      return;
    }
    
    // Some modes have dependencies
    var deps = [];
    if (modeName === 'htmlmixed') {
      deps = ['xml', 'javascript', 'css'];
    } else if (modeName === 'php') {
      deps = ['xml', 'javascript', 'css', 'htmlmixed', 'clike'];
    }
    
    // Load dependencies first
    var depsLoaded = 0;
    if (deps.length === 0) {
      loadScript(CM_BASE + '/' + modeFile, function() {
        loadedModes[modeName] = true;
        if (callback) callback();
      });
    } else {
      deps.forEach(function(dep) {
        loadMode(dep, function() {
          depsLoaded++;
          if (depsLoaded === deps.length) {
            loadScript(CM_BASE + '/' + modeFile, function() {
              loadedModes[modeName] = true;
              if (callback) callback();
            });
          }
        });
      });
    }
  }

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

  // ============ EDITOR FUNCTIONS ============
  function getEditorContent() {
    return cmEditor ? cmEditor.getValue() : '';
  }

  function setEditorContent(content) {
    if (cmEditor) {
      cmEditor.setValue(content || '');
      cmEditor.clearHistory();
    }
  }

  function setEditorLanguage(lang) {
    if (!cmEditor) return;
    
    var mode = getModeForLanguage(lang);
    if (!mode) {
      cmEditor.setOption('mode', null);
      if (langDisplay) langDisplay.textContent = (lang || 'TEXT').toUpperCase();
      return;
    }
    
    var modeName = typeof mode === 'string' ? mode : mode.name;
    loadMode(modeName, function() {
      cmEditor.setOption('mode', mode);
      if (langDisplay) langDisplay.textContent = (lang || modeName || 'TEXT').toUpperCase();
    });
  }

  function updateCursorDisplay() {
    if (!cmEditor || !cursorPos) return;
    var cursor = cmEditor.getCursor();
    cursorPos.textContent = 'Ln ' + (cursor.line + 1) + ', Col ' + (cursor.ch + 1);
  }

  function scheduleAutoSave() {
    if (autoSaveTimer) clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(function() {
      if (isDirty && window.currentFile) {
        saveFile(true);
      }
    }, 2000);
  }

  function saveFile(silent) {
    if (!cmEditor || !window.currentEncoded) {
      if (!silent) showToast('No file open to save', true);
      return false;
    }
    
    var content = getEditorContent();
    if (content === lastSavedContent) {
      if (!silent) showToast('No changes to save');
      return true;
    }
    
    if (editorStatus) editorStatus.textContent = 'Saving...';
    
    var formData = new FormData();
    formData.append('encoded', window.currentEncoded);
    formData.append('content', content);
    
    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (csrfToken) formData.append('csrf_token', csrfToken.content);
    
    var baseUrl = window.location.pathname.startsWith('/editor') ? '/editor' : '/playground/editor';
    
    fetch(baseUrl + '/save', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
      if (data.success) {
        isDirty = false;
        lastSavedContent = content;
        if (editorStatus) editorStatus.textContent = 'Saved';
        if (!silent) showToast('File saved');
      } else {
        if (editorStatus) editorStatus.textContent = 'Error';
        showToast(data.error || 'Save failed', true);
      }
    })
    .catch(function(err) {
      if (editorStatus) editorStatus.textContent = 'Error';
      showToast('Save failed: ' + err.message, true);
    });
    
    return true;
  }

  function syncTheme() {
    if (!cmEditor) return;
    var isDark = getCurrentTheme() === 'dark';
    cmEditor.setOption('theme', isDark ? 'dracula' : 'default');
  }

  // ============ INITIALIZATION ============
  function initMobileEditor() {
    // Find or create container
    var monacoContainer = document.getElementById('monaco-editor');
    if (!monacoContainer) {
      console.error('[MobileEditor] No #monaco-editor container found');
      return;
    }
    
    // Create CodeMirror container
    editorContainer = document.createElement('div');
    editorContainer.id = 'codemirror-editor';
    editorContainer.className = 'codemirror-editor-container';
    editorContainer.style.cssText = 'height:100%;width:100%;';
    
    // Replace Monaco container
    monacoContainer.style.display = 'none';
    monacoContainer.parentNode.insertBefore(editorContainer, monacoContainer);
    
    // Get status elements
    editorStatus = document.getElementById('editor-status');
    langDisplay = document.getElementById('lang-display');
    cursorPos = document.getElementById('cursor-pos');
    
    // Get initial content
    var textarea = document.getElementById('editor-content');
    var initialContent = textarea ? textarea.value : '';
    var initialLang = window.currentLanguage || getLanguageFromPath(window.currentFile || '');
    
    // Load core CodeMirror
    loadStylesheet(CM_BASE + '/codemirror.min.css');
    loadStylesheet(CM_BASE + '/addon/fold/foldgutter.min.css');
    loadStylesheet(CM_BASE + '/addon/dialog/dialog.min.css');
    loadStylesheet(CM_BASE + '/theme/dracula.min.css');
    
    loadScript(CM_BASE + '/codemirror.min.js', function() {
      // Load addons
      var addonsToLoad = [
        'addon/edit/closebrackets.min.js',
        'addon/edit/matchbrackets.min.js',
        'addon/edit/closetag.min.js',
        'addon/fold/foldcode.min.js',
        'addon/fold/foldgutter.min.js',
        'addon/fold/brace-fold.min.js',
        'addon/search/search.min.js',
        'addon/search/searchcursor.min.js',
        'addon/dialog/dialog.min.js',
        'addon/display/autorefresh.min.js'
      ];
      
      var addonsLoaded = 0;
      addonsToLoad.forEach(function(addon) {
        loadScript(CM_BASE + '/' + addon, function() {
          addonsLoaded++;
          if (addonsLoaded === addonsToLoad.length) {
            createEditor(initialContent, initialLang);
          }
        });
      });
    });
  }

  function createEditor(initialContent, initialLang) {
    var isDark = getCurrentTheme() === 'dark';
    
    // Create CodeMirror editor
    cmEditor = CodeMirror(editorContainer, {
      value: initialContent || '',
      mode: null, // Set after loading mode
      theme: isDark ? 'dracula' : 'default',
      lineNumbers: true,
      lineWrapping: true, // Important for mobile
      autoCloseBrackets: true,
      matchBrackets: true,
      foldGutter: true,
      gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
      tabSize: 4,
      indentUnit: 4,
      indentWithTabs: false,
      autoRefresh: true,
      viewportMargin: Infinity, // Render all lines for smoother scrolling
      inputStyle: 'contenteditable', // Better for mobile
      spellcheck: false,
      autocorrect: false,
      autocapitalize: false,
      extraKeys: {
        'Ctrl-S': function() { saveFile(false); },
        'Cmd-S': function() { saveFile(false); },
        'Ctrl-F': 'findPersistent',
        'Cmd-F': 'findPersistent'
      }
    });
    
    // Make editor fill container
    cmEditor.setSize('100%', '100%');
    
    // Set initial language mode
    if (initialLang && initialLang !== 'plaintext') {
      setEditorLanguage(initialLang);
    }
    
    // Track changes
    cmEditor.on('change', function() {
      isDirty = true;
      if (editorStatus) editorStatus.textContent = 'Modified';
      scheduleAutoSave();
    });
    
    // Update cursor position display
    cmEditor.on('cursorActivity', updateCursorDisplay);
    
    // Mark as ready
    isReady = true;
    lastSavedContent = initialContent;
    
    // Initial status
    if (editorStatus) editorStatus.textContent = 'Ready';
    updateCursorDisplay();
    
    // Theme sync
    var observer = new MutationObserver(function(mutations) {
      for (var i = 0; i < mutations.length; i++) {
        if (mutations[i].attributeName === 'class') {
          syncTheme();
        }
      }
    });
    observer.observe(document.documentElement, { attributes: true });
    
    // Apply theme immediately in case dark class was already set
    syncTheme();
    
    // Focus editor
    setTimeout(function() {
      cmEditor.refresh();
      cmEditor.focus();
    }, 100);
    
    console.log('[MobileEditor] CodeMirror initialized successfully');
  }

  // ============ PUBLIC API ============
  // Same interface as GintoEditor for compatibility
  window.GintoMobileEditor = {
    isReady: function() { return isReady; },
    isMobile: true,
    getEditor: function() { return cmEditor; },
    getContent: getEditorContent,
    setContent: setEditorContent,
    setLanguage: setEditorLanguage,
    getCurrentFile: function() { return window.currentFile; },
    getCurrentEncoded: function() { return window.currentEncoded; },
    showToast: showToast,
    save: saveFile,
    loadFile: function(encoded, path) {
      // Delegate to the main loadFile function
      if (window.loadFile) window.loadFile(encoded, path);
    },
    refresh: function() {
      if (cmEditor) cmEditor.refresh();
    }
  };

  // Override GintoEditor with mobile version
  window.GintoEditor = window.GintoMobileEditor;
  window.playgroundEditor = window.GintoMobileEditor;

  // Override the Monaco bootstrap function
  window.bootstrapMonaco = function() {
    console.log('[MobileEditor] Skipping Monaco bootstrap, using CodeMirror');
  };

  // Mark Monaco as loaded to prevent loader from trying to load it
  window.monacoReady = true;

  // ============ DOCUMENT READY ============
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMobileEditor);
  } else {
    initMobileEditor();
  }

})();
