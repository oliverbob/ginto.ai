<?php
// courses/upgrade.php - Shown when user tries to access locked content
$course = $course ?? [];
$lesson = $lesson ?? [];
$plans = $plans ?? [];
$userPlan = $userPlan ?? 'free';
$isLoggedIn = $isLoggedIn ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex items-center justify-center" x-data="{ darkMode: true }" x-init="darkMode = document.documentElement.classList.contains('dark')">
    
    <div class="max-w-lg mx-auto px-4 py-16 text-center">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8">
            <div class="w-20 h-20 mx-auto bg-gradient-to-r from-indigo-500 to-purple-600 rounded-full flex items-center justify-center mb-6">
                <i class="fas fa-lock text-3xl text-white"></i>
            </div>
            
            <h1 class="text-2xl font-bold mb-2">Upgrade to Continue</h1>
            <p class="text-gray-600 dark:text-gray-400 mb-6">
                This lesson requires a <?= $userPlan === 'free' ? 'paid' : 'higher' ?> subscription to access.
            </p>
            
            <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-4 mb-6 text-left">
                <p class="text-sm text-gray-500 dark:text-gray-400">You're trying to access:</p>
                <p class="font-medium"><?= htmlspecialchars($lesson['title'] ?? 'This lesson') ?></p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">from <?= htmlspecialchars($course['title'] ?? 'this course') ?></p>
            </div>
            
            <?php if (!$isLoggedIn): ?>
            <div class="space-y-3">
                <a href="/login?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="block w-full bg-indigo-600 text-white py-3 rounded-lg font-medium hover:bg-indigo-700 transition-colors">
                    Sign In
                </a>
                <a href="/register" class="block w-full bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 py-3 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                    Create Free Account
                </a>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-4">
                Already have an account? Sign in to access your content.
            </p>
            <?php else: ?>
            <a href="/courses/pricing" class="block w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white py-3 rounded-lg font-medium hover:opacity-90 transition-opacity mb-4">
                View Upgrade Options
            </a>
            
            <div class="grid grid-cols-3 gap-3 text-center text-sm">
                <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-3">
                    <p class="font-bold text-green-600">Go</p>
                    <p class="text-xs text-gray-500">₱250/mo</p>
                </div>
                <div class="bg-purple-100 dark:bg-purple-900/30 rounded-lg p-3 ring-2 ring-purple-500">
                    <p class="font-bold text-purple-600">Plus</p>
                    <p class="text-xs text-gray-500">₱1,100/mo</p>
                </div>
                <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-3">
                    <p class="font-bold text-indigo-600">Pro</p>
                    <p class="text-xs text-gray-500">₱9,990/mo</p>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <a href="/courses/<?= $course['slug'] ?? '' ?>" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                    ← Back to course overview
                </a>
            </div>
        </div>
    </div>
</body>
</html>
