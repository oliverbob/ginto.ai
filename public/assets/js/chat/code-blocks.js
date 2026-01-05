/**
 * Chat Code Blocks Module
 * Code block enhancement with CodeMirror, sticky buttons, and copy functionality
 */

// ============ LANGUAGE DETECTION ============

/**
 * Detect programming language from code content or class
 */
export function detectLanguage(code, className) {
  if (className) {
    const match = className.match(/language-([a-zA-Z0-9]+)/);
    if (match) return match[1];
  }
  
  const c = code.trim();
  if (c.includes('<' + '?php') || (c.includes('function ') && c.includes('$'))) return 'php';
  if ((c.includes('import ') && c.includes('from ')) || c.includes('def ') || c.includes('if __name__ ==')) return 'python';
  if (c.includes('const ') || c.includes('let ') || c.includes('=>') || (c.includes('console.log(') && c.includes('{'))) return 'javascript';
  if (c.includes('<html') || c.includes('<!DOCTYPE') || (c.includes('<div') && c.includes('</div>'))) return 'html';
  if (c.includes('{') && c.includes(':') && !c.includes('function') && !c.includes('const ')) return 'css';
  if (c.includes('SELECT ') || c.includes('INSERT ') || c.includes('CREATE TABLE') || c.includes('UPDATE ') && c.includes('SET ')) return 'sql';
  if (c.includes('#include') || c.includes('int main(')) return 'c';
  if (c.includes('package ') && c.includes('func ')) return 'go';
  if (c.includes('fn ') && c.includes('let mut')) return 'rust';
  if (c.startsWith('{') && c.endsWith('}') || c.startsWith('[') && c.endsWith(']')) {
    try { JSON.parse(c); return 'json'; } catch (e) {}
  }
  return 'code';
}

/**
 * Get file extension for language
 */
