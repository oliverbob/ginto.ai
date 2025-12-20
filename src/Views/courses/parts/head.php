<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Online Courses - Learn AI, Web Development & More | Ginto') ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="Explore free online courses in Touch Typing, Artificial Intelligence, Web Development, and AI-powered Digital Marketing. Start learning today and advance your tech career with Ginto.">
    <meta name="keywords" content="online courses, learn AI, artificial intelligence course, web development tutorial, touch typing course, digital marketing, AI marketing, free coding courses, learn programming, tech skills">
    <meta name="author" content="Ginto">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'ginto.dev') . '/courses') ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'ginto.dev') . '/courses') ?>">
    <meta property="og:title" content="Online Courses - Learn AI, Web Development & Digital Marketing | Ginto">
    <meta property="og:description" content="Master in-demand tech skills with our curated courses. Learn touch typing, AI fundamentals, web development, and AI-powered marketing strategies.">
    <meta property="og:image" content="/assets/images/courses-og.png">
    <meta property="og:site_name" content="Ginto">
    
    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'ginto.dev') . '/courses') ?>">
    <meta name="twitter:title" content="Online Courses - Learn AI, Web Development & Digital Marketing | Ginto">
    <meta name="twitter:description" content="Master in-demand tech skills with our curated courses. Learn touch typing, AI fundamentals, web development, and AI-powered marketing strategies.">
    <meta name="twitter:image" content="/assets/images/courses-og.png">
    
    <!-- Structured Data for SEO (JSON-LD) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "ItemList",
        "name": "Ginto Online Courses",
        "description": "Curated online courses for learning tech skills",
        "url": "<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'ginto.dev') . '/courses') ?>",
        "numberOfItems": 4,
        "itemListElement": [
            {
                "@type": "Course",
                "position": 1,
                "name": "Touch Typing Mastery",
                "description": "Build speed and accuracy with proper keyboard technique — essential for every programmer.",
                "provider": {
                    "@type": "Organization",
                    "name": "Ginto"
                },
                "educationalLevel": "Beginner",
                "about": "Keyboard skills and typing proficiency"
            },
            {
                "@type": "Course",
                "position": 2,
                "name": "Introduction to AI",
                "description": "Learn the fundamentals of artificial intelligence and machine learning concepts.",
                "provider": {
                    "@type": "Organization",
                    "name": "Ginto"
                },
                "educationalLevel": "Beginner",
                "about": "Artificial Intelligence and Machine Learning"
            },
            {
                "@type": "Course",
                "position": 3,
                "name": "Web Development Basics",
                "description": "Master HTML, CSS, and JavaScript to build modern web applications.",
                "provider": {
                    "@type": "Organization",
                    "name": "Ginto"
                },
                "educationalLevel": "Beginner",
                "about": "Web Development with HTML, CSS, JavaScript"
            },
            {
                "@type": "Course",
                "position": 4,
                "name": "Agentic Digital Marketing",
                "description": "Learn to leverage AI agents for automated, intelligent digital marketing campaigns.",
                "provider": {
                    "@type": "Organization",
                    "name": "Ginto"
                },
                "educationalLevel": "Intermediate",
                "about": "AI-powered Digital Marketing"
            }
        ]
    }
    </script>
    
    <!-- Theme Detection Script (runs FIRST before any CSS to prevent flash) -->
    <script>
        (function() {
            // Check for saved theme preference or system preference
            const savedTheme = localStorage.getItem('theme');
            const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            // Determine if dark mode should be applied
            let shouldBeDark = false;
            
            if (savedTheme === 'dark') {
                shouldBeDark = true;
            } else if (savedTheme === 'light') {
                shouldBeDark = false;
            } else {
                // No saved preference - default to dark or follow system
                shouldBeDark = systemDark || true; // Default to dark if no preference
            }
            
            if (shouldBeDark) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
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
    
    <style>
        /* Course card hover effects */
        .course-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
        }
        .course-card {
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
        
        /* Sidebar widths - match /chat */
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
        
        /* Dark mode support */
        .dark {
            color-scheme: dark;
        }
    </style>
</head>
