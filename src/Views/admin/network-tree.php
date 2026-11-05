<?php
/** @var string $title */
/** @var array $stats */
include __DIR__ . '/../layout/header.php';
?>

<!-- Include Universal Admin Sidebar -->
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<!-- Main Content Area -->
<div id="mainContent" class="min-h-screen bg-gray-50 dark:bg-gray-900 transition-all duration-300 ease-in-out">
    <!-- Header -->
    <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
        <div class="ml-20 lg:ml-8 px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Network Tree</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Visualize your MLM network structure and relationships
                    </p>
                </div>
                <div class="flex space-x-3">
                    <!-- Theme Toggle -->
                    <button id="themeToggle" onclick="toggleTheme()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2" title="Toggle Dark/Light Mode">
                        <i id="themeIcon" class="fas fa-moon"></i>
                        <span id="themeText">Dark</span>
                    </button>
                    <button onclick="expandAll()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        Expand All
                    </button>
                    <button onclick="collapseAll()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">
                        Collapse All
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="px-6 lg:px-8 py-8">
        <!-- Network Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white"><?= number_format($stats['totalUsers']) ?></h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Total Users</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white"><?= number_format($stats['activeUsers']) ?></h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Active Users</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"></path>
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">$<?= number_format($stats['totalCommissions'], 2) ?></h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Total Commissions</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">$<?= number_format($stats['monthlyCommissions'], 2) ?></h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">This Month</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Controls -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6 mb-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Search Section -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        <i class="fas fa-search mr-2"></i>Find User in Network
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
                            <select id="treeDepth" onchange="loadTree()" 
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <option value="2">2 Levels</option>
                                <option value="3" selected>3 Levels</option>
                                <option value="4">4 Levels</option>
                                <option value="5">5 Levels</option>
                            </select>
                            <input type="number" id="rootUserId" value="2" onchange="loadTree()" placeholder="Root ID"
                                   class="w-20 px-2 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
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
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="hierarchical" selected>Hierarchical Tree</option>
                            <option value="compact">Compact View</option>
                            <option value="organizational">Organizational Chart</option>
                            <option value="network">Network View</option>
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
                    <button onclick="centerTree()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded text-sm">
                        <i class="fas fa-crosshairs mr-1"></i>Center View
                    </button>
                    <button onclick="highlightPath()" class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1 rounded text-sm">
                        <i class="fas fa-route mr-1"></i>Highlight
                    </button>
                </div>
            </div>
        </div>

        <!-- Network Tree Visualization -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Network Tree Structure</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Interactive network tree showing upline/downline relationships
                </p>
            </div>

            <div class="p-6">
                <div id="loadingSpinner" class="flex justify-center items-center py-12">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                    <span class="ml-3 text-gray-400">Loading network tree...</span>
                </div>

                <div id="treeContainer" class="hidden tree-container-hierarchical">
                    <!-- Tree will be rendered here -->
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

<!-- User Details Modal -->
<div id="userModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-gray-800">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-white" id="modalTitle">User Details</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div id="modalContent">
                <!-- User details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<style>
.tree-node {
    min-width: 280px;
    max-width: 320px;
    margin: 10px;
    position: relative;
}

.tree-node .user-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 16px;
    color: white;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    cursor: pointer;
}

