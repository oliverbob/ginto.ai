<?php
/**
 * Playground - Sidebar partial
 * Modern collapsible sidebar with dev tools navigation
 */
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Menu structure with categories
$menuItems = [
    'main' => [
        'title' => 'Main',
            'items' => [
            ['icon' => 'home', 'label' => 'Dashboard', 'href' => '/dashboard', 'badge' => null],
            ['icon' => 'terminal', 'label' => 'Console', 'href' => '/playground/console', 'badge' => null],
            ['icon' => 'code', 'label' => 'Code Editor', 'href' => '/playground/editor', 'badge' => 'New'],
        ]
    ],
    'tools' => [
        'title' => 'Dev Tools',
        'items' => [
            ['icon' => 'database', 'label' => 'Database Explorer', 'href' => '/playground/database', 'badge' => null],
            ['icon' => 'api', 'label' => 'API Tester', 'href' => '/playground/api', 'badge' => null],
            ['icon' => 'webhook', 'label' => 'Webhooks', 'href' => '/playground/webhooks', 'badge' => '3'],
            ['icon' => 'logs', 'label' => 'Log Viewer', 'href' => '/playground/logs', 'badge' => null],
            ['icon' => 'cache', 'label' => 'Cache Manager', 'href' => '/playground/cache', 'badge' => null],
        ]
    ],
    'ai' => [
        'title' => 'AI & MCP',
        'items' => [
            ['icon' => 'brain', 'label' => 'MCP Tools', 'href' => '/playground/mcp', 'badge' => null],
            ['icon' => 'chat', 'label' => 'AI Chat', 'href' => '/playground/ai-chat', 'badge' => null],
            ['icon' => 'mic', 'label' => 'Speech (STT/TTS)', 'href' => '/playground/speech', 'badge' => null],
        ]
    ],
    'testing' => [
        'title' => 'Testing',
        'items' => [
            ['icon' => 'test', 'label' => 'Unit Tests', 'href' => '/playground/tests', 'badge' => null],
            ['icon' => 'bug', 'label' => 'Debug Mode', 'href' => '/playground/debug', 'badge' => null],
            ['icon' => 'performance', 'label' => 'Performance', 'href' => '/playground/perf', 'badge' => null],
        ]
    ],
    'docs' => [
        'title' => 'Resources',
        'items' => [
            ['icon' => 'docs', 'label' => 'Documentation', 'href' => '/playground/docs', 'badge' => null],
            ['icon' => 'github', 'label' => 'GitHub', 'href' => '/playground/github', 'badge' => null],
            ['icon' => 'help', 'label' => 'Help & Support', 'href' => '/playground/help', 'badge' => null],
        ]
    ],
];

// SVG icons map
$icons = [
    'home' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
    'terminal' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
    'code' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>',
    'database' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>',
    'api' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
    'webhook' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>',
    'logs' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
    'cache' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>',
    'brain' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>',
    'chat' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>',
    'mic' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>',
    'test' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
    'bug' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>',
    'performance' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>',
    'docs' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>',
    'github' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>',
    'help' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
];

function isActive($href, $currentPath) {
    if ($href === '/playground') {
        return $currentPath === '/playground' || $currentPath === '/playground/';
    }
    return strpos($currentPath, $href) === 0;
}
?>

<!-- Sidebar Overlay (mobile) -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-40 lg:hidden opacity-0 invisible transition-all duration-300"></div>

<!-- Sidebar -->
<aside id="sidebar" class="sidebar fixed left-0 top-14 bottom-0 bg-white dark:bg-gray-900 border-r border-gray-200/50 dark:border-gray-700/50 z-50 overflow-hidden flex flex-col">
    
    <!-- Scrollable menu area -->
    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-6">
        <?php foreach ($menuItems as $category => $section): ?>
        <div class="menu-section">
            <!-- Section title -->
            <h3 class="sidebar-text px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 transition-opacity duration-200">
                <?= $section['title'] ?>
            </h3>
            
            <!-- Menu items -->
            <ul class="space-y-1">
                <?php foreach ($section['items'] as $item): 
                    $active = isActive($item['href'], $currentPath);
                ?>
                <li>
                    <a href="<?= $item['href'] ?>" 
                       class="menu-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200
                              <?= $active 
                                  ? 'bg-gradient-to-r from-violet-500/10 to-purple-500/10 text-violet-600 dark:text-violet-400' 
                                  : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white' ?>">
                        
                        <!-- Icon -->
                        <span class="sidebar-icon flex-shrink-0 w-5 h-5 transition-all duration-200 <?= $active ? 'text-violet-500' : '' ?>">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <?= $icons[$item['icon']] ?? $icons['code'] ?>
                            </svg>
                        </span>
                        
                        <!-- Label -->
                        <span class="sidebar-text flex-1 whitespace-nowrap transition-opacity duration-200">
                            <?= $item['label'] ?>
                        </span>
                        
                        <!-- Badge -->
                        <?php if ($item['badge']): ?>
                        <span class="sidebar-text px-2 py-0.5 text-[10px] font-bold rounded-full transition-opacity duration-200
                                     <?= is_numeric($item['badge']) 
                                         ? 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400' 
                                         : 'bg-violet-100 dark:bg-violet-900/30 text-violet-600 dark:text-violet-400' ?>">
                            <?= $item['badge'] ?>
                        </span>
                        <?php endif; ?>
                        
                        <!-- Tooltip (shown when collapsed) -->
                        <span class="sidebar-tooltip"><?= $item['label'] ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>
    </nav>
    
    <!-- Sidebar footer -->
    <div class="flex-shrink-0 p-3 border-t border-gray-200/50 dark:border-gray-700/50">
        <!-- Environment indicator -->
        <div class="sidebar-text mb-3 px-3 py-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                <span class="text-xs font-medium text-amber-700 dark:text-amber-400">Development Mode</span>
            </div>
        </div>
        
        <!-- Version info -->
        <div class="sidebar-text flex items-center justify-between px-3 text-xs text-gray-400 dark:text-gray-500">
            <span>Ginto CMS</span>
            <span>v1.0.0</span>
        </div>
    </div>
</aside>
