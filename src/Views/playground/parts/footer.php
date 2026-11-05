<?php
/**
 * Playground - Footer partial
 * Minimal footer with version and quick links
 */
?>
<footer class="mt-auto py-4 px-6 border-t border-gray-200/50 dark:border-gray-700/50 bg-white/50 dark:bg-gray-900/50 backdrop-blur-sm">
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-gray-500 dark:text-gray-400">
        <div class="flex items-center gap-4">
            <span>&copy; <?= date('Y') ?> Ginto CMS</span>
            <span class="hidden sm:inline">•</span>
            <a href="/playground/docs" class="hover:text-violet-600 dark:hover:text-violet-400 transition-colors">Docs</a>
            <a href="/playground/help" class="hover:text-violet-600 dark:hover:text-violet-400 transition-colors">Help</a>
        </div>
        <div class="flex items-center gap-3">
            <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                All systems operational
            </span>
            <span class="px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-xs font-medium">
                PHP <?= phpversion() ?>
            </span>
        </div>
    </div>
</footer>
