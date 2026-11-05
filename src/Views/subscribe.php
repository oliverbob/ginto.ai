<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Subscribe | Ginto' ?></title>
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
</head>
<body class="bg-gray-50 dark:bg-gray-950 min-h-screen" x-data="subscriptionForm()">
    
    <!-- Navigation -->
    <nav class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <a href="/" class="flex items-center gap-2">
                    <img src="/assets/images/ginto.png" alt="Ginto" class="h-8 w-8 rounded-lg">
                    <span class="font-bold text-xl text-gray-900 dark:text-white">Ginto</span>
                </a>
            </div>
            <div class="flex items-center gap-3">
                <a href="/upgrade" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Plans
                </a>
            </div>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 py-12">
        <?php if (!$isLoggedIn): ?>
        <!-- Not Logged In -->
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-xl p-8 text-center">
            <div class="w-16 h-16 bg-amber-100 dark:bg-amber-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-lock text-amber-600 dark:text-amber-400 text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Login Required</h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6">Please log in to subscribe to a plan.</p>
            <a href="/login?redirect=<?= urlencode('/subscribe?plan=' . ($plan['name'] ?? 'plus')) ?>" 
               class="inline-block px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white rounded-lg font-medium transition-colors">
                Log in to Continue
            </a>
        </div>
        <?php elseif (!$plan): ?>
        <!-- No Plan Selected -->
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-xl p-8 text-center">
            <div class="w-16 h-16 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">No Plan Selected</h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6">Please select a plan from our pricing page.</p>
            <a href="/upgrade" class="inline-block px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white rounded-lg font-medium transition-colors">
                View Plans
            </a>
        </div>
        <?php elseif ($plan['name'] === 'free'): ?>
        <!-- Free Plan -->
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-xl p-8 text-center">
            <div class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check text-gray-600 dark:text-gray-400 text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">You're on the Free Plan</h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6">The Free plan is already active on your account.</p>
            <a href="/chat" class="inline-block px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white rounded-lg font-medium transition-colors">
                Start Learning
            </a>
        </div>
        <?php elseif ($currentPlan === $plan['name']): ?>
        <!-- Already Subscribed -->
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-xl p-8 text-center">
            <div class="w-16 h-16 bg-teal-100 dark:bg-teal-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check-circle text-teal-600 dark:text-teal-400 text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Already Subscribed!</h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6">You're already on the <?= htmlspecialchars($plan['display_name']) ?> plan.</p>
            <a href="/chat" class="inline-block px-6 py-3 bg-teal-600 hover:bg-teal-700 text-white rounded-lg font-medium transition-colors">
                Go to Chat
            </a>
        </div>
        <?php else: ?>
        <!-- Subscription Form -->
        <div class="grid md:grid-cols-2 gap-8">
            <!-- Order Summary -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-xl p-8">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Order Summary</h2>
                
                <div class="flex items-start gap-4 mb-6 pb-6 border-b border-gray-200 dark:border-gray-800">
                    <?php
                    $planColors = [
                        'go' => 'emerald',
                        'plus' => 'violet',
                        'pro' => 'amber'
                    ];
                    $color = $planColors[$plan['name']] ?? 'gray';
                    ?>
                    <div class="w-14 h-14 rounded-xl bg-<?= $color ?>-100 dark:bg-<?= $color ?>-900/30 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-<?= $plan['name'] === 'pro' ? 'crown' : ($plan['name'] === 'plus' ? 'bolt' : 'rocket') ?> text-<?= $color ?>-600 dark:text-<?= $color ?>-400 text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-lg text-gray-900 dark:text-white"><?= htmlspecialchars($plan['display_name']) ?> Plan</h3>
                        <p class="text-gray-500 dark:text-gray-400 text-sm"><?= htmlspecialchars($plan['description']) ?></p>
                    </div>
                </div>
                
                <!-- Features -->
                <div class="mb-6">
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">What's included:</h4>
                    <ul class="space-y-2">
                        <?php 
                        $features = json_decode($plan['features'] ?? '[]', true) ?: [];
                        foreach ($features as $feature): 
                        ?>
                        <li class="flex items-start gap-2 text-sm">
                            <i class="fas fa-check text-teal-500 mt-0.5"></i>
                            <span class="text-gray-600 dark:text-gray-300"><?= htmlspecialchars($feature) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <!-- Price -->
                <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-gray-600 dark:text-gray-400">Subtotal</span>
                        <span class="text-gray-900 dark:text-white">₱<?= number_format($plan['price_monthly'], 2) ?></span>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                        <span class="font-bold text-gray-900 dark:text-white">Total (Monthly)</span>
                        <span class="text-2xl font-bold text-gray-900 dark:text-white">₱<?= number_format($plan['price_monthly'], 2) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Payment Form -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-xl p-8">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">Payment Method</h2>
                
                <!-- PayPal Button Container -->
                <div id="paypal-button-container" class="mb-6"></div>
                
                <!-- Loading State -->
                <div id="paypal-loading" class="text-center py-8">
                    <div class="animate-spin w-8 h-8 border-4 border-teal-500 border-t-transparent rounded-full mx-auto mb-4"></div>
                    <p class="text-gray-500 dark:text-gray-400">Loading PayPal...</p>
                </div>
                
                <!-- Error Message -->
                <div id="paypal-error" class="hidden bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 p-4 rounded-lg mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span id="paypal-error-message">An error occurred. Please try again.</span>
                </div>
                
                <!-- Success Message -->
                <div id="paypal-success" class="hidden bg-teal-50 dark:bg-teal-900/20 border border-teal-200 dark:border-teal-800 text-teal-700 dark:text-teal-400 p-4 rounded-lg mb-6">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span>Payment successful! Redirecting...</span>
                </div>
                
                <!-- Security Notice -->
                <div class="mt-6 text-center">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        <i class="fas fa-lock mr-1"></i>
                        Secure payment processed by PayPal
                    </p>
                    <div class="flex items-center justify-center gap-2 mt-3">
                        <img src="https://www.paypalobjects.com/webstatic/mktg/Logo/pp-logo-100px.png" alt="PayPal" class="h-6">
                    </div>
                </div>
                
                <!-- Terms -->
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-4">
                    By subscribing, you agree to our <a href="/terms" class="text-teal-600 dark:text-teal-400 hover:underline">Terms of Service</a> 
                    and <a href="/privacy" class="text-teal-600 dark:text-teal-400 hover:underline">Privacy Policy</a>.
                    You can cancel anytime from your account settings.
                </p>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <?php if ($isLoggedIn && $plan && $plan['name'] !== 'free' && $currentPlan !== $plan['name']): ?>
    <!-- PayPal SDK -->
    <script src="https://www.paypal.com/sdk/js?client-id=<?= htmlspecialchars($paypalClientId) ?>&vault=true&intent=subscription&currency=PHP"></script>
    <script>
        // Hide loading once SDK loads
        document.getElementById('paypal-loading').style.display = 'none';
        
        // PayPal Plan IDs mapped to our plans
        const PAYPAL_PLAN_IDS = {
            'go': '<?= htmlspecialchars($paypalPlanIds['go'] ?? '') ?>',
            'plus': '<?= htmlspecialchars($paypalPlanIds['plus'] ?? '') ?>',
            'pro': '<?= htmlspecialchars($paypalPlanIds['pro'] ?? '') ?>'
        };
        
        const selectedPlan = '<?= htmlspecialchars($plan['name']) ?>';
        const userId = <?= (int)$userId ?>;
        const subscriptionType = '<?= htmlspecialchars($subscriptionType ?? 'other') ?>';
        
        if (PAYPAL_PLAN_IDS[selectedPlan]) {
            paypal.Buttons({
                style: {
                    shape: 'rect',
                    color: 'blue',
                    layout: 'vertical',
                    label: 'subscribe'
                },
                createSubscription: function(data, actions) {
                    return actions.subscription.create({
                        plan_id: PAYPAL_PLAN_IDS[selectedPlan],
                        custom_id: String(userId) // Pass user ID to webhook
                    });
                },
                onApprove: function(data, actions) {
                    document.getElementById('paypal-success').classList.remove('hidden');
                    
                    // Send subscription data to our server for immediate activation
                    fetch('/api/subscription/activate', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            subscription_id: data.subscriptionID,
                            plan: selectedPlan,
                            user_id: userId,
                            type: subscriptionType
                        })
                    })
                    .then(response => response.json())
                    .then(result => {
                        // Redirect to success page
                        window.location.href = '/subscribe/success?subscription=' + data.subscriptionID;
                    })
                    .catch(error => {
                        console.error('Activation error:', error);
                        // Still redirect - webhook will handle activation
                        window.location.href = '/subscribe/success?subscription=' + data.subscriptionID;
                    });
                },
                onError: function(err) {
                    console.error('PayPal error:', err);
                    document.getElementById('paypal-error').classList.remove('hidden');
                    document.getElementById('paypal-error-message').textContent = 'Payment failed. Please try again.';
                },
                onCancel: function(data) {
                    console.log('Payment cancelled');
                }
            }).render('#paypal-button-container');
        } else {
            document.getElementById('paypal-loading').innerHTML = '<p class="text-red-500">PayPal plan not configured for this subscription.</p>';
        }
        
        // Alpine component
        function subscriptionForm() {
            return {
                loading: false
            }
        }
    </script>
    <?php endif; ?>

</body>
</html>
