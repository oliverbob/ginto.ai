<?php
/** @var string $title */
/** @var array $user_data */
/** @var int $current_user_id */
/** @var array $stats */
include __DIR__ . '/../layout/header.php';
?>

<?php include __DIR__ . '/../layout/sidebar.php'; ?>


<style id="network-tree-theme-vars">
:root{
    --connector-color: #cbd5e0;
    --connector-color-dark: #6b7280;
    --primary-500: #3b82f6;
    --card-bg: #ffffff;
    --card-text: #000000; /* Darker text for light mode */
    
    --card-border: #e5e7eb; /* Borderpr color */
    --compact-bg: #ffffff;
    --tree-viewport-max-width: 1200px; /* Constrain visible tree viewport width */
    --tooltip-bg-start: rgba(35,57,93,0.98);
    --tooltip-bg-end: rgba(20,30,40,0.98);
}

/* Ensure dark theme variables apply when the `dark` class is set on the root element */
:root[class~="dark"]{
    --connector-color: var(--connector-color-dark);
    --card-bg: #1f2937;
    --card-text: #f9fafb; /* Light text for dark mode */
    --card-border: rgba(255,255,255,0.06);
}

/* Organizational Chart card text always dark in light mode */
html:not(.dark) .org-node .card {
    color: #111827 !important;
    background: #fff !important;
    border-color: #e2e8f0 !important;
}

/* Remove default blue focus outlines for selects in this view (use sparingly) */
#mainContent select:focus,
#mainContent .selected-value:focus,
#mainContent .custom-select .selected-value:focus,
#mainContent .no-focus-ring:focus {
    outline: none !important;
    box-shadow: none !important;
}
</style>

<div id="mainContent" class="min-h-screen bg-gray-50 dark:bg-gray-900 transition-all duration-300 ease-in-out">
    <!-- Header -->
    <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700" style="height:72px; display:flex; align-items:center; padding-left:80px; padding-right:20px;">
        <div class="flex items-center py-6 justify-between w-full">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white text-left">My Network Tree</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Visualize your referral network and team structure
                </p>
            </div>
            <div class="flex space-x-3 ml-auto">
                <!-- Theme Toggle -->
                <button id="themeToggle" onclick="toggleTheme()" class="theme-toggle bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2" title="Toggle Dark/Light Mode">
                    <i id="themeIcon" class="fas fa-moon"></i>
                    <span id="themeText" class="theme-toggle-text">Light</span>
                </button>
                <button id="showCommissionsBtn" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg" title="Show Commissions">Show Commissions</button>
                <button onclick="expandAll()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    Expand All
                </button>
                <button onclick="collapseAll()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">
                    Collapse All
                </button>
                <!-- Back to Dashboard -->
                <a href="/dashboard" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-arrow-left"></i>
                    <span>Dashboard</span>
                </a>
            </div>
        </div>
    </div>

    <div class="py-5 px-5">

        <!-- Enhanced Controls -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6 mb-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Search Section -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        <i class="fas fa-search mr-2"></i>Find Member in Network
                    </label>
                    <div class="relative">
                        <input type="text" id="userSearch" placeholder="Search by username, email, or name..."
                               class="w-full pl-4 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                    </div>
                    <div id="searchResults" class="absolute z-10 w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg mt-1 hidden">
                    </div>
                </div>

                <!-- Tree Configuration -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        <i class="fas fa-cogs mr-2"></i>Tree Configuration
                    </label>
                    <div class="space-y-2">
                        <div class="flex gap-2">
                            <select id="treeDepth" onchange="onDepthChange()" 
                                class="flex-1 px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white no-focus-ring">
                                <option value="1">1 Level</option>
                                <option value="2">2 Levels</option>
                                <option value="3">3 Levels</option>
                                <option value="4">4 Levels</option>
                                <option value="5">5 Levels</option>
                                <option value="6">6 Levels</option>
                                <option value="7">7 Levels</option>
                                <option value="8">8 Levels</option>
                                <option value="9">9 Levels</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- View Mode Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        <i class="fas fa-eye mr-2"></i>View Mode
                    </label>
                    <div class="space-y-2">
                        <select id="viewMode" onchange="changeViewMode()" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white no-focus-ring">
                            <option value="organizational" selected>Network View</option>
                            <option value="compact">Compact View</option>
                            <option value="organizational">Organizational Chart</option>
                            <option value="tree">Tree View</option>
                            <option value="hierarchical">Hierarchical View</option>
                            <option value="circle">Circle View</option>
                        </select>
                        <div class="flex gap-2">
                            <button onclick="exportTree()" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">
                                <i class="fas fa-download mr-1"></i>Export
                            </button>
                            <button onclick="showStatistics()" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white px-3 py-1 rounded text-sm">
                                <i class="fas fa-chart-bar mr-1"></i>Stats
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                <div class="flex flex-wrap gap-2">
                    <button onclick="expandAll()" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                        <i class="fas fa-expand-arrows-alt mr-1"></i>Expand All
                    </button>
                    <button onclick="collapseAll()" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm">
                        <i class="fas fa-compress-arrows-alt mr-1"></i>Collapse All
                    </button>
                    <!-- Top Auto-Fit removed: use floating Auto-Fit control instead -->
                    <button onclick="exportTree()" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">
                        <i class="fas fa-download mr-1"></i>Export
                    </button>
                </div>

        <!-- Network Tree Visualization -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Network Tree Structure</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Interactive network tree showing your downline relationships
                </p>
            </div>

            <div class="p-6">
                <div id="loadingSpinner" class="flex justify-center items-center py-12">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                    <span class="ml-3 text-gray-400">Loading network tree...</span>
                </div>

                <div class="tree-viewport" aria-label="Tree viewport">
                    <div id="treeContainer" class="hidden tree-container-hierarchical">
                        <!-- Tree will be rendered here -->
                    </div>
                </div>

                <div id="errorMessage" class="hidden text-center py-12">
                    <div class="text-red-500 dark:text-red-400">
                        <svg class="w-12 h-12 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-lg font-semibold">Failed to load network tree</p>
                        <p class="text-sm text-gray-400 mt-1">Please try again or contact support</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- legacy static user modal removed (dynamic modal is created/updated by JS) -->

<!-- Floating canvas resize slider (fixed, bottom-right) -->
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
/* Floating resize control styling */
.floating-resize { position: fixed; right: 50px; bottom: 20px; z-index: 1100; opacity: 0.5; transition: opacity .25s ease, transform .15s ease; background: rgba(255,255,255,0.03); backdrop-filter: blur(6px); padding: 6px 8px; border-radius: 999px; display:flex; align-items:center; gap:8px; box-shadow: 0 6px 18px rgba(2,6,23,0.45); }
.dark .floating-resize { background: rgba(0,0,0,0.28); }
.floating-resize:hover, .floating-resize:focus-within { opacity: 1; }
.floating-resize input[type=range] { width: 160px; }
.fr-btn { background: transparent; border: none; color: inherit; font-size: 14px; padding:4px; cursor:pointer; }
.fr-display { font-weight:700; font-size:13px; padding:0 6px; white-space:nowrap; }
.floating-resize-inner { display:flex; align-items:center; gap:8px; }
</style>

