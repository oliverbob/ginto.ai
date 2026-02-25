// Minimal streaming client for POST /chat
(() => {
  const sendBtn = document.getElementById('send');
  const clearBtn = document.getElementById('clear');
  const resetHistoryBtn = document.getElementById('reset_history');
  const promptEl = document.getElementById('prompt');
  const messagesEl = document.getElementById('messages');
  
  // Attachment elements
  const attachBtn = document.getElementById('attach-btn');
  const attachInput = document.getElementById('attach-input');
  const attachPreview = document.getElementById('attach-preview');
  const attachPreviewImg = document.getElementById('attach-preview-img');
  const attachFilename = document.getElementById('attach-filename');
  const attachRemove = document.getElementById('attach-remove');
  
  // Current attached image (base64 data URL or server URL)
  let currentAttachment = null;
  
  // Upload image to server and return URL (for persistence across reloads)
  async function uploadImageToServer(base64DataUrl) {
    try {
      const csrfToken = window.CSRF_TOKEN || document.getElementById('csrf_token')?.value || '';
      const formData = new URLSearchParams();
      formData.append('image', base64DataUrl);
      formData.append('csrf_token', csrfToken);
      
      const response = await fetch('/chat/upload-image', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-CSRF-Token': csrfToken
        },
        body: formData.toString()
      });
      
      if (!response.ok) {
        console.warn('[uploadImageToServer] Upload failed:', response.status);
        return null;
      }
      
      const data = await response.json();
      if (data.success && data.url) {
        console.log('[uploadImageToServer] Image uploaded:', data.url);
        return data.url;
      }
      return null;
    } catch (err) {
      console.warn('[uploadImageToServer] Error:', err);
      return null;
    }
  }
  
  // Auto-scroll state: only scroll if user hasn't scrolled up
  let userHasScrolledUp = false;
  let lastScrollTop = 0;
  const SCROLL_THRESHOLD = 200; // pixels from bottom to consider "at bottom"
  
  // Get composer height dynamically
  function getComposerHeight() {
    const composer = document.querySelector('.composer-container') || document.querySelector('#chat-form')?.closest('div');
    if (composer) {
      return composer.offsetHeight + 20; // Add some padding
    }
    return 140; // fallback
  }
  
  // Get scroll container (document for full page scroll)
  function getScrollInfo() {
    const scrollTop = window.scrollY || document.documentElement.scrollTop;
    const windowHeight = window.innerHeight;
    const docHeight = document.documentElement.scrollHeight;
    const distanceFromBottom = docHeight - scrollTop - windowHeight;
    return { scrollTop, windowHeight, docHeight, distanceFromBottom };
  }
  
  // Check if we should auto-scroll (user hasn't scrolled up)
  function shouldAutoScroll() {
    if (userHasScrolledUp) return false;
    return true;
  }
  
  // Scroll to keep content visible above composer
  function scrollToBottom() {
    if (!shouldAutoScroll()) return;
    const composerHeight = getComposerHeight();
    // Use requestAnimationFrame to avoid fighting with browser
    requestAnimationFrame(() => {
      // Scroll so the bottom of content is visible above the composer
      const targetScroll = document.documentElement.scrollHeight - window.innerHeight + composerHeight;
      window.scrollTo({
        top: targetScroll,
        behavior: 'auto' // Use 'auto' to avoid smooth scroll fighting
      });
    });
  }
  
  // Smart scroll that respects user position and composer visibility
  function smartScrollToElement(element, options = { behavior: 'smooth', block: 'end' }) {
    if (!shouldAutoScroll() || !element) return;
    
    const composerHeight = getComposerHeight();
    const rect = element.getBoundingClientRect();
    const viewportHeight = window.innerHeight;
    
    // Check if bottom of element is behind the composer
    const visibleBottom = viewportHeight - composerHeight;
    if (rect.bottom > visibleBottom) {
      // Element is partially or fully behind composer - scroll to show it
      const scrollAmount = rect.bottom - visibleBottom + 20; // 20px buffer
      window.scrollBy({
        top: scrollAmount,
        behavior: 'auto'
      });
    }
  }
  
  // Helper function to copy text to clipboard with fallback for non-HTTPS
  async function copyToClipboard(text) {
    try {
      // Try modern clipboard API first
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
      
      if (success) {
        return true;
      }
    } catch (fallbackErr) {
      console.error('Fallback copy also failed:', fallbackErr);
    }
    
    return false;
  }
  
  // Track when user scrolls during streaming
  let isStreaming = false;
  let scrollCheckTimeout = null;
  
  window.addEventListener('scroll', () => {
    if (!isStreaming) return;
    
    // Any scroll during streaming stops auto-scroll
    // It will resume when user sends a new prompt
    userHasScrolledUp = true;
  }, { passive: true });

  // Global function to copy code blocks
  window.copyCodeBlock = async function(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const text = el.textContent || el.innerText;
    const success = await copyToClipboard(text);
    if (success) {
      // Find the button and update it temporarily
      const btn = el.parentElement?.querySelector('.copy-code-btn');
      if (btn) {
        const original = btn.textContent;
        btn.textContent = '✓ Copied!';
        setTimeout(() => { btn.textContent = original; }, 2000);
      }
    }
  };

  // Admin console toggle wiring
  document.addEventListener('DOMContentLoaded', function() {
    try {
      const toggle = document.getElementById('admin-console-toggle');
      const overlay = document.getElementById('admin-console-overlay');
      const closeBtn = document.getElementById('admin-console-close');
      if (toggle) {
        toggle.classList.remove('hidden');
        toggle.addEventListener('click', function() {
          if (!overlay) return;
          if (overlay.classList.contains('hidden')) overlay.classList.remove('hidden'); else overlay.classList.add('hidden');
        });
      }
      if (closeBtn && overlay) {
        closeBtn.addEventListener('click', function() {
          overlay.classList.add('hidden');
        });
      }
    } catch (e) {}
  });

  // Helper: update admin console overlay when response data contains debug info
  function updateAdminConsole(data) {
    try {
      if (!window.GINTO_SETUP || !window.GINTO_SETUP.isAdmin) return;
      if (!data || typeof data !== 'object') return;
      const provider = data.provider || null;
      const model = data.model || null;
      let raw = data.raw_content || data.text || (data.html ? (data.html.replace(/<[^>]+>/g, '')) : '') || '';
      if (data.admin_log && data.message) {
        const ts = new Date().toLocaleTimeString();
        raw = `[${ts}] ${data.message}`;
      }
      if (!provider && !model && !raw) return;
      const provEl = document.getElementById('admin-console-provider');
      const modelEl = document.getElementById('admin-console-model');
      const rawEl = document.getElementById('admin-console-raw');
      const overlay = document.getElementById('admin-console-overlay');
      const toggleBtn = document.getElementById('admin-console-toggle');
      if (provEl) provEl.textContent = provider || provEl.textContent || '-';
      if (modelEl) modelEl.textContent = model || modelEl.textContent || '-';
      if (rawEl) {
        if (data.admin_log && raw) {
          const prev = rawEl.textContent || '';
          rawEl.textContent = prev ? `${prev}\n${raw}` : raw;
        } else {
          rawEl.textContent = raw || rawEl.textContent || '';
        }
      }
      if (toggleBtn) toggleBtn.classList.remove('hidden');
      if (overlay) overlay.classList.remove('hidden');
    } catch (e) { console.debug('updateAdminConsole failed', e); }
  }

// ------------------ Sandbox job poller for legacy chat.js ------------------
function __startSandboxJobPollerLegacy() {
  const seen = new WeakSet();
  async function pollEl(el) {
    if (!el || seen.has(el)) return;
    seen.add(el);
    const jobId = el.dataset.jobId;
    const pdfUrl = el.dataset.pdfUrl;
    const htmlUrl = el.dataset.htmlUrl;
    const filename = el.dataset.filename || 'document';
    if (!jobId) return;

    let attempts = 0;
    const maxAttempts = 120;
    const iv = setInterval(async () => {
      attempts++;
      try {
        const res = await fetch('/api/sandbox/job-status?job_id=' + encodeURIComponent(jobId), { credentials: 'same-origin' });
        if (!res.ok) return;
        const json = await res.json();
        if (!json.success) return;
        const status = json.status || json.state || '';
        if (status === 'completed' || status === 'done' || status === 'success') {
          el.innerHTML = `<a href="${escapeHtml(pdfUrl)}" target="_blank" download="${escapeHtml(filename)}">Download PDF</a>`;
          clearInterval(iv);
          return;
        }
        if (status === 'failed' || json.error) {
          el.innerHTML = `Conversion failed. <a href="${escapeHtml(htmlUrl)}" target="_blank">Open HTML</a>`;
          clearInterval(iv);
          return;
        }
      } catch (e) {
        // ignore transient errors
      }
      if (attempts >= maxAttempts) {
        clearInterval(iv);
        el.innerHTML = `Conversion taking too long. <a href="${escapeHtml(htmlUrl)}" target="_blank">Open HTML</a>`;
      }
    }, 2000);
  }

  const mo = new MutationObserver((records) => {
    for (const r of records) {
      for (const node of r.addedNodes) {
        if (!node.querySelectorAll) continue;
        const els = node.matches && node.matches('.sandbox-doc-result') ? [node] : Array.from(node.querySelectorAll('.sandbox-doc-result'));
        for (const el of els) pollEl(el);
      }
    }
  });

  // Autosize composer textarea so it's short when empty and grows with content
  function autoResizeComposer() {
    try {
      if (!promptEl) return;
      // Reset to auto so scrollHeight reflects content
      promptEl.style.height = 'auto';
      // Prefer the computed max-height from the composer itself (handles calc() values)
      const promptStyles = window.getComputedStyle(promptEl);
      const capValPrompt = promptStyles.getPropertyValue('max-height') || '';
      let cap = parseInt(capValPrompt, 10);
      if (!cap || isNaN(cap)) {
        // Fallback: try the root CSS token (may be a calc() string)
        const docStyles = window.getComputedStyle(document.documentElement);
        const rootCap = docStyles.getPropertyValue('--assistant-composer-maxheight') || '';
        cap = parseInt(rootCap, 10);
      }
      // Final fallback: use a smaller sensible cap so the composer starts short
      if (!cap || isNaN(cap)) cap = Math.max(72, Math.round(window.innerHeight * 0.18));
      // Compute target height (with small padding)
      const target = Math.min(promptEl.scrollHeight, cap);
      promptEl.style.height = (target) + 'px';
      // Toggle scrolling when content exceeds cap
      if (promptEl.scrollHeight > cap) {
        promptEl.style.overflowY = 'auto';
      } else {
        promptEl.style.overflowY = 'hidden';
      }
    } catch (e) { console.debug('autoResizeComposer error', e); }
  }

  // Hook autosize to input and window resize
  try {
      if (promptEl) {
      // Initial sizing (multiple calls to catch late layout passes / iframe loads)
      setTimeout(autoResizeComposer, 60);
      setTimeout(autoResizeComposer, 300);
      setTimeout(autoResizeComposer, 1000);
      promptEl.addEventListener('input', autoResizeComposer, { passive: true });
      window.addEventListener('resize', autoResizeComposer, { passive: true });
    }
  } catch (e) { console.debug('autosize init failed', e); }

  mo.observe(document.body, { childList: true, subtree: true });
  // Prime existing
  Array.from(document.querySelectorAll('.sandbox-doc-result')).forEach(el => pollEl(el));
}

