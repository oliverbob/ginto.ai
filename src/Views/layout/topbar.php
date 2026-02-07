<?php
/**
 * Shared topbar partial. Renders the page header and opens `#mainContent`.
 * Expects `$title` to be set by the including view.
 */
?>

<div id="mainContent" class="min-h-screen themed-card transition-all duration-300 ease-in-out">
    <!-- Header -->
    <div class="bg-white dark:bg-gray-800 shadow-sm themed-border relative" style="height:72px; display:flex; align-items:center; padding-left:80px; padding-right:20px;">
        <div class="flex items-center py-6 justify-between w-full">
            <div>
                <h1 class="text-2xl font-bold themed-text text-left"><?= htmlspecialchars($title ?? 'Dashboard') ?></h1>
                <p class="mt-1 text-sm themed-text-secondary">Welcome — quick access to your account tools</p>
            </div>
            <div class="flex space-x-3 ml-auto">
                <!-- Theme Toggle -->
                <button id="themeToggle" onclick="toggleTheme()" class="theme-toggle bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2" title="Toggle Dark/Light Mode">
                    <i id="themeIcon" class="fas fa-moon"></i>
                    <span id="themeText" class="theme-toggle-text">Light</span>
                </button>
                <a href="/dashboard" class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-arrow-left"></i>
                    <span>Dashboard</span>
                </a>
            </div>
        </div>
    </div>
    <!-- (decorative arrow moved into the left nav sidebar so it is visible only within the nav) -->
