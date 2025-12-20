<?php
// /src/Views/chat/prompts/prompts.php
// Define role-based prompts for chat UI.
// Three roles: admin, user (logged-in), visitor (guest)

return [
    // =====================================================
    // ADMIN PROMPTS - Repository/system management tasks
    // =====================================================
    'admin' => [
        [
            'title' => '🔧 Show project structure',
            'prompt' => 'Describe the folder and file structure of this repository, including key components and their purposes.'
        ],
        [
            'title' => '🛠️ List MCP tools',
            'prompt' => 'List all available MCP tools in this project and explain what each one does.'
        ],
        [
            'title' => '📊 Recent git activity',
            'prompt' => 'Show the most recent git commits and summarize what changes were made.'
        ],
        [
            'title' => '🚀 Deployment guide',
            'prompt' => 'Explain the deployment process for this project step by step.'
        ]
    ],
    
    // =====================================================
    // USER PROMPTS - Logged-in users with sandboxes
    // =====================================================
    'user' => [
        [
            'title' => '🔍 Latest AI news',
            'prompt' => 'Search the web for the latest breakthroughs in artificial intelligence from this week.'
        ],
        [
            'title' => '📚 Explain quantum computing',
            'prompt' => 'Help me understand quantum computing. Start with the basics and explain qubits, superposition, and why it matters.'
        ],
        [
            'title' => '💻 Build a REST API',
            'prompt' => 'Help me create a simple REST API. Ask me what programming language I prefer and what the API should do.'
        ],
        [
            'title' => '🎯 Career advice',
            'prompt' => 'I want career advice for breaking into tech. Ask me about my background and interests to give personalized suggestions.'
        ]
    ],
    
    // =====================================================
    // VISITOR PROMPTS - Course-focused prompts for guests
    // These prompts guide visitors to explore courses
    // The controller randomly selects 4 from this pool
    // =====================================================
    'visitor' => [
        // Touch Typing Course Prompts
        [
            'title' => '⌨️ Learn touch typing',
            'prompt' => '[COURSE_QUERY] I want to improve my typing speed. Tell me about the Touch Typing Mastery course and how it can help programmers type faster and more accurately.',
            'course_link' => '/courses?category=fundamentals'
        ],
        [
            'title' => '🚀 Type like a pro',
            'prompt' => '[COURSE_QUERY] How important is typing speed for programmers? What courses do you offer to help improve keyboard skills?',
            'course_link' => '/courses?category=fundamentals'
        ],
        
        // AI Course Prompts
        [
            'title' => '🤖 Start learning AI',
            'prompt' => '[COURSE_QUERY] I\'m new to artificial intelligence. What beginner courses do you have to help me understand AI and machine learning?',
            'course_link' => '/courses?category=ai'
        ],
        [
            'title' => '🧠 Understand AI basics',
            'prompt' => '[COURSE_QUERY] Explain what I\'ll learn in the Introduction to AI course. Is it suitable for complete beginners?',
            'course_link' => '/courses?category=ai'
        ],
        
        // Web Development Course Prompts
        [
            'title' => '💻 Build websites',
            'prompt' => '[COURSE_QUERY] I want to learn web development from scratch. What does the Web Development Basics course cover?',
            'course_link' => '/courses?category=development'
        ],
        [
            'title' => '🌐 Code my first site',
            'prompt' => '[COURSE_QUERY] What programming languages will I learn in the web development course? How long does it take to complete?',
            'course_link' => '/courses?category=development'
        ],
        
        // AI Marketing Course Prompts
        [
            'title' => '📊 AI for marketing',
            'prompt' => '[COURSE_QUERY] Tell me about the Agentic Digital Marketing course. How can AI help with marketing campaigns?',
            'course_link' => '/courses?category=marketing'
        ],
        [
            'title' => '📈 Grow with AI agents',
            'prompt' => '[COURSE_QUERY] What is agentic marketing? How does your course teach using AI agents for digital marketing?',
            'course_link' => '/courses?category=marketing'
        ],
        
        // General Course Discovery Prompts
        [
            'title' => '📚 Explore all courses',
            'prompt' => '[COURSE_QUERY] What courses are available on Ginto? Give me an overview of all the learning paths I can take.',
            'course_link' => '/courses'
        ],
        [
            'title' => '🎓 Start my journey',
            'prompt' => '[COURSE_QUERY] I\'m a beginner looking to get into tech. Which course should I start with and why?',
            'course_link' => '/courses'
        ],
        [
            'title' => '🏆 Skill up today',
            'prompt' => '[COURSE_QUERY] What career skills can I gain from your courses? How do they prepare me for the job market?',
            'course_link' => '/courses'
        ],
        [
            'title' => '✨ Free learning path',
            'prompt' => '[COURSE_QUERY] Are there any free courses available? What\'s the best way to start learning on Ginto?',
            'course_link' => '/courses'
        ]
    ],
    
    // =====================================================
    // COURSE CATALOG - Used by AI to answer course queries
    // This provides context for [COURSE_QUERY] prompts
    // =====================================================
    'course_catalog' => [
        [
            'name' => 'Touch Typing Mastery',
            'category' => 'Fundamentals',
            'lessons' => 15,
            'description' => 'Build speed and accuracy with proper keyboard technique — essential for every programmer.',
            'skills' => ['Touch typing', 'Keyboard proficiency', 'Typing speed', 'Accuracy'],
            'audience' => 'Beginners, programmers, anyone wanting to type faster',
            'link' => '/courses?category=fundamentals'
        ],
        [
            'name' => 'Introduction to AI',
            'category' => 'AI & ML',
            'lessons' => 12,
            'description' => 'Learn the fundamentals of artificial intelligence and machine learning concepts.',
            'skills' => ['AI basics', 'Machine learning concepts', 'Neural networks', 'AI applications'],
            'audience' => 'Beginners curious about AI, aspiring data scientists',
            'link' => '/courses?category=ai'
        ],
        [
            'name' => 'Web Development Basics',
            'category' => 'Development',
            'lessons' => 20,
            'description' => 'Master HTML, CSS, and JavaScript to build modern web applications.',
            'skills' => ['HTML', 'CSS', 'JavaScript', 'Responsive design', 'Web fundamentals'],
            'audience' => 'Aspiring web developers, career changers, entrepreneurs',
            'link' => '/courses?category=development'
        ],
        [
            'name' => 'Agentic Digital Marketing',
            'category' => 'AI Marketing',
            'lessons' => 10,
            'description' => 'Learn to leverage AI agents for automated, intelligent digital marketing campaigns.',
            'skills' => ['AI agents', 'Marketing automation', 'Campaign optimization', 'Analytics'],
            'audience' => 'Marketers, business owners, growth hackers',
            'link' => '/courses?category=marketing'
        ]
    ]
];
