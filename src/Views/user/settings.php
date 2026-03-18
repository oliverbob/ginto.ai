<?php
/**
 * User Settings page
 * Expects: $user (array), $success (string|null), $error (string|null)
 */
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

if (empty($_SESSION['csrf_token'])) {
    try { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
    catch (\Throwable $e) { $_SESSION['csrf_token'] = ''; }
}
$csrf = $_SESSION['csrf_token'] ?? '';

require_once __DIR__ . '/../layout/header.php';
?>
<style>
html, body { height: 100%; margin: 0; padding: 0; overflow: hidden; }
.sidebar { height: 100vh; overflow: auto; -webkit-overflow-scrolling: touch; }
#settingsWrapper { height: 100vh; overflow-y: auto; -webkit-overflow-scrolling: touch; }
.settings-card { background: white; border: 1px solid #e5e7eb; border-radius: 16px; padding: 24px; max-width: 640px; margin-bottom: 24px; }
.dark .settings-card { background: #1f2937; border-color: #374151; }
.settings-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
.dark .settings-label { color: #d1d5db; }
.settings-input {
    width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #d1d5db;
    background: #f9fafb; color: #111827; font-size: 14px; box-sizing: border-box;
}
.settings-input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
.dark .settings-input { background: #111827; border-color: #374151; color: #f3f4f6; }
.dark .settings-input:focus { border-color: #6366f1; }
.settings-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; background-size: 18px; padding-right: 36px; }
.btn-primary { background: #6366f1; color: white; border: none; padding: 10px 22px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; transition: background 0.2s; }
.btn-primary:hover { background: #4f46e5; }
.btn-primary:disabled { background: #a5b4fc; cursor: not-allowed; }
.alert-success { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; border-radius: 8px; padding: 12px 16px; margin-bottom: 18px; font-size: 14px; display: flex; align-items: center; gap: 8px; }
.alert-error { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; border-radius: 8px; padding: 12px 16px; margin-bottom: 18px; font-size: 14px; display: flex; align-items: center; gap: 8px; }
.dark .alert-success { background: #064e3b; border-color: #059669; color: #6ee7b7; }
.dark .alert-error { background: #7f1d1d; border-color: #ef4444; color: #fca5a5; }
.section-divider { border: none; border-top: 1px solid #e5e7eb; margin: 20px 0; }
.dark .section-divider { border-color: #374151; }
</style>

<div class="flex h-screen">
    <?php require_once __DIR__ . '/../layout/sidebar.php'; ?>

    <div id="settingsWrapper" class="flex-1 lg:ml-64">
        <div class="p-4 sm:p-6" style="max-width: 700px;">

            <div class="mb-6">
                <h1 class="text-2xl font-bold themed-text">Settings</h1>
                <p class="text-sm themed-text-secondary mt-1">Manage your profile and account security.</p>
            </div>

            <?php if (!empty($success)): ?>
            <div class="alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
            <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Profile -->
            <div class="settings-card">
                <h2 class="text-base font-bold themed-text mb-4">Profile Information</h2>
                <form method="POST" action="/user/settings/update" id="profileForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="form" value="profile">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="settings-label" for="s_firstname">First name</label>
                            <input id="s_firstname" name="first_name" type="text" class="settings-input"
                                   value="<?= htmlspecialchars($user['first_name'] ?? $user['firstname'] ?? '') ?>" maxlength="50">
                        </div>
                        <div>
                            <label class="settings-label" for="s_lastname">Last name</label>
                            <input id="s_lastname" name="last_name" type="text" class="settings-input"
                                   value="<?= htmlspecialchars($user['last_name'] ?? $user['lastname'] ?? '') ?>" maxlength="50">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="settings-label" for="s_email">Email address</label>
                        <input id="s_email" name="email" type="email" class="settings-input"
                               value="<?= htmlspecialchars($user['email'] ?? '') ?>" maxlength="100" required>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="settings-label" for="s_phone">Phone</label>
                            <input id="s_phone" name="phone" type="tel" class="settings-input"
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>" maxlength="20">
                        </div>
                        <div>
                            <label class="settings-label" for="s_gender">Gender</label>
                            <select id="s_gender" name="gender" class="settings-input settings-select">
                                <option value="">— select —</option>
                                <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other', 'prefer_not' => 'Prefer not to say'] as $v => $l): ?>
                                <option value="<?= $v ?>" <?= ($user['gender'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="settings-label" for="s_country">Country</label>
                        <input id="s_country" name="country" type="text" class="settings-input"
                               value="<?= htmlspecialchars($user['country'] ?? '') ?>" maxlength="5" placeholder="e.g. PH, US, GB">
                    </div>

                    <div class="mb-5">
                        <label class="settings-label" for="s_bio">Bio <span class="font-normal text-gray-400">(optional)</span></label>
                        <textarea id="s_bio" name="bio" rows="3" class="settings-input" maxlength="500"
                                  style="resize:vertical;"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn-primary" id="saveProfileBtn">
                        <i class="fas fa-save mr-1"></i> Save Profile
                    </button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="settings-card">
                <h2 class="text-base font-bold themed-text mb-4">Change Password</h2>
                <form method="POST" action="/user/settings/update" id="passwordForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="form" value="password">

                    <div class="mb-4">
                        <label class="settings-label" for="s_current_pw">Current password</label>
                        <input id="s_current_pw" name="current_password" type="password" class="settings-input"
                               autocomplete="current-password" required>
                    </div>
                    <div class="mb-4">
                        <label class="settings-label" for="s_new_pw">New password <span class="font-normal text-gray-400">(min 8 characters)</span></label>
                        <input id="s_new_pw" name="new_password" type="password" class="settings-input"
                               autocomplete="new-password" minlength="8" required>
                    </div>
                    <div class="mb-5">
                        <label class="settings-label" for="s_confirm_pw">Confirm new password</label>
                        <input id="s_confirm_pw" name="confirm_password" type="password" class="settings-input"
                               autocomplete="new-password" minlength="8" required>
                    </div>

                    <button type="submit" class="btn-primary" id="savePasswordBtn">
                        <i class="fas fa-lock mr-1"></i> Update Password
                    </button>
                </form>
            </div>

            <!-- Danger Zone -->
            <div class="settings-card" style="border-color: #fca5a5;">
                <h2 class="text-base font-bold text-red-600 dark:text-red-400 mb-2">Danger Zone</h2>
                <p class="text-sm themed-text-secondary mb-4">Permanently delete your account and all associated data. This action cannot be undone.</p>
                <a href="/account/delete"
                   class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-semibold text-sm py-2 px-4 rounded-lg">
                    <i class="fas fa-trash-alt"></i> Delete My Account
                </a>
            </div>

        </div>
    </div>
</div>

<script>
(function() {
    // Disable submit button while form is submitting to prevent double-clicks
    ['profileForm', 'passwordForm'].forEach(function(id) {
        var form = document.getElementById(id);
        if (!form) return;
        form.addEventListener('submit', function() {
            var btn = form.querySelector('[type=submit]');
            if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
        });
    });
})();
</script>

</body>
</html>
