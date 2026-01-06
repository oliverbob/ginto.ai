<!DOCTYPE html>
<html lang="en" class="dark">
<?php include __DIR__ . '/parts/head.php'; ?>
<body class="bg-gray-50 dark:bg-gray-950 min-h-screen" x-data="{ sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true' }" x-init="$watch('sidebarCollapsed', val => localStorage.setItem('sidebarCollapsed', val))">
    
    <?php include __DIR__ . '/parts/sidebar.php'; ?>
    
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
        ?>
        
        <!-- Top Navigation Bar -->
        <div class="sticky top-0 z-30 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 px-4 py-3">
            <div class="flex items-center justify-between">
                <!-- Left: Back button, lesson info, progress & navigation -->
                <div class="flex items-center gap-3 min-w-0">
                    <button id="sidebar-toggle" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">
                        <i class="fas fa-bars text-gray-600 dark:text-gray-300"></i>
                    </button>
                    <a href="/masterclass/<?= htmlspecialchars($masterclass['slug']) ?>" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-300">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div class="min-w-0">
                        <h1 class="text-sm font-semibold text-gray-900 dark:text-white truncate max-w-[200px] sm:max-w-md"><?= htmlspecialchars($lesson['title']) ?></h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[200px] sm:max-w-md"><?= htmlspecialchars($masterclass['title']) ?></p>
                    </div>
                    
                    <!-- Progress & Navigation (moved to left) -->
                    <div class="hidden sm:flex items-center gap-2 ml-4 pl-4 border-l border-gray-200 dark:border-gray-700">
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            <?= $currentIndex + 1 ?> / <?= count($allLessons) ?>
                        </span>
                        
                        <?php if ($prevLesson): ?>
                        <a href="/masterclass/<?= htmlspecialchars($masterclass['slug']) ?>/lesson/<?= htmlspecialchars($prevLesson['slug']) ?>" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-300" title="Previous Lesson">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($nextLesson): ?>
                        <a href="/masterclass/<?= htmlspecialchars($masterclass['slug']) ?>/lesson/<?= htmlspecialchars($nextLesson['slug']) ?>" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-300" title="Next Lesson">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Right: Star on GitHub + Theme Toggle -->
                <div class="hidden lg:flex items-center gap-2" x-data="{ darkMode: document.documentElement.classList.contains('dark') }">
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
            </div>
        </div>
        
        <!-- Lesson Content -->
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            
            <!-- Lesson Header -->
            <div class="mb-6">
                <div class="flex items-center gap-3 mb-3">
                    <span class="px-3 py-1 bg-<?= $color ?>-100 dark:bg-<?= $color ?>-900/30 text-<?= $color ?>-700 dark:text-<?= $color ?>-400 text-sm rounded-full">
                        Lesson <?= $currentIndex + 1 ?>
                    </span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        <i class="fas fa-clock mr-1"></i><?= $lesson['duration_minutes'] ?? 10 ?> min
                    </span>
                    <?php if ($lesson['is_free_preview'] ?? false): ?>
                    <span class="px-3 py-1 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 text-sm rounded-full">
                        <i class="fas fa-unlock mr-1"></i>Free Preview
                    </span>
                    <?php endif; ?>
                </div>
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 dark:text-white mb-2">
                    <?= htmlspecialchars($lesson['title']) ?>
                </h1>
                <?php if (!empty($lesson['description'])): ?>
                <p class="text-gray-600 dark:text-gray-400">
                    <?= htmlspecialchars($lesson['description']) ?>
                </p>
                <?php endif; ?>
            </div>
            
            <!-- Video Content (if applicable) -->
            <?php if (($lesson['content_type'] ?? 'text') === 'video' && !empty($lesson['video_url'])): ?>
            <div class="mb-8">
                <div class="relative aspect-video bg-gray-900 rounded-xl overflow-hidden">
                    <iframe 
                        src="<?= htmlspecialchars($lesson['video_url']) ?>" 
                        class="absolute inset-0 w-full h-full"
                        frameborder="0" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen>
                    </iframe>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Lesson Content -->
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6 lg:p-10 mb-6">
                <div class="lesson-content text-gray-700 dark:text-gray-300">
                    <?php 
                    // Content is already HTML in content_html column
                    $content = $lesson['content_html'] ?? '';
                    
                    // If content_html is empty, fall back to content field (legacy)
                    if (empty($content)) {
                        $content = $lesson['content'] ?? '';
                        // Apply markdown conversion if using legacy content
                        $content = preg_replace('/```(\w+)?\n([\s\S]*?)```/m', '<pre><code class="language-$1">$2</code></pre>', $content);
                        $content = preg_replace('/`([^`]+)`/', '<code>$1</code>', $content);
                        $content = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $content);
                        $content = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $content);
                        $content = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $content);
                        $content = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $content);
                        $content = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $content);
                        $content = preg_replace('/^- (.+)$/m', '<li>$1</li>', $content);
                        $content = nl2br($content);
                    }
                    
                    echo $content;
                    ?>
                </div>
            </div>
            
            <!-- Attachments (if any) -->
            <?php 
            $attachments = json_decode($lesson['attachments'] ?? '[]', true);
            if (!empty($attachments)):
            ?>
            <div class="bg-gray-100 dark:bg-gray-800 rounded-xl p-5 mb-6">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">
                    <i class="fas fa-paperclip mr-2"></i>Resources
                </h3>
                <div class="space-y-2">
                    <?php foreach ($attachments as $attachment): ?>
                    <a href="<?= htmlspecialchars($attachment['url'] ?? '#') ?>" class="flex items-center gap-3 p-3 bg-white dark:bg-gray-900 rounded-lg hover:shadow-md transition-shadow">
                        <i class="fas fa-file-download text-<?= $color ?>-500"></i>
                        <span class="text-sm text-gray-700 dark:text-gray-300"><?= htmlspecialchars($attachment['name'] ?? 'Download') ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Mark Complete / Navigation -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-5 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
                <!-- Previous -->
                <div class="w-full sm:w-auto">
                    <?php if ($prevLesson): ?>
                    <a href="/masterclass/<?= htmlspecialchars($masterclass['slug']) ?>/lesson/<?= htmlspecialchars($prevLesson['slug']) ?>" class="flex items-center gap-2 text-gray-600 dark:text-gray-400 hover:text-<?= $color ?>-600 dark:hover:text-<?= $color ?>-400 transition-colors">
                        <i class="fas fa-arrow-left"></i>
                        <div class="text-left">
                            <div class="text-xs text-gray-500">Previous</div>
                            <div class="text-sm font-medium truncate max-w-[120px] sm:max-w-[160px]"><?= htmlspecialchars($prevLesson['title']) ?></div>
                        </div>
                    </a>
                    <?php endif; ?>
                </div>
                
                <!-- Mark Complete Button -->
                <div class="flex-shrink-0">
                    <?php if ($isLoggedIn): ?>
                    <form action="/masterclass/<?= htmlspecialchars($masterclass['slug']) ?>/lesson/<?= htmlspecialchars($lesson['slug']) ?>/complete" method="POST" class="inline">
                        <?php if ($isCompleted): ?>
                        <button type="submit" name="action" value="uncomplete" class="px-5 py-2.5 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 font-medium rounded-lg hover:bg-emerald-200 dark:hover:bg-emerald-900/50 transition-colors">
                            <i class="fas fa-check-circle mr-2"></i>Completed
                        </button>
                        <?php else: ?>
                        <button type="submit" name="action" value="complete" class="px-5 py-2.5 bg-gradient-to-r from-<?= $color ?>-500 to-<?= $color ?>-600 hover:from-<?= $color ?>-600 hover:to-<?= $color ?>-700 text-white font-medium rounded-lg transition-colors">
                            <i class="fas fa-check mr-2"></i>Mark Complete
                        </button>
                        <?php endif; ?>
                    </form>
                    <?php else: ?>
                    <a href="/login" class="inline-block px-5 py-2.5 bg-gradient-to-r from-<?= $color ?>-500 to-<?= $color ?>-600 hover:from-<?= $color ?>-600 hover:to-<?= $color ?>-700 text-white font-medium rounded-lg transition-colors">
                        <i class="fas fa-sign-in-alt mr-2"></i>Log in to Track Progress
                    </a>
                    <?php endif; ?>
                </div>
                
                <!-- Next -->
                <div class="w-full sm:w-auto text-right">
                    <?php if ($nextLesson): ?>
                    <a href="/masterclass/<?= htmlspecialchars($masterclass['slug']) ?>/lesson/<?= htmlspecialchars($nextLesson['slug']) ?>" class="flex items-center justify-end gap-2 text-gray-600 dark:text-gray-400 hover:text-<?= $color ?>-600 dark:hover:text-<?= $color ?>-400 transition-colors">
                        <div class="text-right">
                            <div class="text-xs text-gray-500">Next</div>
                            <div class="text-sm font-medium truncate max-w-[120px] sm:max-w-[160px]"><?= htmlspecialchars($nextLesson['title']) ?></div>
                        </div>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                    <?php else: ?>
                    <a href="/masterclass/<?= htmlspecialchars($masterclass['slug']) ?>" class="flex items-center justify-end gap-2 text-gray-600 dark:text-gray-400 hover:text-<?= $color ?>-600 dark:hover:text-<?= $color ?>-400 transition-colors">
                        <div class="text-right">
                            <div class="text-xs text-gray-500">Finished!</div>
                            <div class="text-sm font-medium">Back to Overview</div>
                        </div>
                        <i class="fas fa-trophy text-yellow-500"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
        
    </main>
    
    <!-- Syntax highlighting for code blocks -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/atom-one-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
    <script>hljs.highlightAll();</script>
    
</body>
</html>