.tree-node .user-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.tree-node.level-0 .user-card { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.tree-node.level-1 .user-card { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.tree-node.level-2 .user-card { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
.tree-node.level-3 .user-card { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
.tree-node.level-4 .user-card { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }

.tree-children {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    margin-top: 20px;
    position: relative;
}

.tree-children::before {
    content: '';
    position: absolute;
    top: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 2px;
    height: 20px;
    background-color: #cbd5e0;
}

.connection-line {
    position: absolute;
    background-color: #cbd5e0;
    z-index: -1;
}

/* Organizational Chart Specific Styles */
.org-node-container {
    transition: all 0.3s ease;
}

.org-node {
    transition: all 0.3s ease;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.org-node:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
}

.org-children {
    position: relative;
}

.org-children::before {
    content: '';
    position: absolute;
    top: -15px;
    left: 50%;
    transform: translateX(-50%);
    width: 100%;
    height: 2px;
    background: #3498db;
    z-index: 0;
}

.org-children .org-node-container::after {
    content: '';
    position: absolute;
    top: -15px;
    left: 50%;
    transform: translateX(-50%);
    width: 2px;
    height: 15px;
    background: #3498db;
    z-index: 1;
}

.org-connector-up,
.org-connector-down,
.org-connector-horizontal {
    transition: all 0.3s ease;
}

/* Different container styling based on view mode */
.tree-container-hierarchical {
    padding: 20px;
    background: #f8fafc;
    border-radius: 8px;
    min-height: 200px;
    max-height: 600px;
    overflow: auto;
}

.dark .tree-container-hierarchical {
    background: #1f2937;
}

.tree-container-organizational {
    padding: 40px 20px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 12px;
    min-height: 400px;
    max-height: 800px;
    overflow: auto;
    display: flex;
    justify-content: center;
    align-items: flex-start;
}

.dark .tree-container-organizational {
    background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
}

.tree-container-network {
    padding: 30px 20px;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border-radius: 15px;
    min-height: 500px;
    max-height: 900px;
    overflow: auto;
    display: flex;
    justify-content: center;
    align-items: flex-start;
}

.dark .tree-container-network {
    background: linear-gradient(135deg, #374151 0%, #4b5563 100%);
}

/* Network View Specific Styles */
.network-person:hover .person-icon {
    transform: scale(1.1);
    box-shadow: 0 6px 30px rgba(0,0,0,0.25);
}

.network-children::before {
    content: '';
    position: absolute;
    top: -20px;
    left: 50%;
    transform: translateX(-50%);
    width: 100%;
    height: 2px;
    background: #bdc3c7;
    z-index: 0;
}

.network-children .network-node-container::after {
    content: '';
    position: absolute;
    top: -20px;
    left: 50%;
    transform: translateX(-50%);
    width: 2px;
    height: 20px;
    background: #bdc3c7;
    z-index: 1;
}

#networkTooltip {
    position: absolute;
    background: rgba(31, 41, 55, 0.95);
    color: white;
    padding: 12px;
    border-radius: 8px;
    font-size: 12px;
    z-index: 1000;
    box-shadow: 0 4px 20px rgba(0,0,0,0.5);
    pointer-events: none;
    max-width: 250px;
    border: 1px solid #4b5563;
}

/* Light theme scrollbar styling */
::-webkit-scrollbar {
    width: 12px;
    height: 12px;
}

::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 6px;
}

::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 6px;
    border: 2px solid #f1f5f9;
}

::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

::-webkit-scrollbar-thumb:active {
    background: #64748b;
}

::-webkit-scrollbar-corner {
    background: #f1f5f9;
}

/* Firefox scrollbar styling for light theme */
* {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 #f1f5f9;
}

/* Dark theme scrollbar styling */
.dark ::-webkit-scrollbar-track {
    background: #1f2937;
}

.dark ::-webkit-scrollbar-thumb {
    background: #4b5563;
    border: 2px solid #1f2937;
}

.dark ::-webkit-scrollbar-thumb:hover {
    background: #6b7280;
}

.dark ::-webkit-scrollbar-thumb:active {
    background: #9ca3af;
}

.dark ::-webkit-scrollbar-corner {
    background: #1f2937;
}

/* Firefox scrollbar styling for dark theme */
.dark * {
    scrollbar-color: #4b5563 #1f2937;
}

/* Enhanced scrollbar for tree containers - Light theme */
.tree-container-hierarchical::-webkit-scrollbar,
.tree-container-organizational::-webkit-scrollbar,
.tree-container-network::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.tree-container-hierarchical::-webkit-scrollbar-thumb,
.tree-container-organizational::-webkit-scrollbar-thumb,
.tree-container-network::-webkit-scrollbar-thumb {
    background: #94a3b8;
    border-radius: 4px;
}

.tree-container-hierarchical::-webkit-scrollbar-thumb:hover,
.tree-container-organizational::-webkit-scrollbar-thumb:hover,
.tree-container-network::-webkit-scrollbar-thumb:hover {
    background: #64748b;
}

/* Enhanced scrollbar for tree containers - Dark theme */
.dark .tree-container-hierarchical::-webkit-scrollbar-thumb,
.dark .tree-container-organizational::-webkit-scrollbar-thumb,
.dark .tree-container-network::-webkit-scrollbar-thumb {
    background: #6b7280;
}

.dark .tree-container-hierarchical::-webkit-scrollbar-thumb:hover,
.dark .tree-container-organizational::-webkit-scrollbar-thumb:hover,
.dark .tree-container-network::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>

<script>
let currentTreeData = null;
let expandedNodes = new Set();



// Load initial tree
document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, initializing...');
    
    // Initialize theme (prefer central themeManager)
    initializeTheme();
    
    loadTree();
    setupSearch();
});

// Theme Management Functions
function initializeTheme() {
    try { if (window.themeManager && typeof window.themeManager.init === 'function') { window.themeManager.init(); return; } } catch (_) {}
    const savedTheme = localStorage.getItem('theme') || 'dark';
    applyTheme(savedTheme);
}

function toggleTheme() {
    try { window.themeManager && window.themeManager.toggle(); return; } catch (_) {}
    const currentTheme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    applyTheme(newTheme);
    try { localStorage.setItem('theme', newTheme); } catch (_) {}
}

function applyTheme(theme) {
    const html = document.documentElement;
    const themeIcon = document.getElementById('themeIcon');
    const themeText = document.getElementById('themeText');
    
    if (theme === 'dark') {
        html.classList.add('dark');
        if (themeIcon && themeText) {
            themeIcon.className = 'fas fa-sun';
            themeText.textContent = 'Light';
        }
    } else {
        html.classList.remove('dark');
        if (themeIcon && themeText) {
            themeIcon.className = 'fas fa-moon';
            themeText.textContent = 'Dark';
        }
    }
    
    // Update all UI components for current theme
    updateAllComponentsTheme(theme);
}

function updateAllComponentsTheme(theme) {
    // Force reload the tree to apply theme to all elements
    setTimeout(() => {
        loadTree();
    }, 100);
    
    // Update compact view nodes if they exist
    const compactNodes = document.querySelectorAll('.compact-node');
    compactNodes.forEach(node => {
        if (theme === 'dark') {
            node.style.background = '#374151';
            node.style.borderColor = '#4b5563';
            node.style.color = '#f3f4f6';
        } else {
            node.style.background = 'white';
            node.style.borderColor = '#d1d5db';
            node.style.color = '#111827';
        }
    });
}