<script>
/* Auto-Fit integration for floating slider
   - Moves Auto-Fit behavior into the floating controls
   - Computes scale (percent) to fit tree content into the `.tree-viewport`
   - Creates/updates a `.canvas-scale-spacer` to let the viewport control scrollWidth
   - When Auto-Fit is active, inner `.tree-scroll-wrap` scrollbars are hidden (CSS class applied)
   - Uses a MutationObserver on the spacer to deterministically scroll after sizing
*/
(function(){
        function findViewportAndInner() {
        const vp = document.querySelector('.tree-viewport');
        if (!vp) return {};
        let inner = vp.querySelector('.tree-scroll-inner') || vp.querySelector('.hierarchical-org-chart') || vp.querySelector('#treeContainer');
        if (!inner) {
            inner = Array.from(vp.children).find(c => c.scrollWidth && c.scrollWidth > 200) || vp.firstElementChild;
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

        document.querySelectorAll('.tree-viewport').forEach(v => v.classList.add('autofit-active'));

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

    window.runAutoFit = function() { const inf = document.getElementById('toggleInfiniteCanvas'); treeAutoFit({ allowOverflow: !!(inf && inf.checked) }); };

    document.addEventListener('DOMContentLoaded', function() {
        const floatBtn = document.getElementById('floatingAutoFit');
        if (floatBtn) floatBtn.addEventListener('click', function() { window.runAutoFit(); });
        const topBtn = document.getElementById('autoFitCanvas');
        if (topBtn) topBtn.addEventListener('click', function() { window.runAutoFit(); });

        const slider = document.getElementById('canvasSlider');
        if (slider) {
            slider.addEventListener('input', function() {
                const p = Number(this.value) || 100;
                const { inner } = findViewportAndInner();
                applyScaleToInner(inner, p);
                document.querySelectorAll('.tree-viewport').forEach(v => v.classList.remove('autofit-active'));
                document.querySelectorAll('.tree-viewport .canvas-scale-spacer').forEach(s => s.remove());
                try { localStorage.setItem('ginto_tree_canvas_scale', String(p)); } catch (e) {}
            });
        }
    });
})();
</script>

<style>
/* If you intended these styles */
.modal-bg-fix {
    position: absolute;
    background-color: #cbd5e0;
    z-index: -1;
}

/* Dark theme adjustments */
.dark .tree-children::before {
    background-color: #4a5568;
}

.dark .connection-line {
    background-color: #4a5568;
}

/* Hierarchical Tree Connection Styles */
.hierarchical-tree-wrapper {
    position: relative;
    overflow: auto;
    min-height: 400px;
}

.hierarchical-tree-node {
    position: relative;
    margin-bottom: 20px;
}

.hierarchical-user-card {
    position: relative;
    z-index: 10;
    max-width: 250px;
}

.hierarchical-children {
    position: relative;
}

.hierarchical-connection {
    position: absolute;
    z-index: 1;
}

.hierarchical-connection.horizontal,
.hierarchical-connection.child-horizontal {
    background-color: #cbd5e0;
    height: 2px;
}

.hierarchical-connection.vertical {
    background-color: #cbd5e0;
    width: 2px;
}

.dark .hierarchical-connection {
    background-color: #6b7280;
}

/* Organizational Chart Specific Styles */
.org-node-container {
    transition: all 0.3s ease;
}

.org-node {
    width: 160px;
    text-align: center;
    margin: 0 10px 30px 10px;
}

.org-node .card {
    background: #fff;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 10px;
    position: relative;
}

.dark .org-node .card {
    background: #2d3748;
    border-color: #4a5568;
}

/* Compact view has been moved to its own template (`network-tree/compact-view.php`).
   Inline compact CSS/HTML/JS removed from this file to avoid duplication. */

/* Scroll wrapper used to confine horizontal scrolling to the tree canvas */
/* Ensure the outer tree container allows horizontal scrolling so canvas can be scrolled */
#treeContainer { overflow-x: auto; overflow-y: visible; position: relative; -webkit-overflow-scrolling: touch; }

/* Constrained viewport that limits visible width while allowing horizontal scrolling.
   Change --tree-viewport-max-width to increase/decrease visible area. */
.tree-viewport {
    overflow-x: auto;
    max-width: var(--tree-viewport-max-width);
    box-sizing: border-box;
    /* keep left-anchored — do not center by default */
}

/* When Auto-Fit is active we prefer the outer viewport to handle horizontal
   scrolling. Add a modifier class to switch off inner scrollbars so the
   visible area is constrained and the outer `.tree-viewport` becomes the
   primary scroller. */
.tree-viewport.autofit-active { overflow-x: auto; }
.tree-viewport.autofit-active .tree-scroll-wrap { overflow-x: visible !important; }
.tree-viewport.autofit-active .tree-scroll-wrap::-webkit-scrollbar { display: none !important; }
.tree-viewport.autofit-active .tree-scroll-wrap { -ms-overflow-style: none; scrollbar-width: none; }

.tree-container-hierarchical { overflow-x: auto; overflow-y: visible; position: relative; }

.tree-scroll-wrap {
    /* Inner wrapper does not show its own scrollbars — viewport handles scrolling */
    overflow: visible; /* inner content won't scroll; outer viewport scrolls */
    overflow-x: visible !important;
    overflow-y: visible !important;
    max-height: 70vh;
    max-width: 100%;
    box-sizing: border-box;
    position: relative;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain; /* prevent scroll chaining to body */
    text-align: left; /* ensure inner block anchors to left */
}

/* Hide any remaining inner scrollbars to ensure the viewport is used */
.tree-scroll-wrap::-webkit-scrollbar { display: none !important; }
.tree-scroll-wrap { -ms-overflow-style: none; scrollbar-width: none; }

/* inner should allow horizontal overflow and not cause page wrapping */
.tree-scroll-inner {
    min-width: 1200px;
    display: block; /* ensure left-anchored and predictable scroll behavior */
    white-space: nowrap;
    vertical-align: top;
    margin: 0 !important; /* override centering from ancestor wrappers */
    padding-left: 40px; /* keep content away from left edge */
}

.tree-scroll-wrap { cursor: grab; }
.tree-scroll-wrap.dragging { cursor: grabbing; user-select: none; }

/* Network View Styles */ (Level-based grid) */
        /* Network Grid styles removed (feature deprecated).
           If a future replacement is added, keep styles in a separate file. */

        /* Tree View Styles (Hierarchical Tree) */
        .tree-container-hierarchical {
            overflow: auto;
            max-height: 80vh;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        
        .dark .tree-container-hierarchical {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-color: #334155;
        }
        
        .tree-view-wrapper {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 600px;
        }
        
        .tree-node-wrapper {
            position: relative;
        }
        
        .tree-user-node {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .tree-user-node:hover {
            transform: translateY(-3px) scale(1.02);
        }
        
        .tree-children-wrapper {
            position: relative;
        }
        
        .connection-line-up {
            filter: drop-shadow(0 0 2px rgba(0,0,0,0.1));
        }
        
        .hierarchical-tree-container {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 600px;
            overflow: auto;
        }
        
        .tree-node-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            margin: 25px 0;
        }
        
        .tree-node-item {
            position: relative;
            z-index: 10;
            margin: 0 10px;
        }
        
        .tree-children-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 50px;
        }
        
        .tree-children-wrapper {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            position: relative;
        }
        
        .tree-connection {
            position: absolute;
            background-color: #cbd5e0;
            z-index: 1;
        }
        
        .tree-connection.upline-connection {
            background-color: #3b82f6;
            box-shadow: 0 0 4px rgba(59, 130, 246, 0.3);
        }
        
        .dark .tree-connection {
            background-color: #6b7280;
        }
        
        .dark .tree-connection.upline-connection {
            background-color: #60a5fa;
            box-shadow: 0 0 4px rgba(96, 165, 250, 0.3);
        }
        
        .vertical-line {
            width: 2px;
        }
        
        .horizontal-line {
            height: 2px;
        }
        
        .child-line {
            width: 2px;
        }
        
        /* Responsive adjustments for tree */
        @media (max-width: 768px) {
            .tree-children-wrapper {
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
            
            .tree-node-container {
                margin: 15px 0;
            }
            
            .tree-children-container {
                margin-top: 30px;
            }
        }/* Circle View Styles (Original network view) */
.circle-container {
    position: relative;
    overflow: hidden;
    min-height: 600px;
}

.circle-node {
    position: absolute;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.circle-node:hover {
    transform: scale(1.1);
    z-index: 10;
}

.circle-connection {
    position: absolute;
    height: 2px;
    background-color: #cbd5e0;
    z-index: 0;
}

.dark .circle-connection {
    background-color: #4a5568;
}

/* Scrollbar styling for dark theme */
.dark::-webkit-scrollbar {
    width: 8px;
}

.dark::-webkit-scrollbar-track {
    background: #2d3748;
}

.dark::-webkit-scrollbar-thumb {
    background: #4a5568;
    border-radius: 4px;
}

.dark::-webkit-scrollbar-thumb:hover {
    background: #718096;
}

/* Light theme scrollbar */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f5f9;
}

::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Ensured text visibility in light mode by explicitly applying the `--card-text` variable */
.network-tree-node {
    color: var(--card-text); /* Apply text color variable */
}


/* Force dark text in light mode for organizational chart cards */
@media (prefers-color-scheme: light) {
    .org-admin-style-chart .card-with-tooltip div[style*="font-size"] {
        color: #111827 !important;
    }
}

/* Animations for earnings table rows */
.level-row {
    transition: opacity .22s ease, transform .22s ease;
    opacity: 1;
    transform: translateY(0);
}
.level-row.hidden-level {
    opacity: 0;
    transform: translateY(-6px);
    pointer-events: none;
}

/* Commission modal rows animation */
.commission-rows > div {
    transition: opacity .22s ease, transform .22s ease;
    opacity: 1;
    transform: translateY(0);
}
.commission-rows.hidden {
    opacity: 0;
    transform: translateY(-6px);
}
</style>

<!-- Immediately apply persisted theme to avoid flash on load -->
<script>
(function(){
    var theme = localStorage.getItem('theme');
    if(theme === 'dark'){
        document.documentElement.classList.add('dark');
    } else if(theme === 'light'){
        document.documentElement.classList.remove('dark');
    } else if(window.matchMedia('(prefers-color-scheme: dark)').matches){
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    }
})();
</script>

<!-- Embed preloaded earnings/tree to avoid duplicate server builds on page load -->
<script>
    // If the controller precomputed earnings/tree and passed it as $earnings,
    // expose it for client-side code so the client can reuse the same tree
    // instead of issuing an AJAX request which would rebuild the tree again.
    window.PRELOADED_EARNINGS = <?php echo json_encode($earnings ?? null, JSON_HEX_TAG); ?>;
</script>

<script>
let currentTreeData = null;
// Cache frequently used DOM elements to avoid repeated queries
let _cached = {};

// simple caches for reusable data
window.__profileCache = window.__profileCache || {};
window.__searchCache = window.__searchCache || {};
// Reusable colors for node levels to avoid reallocating arrays per node
const LEVEL_COLORS_LIGHT = ['#8b5cf6', '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#06b6d4'];
const LEVEL_COLORS_DARK = ['#a78bfa', '#60a5fa', '#34d399', '#fbbf24', '#f87171', '#22d3ee'];
// Make Network View (formerly 'Organizational Chart') the default view.
// Do not persist view mode across reloads for this page.
let currentViewMode = 'organizational';
let searchTimeout = null;
// Current render depth (how many levels to display). Default persisted or 3.
let currentRenderDepth = parseInt(localStorage.getItem('networkTreeDepth') || '3', 10) || 3;

// Initialize the page
document.addEventListener('DOMContentLoaded', function() {
    initializeTheme();
    // Set view select to default value only (no persistence)
    const viewModeSelect = document.getElementById('viewMode');
    if (viewModeSelect) {
        viewModeSelect.value = 'organizational';
    }

    // Tree depth persistence: default to 3 levels if not set
    const depthSelect = document.getElementById('treeDepth');
    const savedDepth = localStorage.getItem('networkTreeDepth') || String(currentRenderDepth || '3');
    if (depthSelect) {
        depthSelect.value = savedDepth;
        currentRenderDepth = parseInt(depthSelect.value, 10) || currentRenderDepth;
        // Persist selection when changed; we handle depth changes client-side
        depthSelect.addEventListener('change', function() {
            try { localStorage.setItem('networkTreeDepth', this.value); } catch (e) {}
            currentRenderDepth = parseInt(this.value, 10) || currentRenderDepth;
        });
    }

    // Initial load (uses the persisted depth)
    loadTree();
    setupSearch();
    // Auto-load tree on page load
    // remove duplicate load call; initial loadTree is sufficient and avoids unnecessary API work

    // Depth change helper used by the select onchange to avoid re-fetching the tree
    window.onDepthChange = function() {
        const sel = document.getElementById('treeDepth');
        if (!sel) return;
        const newDepth = parseInt(sel.value, 10) || 3;
        try { localStorage.setItem('networkTreeDepth', String(newDepth)); } catch (e) {}
        currentRenderDepth = newDepth;
        // Re-render immediately from the preloaded in-memory tree (no network)
        renderTree();
        // Update earnings table visibility and totals
        try { updateEarningsDisplay(); } catch (e) { console.warn('updateEarningsDisplay failed', e); }
        // Notify commission panel code to refresh its visible levels
        try { window.dispatchEvent(new CustomEvent('commissionDepthChange', { detail: { depth: newDepth } })); } catch (e) {}
    };

    // Update earnings table rows visibility based on `currentRenderDepth` and recompute totals
    window.updateEarningsDisplay = function() {
        const tbody = document.getElementById('earningsTableBody');
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll('tr[data-level]'));
        let running = 0;
        let totalDownlines = 0;
        let totalSum = 0;
        let totalEarned = 0;

        // Ensure rows are sorted by level ascending
        rows.sort((a,b) => Number(a.dataset.level) - Number(b.dataset.level));

        for (const row of rows) {
            const lvl = Number(row.dataset.level || 0);
            const sum = Number(row.dataset.sum || 0);
            const count = Number(row.dataset.count || 0);
            const earned = Number(row.dataset.earned || 0);

            const isVisible = lvl <= currentRenderDepth;
            // animate hide/show using classes; finalize display after transition
            if (isVisible) {
                // if was hidden, un-hide then allow transition
                if (row.style.display === 'none') {
                    row.style.display = '';
                    // force reflow then remove hidden class for transition in
                    // eslint-disable-next-line no-unused-expressions
                    row.offsetHeight;
                    row.classList.remove('hidden-level');
                } else {
                    row.classList.remove('hidden-level');
                }

                totalDownlines += count;
                totalSum += sum;
                totalEarned += earned;
                running += earned;
                const cumEl = row.querySelector('.level-cumulative');
                if (cumEl) cumEl.textContent = running.toFixed(2);
            } else {
                // animate out then hide after transition duration
                if (!row.classList.contains('hidden-level')) {
                    row.classList.add('hidden-level');
                    setTimeout(() => {
                        try { row.style.display = 'none'; } catch (e) {}
                    }, 260);
                }
                const cumEl = row.querySelector('.level-cumulative');
                if (cumEl) cumEl.textContent = '0.00';
            }
        }

        // Update footer values
        const elDown = document.getElementById('earnings-total-downlines');
        const elSum = document.getElementById('earnings-total-sum');
        const elEarn = document.getElementById('earnings-total-earned');
        const elCum = document.getElementById('earnings-total-cumulative');
        if (elDown) elDown.textContent = String(totalDownlines);
        if (elSum) elSum.textContent = Number(totalSum).toFixed(2);
        if (elEarn) elEarn.textContent = Number(totalEarned).toFixed(2);
        if (elCum) elCum.textContent = Number(totalEarned).toFixed(2);

        // Update summary paragraph if present
        const summary = document.querySelector('.earnings-summary-text');
        if (summary) {
            summary.textContent = `Summary: Total downlines: ${totalDownlines} — Sales sum: ${Number(totalSum).toFixed(2)} — Estimated commission payouts: ${Number(totalEarned).toFixed(2)}.`;
        }
    }

    // Re-render tree on theme change for full persistence
    const observer = new MutationObserver(() => {
        if (currentTreeData && (currentViewMode === 'compact' || currentViewMode === 'circle')) {
            renderTree();
        }
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});

// Load tree data from server
async function loadTree() {
    // Fetch the full tree up to the system-supported maximum so we can determine
    // whether nodes have (hidden) children, but we'll only render up to
    // `currentRenderDepth`. This lets nodes show 'has children' coloring
    // even when the UI is limited to a shallower depth.
    const fetchDepth = 9; // system max levels (include level 9 at 0% commission)
    const depth = document.getElementById('treeDepth').value;
    const rootUserId = <?= $current_user_id ?>; // Always use current user as root

    // If the server already precomputed the tree (via NetworkTreeController::index)
    // reuse it and avoid making an additional server request which would rebuild
    // the tree recursively a second time for the same page load.
    const pre = window.PRELOADED_EARNINGS ?? null;
    if (pre && pre.success && pre.tree) {
        currentTreeData = pre.tree;
        // Build a fast lookup map to avoid serializing data for each DOM element
        try { window.__nodeMap = window.__nodeMap || {}; buildNodeMap(currentTreeData); } catch (e) {}
        renderTree();
        try { updateEarningsDisplay(); } catch (e) { console.warn('updateEarningsDisplay failed', e); }
        return;
    }

    showLoading();

    try {
        // Request both `depth` (server expects) and `max_level` for backwards compatibility
        const url = `/api/user/direct-downlines?user_id=${rootUserId}&depth=${fetchDepth}&max_level=${fetchDepth}`;
        const response = await fetch(url);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
            // The API returns an array of downlines, but the root user is not included
            // So we need to build a root node with children = downlines
            const rootNode = {
                id: rootUserId,
                fullname: <?= json_encode($user_data['fullname'] ?? '') ?>,
                username: <?= json_encode($user_data['username'] ?? '') ?>,
                directReferrals: data.downlines.length,
                children: data.downlines
            };
            // Debug: log the downlines to verify 'user' is present
            console.log('Root node:', rootNode);
            if (rootNode.children && rootNode.children.length > 0) {
                rootNode.children.forEach(child => {
                    console.log('Downline:', child.username, child);
                });
            }
            currentTreeData = rootNode;
            try { window.__nodeMap = window.__nodeMap || {}; buildNodeMap(currentTreeData); } catch (e) {}
            renderTree();
            try { updateEarningsDisplay(); } catch (e) { console.warn('updateEarningsDisplay failed', e); }
        } else {
            console.error('API Error:', data);
            throw new Error(data.error || data.message || 'Unknown error occurred');
        }
    } catch (error) {
        console.error('Error loading tree:', error);
        console.error('Full error details:', error);
        showError();
    }
}

function showLoading() {
    document.getElementById('loadingSpinner').classList.remove('hidden');
    document.getElementById('treeContainer').classList.add('hidden');
    document.getElementById('errorMessage').classList.add('hidden');
}

function showError() {
    document.getElementById('loadingSpinner').classList.add('hidden');
    document.getElementById('treeContainer').classList.add('hidden');
    document.getElementById('errorMessage').classList.remove('hidden');
    
    // Show debug information in console
    console.log('Error state triggered');
}

function renderTree() {
    document.getElementById('loadingSpinner').classList.add('hidden');
    document.getElementById('errorMessage').classList.add('hidden');
    document.getElementById('treeContainer').classList.remove('hidden');
    
    const container = document.getElementById('treeContainer');
    
    if (!currentTreeData) {
        showError();
        return;
    }
    
    // If a user is selected, render their subtree as root
    let rootNode = currentTreeData;
    if (window.selectedTreeUserId) {
        // Recursively search for the node with selectedTreeUserId
        function findNodeById(node, id) {
            if (!node) return null;
            if (node.id == id) return node;
            if (node.children) {
                for (const child of node.children) {
                    const found = findNodeById(child, id);
                    if (found) return found;
                }
            }
            return null;
        }
        const found = findNodeById(currentTreeData, window.selectedTreeUserId);
        if (found) rootNode = found;
    }
    // Create a truncated copy of the tree limited to `currentRenderDepth` so
    // level changes are instant (client-side) and don't require server calls.
    const displayRoot = truncateTree(rootNode, currentRenderDepth);

    switch(currentViewMode) {
        case 'hierarchical':
            renderHierarchicalTree(container, displayRoot);
            break;
        case 'compact':
            // Compact view moved to its own route. Navigate there instead of rendering inline.
            try { window.location.href = '/user/network-tree/compact-view' + (window.selectedTreeUserId ? ('?node=' + encodeURIComponent(window.selectedTreeUserId)) : ''); } catch(e) {}
            return;
        case 'organizational':
            renderOrganizationalChart(container, displayRoot);
            break;
        // 'network' view (Network Grid) removed.
        case 'tree':
            renderTreeView(container, displayRoot);
            break;
        case 'circle':
            renderCircleView(container, displayRoot);
            break;
    }
}

// Create a shallow copy of the tree limited to `maxDepth` levels (root at depth=0)
function truncateTree(node, maxDepth, depth = 0) {
    if (!node || typeof node !== 'object') return node;
    // Always include basic node fields; copy children only when depth < maxDepth
    const copy = Object.assign({}, node);
    if (!copy.children || !Array.isArray(copy.children) || depth >= maxDepth) {
        copy.children = [];
        return copy;
    }
    // depth < maxDepth => include children but truncate their descendants
    copy.children = copy.children.map(c => truncateTree(c, maxDepth, depth + 1));
    return copy;
}

// Build a fast lookup map of id => node to avoid repeated JSON serialization
function buildNodeMap(root) {
    if (!root) return;
    const stack = [root];
    const map = window.__nodeMap = window.__nodeMap || {};
    while (stack.length) {
        const n = stack.pop();
        if (!n || !n.id) continue;
        map[String(n.id)] = n;
        if (Array.isArray(n.children)) {
            for (let i = 0; i < n.children.length; i++) stack.push(n.children[i]);
        }
    }
}

// Simple debounce helper
// Debounce helper (single shared instance)
function debounce(fn, wait) {
    let t;
    return function() {
        const args = arguments; const ctx = this;
        clearTimeout(t);
        t = setTimeout(() => fn.apply(ctx, args), wait || 100);
    };
}

// Single connector redraw manager to avoid per-node window listeners
window.__connectorRedraws = window.__connectorRedraws || [];
function scheduleConnectorRedraw() {
    if (!window.__connectorRedraws || !window.__connectorRedraws.length) return;
    for (const fn of window.__connectorRedraws) {
        try { fn(); } catch (e) {}
    }
    // Also trigger any layout resize observers registered
    if (window.__layoutResizeObservers && window.__layoutResizeObservers.length) {
        for (const fn of window.__layoutResizeObservers) {
            try { fn(); } catch (e) {}
        }
    }
}
// Register a global debounced resize handler once
try { if (!window.__connectorResizeHandler) { window.__connectorResizeHandler = debounce(scheduleConnectorRedraw, 120); window.addEventListener('resize', window.__connectorResizeHandler); } } catch (e) {}

function renderHierarchicalTree(container, data, level = 0) {
    container.innerHTML = '';
    container.className = 'tree-container-hierarchical';
    
    if (data) {
        const treeHtml = generateHierarchicalTreeHtml(data, 0);

        container.innerHTML = `
            <div class="tree-scroll-wrap">
                <div class="hierarchical-org-chart tree-scroll-inner p-8 min-h-[600px] rounded-xl border dark:border-slate-700 border-slate-300 bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-900 dark:to-slate-800">
                    ${treeHtml}
                </div>
            </div>
        `;
        
        // Add click handlers
        container.querySelectorAll('[data-user-id]').forEach(card => {
            card.addEventListener('click', function(e) {
                // prefer node map lookup, fall back to dataset userData for backward compatibility
                const uid = this.dataset.userId || this.getAttribute('data-user-id');
                const userData = uid && window.__nodeMap && window.__nodeMap[uid] ? window.__nodeMap[uid] : null;
                showUserDetails(userData || uid);
            });
        });
    }
}

function generateHierarchicalTreeHtml(node, level = 0) {
    // Ensure directReferrals is set for color logic
    if (typeof node.directReferrals === 'undefined' && node.children) {
        node.directReferrals = node.children.length;
    }

    const hasChildren = node.children && node.children.length > 0;

    const nodeId = `hierarchical-node-${node.id}`;

    // Level-based color schemes
    const colorsLight = LEVEL_COLORS_LIGHT;
    const colorsDark  = LEVEL_COLORS_DARK;
    const isDark = document.documentElement.classList.contains('dark');
    const nodeColor = isDark
        ? colorsDark[level % colorsDark.length]
        : colorsLight[level % colorsLight.length];

    // Avatar color
    const avatarColor = (node.directReferrals && node.directReferrals > 0)
        ? '#f59e0b'
        : 'rgba(255,255,255,0.25)';

    // Build children HTML (recursive)
    let childrenHtml = "";
    if (hasChildren) {
        childrenHtml = `
            <div class="org-children-level">
                ${node.children
                    .map(child => generateHierarchicalTreeHtml(child, level + 1))
                    .join("")}
            </div>
        `;
    }

    // Build node card HTML
    const html = `
        <div class="org-level-wrapper" data-node-id="${nodeId}">
            <div class="org-chart-node" 
                 style="
                    border-left: 4px solid ${nodeColor};
                    background: var(--node-bg);
                    padding: 12px;
                    border-radius: 12px;
                    margin: 10px auto;
                    width: 260px;
                    box-shadow: var(--node-shadow);
                 "
                 data-user-id="${node.id}"
            >
                <div style="display: flex; align-items: center;">
                    <div style="
                        width: 36px;
                        height: 36px;
                        border-radius: 50%;
                        background: ${avatarColor};
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: white;
                        font-weight: 700;
                        margin-right: 10px;
                    ">
                        ${(node.fullname || node.username || "U")
                            .charAt(0)
                            .toUpperCase()}
                    </div>

                    <div style="flex: 1;">
                        <div style="font-weight: 600;">
                            ${node.fullname || node.username || "Unnamed User"}
                        </div>
                        <div style="font-size: 12px; opacity: 0.7;">
                            ID: ${node.id}
                        </div>
                    </div>
                </div>

                <div style="
                    margin-top: 10px;
                    display: flex;
                    justify-content: space-between;
                ">
                    <div>
                        <div style="opacity: 0.8;">Direct</div>
                        <div style="font-weight: 600;">${node.directReferrals || 0}</div>
                    </div>

                    <div style="
                        background: rgba(255,255,255,0.1);
                        padding: 4px 8px;
                        border-radius: 6px;
                    ">
                        <div style="opacity: 0.8;">Earnings</div>
                        <div style="font-weight: 600;">
                            ${formatCurrency(node.totalCommissions || 0)}
                        </div>
                    </div>
                </div>
            </div>

            ${childrenHtml}
        </div>
    `;

    return html;
}


function generateOrganizationalChartHtml(node, level = 0) {
    const hasChildren = node.children && node.children.length > 0;
    const colors = ['#8b5cf6', '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#06b6d4'];
    // Color logic: highlight node if it has children
    let nodeColor = colors[level % colors.length];
    let avatarBg = 'rgba(255,255,255,0.25)';
    if (hasChildren) {
        nodeColor = '#f59e0b'; // orange for nodes with children
        avatarBg = '#f59e0b';
    }
    
    // Build children HTML recursively
    let childrenHtml = '';
    if (hasChildren) {
        const childrenContent = node.children.map(child => generateOrganizationalChartHtml(child, level + 1)).join('');
        childrenHtml = `
            <div class="org-children-level" style="
                margin-top: 40px;
                position: relative;
                display: flex;
                flex-direction: row;
                align-items: flex-start;
                width: 100%;
                overflow-x: auto;
                justify-content: center;
                gap: 40px;
            ">
                ${childrenContent}
            </div>
        `;
    }

    // Tooltip for direct referrals (applies to all nodes)
    let tooltipHtml = `<div class="org-tooltip" style="
        display:none;
        position:absolute;
        left:50%;
        top:-10px;
        transform:translateX(-50%);
        background:rgba(35,57,93,0.98);
        color:white;
        padding:12px 18px;
        border-radius:10px;
        box-shadow:0 4px 16px rgba(0,0,0,0.18);
        z-index:9999;
        min-width:220px;
        font-size:13px;
    ">
        <div style="font-weight:bold; margin-bottom:6px;">Direct Referrals:</div>
        <div style="font-size:15px; font-weight:bold;">${node.directReferrals || (node.children ? node.children.length : 0)}</div>
    </div>`;

    return `
        <div class="org-level-wrapper" style="
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            width: 100%;
        ">
            <div class="org-chart-node"
                data-user-id="${node.id}"
                data-user-id='${node.id}'
                style="
                    background: linear-gradient(135deg, ${nodeColor}, ${nodeColor}dd);
                    color: white;
                    padding: ${level === 0 ? '20px' : '16px'};
                    border-radius: 12px;
                    width: ${level === 0 ? '280px' : '220px'};
                    min-height: ${level === 0 ? '150px' : '130px'};
                    cursor: pointer;
                    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
                    transition: all 0.3s ease;
                    border: 2px solid rgba(255,255,255,0.2);
                    text-align: center;
                    position: relative;
                    z-index: 10;
                "
                onmouseover="const tip=this.querySelector('.org-tooltip');if(tip){tip.style.display='block';}event.stopPropagation();this.style.transform='translateY(-4px) scale(1.02)'; this.style.boxShadow='0 8px 30px rgba(0,0,0,0.3)';"
                onmouseout="const tip=this.querySelector('.org-tooltip');if(tip){tip.style.display='none';}event.stopPropagation();this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 6px 20px rgba(0,0,0,0.2)';"
                onclick="if(event.target.classList.contains('org-tooltip')){event.stopPropagation();}else{showUserDetails(${node.id});}"
            >
                <!-- User Avatar -->
                <div style="
                    width: ${level === 0 ? '60px' : '45px'};
                    height: ${level === 0 ? '60px' : '45px'};
                    background: ${avatarBg};
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: ${level === 0 ? '24px' : '18px'};
                    font-weight: bold;
                    margin: 0 auto 12px;
                    color: white;
                    border: 3px solid rgba(255,255,255,0.3);
                ">
                    ${(node.fullname || node.username).charAt(0).toUpperCase()}
                </div>
                <!-- User Info -->
                <div style="margin-bottom: 12px;">
                    <div style="font-weight: 600; font-size: ${level === 0 ? '18px' : '14px'}; margin-bottom: 4px;">${node.fullname || node.username}</div>
                    <div style="opacity: 0.8; font-size: ${level === 0 ? '14px' : '11px'};">@${node.username}</div>
                    <div style="opacity: 0.7; font-size: ${level === 0 ? '12px' : '10px'}; margin-top: 4px;">
                        ${level === 0 ? 'ORGANIZATION HEAD' : `Department Level ${level}`}
                    </div>
                </div>
                <!-- Stats -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 11px;">
                    <div style="background: rgba(255,255,255,0.1); padding: 6px; border-radius: 6px;">
                        <div style="opacity: 0.8; margin-bottom: 2px;">Direct Reports</div>
                        <div style="font-weight: 600; font-size: 14px;">${node.directReferrals || 0}</div>
                    </div>
                    <div style="background: rgba(255,255,255,0.1); padding: 6px; border-radius: 6px;">
                        <div style="opacity: 0.8; margin-bottom: 2px;">Performance</div>
                        <div style="font-weight: 600, font-size: 14px;">${formatCurrency(node.totalCommissions || 0)}</div>
                    </div>
                </div>
                ${tooltipHtml}
            </div>
            ${childrenHtml}
        </div>
    `;
}

function createTreeNode(nodeData, level) {
    const nodeDiv = document.createElement('div');
    nodeDiv.className = `tree-node level-${level}`;
    
    const userCard = document.createElement('div');
    userCard.className = 'user-card';
    userCard.onclick = () => showUserDetails(nodeData);
    
    userCard.innerHTML = `
        <div class="flex items-center mb-3">
            <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center text-sm font-bold mr-3">
                ${nodeData.fullname ? nodeData.fullname.charAt(0).toUpperCase() : 'U'}
            </div>
            <div class="flex-1">
                <div class="font-semibold text-sm">${nodeData.fullname || nodeData.username}</div>
                <div class="text-xs opacity-75">Level ${nodeData.level}</div>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2 text-xs">
            <div>
                <div class="font-medium">Direct:</div>
                <div>${nodeData.directReferrals || 0}</div>
            </div>
            <div>
                <div class="font-medium">Earnings:</div>
                <div>${formatCurrency(nodeData.totalCommissions || 0)}</div>
            </div>
        </div>
        <div class="mt-2 text-xs opacity-75">
            Joined: ${nodeData.joinDate ? new Date(nodeData.joinDate).toLocaleDateString() : 'Unknown'}
        </div>
    `;
    
    nodeDiv.appendChild(userCard);
    
    if (nodeData.children && nodeData.children.length > 0) {
        const childrenContainer = document.createElement('div');
        childrenContainer.className = 'tree-children';
        
        nodeData.children.forEach(child => {
            childrenContainer.appendChild(createTreeNode(child, level + 1));
        });
        
        nodeDiv.appendChild(childrenContainer);
        
            // Add an SVG overlay for connectors from this sponsor to its direct children
            nodeDiv.style.position = 'relative';
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('class', 'org-connectors');
            svg.style.position = 'absolute';
            svg.style.left = '0';
            svg.style.top = '0';
            svg.style.width = '100%';
            svg.style.height = '100%';
            svg.style.overflow = 'visible';
            svg.style.pointerEvents = 'none';
            svg.style.zIndex = '0';
            wrapper.insertBefore(svg, wrapper.firstChild);

            const drawConnectors = () => {
                // Clear existing paths
                while (svg.firstChild) svg.removeChild(svg.firstChild);
                // For tree nodes, parent card uses '.user-card'
                const parentCard = nodeDiv.querySelector('.user-card') || nodeDiv.querySelector('.org-node .card');
                if (!parentCard) return;
                const parentRect = parentCard.getBoundingClientRect();
                const containerRect = nodeDiv.getBoundingClientRect();

                // Determine effective scale applied to this subtree (look for dataset.currentScale up the tree)
                let effectiveScale = 1;
                try {
                    let el = parentCard;
                    while (el && el !== nodeDiv && el !== document.documentElement) {
                        if (el.dataset && el.dataset.currentScale) { effectiveScale = parseFloat(el.dataset.currentScale) || 1; break; }
                        el = el.parentElement;
                    }
                    if ((!effectiveScale || effectiveScale === 1) && nodeDiv.dataset && nodeDiv.dataset.currentScale) {
                        effectiveScale = parseFloat(nodeDiv.dataset.currentScale) || 1;
                    }
                } catch (e) { effectiveScale = 1; }

                const startX = (parentRect.left + parentRect.width / 2 - containerRect.left) / effectiveScale;
                const startY = (parentRect.bottom - containerRect.top) / effectiveScale;

                // Children are direct node elements inside the childrenContainer
                const childNodes = Array.from(childrenContainer.children).filter(c => c.classList && (c.classList.contains('tree-node') || c.classList.contains('org-node-container')));
                childNodes.forEach(child => {
                    // Try both possible child card selectors
                    const childCard = child.querySelector('.user-card') || child.querySelector('.org-node .card');
                    if (!childCard) return;
                    const childRect = childCard.getBoundingClientRect();
                    const endX = (childRect.left + childRect.width / 2 - containerRect.left) / effectiveScale;
                    const endY = (childRect.top - containerRect.top) / effectiveScale;

                    // Create a smooth curved path from parent to child
                    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                    const midY = (startY + endY) / 2;
                    const d = `M ${startX} ${startY} C ${startX} ${midY} ${endX} ${midY} ${endX} ${endY}`;
                    path.setAttribute('d', d);
                    // Visible, theme-aware stroke color
                    const isDark = document.documentElement.classList.contains('dark');
                    const strokeColor = isDark ? '#6b7280' : '#cbd5e0';
                    path.setAttribute('stroke', strokeColor);
                    path.setAttribute('stroke-width', '2');
                    path.setAttribute('fill', 'none');
                    path.setAttribute('stroke-linecap', 'round');
                    path.setAttribute('stroke-opacity', '0.95');
                    svg.appendChild(path);
                });
            };

            // Draw initially and on next frame (ensure layout settled)
            requestAnimationFrame(drawConnectors);
            // Redraw on window resize — register the draw function with global manager (avoids N duplicate handlers)
            try {
                const ref = drawConnectors;
                if (!window.__connectorRedraws) window.__connectorRedraws = [];
                // Avoid duplicates
                if (!window.__connectorRedraws.includes(ref)) window.__connectorRedraws.push(ref);
            } catch (e) {}
            // Observe children size changes to redraw when layout changes
            try {
                const ro = new ResizeObserver(drawConnectors);
                ro.observe(nodeDiv);
                // store observer to cleanup if needed (not strictly necessary here)
            } catch (e) {}
    }
    
    return nodeDiv;
}

function createHierarchicalTreeNode(nodeData, level) {
    const nodeDiv = document.createElement('div');
    nodeDiv.className = `hierarchical-tree-node level-${level}`;
    nodeDiv.setAttribute('data-user-id', nodeData.id);
    nodeDiv.setAttribute('data-level', level);
    
    const userCard = document.createElement('div');
    userCard.className = 'hierarchical-user-card';
    userCard.onclick = () => showUserDetails(nodeData);
    
    // Different styling based on level
    let cardClass = '';
    if (level === 0) {
        cardClass = 'bg-gradient-to-br from-purple-500 to-purple-600 text-white';
    } else if (level === 1) {
        cardClass = 'bg-gradient-to-br from-blue-500 to-blue-600 text-white';
    } else if (level === 2) {
        cardClass = 'bg-gradient-to-br from-green-500 to-green-600 text-white';
    } else {
        cardClass = 'bg-gradient-to-br from-orange-500 to-orange-600 text-white';
    }
    
    userCard.innerHTML = `
        <div class="${cardClass} rounded-lg p-3 shadow-lg cursor-pointer transition-all duration-300 hover:scale-105 relative z-10">
            <div class="flex items-center mb-2">
                <div class="w-8 h-8 bg-white bg-opacity-20 rounded-full flex items-center justify-center text-xs font-bold mr-2">
                    ${nodeData.fullname ? nodeData.fullname.charAt(0).toUpperCase() : 'U'}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-sm truncate">${nodeData.fullname || nodeData.username}</div>
                    <div class="text-xs opacity-75">Level ${nodeData.level}</div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div>
                    <div class="font-medium">Direct:</div>
                    <div class="font-semibold">${nodeData.directReferrals || 0}</div>
                </div>
                <div>
                    <div class="font-medium">Earnings:</div>
                    <div class="font-semibold">${formatCurrency(nodeData.totalCommissions || 0)}</div>
                </div>
            </div>
        </div>
    `;
    
    nodeDiv.appendChild(userCard);
    
    if (nodeData.children && nodeData.children.length > 0) {
        const childrenContainer = document.createElement('div');
        childrenContainer.className = 'hierarchical-children';
        childrenContainer.style.marginLeft = '40px';
        childrenContainer.style.marginTop = '20px';
        
        nodeData.children.forEach(child => {
            childrenContainer.appendChild(createHierarchicalTreeNode(child, level + 1));
        });
        
        nodeDiv.appendChild(childrenContainer);
    }
    
    return nodeDiv;
}

function addHierarchicalConnections(container) {
    // Remove existing connections
    container.querySelectorAll('.hierarchical-connection').forEach(el => el.remove());
    
    const nodes = container.querySelectorAll('.hierarchical-tree-node');
    
    nodes.forEach(node => {
        const level = parseInt(node.getAttribute('data-level'));
        if (level === 0) return; // Skip root node
        
        const userCard = node.querySelector('.hierarchical-user-card');
        const parentContainer = node.parentElement.parentElement;
        
        if (parentContainer && parentContainer.classList.contains('hierarchical-tree-node')) {
            const parentCard = parentContainer.querySelector('.hierarchical-user-card');
            
            if (userCard && parentCard) {
                const containerRect = container.getBoundingClientRect();
                const parentRect = parentCard.getBoundingClientRect();
                const childRect = userCard.getBoundingClientRect();

                // Determine effective scale applied to container (look for dataset.currentScale on container or ancestors)
                let effectiveScale = 1;
                try {
                    let el = parentCard;
                    // walk up from parentCard to container to find an element that stores currentScale
                    while (el && el !== container && el !== document.documentElement) {
                        if (el.dataset && el.dataset.currentScale) {
                            effectiveScale = parseFloat(el.dataset.currentScale) || 1;
                            break;
                        }
                        el = el.parentElement;
                    }
                    // fallback: check container itself
                    if ((!effectiveScale || effectiveScale === 1) && container.dataset && container.dataset.currentScale) {
                        effectiveScale = parseFloat(container.dataset.currentScale) || 1;
                    }
                } catch (e) { effectiveScale = 1; }

                // Compute unscaled positions relative to container so CSS positions inside the scaled container align correctly
                const parentLeft = (parentRect.left - containerRect.left) / effectiveScale;
                const parentTop = (parentRect.top - containerRect.top) / effectiveScale;
                const parentRight = parentLeft + (parentRect.width / effectiveScale);
                const parentBottom = parentTop + (parentRect.height / effectiveScale);

                const childLeft = (childRect.left - containerRect.left) / effectiveScale;
                const childTop = (childRect.top - containerRect.top) / effectiveScale;
                
                // Create horizontal line from parent
                const horizontalLine = document.createElement('div');
                horizontalLine.className = 'hierarchical-connection horizontal';
                horizontalLine.style.position = 'absolute';
                horizontalLine.style.left = parentRight + 'px';
                horizontalLine.style.top = (parentTop + (parentRect.height / effectiveScale) / 2 - 1) + 'px';
                horizontalLine.style.width = '20px';
                horizontalLine.style.height = '2px';
                horizontalLine.style.backgroundColor = '#cbd5e0';
                horizontalLine.style.zIndex = '1';
                
                container.appendChild(horizontalLine);
                
                // Create vertical line
                const verticalLine = document.createElement('div');
                verticalLine.className = 'hierarchical-connection vertical';
                verticalLine.style.position = 'absolute';
                verticalLine.style.left = (parentRight + 20 - 1) + 'px';
                verticalLine.style.top = (parentTop + (parentRect.height / effectiveScale) / 2) + 'px';
                verticalLine.style.width = '2px';
                verticalLine.style.height = Math.abs(childTop + (childRect.height / effectiveScale) / 2 - (parentTop + (parentRect.height / effectiveScale) / 2)) + 'px';
                verticalLine.style.backgroundColor = '#cbd5e0';
                verticalLine.style.zIndex = '1';
                
                container.appendChild(verticalLine);
                
                // Create final horizontal line to child
                const childHorizontalLine = document.createElement('div');
                childHorizontalLine.className = 'hierarchical-connection child-horizontal';
                childHorizontalLine.style.position = 'absolute';
                childHorizontalLine.style.left = (parentRight + 20) + 'px';
                childHorizontalLine.style.top = (childTop + (childRect.height / effectiveScale) / 2 - 1) + 'px';
                childHorizontalLine.style.width = (childLeft - (parentRight + 20)) + 'px';
                childHorizontalLine.style.height = '2px';
                childHorizontalLine.style.backgroundColor = '#cbd5e0';
                childHorizontalLine.style.zIndex = '1';
                
                container.appendChild(childHorizontalLine);
            }
        }
    });
}

function renderOrganizationalChart(container, data) {
    container.innerHTML = '';
    container.className = 'org-admin-style-chart';
    if (!data) return;

    // wrapper
    const wrapper = document.createElement('div');
    wrapper.style.display = 'flex';
    wrapper.style.flexDirection = 'column';
    wrapper.style.alignItems = 'center';
    wrapper.style.width = '100%';

    // Commission rates: prefer DB-backed per-membership table (`levels.commission_rate_json`).
    // Fallback to the legacy static rates when missing.
    let commissionRates = [0.05, 0.04, 0.03, 0.02, 0.01, 0.005, 0.0025, 0.0025, 0.00];

    <?php
    // Provide level rate arrays and current user's level to the client-side.
    try {
        $db = \Ginto\Core\Database::getInstance();
        $__levels_for_js = $db->select('tier_plans', ['id', 'name', 'commission_rate_json', 'short_name', 'amount'], ['ORDER' => ['id' => 'ASC']]);
    } catch (Throwable $e) {
        $__levels_for_js = [];
    }
    $__current_level_id = null;
    // Prefer the `user_data` passed into the view (if available) as it reflects
    // the currently-rendered user's context. Fall back to session lookup.
    if (isset($user_data) && is_array($user_data) && !empty($user_data['current_level_id'])) {
        $__current_level_id = $user_data['current_level_id'];
    } else if (isset($_SESSION['user_id'])) {
        try {
            $__current_level_id = $db->get('users', ['current_level_id'], ['id' => $_SESSION['user_id']])['current_level_id'] ?? null;
        } catch (Throwable $e) { $__current_level_id = null; }
    }
    ?>

    const __serverLevels = <?php echo json_encode($__levels_for_js, JSON_HEX_TAG); ?>;
    const __currentLevelId = <?php echo json_encode($__current_level_id, JSON_HEX_TAG); ?>;

    (function applyServerRates(){
        try {
            if (Array.isArray(__serverLevels) && __serverLevels.length && __currentLevelId) {
                const lvl = __serverLevels.find(l => Number(l.id) === Number(__currentLevelId));
                if (lvl && lvl.commission_rate_json) {
                    let parsed = null;
                    try { parsed = JSON.parse(lvl.commission_rate_json); } catch(e) { parsed = null; }
                    if (Array.isArray(parsed) && parsed.length) {
                        commissionRates = parsed.map(v => Number(v) || 0);
                        if (commissionRates.length < 9) while (commissionRates.length < 9) commissionRates.push(0);
                        else if (commissionRates.length > 9) commissionRates = commissionRates.slice(0,9);
                    }
                }
            }
        } catch (err) {
            console.warn('Failed to apply server commission rates, using defaults', err);
        }
    })();

    function formatCurrency(v) {
        return 'P' + Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Format percentage for display: use whole numbers when possible and
    // trim unnecessary trailing zeros for fractional percentages.
    function formatPercentage(rate) {
        const n = Number(rate) || 0;
        const pct = n * 100;
        if (Number.isNaN(pct)) return '0%';
        if (Number.isInteger(pct)) return pct + '%';
        let s = pct.toFixed(2);
        // Trim trailing zeros and possible trailing dot
        s = s.replace(/\.0+$/, '').replace(/(\.\d*[1-9])0+$/, '$1');
        return s + '%';
    }

    // Build a consistent tooltip footer. If `rate` and `baseAmount` are
    // provided, include a breakdown like (5% of P10,000.00). Otherwise show
    // just the commission amount and referrals. Returns an HTML string.
    function buildTooltipFooter(opts) {
        opts = opts || {};
        const commissionAmount = Number(opts.commissionAmount || 0);
        const rate = (typeof opts.rate !== 'undefined' && opts.rate !== null) ? Number(opts.rate) : null;
        const baseAmount = (typeof opts.baseAmount !== 'undefined' && opts.baseAmount !== null) ? Number(opts.baseAmount) : null;
        const referrals = Number(opts.referrals || 0);

        const parts = [];
        parts.push(`<div>Commission: <strong>${formatCurrency(commissionAmount)}</strong></div>`);
        if (rate !== null && baseAmount !== null) {
            parts.push(`<div style="font-size:12px;opacity:0.85;">(${formatPercentage(rate)} of ${formatCurrency(baseAmount)})</div>`);
        }
        parts.push(`<div>${referrals} referrals</div>`);
        return `<div class="tt-foot">${parts.join('\n')}</div>`;
    }

    // root card
    const rootCard = document.createElement('div');
    rootCard.style.display = 'flex';
    rootCard.style.flexDirection = 'column';
    rootCard.style.alignItems = 'center';
    rootCard.style.marginBottom = '32px';
    rootCard.style.position = 'relative';

    const rootInner = document.createElement('div');
    rootInner.style.background = 'transparent';
    rootInner.style.color = '#fff';
    rootInner.style.padding = '6px 8px';
    rootInner.style.borderRadius = '0';
    rootInner.style.boxShadow = 'none';
    rootInner.style.width = 'auto';
    rootInner.style.textAlign = 'center';
    rootInner.style.fontFamily = "Inter, sans-serif";
    // Ensure root content sits above connector SVG
    rootInner.style.position = rootInner.style.position || 'relative';
    rootInner.style.zIndex = '2';
    try {
        if (!document.getElementById('org-chart-tooltips-style')) {
            const s = document.createElement('style');
            s.id = 'org-chart-tooltips-style';
            s.textContent = `
                .card-with-tooltip{position:relative}
                .person-icon{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#4f6b8a,#23395d);display:flex;align-items:center;justify-content:center;color:#fff;box-shadow:0 6px 18px rgba(0,0,0,0.28);margin-bottom:8px}
                .person-icon svg{width:20px;height:20px;opacity:0.95}
                .person-tooltip{position:fixed;display:block;min-width:200px;max-width:360px;width:auto;background:linear-gradient(180deg,rgba(35,57,93,0.98),rgba(20,30,40,0.98));color:#fff;padding:10px 12px;border-radius:8px;box-shadow:0 12px 30px rgba(3,7,18,0.6);opacity:0;visibility:hidden;transition:opacity .12s ease,transform .12s ease;z-index:99999;white-space:normal}
                .person-tooltip .tt-row{display:flex;gap:12px;align-items:flex-start}
                .person-tooltip .tooltip-icon{width:48px;height:48px;border-radius:50%;flex:0 0 48px;background:linear-gradient(135deg,#4f6b8a,#23395d);display:flex;align-items:center;justify-content:center}
                .person-tooltip .tooltip-icon svg{width:20px;height:20px;opacity:0.95}
                .person-tooltip .tt-main{ text-align:left }
                .person-tooltip .tt-main .title{font-weight:700;font-size:14px;margin-bottom:4px}
                .person-tooltip .tt-main .sub{font-size:13px;opacity:0.9;margin-bottom:6px}
                .person-tooltip .tt-details{display:grid;grid-template-columns:1fr 1fr;gap:6px 10px;margin-top:6px;font-size:13px;color:rgba(255,255,255,0.95)}
                .person-tooltip .tt-details .label{opacity:0.85;font-weight:600;margin-right:6px}
                .person-tooltip .tt-foot{display:flex;justify-content:space-between;gap:12px;border-top:1px solid rgba(255,255,255,0.04);padding-top:8px;margin-top:10px;font-size:13px;opacity:0.95}
                .person-tooltip.show{opacity:1;visibility:visible}
            `;
            (document.head || document.documentElement).appendChild(s);
        }
    } catch (e) {}

    // Compute direct referrals for root and set matching icon background when it has children
    const rootDirectReferrals = (typeof data.directReferrals !== 'undefined') ? data.directReferrals : (data.children ? data.children.length : 0);
    const rootIconBg = rootDirectReferrals > 0 ? '#f59e0b' : 'linear-gradient(135deg,#4f6b8a,#23395d)';

    rootInner.innerHTML = `
        <div class="card-with-tooltip" tabindex="0" style="position:relative;display:flex;flex-direction:column;align-items:center;">
            <div class="person-icon" aria-hidden="true" style="background:${rootIconBg};">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5z" fill="rgba(255,255,255,0.95)"></path><path d="M4 20c0-3.314 2.686-6 6-6h4c3.314 0 6 2.686 6 6v1H4v-1z" fill="rgba(255,255,255,0.85)"></path></svg>
            </div>
            <div style="font-size:16px;font-weight:700;margin-top:6px;" class="text-gray-500 dark:text-white">${data.username}</div>
            <div style="font-size:13px;opacity:0.85;margin-top:2px;" class="text-gray-700 dark:text-white">${data.fullname || ''}</div>

            <div class="person-tooltip" role="tooltip">
                <div class="tt-row">
                    <div class="tooltip-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5z" fill="rgba(255,255,255,0.95)"></path><path d="M4 20c0-3.314 2.686-6 6-6h4c3.314 0 6 2.686 6 6v1H4v-1z" fill="rgba(255,255,255,0.85)"></path></svg>
                    </div>
                    <div class="tt-main">
                        <div class="title">${data.fullname || data.username}</div>
                        <div class="sub">${data.email || ''}</div>
                        <div class="tt-details">
                            <div><span class="label">ID:</span> ${data.id || ''}</div>
                            <div><span class="label">Level:</span> ${data.level || data.gl || 'N/A'}</div>
                            <div><span class="label">Joined:</span> ${data.created_at || data.registered_at || ''}</div>
                            <div><span class="label">Phone:</span> ${data.phone || ''}</div>
                            <div><span class="label">Country:</span> ${data.country || data.country_code || ''}</div>
                            <div><span class="label">Sponsor:</span> ${(
                                data.sponsor || data.upline || data.sponsor_username || data.upline_username ||
                                (data.referrer && (data.referrer.username || data.referrer.fullname)) || data.referrer_username ||
                                (data.referrer_id ? ('ID: ' + data.referrer_id) : '') || ''
                            )}</div>
                        </div>
                        <div style='margin-top:10px;'>
                            <span style='font-weight:bold;'>Direct Referrals: ${data.directReferrals || (data.children ? data.children.length : 0)}</span>
                        </div>
                     </div>
                 </div>
                ${buildTooltipFooter({ commissionAmount: data.totalCommissions || 0, rate: null, baseAmount: null, referrals: data.directReferrals || (data.children ? data.children.length : 0) })}
            </div>
        </div>
    `;

    rootCard.appendChild(rootInner);
    // Create a root wrapper similar to other nodeWraps so connectors can reference it
    const rootWrap = document.createElement('div');
    rootWrap.style.display = 'flex';
    rootWrap.style.flexDirection = 'column';
    rootWrap.style.alignItems = 'center';
    rootWrap.style.position = 'relative';
    // store reference to the root card so connector logic can use it
    rootWrap._nodeCard = rootCard;
    // mark root level
    try { rootWrap.dataset.level = '0'; } catch (e) {}
    rootWrap.appendChild(rootCard);
    wrapper.appendChild(rootWrap);

    // Recursively render nodes and draw connectors from each parent to its children
    function renderOrgNode(parentCard, node, parentWrap, level = 0) {
        const nodeWrap = document.createElement('div');
        nodeWrap.style.display = 'flex';
        nodeWrap.style.flexDirection = 'column';
        nodeWrap.style.alignItems = 'center';
        nodeWrap.style.position = 'relative';
        // mark level for later per-level summaries (root = 0)
        try { nodeWrap.dataset.level = String(level); } catch (e) {}

        const nodeCard = document.createElement('div');
        nodeCard.style.background = 'transparent';
        nodeCard.style.color = '#fff';
        nodeCard.style.padding = '6px 8px';
        nodeCard.style.borderRadius = '0';
        nodeCard.style.boxShadow = 'none';
        nodeCard.style.width = 'auto';
        nodeCard.style.textAlign = 'center';
        nodeCard.style.fontFamily = 'Inter, sans-serif';
        nodeCard.style.marginTop = '8px';
        // Ensure each node card renders above connectors
        nodeCard.style.position = nodeCard.style.position || 'relative';
        nodeCard.style.zIndex = '2';

        const directReferrals = (typeof node.directReferrals !== 'undefined') ? node.directReferrals : (node.children ? node.children.length : 0);
        const iconBg = directReferrals > 0 ? '#f59e0b' : 'linear-gradient(135deg,#4f6b8a,#23395d)';

        // Compute what the root user would earn from this single member
        // `level` is relative depth from root (root=0, direct referral=1)
        const fallbackPackageAmount = 10000;
        let nodeBaseAmount = Number(node.totalCommissions || 0) || 0;
        let downlineCount = (typeof node.directReferrals !== 'undefined') ? node.directReferrals : (node.children ? node.children.length : 0);
        // If downline or sum is zero, force commission and sum to zero
        if (!downlineCount || !nodeBaseAmount) {
            nodeBaseAmount = 0;
        } else {
            try {
                if ((!nodeBaseAmount || nodeBaseAmount === 0) && Array.isArray(__serverLevels) && __serverLevels.length) {
                    const lvlObj = __serverLevels.find(l => Number(l.id) === Number(node.level));
                    if (lvlObj && typeof lvlObj.amount !== 'undefined') nodeBaseAmount = Number(lvlObj.amount) || nodeBaseAmount;
                }
            } catch (e) {}
            if (!nodeBaseAmount || nodeBaseAmount === 0) nodeBaseAmount = fallbackPackageAmount;
        }
        const applicableRate = (level >= 1 && Array.isArray(commissionRates)) ? (Number(commissionRates[level - 1]) || 0) : 0;
        const commissionForRoot = (!downlineCount || !nodeBaseAmount) ? 0 : nodeBaseAmount * applicableRate;

        nodeCard.innerHTML = `
            <div class="card-with-tooltip" tabindex="0" style="position:relative;display:flex;flex-direction:column;align-items:center;">
                <div class="person-icon" aria-hidden="true" style="background:${iconBg};">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5z" fill="rgba(255,255,255,0.95)"></path><path d="M4 20c0-3.314 2.686-6 6-6h4c3.314 0 6 2.686 6 6v1H4v-1z" fill="rgba(255,255,255,0.85)"></path></svg>
                </div>
                <div style="font-size:16px;font-weight:700;margin-top:6px;" class="text-gray-500 dark:text-white">${node.username}</div>
                <div style="font-size:12px;opacity:0.85;margin-top:2px;" class="text-gray-700 dark:text-white">${node.fullname || ''}</div>
                <div class="person-tooltip" role="tooltip">
                    <div class="tt-row">
                        <div class="tooltip-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5z" fill="rgba(255,255,255,0.95)"></path><path d="M4 20c0-3.314 2.686-6 6-6h4c3.314 0 6 2.686 6 6v1H4v-1z" fill="rgba(255,255,255,0.85)"></path></svg>
                        </div>
                        <div class="tt-main">
                            <div class="title">${node.fullname || node.username}</div>
                            <div class="sub">${node.email || ''}</div>
                            <div class="tt-details">
                                <div><span class="label">ID:</span> ${node.id || ''}</div>
                                <div><span class="label">Level:</span> ${node.level || node.gl || 'N/A'}</div>
                                <div><span class="label">Joined:</span> ${node.created_at || node.registered_at || ''}</div>
                                <div><span class="label">Phone:</span> ${node.phone || ''}</div>
                                <div><span class="label">Country:</span> ${node.country || node.country_code || ''}</div>
                                <div><span class="label">Sponsor:</span> ${(
                                    node.sponsor || node.upline || node.sponsor_username || node.upline_username ||
                                    (node.referrer && (node.referrer.username || node.referrer.fullname)) || node.referrer_username ||
                                    (node.referrer_id ? ('ID: ' + node.referrer_id) : '') || ''
                                )}</div>
                            </div>
                            <div style='margin-top:10px;'>
                                <span style='font-weight:bold;'>Direct Referrals: ${directReferrals}</span>
                            </div>
                        </div>
                    </div>
                    ${buildTooltipFooter({ commissionAmount: commissionForRoot, rate: applicableRate, baseAmount: nodeBaseAmount, referrals: directReferrals })}
                </div>
            </div>
        `;
        try { nodeCard.dataset.userId = node.id; } catch (e) {}
        nodeWrap.appendChild(nodeCard);
        // Always store reference to this node's card; parent is optional
        nodeWrap._nodeCard = nodeCard;
        // Store the encoded data for summary calculations as well
        try { nodeWrap.dataset.userId = node.id; } catch (e) {}
        if (parentCard) {
            nodeWrap._parentCard = parentCard;
        }
        // Recursively render children up to the selected render depth
        // Render children while the current node's level is less than the requested depth.
        // Using `level < currentRenderDepth` ensures that selecting depth=2 shows level 1 and level 2.
        if (node.children && node.children.length > 0 && level < currentRenderDepth) {
            const childrenRow = document.createElement('div');
            childrenRow.style.display = 'flex';
            childrenRow.style.flexDirection = 'row';
            childrenRow.style.justifyContent = 'center';
            childrenRow.style.alignItems = 'flex-start';
            childrenRow.style.gap = '40px';
            childrenRow.style.marginTop = '24px';
            childrenRow.style.width = '100%';
            node.children.forEach(child => {
                childrenRow.appendChild(renderOrgNode(nodeCard, child, nodeWrap, level + 1));
            });
            nodeWrap.appendChild(childrenRow);

        }
        return nodeWrap;
    }

    // Render the root's children into the rootWrap (root card already appended)
    if (data.children && data.children.length > 0 && 1 <= currentRenderDepth) {
        const childrenRow = document.createElement('div');
        childrenRow.style.display = 'flex';
        childrenRow.style.flexDirection = 'row';
        childrenRow.style.justifyContent = 'center';
        childrenRow.style.alignItems = 'flex-start';
        childrenRow.style.gap = '40px';
        childrenRow.style.marginTop = '24px';
        childrenRow.style.width = '100%';
        data.children.forEach(child => {
            childrenRow.appendChild(renderOrgNode(rootCard, child, rootWrap, 1));
        });
        rootWrap.appendChild(childrenRow);

    }

    container.appendChild(wrapper);

    // Commission panel manager: single-instance, safe update, and duplication guards
    (function manageCommissionPanel() {
            function traverseLevels(root, maxLevel = 9) {
            const sums = Array(maxLevel).fill(0);
            if (!root) return sums;
            const q = [{ node: root, level: 0 }];
            while (q.length) {
                const { node, level } = q.shift();
                if (!node) continue;
                if (level > 0 && level <= maxLevel) {
                    sums[level - 1] += Number(node.totalCommissions || 0);
                }
                if (node.children && node.children.length) {
                    node.children.forEach(child => q.push({ node: child, level: level + 1 }));
                }
            }
            return sums;
        }

        // Use outer `formatCurrency` helper

        function buildPanel() {
            // Reuse any previously created panel attached to this wrapper
            if (wrapper._commissionPanel && document.body.contains(wrapper._commissionPanel)) {
                return wrapper._commissionPanel;
            }

            // Prefer a single global commission panel to avoid duplicates created
            // across multiple render passes. If a global panel exists, reuse it.
            const globalExisting = document.querySelector('.commission-summary-panel');
            if (globalExisting) {
                wrapper._commissionPanel = globalExisting;
                return globalExisting;
            }

            // Prevent duplicates by checking for an existing element inside wrapper
            let existing = wrapper.querySelector('.commission-summary-panel');
            if (existing) {
                wrapper._commissionPanel = existing;
                return existing;
            }

            const panel = document.createElement('div');
            panel.className = 'commission-summary-panel';
            panel.setAttribute('aria-hidden', 'true');
            // Render panel as a modal (fixed, centered) and keep it above per-level badges
            panel.style.position = 'fixed';
            panel.style.left = '50%';
            panel.style.top = '50%';
            panel.style.transform = 'translate(-50%, -50%)';
            // Make modal large enough to occupy a clear modal view
            panel.style.width = 'min(960px, 96vw)';
            panel.style.maxHeight = '90vh';
            panel.style.overflow = 'auto';
            panel.style.padding = '20px';
            panel.style.padding = '12px';
            panel.style.borderRadius = '10px';
            panel.style.boxShadow = '0 8px 30px rgba(2,6,23,0.6)';
            panel.style.backdropFilter = 'saturate(140%) blur(6px)';
            // total summary (modal) should be above per-level badges
            panel.style.zIndex = '10010';
            panel.style.pointerEvents = 'auto';
            panel.style.fontFamily = 'Inter, sans-serif';
            panel.style.fontSize = '13px';
            panel.style.color = document.documentElement.classList.contains('dark') ? '#e6eef8' : '#0f172a';
            panel.style.background = document.documentElement.classList.contains('dark') ? 'linear-gradient(180deg, rgba(17,24,39,0.9), rgba(6,8,15,0.8))' : 'linear-gradient(180deg, rgba(255,255,255,0.95), rgba(250,250,250,0.95))';

            panel.innerHTML = `
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <div style="font-weight:700;">Commission Summary</div>
                    <button type="button" class="close-commission-panel" title="Close" style="background:transparent;border:none;color:inherit;cursor:pointer">✕</button>
                </div>
                <div class="commission-rows" style="display:flex;flex-direction:column;gap:8px;">
                    <!-- rows inserted here -->
                </div>
                <div class="commission-total" style="margin-top:12px;border-top:1px solid rgba(0,0,0,0.06);padding-top:8px;display:flex;justify-content:space-between;font-weight:700;"> 
                    <div>Total Potential</div>
                    <div class="total-amount">P0.00</div>
                </div>
            `;

            // Wire up close button
            panel.querySelector('.close-commission-panel').addEventListener('click', () => {
                panel.style.display = 'none';
                // hide the backdrop as well
                try {
                    const bd = wrapper._commissionBackdrop || document.querySelector('.commission-modal-backdrop');
                    if (bd) bd.style.display = 'none';
                } catch (e) {}
                document.body.classList.remove('show-commissions');
                // remove escape handler if attached
                try {
                    if (wrapper._commissionEscapeHandler) {
                        document.removeEventListener('keydown', wrapper._commissionEscapeHandler);
                        wrapper._commissionEscapeHandler = null;
                    }
                } catch (e) {}
                try {
                    if (document._commissionEscapeHandler) {
                        document.removeEventListener('keydown', document._commissionEscapeHandler);
                        document._commissionEscapeHandler = null;
                    }
                } catch (e) {}
            });

            // create/attach a backdrop so the panel feels modal
            // Reuse any global backdrop if present to avoid duplicates
            let backdrop = document.querySelector('.commission-modal-backdrop') || wrapper._commissionBackdrop;
            if (!backdrop || !document.body.contains(backdrop)) {
                backdrop = document.createElement('div');
                backdrop.className = 'commission-modal-backdrop';
                backdrop.style.position = 'fixed';
                backdrop.style.left = '0';
                backdrop.style.top = '0';
                backdrop.style.width = '100%';
                backdrop.style.height = '100%';
                backdrop.style.background = 'rgba(0,0,0,0.45)';
                backdrop.style.zIndex = '10005';
                backdrop.style.display = 'none';
                backdrop.addEventListener('click', () => {
                    panel.style.display = 'none';
                    backdrop.style.display = 'none';
                    document.body.classList.remove('show-commissions');
                });
                document.body.appendChild(backdrop);
                wrapper._commissionBackdrop = backdrop;
            }

            document.body.appendChild(panel);
            wrapper._commissionPanel = panel;
            return panel;
        }

        function updatePanel(dataArg) {
            try {
                const panel = buildPanel();
                const perLevelSums = traverseLevels(dataArg || data, currentRenderDepth || 9);
                const rowsContainer = panel.querySelector('.commission-rows');
                // Fade rows container out, rebuild rows, then fade back in for smooth update
                try { rowsContainer.classList.add('hidden'); } catch (e) {}
                setTimeout(() => {
                    rowsContainer.innerHTML = '';
                    let totalPotential = 0;
                const levelsToShow = Math.min(commissionRates.length, currentRenderDepth || commissionRates.length);
                for (let i = 0; i < levelsToShow; i++) {
                    const percent = commissionRates[i];
                    const sum = perLevelSums[i] || 0;
                    const amount = sum * percent;
                    totalPotential += amount;

                    const row = document.createElement('div');
                    row.style.display = 'flex';
                    row.style.justifyContent = 'space-between';
                    row.style.alignItems = 'center';
                    row.style.gap = '8px';
                    row.innerHTML = `<div style="display:flex;flex-direction:column;">
                                        <div style='font-weight:600'>Level ${i+1}</div>
                                        <div style='font-size:12px;opacity:0.8'>${Math.round(percent*100*100)/100}% of level total</div>
                                    </div>
                                    <div style='text-align:right;'>
                                        <div style='font-weight:700'>${formatCurrency(amount)}</div>
                                        <div style='font-size:12px;opacity:0.8'>from ${formatCurrency(sum)}</div>
                                    </div>`;
                    rowsContainer.appendChild(row);
                }
                // Add a demo/example breakdown for a sample base amount (P10,000)
                try {
                    const demoBase = 10000; // P10,000.00 example
                    const demoHeader = document.createElement('div');
                    demoHeader.style.marginTop = '12px';
                    demoHeader.style.fontSize = '13px';
                    demoHeader.style.fontWeight = '600';
                    demoHeader.textContent = 'Example (P10,000):';
                    rowsContainer.appendChild(demoHeader);

                    const demoRows = document.createElement('div');
                    demoRows.style.display = 'flex';
                    demoRows.style.flexDirection = 'column';
                    demoRows.style.gap = '6px';
                    let demoTotal = 0;
                    for (let i = 0; i < levelsToShow; i++) {
                        const rate = commissionRates[i];
                        const amt = demoBase * rate;
                        demoTotal += amt;
                        const dr = document.createElement('div');
                        dr.style.display = 'flex';
                        dr.style.justifyContent = 'space-between';
                        dr.style.alignItems = 'center';
                        dr.innerHTML = `<div style="opacity:0.9">Level ${i+1}</div><div style="font-weight:600">${formatCurrency(amt)} <span style='font-size:12px;opacity:0.7'>@ ${Math.round(rate*10000)/100}%</span></div>`;
                        demoRows.appendChild(dr);
                    }
                    rowsContainer.appendChild(demoRows);
                    const demoTotalEl = document.createElement('div');
                    demoTotalEl.style.textAlign = 'right';
                    demoTotalEl.style.fontWeight = '700';
                    demoTotalEl.style.marginTop = '6px';
                    demoTotalEl.textContent = `Total Example: ${formatCurrency(demoTotal)}`;
                    rowsContainer.appendChild(demoTotalEl);
                } catch (e) {
                    console.warn('Failed to render demo commission breakdown', e);
                }
                const totalEl = panel.querySelector('.total-amount');
                if (totalEl) totalEl.textContent = formatCurrency(totalPotential);
                    // keep panel visible state and animate rows in
                    const shown = document.body.classList.contains('show-commissions');
                    panel.style.display = shown ? '' : 'none';
                    panel.setAttribute('aria-hidden', shown ? 'false' : 'true');
                    if (shown) adjustModalScale();
                    // Fade rows back in
                    try { rowsContainer.classList.remove('hidden'); } catch (e) {}
                    return panel;
                }, 20);
            } catch (e) {
                console.warn('Failed to update commission panel', e);
            }
        }

        // Listen for depth change events so the panel can refresh to hide/show levels
        window.addEventListener('commissionDepthChange', function() {
            try { updatePanel(); } catch (e) { console.warn('commissionDepthChange handler failed', e); }
        });

        // Adjust modal scale to compensate for browser zoom (devicePixelRatio).
        function adjustModalScale() {
            try {
                const panel = wrapper._commissionPanel || wrapper.querySelector('.commission-summary-panel');
                if (!panel) return;
                const zoom = window.devicePixelRatio || (window.outerWidth && window.innerWidth ? (window.outerWidth / window.innerWidth) : 1);
                const inv = (zoom && zoom > 0) ? (1 / zoom) : 1;
                // Apply transform with translate + scale (origin center)
                panel.style.transformOrigin = '50% 50%';
                panel.style.transform = `translate(-50%, -50%) scale(${inv})`;
                // Because we scale the panel, ensure it remains centered by keeping left/top at 50%
            } catch (e) {
                // ignore
            }
        }

        // Theme observer: keep panel colors in sync
        if (!wrapper._commissionThemeObserver) {
            wrapper._commissionThemeObserver = new MutationObserver(() => {
                const panel = wrapper._commissionPanel || wrapper.querySelector('.commission-summary-panel');
                if (!panel) return;
                panel.style.color = document.documentElement.classList.contains('dark') ? '#e6eef8' : '#0f172a';
                panel.style.background = document.documentElement.classList.contains('dark') ? 'linear-gradient(180deg, rgba(17,24,39,0.9), rgba(6,8,15,0.8))' : 'linear-gradient(180deg, rgba(255,255,255,0.95), rgba(250,250,250,0.95))';
            });
            wrapper._commissionThemeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        }

        // Expose update for other code paths (e.g. after data refresh)
        wrapper.updateCommissionPanel = function(d) { return updatePanel(d); };

        // Listen for resize/orientation changes to re-adjust scale
        if (!wrapper._commissionScaleHandler) {
            wrapper._commissionScaleHandler = function() { adjustModalScale(); };
            window.addEventListener('resize', wrapper._commissionScaleHandler);
            window.addEventListener('orientationchange', wrapper._commissionScaleHandler);
        }

        // Initial render
        updatePanel();
    })();

    // Tooltip show/hide (inline small tooltips inside each card)
    (function attachTooltips() {
        function showTooltipForCard(card) {
            const tip = card.querySelector('.person-tooltip');
            if (!tip) return;
            // Remember original parent so we can restore later
            if (!tip.__orgParent) {
                try {
                    tip.__orgParent = tip.parentElement;
                    tip.__orgNext = tip.nextSibling;
                    // Link tooltip to card for later lookup
                    tip.__forCard = card;
                    // Move tooltip to body so it's outside any stacking contexts
                    document.body.appendChild(tip);
                } catch (e) {
                    // ignore move errors
                }
            }

            // Ensure tooltip is on top
            tip.style.position = 'fixed';
            tip.style.zIndex = '2147483647';
            tip.classList.add('show');

            // Position near the card (above by default)
            const r = card.getBoundingClientRect();
            tip.style.left = (r.left + r.width / 2) + 'px';
            tip.style.top = (r.top - 8) + 'px';
            tip.style.transform = 'translateX(-50%) translateY(-100%)';

            // adjust if going off screen
            setTimeout(() => {
                const tr = tip.getBoundingClientRect();
                if (tr.left < 8) {
                    tip.style.left = '8px';
                    tip.style.transform = 'none';
                } else if (tr.right > window.innerWidth - 8) {
                    tip.style.left = 'auto';
                    tip.style.right = '8px';
                    tip.style.transform = 'none';
                }
                if (tr.top < 8) {
                    // show below the card instead
                    tip.style.top = (r.bottom + 8) + 'px';
                    tip.style.transform = 'translateX(-50%) translateY(0)';
                }
            }, 0);
            // Ensure tooltip stays visible while hovered and hides when mouse leaves it
            try {
                if (!tip.__leaveHandler) {
                    tip.__leaveHandler = function(ev) {
                        const related = ev.relatedTarget;
                        if (related && (card.contains(related) || tip.contains(related))) return;
                        hideTooltipForCard(card);
                    };
                    tip.addEventListener('mouseleave', tip.__leaveHandler);
                }
            } catch (e) {}
        }

        function hideTooltipForCard(card) {
            // Tooltip may have been moved to body; find it by stored reference
            let tip = null;
            try {
                tip = Array.from(document.querySelectorAll('.person-tooltip')).find(t => t.__forCard === card || t.__orgParent === card);
            } catch (e) { /* ignore */ }
            if (!tip) return;
            tip.classList.remove('show');
            // Restore original parent to keep DOM stable
            if (tip.__orgParent) {
                try {
                    if (tip.__orgNext && tip.__orgParent.contains(tip.__orgNext)) {
                        tip.__orgParent.insertBefore(tip, tip.__orgNext);
                    } else {
                        tip.__orgParent.appendChild(tip);
                    }
                } catch (e) {
                    // ignore restore errors
                }
                try { delete tip.__orgParent; } catch (e) {}
                try { delete tip.__orgNext; } catch (e) {}
            }
            try { 
                if (tip.__leaveHandler) {
                    tip.removeEventListener('mouseleave', tip.__leaveHandler);
                    delete tip.__leaveHandler;
                }
            } catch (e) {}
            try { delete tip.__forCard; } catch (e) {}
            // Clear inline z-index (let CSS control it)
            tip.style.zIndex = '';
        }

        // Use event delegation on wrapper
        wrapper.addEventListener('mouseover', (ev) => {
            const card = ev.target.closest && ev.target.closest('.card-with-tooltip');
            if (card) showTooltipForCard(card);
        });
        wrapper.addEventListener('mouseout', (ev) => {
            const related = ev.relatedTarget;
            const fromCard = ev.target.closest && ev.target.closest('.card-with-tooltip');
            if (!fromCard) return;
            // If mouse moved to something inside the same card, don't hide
            if (related && fromCard.contains(related)) return;
            // If mouse moved into the tooltip (which we move to body), don't hide
            let tip = null;
            try { tip = Array.from(document.querySelectorAll('.person-tooltip')).find(t => t.__forCard === fromCard || t.__orgParent === fromCard); } catch (e) {}
            if (related && tip && tip.contains(related)) return;
            hideTooltipForCard(fromCard);
        });

        // keyboard accessibility: focus/blur
        wrapper.addEventListener('focusin', (ev) => {
            const card = ev.target.closest && ev.target.closest('.card-with-tooltip');
            if (card) showTooltipForCard(card);
        });
        wrapper.addEventListener('focusout', (ev) => {
            const card = ev.target.closest && ev.target.closest('.card-with-tooltip');
            if (card) hideTooltipForCard(card);
        });
    })();

    // Draw connectors from each parent to its direct children (single reusable SVG)
    setTimeout(() => {
        // Ensure wrapper is a positioned container so absolute SVG coordinates align
        wrapper.style.position = wrapper.style.position || 'relative';
        wrapper.style.overflow = wrapper.style.overflow || 'visible';

        // Reuse existing SVG if present to avoid duplicates
        let svg = wrapper.querySelector('.org-connector-svg');
        if (!svg) {
            svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.classList.add('org-connector-svg');
            svg.style.position = 'absolute';
            svg.style.left = '0';
            svg.style.top = '0';
            svg.style.pointerEvents = 'none';
            svg.style.zIndex = '0';
            // Insert first so it stays behind nodes (nodes have z-index >= 2)
            wrapper.insertBefore(svg, wrapper.firstChild);
        }

        // Size the SVG to match wrapper's scrollable area
        function resizeSvgToWrapper() {
            const w = Math.max(wrapper.scrollWidth, wrapper.clientWidth);
            const h = Math.max(wrapper.scrollHeight, wrapper.clientHeight);
            svg.setAttribute('viewBox', `0 0 ${w} ${h}`);
            svg.setAttribute('width', w);
            svg.setAttribute('height', h);
            svg.style.width = w + 'px';
            svg.style.height = h + 'px';
        }

        // Helper: find immediate child nodeWraps inside any children row
        function getChildNodeWraps(parentWrap) {
            return Array.from(parentWrap.children)
                .filter(el => el && el.style && el.style.display === 'flex' && el.style.flexDirection === 'row')
                .flatMap(row => Array.from(row.children));
        }

        // Clear previous paths
        function clearPaths() {
            while (svg.firstChild) svg.removeChild(svg.firstChild);
        }

        // Draw all connectors based on current layout
        function drawAllConnectors() {
            clearPaths();
            resizeSvgToWrapper();
            // Detect effective transform scale applied to the wrapper (if any)
            let wrapperScale = 1;
            try {
                let el = wrapper;
                while (el && el !== document.documentElement) {
                    if (el.dataset && el.dataset.currentScale) { wrapperScale = parseFloat(el.dataset.currentScale) || 1; break; }
                    el = el.parentElement;
                }
            } catch (e) { wrapperScale = 1; }

            const wrapperRect = wrapper.getBoundingClientRect();

            function drawForNode(nodeWrap) {
                if (!nodeWrap || !nodeWrap._nodeCard) return;
                const parentRect = nodeWrap._nodeCard.getBoundingClientRect();
                const startX = (parentRect.left + parentRect.width / 2 - wrapperRect.left) / wrapperScale;
                const startY = (parentRect.bottom - wrapperRect.top) / wrapperScale;

                const childWraps = getChildNodeWraps(nodeWrap);
                childWraps.forEach(childWrap => {
                    if (!childWrap._nodeCard) return;
                    const childRect = childWrap._nodeCard.getBoundingClientRect();
                    const endX = (childRect.left + childRect.width / 2 - wrapperRect.left) / wrapperScale;
                    const endY = (childRect.top - wrapperRect.top) / wrapperScale;
                    const midY = startY + (endY - startY) * 0.35;
                    const d = `M ${startX} ${startY} C ${startX} ${midY} ${endX} ${midY} ${endX} ${endY}`;
                    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                    const isDark = document.documentElement.classList.contains('dark');
                    const strokeColor = isDark ? '#6b7280' : '#cbd5e0';
                    path.setAttribute('d', d);
                    path.setAttribute('stroke', strokeColor);
                    path.setAttribute('stroke-width', '2');
                    path.setAttribute('fill', 'none');
                    path.setAttribute('stroke-linecap', 'round');
                    path.setAttribute('stroke-opacity', '0.95');
                    svg.appendChild(path);
                });

                // recurse into immediate child wraps so deeper levels get processed
                childWraps.forEach(childWrap => drawForNode(childWrap));
            }

            // Start drawing from the root wrapper we created earlier
            try {
                if (typeof rootWrap !== 'undefined' && rootWrap) {
                    drawForNode(rootWrap);
                } else {
                    drawForNode(tree);
                }
            } catch (e) {
                // Fallback: if neither exists, try to draw from wrapper's first child
                const first = wrapper.firstElementChild;
                if (first) drawForNode(first);
            }
            // Debug: report number of paths drawn
            try {
                const count = svg.querySelectorAll('path').length;
                console.log('org connectors drawn:', count);
                if (count === 0) {
                    // Draw a visible debug line so we can see the SVG
                    const dbg = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                    dbg.setAttribute('d', 'M 10 10 L 200 10');
                    dbg.setAttribute('stroke', 'red');
                    dbg.setAttribute('stroke-width', '2');
                    svg.appendChild(dbg);
                }
            } catch (e) { console.warn('connector debug failed', e); }

            // Update per-level floating summaries for visible nodes
            try {
                function updateLevelSummaries() {
                    // remove existing badges
                    Array.from(wrapper.querySelectorAll('.level-summary-badge')).forEach(el => el.remove());

                    const wrapperRect = wrapper.getBoundingClientRect();
                    // collect nodeWraps that have a data-level attribute
                    const nodeWraps = Array.from(wrapper.querySelectorAll('[data-level]'))
                        .filter(n => n.dataset && typeof n.dataset.level !== 'undefined');

                    // group by level
                    const levels = {};
                    nodeWraps.forEach(nw => {
                        const lvl = parseInt(nw.dataset.level, 10) || 0;
                        if (!levels[lvl]) levels[lvl] = [];
                        levels[lvl].push(nw);
                    });

                    Object.keys(levels).forEach(k => {
                        const lvl = parseInt(k, 10);
                        if (isNaN(lvl) || lvl <= 0) return; // only show summaries for level 1+
                        const group = levels[lvl];
                        if (!group.length) return;

                        // compute vertical center for group
                        const centers = group.map(nw => {
                            const c = nw._nodeCard ? nw._nodeCard.getBoundingClientRect() : nw.getBoundingClientRect();
                            return ((c.top + c.bottom) / 2 - wrapperRect.top) / wrapperScale;
                        });
                        const avgY = centers.reduce((a,b)=>a+b,0)/centers.length || 0;

                        // compute sum of visible nodes' commissions at this level
                        let sum = 0;
                        group.forEach(nw => {
                            try {
                                                        const ud = (nw.dataset.userId && window.__nodeMap && window.__nodeMap[nw.dataset.userId]) ? window.__nodeMap[nw.dataset.userId] : (nw.dataset.userData ? JSON.parse(decodeURIComponent(nw.dataset.userData)) : null);
                                if (ud) sum += Number(ud.totalCommissions || 0);
                            } catch (e) {}
                        });

                        const rate = (typeof commissionRates !== 'undefined' && commissionRates[lvl-1]) ? commissionRates[lvl-1] : 0;
                        const amount = sum * rate;

                        const badge = document.createElement('div');
                        badge.className = 'level-summary-badge';
                        badge.dataset.level = String(lvl);
                        badge.style.position = 'absolute';
                        badge.style.right = '18px';
                        badge.style.top = Math.max(8, Math.round(avgY - 18)) + 'px';
                        badge.style.padding = '8px 12px';
                        badge.style.borderRadius = '10px';
                        badge.style.background = document.documentElement.classList.contains('dark') ? 'rgba(6,8,15,0.8)' : 'rgba(255,255,255,0.95)';
                        badge.style.boxShadow = '0 6px 20px rgba(2,6,23,0.4)';
                        badge.style.color = document.documentElement.classList.contains('dark') ? '#e6eef8' : '#0f172a';
                        badge.style.fontSize = '12px';
                        badge.style.fontWeight = '600';
                        badge.style.pointerEvents = 'auto';
                        // place per-level badges above most page content but below the total summary modal
                        badge.style.zIndex = '10000';
                        badge.innerHTML = `<div style="display:flex;flex-direction:column;gap:2px;align-items:flex-end;">
                            <div style="font-weight:700">Level ${lvl}</div>
                            <div style="font-size:12px;opacity:0.85">${formatCurrency(amount)} @ ${Math.round(rate*10000)/100}%</div>
                        </div>`;

                        wrapper.appendChild(badge);
                    });
                }

                updateLevelSummaries();
            } catch (e) { console.warn('failed to update level summaries', e); }
        }

        // Debounced redraw using rAF
        let rafId = null;
        function scheduleRedraw() {
            if (rafId) cancelAnimationFrame(rafId);
            rafId = requestAnimationFrame(() => {
                drawAllConnectors();
                rafId = null;
            });
        }

        // Initial draw
        scheduleRedraw();

        // Redraw on window resize and wrapper scroll
        try { window.__connectorRedraws = window.__connectorRedraws || []; if (!window.__connectorRedraws.includes(scheduleRedraw)) window.__connectorRedraws.push(scheduleRedraw); } catch (e) {}
        wrapper.addEventListener('scroll', scheduleRedraw);
        // Keep per-level badges in sync with theme changes without a reload
        (function observeBadgeTheme(){
            function updateBadges() {
                const isDark = document.documentElement.classList.contains('dark');
                document.querySelectorAll('.level-summary-badge').forEach(b => {
                    b.style.background = isDark ? 'rgba(6,8,15,0.8)' : 'rgba(255,255,255,0.95)';
                    b.style.color = isDark ? '#e6eef8' : '#0f172a';
                    b.style.boxShadow = isDark ? '0 6px 20px rgba(2,6,23,0.4)' : '0 6px 20px rgba(2,6,23,0.08)';
                });
            }
            const moBadges = new MutationObserver(updateBadges);
            moBadges.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
            // run once at init
            updateBadges();
        })();

        // Observe wrapper size changes
        try {
            const ro = new ResizeObserver(scheduleRedraw);
            ro.observe(wrapper);
        } catch (e) {
            // ResizeObserver not available; rely on resize event
        }
    }, 0);
}

function createOrgNode(nodeData) {
    const nodeDiv = document.createElement('div');
    nodeDiv.className = 'org-node-container text-center';
    
    const nodeCard = document.createElement('div');
    nodeCard.className = 'org-node cursor-pointer';
    nodeCard.onclick = () => showUserDetails(nodeData);
    
    nodeCard.innerHTML = `
        <div class="card">
            <div class="font-semibold text-sm text-gray-700 dark:text-white">${nodeData.fullname || nodeData.username}</div>
            <div class="text-xs text-gray-600 dark:text-gray-300">Level ${nodeData.level}</div>
            <div class="text-xs text-green-600 dark:text-green-400 mt-1">${formatCurrency(nodeData.totalCommissions || 0)}</div>
        </div>
    `;
    
    nodeDiv.appendChild(nodeCard);
    
    if (nodeData.children && nodeData.children.length > 0) {
        const childrenContainer = document.createElement('div');
        childrenContainer.className = 'flex justify-center flex-wrap gap-4';
        
        nodeData.children.forEach(child => {
            childrenContainer.appendChild(createOrgNode(child));
        });
        
        nodeDiv.appendChild(childrenContainer);
    }
    
    return nodeDiv;
}

// Compact view renderer: simple vertical list for quick scanning
// Compact view rendering removed from this file — use `network-tree/compact-view.php` instead.


// Network Grid view removed. Related renderer and DOM structure deleted.


function renderTreeView(container, data) {

    container.innerHTML = '';
    container.className = 'tree-container-hierarchical';

    if (!data) {
        container.innerHTML = '<div class="text-center py-10 text-slate-400 dark:text-slate-500">No data available for Tree View</div>';
        return;
    }

    // Create the Tree View layout: D1 D2 D3 D4 D5 above sponsor
    const hasChildren = data.children && data.children.length > 0;

    let treeViewHtml = '';

    if (!hasChildren) {
        // No downlines, just show sponsor
        treeViewHtml = `
            <div class="flex flex-col items-center min-h-[400px] p-10">
                <div class="text-slate-400 mb-5">No Downlines Found</div>
                ${generateSponsorNode(data)}
            </div>
        `;
    } else {
        // Show downlines above sponsor
        const downlinesHtml = data.children.map((child, index) => 
            generateDownlineNode(child, index + 1)
        ).join('');

        const childrenCount = data.children.length;
        const lineWidth = Math.max(300, (childrenCount * 120) + ((childrenCount - 1) * 40));

        // Theme-aware colors for lines
        const isDark = document.documentElement.classList.contains('dark');
        const horizontalLineColor = isDark ? 'bg-blue-900' : 'bg-blue-300';
        const verticalLineColor = isDark ? 'bg-blue-900' : 'bg-blue-300';

        treeViewHtml = `
            <div class="flex flex-col items-center min-h-[400px] px-5 py-10" style="position:relative;">
                <!-- Downlines Row -->
                <div class="flex justify-center items-center gap-10 mb-20" style="position:relative;">
                    ${downlinesHtml}

                    <!-- Horizontal connecting line -->
                    <div class="absolute flex items-center justify-center" style="bottom: -40px; left: 50%; transform: translateX(-50%); width: ${lineWidth}px; height: 3px; border-radius: 2px; background: #f59e42; color: #111; font-size: 14px; z-index: 100;" >
                    </div>

                    <!-- Vertical line to sponsor -->
                    <div class="absolute flex items-center justify-center" style="bottom: -80px; left: 50%; transform: translateX(-50%); width: 3px; height: 40px; border-radius: 2px; background: #f59e42; color: #111; font-size: 14px; z-index: 100;">
                    </div>
                </div>

                <!-- Sponsor at bottom -->
                ${generateSponsorNode(data)}
            </div>
        `;
    }

    container.innerHTML = `
        <div class="tree-scroll-wrap">
            <div class="p-5 tree-scroll-inner min-h-[600px] rounded-xl border dark:border-slate-700 border-slate-300 bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-900 dark:to-slate-800">
                ${treeViewHtml}
            </div>
        </div>
    `;

    // Add click handlers inside the function
    container.querySelectorAll('.inverted-tree-node').forEach(card => {
        card.addEventListener('click', function() {
            const uid = this.dataset.userId || this.getAttribute('data-user-id');
            const userData = uid && window.__nodeMap && window.__nodeMap[uid] ? window.__nodeMap[uid] : (this.dataset.userData ? JSON.parse(decodeURIComponent(this.dataset.userData)) : null);
            showUserDetails(userData || uid);
        });
    });
}


function generateInvertedTreeHtml(node, level = 0, maxLevel = null) {
    // Remove all items: only show debug banner
    return `
        <div style="width:100%;text-align:center;background:#2196f3;color:#fff;font-size:32px;padding:32px 0;margin:40px 0; border: 6px dashed #f44336;">[DEBUG] Tree View: No Items Rendered</div>
    `;
}

function generateDownlineNode(node, position) {
    return `
           <div class="downline-node" 
               data-user-id="${node.id}" 
               data-user-id='${node.id}'
             onclick="showUserDetails(${node.id})"
             style="
                background: linear-gradient(135deg, #3b82f6, #1d4ed8);
                color: white;
                padding: 15px;
                border-radius: 10px;
                width: 120px;
                height: 100px;
                cursor: pointer;
                box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3);
                transition: all 0.3s ease;
                position: relative;
                z-index: 10;
                border: 2px solid rgba(255,255,255,0.2);
                text-align: center;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
            "
            onmouseover="this.style.transform='translateY(-4px) scale(1.05)'; this.style.boxShadow='0 8px 25px rgba(59, 130, 246, 0.4)';"
            onmouseout="this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 6px 20px rgba(59, 130, 246, 0.3)';">
            
            <!-- User Avatar -->
            <div style="
                width: 35px; 
                height: 35px; 
                background: rgba(255,255,255,0.25); 
                border-radius: 50%; 
                display: flex; 
                align-items: center; 
                justify-content: center; 
                font-size: 14px; 
                font-weight: bold; 
                margin-bottom: 8px; 
                color: white;
                border: 2px solid rgba(255,255,255,0.3);
            ">
                ${(node.fullname || node.username).charAt(0).toUpperCase()}
            </div>
            
            <!-- Downline Label -->
            <div style="
                font-weight: 600; 
                font-size: 12px; 
                margin-bottom: 4px;
                color: rgba(255,255,255,0.9);
            ">D${position}</div>
            
            <!-- User Name -->
            <div style="
                font-size: 10px; 
                opacity: 0.8;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                width: 100%;
            ">${(node.fullname || node.username).length > 12 ? (node.fullname || node.username).substring(0, 12) + '...' : (node.fullname || node.username)}</div>
        </div>
    `;
}

function generateSponsorNode(node) {
    return `
           <div class="sponsor-node" 
               data-user-id="${node.id}" 
               data-user-id='${node.id}'
             onclick="showUserDetails(${node.id})"
             style="
                background: linear-gradient(135deg, #8b5cf6, #7c3aed);
                color: white;
                padding: 25px;
                border-radius: 15px;
                width: 300px;
                min-height: 180px;
                cursor: pointer;
                box-shadow: 0 12px 35px rgba(139, 92, 246, 0.4);
                transition: all 0.3s ease;
                position: relative;
                z-index: 10;
                border: 3px solid rgba(255,255,255,0.3);
                text-align: center;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
            "
            onmouseover="this.style.transform='translateY(-6px) scale(1.02)'; this.style.boxShadow='0 15px 45px rgba(139, 92, 246, 0.5)';"
            onmouseout="this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 12px 35px rgba(139, 92, 246, 0.4)';">
            
            <!-- User Avatar -->
            <div style="
                width: 70px; 
                height: 70px; 
                background: rgba(255,255,255,0.25); 
                border-radius: 50%; 
                display: flex; 
                align-items: center; 
                justify-content: center; 
                font-size: 28px; 
                font-weight: bold; 
                margin-bottom: 15px; 
                color: white;
                border: 4px solid rgba(255,255,255,0.3);
            ">
                ${(node.fullname || node.username).charAt(0).toUpperCase()}
            </div>
            
            <!-- User Info -->
            <div style="margin-bottom: 15px;">
                <div style="font-weight: 700; font-size: 18px; margin-bottom: 5px;">${node.fullname || node.username}</div>
                <div style="opacity: 0.8; font-size: 12px; margin-bottom: 5px;">@${node.username}</div>
                <div style="
                    opacity: 0.9; 
                    font-size: 11px; 
                    background: rgba(255,255,255,0.15); 
                    padding: 4px 8px; 
                    border-radius: 12px; 
                    display: inline-block;
                ">SPONSOR</div>
            </div>
            
            <!-- Stats -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; width: 100%; font-size: 11px;">
                <div style="background: rgba(255,255,255,0.15); padding: 10px; border-radius: 8px;">
                    <div style="opacity: 0.8; margin-bottom: 4px;">Network</div>
                    <div style="font-weight: 600; font-size: 16px;">${node.directReferrals || 0}</div>
                </div>
                <div style="background: rgba(255,255,255,0.15); padding: 10px; border-radius: 8px;">
                    <div style="opacity: 0.8; margin-bottom: 4px;">Earnings</div>
                    <div style="font-weight: 600; font-size: 16px;">${formatCurrency(node.totalCommissions || 0)}</div>
                </div>
            </div>
        </div>
    `;
}

function calculateMaxLevel(node, currentLevel = 0) {
    let maxLevel = currentLevel;
    if (node.children) {
        node.children.forEach(child => {
            const childMaxLevel = calculateMaxLevel(child, currentLevel + 1);
            maxLevel = Math.max(maxLevel, childMaxLevel);
        });
    }
    return maxLevel;
}

function addTreeConnections(container) {
    // Remove existing connections
    container.querySelectorAll('.tree-connection').forEach(el => el.remove());
    
    // Get all nodes except root (level 0)
    const nodeContainers = container.querySelectorAll('.tree-node-container[data-level]:not([data-level="0"])');
    
    // Group nodes by their parent to handle sibling connections
    const nodesByParent = new Map();
    
    nodeContainers.forEach(nodeContainer => {
        const level = parseInt(nodeContainer.getAttribute('data-level'));
        if (level === 0) return;
        
        const parentContainer = nodeContainer.parentElement.parentElement;
        if (parentContainer && parentContainer.classList.contains('tree-node-container')) {
            const parentId = parentContainer.getAttribute('data-user-id');
            if (!nodesByParent.has(parentId)) {
                nodesByParent.set(parentId, []);
            }
            nodesByParent.get(parentId).push(nodeContainer);
        }
    });
    
    // Create connections for each parent-children group
    nodesByParent.forEach((children, parentId) => {
        const parentContainer = container.querySelector(`[data-user-id="${parentId}"]`);
        if (!parentContainer) return;
        
        const parentNode = parentContainer.querySelector('.tree-node-item');
        if (!parentNode) return;
        
        // Determine effective scale applied to this container (if any)
        let containerScale = 1;
        try {
            let el = parentNode;
            while (el && el !== container && el !== document.documentElement) {
                if (el.dataset && el.dataset.currentScale) { containerScale = parseFloat(el.dataset.currentScale) || 1; break; }
                el = el.parentElement;
            }
            if ((!containerScale || containerScale === 1) && container.dataset && container.dataset.currentScale) {
                containerScale = parseFloat(container.dataset.currentScale) || 1;
            }
        } catch (e) { containerScale = 1; }

        const containerRect = container.getBoundingClientRect();
        const parentRect = parentNode.getBoundingClientRect();
        
        // Calculate parent center point (unscaled coords)
        const parentCenterX = (parentRect.left + parentRect.width / 2 - containerRect.left) / containerScale;
        const parentBottom = (parentRect.bottom - containerRect.top) / containerScale;
        
        // Single child - direct line
        if (children.length === 1) {
            const childNode = children[0].querySelector('.tree-node-item');
            if (childNode) {
                const childRect = childNode.getBoundingClientRect();
                const childCenterX = (childRect.left + childRect.width / 2 - containerRect.left) / containerScale;
                const childTop = (childRect.top - containerRect.top) / containerScale;
                
                // Vertical line from parent to child
                const verticalLine = document.createElement('div');
                verticalLine.className = 'tree-connection upline-connection';
                verticalLine.style.position = 'absolute';
                verticalLine.style.left = (parentCenterX - 1) + 'px';
                verticalLine.style.top = parentBottom + 'px';
                verticalLine.style.width = '2px';
                verticalLine.style.height = (childTop - parentBottom - 10) + 'px';
                verticalLine.style.backgroundColor = '#3b82f6';
                verticalLine.style.zIndex = '1';
                
                container.appendChild(verticalLine);
                
                // Connecting line to child if not directly below
                if (Math.abs(parentCenterX - childCenterX) > 5) {
                    // Horizontal connector
                    const horizontalLine = document.createElement('div');
                    horizontalLine.className = 'tree-connection upline-connection';
                    horizontalLine.style.position = 'absolute';
                    horizontalLine.style.left = Math.min(parentCenterX, childCenterX) + 'px';
                    horizontalLine.style.top = (childTop - 10 - 1) + 'px';
                    horizontalLine.style.width = Math.abs(childCenterX - parentCenterX) + 'px';
                    horizontalLine.style.height = '2px';
                    horizontalLine.style.backgroundColor = '#3b82f6';
                    horizontalLine.style.zIndex = '1';
                    
                    container.appendChild(horizontalLine);
                    
                    // Final vertical line to child
                    const finalVertical = document.createElement('div');
                    finalVertical.className = 'tree-connection upline-connection';
                    finalVertical.style.position = 'absolute';
                    finalVertical.style.left = (childCenterX - 1) + 'px';
                    finalVertical.style.top = (childTop - 10) + 'px';
                    finalVertical.style.width = '2px';
                    finalVertical.style.height = '10px';
                    finalVertical.style.backgroundColor = '#3b82f6';
                    finalVertical.style.zIndex = '1';
                    
                    container.appendChild(finalVertical);
                }
            }
        } else if (children.length > 1) {
            // Multiple children - create distribution lines
            const childPositions = children.map(child => {
                const childNode = child.querySelector('.tree-node-item');
                    const childRect = childNode.getBoundingClientRect();
                    return {
                        centerX: (childRect.left + childRect.width / 2 - containerRect.left) / containerScale,
                        top: (childRect.top - containerRect.top) / containerScale,
                        node: child
                    };
            }).sort((a, b) => a.centerX - b.centerX);
            
            const leftmostX = childPositions[0].centerX;
            const rightmostX = childPositions[childPositions.length - 1].centerX;
            const childrenTop = Math.min(...childPositions.map(p => p.top));
            
            // Main vertical line from parent
            const mainVertical = document.createElement('div');
            mainVertical.className = 'tree-connection upline-connection';
            mainVertical.style.position = 'absolute';
            mainVertical.style.left = (parentCenterX - 1) + 'px';
            mainVertical.style.top = parentBottom + 'px';
            mainVertical.style.width = '2px';
            mainVertical.style.height = (childrenTop - parentBottom - 30) + 'px';
            mainVertical.style.backgroundColor = '#3b82f6';
            mainVertical.style.zIndex = '1';
            
            container.appendChild(mainVertical);
            
            // Horizontal distribution line
            const distributionLine = document.createElement('div');
            distributionLine.className = 'tree-connection upline-connection';
            distributionLine.style.position = 'absolute';
            distributionLine.style.left = leftmostX + 'px';
            distributionLine.style.top = (childrenTop - 20 - 1) + 'px';
            distributionLine.style.width = (rightmostX - leftmostX) + 'px';
            distributionLine.style.height = '2px';
            distributionLine.style.backgroundColor = '#3b82f6';
            distributionLine.style.zIndex = '1';
            
            container.appendChild(distributionLine);
            
            // Connector from main line to distribution line
            const connectorVertical = document.createElement('div');
            connectorVertical.className = 'tree-connection upline-connection';
            connectorVertical.style.position = 'absolute';
            connectorVertical.style.left = (parentCenterX - 1) + 'px';
            connectorVertical.style.top = (childrenTop - 30) + 'px';
            connectorVertical.style.width = '2px';
            connectorVertical.style.height = '10px';
            connectorVertical.style.backgroundColor = '#3b82f6';
            connectorVertical.style.zIndex = '1';
            
            container.appendChild(connectorVertical);
            
            // Individual drop lines to each child
            childPositions.forEach(position => {
                const dropLine = document.createElement('div');
                dropLine.className = 'tree-connection upline-connection';
                dropLine.style.position = 'absolute';
                dropLine.style.left = (position.centerX - 1) + 'px';
                dropLine.style.top = (childrenTop - 20) + 'px';
                dropLine.style.width = '2px';
                dropLine.style.height = '20px';
                dropLine.style.backgroundColor = '#3b82f6';
                dropLine.style.zIndex = '1';
                
                container.appendChild(dropLine);
            });
        }
    });
}

function renderCircleView(container, data) {
    container.innerHTML = '';
    container.className = 'tree-container-circle';
    
    const circleContainer = document.createElement('div');
    circleContainer.className = 'circle-container';
    circleContainer.style.position = 'relative';
    circleContainer.style.width = '100%';
    circleContainer.style.height = '600px';
    const isDark = document.documentElement.classList.contains('dark');
    circleContainer.style.background = isDark
        ? 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)'
        : 'linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%)';
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
    
    const nodes = flattenTreeData(data, 0);
    const positions = calculateCirclePositions(nodes);
    
    // Create nodes
    nodes.forEach((item, index) => {
        const nodeDiv = document.createElement('div');
        nodeDiv.className = `circle-node level-${item.level}`;
        nodeDiv.style.left = positions[index].x + 'px';
        nodeDiv.style.top = positions[index].y + 'px';
        nodeDiv.onclick = () => showUserDetails(item.node);
        
        // Color based on level and theme
        const colorsLight = LEVEL_COLORS_LIGHT_MAP || [
            'linear-gradient(135deg, #ec4899, #be185d)', // Pink for root (YOU)
            'linear-gradient(135deg, #06b6d4, #0891b2)', // Cyan for level 1
            'linear-gradient(135deg, #10b981, #059669)', // Green for level 2
            'linear-gradient(135deg, #f59e0b, #d97706)', // Orange for level 3
            'linear-gradient(135deg, #8b5cf6, #7c3aed)'  // Purple for level 4+
        ];
        const colorsDark = LEVEL_COLORS_DARK_MAP || [
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
    
    // Add subtle connecting lines only to immediate children (no overlapping)
    if (nodes.length > 1) {
        const rootPosition = positions[0];
        const rootSize = 120;
        
        // Only connect direct children (level 1) to avoid overlapping
        nodes.filter(item => item.level === 1).forEach((item, index) => {
            const childIndex = nodes.findIndex(n => n.node.id === item.node.id);
            const childPosition = positions[childIndex];
            const childSize = 90;
            
            const line = document.createElement('div');
            line.className = 'circle-connection';
            
            // Calculate line from edge of root to edge of child
            const dx = (childPosition.x + childSize/2) - (rootPosition.x + rootSize/2);
            const dy = (childPosition.y + childSize/2) - (rootPosition.y + rootSize/2);
            const length = Math.sqrt(dx * dx + dy * dy) - (rootSize/2 + childSize/2);
            const angle = Math.atan2(dy, dx) * 180 / Math.PI;
            
            // Position line at edge of root node
            const startX = rootPosition.x + rootSize/2 + Math.cos(angle * Math.PI / 180) * rootSize/2;
            const startY = rootPosition.y + rootSize/2 + Math.sin(angle * Math.PI / 180) * rootSize/2;
            
            line.style.position = 'absolute';
            line.style.left = startX + 'px';
            line.style.top = startY + 'px';
            line.style.width = length + 'px';
            line.style.height = '2px';
            // Theme-aware connecting line
            line.style.background = isDark
                ? 'linear-gradient(90deg, rgba(59,130,246,0.5), rgba(30,41,59,0.2))'
                : 'linear-gradient(90deg, rgba(59,130,246,0.4), rgba(203,213,225,0.1))';
            line.style.transformOrigin = '0 50%';
            line.style.transform = `rotate(${angle}deg)`;
            line.style.zIndex = '1';
            line.style.borderRadius = '1px';
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

function changeViewMode() {
    const select = document.getElementById('viewMode');
    const val = (select && select.value || '').toString().trim();
    // If the selected option provides a data-url, navigate there (append depth param if available)
    try {
        const opt = select && select.options && select.options[select.selectedIndex];
        const url = opt && opt.dataset && opt.dataset.url ? opt.dataset.url : null;
        if (url) {
            const depthVal = (document.getElementById('treeDepth') && document.getElementById('treeDepth').value) ? ('?depth=' + encodeURIComponent(document.getElementById('treeDepth').value)) : '';
            // If url already contains query params, append with &
            const sep = url.indexOf('?') === -1 ? '' : '&';
            window.location.href = url + (depthVal ? (sep + depthVal.replace(/^\?/, '')) : '');
            return;
        }
    } catch (e) {
        // Fall back to legacy behavior
    }

    currentViewMode = val || 'organizational';
    renderTree();
}



function expandAll() {
    // Expand all known children containers and recompute canvas widths
    try {
        const selectors = ['.tree-children', '.hierarchical-children', '.children', '.children-container'];
        const nodes = document.querySelectorAll(selectors.join(','));
        nodes.forEach(n => { try { n.style.display = ''; } catch(e) {} });
        // update icons if present
        document.querySelectorAll('[id$="-icon"]').forEach(ic => {
            try { ic.classList.remove('fa-chevron-up'); ic.classList.add('fa-chevron-down'); } catch(e) {}
        });
        // recompute inner widths and refresh canvas
        document.querySelectorAll('.tree-scroll-inner').forEach(inner => { try { adjustInner(inner); } catch(e) {} });
        try { refreshCanvasDebounced(); } catch(e) {}
    } catch (e) { console.warn('expandAll failed', e); }
}

function toggleNode(nodeId) {
    // Basic expand/collapse for the node's children container
    try {
        const el = document.getElementById(nodeId);
        if (!el) return;

        // Often children HTML is placed immediately after the node element
        const childrenContainer = el.nextElementSibling;
        if (childrenContainer) {
            const isHidden = childrenContainer.style.display === 'none';
            childrenContainer.style.display = isHidden ? '' : 'none';

            // Toggle icon if present
            const icon = document.getElementById(`${nodeId}-icon`);
            if (icon) {
                icon.classList.toggle('fa-chevron-down', isHidden);
                icon.classList.toggle('fa-chevron-up', !isHidden);
            }

            // Recompute widths for nearest inner wrapper and refresh canvas
            try {
                const inner = el.closest ? el.closest('.tree-scroll-inner') : document.querySelector('.tree-scroll-inner');
                if (inner) adjustInner(inner);
            } catch(e) {}
            try { refreshCanvasDebounced(); } catch(e) {}
        }
    } catch (e) {
        console.error('toggleNode error', e);
    }
}

function collapseAll() {
    try {
        const selectors = ['.tree-children', '.hierarchical-children', '.children', '.children-container'];
        const nodes = document.querySelectorAll(selectors.join(','));
        nodes.forEach(n => { try { n.style.display = 'none'; } catch(e) {} });
        // update icons if present
        document.querySelectorAll('[id$="-icon"]').forEach(ic => {
            try { ic.classList.remove('fa-chevron-down'); ic.classList.add('fa-chevron-up'); } catch(e) {}
        });
        // recompute inner widths and refresh canvas
        document.querySelectorAll('.tree-scroll-inner').forEach(inner => { try { adjustInner(inner); } catch(e) {} });
        try { refreshCanvasDebounced(); } catch(e) {}
    } catch (e) { console.warn('collapseAll failed', e); }
}

function centerTree() {
    const container = document.getElementById('treeContainer');
    container.scrollTo({
        left: container.scrollWidth / 2 - container.clientWidth / 2,
        top: 0,
        behavior: 'smooth'
    });
}

function highlightPath() {
    // Implementation for highlight path functionality
    console.log('Highlight path triggered');
}

function exportTree() {
    if (!currentTreeData) return;
    
    const dataStr = JSON.stringify(currentTreeData, null, 2);
    const dataBlob = new Blob([dataStr], {type: 'application/json'});
    
    const link = document.createElement('a');
    link.href = URL.createObjectURL(dataBlob);
    link.download = `my-network-tree-${new Date().toISOString().split('T')[0]}.json`;
    link.click();
}

function showStatistics() {
    if (!currentTreeData) return;
    
    const stats = calculateTreeStatistics(currentTreeData);
    
    alert(`Network Statistics:
• Total Members: ${stats.totalMembers}
• Max Depth: ${stats.maxDepth}
• Total Earnings: ${formatCurrency(stats.totalEarnings)}
• Average per Member: ${formatCurrency(stats.averageEarnings)}`);
}

function calculateTreeStatistics(node) {
    let totalMembers = 1;
    let maxDepth = 0;
    let totalEarnings = node.totalCommissions || 0;
    
    function traverse(n, depth) {
        maxDepth = Math.max(maxDepth, depth);
        if (n.children) {
            n.children.forEach(child => {
                totalMembers++;
                totalEarnings += child.totalCommissions || 0;
                traverse(child, depth + 1);
            });
        }
    }
    
    traverse(node, 0);
    
    return {
        totalMembers,
        maxDepth,
        totalEarnings,
        averageEarnings: totalEarnings / totalMembers
    };
}

async function showUserDetails(userData) {
    // Accept either an object, an encoded JSON dataset, or a numeric id.
    let data = userData;

    // If caller passed an element dataset (stringified / encoded), try to decode
    if (typeof data === 'string') {
        // If it's a number string, treat as id
        if (/^\d+$/.test(data)) {
            data = parseInt(data, 10);
        } else {
            try {
                // Some elements store data-user-data as encodedURIComponent(JSON.stringify(obj))
                const decoded = decodeURIComponent(data);
                if (decoded && (decoded.startsWith('{') || decoded.startsWith('['))) {
                    data = JSON.parse(decoded);
                }
            } catch (e) {
                // leave as-is and proceed
            }
        }
    }

    // If an id was passed, fetch the profile. Prefer username when available
    if (typeof data === 'number') {
        // Cached lookup
        if (window.__profileCache && window.__profileCache[data]) {
            data = window.__profileCache[data];
        } else {
        try {
            const resp = await fetch(`/api/user/profile?user_id=${data}`);
            if (resp.ok) {
                const json = await resp.json();
                if (json.success && json.data) {
                    data = json.data;
                    try { window.__profileCache[data.id] = data; } catch(e) {}
                } else {
                    data = { id: data };
                }
            } else {
                data = { id: data };
            }
            } catch (e) {
            console.error('Failed to fetch user profile by id:', e);
            data = { id: data };
        }
        }
    }

    // If we still don't have phone/country, attempt to fetch full profile and merge
    if (typeof data === 'object' && (!data.phone || !data.country)) {
        try {
            // Prefer querying by username to avoid exposing numeric user_id in requests
            let profileUrl = '';
            if (data.username) {
                profileUrl = `/api/user/profile?username=${encodeURIComponent(data.username)}`;
            } else if (data.id) {
                profileUrl = `/api/user/profile?user_id=${encodeURIComponent(data.id)}`;
            }
            if (profileUrl) {
                const resp = await fetch(profileUrl);
                if (resp.ok) {
                    const json = await resp.json();
                    if (json.success && json.data) {
                        data.phone = data.phone || json.data.phone || '';
                        data.country = data.country || json.data.country || '';
                        try { window.__profileCache[json.data.id] = json.data; } catch(e) {}
                    }
                }
            }
        } catch (e) {
            console.error('Failed to fetch user profile:', e);
        }
    }

    // Fallback ensure data is an object
    data = (typeof data === 'object') ? data : { id: data };

    document.getElementById('modalTitle').textContent = data.fullname || data.username || ('User ' + (data.id || ''));
    document.getElementById('modalContent').innerHTML = `
        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-300">Username:</label>
                    <div class="text-white">${data.username || ''}</div>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-300">Email:</label>
                    <div class="text-white">${data.email || ''}</div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-300">Level:</label>
                    <div class="text-white">${data.level || ''}</div>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-300">Direct Referrals:</label>
                    <div class="text-white">${data.directReferrals || 0}</div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-300">Phone:</label>
                    <div class="text-white">${data.phone ? data.phone : 'N/A'}</div>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-300">Country:</label>
                    <div class="text-white">${data.country ? data.country : 'N/A'}</div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-300">Total Earnings:</label>
                    <div id="modalTotalEarnings" class="text-green-400 font-semibold">${formatCurrency(userData.totalCommissions || 0)}</div>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-300">Monthly Earnings:</label>
                    <div id="modalMonthlyEarnings" class="text-green-400 font-semibold">${formatCurrency(userData.monthlyCommissions || 0)}</div>
                </div>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-300">Join Date:</label>
                <div class="text-white">${userData.joinDate ? new Date(userData.joinDate).toLocaleString() : 'Unknown'}</div>
            </div>
        </div>
    `;
    document.getElementById('userModal').classList.remove('hidden');

    // Fetch estimator for this user and update modal earnings fields
    (async function(){
        try {
            const depthEl = document.getElementById('treeDepth');
            const depth = depthEl ? Number(depthEl.value || 9) : 9;
            if (data) {
                // Prefer using username in query to avoid exposing numeric IDs
                let url = '';
                if (data.username) {
                    url = `/network/earnings?user=${encodeURIComponent(data.username)}&depth=${encodeURIComponent(depth)}`;
                } else if (data.id) {
                    url = `/network/earnings?user_id=${encodeURIComponent(data.id)}&depth=${encodeURIComponent(depth)}`;
                }
                if (!url) return;
                const resp = await fetch(url);
                if (resp.ok) {
                    const json = await resp.json();
                    if (json && json.success && json.data) {
                        const total = Number(json.data.total) || 0;
                        const monthly = Number(json.data.monthly) || 0;
                        const elTotal = document.getElementById('modalTotalEarnings');
                        const elMonthly = document.getElementById('modalMonthlyEarnings');
                        if (elTotal) elTotal.textContent = formatCurrency(total);
                        if (elMonthly) elMonthly.textContent = formatCurrency(monthly);
                        // keep userData in sync for other UI pieces
                        try { userData.totalCommissions = total; userData.monthlyCommissions = monthly; } catch(e){}
                    }
                }
            }
        } catch (e) {
            console.warn('Failed to fetch network earnings estimator', e);
        }
    })();

    // Add escape key listener when modal is opened
    document.addEventListener('keydown', handleEscapeKey);
}

// =====================
// Canvas resize controls (percent-only scale)
// =====================
const CANVAS_WIDTH_KEY = 'ginto_tree_canvas_width'; // legacy: preserved for migration
const CANVAS_SCALE_KEY = 'ginto_tree_canvas_scale';
const DEFAULT_CANVAS_WIDTH = 1200; // fallback if measurement fails (used for base measurements)
const DEFAULT_CANVAS_SCALE = 100; // percent
const MIN_CANVAS_SCALE = 50; // percent
const MAX_CANVAS_SCALE = 200; // percent
const CANVAS_SCALE_STEP = 1; // percent step for slider (finer adjustments)
// When user wants the canvas to be effectively 'infinite' horizontally,
// use a very large minimum spacer width so wrappers can scroll far to the right.
const CANVAS_BROAD_MIN = 200000; // px
// left gutter inside the canvas so nodes are not flush against the edge
const CANVAS_LEFT_GUTTER = 40; // px

const CANVAS_BROAD_KEY = 'ginto_tree_canvas_broad_enabled';

function getInfiniteCanvasEnabled() {
    try {
        const v = localStorage.getItem(CANVAS_BROAD_KEY);
        if (v === null) return true; // default enabled
        return v === '1' || v === 'true';
    } catch (e) { return true; }
}

function setInfiniteCanvasEnabled(val) {
    try { localStorage.setItem(CANVAS_BROAD_KEY, val ? '1' : '0'); } catch(e) {}
}

function getSavedCanvasScale() {
    // prefer explicit scale key
    const s = localStorage.getItem(CANVAS_SCALE_KEY);
    if (s) return Math.max(MIN_CANVAS_SCALE, Math.min(MAX_CANVAS_SCALE, parseInt(s,10) || DEFAULT_CANVAS_SCALE));
    // fall back to legacy width key (best-effort conversion)
    const legacy = localStorage.getItem(CANVAS_WIDTH_KEY);
    if (legacy) {
        const w = parseInt(legacy,10) || DEFAULT_CANVAS_WIDTH;
        try {
            const defaultBase = computeDefaultCanvasWidth() || DEFAULT_CANVAS_WIDTH;
            const pct = Math.round((w / defaultBase) * 100);
            return Math.max(MIN_CANVAS_SCALE, Math.min(MAX_CANVAS_SCALE, pct || DEFAULT_CANVAS_SCALE));
        } catch (e) {
            return DEFAULT_CANVAS_SCALE;
        }
    }
    return DEFAULT_CANVAS_SCALE;
}

// Compatibility helper: return a reasonable base width (px) for legacy code paths
function getSavedCanvasWidth() {
    try {
        const scale = getSavedCanvasScale() || DEFAULT_CANVAS_SCALE;
        const base = computeDefaultCanvasWidth() || DEFAULT_CANVAS_WIDTH;
        return Math.max(400, Math.round((scale / 100) * base));
    } catch (e) { return DEFAULT_CANVAS_WIDTH; }
}

function computeDefaultCanvasWidth() {
    try {
        const treeContainer = document.getElementById('treeContainer');
        let avail = 0;
        if (treeContainer) {
            avail = treeContainer.clientWidth || treeContainer.getBoundingClientRect().width || 0;
        }
        // If treeContainer not measured yet, fallback to viewport width minus sidebar
        if (!avail || avail < 200) {
            const sidebar = document.getElementById('sidebar');
            const sidebarWidth = (sidebar && window.getComputedStyle(sidebar).width) ? parseInt(window.getComputedStyle(sidebar).width,10) : (window.innerWidth >= 1024 ? 256 : 0);
            avail = Math.max(400, window.innerWidth - sidebarWidth - 120);
        }
        // Use 50% of the available area as the default
        const fifty = Math.max(400, Math.round(avail * 0.5));
        return fifty;
    } catch (e) {
        return DEFAULT_CANVAS_WIDTH;
    }
}

function setCanvasScale(percent, opts = {}) {
    if (!percent || isNaN(percent)) return;
    const pct = Math.max(MIN_CANVAS_SCALE, Math.min(MAX_CANVAS_SCALE, Math.round(percent)));
    const persist = (opts.persist === undefined) ? true : !!opts.persist;
    const allowOverflow = !!opts.allowOverflow;
    if (persist) try { localStorage.setItem(CANVAS_SCALE_KEY, String(pct)); } catch(e) {}

    // apply to all views that use inner wrappers
    const applyTo = (el) => {
        // ensure immutable original base width exists
        let original = parseInt(el.dataset.originalBase || el.dataset.baseWidth || 0, 10) || 0;
        if (!original) {
            original = Math.max(400, el.clientWidth || el.getBoundingClientRect().width || DEFAULT_CANVAS_WIDTH);
            el.dataset.originalBase = String(original);
        }
        // Ensure original base is at least the visible wrapper width (so inner doesn't appear too narrow)
        let appliedScale = pct / 100;
        try {
            const wrap = el.closest && el.closest('.tree-scroll-wrap');
            const wrapW = wrap ? (wrap.clientWidth || (wrap.getBoundingClientRect && wrap.getBoundingClientRect().width) || 0) : 0;
            if (wrapW) {
                const minOriginal = Math.ceil(wrapW / (pct/100));
                if (minOriginal > original) {
                    original = minOriginal;
                    el.dataset.originalBase = String(original);
                }
                // Cap the applied scale so the visual width (original * scale) never exceeds the wrapper width
                // unless overflow is explicitly allowed.
                if (!allowOverflow && !getInfiniteCanvasEnabled()) {
                    const maxAllowedScale = wrapW > 0 ? (wrapW / original) : appliedScale;
                    if (maxAllowedScale < appliedScale) {
                        appliedScale = maxAllowedScale;
                    }
                }
            }
        } catch (e) {}

        el.style.minWidth = original + 'px';
        el.style.width = original + 'px';
        // ensure inner is anchored to left and has a left gutter so nodes are accessible
        try { el.style.marginLeft = '0'; } catch(e) {}
        try { el.style.paddingLeft = CANVAS_LEFT_GUTTER + 'px'; } catch(e) {}
        const scale = appliedScale;
        el.style.transformOrigin = '0 0';
        el.style.transform = `scale(${scale})`;
        el.dataset.currentScale = String(scale);
        // If we reduced the requested percent due to wrapper constraints, update persisted value to match actual applied scale
        try {
            const appliedPct = Math.max(MIN_CANVAS_SCALE, Math.min(MAX_CANVAS_SCALE, Math.round(scale * 100)));
            if (opts.persist) try { localStorage.setItem(CANVAS_SCALE_KEY, String(appliedPct)); } catch(e) {}
        } catch (e) {}
    };

    document.querySelectorAll('.tree-scroll-inner').forEach(applyTo);
    document.querySelectorAll('.tree-container-hierarchical, .hierarchical-org-chart, .org-admin-style-chart').forEach(el => {
        applyTo(el);
        const inner = el.querySelector('.tree-scroll-inner');
        if (inner) inner.dataset.baseWidth = el.dataset.originalBase || inner.dataset.baseWidth || '';
    });

    // Ensure each scroll wrapper's scrollable area matches the visual scaled width
    try {
        const scale = pct / 100;
        document.querySelectorAll('.tree-scroll-wrap').forEach(wrap => {
            try {
                // compute the maximum scaled width from any inner wrapper it contains
                let maxScaled = 0;
                const inners = Array.from(wrap.querySelectorAll('.tree-scroll-inner'));
                inners.forEach(inner => {
                    const orig = parseInt(inner.dataset.originalBase || inner.dataset.baseWidth || 0, 10) || 0;
                    const scaled = Math.ceil(orig * scale) + CANVAS_LEFT_GUTTER;
                    if (scaled > maxScaled) maxScaled = scaled;
                });
                if (!maxScaled) {
                    // fallback to wrapper's own width
                    const wrect = wrap.getBoundingClientRect();
                    maxScaled = Math.ceil((wrect.width || DEFAULT_CANVAS_WIDTH) * scale) + CANVAS_LEFT_GUTTER;
                }

                // create or update a hidden spacer element that forces the wrapper's scrollWidth
                let sp = wrap.querySelector('.canvas-scale-spacer');
                if (!sp) {
                    sp = document.createElement('div');
                    sp.className = 'canvas-scale-spacer';
                    sp.style.height = '1px';
                    sp.style.visibility = 'hidden';
                    sp.style.pointerEvents = 'none';
                    wrap.appendChild(sp);
                }
                // Ensure the spacer is at least the wrapper width so scrolling stays usable
                try {
                    const wrapRect = wrap.getBoundingClientRect();
                    const wrapWidth = Math.ceil(wrapRect.width || 0);
                    if (allowOverflow || getInfiniteCanvasEnabled()) {
                        sp.style.width = Math.max(maxScaled, getInfiniteCanvasEnabled() ? CANVAS_BROAD_MIN : 0) + 'px';
                    } else {
                        sp.style.width = Math.max(maxScaled, wrapWidth) + 'px';
                    }
                } catch (e) {
                    sp.style.width = Math.max(maxScaled, getInfiniteCanvasEnabled() ? CANVAS_BROAD_MIN : 0) + 'px';
                }
                // Do not force-scroll wrappers here; preserve user's scroll position.
                // Initial left-align on load is handled elsewhere.
            } catch (e) {}
        });
    } catch (e) {}

    // Trigger redraws so connectors and theme-aware badges update
    try {
        setTimeout(() => {
            try {
                document.querySelectorAll('.hierarchical-tree-container, .tree-container-hierarchical, .org-admin-style-chart').forEach(container => {
                    try { if (typeof addHierarchicalConnections === 'function') addHierarchicalConnections(container); } catch(e) {}
                });
            } catch (e) {}
            try { window.dispatchEvent(new Event('resize')); } catch(e) {}
        }, 60);
    } catch (e) {}

    // Display the chosen scale
    const disp = document.getElementById('canvasWidthDisplay');
    if (disp) {
        disp.textContent = pct + '%';
    }
}

function increaseCanvas() { const cur = getSavedCanvasScale(); setCanvasScale(Math.min(MAX_CANVAS_SCALE, cur + CANVAS_SCALE_STEP)); }
function decreaseCanvas() { const cur = getSavedCanvasScale(); setCanvasScale(Math.max(MIN_CANVAS_SCALE, cur - CANVAS_SCALE_STEP)); }
function resetCanvas() { try { localStorage.removeItem(CANVAS_SCALE_KEY); } catch(e) {} setCanvasScale(DEFAULT_CANVAS_SCALE); }

function initResizeControls() {
    // initialize control state (scale-based)
    const savedScale = getSavedCanvasScale();
    setCanvasScale(savedScale);

    const toggle = document.getElementById('resizeCanvasToggle');
    const controls = document.getElementById('resizeControls');
    const inc = document.getElementById('increaseCanvas');
    const dec = document.getElementById('decreaseCanvas');
    const reset = document.getElementById('resetCanvas');

    if (toggle && controls) {
        toggle.addEventListener('click', function(e){
            e.stopPropagation();
            controls.classList.toggle('hidden');
        });
        // close when clicking outside
        document.addEventListener('click', function(){ if (!controls.classList.contains('hidden')) controls.classList.add('hidden'); });
        controls.addEventListener('click', function(e){ e.stopPropagation(); });

        const slider = document.getElementById('canvasSlider');
        if (slider) {
            // switch slider semantics to percent-only
            slider.min = String(MIN_CANVAS_SCALE);
            slider.max = String(MAX_CANVAS_SCALE);
            slider.step = String(CANVAS_SCALE_STEP);
            slider.value = String(savedScale);
            // mark bound to avoid duplicate listeners
            if (!slider.dataset.bound) {
                slider.addEventListener('input', function(e){
                    const v = parseInt(e.target.value, 10);
                    setCanvasScale(v);
                });
                // also update on change for accessibility
                slider.addEventListener('change', function(e){
                    const v = parseInt(e.target.value, 10);
                    setCanvasScale(v);
                });
                slider.dataset.bound = '1';
            }
            // Infinite canvas toggle
            const toggleInfinite = document.getElementById('toggleInfiniteCanvas');
            if (toggleInfinite && !toggleInfinite.dataset.bound) {
                try { toggleInfinite.checked = getInfiniteCanvasEnabled(); } catch(e) {}
                toggleInfinite.addEventListener('change', function(e){
                    try { setInfiniteCanvasEnabled(!!e.target.checked); } catch(err){}
                    try { refreshCanvasDebounced(); } catch(e) {}
                });
                toggleInfinite.dataset.bound = '1';
            }
        }

    // Also ensure the floating slider (if present) is wired even when legacy toggle/controls are not in DOM
    try {
        const floatingSlider = document.getElementById('canvasSlider');
        if (floatingSlider && !floatingSlider.dataset.floatingBound) {
            floatingSlider.min = String(MIN_CANVAS_SCALE);
            floatingSlider.max = String(MAX_CANVAS_SCALE);
            floatingSlider.step = String(CANVAS_SCALE_STEP);
            floatingSlider.value = String(getSavedCanvasScale());
            floatingSlider.addEventListener('input', function(e){ setCanvasScale(parseInt(e.target.value,10)); });
            floatingSlider.addEventListener('change', function(e){ setCanvasScale(parseInt(e.target.value,10)); });
            floatingSlider.dataset.floatingBound = '1';
            const floatToggle = document.getElementById('toggleInfiniteCanvas');
            if (floatToggle && !floatToggle.dataset.floatingBound) {
                try { floatToggle.checked = getInfiniteCanvasEnabled(); } catch(e) {}
                floatToggle.addEventListener('change', function(e){ setInfiniteCanvasEnabled(!!e.target.checked); try{ refreshCanvasDebounced(); }catch(e){} });
                floatToggle.dataset.floatingBound = '1';
            }
        }
    } catch(e) {}
    }

    if (inc) inc.addEventListener('click', function(){ increaseCanvas(); const s = document.getElementById('canvasSlider'); if (s) s.value = String(getSavedCanvasScale()); });
    if (dec) dec.addEventListener('click', function(){ decreaseCanvas(); const s = document.getElementById('canvasSlider'); if (s) s.value = String(getSavedCanvasScale()); });
    if (reset) reset.addEventListener('click', function(){ resetCanvas(); const s = document.getElementById('canvasSlider'); if (s) s.value = String(getSavedCanvasScale()); });

    // Ensure left-aligned initial view: reset wrapper scroll positions after layout settles
    try {
        setTimeout(() => {
            document.querySelectorAll('.tree-scroll-wrap').forEach(w => {
                try { w.scrollLeft = 0; } catch(e) {}
            });
        }, 80);
    } catch (e) {}

    // Dynamically measure and adjust canvas inner width when content changes
    // Reuse global debounce helper

    function computeRequiredWidth(innerEl, minW){
        try {
            if (!innerEl) return minW;
            const innerRect = innerEl.getBoundingClientRect();
            const candidates = innerEl.querySelectorAll('*');
            let min = Infinity, max = -Infinity;
                // If the inner element is scaled via dataset.currentScale, convert measured positions
                const scale = parseFloat(innerEl.dataset.currentScale || '1') || 1;
                candidates.forEach(n => {
                    const cname = (n.className || '').toString();
                    if (!/node|card|member|item|circle|org|level|user|box|node-item|hierarchical|org-chart/i.test(cname)) return;
                    const rect = n.getBoundingClientRect();
                    // convert to unscaled coordinates relative to innerEl
                    const left = (rect.left - innerRect.left) / scale;
                    const right = (rect.right - innerRect.left) / scale;
                    if (left < min) min = left;
                    if (right > max) max = right;
                });
            if (min === Infinity) return Math.max(minW, innerEl.scrollWidth || minW);
            const padding = 120; // give some breathing room
            return Math.max(minW, Math.ceil(max - min + padding));
        } catch (e) { return minW; }
    }

    const adjustInner = debounce(function(innerEl){
        const minW = getSavedCanvasWidth();
        const required = computeRequiredWidth(innerEl, minW);
        if (required) {
            innerEl.style.width = required + 'px';
            innerEl.style.minWidth = Math.max(required, minW) + 'px';
            try { refreshCanvasDebounced(); } catch(e) {}
        }
    }, 100);

// Debounced refresh to reapply spacers/scale when content changes
const refreshCanvasDebounced = (function(){ let t; return function(){ clearTimeout(t); t = setTimeout(()=>{
    try { setCanvasScale(getSavedCanvasScale(), { persist: false }); } catch(e) {}
}, 120); }; })();

    

    // helper: attach observers/handlers to a given inner wrapper
    function attachInnerObservers(inner) {
        if (!inner) return;
        try {
            // initial adjustment
            adjustInner(inner);

            // observe content changes inside this inner wrapper
            const mo = new MutationObserver(() => adjustInner(inner));
            mo.observe(inner, { childList: true, subtree: true, attributes: true });

            // adjust on window resize
                const onResize = () => adjustInner(inner);
            // Register inner resize with global layout observers
            try { window.__layoutResizeObservers = window.__layoutResizeObservers || []; if (!window.__layoutResizeObservers.includes(onResize)) window.__layoutResizeObservers.push(onResize); } catch (e) {}

            // remember to apply saved canvas width as well
            // apply saved percent-based scale (compat)
            const savedScale = getSavedCanvasScale();
            if (savedScale) setCanvasScale(savedScale);
        } catch (e) {
            // ignore observer failures
        }
    }

    // Attach to any existing inner wrappers
    document.querySelectorAll('.tree-scroll-inner').forEach(inner => attachInnerObservers(inner));

    // Add drag-to-scroll support for tree-scroll-wrap so users can pan horizontally by dragging
    function attachDragToWrap(wrap) {
        if (!wrap || wrap.dataset.dragBound) return;
        let isDown = false; let startX = 0; let scrollLeft = 0;
        const onMouseDown = (e) => {
            isDown = true;
            wrap.classList.add('dragging');
            startX = (e.pageX || (e.touches && e.touches[0] && e.touches[0].pageX)) - wrap.offsetLeft;
            scrollLeft = wrap.scrollLeft;
            e.preventDefault();
        };
        const onMouseMove = (e) => {
            if (!isDown) return;
            const x = (e.pageX || (e.touches && e.touches[0] && e.touches[0].pageX)) - wrap.offsetLeft;
            const walk = (x - startX) * 1; // scroll-fast multiplier
            wrap.scrollLeft = scrollLeft - walk;
            e.preventDefault();
        };
        const onMouseUp = () => { isDown = false; wrap.classList.remove('dragging'); };

        wrap.addEventListener('mousedown', onMouseDown);
        wrap.addEventListener('touchstart', onMouseDown, { passive: false });
        window.addEventListener('mousemove', onMouseMove);
        window.addEventListener('touchmove', onMouseMove, { passive: false });
        window.addEventListener('mouseup', onMouseUp);
        window.addEventListener('touchend', onMouseUp);
        wrap.dataset.dragBound = '1';
    }

    // attach drag to existing wraps
    document.querySelectorAll('.tree-scroll-wrap').forEach(w => attachDragToWrap(w));

    // Also observe the tree container for newly inserted inner wrappers (render inserts them async)
    try {
        const treeContainer = document.getElementById('treeContainer');
        if (treeContainer) {
            const outerObserver = new MutationObserver(muts => {
                muts.forEach(m => {
                    if (m.addedNodes && m.addedNodes.length) {
                        m.addedNodes.forEach(node => {
                            if (!node) return;
                            if (node.nodeType !== 1) return;
                            // if the added node itself is an inner wrapper
                            if (node.classList && node.classList.contains('tree-scroll-inner')) {
                                attachInnerObservers(node);
                                try { refreshCanvasDebounced(); } catch(e) {}
                            }
                            // or if it contains an inner wrapper deeper
                            const found = node.querySelector && node.querySelector('.tree-scroll-inner');
                            if (found) {
                                attachInnerObservers(found);
                                try { refreshCanvasDebounced(); } catch(e) {}
                            }
                        });
                    }
                });
            });
            outerObserver.observe(treeContainer, { childList: true, subtree: true });
        }
    } catch (e) {}

    // Wire Auto-Fit button: measure actual content and set canvas accordingly
    const autoBtn = document.getElementById('autoFitCanvas');
    function runAutoFit(e) {
        try { if (e && e.stopPropagation) e.stopPropagation(); } catch (ex) {}
        // Auto-Fit with retries to allow layout to settle
        let attempts = 0, maxAttempts = 6;
        function runOnce() {
            attempts++;
            requestAnimationFrame(() => {
                try {
                    // Determine content bounds across all inners
                    let contentW = 0, contentH = 0;
                    const inners = Array.from(document.querySelectorAll('.tree-scroll-inner'));
                    inners.forEach(inner => {
                        try {
                            const rect = inner.getBoundingClientRect();
                            const reqW = computeRequiredWidth(inner, getSavedCanvasWidth()) || Math.max(getSavedCanvasWidth(), inner.scrollWidth || rect.width || 0);
                            const reqH = inner.scrollHeight || rect.height || 0;
                            if (reqW > contentW) contentW = reqW;
                            if (reqH > contentH) contentH = reqH;
                        } catch (e) {}
                    });

                    // Fallback: measure hierarchical/container wrappers if no inner wrappers
                    if (!contentW) {
                        document.querySelectorAll('.tree-container-hierarchical, .hierarchical-org-chart, .org-admin-style-chart').forEach(el => {
                            try {
                                const rect = el.getBoundingClientRect();
                                const w = el.scrollWidth || rect.width || 0;
                                const h = el.scrollHeight || rect.height || 0;
                                if (w > contentW) contentW = w;
                                if (h > contentH) contentH = h;
                            } catch (e) {}
                        });
                    }

                    if (!contentW) {
                        if (attempts < maxAttempts) setTimeout(runOnce, 120);
                        return; // nothing to fit yet
                    }

                    // Choose the primary wrapper to compute available viewport
                    const wrap = document.querySelector('.tree-scroll-wrap');
                    const availW = wrap ? (wrap.clientWidth || wrap.getBoundingClientRect().width) : (window.innerWidth - 120);
                    const availH = wrap ? (wrap.clientHeight || wrap.getBoundingClientRect().height) : (window.innerHeight - 200);

                    // Compute scale to fit both width and height into available viewport
                    const marginFactor = 0.98; // leave small breathing room
                    const scaleW = availW / contentW;
                    const scaleH = availH / contentH || 1;
                    const fittedScale = Math.min(1, scaleW * marginFactor, scaleH * marginFactor);

                    // Apply base width to content (so layout metrics remain correct)
                    const baseWidth = Math.max(Math.round(contentW), getSavedCanvasWidth());
                    try {
                        document.querySelectorAll('.tree-scroll-inner, .tree-container-hierarchical, .hierarchical-org-chart, .org-admin-style-chart').forEach(el => {
                                            el.dataset.originalBase = String(baseWidth);
                                            el.style.minWidth = baseWidth + 'px';
                                            el.style.width = baseWidth + 'px';
                                        });
                                    } catch(e) {}

                    // For Auto-Fit we want to show the whole tree; allow overflow so the container spacer
                    // will be expanded to the full scaled content width and scrolling will work.
                    const desiredPct = Math.max(MIN_CANVAS_SCALE, Math.min(MAX_CANVAS_SCALE, Math.round(fittedScale * 100)));
                    try {
                        // Apply the computed percent but allow overflow so user can scroll to see everything
                        setCanvasScale(desiredPct, { persist: false, allowOverflow: true });
                    } catch (e) {
                        // fallback: if setCanvasScale not available for some reason, apply transform locally
                        const appliedScale = fittedScale;
                        document.querySelectorAll('.tree-scroll-inner').forEach(inner => {
                            try { inner.style.transform = `scale(${appliedScale})`; inner.dataset.currentScale = String(appliedScale); } catch(e){}
                        });
                    }
                    // Update slider display to the desired percent (non-persist)
                    const s = document.getElementById('canvasSlider');
                    if (s) s.value = String(desiredPct);

                    // Schedule connector redraw and theme reflow so connectors/colors align with the transformed layout
                    try {
                        setTimeout(() => {
                            try {
                                document.querySelectorAll('.hierarchical-tree-container, .tree-container-hierarchical, .org-admin-style-chart').forEach(container => {
                                    try { if (typeof addHierarchicalConnections === 'function') addHierarchicalConnections(container); } catch(e) {}
                                });
                            } catch (e) {}
                            try { window.dispatchEvent(new Event('resize')); } catch(e) {}
                        }, 80);
                    } catch (e) {}

                    // After Auto-Fit completes and spacer sizes update, ensure wrapper scroll position
                    // is set so left-most content is reachable. Delay slightly to allow DOM updates.
                    try {
                        setTimeout(() => {
                            document.querySelectorAll('.tree-scroll-wrap').forEach(wrap => {
                                try {
                                    // Prefer left-align after Auto-Fit so left nodes are visible
                                    if (wrap && wrap.scrollWidth > (wrap.clientWidth || 0)) {
                                        try { wrap.scrollLeft = 0; } catch(e) { /* ignore */ }
                                    }
                                } catch (e) {}
                            });
                        }, 160);
                    } catch (e) {}

                    // If measurements may still be settling, retry once
                    if (attempts < maxAttempts) setTimeout(runOnce, 150);
                } catch (err) {
                    if (attempts < maxAttempts) setTimeout(runOnce, 150);
                }
            });
        }

        runOnce();
    }
    // attach to button if present
    if (autoBtn) autoBtn.addEventListener('click', runAutoFit);
    // expose globally as fallback
    try { window.runAutoFit = runAutoFit; } catch(e) {}
    // delegated fallback: capture clicks on any element with id autoFitCanvas
    document.addEventListener('click', function(e){
        try {
            const el = e.target && (e.target.closest ? e.target.closest('#autoFitCanvas') : (e.target.id === 'autoFitCanvas' ? e.target : null));
            if (el) runAutoFit(e);
        } catch(err) {}
    }, { passive: true });

    // zoom feature removed

    // Ensure main content is offset if sidebar is visible to avoid overlap
    try {
        const main = document.getElementById('mainContent') || document.getElementById('mainContentWrapper');
        const sidebar = document.getElementById('sidebar');
        if (main && sidebar) {
            const applyMargin = () => {
                if (window.innerWidth >= 1024) {
                    // desktop: respect collapsed state
                    if (sidebar.classList.contains('collapsed')) {
                        main.style.marginLeft = '4.5rem';
                    } else {
                        main.style.marginLeft = '16rem';
                    }
                } else {
                    main.style.marginLeft = '';
                }
            };
            applyMargin();
            try { window.__layoutResizeObservers = window.__layoutResizeObservers || []; if (!window.__layoutResizeObservers.includes(applyMargin)) window.__layoutResizeObservers.push(applyMargin); } catch (e) {}
        }
    } catch (e) {}

    // Prevent page-level horizontal scroll (force horizontal scrolling to the canvas)
    try { document.getElementById('mainContent').style.overflowX = 'hidden'; } catch(e) {}

    // Adjust tree scroll wrapper height so vertical scrolling remains inside the canvas
    function setTreeWrapperHeight() {
        try {
            const header = document.querySelector('.bg-white') || document.querySelector('header');
            const headerH = header ? (header.getBoundingClientRect().height || 72) : 72;
            const controlsArea = document.querySelector('.bg-white.dark\:bg-gray-800.rounded-lg') || document.querySelector('.p-6');
            const controlsH = controlsArea ? (controlsArea.getBoundingClientRect().height || 260) : 260;
            const footerH = 40; // safe footer/padding estimate
            const avail = Math.max(300, window.innerHeight - headerH - controlsH - footerH);
            document.querySelectorAll('.tree-scroll-wrap').forEach(w => {
                w.style.maxHeight = avail + 'px';
                w.style.height = avail + 'px';
                w.style.overflow = 'auto';
            });
        } catch (e) {}
    }
    const debSetTreeHeight = (function(){ let t; return function(){ clearTimeout(t); t = setTimeout(setTreeWrapperHeight, 120); }; })();
    setTreeWrapperHeight();
    try { window.__layoutResizeObservers = window.__layoutResizeObservers || []; if (!window.__layoutResizeObservers.includes(debSetTreeHeight)) window.__layoutResizeObservers.push(debSetTreeHeight); } catch (e) {}

        // Wire zoom buttons and enable canvas panning
    try {
        // No keyboard shortcuts — do not override native browser zoom.
        // Enable drag-to-pan and wheel-to-horizontal scroll inside canvas
        function enableCanvasPanning() {
            document.querySelectorAll('.tree-scroll-wrap').forEach(wrapper => {
                if (wrapper.__panningAttached) return;
                wrapper.__panningAttached = true;
                let isDown = false, startX = 0, scrollLeft = 0;

                wrapper.addEventListener('mousedown', (ev) => {
                    if (ev.button !== 0) return;
                    isDown = true;
                    wrapper.classList.add('dragging');
                    startX = ev.pageX - wrapper.offsetLeft;
                    scrollLeft = wrapper.scrollLeft;
                });

                window.addEventListener('mouseup', () => { if (!isDown) return; isDown = false; wrapper.classList.remove('dragging'); });

                wrapper.addEventListener('mousemove', (ev) => {
                    if (!isDown) return;
                    ev.preventDefault();
                    const x = ev.pageX - wrapper.offsetLeft;
                    const walk = (x - startX) * 1.0;
                    wrapper.scrollLeft = scrollLeft - walk;
                });

                wrapper.addEventListener('wheel', (ev) => {
                    if (ev.shiftKey || Math.abs(ev.deltaX) > Math.abs(ev.deltaY)) return;
                    ev.preventDefault();
                    wrapper.scrollLeft += ev.deltaY;
                }, { passive: false });

                // touch
                wrapper.addEventListener('touchstart', (ev) => {
                    if (!ev.touches || !ev.touches.length) return;
                    startX = ev.touches[0].pageX - wrapper.offsetLeft;
                    scrollLeft = wrapper.scrollLeft;
                }, { passive: true });

                wrapper.addEventListener('touchmove', (ev) => {
                    if (!ev.touches || !ev.touches.length) return;
                    const x = ev.touches[0].pageX - wrapper.offsetLeft;
                    const walk = (x - startX) * 1.0;
                    wrapper.scrollLeft = scrollLeft - walk;
                }, { passive: true });
            });
        }

        enableCanvasPanning();
        try { const tc = document.getElementById('treeContainer'); if (tc) { const inst = new MutationObserver(() => enableCanvasPanning()); inst.observe(tc, { childList: true, subtree: true }); } } catch (e) {}
    } catch (e) { console.warn('Zoom/panning setup failed', e); }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initResizeControls);
} else {
    // DOM already ready
    initResizeControls();
}

// Global fallback: catch slider input events at document level in case the element is dynamically moved
try {
    document.addEventListener('input', function(e){
        try {
            if (!e || !e.target) return;
            if (e.target.id === 'canvasSlider') {
                const v = parseInt(e.target.value,10);
                if (!isNaN(v)) setCanvasScale(v);
            }
        } catch(err){}
    }, { passive: true });
} catch(e) {}

function closeModal() {
    document.getElementById('userModal').classList.add('hidden');
    // Remove escape key listener when modal is closed
    document.removeEventListener('keydown', handleEscapeKey);
}

function closeModalOnBackdrop(event) {
    // Only close if clicked on the backdrop (not on the modal content)
    if (event.target === event.currentTarget) {
        closeModal();
    }
}

function handleEscapeKey(event) {
    // Close modal on Escape key press
    if (event.key === 'Escape') {
        closeModal();
    }
}

function setupSearch() {
    const searchInput = document.getElementById('userSearch');
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length >= 2) {
            searchTimeout = setTimeout(() => performSearch(query), 300);
        } else {
            hideSearchResults();
        }
    });
    
    // Hide search results when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#userSearch') && !e.target.closest('#searchResults')) {
            hideSearchResults();
        }
    });
}

async function performSearch(query) {
    try {
        // simple client-side cache with a short lifetime
        const cacheKey = String(query).trim().toLowerCase();
        if (window.__searchCache && window.__searchCache[cacheKey]) {
            showSearchResults(window.__searchCache[cacheKey]);
            return;
        }
        const response = await fetch(`/api/user/network-search?q=${encodeURIComponent(query)}&user_id=<?= $current_user_id ?>`);
        const data = await response.json();
        
        if (data.success) {
            try { window.__searchCache[cacheKey] = data.users; } catch(e) {}
            showSearchResults(data.users);
        }
    } catch (error) {
        console.error('Search error:', error);
    }
}

function showSearchResults(users) {
    const resultsContainer = document.getElementById('searchResults');
    
    if (users.length === 0) {
        resultsContainer.innerHTML = '<div class="p-3 text-gray-500">No users found</div>';
    } else {
        resultsContainer.innerHTML = users.map(user => `
            <div class="p-3 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer border-b border-gray-200 dark:border-gray-600 last:border-b-0"
                 onclick="focusOnUser(${user.id})">
                <div class="font-medium text-gray-900 dark:text-white">${user.fullname || user.username}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">${user.email} • Level ${user.ginto_level}</div>
            </div>
        `).join('');
    }
    
    resultsContainer.classList.remove('hidden');
}

function hideSearchResults() {
    document.getElementById('searchResults').classList.add('hidden');
}

function focusOnUser(userId) {
    hideSearchResults();
    document.getElementById('userSearch').value = '';
    
    // Find and highlight the user in the tree
    // Use delegated highlight - clear previous highlight once centrally
    document.querySelectorAll('.highlight').forEach(el => el.classList.remove('highlight'));
    
    // Add highlight class (you can define this in CSS)
    // This is a simplified implementation
    console.log('Focusing on user:', userId);
}

// Global delegated click handler for user nodes (single listener)
document.addEventListener('click', function(e) {
    try {
        const nodeEl = e.target.closest('[data-user-id]');
        if (!nodeEl) return;
        // ensure the click happened inside the tree container
        const treeContainer = document.getElementById('treeContainer');
        if (!treeContainer || !treeContainer.contains(nodeEl)) return;
        const uid = nodeEl.dataset.userId || nodeEl.getAttribute('data-user-id');
        const userData = uid && window.__nodeMap && window.__nodeMap[uid] ? window.__nodeMap[uid] : null;
        showUserDetails(userData || uid);
    } catch (err) {
        // just ignore delegation errors
    }
}, { passive: true });

// Theme Management
function initializeTheme() {
    try { if (window.themeManager && typeof window.themeManager.init === 'function') { window.themeManager.init(); return; } } catch (_) {}
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.documentElement.classList.add('dark');
        updateThemeButton(true);
    } else if (savedTheme === 'light') {
        document.documentElement.classList.remove('dark');
        updateThemeButton(false);
    } else {
        // Auto-detect system preference
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (prefersDark) {
            document.documentElement.classList.add('dark');
            try { localStorage.setItem('theme', 'dark'); } catch (_) {}
        } else {
            document.documentElement.classList.remove('dark');
            try { localStorage.setItem('theme', 'light'); } catch (_) {}
        }
        updateThemeButton(prefersDark);
    }
}

function toggleTheme() {
    try { window.themeManager && window.themeManager.toggle(); return; } catch (_) {}
    const isDark = document.documentElement.classList.contains('dark');
    if (isDark) {
        document.documentElement.classList.remove('dark');
        try { localStorage.setItem('theme', 'light'); } catch (e) {}
        updateThemeButton(false);
    } else {
        document.documentElement.classList.add('dark');
        try { localStorage.setItem('theme', 'dark'); } catch (e) {}
        updateThemeButton(true);
    }
}

function updateThemeButton(isDark) {
    const themeIcon = document.getElementById('themeIcon');
    const themeText = document.getElementById('themeText');
    
    if (isDark) {
        themeIcon.className = 'fas fa-sun';
        themeText.textContent = 'Light';
    } else {
        themeIcon.className = 'fas fa-moon';
        themeText.textContent = 'Dark';
    }
}
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>


<script>
// Show Commissions toggle: toggles visible per-level badges
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('showCommissionsBtn');
    if (!btn) return;
    // Ensure the commission modal is hidden by default, but keep per-level badges visible
    const commissionModalSel = '.commission-summary-panel';
    const commissionBackdropSel = '.commission-modal-backdrop';
    try {
        document.body.classList.remove('show-commissions');
        // hide only the modal and its backdrop on load
        document.querySelectorAll(commissionModalSel).forEach(el => el.style.display = 'none');
        document.querySelectorAll(commissionBackdropSel).forEach(el => el.style.display = 'none');
        // Ensure per-level badges are visible by default
        document.querySelectorAll('.level-summary-badge').forEach(b => b.style.display = '');
    } catch (e) {
        // ignore if elements not present yet
    }

    btn.addEventListener('click', function() {
        const on = document.body.classList.toggle('show-commissions');
        const panel = document.querySelector('.commission-summary-panel');
        const backdrop = document.querySelector('.commission-modal-backdrop');
        if (panel) panel.style.display = on ? '' : 'none';
        if (backdrop) backdrop.style.display = on ? '' : 'none';

        // Manage Escape key handler for the commission modal
        try {
            if (on) {
                // Show: attach escape handler and focus close button
                if (panel) {
                    const closeBtn = panel.querySelector('.close-commission-panel');
                    if (closeBtn && typeof closeBtn.focus === 'function') closeBtn.focus();
                }
                document._commissionEscapeHandler = function(e) {
                    if (e.key === 'Escape') {
                        try { if (panel) panel.style.display = 'none'; } catch (err) {}
                        try { if (backdrop) backdrop.style.display = 'none'; } catch (err) {}
                        document.body.classList.remove('show-commissions');
                        try { document.removeEventListener('keydown', document._commissionEscapeHandler); } catch (err) {}
                        document._commissionEscapeHandler = null;
                    }
                };
                document.addEventListener('keydown', document._commissionEscapeHandler);
            } else {
                // Hide: remove escape handler
                if (document._commissionEscapeHandler) {
                    document.removeEventListener('keydown', document._commissionEscapeHandler);
                    document._commissionEscapeHandler = null;
                }
            }
        } catch (e) {}
    });
});
</script>
<script>
// Move the page-level Show Commissions button into the Network Tree canvas header
document.addEventListener('DOMContentLoaded', function() {
    try {
        const pageBtn = document.getElementById('showCommissionsBtn');
        if (!pageBtn) return;
        // Find the H3 titled 'Network Tree Structure'
        const h3s = Array.from(document.querySelectorAll('h3'));
        let targetHeader = null;
        for (const h of h3s) {
            if ((h.textContent || '').trim().includes('Network Tree Structure')) {
                targetHeader = h.closest('.p-6.border-b') || h.parentElement;
                break;
            }
        }
        if (!targetHeader) return;
        // Ensure header is position:relative so absolute button sits top-right
        targetHeader.style.position = targetHeader.style.position || 'relative';
        // Style and move the button
        pageBtn.style.position = 'absolute';
        pageBtn.style.right = '18px';
        pageBtn.style.top = '18px';
        pageBtn.classList.remove('ml-4');
        targetHeader.appendChild(pageBtn);
    } catch (e) {
        console.warn('Failed to move Show Commissions button into canvas header', e);
    }
});
// Ensure theme toggle has a reliable click handler and is interactive
document.addEventListener('DOMContentLoaded', function() {
    try {
        const themeBtn = document.getElementById('themeToggle');
        if (!themeBtn) return;
        // Make sure it's clickable and above overlays
        themeBtn.style.pointerEvents = 'auto';
        themeBtn.style.zIndex = '20020';
        themeBtn.style.position = themeBtn.style.position || 'relative';
        // Attach handler (defensive) to ensure toggleTheme is called
        themeBtn.addEventListener('click', function(e) {
            try { e.preventDefault(); toggleTheme(); } catch (err) { console.warn('theme toggle failed', err); }
        });
    } catch (e) {
        // ignore
    }
});
</script>

