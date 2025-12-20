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
        // Category colors
        $categoryColors = [
            'infrastructure' => 'teal',
            'containers' => 'cyan',
            'ai-platform' => 'violet',
            'server-management' => 'amber',
        ];
        $color = $categoryColors[$masterclass['category_slug'] ?? ''] ?? 'teal';
        
        // Difficulty badge
        $difficultyClass = match($masterclass['difficulty_level'] ?? 'intermediate') {
            'beginner' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            'intermediate' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            'advanced' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            'expert' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
            default => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
        };
        ?>
        
        <!-- Hero Section -->
        <div class="bg-gradient-to-br from-<?= $color ?>-600 via-<?= $color ?>-500 to-<?= $color ?>-400 dark:from-<?= $color ?>-800 dark:via-<?= $color ?>-700 dark:to-<?= $color ?>-600">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10 lg:py-14">
                <!-- Breadcrumb -->
                <nav class="mb-4">
                    <ol class="flex items-center gap-2 text-sm text-<?= $color ?>-100">
                        <li><a href="/masterclass" class="hover:text-white transition-colors">Masterclasses</a></li>
                        <li><i class="fas fa-chevron-right text-xs"></i></li>
                        <li><a href="/masterclass?category=<?= htmlspecialchars($masterclass['category_slug'] ?? '') ?>" class="hover:text-white transition-colors"><?= htmlspecialchars($masterclass['category_name'] ?? 'General') ?></a></li>
                        <li><i class="fas fa-chevron-right text-xs"></i></li>
                        <li class="text-white font-medium truncate max-w-[200px]"><?= htmlspecialchars($masterclass['title']) ?></li>
                    </ol>
                </nav>
                
                <div class="flex flex-col lg:flex-row gap-8">
                    <!-- Left: Info -->
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="px-3 py-1 bg-white/20 backdrop-blur text-white text-sm font-medium rounded-full">
                                <i class="fas fa-<?= htmlspecialchars($masterclass['category_icon'] ?? 'folder') ?> mr-1"></i>
                                <?= htmlspecialchars($masterclass['category_name'] ?? 'General') ?>
                            </span>
                            <span class="<?= $difficultyClass ?> px-3 py-1 rounded-full text-sm font-medium">
                                <?= ucfirst($masterclass['difficulty_level'] ?? 'intermediate') ?>
                            </span>
                            <?php if ($masterclass['is_featured'] ?? false): ?>
                            <span class="px-3 py-1 bg-yellow-400 text-yellow-900 text-sm font-medium rounded-full">
                                <i class="fas fa-star mr-1"></i> Featured
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <h1 class="text-3xl lg:text-4xl font-bold text-white mb-2">
                            <?= htmlspecialchars($masterclass['title']) ?>
                        </h1>
                        <p class="text-lg text-<?= $color ?>-100 mb-4">
                            <?= htmlspecialchars($masterclass['subtitle'] ?? '') ?>
                        </p>
                        
                        <!-- Stats -->
                        <div class="flex flex-wrap items-center gap-4 text-sm text-white/90 mb-6">
                            <span class="inline-flex items-center gap-2">
                                <i class="fas fa-clock"></i>
                                <?= htmlspecialchars($masterclass['estimated_hours'] ?? '0') ?> hours
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <i class="fas fa-list"></i>
                                <?= htmlspecialchars($masterclass['total_lessons'] ?? '0') ?> lessons
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <i class="fas fa-users"></i>
                                <?= $stats['total_enrolled'] ?? 0 ?> enrolled
                            </span>
                        </div>
                        
                        <!-- Instructor -->
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full bg-white/20 backdrop-blur flex items-center justify-center text-white text-lg font-bold">
                                <?= strtoupper(substr($masterclass['instructor_name'] ?? 'G', 0, 1)) ?>
                            </div>
                            <div>
                                <div class="text-white font-medium"><?= htmlspecialchars($masterclass['instructor_name'] ?? 'Instructor') ?></div>
                                <div class="text-<?= $color ?>-200 text-sm">Instructor</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right: CTA Card -->
                    <div class="lg:w-80">
                        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-xl p-6">
                            <?php if ($enrollment): ?>
                            <div class="mb-4">
                                <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <span>Your Progress</span>
                                    <span class="font-medium"><?= number_format($enrollment['progress_percent'] ?? 0, 0) ?>%</span>
                                </div>
                                <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                    <div class="progress-bar h-full bg-gradient-to-r from-teal-500 to-cyan-500 rounded-full" style="width: <?= $enrollment['progress_percent'] ?? 0 ?>%"></div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php 
                            // Find first accessible lesson
                            $firstLesson = null;
                            $continueLesson = null;
                            foreach ($lessons as $l) {
                                if (!$firstLesson && ($l['is_accessible'] || $l['is_free_preview'])) {
                                    $firstLesson = $l;
                                }
                                // Find lesson to continue (first in-progress or first not completed)
                                foreach ($progressDetails as $pd) {
                                    if ($pd['lesson_id'] == $l['id'] && $pd['status'] === 'in_progress') {
                                        $continueLesson = $l;
                                        break 2;
                                    }
                                }
                            }
                            if (!$continueLesson) $continueLesson = $firstLesson;
                            ?>
                            
                            <?php if ($continueLesson): ?>
                            <a href="/masterclass/<?= htmlspecialchars($masterclass['slug']) ?>/lesson/<?= htmlspecialchars($continueLesson['slug']) ?>" class="block w-full py-3 px-4 bg-gradient-to-r from-<?= $color ?>-500 to-<?= $color ?>-600 hover:from-<?= $color ?>-600 hover:to-<?= $color ?>-700 text-white font-medium rounded-lg text-center transition-colors mb-3">
                                <i class="fas fa-<?= $enrollment ? 'play' : 'rocket' ?> mr-2"></i>
                                <?= $enrollment ? 'Continue Learning' : 'Start Masterclass' ?>
                            </a>
                            <?php endif; ?>
                            
                            <div class="text-center text-sm text-gray-500 dark:text-gray-400">
                                <?php if (!$isLoggedIn): ?>
                                <a href="/login" class="text-<?= $color ?>-600 dark:text-<?= $color ?>-400 hover:underline">Log in</a> to track your progress
                                <?php else: ?>
                                Free preview available
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Content -->
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex flex-col lg:flex-row gap-8">
                
                <!-- Left: Description & Lessons -->
                <div class="flex-1">
                    <!-- Description -->
                    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">About This Masterclass</h2>
                        <div class="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-300">
                            <?= nl2br(htmlspecialchars($masterclass['description'] ?? '')) ?>
                        </div>
                        
                        <?php 
                        $outcomes = json_decode($masterclass['learning_outcomes'] ?? '[]', true);
                        if (!empty($outcomes)):
                        ?>
                        <h3 class="text-md font-semibold text-gray-900 dark:text-white mt-6 mb-3">What You'll Learn</h3>
                        <ul class="space-y-2">
                            <?php foreach ($outcomes as $outcome): ?>
                            <li class="flex items-start gap-2 text-gray-600 dark:text-gray-300">
                                <i class="fas fa-check-circle text-teal-500 mt-1 flex-shrink-0"></i>
                                <span><?= htmlspecialchars($outcome) ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                        
                        <?php 
                        $technologies = json_decode($masterclass['technologies_covered'] ?? '[]', true);
                        if (!empty($technologies)):
                        ?>
                        <h3 class="text-md font-semibold text-gray-900 dark:text-white mt-6 mb-3">Technologies Covered</h3>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($technologies as $tech): ?>
                            <span class="px-3 py-1 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-full text-sm">
                                <?= htmlspecialchars($tech) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Curriculum -->
                    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            Curriculum
                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400 ml-2"><?= count($lessons) ?> lessons</span>
                        </h2>
                        
                        <div class="space-y-2">
                            <?php foreach ($lessons as $index => $lesson): ?>
                            <?php
                            $isCompleted = false;
                            $isInProgress = false;
                            foreach ($progressDetails as $pd) {
                                if ($pd['lesson_id'] == $lesson['id']) {
                                    $isCompleted = $pd['status'] === 'completed';
                                    $isInProgress = $pd['status'] === 'in_progress';
                                    break;
                                }
                            }
                            ?>
                            <div class="flex items-center gap-3 p-3 rounded-lg <?= $lesson['is_accessible'] || $lesson['is_free_preview'] ? 'hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer' : 'opacity-60' ?> transition-colors group">
                                <!-- Status Icon -->
                                <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 <?php
                                    if ($isCompleted) {
                                        echo 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400';
                                    } elseif ($isInProgress) {
                                        echo 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400';
                                    } elseif ($lesson['is_accessible'] || $lesson['is_free_preview']) {
                                        echo 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 group-hover:bg-' . $color . '-100 dark:group-hover:bg-' . $color . '-900/30 group-hover:text-' . $color . '-600 dark:group-hover:text-' . $color . '-400';
                                    } else {
                                        echo 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-600';
                                    }
                                ?>">
                                    <?php if ($isCompleted): ?>
                                    <i class="fas fa-check text-sm"></i>
                                    <?php elseif ($isInProgress): ?>
                                    <i class="fas fa-play text-xs"></i>
                                    <?php elseif ($lesson['is_accessible'] || $lesson['is_free_preview']): ?>
                                    <span class="text-sm font-medium"><?= $index + 1 ?></span>
                                    <?php else: ?>
                                    <i class="fas fa-lock text-xs"></i>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Lesson Info -->
                                <?php if ($lesson['is_accessible'] || $lesson['is_free_preview']): ?>
                                <a href="/masterclass/<?= htmlspecialchars($masterclass['slug']) ?>/lesson/<?= htmlspecialchars($lesson['slug']) ?>" class="flex-1 min-w-0">
                                <?php else: ?>
                                <div class="flex-1 min-w-0">
                                <?php endif; ?>
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-sm font-medium text-gray-900 dark:text-white truncate <?= ($lesson['is_accessible'] || $lesson['is_free_preview']) ? 'group-hover:text-' . $color . '-600 dark:group-hover:text-' . $color . '-400' : '' ?>">
                                            <?= htmlspecialchars($lesson['title']) ?>
                                        </h4>
                                        <?php if ($lesson['is_free_preview']): ?>
                                        <span class="px-2 py-0.5 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 text-xs rounded-full">Free</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        <span><i class="fas fa-clock mr-1"></i><?= $lesson['duration_minutes'] ?? 10 ?> min</span>
                                        <span class="capitalize"><?= $lesson['content_type'] ?? 'text' ?></span>
                                    </div>
                                <?php if ($lesson['is_accessible'] || $lesson['is_free_preview']): ?>
                                </a>
                                <?php else: ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Right: Sidebar Info -->
                <div class="lg:w-72 space-y-6">
                    <!-- Prerequisites -->
                    <?php 
                    $prerequisites = json_decode($masterclass['prerequisites'] ?? '[]', true);
                    if (!empty($prerequisites)):
                    ?>
                    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Prerequisites</h3>
                        <ul class="space-y-2">
                            <?php foreach ($prerequisites as $prereq): ?>
                            <li class="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <i class="fas fa-arrow-right text-xs text-<?= $color ?>-500 mt-1 flex-shrink-0"></i>
                                <span><?= htmlspecialchars($prereq) ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Related Masterclasses -->
                    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-5">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Related</h3>
                        <div class="space-y-3">
                            <a href="/masterclass/ginto-ai-platform" class="block p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Ginto AI Platform</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Learn all technologies together</div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </main>
    
</body>
</html>
