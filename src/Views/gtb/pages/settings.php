<?php
// gtb/pages/settings.php - Binance API settings (dual mainnet + testnet keys)
// Writes BINANCE_* to .env via POST /gtb-settings. The Testnet toggle selects the
// ACTIVE key pair + endpoint, so switching environments needs no re-entry of keys.

$mainnetApiKey    = $mainnetApiKey ?? '';
$mainnetSecretSet = $mainnetSecretSet ?? false;
$testnetApiKey    = $testnetApiKey ?? '';
$testnetSecretSet = $testnetSecretSet ?? false;
$binanceTestnet   = $binanceTestnet ?? false;
$binanceEndpoint  = $binanceEndpoint ?? 'https://api.binance.com';
$anthropicKeySet   = $anthropicKeySet ?? false;
$anthropicModel    = $anthropicModel ?? 'claude-opus-4-8';
$anthropicScanModel = $anthropicScanModel ?? 'claude-haiku-4-5';
$gtbTemplates      = $gtbTemplates ?? ['scalp', 'breakout', 'trend', 'pullback'];
$gtbMemory         = $gtbMemory ?? false;
$csrf_token        = $csrf_token ?? '';

$gtbModelOptions = [
    'claude-haiku-4-5' => 'Haiku 4.5 — cheapest ($1 / $5 per 1M)',
    'claude-sonnet-5'  => 'Sonnet 5 — mid ($3 / $15 per 1M)',
    'claude-opus-4-8'  => 'Opus 4.8 — smartest ($5 / $25 per 1M)',
];
function gtb_model_select(string $id, string $current, array $opts): void {
    echo '<select id="' . $id . '" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary outline-none text-sm">';
    foreach ($opts as $val => $label) {
        echo '<option value="' . htmlspecialchars($val) . '"' . ($val === $current ? ' selected' : '') . '>' . htmlspecialchars($label) . '</option>';
    }
    echo '</select>';
}

function gtb_key_section(string $env, string $label, string $apiKey, bool $secretSet, string $tone): void {
    $keyId = "{$env}_api_key";
    $secId = "{$env}_api_secret";
    ?>
    <div id="gtb-sec-<?= $env ?>" class="rounded-xl border p-4 space-y-4 border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($label) ?></h3>
            <span id="gtb-active-<?= $env ?>" class="hidden text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-primary/10 text-primary">Active</span>
        </div>
        <div>
            <label for="<?= $keyId ?>" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">API Key</label>
            <input type="text" id="<?= $keyId ?>" autocomplete="off" spellcheck="false"
                   value="<?= htmlspecialchars($apiKey) ?>"
                   class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary outline-none font-mono text-sm"
                   placeholder="<?= htmlspecialchars($label) ?> API key">
        </div>
        <div>
            <label for="<?= $secId ?>" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Secret Key</label>
            <input type="password" id="<?= $secId ?>" autocomplete="new-password" spellcheck="false"
                   class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary outline-none font-mono text-sm"
                   placeholder="<?= $secretSet ? '•••••••• saved — leave blank to keep' : ($label . ' secret key') ?>">
            <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                <?= $secretSet ? 'Saved. Leave blank to keep, or type a new one to replace.' : 'Write-only — never shown after saving.' ?>
            </p>
        </div>
    </div>
    <?php
}
?>

<div class="mb-6 flex items-center gap-3">
    <a href="/gtb" class="text-sm text-gray-500 dark:text-gray-400 hover:text-primary">
        <i class="fas fa-arrow-left mr-1"></i>Dashboard
    </a>
</div>

