<!-- Desktop Top Bar (visible on lg screens) -->
<header class="hidden lg:block bg-gray-100/80 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700/50 sticky top-0 z-20 backdrop-blur-sm">
    <div class="px-6 py-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($title ?? 'Courses') ?></h1>
            
            <div class="flex items-center gap-4">
                <!-- Star on GitHub + Theme Toggle -->
                <a href="https://github.com/oliverbob/ginto.ai" target="_blank" 
                   class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors text-sm"
                   title="Star us on GitHub">
                    <i class="fab fa-github"></i>
                    <span>Star us</span>
                </a>
                <button @click="darkMode = !darkMode; document.documentElement.classList.toggle('dark'); localStorage.setItem('theme', darkMode ? 'dark' : 'light')" 
                        class="p-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
                        title="Toggle theme">
                    <i class="fas fa-sun" x-show="darkMode"></i>
                    <i class="fas fa-moon" x-show="!darkMode"></i>
                </button>
                
                <?php if ($isLoggedIn): ?>
                <span class="text-sm text-gray-600 dark:text-gray-400">Welcome, <?= htmlspecialchars($userFullname ?? $username ?? 'User') ?></span>
                <?php else: ?>
                <a href="/login" class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white text-sm transition-colors">Login</a>
                <a href="/register" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<!-- Mobile spacer for fixed header -->
<div class="h-14 lg:hidden"></div>
