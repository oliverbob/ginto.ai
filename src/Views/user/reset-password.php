<?php
/** @var string $title */
/** @var string $csrf_token */
/** @var string $token */
/** @var string|null $error */
/** @var bool $done */
$done = $done ?? false;
?>
<?php require_once __DIR__ . '/../layout/login_header.php'; ?>

<div class="min-h-screen flex items-center justify-center p-6 bg-gradient-to-br from-amber-300 to-amber-500 dark:from-amber-700 dark:to-amber-900 transition-colors duration-200">
<div class="themed-card rounded-2xl shadow-xl max-w-md w-full p-8">

    <?php if ($done): ?>

        <div class="text-center">
            <div class="text-5xl mb-4">🎉</div>
            <h1 class="text-2xl font-bold mb-2 text-green-600 dark:text-green-400">Password Updated!</h1>
            <p class="text-gray-600 dark:text-gray-300 mb-6">Your password has been changed successfully. You can now log in with your new password.</p>
            <a href="/login"
               class="inline-block bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600
                      text-white font-semibold py-3 px-8 rounded-lg shadow-lg
                      hover:from-yellow-500 hover:via-yellow-600 hover:to-yellow-700 transition-all duration-300">
                Go to Login
            </a>
        </div>

    <?php else: ?>

        <h1 class="text-3xl font-bold text-center mb-2 text-amber-600 dark:text-amber-400">Set New Password</h1>
        <p class="text-center text-gray-500 dark:text-gray-400 mb-6 text-sm">Choose a strong password (at least 8 characters).</p>

        <?php if (!empty($error)): ?>
            <div class="mb-4 p-3 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 border border-red-300 dark:border-red-700 rounded">
                <?= htmlspecialchars($error) ?>
                <?php if (!$token): ?>
                    <div class="mt-2">
                        <a href="/forgot-password" class="text-amber-600 dark:text-amber-400 hover:underline">Request a new reset link</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($token): ?>
        <form action="/reset-password" method="POST" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="_csrf"       value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="token"       value="<?= $token ?>">

            <div class="relative">
                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">New Password</label>
                <input type="password" id="password" name="password" required autofocus
                       placeholder="••••••••" minlength="8"
                       class="w-full border rounded-lg p-3 pr-12 focus:ring-2 focus:ring-amber-500
                              bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100
                              border-gray-300 dark:border-gray-600">
                <button type="button" id="togglePwd"
                        class="absolute bottom-3 right-3 text-gray-500 hover:text-amber-600">
                    <svg id="eye-open" class="h-5 w-5 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg id="eye-closed" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7 1.274-4.057 5.065-7 9.542-7 1.05 0 2.05.15 3 .425M12 5c4.477 0 8.268 2.943 9.542 7a10.04 10.04 0 01-1.5 3.5M16.5 13.5a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/>
                    </svg>
                </button>
            </div>

            <div>
                <label for="password_confirm" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Confirm New Password</label>
                <input type="password" id="password_confirm" name="password_confirm" required
                       placeholder="••••••••" minlength="8"
                       class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-amber-500
                              bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100
                              border-gray-300 dark:border-gray-600">
            </div>

            <button type="submit"
                    class="w-full bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600
                           text-white font-semibold py-3 rounded-lg shadow-lg
                           hover:from-yellow-500 hover:via-yellow-600 hover:to-yellow-700
                           transition-all duration-300">
                Update Password
            </button>
        </form>
        <?php endif; ?>

        <p class="mt-4 text-center text-gray-700 dark:text-gray-300">
            <a href="/login" class="text-amber-600 dark:text-amber-400 hover:underline">&larr; Back to Login</a>
        </p>

    <?php endif; ?>
</div>
</div>

<script>
var btn = document.getElementById('togglePwd');
if (btn) {
    btn.addEventListener('click', function () {
        var input = document.getElementById('password');
        input.type = (input.type === 'password') ? 'text' : 'password';
        document.getElementById('eye-open').classList.toggle('hidden');
        document.getElementById('eye-closed').classList.toggle('hidden');
    });
}
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
