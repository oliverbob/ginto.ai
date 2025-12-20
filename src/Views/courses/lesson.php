<?php
// courses/lesson.php - Individual lesson page
$course = $course ?? [];
$lesson = $lesson ?? [];
$allLessons = $allLessons ?? [];
$nextLesson = $nextLesson ?? null;
$prevLesson = $prevLesson ?? null;
$isLoggedIn = $isLoggedIn ?? false;
$userPlan = $userPlan ?? 'free';
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen" x-data="{ sidebarOpen: false, darkMode: true, lessonComplete: false }" x-init="darkMode = document.documentElement.classList.contains('dark')">
    
    <!-- Top Navigation -->
    <nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 fixed top-0 left-0 right-0 z-50 h-14">
        <div class="max-w-full mx-auto px-4 h-full flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-bars"></i>
                </button>
                <a href="/courses/<?= $course['slug'] ?>" class="flex items-center gap-2 text-gray-600 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400">
                    <i class="fas fa-arrow-left"></i>
                    <span class="hidden sm:inline truncate max-w-xs"><?= htmlspecialchars($course['title']) ?></span>
                </a>
            </div>
            <div class="flex items-center gap-3">
                <button @click="darkMode = !darkMode; document.documentElement.classList.toggle('dark'); localStorage.setItem('theme', darkMode ? 'dark' : 'light')" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-sun text-gray-600 dark:text-gray-300" x-show="darkMode"></i>
                    <i class="fas fa-moon text-gray-600 dark:text-gray-300" x-show="!darkMode"></i>
                </button>
                <?php if ($isLoggedIn): ?>
                <span class="text-sm text-gray-500 dark:text-gray-400 hidden md:inline"><?= htmlspecialchars($userFullname ?? $username) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="flex pt-14 pb-16">
        <!-- Sidebar - Lesson List (ends where bottom nav starts) -->
        <aside class="fixed top-14 left-0 w-72 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 transform transition-transform lg:translate-x-0 z-40 overflow-y-auto pb-4"
               :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
               style="bottom: 57px;">
            <!-- Lesson List -->
            <div class="p-4">
                <h2 class="font-bold text-lg mb-4"><?= htmlspecialchars($course['title']) ?></h2>
                <div class="space-y-1">
                    <?php foreach ($allLessons as $i => $l): 
                        $isCurrent = $l['slug'] === $lesson['slug'];
                        $isAccessible = $l['is_accessible'] ?? false;
                    ?>
                    <a href="<?= $isAccessible ? '/courses/' . $course['slug'] . '/lesson/' . $l['slug'] : '#' ?>" 
                       class="flex items-center gap-3 p-2 rounded-lg text-sm <?= $isCurrent ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300' : ($isAccessible ? 'hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300' : 'opacity-50 cursor-not-allowed text-gray-500') ?>">
                        <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs flex-shrink-0 <?= $isCurrent ? 'bg-indigo-600 text-white' : 'bg-gray-200 dark:bg-gray-700' ?>">
                            <?= $i + 1 ?>
                        </span>
                        <span class="truncate flex-1"><?= htmlspecialchars($l['title']) ?></span>
                        <?php if (!$isAccessible): ?>
                        <i class="fas fa-lock text-xs"></i>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>
        
        <!-- Overlay for mobile -->
        <div x-show="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 bg-black/50 z-30 lg:hidden"></div>

        <!-- Main Content -->
        <main class="flex-1 lg:ml-72 min-h-screen">
            <div class="max-w-4xl mx-auto px-4 py-8">
                <!-- Lesson Header -->
                <div class="mb-8">
                    <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-2">
                        <span>Lesson <?= $lesson['lesson_order'] ?></span>
                        <span>•</span>
                        <span><?= $lesson['duration_minutes'] ?? 10 ?> min</span>
                        <?php if ($lesson['is_free_preview']): ?>
                        <span class="px-2 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded text-xs">Free</span>
                        <?php endif; ?>
                    </div>
                    <h1 class="text-3xl font-bold"><?= htmlspecialchars($lesson['title']) ?></h1>
                    <?php if ($lesson['description']): ?>
                    <p class="text-gray-600 dark:text-gray-400 mt-2"><?= htmlspecialchars($lesson['description']) ?></p>
                    <?php endif; ?>
                </div>

                <!-- Lesson Content -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
                    <?php if ($lesson['content_type'] === 'embed' && $lesson['embed_file']): ?>
                    <!-- Embedded Content (like typing.html) -->
                    <div class="aspect-video lg:aspect-auto lg:h-[600px]">
                        <iframe src="/<?= htmlspecialchars($lesson['embed_file']) ?>" class="w-full h-full border-0" allowfullscreen></iframe>
                    </div>
                    <?php elseif ($lesson['content_type'] === 'video' && $lesson['content_url']): ?>
                    <!-- Video Content -->
                    <div class="aspect-video">
                        <iframe src="<?= htmlspecialchars($lesson['content_url']) ?>" class="w-full h-full border-0" allowfullscreen></iframe>
                    </div>
                    <?php else: ?>
                    <!-- Text Content -->
                    <div class="p-6 lg:p-8 prose dark:prose-invert max-w-none">
                        <?php 
                        // If content_html exists, use it. Otherwise, load from file or show placeholder
                        if (!empty($lesson['content_html'])) {
                            echo $lesson['content_html'];
                        } else {
                            // Try to load content from file
                            $contentFile = __DIR__ . '/content/' . $course['slug'] . '/' . $lesson['slug'] . '.php';
                            if (file_exists($contentFile)) {
                                include $contentFile;
                            } else {
                                // Show placeholder with lesson info
                                echo '<div class="text-center py-12">';
                                echo '<i class="fas fa-book-open text-4xl text-indigo-500 mb-4"></i>';
                                echo '<h2 class="text-xl font-bold mb-2">' . htmlspecialchars($lesson['title']) . '</h2>';
                                echo '<p class="text-gray-500 dark:text-gray-400">Lesson content is being prepared. Check back soon!</p>';
                                echo '</div>';
                            }
                        }
                        ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Mark Complete & Navigation -->
                <div class="mt-8 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div>
                        <?php if ($prevLesson): ?>
                        <a href="/courses/<?= $course['slug'] ?>/lesson/<?= $prevLesson['slug'] ?>" class="inline-flex items-center gap-2 text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">
                            <i class="fas fa-arrow-left"></i>
                            <span>Previous: <?= htmlspecialchars($prevLesson['title']) ?></span>
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <?php if ($isLoggedIn): ?>
                        <button @click="markComplete()" 
                                :class="lessonComplete ? 'bg-green-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-indigo-600 hover:text-white'"
                                class="px-6 py-2 rounded-lg font-medium transition-colors">
                            <i class="fas" :class="lessonComplete ? 'fa-check' : 'fa-check-circle'"></i>
                            <span x-text="lessonComplete ? 'Completed!' : 'Mark Complete'"></span>
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($nextLesson): ?>
                        <?php if ($nextLesson['is_accessible'] ?? false): ?>
                        <a href="/courses/<?= $course['slug'] ?>/lesson/<?= $nextLesson['slug'] ?>" class="inline-flex items-center gap-2 bg-indigo-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-indigo-700 transition-colors">
                            <span>Next Lesson</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                        <?php else: ?>
                        <a href="/courses/pricing" class="inline-flex items-center gap-2 bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-2 rounded-lg font-medium hover:opacity-90 transition-opacity">
                            <i class="fas fa-lock"></i>
                            <span>Upgrade to Continue</span>
                        </a>
                        <?php endif; ?>
                        <?php else: ?>
                <a href="/courses/<?= $course['slug'] ?>" class="inline-flex items-center gap-2 bg-green-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-green-700 transition-colors">
                    <i class="fas fa-trophy"></i>
                    <span>Course Complete!</span>
                </a>
                <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Fixed Bottom Navigation Bar - FULL WIDTH including sidebar area -->
    <div class="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 z-50">
        <div class="flex">
            <!-- Sidebar Footer Section -->
            <div class="hidden lg:flex w-72 flex-shrink-0 border-r border-gray-200 dark:border-gray-700 p-3 bg-gray-50 dark:bg-gray-900/50 items-center justify-between">
                <?php if ($isLoggedIn): ?>
                <!-- User Info -->
                <div class="flex items-center gap-2 flex-1 min-w-0">
                    <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm font-medium flex-shrink-0">
                        <?= strtoupper(substr($username ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="truncate text-sm">
                        <div class="font-medium text-gray-900 dark:text-gray-100 truncate"><?= htmlspecialchars($userFullname ?? $username) ?></div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 capitalize"><?= $userPlan ?> Plan</div>
                    </div>
                </div>
                <!-- Actions -->
                <div class="flex items-center gap-1">
                    <a href="/settings" class="p-2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors" title="Settings">
                        <i class="fas fa-cog"></i>
                    </a>
                    <a href="/logout" class="p-2 text-gray-500 hover:text-red-600 dark:hover:text-red-400 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
                <?php else: ?>
                <!-- Guest Actions -->
                <a href="/login?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="flex-1 text-center py-2 text-sm text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 rounded-lg transition-colors font-medium">
                    <i class="fas fa-sign-in-alt mr-1"></i> Sign In
                </a>
                <a href="/register" class="flex-1 text-center py-2 text-sm bg-indigo-600 text-white hover:bg-indigo-700 rounded-lg transition-colors font-medium">
                    Register
                </a>
                <?php endif; ?>
            </div>
            
            <!-- Main Nav Section -->
            <div class="flex-1 px-4 py-3">
                <div class="max-w-4xl mx-auto flex items-center justify-between">
                <!-- Previous -->
                <div class="flex-1">
                    <?php if ($prevLesson): ?>
                    <a href="/courses/<?= $course['slug'] ?>/lesson/<?= $prevLesson['slug'] ?>" class="inline-flex items-center gap-2 text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 group">
                        <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i>
                        <div class="hidden sm:block">
                            <div class="text-xs text-gray-400">Previous</div>
                            <div class="text-sm font-medium truncate max-w-[200px]"><?= htmlspecialchars($prevLesson['title']) ?></div>
                        </div>
                        <span class="sm:hidden text-sm">Previous</span>
                    </a>
                    <?php else: ?>
                    <a href="/courses/<?= $course['slug'] ?>" class="inline-flex items-center gap-2 text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">
                        <i class="fas fa-arrow-left"></i>
                        <span class="text-sm">Back to Course</span>
                    </a>
                    <?php endif; ?>
                </div>
                
                <!-- Progress indicator -->
                <div class="hidden md:flex items-center gap-2 px-4">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        Lesson <?= $lesson['lesson_order'] ?> of <?= count($allLessons) ?>
                    </span>
                    <div class="w-24 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                        <div class="h-full bg-indigo-600 rounded-full" style="width: <?= ($lesson['lesson_order'] / max(count($allLessons), 1)) * 100 ?>%"></div>
                    </div>
                </div>
                
                <!-- Next -->
                <div class="flex-1 text-right">
                    <?php if ($nextLesson): ?>
                        <?php if ($nextLesson['is_accessible'] ?? false): ?>
                        <a href="/courses/<?= $course['slug'] ?>/lesson/<?= $nextLesson['slug'] ?>" class="inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-indigo-700 transition-colors group">
                            <div class="hidden sm:block text-left">
                                <div class="text-xs text-indigo-200">Next</div>
                                <div class="text-sm font-medium truncate max-w-[200px]"><?= htmlspecialchars($nextLesson['title']) ?></div>
                            </div>
                            <span class="sm:hidden">Next</span>
                            <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </a>
                        <?php else: ?>
                        <a href="/courses/pricing" class="inline-flex items-center gap-2 bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-4 py-2 rounded-lg font-medium hover:opacity-90 transition-opacity">
                            <i class="fas fa-lock"></i>
                            <span>Upgrade to Continue</span>
                        </a>
                        <?php endif; ?>
                    <?php else: ?>
                    <a href="/courses/<?= $course['slug'] ?>" class="inline-flex items-center gap-2 bg-green-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-green-700 transition-colors">
                        <i class="fas fa-trophy"></i>
                        <span>Complete!</span>
                    </a>
                    <?php endif; ?>
                </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Spacer for fixed footer -->
    <div class="h-20"></div>

    <script>
    function markComplete() {
        fetch('/api/courses/complete-lesson', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                lesson_id: <?= $lesson['id'] ?>,
                course_id: <?= $course['id'] ?>
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Alpine.store('lessonComplete', true);
                document.querySelector('[x-data]').__x.$data.lessonComplete = true;
            }
        });
    }
    </script>
</body>
</html>
