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

        /* When a themed-hoverable element is hovered, make its text/icons more prominent
           so chevrons and similar controls remain readable in both themes. */
        .themed-hover:hover .themed-text-secondary,
        .themed-hover:hover .themed-text {
            color: var(--text-primary) !important;
            transition: color .12s ease;
        }
    </style>
</head>


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

