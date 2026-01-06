<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Upgrade | Ginto' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        gray: {
                            950: '#0a0a0f',
                        }
                    }
                }
            }
        }
        if (localStorage.theme === 'light') {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <style>
        .plan-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .plan-card:hover {
            transform: translateY(-4px);
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-950 min-h-screen" x-data="{ billingCycle: 'monthly' }">
    
    <!-- Navigation -->
    <nav class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <a href="/" class="flex items-center gap-2">
                    <img src="/assets/images/ginto.png" alt="Ginto" class="h-8 w-8 rounded-lg">
                    <span class="font-bold text-xl text-gray-900 dark:text-white">Ginto</span>
                </a>
                <nav class="hidden md:flex items-center gap-4">
                    <a href="/chat" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">Chat</a>
                    <a href="/masterclass" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">Masterclasses</a>
                    <a href="/courses" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">Courses</a>
                </nav>
            </div>
            <div class="flex items-center gap-3">
                <div x-data="{ darkMode: document.documentElement.classList.contains('dark') }">
                    <button @click="darkMode = !darkMode; document.documentElement.classList.toggle('dark'); localStorage.setItem('theme', darkMode ? 'dark' : 'light')" 
                            class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors group">
                        <i class="fas fa-sun text-yellow-400 group-hover:text-yellow-300" x-show="darkMode"></i>
                        <i class="fas fa-moon text-gray-600 group-hover:text-gray-800" x-show="!darkMode"></i>
                    </button>
                </div>
                <?php if ($isLoggedIn): ?>
                <a href="/chat" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg font-medium transition-colors">
                    Go to Chat
                </a>
                <?php else: ?>
                <a href="/login" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">Log in</a>
                <a href="/register" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg font-medium transition-colors">
                    Sign up
                </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main>
        <!-- Hero Section -->
        <div class="bg-gradient-to-br from-teal-600 via-cyan-600 to-teal-700 text-white py-20">
            <div class="max-w-4xl mx-auto px-4 text-center">
                <h1 class="text-4xl md:text-5xl font-bold mb-4">
                    Upgrade Your Ginto Experience
                </h1>
                <p class="text-xl text-teal-100 mb-8 max-w-2xl mx-auto">
                    Unlock AI-powered coding, unlimited masterclasses, and advanced features to accelerate your development journey.
                </p>
                
                <!-- Current Plan Badge -->
                <?php if ($isLoggedIn && $currentPlan !== 'free'): ?>
                <div class="inline-flex items-center gap-2 px-4 py-2 bg-white/20 rounded-full text-sm">
                    <i class="fas fa-crown text-yellow-300"></i>
                    <span>Current Plan: <strong class="capitalize"><?= htmlspecialchars($currentPlan) ?></strong></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Plans Section -->
        <div class="max-w-7xl mx-auto px-4 -mt-10 pb-20">
            
            <!-- Billing Toggle -->
            <div class="flex justify-center mb-10">
                <div class="inline-flex items-center gap-3 bg-white dark:bg-gray-900 rounded-full p-1.5 shadow-lg border border-gray-200 dark:border-gray-800">
                    <button @click="billingCycle = 'monthly'" 
                            :class="billingCycle === 'monthly' ? 'bg-teal-600 text-white' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                            class="px-5 py-2 rounded-full font-medium transition-colors">
                        Monthly
                    </button>
                    <button @click="billingCycle = 'yearly'" 
                            :class="billingCycle === 'yearly' ? 'bg-teal-600 text-white' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                            class="px-5 py-2 rounded-full font-medium transition-colors flex items-center gap-2">
                        Yearly
                        <span class="text-xs bg-green-500 text-white px-2 py-0.5 rounded-full">Save 20%</span>
                    </button>
                </div>
            </div>
            
            <!-- Plan Cards -->
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php 
                $planConfig = [
                    'free' => ['color' => 'gray', 'icon' => 'fas fa-user', 'popular' => false],
                    'go' => ['color' => 'emerald', 'icon' => 'fas fa-rocket', 'popular' => false, 'badge' => 'NEW'],
                    'plus' => ['color' => 'violet', 'icon' => 'fas fa-bolt', 'popular' => true],
                    'pro' => ['color' => 'amber', 'icon' => 'fas fa-crown', 'popular' => false],
                ];
                
                foreach ($plans as $plan): 
                    $config = $planConfig[$plan['name']] ?? $planConfig['free'];
                    $features = json_decode($plan['features'] ?? '[]', true) ?: [];
                    $isCurrentPlan = $currentPlan === $plan['name'];
                    $yearlyPrice = ($plan['price_monthly'] ?? 0) * 12 * 0.8; // 20% discount
                ?>
                <div class="plan-card relative bg-white dark:bg-gray-900 rounded-2xl shadow-xl overflow-hidden border-2 <?= $config['popular'] ? 'border-violet-500' : 'border-transparent dark:border-gray-800' ?>">
                    
                    <?php if ($config['popular']): ?>
                    <div class="absolute top-0 left-0 right-0 bg-gradient-to-r from-violet-500 to-purple-600 text-white text-center text-sm font-bold py-1.5">
                        MOST POPULAR
                    </div>
                    <?php elseif (!empty($config['badge'])): ?>
                    <div class="absolute top-4 right-4 bg-emerald-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                        <?= $config['badge'] ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="p-6 <?= $config['popular'] ? 'pt-10' : '' ?>">
                        <!-- Plan Icon & Name -->
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 rounded-xl bg-<?= $config['color'] ?>-100 dark:bg-<?= $config['color'] ?>-900/30 flex items-center justify-center">
                                <i class="<?= $config['icon'] ?> text-<?= $config['color'] ?>-600 dark:text-<?= $config['color'] ?>-400 text-xl"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($plan['display_name'] ?? ucfirst($plan['name'])) ?></h2>
                            </div>
                        </div>
                        
                        <!-- Price -->
                        <div class="mb-4">
                            <div class="flex items-baseline gap-1" x-show="billingCycle === 'monthly'">
                                <span class="text-sm text-gray-500 dark:text-gray-400">₱</span>
                                <span class="text-4xl font-bold text-gray-900 dark:text-white"><?= number_format($plan['price_monthly'] ?? 0, 0) ?></span>
                                <span class="text-gray-500 dark:text-gray-400">/mo</span>
                            </div>
                            <div class="flex items-baseline gap-1" x-show="billingCycle === 'yearly'" x-cloak>
                                <span class="text-sm text-gray-500 dark:text-gray-400">₱</span>
                                <span class="text-4xl font-bold text-gray-900 dark:text-white"><?= number_format($yearlyPrice, 0) ?></span>
                                <span class="text-gray-500 dark:text-gray-400">/yr</span>
                            </div>
                            <?php if (($plan['price_monthly'] ?? 0) > 0): ?>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Billed <span x-text="billingCycle"></span></p>
                            <?php else: ?>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Forever free</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Description -->
                        <p class="text-gray-600 dark:text-gray-400 text-sm mb-6">
                            <?= htmlspecialchars($plan['description'] ?? '') ?>
                        </p>
                        
                        <!-- CTA Button -->
                        <?php if ($isCurrentPlan): ?>
                        <button disabled class="w-full py-3 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 font-medium cursor-not-allowed">
                            <i class="fas fa-check mr-2"></i>Current Plan
                        </button>
                        <?php elseif (($plan['price_monthly'] ?? 0) == 0): ?>
                        <button disabled class="w-full py-3 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 font-medium cursor-not-allowed">
                            Free Forever
                        </button>
                        <?php else: ?>
                        <a href="/subscribe?plan=<?= $plan['name'] ?>&amp;type=<?= htmlspecialchars($subscriptionType ?? 'other') ?>" 
                           class="block w-full py-3 rounded-xl text-center font-medium transition-all
                                  <?= $config['popular'] 
                                      ? 'bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white shadow-lg shadow-violet-500/25' 
                                      : 'bg-gray-900 dark:bg-white text-white dark:text-gray-900 hover:opacity-90' ?>">
                            <?= $plan['name'] === 'go' ? 'Start with Go' : 'Upgrade to ' . ucfirst($plan['name']) ?>
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Features -->
                    <div class="px-6 pb-6 border-t border-gray-100 dark:border-gray-800 pt-6">
                        <ul class="space-y-3">
                            <?php foreach ($features as $feature): ?>
                            <li class="flex items-start gap-3 text-sm">
                                <i class="fas fa-check text-<?= $config['color'] ?>-500 mt-0.5 flex-shrink-0"></i>
                                <span class="text-gray-600 dark:text-gray-300"><?= htmlspecialchars($feature) ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Feature Comparison -->
        <div class="bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 py-20">
            <div class="max-w-6xl mx-auto px-4">
                <h2 class="text-3xl font-bold text-center text-gray-900 dark:text-white mb-4">
                    Compare Plans
                </h2>
                <p class="text-center text-gray-600 dark:text-gray-400 mb-12 max-w-2xl mx-auto">
                    All plans include access to Ginto AI chat. Upgrade for more features.
                </p>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-800">
                                <th class="text-left py-4 pr-4 font-medium text-gray-500 dark:text-gray-400">Feature</th>
                                <th class="text-center py-4 px-4 font-medium text-gray-900 dark:text-white">Free</th>
                                <th class="text-center py-4 px-4 font-medium text-emerald-600 dark:text-emerald-400">Go</th>
                                <th class="text-center py-4 px-4 font-medium text-violet-600 dark:text-violet-400">Plus</th>
                                <th class="text-center py-4 px-4 font-medium text-amber-600 dark:text-amber-400">Pro</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-gray-700 dark:text-gray-300">
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-4 pr-4 text-gray-600 dark:text-gray-300">AI Chat Messages</td>
                                <td class="text-center py-4 px-4 text-gray-700 dark:text-gray-300">50/day</td>
                                <td class="text-center py-4 px-4 text-gray-700 dark:text-gray-300">500/day</td>
                                <td class="text-center py-4 px-4 text-gray-700 dark:text-gray-300">Unlimited</td>
                                <td class="text-center py-4 px-4 text-gray-700 dark:text-gray-300">Unlimited</td>
                            </tr>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-4 pr-4 text-gray-600 dark:text-gray-300">Masterclass Lessons</td>
                                <td class="text-center py-4 px-4 text-gray-700 dark:text-gray-300">First 4</td>
                                <td class="text-center py-4 px-4 text-gray-700 dark:text-gray-300">First 10</td>
                                <td class="text-center py-4 px-4 text-teal-600 dark:text-teal-400"><i class="fas fa-check mr-1"></i>All</td>
                                <td class="text-center py-4 px-4 text-teal-600 dark:text-teal-400"><i class="fas fa-check mr-1"></i>All</td>
                            </tr>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-4 pr-4 text-gray-600 dark:text-gray-300">LXC Sandboxes</td>
                                <td class="text-center py-4 px-4 text-gray-500 dark:text-gray-500"><i class="fas fa-times"></i></td>
                                <td class="text-center py-4 px-4 text-gray-700 dark:text-gray-300">1 concurrent</td>
                                <td class="text-center py-4 px-4 text-gray-700 dark:text-gray-300">3 concurrent</td>
                                <td class="text-center py-4 px-4 text-gray-700 dark:text-gray-300">10 concurrent</td>
                            </tr>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-4 pr-4 text-gray-600 dark:text-gray-300">Sandbox Persistence</td>
                                <td class="text-center py-4 px-4 text-gray-500 dark:text-gray-500"><i class="fas fa-times"></i></td>
                                <td class="text-center py-4 px-4 text-gray-700 dark:text-gray-300">24 hours</td>
                                <td class="text-center py-4 px-4 text-gray-700 dark:text-gray-300">7 days</td>
                                <td class="text-center py-4 px-4 text-gray-700 dark:text-gray-300">30 days</td>
                            </tr>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-4 pr-4 text-gray-600 dark:text-gray-300">Container Resources</td>
                                <td class="text-center py-4 px-4 text-gray-500 dark:text-gray-500"><i class="fas fa-times"></i></td>
                                <td class="text-center py-4 px-4 text-gray-700 dark:text-gray-300">1 vCPU / 512MB</td>
                                <td class="text-center py-4 px-4 text-gray-700 dark:text-gray-300">2 vCPU / 2GB</td>
                                <td class="text-center py-4 px-4 text-gray-700 dark:text-gray-300">4 vCPU / 4GB</td>
                            </tr>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-4 pr-4 text-gray-600 dark:text-gray-300">Containerized Deployment</td>
                                <td class="text-center py-4 px-4 text-gray-500 dark:text-gray-500"><i class="fas fa-times"></i></td>
                                <td class="text-center py-4 px-4 text-gray-500 dark:text-gray-500"><i class="fas fa-times"></i></td>
                                <td class="text-center py-4 px-4 text-teal-600 dark:text-teal-400"><i class="fas fa-check"></i></td>
                                <td class="text-center py-4 px-4 text-teal-600 dark:text-teal-400"><i class="fas fa-check"></i></td>
                            </tr>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-4 pr-4 text-gray-600 dark:text-gray-300">Custom Domain</td>
                                <td class="text-center py-4 px-4 text-gray-500 dark:text-gray-500"><i class="fas fa-times"></i></td>
                                <td class="text-center py-4 px-4 text-gray-500 dark:text-gray-500"><i class="fas fa-times"></i></td>
                                <td class="text-center py-4 px-4 text-gray-700 dark:text-gray-300">1 domain</td>
                                <td class="text-center py-4 px-4 text-gray-700 dark:text-gray-300">Unlimited</td>
                            </tr>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-4 pr-4 text-gray-600 dark:text-gray-300">SSL Certificates</td>
                                <td class="text-center py-4 px-4 text-gray-500 dark:text-gray-500"><i class="fas fa-times"></i></td>
                                <td class="text-center py-4 px-4 text-gray-500 dark:text-gray-500"><i class="fas fa-times"></i></td>
                                <td class="text-center py-4 px-4 text-teal-600 dark:text-teal-400"><i class="fas fa-check"></i></td>
                                <td class="text-center py-4 px-4 text-teal-600 dark:text-teal-400"><i class="fas fa-check"></i></td>
                            </tr>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-4 pr-4 text-gray-600 dark:text-gray-300">Priority Support</td>
                                <td class="text-center py-4 px-4 text-gray-500 dark:text-gray-500"><i class="fas fa-times"></i></td>
                                <td class="text-center py-4 px-4 text-gray-500 dark:text-gray-500"><i class="fas fa-times"></i></td>
                                <td class="text-center py-4 px-4 text-teal-600 dark:text-teal-400"><i class="fas fa-check"></i></td>
                                <td class="text-center py-4 px-4 text-teal-600 dark:text-teal-400"><i class="fas fa-check"></i></td>
                            </tr>
                            <tr>
                                <td class="py-4 pr-4 text-gray-600 dark:text-gray-300">SSH Access to Container</td>
                                <td class="text-center py-4 px-4 text-gray-500 dark:text-gray-500"><i class="fas fa-times"></i></td>
                                <td class="text-center py-4 px-4 text-gray-500 dark:text-gray-500"><i class="fas fa-times"></i></td>
                                <td class="text-center py-4 px-4 text-gray-500 dark:text-gray-500"><i class="fas fa-times"></i></td>
                                <td class="text-center py-4 px-4 text-teal-600 dark:text-teal-400"><i class="fas fa-check"></i></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- FAQ Section -->
        <div class="py-20 px-4">
            <div class="max-w-3xl mx-auto">
                <h2 class="text-3xl font-bold text-center text-gray-900 dark:text-white mb-12">
                    Frequently Asked Questions
                </h2>
                
                <div class="space-y-4" x-data="{ open: null }">
                    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                        <button @click="open = open === 1 ? null : 1" class="w-full px-6 py-4 text-left flex items-center justify-between">
                            <span class="font-medium text-gray-900 dark:text-white">Can I switch plans anytime?</span>
                            <i class="fas fa-chevron-down text-gray-400 transition-transform" :class="open === 1 ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 1" x-collapse class="px-6 pb-4 text-gray-600 dark:text-gray-400">
                            Yes! You can upgrade or downgrade your plan at any time. When upgrading, you'll be charged the prorated difference. When downgrading, the change takes effect at your next billing cycle.
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                        <button @click="open = open === 2 ? null : 2" class="w-full px-6 py-4 text-left flex items-center justify-between">
                            <span class="font-medium text-gray-900 dark:text-white">What payment methods do you accept?</span>
                            <i class="fas fa-chevron-down text-gray-400 transition-transform" :class="open === 2 ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 2" x-collapse class="px-6 pb-4 text-gray-600 dark:text-gray-400">
                            We accept all major credit cards, GCash, Maya, and bank transfers. All payments are processed securely through our payment partners.
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                        <button @click="open = open === 3 ? null : 3" class="w-full px-6 py-4 text-left flex items-center justify-between">
                            <span class="font-medium text-gray-900 dark:text-white">Is there a free trial?</span>
                            <i class="fas fa-chevron-down text-gray-400 transition-transform" :class="open === 3 ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 3" x-collapse class="px-6 pb-4 text-gray-600 dark:text-gray-400">
                            Our Free plan gives you a taste of Ginto's capabilities forever. Premium masterclass lessons have free previews so you can see the content quality before subscribing.
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                        <button @click="open = open === 4 ? null : 4" class="w-full px-6 py-4 text-left flex items-center justify-between">
                            <span class="font-medium text-gray-900 dark:text-white">Can I get a refund?</span>
                            <i class="fas fa-chevron-down text-gray-400 transition-transform" :class="open === 4 ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 4" x-collapse class="px-6 pb-4 text-gray-600 dark:text-gray-400">
                            We offer a 7-day money-back guarantee for annual subscriptions. If you're not satisfied, contact support within 7 days of purchase for a full refund.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Footer -->
    <footer class="bg-gray-100 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 py-8">
        <div class="max-w-7xl mx-auto px-4 text-center text-gray-600 dark:text-gray-400 text-sm">
            <p>&copy; <?= date('Y') ?> Ginto AI. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
