<!DOCTYPE html>
<html lang="en" class="dark">
<?php include __DIR__ . '/parts/head.php'; ?>
<body class="bg-gray-50 dark:bg-gray-950 min-h-screen" x-data="{ sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true' }" x-init="$watch('sidebarCollapsed', val => localStorage.setItem('sidebarCollapsed', val))">
    
    <?php include __DIR__ . '/parts/sidebar.php'; ?>
    
    <!-- Star on GitHub + Theme Toggle (aligned with sidebar header) -->
    <div class="fixed top-2 right-4 z-50 hidden lg:flex items-center gap-2" x-data="{ darkMode: document.documentElement.classList.contains('dark') }">
        <a href="https://github.com/oliverbob/ginto.ai" target="_blank" 
           class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-sm"
           title="Star us on GitHub">
            <i class="fab fa-github"></i>
            <span>Star us</span>
        </a>
        <button @click="darkMode = !darkMode; document.documentElement.classList.toggle('dark'); localStorage.setItem('theme', darkMode ? 'dark' : 'light')" 
                class="p-2 rounded-lg transition-colors group"
                title="Toggle theme">
            <i class="fas fa-sun text-lg text-yellow-400 group-hover:text-yellow-300" x-show="darkMode"></i>
            <i class="fas fa-moon text-lg text-gray-600 dark:text-gray-300 group-hover:text-gray-800 dark:group-hover:text-white" x-show="!darkMode"></i>
        </button>
    </div>
    
    <!-- Main Content -->
    <main class="main-content min-h-screen lg:pt-0 pt-14" :class="sidebarCollapsed ? 'collapsed' : ''">
        
        <?php
        // Define unique hero content per category/filter
        $heroConfig = [
            'infrastructure' => [
                'gradient' => 'from-rose-600 via-pink-500 to-fuchsia-500 dark:from-rose-800 dark:via-pink-700 dark:to-fuchsia-700',
                'title' => 'Infrastructure Mastery',
                'description' => 'Learn Redis, caching strategies, and the data layer technologies that power high-performance applications.',
                'tags' => [
                    ['icon' => 'fas fa-database', 'label' => 'Redis'],
                    ['icon' => 'fas fa-bolt', 'label' => 'Caching'],
                    ['icon' => 'fas fa-tachometer-alt', 'label' => 'Performance'],
                ]
            ],
            'containers' => [
                'gradient' => 'from-cyan-600 via-blue-500 to-indigo-500 dark:from-cyan-800 dark:via-blue-700 dark:to-indigo-700',
                'title' => 'Container Technologies',
                'description' => 'Master containerization with LXC/LXD, Docker, and Proxmox. Build isolated, scalable, and portable environments.',
                'tags' => [
                    ['icon' => 'fas fa-cube', 'label' => 'LXC/LXD'],
                    ['icon' => 'fab fa-docker', 'label' => 'Docker'],
                    ['icon' => 'fas fa-server', 'label' => 'Proxmox'],
                ]
            ],
            'ai-platform' => [
                'gradient' => 'from-violet-600 via-purple-500 to-fuchsia-500 dark:from-violet-800 dark:via-purple-700 dark:to-fuchsia-700',
                'title' => 'AI Platform Development',
                'description' => 'Build your own AI-powered applications with the Ginto platform. Learn MCP tools, provider integration, and deployment.',
                'tags' => [
                    ['icon' => 'fas fa-robot', 'label' => 'Ginto AI'],
                    ['icon' => 'fas fa-brain', 'label' => 'MCP Tools'],
                    ['icon' => 'fas fa-code', 'label' => 'Integration'],
                ]
            ],
            'server-management' => [
                'gradient' => 'from-amber-600 via-orange-500 to-yellow-500 dark:from-amber-800 dark:via-orange-700 dark:to-yellow-700',
                'title' => 'Server Management',
                'description' => 'Master web hosting, backend development, and server administration with Virtualmin and modern MVC patterns.',
                'tags' => [
                    ['icon' => 'fas fa-globe', 'label' => 'Virtualmin'],
                    ['icon' => 'fas fa-layer-group', 'label' => 'MVC'],
                    ['icon' => 'fas fa-cogs', 'label' => 'Backend'],
                ]
            ],
            'default' => [
                'gradient' => 'from-teal-600 via-teal-500 to-cyan-500 dark:from-teal-800 dark:via-teal-700 dark:to-cyan-700',
                'title' => 'Master Infrastructure Technologies',
                'description' => 'Deep-dive technical training on Redis, LXC/LXD, Docker, Proxmox, Virtualmin, and the Ginto AI platform.',
                'tags' => [
                    ['icon' => 'fas fa-database', 'label' => 'Redis'],
                    ['icon' => 'fas fa-cube', 'label' => 'LXC/LXD'],
                    ['icon' => 'fab fa-docker', 'label' => 'Docker'],
                    ['icon' => 'fas fa-server', 'label' => 'Proxmox'],
                    ['icon' => 'fas fa-robot', 'label' => 'Ginto AI'],
                ]
            ],
        ];
        
        // Get hero config based on current filter
        $currentHero = $heroConfig[$categoryFilter] ?? $heroConfig['default'];
        ?>
        
        <!-- Hero Section -->
        <div class="bg-gradient-to-br <?= $currentHero['gradient'] ?>">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
                <div class="text-center">
                    <h1 class="text-3xl lg:text-4xl font-bold text-white mb-4">
                        <?= htmlspecialchars($currentHero['title']) ?>
                    </h1>
                    <p class="text-lg text-white/80 max-w-2xl mx-auto mb-6">
                        <?= htmlspecialchars($currentHero['description']) ?>
                    </p>
                    <div class="flex flex-wrap justify-center gap-3">
                        <?php foreach ($currentHero['tags'] as $tag): ?>
                        <span class="inline-flex items-center gap-2 px-4 py-2 bg-white/20 backdrop-blur rounded-full text-white text-sm">
                            <i class="<?= $tag['icon'] ?>"></i> <?= $tag['label'] ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Masterclass Grid -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            
            <!-- Section Title -->
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                    <?php if ($categoryFilter): ?>
                        <?= htmlspecialchars(ucfirst(str_replace('-', ' ', $categoryFilter))) ?> Masterclasses
                    <?php elseif ($statusFilter): ?>
                        <?= $statusFilter === 'progress' ? 'In Progress' : ucfirst($statusFilter) ?>
                    <?php else: ?>
                        All Masterclasses
                    <?php endif; ?>
                </h2>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    <?= count($statusFilter ? $enrolledMasterclasses : $masterclasses) ?> masterclass<?= count($statusFilter ? $enrolledMasterclasses : $masterclasses) !== 1 ? 'es' : '' ?>
                </span>
            </div>
            
            <?php 
            $displayMasterclasses = $statusFilter ? $enrolledMasterclasses : $masterclasses;
            if (empty($displayMasterclasses)): 
            ?>
            <div class="text-center py-16">
                <i class="fas fa-graduation-cap text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
                <h3 class="text-xl font-medium text-gray-600 dark:text-gray-400 mb-2">No masterclasses found</h3>
                <p class="text-gray-500 dark:text-gray-500">
                    <?php if ($statusFilter): ?>
                        Start a masterclass to see it here!
                    <?php else: ?>
                        Check back soon for new masterclasses.
                    <?php endif; ?>
                </p>
                <?php if ($categoryFilter || $statusFilter): ?>
                <a href="/masterclass" class="inline-flex items-center gap-2 mt-4 px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 transition-colors">
                    <i class="fas fa-arrow-left"></i>
                    View All Masterclasses
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($displayMasterclasses as $mc): ?>
                <?php
                    // Unique colors per masterclass for visual variety
                    $masterclassColors = [
                        'redis-mastery' => 'teal',
                        'lxc-lxd-containers' => 'orange',
                        'docker-complete' => 'sky',
                        'proxmox-virtualization' => 'emerald',
                        'virtualmin-hosting' => 'amber',
                        'ginto-ai-platform' => 'violet',
                        'mvc-backend-development' => 'slate',
                    ];
                    $color = $masterclassColors[$mc['slug'] ?? ''] ?? 'teal';
                    
                    // Difficulty badge
                    $difficultyClass = match($mc['difficulty_level'] ?? 'intermediate') {
                        'beginner' => 'badge-beginner',
                        'intermediate' => 'badge-intermediate',
                        'advanced' => 'badge-advanced',
                        'expert' => 'badge-expert',
                        default => 'badge-intermediate'
                    };
                ?>
                <a href="/masterclass/<?= htmlspecialchars($mc['slug']) ?>" class="masterclass-card block bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden hover:border-<?= $color ?>-300 dark:hover:border-<?= $color ?>-700 transition-all">
                    <!-- Card Header with gradient -->
                    <div class="h-32 bg-gradient-to-br from-<?= $color ?>-500 to-<?= $color ?>-600 dark:from-<?= $color ?>-700 dark:to-<?= $color ?>-800 relative">
                        <?php if ($mc['is_featured'] ?? false): ?>
                        <span class="absolute top-3 right-3 px-2 py-1 bg-white/20 backdrop-blur text-white text-xs font-medium rounded-full">
                            <i class="fas fa-star mr-1"></i> Featured
                        </span>
                        <?php endif; ?>
                        <div class="absolute bottom-3 left-3">
                            <span class="px-2 py-1 bg-white/20 backdrop-blur text-white text-xs font-medium rounded-full">
                                <i class="fas fa-<?= htmlspecialchars($mc['category_icon'] ?? 'folder') ?> mr-1"></i>
                                <?= htmlspecialchars($mc['category_name'] ?? 'General') ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Card Body -->
                    <div class="p-5">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1 group-hover:text-<?= $color ?>-600 dark:group-hover:text-<?= $color ?>-400">
                            <?= htmlspecialchars($mc['title']) ?>
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                            <?= htmlspecialchars($mc['subtitle'] ?? '') ?>
                        </p>
                        
                        <!-- Meta info -->
                        <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400 mb-3">
                            <span class="inline-flex items-center gap-1">
                                <i class="fas fa-clock"></i>
                                <?= htmlspecialchars($mc['estimated_hours'] ?? '0') ?>h
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <i class="fas fa-list"></i>
                                <?= htmlspecialchars($mc['total_lessons'] ?? '0') ?> lessons
                            </span>
                            <span class="<?= $difficultyClass ?> px-2 py-0.5 rounded-full text-xs font-medium">
                                <?= ucfirst($mc['difficulty_level'] ?? 'intermediate') ?>
                            </span>
                        </div>
                        
                        <!-- Progress bar (if enrolled) -->
                        <?php if (isset($mc['progress_percent']) && $mc['progress_percent'] > 0): ?>
                        <div class="mt-3">
                            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                                <span>Progress</span>
                                <span><?= number_format($mc['progress_percent'], 0) ?>%</span>
                            </div>
                            <div class="h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="progress-bar h-full bg-gradient-to-r from-teal-500 to-cyan-500 rounded-full" style="width: <?= $mc['progress_percent'] ?>%"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Instructor -->
                        <div class="flex items-center gap-2 mt-4 pt-3 border-t border-gray-100 dark:border-gray-800">
                            <div class="w-7 h-7 rounded-full bg-gradient-to-br from-teal-500 to-cyan-500 flex items-center justify-center text-white text-xs font-bold">
                                <?= strtoupper(substr($mc['instructor_name'] ?? 'G', 0, 1)) ?>
                            </div>
                            <span class="text-sm text-gray-600 dark:text-gray-400"><?= htmlspecialchars($mc['instructor_name'] ?? 'Instructor') ?></span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
        </div>
        
        <!-- CTA Section -->
        <div class="bg-gray-100 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 text-center">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-3">
                    Ready to Master These Technologies?
                </h2>
                <p class="text-gray-600 dark:text-gray-400 mb-6 max-w-2xl mx-auto">
                    By mastering Ginto AI, you'll learn multiple technologies superpowered by AI—containers, caching, streaming, and intelligent agents.
                </p>
                <a href="/masterclass/ginto-ai-platform" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-teal-500 to-cyan-500 text-white font-medium rounded-lg hover:from-teal-600 hover:to-cyan-600 transition-colors shadow-lg shadow-teal-500/25">
                    <i class="fas fa-rocket"></i>
                    Start with Ginto AI Masterclass
                </a>
            </div>
        </div>
        
    </main>
    
</body>
</html>
