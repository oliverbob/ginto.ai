<?php
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 3));
}
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

include ROOT_PATH . '/src/Views/layout/header.php';
include ROOT_PATH . '/src/Views/layout/sidebar.php';

$csrf = htmlspecialchars($csrf_token ?? $_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
$username = htmlspecialchars($user['username'] ?? '', ENT_QUOTES, 'UTF-8');
$email    = htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8');
?>

<div id="mainContent" class="p-6">
    <div class="max-w-lg mx-auto">

        <!-- Warning card -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-red-200 dark:border-red-800 shadow-sm overflow-hidden">

            <!-- Red header band -->
            <div class="bg-red-50 dark:bg-red-900/30 px-6 py-5 border-b border-red-200 dark:border-red-800 flex items-center gap-4">
                <div class="flex-shrink-0 w-12 h-12 bg-red-100 dark:bg-red-800/50 rounded-full flex items-center justify-center">
                    <i class="fas fa-triangle-exclamation text-red-600 dark:text-red-400 text-xl"></i>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-red-700 dark:text-red-300">Delete Account</h1>
                    <p class="text-sm text-red-600 dark:text-red-400 mt-0.5">This action cannot be undone</p>
                </div>
            </div>

            <div class="px-6 py-6 space-y-5">

                <!-- What will be deleted -->
                <div class="text-sm text-gray-600 dark:text-gray-400 space-y-2">
                    <p>You are about to permanently delete the account for:</p>
                    <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg px-4 py-3 border border-gray-200 dark:border-gray-700">
                        <div class="font-semibold text-gray-800 dark:text-gray-200"><?= $username ?></div>
                        <div class="text-xs text-gray-500 dark:text-gray-400"><?= $email ?></div>
                    </div>
                    <p>Deleting your account will:</p>
                    <ul class="list-disc list-outside ml-5 space-y-1">
                        <li>Permanently remove your profile and personal data</li>
                        <li>Cancel any active subscriptions</li>
                        <li>Revoke all API keys and tunnel credentials</li>
                        <li>Erase your chat history and sandbox files</li>
                    </ul>
                    <p class="text-red-600 dark:text-red-400 font-medium">
                        <i class="fas fa-circle-info mr-1"></i>
                        This cannot be reversed. There is no grace period.
                    </p>
                </div>

                <!-- Confirmation form -->
                <form id="deleteAccountForm" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                    <div>
                        <label for="confirmPassword" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Confirm your password to proceed
                        </label>
                        <input
                            type="password"
                            id="confirmPassword"
                            name="password"
                            autocomplete="current-password"
                            placeholder="Enter your current password"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
                            required
                        >
                    </div>

                    <div id="deleteError" class="hidden text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 rounded-lg px-4 py-3 border border-red-200 dark:border-red-800">
                        <i class="fas fa-circle-exclamation mr-1"></i>
                        <span id="deleteErrorText"></span>
                    </div>

                    <div class="flex items-center gap-3 pt-1">
                        <button
                            type="submit"
                            id="deleteBtn"
                            class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-lg transition-colors"
                        >
                            <span id="deleteBtnText"><i class="fas fa-trash-can mr-2"></i>Yes, delete my account</span>
                            <span id="deleteBtnLoading" class="hidden"><i class="fas fa-circle-notch fa-spin mr-2"></i>Deleting…</span>
                        </button>
                        <a
                            href="/account"
                            class="flex-1 text-center px-4 py-2.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-lg transition-colors"
                        >
                            Cancel
                        </a>
                    </div>
                </form>

            </div>
        </div>

    </div>
</div>

<script>
document.getElementById('deleteAccountForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn       = document.getElementById('deleteBtn');
    const btnText   = document.getElementById('deleteBtnText');
    const btnLoad   = document.getElementById('deleteBtnLoading');
    const errBox    = document.getElementById('deleteError');
    const errText   = document.getElementById('deleteErrorText');

    errBox.classList.add('hidden');
    btn.disabled = true;
    btnText.classList.add('hidden');
    btnLoad.classList.remove('hidden');

    const data = new FormData(this);

    try {
        const res  = await fetch('/account/delete/confirm', { method: 'POST', body: data });
        const json = await res.json();

        if (json.success) {
            // Redirect to login with a notice
            window.location.href = '/login?notice=account_deleted';
        } else {
            errText.textContent = json.error ?? 'An error occurred. Please try again.';
            errBox.classList.remove('hidden');
            btn.disabled = false;
            btnText.classList.remove('hidden');
            btnLoad.classList.add('hidden');
        }
    } catch (err) {
        errText.textContent = 'Network error. Please try again.';
        errBox.classList.remove('hidden');
        btn.disabled = false;
        btnText.classList.remove('hidden');
        btnLoad.classList.add('hidden');
    }
});
</script>

<?php include ROOT_PATH . '/src/Views/layout/footer.php'; ?>
