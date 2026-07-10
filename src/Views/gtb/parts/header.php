<?php
// gtb/parts/header.php - Navigation bar for Ginto Trading Bot
$isLoggedIn   = $isLoggedIn ?? false;
$username     = $username ?? null;
$userFullname = $userFullname ?? null;
?>
<nav class="bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center gap-6">
                <a href="/chat" class="flex items-center gap-2 text-gray-500 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors">
                    <i class="fas fa-arrow-left"></i>
                    <span class="hidden sm:inline">Ginto</span>
                </a>
                <h1 class="flex items-center gap-2 text-xl font-semibold text-gray-900 dark:text-white">
                    <i class="fas fa-robot text-primary"></i>
                    Ginto Trading Bot
                    <span class="ml-1 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded bg-primary/10 text-primary">GTB</span>
                </h1>
            </div>
            <div class="flex items-center gap-4">
                <button type="button" id="gtb-theme-toggle" onclick="gtbToggleTheme()" title="Toggle dark / light"
                        class="w-9 h-9 inline-flex items-center justify-center rounded-lg border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-300 hover:text-primary hover:border-primary transition-colors">
                    <i class="fas fa-moon gtb-theme-dark-icon"></i>
                    <i class="fas fa-sun gtb-theme-light-icon hidden"></i>
                </button>
                <?php if ($isLoggedIn): ?>
                    <span class="text-sm text-gray-500 dark:text-gray-300">
                        <i class="fas fa-user-circle mr-1"></i><?= htmlspecialchars($userFullname ?? $username ?? 'Account') ?>
                    </span>
                <?php else: ?>
                    <a href="/login" class="text-sm text-gray-500 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">Login</a>
                    <a href="/register" class="text-sm px-3 py-1.5 rounded-md bg-primary text-white hover:bg-primary/90">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
