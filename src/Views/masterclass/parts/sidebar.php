<!-- Sidebar - Masterclass Navigation (teal/cyan theme) -->
<?php
// Get current filter from URL
$currentCategory = $_GET['category'] ?? null;
$currentStatus = $_GET['status'] ?? null;
$isAllMasterclasses = !$currentCategory && !$currentStatus;

// Category styles for visual distinction
$categoryStyles = [
    'infrastructure' => ['icon' => 'fa-server', 'color' => 'teal'],
    'containers' => ['icon' => 'fa-cubes', 'color' => 'cyan'],
    'ai-platform' => ['icon' => 'fa-robot', 'color' => 'violet'],
    'server-management' => ['icon' => 'fa-cogs', 'color' => 'amber'],
];
?>
<aside id="sidebar" 
    class="bg-white dark:bg-gray-900 flex flex-col fixed inset-y-0 left-0 z-50 sidebar-transition lg:translate-x-0 -translate-x-full text-gray-900 dark:text-gray-100 overflow-hidden border-r border-gray-200 dark:border-gray-800" 
    :class="sidebarCollapsed ? 'sidebar-collapsed' : 'sidebar-expanded'"
    role="navigation" 
    aria-label="Masterclass navigation">
    
    <!-- Top Header Row - Logo -->
    <div class="sidebar-header py-2 pl-2 pr-2">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="relative group">
                    <a href="/" class="block" title="Ginto Home">
                        <img src="/assets/images/ginto.png" alt="Ginto" class="w-7 h-7 rounded flex-shrink-0" onerror="this.style.display='none'">
                    </a>
                    <!-- Expand toggle (shown when collapsed) -->
                    <button x-show="sidebarCollapsed" @click="sidebarCollapsed = false" class="absolute inset-0 w-7 h-7 flex items-center justify-center bg-transparent rounded opacity-75 hover:opacity-100 transition-opacity text-white z-10" title="Expand sidebar">
                        <i class="fas fa-chevron-right text-sm"></i>
                    </button>
                </div>
                <span x-show="!sidebarCollapsed" class="sidebar-text text-lg font-semibold bg-gradient-to-r from-teal-500 to-cyan-500 bg-clip-text text-transparent">Masterclasses</span>
            </div>
            <!-- Collapse buttons -->
            <div x-show="!sidebarCollapsed" class="flex items-center gap-1">
                <button @click="sidebarCollapsed = true" class="hidden lg:flex p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors text-gray-400 hover:text-teal-600 dark:hover:text-teal-400" title="Collapse sidebar">
                    <i class="fas fa-chevron-left text-sm"></i>
                </button>
                <button id="sidebar-close-mobile" class="lg:hidden p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors text-gray-400 hover:text-teal-600 dark:hover:text-teal-400" title="Close sidebar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Nav Items -->
    <div class="pl-3 pr-2 pb-2 space-y-0.5">
        <!-- Ginto Chat -->
        <a href="/chat" class="nav-item flex items-center gap-2 py-1.5 px-2 rounded-md hover:bg-teal-50 dark:hover:bg-teal-900/20 text-gray-700 dark:text-gray-300 hover:text-teal-700 dark:hover:text-teal-300 text-sm transition-colors group" :class="sidebarCollapsed ? 'justify-center' : ''">
            <i class="fas fa-comments nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-teal-600 dark:group-hover:text-teal-400 flex-shrink-0 text-center"></i>
            <span x-show="!sidebarCollapsed" class="sidebar-text">Ginto Chat</span>
        </a>
        
        <!-- Courses Link -->
        <a href="/courses" class="nav-item flex items-center gap-2 py-1.5 px-2 rounded-md hover:bg-teal-50 dark:hover:bg-teal-900/20 text-gray-700 dark:text-gray-300 hover:text-teal-700 dark:hover:text-teal-300 text-sm transition-colors group" :class="sidebarCollapsed ? 'justify-center' : ''">
            <i class="fas fa-graduation-cap nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-teal-600 dark:group-hover:text-teal-400 flex-shrink-0 text-center"></i>
            <span x-show="!sidebarCollapsed" class="sidebar-text">Courses</span>
        </a>
        
        <!-- Search Masterclasses -->
        <div x-show="!sidebarCollapsed" class="nav-item relative flex items-center py-1.5 px-2">
            <input id="masterclass-search" type="search" placeholder="Search masterclasses..." autocomplete="off" class="sidebar-text w-full py-1.5 pl-8 pr-3 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-md text-sm text-gray-800 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-teal-500 focus:border-teal-500">
            <i class="fas fa-search nav-icon absolute left-4 text-gray-500 dark:text-gray-400 text-sm"></i>
        </div>
    </div>
    
    <!-- Divider -->
    <div class="mx-2 border-t border-gray-200 dark:border-gray-800"></div>
    
    <!-- Masterclass Categories -->
    <div class="flex-1 overflow-y-auto sidebar-scroll pt-2 pl-3 pr-2">
        <h2 x-show="!sidebarCollapsed" class="section-header sidebar-text text-xs font-medium text-gray-500 uppercase tracking-wider px-2 mb-2">Technologies</h2>
        <nav class="space-y-0.5">
            <!-- All Masterclasses -->
            <a href="/masterclass" class="nav-item flex items-center gap-2 py-1.5 px-2 rounded-md <?= $isAllMasterclasses ? 'bg-teal-50 dark:bg-teal-900/20 text-teal-700 dark:text-teal-300' : 'hover:bg-teal-50 dark:hover:bg-teal-900/20 text-gray-700 dark:text-gray-300 hover:text-teal-700 dark:hover:text-teal-300' ?> text-sm transition-colors group" :class="sidebarCollapsed ? 'justify-center' : ''">
                <i class="fas fa-th-large nav-icon w-5 <?= $isAllMasterclasses ? 'text-teal-600 dark:text-teal-400' : 'text-gray-500 dark:text-gray-400 group-hover:text-teal-600 dark:group-hover:text-teal-400' ?> flex-shrink-0 text-center"></i>
                <span x-show="!sidebarCollapsed" class="sidebar-text">All Masterclasses</span>
                <span x-show="!sidebarCollapsed" class="sidebar-text ml-auto text-xs <?= $isAllMasterclasses ? 'bg-teal-100 dark:bg-teal-800 text-teal-600 dark:text-teal-300' : 'text-gray-400' ?> px-1.5 py-0.5 rounded">7</span>
            </a>
            
            <!-- Infrastructure -->
            <?php $isActive = $currentCategory === 'infrastructure'; ?>
            <a href="/masterclass?category=infrastructure" class="nav-item flex items-center gap-2 py-1.5 px-2 rounded-md <?= $isActive ? 'bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300 border-l-2 border-teal-500' : 'hover:bg-teal-50 dark:hover:bg-teal-900/20 text-gray-700 dark:text-gray-300 hover:text-teal-700 dark:hover:text-teal-300' ?> text-sm transition-colors group" :class="sidebarCollapsed ? 'justify-center' : ''">
                <i class="fas fa-server nav-icon w-5 <?= $isActive ? 'text-teal-600 dark:text-teal-400' : 'text-gray-500 dark:text-gray-400 group-hover:text-teal-600 dark:group-hover:text-teal-400' ?> flex-shrink-0 text-center"></i>
                <span x-show="!sidebarCollapsed" class="sidebar-text <?= $isActive ? 'font-medium' : '' ?>">Infrastructure</span>
                <span x-show="!sidebarCollapsed" class="sidebar-text ml-auto text-xs text-gray-400">1</span>
            </a>
            
            <!-- Containers & Virtualization -->
            <?php $isActive = $currentCategory === 'containers'; ?>
            <a href="/masterclass?category=containers" class="nav-item flex items-center gap-2 py-1.5 px-2 rounded-md <?= $isActive ? 'bg-cyan-100 dark:bg-cyan-900/30 text-cyan-700 dark:text-cyan-300 border-l-2 border-cyan-500' : 'hover:bg-cyan-50 dark:hover:bg-cyan-900/20 text-gray-700 dark:text-gray-300 hover:text-cyan-700 dark:hover:text-cyan-300' ?> text-sm transition-colors group" :class="sidebarCollapsed ? 'justify-center' : ''">
                <i class="fas fa-cubes nav-icon w-5 <?= $isActive ? 'text-cyan-600 dark:text-cyan-400' : 'text-gray-500 dark:text-gray-400 group-hover:text-cyan-600 dark:group-hover:text-cyan-400' ?> flex-shrink-0 text-center"></i>
                <span x-show="!sidebarCollapsed" class="sidebar-text <?= $isActive ? 'font-medium' : '' ?>">Containers</span>
                <span x-show="!sidebarCollapsed" class="sidebar-text ml-auto text-xs text-gray-400">3</span>
            </a>
            
            <!-- AI Platform -->
            <?php $isActive = $currentCategory === 'ai-platform'; ?>
            <a href="/masterclass?category=ai-platform" class="nav-item flex items-center gap-2 py-1.5 px-2 rounded-md <?= $isActive ? 'bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 border-l-2 border-violet-500' : 'hover:bg-violet-50 dark:hover:bg-violet-900/20 text-gray-700 dark:text-gray-300 hover:text-violet-700 dark:hover:text-violet-300' ?> text-sm transition-colors group" :class="sidebarCollapsed ? 'justify-center' : ''">
                <i class="fas fa-robot nav-icon w-5 <?= $isActive ? 'text-violet-600 dark:text-violet-400' : 'text-gray-500 dark:text-gray-400 group-hover:text-violet-600 dark:group-hover:text-violet-400' ?> flex-shrink-0 text-center"></i>
                <span x-show="!sidebarCollapsed" class="sidebar-text <?= $isActive ? 'font-medium' : '' ?>">AI Platform</span>
                <span x-show="!sidebarCollapsed" class="sidebar-text ml-auto text-xs text-gray-400">1</span>
            </a>
            
            <!-- Server Management & Backend -->
            <?php $isActive = $currentCategory === 'server-management'; ?>
            <a href="/masterclass?category=server-management" class="nav-item flex items-center gap-2 py-1.5 px-2 rounded-md <?= $isActive ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border-l-2 border-amber-500' : 'hover:bg-amber-50 dark:hover:bg-amber-900/20 text-gray-700 dark:text-gray-300 hover:text-amber-700 dark:hover:text-amber-300' ?> text-sm transition-colors group" :class="sidebarCollapsed ? 'justify-center' : ''">
                <i class="fas fa-cogs nav-icon w-5 <?= $isActive ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400 group-hover:text-amber-600 dark:group-hover:text-amber-400' ?> flex-shrink-0 text-center"></i>
                <span x-show="!sidebarCollapsed" class="sidebar-text <?= $isActive ? 'font-medium' : '' ?>">Backend & Hosting</span>
                <span x-show="!sidebarCollapsed" class="sidebar-text ml-auto text-xs text-gray-400">2</span>
            </a>
        </nav>
        
        <!-- Divider -->
        <div x-show="!sidebarCollapsed" class="my-3 border-t border-gray-200 dark:border-gray-800"></div>
        
        <!-- My Learning -->
        <h2 x-show="!sidebarCollapsed" class="section-header sidebar-text text-xs font-medium text-gray-500 uppercase tracking-wider px-2 mb-2">My Learning</h2>
        <nav class="space-y-0.5">
            <!-- In Progress -->
            <?php $isActive = $currentStatus === 'progress'; ?>
            <a href="/masterclass?status=progress" class="nav-item flex items-center gap-2 py-1.5 px-2 rounded-md <?= $isActive ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300' : 'hover:bg-teal-50 dark:hover:bg-teal-900/20 text-gray-700 dark:text-gray-300 hover:text-teal-700 dark:hover:text-teal-300' ?> text-sm transition-colors group" :class="sidebarCollapsed ? 'justify-center' : ''">
                <i class="fas fa-spinner nav-icon w-5 <?= $isActive ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400 group-hover:text-teal-600 dark:group-hover:text-teal-400' ?> flex-shrink-0 text-center"></i>
                <span x-show="!sidebarCollapsed" class="sidebar-text">In Progress</span>
                <span x-show="!sidebarCollapsed" class="sidebar-text ml-auto text-xs text-gray-400">0</span>
            </a>
            
            <!-- Completed -->
            <?php $isActive = $currentStatus === 'completed'; ?>
            <a href="/masterclass?status=completed" class="nav-item flex items-center gap-2 py-1.5 px-2 rounded-md <?= $isActive ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300' : 'hover:bg-teal-50 dark:hover:bg-teal-900/20 text-gray-700 dark:text-gray-300 hover:text-teal-700 dark:hover:text-teal-300' ?> text-sm transition-colors group" :class="sidebarCollapsed ? 'justify-center' : ''">
                <i class="fas fa-check-circle nav-icon w-5 <?= $isActive ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400 group-hover:text-teal-600 dark:group-hover:text-teal-400' ?> flex-shrink-0 text-center"></i>
                <span x-show="!sidebarCollapsed" class="sidebar-text">Completed</span>
                <span x-show="!sidebarCollapsed" class="sidebar-text ml-auto text-xs text-gray-400">0</span>
            </a>
        </nav>
    </div>
    
    <!-- Divider -->
    <div class="mx-2 border-t border-gray-200 dark:border-gray-800 mt-auto"></div>
    
    <!-- User Account -->
    <div class="pl-3 pr-2 pb-2 space-y-0.5">
        <?php if ($isLoggedIn ?? false): ?>
        <div class="nav-item flex items-center gap-2 py-1.5 px-2 rounded-md hover:bg-teal-50 dark:hover:bg-teal-900/20 transition-colors cursor-pointer group" :class="sidebarCollapsed ? 'justify-center' : ''">
            <div class="nav-icon w-5 h-5 rounded-full bg-gradient-to-br from-teal-500 to-cyan-600 flex items-center justify-center text-white text-xs font-medium flex-shrink-0">
                <?= strtoupper(substr($username ?? 'U', 0, 1)) ?>
            </div>
            <div x-show="!sidebarCollapsed" class="flex-1 min-w-0 sidebar-text">
                <span class="text-sm text-gray-700 dark:text-gray-200 truncate block"><?= htmlspecialchars($userFullname ?? $username ?? 'User') ?></span>
            </div>
            <a x-show="!sidebarCollapsed" href="/logout" class="sidebar-text p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-400 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-all" title="Logout">
                <i class="fas fa-sign-out-alt text-xs"></i>
            </a>
        </div>
        <?php else: ?>
        <a href="/login" class="nav-item flex items-center gap-2 py-1.5 px-2 rounded-md hover:bg-teal-50 dark:hover:bg-teal-900/20 text-gray-700 dark:text-gray-300 hover:text-teal-700 dark:hover:text-teal-300 text-sm transition-colors group" :class="sidebarCollapsed ? 'justify-center' : ''">
            <div class="nav-icon w-5 h-5 rounded-full bg-gray-200 dark:bg-gray-800 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-user text-xs text-gray-500 dark:text-gray-400"></i>
            </div>
            <span x-show="!sidebarCollapsed" class="sidebar-text">Sign in</span>
        </a>
        <?php endif; ?>
    </div>
