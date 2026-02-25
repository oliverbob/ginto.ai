/**
 * Chat Tools Module
 * Tool execution, formatToolResult, executeToolCall
 */

import { escapeHtml, tryParseJsonSafe } from './core.js';

// ============ TOOL CALL EXTRACTION ============

/**
 * Extract tool_call from text (handles various JSON formats)
 */
export function extractToolCallFromText(s) {
  if (!s || typeof s !== 'string') return null;
  
  const trimmed = s.trim();
  try {
    if (trimmed.startsWith('{') && trimmed.endsWith('}')) {
      const j = JSON.parse(trimmed);
      if (j.tool_call) return j.tool_call;
      if (j.tool_calls && Array.isArray(j.tool_calls) && j.tool_calls.length) return j.tool_calls[0];
      if (j.function_call) return { name: j.function_call.name, arguments: tryParseJsonSafe(j.function_call.arguments) };
      if (j.tool && (j.tool.name || j.tool.arguments)) return { name: j.tool.name || j.tool, arguments: j.tool.arguments || {} };
    }
  } catch (e) { /* continue to more flexible parsing */ }

  // Search for common markers
  const markers = ['"tool_call"', '"tool_calls"', '"function_call"', '"function-call"', '"tool"'];
  let found = false;
  for (const m of markers) if (s.indexOf(m) !== -1) { found = true; break; }
  if (!found) return null;

  // Find JSON object around marker
  const firstMarkerIdx = markers.map(m => s.indexOf(m)).filter(i => i >= 0).sort((a,b) => a-b)[0];
  if (firstMarkerIdx === undefined) return null;
  
  let start = s.lastIndexOf('{', firstMarkerIdx);
  if (start === -1) start = s.indexOf('{');
  if (start === -1) return null;
  
  let depth = 0; 
  let end = -1;
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

  // Fallback pattern
  try {
    const re = /\{[^}]*"name"\s*:\s*"([^\"]+)"[^}]*"arguments"\s*:\s*(\{[\s\S]*\})/i;
    const m = re.exec(s);
    if (m) return { name: m[1], arguments: JSON.parse(m[2]) };
  } catch (e) {}
  
  return null;
}

// ============ TOOL RESULT FORMATTING ============

/**
 * Format tool results in a human-friendly way
 */
