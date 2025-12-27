<?php
// Get courses from database (passed from controller)
$courses = $courses ?? [];
$isLoggedIn = $isLoggedIn ?? false;
$userPlan = $userPlan ?? 'free';

// Course icons and colors mapping (slugs must match database)
$courseStyles = [
    'touch-typing' => [
        'gradient' => 'from-indigo-600 to-violet-600',
        'tag_bg' => 'bg-indigo-100 dark:bg-indigo-500/20',
        'tag_text' => 'text-indigo-600 dark:text-indigo-400',
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5M6 12h.008v.008H6V12zm3.75 0h.008v.008H9.75V12zm3.75 0h.008v.008h-.008V12zm3.75 0h.008v.008h-.008V12zM6 15.75h.008v.008H6v-.008zm3.75 0h.008v.008H9.75v-.008zm3.75 0h.008v.008h-.008v-.008zm3.75 0h.008v.008h-.008v-.008zM6 8.25h.008v.008H6V8.25zm3.75 0h.008v.008H9.75V8.25zm3.75 0h.008v.008h-.008V8.25zm3.75 0h.008v.008h-.008V8.25z"/>'
    ],
    'intro-to-ai' => [
        'gradient' => 'from-blue-600 to-purple-600',
        'tag_bg' => 'bg-blue-100 dark:bg-blue-500/20',
        'tag_text' => 'text-blue-600 dark:text-blue-400',
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5"/>'
    ],
    'web-development' => [
        'gradient' => 'from-green-600 to-teal-600',
        'tag_bg' => 'bg-green-100 dark:bg-green-500/20',
        'tag_text' => 'text-green-600 dark:text-green-400',
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5"/>'
    ],
    'ai-marketing' => [
        'gradient' => 'from-orange-600 to-red-600',
        'tag_bg' => 'bg-orange-100 dark:bg-orange-500/20',
        'tag_text' => 'text-orange-600 dark:text-orange-400',
        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6"/>'
    ]
];

// Default style for courses not in the map
$defaultStyle = [
    'gradient' => 'from-gray-600 to-gray-700',
    'tag_bg' => 'bg-gray-100 dark:bg-gray-500/20',
    'tag_text' => 'text-gray-600 dark:text-gray-400',
    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>'
];

// Get current category filter
$categoryFilter = $categoryFilter ?? $_GET['category'] ?? null;

// Category-specific content
$categoryInfo = [
    'fundamentals' => [
        'title' => 'Fundamentals',
        'subtitle' => 'Build Your Foundation',
        'description' => 'Master the essential skills every professional needs. Start with touch typing to boost your productivity.',
        'gradient' => 'from-indigo-600 to-violet-700',
        'icon' => 'fa-keyboard',
        'cta' => 'Start with Typing'
    ],
    'ai' => [
        'title' => 'AI & Machine Learning',
        'subtitle' => 'Understand the Future',
        'description' => 'Learn how artificial intelligence works and how to leverage it in your career. No coding required to start.',
        'gradient' => 'from-blue-600 to-purple-700',
        'icon' => 'fa-brain',
        'cta' => 'Explore AI'
    ],
    'development' => [
        'title' => 'Web Development',
        'subtitle' => 'Build the Web',
        'description' => 'Learn HTML, CSS, and JavaScript to create modern, responsive websites from scratch.',
        'gradient' => 'from-green-600 to-teal-700',
        'icon' => 'fa-code',
        'cta' => 'Start Coding'
    ],
    'marketing' => [
        'title' => 'AI Marketing',
        'subtitle' => 'Scale Your Reach',
        'description' => 'Leverage AI agents to automate campaigns, analyze data, and grow your business at scale.',
        'gradient' => 'from-orange-600 to-red-700',
        'icon' => 'fa-chart-line',
        'cta' => 'Learn AI Marketing'
    ]
];

$currentCat = $categoryFilter ? ($categoryInfo[$categoryFilter] ?? null) : null;
?>

<?php if ($currentCat): ?>
<!-- Category-Specific Hero -->
<div class="course-hero bg-gradient-to-r <?= $currentCat['gradient'] ?> py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row items-center gap-8">
            <div class="w-24 h-24 bg-white/20 rounded-2xl flex items-center justify-center">
                <i class="fas <?= $currentCat['icon'] ?> text-5xl text-white"></i>
            </div>
            <div class="text-center md:text-left">
                <p class="text-white/70 text-sm uppercase tracking-wider mb-1"><?= $currentCat['subtitle'] ?></p>
                <h2 class="text-4xl font-bold text-white mb-3"><?= $currentCat['title'] ?></h2>
                <p class="text-xl text-white/80 max-w-2xl"><?= $currentCat['description'] ?></p>
            </div>
        </div>
        <div class="mt-8 flex flex-wrap gap-4 justify-center md:justify-start">
            <span class="px-4 py-2 bg-white/20 rounded-full text-white text-sm">
                <i class="fas fa-book mr-2"></i><?= count($courses) ?> Course<?= count($courses) !== 1 ? 's' : '' ?>
            </span>
            <span class="px-4 py-2 bg-white/20 rounded-full text-white text-sm">
                <i class="fas fa-clock mr-2"></i>Self-paced
            </span>
            <span class="px-4 py-2 bg-white/20 rounded-full text-white text-sm">
                <i class="fas fa-certificate mr-2"></i>Certificate Available
            </span>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Default Hero Section -->
