<!DOCTYPE html>
<html lang="en" class="dark">
<?php include __DIR__ . '/parts/head.php'; ?>
<body class="bg-gray-50 dark:bg-gray-950 min-h-screen" x-data="{ sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true' }" x-init="$watch('sidebarCollapsed', val => localStorage.setItem('sidebarCollapsed', val))">
    
    <?php include __DIR__ . '/parts/sidebar.php'; ?>
    
    <!-- Star on GitHub + Theme Toggle (aligned with sidebar header) -->
    <div class="fixed top-2 right-4 z-50 hidden lg:flex items-center gap-2" x-data="{ darkMode: document.documentElement.classList.contains('dark') }">
        <a href="https://github.com/oliverbob/ginto.ai" target="_blank" 
           class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-sm"
           title="Star us on GitHub">
            <i class="fab fa-github"></i>
            <span>Star us</span>
        </a>
        <button @click="darkMode = !darkMode; document.documentElement.classList.toggle('dark'); localStorage.setItem('theme', darkMode ? 'dark' : 'light')" 
                class="p-2 rounded-lg transition-colors group"
                title="Toggle theme">
            <i class="fas fa-sun text-lg text-yellow-400 group-hover:text-yellow-300" x-show="darkMode"></i>
            <i class="fas fa-moon text-lg text-gray-600 dark:text-gray-300 group-hover:text-gray-800 dark:group-hover:text-white" x-show="!darkMode"></i>
        </button>
    </div>
    
    <!-- Main Content -->
    <main class="main-content min-h-screen flex items-center justify-center lg:pt-0 pt-14" :class="sidebarCollapsed ? 'collapsed' : ''">
        
        <div class="w-full max-w-lg mx-auto px-4 py-16">
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-8 text-center">
                
                <!-- Lock Icon -->
                <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-gradient-to-br from-teal-100 to-cyan-100 dark:from-teal-900/30 dark:to-cyan-900/30 flex items-center justify-center">
                    <i class="fas fa-lock text-3xl text-teal-600 dark:text-teal-400"></i>
                </div>
                
                <!-- Title -->
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                    Premium Content
                </h1>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    This lesson requires a premium subscription to access.
                </p>
                
                <!-- Lesson Info -->
                <?php if (!empty($lesson)): ?>
                <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4 mb-6 text-left">
                    <h3 class="font-medium text-gray-900 dark:text-white mb-1">
                        <?= htmlspecialchars($lesson['title'] ?? 'Premium Lesson') ?>
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Part of: <?= htmlspecialchars($masterclass['title'] ?? 'Masterclass') ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <!-- What You'll Get -->
                <div class="text-left mb-6">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">With Premium, you get:</h3>
                    <ul class="space-y-2">
                        <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <i class="fas fa-check-circle text-teal-500"></i>
                            <span>Access to all masterclass lessons</span>
                        </li>
                        <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <i class="fas fa-check-circle text-teal-500"></i>
                            <span>Downloadable resources & code samples</span>
                        </li>
                        <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <i class="fas fa-check-circle text-teal-500"></i>
                            <span>Completion certificates</span>
                        </li>
                        <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <i class="fas fa-check-circle text-teal-500"></i>
                            <span>Priority support from instructors</span>
                        </li>
                    </ul>
                </div>
                
                <!-- CTAs -->
                <div class="space-y-3">
                    <?php if ($isLoggedIn ?? false): ?>
                    <a href="/upgrade?type=masterclass" class="block w-full py-3 px-4 bg-gradient-to-r from-teal-500 to-cyan-500 hover:from-teal-600 hover:to-cyan-600 text-white font-medium rounded-xl transition-colors">
                        <i class="fas fa-crown mr-2"></i>Upgrade to Premium
                    </a>
                    <?php else: ?>
                    <a href="/login?redirect=<?= urlencode($_SERVER['REQUEST_URI'] ?? '/masterclass') ?>" class="block w-full py-3 px-4 bg-gradient-to-r from-teal-500 to-cyan-500 hover:from-teal-600 hover:to-cyan-600 text-white font-medium rounded-xl transition-colors">
                        <i class="fas fa-sign-in-alt mr-2"></i>Log In to Continue
                    </a>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Don't have an account? <a href="/register" class="text-teal-600 dark:text-teal-400 hover:underline">Sign up free</a>
                    </p>
                    <?php endif; ?>
                    
                    <a href="/masterclass/<?= htmlspecialchars($masterclass['slug'] ?? '') ?>" class="block w-full py-3 px-4 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-medium rounded-xl transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Masterclass
                    </a>
                </div>
                
            </div>
            
            <!-- Free Preview Hint -->
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <i class="fas fa-lightbulb text-yellow-500 mr-1"></i>
                    Tip: Some lessons are available as free previews
                </p>
            </div>
            
        </div>
        
    </main>
    
</body>
</html>
