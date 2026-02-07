<script>
document.addEventListener('DOMContentLoaded', function() {
    const depthSelect = document.getElementById('treeDepth');
    // Load persisted value from localStorage
    const savedDepth = localStorage.getItem('compactTreeDepth');
    if (savedDepth && depthSelect) {
        depthSelect.value = savedDepth;
    }
    // Save on change
    if (depthSelect) {
        depthSelect.addEventListener('change', function() {
            const selectedDepth = this.value;
            console.log('Dropdown value changed to:', selectedDepth);
            localStorage.setItem('compactTreeDepth', selectedDepth);
            // Trigger depth change logic
            loadCompactNetwork(selectedDepth);
        });
    } else {
        console.error('Dropdown element with id "treeDepth" not found.');
    }
});

async function loadCompactNetwork(depth) {
    try {
        const response = await fetch(`/api/user/commissions?depth=${depth}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const text = await response.text();
        try {
            const data = JSON.parse(text);
            renderCompactCommissions(data);
        } catch (jsonError) {
            console.error('Failed to parse JSON response:', text);
        }
    } catch (error) {
        console.error('Failed to load compact network:', error);
    }
}
</script>
<style>
/* Elegant scrollbar for commissions modal content */
#compactCommissionsContent {
    scrollbar-width: thin;
    scrollbar-color: #7c3aed #23272f;
}
#compactCommissionsContent::-webkit-scrollbar {
    width: 10px;
    background: #23272f;
    border-radius: 8px;
}
#compactCommissionsContent::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #7c3aed 0%, #6366f1 100%);
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(124,58,237,0.15);
    min-height: 32px;
    border: 2px solid #23272f;
}
#compactCommissionsContent::-webkit-scrollbar-track {
    background: #23272f;
    border-radius: 8px;
}
#compactCommissionsContent::-webkit-scrollbar-corner {
    background: #23272f;
}
@media (max-width: 600px) {
    #compactCommissionsContent::-webkit-scrollbar {
        width: 6px;
    }
    #compactCommissionsContent::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #7c3aed 0%, #6366f1 100%);
        border-radius: 6px;
        border: 1px solid #23272f;
    }
}
<style>
</style>
<style>
.commissions-modal-content {
    position: relative;
    width: 880px;
    max-width: 95vw;
    background: var(--card-bg,#0b1220);
    color: var(--text-primary,#e6eef8);
    border-radius: 10px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.6);
    padding: 18px;
    margin: 0 auto;
}
.commissions-modal-table {
    margin-top: 12px;
    max-height: 60vh;
    overflow: auto;
}
@media (max-width: 600px) {
    .commissions-modal-content {
        width: 98vw;
        min-width: 0;
        padding: 8px;
        border-radius: 6px;
    }
    .commissions-modal-table {
        font-size: 14px;
        max-height: 60vh;
        padding: 0;
    }
    .commissions-modal-content table {
        font-size: 13px;
        min-width: 0;
        width: 100%;
        word-break: break-word;
        overflow-x: auto;
        display: block;
    }
    .commissions-modal-content th, .commissions-modal-content td {
        padding: 6px 4px;
    }
}
</style>
<?php
/** Compact View - Views/users/network-tree/compact-view.php
 * This file is included from `public/index.php` route. It assumes
 * `ROOT_PATH` is defined by the including script.
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 3));
}

include ROOT_PATH . '/src/Views/layout/header.php';
include ROOT_PATH . '/src/Views/layout/sidebar.php';
?>

<div id="mainContent" class="min-h-screen bg-gray-50 dark:bg-gray-900 transition-all duration-300 ease-in-out">
    <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700" style="height:72px; display:flex; align-items:center; padding-left:80px; padding-right:20px;">
        <div class="flex items-center py-6 justify-between w-full">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white text-left">Network — Compact View</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Raw compact canvas (no pan/zoom controls)</p>
            </div>
            <div>
                <button id="showCommissionsBtn" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg" title="Show Commissions">Show Commissions</button>
                <a href="/user/network-tree" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg">Back</a>
            </div>
        </div>
    </div>

    <div class="py-6 px-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
            <div id="compactCanvas" class="compact-canvas">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <label class="text-sm text-gray-600 dark:text-gray-300">Depth:</label>
                        <div class="relative inline-block">
                            <select id="treeDepth" class="appearance-none w-[220px] pr-10 px-3 py-1 border rounded bg-white text-gray-800 border-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:border-gray-600">
                            <option value="1">1 Level</option>
                            <option value="2">2 Levels</option>
                            <option value="3" selected>3 Levels</option>
                            <option value="4">4 Levels</option>
                            <option value="5">5 Levels</option>
                            <option value="6">6 Levels</option>
                            <option value="7">7 Levels</option>
                            <option value="8">8 Levels</option>
                            <option value="9">9 Levels</option>
                            </select>
                            <div class="absolute inset-y-0 right-2 flex items-center pointer-events-none">
                                <svg class="w-3 h-2 text-gray-700 dark:text-gray-200" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <path d="M0 0L10 0L5 6Z" fill="currentColor" />
                                </svg>
                            </div>
                        </div>

                        <label class="text-sm text-gray-600 dark:text-gray-300">View:</label>
                        <div class="relative inline-block">
                            <select id="viewMode" class="appearance-none w-[220px] pr-10 px-3 py-1 border rounded bg-white text-gray-800 border-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:border-gray-600">
                            <option value="compact" data-url="/user/network-tree/compact-view" selected>Compact View</option>
                            <option value="organizational">Organizational Chart</option>
                            <option value="network">Network View</option>
                            <option value="tree">Tree View</option>
                            <option value="hierarchical">Hierarchical View</option>
                            <option value="circle" data-url="/user/network-tree/circle-view">Circle View</option>
                            </select>
                            <div class="absolute inset-y-0 right-2 flex items-center pointer-events-none">
                                <svg class="w-3 h-2 text-gray-700 dark:text-gray-200" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <path d="M0 0L10 0L5 6Z" fill="currentColor" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div>
                        <button id="refreshCompact" class="bg-blue-600 text-white px-3 py-1 rounded">Refresh</button>
                    </div>
                </div>

                <div id="compactLoading" class="text-center py-6 text-gray-500">Loading compact network...</div>
                <div class="compact-viewport" style="max-width:var(--tree-viewport-max-width,1200px); max-height:calc(100vh - 260px); overflow:auto; position:relative;">
                    <div id="compactCanvasInner" class="compact-canvas-inner" style="position:relative;">
                        <div id="compactContent" style="display:none;"></div>
                    </div>
                </div>
                <div id="compactError" style="display:none;color:#d9534f;">Failed to load compact network</div>
            </div>
        </div>
    </div>
</div>

<style>
.compact-canvas { max-width: var(--tree-viewport-max-width, 1200px); box-sizing: border-box; }
/* Compact nested list styling (match admin compact node look) */
.compact-list { padding: 8px; max-width: var(--tree-viewport-max-width, 920px); margin: 0 auto; box-sizing: border-box; display:flex; flex-direction:column; align-items:flex-start; gap:6px; overflow: visible; }
/* Light theme (default) */
.compact-node { display: flex; align-items: center; background: #ffffff; border: 1px solid #e6e7eb; padding:10px 12px; border-radius:18px; margin:6px 0; box-shadow:0 6px 18px rgba(2,6,23,0.06); cursor:pointer; transition: background .12s ease, transform .12s ease; box-sizing:border-box; width:200px; min-width:200px; max-width:200px; flex: 0 0 200px; color: #0f1724; }
.compact-node:hover { background: #f3f4f6; transform: scale(1.02); }
.compact-node .avatar { width:28px; height:28px; border-radius:50%; background:#3b82f6; color:#ffffff; display:flex; align-items:center; justify-content:center; font-weight:700; margin-right:10px; flex: 0 0 28px; font-size:13px; }
.compact-node .info { flex:1 1 auto; min-width:0; overflow:hidden; }
.compact-node .title { font-weight:700; font-size:14px; white-space:normal; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
.compact-node .sub { font-size:11px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
/* Dark theme override */
.dark .compact-node { background:#111827; border: none; color:#E6EEF8; box-shadow:0 6px 18px rgba(2,6,23,0.6); }

/* caret is provided inline using an absolutely-positioned SVG inside the wrapper (Tailwind classes handle theme) */
</style>

<style>
/* Tooltip for compact nodes */
.compact-tooltip {
    position: fixed;
    background: #ffffff;
    color: #0f1724;
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px solid #e6e7eb;
    box-shadow: 0 6px 20px rgba(2,6,23,0.06);
    font-size: 13px;
    z-index: 2000;
    pointer-events: none;
    min-width: 180px;
}
.compact-tooltip .t-title { font-weight: 700; margin-bottom: 6px; }
.compact-tooltip .t-sub { color: #6b7280; font-size: 12px; }
.dark .compact-tooltip { background: #0b1220; color: #e6eef8; border: none; box-shadow: 0 6px 20px rgba(2,6,23,0.6); }

</style>

<style>
/* Connector stacking: ensure svg sits behind nodes */
.compact-list { position: relative; }
.compact-node { position: relative; z-index: 2; }
.compact-connector-layer { position: absolute; left: 0; top: 0; width: 100%; height: 100%; pointer-events: none; z-index: 1; }
</style>

<style>
/* Viewport specifics: limit the canvas area and enable scrolling when content overflows */
.compact-viewport { box-sizing: border-box; margin: 0 auto; }
.compact-viewport::-webkit-scrollbar { height: 10px; width: 10px; }
.compact-viewport::-webkit-scrollbar-thumb { background: rgba(99,102,241,0.18); border-radius: 8px; }
.compact-viewport::-webkit-scrollbar-track { background: transparent; }
</style>

<style>
/* Inner canvas wrapper so svg and nodes share same transform origin */
.compact-canvas-inner { position: relative; transform-origin: 0 0; }
</style>
</style>

<script>
async function loadCompact() {
    const depthEl = document.getElementById('treeDepth');
    const viewEl = document.getElementById('viewMode');
    const depth = depthEl ? parseInt(depthEl.value, 10) : 3;
    const view = viewEl ? viewEl.value : 'compact';

    try {
        const res = await fetch('/api/user/network-tree?depth=' + encodeURIComponent(depth));
        if (!res.ok) throw new Error('Network error');
        const payload = await res.json();
        if (!payload.success) throw new Error('API error');
        const tree = payload.data;
        document.getElementById('compactLoading').style.display = 'none';
        const content = document.getElementById('compactContent');
        content.innerHTML = '';
        const el = buildLevelsHtml(tree);
        content.appendChild(el);
        content.style.display = '';
    } catch (e) {
        document.getElementById('compactLoading').style.display = 'none';
        document.getElementById('compactError').style.display = '';
        console.error(e);
    }
}

// Mirror simple behavior from network-tree controls: persist depth/view and refresh
document.addEventListener('DOMContentLoaded', function(){
    const depthEl = document.getElementById('treeDepth');
    const viewEl = document.getElementById('viewMode');
    const refreshBtn = document.getElementById('refreshCompact');

    // Do not persist selector values across reloads for compact view

    if (depthEl) depthEl.addEventListener('change', function(){
        document.getElementById('compactLoading').style.display = '';
        document.getElementById('compactContent').style.display = 'none';
        loadCompact();
    });

    if (viewEl) viewEl.addEventListener('change', function(){
        const val = (this.value || '').toString().trim();
        // If the selected option provides an explicit URL, navigate to it
        try {
            const opt = (this.options && this.options[this.selectedIndex]) ? this.options[this.selectedIndex] : null;
            const url = opt && opt.dataset ? (opt.dataset.url || null) : null;
            if (url) {
                try { window.location.href = url; } catch(e){}
                return;
            }
        } catch(e) {
            // fall through to other handlers
        }

        if (val === 'network') {
            try { window.location.href = '/user/network-tree'; } catch(e){}
            return;
        }
        // Other view modes are ignored in compact view
    });

    if (refreshBtn) refreshBtn.addEventListener('click', function(){
        document.getElementById('compactLoading').style.display = '';
        document.getElementById('compactContent').style.display = 'none';
        loadCompact();
    });

    // initial load
    loadCompact();
});

function buildLevelsHtml(root) {
    // Use the admin-style HTML string generator for compact nodes for
    // visual parity. The generator returns HTML string; we wrap it
    // into a DOM element and attach safe click handlers.
    function safeCharFirst(s) {
        try { return (s || '').charAt(0).toUpperCase(); } catch(e) { return 'U'; }
    }

    function generateCompactTreeHtml(node, level) {
        const hasChildren = node && node.children && node.children.length > 0;
        const nodeId = `node-${node.id}`;
        const indentLevel = (level || 0) * 25;

        const fullname = node.fullname || '';
        const username = node.username || fullname || ('user' + (node.id || ''));
        const publicId = node.public_id || node.publicId || '';
        const firstChar = safeCharFirst(username);
        const totalCommissions = typeof node.totalCommissions !== 'undefined' ? Number(node.totalCommissions) : 0;
        const directReferrals = typeof node.directReferrals !== 'undefined' ? node.directReferrals : (node.direct_refs || 0);

        // Build markup using CSS classes so theme (light/dark) is controlled via global classes
        let nodeHtml = `
            <div style="margin-left: ${indentLevel}px; margin: 5px 0; display: flex; align-items: center;" data-node-id="${nodeId}">
                ${level > 0 ? `<i class="fas fa-corner-down-right mr-2" style="margin-right: 8px;"></i>` : ''}
                <div class="compact-node" data-user-id="${node.id}" data-public-id="${publicId}" data-fullname="${fullname.replace(/"/g, '&quot;')}" data-username="${node.username || ''}" data-level="${node.level || ''}" data-commissions="${Math.round(totalCommissions)}" data-refs="${directReferrals}">
                    <div class="avatar" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5z" fill="#ffffff"/>
                            <path d="M4 20c0-3.313 4.03-6 8-6s8 2.687 8 6v1H4v-1z" fill="#ffffff"/>
                        </svg>
                    </div>
                    <div class="info">
                        <div class="title">${username}</div>
                        <div class="sub">L${node.level || ''} • $${Math.round(totalCommissions)}</div>
                    </div>
                    ${hasChildren ? `<i class="fas fa-chevron-right" style="margin-left:8px;"></i>` : ''}
                </div>
            </div>
            ${hasChildren ? `
                <div id="${nodeId}-children" style="margin-left: ${indentLevel + 20}px;">
                    ${node.children.map(child => generateCompactTreeHtml(child, level + 1)).join('')}
                </div>
            ` : ''}
        `;

        return nodeHtml;
    }

    const container = document.createElement('div');
    container.className = 'compact-list';
    try {
        if (root) {
            // If root is an array (multiple roots), render each; otherwise render the single root
            if (Array.isArray(root)) {
                container.innerHTML = root.map(r => generateCompactTreeHtml(r, 0)).join('');
            } else {
                container.innerHTML = generateCompactTreeHtml(root, 0);
            }

            // Attach click handlers to nodes safely
            setTimeout(() => {
                try {
                    container.querySelectorAll('.compact-node').forEach(card => {
                        // Click opens modal/profile
                        card.addEventListener('click', function(e) {
                            const publicId = (this.dataset.publicId || '').trim();
                            const userId = (this.dataset.userId || '').toString().trim();
                            const targetId = publicId || userId;
                            if (!targetId) return;

                            // Call modal handlers if present (non-blocking), but always navigate to the
                            // network overview. Include the clicked node id as a query param so the
                            // network view can pre-focus the node if desired.
                            try { if (typeof showUserModal === 'function' && userId) { try { showUserModal(userId); } catch(e){} } } catch(e){}
                            try { if (typeof showUserDetails === 'function' && userId) { try { showUserDetails(userId); } catch(e){} } } catch(e){}

                            try {
                                const nodeParam = encodeURIComponent(targetId || '');
                                window.location.href = '/user/profile/' + nodeParam;
                            } catch (ignore) { }
                        });

                        // Hover shows tooltip with more details
                        card.addEventListener('mouseenter', function(e) {
                            showCompactTooltip(this, e);
                        });
                        card.addEventListener('mousemove', function(e) {
                            moveCompactTooltip(e);
                        });
                        card.addEventListener('mouseleave', function(e) {
                            hideCompactTooltip();
                        });
                    });
                    // (commissions button removed — commission info is shown in tooltip)
                } catch (handlerErr) {
                    console.warn('Error attaching compact-node handlers', handlerErr);
                }
            }, 10);
        }
    } catch(e) {
        container.textContent = 'Error rendering compact network';
        console.error(e);
    }

    return container;
}

// Tooltip implementation
let _compactTooltipEl = null;
function ensureCompactTooltip() {
    if (!_compactTooltipEl) {
        _compactTooltipEl = document.createElement('div');
        _compactTooltipEl.className = 'compact-tooltip';
        _compactTooltipEl.style.display = 'none';
        document.body.appendChild(_compactTooltipEl);
    }
}

function showCompactTooltip(node, event) {
    ensureCompactTooltip();
    const fullname = node.dataset.fullname || '';
    const username = node.dataset.username || '';
    const level = node.dataset.level || '';
    const commissions = node.dataset.commissions || '0';
    const refs = node.dataset.refs || '0';

    // compute per-individual (average) commission when possible
    const commissionsNum = Number(commissions) || 0;
    const refsNum = Number(refs) || 0;
    const perIndividual = refsNum > 0 ? (commissionsNum / refsNum) : commissionsNum;
    // Determine applicable commission rate (prefer server-provided levels if available)
    function parseServerRates() {
        try {
            if (window.__serverLevels && Array.isArray(window.__serverLevels)) {
                // Try to find the current level object
                const currentLevelId = window.__currentLevelId || null;
                let lvlObj = null;
                if (currentLevelId !== null) {
                    lvlObj = window.__serverLevels.find(l => String(l.id) === String(currentLevelId));
                }
                if (!lvlObj) lvlObj = window.__serverLevels[0];
                if (lvlObj && lvlObj.commission_rate_json) {
                    const parsed = JSON.parse(lvlObj.commission_rate_json);
                    if (Array.isArray(parsed) && parsed.length) return parsed.map(r => Number(r) || 0);
                }
            }
        } catch (e) {
            console.warn('Failed to parse server commission rates', e);
        }
        return null;
    }

    function formatPercentage(rate) {
        // rate is decimal (e.g., 0.05)
        const pct = (Number(rate) || 0) * 100;
        if (Number.isInteger(pct)) return `${pct}%`;
        // show up to 2 decimals but strip trailing zeros
        return `${parseFloat(pct.toFixed(2)).toString()}%`;
    }

    const serverRates = parseServerRates();
    const defaultRates = [0.05,0.04,0.03,0.02,0.01];
    const rates = serverRates && serverRates.length ? serverRates : defaultRates;
    const nodeLevel = Math.max(1, Number(level) || 1);
    const rateIndex = Math.max(0, Math.min(rates.length - 1, nodeLevel - 1));
    const applicableRate = Number(rates[rateIndex] || rates[0] || 0);
    const myCommission = commissionsNum * applicableRate;

    _compactTooltipEl.innerHTML = `
        <div class="t-title">${fullname} ${username ? '(@'+username+')' : ''}</div>
        <div class="t-sub">Level: ${level} • Refs: ${refs}</div>
        <div style="margin-top:6px; font-weight:700;">$${commissionsNum.toLocaleString()}</div>
        <div style="margin-top:4px; font-size:12px; color:#6b7280;">Per individual: $${perIndividual.toLocaleString(undefined, {maximumFractionDigits:2})}</div>
        <div style="margin-top:6px; font-size:13px; font-weight:700;">My commission: $${myCommission.toLocaleString(undefined, {maximumFractionDigits:2})}</div>
        <div style="margin-top:4px; font-size:12px; color:#6b7280;">Applied rate: ${formatPercentage(applicableRate)}</div>
    `;
    _compactTooltipEl.style.display = 'block';
    moveCompactTooltip(event);
}

function moveCompactTooltip(event) {
    if (!_compactTooltipEl) return;
    // Position tooltip above and to the right of cursor, but keep inside viewport
    const pad = 12;
    const tooltipRect = _compactTooltipEl.getBoundingClientRect();
    let left = event.clientX + 16;
    let top = event.clientY - tooltipRect.height - 10;
    if (left + tooltipRect.width + pad > window.innerWidth) left = window.innerWidth - tooltipRect.width - pad;
    if (top < pad) top = event.clientY + 16;
    _compactTooltipEl.style.left = left + 'px';
    _compactTooltipEl.style.top = top + 'px';
}

function hideCompactTooltip() {
    if (_compactTooltipEl) _compactTooltipEl.style.display = 'none';
}

/* Connector layer and drawing: per-parent vertical trunks + short horizontal branches
   - Uses actual node bounding boxes to align trunks to node centers
   - Simple collision avoidance shifts trunk X if too close to an existing trunk
   - Throttled redraw on resize/scroll
*/
function throttle(fn, wait) {
    let timeout = null, last = 0;
    return function() {
        const now = Date.now();
        const args = arguments;
        const ctx = this;
        const remaining = wait - (now - last);
        if (remaining <= 0) {
            if (timeout) { clearTimeout(timeout); timeout = null; }
            last = now;
            fn.apply(ctx, args);
        } else if (!timeout) {
            timeout = setTimeout(function() { last = Date.now(); timeout = null; fn.apply(ctx, args); }, remaining);
        }
    };
}

function ensureConnectorLayer(contentEl) {
    if (!contentEl) return null;
    // Prefer attaching the svg to the inner canvas wrapper so svg and nodes scale together
    const wrapper = contentEl.closest('.compact-canvas-inner') || contentEl.closest('.compact-viewport') || contentEl.parentElement || contentEl;
    if (!wrapper) return null;
    let existing = wrapper.querySelector('.compact-connector-layer');
    if (existing && existing.tagName === 'svg') {
        // clear children
        while (existing.firstChild) existing.removeChild(existing.firstChild);
        return existing;
    }
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.classList.add('compact-connector-layer');
    svg.setAttribute('preserveAspectRatio', 'none');
    svg.style.position = 'absolute';
    svg.style.left = '0px';
    svg.style.top = '0px';
    svg.style.pointerEvents = 'none';
    svg.style.zIndex = '1';
    // Ensure the wrapper is positioned so absolute children align
    try { if (wrapper && wrapper.style) wrapper.style.position = wrapper.style.position || 'relative'; } catch (e) {}
    // Insert behind other content inside the wrapper
    wrapper.insertBefore(svg, wrapper.firstChild);
    return svg;
}

function drawConnectors() {
    const content = document.getElementById('compactContent');
    if (!content) return;
    // ensure content is positioned so absolute svg aligns
    content.style.position = 'relative';
    const svg = ensureConnectorLayer(content);
    if (!svg) return;

    // Bind scroll on the viewport to redraw connectors if not already bound
    const vpBind = content.closest('.compact-viewport') || content.parentElement || content;
    try {
        if (vpBind && !vpBind.dataset._connBound) {
            vpBind.addEventListener('scroll', _redrawConnectorsThrottled);
            vpBind.dataset._connBound = '1';
        }
    } catch (e) {}

    // find the scrollable viewport (if any) so we can account for scroll offsets
    const vp = content.closest('.compact-viewport') || content.parentElement || content;
    try { if (vp && vp.style) vp.style.position = vp.style.position || 'relative'; } catch (e) {}

    const contentRect = content.getBoundingClientRect();
    const scrollW = Math.max(content.scrollWidth || contentRect.width, contentRect.width);
    const scrollH = Math.max(content.scrollHeight || contentRect.height, contentRect.height);
    const width = Math.max(600, Math.round(scrollW));
    const height = Math.max(400, Math.round(scrollH));
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    // make svg cover the full scrollable area so connectors align even when scrolled
    svg.style.width = width + 'px';
    svg.style.height = height + 'px';

    // simple defs: arrow marker
    const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
    const marker = document.createElementNS('http://www.w3.org/2000/svg', 'marker');
    marker.setAttribute('id', 'compact-arrow');
    marker.setAttribute('markerWidth', '8');
    marker.setAttribute('markerHeight', '8');
    marker.setAttribute('refX', '6');
    marker.setAttribute('refY', '3');
    marker.setAttribute('orient', 'auto');
    marker.setAttribute('markerUnits', 'strokeWidth');
    const mpath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    mpath.setAttribute('d', 'M0,0 L6,3 L0,6 L2,3 z');
    mpath.setAttribute('fill', '#94a3b8');
    marker.appendChild(mpath);
    defs.appendChild(marker);
    svg.appendChild(defs);

    const parents = Array.from(content.querySelectorAll('[data-node-id]'));
    const usedXs = [];
    const collisionGap = 14; // px
    const strokeColor = '#94a3b8';

    function offsetWithin(el, ancestor) {
        let x = 0, y = 0;
        let cur = el;
        while (cur && cur !== ancestor && cur.offsetParent) {
            x += cur.offsetLeft;
            y += cur.offsetTop;
            cur = cur.offsetParent;
        }
        return { x: x, y: y, width: el.offsetWidth || 0, height: el.offsetHeight || 0 };
    }

    parents.forEach(p => {
        const pid = p.getAttribute('data-node-id');
        if (!pid) return;
        const childrenWrap = document.getElementById(pid + '-children');
        if (!childrenWrap) return; // no immediate children

        const parentCard = p.querySelector('.compact-node');
        if (!parentCard) return;
        // compute unscaled offsets relative to the content element
        const pOff = offsetWithin(parentCard, content);
        const pLeftX = Math.round(pOff.x);
        const parentBottomY = Math.round(pOff.y + pOff.height);

        // gather immediate children (direct child wrappers)
        const childWrappers = Array.from(childrenWrap.children).filter(ch => ch && ch.getAttribute && ch.getAttribute('data-node-id'));
        if (!childWrappers.length) return;

        const childPoints = [];
        childWrappers.forEach(ch => {
            const chCard = ch.querySelector('.compact-node');
            if (!chCard) return;
            const cOff = offsetWithin(chCard, content);
            const cCenterX = Math.round(cOff.x + (cOff.width / 2));
            const cCenterY = Math.round(cOff.y + (cOff.height / 2));
            childPoints.push({ x: cCenterX, y: cCenterY });
        });
        if (!childPoints.length) return;

        // deepest child Y to decide trunk bottom
        const maxChildY = Math.max(...childPoints.map(c => c.y));

        // prefer trunk X to be to the left of the parent itself (so it sits behind the node)
        const leftMostChildX = Math.min(...childPoints.map(c => c.x));
        // shift trunks 10px right from previous placement so they sit closer to (but behind) the parent
        let trunkX = Math.round(pLeftX - 4);
        let tries = 0;
        while (usedXs.some(u => Math.abs(u - trunkX) < collisionGap) && tries < 10) {
            trunkX += (tries % 2 === 0) ? (collisionGap) : (-collisionGap); // nudge left/right
            tries++;
        }
        usedXs.push(trunkX);

        // draw a short horizontal from slightly left of the parent's bottom-left to the trunk,
        // but start a few pixels below the parent's bottom to avoid overlap/glitches
        const hFromX = pLeftX - 4; // small offset so the junction is behind the parent
        const junctionOffset = 8; // px down from parent's bottom
        const hY = parentBottomY + junctionOffset;
        const vPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        const topY = hY;
        const bottomY = maxChildY;
        vPath.setAttribute('d', `M ${hFromX} ${hY} L ${trunkX} ${hY} L ${trunkX} ${bottomY}`);
        vPath.setAttribute('stroke', strokeColor);
        vPath.setAttribute('stroke-width', '2');
        vPath.setAttribute('fill', 'none');
        vPath.setAttribute('stroke-linecap', 'round');
        svg.appendChild(vPath);

        // junction dot at parent bottom-left origin
        const dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        dot.setAttribute('cx', hFromX);
        dot.setAttribute('cy', hY);
        dot.setAttribute('r', '3');
        dot.setAttribute('fill', strokeColor);
        svg.appendChild(dot);

        // draw short horizontal branches to each child
        childPoints.forEach(cp => {
            // stop a few px before child center so line sits behind node
            const endX = cp.x - Math.sign(cp.x - trunkX) * 6;
            const branch = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            branch.setAttribute('d', `M ${trunkX} ${cp.y} L ${endX} ${cp.y}`);
            branch.setAttribute('stroke', strokeColor);
            branch.setAttribute('stroke-width', '2');
            branch.setAttribute('fill', 'none');
            branch.setAttribute('stroke-linecap', 'round');
            branch.setAttribute('marker-end', 'url(#compact-arrow)');
            svg.appendChild(branch);
        });
    });
}

const _redrawConnectorsThrottled = throttle(drawConnectors, 120);
window.addEventListener('resize', _redrawConnectorsThrottled);
document.addEventListener('scroll', _redrawConnectorsThrottled, true);

// ensure we draw connectors after each load
const _oldLoadCompact = window.loadCompact;
if (typeof _oldLoadCompact === 'function') {
    window.loadCompact = async function() {
        await _oldLoadCompact();
        // small delay to ensure DOM painted
        setTimeout(() => { try { drawConnectors(); } catch(e){console.warn('drawConnectors failed', e);} }, 40);
    };
}

document.addEventListener('DOMContentLoaded', loadCompact);
</script>

<!-- Commissions modal markup (used by compact view) -->
<div id="compactCommissionsModal" style="display:none; position:fixed; inset:0; align-items:center; justify-content:center; z-index:3000;">
    <div style="position:absolute; inset:0; background:rgba(20,24,31,0.92); backdrop-filter: blur(2px); transition:background 0.3s;"></div>
    <div style="position:relative; width:800px; max-width:95%; background:linear-gradient(180deg, rgba(17,24,39,0.9), rgba(6,8,15,0.8)); color:#e6eef8; border-radius:16px; box-shadow:0 8px 32px 0 rgba(31,38,135,0.37); padding:32px 24px; max-height:90vh; overflow-y:auto;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
            <div style="font-weight:700; font-size:18px;">Commission Summary</div>
            <button id="compactCommissionsClose" type="button" title="Close" style="background:transparent;border:none;color:inherit;cursor:pointer;font-size:22px;">✕</button>
        </div>
        <div id="compactCommissionsContent" style="margin-top:12px;">
            <div style="color:#9aa4b2;">Loading...</div>
        </div>
    </div>
</div>

<script>
// Commissions modal helpers for compact view
function showCompactCommissionsModal() {
    const m = document.getElementById('compactCommissionsModal');
    if (!m) return;
    m.style.display = 'flex';
}
function hideCompactCommissionsModal() {
    const m = document.getElementById('compactCommissionsModal');
    if (!m) return;
    m.style.display = 'none';
}
document.addEventListener('click', function(e){
    const close = document.getElementById('compactCommissionsClose');
    if (close && e.target === close) hideCompactCommissionsModal();
});
// Activate click outside (on backdrop) to close modal
document.getElementById('compactCommissionsModal')?.addEventListener('click', function(e){
    // Only close if clicking the backdrop, not the modal content
    if (e.target === this) hideCompactCommissionsModal();
    // Also close if clicking the backdrop div inside
    const backdrop = this.querySelector('div[style*="background:rgba(3,7,18,0.6)"]');
    if (backdrop && e.target === backdrop) hideCompactCommissionsModal();
});

async function openCommissionsModal(userId, depth = 9) {
    const content = document.getElementById('compactCommissionsContent');
    if (!content) return;
    content.innerHTML = '<div style="color:var(--text-secondary,#9aa4b2);">Loading...</div>';
    showCompactCommissionsModal();
    try {
        const res = await fetch('/api/user/commissions?userId=' + encodeURIComponent(userId) + '&depth=' + encodeURIComponent(depth) + '&format=json');
        if (!res.ok) throw new Error('Network error');
        const payload = await res.json();
        if (!payload || !payload.success) throw new Error(payload && payload.message ? payload.message : 'API error');
        renderCompactCommissions(payload.data || payload);
    } catch (err) {
        content.innerHTML = '<div style="color:#ffb4b4;">Failed to load commissions: ' + (err.message || err) + '</div>';
        console.error(err);
    }
}

function renderCompactCommissions(data) {
    const content = document.getElementById('compactCommissionsContent');
    if (!content) return;
    const rates = data.commissionRates || [0.05,0.04,0.03,0.02,0.01,0.005,0.0025,0.0025,0.00];
    // Use the selected depth from the option select
    const depthSelect = document.getElementById('treeDepth');
    let selectedDepth = 9;
    if (depthSelect) {
        selectedDepth = parseInt(depthSelect.value, 10) || 9;
    }
    const depth = Math.min(rates.length, selectedDepth);
    const rows = [];
    rows.push('<table style="width:100%; border-collapse:collapse; color:var(--text-primary);">');
    rows.push('<thead><tr style="text-align:left; color:var(--text-secondary);"><th style="padding:8px;">Level</th><th style="padding:8px;">Downline</th><th style="padding:8px;">Sum</th><th style="padding:8px;">Rate</th><th style="padding:8px;">Commission</th></tr></thead>');
    rows.push('<tbody>');
    for (let i=0;i<depth;i++){
        const level = i+1;
        const count = (data.perLevelCounts && data.perLevelCounts[i]) || 0;
        const sum = (data.perLevelSums && data.perLevelSums[i]) || 0;
        let rateDisplay = (data.commissionRates && typeof data.commissionRates[i] !== 'undefined')
            ? data.commissionRates[i]
            : '';
        let commission = (data.perLevelEarnings && data.perLevelEarnings[i]) || 0;
        // Level 9 always shows zero commission if rate is 0%
        if (level === 9 && (!rateDisplay || rateDisplay === '0%' || rateDisplay === '0')) {
            rows.push(`<tr><td style="padding:8px;">${level}</td><td style="padding:8px;">${count}</td><td style="padding:8px;">${data.currencySymbol||'P'}0</td><td style="padding:8px;">${rateDisplay}</td><td style="padding:8px;">${data.currencySymbol||'P'}0</td></tr>`);
            continue;
        }
        // For other levels, show commission if downline and sum are nonzero
        const displaySum = (count === 0 || sum === 0) ? 0 : sum;
        const displayCommission = (count === 0 || sum === 0) ? 0 : commission;
        const displayRate = (count === 0 || sum === 0) ? '' : rateDisplay;
        rows.push(`<tr><td style="padding:8px;">${level}</td><td style="padding:8px;">${count}</td><td style="padding:8px;">${data.currencySymbol||'P'}${Number(displaySum).toLocaleString()}</td><td style="padding:8px;">${displayRate}</td><td style="padding:8px;">${data.currencySymbol||'P'}${Number(displayCommission).toLocaleString()}</td></tr>`);
    }
    rows.push('</tbody></table>');
    let projected = 0; if (data.perLevelEarnings) for (let j=0;j<depth;j++) projected += (data.perLevelEarnings[j]||0);
    rows.push('<div style="margin-top:12px; font-weight:500; font-size:15px;">Computation at depth '+depth+': ' + (data.currencySymbol||'P') + Number(projected).toLocaleString() + '</div>');
    content.innerHTML = rows.join('');
}
</script>

<?php
// Remove Compact View-specific JS and CSS

// Replace Compact View canvas with Network View
include __DIR__ . '/../network-tree2.php';

include ROOT_PATH . '/src/Views/layout/footer.php';
?>