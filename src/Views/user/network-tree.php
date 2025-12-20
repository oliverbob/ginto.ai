<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Network Tree</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/assets/css/tailwind.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <style>
    .org-admin-style-chart {
        display: flex;
        flex-direction: column;
        align-items: center;
        width: 100%;
        position: relative;
    }
    .org-level-wrapper {
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        width: 100%;
    }
    .org-chart-node {
        background: #fff;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 10px;
        position: relative;
        box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        transition: all 0.3s ease;
        z-index: 10;
    }
    .org-chart-node:hover {
        transform: translateY(-4px) scale(1.02);
        box-shadow: 0 8px 30px rgba(0,0,0,0.3);
    }
    .org-children-level {
        margin-top: 40px;
        position: relative;
        display: flex;
        flex-direction: row;
        align-items: flex-start;
        width: 100%;
        overflow-x: auto;
        justify-content: center;
        gap: 40px;
    }
    .org-tooltip {
        display: none;
        position: absolute;
        left: 50%;
        top: -10px;
        transform: translateX(-50%);
        background: rgba(35,57,93,0.98);
        color: white;
        padding: 12px 18px;
        border-radius: 10px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.18);
        z-index: 9999;
        min-width: 220px;
        font-size: 13px;
    }

    .floating-resize { position: fixed; right: 50px; bottom: 20px; z-index: 1100; opacity: 0.5; transition: opacity .25s ease, transform .15s ease; background: rgba(255,255,255,0.03); backdrop-filter: blur(6px); padding: 6px 8px; border-radius: 999px; display:flex; align-items:center; gap:8px; box-shadow: 0 6px 18px rgba(2,6,23,0.45); }
    .dark .floating-resize { background: rgba(0,0,0,0.28); }
    .floating-resize:hover, .floating-resize:focus-within { opacity: 1; }
    .floating-resize input[type=range] { width: 160px; }
    .fr-btn { background: transparent; border: none; color: inherit; font-size: 14px; padding:4px; cursor:pointer; }
    .fr-display { font-weight:700; font-size:13px; padding:0 6px; white-space:nowrap; }
    .floating-resize-inner { display:flex; align-items:center; gap:8px; }

    .org-level-wrapper { position: relative; padding-top: 50px; }
    .org-level-wrapper:not(:first-child)::before {
        content: '';
        position: absolute;
        top: 0;
        left: 50%;
        width: 2px;
        height: 40px; 
        background-color: #cbd5e0;
        z-index: 5;
        transform: translateX(-50%);
    }
    .org-children-level::before {
        content: '';
        position: absolute;
        top: -40px;
        left: 0;
        right: 0;
        height: 2px;
        background-color: #cbd5e0;
        z-index: 5;
    }
    .org-children-level > .org-level-wrapper::before {
        height: 40px;
        top: -40px;
        z-index: 5;
    }
    .org-level-wrapper > .org-chart-node {
        margin-top: -40px; 
    }
    .org-chart-node { z-index: 10; }
    .org-children-level::before { z-index: 1; }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <?php
    // network-tree.php: Organizational Network View with Header and Footer
    // Extracted header and footer from network-tree2.php

    $title = 'Network Tree';
    // --- RESTORED PHP INCLUDES ---
    include __DIR__ . '/../layout/header.php';
    include __DIR__ . '/../layout/sidebar.php';
    // ----------------------------
    ?>
    <?php
    if (!isset($_SESSION)) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrf_token = $_SESSION['csrf_token'];
    ?>

    <div id="mainContent" class="min-h-screen bg-gray-50 dark:bg-gray-900 transition-all duration-300 ease-in-out">
        <!-- Header -->
        <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700" style="height:72px; display:flex; align-items:center; padding-left:80px; padding-right:20px;">
            <div class="flex items-center py-6 justify-between w-full">
                <div>
                    <h6 class="text font-bold text-gray-600 dark:text-white text-left">Network Tree</h6>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Interactive Downline Tree</p>
                </div>
                <div class="flex space-x-3 ml-auto flex-wrap items-center">
                    <button id="expandAll" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg flex items-center space-x-2"><i class="fas fa-expand-arrows-alt"></i><span class="hidden sm:inline">Expand All</span></button>
                    <button id="collapseAll" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded-lg flex items-center space-x-2"><i class="fas fa-compress-arrows-alt"></i><span class="hidden sm:inline">Collapse All</span></button>
                    <button id="showCommissionsBtn" class="bg-yellow-600 hover:bg-yellow-00 text-white px-3 py-2 rounded-lg flex items-center space-x-2"><i class="fas fa-coins"></i><span class="hidden sm:inline">Show Commissions</span></button>
                
                </div>
            </div>
        </div>

        <div class="py-5 px-5">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6 mb-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Search Section -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        <i class="fas fa-search mr-2"></i>Find Member in Network
                    </label>
                    <div class="relative">
                        <!-- Autofill trap: off-screen fields to consume browser autocomplete values -->
                        <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:none;">
                            <input type="text" name="username" autocomplete="username" tabindex="-1" />
                            <input type="password" name="password" autocomplete="new-password" tabindex="-1" />
                        </div>
                        <input type="search" id="userSearch" name="ginto_user_search" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" placeholder="Search by username, email, or name..." inputmode="search" role="searchbox" aria-label="Find member in network"
                               class="w-full pl-4 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <div id="searchResults" class="absolute left-0 top-full mt-1 z-20 w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg hidden" style="box-sizing:border-box;">
                        </div>
                    </div> <!-- close .relative wrapper -->
                    <div class="mt-2 flex items-center gap-3">
                        <label for="searchLevelFilter" class="text-sm text-gray-600 dark:text-gray-300">Search level</label>
                        <select id="searchLevelFilter" class="px-2 py-1 text-sm rounded bg-white dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="all">All levels</option>
                            <option value="1">Level 1 (direct referrals)</option>
                            <option value="2">Level 2</option>
                            <option value="3">Level 3</option>
                            <option value="4">Level 4</option>
                            <option value="5">Level 5</option>
                            <option value="6">Level 6</option>
                            <option value="7">Level 7</option>
                            <option value="8">Level 8</option>
                            <option value="9">Level 9</option>
                        </select>
                    </div>
                    <div id="viewingAs" class="mt-2 text-sm text-gray-600 dark:text-gray-300" style="display:none;">
                        Viewing as <span id="viewingAsName" class="font-semibold"></span>
                        <button id="backToMeBtn" class="ml-3 text-xs bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 px-2 py-1 rounded" style="display:none;">Back to me</button>
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
                    </div>
                </div>
            </div>

                <div class="tree-viewport" style="width:100%; height:80vh; overflow:auto; position:relative;">
                    <div id="orgNetworkTree" class="org-admin-style-chart min-h-[400px]" style="min-width:max-content; width:max-content;">
                    <!-- ADDED: Loading Spinner and Error Message elements, which were referenced in JS -->
                    <div id="loadingSpinner" class="flex flex-col items-center justify-center p-20 hidden">
                        <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-4 text-gray-600 dark:text-gray-400">Loading network data... Please wait.</p>
                    </div>
                    <div id="errorMessage" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative p-20" role="alert">
                        <strong class="font-bold">Error!</strong>
                        <span class="block sm:inline">Could not load the network tree data. Please try again or contact support.</span>
                    </div>
                </div>
        </div>


    <!-- Floating canvas resize slider (fixed, bottom-right) -->
    <div id="floatingResizeControls" class="floating-resize" aria-hidden="false" style="right:50px;bottom:30px;">
        <div class="floating-resize-inner" tabindex="0">
            <button id="decreaseCanvas" class="fr-btn" title="Decrease scale">−</button>
            <input id="canvasSlider" type="range" min="10" max="200" step="5" value="100" aria-label="Canvas scale">
            <button id="floatingAutoFit" class="fr-btn" title="Auto-Fit">⤢</button>
            <button id="increaseCanvas" class="fr-btn" title="Increase scale">+</button>
            <span id="canvasWidthDisplay" class="fr-display">100%</span>
            <button id="resetCanvas" class="fr-btn" title="Reset scale">⟳</button>
            <label class="fr-infinite" style="display:inline-flex;align-items:center;gap:4px;margin-left:6px;color:inherit;">
                <input id="toggleInfiniteCanvas" type="checkbox" style="width:14px;height:14px;" title="Enable infinite canvas"> <span style="font-size:12px;opacity:0.9">∞</span>
            </label>
        </div>
    </div>

    <script>
    // Only declare once at the top
    let currentTreeData = null;
    let _cached = {};
    // Root user id for the displayed tree; can be changed at runtime when user selects a new search result
    // initial server-provided current user id, and dynamic root used in the tree
    window.INITIAL_CURRENT_USER_ID = <?= json_encode($current_user_id) ?>;
    window.ROOT_NETWORK_TREE_USER_ID = window.ROOT_NETWORK_TREE_USER_ID || window.INITIAL_CURRENT_USER_ID;
    window.__searchCache = window.__searchCache || {};
    let __searchCache = window.__searchCache;
    let searchTimeout = null;
    // CSRF token injected from server session
    const CSRF_TOKEN = <?= json_encode($csrf_token ?? '') ?>;
    // Expose on global so other scripts can reuse
    window.__CSRF_TOKEN = CSRF_TOKEN;
    // Helper that appends CSRF token as header and query param for GET requests
    function withCsrf(url, opts = {}) {
        opts = opts || {};
        opts.headers = opts.headers || {};
        try { opts.headers['X-CSRF-TOKEN'] = CSRF_TOKEN; } catch (e) {}
        // If GET request, also add as query param; this makes endpoints robust to either check
        const method = (opts.method || 'GET').toUpperCase();
        if (method === 'GET' && typeof url === 'string') {
            if (!/csrf_token=/.test(url)) {
                url += (url.indexOf('?') === -1 ? '?' : '&') + 'csrf_token=' + encodeURIComponent(CSRF_TOKEN);
            }
        }
        return fetch(url, opts);
    }
    const LEVEL_COLORS_LIGHT = ['#8b5cf6', '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#06b6d4'];
    const LEVEL_COLORS_DARK = ['#a78bfa', '#60a5fa', '#34d399', '#fbbf24', '#f87171', '#22d3ee'];
    let currentViewMode = 'organizational';
    let currentRenderDepth = parseInt(localStorage.getItem('networkTreeDepth') || '3', 10) || 3;

    function showUserDetails(userId) {
        alert(`Showing details for User ID: ${userId}`);
    }
    
    function buildNodeMap(node, map = {}, depth = 0) {
        if (!node) return map;
        try { node.__treeLevel = depth; } catch (e) {}
        map[node.id] = node;
        if (node.children && Array.isArray(node.children)) {
            node.children.forEach(child => buildNodeMap(child, map, depth + 1));
        }
        return map;
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Theme toggle mock function (if not provided by header.php)
        window.toggleTheme = window.toggleTheme || function() {
            try { window.themeManager && window.themeManager.toggle(); } catch (_) { /* fallback ignored */ }
        };

        // Tree depth persistence
        const depthSelect = document.getElementById('treeDepth');
        const savedDepth = localStorage.getItem('networkTreeDepth') || String(currentRenderDepth || '3');
        if (depthSelect) {
            depthSelect.value = savedDepth;
            currentRenderDepth = parseInt(depthSelect.value, 10) || currentRenderDepth;
            depthSelect.addEventListener('change', function() {
                try { localStorage.setItem('networkTreeDepth', this.value); } catch (e) {}
                currentRenderDepth = parseInt(this.value, 10) || currentRenderDepth;
                renderTreeWithMode();
            });
        }
        // Remove view mode selector and always use organizational chart
        currentViewMode = 'organizational';
        
        // Setup search (typeahead + remote search)
        const searchInput = document.getElementById('userSearch');
        if (searchInput) {
            // Make input readonly initially and remove readonly on focus to prevent many browsers' autofill dropdowns
            try {
                searchInput.setAttribute('readonly', 'true');
                searchInput.addEventListener('focus', function() { this.removeAttribute('readonly'); });
                // some browsers show autofill on click rather than focus; also support mousedown
                searchInput.addEventListener('mousedown', function() { this.removeAttribute('readonly'); });
            } catch (e) {}
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                if (query.length >= 2) {
                    searchTimeout = setTimeout(() => performSearch(query), 250);
                } else {
                    hideSearchResults();
                }
            });

            // Add Enter key handler to select first result
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const resultsContainer = document.getElementById('searchResults');
                    if (resultsContainer && !resultsContainer.classList.contains('hidden')) {
                        const firstResult = resultsContainer.querySelector('[data-user-id]');
                        if (firstResult) {
                            const userId = Number(firstResult.getAttribute('data-user-id'));
                            const userObj = window.__nodeMap ? window.__nodeMap[userId] : null;
                            focusOnUser(userId, userObj);
                        }
                    }
                }
            });

            // Hide search results when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('#userSearch') && !e.target.closest('#searchResults')) {
                    hideSearchResults();
                }
            });
        }
        
        // Setup expand/collapse
        if (document.getElementById('expandAll')) {
            document.getElementById('expandAll').addEventListener('click', expandAll);
        }
        if (document.getElementById('collapseAll')) {
            document.getElementById('collapseAll').addEventListener('click', collapseAll);
        }

        // Setup show commissions button
        if (document.getElementById('showCommissionsBtn')) {
            document.getElementById('showCommissionsBtn').addEventListener('click', function() {
                const panel = document.querySelector('.commission-summary-panel');
                const backdrop = document.querySelector('.commission-modal-backdrop');
                if (panel && backdrop) {
                    const isShown = panel.style.display !== 'none';
                    panel.style.display = isShown ? 'none' : '';
                    backdrop.style.display = isShown ? 'none' : '';
                    if (!isShown) {
                        document.body.classList.add('show-commissions');
                    } else {
                        document.body.classList.remove('show-commissions');
                    }
                }
            });
        }

        loadTree();
    });

    async function loadTree() { 
        console.log('loadTree() triggered');
        const fetchDepth = 9;
        // --- RESTORED PHP PLACEHOLDER ---
        const rootUserId = (window.ROOT_NETWORK_TREE_USER_ID || window.INITIAL_CURRENT_USER_ID);
        // -------------------------------
        
        // Check for preloaded tree data (placeholder will be filled by PHP)
        const pre = window.PRELOADED_EARNINGS ?? null;
        const usingPreloaded = pre && pre.success && pre.tree && (Number(window.ROOT_NETWORK_TREE_USER_ID || window.INITIAL_CURRENT_USER_ID) === window.INITIAL_CURRENT_USER_ID);
        if (usingPreloaded) {
            currentTreeData = pre.tree;
            // Ensure root sponsor/referrer is present when preloaded data doesn't include it
            try {
                const rootId = Number(window.ROOT_NETWORK_TREE_USER_ID || window.INITIAL_CURRENT_USER_ID);
                if (window.INITIAL_USER_DATA && Number(window.INITIAL_CURRENT_USER_ID) === rootId) {
                    const ud = window.INITIAL_USER_DATA;
                    if (ud.referrer && (ud.referrer.username || ud.referrer.fullname)) {
                        currentTreeData.referrer = ud.referrer;
                    } else if (ud.referrer_username) {
                        currentTreeData.referrer_username = ud.referrer_username;
                    } else if (ud.sponsor) {
                        currentTreeData.sponsor = ud.sponsor;
                    } else if (ud.referrer_id) {
                        currentTreeData.referrer_id = ud.referrer_id;
                    }
                }
            } catch (e) {}
            try { window.__nodeMap = window.__nodeMap || {}; buildNodeMap(currentTreeData, window.__nodeMap, 0); } catch (e) {}
            renderTreeWithMode();
            return;
        }
        showLoading();
        try {
            const url = `/api/user/direct-downlines?user_id=${rootUserId}&depth=${fetchDepth}&max_level=${fetchDepth}`;
            const response = await withCsrf(url);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            if (data.success) {
                const rootNode = {
                    id: rootUserId,
                    // default to server-side user if not re-rooting; we'll update below if necessary
                    fullname: <?= json_encode($user_data['fullname'] ?? '') ?>,
                    username: <?= json_encode($user_data['username'] ?? '') ?>,
                    directReferrals: data.downlines.length,
                    children: data.downlines
                };
                // If this is the currently-logged-in user's root, try to attach their referrer/sponsor
                // from server-provided `INITIAL_USER_DATA` so the tooltip can display the upline username
                try {
                    if (window.INITIAL_USER_DATA && Number(window.INITIAL_CURRENT_USER_ID) === Number(rootUserId)) {
                        const ud = window.INITIAL_USER_DATA;
                        // Prefer an object referrer with username/fullname, fall back to string fields
                        if (ud.referrer && (ud.referrer.username || ud.referrer.fullname)) {
                            rootNode.referrer = ud.referrer;
                        } else if (ud.referrer_username) {
                            rootNode.referrer_username = ud.referrer_username;
                        } else if (ud.sponsor) {
                            rootNode.sponsor = ud.sponsor;
                        } else if (ud.referrer_id) {
                            rootNode.referrer_id = ud.referrer_id;
                        }
                    }
                } catch (e) { /* ignore */ }
                // If we're re-rooting to another user, load their profile to show accurate name/username
                try {
                    if (Number(rootUserId) !== Number(window.INITIAL_CURRENT_USER_ID)) {
                        const profileResp = await withCsrf(`/api/user/profile?user_id=${rootUserId}`);
                        if (profileResp && profileResp.ok) {
                            const pData = await profileResp.json();
                            if (pData && pData.success && pData.data) {
                                const profileUser = pData.data || pData.user || null;
                                rootNode.fullname = (profileUser && (profileUser.fullname || profileUser.username)) || rootNode.fullname;
                                rootNode.username = (profileUser && profileUser.username) || rootNode.username;
                            }
                        }
                    }
                } catch (e) {
                    console.warn('Failed to fetch root user profile', e);
                }
                currentTreeData = rootNode;
                try { window.__nodeMap = window.__nodeMap || {}; buildNodeMap(currentTreeData, window.__nodeMap, 0); } catch (e) {}
                renderTreeWithMode();
                // Update the viewing UI to show root context
                try {
                    updateViewingAsDisplay({ id: rootUserId, username: rootNode.username, fullname: rootNode.fullname });
                } catch (e) {}
                // Build an array list of nodes for client-side local search (scoped to current tree)
                try {
                    window.__nodeList = Object.keys(window.__nodeMap || {}).map(k => window.__nodeMap[k]);
                } catch (e) { window.__nodeList = [] }
            } else {
                throw new Error(data.error || data.message || 'Unknown error occurred');
            }
        } catch (error) {
            console.error("Failed to load tree:", error);
            showError();
        }
    }

    // --- Search helpers (typeahead based on network-tree2 behavior) ---
    async function performSearch(query) {
        try {
            const cacheKey = String(query).trim().toLowerCase();
            if (window.__searchCache && window.__searchCache[cacheKey]) {
                showSearchResults(window.__searchCache[cacheKey]);
                return;
            }
            // Perform local search using `window.__nodeList` to limit search to the current tree.
            const matches = localSearch(query);
            try { window.__searchCache[cacheKey] = matches; } catch(e) {}
            showSearchResults(matches);
        } catch (error) {
            console.error('Search error:', error);
        }
    }

    function localSearch(query) {
        query = String(query || '').trim().toLowerCase();
        if (!query) return [];
        const list = window.__nodeList || [];
        const results = [];
        const maxResults = 10;
        const levelFilter = (function() {
            const sel = document.getElementById('searchLevelFilter');
            if (!sel) return null;
            const v = sel.value || 'all';
            return (v === 'all') ? null : Number(v);
        })();
        for (let i = 0; i < list.length; i++) {
            const node = list[i];
            if (!node) continue;
            const username = String(node.username || '').toLowerCase();
            const fullname = String(node.fullname || '').toLowerCase();
            const email = String(node.email || '').toLowerCase();
            if ((username.indexOf(query) !== -1 || fullname.indexOf(query) !== -1 || email.indexOf(query) !== -1)) {
                // If a level filter is set, ensure the node's tree level matches
                if (levelFilter !== null) {
                    const lvl = Number(node.__treeLevel || 0);
                    if (lvl !== Number(levelFilter)) continue;
                }
                results.push(node);
                if (results.length >= maxResults) break;
            }
        }
        return results;
    }

    function showSearchResults(users) {
        const resultsContainer = document.getElementById('searchResults');
        if (!resultsContainer) return;
        if (!Array.isArray(users) || users.length === 0) {
            resultsContainer.innerHTML = '<div class="p-3 text-gray-500">No users found in this network</div>';
        } else {
            resultsContainer.innerHTML = users.map(user => `
                <div class="p-3 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer border-b border-gray-200 dark:border-gray-600 last:border-b-0" data-user-id="${user.id}">
                    <div class="font-medium text-gray-900 dark:text-white">${user.fullname || user.username}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">${user.email || ''} • Level ${user.ginto_level || ''} • Network level: ${ (typeof user.__treeLevel !== 'undefined') ? user.__treeLevel : 'N/A' }</div>
                </div>
            `).join('');
            // Attach click handlers
            try {
                resultsContainer.querySelectorAll('[data-user-id]').forEach(el => {
                    el.addEventListener('click', function() {
                        const id = Number(this.getAttribute('data-user-id'));
                        focusOnUser(id);
                    });
                });
            } catch(e) {}
        }
        resultsContainer.classList.remove('hidden');
    }

    function hideSearchResults() {
        const el = document.getElementById('searchResults');
        if (!el) return;
        el.classList.add('hidden');
    }

    function focusOnUser(userId, userObj = null) {
        hideSearchResults();
        try { document.getElementById('userSearch').value = ''; } catch (e) {}
        window.ROOT_NETWORK_TREE_USER_ID = userId;
        // update UI indicator with provided object (if any)
        try { 
            if (userObj) updateViewingAsDisplay(userObj); 
            else if (window.__nodeMap && window.__nodeMap[userId]) updateViewingAsDisplay(window.__nodeMap[userId]);
        } catch (e) {}
        try { loadTree(); } catch (e) { console.warn('Failed to reload tree for selected user', e); }
    }

    // --- Core Utility Functions (now referencing the added HTML elements) ---
    function showLoading() {
        const spinner = document.getElementById('loadingSpinner');
        const tree = document.getElementById('orgNetworkTree');
        const error = document.getElementById('errorMessage');
        if (spinner) spinner.classList.remove('hidden');
        if (tree) tree.innerHTML = '';
        if (error) error.classList.add('hidden');
    }
    function showError() {
        const spinner = document.getElementById('loadingSpinner');
        const tree = document.getElementById('orgNetworkTree');
        const error = document.getElementById('errorMessage');
        if (spinner) spinner.classList.add('hidden');
        if (tree) tree.innerHTML = '';
        if (error) error.classList.remove('hidden');
    }
    function renderTree() {
        const spinner = document.getElementById('loadingSpinner');
        const error = document.getElementById('errorMessage');
        const tree = document.getElementById('orgNetworkTree');
        if (spinner) spinner.classList.add('hidden');
        if (error) error.classList.add('hidden');
        
        if (!currentTreeData) { showError(); return; }
        const displayRoot = truncateTree(currentTreeData, currentRenderDepth);
        renderOrganizationalChart(tree, displayRoot);
    }
    function renderTreeWithMode() {
        const tree = document.getElementById('orgNetworkTree');
        if (!tree || !currentTreeData) return;
        renderTree();
    }
    // ------------------------------------------------------------------------

    function truncateTree(node, maxDepth, depth = 0) {
        if (!node || typeof node !== 'object') return node;
        const copy = Object.assign({}, node);
        if (!copy.children || !Array.isArray(copy.children) || depth >= maxDepth) {
            copy.children = [];
            return copy;
        }
        copy.children = copy.children.map(c => truncateTree(c, maxDepth, depth + 1));
        return copy;
    }
    function formatCurrency(val) {
        return '₱' + Number(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderOrganizationalChart(container, data) {
        container.innerHTML = '';
        container.className = 'org-admin-style-chart';
        if (!data) return;

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
                    .person-tooltip{position:fixed;display:block;width:260px;background:linear-gradient(180deg,rgba(35,57,93,0.98),rgba(20,30,40,0.98));color:#fff;padding:10px 12px;border-radius:8px;box-shadow:0 10px 25px rgba(3,7,18,0.6);opacity:0;visibility:hidden;transition:opacity .12s ease,transform .12s ease;z-index:99999;white-space:normal;font-size:14px}
                    .person-tooltip .tt-row{display:flex;gap:12px;align-items:flex-start}
                    .person-tooltip .tooltip-icon{width:40px;height:40px;border-radius:50%;flex:0 0 40px;background:linear-gradient(135deg,#4f6b8a,#23395d);display:flex;align-items:center;justify-content:center}
                    .person-tooltip .tooltip-icon svg{width:18px;height:18px;opacity:0.95}
                    .person-tooltip .tt-main{ text-align:left }
                    .person-tooltip .tt-main .title{font-weight:700;font-size:16px;margin-bottom:6px}
                    .person-tooltip .tt-main .sub{font-size:14px;opacity:0.9;margin-bottom:6px}
                    .person-tooltip .tt-details{display:grid;grid-template-columns:1fr 1fr;gap:6px 10px;margin-top:6px;font-size:13px;color:rgba(255,255,255,0.95)}
                    .person-tooltip .tt-details .label{opacity:0.85;font-weight:600;margin-right:6px}
                    .person-tooltip .tt-foot{display:flex;justify-content:space-between;gap:12px;border-top:1px solid rgba(255,255,255,0.04);padding-top:8px;margin-top:10px;font-size:13px;opacity:0.95}
                    .person-tooltip.show{opacity:1;visibility:visible}
                    .chevron-btn{background-color:rgba(0,0,0,0.05);border:1px solid rgba(0,0,0,0.1);cursor:pointer;padding:5px;border-radius:3px;}
                    .dark .chevron-btn{background-color:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);}
                    .chevron-btn:hover{background-color:rgba(0,0,0,0.15);border-color:rgba(0,0,0,0.2);}
                    .dark .chevron-btn:hover{background-color:rgba(255,255,255,0.2);border-color:rgba(255,255,255,0.3);}
                    .chevron-icon{display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:bold;color:#000;}
                    .dark .chevron-icon{color:#fff;}
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
                ${data.children && data.children.length > 0 ? `<button class="chevron-btn" data-node-id="${data.id}" style="background:none;border:none;cursor:pointer;padding:5px;border-radius:3px;margin-top:8px;" title="Toggle children">
                    <div class="chevron-icon" style="transition:transform 0.2s ease;${currentRenderDepth >= 1 ? 'transform:rotate(180deg);' : ''}">
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </button>` : ''}
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
                                <div><span class="label">Sponsor:</span> <span class="sponsor-value">${(
                                    data.sponsor || data.upline || data.sponsor_username || data.upline_username ||
                                    (data.referrer && (data.referrer.username || data.referrer.fullname)) || data.referrer_username ||
                                    (data.referrer_id ? ('ID: ' + data.referrer_id) : '') || ''
                                )}</span></div>
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
        // expose referrer username for the root card so front-end tooltip scripts can reference it
        try {
            const rCardWidget = rootInner.querySelector('.card-with-tooltip');
            const r = (data && data.referrer && (data.referrer.username || data.referrer.fullname)) ? (data.referrer.username || data.referrer.fullname) : (data.referrer_username || data.sponsor || null);
            if (rCardWidget && r) rCardWidget.dataset.referrerUsername = r;
        } catch (e) {}
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
                    ${node.children && node.children.length > 0 ? `<button class="chevron-btn" data-node-id="${node.id}" style="background:none;border:none;cursor:pointer;padding:5px;border-radius:3px;margin-top:8px;" title="Toggle children">
                        <div class="chevron-icon" style="transition:transform 0.2s ease;${level < currentRenderDepth ? 'transform:rotate(180deg);' : ''}">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </button>` : ''}
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
                                    <div><span class="label">Sponsor:</span> <span class="sponsor-value">${(
                                        node.sponsor || node.upline || node.sponsor_username || node.upline_username ||
                                        (node.referrer && (node.referrer.username || node.referrer.fullname)) || node.referrer_username ||
                                        (node.referrer_id ? ('ID: ' + node.referrer_id) : '') || ''
                                    )}</span></div>
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
            // expose referrer/sponsor username for this node card so tooltip resolver can use it
            try {
                const cw = nodeCard.querySelector('.card-with-tooltip');
                const r = (node && node.referrer && (node.referrer.username || node.referrer.fullname)) ? (node.referrer.username || node.referrer.fullname) : (node.sponsor || node.referrer_username || null);
                if (cw && r) cw.dataset.referrerUsername = r;
            } catch (e) {}
            // Always store reference to this node's card; parent is optional
            nodeWrap._nodeCard = nodeCard;
            // Store the encoded data for summary calculations as well
            try { nodeWrap.dataset.userId = node.id; } catch (e) {}
            if (parentCard) {
                nodeWrap._parentCard = parentCard;
            }
            // Recursively render children based on individual node expansion state and depth
            if (node.children && node.children.length > 0) {
                const childrenRow = document.createElement('div');
                childrenRow.style.display = 'flex';
                childrenRow.style.flexDirection = 'row';
                childrenRow.style.justifyContent = 'center';
                childrenRow.style.alignItems = 'flex-start';
                childrenRow.style.gap = '40px';
                childrenRow.style.marginTop = '24px';
                childrenRow.style.width = '100%';
                childrenRow.className = 'children-row';
                childrenRow.dataset.parentId = node.id;
                
                // Show children if within current render depth
                const shouldExpand = level < currentRenderDepth;
                childrenRow.style.display = shouldExpand ? 'flex' : 'none';
                
                node.children.forEach(child => {
                    childrenRow.appendChild(renderOrgNode(nodeCard, child, nodeWrap, level + 1));
                });
                nodeWrap.appendChild(childrenRow);
            }
            return nodeWrap;
        }

        // Render the root's children into the rootWrap (root card already appended)
        if (data.children && data.children.length > 0) {
            const childrenRow = document.createElement('div');
            childrenRow.style.display = 'flex';
            childrenRow.style.flexDirection = 'row';
            childrenRow.style.justifyContent = 'center';
            childrenRow.style.alignItems = 'flex-start';
            childrenRow.style.gap = '40px';
            childrenRow.style.marginTop = '24px';
            childrenRow.style.width = '100%';
            childrenRow.className = 'children-row';
            childrenRow.dataset.parentId = data.id;
            
            // Root level (level 0) children are shown if depth >= 1
            const shouldExpand = currentRenderDepth >= 1;
            childrenRow.style.display = shouldExpand ? 'flex' : 'none';
            
            data.children.forEach(child => {
                childrenRow.appendChild(renderOrgNode(rootCard, child, rootWrap, 1));
            });
            rootWrap.appendChild(childrenRow);
        }

        container.appendChild(wrapper);

        // Add chevron button event listeners for expand/collapse functionality
        // Use event delegation on the tree container to handle dynamic re-rendering
        const treeElement = document.getElementById('orgNetworkTree');
        if (treeElement && !treeElement.hasChevronListener) {
            treeElement.addEventListener('click', function(e) {
                if (e.target.closest('.chevron-btn')) {
                    e.stopPropagation();
                    const button = e.target.closest('.chevron-btn');
                    const nodeId = button.dataset.nodeId;
                    const chevronIcon = button.querySelector('.chevron-icon');
                    const childrenRow = treeElement.querySelector(`.children-row[data-parent-id="${nodeId}"]`);
                    
                    if (childrenRow) {
                        const isExpanded = childrenRow.style.display !== 'none';
                        if (isExpanded) {
                            // Collapse
                            childrenRow.style.display = 'none';
                            chevronIcon.style.transform = 'rotate(0deg)';
                            button.title = 'Expand children';
                        } else {
                            // Expand
                            childrenRow.style.display = 'flex';
                            chevronIcon.style.transform = 'rotate(180deg)';
                            button.title = 'Collapse children';
                        }
                    }
                }
            });
            treeElement.hasChevronListener = true;
        }

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

            function buildPanel() {
                if (wrapper._commissionPanel && document.body.contains(wrapper._commissionPanel)) {
                    return wrapper._commissionPanel;
                }

                const globalExisting = document.querySelector('.commission-summary-panel');
                if (globalExisting) {
                    wrapper._commissionPanel = globalExisting;
                    return globalExisting;
                }

                let existing = wrapper.querySelector('.commission-summary-panel');
                if (existing) {
                    wrapper._commissionPanel = existing;
                    return existing;
                }

                const panel = document.createElement('div');
                panel.className = 'commission-summary-panel';
                panel.setAttribute('aria-hidden', 'true');
                panel.style.position = 'fixed';
                panel.style.left = '50%';
                panel.style.top = '50%';
                panel.style.transform = 'translate(-50%, -50%)';
                panel.style.width = 'min(960px, 96vw)';
                panel.style.maxHeight = '90vh';
                panel.style.overflow = 'auto';
                panel.style.padding = '20px';
                panel.style.borderRadius = '10px';
                panel.style.boxShadow = '0 8px 30px rgba(2,6,23,0.6)';
                panel.style.backdropFilter = 'saturate(140%) blur(6px)';
                panel.style.zIndex = '10010';
                panel.style.pointerEvents = 'auto';
                panel.style.fontFamily = 'Inter, sans-serif';
                panel.style.fontSize = '13px';
                panel.style.color = document.documentElement.classList.contains('dark') ? '#e6eef8' : '#0f172a';
                panel.style.background = document.documentElement.classList.contains('dark') ? 'linear-gradient(180deg, rgba(17,24,39,0.9), rgba(6,8,15,0.8))' : 'linear-gradient(180deg, rgba(255,255,255,0.95), rgba(250,250,250,0.95))';
                panel.style.display = 'none';

                panel.innerHTML = `
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                        <div style="font-weight:700;">Commission Summary</div>
                        <button type="button" class="close-commission-panel" title="Close" style="background:transparent;border:none;color:inherit;cursor:pointer;font-size:20px;">✕</button>
                    </div>
                    <div class="commission-rows" style="display:flex;flex-direction:column;gap:8px;">
                    </div>
                    <div class="commission-total" style="margin-top:12px;border-top:1px solid rgba(0,0,0,0.06);padding-top:8px;display:flex;justify-content:space-between;font-weight:700;"> 
                        <div>Total Potential</div>
                        <div class="total-amount">₱0.00</div>
                    </div>
                `;

                panel.querySelector('.close-commission-panel').addEventListener('click', () => {
                    panel.style.display = 'none';
                    try {
                        const bd = wrapper._commissionBackdrop || document.querySelector('.commission-modal-backdrop');
                        if (bd) bd.style.display = 'none';
                    } catch (e) {}
                    document.body.classList.remove('show-commissions');
                });

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
                    
                    const totalEl = panel.querySelector('.total-amount');
                    if (totalEl) totalEl.textContent = formatCurrency(totalPotential);
                    
                    const shown = document.body.classList.contains('show-commissions');
                    panel.style.display = shown ? '' : 'none';
                    panel.setAttribute('aria-hidden', shown ? 'false' : 'true');
                } catch (e) {
                    console.warn('Failed to update commission panel', e);
                }
            }

            window.addEventListener('commissionDepthChange', function() {
                try { updatePanel(); } catch (e) { console.warn('commissionDepthChange handler failed', e); }
            });

            if (!wrapper._commissionThemeObserver) {
                wrapper._commissionThemeObserver = new MutationObserver(() => {
                    const panel = wrapper._commissionPanel || wrapper.querySelector('.commission-summary-panel');
                    if (!panel) return;
                    panel.style.color = document.documentElement.classList.contains('dark') ? '#e6eef8' : '#0f172a';
                    panel.style.background = document.documentElement.classList.contains('dark') ? 'linear-gradient(180deg, rgba(17,24,39,0.9), rgba(6,8,15,0.8))' : 'linear-gradient(180deg, rgba(255,255,255,0.95), rgba(250,250,250,0.95))';
                });
                wrapper._commissionThemeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
            }

            wrapper.updateCommissionPanel = function(d) { return updatePanel(d); };
            updatePanel();
        })();

        // Tooltip show/hide (inline small tooltips inside each card)
        (function attachTooltips() {
            const HIDE_DELAY = 120; // ms: small delay to avoid flicker when moving between card<->tooltip

            function showTooltipForCard(card) {
                const tip = card.querySelector('.person-tooltip');
                if (!tip) return;
                
                if (!tip.__orgParent) {
                    try {
                        tip.__orgParent = tip.parentElement;
                        tip.__orgNext = tip.nextSibling;
                        tip.__forCard = card;
                        document.body.appendChild(tip);
                    } catch (e) {}
                }

                tip.style.position = 'fixed';
                tip.style.zIndex = '2147483647';
                tip.classList.add('show');
                // Cancel any pending hide timer
                try { if (tip.__hideTimer) { clearTimeout(tip.__hideTimer); tip.__hideTimer = null; } } catch (e) {}

                const r = card.getBoundingClientRect();
                tip.style.left = (r.left + r.width / 2) + 'px';
                tip.style.top = (r.top - 8) + 'px';
                tip.style.transform = 'translateX(-50%) translateY(-100%)';

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
                        tip.style.top = (r.bottom + 8) + 'px';
                        tip.style.transform = 'translateX(-50%) translateY(0)';
                    }
                }, 0);

                // Add pointer enter/leave on tooltip to keep it visible while hovered
                if (!tip.__listenersAdded) {
                    tip.addEventListener('mouseenter', function() {
                        try { if (tip.__hideTimer) { clearTimeout(tip.__hideTimer); tip.__hideTimer = null; } } catch (e) {}
                        tip.classList.add('show');
                    });
                    tip.addEventListener('mouseleave', function(ev) {
                        // If we leave tooltip but move back into the card, keep it open
                        const related = ev.relatedTarget;
                        if (related && card.contains(related)) return;
                        hideTooltipForCard(card);
                    });
                    tip.addEventListener('pointerdown', function() { hideTooltipForCard(card); });
                    tip.__listenersAdded = true;
                }
                // Try to resolve sponsor username if the tooltip currently shows an ID fallback
                try {
                    const sponsorEl = tip.querySelector('.sponsor-value');
                    if (sponsorEl) {
                        const cur = (sponsorEl.textContent || '').trim();
                        const cardDatasetSponsor = (card && card.dataset && card.dataset.referrerUsername) ? card.dataset.referrerUsername : '';
                        const nodeId = (card && card.dataset && card.dataset.userId) ? card.dataset.userId : null;
                        const nodeMapCandidate = (nodeId && window.__nodeMap && window.__nodeMap[nodeId] && window.__nodeMap[nodeId].referrer) ? (window.__nodeMap[nodeId].referrer.username || window.__nodeMap[nodeId].referrer.fullname) : '';
                        if (!cur && (cardDatasetSponsor || nodeMapCandidate)) {
                            sponsorEl.textContent = cardDatasetSponsor || nodeMapCandidate;
                        } else if (/^ID:\s*\d+$/i.test(cur) || !cur) {
                            const idMatch = (cur || '').match(/(\d+)/);
                            const idToFetch = idMatch ? Number(idMatch[1]) : (window.__nodeMap && nodeId && window.__nodeMap[nodeId] && window.__nodeMap[nodeId].referrer_id ? window.__nodeMap[nodeId].referrer_id : null);
                            if (!cardDatasetSponsor && !nodeMapCandidate && idToFetch) {
                                (async () => {
                                    try {
                                        const resp = await withCsrf(`/api/user/profile?user_id=${encodeURIComponent(idToFetch)}`);
                                        if (resp && resp.ok) {
                                            const pj = await resp.json();
                                            if (pj && pj.success && pj.data) {
                                                const u = pj.data;
                                                const name = u.username || u.fullname || `ID: ${idToFetch}`;
                                                sponsorEl.textContent = name;
                                                // Also stash it on the card for future quick usage
                                                try { if (card && card.dataset) card.dataset.referrerUsername = name; } catch (e) {}
                                            }
                                        }
                                    } catch (err) { /* ignore */ }
                                })();
                            }
                        }
                    }
                } catch (e) {}
            }

            function hideTooltipForCard(card, immediate) {
                let tip = null;
                try {
                    tip = Array.from(document.querySelectorAll('.person-tooltip')).find(t => t.__forCard === card || t.__orgParent === card);
                } catch (e) {}
                if (!tip) return;
                // Allow a small delay so entering the tooltip doesn't hide right away
                try {
                    if (!immediate) {
                        if (tip.__hideTimer) clearTimeout(tip.__hideTimer);
                        tip.__hideTimer = setTimeout(() => {
                            try { tip.classList.remove('show'); } catch (e) {}
                            // When hidden, reattach to original parent
                            try {
                                if (tip.__orgParent) {
                                    if (tip.__orgNext && tip.__orgParent.contains(tip.__orgNext)) {
                                        tip.__orgParent.insertBefore(tip, tip.__orgNext);
                                    } else {
                                        tip.__orgParent.appendChild(tip);
                                    }
                                }
                                try { delete tip.__orgParent; } catch (e) {}
                                try { delete tip.__orgNext; } catch (e) {}
                            } catch (e) {}
                            try { tip.style.zIndex = ''; } catch (e) {}
                        }, HIDE_DELAY);
                        return;
                    }
                } catch (e) {}

                // immediate hide
                if (tip.__hideTimer) { try { clearTimeout(tip.__hideTimer); } catch (e) {} tip.__hideTimer = null; }
                tip.classList.remove('show');
                
                if (tip.__orgParent) {
                    try {
                        if (tip.__orgNext && tip.__orgParent.contains(tip.__orgNext)) {
                            tip.__orgParent.insertBefore(tip, tip.__orgNext);
                        } else {
                            tip.__orgParent.appendChild(tip);
                        }
                    } catch (e) {}
                    try { delete tip.__orgParent; } catch (e) {}
                    try { delete tip.__orgNext; } catch (e) {}
                }
                tip.style.zIndex = '';
            }

            // Attach mouse events directly to person icons
            function attachPersonIconEvents() {
                wrapper.querySelectorAll('.person-icon').forEach(icon => {
                    if (icon.__tooltipEventsAttached) return;
                    
                    const card = icon.closest('.card-with-tooltip');
                    if (!card) return;
                    
                    icon.addEventListener('mouseenter', () => {
                        showTooltipForCard(card);
                    });
                    
                    icon.addEventListener('mouseleave', (ev) => {
                        const related = ev.relatedTarget;
                        // Don't hide if moving to the tooltip
                        let tip = null;
                        try { tip = Array.from(document.querySelectorAll('.person-tooltip')).find(t => t.__forCard === card || t.__orgParent === card); } catch (e) {}
                        if (related && tip && tip.contains(related)) return;
                        hideTooltipForCard(card);
                    });
                    
                    icon.__tooltipEventsAttached = true;
                });
            }
            
            // Attach events after a short delay to ensure DOM is ready
            setTimeout(attachPersonIconEvents, 10);
        })();

        // Draw connectors from each parent to its direct children (single reusable SVG)
        setTimeout(() => {
            wrapper.style.position = wrapper.style.position || 'relative';
            wrapper.style.overflow = wrapper.style.overflow || 'visible';
            wrapper.style.minWidth = 'max-content';
            wrapper.style.width = 'max-content';

            let svg = wrapper.querySelector('.org-connector-svg');
            if (!svg) {
                svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.classList.add('org-connector-svg');
                svg.style.position = 'absolute';
                svg.style.left = '0';
                svg.style.top = '0';
                svg.style.pointerEvents = 'none';
                svg.style.zIndex = '0';
                wrapper.insertBefore(svg, wrapper.firstChild);
            }

            function resizeSvgToWrapper() {
                const w = Math.max(wrapper.scrollWidth, wrapper.clientWidth, wrapper.offsetWidth);
                const h = Math.max(wrapper.scrollHeight, wrapper.clientHeight, wrapper.offsetHeight);
                svg.setAttribute('viewBox', `0 0 ${w} ${h}`);
                svg.setAttribute('width', w);
                svg.setAttribute('height', h);
                svg.style.width = w + 'px';
                svg.style.height = h + 'px';
            }

            function getChildNodeWraps(parentWrap) {
                return Array.from(parentWrap.children)
                    .filter(el => el && el.style && el.style.display === 'flex' && el.style.flexDirection === 'row')
                    .flatMap(row => Array.from(row.children));
            }

            function clearPaths() {
                while (svg.firstChild) svg.removeChild(svg.firstChild);
            }

            function drawAllConnectors() {
                clearPaths();
                resizeSvgToWrapper();
                
                let wrapperScale = 1;
                try {
                    let el = wrapper;
                    while (el && el !== document.documentElement) {
                        if (el.dataset && el.dataset.currentScale) { 
                            wrapperScale = parseFloat(el.dataset.currentScale) || 1; 
                            break; 
                        }
                        el = el.parentElement;
                    }
                } catch (e) { wrapperScale = 1; }

                const wrapperRect = wrapper.getBoundingClientRect();
                const viewport = wrapper.closest('.tree-viewport');
                const scrollLeft = viewport ? viewport.scrollLeft : 0;
                const scrollTop = viewport ? viewport.scrollTop : 0;

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

                    childWraps.forEach(childWrap => drawForNode(childWrap));
                }

                try {
                    if (typeof rootWrap !== 'undefined' && rootWrap) {
                        drawForNode(rootWrap);
                    } else {
                        drawForNode(wrapper);
                    }
                } catch (e) {
                    const first = wrapper.firstElementChild;
                    if (first) drawForNode(first);
                }

                try {
                    function updateLevelSummaries() {
                        Array.from(wrapper.querySelectorAll('.level-summary-badge')).forEach(el => el.remove());

                        const wrapperRect = wrapper.getBoundingClientRect();
                        const nodeWraps = Array.from(wrapper.querySelectorAll('[data-level]'))
                            .filter(n => n.dataset && typeof n.dataset.level !== 'undefined');

                        const levels = {};
                        nodeWraps.forEach(nw => {
                            const lvl = parseInt(nw.dataset.level, 10) || 0;
                            if (!levels[lvl]) levels[lvl] = [];
                            levels[lvl].push(nw);
                        });

                        Object.keys(levels).forEach(k => {
                            const lvl = parseInt(k, 10);
                            if (isNaN(lvl) || lvl <= 0) return;
                            const group = levels[lvl];
                            if (!group.length) return;

                            const centers = group.map(nw => {
                                const c = nw._nodeCard ? nw._nodeCard.getBoundingClientRect() : nw.getBoundingClientRect();
                                return ((c.top + c.bottom) / 2 - wrapperRect.top) / wrapperScale;
                            });
                            const avgY = centers.reduce((a,b)=>a+b,0)/centers.length || 0;

                            let sum = 0;
                            group.forEach(nw => {
                                try {
                                    const ud = (nw.dataset.userId && window.__nodeMap && window.__nodeMap[nw.dataset.userId]) ? window.__nodeMap[nw.dataset.userId] : null;
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
                            badge.style.zIndex = '10000';
                            badge.innerHTML = `<div style="display:flex;flex-direction:column;gap:2px;align-items:flex-end;">
                                <div style="font-weight:700">Level ${lvl}</div>
                                <div style="font-size:12px;opacity:0.85">${formatCurrency(amount)} @ ${Math.round(rate*10000)/100}%</div>
                            </div>`;

                            wrapper.appendChild(badge);
                        });
                    }
                } catch (e) { console.warn('failed to update level summaries', e); }
            }

            let rafId = null;
            function scheduleRedraw() {
                if (rafId) cancelAnimationFrame(rafId);
                rafId = requestAnimationFrame(() => {
                    drawAllConnectors();
                    rafId = null;
                });
            }

            scheduleRedraw();

            try { 
                window.__connectorRedraws = window.__connectorRedraws || []; 
                if (!window.__connectorRedraws.includes(scheduleRedraw)) 
                    window.__connectorRedraws.push(scheduleRedraw); 
            } catch (e) {}
            
            const viewport = wrapper.closest('.tree-viewport');
            if (viewport) {
                viewport.addEventListener('scroll', scheduleRedraw);
            }
            wrapper.addEventListener('scroll', scheduleRedraw);

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
                updateBadges();
            })();

            try {
                const ro = new ResizeObserver(scheduleRedraw);
                ro.observe(wrapper);
            } catch (e) {}
            
            window.addEventListener('resize', scheduleRedraw);
        }, 0);
    }

    // Utility functions for the floating resize controls
    (function(){
        function findViewportAndInner() {
            const vp = document.getElementById('orgNetworkTree')?.closest('.tree-viewport');
            if (!vp) return {};
            let inner = document.getElementById('orgNetworkTree');
            return { vp, inner };
        }
        
        function applyScaleToInner(inner, percent) {
            if (!inner) return;
            const s = Math.max(0.1, Math.min(2, percent / 100));
            inner.style.transformOrigin = '0 0';
            inner.style.transform = `scale(${s})`;
            inner.dataset.currentScale = String(s); 
            const disp = document.getElementById('canvasWidthDisplay');
            if (disp) disp.textContent = `${Math.round(percent)}%`;
            const slider = document.getElementById('canvasSlider');
            if (slider && Number(slider.value) !== Math.round(percent)) slider.value = Math.round(percent);
            
            // Trigger connector redraw
            if (window.__connectorRedraws) {
                window.__connectorRedraws.forEach(fn => fn());
            }
        }
        
        function ensureSpacer(vp, width) {
            let spacer = vp.querySelector('.canvas-scale-spacer');
            if (!spacer) {
                spacer = document.createElement('div');
                spacer.className = 'canvas-scale-spacer';
                spacer.style.position = 'absolute';
                spacer.style.top = '0';
                spacer.style.left = '0';
                spacer.style.height = '1px';
                spacer.style.pointerEvents = 'none';
                spacer.style.visibility = 'hidden';
                vp.appendChild(spacer);
            }
            spacer.style.width = width + 'px';
            return spacer;
        }
        
        function treeAutoFit(opts = {}) {
            const allowOverflow = !!opts.allowOverflow;
            const { vp, inner } = findViewportAndInner();
            if (!vp || !inner) return;

            const prevTransform = inner.style.transform || '';
            inner.style.transform = '';
            const contentWidth = Math.max(inner.scrollWidth || inner.getBoundingClientRect().width, 1);
            const vpClient = vp.clientWidth || vp.getBoundingClientRect().width || 800;
            let percent = Math.min(100, (vpClient / contentWidth) * 100);
            percent = Math.max(10, Math.round(percent));

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

        window.runAutoFit = function() { 
            const inf = document.getElementById('toggleInfiniteCanvas'); 
            treeAutoFit({ allowOverflow: !!(inf && inf.checked) }); 
        };

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
            
            const decreaseBtn = document.getElementById('decreaseCanvas');
            if (decreaseBtn) {
                decreaseBtn.addEventListener('click', function() {
                    const slider = document.getElementById('canvasSlider');
                    if (slider) {
                        const current = Number(slider.value) || 100;
                        const newVal = Math.max(10, current - 5);
                        slider.value = newVal;
                        slider.dispatchEvent(new Event('input'));
                    }
                });
            }
            
            const increaseBtn = document.getElementById('increaseCanvas');
            if (increaseBtn) {
                increaseBtn.addEventListener('click', function() {
                    const slider = document.getElementById('canvasSlider');
                    if (slider) {
                        const current = Number(slider.value) || 100;
                        const newVal = Math.min(200, current + 5);
                        slider.value = newVal;
                        slider.dispatchEvent(new Event('input'));
                    }
                });
            }
            
            const resetBtn = document.getElementById('resetCanvas');
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    const slider = document.getElementById('canvasSlider');
                    if (slider) {
                        slider.value = 100;
                        slider.dispatchEvent(new Event('input'));
                    }
                });
            }
        });
    })();

    // Expose initial user data and helper to update viewing indicator
    window.INITIAL_USER_DATA = <?= json_encode($user_data ?? null, JSON_HEX_TAG) ?>;
    function updateViewingAsDisplay(userObj) {
        const viewing = document.getElementById('viewingAs');
        const nameEl = document.getElementById('viewingAsName');
        const backBtn = document.getElementById('backToMeBtn');
        if (!viewing || !nameEl || !backBtn) return;
        if (!userObj) {
            viewing.style.display = 'none';
            backBtn.style.display = 'none';
            nameEl.textContent = '';
            return;
        }
        viewing.style.display = '';
        const displayName = userObj.fullname || userObj.username || `ID: ${userObj.id}`;
        nameEl.textContent = displayName + (userObj.username ? ` (${userObj.username})` : ` (ID: ${userObj.id || ''})`);
        if (Number(userObj.id || userObj.user_id || userObj.userId) === Number(window.INITIAL_CURRENT_USER_ID)) {
            backBtn.style.display = 'none';
        } else {
            backBtn.style.display = '';
        }
    }

    // Expand All function - sets tree depth to maximum (9 levels)
    function expandAll() {
        const depthSelect = document.getElementById('treeDepth');
        if (depthSelect) {
            depthSelect.value = '9';
            currentRenderDepth = 9;
            try { localStorage.setItem('networkTreeDepth', '9'); } catch (e) {}
            renderTreeWithMode();
        }
    }

    // Collapse All function - sets tree depth to minimum (1 level)
    function collapseAll() {
        const depthSelect = document.getElementById('treeDepth');
        if (depthSelect) {
            depthSelect.value = '1';
            currentRenderDepth = 1;
            try { localStorage.setItem('networkTreeDepth', '1'); } catch (e) {}
            renderTreeWithMode();
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const backBtn = document.getElementById('backToMeBtn');
        if (backBtn) {
            backBtn.addEventListener('click', function() {
                window.ROOT_NETWORK_TREE_USER_ID = window.INITIAL_CURRENT_USER_ID;
                loadTree();
            });
        }
        // Initialize viewing indicator to logged-in user
        if (window.INITIAL_USER_DATA) updateViewingAsDisplay(window.INITIAL_USER_DATA);
    });
    </script>

    <script>
    // Use PHP to inject the tree data as JSON
    // --- RESTORED PHP PLACEHOLDER ---
    window.PRELOADED_EARNINGS = <?php echo json_encode($earnings ?? null, JSON_HEX_TAG); ?>;
    // --------------------------------
    </script>

    <?php include __DIR__ . '/../layout/footer.php'; ?>
</body>
</html>