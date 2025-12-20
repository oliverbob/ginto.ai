<?php
// courses/pricing.php - Subscription pricing page
$plans = $plans ?? [];
$currentPlan = $currentPlan ?? 'free';
$isLoggedIn = $isLoggedIn ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/parts/head.php'; ?>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen" x-data="{ darkMode: true, billingCycle: 'monthly' }" x-init="darkMode = document.documentElement.classList.contains('dark')">
    
    <!-- Navigation -->
    <nav class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 fixed top-0 left-0 right-0 z-50 h-14">
        <div class="max-w-7xl mx-auto px-4 h-full flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="/courses" class="flex items-center gap-2 text-gray-600 dark:text-gray-300 hover:text-indigo-600">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Courses</span>
                </a>
            </div>
            <div class="flex items-center gap-3">
                <button @click="darkMode = !darkMode; document.documentElement.classList.toggle('dark'); localStorage.setItem('theme', darkMode ? 'dark' : 'light')" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-sun" x-show="darkMode"></i>
                    <i class="fas fa-moon" x-show="!darkMode"></i>
                </button>
            </div>
        </div>
    </nav>

    <main class="pt-14">
        <!-- Header -->
        <div class="bg-gradient-to-r from-indigo-600 to-purple-700 text-white py-16">
            <div class="max-w-4xl mx-auto px-4 text-center">
                <h1 class="text-4xl font-bold mb-4">Upgrade Your Learning</h1>
                <p class="text-xl text-white/90">Choose a plan that fits your learning goals</p>
            </div>
        </div>

        <!-- Pricing Cards -->
        <div class="max-w-7xl mx-auto px-4 -mt-8">
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($plans as $plan): 
                    $features = json_decode($plan['features'] ?? '[]', true) ?: [];
                    $isCurrentPlan = $currentPlan === $plan['name'];
                    $isPopular = $plan['name'] === 'plus';
                    $colorMap = ['free' => 'gray', 'go' => 'green', 'plus' => 'purple', 'pro' => 'indigo'];
                    $color = $colorMap[$plan['name']] ?? 'gray';
                ?>
                <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-lg overflow-hidden <?= $isPopular ? 'ring-2 ring-purple-500' : '' ?>">
                    <?php if ($isPopular): ?>
                    <div class="absolute top-0 right-0 bg-purple-500 text-white text-xs font-bold px-3 py-1 rounded-bl-lg">
                        POPULAR
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($plan['name'] === 'go'): ?>
                    <div class="absolute top-0 left-0 bg-green-500 text-white text-xs font-bold px-3 py-1 rounded-br-lg">
                        NEW
                    </div>
                    <?php endif; ?>
                    
                    <div class="p-6">
                        <h2 class="text-2xl font-bold mb-2"><?= htmlspecialchars($plan['display_name']) ?></h2>
                        
                        <div class="flex items-baseline gap-1 mb-2">
                            <span class="text-sm text-gray-500">₱</span>
                            <span class="text-4xl font-bold"><?= number_format($plan['price_monthly'], 0) ?></span>
                            <span class="text-sm text-gray-500">PHP / month</span>
                        </div>
                        
                        <?php if ($plan['price_monthly'] > 0): ?>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">(inclusive of VAT)</p>
                        <?php else: ?>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">&nbsp;</p>
                        <?php endif; ?>
                        
                        <p class="text-gray-600 dark:text-gray-400 mb-6"><?= htmlspecialchars($plan['description']) ?></p>
                        
                        <?php if ($isCurrentPlan): ?>
                        <button disabled class="w-full py-3 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-500 cursor-not-allowed font-medium">
                            Your current plan
                        </button>
                        <?php elseif ($plan['price_monthly'] == 0): ?>
                        <button disabled class="w-full py-3 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400 cursor-not-allowed font-medium">
                            Your current plan
                        </button>
                        <?php else: ?>
                        <a href="/subscribe?plan=<?= $plan['name'] ?>&amp;type=course" class="block w-full py-3 rounded-lg text-center font-medium transition-colors <?= $isPopular ? 'bg-purple-600 text-white hover:bg-purple-700' : 'bg-gray-900 dark:bg-white text-white dark:text-gray-900 hover:opacity-90' ?>">
                            <?= $plan['name'] === 'go' ? 'Upgrade to Go' : ($plan['name'] === 'plus' ? 'Get Plus' : 'Get Pro') ?>
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="px-6 pb-6">
                        <ul class="space-y-3">
                            <?php foreach ($features as $feature): ?>
                            <li class="flex items-start gap-3 text-sm">
                                <i class="fas fa-check text-<?= $color ?>-500 mt-0.5"></i>
                                <span class="text-gray-600 dark:text-gray-300"><?= htmlspecialchars($feature) ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="max-w-3xl mx-auto px-4 py-16">
            <h2 class="text-2xl font-bold text-center mb-8">Frequently Asked Questions</h2>
            
            <div class="space-y-4" x-data="{ openFaq: null }">
                <div class="bg-white dark:bg-gray-800 rounded-xl overflow-hidden">
                    <button @click="openFaq = openFaq === 1 ? null : 1" class="w-full flex items-center justify-between p-4 text-left font-medium">
                        <span>Can I cancel my subscription anytime?</span>
                        <i class="fas fa-chevron-down transition-transform" :class="openFaq === 1 ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="openFaq === 1" class="px-4 pb-4 text-gray-600 dark:text-gray-400">
                        Yes! You can cancel your subscription at any time. Your access will continue until the end of your billing period.
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl overflow-hidden">
                    <button @click="openFaq = openFaq === 2 ? null : 2" class="w-full flex items-center justify-between p-4 text-left font-medium">
                        <span>What payment methods do you accept?</span>
                        <i class="fas fa-chevron-down transition-transform" :class="openFaq === 2 ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="openFaq === 2" class="px-4 pb-4 text-gray-600 dark:text-gray-400">
                        We accept GCash, PayMaya, bank transfers, and major credit cards. Crypto payments are also available for annual plans.
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl overflow-hidden">
                    <button @click="openFaq = openFaq === 3 ? null : 3" class="w-full flex items-center justify-between p-4 text-left font-medium">
                        <span>Do I get a certificate after completing courses?</span>
                        <i class="fas fa-chevron-down transition-transform" :class="openFaq === 3 ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="openFaq === 3" class="px-4 pb-4 text-gray-600 dark:text-gray-400">
                        Certificates are available for Plus and Pro subscribers. Complete a course and download your verified certificate!
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl overflow-hidden">
                    <button @click="openFaq = openFaq === 4 ? null : 4" class="w-full flex items-center justify-between p-4 text-left font-medium">
                        <span>Can I upgrade or downgrade my plan?</span>
                        <i class="fas fa-chevron-down transition-transform" :class="openFaq === 4 ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="openFaq === 4" class="px-4 pb-4 text-gray-600 dark:text-gray-400">
                        Absolutely! You can change your plan anytime. When upgrading, you'll be charged the prorated difference. When downgrading, the change takes effect at your next billing cycle.
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Footer -->
    <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-8">
        <div class="max-w-7xl mx-auto px-4 text-center text-sm text-gray-500 dark:text-gray-400">
            © <?= date('Y') ?> Ginto. All rights reserved.
        </div>
    </footer>
</body>
</html>