<div class="course-hero bg-gradient-to-r from-indigo-800 to-purple-900 dark:from-indigo-900 dark:to-purple-950 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-4xl font-bold text-white mb-4">Learn & Grow</h2>
        <p class="text-xl text-gray-200 dark:text-gray-300 max-w-2xl mx-auto">
            Explore our curated courses designed to help you master new skills and advance your career.
        </p>
        
        <!-- Quick Stats -->
        <div class="mt-8 flex justify-center gap-8 text-white/80">
            <div class="text-center">
                <div class="text-3xl font-bold"><?= count($courses) ?></div>
                <div class="text-sm">Courses</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold">57+</div>
                <div class="text-sm">Lessons</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold">4</div>
                <div class="text-sm">Categories</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!$categoryFilter || $categoryFilter === 'fundamentals'): ?>
<!-- Typing Course Special Section -->
<div class="course-hero bg-gradient-to-r from-indigo-600 to-violet-600 py-8 border-b border-indigo-500">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5M6 12h.008v.008H6V12zm3.75 0h.008v.008H9.75V12zm3.75 0h.008v.008h-.008V12zm3.75 0h.008v.008h-.008V12z"/>
                    </svg>
                </div>
                <div class="text-white">
                    <h3 class="text-xl font-bold">🎹 Practice Typing Now!</h3>
                    <p class="text-indigo-200">Jump into our interactive typing trainer - no signup required</p>
                </div>
            </div>
            <a href="/typing.html" target="_blank" class="bg-white text-indigo-600 font-semibold px-6 py-3 rounded-lg hover:bg-indigo-50 transition-colors flex items-center gap-2">
                <span>Launch Typing Trainer</span>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                </svg>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($categoryFilter === 'ai'): ?>
<!-- AI Chat CTA Section -->
<div class="course-hero bg-gradient-to-r from-blue-600 to-purple-600 py-8 border-b border-blue-500">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center">
                    <i class="fas fa-robot text-3xl text-white"></i>
                </div>
                <div class="text-white">
                    <h3 class="text-xl font-bold">🤖 Experience AI Right Now!</h3>
                    <p class="text-blue-200">Chat with our AI assistant to see AI in action</p>
                </div>
            </div>
            <a href="/chat" class="bg-white text-blue-600 font-semibold px-6 py-3 rounded-lg hover:bg-blue-50 transition-colors flex items-center gap-2">
                <span>Open AI Chat</span>
                <i class="fas fa-comments"></i>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($categoryFilter === 'development'): ?>
