<?php
/** Circle View - Views/user/network-tree/circle-view.php
 * Standalone copy of the working circle renderer from `network-tree.php`.
 * This file intentionally does not include the compact view.
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
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white text-left">Network — Circle View</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Circle layout of the network</p>
            </div>
            <div>
                <a href="/user/network-tree" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg">Back</a>
            </div>
        </div>
    </div>

    <div class="py-6 px-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
            <div id="circleControls" class="mb-4 flex items-center justify-between">
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
                    </div>
                    <label class="text-sm text-gray-600 dark:text-gray-300">View:</label>
                    <div class="relative inline-block">
                        <select id="viewMode" class="appearance-none w-[220px] pr-10 px-3 py-1 border rounded bg-white text-gray-800 border-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:border-gray-600">
                            <option value="circle" data-url="/user/network-view/circle-view" selected>Circle View</option>
                            <option value="compact" data-url="/user/network-tree/compact-view">Compact View</option>
                            <option value="organizational">Organizational Chart</option>
                            <option value="network" data-url="/user/network-tree">Network View</option>
                            <option value="tree">Tree View</option>
                            <option value="hierarchical">Hierarchical View</option>
                        </select>
                    </div>
                </div>
                <div>
                    <button id="refreshCircle" class="bg-blue-600 text-white px-3 py-1 rounded">Refresh</button>
                </div>
            </div>

            <div class="circle-viewport" style="max-width:var(--tree-viewport-max-width,1200px); max-height:calc(100vh - 260px); overflow:auto; position:relative;">
                <div id="circleCanvasInner" class="circle-canvas-inner" style="position:relative;width:1000px;height:1000px;">
                    <div id="circleContainer" class="circle-container"></div>
                </div>
            </div>

            <div id="circleLoading" class="text-center py-6 text-gray-500">Loading circle network...</div>
            <div id="circleError" style="display:none;color:#d9534f;">Failed to load circle network</div>
            <pre id="circleDebug" style="display:none;white-space:pre-wrap;background:#0b1220;color:#e6eef8;padding:12px;border-radius:6px;margin:8px 0;max-height:260px;overflow:auto;font-size:12px;"></pre>
        </div>
    </div>
</div>

<style>
/* Circle layout specific styles (kept small and self-contained) */
.circle-viewport { max-width:var(--tree-viewport-max-width,1200px); max-height:calc(100vh - 260px); overflow:auto; position:relative; }
.circle-canvas-inner { position:relative; transform-origin: 0 0; }
.circle-container { position:relative; width:100%; height:100%; }
.circle-node { position:absolute; width:160px; min-width:120px; cursor:pointer; z-index:2; box-shadow:none; border-radius:50%; padding:0; display:flex; align-items:center; justify-content:center; }
.dark .circle-node { color:#e6eef8; }
.circle-node .avatar { width:36px; height:36px; border-radius:50%; background:#3b82f6; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; }
.circle-node .info { overflow:hidden; }
.circle-node .title { font-weight:700; font-size:14px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.circle-node .sub { font-size:11px; color:#6b7280; }
.circle-connector-layer { position:absolute; left:0; top:0; width:100%; height:100%; pointer-events:none; z-index:1; }
</style>

<script>
// Self-contained circle view renderer (copied + adapted from network-tree.php)
(function(){
    const loadingEl = () => document.getElementById('circleLoading');
    const errorEl = () => document.getElementById('circleError');
    const debugEl = () => document.getElementById('circleDebug');

    function showDebug(msg){
        try{ const d = debugEl(); if(!d) return; d.style.display=''; d.textContent = msg; }catch(e){}
    }

    async function loadCircle(){
        const depthEl = document.getElementById('treeDepth');
        const depth = depthEl ? Number(depthEl.value) : 3;
        try{ if(loadingEl()) loadingEl().style.display=''; }catch(e){}
        try{ if(errorEl()) errorEl().style.display='none'; }catch(e){}
        try{ if(debugEl()) { debugEl().style.display='none'; debugEl().textContent=''; } }catch(e){}

        try{
            const res = await fetch('/api/user/network-tree?depth=' + encodeURIComponent(depth), { credentials: 'same-origin' });
            const text = await res.text();
            let payload = null;
            try { payload = JSON.parse(text); } catch (jsonErr){ showDebug('Invalid JSON response:\n' + text); throw new Error('Invalid JSON response'); }

            if (!res.ok) { showDebug('HTTP error ' + res.status + '\n' + JSON.stringify(payload, null, 2)); throw new Error('HTTP ' + res.status); }
            if (!payload || !payload.success) { showDebug('API returned unexpected payload:\n' + JSON.stringify(payload, null, 2)); throw new Error('API returned no data'); }

            const tree = payload.data;
            if (!tree || (Array.isArray(tree) && tree.length===0) || (typeof tree==='object' && Object.keys(tree).length===0)) { showDebug('API returned empty data:\n' + JSON.stringify(payload, null, 2)); throw new Error('Empty network data'); }

            const container = document.getElementById('circleContainer');
            renderCircleView(container, tree);
            try{ if(loadingEl()) loadingEl().style.display='none'; }catch(e){}
        } catch (err){
            console.error('loadCircle error', err);
            try{ if(loadingEl()) loadingEl().style.display='none'; }catch(e){}
            try{ if(errorEl()) { errorEl().style.display=''; errorEl().textContent = 'Failed to load circle network: ' + (err && err.message ? err.message : err); } }catch(e){}
        }
    }

    function renderCircleView(container, data) {
        container.innerHTML = '';
        container.className = 'tree-container-circle';

        const circleContainer = document.createElement('div');
        circleContainer.className = 'circle-container';
        circleContainer.style.position = 'relative';
        circleContainer.style.width = '100%';
        circleContainer.style.height = '600px';
        // Use transparent background so the canvas blends with page background
        circleContainer.style.background = 'transparent';
        circleContainer.style.borderRadius = '12px';
        circleContainer.style.display = 'flex';
        circleContainer.style.alignItems = 'center';
        circleContainer.style.justifyContent = 'center';
        circleContainer.style.overflow = 'hidden';

        // Create inner positioning container
        const positionContainer = document.createElement('div');
        positionContainer.style.position = 'relative';
        positionContainer.style.width = '500px';
        positionContainer.style.height = '500px';

        // Detect dark mode (guard against missing global)
        const isDark = (typeof document !== 'undefined' && document.documentElement && document.documentElement.classList && document.documentElement.classList.contains('dark')) || false;

        const nodes = flattenTreeData(data, 0);
        const positions = calculateCirclePositions(nodes);

        // Compute bounding box for positions so we can size the inner canvas and avoid clipping
        let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        const tempSizes = [];
        for (let i = 0; i < nodes.length; i++) {
            const p = positions[i] || { x: 0, y: 0 };
            const s = nodes[i].level === 0 ? 120 : 90;
            tempSizes.push(s);
            minX = Math.min(minX, p.x);
            minY = Math.min(minY, p.y);
            maxX = Math.max(maxX, p.x + s);
            maxY = Math.max(maxY, p.y + s);
        }

        const pad = 80;
        const contentWidth = Math.max(600, Math.ceil(maxX - minX + pad * 2));
        const contentHeight = Math.max(600, Math.ceil(maxY - minY + pad * 2));

        // Apply sizes to outer wrappers so the canvas is large enough
        try {
            const outer = document.getElementById('circleCanvasInner');
            if (outer) {
                outer.style.width = contentWidth + 'px';
                outer.style.height = contentHeight + 'px';
            }
            if (circleContainer) {
                circleContainer.style.width = contentWidth + 'px';
                circleContainer.style.height = contentHeight + 'px';
                circleContainer.style.overflow = 'visible';
                circleContainer.style.borderRadius = '0';
            }
        } catch (e) {}

        positionContainer.style.width = contentWidth + 'px';
        positionContainer.style.height = contentHeight + 'px';

        const offsetX = pad - minX;
        const offsetY = pad - minY;

        // Create nodes
        nodes.forEach((item, index) => {
            const nodeDiv = document.createElement('div');
            nodeDiv.className = `circle-node level-${item.level}`;
            nodeDiv.style.left = (positions[index].x + offsetX) + 'px';
            nodeDiv.style.top = (positions[index].y + offsetY) + 'px';
            nodeDiv.onclick = () => showUserDetails(item.node);

            // Color based on level and theme
            const colorsLight = [
                'linear-gradient(135deg, #ec4899, #be185d)', // Pink for root (YOU)
                'linear-gradient(135deg, #06b6d4, #0891b2)', // Cyan for level 1
                'linear-gradient(135deg, #10b981, #059669)', // Green for level 2
                'linear-gradient(135deg, #f59e0b, #d97706)', // Orange for level 3
                'linear-gradient(135deg, #8b5cf6, #7c3aed)'  // Purple for level 4+
            ];
            const colorsDark = [
                'linear-gradient(135deg, #f472b6, #db2777)', // Pink for root (YOU)
                'linear-gradient(135deg, #67e8f9, #0891b2)', // Cyan for level 1
                'linear-gradient(135deg, #6ee7b7, #059669)', // Green for level 2
                'linear-gradient(135deg, #fde68a, #d97706)', // Orange for level 3
                'linear-gradient(135deg, #c4b5fd, #7c3aed)'  // Purple for level 4+
            ];
            nodeDiv.style.background = isDark
                ? colorsDark[item.level % colorsDark.length]
                : colorsLight[item.level % colorsLight.length];

            // Size based on level (root user is larger)
            const size = item.level === 0 ? 120 : 90;
            nodeDiv.style.width = size + 'px';
            nodeDiv.style.height = size + 'px';
            nodeDiv.style.borderRadius = '50%';
            nodeDiv.style.position = 'absolute';
            nodeDiv.style.display = 'flex';
            nodeDiv.style.alignItems = 'center';
            nodeDiv.style.justifyContent = 'center';
            nodeDiv.style.color = 'white';
            nodeDiv.style.fontSize = item.level === 0 ? '14px' : '11px';
            nodeDiv.style.fontWeight = 'bold';
            nodeDiv.style.cursor = 'pointer';
            nodeDiv.style.transition = 'all 0.3s ease';
            nodeDiv.style.boxShadow = '0 4px 15px rgba(0, 0, 0, 0.2)';
            nodeDiv.style.border = '3px solid rgba(255, 255, 255, 0.2)';
            nodeDiv.style.zIndex = item.level === 0 ? '5' : '2';

            nodeDiv.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.15)';
                this.style.zIndex = '10';
                this.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.3)';
            });

            nodeDiv.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
                this.style.zIndex = item.level === 0 ? '5' : '2';
                this.style.boxShadow = '0 4px 15px rgba(0, 0, 0, 0.2)';
            });

            nodeDiv.innerHTML = `
            <div class="text-center leading-tight">
                <div class="font-bold">${(item.node.fullname || item.node.username).substring(0, item.level === 0 ? 12 : 9)}</div>
                <div class="text-xs opacity-90 ${item.level === 0 ? 'mt-1' : ''}">L${item.level}</div>
                ${item.level === 0 ? `<div class="text-xs font-semibold text-blue-700 dark:text-white">YOU</div>` : ''}
            </div>
        `;

            positionContainer.appendChild(nodeDiv);
        });

        // Restore original subtle connecting lines (root -> immediate children)
        if (nodes.length > 1) {
            // Root position from positions array (apply offsets)
            const rootPosition = { x: (positions[0].x || 0) + offsetX, y: (positions[0].y || 0) + offsetY };
            const rootSize = 120;

            // Only connect direct children (level 1) to avoid overlapping
            nodes.filter(item => item.level === 1).forEach((item) => {
                const childIndex = nodes.findIndex(n => n.node.id === item.node.id);
                if (childIndex === -1) return;
                const childPositionRaw = positions[childIndex] || { x: 0, y: 0 };
                const childPosition = { x: childPositionRaw.x + offsetX, y: childPositionRaw.y + offsetY };
                const childSize = nodes[childIndex].level === 0 ? 120 : 90;

                const dx = (childPosition.x + childSize/2) - (rootPosition.x + rootSize/2);
                const dy = (childPosition.y + childSize/2) - (rootPosition.y + rootSize/2);
                const length = Math.max(0, Math.sqrt(dx * dx + dy * dy) - (rootSize/2 + childSize/2));
                const angle = Math.atan2(dy, dx) * 180 / Math.PI;

                const startX = rootPosition.x + rootSize/2 + Math.cos(angle * Math.PI / 180) * rootSize/2;
                const startY = rootPosition.y + rootSize/2 + Math.sin(angle * Math.PI / 180) * rootSize/2;

                const line = document.createElement('div');
                line.className = 'circle-connection';
                line.style.position = 'absolute';
                line.style.left = startX + 'px';
                line.style.top = startY + 'px';
                line.style.width = length + 'px';
                line.style.height = '2px';
                line.style.transformOrigin = '0 50%';
                line.style.transform = `rotate(${angle}deg)`;
                line.style.zIndex = '1';
                line.style.borderRadius = '1px';
                line.style.background = isDark
                    ? 'linear-gradient(90deg, rgba(59,130,246,0.5), rgba(30,41,59,0.2))'
                    : 'linear-gradient(90deg, rgba(59,130,246,0.4), rgba(203,213,225,0.1))';
                positionContainer.appendChild(line);
            });
        }

        circleContainer.appendChild(positionContainer);
        container.appendChild(circleContainer);
    }

    function calculateCirclePositions(nodes) {
    const positions = [];
    const centerX = 250; // Center of 500px container
    const centerY = 250;

    // Group nodes by level
    const nodesByLevel = {};
    nodes.forEach(item => {
        if (!nodesByLevel[item.level]) {
            nodesByLevel[item.level] = [];
        }
        nodesByLevel[item.level].push(item);
    });

    nodes.forEach((item, index) => {
        if (item.level === 0) {
            // Center the root node
            positions.push({ x: centerX - 60, y: centerY - 60 });
        } else {
            // Calculate position for this level
            const levelNodes = nodesByLevel[item.level];
            const nodeIndexInLevel = levelNodes.findIndex(n => n.node.id === item.node.id);
            const totalInLevel = levelNodes.length;

            // Calculate angle for even distribution
            const angleStep = (2 * Math.PI) / totalInLevel;
            const angle = nodeIndexInLevel * angleStep - Math.PI / 2; // Start from top

            // Radius increases with level
            const radius = item.level * 110 + 60; // Reduced radius for better fit

            const x = centerX - 45 + Math.cos(angle) * radius;
            const y = centerY - 45 + Math.sin(angle) * radius;

            positions.push({ x, y });
        }
    });

    return positions;
}

    function flattenTreeData(node, level, result = []) {
    result.push({ node, level });

    if (node.children) {
        node.children.forEach(child => {
            flattenTreeData(child, level + 1, result);
        });
    }

    return result;
}

    // Minimal tooltip implementation
    let _circleTooltip = null;
    function ensureCircleTooltip(){ if(!_circleTooltip){ _circleTooltip = document.createElement('div'); _circleTooltip.className='compact-tooltip'; _circleTooltip.style.display='none'; document.body.appendChild(_circleTooltip);} }
    function showCircleTooltip(node, event){ ensureCircleTooltip(); const fullname=node.dataset.fullname||''; const username=node.dataset.username||''; const level=node.dataset.level||''; const commissions=node.dataset.commissions||'0'; _circleTooltip.innerHTML = `<div class="t-title">${fullname} ${username? '(@'+username+')':''}</div><div class="t-sub">Level: ${level}</div><div style="margin-top:6px; font-weight:700;">$${commissions}</div>`; _circleTooltip.style.display='block'; moveCircleTooltip(event); }
    function moveCircleTooltip(event){ if(!_circleTooltip) return; const pad=12; const tooltipRect=_circleTooltip.getBoundingClientRect(); let left=event.clientX+16; let top=event.clientY-tooltipRect.height-10; if(left+tooltipRect.width+pad>window.innerWidth) left=window.innerWidth-tooltipRect.width-pad; if(top<pad) top=event.clientY+16; _circleTooltip.style.left=left+'px'; _circleTooltip.style.top=top+'px'; }
    function hideCircleTooltip(){ if(_circleTooltip) _circleTooltip.style.display='none'; }

    document.addEventListener('DOMContentLoaded', function(){
        const refreshBtn = document.getElementById('refreshCircle'); if(refreshBtn) refreshBtn.addEventListener('click', loadCircle);
        const depthEl = document.getElementById('treeDepth'); if(depthEl) depthEl.addEventListener('change', loadCircle);
        loadCircle();
    });
})();
</script>
<script>
// Ensure all `#viewMode` selects on the page show Circle View by default.
document.addEventListener('DOMContentLoaded', function(){
    try {
        setTimeout(function(){
            try {
                const sels = document.querySelectorAll('[id="viewMode"]');
                if (sels && sels.length) {
                    sels.forEach(function(sel){
                        try {
                            sel.value = 'circle';
                            Array.from(sel.options || []).forEach(function(opt){ opt.selected = (opt.value === 'circle'); });
                        } catch(e) { }
                    });
                }
            } catch(e) {}
        }, 40);
    } catch(e) {}
});
</script>
<script>
// Mirror compact view option behavior in Circle View
document.addEventListener('DOMContentLoaded', function(){
    const depthEl = document.getElementById('treeDepth');
    const viewEl = document.getElementById('viewMode');
    const refreshBtn1 = document.getElementById('refreshCompact');
    const refreshBtn2 = document.getElementById('refreshCircle');

    function doRefresh() {
        try { document.getElementById('compactLoading').style.display = ''; } catch(e){}
        try { const content = document.getElementById('compactContent'); if(content) content.style.display = 'none'; } catch(e){}
        try { if (typeof loadCircle === 'function') loadCircle(); } catch(e){}
    }

    if (depthEl) depthEl.addEventListener('change', function(){ doRefresh(); });

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
        if (val === 'compact') {
            try { window.location.href = '/user/network-tree/compact-view'; } catch(e){}
            return;
        }
        // other view modes are ignored here for Circle View
    });

    // Ensure Circle View is selected by default on this page.
    // Use a short timeout so this runs after any global scripts that may set the select value.
    try {
        setTimeout(function(){
            try {
                const sel = document.getElementById('viewMode');
                if (sel) sel.value = 'circle';
            } catch (e) {}
        }, 30);
    } catch (e) {}

    if (refreshBtn1) refreshBtn1.addEventListener('click', doRefresh);
    if (refreshBtn2) refreshBtn2.addEventListener('click', doRefresh);
});
</script>
<?php
/** Circle View - Views/users/network-tree/circle-view.php
 * Reuses the compact view template as a base and adapts it for Circle View.
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
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white text-left">Network — Circle View</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Circle layout of the network based on the compact template</p>
            </div>
            <div>
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
                            <option value="circle" data-url="/user/network-view/circle-view" selected>Circle View</option>
                            <option value="compact" data-url="/user/network-tree/compact-view">Compact View</option>
                            <option value="organizational">Network View</option>
                            <option value="network">Network Grid</option>
                            <option value="tree">Tree View</option>
                            <option value="hierarchical">Hierarchical View</option>
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

                <div id="compactLoading" class="text-center py-6 text-gray-500">Loading circle network...</div>
                <div class="compact-viewport" style="max-width:var(--tree-viewport-max-width,1200px); max-height:calc(100vh - 260px); overflow:auto; position:relative;">
                    <div id="compactCanvasInner" class="compact-canvas-inner" style="position:relative;">
                        <div id="compactContent" style="display:none;"></div>
                    </div>
                </div>
                <div id="compactError" style="display:none;color:#d9534f;">Failed to load circle network</div>
            </div>
        </div>
    </div>
</div>

<!-- Reuse same CSS/JS from compact view by including compact view helpers where necessary -->
<!-- compact-view include removed; this file is now standalone -->

<!-- Floating canvas resize slider (fixed, bottom-right) - Circle View specific -->
<div id="floatingResizeControls" class="floating-resize" aria-hidden="false" style="right:50px;bottom:30px;">
    <div class="floating-resize-inner" tabindex="0">
        <button id="decreaseCanvas" class="fr-btn" title="Decrease scale">−</button>
        <input id="canvasSlider" type="range" min="50" max="200" step="5" value="100" aria-label="Canvas scale">
        <button id="floatingAutoFit" class="fr-btn" title="Auto-Fit">⤢</button>
        <button id="increaseCanvas" class="fr-btn" title="Increase scale">+</button>
        <span id="canvasWidthDisplay" class="fr-display">100%</span>
        <button id="resetCanvas" class="fr-btn" title="Reset scale">⟳</button>
        <label class="fr-infinite" style="display:inline-flex;align-items:center;gap:4px;margin-left:6px;color:inherit;">
            <input id="toggleInfiniteCanvas" type="checkbox" style="width:14px;height:14px;" title="Enable infinite canvas"> <span style="font-size:12px;opacity:0.9">∞</span>
        </label>
    </div>
</div>

<style>
/* Floating resize control styling (reused from network-tree) */
.floating-resize { position: fixed; right: 50px; bottom: 20px; z-index: 1100; opacity: 0.5; transition: opacity .25s ease, transform .15s ease; background: rgba(255,255,255,0.03); backdrop-filter: blur(6px); padding: 6px 8px; border-radius: 999px; display:flex; align-items:center; gap:8px; box-shadow: 0 6px 18px rgba(2,6,23,0.45); }
.dark .floating-resize { background: rgba(0,0,0,0.28); }
.floating-resize:hover, .floating-resize:focus-within { opacity: 1; }
.floating-resize input[type=range] { width: 160px; }
.fr-btn { background: transparent; border: none; color: inherit; font-size: 14px; padding:4px; cursor:pointer; }
.fr-display { font-weight:700; font-size:13px; padding:0 6px; white-space:nowrap; }
.floating-resize-inner { display:flex; align-items:center; gap:8px; }
</style>

