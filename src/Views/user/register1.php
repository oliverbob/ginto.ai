<?php
/** Gold-themed register view for Ginto - Final simplified form for registration */
/** @var string $title */
/** @var array $countries */
/** @var array $old */
/** @var array $levels */
/** @var string|null $error */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($title ?? 'Ginto - Register') ?></title>
  <link href="/assets/css/tailwind.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-yellow-200 to-amber-100 min-h-screen flex items-center justify-center p-6">
  <div class="max-w-5xl w-full bg-white rounded-lg shadow p-8">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <div>
        <h1 class="text-3xl font-bold text-gray-800">Join Ginto</h1>
        <p class="text-gray-600">Choose a plan and register to start earning with Ginto Rewards.</p>

        <form id="gintoRegisterForm" action="/register" method="POST" class="mt-6 space-y-4">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
          <input type="hidden" name="package" id="selectedPackage" value="<?= htmlspecialchars($old['package'] ?? 'Starter') ?>">
          <input type="hidden" name="package_amount" id="selectedPackageAmount" value="<?= htmlspecialchars($old['package_amount'] ?? 150) ?>">
          <input type="hidden" name="package_currency" id="selectedPackageCurrency" value="<?= htmlspecialchars($old['package_currency'] ?? 'PHP') ?>">
          <input type="hidden" name="pay_method" id="selectedPayMethod" value="<?= htmlspecialchars($old['pay_method'] ?? 'btcpay') ?>">

          <input required name="fullname" placeholder="Full name" class="w-full border p-3 rounded-lg" value="<?= htmlspecialchars($old['fullname'] ?? '') ?>">
          <input required name="username" placeholder="Username" class="w-full border p-3 rounded-lg" value="<?= htmlspecialchars($old['username'] ?? '') ?>">
          <input required type="email" name="email" placeholder="Email" class="w-full border p-3 rounded-lg" value="<?= htmlspecialchars($old['email'] ?? '') ?>">
          <select required name="country" class="w-full border p-3 rounded-lg">
            <option value="" disabled <?= empty($old['country']) ? 'selected' : '' ?>>Select your country</option>
            <?php foreach ($countries as $code => $c): ?>
            <option value="<?= $code ?>" <?= ($old['country'] ?? '') === $code ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <input required name="phone" placeholder="Phone" class="w-full border p-3 rounded-lg" value="<?= htmlspecialchars($old['phone'] ?? '') ?>">
          <input required type="password" name="password" placeholder="Password" class="w-full border p-3 rounded-lg">

          <!-- Package cards -->
          <div class="mt-4">
            <label class="block text-sm font-semibold mb-2">Choose a plan</label>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <?php
                $renderLevels = [];
                if (!empty($levels) && is_array($levels)) {
                    $limited = array_slice($levels, 0, 3);
                    foreach ($limited as $l) {
                        $renderLevels[] = ['name' => $l['name'], 'amount' => $l['cost_amount'], 'currency' => $l['cost_currency'] ?? 'PHP'];
                    }
                } else {
                    $renderLevels = [
                        ['name' => 'Starter', 'amount' => 150, 'currency' => 'PHP'],
                        ['name' => 'Basic', 'amount' => 1000, 'currency' => 'PHP'],
                        ['name' => 'Silver', 'amount' => 5000, 'currency' => 'PHP']
                    ];
                }
                $default = $old['package'] ?? ($renderLevels[0]['name'] ?? 'Starter');
                foreach ($renderLevels as $lvl):
                    $isSelected = ($default === $lvl['name']);
              ?>
              <label class="tier-card border p-4 rounded-lg text-gray-800 hover:shadow-lg <?= $isSelected ? 'border-2 border-yellow-500 bg-yellow-50' : '' ?>" data-name="<?= htmlspecialchars($lvl['name']) ?>" data-amount="<?= htmlspecialchars($lvl['amount']) ?>" data-currency="<?= htmlspecialchars($lvl['currency']) ?>">
                <div class="flex justify-between items-center">
                  <strong><?= htmlspecialchars($lvl['name']) ?></strong>
                  <span class="text-yellow-600 font-bold"><?= htmlspecialchars(number_format((float)$lvl['amount'])) ?> <?= htmlspecialchars($lvl['currency']) ?></span>
                </div>
                <input type="radio" name="package_radio" class="hidden" value="<?= htmlspecialchars($lvl['name']) ?>" <?= $isSelected ? 'checked' : '' ?> />
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="mt-4 flex items-center space-x-3">
            <label class="inline-flex items-center">
              <input type="radio" name="pay_method_radio" value="btcpay" class="mr-2" <?= ($old['pay_method'] ?? 'btcpay') === 'btcpay' ? 'checked' : '' ?> />
              <span class="text-sm">BTCPay / Crypto</span>
            </label>
            <label class="inline-flex items-center">
              <input type="radio" name="pay_method_radio" value="paypal" class="mr-2" <?= ($old['pay_method'] ?? '') === 'paypal' ? 'checked' : '' ?> />
              <span class="text-sm">PayPal</span>
            </label>
            <label class="inline-flex items-center">
              <input type="radio" name="pay_method_radio" value="gcash" class="mr-2" <?= ($old['pay_method'] ?? '') === 'gcash' ? 'checked' : '' ?> />
              <span class="text-sm">GCash</span>
            </label>
          </div>

          <div class="mt-4 flex items-center space-x-3">
            <button type="button" id="payNowBtn" class="flex-1 bg-yellow-600 hover:bg-yellow-700 text-white py-3 rounded-lg">Pay / Proceed</button>
            <button type="submit" id="createAccountBtn" class="flex-1 bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600 text-white py-3 rounded-lg">Create Account</button>
          </div>
        </form>
      </div>

      <div class="p-4 bg-yellow-50 rounded-lg">
        <h2 class="text-2xl font-bold text-yellow-700 mb-3">Ginto Benefits</h2>
        <p class="text-gray-700 mb-6">Earn residual commissions, build a network, access learning & tools.</p>
        <ul class="list-disc pl-4 space-y-2 text-gray-700">
          <li>Unilevel commission up to 8 levels</li>
          <li>Fast payouts</li>
          <li>Referral bonuses</li>
        </ul>
      </div>
    </div>
  </div>