<!-- Code Editor CTA Section -->
<div class="course-hero bg-gradient-to-r from-green-600 to-teal-600 py-8 border-b border-green-500">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center">
                    <i class="fas fa-laptop-code text-3xl text-white"></i>
                </div>
                <div class="text-white">
                    <h3 class="text-xl font-bold">💻 Learn by Building</h3>
                    <p class="text-green-200">Create real projects with hands-on coding exercises</p>
                </div>
            </div>
            <a href="/courses/web-development" class="bg-white text-green-600 font-semibold px-6 py-3 rounded-lg hover:bg-green-50 transition-colors flex items-center gap-2">
                <span>Start Course</span>
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($categoryFilter === 'marketing'): ?>
<!-- Marketing Tools CTA Section -->
<div class="course-hero bg-gradient-to-r from-orange-600 to-red-600 py-8 border-b border-orange-500">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center">
                    <i class="fas fa-magic text-3xl text-white"></i>
                </div>
                <div class="text-white">
                    <h3 class="text-xl font-bold">🚀 AI-Powered Marketing</h3>
                    <p class="text-orange-200">Use AI to create content, analyze data, and scale campaigns</p>
                </div>
            </div>
            <a href="/chat" class="bg-white text-orange-600 font-semibold px-6 py-3 rounded-lg hover:bg-orange-50 transition-colors flex items-center gap-2">
                <span>Try AI Content Writer</span>
                <i class="fas fa-pen-fancy"></i>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Courses Grid -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    
    <?php if (empty($courses)): ?>
    <!-- No courses available -->
    <div class="text-center py-12">
        <div class="w-20 h-20 mx-auto bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mb-4">
            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
            </svg>
        </div>
        <h3 class="text-xl font-semibold text-gray-700 dark:text-gray-300 mb-2">No Courses Yet</h3>
        <p class="text-gray-500 dark:text-gray-400">Check back soon for our upcoming courses!</p>
    </div>
    <?php else: ?>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
        <?php foreach ($courses as $course): 
            $style = $courseStyles[$course['slug']] ?? $defaultStyle;
            $lessonCount = $course['lesson_count'] ?? 0;
        ?>
        
        <a href="/courses/<?= htmlspecialchars($course['slug']) ?>" class="course-card group bg-white dark:bg-gray-800 rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-lg transition-all duration-300 block">
            <div class="h-48 bg-gradient-to-br <?= $style['gradient'] ?> flex items-center justify-center relative overflow-hidden">
                <svg class="w-20 h-20 text-white/80 group-hover:scale-110 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <?= $style['icon'] ?>
                </svg>
                
                <?php if ($course['is_featured'] ?? false): ?>
                <span class="absolute top-3 right-3 bg-yellow-400 text-yellow-900 text-xs font-bold px-2 py-1 rounded">⭐ Featured</span>
                <?php endif; ?>
            </div>
            <div class="p-6">
                <div class="flex items-center gap-2 mb-3">
                    <span class="<?= $style['tag_bg'] ?> <?= $style['tag_text'] ?> text-xs px-2 py-1 rounded-full">
                        <?= htmlspecialchars($course['category_name'] ?? 'General') ?>
                    </span>
                    <span class="text-gray-500 text-xs"><?= $lessonCount ?> Lessons</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    <?= htmlspecialchars($course['title']) ?>
                </h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm mb-4 line-clamp-2">
                    <?= htmlspecialchars($course['description']) ?>
                </p>
                <div class="flex items-center justify-between">
                    <?php if ($course['slug'] === 'touch-typing'): ?>
                    <span class="text-green-600 dark:text-green-400 font-medium text-sm flex items-center gap-1">
                        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                        Live Now
                    </span>
                    <?php else: ?>
                    <span class="<?= $style['tag_text'] ?> font-medium text-sm">
                        <?= $course['is_published'] ? 'Available' : 'Coming Soon' ?>
                    </span>
                    <?php endif; ?>
                    <span class="text-gray-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 group-hover:translate-x-1 transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                        </svg>
                    </span>
                </div>
            </div>
        </a>
        
        <?php endforeach; ?>
    </div>
    
    <?php endif; ?>
    
    <!-- Subscription CTA -->
    <div class="mt-16 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl p-8 md:p-12 text-center text-white">
        <h3 class="text-2xl md:text-3xl font-bold mb-4">Get Unlimited Access</h3>
        <p class="text-purple-100 mb-6 max-w-2xl mx-auto">
            Upgrade to unlock all lessons, get AI tutor assistance, earn certificates, and access premium content.
        </p>
        <div class="flex flex-col sm:flex-row justify-center gap-4">
            <a href="/courses/pricing" class="bg-white text-indigo-600 font-semibold px-8 py-3 rounded-lg hover:bg-indigo-50 transition-colors">
                View Plans
            </a>
            <?php if (!$isLoggedIn): ?>
            <a href="/register" class="bg-white/20 text-white font-semibold px-8 py-3 rounded-lg hover:bg-white/30 transition-colors border border-white/30">
                Sign Up Free
            </a>
            <?php endif; ?>
        </div>
        <p class="text-purple-200 text-sm mt-4">Starting at ₱250/month • Cancel anytime</p>
    </div>
    
    <!-- How It Works -->
    <div class="mt-16">
        <h3 class="text-2xl font-bold text-center text-gray-900 dark:text-white mb-8">How It Works</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="w-16 h-16 mx-auto bg-indigo-100 dark:bg-indigo-500/20 rounded-full flex items-center justify-center mb-4">
                    <span class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">1</span>
                </div>
                <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Choose a Course</h4>
                <p class="text-gray-600 dark:text-gray-400 text-sm">Browse our catalog and find the skills you want to learn.</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 mx-auto bg-purple-100 dark:bg-purple-500/20 rounded-full flex items-center justify-center mb-4">
                    <span class="text-2xl font-bold text-purple-600 dark:text-purple-400">2</span>
                </div>
                <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Learn at Your Pace</h4>
                <p class="text-gray-600 dark:text-gray-400 text-sm">Access lessons anytime, anywhere. Track your progress as you go.</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 mx-auto bg-green-100 dark:bg-green-500/20 rounded-full flex items-center justify-center mb-4">
                    <span class="text-2xl font-bold text-green-600 dark:text-green-400">3</span>
                </div>
                <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Master & Grow</h4>
                <p class="text-gray-600 dark:text-gray-400 text-sm">Complete courses, earn certificates, and advance your career.</p>
            </div>
        </div>
    </div>
</div>