<!-- Commission UI enhancement: compute per-level earnings from node.totalCommissions -->
<script>
(function(){
    try {
        var defaultRates = [0.05,0.04,0.03,0.02,0.01,0.005,0.0025,0.0025,0.00];
        var serverLevels = window.__serverLevels || [];
        var currentLevelId = window.__currentLevelId || (serverLevels[0] && serverLevels[0].id) || null;

        function parseRatesFromLevel(levelObj){
            if (!levelObj || !levelObj.commission_rate_json) return null;
            try { var r = JSON.parse(levelObj.commission_rate_json); return Array.isArray(r) ? r : null; } catch(e){ return null; }
        }

        var commissionRates = null;
        if (Array.isArray(serverLevels) && serverLevels.length){
            var found = serverLevels.find(function(x){ return String(x.id) === String(currentLevelId); }) || serverLevels[0];
            commissionRates = parseRatesFromLevel(found) || parseRatesFromLevel(serverLevels[0]) || null;
        }
        if (!commissionRates) commissionRates = defaultRates.slice();
        commissionRates = commissionRates.map(function(r){ var n = Number(r)||0; return (n>1) ? n/100.0 : n; });

        function fmtPercent(rate){
            try {
                var n = Number(rate) || 0;
                // Accept either a decimal (0.05) or a percent (5) and normalize to integer percent
                if (n > 1) return Math.round(n) + '%';
                return Math.round(n * 100) + '%';
            } catch (e) { return '0%'; }
        }
        function fmtMoney(v){ try { if (typeof formatCurrency === 'function') return formatCurrency(v); } catch(e) {} var n = Number(v)||0; return n.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }

        function computeLevelTotals(root){ var totals = []; if (!root) return totals; (function trav(node, depth){ if (!node) return; totals[depth] = (totals[depth] || 0) + (Number(node.totalCommissions) || 0); if (Array.isArray(node.children)) node.children.forEach(function(c){ trav(c, depth+1); }); })(root, 0); return totals; }

        function renderCommissionPanel(rootNode, containerSelector){
            try {
                // If a global commission modal/panel exists, avoid rendering a second independent
                // panel. The global panel created by the other code path will be used instead.
                if (document.querySelector('.commission-summary-panel')) return null;

                var container = document.querySelector(containerSelector || '#commission-panel-body');
                if (!container) return null;

                // Totals aggregated from node.totalCommissions per depth (depth 0 == root)
                var totals = computeLevelTotals(rootNode || window.currentTreeData || window.__treeData || null) || [];

                // Also compute member counts per depth so we can estimate from package amount when ledger totals are empty
                function computeMemberCounts(root){ var counts = []; if(!root) return counts; (function trav(n, d){ counts[d] = (counts[d]||0) + 1; if(Array.isArray(n.children)) n.children.forEach(function(c){ trav(c, d+1); }); })(root,0); return counts; }
                var memberCounts = computeMemberCounts(rootNode || window.currentTreeData || window.__treeData || null) || [];

                var defaultPackageAmount = 10000; // P10,000 fallback when no ledger data

                // We want to display Level 1 as the root's direct referrals (depth 1).
                var maxLevels = Math.max(commissionRates.length, Math.max(0, totals.length-1));

                // Render simplified commission table (only rates and commission amounts)
                var header = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">'
                    + '<div style="font-weight:700">Commission Breakdown</div>'
                    + '<div style="font-size:12px;color:#666">Base price used for estimates: ' + fmtMoney(defaultPackageAmount) + '</div>'
                    + '</div>';

                var tableHead = '<table style="width:100%;border-collapse:collapse;margin-bottom:8px">'
                    + '<thead>'
                    + '<tr style="text-align:left;color:#333;background:rgba(0,0,0,0.03)">'
                    + '<th style="padding:8px">Level</th>'
                    + '<th style="padding:8px">Rate</th>'
                    + '<th style="padding:8px;text-align:right">Commission</th>'
                    + '<th style="padding:8px;text-align:right">Per member</th>'
                    + '<th style="padding:8px;text-align:right">Cumulative</th>'
                    + '</tr></thead><tbody>';

                var rows = '';
                var cumulative = 0;

                for (var lvl = 1; lvl <= maxLevels; lvl++){
                    var rate = commissionRates[lvl-1] || 0;
                    var baseDepth = (lvl === 8) ? 9 : lvl;
                    var depthTotal = totals[baseDepth] || 0;
                    var membersAtLevel = memberCounts[baseDepth] || 0;

                    var baseAmount = 0;
                    if (depthTotal > 0) {
                        baseAmount = depthTotal;
                    } else if (membersAtLevel > 0) {
                        baseAmount = membersAtLevel * defaultPackageAmount;
                    }

                    var computed = baseAmount * (rate || 0);
                    var perMember = (membersAtLevel > 0) ? (computed / membersAtLevel) : null;
                    cumulative += computed;

                    rows += '<tr style="border-bottom:1px solid rgba(0,0,0,0.04)">'
                        + '<td style="padding:8px">' + lvl + '</td>'
                        + '<td style="padding:8px">' + fmtPercent(rate) + '</td>'
                        + '<td style="padding:8px;text-align:right">' + fmtMoney(computed) + '</td>'
                        + '<td style="padding:8px;text-align:right">' + (perMember === null ? 'n/a' : fmtMoney(perMember)) + '</td>'
                        + '<td style="padding:8px;text-align:right">' + fmtMoney(cumulative) + '</td>'
                        + '</tr>';
                }

                var tableFoot = '</tbody></table>';
                var summary = '<div style="margin-top:6px;font-weight:700">Total payout: ' + fmtMoney(cumulative) + '</div>';

                var html = header + tableHead + rows + tableFoot + summary;
                container.innerHTML = html;
                return { totals: totals, memberCounts: memberCounts, html: html };
            } catch(e){ console.error('renderCommissionPanel error', e); return null; }
        }

        window.renderCommissionPanelFromTree = renderCommissionPanel;

        document.addEventListener('DOMContentLoaded', function(){ try { renderCommissionPanel(window.currentTreeData || window.__treeData || null); } catch(e){} });

        (function observeTree(){
            var treeContainer = document.getElementById('treeContainer') || document.querySelector('.tree-container-hierarchical') || document.querySelector('.tree-container-network');
            if (!treeContainer || typeof MutationObserver === 'undefined') return;
            var mo = new MutationObserver(function(){ try { renderCommissionPanel(window.currentTreeData || window.__treeData || null); } catch(e){} });
            mo.observe(treeContainer, { childList: true, subtree: true, attributes: false });
        })();

    } catch(e){ console.error('Commission UI enhancement failed', e); }
})();
</script>


<!-- Appended: prefer outer viewport scrollbar, hide inner horizontal scrollbars -->
<style id="viewport-scroll-fix">
/* Ensure inner containers don't create a horizontal scrollbar. Outer .tree-viewport will be the single horizontal scroller */
#treeContainer {
    overflow-x: visible !important; /* let the outer .tree-viewport handle horizontal scroll */
    overflow-y: visible;
    position: relative;
    -webkit-overflow-scrolling: touch;
    -ms-overflow-style: none; /* IE/Edge */
    scrollbar-width: none; /* Firefox */
}
#treeContainer::-webkit-scrollbar { display: none !important; height: 0; }

.tree-container-hierarchical {
    overflow-x: visible !important;
    overflow-y: auto;
    max-height: 80vh;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    -ms-overflow-style: none; /* IE/Edge */
    scrollbar-width: none; /* Firefox */
}
.tree-container-hierarchical::-webkit-scrollbar { display: none !important; height: 0; }
</style>
