<?php
/** @var string $title */
/** @var string $csrf_token */
/** @var array $old */
/** @var string|null $error */
?>
<?php require_once __DIR__ . '/../layout/login_header.php'; ?>

<div class="min-h-screen flex items-center justify-center p-6 bg-gradient-to-br from-amber-300 to-amber-500 dark:from-amber-700 dark:to-amber-900 transition-colors duration-200">

<div class="themed-card rounded-2xl shadow-xl max-w-md w-full p-8">

    <h1 class="text-3xl font-bold text-center mb-6 text-amber-600 dark:text-amber-400">Login to Ginto</h1>

    <?php if (!empty($error)): ?>
        <div class="mb-4 p-3 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 border border-red-300 dark:border-red-700 rounded">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form action="/login" method="POST" class="space-y-5">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <!-- Backwards-compatible field name for admin middleware which checks '_csrf' -->
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf_token) ?>">

        <!-- Login Identifier -->
        <div>
            <label for="identifier" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Login with Email, Username, or Phone Number
            </label>
            <input type="text" id="identifier" name="identifier" required
                   placeholder="Enter your email, username, or phone number"
                   value="<?= htmlspecialchars($old['identifier'] ?? '') ?>"
                   class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-amber-500 
                          bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100
                          border-gray-300 dark:border-gray-600">
        </div>

        <!-- Password -->
        <div class="relative">
            <input type="password" name="password" id="password" required
                placeholder="Password"
                class="w-full border rounded-lg p-3 pr-12 focus:ring-2 focus:ring-amber-500
                       bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100
                       border-gray-300 dark:border-gray-600">
            <button type="button" id="togglePassword"
                    class="absolute inset-y-0 right-3 flex items-center text-gray-500 hover:text-amber-600">
                <svg id="eye-open" class="h-5 w-5 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <svg id="eye-closed" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7 1.274-4.057 5.065-7 9.542-7 1.05 0 2.05.15 3 .425M12 5c4.477 0 8.268 2.943 9.542 7a10.04 10.04 0 01-1.5 3.5M16.5 13.5a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
                </svg>
            </button>
        </div>

        <button type="submit"
                class="w-full bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600 
                       text-white font-semibold py-3 rounded-lg shadow-lg 
                       hover:from-yellow-500 hover:via-yellow-600 hover:to-yellow-700 
                       transition-all duration-300">
            Login
        </button>

    </form>

    <p class="mt-4 text-center text-gray-700 dark:text-gray-300">
        Don't have an account? <a href="/register" class="text-amber-600 dark:text-amber-400 hover:underline">Register here</a>
    </p>

</div>
</div>

<script>
document.getElementById('togglePassword').addEventListener('click', () => {
    const input = document.getElementById('password');
    input.type = (input.type === 'password') ? 'text' : 'password';
    document.getElementById('eye-open').classList.toggle('hidden');
    document.getElementById('eye-closed').classList.toggle('hidden');
});
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