async function loadTree() {
    const rootUserId = document.getElementById('rootUserId').value;
    const depth = document.getElementById('treeDepth').value;
    
    console.log('Loading tree for user:', rootUserId, 'depth:', depth);
    showLoading();
    
    try {
        const url = `/admin/network-tree/data?user_id=${rootUserId}&depth=${depth}`;
        console.log('Fetching:', url);
        
        const response = await fetch(url);
        console.log('Response status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        console.log('Response data:', result);
        
        if (result.success && result.data) {
            currentTreeData = result.data;
            renderTree(result.data);
        } else {
            showError(result.message || 'No data returned from server');
        }
    } catch (error) {
        console.error('Error loading tree:', error);
        showError('Error: ' + error.message);
        // Show a simple fallback with basic HTML
        const container = document.getElementById('treeContainer');
        container.innerHTML = `
            <div style="padding: 20px; border: 1px solid #ccc; background: white; text-align: center;">
                <h3>Error loading tree</h3>
                <p>Error: ${error.message}</p>
                <button onclick="location.reload()" style="padding: 10px 20px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Reload Page
                </button>
            </div>
        `;
        container.classList.remove('hidden');
        hideLoading();
    }
}

function renderTree(treeData) {
    console.log('Rendering tree with data:', treeData);
    
    try {
        if (!treeData) {
            console.error('No tree data received');
            showError('No data to display');
            return;
        }
        
        const container = document.getElementById('treeContainer');
        container.innerHTML = '';
        container.classList.remove('hidden');
        
        hideLoading();
        hideError();
        
        console.log('Generating tree HTML...');
        const treeHtml = generateTreeHtml(treeData, 0);
        console.log('Generated HTML length:', treeHtml.length);
        
        if (!treeHtml || treeHtml.length === 0) {
            throw new Error('Generated HTML is empty');
        }
        
        container.innerHTML = treeHtml;
        console.log('Tree rendered successfully');
        
        // Add click handlers with error handling
        try {
            container.querySelectorAll('.user-node').forEach(card => {
                card.addEventListener('click', function() {
                    const userId = this.dataset.userId;
                    if (userId) {
                        showUserModal(userId);
                    }
                });
            });
            console.log('Click handlers added successfully');
        } catch (handlerError) {
            console.warn('Error adding click handlers:', handlerError);
        }
        
    } catch (renderError) {
        console.error('Error in renderTree:', renderError);
        const container = document.getElementById('treeContainer');
        container.innerHTML = `
            <div style="padding: 20px; text-align: center; background: white; border: 1px solid #ddd; border-radius: 8px;">
                <h3 style="color: #e74c3c;">Rendering Error</h3>
                <p>Error: ${renderError.message}</p>
                <button onclick="loadTree()" style="padding: 10px 20px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Try Again
                </button>
            </div>
        `;
        container.classList.remove('hidden');
        hideLoading();
    }
}

// Fallback: Add click handlers differently
function addClickHandlers() {
    const container = document.getElementById('treeContainer');
    container.querySelectorAll('[data-user-id]').forEach(card => {
        card.addEventListener('click', function() {
            const userId = this.dataset.userId;
            showUserModal(userId);
        });
    });
}

let currentViewMode = 'hierarchical';

function generateTreeHtml(node, level = 0) {
    const hasChildren = node.children && node.children.length > 0;
    const nodeId = `node-${node.id}`;
    
    // Different rendering based on view mode
    switch(currentViewMode) {
        case 'compact':
            return generateCompactTreeHtml(node, level);
        case 'organizational':
            return generateOrganizationalTreeHtml(node, level);
        case 'network':
            return generateNetworkViewHtml(node, level);
        default:
            return generateHierarchicalTreeHtml(node, level);
    }
}

function generateHierarchicalTreeHtml(node, level) {
    console.log('Generating node:', node.username, 'at level:', level);
    
    const hasChildren = node.children && node.children.length > 0;
    const nodeId = `node-${node.id}`;
    const isDark = document.documentElement.classList.contains('dark');
    
    // Level-based color schemes for both themes
    const darkColors = ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c'];
    const lightColors = ['#ef4444', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#06b6d4'];
    const nodeColor = isDark ? darkColors[level % darkColors.length] : lightColors[level % lightColors.length];
    
    // Theme-aware styling
    const textColor = 'white'; // White text works on all colored backgrounds
    const borderColor = 'transparent'; // No borders needed with colored backgrounds
    const shadowColor = isDark ? 'rgba(0,0,0,0.3)' : 'rgba(0,0,0,0.15)';
    
    // Build children HTML first to avoid recursion issues
    let childrenHtml = '';
    if (hasChildren) {
        try {
            childrenHtml = node.children.map(child => generateHierarchicalTreeHtml(child, level + 1)).join('');
        } catch (childError) {
            console.error('Error generating children for node', node.id, ':', childError);
            childrenHtml = '<div style="padding: 10px; color: red;">Error loading children</div>';
        }
    }
    
    const html = `
        <div class="tree-node-container" style="margin: 20px; text-align: center;" data-node-id="${nodeId}">
            <div class="user-node" data-user-id="${node.id}" style="
                background: ${nodeColor};
                color: ${textColor};
                padding: 20px;
                border-radius: 15px;
                width: 280px;
                cursor: pointer;
                box-shadow: 0 4px 8px ${shadowColor};
                margin: 0 auto;
                position: relative;
                border: ${isDark ? 'none' : `2px solid ${borderColor}`};
            ">
                <!-- Status dot -->
                <div style="position: absolute; top: 5px; right: 5px; width: 12px; height: 12px; background: ${node.status === 'active' ? '#2ecc71' : '#e74c3c'}; border-radius: 50%;"></div>
                
                <!-- User info -->
                <div style="margin-bottom: 15px;">
                    <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.3); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: bold; margin: 0 auto 10px; color: white;">
                        ${(node.fullname || node.username).charAt(0).toUpperCase()}
                    </div>
                    <h3 style="margin: 0; font-size: 16px; color: ${textColor};">${node.fullname || node.username}</h3>
                    <p style="margin: 0; opacity: 0.8; font-size: 12px; color: ${textColor};">@${node.username}</p>
                    <p style="margin: 0; opacity: 0.7; font-size: 11px; color: ${textColor};">Level ${node.level}</p>
                </div>
                
                <!-- Stats -->
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <div style="text-align: center;">
                        <div style="font-size: 18px; font-weight: bold; color: ${textColor};">${node.directReferrals || 0}</div>
                        <div style="font-size: 10px; opacity: 0.8; color: ${textColor};">Referrals</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 18px; font-weight: bold; color: ${textColor};">$${(node.totalCommissions || 0).toFixed(0)}</div>
                        <div style="font-size: 10px; opacity: 0.8; color: ${textColor};">Commissions</div>
                    </div>
                </div>
                
                <div style="font-size: 12px; opacity: 0.9; color: ${textColor};">This Month: $${(node.monthlyCommissions || 0).toFixed(2)}</div>
                
                ${hasChildren ? `
                    <button onclick="toggleNode('${nodeId}')" style="
                        width: 100%;
                        margin-top: 10px;
                        padding: 6px;
                        background: rgba(255,255,255,0.2);
                        color: white;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                        font-size: 11px;
                    ">
                        <span id="${nodeId}-toggle">▼ ${node.children.length} Team Members</span>
                    </button>
                ` : ''}
            </div>
            
            ${hasChildren ? `
                <div id="${nodeId}-children" style="
                    margin-top: 30px;
                    display: flex;
                    justify-content: center;
                    flex-wrap: wrap;
                    gap: 20px;
                ">
                    <!-- Connection line -->
                    <div style="position: absolute; width: 2px; height: 30px; background: ${nodeColor}; top: -30px; left: 50%; transform: translateX(-50%);"></div>
                    ${childrenHtml}
                </div>
            ` : ''}
        </div>
    `;
    
    return html;
}

function generateOrganizationalTreeHtml(node, level) {
    console.log('Generating organizational node:', node.username, 'at level:', level);
    
    const hasChildren = node.children && node.children.length > 0;
    const nodeId = `node-${node.id}`;
    const levelColors = ['#2c3e50', '#34495e', '#7f8c8d', '#95a5a6', '#bdc3c7', '#ecf0f1'];
    const nodeColor = levelColors[level % levelColors.length];
    
    // Calculate total commission for this user
    const totalCommission = node.commission_info ? 
        Object.values(node.commission_info).reduce((sum, commission) => sum + parseFloat(commission.amount || 0), 0) : 0;
    
    // Build children HTML in horizontal layout
    let childrenHtml = '';
    if (hasChildren) {
        try {
            const childrenNodes = node.children.map(child => generateOrganizationalTreeHtml(child, level + 1)).join('');
            childrenHtml = `
                <div class="org-children" style="margin-top: 30px; display: flex; justify-content: center; gap: 40px; flex-wrap: wrap; position: relative;">
                    ${childrenNodes}
                </div>`;
        } catch (childError) {
            console.error('Error generating children for org node', node.id, ':', childError);
            childrenHtml = '<div style="padding: 10px; color: red; text-align: center;">Error loading children</div>';
        }
    }
    
    const html = `
        <div class="org-node-container" style="display: flex; flex-direction: column; align-items: center; position: relative;">
            <!-- Vertical connector line to parent -->
            ${level > 0 ? '<div class="org-connector-up" style="width: 2px; height: 30px; background: #3498db; position: absolute; top: -30px; z-index: 1;"></div>' : ''}
            
            <!-- Main Node Box -->
            <div id="${nodeId}" class="org-node bg-white dark:bg-gray-800 border-2 shadow-lg rounded-lg overflow-hidden"
                 style="border-color: ${nodeColor}; min-width: 200px; max-width: 250px; position: relative; z-index: 2;">
                
                <!-- User Header -->
                <div class="org-node-header p-4" style="background: linear-gradient(135deg, ${nodeColor}, ${nodeColor}dd); color: white;">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-bold text-lg">${node.username}</div>
                            <div class="text-sm opacity-90">Level ${level + 1}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs opacity-80">ID: ${node.id}</div>
                            <div class="text-xs opacity-80">GL: ${node.ginto_level || 1}</div>
                        </div>
                    </div>
                </div>
                
                <!-- User Info -->
                <div class="org-node-body p-4">
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">${node.fullname || 'N/A'}</div>
                        <div class="text-xs text-gray-600 dark:text-gray-400 break-words">${node.email}</div>
                        
                        <!-- Commission Info -->
                        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-gray-500 dark:text-gray-400">Commission</span>
                                <span class="text-sm font-bold ${totalCommission > 0 ? 'text-green-600' : 'text-gray-400'}">
                                    $${totalCommission.toFixed(2)}
                                </span>
                            </div>
                            ${hasChildren ? `<div class="text-xs text-gray-500 dark:text-gray-400 mt-1">${node.children.length} Direct Referral${node.children.length !== 1 ? 's' : ''}</div>` : ''}
                        </div>
                    </div>
                </div>
                
                <!-- Expand/Collapse Button -->
                ${hasChildren ? `
                <div class="org-node-toggle absolute -bottom-3 left-1/2 transform -translate-x-1/2 z-10">
                    <button onclick="toggleNode('${nodeId}')" 
                            class="bg-white dark:bg-gray-700 border-2 rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-gray-50 dark:hover:bg-gray-600"
                            style="border-color: ${nodeColor}; color: ${nodeColor};">
                        <i class="fas fa-chevron-down" id="${nodeId}-icon"></i>
                    </button>
                </div>` : ''}
            </div>
            
            <!-- Horizontal connector line to children -->
            ${hasChildren ? `
                <div class="org-connector-down" style="width: 2px; height: 15px; background: #3498db; margin-top: 15px; z-index: 1;"></div>
                <div class="org-connector-horizontal" style="height: 2px; background: #3498db; width: ${Math.max(200, node.children.length * 120)}px; z-index: 1;"></div>` : ''}
            
            <!-- Children Container -->
            ${childrenHtml}
        </div>
    `;
    
    return html;
}

function generateNetworkViewHtml(node, level) {
    console.log('Generating network view node:', node.username, 'at level:', level);
    
    const hasChildren = node.children && node.children.length > 0;
    const nodeId = `node-${node.id}`;
    const isDark = document.documentElement.classList.contains('dark');
    
    // Theme-aware colors
    const levelColors = isDark ? 
        ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#e67e22', '#34495e'] :
        ['#dc2626', '#2563eb', '#059669', '#d97706', '#7c3aed', '#0891b2', '#ea580c', '#374151'];
    const nodeColor = levelColors[level % levelColors.length];
    
    // Theme-aware text colors
    const textColor = isDark ? '#2c3e50' : '#111827';
    const borderColor = isDark ? 'white' : '#f3f4f6';
    const connectorColor = isDark ? '#bdc3c7' : '#9ca3af';
    
    // Calculate total commission for this user
    const totalCommission = node.commission_info ? 
        Object.values(node.commission_info).reduce((sum, commission) => sum + parseFloat(commission.amount || 0), 0) : 0;
    
    // Build children HTML in network layout
    let childrenHtml = '';
    if (hasChildren) {
        try {
            const childrenNodes = node.children.map(child => generateNetworkViewHtml(child, level + 1)).join('');
            childrenHtml = `
                <div class="network-children" style="margin-top: 40px; display: flex; justify-content: center; gap: 30px; flex-wrap: wrap; position: relative;">
                    ${childrenNodes}
                </div>`;
        } catch (childError) {
            console.error('Error generating children for network node', node.id, ':', childError);
            childrenHtml = '<div style="padding: 10px; color: red; text-align: center;">Error loading children</div>';
        }
    }
    
    const html = `
        <div class="network-node-container" style="display: flex; flex-direction: column; align-items: center; position: relative; margin: 10px;">
            <!-- Connection line to parent -->
            ${level > 0 ? `<div class="network-connector" style="width: 2px; height: 40px; background: ${connectorColor}; position: absolute; top: -40px; z-index: 1;"></div>` : ''}
            
            <!-- Person Icon Node -->
            <div id="${nodeId}" class="network-person" 
                 onmouseover="showNetworkTooltip(event, ${node.id})" 
                 onmouseout="hideNetworkTooltip()" 
                 onclick="showNetworkDetails(${node.id})"
                 style="cursor: pointer; position: relative; z-index: 2; transition: all 0.3s ease;">
                
                <!-- Person Icon -->
                <div class="person-icon" style="
                    width: 80px; 
                    height: 80px; 
                    border-radius: 50%; 
                    background: ${nodeColor}; 
                    display: flex; 
                    align-items: center; 
                    justify-content: center; 
                    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                    border: 4px solid ${borderColor};
                    position: relative;
                    margin-bottom: 8px;
                ">
                    <i class="fas fa-user" style="color: white; font-size: 32px;"></i>
                    
                    <!-- Status indicator -->
                    <div class="status-indicator" style="
                        position: absolute;
                        bottom: 5px;
                        right: 5px;
                        width: 16px;
                        height: 16px;
                        border-radius: 50%;
                        background: ${totalCommission > 0 ? '#2ecc71' : '#95a5a6'};
                        border: 2px solid ${borderColor};
                    "></div>
                    
                    <!-- Level badge -->
                    <div class="level-badge" style="
                        position: absolute;
                        top: -5px;
                        right: -5px;
                        background: ${isDark ? '#34495e' : '#374151'};
                        color: white;
                        border-radius: 50%;
                        width: 24px;
                        height: 24px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 10px;
                        font-weight: bold;
                        border: 2px solid ${borderColor};
                    ">${level + 1}</div>
                </div>
                
                <!-- Username -->
                <div class="network-username" style="
                    text-align: center;
                    font-weight: 600;
                    color: ${textColor};
                    font-size: 12px;
                    max-width: 100px;
                    word-wrap: break-word;
                    margin-bottom: 4px;
                ">${node.username}</div>
                
                <!-- Commission indicator -->
                <div class="commission-indicator" style="
                    text-align: center;
                    font-size: 10px;
                    color: ${totalCommission > 0 ? '#27ae60' : '#95a5a6'};
                    font-weight: bold;
                ">$${totalCommission.toFixed(0)}</div>
                
                <!-- Children count -->
                ${hasChildren ? `<div class="children-count" style="
                    text-align: center;
                    font-size: 9px;
                    color: #7f8c8d;
                    margin-top: 2px;
                ">${node.children.length} referral${node.children.length !== 1 ? 's' : ''}</div>` : ''}
            </div>
            
            <!-- Children Container -->
            ${childrenHtml}
        </div>
    `;
    
    return html;
}

function generateCompactTreeHtml(node, level) {
    const hasChildren = node.children && node.children.length > 0;
    const nodeId = `node-${node.id}`;
    const indentLevel = level * 25;
    const isDark = document.documentElement.classList.contains('dark');
    
    // Theme-aware colors
    const colors = isDark ? {
        background: '#111827',
        border: 'rgba(255,255,255,0.04)',
        text: '#f3f4f6',
        subtext: '#9ca3af',
        icon: '#9ca3af',
        hoverBg: '#0f1724',
        shadow: 'rgba(0,0,0,0.5)'
    } : {
        // Use a darker node background in light mode so white text is visible
        background: '#111827',
        border: 'rgba(255,255,255,0.04)',
        text: '#ffffff',
        subtext: '#9fb6d6',
        icon: '#9fb6d6',
        hoverBg: '#0f1724',
        shadow: 'rgba(0,0,0,0.5)'
    };
    
    let html = `
        <div style="margin-left: ${indentLevel}px; margin: 5px 0; display: flex; align-items: center;" data-node-id="${nodeId}">
            ${level > 0 ? `<i class="fas fa-corner-down-right mr-2" style="color: ${colors.icon}; margin-right: 8px;"></i>` : ''}
                <div class="compact-node" data-user-id="${node.id}" style="
                display: flex;
                align-items: center;
                background: ${colors.background};
                border: none;
                padding: 10px 12px;
                border-radius: 18px;
                cursor: pointer;
                box-shadow: 0 4px 10px ${colors.shadow};
                width: 200px;
                min-width: 200px;
                max-width: 200px;
                transition: background .12s ease, transform .12s ease;
                color: ${colors.text};
            " onmouseover="this.style.transform='scale(1.02)'; this.style.background='${colors.hoverBg}'" onmouseout="this.style.transform='scale(1)'; this.style.background='${colors.background}'">
                <div style="width: 34px; height: 34px; background: #0ea5e9; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; margin-right: 12px;">
                    ${node.fullname.charAt(0).toUpperCase()}
                </div>
                <div style="flex: 1; min-width:0; overflow:hidden; max-width:140px;">
                    <div style="font-weight: bold; font-size: 14px; color: ${colors.text}; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${node.fullname}</div>
                    <div style="font-size: 12px; color: ${colors.subtext}; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">L${node.level} • $${node.totalCommissions.toFixed(0)}</div>
                </div>
                ${hasChildren ? `<i class="fas fa-chevron-right" style="color: ${colors.icon};"></i>` : ''}
            </div>
        </div>
        ${hasChildren ? `
            <div id="${nodeId}-children" style="margin-left: ${indentLevel + 20}px;">
                ${node.children.map(child => generateCompactTreeHtml(child, level + 1)).join('')}
            </div>
        ` : ''}
    `;
    
    return html;
}

function toggleNode(nodeId) {
    const childrenContainer = document.getElementById(`${nodeId}-children`);
    const toggleButton = document.getElementById(`${nodeId}-toggle`);
    
    if (expandedNodes.has(nodeId)) {
        // Collapse
        childrenContainer.style.display = 'none';
        toggleButton.innerHTML = '▶ ' + toggleButton.innerHTML.split(' ')[1] + ' ' + toggleButton.innerHTML.split(' ')[2];
        expandedNodes.delete(nodeId);
    } else {
        // Expand
        childrenContainer.style.display = 'block';
        toggleButton.innerHTML = '▼ ' + toggleButton.innerHTML.split(' ')[1] + ' ' + toggleButton.innerHTML.split(' ')[2];
        expandedNodes.add(nodeId);
    }
}

function expandAll() {
    document.querySelectorAll('[data-node-id]').forEach(node => {
        const nodeId = node.dataset.nodeId;
        expandedNodes.add(nodeId);
        
        const childrenContainer = document.getElementById(`${nodeId}-children`);
        const toggleButton = document.getElementById(`${nodeId}-toggle`);
        
        if (childrenContainer) {
            childrenContainer.classList.remove('hidden');
        }
        if (toggleButton) {
            toggleButton.innerHTML = toggleButton.innerHTML.replace('Expand', 'Collapse');
        }
    });
}

function collapseAll() {
    expandedNodes.clear();
    
    document.querySelectorAll('.tree-children').forEach(container => {
        container.classList.add('hidden');
    });
    
    document.querySelectorAll('[id$="-toggle"]').forEach(button => {
        button.innerHTML = button.innerHTML.replace('Collapse', 'Expand');
    });
}

function setupSearch() {
    const searchInput = document.getElementById('userSearch');
    const resultsContainer = document.getElementById('searchResults');
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            resultsContainer.classList.add('hidden');
            return;
        }
        
        searchTimeout = setTimeout(async () => {
            try {
                const response = await fetch(`/admin/network-tree/search?q=${encodeURIComponent(query)}`);
                const result = await response.json();
                
                if (result.success) {
                    displaySearchResults(result.users);
                }
            } catch (error) {
                console.error('Search error:', error);
            }
        }, 300);
    });
    
    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
            resultsContainer.classList.add('hidden');
        }
    });
}

function displaySearchResults(users) {
    const resultsContainer = document.getElementById('searchResults');
    
    if (users.length === 0) {
        resultsContainer.innerHTML = '<div class="p-3 text-gray-500 dark:text-gray-400">No users found</div>';
    } else {
        const resultsHtml = users.map(user => `
            <div class="p-3 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer border-b border-gray-200 dark:border-gray-600 last:border-b-0" 
                 onclick="loadUserTree(${user.id})">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-medium text-gray-900 dark:text-white">${user.fullname || user.username}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">@${user.username} • Level ${user.ginto_level}</div>
                    </div>
                    <span class="text-xs bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 px-2 py-1 rounded">
                        View Tree
                    </span>
                </div>
            </div>
        `).join('');
        resultsContainer.innerHTML = resultsHtml;
    }
    
    resultsContainer.classList.remove('hidden');
}

function loadUserTree(userId) {
    document.getElementById('rootUserId').value = userId;
    document.getElementById('userSearch').value = '';
    document.getElementById('searchResults').classList.add('hidden');
    loadTree();
}

function showUserModal(userId) {
    // Find user data in current tree
    const userData = findUserInTree(currentTreeData, parseInt(userId));
    
    if (userData) {
        const modalTitle = document.getElementById('modalTitle');
        const modalContent = document.getElementById('modalContent');
        
        modalTitle.textContent = `${userData.fullname} (@${userData.username})`;
        
        modalContent.innerHTML = `
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <p class="text-sm text-gray-900 dark:text-white">${userData.email}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Level</label>
                        <p class="text-sm text-gray-900 dark:text-white">${userData.level}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                        <span class="text-sm px-2 py-1 rounded ${userData.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                            ${userData.status}
                        </span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Join Date</label>
                        <p class="text-sm text-gray-900 dark:text-white">${new Date(userData.joinDate).toLocaleDateString()}</p>
                    </div>
                </div>
                
                <div class="border-t pt-4">
                    <h4 class="font-medium text-gray-900 dark:text-white mb-3">Performance</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-blue-50 dark:bg-blue-900 p-3 rounded">
                            <div class="text-lg font-semibold text-blue-900 dark:text-blue-100">${userData.directReferrals}</div>
                            <div class="text-sm text-blue-700 dark:text-blue-300">Direct Referrals</div>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900 p-3 rounded">
                            <div class="text-lg font-semibold text-green-900 dark:text-green-100">$${userData.totalCommissions.toFixed(2)}</div>
                            <div class="text-sm text-green-700 dark:text-green-300">Total Commissions</div>
                        </div>
                    </div>
                </div>
                
                <div class="flex space-x-3 pt-4">
                    <button onclick="loadUserTree(${userData.id})" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
                        View Their Tree
                    </button>
                    <button onclick="closeModal()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded text-sm">
                        Close
                    </button>
                </div>
            </div>
        `;
        
        document.getElementById('userModal').classList.remove('hidden');
    }
}

function findUserInTree(node, targetId) {
    if (node.id === targetId) {
        return node;
    }
    
    if (node.children) {
        for (const child of node.children) {
            const result = findUserInTree(child, targetId);
            if (result) return result;
        }
    }
    
    return null;
}

function closeModal() {
    document.getElementById('userModal').classList.add('hidden');
}

function showLoading() {
    document.getElementById('loadingSpinner').classList.remove('hidden');
    document.getElementById('treeContainer').classList.add('hidden');
    document.getElementById('errorMessage').classList.add('hidden');
}

function hideLoading() {
    document.getElementById('loadingSpinner').classList.add('hidden');
}

function showError(message) {
    document.getElementById('errorMessage').classList.remove('hidden');
    document.getElementById('treeContainer').classList.add('hidden');
    hideLoading();
}

function hideError() {
    document.getElementById('errorMessage').classList.add('hidden');
}

// Enhanced tree functionality
function changeViewMode() {
    currentViewMode = document.getElementById('viewMode').value;
    
    // Update container styling based on view mode
    const container = document.getElementById('treeContainer');
    container.className = 'tree-container-' + currentViewMode;
    
    if (currentTreeData) {
        renderTree(currentTreeData);
    }
}

// Network View Functions
function showNetworkTooltip(event, userId) {
    const user = findUserInTree(currentTreeData, userId);
    if (!user) return;
    
    // Remove existing tooltip
    hideNetworkTooltip();
    
    const tooltip = document.createElement('div');
    tooltip.id = 'networkTooltip';
    tooltip.innerHTML = `
        <div style="font-weight: bold; margin-bottom: 6px;">${user.fullname || user.username}</div>
        <div style="margin-bottom: 4px;"><i class="fas fa-envelope" style="width: 12px;"></i> ${user.email}</div>
        <div style="margin-bottom: 4px;"><i class="fas fa-layer-group" style="width: 12px;"></i> Ginto Level: ${user.ginto_level || 1}</div>
        ${user.commission_info ? `<div style="margin-bottom: 4px;"><i class="fas fa-dollar-sign" style="width: 12px;"></i> Commission: $${Object.values(user.commission_info).reduce((sum, c) => sum + parseFloat(c.amount || 0), 0).toFixed(2)}</div>` : ''}
        ${user.children && user.children.length > 0 ? `<div><i class="fas fa-users" style="width: 12px;"></i> ${user.children.length} Direct Referrals</div>` : '<div style="color: #bdc3c7;">No referrals yet</div>'}
    `;
    
    // Find the network-person container (parent of person-icon)
    let personContainer = event.target;
    while (personContainer && !personContainer.classList.contains('network-person')) {
        personContainer = personContainer.parentElement;
        if (personContainer === document.body) {
            personContainer = event.target;
            break;
        }
    }
    
    // Add tooltip as sibling to the person container for proper positioning
    if (personContainer && personContainer.parentElement) {
        personContainer.parentElement.style.position = 'relative';
        personContainer.parentElement.appendChild(tooltip);
        
        // Position tooltip above the person icon
        const personRect = personContainer.getBoundingClientRect();
        const containerRect = personContainer.parentElement.getBoundingClientRect();
        
        tooltip.style.position = 'absolute';
        tooltip.style.left = '50%';
        tooltip.style.transform = 'translateX(-50%)';
        tooltip.style.bottom = '100%';
        tooltip.style.marginBottom = '10px';
        tooltip.style.zIndex = '1000';
        
        // Ensure tooltip doesn't go outside viewport
        setTimeout(() => {
            const tooltipRect = tooltip.getBoundingClientRect();
            
            // Adjust if tooltip goes off left edge
            if (tooltipRect.left < 10) {
                tooltip.style.left = '10px';
                tooltip.style.transform = 'none';
            }
            // Adjust if tooltip goes off right edge  
            else if (tooltipRect.right > window.innerWidth - 10) {
                tooltip.style.left = 'auto';
                tooltip.style.right = '10px';
                tooltip.style.transform = 'none';
            }
            
            // If tooltip goes above viewport, show below instead
            if (tooltipRect.top < 10) {
                tooltip.style.bottom = 'auto';
                tooltip.style.top = '100%';
                tooltip.style.marginTop = '10px';
                tooltip.style.marginBottom = '0';
            }
        }, 0);
    }
}

function hideNetworkTooltip() {
    const tooltip = document.getElementById('networkTooltip');
    if (tooltip) {
        tooltip.remove();
    }
}

function showNetworkDetails(userId) {
    // Use existing modal functionality. Prefer `showUserModal` (defined below).
    if (typeof showUserModal === 'function') {
        showUserModal(userId);
    } else if (typeof showUserDetails === 'function') {
        showUserDetails(userId);
    } else {
        console.warn('No user-details modal function available for userId=', userId);
    }
}

function findUserInTree(node, userId) {
    if (!node) return null;
    if (node.id === userId) return node;
    
    if (node.children) {
        for (const child of node.children) {
            const found = findUserInTree(child, userId);
            if (found) return found;
        }
    }
    return null;
}

function centerTree() {
    const container = document.getElementById('treeContainer');
    container.scrollTo({
        left: (container.scrollWidth - container.clientWidth) / 2,
        top: 0,
        behavior: 'smooth'
    });
}

function highlightPath() {
    // Add path highlighting functionality
    document.querySelectorAll('.user-node').forEach((node, index) => {
        setTimeout(() => {
            node.style.outline = '3px solid #f39c12';
            node.style.outlineOffset = '3px';
            setTimeout(() => {
                node.style.outline = 'none';
                node.style.outlineOffset = '0';
            }, 1000);
        }, index * 200);
    });
}

function showStatistics() {
    if (!currentTreeData) return;
    
    const stats = calculateTreeStatistics(currentTreeData);
    const modal = document.getElementById('userModal');
    const title = document.getElementById('modalTitle');
    const content = document.getElementById('modalContent');
    
    title.textContent = 'Network Statistics';
    content.innerHTML = `
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-blue-50 p-4 rounded">
                <h4 class="font-bold text-blue-800">Total Nodes</h4>
                <p class="text-2xl font-bold text-blue-600">${stats.totalNodes}</p>
            </div>
            <div class="bg-green-50 p-4 rounded">
                <h4 class="font-bold text-green-800">Max Depth</h4>
                <p class="text-2xl font-bold text-green-600">${stats.maxDepth}</p>
            </div>
            <div class="bg-yellow-50 p-4 rounded">
                <h4 class="font-bold text-yellow-800">Total Commission</h4>
                <p class="text-2xl font-bold text-yellow-600">$${stats.totalCommission.toFixed(2)}</p>
            </div>
            <div class="bg-purple-50 p-4 rounded">
                <h4 class="font-bold text-purple-800">Avg per Level</h4>
                <p class="text-2xl font-bold text-purple-600">${stats.avgPerLevel.toFixed(1)}</p>
            </div>
        </div>
        <div class="mt-4">
            <h4 class="font-bold mb-2">Level Distribution</h4>
            ${stats.levelDistribution.map(level => `
                <div class="flex justify-between items-center py-1 border-b border-gray-200">
                    <span>Level ${level.level}:</span>
                    <span class="font-bold">${level.count} users</span>
                </div>
            `).join('')}
        </div>
        <button onclick="closeModal()" class="w-full mt-4 bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded">Close</button>
    `;
    
    modal.classList.remove('hidden');
}

function calculateTreeStatistics(node, level = 0, stats = { totalNodes: 0, maxDepth: 0, totalCommission: 0, levels: {} }) {
    stats.totalNodes++;
    stats.maxDepth = Math.max(stats.maxDepth, level);
    stats.totalCommission += node.totalCommissions || 0;
    stats.levels[level] = (stats.levels[level] || 0) + 1;
    
    if (node.children) {
        node.children.forEach(child => {
            calculateTreeStatistics(child, level + 1, stats);
        });
    }
    
    // Convert levels object to array for easier display
    stats.levelDistribution = Object.keys(stats.levels).map(level => ({
        level: parseInt(level),
        count: stats.levels[level]
    }));
    
    stats.avgPerLevel = stats.totalNodes / (stats.maxDepth + 1);
    
    return stats;
}

function exportTree() {
    if (!currentTreeData) return;
    
    const data = JSON.stringify(currentTreeData, null, 2);
    const blob = new Blob([data], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `network-tree-${new Date().toISOString().split('T')[0]}.json`;
    a.click();
    URL.revokeObjectURL(url);
}

// Close modal when clicking outside
document.getElementById('userModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>