<script>
(function () {
  // wire tier selection
  const tierCards = document.querySelectorAll('.tier-card');
  const selectedPackage = document.getElementById('selectedPackage');
  const selectedPackageAmount = document.getElementById('selectedPackageAmount');
  const selectedPackageCurrency = document.getElementById('selectedPackageCurrency');
  const selectedPayMethod = document.getElementById('selectedPayMethod');

  tierCards.forEach(card => {
    card.addEventListener('click', function () {
      tierCards.forEach(c => c.classList.remove('border-2','border-yellow-500','bg-yellow-50'));
      this.classList.add('border-2','border-yellow-500','bg-yellow-50');
      const name = this.getAttribute('data-name');
      const amount = this.getAttribute('data-amount');
      const currency = this.getAttribute('data-currency');
      if (selectedPackage) selectedPackage.value = name || '';
      if (selectedPackageAmount) selectedPackageAmount.value = amount || '';
      if (selectedPackageCurrency) selectedPackageCurrency.value = currency || '';
    });
  });

  // pay method radios

  const payMethodRadios = document.querySelectorAll('input[name="pay_method_radio"]');
  payMethodRadios.forEach(r => r.addEventListener('change', () => {
    if (selectedPayMethod) selectedPayMethod.value = r.value;
  }));

  // Simple behavior for Pay Now: if BTCPay, open a simple modal (or backup to submit)
  const payNowBtn = document.getElementById('payNowBtn');
  if (payNowBtn) payNowBtn.addEventListener('click', () => {
    const method = document.querySelector('input[name="pay_method_radio"]:checked')?.value || 'btcpay';
    if (method === 'btcpay') {
      // For now: submit the form and let server record a pending order; real BTCPay modal would be added later
      document.getElementById('createAccountBtn').click();
    } else {
      document.getElementById('createAccountBtn').click();
    }
  });
})();
</script>

</body>
</html>
