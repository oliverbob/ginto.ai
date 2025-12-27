<?php
/**
 * Header partial that includes the opening HTML and head section
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Ginto') ?></title>
    <!-- Local Tailwind: self-hosted (minimal/targeted) -->
    <link rel="stylesheet" href="/assets/css/tailwind.css">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
        <link rel="icon" type="image/png" href="/assets/images/ginto.png">
        <script>
            /* Using self-hosted CSS; dark-mode is controlled via the .dark class on root */
        </script>
    <!-- Use shared theme.js for persistent theming -->
    <script src="/assets/js/theme.js"></script>
    <style>
        /* Theme Variables */
        :root {
            /* Light theme (default) */
            --bg-primary: #ffffff;
            --bg-secondary: #f3f4f6;
            --text-primary: #111827;
            --text-secondary: #4b5563;
            --border-color: #e5e7eb;
            --accent-color: #3b82f6;
            --sidebar-bg: #ffffff;
            --card-bg: #ffffff;
            --hover-bg: #f3f4f6;
        }

        :root[class~="dark"] {
            /* Dark theme */
            --bg-primary: #111827;
            --bg-secondary: #1f2937;
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --border-color: #374151;
            --accent-color: #60a5fa;
            --sidebar-bg: #1f2937;
            --card-bg: #1f2937;
            --hover-bg: #374151;
        }

        /* Apply theme variables */
        body {
            background-color: var(--bg-secondary);
            color: var(--text-primary);
        }

        .themed-card {
            background-color: var(--card-bg);
            border-color: var(--border-color);
        }

        .themed-sidebar {
            background-color: var(--sidebar-bg);
            border-color: var(--border-color);
        }

        .themed-border {
            border-color: var(--border-color);
        }

        .themed-text {
            color: var(--text-primary);
        }

        .themed-text-secondary {
            color: var(--text-secondary);
        }

        .themed-hover:hover {
            background-color: var(--hover-bg);
        }
    </style>
</head>
<body class="antialiased">
    <header id="ginto-header" class="relative shadow-md sticky top-0 z-50 transition-all duration-500">
        <div class="absolute inset-0 w-full h-full pointer-events-none" style="z-index:0;">
            <div id="gold-gradient-bg" style="width:100%;height:100%;background:linear-gradient(90deg,#bfa14a 0%,#ffe066 100%);opacity:1;transition:opacity 0.5s;"></div>
        </div>
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between relative" style="z-index:1;">
            <a href="/" class="flex items-center space-x-3">
                <img src="/assets/images/ginto.png" alt="Ginto Logo" style="width:40px;height:40px;border-radius:50%;box-shadow:0 0 8px #bfa14a;">
                <span class="text-2xl font-bold text-yellow-700">Ginto</span>
            </a>
            <!-- ...existing code... -->
        </div>
    </header>
    <script>
    // Gold accent fade on scroll
    document.addEventListener('DOMContentLoaded', function() {
        var goldBg = document.getElementById('gold-gradient-bg');
        var header = document.getElementById('ginto-header');
        function fadeGold() {
            var getStarted = document.querySelector('#how-it-works, #get-started, [id*="get-started"]');
            if (!getStarted || !goldBg) return;
            var rect = getStarted.getBoundingClientRect();
            var windowH = window.innerHeight || document.documentElement.clientHeight;
            var fadeStart = 0;
            var fadeEnd = rect.top - header.offsetHeight;
            var scrollY = window.scrollY || window.pageYOffset;
            var opacity = 1;
            if (fadeEnd < windowH) {
                var progress = Math.max(0, Math.min(1, (windowH - fadeEnd) / (windowH/2)));
                opacity = 1 - progress;
            }
            goldBg.style.opacity = opacity;
        }
        window.addEventListener('scroll', fadeGold);
        window.addEventListener('resize', fadeGold);
        fadeGold();
    });
    </script>

    <!-- Back to Top Button (Bottom Right) -->
    <button id="backToTop" class="fixed bottom-6 right-4 p-3 rounded-full bg-blue-500 text-white shadow-2xl hover:bg-blue-600 transition-all duration-200 transform z-40 opacity-0 pointer-events-none translate-y-4" aria-label="Back to top">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path d="M3 10a1 1 0 001.707.707L9 6.414V17a1 1 0 102 0V6.414l4.293 4.293A1 1 0 0017 10l-6-6-6 6z" />
        </svg>
    </button>