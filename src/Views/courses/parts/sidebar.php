<!-- Sidebar - Courses Navigation (matches /chat sidebar pattern) -->
<?php
// Get current filter from URL
$currentCategory = $_GET['category'] ?? null;
$currentStatus = $_GET['status'] ?? null;
$isAllCourses = !$currentCategory && !$currentStatus;

// Category styles for visual distinction
$categoryStyles = [
    'fundamentals' => ['icon' => 'fa-keyboard', 'color' => 'indigo', 'gradient' => 'from-indigo-500 to-violet-500'],
    'ai' => ['icon' => 'fa-brain', 'color' => 'purple', 'gradient' => 'from-blue-500 to-purple-500'],
    'development' => ['icon' => 'fa-code', 'color' => 'green', 'gradient' => 'from-green-500 to-teal-500'],
    'marketing' => ['icon' => 'fa-chart-line', 'color' => 'orange', 'gradient' => 'from-orange-500 to-red-500'],
];
?>
<aside id="sidebar" 
    class="bg-white dark:bg-gray-900 flex flex-col fixed inset-y-0 left-0 z-50 sidebar-transition lg:translate-x-0 -translate-x-full text-gray-900 dark:text-gray-100 overflow-hidden border-r border-gray-200 dark:border-gray-800" 
    :class="sidebarCollapsed ? 'sidebar-collapsed' : 'sidebar-expanded'"
    role="navigation" 
    aria-label="Courses navigation">
    
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
                <span x-show="!sidebarCollapsed" class="sidebar-text text-lg font-semibold bg-gradient-to-r from-indigo-500 to-purple-500 bg-clip-text text-transparent">Courses</span>
            </div>
            <!-- Close/Collapse buttons - stacked in same position -->
            <div class="flex items-center gap-1">
                <!-- Desktop collapse button -->
                <button x-show="!sidebarCollapsed" @click="sidebarCollapsed = true" class="hidden lg:flex p-1 rounded hover:bg-indigo-100 dark:hover:bg-indigo-900/30 transition-colors text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400" title="Collapse sidebar">
                    <i class="fas fa-chevron-left text-sm"></i>
                </button>
                <!-- Mobile close button -->
                <button id="sidebar-close-mobile" class="lg:hidden p-1 rounded hover:bg-indigo-100 dark:hover:bg-indigo-900/30 transition-colors text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400" title="Close sidebar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Nav Items -->
    <div class="pl-3 pr-2 pb-2 space-y-0.5">
        <!-- Ginto Chat -->
        <a href="/chat" class="nav-item flex items-center gap-2 py-1.5 px-2 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-700 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300 text-sm transition-colors group" :class="sidebarCollapsed ? 'justify-center' : ''">
            <i class="fas fa-comments nav-icon w-5 h-5 text-gray-500 dark:text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 flex-shrink-0 text-center"></i>
            <span x-show="!sidebarCollapsed" class="sidebar-text">Ginto Chat</span>
        </a>
        
        <!-- Search Courses -->
        <div x-show="!sidebarCollapsed" class="nav-item relative flex items-center py-1.5 px-2">
            <input id="course-search" type="search" placeholder="Search courses..." autocomplete="off" class="sidebar-text w-full py-1.5 pl-8 pr-3 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-md text-sm text-gray-800 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
            <i class="fas fa-search nav-icon absolute left-4 text-gray-500 dark:text-gray-400 text-sm"></i>
        </div>
    </div>
    
    <!-- Divider -->
    <div class="mx-2 border-t border-gray-200 dark:border-gray-800"></div>
    
    <!-- Course Categories -->
    <div class="flex-1 overflow-y-auto sidebar-scroll pt-2 pl-3 pr-2">
        <h2 x-show="!sidebarCollapsed" class="section-header sidebar-text text-xs font-medium text-gray-500 uppercase tracking-wider px-2 mb-2">Categories</h2>
        <nav class="space-y-0.5">
            <!-- All Courses -->
            <a href="/courses" class="nav-item flex items-center gap-2 py-1.5 px-2 rounded-md <?= $isAllCourses ? 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300' : 'hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-700 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300' ?> text-sm transition-colors group" :class="sidebarCollapsed ? 'justify-center' : ''">
                <i class="fas fa-th-large nav-icon w-5 <?= $isAllCourses ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-500 dark:text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400' ?> flex-shrink-0 text-center"></i>
                <span x-show="!sidebarCollapsed" class="sidebar-text">All Courses</span>
                <span x-show="!sidebarCollapsed" class="sidebar-text ml-auto text-xs <?= $isAllCourses ? 'bg-indigo-100 dark:bg-indigo-800 text-indigo-600 dark:text-indigo-300' : 'text-gray-400' ?> px-1.5 py-0.5 rounded">4</span>
            </a>
            
            <!-- Fundamentals -->
            <?php $isActive = $currentCategory === 'fundamentals'; ?>
            <a href="/courses?category=fundamentals" class="nav-item flex items-center gap-2 py-1.5 px-2 rounded-md <?= $isActive ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 border-l-2 border-indigo-500' : 'hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-700 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300' ?> text-sm transition-colors group" :class="sidebarCollapsed ? 'justify-center' : ''">
                <i class="fas fa-keyboard nav-icon w-5 <?= $isActive ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-500 dark:text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400' ?> flex-shrink-0 text-center"></i>
                <span x-show="!sidebarCollapsed" class="sidebar-text <?= $isActive ? 'font-medium' : '' ?>">Fundamentals</span>
                <span x-show="!sidebarCollapsed" class="sidebar-text ml-auto text-xs <?= $isActive ? 'bg-indigo-200 dark:bg-indigo-800 text-indigo-700 dark:text-indigo-300 px-1.5 py-0.5 rounded' : 'text-gray-400' ?>">1</span>
            </a>
            
            <!-- AI & ML -->
            <?php $isActive = $currentCategory === 'ai'; ?>
            <a href="/courses?category=ai" class="nav-item flex items-center gap-2 py-1.5 px-2 rounded-md <?= $isActive ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 border-l-2 border-purple-500' : 'hover:bg-purple-50 dark:hover:bg-purple-900/20 text-gray-700 dark:text-gray-300 hover:text-purple-700 dark:hover:text-purple-300' ?> text-sm transition-colors group" :class="sidebarCollapsed ? 'justify-center' : ''">
                <i class="fas fa-brain nav-icon w-5 <?= $isActive ? 'text-purple-600 dark:text-purple-400' : 'text-gray-500 dark:text-gray-400 group-hover:text-purple-600 dark:group-hover:text-purple-400' ?> flex-shrink-0 text-center"></i>
                <span x-show="!sidebarCollapsed" class="sidebar-text <?= $isActive ? 'font-medium' : '' ?>">AI & ML</span>
                <span x-show="!sidebarCollapsed" class="sidebar-text ml-auto text-xs <?= $isActive ? 'bg-purple-200 dark:bg-purple-800 text-purple-700 dark:text-purple-300 px-1.5 py-0.5 rounded' : 'text-gray-400' ?>">1</span>
            </a>
            
            <!-- Development -->
            <?php $isActive = $currentCategory === 'development'; ?>
            <a href="/courses?category=development" class="nav-item flex items-center gap-2 py-1.5 px-2 rounded-md <?= $isActive ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 border-l-2 border-green-500' : 'hover:bg-green-50 dark:hover:bg-green-900/20 text-gray-700 dark:text-gray-300 hover:text-green-700 dark:hover:text-green-300' ?> text-sm transition-colors group" :class="sidebarCollapsed ? 'justify-center' : ''">
                <i class="fas fa-code nav-icon w-5 <?= $isActive ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400 group-hover:text-green-600 dark:group-hover:text-green-400' ?> flex-shrink-0 text-center"></i>
                <span x-show="!sidebarCollapsed" class="sidebar-text <?= $isActive ? 'font-medium' : '' ?>">Development</span>
                <span x-show="!sidebarCollapsed" class="sidebar-text ml-auto text-xs <?= $isActive ? 'bg-green-200 dark:bg-green-800 text-green-700 dark:text-green-300 px-1.5 py-0.5 rounded' : 'text-gray-400' ?>">1</span>
            </a>
            
            <!-- AI Marketing -->
            <?php $isActive = $currentCategory === 'marketing'; ?>
            <a href="/courses?category=marketing" class="nav-item flex items-center gap-2 py-1.5 px-2 rounded-md <?= $isActive ? 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300 border-l-2 border-orange-500' : 'hover:bg-orange-50 dark:hover:bg-orange-900/20 text-gray-700 dark:text-gray-300 hover:text-orange-700 dark:hover:text-orange-300' ?> text-sm transition-colors group" :class="sidebarCollapsed ? 'justify-center' : ''">
                <i class="fas fa-chart-line nav-icon w-5 <?= $isActive ? 'text-orange-600 dark:text-orange-400' : 'text-gray-500 dark:text-gray-400 group-hover:text-orange-600 dark:group-hover:text-orange-400' ?> flex-shrink-0 text-center"></i>
                <span x-show="!sidebarCollapsed" class="sidebar-text <?= $isActive ? 'font-medium' : '' ?>">AI Marketing</span>
                <span x-show="!sidebarCollapsed" class="sidebar-text ml-auto text-xs <?= $isActive ? 'bg-orange-200 dark:bg-orange-800 text-orange-700 dark:text-orange-300 px-1.5 py-0.5 rounded' : 'text-gray-400' ?>">1</span>
            </a>
        </nav>
        
        <!-- Divider -->
        <div x-show="!sidebarCollapsed" class="my-3 border-t border-gray-200 dark:border-gray-800"></div>
        
        <!-- My Learning -->
        <h2 x-show="!sidebarCollapsed" class="section-header sidebar-text text-xs font-medium text-gray-500 uppercase tracking-wider px-2 mb-2">My Learning</h2>
        <nav class="space-y-0.5">
            <!-- In Progress -->
            <?php $isActive = $currentStatus === 'progress'; ?>
            <a href="/courses?status=progress" class="nav-item flex items-center gap-2 py-1.5 px-2 rounded-md <?= $isActive ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300' : 'hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-700 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300' ?> text-sm transition-colors group" :class="sidebarCollapsed ? 'justify-center' : ''">
                <i class="fas fa-spinner nav-icon w-5 <?= $isActive ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400' ?> flex-shrink-0 text-center"></i>
                <span x-show="!sidebarCollapsed" class="sidebar-text">In Progress</span>
                <span x-show="!sidebarCollapsed" class="sidebar-text ml-auto text-xs text-gray-400">0</span>
            </a>
            
            <!-- Completed -->
            <?php $isActive = $currentStatus === 'completed'; ?>
            <a href="/courses?status=completed" class="nav-item flex items-center gap-2 py-1.5 px-2 rounded-md <?= $isActive ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300' : 'hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-700 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300' ?> text-sm transition-colors group" :class="sidebarCollapsed ? 'justify-center' : ''">
                <i class="fas fa-check-circle nav-icon w-5 <?= $isActive ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400' ?> flex-shrink-0 text-center"></i>
                <span x-show="!sidebarCollapsed" class="sidebar-text">Completed</span>
                <span x-show="!sidebarCollapsed" class="sidebar-text ml-auto text-xs text-gray-400">0</span>
            </a>
            
            <!-- Bookmarked -->
            <?php $isActive = $currentStatus === 'bookmarked'; ?>
            <a href="/courses?status=bookmarked" class="nav-item flex items-center gap-2 py-1.5 px-2 rounded-md <?= $isActive ? 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300' : 'hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-700 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300' ?> text-sm transition-colors group" :class="sidebarCollapsed ? 'justify-center' : ''">
                <i class="fas fa-bookmark nav-icon w-5 <?= $isActive ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-500 dark:text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400' ?> flex-shrink-0 text-center"></i>
                <span x-show="!sidebarCollapsed" class="sidebar-text">Bookmarked</span>
                <span x-show="!sidebarCollapsed" class="sidebar-text ml-auto text-xs text-gray-400">0</span>
            </a>
        </nav>
    </div>
    
    <!-- Divider -->
    <div class="mx-2 border-t border-gray-200 dark:border-gray-800 mt-auto"></div>
    
    <!-- User Account -->
    <div class="pl-3 pr-2 pb-2 space-y-0.5">
        <?php if ($isLoggedIn): ?>
        <div class="nav-item flex items-center gap-2 py-1.5 px-2 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors cursor-pointer group" :class="sidebarCollapsed ? 'justify-center' : ''">
            <div class="nav-icon w-5 h-5 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-xs font-medium flex-shrink-0">
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
        <a href="/login" class="nav-item flex items-center gap-2 py-1.5 px-2 rounded-md hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-700 dark:text-gray-300 hover:text-indigo-700 dark:hover:text-indigo-300 text-sm transition-colors group" :class="sidebarCollapsed ? 'justify-center' : ''">
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
    <span class="text-lg font-semibold bg-gradient-to-r from-indigo-500 to-purple-500 bg-clip-text text-transparent">Courses</span>
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