<div class="max-w-3xl">
    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Binance API Settings</h2>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        Store your mainnet and testnet keys once; the <strong>active environment</strong> toggle picks which the bot uses.
        Saved to <code class="px-1 rounded bg-gray-100 dark:bg-gray-700">.env</code>; secrets are write-only.
    </p>

    <!-- Active environment -->
    <div class="mt-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-4">
        <label class="flex items-center gap-3 cursor-pointer select-none">
            <input type="checkbox" id="binance_testnet" <?= $binanceTestnet ? 'checked' : '' ?>
                   onchange="gtbSyncActive()"
                   class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary focus:ring-primary">
            <span class="text-sm text-gray-700 dark:text-gray-300">Use Binance <strong>Testnet</strong> (fake money) as the active environment</span>
        </label>
        <div class="mt-3 flex items-center gap-2 text-sm font-mono">
            <span class="text-xs text-gray-500 dark:text-gray-400">Endpoint:</span>
            <span id="binance_endpoint_display" class="text-gray-700 dark:text-gray-300"><?= htmlspecialchars($binanceEndpoint) ?></span>
        </div>
    </div>

    <form id="gtb-settings-form" class="mt-4 space-y-4" onsubmit="return false;">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php gtb_key_section('mainnet', 'Mainnet (real funds)', $mainnetApiKey, $mainnetSecretSet, 'red'); ?>
            <?php gtb_key_section('testnet', 'Testnet', $testnetApiKey, $testnetSecretSet, 'amber'); ?>
        </div>

        <div class="rounded-xl border border-blue-200 dark:border-blue-500/30 bg-blue-50 dark:bg-blue-500/10 p-3 text-xs text-blue-800 dark:text-blue-200">
            <i class="fas fa-circle-info mr-1"></i>
            Testnet keys come from <code>testnet.binance.vision</code> (tick TRADE + USER_DATA). Mainnet keys from binance.com —
            enable Reading + Spot, keep Withdrawals off, restrict to this server's IP.
        </div>

        <!-- AI Brain (Claude) -->
        <div class="rounded-xl border p-4 space-y-3 border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-robot text-primary mr-1.5"></i>AI Brain (Claude)</h3>
                <span class="text-[10px] font-mono text-gray-400 dark:text-gray-500"><?= htmlspecialchars($anthropicModel) ?></span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                A <strong>paid Anthropic API key</strong> from <code>console.anthropic.com</code> (this is <em>not</em> your Claude Pro login — Pro can't be used programmatically). Powers the bot's self-reflection chat.
            </p>
            <div>
                <label for="anthropic_api_key" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Anthropic API Key</label>
                <input type="password" id="anthropic_api_key" autocomplete="new-password" spellcheck="false"
                       class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary outline-none font-mono text-sm"
                       placeholder="<?= $anthropicKeySet ? '•••••••• saved — leave blank to keep' : 'sk-ant-...' ?>">
                <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                    <?= $anthropicKeySet ? 'A key is saved. Leave blank to keep it, or type a new one to replace it.' : 'Write-only — never shown after saving. Billed per token, separately from Claude Pro.' ?>
                </p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="scan_model" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Scan model (cheap, frequent)</label>
                    <?php gtb_model_select('scan_model', $anthropicScanModel, $gtbModelOptions); ?>
                </div>
                <div>
                    <label for="decision_model" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Decision model (used by “Reflect now”)</label>
                    <?php gtb_model_select('decision_model', $anthropicModel, $gtbModelOptions); ?>
                </div>
            </div>
            <p class="text-[11px] text-gray-400 dark:text-gray-500">
                Routine market scans use the cheaper model; actual trade decisions use the smarter one. A reflection costs roughly Haiku ~0.2¢ · Opus ~1¢.
            </p>
        </div>

        <!-- Strategy templates -->
        <div class="rounded-xl border p-4 space-y-2.5 border-gray-200 dark:border-gray-700">
            <h3 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-shapes text-primary mr-1.5"></i>Strategy templates</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Which strategies the bot may run. Each open capital slot uses one, diversified least-used-first.</p>
            <?php
            $tplOpts = [
                'scalp'    => ['Scalp Momentum', 'Top gainer, tight stop-loss / take-profit'],
                'breakout' => ['Breakout', 'Coin pressing its 24h high on volume'],
                'trend'    => ['Trend Trailing', 'Ride momentum with a trailing stop'],
                'pullback' => ['Pullback Dip', 'Buy an uptrend that pulled back off its 24h high'],
            ];
            foreach ($tplOpts as $k => $info): ?>
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" class="gtb-tpl mt-0.5 w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary focus:ring-primary"
                           value="<?= $k ?>" <?= in_array($k, $gtbTemplates, true) ? 'checked' : '' ?>>
                    <span class="text-sm"><span class="font-medium text-gray-800 dark:text-gray-200"><?= $info[0] ?></span>
                        <span class="text-xs text-gray-400 dark:text-gray-500">— <?= $info[1] ?></span></span>
                </label>
            <?php endforeach; ?>

            <label class="flex items-start gap-2 cursor-pointer pt-1 border-t border-gray-100 dark:border-gray-700/60 mt-1">
                <input type="checkbox" id="memory_enabled" class="mt-0.5 w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary focus:ring-primary" <?= $gtbMemory ? 'checked' : '' ?>>
                <span class="text-sm">
                    <span class="font-medium text-gray-800 dark:text-gray-200">Enable memory</span>
                    <span class="block text-xs text-amber-600 dark:text-amber-400 mt-0.5">
                        <i class="fas fa-triangle-exclamation mr-0.5"></i> Feeds recent trade outcomes into each AI decision so it learns.
                        <strong>Increases tokens per decision</strong> (~+0.1–0.2¢ on Opus, ~+0.02¢ on Haiku). Off by default.
                    </span>
                </span>
            </label>
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-1">
            <button type="button" id="gtb-save-btn" onclick="gtbSaveSettings()"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg font-semibold bg-primary text-white hover:bg-primary/90 disabled:opacity-60">
                <i class="fas fa-floppy-disk"></i> Save settings
            </button>
            <button type="button" id="gtb-test-btn" onclick="gtbTestConnection()"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg font-semibold border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:border-primary hover:text-primary disabled:opacity-60">
                <i class="fas fa-plug"></i> Test active connection
            </button>
            <span id="gtb-save-status" class="text-sm"></span>
        </div>

        <div id="gtb-test-result" class="hidden mt-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-4 text-sm"></div>
    </form>
</div>

<script>
    const GTB_CSRF = <?= json_encode($csrf_token) ?>;

    function gtbSyncActive() {
        const testnet = document.getElementById('binance_testnet').checked;
        document.getElementById('binance_endpoint_display').textContent =
            testnet ? 'https://testnet.binance.vision' : 'https://api.binance.com';
        // Highlight the active section + show its "Active" pill
        const on = { mainnet: !testnet, testnet: testnet };
        for (const env of ['mainnet', 'testnet']) {
            document.getElementById('gtb-sec-' + env).classList.toggle('ring-2', on[env]);
            document.getElementById('gtb-sec-' + env).classList.toggle('ring-primary', on[env]);
            document.getElementById('gtb-active-' + env).classList.toggle('hidden', !on[env]);
        }
    }

    async function gtbSaveSettings() {
        const btn = document.getElementById('gtb-save-btn');
        const status = document.getElementById('gtb-save-status');
        const payload = {
            csrf_token: GTB_CSRF,
            binance_testnet: document.getElementById('binance_testnet').checked,
            mainnet_api_key: document.getElementById('mainnet_api_key').value.trim(),
            mainnet_api_secret: document.getElementById('mainnet_api_secret').value,
            testnet_api_key: document.getElementById('testnet_api_key').value.trim(),
            testnet_api_secret: document.getElementById('testnet_api_secret').value,
            anthropic_api_key: document.getElementById('anthropic_api_key').value,
            scan_model: document.getElementById('scan_model').value,
            decision_model: document.getElementById('decision_model').value,
            templates: Array.from(document.querySelectorAll('.gtb-tpl:checked')).map(c => c.value),
            memory_enabled: document.getElementById('memory_enabled').checked,
        };
        const orig = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
        status.textContent = ''; status.className = 'text-sm';
        try {
            const res = await fetch('/gtb-settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': GTB_CSRF },
                body: JSON.stringify(payload),
            });
            const d = await res.json();
            if (res.ok && d.success) {
                status.textContent = '✓ ' + (d.message || 'Saved') + (d.configured ? '' : ' — active env still missing key/secret');
                status.className = 'text-sm ' + (d.configured ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400');
                ['mainnet_api_secret', 'testnet_api_secret', 'anthropic_api_key'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el.value) { el.value = ''; el.placeholder = '•••••••• saved — leave blank to keep'; }
                });
            } else {
                status.textContent = '✗ ' + (d.error || 'Save failed');
                status.className = 'text-sm text-red-500 dark:text-red-400';
            }
        } catch (e) {
            status.textContent = '✗ ' + e.message; status.className = 'text-sm text-red-500 dark:text-red-400';
        } finally { btn.disabled = false; btn.innerHTML = orig; }
    }

    async function gtbTestConnection() {
        const btn = document.getElementById('gtb-test-btn');
        const box = document.getElementById('gtb-test-result');
        const orig = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing…';
        box.classList.remove('hidden');
        box.innerHTML = '<span class="text-gray-500">Contacting Binance…</span>';
        try {
            const res = await fetch('/gtb/account');
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
                        : `<div class="text-xs text-gray-400">No non-zero balances (empty wallet — normal for a fresh testnet key; fund it from the testnet faucet).</div>`);
            } else {
                box.innerHTML =
                    `<div class="text-red-500 font-semibold mb-1"><i class="fas fa-circle-xmark"></i> Failed</div>` +
                    `<div class="text-xs text-gray-500 dark:text-gray-400">${d.error || 'Unknown error'}</div>` +
                    (d.endpoint ? `<div class="text-xs text-gray-400 mt-1">${d.testnet ? 'Testnet' : 'Mainnet'} · ${d.endpoint}</div>` : '');
            }
        } catch (e) {
            box.innerHTML = `<div class="text-red-500"><i class="fas fa-circle-xmark"></i> ${e.message}</div>`;
        } finally { btn.disabled = false; btn.innerHTML = orig; }
    }

    document.addEventListener('DOMContentLoaded', gtbSyncActive);
</script>
