<?php require_once __DIR__ . '/../layout/login_header.php'; ?>
<div class="min-h-screen flex items-center justify-center p-4 bg-gray-50 dark:bg-gray-900">
  <div class="themed-card rounded-2xl shadow-xl max-w-md w-full p-8">
    <h1 class="text-3xl font-bold text-center mb-2 text-amber-600 dark:text-amber-400">Verify your sign-in</h1>
    <p class="text-center text-gray-500 dark:text-gray-400 mb-6">Enter the 6-digit code from Google Authenticator.</p>
    <?php if (!empty($error)): ?><div class="mb-4 p-3 bg-red-100 text-red-700 border border-red-300 rounded"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form action="/login/2fa" method="POST" class="space-y-5">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Authenticator code</label>
      <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus class="w-full border rounded-lg p-3 text-center tracking-widest text-xl bg-white dark:bg-gray-800" placeholder="000000">
      <button type="submit" class="w-full bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600 text-white font-semibold py-3 rounded-lg">Verify and continue</button>
    </form>
    <p class="mt-4 text-center text-sm"><a href="/login" class="text-amber-600 hover:underline">Use a different account</a></p>
  </div>
</div>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
