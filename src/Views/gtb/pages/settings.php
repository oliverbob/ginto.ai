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
$aiProvider        = $aiProvider ?? 'anthropic';
$groqKeySet        = $groqKeySet ?? false;
$groqSharedKey     = $groqSharedKey ?? false;
$groqModel         = $groqModel ?? 'openai/gpt-oss-120b';
$groqScanModel     = $groqScanModel ?? 'llama-3.1-8b-instant';
$gtbTemplates      = $gtbTemplates ?? ['scalp', 'breakout', 'trend', 'pullback'];
$gtbMemory         = $gtbMemory ?? false;
$gtbInstructions   = $gtbInstructions ?? '';
$gtbPineScript     = $gtbPineScript ?? '';
$gtbProfiles       = $gtbProfiles ?? ['conservative', 'aggressive'];
$gtbCapitalMode    = $gtbCapitalMode ?? 'staked';
$gtbPaperWallet    = $gtbPaperWallet ?? 35;
$gtbMaxHoldMin     = $gtbMaxHoldMin ?? 0;
$gtbSessionHours   = $gtbSessionHours ?? 0;
$gtbStallMin       = $gtbStallMin ?? 0;
$gtbStallGain      = $gtbStallGain ?? 0;
$gtbSessionMaxLoss = $gtbSessionMaxLoss ?? 0;
$gtbTpOverride     = $gtbTpOverride ?? 0;
$gtbSlOverride     = $gtbSlOverride ?? 0;
$gtbBaseCapital    = $gtbBaseCapital ?? 7;
$gtbMinNotional    = $gtbMinNotional ?? 5;
$gtbMaxTrade       = $gtbMaxTrade ?? 0;
$gtbGrowthUnit     = $gtbGrowthUnit ?? 5;
$gtbWalletFloor    = $gtbWalletFloor ?? 0;
$nf = fn($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.') ?: '0';
$csrf_token        = $csrf_token ?? '';

$gtbModelOptions = [
    'claude-haiku-4-5' => 'Haiku 4.5 — cheapest ($1 / $5 per 1M)',
    'claude-sonnet-5'  => 'Sonnet 5 — mid ($3 / $15 per 1M)',
    'claude-opus-4-8'  => 'Opus 4.8 — smartest ($5 / $25 per 1M)',
];
$gtbGroqModels = [
    'llama-3.1-8b-instant'                      => 'Llama 3.1 8B Instant — cheapest, for scans ($0.05 / $0.08)',
    'openai/gpt-oss-20b'                        => 'GPT-OSS 20B — cheap reasoning ($0.10 / $0.50)',
    'meta-llama/llama-4-scout-17b-16e-instruct' => 'Llama 4 Scout 17B — fast ($0.11 / $0.34)',
    'qwen/qwen3-32b'                            => 'Qwen3 32B — reasoning ($0.29 / $0.59)',
    'qwen/qwen3.6-27b'                          => 'Qwen3.6 27B — newest, highest intelligence',
    'openai/gpt-oss-120b'                       => 'GPT-OSS 120B — best for trading (reasoning + fast) ($0.15 / $0.60)',
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

        <!-- AI Brain (provider-based) -->
        <div class="rounded-xl border p-4 space-y-3 border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-robot text-primary mr-1.5"></i>AI Brain</h3>
                <span class="text-[10px] font-mono text-gray-400 dark:text-gray-500"><?= htmlspecialchars($aiProvider === 'groq' ? $groqModel : $anthropicModel) ?></span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">The engine that reflects and confirms trades. Both providers below keep their own API key &amp; models — the <strong>Provider</strong> picks which one the bot uses. <strong>Groq</strong> (Llama, Qwen, GPT-OSS) runs ~10–40× cheaper than <strong>Claude</strong>.</p>

            <div>
                <label for="ai_provider" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Provider</label>
                <select id="ai_provider" onchange="gtbSyncProvider()" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary outline-none text-sm">
                    <option value="groq" <?= $aiProvider === 'groq' ? 'selected' : '' ?>>Groq — cheapest (DeepSeek / Llama / Qwen)</option>
                    <option value="anthropic" <?= $aiProvider === 'anthropic' ? 'selected' : '' ?>>Anthropic — Claude (premium)</option>
                </select>
            </div>

            <!-- Groq block (always visible; the dropdown only marks which is active) -->
            <div id="prov-groq" class="space-y-3 rounded-lg border p-3 border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200"><i class="fas fa-bolt text-primary mr-1"></i>Groq</span>
                    <span id="prov-badge-groq" class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400 <?= $aiProvider === 'groq' ? '' : 'hidden' ?>">Active</span>
                </div>
                <div>
                    <label for="groq_api_key" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Groq API Key <span class="text-gray-400 font-normal">(dedicated to the bot)</span></label>
                    <input type="password" id="groq_api_key" autocomplete="new-password" spellcheck="false"
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary outline-none font-mono text-sm"
                           placeholder="<?= $groqKeySet ? '•••••••• saved — leave blank to keep' : 'gsk_...' ?>">
                    <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">Its own key from <code>console.groq.com</code> (separate from the app's Groq key). Write-only. <?= $groqKeySet ? 'A dedicated key is saved.' : ($groqSharedKey ? 'Until you add one, the bot uses the app-wide Groq key.' : '') ?></p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="groq_scan_model" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Scan model (cheap, frequent)</label>
                        <?php gtb_model_select('groq_scan_model', $groqScanModel, $gtbGroqModels); ?>
                    </div>
                    <div>
                        <label for="groq_decision_model" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Decision model</label>
                        <?php gtb_model_select('groq_decision_model', $groqModel, $gtbGroqModels); ?>
                    </div>
                </div>
                <p class="text-[11px] text-gray-400 dark:text-gray-500"><strong>GPT-OSS 120B</strong> or <strong>Qwen3 32B</strong> = strongest reasoning; <strong>Llama 3.3 70B</strong> = fast &amp; balanced (recommended); <strong>Llama 3.1 8B</strong> = cheapest for scans. A decision costs ~0.02–0.1¢ (vs ~1¢ on Opus). <em>(DeepSeek R1 was retired from Groq.)</em></p>
            </div>

            <!-- Anthropic / Claude block (always visible) -->
            <div id="prov-anthropic" class="space-y-3 rounded-lg border p-3 border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200"><i class="fas fa-robot text-primary mr-1"></i>Claude (Anthropic)</span>
                    <span id="prov-badge-anthropic" class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400 <?= $aiProvider === 'anthropic' ? '' : 'hidden' ?>">Active</span>
                </div>
                <div>
                    <label for="anthropic_api_key" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Anthropic API Key</label>
                    <input type="password" id="anthropic_api_key" autocomplete="new-password" spellcheck="false"
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary outline-none font-mono text-sm"
                           placeholder="<?= $anthropicKeySet ? '•••••••• saved — leave blank to keep' : 'sk-ant-...' ?>">
                    <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500"><?= $anthropicKeySet ? 'A key is saved. Leave blank to keep it.' : 'Paid key from console.anthropic.com (not Claude Pro). Write-only.' ?></p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="scan_model" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Scan model (cheap, frequent)</label>
                        <?php gtb_model_select('scan_model', $anthropicScanModel, $gtbModelOptions); ?>
                    </div>
                    <div>
                        <label for="decision_model" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Decision model</label>
                        <?php gtb_model_select('decision_model', $anthropicModel, $gtbModelOptions); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Capital strategy -->
        <div class="rounded-xl border p-4 space-y-3 border-gray-200 dark:border-gray-700">
            <h3 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-coins text-primary mr-1.5"></i>Capital strategy</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">How much of your wallet the bot may put to work. Your $7 stake is always the starting point.</p>
            <?php
            $capOpts = [
                'staked' => ['Staked — $7 discipline', 'Never risk more than your $7 stake plus the profit it earns. Safest.'],
                'full'   => ['Full wallet (manual override)', 'After the $7 is working, deploy the entire available balance across concurrent slots.'],
                'ai'     => ['Performance-scaled (AI-guided)', 'Starts at $7 and unlocks more of the wallet only as the bot grows the stake; losses pull it back.'],
            ];
            foreach ($capOpts as $k => $info): ?>
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="radio" name="capital_mode" class="gtb-cap mt-0.5 w-4 h-4 border-gray-300 dark:border-gray-600 text-primary focus:ring-primary"
                           value="<?= $k ?>" <?= $gtbCapitalMode === $k ? 'checked' : '' ?>>
                    <span class="text-sm"><span class="font-medium text-gray-800 dark:text-gray-200"><?= $info[0] ?></span>
                        <span class="block text-xs text-gray-400 dark:text-gray-500"><?= $info[1] ?></span></span>
                </label>
            <?php endforeach; ?>
            <p id="cap-warn" class="text-xs text-amber-600 dark:text-amber-400 <?= $gtbCapitalMode === 'staked' ? 'hidden' : '' ?>">
                <i class="fas fa-triangle-exclamation mr-0.5"></i> This can risk more than $7 of real capital. Every position still opens with an exchange-side stop-loss, but larger size means larger swings.
            </p>
            <div class="flex items-center gap-2 pt-1 border-t border-gray-100 dark:border-gray-700/60">
                <label for="paper_wallet" class="text-xs text-gray-500 dark:text-gray-400">Paper wallet size (testnet sim, full/AI modes)</label>
                <span class="text-xs text-gray-400">$</span>
                <input type="number" id="paper_wallet" min="7" step="1" value="<?= htmlspecialchars((string) $gtbPaperWallet, ENT_QUOTES) ?>"
                       class="w-24 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm px-2 py-1 focus:ring-primary focus:border-primary">
            </div>
        </div>

        <!-- Capital & spend limits -->
        <div class="rounded-xl border p-4 space-y-3 border-gray-200 dark:border-gray-700">
            <h3 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-sliders text-primary mr-1.5"></i>Spend limits</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Exact dollar amounts the bot trades with. All in USDT.</p>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div>
                    <label for="base_capital" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Base capital ($)</label>
                    <input type="number" id="base_capital" min="1" step="1" value="<?= htmlspecialchars($nf($gtbBaseCapital), ENT_QUOTES) ?>"
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm px-2 py-1.5 focus:ring-primary focus:border-primary">
                    <p class="text-[11px] text-gray-400 mt-0.5">The stake it works with (staked mode).</p>
                </div>
                <div>
                    <label for="min_notional" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Min per-trade ($)</label>
                    <input type="number" id="min_notional" min="1" step="1" value="<?= htmlspecialchars($nf($gtbMinNotional), ENT_QUOTES) ?>"
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm px-2 py-1.5 focus:ring-primary focus:border-primary">
                    <p class="text-[11px] text-gray-400 mt-0.5">Smallest order it will place (Binance min ≈ $5).</p>
                </div>
                <div>
                    <label for="max_trade" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Max per-trade ($)</label>
                    <input type="number" id="max_trade" min="0" step="1" value="<?= htmlspecialchars($nf($gtbMaxTrade), ENT_QUOTES) ?>"
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm px-2 py-1.5 focus:ring-primary focus:border-primary">
                    <p class="text-[11px] text-gray-400 mt-0.5">Cap on one position. 0 = no cap.</p>
                </div>
                <div>
                    <label for="growth_unit" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Slot step ($)</label>
                    <input type="number" id="growth_unit" min="1" step="1" value="<?= htmlspecialchars($nf($gtbGrowthUnit), ENT_QUOTES) ?>"
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm px-2 py-1.5 focus:ring-primary focus:border-primary">
                    <p class="text-[11px] text-gray-400 mt-0.5">Each $X of tradable unlocks a concurrent slot. Higher = more concentrated.</p>
                </div>
                <div>
                    <label for="wallet_floor" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Wallet floor ($)</label>
                    <input type="number" id="wallet_floor" min="0" step="1" value="<?= htmlspecialchars($nf($gtbWalletFloor), ENT_QUOTES) ?>"
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm px-2 py-1.5 focus:ring-primary focus:border-primary">
                    <p class="text-[11px] text-gray-400 mt-0.5">Stop opening new trades when the wallet drops below this. 0 = off.</p>
                </div>
            </div>
        </div>

        <!-- Trading profiles (two bots, one wallet) -->
        <div class="rounded-xl border p-4 space-y-2.5 border-gray-200 dark:border-gray-700">
            <h3 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-user-group text-primary mr-1.5"></i>Trading bots</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Two temperaments sharing the <strong>same</strong> capital. Enable one or both — with both on, they run side by side and take turns on each free slot (least-used first, AI-confirmed).</p>
            <?php
            $profOpts = [
                'conservative' => ['Conservative', 'High-conviction only · trend + pullback · tighter risk, banks profit quickly'],
                'aggressive'   => ['Aggressive', 'Hunts fast movers · scalp + breakout · wider targets, lets winners run'],
            ];
            foreach ($profOpts as $k => $info): ?>
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" class="gtb-prof mt-0.5 w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary focus:ring-primary"
                           value="<?= $k ?>" <?= in_array($k, $gtbProfiles, true) ? 'checked' : '' ?>>
                    <span class="text-sm"><span class="font-medium text-gray-800 dark:text-gray-200"><?= $info[0] ?></span>
                        <span class="block text-xs text-gray-400 dark:text-gray-500"><?= $info[1] ?></span></span>
                </label>
            <?php endforeach; ?>
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

        <!-- Profit target override -->
        <div class="rounded-xl border p-4 space-y-2.5 border-gray-200 dark:border-gray-700">
            <h3 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-bullseye text-primary mr-1.5"></i>Profit target</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Force a fixed take-profit / stop-loss on <em>every</em> trade instead of the template's bigger targets. Use this to bank <strong>small, frequent</strong> wins. Leave 0 to keep template defaults.</p>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="tp_override" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Take-profit (%)</label>
                    <input type="number" id="tp_override" min="0" step="0.1" value="<?= $nf($gtbTpOverride) ?>"
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm px-2 py-1.5 focus:ring-primary focus:border-primary">
                    <p class="text-[11px] text-gray-400 mt-0.5">e.g. 1.0. Bank the win here. 0 = template default.</p>
                </div>
                <div>
                    <label for="sl_override" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Stop-loss (%)</label>
                    <input type="number" id="sl_override" min="0" step="0.1" value="<?= $nf($gtbSlOverride) ?>"
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm px-2 py-1.5 focus:ring-primary focus:border-primary">
                    <p class="text-[11px] text-gray-400 mt-0.5">e.g. 0.8. Keep it tight. 0 = template default.</p>
                </div>
            </div>
            <p class="text-[11px] text-amber-600 dark:text-amber-400"><i class="fas fa-triangle-exclamation mr-0.5"></i> Fees are ~0.2% round-trip, so a target below ~0.5% barely clears costs. Small targets need a high win-rate to net positive.</p>
        </div>

        <!-- Session & time-boxing -->
        <div class="rounded-xl border p-4 space-y-3 border-gray-200 dark:border-gray-700">
            <h3 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-hourglass-half text-primary mr-1.5"></i>Session &amp; time-boxing</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Hard time limits the bot enforces itself (not just AI guidance). These free up the slot so it keeps cycling within your window.</p>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="max_hold_min" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Max hold per trade (min)</label>
                    <input type="number" id="max_hold_min" min="0" step="5" value="<?= (int) $gtbMaxHoldMin ?>"
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm px-2 py-1.5 focus:ring-primary focus:border-primary">
                    <p class="text-[11px] text-gray-400 mt-0.5">Auto-closes any trade older than this. 0 = off.</p>
                </div>
                <div>
                    <label for="session_hours" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Session length (hours)</label>
                    <input type="number" id="session_hours" min="0" step="0.5" value="<?= rtrim(rtrim(number_format((float) $gtbSessionHours, 2, '.', ''), '0'), '.') ?: '0' ?>"
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm px-2 py-1.5 focus:ring-primary focus:border-primary">
                    <p class="text-[11px] text-gray-400 mt-0.5">After this long, it flattens &amp; stops opening. Resets on Start. 0 = off.</p>
                </div>
                <div>
                    <label for="stall_minutes" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Stall exit (min)</label>
                    <input type="number" id="stall_minutes" min="0" step="1" value="<?= (int) $gtbStallMin ?>"
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm px-2 py-1.5 focus:ring-primary focus:border-primary">
                    <p class="text-[11px] text-gray-400 mt-0.5">Rotate out if a trade hasn't followed through after this long. 0 = off.</p>
                </div>
                <div>
                    <label for="stall_min_gain" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">…unless up at least (%)</label>
                    <input type="number" id="stall_min_gain" min="0" step="0.5" value="<?= $nf($gtbStallGain) ?>"
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm px-2 py-1.5 focus:ring-primary focus:border-primary">
                    <p class="text-[11px] text-gray-400 mt-0.5">Keep the trade past the stall time only if it's gained this much.</p>
                </div>
                <div>
                    <label for="session_max_loss" class="block text-xs font-medium text-red-600 dark:text-red-400 mb-1"><i class="fas fa-shield-halved mr-0.5"></i>Max session loss ($)</label>
                    <input type="number" id="session_max_loss" min="0" step="1" value="<?= $nf($gtbSessionMaxLoss) ?>"
                           class="w-full rounded-lg border border-red-300 dark:border-red-700 bg-white dark:bg-gray-800 text-sm px-2 py-1.5 focus:ring-red-500 focus:border-red-500">
                    <p class="text-[11px] text-gray-400 mt-0.5"><strong>Circuit breaker</strong>: stop opening new trades once the session is down this much. 0 = off.</p>
                </div>
            </div>
        </div>

        <!-- Operator instructions -->
        <div class="rounded-xl border p-4 space-y-2.5 border-gray-200 dark:border-gray-700">
            <h3 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-wand-magic-sparkles text-primary mr-1.5"></i>Operator instructions <span class="text-xs font-normal text-gray-400">(optional)</span></h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Plain-English steering fed into <em>every</em> AI trade decision — e.g. focus/avoid certain coins, be more or less aggressive, cap what you'll chase. The bot follows these <strong>within</strong> the hard risk rules (capital cap, per-trade size, mandatory stop-loss) — they can tighten behavior but never loosen a safety rule.</p>
            <textarea id="custom_instructions" rows="5" maxlength="2000"
                      class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 focus:ring-primary focus:border-primary"
                      placeholder="e.g. Only trade BTC, ETH and SOL. Skip anything already up more than 15% today. Prefer breakouts over dip-buys. Be conservative — when unsure, skip."><?= htmlspecialchars($gtbInstructions, ENT_QUOTES) ?></textarea>
            <p class="text-xs text-gray-400 dark:text-gray-500">Applied on the next decision after saving. Leave blank to remove.</p>
        </div>

        <!-- PineScript strategy -->
        <div class="rounded-xl border p-4 space-y-2.5 border-gray-200 dark:border-gray-700">
            <h3 class="font-semibold text-gray-900 dark:text-white"><i class="fas fa-chart-line text-primary mr-1.5"></i>PineScript strategy <span class="text-xs font-normal text-gray-400">(optional)</span></h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Paste a TradingView PineScript strategy (e.g. one you found in the trading community). The AI <strong>reads its entry/exit logic and applies that intent</strong> to every decision — it is <em>not</em> executed. <strong>Inspect it first</strong> for bugs/risk, then Save to apply. Always keep a stop-loss.</p>
            <textarea id="pinescript" rows="8" maxlength="8000" spellcheck="false"
                      class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 font-mono text-xs px-3 py-2 focus:ring-primary focus:border-primary"
                      placeholder="//@version=5&#10;strategy(&quot;My Momentum&quot;, overlay=true)&#10;// paste a community strategy here…"><?= htmlspecialchars($gtbPineScript, ENT_QUOTES) ?></textarea>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="gtbPineReview()" class="inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:border-primary hover:text-primary"><i class="fas fa-magnifying-glass"></i> Inspect with AI</button>
                <button type="button" onclick="gtbPineSuggest()" class="inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:border-primary hover:text-primary"><i class="fas fa-lightbulb"></i> Suggest one</button>
                <span id="gtb-pine-status" class="text-xs"></span>
            </div>
            <pre id="gtb-pine-out" class="hidden whitespace-pre-wrap text-xs bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-3 max-h-64 overflow-y-auto"></pre>
            <p class="text-xs text-gray-400 dark:text-gray-500">Inspect/Suggest just show AI output — nothing is applied until you press <strong>Save settings</strong>.</p>
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

    async function gtbPineCall(body, btnLabel) {
        const status = document.getElementById('gtb-pine-status');
        const out = document.getElementById('gtb-pine-out');
        status.textContent = btnLabel + '…'; status.className = 'text-xs text-gray-400';
        try {
            const res = await fetch('/gtb/bot/pine', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': GTB_CSRF },
                body: JSON.stringify(Object.assign({ csrf_token: GTB_CSRF }, body)),
            });
            const d = await res.json();
            if (d.ok) {
                out.textContent = d.text || '(no output)';
                out.classList.remove('hidden');
                status.textContent = 'Done' + (d.cost ? ' · $' + (+d.cost).toFixed(5) : '');
                status.className = 'text-xs text-green-600 dark:text-green-400';
            } else {
                status.textContent = d.error || 'Failed'; status.className = 'text-xs text-red-500';
            }
        } catch (e) { status.textContent = 'Network error'; status.className = 'text-xs text-red-500'; }
    }
    function gtbPineReview() {
        const code = document.getElementById('pinescript').value.trim();
        if (!code) { const s = document.getElementById('gtb-pine-status'); s.textContent = 'Paste a script first.'; s.className = 'text-xs text-amber-500'; return; }
        gtbPineCall({ action: 'review', pinescript: code }, 'Inspecting');
    }
    function gtbPineSuggest() {
        gtbPineCall({ action: 'suggest', hint: document.getElementById('custom_instructions').value || '' }, 'Generating');
    }

    function gtbSyncProvider() {
        const p = document.getElementById('ai_provider').value;
        // Both provider cards stay visible; the dropdown just marks which one is active.
        document.getElementById('prov-badge-groq').classList.toggle('hidden', p !== 'groq');
        document.getElementById('prov-badge-anthropic').classList.toggle('hidden', p !== 'anthropic');
        document.getElementById('prov-groq').classList.toggle('ring-2', p === 'groq');
        document.getElementById('prov-groq').classList.toggle('ring-primary', p === 'groq');
        document.getElementById('prov-anthropic').classList.toggle('ring-2', p === 'anthropic');
        document.getElementById('prov-anthropic').classList.toggle('ring-primary', p === 'anthropic');
    }

    document.querySelectorAll('.gtb-cap').forEach(r => r.addEventListener('change', () => {
        const sel = document.querySelector('.gtb-cap:checked');
        document.getElementById('cap-warn').classList.toggle('hidden', !sel || sel.value === 'staked');
    }));

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
            ai_provider: document.getElementById('ai_provider').value,
            anthropic_api_key: document.getElementById('anthropic_api_key').value,
            scan_model: document.getElementById('scan_model').value,
            decision_model: document.getElementById('decision_model').value,
            groq_api_key: document.getElementById('groq_api_key').value,
            groq_scan_model: document.getElementById('groq_scan_model').value,
            groq_decision_model: document.getElementById('groq_decision_model').value,
            capital_mode: (document.querySelector('.gtb-cap:checked') || {}).value || 'staked',
            paper_wallet: parseFloat(document.getElementById('paper_wallet').value) || 0,
            max_hold_min: parseInt(document.getElementById('max_hold_min').value) || 0,
            session_hours: parseFloat(document.getElementById('session_hours').value) || 0,
            stall_minutes: parseInt(document.getElementById('stall_minutes').value) || 0,
            stall_min_gain: parseFloat(document.getElementById('stall_min_gain').value) || 0,
            session_max_loss: parseFloat(document.getElementById('session_max_loss').value) || 0,
            tp_override: parseFloat(document.getElementById('tp_override').value) || 0,
            sl_override: parseFloat(document.getElementById('sl_override').value) || 0,
            base_capital: parseFloat(document.getElementById('base_capital').value) || 0,
            min_notional: parseFloat(document.getElementById('min_notional').value) || 0,
            max_trade: parseFloat(document.getElementById('max_trade').value) || 0,
            growth_unit: parseFloat(document.getElementById('growth_unit').value) || 0,
            wallet_floor: parseFloat(document.getElementById('wallet_floor').value) || 0,
            profiles: Array.from(document.querySelectorAll('.gtb-prof:checked')).map(c => c.value),
            templates: Array.from(document.querySelectorAll('.gtb-tpl:checked')).map(c => c.value),
            memory_enabled: document.getElementById('memory_enabled').checked,
            custom_instructions: document.getElementById('custom_instructions').value,
            pinescript: document.getElementById('pinescript').value,
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
                ['mainnet_api_secret', 'testnet_api_secret', 'anthropic_api_key', 'groq_api_key'].forEach(id => {
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

    document.addEventListener('DOMContentLoaded', () => { gtbSyncActive(); gtbSyncProvider(); });
</script>
