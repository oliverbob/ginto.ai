<?php
/** @var string $title */
/** @var string $csrf_token */
/** @var array $countries */
/** @var array $old */
/** @var string|null $error */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Ginto') ?></title>
    <link href="/assets/css/tailwind.css" rel="stylesheet">
    <style>
        .toggle-password { cursor: pointer; }
        .form-section { display: none; }
        .form-section.active { display: block; }
    </style>
</head>
<body class="bg-gradient-to-br from-amber-300 to-yellow-500 min-h-screen flex items-center justify-center p-6">

<div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-8">

    <!-- Toggle Buttons -->
    <div class="flex justify-center mb-6 space-x-4">
        <button id="showRegister" class="px-4 py-2 font-semibold rounded-lg transition">Register</button>
        <button id="showLogin" class="px-4 py-2 font-semibold rounded-lg transition">Login</button>
    </div>

    <!-- Error -->
    <?php if (!empty($error)): ?>
        <div class="mb-4 p-3 bg-red-100 text-red-700 border border-red-300 rounded">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- REGISTER FORM -->
    <form id="registerForm" action="/register" method="POST" class="space-y-5 form-section active">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <!-- Package / Payment hidden fields set from UI -->
        <input type="hidden" name="package" id="selectedPackage" value="<?= htmlspecialchars($old['package'] ?? 'Gold') ?>">
        <input type="hidden" name="package_amount" id="selectedPackageAmount" value="<?= htmlspecialchars($old['package_amount'] ?? 10000) ?>">
        <input type="hidden" name="package_currency" id="selectedPackageCurrency" value="<?= htmlspecialchars($old['package_currency'] ?? 'PHP') ?>">
        <input type="hidden" name="pay_method" id="selectedPayMethod" value="<?= htmlspecialchars($old['pay_method'] ?? 'btcpay') ?>">

        <!-- Fullname -->
        <div>
            <input type="text" name="fullname" required
                   placeholder="Full name"
                   value="<?= htmlspecialchars($old['fullname'] ?? '') ?>"
                   class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-amber-500">
        </div>

        <!-- Username -->
        <div>
            <input type="text" name="username" required
                   placeholder="Username"
                   value="<?= htmlspecialchars($old['username'] ?? '') ?>"
                   class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-amber-500">
        </div>

        <!-- Email -->
        <div>
            <input type="email" name="email" required
                   placeholder="Email"
                   value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                   class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-amber-500">
        </div>

        <!-- Country -->
        <div>
            <select name="country" id="countrySelect" required
                    class="w-full border rounded-lg p-3 bg-white focus:ring-2 focus:ring-amber-500">
                <option value="" disabled <?= empty($old['country']) ? 'selected' : '' ?>>Select your country</option>
                <?php foreach ($countries as $code => $c): ?>
                    <option value="<?= $code ?>" <?= ($old['country'] ?? '') === $code ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                        <?= $c['dial_code'] ? ' (' . $c['dial_code'] . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Phone Number -->
        <div>
            <input type="text" name="phone" required
                id="phoneInput"
                placeholder="Phone"
                inputmode="numeric"
                pattern="[0-9]*"
                value="<?= htmlspecialchars($old['phone'] ?? '') ?>"
                class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-amber-500">
        </div>

        <!-- Password -->
        <div class="relative">
            <input type="password" name="password" id="password" required
                   placeholder="Password"
                   class="w-full border rounded-lg p-3 pr-12 focus:ring-2 focus:ring-amber-500">
            <button type="button" id="togglePassword" class="absolute inset-y-0 right-3 flex items-center text-gray-500 hover:text-amber-600">
                <svg id="eye-open" class="h-5 w-5 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <svg id="eye-closed" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7 1.274-4.057 5.065-7 9.542-7 1.05 0 2.05.15 3 .425M12 5c4.477 0 8.268 2.943 9.542 7a10.04 10.04 0 01-1.5 3.5M16.5 13.5a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
                </svg>
            </button>
        </div>

        <!-- PACKAGE SELECTOR -->
        <div class="mt-2">
            <label class="block text-sm font-medium text-gray-700 mb-2">Choose Membership Plan</label>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php
                // Render levels from DB if available; otherwise fallback to defaults
                $renderLevels = [];
                if (!empty($levels) && is_array($levels)) {
                    foreach ($levels as $l) {
                        $renderLevels[] = [
                            'id' => $l['id'],
                            'name' => $l['name'],
                            'amount' => $l['cost_amount'],
                            'currency' => $l['cost_currency'] ?? 'PHP'
                        ];
                    }
                } else {
                    $renderLevels = [
                        ['id'=>1,'name'=>'Starter','amount'=>150,'currency'=>'PHP'],
                        ['id'=>2,'name'=>'Basic','amount'=>1000,'currency'=>'PHP'],
                        ['id'=>3,'name'=>'Silver','amount'=>5000,'currency'=>'PHP'],
                        ['id'=>4,'name'=>'Gold','amount'=>10000,'currency'=>'PHP'],
                        ['id'=>5,'name'=>'Platinum','amount'=>50000,'currency'=>'PHP']
                    ];
                }
                $defaultSelected = $old['package'] ?? 'Gold';
                $colCount = count($renderLevels);
                $idx = 0;
                foreach ($renderLevels as $lvl):
                    $isSelected = ($defaultSelected == ($lvl['name'] ?? $lvl['id']));
                ?>
                <label class="tier-card p-4 rounded-lg border shadow-sm flex flex-col justify-between <?= $isSelected ? 'border-2 border-yellow-500 bg-yellow-50' : '' ?>" data-name="<?= htmlspecialchars($lvl['name']) ?>" data-amount="<?= htmlspecialchars($lvl['amount']) ?>" data-currency="<?= htmlspecialchars($lvl['currency']) ?>">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($lvl['name']) ?></div>
                        <div class="text-lg font-bold text-yellow-600"><?= htmlspecialchars(number_format((float)$lvl['amount'])) ?> <?= htmlspecialchars($lvl['currency']) ?></div>
                    </div>
                    <div class="text-sm text-gray-600">Best for <?= htmlspecialchars($lvl['name']) ?> users</div>
                    <input type="radio" name="package_radio" class="hidden" value="<?= htmlspecialchars($lvl['name']) ?>" <?= $isSelected ? 'checked' : '' ?> />
                </label>
                <?php endforeach; ?>
            </div>

            <!-- Payment method options (BTCPay, PayPal, GCash) -->
            <div class="mt-4 flex items-center space-x-4">
                <label class="inline-flex items-center">
                    <input type="radio" name="pay_method_radio" value="btcpay" class="mr-2" <?= ($old['pay_method'] ?? 'btcpay') === 'btcpay' ? 'checked' : '' ?> />
                    <span class="text-sm">BTCPay / Crypto</span>
                </label>
                <label class="inline-flex items-center">
                    <input type="radio" name="pay_method_radio" value="paypal" class="mr-2" <?= ($old['pay_method'] ?? '') === 'paypal' ? 'checked' : '' ?> />
                    <span class="text-sm">PayPal (radio)</span>
                </label>
                <label class="inline-flex items-center">
                    <input type="radio" name="pay_method_radio" value="gcash" class="mr-2" <?= ($old['pay_method'] ?? '') === 'gcash' ? 'checked' : '' ?> />
                    <span class="text-sm">GCash</span>
                </label>
            </div>
        </div>

        <div class="mt-4 flex items-center space-x-3">
            <button type="button" id="payNowBtn" class="flex-1 bg-yellow-600 hover:bg-yellow-700 text-white font-semibold py-3 rounded-lg shadow-lg transition">Pay / Proceed</button>
            <button type="submit" id="createAccountBtn" class="flex-1 bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600 text-white font-semibold py-3 rounded-lg shadow-lg hover:from-yellow-500 hover:via-yellow-600 hover:to-yellow-700 transition-all duration-300">Create Account</button>
        </div>
    </form>

    <!-- LOGIN FORM -->
    <form id="loginForm" action="/login" method="POST" class="space-y-5 form-section">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <input type="text" name="identifier" required
               placeholder="Email, Username, or Phone"
               class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-amber-500">

        <input type="password" name="password" required
               placeholder="Password"
               class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-amber-500">

        <button type="submit"
                class="w-full bg-gradient-to-r from-amber-500 via-yellow-500 to-amber-600 
                    text-white font-semibold py-3 rounded-lg shadow-lg 
                    hover:from-amber-600 hover:via-yellow-600 hover:to-amber-700 
                    transition-all duration-300">
            Login
        </button>
    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const registerForm = document.getElementById('registerForm');
    const loginForm = document.getElementById('loginForm');
    const btnRegister = document.getElementById('showRegister');
    const btnLogin = document.getElementById('showLogin');

    function setActiveButton(activeBtn, inactiveBtn) {
        activeBtn.classList.remove('bg-gray-200', 'text-gray-800');
        activeBtn.classList.add('bg-amber-500', 'text-white', 'hover:bg-amber-600');
        inactiveBtn.classList.remove('bg-amber-500', 'text-white', 'hover:bg-amber-600');
        inactiveBtn.classList.add('bg-gray-200', 'text-gray-800', 'hover:bg-gray-300');
    }

    btnRegister.addEventListener('click', () => {
        registerForm.classList.add('active');
        loginForm.classList.remove('active');
        setActiveButton(btnRegister, btnLogin);
    });

    btnLogin.addEventListener('click', () => {
        loginForm.classList.add('active');
        registerForm.classList.remove('active');
        setActiveButton(btnLogin, btnRegister);
    });

    // Password toggle
    const togglePassword = document.getElementById('togglePassword');
    togglePassword.addEventListener('click', () => {
        const input = document.getElementById('password');
        input.type = (input.type === 'password') ? 'text' : 'password';
        document.getElementById('eye-open').classList.toggle('hidden');
        document.getElementById('eye-closed').classList.toggle('hidden');
    });

    // Country Auto-Detect
    const countrySelect = document.getElementById('countrySelect');
    if (countrySelect && !countrySelect.value) {
        fetch('https://ipapi.co/country_code/')
            .then(res => res.text())
            .then(code => {
                code = code.trim().toUpperCase();
                const option = countrySelect.querySelector(`option[value="${code}"]`);
                if (option) countrySelect.value = code;
            })
            .catch(err => console.warn('GeoIP lookup failed:', err));
    }

    // Numeric-only phone input
    const phoneInput = document.getElementById('phoneInput');
    if (phoneInput) {
        phoneInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '');
        });
    }

    // Initial button state
    setActiveButton(btnRegister, btnLogin);
});
</script>

</body>
</html>