try { __startSandboxJobPollerLegacy(); } catch (e) { console.warn('legacy sandbox poller failed', e); }

  // ============ MARKDOWN RENDERER MODULE ============
  // ============ STANDARD LLM RESPONSE RENDERER ============
  // Uses marked.js + highlight.js + KaTeX (industry standard)
  
  // Configure marked.js with highlight.js for code blocks
  function initMarkdownRenderer() {
    if (typeof marked === 'undefined') {
      console.warn('[MarkdownRenderer] marked.js not loaded');
      return false;
    }
    
    // Configure marked with highlight.js for code syntax highlighting
    marked.setOptions({
      breaks: true,     // Convert \n to <br> in paragraphs
      gfm: true,        // GitHub Flavored Markdown (tables, strikethrough, etc.)
      highlight: function(code, lang) {
        if (typeof hljs !== 'undefined') {
          // Store raw code in base64 for persistence and copy functionality
          let encodedCode = '';
          try {
            encodedCode = btoa(unescape(encodeURIComponent(code)));
          } catch (e) {}
          
          const safeLang = (lang || '').replace(/[^a-zA-Z0-9+#-]/g, '');
          
          // Use highlight.js for syntax highlighting
          if (safeLang && hljs.getLanguage(safeLang)) {
            try {
              const highlighted = hljs.highlight(code, { language: safeLang }).value;
              return highlighted;
            } catch (e) {}
          }
          // Auto-detect language
          try {
            return hljs.highlightAuto(code).value;
          } catch (e) {}
        }
        // Fallback: just escape
        return code.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
      }
    });
    
    return true;
  }
  
  // Main function: Convert markdown to HTML with LaTeX support
  function simpleMarkdownToHtml(markdown) {
    if (!markdown) return '';
    
    // Preprocess: fix PHP code blocks
    markdown = fixPhpCodeBlocks(markdown);
    
    // NOTE: Removed all "fix malformed markdown" regexes - they were breaking properly-formatted 
    // markdown. Models like gpt-oss-120b output correct markdown, and these "fixes" corrupted it.
    // The problematic patterns were:
    // - /\*\s+\*/g - turned "* **Bold" into "***Bold" (corrupted bullet+bold)
    // - /\*\*\s+\*\*/g - similar issue
    // - /\*{4,}/g - tried to fix the above but didn't work for 3 asterisks
    
    // Preprocess: fix headings without space after # (LLM often outputs ###1. instead of ### 1.)
    markdown = markdown.replace(/^(#{1,6})([^\s#])/gm, '$1 $2');
    
    // Preprocess: fix inline list items - add newlines before "- **" patterns that appear inline
    // LLMs sometimes output: "text - **Item1:** value - **Item2:** value" instead of proper lists
    markdown = markdown.replace(/([^\n])\s+-\s+\*\*/g, '$1\n\n- **');
    
    // Preprocess: fix inline list items without bold - add newlines before standalone " - " followed by capital letter
    markdown = markdown.replace(/([^\n])\s+-\s+([A-Z])/g, '$1\n\n- $2');
    
    // Preprocess: fix inline bullet patterns like "text.- Next" (period followed by dash)
    markdown = markdown.replace(/\.(\s*)-\s+/g, '.\n\n- ');
    
    // Preprocess: ensure double newlines before headers for proper paragraph breaks
    markdown = markdown.replace(/([^\n])\n(#{1,6}\s)/g, '$1\n\n$2');
    
    // Preprocess: fix markdown tables that have rows concatenated without proper newlines
    // Pattern: | ... | followed immediately by | (another row start) - inject newline
    // e.g., "| Header |Word || Data1 | Data2 |" -> "| Header |Word |\n| Data1 | Data2 |"
    markdown = markdown.replace(/(\|[^|\n]*\|)(\s*)(\|)/g, function(match, row1, space, pipe2) {
      // If there's no newline between the end of one row and start of next, add one
      if (!space.includes('\n')) {
        return row1 + '\n' + pipe2;
      }
      return match;
    });
    
    // Preprocess: protect and normalize LaTeX delimiters
    markdown = fixLatexDelimiters(markdown);
    
    // CRITICAL: Protect math blocks from marked.js processing
    // marked.js escapes backslashes which breaks LaTeX commands
    const mathBlocks = [];
    
    // Protect display math $$ ... $$ - ensure it's on its own line for block display
    // Match $$ with optional surrounding whitespace/newlines
    markdown = markdown.replace(/(\n*)\$\$([\s\S]*?)\$\$(\n*)/g, function(match, before, content, after) {
      const placeholder = '\u0002MATH' + mathBlocks.length + 'MATH\u0003';
      mathBlocks.push({ type: 'display', content: content });
      // Ensure paragraph breaks around display math
      return '\n\n' + placeholder + '\n\n';
    });
    
    // Protect inline math $ ... $ (but not $$ which is already protected)
    // IMPORTANT: Skip currency patterns like $100, $1.5M, $436 billion
    // Real LaTeX typically has backslash commands or operators: $x^2$, $\frac{1}{2}$, $a + b$
    markdown = markdown.replace(/\$([^\$\n]+?)\$/g, function(match, content) {
      // Skip if content looks like currency (starts with number or is just numbers with optional suffix)
      if (/^\d/.test(content.trim()) || /^[\d,.\s]+(?:billion|million|trillion|k|m|b)?$/i.test(content.trim())) {
        return match; // Keep as-is, it's currency not math
      }
      // Only protect if it looks like actual LaTeX (has operators, backslash, or math symbols)
      if (/[\\^_{}+\-*/=<>]|\\[a-z]+/i.test(content)) {
        const placeholder = '\u0002MATH' + mathBlocks.length + 'MATH\u0003';
        mathBlocks.push({ type: 'inline', content: content });
        return placeholder;
      }
      return match; // Not obviously math, keep as-is
    });
    
    // Initialize renderer
    initMarkdownRenderer();
    
    // Render markdown to HTML
    let html = '';
    if (typeof marked !== 'undefined') {
      try {
        html = marked.parse(markdown);
      } catch (e) {
        console.error('[MarkdownRenderer] marked.parse error:', e);
        html = fallbackMarkdownToHtml(markdown);
      }
    } else {
      console.warn('[MarkdownRenderer] marked.js NOT available, using fallback');
      html = fallbackMarkdownToHtml(markdown);
    }
    
    // Restore math blocks after marked.js processing
    for (let i = 0; i < mathBlocks.length; i++) {
      const block = mathBlocks[i];
      const placeholder = '\u0002MATH' + i + 'MATH\u0003';
      if (block.type === 'display') {
        html = html.replace(placeholder, '$$' + block.content + '$$');
      } else {
        html = html.replace(placeholder, '$' + block.content + '$');
      }
    }
    
    return html;
  }
  
  // Render KaTeX math in an element AFTER innerHTML is set
  function renderLatexInElement(element) {
    if (!element) return;
    if (typeof renderMathInElement === 'undefined' || typeof katex === 'undefined') {
      console.warn('[MarkdownRenderer] KaTeX not available - renderMathInElement:', typeof renderMathInElement, 'katex:', typeof katex);
      return;
    }
    
    try {
      renderMathInElement(element, {
        delimiters: [
          { left: '$$', right: '$$', display: true },
          // DISABLED: Single $ delimiters conflict with currency ($100, $1 trillion, etc.)
          // { left: '$', right: '$', display: false },
          { left: '\\[', right: '\\]', display: true },
          { left: '\\(', right: '\\)', display: false }
        ],
        throwOnError: false,
        errorColor: '#cc0000',
        ignoredTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code']
      });
    } catch (e) {
      console.warn('[MarkdownRenderer] KaTeX error:', e);
    }
  }
  
  // Fix PHP code blocks: add missing closing tag, fix formatting
  function fixPhpCodeBlocks(markdown) {
    if (!markdown) return '';
    
    // Fix ```php followed immediately by <?php (no newline)
    markdown = markdown.replace(/```(php|PHP)\s*(<\?php)/gi, '```php\n$2');
    
    // Fix <?php immediately followed by /* (block comment) - add newline
    markdown = markdown.replace(/<\?php(?=\/\*)/g, '<?php\n');
    
    // Fix <?php immediately followed by // (line comment) - add newline
    markdown = markdown.replace(/<\?php(?=\/\/)/g, '<?php\n');
    
    // Fix <?php immediately followed by code (no space/newline)
    markdown = markdown.replace(/<\?php(?=[^\s\/\n])/g, '<?php\n');
    
    // Process PHP code blocks
    markdown = markdown.replace(/```php\s*([\s\S]*?)```/gi, function(match, code) {
      let trimmedCode = code.trim();
      
      // Check if code starts with <?php
      if (trimmedCode.startsWith('<?php')) {
        // Add newline after <?php if immediately followed by content
        trimmedCode = trimmedCode.replace(/^<\?php(?=[^\s\n])/, '<?php\n');
        
        // Fix ?> on same line as code - add newline before it
        // Match ?> that's preceded by non-whitespace on the same line
        trimmedCode = trimmedCode.replace(/([^\n\s])\s*\?>(\s*)$/, '$1\n?>');
        
        // Add closing ?> if missing
        if (!trimmedCode.endsWith('?>')) {
          // Ensure there's a newline before ?>
          if (!trimmedCode.endsWith('\n')) {
            trimmedCode += '\n';
          }
          trimmedCode += '?>';
        }
        
        return '```php\n' + trimmedCode + '\n```';
      }
      return match;
    });
    
    return markdown;
  }
  
  // Fix LaTeX delimiters that models sometimes use incorrectly
  function fixLatexDelimiters(markdown) {
    if (!markdown) return '';
    
    // Step 1: Protect LaTeX line breaks with spacing in matrices: \\[2pt], \\[5mm], etc.
    // After JSON parsing/storage, these may appear as:
    //   - \\[2pt] (two backslashes - original)
    //   - \[2pt] (one backslash - after JSON parse)
    // We need to protect these from being treated as display math \[...\]
    const linebreakPlaceholders = [];
    
    // Pattern for spacing inside brackets: digits optionally followed by unit
    // Examples: [2pt], [5mm], [10ex], [], [3]
    const spacingPattern = /^\[\d*(?:pt|em|ex|mm|cm|in|sp|pc|bp|dd|cc|mu)?\]$/i;
    
    // First: match double backslash before bracket (original form)
    markdown = markdown.replace(/\\\\(\[[^\]]*?\])/g, function(match, bracket) {
      if (spacingPattern.test(bracket)) {
        const placeholder = '\u0000LB' + linebreakPlaceholders.length + '\u0000';
        linebreakPlaceholders.push('\\\\' + bracket); // Store as \\[2pt]
        return placeholder;
      }
      return match;
    });
    
    // Second: match single backslash before bracket (JSON-parsed form)
    // Use word boundary or start/look for non-backslash before
    markdown = markdown.replace(/([^\\])\\(\[[^\]]*?\])/g, function(match, prefix, bracket) {
      if (spacingPattern.test(bracket)) {
        const placeholder = '\u0000LB' + linebreakPlaceholders.length + '\u0000';
        linebreakPlaceholders.push('\\\\' + bracket); // Store as \\[2pt] for proper LaTeX
        return prefix + placeholder;
      }
      return match;
    });
    
    // Also handle at start of string
    markdown = markdown.replace(/^\\(\[[^\]]*?\])/g, function(match, bracket) {
      if (spacingPattern.test(bracket)) {
        const placeholder = '\u0000LB' + linebreakPlaceholders.length + '\u0000';
        linebreakPlaceholders.push('\\\\' + bracket);
        return placeholder;
      }
      return match;
    });
    
    // Step 2: Protect \left[ and \right] from being confused with \[ and \]
    const LEFTBRACKET = '\u0000LEFTBRACKET\u0000';
    const RIGHTBRACKET = '\u0000RIGHTBRACKET\u0000';
    markdown = markdown.replace(/\\left\[/g, LEFTBRACKET);
    markdown = markdown.replace(/\\right\]/g, RIGHTBRACKET);
    
    // Also protect \left( and \right) similarly
    const LEFTPAREN = '\u0000LEFTPAREN\u0000';
    const RIGHTPAREN = '\u0000RIGHTPAREN\u0000';
    markdown = markdown.replace(/\\left\(/g, LEFTPAREN);
    markdown = markdown.replace(/\\right\)/g, RIGHTPAREN);
    
    // Step 3: Handle properly closed \[ ... \] (standard LaTeX display math)
    // IMPORTANT: Keep $$ and content on SAME LINE for KaTeX to recognize
    markdown = markdown.replace(/\\\[\s*([\s\S]*?)\s*\\\]/g, function(match, content) {
      // Collapse newlines within content so it stays as single display math block
      const cleaned = content.trim().replace(/\n+/g, ' ');
      return '\n\n$$' + cleaned + '$$\n\n';
    });
    
    // Step 4: Handle UNCLOSED \[ - GPT-OSS sometimes outputs \[ without \]
    // Pattern: \[ followed by math content on SAME LINE (don't cross table cells or lines)
    // IMPORTANT: Only match within the same line to avoid corrupting tables
    markdown = markdown.replace(/\\\[\s*([^\n\|]+?)(?=\n|\||$)/g, function(match, content) {
      // Only if content looks like math (has LaTeX commands) and doesn't contain pipe (table separator)
      if (content && /\\[a-zA-Z]+/.test(content) && !content.includes('|')) {
        const cleaned = content.trim();
        return '$$' + cleaned + '$$';
      }
      return match;
    });
    
    // Step 5: Handle properly closed \( ... \) (standard LaTeX inline math)
    markdown = markdown.replace(/\\\(\s*([\s\S]*?)\s*\\\)/g, function(match, content) {
      const cleaned = content.trim().replace(/\n+/g, ' ');
      return '$' + cleaned + '$';
    });
    
    // Step 6: Restore protected delimiters
    // Since math blocks are now protected from marked.js, we don't need to double-escape
    for (let i = 0; i < linebreakPlaceholders.length; i++) {
      markdown = markdown.replace('\u0000LB' + i + '\u0000', linebreakPlaceholders[i]);
    }
    markdown = markdown.replace(new RegExp(LEFTBRACKET, 'g'), '\\left[');
    markdown = markdown.replace(new RegExp(RIGHTBRACKET, 'g'), '\\right]');
    markdown = markdown.replace(new RegExp(LEFTPAREN, 'g'), '\\left(');
    markdown = markdown.replace(new RegExp(RIGHTPAREN, 'g'), '\\right)');
    
    // Step 7: Fix any $$ blocks that got newlines inside them (rejoin)
    markdown = markdown.replace(/\$\$\s*\n+\s*([^\$]+?)\s*\n+\s*\$\$/g, function(match, content) {
      const cleaned = content.trim().replace(/\n+/g, ' ');
      return '$$' + cleaned + '$$';
    });
    
    return markdown;
  }
  
  // Fallback markdown renderer (basic regex-based)
  function fallbackMarkdownToHtml(md) {
    if (!md) return '';
    
    let content = md.trim();
    const lines = content.split('\n');
    const result = [];
    let inList = false;
    
    // Helper to process inline formatting
    function processInline(text) {
      // Bold: **text** or __text__
      text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
      text = text.replace(/__([^_]+)__/g, '<strong>$1</strong>');
      // Italic: *text* or _text_
      text = text.replace(/\*([^*]+)\*/g, '<em>$1</em>');
      text = text.replace(/_([^_]+)_/g, '<em>$1</em>');
      // Inline code: `text`
      text = text.replace(/`([^`]+)`/g, '<code>$1</code>');
      return text;
    }
    
    // Helper to parse markdown table
    function parseTable(tableLines) {
      if (tableLines.length < 2) return null;
      
      const rows = tableLines.map(line => {
        // Split by | and filter empty cells
        const cells = line.split('|').map(c => c.trim()).filter((c, i, arr) => i > 0 && i < arr.length);
        return cells;
      });
      
      // Check if second row is a separator row (|---|---|)
      const isSeparator = rows[1] && rows[1].every(cell => /^[-:]+$/.test(cell));
      if (!isSeparator) return null;
      
      let html = '<table class="markdown-table">';
      // Header row
      html += '<thead><tr>';
      rows[0].forEach(cell => {
        html += `<th>${processInline(cell.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'))}</th>`;
      });
      html += '</tr></thead>';
      
      // Body rows
      html += '<tbody>';
      for (let i = 2; i < rows.length; i++) {
        html += '<tr>';
        rows[i].forEach(cell => {
          html += `<td>${processInline(cell.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'))}</td>`;
        });
        html += '</tr>';
      }
      html += '</tbody></table>';
      return html;
    }
    
    // First pass: detect and parse tables
    let i = 0;
    let inTable = false;
    let tableLines = [];
    
    while (i < lines.length) {
      const line = lines[i];
      const isTableLine = line.trim().startsWith('|') && line.trim().endsWith('|');
      
      if (isTableLine) {
        if (!inTable) {
          inTable = true;
          tableLines = [];
        }
        tableLines.push(line);
      } else {
        if (inTable) {
          // End of table, parse it
          const tableHtml = parseTable(tableLines);
          if (tableHtml) {
            result.push(tableHtml);
          } else {
            // Not a valid table, treat as regular text
            tableLines.forEach(tl => {
              result.push(`<p>${processInline(tl.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'))}</p>`);
            });
          }
          inTable = false;
          tableLines = [];
        }
        
        // Process non-table line
        const escaped = line.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const processed = processInline(escaped);
        
        if (line.startsWith('### ')) {
          if (inList) { result.push('</ul>'); inList = false; }
          result.push(`<h3>${processInline(escaped.slice(4))}</h3>`);
        } else if (line.startsWith('## ')) {
          if (inList) { result.push('</ul>'); inList = false; }
          result.push(`<h2>${processInline(escaped.slice(3))}</h2>`);
        } else if (line.startsWith('# ')) {
          if (inList) { result.push('</ul>'); inList = false; }
          result.push(`<h1>${processInline(escaped.slice(2))}</h1>`);
        } else if (line.match(/^[\-\*]\s/)) {
          if (!inList) { result.push('<ul>'); inList = true; }
          result.push(`<li>${processInline(escaped.slice(2))}</li>`);
        } else if (escaped.trim() === '') {
          if (inList) { result.push('</ul>'); inList = false; }
        } else {
          if (inList) { result.push('</ul>'); inList = false; }
          result.push(`<p>${processed}</p>`);
        }
      }
      i++;
    }
    
    // Handle table at end of content
    if (inTable && tableLines.length > 0) {
      const tableHtml = parseTable(tableLines);
      if (tableHtml) {
        result.push(tableHtml);
      } else {
        tableLines.forEach(tl => {
          result.push(`<p>${processInline(tl.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'))}</p>`);
        });
      }
    }
    
    if (inList) result.push('</ul>');
    return result.join('\n');
  }

  function setBusy(b) {
    if (b) {
      // Show stop button (square icon) when streaming - clicking aborts
      sendBtn.disabled = false; // Keep enabled so user can click to stop
      sendBtn.classList.remove('bg-indigo-600', 'hover:bg-indigo-500');
      sendBtn.classList.add('bg-red-600', 'hover:bg-red-500');
      sendBtn.title = 'Stop generating';
      sendBtn.innerHTML = `<svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
        <rect x="6" y="6" width="12" height="12" rx="1"/>
      </svg>`;
      sendBtn._isStreaming = true;
    } else {
      // Paper plane icon (send)
      sendBtn.disabled = false;
      sendBtn.classList.remove('bg-red-600', 'hover:bg-red-500');
      sendBtn.classList.add('bg-indigo-600', 'hover:bg-indigo-500');
      sendBtn.title = 'Send message';
      sendBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 2L11 13"/>
        <path d="M22 2L15 22L11 13L2 9L22 2Z"/>
      </svg>`;
      sendBtn._isStreaming = false;
    }
  }

  // Detect programming language from code content or class
  function detectLanguage(code, className) {
    if (className) {
      const match = className.match(/language-([a-zA-Z0-9]+)/);
      if (match) return match[1];
    }
    // Simple heuristics
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

  // Get file extension for language
  function getFileExtension(language) {
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

  // Map language names to CodeMirror modes
  function getCodeMirrorMode(language) {
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

  // Ensure code blocks in HTML have data-code and data-lang attributes for persistence
  function ensureCodeBlockAttributes(html) {
    if (!html) return html;
    
    // Process HTML string directly with regex to avoid DOM parsing issues
    // DOM parsing corrupts <?php tags (treats them as XML processing instructions)
    
    // Match <pre> elements that don't already have data-code-b64
    return html.replace(/<pre(?![^>]*data-code-b64)([^>]*)><code([^>]*)>([\s\S]*?)<\/code><\/pre>/gi, 
      function(match, preAttrs, codeAttrs, codeContent) {
        // Decode HTML entities to get raw code
        const rawCode = codeContent
          .replace(/&lt;/g, '<')
          .replace(/&gt;/g, '>')
          .replace(/&amp;/g, '&')
          .replace(/&quot;/g, '"')
          .replace(/&#39;/g, "'");
        
        // Detect language from class
        let language = 'text';
        const langMatch = codeAttrs.match(/class="[^"]*language-([a-zA-Z0-9]+)/);
        if (langMatch) {
          language = langMatch[1].toLowerCase();
        }
        
        // Encode to Base64
        try {
          const encodedCode = btoa(unescape(encodeURIComponent(rawCode)));
          return `<pre${preAttrs} data-code-b64="${encodedCode}" data-lang="${language}"><code${codeAttrs}>${codeContent}</code></pre>`;
        } catch (e) {
          console.error('[ensureCodeBlockAttributes] Failed to encode:', e);
          return match; // Return original on error
        }
      }
    );
  }

  // Enhance code blocks with header buttons (Code, Preview, Save, Copy) and CodeMirror
  function enhanceCodeBlocks(container) {
    // First, initialize CodeMirror on any existing wrappers that don't have it yet
    if (typeof CodeMirror !== 'undefined') {
      const existingWrappers = container.querySelectorAll('.code-block-wrapper');
      console.log('[enhanceCodeBlocks] Found', existingWrappers.length, 'existing .code-block-wrapper elements');
      existingWrappers.forEach((wrapper, widx) => {
        // Skip if already has a live CodeMirror instance
        if (wrapper.cmInstance) {
          console.log('[enhanceCodeBlocks] Wrapper', widx, 'already has cmInstance, skipping');
          return;
        }
        
        const codeContent = wrapper.querySelector('.code-content');
        if (!codeContent) {
          console.log('[enhanceCodeBlocks] Wrapper', widx, 'has no .code-content, skipping');
          return;
        }
        
        // Check for stale CodeMirror HTML (saved from previous session)
        const staleCM = codeContent.querySelector('.CodeMirror');
        let textarea = codeContent.querySelector('textarea');
        
        // Get code from various sources (in order of reliability)
        // First try Base64 encoded (newer format)
        let codeFromData = null;
        if (wrapper.dataset.codeB64) {
          try {
            codeFromData = decodeURIComponent(escape(atob(wrapper.dataset.codeB64)));
            console.log('[enhanceCodeBlocks] Wrapper', widx, 'decoded from Base64, length:', codeFromData.length);
          } catch (e) {
            console.error('[enhanceCodeBlocks] Failed to decode Base64:', e);
          }
        }
        // Fall back to old data-code attribute
        if (!codeFromData && wrapper.dataset.code) {
          codeFromData = wrapper.dataset.code;
        }
        // Sanitize language from data attribute - extract only alphanumeric chars
        const rawLangFromData = wrapper.dataset.lang || 'text';
        const langFromDataMatch = rawLangFromData.match(/^([a-zA-Z0-9+#]+)/);
        const langFromData = langFromDataMatch ? langFromDataMatch[1].toLowerCase() : 'text';
        
        console.log('[enhanceCodeBlocks] Wrapper', widx, 'code length:', codeFromData?.length, 'has staleCM:', !!staleCM, 'has textarea:', !!textarea);
        
        // If no data-code, try to extract from stale CodeMirror lines
        if (!codeFromData && staleCM) {
          const lines = staleCM.querySelectorAll('.CodeMirror-line');
          if (lines.length > 0) {
            const lineTexts = [];
            lines.forEach(line => lineTexts.push(line.textContent || ''));
            codeFromData = lineTexts.join('\n');
            console.log('[enhanceCodeBlocks] Wrapper', widx, 'extracted code from staleCM, length:', codeFromData.length);
          }
        }
        
        // If still no code, try textarea content
        if (!codeFromData && textarea) {
          codeFromData = textarea.value || textarea.textContent || '';
          console.log('[enhanceCodeBlocks] Wrapper', widx, 'got code from textarea, length:', codeFromData.length);
        }
        
        // Skip if no code found
        if (!codeFromData || !codeFromData.trim()) {
          console.log('[enhanceCodeBlocks] Wrapper', widx, 'no code found, skipping');
          return;
        }
        
        if (staleCM) {
          // Remove stale CodeMirror HTML
          staleCM.remove();
        }
        
        // Ensure textarea exists with fallback styling
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
          // Show existing textarea with fallback styling
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
        }
        
        // Set the code content - use .value for CodeMirror.fromTextArea()
        textarea.value = codeFromData;
        textarea.textContent = codeFromData; // Also set textContent for HTML persistence
        
        // Store for future reloads (use Base64 to avoid HTML entity issues)
        try {
          wrapper.dataset.codeB64 = btoa(unescape(encodeURIComponent(codeFromData)));
        } catch (e) {
          console.error('[enhanceCodeBlocks] Failed to encode Base64:', e);
          wrapper.dataset.code = codeFromData; // fallback
        }
        
        // Detect language from header if not in data attribute
        let lang = langFromData;
        if (lang === 'text') {
          const header = wrapper.querySelector('.code-block-header span');
          if (header) {
            // Get only direct text nodes, not SVG content
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
        
        // Initialize CodeMirror
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
          console.error('[enhanceCodeBlocks] CodeMirror init failed for wrapper:', e);
        }
      });
    }
    
    const preElements = container.querySelectorAll('pre');
    console.log('[enhanceCodeBlocks] Found', preElements.length, 'pre elements');
    preElements.forEach((pre, idx) => {
      // Check if already inside an enhanced wrapper (has code-block-header)
      const existingWrapper = pre.closest('.code-block-wrapper');
      if (existingWrapper && existingWrapper.querySelector('.code-block-header')) {
        // Already has the new format, just mark as enhanced and skip
        pre.dataset.enhanced = 'true';
        console.log('[enhanceCodeBlocks] Pre', idx, 'already has wrapper, skipping');
        return;
      }
      
      // Skip if already enhanced (handles in-session duplicates)
      if (pre.dataset.enhanced) {
        console.log('[enhanceCodeBlocks] Pre', idx, 'already enhanced, skipping');
        return;
      }
      pre.dataset.enhanced = 'true';
      
      // If inside an old-format wrapper (has copy-code-btn but no code-block-header),
      // we need to replace it
      let oldWrapper = null;
      if (existingWrapper && existingWrapper.querySelector('.copy-code-btn') && !existingWrapper.querySelector('.code-block-header')) {
        oldWrapper = existingWrapper;
      }
      
      const code = pre.querySelector('code') || pre;
      
      // Check for Base64-encoded data attribute first (new format), then fallback to old format
      let codeText = '';
      // Sanitize language from data attribute - extract only alphanumeric chars
      const rawLang = pre.dataset.lang || '';
      const langMatch = rawLang.match(/^([a-zA-Z0-9+#]+)/);
      let language = langMatch ? langMatch[1].toLowerCase() : '';
      
      console.log('[enhanceCodeBlocks] Pre', idx, 'data-lang attr:', pre.getAttribute('data-lang'), 'sanitized lang:', language, 'dataset.codeB64:', !!pre.dataset.codeB64);
      
      if (pre.dataset.codeB64) {
        // Decode Base64
        try {
          codeText = decodeURIComponent(escape(atob(pre.dataset.codeB64)));
        } catch (e) {
          console.error('[enhanceCodeBlocks] Failed to decode Base64:', e);
          codeText = '';
        }
      } else if (pre.dataset.code) {
        // Old format: HTML-escaped (decode it)
        codeText = pre.dataset.code
          .replace(/&lt;/g, '<')
          .replace(/&gt;/g, '>')
          .replace(/&quot;/g, '"')
          .replace(/&amp;/g, '&')
          .replace(/\\\\/g, '\\');
      }
      
      // If no data attributes, fall back to extracting from DOM
      if (!codeText) {
        codeText = (code.textContent || '').trim();
      }
      
      // Fallback language detection if not in data attribute
      if (!language) {
        language = detectLanguage(codeText, code.className);
      }
      console.log('[enhanceCodeBlocks] Pre', idx, 'codeText length:', codeText.length, 'preview:', codeText.substring(0, 100), 'language:', language, 'from data:', !!pre.dataset.code);
      const isHtml = language === 'html' || codeText.includes('<html') || codeText.includes('<!DOCTYPE') || (codeText.includes('<') && codeText.includes('</'));
      
      // Create wrapper
      const wrapper = document.createElement('div');
      wrapper.className = 'code-block-wrapper';
      // Store code in Base64 for reload persistence (avoids HTML entity issues)
      try {
        wrapper.dataset.codeB64 = btoa(unescape(encodeURIComponent(codeText)));
      } catch (e) {
        console.error('[enhanceCodeBlocks] Failed to encode Base64:', e);
      }
      wrapper.dataset.lang = language;
      
      // Create header with language label + buttons (normal state)
      const header = document.createElement('div');
      header.className = 'code-block-header';
      
      const langLabel = document.createElement('span');
      langLabel.innerHTML = `<svg style="display:inline;width:0.875rem;height:0.875rem;margin-right:0.25rem;vertical-align:-2px" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>${language}`;
      
      // Build code content container for CodeMirror
      const codeContent = document.createElement('div');
      codeContent.className = 'code-content';
      
      // Create textarea for CodeMirror
      const textarea = document.createElement('textarea');
      textarea.textContent = codeText; // Use textContent so it persists in HTML
      textarea.value = codeText; // Also set value for CodeMirror.fromTextArea()
      // Don't hide textarea until CodeMirror is ready - CSS hides .code-content textarea by default
      // If CodeMirror fails to load, we'll show the textarea as fallback
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
      
      // Create buttons div (normally lives in the header; can be temporarily moved to a floating host)
      const buttonsDiv = document.createElement('div');
      buttonsDiv.className = 'code-header-buttons';
      
      // Preview iframe for HTML (hidden initially)
      let previewFrame = null;
      
      // View button for HTML - add as INDIVIDUAL buttons, no container
      if (isHtml) {
        previewFrame = document.createElement('iframe');
        previewFrame.className = 'code-preview-iframe';
        previewFrame.style.display = 'none';
        
        // Code button (active by default)
        const codeBtn = document.createElement('button');
        codeBtn.className = 'code-action-btn code-view-btn active';
        codeBtn.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>Code`;
        codeBtn.title = 'View code';
        
        // Preview button
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
        
        // Add as individual buttons at the start
        buttonsDiv.insertBefore(previewBtn, buttonsDiv.firstChild);
        buttonsDiv.insertBefore(codeBtn, buttonsDiv.firstChild);
      }
      
      // Save button with visual feedback
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
        
        // Visual feedback
        const originalHTML = saveBtn.innerHTML;
        saveBtn.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color: #3fb950;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Saved!`;
        saveBtn.style.color = '#3fb950';
        
        setTimeout(() => {
          saveBtn.innerHTML = originalHTML;
          saveBtn.style.color = '';
        }, 2000);
      });
      buttonsDiv.appendChild(saveBtn);
      
      // Copy button with visual feedback
      const copyBtn = document.createElement('button');
      copyBtn.className = 'code-action-btn';
      copyBtn.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>Copy`;
      copyBtn.title = 'Copy code to clipboard';
      
      copyBtn.addEventListener('click', async (e) => {
        e.stopPropagation();
        e.preventDefault();
        
        // Get code dynamically from the wrapper (handles streaming, reload, etc.)
        let textToCopy = '';
        const parentWrapper = copyBtn.closest('.code-block-wrapper');
        if (parentWrapper) {
          // Try CodeMirror instance first
          if (parentWrapper.cmInstance) {
            textToCopy = parentWrapper.cmInstance.getValue();
          }
          // Fall back to Base64 data attribute
          else if (parentWrapper.dataset.codeB64) {
            try {
              textToCopy = decodeURIComponent(escape(atob(parentWrapper.dataset.codeB64)));
            } catch (err) {
              console.error('Failed to decode Base64:', err);
            }
          }
          // Fall back to old data-code attribute
          else if (parentWrapper.dataset.code) {
            textToCopy = parentWrapper.dataset.code;
          }
          // Fall back to textarea content
          else {
            const ta = parentWrapper.querySelector('textarea');
            if (ta) textToCopy = ta.value || ta.textContent || '';
          }
        }
        
        // Fallback to captured codeText if nothing found
        if (!textToCopy) textToCopy = codeText;
        
        try {
          await navigator.clipboard.writeText(textToCopy);
          const originalHTML = copyBtn.innerHTML;
          copyBtn.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color: #3fb950;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Copied!`;
          copyBtn.style.color = '#3fb950';
          
          setTimeout(() => {
            copyBtn.innerHTML = originalHTML;
            copyBtn.style.color = '';
          }, 2000);
        } catch (err) {
          console.error('Copy failed:', err);
          // Fallback for older browsers or non-HTTPS
          try {
            const textArea = document.createElement('textarea');
            textArea.value = textToCopy;
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            
            const originalHTML = copyBtn.innerHTML;
            copyBtn.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color: #3fb950;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Copied!`;
            copyBtn.style.color = '#3fb950';
            setTimeout(() => {
              copyBtn.innerHTML = originalHTML;
              copyBtn.style.color = '';
            }, 2000);
          } catch (fallbackErr) {
            console.error('Fallback copy also failed:', fallbackErr);
          }
        }
      });
      buttonsDiv.appendChild(copyBtn);
      
      header.appendChild(langLabel);
      header.appendChild(buttonsDiv);
      
      // Insert wrapper - either replace old wrapper or insert before pre
      if (oldWrapper) {
        // Replace the old-format wrapper with the new one
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
      
      // Initialize CodeMirror (if available)
      if (typeof CodeMirror !== 'undefined') {
        const mode = getCodeMirrorMode(language);
        console.log('[enhanceCodeBlocks] Initializing CodeMirror for pre', idx, 'language:', language, 'mode:', mode);
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
          // Force refresh to ensure rendering
          setTimeout(() => {
            cm.refresh();
            console.log('[enhanceCodeBlocks] CodeMirror refreshed for pre', idx);
          }, 100);
          // Store reference for potential future use
          wrapper.cmInstance = cm;
          console.log('[enhanceCodeBlocks] CodeMirror initialized successfully for pre', idx);
        } catch (e) {
          console.error('[enhanceCodeBlocks] CodeMirror init failed:', e);
        }
      } else {
        console.warn('[enhanceCodeBlocks] CodeMirror not available');
      }
    });
  }

  // ============ STICKY CODE BLOCK BUTTONS ============
  // Makes just the buttons (Code, Preview, Save, Copy) sticky when scrolling code blocks
  let stickyButtonsInitialized = false;
  let stickyTicking = false;
  
  function updateStickyButtons() {
    // Determine the sticky top position based on visible header
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
      
      // Check if header has scrolled past the sticky position
      const headerPastTop = headerRect.top < stickyTop;
      // Check if wrapper bottom is still visible - add small offset (~1 line height) so buttons
      // disappear right as the last line passes the sticky position
      const lineHeight = 24;
      const wrapperBottomVisible = wrapperRect.bottom > (stickyTop + lineHeight);
      
      if (headerPastTop && wrapperBottomVisible) {
        // Each button should be sticky
        buttons.forEach((btn) => {
          if (!btn.classList.contains('stuck')) {
            // Capture original position before making sticky
            const rect = btn.getBoundingClientRect();
            btn.dataset.originalLeft = rect.left;
            btn.classList.add('stuck');
            btn.style.left = rect.left + 'px';
          }
        });
      } else {
        // Remove sticky from all buttons
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
  
  function requestStickyUpdate() {
    if (!stickyTicking) {
      requestAnimationFrame(updateStickyButtons);
      stickyTicking = true;
    }
  }
  
  function initStickyCodeButtons() {
    // Only add event listeners once
    if (!stickyButtonsInitialized) {
      window.addEventListener('scroll', requestStickyUpdate, { passive: true });
      window.addEventListener('resize', requestStickyUpdate, { passive: true });
      stickyButtonsInitialized = true;
    }
    
    // Always run an update to catch new code blocks
    updateStickyButtons();
  }

  // ============ SIMPLE CONVERSATION PERSISTENCE ============
  const STORAGE_KEY = 'ginto_conversations_v2';
  
  // ============ DATABASE SYNC FOR LOGGED-IN USERS ============
  // Check if user is logged in (set by view template)
  function isUserLoggedIn() {
    return window.GINTO_AUTH && window.GINTO_AUTH.isLoggedIn && window.GINTO_AUTH.userId;
  }
  
  // Get CSRF token from the page
  function getCsrfToken() {
    // First try GINTO_AUTH (set by /user endpoint)
    if (window.GINTO_AUTH && window.GINTO_AUTH.csrfToken) {
      return window.GINTO_AUTH.csrfToken;
    }
    // Fallback to hidden input
    const input = document.querySelector('input[name="csrf_token"]');
    return input ? input.value : '';
  }
  
  // Load conversations from database (for logged-in users)
  async function loadConvosFromDb() {
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
  
  // Save a single conversation to database (for logged-in users)
  async function saveConvoToDb(convoId, convo) {
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
  
  // Delete a conversation from database (for logged-in users)
  async function deleteConvoFromDb(convoId) {
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
  
  // Load all conversations from localStorage
  function loadAllConvos() {
    try {
      // Support migration from older storage keys
      const legacyKey = 'ginto_conversations';
      const dataV2 = localStorage.getItem(STORAGE_KEY);
      const dataLegacy = localStorage.getItem(legacyKey);
      if (!dataV2 && dataLegacy) {
        try {
          const parsedLegacy = JSON.parse(dataLegacy);
          // If legacy looks like a convos bundle, migrate directly
          if (parsedLegacy && (parsedLegacy.convos || parsedLegacy.activeId)) {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(parsedLegacy));
            console.log('[loadAllConvos] Migrated legacy conversations to', STORAGE_KEY);
          }
        } catch (e) {
          // ignore parse errors from legacy format
        }
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
  
  // Save all conversations to localStorage (and optionally to DB for logged-in users)
  function saveAllConvos() {
    try {
      const data = JSON.stringify({ convos: allConvos, activeId: activeConvoId });
      localStorage.setItem(STORAGE_KEY, data);
      console.log('[saveAllConvos] Saved to localStorage, size:', data.length, 'bytes', 'keys:', Object.keys(allConvos));
    } catch (e) { console.error('Save failed', e); }
  }
  
  // Save current conversation to DB (debounced to avoid excessive calls)
  let dbSaveTimeout = null;
  function saveCurrentConvoToDbDebounced() {
    if (!isUserLoggedIn()) return;
    if (dbSaveTimeout) clearTimeout(dbSaveTimeout);
    dbSaveTimeout = setTimeout(() => {
      if (activeConvoId && allConvos[activeConvoId]) {
        saveConvoToDb(activeConvoId, allConvos[activeConvoId]);
      }
    }, 1000); // Save after 1 second of inactivity
  }
  
  // Generate title from first user message
  function makeTitle(msgs) {
    const first = msgs.find(m => m.role === 'user');
    if (first?.content) {
      return first.content.length > 35 ? first.content.slice(0, 35) + '...' : first.content;
    }
    return 'New chat';
  }
  
  // State
  let stored = loadAllConvos();

  // NOTE: We used to clear persisted conversations immediately if an
  // unauthenticated auth object was present. This could cause guest
  // conversations to disappear on reload when auth is in a known
  // 'not logged in' state. Move this logic into initializeChat so we
  // only clear storage after auth is known and the user has explicitly
  // not logged in.

  const allConvos = stored.convos || {};
  let activeConvoId = stored.activeId;
  const history = [];
  
  console.log('[chat.js init] allConvos keys:', Object.keys(allConvos), 'activeConvoId:', activeConvoId);
  
  // Wait for auth to be ready before loading initial messages
  async function initializeChat() {
    // Wait for user info to be loaded
    if (window.GINTO_AUTH_PROMISE) {
      await window.GINTO_AUTH_PROMISE;
    }

    // For logged-in users, load conversations from database (with 24h expiration)
    if (isUserLoggedIn()) {
      console.log('[initializeChat] User is logged in, loading conversations from DB');
      const dbConvos = await loadConvosFromDb();
      if (dbConvos) {
        // Merge DB conversations into local state (DB takes priority)
        Object.keys(allConvos).forEach(k => delete allConvos[k]);
        Object.assign(allConvos, dbConvos);
        
        // Find the most recently updated conversation to set as active
        const convoIds = Object.keys(allConvos);
        if (convoIds.length > 0) {
          // Sort by ts (updated_at) descending and pick the first
          convoIds.sort((a, b) => (allConvos[b].ts || 0) - (allConvos[a].ts || 0));
          activeConvoId = convoIds[0];
        } else {
          activeConvoId = null;
        }
        console.log('[initializeChat] Loaded', convoIds.length, 'conversations from DB, active:', activeConvoId);
      }
    }
    
    console.log('[initializeChat] activeConvoId:', activeConvoId, 'exists:', !!allConvos[activeConvoId]);
    console.log('[initializeChat] existing convo keys:', Object.keys(allConvos));
    if (activeConvoId && !allConvos[activeConvoId]) {
      console.warn('[initializeChat] activeConvoId', activeConvoId, "not found in allConvos keys", Object.keys(allConvos));
    }
    if (allConvos[activeConvoId]) {
      console.log('[initializeChat] convo messages:', allConvos[activeConvoId].messages?.length);
    }
    
    // If we have an active convo, load its messages
    if (activeConvoId && allConvos[activeConvoId]) {
      const messages = allConvos[activeConvoId].messages || [];
      console.log('[initializeChat] Loading convo with', messages.length, 'messages');
      messages.forEach(m => {
        if (m && m.role && (m.content !== undefined || m.html !== undefined)) {
          history.push({...m});
        }
      });
      console.log('[initializeChat] Loaded', history.length, 'messages into history');
    } else {
      // Create first conversation
      activeConvoId = 'c_' + Date.now();
      allConvos[activeConvoId] = { id: activeConvoId, title: 'New chat', messages: [], ts: Date.now() };
      console.log('[initializeChat] Created new conversation:', activeConvoId);
    }
    
    // Render initial state
    renderMessages();
    renderConvoList();
  }
  
  // Initialize when auth is ready
  initializeChat();
  
  // After initialization, render LaTeX in all existing cards (for page reloads)
  setTimeout(() => {
    console.log('[chat.js] Running post-init LaTeX render for all cards');
    document.querySelectorAll('.card-response').forEach(el => {
      renderLatexInElement(el);
    });
  }, 500);

  // Ensure conversations are saved on unload so session state persists across
  // refreshes or accidental navigations.
  window.addEventListener('beforeunload', () => {
    try { saveAllConvos(); } catch (e) {}
  });

  // Listen for storage changes across tabs and reload conversations if updated
  window.addEventListener('storage', (e) => {
    try {
      if (!e || !e.key) return;
      if (e.key === STORAGE_KEY) {
        console.log('[storage] Detected changes to', STORAGE_KEY, 'reloading');
        const loaded = loadAllConvos();
        // Update in-memory state
        try { Object.keys(allConvos).forEach(k => delete allConvos[k]); } catch (er) {}
        Object.assign(allConvos, loaded.convos || {});
        activeConvoId = loaded.activeId;
        // Refresh UI
        renderConvoList();
        history.length = 0;
        if (activeConvoId && allConvos[activeConvoId]) {
          (allConvos[activeConvoId].messages || []).forEach(m => history.push({...m}));
        }
        renderMessages();
      }
    } catch (e) {
      console.warn('[storage] error handling', e);
    }
  });

  // Clear ALL persisted data on logout click
  (function bindLogoutClear() {
    try {
      const logoutEl = document.getElementById('logout-link');
      if (!logoutEl) return;
      logoutEl.addEventListener('click', function(e) {
        try {
          // Clear ALL localStorage to remove all persistence
          const keysToRemove = [];
          for (let i = 0; i < localStorage.length; i++) {
            const k = localStorage.key(i);
            if (k) keysToRemove.push(k);
          }
          keysToRemove.forEach(k => {
            try { localStorage.removeItem(k); } catch (er) {}
          });
          console.log('[logout] Cleared all localStorage keys:', keysToRemove.length);
        } catch (err) { console.error('Failed clearing storage on logout', err); }

        // wipe in-memory convos as a best-effort cleanup
        try {
          Object.keys(allConvos).forEach(k => delete allConvos[k]);
          activeConvoId = null;
          history.length = 0;
        } catch (er) {}
        
        // Clear sessionStorage too
        try { sessionStorage.clear(); } catch (er) {}
        
        // allow navigation to proceed
      });
    } catch (e) { /* ignore */ }
  })();
  
  // Listen for auth ready event to re-render if needed
  window.addEventListener('gintoAuthReady', () => {
    console.log('[Auth] User info loaded, re-rendering messages');
    renderMessages();
  });
  
  // Save current history to active conversation
  function syncCurrentConvo() {
    if (!activeConvoId) return;
    if (!allConvos[activeConvoId]) {
      allConvos[activeConvoId] = { id: activeConvoId, title: 'New chat', messages: [], ts: Date.now() };
    }
    console.log('[syncCurrentConvo] Syncing', activeConvoId, 'with', history.length, 'messages');
    // Deep copy messages, but strip large base64 imageUrl data to avoid localStorage quota issues
    // Keep server URLs (they start with /) as they are small and needed for display
    allConvos[activeConvoId].messages = history.map(m => {
      const copy = {...m};
      // Keep imageUrl if it's a server URL (starts with /), delete if it's base64
      if (copy.imageUrl && copy.imageUrl.startsWith('data:')) {
        delete copy.imageUrl;
      }
      return copy;
    });
    allConvos[activeConvoId].title = makeTitle(history);
    allConvos[activeConvoId].ts = Date.now();
    console.log('[syncCurrentConvo] After copy, convo has', allConvos[activeConvoId].messages.length, 'messages');
    saveAllConvos();
    // Also sync to database for logged-in users (debounced)
    saveCurrentConvoToDbDebounced();
    console.log('[syncCurrentConvo] Saved. Total convos:', Object.keys(allConvos).length);
    renderConvoList();
  }
  
  // Create new conversation
  function newConvo() {
    console.log('[newConvo] Before sync - allConvos:', Object.keys(allConvos).length, 'history:', history.length);
    // Save current first (only if it has messages)
    if (history.length > 0) {
      syncCurrentConvo();
    }
    console.log('[newConvo] After sync - allConvos:', Object.keys(allConvos).length);
    // Create new
    activeConvoId = 'c_' + Date.now();
    const newConvoData = { id: activeConvoId, title: 'New chat', messages: [], ts: Date.now() };
    allConvos[activeConvoId] = newConvoData;
    history.length = 0;
    console.log('[newConvo] After create - allConvos:', Object.keys(allConvos).length);
    saveAllConvos();
    // Save to DB immediately for logged-in users so new chat persists on reload
    if (isUserLoggedIn()) {
      saveConvoToDb(activeConvoId, newConvoData);
    }
    console.log('[newConvo] Saved. Rendering...');
    renderConvoList();
    renderMessages();
  }
  
  // Switch to a conversation
  function switchConvo(id) {
    if (!allConvos[id]) return;
    // Save current first
    syncCurrentConvo();
    // Switch
    activeConvoId = id;
    history.length = 0;
    allConvos[id].messages.forEach(m => history.push({...m}));
    saveAllConvos();
    renderConvoList();
    renderMessages();
  }
  
  // Delete a conversation
  function deleteConvo(id) {
    console.log('[deleteConvo] Deleting:', id, 'active:', activeConvoId);
    delete allConvos[id];
    
    // Also delete from database for logged-in users
    deleteConvoFromDb(id);
    
    if (activeConvoId === id) {
      // Clear active reference first to prevent sync issues
      activeConvoId = null;
      history.length = 0;
      
      const ids = Object.keys(allConvos);
      if (ids.length > 0) {
        // Switch to first remaining conversation
        activeConvoId = ids[0];
        allConvos[ids[0]].messages.forEach(m => history.push({...m}));
        saveAllConvos();
        renderConvoList();
        renderMessages();
      } else {
        // No conversations left, create new one
        newConvo();
      }
    } else {
      saveAllConvos();
      renderConvoList();
    }
    console.log('[deleteConvo] Done. Remaining convos:', Object.keys(allConvos));
  }
  
  // Generate article HTML from conversation
  function generateArticleFromConvo(convo) {
    const title = convo.title || 'Untitled Article';
    const date = new Date(convo.ts || Date.now()).toLocaleDateString('en-US', { 
      year: 'numeric', month: 'long', day: 'numeric' 
    });
    
    let content = '';
    if (convo.messages) {
      convo.messages.forEach(m => {
        if (m.role === 'assistant' && m.content) {
          // Use assistant messages as article content
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

  // Render conversation list in sidebar
  function renderConvoList() {
    const el = document.getElementById('conversation-list');
    if (!el) return;
    
    console.log('[renderConvoList] All convos:', Object.keys(allConvos).map(id => ({ id, msgs: allConvos[id].messages.length })));
    
    const list = Object.values(allConvos)
      .filter(c => c.messages && c.messages.length > 0)
      .sort((a, b) => {
        // Pinned items first
        if (a.pinned && !b.pinned) return -1;
        if (!a.pinned && b.pinned) return 1;
        // Then by timestamp
        return (b.ts || 0) - (a.ts || 0);
      });
    
    console.log('[renderConvoList] Filtered (with messages):', list.length);
    
    if (list.length === 0) {
      el.innerHTML = '<div class="text-sm text-gray-500 py-4 text-center">No conversations yet</div>';
      return;
    }
    
    // Helper to format time remaining until expiration
    function formatTimeRemaining(expiresAt) {
      if (!expiresAt || !isUserLoggedIn()) return '';
      const now = Date.now();
      // expiresAt is now a Unix timestamp in milliseconds from the server
      const expiry = typeof expiresAt === 'number' ? expiresAt : new Date(expiresAt).getTime();
      const diff = expiry - now;
      if (diff <= 0) return '<span class="text-red-500 text-xs" title="Expired. Upgrade to keep your conversations.">Expired</span>';
      const hours = Math.floor(diff / (1000 * 60 * 60));
      const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
      if (hours > 0) {
        return `<span class="text-orange-500 text-xs cursor-help" title="Expires in ${hours} hour${hours > 1 ? 's' : ''}. Upgrade to keep.">${hours}h</span>`;
      }
      return `<span class="text-red-500 text-xs cursor-help" title="Expires in ${mins} minute${mins > 1 ? 's' : ''}. Upgrade to keep.">${mins}m</span>`;
    }
    
    el.innerHTML = list.map(c => {
      const expiryBadge = c.expires_at ? formatTimeRemaining(c.expires_at) : '';
      return `
      <div class="convo-item group flex items-center gap-2 px-4 py-2 cursor-pointer transition-colors text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 hover:text-indigo-700 dark:hover:text-indigo-300 text-sm ${c.id === activeConvoId ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300' : ''}" data-id="${c.id}">
        ${c.pinned ? '<svg class="w-4 h-4 flex-shrink-0 text-indigo-500" fill="currentColor" viewBox="0 0 24 24"><path d="M16.5 3.75V16.5L12 14.25 7.5 16.5V3.75m9 0H18A2.25 2.25 0 0120.25 6v12A2.25 2.25 0 0118 20.25H6A2.25 2.25 0 013.75 18V6A2.25 2.25 0 016 3.75h1.5m9 0h-9"/></svg>' : '<svg class="w-4 h-4 flex-shrink-0 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>'}
        <span class="sidebar-label flex-1 truncate">${c.title.replace(/</g,'&lt;')}</span>
        ${expiryBadge}
        <div class="convo-menu-container relative opacity-0 group-hover:opacity-100 flex-shrink-0">
          <button class="convo-menu-btn sidebar-label p-0.5 text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 rounded hover:bg-gray-200 dark:hover:bg-gray-700 transition-all" data-id="${c.id}" title="Options">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
              <circle cx="5" cy="12" r="1.5"/>
              <circle cx="12" cy="12" r="1.5"/>
              <circle cx="19" cy="12" r="1.5"/>
            </svg>
          </button>
          <div class="convo-menu hidden absolute right-0 top-full mt-1 w-44 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 py-1 z-50" data-id="${c.id}">
            <button class="menu-rename w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 hover:text-indigo-700 dark:hover:text-indigo-300 transition-colors text-left">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
              Rename
            </button>
            <button class="menu-article w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 hover:text-indigo-700 dark:hover:text-indigo-300 transition-colors text-left">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
              Make Article
            </button>
            <button class="menu-export w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 hover:text-indigo-700 dark:hover:text-indigo-300 transition-colors text-left">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
              Export
            </button>
            <button class="menu-pin w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 hover:text-indigo-700 dark:hover:text-indigo-300 transition-colors text-left">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.75V16.5L12 14.25 7.5 16.5V3.75m9 0H18A2.25 2.25 0 0120.25 6v12A2.25 2.25 0 0118 20.25H6A2.25 2.25 0 013.75 18V6A2.25 2.25 0 016 3.75h1.5m9 0h-9"/></svg>
              Pin to top
            </button>
            <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
            <button class="menu-delete w-full flex items-center gap-2 px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors text-left">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
              Delete
            </button>
          </div>
        </div>
      </div>
    `;
    }).join('');
    
    // Click handlers
    el.querySelectorAll('.convo-item').forEach(item => {
      item.addEventListener('click', (e) => {
        // Don't switch if clicking menu button or menu
        if (e.target.closest('.convo-menu-btn') || e.target.closest('.convo-menu')) return;
        switchConvo(item.dataset.id);
      });
    });
    
    // Menu button handlers
    el.querySelectorAll('.convo-menu-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const menu = btn.nextElementSibling;
        // Close all other menus first
        document.querySelectorAll('.convo-menu').forEach(m => {
          if (m !== menu) m.classList.add('hidden');
        });
        menu.classList.toggle('hidden');
      });
    });
    
    // Menu action handlers
    el.querySelectorAll('.menu-rename').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const id = btn.closest('.convo-menu').dataset.id;
        const convo = allConvos[id];
        const newTitle = prompt('Rename conversation:', convo?.title || 'New chat');
        if (newTitle && newTitle.trim()) {
          convo.title = newTitle.trim();
          saveAllConvos();
          renderConvoList();
        }
        btn.closest('.convo-menu').classList.add('hidden');
      });
    });
    
    el.querySelectorAll('.menu-article').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const id = btn.closest('.convo-menu').dataset.id;
        const convo = allConvos[id];
        if (convo && convo.messages) {
          // Generate article from conversation
          const article = generateArticleFromConvo(convo);
          // Open in new window or copy to clipboard
          const win = window.open('', '_blank');
          win.document.write(article);
          win.document.close();
        }
        btn.closest('.convo-menu').classList.add('hidden');
      });
    });
    
    el.querySelectorAll('.menu-export').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const id = btn.closest('.convo-menu').dataset.id;
        const convo = allConvos[id];
        if (convo) {
          const json = JSON.stringify(convo, null, 2);
          const blob = new Blob([json], { type: 'application/json' });
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `${convo.title || 'conversation'}.json`;
          a.click();
          URL.revokeObjectURL(url);
        }
        btn.closest('.convo-menu').classList.add('hidden');
      });
    });
    
    el.querySelectorAll('.menu-pin').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const id = btn.closest('.convo-menu').dataset.id;
        const convo = allConvos[id];
        if (convo) {
          convo.pinned = !convo.pinned;
          saveAllConvos();
          renderConvoList();
        }
        btn.closest('.convo-menu').classList.add('hidden');
      });
    });
    
    el.querySelectorAll('.menu-delete').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const id = btn.closest('.convo-menu').dataset.id;
        const convo = allConvos[id];
        btn.closest('.convo-menu').classList.add('hidden');
        
        const confirmed = await showConfirmModal({
          title: 'Delete Conversation',
          message: `Are you sure you want to delete "${convo?.title || 'this conversation'}"? This action cannot be undone.`,
          confirmText: 'Delete',
          type: 'danger'
        });
        
        if (confirmed) {
          deleteConvo(id);
        }
      });
    });
    
    // Close menus when clicking outside
    document.addEventListener('click', () => {
      document.querySelectorAll('.convo-menu').forEach(m => m.classList.add('hidden'));
    });
  }

  // ============ SEARCH OVER PERSISTED CONVERSATIONS ============
  // Debounce helper
  function debounce(fn, wait) {
    let t;
    return function(...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), wait);
    };
  }

  // Escape HTML for safe insertion
  function escapeHtml(text) {
    return String(text || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  // Create a snippet around the first match
  function makeSnippet(content, idx, len = 120) {
    const start = Math.max(0, idx - Math.floor(len/3));
    const snippet = content.substring(start, start + len);
    return (start > 0 ? '... ' : '') + snippet + (start + len < content.length ? ' ...' : '');
  }

  // Perform search across allConvos titles and messages
  function performSearch(q) {
    const outEl = document.getElementById('conversation-list');
    if (!outEl) return;
    q = String(q || '').trim();
    if (!q) {
      renderConvoList();
      return;
    }
    const term = q.toLowerCase();
    const matches = [];
    Object.values(allConvos).forEach(c => {
      // search title
      if ((c.title || '').toLowerCase().includes(term)) {
        matches.push({ convo: c, snippet: escapeHtml(c.title) });
        return;
      }
      // search messages
      if (Array.isArray(c.messages)) {
        for (const m of c.messages) {
          const text = (m.content || '') + '';
          const li = text.toLowerCase().indexOf(term);
          if (li !== -1) {
            const rawSnippet = makeSnippet(text, li);
            // highlight matched term
            const escaped = escapeHtml(rawSnippet).replace(new RegExp(term.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'), 'ig'), (m1) => `<mark>${m1}</mark>`);
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

    // attach handlers
    outEl.querySelectorAll('.convo-item').forEach(item => {
      item.addEventListener('click', () => switchConvo(item.dataset.id));
    });
    outEl.querySelectorAll('.del-btn').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const id = btn.dataset.id;
        const convo = allConvos[id];
        
        const confirmed = await showConfirmModal({
          title: 'Delete Conversation',
          message: `Are you sure you want to delete "${convo?.title || 'this conversation'}"? This action cannot be undone.`,
          confirmText: 'Delete',
          type: 'danger'
        });
        
        if (confirmed) {
          deleteConvo(id);
        }
      });
    });
  }

  const searchEl = document.getElementById('convo-search');
  if (searchEl) {
    const deb = debounce((e) => performSearch(e.target.value), 250);
    searchEl.addEventListener('input', deb);
  }
  
  const msgId = () => Date.now().toString(36) + Math.random().toString(36).slice(2,6);

  // Fetch MCP discovery info for client-side display (dev-friendly).
  (async function fetchMcpDiscover() {
    // Ensure we have a CSRF token for subsequent POSTs. Dev endpoint
    // `/dev/csrf` returns JSON { success: true, csrf_token, session_id }
    async function ensureCsrf() {
      try {
        const r = await fetch('/dev/csrf', { credentials: 'same-origin' });
        if (!r.ok) return '';
        const j = await r.json().catch(() => null);
        const tok = (j && j.csrf_token) ? j.csrf_token : '';
        if (tok) window.CSRF_TOKEN = tok;
        return tok;
      } catch (e) { return ''; }
    }
    // Kick off CSRF fetch in parallel with MCP discovery
    ensureCsrf().catch(() => {});
    try {
      const badge = document.getElementById('mcp-badge');
      const statusDot = document.getElementById('mcp-status-dot');
      const statusDotPanel = document.getElementById('mcp-status-dot-panel');
      const statusText = document.getElementById('mcp-status-text');
      
      function setMcpStatus(available, text) {
        if (badge) badge.textContent = text || '';
        const dotColor = available ? 'bg-green-500' : 'bg-red-500';
        const dotColorOld = available ? 'bg-red-500' : 'bg-green-500';
        if (statusDot) { statusDot.classList.remove(dotColorOld); statusDot.classList.add(dotColor); }
        if (statusDotPanel) { statusDotPanel.classList.remove(dotColorOld); statusDotPanel.classList.add(dotColor); }
        if (statusText) {
          statusText.textContent = available ? 'Available' : 'Unavailable';
          statusText.classList.toggle('text-green-600', available);
          statusText.classList.toggle('dark:text-green-400', available);
          statusText.classList.toggle('text-red-600', !available);
          statusText.classList.toggle('dark:text-red-400', !available);
        }
      }
      
      const res = await fetch('/mcp/unified', { method: 'GET', credentials: 'same-origin' });
      if (!res.ok) {
        setMcpStatus(false, 'MCP: Unavailable');
        return;
      }
      const j = await res.json().catch(() => null);
      if (!j || !j.success) {
        setMcpStatus(false, 'MCP: Unavailable');
        return;
      }
      // Prefer server-provided `mcps` and `handlers` lists when available.
      // NOTE: don't return early here — we want to render the full
      // capabilities panel below even when mcps/handlers are present.
      const mcps = Array.isArray(j.mcps) ? j.mcps : [];
      const handlers = Array.isArray(j.handlers) ? j.handlers : [];
      if (mcps.length || handlers.length) {
        const parts = [];
        if (mcps.length) parts.push('mcps: ' + mcps.join(', '));
        if (handlers.length) parts.push('handlers: ' + handlers.join(', '));
        if (badge) badge.textContent = parts.join(' | ');
        // continue and render the capabilities panel as well
      }

      // Normalized unifier returns `tools` as array of objects; fallbacks handled
      const tools = Array.isArray(j.tools) ? j.tools : (Array.isArray(j.tools?.result) ? j.tools.result : []);
      const names = tools.map(t => (typeof t === 'string' ? t : (t.name || t['name'] || (t.name ?? 'unknown')))).filter(Boolean);

      // Build capabilities summary per tool. We try several common fields
      // that the discovery script may expose. This is defensive — if the
      // schema doesn't include capabilities, fall back to description or
      // schema properties.
      function extractCapabilities(tool) {
        if (!tool) return '';
        try {
          if (Array.isArray(tool.capabilities) && tool.capabilities.length) return tool.capabilities.join(', ');
          if (typeof tool.capabilities === 'string' && tool.capabilities) return tool.capabilities;
          if (Array.isArray(tool.actions) && tool.actions.length) return tool.actions.map(a => a.name || a.id || a.command || JSON.stringify(a)).join(', ');
          if (tool.schema && tool.schema.properties && typeof tool.schema.properties === 'object') return Object.keys(tool.schema.properties).join(', ');
          if (tool.description && typeof tool.description === 'string') return tool.description.split(/[\.\n]/)[0];
        } catch (e) {}
        return '';
      }

      const caps = [];
      for (const t of tools) {
        const tname = (typeof t === 'string' ? t : (t.name || t['name'] || (t.name ?? 'unknown')));
        const c = extractCapabilities(t);
        if (c) caps.push(tname + ': ' + c);
      }

      // If discovery shows at least one MCP or exposes a chat tool, allow
      // the client to prefer `/mcp/chat` (non-streaming) so that browser
      // requests include the CSRF token and session cookie. This keeps
      // CSRF protection intact while enabling MCP-driven responses.
      if (names.length > 0) {
        setMcpStatus(true, 'MCP Available');
        if (badge && caps.length) badge.title = caps.join('\n');
        if (statusText) statusText.textContent = 'Available (' + names.length + ' tools)';
      } else {
        setMcpStatus(false, 'No Tools');
      }

      // Render a full capabilities list into the UI (if container exists)
      try {
        const capContainer = document.getElementById('mcp-capabilities');
        if (capContainer) {
          if (!tools || tools.length === 0) {
            capContainer.textContent = 'No MCP tools discovered.';
          } else {
            function escapeHtml(s) {
              if (s == null) return '';
              return String(s).replace(/[&<>"']/g, function(ch) {
                return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[ch];
              });
            }
            
            // Get disabled tools from localStorage
            function getDisabledTools() {
              try {
                const stored = localStorage.getItem('ginto_disabled_tools');
                return stored ? JSON.parse(stored) : [];
              } catch (e) { return []; }
            }
            
            function setDisabledTools(arr) {
              localStorage.setItem('ginto_disabled_tools', JSON.stringify(arr));
              // Sync to server via session
              syncDisabledToolsToServer(arr);
            }
            
            function isToolDisabled(toolName) {
              return getDisabledTools().includes(toolName);
            }
            
            function toggleTool(toolName) {
              const disabled = getDisabledTools();
              const idx = disabled.indexOf(toolName);
              if (idx >= 0) {
                disabled.splice(idx, 1);
              } else {
                disabled.push(toolName);
              }
              setDisabledTools(disabled);
              return idx >= 0; // returns true if now enabled
            }
            
            // Sync disabled tools to server session
            function syncDisabledToolsToServer(disabledArr) {
              fetch('/chat/disabled-tools', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ disabled_tools: disabledArr, csrf_token: window.CSRF_TOKEN })
              }).catch(() => {});
            }
            
            // Initial sync on load
            syncDisabledToolsToServer(getDisabledTools());

            // Simpler, clearer rendering: group by MCP (if present) and list tools
            try {
              function prettyToolLine(t) {
                const tname = (typeof t === 'string' ? t : (t.name || t['name'] || (t.name ?? 'unknown')));
                const desc = t.description || '';
                const disabled = isToolDisabled(tname);
                const toggleId = 'tool-toggle-' + tname.replace(/[^a-z0-9]/gi, '-');
                let line = '<div class="p-2 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700 flex items-start gap-3' + (disabled ? ' opacity-50' : '') + '" data-tool-name="' + escapeHtml(tname) + '">';
                line += '<label class="relative inline-flex items-center cursor-pointer flex-shrink-0 mt-0.5">';
                line += '<input type="checkbox" id="' + toggleId + '" class="sr-only peer tool-toggle-checkbox" data-tool="' + escapeHtml(tname) + '"' + (disabled ? '' : ' checked') + '>';
                line += '<div class="w-9 h-5 bg-gray-300 dark:bg-gray-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[\'\'] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>';
                line += '</label>';
                line += '<div class="flex-1 min-w-0">';
                line += '<code class="font-semibold text-indigo-600 dark:text-indigo-400">' + escapeHtml(tname) + '</code>';
                if (desc) line += '<div class="text-xs text-gray-500 dark:text-gray-400 mt-1">' + escapeHtml(desc.substring(0, 100)) + (desc.length > 100 ? '...' : '') + '</div>';
                line += '</div></div>';
                return line;
              }

              // build mcpMap heuristically as before
              const mcpMap = {};
              try {
                if (Array.isArray(mcps) && mcps.length) {
                  for (const m of mcps) mcpMap[m] = new Set();
                  const mcpTokens = Object.keys(mcpMap).map(m => ({ m, tokens: m.toLowerCase().split(/[^a-z0-9]+/) }));
                  for (const t of tools) {
                    const tname = (typeof t === 'string' ? t : (t.name || t['name'] || (t.name ?? ''))).toLowerCase();
                    let assigned = false;
                    for (const mt of mcpTokens) {
                      for (const tok of mt.tokens) {
                        if (!tok) continue;
                        if (tname.includes(tok)) { mcpMap[mt.m].add(tname); assigned = true; break; }
                      }
                      if (assigned) break;
                    }
                    if (!assigned) {
                      mcpMap['(other)'] = mcpMap['(other)'] || new Set();
                      mcpMap['(other)'].add(tname);
                    }
                  }
                }
              } catch (e) {}

              // Collect runtime discovery errors (if any) and show them prominently
              const discoveryErrors = Array.isArray(j.errors) ? j.errors : [];
              let html = '';
              if (discoveryErrors.length) {
                html += '<div class="mb-3 p-3 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">';
                html += '<div class="font-medium text-red-700 dark:text-red-400 mb-2">Discovery Errors</div>';
                for (const er of discoveryErrors) {
                  const m = er.mcp || '(unknown)';
                  const em = er.error ? escapeHtml(er.error) : 'failed';
                  html += '<div class="text-sm text-red-600 dark:text-red-300 mb-1"><strong>' + escapeHtml(m) + '</strong>: ' + em + '</div>';
                }
                html += '</div>';
              }
              if (Object.keys(mcpMap).length) {
                for (const m of Object.keys(mcpMap)) {
                  const items = Array.from(mcpMap[m] || []).map(n => {
                    const found = tools.find(tt => ((typeof tt === 'string' ? tt : (tt.name || tt['name'] || '')).toLowerCase() === n));
                    return found || n;
                  });
                  html += '<div class="mb-4"><div class="font-medium text-gray-700 dark:text-gray-300 mb-2">' + escapeHtml(m) + '</div>';
                  if (items.length === 0) html += '<div class="text-gray-500 text-sm pl-2">(no tools)</div>';
                  else {
                    html += '<div class="space-y-1 pl-2">';
                    for (const it of items) html += prettyToolLine(it);
                    html += '</div>';
                  }
                  html += '</div>';
                }
              } else {
                html += '<div class="space-y-1">';
                for (const t of tools) html += prettyToolLine(t);
                html += '</div>';
              }

              // leftover tools
              const grouped = new Set();
              for (const s of Object.values(mcpMap)) for (const n of s) grouped.add(n);
              const leftovers = tools.filter(tt => {
                const n = (typeof tt === 'string' ? tt : (tt.name || tt['name'] || '')).toLowerCase();
                return !grouped.has(n);
              });
              if (leftovers.length) {
                html += '<div class="mb-4"><div class="font-medium text-gray-700 dark:text-gray-300 mb-2">Other Tools</div>';
                html += '<div class="space-y-1 pl-2">';
                for (const t of leftovers) html += prettyToolLine(t);
                html += '</div></div>';
              }

              // Remove the copy button from here - it's now in the HTML
              capContainer.innerHTML = html;

              // Determine whether to prefer MCP-based chat. Simple heuristic:
              // if discovery returned explicit mcps or any tool named
              // 'chat_completion', enable MCP usage.
              try {
                window.USE_MCP = false;
                if (Array.isArray(j.mcps) && j.mcps.length > 0) window.USE_MCP = true;
                if (!window.USE_MCP && Array.isArray(j.tools)) {
                  for (const t of j.tools) {
                    const nm = (typeof t === 'string' ? t : (t.name || t['name'] || '')) || '';
                    if (nm.toLowerCase() === 'chat_completion') { window.USE_MCP = true; break; }
                  }
                }
              } catch (e) {}

              try {
                const btn = document.getElementById('mcp-copy-json');
                if (btn) btn.addEventListener('click', async () => {
                  const success = await copyToClipboard(JSON.stringify(j, null, 2));
                  if (success) { btn.textContent = 'Copied'; setTimeout(()=>btn.textContent='Copy discovery JSON',1200); }
                });
              } catch (e) {}
              
              // Bind tool toggle event listeners
              capContainer.querySelectorAll('.tool-toggle-checkbox').forEach(cb => {
                cb.addEventListener('change', function() {
                  const toolName = this.dataset.tool;
                  const isNowEnabled = this.checked;
                  const disabled = getDisabledTools();
                  const idx = disabled.indexOf(toolName);
                  if (isNowEnabled && idx >= 0) {
                    disabled.splice(idx, 1);
                  } else if (!isNowEnabled && idx < 0) {
                    disabled.push(toolName);
                  }
                  setDisabledTools(disabled);
                  // Update visual state
                  const row = this.closest('[data-tool-name]');
                  if (row) {
                    if (isNowEnabled) row.classList.remove('opacity-50');
                    else row.classList.add('opacity-50');
                  }
                });
              });
              
              // Bind Enable All / Disable All buttons
              const enableAllBtn = document.getElementById('mcp-enable-all');
              const disableAllBtn = document.getElementById('mcp-disable-all');
              if (enableAllBtn) {
                enableAllBtn.addEventListener('click', () => {
                  setDisabledTools([]);
                  capContainer.querySelectorAll('.tool-toggle-checkbox').forEach(cb => {
                    cb.checked = true;
                    const row = cb.closest('[data-tool-name]');
                    if (row) row.classList.remove('opacity-50');
                  });
                });
              }
              if (disableAllBtn) {
                disableAllBtn.addEventListener('click', () => {
                  const allTools = [];
                  capContainer.querySelectorAll('.tool-toggle-checkbox').forEach(cb => {
                    allTools.push(cb.dataset.tool);
                    cb.checked = false;
                    const row = cb.closest('[data-tool-name]');
                    if (row) row.classList.add('opacity-50');
                  });
                  setDisabledTools(allTools);
                });
              }
            } catch (e) {}
          }
        }
      } catch (e) {
        // non-fatal
      }
    } catch (e) {
      try { setMcpStatus(false, 'Error'); } catch(_){}
    }
  })();

  // Render message history into the UI
  function renderMessages() {
    try {
      if (!messagesEl) return;
      
      // Preserve empty state hint before clearing
      const hintEl = messagesEl.querySelector('.bg-hint');
      const hintHtml = hintEl ? hintEl.outerHTML : null;
      
      messagesEl.innerHTML = '';
      
      // Filter out system messages
      const visibleHistory = history.filter(m => m.role !== 'system');
      
      // If no messages, restore/show the welcome screen
      if (visibleHistory.length === 0) {
        if (hintHtml) {
          messagesEl.innerHTML = hintHtml;
          // Make sure the hint is visible (it may have been hidden with display:none)
          const restoredHint = messagesEl.querySelector('.bg-hint');
          if (restoredHint) {
            restoredHint.style.display = '';
          }
          // Re-attach event listeners for example prompts
          messagesEl.querySelectorAll('.example-prompt').forEach(btn => {
            btn.addEventListener('click', () => {
              const text = btn.querySelector('span')?.textContent;
              if (text && promptEl) {
                promptEl.value = text;
                promptEl.focus();
              }
            });
          });
        }
        return;
      }
      
      // Get or create the convo container for card-style rendering
      const convoContainer = getConvoContainer();
      convoContainer.innerHTML = ''; // Clear existing cards
      
      console.log('[renderMessages] Rendering', visibleHistory.length, 'messages, container:', convoContainer);
      
      // Group messages into user-assistant pairs for card rendering
      let i = 0;
      while (i < visibleHistory.length) {
        const m = visibleHistory[i];
        
        // If it's a user message, look for the following assistant response
        if (m.role === 'user') {
          const userMsg = m;
          let assistantMsg = null;
          
          // Check if next message is assistant
          if (i + 1 < visibleHistory.length && visibleHistory[i + 1].role === 'assistant') {
            assistantMsg = visibleHistory[i + 1];
            i += 2; // Skip both messages
          } else {
            i += 1; // Just skip user message
          }
          
          // Render as a card
          renderSavedCard(convoContainer, userMsg, assistantMsg);
        } else if (m.role === 'assistant') {
          // Orphan assistant message (no preceding user message) - still render it
          renderSavedCard(convoContainer, null, m);
          i += 1;
        } else {
          i += 1; // Skip unknown roles
        }
      }
      
      // Scroll to bottom
      try { messagesEl.scrollTop = messagesEl.scrollHeight; } catch (e) {}
    } catch (e) { console.error('renderMessages', e); }
  }
  
  // Render a saved user-assistant message pair as messenger-style bubbles
  function renderSavedCard(container, userMsg, assistantMsg) {
    const query = userMsg?.content || '';
    console.log('[renderSavedCard] Rendering - userMsg:', !!userMsg, 'content:', query?.substring(0, 50), 'assistantMsg:', !!assistantMsg);
    const timeStr = userMsg?.ts ? new Date(userMsg.ts).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) 
                                : new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    
    // Create wrapper for the message pair
    const wrapper = document.createElement('div');
    wrapper.className = 'messenger-pair';
    
    // ===== USER MESSAGE BUBBLE (right-aligned, blue) =====
    if (userMsg) {
      const userRow = document.createElement('div');
      userRow.className = 'messenger-row messenger-row-user';
      userRow.style.cssText = 'display:flex;flex-direction:column;align-items:flex-end;width:100%;margin-bottom:8px;';
      
      // Build user image HTML if present
      let userImageHtml = '';
      if (userMsg?.hasImage && userMsg?.imageUrl) {
        userImageHtml = `
          <div style="max-width:70%;margin-bottom:8px;">
            <img src="${userMsg.imageUrl}" alt="Attached image" style="max-width:200px;max-height:128px;border-radius:12px;cursor:pointer;" onclick="window.showImageModal && window.showImageModal(this.src)">
          </div>`;
      }
      
      userRow.innerHTML = `
        ${userImageHtml}
        <div class="messenger-bubble messenger-bubble-user">
          <div class="user-message-content" style="white-space:pre-wrap;">${escapeHtml(query)}</div>
          <div class="user-message-edit-area hidden">
            <textarea class="user-edit-textarea"></textarea>
            <div class="user-edit-buttons">
              <button class="user-edit-cancel-btn">Cancel</button>
              <button class="user-edit-save-btn">Save & Send</button>
            </div>
          </div>
        </div>
        <div class="user-message-actions">
          <button class="user-action-btn user-copy-btn" title="Copy">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
          </button>
          <button class="user-action-btn user-edit-btn" title="Edit">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
          </button>
          <button class="user-action-btn user-regen-btn" title="Regenerate">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          </button>
        </div>
      `;
      
      // Add event listeners for user action buttons
      const userCopyBtn = userRow.querySelector('.user-copy-btn');
      const userEditBtn = userRow.querySelector('.user-edit-btn');
      const userRegenBtn = userRow.querySelector('.user-regen-btn');
      const userBubble = userRow.querySelector('.messenger-bubble-user');
      const userMsgContent = userBubble?.querySelector('.user-message-content');
      const editArea = userBubble?.querySelector('.user-message-edit-area');
      const editTextarea = userBubble?.querySelector('.user-edit-textarea');
      const editCancelBtn = userBubble?.querySelector('.user-edit-cancel-btn');
      const editSaveBtn = userBubble?.querySelector('.user-edit-save-btn');
      
      // Copy user prompt
      userCopyBtn?.addEventListener('click', async (e) => {
        e.stopPropagation();
        const success = await copyToClipboard(query);
        if (success) {
          userCopyBtn.innerHTML = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
          setTimeout(() => {
            userCopyBtn.innerHTML = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>';
          }, 2000);
        }
      });
      
      // Edit user prompt - show edit in place
      userEditBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        if (userMsgContent && editArea && editTextarea) {
          editTextarea.value = query;
          userMsgContent.classList.add('hidden');
          editArea.classList.remove('hidden');
          editTextarea.focus();
          editTextarea.setSelectionRange(editTextarea.value.length, editTextarea.value.length);
        }
      });
      
      // Cancel edit
      editCancelBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        if (userMsgContent && editArea) {
          editArea.classList.add('hidden');
          userMsgContent.classList.remove('hidden');
        }
      });
      
      // Save & Send edited message
      editSaveBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        const newQuery = editTextarea?.value?.trim();
        if (newQuery && promptEl && sendBtn) {
          editArea.classList.add('hidden');
          userMsgContent.classList.remove('hidden');
          window._regeneratingPromptId = userMsg.id || userMsg.ts;
          window._editedPromptText = newQuery;
          promptEl.value = newQuery;
          sendBtn.click();
        }
      });
      
      // Regenerate response - resend the same prompt and store alternative response
      userRegenBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        if (query && promptEl && sendBtn) {
          // Mark this as a regeneration request to store alternative response
          window._regeneratingPromptId = userMsg.id || userMsg.ts;
          promptEl.value = query;
          sendBtn.click();
        }
      });
      
      wrapper.appendChild(userRow);
    }
    
    // ===== ASSISTANT MESSAGE (using existing card styling) =====
    if (assistantMsg) {
      const assistantRow = document.createElement('div');
      assistantRow.className = 'convo-card-body';
      assistantRow.style.cssText = 'width:100%;';
      
      // Build response content
      let responseContent = '';
      let toolResultHtml = '';
      
      // Render tool results from saved toolResults array
      if (assistantMsg?.toolResults && assistantMsg.toolResults.length > 0) {
        toolResultHtml = assistantMsg.toolResults.map(tr => formatToolResult(tr.name, tr.result)).join('');
      }
      
      // If we have toolResults, use content (not html) to avoid duplicates
      // The html field may contain already-rendered tool results from streaming
      if (assistantMsg?.toolResults && assistantMsg.toolResults.length > 0) {
        // Use markdown content, not pre-rendered html (which may have tool results embedded)
        if (assistantMsg?.content) {
          responseContent = toolResultHtml + simpleMarkdownToHtml(assistantMsg.content);
        } else {
          responseContent = toolResultHtml;
        }
      } else if (assistantMsg?.content) {
        responseContent = simpleMarkdownToHtml(assistantMsg.content);
      } else if (assistantMsg?.html) {
        responseContent = ensureCodeBlockAttributes(assistantMsg.html);
      } else {
        responseContent = '<p>No response</p>';
      }

      
      
      // Build search task HTML if activities are available (collapsible)
      let searchTaskHtml = '';
      const activities = assistantMsg?.activities || {};
      const searches = activities.searches || [];
      const reads = activities.reads || [];
      if (searches.length > 0 || reads.length > 0) {
        // Build activity lines - deduplicate by query/domain
        let activityLines = '';
        const seenQueries = new Set();
        const seenDomains = new Set();
        
        searches.forEach(s => {
          const query = s.query || '';
          if (query && !seenQueries.has(query)) {
            seenQueries.add(query);
            activityLines += `<div class="reasoning-item"><div class="reasoning-item-indicator"><div class="reasoning-item-dot"></div><div class="reasoning-item-line"></div></div><div class="reasoning-item-text"><p>🔎 Searched: "${escapeHtml(query)}" ✅</p></div></div>`;
          }
        });
        reads.forEach(r => {
          const domain = r.domain || '';
          if (domain && !seenDomains.has(domain)) {
            seenDomains.add(domain);
            activityLines += `<div class="reasoning-item"><div class="reasoning-item-indicator"><div class="reasoning-item-dot"></div><div class="reasoning-item-line"></div></div><div class="reasoning-item-text"><p>📖 Read: ${escapeHtml(domain)} ✅</p></div></div>`;
          }
        });
        // Add completion line
        activityLines += `<div class="reasoning-item"><div class="reasoning-item-indicator"><div class="reasoning-item-dot" style="background:#22c55e"></div><div class="reasoning-item-line"></div></div><div class="reasoning-item-text" style="color:#22c55e"><p>🏁 Complete! ${seenDomains.size} sources</p></div></div>`;
        
        searchTaskHtml = `
          <div class="card-search-task mb-3">
            <div class="search-task-timeline">
              <div class="search-task-header card-search-toggle" style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;padding:4px 8px;border-radius:8px;font-size:0.8125rem;font-weight:600;color:#b0b3b8;">
                <svg class="search-chevron" style="width:16px;height:16px;transition:transform 0.2s;transform:rotate(-90deg);" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
                <span style="font-size:12px;">🐼</span>
                <span>Search complete</span>
              </div>
              <div class="card-search-content search-task-content modern-scroll hidden" style="margin-top:8px;padding-left:8px;max-height:200px;overflow-y:auto;">${activityLines}</div>
            </div>
          </div>`;
      }

      // Build reasoning HTML if available (collapsible) - using existing card-reasoning structure
      let reasoningHtml = '';
      if (assistantMsg?.reasoning) {
        reasoningHtml = `
          <div class="card-reasoning mb-3">
            <div class="reasoning-timeline">
              <div class="reasoning-header card-reasoning-toggle">
                <svg class="reasoning-chevron open" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
                <span>Reasoning</span>
              </div>
              <div class="card-reasoning-content reasoning-content modern-scroll">${formatReasoningText(assistantMsg.reasoning)}</div>
            </div>
          </div>`;
      }
      
      // Build citations HTML if available
      let citationsHtml = '';
      let sourcesStackHtml = '';
      const savedCitations = assistantMsg?.citations || [];
      if (savedCitations.length > 0) {
        const citationItems = savedCitations.slice(0, 4).map((c, i) => {
          const faviconUrl = c.favicon || `https://www.google.com/s2/favicons?domain=${escapeHtml(c.domain || '')}&sz=16`;
          return `<a href="${escapeHtml(c.url || '')}" target="_blank" class="citation-chip" onclick="event.stopPropagation()">
            <img src="${faviconUrl}" alt="" class="w-4 h-4 rounded-full" style="object-fit:cover;" onerror="this.style.display='none'">
            <span>${escapeHtml(c.domain || '')}</span>
          </a>`;
        }).join('');
        citationsHtml = `<div class="card-citations mb-3" style="margin-left:10px;"><div class="flex flex-wrap gap-2 card-citations-list">${citationItems}</div></div>`;
        
        // Build sources stack with favicons for footer (right-aligned)
        const sourceIcons = savedCitations.slice(0, 4).map((c, i) => {
          const faviconUrl = c.favicon || `https://www.google.com/s2/favicons?domain=${c.domain}&sz=16`;
          const marginLeft = i === 0 ? '0' : '-4px';
          return `<img src="${faviconUrl}" alt="${c.domain}" title="${escapeHtml(c.title || c.domain)}" loading="lazy" style="width:16px;height:16px;border-radius:50%;border:1px solid #374151;object-fit:cover;vertical-align:middle;margin-left:${marginLeft};" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%236b7280%22><circle cx=%2212%22 cy=%2212%22 r=%2210%22/></svg>'">`;
        }).join('');
        sourcesStackHtml = `<div class="sources-stack" style="display:flex;gap:8px;align-items:center;margin-right:10px;">
          <div style="display:flex;">${sourceIcons}</div>
          <span style="font-size:0.75rem;color:#9ca3af;">${savedCitations.length} source${savedCitations.length > 1 ? 's' : ''}</span>
        </div>`;
      }
      
      assistantRow.innerHTML = `
        ${searchTaskHtml}
        ${reasoningHtml}
        ${citationsHtml}
        <div class="card-response-label response-label">Response</div>
        <div class="card-response prose">${responseContent}</div>
        <div class="card-footer flex items-center gap-2" style="justify-content: space-between; padding: 12px 16px;">
          <div class="flex items-center gap-1">
            <!-- Version Navigation (shown when multiple versions exist) -->
            <div class="version-nav ${(assistantMsg?.versions?.length > 1) ? '' : 'hidden'}" style="display:${(assistantMsg?.versions?.length > 1) ? 'inline-flex' : 'none'};align-items:center;gap:2px;margin-right:8px;">
              <button class="action-btn version-prev-btn" title="Previous response">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
              </button>
              <span class="version-indicator" style="font-size:0.8125rem;color:#9ca3af;min-width:32px;text-align:center;">${assistantMsg?.currentVersion || 1}/${assistantMsg?.versions?.length || 1}</span>
              <button class="action-btn version-next-btn" title="Next response">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
              </button>
            </div>
            <button class="action-btn card-copy-btn" title="Copy"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg></button>
            <button class="action-btn card-like-btn" title="Good"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/></svg></button>
            <button class="action-btn card-dislike-btn" title="Bad"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14H5.236a2 2 0 01-1.789-2.894l3.5-7A2 2 0 018.736 3h4.018c.163 0 .326.02.485.06L17 4m-7 10v5a2 2 0 002 2h.095c.5 0 .905-.405.905-.905 0-.714.211-1.412.608-2.006L17 13V4m-7 10h2m5-10h2a2 2 0 012 2v6a2 2 0 01-2 2h-2.5"/></svg></button>
            <button class="action-btn card-share-btn" title="Share"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg></button>
            <button class="action-btn card-regen-btn" title="Regenerate"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg></button>
            <div class="relative">
              <button class="action-btn card-more-btn" title="More actions"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/></svg></button>
              <div class="dropdown-menu hidden card-more-menu">
                <div class="dropdown-item card-menu-branch"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>Branch new</div>
                <div class="dropdown-item card-menu-read"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>Read aloud</div>
                <div class="dropdown-item card-menu-report"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/></svg>Report message</div>
              </div>
            </div>
          </div>
          ${sourcesStackHtml}
        </div>
      `;
      
      // Show actions on hover
      assistantRow.addEventListener('mouseenter', () => {
        const actions = assistantRow.querySelector('.messenger-actions');
        if (actions) actions.style.opacity = '1';
      });
      
      wrapper.appendChild(assistantRow);
      
      // Add event listeners for reasoning toggle (uses existing card-reasoning-toggle)
      const reasoningToggle = assistantRow.querySelector('.card-reasoning-toggle');
      const reasoningChevron = reasoningToggle?.querySelector('.reasoning-chevron');
      const reasoningContent = assistantRow.querySelector('.card-reasoning-content');
      reasoningToggle?.addEventListener('click', (e) => {
        e.stopPropagation();
        if (reasoningChevron) {
          reasoningChevron.classList.toggle('open');
        }
        if (reasoningContent) {
          reasoningContent.classList.toggle('hidden');
        }
      });
      
      // Add event listener for search task toggle
      const searchToggle = assistantRow.querySelector('.card-search-toggle');
      const searchChevron = searchToggle?.querySelector('.search-chevron');
      const searchContent = assistantRow.querySelector('.card-search-content');
      searchToggle?.addEventListener('click', (e) => {
        e.stopPropagation();
        if (searchChevron) {
          searchChevron.style.transform = searchChevron.style.transform === 'rotate(-90deg)' ? '' : 'rotate(-90deg)';
        }
        if (searchContent) {
          searchContent.classList.toggle('hidden');
        }
      });
      
      // Copy button
      const copyBtn = assistantRow.querySelector('.card-copy-btn');
      const responseEl = assistantRow.querySelector('.card-response');
      copyBtn?.addEventListener('click', async (e) => {
        e.stopPropagation();
        const textToCopy = extractTextForCopy(responseEl);
        const success = await copyToClipboard(textToCopy || '');
        if (success) {
          copyBtn.innerHTML = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
          setTimeout(() => {
            copyBtn.innerHTML = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>';
          }, 2000);
        }
      });
      
      // Like button
      const likeBtn = assistantRow.querySelector('.card-like-btn');
      likeBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        likeBtn.classList.toggle('active');
        // Remove dislike active if like is clicked
        const dislikeBtn = assistantRow.querySelector('.card-dislike-btn');
        dislikeBtn?.classList.remove('active');
      });
      
      // Dislike button
      const dislikeBtn = assistantRow.querySelector('.card-dislike-btn');
      dislikeBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        dislikeBtn.classList.toggle('active');
        // Remove like active if dislike is clicked
        likeBtn?.classList.remove('active');
      });
      
      // Share button
      const shareBtn = assistantRow.querySelector('.card-share-btn');
      shareBtn?.addEventListener('click', async (e) => {
        e.stopPropagation();
        const payload = extractTextForCopy(responseEl) || '';
        if (navigator.share) {
          navigator.share({ text: payload }).catch(() => {});
        } else {
          await copyToClipboard(payload);
        }
      });

      // Branch new
      const branchItem = assistantRow.querySelector('.card-menu-branch');
      branchItem?.addEventListener('click', (e) => {
        e.stopPropagation();
        moreMenu?.classList.add('hidden');
        // Branch functionality - start new chat with this context
        const text = extractTextForCopy(responseEl) || '';
        if (text) {
          promptEl.value = `Continue from: "${text.substring(0, 200)}..."`;
          promptEl.focus();
        }
      });

      // Read aloud
      const readItem = assistantRow.querySelector('.card-menu-read');
      readItem?.addEventListener('click', (e) => {
        e.stopPropagation();
        moreMenu?.classList.add('hidden');
        const ttsText = extractTextForTTS(responseEl);
        if (ttsText && 'speechSynthesis' in window) {
          const utterance = new SpeechSynthesisUtterance(ttsText);
          window.speechSynthesis.speak(utterance);
        }
      });
      
      // Regenerate button - now stores alternative versions
      const regenBtn = assistantRow.querySelector('.card-regen-btn');
      regenBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        if (userMsg?.content) {
          // Mark this as a regeneration request to store alternative response
          window._regeneratingPromptId = userMsg.id || userMsg.ts;
          promptEl.value = userMsg.content;
          sendBtn?.click();
        }
      });
      
      // Version navigation buttons
      const versionNav = assistantRow.querySelector('.version-nav');
      const versionPrevBtn = assistantRow.querySelector('.version-prev-btn');
      const versionNextBtn = assistantRow.querySelector('.version-next-btn');
      const versionIndicator = assistantRow.querySelector('.version-indicator');
      
      if (assistantMsg?.versions && assistantMsg.versions.length > 1) {
        let currentIdx = (assistantMsg.currentVersion || 1) - 1;
        
        const updateVersionDisplay = (newIdx) => {
          if (newIdx < 0 || newIdx >= assistantMsg.versions.length) return;
          currentIdx = newIdx;
          const version = assistantMsg.versions[currentIdx];
          
          // Update the response content
          if (responseEl) {
            let newContent = '';
            if (version.content) {
              newContent = simpleMarkdownToHtml(version.content);
            } else if (version.html) {
              newContent = ensureCodeBlockAttributes(version.html);
            }
            responseEl.innerHTML = newContent;
            renderLatexInElement(responseEl);
            enhanceCodeBlocks(wrapper);
          }
          
          // Update version indicator
          if (versionIndicator) {
            versionIndicator.textContent = `${currentIdx + 1}/${assistantMsg.versions.length}`;
          }
          
          // Update stored current version
          assistantMsg.currentVersion = currentIdx + 1;
          
          // Sync to storage
          syncCurrentConvo();
        };
        
        versionPrevBtn?.addEventListener('click', (e) => {
          e.stopPropagation();
          if (currentIdx > 0) {
            updateVersionDisplay(currentIdx - 1);
          }
        });
        
        versionNextBtn?.addEventListener('click', (e) => {
          e.stopPropagation();
          if (currentIdx < assistantMsg.versions.length - 1) {
            updateVersionDisplay(currentIdx + 1);
          }
        });
      }
      
      // More menu toggle
      const moreBtn = assistantRow.querySelector('.card-more-btn');
      const moreMenu = assistantRow.querySelector('.card-more-menu');
      moreBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        moreMenu?.classList.toggle('hidden');
      });
      
      // Close menu when clicking outside
      document.addEventListener('click', () => {
        moreMenu?.classList.add('hidden');
      });
      

      
      // Report message
      const reportItem = assistantRow.querySelector('.card-menu-report');
      reportItem?.addEventListener('click', (e) => {
        e.stopPropagation();
        moreMenu?.classList.add('hidden');
        alert('Thank you for your feedback. This message has been reported.');
      });
    }
    
    // Append to container
    container.appendChild(wrapper);
    
    // Render LaTeX math expressions with KaTeX
    const latexTarget = wrapper.querySelector('.card-response');
    if (latexTarget) {
      renderLatexInElement(latexTarget);
    }
    
    enhanceCodeBlocks(wrapper);
    
    // Initialize sticky buttons for this card
    setTimeout(() => initStickyCodeButtons(), 50);
  }

  // Append transcription text into the composer prompt
  function appendToComposer(txt) {
    try {
      if (!promptEl) return;
      const t = (txt || '').toString();
      if (!t) return;
      const cur = (promptEl.value || '').trim();
      promptEl.value = (cur ? (cur + ' ' + t) : t);
      try { promptEl.focus(); } catch (e) {}
    } catch (e) { console.error('appendToComposer error', e); }
  }

  // Listen for transcripts posted from a helper /transcribe test window
  try {
    window.addEventListener('message', (ev) => {
      try {
        if (!ev || !ev.data) return;
        const d = ev.data;
        if (d && d.type === 'ginto_transcript' && typeof d.text === 'string') {
          appendToComposer(d.text);
          try { promptEl.focus(); } catch (e) {}
        }
      } catch (e) { console.debug('postMessage handler error', e); }
    }, false);
  } catch (e) { console.debug('message listener install failed', e); }

  // --- TTS debug overlay removed ---
  // Previously a floating debug box was created here. For production and
  // cleaner UI we no longer create DOM debug elements. Provide a no-op
  // `updateTtsDebug` so other modules can still call it safely.
  try { window.updateTtsDebug = function() {}; } catch (e) { /* ignore */ }

  // Extract text from element for TTS, excluding code blocks, tables, and pre elements
  function extractTextForTTS(element) {
    if (!element) return '';
    
    // Clone the element so we don't modify the original
    const clone = element.cloneNode(true);
    
    // Remove elements that shouldn't be read aloud
    const selectorsToRemove = [
      'pre',           // Code blocks
      'code',          // Inline code
      'table',         // Tables
      '.code-block',   // Custom code block class
      '.code-header',  // Code block headers
      '.line-numbers', // Line numbers
      '[data-code]',   // Any element with data-code attribute
      '.hljs'          // Highlighted code
    ];
    
    selectorsToRemove.forEach(selector => {
      clone.querySelectorAll(selector).forEach(el => el.remove());
    });
    
    // Get the text content
    let text = clone.innerText || clone.textContent || '';
    
    // Clean up the text
    return sanitizeForTTS(text);
  }

  // Extract sanitized text for copying to clipboard
  // - Strips UI elements (headers/buttons/line-numbers)
  // - For code blocks, extracts the raw code (prefers CodeMirror or data attributes)
  // - Normalizes whitespace and removes incidental labels like "Save"/"Copy"
  function extractTextForCopy(element) {
    if (!element) return '';

    const clone = element.cloneNode(true);

    // Remove UI/buttons and interactive elements
    const uiSelectors = [
      'button',
      '.message-actions',
      '.user-message-actions',
      '.code-block-header',
      '.code-header-buttons',
      '.code-action-btn',
      '.card-footer',
      '.card-copy-btn',
      '.card-more-btn',
      '.card-more-menu',
      '.version-nav',
      '.version-prev-btn',
      '.version-next-btn',
      '.card-regen-btn',
      '.card-like-btn',
      '.card-dislike-btn',
      '.card-share-btn',
      '.card-menu-*'
    ];
    uiSelectors.forEach(sel => {
      try { clone.querySelectorAll(sel).forEach(el => el.remove()); } catch (e) {}
    });

    // Replace each code-block-wrapper in the clone with plain code text
    const origCodeBlocks = Array.from(element.querySelectorAll('.code-block-wrapper'));
    const cloneCodeBlocks = Array.from(clone.querySelectorAll('.code-block-wrapper'));

    for (let i = 0; i < cloneCodeBlocks.length; i++) {
      const cClone = cloneCodeBlocks[i];
      const orig = origCodeBlocks[i];
      let codeText = '';

      if (orig) {
        if (orig.cmInstance && typeof orig.cmInstance.getValue === 'function') {
          try { codeText = orig.cmInstance.getValue(); } catch (e) { codeText = ''; }
        }
        if (!codeText && orig.dataset.codeB64) {
          try { codeText = decodeURIComponent(escape(atob(orig.dataset.codeB64))); } catch (e) { codeText = ''; }
        }
        if (!codeText && orig.dataset.code) {
          codeText = orig.dataset.code.replace(/\\n/g, '\n');
        }
        if (!codeText) {
          const ta = orig.querySelector('textarea');
          if (ta) codeText = ta.value || ta.textContent || '';
        }
        if (!codeText) {
          const pre = orig.querySelector('pre');
          if (pre) codeText = pre.textContent || pre.innerText || '';
        }
      }

      // Create a plain text node with the code and replace the clone's wrapper
      const pre = document.createElement('pre');
      pre.textContent = (codeText || '').trim();
      cClone.parentNode.replaceChild(pre, cClone);
    }

    // Remove stray elements that may include labels like "Save"/"Copy" or line numbers
    ['.line-numbers', '.code-block-header', '.code-header-buttons', '.message-actions', '.user-message-actions'].forEach(s => {
      clone.querySelectorAll(s).forEach(el => el.remove());
    });

    // Get the text
    let text = clone.innerText || clone.textContent || '';

    // Remove isolated markdown-like labels (e.g., leading "code", "Save", "Copy") that may appear
    text = text.replace(/^\s*(code|save|copy)\s*$/gmi, '');
    // Remove standalone line numbers
    text = text.split('\n').map(l => l.trim()).filter(l => !/^[0-9]+$/.test(l)).join('\n');

    // Normalize whitespace and trim
    text = text.replace(/\s+\n/g, '\n').replace(/\n\s+/g, '\n').replace(/[ \t]{2,}/g, ' ').replace(/\n{3,}/g, '\n\n').trim();

    return text;
  }

  // Sanitize text for TTS - remove markdown, code, tables and markers that shouldn't be spoken
  function sanitizeForTTS(text) {
    if (!text) return '';
    let clean = text;
    // Remove citation markers like 【2†L31-L38】 or [1], [2], etc.
    clean = clean.replace(/【[^】]*】/g, '');
    clean = clean.replace(/\[\d+\]/g, '');
    // Remove markdown code blocks (fenced with ```)
    clean = clean.replace(/```[\s\S]*?```/g, ' ');
    // Remove inline code
    clean = clean.replace(/`[^`]+`/g, ' ');
    // Remove markdown tables (lines with pipes)
    clean = clean.replace(/^\|.*\|$/gm, '');
    clean = clean.replace(/^[\s]*[-|:]+[\s]*$/gm, ''); // table separator lines
    // Remove HTML tables
    clean = clean.replace(/<table[\s\S]*?<\/table>/gi, '');
    // Remove markdown bold/italic
    clean = clean.replace(/\*\*([^*]+)\*\*/g, '$1');
    clean = clean.replace(/\*([^*]+)\*/g, '$1');
    clean = clean.replace(/__([^_]+)__/g, '$1');
    clean = clean.replace(/_([^_]+)_/g, '$1');
    // Remove markdown headers
    clean = clean.replace(/^#{1,6}\s+/gm, '');
    // Remove markdown links, keep text
    clean = clean.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1');
    // Remove URLs
    clean = clean.replace(/https?:\/\/[^\s]+/g, '');
    // Remove HTML tags
    clean = clean.replace(/<[^>]+>/g, ' ');
    // Remove special chars that sound weird
    clean = clean.replace(/[•→←↑↓…—–""'']/g, ' ');
    // Collapse whitespace
    clean = clean.replace(/\s+/g, ' ').trim();
    return clean;
  }

  // ============ WEBSEARCH-STYLE STREAMING IMPLEMENTATION ============
  
  let abortController = null;
  let currentCard = null;

  // Get or create conversation card container
  function getConvoContainer() {
    // Hide welcome screen when conversation starts
    const hintEl = messagesEl.querySelector('.bg-hint');
    if (hintEl) hintEl.style.display = 'none';
    
    let container = document.getElementById('convoContainer');
    if (!container) {
      container = document.createElement('div');
      container.id = 'convoContainer';
      container.className = 'convo-history';
      container.style.cssText = 'display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1rem;';
      // Append the convo container to the bottom (end) of the messages list
      // so the newest conversation messages appear beneath earlier content.
      messagesEl.appendChild(container);
    }
    return container;
  }

  // Detect query type for icon
  function getQueryType(query) {
    const q = query.toLowerCase();
    if (q.includes('weather') || q.includes('temperature') || q.includes('forecast')) return 'weather';
    if (q.includes('news') || q.includes('latest') || q.includes('breaking')) return 'news';
    if (q.includes('search') || q.includes('find') || q.includes('look up')) return 'search';
    return 'general';
  }

  // Get icon SVG for query type
  function getQueryIcon(type) {
    const icons = {
      weather: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>',
      news: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>',
      search: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>',
      general: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'
    };
    return icons[type] || icons.general;
  }

  function formatReasoningText(text) {
    // Each logical reasoning step/paragraph gets its own row with dot + line (Groq style)
    
    if (!text || !text.trim()) {
      return '';
    }
    
    // Filter out raw JSON tool calls that shouldn't be displayed
    let cleanedText = text;
    // Remove JSON objects that look like tool calls
    cleanedText = cleanedText.replace(/\{"query"\s*:[^}]+\}/g, '');
    cleanedText = cleanedText.replace(/\{"tool_call"\s*:[^}]+\}/g, '');
    cleanedText = cleanedText.replace(/\{"tool"\s*:[^}]+\}/g, '');
    cleanedText = cleanedText.replace(/\{"browser_search"\s*:[^}]+\}/g, '');
    // Remove any standalone JSON-like objects
    cleanedText = cleanedText.replace(/^\s*\{[^}]{10,200}\}\s*$/gm, '');
    // Clean up extra whitespace
    cleanedText = cleanedText.replace(/\n{3,}/g, '\n\n').trim();
    
    if (!cleanedText) {
      return '';
    }
    
    // Escape HTML to prevent XSS, then apply basic markdown
    const escapeHtmlText = (str) => {
      // First escape HTML
      let escaped = str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
      
      // Then apply basic markdown formatting
      // Bold: **text** or __text__
      escaped = escaped.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
      escaped = escaped.replace(/__([^_]+)__/g, '<strong>$1</strong>');
      // Italic: *text* or _text_
      escaped = escaped.replace(/\*([^*]+)\*/g, '<em>$1</em>');
      escaped = escaped.replace(/(?<![a-zA-Z0-9])_([^_]+)_(?![a-zA-Z0-9])/g, '<em>$1</em>');
      // Inline code: `code`
      escaped = escaped.replace(/`([^`]+)`/g, '<code style="background:rgba(255,255,255,0.1);padding:2px 4px;border-radius:3px;font-size:0.9em;">$1</code>');
      
      return escaped;
    };
    
    // Helper to create a reasoning item with Groq-style structure
    const createReasoningItem = (content, isLast = false) => `<div class="reasoning-item${isLast ? ' reasoning-item-last' : ''}">
      <div class="reasoning-item-indicator">
        <div class="reasoning-item-dot${isLast ? ' reasoning-item-dot-green' : ''}"></div>
        <div class="reasoning-item-line"></div>
      </div>
      <div class="reasoning-item-text"><p>${escapeHtmlText(content)}</p></div>
    </div>`;
    
    // Helper to map items with last-item flag
    const mapWithLast = (items) => items.map((item, i) => createReasoningItem(item, i === items.length - 1)).join('');
    
    // First, try to split by double newlines (explicit paragraphs from the model)
    let paragraphs = cleanedText.split(/\n\n+/).map(p => p.trim()).filter(p => p);
    
    // If we got multiple paragraphs, use them directly - each gets its own dot
    if (paragraphs.length > 1) {
      return mapWithLast(paragraphs.map(p => p.replace(/\n/g, ' ')));
    }
    
    // Otherwise, split by single newlines - each line is a step
    paragraphs = text.split(/\n/).map(p => p.trim()).filter(p => p);
    if (paragraphs.length > 1) {
      return mapWithLast(paragraphs);
    }
    
    // No newlines - split by sentence boundaries that indicate new reasoning steps
    const normalized = text.replace(/\s+/g, ' ').trim();
    
    // Split on sentence endings followed by common reasoning starters
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
    
    // Single paragraph fallback - single item is also the "last" item, so green dot
    return createReasoningItem(normalized, true);
  }

  // Create a new conversation card - returns references to its internal elements
  function createConversationCard(query, type) {
    const convoContainer = getConvoContainer();
    const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    
    // Create messenger-style wrapper
    const card = document.createElement('div');
    card.className = 'messenger-pair';
    card.innerHTML = `
      <!-- User Message (right-aligned, blue bubble) -->
      <div class="messenger-row messenger-row-user" style="display:flex;flex-direction:column;align-items:flex-end;width:100%;margin-bottom:8px;">
        <div class="card-user-image hidden" style="max-width:70%;margin-bottom:8px;"></div>
        <div class="messenger-bubble messenger-bubble-user">
          <div class="user-message-content" style="white-space:pre-wrap;">${escapeHtml(query)}</div>
          <div class="user-message-edit-area hidden">
            <textarea class="user-edit-textarea"></textarea>
            <div class="user-edit-buttons">
              <button class="user-edit-cancel-btn">Cancel</button>
              <button class="user-edit-save-btn">Save & Send</button>
            </div>
          </div>
        </div>
        <div class="user-message-actions">
          <button class="user-action-btn user-copy-btn" title="Copy">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
          </button>
          <button class="user-action-btn user-edit-btn" title="Edit">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
          </button>
          <button class="user-action-btn user-regen-btn" title="Regenerate">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          </button>
        </div>
      </div>
      
      <!-- Assistant Response (left-aligned, using convo-card-body styling) -->
      <div class="convo-card-body">
        <!-- Search Task Section (Activity Stream) -->
        <div class="card-search-task hidden mb-3">
          <div class="reasoning-timeline">
            <div class="reasoning-header card-search-toggle">
              <svg class="reasoning-chevron search-chevron open" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
              </svg>
              <span style="font-size:12px;">🐼</span>
              <svg class="search-spinner" style="width:18px;height:18px;color:#a855f7;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
              </svg>
              <span class="card-search-status">Search Task</span>
            </div>
            <div class="card-search-content reasoning-content modern-scroll" style="max-height: 200px;"></div>
          </div>
        </div>
        
        <!-- Activity Section (legacy - hidden, keeping for compatibility) -->
        <div class="card-activity hidden mb-3">
          <div class="flex items-center gap-2 text-indigo-400 text-sm mb-2">
            <span class="panda-icon">🐼</span>
            <svg class="w-4 h-4 activity-spinner" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            <span class="card-activity-text">Searching the web...</span>
          </div>
          <div class="card-sites-list flex flex-wrap gap-1"></div>
        </div>
        
        <!-- Reasoning Section -->
        <div class="card-reasoning hidden mb-3">
          <div class="reasoning-timeline">
            <div class="reasoning-header card-reasoning-toggle">
              <svg class="reasoning-chevron open" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
              </svg>
              <span>Reasoning</span>
            </div>
            <div class="card-reasoning-content reasoning-content modern-scroll"></div>
          </div>
        </div>
        
        <!-- Citations Section -->
        <div class="card-citations hidden mb-3" style="margin-left:10px;">
          <div class="flex flex-wrap gap-2 card-citations-list"></div>
        </div>
        
        <!-- Response Section -->
        <div class="card-response-label response-label hidden">Response</div>
        <div class="card-response prose">
          <p class="text-gray-500 dark:text-gray-400 thinking-indicator-wrapper" style="display:flex;align-items:center;"><span class="dancing-dots"><span>•</span><span>•</span><span>•</span></span></p>
        </div>
        
        <!-- Footer Actions -->
        <div class="card-footer hidden flex items-center gap-2" style="justify-content: space-between; padding: 8px 0 4px 6px;">
          <div class="flex items-center gap-0.5">
            <button class="action-btn card-copy-btn" title="Copy"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg></button>
            <button class="action-btn card-like-btn" title="Good"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/></svg></button>
            <button class="action-btn card-dislike-btn" title="Bad"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14H5.236a2 2 0 01-1.789-2.894l3.5-7A2 2 0 018.736 3h4.018c.163 0 .326.02.485.06L17 4m-7 10v5a2 2 0 002 2h.095c.5 0 .905-.405.905-.905 0-.714.211-1.412.608-2.006L17 13V4m-7 10h2m5-10h2a2 2 0 012 2v6a2 2 0 01-2 2h-2.5"/></svg></button>
            <button class="action-btn card-share-btn" title="Share"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg></button>
            <button class="action-btn card-regen-btn" title="Regenerate"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg></button>
            <div class="relative">
              <button class="action-btn card-more-btn" title="More actions"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/></svg></button>
              <div class="dropdown-menu hidden card-more-menu">
                <div class="dropdown-item card-menu-branch"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>Branch new</div>
                <div class="dropdown-item card-menu-read"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>Read aloud</div>
                <div class="dropdown-item card-menu-report"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/></svg>Report message</div>
              </div>
            </div>
          </div>
          <!-- Sources Stack (favicon display) - right aligned -->
          <div class="card-sources-stack hidden" style="display:flex;align-items:center;gap:8px;margin-right:10px;">
            <div style="display:flex;align-items:center;" class="card-sources-icons"></div>
            <span style="font-size:14px;color:#9ca3af;" class="card-sources"></span>
          </div>
        </div>
      </div>
    `;
    
    // Get references to internal elements
    const elements = {
      card,
      userRow: card.querySelector('.messenger-row-user'),
      userBubble: card.querySelector('.messenger-bubble-user'),
      body: card.querySelector('.convo-card-body'),
      userImage: card.querySelector('.card-user-image'),
      // User action buttons
      userCopyBtn: card.querySelector('.user-copy-btn'),
      userEditBtn: card.querySelector('.user-edit-btn'),
      userRegenBtn: card.querySelector('.user-regen-btn'),
      // Search Task section (new activity stream)
      searchTask: card.querySelector('.card-search-task'),
      searchToggle: card.querySelector('.card-search-toggle'),
      searchChevron: card.querySelector('.search-chevron'),
      searchSpinner: card.querySelector('.search-spinner'),
      searchStatus: card.querySelector('.card-search-status'),
      searchContent: card.querySelector('.card-search-content'),
      // Legacy activity section
      activity: card.querySelector('.card-activity'),
      activityText: card.querySelector('.card-activity-text'),
      activitySpinner: card.querySelector('.card-activity .activity-spinner'),
      sitesList: card.querySelector('.card-sites-list'),
      reasoning: card.querySelector('.card-reasoning'),
      reasoningToggle: card.querySelector('.card-reasoning-toggle'),
      reasoningChevron: card.querySelector('.card-reasoning .reasoning-chevron'),
      reasoningContent: card.querySelector('.card-reasoning-content'),
      citations: card.querySelector('.card-citations'),
      citationsList: card.querySelector('.card-citations-list'),
      responseLabel: card.querySelector('.card-response-label'),
      response: card.querySelector('.card-response'),
      footer: card.querySelector('.card-footer'),
      sourcesStack: card.querySelector('.card-sources-stack'),
      sourcesIcons: card.querySelector('.card-sources-icons'),
      sourcesSpan: card.querySelector('.card-sources'),
      copyBtn: card.querySelector('.card-copy-btn'),
      likeBtn: card.querySelector('.card-like-btn'),
      dislikeBtn: card.querySelector('.card-dislike-btn'),
      shareBtn: card.querySelector('.card-share-btn'),
      regenBtn: card.querySelector('.card-regen-btn'),
      moreBtn: card.querySelector('.card-more-btn'),
      moreDropdown: card.querySelector('.card-more-menu'),
      menuBranch: card.querySelector('.card-menu-branch'),
      menuRead: card.querySelector('.card-menu-read'),
      menuReport: card.querySelector('.card-menu-report'),
    };
    
    // Search Task toggle
    if (elements.searchToggle) {
      elements.searchToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = elements.searchChevron.classList.toggle('open');
        elements.searchContent.style.display = isOpen ? 'block' : 'none';
      });
    }
    
    // User action buttons
    if (elements.userCopyBtn) {
      elements.userCopyBtn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const success = await copyToClipboard(query);
        if (success) {
          elements.userCopyBtn.innerHTML = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
          setTimeout(() => {
            elements.userCopyBtn.innerHTML = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>';
          }, 2000);
        }
      });
    }
    
    if (elements.userEditBtn) {
      const userMsgContent = elements.userBubble?.querySelector('.user-message-content');
      const editArea = elements.userBubble?.querySelector('.user-message-edit-area');
      const editTextarea = elements.userBubble?.querySelector('.user-edit-textarea');
      const editCancelBtn = elements.userBubble?.querySelector('.user-edit-cancel-btn');
      const editSaveBtn = elements.userBubble?.querySelector('.user-edit-save-btn');
      
      elements.userEditBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (userMsgContent && editArea && editTextarea) {
          editTextarea.value = query;
          userMsgContent.classList.add('hidden');
          editArea.classList.remove('hidden');
          editTextarea.focus();
          editTextarea.setSelectionRange(editTextarea.value.length, editTextarea.value.length);
        }
      });
      
      // Cancel edit
      editCancelBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        if (userMsgContent && editArea) {
          editArea.classList.add('hidden');
          userMsgContent.classList.remove('hidden');
        }
      });
      
      // Save & Send edited message
      editSaveBtn?.addEventListener('click', (e) => {
        e.stopPropagation();
        const newQuery = editTextarea?.value?.trim();
        if (newQuery && promptEl && sendBtn) {
          editArea.classList.add('hidden');
          userMsgContent.classList.remove('hidden');
          // Update the displayed message
          userMsgContent.textContent = newQuery;
          // Mark as regeneration to store alternative
          const lastUserMsg = history.filter(m => m.role === 'user' && m.content === query).pop();
          window._regeneratingPromptId = lastUserMsg?.id || lastUserMsg?.ts || Date.now();
          window._editedPromptText = newQuery;
          promptEl.value = newQuery;
          sendBtn.click();
        }
      });
    }
    
    if (elements.userRegenBtn) {
      elements.userRegenBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (query && promptEl && sendBtn) {
          // Find the user message ID from history for regeneration tracking
          const lastUserMsg = history.filter(m => m.role === 'user' && m.content === query).pop();
          window._regeneratingPromptId = lastUserMsg?.id || lastUserMsg?.ts || Date.now();
          promptEl.value = query;
          sendBtn.click();
        }
      });
    }
    
    // Reasoning toggle
    elements.reasoningToggle.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = elements.reasoningChevron.classList.toggle('open');
      elements.reasoningContent.style.display = isOpen ? 'block' : 'none';
    });
    
    // Copy button
    elements.copyBtn?.addEventListener('click', async (e) => {
      e.stopPropagation();
      const success = await copyToClipboard(elements.response.innerText);
      if (success) {
        elements.copyBtn.innerHTML = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
        setTimeout(() => {
          elements.copyBtn.innerHTML = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>';
        }, 2000);
      }
    });
    
    // Like/Dislike
    elements.likeBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      elements.likeBtn.classList.toggle('active');
      elements.dislikeBtn?.classList.remove('active');
    });
    elements.dislikeBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      elements.dislikeBtn.classList.toggle('active');
      elements.likeBtn?.classList.remove('active');
    });
    
    // Regenerate
    elements.regenBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      promptEl.value = query;
      sendBtn.click();
    });
    
    // Share button
    elements.shareBtn?.addEventListener('click', async (e) => {
      e.stopPropagation();
      const shareData = {
        title: 'Chat Response',
        text: elements.response.innerText.slice(0, 200) + '...',
        url: window.location.href
      };
      if (navigator.share) {
        try {
          await navigator.share(shareData);
        } catch (err) {}
      } else {
        const success = await copyToClipboard(window.location.href);
        if (success) {
          elements.shareBtn.innerHTML = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
          setTimeout(() => {
            elements.shareBtn.innerHTML = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>';
          }, 2000);
        }
      }
    });
    
    // More dropdown
    elements.moreBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = !elements.moreDropdown?.classList.contains('hidden');
      
      // Close all other dropdowns first
      document.querySelectorAll('.card-more-menu').forEach(d => d.classList.add('hidden'));
      
      if (!isOpen) {
        elements.moreDropdown.classList.remove('hidden');
      }
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', () => {
      elements.moreDropdown.classList.add('hidden');
    });
    
    // Dropdown menu items
    elements.menuBranch?.addEventListener('click', (e) => {
      e.stopPropagation();
      elements.moreDropdown.classList.add('hidden');
      // Branch functionality - could open in new tab
      console.log('Branch conversation');
    });
    
    elements.menuRead?.addEventListener('click', (e) => {
      e.stopPropagation();
      elements.moreDropdown.classList.add('hidden');
      // Read aloud functionality
      const text = elements.response.innerText;
      if ('speechSynthesis' in window) {
        const utterance = new SpeechSynthesisUtterance(text);
        speechSynthesis.speak(utterance);
      }
    });
    
    elements.menuReport?.addEventListener('click', (e) => {
      e.stopPropagation();
      elements.moreDropdown.classList.add('hidden');
      console.log('Report response');
    });
    
    // Sources stack click (if element exists)
    elements.sourcesStack?.addEventListener('click', (e) => {
      e.stopPropagation();
      elements.citations.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
    
    convoContainer.appendChild(card);
    // Scroll so the response area is at the top (user prompt above viewport)
    setTimeout(() => {
      elements.body.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 100);
    return elements;
  }

  function updateCardActivity(card, activities) {
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

  function finishCardActivity(card, activities) {
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

  function updateCardCitations(card, citations) {
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

  // Helper to strip tool_call JSON from content for display
  function stripToolCallJson(text) {
    if (!text) return text;
    let cleaned = text;
    
    // Most aggressive pattern first: any JSON object starting with {"query"
    // This catches all browser_search variations regardless of other fields
    cleaned = cleaned.replace(/\{"query"\s*:[^}]+\}/g, '');
    
    // Strip web search response JSON: {"response": [{"title": ...}], "status": "success"}
    // This can span multiple lines and contains nested objects
    cleaned = cleaned.replace(/\{"response"\s*:\s*\[[\s\S]*?\]\s*,\s*"status"\s*:\s*"[^"]*"\s*\}/g, '');
    
    // Also strip simpler response patterns
    cleaned = cleaned.replace(/\{"response"\s*:\s*\[[\s\S]*?\]\s*\}/g, '');
    
    // Standard tool_call patterns
    cleaned = cleaned.replace(/\{"tool_call"\s*:\s*\{[\s\S]*?\}\s*\}/g, '');
    cleaned = cleaned.replace(/```json\s*\{"tool_call"[\s\S]*?\}\s*```/g, '');
    cleaned = cleaned.replace(/\{["\s]*tool_call["\s]*:[\s\S]*?\}\s*\}/g, '');
    
    // Search/browse markers that shouldn't display
    cleaned = cleaned.replace(/Search web\.\{[^}]+\}\.{0,3}/gi, '');
    cleaned = cleaned.replace(/Browse web\.\{[^}]+\}\.{0,3}/gi, '');
    cleaned = cleaned.replace(/Let's search[^.]*\./gi, '');
    cleaned = cleaned.replace(/We need to browse[^.]*\./gi, '');
    
    // Strip image markdown - UI shows images from tool results, don't render duplicates
    // Matches ![alt text](url) and ![](url)
    cleaned = cleaned.replace(/!\[[^\]]*\]\([^)]+\)/g, '');
    
    // Clean up leftover whitespace and punctuation artifacts
    // BUT preserve newlines for tables and markdown formatting
    cleaned = cleaned.replace(/\n{3,}/g, '\n\n');
    cleaned = cleaned.replace(/\.{2,}/g, '.');
    // Only collapse multiple spaces on the same line, not newlines
    cleaned = cleaned.replace(/[^\S\n]{2,}/g, ' ');
    cleaned = cleaned.replace(/\s+\./g, '.');
    return cleaned.trim();
  }

  function parseOpenAiContentParts(content) {
    const out = { text: '', images: [] };
    if (typeof content === 'string') {
      out.text = content;
      return out;
    }
    if (!Array.isArray(content)) return out;

    for (const block of content) {
      if (typeof block === 'string') {
        if (block) out.text += block;
        continue;
      }
      if (!block || typeof block !== 'object') continue;

      const type = String(block.type || '').toLowerCase();
      if (type === 'text' || type === 'input_text' || type === 'output_text' || type === '') {
        const text =
          (typeof block.text === 'string' && block.text) ||
          (typeof block.output_text === 'string' && block.output_text) ||
          (typeof block.content === 'string' && block.content) ||
          '';
        if (text) out.text += text;
        continue;
      }

      if (type === 'image_url') {
        const imageUrlRaw =
          (typeof block.image_url?.url === 'string' && block.image_url.url) ||
          (typeof block.url === 'string' && block.url) ||
          '';
        const imageUrl = normalizeImageResponseUrl(imageUrlRaw);
        if (imageUrl) out.images.push(imageUrl);
      }
    }

    return out;
  }

  function mergeOpenAiContentParts(target, source) {
    if (!target || !source) return target;
    if (source.text) target.text += source.text;
    if (Array.isArray(source.images) && source.images.length > 0) {
      target.images.push(...source.images);
    }
    return target;
  }

  function extractOpenAiContentPartsFromPayload(data) {
    const out = { text: '', images: [] };

    if (!data) return out;

    // Direct array payload (OpenAI content blocks)
    if (Array.isArray(data)) {
      return parseOpenAiContentParts(data);
    }

    if (typeof data !== 'object') return out;

    // OpenAI-compatible priority
    mergeOpenAiContentParts(out, parseOpenAiContentParts(data?.choices?.[0]?.delta?.content));
    mergeOpenAiContentParts(out, parseOpenAiContentParts(data?.choices?.[0]?.message?.content));

    // Generic/common fallbacks
    mergeOpenAiContentParts(out, parseOpenAiContentParts(data?.delta?.content));
    mergeOpenAiContentParts(out, parseOpenAiContentParts(data?.message?.content));
    mergeOpenAiContentParts(out, parseOpenAiContentParts(data?.content));
    mergeOpenAiContentParts(out, parseOpenAiContentParts(data?.reply));
    mergeOpenAiContentParts(out, parseOpenAiContentParts(data?.response));

    // Common single-image fields
    if (typeof data?.imageUrl === 'string' && data.imageUrl) {
      const normalized = normalizeImageResponseUrl(data.imageUrl);
      if (normalized) out.images.push(normalized);
    }
    if (typeof data?.image_url === 'string' && data.image_url) {
      const normalized = normalizeImageResponseUrl(data.image_url);
      if (normalized) out.images.push(normalized);
    }
    if (Array.isArray(data?.images)) {
      for (const img of data.images) {
        if (typeof img === 'string' && img) {
          const normalized = normalizeImageResponseUrl(img);
          if (normalized) out.images.push(normalized);
        }
        else if (img && typeof img === 'object') {
          if (typeof img.url === 'string' && img.url) {
            const normalized = normalizeImageResponseUrl(img.url);
            if (normalized) out.images.push(normalized);
          }
          else if (typeof img.imageUrl === 'string' && img.imageUrl) {
            const normalized = normalizeImageResponseUrl(img.imageUrl);
            if (normalized) out.images.push(normalized);
          }
          else if (typeof img.dataUrl === 'string' && img.dataUrl) {
            const normalized = normalizeImageResponseUrl(img.dataUrl);
            if (normalized) out.images.push(normalized);
          }
        }
      }
    }

    return out;
  }

  const MAX_STREAM_TEXT_CHUNK_CHARS = 12000;
  const MAX_IMAGE_INLINE_DATA_URL_CHARS = 4096;

  function isLikelyImageGenerationPrompt(prompt) {
    if (!prompt || typeof prompt !== 'string') return false;
    const p = prompt.toLowerCase();
    if (/\b(generate|create|make|draw|render|produce)\b[\s\S]{0,40}\b(image|picture|photo|illustration|art|logo|icon|poster|banner|avatar)\b/.test(p)) return true;
    if (/\bimage\b[\s\S]{0,30}\b(of|for|with)\b/.test(p)) return true;
    if (/\bb64_json\b|\bbase64\b/.test(p)) return true;
    return false;
  }

  function shouldPreferNonStreamResponse(prompt, attached) {
    if (attached && attached.attachmentType === 'image') return true;
    return isLikelyImageGenerationPrompt(prompt);
  }

  function looksLikeLargeBase64Chunk(text) {
    if (typeof text !== 'string') return false;
    if (text.length < MAX_STREAM_TEXT_CHUNK_CHARS) return false;

    const compact = text.replace(/\s+/g, '');
    if (compact.length < MAX_STREAM_TEXT_CHUNK_CHARS) return false;

    const base64ish = compact.match(/[A-Za-z0-9+/=]/g);
    const ratio = base64ish ? (base64ish.length / compact.length) : 0;
    return ratio > 0.97;
  }

  function normalizeImageResponseUrl(url) {
    if (typeof url !== 'string') return '';
    const trimmed = url.trim();
    if (!trimmed) return '';

    const lower = trimmed.toLowerCase();
    if (lower.startsWith('data:image/')) {
      if (trimmed.length > MAX_IMAGE_INLINE_DATA_URL_CHARS) {
        console.warn('[chat] Ignoring oversized inline data:image URL in response');
      }
      return '';
    }

    if (
      lower.startsWith('http://') ||
      lower.startsWith('https://') ||
      lower.startsWith('/') ||
      lower.startsWith('./') ||
      lower.startsWith('../')
    ) {
      return trimmed;
    }

    return '';
  }

  function sanitizeOpenAiTextChunk(text) {
    if (typeof text !== 'string' || !text) return '';
    if (looksLikeLargeBase64Chunk(text)) return '';
    return text;
  }

  function mergeAssistantTextAndImagesHtml(text, imageUrls) {
    const safeText = stripToolCallJson(text || '');
    let html = simpleMarkdownToHtml(safeText);

    if (Array.isArray(imageUrls) && imageUrls.length > 0) {
      const safeImageUrls = imageUrls
        .map((url) => normalizeImageResponseUrl(url))
        .filter(Boolean);
      const imagesHtml = safeImageUrls.map((url) => {
        const safeUrl = escapeHtml(url);
        return `<img src="${safeUrl}" alt="Generated image" class="max-w-full md:max-w-md rounded-lg border border-gray-600 cursor-pointer hover:opacity-90 transition-opacity" loading="lazy" onclick="window.showImageModal && window.showImageModal('${safeUrl}')">`;
      }).join('');
      if (imagesHtml) {
        html += `<div class="mt-4 flex flex-wrap gap-3">${imagesHtml}</div>`;
      }
    }

    return html;
  }

  // Websearch-style streaming to /chat endpoint
  async function streamWebSearch(query) {
    // Check install, login and model setup before allowing chat
    if (window.GINTO_SETUP) {
      // No tables (fresh install, empty DB) - show setup modal to go to /live
      if (!window.GINTO_SETUP.hasTables) {
        if (typeof showSetupRequiredModal === 'function') {
          showSetupRequiredModal();
        } else {
          window.location.href = '/live';
        }
        return;
      }
      // Tables exist but not logged in - show login modal (Congrats if no users, Sign In if users exist)
      if (!window.GINTO_SETUP.isLoggedIn) {
        if (typeof showLoginRequiredModal === 'function') {
          showLoginRequiredModal();
        } else {
          window.location.href = '/login';
        }
        return;
      }
      // Logged in but no model configured - show setup modal
      if (!window.GINTO_SETUP.hasModel) {
        if (typeof showSetupRequiredModal === 'function') {
          showSetupRequiredModal();
        } else {
          window.location.href = '/live';
        }
        return;
      }
    }

    const queryType = getQueryType(query);

    // Abort previous request
    if (abortController) abortController.abort();
    abortController = new AbortController();
    
    // Start streaming - reset scroll state
    isStreaming = true;
    userHasScrolledUp = false;
    
    // Check if this is a regeneration request
    const isRegeneration = !!window._regeneratingPromptId;
    const regeneratingPromptId = window._regeneratingPromptId;
    window._regeneratingPromptId = null; // Clear the flag
    
    // Create the card immediately (unless regenerating)
    if (!isRegeneration) {
      currentCard = createConversationCard(query, queryType);
    } else {
      // For regeneration, find the existing card and update its response section
      // Create a new temporary card for streaming, we'll merge the response later
      currentCard = createConversationCard(query, queryType);
    }
    
    // Track state for this card
    let activities = { searches: [], reads: [], analyzing: false, analyzed: false };
    let accumulatedReasoning = '';
    let accumulatedContent = '';
    let lastAssistantHtml = null;
    let lastAssistantContent = '';
    let accumulatedImageUrls = [];
    const seenImageUrls = new Set();
    let citations = [];
    let toolResults = []; // Track tool results for persistence
    let warnedLargeChunkSkipped = false;
    
    // Capture current attachment before clearing
    const attachedImage = currentAttachment;
    clearAttachment(); // Clear UI immediately
    
    // Show attached file in the card if present
    if (attachedImage && currentCard.userImage) {
      if (attachedImage.attachmentType === 'document') {
        // Document attachment
        const ext = attachedImage.filename?.split('.').pop()?.toUpperCase() || 'DOC';
        currentCard.userImage.innerHTML = `
          <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-900/30 dark:to-purple-900/30 rounded-lg border border-indigo-200 dark:border-indigo-700">
            <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-800 rounded-lg flex items-center justify-center flex-shrink-0">
              <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">${attachedImage.filename || 'Document'}</p>
              <p class="text-xs text-indigo-600 dark:text-indigo-400">${ext} • Using document for RAG context</p>
            </div>
          </div>
        `;
        currentCard.userImage.classList.remove('hidden');
      } else {
        // Image attachment (original logic)
        currentCard.userImage.innerHTML = `
          <img src="${attachedImage.dataUrl}" alt="Attached image" class="max-w-xs max-h-48 rounded-lg border border-gray-600 cursor-pointer hover:opacity-90 transition-opacity" onclick="window.showImageModal && window.showImageModal(this.src)">
          <p class="text-xs text-gray-500 mt-1 flex items-center gap-1">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Analyzing with vision model...
          </p>
        `;
        currentCard.userImage.classList.remove('hidden');
      }
    }
    
    // Add user message to history for conversation memory and persist immediately
    // Skip if regenerating - we're using the same user message
    if (!isRegeneration) {
      // If there's an attachment, store it in history too (for context)
      const userMessage = { role: 'user', content: query, ts: Date.now(), id: Date.now() };
      if (attachedImage) {
        if (attachedImage.attachmentType === 'document') {
          // Document attachment
          userMessage.hasDocument = true;
          userMessage.documentId = attachedImage.documentId;
          userMessage.documentFilename = attachedImage.filename;
        } else {
          // Image attachment
          userMessage.hasImage = true;
          userMessage.imageUrl = attachedImage.dataUrl; // Temporarily store base64 for display
          
          // Upload image to server in background for persistence (don't block the request)
          uploadImageToServer(attachedImage.dataUrl).then(serverUrl => {
            if (serverUrl) {
              // Update the message in history with server URL instead of base64
              userMessage.imageUrl = serverUrl;
              // Update the image src in the card to use server URL
              const imgEl = currentCard?.userImage?.querySelector('img');
              if (imgEl) imgEl.src = serverUrl;
              // Re-sync to persist the server URL
              try { syncCurrentConvo(); } catch (e) { console.warn('[streamWebSearch] syncCurrentConvo (image upload) failed', e); }
            }
          });
        }
      }
      history.push(userMessage);
      console.log('[streamWebSearch] Added user message to history; length:', history.length, 'hasImage:', !!attachedImage?.hasImage, 'hasDocument:', !!attachedImage?.hasDocument);
    } else {
      console.log('[streamWebSearch] Regenerating response for prompt:', query.substring(0, 50));
    }
    try { syncCurrentConvo(); } catch (e) { console.warn('[streamWebSearch] syncCurrentConvo failed', e); }
    
    // Update UI
    setBusy(true);

    try {
      // Build request body with history for conversation memory
      const bodyParams = new URLSearchParams();
      bodyParams.append('prompt', query);
      
      // Filter history for backend: remove image data from previous messages to avoid vision model errors
      // Only the current message should have image if attached
      const historyForBackend = history.slice(0, -1).map(msg => {
        // Strip imageUrl from history sent to backend (too large and causes issues)
        const { imageUrl, ...rest } = msg;
        return rest;
      });
      bodyParams.append('history', JSON.stringify(historyForBackend));
      
      // If there's an attached image, send it (still send base64 for vision processing)
      if (attachedImage) {
        if (attachedImage.attachmentType === 'document') {
          // Document attachment - send document ID (already uploaded and processed)
          bodyParams.append('documentId', attachedImage.documentId);
          bodyParams.append('hasDocument', '1');
        } else {
          // Image attachment - send base64 for vision
          bodyParams.append('image', attachedImage.dataUrl);
          bodyParams.append('hasImage', '1');
        }
      }
      
      // Include CSRF token
      const csrfToken = window.CSRF_TOKEN || document.getElementById('csrf_token')?.value || '';
      if (csrfToken) {
        bodyParams.append('csrf_token', csrfToken);
      }

      const preferNonStream = shouldPreferNonStreamResponse(query, attachedImage);
      bodyParams.append('stream', preferNonStream ? '0' : '1');
      if (preferNonStream) {
        bodyParams.append('prefer_non_stream', '1');
      }
      
      const response = await fetch('/chat', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-CSRF-Token': csrfToken
        },
        body: bodyParams.toString(),
        signal: abortController.signal
      });

      if (!response.ok) {
        let errorMessage = '';
        try {
          const raw = await response.text();
          try {
            const parsed = JSON.parse(raw);
            errorMessage = parsed?.detail || parsed?.error?.message || parsed?.error || parsed?.message || '';
          } catch (_) {
            errorMessage = raw || '';
          }
        } catch (_) {
          errorMessage = '';
        }

        const fallbackText = 'Backend is temporarily unavailable. Please try again.';
        const visibleMessage = (errorMessage && String(errorMessage).trim()) ? String(errorMessage).trim() : fallbackText;
        currentCard.response.innerHTML = `<p class="text-red-400">${escapeHtml(visibleMessage)}</p>`;
        currentCard.responseLabel.classList.remove('hidden');
        return;
      }

      const contentType = (response.headers.get('content-type') || '').toLowerCase();
      if (contentType.includes('application/json') || contentType.includes('text/json')) {
        const data = await response.json().catch(() => null);
        if (data) {
          try { updateAdminConsole(data); } catch (_) {}

          const payloadError =
            (typeof data?.detail === 'string' && data.detail.trim()) ||
            (typeof data?.message === 'string' && data.message.trim()) ||
            (typeof data?.error === 'string' && data.error.trim()) ||
            (typeof data?.error?.message === 'string' && data.error.message.trim()) ||
            '';

          if (payloadError) {
            currentCard.response.innerHTML = `<p class="text-red-400">${escapeHtml(payloadError)}</p>`;
            currentCard.responseLabel.classList.remove('hidden');
            currentCard.body.classList.remove('collapsed');
            return;
          }

          const payloadParts = extractOpenAiContentPartsFromPayload(data);
          const mergedRaw = (payloadParts.text || '') + (typeof data.text === 'string' ? data.text : '');
          const mergedText = sanitizeOpenAiTextChunk(mergedRaw);

          if (mergedRaw && !mergedText && !warnedLargeChunkSkipped) {
            warnedLargeChunkSkipped = true;
            console.warn('[streamWebSearch] Skipped oversized base64-like JSON chunk');
          }

          const imageCandidates = [...(payloadParts.images || [])];
          for (const imageUrl of imageCandidates) {
            if (!imageUrl || seenImageUrls.has(imageUrl)) continue;
            seenImageUrls.add(imageUrl);
            accumulatedImageUrls.push(imageUrl);
          }

          if (mergedText) {
            accumulatedContent += mergedText;
          }

          if (accumulatedContent || accumulatedImageUrls.length > 0) {
            contentStarted = true;
            currentCard.responseLabel.classList.remove('hidden');
            currentCard.body.classList.remove('collapsed');
            const combinedHtml = mergeAssistantTextAndImagesHtml(accumulatedContent, accumulatedImageUrls);
            currentCard.response.innerHTML = combinedHtml;
            renderLatexInElement(currentCard.response);
            enhanceCodeBlocks(currentCard.response);
            initStickyCodeButtons();
            lastAssistantHtml = ensureCodeBlockAttributes(currentCard.response.innerHTML);
            lastAssistantContent = accumulatedContent || (typeof data.raw_content === 'string' ? data.raw_content : '');
            smartScrollToElement(currentCard.response, { behavior: 'smooth', block: 'end' });
            return;
          }

          if (typeof data.html === 'string' && data.html.trim()) {
            contentStarted = true;
            currentCard.responseLabel.classList.remove('hidden');
            currentCard.body.classList.remove('collapsed');
            currentCard.response.innerHTML = ensureCodeBlockAttributes(stripToolCallJson(data.html));
            renderLatexInElement(currentCard.response);
            enhanceCodeBlocks(currentCard.response);
            initStickyCodeButtons();
            lastAssistantHtml = ensureCodeBlockAttributes(currentCard.response.innerHTML);
            lastAssistantContent = (typeof data.raw_content === 'string' && data.raw_content.trim())
              ? data.raw_content
              : (data.html || '').replace(/<[^>]+>/g, '').trim();
            smartScrollToElement(currentCard.response, { behavior: 'smooth', block: 'end' });
            return;
          }
        }
      }

      const reader = response.body.getReader();
      const decoder = new TextDecoder();
      let buffer = '';
      let contentStarted = false;

      while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });
        const lines = buffer.split('\n');
        buffer = lines.pop() || '';

        for (const line of lines) {
          // Skip empty lines and padding (spaces only)
          const trimmedLine = line.trim();
          if (!trimmedLine || !trimmedLine.startsWith('data: ')) continue;
          
          const jsonStr = trimmedLine.slice(6).trim();
          if (!jsonStr) continue;

            try {
            const data = JSON.parse(jsonStr);

            // DEBUG: Log all incoming SSE events
            console.log('[SSE Event]', data);
            // Update admin console if this event includes provider/model/raw info
            try { updateAdminConsole(data); } catch (e) { /* ignore */ }

            // admin_log events are shown in Admin Console via updateAdminConsole()
            if (data.admin_log && data.message) {
              continue;
            }

            // Handle special actions (register, upgrade, login)
            if (data.action === 'register') {
              // Visitor limit reached - show register modal
              if (typeof window.showRegisterModal === 'function') {
                window.showRegisterModal(data.text || 'You\'ve reached the free limit. Create a free account to continue!');
              }
              // Still show the message in the chat
              if (data.html) {
                currentCard.response.innerHTML = data.html;
                currentCard.responseLabel.classList.remove('hidden');
              }
              finishCardActivity(currentCard, activities);
              currentCard.footer.classList.remove('hidden');
              continue;
            }

            // Handle phase events (matches pandasearch format - separate from activity events)
            // Phase events are one-time markers, not start/complete pairs, so no spinners needed
            if (data.phase && !data.activity && data.phase !== 'error') {
              // Show search task section
              currentCard.searchTask.classList.remove('hidden');
              currentCard.body.classList.remove('collapsed');
              if (currentCard.searchChevron) currentCard.searchChevron.classList.add('open');
              
              // Show header spinner when phase starts
              if (currentCard.searchSpinner && data.phase !== 'search_complete') {
                currentCard.searchSpinner.classList.remove('hidden');
              }
              
              const phase = data.phase;
              const message = data.message || '';
              
              // Phase markers are informational - skip if no message
              // (elapsed time alone is not useful to display)
              if (!message) {
                // Just update status, don't add a line
                if (phase === 'search') {
                  currentCard.searchStatus.textContent = 'Searching...';
                } else if (phase === 'fetch') {
                  currentCard.searchStatus.textContent = 'Reading sources...';
                } else if (phase === 'search_complete') {
                  currentCard.searchStatus.textContent = 'complete';
                  if (currentCard.searchSpinner) {
                    currentCard.searchSpinner.classList.add('hidden');
                  }
                }
                continue;
              }
              
              const text = escapeHtml(message);
              const activityLine = `<div class="reasoning-item"><div class="reasoning-item-indicator"><div class="reasoning-item-dot"></div><div class="reasoning-item-line"></div></div><div class="reasoning-item-text"><p>${text}</p></div></div>`;
              
              currentCard.searchContent.innerHTML += activityLine;
              currentCard.searchContent.scrollTop = currentCard.searchContent.scrollHeight;
              
              smartScrollToElement(currentCard.searchTask, { behavior: 'smooth', block: 'end' });
              continue;
            }
            
            // Handle final event (matches pandasearch format)
            if (data.final) {
              const sourcesCount = data.sources_count || 0;
              const reasoningChunks = data.reasoning_chunks || 0;
              const elapsedMs = data.total_elapsed_ms || data.elapsed_ms || '';
              const elapsedText = elapsedMs ? `${elapsedMs}ms` : '';
              
              const text = data.success 
                ? `🏁 Complete! ${sourcesCount} sources, ${reasoningChunks} reasoning chunks, ${elapsedText}`
                : `🏁 Finished (with errors)`;
              
              const completeLine = `<div class="reasoning-item"><div class="reasoning-item-indicator"><div class="reasoning-item-dot" style="background:#22c55e"></div><div class="reasoning-item-line"></div></div><div class="reasoning-item-text" style="color:#22c55e"><p>${text}</p></div></div>`;
              
              currentCard.searchContent.innerHTML += completeLine;
              currentCard.searchContent.scrollTop = currentCard.searchContent.scrollHeight;
              
              currentCard.searchStatus.textContent = 'complete';
              if (currentCard.searchSpinner) {
                currentCard.searchSpinner.classList.add('hidden');
              }
              
              // Auto-collapse the search task section after completion
              if (currentCard.searchContent) {
                currentCard.searchContent.classList.add('hidden');
              }
              if (currentCard.searchChevron) {
                currentCard.searchChevron.classList.remove('open');
              }
              
              smartScrollToElement(currentCard.searchTask, { behavior: 'smooth', block: 'end' });
              continue;
            }

            // Handle activity events (search task stream)
            if (data.activity === true || data.activity === 'websearch' || data.type === 'websearch') {
              // Show search task section
              currentCard.searchTask.classList.remove('hidden');
              currentCard.body.classList.remove('collapsed');
              if (currentCard.searchChevron) currentCard.searchChevron.classList.add('open');
              
              // Show spinner when activity starts (will be hidden when complete)
              if (currentCard.searchSpinner) {
                currentCard.searchSpinner.classList.remove('hidden');
              }
              
              // Build activity line based on event type - match pandasearch format
              let activityLine = '';
              const phase = data.phase || data.type || '';
              const message = data.message || '';
              const domain = data.domain || '';
              const status = data.status || 'running';
              const query = data.query || '';
              const isComplete = (status === 'completed' || status === 'complete');
              const isError = status === 'error';
              
              // Spinner HTML for loading state, checkmark for complete - simple circle spinner
              const spinnerIcon = `<svg class="search-spinner" style="width:14px;height:14px;margin-right:6px;vertical-align:middle;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="#9ca3af" stroke-width="2" stroke-dasharray="31.4 31.4" stroke-linecap="round"/></svg>`;
              const checkIcon = `<span style="color:#22c55e;font-weight:bold;margin-right:6px;vertical-align:middle;">✓</span>`;
              const errorIcon = `<span style="color:#ef4444;font-weight:bold;margin-right:6px;vertical-align:middle;">✗</span>`;
              const statusPrefix = isComplete ? checkIcon : isError ? errorIcon : spinnerIcon;
              
              // Handle search events - format: 🔍/✅ Searching/Searched: "query"
              if (phase === 'search') {
                if (query) {
                  const actionText = isComplete ? 'Searched' : 'Searching';
                  const text = `${statusPrefix}${actionText}: "${escapeHtml(query)}"`;
                  // Try to find and update existing search line for this query
                  const existingSearches = currentCard.searchContent.querySelectorAll('.reasoning-item-text p');
                  let updated = false;
                  for (const p of existingSearches) {
                    if (p.textContent.includes(`Searching: "${query}"`) || p.textContent.includes(`Searched: "${query}"`)) {
                      p.innerHTML = text;
                      updated = true;
                      break;
                    }
                  }
                  if (!updated) {
                    activityLine = `<div class="reasoning-item"><div class="reasoning-item-indicator"><div class="reasoning-item-dot"></div><div class="reasoning-item-line"></div></div><div class="reasoning-item-text"><p>${text}</p></div></div>`;
                  }
                } else if (message) {
                  // Phase marker: 🔍 message
                  const text = `${spinnerIcon}${escapeHtml(message)}`;
                  activityLine = `<div class="reasoning-item"><div class="reasoning-item-indicator"><div class="reasoning-item-dot"></div><div class="reasoning-item-line"></div></div><div class="reasoning-item-text"><p>${text}</p></div></div>`;
                }
                currentCard.searchStatus.textContent = status === 'completed' ? 'Search complete' : 'Searching...';
                
                // Hide spinner when search is complete
                if (status === 'completed' && currentCard.searchSpinner) {
                  currentCard.searchSpinner.classList.add('hidden');
                }
              } 
              // Handle read/fetch events - format: 📖/✅ Reading/Read: domain
              else if (phase === 'fetch' || phase === 'read') {
                if (domain) {
                  const actionText = isComplete ? 'Read' : 'Reading';
                  const text = `${statusPrefix}${actionText}: ${escapeHtml(domain)}`;
                  // Try to find and update existing read line for this domain
                  const existingReads = currentCard.searchContent.querySelectorAll('.reasoning-item-text p');
                  let updated = false;
                  for (const p of existingReads) {
                    if (p.textContent.includes(`Reading: ${domain}`) || p.textContent.includes(`Read: ${domain}`)) {
                      p.innerHTML = text;
                      updated = true;
                      break;
                    }
                  }
                  if (!updated) {
                    activityLine = `<div class="reasoning-item"><div class="reasoning-item-indicator"><div class="reasoning-item-dot"></div><div class="reasoning-item-line"></div></div><div class="reasoning-item-text"><p>${text}</p></div></div>`;
                  }
                } else if (message) {
                  // Phase marker with spinner
                  const text = `${spinnerIcon}${escapeHtml(message)}`;
                  activityLine = `<div class="reasoning-item"><div class="reasoning-item-indicator"><div class="reasoning-item-dot"></div><div class="reasoning-item-line"></div></div><div class="reasoning-item-text"><p>${text}</p></div></div>`;
                }
                currentCard.searchStatus.textContent = isComplete ? `Read ${domain || 'source'}` : `Reading ${domain || 'source'}...`;
              }
              // Handle search_complete phase
              else if (phase === 'search_complete') {
                currentCard.searchStatus.textContent = 'complete';
                if (currentCard.searchSpinner) {
                  currentCard.searchSpinner.classList.add('hidden');
                }
              }
              // Handle generic messages
              else if (message) {
                activityLine = `<div class="reasoning-item"><div class="reasoning-item-indicator"><div class="reasoning-item-dot"></div><div class="reasoning-item-line"></div></div><div class="reasoning-item-text"><p>📡 ${escapeHtml(message)}</p></div></div>`;
              }
              
              if (activityLine) {
                currentCard.searchContent.innerHTML += activityLine;
                currentCard.searchContent.scrollTop = currentCard.searchContent.scrollHeight;
              }
              
              // Track for citations
              if (phase === 'search' && query) {
                activities.searches.push({ query: query });
              } else if ((phase === 'read' || phase === 'fetch') && domain) {
                activities.reads.push({ domain: domain, url: data.url, title: data.title });
              }
              
              smartScrollToElement(currentCard.searchTask, { behavior: 'smooth', block: 'end' });
              continue;
            }
            
            // Handle websearch complete event - show all sources with favicons
            if (data.websearch_complete && data.sources) {
              // Mark search task as complete (final event already added the completion line)
              if (currentCard.searchSpinner) {
                currentCard.searchSpinner.classList.add('hidden');
              }
              currentCard.searchStatus.textContent = `complete`;
              
              // Update citations with full source info including favicons
              citations = data.sources.map(s => ({
                domain: s.domain,
                url: s.url,
                title: s.title,
                favicon: s.favicon
              }));
              updateCardCitations(currentCard, citations);
              
              // Mark search as complete
              activities.analyzed = true;
              activities.analyzing = false;
              continue;
            }

            // Handle reasoning chunks (streamed during search)
            if (data.reasoning !== undefined || (data.reasoning === true && data.text)) {
              const reasoningText = data.text || data.reasoning;
              if (typeof reasoningText === 'string' && reasoningText.length > 0) {
                accumulatedReasoning += reasoningText;
                currentCard.reasoning.classList.remove('hidden');
                currentCard.body.classList.remove('collapsed');
                if (currentCard.reasoningChevron) currentCard.reasoningChevron.classList.add('open');
                
                // Strip tool_call JSON from reasoning display
                const cleanReasoning = stripToolCallJson(accumulatedReasoning);
                currentCard.reasoningContent.innerHTML = formatReasoningText(cleanReasoning);
                currentCard.reasoningContent.scrollTop = currentCard.reasoningContent.scrollHeight;
              }
              continue;
            }

            // Handle server-side tool execution events (new agentic loop)
            if (data.tool_use) {
              // Server is executing a tool - show spinner
              if (!contentStarted) {
                currentCard.response.innerHTML = '';
                currentCard.responseLabel.classList.remove('hidden');
                currentCard.body.classList.remove('collapsed');
                contentStarted = true;
              }
              // Mark that we have tool results - don't overwrite with text
              currentCard.hasToolResults = true;
              const toolNote = document.createElement('div');
              toolNote.className = 'mt-3 p-3 bg-gray-100 dark:bg-gray-800/50 border border-gray-300 dark:border-gray-700 rounded-lg tool-exec-' + (data.tool_name || 'unknown').replace(/[^a-zA-Z0-9]/g, '_');
              toolNote.innerHTML = `<div class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Executing <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">${escapeHtml(data.tool_name || 'tool')}</code>...</span>
              </div>`;
              currentCard.response.appendChild(toolNote);
              smartScrollToElement(toolNote, { behavior: 'smooth', block: 'end' });
              continue;
            }

            // Handle image generation progress
            if (data.image_progress) {
              // Find or create progress bar for generate_image tool
              let progressContainer = currentCard.response.querySelector('.image-gen-progress');
              if (!progressContainer) {
                progressContainer = document.createElement('div');
                progressContainer.className = 'image-gen-progress mt-2 p-3 bg-purple-100 dark:bg-purple-900/20 border border-purple-300 dark:border-purple-500/30 rounded-lg';
                progressContainer.innerHTML = `
                  <div class="flex items-center gap-2 text-purple-700 dark:text-purple-300 mb-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="text-sm font-medium">Generating Image</span>
                    <span class="image-gen-step text-xs text-purple-500 dark:text-purple-400/70 ml-auto"></span>
                  </div>
                  <div class="flex items-center gap-2">
                    <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                      <div class="image-gen-bar bg-gradient-to-r from-purple-500 to-pink-500 h-2 rounded-full transition-all duration-150" style="width: 0%"></div>
                    </div>
                    <span class="image-gen-percent text-xs text-purple-600 dark:text-purple-300/70 min-w-[3rem] text-right"></span>
                  </div>
                  <div class="image-gen-status text-xs text-purple-600 dark:text-purple-300/70 mt-1"></div>
                `;
                currentCard.response.appendChild(progressContainer);
              }
              // Update progress
              const bar = progressContainer.querySelector('.image-gen-bar');
              const status = progressContainer.querySelector('.image-gen-status');
              const stepLabel = progressContainer.querySelector('.image-gen-step');
              const percentLabel = progressContainer.querySelector('.image-gen-percent');
              
              if (bar) bar.style.width = (data.progress || 0) + '%';
              if (status) status.textContent = data.message || '';
              if (percentLabel) percentLabel.textContent = Math.round(data.progress || 0) + '%';
              if (stepLabel && data.step && data.total_steps) {
                stepLabel.textContent = `Step ${data.step}/${data.total_steps}`;
              }
              
              // Remove progress bar when complete
              if (data.progress >= 100) {
                progressContainer.dataset.finalizedAt = String(Date.now());
                progressContainer.dataset.removeDelayMs = '500';
                setTimeout(() => {
                  progressContainer.remove();
                }, 500);
              }
              continue;
            }

            if (data.tool_result) {
              // Server finished executing a tool - replace spinner with result
              currentCard.hasToolResults = true;

              if (data.tool_name === 'generate_image') {
                const progressContainer = currentCard.response.querySelector('.image-gen-progress');
                if (progressContainer) {
                  const bar = progressContainer.querySelector('.image-gen-bar');
                  const status = progressContainer.querySelector('.image-gen-status');
                  const percentLabel = progressContainer.querySelector('.image-gen-percent');
                  const success = !!(data.result && data.result.success);
                  if (bar) bar.style.width = '100%';
                  if (percentLabel) percentLabel.textContent = '100%';
                  if (status) status.textContent = success ? 'Complete!' : 'Failed';
                  progressContainer.dataset.finalizedAt = String(Date.now());
                  progressContainer.dataset.removeDelayMs = success ? '600' : '300';
                  setTimeout(() => {
                    if (progressContainer.parentNode) progressContainer.remove();
                  }, success ? 600 : 300);
                }
              }
              
              // Collect for persistence - but truncate large results like lightpanda_search
              let persistData = data;
              if (data.tool_name === 'lightpanda_search' && data.result?.content) {
                // For web search, just save a summary not the full content (which can be huge)
                const sources = [];
                const sourceRegex = /### Source \d+: (.+?)\nURL: (https?:\/\/[^\n]+)/g;
                let match;
                while ((match = sourceRegex.exec(data.result.content)) !== null) {
                  sources.push({ title: match[1], url: match[2] });
                }
                persistData = {
                  ...data,
                  result: {
                    success: data.result.success,
                    sources: sources,
                    sourcesCount: sources.length,
                    truncated: true
                  }
                };
              }
              toolResults.push({ name: data.tool_name, result: persistData });
              
              const toolClass = 'tool-exec-' + (data.tool_name || 'unknown').replace(/[^a-zA-Z0-9]/g, '_');
              const existingNote = currentCard.response.querySelector('.' + toolClass);
              if (existingNote) {
                existingNote.className = 'mt-3 tool-result-formatted';
                existingNote.innerHTML = formatToolResult(data.tool_name, data);
              } else {
                // No spinner found, just append result
                const resultNote = document.createElement('div');
                resultNote.className = 'mt-3 tool-result-formatted';
                resultNote.innerHTML = formatToolResult(data.tool_name, data);
                currentCard.response.appendChild(resultNote);
              }
              smartScrollToElement(currentCard.response.lastChild, { behavior: 'smooth', block: 'end' });
              continue;
            }

            // Handle OpenAI-compatible chunks including root arrays and reply/response arrays
            const payloadParts = extractOpenAiContentPartsFromPayload(data);
            const hasOpenAiContent =
              (payloadParts.text && payloadParts.text.length > 0) ||
              (Array.isArray(payloadParts.images) && payloadParts.images.length > 0);

            if (hasOpenAiContent) {
              if (!contentStarted) {
                if (!currentCard.hasToolResults) {
                  currentCard.response.innerHTML = '';
                }
                currentCard.responseLabel.classList.remove('hidden');
                currentCard.body.classList.remove('collapsed');
                contentStarted = true;
              }

              const mergedRawText = payloadParts.text || '';
              const mergedText = sanitizeOpenAiTextChunk(mergedRawText);
              if (mergedRawText && !mergedText && !warnedLargeChunkSkipped) {
                warnedLargeChunkSkipped = true;
                console.warn('[streamWebSearch] Skipped oversized base64-like stream chunk');
              }

              if (mergedText) {
                accumulatedContent += mergedText;
                try {
                  if (window.__gintoAudio && typeof window.__gintoAudio.queueFragment === 'function') {
                    const cleanText = sanitizeForTTS(mergedText);
                    if (cleanText) window.__gintoAudio.queueFragment(cleanText);
                  }
                } catch (_) {}
              }

              const imageCandidates = [...(payloadParts.images || [])];
              for (const imageUrl of imageCandidates) {
                if (!imageUrl || seenImageUrls.has(imageUrl)) continue;
                seenImageUrls.add(imageUrl);
                accumulatedImageUrls.push(imageUrl);
              }

              const combinedHtml = mergeAssistantTextAndImagesHtml(accumulatedContent, accumulatedImageUrls);

              if (currentCard.hasToolResults) {
                let textResponse = currentCard.response.querySelector('.text-response-after-tools');
                if (!textResponse) {
                  textResponse = document.createElement('div');
                  textResponse.className = 'text-response-after-tools mt-4';
                  currentCard.response.appendChild(textResponse);
                }
                textResponse.innerHTML = combinedHtml;
              } else {
                currentCard.response.innerHTML = combinedHtml;
              }

              renderLatexInElement(currentCard.response);
              enhanceCodeBlocks(currentCard.response);
              initStickyCodeButtons();
              lastAssistantHtml = ensureCodeBlockAttributes(currentCard.response.innerHTML);
              smartScrollToElement(currentCard.response, { behavior: 'smooth', block: 'end' });
              continue;
            }


            // Handle text chunks - render markdown client-side with markdown-it + KaTeX
            // IMPORTANT: Check for data.final BEFORE this - final message also has html but we don't want to re-render
            if ((data.text || data.html) && !data.final) {
              if (!contentStarted) {
                // If we have tool results, don't clear them - append text after
                if (!currentCard.hasToolResults) {
                  currentCard.response.innerHTML = '';
                }
                currentCard.responseLabel.classList.remove('hidden');
                // Ensure the card body is visible and scroll into view
                currentCard.body.classList.remove('collapsed');
                contentStarted = true;
              }
              accumulatedContent += data.text || '';
              
              // Client-side markdown rendering (preferred)
              // Strip tool_call JSON artifacts and render with markdown-it
              const displayContent = stripToolCallJson(accumulatedContent);
              let displayHtml = simpleMarkdownToHtml(displayContent);
              
              // If server still sends HTML (legacy mode), use it as fallback
              if (data.html && !data.text) {
                displayHtml = ensureCodeBlockAttributes(stripToolCallJson(data.html));
              }
              
              // If we have tool results, keep them and add text response after
              if (currentCard.hasToolResults) {
                // Find or create text response container after tool results
                let textResponse = currentCard.response.querySelector('.text-response-after-tools');
                if (!textResponse) {
                  textResponse = document.createElement('div');
                  textResponse.className = 'text-response-after-tools mt-4';
                  currentCard.response.appendChild(textResponse);
                }
                textResponse.innerHTML = displayHtml;
              } else {
                currentCard.response.innerHTML = displayHtml;
              }
              
              // Render LaTeX math expressions with KaTeX
              renderLatexInElement(currentCard.response);
              
              // Enhance code blocks during streaming so they look correct immediately
              enhanceCodeBlocks(currentCard.response);
              
              // Initialize sticky buttons as soon as code blocks appear
              initStickyCodeButtons();
              
              // Auto-scroll to keep response visible during streaming (only if user is at bottom)
              smartScrollToElement(currentCard.response, { behavior: 'smooth', block: 'end' });
              
              // Queue sanitized text for TTS (remove markdown, code, citations)
              try {
                if (window.__gintoAudio && typeof window.__gintoAudio.queueFragment === 'function') {
                  const cleanText = sanitizeForTTS(data.text || '');
                  if (cleanText) window.__gintoAudio.queueFragment(cleanText);
                }
              } catch (e) {}
              continue;
            }

            // Handle final HTML response
            if (data.final) {
              const finalRawContent = (typeof data.raw_content === 'string') ? data.raw_content : '';
              // Clean up any lingering image progress UI on final event.
              const lingeringProgress = currentCard.response.querySelector('.image-gen-progress');
              if (lingeringProgress && lingeringProgress.parentNode) {
                const finalizedAt = parseInt(lingeringProgress.dataset.finalizedAt || '0', 10);
                const removeDelayMs = parseInt(lingeringProgress.dataset.removeDelayMs || '0', 10);
                if (finalizedAt > 0 && removeDelayMs > 0) {
                  const elapsedMs = Date.now() - finalizedAt;
                  const remainingMs = Math.max(0, removeDelayMs - elapsedMs);
                  if (remainingMs === 0) {
                    lingeringProgress.remove();
                  } else {
                    setTimeout(() => {
                      if (lingeringProgress.parentNode) lingeringProgress.remove();
                    }, remainingMs);
                  }
                } else {
                  lingeringProgress.remove();
                }
              }

              // Always show the response label and handle content
              currentCard.responseLabel.classList.remove('hidden');
              
              // If we have tool results, preserve them - just save for persistence
              if (currentCard.hasToolResults) {
                lastAssistantHtml = ensureCodeBlockAttributes(currentCard.response.innerHTML);
                lastAssistantContent = accumulatedContent || finalRawContent || '';
                enhanceCodeBlocks(currentCard.response);
                setTimeout(() => initStickyCodeButtons(), 100);
              } else if (accumulatedContent) {
                // DON'T re-render if streaming already rendered content - just save for persistence
                const cleanedContent = stripToolCallJson(accumulatedContent);
                
                // Save the current rendered HTML and raw content for persistence
                // Do NOT replace innerHTML - streaming already rendered it correctly!
                lastAssistantHtml = ensureCodeBlockAttributes(currentCard.response.innerHTML);
                lastAssistantContent = accumulatedContent;
                
                // Just ensure code blocks are enhanced (idempotent operation)
                enhanceCodeBlocks(currentCard.response);
                setTimeout(() => initStickyCodeButtons(), 100);
              } else if (accumulatedImageUrls.length > 0) {
                const combinedHtml = mergeAssistantTextAndImagesHtml('', accumulatedImageUrls);
                currentCard.response.innerHTML = combinedHtml;
                lastAssistantHtml = ensureCodeBlockAttributes(combinedHtml);
                lastAssistantContent = finalRawContent || '';
                renderLatexInElement(currentCard.response);
                enhanceCodeBlocks(currentCard.response);
                setTimeout(() => initStickyCodeButtons(), 100);
              } else if (data.html) {
                // Fallback: Use server-rendered HTML only if no streaming happened
                let cleanedHtml = stripToolCallJson(data.html);
                cleanedHtml = ensureCodeBlockAttributes(cleanedHtml);
                currentCard.response.innerHTML = cleanedHtml;
                lastAssistantHtml = cleanedHtml;
                lastAssistantContent = finalRawContent || (cleanedHtml || '').replace(/<[^>]+>/g, '').trim();
                
                renderLatexInElement(currentCard.response);
                enhanceCodeBlocks(currentCard.response);
                setTimeout(() => initStickyCodeButtons(), 100);
              } else if (finalRawContent) {
                const finalTextHtml = ensureCodeBlockAttributes(simpleMarkdownToHtml(stripToolCallJson(finalRawContent)));
                currentCard.response.innerHTML = finalTextHtml;
                lastAssistantHtml = finalTextHtml;
                lastAssistantContent = finalRawContent;
                renderLatexInElement(currentCard.response);
                enhanceCodeBlocks(currentCard.response);
                setTimeout(() => initStickyCodeButtons(), 100);
              } else if (data.contentEmpty && data.reasoningHtml) {
                // Content was empty, show informative message
                currentCard.response.innerHTML = '<p class="text-gray-500 dark:text-gray-400 italic">The model\'s analysis is shown in the reasoning section above.</p>';
              }
              
              if (data.reasoningHtml) {
                currentCard.reasoning.classList.remove('hidden');
                // Strip tool_call JSON from server-rendered reasoning HTML too
                currentCard.reasoningContent.innerHTML = stripToolCallJson(data.reasoningHtml);
              } else if (accumulatedReasoning) {
                // Fallback: use accumulated reasoning if no final reasoningHtml
                currentCard.reasoning.classList.remove('hidden');
                const cleanReasoning = stripToolCallJson(accumulatedReasoning);
                currentCard.reasoningContent.innerHTML = formatReasoningText(cleanReasoning);
              }
              
              // Mark analysis as complete if we had web search + reasoning
              if (activities.analyzing) {
                activities.analyzed = true;
                activities.analyzing = false;
              }
              
              finishCardActivity(currentCard, activities);
              currentCard.footer.classList.remove('hidden');
            }

            if (data.error) {
              const lingeringProgress = currentCard.response.querySelector('.image-gen-progress');
              if (lingeringProgress && lingeringProgress.parentNode) {
                lingeringProgress.remove();
              }
              currentCard.response.innerHTML = `<p class="text-red-400">Error: ${data.error}</p>`;
            }

          } catch (parseErr) {
            console.debug('Parse error:', parseErr.message);
          }
        }
      }

    } catch (err) {
      if (err.name === 'AbortError') {
        // Replace spinner with sad face icon when cancelled
        const thinkingWrapper = currentCard.response.querySelector('.thinking-indicator-wrapper');
        if (thinkingWrapper) {
          thinkingWrapper.innerHTML = '<svg class="w-4 h-4" viewBox="0 0 36 36"><circle fill="#FFCC4D" cx="18" cy="18" r="18"/><ellipse fill="#664500" cx="11.5" cy="12.5" rx="2.5" ry="2.5"/><ellipse fill="#664500" cx="24.5" cy="12.5" rx="2.5" ry="2.5"/><path fill="none" stroke="#664500" stroke-width="2" stroke-linecap="round" d="M12 26c2-3 8-3 12 0"/></svg><span>Cancelled</span>';
        } else {
          currentCard.response.innerHTML += '<p class="text-yellow-400 mt-2"><em>Cancelled</em></p>';
        }
        // Remove the user message from history if aborted
        if (history.length > 0 && history[history.length - 1].role === 'user') {
          history.pop();
        }
        accumulatedContent = ''; // Clear so finally doesn't add empty response
      } else {
        currentCard.response.innerHTML = '<p class="text-red-400">Backend is temporarily unavailable. Please try again.</p>';
        // Remove the user message from history on error
        if (history.length > 0 && history[history.length - 1].role === 'user') {
          history.pop();
        }
        accumulatedContent = ''; // Clear so finally doesn't add empty response
      }
    } finally {
      // Update attachment caption if we had an attachment (only for images, not documents)
      if (attachedImage && currentCard?.userImage && attachedImage.attachmentType !== 'document') {
        const caption = currentCard.userImage.querySelector('p');
        if (caption) {
          caption.innerHTML = `
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Attached image (analyzed with vision)
          `;
        }
      }
      
      // Add assistant response to history if we got content OR tool results
      const responseContent = lastAssistantContent || accumulatedContent;
      const hasToolResults = toolResults.length > 0;
      if ((responseContent && responseContent.trim()) || hasToolResults) {
        const newVersion = { 
          content: responseContent || '', 
          ts: Date.now(),
          html: lastAssistantHtml || null,
          reasoning: accumulatedReasoning || null,
          activities: (activities.searches.length > 0 || activities.reads.length > 0) ? activities : null,
          citations: citations.length > 0 ? citations : null,
          toolResults: toolResults.length > 0 ? toolResults : null
        };
        
        // Ensure we have HTML for persistence even if data.final was missing
        if (!newVersion.html && responseContent) {
          const cleanedContent = stripToolCallJson(responseContent);
          newVersion.html = ensureCodeBlockAttributes(simpleMarkdownToHtml(cleanedContent));
        }
        
        // Check if this is a regeneration (isRegeneration flag from earlier in function scope)
        if (isRegeneration && history.length >= 2) {
          // Find the last assistant message that follows the user message we're regenerating
          const lastAssistantIdx = history.length - 1;
          const lastAssistant = history[lastAssistantIdx];
          
          if (lastAssistant && lastAssistant.role === 'assistant') {
            // Initialize versions array if it doesn't exist
            if (!lastAssistant.versions) {
              // Convert the current response to version 1
              lastAssistant.versions = [{
                content: lastAssistant.content,
                ts: lastAssistant.ts,
                html: lastAssistant.html,
                reasoning: lastAssistant.reasoning,
                activities: lastAssistant.activities,
                citations: lastAssistant.citations,
                toolResults: lastAssistant.toolResults
              }];
            }
            
            // Add new version
            lastAssistant.versions.push(newVersion);
            lastAssistant.currentVersion = lastAssistant.versions.length;
            
            // Update the main content to the new version
            lastAssistant.content = newVersion.content;
            lastAssistant.html = newVersion.html;
            lastAssistant.reasoning = newVersion.reasoning;
            lastAssistant.activities = newVersion.activities;
            lastAssistant.citations = newVersion.citations;
            lastAssistant.toolResults = newVersion.toolResults;
            lastAssistant.ts = newVersion.ts;
            
            console.log('[streamWebSearch] Added version', lastAssistant.versions.length, 'to assistant response');
            
            // Re-render to show version navigation
            renderMessages();
          } else {
            // Fallback: add as new message if structure is unexpected
            const assistantMsg = { 
              role: 'assistant', 
              content: responseContent || '', 
              ts: Date.now(),
              versions: [newVersion],
              currentVersion: 1
            };
            if (newVersion.html) assistantMsg.html = newVersion.html;
            if (newVersion.reasoning) assistantMsg.reasoning = newVersion.reasoning;
            if (newVersion.activities) assistantMsg.activities = newVersion.activities;
            if (newVersion.citations) assistantMsg.citations = newVersion.citations;
            if (newVersion.toolResults) assistantMsg.toolResults = newVersion.toolResults;
            history.push(assistantMsg);
          }
        } else {
          // Normal new message - create with single version
          const assistantMsg = { 
            role: 'assistant', 
            content: responseContent || '', 
            ts: Date.now(),
            versions: [newVersion],
            currentVersion: 1
          };
          if (newVersion.html) assistantMsg.html = newVersion.html;
          if (newVersion.reasoning) assistantMsg.reasoning = newVersion.reasoning;
          if (newVersion.activities) assistantMsg.activities = newVersion.activities;
          if (newVersion.citations) assistantMsg.citations = newVersion.citations;
          if (newVersion.toolResults) assistantMsg.toolResults = newVersion.toolResults;
          
          history.push(assistantMsg);
          console.log('[streamWebSearch] Added assistant response to history; length:', history.length);
        }

        // Tool execution is now handled server-side via structured tool_calls
        // Client just displays tool_use and tool_result events streamed from server
      }
      // Always sync to persist any changes (including successful responses)
      syncCurrentConvo();
      setBusy(false);
      isStreaming = false; // End streaming - stop auto-scroll control
      userHasScrolledUp = false; // Reset scroll flag when response completes
      finishCardActivity(currentCard, activities);
      currentCard.footer.classList.remove('hidden');
      
      // Force scroll to show the completed response above the composer
      // Use setTimeout to ensure DOM has updated with final content
      const cardToScroll = currentCard;
      setTimeout(() => {
        if (cardToScroll?.card) {
          // Get the card's bottom position and scroll so it's visible above the composer
          const rect = cardToScroll.card.getBoundingClientRect();
          const composerHeight = document.getElementById('composer')?.offsetHeight || 150;
          const windowHeight = window.innerHeight;
          const cardBottom = rect.bottom + window.scrollY;
          // Scroll so the card bottom is above the composer with some padding
          const targetScroll = cardBottom - windowHeight + composerHeight + 40;
          if (targetScroll > window.scrollY) {
            window.scrollTo({ top: targetScroll, behavior: 'smooth' });
          }
        }
      }, 100);
      
      currentCard = null;
    }
  }

  // ============ ATTACHMENT HANDLING ============
  
  // Supported document types for RAG
  const DOCUMENT_TYPES = [
    'application/pdf',
    'text/plain',
    'text/markdown',
    'text/x-markdown',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/rtf',
    'text/rtf',
    'text/html',
    'application/xhtml+xml',
  ];
  const DOCUMENT_EXTENSIONS = ['pdf', 'txt', 'md', 'doc', 'docx', 'rtf', 'html', 'htm'];
  
  function isDocumentFile(file) {
    if (DOCUMENT_TYPES.includes(file.type)) return true;
    const ext = file.name?.split('.').pop()?.toLowerCase();
    return ext && DOCUMENT_EXTENSIONS.includes(ext);
  }
  
  function isImageFile(file) {
    return file.type.startsWith('image/');
  }
  
  // Process any file (image or document)
  function processFile(file) {
    if (!file) return;
    if (isImageFile(file)) {
      processImageFile(file);
    } else if (isDocumentFile(file)) {
      processDocumentFile(file);
    } else {
      showToast('Unsupported file type. Please upload an image or document (PDF, TXT, MD, DOC, DOCX).', 'error');
    }
  }
  
  // Helper to process an image file (used by file input, drag/drop, and paste)
  function processImageFile(file) {
    if (!file) return;
    
    // Validate it's an image
    if (!file.type.startsWith('image/')) {
      alert('Please select an image file');
      return;
    }
    
    // Check file size (max 20MB for Groq vision)
    if (file.size > 20 * 1024 * 1024) {
      alert('Image too large. Maximum size is 20MB.');
      return;
    }
    
    // Read as base64
    const reader = new FileReader();
    reader.onload = (evt) => {
      currentAttachment = {
        dataUrl: evt.target.result,
        filename: file.name || 'pasted-image.png',
        type: file.type,
        attachmentType: 'image'
      };
      
      // Show preview
      if (attachPreviewImg) {
        attachPreviewImg.src = evt.target.result;
        attachPreviewImg.classList.remove('hidden');
      }
      if (attachFilename) attachFilename.textContent = currentAttachment.filename;
      const attachType = document.getElementById('attach-type');
      if (attachType) {
        attachType.textContent = 'Image will be analyzed with vision model';
        attachType.className = 'text-xs text-indigo-500';
      }
      if (attachPreview) attachPreview.classList.remove('hidden');
      
      // Focus prompt for user to type
      promptEl?.focus();
    };
    reader.readAsDataURL(file);
  }
  
  // Process a document file for RAG
  async function processDocumentFile(file) {
    if (!file) return;
    
    // Check file size (max 10MB for documents)
    if (file.size > 10 * 1024 * 1024) {
      showToast('Document too large. Maximum size is 10MB.', 'error');
      return;
    }
    
    // Show loading state
    if (attachPreview) attachPreview.classList.remove('hidden');
    if (attachPreviewImg) attachPreviewImg.classList.add('hidden');
    const docIcon = document.getElementById('attach-doc-icon');
    if (docIcon) docIcon.classList.remove('hidden');
    if (attachFilename) attachFilename.textContent = file.name;
    const attachType = document.getElementById('attach-type');
    if (attachType) {
      attachType.innerHTML = '<span class="inline-flex items-center"><svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Uploading document...</span>';
      attachType.className = 'text-xs text-amber-600 dark:text-amber-400';
    }
    
    // Read as base64
    const reader = new FileReader();
    reader.onload = async (evt) => {
      const dataUrl = evt.target.result;
      
      // Upload to server for RAG processing
      try {
        const formData = new FormData();
        formData.append('document', dataUrl);
        formData.append('filename', file.name);
        formData.append('csrf_token', getCsrfToken());
        
        const res = await fetch('/chat/upload-document', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'X-CSRF-Token': getCsrfToken()
          },
          body: formData
        });
        
        const result = await res.json();
        
        if (result && result.success) {
          currentAttachment = {
            dataUrl: null,
            filename: file.name,
            type: file.type,
            attachmentType: 'document',
            documentId: result.document_id,
            textLength: result.text_length,
            preview: result.preview
          };
          
          // Update UI to show success
          if (attachType) {
            const ext = file.name.split('.').pop()?.toUpperCase() || 'DOC';
            attachType.innerHTML = `<span class="inline-flex items-center"><svg class="w-4 h-4 mr-1 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>${ext} ready for AI analysis (${formatBytes(result.text_length)} text extracted)</span>`;
            attachType.className = 'text-xs text-green-600 dark:text-green-400';
          }
          
          showToast(`Document "${file.name}" uploaded and ready for AI analysis`, 'success');
          promptEl?.focus();
          
        } else {
          clearAttachment();
          showToast(result?.error || 'Failed to process document', 'error');
        }
      } catch (e) {
        console.error('[processDocumentFile] Error:', e);
        clearAttachment();
        showToast('Failed to upload document. Please try again.', 'error');
      }
    };
    reader.readAsDataURL(file);
  }
  
  function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }
  
  // Open file picker when attach button is clicked
  attachBtn?.addEventListener('click', () => {
    attachInput?.click();
  });
  
  // Handle file selection from input
  attachInput?.addEventListener('change', (e) => {
    const file = e.target.files?.[0];
    processFile(file);
  });
  
  // Handle paste (Ctrl+V / Cmd+V) for images
  document.addEventListener('paste', (e) => {
    // Only handle if prompt is focused or no other input is focused
    const activeEl = document.activeElement;
    const isPromptFocused = activeEl === promptEl;
    const isInputFocused = activeEl?.tagName === 'INPUT' || activeEl?.tagName === 'TEXTAREA';
    
    // If an input other than prompt is focused, don't intercept
    if (isInputFocused && !isPromptFocused) return;
    
    const items = e.clipboardData?.items;
    if (!items) return;
    
    for (const item of items) {
      if (item.type.startsWith('image/')) {
        e.preventDefault();
        const file = item.getAsFile();
        if (file) processImageFile(file);
        return;
      }
    }
  });
  
  // Handle drag and drop
  const composerEl = document.getElementById('composer');
  
  // Prevent default drag behaviors on the whole document
  ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    document.addEventListener(eventName, (e) => {
      e.preventDefault();
      e.stopPropagation();
    }, false);
  });
  
  // Highlight drop zone when dragging over composer
  if (composerEl) {
    composerEl.addEventListener('dragenter', () => {
      composerEl.classList.add('drag-over');
    });
    
    composerEl.addEventListener('dragleave', (e) => {
      // Only remove if leaving the composer entirely
      if (!composerEl.contains(e.relatedTarget)) {
        composerEl.classList.remove('drag-over');
      }
    });
    
    composerEl.addEventListener('dragover', (e) => {
      e.dataTransfer.dropEffect = 'copy';
    });
    
    composerEl.addEventListener('drop', (e) => {
      composerEl.classList.remove('drag-over');
      
      const files = e.dataTransfer?.files;
      if (files && files.length > 0) {
        // Process the first file (image or document)
        processFile(files[0]);
      }
    });
  }
  
  // Remove attachment
  attachRemove?.addEventListener('click', () => {
    currentAttachment = null;
    if (attachInput) attachInput.value = '';
    if (attachPreview) attachPreview.classList.add('hidden');
    if (attachPreviewImg) {
      attachPreviewImg.src = '';
      attachPreviewImg.classList.remove('hidden');
    }
    const docIcon = document.getElementById('attach-doc-icon');
    if (docIcon) docIcon.classList.add('hidden');
    if (attachFilename) attachFilename.textContent = '';
    const attachType = document.getElementById('attach-type');
    if (attachType) attachType.textContent = '';
  });
  
  // Helper to clear attachment after sending
  function clearAttachment() {
    currentAttachment = null;
    if (attachInput) attachInput.value = '';
    if (attachPreview) attachPreview.classList.add('hidden');
    if (attachPreviewImg) {
      attachPreviewImg.src = '';
      attachPreviewImg.classList.remove('hidden');
    }
    const docIcon = document.getElementById('attach-doc-icon');
    if (docIcon) docIcon.classList.add('hidden');
    if (attachFilename) attachFilename.textContent = '';
    const attachType = document.getElementById('attach-type');
    if (attachType) attachType.textContent = '';
  }

  // Wire send button click - triggers websearch-style streaming or stops generation
  sendBtn?.addEventListener('click', (e) => {
    try {
      // If currently streaming, abort instead of sending
      if (sendBtn._isStreaming && abortController) {
        abortController.abort();
        return;
      }
      
      const p = (promptEl.value || '').trim();
      if (!p) return;
      promptEl.value = '';
      try { autoResizeComposer(); } catch(e){}
      streamWebSearch(p);
    } catch (e) { console.error('send click error', e); }
  });

  // Escape to cancel
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && abortController) {
      abortController.abort();
    }
  });

  // --- Tool-call handling and UI ---
  // Add an Auto-run toggle (persisted in localStorage) and helper to execute tool_call JSON
  (function setupToolUi() {
    try {
      // Check if placeholder exists in settings panel, otherwise create floating element
      const existingPlaceholder = document.getElementById('auto-run-tools-container');
      if (existingPlaceholder) {
        const key = 'ginto_auto_run_tools';
        const saved = localStorage.getItem(key) === '1';
        existingPlaceholder.innerHTML = `
          <label class="flex items-center justify-between cursor-pointer">
            <span class="text-sm">Auto-run tools</span>
            <input id="auto_run_tools" type="checkbox" ${saved ? 'checked' : ''} 
              class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-500 focus:ring-indigo-500">
          </label>
        `;
        const cb = document.getElementById('auto_run_tools');
        if (cb) cb.addEventListener('change', (e) => { localStorage.setItem(key, e.target.checked ? '1' : '0'); });
        return;
      }
      
      // Fallback: create styled floating element for dark theme
      const ctl = document.createElement('div');
      ctl.style.position = 'fixed';
      ctl.style.right = '12px';
      ctl.style.bottom = '12px';
      ctl.style.background = 'rgba(31, 41, 55, 0.95)';
      ctl.style.border = '1px solid #374151';
      ctl.style.padding = '8px 12px';
      ctl.style.borderRadius = '8px';
      ctl.style.fontSize = '13px';
      ctl.style.zIndex = 9999;
      ctl.style.color = '#d1d5db';
      ctl.style.backdropFilter = 'blur(8px)';
      ctl.id = 'ginto_tool_ui';
      const key = 'ginto_auto_run_tools';
      const saved = localStorage.getItem(key) === '1';
      ctl.innerHTML = '<label style="cursor:pointer;display:flex;align-items:center;gap:8px"><input id="auto_run_tools" type="checkbox" ' + (saved ? 'checked' : '') + ' style="width:16px;height:16px;accent-color:#6366f1"> Auto-run tools</label>';
      document.body.appendChild(ctl);
      const cb = document.getElementById('auto_run_tools');
      cb.addEventListener('change', (e) => { localStorage.setItem(key, e.target.checked ? '1' : '0'); });
    } catch (e) { /* non-critical */ }
  })();

  // Helper: find a JSON object containing a top-level "tool_call" key inside free text.
  function extractToolCallFromText(s) {
    if (!s || typeof s !== 'string') return null;
    // Trim and quick-check if content is pure JSON
    const trimmed = s.trim();
    try {
      if (trimmed.startsWith('{') && trimmed.endsWith('}')) {
        const j = JSON.parse(trimmed);
        // handle several common shapes
        if (j.tool_call) return j.tool_call;
        if (j.tool_calls && Array.isArray(j.tool_calls) && j.tool_calls.length) return j.tool_calls[0];
        // OpenAI-style function_call inside message
        if (j.function_call) return { name: j.function_call.name, arguments: tryParseJsonSafe(j.function_call.arguments) };
        // some servers return { "tool": { name, arguments } }
        if (j.tool && (j.tool.name || j.tool.arguments)) return { name: j.tool.name || j.tool, arguments: j.tool.arguments || {} };
      }
    } catch (e) { /* continue to more flexible parsing below */ }

    // Search for common markers inside larger text blobs
    const markers = ['"tool_call"', '"tool_calls"', '"function_call"', '"function-call"', '"tool"'];
    let found = false;
    for (const m of markers) if (s.indexOf(m) !== -1) { found = true; break; }
    if (!found) return null;

    // Find a nearby JSON object by scanning for braces around the first marker
    const firstMarkerIdx = markers.map(m => s.indexOf(m)).filter(i => i >= 0).sort((a,b) => a-b)[0];
    if (firstMarkerIdx === undefined) return null;
    // try to find a '{' before the marker and parse a balanced object
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
    } catch (e) {
      // fall through
    }

    // fallback: look for simple patterns like {"name":"TOOL","arguments":{...}}
    try {
      const re = /\{[^}]*"name"\s*:\s*"([^\"]+)"[^}]*"arguments"\s*:\s*(\{[\s\S]*\})/i;
      const m = re.exec(s);
      if (m) return { name: m[1], arguments: JSON.parse(m[2]) };
    } catch (e) { /* ignore */ }
    return null;
  }

  // Format tool results in a human-friendly way
  function formatToolResult(toolName, result) {
    const data = result?.result || result;
    
    // Handle upgrade_required responses (ImageGen, etc.)
    if (data?.upgrade_required) {
      const addonName = data.addon_name || 'Premium Addon';
      const addonPrice = data.addon_price || '$500.00/month';
      const features = data.features || [];
      const addonType = data.addon_type || 'unknown';
      
      return `<div class="p-4 bg-gradient-to-r from-purple-100 to-pink-100 dark:from-purple-900/30 dark:to-pink-900/30 border border-purple-300 dark:border-purple-500/40 rounded-xl">
        <div class="flex items-start gap-4">
          <div class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center flex-shrink-0">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
          </div>
          <div class="flex-1">
            <h3 class="text-lg font-bold text-purple-800 dark:text-purple-200 mb-1">${escapeHtml(addonName)}</h3>
            <p class="text-purple-600 dark:text-purple-300 mb-3">${escapeHtml(data.error || 'Upgrade required to access this feature.')}</p>
            <div class="mb-4">
              <div class="text-2xl font-bold text-purple-700 dark:text-purple-200">${escapeHtml(addonPrice)}</div>
            </div>
            ${features.length > 0 ? `
              <ul class="space-y-2 mb-4">
                ${features.map(f => `
                  <li class="flex items-center gap-2 text-sm text-purple-700 dark:text-purple-300">
                    <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    ${escapeHtml(f)}
                  </li>
                `).join('')}
              </ul>
            ` : ''}
            <button onclick="window.showAddonUpgradeModal && window.showAddonUpgradeModal('${escapeHtml(addonType)}')" 
                    class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white font-semibold rounded-lg shadow-lg transition-all transform hover:scale-105">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
              </svg>
              Upgrade Now
            </button>
          </div>
        </div>
      </div>`;
    }
    
    // Handle subscription_active but pending_setup responses
    if (data?.subscription_active && data?.pending_setup) {
      return `<div class="p-4 bg-gradient-to-r from-green-100 to-teal-100 dark:from-green-900/30 dark:to-teal-900/30 border border-green-300 dark:border-green-500/40 rounded-xl">
        <div class="flex items-start gap-4">
          <div class="w-12 h-12 rounded-full bg-gradient-to-br from-green-500 to-teal-500 flex items-center justify-center flex-shrink-0">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <div class="flex-1">
            <h3 class="text-lg font-bold text-green-800 dark:text-green-200 mb-1">Subscription Active!</h3>
            <p class="text-green-600 dark:text-green-300">${escapeHtml(data.error || 'Your subscription is active. Setting up your resources...')}</p>
          </div>
        </div>
      </div>`;
    }
    
    // lightpanda_search - web search results (show summary, not full content)
    if (toolName === 'lightpanda_search') {
      const success = data?.success;
      const content = data?.content || '';
      
      if (!success) {
        const errorMsg = data?.error || 'Search failed';
        return `<div class="p-3 bg-red-100 dark:bg-red-900/20 border border-red-300 dark:border-red-500/30 rounded-lg text-red-700 dark:text-red-300">
          <div class="flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>Web search failed: ${escapeHtml(errorMsg)}</span>
          </div>
        </div>`;
      }
      
      // Parse the markdown content to extract sources OR use pre-extracted sources (from persisted data)
      let sources = data?.sources || [];
      if (sources.length === 0 && content) {
        const sourceRegex = /### Source \d+: (.+?)\nURL: (https?:\/\/[^\n]+)/g;
        let match;
        while ((match = sourceRegex.exec(content)) !== null) {
          sources.push({ title: match[1], url: match[2] });
        }
      }
      
      // Also check for sourcesCount from truncated/persisted data
      const sourcesCount = data?.sourcesCount || sources.length;
      
      // Show a compact summary with expandable full content
      return `<div class="p-3 bg-blue-100 dark:bg-blue-900/20 border border-blue-300 dark:border-blue-500/30 rounded-lg">
        <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300 mb-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <span class="font-medium">Web Search Complete</span>
          <span class="text-xs text-blue-400/70">${sourcesCount} sources found</span>
        </div>
        ${sources.length > 0 ? `
          <div class="space-y-1 text-sm">
            ${sources.slice(0, 5).map(s => `
              <a href="${escapeHtml(s.url)}" target="_blank" rel="noopener" class="flex items-center gap-1 text-blue-400 hover:text-blue-300 truncate">
                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
                ${escapeHtml(s.title.substring(0, 60))}${s.title.length > 60 ? '...' : ''}
              </a>
            `).join('')}
          </div>
        ` : ''}
      </div>`;
    }
    
    if (result?.error || data?.error) {
      const errorMsg = result?.error || data?.error;
      return `<div class="p-3 bg-red-100 dark:bg-red-900/20 border border-red-300 dark:border-red-500/30 rounded-lg text-red-700 dark:text-red-300">
        <div class="flex items-center gap-2">
          <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <span>${escapeHtml(errorMsg)}</span>
        </div>
      </div>`;
    }
    
    // sandbox_list_files - show as a nice file tree
    if (toolName === 'sandbox_list_files' && data?.tree) {
      const tree = data.tree;
      const files = [];
      const folders = [];
      
      for (const [name, info] of Object.entries(tree)) {
        if (info.type === 'folder') {
          folders.push(name);
        } else {
          files.push(name);
        }
      }
      
      let html = '<div class="space-y-2">';
      html += '<p class="text-gray-700 dark:text-gray-300">Here are your files:</p>';
      html += '<div class="bg-gray-100 dark:bg-gray-800/50 rounded-lg p-3 font-mono text-sm">';
      
      // Show folders first
      for (const folder of folders.sort()) {
        html += `<div class="flex items-center gap-2 text-blue-600 dark:text-blue-400 py-0.5">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
          </svg>
          <span>${escapeHtml(folder)}/</span>
        </div>`;
      }
      
      // Then files
      for (const file of files.sort()) {
        html += `<div class="flex items-center gap-2 text-gray-700 dark:text-gray-300 py-0.5">
          <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          <span>${escapeHtml(file)}</span>
        </div>`;
      }
      
      if (folders.length === 0 && files.length === 0) {
        html += '<div class="text-gray-500 italic">This folder is empty</div>';
      }
      
      html += '</div></div>';
      return html;
    }
    
    // sandbox_read_file - show file content (support images, PDFs, and binary previews)
    if (toolName === 'sandbox_read_file' && data?.content !== undefined) {
      const path = data.path || 'file';
      const content = data.content || '';
      const encoding = data.encoding || '';
      const isBinary = data.is_binary || encoding === 'base64';
      const ext = path.split('.').pop()?.toLowerCase() || '';

      const extToMime = (e) => ({
        png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', gif: 'image/gif', webp: 'image/webp', svg: 'image/svg+xml',
        pdf: 'application/pdf', docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', doc: 'application/msword', odt: 'application/vnd.oasis.opendocument.text'
      }[e] || 'application/octet-stream');

      if (isBinary && ['png','jpg','jpeg','gif','webp','svg','pdf'].includes(ext)) {
        const mime = extToMime(ext);
        const dataUrl = `data:${mime};base64,${content}`;
        if (ext === 'pdf') {
          return `<div class="p-3 bg-gray-50 dark:bg-gray-900/20 rounded-lg">
            <p class="text-gray-700 dark:text-gray-300">Preview: <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">${escapeHtml(path)}</code></p>
            <iframe src="${dataUrl}" class="w-full h-96 border rounded mt-2"></iframe>
          </div>`;
        }
        return `<div class="p-3 bg-gray-50 dark:bg-gray-900/20 rounded-lg">
          <p class="text-gray-700 dark:text-gray-300">Preview: <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">${escapeHtml(path)}</code></p>
          <div class="mt-2 text-center"><img src="${dataUrl}" alt="${escapeHtml(path)}" class="max-w-full max-h-96 rounded"/></div>
        </div>`;
      }

      if (isBinary && ['docx','doc','odt','rtf'].includes(ext)) {
        const sandboxId = data.sandbox_id || window.editorConfig?.sandboxId || '';
        const clientUrl = sandboxId ? ('/clients/' + sandboxId + '/' + path.replace(/^\//, '')) : ('/clients/' + path.replace(/^\//, ''));
        const officeViewer = 'https://view.officeapps.live.com/op/view.aspx?src=' + encodeURIComponent(window.location.origin + clientUrl);
        return `<div class="p-3 bg-gray-50 dark:bg-gray-900/20 rounded-lg">
          <p class="text-gray-700 dark:text-gray-300">File: <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">${escapeHtml(path)}</code></p>
          <div class="mt-2 flex gap-2">
            <a href="${clientUrl}" target="_blank" rel="noopener" class="px-3 py-2 bg-blue-600 text-white rounded">Open / Download</a>
            <a href="${officeViewer}" target="_blank" rel="noopener" class="px-3 py-2 bg-indigo-600 text-white rounded">View in Office Online</a>
          </div>
        </div>`;
      }

      // fallback: text
      const safeContent = encoding === 'base64' ? '(binary file — open to download)' : content;
      return `<div class="space-y-2">
        <p class="text-gray-700 dark:text-gray-300">Contents of <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">${escapeHtml(path)}</code>:</p>
        <pre class="bg-gray-100 dark:bg-gray-800/50 rounded-lg p-3 text-sm overflow-x-auto max-h-96 text-gray-800 dark:text-gray-200"><code>${escapeHtml(safeContent)}</code></pre>
      </div>`;
    }
    
    // sandbox_write_file - confirm file was written with download link
    if (toolName === 'sandbox_write_file' && data?.success) {
      const path = data.path || 'file';
      const bytes = data.bytes_written || 0;
      const url = data.url || '/clients/' + path.replace(/^\//, '');
      return `<div class="flex items-center justify-between gap-2 p-3 bg-green-100 dark:bg-green-900/20 border border-green-300 dark:border-green-500/30 rounded-lg text-green-700 dark:text-green-300">
        <div class="flex items-center gap-2">
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
          </svg>
          <span>Created <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">${escapeHtml(path)}</code>${bytes > 0 ? ` (${bytes} bytes)` : ''}</span>
        </div>
        <a href="${escapeHtml(url)}" target="_blank" class="flex items-center gap-1 px-2 py-1 bg-green-200 hover:bg-green-300 dark:bg-green-600/30 dark:hover:bg-green-600/50 rounded text-sm transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
          </svg>
          Open
        </a>
      </div>`;
    }
    
    // sandbox_create_document - show document creation with format info and download
    if (toolName === 'sandbox_create_document' && data?.success) {
      const filename = data.filename || 'document';
      const format = data.format || 'Document';
      const formatKey = data.format_key || 'txt';
      const bytes = data.bytes_written || 0;
      const url = data.url || '/clients/' + (data.path || '').replace(/^\//, '');
      const openHint = data.open_hint || '';
      
      // Format-specific icon and color - including PDF and DOCX
      const formatStyles = {
        pdf: { icon: '📕', bg: 'bg-red-100 dark:bg-red-900/20', border: 'border-red-300 dark:border-red-500/30', text: 'text-red-700 dark:text-red-300', btn: 'bg-red-200 hover:bg-red-300 dark:bg-red-600/30 dark:hover:bg-red-600/50' },
        docx: { icon: '📘', bg: 'bg-blue-100 dark:bg-blue-900/20', border: 'border-blue-300 dark:border-blue-500/30', text: 'text-blue-700 dark:text-blue-300', btn: 'bg-blue-200 hover:bg-blue-300 dark:bg-blue-600/30 dark:hover:bg-blue-600/50' },
        odt: { icon: '📙', bg: 'bg-orange-100 dark:bg-orange-900/20', border: 'border-orange-300 dark:border-orange-500/30', text: 'text-orange-700 dark:text-orange-300', btn: 'bg-orange-200 hover:bg-orange-300 dark:bg-orange-600/30 dark:hover:bg-orange-600/50' },
        html: { icon: '🌐', bg: 'bg-cyan-100 dark:bg-cyan-900/20', border: 'border-cyan-300 dark:border-cyan-500/30', text: 'text-cyan-700 dark:text-cyan-300', btn: 'bg-cyan-200 hover:bg-cyan-300 dark:bg-cyan-600/30 dark:hover:bg-cyan-600/50' },
        rtf: { icon: '📄', bg: 'bg-purple-100 dark:bg-purple-900/20', border: 'border-purple-300 dark:border-purple-500/30', text: 'text-purple-700 dark:text-purple-300', btn: 'bg-purple-200 hover:bg-purple-300 dark:bg-purple-600/30 dark:hover:bg-purple-600/50' },
        md: { icon: '📝', bg: 'bg-gray-100 dark:bg-gray-800/50', border: 'border-gray-300 dark:border-gray-500/30', text: 'text-gray-700 dark:text-gray-300', btn: 'bg-gray-200 hover:bg-gray-300 dark:bg-gray-600/30 dark:hover:bg-gray-600/50' },
        txt: { icon: '📃', bg: 'bg-gray-100 dark:bg-gray-800/50', border: 'border-gray-300 dark:border-gray-500/30', text: 'text-gray-700 dark:text-gray-300', btn: 'bg-gray-200 hover:bg-gray-300 dark:bg-gray-600/30 dark:hover:bg-gray-600/50' }
      };
      const style = formatStyles[formatKey] || formatStyles.txt;
      
      // Format file size nicely
      const formatSize = (bytes) => {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
      };
      
      // If conversion was enqueued, show a placeholder that will be updated by a poller
      if (data.job_id) {
        const jobId = data.job_id;
        const htmlUrl = data.url_html || '';
        const pdfUrl = url;
        return `<div class="p-4 ${style.bg} border ${style.border} rounded-lg">
          <div class="flex items-center justify-between gap-3 mb-2">
            <div class="flex items-center gap-3 ${style.text}">
              <span class="text-2xl">${style.icon}</span>
              <div>
                <div class="font-semibold text-base max-w-[12rem] sm:max-w-full truncate">${escapeHtml(filename)}</div>
                <div class="text-sm opacity-75">${escapeHtml(format)} • Conversion queued</div>
              </div>
            </div>
          </div>
          <div class="mt-2 sandbox-doc-result" data-job-id="${escapeHtml(jobId)}" data-pdf-url="${escapeHtml(pdfUrl)}" data-html-url="${escapeHtml(htmlUrl)}" data-filename="${escapeHtml(filename)}">
            <div class="text-sm">Conversion queued — preparing PDF. <a href="${escapeHtml(htmlUrl)}" target="_blank">Open HTML</a></div>
          </div>
        </div>`;
      }

      return `<div class="p-4 ${style.bg} border ${style.border} rounded-lg">
        <div class="flex items-center gap-3 mb-2">
          <span class="text-2xl">${style.icon}</span>
          <div class="${style.text}">
              <div class="font-semibold text-base max-w-[12rem] sm:max-w-full truncate">${escapeHtml(filename)}</div>
            <div class="text-sm opacity-75">${escapeHtml(format)}${bytes > 0 ? ` • ${formatSize(bytes)}` : ''}</div>
          </div>
        </div>

        <div class="mt-3 flex justify-end">
          <a href="${escapeHtml(url)}" target="_blank" download="${escapeHtml(filename)}" class="flex items-center gap-2 px-4 py-2 ${style.btn} rounded-lg text-sm font-semibold transition-colors ${style.text}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Download
          </a>
        </div>

        ${openHint ? `<div class="text-xs ${style.text} opacity-60 mt-2">💡 ${escapeHtml(openHint)}</div>` : ''}
      </div>`;
    }
    
    // sandbox_list_document_formats - show available formats
    if (toolName === 'sandbox_list_document_formats' && data?.formats) {
      const formats = data.formats || [];
      return `<div class="p-4 bg-gray-100 dark:bg-gray-800/50 rounded-lg">
        <div class="font-medium text-gray-800 dark:text-gray-200 mb-3">📄 Available Document Formats</div>
        <div class="grid gap-2">
          ${formats.map(f => `
            <div class="flex items-center gap-3 p-2 bg-gray-200/50 dark:bg-gray-700/30 rounded">
              <code class="bg-gray-300 dark:bg-gray-600 px-2 py-0.5 rounded text-sm text-blue-600 dark:text-blue-300">${escapeHtml(f.format)}</code>
              <span class="text-gray-700 dark:text-gray-300">${escapeHtml(f.name)}</span>
              <span class="text-gray-500 text-sm">${escapeHtml(f.extension)}</span>
            </div>
          `).join('')}
        </div>
        ${data.tip ? `<div class="text-xs text-gray-500 dark:text-gray-400 mt-3">💡 ${escapeHtml(data.tip)}</div>` : ''}
      </div>`;
    }
    
    // sandbox_delete - confirm file was deleted
    if (toolName === 'sandbox_delete' && data?.success) {
      const path = data.path || 'file';
      return `<div class="flex items-center gap-2 p-3 bg-amber-100 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-500/30 rounded-lg text-amber-700 dark:text-amber-300">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
        </svg>
        <span>Deleted <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">${escapeHtml(path)}</code></span>
      </div>`;
    }
    
    // sandbox_exec - show command output
    if (toolName === 'sandbox_exec') {
      const output = data?.output || data?.stdout || '';
      const exitCode = data?.exit_code ?? data?.exitCode ?? 0;
      const isError = exitCode !== 0;
      
      return `<div class="space-y-2">
        <div class="flex items-center gap-2 ${isError ? 'text-red-700 dark:text-red-300' : 'text-green-700 dark:text-green-300'}">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
          <span>Command ${isError ? 'failed' : 'completed'}</span>
        </div>
        ${output ? `<pre class="bg-gray-100 dark:bg-gray-800/50 rounded-lg p-3 text-sm overflow-x-auto max-h-64 text-gray-800 dark:text-gray-300"><code>${escapeHtml(output)}</code></pre>` : ''}
      </div>`;
    }
    
    // generate_image - show generated AI images
    if (toolName === 'generate_image') {
      const normalizeImageEntry = (entry) => {
        if (!entry) return null;
        if (typeof entry === 'string') {
          return { dataUrl: entry, url: entry };
        }
        if (typeof entry !== 'object') return null;

        if (typeof entry.b64_json === 'string' && entry.b64_json) {
          return {
            dataUrl: `data:image/png;base64,${entry.b64_json}`,
            url: `data:image/png;base64,${entry.b64_json}`,
          };
        }

        const directUrl =
          (typeof entry.url === 'string' && entry.url) ||
          (typeof entry.image_url === 'string' && entry.image_url) ||
          (typeof entry.imageUrl === 'string' && entry.imageUrl) ||
          (typeof entry.dataUrl === 'string' && entry.dataUrl) ||
          '';

        if (directUrl) {
          return { dataUrl: directUrl, url: directUrl };
        }

        return null;
      };

      const directOpenAiImages = Array.isArray(data?.data)
        ? data.data
            .map(normalizeImageEntry)
            .filter(Boolean)
        : [];

      const images = (Array.isArray(data?.images) && data.images.length > 0)
        ? data.images.map(normalizeImageEntry).filter(Boolean)
        : directOpenAiImages;

      if (images.length > 0) {
        const prompt = data.prompt || 'AI Generated Image';
        const model =
          data.model ||
          data.image_model ||
          data.generation_model ||
          data?.request?.model ||
          data?.request_body?.model ||
          data?.metadata?.model ||
          data?.meta?.model ||
          data?.data?.[0]?.model ||
          'Image Model';
        const text = data.text || '';
        
        return `<div class="p-4 bg-gradient-to-br from-slate-50 to-gray-100 dark:from-slate-800/50 dark:to-gray-900/50 border border-slate-200 dark:border-slate-600/30 rounded-xl shadow-sm">
          <div class="flex items-center gap-2 text-slate-700 dark:text-slate-200 mb-3">
            <svg class="w-5 h-5 text-indigo-500 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span class="font-semibold">Image Generated</span>
            <span class="text-xs text-slate-500 dark:text-slate-400 ml-auto">${escapeHtml(model)}</span>
          </div>
          ${text ? `<p class="text-slate-600 dark:text-slate-300 text-sm mb-3">${escapeHtml(text)}</p>` : ''}
          <div class="flex flex-wrap gap-3">
            ${images.map((img, idx) => `
              <div class="relative group">
                <img 
                  src="${escapeHtml(img.dataUrl || img.url)}" 
                  alt="${escapeHtml(prompt)}"
                  class="max-w-[200px] max-h-[200px] rounded-xl shadow-md cursor-pointer hover:shadow-lg hover:scale-105 transition-all border border-slate-200 dark:border-slate-600/50"
                  onclick="window.showImageModal && window.showImageModal('${escapeHtml(img.url || img.dataUrl)}')"
                  loading="lazy"
                  title="Click to view full size"
                />
              </div>
            `).join('')}
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-3 italic">"${escapeHtml(prompt.length > 100 ? prompt.substring(0, 100) + '...' : prompt)}"</p>
        </div>`;
      } else {
        // Error case
        const errorMsg = data?.error || 'Image generation failed';
        return `<div class="p-3 bg-red-100 dark:bg-red-900/20 border border-red-300 dark:border-red-500/30 rounded-lg text-red-700 dark:text-red-300">
          <div class="flex items-center gap-2">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>${escapeHtml(errorMsg)}</span>
          </div>
        </div>`;
      }
    }
    
    // image_gen_status - show status
    if (toolName === 'image_gen_status') {
      const available = data?.available || false;
      const message = data?.message || (available ? 'Image generation is available' : 'Image generation not configured');
      return `<div class="p-3 ${available ? 'bg-green-100 dark:bg-green-900/20 border-green-300 dark:border-green-500/30 text-green-700 dark:text-green-300' : 'bg-yellow-100 dark:bg-yellow-900/20 border-yellow-300 dark:border-yellow-500/30 text-yellow-700 dark:text-yellow-300'} border rounded-lg">
        <div class="flex items-center gap-2">
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${available ? 'M5 13l4 4L19 7' : 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'}"/>
          </svg>
          <span>${escapeHtml(message)}</span>
        </div>
      </div>`;
    }
    
    // sandbox_create_file - confirm created
    if (toolName === 'sandbox_create_file' && data?.success) {
      const path = data.path || 'item';
      const type = data.type || 'file';
      return `<div class="flex items-center gap-2 p-3 bg-green-100 dark:bg-green-900/20 border border-green-300 dark:border-green-500/30 rounded-lg text-green-700 dark:text-green-300">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <span>Created ${type} <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">${escapeHtml(path)}</code></span>
      </div>`;
    }
    
    // sandbox_create_project - show project creation result
    if (toolName === 'sandbox_create_project') {
      if (data?.success) {
        const files = data.files_created || [];
        const projectName = data.project_name || 'project';
        const projectType = data.template_name || data.project_type || 'Project';
        const runHint = data.run_hint || '';
        return `<div class="p-4 bg-green-100 dark:bg-green-900/20 border border-green-300 dark:border-green-500/30 rounded-lg">
          <div class="flex items-center gap-2 text-green-700 dark:text-green-300 mb-3">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="font-semibold text-lg">${escapeHtml(projectType)} Created!</span>
          </div>
          <div class="mb-3 text-gray-700 dark:text-gray-300">
            <span class="text-gray-500 dark:text-gray-400">Project:</span> <code class="bg-gray-200 dark:bg-gray-700 px-2 py-0.5 rounded text-green-700 dark:text-green-300">${escapeHtml(projectName)}</code>
          </div>
          <details class="mb-3">
            <summary class="cursor-pointer text-gray-500 dark:text-gray-400 text-sm hover:text-gray-600 dark:hover:text-gray-300">${files.length} files created</summary>
            <ul class="mt-2 text-sm text-gray-500 dark:text-gray-400 space-y-1 ml-4">
              ${files.map(f => `<li class="flex items-center gap-1"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/></svg> ${escapeHtml(f)}</li>`).join('')}
            </ul>
          </details>
          ${runHint ? `<div class="p-2 bg-gray-100 dark:bg-gray-800/50 rounded text-sm text-gray-700 dark:text-gray-300"><span class="text-gray-500">To run:</span> <code class="text-blue-600 dark:text-blue-300">${escapeHtml(runHint)}</code></div>` : ''}
        </div>`;
      } else {
        return `<div class="p-3 bg-red-100 dark:bg-red-900/20 border border-red-300 dark:border-red-500/30 rounded-lg text-red-700 dark:text-red-300">
          <div class="flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>Failed to create project: ${escapeHtml(data?.error || 'Unknown error')}</span>
          </div>
          ${data?.available_types ? `<div class="mt-2 text-sm text-gray-500 dark:text-gray-400">Available types: ${data.available_types.join(', ')}</div>` : ''}
        </div>`;
      }
    }
    
    // sandbox_list_project_types - show available templates
    if (toolName === 'sandbox_list_project_types' && data?.success) {
      const types = data.project_types || [];
      return `<div class="p-4 bg-gray-100 dark:bg-gray-800/50 border border-gray-300 dark:border-gray-700 rounded-lg">
        <div class="flex items-center gap-2 text-gray-800 dark:text-gray-200 mb-3">
          <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
          </svg>
          <span class="font-semibold">Available Project Templates</span>
        </div>
        <div class="grid gap-2">
          ${types.map(t => `<div class="p-2 bg-gray-200/50 dark:bg-gray-900/50 rounded flex items-center justify-between">
            <div>
              <code class="text-blue-600 dark:text-blue-300 font-mono">${escapeHtml(t.type)}</code>
              <span class="text-gray-600 dark:text-gray-400 ml-2">${escapeHtml(t.name)}</span>
            </div>
            <span class="text-xs text-gray-500">${escapeHtml(t.description)}</span>
          </div>`).join('')}
        </div>
        <div class="mt-3 text-sm text-gray-600 dark:text-gray-400">Use: "Create a [type] project called [name]"</div>
      </div>`;
    }
    
    // Default: show success message or raw data
    if (data?.success) {
      const msg = data.message || 'Done!';
      return `<div class="flex items-center gap-2 p-3 bg-green-100 dark:bg-green-900/20 border border-green-300 dark:border-green-500/30 rounded-lg text-green-700 dark:text-green-300">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <span>${escapeHtml(msg)}</span>
      </div>`;
    }
    
    // Fallback: show raw JSON in a collapsible
    return `<details class="mt-2">
      <summary class="cursor-pointer text-gray-400 text-sm">View raw result</summary>
      <pre class="mt-1 bg-gray-800/50 rounded-lg p-2 text-xs overflow-x-auto">${escapeHtml(JSON.stringify(data, null, 2))}</pre>
    </details>`;
  }

  // Execute a tool_call via /mcp/call or /sandbox/call. Returns promise resolving to result object.
  // Uses /sandbox/call for sandbox_* tools (works for non-admin users with active sandbox)
  // Uses /mcp/call for all other tools (admin only)
  async function executeToolCall(toolCall) {
    if (!toolCall) throw new Error('invalid toolCall');
    // Normalize the tool name and arguments from several possible shapes
    let name = toolCall.name || toolCall.function?.name || toolCall.function_name || toolCall.tool || null;
    let args = toolCall.arguments || toolCall.args || toolCall.function?.arguments || {};
    // If args is a string (some function_call payloads send JSON as string), try to parse
    if (typeof args === 'string') args = tryParseJsonSafe(args) || {};
    if (!name) throw new Error('toolCall missing name');

    const body = { tool: name, args: args };
    // Use /sandbox/call for sandbox-prefixed tools and ginto_install (available to all users)
    // Use /mcp/call for other tools (admin-only)
    const endpoint = (name.startsWith('sandbox_') || name === 'ginto_install') ? '/sandbox/call' : '/mcp/call';
    const res = await fetch(endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || document.getElementById('csrf_token')?.value || '' }, body: JSON.stringify(body) });
    
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
        }
        throw new Error(errorData.error || 'Premium subscription required');
      }
      if (errorData?.action === 'login') {
        // Visitor trying sandbox tools - show agentic features modal
        if (typeof window.showAgenticModal === 'function') {
          window.showAgenticModal(errorData.error || 'Create a free account to unlock file management and agentic features.');
        } else if (confirm((errorData.error || 'Please log in to continue.') + '\n\nWould you like to go to the login page?')) {
          window.location.href = '/login';
        }
        throw new Error(errorData.error || 'Login required');
      }
      
      throw new Error('HTTP ' + res.status + ': ' + txt);
    }
    const j = await res.json().catch(()=>null);
    
    // Handle special action: ginto_install - open web terminal with the install command
    if (j?.action === 'ginto_install' || j?.result?.action === 'ginto_install') {
      // Use command from server, or build from GINTO_PATH global
      const gintoPath = window.GINTO_PATH || '/home/test/ginto.ai';
      const cmd = j?.result?.command || ('sudo bash ' + gintoPath + '/bin/ginto.sh install');
      
      // Try to open the web console terminal with the command (admin only)
      if (typeof window.openConsoleWithCommand === 'function') {
        window.openConsoleWithCommand(cmd);
      } else {
        // Fallback: show the LXC installation wizard step with manual instructions
        const modal = document.getElementById('sandbox-wizard-modal');
        if (modal) {
          modal.classList.remove('hidden');
          if (typeof showWizardStep === 'function') {
            showWizardStep('lxc-install');
          }
        } else {
          // Final fallback: show command in an alert
          alert('To install Ginto, run this command in your server SSH terminal:\n\n' + cmd);
        }
      }
    }
    
    // Handle special action: install_sandbox - triggers the sandbox wizard
    if (j?.action === 'install_sandbox' || j?.result?.action === 'install_sandbox') {
      // Call the showSandboxWizard function if available
      if (typeof window.showSandboxWizard === 'function') {
        window.showSandboxWizard();
      } else if (typeof showSandboxWizard === 'function') {
        showSandboxWizard();
      } else {
        // Fallback: try to find and show the wizard modal directly
        const modal = document.getElementById('sandbox-wizard-modal');
        if (modal) {
          modal.classList.remove('hidden');
          // Try to show step 1 if the function exists
          if (typeof showWizardStep === 'function') showWizardStep(1);
        } else {
          console.warn('Sandbox wizard not found, redirecting to manual setup');
          alert('Let\'s set up your sandbox! Click "My Files" in the sidebar to get started.');
        }
      }
    }
    
    return j;
  }

  // Helper: Send a custom prompt and handle the response for agent continuation
  // Used for review/retry prompts when no tool_call is detected
  async function continueAgentPlanWithPrompt(card, customPrompt, previousResponse, depth, completedSteps, originalRequest = '') {
    const MAX_DEPTH = window.GINTO_CONFIG?.agentPlan?.maxToolCallsPerPlan || 15;
    if (depth >= MAX_DEPTH) return;
    
    console.log('[continueAgentPlanWithPrompt] Sending review prompt, depth:', depth);
    
    const continuationHistory = [...history];
    if (previousResponse && (!continuationHistory.length || continuationHistory[continuationHistory.length - 1].content !== previousResponse)) {
      continuationHistory.push({ role: 'assistant', content: previousResponse });
    }
    
    const csrfToken = window.CSRF_TOKEN || document.getElementById('csrf_token')?.value || '';
    const bodyParams = new URLSearchParams();
    bodyParams.append('prompt', customPrompt);
    bodyParams.append('history', JSON.stringify(continuationHistory));
    if (csrfToken) bodyParams.append('csrf_token', csrfToken);
    
    const res = await fetch('/chat', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrfToken },
      body: bodyParams.toString()
    });
    
    if (!res.ok) return;
    
    const reader = res.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let aiResponse = '';
    
    while (true) {
      const { done, value } = await reader.read();
      if (done) break;
      buffer += decoder.decode(value, { stream: true });
      const lines = buffer.split('\n');
      buffer = lines.pop() || '';
      for (const line of lines) {
        if (!line.startsWith('data: ')) continue;
        const jsonStr = line.slice(6).trim();
        if (!jsonStr || jsonStr === '[DONE]') continue;
        try {
          const data = JSON.parse(jsonStr);
          const parts = extractOpenAiContentPartsFromPayload(data);
          if (parts.text) aiResponse += sanitizeOpenAiTextChunk(parts.text);
          else if (data.text) aiResponse += sanitizeOpenAiTextChunk(data.text);
        } catch (e) { /* ignore */ }
      }
    }
    
    if (!aiResponse) return;
    
    // Check if there's a tool call in the review response
    const nextToolCall = extractToolCallFromText(aiResponse);
    if (nextToolCall && nextToolCall.name && nextToolCall.name.startsWith('sandbox_')) {
      console.log('[continueAgentPlanWithPrompt] Review found tool call:', nextToolCall.name);
      
      const execNote = document.createElement('div');
      execNote.className = 'mt-3 p-3 bg-gray-800/50 border border-gray-700 rounded-lg';
      execNote.innerHTML = '<div class="flex items-center gap-2 text-gray-300"><svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><span>Executing ' + escapeHtml(nextToolCall.name) + '...</span></div>';
      if (card?.response) card.response.appendChild(execNote);
      
      try {
        const nextResult = await executeToolCall(nextToolCall);
        execNote.className = 'mt-3';
        execNote.innerHTML = formatToolResult(nextToolCall.name, nextResult);
        
        if (nextResult?.success !== false) {
          // IMPORTANT: Filter out __review_retry__ markers so the loop can review again if needed
          const cleanedSteps = completedSteps.filter(s => s.tool !== '__review_retry__');
          await continueAgentPlan(card, nextToolCall.name, nextToolCall.arguments || {}, nextResult, aiResponse, depth + 1, cleanedSteps, originalRequest);
        }
      } catch (e) {
        console.error('[continueAgentPlanWithPrompt] Tool failed:', e);
        execNote.innerHTML = '<div class="text-red-400">Tool failed: ' + escapeHtml(e.message) + '</div>';
      }
    } else {
      // Model confirmed completion
      console.log('[continueAgentPlanWithPrompt] Model confirmed complete');
      const doneNote = document.createElement('div');
      doneNote.className = 'mt-3 p-3 bg-green-100 dark:bg-green-900/20 border border-green-300 dark:border-green-500/30 rounded-lg text-green-700 dark:text-green-300';
      doneNote.innerHTML = '<div class="flex items-center gap-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg><span>All steps completed!</span></div>';
      if (card?.response) card.response.appendChild(doneNote);
    }
  }

  // Continue agent plan execution after a tool completes
  // This sends the tool result back to the AI to get the next step
  // completedSteps tracks what's been done to prevent re-planning
  // originalRequest is the user's original prompt so we know what was asked
  async function continueAgentPlan(card, toolName, toolArguments, toolResult, previousResponse, depth = 0, completedSteps = [], originalRequest = '') {
    // Prevent infinite loops - max tool calls per plan (configurable from server)
    const MAX_DEPTH = window.GINTO_CONFIG?.agentPlan?.maxToolCallsPerPlan || 15;
    if (depth >= MAX_DEPTH) {
      console.log('[continueAgentPlan] Max depth reached, stopping');
      const summaryNote = document.createElement('div');
      summaryNote.className = 'mt-3 p-3 bg-blue-100 dark:bg-blue-900/20 border border-blue-300 dark:border-blue-500/30 rounded-lg text-blue-700 dark:text-blue-300';
      summaryNote.innerHTML = '<div class="flex items-center gap-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>Plan execution complete (max steps reached)</span></div>';
      if (card?.response) card.response.appendChild(summaryNote);
      return;
    }
    
    // Track this completed step (include arguments for duplicate detection)
    const stepRecord = {
      tool: toolName,
      arguments: toolArguments || {},
      success: toolResult?.success !== false,
      summary: toolResult?.message || (toolResult?.files ? `Listed ${toolResult.files.length} files` : 'Completed')
    };
    completedSteps = [...completedSteps, stepRecord];
    
    // Build history for continuation - include the previous response and tool result
    // The AI needs context of what it said and what the tool returned
    const toolResultSummary = toolResult?.success 
      ? `Tool executed successfully. ${toolResult?.message || ''}`
      : `Tool failed: ${toolResult?.error || 'Unknown error'}`;
    
    // Build a clearer continuation prompt that shows progress
    const stepsCompletedText = completedSteps.map((s, i) => 
      `  ${i + 1}. ${s.tool}(${JSON.stringify(s.arguments || {}).substring(0, 50)}): ${s.success ? '✓' : '✗'} ${s.summary}`
    ).join('\n');
    
    // Simple continuation prompt - include original request so model knows what to complete
    const continuePrompt = `[TOOL RESULT] ${toolResult?.message || toolResult?.path || 'Done'}

ORIGINAL REQUEST: "${originalRequest}"

Steps completed: ${completedSteps.length}
${stepsCompletedText}

Have you finished the ORIGINAL REQUEST above? 
- If NOT done yet: output the next tool_call
- If DONE: say "Done" with a 1-sentence summary`;
    
    console.log('[continueAgentPlan] Continuing plan, depth:', depth, 'completedSteps:', completedSteps.length);
    
    // Show continuation indicator
    const continueNote = document.createElement('div');
    continueNote.className = 'mt-3 p-2 bg-gray-800/30 border-l-2 border-blue-500 text-gray-400 text-sm';
    continueNote.innerHTML = '<div class="flex items-center gap-2"><svg class="w-3 h-3 animate-pulse" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg><span>Continuing...</span></div>';
    if (card?.response) card.response.appendChild(continueNote);
    
    try {
      // Build history for the continuation request
      // We need to include the previous assistant response and the tool result as a user message
      const continuationHistory = [...history];
      
      // If the previous response isn't in history yet, add it
      if (previousResponse && (!continuationHistory.length || continuationHistory[continuationHistory.length - 1].content !== previousResponse)) {
        continuationHistory.push({ role: 'assistant', content: previousResponse });
      }
      
      // Call the chat endpoint to continue - use same format as main chat
      const csrfToken = window.CSRF_TOKEN || document.getElementById('csrf_token')?.value || '';
      const bodyParams = new URLSearchParams();
      bodyParams.append('prompt', continuePrompt);
      bodyParams.append('history', JSON.stringify(continuationHistory));
      if (csrfToken) bodyParams.append('csrf_token', csrfToken);
      
      console.log('[continueAgentPlan] Sending continuation request, history length:', continuationHistory.length);
      console.log('[continueAgentPlan] Prompt:', continuePrompt.substring(0, 200));
      
      const res = await fetch('/chat', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-CSRF-Token': csrfToken
        },
        body: bodyParams.toString()
      });
      
      console.log('[continueAgentPlan] Response status:', res.status);
      
      if (!res.ok) {
        console.error('[continueAgentPlan] Bad response:', res.status);
        continueNote.remove();
        return;
      }
      
      // Read streaming response
      const reader = res.body.getReader();
      const decoder = new TextDecoder();
      let buffer = '';
      let aiResponse = '';
      let chunkCount = 0;
      
      while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        
        buffer += decoder.decode(value, { stream: true });
        const lines = buffer.split('\n');
        buffer = lines.pop() || '';
        
        for (const line of lines) {
          if (!line.startsWith('data: ')) continue;
          const jsonStr = line.slice(6).trim();
          if (!jsonStr || jsonStr === '[DONE]') continue;
          
          try {
            const data = JSON.parse(jsonStr);
            chunkCount++;
            // Handle content chunks - multiple formats
            const parts = extractOpenAiContentPartsFromPayload(data);
            if (parts.text) {
              aiResponse += sanitizeOpenAiTextChunk(parts.text);
            } else if (data.text) {
              aiResponse += sanitizeOpenAiTextChunk(data.text);
            }
          } catch (e) {
            console.log('[continueAgentPlan] Parse error on line:', line.substring(0, 100));
          }
        }
      }
      
      // Remove continuation indicator
      continueNote.remove();
      
      console.log('[continueAgentPlan] Streaming complete. Chunks:', chunkCount, 'Response length:', aiResponse.length);
      console.log('[continueAgentPlan] AI response preview:', aiResponse.substring(0, 500));
      
      if (!aiResponse) {
        console.log('[continueAgentPlan] No AI response received, stopping continuation');
        return;
      }
      
      // Add AI's continuation response to the card
      const contDiv = document.createElement('div');
      contDiv.className = 'mt-4 pt-4 border-t border-gray-700';
      
      // Parse markdown if parsedown available, otherwise use simple formatting
      let formattedContent = aiResponse;
      // Clean tool_call JSON from display
      const toolCallPatterns = [
        /\{"tool_call"\s*:\s*\{[^}]*"name"\s*:\s*"sandbox_[^"]*"[^}]*"arguments"\s*:\s*\{[^}]*\}[^}]*\}\}/g,
        /\{"tool_call"\s*:\s*\{[^}]*\}\}/g,
        /```json\s*\{"tool_call"[\s\S]*?\}\s*```/g,
        /\{["\s]*tool_call["\s]*:[\s\S]*?sandbox_[^}]*\}\s*\}/g
      ];
      for (const pattern of toolCallPatterns) {
        formattedContent = formattedContent.replace(pattern, '');
      }
      formattedContent = formattedContent.replace(/<p>\s*<\/p>/g, '').trim();
      
      if (formattedContent) {
        const formattedHtml = `<div class="prose prose-invert prose-sm max-w-none">${escapeHtml(formattedContent).replace(/\n/g, '<br>')}</div>`;
        contDiv.innerHTML = formattedHtml;
        if (card?.response) card.response.appendChild(contDiv);
        // Add to history with HTML for persistence
        history.push({ role: 'assistant', content: aiResponse, html: formattedHtml, ts: Date.now() });
      } else {
        // Add to history without HTML
        history.push({ role: 'assistant', content: aiResponse, ts: Date.now() });
      }
      
      // Check if there's another tool call in this response
      const nextToolCall = extractToolCallFromText(aiResponse);
      if (nextToolCall && nextToolCall.name && nextToolCall.name.startsWith('sandbox_')) {
        // Check if this EXACT tool call (same name AND same arguments) was already executed
        // This prevents infinite loops calling the same thing, but ALLOWS calling same tool with different args
        const toolSignature = `${nextToolCall.name}:${JSON.stringify(nextToolCall.arguments || {})}`;
        const alreadyExecuted = completedSteps.some(s => {
          const stepSignature = `${s.tool}:${JSON.stringify(s.arguments || {})}`;
          return stepSignature === toolSignature; // Only block if EXACT same call (name + args)
        });
        
        if (alreadyExecuted) {
          console.log('[continueAgentPlan] Exact same tool call already executed, stopping to prevent loop:', toolSignature);
          const doneNote = document.createElement('div');
          doneNote.className = 'mt-3 p-3 bg-green-100 dark:bg-green-900/20 border border-green-300 dark:border-green-500/30 rounded-lg text-green-700 dark:text-green-300';
          doneNote.innerHTML = '<div class="flex items-center gap-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg><span>Plan complete!</span></div>';
          if (card?.response) card.response.appendChild(doneNote);
          return;
        }
        
        console.log('[continueAgentPlan] Found next tool call:', nextToolCall.name);
        
        // Show executing indicator
        const execNote = document.createElement('div');
        execNote.className = 'mt-3 p-3 bg-gray-800/50 border border-gray-700 rounded-lg';
        execNote.innerHTML = '<div class="flex items-center gap-2 text-gray-300"><svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><span>Executing ' + escapeHtml(nextToolCall.name) + '...</span></div>';
        if (card?.response) card.response.appendChild(execNote);
        
        try {
          const nextResult = await executeToolCall(nextToolCall);
          console.log('[continueAgentPlan] Next tool result:', nextResult);
          
          // Format result
          execNote.className = 'mt-3';
          execNote.innerHTML = formatToolResult(nextToolCall.name, nextResult);
          
          // Update history with the tool result for persistence
          if (history.length > 0 && history[history.length - 1].role === 'assistant') {
            const lastMsg = history[history.length - 1];
            // Store tool results separately for reliable persistence
            if (!lastMsg.toolResults) lastMsg.toolResults = [];
            lastMsg.toolResults.push({ name: nextToolCall.name, result: nextResult });
            lastMsg.content = stripToolCallJson(lastMsg.content || '');
            syncCurrentConvo();
          }
          
          // Recursively continue if successful, passing completedSteps and originalRequest
          if (nextResult?.success !== false) {
            await continueAgentPlan(card, nextToolCall.name, nextToolCall.arguments || {}, nextResult, aiResponse, depth + 1, completedSteps, originalRequest);
          }
        } catch (e) {
          console.error('[continueAgentPlan] Tool execution failed:', e);
          execNote.className = 'mt-3 p-3 bg-red-100 dark:bg-red-900/20 border border-red-300 dark:border-red-500/30 rounded-lg text-red-700 dark:text-red-300';
          execNote.innerHTML = '<div class="flex items-center gap-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>Tool failed: ' + escapeHtml(e.message) + '</span></div>';
        }
      } else {
        // No tool_call found - but maybe the model forgot or got confused
        // Give it ONE retry chance to review remaining tasks (only if we haven't retried yet)
        // BUT: Only do this for DELETE operations where files might be missed
        const hasRetried = completedSteps.some(s => s.tool === '__review_retry__');
        const lastToolWasDelete = completedSteps.length > 0 && 
          completedSteps[completedSteps.length - 1].tool === 'sandbox_delete';
        
        if (!hasRetried && lastToolWasDelete && depth < 8) {
          console.log('[continueAgentPlan] No tool_call found after delete, sending review prompt');
          
          // Mark that we've done a retry to prevent infinite loops
          const retryStep = { tool: '__review_retry__', arguments: {}, success: true, summary: 'Review check' };
          const stepsWithRetry = [...completedSteps, retryStep];
          
          // Send a nudge to review remaining items - ONLY for delete operations
          const reviewPrompt = `ORIGINAL REQUEST: "${originalRequest}"

Files deleted so far: ${completedSteps.filter(s => s.tool === 'sandbox_delete').length}

Is the ORIGINAL REQUEST complete? If not, output the next sandbox_delete. If done, say "Done".`;

          // Recursively call with review prompt - this will trigger another LLM call
          try {
            await continueAgentPlanWithPrompt(card, reviewPrompt, aiResponse, depth + 1, stepsWithRetry, originalRequest);
          } catch (e) {
            console.log('[continueAgentPlan] Review retry failed:', e);
          }
        } else {
          // Already retried or no steps done - truly complete
          console.log('[continueAgentPlan] No more tool calls detected, plan complete');
          if (formattedContent && formattedContent.length > 20) {
            const doneNote = document.createElement('div');
            doneNote.className = 'mt-3 p-3 bg-green-100 dark:bg-green-900/20 border border-green-300 dark:border-green-500/30 rounded-lg text-green-700 dark:text-green-300';
            doneNote.innerHTML = '<div class="flex items-center gap-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg><span>All steps completed!</span></div>';
            if (card?.response) card.response.appendChild(doneNote);
          }
        }
      }
      
      // Sync conversation to persist tool results
      syncCurrentConvo();
      
    } catch (e) {
      console.error('[continueAgentPlan] Continuation failed:', e);
      continueNote.remove();
    }
  }

  // Safe JSON parse helper used by detection/execution code
  function tryParseJsonSafe(s) {
    if (!s) return null;
    if (typeof s !== 'string') return s;
    try { return JSON.parse(s); } catch (e) {
      // try to fix common single-quote or trailing-comma issues
      try {
        const fixed = s.replace(/'(.*?)'/g, '"$1"').replace(/,\s*}/g, '}').replace(/,\s*]/g, ']');
        return JSON.parse(fixed);
      } catch (e2) { return null; }
    }
  }

  function extractTextFromOpenAiContent(content) {
    if (typeof content === 'string') return sanitizeOpenAiTextChunk(content);
    if (!Array.isArray(content)) return '';

    const parts = [];
    for (const block of content) {
      if (typeof block === 'string') {
        if (block) parts.push(block);
        continue;
      }
      if (!block || typeof block !== 'object') continue;

      const text =
        (typeof block.text === 'string' && block.text) ||
        (typeof block.output_text === 'string' && block.output_text) ||
        (typeof block.content === 'string' && block.content) ||
        '';
      if (text) parts.push(text);
    }

    return sanitizeOpenAiTextChunk(parts.join(''));
  }

      // STT client-side recording/processing has been removed.
      // Speech-to-text is handled server-side now (see /audio/stt).
      // All microphone/VAD/WS upload code was intentionally removed to
      // prevent leakage of provider details and to centralize STT on the
      // server. If you need client-side helpers in the future, add them
      // back behind a feature-flag with clear privacy boundaries.
      //
      // Previously recorded client STT code was here; removed per project
      // policy (STT now runs server-side).

      // NOTE: No-op placeholder — nothing to run on the client for STT.
    // removed STT client code (no-op placeholder)

  // --- TTS audio manager ---
  // Provides simple chunked playback: queued fragments are batched and sent
  // to `/audio/tts` periodically. Uses a user gesture (Enable audio) to
  // satisfy autoplay policies.
  (function setupAudioManager() {
    const enableCheckbox = document.getElementById('enable_audio');
    const stopBtn = document.getElementById('stop_audio');

    // Cache DOM elements used frequently and helpers to reduce repeated work
    const ttsStateEl = document.getElementById('tts_state');

    window.__gintoAudio = {
      stopWords: ['stop', 'cancel', 'enough', 'pause'],
      stopRequested: false,
      enabled: !!(enableCheckbox && enableCheckbox.checked),
      queue: [],           // Queue of text chunks ready to play
      pendingText: '',     // Accumulated text waiting for sentence boundary
      flushTimer: null,    // Timer for flushing pending text
      inFlight: false,
      currentAudio: null,
      playing: false,
      prefetchedBlob: null, // Pre-fetched audio blob for next chunk
      prefetchingChunk: null, // Which chunk is being prefetched
      inCodeBlock: false,  // Track if we're inside a code block
      
      // Queue a fragment - splits on sentence boundaries for natural speech
      queueFragment(fragment) {
        this.stopRequested = false;
        if (!this.enabled) return;
        
        let f = ('' + fragment).trim();
        if (!f) return;
        
        // Track code block state - count ``` occurrences
        const codeBlockMarkers = (f.match(/```/g) || []).length;
        if (codeBlockMarkers > 0) {
          // Toggle code block state for each marker
          for (let i = 0; i < codeBlockMarkers; i++) {
            this.inCodeBlock = !this.inCodeBlock;
          }
          // Remove the code block content from this fragment
          f = f.replace(/```[\s\S]*?```/g, ' '); // Remove complete blocks
          f = f.replace(/```[\s\S]*/g, ''); // Remove partial opening block
          f = f.replace(/[\s\S]*```/g, ''); // Remove partial closing block
        }
        
        // Skip if we're inside a code block
        if (this.inCodeBlock) return;
        
        // Skip fragments that look like code (contain typical code patterns)
        if (/^[\s]*[<{}\[\]();=]|function\s|const\s|let\s|var\s|class\s|import\s|export\s|return\s/.test(f)) {
          return;
        }
        
        f = f.trim();
        if (!f) return;
        
        // Accumulate text
        this.pendingText += (this.pendingText ? ' ' : '') + f;
        
        // Clear existing flush timer
        if (this.flushTimer) {
          clearTimeout(this.flushTimer);
          this.flushTimer = null;
        }
        
        // Split on sentence boundaries (. ! ?)
        // Match complete sentences including the punctuation
        const sentenceRegex = /[^.!?]+[.!?]+/g;
        let match;
        let lastIndex = 0;
        
        while ((match = sentenceRegex.exec(this.pendingText)) !== null) {
          const sentence = match[0].trim();
          if (sentence.length >= 10) { // Minimum sentence length
            this.queue.push(sentence);
            lastIndex = sentenceRegex.lastIndex;
          }
        }
        
        // Keep remaining text that doesn't form a complete sentence
        if (lastIndex > 0) {
          this.pendingText = this.pendingText.slice(lastIndex).trim();
        }
        
        // Start playback immediately if we have queued sentences
        if (!this.playing && this.queue.length > 0) {
          this.playNextChunk();
        }
        
        // Flush remaining text after 800ms of no new input (end of stream)
        this.flushTimer = setTimeout(() => {
          const remaining = this.pendingText.trim();
          if (remaining && remaining.length >= 5) {
            this.queue.push(remaining);
            this.pendingText = '';
            // Start playback if not already playing
            if (!this.playing && this.queue.length > 0) {
              this.playNextChunk();
            }
          }
          this.flushTimer = null;
        }, 800);
      },
      
      // Clean text before sending to TTS - skip tables and code
      cleanForTTS(text) {
        if (!text) return '';
        let clean = text;
        // Remove code blocks (fenced with ```)
        clean = clean.replace(/```[\s\S]*?```/g, ' ');
        // Remove inline code
        clean = clean.replace(/`[^`]+`/g, ' ');
        // Remove markdown tables (lines with pipes)
        clean = clean.replace(/^\|.*\|$/gm, '');
        clean = clean.replace(/^[\s]*[-|:]+[\s]*$/gm, ''); // table separator lines
        // Remove emoji
        clean = clean.replace(/[\u{1F300}-\u{1F9FF}]|[\u{2600}-\u{26FF}]|[\u{2700}-\u{27BF}]/gu, '');
        // Remove special chars
        clean = clean.replace(/[•→←↑↓…—–""''✓✗✨🎉🙏]/g, '');
        // Remove markdown remnants
        clean = clean.replace(/[*_`#\[\]]/g, '');
        // Remove citations like [1], [2]
        clean = clean.replace(/\[\d+\]/g, '');
        // Remove URLs
        clean = clean.replace(/https?:\/\/[^\s]+/g, '');
        // Collapse whitespace
        clean = clean.replace(/\s+/g, ' ').trim();
        return clean;
      },
      
      // Fetch TTS audio for text
      async fetchTTS(text) {
        const cleaned = this.cleanForTTS(text);
        if (!cleaned || cleaned.length < 3) return null;
        
        const res = await fetch('/audio/tts', {
          method: 'POST',
          credentials: 'same-origin',
          body: cleaned,
          headers: {
            'Content-Type': 'text/plain',
            'X-CSRF-Token': window.CSRF_TOKEN || document.getElementById('csrf_token')?.value || ''
          }
        });
        
        // Handle rate limit response
        if (res.status === 429) {
          try {
            const data = await res.json();
            // Show the TTS limit modal if available
            if (typeof window.showTtsLimitModal === 'function') {
              window.showTtsLimitModal(data);
            }
            // Disable TTS for this session to avoid repeated failures
            this.enabled = false;
            this.stopRequested = true;
          } catch (e) {
            console.debug('TTS rate limit response parse error:', e);
          }
          return null;
        }
        
        if (!res.ok) throw new Error('TTS fetch failed: ' + res.status);
        const audioData = await res.arrayBuffer();
        return new Blob([audioData], { type: 'audio/mpeg' });
      },
      
      // Prefetch the next chunk while current is playing
      async prefetchNext() {
        if (this.queue.length === 0 || this.prefetchedBlob) return;
        
        const nextChunk = this.queue[0]; // Peek
        if (!nextChunk || this.prefetchingChunk === nextChunk) return;
        
        this.prefetchingChunk = nextChunk;
        
        try {
          const blob = await this.fetchTTS(nextChunk);
          // Only store if it's still the same chunk we wanted
          if (this.queue[0] === nextChunk && blob) {
            this.prefetchedBlob = { chunk: nextChunk, blob };
          }
        } catch (e) {
          // Prefetch failed, will fetch normally
        }
        this.prefetchingChunk = null;
      },
      
      // Play a single audio blob
      playBlob(blob) {
        return new Promise((resolve) => {
          const url = URL.createObjectURL(blob);
          const audio = new Audio(url);
          audio._ginto_url = url;
          this.currentAudio = audio;
          
          const cleanup = () => {
            try { audio.pause(); } catch(_){}
            try { URL.revokeObjectURL(url); } catch(_){}
            this.currentAudio = null;
            resolve();
          };
          
          audio.onended = cleanup;
          audio.onerror = cleanup;
          
          // Poll for stop request
          const pollStop = () => {
            if (this.stopRequested) {
              cleanup();
              return;
            }
            if (this.currentAudio === audio) {
              setTimeout(pollStop, 100);
            }
          };
          
          audio.play().then(pollStop).catch(cleanup);
        });
      },
      
      // Main playback loop
      async playNextChunk() {
        if (this.playing) return;
        this.playing = true;
        this.inFlight = true;
        
        while (this.queue.length > 0 && !this.stopRequested) {
          const chunk = this.queue.shift();
          
          // Check for stop words
          if (this.stopWords.some(w => chunk.toLowerCase().includes(w.toLowerCase()))) {
            console.log('[TTS] Stop word detected');
            this.stopRequested = true;
            break;
          }
          
          try {
            let blob = null;
            
            // Use prefetched blob if available
            if (this.prefetchedBlob && this.prefetchedBlob.chunk === chunk) {
              blob = this.prefetchedBlob.blob;
              this.prefetchedBlob = null;
            } else {
              blob = await this.fetchTTS(chunk);
            }
            
            if (this.stopRequested) break;
            
            if (blob) {
              // Start prefetching next chunk while we play this one
              this.prefetchNext();
              await this.playBlob(blob);
            }
          } catch (e) {
            console.error('[TTS] Playback error:', e);
          }
        }
        
        this.playing = false;
        this.inFlight = false;
        this.prefetchedBlob = null;
        
        // If there's still pending text after playback, flush it
        if (this.pendingText.trim() && this.pendingText.trim().length >= 5) {
          this.queue.push(this.pendingText.trim());
          this.pendingText = '';
          if (this.queue.length > 0 && !this.stopRequested) {
            this.playNextChunk();
          }
        }
      },
      
      // Stop all playback
      stop() {
        this.stopRequested = true;
        this.queue.length = 0;
        this.pendingText = '';
        this.prefetchedBlob = null;
        this.inCodeBlock = false; // Reset code block state
        
        if (this.flushTimer) {
          clearTimeout(this.flushTimer);
          this.flushTimer = null;
        }
        
        if (this.currentAudio) {
          try { this.currentAudio.pause(); } catch(_){}
          try { 
            if (this.currentAudio._ginto_url) {
              URL.revokeObjectURL(this.currentAudio._ginto_url);
            }
          } catch(_){}
          this.currentAudio = null;
        }
        
        this.playing = false;
        this.inFlight = false;
      }
    };

    // Auto-enable TTS on page load
    try {
      window.__gintoAudio.enabled = true;
      if (enableCheckbox) enableCheckbox.checked = true;
      if (stopBtn) stopBtn.disabled = false;
    } catch (e) { console.debug('auto-enable TTS failed', e); }

    // Helper: small wrapper for writing to TTS debug overlay safely
    function logTtsDebug(msg, level) {
      try { window.updateTtsDebug && window.updateTtsDebug(msg, level); } catch (e) { /* ignore */ }
    }

    // Helper: schedule auto-start of STT after TTS finishes. Uses retries
    // to avoid races where fragments are flushed immediately after an ended
    // event. startFn is typically window.__gintoStartRecording.
    function scheduleAutoStart(startFn) {
      if (typeof startFn !== 'function') {
        console.warn('No startRecording function available to auto-start STT');
        logTtsDebug('No startRecording function available', 'warn');
        return;
      }

      const MAX_TRIES = 6;
      const TRY_DELAY_MS = 150;
      let tries = 0;
      function isSpeakingComplete() {
        try {
          const am = window.__gintoAudio;
          const noQueue = !(am && Array.isArray(am.queue) && am.queue.length > 0);
          const notInFlight = !(am && am.inFlight);
          const noCurrentAudio = !(am && am.currentAudio);
          return noQueue && notInFlight && noCurrentAudio;
        } catch (e) { return false; }
      }

      (function tryStart() {
        tries++;
        if (isSpeakingComplete()) {
          try {
            logTtsDebug('Speaking finished — starting recording', 'info');
            const p = startFn();
            if (p && typeof p.then === 'function') {
              p.then(() => { console.debug('auto-startRecording resolved'); logTtsDebug('Auto-startRecording resolved', 'info'); })
               .catch((err) => { console.error('auto-startRecording rejected', err); logTtsDebug('Auto-startRecording rejected: ' + (err?.message||err), 'error'); });
            }
          } catch (e) { console.debug('auto-startRecording failed', e); logTtsDebug('auto-startRecording failed: ' + (e?.message||e), 'error'); }
          return;
        }
        if (tries < MAX_TRIES) setTimeout(tryStart, TRY_DELAY_MS);
        else logTtsDebug('Giving up on auto-start; speaking still appears active', 'warn');
      })();
    }

    // Expose a simple wrapper that other modules (or TTS ended handlers)
    // can call to attempt to start listening after the assistant finishes.
    try { window.startListeningAfterTts = () => scheduleAutoStart(window.__gintoStartRecording); } catch (e) { /* ignore */ }

    // TTS checkbox handler
    enableCheckbox?.addEventListener('change', (e) => { window.__gintoAudio.enabled = !!e.target.checked; });
    if (stopBtn) stopBtn.disabled = false;
    
    // Stop button uses the new stop() method on the audio manager
    stopBtn?.addEventListener('click', () => {
      console.log('[TTS] Stop Audio button pressed');
      if (window.__gintoAudio) {
        window.__gintoAudio.stop();
      }
    });
  })();

  // Send on Enter (simple Enter) or Ctrl/Cmd+Enter. Preserve Shift+Enter for newline.
  // --- Manual STT (push-to-talk) ---
  // Simple start/stop recorder that uploads a single blob to /audio/stt and
  // appends returned transcript to the composer. No model info is sent from
  // the client to avoid revealing provider choices — server decides model.
  (function setupManualStt() {
    const startSttBtn = document.getElementById('start_stt');
    const stopSttBtn = document.getElementById('stop_stt');
    // continuous listen UI removed; element intentionally not used
    const sttTranscriptEl = document.getElementById('stt_transcript');
    const sttDebugEl = document.getElementById('stt_debug');

    console.debug('STT init: elements', { start: !!startSttBtn, stop: !!stopSttBtn, transcript: !!sttTranscriptEl, debug: !!sttDebugEl });
    function sttDebug(msg) {
      console.debug('STT DEBUG:', msg);
      try { if (sttDebugEl) sttDebugEl.textContent = msg; } catch (e) { console.debug('sttDebug write failed', e); }
      try {
        // Mirror STT debug messages into the floating TTS debug overlay for visibility
        if (window.updateTtsDebug) {
          const m = (msg || '').toString();
          const low = m.toLowerCase();
          const level = (low.includes('error') || low.includes('denied') || low.includes('fail') || low.includes('no audio') || low.includes('not available')) ? 'error' : 'info';
          window.updateTtsDebug('[STT] ' + m, level);
        }
      } catch (e) { console.debug('mirror to TTS overlay failed', e); }
    }
    // Provide a clearer message about which elements are missing
    const missing = [];
    if (!startSttBtn) missing.push('start_stt');
    if (!stopSttBtn) missing.push('stop_stt');
    if (!sttTranscriptEl) missing.push('stt_transcript');
    if (!sttDebugEl) missing.push('stt_debug');
    if (missing.length) {
      sttDebug('STT elements missing: ' + missing.join(', '));
      console.warn('STT elements missing, aborting STT setup:', missing);
      return;
    }
    // Ensure UI is enabled (some browsers or CSS/state might have left it disabled)
    try { startSttBtn.disabled = false; stopSttBtn.disabled = true; } catch (e) {}

    // Show capability diagnostics so user can see why STT may not start
    try {
      const hasGetUserMedia = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
      const hasMediaRecorder = !!window.MediaRecorder;
      const supportedTypes = [];
      if (MediaRecorder && typeof MediaRecorder.isTypeSupported === 'function') {
        const candidates = ['audio/webm;codecs=opus','audio/webm','audio/ogg;codecs=opus','audio/ogg'];
        for (const c of candidates) if (MediaRecorder.isTypeSupported(c)) supportedTypes.push(c);
      }
      sttDebug('Capabilities: getUserMedia=' + (hasGetUserMedia? 'yes':'no') + ', MediaRecorder=' + (hasMediaRecorder? 'yes':'no') + ', supportedTypes=' + (supportedTypes.join(',')||'none'));
    } catch (e) { console.debug('stt capabilities check failed', e); }

    // Keyboard shortcut to start/stop STT for debugging: Ctrl+Shift+M
    try {
      document.addEventListener('keydown', (ev) => {
        if (ev.ctrlKey && ev.shiftKey && ev.key.toLowerCase() === 'm') {
          try {
            if (!startSttBtn.disabled) startSttBtn.click();
            else if (!stopSttBtn.disabled) stopSttBtn.click();
          } catch (_) {}
        }
      });
    } catch (e) {}

    let mediaRecorder = null;
    let recordedChunks = [];
    let _stt_record_timer = null;
    let _stt_record_start = 0;
    let _stt_request_timer = null;
    let lastAutoSent = '';
    const MIN_AUTO_SEND_CHARS = 3;
    // Autostop / VAD related
    let _stt_audio_ctx = null;
    let _stt_analyser = null;
    let _stt_silence_checkId = null;
    let _stt_max_record_timer = null;
    let stt_stream = null;
    // Silence detection settings (defaults). These can be overridden at runtime
    // by calling `window.setSttConfig({...})`.
    const _defaultSttConfig = {
      silenceThreshold: 0.010, // RMS threshold for silence
      silenceMs: 1200,         // how long of silence to wait before autostop (ms) — 3 seconds
      minSpeechMs: 300,        // don't autostop immediately at start (ms)
      maxRecordMs: 30000       // force-stop after 30s (ms)
    };
    // Runtime config (start from page-global if provided)
    let _sttConfig = Object.assign({}, _defaultSttConfig, window.__gintoSttConfig || {});
    // Expose setter/getter so app or tests can adjust behaviour at runtime
    try {
      window.setSttConfig = (cfg) => {
        try {
          Object.assign(_sttConfig, cfg || {});
          sttDebug('STT config updated: ' + JSON.stringify(_sttConfig));
          return _sttConfig;
        } catch (e) { console.debug('setSttConfig failed', e); }
      };
      window.getSttConfig = () => Object.assign({}, _sttConfig);
    } catch (e) {}
    // continuous mode removed; keep flag false for compatibility
    let continuousListen = false;

    // Shared stop routine used by manual stop and autostop
    async function stopRecording(trigger) {
      try { sttDebug('(stop handler, trigger=' + (trigger||'manual') + ')'); } catch (e) {}
      if (!mediaRecorder) return;
      try {
        sttDebug('Stopping recorder...');
        stopSttBtn.disabled = true;
        // ensure buffered data is delivered
        try { mediaRecorder.requestData?.(); } catch (e) { console.debug('requestData failed', e); }
        mediaRecorder.stop();
        // give the recorder a short moment to flush
        await new Promise(r => setTimeout(r, 150));

        // Stop silence detection and max timers
        try { if (_stt_silence_checkId) { clearInterval(_stt_silence_checkId); _stt_silence_checkId = null; } } catch(e){}
        try { if (_stt_max_record_timer) { clearTimeout(_stt_max_record_timer); _stt_max_record_timer = null; } } catch(e){}
        try { if (_stt_audio_ctx) { try { _stt_audio_ctx.close(); } catch(_){} _stt_audio_ctx = null; _stt_analyser = null; } } catch(e){}

        // construct blob using detected mimeType when present
        const usedType = (mediaRecorder && mediaRecorder.mimeType) || (recordedChunks[0]?.type) || 'audio/webm';
        const blob = new Blob(recordedChunks, { type: usedType });
          if (!blob || !blob.size) {
          sttTranscriptEl.textContent = '(no audio recorded)';
          sttDebug('No audio recorded (empty blob)');
          startSttBtn.disabled = false;
          // cleanup stream tracks
          try { if (stt_stream) { stt_stream.getTracks().forEach(t=>t.stop()); stt_stream = null; } } catch(e){}
          return;
        }

        sttTranscriptEl.textContent = '(sending...)';
        sttDebug('Uploading ' + blob.size + ' bytes');
        console.debug('STT: uploading', { size: blob.size, type: usedType });

        const form = new FormData();
        form.append('file', blob, 'stt.webm');
        // Intentionally do NOT append a model field — server chooses model.
        form.append('csrf_token', window.CSRF_TOKEN || document.getElementById('csrf_token')?.value || '');

        // Use isolated /transcribe route for testing to avoid extra proxy logic
        const res = await fetch('/transcribe', { method: 'POST', credentials: 'same-origin', body: form });
        // Read full body as text so we can inspect non-JSON responses during debugging
        const bodyText = await res.text().catch(()=>'');
        console.debug('STT: upload response status=', res.status, 'content-type=', res.headers.get('Content-Type'));
        console.debug('STT: upload response body (truncated):', bodyText.slice(0,2000));
        sttDebug('Upload finished, status ' + res.status + ', Content-Type=' + (res.headers.get('Content-Type') || '(none)'));
        if (!res.ok) {
          sttTranscriptEl.textContent = `[STT error ${res.status}] ${bodyText}`;
          sttDebug('STT server error: ' + (bodyText ? (bodyText.length>2000? bodyText.slice(0,2000)+'…': bodyText) : '(empty)'));
          startSttBtn.disabled = false;
          return;
        }

        // Try to parse JSON from the body; if parsing fails, show raw body for diagnosis
        let parsed = null;
        try { parsed = JSON.parse(bodyText); } catch (e) { parsed = null; }

        // Helper: attempt to extract any reasonable text from an arbitrary JSON shape
        function extractTextFromJson(obj) {
          if (!obj) return '';
          if (typeof obj === 'string') return obj;
          if (typeof obj === 'object') {
            // direct fields commonly used
            if (obj.text && typeof obj.text === 'string') return obj.text;
            if (obj.transcript && typeof obj.transcript === 'string') return obj.transcript;
            if (obj.result && typeof obj.result === 'string') return obj.result;
            // scan nested arrays/objects recursively but stop early
            for (const k of Object.keys(obj)) {
              try {
                const v = obj[k];
                if (typeof v === 'string' && v.length > 0) return v;
                if (Array.isArray(v)) {
                  for (const el of v) {
                    const t = extractTextFromJson(el);
                    if (t) return t;
                  }
                } else if (typeof v === 'object') {
                  const t = extractTextFromJson(v);
                  if (t) return t;
                }
              } catch (e) {}
            }
          }
          return '';
        }

        let transcript = '';
        if (parsed) {
          transcript = extractTextFromJson(parsed) || '';
        } else {
          // non-JSON response: treat body as plain text
          transcript = (bodyText || '').trim();
        }

        if (!transcript) {
          sttTranscriptEl.textContent = '(no transcript returned)';
          sttDebug('No transcript in response — server body: ' + (bodyText ? (bodyText.length>2000? bodyText.slice(0,2000)+'…':bodyText) : '(empty)'));
          startSttBtn.disabled = false;
          return;
        }

        // Show transcript and append to composer
        sttDebug('Received transcript: ' + (transcript || '').slice(0,120));
        sttTranscriptEl.textContent = transcript;
        try { appendToComposer(transcript); } catch (e) { console.error('appendToComposer failed', e); }

        // Auto-send if we have a decent transcription and user hasn't already typed
        try {
          function normalizeText(t) { return (t || '').replace(/\s+/g, ' ').trim(); }
          let text = normalizeText(promptEl.value || '');
          if (!text) text = normalizeText(transcript || '');
          if (text && text.length >= MIN_AUTO_SEND_CHARS) {
            if (text !== normalizeText(lastAutoSent || '')) {
              lastAutoSent = text;
              promptEl.value = '';
              try { autoResizeComposer(); } catch(e){}
              streamWebSearch(text);
            }
          }
        } catch (e) { console.error('auto-send STT error', e); }
      } catch (e) {
        console.error('STT stop error', e);
        sttTranscriptEl.textContent = '(STT failed)';
      } finally {
        startSttBtn.disabled = false;
        stopSttBtn.disabled = true;
        // clear recording timers and reset (including interim requestData caller)
        try { if (_stt_record_timer) clearInterval(_stt_record_timer); _stt_record_timer = null; _stt_record_start = 0; } catch(e){}
        try { if (_stt_request_timer) clearInterval(_stt_request_timer); _stt_request_timer = null; } catch(e){}
        mediaRecorder = null;
        recordedChunks = [];
        // cleanup stream tracks
        try { if (stt_stream) { stt_stream.getTracks().forEach(t=>t.stop()); stt_stream = null; } } catch(e){}
        // continuous listen removed: do not auto-restart recording
      }
    }

    // startRecording: optional preStream allows reusing an existing mic stream (used by wake monitor)
    async function startRecording(preStream) {
      // Prevent starting if already recording
      try { if (mediaRecorder) { sttDebug('Already recording, startRecording ignored'); console.debug('startRecording called but mediaRecorder already active - ignoring'); return; } } catch(e){}
      console.debug('startRecording called, preStream=', !!preStream);
      sttDebug('(requesting microphone access...)');
      try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
          sttTranscriptEl.textContent = '(microphone not available)';
          sttDebug('getUserMedia not supported by this browser');
          return;
        }
        const stream = preStream || await navigator.mediaDevices.getUserMedia({ audio: true });
        sttDebug('(microphone access granted)');
        // remember stream for cleanup
        stt_stream = stream;
        recordedChunks = [];
        // pick a sensible mimeType that the browser supports
        let mimeType = '';
        const candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/ogg'];
        for (const c of candidates) {
          if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(c)) { mimeType = c; break; }
        }
        const opts = mimeType ? { mimeType } : undefined;
        if (!window.MediaRecorder) {
          sttDebug('(MediaRecorder not available in this browser)');
          console.warn('MediaRecorder not available; STT recording will not work');
        }
        console.debug('STT: creating MediaRecorder with opts', opts);
        mediaRecorder = new MediaRecorder(stream, opts);
        // Setup lightweight VAD/silence detection using WebAudio
        try {
          const AudioCtx = window.AudioContext || window.webkitAudioContext;
          if (AudioCtx) {
            _stt_audio_ctx = new AudioCtx();
            const src = _stt_audio_ctx.createMediaStreamSource(stream);
            _stt_analyser = _stt_audio_ctx.createAnalyser();
            _stt_analyser.fftSize = 2048;
            src.connect(_stt_analyser);
            let lastSpoke = Date.now();
            const data = new Float32Array(_stt_analyser.fftSize);
            _stt_silence_checkId = setInterval(() => {
              try {
                _stt_analyser.getFloatTimeDomainData(data);
                let sum = 0;
                for (let i = 0; i < data.length; i++) sum += data[i] * data[i];
                const rms = Math.sqrt(sum / data.length);
                if (rms >= (_sttConfig.silenceThreshold || _defaultSttConfig.silenceThreshold)) {
                  lastSpoke = Date.now();
                } else {
                  // if user has been silent for long enough after speaking, autostop
                  const silenceMs = (_sttConfig.silenceMs || _defaultSttConfig.silenceMs);
                  const minSpeechMs = (_sttConfig.minSpeechMs || _defaultSttConfig.minSpeechMs);
                  if (Date.now() - lastSpoke > silenceMs && Date.now() - _stt_record_start > minSpeechMs) {
                    try { sttDebug('Silence detected (rms=' + rms.toFixed(5) + '), auto-stopping'); } catch(e){}
                    // fire autostop; don't await here to avoid blocking audio thread
                    try { stopRecording('autosilence'); } catch(e){}
                  }
                }
              } catch (e) {}
            }, 200);
            // Max duration guard
            _stt_max_record_timer = setTimeout(() => { try { sttDebug('Max record duration reached, auto-stopping'); stopRecording('max'); } catch(e){} }, (_sttConfig.maxRecordMs || _defaultSttConfig.maxRecordMs));
          }
        } catch (e) { console.debug('VAD setup failed', e); }
        sttDebug('Recording started (mime=' + (mediaRecorder.mimeType || opts?.mimeType || 'unknown') + ')');
        // Start live recording status updates (elapsed time + chunk count)
        try {
          _stt_record_start = Date.now();
          _stt_record_timer = setInterval(() => {
            try {
              const secs = Math.floor((Date.now() - _stt_record_start) / 1000);
              sttDebug('(listening... ' + secs + 's, chunks=' + recordedChunks.length + ')');
            } catch (e) {}
          }, 700);
          // Request interim data regularly so `dataavailable` fires during recording
          try {
            _stt_request_timer = setInterval(() => {
              try { mediaRecorder.requestData?.(); } catch (e) {}
            }, 700);
          } catch (e) {}
        } catch (e) {}
        mediaRecorder.addEventListener('dataavailable', e => { if (e.data && e.data.size) { recordedChunks.push(e.data); try { sttDebug('(dataavailable) chunks=' + recordedChunks.length + ', bytes=' + recordedChunks.reduce((s,c)=>s+(c.size||0),0)); } catch(e){} } });
        mediaRecorder.addEventListener('stop', () => {
          try {
            // stop source tracks (continuous mode removed)
            try { stream.getTracks().forEach(t=>t.stop()); } catch (_) {}
          } catch (e) {}
        });
        mediaRecorder.addEventListener('error', (ev) => {
          console.error('MediaRecorder error', ev);
          sttTranscriptEl.textContent = '(recording error)';
        });
        mediaRecorder.start();
        console.debug('MediaRecorder.start() invoked, mimeType=', mediaRecorder.mimeType || opts?.mimeType || 'unknown');
        try { sttDebug('Recording started (mediaRecorder running)'); } catch(e){}
        startSttBtn.disabled = true;
        stopSttBtn.disabled = false;
        sttTranscriptEl.textContent = '(listening...)';
      } catch (e) {
        sttTranscriptEl.textContent = '(permission denied or error)';
        sttDebug('start error: ' + (e?.message || e));
        console.error('start STT error', e);
      }
    }
    // expose startRecording globally so other modules (TTS) can wake STT
    try { window.__gintoStartRecording = startRecording; } catch (e) { console.debug('expose startRecording failed', e); }

    startSttBtn.addEventListener('click', async () => { await startRecording(); });

    stopSttBtn.addEventListener('click', async () => { await stopRecording('manual'); });

    // continuous listen UI removed; no handler

    // --- Wake-word training & monitoring ---
    const trainWakeBtn = document.getElementById('train_wake');
    const enableWakeCheckbox = document.getElementById('enable_wake');
    const wakeStatusEl = document.getElementById('wake_status');

    // Wake templates and runtime state
    let wakeTemplate = null; // Float32Array of normalized rms frames
    let wakeTemplateLen = 0;
    const WAKE_LS_KEY = 'ginto_wake_template_v1';
    let wakeMonitorStream = null;
    let wakeMonitorCtx = null;
    let wakeMonitorAnalyser = null;
    let wakeMonitorInterval = null;
    let wakeFramesRing = [];
    let wakeCooldown = 0;
    const WAKE_SIM_THRESHOLD = 0.65; // similarity threshold (0-1)
    const WAKE_COOLDOWN_MS = 2000;
    const TRAIN_MS = 1400;

    function updateWakeStatus(msg) {
      try { if (wakeStatusEl) wakeStatusEl.textContent = msg; } catch (e) {}
      try { sttDebug('[wake] ' + msg); } catch (e) {}
    }

    // Persist/load template to localStorage
    function saveWakeTemplate() {
      try {
        if (!wakeTemplate || !wakeTemplate.length) { localStorage.removeItem(WAKE_LS_KEY); return; }
        // store as array of numbers
        const arr = Array.from(wakeTemplate);
        const payload = { v: 1, frames: arr, len: wakeTemplateLen, t: Date.now() };
        localStorage.setItem(WAKE_LS_KEY, JSON.stringify(payload));
        updateWakeStatus('Wake template saved');
      } catch (e) { console.debug('saveWakeTemplate failed', e); }
    }

    function loadWakeTemplate() {
      try {
        const raw = localStorage.getItem(WAKE_LS_KEY);
        if (!raw) return false;
        const parsed = JSON.parse(raw);
        if (!parsed || !Array.isArray(parsed.frames) || parsed.frames.length === 0) return false;
        wakeTemplate = normalizeVector(new Float32Array(parsed.frames));
        wakeTemplateLen = wakeTemplate.length;
        updateWakeStatus('Wake template loaded (frames=' + wakeTemplateLen + ')');
        return true;
      } catch (e) { console.debug('loadWakeTemplate failed', e); return false; }
    }

    // Compute RMS frames for an AudioBuffer
    function computeRmsFramesFromAudioBuffer(buf, frameSize = 2048) {
      try {
        const ch = buf.numberOfChannels > 0 ? buf.getChannelData(0) : null;
        if (!ch) return new Float32Array();
        const frames = Math.floor(ch.length / frameSize);
        const out = new Float32Array(frames);
        for (let i = 0; i < frames; i++) {
          let sum = 0;
          const off = i * frameSize;
          for (let j = 0; j < frameSize; j++) {
            const v = ch[off + j] || 0;
            sum += v * v;
          }
          out[i] = Math.sqrt(sum / frameSize);
        }
        return out;
      } catch (e) { console.debug('computeRmsFramesFromAudioBuffer failed', e); return new Float32Array(); }
    }

    function normalizeVector(v) {
      try {
        let sum = 0;
        for (let i = 0; i < v.length; i++) sum += v[i] * v[i];
        const norm = Math.sqrt(sum) || 1e-9;
        const out = new Float32Array(v.length);
        for (let i = 0; i < v.length; i++) out[i] = v[i] / norm;
        return out;
      } catch (e) { return v; }
    }

    function cosineSimilarity(a, b) {
      if (!a || !b || a.length !== b.length) return 0;
      let s = 0, na = 0, nb = 0;
      for (let i = 0; i < a.length; i++) { s += a[i] * b[i]; na += a[i]*a[i]; nb += b[i]*b[i]; }
      const denom = Math.sqrt(na) * Math.sqrt(nb) || 1e-9;
      return s / denom;
    }

    async function trainWakeWord() {
      updateWakeStatus('Training... (please say the wake phrase)');
      try {
        const s = await navigator.mediaDevices.getUserMedia({ audio: true });
        const mr = new MediaRecorder(s);
        const chunks = [];
        mr.ondataavailable = (e) => { if (e.data && e.data.size) chunks.push(e.data); };
        mr.onstop = async () => {
          try { s.getTracks().forEach(t=>t.stop()); } catch(e){}
          const blob = new Blob(chunks, { type: 'audio/webm' });
          const ab = await blob.arrayBuffer();
          const AudioCtx = window.AudioContext || window.webkitAudioContext;
          if (!AudioCtx) { updateWakeStatus('AudioContext unavailable'); return; }
          const ctx = new AudioCtx();
          try {
            const decoded = await ctx.decodeAudioData(ab.slice(0));
            const frames = computeRmsFramesFromAudioBuffer(decoded, 2048);
              if (!frames || frames.length < 4) { updateWakeStatus('Training failed (too short)'); ctx.close(); return; }
              wakeTemplate = normalizeVector(frames);
              wakeTemplateLen = wakeTemplate.length;
              updateWakeStatus('Wake word trained (frames=' + wakeTemplateLen + ')');
              try { ctx.close(); } catch(e){}
              // persist template so it survives page reloads
              try { saveWakeTemplate(); } catch (e) { console.debug('save after train failed', e); }
          } catch (e) { updateWakeStatus('Training parse failed'); try { ctx.close(); } catch(e){} }
        };
        mr.start();
        setTimeout(() => { try { mr.stop(); } catch(e){} }, TRAIN_MS);
      } catch (e) {
        updateWakeStatus('Training error: ' + (e?.message||e));
      }
    }

    async function startWakeMonitor() {
      if (!wakeTemplate || wakeTemplateLen <= 0) { updateWakeStatus('Train wake word first'); return; }
      if (wakeMonitorInterval) return; // already running
      try {
        wakeMonitorStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) { updateWakeStatus('AudioContext unavailable'); return; }
        wakeMonitorCtx = new AudioCtx();
        const src = wakeMonitorCtx.createMediaStreamSource(wakeMonitorStream);
        wakeMonitorAnalyser = wakeMonitorCtx.createAnalyser();
        wakeMonitorAnalyser.fftSize = 2048;
        src.connect(wakeMonitorAnalyser);
        const data = new Float32Array(wakeMonitorAnalyser.fftSize);
        wakeFramesRing = [];
        updateWakeStatus('Wake monitor running');
        wakeMonitorInterval = setInterval(() => {
          try {
            wakeMonitorAnalyser.getFloatTimeDomainData(data);
            let sum = 0;
            for (let i = 0; i < data.length; i++) sum += data[i] * data[i];
            const rms = Math.sqrt(sum / data.length);
            // push frame
            wakeFramesRing.push(rms);
            if (wakeFramesRing.length > wakeTemplateLen) wakeFramesRing.shift();
            if (wakeFramesRing.length === wakeTemplateLen) {
              // normalize current vector
              const cur = normalizeVector(new Float32Array(wakeFramesRing));
              const sim = cosineSimilarity(cur, wakeTemplate);
              if (sim >= WAKE_SIM_THRESHOLD && Date.now() - wakeCooldown > WAKE_COOLDOWN_MS) {
                wakeCooldown = Date.now();
                updateWakeStatus('Wake word detected (sim=' + sim.toFixed(2) + ')');
                // start recording (reuse monitor stream)
                try { startRecording(wakeMonitorStream); } catch (e) { console.error('startRecording from wake failed', e); }
                // short cooldown to avoid double triggers
                setTimeout(() => { updateWakeStatus('Waiting...'); }, 800);
              }
            }
          } catch (e) { console.debug('wake monitor tick error', e); }
        }, 150);
      } catch (e) { updateWakeStatus('Wake monitor error: ' + (e?.message||e)); }
    }

    function stopWakeMonitor() {
      try { if (wakeMonitorInterval) clearInterval(wakeMonitorInterval); wakeMonitorInterval = null; } catch(e){}
      try { if (wakeMonitorCtx) { wakeMonitorCtx.close(); wakeMonitorCtx = null; wakeMonitorAnalyser = null; } } catch(e){}
      try { if (wakeMonitorStream) { wakeMonitorStream.getTracks().forEach(t=>t.stop()); wakeMonitorStream = null; } } catch(e){}
      wakeFramesRing = [];
      updateWakeStatus('Wake monitor stopped');
    }

    // UI hooks (if present)
    if (trainWakeBtn) trainWakeBtn.addEventListener('click', () => trainWakeWord());
    if (enableWakeCheckbox) {
      enableWakeCheckbox.addEventListener('change', (ev) => {
        if (ev.target.checked) startWakeMonitor(); else stopWakeMonitor();
      });
      // start monitoring if checkbox is pre-checked
      try { if (enableWakeCheckbox.checked) startWakeMonitor(); } catch(e){}
    } else {
      // If no checkbox but wake_status exists, inform the user how to train/enable
      if (wakeStatusEl) updateWakeStatus('No wake controls found; add #train_wake and #enable_wake to enable.');
    }
    // Attempt to load persisted wake template on init
    try { if (loadWakeTemplate()) { /* loaded */ } } catch (e) { console.debug('loadWakeTemplate failed', e); }

    // File-upload STT UI removed: server-side STT is triggered by recorder stop.
  })();

  promptEl.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      // If user pressed Shift+Enter, let the browser insert a newline
      if (e.shiftKey) return;
      // Otherwise prevent default (avoid newline) and send. Support Ctrl/Cmd+Enter too.
      e.preventDefault();
      const p = promptEl.value.trim();
      if (!p) return;
      promptEl.value = '';
      try { autoResizeComposer(); } catch(e){}
      streamWebSearch(p);
    }
  });

  clearBtn.addEventListener('click', () => {
    // clear composer
    promptEl.value = '';
    try { autoResizeComposer(); } catch(e){}
  });

  resetHistoryBtn?.addEventListener('click', () => {
    // Clear current conversation and start fresh
    history.length = 0;
    if (conversationStore.activeId && conversationStore.conversations[conversationStore.activeId]) {
      conversationStore.conversations[conversationStore.activeId].messages = [];
      conversationStore.conversations[conversationStore.activeId].title = 'New conversation';
      conversationStore.conversations[conversationStore.activeId].updatedAt = Date.now();
      saveConversationsToStorage();
      renderConvoList();
    }
    renderMessages();
  });
  
  // Initialize sidebar and render restored history on page load
  renderConvoList();
  if (history.length > 0) {
    console.log('[chat] Restored', history.length, 'messages from localStorage');
    renderMessages();
    // Initialize sticky buttons for restored messages
    setTimeout(() => initStickyCodeButtons(), 200);
  }
  
  // Wire up "New Chat" button in sidebar header
  const newChatBtn = document.getElementById('new_chat');
  if (newChatBtn) {
    newChatBtn.addEventListener('click', () => {
      newConvo();
    });
  }
  
  // ==========================================================================
  // ADDON UPGRADE MODAL - For ImageGen Pro and other premium addons
  // ==========================================================================
  
  window.showAddonUpgradeModal = async function(addonType) {
    // Fetch addon details from server
    try {
      const response = await fetch(`/api/addon/info/${addonType}`);
      const addon = await response.json();
      
      if (!addon.success) {
        alert(addon.error || 'Failed to load addon information');
        return;
      }

      // If the current user already has this addon, show an informational modal instead of the PayPal flow
      if (addon.user_has_addon) {
        const pending = !!addon.user_addon_next_billing || (addon.user_addon_status === 'active' && addon.user_addon_next_billing === null);
        const message = pending ? 'processing imagegen request for your purchase. We will update the user interface once ImageGen has finished updating your account, and you\'ll receive an email when it\'s ready.' : 'You already have ImageGen Pro active.';
        const modalHtmlAlready = `
        <div id="addon-upgrade-modal" class="fixed inset-0 z-[99999] flex items-end sm:items-center justify-center bg-black/60 backdrop-blur-sm p-0 sm:p-4">
          <div class="bg-white dark:bg-gray-800 rounded-t-2xl sm:rounded-2xl shadow-2xl w-full sm:max-w-sm max-h-[85vh] overflow-y-auto animate-fade-in">
            <div class="bg-gradient-to-r from-purple-600 to-pink-600 px-4 py-3 sticky top-0">
              <div class="flex items-center gap-3">
                <div class="w-6 h-6 rounded bg-white/20 flex items-center justify-center flex-shrink-0">
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14" />
                  </svg>
                </div>
                <div class="text-left">
                  <h2 class="text-lg font-bold text-white leading-tight">${escapeHtml(addon.name)}</h2>
                  <p class="text-white/80 text-xs sm:text-sm leading-tight">Professional AI Image Generation with GPU acceleration</p>
                </div>
              </div>
            </div>
            <div class="px-4 py-6 text-center">
              <p class="text-sm text-gray-700 dark:text-gray-300">${escapeHtml(message)}</p>
            </div>
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 flex justify-center sticky bottom-0">
              <button onclick="document.getElementById('addon-upgrade-modal')?.remove()" 
                      class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                Close
              </button>
            </div>
          </div>
        </div>
        `;

        // Remove existing modal if any
        document.getElementById('addon-upgrade-modal')?.remove();
        // Insert informational modal
        document.body.insertAdjacentHTML('beforeend', modalHtmlAlready);
        return;
      }

      // Create modal HTML - mobile-first, compact design
      const modalHtml = `
        <div id="addon-upgrade-modal" class="fixed inset-0 z-[99999] flex items-end sm:items-center justify-center bg-black/60 backdrop-blur-sm p-0 sm:p-4">
          <div class="bg-white dark:bg-gray-800 rounded-t-2xl sm:rounded-2xl shadow-2xl w-full sm:max-w-sm max-h-[85vh] overflow-y-auto animate-fade-in">
            <!-- Header - compact -->
            <div class="bg-gradient-to-r from-purple-600 to-pink-600 px-4 py-3 sticky top-0">
              <div class="flex items-center gap-3">
                <div class="w-6 h-6 rounded bg-white/20 flex items-center justify-center flex-shrink-0">
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14" />
                  </svg>
                </div>
                <div class="text-left">
                  <h2 class="text-lg font-bold text-white leading-tight">${escapeHtml(addon.name)}</h2>
                  <p class="text-white/80 text-xs sm:text-sm leading-tight">Professional AI Image Generation with GPU acceleration</p>
                </div>
              </div>
            </div>
            
            <!-- Content - compact -->
            <div class="px-4 py-4">
              <div class="text-center mb-4">
                <span class="text-3xl font-bold text-gray-800 dark:text-white">$${addon.amount_usd}</span>
                <span class="text-gray-500 dark:text-gray-400 text-sm">/month</span>
              </div>
              
              <ul class="space-y-2 mb-4 text-sm">
                ${(addon.features || []).map(f => `
                  <li class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                    <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    ${escapeHtml(f)}
                  </li>
                `).join('')}
              </ul>
              
              <!-- PayPal Button Container -->
              <div id="addon-paypal-container" class="mb-3">
                <!-- Loading indicator - hidden automatically when PayPal buttons are available -->
                <div id="addon-paypal-loading" class="text-center text-gray-500 dark:text-gray-400 py-3">
                  <svg class="w-5 h-5 mx-auto animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <p class="mt-1 text-sm">PayPal is loading...</p>
                  <p class="text-xs mt-1">Please wait or refresh the page.</p>
                </div>
                <!-- Actual PayPal button mount point (PayPal SDK will render into this div) -->
                <div id="addon-paypal-buttons"></div>
              </div>
              
              <p class="text-xs text-center text-gray-500 dark:text-gray-400">
                Cancel anytime from your account settings. Subscription billed monthly.
              </p>
            </div>
            
            <!-- Footer - compact -->
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 flex justify-center sticky bottom-0">
              <button onclick="document.getElementById('addon-upgrade-modal')?.remove()" 
                      class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                Maybe Later
              </button>
            </div>
          </div>
        </div>
      `;
      
      // Remove existing modal if any
      document.getElementById('addon-upgrade-modal')?.remove();
      
      // Insert modal
      document.body.insertAdjacentHTML('beforeend', modalHtml);
      
      // If PayPal SDK is already loaded, hide the loading indicator immediately so users don't see "PayPal is loading..."
      try {
        const loadingEl = document.getElementById('addon-paypal-loading');
        if (loadingEl && typeof paypal !== 'undefined' && paypal && paypal.Buttons) {
          loadingEl.style.display = 'none';
        }
      } catch (e) {
        // ignore
      }

      // Initialize PayPal button - with retry logic
      const initPayPalButton = () => {
        if (addon.paypal_plan_id && typeof paypal !== 'undefined' && paypal.Buttons) {
          paypal.Buttons({
            style: {
              shape: 'rect',
              color: 'gold',
              layout: 'vertical',
              label: 'subscribe'
            },
            createSubscription: function(data, actions) {
              return actions.subscription.create({
                plan_id: addon.paypal_plan_id,
                application_context: {
                  brand_name: 'Ginto',
                  user_action: 'SUBSCRIBE_NOW',
                  shipping_preference: 'NO_SHIPPING'
                }
              });
            },
            onApprove: async function(data, actions) {
              // Subscription approved - activate in database
              const loadingEl = document.getElementById('addon-paypal-loading'); if (loadingEl) loadingEl.style.display = 'none';
              document.getElementById('addon-paypal-buttons').innerHTML = `
                <div class="text-center py-3">
                  <svg class="w-10 h-10 mx-auto text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  <p class="mt-2 text-green-600 dark:text-green-400 font-semibold text-sm">Activating your subscription...</p>
                </div>
              `;
              
              try {
                const csrfToken = window.CSRF_TOKEN || document.getElementById('csrf_token')?.value || '';
                const activateResponse = await fetch('/api/addon/activate', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                  },
                  body: JSON.stringify({
                    addon_type: addonType,
                    subscription_id: data.subscriptionID,
                    csrf_token: csrfToken
                  })
                });
                
                const result = await activateResponse.json();
                
                if (result.success) {
                  const loadingEl = document.getElementById('addon-paypal-loading'); if (loadingEl) loadingEl.style.display = 'none';
                  document.getElementById('addon-paypal-buttons').innerHTML = `
                    <div class="text-center py-3">
                      <svg class="w-12 h-12 mx-auto text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                      <h3 class="mt-2 text-lg font-bold text-green-600 dark:text-green-400">Subscription Activated!</h3>
                      <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Your ${escapeHtml(addon.name)} is now active.</p>
                      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">We're setting up your GPU resources. We will update the user interface once ImageGen has finished updating your account, and you'll receive an email or be notified within the platformwhen it's ready.</p>
                    </div>
                  `;
                } else {
                  const loadingEl = document.getElementById('addon-paypal-loading'); if (loadingEl) loadingEl.style.display = 'none';
                  document.getElementById('addon-paypal-buttons').innerHTML = `
                    <div class="text-center py-3 text-red-600 dark:text-red-400 text-sm">
                      <p>Activation failed: ${escapeHtml(result.error || 'Unknown error')}</p>
                      <p class="text-xs mt-2">Subscription ID: ${escapeHtml(data.subscriptionID)}</p>
                      <p class="text-xs">Please contact support with this ID.</p>
                    </div>
                  `;
                }
              } catch (err) {
                console.error('Addon activation error:', err);
                const loadingEl = document.getElementById('addon-paypal-loading'); if (loadingEl) loadingEl.style.display = 'none';
                document.getElementById('addon-paypal-buttons').innerHTML = `
                  <div class="text-center py-3 text-red-600 dark:text-red-400 text-sm">
                    <p>Network error during activation.</p>
                    <p class="text-xs mt-2">Subscription ID: ${escapeHtml(data.subscriptionID)}</p>
                    <p class="text-xs">Please contact support with this ID.</p>
                  </div>
                `;
              }
            },
            onError: function(err) {
              console.error('PayPal error:', err);
              const loadingEl = document.getElementById('addon-paypal-loading'); if (loadingEl) loadingEl.style.display = 'none';
              document.getElementById('addon-paypal-buttons').innerHTML = `
                <div class="text-center py-3 text-red-600 dark:text-red-400 text-sm">
                  <p>Payment failed. Please try again.</p>
                </div>
              `;
            },
            onCancel: function() {
              console.log('Subscription cancelled by user');
            }
          }).render('#addon-paypal-buttons').then(() => {
            // Hide loading indicator when PayPal UI is visible
            const loadingEl = document.getElementById('addon-paypal-loading');
            if (loadingEl) loadingEl.style.display = 'none';
            console.log('PayPal button rendered successfully');
          }).catch(err => {
            console.error('PayPal render error:', err);
            const loadingEl = document.getElementById('addon-paypal-loading');
            if (loadingEl) loadingEl.style.display = 'none';
            document.getElementById('addon-paypal-buttons').innerHTML = `
              <div class="text-center py-3 text-yellow-600 dark:text-yellow-400 text-sm">
                <p>Failed to load PayPal. Please refresh the page.</p>
              </div>
            `;
          });
        } else if (!addon.paypal_plan_id) {
          const loadingEl = document.getElementById('addon-paypal-loading'); if (loadingEl) loadingEl.style.display = 'none';
          document.getElementById('addon-paypal-buttons').innerHTML = `
            <div class="text-center py-3 text-yellow-600 dark:text-yellow-400 text-sm">
              <p>Payment is not configured for this addon.</p>
              <p class="text-xs mt-1">Please contact support.</p>
            </div>
          `;
        } else {
          // PayPal not loaded yet - retry
          return false;
        }
        return true;
      };

      // Try to initialize PayPal button, retry if SDK not ready
      if (!initPayPalButton()) {
        let retries = 0;
        const maxRetries = 10;
        const retryInterval = setInterval(() => {
          retries++;
          if (initPayPalButton() || retries >= maxRetries) {
            clearInterval(retryInterval);
            if (retries >= maxRetries && typeof paypal === 'undefined') {
              const loadingEl = document.getElementById('addon-paypal-loading'); if (loadingEl) loadingEl.style.display = 'none';
              document.getElementById('addon-paypal-buttons').innerHTML = `
                </div>
              `;
            }
          }
        }, 500);
      }
      
    } catch (err) {
      console.error('showAddonUpgradeModal error:', err);
      alert('Failed to load upgrade options. Please try again.');
    }
  };
})();

