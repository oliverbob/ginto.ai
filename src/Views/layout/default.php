<?php
/**
 * Default layout file with dark/light theme support
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
    <!-- Using self-hosted CSS; dark-mode styles are driven by the .dark class in the markup -->
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

    <!-- Back to Top Button (Bottom Right) -->
    <button id="backToTop" class="fixed bottom-6 right-4 p-3 rounded-full bg-blue-500 text-white shadow-2xl hover:bg-blue-600 transition-all duration-200 transform z-40 opacity-0 pointer-events-none translate-y-4" aria-label="Back to top">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path d="M3 10a1 1 0 001.707.707L9 6.414V17a1 1 0 102 0V6.414l4.293 4.293A1 1 0 0017 10l-6-6-6 6z" />
        </svg>
    </button>

    <!-- Main Content -->
    <main class="min-h-screen transition-colors duration-200">
        <?= $content ?? '' ?>
    </main>

    <!-- Theme Management Script -->
    <script>
        // Always use localStorage for theme if set
        if (localStorage.theme === 'dark') {
            document.documentElement.classList.add('dark');
        } else if (localStorage.theme === 'light') {
            document.documentElement.classList.remove('dark');
        } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        } else {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        }
        // Listen for system theme changes only if not set in localStorage
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if (!('theme' in localStorage)) {
                if (e.matches) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }
        });
    </script>
</body>
</html>