</aside>

<!-- Mobile Sidebar Overlay -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-20 lg:hidden hidden" @click="document.getElementById('sidebar').classList.add('-translate-x-full'); this.classList.add('hidden')"></div>

<!-- Mobile Header -->
<header class="lg:hidden fixed top-0 left-0 right-0 h-14 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between px-4 z-40">
    <button id="mobile-menu-toggle" class="p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800" @click="document.getElementById('sidebar').classList.toggle('-translate-x-full'); document.getElementById('sidebar-overlay').classList.toggle('hidden')">
        <i class="fas fa-bars text-gray-600 dark:text-gray-300"></i>
    </button>
    <span class="text-lg font-semibold bg-gradient-to-r from-teal-500 to-cyan-500 bg-clip-text text-transparent">Masterclasses</span>
    <div class="flex items-center gap-2">
        <!-- Theme Toggle -->
        <button @click="darkMode = !darkMode; document.documentElement.classList.toggle('dark'); localStorage.setItem('theme', darkMode ? 'dark' : 'light')" 
                class="p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800">
            <i class="fas fa-sun text-gray-600 dark:text-gray-300" x-show="darkMode"></i>
            <i class="fas fa-moon text-gray-600 dark:text-gray-300" x-show="!darkMode"></i>
        </button>
        <a href="/chat" class="p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800">
            <i class="fas fa-comments text-gray-600 dark:text-gray-300"></i>
        </a>
    </div>
</header>

<script>
// Mobile sidebar close
document.addEventListener('DOMContentLoaded', function() {
    const closeBtn = document.getElementById('sidebar-close-mobile');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        });
    }
});
</script>