// Fix chat-content overflow issues (images, tables, code blocks) on /chat
(function fixChatOverflow(){
  try {
    if (!window || !window.location) return;
    if (!window.location.pathname || !window.location.pathname.startsWith('/chat')) return;

    function injectStyles() {
      try {
        // Avoid duplicate insertion
        if (document.getElementById('ginto-chat-overflow-fix')) return;
        const css = `
          /* Ensure assistant response content never forces horizontal scroll */
          .card-response, .card-response .prose, .convo-card-body, .messenger-pair { max-width: 100% !important; box-sizing: border-box !important; }
          .card-response { overflow-wrap: break-word !important; word-break: break-word !important; }
          .card-response img, .card-response video, .card-response iframe { max-width: 100% !important; height: auto !important; display: block; }
          .card-response pre, .card-response code { white-space: pre-wrap !important; word-break: break-word !important; max-width: 100% !important; }
          .card-response table { width: 100% !important; max-width: 100% !important; display: block; overflow-x: auto !important; }
          /* Allow message bubbles to wrap without expanding layout */
          .messenger-bubble, .messenger-row, .messenger-pair { min-width: 0 !important; }
        `;
        const s = document.createElement('style');
        s.id = 'ginto-chat-overflow-fix';
        s.appendChild(document.createTextNode(css));
        (document.head || document.documentElement).appendChild(s);
      } catch (e) { console.debug('injectStyles failed', e); }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', injectStyles); else injectStyles();
    window.addEventListener('resize', injectStyles);
    window.addEventListener('orientationchange', injectStyles);
  } catch (e) { console.debug('fixChatOverflow init failed', e); }
})();
