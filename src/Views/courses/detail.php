<?php
// courses/detail.php - Course detail page with lessons list
$course = $course ?? [];
$lessons = $lessons ?? [];
$userPlan = $userPlan ?? 'free';
$enrollment = $enrollment ?? null;
$progressDetails = $progressDetails ?? [];
$stats = $stats ?? [];
$isLoggedIn = $isLoggedIn ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen" x-data="{ sidebarCollapsed: true, darkMode: true }" x-init="darkMode = document.documentElement.classList.contains('dark')">
    
    <!-- Top Navigation -->
    <nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 fixed top-0 left-0 right-0 z-50 h-14">
        <div class="max-w-7xl mx-auto px-4 h-full flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="/courses" class="flex items-center gap-2 text-gray-600 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400">
                    <i class="fas fa-arrow-left"></i>
                    <span class="hidden sm:inline">Back to Courses</span>
                </a>
            </div>
            <div class="flex items-center gap-3">
                <button @click="darkMode = !darkMode; document.documentElement.classList.toggle('dark'); localStorage.setItem('theme', darkMode ? 'dark' : 'light')" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-sun text-gray-600 dark:text-gray-300" x-show="darkMode"></i>
                    <i class="fas fa-moon text-gray-600 dark:text-gray-300" x-show="!darkMode"></i>
                </button>
                <?php if ($isLoggedIn): ?>
                <a href="/chat" class="text-sm text-gray-600 dark:text-gray-300 hover:text-indigo-600">
                    <i class="fas fa-comments mr-1"></i> Chat
                </a>
                <?php else: ?>
                <a href="/login" class="text-sm text-gray-600 dark:text-gray-300 hover:text-indigo-600">Login</a>
                <a href="/register" class="text-sm bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="pt-14">
        <!-- Course Header -->
        <div class="bg-gradient-to-r from-indigo-600 to-purple-700 text-white">
            <div class="max-w-7xl mx-auto px-4 py-12">
                <div class="flex flex-col lg:flex-row gap-8">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="px-3 py-1 bg-white/20 rounded-full text-sm"><?= htmlspecialchars($course['category_name'] ?? 'Course') ?></span>
                            <span class="px-3 py-1 bg-white/20 rounded-full text-sm capitalize"><?= htmlspecialchars($course['difficulty_level'] ?? 'beginner') ?></span>
                        </div>
                        <h1 class="text-3xl lg:text-4xl font-bold mb-4"><?= htmlspecialchars($course['title']) ?></h1>
                        <p class="text-lg text-white/90 mb-6"><?= htmlspecialchars($course['subtitle'] ?? '') ?></p>
                        
                        <div class="flex flex-wrap items-center gap-6 text-sm text-white/80">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-book-open"></i>
                                <span><?= count($lessons) ?> Lessons</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fas fa-clock"></i>
                                <span><?= $course['estimated_hours'] ?? '10' ?> hours</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fas fa-users"></i>
                                <span><?= number_format($stats['total_students'] ?? 0) ?> students</span>
                            </div>
                            <?php if (($stats['avg_rating'] ?? 0) > 0): ?>
                            <div class="flex items-center gap-2">
                                <i class="fas fa-star text-yellow-400"></i>
                                <span><?= number_format($stats['avg_rating'], 1) ?> (<?= $stats['review_count'] ?> reviews)</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($enrollment): ?>
                        <div class="mt-6">
                            <div class="bg-white/20 rounded-full h-2 w-64">
                                <div class="bg-white rounded-full h-2" style="width: <?= $enrollment['progress_percent'] ?>%"></div>
                            </div>
                            <p class="text-sm mt-2"><?= round($enrollment['progress_percent']) ?>% complete</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="lg:w-80">
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 text-gray-900 dark:text-white shadow-xl">
                            <div class="text-center mb-4">
                                <?php 
                                $planBadge = $course['min_plan_required'] ?? 'free';
                                $planColors = ['free' => 'gray', 'go' => 'green', 'plus' => 'purple', 'pro' => 'indigo'];
                                $planColor = $planColors[$planBadge] ?? 'gray';
                                ?>
                                <span class="px-3 py-1 bg-<?= $planColor ?>-100 dark:bg-<?= $planColor ?>-900/30 text-<?= $planColor ?>-700 dark:text-<?= $planColor ?>-300 rounded-full text-sm font-medium">
                                    <?= ucfirst($planBadge) ?> Plan
                                </span>
                            </div>
                            
                            <?php 
                            $firstLesson = $lessons[0] ?? null;
                            $continueLesson = $enrollment && $enrollment['last_lesson_id'] ? null : $firstLesson;
                            // Find continue lesson
                            foreach ($lessons as $l) {
                                if (isset($progressDetails[$l['id']]) && $progressDetails[$l['id']]['status'] === 'in_progress') {
                                    $continueLesson = $l;
                                    break;
                                }
                            }
                            if (!$continueLesson) $continueLesson = $firstLesson;
                            ?>
                            
                            <?php if ($continueLesson): ?>
                            <a href="/courses/<?= $course['slug'] ?>/lesson/<?= $continueLesson['slug'] ?>" 
                               class="block w-full bg-indigo-600 hover:bg-indigo-700 text-white text-center py-3 rounded-lg font-medium transition-colors">
                                <?= $enrollment ? 'Continue Learning' : 'Start Course' ?>
                            </a>
                            <?php endif; ?>
                            
                            <p class="text-center text-sm text-gray-500 dark:text-gray-400 mt-4">
                                <?php if ($userPlan === 'free'): ?>
                                Free access to first 3 lessons
                                <?php elseif ($userPlan === 'go'): ?>
                                Access to first 10 lessons
                                <?php else: ?>
                                Full access to all lessons
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Course Content -->
        <div class="max-w-7xl mx-auto px-4 py-8">
            <div class="grid lg:grid-cols-3 gap-8">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Description -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm">
                        <h2 class="text-xl font-bold mb-4">About This Course</h2>
                        <p class="text-gray-600 dark:text-gray-300 leading-relaxed">
                            <?= nl2br(htmlspecialchars($course['description'] ?? '')) ?>
                        </p>
                    </div>
                    
                    <!-- What You'll Learn -->
                    <?php 
                    $outcomes = json_decode($course['learning_outcomes'] ?? '[]', true) ?: [];
                    if (!empty($outcomes)):
                    ?>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm">
                        <h2 class="text-xl font-bold mb-4">What You'll Learn</h2>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <?php foreach ($outcomes as $outcome): ?>
                            <div class="flex items-start gap-3">
                                <i class="fas fa-check text-green-500 mt-1"></i>
                                <span class="text-gray-600 dark:text-gray-300"><?= htmlspecialchars($outcome) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Lessons List -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm">
                        <h2 class="text-xl font-bold mb-4">Course Content</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4"><?= count($lessons) ?> lessons • <?= $course['estimated_hours'] ?? '10' ?> hours total</p>
                        
                        <div class="space-y-2">
                            <?php foreach ($lessons as $i => $lesson): 
                                $isCompleted = isset($progressDetails[$lesson['id']]) && $progressDetails[$lesson['id']]['status'] === 'completed';
                                $isAccessible = $lesson['is_accessible'] ?? false;
                                $isFree = $lesson['is_free_preview'] ?? false;
                            ?>
                            <div class="flex items-center gap-4 p-3 rounded-lg <?= $isAccessible ? 'hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer' : 'opacity-60' ?> transition-colors"
                                 <?php if ($isAccessible): ?>onclick="window.location='/courses/<?= $course['slug'] ?>/lesson/<?= $lesson['slug'] ?>'"<?php endif; ?>>
                                <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 <?= $isCompleted ? 'bg-green-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400' ?>">
                                    <?php if ($isCompleted): ?>
                                    <i class="fas fa-check text-sm"></i>
                                    <?php else: ?>
                                    <span class="text-sm font-medium"><?= $i + 1 ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-medium text-gray-900 dark:text-white truncate"><?= htmlspecialchars($lesson['title']) ?></h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400"><?= $lesson['duration_minutes'] ?? 10 ?> min</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <?php if ($isFree): ?>
                                    <span class="text-xs px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded">Free</span>
                                    <?php endif; ?>
                                    <?php if (!$isAccessible): ?>
                                    <i class="fas fa-lock text-gray-400"></i>
                                    <?php else: ?>
                                    <i class="fas fa-play text-indigo-500"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($userPlan === 'free'): ?>
                        <div class="mt-6 p-4 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-lg text-white text-center">
                            <p class="font-medium mb-2">Unlock all lessons</p>
                            <a href="/courses/pricing" class="inline-block bg-white text-indigo-600 px-6 py-2 rounded-lg font-medium hover:bg-gray-100 transition-colors">
                                Upgrade Now
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Prerequisites -->
                    <?php 
                    $prereqs = json_decode($course['prerequisites'] ?? '[]', true) ?: [];
                    if (!empty($prereqs)):
                    ?>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm">
                        <h3 class="font-bold mb-4">Prerequisites</h3>
                        <ul class="space-y-2">
                            <?php foreach ($prereqs as $prereq): ?>
                            <li class="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-300">
                                <i class="fas fa-circle text-xs mt-1.5 text-indigo-500"></i>
                                <?= htmlspecialchars($prereq) ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Instructor -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm">
                        <h3 class="font-bold mb-4">Instructor</h3>
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 flex items-center justify-center text-white font-bold">
                                <?= strtoupper(substr($course['instructor_name'] ?? 'G', 0, 1)) ?>
                            </div>
                            <div>
                                <p class="font-medium"><?= htmlspecialchars($course['instructor_name'] ?? 'Ginto Academy') ?></p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Course Instructor</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Share -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm">
                        <h3 class="font-bold mb-4">Share This Course</h3>
                        <div class="flex gap-3">
                            <a href="#" class="w-10 h-10 rounded-full bg-blue-500 text-white flex items-center justify-center hover:bg-blue-600">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="w-10 h-10 rounded-full bg-sky-500 text-white flex items-center justify-center hover:bg-sky-600">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="w-10 h-10 rounded-full bg-green-500 text-white flex items-center justify-center hover:bg-green-600">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                            <a href="#" class="w-10 h-10 rounded-full bg-gray-500 text-white flex items-center justify-center hover:bg-gray-600">
                                <i class="fas fa-link"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Footer -->
    <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 text-center text-sm text-gray-500 dark:text-gray-400">
            © <?= date('Y') ?> Ginto. All rights reserved.
        </div>
    </footer>
</body>
</html>
