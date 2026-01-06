/**
 * Chat Markdown Module
 * Markdown rendering with marked.js, highlight.js, and KaTeX
 */

// ============ MARKDOWN INITIALIZATION ============

let markdownInitialized = false;

/**
 * Configure marked.js with highlight.js for code blocks
 */
export function initMarkdownRenderer() {
  if (typeof marked === 'undefined') {
    console.warn('[MarkdownRenderer] marked.js not loaded');
    return false;
  }
  
  if (markdownInitialized) return true;
  
  marked.setOptions({
    breaks: true,
    gfm: true,
    highlight: function(code, lang) {
      if (typeof hljs !== 'undefined') {
        const safeLang = (lang || '').replace(/[^a-zA-Z0-9+#-]/g, '');
        
        if (safeLang && hljs.getLanguage(safeLang)) {
          try {
            const highlighted = hljs.highlight(code, { language: safeLang }).value;
            return highlighted;
          } catch (e) {}
        }
        
        try {
          return hljs.highlightAuto(code).value;
        } catch (e) {}
      }
      return code.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
  });
  
  markdownInitialized = true;
  return true;
}

// ============ PHP CODE BLOCK FIXER ============

/**
 * Fix PHP code blocks formatting
 */
export function fixPhpCodeBlocks(markdown) {
  if (!markdown) return '';
  
  markdown = markdown.replace(/```(php|PHP)\s*(<\?php)/gi, '```php\n$2');
  markdown = markdown.replace(/<\?php(?=\/\*)/g, '<?php\n');
  markdown = markdown.replace(/<\?php(?=\/\/)/g, '<?php\n');
  markdown = markdown.replace(/<\?php(?=[^\s\/\n])/g, '<?php\n');
  
  markdown = markdown.replace(/```php\s*([\s\S]*?)```/gi, function(match, code) {
    let trimmedCode = code.trim();
    
    if (trimmedCode.startsWith('<?php')) {
      trimmedCode = trimmedCode.replace(/^<\?php(?=[^\s\n])/, '<?php\n');
      trimmedCode = trimmedCode.replace(/([^\n\s])\s*\?>(\s*)$/, '$1\n?>');
      
      if (!trimmedCode.endsWith('?>')) {
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

// ============ LATEX DELIMITER FIXER ============

/**
 * Fix LaTeX delimiters for KaTeX compatibility
 */
export function fixLatexDelimiters(markdown) {
  if (!markdown) return '';
  
  const linebreakPlaceholders = [];
  const spacingPattern = /^\[\d*(?:pt|em|ex|mm|cm|in|sp|pc|bp|dd|cc|mu)?\]$/i;
  
  markdown = markdown.replace(/\\\\(\[[^\]]*?\])/g, function(match, bracket) {
    if (spacingPattern.test(bracket)) {
      const placeholder = '\u0000LB' + linebreakPlaceholders.length + '\u0000';
      linebreakPlaceholders.push('\\\\' + bracket);
      return placeholder;
    }
    return match;
  });
  
  markdown = markdown.replace(/([^\\])\\(\[[^\]]*?\])/g, function(match, prefix, bracket) {
    if (spacingPattern.test(bracket)) {
      const placeholder = '\u0000LB' + linebreakPlaceholders.length + '\u0000';
      linebreakPlaceholders.push('\\\\' + bracket);
      return prefix + placeholder;
    }
    return match;
  });
  
  markdown = markdown.replace(/^\\(\[[^\]]*?\])/g, function(match, bracket) {
    if (spacingPattern.test(bracket)) {
      const placeholder = '\u0000LB' + linebreakPlaceholders.length + '\u0000';
      linebreakPlaceholders.push('\\\\' + bracket);
      return placeholder;
    }
    return match;
  });
  
  const LEFTBRACKET = '\u0000LEFTBRACKET\u0000';
  const RIGHTBRACKET = '\u0000RIGHTBRACKET\u0000';
  markdown = markdown.replace(/\\left\[/g, LEFTBRACKET);
  markdown = markdown.replace(/\\right\]/g, RIGHTBRACKET);
  
  const LEFTPAREN = '\u0000LEFTPAREN\u0000';
  const RIGHTPAREN = '\u0000RIGHTPAREN\u0000';
  markdown = markdown.replace(/\\left\(/g, LEFTPAREN);
  markdown = markdown.replace(/\\right\)/g, RIGHTPAREN);
  
  markdown = markdown.replace(/\\\[\s*([\s\S]*?)\s*\\\]/g, function(match, content) {
    const cleaned = content.trim().replace(/\n+/g, ' ');
    return '\n\n$$' + cleaned + '$$\n\n';
  });
  
  markdown = markdown.replace(/\\\[\s*([^\n\|]+?)(?=\n|\||$)/g, function(match, content) {
    if (content && /\\[a-zA-Z]+/.test(content) && !content.includes('|')) {
      const cleaned = content.trim();
      return '$$' + cleaned + '$$';
    }
    return match;
  });
  
  markdown = markdown.replace(/\\\(\s*([\s\S]*?)\s*\\\)/g, function(match, content) {
    const cleaned = content.trim().replace(/\n+/g, ' ');
    return '$' + cleaned + '$';
  });
  
  for (let i = 0; i < linebreakPlaceholders.length; i++) {
    markdown = markdown.replace('\u0000LB' + i + '\u0000', linebreakPlaceholders[i]);
  }
  markdown = markdown.replace(new RegExp(LEFTBRACKET, 'g'), '\\left[');
  markdown = markdown.replace(new RegExp(RIGHTBRACKET, 'g'), '\\right]');
  markdown = markdown.replace(new RegExp(LEFTPAREN, 'g'), '\\left(');
  markdown = markdown.replace(new RegExp(RIGHTPAREN, 'g'), '\\right)');
  
  markdown = markdown.replace(/\$\$\s*\n+\s*([^\$]+?)\s*\n+\s*\$\$/g, function(match, content) {
    const cleaned = content.trim().replace(/\n+/g, ' ');
    return '$$' + cleaned + '$$';
  });
  
  return markdown;
}

// ============ MAIN MARKDOWN RENDERER ============

/**
 * Convert markdown to HTML with LaTeX support
 */
export function simpleMarkdownToHtml(markdown) {
  if (!markdown) return '';
  
  // Preprocess: fix PHP code blocks
  markdown = fixPhpCodeBlocks(markdown);
  
  // Preprocess: fix headings without space after #
  markdown = markdown.replace(/^(#{1,6})([^\s#])/gm, '$1 $2');
  
  // Preprocess: fix inline list items
  markdown = markdown.replace(/([^\n])\s+-\s+\*\*/g, '$1\n\n- **');
  markdown = markdown.replace(/([^\n])\s+-\s+([A-Z])/g, '$1\n\n- $2');
  markdown = markdown.replace(/\.(\s*)-\s+/g, '.\n\n- ');
  
  // Preprocess: ensure double newlines before headers
  markdown = markdown.replace(/([^\n])\n(#{1,6}\s)/g, '$1\n\n$2');
  
  // Preprocess: fix markdown tables
  markdown = markdown.replace(/(\|[^|\n]*\|)(\s*)(\|)/g, function(match, row1, space, pipe2) {
    if (!space.includes('\n')) {
      return row1 + '\n' + pipe2;
    }
    return match;
  });
  
  // Preprocess: protect and normalize LaTeX delimiters
  markdown = fixLatexDelimiters(markdown);
  
  // Protect math blocks from marked.js processing
  const mathBlocks = [];
  
  markdown = markdown.replace(/(\n*)\$\$([\s\S]*?)\$\$(\n*)/g, function(match, before, content, after) {
    const placeholder = '\u0002MATH' + mathBlocks.length + 'MATH\u0003';
    mathBlocks.push({ type: 'display', content: content });
    return '\n\n' + placeholder + '\n\n';
  });
  
  markdown = markdown.replace(/\$([^\$\n]+?)\$/g, function(match, content) {
    if (/^\d/.test(content.trim()) || /^[\d,.\s]+(?:billion|million|trillion|k|m|b)?$/i.test(content.trim())) {
      return match;
    }
    if (/[\\^_{}+\-*/=<>]|\\[a-z]+/i.test(content)) {
      const placeholder = '\u0002MATH' + mathBlocks.length + 'MATH\u0003';
      mathBlocks.push({ type: 'inline', content: content });
      return placeholder;
    }
    return match;
  });
  
  initMarkdownRenderer();
  
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
  
  // Restore math blocks
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

// ============ FALLBACK MARKDOWN RENDERER ============

/**
 * Basic regex-based markdown renderer (fallback)
 */
export function fallbackMarkdownToHtml(md) {
  if (!md) return '';
  
  let content = md.trim();
  const lines = content.split('\n');
  const result = [];
  let inList = false;
  
  function processInline(text) {
    text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    text = text.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    text = text.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    text = text.replace(/_([^_]+)_/g, '<em>$1</em>');
    text = text.replace(/`([^`]+)`/g, '<code>$1</code>');
    return text;
  }
  
  function parseTable(tableLines) {
    if (tableLines.length < 2) return null;
    
    const rows = tableLines.map(line => {
      const cells = line.split('|').map(c => c.trim()).filter((c, i, arr) => i > 0 && i < arr.length);
      return cells;
    });
    
    const isSeparator = rows[1] && rows[1].every(cell => /^[-:]+$/.test(cell));
    if (!isSeparator) return null;
    
    let html = '<table class="markdown-table">';
    html += '<thead><tr>';
    rows[0].forEach(cell => {
      html += `<th>${processInline(cell.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'))}</th>`;
    });
    html += '</tr></thead>';
    
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
        const tableHtml = parseTable(tableLines);
        if (tableHtml) {
          result.push(tableHtml);
        } else {
          tableLines.forEach(tl => {
            result.push(`<p>${processInline(tl.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'))}</p>`);
          });
        }
        inTable = false;
        tableLines = [];
      }
      
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

// ============ KATEX RENDERING ============

/**
 * Render KaTeX math in an element AFTER innerHTML is set
 */
export function renderLatexInElement(element) {
  if (!element) return;
  if (typeof renderMathInElement === 'undefined' || typeof katex === 'undefined') {
    console.warn('[MarkdownRenderer] KaTeX not available');
    return;
  }
  
  try {
    renderMathInElement(element, {
      delimiters: [
        { left: '$$', right: '$$', display: true },
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

// ============ CONTENT STRIP HELPERS ============

/**
 * Strip tool_call JSON from content for display
 */
export function stripToolCallJson(text) {
  if (!text) return text;
  let cleaned = text;
  
  cleaned = cleaned.replace(/\{"query"\s*:[^}]+\}/g, '');
  cleaned = cleaned.replace(/\{"response"\s*:\s*\[[\s\S]*?\]\s*,\s*"status"\s*:\s*"[^"]*"\s*\}/g, '');
  cleaned = cleaned.replace(/\{"response"\s*:\s*\[[\s\S]*?\]\s*\}/g, '');
  cleaned = cleaned.replace(/\{"tool_call"\s*:\s*\{[\s\S]*?\}\s*\}/g, '');
  cleaned = cleaned.replace(/```json\s*\{"tool_call"[\s\S]*?\}\s*```/g, '');
  cleaned = cleaned.replace(/\{["\s]*tool_call["\s]*:[\s\S]*?\}\s*\}/g, '');
  cleaned = cleaned.replace(/Search web\.\{[^}]+\}\.{0,3}/gi, '');
  cleaned = cleaned.replace(/Browse web\.\{[^}]+\}\.{0,3}/gi, '');
  cleaned = cleaned.replace(/Let's search[^.]*\./gi, '');
  cleaned = cleaned.replace(/We need to browse[^.]*\./gi, '');
  cleaned = cleaned.replace(/!\[[^\]]*\]\([^)]+\)/g, '');
  cleaned = cleaned.replace(/\n{3,}/g, '\n\n');
  cleaned = cleaned.replace(/\.{2,}/g, '.');
  cleaned = cleaned.replace(/[^\S\n]{2,}/g, ' ');
  cleaned = cleaned.replace(/\s+\./g, '.');
  
  return cleaned.trim();
}
