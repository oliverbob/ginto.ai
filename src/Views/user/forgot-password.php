<?php
/** @var string $title */
/** @var string $csrf_token */
/** @var string|null $success */
/** @var string|null $error */
?>
<?php require_once __DIR__ . '/../layout/login_header.php'; ?>

<div class="min-h-screen flex items-center justify-center p-6 bg-gradient-to-br from-amber-300 to-amber-500 dark:from-amber-700 dark:to-amber-900 transition-colors duration-200">
<div class="themed-card rounded-2xl shadow-xl max-w-md w-full p-8">

    <h1 class="text-3xl font-bold text-center mb-2 text-amber-600 dark:text-amber-400">Forgot Password</h1>
    <p class="text-center text-gray-500 dark:text-gray-400 mb-6 text-sm">Enter your account email and we'll send you a reset link.</p>

    <?php if (!empty($success)): ?>
        <div class="mb-4 p-3 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 border border-green-300 dark:border-green-700 rounded">
            <?= htmlspecialchars($success) ?>
        </div>
        <p class="text-center mt-4">
            <a href="/login" class="text-amber-600 dark:text-amber-400 hover:underline">&larr; Back to Login</a>
        </p>
    <?php else: ?>

        <?php if (!empty($error)): ?>
            <div class="mb-4 p-3 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 border border-red-300 dark:border-red-700 rounded">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="/forgot-password" method="POST" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="_csrf"       value="<?= htmlspecialchars($csrf_token) ?>">

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Address</label>
                <input type="email" id="email" name="email" required autofocus
                       placeholder="you@example.com"
                       class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-amber-500
                              bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100
                              border-gray-300 dark:border-gray-600">
            </div>

            <button type="submit"
                    class="w-full bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600
                           text-white font-semibold py-3 rounded-lg shadow-lg
                           hover:from-yellow-500 hover:via-yellow-600 hover:to-yellow-700
                           transition-all duration-300">
                Send Reset Link
            </button>
        </form>

        <p class="mt-4 text-center text-gray-700 dark:text-gray-300">
            <a href="/login" class="text-amber-600 dark:text-amber-400 hover:underline">&larr; Back to Login</a>
        </p>

    <?php endif; ?>
</div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
