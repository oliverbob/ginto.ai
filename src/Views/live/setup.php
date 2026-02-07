<?php
/**
 * Ginto AI - First Time Setup
 * 
 * Create the first admin user for a fresh installation.
 * This page is shown when no users exist in the database.
 */
$htmlDark = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? ' class="dark"' : '';
?>
<!DOCTYPE html>
<html lang="en"<?php echo $htmlDark; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Ginto AI - First Time Setup') ?></title>
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/tailwind.css">
    <link rel="stylesheet" href="/assets/css/dark-fallback.css">
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('theme') || (document.cookie.match(/(?:^|; )theme=(dark|light)(?:;|$)/) || [])[1];
                if (saved === 'dark') document.documentElement.classList.add('dark');
                else if (saved === 'light') document.documentElement.classList.remove('dark');
            } catch (e) {}
        })();
    </script>
    <style>
        .input-field {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            background: white;
            color: #111827;
            font-size: 0.875rem;
            transition: all 0.15s;
        }
        .input-field:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }
        .input-field::placeholder {
            color: #9ca3af;
        }
        .dark .input-field {
            background: #374151;
            border-color: #4b5563;
            color: white;
        }
        .dark .input-field:focus {
            border-color: #3b82f6;
        }
        .label-text {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        .dark .label-text {
            color: #d1d5db;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-900 dark:to-gray-800 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <!-- Logo/Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 mb-4">
                <i class="fas fa-robot text-4xl text-white"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Welcome to Ginto AI</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">Create your admin account to get started</p>
        </div>

        <!-- Setup Card -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8">
            <?php if (!empty($error)): ?>
            <div class="mb-6 p-4 bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 rounded-lg">
                <div class="flex items-center gap-2 text-red-800 dark:text-red-300">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            </div>
            <?php endif; ?>

            <form id="setup-form" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="create_admin">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <input type="text" name="first_name" class="input-field" placeholder="First Name" value="<?= htmlspecialchars($envValues['ADMIN_FIRST_NAME'] ?? '') ?>">
                    </div>
                    <div>
                        <input type="text" name="last_name" class="input-field" placeholder="Last Name" value="<?= htmlspecialchars($envValues['ADMIN_LAST_NAME'] ?? '') ?>">
                    </div>
                </div>

                <div>
                    <input type="text" name="username" class="input-field" placeholder="Username *" required 
                           value="<?= htmlspecialchars($envValues['ADMIN_USERNAME'] ?? '') ?>"
                           pattern="[a-zA-Z0-9_\-]+" minlength="3">
                </div>

                <div>
                    <input type="email" name="email" class="input-field" placeholder="Email *" required
                           value="<?= htmlspecialchars($envValues['ADMIN_EMAIL'] ?? '') ?>">
                </div>

                <div>
                    <div class="relative">
                        <input type="password" name="password" id="password" class="input-field pr-12" 
                               placeholder="Password * (min 6 chars)" required minlength="6">
                        <button type="button" onclick="togglePassword()" 
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                            <i class="fas fa-eye" id="eye-icon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" id="submit-btn" 
                        class="w-full py-3 px-4 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white font-semibold rounded-lg transition-all duration-200 flex items-center justify-center gap-2">
                    <i class="fas fa-user-plus"></i>
                    Create Admin Account
                </button>
            </form>

            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center">
                    <i class="fas fa-info-circle mr-1"></i>
                    This will be your administrator account with full access to all settings.
                </p>
            </div>
        </div>

        <!-- Footer -->
        <p class="text-center text-gray-500 dark:text-gray-400 text-sm mt-6">
            Powered by <a href="https://ginto.ai" class="text-blue-600 hover:underline">Ginto AI</a>
        </p>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('eye-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        document.getElementById('setup-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const btn = document.getElementById('submit-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
            btn.disabled = true;
            
            try {
                const formData = new FormData(e.target);
                const data = Object.fromEntries(formData.entries());
                
                const response = await fetch('/live', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    btn.innerHTML = '<i class="fas fa-check"></i> Success!';
                    btn.classList.remove('from-blue-600', 'to-purple-600');
                    btn.classList.add('from-green-600', 'to-green-600', 'bg-green-600');
                    
                    // Redirect to settings
                    setTimeout(() => {
                        window.location.href = result.redirect || '/live';
                    }, 1000);
                } else {
                    throw new Error(result.error || 'Failed to create admin account');
                }
            } catch (error) {
                btn.innerHTML = originalText;
                btn.disabled = false;
                
                alert('Error: ' + error.message);
            }
        });
    </script>
</body>
</html>
