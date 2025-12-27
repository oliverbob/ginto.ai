<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Web Search</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #0f172a; color: #e2e8f0; font-family: system-ui, sans-serif; }
        .activity-spinner { animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        
        /* Modern scrollbar */
        .modern-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .modern-scroll::-webkit-scrollbar-track { background: transparent; }
        .modern-scroll::-webkit-scrollbar-thumb { 
            background: #4b5563; 
            border-radius: 3px;
        }
        .modern-scroll::-webkit-scrollbar-thumb:hover { background: #6b7280; }
        .modern-scroll { scrollbar-width: thin; scrollbar-color: #4b5563 transparent; }
        
        .site-badge { 
            display: inline-flex; 
            align-items: center; 
            gap: 0.25rem;
            padding: 0.25rem 0.5rem; 
            background: #1e293b; 
            border-radius: 0.375rem; 
            font-size: 0.75rem;
            color: #94a3b8;
        }
        
        /* Reasoning timeline - Groq style */
        .reasoning-timeline {
            position: relative;
            padding-left: 0.5rem;
        }
        .reasoning-header {
            font-size: 0.875rem;
            font-weight: 500;
            color: #9ca3af;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .reasoning-header:hover { color: #d1d5db; }
        .reasoning-chevron {
            transition: transform 0.2s;
            width: 1rem;
            height: 1rem;
        }
        .reasoning-chevron.open { transform: rotate(180deg); }
        .reasoning-content {
            font-size: 0.8125rem;
            line-height: 1.6;
            color: #9ca3af;
            padding-right: 0.5rem;
            max-height: 300px;
            overflow-y: auto;
            overflow-x: hidden;
            position: relative;
        }
        /* Each reasoning step row */
        .reasoning-item {
            display: flex;
            align-items: stretch;
            gap: 1rem;
            padding-left: 0.375rem;
        }
        /* Left column with dot and line */
        .reasoning-item-indicator {
            display: flex;
            flex-direction: column;
            width: 0.75rem;
            flex-shrink: 0;
            align-items: center;
            position: relative;
        }
        .reasoning-item-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #6b7280;
            margin-top: 0.25rem;
            flex-shrink: 0;
        }
        /* Green dot for the last/latest reasoning step */
        .reasoning-item-dot-green {
            background: #4ade80 !important;
        }
        .reasoning-item-line {
            position: absolute;
            top: 10px;
            width: 1px;
            background: #4b5563;
            height: 100%;
        }
        .reasoning-item:last-child .reasoning-item-line {
            display: none;
        }
        /* Text content */
        .reasoning-item-text {
            padding-bottom: 1rem;
            flex: 1;
        }
        .reasoning-item-text p {
            margin: 0;
            padding-top: 0;
        }
        
        /* Response area */
        .response-container { position: relative; }
        .response-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #9ca3af;
            margin-bottom: 0.5rem;
        }
        
        /* Sticky copy button */
        .sticky-copy {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            z-index: 10;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .response-container:hover .sticky-copy { opacity: 1; }
        .sticky-copy.copied { opacity: 1; }
        
        /* Action buttons */
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 0.5rem;
            color: #6b7280;
            transition: all 0.2s ease;
            background: transparent;
            border: none;
            cursor: pointer;
        }
        .action-btn:hover { 
            background: rgba(99, 102, 241, 0.1); 
            color: #a5b4fc;
            transform: translateY(-1px);
        }
        .action-btn:active { transform: translateY(0); }
        .action-btn.active { color: #60a5fa; background: rgba(96, 165, 250, 0.15); }
        .action-btn.liked { color: #34d399; background: rgba(52, 211, 153, 0.15); }
        .action-btn.disliked { color: #f87171; background: rgba(248, 113, 113, 0.15); }
        .action-btn svg { width: 1.125rem; height: 1.125rem; }
        
        /* Citations */
        .citation {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.125rem 0.5rem;
            background: #1e293b;
            border: 1px solid #374151;
            border-radius: 1rem;
            font-size: 0.75rem;
            color: #94a3b8;
            text-decoration: none;
            transition: all 0.15s;
        }
        .citation:hover { background: #374151; color: #e5e7eb; border-color: #4b5563; }
        .citation-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.25rem;
            height: 1.25rem;
            background: #4b5563;
            border-radius: 50%;
            font-size: 0.625rem;
            font-weight: 600;
            color: #e5e7eb;
        }
        
        .prose { line-height: 1.7; }
        .prose h1, .prose h2, .prose h3 { color: #e2e8f0; margin-top: 1em; }
        .prose p { margin: 0.5em 0; }
        .prose table { width: 100%; border-collapse: collapse; margin: 1em 0; }
        .prose th, .prose td { border: 1px solid #374151; padding: 0.5em; text-align: left; }
        .prose th { background: #1e293b; }
        .prose strong { color: #f8fafc; }
        .prose ul, .prose ol { margin: 0.5em 0; padding-left: 1.5em; }
        .prose li { margin: 0.25em 0; }
        
        /* Code blocks - base styling for unwrapped pre elements */
        .prose pre {
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 0.5rem;
            padding: 1rem;
            overflow-x: auto;
            margin: 1em 0;
        }
        .prose pre code {
            background: transparent;
            padding: 0;
            font-size: 0.8125rem;
            line-height: 1.6;
            color: #e6edf3;
            font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
        }
        .prose code {
            background: #1e293b;
            padding: 0.125rem 0.375rem;
            border-radius: 0.25rem;
            font-size: 0.8125rem;
            color: #f472b6;
            font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
        }
        .code-block-wrapper {
            position: relative;
            margin: 1em 0;
            border-radius: 0.5rem;
            border: 1px solid #30363d;
            background: #0d1117;
        }
        .code-block-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #161b22;
            border-bottom: 1px solid #30363d;
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
            color: #8b949e;
            min-height: 2.25rem;
            border-radius: 0.5rem 0.5rem 0 0;
            position: sticky;
            top: 3.5rem;
            z-index: 40;
        }
        .code-header-buttons {
            display: flex;
            align-items: center;
            min-height: 1.75rem;
        }
        .code-block-wrapper pre {
            margin: 0;
            border: none;
            border-radius: 0;
            background: transparent;
            padding: 0;
        }
        .code-block-wrapper code {
            display: block;
            background: transparent;
        }
        /* Line numbers container */
        .code-content {
            display: block;
            overflow-x: auto;
            font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
            font-size: 0.875rem;
            line-height: 1.5;
        }
        .code-table {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }
        .code-row {
            display: table-row;
        }
        .code-row:hover {
            background: rgba(110, 118, 129, 0.1);
        }
        .code-line-num {
            display: table-cell;
            padding: 0 0.75rem;
            text-align: right;
            color: #484f58;
            user-select: none;
            vertical-align: top;
            white-space: nowrap;
            border-right: 1px solid #21262d;
            background: #0d1117;
            position: sticky;
            left: 0;
        }
        .code-line-text {
            display: table-cell;
            padding: 0 1rem;
            white-space: pre;
            color: #e6edf3;
        }
        /* Action buttons */
        .code-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            margin-right: 0.5rem;
            background: #21262d;
            border: 1px solid #30363d;
            border-radius: 0.25rem;
            color: #8b949e;
            font-size: 0.75rem;
            cursor: pointer;
        }
        .code-action-btn:last-child { margin-right: 0; }
        .code-action-btn:hover { background: #30363d; color: #e6edf3; }
        .code-action-btn svg { width: 0.875rem; height: 0.875rem; }
        .code-action-btn.save-btn:hover { color: #3fb950; }
        .code-copy-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            margin-right: 0.5rem;
            background: #21262d;
            border: 1px solid #30363d;
            border-radius: 0.25rem;
            color: #8b949e;
            font-size: 0.75rem;
            cursor: pointer;
        }
        .code-copy-btn:last-child { margin-right: 0; }
        .code-copy-btn:hover { background: #30363d; color: #e6edf3; }
        .code-copy-btn svg { width: 0.875rem; height: 0.875rem; }
        
        /* Active state for Code/Preview toggle buttons */
        .code-action-btn.active { background: #30363d; color: #e6edf3; }
        
        /* Preview Iframe */
        .code-preview-iframe {
            width: 100%;
            min-height: 300px;
            border: none;
            background: #fff;
            border-radius: 0 0 0.5rem 0.5rem;
        }
        
        /* Full source toggle */
        .full-source-toggle {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 0.375rem;
            color: #58a6ff;
            font-size: 0.8125rem;
            cursor: pointer;
            margin-bottom: 0.5rem;
            transition: all 0.15s;
        }
        .full-source-toggle:hover { background: #21262d; }
        .full-source-toggle svg { width: 1rem; height: 1rem; }
        
        /* Dropdown menu */
        .dropdown-menu {
            position: absolute;
            bottom: 100%;
            right: 0;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            border: 1px solid rgba(99, 102, 241, 0.2);
            border-radius: 0.75rem;
            padding: 0.375rem;
            min-width: 200px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.05) inset;
            z-index: 50;
            backdrop-filter: blur(12px);
            animation: dropdownFadeIn 0.15s ease-out;
        }
        @keyframes dropdownFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            color: #e5e7eb;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .dropdown-item:hover { 
            background: rgba(99, 102, 241, 0.15); 
            color: #fff;
        }
        .dropdown-item:hover svg { color: #a5b4fc; }
        .dropdown-item svg { 
            width: 1.125rem; 
            height: 1.125rem; 
            color: #6b7280;
            transition: color 0.15s;
        }
        
        /* Sources stack - overlapping circular favicons */
        .sources-stack {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            margin-left: auto;
            cursor: pointer;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            background: rgba(99, 102, 241, 0.08);
            border: 1px solid rgba(99, 102, 241, 0.15);
        }
        .sources-stack:hover { 
            background: rgba(99, 102, 241, 0.15);
            border-color: rgba(99, 102, 241, 0.3);
            transform: translateY(-1px);
        }
        .sources-label {
            font-size: 0.8125rem;
            font-weight: 500;
            color: #a5b4fc;
        }
        .sources-icons {
            display: flex;
            align-items: center;
        }
        .sources-icons img {
            width: 1.375rem;
            height: 1.375rem;
            border-radius: 50%;
            border: 2px solid #1f2937;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            background: #374151;
            object-fit: cover;
        }
        .sources-icons img:not(:first-child) {
            margin-left: -0.5rem;
        }
        .sources-label {
            font-size: 0.875rem;
            color: #9ca3af;
        }
        .sources-stack:hover .sources-label { color: #e5e7eb; }
        
        /* Collapsible Conversation Items */
        .convo-history {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .convo-card {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border: 1px solid #334155;
            border-radius: 0.75rem;
            overflow: hidden;
        }
        .convo-card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            cursor: pointer;
            transition: background 0.15s;
        }
        .convo-card-header:hover { background: rgba(99, 102, 241, 0.1); }
        .convo-card-icon {
            width: 2rem;
            height: 2rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .convo-card-icon.search { background: #3b82f6; }
        .convo-card-icon.weather { background: #f59e0b; }
        .convo-card-icon.news { background: #ef4444; }
        .convo-card-icon.general { background: #6366f1; }
        .convo-card-icon svg { width: 1rem; height: 1rem; color: white; }
        .convo-card-info {
            flex: 1;
            min-width: 0;
        }
        .convo-card-query {
            font-weight: 500;
            color: #e2e8f0;
            font-size: 0.9375rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .convo-card-meta {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.125rem;
        }
        .convo-card-chevron {
            width: 1.25rem;
            height: 1.25rem;
            color: #6b7280;
            transition: transform 0.2s;
            flex-shrink: 0;
        }
        .convo-card-chevron.collapsed { transform: rotate(-90deg); }
        .convo-card-body {
            border-top: 1px solid #334155;
            padding: 1rem;
            max-height: 600px;
            overflow-y: auto;
        }
        .convo-card-body.collapsed { display: none; }
        .convo-card-body .prose { font-size: 0.875rem; }
    </style>
</head>
<body class="min-h-screen p-8">
    <div class="max-w-3xl mx-auto">
        <h1 class="text-2xl font-bold text-indigo-400 mb-2">🔍 AI Web Search</h1>
        <p class="text-gray-400 mb-6">Search the web with AI-powered understanding.</p>
        
        <!-- Conversation Container - Cards will be added here -->
        <div id="convoContainer" class="convo-history"></div>
        
        <!-- Input Form -->
        <form id="searchForm" class="mb-6">
            <div class="flex gap-2">
                <input 
                    type="text" 
                    id="query" 
                    name="query"
                    placeholder="Ask anything that requires web search..."
                    class="flex-1 px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500"
                    autofocus
                >
                <button 
                    type="submit"
                    id="submitBtn"
                    class="px-6 py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Search
                </button>
            </div>
        </form>

        <!-- Debug Log -->
        <details class="mt-4">
            <summary class="text-gray-500 text-sm cursor-pointer hover:text-gray-400">Debug Log</summary>
            <pre id="debugLog" class="mt-2 p-3 bg-gray-900 rounded text-xs text-gray-400 max-h-[200px] overflow-auto modern-scroll"></pre>
        </details>
    </div>

    <script>
        const form = document.getElementById('searchForm');
        const queryInput = document.getElementById('query');
        const submitBtn = document.getElementById('submitBtn');
        const convoContainer = document.getElementById('convoContainer');
        const debugLog = document.getElementById('debugLog');
        
        let abortController = null;
        let currentCard = null;  // The active card being populated

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

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function log(msg) {
            const time = new Date().toLocaleTimeString();
            debugLog.textContent += `[${time}] ${msg}\n`;
            debugLog.scrollTop = debugLog.scrollHeight;
        }

        function formatReasoningText(text) {
            // Each reasoning step/paragraph gets its own row with dot + line (Groq style)
            
            if (!text || !text.trim()) {
                return '';
            }
            
            // Escape HTML to prevent XSS
            const escapeHtmlText = (str) => str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
            
            // Helper to create a reasoning item with Groq-style structure
            const createReasoningItem = (content) => `<div class="reasoning-item">
                <div class="reasoning-item-indicator">
                    <div class="reasoning-item-dot"></div>
                    <div class="reasoning-item-line"></div>
                </div>
                <div class="reasoning-item-text"><p>${escapeHtmlText(content)}</p></div>
            </div>`;
            
            // First, try to split by double newlines (explicit paragraphs from the model)
            let paragraphs = text.split(/\n\n+/).map(p => p.trim()).filter(p => p);
            
            // If we got multiple paragraphs, use them directly - each gets its own dot
            if (paragraphs.length > 1) {
                return paragraphs.map(p => createReasoningItem(p.replace(/\n/g, ' '))).join('');
            }
            
            // Otherwise, split by single newlines - each line is a step
            paragraphs = text.split(/\n/).map(p => p.trim()).filter(p => p);
            if (paragraphs.length > 1) {
                return paragraphs.map(p => createReasoningItem(p)).join('');
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
                    return steps.map(s => createReasoningItem(s)).join('');
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
                        return steps.map(s => createReasoningItem(s)).join('');
                    }
                }
            }
            
            // Single paragraph fallback
            return createReasoningItem(normalized);
        }

        // Detect programming language from code content or class
        function detectLanguage(code, className) {
            if (className) {
                const match = className.match(/language-(\w+)/);
                if (match) return match[1];
            }
            // Simple heuristics
            if (code.includes('<' + '?php') || (code.includes('function ') && code.includes('$'))) return 'php';
            if (code.includes('import ') && code.includes('from ') || code.includes('def ')) return 'python';
            if (code.includes('const ') || code.includes('let ') || code.includes('=>')) return 'javascript';
            if (code.includes('<html') || code.includes('<!DOCTYPE')) return 'html';
            if (code.includes('{') && code.includes(':') && !code.includes('function')) return 'css';
            if (code.includes('SELECT ') || code.includes('INSERT ') || code.includes('CREATE TABLE')) return 'sql';
            if (code.includes('#include') || code.includes('int main')) return 'c';
            if (code.includes('package ') && code.includes('func ')) return 'go';
            if (code.includes('fn ') && code.includes('let mut')) return 'rust';
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
            return extensions[language.toLowerCase()] || 'txt';
        }

        // Enhance code blocks with header and copy button
        function enhanceCodeBlocks(container) {
            const preElements = container.querySelectorAll('pre');
            preElements.forEach((pre, index) => {
                // Skip if already enhanced
                if (pre.dataset.enhanced) return;
                pre.dataset.enhanced = 'true';
                
                const code = pre.querySelector('code') || pre;
                const codeText = code.textContent || '';
                const codeLines = codeText.split('\n');
                const lines = codeLines.length;
                const language = detectLanguage(codeText, code.className);
                const isHtml = language === 'html' || codeText.includes('<html') || codeText.includes('<!DOCTYPE') || (codeText.includes('<') && codeText.includes('</'));
                
                // Create wrapper
                const wrapper = document.createElement('div');
                wrapper.className = 'code-block-wrapper';
                
                // Create header with buttons
                const header = document.createElement('div');
                header.className = 'code-block-header';
                
                const langLabel = document.createElement('span');
                langLabel.innerHTML = `<svg style="display:inline;width:0.875rem;height:0.875rem;margin-right:0.25rem;vertical-align:-2px" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>${language}`;
                
                const buttonsDiv = document.createElement('div');
                buttonsDiv.className = 'code-header-buttons';
                
                // View button for HTML - add as INDIVIDUAL buttons, no container
                if (isHtml) {
                    // Create preview iframe (hidden initially)
                    const previewFrame = document.createElement('iframe');
                    previewFrame.className = 'code-preview-iframe';
                    previewFrame.style.display = 'none';
                    
                    // Code button (active by default)
                    const codeBtn = document.createElement('button');
                    codeBtn.className = 'code-action-btn code-view-btn active';
                    codeBtn.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>Code`;
                    
                    // Preview button
                    const previewBtn = document.createElement('button');
                    previewBtn.className = 'code-action-btn preview-view-btn';
                    previewBtn.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>Preview`;
                    
                    // Toggle handlers
                    codeBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        codeBtn.classList.add('active');
                        previewBtn.classList.remove('active');
                        codeContent.style.display = 'flex';
                        previewFrame.style.display = 'none';
                    });
                    
                    previewBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        previewBtn.classList.add('active');
                        codeBtn.classList.remove('active');
                        codeContent.style.display = 'none';
                        previewFrame.style.display = 'block';
                        // Load content into iframe if not already loaded
                        if (!previewFrame.dataset.loaded) {
                            previewFrame.srcdoc = codeText;
                            previewFrame.dataset.loaded = 'true';
                        }
                    });
                    
                    // Add as individual buttons at the start
                    buttonsDiv.insertBefore(previewBtn, buttonsDiv.firstChild);
                    buttonsDiv.insertBefore(codeBtn, buttonsDiv.firstChild);
                    
                    // Store preview frame to add after wrapper is built
                    wrapper.previewFrame = previewFrame;
                }
                
                // Save button
                const saveBtn = document.createElement('button');
                saveBtn.className = 'code-action-btn save-btn';
                saveBtn.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>Save`;
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
                    saveBtn.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Saved!`;
                    setTimeout(() => {
                        saveBtn.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>Save`;
                    }, 2000);
                });
                buttonsDiv.appendChild(saveBtn);
                
                // Copy button
                const copyBtn = document.createElement('button');
                copyBtn.className = 'code-action-btn';
                copyBtn.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>Copy`;
                
                copyBtn.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    try {
                        await navigator.clipboard.writeText(codeText);
                        copyBtn.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Copied!`;
                        setTimeout(() => {
                            copyBtn.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>Copy`;
                        }, 2000);
                    } catch (err) {
                        console.error('Copy failed:', err);
                    }
                });
                buttonsDiv.appendChild(copyBtn);
                
                // Build code content with line numbers using table layout
                const codeContent = document.createElement('div');
                codeContent.className = 'code-content';
                
                const codeTable = document.createElement('div');
                codeTable.className = 'code-table';
                
                // Create rows for each line
                codeLines.forEach((line, i) => {
                    const row = document.createElement('div');
                    row.className = 'code-row';
                    
                    const lineNum = document.createElement('span');
                    lineNum.className = 'code-line-num';
                    lineNum.textContent = i + 1;
                    
                    const lineText = document.createElement('span');
                    lineText.className = 'code-line-text';
                    lineText.textContent = line || ' ';
                    
                    row.appendChild(lineNum);
                    row.appendChild(lineText);
                    codeTable.appendChild(row);
                });
                
                codeContent.appendChild(codeTable);
                
                header.appendChild(langLabel);
                header.appendChild(buttonsDiv);
                
                // Insert wrapper - remove old pre
                pre.parentNode.insertBefore(wrapper, pre);
                pre.remove();
                
                wrapper.appendChild(header);
                wrapper.appendChild(codeContent);
                
                // Add preview iframe if HTML
                if (wrapper.previewFrame) {
                    wrapper.appendChild(wrapper.previewFrame);
                }
            });
        }

        // Create a new conversation card - returns references to its internal elements
        function createConversationCard(query, type) {
            const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            const card = document.createElement('div');
            card.className = 'convo-card';
            card.innerHTML = `
                <div class="convo-card-header">
                    <div class="convo-card-icon ${type}">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            ${getQueryIcon(type)}
                        </svg>
                    </div>
                    <div class="convo-card-info">
                        <div class="convo-card-query">${escapeHtml(query)}</div>
                        <div class="convo-card-meta"><span class="card-time">${timeStr}</span><span class="card-sources"></span></div>
                    </div>
                    <svg class="convo-card-chevron" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="convo-card-body modern-scroll">
                    <!-- Activity Section -->
                    <div class="card-activity hidden mb-3">
                        <div class="flex items-center gap-2 text-indigo-400 text-sm mb-2">
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
                    <div class="card-citations hidden mb-3">
                        <div class="flex flex-wrap gap-2 card-citations-list"></div>
                    </div>
                    
                    <!-- Response Section -->
                    <div class="card-response-label response-label hidden">Response</div>
                    <div class="card-response prose">
                        <p class="text-gray-400"><span class="activity-spinner inline-block w-4 h-4 border-2 border-indigo-400 border-t-transparent rounded-full"></span> Thinking...</p>
                    </div>
                    
                    <!-- Footer Actions -->
                    <div class="card-footer hidden flex items-center gap-2 mt-4 pt-4 border-t border-gray-700/30" style="justify-content: space-between;">
                        <div class="flex items-center gap-0.5">
                            <button class="action-btn card-copy-btn" title="Copy"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg></button>
                            <button class="action-btn card-like-btn" title="Good"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/></svg></button>
                            <button class="action-btn card-dislike-btn" title="Bad"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14H5.236a2 2 0 01-1.789-2.894l3.5-7A2 2 0 018.736 3h4.018c.163 0 .326.02.485.06L17 4m-7 10v5a2 2 0 002 2h.095c.5 0 .905-.405.905-.905 0-.714.211-1.412.608-2.006L17 13V4m-7 10h2m5-10h2a2 2 0 012 2v6a2 2 0 01-2 2h-2.5"/></svg></button>
                            <button class="action-btn card-share-btn" title="Share"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg></button>
                            <button class="action-btn card-regen-btn" title="Regenerate"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg></button>
                            <div class="relative">
                                <button class="action-btn card-more-btn" title="More actions"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/></svg></button>
                                <div class="dropdown-menu hidden card-more-menu">
                                    <div class="dropdown-item card-menu-branch"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>Branch in new chat</div>
                                    <div class="dropdown-item card-menu-read"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>Read aloud</div>
                                    <div class="dropdown-item card-menu-report"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/></svg>Report message</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-sources-stack sources-stack hidden">
                            <div class="card-sources-icons sources-icons"></div>
                            <span class="sources-label">Sources</span>
                        </div>
                    </div>
                </div>
            `;
            
            // Get references to internal elements
            const elements = {
                card,
                header: card.querySelector('.convo-card-header'),
                chevron: card.querySelector('.convo-card-chevron'),
                body: card.querySelector('.convo-card-body'),
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
                moreMenu: card.querySelector('.card-more-menu'),
                menuBranch: card.querySelector('.card-menu-branch'),
                menuRead: card.querySelector('.card-menu-read'),
                menuReport: card.querySelector('.card-menu-report'),
            };
            
            // Header toggle (collapse/expand)
            elements.header.addEventListener('click', () => {
                elements.body.classList.toggle('collapsed');
                elements.chevron.classList.toggle('collapsed');
            });
            
            // Reasoning toggle
            elements.reasoningToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                const isOpen = elements.reasoningChevron.classList.toggle('open');
                elements.reasoningContent.style.display = isOpen ? 'block' : 'none';
            });
            
            // Copy button
            elements.copyBtn.addEventListener('click', async (e) => {
                e.stopPropagation();
                try {
                    await navigator.clipboard.writeText(elements.response.innerText);
                    elements.copyBtn.innerHTML = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
                    setTimeout(() => {
                        elements.copyBtn.innerHTML = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>';
                    }, 2000);
                } catch (err) {}
            });
            
            // Like/Dislike with visual feedback
            elements.likeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const isActive = elements.likeBtn.classList.contains('liked');
                elements.likeBtn.classList.toggle('liked');
                elements.dislikeBtn.classList.remove('disliked');
                // Animate
                if (!isActive) {
                    elements.likeBtn.style.transform = 'scale(1.2)';
                    setTimeout(() => elements.likeBtn.style.transform = '', 150);
                }
            });
            elements.dislikeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const isActive = elements.dislikeBtn.classList.contains('disliked');
                elements.dislikeBtn.classList.toggle('disliked');
                elements.likeBtn.classList.remove('liked');
                // Animate
                if (!isActive) {
                    elements.dislikeBtn.style.transform = 'scale(1.2)';
                    setTimeout(() => elements.dislikeBtn.style.transform = '', 150);
                }
            });
            
            // Share button
            elements.shareBtn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const shareText = `${query}\n\n${elements.response.innerText}`;
                if (navigator.share) {
                    try {
                        await navigator.share({ title: 'AI Web Search', text: shareText });
                    } catch (err) {}
                } else {
                    // Fallback: copy to clipboard
                    await navigator.clipboard.writeText(shareText);
                    elements.shareBtn.innerHTML = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
                    setTimeout(() => {
                        elements.shareBtn.innerHTML = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>';
                    }, 2000);
                }
            });
            
            // Regenerate
            elements.regenBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                queryInput.value = query;
                form.dispatchEvent(new Event('submit'));
            });
            
            // More actions menu toggle
            elements.moreBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                elements.moreMenu.classList.toggle('hidden');
            });
            
            // Close menu when clicking outside
            document.addEventListener('click', () => {
                elements.moreMenu.classList.add('hidden');
            });
            
            // Branch in new chat
            elements.menuBranch.addEventListener('click', (e) => {
                e.stopPropagation();
                elements.moreMenu.classList.add('hidden');
                queryInput.value = query;
                queryInput.focus();
            });
            
            // Read aloud (TTS)
            elements.menuRead.addEventListener('click', async (e) => {
                e.stopPropagation();
                elements.moreMenu.classList.add('hidden');
                const text = elements.response.innerText;
                try {
                    const response = await fetch('/audio/tts', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ text })
                    });
                    
                    // Handle rate limit response
                    if (response.status === 429) {
                        try {
                            const data = await response.json();
                            if (typeof window.showTtsLimitModal === 'function') {
                                window.showTtsLimitModal(data);
                            }
                        } catch (parseErr) {
                            console.debug('TTS rate limit response parse error:', parseErr);
                        }
                        return;
                    }
                    
                    if (response.ok) {
                        const blob = await response.blob();
                        const url = URL.createObjectURL(blob);
                        const audio = new Audio(url);
                        audio.play();
                        audio.onended = () => URL.revokeObjectURL(url);
                    }
                } catch (err) {
                    console.error('TTS error:', err);
                }
            });
            
            // Report message
            elements.menuReport.addEventListener('click', (e) => {
                e.stopPropagation();
                elements.moreMenu.classList.add('hidden');
                alert('Thank you for your feedback. This response has been flagged for review.');
            });
            
            // Sources stack click
            elements.sourcesStack.addEventListener('click', (e) => {
                e.stopPropagation();
                elements.citations.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
            
            convoContainer.appendChild(card);
            return elements;
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const query = queryInput.value.trim();
            if (!query) return;
            
            const queryType = getQueryType(query);

            // Abort previous request
            if (abortController) abortController.abort();
            abortController = new AbortController();
            
            // Create the card immediately - this IS the response container
            currentCard = createConversationCard(query, queryType);
            
            // Track state for this card
            let activities = { searches: [], reads: [] };
            let accumulatedReasoning = '';
            let accumulatedContent = '';
            let citations = [];
            
            // Update UI
            submitBtn.disabled = true;
            submitBtn.textContent = 'Searching...';
            queryInput.value = '';
            debugLog.textContent = '';
            log(`Query: ${query}`);

            try {
                const response = await fetch('/websearch', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `prompt=${encodeURIComponent(query)}`,
                    signal: abortController.signal
                });

                log(`Response status: ${response.status}`);

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                let contentStarted = false;

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) {
                        log('Stream ended');
                        break;
                    }

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop() || '';

                    for (const line of lines) {
                        if (!line.startsWith('data: ')) continue;
                        
                        const jsonStr = line.slice(6).trim();
                        if (!jsonStr) continue;

                        try {
                            const data = JSON.parse(jsonStr);

                            // Handle activity events
                            if (data.activity === 'websearch') {
                                currentCard.activity.classList.remove('hidden');
                                
                                if (data.type === 'search' && data.query) {
                                    activities.searches.push({ query: data.query });
                                } else if (data.type === 'read' && data.domain) {
                                    activities.reads.push({ domain: data.domain, url: data.url });
                                    if (!citations.find(c => c.url === data.url)) {
                                        citations.push({ domain: data.domain, url: data.url });
                                        updateCardCitations(currentCard, citations);
                                    }
                                }
                                updateCardActivity(currentCard, activities);
                                continue;
                            }

                            // Handle reasoning chunks
                            if (data.reasoning !== undefined) {
                                accumulatedReasoning += data.reasoning;
                                currentCard.reasoning.classList.remove('hidden');
                                currentCard.reasoningContent.innerHTML = formatReasoningText(accumulatedReasoning);
                                currentCard.reasoningContent.scrollTop = currentCard.reasoningContent.scrollHeight;
                                continue;
                            }

                            // Handle text chunks
                            if (data.text) {
                                if (!contentStarted) {
                                    currentCard.response.innerHTML = '';
                                    currentCard.responseLabel.classList.remove('hidden');
                                    contentStarted = true;
                                }
                                accumulatedContent += data.text;
                                currentCard.response.innerHTML = accumulatedContent.replace(/\n/g, '<br>');
                                continue;
                            }

                            // Handle final HTML response
                            if (data.final) {
                                // Always show the response label and handle content
                                currentCard.responseLabel.classList.remove('hidden');
                                
                                if (data.html) {
                                    currentCard.response.innerHTML = data.html;
                                    // Enhance code blocks with headers and copy buttons
                                    enhanceCodeBlocks(currentCard.response);
                                } else if (accumulatedContent) {
                                    // Fallback: use accumulated content if no final HTML provided
                                    currentCard.response.innerHTML = accumulatedContent.replace(/\n/g, '<br>');
                                } else if (data.contentEmpty && data.reasoningHtml) {
                                    // Content was empty, show informative message
                                    currentCard.response.innerHTML = '<p class="text-gray-400 italic">The model\'s analysis is shown in the reasoning section above.</p>';
                                }
                                
                                if (data.reasoningHtml) {
                                    currentCard.reasoning.classList.remove('hidden');
                                    currentCard.reasoningContent.innerHTML = data.reasoningHtml;
                                } else if (accumulatedReasoning) {
                                    // Fallback: use accumulated reasoning if no final reasoningHtml
                                    currentCard.reasoning.classList.remove('hidden');
                                    currentCard.reasoningContent.innerHTML = formatReasoningText(accumulatedReasoning);
                                }
                                
                                finishCardActivity(currentCard, activities);
                                currentCard.footer.classList.remove('hidden');
                                log('Received final response');
                            }

                            if (data.error) {
                                currentCard.response.innerHTML = `<p class="text-red-400">Error: ${data.error}</p>`;
                            }

                        } catch (parseErr) {
                            log(`Parse error: ${parseErr.message}`);
                        }
                    }
                }

            } catch (err) {
                if (err.name === 'AbortError') {
                    log('Request aborted');
                    currentCard.response.innerHTML += '<p class="text-yellow-400 mt-2"><em>Cancelled</em></p>';
                } else {
                    log(`Error: ${err.message}`);
                    currentCard.response.innerHTML = `<p class="text-red-400">Error: ${err.message}</p>`;
                }
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Search';
                finishCardActivity(currentCard, activities);
                currentCard.footer.classList.remove('hidden');
                currentCard = null;
            }
        });

        function updateCardActivity(card, activities) {
            const parts = [];
            if (activities.searches.length > 0) parts.push('searched the web');
            if (activities.reads.length > 0) parts.push(`visited ${activities.reads.length} site${activities.reads.length > 1 ? 's' : ''}`);
            
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
            card.activityText.textContent = parts.length > 0 ? `✓ ${parts.join(', ')}` : '✓ Done';
            
            // Update header meta with source count
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
            card.citationsList.innerHTML = citations.map((c, i) => 
                `<a href="${c.url}" target="_blank" class="citation" onclick="event.stopPropagation()">
                    <img src="https://www.google.com/s2/favicons?domain=${c.domain}&sz=16" alt="" class="w-4 h-4 rounded-sm" onerror="this.style.display='none'">
                    <span class="citation-num">${i + 1}</span>
                    <span>${c.domain}</span>
                </a>`
            ).join('');
            
            card.sourcesStack.classList.remove('hidden');
            const iconsToShow = citations.slice(0, 4);
            card.sourcesIcons.innerHTML = iconsToShow.map(c => 
                `<img src="https://www.google.com/s2/favicons?domain=${c.domain}&sz=32" alt="${c.domain}" title="${c.domain}" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%236b7280%22><circle cx=%2212%22 cy=%2212%22 r=%2210%22/></svg>'">`
            ).join('');
        }

        // Escape to cancel
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && abortController) {
                abortController.abort();
                log('Aborted via Escape key');
            }
        });
    </script>
</body>
</html>