export function formatToolResult(toolName, result) {
  const data = result?.result || result;
  
  // lightpanda_search - web search results
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
    
    let sources = data?.sources || [];
    if (sources.length === 0 && content) {
      const sourceRegex = /### Source \d+: (.+?)\nURL: (https?:\/\/[^\n]+)/g;
      let match;
      while ((match = sourceRegex.exec(content)) !== null) {
        sources.push({ title: match[1], url: match[2] });
      }
    }
    
    const sourcesCount = data?.sourcesCount || sources.length;
    
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
  
  // Error handling
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
  
  // sandbox_list_files
  if (toolName === 'sandbox_list_files' && data?.tree) {
    const tree = data.tree;
    const files = [];
    const folders = [];
    
    for (const [name, info] of Object.entries(tree)) {
      if (info.type === 'folder') folders.push(name);
      else files.push(name);
    }
    
    let html = '<div class="space-y-2">';
    html += '<p class="text-gray-700 dark:text-gray-300">Here are your files:</p>';
    html += '<div class="bg-gray-100 dark:bg-gray-800/50 rounded-lg p-3 font-mono text-sm">';
    
    for (const folder of folders.sort()) {
      html += `<div class="flex items-center gap-2 text-blue-600 dark:text-blue-400 py-0.5">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
        </svg>
        <span>${escapeHtml(folder)}/</span>
      </div>`;
    }
    
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
  
  // sandbox_read_file
  if (toolName === 'sandbox_read_file' && data?.content !== undefined) {
    const path = data.path || 'file';
    const content = data.content || '';
    
    return `<div class="space-y-2">
      <p class="text-gray-700 dark:text-gray-300">Contents of <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">${escapeHtml(path)}</code>:</p>
      <pre class="bg-gray-100 dark:bg-gray-800/50 rounded-lg p-3 text-sm overflow-x-auto max-h-96 text-gray-800 dark:text-gray-200"><code>${escapeHtml(content)}</code></pre>
    </div>`;
  }
  
  // sandbox_write_file
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

  // sandbox_create_document - show document creation with PDF queue handling
  if (toolName === 'sandbox_create_document' && data?.success) {
    const filename = data.filename || 'document';
    const format = data.format || 'Document';
    const formatKey = data.format_key || 'txt';
    const bytes = data.bytes_written || 0;
    const url = data.url || '/clients/' + (data.path || '').replace(/^\//, '');
    const urlHtml = data.url_html || '';

    const formatStyles = {
      pdf: { icon: '📕', bg: 'bg-red-100', text: 'text-red-700', btn: 'bg-red-200' },
      docx: { icon: '📘', bg: 'bg-blue-100', text: 'text-blue-700', btn: 'bg-blue-200' },
      html: { icon: '🌐', bg: 'bg-cyan-100', text: 'text-cyan-700', btn: 'bg-cyan-200' },
      txt: { icon: '📃', bg: 'bg-gray-100', text: 'text-gray-700', btn: 'bg-gray-200' }
    };
    const style = formatStyles[formatKey] || formatStyles.txt;

    // If a job was enqueued, render a placeholder and poll job status
    if (data.job_id) {
      const jobId = data.job_id;
      const pdfUrl = url;
      const htmlUrl = urlHtml;

      return `<div class="p-3 ${style.bg} rounded-lg border ${style.text}">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
          <div style="display:flex;align-items:center;gap:8px;">
            <span style="font-size:20px;">${style.icon}</span>
            <div>
              <div style="font-weight:600;">${escapeHtml(filename)}</div>
              <div style="font-size:12px;opacity:0.8">${escapeHtml(format)} • Conversion queued</div>
            </div>
          </div>
        </div>
        <div class="mt-3 sandbox-doc-result" data-job-id="${escapeHtml(jobId)}" data-pdf-url="${escapeHtml(pdfUrl)}" data-html-url="${escapeHtml(htmlUrl)}" data-filename="${escapeHtml(filename)}">
          <div style="display:flex;gap:8px;align-items:center;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"></path></svg>
            <span>Conversion queued — preparing PDF. <a href="${escapeHtml(htmlUrl)}" target="_blank">Open HTML</a></span>
          </div>
        </div>
      </div>`;
    }

    // No job_id -> PDF available immediately
    return `<div class="p-3 ${style.bg} rounded-lg border ${style.text}">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
        <div style="display:flex;align-items:center;gap:8px;">
          <span style="font-size:20px;">${style.icon}</span>
          <div>
            <div style="font-weight:600;">${escapeHtml(filename)}</div>
            <div style="font-size:12px;opacity:0.8">${escapeHtml(format)}${bytes ? ' • ' + bytes + ' bytes' : ''}</div>
          </div>
        </div>
        <a href="${escapeHtml(url)}" target="_blank" class="${style.btn} rounded px-3 py-1" download="${escapeHtml(filename)}">Download</a>
      </div>
    </div>`;
  }
  
  // sandbox_delete
  if (toolName === 'sandbox_delete' && data?.success) {
    const path = data.path || 'file';
    return `<div class="flex items-center gap-2 p-3 bg-amber-100 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-500/30 rounded-lg text-amber-700 dark:text-amber-300">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
      </svg>
      <span>Deleted <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">${escapeHtml(path)}</code></span>
    </div>`;
  }
  
  // sandbox_exec
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

// ------------------ Sandbox job poller ------------------
// Watches for elements with class 'sandbox-doc-result' and polls job status
function startSandboxJobPoller() {
  const processed = new WeakSet();

  async function pollOnce(el) {
    const jobId = el.dataset.jobId;
    const pdfUrl = el.dataset.pdfUrl;
    const htmlUrl = el.dataset.htmlUrl;
    const filename = el.dataset.filename || 'document';
    if (!jobId) return;

    try {
      const res = await fetch('/api/sandbox/job-status?job_id=' + encodeURIComponent(jobId), { credentials: 'same-origin' });
      if (!res.ok) return null;
      const json = await res.json();
      if (!json.success) return json;
      const status = json.status || json.state || '';
      if (status === 'completed' || status === 'done' || status === 'success') {
        // Replace placeholder with download link
        el.innerHTML = `<a href="${escapeHtml(pdfUrl)}" target="_blank" download="${escapeHtml(filename)}" style="font-weight:600;color:inherit;">Download PDF</a>`;
        return { done: true };
      }
      if (status === 'failed' || json.error) {
        el.innerHTML = `Conversion failed. <a href="${escapeHtml(htmlUrl)}" target="_blank">Open HTML</a>`;
        return { done: true };
      }
      return { done: false, status };
    } catch (e) {
      return null;
    }
  }

  function observeRoot(root) {
    const runner = async (targetEl) => {
      if (!targetEl || !targetEl.dataset) return;
      if (processed.has(targetEl)) return;
      processed.add(targetEl);

      let attempts = 0;
      const maxAttempts = 120; // ~4 minutes at 2s interval

      const iv = setInterval(async () => {
        attempts++;
        const r = await pollOnce(targetEl);
        if (r && r.done) {
          clearInterval(iv);
        } else if (attempts >= maxAttempts) {
          clearInterval(iv);
          // show HTML fallback
          const htmlUrl = targetEl.dataset.htmlUrl;
          targetEl.innerHTML = `Conversion taking too long. <a href="${escapeHtml(htmlUrl)}" target="_blank">Open HTML</a>`;
        }
      }, 2000);
    };

    const mo = new MutationObserver((records) => {
      for (const r of records) {
        for (const node of r.addedNodes) {
          if (!node.querySelectorAll) continue;
          const els = node.matches && node.matches('.sandbox-doc-result') ? [node] : Array.from(node.querySelectorAll('.sandbox-doc-result'));
          for (const el of els) runner(el);
        }
      }
    });

    mo.observe(root || document.body, { childList: true, subtree: true });

    // Also prime existing elements
    Array.from((root || document.body).querySelectorAll('.sandbox-doc-result')).forEach(el => runner(el));
  }

  // Start observing document body
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => observeRoot(document.body));
  } else {
    observeRoot(document.body);
  }
}

// Initialize poller when module is loaded
try { startSandboxJobPoller(); } catch (e) { console.warn('Sandbox job poller init failed', e); }
  
  // generate_image
  if (toolName === 'generate_image') {
    const directOpenAiImages = Array.isArray(data?.data)
      ? data.data
          .map((entry) => {
            if (!entry || typeof entry !== 'object') return null;
            if (typeof entry.b64_json === 'string' && entry.b64_json) {
              return {
                dataUrl: `data:image/png;base64,${entry.b64_json}`,
                url: `data:image/png;base64,${entry.b64_json}`,
              };
            }
            if (typeof entry.url === 'string' && entry.url) {
              return { dataUrl: entry.url, url: entry.url };
            }
            return null;
          })
          .filter(Boolean)
      : [];

    const images = (Array.isArray(data?.images) && data.images.length > 0)
      ? data.images
      : directOpenAiImages;

    if (images.length > 0) {
      const prompt = data.prompt || 'AI Generated Image';
      const model = data.model || 'Imagen';
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
  
  // Default success
  if (data?.success) {
    const msg = data.message || 'Done!';
    return `<div class="flex items-center gap-2 p-3 bg-green-100 dark:bg-green-900/20 border border-green-300 dark:border-green-500/30 rounded-lg text-green-700 dark:text-green-300">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
      </svg>
      <span>${escapeHtml(msg)}</span>
    </div>`;
  }
  
  // Fallback: raw JSON
  return `<details class="mt-2">
    <summary class="cursor-pointer text-gray-400 text-sm">View raw result</summary>
    <pre class="mt-1 bg-gray-800/50 rounded-lg p-2 text-xs overflow-x-auto">${escapeHtml(JSON.stringify(data, null, 2))}</pre>
  </details>`;
}

// ============ TOOL EXECUTION ============

/**
 * Execute a tool_call via /mcp/call or /sandbox/call
 */
export async function executeToolCall(toolCall) {
  if (!toolCall) throw new Error('invalid toolCall');
  
  // Normalize tool name and arguments
  let name = toolCall.name || toolCall.function?.name || toolCall.function_name || toolCall.tool || null;
  let args = toolCall.arguments || toolCall.args || toolCall.function?.arguments || {};
  
  if (typeof args === 'string') args = tryParseJsonSafe(args) || {};
  if (!name) throw new Error('toolCall missing name');

  const body = { tool: name, args: args };
  
  // Route to appropriate endpoint
  const endpoint = (name.startsWith('sandbox_') || name === 'ginto_install') ? '/sandbox/call' : '/mcp/call';
  
  const res = await fetch(endpoint, { 
    method: 'POST', 
    credentials: 'same-origin', 
    headers: { 
      'Content-Type': 'application/json', 
      'X-CSRF-Token': window.CSRF_TOKEN || document.getElementById('csrf_token')?.value || '' 
    }, 
    body: JSON.stringify(body) 
  });
  
  if (!res.ok) {
    const txt = await res.text().catch(() => '(no body)');
    let errorData = null;
    try { errorData = JSON.parse(txt); } catch(e) {}
    
    // Handle special actions
    if (errorData?.action === 'upgrade') {
      if (typeof window.showUpgradeModal === 'function') {
        window.showUpgradeModal(errorData.error || 'This feature requires a Premium subscription.');
      }
      throw new Error(errorData.error || 'Premium subscription required');
    }
    if (errorData?.action === 'login') {
      if (typeof window.showAgenticModal === 'function') {
        window.showAgenticModal(errorData.error || 'Create a free account to unlock file management and agentic features.');
      }
      throw new Error(errorData.error || 'Login required');
    }
    
    throw new Error('HTTP ' + res.status + ': ' + txt);
  }
  
  return await res.json().catch(() => null);
}

// ============ AUTO-RUN TOGGLE ============

/**
 * Check if auto-run tools is enabled
 */
export function isAutoRunEnabled() {
  return localStorage.getItem('ginto_auto_run_tools') === '1';
}

/**
 * Set auto-run tools preference
 */
export function setAutoRunEnabled(enabled) {
  localStorage.setItem('ginto_auto_run_tools', enabled ? '1' : '0');
}