export function getFileExtension(language) {
  const extensions = {
    'html': 'html', 'css': 'css', 'javascript': 'js', 'js': 'js',
    'python': 'py', 'php': 'php', 'sql': 'sql', 'json': 'json',
    'typescript': 'ts', 'jsx': 'jsx', 'tsx': 'tsx', 'xml': 'xml',
    'yaml': 'yml', 'markdown': 'md', 'bash': 'sh', 'shell': 'sh',
    'c': 'c', 'cpp': 'cpp', 'java': 'java', 'go': 'go', 'rust': 'rs',
    'ruby': 'rb', 'swift': 'swift', 'kotlin': 'kt', 'csharp': 'cs'
  };
  const langMatch = (language || '').match(/^([a-zA-Z0-9+#]+)/);
  const lang = langMatch ? langMatch[1].toLowerCase() : '';
  return extensions[lang] || 'txt';
}

/**
 * Map language names to CodeMirror modes
 */
export function getCodeMirrorMode(language) {
  const modeMap = {
    'javascript': 'javascript',
    'js': 'javascript',
    'typescript': 'text/typescript',
    'ts': 'text/typescript',
    'json': { name: 'javascript', json: true },
    'html': 'htmlmixed',
    'xml': 'xml',
    'css': 'css',
    'python': 'python',
    'py': 'python',
    'php': 'text/x-php',
    'sql': 'sql',
    'shell': 'shell',
    'bash': 'shell',
    'sh': 'shell',
    'markdown': 'markdown',
    'md': 'markdown',
    'c': 'text/x-csrc',
    'cpp': 'text/x-c++src',
    'c++': 'text/x-c++src',
    'java': 'text/x-java',
    'csharp': 'text/x-csharp',
    'cs': 'text/x-csharp',
    'go': 'text/x-go',
    'rust': 'text/x-rustsrc',
    'kotlin': 'text/x-kotlin',
    'swift': 'text/x-swift',
    'ruby': 'ruby',
    'rb': 'ruby',
    'perl': 'perl',
    'lua': 'lua',
    'r': 'r',
    'scala': 'text/x-scala',
    'groovy': 'text/x-groovy',
    'objective-c': 'text/x-objectivec',
    'objc': 'text/x-objectivec'
  };
  const langMatch = (language || '').match(/^([a-zA-Z0-9+#]+)/);
  const lang = langMatch ? langMatch[1].toLowerCase() : '';
  return modeMap[lang] || 'text/plain';
}

// ============ CODE BLOCK ATTRIBUTES ============

/**
 * Ensure code blocks in HTML have data-code and data-lang attributes for persistence
 */
export function ensureCodeBlockAttributes(html) {
  if (!html) return html;
  
  return html.replace(/<pre(?![^>]*data-code-b64)([^>]*)><code([^>]*)>([\s\S]*?)<\/code><\/pre>/gi, 
    function(match, preAttrs, codeAttrs, codeContent) {
      const rawCode = codeContent
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&amp;/g, '&')
        .replace(/&quot;/g, '"')
        .replace(/&#39;/g, "'");
      
      let language = 'text';
      const langMatch = codeAttrs.match(/class="[^"]*language-([a-zA-Z0-9]+)/);
      if (langMatch) {
        language = langMatch[1].toLowerCase();
      }
      
      try {
        const encodedCode = btoa(unescape(encodeURIComponent(rawCode)));
        return `<pre${preAttrs} data-code-b64="${encodedCode}" data-lang="${language}"><code${codeAttrs}>${codeContent}</code></pre>`;
      } catch (e) {
        console.error('[ensureCodeBlockAttributes] Failed to encode:', e);
        return match;
      }
    }
  );
}

// ============ CODE BLOCK ENHANCEMENT ============

/**
 * Enhance code blocks with header buttons and CodeMirror
 */
export function enhanceCodeBlocks(container, copyToClipboard) {
  // Import copyToClipboard if not provided
  if (!copyToClipboard) {
    copyToClipboard = async (text) => {
      try {
        await navigator.clipboard.writeText(text);
        return true;
      } catch (e) {
        return false;
      }
    };
  }

  // Initialize CodeMirror on existing wrappers
  if (typeof CodeMirror !== 'undefined') {
    const existingWrappers = container.querySelectorAll('.code-block-wrapper');
    existingWrappers.forEach((wrapper, widx) => {
      if (wrapper.cmInstance) return;
      
      const codeContent = wrapper.querySelector('.code-content');
      if (!codeContent) return;
      
      const staleCM = codeContent.querySelector('.CodeMirror');
      let textarea = codeContent.querySelector('textarea');
      
      let codeFromData = null;
      if (wrapper.dataset.codeB64) {
        try {
          codeFromData = decodeURIComponent(escape(atob(wrapper.dataset.codeB64)));
        } catch (e) {
          console.error('[enhanceCodeBlocks] Failed to decode Base64:', e);
        }
      }
      if (!codeFromData && wrapper.dataset.code) {
        codeFromData = wrapper.dataset.code;
      }
      
      const rawLangFromData = wrapper.dataset.lang || 'text';
      const langMatch = rawLangFromData.match(/^([a-zA-Z0-9+#]+)/);
      const langFromData = langMatch ? langMatch[1].toLowerCase() : 'text';
      
      if (!codeFromData && staleCM) {
        const lines = staleCM.querySelectorAll('.CodeMirror-line');
        if (lines.length > 0) {
          const lineTexts = [];
          lines.forEach(line => lineTexts.push(line.textContent || ''));
          codeFromData = lineTexts.join('\n');
        }
      }
      
      if (!codeFromData && textarea) {
        codeFromData = textarea.value || textarea.textContent || '';
      }
      
      if (!codeFromData || !codeFromData.trim()) return;
      
      if (staleCM) staleCM.remove();
      
      if (!textarea) {
        textarea = document.createElement('textarea');
        textarea.style.display = 'block';
        textarea.style.width = '100%';
        textarea.style.minHeight = '100px';
        textarea.style.background = '#1e1e1e';
        textarea.style.color = '#d4d4d4';
        textarea.style.border = 'none';
        textarea.style.padding = '1rem';
        textarea.style.fontFamily = 'monospace';
        textarea.style.fontSize = '0.875rem';
        textarea.readOnly = true;
        codeContent.appendChild(textarea);
      } else {
        Object.assign(textarea.style, {
          display: 'block',
          width: '100%',
          minHeight: '100px',
          background: '#1e1e1e',
          color: '#d4d4d4',
          border: 'none',
          padding: '1rem',
          fontFamily: 'monospace',
          fontSize: '0.875rem'
        });
        textarea.readOnly = true;
      }
      
      textarea.value = codeFromData;
      textarea.textContent = codeFromData;
      
      try {
        wrapper.dataset.codeB64 = btoa(unescape(encodeURIComponent(codeFromData)));
      } catch (e) {
        wrapper.dataset.code = codeFromData;
      }
      
      let lang = langFromData;
      if (lang === 'text') {
        const header = wrapper.querySelector('.code-block-header span');
        if (header) {
          let langText = '';
          header.childNodes.forEach(node => {
            if (node.nodeType === Node.TEXT_NODE) {
              langText += node.textContent || '';
            }
          });
          lang = langText.trim().toLowerCase() || 'text';
        }
      }
      wrapper.dataset.lang = lang;
      
      try {
        const cm = CodeMirror.fromTextArea(textarea, {
          mode: getCodeMirrorMode(lang),
          theme: 'material-darker',
          lineNumbers: true,
          readOnly: true,
          lineWrapping: false,
          viewportMargin: Infinity,
          cursorBlinkRate: 530,
          inputStyle: 'contenteditable'
        });
        wrapper.cmInstance = cm;
      } catch (e) {
        console.error('[enhanceCodeBlocks] CodeMirror init failed:', e);
      }
    });
  }
  
  // Process pre elements
  const preElements = container.querySelectorAll('pre');
  preElements.forEach((pre, idx) => {
    const existingWrapper = pre.closest('.code-block-wrapper');
    if (existingWrapper && existingWrapper.querySelector('.code-block-header')) {
      pre.dataset.enhanced = 'true';
      return;
    }
    
    if (pre.dataset.enhanced) return;
    pre.dataset.enhanced = 'true';
    
    let oldWrapper = null;
    if (existingWrapper && existingWrapper.querySelector('.copy-code-btn') && !existingWrapper.querySelector('.code-block-header')) {
      oldWrapper = existingWrapper;
    }
    
    const code = pre.querySelector('code') || pre;
    
    let codeText = '';
    const rawLang = pre.dataset.lang || '';
    const langMatch = rawLang.match(/^([a-zA-Z0-9+#]+)/);
    let language = langMatch ? langMatch[1].toLowerCase() : '';
    
    if (pre.dataset.codeB64) {
      try {
        codeText = decodeURIComponent(escape(atob(pre.dataset.codeB64)));
      } catch (e) {
        codeText = '';
      }
    } else if (pre.dataset.code) {
      codeText = pre.dataset.code
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&amp;/g, '&')
        .replace(/\\\\/g, '\\');
    }
    
    if (!codeText) {
      codeText = (code.textContent || '').trim();
    }
    
    if (!language) {
      language = detectLanguage(codeText, code.className);
    }
    
    const isHtml = language === 'html' || codeText.includes('<html') || codeText.includes('<!DOCTYPE') || (codeText.includes('<') && codeText.includes('</'));
    
    const wrapper = document.createElement('div');
    wrapper.className = 'code-block-wrapper';
    try {
      wrapper.dataset.codeB64 = btoa(unescape(encodeURIComponent(codeText)));
    } catch (e) {}
    wrapper.dataset.lang = language;
    
    const header = document.createElement('div');
    header.className = 'code-block-header';
    
    const langLabel = document.createElement('span');
    langLabel.innerHTML = `<svg style="display:inline;width:0.875rem;height:0.875rem;margin-right:0.25rem;vertical-align:-2px" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>${language}`;
    
    const codeContent = document.createElement('div');
    codeContent.className = 'code-content';
    
    const textarea = document.createElement('textarea');
    textarea.textContent = codeText;
    textarea.value = codeText;
    Object.assign(textarea.style, {
      display: 'block',
      width: '100%',
      minHeight: '100px',
      background: '#1e1e1e',
      color: '#d4d4d4',
      border: 'none',
      padding: '1rem',
      fontFamily: 'monospace',
      fontSize: '0.875rem'
    });
    textarea.readOnly = true;
    codeContent.appendChild(textarea);
    
    const buttonsDiv = document.createElement('div');
    buttonsDiv.className = 'code-header-buttons';
    
    let previewFrame = null;
    
    if (isHtml) {
      previewFrame = document.createElement('iframe');
      previewFrame.className = 'code-preview-iframe';
      previewFrame.style.display = 'none';
      
      const codeBtn = document.createElement('button');
      codeBtn.className = 'code-action-btn code-view-btn active';
      codeBtn.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>Code`;
      codeBtn.title = 'View code';
      
      const previewBtn = document.createElement('button');
      previewBtn.className = 'code-action-btn preview-view-btn';
      previewBtn.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>Preview`;
      previewBtn.title = 'Preview rendered HTML';
      
      codeBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        codeBtn.classList.add('active');
        previewBtn.classList.remove('active');
        codeContent.style.display = 'block';
        previewFrame.style.display = 'none';
      });
      
      previewBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        previewBtn.classList.add('active');
        codeBtn.classList.remove('active');
        codeContent.style.display = 'none';
        previewFrame.style.display = 'block';
        if (!previewFrame.dataset.loaded) {
          previewFrame.srcdoc = codeText;
          previewFrame.dataset.loaded = 'true';
        }
      });
      
      buttonsDiv.insertBefore(previewBtn, buttonsDiv.firstChild);
      buttonsDiv.insertBefore(codeBtn, buttonsDiv.firstChild);
    }
    
    // Save button
    const saveBtn = document.createElement('button');
    saveBtn.className = 'code-action-btn save-btn';
    saveBtn.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>Save`;
    saveBtn.title = 'Download code as file';
    
    saveBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const ext = getFileExtension(language);
      const blob = new Blob([codeText], { type: 'text/plain' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `code-${Date.now()}.${ext}`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
      
      const originalHTML = saveBtn.innerHTML;
      saveBtn.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color: #3fb950;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Saved!`;
      saveBtn.style.color = '#3fb950';
      
      setTimeout(() => {
        saveBtn.innerHTML = originalHTML;
        saveBtn.style.color = '';
      }, 2000);
    });
    buttonsDiv.appendChild(saveBtn);
    
    // Copy button
    const copyBtn = document.createElement('button');
    copyBtn.className = 'code-action-btn';
    copyBtn.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>Copy`;
    copyBtn.title = 'Copy code to clipboard';
    
    copyBtn.addEventListener('click', async (e) => {
      e.stopPropagation();
      e.preventDefault();
      
      let textToCopy = '';
      const parentWrapper = copyBtn.closest('.code-block-wrapper');
      if (parentWrapper) {
        if (parentWrapper.cmInstance) {
          textToCopy = parentWrapper.cmInstance.getValue();
        } else if (parentWrapper.dataset.codeB64) {
          try {
            textToCopy = decodeURIComponent(escape(atob(parentWrapper.dataset.codeB64)));
          } catch (err) {}
        } else if (parentWrapper.dataset.code) {
          textToCopy = parentWrapper.dataset.code;
        } else {
          const ta = parentWrapper.querySelector('textarea');
          if (ta) textToCopy = ta.value || ta.textContent || '';
        }
      }
      
      if (!textToCopy) textToCopy = codeText;
      
      try {
        const success = await copyToClipboard(textToCopy);
        if (success) {
          const originalHTML = copyBtn.innerHTML;
          copyBtn.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color: #3fb950;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Copied!`;
          copyBtn.style.color = '#3fb950';
          
          setTimeout(() => {
            copyBtn.innerHTML = originalHTML;
            copyBtn.style.color = '';
          }, 2000);
        }
      } catch (err) {
        console.error('Copy failed:', err);
      }
    });
    buttonsDiv.appendChild(copyBtn);
    
    header.appendChild(langLabel);
    header.appendChild(buttonsDiv);
    
    if (oldWrapper) {
      oldWrapper.parentNode.insertBefore(wrapper, oldWrapper);
      oldWrapper.remove();
    } else {
      pre.parentNode.insertBefore(wrapper, pre);
      pre.remove();
    }
    
    wrapper.appendChild(header);
    wrapper.appendChild(codeContent);
    
    if (previewFrame) {
      wrapper.appendChild(previewFrame);
    }
    
    // Initialize CodeMirror
    if (typeof CodeMirror !== 'undefined') {
      const mode = getCodeMirrorMode(language);
      try {
        const cm = CodeMirror.fromTextArea(textarea, {
          mode: mode,
          theme: 'material-darker',
          lineNumbers: true,
          readOnly: true,
          lineWrapping: false,
          viewportMargin: Infinity,
          cursorBlinkRate: 530,
          inputStyle: 'contenteditable'
        });
        setTimeout(() => cm.refresh(), 100);
        wrapper.cmInstance = cm;
      } catch (e) {
        console.error('[enhanceCodeBlocks] CodeMirror init failed:', e);
      }
    }
  });
}

