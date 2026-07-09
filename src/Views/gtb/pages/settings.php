<?php
// gtb/pages/settings.php - Binance API settings form
// Writes BINANCE_* to .env via POST /gtb-settings (same mechanism as /live).

$binanceApiKey    = $binanceApiKey ?? '';
$binanceSecretSet = $binanceSecretSet ?? false;
$binanceTestnet   = $binanceTestnet ?? false;
$binanceBaseUrl   = $binanceBaseUrl ?? 'https://api.binance.com';
$csrf_token       = $csrf_token ?? '';
$apiConfigured    = $apiConfigured ?? false;
?>

<div class="mb-6 flex items-center gap-3">
    <a href="/gtb" class="text-sm text-gray-500 dark:text-gray-400 hover:text-primary">
        <i class="fas fa-arrow-left mr-1"></i>Dashboard
    </a>
</div>

<div class="max-w-2xl">
    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Binance API Settings</h2>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        Saved to <code class="px-1 rounded bg-gray-100 dark:bg-gray-700">.env</code> and read the same way as the
        rest of the app. The secret is stored write-only here.
    </p>

    <!-- Guidance -->
    <div class="mt-5 rounded-xl border border-blue-200 dark:border-blue-500/30 bg-blue-50 dark:bg-blue-500/10 p-4 text-sm text-blue-800 dark:text-blue-200">
        <p class="font-semibold"><i class="fas fa-circle-info mr-1"></i>On your Binance API key</p>
        <ul class="mt-1 list-disc list-inside space-y-0.5 text-blue-700 dark:text-blue-300/90">
            <li>Enable <strong>Reading</strong> and <strong>Spot &amp; Margin Trading</strong>.</li>
            <li>Keep <strong>Withdrawals</strong> and <strong>Futures</strong> disabled.</li>
            <li>Restrict access to this server's IP for safety.</li>
        </ul>
    </div>

    <form id="gtb-settings-form" class="mt-6 space-y-5" onsubmit="return false;">
        <div>
            <label for="binance_api_key" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">API Key</label>
            <input type="text" id="binance_api_key" autocomplete="off" spellcheck="false"
                   value="<?= htmlspecialchars($binanceApiKey) ?>"
                   class="w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary focus:border-primary outline-none font-mono text-sm"
                   placeholder="Your Binance API key">
        </div>

        <div>
            <label for="binance_api_secret" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Secret Key</label>
            <input type="password" id="binance_api_secret" autocomplete="new-password" spellcheck="false"
                   class="w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary focus:border-primary outline-none font-mono text-sm"
                   placeholder="<?= $binanceSecretSet ? '•••••••• saved — leave blank to keep' : 'Your Binance secret key' ?>">
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                <?= $binanceSecretSet
                    ? 'A secret is already saved. Leave blank to keep it, or type a new one to replace it.'
                    : 'The secret is never shown back after saving.' ?>
            </p>
        </div>

        <label class="flex items-center gap-3 cursor-pointer select-none">
            <input type="checkbox" id="binance_testnet" <?= $binanceTestnet ? 'checked' : '' ?>
                   class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary focus:ring-primary">
            <span class="text-sm text-gray-700 dark:text-gray-300">Use Binance <strong>Testnet</strong></span>
        </label>

        <div>
            <label for="binance_base_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Base URL</label>
            <input type="text" id="binance_base_url" autocomplete="off" spellcheck="false"
                   value="<?= htmlspecialchars($binanceBaseUrl) ?>"
                   class="w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary focus:border-primary outline-none font-mono text-sm"
                   placeholder="https://api.binance.com">
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                Mainnet: <code>https://api.binance.com</code> &middot; Testnet: <code>https://testnet.binance.vision</code>
                (leave blank to auto-fill from the Testnet toggle).
            </p>
        </div>

        <div class="flex items-center gap-4 pt-1">
            <button type="button" id="gtb-save-btn" onclick="gtbSaveSettings()"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg font-semibold bg-primary text-white hover:bg-primary/90 disabled:opacity-60">
                <i class="fas fa-floppy-disk"></i> Save settings
            </button>
            <span id="gtb-save-status" class="text-sm"></span>
        </div>
    </form>
</div>

<script>
    async function gtbSaveSettings() {
        const btn = document.getElementById('gtb-save-btn');
        const status = document.getElementById('gtb-save-status');
        const secretEl = document.getElementById('binance_api_secret');
        const csrf = <?= json_encode($csrf_token) ?>;

        const payload = {
            csrf_token: csrf,
            binance_api_key: document.getElementById('binance_api_key').value.trim(),
            binance_api_secret: secretEl.value,
            binance_testnet: document.getElementById('binance_testnet').checked,
            binance_base_url: document.getElementById('binance_base_url').value.trim(),
        };

        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
        status.textContent = '';
        status.className = 'text-sm';

        try {
            const res = await fetch('/gtb-settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (res.ok && data.success) {
                status.textContent = '✓ ' + (data.message || 'Saved');
                status.className = 'text-sm text-green-600 dark:text-green-400';
                // Clear the secret field and reflect the saved state
                secretEl.value = '';
                secretEl.placeholder = '•••••••• saved — leave blank to keep';
            } else {
                status.textContent = '✗ ' + (data.error || 'Save failed');
                status.className = 'text-sm text-red-500 dark:text-red-400';
            }
        } catch (e) {
            status.textContent = '✗ ' + e.message;
            status.className = 'text-sm text-red-500 dark:text-red-400';
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }
</script>
