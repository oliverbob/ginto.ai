<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Successful | Ginto</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        gray: { 950: '#0a0a0f' }
                    }
                }
            }
        }
        if (localStorage.theme === 'light') {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <style>
        @keyframes confetti {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; }
            100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
        }
        .confetti {
            position: fixed;
            top: -10px;
            animation: confetti 3s linear forwards;
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-950 min-h-screen flex items-center justify-center p-4">
    
    <!-- Confetti -->
    <script>
        const colors = ['#10b981', '#06b6d4', '#8b5cf6', '#f59e0b', '#ec4899'];
        for (let i = 0; i < 50; i++) {
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            confetti.style.left = Math.random() * 100 + 'vw';
            confetti.style.width = (Math.random() * 10 + 5) + 'px';
            confetti.style.height = confetti.style.width;
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.borderRadius = Math.random() > 0.5 ? '50%' : '0';
            confetti.style.animationDelay = Math.random() * 2 + 's';
            confetti.style.animationDuration = (Math.random() * 2 + 2) + 's';
            document.body.appendChild(confetti);
        }
    </script>

    <div class="max-w-lg w-full">
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-xl p-8 text-center">
            <!-- Success Icon -->
            <div class="w-20 h-20 bg-gradient-to-br from-teal-400 to-cyan-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg shadow-teal-500/25">
                <i class="fas fa-check text-white text-3xl"></i>
            </div>
            
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                Welcome to <?= htmlspecialchars($planName ?? 'Ginto') ?>!
            </h1>
            
            <p class="text-gray-600 dark:text-gray-400 mb-8">
                Your subscription is now active. Thank you for joining Ginto!
            </p>
            
            <!-- Subscription Details -->
            <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-6 mb-8 text-left">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Subscription Details</h3>
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Plan</dt>
                        <dd class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($planName ?? 'Unknown') ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                        <dd class="font-medium text-teal-600 dark:text-teal-400">
                            <i class="fas fa-circle text-xs mr-1"></i>Active
                        </dd>
                    </div>
                    <?php if (!empty($subscriptionId)): ?>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Subscription ID</dt>
                        <dd class="font-mono text-sm text-gray-700 dark:text-gray-300"><?= htmlspecialchars(substr($subscriptionId, 0, 15)) ?>...</dd>
                    </div>
                    <?php endif; ?>
                </dl>
            </div>
            
            <!-- What's Next -->
            <div class="text-left mb-8">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-3">What's Next?</h3>
                <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                    <li class="flex items-start gap-2">
                        <i class="fas fa-check-circle text-teal-500 mt-0.5"></i>
                        Access all your premium features immediately
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="fas fa-check-circle text-teal-500 mt-0.5"></i>
                        Explore masterclass lessons without restrictions
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="fas fa-check-circle text-teal-500 mt-0.5"></i>
                        Use AI chat with enhanced capabilities
                    </li>
                </ul>
            </div>
            
            <!-- CTA Buttons -->
            <div class="flex flex-col sm:flex-row gap-3">
                <a href="/chat" class="flex-1 py-3 px-6 bg-gradient-to-r from-teal-500 to-cyan-500 hover:from-teal-600 hover:to-cyan-600 text-white font-medium rounded-xl transition-all text-center">
                    <i class="fas fa-comments mr-2"></i>Start Chatting
                </a>
                <a href="/masterclass" class="flex-1 py-3 px-6 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-900 dark:text-white font-medium rounded-xl transition-all text-center">
                    <i class="fas fa-graduation-cap mr-2"></i>Browse Masterclasses
                </a>
            </div>
            
            <!-- Receipt Link -->
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-6">
                A receipt has been sent to your email. You can manage your subscription in <a href="/settings" class="text-teal-600 dark:text-teal-400 hover:underline">account settings</a>.
            </p>
        </div>
    </div>

</body>
</html>