// ============ STICKY BUTTONS ============

let stickyButtonsInitialized = false;
let stickyTicking = false;

/**
 * Update sticky buttons position based on scroll
 */
export function updateStickyButtons() {
  const mainHeader = document.getElementById('main-header');
  const mobileHeader = document.getElementById('mobile-header');
  
  let stickyTop = 56;
  if (mainHeader && getComputedStyle(mainHeader).display !== 'none') {
    stickyTop = mainHeader.offsetHeight || 56;
  } else if (mobileHeader && getComputedStyle(mobileHeader).display !== 'none') {
    stickyTop = mobileHeader.offsetHeight || 48;
  }
  
  const codeBlocks = document.querySelectorAll('.code-block-wrapper');
  
  codeBlocks.forEach((wrapper) => {
    const buttonsContainer = wrapper.querySelector('.code-header-buttons');
    if (!buttonsContainer) return;
    
    const header = wrapper.querySelector('.code-block-header');
    if (!header) return;
    
    const buttons = buttonsContainer.querySelectorAll('.code-action-btn');
    if (!buttons.length) return;
    
    const wrapperRect = wrapper.getBoundingClientRect();
    const headerRect = header.getBoundingClientRect();
    
    const headerPastTop = headerRect.top < stickyTop;
    const lineHeight = 24;
    const wrapperBottomVisible = wrapperRect.bottom > (stickyTop + lineHeight);
    
    if (headerPastTop && wrapperBottomVisible) {
      buttons.forEach((btn) => {
        if (!btn.classList.contains('stuck')) {
          const rect = btn.getBoundingClientRect();
          btn.dataset.originalLeft = rect.left;
          btn.classList.add('stuck');
          btn.style.left = rect.left + 'px';
        }
      });
    } else {
      buttons.forEach((btn) => {
        if (btn.classList.contains('stuck')) {
          btn.classList.remove('stuck');
          btn.style.left = '';
          delete btn.dataset.originalLeft;
        }
      });
    }
  });
  
  stickyTicking = false;
}

/**
 * Request sticky update on next animation frame
 */
export function requestStickyUpdate() {
  if (!stickyTicking) {
    requestAnimationFrame(updateStickyButtons);
    stickyTicking = true;
  }
}

/**
 * Initialize sticky code buttons
 */
export function initStickyCodeButtons() {
  if (!stickyButtonsInitialized) {
    window.addEventListener('scroll', requestStickyUpdate, { passive: true });
    window.addEventListener('resize', requestStickyUpdate, { passive: true });
    stickyButtonsInitialized = true;
  }
  
  updateStickyButtons();
}
