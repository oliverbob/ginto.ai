<?php
// gtb/pages/settings.php - Binance API settings form
// Writes BINANCE_* to .env via POST /gtb-settings (same mechanism as /live).
// The Testnet toggle is authoritative for the endpoint (no free-text base URL).

$binanceApiKey    = $binanceApiKey ?? '';
$binanceSecretSet = $binanceSecretSet ?? false;
$binanceTestnet   = $binanceTestnet ?? false;
$binanceEndpoint  = $binanceEndpoint ?? 'https://api.binance.com';
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
        <p class="font-semibold"><i class="fas fa-circle-info mr-1"></i>Testnet vs Mainnet keys are different</p>
        <ul class="mt-1 list-disc list-inside space-y-0.5 text-blue-700 dark:text-blue-300/90">
            <li><strong>Testnet:</strong> create a key at <code>testnet.binance.vision</code> (fake money). A mainnet key will NOT work here.</li>
            <li><strong>Mainnet:</strong> your real binance.com key — real funds. Enable Reading + Spot, keep Withdrawals off, restrict to this server's IP.</li>
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
                   onchange="gtbSyncEndpoint()"
                   class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary focus:ring-primary">
            <span class="text-sm text-gray-700 dark:text-gray-300">Use Binance <strong>Testnet</strong> (fake money for safe testing)</span>
        </label>

        <div>
            <span class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Endpoint</span>
            <div class="flex items-center gap-2 px-3 py-2.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 text-sm font-mono">
                <i class="fas fa-link text-gray-400"></i>
                <span id="binance_endpoint_display" class="text-gray-700 dark:text-gray-300"><?= htmlspecialchars($binanceEndpoint) ?></span>
            </div>
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Derived automatically from the Testnet toggle — the flag and endpoint always match.</p>
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-1">
            <button type="button" id="gtb-save-btn" onclick="gtbSaveSettings()"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg font-semibold bg-primary text-white hover:bg-primary/90 disabled:opacity-60">
                <i class="fas fa-floppy-disk"></i> Save settings
            </button>
            <button type="button" id="gtb-test-btn" onclick="gtbTestConnection()"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg font-semibold border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:border-primary hover:text-primary disabled:opacity-60">
                <i class="fas fa-plug"></i> Test connection
            </button>
            <span id="gtb-save-status" class="text-sm"></span>
        </div>

        <!-- Test connection result -->
        <div id="gtb-test-result" class="hidden mt-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-4 text-sm"></div>
    </form>
</div>

<script>
    const GTB_CSRF = <?= json_encode($csrf_token) ?>;

    function gtbSyncEndpoint() {
        const testnet = document.getElementById('binance_testnet').checked;
        document.getElementById('binance_endpoint_display').textContent =
            testnet ? 'https://testnet.binance.vision' : 'https://api.binance.com';
    }

    async function gtbSaveSettings() {
        const btn = document.getElementById('gtb-save-btn');
        const status = document.getElementById('gtb-save-status');
        const secretEl = document.getElementById('binance_api_secret');

        const payload = {
            csrf_token: GTB_CSRF,
            binance_api_key: document.getElementById('binance_api_key').value.trim(),
            binance_api_secret: secretEl.value,
            binance_testnet: document.getElementById('binance_testnet').checked,
        };

        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
        status.textContent = '';
        status.className = 'text-sm';

        try {
            const res = await fetch('/gtb-settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': GTB_CSRF },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (res.ok && data.success) {
                status.textContent = '✓ ' + (data.message || 'Saved');
                status.className = 'text-sm text-green-600 dark:text-green-400';
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

    async function gtbTestConnection() {
        const btn = document.getElementById('gtb-test-btn');
        const box = document.getElementById('gtb-test-result');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing…';
        box.classList.remove('hidden');
        box.innerHTML = '<span class="text-gray-500">Contacting Binance…</span>';

        try {
            const res = await fetch('/gtb/test-connection', { headers: { 'X-CSRF-TOKEN': GTB_CSRF } });
            const d = await res.json();
            if (d.ok) {
                const rows = (d.balances || []).map(b =>
                    `<tr><td class="pr-4 font-medium">${b.asset}</td>` +
                    `<td class="pr-4 text-right tabular-nums">${(+b.free).toLocaleString(undefined,{maximumFractionDigits:8})}</td>` +
                    `<td class="text-right tabular-nums text-gray-400">${(+b.locked).toLocaleString(undefined,{maximumFractionDigits:8})}</td></tr>`).join('');
                box.innerHTML =
                    `<div class="text-green-600 dark:text-green-400 font-semibold mb-1"><i class="fas fa-circle-check"></i> Connected</div>` +
                    `<div class="text-xs text-gray-500 dark:text-gray-400 mb-2">${d.testnet ? 'Testnet' : 'Mainnet'} · ${d.endpoint} · canTrade: ${d.canTrade}</div>` +
                    (rows
                        ? `<table class="w-full text-xs"><thead><tr class="text-gray-400 text-left"><th class="pr-4">Asset</th><th class="pr-4 text-right">Free</th><th class="text-right">Locked</th></tr></thead><tbody>${rows}</tbody></table>`
                        : `<div class="text-xs text-gray-400">No non-zero balances (empty wallet — normal for a fresh key).</div>`);
            } else {
                box.innerHTML =
                    `<div class="text-red-500 font-semibold mb-1"><i class="fas fa-circle-xmark"></i> Failed</div>` +
                    `<div class="text-xs text-gray-500 dark:text-gray-400">${d.error || 'Unknown error'}</div>` +
                    (d.endpoint ? `<div class="text-xs text-gray-400 mt-1">${d.testnet ? 'Testnet' : 'Mainnet'} · ${d.endpoint}</div>` : '');
            }
        } catch (e) {
            box.innerHTML = `<div class="text-red-500"><i class="fas fa-circle-xmark"></i> ${e.message}</div>`;
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }
</script>
