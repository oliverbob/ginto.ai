<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Masterclasses - Deep Dive Technical Training | Ginto AI') ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="Master infrastructure technologies with in-depth masterclasses. Learn Redis, LXC/LXD, Docker, Proxmox, Virtualmin, and the Ginto AI platform.">
    <meta name="keywords" content="masterclass, redis tutorial, lxc lxd containers, docker training, proxmox guide, virtualmin hosting, ginto ai, devops training, infrastructure, containers">
    <meta name="author" content="Ginto AI">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'ginto.ai') . '/masterclass') ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'ginto.ai') . '/masterclass') ?>">
    <meta property="og:title" content="Masterclasses - Deep Dive Technical Training | Ginto AI">
    <meta property="og:description" content="Master infrastructure technologies with hands-on masterclasses. Redis, LXC/LXD, Docker, Proxmox, Virtualmin, and the Ginto AI platform.">
    <meta property="og:image" content="/assets/images/masterclass-og.png">
    <meta property="og:site_name" content="Ginto AI">
    
    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Masterclasses - Deep Dive Technical Training | Ginto AI">
    <meta name="twitter:description" content="Master infrastructure technologies with hands-on masterclasses. Redis, LXC/LXD, Docker, Proxmox, Virtualmin, and the Ginto AI platform.">
    
    <!-- Theme Detection Script (runs FIRST before any CSS to prevent flash) -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            let shouldBeDark = savedTheme === 'dark' || (!savedTheme && systemDark);
            if (shouldBeDark) {
                document.documentElement.classList.add('dark');
            }
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
                        primary: '#14b8a6',
                        secondary: '#06b6d4',
                        dark: {
                            bg: '#1a1a2e',
                            surface: '#16213e',
                            card: '#1f2937',
                            border: '#374151'
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Local FontAwesome -->
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    
    <!-- Alpine.js for interactive components -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style>
        /* Masterclass card hover effects */
        .masterclass-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
        }
        .masterclass-card {
            transition: all 0.3s ease;
        }
        
        /* Sidebar transition */
        .sidebar-transition {
            transition: width 0.3s ease, transform 0.3s ease;
        }
        
        /* Sidebar collapsed state - hide text, center icons */
        .sidebar-collapsed .sidebar-text {
            display: none !important;
        }
        .sidebar-collapsed .nav-item {
            justify-content: center;
            padding-left: 0;
            padding-right: 0;
        }
        .sidebar-collapsed .nav-icon {
            margin: 0;
        }
        .sidebar-collapsed .section-header {
            display: none !important;
        }
        
        /* Sidebar widths - match /chat and /courses */
        .sidebar-expanded { width: 256px; } /* w-64 */
        .sidebar-collapsed { width: 44px; } /* tiny collapsed width like /chat */
        
        /* Keep logo size fixed */
        .sidebar-header img { 
            width: 1.75rem !important; 
            height: 1.75rem !important; 
            min-width: 1.75rem; 
            min-height: 1.75rem; 
        }
        
        /* Main content area - responsive to sidebar */
        @media (min-width: 1024px) {
            .main-content {
                margin-left: 256px;
                transition: margin-left 0.3s ease;
            }
            .main-content.collapsed {
                margin-left: 44px;
            }
        }
        
        /* Mobile: no margin */
        @media (max-width: 1023px) {
            .main-content {
                margin-left: 0;
            }
        }
        
        /* Progress bar animation */
        .progress-bar {
            transition: width 0.5s ease-out;
        }
        
        /* Scrollbar styling */
        .sidebar-scroll::-webkit-scrollbar { width: 4px; }
        .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
        .sidebar-scroll::-webkit-scrollbar-thumb { 
            background: rgba(20, 184, 166, 0.3); 
            border-radius: 2px; 
        }
        .sidebar-scroll::-webkit-scrollbar-thumb:hover { 
            background: rgba(20, 184, 166, 0.5); 
        }
        
        /* Dark mode support */
        .dark {
            color-scheme: dark;
        }
        
        /* =====================================================
           LESSON CONTENT STYLES
           ===================================================== */
        
        /* Content wrapper spacing */
        .lesson-content {
            line-height: 1.8;
        }
        
        .lesson-content > * {
            margin-bottom: 1.5rem;
        }
        
        .lesson-content > *:last-child {
            margin-bottom: 0;
        }
        
        /* Headings */
        .lesson-content h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #14b8a6;
            color: #111827;
        }
        
        .lesson-content h2:first-child {
            margin-top: 0;
        }
        
        .dark .lesson-content h2 {
            color: #f9fafb;
            border-bottom-color: #14b8a6;
        }
        
        .lesson-content h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-top: 2rem;
            margin-bottom: 0.75rem;
            color: #1f2937;
        }
        
        .dark .lesson-content h3 {
            color: #e5e7eb;
        }
        
        .lesson-content h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
            color: #374151;
        }
        
        .dark .lesson-content h4 {
            color: #d1d5db;
        }
        
        /* Paragraphs */
        .lesson-content p {
            margin-bottom: 1rem;
            line-height: 1.75;
        }
        
        /* Lists */
        .lesson-content ul,
        .lesson-content ol {
            margin-bottom: 1.5rem;
            padding-left: 1.5rem;
        }
        
        .lesson-content ul {
            list-style-type: disc;
        }
        
        .lesson-content ol {
            list-style-type: decimal;
        }
        
        .lesson-content li {
            margin-bottom: 0.5rem;
            line-height: 1.6;
        }
        
        .lesson-content li > ul,
        .lesson-content li > ol {
            margin-top: 0.5rem;
            margin-bottom: 0;
        }
        
        /* Info boxes - teal themed */
        .lesson-content .info-box {
            margin: 1.5rem 0;
            padding: 1rem 1.25rem;
            border-radius: 0 0.5rem 0.5rem 0;
        }
        
        .lesson-content .info-box.bg-teal-50 {
            background: rgba(20, 184, 166, 0.1);
        }
        
        .dark .lesson-content .info-box.bg-teal-50,
        .lesson-content .info-box.dark\\:bg-teal-900\\/20 {
            background: rgba(20, 184, 166, 0.15);
        }
        
        .lesson-content .border-teal-500 {
            border-color: #14b8a6;
        }
        
        .lesson-content .text-teal-700 {
            color: #0f766e;
        }
        
        .dark .lesson-content .dark\\:text-teal-300 {
            color: #5eead4;
        }
        
        .lesson-content .text-teal-600 {
            color: #0d9488;
        }
        
        .dark .lesson-content .dark\\:text-teal-400 {
            color: #2dd4bf;
        }
        
        /* Code blocks */
        .lesson-content pre {
            background: #1e293b !important;
            border: 1px solid #334155;
            border-radius: 0.75rem;
            padding: 1.25rem;
            margin: 1.5rem 0;
            overflow-x: auto;
            font-size: 0.875rem;
            line-height: 1.6;
        }
        
        .lesson-content pre code {
            background: transparent !important;
            padding: 0 !important;
            border-radius: 0 !important;
            color: #e2e8f0 !important;
            font-family: 'Fira Code', 'Monaco', 'Consolas', monospace;
            font-size: 0.875rem;
        }
        
        /* Inline code */
        .lesson-content code:not(pre code) {
            background: rgba(20, 184, 166, 0.15);
            color: #14b8a6;
            padding: 0.2rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.9em;
            font-family: 'Fira Code', 'Monaco', 'Consolas', monospace;
        }
        
        .dark .lesson-content code:not(pre code) {
            background: rgba(20, 184, 166, 0.2);
            color: #5eead4;
        }
        
        /* Tables */
        .lesson-content table {
            width: 100%;
            margin: 1.5rem 0;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        
        .lesson-content th,
        .lesson-content td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .dark .lesson-content th,
        .dark .lesson-content td {
            border-bottom-color: #374151;
        }
        
        .lesson-content th {
            font-weight: 600;
            background: #f9fafb;
        }
        
        .dark .lesson-content th {
            background: #1f2937;
        }
        
        .lesson-content tbody tr:hover {
            background: #f9fafb;
        }
        
        .dark .lesson-content tbody tr:hover {
            background: #1f2937;
        }
        
        /* Grid layouts for feature cards */
        .lesson-content .grid {
            margin: 1.5rem 0;
            display: grid;
            gap: 1rem;
        }
        
        .lesson-content .grid.grid-cols-1 {
            grid-template-columns: repeat(1, 1fr);
        }
        
        .lesson-content .grid.md\\:grid-cols-2 {
            grid-template-columns: repeat(1, 1fr);
        }
        
        @media (min-width: 768px) {
            .lesson-content .grid.md\\:grid-cols-2 {
                grid-template-columns: repeat(2, 1fr);
            }
            .lesson-content .grid.md\\:grid-cols-3 {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        /* Cards inside grids */
        .lesson-content .rounded-lg {
            border-radius: 0.75rem;
        }
        
        .lesson-content .p-4,
        .lesson-content .p-5,
        .lesson-content .p-6 {
            padding: 1.25rem;
        }
        
        .lesson-content .bg-gray-50 {
            background: #f9fafb;
        }
        
        .dark .lesson-content .dark\\:bg-gray-800\\/50,
        .dark .lesson-content .dark\\:bg-gray-800 {
            background: rgba(31, 41, 55, 0.5);
        }
        
        /* Links */
        .lesson-content a {
            color: #14b8a6;
            text-decoration: none;
        }
        
        .lesson-content a:hover {
            text-decoration: underline;
        }
        
        /* Strong/Bold */
        .lesson-content strong {
            font-weight: 600;
            color: inherit;
        }
        
        /* Emojis and icons in headings */
        .lesson-content h3 span.text-2xl,
        .lesson-content h4 span.text-xl {
            margin-right: 0.5rem;
        }
        
        /* Gradient boxes (Next Steps) - teal/cyan theme */
        .lesson-content .bg-gradient-to-r {
            margin-top: 2rem;
            padding: 1.5rem;
            border-radius: 0.75rem;
        }
        
        .lesson-content .bg-gradient-to-r.from-teal-500 {
            background: linear-gradient(to right, #14b8a6, #06b6d4);
        }
        
        .lesson-content .from-teal-500\\/20,
        .lesson-content .from-teal-500\\/10 {
            background: linear-gradient(to right, rgba(20, 184, 166, 0.15), rgba(6, 182, 212, 0.05));
        }
        
        /* Cyan colors */
        .lesson-content .text-cyan-600 {
            color: #0891b2;
        }
        
        .dark .lesson-content .dark\\:text-cyan-400 {
            color: #22d3ee;
        }
        
        /* Violet colors */
        .lesson-content .text-violet-600 {
            color: #7c3aed;
        }
        
        .dark .lesson-content .dark\\:text-violet-400 {
            color: #a78bfa;
        }
        
        /* Fix for code inside grid/cards */
        .lesson-content .rounded-lg pre {
            margin: 0.75rem 0 0 0;
            padding: 0.75rem;
            font-size: 0.8rem;
        }
        
        /* List items in cards */
        .lesson-content .rounded-lg ul {
            padding-left: 1.25rem;
            margin-bottom: 0;
        }
        
        .lesson-content .rounded-lg li {
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }
        
        /* Card headings */
        .lesson-content .rounded-lg h4,
        .lesson-content .rounded-lg h5 {
            margin-top: 0;
            margin-bottom: 0.5rem;
        }
        
        /* Flex utilities */
        .lesson-content .flex {
            display: flex;
        }
        
        .lesson-content .items-center {
            align-items: center;
        }
        
        .lesson-content .gap-2 {
            gap: 0.5rem;
        }
        
        .lesson-content .gap-3 {
            gap: 0.75rem;
        }
        
        /* Text colors for cards */
        .lesson-content .text-gray-600 {
            color: #4b5563;
        }
        
        .dark .lesson-content .dark\\:text-gray-400 {
            color: #9ca3af;
        }
        
        .lesson-content .text-teal-500 {
            color: #14b8a6;
        }
        
        /* Font sizes */
        .lesson-content .text-sm {
            font-size: 0.875rem;
        }
        
        .lesson-content .text-lg {
            font-size: 1.125rem;
        }
        
        .lesson-content .text-xl {
            font-size: 1.25rem;
        }
        
        .lesson-content .text-2xl {
            font-size: 1.5rem;
        }
        
        /* Font weights */
        .lesson-content .font-semibold {
            font-weight: 600;
        }
        
        .lesson-content .font-medium {
            font-weight: 500;
        }
        
        /* Borders */
        .lesson-content .border {
            border-width: 1px;
            border-style: solid;
        }
        
        .lesson-content .border-gray-200 {
            border-color: #e5e7eb;
        }
        
        .dark .lesson-content .dark\\:border-gray-700 {
            border-color: #374151;
        }
        
        /* Syntax highlighting colors */
        .lesson-content pre .comment { color: #6b7280; }
        .lesson-content pre .string { color: #fbbf24; }
        .lesson-content pre .keyword { color: #f472b6; }
        .lesson-content pre .function { color: #60a5fa; }
        .lesson-content pre .variable { color: #a78bfa; }
    </style>
</head>
