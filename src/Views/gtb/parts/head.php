<?php // gtb/parts/head.php - <head> for Ginto Trading Bot ?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Ginto Trading Bot (GTB) - automated trading dashboard.">
    <title><?= htmlspecialchars($title ?? 'Ginto Trading Bot') ?></title>

    <!-- Theme Detection Script (runs FIRST before any CSS to prevent flash) -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            let shouldBeDark = savedTheme === 'dark' || (savedTheme !== 'light' && (systemDark || true));
            document.documentElement.classList.toggle('dark', shouldBeDark);
        })();
    </script>

    <!-- Tailwind CSS (local, same as /chat) -->
    <script src="/assets/js/tailwindcss.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#6366f1',
                        secondary: '#8b5cf6',
                        dark: {
                            bg: '#1a1a2e',
                            surface: '#16213e',
                            card: '#1f2937',
                            border: '#374151'
                        }
                    }
                }
            }
        };
    </script>

    <!-- Local FontAwesome -->
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">

    <!-- Alpine.js for interactive components -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- TradingView Lightweight Charts (candlestick charts) -->
    <script src="https://cdn.jsdelivr.net/npm/lightweight-charts@4.2.0/dist/lightweight-charts.standalone.production.js"></script>

    <style>
        .dark { color-scheme: dark; }
    </style>
</head>