<script>
// Floating controls adapted for Circle View
(function(){
    function findViewportAndInner() {
        const vp = document.querySelector('.circle-viewport');
        if (!vp) return {};
        // Prefer the explicit inner wrapper if present
        let inner = vp.querySelector('#circleCanvasInner') || vp.querySelector('#circleContainer') || vp.querySelector('.circle-canvas-inner');
        if (!inner) {
            inner = Array.from(vp.children).find(c => (c.scrollWidth && c.scrollWidth > 200) || c.offsetWidth > 0) || vp.firstElementChild;
        }
        return { vp, inner };
    }

    function applyScaleToInner(inner, percent) {
        if (!inner) return;
        const s = Math.max(0.5, Math.min(2, percent / 100));
        inner.style.transformOrigin = '0 0';
        inner.style.transform = `scale(${s})`;
        try { inner.dataset.currentScale = String(s); } catch (e) {}
        const disp = document.getElementById('canvasWidthDisplay');
        if (disp) disp.textContent = `${Math.round(percent)}%`;
        const slider = document.getElementById('canvasSlider');
        if (slider && Number(slider.value) !== Math.round(percent)) slider.value = Math.round(percent);
    }

    function ensureSpacer(vp, widthPx) {
        if (!vp) return null;
        let sp = vp.querySelector('.canvas-scale-spacer');
        if (!sp) {
            sp = document.createElement('div');
            sp.className = 'canvas-scale-spacer';
            sp.style.height = '1px';
            sp.style.pointerEvents = 'none';
            sp.style.display = 'block';
            vp.appendChild(sp);
        }
        sp.style.width = (Math.round(widthPx) || 0) + 'px';
        return sp;
    }

    function treeAutoFit(opts = {}) {
        const allowOverflow = !!opts.allowOverflow;
        const { vp, inner } = findViewportAndInner();
        if (!vp || !inner) return;

        // measure content unscaled width
        const prevTransform = inner.style.transform || '';
        inner.style.transform = '';
        const contentWidth = Math.max(inner.scrollWidth || inner.getBoundingClientRect().width, 1);
        const vpClient = vp.clientWidth || vp.getBoundingClientRect().width || 800;
        let percent = Math.min(100, (vpClient / contentWidth) * 100);
        percent = Math.max(50, Math.round(percent));

        applyScaleToInner(inner, percent);
        const scaledWidth = contentWidth * (percent / 100);

        const spacerWidth = allowOverflow ? Math.ceil(scaledWidth) : Math.max(Math.ceil(vpClient), Math.ceil(scaledWidth));
        const spacer = ensureSpacer(vp, spacerWidth);

        document.querySelectorAll('.circle-viewport').forEach(v => v.classList.add('autofit-active'));

        if (spacer) {
            const mo = new MutationObserver((mutations, obs) => {
                try { vp.scrollLeft = 0; } catch (e) {}
                obs.disconnect();
            });
            mo.observe(spacer, { attributes: true, attributeFilter: ['style'] });
            setTimeout(() => { try { vp.scrollLeft = 0; } catch (e) {} }, 300);
        }

        try { localStorage.setItem('ginto_tree_canvas_scale', String(percent)); } catch (e) {}
    }

    window.runAutoFitForCircle = function() { const inf = document.getElementById('toggleInfiniteCanvas'); treeAutoFit({ allowOverflow: !!(inf && inf.checked) }); };

    document.addEventListener('DOMContentLoaded', function() {
        const floatBtn = document.getElementById('floatingAutoFit');
        if (floatBtn) floatBtn.addEventListener('click', function() { window.runAutoFitForCircle(); });

        const slider = document.getElementById('canvasSlider');
        if (slider) {
            slider.addEventListener('input', function() {
                const p = Number(this.value) || 100;
                const { inner } = findViewportAndInner();
                applyScaleToInner(inner, p);
                document.querySelectorAll('.circle-viewport').forEach(v => v.classList.remove('autofit-active'));
                document.querySelectorAll('.circle-viewport .canvas-scale-spacer').forEach(s => s.remove());
                try { localStorage.setItem('ginto_tree_canvas_scale', String(p)); } catch (e) {}
            });
        }

        const inc = document.getElementById('increaseCanvas');
        const dec = document.getElementById('decreaseCanvas');
        const reset = document.getElementById('resetCanvas');
        if (inc) inc.addEventListener('click', function(){ const s=document.getElementById('canvasSlider'); if(s){ s.value = Math.min(Number(s.max), Number(s.value)+5); s.dispatchEvent(new Event('input')); } });
        if (dec) dec.addEventListener('click', function(){ const s=document.getElementById('canvasSlider'); if(s){ s.value = Math.max(Number(s.min), Number(s.value)-5); s.dispatchEvent(new Event('input')); } });
        if (reset) reset.addEventListener('click', function(){ const s=document.getElementById('canvasSlider'); if(s){ s.value = 100; s.dispatchEvent(new Event('input')); } });

        // Restore saved scale if present
        try {
            const saved = Number(localStorage.getItem('ginto_tree_canvas_scale')) || 100;
            const sEl = document.getElementById('canvasSlider');
            if (sEl) { sEl.value = saved; sEl.dispatchEvent(new Event('input')); }
        } catch (e) {}
    });
})();
</script>
